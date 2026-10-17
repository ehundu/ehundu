<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Compilador;
use Ehundu\CompiladorEnProceso;
use Ehundu\Despliegue\Desplegador;
use Ehundu\Despliegue\Manifiesto;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\DestinoEnMemoria;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DesplegadorPrueba extends TestCase
{
    use CarpetaTemporal;

    private DestinoEnMemoria $destino;

    protected function setUp(): void
    {
        $this->destino = new DestinoEnMemoria();
    }

    #[Test]
    public function laPrimeraVezLoSubeTodoYDejaElManifiesto(): void
    {
        $this->crearSitio();

        $informe = $this->desplegar();

        self::assertSame(['subir css/a.css', 'subir index.html', 'subir otra/index.html', 'subir sitemap.xml'], $this->destino->cambios());
        self::assertSame([Manifiesto::FICHERO, 'css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'], array_keys($this->ordenados()));
        self::assertSame(
            ['css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'],
            array_keys(Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->ficheros()),
        );
        self::assertSame('https://ejemplo.com', Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->url);
        self::assertSame(4, count($informe->subidos));
        self::assertStringStartsWith('Compilado: 2 páginas y 1 fichero ', $informe->compilacion->resumen());
        self::assertTrue($this->destino->cerrado);
    }

    #[Test]
    public function unDestinoDeOtroSitioDetieneElDespliegueSinTocarNada(): void
    {
        $this->crearSitio();
        $this->desplegar();
        $this->destino->operaciones = [];
        $this->destino->cerrado = false;
        $antes = $this->destino->ficheros;

        // Un sitio.yml copiado de otro sitio con la misma ruta.
        $this->crearFichero('sitio.yml', "nombre: Otro\nurl: https://otro.com/\ndespliegue:\n  destino: carpeta\n  ruta: /publicado\n");
        unlink($this->carpetaTemporal() . '/contenido/otra.md');

        foreach ([false, true] as $simular) {
            try {
                $this->desplegar(simular: $simular);
                self::fail('Tenía que fallar');
            } catch (ErrorDeProyecto $error) {
                self::assertSame(
                    'El destino es de otro sitio: su .ehundu.json dice https://ejemplo.com y sitio.yml dice https://otro.com. '
                        . 'Si la ruta de despliegue está mal, corrígela: desplegar ahí borraría lo que subió ese sitio. '
                        . 'Si es este mismo sitio con otra dirección, despliega con --todo',
                    $error->getMessage(),
                );
            }
        }

        self::assertSame([], $this->destino->operaciones);
        self::assertSame($antes, $this->destino->ficheros);
        self::assertTrue($this->destino->cerrado);
    }

    #[Test]
    public function conTodoElDestinoPasaAEsteSitio(): void
    {
        $this->crearSitio();
        $this->desplegar();
        $this->destino->operaciones = [];

        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://www.ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: /publicado\n");
        unlink($this->carpetaTemporal() . '/contenido/otra.md');
        $informe = $this->desplegar(todo: true);

        self::assertSame(['El destino era de https://ejemplo.com y pasa a ser de https://www.ejemplo.com'], array_map('strval', $informe->avisos));
        self::assertSame(['otra/index.html'], $informe->borrados);
        self::assertSame('https://www.ejemplo.com', Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->url);

        // Y a partir de ahí, como siempre.
        $this->destino->operaciones = [];
        $this->desplegar();
        self::assertSame([], $this->destino->operaciones);
    }

    #[Test]
    public function unManifiestoSinUrlSeAceptaYLaGanaAunqueNoHayaCambios(): void
    {
        $this->crearSitio();
        $this->desplegar();
        $deEhundu01 = new Manifiesto(Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->ficheros());
        $this->destino->ficheros[Manifiesto::FICHERO] = $deEhundu01->json();
        $this->destino->operaciones = [];

        $informe = $this->desplegar();

        self::assertSame(['escribir ' . Manifiesto::FICHERO], $this->destino->operaciones);
        self::assertSame([], $informe->subidos);
        self::assertSame([], $informe->avisos);
        self::assertSame('https://ejemplo.com', Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->url);
    }

    #[Test]
    public function laSegundaVezSoloSubeLoQueHaCambiado(): void
    {
        $this->crearSitio();
        $this->desplegar();
        $this->destino->operaciones = [];

        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nCambiada");
        $informe = $this->desplegar();

        self::assertSame(['subir otra/index.html'], $this->destino->operaciones === [] ? [] : $this->destino->cambios());
        self::assertSame(3, $informe->sinCambios);
        self::assertStringStartsWith('Desplegado en la carpeta /publicado: 1 fichero (', $informe->resumen());
    }

    #[Test]
    public function sinCambiosNoTocaNada(): void
    {
        $this->crearSitio();
        $this->desplegar();
        $this->destino->operaciones = [];

        $informe = $this->desplegar();

        self::assertSame([], $this->destino->operaciones);
        self::assertSame('Nada que desplegar en la carpeta /publicado: los 4 ficheros están al día.', $informe->resumen());
    }

    #[Test]
    public function borraLoQueSubioYYaNoSeGeneraYNoTocaLoDemas(): void
    {
        $this->crearSitio();
        $this->destino->ficheros['.well-known/acme-challenge/x'] = 'del certificado';
        $this->desplegar();
        $this->destino->operaciones = [];

        unlink($this->carpetaTemporal() . '/contenido/otra.md');
        $informe = $this->desplegar();

        // El sitemap cambia porque ya no lleva la página.
        self::assertSame(['subir sitemap.xml', 'borrar otra/index.html', 'quitar otra'], $this->destino->cambios());
        self::assertArrayHasKey('.well-known/acme-challenge/x', $this->destino->ficheros);
        self::assertSame(['otra/index.html'], $informe->borrados);
    }

    #[Test]
    public function simularNoTocaElDestino(): void
    {
        $this->crearSitio();

        $informe = $this->desplegar(simular: true);

        self::assertSame([], $this->destino->operaciones);
        self::assertSame(['css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'], $informe->subidos);
        self::assertStringStartsWith('Simulación en la carpeta /publicado: se subirían 4 ficheros (', $informe->resumen());
        self::assertStringEndsWith('No se ha tocado nada.', $informe->resumen());
    }

    #[Test]
    public function todoLoVuelveASubir(): void
    {
        $this->crearSitio();
        $this->desplegar();
        $this->destino->operaciones = [];

        $this->desplegar(todo: true);

        self::assertCount(4, $this->destino->cambios());
    }

    #[Test]
    public function unDespliegueCortadoSigueDondeSeQuedo(): void
    {
        $this->crearSitio();
        $this->destino->fallarAlSubir = 'otra/index.html';

        try {
            $this->desplegar();
            self::fail('Tenía que fallar');
        } catch (ErrorDeProyecto $error) {
            self::assertSame('No se puede subir otra/index.html (fallo de prueba)', $error->getMessage());
        }

        // Lo subido antes del fallo quedó anotado.
        self::assertSame(['css/a.css', 'index.html'], array_keys(Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->ficheros()));

        $this->destino->fallarAlSubir = null;
        $this->destino->operaciones = [];
        $this->desplegar();

        self::assertSame(['subir otra/index.html', 'subir sitemap.xml'], $this->destino->cambios());
    }

    #[Test]
    public function unManifiestoEstropeadoDetieneElDespliegueSalvoConTodo(): void
    {
        $this->crearSitio();
        $this->destino->ficheros[Manifiesto::FICHERO] = 'esto no es json';

        try {
            $this->desplegar();
            self::fail('Tenía que fallar');
        } catch (ErrorDeProyecto $error) {
            self::assertStringStartsWith('El .ehundu.json del destino no es un manifiesto de Ehundu que se pueda leer.', $error->getMessage());
        }

        self::assertSame([], $this->destino->operaciones);

        $informe = $this->desplegar(todo: true);

        self::assertCount(4, $informe->subidos);
        self::assertStringContainsString('se sube todo y se rehace', (string) $informe->avisos[0]);
    }

    #[Test]
    public function unManifiestoConUnaRutaQueSaleDelSitioNoHaceBorrarNadaFuera(): void
    {
        $this->crearSitio();
        $this->destino->ficheros['fuera.html'] = 'no es del sitio';
        $this->destino->ficheros[Manifiesto::FICHERO] = json_encode(['ehundu' => 1, 'ficheros' => ['../fuera.html' => ['md5' => md5('x'), 'tamano' => 1]]], JSON_THROW_ON_ERROR);

        try {
            $this->desplegar();
            self::fail('Tenía que fallar');
        } catch (ErrorDeProyecto $error) {
            self::assertStringStartsWith('El .ehundu.json del destino la ruta «../fuera.html» no es una ruta de dentro del sitio.', $error->getMessage());
        }

        self::assertSame([], $this->destino->operaciones, 'No se ha tocado nada del destino');
        self::assertArrayHasKey('fuera.html', $this->destino->ficheros);
    }

    #[Test]
    public function unFicheroDelSitioNoPuedeLlamarseComoElManifiesto(): void
    {
        $this->crearSitio();
        $this->crearFichero('publico/.ehundu.json', '{}');

        $this->expectExceptionMessage('El sitio tiene un fichero .ehundu.json en la raíz');

        $this->desplegar();
    }

    #[Test]
    public function siLaCompilacionFallaNoSeConectaConElDestino(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/rota.md', "---\ntitulo: [sin cerrar\n---\n");
        $abierto = false;
        $desplegador = new Desplegador(new CompiladorEnProceso(), function () use (&$abierto) {
            $abierto = true;

            return $this->destino;
        });

        try {
            $desplegador->desplegar(Proyecto::abrir($this->carpetaTemporal()));
            self::fail('Tenía que fallar');
        } catch (ErrorDeProyecto) {
        }

        self::assertFalse($abierto);
    }

    #[Test]
    public function conUnaCarpetaDeVerdad(): void
    {
        $this->crearSitio();
        $publicado = $this->carpetaTemporal() . '-publicado';
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: {$publicado}\n");

        try {
            (new Desplegador())->desplegar(Proyecto::abrir($this->carpetaTemporal()));
            unlink($this->carpetaTemporal() . '/contenido/otra.md');
            $informe = (new Desplegador())->desplegar(Proyecto::abrir($this->carpetaTemporal()));

            self::assertSame(['otra/index.html'], $informe->borrados);
            self::assertFileExists("{$publicado}/index.html");
            self::assertFileExists("{$publicado}/" . Manifiesto::FICHERO);
            self::assertDirectoryDoesNotExist("{$publicado}/otra");
        } finally {
            self::borrarCarpeta($publicado);
        }
    }

    #[Test]
    public function simularNoCreaLaCarpetaDeDestinoYDesplegarLaCreaAlSubirElPrimerFichero(): void
    {
        $this->crearSitio();
        $publicado = $this->carpetaTemporal() . '-publicado-nuevo';
        $this->crearFichero('sitio.yml', "nombre: Prueba
url: https://ejemplo.com
despliegue:
  destino: carpeta
  ruta: {$publicado}
");

        try {
            $informe = (new Desplegador())->desplegar(Proyecto::abrir($this->carpetaTemporal()), simular: true);

            self::assertNotSame([], $informe->subidos);
            self::assertDirectoryDoesNotExist($publicado, 'Simular no toca el destino, tampoco para crearlo');

            (new Desplegador())->desplegar(Proyecto::abrir($this->carpetaTemporal()));

            self::assertFileExists("{$publicado}/index.html");
        } finally {
            self::borrarCarpeta($publicado);
        }
    }

    #[Test]
    public function unaCarpetaDentroDelProyectoNoSeCreaNiSeUsa(): void
    {
        $this->crearSitio();
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: publicado/../contenido/copia\n");

        try {
            (new Desplegador())->desplegar(Proyecto::abrir($this->carpetaTemporal()));
            self::fail('Tenía que fallar');
        } catch (ErrorDeProyecto $error) {
            self::assertStringContainsString('La carpeta de despliegue no puede estar dentro del proyecto', $error->getMessage());
        }

        self::assertDirectoryDoesNotExist($this->carpetaTemporal() . '/contenido/copia');
    }

    #[Test]
    public function conTodoElPresupuestoDeTiempoSeIgnoraYSeSubeTodoEnUnaLlamada(): void
    {
        $this->crearSitio();

        $informe = $this->desplegar(todo: true, segundos: 0.0);

        self::assertTrue($informe->completo, 'Repartido no terminaría nunca: cada llamada volvería a empezar por el primer fichero');
        self::assertSame(0, $informe->pendientes);
        self::assertSame(['css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'], $informe->subidos);
    }

    #[Test]
    public function conUnPresupuestoDeTiempoSubeUnFicheroPorLlamadaYAlFinalEstaTodo(): void
    {
        $this->crearSitio();
        $subidos = [];
        $llamadas = 0;

        do {
            $informe = $this->desplegar(segundos: 0.0);
            $llamadas++;
            $subidos = [...$subidos, ...$informe->subidos];

            if (!$informe->completo) {
                self::assertCount(1, $informe->subidos, 'Cada llamada hace al menos un fichero, y con el tiempo agotado, solo uno');
                self::assertGreaterThan(0, $informe->pendientes);
                self::assertStringContainsString(' por desplegar.', $informe->resumen());
            }
        } while (!$informe->completo && $llamadas < 20);

        self::assertTrue($informe->completo);
        self::assertSame(0, $informe->pendientes);
        self::assertSame(4, $llamadas);
        self::assertSame(['css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'], $subidos);
        self::assertSame(
            ['css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'],
            array_keys(Manifiesto::leer($this->destino->ficheros[Manifiesto::FICHERO])->ficheros()),
        );
        self::assertSame([Manifiesto::FICHERO, 'css/a.css', 'index.html', 'otra/index.html', 'sitemap.xml'], array_keys($this->ordenados()));
    }

    #[Test]
    public function unDespliegueEnLotesDejaElDestinoComoUnoDeGolpeTambienConLosBorrados(): void
    {
        $this->crearSitio();
        $this->desplegar();
        unlink($this->carpetaTemporal() . '/contenido/otra.md');
        unlink($this->carpetaTemporal() . '/publico/css/a.css');
        $this->crearFichero('contenido/index.md', "---
titulo: Inicio
---
Hola, cambiado");

        $deGolpe = new DestinoEnMemoria();
        $deGolpe->ficheros = $this->destino->ficheros;
        $this->desplegar(destino: $deGolpe);

        $subidos = $borrados = [];
        $llamadas = 0;

        do {
            $informe = $this->desplegar(segundos: 0.0);
            $llamadas++;
            $subidos = [...$subidos, ...$informe->subidos];
            $borrados = [...$borrados, ...$informe->borrados];
        } while (!$informe->completo && $llamadas < 20);

        self::assertTrue($informe->completo);
        self::assertSame(4, $llamadas, 'Dos ficheros que cambian, dos que se borran');
        self::assertSame(['index.html', 'sitemap.xml'], $subidos);
        self::assertSame(['css/a.css', 'otra/index.html'], $borrados);
        self::assertSame($deGolpe->ficheros, $this->destino->ficheros);
    }

    #[Test]
    public function conUnaCompilacionYaHechaNoSeVuelveACompilar(): void
    {
        $this->crearSitio();
        $compilado = (new CompiladorEnProceso())->compilar(Proyecto::abrir($this->carpetaTemporal()));
        $noCompila = new class implements Compilador {
            public function compilar(Proyecto $proyecto): Informe
            {
                throw new \LogicException('No tenía que compilar');
            }
        };

        $informe = $this->desplegar(compilado: $compilado, compilador: $noCompila);

        self::assertSame($compilado, $informe->compilacion);
        self::assertSame(4, count($informe->subidos));
        self::assertTrue($informe->completo);
    }

    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: /publicado\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola");
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nOtra");
        $this->crearFichero('publico/css/a.css', 'a{}');
    }

    private function desplegar(bool $simular = false, bool $todo = false, ?float $segundos = null, ?Informe $compilado = null, ?Compilador $compilador = null, ?DestinoEnMemoria $destino = null): \Ehundu\Despliegue\InformeDeDespliegue
    {
        return (new Desplegador($compilador ?? new CompiladorEnProceso(), fn () => $destino ?? $this->destino))
            ->desplegar(Proyecto::abrir($this->carpetaTemporal()), $simular, $todo, segundos: $segundos, compilado: $compilado);
    }

    /**
     * @return array<string, string>
     */
    private function ordenados(): array
    {
        $ficheros = $this->destino->ficheros;
        ksort($ficheros, SORT_STRING);

        return $ficheros;
    }

    private static function borrarCarpeta(string $ruta): void
    {
        if (!is_dir($ruta)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $fichero) {
            $fichero->isDir() ? rmdir($fichero->getPathname()) : unlink($fichero->getPathname());
        }

        rmdir($ruta);
    }
}
