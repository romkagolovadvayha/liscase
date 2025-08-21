<?php
namespace console\components;

use Yii;
use yii\base\Component;
use yii\console\ExitCode;

class Supervisor extends Component
{
    /** Путь к supervisorctl (проверь: which supervisorctl) */
    public string $ctl = '/usr/bin/supervisorctl';

    /** Нужен ли sudo (если supervisord крутится от root) */
    public bool $sudo = false;

    /** Добавить -c <config> (если нужен нестандартный конфиг) */
    public ?string $config = null;

    /** Добавить -s <unix:///run/supervisor.sock | http://host:port> (если нестандартный сокет/endpoint) */
    public ?string $socket = null;

    /** Доп. переменные окружения для процесса */
    public array $env = [];

    /** Таймаут выполнения (сек) */
    public int $timeout = 30;

    /** Выполнить supervisorctl с аргументами */
    protected function run(array $args): array
    {
        $cmd = [];

        if ($this->sudo) {
            $cmd[] = 'sudo';
            $cmd[] = '-n'; // без запроса пароля
        }

        $cmd[] = escapeshellcmd($this->ctl);

        if ($this->config) {
            $cmd[] = '-c';
            $cmd[] = escapeshellarg($this->config);
        }
        if ($this->socket) {
            $cmd[] = '-s';
            $cmd[] = escapeshellarg($this->socket);
        }

        foreach ($args as $a) {
            $cmd[] = $a; // аргументы ниже сами экранируем при формировании
        }

        $command = implode(' ', $cmd);

        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = array_merge($_ENV, $this->env);
        $proc = proc_open($command, $descriptor, $pipes, null, $env);

        if (!\is_resource($proc)) {
            return [ExitCode::UNSPECIFIED_ERROR, '', 'Failed to start supervisorctl'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $p) { fclose($p); }

        $status = proc_close($proc);

        return [$status, (string)$stdout, (string)$stderr];
    }

    public function status(?string $filter = null): array
    {
        [$code, $out, $err] = $this->run(['status']);
        if ($code !== 0) {
            return ['ok' => false, 'code' => $code, 'error' => trim($err ?: $out)];
        }

        $lines = preg_split('/\R/u', trim($out));
        $items = [];
        foreach ($lines as $line) {
            if ($line === '') continue;
            // Формат: "program_name           RUNNING   pid 123, uptime 0:01:23"
            // или "group:program          FATAL     exited too quickly ..."
            if (!preg_match('/^(?<name>\S+)\s+(?<state>\S+)\s*(?<rest>.*)$/u', $line, $m)) {
                $items[] = ['raw' => $line];
                continue;
            }
            $items[] = [
                'name'  => $m['name'],
                'state' => $m['state'],
                'info'  => trim($m['rest']),
            ];
        }

        if ($filter) {
            $items = array_values(array_filter($items, function ($row) use ($filter) {
                return isset($row['name']) && stripos($row['name'], $filter) !== false;
            }));
        }

        return ['ok' => true, 'list' => $items];
    }

    public function start(string $name): array
    {
        return $this->simple('start', $name);
    }
    public function stop(string $name): array
    {
        return $this->simple('stop', $name);
    }
    public function restart(string $name): array
    {
        // у supervisorctl есть restart <name>
        return $this->simple('restart', $name);
    }
    public function signal(string $name, string $signal = 'TERM'): array
    {
        return $this->runAndWrap(['signal', escapeshellarg(strtoupper($signal)), escapeshellarg($name)]);
    }
    public function clear(string $name): array
    {
        return $this->simple('clear', $name); // очистка логов
    }

    public function reread(): array
    {
        return $this->runAndWrap(['reread']);
    }
    public function update(): array
    {
        return $this->runAndWrap(['update']);
    }
    public function reload(): array
    {
        // перезапускает сам supervisord (жёстко)
        return $this->runAndWrap(['reload']);
    }

    /** Последние N строк stdout|stderr у процесса */
    public function tail(string $name, string $stream = 'stdout', int $last = 200): array
    {
        $stream = strtolower($stream);
        if (!\in_array($stream, ['stdout', 'stderr'], true)) {
            $stream = 'stdout';
        }
        $last = max(1, min(10000, $last));
        return $this->runAndWrap(['tail', escapeshellarg($name), $stream, (string)('-' . $last)]);
    }

    protected function simple(string $verb, string $name): array
    {
        return $this->runAndWrap([$verb, escapeshellarg($name)]);
    }

    protected function runAndWrap(array $args): array
    {
        [$code, $out, $err] = $this->run($args);
        return [
            'ok'   => ($code === 0),
            'code' => $code,
            'out'  => trim($out),
            'err'  => trim($err),
        ];
    }
}
