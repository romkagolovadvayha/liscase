using System;
using Newtonsoft.Json;
using Oxide.Core;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("AlwaysDay", "CASHR#6906", "1.0.0")]
    [Description("Фиксирует время суток на сервере на заданном значении")]
    public class AlwaysDay : RustPlugin
    {
        private Configuration _config;
        private Timer _timeUpdateTimer;

        #region Config

        private class Configuration
        {
            [JsonProperty(PropertyName = "Включить плагин")]
            public bool Enabled = true;

            [JsonProperty(PropertyName = "Время суток (часы, 0-24)")]
            public float TimeOfDay = 14f;

            [JsonProperty(PropertyName = "Период обновления времени (секунды)")]
            public float UpdateInterval = 60f;

            [JsonProperty(PropertyName = "Плавное изменение времени")]
            public bool SmoothTime = false;

            [JsonProperty(PropertyName = "Скорость изменения времени (если плавное включено, 0 = остановлено)")]
            public float TimeScale = 0f;

            [JsonProperty(PropertyName = "Показывать сообщения в логах")]
            public bool ShowLogs = false;
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                _config = Config.ReadObject<Configuration>();
                if (_config == null) throw new Exception();
                SaveConfig();
            }
            catch
            {
                PrintError("Конфигурация повреждена. Загружены значения по умолчанию.");
                LoadDefaultConfig();
            }
        }

        protected override void SaveConfig()
        {
            Config.WriteObject(_config);
        }

        protected override void LoadDefaultConfig()
        {
            _config = new Configuration();
        }

        #endregion

        #region Oxide Hooks

        private void OnServerInitialized()
        {
            if (_config.Enabled)
            {
                StartTimeControl();
                PrintWarning($"[AlwaysDay] Плагин загружен. Время установлено на {_config.TimeOfDay:F1}ч.");
            }
            else
            {
                PrintWarning("[AlwaysDay] Плагин отключен в конфигурации.");
            }

            // Регистрация команд
            permission.RegisterPermission("alwaysday.admin", this);
        }

        private void Unload()
        {
            _timeUpdateTimer?.Destroy();
            _timeUpdateTimer = null;
        }

        #endregion

        #region Time Control

        private void StartTimeControl()
        {
            // Сразу устанавливаем время
            SetServerTime(_config.TimeOfDay);

            // Запускаем таймер для поддержания времени
            _timeUpdateTimer?.Destroy();
            float interval = Mathf.Max(1f, _config.UpdateInterval);
            
            _timeUpdateTimer = timer.Every(interval, () =>
            {
                if (_config.Enabled)
                {
                    SetServerTime(_config.TimeOfDay);
                    
                    if (_config.ShowLogs)
                    {
                        Puts($"[AlwaysDay] Время обновлено: {_config.TimeOfDay:F1}ч");
                    }
                }
            });

            // Устанавливаем скорость времени
            try
            {
                if (TOD_Sky.Instance != null && TOD_Sky.Instance.Components != null)
                {
                    // Останавливаем или устанавливаем скорость времени
                    if (_config.TimeScale <= 0f)
                    {
                        TOD_Sky.Instance.Components.Time.ProgressTime = false;
                    }
                    else
                    {
                        TOD_Sky.Instance.Components.Time.ProgressTime = true;
                        TOD_Sky.Instance.Components.Time.DayLengthInMinutes = 30f / _config.TimeScale;
                    }
                }
            }
            catch (Exception ex)
            {
                PrintWarning($"[AlwaysDay] Ошибка установки скорости времени: {ex.Message}");
            }
        }

        private void SetServerTime(float hour)
        {
            // Ограничиваем значение в пределах 0-24
            hour = Mathf.Clamp(hour, 0f, 24f);

            try
            {
                // Устанавливаем через ConVar
                ConVar.Env.time = hour;

                // Устанавливаем через TOD_Sky (Time Of Day)
                if (TOD_Sky.Instance != null)
                {
                    TOD_Sky.Instance.Cycle.Hour = hour;
                }
            }
            catch (Exception ex)
            {
                PrintError($"[AlwaysDay] Ошибка установки времени: {ex.Message}");
            }
        }

        #endregion

        #region Commands

        [ChatCommand("time")]
        private void CmdTime(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "alwaysday.admin"))
            {
                player.ChatMessage("<color=#ff6b6b>❌</color> У вас нет прав для использования этой команды");
                return;
            }

            if (args.Length < 1)
            {
                player.ChatMessage("<color=#4ecdc4>━━━━━━━ УПРАВЛЕНИЕ ВРЕМЕНЕМ ━━━━━━━</color>");
                player.ChatMessage($"<color=#ffd93d>Текущее время:</color> {_config.TimeOfDay:F1}ч ({GetTimeDescription(_config.TimeOfDay)})");
                player.ChatMessage($"<color=#ffd93d>Статус:</color> {(_config.Enabled ? "<color=#6BCF7F>Включено</color>" : "<color=#ff6b6b>Выключено</color>")}");
                player.ChatMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</color>");
                player.ChatMessage("<color=#ffd93d>/time set <часы></color> - Установить время (0-24)");
                player.ChatMessage("<color=#ffd93d>/time freeze</color> - Заморозить текущее время");
                player.ChatMessage("<color=#ffd93d>/time day</color> - Установить день (14ч)");
                player.ChatMessage("<color=#ffd93d>/time night</color> - Установить ночь (0ч)");
                player.ChatMessage("<color=#ffd93d>/time dawn</color> - Установить рассвет (6ч)");
                player.ChatMessage("<color=#ffd93d>/time dusk</color> - Установить закат (18ч)");
                player.ChatMessage("<color=#ffd93d>/time on</color> - Включить плагин");
                player.ChatMessage("<color=#ffd93d>/time off</color> - Выключить плагин");
                player.ChatMessage("<color=#ffd93d>/time normal</color> - Нормальная скорость времени");
                player.ChatMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</color>");
                return;
            }

            switch (args[0].ToLower())
            {
                case "set":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("<color=#ff6b6b>❌</color> Использование: /time set <часы>");
                        return;
                    }

                    if (float.TryParse(args[1], out float hours))
                    {
                        hours = Mathf.Clamp(hours, 0f, 24f);
                        _config.TimeOfDay = hours;
                        _config.Enabled = true;
                        SaveConfig();
                        SetServerTime(hours);
                        
                        player.ChatMessage($"<color=#6BCF7F>✓</color> Время установлено на <color=#ffd93d>{hours:F1}ч</color> ({GetTimeDescription(hours)})");
                        Broadcast($"<color=#4ecdc4>⏰</color> Администратор <color=#ffd93d>{player.displayName}</color> установил время на <color=#ffd93d>{hours:F1}ч</color>");
                    }
                    else
                    {
                        player.ChatMessage("<color=#ff6b6b>❌</color> Неверное значение. Используйте число от 0 до 24");
                    }
                    break;

                case "freeze":
                    float currentTime = TOD_Sky.Instance?.Cycle.Hour ?? 12f;
                    _config.TimeOfDay = currentTime;
                    _config.Enabled = true;
                    _config.TimeScale = 0f;
                    SaveConfig();
                    
                    // Останавливаем время
                    if (TOD_Sky.Instance?.Components?.Time != null)
                        TOD_Sky.Instance.Components.Time.ProgressTime = false;
                    
                    player.ChatMessage($"<color=#6BCF7F>✓</color> Время заморожено на <color=#ffd93d>{currentTime:F1}ч</color>");
                    Broadcast($"<color=#4ecdc4>❄️</color> Время заморожено на <color=#ffd93d>{currentTime:F1}ч</color>");
                    break;

                case "day":
                    _config.TimeOfDay = 14f;
                    _config.Enabled = true;
                    SaveConfig();
                    SetServerTime(14f);
                    
                    player.ChatMessage("<color=#6BCF7F>✓</color> Установлен <color=#ffd93d>день</color> (14:00)");
                    Broadcast("<color=#4ecdc4>☀️</color> Установлен постоянный <color=#ffd93d>день</color>");
                    break;

                case "night":
                    _config.TimeOfDay = 0f;
                    _config.Enabled = true;
                    SaveConfig();
                    SetServerTime(0f);
                    
                    player.ChatMessage("<color=#6BCF7F>✓</color> Установлена <color=#ffd93d>ночь</color> (00:00)");
                    Broadcast("<color=#4ecdc4>🌙</color> Установлена постоянная <color=#ffd93d>ночь</color>");
                    break;

                case "dawn":
                    _config.TimeOfDay = 6f;
                    _config.Enabled = true;
                    SaveConfig();
                    SetServerTime(6f);
                    
                    player.ChatMessage("<color=#6BCF7F>✓</color> Установлен <color=#ffd93d>рассвет</color> (06:00)");
                    Broadcast("<color=#4ecdc4>🌅</color> Установлен <color=#ffd93d>рассвет</color>");
                    break;

                case "dusk":
                    _config.TimeOfDay = 18f;
                    _config.Enabled = true;
                    SaveConfig();
                    SetServerTime(18f);
                    
                    player.ChatMessage("<color=#6BCF7F>✓</color> Установлен <color=#ffd93d>закат</color> (18:00)");
                    Broadcast("<color=#4ecdc4>🌇</color> Установлен <color=#ffd93d>закат</color>");
                    break;

                case "on":
                    _config.Enabled = true;
                    SaveConfig();
                    StartTimeControl();
                    
                    player.ChatMessage($"<color=#6BCF7F>✓</color> Плагин <color=#6BCF7F>включен</color>. Время: <color=#ffd93d>{_config.TimeOfDay:F1}ч</color>");
                    Broadcast("<color=#4ecdc4>⏰</color> Управление временем <color=#6BCF7F>включено</color>");
                    break;

                case "off":
                    _config.Enabled = false;
                    SaveConfig();
                    _timeUpdateTimer?.Destroy();
                    
                    // Включаем нормальное течение времени
                    if (TOD_Sky.Instance?.Components?.Time != null)
                    {
                        TOD_Sky.Instance.Components.Time.ProgressTime = true;
                        TOD_Sky.Instance.Components.Time.DayLengthInMinutes = 30f;
                    }
                    
                    player.ChatMessage("<color=#ff6b6b>✓</color> Плагин <color=#ff6b6b>выключен</color>. Время течет нормально.");
                    Broadcast("<color=#4ecdc4>⏰</color> Управление временем <color=#ff6b6b>выключено</color>");
                    break;

                case "normal":
                    _config.Enabled = false;
                    _config.TimeScale = 1f;
                    SaveConfig();
                    _timeUpdateTimer?.Destroy();
                    
                    // Включаем нормальное течение времени
                    if (TOD_Sky.Instance?.Components?.Time != null)
                    {
                        TOD_Sky.Instance.Components.Time.ProgressTime = true;
                        TOD_Sky.Instance.Components.Time.DayLengthInMinutes = 30f;
                    }
                    
                    player.ChatMessage("<color=#6BCF7F>✓</color> Время течет с <color=#ffd93d>нормальной</color> скоростью");
                    Broadcast("<color=#4ecdc4>⏰</color> Восстановлена нормальная скорость времени");
                    break;

                default:
                    player.ChatMessage("<color=#ff6b6b>❌</color> Неизвестная команда. Используйте <color=#ffd93d>/time</color> для справки");
                    break;
            }
        }

        private string GetTimeDescription(float hour)
        {
            if (hour >= 5f && hour < 7f) return "Рассвет 🌅";
            if (hour >= 7f && hour < 12f) return "Утро ☀️";
            if (hour >= 12f && hour < 17f) return "День ☀️";
            if (hour >= 17f && hour < 19f) return "Закат 🌇";
            if (hour >= 19f && hour < 22f) return "Вечер 🌆";
            return "Ночь 🌙";
        }

        private void Broadcast(string msg)
        {
            Server.Broadcast(msg);
        }

        #endregion

        #region Console Commands

        [ConsoleCommand("alwaysday.set")]
        private void CmdConsoleSet(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            if (!arg.HasArgs())
            {
                arg.ReplyWith("Использование: alwaysday.set <часы>");
                return;
            }

            if (float.TryParse(arg.Args[0], out float hours))
            {
                hours = Mathf.Clamp(hours, 0f, 24f);
                _config.TimeOfDay = hours;
                _config.Enabled = true;
                SaveConfig();
                SetServerTime(hours);
                
                arg.ReplyWith($"[AlwaysDay] Время установлено на {hours:F1}ч");
            }
            else
            {
                arg.ReplyWith("[AlwaysDay] Неверное значение");
            }
        }

        [ConsoleCommand("alwaysday.enable")]
        private void CmdConsoleEnable(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            _config.Enabled = true;
            SaveConfig();
            StartTimeControl();
            
            arg.ReplyWith($"[AlwaysDay] Включено. Время: {_config.TimeOfDay:F1}ч");
        }

        [ConsoleCommand("alwaysday.disable")]
        private void CmdConsoleDisable(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            _config.Enabled = false;
            SaveConfig();
            _timeUpdateTimer?.Destroy();
            
            // Включаем нормальное течение времени
            if (TOD_Sky.Instance?.Components?.Time != null)
            {
                TOD_Sky.Instance.Components.Time.ProgressTime = true;
                TOD_Sky.Instance.Components.Time.DayLengthInMinutes = 30f;
            }
            
            arg.ReplyWith("[AlwaysDay] Выключено. Время течет нормально.");
        }

        #endregion
    }
}

