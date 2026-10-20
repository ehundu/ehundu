<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\CompiladorIncrementalEnProceso;
use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `publicarHasta` (formato §4): una página, o un fragmento de una colección, se
 * publica hasta el final del día que dice y después desaparece, igual que
 * `publicar` hace que no aparezca antes. El caso que lo motivó es un aviso en
 * la portada (un fragmento de la colección `avisos`) que no hay que acordarse
 * de quitar.
 */
final class PublicarHastaPrueba extends TestCase
{
    use CarpetaTemporal;

    /** @var list<string> */
    private array $otras = [];

    #[After]
    protected function borrarOtras(): void
    {
        foreach ($this->otras as $carpeta) {
            self::borrarCarpeta($carpeta);
        }

        $this->otras = [];
    }

    #[Test]
    public function unAvisoCaducadoSaleDeLaColeccionYUnoConVentanaSoloEntreSusDias(): void
    {
        $this->crearSitio();

        $antes = $this->construir($this->madrid('2026-10-04 12:00:00'));
        $dentro = $this->construir($this->madrid('2026-10-09 12:00:00'));
        $despues = $this->construir($this->madrid('2026-10-13 12:00:00'));
        $mucho = $this->construir($this->madrid('2026-11-01 12:00:00'));

        // `Ya paso` caducó el 30 de septiembre; `Ventana` va del 5 al 12 de octubre; `Plazo` hasta el 31.
        self::assertSame('Plazo abierto;Siempre;', $antes->escritos['index.html']);
        self::assertSame('Plazo abierto;Siempre;Ventana;', $dentro->escritos['index.html']);
        self::assertSame('Plazo abierto;Siempre;', $despues->escritos['index.html']);
        self::assertSame('Siempre;', $mucho->escritos['index.html']);
    }

    #[Test]
    public function seVeElDiaEnteroYDesapareceALaMedianocheSiguienteEnLaZonaDelSitio(): void
    {
        $this->crearSitio();

        $ultimoSegundo = $this->construir($this->madrid('2026-10-31 23:59:59'));
        $medianoche = $this->construir($this->madrid('2026-11-01 00:00:00'));

        self::assertStringContainsString('Plazo abierto', $ultimoSegundo->escritos['index.html']);
        self::assertStringNotContainsString('Plazo abierto', $medianoche->escritos['index.html']);
    }

    #[Test]
    public function laPaginaCaducadaNoGeneraFicheroNiSaleEnElSitemapNiEnElFeed(): void
    {
        $this->crearSitio();

        $vigente = $this->construir($this->madrid('2026-10-14 12:00:00'));
        $caducada = $this->construir($this->madrid('2026-10-16 00:00:00'));

        // `Oferta` es de septiembre y lleva `publicarHasta: 2026-10-15`.
        self::assertArrayHasKey('blog/oferta/index.html', $vigente->escritos);
        self::assertStringContainsString('<loc>https://ejemplo.com/blog/oferta/</loc>', $vigente->escritos['sitemap.xml']);
        self::assertStringContainsString('<title>Oferta</title>', $vigente->escritos['feed.xml']);

        self::assertArrayNotHasKey('blog/oferta/index.html', $caducada->escritos);
        self::assertStringNotContainsString('/blog/oferta/', $caducada->escritos['sitemap.xml']);
        self::assertStringNotContainsString('<title>Oferta</title>', $caducada->escritos['feed.xml']);
        self::assertArrayHasKey('blog/fija/index.html', $caducada->escritos);
    }

    #[Test]
    public function puedePonerseEnLaCarpetaYUnaPaginaLoCambiaOLoQuita(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/avisos/_datos.yml', "etiquetas: [avisos]\nurl: false\npublicarHasta: 2026-10-05\n");
        $this->crearFichero('contenido/avisos/especial.md', "---\ntitulo: Especial\npublicarHasta: 2027-01-01\n---\nMás largo.\n");
        $this->crearFichero('contenido/avisos/permanente.md', "---\ntitulo: Permanente\npublicarHasta:\n---\nNo caduca.\n");

        $hoy = $this->construir($this->madrid('2026-10-09 12:00:00'));

        // La carpeta caduca todo el 5 de octubre, y gana lo que dice otra cosa: su propia fecha (`Plazo`, `Ventana`,
        // `Especial`) o ninguna, en blanco (`Permanente`). `Siempre` hereda el 5 y `Ya paso` tiene su 30 de septiembre.
        self::assertSame('Especial;Permanente;Plazo abierto;Ventana;', $hoy->escritos['index.html']);
    }

    #[Test]
    public function unValorQueNoEsUnaFechaSeAvisaYNoRetiene(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/avisos/raro.md', "---\ntitulo: Raro\npublicarHasta: pronto\n---\nSin fecha buena.\n");

        $construccion = $this->construir($this->madrid('2027-06-01 12:00:00'));

        self::assertContains(
            'contenido/avisos/raro.md:3: «publicarHasta» tiene que ser una fecha AAAA-MM-DD',
            array_map(strval(...), $construccion->avisos),
        );
        self::assertStringContainsString('Raro', $construccion->escritos['index.html']);
    }

    #[Test]
    public function conUnaHoraSeTomaSoloElDiaYSeAvisa(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/avisos/con-hora.md', "---\ntitulo: Con hora\npublicarHasta: 2026-10-20 09:00\n---\nHasta las nueve.\n");

        $durante = $this->construir($this->madrid('2026-10-20 18:00:00'));
        $despues = $this->construir($this->madrid('2026-10-21 00:00:00'));

        self::assertStringContainsString('Con hora', $durante->escritos['index.html'], 'La hora no cuenta: sigue todo el día');
        self::assertStringNotContainsString('Con hora', $despues->escritos['index.html']);
        self::assertContains(
            'contenido/avisos/con-hora.md:3: «publicarHasta» es un día, AAAA-MM-DD: la hora no cuenta y se toma solo el día',
            array_map(strval(...), $durante->avisos),
        );
    }

    #[Test]
    public function enVariosIdiomasCadaTraduccionMiraSuPublicarHasta(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nzonaHoraria: Europe/Madrid\nidiomas:\n  - codigo: es\n  - codigo: eu\n");
        $this->crearFichero('plantillas/pagina.twig', '{% for codigo, t in pagina.traducciones %}{{ codigo }}={{ t.url }};{% endfor %}');
        $this->crearFichero('contenido/uno.md', "---\ntitulo: Uno\nurl: /uno/\n---\nUno.\n");
        $this->crearFichero('contenido/uno.eu.md', "---\ntitulo: Bat\nurl: /bat/\npublicarHasta: 2026-10-05\n---\nBat.\n");

        $antes = $this->construir($this->madrid('2026-10-05 12:00:00'));
        $despues = $this->construir($this->madrid('2026-10-06 00:00:00'));

        self::assertSame('es=/uno/;eu=/eu/bat/;', $antes->escritos['uno/index.html']);
        self::assertArrayHasKey('eu/bat/index.html', $antes->escritos);
        self::assertSame('es=/uno/;', $despues->escritos['uno/index.html']);
        self::assertArrayNotHasKey('eu/bat/index.html', $despues->escritos);
    }

    #[Test]
    public function laPrevisualizacionConBorradoresEnseñaLoCaducado(): void
    {
        $this->crearSitio();

        $normal = $this->construir($this->madrid('2027-06-01 12:00:00'));
        $conBorradores = (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), $this->madrid('2027-06-01 12:00:00'), conBorradores: true);

        self::assertSame('Siempre;', $normal->escritos['index.html']);
        self::assertSame('Plazo abierto;Siempre;Ventana;Ya paso;', $conBorradores->escritos['index.html']);
    }

    #[Test]
    public function laIncrementalRehaceLoQueDependeDeLaPaginaQueCaducaYDaLoMismoQueUnaCompleta(): void
    {
        $this->crearSitio();
        $constructor = new Constructor(incremental: true);
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $hoy = $this->madrid('2026-10-15 12:00:00');
        $manana = $this->madrid('2026-10-16 00:00:00');

        $primera = $constructor->construir($proyecto, $hoy);
        self::assertArrayHasKey('blog/oferta/index.html', $primera->escritos);

        $igual = $constructor->construir($proyecto, $hoy);
        self::assertSame(0, $igual->rehechas);

        // Pasa el día sin tocar ningún fichero: la página con URL se va. Nada la lista, así que no se rehace nada más.
        $caduca = $constructor->construir($proyecto, $manana);
        self::assertArrayNotHasKey('blog/oferta/index.html', $caduca->escritos);
        self::assertSame((new Constructor())->construir($proyecto, $manana)->escritos, $caduca->escritos);

        // Un aviso (un fragmento) caduca el 31: la portada, que lo lista, se rehace aunque no haya cambiado ningún fichero.
        $ultimoDia = $this->madrid('2026-10-31 12:00:00');
        $siguiente = $this->madrid('2026-11-01 00:00:00');
        $constructor->construir($proyecto, $ultimoDia);
        $sinAviso = $constructor->construir($proyecto, $siguiente);

        self::assertGreaterThan(0, $sinAviso->rehechas);
        self::assertStringNotContainsString('Plazo abierto', $sinAviso->escritos['index.html']);
        self::assertSame((new Constructor())->construir($proyecto, $siguiente)->escritos, $sinAviso->escritos);
    }

    #[Test]
    public function entreProcesosLaPaginaCaducadaDesapareceDeSalidaYDaLoMismoQueUnaCompleta(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        (new CompiladorIncrementalEnProceso($this->madrid('2026-10-15 12:00:00')))->compilar($proyecto);
        self::assertFileExists($this->carpetaTemporal() . '/salida/blog/oferta/index.html');
        self::assertStringContainsString('Plazo abierto', $this->leerFichero('salida/index.html'));

        // Otro proceso, al día siguiente, sin que haya cambiado ningún fichero.
        $manana = $this->madrid('2026-11-01 00:00:00');
        (new CompiladorIncrementalEnProceso($manana))->compilar($proyecto);

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/blog/oferta/index.html');
        self::assertStringNotContainsString('Plazo abierto', $this->leerFichero('salida/index.html'));

        $completa = str_replace('\\', '/', sys_get_temp_dir()) . '/ehundu-pruebas-copia-' . bin2hex(random_bytes(6));
        $this->otras[] = $completa;
        self::copiarCarpeta($this->carpetaTemporal(), $completa, false);
        (new CompiladorEnProceso($manana))->compilar(Proyecto::abrir($completa));

        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
    }

    /**
     * Un sitio con avisos (fragmentos de la colección `avisos`, que la portada
     * lista) y un blog con una oferta que caduca. El día de las pruebas está
     * entre el 4 y el 31 de octubre de 2026.
     */
    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nzonaHoraria: Europe/Madrid\nfeed:\n  coleccion: blog\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero(
            'contenido/index.twig',
            "---\ntitulo: Inicio\n---\n{% for aviso in coleccion('avisos')|orden('titulo') %}{{ aviso.titulo }};{% endfor %}",
        );
        $this->crearFichero('contenido/avisos/_datos.yml', "etiquetas: [avisos]\nurl: false\n");
        $this->crearFichero('contenido/avisos/plazo.md', "---\ntitulo: Plazo abierto\npublicarHasta: 2026-10-31\n---\nInscríbete.\n");
        $this->crearFichero('contenido/avisos/siempre.md', "---\ntitulo: Siempre\n---\nNo caduca.\n");
        $this->crearFichero('contenido/avisos/ya-paso.md', "---\ntitulo: Ya paso\npublicarHasta: 2026-09-30\n---\nSe acabó.\n");
        $this->crearFichero('contenido/avisos/ventana.md', "---\ntitulo: Ventana\npublicar: 2026-10-05\npublicarHasta: 2026-10-12\n---\nDel 5 al 12.\n");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");
        $this->crearFichero('contenido/blog/oferta.md', "---\ntitulo: Oferta\nfecha: 2026-09-01\npublicarHasta: 2026-10-15\n---\nHasta el 15.\n");
        $this->crearFichero('contenido/blog/fija.md', "---\ntitulo: Fija\nfecha: 2026-09-02\n---\nNo caduca.\n");
    }

    private function madrid(string $momento): \DateTimeImmutable
    {
        return new \DateTimeImmutable($momento, new \DateTimeZone('Europe/Madrid'));
    }

    private function construir(\DateTimeImmutable $ahora): Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), $ahora);
    }

    /**
     * @return array<string, string> el SHA-1 de cada fichero de una carpeta, por ruta, en orden
     */
    private function arbol(string $carpeta): array
    {
        $ficheros = [];
        $raiz = rtrim(str_replace('\\', '/', $carpeta), '/') . '/';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $fichero) {
            if ($fichero->isFile()) {
                $ficheros[substr(str_replace('\\', '/', $fichero->getPathname()), strlen($raiz))] = sha1((string) file_get_contents($fichero->getPathname()));
            }
        }

        ksort($ficheros);

        return $ficheros;
    }

    /**
     * Una copia del proyecto sin `salida/` ni `.ehundu/`, para compilarlo entero y comparar.
     */
    private static function copiarCarpeta(string $origen, string $destino, bool $conMotor): void
    {
        mkdir($destino, 0777, true);

        foreach (scandir($origen) ?: [] as $nombre) {
            if ($nombre === '.' || $nombre === '..' || (!$conMotor && in_array($nombre, ['salida', '.ehundu'], true))) {
                continue;
            }

            is_dir("{$origen}/{$nombre}")
                ? self::copiarCarpeta("{$origen}/{$nombre}", "{$destino}/{$nombre}", $conMotor)
                : copy("{$origen}/{$nombre}", "{$destino}/{$nombre}");
        }
    }

    private static function borrarCarpeta(string $ruta): void
    {
        if (!is_dir($ruta) || is_link($ruta)) {
            @unlink($ruta);

            return;
        }

        foreach (scandir($ruta) ?: [] as $nombre) {
            if ($nombre !== '.' && $nombre !== '..') {
                self::borrarCarpeta("{$ruta}/{$nombre}");
            }
        }

        @rmdir($ruta);
    }
}
