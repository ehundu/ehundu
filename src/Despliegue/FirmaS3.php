<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

/**
 * La firma de las peticiones a S3 (Signature Version 4 de AWS), la que
 * aceptan también los servicios compatibles: Scaleway, Cloudflare R2,
 * Backblaze, Hetzner. Son unas cuantas HMAC-SHA256; no hace falta el SDK.
 *
 * @internal
 */
final readonly class FirmaS3
{
    public const string ALGORITMO = 'AWS4-HMAC-SHA256';

    public function __construct(
        private string $usuario,
        #[\SensitiveParameter] private string $clave,
        private string $region,
        private string $servicio = 's3',
    ) {
    }

    /**
     * La cabecera `Authorization` de una petición.
     *
     * @param string                $ruta      la ruta ya codificada, como va en la petición
     * @param string                $consulta  lo que va detrás de `?`, sin ordenar
     * @param array<string, string> $cabeceras las que se firman, con `host` y `x-amz-date`
     * @param string                $resumen   el SHA-256 del cuerpo, en hexadecimal
     */
    public function autorizacion(string $metodo, string $ruta, string $consulta, array $cabeceras, string $resumen, \DateTimeImmutable $momento): string
    {
        $momento = $momento->setTimezone(new \DateTimeZone('UTC'));
        $dia = $momento->format('Ymd');
        $ambito = "{$dia}/{$this->region}/{$this->servicio}/aws4_request";

        $normalizadas = [];

        foreach ($cabeceras as $nombre => $valor) {
            $normalizadas[strtolower(trim((string) $nombre))] = (string) preg_replace('/\s+/', ' ', trim($valor));
        }

        ksort($normalizadas, SORT_STRING);
        $firmadas = implode(';', array_keys($normalizadas));

        $peticion = implode("\n", [
            $metodo,
            $ruta === '' ? '/' : $ruta,
            self::consultaCanonica($consulta),
            implode('', array_map(fn ($nombre, $valor) => "{$nombre}:{$valor}\n", array_keys($normalizadas), $normalizadas)),
            $firmadas,
            $resumen,
        ]);

        $texto = implode("\n", [self::ALGORITMO, $momento->format('Ymd\THis\Z'), $ambito, hash('sha256', $peticion)]);

        $clave = hash_hmac('sha256', $dia, 'AWS4' . $this->clave, true);
        $clave = hash_hmac('sha256', $this->region, $clave, true);
        $clave = hash_hmac('sha256', $this->servicio, $clave, true);
        $clave = hash_hmac('sha256', 'aws4_request', $clave, true);

        return self::ALGORITMO . " Credential={$this->usuario}/{$ambito}, SignedHeaders={$firmadas}, Signature=" . hash_hmac('sha256', $texto, $clave);
    }

    /**
     * Una ruta de S3 codificada como la firma la espera: cada tramo por
     * separado, dejando las barras.
     */
    public static function codificarRuta(string $ruta): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $ruta)));
    }

    private static function consultaCanonica(string $consulta): string
    {
        if ($consulta === '') {
            return '';
        }

        $pares = [];

        foreach (explode('&', $consulta) as $par) {
            [$nombre, $valor] = array_pad(explode('=', $par, 2), 2, '');
            $pares[] = [rawurlencode(rawurldecode($nombre)), rawurlencode(rawurldecode($valor))];
        }

        // Por bytes: <=> compararía como números los textos que lo parecen.
        usort($pares, fn (array $a, array $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));

        return implode('&', array_map(fn (array $par) => "{$par[0]}={$par[1]}", $pares));
    }
}
