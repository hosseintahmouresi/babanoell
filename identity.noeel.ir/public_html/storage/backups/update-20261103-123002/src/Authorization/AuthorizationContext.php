<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

final class AuthorizationContext
{
    /**
     * @param array<int,string> $roleNames
     * @param array<int,string> $permissionNames
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        private readonly ?string $userId = null,
        private readonly array $roleNames = [],
        private readonly array $permissionNames = [],
        private readonly array $attributes = [],
    ) {
    }

    public static function anonymous(): self
    {
        return new self();
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    /**
     * @return array<int,string>
     */
    public function roleNames(): array
    {
        return array_values(array_unique($this->roleNames));
    }

    /**
     * @return array<int,string>
     */
    public function permissionNames(): array
    {
        return array_values(array_unique($this->permissionNames));
    }

    /**
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
