<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\Plantillas\Generador;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La etiqueta `generator` que marca las páginas como hechas con Ehundu
 * (formato §2, decisión 127).
 */
final class GeneradorPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';
    private const string ETIQUETA = '<meta name="generator" content="Ehundu">';

    /**
     * @return iterable<string, array{string, string}> el HTML de la plantilla y cómo queda
     */
    public static function paginas(): iterable
    {
        $e = self::ETIQUETA;

        yield 'con </head> al principio de su línea, la etiqueta lleva dos espacios' => [
            "<head>\n  <title>Hola</title>\n</head>\n<body></body>",
            "<head>\n  <title>Hola</title>\n  {$e}\n</head>\n<body></body>",
        ];
        yield 'con </head> sangrado, la etiqueta lleva su sangría más dos espacios' => [
            "<html>\n  <head>\n    <title>Hola</title>\n  </head>\n</html>",
            "<html>\n  <head>\n    <title>Hola</title>\n    {$e}\n  </head>\n</html>",
        ];
        yield 'con </head> pegado a otra cosa, la etiqueta va pegada a él' => [
            '<head><title>Hola</title></head><body></body>',
            "<head><title>Hola</title>{$e}</head><body></body>",
        ];
        yield 'con </head> como única línea' => [
            '</head>',
            "  {$e}\n</head>",
        ];
        yield 'con </head> en la segunda línea y nada antes' => [
            "<head>\n</head>",
            "<head>\n  {$e}\n</head>",
        ];
        yield 'con finales de línea de Windows' => [
            "<head>\r\n<title>Hola</title>\r\n</head>\r\n",
            "<head>\r\n<title>Hola</title>\r\n  {$e}\r\n</head>\r\n",
        ];
        yield 'sin distinguir mayúsculas' => [
            "<HEAD>\n</HEAD >\n",
            "<HEAD>\n  {$e}\n</HEAD >\n",
        ];
        yield 'solo en el primer </head>' => [
            "<head>\n</head>\n<p>&lt;/head&gt; <code></head></code></p>",
            "<head>\n  {$e}\n</head>\n<p>&lt;/head&gt; <code></head></code></p>",
        ];
        yield 'sin </head> no cambia' => ['<p>Un fragmento</p>', '<p>Un fragmento</p>'];
        yield 'un texto tampoco' => ["User-agent: *\nDisallow:\n", "User-agent: *\nDisallow:\n"];
        yield 'ni lo vacío' => ['', ''];
        yield 'la de la plantilla manda' => [
            "<head>\n  <meta name=\"generator\" content=\"Otro 1.2\">\n</head>",
            "<head>\n  <meta name=\"generator\" content=\"Otro 1.2\">\n</head>",
        ];
        yield 'también con comillas simples, sin ellas y con otro orden de atributos' => [
            "<head><meta content='X' name='Generator'></head>",
            "<head><meta content='X' name='Generator'></head>",
        ];
        yield 'sin comillas' => [
            '<head><meta name=generator content=X></head>',
            '<head><meta name=generator content=X></head>',
        ];
        yield 'otras etiquetas no cuentan' => [
            "<head>\n<meta property=\"og:generator\" content=\"X\">\n<meta name=\"generator-version\" content=\"1\">\n</head>",
            "<head>\n<meta property=\"og:generator\" content=\"X\">\n<meta name=\"generator-version\" content=\"1\">\n  {$e}\n</head>",
        ];
        yield 'una etiqueta generator después de </head> no cuenta' => [
            "<head>\n</head>\n<meta name=\"generator\" content=\"X\">",
            "<head>\n  {$e}\n</head>\n<meta name=\"generator\" content=\"X\">",
        ];
    }

    #[Test]
    #[DataProvider('paginas')]
    public function marcaElHtmlComoDebe(string $html, string $esperado): void
    {
        self::assertSame($esperado, Generador::marcar($html));
    }

    #[Test]
    public function marcarDosVecesNoCambiaNada(): void
    {
        $una = Generador::marcar("<head>\n<title>Hola</title>\n</head>\n");

        self::assertSame($una, Generador::marcar($una));
    }

    #[Test]
    public function porDefectoLasPaginasLlevanLaEtiquetaAntesDeCerrarLaCabecera(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\n");

        $construccion = $this->construir();

        self::assertSame(
            "<!doctype html>\n<head>\n  <title>Inicio</title>\n  " . self::ETIQUETA . "\n</head>\n<p>Hola</p>\n",
            $construccion->escritos['index.html'],
        );
        self::assertSame([], array_map('strval', $construccion->avisos));
    }

    #[Test]
    public function conGeneradorNoLasPaginasQuedanComoLasHizoLaPlantilla(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\ngenerador: no\n");

        $construccion = $this->construir();

        self::assertSame("<!doctype html>\n<head>\n  <title>Inicio</title>\n</head>\n<p>Hola</p>\n", $construccion->escritos['index.html']);
    }

    #[Test]
    #[DataProvider('valoresQueSiLaPonen')]
    public function sinLaClaveOConValorAfirmativoLaPonen(string $linea): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\n{$linea}");

        self::assertStringContainsString(self::ETIQUETA, $this->construir()->escritos['index.html']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valoresQueSiLaPonen(): iterable
    {
        yield 'sin la clave' => [''];
        yield 'sí' => ["generador: sí\n"];
        yield 'si' => ["generador: si\n"];
        yield 'true' => ["generador: true\n"];
    }

    #[Test]
    #[DataProvider('valoresQueLaQuitan')]
    public function conValorNegativoNoLaPonen(string $linea): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\n{$linea}");

        $construccion = $this->construir();

        self::assertStringNotContainsString('generator', $construccion->escritos['index.html']);
        self::assertSame([], array_map('strval', $construccion->avisos));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valoresQueLaQuitan(): iterable
    {
        yield 'no' => ["generador: no\n"];
        yield 'false' => ["generador: false\n"];
        yield 'No' => ["generador: No\n"];
    }

    #[Test]
    public function unValorQueNoEsSiNiNoSeAvisaYSeUsaSi(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\ngenerador: quizá\n");

        $construccion = $this->construir();

        self::assertSame(['sitio.yml:3: «generador» tiene que ser sí o no; se usa sí'], array_map('strval', $construccion->avisos));
        self::assertStringContainsString(self::ETIQUETA, $construccion->escritos['index.html']);
    }

    #[Test]
    public function elLectorLaGuardaEnElSitio(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\n");
        self::assertTrue((new \Ehundu\Lector())->leer(Proyecto::abrir($this->carpetaTemporal()))->sitio->generador);

        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ngenerador: no\n");
        self::assertFalse((new \Ehundu\Lector())->leer(Proyecto::abrir($this->carpetaTemporal()))->sitio->generador);
    }

    #[Test]
    public function soloCambianLasPaginasHtmlConCabecera(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\nfeed:\n  coleccion: blog\n");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\n---\nPrimero.\n");
        $this->crearFichero('contenido/robots.twig', "---\nurl: /robots.txt\nplantilla: false\n---\nUser-agent: *\n");
        $this->crearFichero('contenido/fragmento.md', "---\ntitulo: Trozo\nplantilla: false\n---\n<p>Sin cabecera</p>\n");

        $construccion = $this->construir();

        self::assertStringContainsString(self::ETIQUETA, $construccion->escritos['index.html']);
        self::assertStringContainsString(self::ETIQUETA, $construccion->escritos['blog/uno/index.html']);
        self::assertSame("User-agent: *\n", $construccion->escritos['robots.txt']);
        self::assertSame("<p>Sin cabecera</p>\n", $construccion->escritos['fragmento/index.html']);

        foreach (['sitemap.xml', 'feed.xml'] as $fichero) {
            self::assertArrayHasKey($fichero, $construccion->escritos);
            self::assertStringNotContainsString('generator', $construccion->escritos[$fichero], $fichero);
        }
    }

    #[Test]
    public function seMarcanLasPaginasDeTodosLosIdiomas(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n    nombre: Español\n  - codigo: eu\n    nombre: Euskara\n");
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\nEscríbenos.\n");
        $this->crearFichero('contenido/contacto.eu.md', "---\ntitulo: Harremana\n---\nIdatzi.\n");

        $construccion = $this->construir();
        $paginas = array_filter(array_keys($construccion->escritos), fn (string $f) => str_ends_with($f, '.html'));

        self::assertCount(3, $paginas);

        foreach ($paginas as $fichero) {
            self::assertSame(1, substr_count($construccion->escritos[$fichero], self::ETIQUETA), $fichero);
        }
    }

    #[Test]
    public function cambiarLaClaveRehaceTodasLasPaginasYDaLoMismoQueUnaConstruccionCompleta(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\nEscríbenos.\n");
        $incremental = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $primera = $this->comprobar($incremental, $ahora);
        self::assertSame(2, $primera->rehechas);
        self::assertStringContainsString(self::ETIQUETA, $primera->escritos['contacto/index.html']);

        self::assertSame(0, $this->comprobar($incremental, $ahora)->rehechas);

        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ngenerador: no\n");
        $sin = $this->comprobar($incremental, $ahora);
        self::assertSame(2, $sin->rehechas);
        self::assertStringNotContainsString('generator', $sin->escritos['contacto/index.html']);
        self::assertStringNotContainsString('generator', $sin->escritos['index.html']);

        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $con = $this->comprobar($incremental, $ahora);
        self::assertSame(2, $con->rehechas);
        self::assertStringContainsString(self::ETIQUETA, $con->escritos['index.html']);
    }

    #[Test]
    public function elPdfDeUnaPaginaNoLlevaLaEtiqueta(): void
    {
        $this->crearSitio("nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('plantillas/informe.twig', "<!doctype html>\n<head>\n  <title>{{ pagina.titulo }}</title>\n</head>\n<p>{{ pagina.contenido }}</p>\n");
        $this->crearFichero('plantillas/informe.pdf.twig', "<!doctype html>\n<head>\n  <title>{{ pagina.titulo }}</title>\n</head>\n<p>PDF</p>\n");
        $this->crearFichero('contenido/informe.md', "---\ntitulo: Informe\nplantilla: informe\npdf: sí\n---\nTexto.\n");

        $con = $this->construir();

        self::assertStringContainsString(self::ETIQUETA, $con->escritos['informe/index.html']);
        self::assertStringStartsWith('%PDF-', $con->escritos['informe.pdf']);

        // El PDF sale igual con la etiqueta que sin ella: no pasa por ella
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ngenerador: no\n");
        $sin = $this->construir();

        self::assertStringNotContainsString('generator', $sin->escritos['informe/index.html']);
        self::assertSame($sin->escritos['informe.pdf'], $con->escritos['informe.pdf']);
    }

    private function crearSitio(string $sitio): void
    {
        $this->crearFichero('sitio.yml', $sitio);
        $this->crearFichero('plantillas/pagina.twig', "<!doctype html>\n<head>\n  <title>{{ pagina.titulo }}</title>\n</head>\n{{ pagina.contenido }}");
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola\n");
    }

    private function construir(): Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeImmutable(self::AHORA));
    }

    private function comprobar(Constructor $incremental, \DateTimeImmutable $ahora): Construccion
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $completa = (new Constructor())->construir($proyecto, $ahora);
        $parcial = $incremental->construir($proyecto, $ahora);

        self::assertSame($completa->escritos, $parcial->escritos);

        return $parcial;
    }
}
