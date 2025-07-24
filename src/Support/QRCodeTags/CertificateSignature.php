<?php

namespace KhaledHajSalem\Zatca\Support\QRCodeTags;

class CertificateSignature extends Tag
{
    public function __construct($value)
    {
        parent::__construct(9, $value);
    }
}