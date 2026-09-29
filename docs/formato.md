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

`idioma` es el código del idioma del sitio: dos o tres letras minúsculas, como
`es`, `eu` o `en`. Lo usan `fecha()` (§7.3) y el feed, y las plantillas lo ven
también en `pagina.idioma`. Si falta, es `es`; si no es un código así, se
avisa y se usa `es`. Un sitio en varios idiomas los declara en `idiomas`
(§15.1).

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

En un sitio en varios idiomas, un fichero puede llevar el código de un idioma
antes de la extensión (`menus.eu.yml`): en las páginas de ese idioma se mezcla
con el común (§15.6).

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

En un sitio en varios idiomas, cada carpeta puede tener además un `_datos.yml`
por idioma (`_datos.eu.yml`), y cada página, un `.yml` con sus campos comunes a
todos los idiomas (§15.6, §15.7).

---

## 4. Ficheros de contenido

Extensiones reconocidas: `.md` (Markdown) y `.twig` (plantilla). Cualquier otra
cosa dentro de `contenido/` se copia tal cual a `salida/`, con su misma ruta
(`contenido/blog/foto.jpg` → `blog/foto.jpg`), salvo los ficheros que empiezan
por guion bajo, como `_datos.yml`, y el `.yml` que lleva el nombre de una
página, que son sus campos comunes (§15.7). Lo que empieza por punto
(`.gitkeep`, `.git/`) se ignora. Las plantillas y los datos de otros generadores (`.njk`,
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

`idioma` también está reservado, pero no se escribe: sale del nombre del
fichero (`contacto.eu.md`, §15.2).

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
  publica a las 00:00 del 1 de octubre en esa zona. Una fecha sin comillas
  que no existe (`2026-13-01`) se lee como el texto que es: en `fecha` y
  `publicar` avisa, como cualquier fecha imposible, y en un campo que no es
  reservado llega así a la plantilla, sin aviso (decisión 88).
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
extensión (y el código de idioma, si lo hay: §15.2) y añadiendo barra final.
Un `index` da la URL de su carpeta, en cualquier nivel:

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

En un sitio en varios idiomas, las páginas que no son del predeterminado
llevan delante el prefijo de su idioma, también cuando su URL se escribe en
`url` (§15.3).

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

En un sitio en varios idiomas, las colecciones están acotadas al idioma de la
página que las consulta, y se puede pedir otro (§15.5).

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
(vacía en un fragmento), `ruta`, la del fichero dentro de `contenido/`, e
`idioma`, el código del idioma de la página (§15.8).
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

- `sitio`: lo que hay en `sitio.yml` (sin la sección de despliegue), más
  `sitio.idiomas` (§15.8).
- `datos`: los ficheros de `datos/`, con los del idioma de la página (§15.6).
- `pagina`: el front matter de la página actual, más `pagina.url`,
  `pagina.contenido` (el cuerpo ya convertido a HTML), `pagina.ruta`,
  `pagina.idioma` y `pagina.traducciones` (§15.8).

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
  es activo en la portada, y la raíz de cada idioma (`/eu/`), en la suya
  (§15.8). Para comparar exacto, `pagina.url == url`.
- `slug` (§5.2).
- `fecha(formato)` escribe una fecha con las mismas letras que el filtro
  `date` de Twig, pero con los nombres de meses y días en el idioma de la
  página (§15.9). En castellano, `pagina.fecha|fecha('d F Y')` da
  `18 marzo 2025`, y sin formato, `18 de marzo de 2025`. Una letra que tiene
  que salir tal cual se escapa
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
`listada: no`; §6), en su orden, salvo `/404.html`; en un sitio en varios
idiomas, las de todos ellos, sin la `404.html` de ninguno (§15.10). Una página
es HTML si su URL acaba en `/` o en `.html`: `robots.txt` o un XML no entran.
Cada una lleva su dirección completa, la `url` del sitio más la de la página, y
`<lastmod>` con su fecha en la zona horaria del sitio, solo si la tiene.

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

La fecha del feed es la de su entrada más reciente. Un feed sin entradas
lleva siempre el 1 de enero de 1970, en la zona horaria del sitio: Atom exige
una fecha también ahí, y con una fija el feed no cambia de una compilación a
otra, ni se vuelve a escribir ni a subir mientras siga vacío.

En un sitio en varios idiomas hay un feed por idioma (§15.10).

---

## 11. `esquema.json`

Lo usan los editores; el motor lo ignora. Declara los tipos de contenido del
sitio, dónde están sus páginas, qué campos llevan y quién puede editarlos.

    {
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

Cuando un tipo tiene más de una carpeta, el editor agrupa sus páginas por
carpeta y pregunta en cuál crear una nueva. Cada carpeta se rotula con el
título de la página que se llama como ella a su lado
(`contenido/libros/novela.md` para `contenido/libros/novela/`) o, si no la
hay, con el de su `index.md`; si tampoco, con su nombre. El editor no crea
carpetas: una categoría nueva lleva su `_datos.yml`, y eso es trabajo de
quien desarrolla el sitio.

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

Tipos de campo en la v1: `texto`, `parrafo`, `markdown`, `numero`, `fecha`,
`booleano`, `imagen`, `enlace`, `lista-texto`, `lista` y `grupo`.

- `enlace` es una dirección: completa, con `http://` o `https://`, o una ruta
  del propio sitio que empieza por `/`. El editor la comprueba, y puede
  completar el `https://` que falte.
- `lista-texto` es una lista de textos sueltos, como `etiquetas`.
- `grupo` es un conjunto de campos con nombre, un mapa en el front matter, y
  `lista` es una lista de grupos, que se pueden añadir, quitar y ordenar. Los
  dos declaran lo que llevan en su propio `campos`, con la misma forma que los
  del tipo. Ahí dentro solo caben campos simples: ni listas ni grupos.

Con el esquema de arriba, las presentaciones de un libro son:

    presentaciones:
      - dia: 2026-10-15
        lugar: Biblioteca municipal
      - dia: 2026-11-02

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
generación de imágenes en varios tamaños, paginación de listados, procesadores
sobre el HTML ya generado, pasos posteriores al build y búsqueda. El
multiidioma estuvo aquí hasta la 0.3 (§15).

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
4. **Cerrada.** Multiidioma por fichero de idioma (§15). No entró en la
   primera versión, pero el contrato reservó lo necesario; entra en la 0.3
   (decisiones 72 a 81).
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
72. **Cerrada.** Los idiomas se declaran en `idiomas`, y el primero es el
    predeterminado; sin `prefijo`, que es siempre `/codigo/`. Un sitio en un
    solo idioma lo dice con `idioma`, que es `es` si falta (§2, §15.1).
73. **Cerrada.** Un fichero sin código es del idioma predeterminado; con el
    código del predeterminado es lo mismo, y los dos a la vez detienen el
    build. Las traducciones se emparejan por carpeta y nombre (§15.2).
74. **Cerrada.** El motor pone el prefijo del idioma a toda URL que no es del
    predeterminado, también a la de `url` y a la de un patrón (§15.3).
75. **Cerrada.** `idioma` es un campo reservado que sale del nombre del
    fichero; las plantillas ven `pagina.idioma`, `pagina.traducciones` y
    `sitio.idiomas`, y `orden` y `donde` ven `idioma` (§15.2, §15.8).
76. **Cerrada.** Las colecciones se acotan al idioma de la página;
    `coleccion(nombre, idioma)` pide otro, o `todos` (§15.5).
77. **Cerrada.** Un fichero de datos por idioma se mezcla con el común por las
    claves de primer nivel, y un `_datos.eu.yml`, encima del `_datos.yml` de
    su carpeta (§15.6).
78. **Cerrada.** Un `.yml` con el nombre de una página son sus campos comunes a
    todos los idiomas, y no se copia a `salida/` (§4, §15.7).
79. **Cerrada.** `fecha()` escribe en el idioma de la página; el motor trae
    castellano, euskera e inglés, y con otro idioma avisa y usa los nombres
    en inglés (§7.3, §15.9).
80. **Cerrada.** Un solo sitemap con todos los idiomas; un feed por idioma, el
    del predeterminado siempre y los demás si tienen entradas (§15.10).
81. **Cerrada.** `activo()` trata la raíz de cada idioma como la portada, y se
    avisa si esa raíz no es la URL de ninguna página (§7.3, §15.3).
82. **Cerrada.** Un tipo del esquema puede vivir en varias carpetas: `carpeta`
    admite una lista, y una ruta que acaba en `/*` son sus subcarpetas
    directas. El editor agrupa por carpeta, pregunta dónde crear y no crea
    carpetas (§11).
83. **Cerrada.** `lista` y `grupo` declaran lo que llevan en su propio
    `campos`, y ahí dentro solo caben campos simples (§11).
84. **Cerrada.** En tipos y campos, `nombre` es la clave y `titulo` lo que se
    enseña; los campos admiten `ayuda` y los tipos, `plural` (§11).
85. **Cerrada.** Nuevo tipo de campo `enlace`: una dirección completa o una
    ruta del propio sitio (§11).
86. **Cerrada.** Una página que crea un editor se llama según el patrón
    `fichero` del tipo, `{{ titulo|slug }}` si falta; el nombre se fija al
    crearla y no cambia después (§11).
87. **Cerrada.** `cuerpo` admite también la forma de un campo, con su `titulo`
    y su `ayuda`; `"markdown"` a secas es la forma corta (§11).
88. **Cerrada.** Una fecha sin comillas que no existe (`2026-13-01`,
    `2026-01-32`) se lee como el texto que es y nunca detiene el build: en
    `fecha` y `publicar` avisa, como cualquier fecha imposible; en los demás
    campos llega así a la plantilla, sin aviso (§4).
89. **Cerrada.** El motor trae también el alemán para `fecha()`: meses y días
    con mayúscula, como se escriben, y «18. März 2025» por defecto (§15.9).
    Amplía la decisión 79.
90. **Cerrada.** Un feed sin entradas lleva como fecha el 1 de enero de 1970
    en la zona del sitio, y no el momento de la compilación: así no cambia de
    una compilación a otra (§10.2).

---

## 15. Multiidioma

Un sitio puede estar en varios idiomas. Cada página de cada idioma es su propio
fichero, en el mismo árbol que las demás, y el idioma va en el nombre:

    contenido/
      index.md                 portada en castellano
      index.eu.md              portada en euskera
      instalaciones.md
      instalaciones.eu.md      con url: /instalazioak/
      blog/
        un-articulo.md         solo en castellano

Un sitio en un solo idioma no escribe nada de esto y no cambia en nada.

### 15.1 Idiomas en `sitio.yml`

    idiomas:
      - codigo: es
        nombre: Castellano
      - codigo: eu
        nombre: Euskara

El primero es el predeterminado: sus páginas van sin prefijo en la URL, y las
de los demás, con el suyo (`/eu/`). `codigo` son dos o tres letras minúsculas,
como en ISO 639 (`es`, `eu`, `en`), y es lo que va en el nombre de los
ficheros, en el prefijo y en el `hreflang`. `nombre` es el nombre del idioma
para el selector, escrito en ese idioma; si falta, se usa el código.

Si `idiomas` no es una lista, si está vacía, si una entrada no tiene un código
válido o si un código se repite, el build se detiene: sin los idiomas no se
sabe qué ficheros se leen ni qué URL lleva cada página. Cualquier otro campo
de una entrada se avisa y se ignora.

Con `idiomas`, `idioma` sobra: si se escribe y no es el primero de la lista,
se avisa, y `sitio.idioma` vale siempre el del predeterminado. Un sitio en un
solo idioma lo dice con `idioma` (§2), y para el motor es un sitio con un solo
idioma en la lista.

### 15.2 Un fichero por idioma

El idioma de una página es el código que lleva antes de la extensión:
`contacto.eu.md` está en euskera. Un fichero sin código (`contacto.md`) está
en el idioma predeterminado, así que un sitio pasa a ser bilingüe sin renombrar
nada: basta con añadir los ficheros del otro idioma. Escribir el código del
predeterminado (`contacto.es.md`) es lo mismo; tener a la vez `contacto.md` y
`contacto.es.md`, o `contacto.md` y `contacto.twig`, detiene el build, porque
serían dos veces la misma página en el mismo idioma.

Las traducciones de una página son los ficheros de la misma carpeta con el
mismo nombre sin el código: `contacto.md` y `contacto.eu.md` son la misma
página. No hay que declararlo en ningún sitio, y un editor puede mostrarlas
como pestañas de idioma sobre la misma página.

Un fichero con un código que el sitio no declara (`contacto.fr.md` en un sitio
sin francés), o con un punto de más en el nombre (`guia.v2.md`), no se lee: se
salta con un aviso. El punto en el nombre de una página, de un fichero de
`datos/` o de un `_datos.yml` queda reservado para el idioma. Los ficheros que
se copian tal cual (`jquery.min.js`) llevan los puntos que quieran.

El idioma sale del nombre del fichero, no del front matter. `idioma` es un
campo reservado: si se escribe en una página, en un `_datos.yml` o en los
campos comunes de una página (§15.7), se avisa y se ignora.

### 15.3 URL

Las páginas del idioma predeterminado tienen la URL de siempre (§5). Las de los
demás llevan delante el prefijo de su idioma, y el motor lo pone siempre,
también a la URL que se escribe en `url` y a la que sale de un patrón:

    contenido/index.eu.md                                →  /eu/
    contenido/instalaciones.eu.md                        →  /eu/instalaciones/
    contenido/instalaciones.eu.md con url: /instalazioak/ →  /eu/instalazioak/
    contenido/404.eu.md con url: /404.html               →  /eu/404.html

Así el patrón `url: "/blog/{{ titulo|slug }}/"` de un `_datos.yml` vale para
todos los idiomas, y en la URL de una traducción solo se escribe lo que se
traduce. Una página que no es del predeterminado no puede salir de su prefijo.
Si su `url` ya empieza por él (`url: /eu/instalazioak/`), se avisa: saldría
`/eu/eu/instalazioak/`.

Si la raíz de un idioma (`/eu/`) no es la URL de ninguna página, se avisa: el
selector de idioma llevaría a una dirección que no existe (§15.8). Solo en los
sitios que declaran `idiomas`.

### 15.4 Traducción parcial

El caso normal no es un sitio duplicado: es una empresa que traduce portada,
quiénes somos y contacto, y mantiene el blog solo en castellano. Con un fichero
por idioma, eso sale solo: existe lo que existe. Si hay `contacto.md` y
`contacto.en.md`, la página está en dos idiomas; si un artículo solo tiene
`un-articulo.md`, solo existe en castellano y no se genera nada en inglés. No
se avisa de las traducciones que faltan.

Reglas que se derivan de eso:

- **Sin respaldo automático.** Una página sin traducir no se publica en ese
  idioma con el texto del otro. Servir castellano bajo una URL inglesa es
  contenido duplicado y un `hreflang` que miente.
- **`hreflang` solo entre las traducciones que existen**, y cada página se
  apunta también a sí misma (§15.8).
- **El selector de idioma nunca enlaza a una URL que no existe.** Si la página
  actual no está traducida, lleva a la portada de ese idioma, y la plantilla
  puede distinguir los dos casos para avisar al visitante (§15.8).

### 15.5 Colecciones

Las colecciones están acotadas al idioma de la página que las consulta: un
listado de blog en inglés nunca saca artículos en castellano. También `todo`.
Otro idioma se pide con un segundo argumento, que es un código o `todos`:

    {% for articulo in coleccion('blog') %}                  {# en el idioma de la página #}
    {% for articulo in coleccion('blog', 'eu') %}            {# solo los que están en euskera #}
    {% for articulo in coleccion('blog', idioma: 'todos') %} {# todos #}

Un código que el sitio no declara avisa y da una lista vacía. `orden` y `donde`
ven también `idioma` (§6.2).

### 15.6 Datos por idioma

Los menús no son la traducción uno a uno del mismo árbol: si el blog solo
existe en castellano, el menú inglés tiene una entrada menos. Lo mismo pasa con
el pie y a veces con secciones enteras. Así que los datos siguen la misma
convención que el contenido:

    datos/menus.yml        común a todos los idiomas
    datos/menus.es.yml     castellano
    datos/menus.en.yml     inglés

Al contrario que con las páginas, un fichero de datos sin código no es del
predeterminado: es común a todos. En una página, `datos.menus` es el fichero
común con el de su idioma encima, mezclados por sus claves de primer nivel,
igual que un `_datos.yml` sobre el de su carpeta (§3): gana el del idioma, y
las listas y los mapas se sustituyen enteros. Un `cliente.en.yml` que solo
tiene `horario` traduce el horario y deja los teléfonos y la dirección del
común. Un `menus.en.yml` con su lista `principal` es otro menú, con sus
propias entradas, no una tabla de traducciones. Si uno de los dos no es un
mapa, el del idioma sustituye entero al común. Un dato que solo existe en un
idioma (`textos.en.yml` sin `textos.yml`) solo está en las páginas de ese
idioma. El atajo `dato` (§8.2) lee lo mismo que la página en la que está.

Aquí sí hay respaldo al fichero común, al contrario que con las páginas: un
menú que falta deja el sitio sin navegación, mientras que una página que falta
simplemente no está.

Los `_datos.yml` de carpeta admiten lo mismo: en cada carpeta, `_datos.en.yml`
se pone encima de `_datos.yml` para las páginas en inglés, antes de pasar a la
subcarpeta, donde el `_datos.yml` vuelve a ganar por estar más cerca. Sirve
para cambiar por idioma la plantilla o el patrón de URL de una sección.

### 15.7 Lo no traducible de una página

Junto a una página puede haber un `.yml` con su mismo nombre y lo que no
cambia de idioma: la imagen principal, el icono, el orden, los destinos de los
enlaces.

    contenido/servicios.yml       imagen, icono, orden
    contenido/servicios.md        título y texto en castellano
    contenido/servicios.eu.md     título y texto en euskera

Sus campos van encima de la cascada y debajo del front matter de cada idioma,
que gana si repite alguno; `etiquetas`, `css` y `js` se suman, como en toda la
cascada (§3). Así la misma imagen no se escribe una vez por idioma, sin nada
que obligue a que coincidan. Vale también en un sitio de un solo idioma.

Ese `.yml` no se copia a `salida/`: es parte de la página, no un fichero
publicado. Un `.yml` sin una página con su nombre en la misma carpeta se copia
como cualquier otro fichero (§4).

### 15.8 Plantillas

Todas las páginas, también las que dan `coleccion()`, llevan:

- `pagina.idioma`, el código de su idioma. En un sitio de un solo idioma es el
  de `idioma`, así que `<html lang="{{ pagina.idioma }}">` vale para todos.
- `pagina.traducciones`, sus versiones publicadas, ella incluida, por código y
  en el orden de `idiomas`: `pagina.traducciones.eu.url`. Cada una tiene la
  misma forma que cualquier otra página. Un borrador o una traducción con
  `publicar` en el futuro no están.

`sitio.idiomas` es la lista de `idiomas`, cada uno con `codigo`, `nombre` y
`url`, la raíz de ese idioma: `/` para el predeterminado y `/eu/` para los
demás. En un sitio de un solo idioma tiene una sola entrada.

Con eso, la plantilla escribe los `hreflang` y el selector de idioma; el motor
no toca el HTML:

    {% for codigo, traduccion in pagina.traducciones %}
      <link rel="alternate" hreflang="{{ codigo }}" href="{{ sitio.url }}{{ traduccion.url }}">
    {% endfor %}

    {% for idioma in sitio.idiomas %}
      {% set traduccion = pagina.traducciones[idioma.codigo] %}
      <a href="{{ traduccion ? traduccion.url : idioma.url }}" hreflang="{{ idioma.codigo }}">{{ idioma.nombre }}</a>
    {% endfor %}

`activo('/eu/')` solo es cierto en la portada en euskera, igual que `/` solo lo
es en la del predeterminado (§7.3).

### 15.9 Fechas

`fecha()` (§7.3) escribe los meses y los días en el idioma de la página. El
motor trae castellano, euskera, inglés y alemán, cada uno con su formato por
defecto:

| Idioma | `fecha()`                | `fecha('l j F Y')`         |
|--------|--------------------------|----------------------------|
| `es`   | 18 de marzo de 2025      | martes 18 marzo 2025       |
| `eu`   | 2025eko martxoaren 18a   | asteartea 18 martxoa 2025  |
| `en`   | 18 March 2025            | Tuesday 18 March 2025      |
| `de`   | 18. März 2025            | Dienstag 18 März 2025      |

El formato por defecto del euskera no se puede escribir con letras: el sufijo
del año es `-ko` o `-eko` según cómo se lee el número (`2026ko`, `2025eko`), y
lo calcula el motor. Con las letras, el mes sale como se nombra suelto
(`martxoa`); `fecha('F\\r\\e\\n j\\a')` da `martxoaren 18a`.

Para un idioma que el motor no trae (`fr`), se avisa una vez, al leer
`sitio.yml`, y las fechas salen con los nombres en inglés, que son los de PHP.

### 15.10 Sitemap y feed

El sitemap es uno solo, con las páginas de todos los idiomas (§10.1) y sin la
`404.html` de ninguno. No lleva alternativas de idioma: los `hreflang` van en
el HTML de cada página.

El feed (§10.2) sale de la misma colección en cada idioma, cada uno en la raíz
de su idioma y con su `xml:lang`: `/feed.xml` en el predeterminado y
`/eu/feed.xml` en euskera. El del predeterminado se genera siempre, como en un
sitio de un solo idioma; el de los demás, solo si tienen alguna entrada. Todos
llevan el mismo título. Como en §10, un `/eu/feed.xml` del proyecto gana al
del motor.

### 15.11 Por qué un fichero por idioma

Había tres formas de hacerlo. En las tres, el idioma por defecto va sin
prefijo en la URL, los demás con prefijo, y cada página emite sus `hreflang`
apuntando a sus traducciones.

**A. Carpeta por idioma** (`contenido/es/contacto.md`,
`contenido/eu/kontaktua.md`). Cada idioma es un árbol completo e
independiente. No exige nada nuevo al motor, porque la cascada ya funciona por
carpeta, y una página que solo existe en un idioma no es un caso especial. Pero
nada relaciona una página con su traducción, hay que declararlo a mano con un
campo; un editor muestra dos árboles separados y es fácil publicar en uno y
olvidar el otro; y lo que no se traduce (imágenes, enlaces, teléfonos) se
duplica y acaba desincronizándose.

**B. Fichero por idioma, mismo árbol** (`contenido/contacto.md`,
`contenido/contacto.eu.md`). La relación entre traducciones es la propia
convención, sin declarar nada. Cada página y sus traducciones viven juntas, así
que un editor puede mostrarlas como pestañas de idioma sobre la misma página y
es imposible no ver que falta una; los slugs por idioma se resuelven con el
campo `url` de cada fichero. El motor tiene que entender el idioma en el nombre
del fichero, que es código nuevo aunque poco, y el árbol se ve más cargado; lo
no traducible se saca a los campos comunes de la página (§15.7).

**C. Un fichero con campos por idioma** (`titulo: { es: Contacto, eu:
Kontaktua }`). Cero duplicación de lo no traducible, y un editor con pestañas
sale casi gratis. Pero un cuerpo largo en Markdown dentro de un YAML es
inmanejable, y los cambios son ilegibles al compararlos. Sirve para páginas
hechas de campos, no para artículos.

Se eligió B: es la que mejor encaja con un editor, que es donde el multiidioma
se rompe en la práctica.
