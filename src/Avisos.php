<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Recoge los avisos que van saliendo durante una compilación.
 */
final class Avisos
{
    /** @var list<Aviso> */
    private array $avisos = [];

    public function registrar(string $mensaje, ?string $fichero = null, ?int $linea = null): void
    {
        $this->avisos[] = new Aviso($mensaje, $fichero, $linea);
    }

    /**
     * @return list<Aviso>
     */
    public function todos(): array
    {
        return $this->avisos;
    }
}
