<?php

namespace console\components\migration;

class Migration extends \yii\db\Migration
{
    const TABLE_OPTIONS     = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
    const TABLE_OPTIONS_MB4 = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

    const PRIMARY_KEY        = 'INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    const INT_FIELD          = 'INT(10) UNSIGNED DEFAULT NULL';
    const INT_FIELD_NOT_NULL = 'INT(10) UNSIGNED NOT NULL';
    const TINYINT_1_FIELD    = 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0';
    const TIMESTAMP_FIELD    = 'TIMESTAMP NULL';
    const TINYINT_FIELD      = 'TINYINT(3) UNSIGNED NOT NULL';
    const VARCHAR_FIELD      = 'VARCHAR(255) DEFAULT NULL';

    protected $_ignoreError = false;

    /**
     * Executes a SQL statement.
     * This method executes the specified SQL statement using [[db]].
     *
     * @param string $sql    the SQL statement to be executed
     * @param array  $params input parameters (name => value) for the SQL execution.
     *                       See [[Command::execute()]] for more details.
     */
    public function execute($sql, $params = [])
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Creates and executes an INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * @param string $table   the table that new rows will be inserted into.
     * @param array  $columns the column data (name => value) to be inserted into the table.
     */
    public function insert($table, $columns)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Creates and executes an batch INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * @param string $table   the table that new rows will be inserted into.
     * @param array  $columns the column names.
     * @param array  $rows    the rows to be batch inserted into the table
     */
    public function batchInsert($table, $columns, $rows)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Creates and executes an UPDATE SQL statement.
     * The method will properly escape the column names and bind the values to be updated.
     *
     * @param string       $table     the table to be updated.
     * @param array        $columns   the column data (name => value) to be updated.
     * @param array|string $condition the conditions that will be put in the WHERE part. Please
     *                                refer to [[Query::where()]] on how to specify conditions.
     * @param array        $params    the parameters to be bound to the query.
     */
    public function update($table, $columns, $condition = '', $params = [])
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Creates and executes a DELETE SQL statement.
     *
     * @param string       $table     the table where the data will be deleted from.
     * @param array|string $condition the conditions that will be put in the WHERE part. Please
     *                                refer to [[Query::where()]] on how to specify conditions.
     * @param array        $params    the parameters to be bound to the query.
     */
    public function delete($table, $condition = '', $params = [])
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for creating a new DB table.
     *
     * The columns in the new  table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     *
     * The [[QueryBuilder::getColumnType()]] method will be invoked to convert any abstract type into a physical one.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * put into the generated SQL.
     *
     * @param string $table   the name of the table to be created. The name will be properly quoted by the method.
     * @param array  $columns the columns (name => definition) in the new table.
     * @param string $options additional SQL fragment that will be appended to the generated SQL.
     */
    public function createTable($table, $columns, $options = null)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for renaming a DB table.
     *
     * @param string $table   the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     */
    public function renameTable($table, $newName)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for dropping a DB table.
     *
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     */
    public function dropTable($table)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for truncating a DB table.
     *
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     */
    public function truncateTable($table)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for adding a new DB column.
     *
     * @param string $table  the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type   the column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     *                       into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     *                       For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     */
    public function addColumn($table, $column, $type)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for dropping a DB column.
     *
     * @param string $table  the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     */
    public function dropColumn($table, $column)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for renaming a column.
     *
     * @param string $table   the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name    the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     */
    public function renameColumn($table, $name, $newName)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for changing the definition of a column.
     *
     * @param string $table  the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type   the new column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     *                       into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     *                       For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     */
    public function alterColumn($table, $column, $type)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for creating a primary key.
     * The method will properly quote the table and column names.
     *
     * @param string       $name    the name of the primary key constraint.
     * @param string       $table   the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for dropping a primary key.
     *
     * @param string $name  the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     */
    public function dropPrimaryKey($name, $table)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     *
     * @param string       $name       the name of the foreign key constraint.
     * @param string       $table      the table that the foreign key constraint will be added to.
     * @param string|array $columns    the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas or use an array.
     * @param string       $refTable   the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas or use an array.
     * @param string       $delete     the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string       $update     the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     *
     * @param string $name  the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     */
    public function dropForeignKey($name, $table)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for creating a new index.
     *
     * @param string       $name    the name of the index. The name will be properly quoted by the method.
     * @param string       $table   the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
     *                              by commas or use an array. Each column name will be properly quoted by the method. Quoting will be skipped for column names that
     *                              include a left parenthesis "(".
     * @param boolean      $unique  whether to add UNIQUE constraint on the created index.
     */
    public function createIndex($name, $table, $columns, $unique = false)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and executes a SQL statement for dropping an index.
     *
     * @param string $name  the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     */
    public function dropIndex($name, $table)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and execute a SQL statement for adding comment to column
     *
     * @param string $table   the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column  the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     *
     * @since 2.0.8
     */
    public function addCommentOnColumn($table, $column, $comment)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds a SQL statement for adding comment to table
     *
     * @param string $table   the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     *
     * @since 2.0.8
     */
    public function addCommentOnTable($table, $comment)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds and execute a SQL statement for dropping comment from column
     *
     * @param string $table  the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     *
     * @since 2.0.8
     */
    public function dropCommentFromColumn($table, $column)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * Builds a SQL statement for dropping comment from table
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     *
     * @since 2.0.8
     */
    public function dropCommentFromTable($table)
    {
        $this->_executeMethod(__FUNCTION__, func_get_args());
    }

    /**
     * @param string $methodName
     * @param array  $args
     */
    private function _executeMethod($methodName, $args)
    {
        $object = parent::class;

        if ($this->_ignoreError) {
            try {
                call_user_func_array([$object, $methodName], $args);

            } catch (\Exception $e) {
                echo PHP_EOL . "\033[01;31m ERROR: {$e->getMessage()} \033[0m" . PHP_EOL;
            }

        } else {
            call_user_func_array([$object, $methodName], $args);
        }
    }
}