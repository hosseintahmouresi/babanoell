<?php
declare(strict_types=1);

namespace Babanoell\Auth\Audit;

use Babanoell\Auth\Exceptions\AuditException;

final class AuditEvent
{
    private const SENSITIVE_METADATA_KEYS = [
        'password',
        'plain_password',
        'token',
        'access_token',
        'refresh_token',
        'reset_token',
        'secret',
        'api_key',
        'sms_code',
        'two_factor_code',
        'otp',
    ];

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        private readonly AuditEventType $type,
        private readonly string $result,
        private readonly AuditContext $context = new AuditContext(),
        private readonly array $metadata = [],
    ) {
        if (trim($result) === '') {
            throw new AuditException('Audit result is required.');
        }

        $this->assertMetadataIsSafe($metadata);
    }

    public function type(): AuditEventType
    {
        return $this->type;
    }

    public function result(): string
    {
        return $this->result;
    }

    public function context(): AuditContext
    {
        return $this->context;
    }

    /**
     * @return array<string,mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function assertMetadataIsSafe(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_METADATA_KEYS, true)) {
                throw new AuditException('Audit metadata contains a prohibited sensitive field.');
            }

            if (is_array($value)) {
                $this->assertMetadataIsSafe($value);
            }
        }
    }
}
