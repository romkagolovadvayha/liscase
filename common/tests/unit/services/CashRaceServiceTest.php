<?php

namespace common\tests\unit\services;

use common\components\tournament\CashRaceService;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\Tournament;
use common\models\user\User;
use common\models\servers\Servers;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\console\Application;
use yii\db\Transaction;

final class CashRaceServiceTest extends TestCase
{
    /** @var Transaction|null */
    private $transaction;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 4);
        require_once $root . '/vendor/autoload.php';
        require_once $root . '/vendor/yiisoft/yii2/Yii.php';
        if (Yii::$app !== null) return;
        require $root . '/common/config/bootstrap.php';
        require $root . '/console/config/bootstrap.php';
        new Application(require $root . '/console/config/console.php');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->transaction !== null && $this->transaction->isActive) $this->transaction->rollBack();
        parent::tearDown();
    }

    public function testBoundTokensDepositOnceAndLostTokensNeverScore(): void
    {
        $server = Servers::find()->one();
        $users = User::find()->where(['not', ['steam_id' => null]])->andWhere(['<>', 'steam_id', ''])->limit(2)->all();
        self::assertNotNull($server);
        self::assertCount(2, $users);

        $tournament = new Tournament([
            'type' => Tournament::TYPE_CASH_RACE,
            'slug' => 'cash-race-test-' . bin2hex(random_bytes(4)),
            'title' => 'Cash Race integration test',
            'server_id' => $server->id,
            'status' => Tournament::STATUS_PUBLISHED,
            'starts_at' => date('Y-m-d H:i:s', time() - 60),
            'ends_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        self::assertTrue($tournament->save(), json_encode($tournament->errors));
        $config = new CashRaceTournament(['tournament_id' => $tournament->id]);
        self::assertTrue($config->save(), json_encode($config->errors));

        $held = [self::uuid(), self::uuid()];
        self::assertCount(2, CashRaceService::mint($config, $server, $users[0], $held));
        $lost = self::uuid();
        CashRaceService::mint($config, $server, $users[0], [$lost]);
        self::assertSame(1, CashRaceService::markLost($config, $users[0], [$lost]));

        $terminal = CashRaceService::openTerminal($config, $server, [
            'session_uuid' => self::uuid(),
            'monument_key' => 'test_monument',
            'monument_name' => 'Test monument',
            'position' => '1,2,3',
        ]);
        $depositUuid = self::uuid();
        $first = CashRaceService::deposit($config, $server, $users[0], $depositUuid, $terminal->session_uuid, $held);
        self::assertSame(2, $first['count']);
        self::assertFalse($first['duplicate']);
        $retry = CashRaceService::deposit($config, $server, $users[0], $depositUuid, $terminal->session_uuid, $held);
        self::assertSame(2, $retry['count']);
        self::assertTrue($retry['duplicate']);

        $score = CashRaceService::score($tournament, $users[0]);
        self::assertSame(3, (int)$score->keys_found);
        self::assertSame(1, (int)$score->keys_lost);
        self::assertSame(2, (int)$score->keys_deposited);

        $foreign = self::uuid();
        CashRaceService::mint($config, $server, $users[0], [$foreign]);
        $this->expectException(\DomainException::class);
        CashRaceService::deposit($config, $server, $users[1], self::uuid(), $terminal->session_uuid, [$foreign]);
    }

    public function testUuidValidationRejectsReplayShapedGarbage(): void
    {
        self::assertTrue(CashRaceService::validUuid(self::uuid()));
        self::assertFalse(CashRaceService::validUuid('../same-token'));
        self::assertFalse(CashRaceService::validUuid('00000000-0000-0000-0000-000000000000'));
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
