<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Recoge los avisos que van saliendo durante una compilación. Un aviso
 * idéntico a otro ya registrado no se repite: un parcial compartido por todas
 * las páginas avisaría una vez por página.
 */
final class Avisos
{
    /** @var array<string, Aviso> */
    private array $avisos = [];

    public function registrar(string $mensaje, ?string $fichero = null, ?int $linea = null): void
    {
        $aviso = new Aviso($mensaje, $fichero, $linea);
        $this->avisos[(string) $aviso] ??= $aviso;
    }

    /**
     * @return list<Aviso>
     */
    public function todos(): array
    {
        return array_values($this->avisos);
    }
}
