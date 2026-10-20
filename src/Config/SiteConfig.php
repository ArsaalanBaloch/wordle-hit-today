<?php

declare(strict_types=1);

namespace WordleHitToday\Config;

use WordleHitToday\Validation\SchemaValidator;
use WordleHitToday\Validation\ValidationException;

final readonly class SiteConfig
{
    /** @param array<string, mixed> $values */
    private function __construct(private string $projectRoot, private array $values)
    {
    }

    public static function load(string $projectRoot, string $path, SchemaValidator $validator): self
    {
        $json = file_get_contents($path);
        if (false === $json) {
            throw new ValidationException(sprintf('Configuration is not readable: %s', $path));
        }

        $validator->validateJson($json, 'config.schema.json');
        /** @var array<string, mixed> $values */
        $values = json_decode($json, true, 64, JSON_THROW_ON_ERROR);

        if ('production' === $values['mode']) {
            if (str_contains((string) $values['canonical_origin'], '.invalid')) {
                throw new ValidationException('Production configuration cannot use a placeholder canonical origin.');
            }
            if (!is_string($values['contact_address']) || '' === $values['contact_address']) {
                throw new ValidationException('Production configuration requires a contact address.');
            }
            foreach (['public_output', 'private_storage'] as $key) {
                if (!self::isAbsolutePath((string) $values[$key])) {
                    throw new ValidationException(sprintf('Production %s must be an absolute path.', $key));
                }
            }
            if (!is_array($values['puzzle_number_anchor'])) {
                throw new ValidationException('Production configuration requires a validated puzzle-number anchor.');
            }
        }

        if ((string) $values['public_output'] === (string) $values['private_storage']) {
            throw new ValidationException('Public and private storage paths must be different.');
        }

        return new self($projectRoot, $values);
    }

    public function mode(): string
    {
        return (string) $this->values['mode'];
    }

    public function siteName(): string
    {
        return (string) $this->values['site_name'];
    }

    public function canonicalOrigin(): string
    {
        return rtrim((string) $this->values['canonical_origin'], '/');
    }

    public function publicOutput(): string
    {
        return $this->resolvePath((string) $this->values['public_output']);
    }

    public function privateStorage(): string
    {
        return $this->resolvePath((string) $this->values['private_storage']);
    }

    public function contactAddress(): ?string
    {
        return is_string($this->values['contact_address']) ? $this->values['contact_address'] : null;
    }

    public function archivePageSize(): int
    {
        return (int) $this->values['archive_page_size'];
    }

    /** @return array{date: string, number: int}|null */
    public function puzzleNumberAnchor(): ?array
    {
        $anchor = $this->values['puzzle_number_anchor'];
        if (!is_array($anchor)) {
            return null;
        }

        return ['date' => (string) $anchor['date'], 'number' => (int) $anchor['number']];
    }

    /** @return array{host: string, path_pattern: string, connect_timeout_seconds: int, total_timeout_seconds: int, maximum_response_bytes: int} */
    public function source(): array
    {
        /** @var array<string, mixed> $source */
        $source = $this->values['source'];

        return [
            'host' => (string) $source['host'],
            'path_pattern' => (string) $source['path_pattern'],
            'connect_timeout_seconds' => (int) $source['connect_timeout_seconds'],
            'total_timeout_seconds' => (int) $source['total_timeout_seconds'],
            'maximum_response_bytes' => (int) $source['maximum_response_bytes'],
        ];
    }

    public function sourcePollMinutes(): int
    {
        return (int) $this->values['source_poll_minutes'];
    }

    public function statusPath(): string
    {
        /** @var array<string, mixed> $monitoring */
        $monitoring = $this->values['monitoring'];

        return $this->resolvePath((string) $monitoring['status_path']);
    }

    public function backupPath(): string
    {
        /** @var array<string, mixed> $backup */
        $backup = $this->values['backup'];

        return $this->resolvePath((string) $backup['local_path']);
    }

    /** @return array{daily_copies: int, weekly_copies: int} */
    public function backupRetention(): array
    {
        /** @var array<string, mixed> $backup */
        $backup = $this->values['backup'];

        return [
            'daily_copies' => (int) $backup['daily_copies'],
            'weekly_copies' => (int) $backup['weekly_copies'],
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    private function resolvePath(string $path): string
    {
        if (self::isAbsolutePath($path)) {
            return rtrim($path, '\\/');
        }

        return $this->projectRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return 1 === preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path);
    }
}
