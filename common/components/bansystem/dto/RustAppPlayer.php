<?php

namespace common\components\bansystem\dto;

class RustAppPlayer
{
    public ?string $steamId = null;
    public ?string $steamName = null;
    public ?string $steamAvatar = null;
    public ?int $projectId = null;
    public ?int $serverId = null;
    public ?string $ip = null;
    public ?RustAppPlayerIpDetails $ipDetails = null;
    /** @var string[] */
    public array $team = [];
    public ?RustAppPlayerTeam $playerTeam = null;
    public ?string $status = null;
    public ?int $createdAt = null;
    public ?int $lastOnlineAt = null;
    public ?bool $lastNoLicense = null;
    public ?int $lastCheckId = null;
    public ?int $lastCheckTime = null;
    public ?string $lastLanguage = null;
    public ?RustAppSteamSummary $steam = null;
    public ?RustAppSteamData $steamData = null;

    public static function fromArray(array $data): self
    {
        $player = new self();
        $player->steamId = $data['steam_id'] ?? null;
        $player->steamName = $data['steam_name'] ?? null;
        $player->steamAvatar = $data['steam_avatar'] ?? null;
        $player->projectId = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $player->serverId = isset($data['server_id']) ? (int)$data['server_id'] : null;
        $player->ip = $data['ip'] ?? null;
        if (isset($data['ip_details']) && is_array($data['ip_details'])) {
            $player->ipDetails = RustAppPlayerIpDetails::fromArray($data['ip_details']);
        }
        if (!empty($data['team']) && is_array($data['team'])) {
            $player->team = array_values(array_filter($data['team'], 'is_string'));
        }
        if (isset($data['player_team']) && is_array($data['player_team'])) {
            $player->playerTeam = RustAppPlayerTeam::fromArray($data['player_team']);
        }
        $player->status = $data['status'] ?? null;
        $player->createdAt = isset($data['created_at']) ? (int)$data['created_at'] : null;
        $player->lastOnlineAt = isset($data['last_online_at']) ? (int)$data['last_online_at'] : null;
        $player->lastNoLicense = isset($data['last_no_license']) ? (bool)$data['last_no_license'] : null;
        $player->lastCheckId = isset($data['last_check_id']) ? (int)$data['last_check_id'] : null;
        $player->lastCheckTime = isset($data['last_check_time']) ? (int)$data['last_check_time'] : null;
        $player->lastLanguage = $data['last_language'] ?? null;
        if (isset($data['steam']) && is_array($data['steam'])) {
            $player->steam = RustAppSteamSummary::fromArray($data['steam']);
        }
        if (isset($data['steam_data']) && is_array($data['steam_data'])) {
            $player->steamData = RustAppSteamData::fromArray($data['steam_data']);
        }

        return $player;
    }
}

