<?php
/**
 * This view is used by controllers/MigrateController.php
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name */

echo "<?php\n";
?>

use console\components\migration\Migration;

class <?= $className ?> extends Migration
{
    public function up()
    {

    }

    public function down()
    {

    }
}
