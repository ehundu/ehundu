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

    /** Plantillas y datos de otros generadores, que nunca se publican (formato §4). */
    private const string DE_OTROS_GENERADORES = '/\.(?:njk|liquid|vto|webc)$|\.11ty\.[cm]?js$|\.11tydata\.(?:[cm]?js|json)$/i';

    /** Los campos que admite cada idioma de `idiomas` en `sitio.yml`. */
    private const array CAMPOS_DE_UN_IDIOMA = ['codigo', 'nombre'];

    /**
     * @throws ErrorDeProyecto si falta algo imprescindible o un fichero no se puede leer
     */
    public function leer(Proyecto $proyecto): Lectura
    {
        $avisos = new Avisos();
        $sitio = $this->leerSitio($proyecto, $avisos);
        [$datos, $datosPorIdioma] = $this->leerDatos($proyecto, $sitio->idiomas, $avisos);
        $cascada = new Cascada($proyecto, $avisos, $sitio->zonaHoraria);

        $paginas = [];
        [$encontradas, $ficheros, $comunes] = $this->recorrerContenido($proyecto, $sitio->idiomas, $avisos);

        foreach ($encontradas as $encontrada) {
            $paginas[] = $this->leerPagina($proyecto, $encontrada, $comunes, $cascada, $sitio, $avisos);
        }

        return new Lectura($sitio, $datos, $paginas, $avisos->todos(), $ficheros, $datosPorIdioma);
    }

    /**
     * Solo `sitio.yml`, sin el resto del proyecto.
     *
     * @param Avisos|null $avisos donde van los avisos; si falta, se pierden
     *
     * @throws ErrorDeProyecto si falta o le falta algo imprescindible
     */
    public function leerSitio(Proyecto $proyecto, ?Avisos $avisos = null): Sitio
    {
        $avisos ??= new Avisos();
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

        $zona = $campos['zonaHoraria'] ?? 'UTC';
        try {
            $zonaHoraria = new \DateTimeZone(is_string($zona) ? $zona : '');
        } catch (\Exception) {
            throw new ErrorDeProyecto(
                '«zonaHoraria» no es una zona horaria válida; se escribe como Europe/Madrid o America/Mexico_City',
                $fichero,
                $lineas['zonaHoraria'] ?? null,
            );
        }

        $idiomas = self::leerIdiomas($campos, $lineas, $avisos);

        $campos['url'] = rtrim($url, '/');
        $campos['idioma'] = $idiomas->predeterminado();
        $campos['idiomas'] = $idiomas->paraPlantillas();
        unset($campos['despliegue']);

        return new Sitio($nombre, $campos['url'], $zonaHoraria, $campos, $idiomas);
    }

    /**
     * Los idiomas del sitio (formato §2 y §15.1): los de `idiomas`, o el de
     * `idioma`, que es `es` si falta.
     *
     * @param array<array-key, mixed> $campos los de `sitio.yml`
     * @param array<string, int>      $lineas
     *
     * @throws ErrorDeProyecto si `idiomas` está mal escrito
     */
    private static function leerIdiomas(array $campos, array $lineas, Avisos $avisos): Idiomas
    {
        $fichero = Proyecto::SITIO;
        $idioma = $campos['idioma'] ?? null;

        if (!array_key_exists('idiomas', $campos)) {
            if ($idioma !== null && !Idiomas::esCodigo($idioma)) {
                $avisos->registrar(
                    '«idioma» tiene que ser el código del idioma, en dos o tres letras minúsculas, como es, eu o en; se usa es',
                    $fichero,
                    $lineas['idioma'] ?? null,
                );
                $idioma = null;
            }

            $codigo = is_string($idioma) ? $idioma : Idiomas::PREDETERMINADO;
            $idiomas = new Idiomas([['codigo' => $codigo, 'nombre' => $codigo]]);
        } else {
            $linea = $lineas['idiomas'] ?? null;
            $lista = $campos['idiomas'];

            if (!is_array($lista) || $lista === [] || !array_is_list($lista)) {
                throw new ErrorDeProyecto(
                    '«idiomas» tiene que ser una lista con los idiomas del sitio, cada uno con su código (- codigo: es)',
                    $fichero,
                    $linea,
                );
            }

            $leidos = [];

            foreach ($lista as $posicion => $entrada) {
                $codigo = is_array($entrada) ? ($entrada['codigo'] ?? null) : null;

                if (!Idiomas::esCodigo($codigo)) {
                    throw new ErrorDeProyecto(
                        sprintf('El idioma %d de «idiomas» necesita un «codigo» de dos o tres letras minúsculas, como es, eu o en', $posicion + 1),
                        $fichero,
                        $linea,
                    );
                }

                if (isset($leidos[$codigo])) {
                    throw new ErrorDeProyecto("El idioma «{$codigo}» está dos veces en «idiomas»", $fichero, $linea);
                }

                $nombre = $entrada['nombre'] ?? null;

                if ($nombre !== null && (!is_string($nombre) || trim($nombre) === '')) {
                    $avisos->registrar("«idiomas»: el nombre de «{$codigo}» tiene que ser un texto; se usa el código", $fichero, $linea);
                }

                foreach (array_keys($entrada) as $campo) {
                    if (!in_array($campo, self::CAMPOS_DE_UN_IDIOMA, true)) {
                        $avisos->registrar("«idiomas»: {$codigo} " . match ($campo) {
                            'predeterminado' => 'no necesita «predeterminado»: el predeterminado es el primero de la lista',
                            'prefijo' => "no necesita «prefijo»: el de cada idioma es siempre /{$codigo}/",
                            default => "no admite «{$campo}»; se ignora",
                        }, $fichero, $linea);
                    }
                }

                $leidos[$codigo] = [
                    'codigo' => $codigo,
                    'nombre' => is_string($nombre) && trim($nombre) !== '' ? $nombre : $codigo,
                ];
            }

            $idiomas = new Idiomas(array_values($leidos), declarados: true);

            if ($idioma !== null && $idioma !== $idiomas->predeterminado()) {
                $avisos->registrar(
                    "«idioma» no hace falta con «idiomas», y no coincide con el primero de la lista, que es el predeterminado; se usa {$idiomas->predeterminado()}",
                    $fichero,
                    $lineas['idioma'] ?? null,
                );
            }
        }

        foreach ($idiomas->codigos() as $codigo) {
            if (!Fecha::conoce($codigo)) {
                $avisos->registrar("Ehundu no trae los nombres de los meses y los días en «{$codigo}»; fecha() los escribe en inglés", $fichero);
            }
        }

        return $idiomas;
    }

    /**
     * Los ficheros `.yml` y `.json` de `datos/`, por su nombre sin extensión:
     * los comunes y, aparte, los que llevan el código de un idioma
     * (formato §15.6). Si dos ficheros dan el mismo nombre, gana el `.yml`.
     *
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>} los comunes y los de cada idioma
     */
    private function leerDatos(Proyecto $proyecto, Idiomas $idiomas, Avisos $avisos): array
    {
        /** @var array<string, array<string, array<string, string>>> $ficheros por idioma ('' los comunes), nombre y formato */
        $ficheros = [];

        foreach ($this->entradas($proyecto, Proyecto::DATOS) as $entrada) {
            $fichero = Proyecto::DATOS . "/{$entrada}";
            $extension = pathinfo($entrada, PATHINFO_EXTENSION);

            if (is_dir($proyecto->ruta($fichero))) {
                $avisos->registrar('Las subcarpetas de datos/ no se leen', $fichero);

                continue;
            }

            if (!in_array($extension, self::FORMATOS_DE_DATOS, true)) {
                $avisos->registrar('En datos/ solo se leen ficheros .yml y .json; este se ignora', $fichero);

                continue;
            }

            $nombreEIdioma = self::nombreEIdioma(pathinfo($entrada, PATHINFO_FILENAME), $idiomas);

            if (is_string($nombreEIdioma)) {
                $avisos->registrar("{$nombreEIdioma}; este fichero no se lee", $fichero);

                continue;
            }

            [$nombre, $idioma] = $nombreEIdioma;
            $ficheros[$idioma ?? ''][$nombre][$extension] = $fichero;
        }

        $datos = [];
        $datosPorIdioma = [];

        foreach ($ficheros as $idioma => $porNombre) {
            foreach ($porNombre as $nombre => $porFormato) {
                if (count($porFormato) > 1) {
                    $avisos->registrar("«datos.{$nombre}» sale también de {$porFormato['yml']}; este fichero se ignora", $porFormato['json']);
                }

                $fichero = $porFormato['yml'] ?? $porFormato['json'];
                $texto = $proyecto->leerTexto($fichero);
                $valor = isset($porFormato['yml']) ? Yaml::leer($texto, $fichero) : self::leerJson($texto, $fichero);

                if ($idioma === '') {
                    $datos[$nombre] = $valor;
                } else {
                    $datosPorIdioma[$idioma][$nombre] = $valor;
                }
            }
        }

        return [$datos, $datosPorIdioma];
    }

    /**
     * Las páginas de `contenido/`, con su idioma y su clave (la ruta sin
     * extensión ni idioma, que comparten las traducciones), y los demás
     * ficheros, que se copian tal cual salvo los que empiezan por `_`
     * (formato §4) y los campos comunes de una página (formato §15.7). Rutas
     * relativas a esa carpeta y en orden, para que el resultado no dependa
     * del sistema de ficheros.
     *
     * @return array{
     *     0: list<array{ruta: string, idioma: string, clave: string}>,
     *     1: list<string>,
     *     2: array<string, string>,
     * } las páginas, los ficheros que se copian y los campos comunes por clave
     *
     * @throws ErrorDeProyecto si hay dos ficheros de la misma página en el mismo idioma
     */
    private function recorrerContenido(Proyecto $proyecto, Idiomas $idiomas, Avisos $avisos): array
    {
        if (!is_dir($proyecto->ruta(Proyecto::CONTENIDO))) {
            $avisos->registrar('No hay carpeta contenido/: no se genera ninguna página');

            return [[], [], []];
        }

        $paginas = [];
        $ficheros = [];
        $pendientes = [''];

        while ($pendientes !== []) {
            $carpeta = array_pop($pendientes);

            foreach ($this->entradas($proyecto, Proyecto::CONTENIDO . ($carpeta === '' ? '' : "/{$carpeta}")) as $nombre) {
                $ruta = $carpeta === '' ? $nombre : "{$carpeta}/{$nombre}";

                if (is_dir($proyecto->ruta(Proyecto::CONTENIDO, $ruta))) {
                    $pendientes[] = $ruta;
                } elseif (in_array(pathinfo($nombre, PATHINFO_EXTENSION), self::FORMATOS, true)) {
                    $nombreEIdioma = self::nombreEIdioma(pathinfo($nombre, PATHINFO_FILENAME), $idiomas);

                    if (is_string($nombreEIdioma)) {
                        $avisos->registrar("{$nombreEIdioma}; este fichero no se compila", Proyecto::CONTENIDO . "/{$ruta}");
                    } else {
                        $paginas[$ruta] = [
                            'ruta' => $ruta,
                            'idioma' => $nombreEIdioma[1] ?? $idiomas->predeterminado(),
                            'clave' => $carpeta === '' ? $nombreEIdioma[0] : "{$carpeta}/{$nombreEIdioma[0]}",
                        ];
                    }
                } elseif (preg_match(self::DE_OTROS_GENERADORES, $nombre) === 1) {
                    $avisos->registrar(
                        'Es una plantilla o unos datos de otro generador; no se copia a salida/',
                        Proyecto::CONTENIDO . "/{$ruta}",
                    );
                } elseif (!str_starts_with($nombre, '_')) {
                    FicherosPhp::comprobar(Proyecto::CONTENIDO . "/{$ruta}");
                    $ficheros[] = $ruta;
                } elseif (str_starts_with($nombre, '_datos.') && $nombre !== Cascada::FICHERO && str_ends_with($nombre, '.yml')) {
                    $nombreEIdioma = self::nombreEIdioma(substr($nombre, 0, -4), $idiomas);

                    if (is_string($nombreEIdioma) || $nombreEIdioma[0] !== '_datos') {
                        $motivo = is_string($nombreEIdioma) ? $nombreEIdioma : 'El punto en el nombre está reservado para el idioma (formato §15.2)';
                        $avisos->registrar("{$motivo}; este fichero no se lee", Proyecto::CONTENIDO . "/{$ruta}");
                    }
                }
            }
        }

        ksort($paginas, SORT_STRING);
        sort($ficheros, SORT_STRING);

        /** @var array<string, string> $porClaveEIdioma */
        $porClaveEIdioma = [];

        foreach ($paginas as $pagina) {
            $otra = $porClaveEIdioma["{$pagina['idioma']}:{$pagina['clave']}"] ?? null;

            if ($otra !== null) {
                throw new ErrorDeProyecto(sprintf(
                    '%s y %s son la misma página en el mismo idioma (%s); sobra uno de los dos (formato §15.2)',
                    Proyecto::CONTENIDO . "/{$otra}",
                    Proyecto::CONTENIDO . "/{$pagina['ruta']}",
                    $pagina['idioma'],
                ), Proyecto::CONTENIDO . "/{$pagina['ruta']}");
            }

            $porClaveEIdioma["{$pagina['idioma']}:{$pagina['clave']}"] = $pagina['ruta'];
        }

        // Un .yml con el nombre de una página son sus campos comunes, no un
        // fichero que se publica.
        $claves = array_flip(array_column($paginas, 'clave'));
        $comunes = [];

        foreach ($ficheros as $posicion => $fichero) {
            if (str_ends_with($fichero, '.yml') && isset($claves[substr($fichero, 0, -4)])) {
                $comunes[substr($fichero, 0, -4)] = $fichero;
                unset($ficheros[$posicion]);
            }
        }

        return [array_values($paginas), array_values($ficheros), $comunes];
    }

    /**
     * @param array{ruta: string, idioma: string, clave: string} $encontrada
     * @param array<string, string>                              $comunes    los `.yml` de campos comunes, por clave
     */
    private function leerPagina(Proyecto $proyecto, array $encontrada, array $comunes, Cascada $cascada, Sitio $sitio, Avisos $avisos): Pagina
    {
        ['ruta' => $ruta, 'idioma' => $idioma, 'clave' => $clave] = $encontrada;
        $fichero = Proyecto::CONTENIDO . "/{$ruta}";
        $frontMatter = FrontMatter::separar($proyecto->leerTexto($fichero), $fichero);

        $propios = Campos::normalizar(
            Yaml::leerCampos($frontMatter->yaml, $fichero, $frontMatter->lineaYaml),
            $frontMatter->yaml,
            $frontMatter->lineaYaml,
            $fichero,
            $avisos,
            $sitio->zonaHoraria,
        );

        $carpeta = str_contains($ruta, '/') ? substr($ruta, 0, strrpos($ruta, '/')) : '';
        $campos = $cascada->campos($carpeta, $idioma);

        if (isset($comunes[$clave])) {
            $campos = Cascada::combinar($campos, $cascada->comunes($comunes[$clave]));
        }

        $campos = Cascada::combinar($campos, $propios);
        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $url = Url::resolver("{$clave}.{$extension}", $campos, $fichero, $avisos, $sitio->idiomas->prefijo($idioma));

        // Obligatorio en las páginas HTML, por su <title>; un fragmento o un
        // robots.txt no lo necesitan.
        if ($url !== false && str_ends_with(Url::fichero($url), '.html') && ($campos['titulo'] ?? '') === '') {
            $avisos->registrar('Falta «titulo», el título de la página', $fichero);
        }

        // El PDF sale en una dirección que sale de la de la página (formato §10.3).
        if (($campos['pdf'] ?? false) === true && ($url === false || Url::ficheroPdf($url) === null)) {
            $avisos->registrar('«pdf» solo vale en una página cuya URL acaba en / o en .html; no se genera el PDF', $fichero);
            $campos['pdf'] = false;
        }

        return new Pagina(
            $ruta,
            $extension,
            $campos,
            $url,
            $frontMatter->cuerpo,
            $frontMatter->lineaCuerpo,
            $idioma,
        );
    }

    /**
     * El nombre de un fichero sin el código de idioma, y el idioma, o null si
     * no lleva código (formato §15.2). Si el punto del nombre no es el de un
     * idioma del sitio, el motivo por el que el fichero no se lee.
     *
     * @param string $nombre el nombre sin la extensión: 'contacto.eu'
     *
     * @return array{0: string, 1: string|null}|string
     */
    private static function nombreEIdioma(string $nombre, Idiomas $idiomas): array|string
    {
        $punto = strrpos($nombre, '.');

        if ($punto === false) {
            return [$nombre, null];
        }

        $base = substr($nombre, 0, $punto);
        $codigo = substr($nombre, $punto + 1);

        if (str_contains($base, '.') || !Idiomas::esCodigo($codigo)) {
            return 'El punto en el nombre está reservado para el idioma (formato §15.2)';
        }

        if (!$idiomas->declara($codigo)) {
            return "El punto en el nombre está reservado para el idioma, y «{$codigo}» no es un idioma del sitio (formato §15.2)";
        }

        return [$base, $codigo];
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
