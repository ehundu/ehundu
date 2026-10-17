<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Markdown;

use Ehundu\Avisos;
use Ehundu\Encabezados;
use Ehundu\EnlacesExternos;
use Ehundu\Markdown\Conversor;
use Ehundu\Markdown\Convertido;
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
    public function laSangriaHaceUnBloqueDeCodigoComoEnCommonMark(): void
    {
        self::assertSame(
            "<p>Texto.</p>\n<pre><code>sangrado\n</code></pre>\n<pre><code class=\"language-php\">cercado\n</code></pre>\n",
            $this->convertir("Texto.\n\n    sangrado\n\n```php\ncercado\n```\n"),
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
    public function elDominioDelSitioNoDistingueMayusculas(): void
    {
        $html = $this->convertir('[propio](https://EJEMPLO.com/a) [con www](https://WWW.Ejemplo.COM/b) [fuera](https://OTRO.com/c)');

        self::assertStringContainsString('<a href="https://EJEMPLO.com/a">propio</a>', $html);
        self::assertStringContainsString('<a href="https://WWW.Ejemplo.COM/b">con www</a>', $html);
        self::assertStringContainsString('<a rel="noopener" target="_blank" href="https://OTRO.com/c">fuera</a>', $html);
        self::assertTrue(\Ehundu\EnlacesExternos::esExterno('https://OTRO.com/c', 'https://www.ejemplo.com'));
        self::assertFalse(\Ehundu\EnlacesExternos::esExterno('//Ejemplo.com/c', 'https://www.ejemplo.com'));
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
    public function elAvisoSeAnadeSoloALosEnlacesExternosDelMarkdown(): void
    {
        $enlaces = new EnlacesExternos(['es' => '(abre en ventana nueva)']);
        $html = $this->convertirConEnlaces(
            $enlaces,
            "[fuera](https://otro.com/a) [propio](https://www.ejemplo.com/b) [relativo](/d/) https://otro.com/suelta\n\n"
            . '<a href="https://otro.com/html">escrito a mano</a>',
        );

        $aviso = '<span class="visually-hidden"> (abre en ventana nueva)</span>';

        self::assertStringContainsString("<a rel=\"noopener\" target=\"_blank\" href=\"https://otro.com/a\">fuera{$aviso}</a>", $html);
        self::assertStringContainsString("<a rel=\"noopener\" target=\"_blank\" href=\"https://otro.com/suelta\">https://otro.com/suelta{$aviso}</a>", $html);
        self::assertStringContainsString('<a href="https://www.ejemplo.com/b">propio</a>', $html);
        self::assertStringContainsString('<a href="/d/">relativo</a>', $html);
        self::assertStringContainsString('<a href="https://otro.com/html">escrito a mano</a>', $html);
        self::assertSame(2, substr_count($html, $aviso));
    }

    #[Test]
    public function elAvisoLlevaLaClaveDelSitioYSuTextoEscapado(): void
    {
        $enlaces = new EnlacesExternos(['es' => '(abre <otra> pestaña & "ventana")'], clase: 'sr-only');
        $html = $this->convertirConEnlaces($enlaces, '[fuera](https://otro.com/a)');

        self::assertStringContainsString('fuera<span class="sr-only"> (abre &lt;otra&gt; pestaña &amp; "ventana")</span></a>', $html);
    }

    #[Test]
    public function elAvisoEsElDelIdiomaDeLaPaginaYSinTextoEnEseIdiomaNoHayAviso(): void
    {
        $enlaces = new EnlacesExternos(['es' => '(nueva ventana)', 'eu' => '(leiho berria)']);

        self::assertStringContainsString('<span class="visually-hidden"> (leiho berria)</span>', $this->convertirConEnlaces($enlaces, '[a](https://otro.com)', 'eu'));
        self::assertStringNotContainsString('visually-hidden', $this->convertirConEnlaces($enlaces, '[a](https://otro.com)', 'en'));
    }

    #[Test]
    public function noreferrerSeAnadeAlRelSoloSiElSitioLoPide(): void
    {
        self::assertStringContainsString(
            '<a rel="noopener noreferrer" target="_blank" href="https://otro.com/a">fuera</a>',
            $this->convertirConEnlaces(new EnlacesExternos(noreferrer: true), '[fuera](https://otro.com/a)'),
        );
        self::assertStringContainsString(
            '<a rel="noopener" target="_blank" href="https://otro.com/a">fuera</a>',
            $this->convertirConEnlaces(new EnlacesExternos(), '[fuera](https://otro.com/a)'),
        );
    }

    #[Test]
    public function unaFiguraEnlazadaAUnSitioExternoSigueSiendoUnaFiguraSinAviso(): void
    {
        $html = $this->convertirConEnlaces(new EnlacesExternos(['es' => '(abre en ventana nueva)']), '[![Plano](/img/plano.png)](https://otro.com/plano)');

        self::assertSame("[figura]\n", $html);
        self::assertSame([['/img/plano.png', 'Plano', 'Plano', 'https://otro.com/plano', 1]], $this->figuras);
    }

    #[Test]
    public function unEnlaceExternoConUnaImagenYTextoSiLlevaAviso(): void
    {
        $html = $this->convertirConEnlaces(new EnlacesExternos(['es' => '(nueva ventana)']), 'Mira [![i](/i.svg) la web](https://otro.com)');

        self::assertStringContainsString('la web<span class="visually-hidden"> (nueva ventana)</span></a>', $html);
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
    public function avisaDeUnAtajoRegistradoConLosAtributosMalEscritosYLoDejaComoTexto(): void
    {
        $html = $this->convertir("Foto [imagen fichero=a.png alt=x] y [imagen fichero=\"b.png\" alt=x] y [nota = importante] y [archivo](/a.pdf).");

        self::assertSame('<p>Foto [imagen fichero=a.png alt=x] y [imagen fichero=&quot;b.png&quot; alt=x] y [nota = importante] y <a href="/a.pdf">archivo</a>.</p>' . "
", $html);
        // Los dos dicen lo mismo en la misma línea: un solo aviso
        self::assertSame([
            'contenido/a.md:5: El atajo «imagen» está mal escrito: cada atributo se escribe nombre="valor", con comillas dobles; se deja como texto',
        ], $this->avisos());
        self::assertSame([], $this->atajos);
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

    #[Test]
    public function sinLaSeccionLosEncabezadosNoRecibenIdNiSeListan(): void
    {
        $convertido = $this->convertirConEncabezados(new Encabezados(), "## Uno\n\n### Dos\n");

        self::assertSame("<h2>Uno</h2>\n<h3>Dos</h3>\n", $convertido->html);
        self::assertSame([], $convertido->encabezados);
    }

    #[Test]
    public function losEncabezadosDeLosNivelesPedidosRecibenIdYSeListanEnOrden(): void
    {
        $convertido = $this->convertirConEncabezados(
            new Encabezados([2, 4]),
            "# Título\n\n## Dos\n\n### Tres\n\n#### Cuatro\n\nSubtítulo\n---------\n",
        );

        self::assertSame(
            "<h1>Título</h1>\n<h2 id=\"dos\">Dos</h2>\n<h3>Tres</h3>\n<h4 id=\"cuatro\">Cuatro</h4>\n<h2 id=\"subtitulo\">Subtítulo</h2>\n",
            $convertido->html,
        );
        self::assertSame(
            [
                ['nivel' => 2, 'texto' => 'Dos', 'id' => 'dos'],
                ['nivel' => 4, 'texto' => 'Cuatro', 'id' => 'cuatro'],
                ['nivel' => 2, 'texto' => 'Subtítulo', 'id' => 'subtitulo'],
            ],
            $convertido->encabezados,
        );
    }

    #[Test]
    public function elIdSaleDelTextoPlanoYNoSeRepiteEnLaPagina(): void
    {
        $convertido = $this->convertirConEncabezados(
            new Encabezados([2]),
            "## Uno\n\n## Uno\n\n## Uno-2\n\n## **Uno**\n\n## ¡!\n\n## ?\n\n## [Enlace](/a/) con `código` y ![foto](/f.jpg) salto <b>HTML</b>\n",
        );

        self::assertSame(
            ['uno', 'uno-2', 'uno-2-2', 'uno-3', 'seccion', 'seccion-2', 'enlace-con-codigo-y-salto-html'],
            array_column($convertido->encabezados, 'id'),
        );
        self::assertSame('Enlace con código y salto HTML', $convertido->encabezados[6]['texto']);
    }

    #[Test]
    public function elEnlacePermanenteSeEscapaYNoEntraEnElTexto(): void
    {
        $convertido = $this->convertirConEncabezados(
            new Encabezados([2], '<#>', 'ancla a', ['es' => 'Ir a "{texto}"']),
            "## Tom & Jerry\n",
        );

        self::assertSame(
            "<h2 id=\"tom-y-jerry\">Tom &amp; Jerry<a class=\"ancla a\" href=\"#tom-y-jerry\" aria-label=\"Ir a &quot;Tom &amp; Jerry&quot;\">&lt;#&gt;</a></h2>\n",
            $convertido->html,
        );
        self::assertSame('Tom & Jerry', $convertido->encabezados[0]['texto']);
    }

    #[Test]
    public function sinEtiquetaEnSuIdiomaElEnlaceNoLlevaAriaLabel(): void
    {
        $convertido = $this->convertirConEncabezados(new Encabezados([2], '¶', etiquetas: ['eu' => '{texto}']), "## Uno\n");

        self::assertSame("<h2 id=\"uno\">Uno<a class=\"ancla\" href=\"#uno\">¶</a></h2>\n", $convertido->html);
    }

    #[Test]
    public function noCuentanLosEncabezadosDeUnAtajoQueEnvuelveNiLosDelHtmlEscritoAMano(): void
    {
        $convertido = $this->convertirConEncabezados(
            new Encabezados([2]),
            "## Antes\n\n[aviso tipo=\"importante\"]\n## Dentro\n[/aviso]\n\n<h2>A mano</h2>\n\n## Después\n",
        );

        self::assertSame(['antes', 'despues'], array_column($convertido->encabezados, 'id'));
        self::assertStringContainsString('<h2>A mano</h2>', $convertido->html);
    }

    #[Test]
    public function elAvisoDeLosEnlacesExternosNoEntraEnElTextoDelEncabezado(): void
    {
        $conversor = new Conversor('https://www.ejemplo.com', new EnlacesExternos(['es' => '(abre en ventana nueva)']), new Encabezados([2]));
        $convertido = $conversor->convertirConEncabezados("## Mira [fuera](https://otro.com/a)\n", $this->resolutor(), $this->avisos, 'contenido/a.md', 5, 'es');

        self::assertSame('Mira fuera', $convertido->encabezados[0]['texto']);
        self::assertSame('mira-fuera', $convertido->encabezados[0]['id']);
        self::assertStringContainsString('(abre en ventana nueva)', $convertido->html);
    }

    #[Test]
    public function enLineaNoPoneParrafoNiSaltoFinal(): void
    {
        self::assertSame('Una cocina que <em>viaja</em>', $this->enLinea('Una cocina que *viaja*'));
        self::assertSame('Una <strong>cocina</strong>', $this->enLinea("Una **cocina**\n"));
        self::assertSame('', $this->enLinea(''));
    }

    #[Test]
    public function enLineaUnSaltoConBarraODosEspaciosEsUnBr(): void
    {
        // Como en el cuerpo, el `<br>` conserva el salto de línea del texto: en HTML no se ve.
        self::assertSame(
            "Una cocina<br>\nque <em>viaja</em><br>\ncontigo",
            $this->enLinea("Una cocina\\\nque *viaja*\\\ncontigo"),
        );
        self::assertSame("Una cocina<br>\ncontigo", $this->enLinea("Una cocina  \ncontigo"));
        self::assertSame("Una cocina\ncontigo", $this->enLinea("Una cocina\ncontigo"));
    }

    #[Test]
    public function enLineaAdmiteNegritaTachadoCodigoEnlacesAutomaticosYHtmlAMano(): void
    {
        self::assertSame(
            '<strong>a</strong> <del>b</del> <code>c</code> <mark>d</mark> <a href="http://www.ejemplo.com">www.ejemplo.com</a>',
            $this->enLinea('**a** ~~b~~ `c` <mark>d</mark> www.ejemplo.com'),
        );
        self::assertSame('Tom &amp; Jerry', $this->enLinea('Tom & Jerry'));
    }

    #[Test]
    public function enLineaNoTieneBloquesNiAtajos(): void
    {
        self::assertSame('# No es un encabezado', $this->enLinea('# No es un encabezado'));
        self::assertSame('- uno - dos', str_replace("\n", ' ', $this->enLinea("- uno\n- dos")));
        self::assertSame('[imagen fichero=&quot;a.jpg&quot;]', $this->enLinea('[imagen fichero="a.jpg"]'));
        self::assertSame([], $this->avisos->todos());
    }

    #[Test]
    public function enLineaLosEnlacesExternosVanComoEnElCuerpo(): void
    {
        $enlaces = new EnlacesExternos(['es' => '(abre en ventana nueva)']);

        self::assertSame(
            '<a href="/contacto/">dentro</a> y <a rel="noopener" target="_blank" href="https://otro.com/a">fuera<span class="visually-hidden"> (abre en ventana nueva)</span></a>',
            $this->enLinea('[dentro](/contacto/) y [fuera](https://otro.com/a)', $enlaces, 'es'),
        );
        self::assertSame(
            '<a rel="noopener" target="_blank" href="https://otro.com/a">fuera</a>',
            $this->enLinea('[fuera](https://otro.com/a)'),
        );
    }

    #[Test]
    public function enLineaUnaImagenSaleSinBarraFinalYUnEnlaceInseguroSinDestino(): void
    {
        self::assertSame('<img src="/a.jpg" alt="Una foto">', $this->enLinea('![Una foto](/a.jpg)'));
        self::assertStringNotContainsString('javascript:', $this->enLinea('[pulsa](javascript:alert(1))'));
    }

    #[Test]
    public function enBloqueSonLosBloquesDelCuerpoSinNadaDeLoQueNecesitaUnaPagina(): void
    {
        self::assertSame(
            "<p>Uno con <em>énfasis</em> y <mark>marca</mark>.</p>\n<p>Dos.</p>",
            $this->enBloque("Uno con *énfasis* y <mark>marca</mark>.\n\nDos.\n"),
        );
        self::assertSame("<ul>\n<li>a</li>\n<li>b</li>\n</ul>", $this->enBloque("- a\n- b\n"));
        self::assertSame('<h2>Título</h2>', $this->enBloque('## Título'));
        self::assertSame("<blockquote>\n<p>Cita</p>\n</blockquote>", $this->enBloque('> Cita'));
        self::assertSame('', $this->enBloque(''));
    }

    #[Test]
    public function enBloqueNoHayAtajosFigurasTablasNiNotasAlPie(): void
    {
        self::assertSame('<p>[imagen fichero=&quot;a.jpg&quot;]</p>', $this->enBloque('[imagen fichero="a.jpg"]'));
        self::assertSame('<p><img src="/a.jpg" alt="Una foto"></p>', $this->enBloque('![Una foto](/a.jpg)'));
        self::assertStringNotContainsString('<table>', $this->enBloque("| a | b |\n|---|---|\n| 1 | 2 |"));
        self::assertStringNotContainsString('footnote', $this->enBloque("Con nota[^1].\n\n[^1]: La nota."));
        self::assertSame([], $this->avisos->todos());
    }

    #[Test]
    public function enBloqueLosEnlacesExternosYElSaltoVanComoEnElCuerpo(): void
    {
        $enlaces = new EnlacesExternos(['es' => '(abre en ventana nueva)']);

        self::assertSame(
            '<p><a rel="noopener" target="_blank" href="https://otro.com/a">fuera<span class="visually-hidden"> (abre en ventana nueva)</span></a>'
            . "<br>\nsigue</p>",
            $this->enBloque("[fuera](https://otro.com/a)\\\nsigue", $enlaces, 'es'),
        );
    }

    private function enBloque(string $markdown, ?EnlacesExternos $enlaces = null, string $idioma = ''): string
    {
        return (new Conversor('https://www.ejemplo.com', $enlaces ?? new EnlacesExternos()))->convertirEnBloque($markdown, $idioma);
    }

    private function enLinea(string $markdown, ?EnlacesExternos $enlaces = null, string $idioma = ''): string
    {
        return (new Conversor('https://www.ejemplo.com', $enlaces ?? new EnlacesExternos()))->convertirEnLinea($markdown, $idioma);
    }

    private function convertirConEncabezados(Encabezados $encabezados, string $markdown, string $idioma = 'es'): Convertido
    {
        return (new Conversor('https://www.ejemplo.com', new EnlacesExternos(), $encabezados))
            ->convertirConEncabezados($markdown, $this->resolutor(), $this->avisos, 'contenido/a.md', 5, $idioma);
    }

    private function convertir(string $markdown): string
    {
        return (new Conversor('https://www.ejemplo.com'))->convertir($markdown, $this->resolutor(), $this->avisos, 'contenido/a.md', 5);
    }

    private function convertirConEnlaces(EnlacesExternos $enlaces, string $markdown, string $idioma = 'es'): string
    {
        return (new Conversor('https://www.ejemplo.com', $enlaces))->convertir($markdown, $this->resolutor(), $this->avisos, 'contenido/a.md', 5, $idioma);
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
