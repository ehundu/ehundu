<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Avisos;
use Ehundu\Colecciones;
use Ehundu\ErrorDeProyecto;
use Ehundu\Lectura;
use Ehundu\Markdown\Conversor;
use Ehundu\Markdown\ResolutorDeAtajos;
use Ehundu\Pagina;
use Ehundu\Proyecto;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Extension\CoreExtension;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * Construye el HTML de cada página (formato §7): convierte su cuerpo, de
 * Markdown o de Twig, y lo pone dentro de su plantilla.
 *
 * El cuerpo de cada página se convierte una sola vez, cuando alguien lo pide:
 * su propia plantilla o la de otra página a través de una colección. Si una
 * página acaba necesitando su propio cuerpo para construirse, es un error.
 */
final class Maquetador
{
    public const string PLANTILLA_POR_DEFECTO = 'pagina';

    private Environment $twig;
    private ExtensionTwig $extension;
    private Conversor $markdown;
    private Atajos $atajos;

    /** @var array<string, string> cuerpos ya convertidos, por ruta */
    private array $cuerpos = [];

    /** @var list<string> rutas de las páginas cuyo cuerpo se está convirtiendo */
    private array $enCurso = [];

    public function __construct(
        Proyecto $proyecto,
        private readonly Lectura $lectura,
        Colecciones $colecciones,
        private readonly Avisos $avisos,
    ) {
        $zona = $lectura->sitio->zonaHoraria;

        // Las plantillas de los atajos que trae Ehundu, como @ehundu/imagen.twig.
        $incluidas = new FilesystemLoader();
        $incluidas->addPath(dirname(__DIR__, 2) . '/recursos/atajos', 'ehundu');

        $this->markdown = new Conversor($lectura->sitio->url);
        $this->twig = new Environment(new ChainLoader([new CargadorDePlantillas($proyecto), $incluidas]), [
            'autoescape' => 'html',
            'strict_variables' => false,
            'cache' => false,
        ]);
        $this->twig->getExtension(CoreExtension::class)->setTimezone($zona);

        $this->extension = new ExtensionTwig($colecciones, $this->cuerpo(...), $proyecto, $avisos, $zona);
        $this->twig->addExtension($this->extension);

        $this->atajos = new Atajos($proyecto, $this->twig, $lectura, $this->extension, $avisos);
    }

    /**
     * La página entera: su cuerpo dentro de su plantilla, o el cuerpo solo
     * si lleva `plantilla: false`.
     *
     * @throws ErrorDeProyecto si falta la plantilla o hay un error en ella
     */
    public function maquetar(Pagina $pagina): string
    {
        $plantilla = $pagina->campos['plantilla'] ?? self::PLANTILLA_POR_DEFECTO;

        if ($plantilla === false) {
            return $this->cuerpo($pagina);
        }

        $nombre = Proyecto::PLANTILLAS . "/{$plantilla}.twig";

        if (!$this->twig->getLoader()->exists($nombre)) {
            throw new ErrorDeProyecto(
                "La página usa la plantilla «{$plantilla}», pero no existe {$nombre}",
                Proyecto::CONTENIDO . "/{$pagina->ruta}",
            );
        }

        return $this->conTwig(fn () => $this->twig->render($nombre, $this->variables($pagina)));
    }

    /**
     * El cuerpo de una página ya en HTML.
     *
     * @throws ErrorDeProyecto si la página se necesita a sí misma o hay un error en su Twig
     */
    public function cuerpo(Pagina $pagina): string
    {
        if (isset($this->cuerpos[$pagina->ruta])) {
            return $this->cuerpos[$pagina->ruta];
        }

        $posicion = array_search($pagina->ruta, $this->enCurso, true);

        if ($posicion !== false) {
            $cadena = array_map(
                fn (string $ruta) => Proyecto::CONTENIDO . "/{$ruta}",
                [...array_slice($this->enCurso, $posicion), $pagina->ruta],
            );

            throw new ErrorDeProyecto(
                'Una página necesita su propio contenido para construirse: ' . implode(' → ', $cadena),
                Proyecto::CONTENIDO . "/{$pagina->ruta}",
            );
        }

        $this->enCurso[] = $pagina->ruta;

        try {
            $html = $pagina->formato === 'md'
                ? $this->cuerpoMarkdown($pagina)
                : $this->cuerpoTwig($pagina);
        } finally {
            array_pop($this->enCurso);
        }

        return $this->cuerpos[$pagina->ruta] = $html;
    }

    private function cuerpoMarkdown(Pagina $pagina): string
    {
        $fichero = Proyecto::CONTENIDO . "/{$pagina->ruta}";

        $resolutor = new class($this->atajos, $pagina, $fichero) implements ResolutorDeAtajos {
            public function __construct(
                private readonly Atajos $atajos,
                private readonly Pagina $pagina,
                private readonly string $fichero,
            ) {
            }

            public function nombres(): array
            {
                return $this->atajos->nombres();
            }

            public function atajo(string $nombre, array $atributos, ?string $contenido, ?int $linea): string
            {
                return $this->atajos->renderizar($this->pagina, $nombre, $atributos, $contenido, null, $this->fichero, $linea);
            }

            public function figura(string $src, string $alt, string $pie, ?string $enlace, ?int $linea): string
            {
                return $this->atajos->renderizar($this->pagina, 'imagen', [], null, [
                    'src' => $src,
                    'alt' => $alt,
                    'pie' => $pie === '' ? null : new \Twig\Markup($pie, 'UTF-8'),
                    'enlace' => $enlace,
                ], $this->fichero, $linea);
            }
        };

        return $this->markdown->convertir($pagina->cuerpo, $resolutor, $this->avisos, $fichero, $pagina->lineaCuerpo);
    }

    private function cuerpoTwig(Pagina $pagina): string
    {
        // Un comentario con tantas líneas como ocupa el front matter hace que
        // Twig cuente las líneas igual que el fichero, sin añadir nada al HTML.
        $fuente = $pagina->lineaCuerpo > 1
            ? '{#' . str_repeat("\n", $pagina->lineaCuerpo - 1) . '#}' . $pagina->cuerpo
            : $pagina->cuerpo;

        return $this->conTwig(fn () => $this->twig
            ->createTemplate($fuente, Proyecto::CONTENIDO . "/{$pagina->ruta}")
            ->render($this->variables($pagina)));
    }

    /**
     * @return array<string, mixed>
     */
    private function variables(Pagina $pagina): array
    {
        return [
            'sitio' => $this->lectura->sitio->campos,
            'datos' => $this->lectura->datos,
            'pagina' => $this->extension->vistaDe($pagina),
        ];
    }

    /**
     * @param \Closure(): string $renderizar
     */
    private function conTwig(\Closure $renderizar): string
    {
        try {
            return $renderizar();
        } catch (Error $error) {
            throw ErroresDeTwig::traducir(
                $error,
                fn (string $nombre) => (string) preg_replace('/ \(string template [0-9a-f]+\)$/', '', $nombre),
            );
        }
    }
}
