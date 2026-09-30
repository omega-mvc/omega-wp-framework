<?php

declare(strict_types=1);

namespace Tests\Console\Exceptions;

use LogicException;
use Omega\Console\Exceptions\InvalidArgumentException;

use function get_parent_class;

covers(InvalidArgumentException::class);

it('is a logic exception', function (): void {
    $exception = new InvalidArgumentException('Invalid configuration.');

    expect(get_parent_class($exception))->toBe(LogicException::class);
});

it('carries the configuration message', function (): void {
    try {
        throw new InvalidArgumentException('Invalid configuration.');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toBe('Invalid configuration.');
    }
});
