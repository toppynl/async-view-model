<?php

declare(strict_types=1);

namespace Toppy\AsyncViewModel;

/**
 * Interface for services that need to reset state between requests.
 *
 * In worker mode (FrankenPHP, RoadRunner), PHP processes persist across requests.
 * Services implementing this interface can clean up request-scoped state.
 *
 * When used with Symfony, services can implement both this interface and
 * Symfony\Contracts\Service\ResetInterface for automatic reset handling.
 */
interface ResetInterface
{
    public function reset(): void;
}
