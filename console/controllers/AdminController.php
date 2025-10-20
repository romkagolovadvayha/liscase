<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use common\models\user\User;

/**
 * Консольная команда для управления администраторами
 * 
 * Использование:
 * php yii admin/create                    - создать админа из ENV переменной ADMIN_STEAM_ID
 * php yii admin/create 76561198012345678  - создать админа по конкретному Steam ID
 * php yii admin/grant 1                   - выдать права админа пользователю с ID=1
 */
class AdminController extends Controller
{
    /**
     * @var string Steam ID для создания администратора
     */
    public $steamId;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['steamId']);
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            's' => 'steamId',
        ]);
    }

    /**
     * Создает пользователя-администратора из Steam ID
     * 
     * @param string|null $steamId Steam ID (опционально, если не задан - берется из ENV)
     * @return int Exit code
     */
    public function actionCreate($steamId = null)
    {
        // Приоритет: аргумент > опция > ENV
        $sid = $steamId ?? $this->steamId ?? getenv('ADMIN_STEAM_ID');

        if (empty($sid)) {
            $this->stderr("❌ Steam ID не указан!\n", \yii\helpers\Console::FG_RED);
            $this->stdout("\nИспользование:\n", \yii\helpers\Console::FG_YELLOW);
            $this->stdout("   php yii admin/create 76561198012345678\n");
            $this->stdout("   php yii admin/create --steamId=76561198012345678\n");
            $this->stdout("   export ADMIN_STEAM_ID=76561198012345678 && php yii admin/create\n\n");
            return ExitCode::DATAERR;
        }

        $this->stdout("👤 Создание администратора из Steam ID: {$sid}\n\n", \yii\helpers\Console::FG_CYAN);

        try {
            // Создаем или находим пользователя
            $this->stdout("🔍 Поиск/создание пользователя...\n", \yii\helpers\Console::FG_YELLOW);
            $user = User::findBySteamId($sid, true, "console", null);

            if (!$user) {
                $this->stderr("❌ Не удалось создать пользователя\n", \yii\helpers\Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout("   ✅ Пользователь найден/создан: [{$user->id}] {$user->username}\n", \yii\helpers\Console::FG_GREEN);

            // Назначаем роль администратора
            $this->stdout("\n🔐 Назначение роли администратора...\n", \yii\helpers\Console::FG_YELLOW);
            
            $auth = Yii::$app->authManager;
            
            // Проверяем, есть ли уже роль
            $hasRole = $auth->getAssignments($user->id);
            if (isset($hasRole['admin'])) {
                $this->stdout("   ℹ️  Пользователь уже имеет роль администратора\n", \yii\helpers\Console::FG_GREY);
            } else {
                // Получаем роль admin
                $adminRole = $auth->getRole('admin');
                
                if (!$adminRole) {
                    $this->stderr("   ❌ Роль 'admin' не найдена в системе RBAC\n", \yii\helpers\Console::FG_RED);
                    $this->stdout("   💡 Создайте роль: php yii rbac/init\n", \yii\helpers\Console::FG_YELLOW);
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                // Назначаем роль
                $auth->assign($adminRole, $user->id);
                $this->stdout("   ✅ Роль 'admin' назначена\n", \yii\helpers\Console::FG_GREEN);
            }

            $this->stdout("\n" . str_repeat("=", 60) . "\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout("✅ Администратор успешно создан!\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout(str_repeat("=", 60) . "\n\n", \yii\helpers\Console::FG_CYAN);
            
            $this->stdout("Информация о пользователе:\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout("   ID: {$user->id}\n");
            $this->stdout("   Username: {$user->username}\n");
            $this->stdout("   Email: {$user->email}\n");
            $this->stdout("   Steam ID: {$sid}\n");
            $this->stdout("   Роль: admin\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("\n");

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            $this->stderr("   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n");
            if (YII_DEBUG) {
                $this->stderr("\nTrace:\n" . $e->getTraceAsString() . "\n");
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Назначает права администратора существующему пользователю
     * 
     * @param int $userId ID пользователя
     * @return int Exit code
     */
    public function actionGrant($userId)
    {
        if (empty($userId) || !is_numeric($userId)) {
            $this->stderr("❌ Некорректный ID пользователя\n", \yii\helpers\Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("🔐 Назначение прав администратора пользователю ID={$userId}...\n\n", \yii\helpers\Console::FG_CYAN);

        try {
            $user = User::findOne($userId);
            
            if (!$user) {
                $this->stderr("❌ Пользователь с ID={$userId} не найден\n", \yii\helpers\Console::FG_RED);
                return ExitCode::DATAERR;
            }

            $this->stdout("👤 Пользователь: [{$user->id}] {$user->username}\n");

            $auth = Yii::$app->authManager;
            $adminRole = $auth->getRole('admin');
            
            if (!$adminRole) {
                $this->stderr("❌ Роль 'admin' не найдена в системе RBAC\n", \yii\helpers\Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            // Проверяем, есть ли уже роль
            $hasRole = $auth->getAssignments($user->id);
            if (isset($hasRole['admin'])) {
                $this->stdout("ℹ️  Пользователь уже имеет роль администратора\n", \yii\helpers\Console::FG_YELLOW);
                return ExitCode::OK;
            }

            $auth->assign($adminRole, $user->id);
            $this->stdout("✅ Роль 'admin' успешно назначена!\n", \yii\helpers\Console::FG_GREEN);

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Отзывает права администратора
     * 
     * @param int $userId ID пользователя
     * @return int Exit code
     */
    public function actionRevoke($userId)
    {
        if (empty($userId) || !is_numeric($userId)) {
            $this->stderr("❌ Некорректный ID пользователя\n", \yii\helpers\Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("🔓 Отзыв прав администратора у пользователя ID={$userId}...\n\n", \yii\helpers\Console::FG_CYAN);

        try {
            $user = User::findOne($userId);
            
            if (!$user) {
                $this->stderr("❌ Пользователь с ID={$userId} не найден\n", \yii\helpers\Console::FG_RED);
                return ExitCode::DATAERR;
            }

            $auth = Yii::$app->authManager;
            $adminRole = $auth->getRole('admin');
            
            if (!$adminRole) {
                $this->stderr("❌ Роль 'admin' не найдена\n", \yii\helpers\Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $revoked = $auth->revoke($adminRole, $user->id);
            
            if ($revoked) {
                $this->stdout("✅ Роль 'admin' успешно отозвана!\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("ℹ️  У пользователя не было роли 'admin'\n", \yii\helpers\Console::FG_YELLOW);
            }

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Показывает список всех администраторов
     * 
     * @return int Exit code
     */
    public function actionList()
    {
        $this->stdout("👥 Список администраторов:\n\n", \yii\helpers\Console::FG_CYAN);

        try {
            $auth = Yii::$app->authManager;
            $adminRole = $auth->getRole('admin');
            
            if (!$adminRole) {
                $this->stderr("❌ Роль 'admin' не найдена\n", \yii\helpers\Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $assignments = $auth->getUserIdsByRole('admin');
            
            if (empty($assignments)) {
                $this->stdout("   Нет пользователей с правами администратора\n", \yii\helpers\Console::FG_YELLOW);
                return ExitCode::OK;
            }

            $this->stdout(str_repeat("=", 80) . "\n", \yii\helpers\Console::FG_GREY);
            
            foreach ($assignments as $userId) {
                $user = User::findOne($userId);
                if ($user) {
                    $this->stdout(sprintf(
                        "   [ID: %5d] %-30s | Email: %-30s\n",
                        $user->id,
                        $user->username,
                        $user->email ?: 'не указан'
                    ), \yii\helpers\Console::FG_GREEN);
                }
            }
            
            $this->stdout(str_repeat("=", 80) . "\n", \yii\helpers\Console::FG_GREY);
            $this->stdout("\nВсего администраторов: " . count($assignments) . "\n", \yii\helpers\Console::FG_CYAN);

            return ExitCode::OK;

        } catch (\Throwable $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}

