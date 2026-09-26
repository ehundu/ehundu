<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Despliegue\Manifiesto;
use Ehundu\Despliegue\PlanDeDespliegue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlanDeDesplieguePrueba extends TestCase
{
    #[Test]
    public function subeLoNuevoYLoCambiadoYBorraLoQueSobra(): void
    {
        $anterior = new Manifiesto([
            'igual.css' => self::fichero('a'),
            'cambia.html' => self::fichero('b'),
            'sobra.html' => self::fichero('c'),
        ]);

        $plan = PlanDeDespliegue::trazar([
            'igual.css' => self::fichero('a'),
            'cambia.html' => self::fichero('b2'),
            'nuevo.jpg' => self::fichero('d'),
        ], $anterior);

        self::assertSame(['nuevo.jpg', 'cambia.html'], array_column($plan->subidas, 'ruta'));
        self::assertSame(['sobra.html'], $plan->borrados);
        self::assertSame(1, $plan->sinCambios);
    }

    #[Test]
    public function subeLosFicherosAntesQueLasPaginasYElSitemapYElFeedAlFinal(): void
    {
        $plan = PlanDeDespliegue::trazar([
            'sitemap.xml' => self::fichero('1'),
            'index.html' => self::fichero('2'),
            'feed.xml' => self::fichero('3'),
            'css/a.css' => self::fichero('4'),
            'blog/index.html' => self::fichero('5'),
            'img/b.jpg' => self::fichero('6'),
        ], null);

        self::assertSame(
            ['css/a.css', 'img/b.jpg', 'blog/index.html', 'index.html', 'feed.xml', 'sitemap.xml'],
            array_column($plan->subidas, 'ruta'),
        );
    }

    #[Test]
    public function sinManifiestoSeSubeTodoYNoSeBorraNada(): void
    {
        $plan = PlanDeDespliegue::trazar(['a.html' => self::fichero('a')], null);

        self::assertCount(1, $plan->subidas);
        self::assertSame([], $plan->borrados);
    }

    #[Test]
    public function conTodoSeSubeTodoPeroSeBorraIgual(): void
    {
        $anterior = new Manifiesto(['a.html' => self::fichero('a'), 'b.html' => self::fichero('b')]);

        $plan = PlanDeDespliegue::trazar(['a.html' => self::fichero('a')], $anterior, todo: true);

        self::assertSame(['a.html'], array_column($plan->subidas, 'ruta'));
        self::assertSame(['b.html'], $plan->borrados);
        self::assertSame(0, $plan->sinCambios);
    }

    #[Test]
    public function lasCarpetasQueSobranSonLasQueSeQuedanSinNadaDeEhundu(): void
    {
        $anterior = new Manifiesto([
            'blog/viejo/index.html' => self::fichero('a'),
            'blog/viejo/foto.jpg' => self::fichero('b'),
            'blog/nuevo/index.html' => self::fichero('c'),
            'tienda/index.html' => self::fichero('d'),
        ]);
        $ficheros = ['blog/nuevo/index.html' => self::fichero('c')];

        $plan = PlanDeDespliegue::trazar($ficheros, $anterior);

        self::assertSame(['blog/viejo', 'tienda'], $plan->carpetasQueSobran($ficheros));
    }

    /**
     * @return array{md5: string, tamano: int}
     */
    private static function fichero(string $contenido): array
    {
        return ['md5' => md5($contenido), 'tamano' => strlen($contenido)];
    }
}
