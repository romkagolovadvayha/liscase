<?php
namespace console\components;

use gofmanaa\crontask\components\Crontab as BaseCrontab;
use gofmanaa\crontask\components\Cronjob;

class CrontabSafe extends BaseCrontab
{
    protected function loadJobs()
    {
        $this->jobs = [];
        $output = $this->getAllJobs(); // у родителя это читает crontab -l
        $lines = preg_split('/\R/u', (string)$output);

        foreach ($lines as $line) {
            $line = trim($line);
            // пропускаем пустые, комментарии, и строки-окружения
            if ($line === '' || $line[0] === '#'
                || preg_match('/^(SHELL|PATH|MAILTO|HOME)=/i', $line)) {
                continue;
            }
            // простая валидация: 5 тайм-полей + команда ИЛИ cron-макрос (@reboot и т.п.)
            if (!preg_match('/^(@\w+|\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+.+$/', $line)) {
                continue;
            }
            // сам парсинг
            $job = Cronjob::parseFromCommand($line);
            if ($job) {
                $this->jobs[] = $job;
            }
        }
    }
}
