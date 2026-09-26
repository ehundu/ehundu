<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Avisos;
use Ehundu\Colecciones;
use Ehundu\ErrorDeProyecto;
use Ehundu\Lector;
use Ehundu\Plantillas\Maquetador;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AtajosPrueba extends TestCase
{
    use CarpetaTemporal;

    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\n");
        $this->crearFichero('datos/cliente.yml', "nombre: La Esquina\nlegal:\n  razonSocial: La Esquina S. L.\ndireccion:\n  ciudad: ''\n");
    }

    #[Test]
    public function unaImagenSolaSaleConLaFiguraDeEhundu(): void
    {
        self::assertSame(
            "<figure><img src=\"/img/escaparate.jpg\" alt=\"El escaparate en otoño\"><figcaption>El <strong>escaparate</strong> en otoño</figcaption></figure>\n",
            $this->cuerpo('![El **escaparate** en otoño](/img/escaparate.jpg)'),
        );
    }

    #[Test]
    public function elSitioPuedeSustituirLaFigura(): void
    {
        $this->crearFichero(
            'parciales/atajos/imagen.twig',
            "<figure><img src=\"{{ src }}\" alt=\"{{ alt }}\" class=\"lazy\"><figcaption>{{ pie }}</figcaption></figure>\n",
        );

        self::assertSame(
            "<figure><img src=\"/img/a.jpg\" alt=\"Un servicio\" class=\"lazy\"><figcaption>Un <strong>servicio</strong></figcaption></figure>\n",
            $this->cuerpo('![Un **servicio**](/img/a.jpg)'),
        );
    }

    #[Test]
    public function elAtajoImagenDaLaMismaFigura(): void
    {
        $this->crearFichero('publico/img/escaparate.jpg', 'jpg');

        self::assertSame(
            "<figure><img src=\"/img/escaparate.jpg\" alt=\"El escaparate\"><figcaption>La tienda &amp; el otoño</figcaption></figure>\n",
            $this->cuerpo('[imagen fichero="img/escaparate.jpg" alt="El escaparate" pie="La tienda & el otoño"]'),
        );
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function elAtajoImagenAvisaDeLoQueFalta(): void
    {
        $this->cuerpo("[imagen fichero=\"img/no-esta.jpg\"]\n\n[imagen alt=\"Sin fichero\"]");

        self::assertSame([
            'contenido/index.md:5: [imagen] no existe publico/img/no-esta.jpg',
            'contenido/index.md:5: [imagen] falta «alt», el texto alternativo de la imagen',
            'contenido/index.md:7: [imagen] falta «fichero», la imagen dentro de publico/; no se inserta nada',
        ], $this->avisos());
    }

    #[Test]
    public function elAtajoVideoAdmiteYoutubeVimeoYFicheros(): void
    {
        $this->crearFichero('publico/video/visita.mp4', 'mp4');

        $html = $this->cuerpo(
            "[video url=\"https://www.youtube.com/watch?v=dQw4w9WgXcQ\" titulo=\"Visita\"]\n\n"
            . "[video url=\"https://vimeo.com/76979871\" proporcion=\"4:3\"]\n\n"
            . "[video fichero=\"video/visita.mp4\"]\n\n"
            . '[video url="https://ejemplo.com/v.mp4"]',
        );

        self::assertStringContainsString('<div class="video" style="aspect-ratio: 16 / 9"><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Visita"', $html);
        self::assertStringContainsString('<div class="video" style="aspect-ratio: 4 / 3"><iframe src="https://player.vimeo.com/video/76979871?dnt=1" title="Vídeo"', $html);
        self::assertStringContainsString('<video src="/video/visita.mp4" title="Vídeo"', $html);
        self::assertSame(
            ['contenido/index.md:11: [video] solo se admiten vídeos de YouTube y Vimeo, o un «fichero» de publico/; no se inserta https://ejemplo.com/v.mp4'],
            $this->avisos(),
        );
    }

    #[Test]
    public function elAtajoArchivoDaElTipoYElPeso(): void
    {
        $this->crearFichero('publico/docs/tarifas.pdf', str_repeat('x', 1_258_291));
        $this->crearFichero('publico/docs/folleto.pdf', str_repeat('x', 358_400));

        self::assertSame(
            "<p>Descarga <a href=\"/docs/tarifas.pdf\">Tarifas 2025</a> (PDF, 1,2 MB) o <a href=\"/docs/folleto.pdf\">folleto.pdf</a> (PDF, 350 KB).</p>\n",
            $this->cuerpo('Descarga [archivo fichero="docs/tarifas.pdf" texto="Tarifas 2025"] o [archivo fichero="docs/folleto.pdf"].'),
        );
    }

    #[Test]
    public function elAtajoDatoEscribeUnValorDeDatos(): void
    {
        self::assertSame(
            "<p>Titular: La Esquina S. L., en su sede social.</p>\n",
            $this->cuerpo('Titular: [dato clave="cliente.legal.razonSocial"], en [dato clave="cliente.direccion.ciudad" siFalta="su sede social"].'),
        );
    }

    #[Test]
    public function elAtajoDatoAvisaSiNoHayValor(): void
    {
        self::assertSame("<p>Teléfono: .</p>\n", $this->cuerpo('Teléfono: [dato clave="cliente.telefono"].'));
        self::assertSame(['contenido/index.md:5: [dato] no hay ningún dato en «cliente.telefono»; no se inserta nada'], $this->avisos());
    }

    #[Test]
    public function unAtajoDelSitioConContenidoYDatos(): void
    {
        $this->crearFichero(
            'parciales/atajos/aviso.twig',
            "<aside class=\"aviso aviso-{{ tipo }}\"><p>{{ datos.cliente.nombre }} avisa:</p>{{ contenido }}</aside>\n",
        );

        self::assertSame(
            "<aside class=\"aviso aviso-importante\"><p>La Esquina avisa:</p><p>Las plazas son <strong>limitadas</strong>.</p></aside>\n",
            $this->cuerpo("[aviso tipo=\"importante\"]\nLas plazas son **limitadas**.\n[/aviso]"),
        );
    }

    #[Test]
    public function losNombresReservadosNoSonAtributos(): void
    {
        $this->crearFichero('parciales/atajos/saludo.twig', 'Hola desde {{ sitio.nombre }}');

        self::assertSame("<p>Hola desde Librería La Esquina</p>\n", $this->cuerpo('[saludo sitio="otro"]'));
        self::assertSame(
            ['contenido/index.md:5: [saludo] «sitio» es un nombre reservado y no se puede usar como atributo; se ignora'],
            $this->avisos(),
        );
    }

    #[Test]
    public function unErrorEnLaPlantillaDeUnAtajoLlevaSuFicheroYLinea(): void
    {
        $this->crearFichero('parciales/atajos/saludo.twig', "Hola\n{{ nombre|saludar }}");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('parciales/atajos/saludo.twig:2: No existe el filtro «saludar»');

        $this->cuerpo('[saludo nombre="Ana"]');
    }

    #[Test]
    public function avisaDeUnAtajoDelSitioConUnNombreQueNoVale(): void
    {
        $this->crearFichero('parciales/atajos/Mi_Atajo.twig', 'x');

        $this->cuerpo('Hola');

        self::assertSame(
            ['parciales/atajos/Mi_Atajo.twig: El nombre de un atajo solo lleva minúsculas sin tildes, números y guiones; este no se puede usar'],
            $this->avisos(),
        );
    }

    private function cuerpo(string $markdown): string
    {
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\nplantilla: false\n---\n{$markdown}");

        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $lectura = (new Lector())->leer($proyecto);
        $maquetador = new Maquetador($proyecto, $lectura, new Colecciones($lectura->paginas, new \DateTimeImmutable()), $this->avisos);

        return $maquetador->cuerpo($lectura->paginas[0]);
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
