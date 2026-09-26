<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\ErrorDeProyecto;

/**
 * Adónde se publica un sitio: una carpeta, un servidor FTP o SFTP, o un cubo
 * de S3. Las rutas son relativas a la raíz de lo publicado, con barras
 * normales y sin barra inicial: `blog/index.html`.
 *
 * Cada operación falla con un `ErrorDeProyecto` que dice qué no se ha podido
 * hacer y con qué fichero.
 */
interface Destino
{
    /**
     * El contenido de un fichero, o null si no existe.
     *
     * @throws ErrorDeProyecto si existe pero no se puede leer
     */
    public function leer(string $ruta): ?string;

    /**
     * Sube un fichero local, creando las carpetas que falten.
     */
    public function subir(string $ruta, string $origen): void;

    /**
     * Escribe un fichero con ese contenido, creando las carpetas que falten.
     */
    public function escribir(string $ruta, string $contenido): void;

    /**
     * Borra un fichero. Si ya no existe, no hace nada.
     */
    public function borrar(string $ruta): void;

    /**
     * Quita una carpeta si está vacía. Si no lo está, o no se puede, no hace
     * nada: puede tener ficheros que no subió Ehundu.
     */
    public function quitarCarpeta(string $ruta): void;

    public function cerrar(): void;
}
