# CLAUDE.md

Contexto para Claude Code. Léelo entero antes de escribir código.

## Qué es Ehundu

Un generador de sitios estáticos en PHP, al estilo de Eleventy y Lume, pensado
para compilar también dentro de un proceso web y no solo desde la consola.
Compila una carpeta de contenido y plantillas a HTML. Licencia MIT.

La orden de consola es una forma de usarlo; la otra es incrustarlo en un
programa (por ejemplo, una herramienta de edición) que llama a la misma API.
Ese es el rasgo que lo distingue y el criterio para decidir qué entra.

## Documentación

- `docs/formato.md` — **fuente de verdad.** Es el contrato del formato de un
  proyecto: carpetas, ficheros de datos, campos del front matter, URL,
  colecciones, plantillas, Markdown, atajos y multiidioma. Si el código y el
  formato no coinciden, manda el formato.

## Idioma

- Formato del proyecto (carpetas, campos, ficheros de datos): **español**.
  Decisión cerrada y deliberada: el proyecto defiende el español como idioma
  de trabajo. No lo "corrijas" al inglés.
- Documentación, mensajes de consola, mensajes de error y commits: español.
- Identificadores del código (clases, métodos, variables): **español, sin
  tildes ni eñes** (`Pagina`, `Coleccion`, `$pagina->etiquetas`). Los nombres
  que imponen PHP y las dependencias se quedan como vienen. Nada de híbridos
  tipo `PaginaRenderer`.

## Organización

    src/          el motor, espacio de nombres Ehundu\
    pruebas/      pruebas unitarias, espacio de nombres Ehundu\Pruebas\
    bin/ehundu    la orden de consola
    recursos/     plantillas que trae el motor (los atajos incluidos)
    docs/         documentación: el formato (formato.md), la previsualización,
                  la compilación incremental y el despliegue

Las clases de prueba llevan el sufijo `Prueba` y los métodos el atributo
`#[Test]`, para que se llamen en español (`fallaSiLaCarpetaNoExiste`). Las
pruebas se ejecutan con `composer pruebas`. Las de FTP necesitan la extensión
`ftp`; si no está activa, se saltan (en Windows:
`php -d extension=ftp vendor/bin/phpunit`).

El trabajo va en la rama `desarrollo`. `main` solo recibe versiones
publicadas, la primera en la 0.1: no hagas commits en `main`.

**Nada privado entra en este repositorio:** ni nombres ni datos de clientes,
ni rutas de máquinas concretas. Los ejemplos de las pruebas y de la
documentación son inventados.

## Reglas que no se rompen

1. **PHP 8.4+** y el mínimo de extensiones. Nada que exija `exec()`,
   `proc_open()` ni binarios externos: el motor tiene que funcionar en un
   alojamiento compartido cualquiera. Si trabajas con PHP 8.5, no uses nada
   exclusivo de 8.5 (operador `|>`, `array_first`/`array_last`, `clone` con
   argumentos, `#[\NoDiscard]`).
2. **Dependencias mínimas.** Previstas: `twig/twig`, `league/commonmark`,
   `symfony/yaml`, y PHPUnit para pruebas. Cualquier otra, justifícala y
   pregunta antes de añadirla.
3. **Todo lo que afecta al resultado vive en la carpeta del proyecto.** El
   motor solo lee de ella y solo escribe en `salida/`.
4. **El Markdown no pasa por Twig.** Los atajos (`[imagen ...]`) son un
   vocabulario cerrado, se resuelven sobre el HTML ya convertido y no admiten
   expresiones ni lógica. Ver §8.1 del formato.
5. **Las imágenes no se procesan en el build.** Se copian tal cual desde
   `publico/`. Nada de GD ni Imagick en la v1.
6. **Sin pipeline de assets.** El CSS y el JS llegan escritos; el motor solo
   los copia o los incrusta cuando una página lo declara.
7. **Nada de código propio por sitio en la v1.** El motor no carga ni ejecuta
   PHP que venga del proyecto que compila.
8. **La orden de consola es una envoltura fina** sobre una API de biblioteca.
   Un programa que incruste el motor llamará a esa misma API desde un proceso
   web, así que el motor no puede depender de nada propio de la consola.
9. **La compilación es una interfaz.** La previsualización ya compila de
   forma incremental y tiene que dar siempre lo mismo que una compilación
   completa (ver `docs/compilacion.md`). Cualquier cosa nueva que una
   plantilla, un atajo o el Markdown puedan leer (un fichero, una colección,
   otra página) tiene que anotarse en el registro de la página, y las pruebas
   de equivalencia tienen que cubrirla. Los lotes llegarán más adelante: no
   mezcles decidir qué se rehace con rehacerlo, ni al compilar ni al
   desplegar.
10. **Errores claros y en español**, con fichero y línea cuando se pueda. Un
    valor que no vale en un campo reservado avisa; no rompe el build salvo
    que sea imprescindible (§4 del formato).

## Fuera de la v1

No implementes nada de esto aunque parezca fácil: catálogo de componentes con
parámetros, tokens de estilo, generación de imágenes, multiidioma (solo
reservar lo que indica §15.5 del formato), paginación, procesadores sobre el
HTML generado, pasos posteriores al build, búsqueda.

El despliegue (carpeta, FTP, SFTP, S3) está en el motor: ver
`docs/despliegue.md`. Nada de APIs propias de un proveedor ni de pasos
después de desplegar.

## Orden de trabajo de la v1

1. Esqueleto: `composer.json`, autoload, PHPUnit y la orden `ehundu` con
   `compilar` como único comando.
2. Lectura del proyecto: `sitio.yml`, `datos/`, front matter con los alias
   ingleses aceptados, cascada de `_datos.yml`.
3. URL: derivada de la ruta, patrón con filtro `slug`, `url: false` para
   fragmentos.
4. Colecciones por etiqueta con los filtros del §6.
5. Twig: plantillas, parciales, variables y funciones del §7.
6. Markdown: figuras desde imágenes, enlaces externos, atajos `imagen`,
   `video` y `archivo`.
7. Copia de `publico/`, CSS por página, `sitemap.xml` y `feed.xml`.

Cada paso con sus pruebas antes de pasar al siguiente.

## Forma de trabajar

- Pasos pequeños y verificables. Ejecuta las pruebas tú mismo.
- Si al implementar algo el formato resulta ambiguo, incompleto o
  contradictorio, **para y pregunta**. No lo resuelvas por tu cuenta en el
  código: la decisión se toma, se anota en `docs/formato.md` y luego se
  implementa.
- Si una decisión de diseño no está en la documentación, propón opciones con
  sus consecuencias en lugar de elegir en silencio.
- Las explicaciones, mejor en prosa que en listas de viñetas.
