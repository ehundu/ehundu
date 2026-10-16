<?php

declare(strict_types=1);

namespace Ehundu\Lotes;

use Ehundu\Aviso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

/**
 * Por dónde va una compilación por lotes (formato §10.4), tal como se guarda en
 * `.ehundu/lote.json`. No lleva nada del sitio, solo cuenta: cada llamada vuelve
 * a leer el proyecto.
 *
 * @internal
 */
final class Estado
{
    public const string CARPETA = '.ehundu';
    public const string FICHERO = self::CARPETA . '/lote.json';
    public const string CERROJO = self::CARPETA . '/lote.lock';

    private const int VERSION = 1;

    /**
     * @param string                $huella   la del proyecto cuando empezó el lote (`Huella`)
     * @param string                $ahora    el momento con el que se decide qué está publicado, en ISO 8601
     * @param int                   $total    cuántas tareas tiene el lote
     * @param int                   $hechas   cuántas lleva
     * @param array<string, true>   $escritos los ficheros de `salida/` que han escrito las tareas de las páginas
     * @param array<string, string> $paquetes los ficheros de CSS y JS que enlazan las páginas, con la página que
     *                                        los pidió primero (para avisar si chocan con otro fichero)
     * @param list<Aviso>           $avisos   los que han dado las tareas, sin repetir
     * @param array{paginas: int, ficheros: int, segundos: float, avisos: list<Aviso>}|null $informe el resultado, cuando está completo
     */
    public function __construct(
        public string $huella,
        public string $ahora,
        public int $total,
        public int $hechas = 0,
        public array $escritos = [],
        public array $paquetes = [],
        public array $avisos = [],
        public float $segundos = 0.0,
        public ?array $informe = null,
    ) {
    }

    public function completo(): bool
    {
        return $this->informe !== null;
    }

    /**
     * El estado guardado, o null si no hay lote o lo que hay no vale (de otra
     * versión, o a medio escribir): en ese caso se empieza uno nuevo.
     */
    public static function leer(Proyecto $proyecto): ?self
    {
        $fichero = $proyecto->ruta(self::FICHERO);

        if (!is_file($fichero)) {
            return null;
        }

        $datos = json_decode((string) @file_get_contents($fichero), true);

        if (!is_array($datos) || ($datos['version'] ?? null) !== self::VERSION) {
            return null;
        }

        try {
            $informe = $datos['informe'] ?? null;

            return new self(
                (string) $datos['huella'],
                (string) $datos['ahora'],
                (int) $datos['total'],
                (int) $datos['hechas'],
                array_fill_keys(array_map('strval', $datos['escritos']), true),
                array_map('strval', $datos['paquetes']),
                self::avisosDe($datos['avisos']),
                (float) $datos['segundos'],
                $informe === null ? null : [
                    'paginas' => (int) $informe['paginas'],
                    'ficheros' => (int) $informe['ficheros'],
                    'segundos' => (float) $informe['segundos'],
                    'avisos' => self::avisosDe($informe['avisos']),
                ],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Guarda el estado entero, sin que se vea nunca a medias: se escribe a un
     * lado y se cambia de sitio.
     *
     * @throws ErrorDeProyecto si no se puede escribir
     */
    public function guardar(Proyecto $proyecto): void
    {
        self::prepararCarpeta($proyecto);

        $json = json_encode([
            'version' => self::VERSION,
            'huella' => $this->huella,
            'ahora' => $this->ahora,
            'total' => $this->total,
            'hechas' => $this->hechas,
            'escritos' => array_keys($this->escritos),
            'paquetes' => $this->paquetes,
            'avisos' => self::avisosAMatriz($this->avisos),
            'segundos' => $this->segundos,
            'informe' => $this->informe === null ? null : [
                'paginas' => $this->informe['paginas'],
                'ficheros' => $this->informe['ficheros'],
                'segundos' => $this->informe['segundos'],
                'avisos' => self::avisosAMatriz($this->informe['avisos']),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $fichero = $proyecto->ruta(self::FICHERO);
        $nuevo = $fichero . '.nuevo';

        if (@file_put_contents($nuevo, $json) === false || !@rename($nuevo, $fichero)) {
            @unlink($nuevo);

            throw new ErrorDeProyecto('No se puede guardar el estado de la compilación por lotes', self::FICHERO);
        }
    }

    /**
     * Olvida el lote que hubiera, completo o no.
     */
    public static function olvidar(Proyecto $proyecto): void
    {
        @unlink($proyecto->ruta(self::FICHERO));
    }

    /**
     * Crea `.ehundu/` con su propio `.gitignore`, para que ningún repositorio la
     * versione sin que cada sitio tenga que acordarse de ignorarla.
     *
     * @throws ErrorDeProyecto si no se puede crear
     */
    public static function prepararCarpeta(Proyecto $proyecto): void
    {
        $carpeta = $proyecto->ruta(self::CARPETA);

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0777, true) && !is_dir($carpeta)) {
            throw new ErrorDeProyecto('No se puede crear la carpeta ' . self::CARPETA . '/');
        }

        $ignorar = $carpeta . '/.gitignore';

        if (!is_file($ignorar) && @file_put_contents($ignorar, "*\n") === false) {
            throw new ErrorDeProyecto('No se puede escribir', self::CARPETA . '/.gitignore');
        }
    }

    /**
     * Pide el cerrojo de este proyecto para avanzar un lote, sin esperar.
     *
     * @return resource|null el cerrojo, que se suelta con `soltar()`; null si otra llamada lo tiene
     *
     * @throws ErrorDeProyecto si no se puede crear
     */
    public static function bloquear(Proyecto $proyecto)
    {
        self::prepararCarpeta($proyecto);
        $cerrojo = @fopen($proyecto->ruta(self::CERROJO), 'c');

        if ($cerrojo === false) {
            throw new ErrorDeProyecto('No se puede abrir el cerrojo de la compilación por lotes', self::CERROJO);
        }

        if (!flock($cerrojo, LOCK_EX | LOCK_NB)) {
            fclose($cerrojo);

            return null;
        }

        return $cerrojo;
    }

    /**
     * @param resource $cerrojo
     */
    public static function soltar($cerrojo): void
    {
        flock($cerrojo, LOCK_UN);
        fclose($cerrojo);
    }

    /**
     * @param list<Aviso> $avisos
     *
     * @return list<array{mensaje: string, fichero: string|null, linea: int|null}>
     */
    private static function avisosAMatriz(array $avisos): array
    {
        return array_map(fn (Aviso $a): array => ['mensaje' => $a->mensaje, 'fichero' => $a->fichero, 'linea' => $a->linea], $avisos);
    }

    /**
     * @param array<array-key, array{mensaje: string, fichero: string|null, linea: int|null}> $avisos
     *
     * @return list<Aviso>
     */
    private static function avisosDe(array $avisos): array
    {
        return array_values(array_map(
            fn (array $a): Aviso => new Aviso((string) $a['mensaje'], $a['fichero'] === null ? null : (string) $a['fichero'], $a['linea'] === null ? null : (int) $a['linea']),
            $avisos,
        ));
    }
}
