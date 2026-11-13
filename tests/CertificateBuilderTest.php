<?php

namespace KhaledHajSalem\Zatca\Tests;

use KhaledHajSalem\Zatca\Exceptions\CertificateBuilderException;
use KhaledHajSalem\Zatca\Support\CertificateBuilder;
use phpseclib3\Crypt\EC;
use PHPUnit\Framework\TestCase;

class CertificateBuilderTest extends TestCase
{
    private function builder(): CertificateBuilder
    {
        return (new CertificateBuilder())
            ->setOrganizationIdentifier('399999999900003')
            ->setSerialNumber('ZatcaPHP', 'Test', 'SN001')
            ->setCommonName('TST-886431145-399999999900003')
            ->setCountryName('SA')
            ->setOrganizationName('Maximum Speed Tech Supply LTD')
            ->setOrganizationalUnitName('Riyadh Branch')
            ->setAddress('RRRD2929')
            ->setInvoiceType(1100)
            ->setProduction(false)
            ->setBusinessCategory('Supply activities');
    }

    protected function setUp(): void
    {
        if (!in_array('secp256k1', openssl_get_curve_names() ?: [], true)) {
            $this->markTestSkipped('This OpenSSL build has no secp256k1 curve.');
        }
    }

    public function testGeneratesACsrOnTheCurveZatcaRequires(): void
    {
        $builder = $this->builder();
        $builder->generate();

        $csr = $builder->getCsr();
        $this->assertStringContainsString('BEGIN CERTIFICATE REQUEST', $csr);

        $subject = openssl_csr_get_subject($csr, false);
        $this->assertSame('TST-886431145-399999999900003', $subject['commonName']);
        $this->assertSame('Maximum Speed Tech Supply LTD', $subject['organizationName']);
        $this->assertSame('SA', $subject['countryName']);

        $details = openssl_pkey_get_details(openssl_csr_get_public_key($csr));
        $this->assertSame('secp256k1', $details['ec']['curve_name']);
    }

    /**
     * @dataProvider invoiceTypes
     */
    public function testInvoiceTypeIsWrittenWithFourDigits(int $type, string $expected): void
    {
        $builder = $this->builder()->setInvoiceType($type);
        $builder->generate();

        // The flag is the "title" attribute of the subject alternative name.
        // It is a short ASCII value, so it can be read from the DER directly:
        // OID 2.5.4.12 (title) followed by its string.
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $builder->getCsr()));
        $position = strpos($der, "\x55\x04\x0c");

        $this->assertNotFalse($position, 'The CSR has no title attribute');
        $length = ord($der[$position + 4]);
        $this->assertSame($expected, substr($der, $position + 5, $length));
    }

    public function invoiceTypes(): array
    {
        return [
            'standard and simplified' => [1100, '1100'],
            'standard only' => [1000, '1000'],
            'simplified only' => [100, '0100'],
        ];
    }

    public function testPrivateKeyIsAvailableWithoutTouchingTheDisk(): void
    {
        $builder = $this->builder();
        $builder->generate();

        $pem = $builder->getPrivateKey();

        $this->assertStringContainsString('PRIVATE KEY-----', $pem);

        // The same key the CSR was made with, loadable by the signer.
        $fromCsr = openssl_pkey_get_details(openssl_csr_get_public_key($builder->getCsr()))['key'];
        $fromKey = openssl_pkey_get_details(openssl_pkey_get_private($pem))['key'];
        $this->assertSame($fromCsr, $fromKey);
        $this->assertSame('secp256k1', EC::loadPrivateKey($pem)->getCurve());
    }

    public function testPrivateKeyRequiresGeneration(): void
    {
        $this->expectException(CertificateBuilderException::class);
        $this->builder()->getPrivateKey();
    }

    public function testEachGenerationProducesANewKey(): void
    {
        $first = $this->builder();
        $first->generate();
        $second = $this->builder();
        $second->generate();

        $this->assertNotSame($first->getPrivateKey(), $second->getPrivateKey());
    }

    public function testRejectsAVatNumberThatIsNotFifteenDigits(): void
    {
        $this->expectException(CertificateBuilderException::class);
        (new CertificateBuilder())->setOrganizationIdentifier('123');
    }

    public function testMissingFieldsAreReported(): void
    {
        $this->expectException(CertificateBuilderException::class);
        (new CertificateBuilder())->setOrganizationIdentifier('399999999900003')->generate();
    }
}
