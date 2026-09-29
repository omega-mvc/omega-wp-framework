<?php

declare(strict_types=1);

/*
 * Part of Omega - Tests\Console\Commands Package.
 *
 * @link      https://omegamvc.github.io
 * @author    Axel Magold <axel.magold@gmail.com>
 * @copyright 2025-2026 The Omega MVC Framework
 * @license   https://opensource.org/license/mit The MIT License
 * @version   2.0.0
 */

namespace Tests\Console\Commands\Fixtures;

use Closure;
use Omega\Queue\Job;
use Omega\Queue\QueueAdapterInterface;
use Omega\SerializableClosure\UnsignedSerializableClosure;
use Throwable;

use function array_shift;
use function count;
use function serialize;
use function time;

class FakeQueueAdapter implements QueueAdapterInterface
{
    /** @var list<Job> */
    private array $jobs = [];

    /** @var list<Job> */
    private array $deleted = [];

    /** @var list<Job> */
    private array $failed = [];

    private int $nextId = 1;

    /**
     * @param array<string, mixed> $data
     */
    public function push(Closure $job, array $data = [], string $queue = 'default', int $delay = 0): Job
    {
        $jobInstance = new Job(
            id: $this->nextId++,
            queue: $queue,
            payload: serialize(new UnsignedSerializableClosure($job)),
            attempts: 0,
            reservedAt: null,
            availableAt: null,
            createdAt: (string) time(),
        );

        $this->jobs[] = $jobInstance;

        return $jobInstance;
    }

    public function shift(): ?Job
    {
        return array_shift($this->jobs);
    }

    public function size(string $queue = 'default'): int
    {
        return count($this->jobs);
    }

    public function delete(Job $job): bool
    {
        $this->deleted[] = $job;
        $job->markDeleted();

        return true;
    }

    public function release(Job $job, int $delay = 0): bool
    {
        $job->markReleased();

        return true;
    }

    public function failed(Job $job, Throwable $exception): void
    {
        $this->failed[] = $job;
        $job->markDeleted();
    }

    /** @return list<Job> */
    public function getDeletedJobs(): array
    {
        return $this->deleted;
    }

    /** @return list<Job> */
    public function getFailedJobs(): array
    {
        return $this->failed;
    }
}