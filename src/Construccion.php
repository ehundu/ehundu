<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Un sitio construido en memoria, listo para escribirse en `salida/` o para
 * servirse tal cual desde la previsualización.
 *
 * @internal
 */
final readonly class Construccion
{
    /**
     * @param array<string, string> $escritos contenido de cada fichero que se genera
     *                                        (páginas, PDF, sitemap, feed y los paquetes de CSS y JS
     *                                        que enlazan las páginas), por ruta en `salida/`
     * @param array<string, string> $copias   origen de cada fichero que se copia tal cual,
     *                                        relativo a la raíz del proyecto, por ruta en `salida/`
     * @param int                   $paginas  cuántos de los escritos son páginas
     * @param list<Aviso>           $avisos
     * @param int|null              $rehechas cuántas de las páginas se han construido de nuevo; las
     *                                        demás se han aprovechado de la construcción anterior.
     *                                        Null si no se sabe: todas
     * @param bool                  $completa false si la construcción se ha cortado al llegar a su límite de
     *                                        tiempo (`Constructor::construir`): entonces `escritos` es solo lo que
     *                                        han escrito las páginas rehechas, `copias` y `avisos` están vacíos y
     *                                        `paginas` son las que lleva hechas, de `total`
     * @param int                   $total    cuántas páginas se escriben en total
     */
    public function __construct(
        public array $escritos,
        public array $copias,
        public int $paginas,
        public array $avisos,
        public ?int $rehechas = null,
        public bool $completa = true,
        public int $total = 0,
    ) {
    }
}
