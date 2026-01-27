<?php

namespace common\components\bansystem\dto;

class RustAppBanData
{
    public ?RustAppBanDetails $vacBan = null;
    public ?RustAppBanDetails $gameBan = null;
    public ?bool $communityBan = null;
    public ?int $daysSinceLastBan = null;

    public static function fromArray(array $data): self
    {
        $banData = new self();
        if (isset($data['vac_ban']) && is_array($data['vac_ban'])) {
            $banData->vacBan = RustAppBanDetails::fromArray($data['vac_ban']);
        }
        if (isset($data['game_ban']) && is_array($data['game_ban'])) {
            $banData->gameBan = RustAppBanDetails::fromArray($data['game_ban']);
        }
        $banData->communityBan = isset($data['community_ban']) ? (bool)$data['community_ban'] : null;
        $banData->daysSinceLastBan = isset($data['days_since_last_ban']) ? (int)$data['days_since_last_ban'] : null;

        return $banData;
    }
}

