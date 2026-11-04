<?php
declare(strict_types=1);
namespace Babanoell\Loans\Platform;

/** Forwarding headers are accepted only from an explicitly configured peer. */
final class ProxyContext
{
    public static function configured(): array
    {
        $value = $_ENV['NOEEL_TRUSTED_PROXIES'] ?? $_SERVER['NOEEL_TRUSTED_PROXIES'] ?? (function_exists('getenv') ? getenv('NOEEL_TRUSTED_PROXIES') : false);
        if (function_exists('babanoell_loans_env')) $value = \babanoell_loans_env('NOEEL_TRUSTED_PROXIES', '');
        if (!is_string($value) || trim($value) === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public static function trusted(string $ip, array $ranges): bool
    {
        $address = @inet_pton($ip);
        if ($address === false) return false;
        foreach ($ranges as $range) {
            if (!is_string($range)) continue;
            $parts = explode('/', $range, 2);
            $network = @inet_pton($parts[0]);
            if ($network === false || strlen($network) !== strlen($address)) continue;
            $bits = count($parts) === 1 ? strlen($network) * 8 : (ctype_digit($parts[1]) ? (int)$parts[1] : -1);
            if ($bits < 0 || $bits > strlen($network) * 8) continue;
            $bytes = intdiv($bits, 8); $remaining = $bits % 8;
            if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) continue;
            if ($remaining && ((ord($address[$bytes]) ^ ord($network[$bytes])) & (0xff << (8 - $remaining)))) continue;
            return true;
        }
        return false;
    }

    public static function clientIp(array $server, ?array $ranges = null): ?string
    {
        $peer = $server['REMOTE_ADDR'] ?? null;
        if (!is_string($peer) || !filter_var($peer, FILTER_VALIDATE_IP)) return null;
        $ranges ??= self::configured();
        if (!self::trusted($peer, $ranges)) return $peer;
        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!is_string($forwarded) || strlen($forwarded) > 2048 || trim($forwarded) === '') return $peer;
        $hops = array_map('trim', explode(',', $forwarded));
        if (count($hops) > 32) return $peer;
        foreach ($hops as $hop) if (!filter_var($hop, FILTER_VALIDATE_IP)) return $peer;
        $hops[] = $peer;
        // Walk from the known connection toward the client, stopping at first untrusted hop.
        for ($i = count($hops) - 1; $i >= 0; $i--) if (!self::trusted($hops[$i], $ranges)) return $hops[$i];
        return $hops[0];
    }

    public static function secure(array $server, ?array $ranges = null): bool
    {
        $https = strtolower((string)($server['HTTPS'] ?? ''));
        if (in_array($https, ['on','1','https'], true)) return true;
        $peer = $server['REMOTE_ADDR'] ?? '';
        if (!is_string($peer) || !self::trusted($peer, $ranges ?? self::configured())) return false;
        // The trusted edge must overwrite this header with a single transport value.
        return strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
    }
}
