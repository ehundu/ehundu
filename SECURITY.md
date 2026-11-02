# Política de seguridad

Ehundu compila el proyecto de quien lo usa y, si se le pide, lo sube a un servidor con las contraseñas que se le dan. Por eso un fallo de seguridad puede importar. Si encuentras uno, esta página dice cómo avisar.

## Versiones con soporte

| Versión | Soporte |
|---------|---------|
| 1.x, la última versión menor | Sí: los fallos de seguridad se arreglan en ella. |
| 0.x | No. Actualiza a la 1.x. |

Dentro de la versión mayor 1, actualizar es seguro: el contrato del formato solo crece ([§12, regla 5](docs/formato.md)), así que un proyecto que compilaba sigue compilando.

## Cómo avisar de un fallo

**No abras un issue público**, porque cualquiera lo leería antes de que esté arreglado. Escribe a [ehundu@paigar.eu](mailto:ehundu@paigar.eu) con:

1. Qué has encontrado y qué consecuencias crees que tiene.
2. Cómo reproducirlo. Un proyecto mínimo es lo ideal.
3. La versión de Ehundu (`ehundu --version`), la de PHP y cómo lo ejecutas: la orden, el `.phar` o la biblioteca dentro de otro programa.

Si dudas de si es un fallo de seguridad, escribe igualmente: es mejor un aviso de más.

## Qué pasa después

Es un proyecto pequeño y no hay plazos garantizados, como dice [CONTRIBUTING.md](CONTRIBUTING.md), pero un aviso de seguridad pasa por delante de todo lo demás. Se confirma si es un fallo y, si lo es, se arregla en una versión nueva y se cuenta en sus notas. Si quieres, ahí sale tu nombre. Pedimos que no lo cuentes en público hasta que esa versión esté publicada.

## Qué cuenta como fallo de seguridad

Lo que rompe una promesa del [contrato](docs/formato.md):

- Que el motor **ejecute código que viene del proyecto** que compila (§12, regla 4).
- Que **lea o escriba fuera de la carpeta del proyecto**, salvo lo que el contrato permite (§1): `salida/`, `.ehundu/`, la caché de tipografías de dompdf en la carpeta temporal y, en el despliegue, el destino y el fichero de la clave privada si se da uno.
- Que **enseñe o publique una contraseña** o una clave de `.secretos.yml`, en un mensaje de error, en un volcado o en un fichero de `salida/`.
- Que un despliegue por **SFTP acepte un servidor sin comprobar su huella**.
- Que el HTML que genera a partir de contenido y plantillas bien escritos **abra una inyección de scripts** que no escribió quien hizo el sitio. El HTML escrito a mano dentro del Markdown pasa tal cual, y eso es a propósito.

## Qué no cuenta

- **Los fallos de las dependencias** (Twig, CommonMark, Symfony YAML, dompdf, phpseclib): avisa a sus proyectos. Si afectan a Ehundu, escríbenos también para que actualicemos.
- **Los sitios hechos con Ehundu**, también los que salen de un andamio: lo que un sitio hace bien o mal es de quien lo mantiene. Un fallo en el propio andamio (`ehundu/andamio-…`) sí nos interesa; avisa a la misma dirección.
