<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\Yaml;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class YamlPrueba extends TestCase
{
    #[Test]
    public function leeUnaSerieDeCampos(): void
    {
        self::assertSame(['titulo' => 'Hola', 'orden' => 2], Yaml::leerCampos("titulo: Hola\norden: 2", 'a.yml'));
    }

    #[Test]
    public function leeLasFechasSinComillasComoFechasEnUtc(): void
    {
        $fecha = Yaml::leerCampos('fecha: 2025-03-18', 'a.yml')['fecha'];

        self::assertInstanceOf(\DateTimeImmutable::class, $fecha);
        self::assertSame('2025-03-18 00:00:00 UTC', $fecha->format('Y-m-d H:i:s e'));
    }

    #[Test]
    public function unaFechaSinComillasQueNoExisteSeLeeComoTexto(): void
    {
        $campos = Yaml::leerCampos(
            "mes: 2026-13-01\ndia: 2026-01-32\nlista: [2026-13-01, 2026-05-01]\nhora: 2026-13-01T10:00:00Z\nbuena: 2026-05-01\n",
            'a.yml',
        );

        self::assertSame('2026-13-01', $campos['mes']);
        self::assertSame('2026-01-32', $campos['dia']);
        self::assertSame('2026-13-01', $campos['lista'][0]);
        self::assertInstanceOf(\DateTimeImmutable::class, $campos['lista'][1], 'las demás fechas siguen siendo fechas');
        self::assertSame('2026-13-01T10:00:00Z', $campos['hora']);
        self::assertInstanceOf(\DateTimeImmutable::class, $campos['buena']);
    }

    #[Test]
    public function unTextoVacioSonCeroCampos(): void
    {
        self::assertSame([], Yaml::leerCampos('', 'a.yml'));
        self::assertSame([], Yaml::leerCampos("# solo un comentario\n", 'a.yml'));
    }

    #[Test]
    public function explicaLosDosPuntosSinComillas(): void
    {
        $error = $this->error(fn () => Yaml::leerCampos('titulo: Novedades: otoño', 'contenido/a.md', 2));

        self::assertSame(
            'contenido/a.md:2: El YAML no es válido: hay dos puntos en un valor sin comillas; '
            . 'pon el valor entre comillas. Cerca de «titulo: Novedades: otoño».',
            $error->getMessage(),
        );
        self::assertSame('contenido/a.md', $error->fichero);
        self::assertSame(2, $error->linea);
    }

    #[Test]
    public function explicaLosValoresQueEmpiezanPorUnCaracterReservado(): void
    {
        self::assertSame(
            'a.yml:1: El YAML no es válido: un valor empieza por «@», que YAML reserva; pon el valor entre comillas. Cerca de «titulo: @hola».',
            $this->error(fn () => Yaml::leerCampos('titulo: @hola', 'a.yml'))->getMessage(),
        );
        self::assertStringContainsString(
            'un valor empieza por «!», que en YAML marca un tipo; pon el valor entre comillas',
            $this->error(fn () => Yaml::leerCampos('titulo: !hola', 'a.yml'))->getMessage(),
        );
        self::assertStringContainsString(
            'un valor empieza por «*», que en YAML es una referencia; pon el valor entre comillas',
            $this->error(fn () => Yaml::leerCampos('titulo: *hola', 'a.yml'))->getMessage(),
        );
    }

    #[Test]
    public function unTextoConSecretosNoSaleEnElError(): void
    {
        $error = $this->error(fn () => Yaml::leerCampos("despliegue:\n  clave: @Secreto", '.secretos.yml', secreto: true));

        self::assertSame(
            '.secretos.yml:2: El YAML no es válido: un valor empieza por un carácter que YAML reserva; '
            . 'pon el valor entre comillas. La línea no se enseña porque lleva secretos.',
            $error->getMessage(),
        );
        self::assertNull($error->getPrevious());
    }

    #[Test]
    public function situaElErrorEnLaLineaDelFicheroYNoEnLaDelTexto(): void
    {
        $error = $this->error(fn () => Yaml::leerCampos("a: 1\na: 2", 'contenido/a.md', 2));

        self::assertSame(3, $error->linea);
        self::assertStringContainsString('la clave «a» está repetida', $error->getMessage());
    }

    #[Test]
    public function rechazaUnaListaDondeSeEsperanCampos(): void
    {
        $error = $this->error(fn () => Yaml::leerCampos("- a\n- b", 'sitio.yml'));

        self::assertSame('sitio.yml:1: Tiene que ser una serie de campos «nombre: valor»', $error->getMessage());
    }

    #[Test]
    public function noConstruyeObjetosDePhp(): void
    {
        $error = $this->error(fn () => Yaml::leer('a: !php/object \'O:8:"stdClass":0:{}\'', 'datos/a.yml'));

        self::assertStringStartsWith('datos/a.yml:1: El YAML no es válido', $error->getMessage());
    }

    #[Test]
    public function situaCadaClaveEnSuLinea(): void
    {
        $yaml = "titulo: A\n# comentario\nfecha: 2025-01-01\netiquetas:\n  - a\n\"url\": /x/";

        self::assertSame(
            ['titulo' => 2, 'fecha' => 4, 'etiquetas' => 5, 'url' => 7],
            Yaml::lineasDeClaves($yaml, 2),
        );
    }

    private function error(\Closure $leer): ErrorDeProyecto
    {
        try {
            $leer();
        } catch (ErrorDeProyecto $error) {
            return $error;
        }

        self::fail('Se esperaba un ErrorDeProyecto');
    }
}
