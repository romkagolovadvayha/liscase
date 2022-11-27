<?php

namespace console\components\migration;

use yii\db\Query;
use yii\helpers\Console;
use yii\console\Exception;

class MigrateController extends \yii\console\controllers\MigrateController
{
    /**
     * @param $name
     *
     * @return int
     * @throws Exception
     */
    public function actionRedoSingle($name)
    {
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            throw new Exception(
                'The migration name should contain letters, digits, underscore and/or backslash characters only.'
            );
        }

        $migration = $this->getMigrationByName($name);

        if (empty($migration)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);

            return self::EXIT_CODE_NORMAL;
        }

        if ($this->confirm('Redo the migration ' . $migration . '?')) {
            if (!$this->migrateDown($migration)) {
                $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                return self::EXIT_CODE_ERROR;
            }

            if (!$this->migrateUp($migration)) {
                $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

                return self::EXIT_CODE_ERROR;
            }

            $this->stdout("\nMigration redone successfully.\n", Console::FG_GREEN);
        }

        return true;
    }

    /**
     * @param $name
     *
     * @return false|null|string
     */
    private function getMigrationByName($name)
    {
        $query = new Query();

        return $query
            ->select('version')
            ->from($this->migrationTable)
            ->andWhere(['version' => $name])
            ->createCommand($this->db)
            ->queryScalar();
    }
}