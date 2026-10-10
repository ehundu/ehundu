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
 * Con `css_fichero()` y `js_fichero()` el layout pide en cambio una etiqueta
 * que enlaza un fichero con ese mismo contenido, que la página lleva aparte
 * (un «paquete») con un nombre que es la huella de su contenido.
 *
 * @internal
 */
final class Recursos
{
    public const string MARCA_CSS = "\u{E000}ehundu:css\u{E000}";
    public const string MARCA_JS = "\u{E000}ehundu:js\u{E000}";
    public const string MARCA_CSS_FICHERO = "\u{E000}ehundu:css-fichero\u{E000}";
    public const string MARCA_JS_FICHERO = "\u{E000}ehundu:js-fichero\u{E000}";

    /** Cuántos caracteres de la huella de un contenido se usan en un nombre o en una dirección. */
    private const int LONGITUD_DE_LA_HUELLA = 10;

    private const array MARCAS = ['css' => self::MARCA_CSS, 'js' => self::MARCA_JS];
    private const array MARCAS_DE_FICHERO = ['css' => self::MARCA_CSS_FICHERO, 'js' => self::MARCA_JS_FICHERO];
    private const array ETIQUETAS_DE_FICHERO = [
        'css' => '<link rel="stylesheet" href="%s">',
        'js' => '<script src="%s" defer></script>',
    ];

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
     * Los 10 primeros caracteres hexadecimales del SHA-256 de un contenido.
     */
    public static function huella(string $contenido): string
    {
        return substr(hash('sha256', $contenido), 0, self::LONGITUD_DE_LA_HUELLA);
    }

    /**
     * La huella de un fichero, o null si no se puede leer.
     */
    public static function huellaDeFichero(string $ruta): ?string
    {
        $hash = @hash_file('sha256', $ruta);

        return $hash === false ? null : substr($hash, 0, self::LONGITUD_DE_LA_HUELLA);
    }

    /**
     * Pone el contenido de los ficheros declarados, o la etiqueta que enlaza
     * su paquete, en las marcas de la página.
     *
     * @param array{css: list<string>, js: list<string>} $declarados
     * @param bool                                       $conFicheros si la página admite paquetes; un PDF no, y sus
     *                                                                marcas de fichero salen vacías
     *
     * @return array{html: string, paquetes: array<string, string>} la página y el contenido de cada paquete
     *                                                              que enlaza, por ruta en `salida/`
     */
    public function insertar(string $html, array $declarados, bool $conFicheros = true): array
    {
        $paquetes = [];

        foreach (self::MARCAS as $tipo => $marca) {
            $marcaDeFichero = self::MARCAS_DE_FICHERO[$tipo];
            $incrustado = str_contains($html, $marca);
            $enlazado = $conFicheros && str_contains($html, $marcaDeFichero);

            if (!$conFicheros) {
                $html = str_replace($marcaDeFichero, '', $html);
            }

            if (!$incrustado && !$enlazado) {
                if ($declarados[$tipo] !== []) {
                    $escrito = $conFicheros ? "{{ {$tipo}() }} ni {{ {$tipo}_fichero() }}; no se incluye" : "{{ {$tipo}() }}; no se incrusta";
                    $this->avisos->registrar("Hay {$tipo} declarado, pero ninguna plantilla escribe {$escrito}");
                }

                continue;
            }

            $contenido = implode("\n", array_filter(array_map(fn (string $ruta) => $this->leer($tipo, $ruta), $declarados[$tipo]), is_string(...)));

            if ($incrustado) {
                $html = str_replace($marca, $contenido, $html);
            }

            if ($enlazado) {
                if ($contenido === '') {
                    $html = str_replace($marcaDeFichero, '', $html);

                    continue;
                }

                $fichero = "{$tipo}/" . self::huella($contenido) . ".{$tipo}";
                $paquetes[$fichero] = $contenido;
                $html = str_replace($marcaDeFichero, sprintf(self::ETIQUETAS_DE_FICHERO[$tipo], "/{$fichero}"), $html);
            }
        }

        return ['html' => $html, 'paquetes' => $paquetes];
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
