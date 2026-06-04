<?php

namespace common\components\queue\tournament;

use common\components\tournament\TournamentRankingCalculator;
use common\models\tournament\Tournament;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Пересчёт рейтинга одного турнира.
 */
class CalculateTournamentRankingsJob extends BaseObject implements JobInterface
{
    /** @var int */
    public $tournamentId;

    /**
     * @param \yii\queue\Queue $queue
     */
    public function execute($queue)
    {
        if (!$this->tournamentId) {
            return;
        }
        $tournament = Tournament::findOne((int)$this->tournamentId);
        if (!$tournament || !$tournament->isPubliclyVisible()) {
            return;
        }
        if ($tournament->getPublicPhase() !== Tournament::PHASE_ACTIVE) {
            return;
        }
        try {
            TournamentRankingCalculator::recalculate($tournament);
        } catch (\Throwable $e) {
            Yii::error(
                'CalculateTournamentRankingsJob tournament ' . $this->tournamentId . ': ' . $e->getMessage(),
                'tournament'
            );
        }
    }
}
