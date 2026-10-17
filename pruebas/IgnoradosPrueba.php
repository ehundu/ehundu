<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Constructor;
use Ehundu\Ignorados;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Lo que el sistema y el control de versiones dejan en `publico/` y en `contenido/` no se copia (formato §9,
 * decisión 138); el resto de `publico/` sí, también lo que empieza por punto.
 */
final class IgnoradosPrueba extends TestCase
{
    use CarpetaTemporal;

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function rutas(): iterable
    {
        yield '.DS_Store' => ['.DS_Store', true];
        yield '.DS_Store en una carpeta' => ['img/.DS_Store', true];
        yield '.gitkeep' => ['css/.gitkeep', true];
        yield 'Thumbs.db' => ['Thumbs.db', true];
        yield 'thumbs.db en minúsculas' => ['img/thumbs.db', true];
        yield 'desktop.ini' => ['desktop.ini', true];
        yield 'DESKTOP.INI' => ['img/DESKTOP.INI', true];
        yield 'un .git anidado' => ['vendor/.git/config', true];
        yield 'un fichero .git' => ['vendor/.git', true];
        yield 'con barras de Windows' => ['img\\Thumbs.db', true];
        yield '.htaccess' => ['.htaccess', false];
        yield '.well-known' => ['.well-known/security.txt', false];
        yield 'un fichero normal' => ['css/estilos.css', false];
        yield 'un nombre que lo contiene' => ['img/mi.DS_Store.png', false];
        yield 'un nombre parecido' => ['gitkeep.txt', false];
        yield 'un .github' => ['.github/ayuda.md', false];
    }

    #[Test]
    #[DataProvider('rutas')]
    public function sabeQueEsIgnorado(string $ruta, bool $esperado): void
    {
        self::assertSame($esperado, Ignorados::es($ruta), $ruta);
    }

    #[Test]
    public function laCompilacionNoCopiaLoQueDejanElSistemaYGit(): void
    {
        $this->crearSitioMinimo();
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola");
        $this->crearFichero('contenido/blog/Thumbs.db', 'basura');
        $this->crearFichero('contenido/blog/foto.jpg', 'jpg');
        $this->crearFichero('publico/.htaccess', 'Options -Indexes');
        $this->crearFichero('publico/.DS_Store', 'basura');
        $this->crearFichero('publico/.gitkeep', '');
        $this->crearFichero('publico/Thumbs.db', 'basura');
        $this->crearFichero('publico/img/desktop.ini', 'basura');
        $this->crearFichero('publico/img/logo.svg', '<svg/>');
        $this->crearFichero('publico/vendor/.git/config', 'basura');
        $this->crearFichero('publico/.well-known/security.txt', 'Contact: x');

        $construccion = (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeImmutable('2026-01-01 12:00:00'));

        $copias = array_keys($construccion->copias);
        sort($copias);

        self::assertSame(['.htaccess', '.well-known/security.txt', 'blog/foto.jpg', 'img/logo.svg'], $copias);
        self::assertSame([], array_map('strval', $construccion->avisos), 'Sin aviso: no es un error');
    }
}
