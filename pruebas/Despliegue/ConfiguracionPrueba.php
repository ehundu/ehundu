<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Avisos;
use Ehundu\Despliegue\Configuracion;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfiguracionPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function juntaSitioYmlYLosSecretos(): void
    {
        $this->sitio("  destino: sftp\n  servidor: sftp.ejemplo.com\n  puerto: 2222\n  usuario: esquina\n  ruta: /www/\n  huella: SHA256:abc");
        $this->crearFichero('.secretos.yml', "despliegue:\n  clave: secreta\n");

        $configuracion = $this->leer();

        self::assertSame('sftp', $configuracion->destino);
        self::assertSame('sftp.ejemplo.com', $configuracion->servidor);
        self::assertSame(2222, $configuracion->puerto);
        self::assertSame('esquina', $configuracion->usuario);
        self::assertSame('/www/', $configuracion->ruta);
        self::assertSame('SHA256:abc', $configuracion->huella);
        self::assertSame('secreta', $configuracion->clave);
        self::assertSame('sftp://esquina@sftp.ejemplo.com:2222/www/', $configuracion->descripcion());
    }

    #[Test]
    public function losSecretosQueDaElProgramaGananALosDelFichero(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina");
        $this->crearFichero('.secretos.yml', "despliegue:\n  clave: la-del-fichero\n");

        self::assertSame('la-del-programa', $this->leer(['clave' => 'la-del-programa'])->clave);
    }

    #[Test]
    public function elCifradoEstaPuestoSalvoQueSeQuiteAProposito(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina");
        self::assertTrue($this->leer(['clave' => 'x'])->cifrado);
        self::assertSame('ftps://esquina@ftp.ejemplo.com', $this->leer(['clave' => 'x'])->descripcion());

        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina\n  cifrado: no");
        self::assertFalse($this->leer(['clave' => 'x'])->cifrado);
    }

    #[Test]
    public function unSecretoEnSitioYmlDetieneElDespliegue(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina\n  clave: a-la-vista");

        $this->expectExceptionObject(new ErrorDeProyecto(
            '«clave» es secreta y va en .secretos.yml, nunca en sitio.yml, que se comparte y se versiona',
            'sitio.yml',
            7,
        ));

        $this->leer();
    }

    #[Test]
    public function faltaLaSeccion(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");

        $this->expectExceptionMessage('sitio.yml: Falta la sección «despliegue», que dice adónde se publica el sitio');

        $this->leer();
    }

    #[Test]
    public function unDestinoQueNoExiste(): void
    {
        $this->sitio("  destino: netlify");

        $this->expectExceptionMessage('sitio.yml:4: «destino» tiene que ser carpeta, ftp, sftp o s3');

        $this->leer();
    }

    #[Test]
    public function dicenLoQueFalta(): void
    {
        $this->sitio("  destino: s3\n  servidor: s3.fr-par.scw.cloud");

        $this->expectExceptionMessage('sitio.yml: Para desplegar con el destino s3 falta «region», «cubo» y «usuario» en «despliegue»');

        $this->leer(['clave' => 'x']);
    }

    #[Test]
    public function sinClaveNoSeDespliega(): void
    {
        $this->sitio("  destino: sftp\n  servidor: sftp.ejemplo.com\n  usuario: esquina");

        $this->expectExceptionMessage('.secretos.yml: Falta la clave para desplegar: va en .secretos.yml, dentro de «despliegue», como «clave» o, si se entra con una clave privada, como «clavePrivada»');

        $this->leer();
    }

    #[Test]
    public function unSecretoMalEscritoNoSaleEnElError(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina");

        foreach (["clave: !Secreto#123", "clave: *Secreto#123", "clave: @Secreto#123", "clave: 'Secreto#123"] as $linea) {
            $this->crearFichero('.secretos.yml', "despliegue:\n  {$linea}\n");

            try {
                $this->leer();
                self::fail("Se esperaba un error con «{$linea}»");
            } catch (ErrorDeProyecto $error) {
                // Con «*», Symfony sitúa el error en la línea 1; la línea no es lo que se prueba aquí.
                self::assertMatchesRegularExpression('/^\.secretos\.yml:\d: El YAML no es válido/', $error->getMessage());
                self::assertStringEndsWith('. La línea no se enseña porque lleva secretos.', $error->getMessage());
                self::assertStringNotContainsString('Secreto', $error->getMessage());
                self::assertNull($error->getPrevious(), 'El error de Symfony lleva la línea dentro');
            }
        }
    }

    #[Test]
    public function unSecretoQueNoEsTextoPideComillas(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina");
        $this->crearFichero('.secretos.yml', "despliegue:\n  clave: true\n");

        $this->expectExceptionMessage('.secretos.yml:2: «clave» tiene que ser un texto; escribe el valor entre comillas simples');

        $this->leer();
    }

    #[Test]
    public function unSecretoEntreComillasSeLeeTalCual(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina");
        $this->crearFichero('.secretos.yml', "despliegue:\n  clave: '!a #b ''c'' 1e3'\n");

        self::assertSame("!a #b 'c' 1e3", $this->leer()->clave);
    }

    #[Test]
    public function conClavePrivadaNoHaceFaltaContrasena(): void
    {
        $this->sitio("  destino: sftp\n  servidor: sftp.ejemplo.com\n  usuario: esquina");
        $this->crearFichero('.secretos.yml', "despliegue:\n  clavePrivada: ~/.ssh/id_ed25519\n  frase: la-frase\n");

        $configuracion = $this->leer();

        self::assertNull($configuracion->clave);
        self::assertSame('~/.ssh/id_ed25519', $configuracion->clavePrivada);
        self::assertSame('la-frase', $configuracion->frase);
    }

    #[Test]
    public function avisaDeLoQueSobraYSigue(): void
    {
        $this->sitio("  destino: carpeta\n  ruta: ../publicado\n  huella: SHA256:abc\n  servdor: x");
        $this->crearFichero('.secretos.yml', "despliegue:\n  usuario: esquina\n");
        $avisos = new Avisos();

        Configuracion::leer(Proyecto::abrir($this->carpetaTemporal()), [], $avisos);

        self::assertSame([
            'sitio.yml:6: «huella» no se usa con el destino carpeta; se ignora',
            'sitio.yml:7: «servdor» no es un campo de «despliegue»; se ignora',
            '.secretos.yml:2: En .secretos.yml solo van «clave», «clavePrivada» y «frase»; «usuario» se ignora',
        ], array_map('strval', $avisos->todos()));
    }

    #[Test]
    public function unPuertoQueNoVale(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina\n  puerto: 99999");

        $this->expectExceptionMessage('sitio.yml:7: «puerto» tiene que ser un número entre 1 y 65535');

        $this->leer(['clave' => 'x']);
    }

    #[Test]
    public function unVolcadoNoEnsenaLasClaves(): void
    {
        $this->sitio("  destino: ftp\n  servidor: ftp.ejemplo.com\n  usuario: esquina");

        $volcado = print_r($this->leer(['clave' => 'muy-secreta']), true);

        self::assertStringNotContainsString('muy-secreta', $volcado);
        self::assertStringContainsString('(oculta)', $volcado);
    }

    private function sitio(string $despliegue): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\ndespliegue:\n{$despliegue}\n");
    }

    /**
     * @param array<string, string> $secretos
     */
    private function leer(array $secretos = []): Configuracion
    {
        return Configuracion::leer(Proyecto::abrir($this->carpetaTemporal()), $secretos);
    }
}
