<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Monitoring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Monitoring\StatusStore;
use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Validation\SchemaValidator;

#[CoversClass(StatusStore::class)]
final class StatusStoreTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = dirname(__DIR__, 3).'/var/test-status-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testHeartbeatAndRetryStateArePersisted(): void
    {
        $store = $this->store();
        $now = new \DateTimeImmutable('2026-10-16T12:00:00Z');

        $store->heartbeat($now);
        $store->failure('2026-10-16', $now, 'network');
        $snapshot = $store->snapshot();

        self::assertSame('2026-10-16T12:00:00Z', $snapshot['heartbeat_at_utc']);
        self::assertSame('2026-10-16T12:05:00Z', $snapshot['dates']['2026-10-16']['next_attempt_at_utc']);
        self::assertFalse($store->shouldAttempt('2026-10-16', $now->modify('+4 minutes')));
        self::assertTrue($store->shouldAttempt('2026-10-16', $now->modify('+5 minutes')));
    }

    public function testSuccessfulRechecksEventuallyComplete(): void
    {
        $store = $this->store();
        $now = new \DateTimeImmutable('2026-10-16T12:00:00Z');
        for ($check = 0; $check < 4; ++$check) {
            $store->success('2026-10-16', $now->modify(sprintf('+%d hours', $check)));
        }

        self::assertTrue($store->snapshot()['dates']['2026-10-16']['complete']);
        self::assertFalse($store->shouldAttempt('2026-10-16', $now->modify('+2 days')));
    }

    public function testConfiguredMinimumAppliesToOrdinaryFailures(): void
    {
        $store = $this->store(20);
        $now = new \DateTimeImmutable('2026-10-16T12:00:00Z');

        $store->failure('2026-10-16', $now, 'network');

        self::assertSame('2026-10-16T12:20:00Z', $store->snapshot()['dates']['2026-10-16']['next_attempt_at_utc']);
    }

    public function testConfiguredMinimumAlsoAppliesToSuccessfulRechecks(): void
    {
        $store = $this->store(120);
        $now = new \DateTimeImmutable('2026-10-16T12:00:00Z');

        $store->success('2026-10-16', $now);

        self::assertSame('2026-10-16T14:00:00Z', $store->snapshot()['dates']['2026-10-16']['next_attempt_at_utc']);
    }

    private function store(int $minimumPollMinutes = 5): StatusStore
    {
        $projectRoot = dirname(__DIR__, 3);

        return new StatusStore(
            $this->temporaryRoot.'/status.json',
            new AtomicFileWriter(),
            new SchemaValidator($projectRoot.'/schemas'),
            $minimumPollMinutes,
        );
    }
}
