<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\Encabezados;
use Ehundu\Constructor;
use Ehundu\Idiomas;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La sección `encabezados` de `sitio.yml` (formato §2): `id` en los encabezados
 * del Markdown, enlace permanente al final y `pagina.encabezados` en las
 * plantillas.
 */
final class EncabezadosPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    #[Test]
    public function sinLaSeccionNingunEncabezadoRecibeId(): void
    {
        $encabezados = Encabezados::leer(null, null, new Idiomas(), $this->avisos);

        self::assertFalse($encabezados->activo());
        self::assertSame([], $encabezados->niveles);
        self::assertFalse($encabezados->recibeId(2));
        self::assertNull($encabezados->enlace);
        self::assertSame('ancla', $encabezados->clase);
        self::assertNull($encabezados->etiquetaDe('es'));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unaSeccionVaciaDaIdDelDosAlSeis(): void
    {
        $encabezados = Encabezados::leer([], 3, new Idiomas(), $this->avisos);

        self::assertTrue($encabezados->activo());
        self::assertSame([2, 3, 4, 5, 6], $encabezados->niveles);
        self::assertFalse($encabezados->recibeId(1));
        self::assertTrue($encabezados->recibeId(6));
        self::assertNull($encabezados->enlace);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function seLeenTodosSusCampos(): void
    {
        $encabezados = Encabezados::leer(
            ['niveles' => [3, 2, 3], 'enlace' => ' ¶ ', 'clase' => 'ancla enlace', 'etiqueta' => 'Enlace a «{texto}»'],
            3,
            new Idiomas(),
            $this->avisos,
        );

        self::assertSame([2, 3], $encabezados->niveles);
        self::assertSame('¶', $encabezados->enlace);
        self::assertSame('ancla enlace', $encabezados->clase);
        self::assertSame('Enlace a «{texto}»', $encabezados->etiquetaDe('es'));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function nivelesVacioNoDaIdANingunEncabezadoYNoAvisa(): void
    {
        $encabezados = Encabezados::leer(['niveles' => []], 3, new Idiomas(), $this->avisos);

        self::assertFalse($encabezados->activo());
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function loQueNoValeSeAvisaYSeIgnoraSinDetenerNada(): void
    {
        $encabezados = Encabezados::leer(
            ['niveles' => [2, 7], 'enlace' => ['¶'], 'clase' => 'con comillas"', 'etiqueta' => ['uno'], 'otro' => 1],
            2,
            $this->dosIdiomas(),
            $this->avisos,
        );

        self::assertSame([2, 3, 4, 5, 6], $encabezados->niveles);
        self::assertNull($encabezados->enlace);
        self::assertSame('ancla', $encabezados->clase);
        self::assertSame([], $encabezados->etiquetas);
        self::assertSame([
            'sitio.yml:2: «encabezados» no admite «otro»; se ignora',
            'sitio.yml:2: «encabezados»: «niveles» tiene que ser una lista de números del 1 al 6; se usan del 2 al 6',
            'sitio.yml:2: «encabezados»: «enlace» tiene que ser un texto; los encabezados no llevan enlace permanente',
            'sitio.yml:2: «encabezados»: «clase» tiene que ser el nombre de una clase de CSS, o varios separados por espacios; se usa ancla',
            'sitio.yml:2: «encabezados»: «etiqueta» tiene que ser un texto, o un texto por idioma con su código como clave; se ignora',
        ], $this->avisos());
    }

    #[Test]
    public function unNivelQueNoEsUnNumeroSeAvisa(): void
    {
        foreach ([2, '2', 'dos', ['2'], 2.5] as $valor) {
            $avisos = new Avisos();
            $encabezados = Encabezados::leer(['niveles' => $valor], 4, new Idiomas(), $avisos);

            self::assertSame([2, 3, 4, 5, 6], $encabezados->niveles, var_export($valor, true));
            self::assertCount(1, $avisos->todos(), var_export($valor, true));
        }
    }

    #[Test]
    public function unaSeccionQueNoEsUnMapaSeIgnora(): void
    {
        $encabezados = Encabezados::leer(['a', 'b'], 7, new Idiomas(), $this->avisos);

        self::assertFalse($encabezados->activo());
        self::assertSame(
            ['sitio.yml:7: «encabezados» tiene que ser un mapa con «niveles», «enlace», «clase» y «etiqueta»; se ignora'],
            $this->avisos(),
        );
    }

    #[Test]
    public function laEtiquetaTieneUnTextoPorIdiomaComoElAvisoDeLosEnlacesExternos(): void
    {
        $encabezados = Encabezados::leer(
            ['enlace' => '¶', 'etiqueta' => ['es' => 'Enlace a «{texto}»', 'eu' => '«{texto}» estekara', 'fr' => 'Lien']],
            4,
            $this->dosIdiomas(),
            $this->avisos,
        );

        self::assertSame('Enlace a «{texto}»', $encabezados->etiquetaDe('es'));
        self::assertSame('«{texto}» estekara', $encabezados->etiquetaDe('eu'));
        self::assertNull($encabezados->etiquetaDe('fr'));
        self::assertSame(
            ['sitio.yml:4: «encabezados»: «etiqueta» tiene un texto para «fr», que no es un idioma del sitio; se ignora'],
            $this->avisos(),
        );
    }

    #[Test]
    public function unaEtiquetaSueltaConVariosIdiomasSeUsaEnTodosYSeAvisa(): void
    {
        $encabezados = Encabezados::leer(['etiqueta' => 'Enlace'], 4, $this->dosIdiomas(), $this->avisos);

        self::assertSame('Enlace', $encabezados->etiquetaDe('es'));
        self::assertSame('Enlace', $encabezados->etiquetaDe('eu'));
        self::assertSame(
            ['sitio.yml:4: «encabezados»: «etiqueta» es un solo texto y el sitio tiene varios idiomas (es, eu); se usa en todos. Conviene un texto por idioma: etiqueta: {es: …, eu: …}'],
            $this->avisos(),
        );
    }

    #[Test]
    public function unIdiomaSinEtiquetaOUnaEtiquetaQueNoEsTextoSeAvisa(): void
    {
        $encabezados = Encabezados::leer(['etiqueta' => ['es' => 3]], 4, $this->dosIdiomas(), $this->avisos);

        self::assertSame([], $encabezados->etiquetas);
        self::assertSame([
            'sitio.yml:4: «encabezados»: la «etiqueta» de «es» tiene que ser un texto; ese idioma no lleva etiqueta',
            'sitio.yml:4: «encabezados»: «etiqueta» no tiene texto en «eu»; los enlaces permanentes de ese idioma no llevan etiqueta',
        ], $this->avisos());
    }

    #[Test]
    public function seLeeDeSitioYmlYLosEncabezadosRecibenId(): void
    {
        $this->crearSitio("encabezados:\n  niveles: [2]\n", "## Un apartado\n\n### Un subapartado\n\n# Un título\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertSame(
            "<h2 id=\"un-apartado\">Un apartado</h2>\n<h3>Un subapartado</h3>\n<h1>Un título</h1>",
            trim($construccion->escritos['index.html']),
        );
    }

    #[Test]
    public function elEnlacePermanenteVaAlFinalDelEncabezado(): void
    {
        $this->crearSitio(
            "encabezados:\n  niveles: [2, 3]\n  enlace: ¶\n  clase: ancla\n  etiqueta: Enlace a «{texto}»\n",
            "## Día 4: la \"Gran\" ruta & más\n\n### Con **énfasis**\n",
        );

        self::assertSame(
            "<h2 id=\"dia-4-la-gran-ruta-y-mas\">Día 4: la &quot;Gran&quot; ruta &amp; más"
            . '<a class="ancla" href="#dia-4-la-gran-ruta-y-mas" aria-label="Enlace a «Día 4: la &quot;Gran&quot; ruta &amp; más»">¶</a></h2>' . "\n"
            . '<h3 id="con-enfasis">Con <strong>énfasis</strong>'
            . '<a class="ancla" href="#con-enfasis" aria-label="Enlace a «Con énfasis»">¶</a></h3>',
            trim($this->construir()->escritos['index.html']),
        );
    }

    #[Test]
    public function sinLaSeccionLosEncabezadosSalenComoSiempre(): void
    {
        $this->crearSitio('', "## Un apartado\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}[{{ pagina.encabezados|length }}]');

        self::assertSame("<h2>Un apartado</h2>\n[0]", $this->construir()->escritos['index.html']);
    }

    #[Test]
    public function laPlantillaLeeLosEncabezadosDeLaPaginaEnSuOrden(): void
    {
        $this->crearSitio("encabezados:\n  niveles: [2, 3]\n", "## Uno\n\ntexto\n\n### Dos & tres\n\n## Uno\n\n#### Cuatro\n");
        $this->crearFichero(
            'plantillas/pagina.twig',
            "{% for h in pagina.encabezados %}{{ h.nivel }}={{ h.id }}={{ h.texto }}\n{% endfor %}",
        );

        self::assertSame(
            "2=uno=Uno\n3=dos-y-tres=Dos &amp; tres\n2=uno-2=Uno\n",
            $this->construir()->escritos['index.html'],
        );
    }

    #[Test]
    public function lasPaginasDeUnaColeccionTienenSusEncabezadosYUnaPaginaTwigNinguno(): void
    {
        $this->crearSitio("encabezados:\n  niveles: [2]\n", "Portada.\n");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\n---\n## Primero\n\n## Segundo\n");
        $this->crearFichero('contenido/blog/dos.twig', "---\ntitulo: Dos\nfecha: 2024-02-01\n---\n<h2>A mano</h2>\n");
        $this->crearFichero(
            'contenido/todos.twig',
            "---\ntitulo: Todos\nurl: /todos.txt\nplantilla: false\n---\n{% for a in coleccion('blog') %}{{ a.titulo }}:{% for h in a.encabezados %}{{ h.id }},{% endfor %}|{% endfor %}",
        );

        self::assertSame('Uno:primero,segundo,|Dos:|', $this->construir()->escritos['todos.txt']);
    }

    #[Test]
    public function elEnlaceLlevaLaEtiquetaDeCadaIdioma(): void
    {
        $this->crearSitio(
            "idiomas:\n  - codigo: es\n  - codigo: eu\nencabezados:\n  enlace: ¶\n  etiqueta:\n    es: Enlace a «{texto}»\n    eu: «{texto}» estekara\n",
            "## Apartado\n",
        );
        $this->crearFichero('contenido/index.eu.md', "---\ntitulo: Hasiera\n---\n## Atala\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertStringContainsString('aria-label="Enlace a «Apartado»"', $construccion->escritos['index.html']);
        self::assertStringContainsString('aria-label="«Atala» estekara"', $construccion->escritos['eu/index.html']);
    }

    #[Test]
    public function losEncabezadosDeUnAtajoQueEnvuelveNoCuentan(): void
    {
        $this->crearSitio("encabezados:\n  niveles: [2]\n", "## Fuera\n\n[caja]\n\n## Dentro\n\n[/caja]\n\n## Otra vez fuera\n");
        $this->crearFichero('parciales/atajos/caja.twig', '<div>{{ contenido }}</div>');
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}{% for h in pagina.encabezados %}[{{ h.id }}]{% endfor %}');

        self::assertSame(
            "<h2 id=\"fuera\">Fuera</h2>\n<div><h2>Dentro</h2></div>\n<h2 id=\"otra-vez-fuera\">Otra vez fuera</h2>\n[fuera][otra-vez-fuera]",
            $this->construir()->escritos['index.html'],
        );
    }

    #[Test]
    public function cambiarLaSeccionRehaceTodasLasPaginasEnUnaConstruccionIncremental(): void
    {
        $this->crearSitio('', "## Un apartado\n");
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $primera = $constructor->construir($proyecto, $ahora);
        self::assertStringNotContainsString('id=', $primera->escritos['index.html']);

        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nencabezados:\n  enlace: ¶\n");
        $segunda = $constructor->construir($proyecto, $ahora);

        self::assertStringContainsString('<h2 id="un-apartado">Un apartado<a class="ancla" href="#un-apartado">¶</a></h2>', $segunda->escritos['index.html']);
        self::assertSame($this->construir()->escritos, $segunda->escritos);
    }

    #[Test]
    public function losEncabezadosDeOtraPaginaRehacenLaQueLosMuestraSoloSiCambian(): void
    {
        $this->crearSitio("encabezados:\n  niveles: [2]\n", "Portada.\n");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\n---\n## Primero\n\nTexto.\n");
        $this->crearFichero(
            'contenido/indice.twig',
            "---\ntitulo: Índice\n---\n{% for a in coleccion('blog') %}{% for h in a.encabezados %}{{ h.id }},{% endfor %}{% endfor %}\n",
        );

        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $primera = $constructor->construir($proyecto, $ahora);
        self::assertStringContainsString('primero,', $primera->escritos['indice/index.html']);

        // Cambia el cuerpo pero no los encabezados: depende del cuerpo, así que se rehacen las dos.
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\n---\n## Primero\n\nOtro texto.\n");
        $texto = $constructor->construir($proyecto, $ahora);
        self::assertSame($this->construir()->escritos, $texto->escritos);

        // Cambia un encabezado: la página que lo muestra lo recoge.
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\n---\n## Primero\n\n## Nuevo\n");
        $nuevo = $constructor->construir($proyecto, $ahora);
        self::assertStringContainsString('primero,nuevo,', $nuevo->escritos['indice/index.html']);
        self::assertSame($this->construir()->escritos, $nuevo->escritos);
    }

    private function dosIdiomas(): Idiomas
    {
        return new Idiomas([['codigo' => 'es', 'nombre' => 'Castellano'], ['codigo' => 'eu', 'nombre' => 'Euskara']], declarados: true);
    }

    /**
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_map(strval(...), $this->avisos->todos());
    }

    private function crearSitio(string $extra, string $cuerpo): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n{$extra}");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', "---\ntitulo: Portada\n---\n{$cuerpo}");
    }

    private function construir(): \Ehundu\Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeImmutable(self::AHORA));
    }
}
