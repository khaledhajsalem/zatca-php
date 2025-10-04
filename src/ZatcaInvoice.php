<?php

namespace KhaledHajSalem\Zatca;

use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
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
            $this->appendElement($dom, $billingReference, 'cbc:ID', $reference['id'] ?? '');
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
        $pihValue = $invoiceData->getPreviousInvoiceHash() ?: '0';
        // If PIH is not already base64 encoded, encode it
        if (base64_decode($pihValue, true) === false) {
            $pihValue = base64_encode($pihValue);
        }
        
        $pihBinaryObject = $this->appendElement($dom, $pihAttachment, 'cbc:EmbeddedDocumentBinaryObject', $pihValue);
        $pihBinaryObject->setAttribute('mimeCode', 'text/plain');
        $pihBinaryObject->setAttribute('filename', 'base64');
        
        // Add custom document references
        foreach ($invoiceData->getDocumentReferences() as $reference) {
            $documentReference = $dom->createElement('cac:AdditionalDocumentReference');
            $this->appendElement($dom, $documentReference, 'cbc:ID', $reference['id'] ?? '');
            $this->appendElement($dom, $documentReference, 'cbc:DocumentType', $reference['type'] ?? '');
            $rootInvoice->appendChild($documentReference);
        }
        
        // Add custom document references
        foreach ($invoiceData->getDocumentReferences() as $reference) {
            $documentReference = $dom->createElement('cac:AdditionalDocumentReference');
            $this->appendElement($dom, $documentReference, 'cbc:ID', $reference['id'] ?? '');
            $this->appendElement($dom, $documentReference, 'cbc:DocumentType', $reference['type'] ?? '');
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
        $partyIdentification = $dom->createElement('cac:PartyIdentification');
        $idElement = $dom->createElement('cbc:ID', $seller->getPartyIdentification());
        $idElement->setAttribute('schemeID', $seller->getPartyIdentificationId());
        $partyIdentification->appendChild($idElement);
        $party->appendChild($partyIdentification);

//        // Party name
//        $partyName = $dom->createElement('cac:PartyName');
//        $this->appendElement($dom, $partyName, 'cbc:Name', $seller->getRegistrationName());
//        $party->appendChild($partyName);

        // Postal address
        $postalAddress = $dom->createElement('cac:PostalAddress');
        $this->appendElement($dom, $postalAddress, 'cbc:StreetName', $seller->getStreetName());
        $this->appendElement($dom, $postalAddress, 'cbc:BuildingNumber', $seller->getBuildingNumber());
        $this->appendElement($dom, $postalAddress, 'cbc:PlotIdentification', $seller->getPlotIdentification());
        $this->appendElement($dom, $postalAddress, 'cbc:CitySubdivisionName', $seller->getCitySubdivisionName());
        $this->appendElement($dom, $postalAddress, 'cbc:CityName', $seller->getCityName());
        $this->appendElement($dom, $postalAddress, 'cbc:PostalZone', $seller->getPostalZone());
        $this->appendElement($dom, $postalAddress, 'cbc:CountrySubentity', $seller->getCityName());
        
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
        if (!$invoiceData->getBuyer()) {
            return;
        }

        $buyer = $invoiceData->getBuyer();
        $accountingCustomerParty = $dom->createElement('cac:AccountingCustomerParty');
        $party = $dom->createElement('cac:Party');

        // Party identification with schemeID
        $partyIdentification = $dom->createElement('cac:PartyIdentification');
        $idElement = $dom->createElement('cbc:ID', $buyer->getPartyIdentification());
        $idElement->setAttribute('schemeID', $buyer->getPartyIdentificationId());
        $partyIdentification->appendChild($idElement);
        $party->appendChild($partyIdentification);
//
//        // Party name
//        $partyName = $dom->createElement('cac:PartyName');
//        $this->appendElement($dom, $partyName, 'cbc:Name', $buyer->getRegistrationName());
//        $party->appendChild($partyName);

        // Postal address
        $postalAddress = $dom->createElement('cac:PostalAddress');
        $this->appendElement($dom, $postalAddress, 'cbc:StreetName', $buyer->getStreetName());
        $this->appendElement($dom, $postalAddress, 'cbc:BuildingNumber', $buyer->getBuildingNumber());
        $this->appendElement($dom, $postalAddress, 'cbc:PlotIdentification', $buyer->getPlotIdentification());
        $this->appendElement($dom, $postalAddress, 'cbc:CitySubdivisionName', $buyer->getCitySubdivisionName());
        $this->appendElement($dom, $postalAddress, 'cbc:CityName', $buyer->getCityName());
        $this->appendElement($dom, $postalAddress, 'cbc:PostalZone', $buyer->getPostalZone());
        $this->appendElement($dom, $postalAddress, 'cbc:CountrySubentity', $buyer->getCityName());
        
        $country = $dom->createElement('cac:Country');
        $this->appendElement($dom, $country, 'cbc:IdentificationCode', $buyer->getCountryCode());
        $postalAddress->appendChild($country);
        $party->appendChild($postalAddress);

        if (!empty($buyer->getVatNumber())) {
            // Party tax scheme
            $partyTaxScheme = $dom->createElement('cac:PartyTaxScheme');
            $this->appendElement($dom, $partyTaxScheme, 'cbc:CompanyID', $buyer->getVatNumber());

            $taxScheme = $dom->createElement('cac:TaxScheme');
            $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
            $partyTaxScheme->appendChild($taxScheme);
            $party->appendChild($partyTaxScheme);
        }

        // Party legal entity
        $partyLegalEntity = $dom->createElement('cac:PartyLegalEntity');
        $this->appendElement($dom, $partyLegalEntity, 'cbc:RegistrationName', $buyer->getRegistrationName());
        $party->appendChild($partyLegalEntity);

        $accountingCustomerParty->appendChild($party);
        $rootInvoice->appendChild($accountingCustomerParty);
    }

    /**
     * Add delivery information.
     */
    protected function addDeliveryInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Add delivery with actual delivery date
        $delivery = $dom->createElement('cac:Delivery');
        $this->appendElement($dom, $delivery, 'cbc:ActualDeliveryDate', $invoiceData->getIssueDate());
        
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
     */
    protected function addPaymentInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        // Add default payment means if none provided
        if (empty($invoiceData->getPaymentMeans())) {
            $paymentMeansElement = $dom->createElement('cac:PaymentMeans');
            $this->appendElement($dom, $paymentMeansElement, 'cbc:PaymentMeansCode', '10');
            $this->appendElement($dom, $paymentMeansElement, 'cbc:InstructionNote', 'إلغاء أو تعليق التوريدات بعد حدوثها كليًا أو جزئيًا');
            $rootInvoice->appendChild($paymentMeansElement);
        } else {
            foreach ($invoiceData->getPaymentMeans() as $paymentMeans) {
                $paymentMeansElement = $dom->createElement('cac:PaymentMeans');
                $this->appendElement($dom, $paymentMeansElement, 'cbc:ID', $paymentMeans['id'] ?? '1');
                $this->appendElement($dom, $paymentMeansElement, 'cbc:PaymentMeansCode', $paymentMeans['code'] ?? '10');
                $this->appendElement($dom, $paymentMeansElement, 'cbc:PaymentDueDate', $paymentMeans['due_date'] ?? $invoiceData->getDueDate());
                $this->appendElement($dom, $paymentMeansElement, 'cbc:InstructionNote', $paymentMeans['instruction_note'] ?? 'إلغاء أو تعليق التوريدات بعد حدوثها كليًا أو جزئيًا');
                
                if (isset($paymentMeans['amount'])) {
                    $paymentMeansElement->appendChild($dom->createElement('cbc:PaymentChannelCode', ''));
                    $paymentMeansElement->appendChild($dom->createElement('cbc:PaymentID', ''));
                }
                
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
            
            // Add tax category to allowance
            $taxCategory = $dom->createElement('cac:TaxCategory');
            $this->appendElement($dom, $taxCategory, 'cbc:ID', 'S');
            $this->appendElement($dom, $taxCategory, 'cbc:Percent', '15');
            
            $taxScheme = $dom->createElement('cac:TaxScheme');
            $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
            $taxCategory->appendChild($taxScheme);
            $allowanceElement->appendChild($taxCategory);
            
            $rootInvoice->appendChild($allowanceElement);
        } else {
            foreach ($invoiceData->getAllowances() as $allowance) {
                $allowanceElement = $dom->createElement('cac:AllowanceCharge');
                $this->appendElement($dom, $allowanceElement, 'cbc:ID', $allowance['id'] ?? '1');
                $this->appendElement($dom, $allowanceElement, 'cbc:ChargeIndicator', 'true');
                $this->appendElement($dom, $allowanceElement, 'cbc:AllowanceChargeReason', $allowance['reason'] ?? '');
                $this->appendElement($dom, $allowanceElement, 'cbc:MultiplierFactorNumeric', $allowance['multiplier'] ?? '0');
                $this->appendElement($dom, $allowanceElement, 'cbc:Amount', number_format($allowance['amount'] ?? 0, 2, '.', ''));
                $this->appendElement($dom, $allowanceElement, 'cbc:BaseAmount', number_format($allowance['base_amount'] ?? 0, 2, '.', ''));
                $rootInvoice->appendChild($allowanceElement);
            }
        }
    }

    /**
     * Add document-level charges.
     */
    protected function addDocumentLevelCharges(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        foreach ($invoiceData->getCharges() as $charge) {
            $chargeElement = $dom->createElement('cac:AllowanceCharge');
            $this->appendElement($dom, $chargeElement, 'cbc:ID', $charge['id'] ?? '1');
            $this->appendElement($dom, $chargeElement, 'cbc:ChargeIndicator', 'true');
            $this->appendElement($dom, $chargeElement, 'cbc:AllowanceChargeReason', $charge['reason'] ?? '');
            $this->appendElement($dom, $chargeElement, 'cbc:MultiplierFactorNumeric', $charge['multiplier'] ?? '0');
            $this->appendElement($dom, $chargeElement, 'cbc:Amount', number_format($charge['amount'] ?? 0, 2, '.', ''));
            $this->appendElement($dom, $chargeElement, 'cbc:BaseAmount', number_format($charge['base_amount'] ?? 0, 2, '.', ''));
            $rootInvoice->appendChild($chargeElement);
        }
    }

    /**
     * Add tax information.
     */
    protected function addTaxInformation(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        $taxTotal2 = $dom->createElement('cac:TaxTotal');
        $this->appendElementWithCurrency($dom, $taxTotal2, 'cbc:TaxAmount', $invoiceData->getTaxTotalAmount(), $invoiceData->getTaxCurrencyCode());
        $rootInvoice->appendChild($taxTotal2);

        $taxTotal = $dom->createElement('cac:TaxTotal');
        $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:TaxAmount', $invoiceData->getTaxTotalAmount(), $invoiceData->getTaxCurrencyCode());
        
        $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
        $this->appendElementWithCurrency($dom, $taxSubtotal, 'cbc:TaxableAmount', $invoiceData->getTaxExclusiveAmount(), $invoiceData->getTaxCurrencyCode());
        $this->appendElementWithCurrency($dom, $taxSubtotal, 'cbc:TaxAmount', $invoiceData->getTaxTotalAmount(), $invoiceData->getTaxCurrencyCode());
        
        $taxCategory = $dom->createElement('cac:TaxCategory');
        $this->appendElement($dom, $taxCategory, 'cbc:ID', 'S');
        $this->appendElement($dom, $taxCategory, 'cbc:Percent', '15');
        
        $taxScheme = $dom->createElement('cac:TaxScheme');
        $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
        $taxCategory->appendChild($taxScheme);
        $taxSubtotal->appendChild($taxCategory);
        $taxTotal->appendChild($taxSubtotal);
        $rootInvoice->appendChild($taxTotal);
    }

    /**
     * Add monetary totals.
     */
    protected function addMonetaryTotals(DOMDocument $dom, DOMElement $rootInvoice, InvoiceData $invoiceData): void
    {
        $legalMonetaryTotal = $dom->createElement('cac:LegalMonetaryTotal');
        
        // Add elements with currencyID attributes
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:LineExtensionAmount', $invoiceData->getTaxExclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:TaxExclusiveAmount', $invoiceData->getTaxExclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:TaxInclusiveAmount', $invoiceData->getTaxInclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:AllowanceTotalAmount', $invoiceData->getAllowanceTotalAmount(), $invoiceData->getDocumentCurrencyCode());
        $this->appendElementWithCurrency($dom, $legalMonetaryTotal, 'cbc:PrepaidAmount', 0.00, $invoiceData->getDocumentCurrencyCode());
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
            
            // InvoicedQuantity with unitCode
            $invoicedQuantity = $dom->createElement('cbc:InvoicedQuantity', number_format($line->getQuantity(), 2));
            $invoicedQuantity->setAttribute('unitCode', 'PCE');
            $invoiceLine->appendChild($invoicedQuantity);
            
            $this->appendElementWithCurrency($dom, $invoiceLine, 'cbc:LineExtensionAmount', $line->getLineExtensionAmount(), $invoiceData->getDocumentCurrencyCode());
            
            // Tax total for line
            $taxTotal = $dom->createElement('cac:TaxTotal');
            $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:TaxAmount', $line->getTaxAmount(), $invoiceData->getDocumentCurrencyCode());
            $this->appendElementWithCurrency($dom, $taxTotal, 'cbc:RoundingAmount', $line->getTaxInclusiveAmount(), $invoiceData->getDocumentCurrencyCode());
            
            // Item with ClassifiedTaxCategory
            $item = $dom->createElement('cac:Item');
            $this->appendElement($dom, $item, 'cbc:Name', $line->getItemName());
            
            // Add ClassifiedTaxCategory to item
            $classifiedTaxCategory = $dom->createElement('cac:ClassifiedTaxCategory');
            $this->appendElement($dom, $classifiedTaxCategory, 'cbc:ID', 'S');
            $this->appendElement($dom, $classifiedTaxCategory, 'cbc:Percent', number_format($line->getTaxPercent(), 2, '.', ''));
            
            $taxScheme = $dom->createElement('cac:TaxScheme');
            $this->appendElement($dom, $taxScheme, 'cbc:ID', 'VAT');
            $classifiedTaxCategory->appendChild($taxScheme);
            $item->appendChild($classifiedTaxCategory);
            
            $invoiceLine->appendChild($taxTotal);
            $invoiceLine->appendChild($item);
            
            // Price with AllowanceCharge
            $price = $dom->createElement('cac:Price');
            $this->appendElementWithCurrency($dom, $price, 'cbc:PriceAmount', $line->getUnitPrice(), $invoiceData->getDocumentCurrencyCode());
            
            // Add AllowanceCharge to price
            $allowanceCharge = $dom->createElement('cac:AllowanceCharge');
            $this->appendElement($dom, $allowanceCharge, 'cbc:ChargeIndicator', 'false');
            $this->appendElement($dom, $allowanceCharge, 'cbc:AllowanceChargeReason', 'discount');
            $this->appendElementWithCurrency($dom, $allowanceCharge, 'cbc:Amount', 0.00, $invoiceData->getDocumentCurrencyCode());
            $price->appendChild($allowanceCharge);
            
            $invoiceLine->appendChild($price);
            
            
            $rootInvoice->appendChild($invoiceLine);
        }
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