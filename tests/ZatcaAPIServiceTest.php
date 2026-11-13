<?php

namespace KhaledHajSalem\Zatca\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use KhaledHajSalem\Zatca\Exceptions\ZatcaApiException;
use KhaledHajSalem\Zatca\Services\ComplianceCertificateResult;
use KhaledHajSalem\Zatca\Services\ProductionCertificateResult;
use KhaledHajSalem\Zatca\Services\SubmissionResult;
use KhaledHajSalem\Zatca\Services\ZatcaAPIService;
use PHPUnit\Framework\TestCase;

/**
 * The API client against canned responses: nothing here touches the network.
 * Response bodies follow the examples of ZATCA's sandbox API specification.
 */
class ZatcaAPIServiceTest extends TestCase
{
    private const CERTIFICATE = "-----BEGIN CERTIFICATE-----\nTUlJQ0FUQ0NBYWVnQXdJQkFnSUdBWQ==\n-----END CERTIFICATE-----";

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    /**
     * @param array<int, mixed> $responses
     */
    private function service(array $responses, array $options = []): ZatcaAPIService
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ZatcaAPIService('sandbox', new Client(['handler' => $stack]), $options);
    }

    private function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string)json_encode($body));
    }

    private function request(int $index = 0): Request
    {
        return $this->history[$index]['request'];
    }

    private function reported(string $status = 'PASS', array $warnings = [], array $errors = [], string $reporting = 'REPORTED'): array
    {
        return [
            'validationResults' => ['infoMessages' => [], 'warningMessages' => $warnings, 'errorMessages' => $errors, 'status' => $status],
            'reportingStatus' => $reporting,
        ];
    }

    public function testRejectsUnknownEnvironment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZatcaAPIService('staging');
    }

    public function testBaseUriPerEnvironment(): void
    {
        $this->assertStringEndsWith('/developer-portal', (new ZatcaAPIService('sandbox'))->getBaseUri());
        $this->assertStringEndsWith('/simulation', (new ZatcaAPIService('simulation'))->getBaseUri());
        $this->assertStringEndsWith('/core', (new ZatcaAPIService('production'))->getBaseUri());
    }

    public function testReportSendsTheHeadersZatcaRequires(): void
    {
        $service = $this->service([$this->json(200, $this->reported())]);

        $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $request = $this->request();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/invoices/reporting/single', (string)$request->getUri());
        $this->assertSame('V2', $request->getHeaderLine('Accept-Version'));
        $this->assertSame('0', $request->getHeaderLine('Clearance-Status'));
        $this->assertSame('en', $request->getHeaderLine('Accept-Language'));

        // Basic base64( base64(certificate body) : secret )
        $expected = 'Basic ' . base64_encode(base64_encode('TUlJQ0FUQ0NBYWVnQXdJQkFnSUdBWQ==') . ':secret');
        $this->assertSame($expected, $request->getHeaderLine('Authorization'));

        $payload = json_decode((string)$request->getBody(), true);
        $this->assertSame(['invoiceHash' => 'hash==', 'uuid' => 'uuid-1', 'invoice' => base64_encode('<Invoice/>')], $payload);
    }

    public function testClearanceDeclaresClearanceStatusOne(): void
    {
        $service = $this->service([$this->json(200, ['clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode('<Cleared/>')])]);

        $result = $service->clear(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame('1', $this->request()->getHeaderLine('Clearance-Status'));
        $this->assertStringEndsWith('/invoices/clearance/single', (string)$this->request()->getUri());
        $this->assertSame(SubmissionResult::ACCEPTED, $result->getOutcome());
        $this->assertSame('<Cleared/>', $result->getClearedInvoice());
        $this->assertSame('CLEARED', $result->getClearanceStatus());
    }

    public function testLanguageOption(): void
    {
        $service = $this->service([$this->json(200, $this->reported())], ['language' => 'ar']);
        $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame('ar', $this->request()->getHeaderLine('Accept-Language'));
    }

    public function testAcceptedWithWarnings(): void
    {
        $warning = ['type' => 'WARNING', 'code' => 'BR-KSA-98', 'category' => 'KSA', 'message' => 'late', 'status' => 'WARNING'];
        $service = $this->service([$this->json(202, $this->reported('WARNING', [$warning]))]);

        $result = $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame(202, $result->getStatusCode());
        $this->assertSame(SubmissionResult::ACCEPTED_WITH_WARNINGS, $result->getOutcome());
        $this->assertTrue($result->isAccepted());
        $this->assertTrue($result->hasWarnings());
        $this->assertSame('BR-KSA-98', $result->getWarnings()[0]['code']);
        $this->assertFalse($result->shouldRetry());
    }

    public function testRejectedIsNotAnException(): void
    {
        $error = ['type' => 'ERROR', 'code' => 'BR-KSA-84', 'category' => 'KSA', 'message' => 'rate', 'status' => 'ERROR'];
        $service = $this->service([$this->json(400, $this->reported('ERROR', [], [$error], 'NOT_REPORTED'))]);

        $result = $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame(SubmissionResult::REJECTED, $result->getOutcome());
        $this->assertTrue($result->isRejected());
        $this->assertFalse($result->isAccepted());
        $this->assertFalse($result->shouldRetry());
        $this->assertSame('BR-KSA-84', $result->getErrors()[0]['code']);
    }

    public function testAlreadyReportedIsAcceptedDespiteTheBody(): void
    {
        // ZATCA answers 409 with a body that says NOT_REPORTED / ERROR.
        $service = $this->service([$this->json(409, $this->reported('ERROR', [], [['code' => 'Invoice-Errors', 'message' => 'Invoice was already Reported successfully earlier.']], 'NOT_REPORTED'))]);

        $result = $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame(SubmissionResult::ALREADY_ACCEPTED, $result->getOutcome());
        $this->assertTrue($result->isAccepted());
        $this->assertTrue($result->isDuplicate());
        $this->assertFalse($result->isRejected());
        $this->assertSame('NOT_REPORTED', $result->getReportingStatus());
    }

    public function testAlreadyClearedReturnsTheClearedInvoiceAgain(): void
    {
        $service = $this->service([$this->json(208, ['clearanceStatus' => 'CLEARED', 'clearedInvoice' => base64_encode('<Cleared/>')])]);

        $result = $service->clear(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame(SubmissionResult::ALREADY_ACCEPTED, $result->getOutcome());
        $this->assertTrue($result->isDuplicate());
        $this->assertSame('<Cleared/>', $result->getClearedInvoice());
    }

    public function testClearanceDeactivatedIsNotFollowedAsARedirect(): void
    {
        $service = $this->service([new Response(303, ['Location' => 'https://example.invalid/elsewhere'], (string)json_encode(['message' => 'Clearance is deactiviated.']))]);

        $result = $service->clear(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertCount(1, $this->history, 'The 303 must not be followed');
        $this->assertSame(SubmissionResult::CLEARANCE_DISABLED, $result->getOutcome());
        $this->assertFalse($result->isAccepted());
    }

    /**
     * @dataProvider statusOutcomes
     */
    public function testOutcomePerStatusCode(int $status, string $outcome, bool $retry): void
    {
        $service = $this->service([new Response($status, [], $status === 401 ? '' : '{}')]);

        $result = $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame($outcome, $result->getOutcome());
        $this->assertSame($retry, $result->shouldRetry());
        $this->assertSame($status, $service->getLastStatusCode());
    }

    public function statusOutcomes(): array
    {
        return [
            'unauthorized' => [401, SubmissionResult::UNAUTHORIZED, false],
            'payload too large' => [413, SubmissionResult::NOT_RECEIVED, true],
            'too many requests' => [429, SubmissionResult::NOT_RECEIVED, true],
            'internal error' => [500, SubmissionResult::NOT_RECEIVED, true],
            'unavailable' => [503, SubmissionResult::NOT_RECEIVED, true],
            'gateway timeout' => [504, SubmissionResult::NOT_RECEIVED, true],
            'wrong version' => [406, SubmissionResult::UNEXPECTED, false],
        ];
    }

    public function testNonJsonErrorBodyKeepsTheStatusCode(): void
    {
        $service = $this->service([new Response(406, ['Content-Type' => 'text/plain'], 'This Version is not supported or not provided in the header.')]);

        $result = $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame(406, $result->getStatusCode());
        $this->assertStringContainsString('not supported', $result->getBody()['message']);
    }

    public function testTransportFailureThrowsWithoutAStatusCode(): void
    {
        $service = $this->service([new ConnectException('Connection timed out', new Request('POST', 'test'))]);

        try {
            $service->report(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');
            $this->fail('A transport failure must throw');
        } catch (ZatcaApiException $e) {
            // No status code: the outcome is unknown, not a rejection.
            $this->assertArrayNotHasKey('status_code', $e->getContext());
            $this->assertNull($service->getLastStatusCode());
        }
    }

    public function testComplianceCheck(): void
    {
        $service = $this->service([$this->json(200, $this->reported())]);

        $result = $service->checkCompliance(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertStringEndsWith('/compliance/invoices', (string)$this->request()->getUri());
        $this->assertSame('compliance', $result->getOperation());
        $this->assertTrue($result->isAccepted());
    }

    // --- Array-returning methods keep their 1.x behaviour ------------------

    public function testLegacyReportInvoiceReturnsBodyAndExposesStatus(): void
    {
        $service = $this->service([$this->json(202, $this->reported('WARNING'))]);

        $body = $service->reportInvoice(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame('REPORTED', $body['reportingStatus']);
        $this->assertSame(202, $service->getLastStatusCode());
        $this->assertSame('0', $this->request()->getHeaderLine('Clearance-Status'));
    }

    public function testLegacyMethodsStillReturnValidationErrorsAsArray(): void
    {
        $service = $this->service([$this->json(400, $this->reported('ERROR', [], [['code' => 'BR-KSA-84']], 'NOT_REPORTED'))]);

        $body = $service->reportInvoice(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');

        $this->assertSame('NOT_REPORTED', $body['reportingStatus']);
    }

    public function testLegacyMethodsStillThrowOnOtherStatuses(): void
    {
        $service = $this->service([$this->json(409, $this->reported('ERROR', [], [], 'NOT_REPORTED'))]);

        try {
            $service->reportInvoice(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');
            $this->fail('409 throws from the legacy method');
        } catch (ZatcaApiException $e) {
            $this->assertSame(409, $e->getContext()['status_code']);
            $this->assertSame('NOT_REPORTED', $e->getContext()['response']['reportingStatus']);
        }
    }

    public function testWarningsCanBeTreatedAsFailuresByLegacyMethods(): void
    {
        $service = $this->service([$this->json(202, $this->reported('WARNING'))]);
        $service->setWarningHandling(false);

        $this->expectException(ZatcaApiException::class);
        $service->reportInvoice(self::CERTIFICATE, 'secret', '<Invoice/>', 'hash==', 'uuid-1');
    }

    // --- Certificates --------------------------------------------------------

    public function testRequestComplianceCertificate(): void
    {
        $service = $this->service([$this->json(200, ['requestID' => 1234567890123, 'dispositionMessage' => 'ISSUED', 'binarySecurityToken' => base64_encode('CERTBODY'), 'secret' => 's3cret'])]);

        $result = $service->requestComplianceCertificate('CSR-CONTENT', '123345');

        $this->assertInstanceOf(ComplianceCertificateResult::class, $result);
        $this->assertSame("-----BEGIN CERTIFICATE-----\nCERTBODY\n-----END CERTIFICATE-----", $result->getCertificate());
        $this->assertSame('s3cret', $result->getSecret());
        $this->assertSame('1234567890123', $result->getRequestId());

        $this->assertSame('123345', $this->request()->getHeaderLine('OTP'));
        $this->assertSame(['csr' => base64_encode('CSR-CONTENT')], json_decode((string)$this->request()->getBody(), true));
        $this->assertSame('', $this->request()->getHeaderLine('Authorization'));
    }

    public function testRequestProductionCertificate(): void
    {
        $service = $this->service([$this->json(200, ['requestID' => 77, 'binarySecurityToken' => base64_encode('PROD'), 'secret' => 'p'])]);

        $result = $service->requestProductionCertificate(self::CERTIFICATE, 'secret', '1234567890123');

        $this->assertInstanceOf(ProductionCertificateResult::class, $result);
        $this->assertStringEndsWith('/production/csids', (string)$this->request()->getUri());
        $this->assertSame('POST', $this->request()->getMethod());
        $this->assertSame(['compliance_request_id' => '1234567890123'], json_decode((string)$this->request()->getBody(), true));
    }

    public function testRenewProductionCertificate(): void
    {
        $service = $this->service([$this->json(200, ['requestID' => 347, 'dispositionMessage' => 'ISSUED', 'binarySecurityToken' => base64_encode('RENEWED'), 'secret' => 'new'])]);

        $result = $service->renewProductionCertificate(self::CERTIFICATE, 'secret', 'NEW-CSR', '654321');

        $request = $this->request();
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('/production/csids', (string)$request->getUri());
        $this->assertSame('654321', $request->getHeaderLine('OTP'));
        $this->assertSame('V2', $request->getHeaderLine('Accept-Version'));
        $this->assertStringStartsWith('Basic ', $request->getHeaderLine('Authorization'));
        $this->assertSame(['csr' => base64_encode('NEW-CSR')], json_decode((string)$request->getBody(), true));

        $this->assertStringContainsString('RENEWED', $result->getCertificate());
        $this->assertSame('new', $result->getSecret());
        $this->assertSame('347', $result->getRequestId());
    }

    public function testRenewalNotCompliantCarriesTheComplianceCertificate(): void
    {
        $service = $this->service([$this->json(428, ['requestID' => 1234567890123, 'dispositionMessage' => 'NOT_COMPLIANT', 'binarySecurityToken' => 'Q0NTSUQ=', 'secret' => 'c'])]);

        try {
            $service->renewProductionCertificate(self::CERTIFICATE, 'secret', 'NEW-CSR', '654321');
            $this->fail('428 must throw');
        } catch (ZatcaApiException $e) {
            $this->assertSame(428, $e->getContext()['status_code']);
            $this->assertSame('NOT_COMPLIANT', $e->getContext()['response']['dispositionMessage']);
            $this->assertStringContainsString('compliance checks', $e->getMessage());
        }
    }

    public function testRenewalWithInvalidOtpThrows(): void
    {
        $service = $this->service([$this->json(400, ['errors' => [['code' => 'Invalid-OTP', 'message' => 'Provided OTP is invalid']]])]);

        try {
            $service->renewProductionCertificate(self::CERTIFICATE, 'secret', 'NEW-CSR', '000000');
            $this->fail('400 must throw');
        } catch (ZatcaApiException $e) {
            $this->assertSame(400, $e->getContext()['status_code']);
            $this->assertSame('Invalid-OTP', $e->getContext()['response']['errors'][0]['code']);
        }
    }
}
