<?php
declare(strict_types=1);
namespace Babanoell\Panel\Partners;
use Babanoell\Panel\View\Renderer;

final class CooperationFormView
{
    private static function e(mixed$value):string{return Renderer::e(is_scalar($value)?(string)$value:'');}
    public static function values(string$type,array$values):array
    {
        foreach(['full_name'=>$type==='legal'?'company_name':'store_owner_name','trade_name'=>'store_name','mobile'=>'mobile_number','national_code'=>'applicant_id_number','activity'=>'activity_field','phone'=>'phone_number','address'=>$type==='legal'?'company_address':'store_address']as$old=>$new)if(!array_key_exists($new,$values)&&array_key_exists($old,$values))$values[$new]=$values[$old];return$values;
    }
    public static function valid(array$d):bool
    {
        if(!is_int($d['revision']??null)||$d['revision']<1||!is_array($d['catalog']??null)||count($d['catalog'])>40||$d['catalog']===[]||!is_array($d['fields']??null)||!is_array($d['options']??null))return false;
        foreach($d['catalog']as$key=>$f){if(!is_string($key)||!preg_match('/^[a-z][a-z0-9_]{1,60}$/D',$key)||!is_array($f)||!is_string($f['label']??null)||strlen($f['label'])>600||!in_array($f['type']??null,['text','textarea','email','select','file'],true)||!in_array($f['form']??null,['individual','legal','both'],true)||!is_bool($d['fields'][$key]['visible']??null)||!is_bool($d['fields'][$key]['required']??null))return false;
            if($f['type']==='select'){if(!is_string($f['options_key']??null)||!is_array($d['options'][$f['options_key']]??null)||!array_is_list($d['options'][$f['options_key']])||count($d['options'][$f['options_key']])>100)return false;foreach($d['options'][$f['options_key']]as$v)if(!is_string($v)||strlen($v)>600)return false;}}
        return true;
    }
    public static function fields(array$d,string$type,array$values,array$errors=[]):string
    {
        $html='';foreach($d['catalog']as$key=>$f){$rule=$d['fields'][$key];if(!$rule['visible']||$f['type']==='file'||!in_array($f['form'],['both',$type],true))continue;
            $value=is_scalar($values[$key]??null)?(string)$values[$key]:'';$id='cooperation-'.$key;$error=is_string($errors[$key]??null)?$errors[$key]:'';
            $attrs=' id="'.$id.'" name="'.$key.'"'.($rule['required']?' required':'').($error!==''?' aria-invalid="true" aria-describedby="'.$id.'-error"':'');
            $html.='<div class="bn-field'.($f['type']==='textarea'?' bn-grid-span-2':'').'"><label class="bn-label" for="'.$id.'">'.self::e($f['label']).($rule['required']?' <span class="bn-required">*</span>':'').'</label>';
            if($f['type']==='textarea')$html.='<textarea'.$attrs.' rows="3" maxlength="4000">'.self::e($value).'</textarea>';
            elseif($f['type']==='select'){$html.='<select'.$attrs.'><option value="">انتخاب کنید</option>';$options=$d['options'][$f['options_key']];if($value!==''&&!in_array($value,$options,true))$html.='<option selected disabled value="'.self::e($value).'">'.self::e($value).' — گزینه فعال انتخاب کنید</option>';foreach($options as$o)$html.='<option value="'.self::e($o).'"'.($o===$value?' selected':'').'>'.self::e($o).'</option>';$html.='</select>';}
            else{$inputType=$key==='mobile_number'||$key==='phone_number'?'tel':($key==='website'?'url':$f['type']);$numeric=in_array($key,['mobile_number','phone_number','postal_code','applicant_id_number'],true);$html.='<input type="'.$inputType.'"'.$attrs.' value="'.self::e($value).'" maxlength="512"'.($numeric?' inputmode="numeric"':'').'>';}
            if($error!=='')$html.='<small class="bn-alert--error" id="'.$id.'-error" role="alert">'.self::e($error).'</small>';$html.='</div>';
        }return$html;
    }
    public static function documentSummary(array$d,string$type):string
    {
        $rows='';foreach($d['catalog']as$key=>$f)if($f['type']==='file'&&$d['fields'][$key]['visible']&&in_array($f['form'],['both',$type],true))$rows.='<li>'.self::e($f['label']).($d['fields'][$key]['required']?' <strong>الزامی</strong>':' — اختیاری').'</li>';
        return$rows===''?'':'<section class="bn-section"><h2 class="bn-sectiontitle">مدارک گام بعد</h2><p class="bn-helper">ابتدا اطلاعات را ثبت کنید؛ سپس مدارک زیر در صفحه درخواست بارگذاری می‌شوند. مدارک الزامی پیش از بررسی و تأیید کنترل می‌شوند.</p><ul>'.$rows.'</ul></section>';
    }
    public static function settings(array$d):string
    {
        $html='<section class="bn-section"><h2 class="bn-sectiontitle">گزینه‌های انتخابی</h2><div class="bn-grid bn-grid--2">';
        foreach(['activity_field_options'=>'زمینه فعالیت','how_heard_options'=>'نحوه آشنایی']as$key=>$label)$html.='<div class="bn-field"><label class="bn-label" for="options-'.$key.'">'.$label.'</label><textarea rows="7" id="options-'.$key.'" name="options['.$key.']">'.self::e(implode("\n",$d['options'][$key]??[])).'</textarea><small class="bn-field-hint">هر گزینه در یک خط؛ حداکثر صد گزینه.</small></div>';
        $html.='</div></section>';
        foreach(['individual'=>'اطلاعات حقیقی','legal'=>'اطلاعات حقوقی','both'=>'اطلاعات و مدارک مشترک']as$form=>$label){$html.='<section class="bn-section"><h2 class="bn-sectiontitle">'.$label.'</h2><div class="bn-grid bn-grid--2">';
            foreach($d['catalog']as$key=>$f){if($f['form']!==$form)continue;$r=$d['fields'][$key];$mode=!$r['visible']?'hidden':($r['required']?'required':'optional');$locked=in_array($key,['store_owner_name','company_name'],true);
                $html.='<div class="bn-field"><label class="bn-label" for="mode-'.$key.'">'.self::e($f['label']).($f['type']==='file'?' — مدرک':'').'</label>';
                if($locked)$html.='<input type="hidden" name="modes['.$key.']" value="required"><span class="bn-helper">همواره نمایش داده می‌شود و الزامی است.</span>';
                else{$html.='<select id="mode-'.$key.'" name="modes['.$key.']">';foreach(['hidden'=>'پنهان','optional'=>'نمایش اختیاری','required'=>'نمایش الزامی']as$value=>$text)$html.='<option value="'.$value.'"'.($value===$mode?' selected':'').'>'.$text.'</option>';$html.='</select>';}$html.='</div>';
            }$html.='</div></section>';
        }return$html;
    }
    public static function record(array$catalog,string$type,array$data):string
    {
        $rows='';foreach($catalog as$key=>$f){if($f['type']==='file'||!in_array($f['form'],['both',$type],true)||!isset($data[$key])||!is_scalar($data[$key]))continue;$rows.='<div class="bn-detail-item"><span>'.self::e($f['label']).'</span><strong>'.nl2br(self::e($data[$key])).'</strong></div>';}
        return$rows===''?'':'<section class="bn-section"><h2 class="bn-sectiontitle">اطلاعات ثبت‌شده</h2><div class="bn-detail-grid">'.$rows.'</div></section>';
    }
}
