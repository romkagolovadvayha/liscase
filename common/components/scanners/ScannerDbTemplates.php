<?php
namespace common\components\scanners;

use Yii;
use yii\db\Query;
use DemonDogSL\translateManager\services\Scanner;
use common\models\template\TemplateFile;

class ScannerDbTemplates extends Scanner
{
    public $roots = ['frontend_views', 'common_views'];
    public $exts  = ['php', 'twig'];
    public $batchSize = 500;

    public function run(): void  // ← БЕЗ аргументов и с :void
    {
        try {
            $rows = TemplateFile::find()
                                ->select(['path','ext','content'])
                                ->where(['in', 'root_alias', $this->roots])
                                ->andWhere(['in', 'ext', $this->exts])
                                ->andWhere(['not', ['content' => null]])
                                ->asArray()
                                ->all();
        } catch (\Throwable $e) {
            return;
        }

        if (!$rows) return;

        $found = [];
        foreach ($rows as $r) {
            $content = (string)$r['content'];
            $ext = strtolower($r['ext']);

            if ($ext === 'twig') {
                foreach ($this->extractTwig($content) as $it) {
                    $found[$it['category']."\0".$it['message']] = $it;
                }
            } elseif ($ext === 'php') {
                foreach ($this->extractPhp($content) as $it) {
                    $found[$it['category']."\0".$it['message']] = $it;
                }
            }
        }

        if (!$found) return;

        $existing = (new Query())->from('{{%language_source}}')
                                 ->select(["concat(category, '\0', message) c"])
                                 ->column();
        $exists = array_flip($existing);

        $batch = [];
        foreach ($found as $k => $it) {
            if (isset($exists[$k])) continue;
            $batch[] = [$it['category'], $it['message']];
            if (count($batch) >= $this->batchSize) {
                $this->insertBatch($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $this->insertBatch($batch);
        }
    }

    private function insertBatch(array $rows): void
    {
        try {
            Yii::$app->db->createCommand()
                         ->batchInsert('{{%language_source}}', ['category','message'], $rows)
                         ->execute();
        } catch (\Throwable $e) { /* ignore duplicates */ }
    }

    private function extractTwig(string $s): array
    {
        $out = [];
        $re = '/\{\{\s*t\s*\(\s*([\'"])(?<cat>(?:\\\\.|(?!\1).)+)\1\s*,\s*([\'"])(?<msg>(?:\\\\.|(?!\3).)+)\3/su';
        if (preg_match_all($re, $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $out[] = ['category' => stripcslashes($x['cat']), 'message' => stripcslashes($x['msg'])];
            }
        }
        return $out;
    }

    private function extractPhp(string $s): array
    {
        $out = [];
        $re = '/(?:^|[^A-Za-z0-9_\\\\])Yii::t\s*\(\s*([\'"])(?<cat>(?:\\\\.|(?!\1).)+)\1\s*,\s*([\'"])(?<msg>(?:\\\\.|(?!\3).)+)\3/su';
        if (preg_match_all($re, $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $out[] = ['category' => stripcslashes($x['cat']), 'message' => stripcslashes($x['msg'])];
            }
        }
        return $out;
    }
}
