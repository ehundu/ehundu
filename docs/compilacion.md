# Compilación incremental

`ehundu compilar` construye siempre el sitio entero. `ehundu servir`, en
cambio, recuerda la última compilación y, cuando cambia algo, rehace solo las
páginas a las que afecta ese cambio. El resultado tiene que ser exactamente el
de una compilación completa: los mismos ficheros, con el mismo contenido, y
los mismos avisos. Si no se puede asegurar, se rehace todo.

Con `ehundu servir --completo` la previsualización rehace todo en cada
cambio, como antes; sirve para descartar la compilación incremental cuando
algo no cuadra.

## Qué se recuerda

Mientras construye una página, el motor apunta todo lo que usa: qué
plantillas y parciales carga (también los que busca y no existen), qué
ficheros de `publico/` mira, incrusta o comprueba, qué colecciones recorre,
de qué otras páginas muestra el contenido y de qué páginas mira las
traducciones. Apunta también el CSS y el JS que
declara y los avisos que da. El PDF de una página (`pdf: sí`, formato §10.3) se
construye aparte, pero su registro se suma al de la página: se rehace todo
junto cuando cambia algo de lo que usaron la página o su PDF. Del PDF se anota
también lo que dompdf lee de `publico/` por su cuenta (imágenes, tipografías,
CSS que carga), y lo que el HTML pide aunque no exista todavía, para que un
fichero que aparece rehaga el PDF. Lo mismo hace con el cuerpo de cada página, que
se convierte una vez y se reutiliza donde se muestre. Lo que usa un cuerpo lo
usa también la página que lo muestra, así que cada registro está completo
por sí solo.

Las variantes de una imagen (formato §2 y §7.3) se apuntan como cualquier otro
fichero de `publico/`: `variantes()`, el atajo `imagen` y las figuras del
Markdown anotan cada fichero `<tronco>-<ancho>.<formato>` que buscan, exista o
no, así que la página se rehace cuando aparece, desaparece o cambia uno. La
sección `imagenes` es de `sitio.yml`, y su cambio rehace todo.

Además de esos registros, se recuerda lo que se leyó del proyecto y una
huella de cada fichero de `plantillas/`, `parciales/` y `publico/`: su tamaño,
su fecha y, si pesa menos de un mega, un resumen de su contenido. La fecha
sola no basta, porque PHP la da en segundos y dos cambios del mismo tamaño
dentro del mismo segundo la dejarían igual. Para no leer cada vez todos los
ficheros, el resumen se reutiliza mientras no cambien el tamaño ni la fecha,
salvo si el fichero se ha tocado en los dos últimos segundos. El vigilante de
la previsualización usa las mismas huellas.

## Qué se rehace

En cada compilación se vuelve a leer el proyecto entero, que es rápido, y se
compara con lo anterior. Si han cambiado `sitio.yml` o algo de `datos/`, que
llegan a todas las plantillas, se rehace todo. También si la compilación
anterior falló, si se activan o desactivan los borradores, o si no hay una
compilación anterior.

Si no, se rehace una página cuando cambia su fichero, cuando aparece o pasa a
publicarse, o cuando ha cambiado algo de lo que apuntó: una plantilla o un
parcial, un fichero de `publico/`, una colección o el contenido de otra
página. Una colección cambia cuando entra o sale una página con su etiqueta, o
cuando una de sus páginas cambia de campos o de URL; un cambio solo en el
cuerpo de una página no cambia la colección, pero sí rehace las páginas que
muestran ese cuerpo. Las traducciones de una página cambian igual: cuando
aparece, desaparece o se publica una de sus versiones, o cuando cambia de
campos o de URL. Los datos de cada idioma (`menus.eu.yml`) son parte de
`datos/`, y su cambio rehace todo; los `_datos.eu.yml` y los campos comunes
de una página cambian los campos de las páginas que los heredan, como
cualquier `_datos.yml`. Si aparece o desaparece un atajo del sitio, cambian todos
los cuerpos en Markdown, porque cambia cómo se lee el texto. El sitemap y el
feed se generan siempre de nuevo, y la lista de ficheros que se copian también.

Las páginas que genera una página con `agrupar` (formato §6.3) no tienen nada
de especial para todo esto. Se deciden en cada compilación, justo después de
leer el proyecto y antes de nada más, y lo que se recuerda es la lectura ya
con ellas: la página que define los grupos no está y en su lugar están las
generadas, cada una con su ruta (`etiqueta.md#ideas`) y las páginas de su
grupo. Por eso una etiqueta nueva es una página nueva y la última página de una
etiqueta, una página que desaparece, y quien lista las generadas a través de
una colección se entera como con cualquier otra. Una página generada se rehace
cuando cambia lo que la define (el fichero de la plantilla de grupos, que ellas
copian) o su grupo (`Pagina::$miembros`: que entre, salga o cambie de sitio una
página). Que cambien los campos de una de las páginas del grupo se anota
aparte, página por página (`Registro::$campos`): la generada se rehace si
cambia el título de uno de sus artículos, pero no si solo cambia su cuerpo. El
grupo cuenta también como un campo de la página generada para las colecciones:
si gana o pierde una página, se rehace quien lee el recuento a través de
`coleccion()`.

Las listas que parte `paginar` (formato §6.4) funcionan igual. La serie se
decide en cada compilación, justo después de las de `agrupar`: la primera
página es la propia página, con sus rutas de trozo, y las demás son páginas
generadas (`blog/index.md#2`), sin traducciones y sin colecciones. El trozo de
cada una (`Pagina::$miembros`) y su `paginacion` (que es un campo más) cuentan
como cambios de la página: entrar, salir o cambiar de sitio un artículo rehace
las páginas de la serie a las que toca, y si cambia cuántas hay, o sus
direcciones, las de toda la serie. Los campos de cada artículo se anotan uno a
uno, como con `agrupar`, así que un título cambiado rehace solo la página en la
que sale, y un cuerpo cambiado, ninguna. Una página de la serie que ya no existe
deja de escribirse.

Lo que no se rehace se aprovecha tal cual, y sus avisos se repiten, para que
la terminal diga lo mismo que diría una compilación completa. Los avisos de
`agrupar` (un valor en blanco, una `url` sin `grupo`) no son de una página sino
de la lectura, y salen siempre.

Las comparaciones de rutas no distinguen mayúsculas ni cuentan las barras
repetidas, porque Windows tampoco lo hace. Lo que una plantilla pida fuera de
`publico/` con `..` no tiene huella, y la página que lo pide se rehace
siempre.

## Lo que queda fuera

Tres cosas no se vigilan. La primera, la hora: una plantilla que escriba la
fecha actual con `"now"|date` no se rehace al cambiar de día. La segunda, lo que
elige `random()`: una página que no se rehace conserva lo que eligió. Las páginas con `publicar` sí
entran solas cuando llega su fecha, porque eso se comprueba en cada
compilación. La tercera, un fichero de `publico/` que cambie sin cambiar ni
de tamaño ni de fecha después de llevar un rato sin tocarse; solo pasa si un
programa restaura a propósito la fecha antigua.

## Cómo se comprueba

Las pruebas hacen sobre un sitio pequeño, preparado para que las páginas
dependan unas de otras de todas las maneras posibles, una lista de cambios de
cada tipo, y comprueban cuántas páginas se rehacen con cada uno. Después hacen
una secuencia de cambios al azar, con una semilla fija. Tras cada cambio
comparan la compilación incremental con una completa, fichero a fichero,
aviso a aviso, y también cuando la compilación falla.

## Para quien incruste el motor

Lo que se recuerda entre compilaciones son datos simples (`Memoria`), sin
recursos ni conexiones, así que se pueden serializar y guardar fuera del
proceso. La previsualización no guarda nada en disco: al arrancar, hace una
compilación completa. Quien incruste el motor sí puede guardarlo:
`CompiladorIncrementalEnProceso` ([`biblioteca.md`](biblioteca.md), formato §10.5)
lo escribe en `.ehundu/memoria.bin` después de cada compilación en la que se
rehace alguna página, y la siguiente, aunque sea de otro proceso, lo aprovecha. En el sitio más grande de
los de prueba (1.204 páginas), cambiar el cuerpo de una página rehace una y
tarda unos 3 s en total (leer el proyecto, cargar la memoria, comparar `salida/`
y guardarla), frente a los 35–45 s de una compilación completa. La memoria pesa
del orden de `salida/` más lo leído (97 MB en ese sitio) y se carga en unos
0,06 s. Son medidas de un sitio y de una máquina, no una promesa del formato.

**La memoria de PHP.** Se guarda y se lee registro a registro (decisión 141), sin
construir ni leer el fichero entero de una vez. En el mismo sitio, el pico de
memoria de PHP (la reservada, la que cuenta el `memory_limit`) es de 170 MB en la
primera compilación, 190 al cambiar el cuerpo de una página y 252 al cambiar una
plantilla, que rehace todas; con la memoria guardada en una sola cadena eran 332,
352 y 416. En otros dos sitios de 27 y 29 MB de memoria guardada, de 770 y 295
páginas, el peor caso es de 80 y 82 MB. Lo que cuenta es el peso del HTML, no el
número de páginas, y la regla que sale es unas 2,5 veces lo que mide
`.ehundu/memoria.bin` más unos 10 MB en el peor caso: lo medido cabe con 128 MB
hasta una memoria de unos 45 MB, con 256 MB hasta unos 95, y con 512 MB hasta unos
200. El motor es más conservador, porque no sabe qué se va a rehacer: antes de
cargarla pide tres veces su tamaño más 16 MB, y descuenta lo que ya ocupa el
proceso, así que acepta memorias de hasta unos 37 MB con 128 MB, 80 con 256 y 170
con 512. Si no cabe en el `memory_limit`, `compilar` compila entero y avisa, y
`avanzar` falla con el límite que hace falta. La primera compilación, sin fichero que medir, no se
comprueba. Una compilación completa sin memoria (`CompiladorEnProceso`) pide
menos: unos 136 MB en ese sitio.

**Cuándo no vale lo guardado.** Si el fichero falta, está cortado o dañado, lo
escribió otra versión de la memoria o del motor (se compara una huella del
contenido de su código, de las versiones de sus dependencias y de la versión
menor de PHP), o es de otro proyecto (otra ruta), se ignora y
se compila entero: nunca es un error. Solo se guarda cuando una compilación
sale bien, y si no se ha rehecho ninguna página no se vuelve a escribir. Las
huellas de `plantillas/`, `parciales/` y `publico/` que tomó el proceso anterior
también se aprovechan: no se vuelve a leer el contenido de los ficheros que ya
llevaban un rato sin tocarse.

**Repartida en llamadas.** `avanzar` corta la compilación cuando se agota el
presupuesto y guarda lo hecho. No hace falta ningún estado aparte: una memoria
con la mitad de las páginas es una memoria a la que le faltan páginas, y el plan
rehace las que faltan y aprovecha las que valen. Por eso, si el proyecto cambia
entre dos llamadas, no se empieza de nuevo (a diferencia de los lotes de más
abajo): solo se rehace lo que el cambio afecta.

Compilar decide primero qué se aprovecha (`Plan`) y después rehace el resto.

## Compilar por lotes (interno)

Un proceso web puede no tener tiempo de compilar un sitio grande de una vez.
`CompiladorPorLotes` (formato §10.4) reparte la compilación **completa** en
tareas (cada página, cada fichero que se copia, el sitemap con el feed, la
limpieza final) y las hace en varias llamadas, cada una con un presupuesto de
tiempo. No es la compilación incremental: no recuerda qué se rehizo la vez
anterior, y cada lote lo rehace todo. Para un sitio que se compila una y otra
vez, `CompiladorIncrementalEnProceso` (más arriba) hace lo mismo con memoria;
los lotes quedan para quien quiere una compilación completa repartida sin
guardar nada del sitio.

**No se promete en la 1.0** (decisión 128): `CompiladorPorLotes` y
`CompiladorPorLotesEnProceso` son `@internal`, y para repartir una compilación en
llamadas se usa `CompiladorIncrementalEnProceso::avanzar`. Lo que sigue cuenta
cómo funcionan hoy, y puede cambiar.

Cada llamada vuelve a leer el proyecto entero (con `agrupar` y `paginar`
expandidos) y construye de nuevo el maquetador; lo único que viaja de una a otra
es `.ehundu/lote.json`, que cuenta por dónde va el lote y no lleva nada del
sitio. Leer cuesta poco frente a hacer las páginas: en el sitio más grande de
los de prueba, 1.200 páginas, leerlo y expandirlo son menos de 1 s y hacerlas,
de 40 a 50. Lo mismo vale para los avisos: se juntan de una llamada a la
siguiente sin repetirse, como en una compilación completa, y el informe final
los da en el mismo orden.

Si algún fichero de `sitio.yml`, `contenido/`, `datos/`, `plantillas/`,
`parciales/` o `publico/` cambia de tamaño o de fecha entre dos llamadas, el lote
empieza de nuevo (la huella no lee el contenido, porque en `publico/` puede haber
gigas). El momento con el que se decide qué está publicado se fija al empezar y
no cambia en todo el lote: una página que se publica por fecha a mitad de un lote
no cambia las tareas que quedan.

Lo que no se puede garantizar es la coherencia de `salida/` mientras dura: cada
página se escribe al hacerla. Por eso un lote dice si está completo y la subida
no debería empezar hasta entonces.
