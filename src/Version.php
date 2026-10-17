<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La versión de Ehundu, la que escribe `ehundu --version`: `0.15.0` en una versión publicada y `0.16.0-dev` entre
 * una y otra. Se cambia al preparar cada versión, en el mismo commit que el README. No es la versión del formato
 * (`Formato::VERSION`).
 */
final class Version
{
    public const string ACTUAL = '0.17.0-dev';
}
