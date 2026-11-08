<?php
declare(strict_types=1);
namespace Babanoell\Auth\SmartAuth;
final class AuthFailure extends \RuntimeException {
 public function __construct(public readonly int $status,public readonly string $publicCode,string $message,public readonly int $retryAfter=0){parent::__construct($message);}
}
