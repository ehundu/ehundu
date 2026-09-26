<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Previsualizacion;

use Ehundu\ErrorDeProyecto;
use Ehundu\Previsualizacion\Servidor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServidorPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function avisaSiElPuertoEstaOcupado(): void
    {
        $this->crearSitioMinimo();
        $ocupado = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) substr((string) strrchr((string) stream_socket_get_name($ocupado, false), ':'), 1);

        try {
            $this->expectException(ErrorDeProyecto::class);
            $this->expectExceptionMessage("No se puede escuchar en el puerto {$puerto}");

            (new Servidor(Proyecto::abrir($this->carpetaTemporal()), $puerto, false, STDOUT, STDERR))->servir();
        } finally {
            fclose($ocupado);
        }
    }
}
