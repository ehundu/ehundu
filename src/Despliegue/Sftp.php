<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP as ClienteSftp;

/**
 * Publicar por SFTP, con phpseclib: PHP puro, sin extensiones.
 *
 * Antes de mandar la clave se comprueba que el servidor es quien dice ser,
 * con la huella de su clave (`huella` en `sitio.yml`, como la da
 * `ssh-keygen -l`: `SHA256:…`). Si falta, el despliegue se detiene y dice
 * cuál es, para que se compruebe y se anote; si no coincide, se detiene.
 */
final class Sftp implements Destino
{
    private const int ESPERA = 30;

    private ClienteSftp $sftp;
    private string $base;

    /** @var array<string, true> carpetas que ya existen o se han creado */
    private array $carpetas = [];

    /**
     * @throws ErrorDeProyecto si no se puede conectar, la huella no coincide o no se puede entrar
     */
    public function __construct(Configuracion $configuracion, Proyecto $proyecto)
    {
        $servidor = (string) $configuracion->servidor;
        $puerto = $configuracion->puerto ?? 22;

        try {
            $sftp = new ClienteSftp($servidor, $puerto, self::ESPERA);
            $clavePublica = $sftp->getServerPublicHostKey();
        } catch (\Throwable $error) {
            throw new ErrorDeProyecto("No se puede conectar con {$servidor}:{$puerto} ({$error->getMessage()})", Proyecto::SITIO, anterior: $error);
        }

        if ($clavePublica === false) {
            throw new ErrorDeProyecto("No se puede conectar con {$servidor}:{$puerto}: el servidor no demuestra que la clave que presenta sea suya", Proyecto::SITIO);
        }

        $huella = self::huella($clavePublica);

        if ($configuracion->huella === null) {
            throw new ErrorDeProyecto(
                "Falta «huella» en «despliegue». La del servidor es {$huella}: compruébala con quien lo administra "
                    . "(con «ssh-keygen -l -f» sobre su clave pública) y añádela a sitio.yml como «huella: {$huella}»",
                Proyecto::SITIO,
            );
        }

        if (!hash_equals(self::normalizarHuella($configuracion->huella), $huella)) {
            throw new ErrorDeProyecto(
                "La huella del servidor no coincide con la de sitio.yml: es {$huella}. O ha cambiado la clave del servidor, "
                    . 'o alguien se está haciendo pasar por él; no se despliega hasta aclararlo',
                Proyecto::SITIO,
            );
        }

        try {
            $entrada = $configuracion->clavePrivada !== null
                ? $sftp->login((string) $configuracion->usuario, self::clavePrivada($configuracion, $proyecto))
                : $sftp->login((string) $configuracion->usuario, (string) $configuracion->clave);
        } catch (ErrorDeProyecto $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new ErrorDeProyecto("No se puede entrar en {$servidor} como {$configuracion->usuario} ({$error->getMessage()})", Proyecto::SITIO, anterior: $error);
        }

        if (!$entrada) {
            throw new ErrorDeProyecto("No se puede entrar en {$servidor} como {$configuracion->usuario}: el servidor no acepta la clave", Proyecto::SITIO);
        }

        $this->sftp = $sftp;
        $this->base = rtrim(str_replace('\\', '/', $configuracion->ruta), '/');
    }

    /**
     * La huella de una clave pública en formato OpenSSH (`ssh-ed25519 AAAA…`),
     * como la escribe `ssh-keygen -l`: `SHA256:` y el resumen en base64 sin `=`.
     */
    public static function huella(string $clavePublica): string
    {
        $partes = explode(' ', trim($clavePublica));
        $binaria = base64_decode($partes[1] ?? '', true);

        // La clave empieza por su propio tipo, precedido de su longitud.
        $longitud = is_string($binaria) && strlen($binaria) > 4 ? unpack('N', $binaria)[1] : 0;

        if (!is_string($binaria) || $longitud === 0 || substr($binaria, 4, $longitud) !== $partes[0]) {
            throw new \InvalidArgumentException('No es una clave pública en formato OpenSSH');
        }

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $binaria, true)), '=');
    }

    public function leer(string $ruta): ?string
    {
        $remota = $this->remota($ruta);
        $contenido = $this->sftp->get($remota);

        if (is_string($contenido)) {
            return $contenido;
        }

        if ($this->sftp->file_exists($remota)) {
            throw new ErrorDeProyecto("No se puede leer {$ruta} del servidor" . $this->motivo());
        }

        return null;
    }

    public function subir(string $ruta, string $origen): void
    {
        $remota = $this->remota($ruta);
        $this->prepararCarpeta(Rutas::carpeta($remota));

        if (!$this->sftp->put($remota, $origen, ClienteSftp::SOURCE_LOCAL_FILE)) {
            throw new ErrorDeProyecto("No se puede subir {$ruta}" . $this->motivo());
        }
    }

    public function escribir(string $ruta, string $contenido): void
    {
        $remota = $this->remota($ruta);
        $this->prepararCarpeta(Rutas::carpeta($remota));

        if (!$this->sftp->put($remota, $contenido)) {
            throw new ErrorDeProyecto("No se puede subir {$ruta}" . $this->motivo());
        }
    }

    public function borrar(string $ruta): void
    {
        $remota = $this->remota($ruta);

        if (!$this->sftp->delete($remota, false) && $this->sftp->file_exists($remota)) {
            throw new ErrorDeProyecto("No se puede borrar {$ruta} del servidor" . $this->motivo());
        }
    }

    public function quitarCarpeta(string $ruta): void
    {
        $remota = $this->remota($ruta);

        if ($this->sftp->rmdir($remota)) {
            unset($this->carpetas[$remota]);
        }
    }

    public function cerrar(): void
    {
        $this->sftp->disconnect();
    }

    private function remota(string $ruta): string
    {
        return $this->base === '' ? $ruta : "{$this->base}/{$ruta}";
    }

    private function prepararCarpeta(string $carpeta): void
    {
        if ($carpeta === '.' || $carpeta === '/' || $carpeta === '' || isset($this->carpetas[$carpeta])) {
            return;
        }

        if (!$this->sftp->is_dir($carpeta) && !$this->sftp->mkdir($carpeta, -1, true)) {
            throw new ErrorDeProyecto("No se puede crear la carpeta {$carpeta} en el servidor" . $this->motivo());
        }

        $this->carpetas[$carpeta] = true;
    }

    private function motivo(): string
    {
        $error = $this->sftp->getLastSFTPError();

        return $error === '' ? '' : " (el servidor dice: {$error})";
    }

    private static function normalizarHuella(string $huella): string
    {
        $huella = rtrim(trim($huella), '=');

        return str_starts_with($huella, 'SHA256:') ? $huella : "SHA256:{$huella}";
    }

    private static function clavePrivada(Configuracion $configuracion, Proyecto $proyecto): \phpseclib3\Crypt\Common\PrivateKey
    {
        $ruta = str_replace('\\', '/', (string) $configuracion->clavePrivada);
        $completa = preg_match('#^(/|[A-Za-z]:/|~/)#', $ruta) === 1
            ? (str_starts_with($ruta, '~/') ? rtrim((string) (getenv('HOME') ?: getenv('USERPROFILE')), '/\\') . substr($ruta, 1) : $ruta)
            : "{$proyecto->raiz}/{$ruta}";

        $texto = is_file($completa) ? @file_get_contents($completa) : false;

        if ($texto === false) {
            throw new ErrorDeProyecto("No se puede leer la clave privada {$ruta}", Configuracion::SECRETOS);
        }

        try {
            $clave = PublicKeyLoader::load($texto, $configuracion->frase ?? false);
        } catch (\Throwable) {
            throw new ErrorDeProyecto(
                "No se puede usar la clave privada {$ruta}: o no es una clave privada, o lleva frase y «frase» falta o no es la correcta",
                Configuracion::SECRETOS,
            );
        }

        if (!$clave instanceof \phpseclib3\Crypt\Common\PrivateKey) {
            throw new ErrorDeProyecto("{$ruta} es una clave pública; hace falta la privada", Configuracion::SECRETOS);
        }

        return $clave;
    }
}
