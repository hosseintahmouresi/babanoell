<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
use RuntimeException;

/** Resolve only identities supplied by the authenticated contract owner service. */
final class ContractDocumentOwner
{
    public static function resolve(array $contract): string
    {
        $signer=trim((string)($contract['signer_user_id']??''));
        $owner=$signer!==''?$signer:trim((string)($contract['created_by_user_id']??''));
        if(!preg_match('/^(?!0{26}$)[a-f0-9]{26}$/D',$owner)){
            throw new RuntimeException('مالک معتبر برای سند قرارداد مشخص نشده است.');
        }
        return $owner;
    }
}
