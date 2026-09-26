<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

/**
 * Publicar por FTP con la extensión `ftp` de PHP: una sola conexión para todo
 * el despliegue, en modo pasivo. Por defecto, cifrada (FTPS explícito, con
 * `AUTH TLS`); sin cifrar solo con `cifrado: no`, porque FTP a secas manda la
 * contraseña a la vista.
 *
 * La extensión cifra pero no comprueba el certificado del servidor: protege
 * de quien escucha, no de quien se haga pasar por el servidor. Para eso está
 * SFTP, con la huella del servidor.
 */
final class Ftp implements Destino
{
    private const int ESPERA = 30;

    private \FTP\Connection $conexion;
    private string $base;

    /** @var array<string, true> carpetas que ya existen o se han creado */
    private array $carpetas = [];

    /**
     * @throws ErrorDeProyecto si falta la extensión o no se puede entrar en el servidor
     */
    public function __construct(Configuracion $configuracion)
    {
        if (!extension_loaded('ftp')) {
            throw new ErrorDeProyecto(
                'Para desplegar por FTP hace falta la extensión ftp de PHP, que viene con PHP pero puede estar desactivada. '
                    . 'Se activa en php.ini con la línea «extension=ftp»',
            );
        }

        $servidor = (string) $configuracion->servidor;
        $puerto = $configuracion->puerto ?? 21;
        error_clear_last();
        $conexion = $configuracion->cifrado
            ? @ftp_ssl_connect($servidor, $puerto, self::ESPERA)
            : @ftp_connect($servidor, $puerto, self::ESPERA);

        if ($conexion === false) {
            throw new ErrorDeProyecto("No se puede conectar con {$servidor}:{$puerto}" . self::motivo(), Proyecto::SITIO);
        }

        error_clear_last();

        if (!@ftp_login($conexion, (string) $configuracion->usuario, (string) $configuracion->clave)) {
            $motivo = self::motivo();
            @ftp_close($conexion);

            throw new ErrorDeProyecto(
                "No se puede entrar en {$servidor} como {$configuracion->usuario}{$motivo}"
                    . ($configuracion->cifrado ? '. Si el servidor no admite FTPS, se puede desactivar con «cifrado: no», pero la contraseña viajaría a la vista' : ''),
                Proyecto::SITIO,
            );
        }

        // Muchos servidores detrás de un router anuncian para el modo pasivo
        // una dirección interna; se usa siempre la del propio servidor.
        ftp_set_option($conexion, FTP_USEPASVADDRESS, false);
        ftp_pasv($conexion, true);

        $this->conexion = $conexion;
        $this->base = rtrim(str_replace('\\', '/', $configuracion->ruta), '/');
    }

    public function leer(string $ruta): ?string
    {
        $remota = $this->remota($ruta);
        $temporal = fopen('php://temp', 'r+');

        if ($temporal === false) {
            throw new ErrorDeProyecto("No se puede leer {$ruta} del servidor");
        }

        try {
            error_clear_last();

            if (@ftp_fget($this->conexion, $temporal, $remota, FTP_BINARY)) {
                rewind($temporal);

                return (string) stream_get_contents($temporal);
            }

            if ($this->existe($remota)) {
                throw new ErrorDeProyecto("No se puede leer {$ruta} del servidor" . self::motivo());
            }

            return null;
        } finally {
            fclose($temporal);
        }
    }

    public function subir(string $ruta, string $origen): void
    {
        $remota = $this->remota($ruta);
        $this->prepararCarpeta(Rutas::carpeta($remota));

        error_clear_last();

        if (!@ftp_put($this->conexion, $remota, $origen, FTP_BINARY)) {
            throw new ErrorDeProyecto("No se puede subir {$ruta}" . self::motivo());
        }
    }

    public function escribir(string $ruta, string $contenido): void
    {
        $remota = $this->remota($ruta);
        $this->prepararCarpeta(Rutas::carpeta($remota));
        $temporal = fopen('php://temp', 'r+');

        if ($temporal === false) {
            throw new ErrorDeProyecto("No se puede subir {$ruta}");
        }

        try {
            fwrite($temporal, $contenido);
            rewind($temporal);

            error_clear_last();

            if (!@ftp_fput($this->conexion, $remota, $temporal, FTP_BINARY)) {
                throw new ErrorDeProyecto("No se puede subir {$ruta}" . self::motivo());
            }
        } finally {
            fclose($temporal);
        }
    }

    public function borrar(string $ruta): void
    {
        $remota = $this->remota($ruta);

        error_clear_last();

        if (!@ftp_delete($this->conexion, $remota) && $this->existe($remota)) {
            throw new ErrorDeProyecto("No se puede borrar {$ruta} del servidor" . self::motivo());
        }
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
