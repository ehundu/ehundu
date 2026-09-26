<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Avisos;
use Ehundu\Dimensiones;
use Ehundu\Lectura;
use Ehundu\Proyecto;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Markup;

/**
 * Los atajos de un sitio (formato §8.1): los que trae Ehundu (`imagen`,
 * `video`, `archivo` y `dato`) y los que define el sitio en
 * `parciales/atajos/`. Un atajo del sitio con el nombre de uno de Ehundu lo
 * sustituye.
 *
 * Cada atajo es una plantilla de Twig que recibe sus atributos como
 * variables, `contenido` si envuelve algo, y `sitio`, `datos` y `pagina`.
 * Los de Ehundu preparan antes algunas variables: `imagen` recibe `src`,
 * `alt`, `pie`, `enlace`, `ancho` y `alto`; `video`, `insercion` o `src` y `proporcion`; `archivo`,
 * `href`, `texto`, `tipo` y `peso`; `dato`, `valor`.
 */
final class Atajos
{
    public const array INCLUIDOS = ['imagen', 'video', 'archivo', 'dato'];
    public const string CARPETA = Proyecto::PARCIALES . '/atajos';

    private const array RESERVADOS = ['sitio', 'datos', 'pagina', 'contenido'];

    /** @var list<string> */
    private array $delSitio = [];

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Environment $twig,
        private readonly Lectura $lectura,
        private readonly ExtensionTwig $extension,
        private readonly Avisos $avisos,
        private readonly ?Registros $registros = null,
    ) {
        foreach (glob($proyecto->ruta(self::CARPETA, '*.twig')) ?: [] as $fichero) {
            $nombre = basename($fichero, '.twig');

            if (preg_match('/^[a-z][a-z0-9-]*$/', $nombre) === 1) {
                $this->delSitio[] = $nombre;
            } else {
                $avisos->registrar(
                    'El nombre de un atajo solo lleva minúsculas sin tildes, números y guiones; este no se puede usar',
                    self::CARPETA . '/' . basename($fichero),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public function nombres(): array
    {
        return array_values(array_unique([...self::INCLUIDOS, ...$this->delSitio]));
    }

    /**
     * @param array<string, string>     $atributos
     * @param array<string, mixed>|null $preparadas variables ya preparadas (las de una figura)
     * @param string                    $fichero    el de la página, para los avisos
     */
    public function renderizar(
        \Ehundu\Pagina $pagina,
        string $nombre,
        array $atributos,
        ?string $contenido,
        ?array $preparadas,
        string $fichero,
        ?int $linea,
    ): string {
        $aviso = fn (string $mensaje) => $this->avisos->registrar("[{$nombre}] {$mensaje}", $fichero, $linea);

        foreach (self::RESERVADOS as $reservado) {
            if (array_key_exists($reservado, $atributos)) {
                $aviso("«{$reservado}» es un nombre reservado y no se puede usar como atributo; se ignora");
                unset($atributos[$reservado]);
            }
        }

        $variables = $preparadas ?? match ($nombre) {
            'imagen' => $this->imagen($atributos, $aviso),
            'video' => $this->video($atributos, $aviso),
            'archivo' => $this->archivo($atributos, $aviso),
            'dato' => $this->dato($atributos, $aviso),
            default => [],
        };

        if ($variables === null) {
            return '';
        }

        $plantilla = $this->twig->getLoader()->exists(self::CARPETA . "/{$nombre}.twig")
            ? self::CARPETA . "/{$nombre}.twig"
            : "@ehundu/{$nombre}.twig";

        try {
            return $this->twig->render($plantilla, [
                ...$atributos,
                ...$variables,
                'contenido' => $contenido === null ? null : new Markup($contenido, 'UTF-8'),
                'sitio' => $this->lectura->sitio->campos,
                'datos' => $this->lectura->datos,
                'pagina' => $this->extension->vistaDe($pagina),
            ]);
        } catch (Error $error) {
            throw ErroresDeTwig::traducir($error, fn (string $nombre) => $nombre);
        }
    }

    /**
     * Si existe un fichero de `publico/`. Queda anotado en el registro de la
     * página: si aparece, desaparece o cambia, la página se rehace.
     */
    private function existe(string $fichero): bool
    {
        $this->registros?->anotar('publico', $fichero);

        return is_file($this->proyecto->ruta(Proyecto::PUBLICO, $fichero));
    }

    /**
     * @param array<string, string>   $atributos
     * @param \Closure(string): void  $aviso
     *
     * @return array<string, mixed>|null
     */
    private function imagen(array $atributos, \Closure $aviso): ?array
    {
        $fichero = ltrim($atributos['fichero'] ?? '', '/');

        if ($fichero === '') {
            $aviso('falta «fichero», la imagen dentro de publico/; no se inserta nada');

            return null;
        }

        if (!$this->existe($fichero)) {
            $aviso("no existe publico/{$fichero}");
        }

        if (!isset($atributos['alt'])) {
            $aviso('falta «alt», el texto alternativo de la imagen');
        }

        $alt = $atributos['alt'] ?? '';
        $pie = $atributos['pie'] ?? $alt;

        return [
            'src' => "/{$fichero}",
            'alt' => $alt,
            'pie' => $pie === '' ? null : new Markup(htmlspecialchars($pie, ENT_QUOTES, 'UTF-8'), 'UTF-8'),
            'enlace' => $atributos['enlace'] ?? null,
            ...$this->medidas($fichero),
        ];
    }

    /**
     * El ancho y el alto de la imagen de una figura del Markdown, si su
     * dirección empieza por `/` y es un fichero de `publico/`. Una dirección
     * externa o relativa no se mira.
     *
     * @return array{ancho: ?int, alto: ?int}
     */
    public function medidasDeFigura(string $src): array
    {
        if (!str_starts_with($src, '/') || str_starts_with($src, '//')) {
            return ['ancho' => null, 'alto' => null];
        }

        $fichero = ltrim(rawurldecode((string) strtok($src, '?#')), '/');

        if ($fichero === '' || str_contains($fichero, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $fichero) === 1) {
            return ['ancho' => null, 'alto' => null];
        }

        return $this->medidas($fichero);
    }

    /**
     * @return array{ancho: ?int, alto: ?int}
     */
    private function medidas(string $fichero): array
    {
        $medidas = $this->existe($fichero)
            ? Dimensiones::leer($this->proyecto->ruta(Proyecto::PUBLICO, $fichero))
            : null;

        return ['ancho' => $medidas['ancho'] ?? null, 'alto' => $medidas['alto'] ?? null];
    }

    /**
     * @param array<string, string>  $atributos
     * @param \Closure(string): void $aviso
     *
     * @return array<string, mixed>|null
     */
    private function video(array $atributos, \Closure $aviso): ?array
    {
        $proporcion = $atributos['proporcion'] ?? '16:9';

        if (preg_match('/^(\d+)\s*:\s*(\d+)$/', $proporcion, $partes) !== 1 || (int) $partes[1] === 0 || (int) $partes[2] === 0) {
            $aviso("«proporcion» se escribe como 16:9; se usa 16:9 en lugar de «{$proporcion}»");
            $partes = [null, '16', '9'];
        }

        $variables = [
            'proporcion' => "{$partes[1]} / {$partes[2]}",
            'titulo' => $atributos['titulo'] ?? 'Vídeo',
            'insercion' => null,
            'src' => null,
        ];

        if (isset($atributos['url'])) {
            $url = $atributos['url'];

            if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $id) === 1) {
                return [...$variables, 'insercion' => "https://www.youtube-nocookie.com/embed/{$id[1]}"];
            }

            if (preg_match('~^https?://(?:www\.)?(?:player\.)?vimeo\.com/(?:video/)?(\d+)~', $url, $id) === 1) {
                return [...$variables, 'insercion' => "https://player.vimeo.com/video/{$id[1]}?dnt=1"];
            }

            $aviso("solo se admiten vídeos de YouTube y Vimeo, o un «fichero» de publico/; no se inserta {$url}");

            return null;
        }

        $fichero = ltrim($atributos['fichero'] ?? '', '/');

        if ($fichero === '') {
            $aviso('falta «url» o «fichero»; no se inserta nada');

            return null;
        }

        if (!$this->existe($fichero)) {
            $aviso("no existe publico/{$fichero}");
        }

        return [...$variables, 'src' => "/{$fichero}"];
    }

    /**
     * @param array<string, string>  $atributos
     * @param \Closure(string): void $aviso
     *
     * @return array<string, mixed>|null
     */
    private function archivo(array $atributos, \Closure $aviso): ?array
    {
        $fichero = ltrim($atributos['fichero'] ?? '', '/');

        if ($fichero === '') {
            $aviso('falta «fichero», el documento dentro de publico/; no se inserta nada');

            return null;
        }

        $peso = null;

        if ($this->existe($fichero)) {
            $peso = self::peso((int) filesize($this->proyecto->ruta(Proyecto::PUBLICO, $fichero)));
        } else {
            $aviso("no existe publico/{$fichero}; el enlace sale sin tamaño");
        }

        return [
            'href' => "/{$fichero}",
            'texto' => $atributos['texto'] ?? basename($fichero),
            'tipo' => strtoupper(pathinfo($fichero, PATHINFO_EXTENSION)),
            'peso' => $peso,
        ];
    }

    /**
     * @param array<string, string>  $atributos
     * @param \Closure(string): void $aviso
     *
     * @return array<string, mixed>|null
     */
    private function dato(array $atributos, \Closure $aviso): ?array
    {
        $clave = $atributos['clave'] ?? '';

        if ($clave === '') {
            $aviso('falta «clave», por ejemplo clave="cliente.nombre"; no se inserta nada');

            return null;
        }

        $valor = $this->lectura->datos;

        foreach (explode('.', $clave) as $parte) {
            $valor = is_array($valor) ? ($valor[$parte] ?? null) : null;
        }

        if ($valor === null || $valor === '' || $valor === []) {
            if (array_key_exists('siFalta', $atributos)) {
                return ['valor' => $atributos['siFalta']];
            }

            $aviso("no hay ningún dato en «{$clave}»; no se inserta nada");

            return null;
        }

        if (!is_scalar($valor)) {
            $aviso("«{$clave}» no es un texto ni un número; no se inserta nada");

            return null;
        }

        return ['valor' => is_bool($valor) ? ($valor ? 'sí' : 'no') : (string) $valor];
    }

    /**
     * «350 KB», «1,2 MB».
     */
    private static function peso(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return max(1, (int) round($bytes / 1024)) . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
}
