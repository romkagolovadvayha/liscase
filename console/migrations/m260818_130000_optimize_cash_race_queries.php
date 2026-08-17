<?php

use console\components\migration\Migration;

/** Adds covering indexes for the polling and leaderboard hot paths. */
class m260818_130000_optimize_cash_race_queries extends Migration
{
    public function safeUp()
    {
        $this->createIndex(
            'idx-tournaments-cash-race-current',
            'tournaments',
            ['type', 'status', 'server_id', 'ends_at']
        );
        $this->createIndex(
            'idx-cash-race-score-rank-cover',
            'cash_race_score',
            ['tournament_id', 'keys_deposited', 'last_deposited_at', 'user_id']
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx-cash-race-score-rank-cover', 'cash_race_score');
        $this->dropIndex('idx-tournaments-cash-race-current', 'tournaments');
    }
}
