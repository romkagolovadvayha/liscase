<?php

use yii\db\Migration;

/**
 * Class m251203_062405_set_vip_drop_description
 * Устанавливает HTML описание для товара типа VIP
 */
class m251203_062405_set_vip_drop_description extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // HTML описание с CSS стилями для VIP товара
        $description = <<<HTML
<style>
.vip-description {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.6;
    color: #e0e0e0;
}
.vip-description h3 {
    color: #eb0c35;
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #eb0c35;
}
.vip-description ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.vip-description li {
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.vip-description li:last-child {
    border-bottom: none;
}
.vip-description .command {
    color: #aaf16e;
    font-weight: 600;
    font-family: 'Courier New', monospace;
    background: rgba(170, 241, 110, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
}
.vip-description .feature-name {
    color: #feeda1;
    font-weight: 500;
}
.vip-description .feature-desc {
    color: #b0b0b0;
    margin-left: 8px;
}
</style>
<div class="vip-description">
    <h3>Преимущества VIP статуса</h3>
    <ul>
        <li>
            <span class="command">/skin</span>
            <span class="feature-desc">— моментальная смена любых ваших кастомных скинов из этого раздела.</span>
        </li>
        <li>
            <span class="command">/w</span>
            <span class="feature-desc">— быстрая смена обоев прямо в игре.</span>
        </li>
        <li>
            <span class="feature-name">Уникальные задания</span>
            <span class="feature-desc">— специальные квесты, доступные только для VIP-игроков.</span>
        </li>
        <li>
            <span class="feature-name">Скрытие онлайна</span>
            <span class="feature-desc">— возможность скрыть своё присутствие на сервере.</span>
        </li>
        <li>
            <span class="feature-name">Приватность команды</span>
            <span class="feature-desc">— скрытие информации о вашей тиме от других игроков.</span>
        </li>
    </ul>
</div>
HTML;

        // Обновляем описание для всех товаров с типом VIP
        $this->update(
            'drop',
            ['description' => $description],
            ['drop_type' => 4] // TYPE_VIP = 4
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Откатываем изменения - очищаем описание для VIP товаров
        $this->update(
            'drop',
            ['description' => ''],
            ['drop_type' => 4] // TYPE_VIP = 4
        );
    }
}
