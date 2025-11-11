<?php

namespace common\components\bansystem\dto;

class RustAppPlayerResponse
{
    public ?RustAppPlayer $player = null;
    /** @var RustAppTeamMember[] */
    public array $team = [];

    public static function fromArray(array $data): self
    {
        $response = new self();
        if (isset($data['player']) && is_array($data['player'])) {
            $response->player = RustAppPlayer::fromArray($data['player']);
        }
        if (!empty($data['team']) && is_array($data['team'])) {
            foreach ($data['team'] as $member) {
                if (is_array($member)) {
                    $response->team[] = RustAppTeamMember::fromArray($member);
                }
            }
        }

        return $response;
    }
}

