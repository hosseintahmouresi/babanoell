<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Support;
final class ProviderException extends \RuntimeException {
 public function __construct(public readonly string $publicCode,public readonly int $httpStatus=502) {parent::__construct($publicCode);}
}
