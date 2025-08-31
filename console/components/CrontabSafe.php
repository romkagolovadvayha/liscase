<?php
namespace console\components;

use gofmanaa\crontask\components\Crontab as BaseCrontab;
use gofmanaa\crontask\components\Cronjob;
use gofmanaa\crontask\components\CronApplicationJob;

/**
 * Безопасная обёртка над gofmanaa\crontask\components\Crontab.
 * Фильтрует «шумные» строки из crontab и не допускает падений парсера.
 */
class CrontabSafe extends BaseCrontab
{
    /** Разрешать ли cron-макросы (@daily, @hourly, ...). @reboot игнорируется. */
    public $allowMacros = false;

    /** Доп. шаблоны строк, которые надо игнорировать. */
    public $ignoredPatterns = [
        '/^##\s*Ispmanager/i',
        '/^\s*#/',                      // комментарии
        '/^\s*$/',                      // пустые
        '/^(SHELL|PATH|MAILTO|HOME)=/i' // переменные окружения
    ];

    /** Переопределяем загрузку задач: читаем системный + файл, фильтруем, парсим. */
    protected function loadJobs()
    {
        $this->jobs = [];

        $system = $this->readSystemCrontab();
        $file   = $this->readFileCrontab();

        // сливаем и убираем дубликаты
        $merged = array_values(array_unique(array_merge($system, $file)));

        // учитываем переносы строк с '\'
        foreach ($this->joinContinuedLines($merged) as $line) {
            $line = $this->sanitizeLine($line);
            if ($line === null) {
                continue; // пропущено фильтром
            }

            // поддержка макросов (@daily и т.п.)
            if ($this->allowMacros && preg_match('/^@(\w+)\s+(.+)$/', $line, $m)) {
                $mapped = $this->mapMacro($m[1], $m[2]);
                if ($mapped === null) {
                    continue; // неизвестный макрос или @reboot
                }
                $line = $mapped;
            }

            // окончательная простая валидация: 5 полей расписания + команда
            $parts = preg_split('/\s+/', $line, 6);
            if (count($parts) < 6) {
                continue;
            }

            // выбор парсера библиотеки
            $obj = CronApplicationJob::isApplicationJob($line)
                ? CronApplicationJob::parseFromCommand($line)
                : Cronjob::parseFromCommand($line);

            if ($obj !== false && $obj !== null) {
                $this->jobs[] = $obj;
            }
        }
    }

    /** Читает crontab пользователя. */
    protected function readSystemCrontab(): array
    {
        $cmd = rtrim((string)$this->crontabPath) . 'crontab -l 2>&1';
        $raw = [];
        @exec($cmd, $raw);
        $out = [];
        foreach ($raw as $line) {
            $line = trim($line);
            if (stripos($line, 'no crontab') === 0) {
                continue;
            }
            $out[] = $line;
        }
        return $out;
    }

    /** Читает строки из файла .crons. */
    protected function readFileCrontab(): array
    {
        $out = [];
        if (!is_resource($this->handle)) {
            return $out;
        }
        fseek($this->handle, 0);
        while (!feof($this->handle)) {
            $line = fgets($this->handle);
            if ($line === false) break;
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /** Склеивает строки, заканчивающиеся на '\' (многострочные команды). */
    protected function joinContinuedLines(array $lines): array
    {
        $res = [];
        $buf = '';
        foreach ($lines as $line) {
            $l = rtrim($line);
            if ($l === '') continue;
            if (substr($l, -1) === '\\') {
                $buf .= rtrim(substr($l, 0, -1)) . ' ';
                continue;
            }
            $res[] = $buf . $l;
            $buf = '';
        }
        if ($buf !== '') {
            $res[] = rtrim($buf);
        }
        return $res;
    }

    /** Нормализует и фильтрует строку crontab. Возвращает null, если её надо пропустить. */
    protected function sanitizeLine(string $line): ?string
    {
        $line = trim(preg_replace('/\s+/', ' ', $line));

        foreach ($this->ignoredPatterns as $re) {
            if (preg_match($re, $line)) {
                return null;
            }
        }

        // если макросы запрещены — отсекаем их сразу
        if (!$this->allowMacros && preg_match('/^@\w+\b/', $line)) {
            return null;
        }

        return $line;
    }

    /**
     * Мапит макрос (@daily, @hourly, ...) в обычное расписание.
     * @reboot не маппится — возвращаем null, чтобы пропустить.
     */
    protected function mapMacro(string $macro, string $cmd): ?string
    {
        $macro = strtolower($macro);
        $map = [
            'yearly'   => '0 0 1 1 *',
            'annually' => '0 0 1 1 *',
            'monthly'  => '0 0 1 * *',
            'weekly'   => '0 0 * * 0',
            'daily'    => '0 0 * * *',
            'midnight' => '0 0 * * *',
            'hourly'   => '0 * * * *',
        ];
        if (!isset($map[$macro])) {
            // @reboot и неизвестные — пропускаем
            return null;
        }
        return $map[$macro] . ' ' . $cmd;
    }
}
