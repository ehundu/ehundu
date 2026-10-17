# El esquema de un proyecto: `esquema.json`

Este documento es la parte del contrato ([`formato.md`](formato.md), §11) que
describe `esquema.json`, el fichero con el que un editor sabe qué se puede editar
en un sitio. Conserva la numeración (§11 y §11.1 a §11.7) para que las
referencias de `formato.md` y de sus decisiones sigan valiendo. Un `§` sin más es
de `formato.md`.

## Qué es contrato y qué no

El esquema mezcla dos cosas, y este documento las separa:

- **El formato** es lo que declara el esquema: sus claves, los tipos de campo,
  qué valores valen y qué es un error. Es contrato como el resto del formato:
  dentro de una versión mayor solo crece (§12, regla 5), y otro editor puede
  implementarlo.
- **El comportamiento del editor** es cómo usa el primer editor lo que declara el
  esquema: cómo rotula el menú lateral, el calendario de trece meses, los pasos
  con que trata una imagen al subirla, cómo recuerda lo escrito. Está aquí porque
  explica para qué sirve cada clave y para que otros editores se parezcan, pero
  **no vincula**: otro editor puede hacerlo distinto, y el primero puede
  cambiarlo. Va en citas, con «Orientación para el editor».

Dos reglas valen para todo el esquema:

- **Lo que un editor no conoce, lo conserva.** Dentro del formato 1 el esquema
  sigue creciendo: tipos de campo y claves nuevos (decisión 139). Un editor que
  encuentre una clave o un tipo de campo que no conoce no toca ese campo: lo deja
  como está y lo dice. No necesita entender todos los 1.x para editar lo que sí
  entiende.
- **Un error del esquema** es un esquema que no cumple lo que dice este
  documento: una clave con un valor que no vale, una clave en un campo de un tipo
  que no la admite, un `alt` que no nombra un campo del mismo nivel. Lo detecta el
  editor al leerlo; el motor no lee el esquema y compila igual. Qué hace el editor
  con él es suyo, pero lo razonable es no editar lo afectado y decir qué está mal
  y dónde.

Estas palabras se repiten con otro sentido según dónde estén, y ya no se pueden
renombrar (la regla 7 del [§12 del formato](formato.md) dice cómo se escriben los
nombres). Una clave nueva evitará darles un sentido más:

| Palabra    | Dónde                                   | Qué es |
|------------|-----------------------------------------|--------|
| `carpeta`  | en un tipo                              | una ruta, o una lista de rutas, desde la raíz del proyecto; puede acabar en `/*` |
| `carpeta`  | en un campo `imagen` (§11.6)            | una carpeta de `publico/`, sin `/` al principio ni al final |
| `fichero`  | en un tipo                              | el patrón del nombre, sin extensión, de las páginas que crea el editor |
| `fichero`  | en un fichero de datos editable (§11.5) | la ruta de un `.yml` en la raíz de `datos/` |
| `fichero`  | en los atajos (§8)                      | un fichero de `publico/` |
| `grupo`    | tipo de campo                           | un conjunto de campos con nombre |
| `grupo`    | clave de un tipo o de unos datos        | la sección del menú lateral |
| `grupo`    | en `agrupar` (§6.3)                     | el valor por el que se agrupan las páginas |
| `excluir`  | en un tipo                              | páginas que el editor no enseña |
| `excluir`  | en `agrupar` (§6.3)                     | valores que no generan página |
| `orden`    | campo reservado (§4) y tipo `numero`    | un número para ordenar a mano |
| `orden`    | filtro (§6.2) y `paginar.orden` (§6.4)  | un criterio de orden, en texto |

## 11. `esquema.json`

Lo usan los editores; el motor lo ignora. Declara los tipos de contenido del
sitio, dónde están sus páginas, qué campos llevan y quién puede editarlos.

Lleva, si se quiere, `"formato": 1`: la versión de este formato, la misma
clave y las mismas reglas que la de `sitio.yml` (§2). Sin ella, es el 1. Un
editor que no entienda la versión que declara un esquema no lo toca y lo dice.
El motor no la comprueba, porque no lee el esquema; un editor puede comprobarla
con `Formato::leer` ([`biblioteca.md`](biblioteca.md)).

    {
      "formato": 1,
      "tipos": [
        {
          "nombre": "articulo",
          "titulo": "Artículo del blog",
          "plural": "Artículos del blog",
          "carpeta": "contenido/blog",
          "cuerpo": "markdown",
          "campos": [
            { "nombre": "titulo", "titulo": "Título", "tipo": "texto", "requerido": true },
            { "nombre": "subtitulo", "titulo": "Entradilla", "tipo": "texto" },
            { "nombre": "fecha", "titulo": "Fecha", "tipo": "fecha" },
            { "nombre": "imagen", "titulo": "Imagen", "tipo": "imagen" },
            { "nombre": "imagenAlt", "titulo": "Texto alternativo de la imagen", "tipo": "texto", "requerido": true },
            { "nombre": "etiquetas", "tipo": "lista-texto", "rol": "agencia" }
          ]
        },
        {
          "nombre": "libro",
          "titulo": "Libro",
          "plural": "Libros",
          "carpeta": ["contenido/libros/*", "contenido/segunda-mano"],
          "fichero": "{{ autoria|slug }}-{{ titulo|slug }}",
          "cuerpo": { "tipo": "markdown", "titulo": "Reseña" },
          "campos": [
            { "nombre": "titulo", "titulo": "Título del libro", "tipo": "texto", "requerido": true },
            { "nombre": "autoria", "titulo": "Autor o autora", "tipo": "texto" },
            { "nombre": "ficha", "titulo": "Ficha de la editorial", "tipo": "enlace" },
            {
              "nombre": "presentaciones",
              "titulo": "Presentaciones",
              "tipo": "lista",
              "campos": [
                { "nombre": "dia", "titulo": "Día", "tipo": "fecha", "requerido": true },
                { "nombre": "lugar", "titulo": "Lugar", "tipo": "texto", "ayuda": "Solo si no es en la librería" }
              ]
            }
          ]
        }
      ]
    }

**Tipos.** `nombre` identifica el tipo. `titulo` es como lo llama el editor
(«Libro») y `plural`, como llama a la lista («Libros»); sin `plural`, se usa
`titulo`.

`carpeta` dice dónde están las páginas del tipo, y es también donde el editor
crea las nuevas. Es una ruta desde la raíz del proyecto o una lista de ellas.
Una ruta que acaba en `/*` son cada una de las subcarpetas directas de esa
carpeta, y no la carpeta misma: con `contenido/libros/*`, las páginas de
`contenido/libros/novela/` y de `contenido/libros/ensayo/` son libros, y
`contenido/libros.md` o lo que haya en una subcarpeta más honda no lo son. Así
un tipo se puede repartir en categorías, cada una con su `_datos.yml` (§3), sin
declarar sus campos una vez por categoría.

> *Orientación para el editor.* Cuando un tipo tiene más de una carpeta, el editor agrupa sus páginas por
> carpeta y pregunta en cuál crear una nueva. Cada carpeta se rotula con el
> título de la página que se llama como ella a su lado
> (`contenido/libros/novela.md` para `contenido/libros/novela/`) o, si no la
> hay, con el de su `index.md`; si tampoco, con su nombre. Un tipo con una sola
> carpeta y sin ninguna de esas páginas se rotula con el plural del tipo.
> El editor no crea
> carpetas: una categoría nueva lleva su `_datos.yml`, y eso es trabajo de
> quien desarrolla el sitio.

`fichero` es el nombre, sin extensión, de las páginas que crea el editor, con
el vocabulario de los patrones de URL (§5.1): `{{ campo }}` y
`{{ campo|slug }}`. Si falta, es `{{ titulo|slug }}`. Si ya hay en la carpeta
un fichero con ese nombre, se le añade `-2`, `-3`, y así hasta que no choque;
si el patrón no da un nombre (un campo vacío, un `slug` vacío), el editor no
crea la página y lo dice. El nombre se fija al crear la página y no cambia
después, aunque cambien los campos de los que salió: de él sale la URL (§5), y
renombrarlo rompería la dirección publicada. Una traducción toma el nombre de
la página que traduce, con el código de su idioma (§15.2).

`cuerpo` vale `markdown` o `ninguno`. Sin cuerpo, la página es pura ficha de
campos, que es el caso de las páginas de aterrizaje. Un cuerpo en Markdown se
puede escribir también con la forma de un campo, para darle su rótulo y su
ayuda: `{ "tipo": "markdown", "titulo": "Reseña" }`. `"markdown"` a secas es
la forma corta, y el editor le pone un rótulo genérico.

**Campos.** `nombre` es la clave del front matter, la que lee el motor;
`titulo` es el rótulo que enseña el editor («Autor o autora»), y sin él se
enseña el nombre. `ayuda` es una explicación corta junto al campo, si hace
falta. `requerido` dice que el campo no puede quedar vacío.

`rol` vale `cliente` (por defecto), para quien es titular del sitio y edita su
contenido, o `agencia`, para quien lo desarrolla. Los campos de agencia solo
aparecen en el modo avanzado.

Tipos de campo de hoy: `texto`, `parrafo`, `markdown`, `numero`, `fecha`,
`fechas`, `booleano`, `imagen`, `enlace`, `lista-texto`, `seleccion`,
`seleccion-multiple`, `lista` y `grupo`.

- `fechas` es un conjunto de días que el editor enseña como un calendario de
  meses, como los días en que un local está ocupado o los de cierre de un
  negocio (§11.7).
- `seleccion` es una sola elección entre unas opciones fijas, como la talla
  de un producto o la provincia de una tienda (§11.3).
- `enlace` es una dirección: completa, con `http://` o `https://`, o una ruta
  del propio sitio que empieza por `/`. El editor la comprueba, y puede
  completar el `https://` que falte.
- `markdown` es un texto con Markdown (§7.3). Como cuerpo de la página no
  cambia (§8); como campo, la plantilla lo pinta con el filtro `markdown`, de
  línea o de bloque según lo que el campo contenga, y sin él sale como texto.
- `imagen` es una imagen de `publico/`. Su valor es la ruta desde `publico/`
  (`assets/images/bacalao.jpg`), la misma que entienden el campo reservado
  `imagen` (§4) y `dimensiones()` (§7.3). Con solo `nombre` y `tipo`, el editor
  enseña esa ruta escrita; con `carpeta`, deja elegir una imagen del sitio o
  subir otra (§11.6).
- `lista-texto` es una lista de textos sueltos, como `etiquetas`.
- `grupo` es un conjunto de campos con nombre, un mapa en el front matter, y
  `lista` es una lista de grupos, que se pueden añadir, quitar y ordenar. Los
  dos declaran lo que llevan en su propio `campos`, con la misma forma que los
  del tipo. Dentro de un `grupo` solo caben campos simples, que son todos
  menos `lista` y `grupo`. Dentro de una `lista` caben campos simples y otra
  `lista` de campos simples, y nada más hondo (§11.1).

Con el esquema de arriba, las presentaciones de un libro son:

    presentaciones:
      - dia: 2026-10-15
        lugar: Biblioteca municipal
      - dia: 2026-11-02

**Orden manual.** Un tipo cuyo esquema declara un campo `orden` de tipo
`numero` se ordena a mano: es el campo reservado `orden` (§4), el mismo que
usan `orden('orden, titulo')` y las colecciones.

> *Orientación para el editor.* El editor no lo enseña en el
> formulario de la página: lo cambia el propio listado del tipo, arrastrando las
> páginas, y escribe el número de cada una en el mismo sitio en que estaría a
> mano, que en un sitio en varios idiomas es el `.yml` común de la página
> (§15.7). Los números van de 10 en 10, para que al mover una página basten un
> número entre el de sus vecinas y un solo fichero cambiado; las páginas sin
> `orden` van al final, como en las colecciones (§6.1). Un tipo que se ordena
> por fecha, como un blog, no declara `orden` y no se arrastra.

**Página fija.** Un tipo con `"unico": true` es una página que se edita y no
se multiplica, como el menú del día de un restaurante: mientras su carpeta
tenga una, el editor no ofrece añadir otra ni quitar la que hay. Un tipo así
puede no declarar `titulo`: el editor no lo enseña y el que llevan los ficheros
de la página se conserva tal cual.

**Borradores.** Un tipo con `"borradores": true` deja que el editor pase sus
páginas a borrador y las vuelva a publicar: escribe o quita el campo reservado
`borrador` (§4) en cada fichero de la página, en todos los idiomas a la vez
(§15). Una página es un borrador cuando todos sus ficheros lo dicen, y entonces
no genera fichero ni sale en las colecciones (§5, §6). Un tipo así solo deja
quitar los borradores: primero se pasa la página a borrador, que se puede
deshacer, y después se quita. Un tipo sin `borradores` no ofrece nada de esto y
se comporta como siempre. Programar la publicación con `publicar` es otra
clave, `programar` (abajo), y no depende de esta.

**Programar.** Un tipo con `"programar": true` deja que el editor fije cuándo
se publican sus páginas: escribe o quita el campo reservado `publicar` (§4) en
cada fichero de la página, en todos los idiomas a la vez (§15), con una fecha
de hoy en adelante. Una página con `publicar` en el futuro no genera fichero ni
sale en las colecciones, el sitemap ni el feed hasta que llega ese día (§4, §5,
§6, §10). Si los ficheros de una página tienen fechas distintas, el editor
enseña la más próxima y no toca ninguna hasta que se cambie. `borrador` manda
sobre `publicar`: una página que lleva las dos es un borrador. Con `unico` no se
ofrece, porque una página fija no tiene que desaparecer hasta una fecha. Un
tipo sin `programar` no ofrece nada de esto. El sitio es estático: la página
aparece cuando el sitio se vuelve a compilar y a publicar después de esa fecha,
y de eso tiene que encargarse quien aloje el editor, por ejemplo con una tarea
programada. `programar` no depende de `publicarEnSuFecha` (§4), que es cosa de
quien desarrolla el sitio: el editor ni lo escribe ni lo lee, y un `publicar`
que escriba sigue mandando sobre él.

> *Orientación para el editor.* **El menú lateral.** El editor agrupa lo que se edita en secciones. Cada tipo
> lleva la suya, con el plural del tipo por nombre, y los ficheros de datos
> (§11.5) van en «Ajustes». Cuatro claves, todas opcionales, lo cambian:
>
> - `"grupo": "Menús"`, en un tipo o en un fichero de datos, dice en qué
>   sección va. Los que llevan el mismo `grupo` comparten sección, y en ella
>   salen primero las carpetas de los tipos y después los ficheros de datos: así
>   un tipo de menús y otro de servicios van juntos, aunque cada uno tenga su
>   formulario, y un aviso puede ir en una sección «Avisos» con otros.
> - `"rotulo": "Contenidos"`, en un tipo con una sola carpeta (sin `/*`), es
>   cómo se llama esa carpeta en el menú lateral, en lugar del título de la
>   página que la rotula (arriba, §11). Sirve, por ejemplo, para la carpeta
>   `contenido`, cuyo `index.md` es la portada y no un título de sección.
> - `"entrada": "Páginas"`, en un tipo con `unico`, es cómo se llama en el menú
>   lateral la entrada que comparten los tipos fijos de una misma sección que
>   llevan esa misma `entrada`: una sola, con un listado que reúne las páginas de
>   todos, en el orden de los tipos del esquema, y cuyo número es el de todas
>   ellas. Cada fila se llama como su tipo (`titulo`: «Portada», «Contacto») y
>   abre su propio formulario, y el editor no ofrece añadir ni ordenar desde ese
>   listado, porque son páginas fijas. Sirve para un sitio de aterrizaje en el
>   que cada página tiene sus campos, y por eso es un tipo, pero que no puede
>   llevar una entrada del menú por cada una. Sin `unico`, o junto a `rotulo`
>   (que ya da nombre a la carpeta), es un error del esquema.
> - `"excluir": ["404", "aviso-legal"]`, en un tipo, son páginas de sus
>   carpetas que el editor no enseña, no cuenta ni abre, aunque estén en ellas:
>   su nombre, sin carpeta, idioma ni extensión. Sirve para las páginas que solo
>   toca quien desarrolla el sitio.

### 11.1 Una lista dentro de una lista

Una `lista` puede llevar dentro otra `lista`, y no más: dos niveles. Sirve para
lo que se agrupa en dos pasos, como un menú con sus apartados y, en cada uno,
sus platos. Los campos de la lista de dentro son simples, y un `grupo` sigue
sin admitir ni listas ni grupos. En el YAML es lo que se espera: una lista de
mapas que lleva, en uno de sus campos, otra lista de mapas.

    {
      "nombre": "menu",
      "titulo": "Menú",
      "carpeta": "contenido/menus",
      "cuerpo": "ninguno",
      "campos": [
        { "nombre": "titulo", "titulo": "Nombre del menú", "tipo": "texto", "requerido": true },
        { "nombre": "precio", "titulo": "Precio", "tipo": "texto", "traducible": true },
        {
          "nombre": "grupos",
          "titulo": "Grupos de platos",
          "tipo": "lista",
          "campos": [
            { "nombre": "titulo", "titulo": "Título del grupo", "tipo": "texto", "traducible": true },
            { "nombre": "alternativas", "titulo": "Son alternativas", "tipo": "booleano" },
            {
              "nombre": "platos",
              "titulo": "Platos",
              "tipo": "lista",
              "recuerda": { "clave": "nombre", "en": "datos/platos.yml" },
              "campos": [
                { "nombre": "nombre", "titulo": "Plato", "tipo": "texto", "traducible": true, "requerido": true },
                { "nombre": "alergenos", "titulo": "Alérgenos", "tipo": "seleccion-multiple", "opciones": "datos/alergenos.yml", "requerido": true }
              ]
            }
          ]
        }
      ]
    }

Con ese esquema, los campos comunes de un menú (`contenido/menus/del-dia.yml`,
§15.7) son:

    precio:
      es: "20,50 €"
      eu: "20,50 €"
    grupos:
      - titulo: { es: Primer plato, eu: Lehen platera, en: Starter }
        alternativas: false
        platos:
          - nombre: { es: Sopa de pescado, eu: Arrain zopa, en: Fish soup }
            alergenos: [4, 12]
          - nombre: { es: Flan, eu: Flana }
            alergenos: []
      - alternativas: true
        platos:
          - nombre: { es: Pan, eu: Ogia, en: Bread }
            alergenos: [1]

Un grupo sin `titulo` es un grupo sin título, y el editor no obliga a ponerlo.

### 11.2 Campos traducibles

`traducible: true` en un campo `texto` o `parrafo`, también dentro de una
`lista`, dice que su valor cambia según el idioma. El valor es un mapa con el
código de cada idioma del sitio (§15.1): `nombre: { es: Flan, eu: Flana }`. Un
idioma sin escribir falta del mapa, y uno vacío cuenta como si faltara; el
editor no escribe los vacíos. Con `requerido`, hace falta el texto en al menos
un idioma. En un sitio de un solo idioma, `traducible` no hace nada y el campo
es un texto.

El motor no valida estos campos: llegan a la plantilla como cualquier otro
valor, y es la plantilla la que elige el idioma y decide qué hacer si falta.
Con el de la página y, si falta, el del idioma predeterminado (`sitio.idioma`,
§15.1):

    {{ plato.nombre[pagina.idioma]|default(plato.nombre[sitio.idioma]) }}

Un texto suelto, sin mapa (lo que había en el campo antes de hacerlo
traducible), lo lee el editor como el del idioma predeterminado y lo guarda como
mapa la próxima vez. Hasta entonces la plantilla ve un texto y no un mapa, y
esa forma de leerlo no le vale.

**Dónde se guardan los campos.** En un sitio con `idiomas`, una página tiene un
fichero por idioma (§15.2). Lo que es de cada traducción va en su fichero: el
cuerpo y los campos `titulo`, `subtitulo`, `descripcion`, `imagenAlt`, `url`,
`borrador` y `publicar`. Todo lo demás, traducible o no, va en el `.yml` común
de la página (§15.7), así que el texto de todos los idiomas queda junto y dos
traducciones no pueden contradecirse en lo que no cambia, como los alérgenos de
un plato. El editor lee cada campo donde esté, con la precedencia del motor, y
lo escribe donde ya estaba; los campos que aún no existen los pone en el `.yml`
común. Al crear una página, pregunta en qué idiomas y crea un fichero por cada
uno, con su `titulo` y su `url`, más el `.yml` común. En un sitio de un solo
idioma todo va en el front matter, como siempre.

Los campos reservados de texto (`titulo`, `subtitulo`, `descripcion`,
`imagenAlt`) no pueden ser traducibles: ya llevan su traducción en el fichero
de cada idioma.

### 11.3 Selección y selección múltiple

`seleccion` es un campo cuyo valor es una sola elección entre unas opciones
fijas: la talla de un producto, la provincia de una tienda. Lleva
sus `opciones` de las mismas dos formas y con el mismo contenido que las de
`seleccion-multiple` (abajo), y lo que se guarda es el `id` de la elegida, tal
como está en la opción (`provincia: Bizkaia`, o un número si el `id` lo es). Es
la elección entre una lista cerrada que un texto libre no da: una errata en el
texto de un filtro o de una agrupación saca la página de ellos sin avisar.
`requerido` hace que el editor no guarde hasta que se elija una opción; sin él,
se puede dejar sin elegir y el campo falta. Un `id` guardado que ya no está
entre las opciones se conserva tal cual mientras no se cambie, y el editor lo
avisa, igual que en la múltiple. `traducible` no vale en una `seleccion`: el
`id` es el mismo en todos los idiomas, y el rótulo de cada opción lleva sus
traducciones.

`seleccion-multiple` es un campo cuyo valor es una lista de elecciones entre
unas opciones fijas, como los alérgenos de un plato. Las opciones van en
`opciones`, de una de estas dos formas: la ruta de un `.yml` en la raíz de
`datos/` (`"opciones": "datos/alergenos.yml"`), o la lista escrita en el propio
esquema. El fichero es un fichero de datos como los demás, así que las
plantillas lo ven como `datos.alergenos`, para escribir la leyenda o el nombre
de cada opción.

Cada opción tiene un `id`, texto o número, que no se repite y es lo que se
guarda; un `titulo`, que es el rótulo, con la forma de un campo traducible si
el sitio tiene varios idiomas; y, si hace falta, un `icono`, un SVG relativo a
`publico/`, como el campo reservado del mismo nombre (§4):

    - id: 1
      titulo: { es: Gluten, eu: Glutena, en: Gluten }
      icono: img/alergenos/gluten.svg
    - id: 4
      titulo: { es: Pescado, eu: Arraina, en: Fish }

Las opciones son las mismas para todos los idiomas: cada título lleva sus
traducciones dentro. El valor, en el front matter, es la lista de los `id`
elegidos, en el orden de las opciones, para que la misma elección se escriba
siempre igual: `alergenos: [4, 12]`. Un `id` que ya no está entre las opciones
se conserva, y el editor lo avisa.

**Una lista vacía es una respuesta, y un campo que falta, no.** `alergenos: []`
dice que se ha mirado y no hay ninguno; sin el campo, no se ha mirado. Con
`requerido`, el editor no guarda hasta que haya respuesta, y una lista vacía lo
es: quien edita marca «Ninguno». Así un dato que importa, como los alérgenos,
no se queda sin revisar por descuido. Las plantillas ven en los dos casos una
lista sin elementos.

### 11.4 Recordar lo escrito

`recuerda`, en una `lista`, hace que el editor recuerde lo que se escribe en
ella para no escribirlo dos veces, como los platos de un menú, que se repiten
de un día a otro:

    "recuerda": { "clave": "nombre", "en": "datos/platos.yml" }

`clave` es un campo simple de la lista, el que se escribe para identificar un
elemento, y `en`, un `.yml` en la raíz de `datos/`. El fichero es una lista de
los elementos recordados, cada uno con sus campos simples; los de una lista de
dentro no se recuerdan, aunque la haya.

> *Orientación para el editor.* El editor lo usa así:
>
> - Al escribir en `clave`, sugiere los elementos recordados que coinciden, sin
>   distinguir mayúsculas ni tildes y, si la clave es traducible, en el idioma en
>   que escribe quien edita.
> - Al elegir uno, copia sus campos al elemento y lo dice, para que se revisen.
> - Al guardar, añade los elementos nuevos y, si han cambiado los de uno
>   recordado, pregunta si recordar el cambio.

Lo recordado se copia, no se enlaza: cada página lleva sus propios datos
completos, y cambiar el fichero no cambia lo que ya está escrito. El motor no
lee `recuerda`, pero el fichero es un fichero de `datos/` como los demás, así
que las plantillas pueden leerlo (`datos.platos`), y cambiarlo cuenta como
cambiar `datos/` para la compilación incremental (`compilacion.md`), aunque
ninguna plantilla lo lea.

### 11.5 Datos editables

Además de las páginas de sus tipos, un esquema puede declarar ficheros de
`datos/` que también se editan, como el horario o un aviso:

    {
      "tipos": [ … ],
      "datos": [
        {
          "nombre": "aviso",
          "titulo": "Aviso de interés",
          "fichero": "datos/aviso.yml",
          "campos": [
            { "nombre": "activo", "titulo": "Mostrar el aviso", "tipo": "booleano" },
            { "nombre": "texto", "titulo": "Texto", "tipo": "parrafo", "traducible": true }
          ]
        }
      ]
    }

`nombre` identifica el fichero, `titulo` es como lo llama el editor y
`fichero`, aquí una ruta y no un patrón, dice cuál es: un `.yml` en la raíz de
`datos/`. `campos` es como el de un tipo, con los mismos tipos de campo,
`traducible`, `recuerda` y lo demás. No hay `carpeta` ni `cuerpo`: es un solo
fichero, que el editor no crea ni quita, y que escribe la primera vez que se
guarda si aún no existe. Conserva las claves que el esquema no declara, como
con una página, y no toca un fichero sin cambios. También sirve para el
`cliente.yml` (§3), en los campos que el titular puede cambiar.

Un fichero de datos editable es uno solo, sin variantes por idioma
(`aviso.eu.yml`, §15.6): lo que cambia de idioma va dentro, con `traducible`. Si
junto al declarado hay variantes por idioma, el editor lo avisa y no las toca.
Para quien edita, un fichero de datos es un formulario más de la lista de lo
que puede cambiar: no le importa si sus campos viven en una página o en
`datos/`.

**Publicar al guardar.** Un fichero de datos con `"publicarAlGuardar": true`
hace que el editor, al guardarlo, publique el sitio en el acto, en lugar de
dejar que quien edita lo haga después en un segundo paso. Es para lo que no
puede esperar a ese paso: un cambio guardado y sin publicar es una web que
dice lo contrario de lo que quiere su titular, como con los días en que un
local está ocupado o un aviso de cierre. Publicar es compilar y desplegar el
sitio (`despliegue.md`) tal como está guardado, no solo ese fichero: lo que
hubiera guardado y sin publicar sale con él. Si el editor no puede publicar, lo
dice: el fichero queda guardado, sin publicar, y no da el cambio por
publicado. Sin la clave, guardar y publicar siguen siendo dos pasos. Como el
resto del esquema, la clave es del editor: el motor no la lee y el sitio se
compila igual con ella que sin ella. Solo vale en un fichero de datos y su
valor es `true` o `false`; cualquier otro es un error del esquema.

### 11.6 Elegir y subir imágenes

Un campo `imagen` puede declarar tres claves, todas opcionales, para que el
editor deje elegir y subir imágenes en lugar de pedir una ruta escrita:

    {
      "nombre": "imagen",
      "titulo": "Foto",
      "tipo": "imagen",
      "carpeta": "assets/images",
      "ancho": 1600,
      "alt": "imagenAlt"
    }

- `carpeta` es una carpeta de `publico/`, sin `/` al principio ni al final y
  sin `..`. Es donde el editor guarda las imágenes que se suben desde ese
  campo y de donde ofrece las que ya hay: las de esa carpeta y las de sus
  subcarpetas. Sin `carpeta`, el campo no cambia: el editor enseña la ruta
  escrita y no sube ni ofrece nada. Un valor que ya está escrito y no cae en
  `carpeta` se conserva tal cual hasta que se cambie.
- `ancho` es el ancho máximo, en píxeles, de lo que se sube: un entero
  positivo, 2000 si falta. Una imagen más ancha se reduce a ese ancho, con el
  alto en proporción; una que no lo es no se amplía. Solo vale con `carpeta`.
- `alt` es el nombre de otro campo del mismo nivel (el mismo tipo, la misma
  lista o el mismo grupo), de tipo `texto` o `parrafo`, que lleva la
  descripción de la imagen para quien no puede verla: el `imagenAlt` de §4, o
  el `alt` de una lista de fotos. El editor pide esa descripción al elegir la
  imagen y la escribe en ese campo, y es obligatoria si el campo es
  `requerido`. Sin `alt`, no pide descripción. Solo vale con `carpeta`.

Una clave que no cumple lo anterior, o un `alt` que no nombra un campo del
mismo nivel, es un error del esquema.

> *Orientación para el editor.* Al subir una imagen, el editor:
>
> - acepta JPEG, PNG y WebP, y rechaza lo demás diciéndolo; también puede
>   rechazar una imagen que no pueda tratar con los recursos que tiene, y debe
>   decirlo;
> - la gira según la orientación que guarda la cámara y le quita esos datos y la
>   ubicación, de modo que se vea igual y no publique dónde se hizo;
> - la reduce a `ancho`, si hace falta;
> - le da un nombre sin tildes ni espacios y la deja en `carpeta`, sin pisar una
>   que ya esté: si el nombre existe, añade `-2`, `-3`, y así hasta que no choque,
>   como con `fichero` (§11).

> *Orientación para el editor.* El formato y la calidad con que la guarda son cosa del editor, y el fichero
> queda en `publico/` como cualquier otro cambio. Las dimensiones no se guardan
> en el front matter: la plantilla las lee con `dimensiones()` (§7.3), que da
> las de la imagen que esté entonces. El motor no lee estas claves, como el resto
> del esquema, y no procesa imágenes (§9): lo que ve es un fichero de `publico/`.

### 11.7 Fechas

`fechas` es un campo cuyo valor es un conjunto de días: una lista sin orden
propio y sin repetidos, que se marca en un calendario. Sirve para los días en
que un local está ocupado, los de cierre de un negocio o los festivos de los
que depende un precio. En el fichero es una lista YAML de fechas
(`AAAA-MM-DD`), de menor a mayor y una por línea, para que un cambio sea una
línea y quien edita a mano las encuentre ordenadas:

    dias:
      - 2026-10-17
      - 2026-10-24

El motor las lee como cualquier otra fecha del front matter o de `datos/`: días
en la zona horaria del sitio (decisión 30), que la plantilla escribe con el
filtro `fecha` y compara como cualquier fecha. Con `dias` vacío la plantilla
ve una lista sin elementos, y es una respuesta como otra cualquiera: `dias: []`
dice que no hay ningún día marcado, que es el estado normal de un calendario
sin reservas.

> *Orientación para el editor.* El editor lo enseña como un calendario de meses, el actual y los doce
> siguientes (trece en total, para poder marcar el mismo día de dentro de un
> año), en el que se marca y se desmarca cada día desde hoy, en la zona horaria
> del sitio. Los días anteriores a hoy se ven pero no se cambian. Lo que no se
> puede cambiar o no se enseña, un día ya pasado o de más allá de esos trece
> meses, se conserva tal cual al guardar, y un valor que no es un día se conserva
> y se avisa, igual que un `id` que ya no está entre las opciones de una
> `seleccion`. Lo que escribe es siempre la lista entera, ordenada y sin
> repetidos. Dos cambios sobre el mismo calendario no chocan: se juntan día a día,
> con lo que añade y lo que quita cada uno.

Un campo `fechas` no admite `requerido` (no marcar ningún día es una respuesta
tan buena como marcar uno) ni `traducible` (los días son los mismos en todos
los idiomas), y hoy solo vale como campo de primer nivel de un tipo o de
un fichero de datos, no dentro de una `lista` ni de un `grupo`. Lo que no
cumple esto es un error del esquema. El motor no lee el esquema y no cambia: lo
único que ve es una lista de fechas.
