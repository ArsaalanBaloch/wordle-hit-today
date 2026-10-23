<?php

declare(strict_types=1);

namespace WordleHitToday\Validation;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final class SchemaValidator
{
    private Validator $validator;

    /** @var array<string, object> */
    private array $schemas = [];

    public function __construct(private readonly string $schemaDirectory)
    {
        $this->validator = new Validator();
    }

    public function validateJson(string $json, string $schemaFile): void
    {
        try {
            $data = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
            $schema = $this->schema($schemaFile);
        } catch (\JsonException $exception) {
            throw new ValidationException('Invalid JSON: '.$exception->getMessage(), previous: $exception);
        }

        $result = $this->validator->validate($data, $schema);
        if (!$result->isValid()) {
            $error = $result->error();
            if (null === $error) {
                throw new \LogicException('Schema validator returned an invalid result without an error.');
            }
            $details = new ErrorFormatter()->format($error);
            throw new ValidationException('JSON Schema validation failed.', $details);
        }
    }

    private function schema(string $schemaFile): object
    {
        if (basename($schemaFile) !== $schemaFile || !str_ends_with($schemaFile, '.json')) {
            throw new ValidationException('Schema filename is invalid.');
        }
        if (isset($this->schemas[$schemaFile])) {
            return $this->schemas[$schemaFile];
        }

        $schemaJson = file_get_contents($this->schemaDirectory.DIRECTORY_SEPARATOR.$schemaFile);
        if (false === $schemaJson) {
            throw new ValidationException(sprintf('Schema is not readable: %s', $schemaFile));
        }
        $schema = json_decode($schemaJson, false, 64, JSON_THROW_ON_ERROR);
        if (!is_object($schema)) {
            throw new ValidationException(sprintf('Schema root must be an object: %s', $schemaFile));
        }
        $this->schemas[$schemaFile] = $schema;

        return $schema;
    }
}
