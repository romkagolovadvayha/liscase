using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries.Covalence;
using Oxide.Core.Plugins;
using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Auto Backpack", "prostoj.store", "1.4.0")]
    [Description("Простой плагин: если инвентарь полный, перемещает предметы в рюкзак")]
    public class AutoBackpack : RustPlugin
    {
        #region Configuration

        private Configuration config;

        public class Configuration
        {
            [JsonProperty(PropertyName = "Включить плагин")]
            public bool Enabled { get; set; } = true;

            [JsonProperty(PropertyName = "Показывать сообщения игрокам")]
            public bool ShowMessages { get; set; } = false;

            [JsonProperty(PropertyName = "Исключенные предметы (ID)")]
            public List<int> ExcludedItems { get; set; } = new List<int>();

            public static Configuration DefaultConfig()
            {
                return new Configuration
                {
                    Enabled = true,
                    ShowMessages = false,
                    ExcludedItems = new List<int>()
                };
            }
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                config = Config.ReadObject<Configuration>();
                if (config == null) LoadDefaultConfig();
                SaveConfig();
            }
            catch (Exception e)
            {
                PrintWarning("Создание нового файла конфигурации.");
                LoadDefaultConfig();
            }
        }

        protected override void LoadDefaultConfig() => config = Configuration.DefaultConfig();
        protected override void SaveConfig() => Config.WriteObject(config);

        #endregion

        #region Data

        private Dictionary<ulong, bool> playerSettings = new Dictionary<ulong, bool>();

        private void SaveData()
        {
            Interface.Oxide.DataFileSystem.WriteObject(Name, playerSettings);
        }

        private void LoadData()
        {
            try
            {
                playerSettings = Interface.Oxide.DataFileSystem.ReadObject<Dictionary<ulong, bool>>(Name);
            }
            catch
            {
                playerSettings = new Dictionary<ulong, bool>();
            }
        }

        #endregion

        #region Initialization

        void Init()
        {
            LoadData();
            
            // Регистрируем команды
            cmd.AddChatCommand("autobackpack", this, "ToggleAutoBackpack");
            cmd.AddChatCommand("ab", this, "ToggleAutoBackpack");
            cmd.AddChatCommand("ab.debug", this, "DebugBackpack");
            
            // Регистрируем права только для админов
            permission.RegisterPermission("autobackpack.admin", this);
        }

        void OnServerInitialized()
        {
            if (!config.Enabled)
            {
                Unsubscribe(nameof(OnItemPickup));
                return;
            }

            Puts("Auto Backpack плагин загружен! Простая логика: инвентарь полный -> в рюкзак");
        }

        void Unload()
        {
            SaveData();
        }

        #endregion

        #region Localization

        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["AutoBackpackEnabled"] = "Автоматическое помещение в рюкзак <color=green>включено</color>!",
                ["AutoBackpackDisabled"] = "Автоматическое помещение в рюкзак <color=red>отключено</color>!",
                ["ItemMovedToBackpack"] = "Предмет <color=yellow>{0}</color> помещен в рюкзак (инвентарь полный)",
                ["NoBackpackFound"] = "Рюкзак не найден! Наденьте рюкзак.",
                ["BackpackFull"] = "Рюкзак также полный! Освободите место.",
                ["Status"] = "Автоматическое помещение в рюкзак: <color={0}>{1}</color>"
            }, this);

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["AutoBackpackEnabled"] = "Auto backpack <color=green>enabled</color>!",
                ["AutoBackpackDisabled"] = "Auto backpack <color=red>disabled</color>!",
                ["ItemMovedToBackpack"] = "Item <color=yellow>{0}</color> moved to backpack (inventory full)",
                ["NoBackpackFound"] = "No backpack found! Wear a backpack.",
                ["BackpackFull"] = "Backpack is also full! Free up some space.",
                ["Status"] = "Auto backpack: <color={0}>{1}</color>"
            }, this, "en");
        }

        private string GetMessage(string key, BasePlayer player, params object[] args)
        {
            return string.Format(lang.GetMessage(key, this, player?.UserIDString), args);
        }

        #endregion

        #region Commands

        [ChatCommand("autobackpack")]
        private void ToggleAutoBackpack(BasePlayer player, string command, string[] args)
        {
            if (!config.Enabled)
            {
                player.ChatMessage("Плагин отключен администратором!");
                return;
            }

            // Если есть аргумент "status", показываем текущий статус
            if (args.Length > 0 && args[0].ToLower() == "status")
            {
                bool statusSetting = GetPlayerSetting(player.userID);
                string status = statusSetting ? "включено" : "отключено";
                string color = statusSetting ? "green" : "red";
                player.ChatMessage(GetMessage("Status", player, color, status));
                
                // Проверяем наличие рюкзака
                var backpack = FindBackpack(player);
                if (backpack == null)
                {
                    player.ChatMessage(GetMessage("NoBackpackFound", player));
                }
                else if (backpack.contents?.IsFull() == true)
                {
                    player.ChatMessage(GetMessage("BackpackFull", player));
                }
                return;
            }

            bool currentSetting = GetPlayerSetting(player.userID);
            SetPlayerSetting(player.userID, !currentSetting);

            string message = !currentSetting ? 
                GetMessage("AutoBackpackEnabled", player) : 
                GetMessage("AutoBackpackDisabled", player);
            
            player.ChatMessage(message);
        }

        [ChatCommand("ab.debug")]
        private void DebugBackpack(BasePlayer player, string command, string[] args)
        {
            player.ChatMessage("=== ОТЛАДКА РЮКЗАКОВ ===");
            
            player.ChatMessage("Предметы в одежде:");
            foreach (var item in player.inventory.containerWear.itemList)
            {
                string hasContents = item.contents != null ? $" [КОНТЕЙНЕР: {item.contents.capacity} слотов]" : "";
                player.ChatMessage($"- {item.info.shortname} ({item.info.displayName.english}){hasContents}");
            }
            
            player.ChatMessage("Предметы в инвентаре с контейнерами:");
            foreach (var item in player.inventory.containerMain.itemList)
            {
                if (item.contents != null)
                {
                    player.ChatMessage($"- {item.info.shortname} ({item.info.displayName.english}) [КОНТЕЙНЕР: {item.contents.capacity} слотов]");
                }
            }
            
            var foundBackpack = FindBackpack(player);
            if (foundBackpack != null)
            {
                player.ChatMessage($"✅ НАЙДЕН РЮКЗАК: {foundBackpack.info.shortname} ({foundBackpack.info.displayName.english})");
                player.ChatMessage($"   Слотов: {foundBackpack.contents?.capacity ?? 0}, Занято: {foundBackpack.contents?.itemList?.Count ?? 0}");
            }
            else
            {
                player.ChatMessage("❌ РЮКЗАК НЕ НАЙДЕН");
            }
        }

        #endregion

        #region Core Logic

        private bool GetPlayerSetting(ulong userId)
        {
            if (playerSettings.ContainsKey(userId))
                return playerSettings[userId];
            
            return true; // По умолчанию включено для всех
        }

        private void SetPlayerSetting(ulong userId, bool enabled)
        {
            playerSettings[userId] = enabled;
            SaveData();
        }

        private bool IsPlayerAutoBackpackEnabled(BasePlayer player)
        {
            if (!config.Enabled) return false;
            
            return GetPlayerSetting(player.userID);
        }

        private Item FindBackpack(BasePlayer player)
        {
            // Ищем рюкзак в одежде игрока
            foreach (var item in player.inventory.containerWear.itemList)
            {
                if (item.contents != null && 
                    (item.info.shortname.ToLower().Contains("backpack") || 
                     item.info.shortname.ToLower().Contains("pack")))
                {
                    return item;
                }
            }

            return null;
        }

        private bool IsInventoryFull(BasePlayer player)
        {
            return player.inventory.containerMain.IsFull() && player.inventory.containerBelt.IsFull();
        }


        private bool TryMoveItemToBackpack(BasePlayer player, Item item)
        {
            var backpack = FindBackpack(player);
            if (backpack?.contents == null) 
            {
                if (config.ShowMessages)
                {
                    player.ChatMessage(GetMessage("NoBackpackFound", player));
                }
                return false;
            }

            // Проверяем, есть ли место в рюкзаке
            if (backpack.contents.IsFull()) 
            {
                if (config.ShowMessages)
                {
                    player.ChatMessage(GetMessage("BackpackFull", player));
                }
                return false;
            }

            // Перемещаем предмет в рюкзак
            if (item.MoveToContainer(backpack.contents))
            {
                if (config.ShowMessages)
                {
                    player.ChatMessage(GetMessage("ItemMovedToBackpack", player, item.info.displayName.english));
                }
                return true;
            }

            return false;
        }

        #endregion

        #region Hooks

        object OnItemPickup(Item item, BasePlayer player)
        {
            if (!config.Enabled) return null;
            if (player == null || item == null) return null;
            if (!IsPlayerAutoBackpackEnabled(player)) return null;

            // Проверяем, не исключен ли предмет
            if (config.ExcludedItems.Contains(item.info.itemid)) return null;

            // Если инвентарь не полный, позволяем обычный подбор
            if (!IsInventoryFull(player)) return null;

            // Ищем рюкзак
            var backpack = FindBackpack(player);
            if (backpack?.contents == null)
            {
                return null; // Нет рюкзака - предмет выпадет на землю
            }

            // Проверяем, есть ли место в рюкзаке
            if (backpack.contents.IsFull()) return null;

            // Пытаемся переместить предмет напрямую в рюкзак
            if (item.MoveToContainer(backpack.contents))
            {
                if (config.ShowMessages)
                {
                    player.ChatMessage(GetMessage("ItemMovedToBackpack", player, item.info.displayName.english));
                }
                return true; // Предотвращаем стандартный подбор
            }

            return null; // Не удалось переместить - предмет выпадет на землю
        }


        #endregion

        #region Admin Commands

        [ConsoleCommand("autobackpack.reload")]
        private void ReloadConfigCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null && !permission.UserHasPermission(arg.Player().UserIDString, "autobackpack.admin"))
            {
                arg.ReplyWith("No permission!");
                return;
            }

            LoadConfig();
            arg.ReplyWith("Auto Backpack config reloaded!");
        }

        [ConsoleCommand("autobackpack.toggle")]
        private void TogglePluginCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null && !permission.UserHasPermission(arg.Player().UserIDString, "autobackpack.admin"))
            {
                arg.ReplyWith("No permission!");
                return;
            }

            config.Enabled = !config.Enabled;
            SaveConfig();

            if (config.Enabled)
            {
                Subscribe(nameof(OnItemPickup));
            }
            else
            {
                Unsubscribe(nameof(OnItemPickup));
            }

            arg.ReplyWith($"Auto Backpack {(config.Enabled ? "enabled" : "disabled")}!");
        }

        #endregion
    }
}
