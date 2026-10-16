<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Avisos;
use Ehundu\Colecciones;
use Ehundu\Lector;
use Ehundu\Lectura;
use Ehundu\Pagina;
use Ehundu\Plantillas\Maquetador;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\Png;
use Ehundu\Pruebas\Apoyo\Webp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las variantes de ancho (formato §2, §7.3, §8 y §8.2) en las páginas: la
 * función `variantes()`, el atajo `imagen` y las figuras del Markdown.
 */
final class VariantesEnPaginasPrueba extends TestCase
{
    use CarpetaTemporal;

    private Avisos $avisos;
    private Lectura $lectura;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    #[Test]
    public function elAtajoImagenEscribeElSrcsetDeSusVariantes(): void
    {
        $this->sitio("anchos: [480, 960]\n  formatos: [webp]");
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-960.webp', 960, 640);

        self::assertSame(
            "<figure><img src=\"/img/foto-960.webp\" srcset=\"/img/foto-480.webp 480w, /img/foto-960.webp 960w\" sizes=\"100vw\" alt=\"Una foto\" width=\"960\" height=\"640\"><figcaption>Una foto</figcaption></figure>\n",
            $this->cuerpo('[imagen fichero="img/foto.jpg" alt="Una foto"]'),
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unaFiguraDelMarkdownLlevaLasMismasVariantesQueElAtajo(): void
    {
        $this->sitio("anchos: [480, 960]\n  formatos: [webp]");
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-960.webp', 960, 640);

        self::assertSame(
            $this->cuerpo('[imagen fichero="img/foto.jpg" alt="Una foto"]'),
            $this->cuerpo('![Una foto](/img/foto.jpg)'),
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function conVariosFormatosSaleUnPictureConUnSourcePorFormato(): void
    {
        $this->sitio("anchos: [480, 960]\n  formatos: [webp, png]");
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-960.webp', 960, 640);
        $this->crearFichero('publico/img/foto-480.png', Png::de(480, 320));
        $this->crearFichero('publico/img/foto-960.png', Png::de(960, 640));

        self::assertSame(
            '<figure><picture><source type="image/webp" srcset="/img/foto-480.webp 480w, /img/foto-960.webp 960w" sizes="100vw">'
            . '<img src="/img/foto-960.png" srcset="/img/foto-480.png 480w, /img/foto-960.png 960w" sizes="100vw" alt="Una foto" width="960" height="640">'
            . "</picture><figcaption>Una foto</figcaption></figure>\n",
            $this->cuerpo('![Una foto](/img/foto.jpg)'),
        );
    }

    #[Test]
    public function elSizesEsElDelAtributoSiLoLlevaYSiNoElDeLaSeccion(): void
    {
        $this->sitio("anchos: [480]\n  sizes: \"(min-width: 60rem) 50rem, 100vw\"");
        $this->webp('img/foto-480.webp', 480, 320);

        self::assertStringContainsString(
            'sizes="(min-width: 60rem) 50rem, 100vw"',
            $this->cuerpo('![Una foto](/img/foto.jpg)'),
        );
        self::assertStringContainsString(
            'sizes="30vw"',
            $this->cuerpo('[imagen fichero="img/foto.jpg" alt="Una foto" sizes="30vw"]'),
        );
    }

    #[Test]
    public function unaImagenConVariantesNoNecesitaElFicheroQueDaNombreAlTronco(): void
    {
        $this->sitio('anchos: [480]');
        $this->webp('img/foto-480.webp', 480, 320);

        $this->cuerpo('[imagen fichero="img/foto.jpg" alt="Una foto"]');
        $this->cuerpo('[imagen fichero="img/foto" alt="Una foto"]');

        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unaImagenSinNingunaVarianteSaleComoSiempreYSinAviso(): void
    {
        $this->sitio('anchos: [480, 960]');
        $this->crearFichero('publico/img/logo.png', Png::de(200, 100));
        $this->crearFichero('publico/svg/marca.svg', '<svg></svg>');

        self::assertSame(
            "<figure><img src=\"/img/logo.png\" alt=\"Logo\" width=\"200\" height=\"100\"><figcaption>Logo</figcaption></figure>\n",
            $this->cuerpo('![Logo](/img/logo.png)'),
        );
        self::assertSame(
            "<figure><img src=\"/svg/marca.svg\" alt=\"Marca\"><figcaption>Marca</figcaption></figure>\n",
            $this->cuerpo('[imagen fichero="svg/marca.svg" alt="Marca"]'),
        );
        self::assertSame(
            "<figure><img src=\"https://ejemplo.com/a.jpg\" alt=\"Fuera\"><figcaption>Fuera</figcaption></figure>\n",
            $this->cuerpo('![Fuera](https://ejemplo.com/a.jpg)'),
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function siLeFaltanVariantesAvisaDeCadaFicheroConLaLineaDeLaPagina(): void
    {
        $this->sitio('anchos: [480, 960]');
        $this->webp('img/foto-480.webp', 480, 320);

        $this->cuerpo("[imagen fichero=\"img/foto.jpg\" alt=\"Una foto\"]\n\n![Otra vez](/img/foto.jpg)");

        self::assertSame([
            'contenido/index.md:5: [imagen] no existe publico/img/foto-960.webp; la imagen sale sin esa variante',
            'contenido/index.md:7: [imagen] no existe publico/img/foto-960.webp; la imagen sale sin esa variante',
        ], $this->avisos());
    }

    #[Test]
    public function sinLaSeccionLasImagenesSalenComoSiempreAunqueHayaFicherosConEseNombre(): void
    {
        $this->sitio(null);
        $this->crearFichero('publico/img/foto.png', Png::de(40, 30));
        $this->webp('img/foto-480.webp', 480, 320);

        self::assertSame(
            "<figure><img src=\"/img/foto.png\" alt=\"Una foto\" width=\"40\" height=\"30\"><figcaption>Una foto</figcaption></figure>\n",
            $this->cuerpo('![Una foto](/img/foto.png)'),
        );
    }

    #[Test]
    public function elSitioQueSustituyeLaImagenRecibeElSrcsetElSizesYLasFuentes(): void
    {
        $this->sitio("anchos: [480]\n  formatos: [avif, webp]");
        $this->webp('img/foto-480.webp', 480, 320);
        $this->crearFichero('publico/img/foto-480.avif', Png::de(480, 320));
        $this->crearFichero(
            'parciales/atajos/imagen.twig',
            '<img src="{{ src }}" srcset="{{ srcset }}" sizes="{{ sizes }}" data-fuentes="{{ fuentes|length }}">',
        );

        // El AVIF de prueba es un PNG: getimagesize lo lee igual y no hace falta más.
        self::assertSame(
            '<img src="/img/foto-480.webp" srcset="/img/foto-480.webp 480w" sizes="100vw" data-fuentes="1">',
            trim($this->cuerpo('![Una foto](/img/foto.jpg)')),
        );
    }

    #[Test]
    public function laFuncionVariantesDaLosDatosYLaPlantillaEscribeElHtml(): void
    {
        $this->sitio("anchos: [480, 960]\n  formatos: [avif, webp]");
        $this->webp('img/foto-480.webp', 480, 320);
        $this->webp('img/foto-960.webp', 960, 640);
        $this->crearFichero('publico/img/foto-480.avif', Png::de(480, 320));
        $this->crearFichero('publico/img/foto-960.avif', Png::de(960, 640));

        $html = $this->twig(
            "{% set f = variantes('img/foto.jpg') %}"
            . "{{ f.src }}|{{ f.ancho }}x{{ f.alto }}|{{ f.srcset }}|"
            . "{% for fuente in f.fuentes %}{{ fuente.tipo }}={{ fuente.srcset }}{% endfor %}|"
            . "{{ variantes('/img/foto').ancho }}",
        );

        self::assertSame(
            '/img/foto-960.webp|960x640|/img/foto-480.webp 480w, /img/foto-960.webp 960w|'
            . 'image/avif=/img/foto-480.avif 480w, /img/foto-960.avif 960w|960',
            $html,
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function laFuncionVariantesAvisaYDaNullSiNoHayNada(): void
    {
        $this->sitio('anchos: [480]');
        $this->crearFichero('publico/svg/marca.svg', '<svg></svg>');

        $this->twig(
            "{{ variantes('img/sin-nada.jpg') is null ? 'a' }}{{ variantes('../sitio.yml') is null ? 'b' }}{{ variantes('svg/marca.svg') is null ? 'c' }}",
        );

        self::assertSame([
            "variantes('img/sin-nada.jpg'): no hay ninguna variante de img/sin-nada; se busca, por ejemplo, publico/img/sin-nada-480.webp",
            "variantes('../sitio.yml'): solo se leen imágenes de dentro de publico/",
        ], $this->avisos());
    }

    #[Test]
    public function laFuncionVariantesAvisaSiElSitioNoDeclaraImagenes(): void
    {
        $this->sitio(null);
        $this->webp('img/foto-480.webp', 480, 320);

        self::assertSame('a', $this->twig("{{ variantes('img/foto.jpg') is null ? 'a' }}"));
        self::assertSame(
            ["variantes('img/foto.jpg'): el sitio no declara «imagenes» con sus «anchos» en sitio.yml; no hay variantes"],
            $this->avisos(),
        );
    }

    /**
     * @param string|null $imagenes el contenido de la sección `imagenes`, sangrado dos espacios; null, sin sección
     */
    private function sitio(?string $imagenes): void
    {
        $this->crearFichero(
            'sitio.yml',
            "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\n" . ($imagenes === null ? '' : "imagenes:\n  {$imagenes}\n"),
        );
    }

    private function webp(string $ruta, int $ancho, int $alto): void
    {
        $this->crearFichero("publico/{$ruta}", Webp::de($ancho, $alto));
    }

    private function cuerpo(string $markdown): string
    {
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\nplantilla: false\n---\n{$markdown}");

        return $this->maquetador()->cuerpo($this->primera());
    }

    private function twig(string $plantilla): string
    {
        $this->crearFichero('contenido/index.twig', "---\ntitulo: Inicio\nplantilla: false\n---\n{$plantilla}");

        return $this->maquetador()->cuerpo($this->primera());
    }

    private function maquetador(): Maquetador
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->lectura = (new Lector())->leer($proyecto);

        return new Maquetador($proyecto, $this->lectura, new Colecciones($this->lectura->paginas, new \DateTimeImmutable()), $this->avisos);
    }

    private function primera(): Pagina
    {
        return $this->lectura->paginas[0];
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
