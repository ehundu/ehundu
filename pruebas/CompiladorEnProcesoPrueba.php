<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompiladorEnProcesoPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function creaLaCarpetaDeSalida(): void
    {
        $this->crearSitioMinimo();

        $this->compilar();

        self::assertDirectoryExists($this->carpetaTemporal() . '/salida');
    }

    #[Test]
    public function unProyectoVacioNoGeneraPaginasNiAvisos(): void
    {
        $this->crearSitioMinimo();

        $informe = $this->compilar();

        self::assertSame(0, $informe->paginas);
        self::assertSame([], $informe->avisos);
    }

    #[Test]
    public function devuelveLosAvisosDeLaLectura(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', "---\nborrador: quizá\n---\n");

        $avisos = array_map(strval(...), $this->compilar()->avisos);

        self::assertSame(['contenido/index.md:2: «borrador» tiene que ser sí o no'], $avisos);
    }

    #[Test]
    public function dosPaginasConLaMismaUrlDetienenLaCompilacion(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\n");
        $this->crearFichero('contenido/escribenos.md', "---\ntitulo: Escríbenos\nurl: /contacto/\n---\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos páginas van al mismo fichero de salida, contacto/index.html');

        $this->compilar();
    }

    #[Test]
    public function unaPaginaQueAunNoSePublicaNoChoca(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\n");
        $this->crearFichero('contenido/escribenos.md', "---\ntitulo: Escríbenos\nurl: /contacto/\npublicar: 2026-10-01\n---\n");

        $informe = (new CompiladorEnProceso(new \DateTimeImmutable('2026-09-25', new \DateTimeZone('UTC'))))
            ->compilar(Proyecto::abrir($this->carpetaTemporal()));

        self::assertSame([], $informe->avisos);
    }

    #[Test]
    public function noTocaLaSalidaSiLaLecturaFalla(): void
    {
        try {
            $this->compilar();
            self::fail('Se esperaba un ErrorDeProyecto');
        } catch (ErrorDeProyecto) {
            self::assertDirectoryDoesNotExist($this->carpetaTemporal() . '/salida');
        }
    }

    private function compilar(): Informe
    {
        return (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
    }
}
