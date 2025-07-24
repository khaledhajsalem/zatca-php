<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class InvoiceTotalAmount extends Tag
{
    public function __construct($value)
    {
        parent::__construct(4, $value);
    }
}