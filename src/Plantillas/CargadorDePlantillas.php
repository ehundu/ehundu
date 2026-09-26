<?php

declare(strict_types=1);

namespace Ehundu\Plantillas;

use Ehundu\Proyecto;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * El cargador de Twig de un proyecto (formato §7). Las plantillas se nombran
 * con su ruta desde la raíz del proyecto, `plantillas/base.twig` o
 * `parciales/cabecera.twig`, y Twig no puede leer nada fuera de esas dos
 * carpetas: ni los datos, ni el contenido, ni los secretos.
 */
final class CargadorDePlantillas implements LoaderInterface
{
    private const array CARPETAS = [Proyecto::PLANTILLAS . '/', Proyecto::PARCIALES . '/'];

    private FilesystemLoader $ficheros;

    /** @var array<string, string> claves de caché ya calculadas; el cargador vive una sola compilación */
    private array $claves = [];

    /**
     * @param (\Closure(string): void)|null $alUsar se llama con el nombre de cada plantilla que se
     *                                           usa o se busca, exista o no
     */
    public function __construct(Proyecto $proyecto, private readonly ?\Closure $alUsar = null)
    {
        $this->ficheros = new FilesystemLoader([$proyecto->raiz], $proyecto->raiz);
    }

    public function getSourceContext(string $name): Source
    {
        $this->comprobar($name);

        return $this->ficheros->getSourceContext($name);
    }

    /**
     * Twig da a cada plantilla compilada un nombre de clase de PHP que sale de
     * esta clave, y dentro de un proceso reutiliza la clase si ya existe. Con
     * la ruta relativa, dos proyectos con una `plantillas/pagina.twig`
     * compartirían plantilla; por eso la clave lleva la ruta absoluta y un
     * resumen del contenido, que además cambia cuando se edita la plantilla.
     */
    public function getCacheKey(string $name): string
    {
        $this->comprobar($name);

        // Twig la pide cada vez que se usa la plantilla, aunque ya la tenga compilada.
        if ($this->alUsar !== null) {
            ($this->alUsar)($name);
        }

        if (!isset($this->claves[$name])) {
            $ruta = (string) $this->ficheros->getSourceContext($name)->getPath();
            $this->claves[$name] = $ruta . ':' . hash_file('xxh128', $ruta);
        }

        return $this->claves[$name];
    }

    public function isFresh(string $name, int $time): bool
    {
        $this->comprobar($name);

        return $this->ficheros->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        if (!self::permitido($name)) {
            return false;
        }

        // Que se haya buscado también cuenta: si luego aparece, cambia el resultado.
        if ($this->alUsar !== null) {
            ($this->alUsar)($name);
        }

        return $this->ficheros->exists($name);
    }

    private function comprobar(string $nombre): void
    {
        if (!self::permitido($nombre)) {
            throw new LoaderError(
                "Las plantillas y los parciales se nombran desde la raíz del proyecto y tienen que estar en plantillas/ o parciales/: «{$nombre}»",
            );
        }
    }

    private static function permitido(string $nombre): bool
    {
        if (str_contains($nombre, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $nombre) === 1) {
            return false;
        }

        foreach (self::CARPETAS as $carpeta) {
            if (str_starts_with($nombre, $carpeta)) {
                return true;
            }
        }

        return false;
    }
}
