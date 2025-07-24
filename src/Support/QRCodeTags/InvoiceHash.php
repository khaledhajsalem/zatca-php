<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class InvoiceHash extends Tag
{
    public function __construct($value)
    {
        parent::__construct(6, $value);
    }
}