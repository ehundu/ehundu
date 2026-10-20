# Colaborar en Ehundu

Gracias por el interés en colaborar con Ehundu. Es un proyecto de software libre e independiente, sin una empresa grande detrás, y no hay plazos de respuesta garantizados: se contesta en cuanto se puede. Agradecemos la paciencia.

## Antes de empezar

- Esta comunidad se rige por el [código de conducta](CODE_OF_CONDUCT.md). Las críticas constructivas son bienvenidas; las exigencias y el tono condescendiente, no.
- Lo que Ehundu es y lo que no pretende ser está en los [principios del README](README.md#principios) y en el [contrato del formato](docs/formato.md), que es la fuente de verdad. Si el código y el contrato no coinciden, manda el contrato.
- Lo que aportes se publica bajo la licencia MIT del proyecto (fichero `LICENSE`). El logotipo y las ilustraciones de Nidel que hay en `marca/` no están cubiertos por ella; sus condiciones están en [`marca/README.md`](marca/README.md).

## Cómo reportar un error

Si algo no funciona como debería, abre un [issue](https://github.com/ehundu/ehundu/issues) con:

1. Qué querías hacer y qué ha pasado exactamente.
2. El mensaje de error completo (Ehundu indica el fichero y la línea).
3. La versión de Ehundu (`ehundu --version`), la de PHP y tu sistema operativo o alojamiento.
4. Si puedes, un proyecto mínimo que reproduzca el problema.

Un issue que solo diga «no funciona» se queda sin respuesta hasta que tenga estos datos. Los que no respeten el código de conducta pueden cerrarse.

## Fallos de seguridad

No abras un issue público. Escribe a [ehundu@paigar.eu](mailto:ehundu@paigar.eu) contándolo, para poder arreglarlo antes de que se conozca.

## Cómo proponer un cambio

Abre un issue antes de escribir código para algo nuevo. Así se comprueba que encaja, y evitas trabajar en algo que quizá no se pueda integrar. Una propuesta encaja si:

- respeta las [reglas que no se rompen](docs/formato.md) (§12) y no obliga a cambiar el formato de los proyectos que ya existen;
- no es de lo que Ehundu no hace todavía (§13): puede llegar algún día, pero se decide antes;
- mantiene el núcleo ligero: sin dependencias nuevas ni exigencias que un alojamiento compartido corriente no cumpla.

Si el cambio toca el formato (carpetas, campos, URL, plantillas…), primero se decide y se anota, con su motivo, en el registro de decisiones de `docs/formato.md`, y después se implementa. Y si al implementar algo el contrato resulta ambiguo o contradictorio, se para y se pregunta en el issue: no se resuelve en silencio en el código.

Que una propuesta no se acepte no es nada personal. Ehundu quiere seguir siendo pequeño y mantenible, y eso pesa más que cualquier función.

## Cómo proponer código (pull requests)

- **Rama.** Abre el pull request contra `desarrollo`. `main` solo recibe versiones publicadas.
- **Pequeños y de una sola cosa.** Un pull request por cambio, con su issue enlazado.
- **Pruebas.** `composer install` y después `composer pruebas` (PHPUnit; las pruebas están en `pruebas/`, en clases con el sufijo `Prueba`). Todo cambio de comportamiento lleva su prueba, y todas pasan.
- **PHP.** 8.4 como mínimo, y nada exclusivo de 8.5: ni el operador `|>`, ni `array_first` y `array_last`, ni `clone` con argumentos, ni `#[\NoDiscard]`. Nada que exija `exec()`, `proc_open()` ni programas externos: el motor tiene que funcionar en un alojamiento compartido corriente.
- **Dependencias.** Son pocas a propósito (están en `composer.json`). Antes de proponer una nueva, habla en un issue.
- **Español, también en el código.** Los identificadores (clases, métodos, variables) van en español, sin tildes ni eñes (`Pagina`, `Coleccion`); los que imponen PHP y las dependencias se quedan como vienen, y nada de híbridos como `PaginaRenderer`. La documentación, los mensajes de error y los commits van en español. Los errores deben ser claros y, cuando se pueda, decir el fichero y la línea.
