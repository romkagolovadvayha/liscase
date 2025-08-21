<?php
namespace console\components;

use Yii;
use yii\base\Component;
use yii\helpers\FileHelper;

class Supervisortask extends Component
{
    /** Компонент supervisorctl (см. ниже в config) */
    public string $supervisorComponentId = 'supervisor';

    /** Директория, куда писать .conf (из config файла) */
    public string $dir;
    /** Префикс имён файлов */
    public string $prefix = 'app';
    /** Имя группы (опц.) */
    public ?string $group = null;

    /** Сами определения программ (name => options[]) */
    public array $programs = [];

    /** Загрузка PHP-конфига с программами */
    public function loadFromFile(string $file): void
    {
        $cfg = require $file;
        $this->dir    = rtrim($cfg['dir'] ?? '/etc/supervisor/conf.d', '/');
        $this->prefix = $cfg['prefix'] ?? 'app';
        $this->group  = $cfg['group']  ?? null;
        $this->programs = $cfg['programs'] ?? [];
    }

    /** Основной метод: сгенерить .conf файлы и применить через supervisorctl */
    public function sync(bool $apply = true): array
    {
        if (empty($this->dir) || empty($this->programs)) {
            return [false, 'Empty dir or programs'];
        }
        FileHelper::createDirectory($this->dir);

        $written = [];
        foreach ($this->programs as $name => $opts) {
            $content = $this->buildProgram($name, $opts);
            $file = $this->filePath("{$this->prefix}-{$name}.conf");
            $this->atomicWrite($file, $content);
            $written[] = $file;
        }

        if ($this->group) {
            $groupContent = $this->buildGroup($this->group, array_keys($this->programs));
            $file = $this->filePath("{$this->prefix}-group.conf");
            $this->atomicWrite($file, $groupContent);
            $written[] = $file;
        }

        if ($apply) {
            /** @var \console\components\Supervisor $sup */
            $sup = Yii::$app->get($this->supervisorComponentId);
            $sup->reread();
            $sup->update();
        }

        return [true, $written];
    }

    protected function filePath(string $basename): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $basename;
    }

    // + добавь в класс:
    protected function expandAliasesInString(string $s): string {
        // заменим все вхождения вроде @app/..., @runtime/..., @app/..
        return preg_replace_callback('/@[\w\-\.\/]+/u', function($m){
            $r = \Yii::getAlias($m[0], false);
            return $r !== false ? $r : $m[0];
        }, $s);
    }

    protected function expandAliasesInOptions(array $o): array {
        foreach (['directory','command','stdout_logfile','stderr_logfile'] as $k) {
            if (isset($o[$k]) && is_string($o[$k])) {
                $o[$k] = $this->expandAliasesInString($o[$k]);
            }
        }
        // environment может быть строкой с алиасами
        if (isset($o['environment']) && is_string($o['environment'])) {
            $o['environment'] = $this->expandAliasesInString($o['environment']);
        }
        return $o;
    }

    // ⇩ заменяем твою buildProgram на эту
    protected function buildProgram(string $name, array $opts): string
    {
        $o = $this->expandAliasesInOptions($opts);

        // гарантируем каталоги логов
        foreach (['stdout_logfile','stderr_logfile'] as $k) {
            if (!empty($o[$k]) && is_string($o[$k])) {
                $dir = dirname($o[$k]);
                if ($dir && !is_dir($dir)) {
                    \yii\helpers\FileHelper::createDirectory($dir);
                }
            }
        }

        $lines = [];
        $lines[] = "[program:{$name}]";
        $this->line($lines, 'command',       $o['command'] ?? null, true);
        $this->line($lines, 'directory',     $o['directory'] ?? null);
        $this->line($lines, 'user',          $o['user'] ?? null);

        $keys = [
            'autostart','autorestart','startsecs','startretries','exitcodes','stopsignal',
            'stopwaitsecs','stopasgroup','killasgroup','numprocs','process_name','priority',
            'stdout_logfile','stderr_logfile','stdout_logfile_maxbytes','stderr_logfile_maxbytes',
            'stdout_logfile_backups','stderr_logfile_backups','redirect_stderr','serverurl',
            'rlimit_nofile',
        ];
        foreach ($keys as $k) {
            if (array_key_exists($k, $o)) $this->line($lines, $k, $o[$k]);
        }

        // environment: массив → строка, строку оставляем как есть (уже expandAliasesInOptions сделал)
        if (isset($o['environment'])) {
            $env = $o['environment'];
            if (is_array($env)) {
                $pairs = [];
                foreach ($env as $k => $v) {
                    $v = (string)$v;
                    $v = str_replace('"', '\"', $v);
                    $pairs[] = $k.'="'.$v.'"';
                }
                $env = implode(',', $pairs);
            }
            $this->line($lines, 'environment', $env);
        }

        $lines[] = '';
        return implode("\n", $lines);
    }


    /** INI для [group:<name>] */
    protected function buildGroup(string $group, array $programNames): string
    {
        $lines = [];
        $lines[] = "[group:{$group}]";
        $lines[] = 'programs=' . implode(',', $programNames);
        $lines[] = 'priority=999';
        $lines[] = '';
        return implode("\n", $lines);
    }

    protected function line(array &$lines, string $k, $v, bool $required = false): void
    {
        if ($v === null || $v === '') {
            if ($required) {
                throw new \RuntimeException("Missing required option '{$k}' for supervisor program");
            }
            return;
        }
        // булевы в ini как true/false
        if (is_bool($v)) $v = $v ? 'true' : 'false';
        $lines[] = "{$k}={$v}";
    }

    protected function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . uniqid('', true);
        if (file_put_contents($tmp, $content) === false) {
            throw new \RuntimeException("Cannot write temp file: {$tmp}");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Cannot move {$tmp} to {$path}");
        }
    }
}
