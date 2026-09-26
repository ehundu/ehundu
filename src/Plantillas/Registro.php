<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Aviso;

/**
 * Lo que ha usado una página, o el cuerpo de una página, al construirse:
 * plantillas, ficheros de `publico/`, colecciones y el contenido de otras
 * páginas; el CSS y el JS que ha declarado; y los avisos que ha dado. Es lo
 * que permite a la compilación incremental saber qué rehacer y repetir lo
 * que no rehace.
 *
 * @internal
 */
final class Registro
{
    /** @var array<string, true> nombres de plantillas y parciales, también los que se buscaron y no existían */
    public array $plantillas = [];

    /** @var array<string, true> rutas dentro de `publico/` */
    public array $publico = [];

    /** @var array<string, true> nombres de colecciones */
    public array $colecciones = [];

    /** @var array<string, true> rutas de páginas cuyo contenido se ha usado */
    public array $contenidos = [];

    /** @var list<string> */
    public array $css = [];

    /** @var list<string> */
    public array $js = [];

    /** @var list<Aviso> */
    public array $avisos = [];
}
