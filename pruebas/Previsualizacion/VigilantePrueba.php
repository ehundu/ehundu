<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Previsualizacion;

use Ehundu\Previsualizacion\Vigilante;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VigilantePrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function detectaUnFicheroCambiadoOUnoNuevo(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', 'Inicio');
        $vigilante = new Vigilante(Proyecto::abrir($this->carpetaTemporal()));

        self::assertFalse($vigilante->haCambiado());

        $this->crearFichero('contenido/index.md', 'Inicio cambiado');
        self::assertTrue($vigilante->haCambiado());
        self::assertFalse($vigilante->haCambiado());

        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        self::assertTrue($vigilante->haCambiado());

        unlink($this->carpetaTemporal() . '/plantillas/pagina.twig');
        self::assertTrue($vigilante->haCambiado());
    }

    #[Test]
    public function noMiraSalidaNiLoOculto(): void
    {
        $this->crearSitioMinimo();
        $vigilante = new Vigilante(Proyecto::abrir($this->carpetaTemporal()));

        $this->crearFichero('salida/index.html', 'generado');
        $this->crearFichero('.git/HEAD', 'ref');
        $this->crearFichero('contenido/.borrador.md.swp', 'editor');

        self::assertFalse($vigilante->haCambiado());
    }
}
