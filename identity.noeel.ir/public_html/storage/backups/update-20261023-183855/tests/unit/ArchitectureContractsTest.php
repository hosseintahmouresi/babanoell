<?php
declare(strict_types=1);

use Babanoell\Auth\App\Config;
use Babanoell\Auth\Platform\RequestContext;
use Babanoell\Auth\Platform\ServiceAuth;
use Babanoell\Auth\Platform\ServiceBoundary;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testRequestContextPreservesValidCorrelationId(): void
    {
        Assertions::same('req_phase2a_123456', RequestContext::initialize('req_phase2a_123456', 'identity'));
        Assertions::same('req_phase2a_123456', RequestContext::id());
    }

    public function testServiceAuthSignsAndVerifiesCanonicalRequest(): void
    {
        RequestContext::set('req_phase2a_123456');
        $headers = ServiceAuth::outbound('panel', 'POST', '/api/v1/auth/me', '{"x":1}', 'shared-secret-123');
        Assertions::same('panel', $headers[ServiceAuth::HEADER_SERVICE] ?? null);
        Assertions::true(isset($headers[ServiceAuth::HEADER_SIGNATURE]));
        $result = ServiceAuth::verify(
            $headers[ServiceAuth::HEADER_SERVICE] ?? null,
            $headers[ServiceAuth::HEADER_TIMESTAMP] ?? null,
            $headers[ServiceAuth::HEADER_SIGNATURE] ?? null,
            'POST',
            '/api/v1/auth/me',
            '{"x":1}',
            'shared-secret-123',
            'required',
        );
        Assertions::true($result['ok']);
        Assertions::true($result['authenticated']);
        Assertions::same('panel', $result['service']);
    }

    public function testServiceAuthFailsClosedWhenRequiredSignatureIsMissing(): void
    {
        RequestContext::set('req_phase2a_123456');
        $result = ServiceAuth::verify('panel', null, null, 'GET', '/api/v1/auth/me', null, 'shared-secret-123', 'required');
        Assertions::false($result['ok']);
        Assertions::same('service_signature_required', $result['error']);
    }

    public function testServiceBoundaryPublishesIdentityOwnership(): void
    {
        $contract = ServiceBoundary::describe();
        Assertions::same('identity', $contract['service']);
        Assertions::true(in_array('sessions', $contract['owns'], true));
        Assertions::same('http_api_only', $contract['rules']['cross_service_access']);
    }

    public function testConfigDefaultsToBackwardCompatibleOptionalServiceAuth(): void
    {
        $config = new Config('identity', 'local');
        Assertions::same('optional', $config->serviceAuthMode());
        Assertions::same('', $config->serviceAuthSecret());
    }

    public function testPaginationContractCalculatesStableMetadata(): void
    {
        $meta = \Babanoell\Auth\Http\Pagination::fromResult(['page'=>2,'per_page'=>25,'total'=>51]);
        Assertions::same(['page'=>2,'per_page'=>25,'total'=>51,'total_pages'=>3], $meta);
    }

    public function testAuditWriterAddsCorrelationMetadata(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/src/Audit/PdoAuditWriter.php');
        Assertions::contains('RequestContext::auditMeta()', (string)$source);
    }
};
