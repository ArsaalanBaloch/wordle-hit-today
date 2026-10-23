<?php

declare(strict_types=1);

namespace WordleHitToday\Config;

use WordleHitToday\Support\PathPolicy;
use WordleHitToday\Validation\SchemaValidator;
use WordleHitToday\Validation\ValidationException;

final readonly class SiteConfig
{
    /**
     * @param array<string, mixed>                                                                                   $values
     * @param array{public_output: string, private_storage: string, status_path: string, backup_path: string} $paths
     */
    private function __construct(private array $values, private array $paths)
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
                if (!PathPolicy::isAbsoluteFilesystemPath((string) $values[$key])) {
                    throw new ValidationException(sprintf('Production %s must be an absolute path.', $key));
                }
            }
            if (!is_array($values['puzzle_number_anchor'])) {
                throw new ValidationException('Production configuration requires a validated puzzle-number anchor.');
            }
        }

        /** @var array<string, mixed> $monitoring */
        $monitoring = $values['monitoring'];
        /** @var array<string, mixed> $backup */
        $backup = $values['backup'];

        try {
            $projectRoot = PathPolicy::normalizeAbsolute($projectRoot, 'Project root');
            $paths = [
                'public_output' => PathPolicy::resolve((string) $values['public_output'], $projectRoot, 'Public output'),
                'private_storage' => PathPolicy::resolve((string) $values['private_storage'], $projectRoot, 'Private storage'),
                'status_path' => PathPolicy::resolve((string) $monitoring['status_path'], $projectRoot, 'Status path'),
                'backup_path' => PathPolicy::resolve((string) $backup['local_path'], $projectRoot, 'Backup path'),
            ];

            PathPolicy::assertManagedRoot($paths['public_output'], $projectRoot, 'Public output');
            PathPolicy::assertManagedRoot($paths['private_storage'], $projectRoot, 'Private storage');
            PathPolicy::assertManagedRoot($paths['backup_path'], $projectRoot, 'Backup path');
            PathPolicy::assertDisjoint($paths['public_output'], $paths['private_storage'], 'Public output', 'Private storage');
            PathPolicy::assertDisjoint($paths['public_output'], $paths['backup_path'], 'Public output', 'Backup path');
            PathPolicy::assertDisjoint($paths['private_storage'], $paths['backup_path'], 'Private storage', 'Backup path');
            $rollbackPath = $paths['public_output'].'.rollback';
            PathPolicy::assertManagedRoot($rollbackPath, $projectRoot, 'Public rollback');
            PathPolicy::assertDisjoint($rollbackPath, $paths['private_storage'], 'Public rollback', 'Private storage');
            PathPolicy::assertDisjoint($rollbackPath, $paths['backup_path'], 'Public rollback', 'Backup path');
            PathPolicy::assertStrictlyInside($paths['status_path'], $paths['private_storage'], 'Status path', 'Private storage');
        } catch (\InvalidArgumentException $exception) {
            throw new ValidationException($exception->getMessage(), previous: $exception);
        }

        return new self($values, $paths);
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
        return $this->paths['public_output'];
    }

    public function privateStorage(): string
    {
        return $this->paths['private_storage'];
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
        return $this->paths['status_path'];
    }

    public function backupPath(): string
    {
        return $this->paths['backup_path'];
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

}
