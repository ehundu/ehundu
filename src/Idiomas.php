<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Los idiomas de un sitio (formato §15.1): los de `idiomas` en `sitio.yml`,
 * o solo el de `idioma` en un sitio de un solo idioma. El primero es el
 * predeterminado, que va sin prefijo en las URL; los demás llevan el suyo
 * (`/eu`).
 */
final readonly class Idiomas
{
    /** El idioma de un sitio que no dice cuál es. */
    public const string PREDETERMINADO = 'es';

    /** El segundo argumento de `coleccion()` que pide todos los idiomas. */
    public const string TODOS = 'todos';

    /**
     * @param non-empty-list<array{codigo: string, nombre: string}> $lista      en el orden de `sitio.yml`
     * @param bool                                                  $declarados si el sitio escribe `idiomas`
     */
    public function __construct(
        public array $lista = [['codigo' => self::PREDETERMINADO, 'nombre' => self::PREDETERMINADO]],
        public bool $declarados = false,
    ) {
    }

    /**
     * Si un valor puede ser el código de un idioma: dos o tres letras
     * minúsculas, como en ISO 639.
     */
    public static function esCodigo(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/^[a-z]{2,3}$/', $valor) === 1;
    }

    public function predeterminado(): string
    {
        return $this->lista[0]['codigo'];
    }

    /**
     * @return list<string>
     */
    public function codigos(): array
    {
        return array_column($this->lista, 'codigo');
    }

    public function declara(string $codigo): bool
    {
        return in_array($codigo, $this->codigos(), true);
    }

    /**
     * Lo que va delante de las URL de ese idioma: nada en el predeterminado,
     * `/eu` en los demás.
     */
    public function prefijo(string $codigo): string
    {
        return $codigo === $this->predeterminado() ? '' : "/{$codigo}";
    }

    /**
     * La raíz de ese idioma: `/` en el predeterminado, `/eu/` en los demás.
     */
    public function raiz(string $codigo): string
    {
        return $this->prefijo($codigo) . '/';
    }

    /**
     * `sitio.idiomas` tal como lo ven las plantillas (formato §15.8).
     *
     * @return list<array{codigo: string, nombre: string, url: string}>
     */
    public function paraPlantillas(): array
    {
        return array_map(
            fn (array $idioma) => [...$idioma, 'url' => $this->raiz($idioma['codigo'])],
            $this->lista,
        );
    }
}
