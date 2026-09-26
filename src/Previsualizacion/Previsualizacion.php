<?php

declare(strict_types=1);

namespace Ehundu\Previsualizacion;

use Ehundu\Constructor;
use Ehundu\Construccion;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;

/**
 * El sitio que sirve la previsualización: se construye en memoria, sin
 * escribir en `salida/`, y responde a cada petición como lo haría un
 * alojamiento corriente.
 *
 * En cada página HTML mete un script que pregunta a `/__ehundu/estado` si
 * hay una construcción nueva y, si la hay, recarga. Si la última
 * construcción falló, las páginas muestran el error; los demás ficheros se
 * siguen sirviendo de la última construcción buena.
 */
final class Previsualizacion
{
    public const string ESTADO = '/__ehundu/estado';

    private const array TIPOS = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
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
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'webmanifest' => 'application/manifest+json',
    ];

    private ?Construccion $construccion = null;
    private ?\Throwable $error = null;
    private int $version = 0;

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly bool $conBorradores = false,
        private readonly Constructor $constructor = new Constructor(),
    ) {
    }

    /**
     * Vuelve a construir el sitio. Si falla, se guarda el error y se sigue
     * sirviendo lo anterior; en los dos casos cambia la versión, para que el
     * navegador se recargue.
     *
     * @return Informe|\Throwable el resumen de la construcción, o el error
     */
    public function reconstruir(): Informe|\Throwable
    {
        $inicio = hrtime(true);
        $this->version++;

        try {
            $this->construccion = $this->constructor->construir($this->proyecto, conBorradores: $this->conBorradores);
            $this->error = null;
        } catch (\Throwable $error) {
            return $this->error = $error;
        }

        return new Informe(
            $this->construccion->paginas,
            $this->construccion->avisos,
            (hrtime(true) - $inicio) / 1e9,
            count($this->construccion->copias),
        );
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @param string $objetivo la ruta pedida, con su consulta si la lleva
     */
    public function responder(string $metodo, string $objetivo): Respuesta
    {
        if ($metodo !== 'GET' && $metodo !== 'HEAD') {
            return Respuesta::texto(405, 'La previsualización solo responde a GET y HEAD');
        }

        $ruta = rawurldecode((string) parse_url($objetivo, PHP_URL_PATH));

        if ($ruta === self::ESTADO) {
            return Respuesta::texto(200, (string) $this->version);
        }

        if (!str_starts_with($ruta, '/') || str_contains($ruta, "\0") || str_contains($ruta, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $ruta) === 1) {
            return Respuesta::texto(400, 'Ruta no válida');
        }

        if ($this->error !== null && self::esPagina($ruta)) {
            return $this->paginaDeError();
        }

        $fichero = ltrim($ruta, '/');

        if ($fichero === '' || str_ends_with($fichero, '/')) {
            $fichero .= 'index.html';
        }

        $respuesta = $this->fichero($fichero, 200);

        if ($respuesta !== null) {
            return $respuesta;
        }

        if (!str_ends_with($ruta, '/') && $this->existe("{$fichero}/index.html")) {
            $consulta = parse_url($objetivo, PHP_URL_QUERY);

            return Respuesta::redireccion($ruta . '/' . (is_string($consulta) ? "?{$consulta}" : ''));
        }

        return $this->fichero('404.html', 404)
            ?? new Respuesta(404, self::TIPOS['html'], $this->conRecarga(
                '<!doctype html><meta charset="utf-8"><title>No existe</title><p>No existe ' . htmlspecialchars($ruta) . '</p>',
            ));
    }

    private function fichero(string $fichero, int $estado): ?Respuesta
    {
        $tipo = self::TIPOS[strtolower(pathinfo($fichero, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
        $esHtml = $tipo === self::TIPOS['html'];

        if (isset($this->construccion->escritos[$fichero])) {
            $contenido = $this->construccion->escritos[$fichero];

            return new Respuesta($estado, $tipo, $esHtml ? $this->conRecarga($contenido) : $contenido);
        }

        if (isset($this->construccion->copias[$fichero])) {
            $ruta = $this->proyecto->ruta($this->construccion->copias[$fichero]);

            if (!is_file($ruta)) {
                return null;
            }

            return $esHtml
                ? new Respuesta($estado, $tipo, $this->conRecarga((string) file_get_contents($ruta)))
                : new Respuesta($estado, $tipo, fichero: $ruta);
        }

        return null;
    }

    private function existe(string $fichero): bool
    {
        return isset($this->construccion->escritos[$fichero]) || isset($this->construccion->copias[$fichero]);
    }

    private function paginaDeError(): Respuesta
    {
        $error = $this->error;
        $mensaje = $error instanceof ErrorDeProyecto
            ? $error->getMessage()
            : 'Error interno de Ehundu: ' . $error?->getMessage() . ' (' . basename((string) $error?->getFile()) . ':' . $error?->getLine() . ')';

        $html = '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error al compilar</title>'
            . '<body style="margin:0;font:16px/1.5 system-ui,sans-serif;background:#fdf2f2;color:#611a15">'
            . '<main style="max-width:60rem;margin:3rem auto;padding:0 1rem">'
            . '<h1 style="font-size:1.4rem">No se ha podido compilar el sitio</h1>'
            . '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #f5c2c0;padding:1rem;border-radius:.5rem">'
            . htmlspecialchars($mensaje) . '</pre>'
            . '<p>La página se recargará sola cuando lo arregles.</p></main></body></html>';

        return new Respuesta(500, self::TIPOS['html'], $this->conRecarga($html));
    }

    /**
     * El script que recarga la página cuando hay una construcción nueva. Va
     * justo antes de `</body>`, o al final si no lo hay.
     */
    private function conRecarga(string $html): string
    {
        $script = '<script>(() => { const version = "' . $this->version . '"; '
            . 'setInterval(async () => { try { const r = await fetch("' . self::ESTADO . '", { cache: "no-store" }); '
            . 'if ((await r.text()) !== version) location.reload(); } catch (e) {} }, 1000); })();</script>';

        $posicion = strripos($html, '</body>');

        return $posicion === false ? $html . $script : substr_replace($html, $script, $posicion, 0);
    }

    private static function esPagina(string $ruta): bool
    {
        $extension = pathinfo($ruta, PATHINFO_EXTENSION);

        return str_ends_with($ruta, '/') || $extension === '' || strtolower($extension) === 'html';
    }
}
