<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class InvoiceDigitalSignature extends Tag
{
    public function __construct($value)
    {
        parent::__construct(7, $value);
    }
}