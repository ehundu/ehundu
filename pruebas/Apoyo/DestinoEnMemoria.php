<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Apoyo;

use Ehundu\Despliegue\Destino;
use Ehundu\ErrorDeProyecto;

/**
 * Un destino de despliegue que guarda los ficheros en memoria y apunta cada
 * operación. Puede fallar a propósito al subir un fichero concreto.
 */
final class DestinoEnMemoria implements Destino
{
    /** @var array<string, string> */
    public array $ficheros = [];

    /** @var list<string> «subir ruta», «borrar ruta», «quitar carpeta»… en orden */
    public array $operaciones = [];

    public ?string $fallarAlSubir = null;

    public bool $cerrado = false;

    public function leer(string $ruta): ?string
    {
        return $this->ficheros[$ruta] ?? null;
    }

    public function subir(string $ruta, string $origen): void
    {
        if ($ruta === $this->fallarAlSubir) {
            throw new ErrorDeProyecto("No se puede subir {$ruta} (fallo de prueba)");
        }

        $this->ficheros[$ruta] = (string) file_get_contents($origen);
        $this->operaciones[] = "subir {$ruta}";
    }

    public function escribir(string $ruta, string $contenido): void
    {
        $this->ficheros[$ruta] = $contenido;
        $this->operaciones[] = "escribir {$ruta}";
    }

    public function borrar(string $ruta): void
    {
        unset($this->ficheros[$ruta]);
        $this->operaciones[] = "borrar {$ruta}";
    }

    public function quitarCarpeta(string $ruta): void
    {
        $this->operaciones[] = "quitar {$ruta}";
    }

    public function cerrar(): void
    {
        $this->cerrado = true;
    }

    /**
     * Las operaciones que no son guardar el manifiesto.
     *
     * @return list<string>
     */
    public function cambios(): array
    {
        return array_values(array_filter($this->operaciones, fn (string $operacion) => !str_starts_with($operacion, 'escribir ')));
    }
}
