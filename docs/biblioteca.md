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
    ehundu --version

Las órdenes, sus opciones y lo que hacen son los de `ehundu --ayuda`.
`ehundu --version` escribe `Ehundu` y el número de la versión, y sale con `0`. El
código de salida es `0` si todo ha ido bien, `1` si el proyecto no se puede
compilar o desplegar, y `2` si la orden está mal escrita. Se prometen los
nombres y el efecto, no el texto que escriben en la terminal.

## La biblioteca

Todas viven en el espacio de nombres `Ehundu\` (el de `Despliegue`, en
`Ehundu\Despliegue\`). La lista sale de lo que usa el primer programa que las
incrusta, un editor web.

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

`CompiladorPorLotes` y `CompiladorPorLotesEnProceso`, la compilación por lotes de
la 0.12 ([`formato.md`](formato.md), §10.4), **no se prometen**: son `@internal`
(decisión 128). Para repartir una compilación en llamadas con un presupuesto de
tiempo se usa `avanzar`, en la compilación incremental (más abajo).

### Compilar de forma incremental

Para un proceso web que compila el mismo sitio una y otra vez, sobre todo si es
grande ([`formato.md`](formato.md), §10.5). Lo que recuerda entre una compilación
y la siguiente se guarda en `.ehundu/memoria.bin`, en el proyecto: un cambio en
una página rehace unas pocas, aunque la compilación anterior fuera de otro
proceso, y el resultado es siempre el de una compilación completa.

- **`CompiladorIncrementalEnProceso`** implementa `Compilador`.
  `__construct(?\DateTimeImmutable $ahora = null)` fija el momento con el que se
  decide qué está publicado. `compilar(Proyecto): Informe` compila de una vez
  (si otra llamada está compilando el proyecto, espera a que termine, como mucho
  un minuto; pasado ese tiempo lanza `ErrorDeProyecto`, y lo mismo hace `olvidar`);
  `$informe->rehechas` dice cuántas páginas se han rehecho. Sin una memoria que
  valga (la primera vez, tras cambiar de versión del motor, o si se borra
  `.ehundu/`) hace una compilación completa. Lanza `ErrorDeProyecto` si el
  proyecto no se puede compilar, y entonces deja la memoria y `salida/` como
  estaban.
- **`avanzar(Proyecto, float $segundos): Lote`** hace lo mismo con un
  presupuesto: rehace páginas hasta agotarlo, y siempre al menos una, guarda lo
  hecho y devuelve un `Lote` que dice si está completo. La llamada siguiente
  sigue donde se quedó, también si el proyecto ha cambiado entretanto: solo se
  rehace lo que el cambio afecta. Hasta que el `Lote` está completo, `salida/`
  mezcla páginas nuevas y viejas, y nada debe publicarse. Una llamada que llega
  mientras otra está avanzando no espera: devuelve `ocupado()`.
- **`olvidar(Proyecto)`** borra la memoria: la siguiente compilación será
  completa.
- **`Lote`**, lo que devuelve `avanzar`: `$hechas`, `$total`, `completo()`,
  `progreso()` (de 0 a 1), `informe()` (un `Informe`, cuando está completo) y
  `ocupado()` (si otra llamada estaba avanzando y esta no ha hecho nada).

El presupuesto de `avanzar` (`$segundos`), igual en el `Desplegador`, se cuenta
desde que empieza la llamada. Uno negativo o que no es un número (`NAN`) vale 0,
que sigue haciendo una tarea, la mínima; y uno de más de un día, un día, de modo
que `INF` o `PHP_INT_MAX` quieren decir «sin límite».

La memoria pesa del orden de `salida/` más lo leído (97 MB en un sitio de 1.200
páginas) y se carga en una fracción de segundo; el proceso que la usa necesita
memoria para ella (unos 350 MB en ese sitio).

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
  `$campos` es el front matter como lo deja el motor (formato §4): los nombres
  ingleses pasan a su nombre en español; `fecha` y `publicar` son
  `\DateTimeImmutable` (el día, a medianoche, en la zona del sitio); `borrador`,
  `listada`, `pdf` y `publicarEnSuFecha` son `bool`; `orden` es un número;
  `etiquetas`, `css` y `js` son listas de textos; `plantilla` es un texto o
  `false`; el resto, como lo lee YAML. Un valor que no valía se avisó y no está.
  `valor()` da además los nombres que escribe el motor (`url`, `ruta`, `idioma`,
  `pdf`). `Lectura::$datos` son los ficheros de `datos/` tal como los leen YAML y
  JSON.
- **`Formato`**: `VERSION`, la versión del formato que entiende esta versión
  de Ehundu, y `leer(mixed $valor, string $fichero, ?int $linea): int`, que
  comprueba la que declara un proyecto o un esquema ([`formato.md`](formato.md),
  §2): un valor que no es un entero mayor que cero, o es mayor que `VERSION`,
  lanza `ErrorDeProyecto`. El motor la usa con `sitio.yml`; no lee el esquema
  (§11), pero un editor puede usarla con el suyo.
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
quiera llevar el despliegue a su manera, como hace un editor web que las usa.

- **`Despliegue\Desplegador`**: `__construct(Compilador $compilador = ..., ?\Closure $abrir = null)`,
  `desplegar(Proyecto $proyecto, bool $simular = false, bool $todo = false, array $secretos = [], ?\Closure $alAvanzar = null, ?\Closure $alCompilar = null, ?\Closure $alAvisar = null, ?float $segundos = null, ?Informe $compilado = null)`,
  que devuelve un `InformeDeDespliegue`, y `ejecutar(PlanDeDespliegue $plan, Proyecto $proyecto, Destino $destino, ?Manifiesto $anterior, ?\Closure $alAvanzar = null, ?float $segundos = null): bool`,
  que sube un plan ya trazado y dice si lo ha hecho entero. Los nombres de los
  parámetros forman parte de la API, porque se pasan por nombre (`segundos:`,
  `compilado:`): `alAvanzar` se llama con «subido» o «borrado» y la ruta, según
  se va haciendo; `alCompilar`, con el `Informe` de la compilación antes de
  conectar con el destino; y `alAvisar`, con cada `Aviso` según sale. Con `$segundos`, al
  agotarse guarda el manifiesto y para (siempre hace al menos un fichero), y la
  llamada siguiente sigue donde se quedó; con `$compilado`, el `Informe` de una
  compilación ya hecha (repartida con `avanzar`, por ejemplo), no vuelve a compilar. Con `$todo`
  no se reparte: cada llamada empezaría de cero; la primera lleva `$todo` y las
  siguientes no.
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
  `$segundos`, `$simulado`, `$completo` (falso si se ha parado porque se agotó el
  tiempo), `$pendientes` (cuántos ficheros quedan, entonces) y `resumen()`.
- **`Despliegue\Destino`** (interfaz): a dónde se sube, con `leer`, `subir`,
  `escribir`, `borrar`, `quitarCarpeta` y `cerrar`. Un programa puede
  implementarla para llevar el sitio a un destino que Ehundu no trae.
- **`Despliegue\Carpeta`**, **`Despliegue\Ftp`**, **`Despliegue\Sftp`** y
  **`Despliegue\S3`** implementan `Destino` y se construyen con una
  `Configuracion` (`Carpeta` con la ruta y el `Proyecto`).
