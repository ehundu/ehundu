# El formato de un proyecto, v1 (borrador)

Este documento es el contrato del formato de un proyecto de Ehundu: cómo se
organiza, qué campos admite y qué significa cada cosa. Ehundu lo implementa,
una herramienta de edición puede escribirlo y cualquiera puede escribir y
compilar un proyecto con un editor de texto y la línea de comandos.

Estado: borrador. Las decisiones cerradas están en §14.

---

## 1. Estructura del proyecto

    mi-sitio/
      sitio.yml              configuración del sitio
      contenido/             páginas y artículos
        _datos.yml           datos heredados por esta carpeta
        index.md
        blog/
          _datos.yml
          un-articulo.md
      datos/                 datos globales
        cliente.yml
        menus.yml
      plantillas/            layouts
        base.twig
        pagina.twig
      parciales/             fragmentos reutilizables
        cabecera.twig
        listado-articulos.twig
      publico/               se copia tal cual a la raíz del sitio
        css/
        img/
        favicon.ico
      esquema.json           tipos de contenido y campos (lo usan los editores)
      .secretos.yml          credenciales de despliegue (nunca se versiona)
      salida/                resultado del build (nunca se versiona)

Reglas:

- Todo lo que afecta al resultado vive dentro de esta carpeta. Sin excepciones.
- El motor solo lee de aquí y solo escribe en `salida/`.
- Los nombres de carpeta son fijos; no se configuran. Un proyecto siempre se
  reconoce igual, y se puede pasar de uno a otro, o migrar muchos, sin
  averiguar cómo está organizado cada uno.

**Cerrado:** nombres de carpeta en español. El proyecto se publicará con
documentación en español y en inglés, pero el formato es en español.

---

## 2. `sitio.yml`

    nombre: Librería La Esquina
    url: https://www.ejemplo.com
    idioma: es
    zonaHoraria: Europe/Madrid

    despliegue:
      destino: sftp
      servidor: sftp.ejemplo.com
      usuario: esquina
      ruta: /home/esquina/www
      huella: SHA256:OgR5PWmWkjC9MJd/4ChYsOklVAXnzl8AMwJFqb0rAgY
      # la contraseña va en .secretos.yml, nunca aquí

`sitio.yml` es obligatorio y tiene que llevar al menos `nombre` y `url`. `url`
es la dirección absoluta del sitio, con `http://` o `https://`. Si falta el
fichero o alguno de esos dos campos, el build se detiene con un error: sin
ellos no se pueden generar ni el sitemap ni el feed.

`zonaHoraria` es la zona en la que se leen y se escriben todas las fechas del
sitio, con su nombre estándar (`Europe/Madrid`, `America/Mexico_City`). Si
falta, es UTC. Una zona que no existe detiene el build.

La sección `feed` pide un `feed.xml` y dice de qué colección sale (§10.2).

La sección `despliegue` dice adónde publica `ehundu desplegar` (ver
`despliegue.md`). Destinos admitidos en la v1: `carpeta`, `ftp`, `sftp` y
`s3`. Sus campos:

| Campo      | Destinos        | Significado                                                  |
|------------|-----------------|--------------------------------------------------------------|
| `destino`  | todos           | `carpeta`, `ftp`, `sftp` o `s3`                              |
| `ruta`     | todos           | la carpeta de destino; en `carpeta`, una ruta local fuera del proyecto, relativa a él si no es absoluta; en `s3`, la carpeta dentro del cubo |
| `servidor` | ftp, sftp, s3   | el servidor; en `s3`, la dirección del servicio (`s3.fr-par.scw.cloud`) |
| `puerto`   | ftp, sftp       | si no es el de siempre (21 y 22)                             |
| `usuario`  | ftp, sftp, s3   | el usuario; en `s3`, el identificador de la clave de acceso  |
| `huella`   | sftp            | la huella de la clave del servidor, como la da `ssh-keygen -l` |
| `cifrado`  | ftp             | `no` para FTP sin cifrar; por defecto, FTPS                  |
| `region`   | s3              | la región del cubo (`fr-par`)                                |
| `cubo`     | s3              | el nombre del cubo                                           |

Los secretos van en `.secretos.yml`, con la misma forma y solo con ellos:

    despliegue:
      clave: 'la-contraseña'                   # en s3, la clave secreta
      clavePrivada: '~/.ssh/id_ed25519'        # sftp, en lugar de clave
      frase: 'la-de-la-clave-privada'          # si la tiene

Los secretos van entre comillas simples, y una comilla simple dentro se
escribe doble (`'l''arte'`). Sin comillas, YAML lee algunas contraseñas de
otra forma sin avisar: lo que va detrás de ` #` es un comentario y se pierde,
`1e3` es el número 1000, y una que empieza por `!`, `*`, `@` o `%` ni
siquiera se lee. Si `.secretos.yml` no es YAML válido, el error dice el
fichero, la línea y qué pasa, pero nunca enseña la línea.

Un secreto en `sitio.yml` detiene el despliegue: ese fichero se comparte y se
versiona. `.secretos.yml` no se versiona nunca ni se publica. Un programa que
incruste el motor puede darle los secretos directamente, sin fichero; los
suyos ganan. Un campo que no corresponde al destino, o que no existe, avisa y
se ignora.

---

## 3. Datos

**Globales.** Todo fichero `.yml` o `.json` de `datos/` se expone en las
plantillas por su nombre: `datos/cliente.yml` está disponible como
`datos.cliente`. Se reserva el nombre `cliente.yml` para los datos de quien es
titular del sitio (nombre, dominio, logos, teléfonos, dirección, redes, datos
legales), para que las plantillas los encuentren siempre en el mismo sitio.

Si un `.yml` y un `.json` dan el mismo nombre, gana el `.yml` y se avisa. Las
subcarpetas de `datos/` y los ficheros con otra extensión no se leen; también
se avisa. Lo que empieza por punto (`.gitkeep`) se ignora sin aviso.

**Por carpeta.** Un `_datos.yml` dentro de cualquier carpeta de `contenido/`
aplica a todo lo que cuelga de ella, incluidas las subcarpetas. Los valores más
cercanos al fichero ganan, y el front matter de la página gana sobre todos.

Ejemplo, `contenido/blog/_datos.yml`:

    plantilla: articulo
    etiquetas: [blog]
    url: "/blog/{{ titulo|slug }}/"

Con eso, un artículo nuevo solo necesita título y cuerpo.

Las excepciones a "gana el más cercano" son `etiquetas`, `css` y `js`: los de
cada nivel se suman a los heredados, sin repetir y de lo más general a lo más
concreto. Un artículo que declara `etiquetas: [novela, recomendaciones]` sigue
estando en la colección `blog` que le pone su carpeta, y el CSS que añade una
sección se suma al de todo el sitio. Los demás campos, listas y mapas
incluidos, se sustituyen enteros; no se mezclan por dentro.

---

## 4. Ficheros de contenido

Extensiones reconocidas: `.md` (Markdown) y `.twig` (plantilla). Cualquier otra
cosa dentro de `contenido/` se copia tal cual a `salida/`, con su misma ruta
(`contenido/blog/foto.jpg` → `blog/foto.jpg`), salvo los ficheros que empiezan
por guion bajo, como `_datos.yml`. Lo que empieza por punto (`.gitkeep`,
`.git/`) se ignora. Las plantillas y los datos de otros generadores (`.njk`,
`.liquid`, `.vto`, `.webc`, `.11ty.js`, `.11tydata.js`, `.11tydata.json`) no
se copian nunca, y se avisa: publicar el código de una plantilla no es lo que
se quiere, y en una migración es fácil que alguno se quede atrás.

El front matter va entre `---`, en YAML. Si no es YAML válido, el build se
detiene con un error que indica fichero y línea: seguir sin esa página haría
que el despliegue la borrara del sitio publicado.

Campos reservados:

| Campo         | Tipo    | Significado                                              |
|---------------|---------|----------------------------------------------------------|
| `titulo`      | texto   | Título de la página. Obligatorio si genera HTML.          |
| `subtitulo`   | texto   | Entradilla o descripción corta.                           |
| `descripcion` | texto   | Descripción para buscadores (`meta description`).         |
| `fecha`       | fecha   | Fecha de publicación (`AAAA-MM-DD`).                      |
| `url`         | texto   | URL final o patrón. Si falta, se deriva de la ruta.       |
| `plantilla`   | texto   | Nombre del layout, sin extensión. `false`: sin layout.    |
| `etiquetas`   | lista   | Colecciones a las que pertenece.                          |
| `listada`     | sí/no   | Si es `no`, se publica pero no entra en colecciones ni en el sitemap. |
| `css`         | lista   | Ficheros CSS de `publico/` que necesita la página (§9).   |
| `js`          | lista   | Ficheros JavaScript de `publico/` que necesita (§9).      |
| `imagen`      | texto   | Imagen principal, relativa a `publico/`.                  |
| `imagenAlt`   | texto   | Texto alternativo de esa imagen.                          |
| `icono`       | texto   | SVG, relativo a `publico/`.                               |
| `orden`       | número  | Orden manual dentro de una colección.                     |
| `borrador`    | sí/no   | Si es `sí`, no se publica.                                |
| `publicar`    | fecha   | No se publica antes de esa fecha.                         |

Cualquier otro campo se pasa a la plantilla sin tocarlo. El motor no valida
campos desconocidos: eso es tarea del esquema (§11) y del editor. Un campo con
el nombre mal escrito es, para el motor, un campo desconocido más.

Lo que sí comprueba es el valor de los campos reservados. Si no vale (una fecha
imposible, un `orden` que no es un número), avisa con fichero y línea y trata
el campo como si no estuviera. Nunca detiene el build por eso. Un campo en
blanco (`imagen:`) no es un error: pasa vacío y sin aviso. La excepción es
`titulo`: una página que genera HTML sin título, o con el título vacío,
avisa, porque su `<title>` saldría vacío. Un fragmento (`url: false`) o una
página que genera otra cosa (`/robots.txt`) no lo necesitan.

Un fichero `.md` o `.twig` cuyo nombre, o el de su carpeta, empieza por guion
bajo (`_portada/index.md`) es una página como cualquier otra, igual que en
Eleventy. El guion bajo solo impide que se copien los ficheros que no son
páginas.

Detalles de los tipos:

- **sí/no** admite `sí`, `si`, `no`, `true` y `false`. YAML solo reconoce los
  dos últimos, así que el motor traduce los otros tres.
- **fecha** se escribe `AAAA-MM-DD`, con o sin comillas. Es ese día en la
  zona horaria del sitio (§2), desde su medianoche: `publicar: 2026-10-01`
  publica a las 00:00 del 1 de octubre en esa zona.
- **texto** admite un número, que se toma como texto (`titulo: 2024`).
- **número** admite un número entre comillas (`orden: "2"`).
- **`etiquetas`**, **`css`** y **`js`** admiten un texto suelto, que cuenta
  como una lista de un elemento: `etiquetas: servicio` es lo mismo que
  `etiquetas: [servicio]`.
- **`plantilla`** vacía (`plantilla: ""`) es lo mismo que `plantilla: false`.

Se aceptan como alias los nombres ingleses habituales en Eleventy y Lume
(`title`, `subtitle`, `description`, `date`, `tags`, `layout`, `permalink`),
para no tener que reescribir el front matter al migrar. Si una página lleva a
la vez el nombre inglés y el español, gana el español y se avisa. Los alias
valen también en `_datos.yml`.

El alias es para el nombre del campo, no para su valor. `layout: post` vale,
pero `layout: post.njk` no: `plantilla` es el nombre de una plantilla de
Ehundu, sin extensión, y la plantilla que no existe detiene el build (§7.1).
El error dice cómo quedaría el nombre sin extensión. Al migrar, los nombres
de las plantillas se eligen de nuevo al pasarlas a Twig.

---

## 5. URL

Por defecto, la URL sale de la ruta dentro de `contenido/`, quitando la
extensión y añadiendo barra final. Un `index` da la URL de su carpeta, en
cualquier nivel:

    contenido/servicios/encuadernacion.md  →  /servicios/encuadernacion/
    contenido/blog/index.md                 →  /blog/
    contenido/index.md                      →  /

La ruta se usa tal cual. Si da una mala URL (espacios, mayúsculas, tildes o
`ñ`), el motor avisa: conviene renombrar el fichero o darle un `url`.

El campo `url` la sustituye. Tiene que empezar por `/`; si no, se avisa y se
le añade. Lo que se escribe en `salida/` depende de cómo acaba:

- en `/`: un `index.html` dentro de esa carpeta (`/contacto/` →
  `contacto/index.html`);
- en un nombre con extensión: ese fichero tal cual (`/404.html`,
  `/robots.txt`);
- en otra cosa (`/contacto`): se avisa y se toma como `/contacto/`.

Una URL nunca escribe fuera de `salida/`. Si lleva tramos `.` o `..`, o
caracteres que no caben en un nombre de fichero (`\ ? # : * " < > |`), se avisa
y se usa la URL que sale de la ruta. Un `url` en blanco también deja la de la
ruta.

### 5.1 Patrones

`url` admite un patrón con valores del propio front matter:

    url: "/blog/{{ titulo|slug }}/"

El vocabulario es cerrado: `{{ campo }}` pone el valor de un campo y
`{{ campo|slug }}` lo pasa antes por el filtro `slug`. No hay más filtros, ni
expresiones, ni condiciones. Dentro del patrón se aceptan también los alias
ingleses (`title`, `slugify`).

Si el patrón usa un campo que la página no tiene, o que no es un texto o un
número, si un `slug` sale vacío, o si entre las llaves hay otra cosa, se avisa
y se usa la URL que sale de la ruta.

### 5.2 El filtro `slug`

Convierte un texto en un tramo de URL:

- pasa a minúsculas y quita tildes y diéresis (`ü` → `u`, `ñ` → `n`,
  `ç` → `c`; también `ß` → `ss`, `æ` → `ae`, `œ` → `oe`);
- `&` se convierte en `y`, y el punto volado del catalán desaparece
  (`col·legi` → `collegi`);
- cualquier otro carácter que no sea una letra latina o un número se
  convierte en guion, sin guiones repetidos ni en los extremos. Las letras de
  otros alfabetos desaparecen.

`Cómo elegir un buen libro: guía` da `como-elegir-un-buen-libro-guia`.

Para los títulos habituales en español da lo mismo que el filtro `slugify` de
Eleventy. Difiere en la diéresis (Eleventy da `ue`: `bilingüe` →
`bilinguee`), en `&` (Eleventy da `and`) y en símbolos como `€` (Eleventy da
`e`; aquí desaparece). Al migrar, las páginas afectadas conservan su dirección
con un `url` explícito.

### 5.3 Fragmentos y colisiones

`url: false` produce un fragmento: se lee, se puede consultar desde otras
páginas y se puede editar desde un editor, pero no genera fichero.

Si dos páginas que se publican van al mismo fichero de salida, el build se
detiene con un error que nombra las dos. Las mayúsculas no cuentan, porque en
Windows y en macOS `/Blog/` y `/blog/` son la misma carpeta. Los fragmentos,
los borradores y las páginas con `publicar` en el futuro no generan fichero,
así que no chocan con nada.

---

## 6. Colecciones

Una colección es el conjunto de páginas que comparten una etiqueta. Se declaran
con `etiquetas`, normalmente desde el `_datos.yml` de la carpeta. Los
fragmentos entran en las colecciones de sus etiquetas.

Toda página con URL está además en la colección implícita `todo`. Por eso
`todo` no se puede usar como etiqueta: si aparece, se avisa y se quita.

No entran en ninguna colección, tampoco en `todo`, los borradores, las páginas
con `publicar` en el futuro y las de `listada: no`. Este último es el caso de
los textos legales, la página de error o `robots.txt`: se publican, pero no
salen en listados ni en el sitemap.

Desde una plantilla:

    {% for articulo in coleccion('blog')|orden('fecha desc')|limite(6) %}

### 6.1 Orden de una colección

`coleccion()` devuelve las páginas por fecha, de la más antigua a la más
reciente, y a igual fecha por ruta. Las que no tienen fecha van al final,
también por ruta.

Es el mismo orden que usa Eleventy, salvo en las páginas sin fecha: Eleventy
les da la fecha de creación del fichero, que cambia de una máquina a otra, y
un proyecto tiene que dar el mismo resultado en cualquiera.

### 6.2 Filtros

- `orden(criterio)` ordena por uno o varios campos separados por comas, cada
  uno seguido si acaso de `asc` o `desc` (`asc` si no se indica):
  `orden('orden, titulo')`. Sin criterio es `orden('fecha desc')`. Las
  páginas a las que les falta el campo van al final en las dos direcciones.
  Los números se comparan como números, las fechas como fechas y los textos
  sin distinguir mayúsculas ni tildes, con la `ñ` después de la `n`, como en
  el diccionario. A igualdad, las páginas quedan en el orden que tenían.
- `limite(n)` deja las `n` primeras.
- `invertir` da la vuelta a la lista.
- `sin(pagina)` quita una página, normalmente la actual.
- `donde(campo, valor)` deja las páginas en las que el campo vale eso. Si el
  campo es una lista, las que lo contienen: `donde('etiquetas', 'novela')`.
  Una fecha se compara con su día (`donde('fecha', '2025-03-15')`). Los
  textos se comparan exactos: con mayúsculas, tildes y espacios.
- `anterior(pagina)` y `siguiente(pagina)` no filtran: devuelven la página que
  va antes o después de la indicada en la lista, o nada si es la primera, la
  última o no está. Con el orden de `coleccion()`, la anterior es la más
  antigua.

Los filtros aceptan también los alias ingleses de los campos (`date`,
`title`).

`orden` y `donde` ven cada página como la ven las plantillas (§7.2): los
campos del front matter, con la cascada, y además `url`, la URL ya resuelta
(vacía en un fragmento), y `ruta`, la del fichero dentro de `contenido/`.
`donde('url', '/contacto/')` encuentra la página aunque su URL salga de la
ruta y no esté escrita en el front matter. `contenido` no cuenta: para
filtrar u ordenar por él habría que convertir el cuerpo de todas las páginas
de la lista.

Como `orden` respeta el orden previo en los empates, `orden('fecha desc')` no
es lo mismo que `invertir` cuando hay páginas con la misma fecha: la primera
las deja por ruta y la segunda, al revés. Lo que en Eleventy es
`collections.blog | reverse | head(6)` es aquí
`coleccion('blog')|invertir|limite(6)`.

---

## 7. Plantillas

Motor: **Twig**. Los layouts viven en `plantillas/` y los fragmentos
reutilizables en `parciales/`.

### 7.1 Plantillas y parciales

Dentro de Twig, las plantillas y los parciales se nombran con su ruta desde la
raíz del proyecto, y Twig no puede leer nada fuera de esas dos carpetas:

    {% extends 'plantillas/base.twig' %}
    {% include 'parciales/cabecera.twig' %}

El layout de una página se elige con `plantilla`, por su nombre sin carpeta ni
extensión: `plantilla: articulo` es `plantillas/articulo.twig`. Si no se
indica, se usa `pagina`. Con `plantilla: false` la página sale tal cual, sin
layout, que es lo que necesita un `robots.txt`. Si la plantilla que usa una
página no existe, el build se detiene: publicar el contenido sin su layout
sería peor que no publicarlo.

Las plantillas no llevan front matter. Se anidan con `{% extends %}` y
bloques, como en cualquier proyecto de Twig. El cuerpo de la página está
siempre en `{{ pagina.contenido }}`:

    {# plantillas/articulo.twig #}
    {% extends 'plantillas/base.twig' %}
    {% block cuerpo %}
      <article>{{ pagina.contenido }}</article>
    {% endblock %}

Las páginas `.twig` de `contenido/` son plantillas de Twig con las mismas
variables; su resultado es `pagina.contenido` y va dentro de su layout como el
de cualquier otra página.

### 7.2 Variables

- `sitio`: lo que hay en `sitio.yml` (sin la sección de despliegue).
- `datos`: los ficheros de `datos/`.
- `pagina`: el front matter de la página actual, más `pagina.url`,
  `pagina.contenido` (el cuerpo ya convertido a HTML) y `pagina.ruta`.

Las páginas que dan `coleccion()` y sus filtros tienen la misma forma:
`articulo.titulo`, `articulo.url`. También `articulo.contenido`, que se
convierte la primera vez que alguien lo pide. Si una página acaba necesitando
su propio contenido para construirse (una página `.twig` que lista una
colección en la que está ella misma y pide el contenido de cada una), el build
se detiene con un error que muestra la cadena de páginas.

Un campo que falta sale vacío, como en Nunjucks: `{% if pagina.imagen %}`
funciona sin comprobar antes si existe. Twig escapa el HTML de todo lo que
escribe, salvo `pagina.contenido` y lo que devuelve `svg()`.

### 7.3 Funciones y filtros

- `coleccion(nombre)` y los filtros de colección del §6.2.
- `svg(ruta)` incrusta un SVG de `publico/`, tal cual; solo le quita la
  declaración `<?xml ?>` y el `DOCTYPE`, que no caben dentro de HTML:
  `{{ svg('svg/telefono.svg') }}`. Si el fichero no existe, no es un `.svg` o
  la ruta sale de `publico/`, se avisa y no se inserta nada.
- `dimensiones(ruta)` da el ancho y el alto de una imagen de `publico/`, en
  píxeles, para `width`, `height` o `aspect-ratio`:
  `{% set d = dimensiones('img/sede.jpg') %}` y luego `{{ d.ancho }}` y
  `{{ d.alto }}`. Lee la cabecera del fichero con lo que trae PHP (JPEG, PNG,
  GIF, WebP, AVIF); no procesa la imagen. De un SVG o de un fichero que no es
  imagen da `null`, sin aviso. Si el fichero no existe o la ruta sale de
  `publico/`, avisa y da `null`.
- `activo(url)` dice si la página actual es esa URL o está dentro de ella:
  `activo('/blog/')` es cierto en `/blog/` y en `/blog/un-articulo/`. `/` solo
  es activo en la portada. Para comparar exacto, `pagina.url == url`.
- `slug` (§5.2).
- `fecha(formato)` escribe una fecha con las mismas letras que el filtro
  `date` de Twig, pero con los nombres de meses y días en español:
  `pagina.fecha|fecha('d F Y')` da `18 marzo 2025`. Sin formato, da
  `18 de marzo de 2025`. Una letra que tiene que salir tal cual se escapa
  con dos barras dentro de la plantilla, igual que con `date`:
  `fecha('j \\d\\e F')` da `18 de marzo`.

Todas las fechas se escriben en la zona horaria del sitio (§2), también las
del filtro `date` de Twig, que por sí solo usaría la de la máquina.

Las plantillas pueden usar `random()` de Twig para elegir al azar, y la fecha
del momento (`'now'|date('Y')`, para el año del pie). Son las dos únicas
formas de que el mismo proyecto dé otro HTML (§12.3), y tienen un precio: con
`random()`, cada compilación completa elige de nuevo, así que esa página
cambia y se vuelve a subir en cada despliegue. La previsualización, que
compila de forma incremental, conserva lo elegido, o la fecha, mientras no
cambie nada de lo que la página usa.

---

## 8. Markdown

CommonMark, más tablas, notas al pie, tachado (`~~texto~~`) y enlaces
automáticos: una dirección escrita tal cual (`https://ejemplo.com`,
`www.ejemplo.com`) se convierte en enlace. Se admite HTML escrito a mano.
Las etiquetas vacías salen como en HTML5, sin barra final: `<img …>`, `<br>`.

También los bloques de código sangrados de CommonMark: un párrafo que empieza
con cuatro espacios, o un tabulador, tras una línea en blanco, es código.
Eleventy los desactiva, así que al migrar desde él se quita la sangría que
no quería decir código.

**Cerrado:** el Markdown NO pasa por Twig. En su lugar hay atajos, con un
vocabulario cerrado (§8.1).

Dos añadidos forman parte del contrato:

- **Figuras.** Una imagen sola en su párrafo, enlazada o no, es una figura, y
  se construye igual que el atajo `imagen` (§8.2). Su texto alternativo da a
  la vez el `alt` de la imagen, en texto plano, y el pie, con su Markdown
  convertido: `![El **escaparate** en otoño](/img/escaparate.jpg)` lleva
  `alt="El escaparate en otoño"` y el pie `El <strong>escaparate</strong> en
  otoño`. Si la dirección empieza por `/` y es una imagen de `publico/`,
  lleva también su ancho y su alto, como el atajo. Un sitio que quiera otro
  marcado para sus figuras (una clase, un `loading="lazy"`) lo da con su
  propia plantilla `imagen`.
- **Enlaces externos.** Un enlace escrito en Markdown a otro dominio recibe
  `target="_blank"` y `rel="noopener"`. El dominio del sitio es el de `url`
  en `sitio.yml`, con y sin `www.`. El HTML escrito a mano y los enlaces de
  las plantillas no se tocan.

### 8.1 Atajos

Un atajo es una llamada con nombre y atributos con nombre dentro del Markdown:

    [imagen fichero="img/escaparate.jpg" alt="Escaparate de la librería" pie="La tienda en otoño"]

Y con contenido, cuando lo envuelve:

    [aviso tipo="importante"]
    Las plazas son limitadas.
    [/aviso]

Reglas:

- Solo se reconocen los nombres registrados: los que trae el motor y los que
  define el sitio. Un nombre que no está registrado se deja como texto; si
  lleva atributos, o es un cierre sin su apertura, además se avisa. Nunca se
  rompe el build. Un `[algo]` sin atributos es texto normal, y `[imagen](…)`
  sigue siendo un enlace de Markdown.
- Los atributos son siempre cadenas con nombre, entre comillas dobles o
  simples: `nombre="valor"` o `nombre='valor'`. No hay expresiones, ni
  variables, ni condicionales, ni acceso a colecciones: eso es lógica y la
  lógica vive en las plantillas.
- Un atajo simple puede ir en medio del texto. Si va solo en su párrafo y lo
  que produce es un bloque (una figura, un `div`, un vídeo…), sustituye al
  párrafo entero, porque un bloque no cabe dentro de un `<p>`. Si produce
  texto, como `dato`, se queda dentro del párrafo.
- Un atajo que envuelve contenido lleva la apertura y el cierre cada uno en
  su línea. Lo que hay entre ellos es Markdown y llega convertido a HTML. Si
  falta el cierre, se avisa y el atajo se cierra al final del texto.
- Los atajos se reconocen al convertir el Markdown, así que uno escrito dentro
  de código, o empezado con `\[`, se queda como texto. Su HTML se inserta
  después, sin que el convertidor lo toque.
- Los atajos son cosa de quien desarrolla el sitio: un editor puede ofrecer
  botones para insertar los que el sitio declare, sin que quien escribe el
  contenido tenga que poner atributos a mano.

Cada atajo es una plantilla de Twig. Los del sitio están en
`parciales/atajos/`, con el nombre del atajo: `parciales/atajos/aviso.twig`.
El nombre solo lleva minúsculas sin tildes, números y guiones. La plantilla
recibe:

- sus atributos, como variables: `{{ tipo }}`;
- `contenido`, el HTML de lo que envuelve, si envuelve algo;
- `sitio`, `datos` y `pagina`, igual que cualquier plantilla (§7.2).

Por eso `sitio`, `datos`, `pagina` y `contenido` no se pueden usar como
nombres de atributo: se avisa y se ignoran.

### 8.2 Atajos incluidos en el motor

Un sitio puede sustituir cualquiera de ellos con su propia plantilla del mismo
nombre en `parciales/atajos/`, que recibe las mismas variables.

- **`imagen`**: `[imagen fichero="img/escaparate.jpg" alt="…" pie="…"]`, con
  `fichero` dentro de `publico/`. Da `<figure>` con la imagen y, si hay pie,
  `<figcaption>`. Sin `pie`, el pie es el `alt`; con `pie=""`, no hay pie.
  `enlace` hace la imagen enlazada. La imagen lleva `width` y `height` cuando
  se pueden leer del fichero (§7.3, `dimensiones()`), para que el navegador
  le reserve el sitio antes de cargarla. La plantilla recibe `src`, `alt`,
  `pie` (ya en HTML), `enlace`, `ancho` y `alto` (`null` si no se pueden
  leer). Se avisa si falta `alt` o si el fichero no existe; sin `fichero` no
  se inserta nada.
- **`video`**: `[video url="https://www.youtube.com/watch?v=…" titulo="…"]`
  admite YouTube, que se inserta desde `youtube-nocookie.com` para no poner
  cookies hasta que se reproduce, y Vimeo, con `dnt=1`. Con
  `fichero="video/visita.mp4"` inserta un vídeo de `publico/`. La proporción es
  16:9 salvo que se indique otra con `proporcion="4:3"`, y se aplica con
  `aspect-ratio`. `titulo` es el nombre accesible del vídeo (`Vídeo` si
  falta). Con otra dirección, se avisa y no se inserta nada. La plantilla
  recibe `insercion` (la dirección para el `iframe`) o `src` (la del fichero),
  `proporcion` y `titulo`.
- **`archivo`**: `[archivo fichero="docs/tarifas.pdf" texto="Tarifas 2025"]`
  da `<a href="/docs/tarifas.pdf">Tarifas 2025</a> (PDF, 1,2 MB)`, con el
  tipo y el peso leídos del fichero de `publico/` al compilar (en KB por
  debajo de 1 MB). Sin `texto`, el nombre del fichero. Si el fichero no
  existe, se avisa y el enlace sale sin peso. La plantilla recibe `href`,
  `texto`, `tipo` y `peso`.
- **`dato`**: `[dato clave="cliente.nombre"]` escribe un valor de `datos/`,
  escapado. Es lo que necesitan los textos legales, que repiten el nombre, la
  razón social o la dirección del titular. Con `siFalta="…"` se escribe ese
  texto si el dato no existe o está vacío; sin él, se avisa y no se escribe
  nada. La plantilla recibe `valor`.

---

## 9. Ficheros, CSS y JavaScript

`publico/` se copia tal cual a la raíz de la salida, incluidos los ficheros
que empiezan por punto, como un `.htaccess`. Sin pipeline: el CSS y el JS
llegan ya escritos y, si hace falta, minificados fuera del motor.

Las imágenes no se procesan durante el build. Si hacen falta varios tamaños,
los genera otra herramienta (por ejemplo, un editor al subir la imagen) y deja
los ficheros en `publico/`.

### 9.1 CSS y JavaScript de cada página

El CSS y el JavaScript de una página se incrustan en ella, reunidos en un
`<style>` y un `<script>`. Es el equivalente del plugin de bundle de
Eleventy: cada plantilla declara lo que necesita donde lo usa, y todo se
escribe en un solo sitio.

- `{{ css('css/cabecera.css') }}`, en cualquier plantilla, parcial o atajo,
  declara uno o varios ficheros de `publico/` que necesita la página. No
  escribe nada donde está.
- `{{ css() }}`, sin argumentos, marca dónde va todo el CSS de la página:
  `<style>{{ css() }}</style>` en el layout base. Se rellena cuando la página
  está terminada, así que cuentan también los parciales que van después, como
  el pie.
- El campo `css` del front matter añade los ficheros de la página. Se suma a
  lo largo de la cascada (§3): un `_datos.yml` puede añadir el CSS de una
  sección entera.

Cada fichero sale una sola vez por página, en el orden en que se declara al
construirla, de arriba abajo; los del campo `css` van al final, para que
puedan sobrescribir a los demás. Se insertan tal cual. Un fichero que no
existe, o que no es un `.css` de dentro de `publico/`, se avisa y se salta. Si
una página declara CSS y ninguna plantilla escribe `{{ css() }}`, se avisa.

El JavaScript funciona igual, con `js('…')`, `<script>{{ js() }}</script>` y
el campo `js`.

    {# plantillas/base.twig #}
    <head>
      {{ css('css/reset.css', 'css/estilos.css') }}
      <style>{{ css() }}</style>
    </head>
    <body>
      {% include 'parciales/cabecera.twig' %}  {# declara css/cabecera.css #}
      {% block cuerpo %}{% endblock %}
      <script>{{ js() }}</script>
    </body>

---

## 10. Salida

En `salida/` van las páginas, lo que se copia de `publico/` y de
`contenido/`, y dos ficheros que genera el motor: `sitemap.xml` y, si el
sitio lo pide, `feed.xml`. Nada más: los añadidos van como ficheros normales
dentro de `contenido/` o de `publico/`. Una página con `url: /404.html` se
escribe como `404.html`, igual que cualquier otra.

Si dos cosas van al mismo fichero de salida (dos páginas, una página y un
fichero, un fichero de `publico/` y otro de `contenido/`), el build se detiene
con un error que nombra las dos. Las mayúsculas no cuentan.

Si el proyecto tiene su propio `/sitemap.xml` o `/feed.xml`, como página o
como fichero, gana el del proyecto y el motor no genera el suyo.

Una compilación completa deja `salida/` igual que el sitio construido: borra
lo que sobra de compilaciones anteriores, para que no se despliegue, y escribe
o copia solo lo que ha cambiado, para que lo que no cambia conserve su fecha.
Solo toca lo que hay dentro de `salida/`, y solo después de haber construido
todo sin errores: si una plantilla falla, `salida/` se queda como estaba.

La previsualización compila de forma incremental: rehace solo las páginas a
las que afecta cada cambio. El resultado es siempre el mismo que el de una
compilación completa; cómo se consigue está en `docs/compilacion.md`.

`ehundu desplegar` compila y publica `salida/` en el destino de `sitio.yml`
(§2): sube lo nuevo o cambiado y borra solo lo que subió Ehundu y ya no se
genera. Los detalles están en `docs/despliegue.md`.

### 10.1 `sitemap.xml`

Lleva las páginas HTML de la colección `todo` (las publicadas, con URL y sin
`listada: no`; §6), en su orden, salvo `/404.html`. Una página es HTML si su
URL acaba en `/` o en `.html`: `robots.txt` o un XML no entran. Cada una lleva
su dirección completa, la `url` del sitio más la de la página, y `<lastmod>`
con su fecha en la zona horaria del sitio, solo si la tiene.

### 10.2 `feed.xml`

Se genera en formato Atom si `sitio.yml` tiene una sección `feed`:

    feed:
      coleccion: blog     # de dónde salen las entradas; obligatorio
      limite: 20          # cuántas, las más recientes; 20 si no se indica
      titulo: Novedades   # el nombre del sitio si no se indica

Cada entrada lleva el título, la dirección completa, la fecha, la
`descripcion` como resumen si la tiene, y el contenido completo, con los
enlaces y las imágenes que empiezan por `/` pasados a direcciones completas,
porque un lector de feeds no sabe de qué sitio vienen. Una página de la
colección sin fecha no entra, y se avisa: Atom exige una fecha en cada
entrada.

---

## 11. `esquema.json`

Lo usan los editores; el motor lo ignora. Declara los tipos de contenido del
sitio, sus campos y quién puede editarlos.

    {
      "tipos": [
        {
          "nombre": "articulo",
          "titulo": "Artículo del blog",
          "carpeta": "contenido/blog",
          "cuerpo": "markdown",
          "campos": [
            { "nombre": "titulo", "tipo": "texto", "requerido": true },
            { "nombre": "subtitulo", "tipo": "texto" },
            { "nombre": "fecha", "tipo": "fecha" },
            { "nombre": "imagen", "tipo": "imagen" },
            { "nombre": "imagenAlt", "tipo": "texto", "requerido": true },
            { "nombre": "etiquetas", "tipo": "lista-texto", "rol": "agencia" }
          ]
        }
      ]
    }

Tipos de campo en la v1: `texto`, `parrafo`, `markdown`, `numero`, `fecha`,
`booleano`, `imagen`, `lista-texto`, `lista` (grupos repetibles) y `grupo`.

`rol` vale `cliente` (por defecto), para quien es titular del sitio y edita su
contenido, o `agencia`, para quien lo desarrolla. Los campos de agencia solo
aparecen en el modo avanzado.

`cuerpo` vale `markdown` o `ninguno`. Sin cuerpo, la página es pura ficha de
campos, que es el caso de las páginas de aterrizaje.

---

## 12. Reglas que no se rompen

1. Un editor no guarda nada que el motor no pueda leer desde la carpeta.
2. La orden de consola y cualquier programa que incruste el motor usan la
   misma API; la orden es una envoltura fina.
3. Un proyecto exportado en zip compila en cualquier máquina con PHP y da el
   mismo HTML, salvo lo que una plantilla elija al azar con `random()` o
   saque de la fecha del momento (§7.3).
4. El motor no ejecuta código propio del sitio en la v1.

---

## 13. Fuera de la v1

Catálogo de componentes con parámetros y consultas, tokens de estilo por sitio,
generación de imágenes en varios tamaños, multiidioma, paginación de listados,
procesadores sobre el HTML ya generado, pasos posteriores al build y búsqueda.

Ninguna de estas cosas debería obligar a cambiar lo de arriba cuando llegue.
Si al añadirlas hay que romper el contrato, el contrato estaba mal.

Por eso queda reservado ya el campo `componentes`, que será el del catálogo:
un sitio de la v1 no debe usarlo como campo propio.

---

## 14. Decisiones

1. **Cerrada.** Carpetas en español.
2. **Cerrada.** Campos del front matter en español, con los nombres ingleses
   aceptados como alias solo para facilitar la migración.
3. **Cerrada.** El Markdown no pasa por Twig; hay atajos (§8.1).
4. **Cerrada.** Multiidioma por fichero de idioma (§15). No entra en la v1,
   pero el contrato reserva lo necesario.
5. **Cerrada.** `sitio.yml` es obligatorio, con `nombre` y `url` (§2).
6. **Cerrada.** En la cascada, `etiquetas` se suman; el resto se sustituye
   (§3).
7. **Cerrada.** El motor valida el valor de los campos reservados y avisa;
   los campos desconocidos pasan sin comprobar (§4).
8. **Cerrada.** Los campos de sí/no admiten `sí`, `si`, `no`, `true` y
   `false` (§4).
9. **Cerrada.** `etiquetas` admite un texto suelto como lista de uno (§4).
10. **Cerrada.** Nuevos campos reservados `descripcion` y `listada`, y alias
    `subtitle` y `description` (§4, §6).
11. **Cerrada.** `plantilla: false` (o vacía) publica la página sin layout
    (§4, §7).
12. **Cerrada.** Un front matter que no es YAML válido detiene el build (§4).
13. **Cerrada.** Lo que empieza por punto se ignora en `contenido/` y
    `datos/`; en `datos/`, el `.yml` gana al `.json` (§3, §4).
14. **Cerrada.** Filtro `slug` propio, pensado para el español, en lugar de
    copiar el de Eleventy (§5.2).
15. **Cerrada.** Los patrones de `url` tienen un vocabulario cerrado:
    `{{ campo }}` y `{{ campo|slug }}`, con los alias ingleses (§5.1).
16. **Cerrada.** Una URL sin barra final ni extensión se toma como carpeta,
    con aviso (§5).
17. **Cerrada.** La URL derivada usa la ruta tal cual y avisa si da una mala
    URL (§5).
18. **Cerrada.** Dos páginas que se publican en el mismo fichero detienen el
    build (§5.3).
19. **Cerrada.** `coleccion()` ordena por fecha ascendente y, a igual fecha,
    por ruta, como Eleventy (§6.1).
20. **Cerrada.** Las páginas sin fecha van al final de una colección, por ruta
    (§6.1).
21. **Cerrada.** `orden` admite varios campos, deja al final los valores que
    faltan y compara los textos como el diccionario (§6.2).
22. **Cerrada.** `donde` sobre una lista significa «contiene» (§6.2).
23. **Cerrada.** Filtros `anterior` y `siguiente` (§6.2).
24. **Cerrada.** `todo` no se puede usar como etiqueta (§6).
25. **Cerrada.** Plantillas y parciales se nombran desde la raíz del
    proyecto, y Twig solo lee `plantillas/` y `parciales/` (§7.1).
26. **Cerrada.** Las plantillas no llevan front matter; se anidan con
    `extends` (§7.1).
27. **Cerrada; la sustituye la 47.** Una compilación completa vacía
    `salida/` antes de escribir (§10).
28. **Cerrada.** `svg()` inserta el fichero tal cual, sin declaración XML ni
    `DOCTYPE` (§7.3).
29. **Cerrada.** `activo()` es cierto también en las páginas de dentro de esa
    URL; `/` solo en la portada (§7.3).
30. **Cerrada.** Las fechas son días en la zona horaria del sitio, UTC si no
    se indica, y el filtro `fecha` las escribe en español (§2, §4, §7.3).
31. **Cerrada.** El contenido de otras páginas se convierte cuando se pide; si
    una página se necesita a sí misma, error (§7.2).
32. **Cerrada.** Si falta la plantilla de una página, el build se detiene
    (§7.1).
33. **Cerrada.** Una imagen sola en su párrafo se construye como el atajo
    `imagen`, con el `alt` en texto plano y el pie en HTML (§8).
34. **Cerrada.** Los atajos se reconocen al convertir el Markdown y su HTML se
    inserta después (§8.1).
35. **Cerrada.** Las plantillas de atajos reciben los atributos como
    variables, `contenido`, `sitio`, `datos` y `pagina` (§8.1).
36. **Cerrada.** Cuarto atajo incluido, `dato`; y atributos y resultado de
    `imagen`, `video` y `archivo` (§8.2).
37. **Cerrada.** Enlaces externos: otro dominio que el del sitio, con o sin
    `www.`, solo en Markdown (§8).
38. **Cerrada.** El Markdown admite tachado y enlaces automáticos (§8).
39. **Cerrada.** Etiquetas vacías de HTML5, sin barra final (§8).
40. **Cerrada.** El CSS y el JS se declaran donde se usan, con `css('…')` y
    `js('…')`, y se escriben donde marcan `css()` y `js()` (§9.1).
41. **Cerrada.** Los campos `css` y `js` se suman en la cascada, como
    `etiquetas` (§3, §9.1).
42. **Cerrada.** `publico/` se copia entero, también lo que empieza por punto;
    de `contenido/` se copia lo que no es página ni empieza por `_` (§4, §9).
43. **Cerrada.** Dos cosas que van al mismo fichero de salida detienen el
    build (§10).
44. **Cerrada.** Qué lleva el sitemap, y que un sitemap o feed del proyecto
    gana al del motor (§10, §10.1).
45. **Cerrada.** Feed Atom solo si `sitio.yml` tiene la sección `feed`, con la
    colección de la que sale (§2, §10.2).
46. **Cerrada.** Las plantillas y los datos de otros generadores que haya en
    `contenido/` no se copian, y se avisa (§4).
47. **Cerrada.** Una compilación completa sincroniza `salida/` en lugar de
    vaciarla: borra lo que sobra y escribe o copia solo lo que cambia (§10).
48. **Cerrada.** La previsualización compila de forma incremental, con el
    mismo resultado que una compilación completa; `--completo` rehace todo en
    cada cambio. `compilar` es siempre completa (§10, `compilacion.md`).
49. **Cerrada.** Qué se rehace se decide con lo que usó cada página y cada
    cuerpo la vez anterior. Si cambian `sitio.yml` o `datos/`, o si la
    compilación anterior falló, se rehace todo (`compilacion.md`).
50. **Cerrada.** Lo que se recuerda de una compilación a otra vive en memoria
    y no se guarda en disco; son datos simples, que un programa que incruste
    el motor podrá guardar donde le convenga (`compilacion.md`).
51. **Cerrada.** Compilar por lotes queda para más adelante; la compilación
    ya separa decidir qué se rehace de rehacerlo (`compilacion.md`).
52. **Cerrada.** `ehundu desplegar` compila entero y, si no hay errores,
    publica en el destino de `sitio.yml`; `--simular` dice qué haría sin
    tocar el destino y `--todo` lo sube todo (`despliegue.md`).
53. **Cerrada.** Lo que hay en el destino se sabe por un manifiesto que vive
    allí mismo, `.ehundu.json`, con el MD5 y el tamaño de cada fichero que
    subió Ehundu. Se guarda durante el despliegue, así que uno cortado sigue
    donde se quedó; sin él, se sube todo (`despliegue.md`).
54. **Cerrada.** Solo se borra lo que subió Ehundu y ya no se genera; lo
    demás que haya en el destino no se toca nunca (`despliegue.md`).
55. **Cerrada.** Se suben primero los ficheros, después las páginas y al final
    el sitemap y el feed; los borrados van después (`despliegue.md`).
56. **Cerrada.** FTP con la extensión `ftp` de PHP, cifrado (FTPS) salvo
    `cifrado: no` (§2, `despliegue.md`).
57. **Cerrada.** SFTP con phpseclib, que es PHP puro; la huella del servidor
    es obligatoria y se comprueba antes de mandar la clave (§2,
    `despliegue.md`).
58. **Cerrada.** S3 con la firma de AWS hecha en el motor, sin SDK; cada
    fichero con su tipo MIME y su MD5 (§2, `despliegue.md`).
59. **Cerrada.** Campos de `despliegue` y de `.secretos.yml`; un secreto en
    `sitio.yml` detiene el despliegue, y no hay variables de entorno (§2).
60. **Cerrada.** Nada después de desplegar en la v1: ni avisos a otros
    servicios ni vaciado de cachés de una CDN (`despliegue.md`).
61. **Cerrada.** Los secretos se escriben entre comillas simples, y los
    errores de `.secretos.yml` no enseñan su contenido (§2).
62. **Cerrada.** `dimensiones()` lee el ancho y el alto de una imagen de
    `publico/` sin procesarla, y el atajo `imagen` y las figuras del
    Markdown los ponen como `width` y `height` (§7.3, §8, §8.2).
63. **Cerrada.** Las plantillas pueden usar `random()`; es, con la fecha del
    momento, la excepción a que el mismo proyecto dé el mismo HTML (§7.3,
    §12.3).
64. **Cerrada.** Una página que genera HTML sin `titulo` avisa; un fragmento
    o un `robots.txt` no lo necesitan (§4).
65. **Cerrada.** Las páginas cuyo nombre o carpeta empieza por `_` se
    compilan como las demás (§4).
66. **Cerrada.** Los alias ingleses valen para el nombre del campo, no para su
    valor: `layout: post.njk` no es una plantilla de Ehundu, y el error dice
    cómo quitar la extensión. Valen también en `_datos.yml` (§4).
67. **Cerrada.** `donde` compara los textos exactos (§6.2).
68. **Cerrada.** `componentes` queda reservado para el catálogo de la v2 (§13).
69. **Cerrada.** El Markdown sigue CommonMark también en los bloques de código
    sangrados, aunque Eleventy los desactive; al migrar se quita la sangría
    accidental (§8).
70. **Cerrada.** El manifiesto del destino guarda también la `url` del sitio.
    Si la del destino es otra, el despliegue se detiene antes de tocar nada,
    porque lo normal es que la `ruta` apunte a otro sitio; `--todo` confirma
    que es el mismo sitio con otra dirección (`despliegue.md`).
71. **Cerrada.** `orden` y `donde` ven también `url` y `ruta`, con los mismos
    valores que las plantillas; `contenido`, no (§6.2).

---

## 15. Multiidioma: las tres opciones

No entra en la v1, pero condiciona el contrato, así que conviene decidirlo
ahora. En los tres casos hay cosas comunes: el idioma por defecto va sin
prefijo en la URL, los demás con prefijo (`/eu/...`), y cada página emite sus
etiquetas `hreflang` apuntando a sus traducciones.

### A. Carpeta por idioma

    contenido/es/contacto.md
    contenido/eu/kontaktua.md

Cada idioma es un árbol completo e independiente.

A favor: no exige nada nuevo al motor, porque la cascada de `_datos.yml` ya
funciona por carpeta; permite estructura y slugs distintos por idioma, que es
lo correcto para SEO; y una página que solo existe en un idioma no es un caso
especial.

En contra: nada relaciona una página con su traducción, hay que declararlo a
mano con un campo; un editor muestra dos árboles separados y es fácil publicar
en uno y olvidar el otro; y lo que no se traduce (imágenes, enlaces, teléfonos)
se duplica y acaba desincronizándose.

### B. Fichero por idioma, mismo árbol

    contenido/contacto.es.md
    contenido/contacto.eu.md

Mismo árbol de contenido; el idioma es parte del nombre del fichero. La
relación entre traducciones es la propia convención, sin declarar nada.

A favor: cada página y sus traducciones viven juntas, así que un editor puede
mostrarlas como pestañas de idioma sobre la misma página y es imposible no ver
que falta una; las faltantes se detectan solas; los slugs por idioma se
resuelven con el campo `url` en cada fichero.

En contra: el motor tiene que entender el idioma en el nombre del fichero, que
es código nuevo aunque poco; el árbol se ve más cargado; y lo no traducible
sigue repitiéndose salvo que se saque a un fichero de datos compartido.

### C. Un fichero con campos por idioma

    titulo:
      es: Contacto
      eu: Kontaktua

A favor: cero duplicación de lo no traducible, porque la imagen o el teléfono
se escriben una vez; un editor con pestañas sale casi gratis.

En contra: un cuerpo largo en Markdown dentro de un YAML es inmanejable, y los
cambios son ilegibles al compararlos. Sirve para páginas hechas de campos, no
para artículos.

### Decisión: B

Fichero por idioma, con lo no traducible en un fichero de datos compartido
junto a la página. Es la que mejor encaja con un editor, que es donde el
multiidioma se rompe en la práctica.

### 15.1 Traducción parcial

El caso normal no es un sitio duplicado: es una empresa que traduce portada,
quiénes somos y contacto, y mantiene el blog solo en castellano. El formato
tiene que tratar eso como lo corriente, no como la excepción.

Con fichero por idioma sale solo: existe lo que existe. Si hay
`contacto.es.md` y `contacto.en.md`, la página está en dos idiomas; si un
artículo solo tiene `.es.md`, solo existe en castellano y no se genera nada en
inglés.

Reglas que se derivan de eso:

- **Sin respaldo automático.** Una página sin traducir no se publica en ese
  idioma con el texto del otro. Servir castellano bajo una URL inglesa es
  contenido duplicado y un `hreflang` que miente.
- **`hreflang` solo entre las traducciones que existen**, y cada página se
  apunta también a sí misma.
- **El selector de idioma nunca enlaza a una URL que no existe.** Si la página
  actual no está traducida, el selector lleva a la portada de ese idioma, y la
  plantilla puede distinguir ambos casos para avisar al visitante.
- Las colecciones están acotadas al idioma de la página que las consulta. Un
  listado de blog en inglés nunca saca artículos en castellano. Para el caso
  raro en que hagan falta todos: `coleccion('blog', idioma: 'todos')`.

### 15.2 Idiomas en `sitio.yml`

    idiomas:
      - codigo: es
        nombre: Castellano
        predeterminado: sí
      - codigo: en
        nombre: English
        prefijo: /en

El predeterminado no lleva prefijo. Un sitio monolingüe simplemente tiene
`idioma: es` y no escribe esta sección; nada cambia para él.

### 15.3 Datos por idioma: menús y demás

Este es el punto que más duele si se resuelve mal. Los menús no son la
traducción uno a uno del mismo árbol: si el blog solo existe en castellano, el
menú inglés tiene una entrada menos. Lo mismo pasa con el pie y a veces con
secciones enteras.

Así que los datos siguen la misma convención que el contenido:

    datos/menus.yml        común a todos los idiomas (o sitio monolingüe)
    datos/menus.es.yml     castellano
    datos/menus.en.yml     inglés

Cada fichero es un menú completo e independiente, con sus propias entradas y
su propio número de entradas. No es una tabla de traducciones: es otro menú.

Al resolver `datos.menus`, el motor usa el fichero del idioma actual si existe
y, si no, el común. La misma regla vale para cualquier otro fichero de
`datos/`, de modo que `cliente.yml` puede quedarse común (teléfonos,
dirección, redes) mientras `textos.en.yml` lleva los rótulos traducidos.

Aquí sí hay respaldo al fichero común, al contrario que con las páginas: un
menú que falta deja el sitio sin navegación, mientras que una página que falta
simplemente no está.

Los `_datos.yml` de carpeta admiten lo mismo (`_datos.en.yml`) para poder
cambiar por idioma el patrón de URL o la plantilla de una sección.

### 15.4 Lo no traducible de una página

Junto a `servicios.es.md` y `servicios.en.md` puede haber un
`servicios.yml` con lo que no cambia de idioma: imagen principal, icono,
orden, identificadores. Se mezcla con el front matter de cada idioma, y este
último gana si repite un campo.

Así se evita tener la misma imagen escrita una vez por idioma, sin nada que
obligue a que coincidan.

### 15.5 Lo que hay que reservar en la v1

Aunque el multiidioma no entre todavía: el punto en el nombre de un fichero
queda prohibido para cualquier otro uso, las URL pueden llevar prefijo, y las
consultas a colecciones aceptan un parámetro de idioma que de momento siempre
vale lo mismo.

En la v1, una página o un fichero de datos con un punto de más en el nombre
(`contacto.en.md`, `menus.es.yml`) no se lee: se salta con un aviso.
