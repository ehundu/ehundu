<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Avisos;
use Ehundu\Proyecto;

/**
 * El CSS y el JS de cada página (formato §9). Las plantillas, los parciales
 * y los atajos declaran los ficheros de `publico/` que necesitan con
 * `css('…')` y `js('…')`; el layout marca con `css()` y `js()` dónde van, y
 * al terminar la página se escriben allí, cada fichero una vez y en el orden
 * en que se declararon.
 *
 * Lo que se declara mientras se convierte el cuerpo de una página se guarda
 * con ese cuerpo, para repetirlo cuando otra página lo reutiliza.
 *
 * @internal
 */
final class Recursos
{
    public const string MARCA_CSS = "\u{E000}ehundu:css\u{E000}";
    public const string MARCA_JS = "\u{E000}ehundu:js\u{E000}";

    private const array MARCAS = ['css' => self::MARCA_CSS, 'js' => self::MARCA_JS];

    /** @var list<array{css: array<string, true>, js: array<string, true>}> */
    private array $pila = [];

    /** @var array<string, string|null> contenidos ya leídos, por tipo y ruta */
    private array $leidos = [];

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Avisos $avisos,
    ) {
    }

    /**
     * Empieza a recoger lo que se declare para una página o un cuerpo.
     */
    public function abrir(): void
    {
        $this->pila[] = ['css' => [], 'js' => []];
    }

    /**
     * Termina de recoger y devuelve lo declarado desde `abrir()`.
     *
     * @return array{css: list<string>, js: list<string>}
     */
    public function cerrar(): array
    {
        $marco = array_pop($this->pila) ?? ['css' => [], 'js' => []];

        return ['css' => array_keys($marco['css']), 'js' => array_keys($marco['js'])];
    }

    /**
     * Declara un fichero para todo lo que se está recogiendo: la página y los
     * cuerpos que se están convirtiendo dentro de ella.
     *
     * @param 'css'|'js' $tipo
     */
    public function declarar(string $tipo, string $ruta): void
    {
        $ruta = ltrim($ruta, '/');

        foreach (array_keys($this->pila) as $posicion) {
            $this->pila[$posicion][$tipo][$ruta] = true;
        }
    }

    /**
     * Vuelve a declarar lo que declaró un cuerpo que se reutiliza.
     *
     * @param array{css: list<string>, js: list<string>} $declarados
     */
    public function repetir(array $declarados): void
    {
        foreach ($declarados as $tipo => $rutas) {
            foreach ($rutas as $ruta) {
                $this->declarar($tipo, $ruta);
            }
        }
    }

    /**
     * Pone el contenido de los ficheros declarados en las marcas de la página.
     *
     * @param array{css: list<string>, js: list<string>} $declarados
     */
    public function insertar(string $html, array $declarados): string
    {
        foreach (self::MARCAS as $tipo => $marca) {
            if (!str_contains($html, $marca)) {
                if ($declarados[$tipo] !== []) {
                    $this->avisos->registrar("Hay {$tipo} declarado, pero ninguna plantilla escribe {{ {$tipo}() }}; no se incrusta");
                }

                continue;
            }

            $contenidos = array_filter(array_map(fn (string $ruta) => $this->leer($tipo, $ruta), $declarados[$tipo]), is_string(...));
            $html = str_replace($marca, implode("\n", $contenidos), $html);
        }

        return $html;
    }

    private function leer(string $tipo, string $ruta): ?string
    {
        $clave = "{$tipo}:{$ruta}";

        if (array_key_exists($clave, $this->leidos)) {
            return $this->leidos[$clave];
        }

        $fichero = Proyecto::PUBLICO . "/{$ruta}";

        if (str_contains($ruta, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $ruta) === 1 || !str_ends_with(strtolower($ruta), ".{$tipo}")) {
            $this->avisos->registrar("{$tipo}('{$ruta}'): solo se incrustan ficheros .{$tipo} de dentro de publico/");

            return $this->leidos[$clave] = null;
        }

        if (!is_file($this->proyecto->ruta($fichero))) {
            $this->avisos->registrar("{$tipo}('{$ruta}'): no existe {$fichero}");

            return $this->leidos[$clave] = null;
        }

        return $this->leidos[$clave] = $this->proyecto->leerTexto($fichero);
    }
}
