<?php

declare(strict_types=1);

namespace Ehundu\Pruebas\Despliegue;

use Ehundu\Despliegue\Sftp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SftpPrueba extends TestCase
{
    #[Test]
    public function laHuellaEsLaQueDaSshKeygen(): void
    {
        // Una clave hecha para esta prueba con «ssh-keygen -t ed25519»; su
        // huella, la que escribe «ssh-keygen -l».
        $clave = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKeoymmHp2KTirXdyCM5DnI2W0PEAx7uS3xYQEUDELFi';

        self::assertSame('SHA256:OgR5PWmWkjC9MJd/4ChYsOklVAXnzl8AMwJFqb0rAgY', Sftp::huella($clave));
    }

    #[Test]
    public function loQueNoEsUnaClaveNoTieneHuella(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Sftp::huella('esto no es una clave');
    }
}
