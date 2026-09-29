<?php

declare(strict_types=1);

/*
 * Part of Omega - Tests\Console\Commands\Fixtures Package.
 *
 * @link      https://omegamvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright 2025-2026 The Omega MVC Framework
 * @license   https://opensource.org/license/mit The MIT License
 * @version   2.0.0
 */

namespace Tests\Console\Commands\Fixtures;

use Omega\View\Templator;
use Throwable;

final class FakeTemplator extends Templator
{
    /** @var list<string> */
    public array $compiled = [];

    public ?Throwable $throw = null;

    public function __construct()
    {
        parent::__construct('.', '.');
    }

    public function compile(string $templateName): string
    {
        if (null !== $this->throw) {
            throw $this->throw;
        }

        $this->compiled[] = $templateName;

        return '';
    }

    public function getDependency(string $parent): array
    {
        return [];
    }
}