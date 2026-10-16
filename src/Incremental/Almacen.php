<?php

declare(strict_types=1);

namespace Ehundu\Incremental;

use Ehundu\ErrorDeProyecto;
use Ehundu\Lotes\Estado;
use Ehundu\Memoria;
use Ehundu\Proyecto;

/**
 * Lo que recuerda la compilación incremental, guardado en `.ehundu/memoria.bin` para que lo aproveche otro
 * proceso (formato §10.5).
 *
 * El fichero lleva una línea con la marca, la versión de la memoria, la huella del motor que la escribió,
 * cuándo se guardó y cuántos bytes mide lo que sigue, y detrás la memoria serializada. Si no vale (no está, está
 * dañada o a medio escribir, es de otra versión de la memoria o del motor, o es de otro proyecto) no hay error:
 * se devuelve null y se hace una compilación completa.
 *
 * @internal
 */
final class Almacen
{
    public const string FICHERO = Estado::CARPETA . '/memoria.bin';
    public const string CERROJO = Estado::CARPETA . '/memoria.lock';
    public const string CURSO = Estado::CARPETA . '/memoria-curso.json';

    /** Cambia cuando cambia cómo se guarda la memoria */
    public const int VERSION = 1;

    private const string MARCA = 'EHUNDU-MEMORIA';

    /**
     * Las únicas clases que puede haber en una memoria guardada. Se cargan con esta lista para que un fichero
     * cambiado no pueda crear objetos de otras clases; si una clase nueva entra en la memoria y no está aquí,
     * `AlmacenPrueba` lo detecta.
     */
    public const array CLASES = [
        \DateTimeImmutable::class,
        \DateTimeZone::class,
        \Ehundu\Aviso::class,
        \Ehundu\Encabezados::class,
        \Ehundu\EnlacesExternos::class,
        \Ehundu\Idiomas::class,
        \Ehundu\Imagenes::class,
        \Ehundu\Lectura::class,
        \Ehundu\Memoria::class,
        \Ehundu\Pagina::class,
        \Ehundu\Plantillas\Registro::class,
        \Ehundu\Sitio::class,
    ];

    private static ?string $huella = null;

    /**
     * La memoria guardada y cuándo se guardó, o null si no hay o no vale para este proyecto y este motor.
     *
     * @return array{memoria: Memoria, guardada: int}|null
     */
    public static function cargar(Proyecto $proyecto): ?array
    {
        $fichero = $proyecto->ruta(self::FICHERO);

        if (!is_file($fichero)) {
            return null;
        }

        $gestor = @fopen($fichero, 'rb');

        if ($gestor === false) {
            return null;
        }

        try {
            $cabecera = fgets($gestor, 512);

            if ($cabecera === false) {
                return null;
            }

            $partes = explode(' ', rtrim($cabecera, "\n"));

            if (count($partes) !== 5 || $partes[0] !== self::MARCA || (int) $partes[1] !== self::VERSION || $partes[2] !== self::huellaDelMotor()) {
                return null;
            }

            $bytes = (int) $partes[4];
            $datos = $bytes > 0 ? stream_get_contents($gestor) : false;

            // Un fichero cortado (se agotó el espacio, o se mató el proceso al escribir) no vale
            if ($datos === false || strlen($datos) !== $bytes) {
                return null;
            }
        } finally {
            fclose($gestor);
        }

        try {
            $memoria = @unserialize($datos, ['allowed_classes' => self::CLASES]);
        } catch (\Throwable) {
            return null;
        }

        if (!$memoria instanceof Memoria || $memoria->raiz !== $proyecto->raiz) {
            return null;
        }

        return ['memoria' => $memoria, 'guardada' => (int) $partes[3]];
    }

    /**
     * Guarda la memoria sin que se vea nunca a medias: se escribe a un lado y se cambia de sitio.
     *
     * @throws ErrorDeProyecto si no se puede escribir
     */
    public static function guardar(Proyecto $proyecto, Memoria $memoria): void
    {
        Estado::prepararCarpeta($proyecto);

        $datos = serialize($memoria);
        $cabecera = implode(' ', [self::MARCA, self::VERSION, self::huellaDelMotor(), time(), strlen($datos)]) . "\n";
        $fichero = $proyecto->ruta(self::FICHERO);
        $nuevo = $fichero . '.nuevo';
        $gestor = @fopen($nuevo, 'wb');

        if ($gestor === false || fwrite($gestor, $cabecera) !== strlen($cabecera) || fwrite($gestor, $datos) !== strlen($datos) || !fclose($gestor) || !@rename($nuevo, $fichero)) {
            @unlink($nuevo);

            throw new ErrorDeProyecto('No se puede guardar lo que recuerda la compilación incremental', self::FICHERO);
        }
    }

    /**
     * Olvida lo guardado: la siguiente compilación será completa.
     */
    public static function olvidar(Proyecto $proyecto): void
    {
        @unlink($proyecto->ruta(self::FICHERO));
        @unlink($proyecto->ruta(self::CURSO));
    }

    /**
     * Lo que llevan hecho, entre todas, las llamadas de una compilación repartida que aún no ha terminado: cuántas
     * páginas han rehecho y cuánto han tardado. Sirve para que el `Informe` de la última cuente la compilación
     * entera y no solo su parte. Si no hay, o lleva más de una hora sin avanzar, es cero.
     *
     * @return array{rehechas: int, segundos: float}
     */
    public static function leerCurso(Proyecto $proyecto): array
    {
        $datos = json_decode((string) @file_get_contents($proyecto->ruta(self::CURSO)), true);

        if (!is_array($datos) || !is_int($datos['momento'] ?? null) || time() - $datos['momento'] > 3600) {
            return ['rehechas' => 0, 'segundos' => 0.0];
        }

        return ['rehechas' => max(0, (int) ($datos['rehechas'] ?? 0)), 'segundos' => max(0.0, (float) ($datos['segundos'] ?? 0.0))];
    }

    /**
     * @param array{rehechas: int, segundos: float} $curso
     */
    public static function guardarCurso(Proyecto $proyecto, array $curso): void
    {
        Estado::prepararCarpeta($proyecto);
        $fichero = $proyecto->ruta(self::CURSO);
        $nuevo = $fichero . '.nuevo';
        $json = json_encode($curso + ['momento' => time()], JSON_THROW_ON_ERROR);

        if (@file_put_contents($nuevo, $json) === false || !@rename($nuevo, $fichero)) {
            @unlink($nuevo);
        }
    }

    public static function olvidarCurso(Proyecto $proyecto): void
    {
        @unlink($proyecto->ruta(self::CURSO));
    }

    /**
     * Pide el cerrojo de este proyecto para una compilación incremental.
     *
     * @param bool $esperar si espera a que lo suelte quien lo tenga; si no, devuelve null
     *
     * @return resource|null el cerrojo, que se suelta con `soltar()`; null si otra llamada lo tiene y no se espera
     *
     * @throws ErrorDeProyecto si no se puede crear
     */
    public static function bloquear(Proyecto $proyecto, bool $esperar = false)
    {
        Estado::prepararCarpeta($proyecto);
        $cerrojo = @fopen($proyecto->ruta(self::CERROJO), 'c');

        if ($cerrojo === false) {
            throw new ErrorDeProyecto('No se puede abrir el cerrojo de la compilación incremental', self::CERROJO);
        }

        if (!flock($cerrojo, $esperar ? LOCK_EX : LOCK_EX | LOCK_NB)) {
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
     * Una huella de lo que hace el motor: el tamaño y la fecha de cada fichero de su código y de sus recursos.
     * Una memoria guardada por otra versión (o con otro código) no se aprovecha, porque podría dar un resultado
     * distinto al de una compilación completa de esta. No se lee el contenido, que cuesta unos cientos de
     * milisegundos en cada proceso; un cambio de código cambia la fecha, aunque no cambie el tamaño.
     */
    public static function huellaDelMotor(): string
    {
        if (self::$huella !== null) {
            return self::$huella;
        }

        $raiz = dirname(__DIR__, 2);
        $partes = [];

        foreach (['src', 'recursos'] as $carpeta) {
            if (!is_dir("{$raiz}/{$carpeta}")) {
                continue;
            }

            $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$raiz}/{$carpeta}", \FilesystemIterator::SKIP_DOTS));

            foreach ($recorrido as $fichero) {
                if ($fichero->isFile()) {
                    $partes[substr(str_replace('\\', '/', $fichero->getPathname()), strlen($raiz))] = $fichero->getSize() . '|' . $fichero->getMTime();
                }
            }
        }

        ksort($partes, SORT_STRING);

        return self::$huella = substr(md5(serialize($partes)), 0, 16);
    }
}
