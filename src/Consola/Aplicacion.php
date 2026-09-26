<?php

declare(strict_types=1);

namespace Ehundu\Consola;

use Ehundu\Compilador;
use Ehundu\ErrorDeProyecto;
use Ehundu\Previsualizacion\Servidor;
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
          ehundu servir [carpeta]     Previsualiza el proyecto en el navegador y
                                      lo recompila cuando cambia algo. No
                                      escribe en salida/.
                --puerto=8000         El puerto en el que escucha.
                --borradores          Enseña también los borradores y las
                                      páginas que aún no se publican.
                --completo            Rehace todo el sitio en cada cambio, en
                                      lugar de solo lo que le afecta.
          ehundu --ayuda              Muestra esta ayuda.

        TXT;

    /** @var \Closure(Proyecto, int, bool, bool): int */
    private \Closure $servir;

    /**
     * @param resource                              $salida  donde se escribe el resultado
     * @param resource                              $errores donde se escriben errores y avisos
     * @param (\Closure(Proyecto, int, bool, bool): int)|null $servir arranca la previsualización con el
     *                                                                 proyecto, el puerto, si lleva borradores
     *                                                                 y si rehace todo en cada cambio
     */
    public function __construct(
        private readonly Compilador $compilador,
        private $salida,
        private $errores,
        ?\Closure $servir = null,
    ) {
        $this->servir = $servir ?? fn (Proyecto $proyecto, int $puerto, bool $conBorradores, bool $completo) => (new Servidor(
            $proyecto,
            $puerto,
            $conBorradores,
            $this->salida,
            $this->errores,
            $completo,
        ))->servir();
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

        if ($orden !== 'compilar' && $orden !== 'servir') {
            $this->escribir($this->errores, "Orden desconocida: {$orden}\n\n" . self::AYUDA);

            return self::USO_INCORRECTO;
        }

        $carpetas = [];
        $puerto = Servidor::PUERTO_POR_DEFECTO;
        $conBorradores = false;
        $completo = false;

        foreach (array_slice($argumentos, 1) as $argumento) {
            if ($orden === 'servir' && preg_match('/^--puerto=(\d{1,5})$/', $argumento, $partes) === 1 && (int) $partes[1] >= 1 && (int) $partes[1] <= 65535) {
                $puerto = (int) $partes[1];
            } elseif ($orden === 'servir' && $argumento === '--borradores') {
                $conBorradores = true;
            } elseif ($orden === 'servir' && $argumento === '--completo') {
                $completo = true;
            } elseif (str_starts_with($argumento, '-')) {
                $this->escribir($this->errores, str_starts_with($argumento, '--puerto')
                    ? "El puerto tiene que ser un número entre 1 y 65535: {$argumento}\n"
                    : "Opción desconocida: {$argumento}\n");

                return self::USO_INCORRECTO;
            } else {
                $carpetas[] = $argumento;
            }
        }

        if (count($carpetas) > 1) {
            $this->escribir($this->errores, "«{$orden}» admite una sola carpeta; sobra: {$carpetas[1]}\n");

            return self::USO_INCORRECTO;
        }

        $ruta = $carpetas[0] ?? (getcwd() ?: '.');

        try {
            return $orden === 'compilar'
                ? $this->compilar(Proyecto::abrir($ruta))
                : ($this->servir)(Proyecto::abrir($ruta), $puerto, $conBorradores, $completo);
        } catch (ErrorDeProyecto $error) {
            $this->escribir($this->errores, "Error: {$error->getMessage()}\n");

            return self::FALLO;
        }
    }

    private function compilar(Proyecto $proyecto): int
    {
        $informe = $this->compilador->compilar($proyecto);

        foreach ($informe->avisos as $aviso) {
            $this->escribir($this->errores, "Aviso: {$aviso}\n");
        }

        $this->escribir($this->salida, $informe->resumen() . "\n");

        return self::EXITO;
    }

    /**
     * @param resource $destino
     */
    private function escribir($destino, string $texto): void
    {
        fwrite($destino, $texto);
    }
}
