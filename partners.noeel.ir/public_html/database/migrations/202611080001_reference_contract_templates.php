<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
    public function version(): string {return '202611080001';}
    public function name(): string {return 'reference_contract_templates';}
    public function up(PDO $pdo): void {
        $dir=dirname(__DIR__,2).'/resources/contracts/';
        $q=$pdo->prepare("INSERT IGNORE INTO contract_templates(id,code,name,contract_kind,version,status,content_html,variables_json,content_hash,created_by_user_id,published_at) VALUES(:id,:code,:name,'facility_agreement',1,'active',:html,:variables,:hash,:actor,NOW(6))");
        foreach(['hami_plus_reference'=>['hami-plus-digital-loan-contract.html','قرارداد فروش اقساطی حامی پلاس — مرجع'],
            'hami_retirees_reference'=>['hami-retirees-final-loan-contract.html','قرارداد نهایی حامی بازنشستگان — مرجع']] as $code=>[$file,$name]){
            $html=file_get_contents($dir.$file);if($html===false)throw new RuntimeException('فایل قالب مرجع موجود نیست.');
            preg_match_all('/\[([a-z_]+)\]/',$html,$matches);
            $q->execute(['id'=>substr(hash('sha256','reference-template:'.$code),0,26),'code'=>$code,'name'=>$name,'html'=>$html,
                'variables'=>json_encode(array_values(array_unique($matches[1])),JSON_THROW_ON_ERROR),'hash'=>hash('sha256',$html),'actor'=>str_repeat('0',26)]);
        }
    }
};
