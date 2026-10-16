# Lo que promete la biblioteca y la orden de consola

Ehundu se versiona con [versionado semántico](https://semver.org/lang/es/) y la
promesa de una versión mayor cubre tres cosas: el formato de un proyecto
([`formato.md`](formato.md)), la orden de consola y lo que este documento
llama la biblioteca pública. Dentro de una versión mayor solo se añade; quitar
algo o cambiar su significado es una versión mayor, y lo que va a dejar de
valer avisa durante, al menos, una versión menor antes de quitarse
([`formato.md`](formato.md), §12, reglas 5 y 6).

Lo que no está aquí no se promete, aunque PHP lo deje usar. Las demás clases
de `src/` llevan `@internal` y pueden cambiar o desaparecer en cualquier
versión; de las que están aquí, solo se promete lo que se nombra, y no
cualquier otro método o propiedad pública (`Sitio` es público, pero no
`Sitio::$encabezados`). Los textos de los avisos y de los errores tampoco se
prometen: están pensados para que los lea una persona.

## La orden de consola

    ehundu compilar [carpeta]
    ehundu servir [carpeta] [--puerto=8000] [--borradores] [--completo]
    ehundu desplegar [carpeta] [--simular] [--todo]
    ehundu --ayuda

Las órdenes, sus opciones y lo que hacen son los de `ehundu --ayuda`. El
código de salida es `0` si todo ha ido bien, `1` si el proyecto no se puede
compilar o desplegar, y `2` si la orden está mal escrita. Se prometen los
nombres y el efecto, no el texto que escriben en la terminal.

## La biblioteca

Todas viven en el espacio de nombres `Ehundu\` (el de `Despliegue`, en
`Ehundu\Despliegue\`). El panel de idegest es el primer programa que las
incrusta, y de él sale esta lista.

### Compilar

- **`Compilador`** (interfaz). `compilar(Proyecto $proyecto): Informe`.
- **`CompiladorEnProceso`** implementa `Compilador`: compila el proyecto
  entero y lo escribe en `salida/`, sin depender de la consola, para
  llamarlo desde un proceso web. `__construct(?\DateTimeImmutable $ahora = null)`
  fija el momento con el que se decide qué está publicado. Lanza
  `ErrorDeProyecto` si el proyecto no se puede compilar y no toca `salida/`
  si falla la lectura.
- **`Informe`** es lo que devuelve: `$paginas`, `$ficheros`, `$segundos`,
  `$avisos` (una lista de `Aviso`), `$rehechas` y `resumen()`, una frase.

### Leer un proyecto

- **`Proyecto`**: `Proyecto::abrir(string $ruta)`, `$raiz`, `ruta(string ...$partes)`
  (una ruta dentro del proyecto), `leerTexto(string $fichero)`,
  `ficherosDe(string $carpeta)`, y las constantes con los nombres de las
  carpetas y ficheros del formato: `CONTENIDO`, `DATOS`, `PLANTILLAS`,
  `PARCIALES`, `PUBLICO`, `SALIDA` y `SITIO`.
- **`Lector`**: `leer(Proyecto): Lectura`, el proyecto entero sin compilar, y
  `leerSitio(Proyecto, ?Avisos = null): Sitio`, solo `sitio.yml`.
- **`Lectura`**: `$sitio`, `$datos`, `$paginas` (una lista de `Pagina`,
  ordenadas por ruta), `$avisos` y `datosDe(string $idioma)`, los datos de
  una página de ese idioma.
- **`Sitio`**: `$nombre`, `$url`, `$zonaHoraria`, `$campos` (todo `sitio.yml`
  salvo `despliegue`), `$formato` y `absoluta(string $url)`, la dirección
  completa de una URL del sitio.
- **`Pagina`**: `$ruta`, `$formato` (`md` o `twig`), `$campos`, `$url` (o
  `false` en un fragmento), `$cuerpo`, `$lineaCuerpo`, `$idioma`, `clave()`
  (lo que comparte con sus traducciones), `valor(string $campo)`, `urlPdf()` y
  `estaPublicada(\DateTimeImmutable $ahora, bool $conBorradores = false)`.
- **`Formato`**: `VERSION`, la versión del formato que entiende esta versión
  de Ehundu, y `leer(...)`, que comprueba la que declara un proyecto o un
  esquema ([`formato.md`](formato.md), §2).
- **`Slug`**: `Slug::de(string $texto)`, el filtro `slug` del formato (§5.2), y
  `Slug::sinTildes(string $minusculas)`.

### Avisos y errores

- **`Aviso`**: `$mensaje`, `$fichero`, `$linea` y `__toString()`, con la
  ubicación delante.
- **`Avisos`**: `registrar(string $mensaje, ?string $fichero = null, ?int $linea = null)`,
  `escuchar(\Closure $oyente)` y `todos()`.
- **`ErrorDeProyecto`**, una `\RuntimeException` con `$descripcion`, `$fichero`
  y `$linea`: lo que impide compilar, leer o desplegar un proyecto.

### Desplegar

`Desplegador` hace lo que `ehundu desplegar` ([`despliegue.md`](despliegue.md));
las demás clases son las piezas con las que se compone, para un programa que
quiera llevar el despliegue a su manera, como hace el panel.

- **`Despliegue\Desplegador`**: `__construct(Compilador $compilador = ..., ?\Closure $abrir = null)`,
  `desplegar(Proyecto, bool $simular, bool $todo, array $secretos, ...)`, que
  devuelve un `InformeDeDespliegue`, y `ejecutar(PlanDeDespliegue, Proyecto, Destino, ?Manifiesto, ...)`,
  que sube un plan ya trazado.
- **`Despliegue\Configuracion`**: `Configuracion::leer(Proyecto, array $secretos, Avisos)`,
  la sección `despliegue` de `sitio.yml` con sus secretos; sus propiedades
  (`$destino`, `$servidor`, `$puerto`, `$usuario`, `$ruta`, `$huella`,
  `$cifrado`, `$region`, `$cubo`, `$clave`, `$clavePrivada` y `$frase`) y
  `descripcion()`, sin secretos. Un `var_dump` no los enseña.
- **`Despliegue\Manifiesto`**: lo que Ehundu subió, que vive en el destino
  (`.ehundu.json`): `FICHERO`, `$url`, `leer(string $json)`, `ficheros()`,
  `anotar(...)`, `quitar(...)` y `json()`.
- **`Despliegue\PlanDeDespliegue`**: qué subir y qué borrar:
  `PlanDeDespliegue::trazar(...)`, `$subidas`, `$borrados`, `$sinCambios`,
  `$url`, `bytes()`, `estaVacio()` y `carpetasQueSobran(array $quedan)`.
- **`Despliegue\InformeDeDespliegue`**: `$compilacion` (un `Informe`),
  `$destino`, `$subidos`, `$bytes`, `$borrados`, `$sinCambios`, `$avisos`,
  `$segundos`, `$simulado` y `resumen()`.
- **`Despliegue\Destino`** (interfaz): a dónde se sube, con `leer`, `subir`,
  `escribir`, `borrar`, `quitarCarpeta` y `cerrar`. Un programa puede
  implementarla para llevar el sitio a un destino que Ehundu no trae.
- **`Despliegue\Carpeta`**, **`Despliegue\Ftp`**, **`Despliegue\Sftp`** y
  **`Despliegue\S3`** implementan `Destino` y se construyen con una
  `Configuracion` (`Carpeta` con la ruta y el `Proyecto`).
