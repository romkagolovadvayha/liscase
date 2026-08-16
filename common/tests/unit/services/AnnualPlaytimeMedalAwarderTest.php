<?php

namespace common\tests\unit\services;

use common\models\medals\UserMedal;
use common\services\medals\AnnualPlaytimeMedalAwarder;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\console\Application;
use yii\db\Transaction;

final class AnnualPlaytimeMedalAwarderTest extends TestCase
{
    private const TEST_YEAR = 2099;

    /** @var Transaction|null */
    private $transaction;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 4);
        require_once $root . '/vendor/autoload.php';
        require_once $root . '/vendor/yiisoft/yii2/Yii.php';
        if (Yii::$app !== null) {
            return;
        }

        require $root . '/common/config/bootstrap.php';
        require $root . '/console/config/bootstrap.php';
        $config = require $root . '/console/config/console.php';
        new Application($config);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->transaction !== null && $this->transaction->isActive) {
            $this->transaction->rollBack();
        }
        parent::tearDown();
    }

    public function testStrictThresholdAndIdempotentRerun(): void
    {
        $users = Yii::$app->db->createCommand(
            "SELECT MIN(id) AS id, steam_id FROM `user` "
            . "WHERE steam_id <> '' GROUP BY steam_id ORDER BY id LIMIT 2"
        )->queryAll();
        self::assertCount(2, $users, 'The integration test needs two users in the local test database.');

        $suffix = substr(md5(uniqid('', true)), 0, 6);
        $now = date('Y-m-d H:i:s');
        Yii::$app->db->createCommand()->insert('medal', [
            'code' => 'test_annual_playtime_' . $suffix,
            'name' => 'Test annual playtime medal',
            'description' => 'Canonical annual medal description',
            'image_path' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->execute();
        $medalId = (int)Yii::$app->db->getLastInsertID();

        Yii::$app->db->createCommand()->batchInsert(
            'statistics',
            ['steam_id', 'key', 'value', 'server_tag', 'wipe', 'created_at'],
            [
                [(string)$users[0]['steam_id'], 'playtime', 300, 'test-' . $suffix, '2099-01-01/2099-01-08', time()],
                [(string)$users[0]['steam_id'], 'playtime', 201, 'test-' . $suffix, '2099-01-08/2099-01-15', time()],
                [(string)$users[1]['steam_id'], 'playtime', 500, 'test-' . $suffix, '2099-01-01/2099-01-08', time()],
            ]
        )->execute();

        $awarder = new AnnualPlaytimeMedalAwarder(Yii::$app->db);
        self::assertSame(1, $awarder->countAwardableForYear(self::TEST_YEAR, $medalId));
        self::assertSame(1, $awarder->awardYear(self::TEST_YEAR, $medalId));
        self::assertSame(0, $awarder->awardYear(self::TEST_YEAR, $medalId));

        $awardedUserIds = Yii::$app->db->createCommand(
            'SELECT user_id FROM user_medal WHERE medal_id = :medalId',
            [':medalId' => $medalId]
        )->queryColumn();
        self::assertSame([(string)$users[0]['id']], array_map('strval', $awardedUserIds));

        $source = Yii::$app->db->createCommand(
            'SELECT source_type FROM user_medal WHERE medal_id = :medalId',
            [':medalId' => $medalId]
        )->queryScalar();
        self::assertSame(UserMedal::SOURCE_ANNUAL_PLAYTIME, $source);

        $payload = UserMedal::getUserAwardsPayload((int)$users[0]['id']);
        $payloadByMedalId = array_column($payload, null, 'id');
        self::assertArrayHasKey($medalId, $payloadByMedalId);
        self::assertSame('Canonical annual medal description', $payloadByMedalId[$medalId]['description']);
        self::assertSame(
            'Суммарно более 500 минут игры в 2099 году',
            $payloadByMedalId[$medalId]['note']
        );
    }
}
