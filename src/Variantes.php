<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Las variantes de ancho de una imagen de `publico/` (formato §2 y §7.3): los
 * ficheros `<tronco>-<ancho>.<formato>` que otra herramienta dejó junto a ella,
 * según la sección `imagenes` de `sitio.yml`. El motor no genera ninguno, solo
 * los busca y lee su ancho real.
 *
 * Cada fichero que se mira, exista o no, se apunta con `$anotar`: si aparece,
 * desaparece o cambia, la página que lo pidió se rehace.
 *
 * @internal
 */
final class Variantes
{
    /** Las extensiones que se quitan de una ruta para sacar su tronco. */
    private const array EXTENSIONES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /**
     * @param \Closure(string): void $anotar apunta que quien busca depende de un fichero de `publico/`
     */
    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Imagenes $imagenes,
        private readonly \Closure $anotar,
    ) {
    }

    /**
     * Si el sitio declara variantes.
     */
    public function activas(): bool
    {
        return $this->imagenes->activa();
    }

    /**
     * El `sizes` del sitio, el de las imágenes que no dicen otro.
     */
    public function sizes(): string
    {
        return $this->imagenes->sizes;
    }

    /**
     * Si una ruta, relativa a `publico/`, se queda dentro de ella.
     */
    public static function rutaValida(string $relativa): bool
    {
        return $relativa !== '' && !str_contains($relativa, '\\') && preg_match('#(^|/)\.\.?(/|$)#', $relativa) !== 1;
    }

    /**
     * El tronco de una imagen: su ruta sin la extensión, si es la de una imagen.
     * `img/foto.jpg` y `img/foto` dan `img/foto`. De un SVG no hay variantes, y
     * da null.
     */
    public static function troncoDe(string $relativa): ?string
    {
        $extension = strtolower(pathinfo($relativa, PATHINFO_EXTENSION));

        if ($extension === 'svg') {
            return null;
        }

        return in_array($extension, self::EXTENSIONES, true)
            ? substr($relativa, 0, -(strlen($extension) + 1))
            : $relativa;
    }

    /**
     * Un fichero de variantes que se busca, como ejemplo en los avisos.
     */
    public function ejemplo(string $tronco): string
    {
        return $tronco . '-' . $this->imagenes->anchos[0] . '.' . $this->imagenes->formatos[0];
    }

    /**
     * Las variantes de una imagen, o null si no tiene ninguna (o el sitio no
     * las declara). Si tiene unas y le faltan otras, avisa de cada fichero que
     * falta. Dos anchos pedidos que dan el mismo ancho real juntan sus
     * ficheros en uno, el del ancho menor.
     *
     * `src`, `ancho` y `alto` son los de la variante más ancha del último
     * formato que tiene variantes; `srcset`, las de ese formato; y `fuentes`,
     * una por cada formato anterior, para los `<source>`.
     *
     * @param string                 $tronco el de la imagen, sin barra inicial (`troncoDe`)
     * @param \Closure(string): void $avisar recibe cada aviso, sin el nombre de quien busca
     *
     * @return array{src: string, ancho: int, alto: int, srcset: string, fuentes: list<array{tipo: string, srcset: string}>}|null
     */
    public function buscar(string $tronco, \Closure $avisar): ?array
    {
        if (!$this->imagenes->activa()) {
            return null;
        }

        /** @var array<string, list<array{real: int, alto: int, pedido: int, ruta: string}>> $encontradas */
        $encontradas = [];
        $faltan = [];

        foreach ($this->imagenes->formatos as $formato) {
            foreach ($this->imagenes->anchos as $pedido) {
                $ruta = "{$tronco}-{$pedido}.{$formato}";
                ($this->anotar)($ruta);
                $fichero = $this->proyecto->ruta(Proyecto::PUBLICO, $ruta);

                if (!is_file($fichero)) {
                    $faltan[] = $ruta;

                    continue;
                }

                $medidas = Dimensiones::leer($fichero);

                if ($medidas === null) {
                    $avisar("publico/{$ruta} no es una imagen que se pueda leer; la imagen sale sin esa variante");

                    continue;
                }

                $encontradas[$formato][] = ['real' => $medidas['ancho'], 'alto' => $medidas['alto'], 'pedido' => $pedido, 'ruta' => $ruta];
            }
        }

        if ($encontradas === []) {
            return null;
        }

        foreach ($faltan as $ruta) {
            $avisar("no existe publico/{$ruta}; la imagen sale sin esa variante");
        }

        $porFormato = array_map(self::sinRepetidas(...), $encontradas);
        $reserva = array_key_last($porFormato);
        $mayor = $porFormato[$reserva][array_key_last($porFormato[$reserva])];

        $fuentes = [];

        foreach ($porFormato as $formato => $variantes) {
            if ($formato !== $reserva) {
                $fuentes[] = ['tipo' => Imagenes::tipoDe($formato), 'srcset' => self::srcset($variantes)];
            }
        }

        return [
            'src' => self::direccion($mayor['ruta']),
            'ancho' => $mayor['real'],
            'alto' => $mayor['alto'],
            'srcset' => self::srcset($porFormato[$reserva]),
            'fuentes' => $fuentes,
        ];
    }

    /**
     * De menor a mayor ancho real, y solo la primera de las que tienen el mismo.
     *
     * @param list<array{real: int, alto: int, pedido: int, ruta: string}> $variantes
     *
     * @return list<array{real: int, alto: int, pedido: int, ruta: string}>
     */
    private static function sinRepetidas(array $variantes): array
    {
        usort($variantes, fn (array $a, array $b) => [$a['real'], $a['pedido']] <=> [$b['real'], $b['pedido']]);

        $unicas = [];
        $ultimo = 0;

        foreach ($variantes as $variante) {
            if ($variante['real'] > $ultimo) {
                $unicas[] = $variante;
                $ultimo = $variante['real'];
            }
        }

        return $unicas;
    }

    /**
     * @param list<array{real: int, alto: int, pedido: int, ruta: string}> $variantes
     */
    private static function srcset(array $variantes): string
    {
        return implode(', ', array_map(fn (array $v) => self::direccion($v['ruta']) . " {$v['real']}w", $variantes));
    }

    /**
     * La dirección de un fichero de `publico/`, con lo que no cabe en una
     * dirección (espacios, tildes) codificado: en un `srcset`, un espacio
     * separaría la dirección de su ancho.
     */
    private static function direccion(string $ruta): string
    {
        return '/' . implode('/', array_map(rawurlencode(...), explode('/', $ruta)));
    }
}
