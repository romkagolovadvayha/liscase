<?php
namespace common\components\scanners;

use DemonDogSL\translateManager\services\scanners\ScannerFile;
use DemonDogSL\translateManager\services\Scanner;

class ScannerTwigTemplate extends ScannerFile {

    const EXTENSION = '*.twig';
    protected static $files = ['*.php' => [], '*.js' => [], '*.twig' => []];

    /**
     * @param string $route
     * @param array $params
     * @inheritdoc
     */
    public function run($route, $params = []) {
        foreach (self::$files[static::EXTENSION] as $file) {
            if ($this->containsTranslator(['t'], $file)) {
                $this->extractMessages($file, [
                    'translator' => (array) ['t'],
                    'begin' => '(',
                    'end' => ')',
                ]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function getLanguageItem($buffer) {
        if (isset($buffer[0][0]) && $buffer[0][0] === T_CONSTANT_ENCAPSED_STRING) {
            foreach ($buffer as $data) {
                if (isset($data[0], $data[1]) && $data[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $message = stripcslashes($data[1]);
                    $messages[] = mb_substr($message, 1, mb_strlen($message) - 2);
                } elseif ($data === ',') {
                    break;
                }
            }
            $message = implode('', $messages);
            return [
                [
                    'category' => Scanner::CATEGORY_ARRAY,
                    'message' => $message,
                ],
            ];
        }
        return null;
    }
}