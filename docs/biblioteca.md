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
`Sitio::$encabezados`). Los nombres de los parámetros de lo que se promete son
parte de la API, porque se pueden pasar por nombre (`segundos:`, `compilado:`).
Los textos de los avisos y de los errores no se prometen: están pensados para que
los lea una persona.

## La orden de consola

    ehundu compilar [carpeta]
    ehundu servir [carpeta] [--puerto=8000] [--borradores] [--completo]
    ehundu desplegar [carpeta] [--simular] [--todo]
    ehundu --ayuda
    ehundu --version

Las órdenes, sus opciones y lo que hacen son los de `ehundu --ayuda`; `-h` y
`--help` son lo mismo que `--ayuda`, y `-V`, lo mismo que `--version`.
`ehundu --version` escribe `Ehundu` y el número de la versión, y sale con `0`. El
código de salida es `0` si todo ha ido bien, `1` si el proyecto no se puede
compilar o desplegar, y `2` si la orden está mal escrita o no hay ninguna (entonces
escribe la ayuda en el error estándar). Un fallo del propio motor, que no es un
error de proyecto, sale con el `255` de PHP. Se prometen los nombres y el efecto,
no el texto que escriben en la terminal.

## La biblioteca

Todas viven en el espacio de nombres `Ehundu\` (el de `Despliegue`, en
`Ehundu\Despliegue\`). La lista sale de lo que usa el primer programa que las
incrusta, un editor web.

### Compilar

- **`Compilador`** (interfaz). `compilar(Proyecto $proyecto): Informe`. Otros
  programas pueden implementarla (el `Desplegador` acepta cualquier `Compilador`),
  y por eso no crece dentro de una versión mayor: lo que falte vendría como una
  interfaz aparte. Lo mismo vale para `Despliegue\Destino`.
- **`CompiladorEnProceso`** implementa `Compilador`: compila el proyecto
  entero y lo escribe en `salida/`, sin depender de la consola, para
  llamarlo desde un proceso web. `__construct(?\DateTimeImmutable $ahora = null)`
  fija el momento con el que se decide qué está publicado. Lanza
  `ErrorDeProyecto` si el proyecto no se puede compilar y no toca `salida/`
  si falla la lectura.
- **`Informe`** es lo que devuelve: `$paginas`, `$ficheros`, `$segundos`,
  `$avisos` (una lista de `Aviso`), `$rehechas` y `resumen()`, una frase.
  `$rehechas` es siempre un número: las páginas que se han construido de nuevo,
  que en una compilación completa son todas (`$paginas`). Un programa puede
  construir uno: `Informe(paginas:, avisos:, segundos:, ficheros: 0, rehechas: null)`,
  y `rehechas` sin decir vale `paginas`.

`CompiladorPorLotes` y `CompiladorPorLotesEnProceso`, la compilación por lotes de
la 0.12 ([`formato.md`](formato.md), §10.4), **no se prometen**: son `@internal`
(decisión 128). Para repartir una compilación en llamadas con un presupuesto de
tiempo se usa `avanzar`, en la compilación incremental (más abajo).

### Compilar de forma incremental

Para un proceso web que compila el mismo sitio una y otra vez, sobre todo si es
grande ([`formato.md`](formato.md), §10.5). Lo que recuerda entre una compilación
y la siguiente se guarda en `.ehundu/memoria.bin`, en el proyecto: un cambio en
una página rehace unas pocas, aunque la compilación anterior fuera de otro
proceso, y el resultado es el de una compilación completa, salvo en lo que no
depende del proyecto: `random()` y la fecha del momento (`'now'|date`) de una
página que no se rehace se quedan como estaban (`compilacion.md`).

- **`CompiladorIncrementalEnProceso`** implementa `Compilador`.
  `__construct(?\DateTimeImmutable $ahora = null)` fija el momento con el que se
  decide qué está publicado. `compilar(Proyecto): Informe` compila de una vez
  (si otra llamada está compilando el proyecto, espera a que termine, como mucho
  un minuto; pasado ese tiempo lanza `ErrorDeProyecto`, y lo mismo hace `olvidar`);
  `$informe->rehechas` dice cuántas páginas se han rehecho. Sin una memoria que
  valga (la primera vez, tras cambiar de versión del motor, o si se borra
  `.ehundu/`) hace una compilación completa. Lanza `ErrorDeProyecto` si el
  proyecto no se puede compilar, y entonces deja la memoria y `salida/` como
  estaban. Si la memoria guardada no cabe en el `memory_limit` de PHP (pide unas
  tres veces lo que mide el fichero más 16 MB), compila entero sin usarla ni
  tocarla y da un aviso que dice a cuánto subir el límite.
- **`avanzar(Proyecto, float $segundos): Lote`** hace lo mismo con un
  presupuesto: rehace páginas hasta agotarlo, y siempre al menos una, guarda lo
  hecho y devuelve un `Lote` que dice si está completo. La llamada siguiente
  sigue donde se quedó, también si el proyecto ha cambiado entretanto: solo se
  rehace lo que el cambio afecta. Hasta que el `Lote` está completo, `salida/`
  mezcla páginas nuevas y viejas, y nada debe publicarse. Una llamada que llega
  mientras otra está avanzando no espera: devuelve `ocupado()`. Si la memoria
  guardada no cabe en el `memory_limit` de PHP, lanza `ErrorDeProyecto` con lo que
  ocupa, lo que pide y a cuánto subir el límite, porque sin ella no puede repartir
  el trabajo.
- **`olvidar(Proyecto)`** borra la memoria: la siguiente compilación será
  completa.
- **`Lote`**, lo que devuelve `avanzar`: `$hechas`, `$total`, `completo()`,
  `progreso()` (de 0 a 1; 0 en un lote `ocupado()`), `informe()` (un `Informe`,
  cuando está completo) y
  `ocupado()` (si otra llamada estaba avanzando y esta no ha hecho nada).
  `$hechas` y `$total` cuentan páginas en `avanzar`; para saber cómo va una
  compilación, lo que se promete es `progreso()` y `completo()`, y los números
  sueltos son de quien devuelve el `Lote`.

El presupuesto de `avanzar` (`$segundos`), igual en el `Desplegador`, se cuenta
desde que empieza la llamada. Uno negativo o que no es un número (`NAN`) vale 0,
que sigue haciendo una tarea, la mínima; y uno de más de un día, un día, de modo
que `INF` o `PHP_INT_MAX` quieren decir «sin límite». El presupuesto incluye la
preparación de la llamada (cargar la memoria, leer el proyecto, decidir qué se
rehace), y la llamada puede acabar después de él: guardar la memoria viene tras
el último trabajo. Con un presupuesto menor que la preparación, cada llamada
rehace una sola página y vuelve a guardar la memoria entera: acaba, pero cada vez
más despacio. Un presupuesto útil es varias veces la preparación.

La memoria pesa del orden de `salida/` más lo leído (97 MB en un sitio de 1.200
páginas) y se carga en una fracción de segundo; el proceso que la usa necesita
memoria de PHP para ella: unas tres veces lo que mide el fichero más 16 MB como
mucho (en ese sitio, entre 170 y 250 MB según lo que se rehaga; las medidas, en
`compilacion.md`). La cifra no se promete; el resguardo del `memory_limit` sí.

### Leer un proyecto

- **`Proyecto`**: `Proyecto::abrir(string $ruta)`, `$raiz`, `ruta(string ...$partes)`
  (una ruta absoluta dentro del proyecto, con `/`), `leerTexto(string $fichero)`
  (sin BOM, con `\n` por final de línea, y exige UTF-8: si no, lanza
  `ErrorDeProyecto`), `ficherosDe(string $carpeta)` (los ficheros de esa carpeta
  y de sus subcarpetas, también los que empiezan por punto, con rutas relativas a
  ella y ordenadas byte a byte), y las constantes con los nombres de las
  carpetas y ficheros del formato: `CONTENIDO`, `DATOS`, `PLANTILLAS`,
  `PARCIALES`, `PUBLICO`, `SALIDA` y `SITIO`.
- **`Lector`**: `leer(Proyecto): Lectura`, el proyecto entero sin compilar, y
  `leerSitio(Proyecto, ?Avisos = null): Sitio`, solo `sitio.yml`. `leer` da las
  páginas tal como están escritas: las que usan `agrupar` o `paginar` salen sin
  expandir, y una plantilla de grupos tiene `$url === false`, como un fragmento.
  Sin `Avisos`, `leerSitio` pierde los avisos sin decirlo.
- **`Lectura`**: `$sitio`, `$datos`, `$paginas` (una lista de `Pagina`,
  ordenadas por ruta), `$avisos` y `datosDe(string $idioma)`, los datos de
  una página de ese idioma.
- **`Sitio`**: `$nombre`, `$url`, `$zonaHoraria`, `$campos` (todo `sitio.yml`
  salvo `despliegue`: `url` sin la barra final, y `idioma` e `idiomas`, la lista
  de `{codigo, nombre, url}`, siempre, aunque el fichero no los declare; es la única
  vía pública a los idiomas del sitio), `$formato` y `absoluta(string $url)`, la
  dirección completa de una URL del sitio.
- **`Pagina`**: `$ruta`, `$formato` (`md` o `twig`), `$campos`, `$url` (o
  `false` en un fragmento), `$cuerpo`, `$lineaCuerpo`, `$idioma`, `clave()`
  (lo que comparte con sus traducciones), `valor(string $campo)`, `urlPdf()` y
  `estaPublicada(\DateTimeImmutable $ahora, bool $conBorradores = false)`.
  `$campos` es lo que queda de la cascada (el `_datos.yml`, el `.yml` común de la
  página y su front matter, formato §3) como lo deja el motor (formato §4): los
  nombres ingleses pasan a su nombre en español; `fecha` y `publicar` son
  `\DateTimeImmutable` (el día, a medianoche, en la zona del sitio), y un día sin
  hora de cualquier otro campo también; `borrador`, `listada`, `pdf` y
  `publicarEnSuFecha` son `bool`; `orden` es un número; `titulo`, `subtitulo`,
  `descripcion`, `imagen`, `imagenAlt` e `icono` son siempre texto; `etiquetas`,
  `css` y `js` son listas de textos; `plantilla` es un texto o `false`; `url` es
  un texto o `false`, el patrón o la dirección tal como se escribió (la ya
  resuelta es `$url`); `agrupar` y `paginar` salen normalizados como mapas; un
  campo en blanco está, como `null`; el resto, como lo lee YAML. Un valor que no
  valía se avisó y no está. `valor()` da además los nombres que escribe el motor
  (`url`, `ruta`, `idioma`, `pdf`: la dirección del PDF o `null`), `null` de un
  campo que no existe, y de una `url` que es `false`, `null`. `Lectura::$datos` son los ficheros de `datos/` tal como los leen YAML y
  JSON.
- **`Formato`**: `VERSION`, la versión del formato que entiende esta versión
  de Ehundu, y `leer(mixed $valor, string $fichero, ?int $linea): int`, que
  comprueba la que declara un proyecto o un esquema ([`formato.md`](formato.md),
  §2): un valor que no es un entero mayor que cero, o es mayor que `VERSION`,
  lanza `ErrorDeProyecto`; una clave ausente (`null`) da `1`, y un decimal (`1.0`)
  o un texto (`"1"`) lanzan. El motor la usa con `sitio.yml`; no lee el esquema
  (§11), pero un editor puede usarla con el suyo.
- **`Slug`**: `Slug::de(string $texto)`, el filtro `slug` del formato (§5.2).
  Los demás métodos de `Slug` no se prometen.
- **`Version`**: `Version::ACTUAL`, la versión de Ehundu como texto: `0.15.0` en una
  publicada y `0.16.0-dev` entre una y otra. Es la que escribe `ehundu --version`.
  No es la versión del formato (`Formato::VERSION`).

### Avisos y errores

- **`Aviso`**: `$mensaje`, `$fichero`, `$linea` y `__toString()`, con la
  ubicación delante. Un programa puede construir uno: `Aviso(mensaje:, fichero: null, linea: null)`.
- **`Avisos`**: `registrar(string $mensaje, ?string $fichero = null, ?int $linea = null)`,
  `escuchar(\Closure $oyente)` y `todos()`. `escuchar` sustituye al oyente
  anterior: solo hay uno, y recibe todos los avisos según se registran, también
  los repetidos, que `todos()` junta en uno.
- **`ErrorDeProyecto`**, una `\RuntimeException` con `$descripcion`, `$fichero`
  y `$linea`: lo que impide compilar, leer o desplegar un proyecto. Un programa
  que implemente `Compilador` o `Destino` lo lanza con
  `ErrorDeProyecto(string $descripcion, ?string $fichero = null, ?int $linea = null, ?\Throwable $anterior = null)`.

Lo que lanza la biblioteca es `ErrorDeProyecto`, con tres excepciones que se
prometen: `Configuracion::leer` lanza `\InvalidArgumentException` si en
`$secretos` hay una clave que no es `clave`, `clavePrivada` ni `frase`;
`Manifiesto::leer`, `\UnexpectedValueException` si el texto no es un manifiesto
o una de sus rutas sale del sitio; y `Desplegador::desplegar` y `ejecutar`
vuelven a lanzar, después de guardar el manifiesto, lo que lance un `Destino` que
no es de Ehundu. Cualquier otra excepción (un `\TypeError`, por ejemplo) es un fallo del
motor y sale tal cual.

### Desplegar

`Desplegador` hace lo que `ehundu desplegar` ([`despliegue.md`](despliegue.md));
las demás clases son las piezas con las que se compone, para un programa que
quiera llevar el despliegue a su manera, como hace un editor web que las usa.

- **`Despliegue\Desplegador`**: `__construct(Compilador $compilador = ..., ?\Closure $abrir = null)`
  (`$abrir` es para las pruebas y no se promete),
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
  se ignora `$segundos` y se sube todo en una llamada: repartido, cada llamada
  empezaría de cero y no terminaría nunca.
- **`Despliegue\Configuracion`**: `Configuracion::leer(Proyecto $proyecto, array $secretos = [], Avisos $avisos = new Avisos())`,
  la sección `despliegue` de `sitio.yml` con sus secretos (`$clavePrivada` es la
  ruta de un fichero, no su contenido: relativa al proyecto, absoluta o con `~/`); sus propiedades
  (`$destino`, `$servidor`, `$puerto`, `$usuario`, `$ruta`, `$huella`,
  `$cifrado`, `$region`, `$cubo`, `$clave`, `$clavePrivada` y `$frase`),
  `descripcion()`, sin secretos, y `abrir(Proyecto $proyecto, Avisos $avisos): Destino`,
  que abre el destino que dice la configuración (`Carpeta` crea su carpeta al subir
  el primer fichero, no antes, así que simular no deja nada). Un `var_dump` no
  enseña ni los secretos ni dónde está la clave privada.
- **`Despliegue\Manifiesto`**: lo que Ehundu subió, que vive en el destino
  (`.ehundu.json`): `FICHERO`, `$url`, `leer(string $json)`, `ficheros()`,
  `anotar(string $ruta, string $md5, int $tamano)`, `quitar(string $ruta)` y
  `json()`. Un programa puede construir uno:
  `Manifiesto(ficheros: [], url: null)`. Un Ehundu 1.x lee el manifiesto que dejó
  cualquier versión anterior ([`formato.md`](formato.md), §10).
- **`Despliegue\PlanDeDespliegue`**: qué subir y qué borrar:
  `PlanDeDespliegue::trazar(array $ficheros, ?Manifiesto $anterior, bool $todo = false, ?string $url = null)`, `$subidas`, `$borrados`, `$sinCambios`,
  `$url`, `bytes()`, `estaVacio()` y `carpetasQueSobran(array $quedan)`.
  `PlanDeDespliegue::deSalida(Proyecto, ?Manifiesto $anterior = null, bool $todo = false, ?string $url = null)`
  traza el plan de la `salida/` de un proyecto ya compilado, y
  `PlanDeDespliegue::ficheros(Proyecto)` da los ficheros de `salida/` con su MD5
  y su tamaño, que es lo que se compara con el manifiesto: quien llama no tiene
  que calcularlos.
- **`Despliegue\InformeDeDespliegue`**: `$compilacion` (un `Informe`),
  `$destino`, `$subidos`, `$bytes`, `$borrados`, `$sinCambios`, `$avisos`,
  `$segundos`, `$simulado`, `$completo` (falso si se ha parado porque se agotó el
  tiempo), `$pendientes` (cuántos ficheros quedan, entonces) y `resumen()`.
- **`Despliegue\Destino`** (interfaz): a dónde se sube. `leer(string $ruta): ?string`
  da el contenido de un fichero, o `null` si no existe; `subir(string $ruta, string $origen): void`
  copia un fichero local; `escribir(string $ruta, string $contenido): void`;
  `borrar(string $ruta): void`, que no hace nada si no existe;
  `quitarCarpeta(string $ruta): void`, que no hace nada si no está vacía; y
  `cerrar(): void`. Las rutas son relativas al destino y sin barra al principio,
  y un fallo es un `ErrorDeProyecto`. Un programa puede implementarla para llevar
  el sitio a un destino que Ehundu no trae.
- **`Despliegue\Carpeta`**, **`Despliegue\Ftp`**, **`Despliegue\Sftp`** y
  **`Despliegue\S3`** implementan `Destino`. Se abren con
  `Configuracion::abrir`: sus constructores no se prometen, y los parámetros
  que traen para las pruebas tampoco.
