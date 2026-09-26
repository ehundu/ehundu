<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

/**
 * Publicar en una carpeta local: la raíz de un servidor web de la propia
 * máquina, una carpeta sincronizada, un disco de red. Una ruta relativa se
 * cuenta desde la raíz del proyecto. La carpeta no puede estar dentro del
 * proyecto: el motor solo escribe en él dentro de `salida/`.
 */
final class Carpeta implements Destino
{
    private string $raiz;

    /**
     * @throws ErrorDeProyecto si la carpeta está dentro del proyecto o no se puede crear
     */
    public function __construct(string $ruta, Proyecto $proyecto)
    {
        $ruta = str_replace('\\', '/', $ruta);
        $completa = self::normalizar(preg_match('#^(/|[A-Za-z]:/)#', $ruta) === 1 ? $ruta : "{$proyecto->raiz}/{$ruta}");
        $proyectoReal = str_replace('\\', '/', (string) realpath($proyecto->raiz));
        $dentroDelProyecto = new ErrorDeProyecto(
            "La carpeta de despliegue no puede estar dentro del proyecto: {$ruta}. Ehundu solo escribe en el proyecto dentro de salida/",
            Proyecto::SITIO,
        );

        // Se comprueba antes de crear nada, y otra vez después por si hay enlaces.
        if (self::dentro($completa, $proyectoReal) || self::dentro($completa, self::normalizar($proyecto->raiz))) {
            throw $dentroDelProyecto;
        }

        if (!is_dir($completa) && !@mkdir($completa, 0777, true) && !is_dir($completa)) {
            throw new ErrorDeProyecto("No se puede crear la carpeta de despliegue {$ruta}", Proyecto::SITIO);
        }

        $this->raiz = str_replace('\\', '/', (string) realpath($completa));

        if (self::dentro($this->raiz, $proyectoReal)) {
            throw $dentroDelProyecto;
        }
    }

    public function leer(string $ruta): ?string
    {
        $completa = "{$this->raiz}/{$ruta}";

        if (!is_file($completa)) {
            return null;
        }

        $contenido = @file_get_contents($completa);

        if ($contenido === false) {
            throw new ErrorDeProyecto("No se puede leer {$ruta} en la carpeta de despliegue");
        }

        return $contenido;
    }

    public function subir(string $ruta, string $origen): void
    {
        $this->preparar($ruta);

        if (!@copy($origen, "{$this->raiz}/{$ruta}")) {
            throw new ErrorDeProyecto("No se puede copiar {$ruta} a la carpeta de despliegue");
        }
    }

    public function escribir(string $ruta, string $contenido): void
    {
        $this->preparar($ruta);

        if (@file_put_contents("{$this->raiz}/{$ruta}", $contenido) === false) {
            throw new ErrorDeProyecto("No se puede escribir {$ruta} en la carpeta de despliegue");
        }
    }

    public function borrar(string $ruta): void
    {
        $completa = "{$this->raiz}/{$ruta}";

        if (is_file($completa) && !@unlink($completa)) {
            throw new ErrorDeProyecto("No se puede borrar {$ruta} de la carpeta de despliegue");
        }
    }

    public function quitarCarpeta(string $ruta): void
    {
        @rmdir("{$this->raiz}/{$ruta}");
    }

    public function cerrar(): void
    {
    }

    private function preparar(string $ruta): void
    {
        $carpeta = dirname("{$this->raiz}/{$ruta}");

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0777, true) && !is_dir($carpeta)) {
            throw new ErrorDeProyecto("No se puede crear la carpeta de {$ruta} en la carpeta de despliegue");
        }
    }

    /**
     * Una ruta absoluta sin «.» ni «..», sin mirar el disco.
     */
    private static function normalizar(string $ruta): string
    {
        $ruta = str_replace('\\', '/', $ruta);
        $unidad = preg_match('#^[A-Za-z]:#', $ruta) === 1 ? substr($ruta, 0, 2) : '';
        $partes = [];

        foreach (explode('/', substr($ruta, strlen($unidad))) as $parte) {
            if ($parte === '..') {
                array_pop($partes);
            } elseif ($parte !== '' && $parte !== '.') {
                $partes[] = $parte;
            }
        }

        return $unidad . '/' . implode('/', $partes);
    }

    private static function dentro(string $ruta, string $carpeta): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $ruta = strtolower($ruta);
            $carpeta = strtolower($carpeta);
        }

        return $ruta === $carpeta || str_starts_with($ruta, rtrim($carpeta, '/') . '/');
    }
}
