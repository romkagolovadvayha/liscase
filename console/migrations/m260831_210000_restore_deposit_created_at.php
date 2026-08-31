<?php

use console\components\migration\Migration;

/**
 * Restores deposit creation timestamps changed by the initial completed_at
 * backfill while created_at still had ON UPDATE CURRENT_TIMESTAMP.
 */
class m260831_210000_restore_deposit_created_at extends Migration
{
    public function up()
    {
        // For a valid deposit lifecycle creation cannot be later than
        // completion. The broken backfill produced exactly that relation:
        // completed_at retained the original timestamp while created_at was
        // moved to the migration time. This predicate leaves all normal and
        // newly completed deposits untouched.
        $this->execute(
            'UPDATE {{%deposit}}'
            . ' SET [[created_at]] = [[completed_at]]'
            . ' WHERE [[status]] = 3'
            . ' AND [[completed_at]] IS NOT NULL'
            . ' AND [[created_at]] > [[completed_at]]'
        );
    }

    public function down()
    {
        // This is a deterministic data repair and must not corrupt restored
        // timestamps when a code rollback is performed.
        return true;
    }
}
