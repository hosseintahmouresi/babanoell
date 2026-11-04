<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

use InvalidArgumentException;

final class PermissionCheck
{
    public function __construct(
        private readonly string $permissionName,
        private readonly ?string $recordType = null,
        private readonly ?string $recordId = null,
    ) {
        if (trim($permissionName) === '') {
            throw new InvalidArgumentException('Permission name is required.');
        }
    }

    public function permissionName(): string
    {
        return $this->permissionName;
    }

    public function recordType(): ?string
    {
        return $this->recordType;
    }

    public function recordId(): ?string
    {
        return $this->recordId;
    }
}
