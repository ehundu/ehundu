<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La carpeta de un proyecto. Los nombres de las carpetas son fijos y no se
 * configuran (formato §1).
 *
 * Las rutas se guardan siempre con barras normales, también en Windows, para
 * que los mensajes y el resultado no dependan del sistema.
 */
final readonly class Proyecto
{
    public const string CONTENIDO = 'contenido';
    public const string DATOS = 'datos';
    public const string PLANTILLAS = 'plantillas';
    public const string PARCIALES = 'parciales';
    public const string PUBLICO = 'publico';
    public const string SALIDA = 'salida';

    public const string SITIO = 'sitio.yml';

    private function __construct(
        public string $raiz,
    ) {
    }

    /**
     * @throws ErrorDeProyecto si la ruta no existe o no es una carpeta
     */
    public static function abrir(string $ruta): self
    {
        $raiz = realpath($ruta);

        if ($raiz === false) {
            throw new ErrorDeProyecto("No existe la carpeta del proyecto: {$ruta}");
        }

        if (!is_dir($raiz)) {
            throw new ErrorDeProyecto("La ruta del proyecto no es una carpeta: {$ruta}");
        }

        return new self(rtrim(str_replace('\\', '/', $raiz), '/'));
    }

    /**
     * Ruta absoluta de algo dentro del proyecto: `ruta('contenido', 'index.md')`.
     */
    public function ruta(string ...$partes): string
    {
        return implode('/', [$this->raiz, ...$partes]);
    }

    /**
     * Lee un fichero de texto del proyecto. Lo devuelve sin marca BOM y con
     * finales de línea LF, sea cual sea el sistema en que se escribió.
     *
     * @param string $fichero ruta relativa a la raíz del proyecto
     *
     * @throws ErrorDeProyecto si no se puede leer o no está en UTF-8
     */
    public function leerTexto(string $fichero): string
    {
        $texto = is_file($this->ruta($fichero)) ? @file_get_contents($this->ruta($fichero)) : false;

        if ($texto === false) {
            throw new ErrorDeProyecto('No se puede leer el fichero', $fichero);
        }

        if (str_starts_with($texto, "\u{FEFF}")) {
            $texto = substr($texto, 3);
        }

        if (preg_match('//u', $texto) !== 1) {
            throw new ErrorDeProyecto('El fichero no está en UTF-8', $fichero);
        }

        return str_replace(["\r\n", "\r"], "\n", $texto);
    }

    /**
     * Borra todo lo que hay dentro de `salida/` y deja la carpeta vacía. Es lo
     * único que el motor borra, y nunca sigue enlaces: un enlace dentro de
     * `salida/` se quita, pero no lo que hay al otro lado.
     *
     * @throws ErrorDeProyecto si `salida/` es un enlace o no se puede vaciar
     */
    public function vaciarSalida(): void
    {
        $salida = $this->ruta(self::SALIDA);

        if (is_link($salida)) {
            throw new ErrorDeProyecto('salida/ es un enlace a otra carpeta; el motor no la vacía');
        }

        if (!is_dir($salida)) {
            if (!@mkdir($salida, 0777, true) && !is_dir($salida)) {
                throw new ErrorDeProyecto('No se puede crear la carpeta salida/');
            }

            return;
        }

        foreach (scandir($salida) ?: [] as $nombre) {
            if ($nombre !== '.' && $nombre !== '..') {
                self::borrar("{$salida}/{$nombre}");
            }
        }
    }

    /**
     * Escribe un fichero dentro de `salida/`, con las carpetas que hagan falta.
     *
     * @param string $fichero ruta relativa a `salida/`, sin tramos `..`
     *
     * @throws ErrorDeProyecto si no se puede escribir
     */
    public function escribirEnSalida(string $fichero, string $contenido): void
    {
        if (preg_match('#(^|/)\.\.?(/|$)|\\\\#', $fichero) === 1 || str_starts_with($fichero, '/')) {
            throw new ErrorDeProyecto("No se escribe fuera de salida/: {$fichero}");
        }

        $completa = $this->ruta(self::SALIDA, $fichero);
        $carpeta = dirname($completa);

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0777, true) && !is_dir($carpeta)) {
            throw new ErrorDeProyecto('No se puede crear la carpeta', self::SALIDA . '/' . dirname($fichero));
        }

        if (@file_put_contents($completa, $contenido) === false) {
            throw new ErrorDeProyecto('No se puede escribir el fichero', self::SALIDA . "/{$fichero}");
        }
    }

    /**
     * Copia un fichero del proyecto a `salida/`, con las carpetas que hagan
     * falta.
     *
     * @param string $origen  ruta relativa a la raíz del proyecto
     * @param string $destino ruta relativa a `salida/`, sin tramos `..`
     *
     * @throws ErrorDeProyecto si no se puede copiar
     */
    public function copiarASalida(string $origen, string $destino): void
    {
        if (preg_match('#(^|/)\.\.?(/|$)|\\\\#', $destino) === 1 || str_starts_with($destino, '/')) {
            throw new ErrorDeProyecto("No se escribe fuera de salida/: {$destino}");
        }

        $completa = $this->ruta(self::SALIDA, $destino);
        $carpeta = dirname($completa);

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0777, true) && !is_dir($carpeta)) {
            throw new ErrorDeProyecto('No se puede crear la carpeta', self::SALIDA . '/' . dirname($destino));
        }

        if (!@copy($this->ruta($origen), $completa)) {
            throw new ErrorDeProyecto('No se puede copiar a ' . self::SALIDA . "/{$destino}", $origen);
        }

        // La copia conserva la fecha del original: así la próxima vez se sabe,
        // sin leerla, que no ha cambiado.
        @touch($completa, (int) filemtime($this->ruta($origen)));
    }

    /**
     * Deja `salida/` exactamente con estos ficheros: escribe los que han
     * cambiado, copia los que no están o son distintos, y borra lo que sobra.
     * Lo que ya está igual no se toca, así que conserva su fecha y un
     * despliegue que compare por fecha y tamaño no lo vuelve a subir.
     *
     * @param array<string, string> $escritos contenido de cada fichero, por ruta en `salida/`
     * @param array<string, string> $copias   origen de cada copia (relativo a la raíz), por ruta en `salida/`
     *
     * @return int cuántos ficheros se han escrito, copiado o borrado
     *
     * @throws ErrorDeProyecto si `salida/` es un enlace o algo no se puede escribir
     */
    public function sincronizarSalida(array $escritos, array $copias): int
    {
        $salida = $this->ruta(self::SALIDA);

        if (is_link($salida)) {
            throw new ErrorDeProyecto('salida/ es un enlace a otra carpeta; el motor no la toca');
        }

        if (!is_dir($salida) && !@mkdir($salida, 0777, true) && !is_dir($salida)) {
            throw new ErrorDeProyecto('No se puede crear la carpeta salida/');
        }

        $cambios = 0;
        $queda = array_fill_keys([...array_keys($escritos), ...array_keys($copias)], true);

        // Primero lo que sobra, por si un fichero pasa a ser carpeta o al revés.
        foreach (array_reverse($this->ficherosDe(self::SALIDA)) as $fichero) {
            if (!isset($queda[$fichero])) {
                self::borrar("{$salida}/{$fichero}");
                $cambios++;
            }
        }

        self::quitarCarpetasVacias($salida);

        foreach ($escritos as $fichero => $contenido) {
            $completa = "{$salida}/{$fichero}";

            if (!is_file($completa) || filesize($completa) !== strlen($contenido) || file_get_contents($completa) !== $contenido) {
                $this->escribirEnSalida($fichero, $contenido);
                $cambios++;
            }
        }

        foreach ($copias as $destino => $origen) {
            $completa = "{$salida}/{$destino}";
            $original = $this->ruta($origen);

            if (!is_file($completa) || filesize($completa) !== filesize($original) || filemtime($completa) !== filemtime($original)) {
                $this->copiarASalida($origen, $destino);
                $cambios++;
            }
        }

        return $cambios;
    }

    private static function quitarCarpetasVacias(string $carpeta): void
    {
        foreach (scandir($carpeta) ?: [] as $nombre) {
            $ruta = "{$carpeta}/{$nombre}";

            if ($nombre !== '.' && $nombre !== '..' && is_dir($ruta) && !is_link($ruta)) {
                self::quitarCarpetasVacias($ruta);

                if ((scandir($ruta) ?: []) === ['.', '..']) {
                    @rmdir($ruta);
                }
            }
        }
    }

    /**
     * Todos los ficheros de una carpeta del proyecto y sus subcarpetas,
     * también los que empiezan por punto, en orden.
     *
     * @return list<string> rutas relativas a esa carpeta
     */
    public function ficherosDe(string $carpeta): array
    {
        $raiz = $this->ruta($carpeta);

        if (!is_dir($raiz)) {
            return [];
        }

        $ficheros = [];
        $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));

        foreach ($recorrido as $fichero) {
            if ($fichero->isFile()) {
                $ficheros[] = substr(str_replace('\\', '/', $fichero->getPathname()), strlen($raiz) + 1);
            }
        }

        sort($ficheros, SORT_STRING);

        return $ficheros;
    }

    private static function borrar(string $ruta): void
    {
        if (is_link($ruta) || !is_dir($ruta)) {
            if (!@unlink($ruta) && !(is_dir($ruta) && @rmdir($ruta))) {
                throw new ErrorDeProyecto("No se puede borrar {$ruta} al vaciar salida/");
            }

            return;
        }

        foreach (scandir($ruta) ?: [] as $nombre) {
            if ($nombre !== '.' && $nombre !== '..') {
                self::borrar("{$ruta}/{$nombre}");
            }
        }

        if (!@rmdir($ruta)) {
            throw new ErrorDeProyecto("No se puede borrar {$ruta} al vaciar salida/");
        }
    }
}
