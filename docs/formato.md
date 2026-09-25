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
      servidor: ftp.ejemplo.com
      usuario: esquina
      ruta: /home/esquina/www
      # la contraseña va en .secretos.yml, nunca aquí

`sitio.yml` es obligatorio y tiene que llevar al menos `nombre` y `url`. `url`
es la dirección absoluta del sitio, con `http://` o `https://`. Si falta el
fichero o alguno de esos dos campos, el build se detiene con un error: sin
ellos no se pueden generar ni el sitemap ni el feed.

Destinos admitidos en la v1: `carpeta`, `ftp`, `sftp`, `s3`.

`.secretos.yml` tiene la misma forma, solo con las claves sensibles. El motor
lo lee si existe; una herramienta de edición puede guardar esas claves por su
cuenta, cifradas.

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

La única excepción a "gana el más cercano" es `etiquetas`: las de cada nivel se
suman a las heredadas, sin repetir y de lo más general a lo más concreto. Un
artículo que declara `etiquetas: [novela, recomendaciones]` sigue estando en la
colección `blog` que le pone su carpeta. Los demás campos, listas y mapas
incluidos, se sustituyen enteros; no se mezclan por dentro.

---

## 4. Ficheros de contenido

Extensiones reconocidas: `.md` (Markdown) y `.twig` (plantilla). Cualquier otra
cosa dentro de `contenido/` se copia tal cual. Lo que empieza por punto
(`.gitkeep`, `.git/`) se ignora.

El front matter va entre `---`, en YAML. Si no es YAML válido, el build se
detiene con un error que indica fichero y línea: seguir sin esa página haría
que el despliegue la borrara del sitio publicado.

Campos reservados:

| Campo         | Tipo    | Significado                                              |
|---------------|---------|----------------------------------------------------------|
| `titulo`      | texto   | Título de la página. Obligatorio.                         |
| `subtitulo`   | texto   | Entradilla o descripción corta.                           |
| `descripcion` | texto   | Descripción para buscadores (`meta description`).         |
| `fecha`       | fecha   | Fecha de publicación (`AAAA-MM-DD`).                      |
| `url`         | texto   | URL final o patrón. Si falta, se deriva de la ruta.       |
| `plantilla`   | texto   | Nombre del layout, sin extensión. `false`: sin layout.    |
| `etiquetas`   | lista   | Colecciones a las que pertenece.                          |
| `listada`     | sí/no   | Si es `no`, se publica pero no entra en colecciones ni en el sitemap. |
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
blanco (`imagen:`) no es un error: pasa vacío y sin aviso.

Detalles de los tipos:

- **sí/no** admite `sí`, `si`, `no`, `true` y `false`. YAML solo reconoce los
  dos últimos, así que el motor traduce los otros tres.
- **fecha** se escribe `AAAA-MM-DD`, con o sin comillas. Como en YAML, es la
  medianoche de ese día en UTC.
- **texto** admite un número, que se toma como texto (`titulo: 2024`).
- **número** admite un número entre comillas (`orden: "2"`).
- **`etiquetas`** admite un texto suelto, que cuenta como una lista de un
  elemento: `etiquetas: servicio` es lo mismo que `etiquetas: [servicio]`.
- **`plantilla`** vacía (`plantilla: ""`) es lo mismo que `plantilla: false`.

Se aceptan como alias los nombres ingleses habituales en Eleventy y Lume
(`title`, `subtitle`, `description`, `date`, `tags`, `layout`, `permalink`),
para no tener que reescribir el front matter al migrar. Si una página lleva a
la vez el nombre inglés y el español, gana el español y se avisa.

---

## 5. URL

Por defecto, la URL sale de la ruta dentro de `contenido/`, quitando la
extensión y añadiendo barra final:

    contenido/servicios/encuadernacion.md  →  /servicios/encuadernacion/
    contenido/index.md                      →  /

El campo `url` la sustituye. Admite expresiones sobre el propio front matter:

    url: "/blog/{{ titulo|slug }}/"

`url: false` produce un fragmento: se lee, se puede consultar desde otras
páginas y se puede editar desde un editor, pero no genera fichero.

---

## 6. Colecciones

Una colección es el conjunto de páginas que comparten una etiqueta. Se declaran
con `etiquetas`, normalmente desde el `_datos.yml` de la carpeta.

Desde una plantilla:

    {% for articulo in coleccion('blog')|orden('fecha desc')|limite(6) %}

Filtros de la v1: `orden` (por cualquier campo, `asc` o `desc`; por defecto
`fecha desc`), `limite`, `invertir`, `sin` (excluye una página, normalmente la
actual), `donde` (campo igual a valor).

Toda página con URL está además en la colección implícita `todo`.

Una página con `listada: no` no entra en ninguna colección, tampoco en `todo`.
Es el caso de los textos legales, la página de error o `robots.txt`: se
publican, pero no salen en listados ni en el sitemap.

---

## 7. Plantillas

Motor: **Twig**. Los layouts viven en `plantillas/`, los fragmentos en
`parciales/`. El layout se elige con `plantilla`; si no se indica, se usa
`pagina`. Con `plantilla: false` la página sale tal cual, sin layout, que es lo
que necesita un `robots.txt`.

Variables disponibles en cualquier plantilla:

- `sitio`: lo que hay en `sitio.yml` (sin la sección de despliegue).
- `datos`: los ficheros de `datos/`.
- `pagina`: el front matter de la página actual, más `pagina.url`,
  `pagina.contenido` (el cuerpo ya renderizado) y `pagina.ruta`.
- Funciones: `coleccion()`, `svg()` (incrusta un SVG de `publico/`),
  `activo()` (si una URL es la de la página actual).

Los parciales se incluyen con el `include` de Twig, sin invento propio.

---

## 8. Markdown

CommonMark, más tablas, notas al pie y enlaces automáticos. Dos añadidos forman
parte del contrato:

- Una imagen sola en su párrafo se convierte en `<figure>`, con el texto
  alternativo repetido como `<figcaption>`.
- Los enlaces externos reciben `target="_blank"` y `rel="noopener"`.

**Cerrado:** el Markdown NO pasa por Twig. En su lugar hay atajos, con un
vocabulario cerrado.

### 8.1 Atajos

Un atajo es una llamada con nombre y atributos con nombre dentro del Markdown:

    [imagen fichero="img/escaparate.jpg" alt="Escaparate de la librería" pie="La tienda en otoño"]

Y con contenido, cuando lo envuelve:

    [aviso tipo="importante"]
    Las plazas son limitadas.
    [/aviso]

Reglas:

- Solo se reconocen los nombres registrados. Si el nombre no está registrado,
  el texto se deja tal cual y se avisa por consola; nunca se rompe el build.
- Los atributos son siempre cadenas con nombre. No hay expresiones, ni
  variables, ni condicionales, ni acceso a colecciones: eso es lógica y la
  lógica vive en las plantillas.
- Cada atajo se implementa como una plantilla Twig en `parciales/atajos/`, con
  el nombre del atajo. Recibe los atributos y, si lo tiene, el contenido ya
  convertido a HTML.
- Se resuelven después de convertir el Markdown, sobre el HTML, para que el
  marcado que generan no lo reinterprete el convertidor.
- Los atajos son cosa de quien desarrolla el sitio: un editor puede ofrecer
  botones para insertar los que el sitio declare, sin que quien escribe el
  contenido tenga que poner atributos a mano.

Atajos incluidos en el motor: `imagen` (figura con pie y texto alternativo),
`video` (incrustación con relación de aspecto) y `archivo` (enlace a un
documento con su tamaño). El resto los define cada sitio.

---

## 9. Assets y CSS

`publico/` se copia tal cual a la raíz de la salida. Sin pipeline: el CSS y el
JS llegan ya escritos y, si hace falta, minificados fuera del motor.

Para el CSS por página se admite en el front matter:

    css: [estilos/home.css]

El layout lo incrusta con `{{ css() }}`, concatenando los ficheros comunes más
los de la página. Es el equivalente del plugin de bundle de Eleventy.

Las imágenes no se procesan durante el build. Si hacen falta varios tamaños,
los genera otra herramienta (por ejemplo, un editor al subir la imagen) y deja
los ficheros en `publico/`.

---

## 10. Salida

El motor genera, además de las páginas, `sitemap.xml`, `feed.xml` y `404.html`
si existe una página con esa URL. Nada más: los añadidos van como ficheros
normales dentro de `contenido/`.

El sitemap lleva todas las páginas con URL salvo las de `listada: no`.

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
   mismo HTML.
4. El motor no ejecuta código propio del sitio en la v1.

---

## 13. Fuera de la v1

Catálogo de componentes con parámetros y consultas, tokens de estilo por sitio,
generación de imágenes en varios tamaños, multiidioma, paginación de listados,
procesadores sobre el HTML ya generado, pasos posteriores al build y búsqueda.

Ninguna de estas cosas debería obligar a cambiar lo de arriba cuando llegue.
Si al añadirlas hay que romper el contrato, el contrato estaba mal.

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
