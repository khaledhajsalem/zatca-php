<?php

namespace KhaledHajSalem\Zatca\Tests\Sandbox;

use DateTimeImmutable;
use DateTimeZone;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Services\SubmissionResult;
use KhaledHajSalem\Zatca\Services\ZatcaAPIService;
use KhaledHajSalem\Zatca\Support\Certificate;
use KhaledHajSalem\Zatca\Support\CertificateBuilder;
use KhaledHajSalem\Zatca\Support\InvoiceSigner;
use KhaledHajSalem\Zatca\Tests\Fixtures\Scenarios;
use KhaledHajSalem\Zatca\ZatcaInvoice;
use PHPUnit\Framework\TestCase;

/**
 * Submits every scenario to ZATCA's developer-portal validator.
 *
 * This is the authoritative check: the sandbox runs the same XSD, EN 16931
 * and KSA rules as ZATCA's SDK, including rules newer than any published
 * document. A scenario passes only when ZATCA answers PASS with no warnings.
 *
 * Not part of the default run (it needs the network and takes a minute):
 *
 *     composer test-sandbox
 *
 * It onboards a throwaway test unit with the fixed sandbox OTP and uses
 * ZATCA's own dummy taxpayer, so it sends nothing real and writes no files.
 *
 * @group sandbox
 */
class SandboxComplianceTest extends TestCase
{
    private const OTP = '123345';

    private static ?ZatcaAPIService $api = null;
    private static ?Certificate $certificate = null;
    private static string $certificatePem = '';
    private static string $secret = '';
    private static string $privateKey = '';
    private static string $requestId = '';
    private static int $counter = 0;
    private static string $previousHash = InvoiceData::FIRST_INVOICE_HASH;

    public static function setUpBeforeClass(): void
    {
        if (getenv('ZATCA_SANDBOX') !== '1') {
            self::markTestSkipped('Sandbox tests are opt-in: run "composer test-sandbox" (or set ZATCA_SANDBOX=1).');
        }

        $builder = (new CertificateBuilder())
            ->setOrganizationIdentifier('399999999900003')
            ->setSerialNumber('ZatcaPHP', 'Tests', 'SN-' . bin2hex(random_bytes(4)))
            ->setCommonName('TST-886431145-399999999900003')
            ->setCountryName('SA')
            ->setOrganizationName('Maximum Speed Tech Supply LTD')
            ->setOrganizationalUnitName('Riyadh Branch')
            ->setAddress('RRRD2929')
            ->setInvoiceType(1100)
            ->setProduction(false)
            ->setBusinessCategory('Supply activities');
        $builder->generate();

        self::$api = new ZatcaAPIService('sandbox', null, ['timeout' => 60]);
        $compliance = self::$api->requestComplianceCertificate($builder->getCsr(), self::OTP);

        self::$privateKey = $builder->getPrivateKey();
        self::$certificatePem = $compliance->getCertificate();
        self::$secret = $compliance->getSecret();
        self::$requestId = $compliance->getRequestId();
        self::$certificate = new Certificate(self::$certificatePem, self::$privateKey, self::$secret);
    }

    public function scenarios(): array
    {
        return Scenarios::names();
    }

    /**
     * @dataProvider scenarios
     */
    public function testZatcaAcceptsScenarioWithoutWarnings(string $name): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));

        $invoice = Scenarios::all($now->format('Y-m-d'), $now->format('H:i:s'))[$name]();
        $invoice->setInvoiceCounter((string)++self::$counter)->setPreviousInvoiceHash(self::$previousHash);

        $uuid = sprintf('%08x-0000-4000-8000-%012x', crc32($name), self::$counter);
        $signed = InvoiceSigner::signInvoice((new ZatcaInvoice())->generateXml($invoice, $uuid), self::$certificate);
        self::$previousHash = $signed->getHash();

        $result = self::$api->checkCompliance(self::$certificatePem, self::$secret, $signed->getXML(), $signed->getHash(), $uuid);

        $describe = fn(array $messages): string => implode("\n", array_map(
            fn(array $m): string => ($m['code'] ?? '?') . ': ' . preg_replace('/\s+/', ' ', (string)($m['message'] ?? '')),
            $messages
        ));

        $this->assertSame([], $result->getErrors(), "ZATCA rejected '$name':\n" . $describe($result->getErrors()));
        $this->assertSame([], $result->getWarnings(), "ZATCA accepted '$name' with warnings:\n" . $describe($result->getWarnings()));
        $this->assertSame(SubmissionResult::ACCEPTED, $result->getOutcome(), "HTTP {$result->getStatusCode()} for '$name'");
        $this->assertSame($invoice->isStandard() ? 'CLEARED' : 'REPORTED', $invoice->isStandard() ? $result->getClearanceStatus() : $result->getReportingStatus());
    }

    /**
     * The production CSID and the reporting / clearance endpoints, including
     * what ZATCA answers when the same document is sent twice.
     *
     * @depends testZatcaAcceptsScenarioWithoutWarnings
     */
    public function testReportingAndClearanceWithAProductionCsid(): void
    {
        $production = self::$api->requestProductionCertificate(self::$certificatePem, self::$secret, self::$requestId);
        $certificate = new Certificate($production->getCertificate(), self::$privateKey, $production->getSecret());

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));
        $scenarios = Scenarios::all($now->format('Y-m-d'), $now->format('H:i:s'));

        // Simplified: reported.
        $simplified = $scenarios['simplified invoice']()->setInvoiceCounter('1');
        $uuid = sprintf('%08x-1111-4000-8000-%012x', random_int(0, 0xffffffff), 1);
        $signed = InvoiceSigner::signInvoice((new ZatcaInvoice())->generateXml($simplified, $uuid), $certificate);

        $reported = self::$api->report($production->getCertificate(), $production->getSecret(), $signed->getXML(), $signed->getHash(), $uuid);
        $this->assertTrue($reported->isAccepted(), 'Reporting: HTTP ' . $reported->getStatusCode() . ' ' . json_encode($reported->getBody()));
        $this->assertSame('REPORTED', $reported->getReportingStatus());

        // Standard: cleared, and the stamped document comes back.
        $standard = $scenarios['standard invoice']()->setInvoiceCounter('2')->setPreviousInvoiceHash($signed->getHash());
        $uuid = sprintf('%08x-2222-4000-8000-%012x', random_int(0, 0xffffffff), 2);
        $signedStandard = InvoiceSigner::signInvoice((new ZatcaInvoice())->generateXml($standard, $uuid), $certificate);

        $cleared = self::$api->clear($production->getCertificate(), $production->getSecret(), $signedStandard->getXML(), $signedStandard->getHash(), $uuid);
        $this->assertTrue($cleared->isAccepted(), 'Clearance: HTTP ' . $cleared->getStatusCode() . ' ' . json_encode($cleared->getBody()));
        $this->assertSame('CLEARED', $cleared->getClearanceStatus());
        $this->assertStringContainsString('<Invoice', (string)$cleared->getClearedInvoice());
    }
}
