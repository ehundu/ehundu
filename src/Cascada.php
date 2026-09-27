<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Los `_datos.yml` de `contenido/` y sus subcarpetas (formato §3). Cada
 * carpeta hereda lo de sus antecesoras; gana lo más cercano, salvo
 * `etiquetas`, `css` y `js`, que se suman. En cada carpeta, el `_datos.eu.yml`
 * de un idioma va encima de su `_datos.yml` (formato §15.6).
 *
 * Lee también los campos comunes de una página, el `.yml` con su nombre
 * (formato §15.7). Cada fichero se lee una sola vez, la primera vez que se
 * necesita.
 *
 * @internal
 */
final class Cascada
{
    public const string FICHERO = '_datos.yml';

    /** Campos que se suman a lo largo de la cascada en lugar de sustituirse. */
    public const array SUMADOS = ['etiquetas', 'css', 'js'];

    /** @var array<string, array<array-key, mixed>> campos ya combinados, por idioma y carpeta */
    private array $porCarpeta = [];

    /** @var array<string, array<array-key, mixed>> campos de cada fichero ya leído, por su ruta */
    private array $leidos = [];

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Avisos $avisos,
        private readonly \DateTimeZone $zona = new \DateTimeZone('UTC'),
    ) {
    }

    /**
     * Los campos que hereda lo que hay en una carpeta.
     *
     * @param string $carpeta ruta dentro de `contenido/`: '' es la raíz, 'blog/recetas' una subcarpeta
     * @param string $idioma  el de las páginas que heredan
     *
     * @return array<array-key, mixed>
     *
     * @throws ErrorDeProyecto si algún `_datos.yml` no se puede leer
     */
    public function campos(string $carpeta, string $idioma = Idiomas::PREDETERMINADO): array
    {
        $clave = "{$idioma}:{$carpeta}";

        if (!array_key_exists($clave, $this->porCarpeta)) {
            $heredados = $carpeta === '' ? [] : $this->campos(self::padre($carpeta), $idioma);
            $comunes = self::combinar($heredados, $this->leer(self::ruta($carpeta, self::FICHERO)));
            $this->porCarpeta[$clave] = self::combinar($comunes, $this->leer(self::ruta($carpeta, "_datos.{$idioma}.yml")));
        }

        return $this->porCarpeta[$clave];
    }

    /**
     * Los campos comunes a las traducciones de una página (formato §15.7).
     *
     * @param string $ruta la del `.yml` dentro de `contenido/`
     *
     * @return array<array-key, mixed>
     *
     * @throws ErrorDeProyecto si no se puede leer
     */
    public function comunes(string $ruta): array
    {
        return $this->leer(Proyecto::CONTENIDO . "/{$ruta}");
    }

    /**
     * Pone un nivel de campos encima de otro: gana el de encima, salvo
     * `etiquetas`, `css` y `js`, que se suman sin repetir. Listas y mapas se sustituyen
     * enteros.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $encima
     *
     * @return array<array-key, mixed>
     */
    public static function combinar(array $base, array $encima): array
    {
        $combinados = array_replace($base, $encima);

        foreach (self::SUMADOS as $campo) {
            if (array_key_exists($campo, $base) || array_key_exists($campo, $encima)) {
                $combinados[$campo] = array_values(array_unique([
                    ...($base[$campo] ?? []),
                    ...($encima[$campo] ?? []),
                ]));
            }
        }

        return $combinados;
    }

    /**
     * @param string $fichero ruta relativa a la raíz del proyecto
     *
     * @return array<array-key, mixed>
     */
    private function leer(string $fichero): array
    {
        if (!array_key_exists($fichero, $this->leidos)) {
            if (!is_file($this->proyecto->ruta($fichero))) {
                $this->leidos[$fichero] = [];
            } else {
                $yaml = $this->proyecto->leerTexto($fichero);
                $this->leidos[$fichero] = Campos::normalizar(Yaml::leerCampos($yaml, $fichero), $yaml, 1, $fichero, $this->avisos, $this->zona);
            }
        }

        return $this->leidos[$fichero];
    }

    private static function ruta(string $carpeta, string $nombre): string
    {
        return implode('/', array_filter([Proyecto::CONTENIDO, $carpeta, $nombre], fn ($parte) => $parte !== ''));
    }

    private static function padre(string $carpeta): string
    {
        $barra = strrpos($carpeta, '/');

        return $barra === false ? '' : substr($carpeta, 0, $barra);
    }
}
