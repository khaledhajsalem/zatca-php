<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class InvoiceDate extends Tag
{
    public function __construct($value)
    {
        parent::__construct(3, $value);
    }
}