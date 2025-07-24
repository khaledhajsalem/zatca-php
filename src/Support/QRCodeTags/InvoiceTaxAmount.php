<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class InvoiceTaxAmount extends Tag
{
    public function __construct($value)
    {
        parent::__construct(5, $value);
    }
}