<?php

namespace console\controllers;

use common\components\queue\tournament\CalculateTournamentRankingsJob;
use common\components\tournament\TournamentRankingCalculator;
use common\models\tournament\Tournament;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Турниры: пересчёт рейтингов (cron).
 */
class TournamentController extends Controller
{
    /**
     * Пересчитать рейтинги всех активных опубликованных турниров.
     * Рекомендуется cron: каждые 5 минут — yii tournament/recalculate-rankings
     */
    public function actionRecalculateRankings(): int
    {
        $tournaments = Tournament::find()
            ->where(['status' => Tournament::STATUS_PUBLISHED])
            ->all();

        $count = 0;
        foreach ($tournaments as $tournament) {
            if ($tournament->getPublicPhase() !== Tournament::PHASE_ACTIVE) {
                continue;
            }
            try {
                if (Yii::$app->has('queueParams')) {
                    Yii::$app->queueParams->push(new CalculateTournamentRankingsJob([
                        'tournamentId' => (int)$tournament->id,
                    ]));
                    $this->stdout("Queued CalculateTournamentRankingsJob for tournament {$tournament->id} ({$tournament->slug})\n");
                } else {
                    TournamentRankingCalculator::recalculate($tournament);
                    $this->stdout("Recalculated tournament {$tournament->id} ({$tournament->slug})\n");
                }
                $count++;
            } catch (\Throwable $e) {
                Yii::error('TournamentController::actionRecalculateRankings: ' . $e->getMessage(), 'tournament');
                $this->stderr("Error tournament {$tournament->id}: {$e->getMessage()}\n");
            }
        }

        $this->stdout("Done. Processed {$count} active tournament(s).\n");
        return ExitCode::OK;
    }
}
