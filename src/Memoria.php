<?php

declare(strict_types=1);

namespace Ehundu;

use Ehundu\Plantillas\Registro;

/**
 * Lo que recuerda el constructor de la última construcción que salió bien,
 * para aprovecharlo en la siguiente (ver `Plan`). Son datos sin más: se
 * pueden serializar y guardar fuera del proceso.
 *
 * @internal
 */
final readonly class Memoria
{
    /**
     * @param string                                                  $raiz          la del proyecto construido
     * @param bool                                                    $conBorradores
     * @param Lectura                                                 $lectura       lo que se leyó del proyecto
     * @param array<string, string>                                   $huellas       las de `plantillas/`, `parciales/`
     *                                                                               y `publico/` (ver `Huellas`)
     * @param array<string, true>                                     $publicadas    rutas de las páginas publicadas
     * @param array<string, array{html: string, registro: Registro}> $cuerpos       cuerpos convertidos, por ruta
     * @param array<string, array{html: string, pdf: string|null, registro: Registro}> $paginas HTML y PDF, si lo tiene, de cada página escrita, por ruta
     */
    public function __construct(
        public string $raiz,
        public bool $conBorradores,
        public Lectura $lectura,
        public array $huellas,
        public array $publicadas,
        public array $cuerpos,
        public array $paginas,
    ) {
    }
}
