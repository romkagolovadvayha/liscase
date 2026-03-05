using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using UnityEngine;
using Network;

namespace Oxide.Plugins
{
    [Info("Map URL Proxy", "Prostoj", "1.0.0")]
    [Description("Подменяет URL карты на CDN/зеркало для доступности из всех регионов")]
    public class MapUrlProxy : RustPlugin
    {
        #region Configuration

        private Configuration _config;
        private int _mapDownloadErrorCount = 0;
        private int _currentProxyIndex = 0; // Индекс текущего прокси URL
        private ProxyErrorStats _errorStats; // Статистика ошибок по прокси

        private class ProxyErrorStats
        {
            [JsonProperty(PropertyName = "Ошибки по прокси (индекс -> количество)")]
            public Dictionary<int, int> ProxyErrors = new Dictionary<int, int>();
            
            [JsonProperty(PropertyName = "Всего ошибок")]
            public int TotalErrors = 0;
        }

        private class Configuration
        {
            [JsonProperty(PropertyName = "Включить подмену URL карты")]
            public bool Enabled = true;

            [JsonProperty(PropertyName = "Оригинальный URL карты (URL с русского хоста)")]
            public string OriginalMapUrl = "https://storage.prostoj.store/server-maps/procedural_4000_Il7hSadhRUymo_BAmXlCIw.map";

            [JsonProperty(PropertyName = "Проксированные URL карты (CDN/зеркала, будут чередоваться при ошибках)")]
            public List<string> ProxyUrls = new List<string>
            {
                "https://cdn2.mapstr.gg/4cd40a8e/procedural_4000_Il7hSadhRUymo_BAmXlCIw.map",
                "https://maps.rustmaps.com/273/6d1f18df7db44c28a4ee1fb1411ba00a/procedural_4000_Il7hSadhRUymo_BAmXlCIw.map"
            };

            [JsonProperty(PropertyName = "Логировать изменения URL")]
            public bool LogUrlChanges = true;

            [JsonProperty(PropertyName = "Автоматически менять URL при ошибке загрузки карты")]
            public bool AutoChangeOnError = true;

            [JsonProperty(PropertyName = "Количество ошибок для смены URL (0 = сразу)")]
            public int ErrorThreshold = 1;
        }

        protected override void LoadDefaultConfig()
        {
            _config = new Configuration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                _config = Config.ReadObject<Configuration>();
                if (_config == null)
                {
                    LoadDefaultConfig();
                }
                else
                {
                    SaveConfig();
                }
            }
            catch (Exception ex)
            {
                PrintError($"Ошибка загрузки конфигурации: {ex.Message}");
                LoadDefaultConfig();
            }
        }

        protected override void SaveConfig()
        {
            Config.WriteObject(_config, true);
        }

        private void LoadData()
        {
            _errorStats = Interface.Oxide.DataFileSystem.ReadObject<ProxyErrorStats>(Name);
            if (_errorStats == null)
            {
                _errorStats = new ProxyErrorStats();
                SaveData();
            }
        }

        private void SaveData()
        {
            Interface.Oxide.DataFileSystem.WriteObject(Name, _errorStats);
        }

        #endregion

        #region Oxide Hooks

        private void Init()
        {
            LoadData();
            
            if (!_config.Enabled)
            {
                PrintWarning("MapUrlProxy отключен в конфигурации");
                return;
            }

            // Проверяем конфигурацию
            if (string.IsNullOrEmpty(_config.OriginalMapUrl))
            {
                PrintError("Оригинальный URL карты не указан в конфигурации!");
                return;
            }

            if (_config.ProxyUrls == null || _config.ProxyUrls.Count == 0)
            {
                PrintError("Список проксированных URL пуст! Добавьте хотя бы один прокси URL в конфигурацию.");
                return;
            }

            // Фильтруем пустые URL из списка прокси
            _config.ProxyUrls = _config.ProxyUrls.Where(url => !string.IsNullOrEmpty(url)).ToList();

            if (_config.ProxyUrls.Count == 0)
            {
                PrintError("Все проксированные URL пусты! Добавьте хотя бы один валидный прокси URL.");
                return;
            }

            string currentUrl = ConVar.Server.levelurl;
            
            if (string.IsNullOrEmpty(currentUrl))
            {
                PrintWarning("URL карты в ConVar.Server.levelurl пуст.");
            }

            // Определяем текущий индекс прокси на основе текущего URL
            _currentProxyIndex = GetCurrentProxyIndex(currentUrl);

            if (_config.LogUrlChanges)
            {
                Puts($"Оригинальный URL: {_config.OriginalMapUrl}");
                Puts($"Доступно прокси URL: {_config.ProxyUrls.Count}");
                Puts($"Текущий URL: {currentUrl ?? "не установлен"}");
                if (_currentProxyIndex >= 0)
                {
                    Puts($"Используется прокси #{_currentProxyIndex + 1}: {_config.ProxyUrls[_currentProxyIndex]}");
                }
            }

            // Если автосмена при ошибке выключена, применяем первый прокси сразу
            if (!_config.AutoChangeOnError)
            {
                ApplyNextProxy();
            }
        }

        private int GetCurrentProxyIndex(string currentUrl)
        {
            if (string.IsNullOrEmpty(currentUrl))
            {
                return -1;
            }

            // Проверяем, является ли текущий URL одним из прокси
            for (int i = 0; i < _config.ProxyUrls.Count; i++)
            {
                if (currentUrl.Equals(_config.ProxyUrls[i], StringComparison.OrdinalIgnoreCase))
                {
                    return i;
                }
            }

            // Если текущий URL не является прокси, возвращаем -1
            return -1;
        }

        private void OnClientDisconnect(Network.Connection connection, string reason)
        {
            // Обрабатываем отключение на уровне соединения (раньше, чем OnPlayerDisconnected)
            if (!_config.Enabled || !_config.AutoChangeOnError || connection == null)
            {
                return;
            }

            // Логируем все отключения для отладки
            if (_config.LogUrlChanges)
            {
                Puts($"[DEBUG] Соединение отключено. Причина: {reason ?? "null"}");
            }

            // Проверяем, является ли причина отключения ошибкой загрузки карты
            if (IsMapDownloadError(reason))
            {
                HandleMapDownloadError(reason);
            }
        }

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            if (!_config.Enabled || !_config.AutoChangeOnError)
            {
                return;
            }

            // Логируем все отключения для отладки
            if (_config.LogUrlChanges)
            {
                Puts($"[DEBUG] Игрок {player?.displayName ?? "Unknown"} отключился. Причина: {reason ?? "null"}");
            }

            // Проверяем, является ли причина отключения ошибкой загрузки карты
            if (IsMapDownloadError(reason))
            {
                HandleMapDownloadError(reason);
            }
        }

        private void HandleMapDownloadError(string reason)
        {
            _mapDownloadErrorCount++;
            _errorStats.TotalErrors++;

            // Увеличиваем счетчик ошибок для текущего прокси
            if (_currentProxyIndex >= 0 && _currentProxyIndex < _config.ProxyUrls.Count)
            {
                if (!_errorStats.ProxyErrors.ContainsKey(_currentProxyIndex))
                {
                    _errorStats.ProxyErrors[_currentProxyIndex] = 0;
                }
                _errorStats.ProxyErrors[_currentProxyIndex]++;
                SaveData();
            }

            string currentUrl = ConVar.Server.levelurl;

            if (_config.LogUrlChanges)
            {
                Puts($"⚠️ ОБНАРУЖЕНА ОШИБКА ЗАГРУЗКИ КАРТЫ: {reason}");
                Puts($"Текущий URL: {currentUrl ?? "не установлен"}");
                if (_currentProxyIndex >= 0)
                {
                    int errorsOnProxy = _errorStats.ProxyErrors.ContainsKey(_currentProxyIndex) ? _errorStats.ProxyErrors[_currentProxyIndex] : 0;
                    Puts($"Ошибок на прокси #{_currentProxyIndex + 1}: {errorsOnProxy}");
                }
                Puts($"Счетчик ошибок: {_mapDownloadErrorCount}/{_config.ErrorThreshold}");
                Puts($"Всего ошибок за все время: {_errorStats.TotalErrors}");
            }

            // Если достигнут порог ошибок или порог = 0 (сразу менять)
            if (_config.ErrorThreshold == 0 || _mapDownloadErrorCount >= _config.ErrorThreshold)
            {
                if (_config.LogUrlChanges)
                {
                    Puts("🔄 Переключаю на следующий прокси URL из-за ошибок загрузки карты...");
                }

                ApplyNextProxy();
                _mapDownloadErrorCount = 0; // Сбрасываем счетчик после смены URL
            }
        }

        private bool IsMapDownloadError(string reason)
        {
            if (string.IsNullOrEmpty(reason))
            {
                return false;
            }

            string lowerReason = reason.ToLower();

            // Проверяем различные варианты текста ошибки загрузки карты
            return lowerReason.Contains("couldn't download level") ||
                   lowerReason.Contains("could not download level") ||
                   lowerReason.Contains("failed to download level") ||
                   lowerReason.Contains("download level failed") ||
                   lowerReason.Contains("level download error") ||
                   lowerReason.Contains("не удалось загрузить уровень") ||
                   lowerReason.Contains("ошибка загрузки уровня") ||
                   (lowerReason.Contains("map download") && (lowerReason.Contains("fail") || lowerReason.Contains("error"))) ||
                   // Дополнительные варианты
                   lowerReason.StartsWith("couldn't download level") ||
                   lowerReason.Contains("cannot resolve destination host") ||
                   lowerReason.Contains("cannot resolve") ||
                   lowerReason.Contains("destination host") ||
                   // Проверяем, содержит ли имя карты (procedural_4000_...) и ошибку
                   (lowerReason.Contains("procedural_") && (lowerReason.Contains("cannot") || lowerReason.Contains("resolve") || lowerReason.Contains("error") || lowerReason.Contains("fail")));
        }

        #endregion

        #region Functions


        private void ApplyNextProxy()
        {
            if (!_config.Enabled || _config.ProxyUrls == null || _config.ProxyUrls.Count == 0)
            {
                PrintWarning("Невозможно применить прокси: список прокси URL пуст.");
                return;
            }

            // Переходим к следующему прокси URL (циклически)
            _currentProxyIndex = (_currentProxyIndex + 1) % _config.ProxyUrls.Count;
            string nextProxyUrl = _config.ProxyUrls[_currentProxyIndex];

            if (string.IsNullOrEmpty(nextProxyUrl))
            {
                PrintWarning($"Прокси URL #{_currentProxyIndex + 1} пуст. Пропускаю.");
                return;
            }

            try
            {
                string currentUrl = ConVar.Server.levelurl;
                
                // Применяем новый URL
                ConVar.Server.levelurl = nextProxyUrl;

                if (_config.LogUrlChanges)
                {
                    Puts($"URL карты изменен:");
                    if (!string.IsNullOrEmpty(currentUrl))
                    {
                        Puts($"  Предыдущий: {currentUrl}");
                    }
                    Puts($"  Новый (прокси #{_currentProxyIndex + 1}): {nextProxyUrl}");
                }
            }
            catch (Exception ex)
            {
                PrintError($"Ошибка при применении прокси URL: {ex.Message}");
            }
        }


        #endregion

        #region Commands

        [ChatCommand("mapurl")]
        private void CmdMapUrl(BasePlayer player, string command, string[] args)
        {
            if (player == null || !player.IsAdmin)
            {
                SendReply(player, "У вас нет прав для использования этой команды");
                return;
            }

            string currentUrl = ConVar.Server.levelurl;
            
            if (args.Length == 0)
            {
                SendReply(player, $"Текущий URL карты: {currentUrl ?? "не установлен"}");
                SendReply(player, $"Оригинальный URL: {_config.OriginalMapUrl}");
                SendReply(player, $"Текущий прокси: {(_currentProxyIndex >= 0 ? $"#{_currentProxyIndex + 1}" : "не используется")}");
                SendReply(player, $"Счетчик ошибок: {_mapDownloadErrorCount}/{_config.ErrorThreshold}");
                return;
            }

            if (args[0].ToLower() == "reload")
            {
                LoadConfig();
                _mapDownloadErrorCount = 0;
                _currentProxyIndex = GetCurrentProxyIndex(ConVar.Server.levelurl);
                SendReply(player, "Конфигурация перезагружена, счетчики сброшены");
                return;
            }

            if (args[0].ToLower() == "reset")
            {
                ConVar.Server.levelurl = _config.OriginalMapUrl;
                _currentProxyIndex = -1;
                _mapDownloadErrorCount = 0;
                SendReply(player, $"URL карты сброшен на оригинальный: {_config.OriginalMapUrl}");
                return;
            }

            if (args[0].ToLower() == "next")
            {
                ApplyNextProxy();
                SendReply(player, $"Переключено на прокси #{_currentProxyIndex + 1}: {_config.ProxyUrls[_currentProxyIndex]}");
                return;
            }

            if (args[0].ToLower() == "stats")
            {
                SendReply(player, $"Статистика:");
                SendReply(player, $"  Оригинальный URL: {_config.OriginalMapUrl}");
                SendReply(player, $"  Текущий URL: {currentUrl ?? "не установлен"}");
                SendReply(player, $"  Текущий прокси: {(_currentProxyIndex >= 0 ? $"#{_currentProxyIndex + 1} ({_config.ProxyUrls[_currentProxyIndex]})" : "не используется")}");
                SendReply(player, $"  Счетчик ошибок: {_mapDownloadErrorCount}/{_config.ErrorThreshold}");
                SendReply(player, $"  Всего ошибок за все время: {_errorStats.TotalErrors}");
                SendReply(player, $"  Всего прокси: {_config.ProxyUrls.Count}");
                SendReply(player, $"  Автосмена при ошибке: {(_config.AutoChangeOnError ? "Включена" : "Выключена")}");
                SendReply(player, $"");
                SendReply(player, $"Ошибки по прокси:");
                for (int i = 0; i < _config.ProxyUrls.Count; i++)
                {
                    int errors = _errorStats.ProxyErrors.ContainsKey(i) ? _errorStats.ProxyErrors[i] : 0;
                    string marker = (i == _currentProxyIndex) ? "← текущий" : "";
                    SendReply(player, $"    Прокси #{i + 1}: {errors} ошибок {marker}");
                }
                return;
            }

            if (args[0].ToLower() == "clearstats")
            {
                _errorStats = new ProxyErrorStats();
                SaveData();
                _mapDownloadErrorCount = 0;
                SendReply(player, "Статистика ошибок очищена");
                return;
            }

            SendReply(player, "Использование: /mapurl [reload|reset|next|stats|clearstats]");
        }

        #endregion
    }
}

