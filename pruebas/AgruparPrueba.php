<?php

declare(strict_types=1);

namespace Ehundu\Pruebas;

use Ehundu\Construccion;
use Ehundu\Constructor;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Pruebas\Apoyo\CarpetaTemporal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `agrupar` (formato §6.3): una página que el motor repite por cada valor de
 * un campo de las páginas de una colección, para tener una página por
 * etiqueta sin escribirla a mano.
 */
final class AgruparPrueba extends TestCase
{
    use CarpetaTemporal;

    private const string AHORA = '2026-01-01 12:00:00';

    #[Test]
    public function generaUnaPaginaPorCadaValorAgrupadosPorSlug(): void
    {
        $this->crearSitio();

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertSame(
            ['tema/cultura-pop/index.html', 'tema/ideas/index.html', 'tema/oficio/index.html'],
            $this->paginasGeneradas($construccion),
        );

        // El nombre del grupo es la variante que más páginas usan...
        self::assertSame('<h1>ideas</h1><p>ideas</p><ul><li>Uno</li><li>Dos</li><li>Tres</li></ul>', $construccion->escritos['tema/ideas/index.html']);

        // ...y, a igualdad, la primera en el orden de `orden`.
        self::assertSame('<h1>Cultura pop</h1><p>Cultura pop</p><ul><li>Uno</li><li>Dos</li></ul>', $construccion->escritos['tema/cultura-pop/index.html']);
    }

    #[Test]
    public function elGrupoEnElPatronAdmiteElAliasSlugify(): void
    {
        $this->crearSitio();
        $ruta = $this->carpetaTemporal() . '/contenido/tema.md';
        file_put_contents($ruta, str_replace('{{ grupo|slug }}', '{{ grupo|slugify }}', (string) file_get_contents($ruta)));

        $construccion = $this->construir();

        self::assertSame([], array_map(strval(...), $construccion->avisos));
        self::assertSame(
            ['tema/cultura-pop/index.html', 'tema/ideas/index.html', 'tema/oficio/index.html'],
            $this->paginasGeneradas($construccion),
        );
    }

    #[Test]
    public function laColeccionDeLasPaginasGeneradasHaceElIndiceConSusRecuentos(): void
    {
        $this->crearSitio();

        $construccion = $this->construir();

        self::assertSame('Cultura pop=2;ideas=3;oficio=1;', $construccion->escritos['temas/index.html']);
        self::assertStringContainsString('<loc>https://ejemplo.com/tema/ideas/</loc>', $construccion->escritos['sitemap.xml']);
        self::assertStringNotContainsString('grupo', $construccion->escritos['sitemap.xml']);
        self::assertSame(1 + 3 + 3, $construccion->paginas);
    }

    #[Test]
    public function laPlantillaDeGruposNoGeneraFicheroNiSaleEnLasColecciones(): void
    {
        $this->crearSitio();

        $construccion = $this->construir();

        self::assertArrayNotHasKey('tema/index.html', $construccion->escritos);
        self::assertSame(3, substr_count($construccion->escritos['temas/index.html'], ';'));
        self::assertStringNotContainsString('<loc>https://ejemplo.com/tema/</loc>', $construccion->escritos['sitemap.xml']);
    }

    #[Test]
    public function noHayPaginaParaUnGrupoSinPaginasPublicadasHastaQueLaHay(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/futuro.md', "---\ntitulo: Futuro\nfecha: 2024-05-01\npublicar: 2030-01-01\netiquetas: [novedades]\n---\nAún no.\n");
        $this->crearFichero('contenido/blog/borrador.md', "---\ntitulo: Borrador\nborrador: sí\netiquetas: [secretos]\n---\nSin terminar.\n");
        $this->crearFichero('contenido/blog/oculta.md', "---\ntitulo: Oculta\nlistada: no\netiquetas: [ocultas]\n---\nNo sale en listados.\n");

        $hoy = $this->construir();

        self::assertNotContains('tema/novedades/index.html', $this->paginasGeneradas($hoy));
        self::assertNotContains('tema/secretos/index.html', $this->paginasGeneradas($hoy));
        self::assertNotContains('tema/ocultas/index.html', $this->paginasGeneradas($hoy));

        $despues = $this->construir(new \DateTimeImmutable('2031-01-01'));

        self::assertContains('tema/novedades/index.html', $this->paginasGeneradas($despues));
        self::assertNotContains('tema/secretos/index.html', $this->paginasGeneradas($despues));
    }

    #[Test]
    public function unTituloPropioGanaAlDelGrupo(): void
    {
        $this->crearSitio();
        $this->generadora("titulo: Tema\n");

        $construccion = $this->construir();

        self::assertStringStartsWith('<h1>Tema</h1><p>ideas</p>', $construccion->escritos['tema/ideas/index.html']);
        self::assertStringStartsWith('<h1>Tema</h1><p>oficio</p>', $construccion->escritos['tema/oficio/index.html']);
    }

    #[Test]
    public function elCampoPuedeSerUnTextoSueltoYUnNumeroCuentaComoTexto(): void
    {
        $this->crearSitio();
        $this->generadora('', campo: 'categoria', excluir: '[]');
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\ncategoria: Cocina\n---\nUno.\n");
        $this->crearFichero('contenido/blog/dos.md', "---\ntitulo: Dos\nfecha: 2024-02-01\ncategoria: 2024\n---\nDos.\n");
        $this->crearFichero('contenido/blog/tres.md', "---\ntitulo: Tres\nfecha: 2024-03-01\ncategoria: cocina\n---\nTres.\n");

        $construccion = $this->construir();

        self::assertSame(['tema/2024/index.html', 'tema/cocina/index.html'], $this->paginasGeneradas($construccion));
        self::assertSame('<h1>Cocina</h1><p>Cocina</p><ul><li>Uno</li><li>Tres</li></ul>', $construccion->escritos['tema/cocina/index.html']);
    }

    #[Test]
    public function elCampoPuedeLlevarUnNombreInglesDeLosAlias(): void
    {
        $this->crearSitio();
        $this->generadora('', campo: 'tags');

        self::assertSame(
            ['tema/cultura-pop/index.html', 'tema/ideas/index.html', 'tema/oficio/index.html'],
            $this->paginasGeneradas($this->construir()),
        );
    }

    #[Test]
    public function avisaDeLosValoresQueNoValenYSigueConLosDemas(): void
    {
        $this->crearSitio();
        $this->generadora('', campo: 'autor', excluir: '[]');
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\nautor: [Ane, '', '!!!']\n---\nUno.\n");
        $this->crearFichero('contenido/blog/dos.md', "---\ntitulo: Dos\nfecha: 2024-02-01\nautor: {nombre: Ane}\n---\nDos.\n");
        $this->crearFichero('contenido/blog/tres.md', "---\ntitulo: Tres\nfecha: 2024-03-01\n---\nTres.\n");

        $construccion = $this->construir();

        self::assertSame(['tema/ane/index.html'], $this->paginasGeneradas($construccion));
        self::assertEqualsCanonicalizing([
            'contenido/blog/uno.md: «autor» tiene un valor en blanco; no forma parte de ningún grupo',
            'contenido/blog/uno.md: «!!!», un valor de «autor», no deja ninguna letra ni número para la URL; no forma parte de ningún grupo',
            'contenido/blog/dos.md: «autor» tiene un valor que no es un texto ni un número; no forma parte de ningún grupo',
        ], array_map(strval(...), $construccion->avisos));
    }

    #[Test]
    public function sinColeccionOSinCampoAvisaEnSuLineaYNoGeneraNada(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/tema.md', "---\nplantilla: tema\nagrupar:\n  campo: etiquetas\nurl: \"/tema/{{ grupo|slug }}/\"\n---\nCuerpo.\n");

        $construccion = $this->construir();

        self::assertSame([], $this->paginasGeneradas($construccion));
        self::assertSame(
            ['contenido/tema.md:3: «agrupar» necesita «coleccion», la colección de la que salen las páginas; no se genera ninguna página'],
            array_map(strval(...), $construccion->avisos),
        );
    }

    #[Test]
    public function avisaDeLoQueNoAdmiteAgrupar(): void
    {
        $this->crearSitio();
        $this->crearFichero(
            'contenido/tema.md',
            "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: {a: 1}\n  colección: blog\nurl: \"/tema/{{ grupo|slug }}/\"\n---\nCuerpo.\n",
        );

        $avisos = array_map(strval(...), $this->construir()->avisos);

        self::assertEqualsCanonicalizing([
            'contenido/tema.md:3: «agrupar»: «excluir» tiene que ser un texto o una lista de textos; se ignora',
            'contenido/tema.md:3: «agrupar» no admite «colección»; se ignora',
        ], $avisos);
    }

    #[Test]
    public function unAgruparQueNoEsUnMapaSeIgnoraYLaPaginaEsUnaMas(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/tema.md', "---\ntitulo: Tema\nagrupar: sí\n---\nUna página cualquiera.\n");

        $construccion = $this->construir();

        self::assertSame('Una página cualquiera.', trim(strip_tags($construccion->escritos['tema/index.html'])));
        self::assertSame(
            ['contenido/tema.md:3: «agrupar» tiene que ser un mapa con «coleccion» y «campo»; se ignora'],
            array_map(strval(...), $construccion->avisos),
        );
    }

    #[Test]
    public function laUrlTieneQueUsarElGrupoOSeAvisaYNoSeGeneraNada(): void
    {
        $this->crearSitio();
        $this->crearFichero(
            'contenido/tema.md',
            "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\nurl: /tema/\n---\nCuerpo.\n",
        );

        $construccion = $this->construir();

        self::assertSame([], $this->paginasGeneradas($construccion));
        self::assertSame(
            ['contenido/tema.md: Una página con «agrupar» necesita una «url» que use {{ grupo|slug }}: sin él, todas las páginas que genera irían al mismo fichero; no se genera ninguna'],
            array_map(strval(...), $construccion->avisos),
        );

        // Sin url, igual.
        $this->crearFichero('contenido/tema.md', "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n---\nCuerpo.\n");

        self::assertSame([], $this->paginasGeneradas($this->construir()));
    }

    #[Test]
    public function aceptaElGrupoSinSlugEnLaUrlYAvisaSiNoValeComoFichero(): void
    {
        $this->crearSitio();
        $this->generadora('', url: '/tema/{{ grupo }}/');
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\netiquetas: [ideas, 'a:b']\n---\nUno.\n");
        $this->crearFichero('contenido/blog/dos.md', "---\ntitulo: Dos\nfecha: 2024-02-01\netiquetas: [ideas]\n---\nDos.\n");
        $this->crearFichero('contenido/blog/tres.md', "---\ntitulo: Tres\nfecha: 2024-03-01\netiquetas: [ideas]\n---\nTres.\n");

        $construccion = $this->construir();

        self::assertSame(['tema/ideas/index.html'], $this->paginasGeneradas($construccion));
        self::assertSame(
            ['contenido/tema.md: La URL /tema/a:b/ no vale como ruta de fichero; no se genera esta página'],
            array_map(strval(...), $construccion->avisos),
        );
    }

    #[Test]
    public function agruparNoSeHeredaDeUnDatos(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n");

        $construccion = $this->construir();

        self::assertSame(
            ['contenido/blog/_datos.yml:2: «agrupar» no se hereda: todas las páginas de la carpeta serían plantillas de páginas. Se escribe en la página que genera los grupos; se ignora'],
            array_map(strval(...), $construccion->avisos),
        );
        self::assertSame(['blog/dos/index.html', 'blog/tres/index.html', 'blog/uno/index.html'], $this->paginasGeneradas($construccion, ['blog/']));
    }

    #[Test]
    public function unaPlantillaDeGruposNoPuedeAgruparLoQueGeneraNiTodo(): void
    {
        $this->crearSitio();
        $this->generadora('', coleccion: 'todo');

        try {
            $this->construir();
            self::fail('Tenía que detenerse');
        } catch (ErrorDeProyecto $error) {
            self::assertStringContainsString('contenido/tema.md → «todo» → contenido/tema.md', $error->getMessage());
        }

        // Ni una colección a la que pertenecen las páginas que genera: `temas`.
        $this->generadora('', coleccion: 'temas');

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/tema.md → «temas» → contenido/tema.md');

        $this->construir();
    }

    #[Test]
    public function dosPlantillasDeGruposPuedenAgruparColeccionesDistintas(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/autor.md', "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: autor\nurl: \"/autor/{{ grupo|slug }}/\"\netiquetas: [autores]\n---\nCuerpo.\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\nautor: Ane\netiquetas: [ideas]\n---\nUno.\n");

        $construccion = $this->construir();

        self::assertContains('autor/ane/index.html', array_keys($construccion->escritos));
        self::assertContains('tema/ideas/index.html', array_keys($construccion->escritos));
    }

    #[Test]
    public function unaPaginaGeneradaQueVaAlMismoFicheroQueOtraDetieneElBuildNombrandolas(): void
    {
        $this->crearSitio();
        $this->crearFichero('contenido/otra.md', "---\ntitulo: Otra\nurl: /tema/ideas/\n---\nOtra.\n");

        $this->expectException(ErrorDeProyecto::class);
        $this->expectExceptionMessage('contenido/otra.md (/tema/ideas/) y contenido/tema.md#ideas (/tema/ideas/)');

        $this->construir();
    }

    #[Test]
    public function unaPlantillaDeGruposEnBorradorNoGeneraNada(): void
    {
        $this->crearSitio();
        $this->generadora("borrador: sí\n");

        self::assertSame([], $this->paginasGeneradas($this->construir()));
    }

    #[Test]
    public function unaColeccionSinPaginasNoGeneraNadaNiAvisa(): void
    {
        $this->crearSitio();
        $this->generadora('', coleccion: 'nada');

        $construccion = $this->construir();

        self::assertSame([], $this->paginasGeneradas($construccion));
        self::assertSame([], $construccion->avisos);
    }

    #[Test]
    public function cadaIdiomaAgrupaSusPaginasYLasGeneradasNoTienenTraducciones(): void
    {
        $this->crearSitio();
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\nidiomas:\n  - codigo: es\n  - codigo: eu\n");
        $this->crearFichero('plantillas/tema.twig', '{{ pagina.grupo }}:{{ pagina.paginas|length }}:{{ pagina.traducciones|length }}:{% for codigo, t in pagina.traducciones %}{{ codigo }}={{ t.url }}{% endfor %}');
        $this->crearFichero('contenido/tema.eu.md', "---\nplantilla: tema\nagrupar:\n  coleccion: blog\n  campo: etiquetas\n  excluir: [blog]\nurl: \"/gaia/{{ grupo|slug }}/\"\n---\nGorputza.\n");
        $this->crearFichero('contenido/blog/uno.eu.md', "---\ntitulo: Bat\nfecha: 2024-01-01\netiquetas: [ideiak]\n---\nBat.\n");

        $construccion = $this->construir();

        self::assertSame(
            [
                'eu/gaia/ideiak/index.html',
                'tema/cultura-pop/index.html',
                'tema/ideas/index.html',
                'tema/oficio/index.html',
            ],
            $this->paginasGeneradas($construccion, ['tema/', 'eu/gaia/']),
        );
        self::assertSame('ideiak:1:1:eu=/eu/gaia/ideiak/', $construccion->escritos['eu/gaia/ideiak/index.html']);
        self::assertSame('ideas:3:1:es=/tema/ideas/', $construccion->escritos['tema/ideas/index.html']);
    }

    #[Test]
    public function elCampoPaginasDeUnaPaginaNormalSigueSiendoSuyo(): void
    {
        $this->crearSitio();
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.paginas }}');
        $this->crearFichero('contenido/libro.md', "---\ntitulo: Libro\npaginas: 320\n---\nUn libro.\n");

        self::assertSame('320', $this->construir()->escritos['libro/index.html']);
    }

    /**
     * Un blog con tres artículos y una página que agrupa sus etiquetas, más un
     * índice con las páginas que genera. Salen siete páginas: los tres
     * artículos, el índice y tres temas.
     */
    private function crearSitio(): void
    {
        $this->crearFichero('sitio.yml', "nombre: Prueba\nurl: https://ejemplo.com\n");
        $this->crearFichero('plantillas/pagina.twig', '{{ pagina.contenido }}');
        $this->crearFichero(
            'plantillas/tema.twig',
            '<h1>{{ pagina.titulo }}</h1><p>{{ pagina.grupo }}</p><ul>{% for articulo in pagina.paginas %}<li>{{ articulo.titulo }}</li>{% endfor %}</ul>',
        );
        $this->crearFichero('contenido/blog/_datos.yml', "etiquetas: [blog]\nurl: \"/blog/{{ titulo|slug }}/\"\n");
        $this->crearFichero('contenido/blog/uno.md', "---\ntitulo: Uno\nfecha: 2024-01-01\netiquetas: [ideas, Cultura pop]\n---\nUno.\n");
        $this->crearFichero('contenido/blog/dos.md', "---\ntitulo: Dos\nfecha: 2024-02-01\netiquetas: [Ideas, cultura-pop]\n---\nDos.\n");
        $this->crearFichero('contenido/blog/tres.md', "---\ntitulo: Tres\nfecha: 2024-03-01\netiquetas: [ideas, oficio]\n---\nTres.\n");
        $this->crearFichero(
            'contenido/temas.twig',
            "---\ntitulo: Temas\nurl: /temas/\n---\n{% for tema in coleccion('temas')|orden('grupo') %}{{ tema.grupo }}={{ tema.paginas|length }};{% endfor %}",
        );
        $this->generadora('');
    }

    /**
     * La página que agrupa, con sus campos y lo que se quiera añadir al front matter.
     */
    private function generadora(
        string $extra,
        string $coleccion = 'blog',
        string $campo = 'etiquetas',
        string $excluir = '[blog]',
        string $url = '/tema/{{ grupo|slug }}/',
    ): void {
        $this->crearFichero(
            'contenido/tema.md',
            "---\nplantilla: tema\n{$extra}agrupar:\n  coleccion: {$coleccion}\n  campo: {$campo}\n  excluir: {$excluir}\nurl: \"{$url}\"\netiquetas: [temas]\n---\nCuerpo de la plantilla de grupos.\n",
        );
    }

    /**
     * Los ficheros de las páginas generadas entre lo construido.
     *
     * @param list<string> $carpetas dónde buscarlas
     *
     * @return list<string>
     */
    private function paginasGeneradas(Construccion $construccion, array $carpetas = ['tema/']): array
    {
        $ficheros = array_values(array_filter(
            array_keys($construccion->escritos),
            fn (string $fichero) => array_any($carpetas, fn (string $carpeta) => str_starts_with($fichero, $carpeta)),
        ));
        sort($ficheros);

        return $ficheros;
    }

    private function construir(?\DateTimeImmutable $ahora = null): Construccion
    {
        return (new Constructor())->construir(Proyecto::abrir($this->carpetaTemporal()), $ahora ?? new \DateTimeImmutable(self::AHORA));
    }
}
