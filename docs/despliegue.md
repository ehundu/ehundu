# Despliegue

    ehundu desplegar [carpeta] [--simular] [--todo]

Compila el proyecto entero, como `ehundu compilar`, y si no hay errores lo
publica en el destino de la sección `despliegue` de `sitio.yml`: una carpeta,
un servidor FTP o SFTP, o un cubo de S3 o de un servicio compatible. Los
campos de cada destino y los secretos están en el §2 del formato.

Con `--simular` dice qué subiría y qué borraría, sin tocar el destino; sí se
conecta, para saber qué hay allí. Con `--todo` lo sube todo aunque no haya
cambiado, que es lo que hace falta si alguien ha tocado a mano los ficheros
del servidor.

## Qué se sube y qué se borra

Ehundu deja en el destino un fichero, `.ehundu.json`, con lo que ha subido:
la ruta, el MD5 y el tamaño de cada fichero, y nada más. Al desplegar lo lee,
compara con lo que acaba de compilar y sube solo lo nuevo o lo que ha
cambiado. El manifiesto vive en el propio destino para que cualquier máquina
que despliegue el sitio vea lo mismo, y para que el motor no tenga que
escribir nada en el proyecto fuera de `salida/`. Se puede leer desde la web,
como cualquier fichero del sitio: dice qué ficheros hay, también los que no
enlaza ninguna página.

Se borra solo lo que subió Ehundu y ya no se genera. Lo que haya en el
destino y no esté en el manifiesto no se toca nunca: el fichero con el que se
verifica un dominio, el `.well-known` de un certificado, un formulario en PHP.
La contrapartida es que, la primera vez que se despliega sobre un sitio que ya
existía, lo que dejó el sitio anterior se queda allí hasta que alguien lo
quite. Las carpetas que se quedan sin nada de Ehundu se intentan quitar; si
tienen algo más, se quedan.

Si el destino no tiene manifiesto, se sube todo y no se borra nada. Si lo
tiene pero está estropeado, el despliegue se detiene: sin él no se sabe qué
se puede borrar. `--todo` lo rehace.

## En qué orden

Primero se suben los ficheros (CSS, imágenes, documentos), después las
páginas y al final el sitemap y el feed, para que una página nueva no enlace
algo que todavía no está. Los borrados van al final. FTP y SFTP no permiten
cambiarlo todo de golpe, así que durante unos segundos conviven lo nuevo y lo
viejo; este orden hace que lo que se ve en ese rato funcione.

El manifiesto se guarda cada 50 ficheros o cada 15 segundos, y al terminar.
Si un despliegue se corta, el siguiente sigue donde se quedó: lo que ya subió
está anotado. Si falla un fichero concreto, se guarda lo hecho hasta ahí antes
de detenerse.

## Cada destino

**Carpeta.** Una ruta de esta máquina: la raíz de un servidor web local, una
carpeta sincronizada, un disco de red. Si es relativa, se cuenta desde el
proyecto, y no puede estar dentro de él. Se crea si no existe.

**FTP.** Con la extensión `ftp` de PHP, que viene con PHP pero puede estar
desactivada (en Windows se activa en `php.ini` con `extension=ftp`). Usa una
sola conexión y el modo pasivo. Si algo falla, lo repite, hasta dos veces, y
lo deja dicho en un aviso: si la conexión se ha cortado, vuelve a conectar;
si sigue abierta, el servidor ha contestado que no, y eso puede ser pasajero
(vsftpd contesta «Failure reading network stream» cuando una subida le llega
mal, y deja el fichero a medias), así que lo repite una vez y, si vuelve a
decir que no, es un error. Un fichero que se queda a medias no entra en el
manifiesto, así que el siguiente despliegue lo vuelve a subir. Por defecto va cifrado (FTPS explícito);
`cifrado: no` lo desactiva, pero entonces la contraseña viaja a la vista. El
cifrado necesita además la extensión `openssl`, que traen casi todas las
instalaciones de PHP. La extensión cifra pero no comprueba el certificado del
servidor: protege de quien escucha, no de quien se haga pasar por el
servidor. Para eso está SFTP.

**SFTP.** Con phpseclib, que es PHP puro. Se entra con contraseña (`clave`) o
con una clave privada (`clavePrivada`, y `frase` si la lleva). Antes de mandar
nada, se comprueba que el servidor es quien dice ser, con la huella de su
clave: `huella` en `sitio.yml`, tal como la escribe `ssh-keygen -l`. Si
falta, el primer despliegue se detiene y dice cuál es, para que se compruebe
con quien administra el servidor y se anote; si no coincide, se detiene.

**S3.** Cualquier servicio compatible: AWS, Scaleway, Cloudflare R2,
Backblaze, Hetzner. Las peticiones se firman en el propio motor, sin el SDK
de Amazon, y van por HTTPS con lo que trae PHP (la extensión `openssl`). El cubo va en el nombre del
servidor (`cubo.s3.fr-par.scw.cloud`), salvo si lleva puntos o el servidor es
local, que va en la ruta. Cada fichero sube con su tipo MIME y con su MD5,
para que el servicio compruebe que le ha llegado entero. Que el cubo sirva un
sitio web se configura en el servicio; el motor no lo toca.

## Lo que no hace

Nada después de desplegar: ni avisa a otros servicios ni vacía la caché de
una CDN. No hay variables de entorno para los secretos: van en `.secretos.yml`
o los da el programa que incrusta el motor. Y no hay bloqueo: si dos máquinas
despliegan el mismo sitio a la vez, el manifiesto puede quedar mal; se
arregla con `--todo`.

## Para quien incruste el motor

`Desplegador::desplegar()` recibe los secretos directamente, sin fichero, e
informa de cada fichero subido o borrado según avanza. Decidir qué hacer
(`PlanDeDespliegue`) va aparte de hacerlo (`Desplegador::ejecutar()`), así que
un plan se puede ejecutar por partes, en varias peticiones.
