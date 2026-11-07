<?php
declare(strict_types=1);
namespace Babanoell\Loans\Platform;

/** Signed report evidence binds the provider response to one server-side request and subject. */
final class CreditReportProof
{
    public const TTL = 7200;

    public static function canonical(array $report): string
    {
        if ($report === [] || array_is_list($report)
            || ((!is_array($report['score'] ?? null) || $report['score'] === [])
                && (!is_array($report['report'] ?? null) || $report['report'] === []))) {
            throw new \InvalidArgumentException('credit_report_invalid');
        }
        $sort = static function (mixed $value) use (&$sort): mixed {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value, SORT_STRING);
            foreach ($value as $key => $item) $value[$key] = $sort($item);
            return $value;
        };
        $json = json_encode($sort($report), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION, 64);
        if (strlen($json) > 1048576) throw new \InvalidArgumentException('credit_report_too_large');
        return $json;
    }

    public static function issue(array $binding, array $report, string $secret, ?int $now = null): array
    {
        $time = $now ?? time();
        $claims = self::claims(array_merge($binding, ['report_sha256' => hash('sha256', self::canonical($report)),
            'issued_at' => $time, 'expires_at' => $time + self::TTL]));
        return ['claims' => $claims, 'signature' => self::signature($claims, $secret)];
    }

    public static function verify(array $proof, array $report, array $expected, string $secret, ?int $now = null): array
    {
        if (!is_array($proof['claims'] ?? null) || !is_string($proof['signature'] ?? null)) {
            throw new \InvalidArgumentException('credit_proof_invalid');
        }
        $claims = self::claims($proof['claims']);
        if (!preg_match('/^[a-f0-9]{64}$/D', $proof['signature'])
            || !hash_equals(self::signature($claims, $secret), $proof['signature'])) {
            throw new \InvalidArgumentException('credit_proof_invalid');
        }
        foreach (['verification_id', 'request_id', 'request_hash', 'subject_fingerprint', 'reference'] as $name) {
            if (!is_string($expected[$name] ?? null) || !hash_equals($claims[$name], $expected[$name])) {
                throw new \InvalidArgumentException('credit_proof_binding_mismatch');
            }
        }
        $time = $now ?? time();
        if ($claims['issued_at'] > $time + 30 || $claims['expires_at'] <= $time
            || $claims['expires_at'] - $claims['issued_at'] !== self::TTL) {
            throw new \InvalidArgumentException('credit_proof_expired');
        }
        if (!hash_equals($claims['report_sha256'], hash('sha256', self::canonical($report)))) {
            throw new \InvalidArgumentException('credit_report_hash_mismatch');
        }
        return $claims;
    }

    public static function assertSubject(array $report, string $nationalCode): void
    {
        foreach (['score', 'report'] as $section) {
            $value = $report[$section]['personInformation']['nationalCode'] ?? null;
            if ($value === null || $value === '') continue;
            if (!is_scalar($value)) throw new \InvalidArgumentException('credit_report_subject_mismatch');
            $value = strtr(trim((string)$value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3',
                '٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
            if (!hash_equals($nationalCode, $value)) throw new \InvalidArgumentException('credit_report_subject_mismatch');
        }
    }

    public static function summary(array $report): array
    {
        self::canonical($report);
        $score = $report['score']['score'] ?? null;
        if ($score !== null && $score !== ''
            && ((!is_int($score) && !(is_string($score) && ctype_digit($score)))
                || (int)$score < 0 || (int)$score > 1000000)) {
            throw new \InvalidArgumentException('credit_report_score_invalid');
        }
        $risk = $report['score']['risk'] ?? '';
        if (!is_string($risk) || strlen($risk) > 256) throw new \InvalidArgumentException('credit_report_risk_invalid');
        return ['score' => $score === null || $score === '' ? null : (int)$score, 'risk' => $risk];
    }

    private static function claims(array $input): array
    {
        $out = ['version' => 1, 'provider' => 'ics24'];
        if (isset($input['version']) && $input['version'] !== 1) throw new \InvalidArgumentException('credit_proof_version_invalid');
        if (isset($input['provider']) && $input['provider'] !== 'ics24') throw new \InvalidArgumentException('credit_proof_provider_invalid');
        foreach (['verification_id', 'request_id', 'request_hash', 'subject_fingerprint', 'reference', 'report_code', 'report_sha256'] as $name) {
            $value = $input[$name] ?? null;
            $pattern = in_array($name, ['verification_id', 'request_id'], true) ? '/^[a-f0-9]{26}$/D'
                : (in_array($name, ['reference', 'report_code'], true) ? '/^[A-Za-z0-9_-]{6,180}$/D' : '/^[a-f0-9]{64}$/D');
            if (!is_string($value) || preg_match($pattern, $value) !== 1) throw new \InvalidArgumentException('credit_proof_claim_invalid');
            $out[$name] = $value;
        }
        foreach (['issued_at', 'expires_at'] as $name) {
            if (!is_int($input[$name] ?? null) || $input[$name] < 1) throw new \InvalidArgumentException('credit_proof_time_invalid');
            $out[$name] = $input[$name];
        }
        return $out;
    }

    private static function signature(array $claims, string $secret): string
    {
        if (strlen($secret) < 32) throw new \RuntimeException('credit_proof_configuration_invalid');
        return hash_hmac('sha256', 'noel-credit-report-v1|'.json_encode($claims, JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $secret);
    }
}
