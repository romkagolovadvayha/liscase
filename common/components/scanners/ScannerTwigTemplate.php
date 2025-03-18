<?php
namespace common\components\scanners;

use DemonDogSL\translateManager\services\scanners\ScannerFile;
use DemonDogSL\translateManager\services\Scanner;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

class ScannerTwigTemplate extends ScannerFile {

    const EXTENSION = '*.twig';
    public static $files = ['*.php' => [], '*.js' => [], '*.twig' => []];

    /**
     * @param string $route
     * @param array $params
     * @inheritdoc
     */
    public function run($route, $params = []) {
        $this->initFiles();
        foreach (self::$files[static::EXTENSION] as $file) {
            if ($this->containsTranslator(['t'], $file)) {
                $this->extractMessages($file, [
                    'translator' => (array) ['{{ t', '{{t', ': t'],
                    'begin' => '(',
                    'end' => ')',
                ]);
            }
        }
    }

    public function initFiles() {
        if (!empty(self::$files[static::EXTENSION]) || !in_array(static::EXTENSION, $this->module->patterns)) {
            return;
        }
        self::$files[static::EXTENSION] = [];
        foreach ($this->_getRoots() as $root) {
            $root = realpath($root);
            \Yii::trace('Scanning ' . static::EXTENSION . " files for language elements in: $root", 'translateManager');
            $files = FileHelper::findFiles($root, [
                'except' => $this->module->ignoredItems,
                'only' => [static::EXTENSION],
            ]);
            self::$files[static::EXTENSION] = array_merge(self::$files[static::EXTENSION], $files);
        }
        self::$files[static::EXTENSION] = array_unique(self::$files[static::EXTENSION]);
    }

    /**
     * @return array
     */
    private function _getRoots() {
        $directories = [];
        $__root = [
            '@frontend',
            '@common',
        ];
        if (is_string($__root)) {
            $root = \Yii::getAlias($__root);
            if ($this->module->scanRootParentDirectory) {
                $root = dirname($root);
            }
            $directories[] = $root;
        } elseif (is_array($__root)) {
            foreach ($__root as $root) {
                $directories[] = \Yii::getAlias($root);
            }
        } else {
            throw new InvalidConfigException('Invalid `root` option value!');
        }
        return $directories;
    }

    /**
     * @inheritdoc
     */
    protected function getLanguageItem($buffer) {
        if (isset($buffer[0][0], $buffer[1], $buffer[2][0]) && $buffer[0][0] === T_CONSTANT_ENCAPSED_STRING && $buffer[1] === ',' && $buffer[2][0] === T_CONSTANT_ENCAPSED_STRING) {
            // is valid call we can extract
            $category = stripcslashes($buffer[0][1]);
            $category = mb_substr($category, 1, mb_strlen($category) - 2);
            if (!$this->isValidCategory($category)) {
                return null;
            }
            $message = implode('', $this->concatMessage($buffer));
            return [
                [
                    'category' => $category,
                    'message' => $message,
                ],
            ];
        }
        return null;
    }

    /**
     * @param array $buffer Array to store language element pieces.
     * @return array Sorted list of language element pieces.
     */
    protected function concatMessage($buffer) {
        $messages = [];
        $buffer = array_slice($buffer, 2);
        $message = stripcslashes($buffer[0][1]);
        $messages[] = mb_substr($message, 1, mb_strlen($message) - 2);
        if (isset($buffer[1], $buffer[2][0]) && $buffer[1] === '.' && $buffer[2][0] == T_CONSTANT_ENCAPSED_STRING) {
            $messages = array_merge_recursive($messages, $this->concatMessage($buffer));
        }
        return $messages;
    }
}