<?php
namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class SupervisortaskController extends Controller
{
    /**
     * Синхронизировать конфиги supervisor с проектного файла (по умолчанию console/supervisor.php)
     * Пример: ./yii supervisortask/sync
     *         ./yii supervisortask/sync @app/console/supervisor.php 0
     */
    public function actionSync(string $config = '@app/config/supervisor.php', int $apply = 1): int
    {
        $file = Yii::getAlias($config);
        if (!is_file($file)) {
            $this->stderr("Config not found: {$file}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        /** @var \console\components\Supervisortask $svc */
        $svc = Yii::$app->get('supervisortask');
        try {
            $svc->loadFromFile($file);
            [$ok, $data] = $svc->sync((bool)$apply);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage()."\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$ok) {
            $this->stderr(($data ?? 'error')."\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Written:\n");
        foreach ($data as $f) $this->stdout(" - {$f}\n");
        if ($apply) $this->stdout("Applied: supervisorctl reread/update\n");

        return ExitCode::OK;
    }
}
