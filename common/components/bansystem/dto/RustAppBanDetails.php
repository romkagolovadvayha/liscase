<?php

namespace common\components\bansystem\dto;

class RustAppBanDetails
{
    public ?int $count = null;
    public ?bool $exists = null;

    public static function fromArray(array $data): self
    {
        $details = new self();
        $details->count = isset($data['count']) ? (int)$data['count'] : null;
        $details->exists = isset($data['exists']) ? (bool)$data['exists'] : null;

        return $details;
    }
}

