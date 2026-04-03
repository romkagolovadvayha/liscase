<?php

use yii\db\Migration;

/**
 * Права «авторизации в объектах» команды (Rust): ПВО, замки, шкафы, турели.
 */
class m260402_140000_clan_permissions_auth_entities extends Migration
{
    public function safeUp()
    {
        $t = time();
        $this->batchInsert('clan_permissions', ['key', 'name', 'description', 'created_at'], [
            ['auth_sam', 'Авторизация ПВО', 'Доступ к зенитным установкам (SAM) команды', $t],
            ['auth_lock', 'Авторизация в замках', 'Доступ к кодовым замкам команды', $t],
            ['auth_cupboard', 'Авторизация в шкафах', 'Доступ к шкафам команды', $t],
            ['auth_turret', 'Авторизация в турелях', 'Доступ к автоматическим турелям команды', $t],
        ]);
    }

    public function safeDown()
    {
        $this->delete('clan_permissions', ['in', 'key', ['auth_sam', 'auth_lock', 'auth_cupboard', 'auth_turret']]);
    }
}
