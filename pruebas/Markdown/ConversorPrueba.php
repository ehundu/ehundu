<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Markdown;

use Ehundu\Avisos;
use Ehundu\Markdown\Conversor;
use Ehundu\Markdown\ResolutorDeAtajos;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConversorPrueba extends TestCase
{
    private Avisos $avisos;

    /** @var list<array{string, array<string, string>, ?string, ?int}> */
    private array $atajos = [];

    /** @var list<array{string, string, string, ?string, ?int}> */
    private array $figuras = [];

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    #[Test]
    public function convierteCommonMark(): void
    {
        self::assertSame(
            "<h2>Título</h2>\n<p>Un <strong>texto</strong> con <a href=\"/contacto/\">enlace</a>.</p>\n",
            $this->convertir("## Título\n\nUn **texto** con [enlace](/contacto/)."),
        );
    }

    #[Test]
    public function admiteTablasNotasAlPieTachadoYEnlacesAutomaticos(): void
    {
        $html = $this->convertir("| a | b |\n|---|---|\n| 1 | 2 |\n\n~~Antes~~ Ver www.ejemplo.com y la nota[^1].\n\n[^1]: La nota.\n");

        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<del>Antes</del>', $html);
        self::assertStringContainsString('<a href="http://www.ejemplo.com">www.ejemplo.com</a>', $html);
        self::assertStringContainsString('class="footnotes"', $html);
        self::assertStringContainsString('<hr>', $html);
    }

    #[Test]
    public function escribeLasEtiquetasVaciasComoEnHtml5(): void
    {
        self::assertSame(
            "<p>Un icono <img src=\"/i.svg\" alt=\"i\"> y un salto<br>\naquí.</p>\n<hr>\n",
            $this->convertir("Un icono ![i](/i.svg) y un salto  \naquí.\n\n---"),
        );
    }

    #[Test]
    public function losEnlacesAOtrosDominiosSeAbrenEnOtraPestana(): void
    {
        $html = $this->convertir(
            "[fuera](https://otro.com/a) [propio](https://ejemplo.com/b) [con www](https://www.ejemplo.com/c) [relativo](/d/)\n\n"
            . '<a href="https://otro.com/html">escrito a mano</a>',
        );

        self::assertStringContainsString('<a rel="noopener" target="_blank" href="https://otro.com/a">fuera</a>', $html);
        self::assertStringContainsString('<a href="https://ejemplo.com/b">propio</a>', $html);
        self::assertStringContainsString('<a href="https://www.ejemplo.com/c">con www</a>', $html);
        self::assertStringContainsString('<a href="/d/">relativo</a>', $html);
        self::assertStringContainsString('<a href="https://otro.com/html">escrito a mano</a>', $html);
    }

    #[Test]
    public function unaImagenSolaEnSuParrafoEsUnaFigura(): void
    {
        $html = $this->convertir("Antes.\n\n![Un **escaparate** en otoño](/img/escaparate.jpg)\n\nDespués.");

        self::assertSame("<p>Antes.</p>\n[figura]\n<p>Después.</p>\n", $html);
        self::assertSame(
            [['/img/escaparate.jpg', 'Un escaparate en otoño', 'Un <strong>escaparate</strong> en otoño', null, 3]],
            $this->figuras,
        );
    }

    #[Test]
    public function unaImagenEnlazadaSolaTambienEsUnaFigura(): void
    {
        $this->convertir('[![Plano](/img/plano.png)](/img/plano-grande.png)');

        self::assertSame([['/img/plano.png', 'Plano', 'Plano', '/img/plano-grande.png', 1]], $this->figuras);
    }

    #[Test]
    public function unaImagenDentroDelTextoNoEsUnaFigura(): void
    {
        $this->convertir('Mira ![esto](/a.jpg) aquí.');

        self::assertSame([], $this->figuras);
    }

    #[Test]
    public function reconoceUnAtajoEnMedioDelTexto(): void
    {
        self::assertSame(
            "<p>Llámanos al (dato: clave=cliente.telefono) hoy.</p>\n",
            $this->convertir('Llámanos al [dato clave="cliente.telefono"] hoy.'),
        );
        self::assertSame([['dato', ['clave' => 'cliente.telefono'], null, 1]], $this->atajos);
    }

    #[Test]
    public function aceptaComillasSimplesYDobles(): void
    {
        $this->convertir("[archivo fichero='docs/tarifas.pdf' texto=\"Tarifas 2025\"]");

        self::assertSame(['fichero' => 'docs/tarifas.pdf', 'texto' => 'Tarifas 2025'], $this->atajos[0][1]);
    }

    #[Test]
    public function unAtajoDeBloqueSoloEnSuParrafoSustituyeAlParrafo(): void
    {
        self::assertSame(
            "<p>Antes.</p>\n<div class=\"video\">(video)</div>\n",
            $this->convertir("Antes.\n\n[video url=\"https://youtu.be/abcdefghijk\"]"),
        );
    }

    #[Test]
    public function unAtajoEnLineaSoloEnSuParrafoSigueDentroDelParrafo(): void
    {
        self::assertSame("<p>(dato: clave=cliente.nombre)</p>\n", $this->convertir('[dato clave="cliente.nombre"]'));
    }

    #[Test]
    public function unAtajoQueEnvuelveRecibeSuContenidoConvertido(): void
    {
        $html = $this->convertir("Antes.\n\n[aviso tipo=\"importante\"]\nLas plazas son **limitadas**.\n\n- Una\n- Dos\n[/aviso]\n\nDespués.");

        self::assertSame("<p>Antes.</p>\n<div class=\"aviso\">(aviso)</div>\n<p>Después.</p>\n", $html);
        self::assertSame('aviso', $this->atajos[0][0]);
        self::assertSame(['tipo' => 'importante'], $this->atajos[0][1]);
        self::assertSame("<p>Las plazas son <strong>limitadas</strong>.</p>\n<ul>\n<li>Una</li>\n<li>Dos</li>\n</ul>", $this->atajos[0][2]);
        self::assertSame(3, $this->atajos[0][3]);
    }

    #[Test]
    public function losAtajosDentroDeCodigoOEscapadosSonTexto(): void
    {
        $html = $this->convertir("`[dato clave=\"a\"]`\n\n```\n[video url=\"x\"]\n```\n\n\\[dato clave=\"b\"]");

        self::assertSame([], $this->atajos);
        self::assertStringContainsString('<code>[dato clave=&quot;a&quot;]</code>', $html);
        self::assertStringContainsString('<p>[dato clave=&quot;b&quot;]</p>', $html);
    }

    #[Test]
    public function unEnlaceConElNombreDeUnAtajoSigueSiendoUnEnlace(): void
    {
        self::assertSame("<p><a href=\"/foto.jpg\">imagen</a></p>\n", $this->convertir('[imagen](/foto.jpg)'));
        self::assertSame([], $this->atajos);
    }

    #[Test]
    public function avisaDeUnAtajoDesconocidoConAtributosYLoDejaComoTexto(): void
    {
        $html = $this->convertir("Primera línea.\n\nHola [galeria carpeta=\"fotos\"] y [nota].");

        self::assertSame("<p>Primera línea.</p>\n<p>Hola [galeria carpeta=&quot;fotos&quot;] y [nota].</p>\n", $html);
        self::assertSame(['contenido/a.md:7: No existe el atajo «galeria»; se deja como texto'], $this->avisos());
    }

    #[Test]
    public function avisaDeUnCierreSinApertura(): void
    {
        $this->convertir('Texto [/aviso] suelto.');

        self::assertSame(['contenido/a.md:5: «[/aviso]» no cierra ningún atajo; se deja como texto'], $this->avisos());
    }

    #[Test]
    public function avisaSiFaltaElCierre(): void
    {
        $this->convertir("[/aviso]\n\n[aviso]\nSin cerrar.");

        self::assertSame([
            'contenido/a.md:7: Falta la línea [/aviso] que cierra el atajo; se cierra al final del texto',
            'contenido/a.md:5: «[/aviso]» no cierra ningún atajo; se deja como texto',
        ], $this->avisos());
    }

    #[Test]
    public function noInterpretaTwig(): void
    {
        self::assertSame("<p>{{ datos.cliente.nombre }}</p>\n", $this->convertir('{{ datos.cliente.nombre }}'));
    }

    private function convertir(string $markdown): string
    {
        return (new Conversor('https://www.ejemplo.com'))->convertir($markdown, $this->resolutor(), $this->avisos, 'contenido/a.md', 5);
    }

    private function resolutor(): ResolutorDeAtajos
    {
        return new class($this) implements ResolutorDeAtajos {
            public function __construct(
                private readonly ConversorPrueba $prueba,
            ) {
            }

            public function nombres(): array
            {
                return ['imagen', 'video', 'archivo', 'dato', 'aviso'];
            }

            public function atajo(string $nombre, array $atributos, ?string $contenido, ?int $linea): string
            {
                $this->prueba->anotarAtajo($nombre, $atributos, $contenido, $linea === null ? null : $linea - 4);

                return match ($nombre) {
                    'video', 'aviso' => "<div class=\"{$nombre}\">({$nombre})</div>\n",
                    default => "({$nombre}: " . http_build_query($atributos, arg_separator: ' ') . ')',
                };
            }

            public function figura(string $src, string $alt, string $pie, ?string $enlace, ?int $linea): string
            {
                $this->prueba->anotarFigura($src, $alt, $pie, $enlace, $linea === null ? null : $linea - 4);

                return "[figura]\n";
            }
        };
    }

    /**
     * @param array<string, string> $atributos
     */
    public function anotarAtajo(string $nombre, array $atributos, ?string $contenido, ?int $linea): void
    {
        $this->atajos[] = [$nombre, $atributos, $contenido, $linea];
    }

    public function anotarFigura(string $src, string $alt, string $pie, ?string $enlace, ?int $linea): void
    {
        $this->figuras[] = [$src, $alt, $pie, $enlace, $linea];
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }
}
