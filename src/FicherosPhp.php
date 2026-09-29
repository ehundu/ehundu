<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Los ficheros PHP no se copian a `salida/` (formato §9): un sitio de Ehundu
 * es estático, y uno de esos ficheros lo pondría a ejecutarse en el servidor.
 *
 * Cuenta cualquier extensión del nombre, no solo la última: con `AddHandler`
 * un servidor ejecuta también `foto.php.jpg`.
 *
 * @internal
 */
final class FicherosPhp
{
    private const string EXTENSIONES = '/^(?:php[3-8]?|pht|phtml|phar)$/i';

    /**
     * Si el nombre de un fichero tiene alguna extensión de PHP.
     */
    public static function es(string $ruta): bool
    {
        $nombre = basename(str_replace('\\', '/', $ruta));
        $partes = explode('.', $nombre);

        // La primera parte es el nombre, no una extensión; en `.php` está vacía.
        array_shift($partes);

        foreach ($partes as $parte) {
            if (preg_match(self::EXTENSIONES, $parte) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $fichero ruta relativa a la raíz del proyecto
     *
     * @throws ErrorDeProyecto si es un fichero PHP
     */
    public static function comprobar(string $fichero): void
    {
        if (self::es($fichero)) {
            throw new ErrorDeProyecto(
                'Es un fichero PHP y no se copia a salida/: un sitio de Ehundu es estático, y publicarlo lo pondría a ejecutarse en el servidor. Hay que quitarlo del proyecto (formato §9)',
                $fichero,
            );
        }
    }
}
