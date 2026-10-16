<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\Webp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las variantes de ancho (formato §2 y §7.3) en la construcción incremental:
 * una página depende de cada fichero de variantes que mira, también de los que
 * todavía no existen, y de la sección `imagenes`. Después de cada cambio se
 * compara con una construcción completa.
 */
final class VariantesIncrementalPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    private const string SITIO = "nombre: Prueba\nurl: https://ejemplo.com\nimagenes:\n  anchos: [480, 960]\n";

    #[Test]
    public function unaPaginaSeRehaceCuandoApareceCambiaOSeVaUnaDeSusVariantes(): void
    {
        $this->crearFichero('sitio.yml', self::SITIO);
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\n![Una foto](/img/foto.jpg)\n");
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nSin imágenes.\n");
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $primera = $this->comprobar($constructor, $ahora, 'primera construcción');
        self::assertSame(2, $primera?->rehechas);
        self::assertStringNotContainsString('srcset', $primera->escritos['index.html'] ?? '');

        $this->webp('img/foto-480.webp', 480);
        $aparece = $this->comprobar($constructor, $ahora, 'aparece la primera variante: solo la página que las mira');
        self::assertSame(1, $aparece?->rehechas);
        self::assertStringContainsString('srcset="/img/foto-480.webp 480w"', $aparece->escritos['index.html'] ?? '');

        $this->webp('img/foto-960.webp', 960);
        $segunda = $this->comprobar($constructor, $ahora, 'aparece la segunda');
        self::assertSame(1, $segunda?->rehechas);
        self::assertStringContainsString('srcset="/img/foto-480.webp 480w, /img/foto-960.webp 960w"', $segunda->escritos['index.html'] ?? '');

        // Mismo tamaño en bytes y otro ancho: lo que cambia es el contenido.
        $this->webp('img/foto-960.webp', 900);
        $cambia = $this->comprobar($constructor, $ahora, 'cambia el ancho de una variante, sin cambiar de tamaño');
        self::assertSame(1, $cambia?->rehechas);
        self::assertStringContainsString('/img/foto-960.webp 900w', $cambia->escritos['index.html'] ?? '');

        unlink($this->carpetaTemporal() . '/publico/img/foto-480.webp');
        $seVa = $this->comprobar($constructor, $ahora, 'desaparece una variante');
        self::assertSame(1, $seVa?->rehechas);
        self::assertStringNotContainsString('foto-480', $seVa->escritos['index.html'] ?? '');

        $nada = $this->comprobar($constructor, $ahora, 'nada cambia');
        self::assertSame(0, $nada?->rehechas);
    }

    #[Test]
    public function cambiarLaSeccionImagenesRehaceTodo(): void
    {
        $this->crearFichero('sitio.yml', self::SITIO);
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\n![Una foto](/img/foto.jpg)\n");
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nSin imágenes.\n");
        $this->webp('img/foto-480.webp', 480);
        $this->webp('img/foto-1600.webp', 1600);
        $constructor = new Constructor(incremental: true);
        $ahora = new \DateTimeImmutable(self::AHORA);

        $this->comprobar($constructor, $ahora, 'primera construcción');

        $this->crearFichero('sitio.yml', str_replace('[480, 960]', '[480, 960, 1600]', self::SITIO));
        $cambia = $this->comprobar($constructor, $ahora, 'se añade un ancho a la sección');
        self::assertSame(2, $cambia?->rehechas);
        self::assertStringContainsString('/img/foto-1600.webp 1600w', $cambia->escritos['index.html'] ?? '');
    }

    /**
     * Las dos construcciones tienen que dar lo mismo: los mismos ficheros con
     * el mismo contenido y los mismos avisos.
     */
    private function comprobar(Constructor $incremental, \DateTimeImmutable $ahora, string $paso): ?Construccion
    {
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $completa = (new Constructor())->construir($proyecto, $ahora);
        $parcial = $incremental->construir($proyecto, $ahora);

        self::assertSame(array_keys($completa->escritos), array_keys($parcial->escritos), $paso);

        foreach ($completa->escritos as $fichero => $contenido) {
            self::assertSame($contenido, $parcial->escritos[$fichero], "{$paso}: {$fichero}");
        }

        self::assertSame($completa->copias, $parcial->copias, $paso);
        self::assertSame(array_map(strval(...), $completa->avisos), array_map(strval(...), $parcial->avisos), $paso);

        return $parcial;
    }

    private function webp(string $ruta, int $ancho): void
    {
        $this->crearFichero("publico/{$ruta}", Webp::de($ancho, intdiv($ancho * 2, 3)));
    }
}
