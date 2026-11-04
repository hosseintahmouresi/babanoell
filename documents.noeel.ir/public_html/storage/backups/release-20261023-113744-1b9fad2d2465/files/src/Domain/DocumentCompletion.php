<?php
declare(strict_types=1);

namespace Babanoell\Documents\Domain;

final class DocumentCompletion
{
    /**
     * @param list<string> $requiredTypes
     * @param list<array<string,mixed>> $newestFirstDocuments
     * @return array{complete:bool,required_count:int,approved_count:int,missing:list<string>,pending:list<string>,rejected:list<string>,items:list<array{document_type:string,complete:bool,review_status:string,document_id:?string}>}
     */
    public static function evaluate(array $requiredTypes, array $newestFirstDocuments): array
    {
        $required = [];
        foreach ($requiredTypes as $value) {
            $type = strtolower(trim((string) $value));
            if ($type !== '') $required[$type] = true;
        }
        $required = array_keys($required);

        $latest = [];
        foreach ($newestFirstDocuments as $row) {
            if (!is_array($row)) continue;
            $type = strtolower(trim((string) ($row['document_type'] ?? '')));
            if ($type !== '' && !isset($latest[$type])) $latest[$type] = $row;
        }

        $items = [];
        $missing = [];
        $pending = [];
        $rejected = [];
        $approvedCount = 0;

        foreach ($required as $type) {
            $row = $latest[$type] ?? null;
            $status = is_array($row) ? strtolower(trim((string) ($row['review_status'] ?? 'pending'))) : 'missing';
            if (!in_array($status, ['approved', 'rejected', 'pending', 'missing'], true)) $status = 'pending';
            $complete = $status === 'approved';

            if ($complete) $approvedCount++;
            elseif ($status === 'rejected') $rejected[] = $type;
            elseif ($status === 'missing') $missing[] = $type;
            else $pending[] = $type;

            $items[] = [
                'document_type' => $type,
                'complete' => $complete,
                'review_status' => $status,
                'document_id' => is_array($row) && is_string($row['id'] ?? null) ? $row['id'] : null,
            ];
        }

        return [
            'complete' => $approvedCount === count($required),
            'required_count' => count($required),
            'approved_count' => $approvedCount,
            'missing' => $missing,
            'pending' => $pending,
            'rejected' => $rejected,
            'items' => $items,
        ];
    }
}
