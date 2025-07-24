<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class Seller extends Tag
{
    public function __construct($value)
    {
        parent::__construct(1, $value);
    }
}