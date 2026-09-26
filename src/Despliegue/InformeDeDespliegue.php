<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\Aviso;
use Ehundu\Informe;

/**
 * Lo que devuelve un despliegue, o una simulación de despliegue.
 */
final readonly class InformeDeDespliegue
{
    /**
     * @param Informe      $compilacion la compilación que se ha hecho antes
     * @param string       $destino     adónde, sin secretos
     * @param list<string> $subidos     lo que se ha subido, o se subiría
     * @param int          $bytes       lo que pesa todo eso
     * @param list<string> $borrados    lo que se ha borrado, o se borraría
     * @param int          $sinCambios  lo que ya estaba como debía
     * @param list<Aviso>  $avisos      los del despliegue; los de la compilación van en ella
     */
    public function __construct(
        public Informe $compilacion,
        public string $destino,
        public array $subidos,
        public int $bytes,
        public array $borrados,
        public int $sinCambios,
        public array $avisos,
        public float $segundos,
        public bool $simulado = false,
    ) {
    }

    /**
     * «Desplegado en ftps://…: 12 ficheros subidos (1,2 MB), 3 borrados y
     * 290 sin cambios en 8,40 s.»
     */
    public function resumen(): string
    {
        $segundos = number_format($this->segundos, 2, ',', '.');

        if ($this->subidos === [] && $this->borrados === []) {
            return "Nada que desplegar en {$this->destino}: "
                . ($this->sinCambios === 1 ? 'el único fichero está al día.' : "los {$this->sinCambios} ficheros están al día.");
        }

        $subidos = self::cuantos(count($this->subidos), 'fichero', 'ficheros') . ($this->bytes > 0 ? ' (' . self::peso($this->bytes) . ')' : '');
        $borrados = count($this->borrados);
        $igual = $this->sinCambios;

        if ($this->simulado) {
            $partes = array_filter([
                $this->subidos !== [] ? "se subirían {$subidos}" : null,
                $borrados > 0 ? 'se borrarían ' . self::cuantos($borrados, 'fichero', 'ficheros') : null,
                $igual > 0 ? self::cuantos($igual, 'fichero sigue', 'ficheros siguen') . ' igual' : null,
            ]);

            return "Simulación en {$this->destino}: " . self::enumerar($partes) . '. No se ha tocado nada.';
        }

        $partes = array_filter([
            $this->subidos !== [] ? "{$subidos} " . (count($this->subidos) === 1 ? 'subido' : 'subidos') : null,
            $borrados > 0 ? self::cuantos($borrados, 'borrado', 'borrados') : null,
            $igual > 0 ? "{$igual} sin cambios" : null,
        ]);

        return "Desplegado en {$this->destino}: " . self::enumerar($partes) . " en {$segundos} s.";
    }

    private static function cuantos(int $numero, string $uno, string $varios): string
    {
        return $numero === 1 ? "1 {$uno}" : "{$numero} {$varios}";
    }

    private static function peso(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => "{$bytes} B",
            $bytes < 1024 * 1024 => number_format($bytes / 1024, 0, ',', '.') . ' kB',
            default => number_format($bytes / 1024 / 1024, 1, ',', '.') . ' MB',
        };
    }

    /**
     * @param array<int, string> $partes
     */
    private static function enumerar(array $partes): string
    {
        $partes = array_values($partes);

        return count($partes) < 2 ? implode('', $partes) : implode(', ', array_slice($partes, 0, -1)) . ' y ' . $partes[array_key_last($partes)];
    }
}
