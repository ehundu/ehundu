<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\Avisos;
use Ehundu\Compilador;
use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;

/**
 * Publica un proyecto en el destino de su `sitio.yml`: lo compila entero y,
 * si sale bien, deja el destino como `salida/` (ver `PlanDeDespliegue`).
 *
 * Lo que Ehundu sube queda anotado en el manifiesto del destino, que se
 * guarda cada pocos ficheros: si un despliegue se corta, el siguiente sigue
 * donde se quedó. Decidir qué hacer (`PlanDeDespliegue::trazar`) va aparte
 * de hacerlo (`ejecutar`), para poder simular y, más adelante, desplegar por
 * lotes desde un proceso web.
 */
final class Desplegador
{
    /** Cada cuántos ficheros, o segundos, se guarda el manifiesto mientras se despliega. */
    private const int GUARDAR_CADA = 50;
    private const int GUARDAR_CADA_SEGUNDOS = 15;

    /**
     * @param (\Closure(Configuracion, Proyecto): Destino)|null $abrir abre el destino; para las pruebas
     */
    public function __construct(
        private readonly Compilador $compilador = new CompiladorEnProceso(),
        private readonly ?\Closure $abrir = null,
    ) {
    }

    /**
     * @param bool                                  $simular   si solo se calcula qué se haría, sin tocar el destino
     * @param bool                                  $todo      si se sube todo aunque no haya cambiado
     * @param array<string, string>                 $secretos  los que da el programa que incrusta el motor; ganan
     *                                                         a los de `.secretos.yml`
     * @param (\Closure(string, string): void)|null $alAvanzar se llama con «subido» o «borrado» y la ruta,
     *                                                         según se va haciendo
     * @param (\Closure(Informe): void)|null        $alCompilar se llama con el informe de la compilación,
     *                                                         antes de conectar con el destino
     *
     * @throws ErrorDeProyecto si no se puede compilar o desplegar
     */
    public function desplegar(
        Proyecto $proyecto,
        bool $simular = false,
        bool $todo = false,
        #[\SensitiveParameter] array $secretos = [],
        ?\Closure $alAvanzar = null,
        ?\Closure $alCompilar = null,
    ): InformeDeDespliegue {
        $inicio = hrtime(true);
        $avisos = new Avisos();

        // La configuración se lee antes de compilar: si está mal, no hay nada que esperar.
        $configuracion = Configuracion::leer($proyecto, $secretos, $avisos);
        $compilacion = $this->compilador->compilar($proyecto);
        $alCompilar !== null && $alCompilar($compilacion);
        $ficheros = self::ficheros($proyecto);
        $destino = $this->abrir($configuracion, $proyecto, $avisos);

        try {
            $anterior = self::manifiesto($destino, $todo, $avisos);
            $plan = PlanDeDespliegue::trazar($ficheros, $anterior, $todo);

            if (!$simular) {
                $this->ejecutar($plan, $proyecto, $destino, $anterior, $alAvanzar);
            }
        } finally {
            $destino->cerrar();
        }

        return new InformeDeDespliegue(
            $compilacion,
            $configuracion->descripcion(),
            array_column($plan->subidas, 'ruta'),
            $plan->bytes(),
            $plan->borrados,
            $plan->sinCambios,
            $avisos->todos(),
            (hrtime(true) - $inicio) / 1e9,
            $simular,
        );
    }

    /**
     * Hace lo que dice el plan: sube, borra, quita las carpetas que se quedan
     * vacías y guarda el manifiesto. Si algo falla por el camino, intenta
     * guardar lo hecho hasta ahí antes de detenerse.
     *
     * @param Manifiesto|null                       $anterior  el que había en el destino
     * @param (\Closure(string, string): void)|null $alAvanzar
     */
    public function ejecutar(PlanDeDespliegue $plan, Proyecto $proyecto, Destino $destino, ?Manifiesto $anterior, ?\Closure $alAvanzar = null): void
    {
        if ($plan->estaVacio() && $anterior !== null) {
            return;
        }

        $manifiesto = new Manifiesto($anterior?->ficheros() ?? []);
        $sinGuardar = 0;
        $guardado = hrtime(true);

        $guardar = function () use ($destino, $manifiesto, &$sinGuardar, &$guardado): void {
            $destino->escribir(Manifiesto::FICHERO, $manifiesto->json());
            $sinGuardar = 0;
            $guardado = hrtime(true);
        };

        $hecho = function () use ($guardar, &$sinGuardar, &$guardado): void {
            $sinGuardar++;

            if ($sinGuardar >= self::GUARDAR_CADA || (hrtime(true) - $guardado) / 1e9 >= self::GUARDAR_CADA_SEGUNDOS) {
                $guardar();
            }
        };

        try {
            foreach ($plan->subidas as $subida) {
                $destino->subir($subida['ruta'], $proyecto->ruta(Proyecto::SALIDA, $subida['ruta']));
                $manifiesto->anotar($subida['ruta'], $subida['md5'], $subida['tamano']);
                $alAvanzar !== null && $alAvanzar('subido', $subida['ruta']);
                $hecho();
            }

            foreach ($plan->borrados as $ruta) {
                $destino->borrar($ruta);
                $manifiesto->quitar($ruta);
                $alAvanzar !== null && $alAvanzar('borrado', $ruta);
                $hecho();
            }

            foreach ($plan->carpetasQueSobran($manifiesto->ficheros()) as $carpeta) {
                $destino->quitarCarpeta($carpeta);
            }
        } catch (\Throwable $error) {
            try {
                $guardar();
            } catch (\Throwable) {
                // Si no se puede, el siguiente despliegue repetirá lo que falte.
            }

            throw $error;
        }

        $guardar();
    }

    /**
     * Los ficheros de `salida/`, con su MD5 y su tamaño.
     *
     * @return array<string, array{md5: string, tamano: int}>
     */
    private static function ficheros(Proyecto $proyecto): array
    {
        $ficheros = [];

        foreach ($proyecto->ficherosDe(Proyecto::SALIDA) as $ruta) {
            if ($ruta === Manifiesto::FICHERO) {
                throw new ErrorDeProyecto(
                    'El sitio tiene un fichero ' . Manifiesto::FICHERO . ' en la raíz; ese nombre lo usa Ehundu para saber qué ha subido. Cámbiale el nombre',
                );
            }

            $completa = $proyecto->ruta(Proyecto::SALIDA, $ruta);
            $ficheros[$ruta] = ['md5' => (string) md5_file($completa), 'tamano' => (int) filesize($completa)];
        }

        return $ficheros;
    }

    private static function manifiesto(Destino $destino, bool $todo, Avisos $avisos): ?Manifiesto
    {
        $json = $destino->leer(Manifiesto::FICHERO);

        if ($json === null) {
            return null;
        }

        try {
            return Manifiesto::leer($json);
        } catch (\UnexpectedValueException $error) {
            if (!$todo) {
                throw new ErrorDeProyecto(
                    'El ' . Manifiesto::FICHERO . " del destino {$error->getMessage()}. Sin él no se sabe qué subió Ehundu; "
                        . 'si se ha estropeado, despliega con --todo para subirlo todo y rehacerlo',
                );
            }

            $avisos->registrar(
                'El ' . Manifiesto::FICHERO . " del destino {$error->getMessage()}; se sube todo y se rehace, pero lo que "
                    . 'subió Ehundu antes y ya no se genera se queda en el destino',
            );

            return null;
        }
    }

    private function abrir(Configuracion $configuracion, Proyecto $proyecto, Avisos $avisos): Destino
    {
        if ($this->abrir !== null) {
            return ($this->abrir)($configuracion, $proyecto);
        }

        return match ($configuracion->destino) {
            'carpeta' => new Carpeta($configuracion->ruta, $proyecto),
            'ftp' => new Ftp($configuracion, $avisos),
            'sftp' => new Sftp($configuracion, $proyecto),
            's3' => new S3($configuracion),
        };
    }
}
