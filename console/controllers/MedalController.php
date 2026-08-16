<?php

namespace console\controllers;

use common\services\medals\AnnualPlaytimeMedalAwarder;
use common\services\medals\AnnualServerRecordMedalAwarder;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Bulk medal operations.
 */
class MedalController extends Controller
{
    /** @var int|null Limit annual playtime awarding to one configured year. */
    public $year;

    /** @var bool Only count users who would receive a medal. */
    public $dryRun = false;

    /** @var bool Permit an explicit run for a year that has not ended. */
    public $allowIncomplete = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['year', 'dryRun', 'allowIncomplete']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'y' => 'year',
            'n' => 'dryRun',
            'i' => 'allowIncomplete',
        ]);
    }

    /**
     * Awards medals for >500 minutes of total project playtime in each configured year.
     *
     * Examples:
     *   yii medal/award-annual-playtime --dry-run=1
     *   yii medal/award-annual-playtime --year=2026
     */
    public function actionAwardAnnualPlaytime(): int
    {
        $year = $this->year === null ? null : (int)$this->year;
        if ($year !== null && !in_array($year, AnnualPlaytimeMedalAwarder::SUPPORTED_YEARS, true)) {
            $this->stderr('Supported years: ' . implode(', ', AnnualPlaytimeMedalAwarder::SUPPORTED_YEARS) . PHP_EOL);
            return ExitCode::USAGE;
        }

        $years = $year === null ? AnnualPlaytimeMedalAwarder::SUPPORTED_YEARS : [$year];
        $awarder = new AnnualPlaytimeMedalAwarder(Yii::$app->db);

        try {
            foreach ($years as $configuredYear) {
                $medalId = $awarder->getActiveMedalId($configuredYear);
                if ($this->dryRun) {
                    $count = $awarder->countAwardableForYear($configuredYear, $medalId);
                    $this->stdout("{$configuredYear}: would award {$count} medal(s)." . PHP_EOL);
                    continue;
                }

                $count = $awarder->awardYear($configuredYear, $medalId);
                $this->stdout("{$configuredYear}: awarded {$count} new medal(s)." . PHP_EOL);
            }
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr($e->getMessage() . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Awards the #1 player in every Records category, separately by year/server.
     * With no --year, only completed configured years are processed.
     *
     * Examples:
     *   yii medal/award-annual-server-records --dry-run=1
     *   yii medal/award-annual-server-records --year=2025
     *   yii medal/award-annual-server-records --year=2026 --allow-incomplete=1
     */
    public function actionAwardAnnualServerRecords(): int
    {
        $year = $this->year === null ? null : (int)$this->year;
        if ($year !== null && !in_array($year, AnnualServerRecordMedalAwarder::SUPPORTED_YEARS, true)) {
            $this->stderr('Supported years: ' . implode(', ', AnnualServerRecordMedalAwarder::SUPPORTED_YEARS) . PHP_EOL);
            return ExitCode::USAGE;
        }

        $awarder = new AnnualServerRecordMedalAwarder(Yii::$app->db);
        try {
            $reports = $awarder->awardConfiguredCompletedYears(
                $year,
                (bool)$this->allowIncomplete,
                (bool)$this->dryRun
            );
            foreach ($reports as $report) {
                $verb = $this->dryRun ? 'would-award' : 'awarded';
                $this->stdout(sprintf(
                    "%d: servers=%d, winners=%d, eligible=%d, %s=%d, already=%d, no-profile=%d%s",
                    $report['year'],
                    $report['servers'],
                    $report['winners'],
                    $report['eligible'],
                    $verb,
                    $report['awarded'],
                    $report['already_awarded'],
                    $report['skipped_without_profile'],
                    PHP_EOL
                ));
            }
        } catch (\InvalidArgumentException $e) {
            $this->stderr($e->getMessage() . PHP_EOL);
            return ExitCode::USAGE;
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->stderr($e->getMessage() . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
}
