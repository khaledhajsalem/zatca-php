<?php

namespace KhaledHajSalem\Zatca;

use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Support\Money;
use KhaledHajSalem\Zatca\Support\TaxCategory;
use InvalidArgumentException;
use DOMDocument;
use DOMElement;
use Exception;

/**
 * Main class for generating ZATCA compliant invoice XML.
 */
class ZatcaInvoice
{
    /**
     * Generate UBL 2.1 compliant invoice XML.
     *
     * @param InvoiceData $invoiceData
     * @param string|null $uuid Optional UUID to use instead of generating one
     * @return string
     * @throws Exception
     */
    public function generateXml(InvoiceData $invoiceData, ?string $uuid = null): string
    {
        // Calculate totals if not already calculated
        $invoiceData->calculateTotals();
        $this->validateTransaction($invoiceData);

        // Create the XML document
        $dom = $this->createXmlDocument();
        $rootInvoice = $this->createRootElement($dom);

        // Add UBL Extensions (required for digital signature)
        $this->addUblExtensions($dom, $rootInvoice);

        // Add invoice header information
        $this->addInvoiceHeader($dom, $rootInvoice, $invoiceData, $uuid);

        // Add billing reference for credit/debit notes
        $this->addBillingReference($dom, $rootInvoice, $invoiceData);

        // Add document references (ICV, PIH, QR)
        $this->addDocumentReferences($dom, $rootInvoice, $invoiceData);

        // Add signature placeholder
        $this->addSignaturePlaceholder($dom, $rootInvoice, $invoiceData);

        // Add party information (seller and buyer)
        $this->addSellerInformation($dom, $rootInvoice, $invoiceData);
        $this->addBuyerInformation($dom, $rootInvoice, $invoiceData);

        // Add delivery information
        $this->addDeliveryInformation($dom, $rootInvoice, $invoiceData);

        // Add payment information
        $this->addPaymentInformation($dom, $rootInvoice, $invoiceData);

        // Add document-level allowances and charges
        $this->addDocumentLevelAllowances($dom, $rootInvoice, $invoiceData);
        $this->addDocumentLevelCharges($dom, $rootInvoice, $invoiceData);

        // Add tax information
        $this->addTaxInformation($dom, $rootInvoice, $invoiceData);

        // Add monetary totals
        $this->addMonetaryTotals($dom, $rootInvoice, $invoiceData);

        // Add invoice lines
        $this->addInvoiceLines($dom, $rootInvoice, $invoiceData);

        return $dom->saveXML();
    }

    /**
     * Check the KSA-2 flag combinations and supply dates that ZATCA would
     * otherwise reject.
     *
     * @throws InvalidArgumentException
     */
    protected function validateTransaction(InvoiceData $invoiceData): void
    {
        if ($invoiceData->isExport() && $invoiceData->isSelfBilled()) {
            throw new InvalidArgumentException('Export invoices cannot be self-billed (BR-KSA-07).');
        }

        if ($invoiceData->isSimplified() && ($invoiceData->isExport() || $invoiceData->isSelfBilled())) {
            throw new InvalidArgumentException(
                'Simplified invoices only accept the third-party, nominal and summary flags (BR-KSA-31).'
            );
        }

        $buyer = $invoiceData->getBuyer();

        if ($invoiceData->isExport() && $buyer && $buyer->getVatNumber() !== '') {
            throw new InvalidArgumentException('Export invoices must not contain a buyer VAT number (BR-KSA-46).');
        }

        $supplyEndDate = $invoiceData->getSupplyEndDate();

        if ($supplyEndDate !== '' && $supplyEndDate < $invoiceData->getSupplyDate()) {
            throw new InvalidArgumentException('Supply end date must not be before the supply date (BR-KSA-36).');
        }

        if ($invoiceData->isSimplified() && $invoiceData->isSummary()) {
            if ($supplyEndDate === '') {
                throw new InvalidArgumentException('Simplified summary invoices require a supply end date (BR-KSA-72).');
            }

            if (!$buyer || $buyer->getRegistrationName() === '') {
                throw new InvalidArgumentException('Simplified summary invoices require the buyer name (BR-KSA-71).');
            }
        }
    }

    /**
     * Create the XML document with proper namespaces.
     */
    protected function createXmlDocument(): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        return $dom;
    }

    /**
     * Create the root invoice element with namespaces.
     */
    protected function createRootElement(DOMDocument $dom): DOMElement
    {
        $rootInvoice = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $rootInvoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $rootInvoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $rootInvoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $dom->appendChild($rootInvoice);

        return $rootInvoice;
    }

    /**
     * Add UBL Extensions element.
     */
    protected function addUblExtensions(DOMDocument $dom, DOMElement $rootInvoice): void
    {
        $ublExtensions = $dom->createElement('ext:UBLExtensions');
        $rootInvoice->appendChild($ublExtensions);

        $ublExtension = $dom->createElement('ext:UBLExtension');
        $ublExtensions->appendChild($ublExtension);

        $extensionContent = $dom->createElement('ext:ExtensionContent');
        $ublExtension->appendChild($extensionContent);
    }

    /**
     * Add invoice header information.
     */
    protected function addInvoiceHeader(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData, ?string $uuid = null): void
    {
        $this->appendElement($dom, $rootInvoice, 'cbc:ProfileID', 'reporting:1.0');
        $this->appendElement($dom, $rootInvoice, 'cbc:ID', $invoiceData->getInvoiceNumber());
        $this->appendElement($dom, $rootInvoice, 'cbc:UUID', $uuid ?? $this->generateUUID());
        $this->appendElement($dom, $rootInvoice, 'cbc:IssueDate', $invoiceData->getIssueDate());
        $this->appendElement($dom, $rootInvoice, 'cbc:IssueTime', $invoiceData->getIssueTime());
        // Add InvoiceTypeCode with name attribute
        $invoiceTypeCode = $dom->createElement('cbc:InvoiceTypeCode', $invoiceData->getInvoiceTypeCode());
        $invoiceTypeCode->setAttribute('name', $invoiceData->getInvoiceTypeName());
        $rootInvoice->appendChild($invoiceTypeCode);

        // Invoice note (BT-22)
        if ($invoiceData->getNote() !== '') {
            $note = $this->appendElement($dom, $rootInvoice, 'cbc:Note', $invoiceData->getNote());
            if ($invoiceData->getNoteLanguage() !== '') {
                $note->setAttribute('languageID', $invoiceData->getNoteLanguage());
            }
        }
        $this->appendElement($dom, $rootInvoice, 'cbc:DocumentCurrencyCode', $invoiceData->getDocumentCurrencyCode());
        $this->appendElement($dom, $rootInvoice, 'cbc:TaxCurrencyCode', $invoiceData->getTaxCurrencyCode());
        $this->appendElement($dom, $rootInvoice, 'cbc:LineCountNumeric', (string)$invoiceData->getLineCountNumeric());
    }

    /**
     * Add billing reference for credit/debit notes.
     */
    protected function addBillingReference(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        foreach ($invoiceData->getBillingReferences() as $reference) {
            $billingReference = $dom->createElement('cac:BillingReference');
            $invoiceDocumentReference = $dom->createElement('cac:InvoiceDocumentReference');
            $this->appendElement($dom, $invoiceDocumentReference, 'cbc:ID', $reference['id'] ?? '');
            $this->appendElementIfNotEmpty($dom, $invoiceDocumentReference, 'cbc:UUID', (string)($reference['uuid'] ?? ''));
            $billingReference->appendChild($invoiceDocumentReference);
            $rootInvoice->appendChild($billingReference);
        }
    }

    /**
     * Add document references.
     */
    protected function addDocumentReferences(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Add ICV (Invoice Counter Value)
        $icvReference = $dom->createElement('cac:AdditionalDocumentReference');
        $this->appendElement($dom, $icvReference, 'cbc:ID', 'ICV');
        $this->appendElement($dom, $icvReference, 'cbc:UUID', $invoiceData->getInvoiceCounter());
        $rootInvoice->appendChild($icvReference);
        
        // Add Previous Invoice Hash (PIH) - always add this element
        $pihAdditionalDocRef = $dom->createElement('cac:AdditionalDocumentReference');
        $rootInvoice->appendChild($pihAdditionalDocRef);
        $this->appendElement($dom, $pihAdditionalDocRef, 'cbc:ID', 'PIH');

        $pihAttachment = $dom->createElement('cac:Attachment');
        $pihAdditionalDocRef->appendChild($pihAttachment);

        // Get PIH value and ensure it's properly encoded
        $pihValue = $invoiceData->getPreviousInvoiceHash() ?: InvoiceData::FIRST_INVOICE_HASH;
        // If PIH is not already base64 encoded, encode it
        if (base64_decode($pihValue, true) === false) {
            $pihValue = base64_encode($pihValue);
        }
        
        $pihBinaryObject = $this->appendElement($dom, $pihAttachment, 'cbc:EmbeddedDocumentBinaryObject', $pihValue);
        $pihBinaryObject->setAttribute('mimeCode', 'text/plain');
        $pihBinaryObject->setAttribute('filename', 'base64');

        // Add empty QR (QR Code) element AdditionalDocumentReference (will be replaced with the actual QR code later in signer)
        $qrAdditionalDocRef = $dom->createElement('cac:AdditionalDocumentReference');
        $rootInvoice->appendChild($qrAdditionalDocRef);
        $this->appendElement($dom, $qrAdditionalDocRef, 'cbc:ID', 'QR');

        $attachment = $dom->createElement('cac:Attachment');
        $qrAdditionalDocRef->appendChild($attachment);

        $qrBinaryObject = $this->appendElement($dom, $attachment, 'cbc:EmbeddedDocumentBinaryObject', '');
        $qrBinaryObject->setAttribute('mimeCode', 'text/plain');
        $qrBinaryObject->setAttribute('filename', 'base64');

        // Add custom document references
        foreach ($invoiceData->getDocumentReferences() as $reference) {
            $documentReference = $dom->createElement('cac:AdditionalDocumentReference');
            $this->appendElement($dom, $documentReference, 'cbc:ID', $reference['id'] ?? '');
            $this->appendElementIfNotEmpty($dom, $documentReference, 'cbc:DocumentType', (string)($reference['type'] ?? ''));
            $rootInvoice->appendChild($documentReference);
        }
    }

    /**
     * Add signature placeholder.
     */
    protected function addSignaturePlaceholder(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        $signature = $dom->createElement('cac:Signature');
        $this->appendElement($dom, $signature, 'cbc:ID', 'urn:oasis:names:specification:ubl:signature:1');
        $this->appendElement($dom, $signature, 'cbc:SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $rootInvoice->appendChild($signature);
    }

    /**
     * Add seller information.
     */
    protected function addSellerInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        if (!$invoiceData->getSeller()) {
            return;
        }

        $seller = $invoiceData->getSeller();
        $accountingSupplierParty = $dom->createElement('cac:AccountingSupplierParty');
        $party = $dom->createElement('cac:Party');

        // Party identification with schemeID
        if ($seller->getPartyIdentification() !== '') {
            $partyIdentification = $dom->createElement('cac:PartyIdentification');
            $idElement = $dom->createElement('cbc:ID', $seller->getPartyIdentification());
            $idElement->setAttribute('schemeID', $seller->getPartyIdentificationId());
            $partyIdentification->appendChild($idElement);
            $party->appendChild($partyIdentification);
        }

//        // Party name
//        $partyName = $dom->createElement('cac:PartyName');
//        $this->appendElement($dom, $partyName, 'cbc:Name', $seller->getRegistrationName());
//        $party->appendChild($partyName);

        // Postal address
        $postalAddress = $dom->createElement('cac:PostalAddress');
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:StreetName', $seller->getStreetName());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:AdditionalStreetName', $seller->getAdditionalStreetName());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:BuildingNumber', $seller->getBuildingNumber());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:PlotIdentification', $seller->getPlotIdentification());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:CitySubdivisionName', $seller->getCitySubdivisionName());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:CityName', $seller->getCityName());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:PostalZone', $seller->getPostalZone());
        $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:CountrySubentity', $seller->getCityName());
        
        $country = $dom->createElement('cac:Country');
        $this->appendElement($dom, $country, 'cbc:IdentificationCode', $seller->getCountryCode());
        $postalAddress->appendChild($country);
        $party->appendChild($postalAddress);

        // Party tax scheme
        $partyTaxScheme = $dom->createElement('cac:PartyTaxScheme');
        $this->appendElement($dom, $partyTaxScheme, 'cbc:CompanyID', $seller->getVatNumber());
        
        $taxScheme = $dom->createElement('cac:TaxScheme');
        $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
        $partyTaxScheme->appendChild($taxScheme);
        $party->appendChild($partyTaxScheme);

        // Party legal entity
        $partyLegalEntity = $dom->createElement('cac:PartyLegalEntity');
        $this->appendElement($dom, $partyLegalEntity, 'cbc:RegistrationName', $seller->getRegistrationName());
        $party->appendChild($partyLegalEntity);

        $accountingSupplierParty->appendChild($party);
        $rootInvoice->appendChild($accountingSupplierParty);
    }

    /**
     * Add buyer information.
     */
    protected function addBuyerInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Without buyer data (walk-in customer) an empty buyer is used; see below.
        $buyer = $invoiceData->getBuyer() ?? new BuyerData();
        $isSimplified = $invoiceData->isSimplified();
        $party = $dom->createElement('cac:Party');

        // Party identification: only when the buyer actually has an identifier.
        // Never emit an empty <cbc:ID>/empty schemeID.
        if ($buyer->getPartyIdentification() !== '') {
            $partyIdentification = $dom->createElement('cac:PartyIdentification');
            $idElement = $dom->createElement('cbc:ID', $buyer->getPartyIdentification());
            $idElement->setAttribute('schemeID', $buyer->getPartyIdentificationId());
            $partyIdentification->appendChild($idElement);
            $party->appendChild($partyIdentification);
        }

        // Postal address: omitted entirely for simplified (B2C) invoices.
        // For standard invoices, emit only the sub-elements that have a value.
        if (!$isSimplified) {
            $postalAddress = $dom->createElement('cac:PostalAddress');
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:StreetName', $buyer->getStreetName());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:AdditionalStreetName', $buyer->getAdditionalStreetName());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:BuildingNumber', $buyer->getBuildingNumber());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:PlotIdentification', $buyer->getPlotIdentification());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:CitySubdivisionName', $buyer->getCitySubdivisionName());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:CityName', $buyer->getCityName());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:PostalZone', $buyer->getPostalZone());
            $this->appendElementIfNotEmpty($dom, $postalAddress, 'cbc:CountrySubentity', $buyer->getCityName());

            $country = $dom->createElement('cac:Country');
            $this->appendElementIfNotEmpty($dom, $country, 'cbc:IdentificationCode', $buyer->getCountryCode());
            $postalAddress->appendChild($country);
            $party->appendChild($postalAddress);
        }

        if (!empty($buyer->getVatNumber())) {
            // Party tax scheme
            $partyTaxScheme = $dom->createElement('cac:PartyTaxScheme');
            $this->appendElement($dom, $partyTaxScheme, 'cbc:CompanyID', $buyer->getVatNumber());

            $taxScheme = $dom->createElement('cac:TaxScheme');
            $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
            $partyTaxScheme->appendChild($taxScheme);
            $party->appendChild($partyTaxScheme);
        }

        // Party legal entity: only when a registration name is provided.
        if ($buyer->getRegistrationName() !== '') {
            $partyLegalEntity = $dom->createElement('cac:PartyLegalEntity');
            $this->appendElement($dom, $partyLegalEntity, 'cbc:RegistrationName', $buyer->getRegistrationName());
            $party->appendChild($partyLegalEntity);
        }

        // UBL requires cac:AccountingCustomerParty. For an unidentified walk-in
        // (simplified invoice without buyer details), write only the country,
        // so the party isn't empty (BR-KSA-F-03). Verified in the ZATCA sandbox.
        if (!$party->hasChildNodes()) {
            $postalAddress = $dom->createElement('cac:PostalAddress');
            $country = $dom->createElement('cac:Country');
            $this->appendElement($dom, $country, 'cbc:IdentificationCode', $buyer->getCountryCode() !== '' ? $buyer->getCountryCode() : 'SA');
            $postalAddress->appendChild($country);
            $party->appendChild($postalAddress);
        }

        $accountingCustomerParty = $dom->createElement('cac:AccountingCustomerParty');
        $accountingCustomerParty->appendChild($party);
        $rootInvoice->appendChild($accountingCustomerParty);
    }

    /**
     * Add delivery information.
     */
    protected function addDeliveryInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Supply date (KSA-5) and supply end date (KSA-24)
        $delivery = $dom->createElement('cac:Delivery');
        $this->appendElementIfNotEmpty($dom, $delivery, 'cbc:ActualDeliveryDate', $invoiceData->getSupplyDate());
        $this->appendElementIfNotEmpty($dom, $delivery, 'cbc:LatestDeliveryDate', $invoiceData->getSupplyEndDate());
        
        $deliveryInfo = $invoiceData->getDeliveryInfo();
        if (!empty($deliveryInfo)) {
            $deliveryLocation = $dom->createElement('cac:DeliveryLocation');
            $address = $dom->createElement('cac:Address');
            
            if (isset($deliveryInfo['street'])) {
                $this->appendElement($dom, $address, 'cbc:StreetName', $deliveryInfo['street']);
            }
            if (isset($deliveryInfo['city'])) {
                $this->appendElement($dom, $address, 'cbc:CityName', $deliveryInfo['city']);
            }
            if (isset($deliveryInfo['postal_code'])) {
                $this->appendElement($dom, $address, 'cbc:PostalZone', $deliveryInfo['postal_code']);
            }
            
            $country = $dom->createElement('cac:Country');
            $this->appendElement($dom, $country, 'cbc:IdentificationCode', $deliveryInfo['country_code'] ?? 'SA');
            $address->appendChild($country);
            $deliveryLocation->appendChild($address);
            $delivery->appendChild($deliveryLocation);
        }
        
        $rootInvoice->appendChild($delivery);
    }

    /**
     * Add payment information.
     *
     * Note: InstructionNote is only required for Debit Notes (383) and Credit Notes (381)
     * per ZATCA requirement BR-KSA-17
     */
    protected function addPaymentInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Check if InstructionNote should be included (only for debit/credit notes)
        $invoiceTypeCode = $invoiceData->getInvoiceTypeCode();
        $shouldIncludeInstructionNote = in_array($invoiceTypeCode, ['383', '381']);

        // Add default payment means if none provided
        if (empty($invoiceData->getPaymentMeans())) {
            $paymentMeansElement = $dom->createElement('cac:PaymentMeans');
            $this->appendElement($dom, $paymentMeansElement, 'cbc:PaymentMeansCode', '10');

            // Only add InstructionNote for debit/credit notes (BR-KSA-17)
            if ($shouldIncludeInstructionNote) {
                $this->appendElement($dom, $paymentMeansElement, 'cbc:InstructionNote', 'إلغاء أو تعليق التوريدات بعد حدوثها كليًا أو جزئيًا');
            }

            $rootInvoice->appendChild($paymentMeansElement);
        } else {
            foreach ($invoiceData->getPaymentMeans() as $paymentMeans) {
                $paymentMeansElement = $dom->createElement('cac:PaymentMeans');
                // UBL order: ID, PaymentMeansCode, PaymentDueDate,
                // PaymentChannelCode, InstructionNote, PaymentID.
                $this->appendElement($dom, $paymentMeansElement, 'cbc:ID', (string)($paymentMeans['id'] ?? '1'));
                $this->appendElement($dom, $paymentMeansElement, 'cbc:PaymentMeansCode', (string)($paymentMeans['code'] ?? '10'));
                $this->appendElementIfNotEmpty($dom, $paymentMeansElement, 'cbc:PaymentDueDate', (string)($paymentMeans['due_date'] ?? $invoiceData->getDueDate()));
                $this->appendElementIfNotEmpty($dom, $paymentMeansElement, 'cbc:PaymentChannelCode', (string)($paymentMeans['channel_code'] ?? ''));

                // Only add InstructionNote for debit/credit notes (BR-KSA-17)
                if ($shouldIncludeInstructionNote) {
                    $this->appendElement($dom, $paymentMeansElement, 'cbc:InstructionNote', $paymentMeans['instruction_note'] ?? 'إلغاء أو تعليق التوريدات بعد حدوثها كليًا أو جزئيًا');
                }

                $this->appendElementIfNotEmpty($dom, $paymentMeansElement, 'cbc:PaymentID', (string)($paymentMeans['payment_id'] ?? ''));

                $rootInvoice->appendChild($paymentMeansElement);
            }
        }
    }

    /**
     * Add document-level allowances.
     */
    protected function addDocumentLevelAllowances(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Add default allowance charge if none provided
        if (empty($invoiceData->getAllowances())) {
            $allowanceElement = $dom->createElement('cac:AllowanceCharge');
            $this->appendElement($dom, $allowanceElement, 'cbc:ChargeIndicator', 'false');
            $this->appendElement($dom, $allowanceElement, 'cbc:AllowanceChargeReason', 'discount');
            $this->appendElementWithCurrency($dom, $allowanceElement, 'cbc:Amount', 0.00, $invoiceData->getDocumentCurrencyCode());

            $taxCategory = $invoiceData->getAllowanceChargeTaxCategory([]);
            $allowanceElement->appendChild(
                $this->createTaxCategoryElement($dom, 'cac:TaxCategory', $taxCategory['category'], $taxCategory['percent'])
            );

            $rootInvoice->appendChild($allowanceElement);
        } else {
            foreach ($invoiceData->getAllowances() as $allowance) {
                $rootInvoice->appendChild($this->createAllowanceChargeElement($dom, $invoiceData, $allowance, false));
            }
        }
    }

    /**
     * Add document-level charges.
     */
    protected function addDocumentLevelCharges(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        foreach ($invoiceData->getCharges() as $charge) {
            $rootInvoice->appendChild($this->createAllowanceChargeElement($dom, $invoiceData, $charge, true));
        }
    }

    /**
     * Build a document-level cac:AllowanceCharge (BG-20 / BG-21).
     */
    protected function createAllowanceChargeElement(DOMDocument $dom, InvoiceData $invoiceData, array $allowanceCharge, bool $isCharge): DOMElement
    {
        $currency = $invoiceData->getDocumentCurrencyCode();

        $element = $dom->createElement('cac:AllowanceCharge');
        $this->appendElement($dom, $element, 'cbc:ID', (string)($allowanceCharge['id'] ?? '1'));
        $this->appendElement($dom, $element, 'cbc:ChargeIndicator', $isCharge ? 'true' : 'false');
        $this->appendElementIfNotEmpty($dom, $element, 'cbc:AllowanceChargeReasonCode', (string)($allowanceCharge['reason_code'] ?? ''));
        $this->appendElementIfNotEmpty($dom, $element, 'cbc:AllowanceChargeReason', (string)($allowanceCharge['reason'] ?? ''));

        if (isset($allowanceCharge['multiplier'])) {
            $this->appendElement($dom, $element, 'cbc:MultiplierFactorNumeric', (string)$allowanceCharge['multiplier']);
        }

        $this->appendElementWithCurrency($dom, $element, 'cbc:Amount', (float)($allowanceCharge['amount'] ?? 0), $currency);

        if (isset($allowanceCharge['base_amount'])) {
            $this->appendElementWithCurrency($dom, $element, 'cbc:BaseAmount', (float)$allowanceCharge['base_amount'], $currency);
        }

        $taxCategory = $invoiceData->getAllowanceChargeTaxCategory($allowanceCharge);
        $element->appendChild(
            $this->createTaxCategoryElement($dom, 'cac:TaxCategory', $taxCategory['category'], $taxCategory['percent'])
        );

        return $element;
    }

    /**
     * Add tax information.
     */
    protected function addTaxInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        $currency = $invoiceData->getDocumentCurrencyCode();

        // Invoice total VAT amount in accounting currency (BT-111), without
        // subtotals (BR-KSA-EN16931-09). The only amount in the tax currency.
        $taxTotal2 = $dom->createElement('cac:TaxTotal');
        $this->appendElementWithCurrency($dom, $taxTotal2, 'cbc:TaxAmount', $invoiceData->getTaxTotalAmountInTaxCurrency(), $invoiceData->getTaxCurrencyCode());
        $rootInvoice->appendChild($taxTotal2);

        // Invoice total VAT amount (BT-110) with the VAT breakdown, in the
        // invoice currency (BR-KSA-CL-02).
        $taxTotal = $dom->createElement('cac:TaxTotal');
        $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:TaxAmount', $invoiceData->getTaxTotalAmount(), $currency);

        // One TaxSubtotal per VAT category / rate / exemption reason (BG-23).
        foreach ($invoiceData->getTaxSubtotals() as $subtotal) {
            $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
            $this->appendElementWithCurrency($dom, $taxSubtotal, 'cbc:TaxableAmount', $subtotal['taxable_amount'], $currency);
            $this->appendElementWithCurrency($dom, $taxSubtotal, 'cbc:TaxAmount', $subtotal['tax_amount'], $currency);
            $taxSubtotal->appendChild($this->createTaxCategoryElement(
                $dom,
                'cac:TaxCategory',
                $subtotal['category'],
                $subtotal['percent'],
                $subtotal['exemption_reason_code'],
                $subtotal['exemption_reason']
            ));
            $taxTotal->appendChild($taxSubtotal);
        }

        $rootInvoice->appendChild($taxTotal);
    }

    /**
     * Add monetary totals.
     */
    protected function addMonetaryTotals(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        $legalMonetaryTotal = $dom->createElement('cac:LegalMonetaryTotal');
        
        // Add elements with currencyID attributes
        // LineExtensionAmount is the sum of line net amounts (BR-CO-10);
        // TaxExclusiveAmount = LineExtension - allowances + charges (BR-CO-13).
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:LineExtensionAmount', $invoiceData->getLineExtensionAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:TaxExclusiveAmount', $invoiceData->getTaxExclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:TaxInclusiveAmount', $invoiceData->getTaxInclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:AllowanceTotalAmount', $invoiceData->getAllowanceTotalAmount(), $invoiceData->getDocumentCurrencyCode());

        if ($invoiceData->getChargeTotalAmount() > 0) {
            $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:ChargeTotalAmount', $invoiceData->getChargeTotalAmount(), $invoiceData->getDocumentCurrencyCode());
        }

        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:PrepaidAmount', $invoiceData->getPrepaidAmount(), $invoiceData->getDocumentCurrencyCode());

        if ($invoiceData->getPayableRoundingAmount() != 0) {
            $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:PayableRoundingAmount', $invoiceData->getPayableRoundingAmount(), $invoiceData->getDocumentCurrencyCode());
        }
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:PayableAmount', $invoiceData->getPayableAmount(), $invoiceData->getDocumentCurrencyCode());
        
        $rootInvoice->appendChild($legalMonetaryTotal);
    }

    /**
     * Add invoice lines.
     */
    protected function addInvoiceLines(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        foreach ($invoiceData->getLines() as $line) {
            $invoiceLine = $dom->createElement('cac:InvoiceLine');
            $this->appendElement($dom, $invoiceLine, 'cbc:ID', (string)$line->getId());
            
            // InvoicedQuantity with unitCode. Quantities have no decimal limit (§7.3).
            $invoicedQuantity = $dom->createElement('cbc:InvoicedQuantity', Money::format($line->getQuantity()));
            $invoicedQuantity->setAttribute('unitCode', $line->getUnitCode());
            $invoiceLine->appendChild($invoicedQuantity);
            
            // LineExtensionAmount is the NET line amount (gross - line allowance).
            $this->appendElementWithCurrency($dom, $invoiceLine, 'cbc:LineExtensionAmount', $line->getTaxExclusiveAmount(), $invoiceData->getDocumentCurrencyCode());

            // Line-level allowance (BG-27) and charge (BG-28). Must sit between
            // LineExtensionAmount and TaxTotal per the UBL InvoiceLine order.
            if ($line->getAllowanceAmount() > 0) {
                $ac = $dom->createElement('cac:AllowanceCharge');
                $this->appendElement($dom, $ac, 'cbc:ChargeIndicator', 'false');
                $this->appendElementIfNotEmpty($dom, $ac, 'cbc:AllowanceChargeReasonCode', $line->getAllowanceReasonCode());
                $this->appendElementIfNotEmpty($dom, $ac, 'cbc:AllowanceChargeReason', $line->getAllowanceReason());
                $this->appendElementWithCurrency($dom, $ac, 'cbc:Amount', $line->getAllowanceAmount(), $invoiceData->getDocumentCurrencyCode());
                $invoiceLine->appendChild($ac);
            }

            if ($line->getChargeAmount() > 0) {
                if ($line->getChargeReasonCode() === '' || $line->getChargeReason() === '') {
                    throw new InvalidArgumentException(
                        "Invoice line {$line->getId()} has a charge but no reason/reason code; "
                        . 'call setChargeReason() and setChargeReasonCode() (UNTDID 7161) (BR-KSA-20, BR-KSA-22).'
                    );
                }

                $ac = $dom->createElement('cac:AllowanceCharge');
                $this->appendElement($dom, $ac, 'cbc:ChargeIndicator', 'true');
                $this->appendElement($dom, $ac, 'cbc:AllowanceChargeReasonCode', $line->getChargeReasonCode());
                $this->appendElement($dom, $ac, 'cbc:AllowanceChargeReason', $line->getChargeReason());
                $this->appendElementWithCurrency($dom, $ac, 'cbc:Amount', $line->getChargeAmount(), $invoiceData->getDocumentCurrencyCode());
                $invoiceLine->appendChild($ac);
            }

            // Tax total for line
            $taxTotal = $dom->createElement('cac:TaxTotal');
            $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:TaxAmount', $line->getTaxAmount(), $invoiceData->getDocumentCurrencyCode());
            $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:RoundingAmount', $line->getTaxInclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
            
            // Item with ClassifiedTaxCategory
            $item = $dom->createElement('cac:Item');
            $this->appendElementIfNotEmpty($dom, $item, 'cbc:Description', $line->getDescription());
            $this->appendElement($dom, $item, 'cbc:Name', $line->getItemName());
            $this->appendItemIdentification($dom, $item, 'cac:BuyersItemIdentification', $line->getBuyersItemCode());
            $this->appendItemIdentification($dom, $item, 'cac:SellersItemIdentification', $line->getItemCode());
            $this->appendItemIdentification($dom, $item, 'cac:StandardItemIdentification', $line->getStandardItemCode(), $line->getStandardItemSchemeId());
            
            // Add ClassifiedTaxCategory to item
            $item->appendChild(
                $this->createTaxCategoryElement($dom, 'cac:ClassifiedTaxCategory', $line->getTaxCategory(), $line->getTaxPercent())
            );
            
            $invoiceLine->appendChild($taxTotal);
            $invoiceLine->appendChild($item);
            
            // Price: item net price (BT-146). A line-level discount is expressed
            // once, as the line AllowanceCharge above; a per-unit price
            // discount (BT-147) goes on the price with the gross price
            // (BT-148). Unit prices have no decimal limit (§7.3).
            $currency = $invoiceData->getDocumentCurrencyCode();
            $price = $dom->createElement('cac:Price');
            $this->appendUnitAmount($dom, $price, 'cbc:PriceAmount', $line->getNetPrice(), $currency);

            if ($line->getBaseQuantity() !== null) {
                $baseQuantity = $this->appendElement($dom, $price, 'cbc:BaseQuantity', Money::format($line->getBaseQuantity()));
                $baseQuantity->setAttribute('unitCode', $line->getUnitCode());
            }

            if ($line->getPriceDiscount() > 0) {
                $priceAllowance = $dom->createElement('cac:AllowanceCharge');
                $this->appendElement($dom, $priceAllowance, 'cbc:ChargeIndicator', 'false');
                $this->appendUnitAmount($dom, $priceAllowance, 'cbc:Amount', $line->getPriceDiscount(), $currency);
                $this->appendUnitAmount($dom, $priceAllowance, 'cbc:BaseAmount', $line->getUnitPrice(), $currency);
                $price->appendChild($priceAllowance);
            }

            $invoiceLine->appendChild($price);
            
            
            $rootInvoice->appendChild($invoiceLine);
        }

        $this->addPrepaymentLines($dom, $rootInvoice, $invoiceData);
    }

    /**
     * Add one zero-value invoice line per deducted prepayment invoice
     * (§9.5, BR-KSA-73 to 82).
     */
    protected function addPrepaymentLines(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        $currency = $invoiceData->getDocumentCurrencyCode();
        $lineId = 0;
        foreach ($invoiceData->getLines() as $line) {
            $lineId = max($lineId, $line->getId());
        }

        foreach ($invoiceData->getPrepayments() as $prepayment) {
            $invoiceLine = $dom->createElement('cac:InvoiceLine');
            $this->appendElement($dom, $invoiceLine, 'cbc:ID', (string)++$lineId);

            $quantity = $this->appendElement($dom, $invoiceLine, 'cbc:InvoicedQuantity', '0.00');
            $quantity->setAttribute('unitCode', 'PCE');
            $this->appendElementWithCurrency($dom, $invoiceLine, 'cbc:LineExtensionAmount', 0.0, $currency);

            // Prepayment invoice reference (KSA-26, KSA-28, KSA-29, KSA-30)
            $documentReference = $dom->createElement('cac:DocumentReference');
            $this->appendElement($dom, $documentReference, 'cbc:ID', $prepayment['id']);
            $this->appendElement($dom, $documentReference, 'cbc:IssueDate', $prepayment['issue_date']);
            $this->appendElement($dom, $documentReference, 'cbc:IssueTime', $prepayment['issue_time']);
            $this->appendElement($dom, $documentReference, 'cbc:DocumentTypeCode', '386');
            $invoiceLine->appendChild($documentReference);

            // Zero line VAT, plus the prepayment VAT breakdown (KSA-31 to KSA-34).
            $taxTotal = $dom->createElement('cac:TaxTotal');
            $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:TaxAmount', 0.0, $currency);
            $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:RoundingAmount', 0.0, $currency);

            $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
            $this->appendElementWithCurrency($dom, $taxSubtotal, 'cbc:TaxableAmount', $prepayment['taxable_amount'], $currency);
            $this->appendElementWithCurrency($dom, $taxSubtotal, 'cbc:TaxAmount', $prepayment['tax_amount'], $currency);
            $taxSubtotal->appendChild($this->createPrepaymentTaxCategory($dom, 'cac:TaxCategory', $prepayment));
            $taxTotal->appendChild($taxSubtotal);
            $invoiceLine->appendChild($taxTotal);

            $item = $dom->createElement('cac:Item');
            $this->appendElement($dom, $item, 'cbc:Name', 'Prepayment adjustment');
            $item->appendChild($this->createPrepaymentTaxCategory($dom, 'cac:ClassifiedTaxCategory', $prepayment));
            $invoiceLine->appendChild($item);

            $price = $dom->createElement('cac:Price');
            $this->appendElementWithCurrency($dom, $price, 'cbc:PriceAmount', 0.0, $currency);
            $invoiceLine->appendChild($price);

            $rootInvoice->appendChild($invoiceLine);
        }
    }

    /**
     * Prepayment VAT category; the rate is always present (BR-KSA-75 to 78).
     */
    protected function createPrepaymentTaxCategory(DOMDocument $dom, string $name, array $prepayment): DOMElement
    {
        $taxCategory = $dom->createElement($name);
        $this->appendElement($dom, $taxCategory, 'cbc:ID', $prepayment['tax_category']);
        $this->appendElement($dom, $taxCategory, 'cbc:Percent', number_format($prepayment['tax_percent'], 2, '.', ''));

        $taxScheme = $dom->createElement('cac:TaxScheme');
        $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
        $taxCategory->appendChild($taxScheme);

        return $taxCategory;
    }

    /**
     * Append an item identification (cac:*ItemIdentification/cbc:ID) when set.
     */
    protected function appendItemIdentification(DOMDocument $dom, DOMElement $item, string $name, string $id, string $schemeId = ''): void
    {
        if ($id === '') {
            return;
        }

        $identification = $dom->createElement($name);
        $idElement = $this->appendElement($dom, $identification, 'cbc:ID', $id);
        if ($schemeId !== '') {
            $idElement->setAttribute('schemeID', $schemeId);
        }
        $item->appendChild($identification);
    }

    /**
     * Append a unit-price amount with currencyID, keeping its decimals (§7.3).
     */
    protected function appendUnitAmount(DOMDocument $dom, DOMElement $parent, string $name, float $value, string $currencyCode): DOMElement
    {
        $element = $this->appendElement($dom, $parent, $name, Money::format($value));
        $element->setAttribute('currencyID', $currencyCode);
        return $element;
    }

    /**
     * Build a VAT category element (cac:TaxCategory / cac:ClassifiedTaxCategory).
     * Out-of-scope (O) carries no rate; exemption reasons go on the VAT breakdown only.
     */
    protected function createTaxCategoryElement(
        DOMDocument $dom,
        string $name,
        string $category,
        float $percent,
        ?string $exemptionReasonCode = null,
        ?string $exemptionReason = null
    ): DOMElement {
        $taxCategory = $dom->createElement($name);
        $this->appendElement($dom, $taxCategory, 'cbc:ID', $category);

        if (TaxCategory::hasPercent($category)) {
            $this->appendElement($dom, $taxCategory, 'cbc:Percent', number_format($percent, 2, '.', ''));
        }

        if ($exemptionReasonCode !== null) {
            $this->appendElement($dom, $taxCategory, 'cbc:TaxExemptionReasonCode', $exemptionReasonCode);
        }

        if ($exemptionReason !== null) {
            $this->appendElement($dom, $taxCategory, 'cbc:TaxExemptionReason', $exemptionReason);
        }

        $taxScheme = $dom->createElement('cac:TaxScheme');
        $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
        $taxCategory->appendChild($taxScheme);

        return $taxCategory;
    }

    /**
     * Helper method to append an element with text content.
     */
    protected function appendElement(DOMDocument $dom, DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $dom->createElement($name, $value);
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Helper method to append an element only when the value is a non-empty string.
     */
    protected function appendElementIfNotEmpty(DOMDocument $dom, DOMElement $parent, string $name, string $value): ?DOMElement
    {
        if ($value === '') {
            return null;
        }
        return $this->appendElement($dom, $parent, $name, $value);
    }

    /**
     * Helper method to append an element with currencyID attribute.
     */
    protected function appendElementWithCurrency(DOMDocument $dom, DOMElement $parent, string $name, float $value, string $currencyCode): DOMElement
    {
        $element = $dom->createElement($name, number_format($value, 2, '.', ''));
        $element->setAttribute('currencyID', $currencyCode);
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Generate a UUID for the invoice.
     */
    protected function generateUUID(): string
    {
        if (function_exists('random_bytes')) {
            $data = random_bytes(16);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            $data = uniqid('', true);
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
} 