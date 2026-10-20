<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use WordleHitToday\Source\NytSourceClient;
use WordleHitToday\Source\SourceFailure;
use WordleHitToday\Source\SourcePayloadValidator;

#[CoversClass(NytSourceClient::class)]
final class NytSourceClientTest extends TestCase
{
    public function testValidJsonResponseIsAccepted(): void
    {
        $response = new MockResponse(
            '{"id":42,"solution":"crane","print_date":"2026-10-16","days_since_launch":1945}',
            ['http_code' => 200, 'response_headers' => ['content-type: application/json']],
        );
        $client = new NytSourceClient(new MockHttpClient($response), new SourcePayloadValidator(), $this->settings());

        self::assertSame('CRANE', $client->fetch('2026-10-16')['solution']);
    }

    public function testDeniedResponseIsClassifiedWithoutParsingBody(): void
    {
        $response = new MockResponse('Forbidden', ['http_code' => 403, 'response_headers' => ['content-type: text/plain']]);
        $client = new NytSourceClient(new MockHttpClient($response), new SourcePayloadValidator(), $this->settings());

        try {
            $client->fetch('2026-10-16');
            self::fail('Expected source denial.');
        } catch (SourceFailure $exception) {
            self::assertSame('denied', $exception->category);
        }
    }

    public function testHtmlWithHttp200IsRejected(): void
    {
        $response = new MockResponse('<html>Site unavailable</html>', ['http_code' => 200, 'response_headers' => ['content-type: text/html']]);
        $client = new NytSourceClient(new MockHttpClient($response), new SourcePayloadValidator(), $this->settings());

        $this->expectException(SourceFailure::class);
        $this->expectExceptionMessage('did not declare JSON');
        $client->fetch('2026-10-16');
    }

    /** @return array{host: string, path_pattern: string, connect_timeout_seconds: int, total_timeout_seconds: int, maximum_response_bytes: int} */
    private function settings(): array
    {
        return [
            'host' => 'www.nytimes.com',
            'path_pattern' => '/svc/wordle/v2/{date}.json',
            'connect_timeout_seconds' => 10,
            'total_timeout_seconds' => 20,
            'maximum_response_bytes' => 65_536,
        ];
    }
}
