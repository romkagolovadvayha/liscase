<?php

namespace common\components\rustadmin\dto;

class RustAdminPlayerResponse
{
    /** @var RustAdminPlayer|null */
    public $player;
    /** @var RustAdminTeamMember[] */
    public array $team = [];

    public static function fromArray(array $data): self
    {
        $response = new self();
        if (!empty($data['player']) && is_array($data['player'])) {
            $response->player = RustAdminPlayer::fromPlayerArray($data['player']);
        }
        if (!empty($data['team']) && is_array($data['team'])) {
            foreach ($data['team'] as $member) {
                if (is_array($member)) {
                    $response->team[] = RustAdminTeamMember::fromArray($member);
                }
            }
        }

        return $response;
    }
}

class RustAdminPlayer extends RustAdminTeamMember
{
    /** @var RustAdminSteamSummary|null */
    public $steam;
    /** @var RustAdminSteamData|null */
    public $steamData;

    public static function fromPlayerArray(array $data): self
    {
        /** @var self $player */
        $player = parent::fromArray($data, new self());
        if (!empty($data['steam']) && is_array($data['steam'])) {
            $player->steam = RustAdminSteamSummary::fromArray($data['steam']);
        }
        if (!empty($data['steam_data']) && is_array($data['steam_data'])) {
            $player->steamData = RustAdminSteamData::fromArray($data['steam_data']);
        }

        return $player;
    }
}

class RustAdminTeamMember
{
    public ?string $steamId = null;
    public ?string $steamName = null;
    public ?string $steamAvatar = null;
    public ?string $serverId = null;
    public ?string $serverName = null;
    public ?string $ip = null;
    /** @var RustAdminIpDetails|null */
    public $ipDetails;
    public ?string $status = null;
    public ?int $createdAt = null;
    public ?int $lastOnlineAt = null;
    public ?string $lastLanguage = null;

    public static function fromArray(array $data, ?self $member = null): self
    {
        $member = $member ?: new self();
        $member->steamId = isset($data['steam_id']) ? (string)$data['steam_id'] : null;
        $member->steamName = isset($data['steam_name']) ? (string)$data['steam_name'] : null;
        $member->steamAvatar = isset($data['steam_avatar']) ? (string)$data['steam_avatar'] : null;
        $member->serverId = isset($data['server_id']) ? (string)$data['server_id'] : null;
        $member->serverName = isset($data['server_name']) ? (string)$data['server_name'] : null;
        $member->ip = isset($data['ip']) ? (string)$data['ip'] : null;
        if (!empty($data['ip_details']) && is_array($data['ip_details'])) {
            $member->ipDetails = RustAdminIpDetails::fromArray($data['ip_details']);
        }
        $member->status = isset($data['status']) ? (string)$data['status'] : null;
        $member->createdAt = isset($data['created_at']) ? (int)$data['created_at'] : null;
        $member->lastOnlineAt = isset($data['last_online_at']) ? (int)$data['last_online_at'] : null;
        $member->lastLanguage = isset($data['last_language']) ? (string)$data['last_language'] : null;

        return $member;
    }
}

class RustAdminIpDetails
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

class RustAdminSteamSummary
{
    public ?bool $profilePrivate = null;
    /** @var float|null */
    public $profileHours;
    public ?int $profileUpdateTime = null;

    public static function fromArray(array $data): self
    {
        $summary = new self();
        $summary->profilePrivate = isset($data['profile_private'])
            ? (bool)$data['profile_private']
            : null;
        $summary->profileHours = isset($data['profile_hours'])
            ? (float)$data['profile_hours']
            : null;
        $summary->profileUpdateTime = isset($data['profile_update_time'])
            ? (int)$data['profile_update_time']
            : null;

        return $summary;
    }
}

class RustAdminSteamData
{
    public ?string $avatarFullUrl = null;
    public ?bool $visible = null;
    /** @var RustAdminBanData|null */
    public $banData;
    /** @var float|null */
    public $rustHoursTotal;
    /** @var float|null */
    public $rustHours2Week;
    public ?int $updatedAt = null;

    public static function fromArray(array $data): self
    {
        $steam = new self();
        $steam->avatarFullUrl = $data['avatar_full_url'] ?? null;
        $steam->visible = isset($data['visible']) ? (bool)$data['visible'] : null;
        if (!empty($data['ban_data']) && is_array($data['ban_data'])) {
            $steam->banData = RustAdminBanData::fromArray($data['ban_data']);
        }
        $steam->rustHoursTotal = isset($data['rust_hours_total'])
            ? (float)$data['rust_hours_total']
            : null;
        $steam->rustHours2Week = isset($data['rust_hours_2week'])
            ? (float)$data['rust_hours_2week']
            : null;
        $steam->updatedAt = isset($data['updated_at']) ? (int)$data['updated_at'] : null;

        return $steam;
    }
}

class RustAdminBanData
{
    /** @var RustAdminBanDetails|null */
    public $vacBan;
    /** @var RustAdminBanDetails|null */
    public $gameBan;

    public static function fromArray(array $data): self
    {
        $banData = new self();
        if (!empty($data['vac_ban']) && is_array($data['vac_ban'])) {
            $banData->vacBan = RustAdminBanDetails::fromArray($data['vac_ban']);
        }
        if (!empty($data['game_ban']) && is_array($data['game_ban'])) {
            $banData->gameBan = RustAdminBanDetails::fromArray($data['game_ban']);
        }

        return $banData;
    }
}

class RustAdminBanDetails
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
