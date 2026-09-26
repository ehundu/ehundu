<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\TiposMime;

/**
 * Publicar en un cubo de S3 o de un servicio compatible. `servidor` es la
 * dirección del servicio (`s3.fr-par.scw.cloud`), `usuario` el identificador
 * de la clave de acceso y `clave` la clave secreta; `ruta`, si la hay, es la
 * carpeta dentro del cubo.
 *
 * Las peticiones se firman aquí mismo y van por HTTPS con lo que trae PHP.
 * Cada fichero sube con su tipo MIME y con su MD5, para que el servicio
 * compruebe que le ha llegado entero. El cubo tiene que estar configurado
 * para servir un sitio web; eso no lo toca el motor.
 */
final class S3 implements Destino
{
    private FirmaS3 $firma;
    private string $esquema;
    private string $anfitrion;
    private string $ruta;
    private string $prefijo;

    /** @var \Closure(string, string, array<string, string>, string): array{int, string} */
    private \Closure $http;

    /** @var \Closure(): \DateTimeImmutable */
    private \Closure $reloj;

    /**
     * @param (\Closure(string, string, array<string, string>, string): array{int, string})|null $http
     *        hace una petición y da el código y el cuerpo de la respuesta; para las pruebas
     * @param (\Closure(): \DateTimeImmutable)|null $reloj
     */
    public function __construct(Configuracion $configuracion, ?\Closure $http = null, ?\Closure $reloj = null)
    {
        $servidor = (string) $configuracion->servidor;
        $partes = parse_url(str_contains($servidor, '://') ? $servidor : "https://{$servidor}");

        if (!is_array($partes) || !isset($partes['host'])) {
            throw new ErrorDeProyecto("«servidor» no es una dirección válida: {$servidor}", Proyecto::SITIO);
        }

        $cubo = (string) $configuracion->cubo;
        $anfitrion = $partes['host'] . (isset($partes['port']) ? ":{$partes['port']}" : '');

        // El cubo va en el nombre del servidor (bucket.s3.…), como recomienda
        // AWS, salvo donde no puede: nombres con puntos, direcciones IP,
        // servidores locales o con puerto, que van con el cubo en la ruta.
        $enLaRuta = str_contains($cubo, '.') || $cubo !== strtolower($cubo) || isset($partes['port'])
            || $partes['host'] === 'localhost' || filter_var($partes['host'], FILTER_VALIDATE_IP) !== false;

        $this->esquema = $partes['scheme'] ?? 'https';
        $this->anfitrion = $enLaRuta ? $anfitrion : "{$cubo}.{$anfitrion}";
        $this->ruta = $enLaRuta ? '/' . rawurlencode($cubo) : '';
        $this->prefijo = trim(str_replace('\\', '/', $configuracion->ruta), '/');
        $this->firma = new FirmaS3((string) $configuracion->usuario, (string) $configuracion->clave, (string) $configuracion->region);
        $this->http = $http ?? self::peticionHttp(...);
        $this->reloj = $reloj ?? fn () => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function leer(string $ruta): ?string
    {
        [$estado, $cuerpo] = $this->peticion('GET', $ruta);

        return match (true) {
            $estado === 200 => $cuerpo,
            $estado === 404 => null,
            default => throw $this->error($estado, $cuerpo, "leer {$ruta}"),
        };
    }

    public function subir(string $ruta, string $origen): void
    {
        $contenido = @file_get_contents($origen);

        if ($contenido === false) {
            throw new ErrorDeProyecto("No se puede leer {$origen} para subirlo");
        }

        $this->escribir($ruta, $contenido);
    }

    public function escribir(string $ruta, string $contenido): void
    {
        [$estado, $cuerpo] = $this->peticion('PUT', $ruta, $contenido, [
            'content-md5' => base64_encode(md5($contenido, true)),
            'content-type' => TiposMime::de($ruta),
        ]);

        if ($estado !== 200) {
            throw $this->error($estado, $cuerpo, "subir {$ruta}");
        }
    }

    public function borrar(string $ruta): void
    {
        [$estado, $cuerpo] = $this->peticion('DELETE', $ruta);

        if (!in_array($estado, [200, 204, 404], true)) {
            throw $this->error($estado, $cuerpo, "borrar {$ruta}");
        }
    }

    public function quitarCarpeta(string $ruta): void
    {
        // En S3 no hay carpetas: desaparecen con su último fichero.
    }

    public function cerrar(): void
    {
    }

    /**
     * @param array<string, string> $cabeceras
     *
     * @return array{int, string}
     */
    private function peticion(string $metodo, string $ruta, string $cuerpo = '', array $cabeceras = []): array
    {
        $clave = $this->prefijo === '' ? $ruta : "{$this->prefijo}/{$ruta}";
        $camino = $this->ruta . '/' . FirmaS3::codificarRuta($clave);
        $momento = ($this->reloj)();
        $resumen = hash('sha256', $cuerpo);

        $cabeceras = [
            'host' => $this->anfitrion,
            'x-amz-content-sha256' => $resumen,
            'x-amz-date' => $momento->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            ...$cabeceras,
        ];
        $cabeceras['authorization'] = $this->firma->autorizacion($metodo, $camino, '', $cabeceras, $resumen, $momento);

        return ($this->http)($metodo, "{$this->esquema}://{$this->anfitrion}{$camino}", $cabeceras, $cuerpo);
    }

    private function error(int $estado, string $cuerpo, string $que): ErrorDeProyecto
    {
        $codigo = preg_match('#<Code>([^<]*)</Code>#', $cuerpo, $partes) === 1 ? " {$partes[1]}" : '';
        $mensaje = preg_match('#<Message>([^<]*)</Message>#', $cuerpo, $partes) === 1 ? ": {$partes[1]}" : '';

        return new ErrorDeProyecto("No se puede {$que}: el servicio responde {$estado}{$codigo}{$mensaje}");
    }

    /**
     * @param array<string, string> $cabeceras
     *
     * @return array{int, string}
     */
    private static function peticionHttp(string $metodo, string $url, array $cabeceras, string $cuerpo): array
    {
        $lineas = array_map(fn ($nombre, $valor) => "{$nombre}: {$valor}", array_keys($cabeceras), $cabeceras);

        if ($metodo === 'PUT') {
            $lineas[] = 'content-length: ' . strlen($cuerpo);
        }

        $lineas[] = 'connection: close';

        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", $lineas),
            'content' => $cuerpo,
            'ignore_errors' => true,
            'follow_location' => 0,
            'protocol_version' => 1.1,
            'timeout' => 60,
        ]]);

        $respuesta = @file_get_contents($url, false, $contexto);
        $recibidas = http_get_last_response_headers() ?? [];

        if ($respuesta === false || $recibidas === [] || preg_match('#^HTTP/\S+\s+(\d{3})#', $recibidas[0], $partes) !== 1) {
            $error = error_get_last();

            throw new ErrorDeProyecto(
                'No se puede conectar con ' . parse_url($url, PHP_URL_HOST) . ($error !== null ? " ({$error['message']})" : ''),
                Proyecto::SITIO,
            );
        }

        return [(int) $partes[1], $respuesta];
    }
}
