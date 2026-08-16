<?php

use common\services\medals\AnnualPlaytimeMedalAwarder;
use common\services\medals\AnnualServerRecordMedalAwarder;
use console\components\migration\Migration;

/**
 * Executes annual medal awarding automatically during deployment.
 *
 * Both awarders are idempotent: repeated execution is protected by the unique
 * medal code and user_medal(user_id, medal_id) indexes.
 */
class m260817_050000_award_annual_medals extends Migration
{
    public function up()
    {
        $playtimeAwarder = new AnnualPlaytimeMedalAwarder($this->db);
        foreach ($playtimeAwarder->awardConfiguredYears() as $year => $awarded) {
            echo sprintf(
                "    annual playtime %d: awarded %d new medal(s).%s",
                $year,
                $awarded,
                PHP_EOL
            );
        }

        $recordAwarder = new AnnualServerRecordMedalAwarder($this->db);
        foreach ($recordAwarder->awardConfiguredCompletedYears() as $report) {
            echo sprintf(
                "    annual server records %d: awarded %d, already %d, no profile %d.%s",
                $report['year'],
                $report['awarded'],
                $report['already_awarded'],
                $report['skipped_without_profile'],
                PHP_EOL
            );
        }
    }

    public function down()
    {
        echo "m260817_050000_award_annual_medals cannot be reverted safely.\n";
        return false;
    }
}
