<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La sección `imagenes` de `sitio.yml` (formato §2): los anchos y los formatos
 * en que otra herramienta deja las variantes de cada imagen de `publico/`, y el
 * `sizes` que llevan las del atajo `imagen` y las figuras del Markdown (§7.3, §8).
 *
 * El motor no genera ninguna variante: las busca (`Variantes`). Sin la sección,
 * o sin ningún ancho válido, las imágenes salen como siempre.
 */
final readonly class Imagenes
{
    /** El `sizes` de una imagen si el sitio no dice otro: nunca pide menos imagen de la que hace falta. */
    public const string SIZES_POR_DEFECTO = '100vw';

    /** Los formatos que se pueden pedir, con su tipo MIME. */
    public const array FORMATOS = [
        'avif' => 'image/avif',
        'webp' => 'image/webp',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public const array FORMATOS_POR_DEFECTO = ['webp'];

    private const array CAMPOS = ['anchos', 'formatos', 'sizes'];

    /**
     * @param list<int>    $anchos   los anchos pedidos, de menor a mayor; vacío si no hay sección o no vale
     * @param list<string> $formatos de más a menos preferido; el último es el de reserva
     * @param string       $sizes    el de las imágenes del atajo y de las figuras si no dicen otro
     */
    public function __construct(
        public array $anchos = [],
        public array $formatos = self::FORMATOS_POR_DEFECTO,
        public string $sizes = self::SIZES_POR_DEFECTO,
    ) {
    }

    /**
     * Si el sitio declara variantes de imagen.
     */
    public function activa(): bool
    {
        return $this->anchos !== [];
    }

    /**
     * El tipo MIME de un formato de los que se pueden pedir.
     */
    public static function tipoDe(string $formato): string
    {
        return self::FORMATOS[$formato] ?? 'application/octet-stream';
    }

    /**
     * Lee la sección tal como sale del YAML. Lo que no vale se avisa, con la
     * línea de `imagenes`, y se ignora: nunca detiene la compilación.
     *
     * @param int|null $linea la de `imagenes` en `sitio.yml`
     */
    public static function leer(mixed $valor, ?int $linea, Avisos $avisos): self
    {
        if ($valor === null) {
            return new self();
        }

        $fichero = Proyecto::SITIO;

        if (!is_array($valor) || ($valor !== [] && array_is_list($valor))) {
            $avisos->registrar('«imagenes» tiene que ser un mapa con «anchos», «formatos» y «sizes»; se ignora', $fichero, $linea);

            return new self();
        }

        foreach (array_keys($valor) as $campo) {
            if (!in_array($campo, self::CAMPOS, true)) {
                $avisos->registrar("«imagenes» no admite «{$campo}»; se ignora", $fichero, $linea);
            }
        }

        $anchos = self::leerAnchos($valor['anchos'] ?? null, $linea, $avisos);

        if ($anchos === []) {
            return new self();
        }

        return new self(
            $anchos,
            self::leerFormatos($valor['formatos'] ?? null, $linea, $avisos),
            self::leerSizes($valor['sizes'] ?? null, $linea, $avisos),
        );
    }

    /**
     * @return list<int>
     */
    private static function leerAnchos(mixed $valor, ?int $linea, Avisos $avisos): array
    {
        $fichero = Proyecto::SITIO;

        if ($valor === null) {
            $avisos->registrar('«imagenes» necesita «anchos», la lista de anchos de las variantes (por ejemplo [480, 960, 1600]); se ignora la sección', $fichero, $linea);

            return [];
        }

        if (!is_array($valor) || !array_is_list($valor)) {
            $avisos->registrar('«imagenes»: «anchos» tiene que ser una lista de números enteros mayores que cero; se ignora la sección', $fichero, $linea);

            return [];
        }

        $anchos = [];

        foreach ($valor as $ancho) {
            if (is_int($ancho) && $ancho > 0) {
                $anchos[] = $ancho;

                continue;
            }

            $texto = is_scalar($ancho) ? (string) $ancho : get_debug_type($ancho);
            $avisos->registrar("«imagenes»: «{$texto}» no es un ancho válido (un número entero mayor que cero); se ignora", $fichero, $linea);
        }

        if ($anchos === []) {
            $avisos->registrar('«imagenes»: «anchos» no tiene ningún ancho válido; se ignora la sección', $fichero, $linea);

            return [];
        }

        $anchos = array_values(array_unique($anchos));
        sort($anchos);

        return $anchos;
    }

    /**
     * @return list<string>
     */
    private static function leerFormatos(mixed $valor, ?int $linea, Avisos $avisos): array
    {
        if ($valor === null) {
            return self::FORMATOS_POR_DEFECTO;
        }

        $fichero = Proyecto::SITIO;
        $admitidos = implode(', ', array_keys(self::FORMATOS));

        if (!is_array($valor) || !array_is_list($valor)) {
            $avisos->registrar("«imagenes»: «formatos» tiene que ser una lista ({$admitidos}); se usa webp", $fichero, $linea);

            return self::FORMATOS_POR_DEFECTO;
        }

        $formatos = [];

        foreach ($valor as $formato) {
            $limpio = is_string($formato) ? strtolower(trim($formato)) : null;

            if ($limpio === null || !isset(self::FORMATOS[$limpio])) {
                $texto = is_scalar($formato) ? (string) $formato : get_debug_type($formato);
                $avisos->registrar("«imagenes»: «formatos» no admite «{$texto}» (valen {$admitidos}); se ignora", $fichero, $linea);

                continue;
            }

            if (!in_array($limpio, $formatos, true)) {
                $formatos[] = $limpio;
            }
        }

        if ($formatos === []) {
            $avisos->registrar('«imagenes»: «formatos» no tiene ningún formato válido; se usa webp', $fichero, $linea);

            return self::FORMATOS_POR_DEFECTO;
        }

        return $formatos;
    }

    private static function leerSizes(mixed $valor, ?int $linea, Avisos $avisos): string
    {
        if ($valor === null) {
            return self::SIZES_POR_DEFECTO;
        }

        if (is_string($valor) && trim($valor) !== '') {
            return trim($valor);
        }

        $avisos->registrar('«imagenes»: «sizes» tiene que ser un texto, por ejemplo "(min-width: 60rem) 50rem, 100vw"; se usa ' . self::SIZES_POR_DEFECTO, Proyecto::SITIO, $linea);

        return self::SIZES_POR_DEFECTO;
    }
}
