<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use KhaledHajSalem\ZatcaPHP\Data\SellerData;

class SellerConfigurator
{
    public function __construct(private readonly SellerData $seller) {}

    public function name(string $name): self
    {
        $this->seller->setRegistrationName($name);

        return $this;
    }

    public function vat(string $vat): self
    {
        $this->seller->setTaxRegistrationNumber($vat);

        return $this;
    }

    public function partyId(string $id, string $scheme = 'CRN'): self
    {
        $this->seller->setPartyIdentification($id, $scheme);

        return $this;
    }

    public function address(string $street, string $building, string $city, string $postalCode, string $country = 'SA', string $district = ''): self
    {
        $this->seller->setStreetName($street);
        $this->seller->setBuildingNumber($building);
        $this->seller->setCityName($city);
        $this->seller->setPostalCode($postalCode);
        $this->seller->setCountryCode($country);
        if ($district) {
            $this->seller->setDistrictName($district);
        }

        return $this;
    }
}
