<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\CompiladorEnProceso;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use Ehundu\Pruebas\Apoyo\Png;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El .phar que construye herramientas/construir-phar.php compila igual que
 * el motor usado como biblioteca. El proyecto de prueba usa lo que vive dentro
 * del paquete: las plantillas de los atajos de recursos/ y las dependencias.
 */
final class PharPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function elPharCompilaIgualQueLaBiblioteca(): void
    {
        if (getenv('COMPOSER_BINARY') === false && !self::hayComposer()) {
            self::markTestSkipped('Para construir el .phar hace falta Composer');
        }

        $phar = $this->carpetaTemporal() . '/ehundu.phar';
        [$codigo, $salida] = self::ejecutar([
            PHP_BINARY, '-d', 'phar.readonly=0', dirname(__DIR__) . '/herramientas/construir-phar.php', $phar,
        ]);
        self::assertSame(0, $codigo, "No se ha construido el .phar:\n{$salida}");

        $proyecto = $this->carpetaTemporal() . '/proyecto';
        $this->crearProyecto('proyecto');

        (new CompiladorEnProceso())->compilar(Proyecto::abrir($proyecto));
        rename("{$proyecto}/salida", "{$proyecto}/salida-biblioteca");

        [$codigo, $salida] = self::ejecutar([PHP_BINARY, $phar, 'compilar', $proyecto]);
        self::assertSame(0, $codigo, "El .phar no ha compilado:\n{$salida}");
        self::assertStringStartsWith('Compilado: 2 páginas', $salida);

        $esperados = self::ficheros("{$proyecto}/salida-biblioteca");
        self::assertSame(array_keys($esperados), array_keys(self::ficheros("{$proyecto}/salida")));

        foreach ($esperados as $ruta => $contenido) {
            self::assertSame($contenido, file_get_contents("{$proyecto}/salida/{$ruta}"), $ruta);
        }

        self::assertStringContainsString('youtube-nocookie.com', $esperados['index.html'], 'El atajo video sale de recursos/');
        self::assertStringContainsString('width="4" height="3"', $esperados['index.html']);
    }

    private function crearProyecto(string $carpeta): void
    {
        $ficheros = [
            'sitio.yml' => "nombre: Prueba\nurl: https://ejemplo.com\nfeed:\n  coleccion: blog\n",
            'datos/cliente.yml' => "nombre: La Esquina\n",
            'plantillas/pagina.twig' => "<title>{{ pagina.titulo }}</title>{{ css('estilo.css') }}<style>{{ css() }}</style>"
                . "{{ svg('logo.svg') }}{{ pagina.contenido }}{% for a in coleccion('blog') %}<a href=\"{{ a.url }}\">{{ a.titulo|slug }}</a>{% endfor %}",
            'contenido/index.md' => "---\ntitulo: Inicio\n---\n# Hola, [dato clave=\"cliente.nombre\"]\n\n![Una foto](/foto.png)\n\n"
                . "[video url=\"https://www.youtube.com/watch?v=aIVjKQaYiL8\" titulo=\"Visita\"]\n",
            'contenido/blog/uno.md' => "---\ntitulo: Árbol de otoño\nfecha: 2025-10-01\netiquetas: [blog]\n---\nUno.\n",
            'publico/estilo.css' => "body{margin:0}\n",
            'publico/logo.svg' => "<?xml version=\"1.0\"?>\n<svg></svg>\n",
            'publico/foto.png' => Png::de(4, 3),
        ];

        foreach ($ficheros as $ruta => $contenido) {
            $this->crearFichero("{$carpeta}/{$ruta}", $contenido);
        }
    }

    /**
     * @param list<string> $orden
     *
     * @return array{int, string} el código de salida y lo que ha escrito
     */
    private static function ejecutar(array $orden): array
    {
        $proceso = proc_open($orden, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $tuberias);
        self::assertIsResource($proceso);
        $salida = (string) stream_get_contents($tuberias[1]);
        fclose($tuberias[1]);

        return [proc_close($proceso), $salida];
    }

    /**
     * @return array<string, string> el contenido de cada fichero, por su ruta
     */
    private static function ficheros(string $carpeta): array
    {
        $ficheros = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $fichero) {
            $ruta = substr(str_replace('\\', '/', $fichero->getPathname()), strlen($carpeta) + 1);
            $ficheros[$ruta] = (string) file_get_contents($fichero->getPathname());
        }

        ksort($ficheros);

        return $ficheros;
    }

    private static function hayComposer(): bool
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $carpeta) {
            foreach (['composer', 'composer.bat', 'composer.phar'] as $nombre) {
                if (is_file("{$carpeta}/{$nombre}")) {
                    return true;
                }
            }
        }

        return false;
    }
}
