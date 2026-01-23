<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ToppyAsyncViewModelBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
