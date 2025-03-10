<?php
namespace common\components\scanners;

use Yii;
use yii\base\InvalidConfigException;
use DemonDogSL\translateManager\services\Scanner;

class ScannerDatabase {

    private $_tables;
    private $_scanner;

    /**
     * @param Scanner $scanner
     */
    public function __construct(Scanner $scanner) {
        $this->_scanner = $scanner;
        $this->_tables = Yii::$app->getModule('translateManager')->tables;
        if (!empty($this->_tables) && is_array($this->_tables)) {
            foreach ($this->_tables as $tables) {
                if (empty($tables['connection'])) {
                    throw new InvalidConfigException('Incomplete database  configuration: connection ');
                } elseif (empty($tables['table'])) {
                    throw new InvalidConfigException('Incomplete database  configuration: table ');
                } elseif (empty($tables['columns'])) {
                    throw new InvalidConfigException('Incomplete database  configuration: columns ');
                }
            }
        }
    }

    public function run() {
        if (is_array($this->_tables)) {
            foreach ($this->_tables as $tables) {
                $this->_scanningTable($tables);
            }
        }
    }

    /**
     * @param array $tables
     */
    private function _scanningTable($tables) {
        $query = new \yii\db\Query();
        $dataQuery = $query->select($tables['columns'])
            ->from($tables['table']);

        if (!empty($tables['where'])) {
            $dataQuery->where($tables['where']);
        }

        $data = $dataQuery->createCommand(Yii::$app->{$tables['connection']})->queryAll();

        $category = $this->_getCategory($tables);
        foreach ($data as $columns) {
            $columns = array_map('trim', $columns);
            foreach ($columns as $column) {
                $this->_scanner->addLanguageItem($category, $column);
            }
        }
    }

    /**
     * @param array $tables
     *
     * @return string
     */
    private function _getCategory($tables) {
        if (isset($tables['category']) && $tables['category'] == 'database-table-name') {
            $category = $this->_normalizeTablename($tables['table']);
        } else {
            $category = Scanner::CATEGORY_DATABASE;
        }
        return $category;
    }

    /**
     * @param string $tableName database table name.
     * @return string
     */
    private function _normalizeTablename($tableName) {
        return str_replace(['{', '%', '}'], '', $tableName);
    }

}
