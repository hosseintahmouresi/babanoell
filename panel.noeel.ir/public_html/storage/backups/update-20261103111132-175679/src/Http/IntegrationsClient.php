<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;
use Babanoell\Panel\App\Config;
final class IntegrationsClient
{
    public function __construct(private readonly Config $config, private readonly HttpTransportInterface $transport=new NativeHttpTransport()) {}
    public function health(): HttpResponse
    {
        return $this->transport->request('GET', $this->config->integrationsBaseUrl().'/health',
            ['Accept'=>'application/json','User-Agent'=>'NoelPanel/2.12.0'],null,$this->config->httpTimeoutSeconds());
    }
}
