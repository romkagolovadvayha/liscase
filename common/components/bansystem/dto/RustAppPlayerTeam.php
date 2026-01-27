<?php

namespace common\components\bansystem\dto;

class RustAppPlayerTeam
{
    public ?int $serverId = null;
    /** @var string[] */
    public array $teammates = [];
    public ?int $createdAt = null;

    public static function fromArray(array $data): self
    {
        $team = new self();
        $team->serverId = isset($data['server_id']) ? (int)$data['server_id'] : null;
        if (!empty($data['teammates']) && is_array($data['teammates'])) {
            $team->teammates = array_values(array_filter($data['teammates'], 'is_string'));
        }
        $team->createdAt = isset($data['created_at']) ? (int)$data['created_at'] : null;

        return $team;
    }
}

