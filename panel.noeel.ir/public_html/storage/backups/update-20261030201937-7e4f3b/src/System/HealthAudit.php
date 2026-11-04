<?php
declare(strict_types=1);
namespace Babanoell\Panel\System;

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\HttpTransportInterface;
use Babanoell\Panel\Http\NativeHttpTransport;

/** Read-only, bounded network checks; does not query foreign databases or store provider payloads. */
final class HealthAudit
{
    public function __construct(
        private readonly Config $config,
        private readonly ?HttpTransportInterface $transport = null,
    ) {}

    /** @return list<array{name:string,state:string,http_status:?int,elapsed_ms:?int}> */
    public function probe(): array
    {
        $services = [
            ['name'=>'پنل', 'url'=>null, 'path'=>''],
            ['name'=>'هویت', 'url'=>$this->config->identityBaseUrl(), 'path'=>'/healthz'],
            ['name'=>'تسهیلات', 'url'=>$this->config->loansBaseUrl(), 'path'=>'/health'],
            ['name'=>'مدارک', 'url'=>$this->config->documentsBaseUrl(), 'path'=>'/health'],
            ['name'=>'همکاری', 'url'=>$this->config->partnersBaseUrl(), 'path'=>'/health'],
            ['name'=>'مالی', 'url'=>$this->config->financeBaseUrl(), 'path'=>'/health'],
            ['name'=>'ارتباطات', 'url'=>$this->config->integrationsBaseUrl(), 'path'=>'/health'],
        ];
        $transport = $this->transport ?? new NativeHttpTransport();
        $results=[];
        foreach ($services as $service) {
            $base = $service['url'];
            if ($base === null) {
                $results[] = ['name'=>$service['name'],'state'=>$this->config->isReady()?'healthy':'degraded','http_status'=>null,'elapsed_ms'=>null];
                continue;
            }
            if (!self::isAllowedUrl($base)) {
                $results[] = ['name'=>$service['name'],'state'=>'not_checked','http_status'=>null,'elapsed_ms'=>null];
                continue;
            }
            $start = hrtime(true);
            try {
                $response = $transport->request('GET',rtrim($base,'/').$service['path'],['Accept'=>'application/json','User-Agent'=>'NoelPanel-HealthAudit/1.0'],null,2);
                $state = self::state($response);
                $status = $response->status > 0 ? $response->status : null;
            } catch (\Throwable) {
                $state = 'unavailable';
                $status = null;
            }
            $results[] = ['name'=>$service['name'],'state'=>$state,'http_status'=>$status,'elapsed_ms'=>max(0,(int)round((hrtime(true)-$start)/1000000))];
        }
        return $results;
    }

    public static function isAllowedUrl(string $base): bool
    {
        // No arbitrary URL probes (e.g. internal network, localhost, credentials, fragments or query strings).
        $parts = parse_url($base);
        if (!is_array($parts) || strtolower((string)($parts['scheme']??''))!=='https'
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || isset($parts['port']) || (string)($parts['path']??'')!=='') return false;
        return preg_match('/^(?:identity|loans|documents|partners|finance|integrations)\.noeel\.ir$/Di',(string)$parts['host'])===1;
    }

    public static function state(HttpResponse $response): string
    {
        if (!$response->ok()) return 'unavailable';
        $data = $response->json['data']??$response->json;
        if (!is_array($data)) return 'reachable';
        $rawStatus = $data['status'] ?? null;
        $status = is_string($rawStatus) ? strtolower($rawStatus) : '';
        if (in_array($status,['error','down','unhealthy','degraded','not_ready'],true)) return 'degraded';
        return in_array($status,['ok','healthy','up','ready'],true)?'healthy':'reachable';
    }
}
