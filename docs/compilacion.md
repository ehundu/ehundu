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
ficheros de `publico/` mira, incrusta o comprueba, qué colecciones recorre y
de qué otras páginas muestra el contenido. Apunta también el CSS y el JS que
declara y los avisos que da. Lo mismo hace con el cuerpo de cada página, que
se convierte una vez y se reutiliza donde se muestre. Lo que usa un cuerpo lo
usa también la página que lo muestra, así que cada registro está completo
por sí solo.

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
muestran ese cuerpo. Si aparece o desaparece un atajo del sitio, cambian todos
los cuerpos en Markdown, porque cambia cómo se lee el texto. El sitemap y el
feed se generan siempre de nuevo, y la lista de ficheros que se copian también.

Lo que no se rehace se aprovecha tal cual, y sus avisos se repiten, para que
la terminal diga lo mismo que diría una compilación completa.

Las comparaciones de rutas no distinguen mayúsculas ni cuentan las barras
repetidas, porque Windows tampoco lo hace. Lo que una plantilla pida fuera de
`publico/` con `..` no tiene huella, y la página que lo pide se rehace
siempre.

## Lo que queda fuera

Dos cosas no se vigilan. La primera, la hora: una plantilla que escriba la
fecha actual con `"now"|date` no se rehace al cambiar de día, igual que no se
rehacía antes de haber compilación incremental. Las páginas con `publicar` sí
entran solas cuando llega su fecha, porque eso se comprueba en cada
compilación. La segunda, un fichero de `publico/` que cambie sin cambiar ni
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
proceso. De momento no se guarda nada en disco: al arrancar, la
previsualización hace una compilación completa.

Compilar decide primero qué se aprovecha (`Plan`) y después rehace el resto.
Esa separación es la que permitirá compilar por lotes desde un proceso web,
repartiendo lo que hay que rehacer entre varias peticiones, cuando haga falta.
