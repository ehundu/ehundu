<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Apoyo;

use PHPUnit\Framework\Attributes\After;

/**
 * Da a cada prueba una carpeta temporal propia y la borra al terminar.
 */
trait CarpetaTemporal
{
    private ?string $carpetaTemporal = null;

    protected function carpetaTemporal(): string
    {
        if ($this->carpetaTemporal === null) {
            $ruta = sys_get_temp_dir() . '/ehundu-pruebas-' . bin2hex(random_bytes(6));
            mkdir($ruta, 0777, true);
            $this->carpetaTemporal = str_replace('\\', '/', realpath($ruta));
        }

        return $this->carpetaTemporal;
    }

    /**
     * Crea un fichero dentro de la carpeta temporal, con sus carpetas.
     */
    protected function crearFichero(string $ruta, string $contenido): void
    {
        $completa = $this->carpetaTemporal() . "/{$ruta}";

        if (!is_dir(dirname($completa))) {
            mkdir(dirname($completa), 0777, true);
        }

        file_put_contents($completa, $contenido);
    }

    /**
     * El proyecto más pequeño que se compila sin avisos: `sitio.yml` y una
     * carpeta `contenido/` vacía.
     */
    protected function crearSitioMinimo(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('contenido/.gitkeep', '');
    }

    #[After]
    protected function borrarCarpetaTemporal(): void
    {
        if ($this->carpetaTemporal !== null) {
            self::borrar($this->carpetaTemporal);
            $this->carpetaTemporal = null;
        }
    }

    private static function borrar(string $ruta): void
    {
        if (is_dir($ruta) && !is_link($ruta)) {
            foreach (scandir($ruta) as $nombre) {
                if ($nombre !== '.' && $nombre !== '..') {
                    self::borrar("{$ruta}/{$nombre}");
                }
            }
            rmdir($ruta);
        } else {
            unlink($ruta);
        }
    }
}
