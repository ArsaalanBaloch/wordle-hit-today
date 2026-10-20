<?php

declare(strict_types=1);

namespace WordleHitToday\Validation;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

final readonly class SchemaValidator
{
    public function __construct(private string $schemaDirectory)
    {
    }

    public function validateJson(string $json, string $schemaFile): void
    {
        try {
            $data = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
            $schemaJson = file_get_contents($this->schemaDirectory.DIRECTORY_SEPARATOR.$schemaFile);
            if (false === $schemaJson) {
                throw new ValidationException(sprintf('Schema is not readable: %s', $schemaFile));
            }
            $schema = json_decode($schemaJson, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ValidationException('Invalid JSON: '.$exception->getMessage(), previous: $exception);
        }

        $result = new Validator()->validate($data, $schema);
        if (!$result->isValid()) {
            $error = $result->error();
            if (null === $error) {
                throw new \LogicException('Schema validator returned an invalid result without an error.');
            }
            $details = new ErrorFormatter()->format($error);
            throw new ValidationException('JSON Schema validation failed.', $details);
        }
    }
}
