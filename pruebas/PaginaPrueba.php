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
    public function unFragmentoTambienSePublica(): void
    {
        self::assertTrue(new Pagina('a.md', 'md', [], false, '', 1)->estaPublicada($this->ahora()));
    }

    /**
     * @param array<array-key, mixed> $campos
     */
    private function pagina(array $campos): Pagina
    {
        return new Pagina('a.md', 'md', $campos, '/a/', '', 1);
    }

    private function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
    }
}
