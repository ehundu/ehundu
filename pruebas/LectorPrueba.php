<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\ErrorDeProyecto;
use Ehundu\Lector;
use Ehundu\Lectura;
use Ehundu\Pagina;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LectorPrueba extends TestCase
{
    use CarpetaTemporal;

    #[Test]
    public function fallaSinSitioYml(): void
    {
        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('Falta sitio.yml en la raíz del proyecto');

        $this->leer();
    }

    #[Test]
    public function fallaSinNombre(): void
    {
        $this->crearFichero('sitio.yml', "url: https://ejemplo.com\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml: Falta el campo «nombre»');

        $this->leer();
    }

    #[Test]
    public function fallaSinUrl(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml: Falta el campo «url»');

        $this->leer();
    }

    #[Test]
    public function fallaSiLaUrlNoEsCompleta(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: www.ejemplo.com\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml:2: «url» tiene que ser la dirección completa del sitio, con http:// o https://');

        $this->leer();
    }

    #[Test]
    public function leeElSitioSinLaBarraFinalNiElDespliegue(): void
    {
        $this->crearFichero('sitio.yml', <<<'YAML'
            nombre: Librería La Esquina
            url: https://www.ejemplo.com/
            idioma: es
            despliegue:
              destino: sftp
              servidor: ftp.ejemplo.com
            YAML);

        $sitio = $this->leer()->sitio;

        self::assertSame('Librería La Esquina', $sitio->nombre);
        self::assertSame('https://www.ejemplo.com', $sitio->url);
        self::assertSame(
            [
                'nombre' => 'Librería La Esquina',
                'url' => 'https://www.ejemplo.com',
                'idioma' => 'es',
                'idiomas' => [['codigo' => 'es', 'nombre' => 'es', 'url' => '/']],
            ],
            $sitio->campos,
        );
    }

    #[Test]
    public function unSitioSinIdiomaEstaEnCastellano(): void
    {
        $this->crearSitioMinimo();

        $sitio = $this->leer()->sitio;

        self::assertSame('es', $sitio->campos['idioma']);
        self::assertSame(['es'], $sitio->idiomas->codigos());
        self::assertFalse($sitio->idiomas->declarados);
    }

    #[Test]
    public function unIdiomaQueNoEsUnCodigoAvisaYSeUsaElCastellano(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidioma: es-ES\n");
        $this->crearFichero('contenido/.gitkeep', '');

        $lectura = $this->leer();

        self::assertSame('es', $lectura->sitio->campos['idioma']);
        self::assertSame(
            ['sitio.yml:3: «idioma» tiene que ser el código del idioma, en dos o tres letras minúsculas, como es, eu o en; se usa es'],
            $this->avisos($lectura),
        );
    }

    #[Test]
    public function leeLosIdiomasDelSitio(): void
    {
        $this->crearFichero('sitio.yml', <<<'YAML'
            nombre: Prueba
            url: https://ejemplo.com
            idiomas:
              - codigo: es
                nombre: Castellano
              - codigo: eu
                nombre: Euskara
              - codigo: en
            YAML);
        $this->crearFichero('contenido/.gitkeep', '');

        $lectura = $this->leer();
        $idiomas = $lectura->sitio->idiomas;

        self::assertTrue($idiomas->declarados);
        self::assertSame(['es', 'eu', 'en'], $idiomas->codigos());
        self::assertSame('es', $idiomas->predeterminado());
        self::assertSame(['', '/eu', '/en'], array_map($idiomas->prefijo(...), $idiomas->codigos()));
        self::assertSame('es', $lectura->sitio->campos['idioma']);
        self::assertSame([
            ['codigo' => 'es', 'nombre' => 'Castellano', 'url' => '/'],
            ['codigo' => 'eu', 'nombre' => 'Euskara', 'url' => '/eu/'],
            ['codigo' => 'en', 'nombre' => 'en', 'url' => '/en/'],
        ], $lectura->sitio->campos['idiomas']);
        self::assertSame([], $this->avisos($lectura));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function idiomasMalEscritos(): iterable
    {
        yield 'no es una lista' => ["idiomas: es\n", 'sitio.yml:3: «idiomas» tiene que ser una lista con los idiomas del sitio'];
        yield 'vacía' => ["idiomas: []\n", 'sitio.yml:3: «idiomas» tiene que ser una lista con los idiomas del sitio'];
        yield 'sin código' => ["idiomas:\n  - nombre: Castellano\n", 'sitio.yml:3: El idioma 1 de «idiomas» necesita un «codigo»'];
        yield 'código raro' => ["idiomas:\n  - codigo: es\n  - codigo: es-ES\n", 'sitio.yml:3: El idioma 2 de «idiomas» necesita un «codigo»'];
        yield 'sueltos' => ["idiomas: [es, eu]\n", 'sitio.yml:3: El idioma 1 de «idiomas» necesita un «codigo»'];
        yield 'repetido' => ["idiomas:\n  - codigo: es\n  - codigo: es\n", 'sitio.yml:3: El idioma «es» está dos veces en «idiomas»'];
    }

    #[Test]
    #[DataProvider('idiomasMalEscritos')]
    public function unosIdiomasMalEscritosDetienenLaLectura(string $idiomas, string $error): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n{$idiomas}");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage($error);

        $this->leer();
    }

    #[Test]
    public function avisaDeLoQueSobraEnLosIdiomas(): void
    {
        $this->crearFichero('sitio.yml', <<<'YAML'
            nombre: Prueba
            url: https://ejemplo.com
            idioma: eu
            idiomas:
              - codigo: es
                nombre: [Castellano]
              - codigo: eu
                predeterminado: sí
                prefijo: /euskaraz
                bandera: eu.svg
              - codigo: fr
            YAML);
        $this->crearFichero('contenido/.gitkeep', '');

        $lectura = $this->leer();

        self::assertSame('es', $lectura->sitio->campos['idioma']);
        self::assertSame('es', $lectura->sitio->campos['idiomas'][0]['nombre']);
        self::assertSame([
            'sitio.yml:4: «idiomas»: el nombre de «es» tiene que ser un texto; se usa el código',
            'sitio.yml:4: «idiomas»: eu no necesita «predeterminado»: el predeterminado es el primero de la lista',
            'sitio.yml:4: «idiomas»: eu no necesita «prefijo»: el de cada idioma es siempre /eu/',
            'sitio.yml:4: «idiomas»: eu no admite «bandera»; se ignora',
            'sitio.yml:3: «idioma» no hace falta con «idiomas», y no coincide con el primero de la lista, que es el predeterminado; se usa es',
            'sitio.yml: Ehundu no trae los nombres de los meses y los días en «fr»; fecha() los escribe en inglés',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function cadaPaginaTieneElIdiomaDeSuNombre(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/index.md', "---\ntitulo: Inicio\n---\n");
        $this->crearFichero('contenido/index.eu.md', "---\ntitulo: Hasiera\n---\n");
        $this->crearFichero('contenido/instalaciones.md', "---\ntitulo: Instalaciones\n---\n");
        $this->crearFichero('contenido/instalaciones.eu.md', "---\ntitulo: Instalazioak\nurl: /instalazioak/\n---\n");
        $this->crearFichero('contenido/blog/_datos.yml', "url: \"/blog/{{ titulo|slug }}/\"\n");
        $this->crearFichero('contenido/blog/uno.eu.md', "---\ntitulo: Lehena\n---\n");
        $this->crearFichero('contenido/contacto.es.twig', "---\ntitulo: Contacto\n---\n");
        $this->crearFichero('contenido/404.eu.md', "---\ntitulo: Ez dago\nurl: /404.html\n---\n");

        $lectura = $this->leer();
        $porRuta = array_column(array_map(fn (Pagina $pagina) => [$pagina->ruta, [$pagina->idioma, $pagina->url, $pagina->clave()]], $lectura->paginas), 1, 0);

        self::assertSame([
            '404.eu.md' => ['eu', '/eu/404.html', '404'],
            'blog/uno.eu.md' => ['eu', '/eu/blog/lehena/', 'blog/uno'],
            'contacto.es.twig' => ['es', '/contacto/', 'contacto'],
            'index.eu.md' => ['eu', '/eu/', 'index'],
            'index.md' => ['es', '/', 'index'],
            'instalaciones.eu.md' => ['eu', '/eu/instalazioak/', 'instalaciones'],
            'instalaciones.md' => ['es', '/instalaciones/', 'instalaciones'],
        ], $porRuta);
        self::assertSame([], $this->avisos($lectura));
    }

    #[Test]
    public function avisaSiLaUrlYaLlevaElPrefijoDeSuIdioma(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/instalaciones.eu.md', "---\ntitulo: Instalazioak\nurl: /eu/instalazioak/\n---\n");
        $this->crearFichero('contenido/eu/kontaktua.eu.md', "---\ntitulo: Kontaktua\n---\n");

        $lectura = $this->leer();

        self::assertSame(['/eu/eu/kontaktua/', '/eu/eu/instalazioak/'], array_map(fn (Pagina $pagina) => $pagina->url, $lectura->paginas));
        self::assertSame([
            'contenido/eu/kontaktua.eu.md: La URL /eu/kontaktua/ ya empieza por /eu/, el prefijo de su idioma, que el motor pone solo (formato §15.3); queda /eu/eu/kontaktua/',
            'contenido/instalaciones.eu.md: La URL /eu/instalazioak/ ya empieza por /eu/, el prefijo de su idioma, que el motor pone solo (formato §15.3); queda /eu/eu/instalazioak/',
        ], $this->avisos($lectura));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mismaPaginaEnElMismoIdioma(): iterable
    {
        yield 'con y sin el código del predeterminado' => [
            'contacto.es.md',
            'contenido/contacto.md: contenido/contacto.es.md y contenido/contacto.md son la misma página en el mismo idioma (es); sobra uno de los dos (formato §15.2)',
        ];
        yield 'en Markdown y en Twig' => [
            'contacto.twig',
            'contenido/contacto.twig: contenido/contacto.md y contenido/contacto.twig son la misma página en el mismo idioma (es); sobra uno de los dos (formato §15.2)',
        ];
    }

    #[Test]
    #[DataProvider('mismaPaginaEnElMismoIdioma')]
    public function dosFicherosDeLaMismaPaginaEnElMismoIdiomaDetienenLaLectura(string $otro, string $error): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\n---\n");
        $this->crearFichero("contenido/{$otro}", "---\ntitulo: Contacto\nurl: /otro/\n---\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage($error);

        $this->leer();
    }

    #[Test]
    public function laCascadaDeCadaIdiomaVaEncimaDeLaDeSuCarpeta(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/_datos.yml', "plantilla: pagina\nseccion: raiz\netiquetas: [todas]\n");
        $this->crearFichero('contenido/_datos.eu.yml', "plantilla: orria\nseccion: erroa\netiquetas: [euskaraz]\n");
        $this->crearFichero('contenido/blog/_datos.yml', "plantilla: articulo\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\n---\n");
        $this->crearFichero('contenido/blog/uno.eu.md', "---\ntitulo: Bat\n---\n");
        $this->crearFichero('contenido/contacto.eu.md', "---\ntitulo: Kontaktua\n---\n");

        $campos = array_column(array_map(fn (Pagina $pagina) => [$pagina->ruta, $pagina->campos], $this->leer()->paginas), 1, 0);

        self::assertSame(['plantilla' => 'articulo', 'seccion' => 'raiz', 'etiquetas' => ['todas'], 'titulo' => 'Uno'], $campos['blog/uno.md']);
        self::assertSame(['plantilla' => 'articulo', 'seccion' => 'erroa', 'etiquetas' => ['todas', 'euskaraz'], 'titulo' => 'Bat'], $campos['blog/uno.eu.md']);
        self::assertSame(['plantilla' => 'orria', 'seccion' => 'erroa', 'etiquetas' => ['todas', 'euskaraz'], 'titulo' => 'Kontaktua'], $campos['contacto.eu.md']);
    }

    #[Test]
    public function avisaDeLosDatosDeCarpetaQueNoSonDeUnIdiomaDelSitio(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/_datos.fr.yml', 'plantilla: page');
        $this->crearFichero('contenido/blog/_datos.v2.yml', 'plantilla: otra');
        $this->crearFichero('contenido/blog/_datos.antiguo.eu.yml', 'plantilla: otra');
        $this->crearFichero('contenido/blog/_datos.eu.yml', 'plantilla: orria');
        $this->crearFichero('contenido/blog/_notas.es.txt', 'no es de datos');

        self::assertSame([
            'contenido/_datos.fr.yml: El punto en el nombre está reservado para el idioma, y «fr» no es un idioma del sitio (formato §15.2); este fichero no se lee',
            'contenido/blog/_datos.antiguo.eu.yml: El punto en el nombre está reservado para el idioma (formato §15.2); este fichero no se lee',
            'contenido/blog/_datos.v2.yml: El punto en el nombre está reservado para el idioma (formato §15.2); este fichero no se lee',
        ], $this->avisos($this->leer()));
    }

    #[Test]
    public function losCamposComunesDeUnaPaginaVanEntreLaCascadaYSuFrontMatter(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/servicios/_datos.yml', "etiquetas: [servicios]\nicono: generico.svg\n");
        $this->crearFichero('contenido/servicios/comedor.yml', "icono: comedor.svg\nimagen: img/comedor.jpg\norden: 2\netiquetas: [destacados]\nidioma: eu\n");
        $this->crearFichero('contenido/servicios/comedor.md', "---\ntitulo: Comedor\n---\n");
        $this->crearFichero('contenido/servicios/comedor.eu.md', "---\ntitulo: Jangela\nimagen: img/jangela.jpg\n---\n");
        $this->crearFichero('contenido/servicios/suelto.yml', 'se: copia');

        $lectura = $this->leer();
        $campos = array_column(array_map(fn (Pagina $pagina) => [$pagina->ruta, $pagina->campos], $lectura->paginas), 1, 0);

        self::assertSame(
            ['etiquetas' => ['servicios', 'destacados'], 'icono' => 'comedor.svg', 'imagen' => 'img/comedor.jpg', 'orden' => 2, 'titulo' => 'Comedor'],
            $campos['servicios/comedor.md'],
        );
        self::assertSame(
            ['etiquetas' => ['servicios', 'destacados'], 'icono' => 'comedor.svg', 'imagen' => 'img/jangela.jpg', 'orden' => 2, 'titulo' => 'Jangela'],
            $campos['servicios/comedor.eu.md'],
        );
        self::assertSame(['servicios/suelto.yml'], $lectura->ficheros);
        self::assertSame(
            ['contenido/servicios/comedor.yml:5: «idioma» sale del nombre del fichero (formato §15.2); este campo se ignora'],
            $this->avisos($lectura),
        );
    }

    #[Test]
    public function elIdiomaNoSeEscribeEnElFrontMatter(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('contenido/contacto.md', "---\ntitulo: Contacto\nidioma: eu\n---\n");

        $lectura = $this->leer();

        self::assertSame('es', $lectura->paginas[0]->idioma);
        self::assertSame(['titulo' => 'Contacto'], $lectura->paginas[0]->campos);
        self::assertSame(
            ['contenido/contacto.md:3: «idioma» sale del nombre del fichero (formato §15.2); este campo se ignora'],
            $this->avisos($lectura),
        );
    }

    #[Test]
    public function leeLosDatosDeCadaIdiomaAparte(): void
    {
        $this->crearSitioEnDosIdiomas();
        $this->crearFichero('datos/cliente.yml', "nombre: Ercilla\ntelefono: '944000000'\nhorario: De lunes a viernes\n");
        $this->crearFichero('datos/cliente.eu.yml', "horario: Astelehenetik ostiralera\n");
        $this->crearFichero('datos/menus.yml', "principal:\n  - Inicio\n  - Blog\npie: [Aviso legal]\n");
        $this->crearFichero('datos/menus.eu.yml', "principal:\n  - Hasiera\n");
        $this->crearFichero('datos/textos.eu.json', '{"saltar": "Joan edukira"}');
        $this->crearFichero('datos/lista.yml', "- uno\n- dos\n");
        $this->crearFichero('datos/lista.eu.yml', "- bat\n");

        $lectura = $this->leer();

        self::assertSame(['cliente', 'lista', 'menus'], array_keys($lectura->datos));
        self::assertSame($lectura->datos, $lectura->datosDe('es'));
        self::assertSame([
            'cliente' => ['nombre' => 'Ercilla', 'telefono' => '944000000', 'horario' => 'Astelehenetik ostiralera'],
            'lista' => ['bat'],
            'menus' => ['principal' => ['Hasiera'], 'pie' => ['Aviso legal']],
            'textos' => ['saltar' => 'Joan edukira'],
        ], $lectura->datosDe('eu'));
        self::assertSame([], $this->avisos($lectura));
    }

    #[Test]
    public function laZonaHorariaEsLaDelSitioOUtc(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\nzonaHoraria: Europe/Madrid\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2025-03-18\n---\n");

        $lectura = $this->leer();

        self::assertSame('Europe/Madrid', $lectura->sitio->zonaHoraria->getName());
        self::assertSame('2025-03-18 00:00:00 Europe/Madrid', $lectura->paginas[0]->campos['fecha']->format('Y-m-d H:i:s e'));
    }

    #[Test]
    public function sinZonaHorariaEsUtc(): void
    {
        $this->crearSitioMinimo();

        self::assertSame('UTC', $this->leer()->sitio->zonaHoraria->getName());
    }

    #[Test]
    public function fallaConUnaZonaHorariaQueNoExiste(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Librería La Esquina\nurl: https://www.ejemplo.com\nzonaHoraria: Madrid\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('sitio.yml:3: «zonaHoraria» no es una zona horaria válida');

        $this->leer();
    }

    #[Test]
    public function leeLosDatosPorNombre(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/cliente.yml', "nombre: La Esquina\ntelefonos: [900000000]");
        $this->crearFichero('datos/menus.json', '[{"texto": "Inicio", "url": "/"}]');

        $lectura = $this->leer();

        self::assertSame(
            ['cliente' => ['nombre' => 'La Esquina', 'telefonos' => [900000000]], 'menus' => [['texto' => 'Inicio', 'url' => '/']]],
            $lectura->datos,
        );
        self::assertSame([], $lectura->avisos);
    }

    #[Test]
    public function avisaDeLoQueNoSeLeeEnDatos(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/images.js', 'export default [];');
        $this->crearFichero('datos/menus.eu.yml', 'a: 1');
        $this->crearFichero('datos/menus.v2.yml', 'a: 1');
        $this->crearFichero('datos/menus/principal.yml', 'a: 1');
        $this->crearFichero('datos/.gitkeep', '');

        $lectura = $this->leer();

        self::assertSame([], $lectura->datos);
        self::assertSame([], $lectura->datosPorIdioma);
        self::assertSame([
            'datos/images.js: En datos/ solo se leen ficheros .yml y .json; este se ignora',
            'datos/menus: Las subcarpetas de datos/ no se leen',
            'datos/menus.eu.yml: El punto en el nombre está reservado para el idioma, y «eu» no es un idioma del sitio (formato §15.2); este fichero no se lee',
            'datos/menus.v2.yml: El punto en el nombre está reservado para el idioma (formato §15.2); este fichero no se lee',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function enUnSitioDeUnSoloIdiomaSusDatosConCodigoTambienValen(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/menus.yml', 'principal: [Inicio]');
        $this->crearFichero('datos/menus.es.yml', 'principal: [Portada]');

        $lectura = $this->leer();

        self::assertSame(['menus' => ['principal' => ['Portada']]], $lectura->datosDe('es'));
        self::assertSame([], $this->avisos($lectura));
    }

    #[Test]
    public function siUnDatoSaleDeYmlYDeJsonGanaElYml(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/cliente.json', '{"nombre": "json"}');
        $this->crearFichero('datos/cliente.yml', 'nombre: yml');

        $lectura = $this->leer();

        self::assertSame(['cliente' => ['nombre' => 'yml']], $lectura->datos);
        self::assertSame(
            ['datos/cliente.json: «datos.cliente» sale también de datos/cliente.yml; este fichero se ignora'],
            $this->avisos($lectura),
        );
    }

    #[Test]
    public function explicaUnJsonMalEscrito(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/cliente.json', '{"nombre": "La Esquina",}');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('datos/cliente.json: El JSON no es válido: hay un error de sintaxis');

        $this->leer();
    }

    #[Test]
    public function leeSoloLasPaginasYLasDevuelveEnOrden(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', '# Inicio');
        $this->crearFichero('contenido/contacto.twig', '<h1>Contacto</h1>');
        $this->crearFichero('contenido/blog/b.md', 'B');
        $this->crearFichero('contenido/blog/a.md', 'A');
        $this->crearFichero('contenido/blog/_datos.yml', 'etiquetas: [blog]');
        $this->crearFichero('contenido/img/foto.jpg', 'jpg');
        $this->crearFichero('contenido/.borrador.md', 'oculto');
        $this->crearFichero('contenido/.git/config', 'oculto');

        $paginas = $this->leer()->paginas;

        self::assertSame(
            ['blog/a.md', 'blog/b.md', 'contacto.twig', 'index.md'],
            array_map(fn (Pagina $pagina) => $pagina->ruta, $paginas),
        );
        self::assertSame(['md', 'md', 'twig', 'md'], array_map(fn (Pagina $pagina) => $pagina->formato, $paginas));
    }

    #[Test]
    public function losDemasFicherosDeContenidoSeCopianSalvoLosReservadosYLosDeOtrosGeneradores(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/blog/foto.jpg', 'jpg');
        $this->crearFichero('contenido/blog/_notas.txt', 'privado');
        $this->crearFichero('contenido/index.njk', '{{ content }}');
        $this->crearFichero('contenido/blog/blog.11tydata.js', 'export default {};');
        $this->crearFichero('contenido/datos.11tydata.json', '{}');
        $this->crearFichero('contenido/lista.liquid', '{{ x }}');

        $lectura = $this->leer();

        self::assertSame(['blog/foto.jpg'], $lectura->ficheros);
        self::assertSame([
            'contenido/datos.11tydata.json: Es una plantilla o unos datos de otro generador; no se copia a salida/',
            'contenido/index.njk: Es una plantilla o unos datos de otro generador; no se copia a salida/',
            'contenido/lista.liquid: Es una plantilla o unos datos de otro generador; no se copia a salida/',
            'contenido/blog/blog.11tydata.js: Es una plantilla o unos datos de otro generador; no se copia a salida/',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function combinaLaCascadaConElFrontMatter(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/blog/_datos.yml', "plantilla: articulo\ntags: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"");
        $this->crearFichero('contenido/blog/novelas-de-otono.md', <<<'MD'
            ---
            title: Diez novelas para leer en otoño
            date: 2025-03-18
            tags: ["novela", "otono", "recomendaciones"]
            imgmain: blog/otono.jpg
            ---

            Llega el otoño…
            MD);

        $pagina = $this->leer()->paginas[0];

        self::assertSame('Diez novelas para leer en otoño', $pagina->campos['titulo']);
        self::assertSame('articulo', $pagina->campos['plantilla']);
        self::assertSame('/blog/{{ titulo|slug }}/', $pagina->campos['url']);
        self::assertSame('/blog/diez-novelas-para-leer-en-otono/', $pagina->url);
        self::assertSame(['blog', 'novela', 'otono', 'recomendaciones'], $pagina->campos['etiquetas']);
        self::assertSame('blog/otono.jpg', $pagina->campos['imgmain']);
        self::assertSame('2025-03-18', $pagina->campos['fecha']->format('Y-m-d'));
        self::assertSame("\nLlega el otoño…", $pagina->cuerpo);
        self::assertSame(7, $pagina->lineaCuerpo);
    }

    #[Test]
    public function leeFicherosConBomYFinalesDeLineaDeWindows(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', "\u{FEFF}---\r\ntitulo: Inicio\r\n---\r\nCuerpo\r\n");

        $pagina = $this->leer()->paginas[0];

        self::assertSame(['titulo' => 'Inicio'], $pagina->campos);
        self::assertSame("Cuerpo\n", $pagina->cuerpo);
    }

    #[Test]
    public function avisaYSaltaLasPaginasConPuntoEnElNombre(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/contacto.en.md', 'Contact');
        $this->crearFichero('contenido/sitemap.xml.twig', '<urlset/>');
        $this->crearFichero('contenido/guia.v2.md', 'Guía');
        $this->crearFichero('contenido/guia.antigua.es.md', 'Guía');

        $lectura = $this->leer();

        self::assertSame([], $lectura->paginas);
        self::assertSame([
            'contenido/contacto.en.md: El punto en el nombre está reservado para el idioma, y «en» no es un idioma del sitio (formato §15.2); este fichero no se compila',
            'contenido/guia.antigua.es.md: El punto en el nombre está reservado para el idioma (formato §15.2); este fichero no se compila',
            'contenido/guia.v2.md: El punto en el nombre está reservado para el idioma (formato §15.2); este fichero no se compila',
            'contenido/sitemap.xml.twig: El punto en el nombre está reservado para el idioma, y «xml» no es un idioma del sitio (formato §15.2); este fichero no se compila',
        ], $this->avisos($lectura));
    }

    #[Test]
    public function avisaSiUnaPaginaHtmlNoTieneTitulo(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/index.md', 'Inicio');
        $this->crearFichero('contenido/vacio.md', "---\ntitulo: ''\n---\n");
        $this->crearFichero('contenido/ingles.md', "---\ntitle: Contact\n---\n");
        $this->crearFichero('contenido/clinicas/_datos.yml', 'url: false');
        $this->crearFichero('contenido/clinicas/norte.md', 'Un fragmento no lo necesita.');
        $this->crearFichero('contenido/robots.twig', "---\nurl: /robots.txt\nplantilla: false\n---\nNi un robots.txt.");

        self::assertSame([
            'contenido/index.md: Falta «titulo», el título de la página',
            'contenido/vacio.md: Falta «titulo», el título de la página',
        ], $this->avisos($this->leer()));
    }

    #[Test]
    public function lasPaginasQueEmpiezanPorGuionBajoSeCompilanComoLasDemas(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/_portada/index.md', "---\ntitulo: Inicio\nurl: /\n---\n");
        $this->crearFichero('contenido/_fragmento.md', "---\nurl: false\n---\n");

        self::assertSame(
            ['_fragmento.md', '_portada/index.md'],
            array_map(fn (Pagina $pagina) => $pagina->ruta, $this->leer()->paginas),
        );
    }

    #[Test]
    public function losAvisosDeUnaPaginaLlevanSuRutaYLinea(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2025-02-31\n---\n");

        self::assertSame(
            ['contenido/blog/uno.md:3: «fecha» no es una fecha posible: 2025-02-31'],
            $this->avisos($this->leer()),
        );
    }

    /**
     * Fechas que YAML no llega a leer: sin comillas, Symfony falla en lugar
     * de dar otra fecha, como con el 31 de febrero (decisión 88).
     *
     * @return iterable<string, array{string}>
     */
    public static function fechasQueYamlNoPuedeLeer(): iterable
    {
        yield 'mes 13' => ['2026-13-01'];
        yield 'día 32' => ['2026-01-32'];
    }

    #[Test]
    #[DataProvider('fechasQueYamlNoPuedeLeer')]
    public function unaFechaQueYamlNoPuedeLeerEnElFrontMatterAvisaYNoDetieneLaLectura(string $fecha): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/a.md', "---\ntitulo: A\nfecha: {$fecha}\npublicar: {$fecha}\n---\n");
        $this->crearFichero('contenido/b.md', "---\ntitulo: B\nfecha: \"{$fecha}\"\npublicar: '{$fecha}'\n---\n");
        $this->crearFichero('contenido/c.md', "---\ntitulo: C\ndate: {$fecha} # alias y comentario\n---\n");

        $lectura = $this->leer();

        self::assertSame(
            [['titulo' => 'A'], ['titulo' => 'B'], ['titulo' => 'C']],
            array_map(fn (Pagina $pagina) => $pagina->campos, $lectura->paginas),
        );
        self::assertSame([
            "contenido/a.md:3: «fecha» no es una fecha posible: {$fecha}",
            "contenido/a.md:4: «publicar» no es una fecha posible: {$fecha}",
            "contenido/b.md:3: «fecha» no es una fecha posible: {$fecha}",
            "contenido/b.md:4: «publicar» no es una fecha posible: {$fecha}",
            "contenido/c.md:3: «date» no es una fecha posible: {$fecha}",
        ], $this->avisos($lectura));
    }

    #[Test]
    #[DataProvider('fechasQueYamlNoPuedeLeer')]
    public function unaFechaQueYamlNoPuedeLeerEnUnDatosYmlAvisaYNoDetieneLaLectura(string $fecha): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/_datos.yml', "fecha: {$fecha}\npublicar: '{$fecha}'\n");
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nfecha: \"{$fecha}\"\npublicar: {$fecha}\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\n---\n");

        $lectura = $this->leer();

        self::assertSame(['etiquetas' => ['blog'], 'titulo' => 'Uno'], $lectura->paginas[0]->campos);
        self::assertSame([
            "contenido/_datos.yml:1: «fecha» no es una fecha posible: {$fecha}",
            "contenido/_datos.yml:2: «publicar» no es una fecha posible: {$fecha}",
            "contenido/blog/_datos.yml:2: «fecha» no es una fecha posible: {$fecha}",
            "contenido/blog/_datos.yml:3: «publicar» no es una fecha posible: {$fecha}",
        ], $this->avisos($lectura));
    }

    #[Test]
    #[DataProvider('fechasQueYamlNoPuedeLeer')]
    public function unaFechaQueYamlNoPuedeLeerEnLosCamposComunesDeUnaPaginaAvisaYNoDetieneLaLectura(string $fecha): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/servicios.yml', "fecha: {$fecha}\npublicar: \"{$fecha}\"\n");
        $this->crearFichero('contenido/servicios.md', "---\ntitulo: Servicios\n---\n");

        $lectura = $this->leer();

        self::assertSame(['titulo' => 'Servicios'], $lectura->paginas[0]->campos);
        self::assertSame([
            "contenido/servicios.yml:1: «fecha» no es una fecha posible: {$fecha}",
            "contenido/servicios.yml:2: «publicar» no es una fecha posible: {$fecha}",
        ], $this->avisos($lectura));
    }

    #[Test]
    #[DataProvider('fechasQueYamlNoPuedeLeer')]
    public function unaFechaQueYamlNoPuedeLeerEnDatosYEnCamposPropiosLlegaComoTexto(string $fecha): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('datos/agenda.yml', "inicio: {$fecha}\nfechas: [\n  2026-05-01,\n  {$fecha}\n]\n");
        $this->crearFichero('contenido/a.md', "---\ntitulo: A\nevento: {$fecha}\n---\n");

        $lectura = $this->leer();

        self::assertSame($fecha, $lectura->datos['agenda']['inicio']);
        self::assertSame($fecha, $lectura->datos['agenda']['fechas'][1]);
        self::assertSame(['titulo' => 'A', 'evento' => $fecha], $lectura->paginas[0]->campos);
        self::assertSame([], $this->avisos($lectura));
    }

    #[Test]
    public function unFrontMatterMalEscritoDetieneLaLectura(): void
    {
        $this->crearSitioMinimo();
        $this->crearFichero('contenido/libros/novedades.md', "---\ntitulo: Novedades: otoño\n---\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/libros/novedades.md:2: El YAML no es válido: hay dos puntos');

        $this->leer();
    }

    #[Test]
    public function avisaSiNoHayCarpetaDeContenido(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");

        $lectura = $this->leer();

        self::assertSame([], $lectura->paginas);
        self::assertSame(['No hay carpeta contenido/: no se genera ninguna página'], $this->avisos($lectura));
    }

    private function crearSitioEnDosIdiomas(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n  - codigo: eu\n");
        $this->crearFichero('contenido/.gitkeep', '');
    }

    private function leer(): Lectura
    {
        return (new Lector())->leer(Proyecto::abrir($this->carpetaTemporal()));
    }

    /**
     * @return list<string>
     */
    private function avisos(Lectura $lectura): array
    {
        return array_map(strval(...), $lectura->avisos);
    }
}
