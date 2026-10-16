<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\CompiladorIncrementalEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Incremental\Almacen;
use Ehundu\Informe;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La compilación incremental entre procesos (formato §10.5): lo que recuerda se guarda en `.ehundu/memoria.bin`
 * y otra compilación, con un objeto nuevo como en otro proceso, lo aprovecha y da siempre lo mismo que una
 * completa; si la memoria no vale se ignora sin error, y repartida en llamadas con presupuesto también.
 */
final class IncrementalPersistidaPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

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
    public function otraCompilacionAprovechaLaMemoriaYDaLoMismoQueUnaCompleta(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $primera = $this->compilador()->compilar($proyecto);

        self::assertSame($primera->paginas, $primera->rehechas, 'Sin memoria, se hace todo');
        self::assertFileExists($this->carpetaTemporal() . '/.ehundu/memoria.bin');
        self::assertSame("*\n", $this->leerFichero('.ehundu/.gitignore'));

        $this->crearFichero('contenido/blog/dos.md', "---\ntitulo: Dos\nfecha: 2024-02-01\ntemas: [ideas, oficio]\n---\nSegundo artículo, ahora más largo.\n");

        // Un objeto nuevo: lo único que une las dos compilaciones es el disco
        $segunda = $this->compilador()->compilar($proyecto);

        self::assertLessThan($segunda->paginas, $segunda->rehechas, 'Solo se rehace lo que el cambio afecta');
        self::assertGreaterThan(0, $segunda->rehechas);

        $completa = $this->copiaDelProyecto();
        $informeCompleto = (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));

        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
        self::assertSame($informeCompleto->paginas, $segunda->paginas);
        self::assertSame($informeCompleto->ficheros, $segunda->ficheros);
        self::assertSame(array_map(strval(...), $informeCompleto->avisos), array_map(strval(...), $segunda->avisos));
        self::assertNotSame([], $segunda->avisos, 'El sitio de prueba da avisos, para compararlos');
    }

    #[Test]
    public function sinCambiosNoRehaceNadaNiVuelveAGuardarLaMemoria(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);
        $antes = md5_file($this->carpetaTemporal() . '/.ehundu/memoria.bin');

        $segunda = $this->compilador()->compilar($proyecto);

        self::assertSame(0, $segunda->rehechas);
        self::assertSame($antes, md5_file($this->carpetaTemporal() . '/.ehundu/memoria.bin'), 'No se reescribe lo que vale igual');
    }

    #[Test]
    public function repartidaEnLlamadasConPresupuestoDaLoMismoQueUnaCompleta(): void
    {
        $this->crearSitio();
        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        $llamadas = 0;
        $anterior = -1;

        do {
            $lote = $this->compilador()->avanzar($proyecto, 0.0);
            $llamadas++;
            self::assertGreaterThan($anterior, $lote->hechas, 'Cada llamada rehace al menos una página');
            $anterior = $lote->hechas;
            self::assertSame($lote->completo(), $lote->hechas === $lote->total);
        } while (!$lote->completo() && $llamadas < 200);

        self::assertTrue($lote->completo());
        self::assertGreaterThan(3, $llamadas, 'Con el presupuesto a cero, una página por llamada');
        self::assertSame(1.0, $lote->progreso());
        // El informe cuenta la compilación entera, no solo la última llamada
        self::assertSame($lote->informe()->paginas, $lote->informe()->rehechas);
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/.ehundu/memoria-curso.json', 'Al terminar, no queda lo acumulado');
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
        self::assertSame(array_map(strval(...), $this->informeDe($completa)->avisos), array_map(strval(...), $lote->informe()->avisos));
    }

    #[Test]
    public function mientrasSeReparteSalidaMezclaPaginasYLoQueSobraSeBorraAlFinal(): void
    {
        $this->crearSitio();
        $this->crearFichero('salida/vieja/index.html', 'sobra');

        $lote = $this->compilador()->avanzar(Proyecto::abrir($this->carpetaTemporal()), 0.0);

        self::assertFalse($lote->completo());
        self::assertFileExists($this->carpetaTemporal() . '/salida/vieja/index.html');

        do {
            $lote = $this->compilador()->avanzar(Proyecto::abrir($this->carpetaTemporal()), 0.0);
        } while (!$lote->completo());

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/vieja/index.html');
    }

    #[Test]
    public function siElProyectoCambiaEntreLlamadasSoloSeRehaceLoQueElCambioAfecta(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());

        // Se reparte a medias, se cambia una página y se termina
        $this->compilador()->avanzar($proyecto, 0.0);
        $this->compilador()->avanzar($proyecto, 0.0);
        $this->crearFichero('contenido/gracias.md', "---\ntitulo: Gracias de nuevo\n---\nGracias por todo, de verdad.\n");

        do {
            $lote = $this->compilador()->avanzar($proyecto, 0.0);
        } while (!$lote->completo());

        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));

        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
        self::assertStringContainsString('Gracias de nuevo', $this->leerFichero('salida/gracias/index.html'));
    }

    #[Test]
    public function unaLlamadaConLoYaHechoAprovechaLoQueQuedabaDeUnaMemoriaCompleta(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $total = $this->compilador()->compilar($proyecto)->paginas;

        // Un cambio que afecta a todas las páginas (la plantilla base) y un presupuesto de cero: solo se rehace una
        // página por llamada, y las que quedan siguen valiendo hasta que les toca
        $this->crearFichero('plantillas/base.twig', "<!doctype html>\n<title>{{ pagina.titulo }} - {{ sitio.nombre }}</title>\n{{ css_fichero() }}{{ js_fichero() }}\n<main>{% block principal %}{{ pagina.contenido }}{% endblock %}</main>\n");
        $hechas = [];

        do {
            $lote = $this->compilador()->avanzar($proyecto, 0.0);
            $hechas[] = $lote->hechas;
        } while (!$lote->completo() && count($hechas) < 200);

        self::assertTrue($lote->completo());
        self::assertSame($total, $lote->informe()->paginas);
        // La plantilla base afecta a casi todas (no al informe ni a lo que no usa plantilla), y se han rehecho entre
        // varias llamadas: el informe las cuenta todas
        self::assertGreaterThan(5, $lote->informe()->rehechas);
        self::assertLessThanOrEqual($total, $lote->informe()->rehechas);
        self::assertGreaterThanOrEqual($lote->informe()->rehechas, count($hechas), 'Con el presupuesto a cero, una página rehecha por llamada');
        self::assertSame($hechas, array_values(array_unique($hechas)), 'Lo hecho no se pierde de una llamada a otra');

        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
    }

    /**
     * @return iterable<string, array{0: callable(string): void}>
     */
    public static function memoriasQueNoValen(): iterable
    {
        yield 'cortada' => [static fn (string $f) => file_put_contents($f, substr((string) file_get_contents($f), 0, 5000))];
        yield 'vacía' => [static fn (string $f) => file_put_contents($f, '')];
        yield 'basura' => [static fn (string $f) => file_put_contents($f, str_repeat("basura\n", 100))];
        yield 'de otra versión de la memoria' => [static fn (string $f) => file_put_contents($f, preg_replace('/^(EHUNDU-MEMORIA) \d+/', '$1 999', (string) file_get_contents($f), 1))];
        yield 'de otro motor' => [static fn (string $f) => file_put_contents($f, preg_replace('/^(EHUNDU-MEMORIA \d+) \S+/', '$1 0000000000000000', (string) file_get_contents($f), 1))];
        yield 'con la carga estropeada' => [static function (string $f): void {
            $datos = (string) file_get_contents($f);
            // Mismo tamaño que dice la cabecera, pero ya no es una memoria
            file_put_contents($f, substr($datos, 0, (int) strpos($datos, "\n") + 1) . str_repeat('x', strlen($datos) - (int) strpos($datos, "\n") - 1));
        }];
    }

    /**
     * @param callable(string): void $estropear
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('memoriasQueNoValen')]
    public function unaMemoriaQueNoValeSeIgnoraSinErrorYSeCompilaEntero(callable $estropear): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);
        $estropear($this->carpetaTemporal() . '/.ehundu/memoria.bin');

        $informe = $this->compilador()->compilar($proyecto);

        self::assertSame($informe->paginas, $informe->rehechas, 'Sin una memoria que valga, se hace todo');
        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));

        // Y la que ha quedado vale
        self::assertSame(0, $this->compilador()->compilar($proyecto)->rehechas);
    }

    #[Test]
    public function laMemoriaDeUnProyectoNoValeParaOtroEnOtraCarpeta(): void
    {
        $this->crearSitio();
        $this->compilador()->compilar(Proyecto::abrir($this->carpetaTemporal()));

        $otra = str_replace('\\', '/', sys_get_temp_dir()) . '/ehundu-pruebas-otra-' . bin2hex(random_bytes(6));
        $this->otras[] = $otra;
        self::copiarCarpeta($this->carpetaTemporal(), $otra, true);

        $informe = $this->compilador()->compilar(Proyecto::abrir($otra));

        self::assertSame($informe->paginas, $informe->rehechas);
    }

    #[Test]
    public function unErrorDelProyectoDejaLaMemoriaYSalidaComoEstaban(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);
        $memoria = md5_file($this->carpetaTemporal() . '/.ehundu/memoria.bin');
        $salida = $this->arbol($this->carpetaTemporal() . '/salida');

        $this->crearFichero('plantillas/lista.twig', "{% extends 'plantillas/base.twig' %}\n{% block principal %}{{ roto( }}{% endblock %}\n");

        try {
            $this->compilador()->compilar($proyecto);
            self::fail('Una plantilla rota tiene que detener la compilación');
        } catch (ErrorDeProyecto) {
            self::assertSame($memoria, md5_file($this->carpetaTemporal() . '/.ehundu/memoria.bin'));
            self::assertSame($salida, $this->arbol($this->carpetaTemporal() . '/salida'));
        }

        // Arreglado, se vuelve a aprovechar lo que había, no se empieza de cero
        $this->crearFichero('plantillas/lista.twig', "{% extends 'plantillas/base.twig' %}\n{% block principal %}{% set p = pagina.paginacion %}[{{ p.actual }}/{{ p.total }}]{% for a in pagina.paginas %}<a href=\"{{ a.url }}\">{{ a.titulo }}</a>{% endfor %}.{% endblock %}\n");
        $informe = $this->compilador()->compilar($proyecto);

        self::assertLessThan($informe->paginas, $informe->rehechas);
        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
    }

    #[Test]
    public function olvidarHaceQueLaSiguienteSeaCompleta(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);

        $this->compilador()->olvidar($proyecto);

        self::assertFileDoesNotExist($this->carpetaTemporal() . '/.ehundu/memoria.bin');
        $informe = $this->compilador()->compilar($proyecto);
        self::assertSame($informe->paginas, $informe->rehechas);
    }

    #[Test]
    public function unaSalidaBorradaOCambiadaSeRepara(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);
        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));

        unlink($this->carpetaTemporal() . '/salida/acerca/index.html');
        file_put_contents($this->carpetaTemporal() . '/salida/gracias/index.html', 'tocado a mano');
        file_put_contents($this->carpetaTemporal() . '/salida/sobra.html', 'sobra');

        $informe = $this->compilador()->compilar($proyecto);

        self::assertSame(0, $informe->rehechas, 'La memoria vale: no hay nada que rehacer');
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
    }

    #[Test]
    public function unaLlamadaQueLlegaMientrasOtraAvanzaNoEsperaNiHaceNada(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $cerrojo = Almacen::bloquear($proyecto);
        self::assertNotNull($cerrojo);

        try {
            $lote = $this->compilador()->avanzar($proyecto, 0.0);
        } finally {
            Almacen::soltar($cerrojo);
        }

        self::assertTrue($lote->ocupado());
        self::assertFalse($lote->completo());
        self::assertSame(0, $lote->hechas);
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/index.html');
        self::assertFalse($this->compilador()->avanzar($proyecto, 600.0)->ocupado(), 'Suelto el cerrojo, avanza');
    }

    #[Test]
    public function lasClasesDeLaMemoriaEstanTodasEnLaListaQueSePermiteAlCargarla(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);

        $fichero = (string) file_get_contents($this->carpetaTemporal() . '/.ehundu/memoria.bin');
        $datos = substr($fichero, (int) strpos($fichero, "\n") + 1);
        preg_match_all('/(?:^|[;{}])[OE]:\d+:"([^":]+)/', $datos, $coincidencias);
        $clases = array_values(array_unique($coincidencias[1]));

        self::assertNotSame([], $clases);
        self::assertSame([], array_values(array_diff($clases, Almacen::CLASES)), 'Una clase nueva en la memoria tiene que entrar en Almacen::CLASES');
    }

    #[Test]
    public function elInformeDiceCuantasPaginasSeHanRehechoYSuResumenLoCuenta(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->compilador()->compilar($proyecto);
        $this->crearFichero('contenido/gracias.md', "---\ntitulo: Gracias\n---\nGracias, y algo más.\n");

        $informe = $this->compilador()->compilar($proyecto);

        self::assertInstanceOf(Informe::class, $informe);
        self::assertSame(1, $informe->rehechas);
        self::assertMatchesRegularExpression('/\(1 rehecha\)/', $informe->resumen());
    }

    private function compilador(): CompiladorIncrementalEnProceso
    {
        return new CompiladorIncrementalEnProceso($this->ahora());
    }

    private function informeDe(string $carpeta): Informe
    {
        return (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($carpeta));
    }

    private function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::AHORA);
    }

    private function crearSitio(): void
    {
        $ficheros = [
            'sitio.yml' => "nombre: Prueba\nurl: https://ejemplo.com\nfeed:\n  coleccion: blog\nimagenes:\n  anchos: [480, 960]\n",
            'plantillas/base.twig' => "<!doctype html>\n<title>{{ pagina.titulo }} · {{ sitio.nombre }}</title>\n{{ css_fichero() }}{{ js_fichero() }}\n<main>{% block principal %}{{ pagina.contenido }}{% endblock %}</main>\n",
            'plantillas/pagina.twig' => "{% extends 'plantillas/base.twig' %}\n",
            'plantillas/lista.twig' => "{% extends 'plantillas/base.twig' %}\n{% block principal %}{% set p = pagina.paginacion %}[{{ p.actual }}/{{ p.total }}]{% for a in pagina.paginas %}<a href=\"{{ a.url }}\">{{ a.titulo }}</a>{% endfor %}{% endblock %}\n",
            'plantillas/tema.twig' => "{% extends 'plantillas/base.twig' %}\n{% block principal %}{{ pagina.grupo }}:{% for a in pagina.paginas %}{{ a.titulo }},{% endfor %}{% endblock %}\n",
            'plantillas/informe.twig' => "<html><body>{{ pagina.contenido }}<a href=\"{{ pagina.pdf }}\">PDF</a></body></html>\n",
            'plantillas/informe.pdf.twig' => "<html><body><h1>{{ pagina.titulo }}</h1>{{ pagina.contenido }}</body></html>\n",
            'contenido/index.md' => "---\ntitulo: Inicio\ncss: [base.css]\n---\nBienvenida. [archivo fichero=\"doc.pdf\" texto=\"Folleto\"]\n",
            'contenido/acerca.md' => "---\ntitulo: Acerca\njs: [app.js]\n---\nSomos nosotros.\n\n[imagen fichero=\"falta.jpg\" alt=\"Falta\"]\n",
            'contenido/gracias.md' => "---\ntitulo: Gracias\n---\nGracias.\n",
            'contenido/leeme.md' => "---\nurl: /leeme.txt\nplantilla: false\n---\nLéeme.\n",
            'contenido/informe.md' => "---\ntitulo: Informe\nplantilla: informe\npdf: sí\n---\nResumen del año.\n",
            'contenido/blog/_datos.yml' => "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n",
            'contenido/blog/uno.md' => "---\ntitulo: Uno\nfecha: 2024-01-01\ntemas: [ideas]\ncss: [dos.css]\n---\nPrimer artículo.\n",
            'contenido/blog/dos.md' => "---\ntitulo: Dos\nfecha: 2024-02-01\ntemas: [ideas, oficio]\n---\nSegundo artículo.\n",
            'contenido/blog/tres.md' => "---\ntitulo: Tres\nfecha: 2024-03-01\n---\nTercero.\n",
            'contenido/blog/futuro.md' => "---\ntitulo: Futuro\nfecha: 2024-04-01\npublicar: 2030-01-01\n---\nAún no.\n",
            'contenido/blog/index.md' => "---\ntitulo: Blog\nplantilla: lista\nurl: /blog/\npaginar:\n  coleccion: blog\n  porPagina: 2\n  orden: fecha desc\n---\nLista.\n",
            'contenido/tema.md' => "---\ntitulo: Tema\nplantilla: tema\nurl: \"/tema/{{ grupo|slug }}/\"\nagrupar:\n  coleccion: blog\n  campo: temas\n---\nTema.\n",
            'contenido/doc.txt' => "Un documento que se copia tal cual.\n",
            'publico/base.css' => "body{margin:0}\n",
            'publico/dos.css' => ".dos{color:red}\n",
            'publico/app.js' => "console.log(1);\n",
            'publico/img/foto.jpg' => 'JPEG',
            'publico/doc.pdf' => str_repeat('PDF', 300),
            'publico/.htaccess' => "Options -Indexes\n",
        ];

        foreach ($ficheros as $ruta => $contenido) {
            $this->crearFichero($ruta, $contenido);
        }
    }

    /**
     * Una copia del proyecto en otra carpeta, sin `salida/` ni `.ehundu/`, para compilarlo entero y comparar.
     */
    private function copiaDelProyecto(): string
    {
        $copia = str_replace('\\', '/', sys_get_temp_dir()) . '/ehundu-pruebas-copia-' . bin2hex(random_bytes(6));
        $this->otras[] = $copia;
        self::copiarCarpeta($this->carpetaTemporal(), $copia, false);

        return $copia;
    }

    /**
     * @return array<string, string> el SHA-1 de cada fichero de una carpeta, por ruta, en orden
     */
    private function arbol(string $carpeta): array
    {
        $arbol = [];

        if (!is_dir($carpeta)) {
            return $arbol;
        }

        $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS));

        foreach ($recorrido as $fichero) {
            if ($fichero->isFile()) {
                $arbol[substr(str_replace('\\', '/', $fichero->getPathname()), strlen($carpeta) + 1)] = sha1_file($fichero->getPathname());
            }
        }

        ksort($arbol, SORT_STRING);

        return $arbol;
    }

    /**
     * @param bool $conMotor si se copia también `.ehundu/` (con la memoria) y `salida/`
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
