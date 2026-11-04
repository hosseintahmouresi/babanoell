<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;
use Babanoell\Loans\Domain\PlanSettingsValidator;
use Babanoell\Loans\Domain\RequiredDocumentCatalog;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;
final class PlanService
{
    public function __construct(private readonly PdoLoanRepository $repo,private readonly PlanSettingsValidator $validator){}
    public function create(string $actor,array $input): array
    {
        $code=strtoupper(trim((string)($input['code']??'')));$name=trim((string)($input['name']??''));$type=strtolower(trim((string)($input['type']??'')));if($code===''||!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$code))throw new InvalidArgumentException('کد طرح باید ۳ تا ۸۰ نویسه و شامل حروف انگلیسی، عدد، خط تیره یا زیرخط باشد.');if($name==='')throw new InvalidArgumentException('نام طرح الزامی است.');$settings=$this->validator->normalized(is_array($input['settings']??null)?$input['settings']:[]);$errors=$this->validator->validate($type,$settings);if($errors!==[])throw new InvalidArgumentException(implode(' ',$errors));return$this->repo->createPlan($actor,$code,$name,$type,$settings,is_array($input['display_fields']??null)?$input['display_fields']:[],RequiredDocumentCatalog::normalize(is_array($input['required_documents']??null)?$input['required_documents']:[]),trim((string)($input['conditions']??'')));
    }
    public function revise(string $id,string $actor,array $input): array
    {
        $old=$this->repo->getPlan($id);if($old===null)throw new InvalidArgumentException('طرح پیدا نشد.');$name=trim((string)($input['name']??$old['name']));$requestedType=strtolower(trim((string)($input['type']??$old['type'])));if($requestedType!==(string)$old['type'])throw new InvalidArgumentException('نوع طرح بعد از ایجاد قابل تغییر نیست. برای نوع محاسبه متفاوت، یک طرح جدید بسازید.');$type=(string)$old['type'];$settings=$this->validator->normalized(is_array($input['settings']??null)?$input['settings']:$old['settings']);$errors=$this->validator->validate($type,$settings);if($name==='')$errors[]='نام طرح الزامی است.';if($errors!==[])throw new InvalidArgumentException(implode(' ',array_unique($errors)));return$this->repo->revisePlan($id,$actor,$name,$type,$settings,is_array($input['display_fields']??null)?$input['display_fields']:$old['display_fields'],RequiredDocumentCatalog::normalize(is_array($input['required_documents']??null)?$input['required_documents']:(is_array($old['required_documents']??null)?$old['required_documents']:[])),trim((string)($input['conditions']??$old['conditions_text']??'')));
    }
    public function status(string $id,string $status): array
    { $status=strtolower(trim($status));if(!in_array($status,['draft','active','inactive','archived'],true))throw new InvalidArgumentException('وضعیت طرح معتبر نیست.');return$this->repo->setPlanStatus($id,$status); }
}
