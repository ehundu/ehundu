<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="marca/ehundu-horizontal-negativo.svg">
    <img src="marca/ehundu-horizontal-color.svg" alt="Ehundu" width="420">
  </picture>
</p>

# Ehundu

Ehundu es un generador de sitios estáticos escrito en PHP. Toma una carpeta con
contenido en Markdown, datos en YAML y plantillas Twig, y produce un sitio web
de HTML plano que se puede alojar en cualquier servidor.

> **Estado: 0.16.** Compila sitios reales, también en varios idiomas, los
> previsualiza y los publica, saca en PDF las páginas que lo piden, genera una
> página por cada etiqueta, publica las entradas en su fecha, deja las páginas
> de una carpeta sin la carpeta en la URL, avisa a los lectores de pantalla de
> los enlaces que abren otra pestaña, da un `id`, y si se quiere un enlace
> permanente, a los encabezados del Markdown, para enlazar a un apartado o
> hacer un índice, ordena los textos al estilo español, sin contar
> los signos `¿` y `¡`, enlaza el CSS y el JavaScript de una página como un
> fichero con la huella de su contenido en el nombre, para que el navegador lo
> guarde, convierte a HTML el texto con formato de un campo, de una línea o
> de varios párrafos, con el filtro `markdown`, escribe el `srcset` (y el
> `<picture>`, si hay varios formatos) de las imágenes que ya tienen variantes
> de ancho, parte una lista larga en páginas con `paginar`, reparte la
> compilación y la subida de un sitio grande en varias llamadas de unos
> segundos cada una, recuerda lo que compila entre un proceso y otro para
> rehacer solo lo que un cambio afecta, marca sus páginas con una etiqueta
> `generator` (que se quita con `generador: no`), y lleva la versión del
> formato (`formato: 1` en `sitio.yml`) y una lista corta y documentada de lo que se promete de la
> biblioteca ([`docs/biblioteca.md`](docs/biblioteca.md)), que es lo que la 1.0
> congelará. La 0.16 es la relectura final del contrato: tres lectores
> comprobaron cada afirmación contra el código, lo que el motor hacía sin decirlo
> queda escrito, lo que antes tomaba a ciegas (un `formato` que no es un número,
> una `url` con ruta, un punto en el nombre de una página que no es de idioma, un
> `robots.txt.twig`) detiene el build o avisa, las fechas de un campo propio caen
> en la zona del sitio, y lo que la incremental recuerda entre procesos se guarda y
> se lee por registros, con bastante menos memoria de PHP y un aviso claro si no
> cabe en el `memory_limit`. El registro de decisiones está al final del
> documento, el esquema tiene el suyo ([`docs/esquema.md`](docs/esquema.md)) y
> `ehundu --version` dice qué versión es.
> Hasta la 1.0 el formato del proyecto todavía puede cambiar; cada cambio
> queda anotado, con su motivo, en el registro de decisiones de
> [`docs/formato.md`](docs/formato.md).

## Por qué otro generador

Los generadores de sitios estáticos más conocidos están pensados para
ejecutarse desde la línea de comandos, en el ordenador de quien desarrolla o en
un servicio de integración continua. Ehundu nace de una necesidad distinta:
poder compilar un sitio también desde un proceso web, en un alojamiento PHP
corriente, con los límites de tiempo y de recursos que eso supone.

Eso permite que una herramienta de edición guarde un cambio y publique el sitio
al momento, sin depender de servicios externos, repositorios en la nube ni
cadenas de despliegue ajenas. Y permite también lo contrario: trabajar sin
ninguna herramienta de edición, con un editor de texto y la línea de comandos,
exactamente con el mismo resultado.

## Principios

**PHP puro y pocas exigencias.** Ehundu funciona con PHP 8.4 o superior y no
necesita ejecutar programas externos ni extensiones poco habituales. Si un
alojamiento compartido ejecuta PHP, puede ejecutar Ehundu.

**Tu contenido son ficheros.** Todo lo que define un sitio vive en su carpeta:
Markdown, YAML, plantillas y archivos estáticos. Sin base de datos y sin nada
oculto que cambie el resultado (lo que el motor recuerda entre compilaciones vive
en una carpeta `.ehundu/` que se puede borrar). Una carpeta copiada a otra máquina
compila igual.

**Sin dependencia de servicios.** Ehundu se apoya en software libre que se
puede conservar y mantener, nunca en servicios cuyas condiciones pueden cambiar
de un día para otro. Publica en una carpeta, por FTP, por SFTP o en cualquier
almacenamiento compatible con S3, sin APIs propias de ningún proveedor.

**En español.** El formato del proyecto está en español: las carpetas se
llaman `contenido`, `plantillas` o `publico`, y los campos del front matter
`titulo`, `fecha` o `etiquetas`. Es una decisión deliberada. La comunidad
hispanohablante que desarrolla para la web es enorme y merece herramientas
pensadas en su idioma. Para migrar desde Eleventy o Lume se aceptan los nombres
ingleses habituales (`title`, `date`, `tags`, `layout`, `permalink`).

## Instalación

Hace falta PHP 8.4 o superior con la extensión `mbstring`. Para publicar por
FTP, además, la extensión `ftp`, que viene con PHP aunque a veces está
desactivada; y para FTPS o S3, `openssl`, que traen casi todas las
instalaciones.

Para generar el PDF de una página (`pdf: sí`) hace falta además dompdf, que es
PHP puro y necesita la extensión `dom`: `composer require dompdf/dompdf`. Es
opcional, y el `ehundu.phar` no lo trae.

**Un solo fichero.** Descarga `ehundu.phar` de la [página de
versiones](https://github.com/ehundu/ehundu/releases) y úsalo con PHP:

    php ehundu.phar compilar mi-sitio

**Con Composer**, como orden global:

    composer global require ehundu/ehundu

o dentro de un proyecto, que es como lo usaría un programa que incruste el
motor:

    composer require ehundu/ehundu

La orden queda en `vendor/bin/ehundu`.

## Un primer sitio

    mi-sitio/
      sitio.yml
      contenido/
        index.md
        blog/
          _datos.yml
          primer-articulo.md
      plantillas/
        pagina.twig

`sitio.yml` dice cómo se llama el sitio y dónde se publica:

```yaml
nombre: Mi sitio
url: https://www.ejemplo.com
idioma: es
```

Cada página es un Markdown con su front matter:

```markdown
---
titulo: Primer artículo
fecha: 2026-10-01
---

Texto del artículo en **Markdown**.
```

Lo que comparten todas las páginas de una carpeta va en su `_datos.yml`, así
que un artículo nuevo solo necesita título y texto:

```yaml
etiquetas: [blog]
url: "/blog/{{ titulo|slug }}/"
```

La plantilla es Twig, con las funciones que añade Ehundu, como `coleccion()`,
y las fechas en el idioma de la página:

```twig
<!doctype html>
<html lang="{{ pagina.idioma }}">
<meta charset="utf-8">
<title>{{ pagina.titulo }} · {{ sitio.nombre }}</title>
<main>
  <h1>{{ pagina.titulo }}</h1>
  {{ pagina.contenido }}
  {% for articulo in coleccion('blog')|invertir %}
    <p><a href="{{ articulo.url }}">{{ articulo.titulo }}</a>, {{ articulo.fecha|fecha }}</p>
  {% endfor %}
</main>
```

`ehundu compilar mi-sitio` escribe el resultado en `mi-sitio/salida/`: la
portada, `/blog/primer-articulo/` y un `sitemap.xml`.

## Las órdenes

    ehundu compilar [carpeta]     compila el proyecto en su carpeta salida/
    ehundu servir [carpeta]       lo previsualiza en el navegador y lo
                                  recompila cuando cambia algo
    ehundu desplegar [carpeta]    lo compila y lo publica en el destino de
                                  sitio.yml

`servir` rehace solo lo que afecta a cada cambio, en unas décimas de segundo,
y da siempre lo mismo que una compilación completa. `desplegar` sube lo nuevo o
cambiado y borra lo que subió Ehundu y ya no se genera; lo demás que haya en el
servidor no lo toca nunca. Con `--simular` dice qué haría sin hacerlo.
`ehundu --ayuda` enseña todas las opciones.

## Qué hace

- **Markdown** CommonMark, con tablas, notas al pie, tachado y enlaces
  automáticos. Una imagen sola en su párrafo es una figura con su pie, y los
  enlaces a otros dominios se abren aparte.
- **Atajos** para lo que el Markdown no tiene, sin meter lógica en el texto:
  `[imagen …]`, `[video …]` (YouTube sin cookies y Vimeo sin seguimiento), `[archivo …]` (con
  su tipo y su peso) y `[dato …]`. Un sitio puede añadir los suyos o cambiar
  el marcado de estos con una plantilla.
- **Plantillas Twig** con colecciones por etiqueta, filtros para ordenarlas y
  recorrerlas, SVG incrustados, el ancho y el alto de las imágenes, y el menú
  activo.
- **Datos** globales en YAML o JSON, y por carpeta en un `_datos.yml` que
  heredan todas sus páginas y subcarpetas.
- **URL** que salen de la ruta del fichero o de un patrón con los campos de la
  página.
- **Una página por cada valor** de un campo, como una por etiqueta, con una
  sola página que declara `agrupar`: una etiqueta nueva tiene su página sin
  que nadie la escriba.
- **Listas partidas en páginas** con `paginar`: la propia página es la primera,
  en su dirección, y el motor genera las demás (`/blog/pagina/2/`, o la forma
  que se pida), con las entradas de cada trozo y los enlaces para moverse. Una
  entrada nueva que desborde la última abre la siguiente sola.
- **Imágenes con varios anchos.** El motor no las genera, pero si otra
  herramienta las deja en `publico/` (`foto-480.webp`, `foto-960.webp`…) y el
  sitio las declara en `imagenes`, `variantes()`, el atajo `[imagen …]` y las
  figuras del Markdown escriben el `srcset` con el ancho real de cada fichero.
- **Varios idiomas**, con un fichero por idioma en el mismo árbol
  (`contacto.md`, `contacto.eu.md`): cada idioma con su prefijo en la URL, las
  traducciones enlazadas entre sí para los `hreflang` y el selector de
  idioma, colecciones y datos de cada idioma, y las fechas en castellano,
  euskera, inglés o alemán. Traducir solo parte del sitio es lo normal, no
  una excepción.
- **El CSS y el JavaScript de cada página**, reunidos e incrustados en ella o
  enlazados como un fichero cuyo nombre lleva la huella de su contenido, para
  que el navegador lo guarde: cada plantilla declara lo que necesita donde lo
  usa. Y `version('css/estilo.css')`, para dar a cualquier otro fichero de
  `publico/` una dirección que cambia cuando cambia su contenido.
- **PDF** de las páginas que lo piden con `pdf: sí`, con su propia plantilla
  (`menu.pdf.twig`) y sin programas externos. El mismo proyecto da siempre los
  mismos bytes, así que un PDF que no cambia no se vuelve a subir.
- **Un `sitemap.xml`**, y un feed Atom si se pide.
- **Compilación y subida repartidas en llamadas**, para un proceso web que no
  tiene tiempo de compilar un sitio grande de una vez: la biblioteca las reparte
  en varias llamadas con un presupuesto de segundos cada una (`avanzar`, en la
  compilación incremental, y el `Desplegador`), y al terminar dan lo mismo que
  una compilación completa.
- **Compilación incremental entre procesos**, para un proceso web que compila
  el mismo sitio una y otra vez: lo que recuerda se guarda en el proyecto
  (`.ehundu/`) y cambiar una página rehace unas pocas, en un sitio de 1.200
  páginas en unos 3 s en lugar de unos 40, con el mismo resultado que una
  compilación completa. También se puede repartir en llamadas de unos segundos.
- **Despliegue** a una carpeta, por FTP o FTPS, por SFTP (comprobando la
  huella del servidor) o a cualquier servicio compatible con S3. Las
  contraseñas van en un fichero aparte que no se versiona.

Lo que no hace, a propósito, en esta versión: generar las variantes de una
imagen (solo lee las que ya están hechas), compilar CSS o JavaScript, o
ejecutar código propio de cada sitio. El formato
está pensado para que lo que llegue después no obligue a cambiarlo, y ya
reserva lo que necesitará.

## Documentación

- [`docs/formato.md`](docs/formato.md): el formato de un proyecto, completo.
  Carpetas, `sitio.yml`, datos, campos, URL, colecciones, plantillas, Markdown,
  atajos, CSS y JavaScript, varios idiomas, y el registro de las decisiones
  tomadas.
- [`docs/previsualizacion.md`](docs/previsualizacion.md): `ehundu servir`.
- [`docs/compilacion.md`](docs/compilacion.md): cómo decide la compilación
  incremental qué rehace.
- [`docs/despliegue.md`](docs/despliegue.md): `ehundu desplegar` y cada
  destino.
- [`docs/biblioteca.md`](docs/biblioteca.md): lo que se promete de la
  biblioteca PHP y de la orden de consola, y qué es interno.

## Dentro de otro programa

La orden de consola es una capa fina sobre una biblioteca, y un programa puede
usar esa misma biblioteca para compilar desde un proceso web:

```php
use Ehundu\CompiladorEnProceso;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

try {
    $informe = (new CompiladorEnProceso())->compilar(Proyecto::abrir('/ruta/a/mi-sitio'));
    echo $informe->resumen(), "\n"; // Compilado: 2 páginas en 0,07 s.

    foreach ($informe->avisos as $aviso) {
        echo $aviso, "\n";          // con fichero y línea
    }
} catch (ErrorDeProyecto $error) {
    echo $error->getMessage();      // $error->fichero y $error->linea, por separado
}
```

`Ehundu\Despliegue\Desplegador` publica de la misma forma y recibe las
contraseñas directamente, sin fichero. Los errores y los avisos están en
español y señalan el fichero y la línea, para que el programa pueda enseñarlos
tal cual. Lo que hace falta para compilar de forma incremental desde un
proceso web está explicado en [`docs/compilacion.md`](docs/compilacion.md).

## El nombre

*Ehundu* es una palabra en euskera que significa «tejer». La elección no es
casual: *web*, en inglés, es literalmente una tela, un tejido. Un generador de
sitios hace eso mismo, entrelazar contenido y plantillas hasta formar páginas.

Se pronuncia más o menos «e-ún-du».

## Licencia

Ehundu es software libre bajo licencia MIT. Consulta el fichero `LICENSE`.

La licencia cubre el código y la documentación, pero no el logotipo ni las
ilustraciones de Nidel que hay en `marca/`: sus condiciones están en
[`marca/README.md`](marca/README.md).

---

## In English

Ehundu is a static site generator written in PHP, designed to build sites both
from the command line and from within a web process on ordinary shared PHP
hosting. The current version is 0.16: it builds Markdown and Twig sites,
multilingual ones included, renders the pages that ask for it as PDF (with
the optional dompdf), generates one page per tag, publishes entries on their
date, drops a folder name from its pages' URLs, flags external links to screen
readers, gives Markdown headings an id (and, if asked, a permalink) so pages can
link to a section or build a table of contents, sorts text the way a Spanish
dictionary does (ignoring the opening marks ¿ and ¡), links a page's CSS and
JavaScript as a file named after a hash of its contents so browsers can cache
it for a year, converts the formatted text of a field to HTML, inline or in
blocks, with a `markdown` filter, writes the `srcset` of images that already
have width variants, splits long lists into pages, previews them with
incremental rebuilds, spreads the build and the upload of a big site over
several calls of a few seconds each (for a web process with a time limit),
remembers what it built between one process and the next so that a change
rebuilds only the pages it affects, marks its pages with a `generator` meta
tag (`generador: no` turns it off), and deploys them to a folder, FTP, SFTP or any S3-compatible storage. It needs
PHP 8.4 or later with `mbstring` and nothing else, and ships as a single `ehundu.phar` or as
the Composer package `ehundu/ehundu`. Projects declare the version of the
format they use (`formato: 1`), and `docs/biblioteca.md` lists the short part
of the PHP library that 1.0 will promise to keep. Version 0.16 is the final
read-through of the contract: every claim was checked against the code, what the
engine did without saying so is now written down, and a few inputs it used to
take at face value (a malformed `formato`, a `url` with a path, a dot in a page
name that is not a language, a `robots.txt.twig`) now stop the build or raise a
warning. What the incremental build remembers between processes is saved and
read record by record, with much less PHP memory and a clear warning when it does
not fit in `memory_limit`. `ehundu --version` prints the version.

Its project format is deliberately in Spanish (folder names, front matter
fields), as a commitment to Spanish-speaking web developers; the usual English
field names are accepted to ease migrations from Eleventy or Lume.
The documentation is in Spanish, like the format.

The name *ehundu* is Basque for "to weave".

Ehundu is free software under the MIT licence. The licence covers the code
and documentation, not the logo or the Nidel illustrations in `marca/` (see
[`marca/README.md`](marca/README.md)).
