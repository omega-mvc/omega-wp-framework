<?php

declare(strict_types=1);

namespace Tests\Console\Traits;

use Omega\Console\Traits\InteractsWithConsoleOutputTrait;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Console\Fixtures\OutputStyler;

covers(InteractsWithConsoleOutputTrait::class);

it('returns the terminal width', function (): void {
    $styler = new OutputStyler(new BufferedOutput());

    expect($styler->terminalWidth())->toBeGreaterThan(0);
});

it('calculates the visible width ignoring formatting tags', function (): void {
    $styler = new OutputStyler(new BufferedOutput());

    expect($styler->visibleWidth('plain'))->toBe(5)
        ->and($styler->visibleWidth('<info>tagged</info>'))->toBe(6)
        ->and($styler->visibleWidth("\033[31mred\033[0m"))->toBe(3)
        ->and($styler->visibleWidth(''))->toBe(0);
});

it('returns the largest visible width of a collection', function (): void {
    $styler = new OutputStyler(new BufferedOutput());

    expect($styler->visibleMaxWidth(['foo', 'longer', '<info>x</info>']))->toBe(6)
        ->and($styler->visibleMaxWidth([]))->toBe(0);
});

it('aligns a message to the right edge with the given margin', function (): void {
    $output = new BufferedOutput();
    $styler = new OutputStyler($output);

    $styler->printRight('bye');
    $display = $output->fetch();

    expect($display)->toEndWith(PHP_EOL)
        ->and(rtrim($display, PHP_EOL))->toEndWith('bye')
        ->and(strlen(rtrim($display, PHP_EOL)))->toBe($styler->terminalWidth() - 2);
});

it('respects a custom right margin', function (): void {
    $output = new BufferedOutput();
    $styler = new OutputStyler($output);

    $styler->printRight('bye', 6);
    $display = rtrim($output->fetch(), PHP_EOL);

    expect($display)->toEndWith('bye')
        ->and(strlen($display))->toBe($styler->terminalWidth() - 6);
});

it('does not pad when the message is wider than the terminal', function (): void {
    $output = new BufferedOutput();
    $styler = new OutputStyler($output);
    $message = str_repeat('x', $styler->terminalWidth() + 100);

    $styler->printRight($message);

    expect(rtrim($output->fetch(), PHP_EOL))->toBe($message);
});

it('writes two columns separated by dotted filler', function (): void {
    $output = new BufferedOutput();
    $styler = new OutputStyler($output);

    $styler->printColumns('Config', 'Done');

    $display = rtrim($output->fetch(), PHP_EOL);

    expect($display)->toStartWith('  Config ')
        ->and($display)->toEndWith(' Done  ')
        ->and($display)->toContain(str_repeat('.', 10));
});

it('keeps at least two dots when the content fills the line', function (): void {
    $output = new BufferedOutput();
    $styler = new OutputStyler($output);
    $left = str_repeat('l', 60);
    $right = str_repeat('r', 60);

    $styler->printColumns($left, $right);

    $display = rtrim($output->fetch(), PHP_EOL);

    expect($display)->toStartWith('  ' . $left)
        ->and($display)->toEndWith($right . '  ')
        ->and($display)->toContain('..');
});
