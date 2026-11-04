<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
final class HttpResponse
{
    /** @param array<string,mixed>|null $json */
    public function __construct(public readonly int $status, public readonly ?array $json, public readonly string $body='', public readonly ?string $networkError=null) {}
    public function ok(): bool { return $this->status>=200 && $this->status<300; }
    public function errorCode(): ?string { $v=$this->json['error']['code']??null; return is_string($v)?$v:null; }
}
