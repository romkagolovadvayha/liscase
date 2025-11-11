<?php

namespace common\components\bansystem\dto;

class RustAppPlayerIpDetails
{
    public ?string $countryCode = null;
    public ?string $countryName = null;
    public ?string $city = null;
    public ?string $provider = null;
    public ?bool $proxy = null;

    public static function fromArray(array $data): self
    {
        $details = new self();
        $details->countryCode = $data['country_code'] ?? null;
        $details->countryName = $data['country_name'] ?? null;
        $details->city = $data['city'] ?? null;
        $details->provider = $data['provider'] ?? null;
        $details->proxy = isset($data['proxy']) ? (bool)$data['proxy'] : null;

        return $details;
    }
}

