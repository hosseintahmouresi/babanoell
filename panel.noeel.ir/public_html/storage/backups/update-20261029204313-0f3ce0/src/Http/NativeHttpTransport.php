<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;

use Throwable;

final class NativeHttpTransport implements HttpTransportInterface
{
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 6): HttpResponse
    {
        return function_exists('curl_init')
            ? $this->curl($method, $url, $headers, $body, $timeoutSeconds)
            : $this->stream($method, $url, $headers, $body, $timeoutSeconds);
    }

    /** @param array<string,string> $headers */
    private function curl(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        $ch = curl_init($url);
        if ($ch === false) return new HttpResponse(0, null, '', 'curl_init_failed');
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = 'curl_' . (string) curl_errno($ch);
            curl_close($ch);
            return new HttpResponse(0, null, '', $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $this->decode($status, (string) $raw);
    }

    /** @param array<string,string> $headers */
    private function stream(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;
        $options = ['http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]];
        try {
            $raw = @file_get_contents($url, false, stream_context_create($options));
            if ($raw === false) return new HttpResponse(0, null, '', 'stream_request_failed');
            $status = 0;
            foreach ($http_response_header ?? [] as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) { $status = (int) $m[1]; break; }
            }
            return $this->decode($status, $raw);
        } catch (Throwable) {
            return new HttpResponse(0, null, '', 'stream_exception');
        }
    }

    private function decode(int $status, string $body): HttpResponse
    {
        $json = json_decode($body, true);
        return new HttpResponse($status, is_array($json) ? $json : null, $body);
    }
}
