<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Aviso;

/**
 * Los registros que se están llenando mientras se construye una página: el
 * de la página y, dentro, el de cada cuerpo que se convierte para ella. Lo
 * que se anota va a todos, porque lo que usa un cuerpo lo usa también la
 * página que lo contiene.
 *
 * @internal
 */
final class Registros
{
    /** @var list<Registro> */
    private array $pila = [];

    public function abrir(): void
    {
        $this->pila[] = new Registro();
    }

    public function cerrar(): Registro
    {
        return array_pop($this->pila) ?? new Registro();
    }

    /**
     * El registro que se está llenando ahora, o null si no hay ninguno.
     */
    public function actual(): ?Registro
    {
        return $this->pila === [] ? null : $this->pila[array_key_last($this->pila)];
    }

    /**
     * @param 'plantillas'|'publico'|'colecciones'|'contenidos'|'traducciones' $conjunto
     */
    public function anotar(string $conjunto, string $valor): void
    {
        foreach ($this->pila as $registro) {
            $registro->{$conjunto}[$valor] = true;
        }
    }

    /**
     * @param 'css'|'js' $tipo
     */
    public function declarar(string $tipo, string $ruta): void
    {
        foreach ($this->pila as $registro) {
            if (!in_array($ruta, $registro->{$tipo}, true)) {
                $registro->{$tipo}[] = $ruta;
            }
        }
    }

    public function aviso(Aviso $aviso): void
    {
        foreach ($this->pila as $registro) {
            $registro->avisos[] = $aviso;
        }
    }

    /**
     * Vuelve a anotar lo que registró algo que se reutiliza sin rehacerlo.
     * Los avisos no: esos los repite quien reutiliza, para que lleguen
     * también al resto de la compilación.
     */
    public function repetir(Registro $otro): void
    {
        foreach (['plantillas', 'publico', 'colecciones', 'contenidos', 'traducciones'] as $conjunto) {
            foreach (array_keys($otro->{$conjunto}) as $valor) {
                $this->anotar($conjunto, (string) $valor);
            }
        }

        foreach (['css', 'js'] as $tipo) {
            foreach ($otro->{$tipo} as $ruta) {
                $this->declarar($tipo, $ruta);
            }
        }
    }
}
