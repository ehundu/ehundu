<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Despliegue\Configuracion;
use Ehundu\Despliegue\S3;
use Ehundu\ErrorDeProyecto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class S3Prueba extends TestCase
{
    /** @var list<array{metodo: string, url: string, cabeceras: array<string, string>, cuerpo: string}> */
    private array $peticiones = [];

    /** @var list<array{int, string}> */
    private array $respuestas = [];

    #[Test]
    public function subeConElCuboEnElServidorSuTipoYSuMd5(): void
    {
        $this->respuestas = [[200, '']];

        $this->s3()->escribir('blog/index.html', '<p>Hola</p>');

        $peticion = $this->peticiones[0];
        self::assertSame('PUT', $peticion['metodo']);
        self::assertSame('https://mi-sitio.s3.fr-par.scw.cloud/web/blog/index.html', $peticion['url']);
        self::assertSame('text/html; charset=utf-8', $peticion['cabeceras']['content-type']);
        self::assertSame(base64_encode(md5('<p>Hola</p>', true)), $peticion['cabeceras']['content-md5']);
        self::assertSame(hash('sha256', '<p>Hola</p>'), $peticion['cabeceras']['x-amz-content-sha256']);
        self::assertSame('20260926T100000Z', $peticion['cabeceras']['x-amz-date']);
        self::assertStringStartsWith(
            'AWS4-HMAC-SHA256 Credential=SCWEJEMPLO/20260926/fr-par/s3/aws4_request, SignedHeaders=content-md5;content-type;host;x-amz-content-sha256;x-amz-date, Signature=',
            $peticion['cabeceras']['authorization'],
        );
        self::assertSame('<p>Hola</p>', $peticion['cuerpo']);
    }

    #[Test]
    public function unCuboConPuntosVaEnLaRuta(): void
    {
        $this->respuestas = [[204, '']];

        $this->s3(cubo: 'www.ejemplo.com', ruta: '')->borrar('a b.css');

        self::assertSame('DELETE', $this->peticiones[0]['metodo']);
        self::assertSame('https://s3.fr-par.scw.cloud/www.ejemplo.com/a%20b.css', $this->peticiones[0]['url']);
        self::assertSame('s3.fr-par.scw.cloud', $this->peticiones[0]['cabeceras']['host']);
    }

    #[Test]
    public function leerAlgoQueNoExisteDaNull(): void
    {
        $this->respuestas = [[404, '<Error><Code>NoSuchKey</Code></Error>'], [200, '{"ehundu": 1}']];
        $s3 = $this->s3();

        self::assertNull($s3->leer('.ehundu.json'));
        self::assertSame('{"ehundu": 1}', $s3->leer('.ehundu.json'));
    }

    #[Test]
    public function unErrorDelServicioDiceQuePasa(): void
    {
        $this->respuestas = [[403, '<Error><Code>AccessDenied</Code><Message>Access Denied</Message></Error>']];

        $this->expectExceptionObject(new ErrorDeProyecto('No se puede subir index.html: el servicio responde 403 AccessDenied: Access Denied'));

        $this->s3()->escribir('index.html', 'x');
    }

    private function s3(string $cubo = 'mi-sitio', string $ruta = '/web/'): S3
    {
        $configuracion = new Configuracion(
            destino: 's3',
            servidor: 's3.fr-par.scw.cloud',
            usuario: 'SCWEJEMPLO',
            ruta: $ruta,
            region: 'fr-par',
            cubo: $cubo,
            clave: 'secreto-de-ejemplo',
        );

        return new S3(
            $configuracion,
            function (string $metodo, string $url, array $cabeceras, string $cuerpo): array {
                $this->peticiones[] = ['metodo' => $metodo, 'url' => $url, 'cabeceras' => $cabeceras, 'cuerpo' => $cuerpo];

                return array_shift($this->respuestas) ?? [500, ''];
            },
            fn () => new \DateTimeImmutable('2026-09-26T10:00:00Z'),
        );
    }
}
