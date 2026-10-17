<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Campos;
use Ehundu\Constructor;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Un día sin hora, escrito sin comillas en cualquier sitio (un campo propio, `datos/`, `sitio.yml`), es ese día en la
 * zona horaria del sitio (formato §4): no su medianoche en UTC, que en una zona al oeste de UTC es el día anterior.
 */
final class FechasDelSitioPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function unDiaSueltoEnUnCampoPropioEnDatosYEnSitioYmlSeEscribeComoEsEnLaZonaDelSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nzonaHoraria: America/Mexico_City\ninicio: 2026-01-05\n");
        $this->crearFichero('datos/calendario.yml', "dia: 2026-10-17\nlista:\n  - dia: 2026-12-31\n");
        $this->crearFichero(
            'plantillas/pagina.twig',
            "{{ pagina.otra|fecha('Y-m-d') }}|{{ datos.calendario.dia|fecha('Y-m-d') }}|{{ datos.calendario.lista[0].dia|fecha('Y-m-d') }}|{{ sitio.inicio|fecha('Y-m-d') }}|{{ pagina.fecha|fecha('Y-m-d') }}\n",
        );
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\nfecha: 2025-03-19\notra: 2025-03-19\n---\nx\n");

        $construccion = (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), new \DateTimeImmutable('2026-10-20 12:00:00'));

        self::assertSame("2025-03-19|2026-10-17|2026-12-31|2026-01-05|2025-03-19\n", $construccion->escritos['index.html']);
    }

    #[Test]
    public function unaFechaConHoraQuedaComoElInstanteQueSeLeyo(): void
    {
        $zona = new \DateTimeZone('America/Mexico_City');

        $conHora = new \DateTimeImmutable('2026-10-17 10:30:00', new \DateTimeZone('UTC'));
        self::assertSame($conHora, Campos::diaDelSitio($conHora, $zona));

        $dia = Campos::diaDelSitio(new \DateTimeImmutable('2026-10-17 00:00:00', new \DateTimeZone('UTC')), $zona);
        self::assertInstanceOf(\DateTimeImmutable::class, $dia);
        self::assertSame('2026-10-17 00:00:00 America/Mexico_City', $dia->format('Y-m-d H:i:s e'));
    }

    #[Test]
    public function recorreListasYMapasYDejaLoDemasComoEsta(): void
    {
        $zona = new \DateTimeZone('Europe/Madrid');
        $dia = new \DateTimeImmutable('2026-10-17 00:00:00', new \DateTimeZone('UTC'));

        $resultado = Campos::diaDelSitio(['a' => [$dia, 'texto', 3], 'b' => null], $zona);

        self::assertIsArray($resultado);
        self::assertSame('Europe/Madrid', $resultado['a'][0]->getTimezone()->getName());
        self::assertSame(['texto', 3], array_slice($resultado['a'], 1));
        self::assertNull($resultado['b']);
    }
}
