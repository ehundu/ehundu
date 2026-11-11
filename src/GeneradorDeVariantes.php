<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * Genera las variantes de ancho de las imágenes de un proyecto (formato §9.2): lo que hace
 * `ehundu imagenes`. Escribe en `publico/` los ficheros `<tronco>-<ancho>.<formato>` que declara la
 * sección `imagenes` de `sitio.yml` y que busca `Variantes`.
 *
 * No es parte del build: `compilar` no la llama. Necesita la extensión GD, que el resto del motor no
 * exige, y por eso lo primero que mira es si GD sabe escribir cada formato que pide el sitio.
 *
 * @internal
 */
final class GeneradorDeVariantes
{
    /** Una imagen de más píxeles no cabe en la memoria de un PHP corriente (unos 400 MB con 48 millones) */
    public const int MAXIMO_DE_PIXELES = 50_000_000;

    /** Lo que GD gasta por píxel al decodificar y reducir, con holgura (medido: unos 8 bytes) */
    private const int BYTES_POR_PIXEL = 8;

    /** Las imágenes de las que se sacan variantes; un GIF puede estar animado, y de un SVG no hay. */
    private const array ENTRADAS = ['jpg', 'jpeg', 'png', 'webp'];

    /** La calidad con que se escribe cada formato con pérdida (formato §9.2). */
    private const array CALIDAD = ['webp' => 80, 'avif' => 55, 'jpg' => 82, 'jpeg' => 82];

    private const int VELOCIDAD_DE_AVIF = 6;

    private readonly Avisos $avisos;

    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly Imagenes $imagenes,
        ?Avisos $avisos = null,
    ) {
        $this->avisos = $avisos ?? new Avisos();
    }

    /**
     * Lee la sección `imagenes` de `sitio.yml` y prepara el generador. Los avisos de esa lectura van a los
     * mismos `$avisos` que los de la generación.
     *
     * @throws ErrorDeProyecto si falta `sitio.yml` o le falta algo imprescindible
     */
    public static function abrir(Proyecto $proyecto, ?Avisos $avisos = null): self
    {
        $avisos ??= new Avisos();
        $sitio = (new Lector())->leerSitio($proyecto, $avisos);

        return new self($proyecto, $sitio->imagenes, $avisos);
    }

    /**
     * @param bool                        $forzar     rehacer también las variantes que ya existen
     * @param string|null                 $originales carpeta del proyecto, fuera de `publico/`, con su estructura
     * @param (\Closure(string): void)|null $alGenerar recibe cada fichero que se escribe, relativo a `publico/`
     *
     * @throws ErrorDeProyecto si el sitio no declara `imagenes`, si falta GD o no sabe escribir un formato,
     *                         o si la carpeta de originales no vale
     */
    public function generar(bool $forzar = false, ?string $originales = null, ?\Closure $alGenerar = null): InformeDeVariantes
    {
        $inicio = hrtime(true);

        $this->comprobarQueSePuede();
        $carpeta = $this->carpetaDeOrigen($originales);

        $imagenes = 0;
        $generadas = [];
        $existentes = 0;
        $vistas = [];

        foreach ($this->proyecto->ficherosDe($carpeta) as $relativa) {
            if (Ignorados::es($relativa)) {
                continue;
            }

            $extension = strtolower(pathinfo($relativa, PATHINFO_EXTENSION));

            if (!in_array($extension, self::ENTRADAS, true)) {
                continue;
            }

            $tronco = substr($relativa, 0, -(strlen($extension) + 1));

            if ($originales === null && $this->esUnaVariante($tronco, $extension)) {
                continue;
            }

            $donde = "{$carpeta}/{$relativa}";

            if (isset($vistas[$tronco])) {
                $this->avisos->registrar("tiene el mismo nombre que {$vistas[$tronco]} sin la extensión, y sus variantes serían las mismas; se salta", $donde);

                continue;
            }

            $vistas[$tronco] = $donde;
            $imagenes++;
            $existentes += $this->tratar($donde, $tronco, $forzar, $generadas, $alGenerar);
        }

        return new InformeDeVariantes(
            $imagenes,
            $generadas,
            $existentes,
            $this->avisos->todos(),
            (hrtime(true) - $inicio) / 1e9,
        );
    }

    /**
     * @throws ErrorDeProyecto
     */
    private function comprobarQueSePuede(): void
    {
        if (!$this->imagenes->activa()) {
            throw new ErrorDeProyecto('El sitio no declara la sección «imagenes» con sus «anchos»: no hay variantes que generar', Proyecto::SITIO);
        }

        if (!extension_loaded('gd')) {
            throw new ErrorDeProyecto('Para generar las variantes de imagen hace falta la extensión GD de PHP, y no está cargada');
        }

        $gd = gd_info();

        foreach ($this->imagenes->formatos as $formato) {
            $sabe = match ($formato) {
                'webp' => ($gd['WebP Support'] ?? false) && function_exists('imagewebp'),
                'avif' => ($gd['AVIF Support'] ?? false) && function_exists('imageavif'),
                'jpg', 'jpeg' => ($gd['JPEG Support'] ?? false) === true,
                'png' => ($gd['PNG Support'] ?? false) === true,
                default => false,
            };

            if (!$sabe) {
                throw new ErrorDeProyecto("«imagenes» pide el formato {$formato}, y la extensión GD de este PHP no sabe escribirlo; no se genera nada", Proyecto::SITIO);
            }
        }
    }

    /**
     * La carpeta de la que se leen las imágenes: `publico/`, o la de los originales.
     *
     * @throws ErrorDeProyecto
     */
    private function carpetaDeOrigen(?string $originales): string
    {
        if ($originales === null) {
            return Proyecto::PUBLICO;
        }

        $carpeta = trim(str_replace('\\', '/', $originales), '/');

        if ($carpeta === '' || !Variantes::rutaValida($carpeta)) {
            throw new ErrorDeProyecto("La carpeta de originales tiene que estar dentro del proyecto: {$originales}");
        }

        $completa = realpath($this->proyecto->ruta($carpeta));

        if ($completa === false || !is_dir($completa)) {
            throw new ErrorDeProyecto("No existe la carpeta de originales: {$carpeta}");
        }

        $completa = strtolower(str_replace('\\', '/', $completa)) . '/';

        foreach ([Proyecto::PUBLICO, Proyecto::SALIDA] as $reservada) {
            $reservadaCompleta = realpath($this->proyecto->ruta($reservada));

            if ($reservadaCompleta !== false && str_starts_with($completa, strtolower(str_replace('\\', '/', $reservadaCompleta)) . '/')) {
                throw new ErrorDeProyecto("Los originales no pueden estar en {$reservada}/: sin «--originales» ya se trabaja sobre publico/, y las variantes se escriben allí");
            }
        }

        return $carpeta;
    }

    /**
     * Si un fichero de `publico/` ya es una variante de otro: su nombre acaba en `-<ancho>`, con uno de los
     * anchos de la sección, y su extensión es uno de los formatos.
     */
    private function esUnaVariante(string $tronco, string $extension): bool
    {
        if (!in_array($extension, $this->imagenes->formatos, true)) {
            return false;
        }

        return preg_match('/-(\d+)$/', $tronco, $partes) === 1 && in_array((int) $partes[1], $this->imagenes->anchos, true);
    }

    /**
     * Genera las variantes de una imagen. Da cuántas ya existían y no se han rehecho.
     *
     * @param string       $donde     el fichero, relativo al proyecto, para los avisos
     * @param string       $tronco    su ruta sin la extensión, relativa a la carpeta de origen y a `publico/`
     * @param list<string> $generadas donde se anotan los ficheros que se escriben
     */
    private function tratar(string $donde, string $tronco, bool $forzar, array &$generadas, ?\Closure $alGenerar): int
    {
        $existentes = 0;
        $pendientes = [];

        foreach ($this->imagenes->formatos as $formato) {
            foreach ($this->imagenes->anchos as $ancho) {
                $ruta = "{$tronco}-{$ancho}.{$formato}";

                if (!$forzar && is_file($this->proyecto->ruta(Proyecto::PUBLICO, $ruta))) {
                    $existentes++;
                } else {
                    $pendientes[] = [$ruta, $ancho, $formato];
                }
            }
        }

        if ($pendientes === []) {
            return $existentes;
        }

        $fichero = $this->proyecto->ruta($donde);
        $info = @getimagesize($fichero);

        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 1 || $info[1] < 1) {
            $this->avisos->registrar('no es una imagen JPEG, PNG o WebP que se pueda leer; se salta', $donde);

            return $existentes;
        }

        [$ancho, $alto, $tipo] = $info;

        if ($ancho * $alto > self::MAXIMO_DE_PIXELES) {
            $millones = round($ancho * $alto / 1_000_000);
            $this->avisos->registrar("tiene {$millones} millones de píxeles y el máximo es 50; se salta", $donde);

            return $existentes;
        }

        $limite = self::limiteDeMemoria();

        if ($limite !== null && $ancho * $alto * self::BYTES_POR_PIXEL + memory_get_usage(true) > $limite) {
            $this->avisos->registrar('no cabe en la memoria que deja usar PHP (memory_limit); se salta', $donde);

            return $existentes;
        }

        $origen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fichero),
            IMAGETYPE_PNG => @imagecreatefrompng($fichero),
            default => @imagecreatefromwebp($fichero),
        };

        if ($origen === false) {
            $this->avisos->registrar('no se puede leer: está dañada o no es del formato que dice; se salta', $donde);

            return $existentes;
        }

        if (!imageistruecolor($origen)) {
            imagepalettetotruecolor($origen);
        }

        $orientacion = $tipo === IMAGETYPE_JPEG ? OrientacionDeCamara::de($fichero) : 1;

        foreach ($pendientes as [$ruta, $anchoPedido, $formato]) {
            $imagen = $this->reducir($origen, $ancho, $alto, $anchoPedido, $orientacion, $formato);

            if ($imagen !== null && $this->guardar($imagen, $formato, $this->proyecto->ruta(Proyecto::PUBLICO, $ruta))) {
                $generadas[] = $ruta;

                if ($alGenerar !== null) {
                    $alGenerar($ruta);
                }
            } else {
                $this->avisos->registrar("no se ha podido escribir publico/{$ruta}", $donde);
            }
        }

        return $existentes;
    }

    /**
     * La imagen reducida al ancho pedido, sin ampliarla nunca, y girada como la cámara. Primero se reduce y
     * después se gira: girar es copiar la imagen entera.
     */
    private function reducir(\GdImage $origen, int $ancho, int $alto, int $anchoPedido, int $orientacion, string $formato): ?\GdImage
    {
        $anchoVisto = OrientacionDeCamara::deLado($orientacion) ? $alto : $ancho;
        $escala = min(1.0, $anchoPedido / $anchoVisto);
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $imagen = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        if ($imagen === false) {
            return null;
        }

        if (in_array($formato, ['jpg', 'jpeg'], true)) {
            // JPEG no tiene transparencia: lo transparente se mezcla con un fondo blanco
            imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 255, 255, 255));
        } else {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
            imagefill($imagen, 0, 0, (int) imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        }

        imagecopyresampled($imagen, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return OrientacionDeCamara::girar($imagen, $orientacion);
    }

    /**
     * Escribe la imagen en su formato. Lo hace en un fichero aparte y lo renombra al terminar: un fichero a
     * medias se tomaría como una variante que ya existe.
     */
    private function guardar(\GdImage $imagen, string $formato, string $ruta): bool
    {
        if (!is_dir(dirname($ruta)) && !@mkdir(dirname($ruta), 0777, true) && !is_dir(dirname($ruta))) {
            return false;
        }

        $parcial = $ruta . '.parte';

        if ($formato === 'png') {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, self::esTransparente($imagen));
        } elseif ($formato !== 'jpg' && $formato !== 'jpeg') {
            imagealphablending($imagen, false);
            imagesavealpha($imagen, true);
        } else {
            // Progresivo: se ve entero enseguida en una conexión lenta, y pesa algo menos
            imageinterlace($imagen, true);
        }

        $escrito = match ($formato) {
            'webp' => @imagewebp($imagen, $parcial, self::CALIDAD['webp']),
            'avif' => @imageavif($imagen, $parcial, self::CALIDAD['avif'], self::VELOCIDAD_DE_AVIF),
            'png' => @imagepng($imagen, $parcial, 9),
            default => @imagejpeg($imagen, $parcial, self::CALIDAD['jpg']),
        };

        if (!$escrito || !is_file($parcial) || filesize($parcial) === 0 || !@rename($parcial, $ruta)) {
            @unlink($parcial);

            return false;
        }

        return true;
    }

    /** Si alguna parte de la imagen deja ver lo de detrás: se para en el primer píxel que lo hace */
    private static function esTransparente(\GdImage $imagen): bool
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                if (((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** La memoria que deja usar PHP, o null si no tiene límite */
    private static function limiteDeMemoria(): ?int
    {
        $valor = trim((string) ini_get('memory_limit'));

        if ($valor === '' || $valor === '-1') {
            return null;
        }

        $numero = (float) $valor;

        return (int) match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
