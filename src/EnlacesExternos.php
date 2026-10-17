<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La sección `enlacesExternos` de `sitio.yml` (formato §2): lo que llevan los
 * enlaces a otros dominios escritos en Markdown, además de `target="_blank"` y
 * `rel="noopener"`, que llevan siempre (formato §8).
 *
 * @internal
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
     * Los dominios que no son externos: el del sitio, con y sin `www.`.
     *
     * @return list<string>
     */
    public static function dominiosPropios(string $urlDelSitio): array
    {
        $dominio = (string) preg_replace('/^www\./i', '', (string) parse_url($urlDelSitio, PHP_URL_HOST));

        return [$dominio, "www.{$dominio}"];
    }

    /**
     * Si una dirección lleva a otro dominio, como lo decide CommonMark para los enlaces del Markdown:
     * tiene dominio y no es uno de los propios. Una ruta del sitio o un `mailto:` no son externos.
     */
    public static function esExterno(string $direccion, string $urlDelSitio): bool
    {
        $dominio = parse_url($direccion, PHP_URL_HOST);

        return is_string($dominio) && !in_array($dominio, self::dominiosPropios($urlDelSitio), true);
    }

    /**
     * El `rel` de un enlace externo: `noopener` siempre y `noreferrer` si el sitio lo pide.
     */
    public function rel(): string
    {
        return $this->noreferrer ? 'noopener noreferrer' : 'noopener';
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
        return (new TextosPorIdioma('enlacesExternos', 'aviso', 'el', 'los enlaces'))->leer($valor, $linea, $idiomas, $avisos);
    }

    private static function leerClase(mixed $valor, ?int $linea, Avisos $avisos): string
    {
        if ($valor === null) {
            return self::CLASE_POR_DEFECTO;
        }

        $clase = Campos::claseDeCss($valor);

        if ($clase !== null) {
            return $clase;
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
