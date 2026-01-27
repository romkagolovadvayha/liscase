using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("DisableTurrets", "Auto", "1.0.0")]
    [Description("Уничтожает все турели, установленные игроками на сервере")]
    public class DisableTurrets : RustPlugin
    {
        private Configuration _config;
        private Timer _checkTimer;

        #region Config

        private class Configuration
        {
            [JsonProperty(PropertyName = "Включить плагин")]
            public bool Enabled = true;

            [JsonProperty(PropertyName = "Автоматически уничтожать турели при размещении")]
            public bool AutoDisableOnPlace = true;

            [JsonProperty(PropertyName = "Период проверки турелей (секунды, 0 = отключено)")]
            public float CheckInterval = 0f;

            [JsonProperty(PropertyName = "Логировать уничтожение турелей")]
            public bool LogDisabling = true;

            [JsonProperty(PropertyName = "Уведомлять игроков при уничтожении их турелей")]
            public bool NotifyPlayers = false;
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
            if (!_config.Enabled)
            {
                PrintWarning("[DisableTurrets] Плагин отключен в конфигурации.");
                return;
            }

            // Регистрация прав
            permission.RegisterPermission("disableturrets.admin", this);

            // Уничтожаем все существующие турели
            DestroyAllTurrets();

            // Запускаем периодическую проверку, если включена
            if (_config.CheckInterval > 0)
            {
                StartPeriodicCheck();
            }

            PrintWarning($"[DisableTurrets] Плагин загружен. Уничтожено турелей: {GetTurretCount()}");
        }

        private void Unload()
        {
            _checkTimer?.Destroy();
            _checkTimer = null;
        }

        private void OnEntityBuilt(Planner plan, GameObject go)
        {
            if (!_config.Enabled || !_config.AutoDisableOnPlace) return;

            var entity = go.GetComponent<BaseEntity>();
            if (entity == null) return;

            var turret = entity as AutoTurret;
            if (turret != null)
            {
                NextTick(() =>
                {
                    if (turret != null && !turret.IsDestroyed)
                    {
                        DestroyTurret(turret, plan.GetOwnerPlayer());
                    }
                });
            }
        }

        #endregion

        #region Turret Management

        private void DestroyAllTurrets()
        {
            var turrets = UnityEngine.Object.FindObjectsOfType<AutoTurret>();
            int destroyedCount = 0;

            foreach (var turret in turrets)
            {
                if (turret == null || turret.IsDestroyed) continue;

                DestroyTurret(turret, null);
                destroyedCount++;
            }

            if (_config.LogDisabling && destroyedCount > 0)
            {
                Puts($"[DisableTurrets] Уничтожено турелей: {destroyedCount}");
            }
        }

        private void DestroyTurret(AutoTurret turret, BasePlayer player)
        {
            if (turret == null || turret.IsDestroyed) return;

            var ownerId = turret.OwnerID;
            var ownerName = ownerId > 0 ? GetPlayerName(ownerId) : "Unknown";

            // Уничтожаем турель
            turret.Kill();

            if (_config.LogDisabling)
            {
                Puts($"[DisableTurrets] Уничтожена турель. Владелец: {ownerName} (ID: {ownerId})");
            }

            // Уведомляем игрока, если он онлайн
            if (_config.NotifyPlayers && player != null && player.IsConnected)
            {
                player.ChatMessage("<color=#ff6b6b>💥</color> Ваша турель была автоматически уничтожена.");
            }
            else if (_config.NotifyPlayers && ownerId > 0)
            {
                var owner = BasePlayer.FindByID(ownerId);
                if (owner != null && owner.IsConnected)
                {
                    owner.ChatMessage("<color=#ff6b6b>💥</color> Ваша турель была автоматически уничтожена.");
                }
            }
        }

        private void StartPeriodicCheck()
        {
            _checkTimer?.Destroy();
            _checkTimer = timer.Every(_config.CheckInterval, () =>
            {
                if (_config.Enabled)
                {
                    DestroyAllTurrets();
                }
            });
        }

        private int GetTurretCount()
        {
            var turrets = UnityEngine.Object.FindObjectsOfType<AutoTurret>();
            return turrets.Count(t => t != null && !t.IsDestroyed);
        }

        private string GetPlayerName(ulong userId)
        {
            var player = BasePlayer.FindByID(userId);
            if (player != null && player.IsConnected)
            {
                return player.displayName;
            }

            // Пытаемся получить имя из кэша или базы данных
            var cachedName = covalence.Players.FindPlayerById(userId.ToString());
            return cachedName?.Name ?? $"Player_{userId}";
        }

        #endregion

        #region Commands

        [ChatCommand("disableturrets")]
        private void CmdDisableTurrets(BasePlayer player, string command, string[] args)
        {
            if (!permission.UserHasPermission(player.UserIDString, "disableturrets.admin"))
            {
                player.ChatMessage("<color=#ff6b6b>❌</color> У вас нет прав для использования этой команды.");
                return;
            }

            if (args.Length == 0)
            {
                player.ChatMessage("<color=#4ecdc4>Использование:</color>");
                player.ChatMessage("  /disableturrets all - Уничтожить все турели");
                player.ChatMessage("  /disableturrets count - Показать количество турелей");
                player.ChatMessage("  /disableturrets enable - Включить плагин");
                player.ChatMessage("  /disableturrets disable - Выключить плагин");
                player.ChatMessage("  /disableturrets status - Показать статус");
                return;
            }

            switch (args[0].ToLower())
            {
                case "all":
                    var countBefore = GetTurretCount();
                    DestroyAllTurrets();
                    var countAfter = GetTurretCount();
                    player.ChatMessage($"<color=#6BCF7F>✓</color> Уничтожены все турели. Всего турелей на сервере: {countAfter}");
                    break;

                case "count":
                    var totalTurrets = GetTurretCount();
                    player.ChatMessage($"<color=#4ecdc4>📊</color> Всего турелей на сервере: {totalTurrets}");
                    break;

                case "enable":
                    _config.Enabled = true;
                    SaveConfig();
                    if (_config.CheckInterval > 0)
                    {
                        StartPeriodicCheck();
                    }
                    player.ChatMessage("<color=#6BCF7F>✓</color> Плагин включен.");
                    break;

                case "disable":
                    _config.Enabled = false;
                    SaveConfig();
                    _checkTimer?.Destroy();
                    _checkTimer = null;
                    player.ChatMessage("<color=#ff6b6b>✗</color> Плагин выключен.");
                    break;

                case "status":
                    var status = _config.Enabled ? "<color=#6BCF7F>Включен</color>" : "<color=#ff6b6b>Выключен</color>";
                    player.ChatMessage($"<color=#4ecdc4>📋</color> Статус плагина: {status}");
                    player.ChatMessage($"  Автоуничтожение при размещении: {(_config.AutoDisableOnPlace ? "Да" : "Нет")}");
                    player.ChatMessage($"  Периодическая проверка: {(_config.CheckInterval > 0 ? $"{_config.CheckInterval} сек" : "Отключена")}");
                    player.ChatMessage($"  Логирование: {(_config.LogDisabling ? "Включено" : "Выключено")}");
                    break;

                default:
                    player.ChatMessage("<color=#ff6b6b>❌</color> Неизвестная команда. Используйте /disableturrets для справки.");
                    break;
            }
        }

        [ConsoleCommand("disableturrets.all")]
        private void CmdConsoleDisableAll(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            var countBefore = GetTurretCount();
            DestroyAllTurrets();
            var countAfter = GetTurretCount();
            arg.ReplyWith($"[DisableTurrets] Уничтожены все турели. Всего турелей на сервере: {countAfter}");
        }

        [ConsoleCommand("disableturrets.count")]
        private void CmdConsoleCount(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            var totalTurrets = GetTurretCount();
            arg.ReplyWith($"[DisableTurrets] Всего турелей на сервере: {totalTurrets}");
        }

        [ConsoleCommand("disableturrets.enable")]
        private void CmdConsoleEnable(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            _config.Enabled = true;
            SaveConfig();
            if (_config.CheckInterval > 0)
            {
                StartPeriodicCheck();
            }
            arg.ReplyWith("[DisableTurrets] Плагин включен.");
        }

        [ConsoleCommand("disableturrets.disable")]
        private void CmdConsoleDisable(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            _config.Enabled = false;
            SaveConfig();
            _checkTimer?.Destroy();
            _checkTimer = null;
            arg.ReplyWith("[DisableTurrets] Плагин выключен.");
        }

        #endregion
    }
}

