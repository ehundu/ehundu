<?php

declare(strict_types=1);

/*
 * Un servidor FTP mínimo para las pruebas: sin cifrado, en modo pasivo, con
 * un solo usuario y los ficheros en una carpeta. Escribe en la salida el
 * puerto en el que escucha y atiende conexiones, de una en una, hasta que lo
 * paran o hasta que pasa medio minuto sin conexiones.
 *
 * Con un tercer número, corta la conexión de control en mitad de cada
 * subida número N, sin contestar, como un servidor que se cae: para probar
 * que el cliente vuelve a conectar. Con un cuarto, en cada subida número N
 * guarda solo la mitad y contesta 426, como vsftpd cuando una subida le llega
 * mal, pero sin cortar.
 *
 * Uso: php servidor-ftp-falso.php <carpeta> <clave> [cortar-cada] [fallar-cada]
 */

[, $raiz, $clave] = $argv;
$cortarCada = (int) ($argv[3] ?? 0);
$fallarCada = (int) ($argv[4] ?? 0);
$subidas = 0;

$servidor = stream_socket_server('tcp://127.0.0.1:0', $codigo, $mensaje);

if ($servidor === false) {
    fwrite(STDERR, "No se puede escuchar: {$mensaje}\n");
    exit(1);
}

echo puerto($servidor), "\n";

// Si en medio minuto no llega nadie, se acaba: una prueba que se corta no
// deja el servidor huérfano.
while (($control = @stream_socket_accept($servidor, 30)) !== false) {
    atender($control, $raiz, $clave, $cortarCada, $fallarCada, $subidas);
}

/**
 * @param resource $control
 */
function atender($control, string $raiz, string $clave, int $cortarCada, int $fallarCada, int &$subidas): void
{
    $responder = function (string $linea) use ($control): void {
        fwrite($control, "{$linea}\r\n");
    };
    $pasivo = null;
    $usuario = null;
    $responder('220 Servidor FTP falso');

    while (($linea = fgets($control)) !== false) {
        [$orden, $argumento] = array_pad(explode(' ', rtrim($linea, "\r\n"), 2), 2, '');
        $orden = strtoupper($orden);
        $ruta = rtrim($raiz, '/') . '/' . ltrim($argumento, '/');

        // Una conexión de datos ya pedida con PASV o EPSV.
        $datos = function () use (&$pasivo) {
            $conexion = @stream_socket_accept($pasivo, 5);
            fclose($pasivo);
            $pasivo = null;

            return $conexion;
        };

        switch ($orden) {
            case 'USER':
                $usuario = $argumento;
                $responder('331 Falta la clave');
                break;
            case 'PASS':
                $responder($usuario !== null && $argumento === $clave ? '230 Dentro' : '530 Clave incorrecta');
                break;
            case 'SYST':
                $responder('215 UNIX Type: L8');
                break;
            case 'NOOP':
                $responder('200 Aquí sigo');
                break;
            case 'TYPE':
                $responder('200 Tipo cambiado');
                break;
            case 'PWD':
                $responder('257 "/"');
                break;
            case 'PASV':
            case 'EPSV':
                $pasivo = stream_socket_server('tcp://127.0.0.1:0');
                $puerto = puerto($pasivo);
                $responder($orden === 'PASV'
                    ? sprintf('227 Modo pasivo (127,0,0,1,%d,%d)', intdiv($puerto, 256), $puerto % 256)
                    : "229 Modo pasivo extendido (|||{$puerto}|)");
                break;
            case 'STOR':
                $conexion = $datos();
                $responder('150 Adelante');
                $contenido = stream_get_contents($conexion);
                fclose($conexion);
                $subidas++;

                if ($cortarCada > 0 && $subidas % $cortarCada === 0) {
                    fclose($control);

                    return;
                }

                if ($fallarCada > 0 && $subidas % $fallarCada === 0) {
                    file_put_contents($ruta, substr((string) $contenido, 0, intdiv(strlen((string) $contenido), 2)));
                    $responder('426 Failure reading network stream.');
                } elseif (!is_dir(dirname($ruta))) {
                    $responder('553 No existe la carpeta');
                } else {
                    file_put_contents($ruta, $contenido);
                    $responder('226 Recibido');
                }
                break;
            case 'RETR':
                $conexion = $datos();

                if (!is_file($ruta)) {
                    fclose($conexion);
                    $responder('550 No existe');
                } else {
                    $responder('150 Allá va');
                    fwrite($conexion, (string) file_get_contents($ruta));
                    fclose($conexion);
                    $responder('226 Enviado');
                }
                break;
            case 'NLST':
                $conexion = $datos();
                $responder('150 Lista');
                $carpeta = $argumento === '' ? $raiz : $ruta;

                foreach (is_dir($carpeta) ? array_diff(scandir($carpeta), ['.', '..']) : [] as $nombre) {
                    fwrite($conexion, "{$nombre}\r\n");
                }

                fclose($conexion);
                $responder('226 Hecho');
                break;
            case 'SIZE':
                $responder(is_file($ruta) ? '213 ' . filesize($ruta) : '550 No existe');
                break;
            case 'MKD':
                $responder(!is_dir($ruta) && @mkdir($ruta) ? "257 \"{$argumento}\" creada" : '550 No se puede crear');
                break;
            case 'RMD':
                $responder(is_dir($ruta) && @rmdir($ruta) ? '250 Quitada' : '550 No se puede quitar');
                break;
            case 'DELE':
                $responder(is_file($ruta) && @unlink($ruta) ? '250 Borrado' : '550 No existe');
                break;
            case 'QUIT':
                $responder('221 Adiós');
                fclose($control);

                return;
            default:
                $responder('502 No sé hacer eso');
        }
    }
}

/**
 * @param resource $socket
 */
function puerto($socket): int
{
    return (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
}
