<?php
declare(strict_types=1);
namespace Babanoell\Partners\Reports;

use DateTimeImmutable;
use PDO;

/** Aggregate-only, tenant-independent super-admin report. Never returns individual records. */
final class DailyReport
{
    private const SERIES = [
        ['cooperation_created', 'cooperation_requests', 'created_at'],
        ['contracts_created', 'contracts', 'created_at'],
        ['contracts_signed', 'contracts', 'signed_at'],
    ];

    public static function dates(string $from, string $to): array
    {
        foreach ([$from, $to] as $date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) throw new \InvalidArgumentException('invalid_report_date');
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \InvalidArgumentException('invalid_report_date');
        }
        $begin = new DateTimeImmutable($from, new \DateTimeZone('UTC'));
        $end = new DateTimeImmutable($to, new \DateTimeZone('UTC'));
        if ($begin > $end || $end > new DateTimeImmutable('today', new \DateTimeZone('UTC')) || $begin->diff($end)->days > 179) {
            throw new \InvalidArgumentException('invalid_report_range');
        }
        return [$from.' 00:00:00', $end->modify('+1 day')->format('Y-m-d').' 00:00:00'];
    }

    public static function collect(PDO $pdo, string $from, string $to): array
    {
        [$start, $until] = self::dates($from, $to);
        $series = [];
        foreach (self::SERIES as $definition) {
            [$key, $table, $column] = $definition; // Whitelisted literals only, no dynamic SQL identifier input.
            $sql = "SELECT DATE($column) AS report_day, COUNT(*) AS total FROM $table "
                 . "WHERE $column >= :start AND $column < :until "
                 . "GROUP BY DATE($column) ORDER BY report_day ASC";
            $statement = $pdo->prepare($sql);
            $statement->execute([':start'=>$start, ':until'=>$until]);
            $points = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
                $points[] = ['day'=>(string)$record['report_day'], 'count'=>(int)$record['total']];
            }
            $series[$key] = $points;
        }
        return ['from'=>$from, 'to'=>$to, 'timezone'=>'database_session', 'series'=>$series];
    }
}
