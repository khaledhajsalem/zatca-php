<?php

namespace KhaledHajSalem\Zatca\Services;

/**
 * The outcome of submitting a document to ZATCA (reporting, clearance or a
 * compliance check), with the HTTP status code preserved.
 *
 * ZATCA signals several outcomes through the status code alone, so the
 * decoded body is not enough to decide what happened:
 *
 * - 200  accepted
 * - 202  accepted with warnings
 * - 208  clearance: this hash was already submitted; the cleared invoice is
 *        returned again
 * - 409  reporting: already reported. The body still says NOT_REPORTED, so
 *        it must not be read as a rejection
 * - 400  rejected with validation errors
 * - 303  clearance is deactivated; the document must be reported instead
 * - 401  credentials rejected
 * - 413, 429, 5xx  not received; the same document may be sent again
 */
class SubmissionResult
{
    public const ACCEPTED = 'accepted';
    public const ACCEPTED_WITH_WARNINGS = 'accepted_with_warnings';
    public const ALREADY_ACCEPTED = 'already_accepted';
    public const REJECTED = 'rejected';
    public const CLEARANCE_DISABLED = 'clearance_disabled';
    public const UNAUTHORIZED = 'unauthorized';
    public const NOT_RECEIVED = 'not_received';
    public const UNEXPECTED = 'unexpected';

    private string $operation;
    private int $statusCode;
    private array $body;

    /**
     * @param string $operation "reporting", "clearance" or "compliance"
     */
    public function __construct(string $operation, int $statusCode, array $body)
    {
        $this->operation = $operation;
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Decoded response body, exactly as ZATCA returned it. */
    public function getBody(): array
    {
        return $this->body;
    }

    /** One of the class constants. */
    public function getOutcome(): string
    {
        switch (true) {
            case $this->statusCode === 200:
                return $this->getWarnings() === [] ? self::ACCEPTED : self::ACCEPTED_WITH_WARNINGS;
            case $this->statusCode === 202:
                return self::ACCEPTED_WITH_WARNINGS;
            case $this->statusCode === 208:
            case $this->statusCode === 409:
                return self::ALREADY_ACCEPTED;
            case $this->statusCode === 400:
                return self::REJECTED;
            case $this->statusCode === 303:
                return self::CLEARANCE_DISABLED;
            case $this->statusCode === 401:
            case $this->statusCode === 403:
                return self::UNAUTHORIZED;
            case $this->statusCode === 413:
            case $this->statusCode === 429:
            case $this->statusCode >= 500:
                return self::NOT_RECEIVED;
            default:
                return self::UNEXPECTED;
        }
    }

    /** ZATCA holds the document: accepted now, or accepted on an earlier attempt. */
    public function isAccepted(): bool
    {
        return in_array($this->getOutcome(), [self::ACCEPTED, self::ACCEPTED_WITH_WARNINGS, self::ALREADY_ACCEPTED], true);
    }

    /** ZATCA validated the document and refused it. Sending it again will not help. */
    public function isRejected(): bool
    {
        return $this->getOutcome() === self::REJECTED;
    }

    /** The document was submitted before (208 on clearance, 409 on reporting). */
    public function isDuplicate(): bool
    {
        return $this->getOutcome() === self::ALREADY_ACCEPTED;
    }

    /** ZATCA did not receive the document; the identical document may be sent again. */
    public function shouldRetry(): bool
    {
        return $this->getOutcome() === self::NOT_RECEIVED;
    }

    public function hasWarnings(): bool
    {
        return $this->getWarnings() !== [];
    }

    /**
     * Validation errors: each has type, code, category, message, status.
     */
    public function getErrors(): array
    {
        return $this->messages('errorMessages');
    }

    /**
     * Validation warnings. Not available for duplicates: ZATCA does not
     * repeat the warnings of the original submission.
     */
    public function getWarnings(): array
    {
        return $this->messages('warningMessages');
    }

    public function getInfoMessages(): array
    {
        return $this->messages('infoMessages');
    }

    /** "REPORTED" / "NOT_REPORTED", when present. */
    public function getReportingStatus(): ?string
    {
        return isset($this->body['reportingStatus']) ? (string)$this->body['reportingStatus'] : null;
    }

    /** "CLEARED" / "NOT_CLEARED", when present. */
    public function getClearanceStatus(): ?string
    {
        return isset($this->body['clearanceStatus']) ? (string)$this->body['clearanceStatus'] : null;
    }

    /**
     * The cleared invoice XML carrying ZATCA's stamp (clearance only). This
     * is the document to give to the buyer.
     */
    public function getClearedInvoice(): ?string
    {
        if (empty($this->body['clearedInvoice']) || !is_string($this->body['clearedInvoice'])) {
            return null;
        }

        $decoded = base64_decode($this->body['clearedInvoice'], true);

        return $decoded === false ? null : $decoded;
    }

    private function messages(string $key): array
    {
        $messages = $this->body['validationResults'][$key] ?? [];

        return is_array($messages) ? array_values($messages) : [];
    }
}
