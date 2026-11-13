<?php

namespace KhaledHajSalem\Zatca\Tests;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use KhaledHajSalem\Zatca\Support\Certificate;
use KhaledHajSalem\Zatca\Support\InvoiceExtension;
use KhaledHajSalem\Zatca\Support\InvoiceSigner;
use KhaledHajSalem\Zatca\Tests\Fixtures\Scenarios;
use KhaledHajSalem\Zatca\Validation\InvoiceValidator;
use KhaledHajSalem\Zatca\ZatcaInvoice;
use PHPUnit\Framework\TestCase;

/**
 * Offline checks on every scenario: clean local validation, well-formed
 * XML without empty elements, consistent totals, and a signature whose hash
 * survives the round trip ZATCA performs.
 *
 * Signing uses the sandbox certificate committed in storage/. It issues
 * nothing real: the developer portal hands the same test identity to anyone.
 */
class ScenarioMatrixTest extends TestCase
{
    private const DATE = '2024-01-15';
    private const TIME = '10:30:00';

    private static ?Certificate $certificate = null;

    private static function certificate(): Certificate
    {
        $storage = __DIR__ . '/../storage';

        return self::$certificate ??= new Certificate(
            (string)file_get_contents("$storage/certificate.pem"),
            (string)file_get_contents("$storage/private.pem"),
            trim((string)file_get_contents("$storage/secret.txt"))
        );
    }

    public function scenarios(): array
    {
        return Scenarios::names();
    }

    /**
     * @dataProvider scenarios
     */
    public function testScenarioPassesLocalValidation(string $name): void
    {
        $invoice = Scenarios::all(self::DATE, self::TIME)[$name]();
        $invoice->calculateTotals();

        $now = new DateTimeImmutable(self::DATE . ' 18:00:00', new DateTimeZone('Asia/Riyadh'));
        $issues = (new InvoiceValidator($now))->validate($invoice);

        $this->assertSame([], $issues, "Scenario '$name' should raise no validation issue");
    }

    /**
     * @dataProvider scenarios
     */
    public function testScenarioGeneratesConsistentXml(string $name): void
    {
        $invoice = Scenarios::all(self::DATE, self::TIME)[$name]();
        $xml = (new ZatcaInvoice())->generateXml($invoice, '8e6000cf-1a98-4174-b3e7-b5d5954bc10d');

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'XML must be well formed');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        // BR-KSA-F-03: no empty elements, apart from the placeholders the
        // signer fills in (the extension content and the QR attachment).
        $empty = [];
        foreach ($xpath->query('//*[not(*) and not(normalize-space())]') as $node) {
            if (!in_array($node->nodeName, ['ext:ExtensionContent', 'cbc:EmbeddedDocumentBinaryObject'], true)) {
                $empty[] = $node->getNodePath();
            }
        }
        $this->assertSame([], $empty, 'No empty elements (BR-KSA-F-03)');

        $number = fn(string $query): float => (float)$xpath->evaluate("number($query)");
        $total = '/*/cac:LegalMonetaryTotal';
        $lines = $number('sum(/*/cac:InvoiceLine/cbc:LineExtensionAmount)');
        $vat = $number('/*/cac:TaxTotal[cac:TaxSubtotal]/cbc:TaxAmount');

        // BR-CO-10: sum of line net amounts.
        $this->assertEqualsWithDelta($lines, $number("$total/cbc:LineExtensionAmount"), 0.001);

        // BR-CO-13: total without VAT = lines - allowances + charges.
        $allowances = $number('sum(/*/cac:AllowanceCharge[cbc:ChargeIndicator="false"]/cbc:Amount)');
        $charges = $number('sum(/*/cac:AllowanceCharge[cbc:ChargeIndicator="true"]/cbc:Amount)');
        $this->assertEqualsWithDelta($lines - $allowances + $charges, $number("$total/cbc:TaxExclusiveAmount"), 0.001);

        // BR-CO-14: VAT total = sum of the VAT breakdown.
        $this->assertEqualsWithDelta($number('sum(/*/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount)'), $vat, 0.001);

        // BR-CO-15: total with VAT.
        $this->assertEqualsWithDelta($number("$total/cbc:TaxExclusiveAmount") + $vat, $number("$total/cbc:TaxInclusiveAmount"), 0.001);

        // BR-CO-17: VAT per category = taxable amount x rate, to two decimals.
        foreach ($xpath->query('/*/cac:TaxTotal/cac:TaxSubtotal') as $subtotal) {
            $taxable = (float)$xpath->evaluate('number(cbc:TaxableAmount)', $subtotal);
            $rate = (float)$xpath->evaluate('number(cac:TaxCategory/cbc:Percent)', $subtotal);
            $this->assertEqualsWithDelta(round($taxable * $rate / 100, 2), (float)$xpath->evaluate('number(cbc:TaxAmount)', $subtotal), 0.011);
        }

        // BR-KSA-CL-02: one currency throughout, except the VAT total in SAR.
        $currency = $xpath->evaluate('string(/*/cbc:DocumentCurrencyCode)');
        $currencies = [];
        foreach ($xpath->query('//@currencyID') as $attribute) {
            $currencies[$attribute->nodeValue] = true;
        }
        $this->assertEqualsCanonicalizing(array_unique([$currency, 'SAR']), array_keys($currencies + ['SAR' => true]));
    }

    /**
     * @dataProvider scenarios
     */
    public function testScenarioSignsWithStableHash(string $name): void
    {
        $invoice = Scenarios::all(self::DATE, self::TIME)[$name]();
        $xml = (new ZatcaInvoice())->generateXml($invoice, '8e6000cf-1a98-4174-b3e7-b5d5954bc10d');

        $signed = InvoiceSigner::signInvoice($xml, self::certificate());

        // The hash ZATCA recomputes from the signed document must be the
        // hash that was signed and that is sent in the API request.
        $this->assertSame(
            $signed->getHash(),
            InvoiceExtension::fromString($signed->getXML())->computeXmlDigest(),
            'Hash of the signed document must equal the signed hash'
        );
        $this->assertSame(32, strlen((string)base64_decode($signed->getHash(), true)));

        // BR-CL-KSA-14: ZATCA rejects a QR code longer than 1000 characters.
        $this->assertLessThanOrEqual(1000, strlen($signed->getQRCode()));

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($signed->getXML()), 'Signed XML must be well formed');

        // Signing twice gives the same hash (the signature itself is not
        // reproducible, and is not part of the hash).
        $this->assertSame($signed->getHash(), InvoiceSigner::signInvoice($xml, self::certificate())->getHash());
    }
}
