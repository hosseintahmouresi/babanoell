<?php
declare(strict_types=1);
namespace Babanoell\Panel\Support;

/** Session-bound submission identities; no applicant data is stored here. */
final class FormOperation
{
    private const TTL=86400;
    public static function issue(string $scope):string
    {
        $forms=is_array($_SESSION['noel_form_operations']??null)?$_SESSION['noel_form_operations']:[];
        foreach($forms as$key=>$form)if(!is_array($form)||(int)($form['created']??0)<time()-self::TTL)unset($forms[$key]);
        while(count($forms)>=16)array_shift($forms);
        $key='operation-'.bin2hex(random_bytes(16));$forms[$key]=['scope'=>$scope,'created'=>time()];
        $_SESSION['noel_form_operations']=$forms;return $key;
    }
    public static function valid(string $scope,mixed $key):bool
    {
        if(!is_string($key)||!preg_match('/^operation-[a-f0-9]{32}$/D',$key))return false;
        $form=$_SESSION['noel_form_operations'][$key]??null;
        return is_array($form)&&($form['scope']??null)===$scope&&is_int($form['created']??null)
            &&$form['created']>=time()-self::TTL&&$form['created']<=time();
    }
    public static function reuseOrIssue(string $scope,mixed $key):string{return self::valid($scope,$key)?$key:self::issue($scope);}
    public static function bind(string $scope,string $key,array $payload):bool
    {
        if(!self::valid($scope,$key))return false;
        $hash=hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $previous=$_SESSION['noel_form_operations'][$key]['payload_hash']??null;
        if(is_string($previous))return hash_equals($previous,$hash);
        $_SESSION['noel_form_operations'][$key]['payload_hash']=$hash;return true;
    }
    public static function complete(string $scope,string $key):void{if(self::valid($scope,$key))unset($_SESSION['noel_form_operations'][$key]);}
}
