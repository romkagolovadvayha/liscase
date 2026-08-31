<?php

namespace common\tests\unit\models\invoice;

use common\models\invoice\Deposit;
use common\models\profit\Profit;
use common\models\user\User;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\caching\ArrayCache;
use yii\console\Application;
use yii\db\Transaction;

final class DepositProcessingTest extends TestCase
{
    /** @var Transaction|null */
    private $transaction;

    /** @var mixed */
    private $originalTelegramPayments;

    /** @var mixed */
    private $originalCache;

    /** @var object */
    private $telegramPayments;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 5);
        require_once $root . '/vendor/autoload.php';
        require_once $root . '/vendor/yiisoft/yii2/Yii.php';
        if (Yii::$app !== null) {
            return;
        }
        require $root . '/common/config/bootstrap.php';
        require $root . '/console/config/bootstrap.php';
        new Application(require $root . '/console/config/console.php');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->db->enableSchemaCache = false;
        $this->transaction = Yii::$app->db->beginTransaction();
        $this->originalCache = Yii::$app->get('cache', false);
        Yii::$app->set('cache', new ArrayCache());
        $this->originalTelegramPayments = Yii::$app->get('telegramPayments', false);
        $this->telegramPayments = new class {
            public $calls = 0;

            public function sendMessage($message): array
            {
                $this->calls++;
                return ['ok' => true];
            }
        };
        Yii::$app->set('telegramPayments', $this->telegramPayments);
    }

    protected function tearDown(): void
    {
        Yii::$app->set('telegramPayments', $this->originalTelegramPayments);
        Yii::$app->set('cache', $this->originalCache);
        if ($this->transaction !== null && $this->transaction->isActive) {
            $this->transaction->rollBack();
        }
        parent::tearDown();
    }

    public function testSuccessfulDepositAwardsAndNotifiesExactlyOnce(): void
    {
        $user = User::find()
            ->andWhere(['not', ['steam_id' => null]])
            ->andWhere(['<>', 'steam_id', ''])
            ->one();
        self::assertNotNull($user);

        $deposit = new Deposit();
        $deposit->user_id = $user->id;
        $deposit->payment_type = Deposit::TYPE_PAYMENT_CARD_TINKOFF;
        $deposit->amount = 2000;
        $deposit->payment_id = 'test-' . bin2hex(random_bytes(8));
        $deposit->status = Deposit::STATUS_WAIT_CONFIRM;
        $deposit->created_at = date('Y-m-d H:i:s');
        self::assertTrue($deposit->save(false));

        $firstWorker = Deposit::findOne($deposit->id);
        $secondWorker = Deposit::findOne($deposit->id);

        self::assertTrue($firstWorker->markSuccessful());
        self::assertFalse($secondWorker->markSuccessful());
        self::assertSame(1, $this->telegramPayments->calls);
        self::assertSame(Deposit::STATUS_SUCCESS, (int)$secondWorker->status);

        $bonuses = Profit::find()->andWhere([
            'deposit_id' => $deposit->id,
            'type' => Profit::TYPE_BONUS,
        ])->all();
        self::assertCount(1, $bonuses);
        self::assertSame(600, (int)$bonuses[0]->amount);
    }

    public function testLegacyDuplicateBonusesRemainUntouched(): void
    {
        $user = User::find()
            ->andWhere(['not', ['steam_id' => null]])
            ->andWhere(['<>', 'steam_id', ''])
            ->one();
        self::assertNotNull($user);

        $legacyBonusIds = [];
        foreach ([1, 2] as $copy) {
            $legacyBonus = new Profit();
            $legacyBonus->user_balance_id = $user->getPersonalBalance()->id;
            $legacyBonus->deposit_id = null;
            $legacyBonus->type = Profit::TYPE_BONUS;
            $legacyBonus->amount = 75;
            $legacyBonus->status = 1;
            $legacyBonus->comment = 'Бонус при пополнении';
            $legacyBonus->created_at = date('Y-m-d H:i:s');
            self::assertTrue($legacyBonus->save(false), 'Failed to create legacy copy ' . $copy);
            $legacyBonusIds[] = $legacyBonus->id;
        }

        $deposit = new Deposit();
        $deposit->user_id = $user->id;
        $deposit->payment_type = Deposit::TYPE_PAYMENT_CARD_TINKOFF;
        $deposit->amount = 500;
        $deposit->payment_id = 'test-legacy-' . bin2hex(random_bytes(8));
        $deposit->status = Deposit::STATUS_WAIT_CONFIRM;
        $deposit->created_at = date('Y-m-d H:i:s');
        self::assertTrue($deposit->save(false));
        self::assertTrue($deposit->markSuccessful());

        self::assertSame(2, (int)Profit::find()->andWhere(['id' => $legacyBonusIds])->count());
        self::assertSame(1, (int)Profit::find()->andWhere([
            'deposit_id' => $deposit->id,
            'type' => Profit::TYPE_BONUS,
        ])->count());
    }
}
