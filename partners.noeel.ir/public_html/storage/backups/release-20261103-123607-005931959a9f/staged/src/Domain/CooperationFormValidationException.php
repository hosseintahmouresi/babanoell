<?php
declare(strict_types=1);
namespace Babanoell\Partners\Domain;
final class CooperationFormValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly array $errors){parent::__construct('برخی فیلدهای فرم نیاز به اصلاح دارند.');}
}
