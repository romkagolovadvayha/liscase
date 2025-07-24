<?php

use yii\db\Migration;

/**
 * Class m250724_130216_user_fileds
 */
class m250724_130216_user_fileds extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        Yii::$app->db->createCommand("
                   ALTER TABLE `user_profile`
                    ADD COLUMN `dob_day` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                    ADD COLUMN `dob_month` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                    ADD COLUMN `dob_year` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
                    ADD COLUMN `signature` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                    ADD COLUMN `website` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                    ADD COLUMN `location` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                    ADD COLUMN `following` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                    ADD COLUMN `ignored` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                    ADD COLUMN `avatar_crop_x` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                    ADD COLUMN `avatar_crop_y` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                    ADD COLUMN `banner_date` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                    ADD COLUMN `banner_position_y` TINYINT(3) UNSIGNED DEFAULT NULL,
                    ADD COLUMN `about` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
                    ADD COLUMN `custom_fields` MEDIUMBLOB,
                    ADD COLUMN `connected_accounts` MEDIUMBLOB,
                    ADD COLUMN `password_date` INT(10) UNSIGNED NOT NULL DEFAULT 1;
                ")->execute();
        Yii::$app->db->createCommand("
            CREATE INDEX `register_date` ON `user` (`register_date`);
                ")->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250724_130216_user_fileds cannot be reverted.\n";
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250724_130216_user_fileds cannot be reverted.\n";

        return false;
    }
    */
}
