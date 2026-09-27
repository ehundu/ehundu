<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Un fichero de `contenido/` que se compila, en Markdown o en Twig, con sus
 * campos ya combinados con la cascada y normalizados.
 */
final readonly class Pagina
{
    /**
     * @param string                  $ruta        ruta dentro de `contenido/`: 'blog/un-articulo.md'
     * @param string                  $formato     'md' o 'twig'
     * @param array<array-key, mixed> $campos
     * @param string|false            $url         la URL ya resuelta, o false si es un fragmento
     * @param string                  $cuerpo      el fichero sin el front matter
     * @param int                     $lineaCuerpo línea del fichero en la que empieza el cuerpo
     * @param string                  $idioma      el código de su idioma, que sale del nombre (formato §15.2)
     */
    public function __construct(
        public string $ruta,
        public string $formato,
        public array $campos,
        public string|false $url,
        public string $cuerpo,
        public int $lineaCuerpo,
        public string $idioma = Idiomas::PREDETERMINADO,
    ) {
    }

    /**
     * Lo que comparte con sus traducciones: la ruta sin la extensión ni el
     * código de idioma. `blog/uno.md` y `blog/uno.eu.md` dan `blog/uno`.
     */
    public function clave(): string
    {
        return (string) preg_replace('#(\.[a-z]{2,3})?\.[^./]+$#', '', $this->ruta);
    }

    /**
     * El valor de un campo tal como lo ven las plantillas y los filtros de
     * colección (formato §6.2 y §7.2): `url` es la URL ya resuelta, o null
     * en un fragmento; `ruta`, la del fichero; `idioma`, el de la página, y
     * los demás salen de los campos. `contenido` y `traducciones` no están
     * aquí: los dan las plantillas.
     */
    public function valor(string $campo): mixed
    {
        return match ($campo) {
            'url' => $this->url === false ? null : $this->url,
            'ruta' => $this->ruta,
            'idioma' => $this->idioma,
            default => $this->campos[$campo] ?? null,
        };
    }

    /**
     * Si la página está publicada en ese momento: no es un borrador y su
     * fecha de `publicar`, si la tiene, ya ha llegado. Vale también para los
     * fragmentos, que se publican aunque no generen fichero. Con borradores
     * (la previsualización con `--borradores`), todo cuenta como publicado.
     */
    public function estaPublicada(\DateTimeImmutable $ahora, bool $conBorradores = false): bool
    {
        if ($conBorradores) {
            return true;
        }

        if (($this->campos['borrador'] ?? false) === true) {
            return false;
        }

        $publicar = $this->campos['publicar'] ?? null;

        return !$publicar instanceof \DateTimeImmutable || $publicar <= $ahora;
    }
}
