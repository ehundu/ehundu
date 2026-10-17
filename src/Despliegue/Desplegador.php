<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\Aviso;
use Ehundu\Avisos;
use Ehundu\Compilador;
use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Lector;
use Ehundu\Presupuesto;
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
     * @param (\Closure(Aviso): void)|null          $alAvisar  se llama con cada aviso del despliegue según
     *                                                         sale, para que se vea aunque luego falle
     * @param float|null                            $segundos  lo que puede durar la subida (formato decisión 125): al
     *                                                         agotarse, guarda el manifiesto y para, y el informe dice
     *                                                         que no está completo; la llamada siguiente sigue donde se
     *                                                         quedó. La tarea que ha empezado se termina. Sin él, sube todo.
     *                                                         Con `$todo` se ignora: subirlo todo otra vez no se puede
     *                                                         repartir, porque la llamada siguiente volvería a empezar por
     *                                                         el primer fichero
     * @param Informe|null                          $compilado el de una compilación ya hecha, por ejemplo repartida con
     *                                                         `avanzar` (formato §10.5), para no volver a compilar en cada llamada
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
        ?\Closure $alAvisar = null,
        ?float $segundos = null,
        ?Informe $compilado = null,
    ): InformeDeDespliegue {
        $inicio = hrtime(true);
        $segundos = $todo ? null : $segundos;
        $avisos = new Avisos();
        $hechos = ['subidos' => [], 'borrados' => []];
        $completo = true;

        if ($alAvisar !== null) {
            $avisos->escuchar($alAvisar);
        }

        // La configuración se lee antes de compilar: si está mal, no hay nada que esperar.
        $configuracion = Configuracion::leer($proyecto, $secretos, $avisos);
        $compilacion = $compilado ?? $this->compilador->compilar($proyecto);
        $alCompilar !== null && $alCompilar($compilacion);
        $ficheros = PlanDeDespliegue::ficheros($proyecto);
        $url = (new Lector())->leerSitio($proyecto)->url;
        $destino = $this->abrir($configuracion, $proyecto, $avisos);

        try {
            $anterior = self::manifiesto($destino, $todo, $avisos);
            self::comprobarSitio($anterior, $url, $todo, $avisos);
            $plan = PlanDeDespliegue::trazar($ficheros, $anterior, $todo, $url);

            if (!$simular) {
                // Lo que se ha hecho de verdad, que con un presupuesto de tiempo puede ser solo una parte del plan
                $completo = $this->ejecutar($plan, $proyecto, $destino, $anterior, function (string $que, string $ruta) use (&$hechos, $alAvanzar): void {
                    $hechos[$que === 'subido' ? 'subidos' : 'borrados'][] = $ruta;
                    $alAvanzar !== null && $alAvanzar($que, $ruta);
                }, $segundos);
            }
        } finally {
            $destino->cerrar();
        }

        $subidos = $simular ? array_column($plan->subidas, 'ruta') : $hechos['subidos'];
        $borrados = $simular ? $plan->borrados : $hechos['borrados'];
        $bytes = $simular ? $plan->bytes() : array_sum(array_map(
            static fn (array $subida): int => in_array($subida['ruta'], $hechos['subidos'], true) ? $subida['tamano'] : 0,
            $plan->subidas,
        ));
        $pendientes = $simular || $completo ? 0 : count($plan->subidas) - count($hechos['subidos']) + count($plan->borrados) - count($hechos['borrados']);

        return new InformeDeDespliegue(
            $compilacion,
            $configuracion->descripcion(),
            $subidos,
            $bytes,
            $borrados,
            $plan->sinCambios,
            $avisos->todos(),
            (hrtime(true) - $inicio) / 1e9,
            $simular,
            $completo,
            $pendientes,
        );
    }

    /**
     * Hace lo que dice el plan: sube, borra, quita las carpetas que se quedan
     * vacías y guarda el manifiesto. Si algo falla por el camino, intenta
     * guardar lo hecho hasta ahí antes de detenerse.
     *
     * @param Manifiesto|null                       $anterior  el que había en el destino
     * @param (\Closure(string, string): void)|null $alAvanzar
     * @param float|null                            $segundos  lo que puede durar; al agotarse, guarda el manifiesto
     *                                                         y para. Siempre hace al menos un fichero
     *
     * @return bool si ha hecho todo el plan; falso si ha parado por el tiempo y queda algo
     */
    public function ejecutar(PlanDeDespliegue $plan, Proyecto $proyecto, Destino $destino, ?Manifiesto $anterior, ?\Closure $alAvanzar = null, ?float $segundos = null): bool
    {
        $url = $plan->url ?? $anterior?->url;

        if ($plan->estaVacio() && $anterior !== null && $anterior->url === $url) {
            return true;
        }

        $limite = $segundos === null ? null : Presupuesto::limite(hrtime(true), $segundos);
        $hechas = 0;

        $manifiesto = new Manifiesto($anterior?->ficheros() ?? [], $url);
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
                if ($limite !== null && $hechas > 0 && hrtime(true) >= $limite) {
                    $guardar();

                    return false;
                }

                $hechas++;
                $destino->subir($subida['ruta'], $proyecto->ruta(Proyecto::SALIDA, $subida['ruta']));
                $manifiesto->anotar($subida['ruta'], $subida['md5'], $subida['tamano']);
                $alAvanzar !== null && $alAvanzar('subido', $subida['ruta']);
                $hecho();
            }

            foreach ($plan->borrados as $ruta) {
                if ($limite !== null && $hechas > 0 && hrtime(true) >= $limite) {
                    $guardar();

                    return false;
                }

                $hechas++;
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

        return true;
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

    /**
     * Que el destino sea de este sitio. Si su manifiesto es de otra `url`, lo
     * normal es que la ruta esté mal, y desplegar borraría lo que subió el
     * otro sitio; `--todo` confirma que es este mismo con otra dirección. Un
     * manifiesto sin `url`, de Ehundu 0.1, se acepta.
     */
    private static function comprobarSitio(?Manifiesto $anterior, string $url, bool $todo, Avisos $avisos): void
    {
        if ($anterior?->url === null || $anterior->url === $url) {
            return;
        }

        if (!$todo) {
            throw new ErrorDeProyecto(
                'El destino es de otro sitio: su ' . Manifiesto::FICHERO . " dice {$anterior->url} y " . Proyecto::SITIO
                    . " dice {$url}. Si la ruta de despliegue está mal, corrígela: desplegar ahí borraría lo que subió "
                    . 'ese sitio. Si es este mismo sitio con otra dirección, despliega con --todo',
            );
        }

        $avisos->registrar("El destino era de {$anterior->url} y pasa a ser de {$url}");
    }

    private function abrir(Configuracion $configuracion, Proyecto $proyecto, Avisos $avisos): Destino
    {
        if ($this->abrir !== null) {
            return ($this->abrir)($configuracion, $proyecto);
        }

        return $configuracion->abrir($proyecto, $avisos);
    }
}
