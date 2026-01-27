<?php

namespace common\components\bansystem\dto;

class RustAppSteamData
{
    public ?string $avatarHash = null;
    public ?bool $profileFilled = null;
    public ?string $avatarSmallUrl = null;
    public ?string $avatarMediumUrl = null;
    public ?string $avatarFullUrl = null;
    public ?bool $visible = null;
    public ?int $signedAt = null;
    public ?RustAppBanData $banData = null;
    public ?int $hoursTotal = null;
    public ?int $hours2Week = null;
    public ?int $rustHoursTotal = null;
    public ?int $rustHours2Week = null;
    public ?int $createdAt = null;
    public ?int $updatedAt = null;

    public static function fromArray(array $data): self
    {
        $steamData = new self();
        $steamData->avatarHash = $data['avatar_hash'] ?? null;
        $steamData->profileFilled = isset($data['profile_filled']) ? (bool)$data['profile_filled'] : null;
        $steamData->avatarSmallUrl = $data['avatar_small_url'] ?? null;
        $steamData->avatarMediumUrl = $data['avatar_medium_url'] ?? null;
        $steamData->avatarFullUrl = $data['avatar_full_url'] ?? null;
        $steamData->visible = isset($data['visible']) ? (bool)$data['visible'] : null;
        $steamData->signedAt = isset($data['signed_at']) ? (int)$data['signed_at'] : null;
        if (isset($data['ban_data']) && is_array($data['ban_data'])) {
            $steamData->banData = RustAppBanData::fromArray($data['ban_data']);
        }
        $steamData->hoursTotal = isset($data['hours_total']) ? (int)$data['hours_total'] : null;
        $steamData->hours2Week = isset($data['hours_2week']) ? (int)$data['hours_2week'] : null;
        $steamData->rustHoursTotal = isset($data['rust_hours_total']) ? (int)$data['rust_hours_total'] : null;
        $steamData->rustHours2Week = isset($data['rust_hours_2week']) ? (int)$data['rust_hours_2week'] : null;
        $steamData->createdAt = isset($data['created_at']) ? (int)$data['created_at'] : null;
        $steamData->updatedAt = isset($data['updated_at']) ? (int)$data['updated_at'] : null;

        return $steamData;
    }
}

