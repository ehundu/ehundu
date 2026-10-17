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
 * cuándo se guardó y cuántos bytes mide lo que sigue (con ceros delante hasta doce cifras, porque se escribe al
 * final), y detrás los registros: uno con lo que se leyó del proyecto, uno por cada cuerpo convertido y uno por
 * cada página. Cada registro es su largo en cuatro bytes y el valor serializado. Se guarda y se lee de uno en uno
 * para no tener en memoria, además de la propia memoria, una copia serializada entera: en un sitio grande son
 * cien megas de más. Si no vale (no está, está dañada o a medio escribir, es de otra versión de la memoria o
 * del motor, o es de otro proyecto) no hay error: se devuelve null y se hace una compilación completa.
 *
 * @internal
 */
final class Almacen
{
    public const string FICHERO = Estado::CARPETA . '/memoria.bin';
    public const string CERROJO = Estado::CARPETA . '/memoria.lock';
    public const string CURSO = Estado::CARPETA . '/memoria-curso.json';

    /** Lo máximo que una compilación espera al cerrojo de otra, en segundos (formato §10.5). */
    public const float ESPERA_MAXIMA = 60.0;

    /** Cada cuánto, en segundos, mira si el cerrojo se ha soltado. */
    private const float SONDEO = 0.1;

    /** Cambia cuando cambia cómo se guarda la memoria */
    public const int VERSION = 3;

    /**
     * Cuánta memoria de PHP pide la memoria guardada por cada byte que mide el fichero, más `MEMORIA_BASE`, para
     * cargarla y rehacer lo que cambie. Se mide en el peor caso (una plantilla cambiada rehace todas las páginas):
     * en los sitios de prueba salen unas 2,5 veces el fichero más unos 10 MB, y esto lo deja con un 20 % de
     * holgura. Ver `docs/compilacion.md`.
     */
    public const float MEMORIA_POR_BYTE = 3.0;

    /** La parte de lo que pide la memoria guardada que no depende de lo que mide: PHP, Twig y el motor. */
    public const int MEMORIA_BASE = 16 * 1048576;

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

            // Un fichero cortado (se agotó el espacio, o se mató el proceso al escribir) no vale
            if ($bytes <= 0 || (fstat($gestor)['size'] ?? -1) !== strlen($cabecera) + $bytes) {
                return null;
            }

            $memoria = self::leerRegistros($gestor, $bytes);
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($gestor);
        }

        if ($memoria->raiz !== $proyecto->raiz) {
            return null;
        }

        return ['memoria' => $memoria, 'guardada' => (int) $partes[3]];
    }

    /**
     * Lee los registros que siguen a la cabecera: todos los bytes que ésta dice y ni uno más.
     *
     * @param resource $gestor
     *
     * @throws \UnexpectedValueException si algo no es lo que tiene que ser
     */
    private static function leerRegistros($gestor, int $bytes): Memoria
    {
        $quedan = $bytes;
        $principio = self::leerRegistro($gestor, $quedan);

        if (!is_array($principio) || count($principio) !== 8 || !is_int($principio[6]) || !is_int($principio[7]) || $principio[6] < 0 || $principio[7] < 0
            || ($principio[6] + $principio[7]) * 8 > $quedan) {
            throw new \UnexpectedValueException('La cabecera de la memoria no es válida');
        }

        [$raiz, $conBorradores, $lectura, $huellas, $publicadas, $huellasTomadas, $cuerpos, $paginas] = $principio;
        $registros = ['cuerpos' => [], 'paginas' => []];

        foreach (['cuerpos' => $cuerpos, 'paginas' => $paginas] as $cuales => $cuantos) {
            for ($i = 0; $i < $cuantos; $i++) {
                $registro = self::leerRegistro($gestor, $quedan);

                if (!is_array($registro) || count($registro) !== 2 || !array_key_exists(0, $registro) || !(is_string($registro[0]) || is_int($registro[0]))) {
                    throw new \UnexpectedValueException('Un registro de la memoria no es válido');
                }

                $registros[$cuales][$registro[0]] = $registro[1];
            }
        }

        if ($quedan !== 0) {
            throw new \UnexpectedValueException('Sobran datos al final de la memoria');
        }

        return new Memoria($raiz, $conBorradores, $lectura, $huellas, $publicadas, $registros['cuerpos'], $registros['paginas'], $huellasTomadas);
    }

    /**
     * @param resource $gestor
     * @param int      $quedan los bytes de registros que faltan por leer; se rebaja con lo que se lee
     */
    private static function leerRegistro($gestor, int &$quedan): mixed
    {
        $largo = $quedan >= 4 ? fread($gestor, 4) : false;

        if (!is_string($largo) || strlen($largo) !== 4) {
            throw new \UnexpectedValueException('La memoria acaba antes de tiempo');
        }

        $largo = (int) (unpack('N', $largo)[1] ?? -1);

        if ($largo <= 0 || $largo > $quedan - 4) {
            throw new \UnexpectedValueException('Un registro de la memoria mide más de lo que queda');
        }

        $datos = '';

        while (strlen($datos) < $largo) {
            $trozo = fread($gestor, $largo - strlen($datos));

            if ($trozo === false || $trozo === '') {
                throw new \UnexpectedValueException('La memoria acaba antes de tiempo');
            }

            $datos .= $trozo;
        }

        $quedan -= 4 + $largo;
        $valor = @unserialize($datos, ['allowed_classes' => self::CLASES]);

        if ($valor === false) {
            throw new \UnexpectedValueException('Un registro de la memoria no se puede leer');
        }

        return $valor;
    }

    /**
     * Guarda la memoria sin que se vea nunca a medias: se escribe a un lado y se cambia de sitio.
     *
     * @throws ErrorDeProyecto si no se puede escribir
     */
    public static function guardar(Proyecto $proyecto, Memoria $memoria): void
    {
        Estado::prepararCarpeta($proyecto);

        $fichero = $proyecto->ruta(self::FICHERO);
        $nuevo = $fichero . '.nuevo';
        $gestor = @fopen($nuevo, 'wb');
        $huella = self::huellaDelMotor();
        $momento = time();
        $cabecera = self::cabecera($huella, $momento, 0);
        $bien = $gestor !== false && fwrite($gestor, $cabecera) === strlen($cabecera);
        $bytes = 0;

        if ($bien) {
            foreach (self::registros($memoria) as $registro) {
                $datos = serialize($registro);
                $unidad = pack('N', strlen($datos)) . $datos;

                if (fwrite($gestor, $unidad) !== strlen($unidad)) {
                    $bien = false;

                    break;
                }

                $bytes += strlen($unidad);
            }
        }

        // El largo de lo que sigue a la cabecera se sabe al final: se vuelve a escribir, con el mismo ancho
        $bien = $bien && rewind($gestor) && fwrite($gestor, self::cabecera($huella, $momento, $bytes)) === strlen($cabecera);

        if ($gestor !== false) {
            $bien = fclose($gestor) && $bien;
        }

        if (!$bien || !@rename($nuevo, $fichero)) {
            @unlink($nuevo);

            throw new ErrorDeProyecto('No se puede guardar lo que recuerda la compilación incremental', self::FICHERO);
        }
    }

    private static function cabecera(string $huella, int $momento, int $bytes): string
    {
        return implode(' ', [self::MARCA, self::VERSION, $huella, $momento, sprintf('%012d', $bytes)]) . "\n";
    }

    /**
     * Lo que se guarda, registro a registro: lo que no son cuerpos ni páginas, con cuántos hay de cada, y después
     * un par de ruta y valor por cada cuerpo y cada página.
     *
     * @return \Generator<int, array<mixed>>
     */
    private static function registros(Memoria $memoria): \Generator
    {
        yield [$memoria->raiz, $memoria->conBorradores, $memoria->lectura, $memoria->huellas, $memoria->publicadas, $memoria->huellasTomadas, count($memoria->cuerpos), count($memoria->paginas)];

        foreach ($memoria->cuerpos as $ruta => $cuerpo) {
            yield [$ruta, $cuerpo];
        }

        foreach ($memoria->paginas as $ruta => $pagina) {
            yield [$ruta, $pagina];
        }
    }

    /**
     * Lo que mide la memoria guardada, o null si no hay.
     */
    public static function tamano(Proyecto $proyecto): ?int
    {
        $fichero = $proyecto->ruta(self::FICHERO);
        clearstatcache(true, $fichero);
        $tamano = @filesize($fichero);

        return $tamano === false ? null : $tamano;
    }

    /**
     * El `memory_limit` de PHP en bytes, o null si no tiene (o si no se entiende lo que dice).
     */
    public static function limiteDeMemoria(): ?int
    {
        return self::bytesDe((string) ini_get('memory_limit'));
    }

    /**
     * Los bytes que dice un valor de `memory_limit`: un número con una `K`, `M` o `G` detrás, o sin nada. Null si no
     * hay límite (`-1`, `0`) o no se entiende.
     */
    public static function bytesDe(string $valor): ?int
    {
        if (preg_match('/^\s*(\d+)\s*([kmg]?)b?\s*$/i', $valor, $partes) !== 1) {
            return null;
        }

        $bytes = (int) $partes[1] * match (strtolower($partes[2])) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            default => 1,
        };

        return $bytes > 0 ? $bytes : null;
    }

    /**
     * Los bytes de memoria de PHP que pide una memoria guardada de `$tamano` bytes (ver `MEMORIA_POR_BYTE`).
     */
    public static function necesaria(int $tamano): int
    {
        return (int) ceil($tamano * self::MEMORIA_POR_BYTE) + self::MEMORIA_BASE;
    }

    /**
     * Si una memoria guardada de `$tamano` bytes cabe en lo que queda de `$limite`, con `$enUso` ya ocupados.
     * Sin límite, siempre cabe.
     */
    public static function cabe(int $tamano, ?int $limite, int $enUso): bool
    {
        return $limite === null || $enUso + self::necesaria($tamano) <= $limite;
    }

    /**
     * Lo que se le dice a quien tiene un `memory_limit` que no da para la memoria guardada: cuánto ocupa, cuánto
     * hace falta, cuánto hay y a cuánto subirlo (a un múltiplo de 64 MB).
     */
    public static function noCabe(int $tamano, int $limite): string
    {
        $megas = static fn (int $bytes): string => number_format(max(1, (int) ceil($bytes / 1048576)), 0, ',', '.');
        $sugerido = (int) ceil((self::necesaria($tamano) + 8 * 1048576) / (64 * 1048576)) * 64;

        return "La memoria de la compilación incremental ocupa {$megas($tamano)} MB y para usarla hacen falta unos {$megas(self::necesaria($tamano))} MB, "
            . "pero el memory_limit de PHP es de {$megas($limite)} MB. Sube el memory_limit a {$sugerido} MB o más";
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
     * Esperar no es bloquearse en `flock`: una espera dentro del sistema no cuenta para
     * `max_execution_time` en Linux, y si quien tiene el cerrojo se cuelga, quien espera ocuparía un
     * trabajador hasta que alguien lo matara. Se sondea cada `SONDEO` segundos hasta `$espera`, y entonces
     * falla con un error que se entiende.
     *
     * @param bool  $esperar si espera a que lo suelte quien lo tenga; si no, devuelve null
     * @param float $espera  lo máximo que espera, en segundos, si `$esperar`
     *
     * @return resource|null el cerrojo, que se suelta con `soltar()`; null si otra llamada lo tiene y no se espera
     *
     * @throws ErrorDeProyecto si no se puede crear, o si se espera y otra llamada no lo suelta a tiempo
     */
    public static function bloquear(Proyecto $proyecto, bool $esperar = false, float $espera = self::ESPERA_MAXIMA)
    {
        Estado::prepararCarpeta($proyecto);
        $cerrojo = @fopen($proyecto->ruta(self::CERROJO), 'c');

        if ($cerrojo === false) {
            throw new ErrorDeProyecto('No se puede abrir el cerrojo de la compilación incremental', self::CERROJO);
        }

        $inicio = hrtime(true);

        while (!flock($cerrojo, LOCK_EX | LOCK_NB)) {
            if (!$esperar) {
                fclose($cerrojo);

                return null;
            }

            if ((hrtime(true) - $inicio) / 1e9 >= $espera) {
                fclose($cerrojo);

                throw new ErrorDeProyecto(
                    'Otra compilación de este proyecto lleva más de ' . self::segundos($espera) . ' s en marcha y no ha acabado; inténtalo de nuevo cuando termine',
                    self::CERROJO,
                );
            }

            usleep((int) (self::SONDEO * 1e6));
        }

        return $cerrojo;
    }

    private static function segundos(float $segundos): string
    {
        return rtrim(rtrim(number_format($segundos, 1, ',', ''), '0'), ',');
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
     * Una huella de lo que decide el HTML que sale: el contenido de cada fichero del código y de los recursos
     * del motor, las versiones de todos los paquetes que tiene instalados (Twig, CommonMark, `symfony/yaml`,
     * dompdf…) y la versión menor de PHP. Una memoria guardada con otra cosa no se aprovecha, porque podría dar un
     * resultado distinto al de una compilación completa de esta.
     *
     * Se lee el contenido y no se mira la fecha: leer unos cien ficheros cuesta unos 5 ms, y la fecha cambia con
     * cualquier despliegue que reescriba los ficheros sin cambiarlos y no cambia si alguien la conserva.
     */
    public static function huellaDelMotor(): string
    {
        return self::$huella ??= self::componerHuella(self::contenidoDelCodigo(), self::paquetesInstalados(), PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
    }

    /**
     * @param array<string, string> $codigo   el resumen del contenido de cada fichero, por ruta
     * @param array<string, string> $paquetes la versión de cada paquete instalado, por nombre
     * @param string                $php      la versión menor de PHP
     */
    public static function componerHuella(array $codigo, array $paquetes, string $php): string
    {
        ksort($codigo, SORT_STRING);
        ksort($paquetes, SORT_STRING);

        return substr(md5(serialize([$codigo, $paquetes, $php])), 0, 16);
    }

    /**
     * La versión y la referencia de cada paquete que Composer tiene instalado; vacío si el motor no se carga
     * con Composer.
     *
     * @return array<string, string>
     */
    public static function paquetesInstalados(): array
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return [];
        }

        $paquetes = [];

        foreach (\Composer\InstalledVersions::getInstalledPackages() as $nombre) {
            $paquetes[$nombre] = (\Composer\InstalledVersions::getVersion($nombre) ?? '') . '@' . (\Composer\InstalledVersions::getReference($nombre) ?? '');
        }

        return $paquetes;
    }

    /**
     * El resumen del contenido de cada fichero de `src/` y `recursos/`.
     *
     * @return array<string, string>
     */
    private static function contenidoDelCodigo(): array
    {
        $raiz = dirname(__DIR__, 2);
        $partes = [];

        foreach (['src', 'recursos'] as $carpeta) {
            if (!is_dir("{$raiz}/{$carpeta}")) {
                continue;
            }

            $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$raiz}/{$carpeta}", \FilesystemIterator::SKIP_DOTS));

            foreach ($recorrido as $fichero) {
                if ($fichero->isFile()) {
                    $partes[substr(str_replace('\\', '/', $fichero->getPathname()), strlen($raiz))] = (string) @hash_file('xxh128', $fichero->getPathname());
                }
            }
        }

        return $partes;
    }
}
