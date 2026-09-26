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

    /** @var (\Closure(Aviso): void)|null */
    private ?\Closure $oyente = null;

    public function registrar(string $mensaje, ?string $fichero = null, ?int $linea = null): void
    {
        $aviso = new Aviso($mensaje, $fichero, $linea);
        $this->avisos[(string) $aviso] ??= $aviso;

        if ($this->oyente !== null) {
            ($this->oyente)($aviso);
        }
    }

    /**
     * Quien quiera saber de cada aviso que se registra, aunque esté repetido:
     * la compilación incremental, para saber qué avisos da cada página.
     *
     * @param \Closure(Aviso): void $oyente
     */
    public function escuchar(\Closure $oyente): void
    {
        $this->oyente = $oyente;
    }

    /**
     * @return list<Aviso>
     */
    public function todos(): array
    {
        return array_values($this->avisos);
    }
}
