<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La sección `enlacesExternos` de `sitio.yml` (formato §2): lo que llevan los
 * enlaces a otros dominios escritos en Markdown, además de `target="_blank"` y
 * `rel="noopener"`, que llevan siempre (formato §8).
 */
final readonly class EnlacesExternos
{
    /** La clase del aviso si el sitio no dice otra. */
    public const string CLASE_POR_DEFECTO = 'visually-hidden';

    private const array CAMPOS = ['aviso', 'clase', 'noreferrer'];

    /**
     * @param array<string, string> $avisos el texto del aviso de cada idioma del sitio; un idioma
     *                                      que no está aquí no lleva aviso
     * @param string                $clase  la clase del `<span>` del aviso, que define el sitio
     */
    public function __construct(
        public array $avisos = [],
        public string $clase = self::CLASE_POR_DEFECTO,
        public bool $noreferrer = false,
    ) {
    }

    /**
     * El aviso de los enlaces de las páginas de un idioma, o null si no llevan.
     */
    public function avisoDe(string $idioma): ?string
    {
        return $this->avisos[$idioma] ?? null;
    }

    /**
     * Lee la sección tal como sale del YAML. Lo que no vale se avisa, con la
     * línea de `enlacesExternos`, y se ignora: nunca detiene la compilación.
     *
     * @param int|null $linea la de `enlacesExternos` en `sitio.yml`
     */
    public static function leer(mixed $valor, ?int $linea, Idiomas $idiomas, Avisos $avisos): self
    {
        if ($valor === null) {
            return new self();
        }

        $fichero = Proyecto::SITIO;

        if (!is_array($valor) || ($valor !== [] && array_is_list($valor))) {
            $avisos->registrar('«enlacesExternos» tiene que ser un mapa con «aviso», «clase» y «noreferrer»; se ignora', $fichero, $linea);

            return new self();
        }

        foreach (array_keys($valor) as $campo) {
            if (!in_array($campo, self::CAMPOS, true)) {
                $avisos->registrar("«enlacesExternos» no admite «{$campo}»; se ignora", $fichero, $linea);
            }
        }

        return new self(
            self::leerAvisos($valor['aviso'] ?? null, $linea, $idiomas, $avisos),
            self::leerClase($valor['clase'] ?? null, $linea, $avisos),
            self::leerNoreferrer($valor['noreferrer'] ?? null, $linea, $avisos),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function leerAvisos(mixed $valor, ?int $linea, Idiomas $idiomas, Avisos $avisos): array
    {
        if ($valor === null || $valor === []) {
            return [];
        }

        $fichero = Proyecto::SITIO;
        $codigos = $idiomas->codigos();

        if (is_string($valor)) {
            $texto = trim($valor);

            if ($texto === '') {
                return [];
            }

            if (count($codigos) > 1) {
                $avisos->registrar(
                    '«enlacesExternos»: «aviso» es un solo texto y el sitio tiene varios idiomas (' . implode(', ', $codigos)
                    . '); se usa en todos. Conviene un texto por idioma: aviso: {' . $codigos[0] . ': …, ' . $codigos[1] . ': …}',
                    $fichero,
                    $linea,
                );
            }

            return array_fill_keys($codigos, $texto);
        }

        if (!is_array($valor) || array_is_list($valor)) {
            $avisos->registrar(
                '«enlacesExternos»: «aviso» tiene que ser un texto, o un texto por idioma con su código como clave; se ignora',
                $fichero,
                $linea,
            );

            return [];
        }

        $textos = [];

        foreach ($valor as $codigo => $texto) {
            $codigo = (string) $codigo;

            if (!$idiomas->declara($codigo)) {
                $avisos->registrar("«enlacesExternos»: «aviso» tiene un texto para «{$codigo}», que no es un idioma del sitio; se ignora", $fichero, $linea);

                continue;
            }

            if (!is_string($texto) || trim($texto) === '') {
                $avisos->registrar("«enlacesExternos»: el «aviso» de «{$codigo}» tiene que ser un texto; ese idioma no lleva aviso", $fichero, $linea);

                continue;
            }

            $textos[$codigo] = trim($texto);
        }

        foreach ($codigos as $codigo) {
            if (!array_key_exists($codigo, $valor)) {
                $avisos->registrar("«enlacesExternos»: «aviso» no tiene texto en «{$codigo}»; los enlaces de ese idioma no llevan aviso", $fichero, $linea);
            }
        }

        return $textos;
    }

    private static function leerClase(mixed $valor, ?int $linea, Avisos $avisos): string
    {
        if ($valor === null) {
            return self::CLASE_POR_DEFECTO;
        }

        if (is_string($valor) && preg_match('/^[A-Za-z_][A-Za-z0-9_-]*(?: [A-Za-z_][A-Za-z0-9_-]*)*$/', trim($valor)) === 1) {
            return trim($valor);
        }

        $avisos->registrar(
            '«enlacesExternos»: «clase» tiene que ser el nombre de una clase de CSS, o varios separados por espacios; se usa '
            . self::CLASE_POR_DEFECTO,
            Proyecto::SITIO,
            $linea,
        );

        return self::CLASE_POR_DEFECTO;
    }

    private static function leerNoreferrer(mixed $valor, ?int $linea, Avisos $avisos): bool
    {
        if ($valor === null) {
            return false;
        }

        try {
            return Campos::siNo($valor);
        } catch (\UnexpectedValueException) {
            $avisos->registrar('«enlacesExternos»: «noreferrer» tiene que ser sí o no; se ignora', Proyecto::SITIO, $linea);

            return false;
        }
    }
}
