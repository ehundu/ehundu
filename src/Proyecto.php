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
}
