<?php

namespace KhaledHajSalem\Zatca\Exceptions;

/**
 * Thrown when invoice data fails validation before submission.
 * getIssues() returns the issues from InvoiceValidator::validate().
 */
class ZatcaValidationException extends ZatcaException
{
    public function __construct(array $issues)
    {
        $lines = array_map(fn(array $issue) => "[{$issue['rule']}] {$issue['field']}: {$issue['message']}", $issues);

        parent::__construct("Invoice validation failed:\n" . implode("\n", $lines), ['issues' => $issues]);
    }

    public function getIssues(): array
    {
        return $this->context['issues'];
    }
}
