<?php

declare(strict_types=1);

/*
 * Part of Omega - Tests\Console\Commands\Fixtures.
 *
 * @link      https://omegamvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright 2025-2026 The Omega MVC Framework
 * @license   https://opensource.org/license/mit The MIT License
 * @version   2.0.0
 */

namespace Tests\Console\Commands\Fixtures;

use Omega\Cron\InterpolateInterface;

class FakeCronLogger implements InterpolateInterface
{
    /** @var list<string> */
    public array $messages = [];

    public function interpolate(string $message, array $context = []): void
    {
        $this->messages[] = $message;
    }
}