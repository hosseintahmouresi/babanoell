<?php
declare(strict_types=1);
namespace Babanoell\Finance\Http;
interface GreenpayGateway
{
    public function greenpayCapabilities():HttpResponse;
    public function greenpayConfiguration():HttpResponse;
    public function greenpayOtp(string $pocket,int $amount,string $key):HttpResponse;
    public function greenpayRequestDebit(array $payload,string $key):HttpResponse;
    public function greenpayTransfer(array $payload,string $key):HttpResponse;
}
