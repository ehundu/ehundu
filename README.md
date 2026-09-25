# Ehundu

Ehundu es un generador de sitios estáticos escrito en PHP. Toma una carpeta con
contenido en Markdown, datos en YAML y plantillas Twig, y produce un sitio web
de HTML plano que se puede alojar en cualquier servidor.

> **Estado:** en desarrollo. Todavía no hay una versión utilizable. Este
> repositorio se publicará con el código del motor cuando alcance su primera
> versión (0.1). Mientras tanto, aquí se explica qué es y hacia dónde va.

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
de un día para otro.

**En español.** El formato del proyecto está en español: las carpetas se
llaman `contenido`, `plantillas` o `publico`, y los campos del front matter
`titulo`, `fecha` o `etiquetas`. Es una decisión deliberada. La comunidad
hispanohablante que desarrolla para la web es enorme y merece herramientas
pensadas en su idioma.

## Un vistazo al formato

    mi-sitio/
      sitio.yml
      contenido/
        _datos.yml
        index.md
        blog/
          _datos.yml
          primer-articulo.md
      datos/
      plantillas/
      parciales/
      publico/

Un artículo del blog puede ser tan sencillo como esto, porque la carpeta ya
aporta la plantilla, la etiqueta y el patrón de la URL:

    ---
    titulo: Primer artículo
    fecha: 2026-10-01
    ---

    Texto del artículo en **Markdown**.

## El nombre

*Ehundu* es una palabra en euskera que significa «tejer». La elección no es
casual: *web*, en inglés, es literalmente una tela, un tejido. Un generador de
sitios hace eso mismo, entrelazar contenido y plantillas hasta formar páginas.

Se pronuncia más o menos «e-ún-du».

## Licencia

Ehundu es software libre bajo licencia MIT. Consulta el fichero `LICENSE`.

---

## In English

Ehundu is a static site generator written in PHP, designed to build sites both
from the command line and from within a web process on ordinary shared PHP
hosting. It is under active development and not yet usable.

Its project format is deliberately in Spanish (folder names, front matter
fields), as a commitment to Spanish-speaking web developers. Documentation will
be available in both Spanish and English.

The name *ehundu* is Basque for "to weave".
