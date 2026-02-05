<?php

use yii\db\Migration;

/**
 * Class m260205_083427_create_servers_radio_stations_table
 * Создание таблицы для радиостанций серверов
 */
class m260205_083427_create_servers_radio_stations_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%servers_radio_stations}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull()->comment('Название радиостанции'),
            'url' => $this->string(500)->notNull()->comment('URL потока радиостанции'),
            'logo' => $this->string(500)->null()->comment('URL логотипа на S3'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('Порядок сортировки'),
            'status' => $this->smallInteger()->notNull()->defaultValue(1)->comment('Статус (1 - активна, 0 - неактивна)'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_servers_radio_stations_status', '{{%servers_radio_stations}}', 'status');
        $this->createIndex('idx_servers_radio_stations_sort', '{{%servers_radio_stations}}', ['status', 'sort']);

        // Заполняем таблицу данными из хардкода
        $stations = [
            ['name' => 'Русское', 'url' => 'https://rusradio.hostingradio.ru/rusradio128.mp3', 'sort' => 1],
            ['name' => 'Маруся', 'url' => 'https://radio-holding.ru:9433/marusya_default', 'sort' => 2],
            ['name' => 'Новое', 'url' => 'https://stream.newradio.ru/novoe96.aacp', 'sort' => 3],
            ['name' => 'TNT', 'url' => 'https://tntradio.hostingradio.ru:8027/tntradio128.mp3', 'sort' => 4],
            ['name' => 'Авто', 'url' => 'https://pub0201.101.ru/stream/air/aac/64/100', 'sort' => 5],
            ['name' => 'Energy', 'url' => 'https://pub0201.101.ru/stream/air/aac/64/99', 'sort' => 6],
            ['name' => 'Попса', 'url' => 'https://pub0201.101.ru/stream/air/aac/64/99', 'sort' => 7],
            ['name' => 'Шансон', 'url' => 'https://chanson.hostingradio.ru:8041/chanson128.mp3', 'sort' => 8],
            ['name' => 'Романтический Шансон', 'url' => 'https://chanson.hostingradio.ru:8041/chanson-romantic256.mp3', 'sort' => 9],
            ['name' => 'Калина Красная', 'url' => 'https://icecast-studio21.cdnvideo.ru/KalynaK_1a', 'sort' => 10],
            ['name' => 'Спутник', 'url' => 'https://radio.mediacdn.ru/sputnik_fm.mp3', 'sort' => 11],
        ];

        $time = time();
        foreach ($stations as $station) {
            $this->insert('{{%servers_radio_stations}}', [
                'name' => $station['name'],
                'url' => $station['url'],
                'logo' => null,
                'sort' => $station['sort'],
                'status' => 1,
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%servers_radio_stations}}');
    }
}

