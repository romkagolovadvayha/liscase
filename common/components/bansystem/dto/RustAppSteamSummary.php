<?php

namespace common\components\bansystem\dto;

class RustAppSteamSummary
{
    public ?int $avatarUpdateTime = null;
    public ?bool $profilePrivate = null;
    public ?int $profileHours = null;
    public ?int $profileUpdateTime = null;
    public ?int $profileNextUpdateTime = null;

    public static function fromArray(array $data): self
    {
        $summary = new self();
        $summary->avatarUpdateTime = isset($data['avatar_update_time']) ? (int)$data['avatar_update_time'] : null;
        $summary->profilePrivate = isset($data['profile_private']) ? (bool)$data['profile_private'] : null;
        $summary->profileHours = isset($data['profile_hours']) ? (int)$data['profile_hours'] : null;
        $summary->profileUpdateTime = isset($data['profile_update_time']) ? (int)$data['profile_update_time'] : null;
        $summary->profileNextUpdateTime = isset($data['profile_next_update_time']) ? (int)$data['profile_next_update_time'] : null;

        return $summary;
    }
}

