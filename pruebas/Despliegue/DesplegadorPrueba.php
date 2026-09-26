<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\CompiladorEnProceso;
use Ehundu\Despliegue\Desplegador;
use Ehundu\Despliegue\Manifiesto;
use Ehundu\ErrorDeProyecto;
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

    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n  destino: carpeta\n  ruta: /publicado\n");
        $this->crearPlantillaMinima();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\nHola");
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\n---\nOtra");
        $this->crearFichero('publico/css/a.css', 'a{}');
    }

    private function desplegar(bool $simular = false, bool $todo = false): \Ehundu\Despliegue\InformeDeDespliegue
    {
        return (new Desplegador(new CompiladorEnProceso(), fn () => $this->destino))
            ->desplegar(Proyecto::abrir($this->carpetaTemporal()), $simular, $todo);
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
