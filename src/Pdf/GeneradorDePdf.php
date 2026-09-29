<?php

declare(strict_types=1);

namespace Ehundu\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;

/**
 * Convierte el HTML de la plantilla de un PDF en el PDF (formato §10.3), con
 * dompdf, que es PHP puro y una dependencia opcional.
 *
 * Todo lo que puede cambiar de una compilación a otra se fija: las fechas del
 * documento son las de la página y el identificador sale de su contenido, así
 * que el mismo HTML da siempre los mismos bytes. Lo único que dompdf escribe
 * fuera de `salida/`, su caché de tipografías, va a la carpeta temporal del
 * sistema y no a `vendor/`.
 *
 * Solo lee de `publico/`: las direcciones que empiezan por `/` se buscan ahí
 * y nada remoto se carga.
 *
 * @internal
 */
final class GeneradorDePdf
{
    /** Cuando la página no tiene fecha, como en el feed sin entradas (formato §10.2). */
    private const string SIN_FECHA = '1970-01-01 00:00:00';

    /** Lo que pide un HTML: `src`, `url()` y el `href` de un `<link>`. */
    private const string REFERENCIAS = '/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\')|url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s]*))\s*\)|<link\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly \DateTimeZone $zona,
    ) {
    }

    public static function disponible(): bool
    {
        return class_exists(Dompdf::class);
    }

    /**
     * @param string                  $html   el documento completo, con su CSS ya dentro
     * @param string                  $titulo el de la página
     * @param \DateTimeImmutable|null $fecha  la de la página; sin ella, el 1 de enero de 1970
     *
     * @return array{0: string, 1: list<string>, 2: list<string>} el PDF, lo que dompdf ha avisado y los
     *                                                             ficheros de `publico/` que ha leído o
     *                                                             que el HTML pide (una ruta que sube con
     *                                                             `..` es algo de fuera de `publico/`)
     *
     * @throws ErrorDeProyecto si falta dompdf o el PDF no se puede generar
     */
    public function generar(string $html, string $titulo, ?\DateTimeImmutable $fecha): array
    {
        if (!self::disponible()) {
            throw new ErrorDeProyecto(
                'Una página pide su PDF con «pdf: sí», y falta dompdf, que es lo que lo genera. Se instala con: composer require dompdf/dompdf (el ehundu.phar no lo trae)',
            );
        }

        $dia = ($fecha ?? new \DateTimeImmutable(self::SIN_FECHA, $this->zona))->setTimezone($this->zona)->setTime(0, 0);
        $marca = self::marcaDeFecha($dia);
        $publico = $this->proyecto->ruta(Proyecto::PUBLICO);
        $publicoReal = str_replace('\\', '/', realpath($publico) ?: $publico);
        $temporal = self::carpetaTemporal();

        $opciones = new Options();
        $opciones->setIsRemoteEnabled(false);
        $opciones->setIsPhpEnabled(false);
        $opciones->setChroot([$publicoReal]);
        $opciones->setDefaultFont('Helvetica');
        $opciones->setDefaultPaperSize('a4');
        $opciones->setFontDir($temporal);
        $opciones->setFontCache($temporal);
        $opciones->setTempDir($temporal);

        // Lo que dompdf lee de `publico/` por su cuenta, para que la página se rehaga si cambia.
        // Va la primera de las reglas, porque la de dompdf corta las que vienen detrás.
        $leidos = [];
        $opciones->addAllowedProtocol(
            'file://',
            function (string $uri) use (&$leidos, $publicoReal): array {
                $leidos[] = self::relativaAPublico($uri, $publicoReal);

                return [true, null];
            },
            [$opciones, 'validateLocalUri'],
        );

        global $_dompdf_warnings;
        $_dompdf_warnings = [];

        try {
            $pdf = new Dompdf($opciones);
            $pdf->setBasePath($publico);
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->render();

            $pdf->addInfo('Title', $titulo);
            $pdf->addInfo('Creator', 'Ehundu');
            $pdf->addInfo('CreationDate', $marca);
            $pdf->addInfo('ModDate', $marca);
            $pdf->getCanvas()->get_cpdf()->fileIdentifier = md5($html . "\0" . $titulo . "\0" . $marca);

            $bytes = (string) $pdf->output();
        } catch (\Throwable $error) {
            throw new ErrorDeProyecto("No se ha podido generar el PDF: {$error->getMessage()}", previous: $error);
        }

        $avisos = [];

        foreach ($_dompdf_warnings ?? [] as $aviso) {
            // Un <style> vacío, que es lo que deja `{{ css() }}` si no se declara nada
            if (preg_match('/^Unable to parse CSS that starts with:\s*$/', (string) $aviso) === 1) {
                continue;
            }

            $avisos[] = $this->sinRutas((string) $aviso);
        }

        $_dompdf_warnings = [];

        return [
            $bytes,
            array_values(array_unique($avisos)),
            array_values(array_unique([...self::referencias($html), ...$leidos])),
        ];
    }

    /**
     * Las rutas de `publico/` que pide un HTML. Cuentan aunque el fichero no
     * exista todavía: si aparece, el PDF cambia. Lo remoto, los `data:` y los
     * enlaces no cuentan.
     *
     * @return list<string>
     */
    private static function referencias(string $html): array
    {
        preg_match_all(self::REFERENCIAS, $html, $encontradas, PREG_SET_ORDER);

        $rutas = [];

        foreach ($encontradas as $encontrada) {
            $url = trim(html_entity_decode(implode('', array_slice($encontrada, 1)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = (string) preg_replace('/[?#].*$/s', '', $url);

            if ($url === '' || str_contains($url, ':') || str_starts_with($url, '//')) {
                continue;
            }

            $rutas[] = ltrim(str_replace('\\', '/', rawurldecode($url)), '/');
        }

        return $rutas;
    }

    /**
     * Un fichero que ha leído dompdf, como ruta de `publico/`; o, si es de
     * fuera, una que sube con `..`, que no tiene huella y hace que la página
     * se rehaga siempre.
     */
    private static function relativaAPublico(string $uri, string $publicoReal): string
    {
        $ruta = str_replace('\\', '/', rawurldecode((string) preg_replace('#^file://#i', '', $uri)));

        if (preg_match('#^/[A-Za-z]:/#', $ruta) === 1) {
            $ruta = substr($ruta, 1);
        }

        $real = realpath($ruta);
        $ruta = $real === false ? $ruta : str_replace('\\', '/', $real);

        return str_starts_with(mb_strtolower($ruta), mb_strtolower($publicoReal . '/'))
            ? substr($ruta, strlen($publicoReal) + 1)
            : '../' . basename($ruta);
    }

    /**
     * Una fecha como la escribe un PDF: `D:20260929000000+02'00'`.
     */
    private static function marcaDeFecha(\DateTimeImmutable $dia): string
    {
        $desfase = $dia->getOffset();
        $signo = $desfase < 0 ? '-' : '+';
        $desfase = abs($desfase);

        return sprintf("D:%s%s%02d'%02d'", $dia->format('YmdHis'), $signo, intdiv($desfase, 3600), intdiv($desfase % 3600, 60));
    }

    /**
     * Sin la ruta del proyecto, para que el aviso diga lo mismo en cualquier máquina.
     */
    private function sinRutas(string $aviso): string
    {
        return str_replace([$this->proyecto->raiz . '/', str_replace('/', '\\', $this->proyecto->raiz) . '\\'], '', $aviso);
    }

    private static function carpetaTemporal(): string
    {
        $version = class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('dompdf/dompdf')
            ? (string) \Composer\InstalledVersions::getPrettyVersion('dompdf/dompdf')
            : 'x';
        $carpeta = str_replace('\\', '/', sys_get_temp_dir()) . '/ehundu-dompdf-' . preg_replace('/[^0-9A-Za-z.]/', '', $version);

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0777, true) && !is_dir($carpeta)) {
            throw new ErrorDeProyecto("No se puede crear la carpeta temporal del PDF: {$carpeta}");
        }

        return $carpeta;
    }
}
