<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
    public function version():string{return '202611080004';}
    public function name():string{return 'reference_signature_tokens';}
    public function up(PDO $pdo):void {
        // Historical proofs are never manufactured or rewritten during upgrade.
        $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS buyer_confirmation_token VARCHAR(40) NULL, ADD COLUMN IF NOT EXISTS platform_confirmation_token VARCHAR(40) NULL');
    }
};
