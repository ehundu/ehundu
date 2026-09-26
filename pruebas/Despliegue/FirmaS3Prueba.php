<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Despliegue\FirmaS3;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Con los ejemplos oficiales de AWS (la batería «aws4_testsuite», con sus
 * credenciales de ejemplo): si la firma sale igual, la calculamos bien.
 */
final class FirmaS3Prueba extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, array<string, string>, string, string}>
     */
    public static function ejemplosDeAws(): iterable
    {
        $comunes = ['Host' => 'example.amazonaws.com', 'X-Amz-Date' => '20150830T123600Z'];

        yield 'get-vanilla' => ['GET', '/', '', $comunes, '', 'host;x-amz-date', '5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31'];

        yield 'get-vanilla-query-unreserved' => [
            'GET',
            '/',
            '-._~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz=-._~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
            $comunes,
            '',
            'host;x-amz-date',
            '9c3e54bfcdf0b19771a7f523ee5669cdf59bc7cc0884027167c21bb143a40197',
        ];

        yield 'post-x-www-form-urlencoded' => [
            'POST',
            '/',
            '',
            ['Content-Type' => 'application/x-www-form-urlencoded', ...$comunes],
            'Param1=value1',
            'content-type;host;x-amz-date',
            'ff11897932ad3f4e8b18135d722051e5ac45fc38421b1da7b9d196a0fe09473a',
        ];
    }

    /**
     * @param array<string, string> $cabeceras
     */
    #[Test]
    #[DataProvider('ejemplosDeAws')]
    public function firmaComoLosEjemplosDeAws(string $metodo, string $ruta, string $consulta, array $cabeceras, string $cuerpo, string $firmadas, string $firma): void
    {
        $firmador = new FirmaS3('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', 'us-east-1', 'service');

        $autorizacion = $firmador->autorizacion($metodo, $ruta, $consulta, $cabeceras, hash('sha256', $cuerpo), new \DateTimeImmutable('2015-08-30T12:36:00Z'));

        self::assertSame(
            "AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/service/aws4_request, SignedHeaders={$firmadas}, Signature={$firma}",
            $autorizacion,
        );
    }

    #[Test]
    public function codificaCadaTramoDeLaRuta(): void
    {
        self::assertSame('blog/un%20art%C3%ADculo/index.html', FirmaS3::codificarRuta('blog/un artículo/index.html'));
    }
}
