<?php

declare(strict_types=1);

namespace Tests\Console;

use Omega\Console\Style;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

covers(Style::class);

/**
 * @return resource
 */
function streamWith(string $content)
{
    $stream = fopen('php://memory', 'r+');
    if ($stream === false) {
        throw new RuntimeException('Unable to open in-memory stream.');
    }
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

it('writes an indented single line', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->writeln('alpha');

    expect($output->fetch())->toBe('  alpha' . PHP_EOL);
});

it('writes multiple lines keeping empty lines unindented', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->writeln(['alpha', '', 'beta']);

    expect($output->fetch())->toBe('  alpha' . PHP_EOL . PHP_EOL . '  beta' . PHP_EOL);
});

it('trims trailing line endings before indenting', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->writeln("alpha\r\n");

    expect($output->fetch())->toBe('  alpha' . PHP_EOL);
});

it('renders all styled message blocks', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->success('done');
    $style->error('bad');
    $style->warning('careful');
    $style->comment('see note');
    $style->note('remember');
    $style->info('known');

    $display = $output->fetch();

    expect($display)->toContain('SUCCESS  done')
        ->and($display)->toContain('ERROR  bad')
        ->and($display)->toContain('WARNING  careful')
        ->and($display)->toContain('COMMENT see note')
        ->and($display)->toContain('NOTE  remember')
        ->and($display)->toContain('INFO  known');
});

it('accepts iterable messages in styled blocks', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->success(['first', 'second']);

    $display = $output->fetch();

    expect($display)->toContain('first')
        ->and($display)->toContain('second');
});

it('inserts a blank line between blocks that end with content', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->writeln('plain');
    $style->success('after');

    expect($output->fetch())->toContain('  plain' . PHP_EOL . PHP_EOL . '   SUCCESS');
});

it('renders titles, sections and text', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->title('Title');
    $style->section('Body');
    $style->text('hidden note');
    $style->text(['visible line']);

    $display = $output->fetch();

    expect($display)->toContain('Title')
        ->and($display)->toContain('== Body ==')
        ->and($display)->toContain('  hidden note')
        ->and($display)->toContain('  visible line');
});

it('returns the answer provided by the user', function (): void {
    $input = new ArrayInput([]);
    $input->setStream(streamWith("Ada\n"));
    $output = new BufferedOutput();
    $style = new Style($input, $output);

    expect($style->ask('Your name?', 'default'))->toBe('Ada');
});

it('confirms when the user answers yes', function (): void {
    $input = new ArrayInput([]);
    $input->setStream(streamWith("yes\n"));
    $output = new BufferedOutput();
    $style = new Style($input, $output);

    expect($style->confirm('Continue?'))->toBeTrue();
});

it('falls back to the default when no answer is provided', function (): void {
    $input = new ArrayInput([]);
    $input->setStream(streamWith(''));
    $output = new BufferedOutput();
    $style = new Style($input, $output);

    expect($style->confirm('Continue?', false))->toBeFalse();
});

it('writes the requested number of blank lines', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->newLine(2);

    expect($output->fetch())->toBe(PHP_EOL . PHP_EOL);
});

it('creates a progress bar with a message', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $bar = $style->progressBar(10, 'Working');

    expect($bar->getMaxSteps())->toBe(10)
        ->and($bar->getMessage())->toBe('Working');

    $output->fetch();
});

it('creates a progress bar without a message', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $bar = $style->progressBar(0);

    expect($bar->getMaxSteps())->toBe(0)
        ->and($bar->getMessage())->toBeNull();

    $output->fetch();
});

it('writes nothing when given an empty messages array', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->writeln([]);

    expect($output->fetch())->toBe('');
});

it('does not indent an empty string line', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->writeln('');

    expect($output->fetch())->toBe(PHP_EOL);
});

it('renders an empty iterable message in a styled block', function (): void {
    $output = new BufferedOutput();
    $style = new Style(new ArrayInput([]), $output);

    $style->success([]);

    expect($output->fetch())->toBe('   SUCCESS  ' . PHP_EOL . PHP_EOL);
});
