<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lo que se ha leído de un proyecto, antes de compilar nada.
 */
final readonly class Lectura
{
    /**
     * @param array<string, mixed> $datos   los ficheros de `datos/`, por nombre
     * @param list<Pagina>         $paginas ordenadas por ruta
     * @param list<Aviso>          $avisos
     * @param list<string>         $ficheros los demás ficheros de `contenido/`, que se copian tal cual
     * @param array<string, array<string, mixed>> $datosPorIdioma los ficheros de `datos/` con el código
     *                                                           de un idioma, por idioma y nombre
     */
    public function __construct(
        public Sitio $sitio,
        public array $datos,
        public array $paginas,
        public array $avisos,
        public array $ficheros = [],
        public array $datosPorIdioma = [],
    ) {
    }

    /**
     * `datos` en una página de ese idioma (formato §15.6): los ficheros
     * comunes, con los del idioma encima. Si los dos son mapas, se mezclan
     * por sus claves de primer nivel; si no, el del idioma sustituye al común.
     *
     * @return array<string, mixed>
     */
    public function datosDe(string $idioma): array
    {
        $datos = $this->datos;

        foreach ($this->datosPorIdioma[$idioma] ?? [] as $nombre => $propio) {
            $comun = $datos[$nombre] ?? null;

            $datos[$nombre] = self::esMapa($comun) && self::esMapa($propio) ? array_replace($comun, $propio) : $propio;
        }

        return $datos;
    }

    /**
     * @phpstan-assert-if-true array<array-key, mixed> $valor
     */
    private static function esMapa(mixed $valor): bool
    {
        return is_array($valor) && $valor !== [] && !array_is_list($valor);
    }
}
