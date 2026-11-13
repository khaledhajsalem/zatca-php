<?php

namespace KhaledHajSalem\Zatca\Data;

/**
 * Data class for buyer information.
 */
class BuyerData
{
    protected string $registrationName = '';
    protected string $vatNumber = '';
    protected string $partyIdentification = '';
    protected string $partyIdentificationId = 'TIN';
    protected string $address = '';
    protected string $countryCode = 'SA';
    protected string $cityName = '';
    protected string $postalZone = '';
    protected string $streetName = '';
    protected string $additionalStreetName = '';
    protected string $buildingNumber = '';
    protected string $plotIdentification = '';
    protected string $citySubdivisionName = '';
    protected string $companyLegalForm = '';
    protected string $contactName = '';
    protected string $contactTelephone = '';
    protected string $contactEmail = '';
    protected string $contactNote = '';

    public function setRegistrationName(string $registrationName): self
    {
        $this->registrationName = $registrationName;
        return $this;
    }

    public function getRegistrationName(): string
    {
        return $this->registrationName;
    }

    public function setVatNumber(string $vatNumber): self
    {
        $this->vatNumber = $vatNumber;
        return $this;
    }

    public function getVatNumber(): string
    {
        return $this->vatNumber;
    }

    public function setPartyIdentification(string $partyIdentification): self
    {
        $this->partyIdentification = $partyIdentification;
        return $this;
    }

    public function setPartyIdentificationId(string $partyIdentificationId): self
    {
        $this->partyIdentificationId = $partyIdentificationId;
        return $this;
    }

    public function getPartyIdentification(): string
    {
        return $this->partyIdentification;
    }

    public function getPartyIdentificationId(): string
    {
        return $this->partyIdentificationId;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCityName(string $cityName): self
    {
        $this->cityName = $cityName;
        return $this;
    }

    public function getCityName(): string
    {
        return $this->cityName;
    }

    public function setPostalZone(string $postalZone): self
    {
        $this->postalZone = $postalZone;
        return $this;
    }

    public function getPostalZone(): string
    {
        return $this->postalZone;
    }

    public function setStreetName(string $streetName): self
    {
        $this->streetName = $streetName;
        return $this;
    }

    public function getStreetName(): string
    {
        return $this->streetName;
    }

    /**
     * Additional street name (BT-36 / BT-51). Optional.
     */
    public function setAdditionalStreetName(string $additionalStreetName): self
    {
        $this->additionalStreetName = $additionalStreetName;
        return $this;
    }

    public function getAdditionalStreetName(): string
    {
        return $this->additionalStreetName;
    }

    public function setBuildingNumber(string $buildingNumber): self
    {
        $this->buildingNumber = $buildingNumber;
        return $this;
    }

    public function getBuildingNumber(): string
    {
        return $this->buildingNumber;
    }

    public function setPlotIdentification(string $plotIdentification): self
    {
        $this->plotIdentification = $plotIdentification;
        return $this;
    }

    public function getPlotIdentification(): string
    {
        return $this->plotIdentification;
    }

    public function setCitySubdivisionName(string $citySubdivisionName): self
    {
        $this->citySubdivisionName = $citySubdivisionName;
        return $this;
    }

    public function getCitySubdivisionName(): string
    {
        return $this->citySubdivisionName;
    }

    /**
     * Company legal form of the buyer (KSA-40,
     * cac:PartyLegalEntity/cbc:CompanyLegalForm). Optional.
     */
    public function setCompanyLegalForm(string $companyLegalForm): self
    {
        $this->companyLegalForm = $companyLegalForm;
        return $this;
    }

    public function getCompanyLegalForm(): string
    {
        return $this->companyLegalForm;
    }

    /**
     * Buyer contact details (cac:Contact). All optional; ZATCA accepts
     * several e-mail addresses separated by ";".
     */
    public function setContact(string $name = '', string $telephone = '', string $email = '', string $note = ''): self
    {
        $this->contactName = $name;
        $this->contactTelephone = $telephone;
        $this->contactEmail = $email;
        $this->contactNote = $note;
        return $this;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function getContactTelephone(): string
    {
        return $this->contactTelephone;
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function getContactNote(): string
    {
        return $this->contactNote;
    }

    public function hasContact(): bool
    {
        return $this->contactName !== '' || $this->contactTelephone !== ''
            || $this->contactEmail !== '' || $this->contactNote !== '';
    }
}
