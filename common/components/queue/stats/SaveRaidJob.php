<?php

namespace common\components\queue\stats;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanPluginCupboard;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveRaidJob extends BaseObject implements JobInterface
{
    public $data;
    public $serverTag;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $request = json_decode($this->data, 1);
            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['tag' => $this->serverTag])
                             ->one();
            if (empty($server)) {
                return;
            }
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            if (!empty($request['raids'])) {
                foreach ($request['raids'] as $item) {
                    try {
                        $steamId = $item['steam_id'];
                        if (strlen($steamId) < 16) {
                            continue;
                        }
                        $user = User::findBySteamId($steamId, false, 'raid');
                        $location = $item['entityLocation'];
                        $explosives = $item['explosiveUsed'];
                        $owners = $item['owners'];
                        $createdAt = $item['created_at'];

                        $model = new UserRaid();
                        $model->user_id = $user->id;
                        if ($model->hasAttribute('raider_clan_id')) {
                            $raiderCid = $this->resolveActiveClanIdForUserOnServer((int)$user->id, (int)$server->id);
                            if ($raiderCid !== null) {
                                $model->raider_clan_id = $raiderCid;
                            }
                        }
                        $model->location = $location;
                        $model->explosives = json_encode($explosives);
                        $model->owners = json_encode($owners);
                        $model->created_at = $createdAt;
                        $model->server_id = $server->id;
                        $model->wipe = $wipeDate;
                        if (!empty($item['type'])) {
                            $model->type = $item['type'];
                        }
                        if (isset($item['entity_id']) && $item['entity_id'] !== '') {
                            $eid = (string)$item['entity_id'];
                            if (strlen($eid) <= 64) {
                                $model->entity_id = $eid;
                            }
                        }

                        $ownersList = is_array($owners) ? $owners : [];
                        $cupPluginRow = $this->enrichCupboardRaidFromPlugin($model, $server, $wipeDate, $ownersList);
                        $this->assignRealRaidCupboard($model, $server, $ownersList, $cupPluginRow);

                        if (!empty($model->getErrors())) {
                            Yii::$app->telegramChats->sendMessage("SaveRaidJob save UserRaid: " . json_encode($model->getErrors()));
                        }

                        if (!empty($owners)) {
                            $date = new \DateTime();
                            $endDate = $date->format('Y-m-d H:i:s');
                            $date->modify('-1 hour');
                            $startDate = $date->format('Y-m-d H:i:s');

                            $message = "⚠️ <b>Внимание!</b> Ваша постройка в квадрате {$location} атакована!" . PHP_EOL . PHP_EOL;
                            $message .= "Сервер: {$server->name}";
                            if (!empty($explosives)) {
                                $keys = [];
                                foreach ($explosives as $key) {
                                    if ($key === 'explosive.satchel.deployed') {
                                        $key = 'satchelsthrown';
                                    }
                                    if ($key === 'explosive.timed.deployed') {
                                        $key = 'c4thrown';
                                    }
                                    $keys[] = str_replace('.deployed', '', $key);
                                }
                                $drops = \common\models\box\Drop::find()
                                                                ->cache(60*60)
                                                                ->andWhere(['IN', 'eng_name', $keys])
                                                                ->indexBy('eng_name')
                                                                ->all();
                                $names = [];
                                foreach ($drops as $drop) {
                                    $names[] = $drop->name;
                                }

                                if (!empty($names)) {
                                    $message .= PHP_EOL . "Для нанесения урона было использовано: " . implode(',', $names);
                                }
                            }
                            if (!empty($model->type) && !empty(UserRaid::getTypeName($model->type))) {
                                $message .= PHP_EOL . "Уничтоженно: " . UserRaid::getTypeName($model->type);
                            }
                            $model->notify = 1;
                            foreach ($owners as $owner) {
                                $exists = UserRaid::find()
                                                  ->andWhere(['LIKE', 'owners', '%' . $owner . '%', false])
                                                  ->andWhere(['notify' => 1])
                                                  ->andWhere(['location' => $location])
                                                  ->andWhere(['>=', 'created_at', $startDate])
                                                  ->andWhere(['<=', 'created_at', $endDate])
                                                  ->exists();
                                if ($exists) {
                                    continue;
                                }
                                /** @var User $userOwner */
                                $userOwner = User::find()
                                             ->andWhere(['steam_id' => $owner])
                                             ->andWhere(['raid_notify' => 1])
                                             ->one();
                                if (!empty($userOwner)) {
                                    if (!empty($userOwner->telegram_chat_id)) {
                                        Yii::$app->personalBotTelegram->sendMessage($userOwner->telegram_chat_id, $message);
                                    }
                                    if (!empty($userOwner->vk_id)) {
                                        $userOwner->sendPersonalVkBotMessage(strip_tags($message));
                                    }
                                }
                            }
                        }
                        $model->save(false);
                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage($this->data);
                        Yii::$app->telegramChats->sendMessage("SaveRaidJob foreach: " . $e->getLine() . ":" . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage($this->data);
            Yii::$app->telegramChats->sendMessage("SaveRaidJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }

    /**
     * Для рейда шкафа: clan_id жертв и снимок блоков/score/main_cupboard из clan_plugin_cupboards по entity_id.
     *
     * @return ClanPluginCupboard|null строка плагина, если сущность найдена в снимке на этот вайп
     */
    private function enrichCupboardRaidFromPlugin(UserRaid $model, Servers $server, string $wipeDate, array $ownersSteamIds): ?ClanPluginCupboard
    {
        $type = (string)($model->type ?? '');
        if ($type === '' || stripos($type, 'cupboard') === false) {
            return null;
        }

        $cup = null;
        $eid = trim((string)($model->entity_id ?? ''));
        if ($eid !== '') {
            $cup = ClanPluginCupboard::find()
                ->where([
                    'entity_id' => $eid,
                    'server_id' => (int)$server->id,
                    'wipe' => $wipeDate,
                ])
                ->one();
            if ($cup !== null) {
                if ($model->hasAttribute('blocks_wood')) {
                    $model->blocks_wood = (int)$cup->blocks_wood;
                    $model->blocks_stone = (int)$cup->blocks_stone;
                    $model->blocks_metal = (int)$cup->blocks_metal;
                    $model->blocks_hqm = (int)$cup->blocks_hqm;
                    $model->score = (int)$cup->score;
                    $model->main_cupboard = (int)$cup->main_cupboard;
                }
                if ($model->hasAttribute('clan_id') && $cup->clan_id !== null && (int)$cup->clan_id > 0) {
                    $model->clan_id = (int)$cup->clan_id;
                }
            }
        }

        if ($model->hasAttribute('clan_id')) {
            $cid = $model->clan_id;
            if ($cid === null || (int)$cid <= 0) {
                $resolved = $this->resolveVictimClanIdFromOwners($ownersSteamIds, (int)$server->id);
                if ($resolved !== null) {
                    $model->clan_id = $resolved;
                }
            }
        }

        return $cup;
    }

    /**
     * real_raid=1 только для шкафа: есть снимок плагина по entity_id, чужая база, не альт в клане атакующих, был расход взрывчатки.
     */
    private function assignRealRaidCupboard(
        UserRaid $model,
        Servers $server,
        array $ownersSteamIds,
        ?ClanPluginCupboard $pluginRow
    ): void {
        if (!$model->hasAttribute('real_raid')) {
            return;
        }
        $type = (string)($model->type ?? '');
        if (stripos($type, 'cupboard') === false) {
            $model->real_raid = 0;

            return;
        }

        $model->real_raid = 0;

        if ($pluginRow === null) {
            return;
        }

        $victimClanId = (int)($model->clan_id ?? 0);
        if ($victimClanId <= 0) {
            return;
        }

        $victimClan = Clan::find()->select(['id', 'server_id'])->where(['id' => $victimClanId])->one();
        if ($victimClan === null || (int)$victimClan->server_id !== (int)$server->id) {
            return;
        }

        $raiderClanId = isset($model->raider_clan_id) && (int)$model->raider_clan_id > 0
            ? (int)$model->raider_clan_id
            : null;

        if ($raiderClanId !== null && $raiderClanId === $victimClanId) {
            return;
        }

        if ($this->isUserActiveMemberOfClan((int)$model->user_id, $victimClanId)) {
            return;
        }

        $raider = User::find()->select(['id', 'steam_id'])->where(['id' => (int)$model->user_id])->one();
        $raiderSteam = $raider && !empty($raider->steam_id) ? $this->normalizeSteamDigits((string)$raider->steam_id) : null;

        if ($pluginRow->placer_steam_id !== null && $pluginRow->placer_steam_id !== '') {
            $placer = $this->normalizeSteamDigits((string)$pluginRow->placer_steam_id);
            if ($placer !== null && $raiderSteam !== null && $placer === $raiderSteam) {
                return;
            }
        }

        foreach ($ownersSteamIds as $raw) {
            $oSteam = $this->normalizeSteamDigits((string)$raw);
            if ($oSteam !== null && $raiderSteam !== null && $oSteam === $raiderSteam) {
                return;
            }
            $ownerUid = $this->resolveUserIdBySteam($raw);
            if ($ownerUid === null) {
                continue;
            }
            if ($ownerUid === (int)$model->user_id) {
                return;
            }
            if ($raiderClanId !== null) {
                $ownersRaidingClan = $this->resolveActiveClanIdForUserOnServer($ownerUid, (int)$server->id);
                if ($ownersRaidingClan !== null && $ownersRaidingClan === $raiderClanId) {
                    return;
                }
            }
        }

        if (!$this->hasMeaningfulExplosives($model->explosives)) {
            return;
        }

        $model->real_raid = 1;
    }

    private function hasMeaningfulExplosives(?string $json): bool
    {
        if ($json === null || $json === '') {
            return false;
        }
        $arr = json_decode($json, true);
        if (!is_array($arr) || $arr === []) {
            return false;
        }
        foreach ($arr as $v) {
            if (is_numeric($v) && (float)$v > 0) {
                return true;
            }
            if (is_string($v) && trim($v) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeSteamDigits(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        return strlen($digits) >= 17 ? $digits : null;
    }

    private function resolveUserIdBySteam($raw): ?int
    {
        $steam = preg_replace('/\D/', '', (string)$raw);
        if (strlen($steam) < 17) {
            return null;
        }
        $u = User::find()->select(['id'])->where(['steam_id' => $steam])->one();
        if ($u === null) {
            $u = User::find()->select(['id'])->where(['steam_id' => (string)$raw])->one();
        }

        return $u !== null ? (int)$u->id : null;
    }

    private function isUserActiveMemberOfClan(int $userId, int $clanId): bool
    {
        return ClanMember::find()
            ->where(['user_id' => $userId, 'clan_id' => $clanId])
            ->andWhere(['IS', 'leave_date', null])
            ->exists();
    }

    private function resolveVictimClanIdFromOwners(array $ownersSteamIds, int $serverId): ?int
    {
        foreach ($ownersSteamIds as $raw) {
            $uid = $this->resolveUserIdBySteam($raw);
            if ($uid === null) {
                continue;
            }
            $cid = $this->resolveActiveClanIdForUserOnServer($uid, $serverId);
            if ($cid !== null) {
                return $cid;
            }
        }

        return null;
    }

    /**
     * Активное членство пользователя в клане на указанном сервере (первый по cm.id).
     */
    private function resolveActiveClanIdForUserOnServer(int $userId, int $serverId): ?int
    {
        $clanId = ClanMember::find()
            ->alias('cm')
            ->select(['cm.clan_id'])
            ->innerJoin(['c' => Clan::tableName()], 'c.id = cm.clan_id')
            ->where(['cm.user_id' => $userId])
            ->andWhere(['IS', 'cm.leave_date', null])
            ->andWhere(['c.server_id' => $serverId])
            ->orderBy(['cm.id' => SORT_ASC])
            ->scalar();
        if ($clanId !== null && (int)$clanId > 0) {
            return (int)$clanId;
        }
        return null;
    }
}
