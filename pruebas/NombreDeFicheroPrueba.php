<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Constructor;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `{{ nombreDeFichero }}` en el patrón de `url` (formato §5.1): publicar las
 * páginas de una carpeta sin la carpeta en la URL.
 */
final class NombreDeFicheroPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    #[Test]
    public function unDatosDeLaCarpetaPublicaSusPaginasSinLaCarpeta(): void
    {
        $this->crearSitio();

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertSame('<p>Uno.</p>', trim($construccion->escritos['uno/index.html']));
        self::assertSame('<p>Dos.</p>', trim($construccion->escritos['dos/index.html']));
        self::assertArrayNotHasKey('posts/uno/index.html', $construccion->escritos);
        self::assertStringContainsString('<loc>https://ejemplo.com/uno/</loc>', $construccion->escritos['sitemap.xml']);
    }

    #[Test]
    public function unaPaginaConSuPropioUrlGanaAlPatronDeLaCarpeta(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/posts/index.md', "---\ntitulo: Entradas\nurl: /entradas/\n---\nÍndice.\n");
        $this->crearFichero('contenido/posts/el-angel-ciego.md', "---\ntitulo: Origen\nurl: /el-origen/\n---\nOrigen.\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertArrayHasKey('entradas/index.html', $construccion->escritos);
        self::assertArrayHasKey('el-origen/index.html', $construccion->escritos);
        self::assertArrayNotHasKey('el-angel-ciego/index.html', $construccion->escritos);
    }

    #[Test]
    public function cadaIdiomaUsaElNombreSinSuCodigoYElMotorPoneElPrefijo(): void
    {
        $this->crearSitio();
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n  - codigo: eu\n");
        $this->crearFichero('plantillas/pagina.twig', '{% for codigo, t in pagina.traducciones %}{{ codigo }}={{ t.url }};{% endfor %}');
        $this->crearFichero('contenido/posts/uno.eu.md', "---\ntitulo: Bat\n---\nBat.\n");
        $this->crearFichero('contenido/index.md', "---\ntitulo: Portada\n---\nInicio.\n");
        $this->crearFichero('contenido/index.eu.md', "---\ntitulo: Hasiera\n---\nHasiera.\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertSame('es=/uno/;eu=/eu/uno/;', $construccion->escritos['uno/index.html']);
        self::assertSame('es=/uno/;eu=/eu/uno/;', $construccion->escritos['eu/uno/index.html']);
    }

    #[Test]
    public function dosFicherosConElMismoNombreEnCarpetasDistintasDetienenElBuildNombrandolos(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/posts/_datos.yml', "url: \"/{{ nombreDeFichero }}/\"\n");
        $this->crearFichero('contenido/otras/_datos.yml', "url: \"/{{ nombreDeFichero }}/\"\n");
        $this->crearFichero('contenido/otras/uno.md', "---\ntitulo: Otro uno\n---\nOtro.\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/otras/uno.md (/uno/) y contenido/posts/uno.md (/uno/)');

        $this->construir();
    }

    #[Test]
    public function unNombreConEspaciosSeUsaTalCualYConSlugSaleLimpio(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/posts/Quiénes somos.md', "---\ntitulo: Quiénes\n---\nHola.\n");

        $construccion = $this->construir();

        self::assertContains('Quiénes somos/index.html', array_keys($construccion->escritos));

        // Con `|slug` sale limpio y sin avisos.
        $this->crearFichero('contenido/posts/_datos.yml', "url: \"/{{ nombreDeFichero|slug }}/\"\n");
        $construccion = $this->construir();

        self::assertContains('quienes-somos/index.html', array_keys($construccion->escritos));
    }

    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/posts/_datos.yml', "url: \"/{{ nombreDeFichero }}/\"\n");
        $this->crearFichero('contenido/posts/uno.md', "---\ntitulo: Uno\n---\nUno.\n");
        $this->crearFichero('contenido/posts/dos.md', "---\ntitulo: Dos\n---\nDos.\n");
    }

    private function construir(): \Ehundu\Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeImmutable(self::AHORA));
    }
}
