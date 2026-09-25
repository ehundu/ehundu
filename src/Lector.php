<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Lee un proyecto: `sitio.yml`, los ficheros de `datos/` y las páginas de
 * `contenido/` con su cascada de `_datos.yml`. No escribe nada.
 */
final class Lector
{
    private const array FORMATOS = ['md', 'twig'];
    private const array FORMATOS_DE_DATOS = ['yml', 'json'];

    /**
     * @throws ErrorDeProyecto si falta algo imprescindible o un fichero no se puede leer
     */
    public function leer(Proyecto $proyecto): Lectura
    {
        $avisos = new Avisos();
        $sitio = $this->leerSitio($proyecto);
        $datos = $this->leerDatos($proyecto, $avisos);
        $cascada = new Cascada($proyecto, $avisos);

        $paginas = [];
        foreach ($this->rutasDePaginas($proyecto, $avisos) as $ruta) {
            $paginas[] = $this->leerPagina($proyecto, $ruta, $cascada, $avisos);
        }

        return new Lectura($sitio, $datos, $paginas, $avisos->todos());
    }

    private function leerSitio(Proyecto $proyecto): Sitio
    {
        $fichero = Proyecto::SITIO;

        if (!is_file($proyecto->ruta($fichero))) {
            throw new ErrorDeProyecto('Falta sitio.yml en la raíz del proyecto');
        }

        $yaml = $proyecto->leerTexto($fichero);
        $campos = Yaml::leerCampos($yaml, $fichero);
        $lineas = Yaml::lineasDeClaves($yaml);

        $nombre = $campos['nombre'] ?? null;
        if ($nombre === null) {
            throw new ErrorDeProyecto('Falta el campo «nombre»', $fichero);
        }
        if (!is_string($nombre) || trim($nombre) === '') {
            throw new ErrorDeProyecto('«nombre» tiene que ser un texto', $fichero, $lineas['nombre'] ?? null);
        }

        $url = $campos['url'] ?? null;
        if ($url === null) {
            throw new ErrorDeProyecto('Falta el campo «url»', $fichero);
        }
        if (!is_string($url) || preg_match('#^https?://[^\s/?\#]+(/\S*)?$#i', $url) !== 1) {
            throw new ErrorDeProyecto(
                '«url» tiene que ser la dirección completa del sitio, con http:// o https://',
                $fichero,
                $lineas['url'] ?? null,
            );
        }

        $campos['url'] = rtrim($url, '/');
        unset($campos['despliegue']);

        return new Sitio($nombre, $campos['url'], $campos);
    }

    /**
     * Los ficheros `.yml` y `.json` de `datos/`, por su nombre sin extensión.
     * Si dos ficheros dan el mismo nombre, gana el `.yml`.
     *
     * @return array<string, mixed>
     */
    private function leerDatos(Proyecto $proyecto, Avisos $avisos): array
    {
        $ficheros = [];

        foreach ($this->entradas($proyecto, Proyecto::DATOS) as $nombre) {
            $fichero = Proyecto::DATOS . "/{$nombre}";
            $extension = pathinfo($nombre, PATHINFO_EXTENSION);
            $clave = pathinfo($nombre, PATHINFO_FILENAME);

            if (is_dir($proyecto->ruta($fichero))) {
                $avisos->registrar('Las subcarpetas de datos/ no se leen', $fichero);
            } elseif (!in_array($extension, self::FORMATOS_DE_DATOS, true)) {
                $avisos->registrar('En datos/ solo se leen ficheros .yml y .json; este se ignora', $fichero);
            } elseif (str_contains($clave, '.')) {
                $avisos->registrar('El punto en el nombre está reservado para el idioma (formato §15.5); este fichero no se lee', $fichero);
            } else {
                $ficheros[$clave][$extension] = $fichero;
            }
        }

        $datos = [];

        foreach ($ficheros as $clave => $porFormato) {
            if (count($porFormato) > 1) {
                $avisos->registrar("«datos.{$clave}» sale también de {$porFormato['yml']}; este fichero se ignora", $porFormato['json']);
            }

            $fichero = $porFormato['yml'] ?? $porFormato['json'];
            $texto = $proyecto->leerTexto($fichero);

            $datos[$clave] = isset($porFormato['yml']) ? Yaml::leer($texto, $fichero) : self::leerJson($texto, $fichero);
        }

        return $datos;
    }

    /**
     * Las rutas de las páginas de `contenido/`, relativas a esa carpeta y en
     * orden, para que el resultado no dependa del sistema de ficheros.
     *
     * @return list<string>
     */
    private function rutasDePaginas(Proyecto $proyecto, Avisos $avisos): array
    {
        if (!is_dir($proyecto->ruta(Proyecto::CONTENIDO))) {
            $avisos->registrar('No hay carpeta contenido/: no se genera ninguna página');

            return [];
        }

        $rutas = [];
        $pendientes = [''];

        while ($pendientes !== []) {
            $carpeta = array_pop($pendientes);

            foreach ($this->entradas($proyecto, Proyecto::CONTENIDO . ($carpeta === '' ? '' : "/{$carpeta}")) as $nombre) {
                $ruta = $carpeta === '' ? $nombre : "{$carpeta}/{$nombre}";

                if (is_dir($proyecto->ruta(Proyecto::CONTENIDO, $ruta))) {
                    $pendientes[] = $ruta;
                } elseif (in_array(pathinfo($nombre, PATHINFO_EXTENSION), self::FORMATOS, true)) {
                    if (str_contains(pathinfo($nombre, PATHINFO_FILENAME), '.')) {
                        $avisos->registrar(
                            'El punto en el nombre está reservado para el idioma (formato §15.5); este fichero no se compila',
                            Proyecto::CONTENIDO . "/{$ruta}",
                        );
                    } else {
                        $rutas[] = $ruta;
                    }
                }
            }
        }

        sort($rutas, SORT_STRING);

        return $rutas;
    }

    private function leerPagina(Proyecto $proyecto, string $ruta, Cascada $cascada, Avisos $avisos): Pagina
    {
        $fichero = Proyecto::CONTENIDO . "/{$ruta}";
        $frontMatter = FrontMatter::separar($proyecto->leerTexto($fichero), $fichero);

        $propios = Campos::normalizar(
            Yaml::leerCampos($frontMatter->yaml, $fichero, $frontMatter->lineaYaml),
            $frontMatter->yaml,
            $frontMatter->lineaYaml,
            $fichero,
            $avisos,
        );

        $carpeta = str_contains($ruta, '/') ? substr($ruta, 0, strrpos($ruta, '/')) : '';
        $campos = Cascada::combinar($cascada->campos($carpeta), $propios);

        return new Pagina(
            $ruta,
            pathinfo($ruta, PATHINFO_EXTENSION),
            $campos,
            Url::resolver($ruta, $campos, $fichero, $avisos),
            $frontMatter->cuerpo,
            $frontMatter->lineaCuerpo,
        );
    }

    /**
     * Lo que hay en una carpeta del proyecto, sin lo oculto (lo que empieza
     * por punto) y en orden.
     *
     * @return list<string>
     */
    private function entradas(Proyecto $proyecto, string $carpeta): array
    {
        $entradas = is_dir($proyecto->ruta($carpeta)) ? scandir($proyecto->ruta($carpeta)) : false;

        if ($entradas === false) {
            return [];
        }

        $visibles = array_values(array_filter($entradas, fn (string $nombre) => !str_starts_with($nombre, '.')));
        sort($visibles, SORT_STRING);

        return $visibles;
    }

    private static function leerJson(string $texto, string $fichero): mixed
    {
        try {
            return json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            $pista = $error->getCode() === JSON_ERROR_SYNTAX
                ? ': hay un error de sintaxis (una coma de más, unas comillas o una llave sin cerrar)'
                : '';

            throw new ErrorDeProyecto("El JSON no es válido{$pista}", $fichero, null, $error);
        }
    }
}
