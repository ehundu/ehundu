<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Los ficheros que el sistema o el control de versiones dejan en una carpeta y que no se copian a `salida/`
 * (formato §9, decisión 138): `.DS_Store`, `.git`, `.gitkeep`, `Thumbs.db` y `desktop.ini`. Una lista corta y
 * cerrada: el resto de `publico/` se copia entero, también lo que empieza por punto (un `.htaccess`).
 *
 * @internal
 */
final class Ignorados
{
    private const array NOMBRES = ['.ds_store', '.git', '.gitkeep', 'thumbs.db', 'desktop.ini'];

    /**
     * Si una ruta, relativa a su carpeta, es uno de esos ficheros o está dentro de uno de esos directorios (un
     * `.git` anidado). No distingue mayúsculas: Windows escribe `Thumbs.db` y `desktop.ini` como quiere.
     */
    public static function es(string $ruta): bool
    {
        foreach (explode('/', str_replace('\\', '/', $ruta)) as $parte) {
            if (in_array(strtolower($parte), self::NOMBRES, true)) {
                return true;
            }
        }

        return false;
    }
}
