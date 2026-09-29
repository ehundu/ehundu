<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Pagina;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginaPrueba extends TestCase
{
    #[Test]
    public function unaPaginaSinMasEstaPublicada(): void
    {
        self::assertTrue($this->pagina([])->estaPublicada($this->ahora()));
        self::assertTrue($this->pagina(['borrador' => false])->estaPublicada($this->ahora()));
    }

    #[Test]
    public function unBorradorNoEstaPublicado(): void
    {
        self::assertFalse($this->pagina(['borrador' => true])->estaPublicada($this->ahora()));
    }

    #[Test]
    public function unaPaginaSePublicaDesdeSuFechaDePublicar(): void
    {
        $hoy = $this->pagina(['publicar' => new \DateTimeImmutable('2026-09-25', new \DateTimeZone('UTC'))]);
        $manana = $this->pagina(['publicar' => new \DateTimeImmutable('2026-09-26', new \DateTimeZone('UTC'))]);

        self::assertTrue($hoy->estaPublicada($this->ahora()));
        self::assertFalse($manana->estaPublicada($this->ahora()));
    }

    #[Test]
    public function elValorDeUnCampoEsElQueVenLasPlantillas(): void
    {
        $pagina = new Pagina('blog/hola.md', 'md', ['titulo' => 'Hola', 'url' => '/blog/{{ titulo|slug }}/'], '/blog/hola/', '', 1);

        self::assertSame('Hola', $pagina->valor('titulo'));
        self::assertSame('/blog/hola/', $pagina->valor('url'));
        self::assertSame('blog/hola.md', $pagina->valor('ruta'));
        self::assertNull($pagina->valor('subtitulo'));
        self::assertNull(new Pagina('pie.md', 'md', [], false, '', 1)->valor('url'));
    }

    #[Test]
    public function susTraduccionesCompartenLaClave(): void
    {
        self::assertSame('blog/uno', new Pagina('blog/uno.md', 'md', [], '/blog/uno/', '', 1)->clave());
        self::assertSame('blog/uno', new Pagina('blog/uno.eu.md', 'md', [], '/eu/blog/uno/', '', 1, 'eu')->clave());
        self::assertSame('index', new Pagina('index.es.twig', 'twig', [], '/', '', 1)->clave());
        self::assertSame('v1.2/guia', new Pagina('v1.2/guia.md', 'md', [], '/v1.2/guia/', '', 1)->clave());
    }

    #[Test]
    public function suIdiomaEsUnValorMas(): void
    {
        self::assertSame('es', $this->pagina([])->valor('idioma'));
        self::assertSame('eu', new Pagina('a.eu.md', 'md', [], '/eu/a/', '', 1, 'eu')->valor('idioma'));
    }

    #[Test]
    public function unFragmentoTambienSePublica(): void
    {
        self::assertTrue(new Pagina('a.md', 'md', [], false, '', 1)->estaPublicada($this->ahora()));
    }

    /**
     * @param array<array-key, mixed> $campos
     */
    #[Test]
    public function laDireccionDelPdfSaleDeLaUrlSiLaPaginaLoPide(): void
    {
        $conPdf = new Pagina('menu.md', 'md', ['pdf' => true], '/menu/', '', 1);

        self::assertSame('/menu.pdf', $conPdf->urlPdf());
        self::assertSame('/menu.pdf', $conPdf->valor('pdf'));
    }

    #[Test]
    public function sinPdfSiEsNoHayDireccionDePdf(): void
    {
        foreach ([[], ['pdf' => false]] as $campos) {
            $pagina = new Pagina('menu.md', 'md', $campos, '/menu/', '', 1);

            self::assertNull($pagina->urlPdf());
            self::assertNull($pagina->valor('pdf'));
        }
    }

    #[Test]
    public function unFragmentoOUnaUrlSinBarraNiHtmlNoTienenPdf(): void
    {
        self::assertNull((new Pagina('a.md', 'md', ['pdf' => true], false, '', 1))->urlPdf());
        self::assertNull((new Pagina('robots.twig', 'twig', ['pdf' => true], '/robots.txt', '', 1))->urlPdf());
    }

    private function pagina(array $campos): Pagina
    {
        return new Pagina('a.md', 'md', $campos, '/a/', '', 1);
    }

    private function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
    }
}
