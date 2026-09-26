<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * El tipo MIME de cada fichero del sitio según su extensión: el que manda la
 * previsualización y el que lleva cada fichero que se sube a S3.
 *
 * @internal
 */
final class TiposMime
{
    public const string HTML = 'text/html; charset=utf-8';
    public const string DESCONOCIDO = 'application/octet-stream';

    private const array TIPOS = [
        'html' => self::HTML,
        'htm' => self::HTML,
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
        'webmanifest' => 'application/manifest+json',
    ];

    public static function de(string $fichero): string
    {
        return self::TIPOS[strtolower(pathinfo($fichero, PATHINFO_EXTENSION))] ?? self::DESCONOCIDO;
    }
}
