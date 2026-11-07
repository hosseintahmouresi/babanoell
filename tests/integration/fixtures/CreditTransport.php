<?php
declare(strict_types=1);
namespace Babanoell\Tests;

use Babanoell\Integrations\Http\TransportInterface;
use Babanoell\Integrations\Support\ProviderException;

/** Only injected into disposable service copies by the acceptance harness. Never part of a release. */
final class CreditTransport implements TransportInterface
{
    public function __construct(private readonly string $stateFile) {}
    public function request(string $method,string $url,?array $payload=null,array $headers=[],int $timeout=18,string $encoding='json',array $hosts=[]):array
    {
        if(parse_url($url,PHP_URL_HOST)!=='mycredit.ics24.ir')throw new \RuntimeException('Unexpected synthetic provider host');
        $handle=fopen($this->stateFile,'c+');if($handle===false)throw new \RuntimeException('Fixture unavailable');
        flock($handle,LOCK_EX);
        try{
            $state=json_decode(stream_get_contents($handle),true)??[];$mode=$state['mode']??'disabled';
            if($mode==='disabled')throw new ProviderException('credit_not_configured',503);
            $path=(string)parse_url($url,PHP_URL_PATH);$state['calls'][$path]=($state['calls'][$path]??0)+1;
            if($path==='/Connect/Token')$data=['accessToken'=>'synthetic-token','apiKey'=>'synthetic-key'];
            elseif($path==='/b2b/api/request'){
                $reference='request_'.substr(hash('sha256',json_encode($payload)),0,24);
                $state['subjects'][$reference]=['national_code'=>$payload['RealPersonNationalCode'],'mobile'=>$payload['MobileNumber'],'otp'=>false];$data=$reference;
            }elseif(preg_match('~^/b2b/api/request/([A-Za-z0-9_-]+)/validate$~',$path,$match)){
                if(($payload['Token']??'')!=='123456')return ['status'=>422,'valid_json'=>true,'data'=>['hasError'=>true]];
                if(!isset($state['subjects'][$match[1]]))throw new \RuntimeException('Unknown synthetic reference');
                $state['subjects'][$match[1]]['otp']=true;$data='InProcessing';
            }elseif(preg_match('~^/b2b/api/request/([A-Za-z0-9_-]+)/status$~',$path,$match)){
                if(empty($state['subjects'][$match[1]]['otp']))throw new \RuntimeException('Synthetic status before OTP');
                $report='report_'.substr(hash('sha256',$match[1]),0,24);$state['reports'][$report]=$match[1];
                $status=match($mode){'processing'=>'InProcessing','lost'=>'ReportGeneratedButDataLost',default=>'ReportGenerated'};
                $data=['status'=>$status,'statusTitle'=>'Synthetic provider status','reportLink'=>$status==='ReportGenerated'?'https://mycredit.ics24.ir/report/'.$report:''];
            }elseif(preg_match('~^/report/([A-Za-z0-9_-]+)/json$~',$path,$match)){
                $reference=$state['reports'][$match[1]]??'';$national=$state['subjects'][$reference]['national_code']??'';
                if($mode==='wrong_subject')$national='1111111111';
                $data=['score'=>['score'=>610,'risk'=>'B1','description'=>'Synthetic report', 'personInformation'=>['name'=>'Synthetic','family'=>'Applicant','nationalCode'=>$national]],
                    'report'=>['summaryInformationRelatedToContractsSum'=>[['inProgressContractsCount'=>2,'overdueAmount'=>0,'totalAmount'=>1000000]]],
                    'bouncedCheque'=>['bouncedCheques'=>[]]];
                if($mode==='malformed')$data=['message'=>'not a report'];
            }elseif(str_ends_with($path,'/reGenerateReport'))$data=true;
            else throw new \RuntimeException('Unexpected synthetic provider path');
            rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($state,JSON_THROW_ON_ERROR));fflush($handle);
            return ['status'=>200,'valid_json'=>true,'data'=>['hasError'=>false,'data'=>$data]];
        }finally{flock($handle,LOCK_UN);fclose($handle);}
    }
}
