<?php

declare(strict_types=1);

namespace Ehundu\Consola;

use Ehundu\Compilador;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;

/**
 * La orden de consola. Es una envoltura fina: interpreta los argumentos,
 * llama a la biblioteca y escribe el resultado. Nada de la biblioteca
 * depende de esta clase.
 */
final class Aplicacion
{
    public const int EXITO = 0;
    public const int FALLO = 1;
    public const int USO_INCORRECTO = 2;

    private const string AYUDA = <<<'TXT'
        Uso:
          ehundu compilar [carpeta]   Compila el proyecto de esa carpeta (por
                                      defecto, la actual) en su carpeta salida/.
          ehundu --ayuda              Muestra esta ayuda.

        TXT;

    /**
     * @param resource $salida  donde se escribe el resultado
     * @param resource $errores donde se escriben errores y avisos
     */
    public function __construct(
        private readonly Compilador $compilador,
        private $salida,
        private $errores,
    ) {
    }

    /**
     * @param list<string> $argumentos los argumentos sin el nombre del programa
     *
     * @return int el código de salida
     */
    public function ejecutar(array $argumentos): int
    {
        $orden = $argumentos[0] ?? null;

        if ($orden === null) {
            $this->escribir($this->errores, self::AYUDA);

            return self::USO_INCORRECTO;
        }

        if (in_array($orden, ['--ayuda', '-h', '--help'], true)) {
            $this->escribir($this->salida, self::AYUDA);

            return self::EXITO;
        }

        if ($orden !== 'compilar') {
            $this->escribir($this->errores, "Orden desconocida: {$orden}\n\n" . self::AYUDA);

            return self::USO_INCORRECTO;
        }

        $resto = array_slice($argumentos, 1);

        foreach ($resto as $argumento) {
            if (str_starts_with($argumento, '-')) {
                $this->escribir($this->errores, "Opción desconocida: {$argumento}\n");

                return self::USO_INCORRECTO;
            }
        }

        if (count($resto) > 1) {
            $this->escribir($this->errores, "«compilar» admite una sola carpeta; sobra: {$resto[1]}\n");

            return self::USO_INCORRECTO;
        }

        return $this->compilar($resto[0] ?? (getcwd() ?: '.'));
    }

    private function compilar(string $ruta): int
    {
        try {
            $informe = $this->compilador->compilar(Proyecto::abrir($ruta));
        } catch (ErrorDeProyecto $error) {
            $this->escribir($this->errores, "Error: {$error->getMessage()}\n");

            return self::FALLO;
        }

        foreach ($informe->avisos as $aviso) {
            $this->escribir($this->errores, "Aviso: {$aviso}\n");
        }

        $this->escribir($this->salida, $this->resumen($informe));

        return self::EXITO;
    }

    private function resumen(Informe $informe): string
    {
        $paginas = $informe->paginas === 1 ? '1 página' : "{$informe->paginas} páginas";
        $paginas .= match ($informe->ficheros) {
            0 => '',
            1 => ' y 1 fichero',
            default => " y {$informe->ficheros} ficheros",
        };
        $segundos = number_format($informe->segundos, 2, ',', '.');
        $avisos = match (count($informe->avisos)) {
            0 => '',
            1 => ', 1 aviso',
            default => ', ' . count($informe->avisos) . ' avisos',
        };

        return "Compilado: {$paginas} en {$segundos} s{$avisos}.\n";
    }

    /**
     * @param resource $destino
     */
    private function escribir($destino, string $texto): void
    {
        fwrite($destino, $texto);
    }
}
