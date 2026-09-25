<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Plantillas;

use Ehundu\Plantillas\CargadorDePlantillas;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Error\LoaderError;

final class CargadorDePlantillasPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function leePlantillasYParcialesPorSuRutaDesdeLaRaiz(): void
    {
        $this->crearFichero('plantillas/base.twig', 'base');
        $this->crearFichero('parciales/cabecera.twig', 'cabecera');

        $cargador = $this->cargador();

        self::assertSame('base', $cargador->getSourceContext('plantillas/base.twig')->getCode());
        self::assertSame('cabecera', $cargador->getSourceContext('parciales/cabecera.twig')->getCode());
        self::assertTrue($cargador->exists('parciales/cabecera.twig'));
        self::assertFalse($cargador->exists('parciales/pie.twig'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nombresFueraDeLasCarpetas(): iterable
    {
        yield 'datos' => ['datos/cliente.yml'];
        yield 'secretos' => ['.secretos.yml'];
        yield 'contenido' => ['contenido/index.md'];
        yield 'sin carpeta' => ['cabecera.twig'];
        yield 'subiendo desde plantillas' => ['plantillas/../datos/cliente.yml'];
        yield 'barra invertida' => ['plantillas\\base.twig'];
    }

    #[Test]
    #[DataProvider('nombresFueraDeLasCarpetas')]
    public function noLeeNadaFueraDePlantillasYParciales(string $nombre): void
    {
        $this->crearFichero('datos/cliente.yml', 'secreto: sí');
        $this->crearFichero('.secretos.yml', 'clave: 1234');
        $this->crearFichero('contenido/index.md', 'Inicio');
        $this->crearFichero('cabecera.twig', 'cabecera');

        $cargador = $this->cargador();

        self::assertFalse($cargador->exists($nombre));

        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('tienen que estar en plantillas/ o parciales/');

        $cargador->getSourceContext($nombre);
    }

    private function cargador(): CargadorDePlantillas
    {
        return new CargadorDePlantillas(Proyecto::abrir($this->carpetaTemporal()));
    }
}
