<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Support\PathPolicy;

#[CoversClass(PathPolicy::class)]
final class PathPolicyTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = PathPolicy::normalizeAbsolute(dirname(__DIR__, 3));
    }

    public function testResolveCanonicalizesDotSegments(): void
    {
        self::assertSame(
            PathPolicy::resolve('var/site', $this->projectRoot),
            PathPolicy::resolve('var/temporary/../site', $this->projectRoot),
        );
    }

    public function testDisjointRejectsParentBeforeChild(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First and Second must not overlap.');

        PathPolicy::assertDisjoint(
            PathPolicy::resolve('var/private', $this->projectRoot),
            PathPolicy::resolve('var/private/records', $this->projectRoot),
            'First',
            'Second',
        );
    }

    public function testDisjointRejectsChildBeforeParent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('First and Second must not overlap.');

        PathPolicy::assertDisjoint(
            PathPolicy::resolve('var/private/records', $this->projectRoot),
            PathPolicy::resolve('var/private', $this->projectRoot),
            'First',
            'Second',
        );
    }

    public function testStrictContainmentRejectsEquivalentDotSegmentPath(): void
    {
        $root = PathPolicy::resolve('var/private', $this->projectRoot);

        self::assertFalse(PathPolicy::isStrictlyInside(
            PathPolicy::resolve('var/private/records/..', $this->projectRoot),
            $root,
        ));
    }

    public function testStreamWrapperIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a local filesystem path');

        PathPolicy::resolve('file:///temporary/site', $this->projectRoot);
    }

    public function testResolveUsesTheRealLocationOfExistingSymbolicLinkAncestors(): void
    {
        $temporaryRoot = $this->projectRoot.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'test-path-policy-'.bin2hex(random_bytes(4));
        $target = $temporaryRoot.DIRECTORY_SEPARATOR.'target';
        $link = $temporaryRoot.DIRECTORY_SEPARATOR.'link';
        mkdir($target, 0o750, true);
        try {
            if (!@symlink($target, $link)) {
                self::markTestSkipped('Symbolic-link creation is unavailable in this environment.');
            }

            self::assertSame(
                PathPolicy::resolve($target.DIRECTORY_SEPARATOR.'site', $this->projectRoot),
                PathPolicy::resolve($link.DIRECTORY_SEPARATOR.'site', $this->projectRoot),
            );
        } finally {
            new Filesystem()->remove($temporaryRoot);
        }
    }
}
