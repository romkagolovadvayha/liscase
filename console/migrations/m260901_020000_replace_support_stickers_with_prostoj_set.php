<?php

use console\components\migration\Migration;

/**
 * Replaces the support sticker catalog with the 32-piece PROSTOJ set.
 *
 * New files are uploaded to a versioned S3 prefix. Old S3 objects are kept
 * because existing support messages may still contain their public URLs.
 */
class m260901_020000_replace_support_stickers_with_prostoj_set extends Migration
{
    private const LOCAL_DIR_ALIAS = '@frontend/web/stickers';
    private const REMOTE_DIR = 'prostoj-2026';
    private const WIDTH = 512;
    private const HEIGHT = 512;

    private const STICKERS = [
        ['file' => '01-privet.webp', 'code' => 'prostoj_01_privet', 'name' => 'Привет!'],
        ['file' => '02-ya-tut.webp', 'code' => 'prostoj_02_ya_tut', 'name' => 'Я тут'],
        ['file' => '03-spasibo.webp', 'code' => 'prostoj_03_spasibo', 'name' => 'Спасибо!'],
        ['file' => '04-pozhaluysta.webp', 'code' => 'prostoj_04_pozhaluysta', 'name' => 'Пожалуйста'],
        ['file' => '05-ponyal.webp', 'code' => 'prostoj_05_ponyal', 'name' => 'Понял'],
        ['file' => '06-ne-ponyal.webp', 'code' => 'prostoj_06_ne_ponyal', 'name' => 'Не понял'],
        ['file' => '07-odobryayu.webp', 'code' => 'prostoj_07_odobryayu', 'name' => 'Одобряю'],
        ['file' => '08-nu-takoe.webp', 'code' => 'prostoj_08_nu_takoe', 'name' => 'Ну такое'],
        ['file' => '09-ha-ha.webp', 'code' => 'prostoj_09_ha_ha', 'name' => 'Ха-ха!'],
        ['file' => '10-v-shoke.webp', 'code' => 'prostoj_10_v_shoke', 'name' => 'В шоке'],
        ['file' => '11-panika.webp', 'code' => 'prostoj_11_panika', 'name' => 'Паника!'],
        ['file' => '12-gorit.webp', 'code' => 'prostoj_12_gorit', 'name' => 'Горит!'],
        ['file' => '13-vsyo-norm.webp', 'code' => 'prostoj_13_vsyo_norm', 'name' => 'Всё норм'],
        ['file' => '14-grustno.webp', 'code' => 'prostoj_14_grustno', 'name' => 'Грустно'],
        ['file' => '15-ya-ustal.webp', 'code' => 'prostoj_15_ya_ustal', 'name' => 'Я устал'],
        ['file' => '16-zhdyom.webp', 'code' => 'prostoj_16_zhdyom', 'name' => 'Ждёмс'],
        ['file' => '17-shcha-pochinyu.webp', 'code' => 'prostoj_17_shcha_pochinyu', 'name' => 'Ща починю'],
        ['file' => '18-eto-ficha.webp', 'code' => 'prostoj_18_eto_ficha', 'name' => 'Это фича'],
        ['file' => '19-ne-rabotaet.webp', 'code' => 'prostoj_19_ne_rabotaet', 'name' => 'Не работает'],
        ['file' => '20-gotovo.webp', 'code' => 'prostoj_20_gotovo', 'name' => 'Готово!'],
        ['file' => '21-eshchyo-minutku.webp', 'code' => 'prostoj_21_eshchyo_minutku', 'name' => 'Ещё минутку'],
        ['file' => '22-ya-v-afk.webp', 'code' => 'prostoj_22_ya_v_afk', 'name' => 'Я в АФК'],
        ['file' => '23-gde-lut.webp', 'code' => 'prostoj_23_gde_lut', 'name' => 'Где лут?'],
        ['file' => '24-minus-baza.webp', 'code' => 'prostoj_24_minus_baza', 'name' => 'Минус база'],
        ['file' => '25-poslednyaya-katka.webp', 'code' => 'prostoj_25_poslednyaya_katka', 'name' => 'Последняя катка'],
        ['file' => '26-chisto-skill.webp', 'code' => 'prostoj_26_chisto_skill', 'name' => 'Чисто скилл'],
        ['file' => '27-banhammer.webp', 'code' => 'prostoj_27_banhammer', 'name' => 'Банхаммер'],
        ['file' => '28-bez-paniki.webp', 'code' => 'prostoj_28_bez_paniki', 'name' => 'Без паники'],
        ['file' => '29-dogovorilis.webp', 'code' => 'prostoj_29_dogovorilis', 'name' => 'Договорились'],
        ['file' => '30-za-prostoj.webp', 'code' => 'prostoj_30_za_prostoj', 'name' => 'За PROSTOJ!'],
        ['file' => '31-my-bogaty.webp', 'code' => 'prostoj_31_my_bogaty', 'name' => 'Мы богаты!'],
        ['file' => '32-krasava.webp', 'code' => 'prostoj_32_krasava', 'name' => 'Красава!'],
    ];

    public function up()
    {
        $localDir = \Yii::getAlias(self::LOCAL_DIR_ALIAS);
        $this->validateLocalAssets($localDir);
        $this->uploadAssets($localDir);

        $transaction = $this->db->beginTransaction();
        try {
            $this->replaceDatabaseRows();
            $transaction->commit();
        } catch (Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        \Yii::$app->cache->delete('api_support_stickers');
        \Yii::$app->cache->delete('support_stickers_list');
    }

    public function safeDown()
    {
        echo "m260901_020000_replace_support_stickers_with_prostoj_set cannot be reverted automatically.\n";
        echo "The previous database rows are not recreated to avoid guessing their production values.\n";

        return false;
    }

    private function validateLocalAssets(string $localDir): void
    {
        if (!is_dir($localDir)) {
            throw new RuntimeException('Support sticker directory was not found: ' . $localDir);
        }

        foreach (self::STICKERS as $sticker) {
            $path = $localDir . DIRECTORY_SEPARATOR . $sticker['file'];
            if (!is_file($path) || filesize($path) === 0) {
                throw new RuntimeException('Support sticker asset was not found or is empty: ' . $path);
            }

            $size = getimagesize($path);
            if ($size === false || $size[0] !== self::WIDTH || $size[1] !== self::HEIGHT) {
                throw new RuntimeException(
                    sprintf('Support sticker must be %dx%d pixels: %s', self::WIDTH, self::HEIGHT, $path)
                );
            }
        }
    }

    private function uploadAssets(string $localDir): void
    {
        $s3Api = \Yii::$app->s3Api;

        foreach (self::STICKERS as $index => $sticker) {
            $path = $localDir . DIRECTORY_SEPARATOR . $sticker['file'];
            $key = 'support/stickers/' . self::REMOTE_DIR . '/' . $sticker['file'];

            echo sprintf(
                "Uploading support sticker %d/%d: %s\n",
                $index + 1,
                count(self::STICKERS),
                $key
            );

            if ($s3Api->putFile($key, $path, 'image/webp') === false) {
                throw new RuntimeException('Failed to upload support sticker to S3: ' . $key);
            }
        }
    }

    private function replaceDatabaseRows(): void
    {
        $now = time();
        $codes = array_column(self::STICKERS, 'code');

        foreach (self::STICKERS as $index => $sticker) {
            $values = [
                'name' => $sticker['name'],
                'file' => self::REMOTE_DIR . '/' . $sticker['file'],
                'type' => 'image',
                'width' => self::WIDTH,
                'height' => self::HEIGHT,
                'sort' => $index + 1,
                'status' => 1,
                'updated_at' => $now,
            ];

            $existingId = $this->db->createCommand(
                'SELECT `id` FROM `support_sticker` WHERE `code` = :code',
                [':code' => $sticker['code']]
            )->queryScalar();

            if ($existingId) {
                $this->update('support_sticker', $values, ['id' => (int)$existingId]);
                continue;
            }

            $values['code'] = $sticker['code'];
            $values['created_at'] = $now;
            $this->insert('support_sticker', $values);
        }

        // Remove the previous catalog from the picker. Its S3 files intentionally
        // remain available so stickers embedded in old support messages still load.
        $this->delete('support_sticker', ['not in', 'code', $codes]);
    }
}
