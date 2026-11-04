<?php
declare(strict_types=1);

namespace Babanoell\Auth\Models;

use DateTimeImmutable;

final class Permission
{
    use DateTimeValue;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            name: (string) $row['name'],
            description: $row['description'] === null ? null : (string) $row['description'],
            createdAt: self::optionalDateTime($row['created_at'] ?? null),
            updatedAt: self::optionalDateTime($row['updated_at'] ?? null)
        );
    }
}
