<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Un campo de una sección de `sitio.yml` que lleva un texto, o un texto por
 * idioma del sitio con su código como clave (`aviso` de `enlacesExternos`,
 * `etiqueta` de `encabezados`, `titulo` de `feed`; formato §2).
 *
 * Lo que no vale se avisa, con la línea de la sección, y se ignora: nunca
 * detiene la compilación. Los avisos nombran la sección y el campo.
 *
 * @internal
 */
final readonly class TextosPorIdioma
{
    /**
     * @param string      $seccion         la sección de `sitio.yml`: `enlacesExternos`
     * @param string      $campo           el campo: `aviso`
     * @param string      $articulo        el que le corresponde en castellano: `el`
     * @param string      $afectados       a quién deja sin texto un idioma que no lo tiene: `los enlaces`
     * @param bool        $avisaDeUnTexto  si un solo texto en un sitio con varios idiomas se avisa (conviene uno por idioma)
     *                                     o es lo que se quiere, como un título que es el nombre de una marca
     * @param string|null $sinTexto        qué pasa con un idioma que se queda sin texto, para los avisos; si falta, que
     *                                     no lleva el campo
     */
    public function __construct(
        private string $seccion,
        private string $campo,
        private string $articulo,
        private string $afectados,
        private bool $avisaDeUnTexto = true,
        private ?string $sinTexto = null,
    ) {
    }

    /**
     * @param int|null $linea la de la sección en `sitio.yml`
     *
     * @return array<string, string> el texto de cada idioma del sitio; un idioma que no está aquí no lleva
     */
    public function leer(mixed $valor, ?int $linea, Idiomas $idiomas, Avisos $avisos): array
    {
        if ($valor === null || $valor === []) {
            return [];
        }

        $fichero = Proyecto::SITIO;
        $codigos = $idiomas->codigos();
        $seccion = $this->seccion;
        $campo = $this->campo;

        if (is_string($valor)) {
            $texto = trim($valor);

            if ($texto === '') {
                return [];
            }

            if ($this->avisaDeUnTexto && count($codigos) > 1) {
                $avisos->registrar(
                    "«{$seccion}»: «{$campo}» es un solo texto y el sitio tiene varios idiomas (" . implode(', ', $codigos)
                    . "); se usa en todos. Conviene un texto por idioma: {$campo}: {" . $codigos[0] . ': …, ' . $codigos[1] . ': …}',
                    $fichero,
                    $linea,
                );
            }

            return array_fill_keys($codigos, $texto);
        }

        if (!is_array($valor) || array_is_list($valor)) {
            $avisos->registrar(
                "«{$seccion}»: «{$campo}» tiene que ser un texto, o un texto por idioma con su código como clave; se ignora",
                $fichero,
                $linea,
            );

            return [];
        }

        $textos = [];

        foreach ($valor as $codigo => $texto) {
            $codigo = (string) $codigo;

            if (!$idiomas->declara($codigo)) {
                $avisos->registrar("«{$seccion}»: «{$campo}» tiene un texto para «{$codigo}», que no es un idioma del sitio; se ignora", $fichero, $linea);

                continue;
            }

            if (!is_string($texto) || trim($texto) === '') {
                $avisos->registrar(
                    "«{$seccion}»: {$this->articulo} «{$campo}» de «{$codigo}» tiene que ser un texto; " . ($this->sinTexto ?? "ese idioma no lleva {$campo}"),
                    $fichero,
                    $linea,
                );

                continue;
            }

            $textos[$codigo] = trim($texto);
        }

        foreach ($codigos as $codigo) {
            if (!array_key_exists($codigo, $valor)) {
                $avisos->registrar(
                    "«{$seccion}»: «{$campo}» no tiene texto en «{$codigo}»; " . ($this->sinTexto ?? "{$this->afectados} de ese idioma no llevan {$campo}"),
                    $fichero,
                    $linea,
                );
            }
        }

        return $textos;
    }
}
