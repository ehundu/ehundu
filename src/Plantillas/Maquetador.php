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
 *
 * De cada página y de cada cuerpo queda un registro de lo que han usado y de
 * los avisos que han dado (ver `Registro`): es lo que permite a la
 * compilación incremental reutilizar lo que no ha cambiado.
 */
final class Maquetador
{
    public const string PLANTILLA_POR_DEFECTO = 'pagina';

    /** En el registro de los cuerpos en Markdown, la lista de atajos del sitio. */
    public const string ATAJOS = '[atajos]';

    private Environment $twig;
    private ExtensionTwig $extension;
    private Conversor $markdown;
    private Atajos $atajos;
    private Recursos $recursos;
    private Registros $registros;

    /** @var array<string, array{html: string, registro: Registro}> cuerpos ya convertidos, por ruta */
    private array $cuerpos = [];

    /** @var array<string, Registro> registros de las páginas ya maquetadas, por ruta */
    private array $registrosDePaginas = [];

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

        $this->registros = new Registros();
        $avisos->escuchar(fn (\Ehundu\Aviso $aviso) => $this->registros->aviso($aviso));

        $this->markdown = new Conversor($lectura->sitio->url);
        $cargador = new CargadorDePlantillas($proyecto, fn (string $nombre) => $this->registros->anotar('plantillas', $nombre));
        $this->twig = new Environment(new ChainLoader([$cargador, $incluidas]), [
            'autoescape' => 'html',
            'strict_variables' => false,
            'cache' => false,
        ]);
        $this->twig->getExtension(CoreExtension::class)->setTimezone($zona);

        $this->recursos = new Recursos($proyecto, $avisos);
        $this->extension = new ExtensionTwig($colecciones, $this->cuerpo(...), $proyecto, $avisos, $zona, $this->registros);
        $this->twig->addExtension($this->extension);

        $this->atajos = new Atajos($proyecto, $this->twig, $lectura, $this->extension, $avisos, $this->registros);
    }

    /**
     * La página entera: su cuerpo dentro de su plantilla, o el cuerpo solo
     * si lleva `plantilla: false`, con su CSS y su JS en las marcas de
     * `css()` y `js()`. Los ficheros del front matter van detrás de los que
     * declaran las plantillas, para que puedan sobrescribirlos.
     *
     * @throws ErrorDeProyecto si falta la plantilla o hay un error en ella
     */
    public function maquetar(Pagina $pagina): string
    {
        $this->registros->abrir();

        try {
            $html = $this->maquetarSinRecursos($pagina);

            foreach (['css', 'js'] as $tipo) {
                foreach ($pagina->campos[$tipo] ?? [] as $ruta) {
                    $this->registros->declarar($tipo, ltrim($ruta, '/'));
                }
            }

            // Lo que se incrusta es también algo de lo que depende la página, y
            // los avisos de lo que falta tienen que quedar en su registro.
            $actual = $this->registros->actual() ?? new Registro();

            foreach ([...$actual->css, ...$actual->js] as $ruta) {
                $this->registros->anotar('publico', $ruta);
            }

            $html = $this->recursos->insertar($html, ['css' => $actual->css, 'js' => $actual->js]);
        } finally {
            $registro = $this->registros->cerrar();
        }

        $this->registrosDePaginas[$pagina->ruta] = $registro;

        return $html;
    }

    /**
     * Lo que usó una página al maquetarse en esta compilación.
     */
    public function registroDe(string $ruta): ?Registro
    {
        return $this->registrosDePaginas[$ruta] ?? null;
    }

    /**
     * Los cuerpos convertidos o reutilizados en esta compilación, con su registro.
     *
     * @return array<string, array{html: string, registro: Registro}>
     */
    public function cuerpos(): array
    {
        return $this->cuerpos;
    }

    /**
     * Cuerpos de una compilación anterior que siguen valiendo: se usan tal
     * cual en lugar de volver a convertirlos.
     *
     * @param array<string, array{html: string, registro: Registro}> $cuerpos
     */
    public function precargar(array $cuerpos): void
    {
        $this->cuerpos = [...$this->cuerpos, ...$cuerpos];
    }

    private function maquetarSinRecursos(Pagina $pagina): string
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
        $this->registros->anotar('contenidos', $pagina->ruta);

        if (isset($this->cuerpos[$pagina->ruta])) {
            $guardado = $this->cuerpos[$pagina->ruta];
            $this->registros->repetir($guardado['registro']);

            foreach ($guardado['registro']->avisos as $aviso) {
                $this->avisos->registrar($aviso->mensaje, $aviso->fichero, $aviso->linea);
            }

            return $guardado['html'];
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
        $this->registros->abrir();

        try {
            $html = $pagina->formato === 'md'
                ? $this->cuerpoMarkdown($pagina)
                : $this->cuerpoTwig($pagina);
        } finally {
            $registro = $this->registros->cerrar();
            array_pop($this->enCurso);
        }

        $this->cuerpos[$pagina->ruta] = ['html' => $html, 'registro' => $registro];

        return $html;
    }

    private function cuerpoMarkdown(Pagina $pagina): string
    {
        // Qué atajos hay cambia cómo se lee el Markdown: si aparece o
        // desaparece uno, cualquier cuerpo en Markdown puede cambiar.
        $this->registros->anotar('plantillas', self::ATAJOS);

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
