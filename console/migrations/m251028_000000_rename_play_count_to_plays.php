<?php

use console\components\migration\Migration;

/**
 * Rename play_count to plays in radio_track table
 */
class m251028_000000_rename_play_count_to_plays extends Migration
{
    public function safeUp()
    {
        // Check if play_count exists
        $columns = $this->db->createCommand('SHOW COLUMNS FROM radio_track')->queryAll();
        $hasPlayCount = false;
        $hasPlays = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'play_count') {
                $hasPlayCount = true;
            }
            if ($col['Field'] === 'plays') {
                $hasPlays = true;
            }
        }
        
        if ($hasPlayCount && !$hasPlays) {
            echo "  > Renaming play_count to plays...\n";
            $this->renameColumn('radio_track', 'play_count', 'plays');
            echo "  > Done!\n";
        } elseif ($hasPlays) {
            echo "  > Column 'plays' already exists\n";
        } else {
            echo "  > Adding 'plays' column...\n";
            $this->addColumn('radio_track', 'plays', 'INT(10) UNSIGNED DEFAULT 0 AFTER likes');
        }
        
        // Also check for other columns that might be missing
        $hasFilepath = false;
        $hasRejectReason = false;
        $hasUpdatedAt = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'filepath') $hasFilepath = true;
            if ($col['Field'] === 'reject_reason') $hasRejectReason = true;
            if ($col['Field'] === 'updated_at') $hasUpdatedAt = true;
        }
        
        // Remove filepath if exists (we use filename instead)
        if ($hasFilepath) {
            echo "  > Dropping unused column 'filepath'...\n";
            $this->dropColumn('radio_track', 'filepath');
        }
        
        return true;
    }

    public function safeDown()
    {
        echo "m251028_000000_rename_play_count_to_plays cannot be reverted.\n";
        return false;
    }
}

