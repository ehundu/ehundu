<?php

declare(strict_types=1);

/*
 * Construye ehundu.phar: el motor, sus recursos y sus dependencias de
 * producción en un solo fichero, que se ejecuta con PHP y nada más:
 *
 *     php ehundu.phar compilar mi-sitio
 *
 * Copia a una carpeta temporal lo que va dentro, sin las dependencias de
 * desarrollo, rehace allí el autoload con Composer (--no-dev, sin red) y lo
 * empaqueta. Es una herramienta de desarrollo: usa Composer y necesita
 * phar.readonly desactivado, cosas que el motor no pide nunca.
 *
 * Uso: composer phar [-- destino]
 *      php -d phar.readonly=0 herramientas/construir-phar.php [destino]
 *
 * Sin destino, deja el fichero en paquete/ehundu.phar.
 */

/**
 * Lo que no hace falta de cada dependencia: sus pruebas y su documentación,
 * en la raíz del paquete (dentro de src/ puede haber carpetas que se llamen
 * igual y sí hagan falta), y los ficheros de su repositorio.
 */
const EXCLUIDO = '#^(tests?|Tests?|docs?|\.github)/|(^|/)[^/]*\.(md|rst|dist)$|(^|/)(\.gitattributes|\.gitignore|\.editorconfig|phpunit\.xml|phpstan[^/]*\.neon|psalm\.xml)$#';

define('RAIZ', str_replace('\\', '/', dirname(__DIR__)));

if (!Phar::canWrite()) {
    fwrite(STDERR, "Para construir el .phar hace falta phar.readonly=0: php -d phar.readonly=0 herramientas/construir-phar.php\n");
    exit(1);
}

$destino = str_replace('\\', '/', $argv[1] ?? RAIZ . '/paquete/ehundu.phar');
$temporal = str_replace('\\', '/', sys_get_temp_dir()) . '/ehundu-phar-' . bin2hex(random_bytes(6));

try {
    // Lo que va dentro: el motor y las dependencias que no son de desarrollo.
    $instaladas = json_decode(file_get_contents(RAIZ . '/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $deDesarrollo = $instaladas['dev-package-names'] ?? [];

    foreach (['src', 'recursos'] as $carpeta) {
        copiarCarpeta(RAIZ . "/{$carpeta}", "{$temporal}/{$carpeta}");
    }

    foreach (['composer.json', 'composer.lock', 'LICENSE'] as $fichero) {
        copiar(RAIZ . "/{$fichero}", "{$temporal}/{$fichero}");
    }

    copiarCarpeta(RAIZ . '/vendor/composer', "{$temporal}/vendor/composer");

    // Se deja fuera antes de que Composer haga el mapa de clases, para que el
    // mapa y el paquete tengan lo mismo.
    foreach ($instaladas['packages'] as $paquete) {
        if (!in_array($paquete['name'], $deDesarrollo, true)) {
            copiarCarpeta(RAIZ . "/vendor/{$paquete['name']}", "{$temporal}/vendor/{$paquete['name']}", EXCLUIDO);
        }
    }

    // La orden sin la línea #!: dentro del paquete se carga con require, y
    // esa línea saldría como texto.
    copiar(RAIZ . '/bin/ehundu', "{$temporal}/bin/ehundu");
    file_put_contents("{$temporal}/bin/ehundu", preg_replace('/^#![^\n]*\n/', '', file_get_contents("{$temporal}/bin/ehundu")));

    // El autoload sin las dependencias de desarrollo, con un mapa de clases
    // completo para no buscar ficheros dentro del paquete.
    $composer = getenv('COMPOSER_BINARY') ?: null;
    $orden = ($composer === null ? 'composer' : escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($composer))
        . ' dump-autoload --no-dev --classmap-authoritative --no-interaction --quiet --working-dir=' . escapeshellarg($temporal);
    passthru($orden, $codigo);

    if ($codigo !== 0) {
        throw new RuntimeException("Composer no ha podido rehacer el autoload ({$orden})");
    }

    // El paquete.
    is_dir(dirname($destino)) || mkdir(dirname($destino), 0777, true);
    is_file($destino) && unlink($destino);

    $phar = new Phar($destino, 0, 'ehundu.phar');
    $phar->startBuffering();

    $ficheros = [];

    foreach (ficheros($temporal) as $ruta) {
        $ficheros[$ruta] = "{$temporal}/{$ruta}";
    }

    $phar->buildFromIterator(new ArrayIterator($ficheros));
    $phar->setStub(<<<'PHP'
        #!/usr/bin/env php
        <?php
        if (PHP_VERSION_ID < 80400) {
            fwrite(STDERR, 'Ehundu necesita PHP 8.4 o superior, y este es PHP ' . PHP_VERSION . ".\n");
            exit(1);
        }

        Phar::mapPhar('ehundu.phar');
        require 'phar://ehundu.phar/bin/ehundu';

        __HALT_COMPILER();
        PHP);
    $phar->setSignatureAlgorithm(Phar::SHA256);
    $phar->stopBuffering();
    unset($phar);

    chmod($destino, 0755);

    printf("%s: %d ficheros, %s MB\n", $destino, count($ficheros), number_format(filesize($destino) / 1_048_576, 1, ',', ''));
} catch (Throwable $error) {
    fwrite(STDERR, "No se ha construido el .phar: {$error->getMessage()}\n");
    $codigo = 1;
} finally {
    borrar($temporal);
}

exit($codigo === 0 ? 0 : 1);

/**
 * Los ficheros de una carpeta, con su ruta relativa y en orden, para que el
 * paquete salga igual cada vez.
 *
 * @return list<string>
 */
function ficheros(string $carpeta): array
{
    $lista = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS)) as $fichero) {
        if ($fichero->isFile()) {
            $lista[] = substr(str_replace('\\', '/', $fichero->getPathname()), strlen($carpeta) + 1);
        }
    }

    sort($lista);

    return $lista;
}

function copiar(string $origen, string $destino): void
{
    is_dir(dirname($destino)) || mkdir(dirname($destino), 0777, true);
    copy($origen, $destino);
}

/**
 * @param string|null $excluido expresión regular sobre la ruta relativa de lo que no se copia
 */
function copiarCarpeta(string $origen, string $destino, ?string $excluido = null): void
{
    foreach (ficheros($origen) as $ruta) {
        if ($excluido === null || preg_match($excluido, $ruta) !== 1) {
            copiar("{$origen}/{$ruta}", "{$destino}/{$ruta}");
        }
    }
}

function borrar(string $ruta): void
{
    if (!is_dir($ruta)) {
        return;
    }

    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterador as $fichero) {
        $fichero->isDir() ? rmdir($fichero->getPathname()) : unlink($fichero->getPathname());
    }

    rmdir($ruta);
}
