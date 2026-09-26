<?php

declare(strict_types=1);

namespace Ehundu\Previsualizacion;

/**
 * Una respuesta HTTP de la previsualización: un contenido en memoria, un
 * fichero del proyecto que se envía tal cual, o una redirección.
 */
final readonly class Respuesta
{
    /**
     * @param string|null $cuerpo    el contenido, si está en memoria
     * @param string|null $fichero   ruta absoluta del fichero que se envía, si no
     * @param string|null $ubicacion adónde redirige
     */
    public function __construct(
        public int $estado,
        public string $tipo,
        public ?string $cuerpo = null,
        public ?string $fichero = null,
        public ?string $ubicacion = null,
    ) {
    }

    public static function texto(int $estado, string $texto): self
    {
        return new self($estado, 'text/plain; charset=utf-8', $texto);
    }

    public static function redireccion(string $ubicacion): self
    {
        return new self(301, 'text/plain; charset=utf-8', "Se ha movido a {$ubicacion}", ubicacion: $ubicacion);
    }
}
