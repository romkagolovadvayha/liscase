<?php

namespace backend\components;

use Yii;

/**
 * Парсинг вывода o.plugins (Oxide/uMod) и извлечение версий из .cs в проекте.
 */
class PluginsComparisonHelper
{
    /** Ключ кэша для результатов RCON o.plugins */
    const CACHE_KEY_PLUGINS_DATA = 'backend_plugins_comparison_data';
    /** Время жизни кэша (секунды) */
    const CACHE_TTL = 60;

    /**
     * Парсит вывод команды o.plugins (Oxide).
     * Формат строк: "Plugin Name (1.0.0) by Author" или "Plugin Name (1.0.0)" или "Name (Legacy) (1.0.0) by X".
     * Берётся последнее вхождение (X.Y.Z) в строке как версия, остальное слева — название плагина.
     * @param string $output
     * @return array [ 'Plugin Name' => '1.0.0', ... ]
     */
    public static function parseOPluginsOutput(string $output): array
    {
        $list = [];
        $lines = preg_split('/\r\n|\r|\n/', $output);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Найти все вхождения версии в скобках (1.0.0), берём последнее
            if (preg_match_all('/\(([\d.]+)\)/', $line, $matches) && !empty($matches[1])) {
                $version = end($matches[1]);
                // Убираем с конца строки: пробелы, последнюю скобку с версией и опционально " by ..."
                $name = trim(preg_replace('/\s*\([\d.]+\)\s*(?:\s+by\s+.*)?$/i', '', $line));
                // Oxide: "  01 \"Plugin Name\"" -> убрать ведущий номер и кавычки, оставить только название
                $name = preg_replace('/^\s*\d+\s*/', '', $name);
                $name = trim($name, " \t\n\r\0\x0B\"");
                if ($name !== '') {
                    $list[$name] = $version;
                }
            }
        }
        return $list;
    }

    /**
     * Сканирует каталог plugins в корне проекта (liscase/plugins) и извлекает версии из атрибута [Info("Name", "Author", "Version")].
     * @return array [ 'Plugin Name' => '1.0.0', ... ]
     */
    public static function getProjectPluginVersions(): array
    {
        // liscase/plugins — два уровня вверх от backend/components
        $pluginsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins';
        if (!is_dir($pluginsDir)) {
            return [];
        }
        $versions = [];
        $files = glob($pluginsDir . DIRECTORY_SEPARATOR . '*.cs');
        if (!$files) {
            return [];
        }
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            // [Info("Name", "Author", "Version")] или [Info("Name", "Author", "Version", ...)]
            if (preg_match('/\[\s*Info\s*\(\s*["\']([^"\']+)["\']\s*,\s*["\'][^"\']*["\']\s*,\s*["\']([^"\']+)["\']/', $content, $m)) {
                $name = trim($m[1]);
                $version = trim($m[2]);
                $versions[$name] = $version;
            }
        }
        return $versions;
    }

    /**
     * Пути к .cs файлам плагинов в проекте по отображаемому имени.
     * @return array [ 'Plugin Name' => '/absolute/path/to/PluginName.cs', ... ]
     */
    public static function getProjectPluginFiles(): array
    {
        $pluginsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins';
        if (!is_dir($pluginsDir)) {
            return [];
        }
        $files = glob($pluginsDir . DIRECTORY_SEPARATOR . '*.cs');
        if (!$files) {
            return [];
        }
        $result = [];
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            if (preg_match('/\[\s*Info\s*\(\s*["\']([^"\']+)["\']\s*,\s*["\'][^"\']*["\']\s*,\s*["\']([^"\']+)["\']/', $content, $m)) {
                $name = trim($m[1]);
                $path = realpath($file);
                if ($path !== false) {
                    $result[$name] = $path;
                }
            }
        }
        return $result;
    }

    /**
     * Найти локальный путь к .cs по имени плагина (точное или нормализованное).
     * @return string|null абсолютный путь к файлу или null
     */
    public static function resolvePluginFilePath(string $pluginName): ?string
    {
        $map = self::getProjectPluginFiles();
        if (isset($map[$pluginName])) {
            return $map[$pluginName];
        }
        $key = strtolower(preg_replace('/\s++/', ' ', trim($pluginName)));
        if ($key === '') {
            return null;
        }
        foreach ($map as $name => $path) {
            if (strtolower(preg_replace('/\s++/', ' ', $name)) === $key) {
                return $path;
            }
        }
        return null;
    }
}
