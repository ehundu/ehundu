<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La configuración de `sitio.yml` (formato §2).
 */
final readonly class Sitio
{
    /**
     * @param string                  $url         dirección absoluta, sin barra final
     * @param \DateTimeZone           $zonaHoraria la de `zonaHoraria`, o UTC si no se indica
     * @param array<array-key, mixed> $campos      todo `sitio.yml` salvo `despliegue`: es la
     *                                             variable `sitio` de las plantillas
     * @param Idiomas                 $idiomas     los de `idiomas`, o el de `idioma` (formato §15.1)
     */
    public function __construct(
        public string $nombre,
        public string $url,
        public \DateTimeZone $zonaHoraria,
        public array $campos,
        public Idiomas $idiomas = new Idiomas(),
    ) {
    }

    /**
     * La dirección completa de una URL del sitio: `/blog/` da
     * `https://www.ejemplo.com/blog/`. Lo que no puede ir tal cual en una
     * dirección (espacios, tildes) se codifica.
     */
    public function absoluta(string $url): string
    {
        $codificada = preg_replace_callback(
            "~[^A-Za-z0-9\\-._\\~/!$&'()*+,;=:@%]~",
            fn (array $caracter) => rawurlencode($caracter[0]),
            $url,
        );

        return $this->url . $codificada;
    }
}
