# Previsualización

    ehundu servir [carpeta] [--puerto=8000] [--borradores] [--completo]

Sirve el proyecto en `http://127.0.0.1:8000/` y lo vuelve a compilar cada vez
que cambia algo. El navegador se recarga solo. Es la forma de trabajar día a
día con las plantillas.

## Cómo se comporta

- **No escribe en `salida/`.** El sitio se construye en memoria y se sirve
  desde ahí, así que una sesión de trabajo a medias nunca deja en `salida/`
  algo que luego se despliegue. Para publicar hay que usar `ehundu compilar`.
- **Vigila el proyecto.** Cada medio segundo mira si ha cambiado algún
  fichero, salvo `salida/` y lo que empieza por punto. Compara el tamaño, la
  fecha y, en los ficheros de menos de un mega, el contenido, así que nota
  también dos cambios seguidos del mismo tamaño.
- **Rehace solo lo que cambia.** Cuando cambia algo, vuelve a construir solo
  las páginas a las que afecta, con el mismo resultado que una compilación
  completa; la terminal dice cuántas ha rehecho. Si cambian `sitio.yml` o
  `datos/`, rehace todo. Con `--completo` rehace todo en cada cambio. Los
  detalles están en `compilacion.md`.
- **Recarga el navegador.** En cada página HTML que sirve añade un script que
  pregunta cada segundo a `/__ehundu/estado` si hay una compilación nueva y,
  si la hay, recarga. El script solo va en lo que sirve la previsualización,
  nunca en `salida/`.
- **Enseña los errores en el navegador.** Si la compilación falla, cualquier
  página muestra el error con su fichero y su línea, y se recarga sola cuando
  se arregla. Los ficheros de `publico/` se siguen sirviendo. Los avisos salen
  en la terminal.
- **Con `--borradores`** enseña también los borradores y las páginas con
  `publicar` en el futuro. Sin la opción, enseña lo mismo que se publicaría.
- **Sirve como un alojamiento corriente.** `/blog` redirige a `/blog/`, una
  dirección que no existe devuelve el `404.html` del sitio con código 404, y
  cada fichero lleva su tipo.

## Por qué así

El servidor está escrito en PHP, con las funciones de red que PHP trae de
serie: no necesita extensiones, ni el servidor integrado de PHP, ni lanzar
otros procesos, y funciona igual en Windows, en macOS y en Linux. Solo escucha
en `127.0.0.1`, para que nadie de la red vea el sitio a medias, y solo
responde a `GET` y `HEAD`.

Antes de escuchar comprueba si el puerto ya está ocupado: en Windows, PHP
permite que dos procesos escuchen en el mismo puerto, y el segundo se quedaría
con parte de las peticiones sin que nadie lo notase.

La recarga pregunta a intervalos en lugar de mantener una conexión abierta,
porque el servidor atiende las peticiones de una en una y una conexión que no
se cierra lo bloquearía.
