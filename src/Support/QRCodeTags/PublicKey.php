<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class PublicKey extends Tag
{
    public function __construct($value)
    {
        parent::__construct(8, $value);
    }
}