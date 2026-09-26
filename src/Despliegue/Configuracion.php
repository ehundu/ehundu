<?php

declare(strict_types=1);

namespace Ehundu\Despliegue;

use Ehundu\Avisos;
use Ehundu\ErrorDeProyecto;
use Ehundu\Proyecto;
use Ehundu\Yaml;

/**
 * La sección `despliegue` de `sitio.yml`, con sus secretos (formato §2): lo
 * que no es secreto va en `sitio.yml`, que se comparte y se versiona; las
 * claves, en `.secretos.yml`, que no se versiona nunca, o las da el programa
 * que incrusta el motor.
 */
final readonly class Configuracion
{
    public const string SECRETOS = '.secretos.yml';

    public const array DESTINOS = ['carpeta', 'ftp', 'sftp', 's3'];

    /** Lo que es secreto y no puede ir en `sitio.yml`. */
    public const array SECRETAS = ['clave', 'clavePrivada', 'frase'];

    /** Los campos que usa cada destino, además de `destino` y de los secretos. */
    private const array CAMPOS = [
        'carpeta' => ['ruta'],
        'ftp' => ['servidor', 'puerto', 'usuario', 'ruta', 'cifrado'],
        'sftp' => ['servidor', 'puerto', 'usuario', 'ruta', 'huella'],
        's3' => ['servidor', 'usuario', 'ruta', 'region', 'cubo'],
    ];

    /**
     * @param string      $clavePrivada ruta al fichero de la clave privada de SSH, desde la raíz del proyecto
     *                                  si no es absoluta
     */
    public function __construct(
        public string $destino,
        public ?string $servidor = null,
        public ?int $puerto = null,
        public ?string $usuario = null,
        public string $ruta = '',
        public ?string $huella = null,
        public bool $cifrado = true,
        public ?string $region = null,
        public ?string $cubo = null,
        #[\SensitiveParameter] public ?string $clave = null,
        public ?string $clavePrivada = null,
        #[\SensitiveParameter] public ?string $frase = null,
    ) {
    }

    /**
     * @param array<string, string> $secretos los que da el programa que incrusta el motor; ganan a los
     *                                        de `.secretos.yml`
     *
     * @throws ErrorDeProyecto si falta la sección, falta algo imprescindible o hay un secreto en `sitio.yml`
     */
    public static function leer(Proyecto $proyecto, #[\SensitiveParameter] array $secretos = [], Avisos $avisos = new Avisos()): self
    {
        $texto = self::texto($proyecto, Proyecto::SITIO);

        if ($texto === null) {
            throw new ErrorDeProyecto('No existe ' . Proyecto::SITIO);
        }

        $campos = Yaml::leerCampos($texto, Proyecto::SITIO);
        $despliegue = $campos['despliegue'] ?? null;
        $linea = fn (string $clave) => self::linea($texto, $clave);

        if ($despliegue === null) {
            throw new ErrorDeProyecto('Falta la sección «despliegue», que dice adónde se publica el sitio', Proyecto::SITIO);
        }

        if (!is_array($despliegue) || ($despliegue !== [] && array_is_list($despliegue))) {
            throw new ErrorDeProyecto('«despliegue» tiene que ser una serie de campos «nombre: valor»', Proyecto::SITIO, $linea('despliegue'));
        }

        foreach (self::SECRETAS as $secreta) {
            if (array_key_exists($secreta, $despliegue)) {
                throw new ErrorDeProyecto(
                    "«{$secreta}» es secreta y va en " . self::SECRETOS . ', nunca en ' . Proyecto::SITIO . ', que se comparte y se versiona',
                    Proyecto::SITIO,
                    $linea($secreta),
                );
            }
        }

        $destino = $despliegue['destino'] ?? null;

        if (!is_string($destino) || !in_array($destino, self::DESTINOS, true)) {
            throw new ErrorDeProyecto(
                '«destino» tiene que ser ' . implode(', ', array_slice(self::DESTINOS, 0, -1)) . ' o ' . self::DESTINOS[array_key_last(self::DESTINOS)],
                Proyecto::SITIO,
                $linea('destino'),
            );
        }

        $valores = [];

        foreach ($despliegue as $clave => $valor) {
            $clave = (string) $clave;

            if ($clave === 'destino') {
                continue;
            }

            if (!in_array($clave, self::CAMPOS[$destino], true)) {
                $avisos->registrar(
                    in_array($clave, array_merge(...array_values(self::CAMPOS)), true)
                        ? "«{$clave}» no se usa con el destino {$destino}; se ignora"
                        : "«{$clave}» no es un campo de «despliegue»; se ignora",
                    Proyecto::SITIO,
                    $linea($clave),
                );

                continue;
            }

            $valores[$clave] = self::valor($clave, $valor, $linea($clave));
        }

        foreach ([...self::secretosDelFichero($proyecto, $avisos), ...$secretos] as $clave => $valor) {
            if (!in_array($clave, self::SECRETAS, true)) {
                throw new \InvalidArgumentException("«{$clave}» no es un secreto de despliegue");
            }

            if ($valor !== null && $valor !== '') {
                $valores[$clave] = (string) $valor;
            }
        }

        $configuracion = new self($destino, ...$valores);
        $configuracion->comprobar();

        return $configuracion;
    }

    /**
     * Dónde se publica, para los mensajes; nunca lleva secretos.
     */
    public function descripcion(): string
    {
        $ruta = $this->ruta === '' ? '' : '/' . ltrim($this->ruta, '/');

        return match ($this->destino) {
            'carpeta' => "la carpeta {$this->ruta}",
            'ftp' => ($this->cifrado ? 'ftps' : 'ftp') . "://{$this->usuario}@{$this->servidor}" . $this->conPuerto(21) . $ruta,
            'sftp' => "sftp://{$this->usuario}@{$this->servidor}" . $this->conPuerto(22) . $ruta,
            's3' => "s3://{$this->cubo}{$ruta} en {$this->servidor}",
        };
    }

    /**
     * Para que un volcado nunca enseñe las claves.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $datos = get_object_vars($this);

        foreach (['clave', 'frase'] as $secreta) {
            if ($datos[$secreta] !== null) {
                $datos[$secreta] = '(oculta)';
            }
        }

        return $datos;
    }

    private function comprobar(): void
    {
        $faltan = match ($this->destino) {
            'carpeta' => $this->ruta === '' ? ['ruta'] : [],
            'ftp' => array_keys(array_filter(['servidor' => $this->servidor, 'usuario' => $this->usuario], fn ($valor) => $valor === null)),
            'sftp' => array_keys(array_filter(['servidor' => $this->servidor, 'usuario' => $this->usuario], fn ($valor) => $valor === null)),
            's3' => array_keys(array_filter(['servidor' => $this->servidor, 'region' => $this->region, 'cubo' => $this->cubo, 'usuario' => $this->usuario], fn ($valor) => $valor === null)),
        };

        if ($faltan !== []) {
            throw new ErrorDeProyecto(
                "Para desplegar con el destino {$this->destino} falta " . self::enumerar(array_map(fn ($campo) => "«{$campo}»", $faltan)) . ' en «despliegue»',
                Proyecto::SITIO,
            );
        }

        $sinClave = match ($this->destino) {
            'ftp', 's3' => $this->clave === null,
            'sftp' => $this->clave === null && $this->clavePrivada === null,
            default => false,
        };

        if ($sinClave) {
            throw new ErrorDeProyecto(
                'Falta la clave para desplegar: va en ' . self::SECRETOS . ', dentro de «despliegue», como «clave»'
                    . ($this->destino === 'sftp' ? ' o, si se entra con una clave privada, como «clavePrivada»' : ''),
                self::SECRETOS,
            );
        }
    }

    private function conPuerto(int $porDefecto): string
    {
        return $this->puerto === null || $this->puerto === $porDefecto ? '' : ":{$this->puerto}";
    }

    private static function valor(string $clave, mixed $valor, ?int $linea): mixed
    {
        if ($clave === 'puerto') {
            if (is_int($valor) || (is_string($valor) && ctype_digit($valor))) {
                $puerto = (int) $valor;

                if ($puerto >= 1 && $puerto <= 65535) {
                    return $puerto;
                }
            }

            throw new ErrorDeProyecto('«puerto» tiene que ser un número entre 1 y 65535', Proyecto::SITIO, $linea);
        }

        if ($clave === 'cifrado') {
            $texto = is_string($valor) ? mb_strtolower(trim($valor)) : null;

            return match (true) {
                is_bool($valor) => $valor,
                $texto === 'sí' || $texto === 'si' => true,
                $texto === 'no' => false,
                default => throw new ErrorDeProyecto('«cifrado» tiene que ser sí o no', Proyecto::SITIO, $linea),
            };
        }

        if (!is_scalar($valor) || is_bool($valor) || trim((string) $valor) === '') {
            throw new ErrorDeProyecto("«{$clave}» tiene que ser un texto", Proyecto::SITIO, $linea);
        }

        return trim((string) $valor);
    }

    /**
     * @return array<string, mixed>
     */
    private static function secretosDelFichero(Proyecto $proyecto, Avisos $avisos): array
    {
        $texto = self::texto($proyecto, self::SECRETOS);

        if ($texto === null) {
            return [];
        }

        $despliegue = Yaml::leerCampos($texto, self::SECRETOS, secreto: true)['despliegue'] ?? [];

        if (!is_array($despliegue)) {
            throw new ErrorDeProyecto('«despliegue» tiene que ser una serie de campos «nombre: valor»', self::SECRETOS);
        }

        $secretos = [];

        foreach ($despliegue as $clave => $valor) {
            if (!in_array($clave, self::SECRETAS, true)) {
                $avisos->registrar(
                    'En ' . self::SECRETOS . ' solo van ' . self::enumerar(array_map(fn ($secreta) => "«{$secreta}»", self::SECRETAS)) . "; «{$clave}» se ignora",
                    self::SECRETOS,
                    self::linea($texto, (string) $clave),
                );

                continue;
            }

            if (!is_scalar($valor) || is_bool($valor)) {
                throw new ErrorDeProyecto(
                    "«{$clave}» tiene que ser un texto; escribe el valor entre comillas simples",
                    self::SECRETOS,
                    self::linea($texto, (string) $clave),
                );
            }

            $secretos[(string) $clave] = (string) $valor;
        }

        return $secretos;
    }

    private static function texto(Proyecto $proyecto, string $fichero): ?string
    {
        return is_file($proyecto->ruta($fichero)) ? $proyecto->leerTexto($fichero) : null;
    }

    /**
     * La línea de un campo: `despliegue` en el primer nivel, los demás dentro de él.
     */
    private static function linea(string $texto, string $clave): ?int
    {
        $dentro = false;

        foreach (explode("\n", $texto) as $indice => $linea) {
            if (preg_match('/^despliegue\s*:/', $linea) === 1) {
                if ($clave === 'despliegue') {
                    return $indice + 1;
                }

                $dentro = true;

                continue;
            }

            if ($dentro && preg_match('/^\S/', $linea) === 1) {
                $dentro = false;
            }

            if ($dentro && preg_match('/^\s+' . preg_quote($clave, '/') . '\s*:/', $linea) === 1) {
                return $indice + 1;
            }
        }

        return null;
    }

    /**
     * @param list<string> $partes
     */
    private static function enumerar(array $partes): string
    {
        return count($partes) < 2 ? implode('', $partes) : implode(', ', array_slice($partes, 0, -1)) . ' y ' . $partes[array_key_last($partes)];
    }
}
