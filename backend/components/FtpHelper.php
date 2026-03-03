<?php

namespace backend\components;

use common\models\servers\Servers;
use Yii;

/**
 * Хелпер для операций с FTP сервером (использует ext-ftp).
 */
class FtpHelper
{
    /** @var resource|null */
    private $connection;

    /** @var Servers */
    private $server;

    public function __construct(Servers $server)
    {
        $this->server = $server;
    }

    /**
     * Подключение к FTP.
     * @return bool
     */
    public function connect(): bool
    {
        if (!extension_loaded('ftp')) {
            Yii::warning('PHP extension ext-ftp is not loaded', __METHOD__);
            return false;
        }
        $host = $this->server->getFtpHost();
        $port = (int)$this->server->ftp_port ?: 21;
        $this->connection = @ftp_connect($host, $port, 10);
        if (!$this->connection) {
            return false;
        }
        if (!@ftp_login($this->connection, $this->server->ftp_login, $this->server->ftp_password)) {
            $this->disconnect();
            return false;
        }
        @ftp_pasv($this->connection, true);
        return true;
    }

    /**
     * Преобразует пользовательский путь (относительно корня FTP) в полный путь на сервере.
     */
    private function resolvePath(string $userPath): string
    {
        $root = $this->server->getFtpRootPath();
        $path = $this->normalizePath($userPath);
        $path = ($path === '/') ? '' : ltrim($path, '/');
        if ($root === '') {
            return $path === '' ? '/' : '/' . $path;
        }
        return '/' . $root . ($path === '' ? '' : '/' . $path);
    }

    /**
     * Преобразует полный путь на FTP в пользовательский (относительно корня).
     */
    private function toUserPath(string $fullPath): string
    {
        $root = $this->server->getFtpRootPath();
        if ($root === '') {
            return $this->normalizePath($fullPath);
        }
        $fullPath = str_replace('\\', '/', trim($fullPath));
        $root = '/' . trim($root, '/');
        if (strpos($fullPath, $root) === 0) {
            $suffix = substr($fullPath, strlen($root));
            return $suffix === '' || $suffix === false ? '/' : '/' . ltrim($suffix, '/');
        }
        return $fullPath === '' ? '/' : $fullPath;
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Список содержимого директории.
     * @param string $path Путь на сервере (например / или /oxide)
     * @return array[] [ ['name' => ..., 'path' => ..., 'dir' => bool, 'size' => int], ... ]
     */
    public function listDir(string $path): array
    {
        if (!$this->connection) {
            return [];
        }
        $userPath = $this->normalizePath($path);
        $remotePath = $this->resolvePath($path);
        $raw = @ftp_rawlist($this->connection, $remotePath);
        if ($raw === false) {
            return [];
        }
        $result = [];
        foreach ($raw as $line) {
            $info = $this->parseRawListLine($line, $userPath, $remotePath);
            if ($info !== null && $info['name'] !== '.' && $info['name'] !== '..') {
                $result[] = $info;
            }
        }
        usort($result, function ($a, $b) {
            if ($a['dir'] !== $b['dir']) {
                return $a['dir'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        return $result;
    }

    /**
     * Скачать файл во временный путь и вернуть содержимое или путь.
     * @param string $remotePath
     * @param bool $returnPath Если true — вернуть путь к временному файлу (для отдачи в response)
     * @return string|false содержимое файла или путь к temp файлу, false при ошибке
     */
    public function download(string $remotePath, bool $returnPath = false)
    {
        if (!$this->connection) {
            return false;
        }
        $remotePath = $this->resolvePath($remotePath);
        if ($this->isDir($remotePath)) {
            return false;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ftp_');
        if (@ftp_get($this->connection, $tmp, $remotePath, FTP_BINARY)) {
            if ($returnPath) {
                return $tmp;
            }
            $content = file_get_contents($tmp);
            @unlink($tmp);
            return $content;
        }
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        return false;
    }

    /**
     * Загрузить файл на сервер.
     * @param string $localPath Локальный путь к файлу
     * @param string $remotePath Удалённый путь
     * @return bool
     */
    public function upload(string $localPath, string $remotePath): bool
    {
        if (!$this->connection || !is_readable($localPath)) {
            return false;
        }
        $remotePath = $this->resolvePath($remotePath);
        $this->ensureDir($remotePath);
        return @ftp_put($this->connection, $remotePath, $localPath, FTP_BINARY);
    }

    /**
     * Удалить файл или пустую директорию.
     * @param string $remotePath
     * @return bool
     */
    public function delete(string $remotePath): bool
    {
        if (!$this->connection) {
            return false;
        }
        $remotePath = $this->resolvePath($remotePath);
        if ($this->isDir($remotePath)) {
            return @ftp_rmdir($this->connection, $remotePath);
        }
        return @ftp_delete($this->connection, $remotePath);
    }

    /**
     * Прочитать содержимое текстового файла.
     * @param string $remotePath
     * @return string|false
     */
    public function getFileContent(string $remotePath)
    {
        return $this->download($remotePath, false);
    }

    /**
     * Записать содержимое в файл (перезапись).
     * @param string $remotePath
     * @param string $content
     * @return bool
     */
    public function putFileContent(string $remotePath, string $content): bool
    {
        if (!$this->connection) {
            return false;
        }
        $remotePath = $this->resolvePath($remotePath);
        $this->ensureDir($remotePath);
        $tmp = tempnam(sys_get_temp_dir(), 'ftp_');
        file_put_contents($tmp, $content);
        $ok = @ftp_put($this->connection, $remotePath, $tmp, FTP_BINARY);
        @unlink($tmp);
        return $ok;
    }

    /**
     * Создать директорию (родительские создаются при необходимости).
     * @param string $remotePath
     * @return bool
     */
    public function createDir(string $remotePath): bool
    {
        if (!$this->connection) {
            return false;
        }
        $remotePath = $this->resolvePath($remotePath);
        return $this->mkdirRecursive($remotePath);
    }

    private function ensureDir(string $remotePath): void
    {
        $dir = dirname($remotePath);
        if ($dir !== '.' && $dir !== '') {
            $this->mkdirRecursive($dir);
        }
    }

    private function mkdirRecursive(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return true;
        }
        $parts = explode('/', $path);
        $current = '';
        foreach ($parts as $p) {
            $current .= '/' . $p;
            if (!$this->dirExists($current)) {
                if (!@ftp_mkdir($this->connection, $current)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function dirExists(string $path): bool
    {
        $parent = dirname($path);
        $name = basename($path);
        if ($parent === '.' || $parent === '') {
            $list = @ftp_nlist($this->connection, '/');
        } else {
            $list = @ftp_nlist($this->connection, $parent);
        }
        if ($list === false) {
            return false;
        }
        foreach ($list as $item) {
            if (basename($item) === $name) {
                return true;
            }
        }
        return false;
    }

    private function isDir(string $path): bool
    {
        $current = @ftp_pwd($this->connection);
        if ($current === false) {
            return false;
        }
        if (@ftp_chdir($this->connection, $path)) {
            @ftp_chdir($this->connection, $current);
            return true;
        }
        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    private function parseRawListLine(string $line, string $userBasePath, string $remoteBasePath): ?array
    {
        $chunks = preg_split('/\s+/', $line, 9, PREG_SPLIT_NO_EMPTY);
        if (count($chunks) < 9) {
            return null;
        }
        $name = $chunks[8];
        $name = basename($name);
        $isDir = isset($chunks[0][0]) && $chunks[0][0] === 'd';
        $size = (int)($chunks[4] ?? 0);
        $userPath = rtrim($userBasePath, '/') . '/' . $name;
        if ($userPath === '/') {
            $userPath = '/' . $name;
        }
        return [
            'name' => $name,
            'path' => $userPath,
            'dir' => $isDir,
            'size' => $size,
        ];
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
