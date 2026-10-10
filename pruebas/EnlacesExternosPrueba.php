<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Avisos;
use Ehundu\Constructor;
use Ehundu\EnlacesExternos;
use Ehundu\Idiomas;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La sección `enlacesExternos` de `sitio.yml` (formato §2): `noreferrer` y el
 * aviso para lectores de pantalla en los enlaces externos del Markdown.
 */
final class EnlacesExternosPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    private Avisos $avisos;

    protected function setUp(): void
    {
        $this->avisos = new Avisos();
    }

    #[Test]
    public function sinLaSeccionNoLlevanNadaMas(): void
    {
        $enlaces = EnlacesExternos::leer(null, null, new Idiomas(), $this->avisos);

        self::assertSame([], $enlaces->avisos);
        self::assertSame('visually-hidden', $enlaces->clase);
        self::assertFalse($enlaces->noreferrer);
        self::assertNull($enlaces->avisoDe('es'));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unTextoSueltoEnUnSitioDeUnIdiomaEsElDeEseIdioma(): void
    {
        $enlaces = EnlacesExternos::leer(
            ['aviso' => '(abre en ventana nueva)', 'clase' => 'sr-only', 'noreferrer' => 'sí'],
            3,
            new Idiomas(),
            $this->avisos,
        );

        self::assertSame('(abre en ventana nueva)', $enlaces->avisoDe('es'));
        self::assertSame('sr-only', $enlaces->clase);
        self::assertTrue($enlaces->noreferrer);
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unTextoPorIdiomaEnUnSitioConVarios(): void
    {
        $enlaces = EnlacesExternos::leer(
            ['aviso' => ['es' => '(abre en ventana nueva)', 'eu' => '(leiho berrian irekitzen da)']],
            3,
            $this->dosIdiomas(),
            $this->avisos,
        );

        self::assertSame('(abre en ventana nueva)', $enlaces->avisoDe('es'));
        self::assertSame('(leiho berrian irekitzen da)', $enlaces->avisoDe('eu'));
        self::assertNull($enlaces->avisoDe('en'));
        self::assertSame([], $this->avisos());
    }

    #[Test]
    public function unTextoSueltoConVariosIdiomasSeUsaEnTodosYSeAvisa(): void
    {
        $enlaces = EnlacesExternos::leer(['aviso' => '(nueva ventana)'], 4, $this->dosIdiomas(), $this->avisos);

        self::assertSame('(nueva ventana)', $enlaces->avisoDe('es'));
        self::assertSame('(nueva ventana)', $enlaces->avisoDe('eu'));
        self::assertSame(
            ['sitio.yml:4: «enlacesExternos»: «aviso» es un solo texto y el sitio tiene varios idiomas (es, eu); se usa en todos. Conviene un texto por idioma: aviso: {es: …, eu: …}'],
            $this->avisos(),
        );
    }

    #[Test]
    public function avisaDelIdiomaSinTextoYDelQueElSitioNoTiene(): void
    {
        $enlaces = EnlacesExternos::leer(['aviso' => ['es' => 'Nueva ventana', 'fr' => 'Nouvelle fenêtre']], 4, $this->dosIdiomas(), $this->avisos);

        self::assertSame('Nueva ventana', $enlaces->avisoDe('es'));
        self::assertNull($enlaces->avisoDe('eu'));
        self::assertNull($enlaces->avisoDe('fr'));
        self::assertSame([
            'sitio.yml:4: «enlacesExternos»: «aviso» tiene un texto para «fr», que no es un idioma del sitio; se ignora',
            'sitio.yml:4: «enlacesExternos»: «aviso» no tiene texto en «eu»; los enlaces de ese idioma no llevan aviso',
        ], $this->avisos());
    }

    #[Test]
    public function loQueNoValeSeAvisaYSeIgnoraSinDetenerNada(): void
    {
        $enlaces = EnlacesExternos::leer(
            ['aviso' => ['es' => ['no'], 'eu' => 3], 'clase' => 'con comillas"', 'noreferrer' => 'quizá', 'otro' => 1],
            2,
            $this->dosIdiomas(),
            $this->avisos,
        );

        self::assertSame([], $enlaces->avisos);
        self::assertSame('visually-hidden', $enlaces->clase);
        self::assertFalse($enlaces->noreferrer);
        self::assertSame([
            'sitio.yml:2: «enlacesExternos» no admite «otro»; se ignora',
            'sitio.yml:2: «enlacesExternos»: el «aviso» de «es» tiene que ser un texto; ese idioma no lleva aviso',
            'sitio.yml:2: «enlacesExternos»: el «aviso» de «eu» tiene que ser un texto; ese idioma no lleva aviso',
            'sitio.yml:2: «enlacesExternos»: «clase» tiene que ser el nombre de una clase de CSS, o varios separados por espacios; se usa visually-hidden',
            'sitio.yml:2: «enlacesExternos»: «noreferrer» tiene que ser sí o no; se ignora',
        ], $this->avisos());
    }

    #[Test]
    public function unaSeccionQueNoEsUnMapaSeIgnora(): void
    {
        $enlaces = EnlacesExternos::leer(['a', 'b'], 7, new Idiomas(), $this->avisos);

        self::assertSame([], $enlaces->avisos);
        self::assertSame(
            ['sitio.yml:7: «enlacesExternos» tiene que ser un mapa con «aviso», «clase» y «noreferrer»; se ignora'],
            $this->avisos(),
        );
    }

    #[Test]
    public function unAvisoQueNoEsTextoNiMapaSeAvisaYSeIgnora(): void
    {
        $enlaces = EnlacesExternos::leer(['aviso' => ['uno', 'dos']], 7, new Idiomas(), $this->avisos);

        self::assertSame([], $enlaces->avisos);
        self::assertSame(
            ['sitio.yml:7: «enlacesExternos»: «aviso» tiene que ser un texto, o un texto por idioma con su código como clave; se ignora'],
            $this->avisos(),
        );
    }

    #[Test]
    public function seLeeDeSitioYml(): void
    {
        $this->crearSitio("enlacesExternos:\n  aviso: (abre en ventana nueva)\n  noreferrer: sí\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertSame(
            '<p><a rel="noopener noreferrer" target="_blank" href="https://otro.com/a">fuera<span class="visually-hidden"> (abre en ventana nueva)</span></a> y <a href="https://ejemplo.com/b/">dentro</a>.</p>',
            trim($construccion->escritos['index.html']),
        );
    }

    #[Test]
    public function sinLaSeccionLosEnlacesSalenComoSiempre(): void
    {
        $this->crearSitio('');

        self::assertSame(
            '<p><a rel="noopener" target="_blank" href="https://otro.com/a">fuera</a> y <a href="https://ejemplo.com/b/">dentro</a>.</p>',
            trim($this->construir()->escritos['index.html']),
        );
    }

    #[Test]
    public function cadaIdiomaLlevaSuAviso(): void
    {
        $this->crearSitio(
            "idiomas:\n  - codigo: es\n  - codigo: eu\nenlacesExternos:\n  aviso:\n    es: (abre en ventana nueva)\n    eu: (leiho berrian irekitzen da)\n  clase: sr-only\n",
        );
        $this->crearFichero('contenido/index.eu.md', "---\ntitulo: Hasiera\n---\n[kanpora](https://otro.com/a)\n");

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertStringContainsString('fuera<span class="sr-only"> (abre en ventana nueva)</span></a>', $construccion->escritos['index.html']);
        self::assertStringContainsString('kanpora<span class="sr-only"> (leiho berrian irekitzen da)</span></a>', $construccion->escritos['eu/index.html']);
    }

    #[Test]
    public function cambiarLaSeccionRehaceLasPaginasEnUnaConstruccionIncremental(): void
    {
        $this->crearSitio('');
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $primera = $constructor->construir($proyecto, $ahora);
        self::assertStringNotContainsString('visually-hidden', $primera->escritos['index.html']);

        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nenlacesExternos:\n  aviso: Nueva ventana\n");
        $segunda = $constructor->construir($proyecto, $ahora);

        self::assertStringContainsString('<span class="visually-hidden"> Nueva ventana</span>', $segunda->escritos['index.html']);
        self::assertSame($this->construir()->escritos, $segunda->escritos);
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

    private function crearSitio(string $extra): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n{$extra}");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', "---\ntitulo: Portada\n---\n[fuera](https://otro.com/a) y [dentro](https://ejemplo.com/b/).\n");
        $this->crearFichero('contenido/b.md', "---\ntitulo: B\n---\nB.\n");
    }

    private function construir(): \Ehundu\Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeImmutable(self::AHORA));
    }
}
