<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\Avisos;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

/**
 * Publicar por FTP con la extensión `ftp` de PHP: una sola conexión para todo
 * el despliegue, en modo pasivo. Por defecto, cifrada (FTPS explícito, con
 * `AUTH TLS`); sin cifrar solo con `cifrado: no`, porque FTP a secas manda la
 * contraseña a la vista.
 *
 * Si una operación falla, se pregunta al servidor si la conexión sigue viva.
 * Si se ha cortado, se vuelve a conectar y se repite. Si sigue viva, el
 * servidor ha dicho que no; puede ser algo pasajero (una subida que le llegó
 * mal, que vsftpd cuenta con «Failure reading network stream»), así que se
 * repite una vez, y si vuelve a decir que no, es un error. En total, hasta
 * dos repeticiones, y cada una deja un aviso. En los alojamientos compartidos
 * estas cosas pasan de vez en cuando, y un despliegue no debería depender de
 * ellas. La extensión no da el código de la respuesta, que diría si el fallo
 * es pasajero (4xx) o no (5xx); por eso se repite una vez sin saberlo.
 *
 * Algunos servidores FTPS cortan siempre la subida de ciertos tamaños exactos
 * de fichero, sea cual sea su contenido, y repetir no sirve. Como último
 * recurso, un fichero que no sube entero se sube en dos partes: la primera
 * normal y el resto añadido al final (APPE), partiendo por otro punto si
 * tampoco así, y comprobando que al final mide lo que tiene que medir.
 *
 * La extensión cifra pero no comprueba el certificado del servidor: protege
 * de quien escucha, no de quien se haga pasar por el servidor. Para eso está
 * SFTP, con la huella del servidor.
 */
final class Ftp implements Destino
{
    private const int ESPERA = 30;

    /**
     * Por dónde se parte, en orden, un fichero que no sube entero. Los que
     * miden menos que el corte más pequeño no se parten.
     */
    private const array CORTES = [32768, 20000, 45000, 12000, 52000, 7000];

    private \FTP\Connection $conexion;
    private string $base;

    /** @var array<string, true> carpetas que ya existen o se han creado */
    private array $carpetas = [];

    /**
     * @param list<int> $esperas segundos antes de cada reconexión
     *
     * @throws ErrorDeProyecto si falta la extensión o no se puede entrar en el servidor
     */
    public function __construct(
        private readonly Configuracion $configuracion,
        private readonly Avisos $avisos = new Avisos(),
        private readonly array $esperas = [1, 5],
    ) {
        if (!extension_loaded('ftp')) {
            throw new ErrorDeProyecto(
                'Para desplegar por FTP hace falta la extensión ftp de PHP, que viene con PHP pero puede estar desactivada. '
                    . 'Se activa en php.ini con la línea «extension=ftp»',
            );
        }

        $this->conexion = $this->conectar();
        $this->base = rtrim(str_replace('\\', '/', $configuracion->ruta), '/');
    }

    public function leer(string $ruta): ?string
    {
        $remota = $this->remota($ruta);

        return $this->intentar("leer {$ruta} del servidor", function () use ($remota) {
            $temporal = fopen('php://temp', 'r+');

            try {
                if ($temporal !== false && @ftp_fget($this->conexion, $temporal, $remota, FTP_BINARY)) {
                    rewind($temporal);

                    return ['hecho' => (string) stream_get_contents($temporal)];
                }
            } finally {
                is_resource($temporal) && fclose($temporal);
            }

            // Con la conexión viva, que no se pueda leer porque no existe no es un error.
            return $this->viva() && !$this->existe($remota) ? ['hecho' => null] : false;
        });
    }

    public function subir(string $ruta, string $origen): void
    {
        $remota = $this->remota($ruta);

        try {
            $this->intentar("subir {$ruta}", function () use ($remota, $origen) {
                $this->prepararCarpeta(Rutas::carpeta($remota));

                return @ftp_put($this->conexion, $remota, $origen, FTP_BINARY) ? ['hecho' => null] : false;
            });
        } catch (ErrorDeProyecto $error) {
            $this->enDosPartes($ruta, $remota, fn () => fopen($origen, 'rb'), (int) filesize($origen), $error);
        }
    }

    public function escribir(string $ruta, string $contenido): void
    {
        $remota = $this->remota($ruta);

        try {
            $this->intentar("subir {$ruta}", function () use ($remota, $contenido) {
                $this->prepararCarpeta(Rutas::carpeta($remota));
                $temporal = self::flujo($contenido);

                if ($temporal === false) {
                    return false;
                }

                try {
                    return @ftp_fput($this->conexion, $remota, $temporal, FTP_BINARY) ? ['hecho' => null] : false;
                } finally {
                    fclose($temporal);
                }
            });
        } catch (ErrorDeProyecto $error) {
            $this->enDosPartes($ruta, $remota, fn () => self::flujo($contenido), strlen($contenido), $error);
        }
    }

    public function borrar(string $ruta): void
    {
        $remota = $this->remota($ruta);

        $this->intentar("borrar {$ruta} del servidor", function () use ($remota) {
            if (@ftp_delete($this->conexion, $remota)) {
                return ['hecho' => null];
            }

            // Si ya no existe, está hecho.
            return $this->viva() && !$this->existe($remota) ? ['hecho' => null] : false;
        });
    }

    public function quitarCarpeta(string $ruta): void
    {
        $remota = $this->remota($ruta);

        if (@ftp_rmdir($this->conexion, $remota)) {
            unset($this->carpetas[$remota]);
        }
    }

    public function cerrar(): void
    {
        @ftp_close($this->conexion);
    }

    /**
     * Hace una operación y, si falla, la repite como se explica arriba.
     *
     * @param \Closure(): (array{hecho: mixed}|false) $operacion
     */
    private function intentar(string $que, \Closure $operacion): mixed
    {
        $repetida = false;

        for ($intento = 0; ; $intento++) {
            error_clear_last();
            $resultado = $operacion();

            if ($resultado !== false) {
                return $resultado['hecho'];
            }

            $motivo = self::motivo();
            $viva = $this->viva();

            if ($intento >= count($this->esperas) || ($viva && $repetida)) {
                throw new ErrorDeProyecto("No se puede {$que}{$motivo}");
            }

            sleep($this->esperas[$intento]);

            if ($viva) {
                $repetida = true;
                $this->avisos->registrar("Falló al {$que}{$motivo}; se ha vuelto a intentar");

                continue;
            }

            @ftp_close($this->conexion);

            try {
                $this->conexion = $this->conectar();
            } catch (ErrorDeProyecto $error) {
                throw new ErrorDeProyecto("Se ha cortado la conexión al {$que}{$motivo}, y no se puede volver a conectar: {$error->descripcion}", anterior: $error);
            }

            // Lo que se creó antes sigue allí, pero se vuelve a comprobar.
            $this->carpetas = [];
            $this->avisos->registrar("Se cortó la conexión con {$this->configuracion->servidor} al {$que}{$motivo}; se ha vuelto a conectar");
        }
    }

    /**
     * El último recurso para un fichero que no ha subido entero (ver arriba).
     * Si tampoco sube así, el error es el de la subida entera.
     *
     * @param \Closure(): (resource|false) $abrir abre lo que se sube, desde el principio
     *
     * @throws ErrorDeProyecto
     */
    private function enDosPartes(string $ruta, string $remota, \Closure $abrir, int $tamano, ErrorDeProyecto $error): void
    {
        $cortes = array_filter(self::CORTES, fn (int $corte) => $corte < $tamano);

        foreach ($cortes as $corte) {
            if (!$this->viva()) {
                @ftp_close($this->conexion);

                try {
                    $this->conexion = $this->conectar();
                } catch (ErrorDeProyecto) {
                    throw $error;
                }

                $this->carpetas = [];
            }

            $this->prepararCarpeta(Rutas::carpeta($remota));

            if ($this->intentarEnDosPartes($remota, $abrir, $corte, $tamano)) {
                $this->avisos->registrar("{$ruta} no subía entero; se ha subido en dos partes");

                return;
            }
        }

        if ($cortes !== []) {
            $this->avisos->registrar("Tampoco se ha podido subir {$ruta} en dos partes");
        }

        throw $error;
    }

    /**
     * Sube hasta `$corte` con STOR y el resto con APPE, que la extensión solo
     * sabe mandar desde un fichero: va a uno temporal del sistema, fuera del
     * proyecto, que se borra al terminar.
     *
     * @param \Closure(): (resource|false) $abrir
     */
    private function intentarEnDosPartes(string $remota, \Closure $abrir, int $corte, int $tamano): bool
    {
        $origen = $abrir();
        $primera = fopen('php://temp', 'r+');
        $resto = tempnam(sys_get_temp_dir(), 'ehundu-ftp-');

        try {
            if ($origen === false || $primera === false || $resto === false) {
                return false;
            }

            stream_copy_to_stream($origen, $primera, $corte);
            rewind($primera);
            $destino = fopen($resto, 'wb');

            if ($destino === false) {
                return false;
            }

            stream_copy_to_stream($origen, $destino);
            fclose($destino);

            if (!@ftp_fput($this->conexion, $remota, $primera, FTP_BINARY) || !@ftp_append($this->conexion, $remota, $resto, FTP_BINARY)) {
                return false;
            }

            // Si el servidor no dice cuánto mide (no todos responden a SIZE), valen sus dos respuestas
            $mide = @ftp_size($this->conexion, $remota);

            return $mide === -1 || $mide === $tamano;
        } finally {
            is_resource($origen) && fclose($origen);
            is_resource($primera) && fclose($primera);
            $resto !== false && @unlink($resto);
        }
    }

    /**
     * Un texto como flujo que se puede subir con `ftp_fput`.
     *
     * @return resource|false
     */
    private static function flujo(string $contenido)
    {
        $flujo = fopen('php://temp', 'r+');

        if ($flujo !== false) {
            fwrite($flujo, $contenido);
            rewind($flujo);
        }

        return $flujo;
    }

    /**
     * Si la conexión sigue abierta: el servidor contesta a NOOP, sea lo que
     * sea lo que diga.
     */
    private function viva(): bool
    {
        $respuesta = @ftp_raw($this->conexion, 'NOOP');

        return is_array($respuesta) && $respuesta !== [] && preg_match('/^\d{3}/', (string) $respuesta[0]) === 1;
    }

    private function conectar(): \FTP\Connection
    {
        $servidor = (string) $this->configuracion->servidor;
        $puerto = $this->configuracion->puerto ?? 21;
        error_clear_last();
        $conexion = $this->configuracion->cifrado
            ? @ftp_ssl_connect($servidor, $puerto, self::ESPERA)
            : @ftp_connect($servidor, $puerto, self::ESPERA);

        if ($conexion === false) {
            throw new ErrorDeProyecto("No se puede conectar con {$servidor}:{$puerto}" . self::motivo(), Proyecto::SITIO);
        }

        error_clear_last();

        if (!@ftp_login($conexion, (string) $this->configuracion->usuario, (string) $this->configuracion->clave)) {
            $motivo = self::motivo();
            @ftp_close($conexion);

            throw new ErrorDeProyecto(
                "No se puede entrar en {$servidor} como {$this->configuracion->usuario}{$motivo}"
                    . ($this->configuracion->cifrado ? '. Si el servidor no admite FTPS, se puede desactivar con «cifrado: no», pero la contraseña viajaría a la vista' : ''),
                Proyecto::SITIO,
            );
        }

        // Muchos servidores detrás de un router anuncian para el modo pasivo
        // una dirección interna; se usa siempre la del propio servidor.
        ftp_set_option($conexion, FTP_USEPASVADDRESS, false);
        ftp_pasv($conexion, true);

        return $conexion;
    }

    private function remota(string $ruta): string
    {
        return $this->base === '' ? $ruta : "{$this->base}/{$ruta}";
    }

    /**
     * Crea la carpeta y las que falten por encima. Que una ya exista no es un
     * error: FTP no tiene una forma común de preguntarlo, así que se intenta
     * crear y se sigue; si de verdad no se ha podido, fallará la subida.
     */
    private function prepararCarpeta(string $carpeta): void
    {
        if ($carpeta === '.' || $carpeta === '/' || $carpeta === '' || isset($this->carpetas[$carpeta])) {
            return;
        }

        $this->prepararCarpeta(Rutas::carpeta($carpeta));
        @ftp_mkdir($this->conexion, $carpeta);
        $this->carpetas[$carpeta] = true;
    }

    private function existe(string $remota): bool
    {
        if (@ftp_size($this->conexion, $remota) >= 0) {
            return true;
        }

        // No todos los servidores responden a SIZE: se mira la carpeta.
        $carpeta = Rutas::carpeta($remota);
        $lista = @ftp_nlist($this->conexion, $carpeta === '.' ? '' : $carpeta);

        if ($lista === false) {
            return false;
        }

        return in_array(basename($remota), array_map('basename', $lista), true);
    }

    private static function motivo(): string
    {
        $error = error_get_last();
        $mensaje = is_array($error) ? (string) preg_replace('/^ftp_\w+\(\):\s*/', '', $error['message']) : '';
        error_clear_last();

        return $mensaje === '' ? '' : " (el servidor dice: {$mensaje})";
    }
}
