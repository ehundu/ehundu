<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La huella de cada fichero de un proyecto: su tamaño, su fecha de
 * modificación y, si pesa menos de un mega, un resumen de su contenido. Dos
 * huellas iguales quieren decir el mismo fichero.
 *
 * La fecha sola no basta: PHP la da en segundos, y dos cambios del mismo
 * tamaño dentro del mismo segundo la dejan igual. El resumen de cada fichero
 * se calcula una vez y se reutiliza mientras no cambien su tamaño ni su
 * fecha; si el fichero se ha tocado en los dos últimos segundos, se vuelve a
 * calcular la próxima vez, porque podría cambiar otra vez sin que se note en
 * la fecha. Así, después de la primera, cada toma cuesta poco más que mirar
 * la fecha de cada fichero.
 */
final class Huellas
{
    /** Los ficheros más grandes (imágenes, vídeos) se comparan solo por tamaño y fecha. */
    public const int LIMITE = 1048576;

    /** @var array<string, string> huellas de ficheros que llevan un rato sin tocarse, por ruta, tamaño y fecha */
    private array $estables = [];

    /**
     * @param bool $ocultos si se miran los ficheros y carpetas que empiezan por punto
     */
    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly bool $ocultos = false,
    ) {
    }

    /**
     * La huella de cada fichero de esas rutas, que pueden ser ficheros o
     * carpetas, desde la raíz del proyecto.
     *
     * @param list<string> $rutas
     *
     * @return array<string, string> por ruta desde la raíz, con barras normales y en orden
     */
    public function tomar(array $rutas): array
    {
        clearstatcache();

        $ahora = time();
        $huellas = [];
        $estables = [];

        foreach ($rutas as $ruta) {
            $completa = $this->proyecto->ruta($ruta);

            if (is_file($completa)) {
                $huellas[$ruta] = $this->huella($ruta, $completa, $ahora, $estables);

                continue;
            }

            if (!is_dir($completa)) {
                continue;
            }

            $filtro = fn (\SplFileInfo $fichero): bool => $this->ocultos || !str_starts_with($fichero->getFilename(), '.');
            $recorrido = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($completa, \FilesystemIterator::SKIP_DOTS),
                $filtro,
            ));

            foreach ($recorrido as $fichero) {
                if ($fichero->isFile()) {
                    $relativa = $ruta . '/' . substr(str_replace('\\', '/', $fichero->getPathname()), strlen($completa) + 1);
                    $huellas[$relativa] = $this->huella($relativa, $fichero->getPathname(), $ahora, $estables);
                }
            }
        }

        // Solo se recuerdan las huellas de lo que sigue existiendo.
        $this->estables = $estables;
        ksort($huellas, SORT_STRING);

        return $huellas;
    }

    /**
     * @param array<string, string> $estables
     */
    private function huella(string $ruta, string $completa, int $ahora, array &$estables): string
    {
        $tamano = (int) @filesize($completa);
        $fecha = (int) @filemtime($completa);
        $clave = "{$ruta}|{$tamano}|{$fecha}";

        $huella = $this->estables[$clave] ?? null;

        if ($huella === null) {
            $resumen = $tamano < self::LIMITE ? (string) @hash_file('xxh128', $completa) : '';
            $huella = "{$tamano}|{$fecha}|{$resumen}";

            if ($fecha < $ahora - 1) {
                $estables[$clave] = $huella;
            }
        } else {
            $estables[$clave] = $huella;
        }

        return $huella;
    }
}
