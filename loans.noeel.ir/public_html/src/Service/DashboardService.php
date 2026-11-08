<?php
declare(strict_types=1);

namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\DashboardPeriod;
use Babanoell\Loans\Repository\PdoLoanRepository;
use Babanoell\Loans\Security\AccessDeniedException;
use Babanoell\Loans\Security\Principal;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardService
{
    public function __construct(private readonly PdoLoanRepository $repository) {}

    /** Membership IDs are supplied by the authenticated Partners API, never the browser. */
    public function summary(Principal $principal, array $merchantIds = [], ?DateTimeImmutable $now = null): array
    {
        if (!$principal->can('loans.requests.read')) {
            throw new AccessDeniedException('برای دریافت آمار پرونده‌ها دسترسی کافی ندارید.');
        }
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $period = DashboardPeriod::current($now);
        $scope = $principal->visibilityScope();
        foreach ($merchantIds as $id) {
            if (is_string($id) && preg_match('/^[a-f0-9]{26}$/D', $id)) $scope['merchants'][] = $id;
        }
        $scope['merchants'] = array_values(array_unique($scope['merchants']));
        if ($principal->can('loans.requests.global')) {
            // Global allow must still pass through explicit deny rules.
            $scope['global'][] = '*';
        }
        $result = $this->repository->dashboardSummary($principal->isSuperAdmin() ? null : $scope,
            $period['from_utc'], $period['to_utc']);
        return $result + ['period'=>$period, 'generated_at'=>$now->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM)];
    }
}
