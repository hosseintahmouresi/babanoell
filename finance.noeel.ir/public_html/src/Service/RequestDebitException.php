<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;
final class RequestDebitException extends \RuntimeException
{
    public function __construct(public readonly string $publicCode,string $message,public readonly int $httpStatus=409){parent::__construct($message);}
}
