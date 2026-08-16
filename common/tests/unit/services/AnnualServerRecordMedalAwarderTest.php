<?php

namespace common\tests\unit\services;

use common\models\medals\UserMedal;
use common\models\user\UserTop;
use common\services\medals\AnnualServerRecordMedalAwarder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\console\Application;
use yii\db\Transaction;

final class AnnualServerRecordMedalAwarderTest extends TestCase
{
    private const TEST_YEAR = 2021;

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

    public function testAwardsEachServerAndCategoryOnce(): void
    {
        $users = Yii::$app->db->createCommand(
            "SELECT MIN(id) AS id, steam_id FROM `user` "
            . "WHERE steam_id <> '' AND is_stats = 1 GROUP BY steam_id ORDER BY id LIMIT 3"
        )->queryAll();
        self::assertCount(3, $users, 'The integration test needs three visible users in the local test database.');

        $suffix = substr(md5(uniqid('', true)), 0, 8);
        // statistics.server_tag is a compact legacy column; keep test tags short.
        $serverA = 'ra' . substr($suffix, 0, 6);
        $serverB = 'rb' . substr($suffix, 0, 6);
        $wipe = '2021-07-01/2021-07-08';
        $createdAt = time();
        $rows = [];

        $add = static function (array $user, string $server, string $key, float $value) use (&$rows, $wipe, $createdAt): void {
            $rows[] = [(string)$user['steam_id'], $key, $value, $server, $wipe, $createdAt];
        };

        // Server A deliberately has different winners for different categories.
        $add($users[0], $serverA, 'playtime', 600);
        $add($users[0], $serverA, 'kills', 10);
        $add($users[0], $serverA, 'scientists', 1);
        $add($users[0], $serverA, 'sulfur.ore', 100);
        $add($users[0], $serverA, 'c4thrown', 5);
        $add($users[0], $serverA, 'gathered_black.berry', 20);
        $add($users[0], $serverA, 'bear', 3);
        $add($users[0], $serverA, 'f_fish.catfish', 1);

        $add($users[1], $serverA, 'playtime', 700);
        $add($users[1], $serverA, 'kills', 20);
        $add($users[1], $serverA, 'scientists', 9);
        $add($users[1], $serverA, 'sulfur.ore', 90);
        $add($users[1], $serverA, 'c4thrown', 10);
        $add($users[1], $serverA, 'gathered_black.berry', 30);
        $add($users[1], $serverA, 'bear', 5);
        $add($users[1], $serverA, 'f_fish.smallshark', 1);

        // Server B proves that medals are isolated by server. The kill score is
        // tied, so playtime deterministically selects user 0.
        $add($users[0], $serverB, 'playtime', 800);
        $add($users[0], $serverB, 'kills', 20);
        $add($users[0], $serverB, 'scientists', 2);
        $add($users[0], $serverB, 'wood', 500);
        $add($users[0], $serverB, 'satchelsthrown', 5);
        $add($users[0], $serverB, 'gathered_pumpkin', 5);
        $add($users[0], $serverB, 'deer', 2);
        $add($users[0], $serverB, 'f_fish.anchovy', 2);

        $add($users[1], $serverB, 'playtime', 700);
        $add($users[1], $serverB, 'kills', 20);

        Yii::$app->db->createCommand()->batchInsert(
            'statistics',
            ['steam_id', 'key', 'value', 'server_tag', 'wipe', 'created_at'],
            $rows
        )->execute();

        $awarder = new AnnualServerRecordMedalAwarder(Yii::$app->db);
        $first = $awarder->awardYear(self::TEST_YEAR);
        self::assertSame(2, $first['servers']);
        self::assertSame(16, $first['winners']);
        self::assertSame(16, $first['eligible']);
        self::assertSame(16, $first['awarded']);
        self::assertSame(0, $first['already_awarded']);

        $second = $awarder->awardYear(self::TEST_YEAR);
        self::assertSame(0, $second['awarded']);
        self::assertSame(16, $second['already_awarded']);

        $serverAKillsCode = AnnualServerRecordMedalAwarder::medalCode(
            self::TEST_YEAR,
            $serverA,
            UserTop::TYPE_KILLS
        );
        $serverBKillsCode = AnnualServerRecordMedalAwarder::medalCode(
            self::TEST_YEAR,
            $serverB,
            UserTop::TYPE_KILLS
        );
        $killAwards = Yii::$app->db->createCommand(
            'SELECT m.code, um.user_id, um.source_type, m.description '
            . 'FROM user_medal um INNER JOIN medal m ON m.id = um.medal_id '
            . 'WHERE m.code IN (:serverA, :serverB)',
            [':serverA' => $serverAKillsCode, ':serverB' => $serverBKillsCode]
        )->queryAll();
        $killAwards = array_column($killAwards, null, 'code');

        self::assertSame((string)$users[1]['id'], (string)$killAwards[$serverAKillsCode]['user_id']);
        self::assertSame((string)$users[0]['id'], (string)$killAwards[$serverBKillsCode]['user_id']);
        self::assertSame(UserMedal::SOURCE_ANNUAL_SERVER_RECORD, $killAwards[$serverAKillsCode]['source_type']);
        self::assertStringContainsString('2021', $killAwards[$serverAKillsCode]['description']);
        self::assertStringContainsString(strtoupper($serverA), $killAwards[$serverAKillsCode]['description']);
    }

    public function testCurrentYearRequiresExplicitPermission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AnnualServerRecordMedalAwarder(Yii::$app->db))->previewYear(2026);
    }
}
