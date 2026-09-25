<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Los `_datos.yml` de `contenido/` y sus subcarpetas (formato §3). Cada
 * carpeta hereda lo de sus antecesoras; gana lo más cercano, salvo
 * `etiquetas`, que se suman.
 *
 * Cada `_datos.yml` se lee una sola vez, la primera vez que se necesita.
 *
 * @internal
 */
final class Cascada
{
    public const string FICHERO = '_datos.yml';

    /** @var array<string, array<array-key, mixed>> campos ya combinados, por carpeta */
    private array $porCarpeta = [];

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Avisos $avisos,
    ) {
    }

    /**
     * Los campos que hereda lo que hay en una carpeta.
     *
     * @param string $carpeta ruta dentro de `contenido/`: '' es la raíz, 'blog/recetas' una subcarpeta
     *
     * @return array<array-key, mixed>
     *
     * @throws ErrorDeProyecto si algún `_datos.yml` no se puede leer
     */
    public function campos(string $carpeta): array
    {
        if (!array_key_exists($carpeta, $this->porCarpeta)) {
            $heredados = $carpeta === '' ? [] : $this->campos(self::padre($carpeta));
            $this->porCarpeta[$carpeta] = self::combinar($heredados, $this->leer($carpeta));
        }

        return $this->porCarpeta[$carpeta];
    }

    /**
     * Pone un nivel de campos encima de otro: gana el de encima, salvo
     * `etiquetas`, que se suman sin repetir. Listas y mapas se sustituyen
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

        if (array_key_exists('etiquetas', $base) || array_key_exists('etiquetas', $encima)) {
            $combinados['etiquetas'] = array_values(array_unique([
                ...($base['etiquetas'] ?? []),
                ...($encima['etiquetas'] ?? []),
            ]));
        }

        return $combinados;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function leer(string $carpeta): array
    {
        $fichero = implode('/', array_filter([Proyecto::CONTENIDO, $carpeta, self::FICHERO], fn ($parte) => $parte !== ''));

        if (!is_file($this->proyecto->ruta($fichero))) {
            return [];
        }

        $yaml = $this->proyecto->leerTexto($fichero);

        return Campos::normalizar(Yaml::leerCampos($yaml, $fichero), $yaml, 1, $fichero, $this->avisos);
    }

    private static function padre(string $carpeta): string
    {
        $barra = strrpos($carpeta, '/');

        return $barra === false ? '' : substr($carpeta, 0, $barra);
    }
}
