<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\StatusMachine;
use Babanoell\Loans\Domain\CreditValidationPolicy;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class WorkflowService
{
    public function __construct(
        private readonly PdoLoanRepository $repo,
        private readonly StatusMachine $machine,
    ) {}

    public function transition(
        string $requestId,
        string $actor,
        string $target,
        string $reason = '',
        ?string $assignedBankUserId = null,
        string $source = 'api',
    ): array {
        $request = $this->repo->getRequest($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('پرونده پیدا نشد.');
        }

        $reason = trim($reason);
        $candidate = $request;
        if ($assignedBankUserId !== null) {
            $candidate['assigned_bank_user_id'] = trim($assignedBankUserId);
        }

        [$ok, $message] = $this->machine->can((string)$request['status'], $target, $candidate);
        if (!$ok) {
            $this->repo->recordEvent($requestId, 'request.transition.denied', $actor, 'denied', [
                'from' => $request['status'],
                'to' => $target,
                'reason' => $reason,
                'message' => $message,
                'source' => $source,
            ]);
            throw new InvalidArgumentException($message);
        }

        try {
            CreditValidationPolicy::assertForTarget($request, $target);
        } catch (InvalidArgumentException $e) {
            $this->repo->recordEvent($requestId, 'request.transition.denied', $actor, 'denied', [
                'from' => $request['status'],
                'to' => $target,
                'reason' => $reason,
                'message' => $e->getMessage(),
                'source' => $source,
                'guard' => 'credit_validation',
            ]);
            throw $e;
        }

        if ($this->machine->requiresReason($target) && $reason === '') {
            $message = 'برای این تغییر وضعیت، ثبت دلیل الزامی است.';
            $this->repo->recordEvent($requestId, 'request.transition.denied', $actor, 'denied', [
                'from' => $request['status'],
                'to' => $target,
                'reason' => '',
                'message' => $message,
                'source' => $source,
            ]);
            throw new InvalidArgumentException($message);
        }

        return $this->repo->applyTransition(
            $requestId,
            (string)$request['status'],
            $target,
            $actor,
            $reason,
            $source,
            $assignedBankUserId,
        );
    }

    /** @return array<int,array{code:string,label:string,requires_reason:bool}> */
    public function allowed(array $request): array
    {
        $out = [];
        foreach ($this->machine->transitions((string)$request['status']) as $status) {
            [$ok] = $this->machine->can((string)$request['status'], $status, $request);
            $creditOk = true;
            try { CreditValidationPolicy::assertForTarget($request, $status); } catch (InvalidArgumentException) { $creditOk = false; }
            if (($ok && $creditOk) || ($status === 'expert_approved' && trim((string)($request['assigned_bank_user_id'] ?? '')) === '' && $creditOk)) {
                // expert_approved remains visible so the UI can ask for the bank-user assignment.
                $out[] = [
                    'code' => $status,
                    'label' => $this->machine->label($status),
                    'requires_reason' => $this->machine->requiresReason($status),
                ];
            }
        }
        return $out;
    }
}
