<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\CompiladorPorLotesEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Lote;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La compilación por lotes (formato §10.4): repartida en tantas llamadas como
 * haga falta, da lo mismo que la completa; y retoma, empieza de nuevo cuando el
 * proyecto cambia, no se pisa con otra llamada y no deja `salida/` a medias si
 * falla antes de escribir.
 */
final class LotesPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    /** @var list<string> carpetas temporales de los proyectos que se comparan */
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
    public function unLoteRepartidoEnTareasSueltasDaLoMismoQueLaCompilacionCompleta(): void
    {
        $this->crearSitio();
        $completa = $this->copiaDelProyecto();
        $this->sembrarSalida($this->carpetaTemporal());
        $this->sembrarSalida($completa);

        $informeCompleto = (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));

        // Cada llamada con un objeto nuevo y sin presupuesto: una tarea por petición
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $llamadas = 0;
        $anterior = -1;

        do {
            $lote = (new CompiladorPorLotesEnProceso($this->ahora()))->avanzar($proyecto, 0.0);
            $llamadas++;
            self::assertGreaterThan($anterior, $lote->hechas, 'Cada llamada hace al menos una tarea');
            $anterior = $lote->hechas;
            self::assertSame($lote->completo(), $lote->hechas === $lote->total);
        } while (!$lote->completo() && $llamadas < 500);

        self::assertTrue($lote->completo());
        self::assertSame($lote->total, $llamadas);
        self::assertSame(1.0, $lote->progreso());
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
        self::assertInstanceOf(Informe::class, $lote->informe());
        self::assertSame($informeCompleto->paginas, $lote->informe()->paginas);
        self::assertSame($informeCompleto->ficheros, $lote->informe()->ficheros);
        self::assertSame(array_map(strval(...), $informeCompleto->avisos), array_map(strval(...), $lote->informe()->avisos));
        self::assertNotSame([], $lote->informe()->avisos, 'El sitio de prueba da avisos, para compararlos');
    }

    #[Test]
    public function conPresupuestoDeSobraTerminaEnUnaLlamadaYDaLoMismo(): void
    {
        $this->crearSitio();
        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));

        $lote = (new CompiladorPorLotesEnProceso($this->ahora()))->avanzar(Proyecto::abrir($this->carpetaTemporal()), 600.0);

        self::assertTrue($lote->completo());
        self::assertFalse($lote->reiniciado());
        self::assertFalse($lote->ocupado());
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
    }

    #[Test]
    public function escribeLasPaginasAlHacerlasYSalidaQuedaMezclada(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->sembrarSalida($this->carpetaTemporal());

        $lote = (new CompiladorPorLotesEnProceso($this->ahora()))->avanzar($proyecto, 0.0);

        self::assertFalse($lote->completo());
        self::assertSame(1, $lote->hechas);
        self::assertNull($lote->informe());
        // La primera página ya está; lo viejo que sobra todavía no se ha borrado
        self::assertFileExists($this->carpetaTemporal() . '/salida/acerca/index.html');
        self::assertFileExists($this->carpetaTemporal() . '/salida/vieja/index.html');
    }

    #[Test]
    public function elEstadoVivePorSuCuentaYSeLeeSinAvanzar(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        self::assertNull($compilador->estado($proyecto));

        $compilador->avanzar($proyecto, 0.0);
        $compilador->avanzar($proyecto, 0.0);
        $estado = (new CompiladorPorLotesEnProceso())->estado($proyecto);

        self::assertInstanceOf(Lote::class, $estado);
        self::assertSame(2, $estado->hechas);
        self::assertFalse($estado->completo());
        self::assertGreaterThan(0.0, $estado->progreso());
        self::assertLessThan(1.0, $estado->progreso());
        self::assertFileExists($this->carpetaTemporal() . '/.ehundu/lote.json');
    }

    #[Test]
    public function laCarpetaDelMotorSeIgnoraSolaEnGit(): void
    {
        $this->crearSitio();

        (new CompiladorPorLotesEnProceso($this->ahora()))->avanzar(Proyecto::abrir($this->carpetaTemporal()), 0.0);

        self::assertSame("*\n", $this->leerFichero('.ehundu/.gitignore'));
    }

    #[Test]
    public function siElProyectoCambiaEmpiezaDeNuevoYTerminaConLoNuevo(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        $compilador->avanzar($proyecto, 0.0);
        $compilador->avanzar($proyecto, 0.0);
        $this->crearFichero('contenido/acerca.md', "---\ntitulo: Acerca de nosotros\n---\nOtro texto, bastante más largo que el de antes.\n");

        $lote = $compilador->avanzar($proyecto, 0.0);

        self::assertTrue($lote->reiniciado());
        self::assertSame(1, $lote->hechas, 'Empieza de nuevo: solo lleva la tarea de esta llamada');

        $lote = $compilador->avanzar($proyecto, 600.0);

        self::assertTrue($lote->completo());
        self::assertFalse($lote->reiniciado());

        $completa = $this->copiaDelProyecto();
        (new CompiladorEnProceso($this->ahora()))->compilar(Proyecto::abrir($completa));
        self::assertSame($this->arbol($completa . '/salida'), $this->arbol($this->carpetaTemporal() . '/salida'));
        self::assertStringContainsString('Acerca de nosotros', $this->leerFichero('salida/acerca/index.html'));
    }

    #[Test]
    public function unLoteCompletoNoSeRehaceSoloPeroIniciarEmpiezaOtro(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        $primero = $compilador->avanzar($proyecto, 600.0);
        self::assertTrue($primero->completo());

        unlink($this->carpetaTemporal() . '/salida/acerca/index.html');
        $otra = $compilador->avanzar($proyecto, 600.0);

        self::assertTrue($otra->completo());
        self::assertFileDoesNotExist($this->carpetaTemporal() . '/salida/acerca/index.html', 'Un lote completo no hace nada');

        $nuevo = $compilador->iniciar($proyecto);

        self::assertFalse($nuevo->completo());
        self::assertSame(0, $nuevo->hechas);

        self::assertTrue($compilador->avanzar($proyecto, 600.0)->completo());
        self::assertFileExists($this->carpetaTemporal() . '/salida/acerca/index.html');
    }

    #[Test]
    public function abandonarOlvidaElLoteYUnoNuevoEmpiezaDeCero(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        $compilador->avanzar($proyecto, 0.0);
        $compilador->avanzar($proyecto, 0.0);
        $compilador->abandonar($proyecto);

        self::assertNull($compilador->estado($proyecto));
        self::assertSame(1, $compilador->avanzar($proyecto, 0.0)->hechas);
    }

    #[Test]
    public function unEstadoEstropeadoSeTomaPorQueNoHayLote(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $this->crearFichero('.ehundu/lote.json', '{"version": 1, "huella": ');

        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        self::assertNull($compilador->estado($proyecto));
        self::assertTrue($compilador->avanzar($proyecto, 600.0)->completo());
    }

    #[Test]
    public function siOtraLlamadaEstaAvanzandoEstaNoHaceNadaYLoDice(): void
    {
        $this->crearSitio();
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());
        $compilador->avanzar($proyecto, 0.0);

        $cerrojo = fopen($this->carpetaTemporal() . '/.ehundu/lote.lock', 'c');
        self::assertIsResource($cerrojo);
        self::assertTrue(flock($cerrojo, LOCK_EX | LOCK_NB));

        try {
            $lote = $compilador->avanzar($proyecto, 600.0);
            $iniciado = $compilador->iniciar($proyecto);
            $compilador->abandonar($proyecto);
        } finally {
            flock($cerrojo, LOCK_UN);
            fclose($cerrojo);
        }

        self::assertTrue($lote->ocupado());
        self::assertSame(1, $lote->hechas);
        self::assertFalse($lote->completo());
        self::assertTrue($iniciado->ocupado());
        self::assertSame(1, $compilador->estado($proyecto)?->hechas, 'Ni iniciar ni abandonar han tocado nada');
        self::assertFalse($compilador->avanzar($proyecto, 0.0)->ocupado());
    }

    #[Test]
    public function unErrorEnUnaPaginaDetieneElLoteYLoDejaComoEsta(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/zeta.twig', "---\ntitulo: Zeta\nplantilla: false\n---\n{{ llamada_que_no_existe() }}\n");
        $proyecto = Proyecto::abrir($this->carpetaTemporal());
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        try {
            $compilador->avanzar($proyecto, 600.0);
            self::fail('Se esperaba un ErrorDeProyecto');
        } catch (ErrorDeProyecto $error) {
            self::assertStringContainsString('zeta.twig', (string) ($error->fichero ?? $error->descripcion));
        }

        $lote = $compilador->estado($proyecto);
        self::assertNotNull($lote);
        self::assertFalse($lote->completo());
        self::assertGreaterThan(0, $lote->hechas, 'Lo hecho antes de fallar queda guardado');
        self::assertFileExists($this->carpetaTemporal() . '/salida/acerca/index.html');
    }

    #[Test]
    public function lasComprobacionesPreviasNoDejanSalidaAMedias(): void
    {
        $this->crearSitio();
        $this->sembrarSalida($this->carpetaTemporal());
        $antes = $this->arbol($this->carpetaTemporal() . '/salida');
        $this->crearFichero('publico/puerta-trasera.php', '<?php echo 1;');
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        foreach ([fn () => $compilador->iniciar(Proyecto::abrir($this->carpetaTemporal())), fn () => $compilador->avanzar(Proyecto::abrir($this->carpetaTemporal()), 600.0)] as $accion) {
            try {
                $accion();
                self::fail('Se esperaba un ErrorDeProyecto');
            } catch (ErrorDeProyecto $error) {
                self::assertStringContainsString('puerta-trasera.php', (string) ($error->fichero ?? '') . $error->descripcion);
            }
        }

        self::assertSame($antes, $this->arbol($this->carpetaTemporal() . '/salida'));
        self::assertNull($compilador->estado(Proyecto::abrir($this->carpetaTemporal())), 'No queda ningún lote a medias');
    }

    #[Test]
    public function dosPaginasEnElMismoFicheroSeDetectanAntesDeEscribir(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/otra-acerca.md', "---\ntitulo: Otra\nurl: /acerca/\n---\nRepetida.\n");
        $compilador = new CompiladorPorLotesEnProceso($this->ahora());

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Dos páginas van al mismo fichero');

        try {
            $compilador->avanzar(Proyecto::abrir($this->carpetaTemporal()), 600.0);
        } finally {
            self::assertDirectoryDoesNotExist($this->carpetaTemporal() . '/salida');
        }
    }

    /**
     * Un sitio con todo lo que una compilación completa puede hacer: páginas en
     * Markdown y en Twig, una colección con su feed, una lista partida en
     * páginas, una página por etiqueta, CSS y JS enlazados como ficheros, un
     * PDF, copias de `publico/` y de `contenido/`, y avisos.
     */
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
     * Lo que ya hay en `salida/` de una compilación anterior: algo que sobra, un
     * fichero donde ahora va una carpeta y una carpeta donde ahora va un fichero.
     */
    private function sembrarSalida(string $proyecto): void
    {
        foreach ([
            'vieja/index.html' => 'sobra',
            'gracias' => 'era un fichero y ahora es una carpeta',
            'leeme.txt/dentro.txt' => 'era una carpeta y ahora es un fichero',
        ] as $ruta => $contenido) {
            $completa = "{$proyecto}/salida/{$ruta}";
            is_dir(dirname($completa)) || mkdir(dirname($completa), 0777, true);
            file_put_contents($completa, $contenido);
        }
    }

    private function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::AHORA);
    }

    /**
     * Una copia del proyecto en otra carpeta, sin `salida/` ni `.ehundu/`, para
     * compilarlo entero y comparar.
     */
    private function copiaDelProyecto(): string
    {
        $copia = str_replace('\\', '/', sys_get_temp_dir()) . '/ehundu-pruebas-copia-' . bin2hex(random_bytes(6));
        $this->otras[] = $copia;
        self::copiarCarpeta($this->carpetaTemporal(), $copia);

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

    private static function copiarCarpeta(string $origen, string $destino): void
    {
        mkdir($destino, 0777, true);

        foreach (scandir($origen) ?: [] as $nombre) {
            if (in_array($nombre, ['.', '..', 'salida', '.ehundu'], true)) {
                continue;
            }

            is_dir("{$origen}/{$nombre}")
                ? self::copiarCarpeta("{$origen}/{$nombre}", "{$destino}/{$nombre}")
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
