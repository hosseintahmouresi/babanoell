<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;

final class GreenpayVault
{
    public function __construct(private readonly string $key){}
    private function check():void
    {
        if(!function_exists('sodium_crypto_secretbox')||strlen($this->key)!==32)throw new RequestDebitException('greenpay_encryption_not_configured','کلید نگهداری امن کیف پول GreenPay تنظیم نشده است.',503);
    }
    public function encrypt(string $value):string
    {
        $this->check();$nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,$this->key));
    }
    public function decrypt(string $value):string
    {
        $this->check();$v=base64_decode($value,true);
        if(!is_string($v)||strlen($v)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES)throw new RequestDebitException('greenpay_snapshot_invalid','اطلاعات کیف پول قابل تأیید نیست.',503);
        $out=sodium_crypto_secretbox_open(substr($v,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($v,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->key);
        if(!is_string($out))throw new RequestDebitException('greenpay_snapshot_invalid','اطلاعات کیف پول قابل تأیید نیست.',503);return $out;
    }
    public function fingerprint(string $value):string{$this->check();return hash_hmac('sha256',$value,$this->key);}
}
