<?php

namespace common\components\bansystem\dto;

class RustAppTeamMember
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
    public ?string $lastLanguage = null;
    public ?RustAppSteamSummary $steam = null;
    public ?RustAppSteamData $steamData = null;

    public static function fromArray(array $data): self
    {
        $member = new self();
        $member->steamId = $data['steam_id'] ?? null;
        $member->steamName = $data['steam_name'] ?? null;
        $member->steamAvatar = $data['steam_avatar'] ?? null;
        $member->projectId = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $member->serverId = isset($data['server_id']) ? (int)$data['server_id'] : null;
        $member->ip = $data['ip'] ?? null;
        if (isset($data['ip_details']) && is_array($data['ip_details'])) {
            $member->ipDetails = RustAppPlayerIpDetails::fromArray($data['ip_details']);
        }
        if (!empty($data['team']) && is_array($data['team'])) {
            $member->team = array_values(array_filter($data['team'], 'is_string'));
        }
        if (isset($data['player_team']) && is_array($data['player_team'])) {
            $member->playerTeam = RustAppPlayerTeam::fromArray($data['player_team']);
        }
        $member->status = $data['status'] ?? null;
        $member->createdAt = isset($data['created_at']) ? (int)$data['created_at'] : null;
        $member->lastOnlineAt = isset($data['last_online_at']) ? (int)$data['last_online_at'] : null;
        $member->lastNoLicense = isset($data['last_no_license']) ? (bool)$data['last_no_license'] : null;
        $member->lastLanguage = $data['last_language'] ?? null;
        if (isset($data['steam']) && is_array($data['steam'])) {
            $member->steam = RustAppSteamSummary::fromArray($data['steam']);
        }
        if (isset($data['steam_data']) && is_array($data['steam_data'])) {
            $member->steamData = RustAppSteamData::fromArray($data['steam_data']);
        }

        return $member;
    }
}

