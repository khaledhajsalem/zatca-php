<?php

namespace KhaledHajSalem\Zatca\Support;

use KhaledHajSalem\Zatca\Exceptions\ZatcaException;

class InvoiceSigner
{
    /** Longest QR code (base64 characters) ZATCA accepts (BR-CL-KSA-14). */
    public const MAX_QR_LENGTH = 1000;

    private $signedInvoice;  // Signed invoice XML string
    private $hash;           // Invoice hash (base64 encoded)
    private $qrCode;         // QR Code (base64 encoded)
    private $certificate;    // Certificate used for signing
    private $digitalSignature; // Digital signature (base64 encoded)

    // Private constructor to force usage of signInvoice method
    private function __construct() {}

    /**
     * Signs the invoice XML and returns an InvoiceSigner object.
     *
     * @param string      $xmlInvoice  Invoice XML as a string
     * @param Certificate $certificate Certificate for signing
     * @return self
     */
    public static function signInvoice(string $xmlInvoice, Certificate $certificate): self
    {
        $instance = new self();
        $instance->certificate = $certificate;

        // Convert XML string to DOM
        $xmlDom = InvoiceExtension::fromString($xmlInvoice);

        // Remove unwanted tags per guidelines
        $xmlDom->removeByXpath('ext:UBLExtensions');
        $xmlDom->removeByXpath('cac:Signature');
        $xmlDom->removeParentByXpath('cac:AdditionalDocumentReference/cbc:ID[. = "QR"]');

        // Compute hash using SHA-256
        $invoiceHashBinary = hash('sha256', $xmlDom->getElement()->C14N(false, false), true);
        $instance->hash = base64_encode($invoiceHashBinary);

        // Create digital signature using the private key
        $instance->digitalSignature = base64_encode(
            $certificate->getPrivateKey()->sign($invoiceHashBinary)
        );

        // Prepare UBL Extension with certificate, hash, and signature
        $ublExtension = (new InvoiceSignatureBuilder)
            ->setCertificate($certificate)
            ->setInvoiceDigest($instance->hash)
            ->setSignatureValue($instance->digitalSignature)
            ->buildSignatureXml();

        // Generate QR Code
        $instance->qrCode = QRCodeGenerator::createFromTags(
            $xmlDom->generateQrTagsArray($certificate, $instance->hash, $instance->digitalSignature)
        )->encodeBase64();


        // ZATCA rejects a QR code longer than 1000 base64 characters
        // (BR-CL-KSA-14). The seller name is the only variable-length part,
        // so an over-long name is the cause.
        if (strlen($instance->qrCode) > self::MAX_QR_LENGTH) {
            throw new ZatcaException(
                'The QR code is ' . strlen($instance->qrCode) . ' characters; ZATCA accepts at most '
                . self::MAX_QR_LENGTH . ' (BR-CL-KSA-14). Shorten the seller registration name.',
                ['qr_length' => strlen($instance->qrCode)]
            );
        }

        // Insert UBL Extension and QR Code into the XML
        $signedInvoice = str_replace(
            [
                "<cbc:ProfileID>",
                '<cac:AccountingSupplierParty>',
            ],
            [
                "<ext:UBLExtensions>" . $ublExtension . "</ext:UBLExtensions>" . PHP_EOL . "    <cbc:ProfileID>",
                $instance->getQRNode($instance->qrCode) . PHP_EOL . "    <cac:AccountingSupplierParty>",
            ],
            $xmlDom->toXml()
        );

        // Remove extra blank lines and save
        $instance->signedInvoice = preg_replace('/^[ \t]*[\r\n]+/m', '', $signedInvoice);

        // ZATCA recomputes the hash from the document it receives. If the
        // layout of the final XML made that differ from the hash that was
        // signed, the document would be rejected (invalid-invoice-hash)
        // after its ICV had been consumed. Fail here instead.
        $recomputed = InvoiceExtension::fromString($instance->signedInvoice)->computeXmlDigest();
        if ($recomputed !== $instance->hash) {
            throw new ZatcaException(
                'The signed invoice does not hash to the value that was signed. '
                . 'The unsigned XML has an element between the QR reference and cac:Signature, '
                . 'or was not produced by ZatcaInvoice::generateXml().',
                ['signed_hash' => $instance->hash, 'recomputed_hash' => $recomputed]
            );
        }

        return $instance;
    }

    /**
     * Get the signed XML string.
     *
     * @return string
     */
    public function getXML(): string
    {
        return $this->signedInvoice;
    }

    /**
     * Returns the QR node string.
     *
     * @param string $QRCode
     * @return string
     */
    private function getQRNode(string $QRCode): string
    {
        return "<cac:AdditionalDocumentReference>
        <cbc:ID>QR</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode=\"text/plain\">$QRCode</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
    </cac:AdditionalDocumentReference>
    <cac:Signature>
        <cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID>
        <cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod>
    </cac:Signature>";
    }
    /**
     * Get signed invoice XML.
     *
     * @return string
     */
    public function getInvoice(): string
    {
        return $this->signedInvoice;
    }

    /**
     * Get invoice hash.
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get QR Code.
     *
     * @return string
     */
    public function getQRCode(): string
    {
        return $this->qrCode;
    }

    /**
     * Get the certificate used for signing.
     *
     * @return Certificate
     */
    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }
}