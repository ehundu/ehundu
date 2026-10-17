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

### Compilar por lotes

Para un proceso web que no puede dedicarle de una vez todo lo que hace falta
([`formato.md`](formato.md), §10.4). Por dónde va se guarda en
`.ehundu/lote.json`, en el proyecto.

El presupuesto de `avanzar` (`$segundos`), igual en los lotes, en la incremental y
en el `Desplegador`, se cuenta desde que empieza la llamada. Uno negativo o que no
es un número (`NAN`) vale 0, que sigue haciendo una tarea, la mínima; y uno de más
de un día, un día, de modo que `INF` o `PHP_INT_MAX` quieren decir «sin límite».

- **`CompiladorPorLotes`** (interfaz): `iniciar(Proyecto): Lote`, que empieza un
  lote nuevo, descarta el que hubiera y comprueba, antes de escribir nada, lo
  que detendría una compilación completa; `avanzar(Proyecto, float $segundos): Lote`,
  que retoma el lote que haya y hace tareas hasta agotar el presupuesto, y
  siempre al menos una (si no hay lote, empieza uno; si el proyecto ha
  cambiado, empieza de nuevo; si está completo, no hace nada);
  `estado(Proyecto): ?Lote`, sin hacer nada; y `abandonar(Proyecto)`.
- **`CompiladorPorLotesEnProceso`** la implementa. `__construct(?\DateTimeImmutable $ahora = null)`
  fija el momento con el que se decide qué está publicado; se guarda al empezar
  el lote y no cambia en todo él. Cada tarea se escribe en `salida/` al hacerse,
  así que `salida/` mezcla páginas nuevas y viejas hasta que el lote está completo.
  Lanza `ErrorDeProyecto` si una tarea falla.
- **`Lote`**: `$hechas`, `$total`, `completo()`, `progreso()` (de 0 a 1),
  `informe()` (un `Informe`, cuando está completo), `reiniciado()` (si esta
  llamada ha empezado de nuevo porque el proyecto cambió) y `ocupado()` (si otra
  llamada estaba avanzando y esta no ha hecho nada).

### Compilar de forma incremental

Para un proceso web que compila el mismo sitio una y otra vez, sobre todo si es
grande ([`formato.md`](formato.md), §10.5). Lo que recuerda entre una compilación
y la siguiente se guarda en `.ehundu/memoria.bin`, en el proyecto: un cambio en
una página rehace unas pocas, aunque la compilación anterior fuera de otro
proceso, y el resultado es siempre el de una compilación completa.

- **`CompiladorIncrementalEnProceso`** implementa `Compilador`.
  `__construct(?\DateTimeImmutable $ahora = null)` fija el momento con el que se
  decide qué está publicado. `compilar(Proyecto): Informe` compila de una vez
  (si otra llamada está compilando el proyecto, espera a que termine);
  `$informe->rehechas` dice cuántas páginas se han rehecho. Sin una memoria que
  valga (la primera vez, tras cambiar de versión del motor, o si se borra
  `.ehundu/`) hace una compilación completa. Lanza `ErrorDeProyecto` si el
  proyecto no se puede compilar, y entonces deja la memoria y `salida/` como
  estaban.
- **`avanzar(Proyecto, float $segundos): Lote`** hace lo mismo con un
  presupuesto, como los lotes: rehace páginas hasta agotarlo, y siempre al
  menos una, guarda lo hecho y devuelve un `Lote` (ver arriba) que dice si
  está completo. La llamada siguiente sigue donde se quedó, también si el
  proyecto ha cambiado entretanto: solo se rehace lo que el cambio afecta. Hasta
  que el `Lote` está completo, `salida/` mezcla páginas nuevas y viejas. Una
  llamada que llega mientras otra está avanzando no espera: devuelve
  `ocupado()`.
- **`olvidar(Proyecto)`** borra la memoria: la siguiente compilación será
  completa.

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
quiera llevar el despliegue a su manera, como hace un editor web que las usa.

- **`Despliegue\Desplegador`**: `__construct(Compilador $compilador = ..., ?\Closure $abrir = null)`,
  `desplegar(Proyecto, bool $simular, bool $todo, array $secretos, ..., ?float $segundos, ?Informe $compilado)`,
  que devuelve un `InformeDeDespliegue`, y `ejecutar(PlanDeDespliegue, Proyecto, Destino, ?Manifiesto, ..., ?float $segundos): bool`,
  que sube un plan ya trazado y dice si lo ha hecho entero. Con `$segundos`, al
  agotarse guarda el manifiesto y para (siempre hace al menos un fichero), y la
  llamada siguiente sigue donde se quedó; con `$compilado`, el `Informe` de una
  compilación ya hecha (por lotes, por ejemplo), no vuelve a compilar. Con `$todo`
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
