<?php

declare(strict_types=1);

namespace Ehundu\Previsualizacion;

use Ehundu\Constructor;
use Ehundu\ErrorDeProyecto;
use Ehundu\Informe;
use Ehundu\Proyecto;

/**
 * El servidor de la previsualización: un servidor HTTP mínimo escrito en PHP,
 * sin extensiones ni procesos aparte, que solo escucha en 127.0.0.1.
 *
 * Atiende las peticiones una a una y, entre ellas, cada medio segundo
 * pregunta al vigilante si ha cambiado algo; si es así, reconstruye.
 */
final class Servidor
{
    public const int PUERTO_POR_DEFECTO = 8000;

    private const float CADA = 0.5;

    private const array ESTADOS = [
        200 => 'OK',
        301 => 'Moved Permanently',
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
    ];

    /**
     * @param resource $salida   donde se escribe lo que va bien
     * @param resource $errores  donde se escriben los errores y los avisos
     * @param bool     $completo si se rehace todo el sitio en cada cambio
     */
    public function __construct(
        private readonly Proyecto $proyecto,
        private readonly int $puerto,
        private readonly bool $conBorradores,
        private $salida,
        private $errores,
        private readonly bool $completo = false,
    ) {
    }

    /**
     * Sirve hasta que se interrumpe con Ctrl+C.
     *
     * @throws ErrorDeProyecto si no se puede abrir el puerto
     */
    public function servir(): never
    {
        // En Windows, PHP deja escuchar a dos procesos en el mismo puerto y el
        // segundo se quedaría con parte de las peticiones; por eso se comprueba
        // antes si ya responde alguien.
        $ocupado = @stream_socket_client("tcp://127.0.0.1:{$this->puerto}", $codigo, $mensaje, 0.3);

        if ($ocupado !== false) {
            fclose($ocupado);

            throw new ErrorDeProyecto(sprintf(
                'No se puede escuchar en el puerto %d: ya lo está usando otro programa. Prueba con --puerto=%d',
                $this->puerto,
                $this->puerto + 1,
            ));
        }

        $socket = @stream_socket_server("tcp://127.0.0.1:{$this->puerto}", $codigo, $mensaje);

        if ($socket === false) {
            throw new ErrorDeProyecto(sprintf(
                'No se puede escuchar en el puerto %d (%s). Prueba con --puerto=%d',
                $this->puerto,
                trim((string) $mensaje),
                $this->puerto + 1,
            ));
        }

        $previsualizacion = new Previsualizacion($this->proyecto, $this->conBorradores, new Constructor(incremental: !$this->completo));
        $vigilante = new Vigilante($this->proyecto);

        $this->informar($previsualizacion->reconstruir());
        fwrite($this->salida, "Previsualización en http://127.0.0.1:{$this->puerto}/"
            . ($this->conBorradores ? ', con borradores' : '') . ". Ctrl+C para parar.\n");

        $ultimaMirada = microtime(true);

        while (true) {
            $leer = [$socket];
            $escribir = null;
            $excepciones = null;

            if (@stream_select($leer, $escribir, $excepciones, 0, (int) (self::CADA * 1_000_000)) > 0) {
                $cliente = @stream_socket_accept($socket, 0);

                if ($cliente !== false) {
                    $this->atender($cliente, $previsualizacion);
                }
            }

            if (microtime(true) - $ultimaMirada >= self::CADA) {
                if ($vigilante->haCambiado()) {
                    $this->informar($previsualizacion->reconstruir());
                }

                $ultimaMirada = microtime(true);
            }
        }
    }

    /**
     * @param resource $cliente
     */
    private function atender($cliente, Previsualizacion $previsualizacion): void
    {
        stream_set_timeout($cliente, 2);
        $primera = (string) fgets($cliente, 8192);

        // Las cabeceras no hacen falta: basta leerlas hasta la línea vacía.
        for ($i = 0; $i < 100 && ($linea = fgets($cliente, 8192)) !== false && trim($linea) !== ''; $i++) {
        }

        if (preg_match('#^([A-Z]+) (\S+) HTTP/\d\.\d#', $primera, $partes) !== 1) {
            fclose($cliente);

            return;
        }

        $respuesta = $previsualizacion->responder($partes[1], $partes[2]);
        $longitud = $respuesta->cuerpo !== null ? strlen($respuesta->cuerpo) : (int) @filesize((string) $respuesta->fichero);

        $cabeceras = sprintf("HTTP/1.1 %d %s\r\n", $respuesta->estado, self::ESTADOS[$respuesta->estado] ?? '')
            . "Content-Type: {$respuesta->tipo}\r\n"
            . "Content-Length: {$longitud}\r\n"
            . "Cache-Control: no-store\r\n"
            . ($respuesta->ubicacion !== null ? "Location: {$respuesta->ubicacion}\r\n" : '')
            . "Connection: close\r\n\r\n";

        @fwrite($cliente, $cabeceras);

        if ($partes[1] !== 'HEAD') {
            if ($respuesta->cuerpo !== null) {
                @fwrite($cliente, $respuesta->cuerpo);
            } elseif ($respuesta->fichero !== null && ($fichero = @fopen($respuesta->fichero, 'rb')) !== false) {
                @stream_copy_to_stream($fichero, $cliente);
                fclose($fichero);
            }
        }

        fclose($cliente);
    }

    private function informar(Informe|\Throwable $resultado): void
    {
        if ($resultado instanceof \Throwable) {
            fwrite($this->errores, 'Error: ' . $resultado->getMessage() . "\n");

            return;
        }

        foreach ($resultado->avisos as $aviso) {
            fwrite($this->errores, "Aviso: {$aviso}\n");
        }

        fwrite($this->salida, $resultado->resumen() . "\n");
    }
}
