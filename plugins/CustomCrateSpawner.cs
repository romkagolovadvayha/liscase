using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Custom Crate Spawner", "prostoj.ai", "1.2.0")]
    [Description("Спавнит только заданные ящики с лутом и удаляет все остальные ящики/бочки/vehicle_parts.")]
    public class CustomCrateSpawner : RustPlugin
    {
        #region Config

        private ConfigData cfg;

        private class ConfigData 
        {
            [JsonProperty("Включить автоспавн при старте сервера")]
            public bool AutoSpawnOnInit = true;

            [JsonProperty("Периодическая очистка мусора (сек) (0 = выкл.)")]
            public float CleanupInterval = 30f;

            [JsonProperty("Количество наших ящиков")]
            public int CratesCount = 40;

            [JsonProperty("Радиус разброса (м) вокруг центра карты")]
            public float SpawnRadius = 450f;

            [JsonProperty("Минимальная дистанция между ящиками (м)")]
            public float MinSpacing = 25f;

            [JsonProperty("Смещение по высоте при установке (м)")]
            public float YOffset = 0.15f;

            [JsonProperty("Макс. попыток на поиск земли под каждый ящик")]
            public int AttemptsPerCrate = 40;

            [JsonProperty("Пути префабов ящиков (любой из списка)")]
            public List<string> CratePrefabs = new List<string>
            {
                "assets/bundled/prefabs/radtown/crate_normal.prefab",
                "assets/bundled/prefabs/radtown/crate_normal_2.prefab"
            };

            [JsonProperty("Шаблоны для удаления (включая бочки/мусор)")]
            public List<string> KillNameContains = new List<string>
            {
                "loot_barrel","loot_barrel_1","loot_barrel_2","oil_barrel",
                "crate","foodbox","medicalbox","toolbox","hackable_crate",
                "junkpile","vehicle_parts" // удаляет assets/bundled/prefabs/radtown/vehicle_parts.prefab
            };

            [JsonProperty("Лут-таблица (shortname, min, max, weight, optional skin)")]
            public List<LootEntry> Loot = new List<LootEntry>
            {
                new LootEntry("syringe.medical", 1, 3, 50),
                new LootEntry("bandage", 2, 6, 50),
                new LootEntry("bow.hunting", 1, 1, 20),
                new LootEntry("crossbow", 1, 1, 20),
                new LootEntry("arrow.wooden", 10, 20, 60),
                new LootEntry("roadsign.jacket", 1, 1, 10),
                new LootEntry("roadsign.kilt", 1, 1, 10),
                new LootEntry("hoodie", 1, 1, 15),
                new LootEntry("pants", 1, 1, 15),
                new LootEntry("shoes.boots", 1, 1, 15)
            };

            [JsonProperty("Количество предметов в одном ящике (мин)")]
            public int ItemsPerCrateMin = 2;

            [JsonProperty("Количество предметов в одном ящике (макс)")]
            public int ItemsPerCrateMax = 4;
        }

        public class LootEntry
        {
            public string shortname;
            public int min;
            public int max;
            public int weight;
            public ulong skin;

            public LootEntry() { }
            public LootEntry(string sn, int mi, int ma, int w, ulong sk = 0)
            {
                shortname = sn; min = mi; max = ma; weight = w; skin = sk;
            }
        }

        protected override void LoadDefaultConfig() => cfg = new ConfigData();
        protected override void LoadConfig()
        {
            base.LoadConfig();
            try { cfg = Config.ReadObject<ConfigData>() ?? new ConfigData(); }
            catch { PrintWarning("Config damaged, creating default."); cfg = new ConfigData(); }
            SaveConfig();
        }
        protected override void SaveConfig() => Config.WriteObject(cfg, true);

        #endregion

        #region State

        private readonly HashSet<NetworkableId> ourCrates = new HashSet<NetworkableId>();
        private Timer cleanupTimer;
        private static System.Random rnd = new System.Random();
        
        // Кэш всех предметов игры для UI
        private static List<ItemDefinition> allItems = null;
        private Dictionary<ulong, int> editingItemIndex = new Dictionary<ulong, int>();

        #endregion

        #region UI Constants

        private const string CRATES_MAIN_UI = "CratesSpawner_Main";
        private const string CRATES_SETTINGS_UI = "CratesSpawner_Settings";
        private const string CRATES_LOOT_UI = "CratesSpawner_Loot";
        private const string CRATES_COMMANDS_UI = "CratesSpawner_Commands";
        private const string CRATES_ITEM_PICKER_UI = "CratesSpawner_ItemPicker";
        private const string CRATES_EDIT_ITEM_UI = "CratesSpawner_EditItem";

        #endregion

        #region Hooks

        // Если у тебя версия Oxide с сигнатурой OnServerInitialized(bool), можно заменить на void OnServerInitialized(bool _)
        void OnServerInitialized()
        {
            // Дадим миру прогрузиться
            timer.Once(5f, () =>
            {
                if (cfg == null) LoadDefaultConfig();
                // Инициализируем список всех предметов
                InitializeItemsList();
            });

            if (cfg.CleanupInterval > 0f)
                cleanupTimer = timer.Every(Mathf.Max(5f, cfg.CleanupInterval), SoftCleanNearbyForeignLoot);
        }

        void Unload()
        {
            cleanupTimer?.Destroy();
            cleanupTimer = null;
            KillOurCrates();
            
            // Закрываем UI для всех игроков
            foreach (var player in BasePlayer.activePlayerList)
            {
                if (player != null && player.IsConnected)
                {
                    CuiHelper.DestroyUi(player, CRATES_MAIN_UI);
                    CuiHelper.DestroyUi(player, CRATES_SETTINGS_UI);
                    CuiHelper.DestroyUi(player, CRATES_LOOT_UI);
                    CuiHelper.DestroyUi(player, CRATES_COMMANDS_UI);
                    CuiHelper.DestroyUi(player, CRATES_ITEM_PICKER_UI);
                    CuiHelper.DestroyUi(player, CRATES_EDIT_ITEM_UI);
                }
            }
        }

        void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            if (player == null) return;
            
            // Закрываем UI при отключении игрока
            CuiHelper.DestroyUi(player, CRATES_MAIN_UI);
            CuiHelper.DestroyUi(player, CRATES_SETTINGS_UI);
            CuiHelper.DestroyUi(player, CRATES_LOOT_UI);
            CuiHelper.DestroyUi(player, CRATES_COMMANDS_UI);
            CuiHelper.DestroyUi(player, CRATES_ITEM_PICKER_UI);
            CuiHelper.DestroyUi(player, CRATES_EDIT_ITEM_UI);
            
            // Очищаем данные редактирования
            editingItemIndex.Remove(player.userID);
        }

        // Уничтожаем любой ванильный контейнер/бочку/vehicle_parts при спавне (кроме наших заспавненных)
      void OnEntitySpawned(BaseNetworkable entity)
	{
		var be = entity as BaseEntity;
		if (be == null || be.IsDestroyed) return;

		// если это один из наших префабов — не трогаем
		var fullPath = be.PrefabName; // полный путь (предпочтительно)
		var shortName = be.ShortPrefabName;

		if (IsOurCratePrefab(fullPath) || IsOurCratePrefab(shortName))
			return;

		// наши заспавненные контейнеры по ID тоже не трогаем
		var sc = be as StorageContainer;
		if (sc != null && ourCrates.Contains(sc.net.ID))
			return;

		// всё остальное, совпадающее по маскам — удаляем
		if (ShouldKill(shortName, fullPath))
		{
			try { be.KillMessage(); } catch { }
		}
	}

        #endregion

        #region Permissions & Commands

        private const string PermAdmin = "customcrates.admin";

        void Init()
        {
            permission.RegisterPermission(PermAdmin, this);
        }

        [ChatCommand("crates.refresh")]
        void CmdRefresh(BasePlayer player, string cmd, string[] args)
        {
            if (player != null && !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            HardCleanAllForeignLoot();
            KillOurCrates(); 
            SpawnAllCrates();
            player?.ChatMessage("[CRATES] Переспавнил наши ящики и очистил карту.");
            // Обновляем UI если открыто
            timer.Once(0.5f, () => {
                if (player != null && player.IsConnected)
                    ShowMainUI(player, "commands");
            });
        }

        [ChatCommand("crates.clean")]
        void CmdClean(BasePlayer player, string cmd, string[] args)
        {
            if (player != null && !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            HardCleanAllForeignLoot();
            player?.ChatMessage("[CRATES] Очистил все чужие ящики/бочки/vehicle_parts.");
            // Обновляем UI если открыто
            timer.Once(0.1f, () => {
                if (player != null && player.IsConnected)
                    ShowMainUI(player, "commands");
            });
        }

        [ChatCommand("crates.killmine")]
        void CmdKillMine(BasePlayer player, string cmd, string[] args)
        {
            if (player != null && !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            KillOurCrates();
            player?.ChatMessage("[CRATES] Удалил все наши заспавненные ящики.");
            // Обновляем UI если открыто
            timer.Once(0.1f, () => {
                if (player != null && player.IsConnected)
                    ShowMainUI(player, "settings");
            });
        }

        [ChatCommand("crates.ui")]
        void CmdUI(BasePlayer player, string cmd, string[] args)
        {
            if (player != null && !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            ShowMainUI(player, "settings");
        }

        #endregion

        #region Item Management

        private void InitializeItemsList()
        {
            if (allItems != null) return;
            
            allItems = ItemManager.GetItemDefinitions()
                .Where(x => !string.IsNullOrEmpty(x.shortname) && x.category != ItemCategory.Component)
                .OrderBy(x => x.displayName.english)
                .ToList();
                
            Puts($"[CRATES] Загружено {allItems.Count} предметов для UI");
        }

        private List<ItemDefinition> GetFilteredItems(string filter = "")
        {
            if (allItems == null) InitializeItemsList();
            
            if (string.IsNullOrEmpty(filter))
                return allItems.Take(50).ToList(); // Показываем первые 50 по умолчанию
                
            return allItems
                .Where(x => x.shortname.ToLower().Contains(filter.ToLower()) || 
                           x.displayName.english.ToLower().Contains(filter.ToLower()))
                .Take(50)
                .ToList();
        }

        #endregion

        #region UI Methods

        void ShowMainUI(BasePlayer player, string activeTab = "settings")
        {
            if (player == null) return;

            // Закрываем все UI
            CuiHelper.DestroyUi(player, CRATES_MAIN_UI);
            CuiHelper.DestroyUi(player, CRATES_SETTINGS_UI);
            CuiHelper.DestroyUi(player, CRATES_LOOT_UI);
            CuiHelper.DestroyUi(player, CRATES_COMMANDS_UI);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.1 0.1", AnchorMax = "0.9 0.9" },
                CursorEnabled = true
            }, "Overlay", CRATES_MAIN_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "CUSTOM CRATE SPAWNER v1.2.0 - АДМИН ПАНЕЛЬ", FontSize = 18, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 1" }
            }, CRATES_MAIN_UI);

            // Вкладки
            float tabWidth = 0.25f;
            string[] tabs = { "settings", "loot", "commands" };
            string[] tabNames = { "Настройки", "Лут-таблица", "Команды" };
            
            for (int i = 0; i < tabs.Length; i++)
            {
                bool isActive = tabs[i] == activeTab;
                string tabColor = isActive ? "0.3 0.6 0.3 0.9" : "0.2 0.2 0.2 0.8";
                
                container.Add(new CuiButton
                {
                    Button = { Command = $"crates.tab {tabs[i]}", Color = tabColor },
                    RectTransform = { AnchorMin = $"{0.1 + i * tabWidth} 0.85", AnchorMax = $"{0.1 + (i + 1) * tabWidth - 0.02} 0.91" },
                    Text = { Text = tabNames[i], FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, CRATES_MAIN_UI);
            }

            // Контент в зависимости от активной вкладки
            switch (activeTab)
            {
                case "settings":
                    ShowSettingsTabContent(container);
                    break;
                case "loot":
                    ShowLootTabContent(container);
                    break;
                case "commands":
                    ShowCommandsTabContent(container);
                    break;
            }

            // Кнопка закрытия
            container.Add(new CuiButton
            {
                Button = { Command = "crates.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.92 0.02", AnchorMax = "0.98 0.08" },
                Text = { Text = "✕", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            CuiHelper.AddUi(player, container);
        }

        void ShowSettingsTabContent(CuiElementContainer container)
        {
            float yPos = 0.75f;
            float yStep = 0.08f;

            // Автоспавн при старте
            container.Add(new CuiLabel
            {
                Text = { Text = "Автоспавн при старте сервера:", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.toggle autospawn", Color = cfg.AutoSpawnOnInit ? "0.4 0.6 0.4 0.8" : "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.52 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = cfg.AutoSpawnOnInit ? "ВКЛ" : "ВЫКЛ", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Количество ящиков
            container.Add(new CuiLabel
            {
                Text = { Text = $"Количество ящиков: {cfg.CratesCount}", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set count -5", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.52 {yPos - 0.03}", AnchorMax = $"0.57 {yPos + 0.03}" },
                Text = { Text = "-5", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set count -1", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.58 {yPos - 0.03}", AnchorMax = $"0.63 {yPos + 0.03}" },
                Text = { Text = "-1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set count 1", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.64 {yPos - 0.03}", AnchorMax = $"0.69 {yPos + 0.03}" },
                Text = { Text = "+1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set count 5", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.70 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "+5", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Радиус спавна
            container.Add(new CuiLabel
            {
                Text = { Text = $"Радиус спавна: {cfg.SpawnRadius}м", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set radius -50", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.52 {yPos - 0.03}", AnchorMax = $"0.58 {yPos + 0.03}" },
                Text = { Text = "-50", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set radius 50", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.59 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = "+50", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Минимальная дистанция
            container.Add(new CuiLabel
            {
                Text = { Text = $"Мин. дистанция между ящиками: {cfg.MinSpacing}м", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set spacing -5", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.52 {yPos - 0.03}", AnchorMax = $"0.58 {yPos + 0.03}" },
                Text = { Text = "-5", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set spacing 5", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.59 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = "+5", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Интервал очистки
            container.Add(new CuiLabel
            {
                Text = { Text = $"Интервал очистки: {cfg.CleanupInterval}сек", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set cleanup -10", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.52 {yPos - 0.03}", AnchorMax = $"0.58 {yPos + 0.03}" },
                Text = { Text = "-10", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.set cleanup 10", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.59 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = "+10", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            yPos -= yStep * 1.5f;

            // Статистика
            container.Add(new CuiLabel
            {
                Text = { Text = "СТАТИСТИКА", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep * 0.7f;

            container.Add(new CuiLabel
            {
                Text = { Text = $"Наших ящиков на карте: {ourCrates.Count}", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "0.8 1 0.8 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);
        }

        void ShowLootTabContent(CuiElementContainer container)
        {
            float yPos = 0.8f;
            float yStep = 0.05f;

            // Заголовок лут-таблицы
            container.Add(new CuiLabel
            {
                Text = { Text = "ЛУТОВАЯ ТАБЛИЦА", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.02}", AnchorMax = $"0.95 {yPos + 0.02}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep * 1.5f;

            // Настройки количества предметов
            container.Add(new CuiLabel
            {
                Text = { Text = $"Предметов в ящике: {cfg.ItemsPerCrateMin}-{cfg.ItemsPerCrateMax}", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.02}", AnchorMax = $"0.5 {yPos + 0.02}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.loot items -1", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.52 {yPos - 0.02}", AnchorMax = $"0.57 {yPos + 0.02}" },
                Text = { Text = "-1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.loot items 1", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.58 {yPos - 0.02}", AnchorMax = $"0.63 {yPos + 0.02}" },
                Text = { Text = "+1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            yPos -= yStep * 1.5f;

            // Заголовки колонок
            container.Add(new CuiLabel
            {
                Text = { Text = "Предмет", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.015}", AnchorMax = $"0.25 {yPos + 0.015}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Мин", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.26 {yPos - 0.015}", AnchorMax = $"0.35 {yPos + 0.015}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Макс", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.36 {yPos - 0.015}", AnchorMax = $"0.45 {yPos + 0.015}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Вес", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.46 {yPos - 0.015}", AnchorMax = $"0.55 {yPos + 0.015}" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Действия", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.56 {yPos - 0.015}", AnchorMax = $"0.95 {yPos + 0.015}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Список лута
            if (cfg.Loot != null)
            {
                for (int i = 0; i < cfg.Loot.Count && i < 10; i++) // Ограничиваем до 10 элементов для UI
                {
                    var loot = cfg.Loot[i];
                    
                    // Название предмета
                    container.Add(new CuiLabel
                    {
                        Text = { Text = loot.shortname, FontSize = 9, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                        RectTransform = { AnchorMin = $"0.05 {yPos - 0.015}", AnchorMax = $"0.25 {yPos + 0.015}" }
                    }, CRATES_MAIN_UI);

                    // Минимум
                    container.Add(new CuiLabel
                    {
                        Text = { Text = loot.min.ToString(), FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" },
                        RectTransform = { AnchorMin = $"0.26 {yPos - 0.015}", AnchorMax = $"0.35 {yPos + 0.015}" }
                    }, CRATES_MAIN_UI);

                    // Максимум
                    container.Add(new CuiLabel
                    {
                        Text = { Text = loot.max.ToString(), FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" },
                        RectTransform = { AnchorMin = $"0.36 {yPos - 0.015}", AnchorMax = $"0.45 {yPos + 0.015}" }
                    }, CRATES_MAIN_UI);

                    // Вес
                    container.Add(new CuiLabel
                    {
                        Text = { Text = loot.weight.ToString(), FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" },
                        RectTransform = { AnchorMin = $"0.46 {yPos - 0.015}", AnchorMax = $"0.55 {yPos + 0.015}" }
                    }, CRATES_MAIN_UI);

                    // Кнопки действий
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"crates.loot edit {i}", Color = "0.4 0.4 0.6 0.8" },
                        RectTransform = { AnchorMin = $"0.56 {yPos - 0.015}", AnchorMax = $"0.65 {yPos + 0.015}" },
                        Text = { Text = "Изменить", FontSize = 8, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, CRATES_MAIN_UI);

                    container.Add(new CuiButton
                    {
                        Button = { Command = $"crates.loot duplicate {i}", Color = "0.4 0.6 0.4 0.8" },
                        RectTransform = { AnchorMin = $"0.66 {yPos - 0.015}", AnchorMax = $"0.75 {yPos + 0.015}" },
                        Text = { Text = "Копировать", FontSize = 8, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, CRATES_MAIN_UI);

                    container.Add(new CuiButton
                    {
                        Button = { Command = $"crates.loot remove {i}", Color = "0.6 0.4 0.4 0.8" },
                        RectTransform = { AnchorMin = $"0.76 {yPos - 0.015}", AnchorMax = $"0.85 {yPos + 0.015}" },
                        Text = { Text = "Удалить", FontSize = 8, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, CRATES_MAIN_UI);

                    // Кнопки перемещения
                    if (i > 0)
                    {
                        container.Add(new CuiButton
                        {
                            Button = { Command = $"crates.loot move {i} up", Color = "0.5 0.5 0.5 0.8" },
                            RectTransform = { AnchorMin = $"0.86 {yPos - 0.015}", AnchorMax = $"0.90 {yPos + 0.015}" },
                            Text = { Text = "↑", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                        }, CRATES_MAIN_UI);
                    }

                    if (i < cfg.Loot.Count - 1)
                    {
                        container.Add(new CuiButton
                        {
                            Button = { Command = $"crates.loot move {i} down", Color = "0.5 0.5 0.5 0.8" },
                            RectTransform = { AnchorMin = $"0.91 {yPos - 0.015}", AnchorMax = $"0.95 {yPos + 0.015}" },
                            Text = { Text = "↓", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                        }, CRATES_MAIN_UI);
                    }

                    yPos -= yStep;
                }
            }

            // Кнопка добавления нового предмета
            yPos -= yStep * 0.5f;
            container.Add(new CuiButton
            {
                Button = { Command = "crates.loot picker", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.02}", AnchorMax = $"0.25 {yPos + 0.02}" },
                Text = { Text = "Выбрать предмет", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            // Кнопка сброса лута
            container.Add(new CuiButton
            {
                Button = { Command = "crates.loot reset", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.27 {yPos - 0.02}", AnchorMax = $"0.47 {yPos + 0.02}" },
                Text = { Text = "Сбросить к стандартному", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);
        }

        void ShowCommandsTabContent(CuiElementContainer container)
        {
            float yPos = 0.75f;
            float yStep = 0.08f;

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "УПРАВЛЕНИЕ ЯЩИКАМИ", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Кнопка переспавна всех ящиков
            container.Add(new CuiButton
            {
                Button = { Command = "crates.refresh", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.45 {yPos + 0.03}" },
                Text = { Text = "Переспавнить все ящики", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Удаляет все ящики и создает новые", FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.47 {yPos - 0.02}", AnchorMax = $"0.95 {yPos + 0.02}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Кнопка очистки чужих ящиков
            container.Add(new CuiButton
            {
                Button = { Command = "crates.clean", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.45 {yPos + 0.03}" },
                Text = { Text = "Очистить чужие ящики", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Удаляет все ванильные ящики/бочки", FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.47 {yPos - 0.02}", AnchorMax = $"0.95 {yPos + 0.02}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep;

            // Кнопка удаления наших ящиков
            container.Add(new CuiButton
            {
                Button = { Command = "crates.killmine", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.45 {yPos + 0.03}" },
                Text = { Text = "Удалить наши ящики", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Удаляет только заспавненные плагином ящики", FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.47 {yPos - 0.02}", AnchorMax = $"0.95 {yPos + 0.02}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep * 1.5f;

            // Информация о командах
            container.Add(new CuiLabel
            {
                Text = { Text = "ДОСТУПНЫЕ КОМАНДЫ В ЧАТЕ:", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.02}", AnchorMax = $"0.95 {yPos + 0.02}" }
            }, CRATES_MAIN_UI);

            yPos -= yStep * 0.7f;

            string[] commands = {
                "/crates.ui - Открыть панель управления",
                "/crates.refresh - Переспавнить все ящики",
                "/crates.clean - Очистить чужие ящики",
                "/crates.killmine - Удалить наши ящики"
            };

            foreach (var cmd in commands)
            {
                container.Add(new CuiLabel
                {
                    Text = { Text = cmd, FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "0.8 1 0.8 1" },
                    RectTransform = { AnchorMin = $"0.05 {yPos - 0.015}", AnchorMax = $"0.95 {yPos + 0.015}" }
                }, CRATES_MAIN_UI);
                yPos -= yStep * 0.6f;
            }
        }

        void ShowItemPickerUI(BasePlayer player, string filter = "", int page = 0)
        {
            if (player == null) return;

            CuiHelper.DestroyUi(player, CRATES_ITEM_PICKER_UI);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.2 0.1", AnchorMax = "0.8 0.9" },
                CursorEnabled = true
            }, "Overlay", CRATES_ITEM_PICKER_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "ВЫБОР ПРЕДМЕТА", FontSize = 18, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 1" }
            }, CRATES_ITEM_PICKER_UI);

            // Поле поиска (имитация)
            container.Add(new CuiLabel
            {
                Text = { Text = $"Поиск: {filter} (используйте команду /crates.search <текст>)", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = "0.05 0.85", AnchorMax = "0.7 0.9" }
            }, CRATES_ITEM_PICKER_UI);

            // Кнопка очистки поиска
            if (!string.IsNullOrEmpty(filter))
            {
                container.Add(new CuiButton
                {
                    Button = { Command = "crates.loot picker", Color = "0.6 0.4 0.4 0.8" },
                    RectTransform = { AnchorMin = "0.72 0.85", AnchorMax = "0.85 0.9" },
                    Text = { Text = "Очистить", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, CRATES_ITEM_PICKER_UI);
            }

            // Кнопка закрытия
            container.Add(new CuiButton
            {
                Button = { Command = "crates.picker.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.87 0.85", AnchorMax = "0.95 0.9" },
                Text = { Text = "✕", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_ITEM_PICKER_UI);

            // Список предметов
            var items = GetFilteredItems(filter);
            int itemsPerPage = 20;
            int startIndex = page * itemsPerPage;
            int endIndex = Mathf.Min(startIndex + itemsPerPage, items.Count);

            float yPos = 0.8f;
            float yStep = 0.035f;

            for (int i = startIndex; i < endIndex; i++)
            {
                var item = items[i];
                
                // Панель предмета
                container.Add(new CuiPanel
                {
                    Image = { Color = "0.2 0.2 0.2 0.8" },
                    RectTransform = { AnchorMin = $"0.05 {yPos - 0.015}", AnchorMax = $"0.95 {yPos + 0.015}" }
                }, CRATES_ITEM_PICKER_UI, $"item_{i}");

                // Название предмета
                container.Add(new CuiLabel
                {
                    Text = { Text = $"{item.shortname} ({item.displayName.english})", FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                    RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.7 1" }
                }, $"item_{i}");

                // Кнопка выбора
                container.Add(new CuiButton
                {
                    Button = { Command = $"crates.picker.select {item.shortname}", Color = "0.4 0.6 0.4 0.8" },
                    RectTransform = { AnchorMin = "0.75 0.1", AnchorMax = "0.95 0.9" },
                    Text = { Text = "Выбрать", FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, $"item_{i}");

                yPos -= yStep;
            }

            // Навигация по страницам
            if (items.Count > itemsPerPage)
            {
                int totalPages = Mathf.CeilToInt((float)items.Count / itemsPerPage);
                
                if (page > 0)
                {
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"crates.picker.page {filter} {page - 1}", Color = "0.4 0.4 0.6 0.8" },
                        RectTransform = { AnchorMin = "0.05 0.02", AnchorMax = "0.15 0.08" },
                        Text = { Text = "← Назад", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, CRATES_ITEM_PICKER_UI);
                }

                container.Add(new CuiLabel
                {
                    Text = { Text = $"Страница {page + 1} из {totalPages}", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                    RectTransform = { AnchorMin = "0.4 0.02", AnchorMax = "0.6 0.08" }
                }, CRATES_ITEM_PICKER_UI);

                if (page < totalPages - 1)
                {
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"crates.picker.page {filter} {page + 1}", Color = "0.4 0.4 0.6 0.8" },
                        RectTransform = { AnchorMin = "0.85 0.02", AnchorMax = "0.95 0.08" },
                        Text = { Text = "Вперед →", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, CRATES_ITEM_PICKER_UI);
                }
            }

            CuiHelper.AddUi(player, container);
        }

        void ShowEditItemUI(BasePlayer player, int itemIndex)
        {
            if (player == null || cfg.Loot == null || itemIndex < 0 || itemIndex >= cfg.Loot.Count) return;

            CuiHelper.DestroyUi(player, CRATES_EDIT_ITEM_UI);

            var item = cfg.Loot[itemIndex];
            editingItemIndex[player.userID] = itemIndex;

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.3 0.3", AnchorMax = "0.7 0.7" },
                CursorEnabled = true
            }, "Overlay", CRATES_EDIT_ITEM_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = $"РЕДАКТИРОВАНИЕ: {item.shortname}", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.85", AnchorMax = "1 0.95" }
            }, CRATES_EDIT_ITEM_UI);

            float yPos = 0.7f;
            float yStep = 0.12f;

            // Минимальное количество
            container.Add(new CuiLabel
            {
                Text = { Text = $"Минимум: {item.min}", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change min -1", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.55 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = "-1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change min 1", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.67 {yPos - 0.03}", AnchorMax = $"0.77 {yPos + 0.03}" },
                Text = { Text = "+1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change min 5", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.79 {yPos - 0.03}", AnchorMax = $"0.89 {yPos + 0.03}" },
                Text = { Text = "+5", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            yPos -= yStep;

            // Максимальное количество
            container.Add(new CuiLabel
            {
                Text = { Text = $"Максимум: {item.max}", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change max -1", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.55 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = "-1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change max 1", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.67 {yPos - 0.03}", AnchorMax = $"0.77 {yPos + 0.03}" },
                Text = { Text = "+1", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change max 5", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.79 {yPos - 0.03}", AnchorMax = $"0.89 {yPos + 0.03}" },
                Text = { Text = "+5", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            yPos -= yStep;

            // Вес
            container.Add(new CuiLabel
            {
                Text = { Text = $"Вес: {item.weight}", FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.5 {yPos + 0.03}" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change weight -10", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.55 {yPos - 0.03}", AnchorMax = $"0.65 {yPos + 0.03}" },
                Text = { Text = "-10", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change weight 10", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.67 {yPos - 0.03}", AnchorMax = $"0.77 {yPos + 0.03}" },
                Text = { Text = "+10", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = $"crates.edit.change weight 25", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = $"0.79 {yPos - 0.03}", AnchorMax = $"0.89 {yPos + 0.03}" },
                Text = { Text = "+25", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            // Кнопки управления
            container.Add(new CuiButton
            {
                Button = { Command = "crates.edit.save", Color = "0.4 0.6 0.4 0.8" },
                RectTransform = { AnchorMin = "0.1 0.05", AnchorMax = "0.4 0.15" },
                Text = { Text = "Сохранить", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "crates.edit.cancel", Color = "0.6 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = "0.6 0.05", AnchorMax = "0.9 0.15" },
                Text = { Text = "Отмена", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, CRATES_EDIT_ITEM_UI);

            CuiHelper.AddUi(player, container);
        }

        #endregion

        #region Console Commands

        [ConsoleCommand("crates.tab")]
        private void CmdUITab(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            ShowMainUI(player, arg.Args[0]);
        }

        [ConsoleCommand("crates.close")]
        private void CmdUIClose(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;

            CuiHelper.DestroyUi(player, CRATES_MAIN_UI);
            CuiHelper.DestroyUi(player, CRATES_SETTINGS_UI);
            CuiHelper.DestroyUi(player, CRATES_LOOT_UI);
            CuiHelper.DestroyUi(player, CRATES_COMMANDS_UI);
            CuiHelper.DestroyUi(player, CRATES_ITEM_PICKER_UI);
            CuiHelper.DestroyUi(player, CRATES_EDIT_ITEM_UI);
            editingItemIndex.Remove(player.userID);
        }

        [ConsoleCommand("crates.toggle")]
        private void CmdToggle(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string setting = arg.Args[0].ToLower();
            switch (setting)
            {
                case "autospawn":
                    cfg.AutoSpawnOnInit = !cfg.AutoSpawnOnInit;
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Автоспавн при старте: {(cfg.AutoSpawnOnInit ? "ВКЛ" : "ВЫКЛ")}");
                    ShowMainUI(player, "settings");
                    break;
            }
        }

        [ConsoleCommand("crates.set")]
        private void CmdSet(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (arg.Args.Length < 2) return;

            string setting = arg.Args[0].ToLower();
            if (!int.TryParse(arg.Args[1], out int value)) return;

            switch (setting)
            {
                case "count":
                    cfg.CratesCount = Mathf.Max(0, cfg.CratesCount + value);
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Количество ящиков: {cfg.CratesCount}");
                    break;
                case "radius":
                    cfg.SpawnRadius = Mathf.Max(50f, cfg.SpawnRadius + value);
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Радиус спавна: {cfg.SpawnRadius}м");
                    break;
                case "spacing":
                    cfg.MinSpacing = Mathf.Max(5f, cfg.MinSpacing + value);
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Мин. дистанция: {cfg.MinSpacing}м");
                    break;
                case "cleanup":
                    cfg.CleanupInterval = Mathf.Max(0f, cfg.CleanupInterval + value);
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Интервал очистки: {cfg.CleanupInterval}сек");
                    // Перезапускаем таймер очистки
                    cleanupTimer?.Destroy();
                    if (cfg.CleanupInterval > 0f)
                        cleanupTimer = timer.Every(Mathf.Max(5f, cfg.CleanupInterval), SoftCleanNearbyForeignLoot);
                    break;
            }
            ShowMainUI(player, "settings");
        }

        [ConsoleCommand("crates.loot")]
        private void CmdLoot(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string action = arg.Args[0].ToLower();
            switch (action)
            {
                case "items":
                    if (arg.Args.Length < 2 || !int.TryParse(arg.Args[1], out int change)) return;
                    cfg.ItemsPerCrateMin = Mathf.Max(1, cfg.ItemsPerCrateMin + change);
                    cfg.ItemsPerCrateMax = Mathf.Max(cfg.ItemsPerCrateMin, cfg.ItemsPerCrateMax + change);
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Предметов в ящике: {cfg.ItemsPerCrateMin}-{cfg.ItemsPerCrateMax}");
                    ShowMainUI(player, "loot");
                    break;

                case "picker":
                    ShowItemPickerUI(player);
                    break;

                case "remove":
                    if (arg.Args.Length < 2 || !int.TryParse(arg.Args[1], out int index)) return;
                    if (cfg.Loot == null || index < 0 || index >= cfg.Loot.Count) return;
                    string removedItem = cfg.Loot[index].shortname;
                    cfg.Loot.RemoveAt(index);
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Удален предмет: {removedItem}");
                    ShowMainUI(player, "loot");
                    break;

                case "reset":
                    cfg.Loot = new List<LootEntry>
                    {
                        new LootEntry("syringe.medical", 1, 3, 50),
                        new LootEntry("bandage", 2, 6, 50),
                        new LootEntry("bow.hunting", 1, 1, 20),
                        new LootEntry("crossbow", 1, 1, 20),
                        new LootEntry("arrow.wooden", 10, 20, 60),
                        new LootEntry("roadsign.jacket", 1, 1, 10),
                        new LootEntry("roadsign.kilt", 1, 1, 10),
                        new LootEntry("hoodie", 1, 1, 15),
                        new LootEntry("pants", 1, 1, 15),
                        new LootEntry("shoes.boots", 1, 1, 15)
                    };
                    SaveConfig();
                    player.ChatMessage("[CRATES] Лут-таблица сброшена к стандартной");
                    ShowMainUI(player, "loot");
                    break;

                case "edit":
                    if (arg.Args.Length < 2 || !int.TryParse(arg.Args[1], out int editIndex)) return;
                    if (cfg.Loot == null || editIndex < 0 || editIndex >= cfg.Loot.Count) return;
                    ShowEditItemUI(player, editIndex);
                    break;

                case "duplicate":
                    if (arg.Args.Length < 2 || !int.TryParse(arg.Args[1], out int dupIndex)) return;
                    if (cfg.Loot == null || dupIndex < 0 || dupIndex >= cfg.Loot.Count) return;
                    var original = cfg.Loot[dupIndex];
                    cfg.Loot.Add(new LootEntry(original.shortname, original.min, original.max, original.weight, original.skin));
                    SaveConfig();
                    player.ChatMessage($"[CRATES] Скопирован предмет: {original.shortname}");
                    ShowMainUI(player, "loot");
                    break;

                case "move":
                    if (arg.Args.Length < 3 || !int.TryParse(arg.Args[1], out int moveIndex)) return;
                    if (cfg.Loot == null || moveIndex < 0 || moveIndex >= cfg.Loot.Count) return;
                    string direction = arg.Args[2].ToLower();
                    
                    if (direction == "up" && moveIndex > 0)
                    {
                        var temp = cfg.Loot[moveIndex];
                        cfg.Loot[moveIndex] = cfg.Loot[moveIndex - 1];
                        cfg.Loot[moveIndex - 1] = temp;
                        SaveConfig();
                        player.ChatMessage($"[CRATES] Предмет {temp.shortname} перемещен вверх");
                    }
                    else if (direction == "down" && moveIndex < cfg.Loot.Count - 1)
                    {
                        var temp = cfg.Loot[moveIndex];
                        cfg.Loot[moveIndex] = cfg.Loot[moveIndex + 1];
                        cfg.Loot[moveIndex + 1] = temp;
                        SaveConfig();
                        player.ChatMessage($"[CRATES] Предмет {temp.shortname} перемещен вниз");
                    }
                    ShowMainUI(player, "loot");
                    break;
            }
        }

        // Команды для выбора предметов
        [ConsoleCommand("crates.picker.close")]
        private void CmdPickerClose(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            CuiHelper.DestroyUi(player, CRATES_ITEM_PICKER_UI);
        }

        [ConsoleCommand("crates.picker.select")]
        private void CmdPickerSelect(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string shortname = arg.Args[0];
            var itemDef = ItemManager.FindItemDefinition(shortname);
            if (itemDef == null)
            {
                player.ChatMessage($"[CRATES] Предмет не найден: {shortname}");
                return;
            }

            if (cfg.Loot == null) cfg.Loot = new List<LootEntry>();
            cfg.Loot.Add(new LootEntry(shortname, 1, 3, 50));
            SaveConfig();
            
            player.ChatMessage($"[CRATES] Добавлен предмет: {shortname} ({itemDef.displayName.english})");
            CuiHelper.DestroyUi(player, CRATES_ITEM_PICKER_UI);
            ShowMainUI(player, "loot");
        }

        [ConsoleCommand("crates.picker.page")]
        private void CmdPickerPage(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (arg.Args.Length < 2) return;

            string filter = arg.Args[0];
            if (!int.TryParse(arg.Args[1], out int page)) return;

            ShowItemPickerUI(player, filter, page);
        }

        // Команды для редактирования предметов
        [ConsoleCommand("crates.edit.change")]
        private void CmdEditChange(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (arg.Args.Length < 2) return;
            if (!editingItemIndex.ContainsKey(player.userID)) return;

            int itemIndex = editingItemIndex[player.userID];
            if (cfg.Loot == null || itemIndex < 0 || itemIndex >= cfg.Loot.Count) return;

            string property = arg.Args[0].ToLower();
            if (!int.TryParse(arg.Args[1], out int change)) return;

            var item = cfg.Loot[itemIndex];
            
            switch (property)
            {
                case "min":
                    item.min = Mathf.Max(1, item.min + change);
                    if (item.min > item.max) item.max = item.min;
                    break;
                case "max":
                    item.max = Mathf.Max(item.min, item.max + change);
                    break;
                case "weight":
                    item.weight = Mathf.Max(1, item.weight + change);
                    break;
            }

            ShowEditItemUI(player, itemIndex);
        }

        [ConsoleCommand("crates.edit.save")]
        private void CmdEditSave(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!editingItemIndex.ContainsKey(player.userID)) return;

            int itemIndex = editingItemIndex[player.userID];
            if (cfg.Loot == null || itemIndex < 0 || itemIndex >= cfg.Loot.Count) return;

            SaveConfig();
            editingItemIndex.Remove(player.userID);
            CuiHelper.DestroyUi(player, CRATES_EDIT_ITEM_UI);
            
            player.ChatMessage($"[CRATES] Изменения сохранены для предмета: {cfg.Loot[itemIndex].shortname}");
            ShowMainUI(player, "loot");
        }

        [ConsoleCommand("crates.edit.cancel")]
        private void CmdEditCancel(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;

            editingItemIndex.Remove(player.userID);
            CuiHelper.DestroyUi(player, CRATES_EDIT_ITEM_UI);
            ShowMainUI(player, "loot");
        }

        // Команда поиска предметов
        [ChatCommand("crates.search")]
        void CmdSearch(BasePlayer player, string cmd, string[] args)
        {
            if (player != null && !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            string filter = args.Length > 0 ? string.Join(" ", args) : "";
            ShowItemPickerUI(player, filter);
        }

        #endregion

        #region Spawn & Loot

        object API_Spawn()
        {
            HardCleanAllForeignLoot();
            KillOurCrates(); // Удаляем наши старые ящики
            SpawnAllCrates();
            return true;
        }
        void SpawnAllCrates()
        {
            if (cfg == null) LoadDefaultConfig();
            if (cfg.CratesCount <= 0) return;

            if (GameManager.server == null)
            {
                PrintWarning("[CRATES] GameManager.server == null (слишком ранний вызов?)");
                return;
            }

            var positions = PickPositions(cfg.CratesCount, cfg.MinSpacing, cfg.AttemptsPerCrate, cfg.SpawnRadius);
            int spawned = 0;

            foreach (var pos in positions)
            {
                string prefab = (cfg.CratePrefabs != null && cfg.CratePrefabs.Count > 0)
                    ? cfg.CratePrefabs[rnd.Next(cfg.CratePrefabs.Count)]
                    : "assets/bundled/prefabs/radtown/crate_normal.prefab";

                if (string.IsNullOrEmpty(prefab))
                {
                    PrintWarning("[CRATES] Пустой путь префаба, пропускаю.");
                    continue;
                }

                var ent = GameManager.server.CreateEntity(prefab, pos, Quaternion.identity, true);
                if (ent == null)
                {
                    PrintWarning($"[CRATES] Не смог создать сущность для префаба: {prefab}");
                    continue;
                }

                try { ent.Spawn(); }
                catch (Exception e)
                {
                    PrintWarning($"[CRATES] Ошибка Spawn() для {prefab}: {e.Message}");
                    try { ent.KillMessage(); } catch { }
                    continue;
                }

                var sc = ent as StorageContainer;
                if (sc == null)
                {
                    PrintWarning($"[CRATES] Префаб не StorageContainer: {prefab} ({ent.ShortPrefabName}) — удаляю.");
                    try { ent.KillMessage(); } catch { }
                    continue;
                }

                if (sc.net?.ID.IsValid ?? false)
                    ourCrates.Add(sc.net.ID);

                // Заполнение лута отложим на следующий тик — когда inventory гарантированно инициализирован
                NextTick(() => SafeFillLoot(sc));

                spawned++;
            }

            Puts($"[CRATES] Spawned {spawned}/{cfg.CratesCount} custom crates.");
        }

        void SafeFillLoot(StorageContainer box)
        {
            if (box == null || box.IsDestroyed) return;

            // Иногда inventory появляется тиком позже — дадим второй шанс
            if (box.inventory == null)
            {
                timer.Once(0.1f, () =>
                {
                    if (box == null || box.IsDestroyed || box.inventory == null) return;
                    TryFillLoot(box);
                });
                return;
            }

            TryFillLoot(box);
        }

        void TryFillLoot(StorageContainer box)
        {
            if (box == null || box.IsDestroyed || box.inventory == null) return;

            box.inventory.Clear();

            int items = Mathf.Clamp(UnityEngine.Random.Range(cfg.ItemsPerCrateMin, cfg.ItemsPerCrateMax + 1), 1, 10);
            for (int i = 0; i < items; i++)
            {
                var entry = RollLoot();
                if (entry == null) continue;

                var def = ItemManager.FindItemDefinition(entry.shortname);
                if (def == null)
                {
                    PrintWarning($"[CRATES] Unknown shortname: {entry.shortname}");
                    continue;
                }

                int amount = UnityEngine.Random.Range(Mathf.Max(1, entry.min), Mathf.Max(entry.min, entry.max) + 1);
                var item = ItemManager.Create(def, amount, entry.skin);
                if (item == null) continue;

                if (!item.MoveToContainer(box.inventory)) item.Remove();
            }
        }

        LootEntry RollLoot()
        {
            if (cfg.Loot == null || cfg.Loot.Count == 0) return null;
            int total = cfg.Loot.Sum(e => Mathf.Max(0, e.weight));
            if (total <= 0) return cfg.Loot[UnityEngine.Random.Range(0, cfg.Loot.Count)];

            int roll = UnityEngine.Random.Range(1, total + 1);
            int acc = 0;
            foreach (var e in cfg.Loot)
            {
                acc += Mathf.Max(0, e.weight);
                if (roll <= acc) return e;
            }
            return cfg.Loot.Last();
        }

        List<Vector3> PickPositions(int count, float spacing, int attemptsPerCrate, float radius)
        {
            var res = new List<Vector3>();
            var center = Vector3.zero; // центр карты
            int guard = count * attemptsPerCrate;

            while (res.Count < count && guard-- > 0)
            {
                var p = RandomGroundNear(center, radius, cfg.YOffset);
                if (p == Vector3.zero) continue;

                if (res.TrueForAll(x => Vector3.Distance(x, p) >= spacing))
                    res.Add(p);
            }

            return res;
        }

        Vector3 RandomGroundNear(Vector3 c, float radius, float yoff)
        {
            for (int i = 0; i < 12; i++)
            {
                float ang = UnityEngine.Random.Range(0f, Mathf.PI * 2f);
                float r = UnityEngine.Random.Range(5f, radius);
                var pos = c + new Vector3(Mathf.Cos(ang) * r, 0f, Mathf.Sin(ang) * r);

                pos.y = TerrainMeta.HeightMap.GetHeight(pos);
                if (!IsGoodGround(pos)) continue;

                return pos + new Vector3(0, yoff, 0);
            }
            return Vector3.zero;
        }

        bool IsGoodGround(Vector3 p)
        { 
            // без воды
            if (WaterLevel.GetWaterDepth(p, true, false, null) > 0.25f) return false;
            // уклон
            var normal = TerrainMeta.HeightMap.GetNormal(p);
            if (Vector3.Angle(normal, Vector3.up) > 28f) return false;
            return true;
        }

        #endregion

        #region Cleanup
		bool ShouldKill(string shortName, string fullPath = null)
		{
			// Никогда не сносим наши префабы
			if (IsOurCratePrefab(fullPath) || IsOurCratePrefab(shortName))
				return false;

			string s1 = shortName?.ToLowerInvariant() ?? "";
			string s2 = fullPath?.ToLowerInvariant() ?? "";

			foreach (var mask in cfg.KillNameContains)
			{
				if (string.IsNullOrEmpty(mask)) continue;
				string m = mask.ToLowerInvariant();

				if (s1.Contains(m)) return true;
				if (!string.IsNullOrEmpty(s2) && s2.Contains(m)) return true;
			}
			return false;
		}

        void HardCleanAllForeignLoot()
        {
            int removed = 0;
            foreach (var ent in BaseNetworkable.serverEntities)
            {
                var be = ent as BaseEntity;
                if (be == null || be.IsDestroyed) continue;

                var sc = be as StorageContainer;
                if (sc != null && ourCrates.Contains(sc.net.ID)) continue;

                if (ShouldKill(be.ShortPrefabName))
                {
                    try { be.KillMessage(); removed++; } catch { }
                }
            }
            Puts($"[CRATES] HardClean removed: {removed} foreign entities.");
        }

        void SoftCleanNearbyForeignLoot()
        {
            int removed = 0;
            foreach (var ent in BaseNetworkable.serverEntities)
            {
                var be = ent as BaseEntity;
                if (be == null || be.IsDestroyed) continue;

                var sc = be as StorageContainer;
                if (sc != null && ourCrates.Contains(sc.net.ID)) continue;

                if (ShouldKill(be.ShortPrefabName))
                {
                    try { be.KillMessage(); removed++; } catch { }
                }
            }

            if (removed > 0)
                Puts($"[CRATES] SoftClean removed: {removed} foreign entities.");
        }
		bool IsOurCratePrefab(string pathOrShort)
		{
			if (string.IsNullOrEmpty(pathOrShort) || cfg?.CratePrefabs == null) return false;
			// сравниваем по полному пути; если пришёл ShortPrefabName — тоже проверим вхождение
			string p = pathOrShort.ToLowerInvariant();
			foreach (var allowed in cfg.CratePrefabs)
			{
				if (string.IsNullOrEmpty(allowed)) continue;
				var a = allowed.ToLowerInvariant();
				if (p == a) return true;                 // точное совпадение полного пути
				if (!p.Contains("/") && a.EndsWith("/" + p)) return true; // пришло shortname, а в allow полный путь
			}
			return false;
		}
        void KillOurCrates()
        { 
            int removed = 0;
            foreach (var id in ourCrates.ToList())
            {
                var ent = BaseNetworkable.serverEntities.Find(id) as BaseEntity;
                if (ent != null && !ent.IsDestroyed)
                {
                    try { ent.KillMessage(); removed++; } catch { }
                }
                ourCrates.Remove(id);
            }
            if (removed > 0) Puts($"[CRATES] Removed our crates: {removed}");
        }

        #endregion
    }
}
