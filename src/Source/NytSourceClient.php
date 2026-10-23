<?php

declare(strict_types=1);

namespace WordleHitToday\Source;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class NytSourceClient implements SourceClient
{
    /**
     * @param array{host: string, path_pattern: string, connect_timeout_seconds: int, total_timeout_seconds: int, maximum_response_bytes: int} $settings
     */
    public function __construct(
        private HttpClientInterface $http,
        private SourcePayloadValidator $validator,
        private array $settings,
    ) {
        if ('www.nytimes.com' !== $settings['host']) {
            throw new \InvalidArgumentException('The source host is not allowlisted.');
        }
    }

    public function fetch(string $date): array
    {
        $url = $this->urlFor($date);
        $maximumBytes = $this->settings['maximum_response_bytes'];

        try {
            $response = $this->http->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'identity',
                    'User-Agent' => 'Wordle-Hit-Today-Publisher/0.1 (+private CLI)',
                ],
                'max_redirects' => 0,
                'timeout' => $this->settings['connect_timeout_seconds'],
                'max_duration' => $this->settings['total_timeout_seconds'],
                'on_progress' => static function (int $downloaded) use ($maximumBytes): void {
                    if ($downloaded > $maximumBytes) {
                        throw new SourceFailure('invalid_payload', 'Source response exceeded the configured byte ceiling.');
                    }
                },
            ]);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $body = $response->getContent(false);
        } catch (\Throwable $exception) {
            if ($exception instanceof SourceFailure) {
                throw $exception;
            }
            throw new SourceFailure('network', 'The source request failed without a usable response.', previous: $exception);
        }

        if (in_array($status, [401, 403], true)) {
            throw new SourceFailure('denied', sprintf('The source denied the request with HTTP %d.', $status));
        }
        if (429 === $status) {
            $retryAfter = $headers['retry-after'][0] ?? null;
            $retrySeconds = is_string($retryAfter) && ctype_digit($retryAfter)
                ? min(86_400, (int) $retryAfter)
                : null;
            throw new SourceFailure('rate_limited', 'The source rate-limited the request.', $retrySeconds);
        }
        if (200 !== $status) {
            throw new SourceFailure('network', sprintf('The source returned HTTP %d.', $status));
        }
        if (strlen($body) > $maximumBytes) {
            throw new SourceFailure('invalid_payload', 'Source response exceeded the configured byte ceiling.');
        }
        $contentType = strtolower(implode(';', $headers['content-type'] ?? []));
        if (!str_contains($contentType, 'application/json')) {
            throw new SourceFailure('invalid_payload', 'Source response did not declare JSON content.');
        }

        return $this->validator->validate($body, $date);
    }

    public function urlFor(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Source date must be a real ISO calendar date.');
        }

        return 'https://'.$this->settings['host'].str_replace('{date}', $date, $this->settings['path_pattern']);
    }
}
