<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use KhaledHajSalem\ZatcaPHP\Data\BuyerData;

class BuyerConfigurator
{
    public function __construct(private readonly BuyerData $buyer) {}

    public function name(string $name): self
    {
        $this->buyer->setRegistrationName($name);

        return $this;
    }

    public function vat(string $vat): self
    {
        $this->buyer->setTaxRegistrationNumber($vat);

        return $this;
    }

    public function partyId(string $id, string $scheme = 'CRN'): self
    {
        $this->buyer->setPartyIdentification($id, $scheme);

        return $this;
    }

    public function address(string $street, string $building, string $city, string $postalCode, string $country = 'SA', string $district = ''): self
    {
        $this->buyer->setStreetName($street);
        $this->buyer->setBuildingNumber($building);
        $this->buyer->setCityName($city);
        $this->buyer->setPostalCode($postalCode);
        $this->buyer->setCountryCode($country);
        if ($district) {
            $this->buyer->setDistrictName($district);
        }

        return $this;
    }
}
