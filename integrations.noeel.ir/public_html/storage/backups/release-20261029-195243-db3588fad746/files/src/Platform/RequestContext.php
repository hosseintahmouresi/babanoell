<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Platform;

final class RequestContext
{
    private static ?string $requestId = null;
    private static ?string $callerService = null;

    public static function initialize(?string $incoming = null, string $prefix = 'partners'): string
    {
        if (self::valid($incoming)) { self::$requestId = trim((string)$incoming); return self::$requestId; }
        $safe = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower(trim($prefix))) ?: 'req';
        self::$requestId = $safe . '_' . bin2hex(random_bytes(12));
        return self::$requestId;
    }

    /** @param array<string,mixed> $server */
    public static function initializeFromServer(array $server, string $prefix = 'partners'): string
    {
        $incoming = $server['HTTP_X_REQUEST_ID'] ?? null;
        return self::initialize(is_string($incoming) ? $incoming : null, $prefix);
    }

    public static function set(string $requestId): void
    {
        if (!self::valid($requestId)) throw new \InvalidArgumentException('Invalid request id.');
        self::$requestId = trim($requestId);
    }

    public static function id(): string
    {
        return self::$requestId ?? self::initialize(null, 'partners');
    }

    public static function valid(?string $requestId): bool
    {
        return is_string($requestId) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', trim($requestId)) === 1;
    }

    public static function setCallerService(?string $service): void
    {
        $service=is_string($service)?strtolower(trim($service)):'';
        self::$callerService = preg_match('/^[a-z][a-z0-9_-]{1,31}$/',$service)===1 ? $service : null;
    }

    public static function callerService(): ?string
    {
        return self::$callerService;
    }

    /** @return array<string,mixed> */
    public static function auditMeta(): array
    {
        $meta=['request_id'=>self::id()];
        if(self::$callerService!==null)$meta['caller_service']=self::$callerService;
        return $meta;
    }

    /** @return array<string,mixed> */
    public static function responseMeta(): array
    {
        return ['request_id' => self::id(), 'api_version' => 'v1'];
    }
}
