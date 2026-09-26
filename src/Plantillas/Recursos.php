<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Avisos;
use Ehundu\Proyecto;

/**
 * El CSS y el JS de cada página (formato §9). Las plantillas, los parciales
 * y los atajos declaran los ficheros de `publico/` que necesitan con
 * `css('…')` y `js('…')`, y las declaraciones se anotan en el registro de
 * la página (ver `Registros`). El layout marca con `css()` y `js()` dónde van,
 * y al terminar la página esta clase escribe allí el contenido de los
 * ficheros, cada uno una vez y en el orden en que se declararon.
 *
 * @internal
 */
final class Recursos
{
    public const string MARCA_CSS = "\u{E000}ehundu:css\u{E000}";
    public const string MARCA_JS = "\u{E000}ehundu:js\u{E000}";

    private const array MARCAS = ['css' => self::MARCA_CSS, 'js' => self::MARCA_JS];

    /** @var array<string, string> contenidos ya leídos, por tipo y ruta */
    private array $leidos = [];

    /**
     * Por qué no se pudo leer un fichero, por tipo y ruta. El aviso se repite
     * en cada página que lo declara para que quede en el registro de todas.
     *
     * @var array<string, string>
     */
    private array $fallidos = [];

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Avisos $avisos,
    ) {
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

        if (isset($this->leidos[$clave])) {
            return $this->leidos[$clave];
        }

        $fichero = Proyecto::PUBLICO . "/{$ruta}";

        if (!isset($this->fallidos[$clave])) {
            if (str_contains($ruta, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $ruta) === 1 || !str_ends_with(strtolower($ruta), ".{$tipo}")) {
                $this->fallidos[$clave] = "{$tipo}('{$ruta}'): solo se incrustan ficheros .{$tipo} de dentro de publico/";
            } elseif (!is_file($this->proyecto->ruta($fichero))) {
                $this->fallidos[$clave] = "{$tipo}('{$ruta}'): no existe {$fichero}";
            } else {
                return $this->leidos[$clave] = $this->proyecto->leerTexto($fichero);
            }
        }

        $this->avisos->registrar($this->fallidos[$clave]);

        return null;
    }
}
