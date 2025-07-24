<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class TaxNumber extends Tag
{
    public function __construct($value)
    {
        parent::__construct(2, $value);
    }
}