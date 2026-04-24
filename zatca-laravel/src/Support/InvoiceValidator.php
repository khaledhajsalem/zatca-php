<?php

namespace KhaledHajSalem\ZatcaLaravel\Support;

use KhaledHajSalem\ZatcaPHP\Data\BuyerData;
use KhaledHajSalem\ZatcaPHP\Data\InvoiceData;
use KhaledHajSalem\ZatcaPHP\Data\SellerData;

class InvoiceValidator
{
    public function validate(InvoiceData $invoice, SellerData $seller, ?BuyerData $buyer, array $lines): array
    {
        $errors = [];

        $errors = array_merge($errors, $this->validateInvoice($invoice));
        $errors = array_merge($errors, $this->validateSeller($seller));
        $errors = array_merge($errors, $this->validateBuyer($buyer, $invoice));
        $errors = array_merge($errors, $this->validateLines($lines));

        return $errors;
    }

    private function validateInvoice(InvoiceData $invoice): array
    {
        $errors = [];

        if (empty($invoice->getInvoiceNumber())) {
            $errors[] = 'Invoice number is required';
        }

        if (empty($invoice->getIssueDate())) {
            $errors[] = 'Issue date is required';
        } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice->getIssueDate())) {
            $errors[] = 'Issue date must be in Y-m-d format';
        }

        if (empty($invoice->getIssueTime())) {
            $errors[] = 'Issue time is required';
        } elseif (! preg_match('/^\d{2}:\d{2}:\d{2}$/', $invoice->getIssueTime())) {
            $errors[] = 'Issue time must be in H:i:s format';
        }

        if (empty($invoice->getInvoiceTypeCode())) {
            $errors[] = 'Invoice type code is required';
        }

        if (empty($invoice->getUUID())) {
            $errors[] = 'UUID is required';
        }

        return $errors;
    }

    private function validateSeller(SellerData $seller): array
    {
        $errors = [];

        if (empty($seller->getRegistrationName())) {
            $errors[] = 'Seller registration name is required';
        }

        $vat = $seller->getTaxRegistrationNumber();
        if (empty($vat)) {
            $errors[] = 'Seller VAT number is required';
        } elseif (! $this->isValidVat($vat)) {
            $errors[] = 'Seller VAT number must be exactly 15 digits, starting and ending with 3';
        }

        return $errors;
    }

    private function validateBuyer(?BuyerData $buyer, InvoiceData $invoice): array
    {
        $errors = [];

        if ($invoice->getInvoiceTypeName() === '0100000') {
            if (! $buyer) {
                $errors[] = 'Buyer information is required for standard (B2B) invoices';

                return $errors;
            }

            if (empty($buyer->getRegistrationName())) {
                $errors[] = 'Buyer registration name is required for standard invoices';
            }

            $vat = $buyer->getTaxRegistrationNumber();
            if (! empty($vat) && ! $this->isValidVat($vat)) {
                $errors[] = 'Buyer VAT number must be exactly 15 digits, starting and ending with 3';
            }
        }

        return $errors;
    }

    private function validateLines(array $lines): array
    {
        $errors = [];

        if (empty($lines)) {
            $errors[] = 'At least one line item is required';
        }

        foreach ($lines as $i => $line) {
            $idx = $i + 1;
            if ($line->getQuantity() <= 0) {
                $errors[] = "Line {$idx}: quantity must be positive";
            }
            if ($line->getUnitPrice() < 0) {
                $errors[] = "Line {$idx}: unit price cannot be negative";
            }
        }

        return $errors;
    }

    private function isValidVat(string $vat): bool
    {
        return preg_match('/^3\d{13}3$/', $vat) === 1;
    }
}
