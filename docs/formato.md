# El formato de un proyecto, versión 1 (borrador)

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
      .ehundu/               lo que recuerda el motor entre compilaciones: por dónde va un lote
                             y la memoria de la incremental (nunca se versiona)

Reglas:

- Todo lo que afecta al resultado vive dentro de esta carpeta, salvo el momento
  en que se compila (§12, regla 3).
- El motor solo lee de aquí y solo escribe en `salida/` y, en una compilación
  por lotes (§10.4) o incremental entre procesos (§10.5), en `.ehundu/`. Fuera
  del proyecto solo escribe la caché de tipografías de dompdf, si se piden PDF
  (§10.3), en la carpeta temporal del sistema.
- `.ehundu/` es del motor: lo crea él, lleva dentro su propio `.gitignore` para
  que no se versione, no se publica ni se lee como contenido, y no afecta al
  resultado. Se puede borrar cuando se quiera: lo único que se pierde es lo
  avanzado en una compilación por lotes a medias y lo que la incremental
  recordaba, y la siguiente compilación será completa.
- Los nombres de carpeta son fijos; no se configuran. Un proyecto siempre se
  reconoce igual, y se puede pasar de uno a otro, o migrar muchos, sin
  averiguar cómo está organizado cada uno.

**Cerrado:** nombres de carpeta en español. El proyecto se publicará con
documentación en español y en inglés, pero el formato es en español.

---

## 2. `sitio.yml`

    formato: 1
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
es la dirección del dominio del sitio, con `http://` o `https://` y sin ruta:
`https://www.ejemplo.com`, con o sin la barra final. Un sitio dentro de una
subcarpeta (`https://ejemplo.com/sub/`) no está soportado y es un error
(decisión 130). Las plantillas ven `sitio.url` sin la barra final, así que una
dirección completa se escribe `{{ sitio.url }}{{ pagina.url }}`. Si falta el
fichero o alguno de esos dos campos, el build se detiene con un error: sin
ellos no se pueden generar ni el sitemap ni el feed.

`formato` es la versión de este formato para la que está escrito el proyecto:
un número entero, `1`. No hay versión menor, porque dentro de una versión
mayor el formato solo crece (§12): un proyecto del formato 1 compila igual con
cualquier Ehundu que entienda el 1. Si falta, es `1`, y sin avisar, así que los
proyectos escritos antes de que existiera la clave no cambian. Un valor que no
es un entero mayor que cero (`"2"` entre comillas, `0`) detiene el build con un
error: tomarlo por `1` podría hacer pasar por bueno un proyecto de otro
formato. Si es mayor que la
versión que entiende esta versión de Ehundu, el build se detiene con un error
que lo dice (`ehundu compilar`, `servir` y `desplegar`): compilar a ciegas un
formato distinto daría un sitio mal hecho sin que nadie lo notara. Es lo
primero que se comprueba, antes que `nombre` y `url`.

`nombre` es un texto: `nombre: 2024`, sin comillas, es un número y detiene el
build (en el front matter, en cambio, un número se admite como texto, §4).

`zonaHoraria` es la zona en la que se leen y se escriben todas las fechas del
sitio, con su nombre estándar (`Europe/Madrid`, `America/Mexico_City`). Si
falta, es UTC. Una zona que no existe detiene el build.

**Qué detiene el build en `sitio.yml` y qué solo se avisa.** Detienen el build
`nombre`, `url`, `zonaHoraria`, `idiomas` y `formato` mal escritos: un valor que
se tomara por otro cambiaría, sin que nadie lo note, qué se lee o qué sale en
todo el sitio. Con `idioma`, `generador`, `feed`, `enlacesExternos`,
`encabezados` e `imagenes`, que solo ajustan un añadido, se avisa y se usa su
valor por defecto, o se ignora la sección.

**Una sección vacía.** Una sección sin valor (`encabezados:`) es lo mismo que no
ponerla. Un mapa vacío (`encabezados: {}`) la pone con todo por defecto: en
`encabezados` activa los `id` del nivel 2 al 6, y en `enlacesExternos` no cambia
nada; en `imagenes` y en `feed`, que necesitan `anchos` o `coleccion`, se avisa
y se ignora.

`idioma` es el código del idioma del sitio: dos o tres letras minúsculas, como
`es`, `eu` o `en`. Lo usan `fecha()` (§7.3) y el feed, y las plantillas lo ven
también en `pagina.idioma`. Si falta, es `es`; si no es un código así, se
avisa y se usa `es`. Un sitio en varios idiomas los declara en `idiomas`
(§15.1).

La sección `feed` pide un `feed.xml` y dice de qué colección sale (§10.2).

La sección `enlacesExternos` ajusta cómo salen los enlaces a otros dominios
que se escriben en Markdown (§8):

    enlacesExternos:
      aviso: (abre en ventana nueva)
      clase: visually-hidden
      noreferrer: sí

Sin ella, esos enlaces llevan `target="_blank"` y `rel="noopener"` y nada más.
Todos sus campos son opcionales:

- `aviso` es un texto que se añade dentro del enlace, detrás del suyo, en un
  `<span>`, para que los lectores de pantalla avisen de que se abre otra
  pestaña. El motor pone un espacio delante; los paréntesis, si se quieren, van
  en el texto. Un enlace que solo envuelve una imagen (una figura, §8) no lo
  lleva. Un sitio con varios idiomas (§15.1) da un texto por idioma, con su
  código como clave:

      aviso:
        es: (abre en ventana nueva)
        eu: (leiho berrian irekitzen da)

  Un idioma sin texto no lleva aviso, y se avisa de ello. Un texto suelto en un
  sitio con varios idiomas se usa en todos y se avisa también, porque casi
  nunca es lo que se quiere. Un código que el sitio no declara se avisa y se
  ignora.
- `clase` es la clase del `<span>`, y por defecto `visually-hidden`. El motor no
  trae CSS: el sitio tiene que definirla de modo que oculte el texto a la vista
  y no a los lectores de pantalla (el `.visually-hidden` o `.sr-only` de
  siempre). Si no lo hace, el aviso se ve.
- `noreferrer`, con `sí`, añade `noreferrer` a `rel` (`rel="noopener
  noreferrer"`): el sitio de destino no sabe de qué página viene la visita.
  Por defecto, no.

Un valor que no vale en cualquiera de ellos se avisa y se ignora. Cambiar esta
sección rehace todas las páginas, como cualquier otro cambio de `sitio.yml`.

La sección `encabezados` da un `id` a los encabezados del Markdown, para enlazar
a un apartado de una página y para construir un índice con ellos (§7.2, §8):

    encabezados:
      niveles: [2]
      enlace: ¶
      clase: ancla
      etiqueta: Enlace a «{texto}»

Sin ella, los encabezados salen sin `id`. Todos sus campos son
opcionales:

- `niveles` es la lista de niveles de encabezado que reciben `id`, del 1 al 6.
  Por defecto, del 2 al 6: el 1 suele ser el título de la página, y lo escribe
  la plantilla.
- `enlace` es el texto de un enlace permanente al apartado, que se añade al
  final del encabezado. Sin él, no hay enlace.
- `clase` es la clase de ese enlace, y por defecto `ancla`. El motor no trae
  CSS: si el sitio quiere que el enlace solo se vea al pasar el ratón, lo
  define él.
- `etiqueta` es el nombre accesible del enlace (`aria-label`), y `{texto}` se
  sustituye por el texto del encabezado. Un sitio con varios idiomas (§15.1)
  da un texto por idioma, con las mismas reglas que `aviso` de
  `enlacesExternos`. Sin ella, el enlace no lleva `aria-label` y un lector de
  pantalla solo lee su símbolo.

Un valor que no vale en cualquiera de ellos se avisa y se ignora. Una `clase` o
una `etiqueta` sin `enlace` no tienen a qué aplicarse, y se ignoran sin aviso.
Con `niveles: []` ningún encabezado recibe `id`. Cambiar esta sección rehace
todas las páginas.

La sección `imagenes` dice qué variantes de ancho tienen las imágenes del
sitio, para que `variantes()` (§7.3), el atajo `imagen` y las figuras del
Markdown (§8) escriban el `srcset`. El motor no las genera (§9): las busca, con
el nombre `<tronco>-<ancho>.<formato>`, en `publico/`.

    imagenes:
      anchos: [480, 960, 1600]
      formatos: [avif, webp]
      sizes: "(min-width: 60rem) 50rem, 100vw"

Sin ella, las imágenes salen sin `srcset`. Con ella:

- `anchos` es la lista de anchos, en píxeles, que tienen las imágenes del sitio.
  Es obligatoria: la sección sin ella se avisa y se ignora entera. Un valor que
  no es un entero positivo se avisa y se ignora; los repetidos se juntan, y se
  usan de menor a mayor.
- `formatos` es la lista de formatos, de más a menos preferido, porque el
  navegador usa el primero que entiende. Valen `avif`, `webp`, `jpg`, `jpeg` y
  `png`, que son también la extensión de los ficheros. Por defecto, `[webp]`.
  Un formato que no vale se avisa y se ignora. El último es el de reserva: el
  que se usa en el `<img>`, y el único que verían los PDF (§10.3), que solo
  leen bien JPEG, PNG y SVG. Un sitio con PDF que muestran el cuerpo de una
  página con figuras acaba la lista en `jpg` o `png`.
- `sizes` es el valor de `sizes` que llevan las imágenes del atajo `imagen` y
  las figuras del Markdown cuando no dicen otro. Por defecto, `100vw`, que
  nunca pide menos imagen de la que hace falta.

El tronco de una imagen es su ruta sin la extensión: el de `img/foto.jpg` es
`img/foto`, y sus variantes son `img/foto-480.avif`, `img/foto-480.webp`,
`img/foto-960.avif`… Quien las genera (un editor al subir la imagen, o un
programa aparte) deja un fichero por cada ancho y cada formato, y no amplía
nunca: si el original es más estrecho que un ancho, el fichero de ese ancho
sale con el ancho del original. El número del nombre es el ancho que se pidió;
el que cuenta en el `srcset` es el que tiene el fichero, que el motor lee
(`dimensiones()`, §7.3). Si dos anchos pedidos dan el mismo ancho real, entra
solo el menor, para no repetir la imagen.

Cambiar esta sección rehace todas las páginas, y una página depende de cada
fichero de variantes que mira, como de cualquier otro de `publico/`.

`generador` marca las páginas como hechas con Ehundu. Por defecto, el motor
añade `<meta name="generator" content="Ehundu">` al `<head>` de cada página
HTML, y esta clave la quita:

    generador: no

- **Dónde.** En cada página que escribe el motor y que tiene un `</head>`
  (sin distinguir mayúsculas), en el primero. La etiqueta va justo antes, en su
  propia línea y con la sangría de `</head>` más dos espacios; si `</head>` no
  está al principio de su línea, va pegada a él. Lo que no tiene `</head>` no
  cambia: un fragmento, un `.txt`, un XML, una página con `plantilla: false`
  que no es un documento. Tampoco cambian el PDF de una página, el sitemap ni
  el feed.
- **Si la plantilla ya la pone.** Una página que ya tiene una etiqueta `<meta>`
  con `name="generator"` no se toca: manda la plantilla.
- **Sin versión.** El contenido es siempre `Ehundu`. Con la versión, cada
  actualización del motor cambiaría todas las páginas de todos los sitios y
  obligaría a volver a subirlas (decisión 127).
- **Valores.** `no` o `false` la quita; `sí`, `true` o no poner la clave es lo
  mismo, que salga. Otro valor se avisa y se ignora. Cambiar la clave rehace
  todas las páginas, como cualquier otro cambio de `sitio.yml`.

La sección `despliegue` dice adónde publica `ehundu desplegar` (ver
`despliegue.md`). Destinos admitidos hoy: `carpeta`, `ftp`, `sftp` y
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

Las plantillas lo leen como `datos.nombre`. Un nombre con guiones
(`mi-menu.yml`) se escribe `datos['mi-menu']` en Twig, así que para usarlo
cómodamente conviene un nombre de una palabra, con letras, números y guiones
bajos.

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
Los ficheros PHP tampoco se copian, y detienen el build (§9).

El front matter va entre `---`, en YAML. Si no es YAML válido, el build se
detiene con un error que indica fichero y línea: seguir sin esa página haría
que el despliegue la borrara del sitio publicado.

Campos reservados:

| Campo         | Tipo    | Significado                                              |
|---------------|---------|----------------------------------------------------------|
| `titulo`      | texto   | Título de la página. Si genera HTML y falta, se avisa.    |
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
| `publicarEnSuFecha` | sí/no | Si es `sí`, no se publica antes de su `fecha` (abajo). |
| `pdf`         | sí/no   | Si es `sí`, la página sale además en PDF (§10.3).         |
| `agrupar`     | mapa    | La página es una plantilla que se repite por cada valor de un campo (§6.3). |
| `paginar`     | mapa    | La página parte una colección en trozos y se repite una vez por cada uno (§6.4). |

`idioma` también está reservado, pero no se escribe: sale del nombre del
fichero (`contacto.eu.md`, §15.2).

Cualquier otro campo se pasa a la plantilla sin tocarlo, salvo que tenga el
nombre de uno de los que el motor escribe en `pagina` (§7.2). El motor no valida
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

- **sí/no** admite `sí`, `si`, `no`, `true` y `false`, sin distinguir
  mayúsculas ni espacios de más (`Sí`, `NO`). YAML solo reconoce los dos
  últimos, así que el motor traduce los otros tres; y `true` y `false` solo
  valen sin comillas, como los lee YAML: `"true"` entre comillas es un texto y
  se avisa.
- **fecha** se escribe `AAAA-MM-DD`, con o sin comillas. Es ese día en la
  zona horaria del sitio (§2), desde su medianoche: `publicar: 2026-10-01`
  publica a las 00:00 del 1 de octubre en esa zona. Es un día y nada más: si
  se escribe también una hora (`2026-10-01 18:30:00`, `2026-10-01T18:30`) o una
  zona, la hora no cuenta, se toma solo el día y se avisa (decisión 129). Una
  fecha sin comillas que no existe (`2026-13-01`) se lee como el texto que es:
  en `fecha` y `publicar` avisa, como cualquier fecha imposible, y en un campo
  que no es reservado llega así a la plantilla, sin aviso (decisión 88).
- **texto** admite un número, que se toma como texto (`titulo: 2024`).
- **número** admite un número entre comillas (`orden: "2"`).
- **`etiquetas`**, **`css`** y **`js`** admiten un texto suelto, que cuenta
  como una lista de un elemento: `etiquetas: servicio` es lo mismo que
  `etiquetas: [servicio]`.
- **`plantilla`** vacía (`plantilla: ""`) es lo mismo que `plantilla: false`.

**Publicar en su fecha.** Una `fecha` futura no oculta nada: es una fecha más,
como la de un evento en una agenda. Para un blog que se escribe por adelantado
está `publicarEnSuFecha: sí`, que hace que la página no se publique antes de
su `fecha`: es lo mismo que escribir `publicar` con esa fecha, sin repetirla.
Se pone donde se pondría cualquier valor común, en el `_datos.yml` de la
carpeta (§3), y una subcarpeta lo quita con `publicarEnSuFecha: no`. Una
página sin `fecha`, o con la `fecha` ya pasada, no se ve afectada.

- **Quién manda.** Si la página lleva `publicar`, manda `publicar`, también
  cuando es anterior a la `fecha`: una fecha escrita a propósito no se
  contradice con otra deducida. `borrador` manda sobre las dos.
- **A qué hora.** Como `publicar`: desde la medianoche del día de la `fecha`
  en la zona horaria del sitio (§2). El sitio es estático, así que la página
  sale cuando el sitio se vuelve a compilar después de ese momento (§11,
  *Programar*).
- **Lo demás.** Todo lo que este documento dice de una página con `publicar`
  en el futuro (§5.3, §6, §10, §15.8) vale también para una con
  `publicarEnSuFecha` y su `fecha` en el futuro: no genera fichero ni sale
  en colecciones, sitemap, feed ni traducciones. En un sitio en varios idiomas
  cada traducción mira su propia `fecha`, que normalmente es la del `.yml`
  común de la página (§15.7), así que salen el mismo día.
- **La previsualización con borradores** (`ehundu servir --borradores`) lo
  trata como a `publicar`: lo enseña todo.

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

Además de los campos, el patrón tiene `{{ nombreDeFichero }}`: el nombre del
fichero de la página, sin carpeta, sin extensión y sin el código de idioma
(`contenido/blog/un-articulo.eu.md` da `un-articulo`). Sirve para publicar las
páginas de una carpeta sin la carpeta en la URL: con esto en el `_datos.yml`
de `contenido/posts/`,

    url: "/{{ nombreDeFichero }}/"

`contenido/posts/mi-entrada.md` sale como `/mi-entrada/` y no como
`/posts/mi-entrada/`, y una entrada nueva no tiene que escribir nada. No es un
campo: el front matter y la cascada no lo cambian, y si una página tiene un
campo con ese nombre, en el patrón manda el del fichero. Admite `|slug`, que
conviene si los nombres de fichero pueden llevar mayúsculas o espacios, porque
sin él se usan tal cual. Un `index` tiene `index` por nombre: quien quiera la
URL de su carpeta, en una carpeta con este patrón, escribe su propio `url`.

Si el patrón usa un campo que la página no tiene, o que no es un texto o un
número, si un `slug` sale vacío, o si entre las llaves hay otra cosa, se avisa
y se usa la URL que sale de la ruta.

En una página con `agrupar` (§6.3) vale además `{{ grupo }}`, el valor del
grupo de cada página que genera, y el patrón tiene que usarlo. En ella,
`{{ nombreDeFichero }}` es el del fichero que genera las páginas.

En el `url` de `paginar` (§6.4) vale `{{ numero }}`, el número de cada página
que genera, a partir del 2, y el patrón tiene que usarlo. Es el único patrón
donde existe: el `url` de la propia página no lo tiene.

### 5.2 El filtro `slug`

Convierte un texto en un tramo de URL:

- pasa a minúsculas y convierte las letras de la tabla de abajo, que quita
  tildes y diéresis (`ü` → `u`, `ñ` → `n`, `ç` → `c`, `ș` → `s`) y descompone
  algunas ligaduras (`ß` → `ss`, `æ` → `ae`, `œ` → `oe`);
- `&` se convierte en `y`, y el punto volado del catalán desaparece
  (`col·legi` → `collegi`);
- cualquier otro carácter que no sea una letra `a` a `z` o un número se
  convierte en guion, sin guiones repetidos ni en los extremos. Eso incluye las
  letras de otros alfabetos (griego, cirílico…) y las del latino que la tabla no
  recoge (las del vietnamita, por ejemplo), que desaparecen.

`Cómo elegir un buen libro: guía` da `como-elegir-un-buen-libro-guia`.

La tabla de letras es esta, con lo que sale a la izquierda y las letras que lo
dan a la derecha (cada una, también en mayúscula):

    a   àáâãäåāăą
    c   çćĉċč
    d   ďđð
    e   èéêëēĕėęě
    g   ĝğġģ
    h   ĥħ
    i   ìíîïĩīĭįı
    j   ĵ
    k   ķ
    l   ĺļľŀł
    n   ñńņňŉ
    o   òóôõöøōŏő
    r   ŕŗř
    s   śŝşšſș
    t   ţťŧț
    u   ùúûüũūŭůűų
    w   ŵ
    y   ýÿŷ
    z   źżž
    ss  ß
    ae  æ
    oe  œ
    th  þ
    ij  ĳ

**La tabla y la conversión de `&` en `y` son parte del contrato y no cambian
dentro de una versión mayor** (decisión 134). Añadir una letra cambiaría la
dirección de toda página cuyo título la lleve, que hoy sale con un guion. El
mismo filtro fija el orden alfabético de los textos (§6.1), los grupos de
`agrupar` (§6.3) y los `id` de los encabezados (§8): lo que se decide aquí vale
también para ellos.

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
  sin distinguir mayúsculas ni tildes (con la tabla del `slug`, §5.2), con la
  `ñ` después de la `n` y sin contar los signos `¿` y `¡`: «¿Cómo llegar?» va
  entre las «c», no antes de la «a». Es una comparación del valor de cada
  carácter, no la de un diccionario entero: un número escrito en un texto se
  compara como texto («Capítulo 10» va antes que «Capítulo 2») y las letras de
  otros alfabetos se ordenan por su código. A igualdad, las páginas quedan en
  el orden que tenían.
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
de la lista. Tampoco cuenta `markdown` (§7.2).

Como `orden` respeta el orden previo en los empates, `orden('fecha desc')` no
es lo mismo que `invertir` cuando hay páginas con la misma fecha: la primera
las deja por ruta y la segunda, al revés. Lo que en Eleventy es
`collections.blog | reverse | head(6)` es aquí
`coleccion('blog')|invertir|limite(6)`.

### 6.3 Una página por cada valor

Un blog con etiquetas abiertas no puede tener una página escrita a mano por
cada etiqueta: la siguiente entrada puede traer una nueva. Para eso, una página
con `agrupar` no es una página sino una plantilla de páginas: el motor la
repite una vez por cada valor distinto de un campo de las páginas de una
colección.

    ---
    plantilla: etiqueta
    agrupar:
      coleccion: blog       # de dónde salen las páginas; obligatorio
      campo: etiquetas      # el campo por el que se agrupan; obligatorio
      excluir: [blog]       # valores que no generan página; opcional
    url: "/etiqueta/{{ grupo|slug }}/"
    etiquetas: [temas]
    ---

La página con `agrupar` no genera fichero, ni sale en colecciones ni en el
sitemap. Cada página que genera lleva los campos de la que la define (con la
cascada del §3, como cualquier otra) y además:

- `grupo`: el nombre del grupo, en texto;
- `paginas`: las páginas del grupo, con la forma de las que da `coleccion()`
  (§7.2) y en su mismo orden, así que los filtros del §6.2 valen:
  `pagina.paginas|orden('fecha desc')`.

Como `contenido` (§7.2), las dos tapan un campo del front matter que se
llame igual, solo en las páginas generadas.

Reglas:

- **Qué entra.** Las páginas de la colección, tal como las da `coleccion()`
  (§6): sin borradores, sin las de `publicar` en el futuro y sin las de
  `listada: no`, y en el idioma de la página que define el grupo (§15.5). Por
  eso un valor que solo usan páginas que aún no se publican no tiene página
  hasta que sale la primera, y nunca hay un grupo vacío.
- **Los valores.** `campo` es un campo de las páginas de la colección, tal como
  llega a la plantilla (§7.2), con la cascada. Una lista da un valor por
  elemento; un texto suelto, uno, como en `etiquetas` (§4). Un valor que no es
  texto ni número, o que está en blanco, se ignora y se avisa. Si el campo es
  `etiquetas` hay que acordarse de `excluir`: la carpeta pone a cada artículo
  el nombre de su colección (§3), y sin `excluir: [blog]` habría un grupo con
  todo el blog.
- **Variantes.** Los valores se agrupan por su `slug` (§5.2): `Cultura pop`,
  `cultura-pop` y `Cultura Pop` son un grupo. Es la única regla coherente,
  porque la URL sale del `slug` y dos grupos con la misma saldrían al mismo
  fichero. `grupo` es la variante que más páginas usan y, a igualdad, la
  primera en el orden de `orden` (§6.2); si aun así empatan (`Ideas` e
  `ideas`), la de menor código de carácter, para que el nombre no dependa del
  orden en que llegan las páginas. `excluir` también compara por `slug`.
  Un valor cuyo `slug` sale vacío se ignora y se avisa.
- **La URL.** Tiene que usar `{{ grupo }}`, casi siempre como `{{ grupo|slug }}`
  (§5.1), que es lo que da un tramo de URL válido: sin el filtro, el valor se
  escribe tal cual. Sin `url`, o con una que no lo use, todas las páginas irían
  al mismo fichero: se avisa y no se genera ninguna. El resto del §5 vale: el prefijo de idioma (§15.3) y el
  error si dos páginas van al mismo fichero.
- **El título.** Si la página que define el grupo no tiene `titulo`, el de
  cada página generada es su `grupo`, y no avisa. Cualquier otro campo se pasa
  como está: lo que hay que componer con el grupo (`Etiqueta: …`) se escribe
  en la plantilla con `pagina.grupo`.
- **Sitemap y `todo`.** Las páginas generadas salen en el sitemap y en `todo`,
  y en las colecciones que declaren sus `etiquetas`. Si la que las define lleva
  `listada: no`, lo heredan como cualquier otro campo (§3) y no salen en
  ninguno de los dos.
- **El orden.** Las páginas generadas no tienen `fecha` salvo que la herede la
  que las define; en una colección van, como cualquier página sin fecha, al
  final, y entre ellas por `slug` de su grupo. Con `etiquetas: [temas]`, el
  índice de todos los grupos es `coleccion('temas')` y su recuento,
  `tema.paginas|length`, sin repetir en un fichero de datos lo que ya dicen
  las páginas.
- **Idiomas.** Cada idioma tiene su propia página con `agrupar` (`etiqueta.md`,
  `etiqueta.eu.md`) y agrupa las páginas de ese idioma. En una página
  generada, `pagina.traducciones` solo trae ella misma (§15.8): los grupos de
  un idioma no se corresponden con los de otro.
- **Los avisos y los errores.** Un `agrupar` al que le falta `coleccion` o
  `campo` avisa, con su línea, y no genera nada. Una clave que no admite se
  avisa y se ignora. Si ni siquiera es un mapa (`agrupar: sí`), se avisa, se
  ignora y la página es una más. Una colección sin páginas no es un error: no
  hay grupos. `excluir` admite, como `etiquetas`, un texto suelto. `agrupar` no
  se hereda: en un `_datos.yml` se avisa y se ignora, porque todas las páginas
  de la carpeta serían plantillas de páginas y ninguna saldría.
- **Sin vueltas.** Una página con `agrupar` no puede depender de las páginas
  que genera ella misma ni otra (`coleccion: todo`, o una colección a la que
  pertenecen las generadas): el build se detiene con un error que muestra la
  cadena, como en el §7.2.
- **No es paginación.** Parte un conjunto en grupos; no parte una lista larga
  en trozos. Eso es `paginar` (§6.4).

`grupo` y `paginas` los tienen también las páginas generadas cuando salen de
`coleccion()` (§7.2), que es lo que hace posible el índice de arriba.

La compilación incremental da siempre lo mismo que la completa. Una página
generada se rehace cuando entra o sale una página de su grupo o cambian los
campos de alguna; si solo cambia el cuerpo de una de ellas, no, salvo que la
plantilla lo muestre. Las páginas que se generan, o dejan de generarse, se
deciden de nuevo en cada compilación. Cómo se consigue es cosa de
`docs/compilacion.md`.

El esquema (§11) no sabe de `agrupar`. Es cosa de quien desarrolla el sitio, y
un editor ni la enseña ni la toca; lo que sí hace es que el editor pueda
escribir una etiqueta nueva en una entrada sin que nadie tenga que crear antes
su página.

### 6.4 Partir una lista en páginas

Un listado largo se parte en trozos, uno por página. Una página con `paginar`
es, como con `agrupar`, una plantilla de páginas: ella misma es la primera, en
su `url`, y el motor genera las demás.

    ---
    plantilla: blog
    url: /blog/
    paginar:
      coleccion: blog          # de dónde salen las páginas; obligatorio
      porPagina: 10            # cuántas lleva cada página; obligatorio
      orden: fecha desc        # criterio de orden() (§6.2); opcional
      url: "/blog/{{ numero }}/"   # la de las páginas a partir de la segunda; opcional
    ---

Una entrada nueva que llene la última página abre la siguiente sin que nadie
cree nada, y una que se quita la cierra.

- **Qué entra.** Las páginas de la colección, tal como las da `coleccion()`
  (§6): sin borradores, sin las de `publicar` en el futuro y sin las de
  `listada: no`, y en el idioma de la página (§15.5). La propia página que
  pagina no entra en su colección, aunque lleve su etiqueta, ni tampoco las
  que genera: `blog/index.md` hereda la etiqueta `blog` de su carpeta (§3) y no
  por eso se lista a sí misma.
- **El orden.** El de `orden`, que se escribe como el criterio del filtro
  `orden()`; sin él, el de `coleccion()` (§6.1), de la más antigua a la más
  reciente. Un blog suele querer `fecha desc`. Se aplica antes de partir.
- **Los trozos.** `porPagina` es un entero mayor que cero. Cada trozo lleva
  como mucho esa cantidad, y el último, las que sobren. Una colección sin
  páginas no es un error: hay un solo trozo, vacío, y la página sale, para
  que un listado sin entradas siga existiendo. Un `porPagina` que no es un
  entero mayor que cero se avisa y la página es una más, sin partir.
- **La URL.** La primera es la `url` de la página (§5), con las reglas de
  siempre. Las siguientes, la de `paginar.url`, que es un patrón del §5.1 con
  `{{ numero }}` (el número de la página, desde el 2) y que tiene que
  usarlo; sin `paginar.url`, la de la primera más `pagina/2/`, `pagina/3/`…
  (`/blog/` da `/blog/pagina/2/`). Esa forma por defecto exige que la `url`
  de la primera acabe en `/`: si no es así, y no hay `paginar.url`, se avisa y
  la página es una más, sin partir. Sin `{{ numero }}` en el patrón todas irían
  al mismo fichero: se avisa y pasa lo mismo. Lo demás del §5 vale:
  el prefijo de idioma (§15.3) y el error si dos páginas van al mismo
  fichero. `paginar.url` existe para quien migra y ya tiene otra forma
  (`/blog/2/`, `/page/2/`) y no quiere perder direcciones.
- **Lo que ve la plantilla.** Cada página, la primera también, lleva los
  campos de la que la define (con la cascada del §3) y además:
    - `paginas`: las páginas de su trozo, con la forma de las que da
      `coleccion()` (§7.2) y en el orden pedido;
    - `paginacion`: `actual` y `total` (cuántas páginas hay), `porPagina`,
      `cantidad` (cuántas hay en la colección), `primera` y `ultima` (sus URL),
      `anterior` y `siguiente` (la URL, o nada en la primera y en la última) y
      `enlaces`, una lista con un elemento por página, con `numero`, `url` y
      `actual` (`true` en la que se está), para pintar el índice de páginas.

  Como `contenido` (§7.2), las dos tapan un campo del front matter que se
  llame igual. El título es el de la página que define: la plantilla añade
  «Página 2» con `pagina.paginacion.actual`.
- **Colecciones y sitemap.** La primera es una página como cualquier otra, con
  la etiqueta y el sitemap que le toquen. Las que genera no entran en ninguna
  colección, tampoco en `todo`, ni en el sitemap, como las de `listada: no`
  (§4): se llega a ellas por los enlaces de la paginación.
- **Idiomas.** Cada idioma tiene su propia página con `paginar`
  (`blog.md`, `blog.eu.md`) y parte las páginas de ese idioma. En una página
  generada, `pagina.traducciones` solo trae ella misma; la primera, que es la
  que define la página, tiene las suyas (§15.8).
- **Con `agrupar`.** Una página no puede llevar las dos claves: se avisa y
  `paginar` se ignora. Partir cada grupo en páginas queda reservado, sin
  definir. `paginar` no se hereda: en un `_datos.yml` se avisa y se ignora,
  porque todas las páginas de la carpeta serían plantillas de páginas.
- **Los avisos y los errores.** Un `paginar` al que le falta `coleccion` o
  `porPagina` avisa, con su línea, y no parte nada. Una clave que no admite se
  avisa y se ignora. Si ni siquiera es un mapa (`paginar: sí`), se avisa, se
  ignora y la página es una más. Un `orden` que no es un criterio válido se
  avisa y se usa el de `coleccion()`. En un fragmento (`url: false`) no hay
  dónde poner la primera página: se avisa y no se parte nada.
- **Sin vueltas.** Las páginas que genera no están en ninguna colección, así
  que ninguna página puede depender de ellas por una colección: no hay
  cadenas que comprobar, a diferencia de `agrupar` (§6.3). La propia página
  tampoco entra en su trozo, aunque herede la etiqueta de su colección.

La compilación incremental da siempre lo mismo que la completa. Las páginas que
se generan, o dejan de generarse, se deciden de nuevo en cada compilación, y
una se rehace cuando cambia lo que la define, o su trozo: que entre, salga o
cambie de sitio una página, o cambien los campos de alguna de ellas. Cómo se
consigue es cosa de `docs/compilacion.md`.

El esquema (§11) no sabe de `paginar`: es cosa de quien desarrolla el sitio, y
un editor ni la enseña ni la toca. Lo que sí consigue es que quien edita añada
una entrada y la lista se reparta sola.

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

`pagina.markdown` es el cuerpo de una página en Markdown tal como está
escrito: sin el front matter y sin convertir, con los atajos sin resolver.
Las páginas de una colección la tienen también (`articulo.markdown`). Es lo
que necesita un `llms-full.txt`, que reúne el texto de varios artículos. Twig
la escapa como cualquier texto: en un fichero que no es HTML se escribe con
`|raw`. No existe en una página `.twig`, cuyo cuerpo es una plantilla. Quien
la lee depende del fichero de esa página: si cambia, se rehace la que la
muestra. Como `contenido`, tapa un campo del front matter que se llame igual,
y no sirve para filtrar ni ordenar (§6.2).

`pagina.encabezados` son los encabezados del cuerpo que reciben `id` (§2, §8),
en el orden en que aparecen, cada uno con `nivel`, `texto` e `id`. El `texto` es
texto plano, sin enlaces ni énfasis y sin el enlace permanente. Es lo que
necesita un índice de apartados. Las páginas de una colección la tienen también
(`articulo.encabezados`) y, como `contenido`, se calcula la primera vez que
alguien la pide, con el mismo error si una página acaba necesitándose a sí
misma. Sin la sección `encabezados` en `sitio.yml` está vacía; en una página
`.twig`, cuyo cuerpo es una plantilla, también. Quien la lee depende del
contenido de esa página: si cambia, se rehace la que la muestra. Como
`contenido`, tapa un campo del front matter que se llame igual, y no sirve para
filtrar ni ordenar (§6.2).

Una página que genera otra con `agrupar` (§6.3) tiene además `pagina.grupo`,
el valor, y `pagina.paginas`, las páginas del grupo. Las páginas generadas
las tienen también cuando salen de `coleccion()`. Una página con `paginar`
(§6.4), y las que genera, tienen `pagina.paginas`, las de su trozo, y
`pagina.paginacion`.

Los nombres que el motor escribe en `pagina` son estos, y solo estos. Si el
front matter de una página tiene un campo con el mismo nombre, el del motor
tapa el suyo (decisión 133):

| Nombre         | Qué es                                          | Cuándo | Un campo propio con ese nombre |
|----------------|-------------------------------------------------|--------|--------------------------------|
| `url`          | su dirección (§5)                               | siempre | reservado (§4)               |
| `idioma`       | su idioma (§15.2)                               | siempre | reservado: se avisa y se ignora |
| `pdf`          | la dirección de su PDF (§10.3)                  | con `pdf: sí` | reservado: en el front matter es sí/no |
| `ruta`         | su fichero, desde `contenido/`                  | siempre | se avisa y se ignora |
| `contenido`    | su cuerpo ya convertido a HTML                  | siempre | se avisa y se ignora |
| `markdown`     | su cuerpo en Markdown, sin convertir            | en una página `.md` | se avisa y se ignora |
| `encabezados`  | sus encabezados con `id` (§8)                   | siempre, vacía si no hay | se avisa y se ignora |
| `traducciones` | sus traducciones (§15.8)                        | siempre, vacía si no hay | se avisa y se ignora |
| `grupo`        | el valor de su grupo (§6.3)                     | en lo que genera `agrupar` | solo se tapa en esas páginas |
| `paginas`      | las páginas del grupo o del trozo (§6.3, §6.4)  | en lo que genera `agrupar` o `paginar` | solo se tapa en esas páginas |
| `paginacion`   | dónde está en la lista partida (§6.4)           | en lo que genera `paginar` | solo se tapa en esas páginas |

Un nombre nuevo no se añade sin anunciarlo (§12, regla 5).

Un campo que falta sale vacío, como en Nunjucks: `{% if pagina.imagen %}`
funciona sin comprobar antes si existe. Twig escapa el HTML de todo lo que
escribe, salvo lo que ya es HTML hecho por el motor: `pagina.contenido` (también
el `articulo.contenido` de las páginas de una colección y el `contenido` y el
`pie` que reciben los atajos, §8.1), lo que devuelven `svg()`, `css()`, `js()`,
`css_fichero()` y `js_fichero()`, y el resultado del filtro `markdown` (§7.3).
Cualquier otro texto, un campo del front matter por ejemplo, se escapa salvo
que la plantilla pida lo contrario con `|raw`.

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
- `variantes(ruta)` da las variantes de ancho de una imagen, las que declara la
  sección `imagenes` de `sitio.yml` (§2), para escribir `srcset`. Admite el
  tronco (`img/foto`) o el fichero (`img/foto.jpg`): quita la extensión si es
  la de una imagen. Da un mapa con:
    - `src`, la dirección de la variante más ancha del último formato, con `/`
      delante, y `ancho` y `alto` de ese fichero;
    - `srcset`, las variantes del último formato, de menor a mayor ancho y con
      su ancho real: `/img/foto-480.webp 480w, /img/foto-960.webp 960w`;
    - `fuentes`, una lista con un elemento por cada formato anterior al último,
      en el orden de `formatos`, cada uno con su `tipo` (`image/avif`) y su
      `srcset`. Con un solo formato está vacía.

  La plantilla escribe el HTML y decide el `sizes`, que el motor no sabe:

      {% set f = variantes('img/foto') %}
      <picture>
        {% for fuente in f.fuentes %}<source type="{{ fuente.tipo }}" srcset="{{ fuente.srcset }}" sizes="100vw">{% endfor %}
        <img src="{{ f.src }}" srcset="{{ f.srcset }}" sizes="100vw" width="{{ f.ancho }}" height="{{ f.alto }}" alt="">
      </picture>

  Avisa y da `null` si el sitio no declara `imagenes`, si la ruta sale de
  `publico/` o si no hay ninguna variante de la imagen. Avisa de cada fichero
  de variante que falta, y sigue con los que hay; un formato sin ninguna
  variante no entra. De un SVG no da nada, sin aviso.
- `activo(url)` dice si la página actual es esa URL o está dentro de ella:
  `activo('/blog/')` es cierto en `/blog/` y en `/blog/un-articulo/`. `/` solo
  es activo en la portada, y la raíz de cada idioma (`/eu/`), en la suya
  (§15.8). Para comparar exacto, `pagina.url == url`.
- `version(ruta)` da la dirección de un fichero de `publico/` con su huella:
  `{{ version('css/estilo.css') }}` da `/css/estilo.css?v=3fa9c1d2ab`. La
  huella son los 10 primeros caracteres hexadecimales del SHA-256 del fichero,
  así que cambia cuando cambia su contenido y no antes. Es para enlazar los
  CSS, los JS, las tipografías y las imágenes que no pasan por `css()` ni
  `css_fichero()` (§9.1): el navegador puede guardarlos mucho tiempo sin que,
  tras un despliegue, nadie vea una versión vieja. Admite la ruta con o sin
  `/` delante. Si el fichero no existe o la ruta sale de `publico/`, se avisa
  y da la dirección sin huella. Quien la usa depende del fichero: si cambia,
  se rehace la página.
- `slug` (§5.2).
- `fecha(formato)` escribe una fecha con las mismas letras que el filtro
  `date` de Twig, pero con los nombres de meses y días en el idioma de la
  página (§15.9). En castellano, `pagina.fecha|fecha('d F Y')` da
  `18 marzo 2025`, y sin formato, `18 de marzo de 2025`. Una letra que tiene
  que salir tal cual se escapa
  con dos barras dentro de la plantilla, igual que con `date`:
  `fecha('j \\d\\e F')` da `18 de marzo`.
- `markdown` convierte a HTML un texto en Markdown, para los campos del front
  matter o de `datos/` que llevan formato: un titular con una palabra en
  cursiva, un resumen con una negrita. Con `titular: "Una cocina que *viaja*"`,
  `{{ pagina.titular|markdown }}` da `Una cocina que <em>viaja</em>`. Sin
  argumento es de línea, el Markdown del §8 sin bloques: no hay `<p>`, ni
  encabezados, listas, citas, tablas ni figuras, y los atajos (§8.1) no se
  reconocen, porque solo viven en el cuerpo. Con `markdown('bloque')` es de
  bloque: lo que haya en el campo se convierte como el cuerpo, con párrafos,
  listas, citas y encabezados, para un texto con varios párrafos dentro de un
  bloque que la plantilla pone (`{{ pagina.intro|markdown('bloque') }}`). El
  modo de bloque sigue sin atajos, y tampoco convierte las tablas ni las notas
  al pie, ni da `id` a los encabezados (§2, §8), ni hace figuras de las
  imágenes: todo eso es del cuerpo. `markdown('linea')` es lo mismo que sin
  argumento; cualquier otro modo avisa y se usa el de línea. En los dos modos
  valen el énfasis, la negrita, el tachado, el código, los
  enlaces (los externos se tratan como en el cuerpo, con su `target` y su
  `rel`, y el aviso de `enlacesExternos`), los enlaces automáticos y el HTML
  escrito a mano (`<mark>`, `<br>`). Un salto de línea con una barra invertida
  o con dos espacios al final de la línea sale como `<br>`, seguido del salto
  de línea del texto, como en el cuerpo; uno suelto, como un salto en el HTML,
  que el navegador lee como un espacio. El resultado es HTML
  ya hecho: Twig no lo escapa de nuevo, y lo escrito a mano pasa tal cual, como
  en el cuerpo. Un valor vacío o que falta no da nada, sin aviso; un número se
  toma como texto; una lista, un mapa o un booleano avisan y no dan nada. En un
  texto sin formato, la plantilla sigue usando `{{ campo }}`, que sí escapa.

Todas las fechas se escriben en la zona horaria del sitio (§2), también las
del filtro `date` de Twig, que por sí solo usaría la de la máquina.

Las plantillas pueden usar `random()` de Twig para elegir al azar, y la fecha
del momento (`'now'|date('Y')`, para el año del pie). Con la misma versión del
motor, son las dos únicas formas de que el mismo proyecto dé otro HTML en las
páginas que salen (§12, regla 3), y tienen un precio: con
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
  lleva también su ancho y su alto, como el atajo, y, si el sitio declara
  `imagenes` (§2) y la imagen tiene variantes, su `srcset` (§8.2). Un sitio que quiera otro
  marcado para sus figuras (una clase, un `loading="lazy"`) lo da con su
  propia plantilla `imagen`.
- **Enlaces externos.** Un enlace escrito en Markdown a otro dominio recibe
  `target="_blank"` y `rel="noopener"`. El dominio del sitio es el de `url`
  en `sitio.yml`, con y sin `www.`: un enlace es a otro dominio si su dirección
  empieza por `http://`, `https://` u otro esquema con dominio, o por `//`, y
  ese dominio no es el del sitio (un subdominio como `blog.ejemplo.com` sí es
  otro). Una ruta, un ancla, un `mailto:` o un `tel:` no lo son, y un enlace con
  un esquema inseguro (`javascript:`) sale sin dirección. El HTML escrito a mano
  y los enlaces de las plantillas no se tocan. La sección `enlacesExternos` de `sitio.yml`
  (§2) añade `noreferrer` y un aviso para los lectores de pantalla. Una
  imagen enlazada a otro dominio, sea una figura o el atajo `imagen` con
  `enlace` (§8.2), lleva también `target` y `rel`, aunque no el aviso, que un
  enlace que solo envuelve una imagen no tiene (decisión 131).

Y un añadido opcional, que solo existe si el sitio lo pide con la sección
`encabezados` de `sitio.yml` (§2):

- **Encabezados con `id`.** Los encabezados del Markdown de los niveles que se
  indiquen reciben un `id`: el `slug` (§5.2) de su texto plano, sin enlaces ni
  énfasis. Si ya hay otro igual en la página, se le añade `-2`, `-3`… hasta el
  primer número libre; y si el `slug` sale vacío, el `id` es `seccion`. Con
  `enlace`, el encabezado termina en un enlace permanente, pegado a su texto:
  `<h2 id="un-apartado">Un apartado<a class="ancla" href="#un-apartado"
  aria-label="Enlace a «Un apartado»">¶</a></h2>`. Solo cuentan los
  encabezados que escribe el Markdown del cuerpo: no los del HTML escrito a
  mano, ni los de dentro de un atajo que envuelve contenido (§8.1), ni los que
  pone una plantilla, que decide los suyos. Las plantillas leen la lista en
  `pagina.encabezados` (§7.2).

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
- El nombre de un atajo empieza por una letra minúscula y lleva minúsculas,
  números y guiones; el de un atributo empieza por una letra y lleva letras y
  números, sin guiones ni guiones bajos (`pie`, `siFalta`). El valor termina en
  la misma comilla con que empieza y no admite escape: uno que lleve comillas
  dobles y simples a la vez no se puede escribir.
- Un atajo simple puede ir en medio del texto. Si va solo en su párrafo y lo
  que produce es un bloque (una figura, un `div`, un vídeo…), sustituye al
  párrafo entero, porque un bloque no cabe dentro de un `<p>`. Si produce
  texto, como `dato`, se queda dentro del párrafo. Es un bloque lo que empieza
  por una de estas etiquetas: `address`, `article`, `aside`, `audio`,
  `blockquote`, `details`, `div`, `dl`, `fieldset`, `figure`, `footer`, `form`,
  `h1` a `h6`, `header`, `hr`, `iframe`, `nav`, `ol`, `p`, `picture`, `pre`,
  `section`, `table`, `ul` y `video`. Con cualquier otra (`main`, `dialog`…) el
  atajo se queda dentro del `<p>`.
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
nombres de atributo: se avisa y se ignoran. Y un atributo con el nombre de una
variable que el motor calcula para ese atajo (§8.2: `src`, `alt`, `ancho`,
`href`, `peso`, `valor`…) no llega a la plantilla: gana la del motor.

### 8.2 Atajos incluidos en el motor

Un sitio puede sustituir cualquiera de ellos con su propia plantilla del mismo
nombre en `parciales/atajos/`, que recibe las mismas variables.

- **`imagen`**: `[imagen fichero="img/escaparate.jpg" alt="…" pie="…"]`, con
  `fichero` dentro de `publico/`. Da `<figure>` con la imagen y, si hay pie,
  `<figcaption>`. Sin `pie`, el pie es el `alt`; con `pie=""`, no hay pie. El
  `pie` del atajo es texto, que se escapa; el de una figura del Markdown (§8) se
  convierte como Markdown. `enlace` hace la imagen enlazada. La imagen lleva `width` y `height` cuando
  se pueden leer del fichero (§7.3, `dimensiones()`), para que el navegador
  le reserve el sitio antes de cargarla. La plantilla recibe `src`, `alt`,
  `pie` (ya en HTML), `enlace`, `externo` (si el `enlace` lleva a otro
  dominio, como los enlaces del Markdown de §8; sin `enlace`, falso), `rel`
  (el de ese enlace, `noopener` y, si el sitio lo pide, `noreferrer`; solo
  si `externo`), `ancho` y `alto` (`null` si no se pueden leer). La plantilla
  de Ehundu pone `target="_blank"` y `rel` en un enlace externo; la del sitio
  que no use `externo` ni `rel` queda como la escribió. Se avisa si falta `alt` o si el fichero no existe; sin `fichero` no
  se inserta nada.

  Si el sitio declara `imagenes` (§2), el atajo busca las variantes de la
  imagen (`variantes()`, §7.3) y, si las hay, la plantilla recibe además
  `srcset`, `sizes` y `fuentes`, y `src`, `ancho` y `alto` son los de la variante
  más ancha del último formato; la plantilla del motor escribe `<picture>` si
  hay `fuentes` y un `<img srcset>` si no. `sizes` es el del atributo
  `sizes="…"` del atajo o, si no lo lleva, el de la sección. Una imagen sin
  ninguna variante sale como sin `imagenes` y sin aviso: no todas se procesan
  (un logotipo, una imagen pequeña). Si tiene unas variantes y le faltan otras,
  se avisa de cada fichero que falta. Una imagen con variantes no necesita el
  fichero que da nombre a su tronco: puede que en `publico/` solo estén ellas, y
  entonces no se avisa de que no existe. Las imágenes que no son de `publico/`,
  o que son SVG, no se tocan.
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

Lo que escriben las plantillas de Ehundu va en castellano: el título `Vídeo`
de `video`, el peso de `archivo` con coma decimal (`1,2 MB`), y `sí` o `no` en
`dato` si el valor es un booleano. `peso` llega a la plantilla ya como texto, así
que una plantilla propia no puede darle otro formato; un sitio en otro idioma
sustituye el atajo con la suya (decisión 136).

---

## 9. Ficheros, CSS y JavaScript

`publico/` se copia tal cual a la raíz de la salida, incluidos los ficheros
que empiezan por punto, como un `.htaccess`, salvo los que dejan el sistema o
el control de versiones: `.DS_Store`, `.gitkeep`, `Thumbs.db`, `desktop.ini` y
cualquier carpeta `.git`, que no se copian y no avisan (decisión 138). La lista
es corta y cerrada. En `contenido/` pasa igual con `Thumbs.db` y `desktop.ini`;
lo demás que empieza por punto ya se ignora (§4). Sin pipeline: el CSS y el JS
llegan ya escritos y, si hace falta, minificados fuera del motor.

**Lo que no se copia.** Ni de `publico/` ni de `contenido/` se copia un
fichero PHP: uno cuyo nombre tenga como extensión `.php`, de `.php3` a
`.php8`, `.pht`, `.phtml` o `.phar`, en mayúsculas o en minúsculas, y aunque no
sea la última (`foto.php.jpg`: un servidor con `AddHandler` la ejecuta igual).
El build se detiene con un error que nombra el fichero. Un sitio de Ehundu es
estático y ese fichero no lo ha escrito el motor: lo normal es que sea un
resto de otro gestor de contenidos, o algo que alguien dejó en una carpeta de
imágenes, y publicarlo lo pondría a ejecutarse en el servidor. Hoy no hay
forma de permitirlo.

Las imágenes no se procesan durante el build. Si hacen falta varios tamaños,
los genera otra herramienta (por ejemplo, un editor al subir la imagen, §11.6)
y deja los ficheros en `publico/`, con el nombre que dice la sección `imagenes`
de `sitio.yml` (§2). El motor solo los lee: `variantes()` (§7.3), el atajo
`imagen` y las figuras del Markdown (§8) escriben el `srcset` con lo que
encuentran.

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
una página declara CSS y ninguna plantilla escribe `{{ css() }}` (ni
`{{ css_fichero() }}`, más abajo), se avisa.

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

**Como fichero, en lugar de incrustados.** Incrustar es lo mejor con poco CSS:
la página llega con todo y no hace falta otra petición. Con mucho, repite el
mismo CSS en cada página y el navegador no puede guardarlo. `css_fichero()` y
`js_fichero()` hacen lo mismo que `css()` y `js()` con lo que la página ha
declarado (los mismos ficheros, en el mismo orden, una vez cada uno), pero en
lugar de su contenido escriben la etiqueta que enlaza un fichero que contiene
ese contenido:

- `{{ css_fichero() }}` escribe `<link rel="stylesheet" href="/css/3fa9c1d2ab.css">`.
- `{{ js_fichero() }}` escribe `<script src="/js/7c01e4b9d3.js" defer></script>`.

Las dos funciones solo marcan el sitio, como `css()` y `js()` sin argumentos:
los ficheros se declaran con `css('…')` y `js('…')`, y si se los das a
`css_fichero()` se avisa y se ignoran.

El fichero lo escribe el motor en `salida/css/` o `salida/js/`, y su nombre es
la huella del contenido: los 10 primeros caracteres hexadecimales de su SHA-256.
Las páginas que declaran lo mismo comparten el mismo fichero, y si cambia
cualquiera de los ficheros de `publico/` que lo forman, cambia el nombre: ni el
navegador ni una caché intermedia pueden servir uno viejo. Los ficheros que
dejan de usarse desaparecen de `salida/` como cualquier otro (§10). Si una
página no declara nada, o no se puede leer nada de lo que declara, no se
escribe ninguna etiqueta. Si dos cosas van al mismo fichero, por ejemplo un
`publico/css/3fa9c1d2ab.css`, el build se detiene (§10).

Como el nombre cambia con el contenido, el fichero se puede servir con una
caducidad de un año. En Apache, con un `.htaccess` en `publico/`:

    <FilesMatch "^[0-9a-f]{10}\.(css|js)$">
      Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

Una plantilla puede usar la forma que le convenga: `css()` para un CSS corto
que quiere en la propia página, `css_fichero()` para uno largo. Lo declarado
es lo mismo en las dos. Dentro de un CSS que se enlaza, las direcciones de
`url(…)` tienen que empezar por `/`, como ya pasa en uno incrustado: las
relativas se resolverían desde donde esté el fichero o la página, y un
paquete reúne ficheros de carpetas distintas. En la plantilla de un PDF
(§10.3) solo vale `css()`.

    {# plantillas/base.twig #}
    <head>
      {{ css('css/base.css') }}
      {{ css_fichero() }}
    </head>
    <body>
      {% block cuerpo %}{% endblock %}
      {{ js_fichero() }}
    </body>

---

## 10. Salida

En `salida/` van las páginas, lo que se copia de `publico/` y de
`contenido/`, el PDF de las páginas que lo piden (§10.3) y los ficheros que
genera el motor: `sitemap.xml`; `feed.xml`, si el sitio lo pide; y, si una
página los declara y usa `css_fichero()` o `js_fichero()`, los paquetes
`css/<huella>.css` y `js/<huella>.js` (§9.1). Nada más: los añadidos van como
ficheros normales dentro de `contenido/` o de `publico/`. El manifiesto del
despliegue (`.ehundu.json`) se escribe en el destino, no en `salida/`: lleva su
propia versión (`"ehundu": 1`), y un Ehundu 1.x lee el que dejó cualquier
versión anterior, también el de un despliegue de la 0.x. Lo que hay en
`.ehundu/` (`lote.json`, `memoria.bin`) no se promete: es del motor y se puede
tirar (§1). Una página con `url: /404.html` se escribe como `404.html`, igual
que cualquier otra.

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
enlaces y las imágenes que empiezan por `/` pasados a direcciones completas
(también las de cada `srcset`, §2), porque un lector de feeds no sabe de qué
sitio vienen. Una página de la
colección sin fecha no entra, y se avisa: Atom exige una fecha en cada
entrada.

Un lector de feeds identifica cada entrada por su `<id>`, que es la dirección
completa de la entrada, y el del feed es la dirección de la raíz de su idioma;
no cambian con el tiempo. El autor, `<author><name>`, es el `nombre` del sitio.
El feed lleva además su propio `<link rel="self">` y el `xml:lang` de su
idioma. No hay una clave para otro autor; añadirla más adelante es crecer
(decisión 138).

La fecha del feed es la de su entrada más reciente. Un feed sin entradas
lleva siempre el 1 de enero de 1970, en la zona horaria del sitio: Atom exige
una fecha también ahí, y con una fija el feed no cambia de una compilación a
otra, ni se vuelve a escribir ni a subir mientras siga vacío.

En un sitio en varios idiomas hay un feed por idioma (§15.10).

### 10.3 PDF

Una página con `pdf: sí` sale además como PDF. Es una salida más de esa misma
página, con su propia plantilla, y no una conversión de su HTML: un PDF se
maqueta distinto de una web, y las dos comparten los datos, no el marcado. Se
pide página a página, y no para todo el sitio, porque cada PDF cuesta unas
décimas de segundo.

- **La plantilla** es la de la página con `.pdf` antes de la extensión: con
  `plantilla: menu`, `plantillas/menu.pdf.twig`. Recibe las mismas variables
  que la de la página (`sitio`, `datos` y `pagina`, §7.2) y escribe un
  documento HTML completo. Si no existe, el build se detiene, como con la de la
  página (§7.1).
- **La dirección** es la de la página con la barra final cambiada por `.pdf`:
  `/menu-del-dia/` da `/menu-del-dia.pdf`, y la portada, `/index.pdf`. Si la
  URL acaba en `.html`, esa extensión pasa a `.pdf`. Una página cuya URL no
  acaba en `/` ni en `.html`, o que es un fragmento (`url: false`), no puede
  tener PDF: se avisa y se ignora el campo. Si el fichero coincide con otro de
  la salida, el build se detiene (§10). Las plantillas lo enlazan con
  `pagina.pdf`, que es su dirección o queda vacío; también
  `pagina.traducciones.eu.pdf`.
- **Cada idioma** da su PDF, en su idioma, con la plantilla y los datos de su
  página. En un sitio en varios idiomas, `pdf: sí` se escribe una vez, en el
  `.yml` común de la página (§15.7).
- **Lo que admite.** CSS 2.1 con algo de CSS3, y nada de `flex` ni de rejilla.
  La hoja es A4 salvo que el CSS diga otra cosa con `@page { size: … }`. El CSS
  se declara con `css('…')` y `{{ css() }}` en la plantilla del PDF y en sus
  parciales, como en una página (§9.1), pero sin `css_fichero()`; el campo `css`
  del front matter no cuenta, porque es el de la web. Las direcciones que empiezan por `/` se leen
  de `publico/`; nada remoto se carga, y lo que dompdf no entiende (una
  propiedad de CSS, una imagen que no encuentra) sale como aviso, con la
  plantilla como fichero. Las imágenes pueden ser SVG, JPEG o PNG: los SVG no necesitan ninguna extensión de PHP, y las PNG con
  transparencia, GD. Las tipografías son las tres básicas de un PDF,
  `sans-serif` (Helvetica), `serif` (Times) y `monospace` (Courier), que cubren
  el juego de caracteres de Windows-1252 (castellano, euskera, inglés y
  alemán); no se pueden usar otras.
- **Reproducible.** El PDF lleva como fecha de creación y de modificación la
  `fecha` de la página, a medianoche en la zona horaria del sitio, o el 1 de
  enero de 1970 si no la tiene (como el feed vacío, §10.2); su título es el
  `titulo` de la página, y su identificador sale de su contenido. La misma
  página, con la misma versión de la biblioteca, da los mismos bytes: si no,
  cada compilación cambiaría todos los PDF y el despliegue volvería a subirlos.
  Vale lo de §7.3 para `random()` y la fecha del momento.
- **Si falla.** Si un PDF no se puede generar, el build se detiene con el error
  y la plantilla, por lo mismo que con un front matter roto (§4): seguir sin él
  haría que el despliegue borrara el que estaba publicado.
- **La biblioteca** es dompdf, que es PHP puro, sin binarios, y necesita las
  extensiones `dom` y `mbstring`. Es una dependencia opcional: un sitio sin
  `pdf: sí` no la necesita, y con `pdf: sí` y sin ella el build se detiene y
  dice cómo instalarla. El `ehundu.phar` no la trae: para generar PDF hay que
  usar el motor con Composer. Su caché de tipografías va a la carpeta temporal del
  sistema, no junto a su código: el motor no escribe en `vendor/` ni en el
  proyecto fuera de `salida/`.

En la previsualización, un PDF se rehace cuando cambia algo de lo que usó su
plantilla o de lo que lee de `publico/`, igual que su página
(`compilacion.md`). El sitemap no lleva PDF
(§10.1).

### 10.4 Compilación por lotes (no se promete)

El motor trae una compilación por lotes (`CompiladorPorLotes`): reparte una
compilación **completa** en tareas (cada página, cada fichero que se copia, el
sitemap con el feed y la limpieza final) y las hace en varias llamadas, cada una
con un presupuesto de tiempo, sin recordar nada del sitio entre ellas. Por dónde
va lo guarda en `.ehundu/lote.json` (§1). Gasta poca memoria a cambio de volver
a leer el proyecto y hacer todas las páginas cada vez. Cómo funciona se cuenta
en `compilacion.md`.

**No forma parte de lo que promete la 1.0** (decisión 128): `CompiladorPorLotes`
y `CompiladorPorLotesEnProceso` son `@internal` y pueden cambiar o desaparecer
en cualquier versión. Lo que se promete para repartir una compilación en
llamadas con un presupuesto de tiempo es `avanzar` de la compilación
incremental (§10.5). De este apartado, del formato solo vale que `.ehundu/` es
del motor (§1) y que `lote.json` es un nombre que el motor puede usar en él.

El despliegue (`despliegue.md`) se reparte con un presupuesto de tiempo y eso
sí se promete: ver la decisión 125.

### 10.5 Compilación incremental entre procesos

La compilación incremental (`compilacion.md`) recuerda la última compilación y
rehace solo lo que un cambio afecta. La previsualización la tiene dentro de su
proceso; `CompiladorIncrementalEnProceso` ([`biblioteca.md`](biblioteca.md)) la
ofrece a quien incruste el motor, guardando lo que recuerda en
`.ehundu/memoria.bin` (§1) para que la aproveche una compilación de otro
proceso: guardar un cambio en un sitio de mil páginas rehace unas pocas. La
consola no la necesita y no la ofrece.

- **El mismo resultado.** Como toda compilación incremental: los mismos
  ficheros y los mismos avisos que una compilación completa del mismo
  proyecto. Si no se puede asegurar, se rehace todo.
- **Qué se guarda.** Lo que la incremental ya recuerda, tal cual: lo leído del
  proyecto, las huellas de `plantillas/`, `parciales/` y `publico/`, y de cada
  página su HTML (y su PDF), lo que usó y los cuerpos convertidos. Pesa del
  orden de `salida/` más lo leído y se carga en una fracción de segundo (las
  medidas, en `compilacion.md`). No lleva nada que no esté en el proyecto.
- **Cuándo no vale.** Si falta, está dañada o a medio escribir, es de otra
  versión de la memoria o del motor, o es de otro proyecto (otra ruta), se
  ignora y se hace una compilación completa. Nunca es un error. Cambiar de
  versión del motor, de alguna de sus dependencias o de PHP, o tocar el código
  del motor, hace que la primera compilación sea completa: lo que puede
  cambiar el HTML que sale no se mezcla con lo que se recordó con otra cosa.
- **Cuándo se guarda.** Solo cuando una llamada sale bien. Un error del
  proyecto deja la memoria y `salida/` como estaban, y el siguiente cambio
  vuelve a aprovecharla.
- **Por presupuesto.** `avanzar` hace lo mismo con un presupuesto de segundos:
  rehace páginas hasta agotarlo, siempre al menos una (la que ha empezado se
  termina, así que una llamada puede tardar más que el presupuesto), guarda lo
  hecho y devuelve un `Lote` que dice si está completo con `completo()`. La
  llamada siguiente sigue: lo que ya está hecho se aprovecha por la misma regla
  que cualquier otro cambio, y a una memoria con la mitad de las páginas solo le
  faltan páginas, así que no hace falta ningún estado aparte, y si el proyecto
  cambia entre dos llamadas solo se rehace lo que el cambio afecta, no todo.
  Cada página se escribe al hacerla, así que hasta que el `Lote` está completo
  `salida/` mezcla páginas nuevas y viejas: es lo contrario de la garantía de la
  compilación completa, que ante un error deja `salida/` como estaba, y por eso
  nada debe publicarse mientras `completo()` sea falso. Completo, `salida/` y
  los avisos son los de una compilación completa, también si se ha cortado y
  retomado tantas veces como se quiera.
- **Una llamada a la vez.** Una llamada que llega mientras otra está avanzando
  no espera ni estropea nada: devuelve el estado, con `ocupado()`.
- **El primer arranque** (sin memoria) es una compilación completa: con
  `avanzar` se reparte en las llamadas que haga falta.

---

## 11. `esquema.json`

Lo usan los editores; el motor lo ignora. Declara los tipos de contenido del
sitio, dónde están sus páginas, qué campos llevan y quién puede editarlos.

Lleva, si se quiere, `"formato": 1`: la versión de este formato, la misma
clave y las mismas reglas que la de `sitio.yml` (§2). Sin ella, es el 1. Un
editor que no entienda la versión que declara un esquema no lo toca y lo dice.
El motor no la comprueba, porque no lee el esquema.

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

Cuando un tipo tiene más de una carpeta, el editor agrupa sus páginas por
carpeta y pregunta en cuál crear una nueva. Cada carpeta se rotula con el
título de la página que se llama como ella a su lado
(`contenido/libros/novela.md` para `contenido/libros/novela/`) o, si no la
hay, con el de su `index.md`; si tampoco, con su nombre. Un tipo con una sola
carpeta y sin ninguna de esas páginas se rotula con el plural del tipo.
El editor no crea
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
usan `orden('orden, titulo')` y las colecciones. El editor no lo enseña en el
formulario de la página: lo cambia el propio listado del tipo, arrastrando las
páginas, y escribe el número de cada una en el mismo sitio en que estaría a
mano, que en un sitio en varios idiomas es el `.yml` común de la página
(§15.7). Los números van de 10 en 10, para que al mover una página basten un
número entre el de sus vecinas y un solo fichero cambiado; las páginas sin
`orden` van al final, como en las colecciones (§6.1). Un tipo que se ordena
por fecha, como un blog, no declara `orden` y no se arrastra.

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

**El menú lateral.** El editor agrupa lo que se edita en secciones. Cada tipo
lleva la suya, con el plural del tipo por nombre, y los ficheros de datos
(§11.5) van en «Ajustes». Cuatro claves, todas opcionales, lo cambian:

- `"grupo": "Menús"`, en un tipo o en un fichero de datos, dice en qué
  sección va. Los que llevan el mismo `grupo` comparten sección, y en ella
  salen primero las carpetas de los tipos y después los ficheros de datos: así
  un tipo de menús y otro de servicios van juntos, aunque cada uno tenga su
  formulario, y un aviso puede ir en una sección «Avisos» con otros.
- `"rotulo": "Contenidos"`, en un tipo con una sola carpeta (sin `/*`), es
  cómo se llama esa carpeta en el menú lateral, en lugar del título de la
  página que la rotula (arriba, §11). Sirve, por ejemplo, para la carpeta
  `contenido`, cuyo `index.md` es la portada y no un título de sección.
- `"entrada": "Páginas"`, en un tipo con `unico`, es cómo se llama en el menú
  lateral la entrada que comparten los tipos fijos de una misma sección que
  llevan esa misma `entrada`: una sola, con un listado que reúne las páginas de
  todos, en el orden de los tipos del esquema, y cuyo número es el de todas
  ellas. Cada fila se llama como su tipo (`titulo`: «Portada», «Contacto») y
  abre su propio formulario, y el editor no ofrece añadir ni ordenar desde ese
  listado, porque son páginas fijas. Sirve para un sitio de aterrizaje en el
  que cada página tiene sus campos, y por eso es un tipo, pero que no puede
  llevar una entrada del menú por cada una. Sin `unico`, o junto a `rotulo`
  (que ya da nombre a la carpeta), es un error del esquema.
- `"excluir": ["404", "aviso-legal"]`, en un tipo, son páginas de sus
  carpetas que el editor no enseña, no cuenta ni abre, aunque estén en ellas:
  su nombre, sin carpeta, idioma ni extensión. Sirve para las páginas que solo
  toca quien desarrolla el sitio.

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
dentro no se recuerdan, aunque la haya. El editor lo usa así:

- Al escribir en `clave`, sugiere los elementos recordados que coinciden, sin
  distinguir mayúsculas ni tildes y, si la clave es traducible, en el idioma en
  que escribe quien edita.
- Al elegir uno, copia sus campos al elemento y lo dice, para que se revisen.
- Al guardar, añade los elementos nuevos y, si han cambiado los de uno
  recordado, pregunta si recordar el cambio.

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

Al subir una imagen, el editor:

- acepta JPEG, PNG y WebP, y rechaza lo demás diciéndolo; también puede
  rechazar una imagen que no pueda tratar con los recursos que tiene, y debe
  decirlo;
- la gira según la orientación que guarda la cámara y le quita esos datos y la
  ubicación, de modo que se vea igual y no publique dónde se hizo;
- la reduce a `ancho`, si hace falta;
- le da un nombre sin tildes ni espacios y la deja en `carpeta`, sin pisar una
  que ya esté: si el nombre existe, añade `-2`, `-3`, y así hasta que no choque,
  como con `fichero` (§11).

El formato y la calidad con que la guarda son cosa del editor, y el fichero
queda en `publico/` como cualquier otro cambio. Las dimensiones no se guardan
en el front matter: la plantilla las lee con `dimensiones()` (§7.3), que da
las de la imagen que esté entonces. El motor no lee estas claves, como el resto
del esquema, y no procesa imágenes (§9): lo que ve es un fichero de `publico/`.

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

El editor lo enseña como un calendario de meses, el actual y los doce
siguientes (trece en total, para poder marcar el mismo día de dentro de un
año), en el que se marca y se desmarca cada día desde hoy, en la zona horaria
del sitio. Los días anteriores a hoy se ven pero no se cambian. Lo que no se
puede cambiar o no se enseña, un día ya pasado o de más allá de esos trece
meses, se conserva tal cual al guardar, y un valor que no es un día se conserva
y se avisa, igual que un `id` que ya no está entre las opciones de una
`seleccion`. Lo que escribe es siempre la lista entera, ordenada y sin
repetidos. Dos cambios sobre el mismo calendario no chocan: se juntan día a día,
con lo que añade y lo que quita cada uno.

Un campo `fechas` no admite `requerido` (no marcar ningún día es una respuesta
tan buena como marcar uno) ni `traducible` (los días son los mismos en todos
los idiomas), y hoy solo vale como campo de primer nivel de un tipo o de
un fichero de datos, no dentro de una `lista` ni de un `grupo`. Lo que no
cumple esto es un error del esquema. El motor no lee el esquema y no cambia: lo
único que ve es una lista de fechas.

---

## 12. Reglas que no se rompen

1. Un editor no guarda nada que el motor no pueda leer desde la carpeta.
2. La orden de consola y cualquier programa que incruste el motor usan la
   misma API; la orden es una envoltura fina.
3. Un proyecto exportado en zip compila en cualquier máquina con PHP y da el
   mismo HTML con la misma versión del motor y de sus dependencias, salvo lo
   que una plantilla elija al azar con `random()` o saque de la fecha del
   momento (§7.3), y salvo qué páginas salen, que depende del momento en que se
   compila (`publicar` y `publicarEnSuFecha`, §4). Entre versiones del motor no
   se promete el mismo HTML byte a byte: Twig 3, CommonMark 2 y dompdf 3, que
   Composer deja actualizar dentro de su versión mayor, deciden partes del
   resultado (el escapado, las listas, las tablas y las notas al pie, los bytes
   de un PDF), y lo que cambie se anota en las notas de la versión.
4. El motor no ejecuta código propio del sitio (PHP que venga del proyecto), y
   no lo hará dentro de una versión mayor del formato (§2): es una promesa,
   como la de la regla 5.
5. Dentro de una versión mayor del formato (§2), el contrato solo crece: un
   campo, una función, un filtro, un tipo de campo del esquema o una opción
   nuevos no cambian lo que ya significaba un proyecto escrito. Quitar algo
   o cambiar su significado es una versión mayor. Lo que va a dejar de valer
   se avisa durante, al menos, una versión menor antes de quitarse. Tiene una
   excepción, la de los nombres: un campo reservado nuevo (§4) o un nombre
   nuevo que el motor escriba en `pagina` (§7.2) puede tapar un campo propio
   que un sitio ya tuviera con ese nombre. Por eso se anuncia en una versión
   menor, y el motor que lo añade avisa a los sitios que ya lo usan como
   propio.
6. La biblioteca PHP y la orden de consola prometen lo que dice
   [`biblioteca.md`](biblioteca.md), con la misma regla: lo que no está allí
   puede cambiar en cualquier versión.

---

## 13. Lo que Ehundu no hace (todavía)

Catálogo de componentes con parámetros y consultas, tokens de estilo por sitio,
generación de imágenes en varios tamaños, procesadores sobre el HTML ya
generado, pasos posteriores al build y búsqueda. El PDF de una página (§10.3) no
es un procesador sobre el HTML ya generado: es una salida más de la página,
con su propia plantilla, que no toca el HTML de nadie. Los `id` de los
encabezados (§8) tampoco lo son: se escriben al convertir el Markdown, y el
HTML ya convertido no se vuelve a recorrer. Y leer las variantes de ancho de
una imagen que otra herramienta ya generó (`variantes()`, §7.3) no es generar
imágenes: el motor no procesa ninguna, solo busca ficheros.

Que estén aquí no las promete ni las descarta: dentro de una versión mayor
pueden llegar si son aditivas (§12, regla 5), y solo lo que dice la regla 4
queda cerrado (decisión 132).

Ninguna de estas cosas debería obligar a cambiar lo de arriba cuando llegue.
Si al añadirlas hay que romper el contrato, el contrato estaba mal.

Por eso queda reservado ya el campo `componentes`, que será el del catálogo:
un sitio no debe usarlo como campo propio, y si lo hace el motor lo avisa
(decisión 138).

---

## 14. Decisiones

Las decisiones conservan las palabras con que se escribieron: «la v1» y «la v2»
en las anteriores a la 132 quieren decir lo que Ehundu hacía o dejaba para más
adelante entonces, no el formato 1 (decisión 132), y «hasta ahora» o «como
hasta ahora» se refiere a la versión en que se tomó cada una. Cuando una
decisión posterior cambia lo que dice una anterior, lo anota la posterior y,
si hace falta, la anterior lleva una nota entre paréntesis.

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
50. **Cerrada; en parte la sustituye la 126.** Lo que se recuerda de una
    compilación a otra vive en memoria y no se guarda en disco; son datos
    simples, que un programa que incruste el motor podrá guardar donde le
    convenga (`compilacion.md`). (Desde la 126, `CompiladorIncrementalEnProceso`
    lo guarda en `.ehundu/memoria.bin`; la previsualización sigue sin hacerlo.)
51. **Cerrada.** Compilar por lotes queda para más adelante; la compilación
    ya separa decidir qué se rehace de rehacerlo (`compilacion.md`). Entra en
    la 0.12 (decisiones 124 y 125).
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
    §12, regla 3).
64. **Cerrada.** Una página que genera HTML sin `titulo` avisa; un fragmento
    o un `robots.txt` no lo necesitan (§4).
65. **Cerrada.** Las páginas cuyo nombre o carpeta empieza por `_` se
    compilan como las demás (§4).
66. **Cerrada.** Los alias ingleses valen para el nombre del campo, no para su
    valor: `layout: post.njk` no es una plantilla de Ehundu, y el error dice
    cómo quitar la extensión. Valen también en `_datos.yml` (§4).
67. **Cerrada.** `donde` compara los textos exactos (§6.2).
68. **Cerrada.** `componentes` queda reservado para el catálogo de la v2 (§13).
    (Hoy se dice «el catálogo de componentes», sin versión: decisión 132.)
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
91. **Cerrada.** Una `lista` puede llevar dentro otra `lista` de campos
    simples, con dos niveles como máximo; un `grupo` sigue admitiendo solo
    campos simples. Amplía la 83 (§11, §11.1).
92. **Cerrada.** `traducible` en los campos `texto` y `parrafo`: el valor es un
    mapa por código de idioma, y elegir idioma y qué hacer si falta es cosa de
    la plantilla. En un sitio con idiomas, el editor guarda lo de cada
    traducción en su fichero y todo lo demás en el `.yml` común de la página;
    los campos reservados de texto no pueden ser traducibles (§11.2).
93. **Cerrada.** Nuevo tipo de campo `seleccion-multiple`, con `opciones` en un
    fichero de `datos/` o en el esquema. Guarda la lista de `id` elegidos; una
    lista vacía es una respuesta y un campo que falta no lo es, y `requerido`
    pide una respuesta (§11.3).
94. **Cerrada.** `recuerda` en una `lista`: un fichero de `datos/` con los campos
    simples de sus elementos, por una clave, que el editor completa al guardar
    y usa para sugerir. Se copia, no se enlaza, y el motor no lo lee (§11.4).
95. **Cerrada.** El esquema puede declarar ficheros de `datos/` editables, con
    la sección `datos`; un solo fichero por entrada, sin variantes por idioma
    (§11.5).
96. **Cerrada.** `pdf: sí` hace que una página salga también como PDF, con su
    plantilla `<plantilla>.pdf.twig`, en la dirección de la página con `.pdf`.
    Es una salida más de la página y no un procesador del HTML; se genera con
    dompdf, una dependencia opcional (§4, §10.3, §13).
97. **Cerrada.** El PDF es reproducible: lleva la fecha de la página (o el 1 de
    enero de 1970) y un identificador sacado de su contenido, y su caché va a
    la carpeta temporal del sistema. Si no se puede generar, el build se
    detiene (§10.3).
98. **Cerrada.** Los ficheros PHP no se copian: uno en `publico/` o en
    `contenido/` detiene el build, también si `.php` no es la última extensión
    (§4, §9).
99. **Cerrada.** Un tipo del esquema que declara `orden`, de tipo `numero`, se
    ordena a mano: el editor lo cambia arrastrando en el listado y no lo
    enseña en el formulario (§11). No cambia nada en el motor.
100. **Cerrada.** Un tipo del esquema con `"unico": true` es una página fija:
    el editor no deja añadir otra en su carpeta mientras haya una, ni quitar
    la que hay (§11). No cambia nada en el motor.
101. **Cerrada.** El menú lateral del editor se ordena en secciones: `grupo`
    en un tipo o en un fichero de datos, `rotulo` en un tipo de una sola
    carpeta y `excluir` en un tipo, todos opcionales (§11). No cambia nada en
    el motor.
102. **Cerrada.** Un tipo del esquema con `"borradores": true` deja que el
    editor pase sus páginas a borrador y las vuelva a publicar, en todos los
    idiomas a la vez, y solo deja quitar las que son borradores (§11). No
    cambia nada en el motor.
103. **Cerrada.** Un tipo del esquema con `"programar": true` deja que el
    editor fije la fecha de `publicar` de sus páginas, en todos los idiomas a
    la vez; `borrador` manda sobre `publicar` y con `unico` no se ofrece
    (§11). No cambia nada en el motor.
104. **Cerrada.** `pagina.markdown` da el Markdown de una página sin el front
    matter y sin convertir, también en las páginas de una colección. Es lo que
    necesitan ficheros como `llms-full.txt`, que reúnen el texto de varios
    artículos. No existe en las páginas `.twig` (§7.2).
105. **Cerrada.** Una página con `agrupar` es una plantilla que el motor repite
    por cada valor de un campo de las páginas de una colección: una página por
    etiqueta sin escribirla a mano, para vocabularios abiertos. Agrupa por
    `slug`, da `grupo` (la variante más usada) y `paginas`, no genera página
    para un grupo sin páginas publicadas y no es paginación, que sigue fuera de
    la v1. Es declarativa: no ejecuta código del sitio (§6.3). (La paginación
    entró después, en la 0.11: decisión 121.)
106. **Cerrada.** `publicarEnSuFecha: sí`, que se hereda por la cascada, hace
    que una página no se publique antes de su `fecha`, como si llevara
    `publicar` con ella. Por defecto una `fecha` futura sigue sin ocultar nada.
    Un `publicar` explícito manda sobre él, y `borrador` sobre los dos (§4).
107. **Cerrada.** El patrón de `url` tiene `{{ nombreDeFichero }}`, el nombre
    del fichero sin carpeta, extensión ni código de idioma, para publicar las
    páginas de una carpeta sin la carpeta en la URL con un solo `url` en su
    `_datos.yml`. No es un campo, y manda sobre uno que se llame igual; se
    llama así y no `archivo` porque `archivo` es un campo probable (§5.1).
108. **Cerrada.** La sección `enlacesExternos` de `sitio.yml` añade a los
    enlaces externos del Markdown `noreferrer` y un texto para lectores de
    pantalla (`aviso`, uno por idioma en un sitio con varios, con una `clase`
    que define el sitio). Sin ella, los enlaces salen como hasta ahora. No es
    una plantilla: el vocabulario sigue cerrado, y el HTML y los enlaces de
    las plantillas no se tocan (§2, §8).
109. **Cerrada.** La sección `encabezados` de `sitio.yml` da un `id` (el `slug`
    del texto, sin repetirse en la página) a los encabezados del Markdown de los
    niveles que se indiquen, añade al final de cada uno un enlace permanente si
    se pide (`enlace`, `clase`, `etiqueta`) y da a las plantillas
    `pagina.encabezados`, con `nivel`, `texto` e `id`, para construir un índice.
    Es opcional: sin la sección, el Markdown sale como hasta ahora. No es un
    procesador sobre el HTML generado: actúa al convertir el Markdown, solo
    sobre sus encabezados, y no toca los de un atajo, los del HTML escrito a
    mano ni los de las plantillas (§2, §7.2, §8).
110. **Cerrada.** Al ordenar textos (`orden`, §6.2), los signos de apertura `¿` y
    `¡` no cuentan: el título «¿Cómo llegar?» se ordena como «Cómo llegar?», no
    como un texto que empieza por un signo que va antes que cualquier letra.
    En español esos signos solo abren una pregunta o una exclamación, y el
    diccionario no los tiene en cuenta. El resto de la puntuación sigue contando
    como hasta ahora. No cambia ningún texto, solo su posición en una lista.
111. **Cerrada.** `css_fichero()` y `js_fichero()` enlazan el CSS y el JS que ha
    declarado la página en lugar de incrustarlos: el motor escribe el
    contenido que habría incrustado en `css/<huella>.css` o `js/<huella>.js`
    de `salida/`, y las páginas que declaran lo mismo comparten el fichero. Lo
    elige la plantilla, no `sitio.yml`, como todo lo que decide qué lleva una
    página; `css()` y `js()` no cambian. El nombre lleva la huella del
    contenido, para que se pueda guardar un año sin riesgo de servir uno
    viejo. No minifica ni procesa nada: el contenido es el mismo que habría
    incrustado. No hay un fichero por cada declarado (con `<link>` por
    fichero se guardaría el común entre páginas, a costa de más peticiones):
    un paquete por combinación repite el CSS común en cada una, pero son pocos
    kilobytes comprimidos (§9.1).
112. **Cerrada.** `version(ruta)` da la dirección de un fichero de `publico/`
    con `?v=` y la huella de su contenido, para los ficheros que se enlazan sin
    pasar por `css()` (§7.3, §9.1). Se llama `version` y no `activo`, que ya es
    el del menú; y lleva la huella en la consulta y no en el nombre porque
    así funciona en cualquier alojamiento, sin reglas de reescritura.
113. **Cerrada.** El filtro `markdown` convierte a HTML un texto en Markdown de
    línea, para los campos con formato (§7.3, §8, §11). El esquema ya ofrecía
    el tipo `markdown` para cualquier campo, pero solo el cuerpo se podía
    convertir: un titular con una palabra en cursiva, o un resumen con una
    negrita, no tenía forma de llegar a la plantilla como HTML. Por defecto es
    de línea porque la plantilla ya pone el contenedor (`<h1>`, `<p>`, `<li>`);
    el modo de bloque es la decisión 114. No reconoce los atajos, para que el
    vocabulario cerrado siga viviendo en un solo sitio y para que un campo no
    dependa de plantillas. Admite HTML escrito a mano, como el cuerpo, porque
    quien lo escribe es la misma persona. No es un procesador sobre el HTML
    generado: actúa sobre el texto de un campo, antes de que llegue a la
    plantilla, y no cambia ningún sitio que no lo use.
114. **Cerrada.** El filtro `markdown` admite un modo de bloque,
    `markdown('bloque')` (§7.3). La 113 lo dejó fuera pensando que un texto con
    párrafos era el cuerpo de una página, pero una página de aterrizaje se
    compone de bloques (un manifiesto, un apartado de servicio) que llevan
    varios párrafos cada uno, y el cuerpo es uno solo y va entero en un sitio
    de la plantilla. Se descartaron dos salidas sin tocar el motor: una lista
    de párrafos en el front matter, que obliga a quien edita a escribir cada
    párrafo en su caja y a renunciar a encabezados y listas; y una ficha por
    bloque, con su propio cuerpo, que reparte una página en varias. Es un
    argumento del mismo filtro y no un filtro aparte porque lo único que cambia
    es si hay bloques, y el modo por defecto, el de línea, no cambia. El modo de
    bloque deja fuera lo que depende de ser el cuerpo de una página: los
    atajos, las figuras, las tablas, las notas al pie y los `id` de los
    encabezados.
115. **Cerrada.** Los tipos fijos (`unico`) de una misma sección del menú
    lateral pueden compartir una entrada con la clave `entrada` (§11). Salió de
    un sitio con muchas páginas distintas, cada una con sus campos
    y por tanto cada una su tipo: el menú tenía una entrada por página y el
    cliente, que solo quiere ver «Páginas», «Servicios» y «Diario», lo
    encontraba largo. Se descartaron dos salidas: un solo tipo con todos los
    campos de todas las páginas, que obligaría a un formulario enorme y a
    campos que no son de la página que se abre; y un submenú desplegable, que
    cambia solo cómo se pinta el menú pero deja el menú tan largo en cuanto se
    abre y es distinto de cómo funcionan las demás entradas (una entrada, un
    listado, una ficha). La entrada es solo para tipos fijos porque no hay un
    «añadir» que valga para varios tipos a la vez. No cambia nada en el motor.
116. **Cerrada.** Un campo `imagen` puede declarar `carpeta`, `ancho` y `alt`
    para que el editor deje elegir y subir imágenes en lugar de pedir la ruta
    escrita (§11, §11.6). Salió de un sitio cuyo titular edita casi todos los
    textos y lleva un blog: quien no escribe rutas no puede cambiar una foto, y
    las fotos del móvil pesan entre 3 y 10 MB. Se descartaron tres salidas: que
    el editor las reduzca por su cuenta, sin que el esquema diga cuánto, porque
    un fondo a todo el ancho y una foto de una ficha no piden lo mismo; guardarlas
    fuera de la carpeta del proyecto, que rompería la regla de que un proyecto
    exportado compila en cualquier máquina (§12); y generar varios tamaños, que
    sigue fuera de la v1 (§13): `ancho` es un solo máximo, no una familia de
    tamaños. `carpeta` es el interruptor: un sitio que ya usa `imagen` con la
    ruta escrita no cambia. `alt` se declara y no se deduce del nombre porque
    no es el mismo en todos los sitios (`imagenAlt` en una página, `alt` en una
    lista de fotos), y una convención por nombre habría obligado a renombrar
    campos que ya están publicados. Las dimensiones no se guardan en el front
    matter, porque se desharían en cuanto se cambiara la imagen:
    `dimensiones()` (decisión 62) las da. No cambia nada en el motor.
117. **Cerrada.** Nuevo tipo de campo `seleccion`, una sola elección entre unas
    `opciones` (un fichero de `datos/` o la lista del esquema, como en
    `seleccion-multiple`) que guarda el `id` de la elegida (§11, §11.3). Salió de
    un sitio con una lista agrupada a partir de un campo que escribe quien edita,
    y con unos filtros que comparan un texto exacto: con un texto libre, una
    errata o una mayúscula de más sacan la página del grupo o del filtro sin que
    nadie lo vea. Se descartaron
    `seleccion-multiple` con una sola casilla (el valor sería una lista, y la
    plantilla tendría que sacar el primero), un texto libre con la ayuda de qué
    valores valen (la errata se publica) y una comprobación en el motor (el
    esquema no es cosa suya, §11). Un `id` que ya no está entre las opciones se
    conserva y se avisa, como en la múltiple, para que quitar una opción de la
    lista no cambie en silencio las páginas que la usaban. No cambia nada en el
    motor.
118. **Cerrada.** Nuevo tipo de campo `fechas`, un conjunto de días que el editor
    enseña como un calendario de meses y que se guarda como una lista YAML de
    fechas, ordenada, sin repetidos y una por línea (§11, §11.7). Salió de un
    sitio con un calendario de ocupación cuyo titular solo cambia qué días está
    ocupado, dos o tres veces al mes: con una `lista` de grupos con un campo
    `fecha` serían unas ciento cincuenta filas al año, cada una con su «Añadir»
    y su «Quitar», y con una `lista-texto` quien edita escribiría las fechas a
    mano y la errata se publicaría. Una lista ordenada y sin repetidos hace que
    un cambio sea una línea en Git y que quien edita a mano las encuentre donde
    espera. Se descartaron los rangos (`desde` y `hasta`), que ya se escriben con
    una `lista` y que un local que se alquila por días no necesita, y una nota
    por día (de quién es la reserva), que llevaría datos de personas al
    repositorio del sitio. Sin `requerido`, porque una lista vacía es el estado
    normal; sin `traducible`, y hoy solo en el primer nivel de un tipo o de
    un fichero de datos, porque un calendario dentro de la fila de una lista no
    lo pide ningún sitio; se abrirá cuando alguno lo pida. No cambia nada en el
    motor.
119. **Cerrada.** Clave nueva de un fichero de datos editable,
    `publicarAlGuardar` (§11.5): al guardarlo, el editor publica el sitio en el
    acto. Salió del mismo sitio que la 118. Separar guardar de publicar es lo
    normal en un editor, para que nada salga sin que alguien lo decida, pero con
    los días ocupados de un calendario es una trampa: el titular marca el día, no
    pulsa «Publicar» y la web sigue enseñando libre un día ya cobrado. La clave
    se pone en el fichero que no puede esperar y no en todo el sitio, porque un
    aviso de cierre pide lo mismo y una ficha a medias, no. Publica el sitio tal
    como está guardado, no solo ese fichero, y si no puede, el editor lo dice y no
    da el cambio por publicado. Se descartaron una clave general del esquema
    (no distingue un dato urgente de una ficha a medias), un ajuste del editor
    por sitio fuera del proyecto (el zip del proyecto no se llevaría cómo se
    comporta) y un botón único «Guardar y publicar» para todo. No cambia nada en
    el motor.
120. **Cerrada.** El motor lee las variantes de ancho de una imagen, con la sección
    `imagenes` de `sitio.yml` (§2), la función `variantes()` (§7.3) y el
    `srcset` del atajo `imagen` y de las figuras del Markdown (§8.2). No genera
    ninguna: la generación sigue fuera del build (§9, §13). Salió de que varios
    sitios, cada uno por su lado, preparaban sus fotos en WebP en varios anchos
    con un programa propio y las leían con una plantilla de
    `<img srcset>` copiada de uno a otro, con los anchos escritos a mano, y de
    que otro sirve otros anchos en AVIF: las convenciones empezaban
    a divergir antes de congelar el formato. Se descartaron: generar las
    variantes al compilar, porque un proyecto en zip dejaría de dar el mismo
    HTML en cualquier máquina (§12, regla 3: sin la extensión GD no habría
    `srcset`, y con otra versión de ella los ficheros serían otros), exigiría
    una caché en disco que la compilación incremental no tiene y cargaría el
    proceso web con la decodificación de fotos de varios megapíxeles; descubrir
    los anchos buscando `foto-*.webp` en la carpeta, porque un `iphone-12.webp`
    o un `hoja-2.webp` pasaría por variante y la compilación incremental
    necesitaría un tipo de dependencia nuevo (el listado de una carpeta);
    escribir los anchos en cada llamada, porque repetiría la lista en cada
    plantilla y el atajo y las figuras no podrían usarla; y un solo formato,
    porque un sitio que ya sirve AVIF tendría que escribir su propia plantilla.
    La lista de anchos va en `sitio.yml` y no en `esquema.json` porque es del
    sitio y no del editor (§11): quien genere las variantes, sea un editor al
    subir la imagen o un programa aparte, y el motor leen la misma lista. El
    número del nombre es el ancho pedido y el del `srcset` el real del fichero,
    porque quien genera no amplía una foto estrecha. Una imagen sin ninguna
    variante sale en el atajo y las figuras como siempre y sin aviso, porque no
    todas se procesan; en `variantes()` avisa, porque quien la llama las pide.
    `sizes` no lo sabe el motor: lo da la plantilla, o la sección, o el
    atributo del atajo, y por defecto es `100vw`, que no pide menos imagen de
    la que hace falta. Los sitios sin la sección no cambian.
121. **Cerrada.** Una página con `paginar` parte una colección en trozos y se
    repite una vez por cada uno (§6.4): la primera está en su `url` y el motor
    genera las demás. Sale a la vez que la 120 por la misma razón, que la 1.0
    congela el formato y esto toca la URL, que es contrato. Hasta ahora, un sitio
    que venía de Eleventy con una lista paginada la sustituía por páginas
    sueltas escritas por el programa de migración, y esas páginas no se rehacen
    cuando se publica una entrada: la última página se queda corta y la
    siguiente nunca se crea. Es una clave nueva, `paginar`, y no una ampliación
    de `agrupar`: esta es lo contrario (reparte por valores, no por tamaño, y su
    `campo` no tiene sentido para el blog entero, §6.3). Se descartó dejarlo a
    mano con filtros (`desde(n)`), porque no resuelve que una entrada nueva cree
    su página. La URL de las páginas a partir de la segunda es, por defecto,
    la de la primera más `pagina/N/`, en español y sin chocar con un artículo
    llamado `2`; y se puede cambiar con `paginar.url`, con `{{ numero }}`
    (§5.1), porque quien migra no puede perder direcciones y cada generador
    numera a su manera (`/blog/2/`, `/page/2/`). Las páginas generadas no entran en
    colecciones ni en el sitemap, para que «Blog, página 2» no salga en un menú
    ni en un listado. Partir cada grupo de `agrupar` queda reservado: una página
    con las dos claves avisa y `paginar` se ignora. Ningún sitio lo usa hoy, y
    definirlo después no rompe nada, mientras que definirlo ahora fijaría
    algo que nadie ha probado. La paginación se calcula antes de cualquier
    plantilla y no ejecuta código del sitio (§12, regla 4).
122. **Cerrada.** Un proyecto declara para qué versión del formato está
    escrito con `formato: 1` en `sitio.yml` (§2), y un editor con `"formato": 1`
    en `esquema.json` (§11). Salió de preparar la 1.0: sin una declaración, una
    versión mayor futura no podría reconocer los proyectos antiguos y tendría
    que adivinarlos. Es solo la versión mayor, porque dentro de una el formato
    solo crece (§12, regla 5), y una menor (`"1.3"`) haría fallar en un motor
    más viejo a un proyecto que no usa nada de lo nuevo, y cada sitio tendría
    que subirla a mano. Sin la clave, el formato es el 1 y no se avisa: los
    proyectos escritos antes no cambian. Un valor que no es un entero mayor que
    cero se avisa y se toma como 1. Si es mayor que el que entiende el motor, se
    detiene el build con un error (`Formato`): la regla 10 del motor deja parar
    cuando es imprescindible, y compilar a ciegas otro formato daría un sitio
    mal hecho sin que nadie lo notara; un aviso se perdería entre los demás.
    Se descartó dejar el esquema fuera de la promesa de la 1.0, porque otro
    editor no tendría ninguna garantía sobre él: el §11 es contrato como el
    resto, y crecer con un tipo de campo nuevo no lo rompe.
123. **Cerrada.** La promesa de compatibilidad de la 1.0 cubre tres cosas: el
    formato (este documento), la orden de consola y una lista corta de la
    biblioteca PHP, documentada en [`biblioteca.md`](biblioteca.md); el resto
    queda `@internal` y puede cambiar en cualquier versión (§12, reglas 5 y 6).
    La lista sale de lo que usa el primer programa que incrusta el motor, un
    editor web: compilar (`Compilador`, `CompiladorEnProceso`,
    `Informe`), leer un proyecto (`Proyecto`, `Lector`, `Lectura`, `Sitio`,
    `Pagina`, `Formato`), los avisos y los errores (`Aviso`, `Avisos`,
    `ErrorDeProyecto`), `Slug` y el despliegue. Se descartaron cubrir todo lo
    que no es `@internal`, unas treinta clases con todos sus métodos, porque
    cualquier refactor interno sería un cambio mayor; y no prometer nada de la
    biblioteca, porque quien la incrusta quedaría fijado a una versión menor del
    motor y habría que revisarlo en cada una. De cada clase se promete lo que el
    documento nombra y no lo demás aunque PHP lo permita (`Sitio` es público,
    pero no `Sitio::$encabezados`). `FrontMatter` y `Yaml` siguen internos: el
    editor web solo los cita en comentarios. La regla de retirada: lo que va a dejar
    de valer avisa durante, al menos, una versión menor antes de quitarse.
124. **Cerrada.** La compilación por lotes (§10.4) entra en la 1.0. Estaba
    aplazada desde la decisión 51 y la web de Ehundu la prometía para la 1.0:
    es la razón de ser del proyecto, compilar también desde un proceso web con
    los límites que eso trae, y deja una huella en el formato, la carpeta
    `.ehundu/` (§1), que conviene reservar antes de congelarlo. Se mide así: en
    los sitios de prueba, volver a leer el proyecto en cada llamada cuesta 0,7 s
    en el mayor (1.200 páginas, 40 a 50 s de compilación), así que el estado no
    necesita guardar nada del sitio, solo por dónde va. Es una interfaz nueva,
    `CompiladorPorLotes`, con `Lote` como resultado: `Compilador` y
    `CompiladorEnProceso` no cambian, y añadir un parámetro o un resultado a
    `Compilador::compilar` habría roto a quien la implemente. El estado lo
    guarda el motor en `.ehundu/lote.json`, no quien llama: así funciona igual
    desde un editor web, un cron o un programa de otra persona, sin que cada uno
    invente dónde guardarlo. Cuesta la regla de que el motor solo escribe en
    `salida/`, que pasa a ser «y en `.ehundu/`», y se evita que ensucie un
    repositorio con una carpeta que escribe él mismo con su propio
    `.gitignore`. Se descartó guardar cada página en una carpeta aparte y
    cambiarla de golpe al final, que haría `salida/` siempre coherente pero
    copiaría `publico/` otra vez (puede ser de gigas), y escribir cada página al
    hacerla deja `salida/` mezclada hasta el último lote, que el lote
    reconoce con `completo()`. La compilación incremental guardada entre
    peticiones no entra: es otra función, que los lotes no impiden. (La decisión
    128 los sacó después de lo que promete la 1.0.)
125. **Cerrada.** El despliegue también se reparte en lotes. `Desplegador`
    acepta un presupuesto de tiempo (`$segundos`): cuando se agota, guarda el
    manifiesto y para, y lo dice, y la llamada siguiente sigue donde se quedó.
    Y acepta un `Informe` ya hecho (`$compilado`), para no volver a compilar
    en cada llamada cuando la compilación se hizo por lotes. El manifiesto del
    destino ya guarda lo subido cada 50 ficheros o 15 segundos (decisión 53), así
    que cortar la subida y retomarla ya era posible; lo nuevo es parar a tiempo
    y decirlo (`InformeDeDespliegue::$completo`). Salió de que una petición que
    compila en lotes y luego sube de golpe se pasaría igual del límite en un
    sitio con muchos ficheros.
126. **Cerrada.** La compilación incremental sale del proceso: `.ehundu/memoria.bin`
    guarda lo que ya recuerda, y `CompiladorIncrementalEnProceso` lo usa (§10.5).
    Sustituye en parte a la decisión 50.
    La decisión 124 la había dejado fuera, y salió de usar los lotes en un editor web con el sitio más grande de los de
    prueba (1.200 páginas): guardar un cambio de una página costaba una
    compilación entera, de 35 a 45 s, porque los lotes no recuerdan nada, y
    la incremental, que dentro de un proceso rehace esa página en 0,7 s, no se
    podía usar porque lo que recuerda no sobrevivía a la petición. Se midió que
    la memoria entera pesa 97 MB y se carga en 0,06 s: no hacía falta inventar
    nada más pequeño. Se guarda tal cual, con la versión del motor, y se ignora
    sin error si no vale, porque el resultado tiene que ser siempre el de una
    completa y el motor no puede fiarse de algo que escribió otra versión de sí
    mismo. El trabajo por presupuesto no necesita un estado como el de los
    lotes: una memoria con la mitad de las páginas es una memoria a la que le
    faltan páginas, y el plan de la incremental ya rehace las que faltan y
    aprovecha las que valen, también si el proyecto cambia entre dos llamadas
    (con los lotes, empezaba de nuevo). Los lotes se quedan como están, porque
    la decisión 124 ya los promete (la 128 retiró después esa promesa), y esto
    los complementa: son los que reparten una compilación completa sin recordar
    nada. Cuesta lo que pesa la
    memoria, en disco y en el proceso que la carga, y que la primera
    compilación tras cada cambio de versión del motor sea completa. Se
    descartó guardar solo lo que usó cada página y volver a leer el HTML de
    `salida/` (ahorra la mitad, pero depende de que nadie toque `salida/` y
    complica el motor), y dejar el trabajo al editor con una compilación
    posterior a la respuesta, que esconde la espera pero no la quita y deja
    la copia ocupada.
127. **Cerrada.** El motor marca las páginas con `<meta name="generator" content="Ehundu">`,
    por defecto y con `generador: no` para quitarla (§2). Salió de querer que los
    sitios hechos con Ehundu se reconozcan, como los de otros generadores. Se
    decidió que el contenido sea solo `Ehundu`, sin la versión: con ella, cada
    actualización cambiaría el HTML de todas las páginas de todos los sitios y
    obligaría a volver a subirlas, y en un editor todo aparecería como cambios sin
    publicar sin que nadie hubiera tocado nada; además, daría a quien mira el
    código de una web la versión exacta del motor. Y que la añada el motor, antes
    de `</head>`, en vez de una función que cada plantilla tenga que escribir,
    porque la marca solo sirve si sale en todos los sitios sin que cada uno se
    acuerde de ponerla. Es una excepción a que el motor no toca el HTML de la
    página, que son las plantillas del sitio; por eso se limita a una sola línea,
    se respeta la etiqueta de quien ya la tiene y se puede quitar con una clave.
    Cuesta que, al actualizar el motor, cada página de cada sitio lleve una línea
    más, así que cada sitio tiene que volver a publicarse una vez; no pasa de una
    vez a otra, porque la etiqueta no cambia con la versión. Se descartó una
    función de plantilla (`{{ generador() }}`) por no ser por defecto, y poner
    también la versión en el feed (`<generator version="…">` de Atom), porque el
    feed cambiaría con cada versión como las páginas.
128. **Cerrada.** La compilación por lotes (§10.4) deja de ser parte de lo que
    promete la 1.0: `CompiladorPorLotes`, `CompiladorPorLotesEnProceso` y su
    estado en `.ehundu/lote.json` pasan a `@internal`, y lo que se promete para
    repartir una compilación en llamadas con un presupuesto de tiempo es
    `CompiladorIncrementalEnProceso::avanzar` (§10.5), que devuelve el mismo
    `Lote`. Corrige la decisión 124, que los prometía, y la 126, que los dejaba
    «como están, porque la 124 ya los promete». Salió de revisar lo que se iba a
    congelar: la lista pública tendría tres maneras de compilar, y dos de ellas
    con un `avanzar` casi igual, así que quien incruste el motor se
    encontraría con dos puertas que hacen lo mismo. Y los lotes traen rarezas
    que la incremental no podría imitar sin cambiar de significado: `iniciar`,
    `estado` y `abandonar` (un estado que se pueda leer sin cargar la memoria
    entera, descartar un lote), `Lote::reiniciado()` (siempre falso en la
    incremental) y un `Lote::$total` que cuenta tareas en unos y páginas en la
    otra. Además, mantener las dos obliga a mantener dos implementaciones del
    bucle de páginas hasta una versión mayor. Prometer es irreversible dentro de
    una versión mayor y no prometer no lo es: añadir algo a la lista en una
    versión menor no cuesta nada y quitarlo exige una mayor. Se descartaron
    mantener la clase pública prometiendo solo `avanzar` y `Lote` (conserva lo
    único que los lotes hacen mejor, compilar un sitio grande con unas decenas de
    megas por llamada frente a las centenas de la incremental, pero deja dos
    clases públicas con el mismo `avanzar`) y dejarlo como estaba (congela las
    rarezas). El código se queda, `@internal`, y se decidirá si se borra cuando la
    incremental pueda guardar su memoria por partes. No cambia nada para quien los
    use hoy, solo que no se promete. La decisión 125 (el despliegue repartido en
    llamadas) no cambia.
129. **Cerrada.** Una fecha (`fecha`, `publicar`) es un día y la hora no cuenta
    (§4). Si se escribe con hora o con zona (`2026-10-01 18:30:00`,
    `2026-10-01T18:30`, `2026-10-01T18:30:00+02:00`), se avisa y se toma solo el
    día, desde su medianoche en la zona del sitio. Salió de revisar lo que se
    iba a congelar: el motor ya aceptaba la hora sin que el contrato lo dijera
    (la tenían en cuenta el orden de los listados y el feed, y `publicar` y
    `publicarEnSuFecha` publicaban a esa hora, contra lo que decía el §4), pero
    solo con segundos o en formato ISO: `09:00` lo leía YAML como texto, el motor
    avisaba de que no era una fecha y publicaba la página en el acto.
    Documentarla habría prometido la forma exacta que acepta la dependencia que
    lee el YAML, y habría cambiado lo que tendría que ofrecer un editor. Y una
    hora sirve de poco en un sitio estático, que solo saca la página cuando se
    vuelve a compilar después de ese momento (§4, `publicarEnSuFecha`), es
    decir, si hay una compilación a esa hora. Ningún sitio de prueba la usaba.
    Se descartó documentarla y dejarla sin decir, que funciona por accidente y
    podría cambiar con una actualización de esa dependencia sin que nadie lo
    notara. Cuesta no poder publicar a una hora concreta ni ordenar por hora dos
    entradas del mismo día, que quedan en el orden que tenían. Añadirla más
    adelante es crecer: lo que hoy avisa pasaría a valer. Un día escrito con
    hora cero y sin zona (`2026-10-01 00:00:00`) no se distingue de un día y no
    avisa.
130. **Cerrada.** La `url` de `sitio.yml` es la del dominio, sin ruta (§2):
    `https://www.ejemplo.com`, con o sin la barra final; con una ruta o con
    parámetros es un error, y `sitio.url`, en las plantillas, no acaba nunca en
    `/`. Salió de lo mismo: el motor aceptaba `https://ejemplo.com/sub/` y
    dejaba el sitio a medias, con el sitemap, el feed y lo que se escribe con
    `{{ sitio.url }}` bajo `/sub/`, pero con `pagina.url`, los enlaces
    internos, `css_fichero()`, `version()` y las imágenes colgando de la raíz.
    Admitir la ruta de verdad pide prefijar todas las direcciones internas y
    decidir qué vale `pagina.url`: si lleva el prefijo, las plantillas que
    escriben `{{ sitio.url }}{{ pagina.url }}` lo repetirían, y si no, no
    serviría de enlace. Es un cambio grande, que retrasaría la 1.0 y que ningún
    sitio pedía. Documentar el soporte a medias habría sido prometer lo que no
    funciona. Cuesta que un sitio en una subcarpeta no se pueda hacer con Ehundu
    hasta que se soporte, y que el build se detenga a quien tuviera hoy una ruta
    en la `url`. Pasar de error a soportado es crecer y no cierra ninguna
    puerta.
131. **Cerrada.** Una imagen enlazada a otro dominio lleva `target="_blank"` y
    `rel` como cualquier enlace escrito en Markdown (§8): la figura de una
    imagen sola enlazada y el atajo `imagen` con `enlace`. Salían sin ellos,
    contra lo que decía el contrato («un enlace escrito en Markdown a otro
    dominio recibe `target` y `rel`»). La plantilla `imagen` recibe dos
    variables nuevas, `externo` y `rel`, y la de Ehundu las usa; una plantilla
    `imagen` del sitio que no las use queda como la escribió, porque lo que
    escribe el sitio no se toca (§8). Es externo por la misma regla que los
    enlaces del Markdown: tiene dominio y no es el del sitio, con y sin `www.`;
    una ruta, un ancla o un `mailto:` no lo son. No lleva el aviso para lectores
    de pantalla, como ya decía el §2: un enlace que solo envuelve una imagen no
    tiene texto al que añadirlo. Ninguno de los sitios de prueba cambia: las tres
    figuras enlazadas a otro dominio que tenían ya llevaban `target` y `rel`
    escritos a mano. Se descartó dejar las figuras como estaban y corregir el
    contrato, porque la regla de abrir en otra pestaña los enlaces a otros
    dominios no se cumpliría en las figuras, y una clave nueva `figuras: sí` en
    `enlacesExternos`, que sería una opción para algo que casi todos querrán.
132. **Cerrada.** «La v1» deja de significar el alcance de las primeras
    versiones: el formato es el formato 1 (`formato: 1`, §2), y lo que el
    contrato admite hoy o no hace todavía se dice «hoy» o «todavía». Salió de
    revisar lo que se iba a congelar: «la v1» se usaba para tres cosas (el
    formato 1, el alcance de las primeras versiones y la propia 1.0), y leída
    como el formato 1, el §13 prometía que en toda la 1.x no llegarían la
    generación de imágenes, los componentes ni la búsqueda, lo que choca con la
    regla 5, que deja crecer, y con lo que ya estaba planeado después de la 1.0.
    Los destinos, los tipos de campo y el aviso de los ficheros PHP dicen «hoy»;
    el §13 se llama «Lo que Ehundu no hace (todavía)» y dice que lo que lista
    puede llegar dentro de una versión mayor si es aditivo; `componentes` sigue
    reservado, sin decir «v2». La regla 4, que el motor no ejecuta código propio
    del sitio, se queda como promesa explícita de la versión mayor: es lo que
    permite alojar el motor en cualquier hosting compartido sin riesgo. Las
    decisiones anteriores conservan sus palabras, con una nota al empezar este
    apartado. Se descartó tomar «v1» al pie de la letra, que convertiría el §13
    en la promesa de que eso solo llegará con un formato 2, coherente pero con
    puertas cerradas que el plan quiere abrir, y partir el §13 en «para siempre»
    y «por ahora», que obliga a decidir hoy qué es para siempre y casi nada lo
    es salvo la regla 4.
133. **Cerrada.** «Solo crece» (§12, regla 5) tiene una excepción, la de los
    nombres: un campo reservado nuevo o un nombre nuevo que el motor escriba en
    `pagina` puede tapar un campo propio con el mismo nombre que un sitio ya
    tuviera, y por eso un nombre nuevo se anuncia en una versión menor y el
    motor que lo añade avisa a los sitios que ya lo usan. Los nombres que el
    motor escribe en `pagina` se juntan en una tabla (§7.2), y los cinco que
    escribe siempre (`ruta`, `contenido`, `markdown`, `encabezados` y
    `traducciones`) avisan ahora si el front matter los usa, y el campo se
    ignora, como ya hacía `idioma`: hasta ahora lo tapaban en silencio. Salió
    de revisar lo que se iba a congelar: había once nombres repartidos por el
    documento, cada párrafo avisaba por su lado de que tapaba un campo, y la
    regla 5 prometía lo contrario. Un sitio de prueba usa `grupo` como campo
    propio, sin `agrupar`: es el tipo de nombre que un sitio escoge, y por eso
    `grupo`, `paginas` y `paginacion` solo tapan en las páginas que genera
    `agrupar` o `paginar` y no avisan antes. Se descartó reservar ahora un
    conjunto amplio de nombres, que reservaría para siempre palabras comunes y
    avisaría a sitios que hoy funcionan, y mover lo que añade el motor a un
    espacio propio (`pagina.motor.grupo`), que es lo más limpio pero rompe las
    plantillas escritas y sería una versión 2. Ningún sitio de prueba usa los
    cinco nombres, así que no cambia ninguno.
134. **Cerrada.** La tabla de letras del filtro `slug` y la conversión de `&` en
    `y` (§5.2) son parte del contrato y no cambian dentro de una versión mayor.
    El §5.2 enumeraba unas pocas letras y la tabla real tenía bastantes más
    (`ð`, `đ`, `ø`, `ł`, `ı`, `ſ`, `þ`, `ĳ`…), así que dos implementaciones que
    siguieran solo el texto habrían dado direcciones distintas; y la tabla no
    solo fija las URL, también el orden alfabético de los textos, los grupos de
    `agrupar` y los `id` de los encabezados. Se copia entera al §5.2, con una
    prueba que compara la del documento con la del código, letra a letra.
    Cerrarla cuesta que no se pueda añadir una letra, porque cambiaría la
    dirección de toda página cuyo título la lleve, que hoy sale con un guion: las
    de otros alfabetos y las del latino que la tabla no recoge (el vietnamita)
    siguen desapareciendo, y quien las necesite podrá tener más adelante una
    opción por sitio, que añade y no toca esta tabla. `&` seguirá dando `y`,
    también en un título en inglés. Se descartó ampliarla ahora (griego,
    cirílico, `ə`), que exige elegir convenciones de transliteración que ningún
    sitio necesita, y dejarla como detalle de implementación, que era lo que
    había y que impide reproducir las direcciones de otro. Ninguna dirección
    cambia: la tabla es la que ya tenía el código.
135. **Cerrada.** Un punto en el nombre de una página, de un fichero de `datos/`
    o de un `_datos.yml` que no es el de un código de idioma detiene el build con
    un error que nombra el fichero (§15.2), también en un sitio de un solo idioma.
    Antes se saltaba el fichero con un aviso y código de salida 0: `guia.v2.md`
    no salía en el sitio, y el siguiente despliegue la borraba del servidor, que
    es justo lo que el §4 evita para un front matter roto. Lo mismo valía para
    `_datos.v2.yml`, que quita de una carpeta lo que tenía en común. Salió de
    revisar lo que se iba a congelar: el §15 dice que un sitio de un solo idioma
    «no cambia en nada», y no era verdad para los nombres con puntos, que
    afectarán a migraciones de blogs con nombres como `2024.10.01-titulo.md`.
    Es un error cuando lo que hay tras el último punto no tiene forma de código
    (dos o tres letras minúsculas), o cuando hay otro punto antes; si tiene forma
    de código pero el sitio no lo declara (`contacto.fr.md`), sigue siendo un
    aviso, porque puede ser una traducción preparada de antemano. Queda por tanto
    un caso en aviso que se parece: una plantilla `sitemap.xml.twig`, cuyo
    `xml` tiene forma de código. Se descartó aceptar los puntos en los sitios de
    un solo idioma, porque uno que pasara a bilingüe vería cambiar el significado
    de sus nombres y dejaría de ser cierto que se puede pasar a bilingüe sin
    renombrar nada, y dejar el aviso y corregir el texto del §15, que quedaría
    documentado pero con el mismo riesgo de borrar una página sin querer. Ningún
    sitio de prueba tiene un punto que no sea el de un idioma, así que no cambia
    ninguno.
136. **Cerrada.** Lo que el motor hacía y solo estaba en el código queda
    escrito en el contrato, sin cambiar lo que hace, porque con la 1.0 pasa a
    ser promesa. Salió de revisar el contrato contra el código: en una página
    generada, `traducciones` solo trae la propia (§6.3, §6.4, §15.8) y el
    selector de idioma que nunca enlaza a una URL que no existe es de la
    plantilla recomendada, no del motor; las páginas de `agrupar` salen en el
    sitemap y en `todo` y heredan `listada: no` (§6.3); qué es un enlace a otro
    dominio, que hasta ahora decidía la biblioteca de Markdown: una dirección con
    dominio, o que empieza por `//`, que no es el del sitio, y sale sin dirección
    si su esquema es inseguro (§8); las reglas exactas de los atajos (nombres de
    atributo, comillas sin escape, las 24 etiquetas que cuentan como bloque, que
    gana la variable del motor sobre el atributo del mismo nombre, que el pie del
    atajo es texto y el de la figura Markdown, y que lo que escriben las
    plantillas de Ehundu va en castellano: `Vídeo`, `1,2 MB`, `sí`/`no`; §8.1,
    §8.2); el orden de los textos, que compara el valor de cada carácter y no es
    el de un diccionario entero (§6.2); que `{{ grupo }}` y `{{ grupo|slug }}`
    valen en el patrón de `agrupar`; que `sí/no` no distingue mayúsculas y
    `"true"` entre comillas no vale (§4); cómo se leen los nombres de los
    ficheros de `datos/` en Twig (§3); qué es una sección vacía de `sitio.yml` y
    qué errores detienen el build y cuáles se avisan (§2). Y lo que no es
    contrato sale de él: las medidas de rendimiento van a `compilacion.md`, y
    `lote.json` y `memoria.bin` no se prometen, mientras que el manifiesto del
    despliegue sí se lee en cualquier 1.x (§10). Se descartó cambiar el
    comportamiento antes de escribirlo, que ninguna de estas rarezas merece, y
    dejarlas sin decir, que es dejar que dos implementaciones las resuelvan
    distinto. Con una excepción, que sí cambia lo que hace: un `formato` que no
    es un entero mayor que cero (`"2"` entre comillas, `0`) detiene el build en
    vez de avisarse y tomarse por 1, porque la decisión 122 existe para no leer a
    ciegas un proyecto de otro formato; ningún sitio de prueba lo escribía así.
137. **Cerrada.** El contrato promete el mismo HTML con la misma versión del
    motor y de sus dependencias, y no entre versiones: lo que cambie al
    actualizar se anota en las notas de la versión (§12, regla 3). Salió de
    revisar lo que se iba a congelar: «las dos únicas formas» de que el mismo
    proyecto dé otro HTML (`random()` y la fecha del momento) estaban
    incompletas, porque qué páginas salen también depende del reloj
    (`publicar`, `publicarEnSuFecha`), y la regla de que todo lo que afecta al
    resultado vive en la carpeta no incluía el reloj; y el contrato no decía si
    el mismo proyecto da el mismo HTML con dos versiones del motor, cuando Twig,
    CommonMark y dompdf, que Composer deja actualizar dentro de su versión mayor
    (`^3.30`, `^2.10`, `^3.1`), deciden partes del resultado. Importa no
    obligar a subir de nuevo todas las páginas (la decisión 127 lo muestra), y
    por eso el motor lo cuida, pero no lo promete. Se descartó prometer
    estabilidad byte a byte, que exigiría un corpus de referencia y fijar las
    dependencias a una versión menor, de modo que una corrección de seguridad de
    Twig obligaría a publicar una versión de Ehundu, y prometer solo lo que el
    contrato nombra, que deja sin decir justo lo que más puede cambiar. Cuesta
    que un `composer update` puede cambiar bytes de un sitio sin que Ehundu
    cambie; el aviso es la nota de la versión.
138. **Cerrada.** Cuatro ajustes pequeños que, con el contrato congelado, ya no
    se podrían hacer sin una versión mayor. `publico/` deja de copiar lo que
    dejan el sistema y Git (`.DS_Store`, `.gitkeep`, `Thumbs.db`, `desktop.ini` y
    cualquier `.git`), y `contenido/` `Thumbs.db` y `desktop.ini`: una lista corta
    y cerrada, porque añadir una exclusión después haría desaparecer ficheros de
    la salida; `.htaccess` y `.well-known/` se copian, y ningún sitio de prueba
    tenía ninguno de esos ficheros (§9). El feed escribe como `<id>` de cada
    entrada su dirección completa y como autor el `nombre` del sitio, y eso se
    promete (§10.2), porque un lector de feeds identifica las entradas por
    `<id>` y, si el criterio cambiara, cada una aparecería como nueva; no se
    añade una clave de autoría, que sería crecer. Un código de idioma con región
    (`pt-br`) ya era un error y se escribe que esa forma queda reservada, sin
    implementarla (§15.1); y se escriben el formato de fecha por defecto de los
    idiomas sin tabla y que solo cuatro letras se traducen (§15.9). Y
    `componentes`, reservado desde la decisión 68, avisa si un sitio lo usa como
    campo propio, aunque sigue llegando a la plantilla (§13). Se descartó
    ignorar todo lo que empieza por punto salvo `.htaccess` y `.well-known/`, que
    haría desaparecer ficheros que alguien puso a propósito, y dejar `publico/`
    como estaba avisando de que se limpie.

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

Un sitio en un solo idioma no escribe nada de esto y no cambia en nada, salvo
que el punto en el nombre de un fichero está reservado siempre (§15.2).

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
para el selector, escrito en ese idioma; si falta, se usa el código. Un código
con región (`pt-br`, `es-mx`) no se admite y detiene el build: esa forma queda
reservada para más adelante (decisión 138).

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
sin francés) no se lee: se salta con un aviso, porque puede ser una traducción
preparada de antemano. El punto en el nombre de una página, de un fichero de
`datos/` o de un `_datos.yml` está reservado para el idioma en todos los
sitios, también en los de un solo idioma: un punto que no es el de un código de
dos o tres letras minúsculas (`guia.v2.md`, `2024.10.01-titulo.md`,
`guia.antigua.es.md`) detiene el build con un error que nombra el fichero,
porque saltárselo haría que el despliegue borrara la página del sitio publicado
(decisión 135). Los ficheros que se copian tal cual (`jquery.min.js`) llevan
los puntos que quieran.

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
- **El selector de idioma de la plantilla recomendada nunca enlaza a una URL
  que no existe.** El motor no escribe selectores: es una propiedad de esa
  plantilla (§15.8). Si la página actual no está traducida, lleva a la portada de
  ese idioma, y la plantilla puede distinguir los dos casos para avisar al
  visitante.

### 15.5 Colecciones

Las colecciones están acotadas al idioma de la página que las consulta: un
listado de blog en inglés nunca saca artículos en castellano. También `todo`.
Otro idioma se pide con un segundo argumento, que es un código o `todos`:

    {% for articulo in coleccion('blog') %}                  {# en el idioma de la página #}
    {% for articulo in coleccion('blog', 'eu') %}            {# solo los que están en euskera #}
    {% for articulo in coleccion('blog', idioma: 'todos') %} {# todos #}

Un código que el sitio no declara avisa y da una lista vacía. `orden` y `donde`
ven también `idioma` (§6.2). Una página con `agrupar` (§6.3) agrupa las del
idioma de su fichero.

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
como cualquier otro fichero (§4). Es también donde un editor guarda los
campos de una página que no son de cada traducción (§11.2).

### 15.8 Plantillas

Todas las páginas, también las que dan `coleccion()`, llevan:

- `pagina.idioma`, el código de su idioma. En un sitio de un solo idioma es el
  de `idioma`, así que `<html lang="{{ pagina.idioma }}">` vale para todos.
- `pagina.traducciones`, sus versiones publicadas, ella incluida, por código y
  en el orden de `idiomas`: `pagina.traducciones.eu.url`. Cada una tiene la
  misma forma que cualquier otra página. Un borrador o una traducción con
  `publicar` en el futuro no están. Una página generada por `agrupar` o por
  `paginar`, salvo la primera de esta, solo tiene como traducción a sí misma
  (§6.3, §6.4).

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
`sitio.yml`, y las fechas salen con los nombres en inglés, que son los de PHP, y
con el formato por defecto del inglés, `j F Y` (`18 March 2025`). Solo las
letras `l`, `D`, `F` y `M` se escriben en el idioma de la página; las demás
salen como en PHP (`S`, `a` y `A`, en inglés).

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
