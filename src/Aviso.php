<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Algo que conviene corregir pero que no impide compilar: una fecha que no
 * es válida, un atajo desconocido. Lleva fichero y línea cuando se conocen.
 */
final readonly class Aviso implements \Stringable
{
    public function __construct(
        public string $mensaje,
        public ?string $fichero = null,
        public ?int $linea = null,
    ) {
    }

    public function __toString(): string
    {
        return self::conUbicacion($this->mensaje, $this->fichero, $this->linea);
    }

    /**
     * Antepone «fichero:línea: » al mensaje cuando se conocen.
     */
    public static function conUbicacion(string $mensaje, ?string $fichero, ?int $linea): string
    {
        if ($fichero === null) {
            return $mensaje;
        }

        $donde = $linea === null ? $fichero : "{$fichero}:{$linea}";

        return "{$donde}: {$mensaje}";
    }
}
