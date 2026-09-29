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

> **Estado: 0.4.** Compila sitios reales, también en varios idiomas, los
> previsualiza y los publica, y saca en PDF las páginas que lo piden.
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
Markdown, YAML, plantillas y archivos estáticos. Sin base de datos y sin estado
oculto. Una carpeta copiada a otra máquina compila igual.

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
  `[imagen …]`, `[video …]` (YouTube y Vimeo sin cookies), `[archivo …]` (con
  su tipo y su peso) y `[dato …]`. Un sitio puede añadir los suyos o cambiar
  el marcado de estos con una plantilla.
- **Plantillas Twig** con colecciones por etiqueta, filtros para ordenarlas y
  recorrerlas, SVG incrustados, el ancho y el alto de las imágenes, y el menú
  activo.
- **Datos** globales en YAML o JSON, y por carpeta en un `_datos.yml` que
  heredan todas sus páginas y subcarpetas.
- **URL** que salen de la ruta del fichero o de un patrón con los campos de la
  página.
- **Varios idiomas**, con un fichero por idioma en el mismo árbol
  (`contacto.md`, `contacto.eu.md`): cada idioma con su prefijo en la URL, las
  traducciones enlazadas entre sí para los `hreflang` y el selector de
  idioma, colecciones y datos de cada idioma, y las fechas en castellano,
  euskera, inglés o alemán. Traducir solo parte del sitio es lo normal, no
  una excepción.
- **El CSS y el JavaScript de cada página**, reunidos e incrustados en ella:
  cada plantilla declara lo que necesita donde lo usa.
- **PDF** de las páginas que lo piden con `pdf: sí`, con su propia plantilla
  (`menu.pdf.twig`) y sin programas externos. El mismo proyecto da siempre los
  mismos bytes, así que un PDF que no cambia no se vuelve a subir.
- **Un `sitemap.xml`**, y un feed Atom si se pide.
- **Despliegue** a una carpeta, por FTP o FTPS, por SFTP (comprobando la
  huella del servidor) o a cualquier servicio compatible con S3. Las
  contraseñas van en un fichero aparte que no se versiona.

Lo que no hace, a propósito, en esta versión: procesar imágenes, compilar CSS o
JavaScript, paginar listados o ejecutar código propio de cada sitio. El formato
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
hosting. The current version is 0.4: it builds Markdown and Twig sites,
multilingual ones included, renders the pages that ask for it as PDF (with
the optional dompdf), previews them with incremental rebuilds, and
deploys them to a folder, FTP, SFTP or any S3-compatible storage. It needs
PHP 8.4 or later and nothing else, and ships as a single `ehundu.phar` or as
the Composer package `ehundu/ehundu`.

Its project format is deliberately in Spanish (folder names, front matter
fields), as a commitment to Spanish-speaking web developers; the usual English
field names are accepted to ease migrations from Eleventy or Lume.
Documentation is in Spanish for now and will be available in English too.

The name *ehundu* is Basque for "to weave".

Ehundu is free software under the MIT licence. The licence covers the code
and documentation, not the logo or the Nidel illustrations in `marca/` (see
[`marca/README.md`](marca/README.md)).
