<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Separa el front matter del cuerpo de un fichero de contenido. El front
 * matter es YAML y va entre dos líneas `---` al principio del fichero.
 *
 * @internal
 */
final readonly class FrontMatter
{
    /**
     * @param string $yaml        el texto entre las dos líneas `---`
     * @param int    $lineaYaml   línea del fichero en la que empieza ese texto
     * @param int    $lineaCuerpo línea del fichero en la que empieza el cuerpo
     */
    private function __construct(
        public string $yaml,
        public int $lineaYaml,
        public string $cuerpo,
        public int $lineaCuerpo,
    ) {
    }

    /**
     * @param string $texto   el fichero entero, con finales de línea LF
     * @param string $fichero ruta relativa a la raíz del proyecto, para los errores
     *
     * @throws ErrorDeProyecto si el front matter no se cierra o no es YAML
     */
    public static function separar(string $texto, string $fichero): self
    {
        $lineas = explode("\n", $texto);

        if (rtrim($lineas[0]) !== '---') {
            if (preg_match('/^---\S/', $lineas[0]) === 1) {
                throw new ErrorDeProyecto('Solo se admite front matter en YAML, entre dos líneas «---»', $fichero, 1);
            }

            return new self('', 1, $texto, 1);
        }

        for ($i = 1, $total = count($lineas); $i < $total; $i++) {
            if (rtrim($lineas[$i]) === '---') {
                return new self(
                    implode("\n", array_slice($lineas, 1, $i - 1)),
                    2,
                    implode("\n", array_slice($lineas, $i + 1)),
                    $i + 2,
                );
            }
        }

        throw new ErrorDeProyecto('El front matter no se cierra: falta la segunda línea «---»', $fichero, 1);
    }
}
