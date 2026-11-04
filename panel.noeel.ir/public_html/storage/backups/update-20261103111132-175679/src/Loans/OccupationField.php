<?php
declare(strict_types=1);
namespace Babanoell\Panel\Loans;

use Babanoell\Panel\View\Renderer;

/** UI values mirror the Loans owner contract; Persian labels are not API codes. */
final class OccupationField
{
    public const LABELS=['official_employee'=>'کارمند رسمی','contract_employee'=>'کارمند قراردادی','worker'=>'کارگر','freelancer'=>'آزاد','retired'=>'بازنشسته','pensioner'=>'مستمری بگیر','employer'=>'کارفرما','other'=>'سایر'];

    public static function render(string$value):string
    {
        $options='<option value="">انتخاب شغل</option>';
        // Keep old data visible; do not infer an employment category on the user's behalf.
        if($value!==''&&!isset(self::LABELS[$value]))$options.='<option value="'.Renderer::e($value).'" selected>مقدار قبلی: '.Renderer::e($value).'</option>';
        foreach(self::LABELS as$code=>$label)$options.='<option value="'.$code.'"'.($value===$code?' selected':'').'>'.$label.'</option>';
        return'<div class="bn-field"><label class="bn-label">شغل</label><select name="occupation">'.$options.'</select><span class="bn-helper">شغل متقاضی را از فهرست انتخاب کنید.</span></div>';
    }
}
