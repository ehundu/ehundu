<?php

declare(strict_types=1);

namespace Ehundu;

/**
 * La versión de Ehundu, la que escribe `ehundu --version`. Se cambia al preparar cada versión, en el mismo
 * commit que el README; entre versiones lleva `-dev`. No es la versión del formato (`Formato::VERSION`).
 *
 * @internal
 */
final class Version
{
    public const string ACTUAL = '0.15.0-dev';
}
