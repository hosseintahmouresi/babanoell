<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;
use Babanoell\Loans\Domain\CaseReadiness;
use Babanoell\Loans\Http\DocumentsClient;
use Babanoell\Loans\Http\PartnersClient;
use Babanoell\Loans\Repository\PdoLoanRepository;

final class CaseReadinessService
{
    public function __construct(private readonly PdoLoanRepository $repository,private readonly DocumentsClient $documents,private readonly PartnersClient $partners){}
    public function inspect(array $loan,string $bearer):array
    {
        $documentResponse=$this->documents->completion((string)$loan['id'],$bearer);
        $documents=$documentResponse->ok()&&is_array($documentResponse->json['data']??null)?$documentResponse->json['data']:null;
        $contracts=null;
        if(CaseReadiness::requiredContractKinds($loan)!==[]){
            $response=$this->partners->loanContracts((string)$loan['id'],$bearer);
            $contracts=$response->ok()&&is_array($response->json['data']['items']??null)?$response->json['data']['items']:null;
        }
        $proofs=$this->repository->verificationRequests((string)$loan['id']);
        return CaseReadiness::evaluate($loan,$documents,$contracts,$proofs)+['checked_at'=>gmdate(DATE_ATOM)];
    }
}
