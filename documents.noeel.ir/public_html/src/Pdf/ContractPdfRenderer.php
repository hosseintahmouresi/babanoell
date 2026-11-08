<?php
declare(strict_types=1);
namespace Babanoell\Documents\Pdf;
use RuntimeException;

/** Generates PDF entirely on the server from the frozen signed snapshot, without external fetches. */
final class ContractPdfRenderer
{
    public static function available(): bool
    {
        if(class_exists(\Mpdf\Mpdf::class))return true;
        $file=dirname(__DIR__, 2).'/vendor/autoload.php';
        if(!is_file($file))return false;
        try{require_once $file;return class_exists(\Mpdf\Mpdf::class);}catch(\Throwable){return false;}
    }

    public function render(array $contract): string
    {
        if (($contract['status'] ?? '') !== 'signed') throw new RuntimeException('فقط قرارداد امضاشده امکان خروجی نهایی PDF دارد.');
        $body=(string)($contract['rendered_content']??'');
        $hash=(string)($contract['rendered_hash']??'');
        if($body==='' || strlen($body)>2*1024*1024 || !preg_match('/^[a-f0-9]{64}$/',$hash) || !hash_equals(hash('sha256',$body),$hash)) {
            throw new RuntimeException('اثر انگشت نسخه منجمد قرارداد معتبر نیست.');
        }
        if(!class_exists(\DOMDocument::class) || !function_exists('mb_strlen')) {
            throw new RuntimeException('افزونه‌های PHP dom و mbstring برای PDF لازم‌اند.');
        }
        $autoload=dirname(__DIR__, 2).'/vendor/autoload.php';
        if(!class_exists(\Mpdf\Mpdf::class) && is_file($autoload)) require_once $autoload;
        if(!class_exists(\Mpdf\Mpdf::class)) throw new RuntimeException('موتور mPDF در Documents نصب نشده است؛ ابتدا Composer dependencies را نصب کنید.');
        $reference=in_array((string)($contract['template_code']??''),['hami_plus_reference','hami_retirees_reference'],true);
        $safe=self::safeMarkup($body,$reference);
        if($reference)$safe.=\Babanoell\Documents\Contract\SignatureCertificate::render($contract);
        $no=htmlspecialchars((string)($contract['contract_no']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $revision=(int)($contract['revision']??1);
        $signAt=htmlspecialchars((string)($contract['signed_at']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $method=(string)($contract['mode']??'')==='digital'?'تأیید دیجیتال با رمز یک‌بارمصرف':'امضای فیزیکی ثبت‌شده';
        $shortHash=htmlspecialchars($hash,ENT_QUOTES,'UTF-8');
        $tmp=dirname(__DIR__, 2).'/storage/pdf-temp';
        if(!is_dir($tmp) && !mkdir($tmp,0700,true) && !is_dir($tmp)) throw new RuntimeException('فضای موقت PDF در دسترس نیست.');
        $fontRoot=dirname(__DIR__,2).'/resources/fonts';
        foreach(['Regular','Bold'] as $weight) {
            if(!is_readable($fontRoot.'/YekanBakhFaNum-'.$weight.'.ttf')) throw new RuntimeException('فونت محلی قرارداد در بسته سامانه موجود نیست.');
        }
        $fontDefaults=(new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData=$fontDefaults['fontdata'];
        $fontData['noelyekan']=['R'=>'YekanBakhFaNum-Regular.ttf','B'=>'YekanBakhFaNum-Bold.ttf','useOTL'=>0xFF,'useKashida'=>0];
        $config=['mode'=>'utf-8','format'=>'A4','default_font'=>'noelyekan','directionality'=>'rtl',
            'margin_left'=>15,'margin_right'=>15,'margin_top'=>29,'margin_bottom'=>23,'margin_header'=>9,'margin_footer'=>8,
            'tempDir'=>$tmp,'autoScriptToLang'=>false,'autoLangToFont'=>false,
            'fontDir'=>array_merge((new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],[$fontRoot]),'fontdata'=>$fontData];
        $pdf=new \Mpdf\Mpdf($config);
        $pdf->SetTitle('قرارداد '.$no.' - نسخه '.$revision);
        $pdf->SetAuthor('سامانه نوئل');
        $pdf->SetDisplayMode('fullpage');
        $logo=dirname(__DIR__,2).'/public/assets/brand-logo.png';
        $img='';if(is_file($logo)&&is_readable($logo)&&filesize($logo)>0&&filesize($logo)<65536){
            $png=file_get_contents($logo);if(is_string($png)&&str_starts_with($png,"\x89PNG"))$img='<img src="data:image/png;base64,'.base64_encode($png).'" width="90" style="vertical-align:middle"> ';
        }
        $header='<div style="font-size:10pt;direction:rtl;border-bottom:1px solid #27374d;padding-bottom:5px">'.$img.'<b>پلتفرم اعتباری نوئل</b><span style="float:left">'.$no.' | نسخه '.$revision.'</span></div>';
        $footer='<div style="border-top:1px solid #999;padding-top:4px;font-size:8pt;direction:rtl">نسخه '.$revision.' | شناسه اثر انگشت: '.substr($shortHash,0,18).' <span style="float:left">{PAGENO} / {nbpg}</span></div>';
        $pdf->SetHTMLHeader($header,'O');$pdf->SetHTMLHeader($header,'E');
        $pdf->SetHTMLFooter($footer,'O');$pdf->SetHTMLFooter($footer,'E');
        $pdf->WriteHTML('<style>body{font-size:11pt;line-height:1.9;color:#17243b;direction:rtl;text-align:right}p{margin:0 0 8px}h1,h2,h3{page-break-after:avoid;margin:10px 0}table{border-collapse:collapse;width:100%}td,th{border:0.6px solid #999;padding:5px}ul,ol{margin:5px 0} .certificate{border-top:1px solid #777;margin-top:22px;padding:10px 0;font-size:9pt}</style>',\Mpdf\HTMLParserMode::HEADER_CSS);
        if($reference)$pdf->WriteHTML((string)file_get_contents(dirname(__DIR__,2).'/resources/contracts/reference-layout.css'),\Mpdf\HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML('<div dir="rtl">'.$safe.'</div><div class="certificate" dir="rtl"><b>گواهی وضعیت قرارداد</b><p>شماره سند: '.$no.' | نسخه: '.$revision.'</p><p>زمان ثبت امضا: '.$signAt.'</p><p>روش تأیید: '.$method.'</p><p>SHA-256 متن امضاشده: <span dir="ltr">'.$shortHash.'</span></p></div>',\Mpdf\HTMLParserMode::HTML_BODY);
        $output=$pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
        if(!is_string($output)||strlen($output)<500||!str_starts_with($output,'%PDF-'))throw new RuntimeException('تولید خروجی PDF معتبر انجام نشد.');
        if(strlen($output)>12*1024*1024)throw new RuntimeException('اندازه PDF از سقف مجاز بیشتر است.');
        return $output;
    }

    /** Allowlist prevents active content, remote images/stylesheets, CSS URL fetches and events. */
    public static function safeMarkup(string $html,bool $reference=false): string
    {
        if(!class_exists(\DOMDocument::class))throw new RuntimeException('PHP DOM unavailable');
        $doc=new \DOMDocument('1.0','UTF-8');
        $flags=LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING;
        $loaded=$doc->loadHTML('<?xml encoding="UTF-8"><div id="pdf-root">'.$html.'</div>',$flags);
        if(!$loaded)throw new RuntimeException('ساختار HTML قرارداد برای PDF معتبر نیست.');
        $roots=$doc->getElementsByTagName('div');if($roots->length<1)throw new RuntimeException('محتوای قرارداد خالی است.');
        $allow=array_flip(['div','span','p','br','h1','h2','h3','h4','h5','h6','table','thead','tbody','tr','td','th','b','strong','i','em','u','ul','ol','li','blockquote','section','header','footer','small','sup','sub','hr']);
        $walk=function(\DOMNode $node)use(&$walk,$allow,$reference):string{
            if($node instanceof \DOMText)return htmlspecialchars($node->nodeValue??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            if(!($node instanceof \DOMElement))return '';
            $tag=strtolower($node->tagName);
            if($reference&&$tag==='img'){
                $src=$node->getAttribute('src');
                if(!preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~D',$src,$image))return '';
                $bytes=base64_decode($image[1],true);if(!is_string($bytes)||strlen($bytes)>100000)return '';
                $size=@getimagesizefromstring($bytes);if(!is_array($size)||($size['mime']??'')!=='image/png'||$size[0]>2000||$size[1]>2000)return '';
                return '<img src="'.htmlspecialchars($src,ENT_QUOTES,'UTF-8').'" width="110">';
            }

            if(!isset($allow[$tag]))return ''; // Never traverse script, svg, image, link or form nodes.
            $attrs='';
            if($reference&&preg_match('/^[a-zA-Z0-9 _-]{1,200}$/D',$node->getAttribute('class')))$attrs.=' class="'.htmlspecialchars($node->getAttribute('class'),ENT_QUOTES,'UTF-8').'"';
            foreach(['dir','align','colspan','rowspan'] as $key){
                $value=trim($node->getAttribute($key));
                if($key==='dir' && in_array($value,['rtl','ltr'],true))$attrs.=' dir="'.$value.'"';
                if($key==='align' && in_array($value,['left','right','center','justify'],true))$attrs.=' align="'.$value.'"';
                if(in_array($key,['colspan','rowspan'],true)&&ctype_digit($value)&&(int)$value>=1&&(int)$value<=20)$attrs.=' '.$key.'="'.$value.'"';
            }
            if(in_array($tag,['br','hr'],true))return '<'.$tag.$attrs.'>';
            $content='';foreach($node->childNodes as $child)$content.=$walk($child);
            return '<'.$tag.$attrs.'>'.$content.'</'.$tag.'>';
        };
        $out='';foreach($roots->item(0)->childNodes as $node)$out.=$walk($node);
        if(trim(strip_tags($out))==='')throw new RuntimeException('متن قرارداد برای تولید PDF خالی است.');
        return $out;
    }
}
