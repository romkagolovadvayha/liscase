using System;
using System.Collections;
using System.Collections.Generic;
using System.Reflection;
using UnityEngine;
using Newtonsoft.Json.Linq;
using Oxide.Core.Plugins;
using Oxide.Core;
using Newtonsoft.Json;
using Facepunch;
using UnityEngine.AI;
using Oxide.Game.Rust.Cui;
using System.IO;
using UnityEngine.Networking;
using Oxide.Plugins.FerryTerminalEventExtensionMethods;

namespace Oxide.Plugins
{
    [Info("FerryTerminalEvent", "KpucTaJl", "1.1.9")]
    internal class FerryTerminalEvent : RustPlugin
    {
        #region Config
        private const bool En = false;

        private PluginConfig _config;

        protected override void LoadDefaultConfig()
        {
            Puts("Creating a default config...");
            _config = PluginConfig.DefaultConfig();
            _config.PluginVersion = Version;
            SaveConfig();
            Puts("Creation of the default config completed!");
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            _config = Config.ReadObject<PluginConfig>();
            if (_config.PluginVersion < Version) UpdateConfigValues();
        }

        private void UpdateConfigValues()
        {
            Puts("Config update detected! Updating config values...");
            if (_config.PluginVersion < new VersionNumber(1, 0, 1))
            {
                _config.Hostage.IsSpawnRedBubble = true;
            }
            _config.PluginVersion = Version;
            Puts("Config update completed!");
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(_config);

        public class ItemConfig
        {
            [JsonProperty("ShortName")] public string ShortName { get; set; }
            [JsonProperty(En ? "Minimum" : "Минимальное кол-во")] public int MinAmount { get; set; }
            [JsonProperty(En ? "Maximum" : "Максимальное кол-во")] public int MaxAmount { get; set; }
            [JsonProperty(En ? "Chance [0.0-100.0]" : "Шанс выпадения предмета [0.0-100.0]")] public float Chance { get; set; }
            [JsonProperty(En ? "Is this a blueprint? [true/false]" : "Это чертеж? [true/false]")] public bool IsBluePrint { get; set; }
            [JsonProperty("SkinID (0 - default)")] public ulong SkinId { get; set; }
            [JsonProperty(En ? "Name (empty - default)" : "Название (empty - default)")] public string Name { get; set; }
        }

        public class LootTableConfig
        {
            [JsonProperty(En ? "Minimum numbers of items" : "Минимальное кол-во элементов")] public int Min { get; set; }
            [JsonProperty(En ? "Maximum numbers of items" : "Максимальное кол-во элементов")] public int Max { get; set; }
            [JsonProperty(En ? "Use minimum and maximum values? [true/false]" : "Использовать минимальное и максимальное значение? [true/false]")] public bool UseCount { get; set; }
            [JsonProperty(En ? "List of items" : "Список предметов")] public List<ItemConfig> Items { get; set; }
        }

        public class PrefabConfig
        {
            [JsonProperty(En ? "Chance [0.0-100.0]" : "Шанс выпадения [0.0-100.0]")] public float Chance { get; set; }
            [JsonProperty(En ? "The path to the prefab" : "Путь к prefab-у")] public string PrefabDefinition { get; set; }
        }

        public class PrefabLootTableConfig
        {
            [JsonProperty(En ? "Minimum numbers of prefabs" : "Минимальное кол-во prefab-ов")] public int Min { get; set; }
            [JsonProperty(En ? "Maximum numbers of prefabs" : "Максимальное кол-во prefab-ов")] public int Max { get; set; }
            [JsonProperty(En ? "Use minimum and maximum values? [true/false]" : "Использовать минимальное и максимальное значение? [true/false]")] public bool UseCount { get; set; }
            [JsonProperty(En ? "List of prefabs" : "Список prefab-ов")] public List<PrefabConfig> Prefabs { get; set; }
        }

        public class HackCrateConfig
        {
            [JsonProperty(En ? "Time to unlock the crates [sec.]" : "Время разблокирования ящиков [sec.]")] public float UnlockTime { get; set; }
            [JsonProperty(En ? "Which loot table should the plugin use? (0 - default; 1 - own; 2 - AlphaLoot; 3 - CustomLoot; 4 - loot table of the Rust objects; 5 - combine the 1 and 4 methods)" : "Какую таблицу лута необходимо использовать? (0 - стандартную; 1 - собственную; 2 - AlphaLoot; 3 - CustomLoot; 4 - таблица предметов объектов Rust; 5 - совместить 1 и 4 методы)")] public int TypeLootTable { get; set; }
            [JsonProperty(En ? "Loot table from prefabs (if the loot table type is 4 or 5)" : "Таблица предметов из prefab-ов (если тип таблицы предметов - 4 или 5)")] public PrefabLootTableConfig PrefabLootTable { get; set; }
            [JsonProperty(En ? "Own loot table (if the loot table type is 1 or 5)" : "Собственная таблица предметов (если тип таблицы предметов - 1 или 5)")] public LootTableConfig OwnLootTable { get; set; }
        }

        public class ColorConfig
        {
            [JsonProperty("R")] public float R { get; set; }
            [JsonProperty("G")] public float G { get; set; }
            [JsonProperty("B")] public float B { get; set; }
        }

        public class MarkerConfig
        {
            [JsonProperty(En ? "Use map marker? [true/false]" : "Использовать маркер на карте? [true/false]")] public bool Enabled { get; set; }
            [JsonProperty(En ? "Type (0 - simple, 1 - advanced)" : "Тип (0 - упрощенный, 1 - расширенный)")] public int Type { get; set; }
            [JsonProperty(En ? "Background radius (for marker type 0)" : "Радиус фона (если тип маркера - 0)")] public float Radius { get; set; }
            [JsonProperty(En ? "Background transparency" : "Прозрачность фона")] public float Alpha { get; set; }
            [JsonProperty(En ? "Color" : "Цвет")] public ColorConfig Color { get; set; }
            [JsonProperty(En ? "Text" : "Текст")] public string Text { get; set; }
        }

        public class PointConfig
        {
            [JsonProperty(En ? "Enabled? [true/false]" : "Включен? [true/false]")] public bool Enabled { get; set; }
            [JsonProperty(En ? "Text" : "Текст")] public string Text { get; set; }
            [JsonProperty(En ? "Size" : "Размер")] public int Size { get; set; }
            [JsonProperty(En ? "Color" : "Цвет")] public string Color { get; set; }
        }

        public class GuiConfig
        {
            [JsonProperty(En ? "Use GUI? [true/false]" : "Использовать ли GUI? [true/false]")] public bool IsGui { get; set; }
            [JsonProperty(En ? "OffsetMin Y for Tabs" : "OffsetMin Y для GUI с вкладками")] public int TabsOffsetMinY { get; set; }
            [JsonProperty(En ? "OffsetMin Y for Security Point" : "OffsetMin Y для GUI при освобождении заложника")] public int SecurityPointOffsetMinY { get; set; }
        }

        public class ChatConfig
        {
            [JsonProperty(En ? "Use chat messages? [true/false]" : "Использовать ли чат? [true/false]")] public bool IsChat { get; set; }
            [JsonProperty(En ? "Prefix of chat messages" : "Префикс сообщений в чате")] public string Prefix { get; set; }
        }

        public class GameTipConfig
        {
            [JsonProperty(En ? "Use Facepunch Game Tips (notification above hotbar)? [true/false]" : "Использовать ли Facepunch Game Tip (оповещения над слотами быстрого доступа игрока)? [true/false]")] public bool IsGameTip { get; set; }
            [JsonProperty(En ? "Style (0 - Blue Normal, 1 - Red Normal, 2 - Blue Long, 3 - Blue Short, 4 - Server Event)" : "Стиль (0 - Blue Normal, 1 - Red Normal, 2 - Blue Long, 3 - Blue Short, 4 - Server Event)")] public int Style { get; set; }
        }

        public class GuiAnnouncementsConfig
        {
            [JsonProperty(En ? "Use GUI Announcements (Requires GUIAnnouncements plugin) [true/false]" : "Использовать ли GUI Announcements? [true/false]")] public bool IsGuiAnnouncements { get; set; }
            [JsonProperty(En ? "Banner color" : "Цвет баннера")] public string BannerColor { get; set; }
            [JsonProperty(En ? "Text color" : "Цвет текста")] public string TextColor { get; set; }
            [JsonProperty(En ? "Adjust Vertical Position" : "Отступ от верхнего края")] public float ApiAdjustVPosition { get; set; }
        }

        public class NotifyConfig
        {
            [JsonProperty(En ? "Use Notify (Requires Notify plugin)? [true/false]" : "Использовать ли Notify? [true/false]")] public bool IsNotify { get; set; }
            [JsonProperty(En ? "Type" : "Тип")] public int Type { get; set; }
        }

        public class DiscordConfig
        {
            [JsonProperty(En ? "Use DiscordMessages (Requires DiscordMessages plugin)? [true/false]" : "Использовать ли Discord? [true/false]")] public bool IsDiscord { get; set; }
            [JsonProperty("Webhook URL")] public string WebhookUrl { get; set; }
            [JsonProperty(En ? "Embed Color (DECIMAL)" : "Цвет полосы (DECIMAL)")] public int EmbedColor { get; set; }
            [JsonProperty(En ? "Keys of required messages" : "Ключи необходимых сообщений")] public HashSet<string> Keys { get; set; }
        }

        public class EconomyConfig
        {
            [JsonProperty(En ? "Which plugins do you want to use for rewards? (Economics, Server Rewards, IQEconomic, XPerience)" : "Какие плагины экономики вы хотите использовать? (Economics, Server Rewards, IQEconomic, XPerience)")] public HashSet<string> Plugins { get; set; }
            [JsonProperty(En ? "The minimum value that a player must collect to earn rewards" : "Минимальное значение, которое игрок должен заработать, чтобы получить баллы за экономику")] public double Min { get; set; }
            [JsonProperty(En ? "Looting a locked crate" : "Ограбление заблокированного ящика")] public double LockedCrate { get; set; }
            [JsonProperty(En ? "Killing a standard NPC" : "Убийство стандартного Npc")] public double KillNpc { get; set; }
            [JsonProperty(En ? "Killing a security guard" : "Убийство охранника")] public double KillSecurityNpc { get; set; }
            [JsonProperty(En ? "Killing a wolf" : "Убийство волка")] public double KillWolf { get; set; }
            [JsonProperty(En ? "Killing an NPC on a Motorcycle" : "Убийство Npc на мотоцикле")] public double KillMotorbikeNpc { get; set; }
            [JsonProperty(En ? "Liberating a hostage" : "Освобождение заложника")] public double UnlockHostage { get; set; }
            [JsonProperty(En ? "List of commands that are executed in the console at the end of the event ({steamid} - the player who collected the highest number of points)" : "Список команд, которые выполняются в консоли по окончанию ивента ({steamid} - игрок, который набрал наибольшее кол-во баллов)")] public HashSet<string> Commands { get; set; }//?cache=%id%
        }

        public class PveModeConfig
        {
            [JsonProperty(En ? "Use PveMode (Requires PveMode plugin)? [true/false]" : "Использовать PVE режим работы плагина? [true/false]")] public bool Pve { get; set; }
            [JsonProperty(En ? "The amount of damage that the player has to do to become the Event Owner" : "Кол-во урона, которое должен нанести игрок, чтобы стать владельцем ивента")] public float Damage { get; set; }
            [JsonProperty(En ? "Damage Multipliers for calculate to become the Event Owner" : "Коэффициенты урона для подсчета, чтобы стать владельцем ивента")] public Dictionary<string, float> ScaleDamage { get; set; }
            [JsonProperty(En ? "Can the non-owner of the event loot the crates? [true/false]" : "Может ли не владелец ивента грабить ящики? [true/false]")] public bool LootCrate { get; set; }
            [JsonProperty(En ? "Can the non-owner of the event loot NPC corpses? [true/false]" : "Может ли не владелец ивента грабить трупы NPC? [true/false]")] public bool LootNpc { get; set; }
            [JsonProperty(En ? "Can the non-owner of the event deal damage to the NPC? [true/false]" : "Может ли не владелец ивента наносить урон по NPC? [true/false]")] public bool DamageNpc { get; set; }
            [JsonProperty(En ? "Can an Npc attack a non-owner of the event? [true/false]" : "Может ли Npc атаковать не владельца ивента? [true/false]")] public bool TargetNpc { get; set; }
            [JsonProperty(En ? "Allow the non-owner of the event to enter the event zone? [true/false]" : "Разрешать входить внутрь зоны ивента не владельцу ивента? [true/false]")] public bool CanEnter { get; set; }
            [JsonProperty(En ? "Allow a player who has an active cooldown of the Event Owner to enter the event zone? [true/false]" : "Разрешать входить внутрь зоны ивента игроку, у которого активен кулдаун на получение статуса владельца ивента? [true/false]")] public bool CanEnterCooldownPlayer { get; set; }
            [JsonProperty(En ? "The time that the Event Owner may not be inside the event zone [sec.]" : "Время, которое владелец ивента может не находиться внутри зоны ивента [сек.]")] public int TimeExitOwner { get; set; }
            [JsonProperty(En ? "The time until the end of Event Owner status when it is necessary to warn the player [sec.]" : "Время таймера до окончания действия статуса владельца ивента, когда необходимо предупредить игрока [сек.]")] public int AlertTime { get; set; }
            [JsonProperty(En ? "Prevent the actions of the RestoreUponDeath plugin in the event zone? [true/false]" : "Запрещать работу плагина RestoreUponDeath в зоне действия ивента? [true/false]")] public bool RestoreUponDeath { get; set; }
            [JsonProperty(En ? "The time that the player can`t become the Event Owner, after the end of the event and the player was its owner [sec.]" : "Время, которое игрок не сможет стать владельцем ивента, после того как ивент окончен и игрок был его владельцем [sec.]")] public double CooldownOwner { get; set; }
            [JsonProperty(En ? "Darkening the dome (0 - disables the dome)" : "Затемнение купола (0 - отключает купол)")] public int Darkening { get; set; }
        }

        public class NpcBelt
        {
            [JsonProperty("ShortName")] public string ShortName { get; set; }
            [JsonProperty(En ? "Amount" : "Кол-во")] public int Amount { get; set; }
            [JsonProperty("SkinID (0 - default)")] public ulong SkinId { get; set; }
            [JsonProperty(En ? "Mods" : "Модификации на оружие")] public HashSet<string> Mods { get; set; }
            [JsonProperty(En ? "Ammo" : "Боеприпасы")] public string Ammo { get; set; }
        }

        public class NpcWear
        {
            [JsonProperty("ShortName")] public string ShortName { get; set; }
            [JsonProperty("SkinID (0 - default)")] public ulong SkinId { get; set; }
        }

        public class NpcConfig
        {
            [JsonProperty(En ? "Name" : "Название")] public string Name { get; set; }
            [JsonProperty(En ? "Health" : "Кол-во ХП")] public float Health { get; set; }
            [JsonProperty(En ? "Roam Range" : "Дальность патрулирования местности")] public float RoamRange { get; set; }
            [JsonProperty(En ? "Chase Range" : "Дальность погони за целью")] public float ChaseRange { get; set; }
            [JsonProperty(En ? "Attack Range Multiplier" : "Множитель радиуса атаки")] public float AttackRangeMultiplier { get; set; }
            [JsonProperty(En ? "Sense Range" : "Радиус обнаружения цели")] public float SenseRange { get; set; }
            [JsonProperty(En ? "Target Memory Duration [sec.]" : "Длительность памяти цели [sec.]")] public float MemoryDuration { get; set; }
            [JsonProperty(En ? "Damage Scale" : "Множитель урона")] public float DamageScale { get; set; }
            [JsonProperty(En ? "Aim Cone Scale" : "Множитель разброса")] public float AimConeScale { get; set; }
            [JsonProperty(En ? "Use Vision Cone (if false NPC will detect based on Sense Range)? [true/false]" : "Обнаруживать цель только в углу обзора NPC? [true/false]")] public bool CheckVisionCone { get; set; }
            [JsonProperty(En ? "Vision Cone" : "Угол обзора")] public float VisionCone { get; set; }
            [JsonProperty(En ? "Speed" : "Скорость")] public float Speed { get; set; }
            [JsonProperty(En ? "Disable radio effects? [true/false]" : "Отключать эффекты рации? [true/false]")] public bool DisableRadio { get; set; }
            [JsonProperty(En ? "Is this a stationary NPC? [true/false]" : "Это стационарный NPC? [true/false]")] public bool Stationary { get; set; }
            [JsonProperty(En ? "Remove a corpse after death? (it is recommended to use the true value to improve performance) [true/false]" : "Удалять труп после смерти? (рекомендуется использовать значение true для повышения производительности) [true/false]")] public bool IsRemoveCorpse { get; set; }
            [JsonProperty(En ? "Wear items" : "Одежда")] public HashSet<NpcWear> WearItems { get; set; }
            [JsonProperty(En ? "Belt items" : "Быстрые слоты")] public HashSet<NpcBelt> BeltItems { get; set; }
            [JsonProperty(En ? "Kit (it is recommended to use the previous 2 settings to improve performance)" : "Kit (рекомендуется использовать предыдущие 2 пункта настройки для повышения производительности)")] public string Kit { get; set; }
        }

        public class PresetConfig
        {
            [JsonProperty(En ? "Minimum" : "Минимальное кол-во")] public int Min { get; set; }
            [JsonProperty(En ? "Maximum" : "Максимальное кол-во")] public int Max { get; set; }
            [JsonProperty(En ? "List of locations" : "Список расположений")] public HashSet<string> Positions { get; set; }
            [JsonProperty(En ? "NPCs setting" : "Настройки NPC")] public NpcConfig Config { get; set; }
            [JsonProperty(En ? "Which loot table should the plugin use? (0 - default; 1 - own; 2 - AlphaLoot; 3 - CustomLoot; 4 - loot table of the Rust objects; 5 - combine the 1 and 4 methods)" : "Какую таблицу предметов необходимо использовать? (0 - стандартную; 1 - собственную; 2 - AlphaLoot; 3 - CustomLoot; 4 - таблица предметов объектов Rust; 5 - совместить 1 и 4 методы)")] public int TypeLootTable { get; set; }
            [JsonProperty(En ? "Loot table from prefabs (if the loot table type is 4 or 5)" : "Таблица предметов из prefab-ов (если тип таблицы предметов - 4 или 5)")] public PrefabLootTableConfig PrefabLootTable { get; set; }
            [JsonProperty(En ? "Own loot table (if the loot table type is 1 or 5)" : "Собственная таблица предметов (если тип таблицы предметов - 1 или 5)")] public LootTableConfig OwnLootTable { get; set; }
        }

        public class AdditionalNpcConfig
        {
            [JsonProperty(En ? "Name" : "Название")] public string Name { get; set; }
            [JsonProperty(En ? "Health" : "Кол-во ХП")] public float Health { get; set; }
            [JsonProperty(En ? "Roam Range" : "Дальность патрулирования местности")] public float RoamRange { get; set; }
            [JsonProperty(En ? "Chase Range" : "Дальность погони за целью")] public float ChaseRange { get; set; }
            [JsonProperty(En ? "Attack Range Multiplier" : "Множитель радиуса атаки")] public float AttackRangeMultiplier { get; set; }
            [JsonProperty(En ? "Sense Range" : "Радиус обнаружения цели")] public float SenseRange { get; set; }
            [JsonProperty(En ? "Target Memory Duration [sec.]" : "Длительность памяти цели [sec.]")] public float MemoryDuration { get; set; }
            [JsonProperty(En ? "Damage Scale" : "Множитель урона")] public float DamageScale { get; set; }
            [JsonProperty(En ? "Aim Cone Scale" : "Множитель разброса")] public float AimConeScale { get; set; }
            [JsonProperty(En ? "Use Vision Cone (if false NPC will detect based on Sense Range)? [true/false]" : "Обнаруживать цель только в углу обзора NPC? [true/false]")] public bool CheckVisionCone { get; set; }
            [JsonProperty(En ? "Vision Cone" : "Угол обзора")] public float VisionCone { get; set; }
            [JsonProperty(En ? "Speed" : "Скорость")] public float Speed { get; set; }
            [JsonProperty(En ? "Disable radio effects? [true/false]" : "Отключать эффекты рации? [true/false]")] public bool DisableRadio { get; set; }
            [JsonProperty(En ? "Remove a corpse after death? (it is recommended to use the true value to improve performance) [true/false]" : "Удалять труп после смерти? (рекомендуется использовать значение true для повышения производительности) [true/false]")] public bool IsRemoveCorpse { get; set; }
            [JsonProperty(En ? "Wear items" : "Одежда")] public HashSet<NpcWear> WearItems { get; set; }
            [JsonProperty(En ? "Belt items" : "Быстрые слоты")] public HashSet<NpcBelt> BeltItems { get; set; }
            [JsonProperty(En ? "Kit (it is recommended to use the previous 2 settings to improve performance)" : "Kit (рекомендуется использовать предыдущие 2 пункта настройки для повышения производительности)")] public string Kit { get; set; }
            [JsonProperty(En ? "Which loot table should the plugin use? (0 - default; 1 - own; 2 - AlphaLoot; 3 - CustomLoot; 4 - loot table of the Rust objects; 5 - combine the 1 and 4 methods)" : "Какую таблицу предметов необходимо использовать? (0 - стандартную; 1 - собственную; 2 - AlphaLoot; 3 - CustomLoot; 4 - таблица предметов объектов Rust; 5 - совместить 1 и 4 методы)")] public int TypeLootTable { get; set; }
            [JsonProperty(En ? "Loot table from prefabs (if the loot table type is 4 or 5)" : "Таблица предметов из prefab-ов (если тип таблицы предметов - 4 или 5)")] public PrefabLootTableConfig PrefabLootTable { get; set; }
            [JsonProperty(En ? "Own loot table (if the loot table type is 1 or 5)" : "Собственная таблица предметов (если тип таблицы предметов - 1 или 5)")] public LootTableConfig OwnLootTable { get; set; }
        }

        public class WolfConfig
        {
            [JsonProperty(En ? "Health" : "Кол-во ХП")] public float Health { get; set; }
            [JsonProperty(En ? "Roam Range" : "Дальность патрулирования местности")] public float RoamRange { get; set; }
            [JsonProperty(En ? "Chase Range" : "Дальность погони за целью")] public float ChaseRange { get; set; }
            [JsonProperty(En ? "Sense Range" : "Радиус обнаружения цели")] public float SenseRange { get; set; }
            [JsonProperty(En ? "Target Memory Duration [sec.]" : "Длительность памяти цели [sec.]")] public float MemoryDuration { get; set; }
            [JsonProperty(En ? "Attack Range" : "Радиус атаки")] public float AttackRange { get; set; }
            [JsonProperty(En ? "Attack Damage" : "Урон от атаки")] public float AttackDamage { get; set; }
            [JsonProperty(En ? "Attack Rate [sec.]" : "Минимальное время между атаками [sec.]")] public float AttackRate { get; set; }
            [JsonProperty(En ? "Use Vision Cone (if false animal will detect based on Sense Range)? [true/false]" : "Обнаруживать цель только в углу обзора животного? [true/false]")] public bool CheckVisionCone { get; set; }
            [JsonProperty(En ? "Vision Cone" : "Угол обзора")] public float VisionCone { get; set; }
            [JsonProperty(En ? "Speed" : "Скорость")] public float Speed { get; set; }
        }

        public class HostageConfig
        {
            [JsonProperty(En ? "Name" : "Название")] public string Name { get; set; }
            [JsonProperty(En ? "Health" : "Кол-во ХП")] public float Health { get; set; }
            [JsonProperty(En ? "Wear items" : "Одежда")] public HashSet<NpcWear> WearItems { get; set; }
            [JsonProperty(En ? "Amount of time to add to event timer if there is not enough time before the end of the event, when the hostage is being liberated [sec.]" : "Время увеличения таймера ивента, если времени до окончания ивента недостаточно, когда освобождается заложник [сек.]")] public int IncreaseTime { get; set; }
            [JsonProperty(En ? "Percentage earned towards liberating hostage per second" : "Кол-во процентов освобождения заложника в секунду")] public float PercentPerSec { get; set; }
            [JsonProperty(En ? "Enable red bubble around hostages? (shows players how close to stand to liberate) [true/false]" : "Необходимо ли отображать красный круг освобождения заложников? [true/false]")] public bool IsSpawnRedBubble { get; set; }
        }

        private class PluginConfig
        {
            [JsonProperty(En ? "Minimum time between events [sec.]" : "Минимальное время между ивентами [sec.]")] public float MinStartTime { get; set; }
            [JsonProperty(En ? "Maximum time between events [sec.]" : "Максимальное время между ивентами [sec.]")] public float MaxStartTime { get; set; }
            [JsonProperty(En ? "Use minimum and maximum timer to start event? [true/false]" : "Активен ли таймер для запуска ивента? [true/false]")] public bool EnabledTimer { get; set; }
            [JsonProperty(En ? "Duration of the event [sec.]" : "Время проведения ивента [sec.]")] public int FinishTime { get; set; }
            [JsonProperty(En ? "Timer from chat message for event start until event begins [sec.]" : "Время до начала ивента после сообщения в чате [sec.]")] public float PreStartTime { get; set; }
            [JsonProperty(En ? "Notification time until the end of the event [sec.]" : "Время оповещения до окончания ивента [sec.]")] public int PreFinishTime { get; set; }
            [JsonProperty(En ? "Settings of all Npc presets at the start of the event" : "Настройки всех пресетов Npc при запуске ивента")] public HashSet<PresetConfig> NpcPresets { get; set; }
            [JsonProperty(En ? "Security Guards Settings" : "Настройки охранников")] public AdditionalNpcConfig SecurityNpc { get; set; }
            [JsonProperty(En ? "Wolf Settings" : "Настройки волков")] public WolfConfig Wolf { get; set; }
            [JsonProperty(En ? "Hostage Settings" : "Настройки заложников")] public HostageConfig Hostage { get; set; }
            [JsonProperty(En ? "Motorcycle NPCs Settings" : "Настройки Npc на мотоциклах")] public AdditionalNpcConfig MotorbikeNpc { get; set; }
            [JsonProperty(En ? "Disable Motorcycle NPC spawns? [true/false]" : "Отключать появление Npc на мотоциклах? [true/false]")] public bool DisableMotorbikeNpcs { get; set; }
            [JsonProperty(En ? "Locked crate settings" : "Настройка заблокированных ящиков")] public HackCrateConfig HackCrates { get; set; }
            [JsonProperty(En ? "Map Marker Settings" : "Настройка маркера на карте")] public MarkerConfig Marker { get; set; }
            [JsonProperty(En ? "Main marker settings for key event points shown on players screen" : "Настройки основного маркера на экране игрока")] public PointConfig MainPoint { get; set; }
            [JsonProperty(En ? "Additional marker settings for key event points shown on players screen" : "Настройки дополнительного маркера на экране игрока")] public PointConfig AdditionalPoint { get; set; }
            [JsonProperty(En ? "GUI settings" : "Настройки GUI")] public GuiConfig Gui { get; set; }
            [JsonProperty(En ? "Chat settings" : "Настройки чата")] public ChatConfig Chat { get; set; }
            [JsonProperty(En ? "Facepunch Game Tips settings" : "Настройка сообщений Facepunch Game Tip")] public GameTipConfig GameTip { get; set; }
            [JsonProperty(En ? "GUI Announcements settings" : "Настройка GUI Announcements (requires GUIAnnouncements plugin)")] public GuiAnnouncementsConfig GuiAnnouncements { get; set; }
            [JsonProperty(En ? "Notify settings (requires Notify plugin)" : "Настройка Notify")] public NotifyConfig Notify { get; set; }
            [JsonProperty(En ? "The distance from the event to the player for global alerts (0 - no limit)" : "Расстояние от ивента до игрока для глобальных оповещений (0 - нет ограничений)")] public float DistanceAlerts { get; set; }
            [JsonProperty(En ? "Discord Messages settings (requires DiscordMessages plugin)" : "Настройка оповещений в Discord (только для тех, кто использует плагин DiscordMessages)")] public DiscordConfig Discord { get; set; }
            [JsonProperty(En ? "Radius of the event zone" : "Радиус зоны ивента")] public float Radius { get; set; }
            [JsonProperty(En ? "Create a PVP zone in the event area? (requires TruePVE plugin) [true/false]" : "Создавать зону PVP в зоне проведения ивента? (только для тех, кто использует плагин TruePVE) [true/false]")] public bool IsCreateZonePvp { get; set; }
            [JsonProperty(En ? "PVE Mode Settings (requires PveMode plugin)" : "Настройка PVE режима работы плагина (только для тех, кто использует плагин PveMode)")] public PveModeConfig PveMode { get; set; }
            [JsonProperty(En ? "Block teleport in the event area? (requires NTeleportation plugin) [true/false]" : "Запрещать телепорт в зоне проведения ивента? (только для тех, кто использует плагин NTeleportation) [true/false]")] public bool NTeleportationInterrupt { get; set; }
            [JsonProperty(En ? "Disable NPCs from BetterNpc plugin on the monument while the event is on? [true/false]" : "Отключать NPC из плагина BetterNpc на монументе пока проходит ивент? [true/false]")] public bool RemoveBetterNpc { get; set; }
            [JsonProperty(En ? "Rewards settings (total values will be added up and rewarded at the end of the event)" : "Настройка экономики (конечное значение суммируется и будет выдано игрокам по окончанию ивента)")] public EconomyConfig Economy { get; set; }
            [JsonProperty(En ? "List of commands blocked in the event zone" : "Список команд запрещенных в зоне ивента")] public HashSet<string> Commands { get; set; }
            [JsonProperty(En ? "Configuration version" : "Версия конфигурации")] public VersionNumber PluginVersion { get; set; }

            public static PluginConfig DefaultConfig()
            {
                return new PluginConfig
                {
                    MinStartTime = 7200f,
                    MaxStartTime = 10800f,
                    EnabledTimer = true,
                    FinishTime = 1800,
                    PreStartTime = 300f,
                    PreFinishTime = 300,
                    NpcPresets = new HashSet<PresetConfig>
                    {
                        new PresetConfig
                        {
                            Min = 4,
                            Max = 4,
                            Positions = new HashSet<string>
                            {
                                "(-5.53, 5.13, -27.56)",
                                "(-20.48, 5.14, -38.83)",
                                "(-22.11, 6.37, -67.03)",
                                "(7.85, 6.38, -54.19)"
                            },
                            Config = new NpcConfig
                            {
                                Name = "Watchman",
                                Health = 150f,
                                RoamRange = 10f,
                                ChaseRange = 90f,
                                AttackRangeMultiplier = 1f,
                                SenseRange = 60f,
                                MemoryDuration = 10f,
                                DamageScale = 0.8f,
                                AimConeScale = 0.8f,
                                CheckVisionCone = false,
                                VisionCone = 135f,
                                Speed = 7.5f,
                                DisableRadio = true,
                                Stationary = false,
                                IsRemoveCorpse = true,
                                WearItems = new HashSet<NpcWear>
                                {
                                    new NpcWear { ShortName = "coffeecan.helmet", SkinId = 2803024592 },
                                    new NpcWear { ShortName = "roadsign.jacket", SkinId = 2803024010 },
                                    new NpcWear { ShortName = "roadsign.gloves", SkinId = 2806216923 },
                                    new NpcWear { ShortName = "hoodie", SkinId = 2811533300 },
                                    new NpcWear { ShortName = "pants", SkinId = 2811533832 },
                                    new NpcWear { ShortName = "shoes.boots", SkinId = 2816776847 }
                                },
                                BeltItems = new HashSet<NpcBelt>
                                {
                                    new NpcBelt { ShortName = "smg.mp5", Amount = 1, SkinId = 0, Mods = new HashSet<string> { "weapon.mod.flashlight" }, Ammo = string.Empty },
                                    new NpcBelt { ShortName = "syringe.medical", Amount = 5, SkinId = 0, Mods = new HashSet<string>(), Ammo = string.Empty }
                                },
                                Kit = ""
                            },
                            TypeLootTable = 5,
                            PrefabLootTable = new PrefabLootTableConfig
                            {
                                Min = 2, Max = 2, UseCount = false,
                                Prefabs = new List<PrefabConfig>
                                {
                                    new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_oilrig.prefab" },
                                    new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/bundled/prefabs/radtown/underwater_labs/tech_parts_1.prefab" }
                                }
                            },
                            OwnLootTable = new LootTableConfig
                            {
                                Min = 2, Max = 2, UseCount = false,
                                Items = new List<ItemConfig>
                                {
                                    new ItemConfig { ShortName = "scrap", MinAmount = 8, MaxAmount = 33, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" },
                                    new ItemConfig { ShortName = "syringe.medical", MinAmount = 2, MaxAmount = 4, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" }
                                }
                            }
                        },
                        new PresetConfig
                        {
                            Min = 4,
                            Max = 4,
                            Positions = new HashSet<string>
                            {
                                "(-11.94, 5.16, 21.72)",
                                "(40.59, 5.25, -5.94)",
                                "(-38.18, 5.08, -21.09)",
                                "(22.49, 5.09, 14.40)"
                            },
                            Config = new NpcConfig
                            {
                                Name = "Patrolman",
                                Health = 200f,
                                RoamRange = 10f,
                                ChaseRange = 90f,
                                AttackRangeMultiplier = 1.5f,
                                SenseRange = 60f,
                                MemoryDuration = 10f,
                                DamageScale = 1f,
                                AimConeScale = 1f,
                                CheckVisionCone = false,
                                VisionCone = 135f,
                                Speed = 7.5f,
                                DisableRadio = true,
                                Stationary = false,
                                IsRemoveCorpse = true,
                                WearItems = new HashSet<NpcWear>
                                {
                                    new NpcWear { ShortName = "coffeecan.helmet", SkinId = 1624104393 },
                                    new NpcWear { ShortName = "roadsign.jacket", SkinId = 1624100124 },
                                    new NpcWear { ShortName = "roadsign.kilt", SkinId = 1624102935 },
                                    new NpcWear { ShortName = "hoodie", SkinId = 1552703337 },
                                    new NpcWear { ShortName = "pants", SkinId = 1552705077 },
                                    new NpcWear { ShortName = "shoes.boots", SkinId = 1093790510 },
                                    new NpcWear { ShortName = "burlap.gloves", SkinId = 1552705918 }
                                },
                                BeltItems = new HashSet<NpcBelt>
                                {
                                    new NpcBelt { ShortName = "shotgun.m4", Amount = 1, SkinId = 0, Mods = new HashSet<string> { "weapon.mod.flashlight" }, Ammo = string.Empty },
                                    new NpcBelt { ShortName = "syringe.medical", Amount = 5, SkinId = 0, Mods = new HashSet<string>(), Ammo = string.Empty }
                                },
                                Kit = ""
                            },
                            TypeLootTable = 5,
                            PrefabLootTable = new PrefabLootTableConfig
                            {
                                Min = 2, Max = 2, UseCount = false,
                                Prefabs = new List<PrefabConfig>
                                {
                                    new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_oilrig.prefab" },
                                    new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/bundled/prefabs/radtown/underwater_labs/tech_parts_1.prefab" }
                                }
                            },
                            OwnLootTable = new LootTableConfig
                            {
                                Min = 2, Max = 2, UseCount = false,
                                Items = new List<ItemConfig>
                                {
                                    new ItemConfig { ShortName = "scrap", MinAmount = 8, MaxAmount = 33, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" },
                                    new ItemConfig { ShortName = "syringe.medical", MinAmount = 2, MaxAmount = 4, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" }
                                }
                            }
                        }
                    },
                    SecurityNpc = new AdditionalNpcConfig
                    {
                        Name = "Guard",
                        Health = 200f,
                        RoamRange = 5f,
                        ChaseRange = 90f,
                        AttackRangeMultiplier = 1f,
                        SenseRange = 40f,
                        MemoryDuration = 10f,
                        DamageScale = 0.25f,
                        AimConeScale = 1f,
                        CheckVisionCone = false,
                        VisionCone = 135f,
                        Speed = 7.5f,
                        DisableRadio = false,
                        IsRemoveCorpse = true,
                        WearItems = new HashSet<NpcWear>
                        {
                            new NpcWear { ShortName = "metal.facemask", SkinId = 3274815691 },
                            new NpcWear { ShortName = "metal.plate.torso", SkinId = 3274816373 },
                            new NpcWear { ShortName = "roadsign.kilt", SkinId = 2995379386 },
                            new NpcWear { ShortName = "hoodie", SkinId = 3289725349 },
                            new NpcWear { ShortName = "pants", SkinId = 3289723598 },
                            new NpcWear { ShortName = "shoes.boots", SkinId = 3018788619 }
                        },
                        BeltItems = new HashSet<NpcBelt>
                        {
                            new NpcBelt { ShortName = "rifle.lr300", Amount = 1, SkinId = 0, Mods = new HashSet<string> { "weapon.mod.flashlight", "weapon.mod.holosight" }, Ammo = string.Empty },
                            new NpcBelt { ShortName = "syringe.medical", Amount = 5, SkinId = 0, Mods = new HashSet<string>(), Ammo = string.Empty }
                        },
                        Kit = string.Empty,
                        TypeLootTable = 5,
                        PrefabLootTable = new PrefabLootTableConfig
                        {
                            Min = 2,
                            Max = 2,
                            UseCount = false,
                            Prefabs = new List<PrefabConfig>
                            {
                                new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_oilrig.prefab" },
                                new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/bundled/prefabs/radtown/underwater_labs/tech_parts_1.prefab" }
                            }
                        },
                        OwnLootTable = new LootTableConfig
                        {
                            Min = 2,
                            Max = 2,
                            UseCount = false,
                            Items = new List<ItemConfig>
                            {
                                new ItemConfig { ShortName = "scrap", MinAmount = 8, MaxAmount = 33, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" },
                                new ItemConfig { ShortName = "syringe.medical", MinAmount = 2, MaxAmount = 4, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" }
                            }
                        }
                    },
                    Wolf = new WolfConfig
                    {
                        Health = 150f,
                        RoamRange = 6f,
                        ChaseRange = 90f,
                        SenseRange = 40f,
                        MemoryDuration = 10f,
                        AttackRange = 2f,
                        AttackDamage = 10f,
                        AttackRate = 2f,
                        CheckVisionCone = false,
                        VisionCone = 135f,
                        Speed = 9f
                    },
                    Hostage = new HostageConfig
                    {
                        Name = "Hostage",
                        Health = 100f,
                        WearItems = new HashSet<NpcWear>
                        {
                            new NpcWear { ShortName = "prisonerhood", SkinId = 0 },
                            new NpcWear { ShortName = "burlap.shirt", SkinId = 2655843517 },
                            new NpcWear { ShortName = "burlap.trousers", SkinId = 2655838948 },
                            new NpcWear { ShortName = "burlap.shoes", SkinId = 912734730 }
                        },
                        IncreaseTime = 300,
                        PercentPerSec = 2f,
                        IsSpawnRedBubble = true
                    },
                    MotorbikeNpc = new AdditionalNpcConfig
                    {
                        Name = "Driver",
                        Health = 150f,
                        RoamRange = 5f,
                        ChaseRange = 40f,
                        AttackRangeMultiplier = 1f,
                        SenseRange = 40f,
                        MemoryDuration = 10f,
                        DamageScale = 0.8f,
                        AimConeScale = 1.5f,
                        CheckVisionCone = false,
                        VisionCone = 135f,
                        Speed = 7.5f,
                        DisableRadio = false,
                        IsRemoveCorpse = true,
                        WearItems = new HashSet<NpcWear>
                        {
                            new NpcWear { ShortName = "shoes.boots", SkinId = 2936561559 },
                            new NpcWear { ShortName = "pants", SkinId = 2933205831 },
                            new NpcWear { ShortName = "hoodie", SkinId = 2933205232 },
                            new NpcWear { ShortName = "burlap.gloves", SkinId = 2936561057 },
                            new NpcWear { ShortName = "riot.helmet", SkinId = 877578978 },
                            new NpcWear { ShortName = "mask.bandana", SkinId = 830282285 }
                        },
                        BeltItems = new HashSet<NpcBelt>
                        {
                            new NpcBelt { ShortName = "rifle.ak", Amount = 1, SkinId = 3289283029, Mods = new HashSet<string> { "weapon.mod.flashlight", "weapon.mod.holosight" }, Ammo = string.Empty },
                            new NpcBelt { ShortName = "syringe.medical", Amount = 5, SkinId = 0, Mods = new HashSet<string>(), Ammo = string.Empty }
                        },
                        Kit = string.Empty,
                        TypeLootTable = 1,
                        PrefabLootTable = new PrefabLootTableConfig
                        {
                            Min = 1,
                            Max = 1,
                            UseCount = false,
                            Prefabs = new List<PrefabConfig> { new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_oilrig.prefab" } }
                        },
                        OwnLootTable = new LootTableConfig
                        {
                            Min = 2,
                            Max = 2,
                            UseCount = false,
                            Items = new List<ItemConfig>
                            {
                                new ItemConfig { ShortName = "bandage", MinAmount = 1, MaxAmount = 2, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" },
                                new ItemConfig { ShortName = "ammo.pistol", MinAmount = 5, MaxAmount = 10, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" }
                            }
                        }
                    },
                    DisableMotorbikeNpcs = false,
                    HackCrates = new HackCrateConfig
                    {
                        UnlockTime = 10f,
                        TypeLootTable = 0,
                        PrefabLootTable = new PrefabLootTableConfig
                        {
                            Min = 1,
                            Max = 1,
                            UseCount = false,
                            Prefabs = new List<PrefabConfig> { new PrefabConfig { Chance = 100f, PrefabDefinition = "assets/prefabs/deployable/chinooklockedcrate/codelockedhackablecrate.prefab" } }
                        },
                        OwnLootTable = new LootTableConfig
                        {
                            Min = 1,
                            Max = 1,
                            UseCount = false,
                            Items = new List<ItemConfig> { new ItemConfig { ShortName = "scrap", MinAmount = 5, MaxAmount = 10, Chance = 100f, IsBluePrint = false, SkinId = 0, Name = "" } }
                        }
                    },
                    Marker = new MarkerConfig
                    {
                        Enabled = true,
                        Type = 1,
                        Radius = 0.37967f,
                        Alpha = 0.35f,
                        Color = new ColorConfig { R = 0.81f, G = 0.25f, B = 0.15f },
                        Text = "FerryTerminalEvent"
                    },
                    MainPoint = new PointConfig
                    {
                        Enabled = true,
                        Text = "◈",
                        Size = 25,
                        Color = "#CCFF00"
                    },
                    AdditionalPoint = new PointConfig
                    {
                        Enabled = true,
                        Text = "◆",
                        Size = 25,
                        Color = "#FFC700"
                    },
                    Gui = new GuiConfig
                    {
                        IsGui = true,
                        TabsOffsetMinY = -56,
                        SecurityPointOffsetMinY = -278
                    },
                    Chat = new ChatConfig
                    {
                        IsChat = true,
                        Prefix = "[FerryTerminalEvent]"
                    },
                    GameTip = new GameTipConfig
                    {
                        IsGameTip = false,
                        Style = 2
                    },
                    GuiAnnouncements = new GuiAnnouncementsConfig
                    {
                        IsGuiAnnouncements = false,
                        BannerColor = "Orange",
                        TextColor = "White",
                        ApiAdjustVPosition = 0.03f
                    },
                    Notify = new NotifyConfig
                    {
                        IsNotify = false,
                        Type = 0
                    },
                    DistanceAlerts = 0f,
                    Discord = new DiscordConfig
                    {
                        IsDiscord = false,
                        WebhookUrl = "https://support.discordapp.com/hc/en-us/articles/228383668-Intro-to-Webhooks",
                        EmbedColor = 13516583,
                        Keys = new HashSet<string>
                        {
                            "PreStart",
                            "Start",
                            "PreFinish",
                            "Finish",
                            "StartAttack",
                            "LiberateHostage",
                            "OpenCrate",
                            "KillHostage"
                        }
                    },
                    Radius = 150f,
                    IsCreateZonePvp = false,
                    PveMode = new PveModeConfig
                    {
                        Pve = false,
                        Damage = 500f,
                        ScaleDamage = new Dictionary<string, float> { ["Npc"] = 1f, ["Animal"] = 1f },
                        LootCrate = false,
                        LootNpc = false,
                        DamageNpc = false,
                        TargetNpc = false,
                        CanEnter = false,
                        CanEnterCooldownPlayer = true,
                        TimeExitOwner = 300,
                        AlertTime = 60,
                        RestoreUponDeath = true,
                        CooldownOwner = 86400,
                        Darkening = 12
                    },
                    NTeleportationInterrupt = true,
                    RemoveBetterNpc = true,
                    Economy = new EconomyConfig
                    {
                        Plugins = new HashSet<string> { "Economics", "Server Rewards", "IQEconomic", "XPerience" },
                        Min = 0,
                        LockedCrate = 0.5,
                        KillNpc = 0.3,
                        KillSecurityNpc = 0.5,
                        KillWolf = 0.4,
                        KillMotorbikeNpc = 0.4,
                        UnlockHostage = 0.8,
                        Commands = new HashSet<string>()
                    },
                    Commands = new HashSet<string>
                    {
                        "/remove",
                        "remove.toggle"
                    },
                    PluginVersion = new VersionNumber()
                };
            }
        }
        #endregion Config

        #region Lang
        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["PreStart"] = "{0} The <color=#55aaff>captors</color> will be transporting hostages to another island from the <color=#55aaff>Ferry Terminal</color> monument in <color=#55aaff>{1}</color>",
                ["Start"] = "{0} A van carrying hostages will soon arrive at the <color=#55aaff>Ferry Terminal</color> in grid <color=#55aaff>{1}</color>. Liberate them to receive rewards!",
                ["PreFinish"] = "{0} The <color=#55aaff>Ferry Terminal Event</color> <color=#ce3f27>will end</color> in <color=#55aaff>{1}</color>!",
                ["Finish"] = "{0} The <color=#55aaff>Ferry Terminal Event</color> <color=#ce3f27>has concluded</color>!",
                ["StartAttack"] = "{0} <color=#55aaff>{1}</color> <color=#738d43>started</color> a rescue operation, they were spotted and the <color=#55aaff>captors</color> have <color=#ce3f27>called for reinforcements</color>",
                ["LiberateHostage"] = "{0} The <color=#55aaff>hostage</color> has been <color=#738d43>liberated</color> and is <color=#738d43>being evacuated</color> from the area. The <color=#55aaff>captors</color> will <color=#ce3f27>try to kill them</color>, don't let them do it!",
                ["OpenCrate"] = "{0} The <color=#55aaff>hostage</color> was <color=#738d43>successfully evacuated</color> from the area. As a sign of gratitude, he <color=#738d43>opened</color> a <color=#55aaff>locked crate</color> with a reward",
                ["KillHostage"] = "{0} The <color=#55aaff>hostage</color> was <color=#ce3f27>killed</color>. The captors have completed their mission and <color=#55aaff>no reward</color> <color=#ce3f27>will be received</color>",
                ["SetOwner"] = "{0} Player <color=#55aaff>{1}</color> <color=#738d43>has received</color> owner status for the <color=#55aaff>Ferry Terminal Event</color>",
                ["EventActive"] = "{0} This event is active now. To finish this event (<color=#55aaff>/ftstop</color>), then (<color=#55aaff>/ftstart</color>) to start the next one!",
                ["EnterPVP"] = "{0} You <color=#ce3f27>have entered</color> the PVP zone, now other players <color=#ce3f27>can damage</color> you!",
                ["ExitPVP"] = "{0} You <color=#738d43>have left</color> the PVP zone, now other players <color=#738d43>cannot damage</color> you!",
                ["NTeleportation"] = "{0} You <color=#ce3f27>cannot</color> teleport into the event zone!",
                ["SendEconomy"] = "{0} You <color=#738d43>have earned</color> <color=#55aaff>{1}</color> as a reward for participating in the event",
                ["NoCommand"] = "{0} You <color=#ce3f27>cannot</color> use this command in the event zone!"
            }, this);

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["PreStart"] = "{0} Через <color=#55aaff>{1}</color> на монументе <color=#55aaff>Ferry Terminal</color> состоится перевозка заложников на другой остров",
                ["Start"] = "{0} На монумент <color=#55aaff>Ferry Terminal</color> в квадрат <color=#55aaff>{1}</color> скоро прибудет фургон с заложниками. Освободите их и вас ждет вознаграждение",
                ["PreFinish"] = "{0} Ивент на монументе <color=#55aaff>Ferry Terminal</color> <color=#ce3f27>закончится</color> через <color=#55aaff>{1}</color>!",
                ["Finish"] = "{0} Ивент на монументе <color=#55aaff>Ferry Terminal</color> <color=#ce3f27>закончен</color>!",
                ["StartAttack"] = "{0} <color=#55aaff>{1}</color> <color=#738d43>начал</color> спасательную операцию, но его заметили и <color=#55aaff>ренегаты</color> <color=#ce3f27>вызвали подкрепление</color>",
                ["LiberateHostage"] = "{0} <color=#55aaff>Заложник</color> <color=#738d43>освобожден</color> и <color=#738d43>эвакуируется</color> с монумента. <color=#55aaff>Ренегаты</color> <color=#ce3f27>попытаются его убить</color>, не дайте им это сделать!",
                ["OpenCrate"] = "{0} <color=#55aaff>Заложник</color> <color=#738d43>успешно эвакуировался</color> с монумента. В знак благодарности он <color=#738d43>открыл</color> <color=#55aaff>заблокированный ящик</color> с вознаграждением",
                ["KillHostage"] = "{0} <color=#55aaff>Заложник</color> был <color=#ce3f27>убит</color>. Ренегаты выполнили свою миссию и <color=#55aaff>вознаграждение</color> за него <color=#ce3f27>не будет получено</color>",
                ["SetOwner"] = "{0} Игрок <color=#55aaff>{1}</color> <color=#738d43>получил</color> статус владельца ивента для <color=#55aaff>Ferry Terminal Event</color>",
                ["EventActive"] = "{0} Ивент в данный момент активен, сначала завершите текущий ивент (<color=#55aaff>/ftstop</color>), чтобы начать следующий!",
                ["EnterPVP"] = "{0} Вы <color=#ce3f27>вошли</color> в PVP зону, теперь другие игроки <color=#ce3f27>могут</color> наносить вам урон!",
                ["ExitPVP"] = "{0} Вы <color=#738d43>вышли</color> из PVP зоны, теперь другие игроки <color=#738d43>не могут</color> наносить вам урон!",
                ["NTeleportation"] = "{0} Вы <color=#ce3f27>не можете</color> телепортироваться в зоне ивента!",
                ["SendEconomy"] = "{0} Вы <color=#738d43>получили</color> <color=#55aaff>{1}</color> баллов в экономику за прохождение ивента",
                ["NoCommand"] = "{0} Вы <color=#ce3f27>не можете</color> использовать данную команду в зоне ивента!"
            }, this, "ru");
        }

        private string GetMessage(string langKey, string userId) => lang.GetMessage(langKey, _ins, userId);

        private string GetMessage(string langKey, string userId, params object[] args) => (args.Length == 0) ? GetMessage(langKey, userId) : string.Format(GetMessage(langKey, userId), args);
        #endregion Lang

        #region Oxide Hooks
        private static FerryTerminalEvent _ins;

        private void Init()
        {
            _ins = this;
            ToggleHooks(false);
        }

        private void OnServerInitialized()
        {
            if (GetMonument() == null)
            {
                PrintError("The Ferry Terminal location is missing on the map. The plugin cannot be loaded!");
                NextTick(() => Interface.Oxide.UnloadPlugin(Name));
                return;
            }
            CheckAllLootTables();
            ServerMgr.Instance.StartCoroutine(DownloadImages());
            StartTimer();
        }

        private void Unload()
        {
            if (Controller != null) Finish();
            _ins = null;
        }

        private object OnEntityTakeDamage(BaseAnimalNPC entity, HitInfo info)
        {
            if (entity == null || info == null) return null;
            if (Controller.BrainAnimations.Any(x => x.Entity == entity)) return true;
            BasePlayer attacker = info.InitiatorPlayer;
            if (Controller.AttackCoroutine == null && attacker.IsPlayer() && Controller.Wolves.Contains(entity))
            {
                if (ActivePveMode && PveMode.Call("CanActionEvent", Name, attacker) != null) return true;
                else Controller.StartAttack(attacker);
            }
            return null;
        }

        private object OnEntityTakeDamage(BasePlayer entity, HitInfo info)
        {
            if (entity == null || info == null || entity.IsPlayer()) return null;

            if (Controller.BrainAnimations.Any(x => x.Entity == entity)) return true;

            if (entity is ScientistNPC npc)
            {
                if (Controller.Motorbikes.Any(x => x.Driver == npc)) return true;
                if (Controller.NoDamageDrivers.Any(x => x.Id == npc.net.ID.Value)) return true;
                BasePlayer attacker = info.InitiatorPlayer;
                if (attacker.IsPlayer() && Controller.Guards.Contains(npc))
                {
                    if (Controller.AttackCoroutine == null)
                    {
                        if (ActivePveMode)
                        {
                            if (PveMode.Call("CanActionEvent", Name, attacker) == null) Controller.StartAttack(attacker);
                            else return true;
                        }
                        else Controller.StartAttack(attacker);
                    }
                    Controller.TryAttackWolf(npc.transform.position, attacker);
                }
                return null;
            }

            if (Controller.Hostages.Contains(entity)) return true;

            HostageEvacuation animation = Controller.HostagesEvacuations.FirstOrDefault(x => x.Hostage == entity);
            if (animation != null)
            {
                if (info.Initiator is ScientistNPC) animation.TakeDamage(info.damageTypes.Total());
                return true;
            }

            return null;
        }

        private object OnEntityTakeDamage(BuildingBlock entity, HitInfo info)
        {
            if (entity != null && Controller.ContainerShore.Entities.Contains(entity)) return true;
            else return null;
        }

        private object OnEntityTakeDamage(Door entity, HitInfo info)
        {
            if (entity != null && Controller.ContainerShore.Entities.Contains(entity)) return true;
            else return null;
        }

        private object OnEntityTakeDamage(Bike entity, HitInfo info)
        {
            if (entity != null && Controller.Motorbikes.Any(x => x.Bike == entity)) return true;
            else return null;
        }

        private object OnEntityTakeDamage(DecorDeployable entity, HitInfo info)
        {
            if (entity != null && Controller.Vendor != null && Controller.Vendor.Decor.Contains(entity)) return true;
            else return null;
        }

        private object CanHelicopterTarget(PatrolHelicopterAI heli, BasePlayer ply)
        {
            if (ply != null)
            {
                if (Controller.Hostages.Contains(ply)) return false;
                if (Controller.HostagesEvacuations.Any(x => x.Hostage == ply)) return false;
            }
            return null;
        }

        private object CanHelicopterStrafeTarget(PatrolHelicopterAI heli, BasePlayer ply) => CanHelicopterTarget(heli, ply);

        private object OnHelicopterTarget(HelicopterTurret turret, BaseCombatEntity newTarget)
        {
            BasePlayer bp = newTarget as BasePlayer;
            if (bp != null)
            {
                if (Controller.Hostages.Contains(bp)) return true;
                if (Controller.HostagesEvacuations.Any(x => x.Hostage == bp)) return true;
            }
            return null;
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (!_config.Marker.Enabled || Controller == null || !player.IsPlayer()) return;
            if (player.HasPlayerFlag(BasePlayer.PlayerFlags.ReceivingSnapshot)) timer.In(2f, () => OnPlayerConnected(player));
            else Controller.UpdateMapMarkers();
        }

        private object OnPlayerDeath(BasePlayer player, HitInfo info)
        {
            foreach (SecurityPoint point in Controller.SecurityPoints) point.ExitPlayer(player);
            Controller.ExitPlayer(player);
            return null;
        }

        private void OnEntityDeath(ScientistNPC npc, HitInfo info)
        {
            if (npc == null || info == null) return;
            BasePlayer attacker = info.InitiatorPlayer;
            if (!attacker.IsPlayer()) return;
            if (Controller.Scientists.Contains(npc)) ActionEconomy(attacker.userID, "KillNpc");
            if (Controller.Guards.Contains(npc)) ActionEconomy(attacker.userID, "KillSecurityNpc");
            if (Controller.Drivers.Contains(npc)) ActionEconomy(attacker.userID, "KillMotorbikeNpc");
        }

        private void OnEntityDeath(BaseAnimalNPC animal, HitInfo info)
        {
            if (animal == null || info == null) return;
            BasePlayer attacker = info.InitiatorPlayer;
            if (!attacker.IsPlayer()) return;
            if (Controller.Wolves.Contains(animal)) ActionEconomy(attacker.userID, "KillWolf");
        }

        private object CanBuild(Planner planner, Construction prefab, Construction.Target target)
        {
            if (planner == null) return null;
            BasePlayer player = planner.GetOwnerPlayer();
            if (player == null) return null;
            if (Controller.Players.Contains(player)) return true;
            return null;
        }

        private object CanChangeGrade(BasePlayer player, BuildingBlock block, BuildingGrade.Enum grade, ulong skin)
        {
            if (block != null && Controller.ContainerShore != null && Controller.ContainerShore.Entities.Contains(block)) return false;
            else return null;
        }

        private object OnStructureRotate(BuildingBlock block, BasePlayer player)
        {
            if (block != null && Controller.ContainerShore != null && Controller.ContainerShore.Entities.Contains(block)) return true;
            else return null;
        }

        private object OnNpcTarget(BaseEntity attacker, BasePlayer victim)
        {
            if (attacker == null || victim == null || victim.IsPlayer()) return null;
            if (Controller == null) return null;

            if (Controller.BrainAnimations.Any(x => x.IsHostage && x.Entity == victim)) return true;
            if (Controller.Hostages.Contains(victim)) return true;
            if (Controller.HostagesEvacuations.Any(x => x.Hostage == victim)) return true;

            if (attacker is ScientistNPC npcAttacker && Controller.Guards.Contains(npcAttacker))
            {
                if (Controller.Hostages.Contains(victim) ||
                    Controller.HostagesEvacuations.Any(x => x.Hostage == victim))
                    return true;
            }

            return null;
        }

        private void OnCargoPlaneSignaled(CargoPlane cargoPlane, SupplySignal supplySignal)
        {
            if (cargoPlane == null || supplySignal == null || Controller.CargoShipContainer == null) return;
            if (supplySignal == Controller.CargoShipContainer.Supply)
            {
                Controller.CargoShipContainer.Plane = cargoPlane;
                cargoPlane.secondsToTake *= 0.1f;
            }
        }

        private void OnSupplyDropDropped(SupplyDrop supplyDrop, CargoPlane cargoPlane)
        {
            if (supplyDrop == null || cargoPlane == null || Controller.CargoShipContainer == null) return;
            if (cargoPlane == Controller.CargoShipContainer.Plane)
            {
                Controller.CargoShipContainer.SpawnContainer();
                if (supplyDrop.IsExists()) supplyDrop.Kill();
            }
        }

        private object CanDismountEntity(ScientistNPC npc, BaseMountable mountable)
        {
            if (npc == null || mountable == null) return null;
            if (Controller.Motorbikes.Any(x => x.Driver == npc)) return true;
            else return null;
        }

        private void OnCustomNpcGuardTargetEnd(ScientistNPC npc)
        {
            if (npc == null) return;
            
            if (Controller.Hostages.Count + Controller.HostagesEvacuations.Count == 0) 
                _ins.NpcSpawn.Call("SetHomePosition", npc, Controller.GetGlobalPosition(new Vector3(69.108f, 3.471f, 60.912f)));
        }

        private void OnEntityKill(BaseAnimalNPC entity)
        {
            if (entity != null && Controller.Wolves.Contains(entity))
                Controller.Wolves.Remove(entity);
        }

        private void OnEntityKill(HackableLockedCrate entity)
        {
            if (entity != null && Controller.Barge != null && Controller.Barge.HackCrates.Contains(entity))
                Controller.Barge.HackCrates.Remove(entity);
        }

        private HashSet<ulong> LootableCrates { get; } = new HashSet<ulong>();

        private void OnLootEntity(BasePlayer player, HackableLockedCrate container)
        {
            if (!player.IsPlayer() || container == null) return;
            if (Controller.Barge == null || !Controller.Barge.HackCrates.Contains(container)) return;
            if (LootableCrates.Contains(container.net.ID.Value)) return;
            LootableCrates.Add(container.net.ID.Value);
            ActionEconomy(player.userID, "LockedCrate");
        }

        private object OnPlayerCommand(BasePlayer player, string command, string[] args)
        {
            if (player != null && Controller.Players.Contains(player))
            {
                command = "/" + command;
                if (_config.Commands.Contains(command.ToLower()))
                {
                    AlertToPlayer(player, GetMessage("NoCommand", player.UserIDString, _config.Chat.Prefix));
                    return true;
                }
            }
            return null;
        }

        private object OnServerCommand(ConsoleSystem.Arg arg)
        {
            if (arg == null || arg.cmd == null) return null;
            BasePlayer player = arg.Player();
            if (player != null && Controller.Players.Contains(player))
            {
                if (_config.Commands.Contains(arg.cmd.Name.ToLower()) || _config.Commands.Contains(arg.cmd.FullName.ToLower()))
                {
                    AlertToPlayer(player, GetMessage("NoCommand", player.UserIDString, _config.Chat.Prefix));
                    return true;
                }
            }
            return null;
        }
        #endregion Oxide Hooks

        #region Controller
        internal HashSet<Vector3> Marker { get; } = new HashSet<Vector3>
        {
            new Vector3(46f, 0f, 12),
            new Vector3(46f, 0f, 10),
            new Vector3(46f, 0f, 8),
            new Vector3(46f, 0f, 6),
            new Vector3(46f, 0f, 4),
            new Vector3(46f, 0f, 2),
            new Vector3(46f, 0f, 0),
            new Vector3(46f, 0f, -2),
            new Vector3(46f, 0f, -4),
            new Vector3(46f, 0f, -6),
            new Vector3(46f, 0f, -8),
            new Vector3(46f, 0f, -10),
            new Vector3(46f, 0f, -12),
            new Vector3(44f, 0f, 18),
            new Vector3(44f, 0f, 16),
            new Vector3(44f, 0f, 14),
            new Vector3(44f, 0f, 12),
            new Vector3(44f, 0f, 10),
            new Vector3(44f, 0f, -12),
            new Vector3(44f, 0f, -14),
            new Vector3(44f, 0f, -16),
            new Vector3(44f, 0f, -18),
            new Vector3(42f, 0f, 22),
            new Vector3(42f, 0f, 20),
            new Vector3(42f, 0f, 18),
            new Vector3(42f, 0f, -18),
            new Vector3(42f, 0f, -20),
            new Vector3(42f, 0f, -22),
            new Vector3(42f, 0f, -24),
            new Vector3(40f, 0f, 26),
            new Vector3(40f, 0f, 24),
            new Vector3(40f, 0f, 22),
            new Vector3(40f, 0f, -22),
            new Vector3(40f, 0f, -24),
            new Vector3(40f, 0f, -26),
            new Vector3(38f, 0f, 28),
            new Vector3(38f, 0f, 26),
            new Vector3(38f, 0f, 24),
            new Vector3(38f, 0f, -26),
            new Vector3(38f, 0f, -28),
            new Vector3(38f, 0f, -30),
            new Vector3(36f, 0f, 30),
            new Vector3(36f, 0f, 28),
            new Vector3(36f, 0f, -28),
            new Vector3(36f, 0f, -30),
            new Vector3(36f, 0f, -32),
            new Vector3(34f, 0f, 32),
            new Vector3(34f, 0f, 30),
            new Vector3(34f, 0f, -32),
            new Vector3(34f, 0f, -34),
            new Vector3(32f, 0f, 34),
            new Vector3(32f, 0f, 32),
            new Vector3(32f, 0f, -34),
            new Vector3(32f, 0f, -36),
            new Vector3(30f, 0f, 36),
            new Vector3(30f, 0f, 34),
            new Vector3(30f, 0f, -36),
            new Vector3(30f, 0f, -38),
            new Vector3(28f, 0f, 38),
            new Vector3(28f, 0f, 36),
            new Vector3(28f, 0f, -36),
            new Vector3(28f, 0f, -38),
            new Vector3(26f, 0f, 40),
            new Vector3(26f, 0f, 38),
            new Vector3(26f, 0f, 16),
            new Vector3(26f, 0f, 14),
            new Vector3(26f, 0f, 12),
            new Vector3(26f, 0f, 10),
            new Vector3(26f, 0f, -12),
            new Vector3(26f, 0f, -14),
            new Vector3(26f, 0f, -16),
            new Vector3(26f, 0f, -18),
            new Vector3(26f, 0f, -38),
            new Vector3(26f, 0f, -40),
            new Vector3(24f, 0f, 40),
            new Vector3(24f, 0f, 38),
            new Vector3(24f, 0f, 16),
            new Vector3(24f, 0f, 14),
            new Vector3(24f, 0f, 12),
            new Vector3(24f, 0f, 10),
            new Vector3(24f, 0f, -12),
            new Vector3(24f, 0f, -14),
            new Vector3(24f, 0f, -16),
            new Vector3(24f, 0f, -18),
            new Vector3(24f, 0f, -40),
            new Vector3(24f, 0f, -42),
            new Vector3(22f, 0f, 42),
            new Vector3(22f, 0f, 40),
            new Vector3(22f, 0f, 16),
            new Vector3(22f, 0f, 14),
            new Vector3(22f, 0f, 12),
            new Vector3(22f, 0f, 10),
            new Vector3(22f, 0f, -12),
            new Vector3(22f, 0f, -14),
            new Vector3(22f, 0f, -16),
            new Vector3(22f, 0f, -18),
            new Vector3(22f, 0f, -40),
            new Vector3(22f, 0f, -42),
            new Vector3(20f, 0f, 42),
            new Vector3(20f, 0f, 16),
            new Vector3(20f, 0f, 14),
            new Vector3(20f, 0f, 12),
            new Vector3(20f, 0f, 10),
            new Vector3(20f, 0f, -12),
            new Vector3(20f, 0f, -14),
            new Vector3(20f, 0f, -16),
            new Vector3(20f, 0f, -18),
            new Vector3(20f, 0f, -42),
            new Vector3(20f, 0f, -44),
            new Vector3(18f, 0f, 44),
            new Vector3(18f, 0f, 42),
            new Vector3(18f, 0f, 16),
            new Vector3(18f, 0f, 14),
            new Vector3(18f, 0f, 12),
            new Vector3(18f, 0f, 10),
            new Vector3(18f, 0f, -12),
            new Vector3(18f, 0f, -14),
            new Vector3(18f, 0f, -16),
            new Vector3(18f, 0f, -18),
            new Vector3(18f, 0f, -44),
            new Vector3(16f, 0f, 44),
            new Vector3(16f, 0f, 16),
            new Vector3(16f, 0f, 14),
            new Vector3(16f, 0f, 12),
            new Vector3(16f, 0f, 10),
            new Vector3(16f, 0f, -12),
            new Vector3(16f, 0f, -14),
            new Vector3(16f, 0f, -16),
            new Vector3(16f, 0f, -18),
            new Vector3(16f, 0f, -44),
            new Vector3(14f, 0f, 44),
            new Vector3(14f, 0f, 16),
            new Vector3(14f, 0f, 14),
            new Vector3(14f, 0f, 12),
            new Vector3(14f, 0f, 10),
            new Vector3(14f, 0f, -12),
            new Vector3(14f, 0f, -14),
            new Vector3(14f, 0f, -16),
            new Vector3(14f, 0f, -18),
            new Vector3(14f, 0f, -44),
            new Vector3(14f, 0f, -46),
            new Vector3(12f, 0f, 46),
            new Vector3(12f, 0f, 44),
            new Vector3(12f, 0f, 16),
            new Vector3(12f, 0f, 14),
            new Vector3(12f, 0f, 12),
            new Vector3(12f, 0f, 10),
            new Vector3(12f, 0f, 2),
            new Vector3(12f, 0f, 0),
            new Vector3(12f, 0f, -2),
            new Vector3(12f, 0f, -4),
            new Vector3(12f, 0f, -10),
            new Vector3(12f, 0f, -12),
            new Vector3(12f, 0f, -14),
            new Vector3(12f, 0f, -16),
            new Vector3(12f, 0f, -18),
            new Vector3(12f, 0f, -46),
            new Vector3(10f, 0f, 46),
            new Vector3(10f, 0f, 44),
            new Vector3(10f, 0f, 30),
            new Vector3(10f, 0f, 28),
            new Vector3(10f, 0f, 26),
            new Vector3(10f, 0f, 24),
            new Vector3(10f, 0f, 16),
            new Vector3(10f, 0f, 14),
            new Vector3(10f, 0f, 12),
            new Vector3(10f, 0f, 10),
            new Vector3(10f, 0f, 8),
            new Vector3(10f, 0f, 6),
            new Vector3(10f, 0f, 2),
            new Vector3(10f, 0f, 0),
            new Vector3(10f, 0f, -2),
            new Vector3(10f, 0f, -4),
            new Vector3(10f, 0f, -8),
            new Vector3(10f, 0f, -10),
            new Vector3(10f, 0f, -12),
            new Vector3(10f, 0f, -14),
            new Vector3(10f, 0f, -16),
            new Vector3(10f, 0f, -18),
            new Vector3(10f, 0f, -26),
            new Vector3(10f, 0f, -28),
            new Vector3(10f, 0f, -30),
            new Vector3(10f, 0f, -46),
            new Vector3(8f, 0f, 46),
            new Vector3(8f, 0f, 28),
            new Vector3(8f, 0f, 26),
            new Vector3(8f, 0f, 24),
            new Vector3(8f, 0f, 22),
            new Vector3(8f, 0f, 16),
            new Vector3(8f, 0f, 14),
            new Vector3(8f, 0f, 12),
            new Vector3(8f, 0f, 10),
            new Vector3(8f, 0f, 8),
            new Vector3(8f, 0f, 6),
            new Vector3(8f, 0f, 2),
            new Vector3(8f, 0f, 0),
            new Vector3(8f, 0f, -2),
            new Vector3(8f, 0f, -4),
            new Vector3(8f, 0f, -8),
            new Vector3(8f, 0f, -10),
            new Vector3(8f, 0f, -12),
            new Vector3(8f, 0f, -14),
            new Vector3(8f, 0f, -16),
            new Vector3(8f, 0f, -24),
            new Vector3(8f, 0f, -26),
            new Vector3(8f, 0f, -28),
            new Vector3(8f, 0f, -30),
            new Vector3(8f, 0f, -46),
            new Vector3(8f, 0f, -48),
            new Vector3(6f, 0f, 46),
            new Vector3(6f, 0f, 14),
            new Vector3(6f, 0f, 12),
            new Vector3(6f, 0f, 10),
            new Vector3(6f, 0f, 8),
            new Vector3(6f, 0f, 6),
            new Vector3(6f, 0f, 2),
            new Vector3(6f, 0f, 0),
            new Vector3(6f, 0f, -2),
            new Vector3(6f, 0f, -4),
            new Vector3(6f, 0f, -8),
            new Vector3(6f, 0f, -10),
            new Vector3(6f, 0f, -12),
            new Vector3(6f, 0f, -14),
            new Vector3(6f, 0f, -46),
            new Vector3(6f, 0f, -48),
            new Vector3(4f, 0f, 46),
            new Vector3(4f, 0f, 12),
            new Vector3(4f, 0f, 10),
            new Vector3(4f, 0f, 8),
            new Vector3(4f, 0f, 6),
            new Vector3(4f, 0f, 2),
            new Vector3(4f, 0f, 0),
            new Vector3(4f, 0f, -2),
            new Vector3(4f, 0f, -4),
            new Vector3(4f, 0f, -8),
            new Vector3(4f, 0f, -10),
            new Vector3(4f, 0f, -12),
            new Vector3(4f, 0f, -46),
            new Vector3(4f, 0f, -48),
            new Vector3(2f, 0f, 46),
            new Vector3(2f, 0f, 2),
            new Vector3(2f, 0f, 0),
            new Vector3(2f, 0f, -2),
            new Vector3(2f, 0f, -4),
            new Vector3(2f, 0f, -46),
            new Vector3(2f, 0f, -48),
            new Vector3(0f, 0f, 46),
            new Vector3(0f, 0f, 30),
            new Vector3(0f, 0f, 28),
            new Vector3(0f, 0f, 26),
            new Vector3(0f, 0f, 24),
            new Vector3(0f, 0f, 2),
            new Vector3(0f, 0f, 0),
            new Vector3(0f, 0f, -2),
            new Vector3(0f, 0f, -4),
            new Vector3(0f, 0f, -26),
            new Vector3(0f, 0f, -28),
            new Vector3(0f, 0f, -30),
            new Vector3(0f, 0f, -32),
            new Vector3(0f, 0f, -48),
            new Vector3(-2f, 0f, 46),
            new Vector3(-2f, 0f, 30),
            new Vector3(-2f, 0f, 28),
            new Vector3(-2f, 0f, 26),
            new Vector3(-2f, 0f, 24),
            new Vector3(-2f, 0f, 2),
            new Vector3(-2f, 0f, 0),
            new Vector3(-2f, 0f, -2),
            new Vector3(-2f, 0f, -4),
            new Vector3(-2f, 0f, -26),
            new Vector3(-2f, 0f, -28),
            new Vector3(-2f, 0f, -30),
            new Vector3(-2f, 0f, -32),
            new Vector3(-2f, 0f, -48),
            new Vector3(-4f, 0f, 46),
            new Vector3(-4f, 0f, 8),
            new Vector3(-4f, 0f, 6),
            new Vector3(-4f, 0f, 2),
            new Vector3(-4f, 0f, 0),
            new Vector3(-4f, 0f, -2),
            new Vector3(-4f, 0f, -4),
            new Vector3(-4f, 0f, -8),
            new Vector3(-4f, 0f, -10),
            new Vector3(-4f, 0f, -46),
            new Vector3(-4f, 0f, -48),
            new Vector3(-6f, 0f, 46),
            new Vector3(-6f, 0f, 12),
            new Vector3(-6f, 0f, 10),
            new Vector3(-6f, 0f, 8),
            new Vector3(-6f, 0f, 6),
            new Vector3(-6f, 0f, 2),
            new Vector3(-6f, 0f, 0),
            new Vector3(-6f, 0f, -2),
            new Vector3(-6f, 0f, -4),
            new Vector3(-6f, 0f, -8),
            new Vector3(-6f, 0f, -10),
            new Vector3(-6f, 0f, -12),
            new Vector3(-6f, 0f, -14),
            new Vector3(-6f, 0f, -46),
            new Vector3(-6f, 0f, -48),
            new Vector3(-8f, 0f, 46),
            new Vector3(-8f, 0f, 28),
            new Vector3(-8f, 0f, 26),
            new Vector3(-8f, 0f, 24),
            new Vector3(-8f, 0f, 22),
            new Vector3(-8f, 0f, 14),
            new Vector3(-8f, 0f, 12),
            new Vector3(-8f, 0f, 10),
            new Vector3(-8f, 0f, 8),
            new Vector3(-8f, 0f, 6),
            new Vector3(-8f, 0f, 2),
            new Vector3(-8f, 0f, 0),
            new Vector3(-8f, 0f, -2),
            new Vector3(-8f, 0f, -4),
            new Vector3(-8f, 0f, -8),
            new Vector3(-8f, 0f, -10),
            new Vector3(-8f, 0f, -12),
            new Vector3(-8f, 0f, -14),
            new Vector3(-8f, 0f, -16),
            new Vector3(-8f, 0f, -24),
            new Vector3(-8f, 0f, -26),
            new Vector3(-8f, 0f, -28),
            new Vector3(-8f, 0f, -30),
            new Vector3(-8f, 0f, -46),
            new Vector3(-8f, 0f, -48),
            new Vector3(-10f, 0f, 46),
            new Vector3(-10f, 0f, 30),
            new Vector3(-10f, 0f, 28),
            new Vector3(-10f, 0f, 26),
            new Vector3(-10f, 0f, 24),
            new Vector3(-10f, 0f, 22),
            new Vector3(-10f, 0f, 16),
            new Vector3(-10f, 0f, 14),
            new Vector3(-10f, 0f, 12),
            new Vector3(-10f, 0f, 10),
            new Vector3(-10f, 0f, 8),
            new Vector3(-10f, 0f, 6),
            new Vector3(-10f, 0f, 2),
            new Vector3(-10f, 0f, 0),
            new Vector3(-10f, 0f, -2),
            new Vector3(-10f, 0f, -4),
            new Vector3(-10f, 0f, -8),
            new Vector3(-10f, 0f, -10),
            new Vector3(-10f, 0f, -12),
            new Vector3(-10f, 0f, -14),
            new Vector3(-10f, 0f, -16),
            new Vector3(-10f, 0f, -24),
            new Vector3(-10f, 0f, -26),
            new Vector3(-10f, 0f, -28),
            new Vector3(-10f, 0f, -30),
            new Vector3(-10f, 0f, -46),
            new Vector3(-12f, 0f, 46),
            new Vector3(-12f, 0f, 44),
            new Vector3(-12f, 0f, 28),
            new Vector3(-12f, 0f, 26),
            new Vector3(-12f, 0f, 16),
            new Vector3(-12f, 0f, 14),
            new Vector3(-12f, 0f, 12),
            new Vector3(-12f, 0f, 10),
            new Vector3(-12f, 0f, 8),
            new Vector3(-12f, 0f, 2),
            new Vector3(-12f, 0f, 0),
            new Vector3(-12f, 0f, -2),
            new Vector3(-12f, 0f, -4),
            new Vector3(-12f, 0f, -10),
            new Vector3(-12f, 0f, -12),
            new Vector3(-12f, 0f, -14),
            new Vector3(-12f, 0f, -16),
            new Vector3(-12f, 0f, -18),
            new Vector3(-12f, 0f, -28),
            new Vector3(-12f, 0f, -30),
            new Vector3(-12f, 0f, -46),
            new Vector3(-14f, 0f, 44),
            new Vector3(-14f, 0f, 16),
            new Vector3(-14f, 0f, 14),
            new Vector3(-14f, 0f, 12),
            new Vector3(-14f, 0f, 10),
            new Vector3(-14f, 0f, 2),
            new Vector3(-14f, 0f, 0),
            new Vector3(-14f, 0f, -2),
            new Vector3(-14f, 0f, -12),
            new Vector3(-14f, 0f, -14),
            new Vector3(-14f, 0f, -16),
            new Vector3(-14f, 0f, -18),
            new Vector3(-14f, 0f, -44),
            new Vector3(-14f, 0f, -46),
            new Vector3(-16f, 0f, 44),
            new Vector3(-16f, 0f, 16),
            new Vector3(-16f, 0f, 14),
            new Vector3(-16f, 0f, 12),
            new Vector3(-16f, 0f, 10),
            new Vector3(-16f, 0f, -12),
            new Vector3(-16f, 0f, -14),
            new Vector3(-16f, 0f, -16),
            new Vector3(-16f, 0f, -18),
            new Vector3(-16f, 0f, -44),
            new Vector3(-16f, 0f, -46),
            new Vector3(-18f, 0f, 44),
            new Vector3(-18f, 0f, 42),
            new Vector3(-18f, 0f, 16),
            new Vector3(-18f, 0f, 14),
            new Vector3(-18f, 0f, 12),
            new Vector3(-18f, 0f, 10),
            new Vector3(-18f, 0f, -12),
            new Vector3(-18f, 0f, -14),
            new Vector3(-18f, 0f, -16),
            new Vector3(-18f, 0f, -18),
            new Vector3(-18f, 0f, -44),
            new Vector3(-20f, 0f, 42),
            new Vector3(-20f, 0f, 16),
            new Vector3(-20f, 0f, 14),
            new Vector3(-20f, 0f, 12),
            new Vector3(-20f, 0f, 10),
            new Vector3(-20f, 0f, -12),
            new Vector3(-20f, 0f, -14),
            new Vector3(-20f, 0f, -16),
            new Vector3(-20f, 0f, -18),
            new Vector3(-20f, 0f, -42),
            new Vector3(-20f, 0f, -44),
            new Vector3(-22f, 0f, 42),
            new Vector3(-22f, 0f, 40),
            new Vector3(-22f, 0f, 16),
            new Vector3(-22f, 0f, 14),
            new Vector3(-22f, 0f, 12),
            new Vector3(-22f, 0f, 10),
            new Vector3(-22f, 0f, -12),
            new Vector3(-22f, 0f, -14),
            new Vector3(-22f, 0f, -16),
            new Vector3(-22f, 0f, -18),
            new Vector3(-22f, 0f, -42),
            new Vector3(-24f, 0f, 42),
            new Vector3(-24f, 0f, 40),
            new Vector3(-24f, 0f, 16),
            new Vector3(-24f, 0f, 14),
            new Vector3(-24f, 0f, 12),
            new Vector3(-24f, 0f, 10),
            new Vector3(-24f, 0f, -12),
            new Vector3(-24f, 0f, -14),
            new Vector3(-24f, 0f, -16),
            new Vector3(-24f, 0f, -18),
            new Vector3(-24f, 0f, -40),
            new Vector3(-24f, 0f, -42),
            new Vector3(-26f, 0f, 40),
            new Vector3(-26f, 0f, 38),
            new Vector3(-26f, 0f, 16),
            new Vector3(-26f, 0f, 14),
            new Vector3(-26f, 0f, 12),
            new Vector3(-26f, 0f, 10),
            new Vector3(-26f, 0f, -12),
            new Vector3(-26f, 0f, -14),
            new Vector3(-26f, 0f, -16),
            new Vector3(-26f, 0f, -18),
            new Vector3(-26f, 0f, -38),
            new Vector3(-26f, 0f, -40),
            new Vector3(-28f, 0f, 38),
            new Vector3(-28f, 0f, 36),
            new Vector3(-28f, 0f, -38),
            new Vector3(-28f, 0f, -40),
            new Vector3(-30f, 0f, 38),
            new Vector3(-30f, 0f, 36),
            new Vector3(-30f, 0f, -36),
            new Vector3(-30f, 0f, -38),
            new Vector3(-32f, 0f, 36),
            new Vector3(-32f, 0f, 34),
            new Vector3(-32f, 0f, -34),
            new Vector3(-32f, 0f, -36),
            new Vector3(-34f, 0f, 34),
            new Vector3(-34f, 0f, 32),
            new Vector3(-34f, 0f, -32),
            new Vector3(-34f, 0f, -34),
            new Vector3(-36f, 0f, 32),
            new Vector3(-36f, 0f, 30),
            new Vector3(-36f, 0f, 28),
            new Vector3(-36f, 0f, -30),
            new Vector3(-36f, 0f, -32),
            new Vector3(-38f, 0f, 30),
            new Vector3(-38f, 0f, 28),
            new Vector3(-38f, 0f, 26),
            new Vector3(-38f, 0f, -26),
            new Vector3(-38f, 0f, -28),
            new Vector3(-38f, 0f, -30),
            new Vector3(-40f, 0f, 26),
            new Vector3(-40f, 0f, 24),
            new Vector3(-40f, 0f, 22),
            new Vector3(-40f, 0f, -24),
            new Vector3(-40f, 0f, -26),
            new Vector3(-40f, 0f, -28),
            new Vector3(-42f, 0f, 24),
            new Vector3(-42f, 0f, 22),
            new Vector3(-42f, 0f, 20),
            new Vector3(-42f, 0f, -20),
            new Vector3(-42f, 0f, -22),
            new Vector3(-42f, 0f, -24),
            new Vector3(-44f, 0f, 20),
            new Vector3(-44f, 0f, 18),
            new Vector3(-44f, 0f, 16),
            new Vector3(-44f, 0f, 14),
            new Vector3(-44f, 0f, -14),
            new Vector3(-44f, 0f, -16),
            new Vector3(-44f, 0f, -18),
            new Vector3(-44f, 0f, -20),
            new Vector3(-46f, 0f, 14),
            new Vector3(-46f, 0f, 12),
            new Vector3(-46f, 0f, 10),
            new Vector3(-46f, 0f, 8),
            new Vector3(-46f, 0f, 6),
            new Vector3(-46f, 0f, 4),
            new Vector3(-46f, 0f, -4),
            new Vector3(-46f, 0f, -6),
            new Vector3(-46f, 0f, -8),
            new Vector3(-46f, 0f, -10),
            new Vector3(-46f, 0f, -12),
            new Vector3(-46f, 0f, -14),
            new Vector3(-46f, 0f, -16),
            new Vector3(-48f, 0f, 8),
            new Vector3(-48f, 0f, 6),
            new Vector3(-48f, 0f, 4),
            new Vector3(-48f, 0f, 2),
            new Vector3(-48f, 0f, 0),
            new Vector3(-48f, 0f, -2),
            new Vector3(-48f, 0f, -4),
            new Vector3(-48f, 0f, -6),
            new Vector3(-48f, 0f, -8)
        };

        private ControllerFerryTerminalEvent Controller { get; set; } = null;
        private bool Active { get; set; } = false;

        private void StartTimer()
        {
            if (!_config.EnabledTimer) return;
            timer.In(UnityEngine.Random.Range(_config.MinStartTime, _config.MaxStartTime), () =>
            {
                if (!Active) Start(null);
                else Puts("This event is active now. To finish this event (ftstop), then to start the next one");
            });
        }

        private void Start(BasePlayer player)
        {
            if (!PluginExistsForStart("NpcSpawn") || !PluginExistsForStart("AnimalSpawn")) return;
            Active = true;
            AlertToAllPlayers("PreStart", _config.Chat.Prefix, GetTimeFormat((int)_config.PreStartTime));
            timer.In(_config.PreStartTime, () =>
            {
                Puts($"{Name} has begun");
                if (_config.RemoveBetterNpc && plugins.Exists("BetterNpc")) BetterNpc.Call("DestroyController", "Ferry Terminal");
                ToggleHooks(true);
                Controller = new GameObject().AddComponent<ControllerFerryTerminalEvent>();
                Controller.SpawnEntities();
                if (plugins.Exists("MonumentOwner")) MonumentOwner.Call("RemoveZone", Controller.Monument);
                Controller.EnablePveMode(_config.PveMode, player);
                Interface.Oxide.CallHook($"On{Name}Start", Controller.transform.position, _config.Radius);
                AlertToAllPlayers("Start", _config.Chat.Prefix, MapHelper.PositionToString(Controller.transform.position));
            });
        }

        private void Finish()
        {
            ToggleHooks(false);
            if (ActivePveMode) PveMode.Call("EventRemovePveMode", Name, true);
            if (Controller != null)
            {
                if (plugins.Exists("MonumentOwner")) MonumentOwner.Call("CreateZone", Controller.Monument);
                EnableRadiation(Controller.Puzzle);
                UnityEngine.Object.Destroy(Controller.gameObject);
                Controller = null;
            }
            Active = false;
            SendBalance();
            LootableCrates.Clear();
            AlertToAllPlayers("Finish", _config.Chat.Prefix);
            Interface.Oxide.CallHook($"On{Name}End");
            if (_config.RemoveBetterNpc && plugins.Exists("BetterNpc")) BetterNpc.Call("CreateController", "Ferry Terminal");
            Puts($"{Name} has ended");
            StartTimer();
        }

        internal class ControllerFerryTerminalEvent : FacepunchBehaviour
        {
            private PluginConfig _config => _ins._config;

            internal MonumentInfo Monument { get; set; } = null;
            internal PuzzleReset? Puzzle { get; set; } = null;

            private SphereCollider SphereCollider { get; set; } = null;

            private VendingMachineMapMarker VendingMarker { get; set; } = null;
            private HashSet<MapMarkerGenericRadius> Markers { get; } = new HashSet<MapMarkerGenericRadius>();

            internal CustomTravellingVendor Vendor { get; set; } = null;
            internal CustomBarge Barge { get; set; } = null;
            internal CustomContainerShore ContainerShore { get; set; } = null;
            internal CustomCh47 Ch47 { get; set; } = null;
            internal CustomCargoShipContainer CargoShipContainer { get; set; } = null;

            private Door InsideDoor { get; set; } = null;

            internal HashSet<ScientistNPC> Scientists { get; } = new HashSet<ScientistNPC>();

            internal int TimeToFinish { get; set; } = _ins._config.FinishTime;

            internal HashSet<BasePlayer> Players { get; } = new HashSet<BasePlayer>();
            internal BasePlayer Owner { get; set; } = null;

            private void Awake()
            {
                Monument = _ins.GetMonument();
                transform.position = Monument.transform.position;
                transform.rotation = Monument.transform.rotation;

                Puzzle = GetPuzzleReset(Monument);
                DisableRadiation(Puzzle);

                gameObject.layer = 3;
                SphereCollider = gameObject.AddComponent<SphereCollider>();
                SphereCollider.isTrigger = true;
                SphereCollider.radius = _config.Radius;

                CalculateBrainsPoints();
                CalculateMotorbikeRoutes();

                InsideDoor = GetNearEntity<Door>(GetGlobalPosition(new Vector3(-21f, 1.921f, -8.15f)), 1f, 1 << 21);
                if (InsideDoor != null)
                {
                    InsideDoor.SetOpen(false);
                    InsideDoor.canNpcOpen = false;
                    InsideDoor.canHandOpen = false;
                }

                Invoke(() => { foreach (BasePlayer player in Players) TryTeleportPlayer(player); }, 1f);
            }

            private void OnDestroy()
            {
                if (InsideDoor != null)
                {
                    InsideDoor.canNpcOpen = true;
                    InsideDoor.canHandOpen = true;
                }

                CancelInvoke(InvokeUpdates);

                if (AttackCoroutine != null) ServerMgr.Instance.StopCoroutine(AttackCoroutine);

                if (SphereCollider != null) Destroy(SphereCollider);

                if (VendingMarker.IsExists()) VendingMarker.Kill();
                foreach (MapMarkerGenericRadius marker in Markers) if (marker.IsExists()) marker.Kill();

                foreach (BasePlayer player in Players) CuiHelper.DestroyUi(player, "Tabs_KpucTaJl");

                Vendor?.Destroy();
                ContainerShore?.Destroy();
                Barge?.Destroy();
                Ch47?.Destroy();
                CargoShipContainer?.Destroy();

                foreach (ScientistNPC npc in Scientists) if (npc.IsExists()) npc.Kill();
                DestroyBrains();

                foreach (SecurityPoint point in SecurityPoints) if (point != null) Destroy(point.gameObject);

                DestroyMotorbikes();
            }

            private void OnTriggerEnter(Collider other) => EnterPlayer(other.GetComponentInParent<BasePlayer>());

            internal void EnterPlayer(BasePlayer player)
            {
                if (!player.IsPlayer()) return;
                if (Players.Contains(player)) return;
                Players.Add(player);
                Interface.Oxide.CallHook($"OnPlayerEnter{_ins.Name}", player);
                if (_config.IsCreateZonePvp) _ins.AlertToPlayer(player, _ins.GetMessage("EnterPVP", player.UserIDString, _config.Chat.Prefix));
                if (_config.Gui.IsGui) UpdateGui(player);
            }

            private void OnTriggerExit(Collider other) => ExitPlayer(other.GetComponentInParent<BasePlayer>());

            internal void ExitPlayer(BasePlayer player)
            {
                if (!player.IsPlayer()) return;
                if (!Players.Contains(player)) return;
                Players.Remove(player);
                Interface.Oxide.CallHook($"OnPlayerExit{_ins.Name}", player);
                if (_config.IsCreateZonePvp) _ins.AlertToPlayer(player, _ins.GetMessage("ExitPVP", player.UserIDString, _config.Chat.Prefix));
                if (_config.Gui.IsGui) CuiHelper.DestroyUi(player, "Tabs_KpucTaJl");
            }

            private void InvokeUpdates()
            {
                if (_config.Gui.IsGui) foreach (BasePlayer player in Players) UpdateGui(player);
                if (_config.Marker.Enabled) UpdateVendingMarker();
                UpdateMarkerForPlayers();
                UpdateTimeToFinish();
                UpdateNoDamageDrivers();
            }

            private void UpdateGui(BasePlayer player)
            {
                Dictionary<string, string> dic = new Dictionary<string, string>();

                if (HostagesEvacuations.Count == 0) dic.Add("Clock_KpucTaJl", GetTimeFormat(TimeToFinish));

                int countNpc = Scientists.Count + BrainAnimations.Where(x => !x.IsHostage).Count + Guards.Count + Wolves.Count + Motorbikes.Count + Drivers.Count;
                if (countNpc > 0) dic.Add("Npc_KpucTaJl", $"{countNpc}");

                int countCrates = Barge.HackCrates.Count;
                int countWaitingNpc = BrainAnimations.Where(x => x.IsHostage).Count + Hostages.Count + HostagesEvacuations.Where(x => x.HackCrate == null || x.HackCrate.HasFlag(BaseEntity.Flags.Busy)).Count;
                int countSavedNpc = 4 - countCrates + Barge.HackCrates.Where(x => !x.HasFlag(BaseEntity.Flags.Busy)).Count;
                int countDeadNpc = 4 - countWaitingNpc - countSavedNpc;

                dic.Add("Hostage_KpucTaJl", $"{countWaitingNpc} / 4");
                dic.Add("Crate_KpucTaJl", $"{countCrates - countDeadNpc} / 4");

                _ins.CreateTabs(player, dic);
            }

            private void SpawnMapMarker(MarkerConfig config)
            {
                if (!config.Enabled) return;

                MapMarkerGenericRadius background = GameManager.server.CreateEntity("assets/prefabs/tools/map/genericradiusmarker.prefab", transform.position) as MapMarkerGenericRadius;
                background.Spawn();
                background.radius = config.Type == 0 ? config.Radius : 0.37967f;
                background.alpha = config.Alpha;
                background.color1 = new Color(config.Color.R, config.Color.G, config.Color.B);
                background.color2 = new Color(config.Color.R, config.Color.G, config.Color.B);
                Markers.Add(background);

                if (config.Type == 1)
                {
                    foreach (Vector3 pos in _ins.Marker)
                    {
                        MapMarkerGenericRadius marker = GameManager.server.CreateEntity("assets/prefabs/tools/map/genericradiusmarker.prefab", transform.position + pos) as MapMarkerGenericRadius;
                        marker.Spawn();
                        marker.radius = 0.008f;
                        marker.alpha = 1f;
                        marker.color1 = new Color(config.Color.R, config.Color.G, config.Color.B);
                        marker.color2 = new Color(config.Color.R, config.Color.G, config.Color.B);
                        Markers.Add(marker);
                    }
                }

                VendingMarker = GameManager.server.CreateEntity("assets/prefabs/deployable/vendingmachine/vending_mapmarker.prefab", transform.position) as VendingMachineMapMarker;
                VendingMarker.Spawn();

                UpdateVendingMarker();
                UpdateMapMarkers();
            }

            private void UpdateVendingMarker()
            {
                VendingMarker.markerShopName = $"{_config.Marker.Text}";
                VendingMarker.markerShopName += $"\n{GetTimeFormat(TimeToFinish)}";
                if (_ins.ActivePveMode) VendingMarker.markerShopName += Owner == null ? "\nNo Owner" : $"\n{Owner.displayName}";
                VendingMarker.SendNetworkUpdate();
            }

            internal void UpdateMapMarkers() { foreach (MapMarkerGenericRadius marker in Markers) marker.SendUpdate(); }

            private void UpdateMarkerForPlayers()
            {
                if (Players.Count == 0) return;

                if (_config.MainPoint.Enabled)
                {
                    HashSet<Vector3> points = new HashSet<Vector3>();
                    foreach (BasePlayer hostage in Hostages) points.Add(hostage.transform.position);
                    foreach (HostageEvacuation hostageEvacuation in HostagesEvacuations) points.Add(hostageEvacuation.Hostage.transform.position);
                    if (points.Count > 0) foreach (BasePlayer player in Players) foreach (Vector3 point in points) UpdateMarkerForPlayer(player, point, _config.MainPoint);
                    points = null;
                }

                if (_config.AdditionalPoint.Enabled)
                {
                    HashSet<Vector3> points = new HashSet<Vector3>();
                    foreach (HackableLockedCrate crate in Barge.HackCrates) if (!crate.HasFlag(BaseEntity.Flags.Busy)) points.Add(crate.transform.position);
                    if (points.Count > 0) foreach (BasePlayer player in Players) foreach (Vector3 point in points) UpdateMarkerForPlayer(player, point, _config.AdditionalPoint);
                    points = null;
                }
            }

            private void UpdateTimeToFinish()
            {
                if (HostagesEvacuations.Count > 0) return;
                if (TimeToFinish == _config.PreFinishTime) _ins.AlertToAllPlayers("PreFinish", _config.Chat.Prefix, GetTimeFormat(_config.PreFinishTime));
                TimeToFinish--;
                if (TimeToFinish == 0)
                {
                    CancelInvoke(InvokeUpdates);
                    _ins.Finish();
                }
            }

            internal void SpawnEntities()
            {
                Vendor = new CustomTravellingVendor();
                ContainerShore = new CustomContainerShore();
                Barge = new CustomBarge();

                foreach (PresetConfig preset in _config.NpcPresets) SpawnPreset(preset);

                SpawnMapMarker(_config.Marker);

                InvokeRepeating(InvokeUpdates, 0f, 1f);
            }

            internal Vector3 GetGlobalPosition(Vector3 localPosition) => transform.TransformPoint(localPosition);

            internal Quaternion GetGlobalRotation(Vector3 localRotation) => transform.rotation * Quaternion.Euler(localRotation);

            private static T GetNearEntity<T>(Vector3 position, float radius, int layerMask) where T : BaseEntity
            {
                List<T> list = Pool.Get<List<T>>();
                Vis.Entities<T>(position, radius, list, layerMask);
                T result = list.Count == 0 ? null : list.Min(s => Vector3.Distance(position, s.transform.position));
                Pool.FreeUnmanaged(ref list);
                return result;
            }

            public JObject GetObjectConfig(AdditionalNpcConfig config, string home, bool isIdle, bool isWeapon)
            {
                HashSet<string> states = new HashSet<string>();
                if (isIdle) states.Add("IdleState");
                else
                {
                    states.Add("RoamState");
                    states.Add("ChaseState");
                    states.Add("CombatState");
                    if (config.BeltItems.Any(x => x.ShortName is "rocket.launcher" or "explosive.timed")) states.Add("RaidState");
                }
                return new JObject
                {
                    ["Name"] = config.Name,
                    ["WearItems"] = new JArray { config.WearItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["SkinID"] = x.SkinId }) },
                    ["BeltItems"] = isWeapon ? new JArray { config.BeltItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["Amount"] = x.Amount, ["SkinID"] = x.SkinId, ["Mods"] = new JArray { x.Mods }, ["Ammo"] = x.Ammo }) } : new JArray(),
                    ["Kit"] = config.Kit,
                    ["Health"] = config.Health,
                    ["RoamRange"] = config.RoamRange,
                    ["ChaseRange"] = config.ChaseRange,
                    ["SenseRange"] = config.SenseRange,
                    ["ListenRange"] = config.SenseRange / 2f,
                    ["AttackRangeMultiplier"] = config.AttackRangeMultiplier,
                    ["CheckVisionCone"] = config.CheckVisionCone,
                    ["HostileTargetsOnly"] = false,
                    ["VisionCone"] = config.VisionCone,
                    ["DamageScale"] = config.DamageScale,
                    ["TurretDamageScale"] = 0f,
                    ["AimConeScale"] = config.AimConeScale,
                    ["DisableRadio"] = config.DisableRadio,
                    ["CanRunAwayWater"] = true,
                    ["CanSleep"] = false,
                    ["SleepDistance"] = 100f,
                    ["Speed"] = config.Speed,
                    ["AreaMask"] = 1,
                    ["AgentTypeID"] = -1372625422,
                    ["HomePosition"] = home,
                    ["MemoryDuration"] = config.MemoryDuration,
                    ["States"] = new JArray { states }
                };
            }

            private static JObject GetObjectConfig(NpcConfig config)
            {
                HashSet<string> states = config.Stationary ? new HashSet<string> { "IdleState", "CombatStationaryState" } : new HashSet<string> { "RoamState", "ChaseState", "CombatState" };
                if (config.BeltItems.Any(x => x.ShortName is "rocket.launcher" or "explosive.timed")) states.Add("RaidState");
                return new JObject
                {
                    ["Name"] = config.Name,
                    ["WearItems"] = new JArray { config.WearItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["SkinID"] = x.SkinId }) },
                    ["BeltItems"] = new JArray { config.BeltItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["Amount"] = x.Amount, ["SkinID"] = x.SkinId, ["Mods"] = new JArray { x.Mods }, ["Ammo"] = x.Ammo }) },
                    ["Kit"] = config.Kit,
                    ["Health"] = config.Health,
                    ["RoamRange"] = config.RoamRange,
                    ["ChaseRange"] = config.ChaseRange,
                    ["SenseRange"] = config.SenseRange,
                    ["ListenRange"] = config.SenseRange / 2f,
                    ["AttackRangeMultiplier"] = config.AttackRangeMultiplier,
                    ["CheckVisionCone"] = config.CheckVisionCone,
                    ["VisionCone"] = config.VisionCone,
                    ["HostileTargetsOnly"] = false,
                    ["DamageScale"] = config.DamageScale,
                    ["TurretDamageScale"] = 0f,
                    ["AimConeScale"] = config.AimConeScale,
                    ["DisableRadio"] = config.DisableRadio,
                    ["CanRunAwayWater"] = true,
                    ["CanSleep"] = false,
                    ["SleepDistance"] = 100f,
                    ["Speed"] = config.Speed,
                    ["AreaMask"] = 1,
                    ["AgentTypeID"] = -1372625422,
                    ["HomePosition"] = string.Empty,
                    ["MemoryDuration"] = config.MemoryDuration,
                    ["States"] = new JArray { states }
                };
            }

            private JObject GetWolfObjectConfig(string home)
            {
                return new JObject
                {
                    ["Prefab"] = "assets/rust.ai/agents/wolf/wolf.prefab",
                    ["Health"] = _config.Wolf.Health,
                    ["RoamRange"] = _config.Wolf.RoamRange,
                    ["ChaseRange"] = _config.Wolf.ChaseRange,
                    ["SenseRange"] = _config.Wolf.SenseRange,
                    ["ListenRange"] = _config.Wolf.SenseRange / 2f,
                    ["AttackRange"] = _config.Wolf.AttackRange,
                    ["CheckVisionCone"] = _config.Wolf.CheckVisionCone,
                    ["VisionCone"] = _config.Wolf.VisionCone,
                    ["HostileTargetsOnly"] = false,
                    ["AttackDamage"] = _config.Wolf.AttackDamage,
                    ["AttackRate"] = _config.Wolf.AttackRate,
                    ["TurretDamageScale"] = 0f,
                    ["CanRunAwayWater"] = true,
                    ["CanSleep"] = false,
                    ["SleepDistance"] = 100f,
                    ["Speed"] = _config.Wolf.Speed,
                    ["AreaMask"] = 1,
                    ["AgentTypeID"] = -1372625422,
                    ["HomePosition"] = home,
                    ["MemoryDuration"] = _config.Wolf.MemoryDuration,
                    ["States"] = new JArray { "IdleState" }
                };
            }

            private void SpawnPreset(PresetConfig preset)
            {
                int count = UnityEngine.Random.Range(preset.Min, preset.Max + 1);

                List<Vector3> positions = Pool.Get<List<Vector3>>();
                foreach (string pos in preset.Positions) positions.Add(GetGlobalPosition(pos.ToVector3()));

                JObject config = GetObjectConfig(preset.Config);

                for (int i = 0; i < count; i++)
                {
                    Vector3 pos = positions.GetRandom();
                    positions.Remove(pos);

                    ScientistNPC npc = (ScientistNPC)_ins.NpcSpawn.Call("SpawnNpc", pos, config);
                    Scientists.Add(npc);
                }

                Pool.FreeUnmanaged(ref positions);
            }

            #region Brains
            internal Coroutine SpawnBrainsCoroutine { get; set; } = null;
            private HashSet<BrainPath> BrainPaths { get; set; } = null;
            public HostageEvacuationData HostageEvacuationData { get; set; } = null;
            internal HashSet<BrainAnimation> BrainAnimations { get; set; } = new HashSet<BrainAnimation>();
            internal HashSet<HostageEvacuation> HostagesEvacuations { get; set; } = new HashSet<HostageEvacuation>();
            internal HashSet<BaseAnimalNPC> Wolves { get; set; } = new HashSet<BaseAnimalNPC>();
            internal HashSet<ScientistNPC> Guards { get; set; } = new HashSet<ScientistNPC>();
            internal HashSet<BasePlayer> Hostages { get; set; } = new HashSet<BasePlayer>();

            internal void TryAttackWolf(Vector3 pos, BasePlayer attacker)
            {
                BaseAnimalNPC wolf = Wolves.Min(x => Vector3.Distance(pos, x.transform.position));
                if (wolf == null) return;
                if (Vector3.Distance(pos, wolf.transform.position) > _config.SecurityNpc.RoamRange + _config.Wolf.RoamRange) return;
                wolf.brain.Senses.Memory.SetKnown(attacker, wolf, wolf.brain.Senses);
            }

            private void CalculateBrainsPoints()
            {
                BrainPaths = new HashSet<BrainPath>
                {
                    new BrainPath
                    {
                        Wolf = new BrainPositions
                        {
                            SpawnPosition = GetGlobalPosition(new Vector3(-20.986f, 5.138f, -19.772f)),
                            InitialPathPoint = GetGlobalPosition(new Vector3(6.568f, 5.25f, -19.772f)),
                            HomePositions = new List<Vector3>
                            {
                                GetGlobalPosition(new Vector3(15.197f, 5.25f, -19.806f)),
                                GetGlobalPosition(new Vector3(25.4f, 5.25f, -19.806f))
                            }
                        },
                        Hostage = new BrainPositions
                        {
                            SpawnPosition = GetGlobalPosition(new Vector3(-20.986f, 5.138f, -20.772f)),
                            InitialPathPoint = GetGlobalPosition(new Vector3(6.568f, 5.25f, -20.772f)),
                            HomePositions = new List<Vector3>
                            {
                                GetGlobalPosition(new Vector3(19.5f, 5.248f, -16f)),
                                GetGlobalPosition(new Vector3(21.5f, 5.248f, -16f))
                            }
                        },
                        Guard = new BrainPositions
                        {
                            SpawnPosition = GetGlobalPosition(new Vector3(-20.986f, 5.138f, -21.772f)),
                            InitialPathPoint = GetGlobalPosition(new Vector3(6.568f, 5.25f, -21.772f)),
                            HomePositions = new List<Vector3>
                            {
                                GetGlobalPosition(new Vector3(15.197f, 5.25f, -19.806f)),
                                GetGlobalPosition(new Vector3(25.4f, 5.25f, -19.806f))
                            }
                        },
                        PathPoints = new HashSet<Vector3>
                        {
                            GetGlobalPosition(new Vector3(10.249f, 5.25f, -17.5f)),
                            GetGlobalPosition(new Vector3(20.5f, 5.25f, -17.5f))
                        }
                    },
                    new BrainPath
                    {
                        Wolf = new BrainPositions
                        {
                            SpawnPosition = GetGlobalPosition(new Vector3(-20.986f, 5.138f, -19.772f)),
                            InitialPathPoint = GetGlobalPosition(new Vector3(6.568f, 5.25f, -19.772f)),
                            HomePositions = new List<Vector3>
                            {
                                GetGlobalPosition(new Vector3(26.566f, 5.25f, -0.216f)),
                                GetGlobalPosition(new Vector3(16.234f, 5.25f, 1.825f))
                            }
                        },
                        Hostage = new BrainPositions
                        {
                            SpawnPosition = GetGlobalPosition(new Vector3(-20.986f, 5.138f, -20.772f)),
                            InitialPathPoint = GetGlobalPosition(new Vector3(6.568f, 5.25f, -20.772f)),
                            HomePositions = new List<Vector3>
                            {
                                GetGlobalPosition(new Vector3(21.5f, 5.248f, -2f)),
                                GetGlobalPosition(new Vector3(19.5f, 5.248f, -2f))
                            }
                        },
                        Guard = new BrainPositions
                        {
                            SpawnPosition = GetGlobalPosition(new Vector3(-20.986f, 5.138f, -21.772f)),
                            InitialPathPoint = GetGlobalPosition(new Vector3(6.568f, 5.25f, -21.772f)),
                            HomePositions = new List<Vector3>
                            {
                                GetGlobalPosition(new Vector3(26.566f, 5.25f, -0.216f)),
                                GetGlobalPosition(new Vector3(16.234f, 5.25f, 1.825f))
                            }
                        },
                        PathPoints = new HashSet<Vector3>
                        {
                            GetGlobalPosition(new Vector3(10.756f, 5.25f, -14.92f)),
                            GetGlobalPosition(new Vector3(15.357f, 5.25f, -11.737f)),
                            GetGlobalPosition(new Vector3(17.494f, 5.25f, -8.557f)),
                            GetGlobalPosition(new Vector3(15.487f, 5.25f, -5.246f)),
                            GetGlobalPosition(new Vector3(12.136f, 5.25f, -3.106f)),
                            GetGlobalPosition(new Vector3(14.074f, 5.247f, -0.5f)),
                            GetGlobalPosition(new Vector3(20.5f, 5.247f, -0.5f))
                        }
                    }
                };
                HostageEvacuationData = new HostageEvacuationData
                {
                    InitialPoints = new Dictionary<int, List<Vector3>>
                    {
                        [0] = new List<Vector3>
                        {
                            GetGlobalPosition(new Vector3(20.5f, 5.25f, -17.5f)),
                            GetGlobalPosition(new Vector3(24.772f, 5.246f, -17.5f))
                        },
                        [1] = new List<Vector3>
                        {
                            GetGlobalPosition(new Vector3(20.5f, 5.247f, -0.5f)),
                            GetGlobalPosition(new Vector3(27.497f, 5.246f, -0.5f))
                        }
                    },
                    Path1 = new HashSet<Vector3>
                    {
                        GetGlobalPosition(new Vector3(29.092f, 5.246f, 10.782f)),
                        GetGlobalPosition(new Vector3(52.591f, 5.128f, 30.392f)),
                        GetGlobalPosition(new Vector3(63.704f, 6.217f, 39.787f)),
                        GetGlobalPosition(new Vector3(69.877f, 6.601f, 43.305f)),
                        GetGlobalPosition(new Vector3(71.62f, 6.626f, 47.398f)),
                        GetGlobalPosition(new Vector3(71.633f, 6.715f, 48.844f)),
                        GetGlobalPosition(new Vector3(71.632f, 6.29f, 50.383f)),
                        GetGlobalPosition(new Vector3(69.001f, 2.495f, 64.784f))
                    },
                    AnimationStartPoint = GetGlobalPosition(new Vector3(69.001f, 1.05f, 64.784f)),
                    AnimationEndPoint = GetGlobalPosition(new Vector3(69.001f, 1.05f, 65.845f)),
                    Path2 = new HashSet<Vector3>
                    {
                        GetGlobalPosition(new Vector3(69.001f, 1.05f, 69.502f)),
                        GetGlobalPosition(new Vector3(68.997f, 2.295f, 75.494f)),
                        GetGlobalPosition(new Vector3(68.996f, 2.3f, 83.231f))
                    },
                    HackCratePoints = new HashSet<Vector3>
                    {
                        GetGlobalPosition(new Vector3(68f, 2.3f, 84.625f)),
                        GetGlobalPosition(new Vector3(70f, 2.3f, 84.625f)),
                        GetGlobalPosition(new Vector3(68f, 2.3f, 89.424f)),
                        GetGlobalPosition(new Vector3(70f, 2.3f, 89.424f))
                    },
                    ExitPoints = new List<Vector3>
                    {
                        GetGlobalPosition(new Vector3(68.278f, 2.3f, 93.402f)),
                        GetGlobalPosition(new Vector3(69.682f, 2.3f, 93.402f))
                    },
                    WaveLookPoint = GetGlobalPosition(new Vector3(68.996f, 2.3f, 83.231f))
                };
            }

            internal IEnumerator SpawnBrains()
            {
                foreach (BrainPath path in BrainPaths)
                {
                    for (int i = 0; i < 2; i++)
                    {
                        Vector3 homeWolf = path.Wolf.HomePositions.GetRandom();
                        path.Wolf.HomePositions.Remove(homeWolf);
                        BrainAnimation wolf = new BrainAnimation(_ins.AnimalSpawn, "SpawnAnimal", 0f, path.PathPoints, path.Wolf, GetWolfObjectConfig(homeWolf.ToString()));
                        BrainAnimations.Add(wolf);

                        BrainAnimation hostage = new BrainAnimation(null, string.Empty, 1f, path.PathPoints, path.Hostage, null);
                        BrainAnimations.Add(hostage);

                        Vector3 homeGuard = path.Guard.HomePositions.GetRandom();
                        path.Guard.HomePositions.Remove(homeGuard);
                        BrainAnimation guard = new BrainAnimation(_ins.NpcSpawn, "SpawnNpc", 2f, path.PathPoints, path.Guard, GetObjectConfig(_config.SecurityNpc, homeGuard.ToString(), true, true));
                        BrainAnimations.Add(guard);

                        yield return CoroutineEx.waitForSeconds(6f);
                    }
                }
            }

            internal void FinishPathBrain(BaseEntity entity)
            {
                BrainAnimation brainAnimation = BrainAnimations.FirstOrDefault(x => x.Entity == entity);
                if (brainAnimation != null)
                {
                    brainAnimation.FinishPath();
                    return;
                }

                HostageEvacuation evacuation = HostagesEvacuations.FirstOrDefault(x => x.Hostage == entity);
                evacuation?.FinishPath();
            }

            internal BasePlayer GetRandomHostage()
            {
                if (HostagesEvacuations.Count == 0) return null;
                List<BasePlayer> results = Pool.Get<List<BasePlayer>>();
                foreach (HostageEvacuation process in HostagesEvacuations)
                {
                    if (process.IsFinishPath1) continue;
                    if (process.Hostage == null) continue;
                    results.Add(process.Hostage);
                }
                BasePlayer result = results.GetRandom();
                Pool.FreeUnmanaged(ref results);
                return result;
            }

            internal bool IsHostage(BasePlayer player)
            {
                if (player == null) return false;
                return Hostages.Contains(player) || HostagesEvacuations.Any(x => x.Hostage == player);
            }

            internal void AllHostagesLiberatedOrDied()
            {
                if (TimeToFinish > _config.PreFinishTime) TimeToFinish = _config.PreFinishTime;

                Vector3 newHome = GetGlobalPosition(new Vector3(69.108f, 3.471f, 60.912f));

                foreach (ScientistNPC npc in Guards) _ins.NpcSpawn.Call("SetHomePosition", npc, newHome);
                foreach (BaseAnimalNPC animal in Wolves) _ins.AnimalSpawn.Call("SetHomePosition", animal, newHome);
            }

            private void DestroyBrains()
            {
                if (SpawnBrainsCoroutine != null) ServerMgr.Instance.StopCoroutine(SpawnBrainsCoroutine);

                BrainPaths = null;
                HostageEvacuationData = null;

                foreach (BrainAnimation animation in BrainAnimations) animation.Destroy();
                foreach (HostageEvacuation animation in HostagesEvacuations) animation.Destroy();

                foreach (BaseAnimalNPC wolf in Wolves) if (wolf.IsExists()) wolf.Kill();
                foreach (ScientistNPC guard in Guards) if (guard.IsExists()) guard.Kill();
                foreach (BasePlayer hostage in Hostages) if (hostage.IsExists()) hostage.Kill();
            }
            #endregion Brains

            #region Security Point
            internal HashSet<SecurityPoint> SecurityPoints { get; } = new HashSet<SecurityPoint>();

            internal void SpawnSecurityPoint(BasePlayer hostage)
            {
                SecurityPoint point = new GameObject().AddComponent<SecurityPoint>();
                point.Init(hostage);
                SecurityPoints.Add(point);
            }

            internal void FinishSecurity(SecurityPoint point)
            {
                BasePlayer hostage = point.Hostage;

                SecurityPoints.Remove(point);
                Destroy(point.gameObject);

                HostageEvacuation animation = new HostageEvacuation(hostage);
                HostagesEvacuations.Add(animation);

                _ins.AlertToAllPlayers("LiberateHostage", _config.Chat.Prefix);

                if (TimeToFinish < _config.Hostage.IncreaseTime) TimeToFinish += _config.Hostage.IncreaseTime;
            }
            #endregion Security Point

            #region Motorbike
            private HashSet<MotorbikeRoute> MotorbikeRoutes { get; set; } = null;
            internal HashSet<Motorbike> Motorbikes { get; } = new HashSet<Motorbike>();
            internal HashSet<ScientistNPC> Drivers { get; } = new HashSet<ScientistNPC>();

            private void CalculateMotorbikeRoutes()
            {
                MotorbikeRoutes = new HashSet<MotorbikeRoute>
                {
                    new MotorbikeRoute
                    {
                        Spawn = new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.593f, 109.46f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                        Steps = new HashSet<MotorbikeStep>
                        {
                            new MotorbikeStep
                            {
                                StartSpeed = 0f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.628f, 108.721f)), GetGlobalRotation(new Vector3(356.996f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.655f, 107.982f)), GetGlobalRotation(new Vector3(354.159f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.655f, 107.238f)), GetGlobalRotation(new Vector3(354.159f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.528f, 106.583f)), GetGlobalRotation(new Vector3(354.159f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.41f, 105.835f)), GetGlobalRotation(new Vector3(354.159f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.303f, 105.074f)), GetGlobalRotation(new Vector3(354.159f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.151f, 104.304f)), GetGlobalRotation(new Vector3(0.43f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.105f, 102.887f)), GetGlobalRotation(new Vector3(3.166f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 13.025f, 101.438f)), GetGlobalRotation(new Vector3(2.653f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.986f, 99.996f)), GetGlobalRotation(new Vector3(1.386f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.933f, 98.56f)), GetGlobalRotation(new Vector3(0.44f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.933f, 97.111f)), GetGlobalRotation(new Vector3(0.44f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.933f, 95.687f)), GetGlobalRotation(new Vector3(0.44f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.933f, 94.237f)), GetGlobalRotation(new Vector3(0.44f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.933f, 92.79f)), GetGlobalRotation(new Vector3(0.44f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.575f, 12.933f, 91.352f)), GetGlobalRotation(new Vector3(0.44f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.514f, 12.921f, 90.31f)), GetGlobalRotation(new Vector3(0.334f, 184.006f, 355.535f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.412f, 12.944f, 88.886f)), GetGlobalRotation(new Vector3(359.33f, 184.085f, 355.535f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.282f, 12.959f, 87.446f)), GetGlobalRotation(new Vector3(359.681f, 188.558f, 354.222f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(85.075f, 12.966f, 86.069f)), GetGlobalRotation(new Vector3(359.681f, 188.558f, 354.222f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.877f, 12.976f, 84.655f)), GetGlobalRotation(new Vector3(359.549f, 186.657f, 356.013f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.71f, 13.018f, 83.228f)), GetGlobalRotation(new Vector3(358.157f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.544f, 13.064f, 81.81f)), GetGlobalRotation(new Vector3(358.157f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.378f, 13.11f, 80.384f)), GetGlobalRotation(new Vector3(358.157f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.298f, 13.251f, 79.697f)), GetGlobalRotation(new Vector3(348.38f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.194f, 13.443f, 78.81f)), GetGlobalRotation(new Vector3(335.18f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.102f, 13.49f, 78.018f)), GetGlobalRotation(new Vector3(335.18f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(84.028f, 13.445f, 77.39f)), GetGlobalRotation(new Vector3(345.618f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.936f, 13.275f, 76.599f)), GetGlobalRotation(new Vector3(355.704f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.772f, 13.271f, 75.19f)), GetGlobalRotation(new Vector3(356.849f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.609f, 13.349f, 73.795f)), GetGlobalRotation(new Vector3(356.849f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.516f, 13.424f, 73f)), GetGlobalRotation(new Vector3(347.367f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.425f, 13.419f, 72.217f)), GetGlobalRotation(new Vector3(347.367f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.332f, 13.332f, 71.423f)), GetGlobalRotation(new Vector3(353.747f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.166f, 13.36f, 69.996f)), GetGlobalRotation(new Vector3(357.016f, 186.657f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.057f, 13.436f, 68.544f)), GetGlobalRotation(new Vector3(357.024f, 182.563f, 0.213f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.051f, 13.511f, 67.094f)), GetGlobalRotation(new Vector3(357.053f, 177.636f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.111f, 13.586f, 65.637f)), GetGlobalRotation(new Vector3(357.053f, 177.636f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.172f, 13.661f, 64.182f)), GetGlobalRotation(new Vector3(357.053f, 177.636f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.231f, 13.735f, 62.74f)), GetGlobalRotation(new Vector3(357.053f, 177.636f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.291f, 13.809f, 61.297f)), GetGlobalRotation(new Vector3(357.053f, 177.636f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.35f, 13.884f, 59.857f)), GetGlobalRotation(new Vector3(357.053f, 177.636f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.41f, 14.006f, 58.414f)), GetGlobalRotation(new Vector3(354.414f, 177.614f, 0.469f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.47f, 14.14f, 56.979f)), GetGlobalRotation(new Vector3(355.056f, 177.619f, 0.469f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.53f, 14.238f, 55.539f)), GetGlobalRotation(new Vector3(356.374f, 177.63f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.59f, 14.33f, 54.085f)), GetGlobalRotation(new Vector3(356.374f, 177.63f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.651f, 14.47f, 52.608f)), GetGlobalRotation(new Vector3(351.419f, 177.589f, 0.472f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.685f, 14.593f, 51.81f)), GetGlobalRotation(new Vector3(346.552f, 177.548f, 0.48f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.722f, 14.661f, 50.903f)), GetGlobalRotation(new Vector3(346.552f, 177.548f, 0.48f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.747f, 14.759f, 50.31f)), GetGlobalRotation(new Vector3(355.909f, 177.626f, 0.468f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.803f, 14.66f, 48.87f)), GetGlobalRotation(new Vector3(0.541f, 187.584f, 346.964f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(83.516f, 14.685f, 47.419f)), GetGlobalRotation(new Vector3(359.322f, 203.434f, 338.505f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(82.91f, 14.683f, 46.143f)), GetGlobalRotation(new Vector3(0.776f, 207.124f, 338.508f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(82.553f, 14.633f, 45.413f)), GetGlobalRotation(new Vector3(354.693f, 209.525f, 338.413f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(81.963f, 14.504f, 44.157f)), GetGlobalRotation(new Vector3(1.072f, 207.007f, 338.506f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(81.304f, 14.472f, 42.861f)), GetGlobalRotation(new Vector3(1.072f, 207.007f, 338.506f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(80.622f, 14.432f, 41.594f)), GetGlobalRotation(new Vector3(1.998f, 209.364f, 338.569f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(79.918f, 14.382f, 40.344f)), GetGlobalRotation(new Vector3(1.998f, 209.364f, 343.41f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(79.211f, 14.331f, 39.087f)), GetGlobalRotation(new Vector3(1.998f, 209.364f, 348.991f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(78.5f, 14.284f, 37.824f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 352.589f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(77.785f, 14.265f, 36.561f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(77.081f, 14.246f, 35.318f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(76.37f, 14.227f, 34.063f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(75.654f, 14.208f, 32.8f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(74.944f, 14.189f, 31.545f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(74.234f, 14.17f, 30.291f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(73.534f, 14.152f, 29.056f)), GetGlobalRotation(new Vector3(0.75f, 209.526f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(72.816f, 14.154f, 27.792f)), GetGlobalRotation(new Vector3(359.885f, 209.583f, 356.273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(72.349f, 14.188f, 26.973f)), GetGlobalRotation(new Vector3(356.012f, 209.835f, 356.264f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(71.936f, 14.254f, 26.253f)), GetGlobalRotation(new Vector3(351.317f, 210.145f, 356.23f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(71.577f, 14.362f, 25.635f)), GetGlobalRotation(new Vector3(344.788f, 210.59f, 356.137f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(71.325f, 14.437f, 25.2f)), GetGlobalRotation(new Vector3(351.34f, 210.144f, 356.23f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(70.922f, 14.381f, 24.472f)), GetGlobalRotation(new Vector3(355.691f, 214f, 352.29f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(70.124f, 14.343f, 23.262f)), GetGlobalRotation(new Vector3(359.523f, 215.19f, 349.536f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(69.249f, 14.332f, 22.11f)), GetGlobalRotation(new Vector3(0.364f, 219.742f, 349.531f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(68.328f, 14.308f, 21.018f)), GetGlobalRotation(new Vector3(0.612f, 221.083f, 349.543f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(67.376f, 14.292f, 19.925f)), GetGlobalRotation(new Vector3(0.612f, 221.083f, 352.616f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(66.434f, 14.277f, 18.844f)), GetGlobalRotation(new Vector3(0.612f, 221.083f, 356.308f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(65.491f, 14.262f, 17.763f)), GetGlobalRotation(new Vector3(0.612f, 221.083f, 359.289f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(64.559f, 14.281f, 16.695f)), GetGlobalRotation(new Vector3(0.691f, 221.083f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(63.623f, 14.28f, 15.62f)), GetGlobalRotation(new Vector3(359.421f, 221.083f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(62.684f, 14.295f, 14.532f)), GetGlobalRotation(new Vector3(359.421f, 219.519f, 0.016f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(61.772f, 14.309f, 13.416f)), GetGlobalRotation(new Vector3(359.422f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(60.873f, 14.324f, 12.283f)), GetGlobalRotation(new Vector3(359.422f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(59.971f, 14.35f, 11.146f)), GetGlobalRotation(new Vector3(358.796f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(59.086f, 14.38f, 10.031f)), GetGlobalRotation(new Vector3(358.796f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(58.193f, 14.41f, 8.905f)), GetGlobalRotation(new Vector3(358.796f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(57.308f, 14.44f, 7.789f)), GetGlobalRotation(new Vector3(358.796f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(56.423f, 14.47f, 6.674f)), GetGlobalRotation(new Vector3(358.796f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(55.548f, 14.499f, 5.571f)), GetGlobalRotation(new Vector3(358.796f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(54.974f, 14.564f, 4.848f)), GetGlobalRotation(new Vector3(355.238f, 218.43f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(54.395f, 14.645f, 4.118f)), GetGlobalRotation(new Vector3(350.309f, 218.428f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(53.959f, 14.715f, 3.568f)), GetGlobalRotation(new Vector3(350.309f, 218.428f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(53.699f, 14.883f, 3.241f)), GetGlobalRotation(new Vector3(346.438f, 218.426f, 0.028f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(53.294f, 14.856f, 2.731f)), GetGlobalRotation(new Vector3(346.438f, 218.426f, 0.028f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(52.798f, 14.776f, 2.106f)), GetGlobalRotation(new Vector3(353.141f, 218.429f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(52.273f, 14.686f, 1.444f)), GetGlobalRotation(new Vector3(357.561f, 218.431f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(51.552f, 14.695f, 0.536f)), GetGlobalRotation(new Vector3(358.495f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(51.046f, 14.717f, -0.103f)), GetGlobalRotation(new Vector3(358.495f, 218.432f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(50.579f, 14.653f, -0.69f)), GetGlobalRotation(new Vector3(355.214f, 218.43f, 0.027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(50.068f, 14.593f, -1.334f)), GetGlobalRotation(new Vector3(358.101f, 218.432f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(49.307f, 14.541f, -2.299f)), GetGlobalRotation(new Vector3(0.559f, 220.861f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(48.694f, 14.53f, -2.995f)), GetGlobalRotation(new Vector3(1.401f, 225.835f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(47.62f, 14.501f, -3.972f)), GetGlobalRotation(new Vector3(1.686f, 236.19f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(46.401f, 14.491f, -4.775f)), GetGlobalRotation(new Vector3(1.534f, 246.533f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(45.444f, 14.52f, -5.165f)), GetGlobalRotation(new Vector3(357.74f, 248.104f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(45.454f, 14.605f, -5.544f)), GetGlobalRotation(new Vector3(353.584f, 249.837f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.552f, 14.713f, -5.875f)), GetGlobalRotation(new Vector3(348.622f, 251.401f, 0f)).eulerAngles)
                                },
                                ForwardForce = 30000f,
                                UpTorque = 0f,
                                RightTorque = -5000f,
                                Time = 1.636f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 5f,
                                NumberBrakingPoints = 9,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(31.61f, 5.383f, -9.894f)), GetGlobalRotation(new Vector3(18.252f, 251.399f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(30.761f, 5.234f, -10.179f)), GetGlobalRotation(new Vector3(7.644f, 251.399f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(29.306f, 5.063f, -10.669f)), GetGlobalRotation(new Vector3(359.509f, 251.399f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(28.613f, 5.062f, -10.929f)), GetGlobalRotation(new Vector3(1.028f, 241.683f, 8.841f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(27.331f, 5.058f, -11.667f)), GetGlobalRotation(new Vector3(0.419f, 232.437f, 20.029f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(26.212f, 5.077f, -12.638f)), GetGlobalRotation(new Vector3(358.951f, 222.138f, 26.952f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(25.312f, 5.074f, -13.801f)), GetGlobalRotation(new Vector3(0.98f, 213.753f, 26.811f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(24.511f, 5.041f, -14.995f)), GetGlobalRotation(new Vector3(359.052f, 213.149f, 17.374f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(23.676f, 5.069f, -16.101f)), GetGlobalRotation(new Vector3(359.69f, 230.4f, 8.545f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(22.563f, 5.068f, -17.022f)), GetGlobalRotation(new Vector3(359.691f, 235.271f, 359.974f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(21.339f, 5.061f, -17.787f)), GetGlobalRotation(new Vector3(359.704f, 247.594f, 352.195f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(19.985f, 5.049f, -18.271f)), GetGlobalRotation(new Vector3(0.135f, 256.071f, 342.542f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(18.526f, 5.055f, -18.5f)), GetGlobalRotation(new Vector3(359.814f, 267.688f, 336.426f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(17.102f, 5.053f, -18.543f)), GetGlobalRotation(new Vector3(0.454f, 269.031f, 334.528f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(15.661f, 5.042f, -18.568f)), GetGlobalRotation(new Vector3(0.454f, 269.031f, 339.764f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(14.179f, 5.048f, -18.523f)), GetGlobalRotation(new Vector3(359.506f, 273.726f, 344.971f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(12.746f, 5.06f, -18.43f)), GetGlobalRotation(new Vector3(359.506f, 273.726f, 351.581f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(11.321f, 5.073f, -18.337f)), GetGlobalRotation(new Vector3(359.506f, 273.726f, 355.501f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(9.88f, 5.07f, -18.243f)), GetGlobalRotation(new Vector3(359.933f, 273.726f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(8.432f, 5.071f, -18.149f)), GetGlobalRotation(new Vector3(359.933f, 273.726f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(6.981f, 5.073f, -18.054f)), GetGlobalRotation(new Vector3(359.933f, 273.726f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(5.551f, 5.075f, -17.961f)), GetGlobalRotation(new Vector3(359.933f, 273.726f, 0f)).eulerAngles)
                                },
                                ForwardForce = 0f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 0f
                            }
                        }
                    },
                    new MotorbikeRoute
                    {
                        Spawn = new PointAnimationTransform(GetGlobalPosition(new Vector3(-60.517f, 0.397f, 13.789f)), GetGlobalRotation(new Vector3(352.048f, 164.056f, 0f)).eulerAngles),
                        Steps = new HashSet<MotorbikeStep>
                        {
                            new MotorbikeStep
                            {
                                StartSpeed = 0f,
                                NumberBrakingPoints = 10,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-60.125f, 0.597f, 12.415f)), GetGlobalRotation(new Vector3(352.048f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-59.733f, 0.796f, 11.044f)), GetGlobalRotation(new Vector3(352.048f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-59.514f, 0.889f, 10.277f)), GetGlobalRotation(new Vector3(343.198f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-59.244f, 0.881f, 9.333f)), GetGlobalRotation(new Vector3(351.711f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-58.857f, 1.073f, 7.976f)), GetGlobalRotation(new Vector3(351.509f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-58.468f, 1.271f, 6.614f)), GetGlobalRotation(new Vector3(351.509f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-58.082f, 1.475f, 5.264f)), GetGlobalRotation(new Vector3(351.509f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-57.693f, 1.671f, 3.902f)), GetGlobalRotation(new Vector3(351.509f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-57.3f, 1.885f, 2.527f)), GetGlobalRotation(new Vector3(351.509f, 164.056f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-56.9f, 2.052f, 1.149f)), GetGlobalRotation(new Vector3(353.376f, 171.312f, 351.689f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-56.683f, 2.218f, -0.269f)), GetGlobalRotation(new Vector3(352.654f, 173.697f, 347.919f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-56.528f, 2.401f, -1.675f)), GetGlobalRotation(new Vector3(352.654f, 173.697f, 347.919f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-56.374f, 2.599f, -3.095f)), GetGlobalRotation(new Vector3(352.078f, 173.821f, 347.903f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-56.222f, 2.796f, -4.504f)), GetGlobalRotation(new Vector3(352.078f, 173.821f, 351.366f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-56.066f, 2.991f, -5.932f)), GetGlobalRotation(new Vector3(352.078f, 173.821f, 354.018f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-55.919f, 3.171f, -7.281f)), GetGlobalRotation(new Vector3(353.941f, 173.625f, 354.042f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-55.762f, 3.351f, -8.701f)), GetGlobalRotation(new Vector3(350.786f, 173.747f, 357.789f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-55.607f, 3.565f, -10.117f)), GetGlobalRotation(new Vector3(351.661f, 173.747f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-55.458f, 3.759f, -11.485f)), GetGlobalRotation(new Vector3(352.647f, 166.347f, 8.159f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-55.113f, 3.942f, -12.893f)), GetGlobalRotation(new Vector3(352.701f, 155.138f, 16.399f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-54.428f, 4.157f, -14.214f)), GetGlobalRotation(new Vector3(351.204f, 143.271f, 23.556f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-53.584f, 4.355f, -15.362f)), GetGlobalRotation(new Vector3(352.579f, 143.875f, 23.471f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-52.732f, 4.487f, -16.564f)), GetGlobalRotation(new Vector3(357.207f, 138.024f, 29.714f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-51.714f, 4.539f, -17.64f)), GetGlobalRotation(new Vector3(359.642f, 129.619f, 29.793f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-50.565f, 4.614f, -18.542f)), GetGlobalRotation(new Vector3(354.756f, 126.812f, 29.93f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-49.28f, 4.742f, -19.288f)), GetGlobalRotation(new Vector3(355.717f, 116.112f, 30.435f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-47.995f, 4.849f, -19.918f)), GetGlobalRotation(new Vector3(355.717f, 116.112f, 30.435f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-46.537f, 4.924f, -20.384f)), GetGlobalRotation(new Vector3(357.544f, 99.245f, 30.481f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-45.027f, 4.967f, -20.434f)), GetGlobalRotation(new Vector3(359.683f, 82.48f, 29.98f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-43.54f, 4.952f, -19.985f)), GetGlobalRotation(new Vector3(0.571f, 62.903f, 28.564f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.247f, 4.905f, -19.106f)), GetGlobalRotation(new Vector3(357.764f, 49.178f, 27.915f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.348f, 4.914f, -17.904f)), GetGlobalRotation(new Vector3(359.911f, 27.158f, 26.853f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.692f, 4.916f, -16.625f)), GetGlobalRotation(new Vector3(359.44f, 20.191f, 26.706f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.299f, 4.906f, -15.208f)), GetGlobalRotation(new Vector3(359.086f, 10.013f, 26.455f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.049f, 4.929f, -13.793f)), GetGlobalRotation(new Vector3(359.086f, 10.013f, 19.982f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.79f, 4.925f, -12.355f)), GetGlobalRotation(new Vector3(359.723f, 10.131f, 10.543f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.535f, 4.923f, -10.93f)), GetGlobalRotation(new Vector3(359.723f, 10.131f, 6.196f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.333f, 4.873f, -9.498f)), GetGlobalRotation(new Vector3(356.396f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.084f, 4.917f, -8.102f)), GetGlobalRotation(new Vector3(359.437f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.83f, 4.87f, -6.683f)), GetGlobalRotation(new Vector3(2.102f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.571f, 4.847f, -5.235f)), GetGlobalRotation(new Vector3(359.149f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.318f, 4.892f, -3.817f)), GetGlobalRotation(new Vector3(357.215f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.066f, 4.949f, -2.405f)), GetGlobalRotation(new Vector3(358.358f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.813f, 4.961f, -0.99f)), GetGlobalRotation(new Vector3(359.353f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.558f, 4.921f, 0.438f)), GetGlobalRotation(new Vector3(1.645f, 10.131f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.25f, 4.863f, 1.868f)), GetGlobalRotation(new Vector3(1.958f, 18.921f, 354.653f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.735f, 4.884f, 3.294f)), GetGlobalRotation(new Vector3(355.164f, 28.866f, 346.098f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.978f, 4.949f, 4.52f)), GetGlobalRotation(new Vector3(0.329f, 35.092f, 343.382f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.158f, 4.941f, 5.688f)), GetGlobalRotation(new Vector3(0.113f, 35.157f, 343.382f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.323f, 4.938f, 6.873f)), GetGlobalRotation(new Vector3(0.113f, 35.157f, 343.382f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.436f, 4.942f, 8.051f)), GetGlobalRotation(new Vector3(0.026f, 46.066f, 338.735f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.392f, 4.954f, 9.047f)), GetGlobalRotation(new Vector3(0.237f, 53.08f, 332.415f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.139f, 4.934f, 9.832f)), GetGlobalRotation(new Vector3(0.865f, 63.138f, 332.841f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-29.866f, 4.912f, 10.477f)), GetGlobalRotation(new Vector3(0.865f, 63.138f, 332.841f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.565f, 4.908f, 11.126f)), GetGlobalRotation(new Vector3(358.835f, 64.179f, 332.839f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-27.258f, 4.937f, 11.758f)), GetGlobalRotation(new Vector3(359.272f, 63.955f, 332.842f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.931f, 4.93f, 12.328f)), GetGlobalRotation(new Vector3(0.681f, 70.286f, 332.929f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-24.502f, 4.924f, 12.706f)), GetGlobalRotation(new Vector3(357.765f, 81.689f, 333.377f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-23.06f, 4.949f, 12.853f)), GetGlobalRotation(new Vector3(0.414f, 86.475f, 333.367f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-21.614f, 4.948f, 12.937f)), GetGlobalRotation(new Vector3(359.523f, 86.922f, 333.367f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-20.167f, 4.96f, 13.015f)), GetGlobalRotation(new Vector3(359.523f, 86.922f, 340.607f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-18.72f, 4.972f, 13.093f)), GetGlobalRotation(new Vector3(359.523f, 86.922f, 347.955f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-17.284f, 4.97f, 13.173f)), GetGlobalRotation(new Vector3(359.523f, 86.922f, 352.949f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-15.848f, 4.97f, 13.252f)), GetGlobalRotation(new Vector3(359.672f, 86.903f, 355.779f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-14.417f, 4.972f, 13.33f)), GetGlobalRotation(new Vector3(359.672f, 86.903f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-13.003f, 4.959f, 13.407f)), GetGlobalRotation(new Vector3(359.672f, 86.903f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-11.568f, 4.958f, 13.416f)), GetGlobalRotation(new Vector3(359.674f, 94.139f, 359.959f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-10.137f, 4.966f, 13.225f)), GetGlobalRotation(new Vector3(359.684f, 102.523f, 359.912f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-8.769f, 4.974f, 12.819f)), GetGlobalRotation(new Vector3(359.7f, 111.029f, 359.866f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-7.453f, 4.92f, 12.229f)), GetGlobalRotation(new Vector3(2.252f, 117.71f, 359.832f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-6.217f, 4.868f, 11.447f)), GetGlobalRotation(new Vector3(0.716f, 128.661f, 0.263f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.122f, 4.901f, 10.511f)), GetGlobalRotation(new Vector3(358.346f, 133.651f, 0.119f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-4.167f, 4.943f, 9.412f)), GetGlobalRotation(new Vector3(358.357f, 145.579f, 359.775f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-3.356f, 4.95f, 8.228f)), GetGlobalRotation(new Vector3(359.694f, 145.574f, 359.775f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-2.62f, 4.957f, 6.982f)), GetGlobalRotation(new Vector3(359.728f, 153.461f, 359.735f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-1.975f, 4.955f, 5.69f)), GetGlobalRotation(new Vector3(359.728f, 153.461f, 359.735f)).eulerAngles)
                                },
                                ForwardForce = 0f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 0f
                            }
                        }
                    },
                    new MotorbikeRoute
                    {
                        Spawn = new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.252f, -7.456f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                        Steps = new HashSet<MotorbikeStep>
                        {
                            new MotorbikeStep
                            {
                                StartSpeed = 0f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.252f, -8.891f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.157f, -10.239f)), GetGlobalRotation(new Vector3(8.064f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.06f, -11.744f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.06f, -13.189f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.06f, -14.621f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.06f, -16.054f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.06f, -17.499f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.256f, 18.06f, -18.932f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles)
                                },
                                ForwardForce = 30000f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 1.2f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 6f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.312f, 13.665f, -29.606f)), GetGlobalRotation(new Vector3(0f, 180.194f, 355.811f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.317f, 13.526f, -31.053f)), GetGlobalRotation(new Vector3(0f, 180.194f, 355.811f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.312f, 13.526f, -32.483f)), GetGlobalRotation(new Vector3(0f, 180.194f, 355.811f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.307f, 13.526f, -33.929f)), GetGlobalRotation(new Vector3(0f, 180.194f, 355.811f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.302f, 13.526f, -35.373f)), GetGlobalRotation(new Vector3(0f, 180.194f, 355.811f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.286f, 13.53f, -36.824f)), GetGlobalRotation(new Vector3(0f, 188.129f, 345.229f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(3.907f, 13.538f, -38.264f)), GetGlobalRotation(new Vector3(358.589f, 201.673f, 336.88f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(3.199f, 13.588f, -39.612f)), GetGlobalRotation(new Vector3(357.764f, 213.822f, 336.869f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(2.253f, 13.643f, -40.766f)), GetGlobalRotation(new Vector3(357.764f, 223.424f, 336.869f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(1.138f, 13.705f, -41.721f)), GetGlobalRotation(new Vector3(356.552f, 234.951f, 336.843f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-0.129f, 13.792f, -42.467f)), GetGlobalRotation(new Vector3(356.552f, 243.71f, 336.843f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-1.411f, 13.873f, -43.103f)), GetGlobalRotation(new Vector3(356.552f, 243.71f, 336.843f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-2.697f, 13.948f, -43.744f)), GetGlobalRotation(new Vector3(356.552f, 243.71f, 336.843f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-3.54f, 14.005f, -44.16f)), GetGlobalRotation(new Vector3(356.552f, 243.71f, 336.843f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-4.321f, 13.865f, -44.58f)), GetGlobalRotation(new Vector3(2.064f, 247.18f, 336.872f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.738f, 13.806f, -45.066f)), GetGlobalRotation(new Vector3(2.064f, 255.21f, 336.872f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-7.211f, 13.714f, -45.294f)), GetGlobalRotation(new Vector3(3.478f, 267.094f, 341.376f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-8.674f, 13.621f, -45.267f)), GetGlobalRotation(new Vector3(2.843f, 276.913f, 341.299f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-10.111f, 13.549f, -45.093f)), GetGlobalRotation(new Vector3(2.843f, 276.913f, 351.164f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-10.961f, 13.504f, -44.99f)), GetGlobalRotation(new Vector3(1.308f, 277.008f, 356.479f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-12.392f, 13.449f, -44.815f)), GetGlobalRotation(new Vector3(1.308f, 277.008f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-12.933f, 13.437f, -44.748f)), GetGlobalRotation(new Vector3(1.308f, 277.008f, 0f)).eulerAngles)
                                },
                                ForwardForce = 30000f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 1.1f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 8f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-22.72f, 9.127f, -43.545f)), GetGlobalRotation(new Vector3(0.595f, 277.009f, 0.024f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-24.152f, 9.112f, -43.369f)), GetGlobalRotation(new Vector3(0.595f, 277.009f, 0.024f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-24.879f, 9.174f, -43.279f)), GetGlobalRotation(new Vector3(356.475f, 277.008f, 0.024f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.63f, 9.16f, -43.187f)), GetGlobalRotation(new Vector3(356.475f, 277.008f, 0.024f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.869f, 9.17f, -43.158f)), GetGlobalRotation(new Vector3(356.475f, 277.008f, 0.024f)).eulerAngles)
                                },
                                ForwardForce = 30000f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 1.2f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 6f,
                                NumberBrakingPoints = 10,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.40774f, 4.864747f, -41.86156f)), GetGlobalRotation(new Vector3(358.4369f, 277.0132f, 0.2128583f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.82877f, 4.903816f, -41.56631f)), GetGlobalRotation(new Vector3(358.4369f, 288.3522f, 350.7527f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.14363f, 4.943055f, -40.93985f)), GetGlobalRotation(new Vector3(358.4369f, 302.4236f, 342.6454f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.35118f, 4.949776f, -39.97571f)), GetGlobalRotation(new Vector3(358.4369f, 314.4562f, 342.6454f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.31929f, 4.971458f, -38.82928f)), GetGlobalRotation(new Vector3(358.4369f, 324.2958f, 335.8627f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.13248f, 4.962536f, -37.5763f)), GetGlobalRotation(new Vector3(-5.122642E-06f, 335.5398f, 335.8627f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.60168f, 4.952454f, -36.19578f)), GetGlobalRotation(new Vector3(-7.897406E-06f, 349.241f, 327.9156f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.73455f, 4.952454f, -34.70017f)), GetGlobalRotation(new Vector3(-9.731686E-06f, 0.250707f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.52281f, 4.937549f, -33.20884f)), GetGlobalRotation(new Vector3(-7.257076E-06f, 13.92651f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.96873f, 4.937549f, -31.80404f)), GetGlobalRotation(new Vector3(-6.830189E-06f, 28.70841f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.07652f, 4.916435f, -30.5731f)), GetGlobalRotation(new Vector3(-6.830189E-06f, 42.3112f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.06699f, 4.916435f, -29.48091f)), GetGlobalRotation(new Vector3(-5.976416E-06f, 51.70395f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.81445f, 4.916435f, -28.65652f)), GetGlobalRotation(new Vector3(-1.963679E-05f, 62.35493f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.44104f, 4.916435f, -28.07788f)), GetGlobalRotation(new Vector3(-1.195283E-05f, 72.97856f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.02687f, 4.916435f, -27.71049f)), GetGlobalRotation(new Vector3(-1.878302E-05f, 77.81342f, 327.9155f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.61668f, 4.916435f, -27.40595f)), GetGlobalRotation(new Vector3(-6.830189E-06f, 77.81344f, 337.7177f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.21903f, 4.916435f, -27.10411f)), GetGlobalRotation(new Vector3(-8.964624E-06f, 77.81348f, 346.8741f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.82104f, 4.916435f, -26.8022f)), GetGlobalRotation(new Vector3(-5.976416E-06f, 77.8135f, 353.7239f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.41923f, 4.916435f, -26.49946f)), GetGlobalRotation(new Vector3(-3.468456E-06f, 77.81358f, 358.3872f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.95625f, 4.994474f, -26.18576f)), GetGlobalRotation(new Vector3(353.8306f, 77.98794f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-27.59088f, 5.050546f, -25.89525f)), GetGlobalRotation(new Vector3(0f, 77.98794f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-26.183f, 5.050546f, -25.56978f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-24.80418f, 5.050546f, -25.18605f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-23.43414f, 5.050546f, -24.80476f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-22.04091f, 5.050546f, -24.41702f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-20.65764f, 5.050546f, -24.03205f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-19.26098f, 5.050546f, -23.64335f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-17.87014f, 5.050546f, -23.25626f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-16.46913f, 5.050546f, -22.86635f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-15.08491f, 5.050546f, -22.48111f)), GetGlobalRotation(new Vector3(0f, 74.44783f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-13.71204f, 4.911248f, -22.09902f)), GetGlobalRotation(new Vector3(0.465363f, 74.44789f, 1.093934E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-12.33222f, 4.910535f, -21.71501f)), GetGlobalRotation(new Vector3(359.0112f, 74.44789f, 1.227482E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-10.93349f, 4.935594f, -21.32573f)), GetGlobalRotation(new Vector3(0f, 74.44789f, 1.227482E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-9.548395f, 4.935594f, -20.94025f)), GetGlobalRotation(new Vector3(0f, 74.44789f, 1.227482E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-8.187967f, 4.935594f, -20.45045f)), GetGlobalRotation(new Vector3(-4.579999E-13f, 66.96708f, 1.227483E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-6.919466f, 4.935594f, -19.76751f)), GetGlobalRotation(new Vector3(5.088888E-14f, 58.49566f, 1.227482E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.690868f, 4.935594f, -19.01449f)), GetGlobalRotation(new Vector3(5.088888E-14f, 58.49566f, 1.227482E-06f)).eulerAngles)
                                },
                                ForwardForce = 0f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 0f
                            }
                        }
                    },
                    new MotorbikeRoute
                    {
                        Spawn = new PointAnimationTransform(GetGlobalPosition(new Vector3(24.172f, 6.134f, 31.878f)), GetGlobalRotation(new Vector3(0f, 270f, 0f)).eulerAngles),
                        Steps = new HashSet<MotorbikeStep>
                        {
                            new MotorbikeStep
                            {
                                StartSpeed = 0f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(22.734f, 6.134f, 31.878f)), GetGlobalRotation(new Vector3(0f, 270f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(21.293f, 6.134f, 31.878f)), GetGlobalRotation(new Vector3(0f, 270f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(19.846f, 6.134f, 31.878f)), GetGlobalRotation(new Vector3(0f, 270f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(18.661f, 6.193f, 31.878f)), GetGlobalRotation(new Vector3(355.17f, 270f, 0f)).eulerAngles)
                                },
                                ForwardForce = 20000f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 1.1f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 5f,
                                NumberBrakingPoints = 11,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(12.04356f, 4.865462f, 31.87019f)), GetGlobalRotation(new Vector3(0.3949172f, 268.955f, 0.5063146f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(10.61604f, 4.856831f, 31.98016f)), GetGlobalRotation(new Vector3(0.286607f, 280.3975f, 349.2623f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(9.878246f, 4.853032f, 32.11761f)), GetGlobalRotation(new Vector3(0.4403547f, 287.5716f, 344.9117f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(8.484432f, 4.873095f, 32.5863f)), GetGlobalRotation(new Vector3(0.1454892f, 295.5905f, 338.0301f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(7.216078f, 4.900137f, 33.29534f)), GetGlobalRotation(new Vector3(0.06290264f, 302.601f, 338.1952f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(6.015561f, 4.898576f, 34.06314f)), GetGlobalRotation(new Vector3(0.06291246f, 302.6011f, 348.3792f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.801276f, 4.89699f, 34.83974f)), GetGlobalRotation(new Vector3(0.06291331f, 302.6011f, 357.5516f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(3.584584f, 4.895402f, 35.618f)), GetGlobalRotation(new Vector3(0.06291303f, 302.6011f, 3.335056E-09f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(2.376608f, 4.903402f, 36.39056f)), GetGlobalRotation(new Vector3(0.06291304f, 302.6011f, 5.002583E-09f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(1.137622f, 4.95979f, 37.18293f)), GetGlobalRotation(new Vector3(355.9638f, 302.6011f, -1.176858E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-0.08242777f, 5.050003f, 37.96322f)), GetGlobalRotation(new Vector3(357.2781f, 302.6011f, -6.410535E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-1.292495f, 5.065517f, 38.72593f)), GetGlobalRotation(new Vector3(0.6902505f, 296.697f, 6.644553f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-2.597884f, 5.044632f, 39.31537f)), GetGlobalRotation(new Vector3(1.678715f, 288.1099f, 11.7972f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-4.031507f, 5.040511f, 39.74226f)), GetGlobalRotation(new Vector3(0.009743265f, 281.1184f, 16.46173f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.472352f, 5.048433f, 39.90607f)), GetGlobalRotation(new Vector3(359.7655f, 273.2986f, 16.31826f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-6.932315f, 5.035826f, 39.83677f)), GetGlobalRotation(new Vector3(0.3540963f, 263.1768f, 16.11147f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-8.389216f, 5.040504f, 39.58975f)), GetGlobalRotation(new Vector3(359.0823f, 258.4809f, 16.04291f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-9.786715f, 5.044619f, 39.2377f)), GetGlobalRotation(new Vector3(0.2084971f, 254.5692f, 16.06714f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-11.17195f, 5.039389f, 38.85532f)), GetGlobalRotation(new Vector3(0.2084958f, 254.5692f, 9.076907f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-12.55464f, 5.034169f, 38.47368f)), GetGlobalRotation(new Vector3(0.208494f, 254.5692f, 3.12303f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-13.93396f, 5.028962f, 38.093f)), GetGlobalRotation(new Vector3(0.2084945f, 254.5693f, -1.33403E-08f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-15.31553f, 5.023746f, 37.71167f)), GetGlobalRotation(new Vector3(0.2084946f, 254.5693f, -3.335075E-08f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-16.32471f, 4.986531f, 37.43309f)), GetGlobalRotation(new Vector3(355.2361f, 254.5694f, -1.713467E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-17.72204f, 4.965286f, 37.04736f)), GetGlobalRotation(new Vector3(355.2361f, 254.5694f, -1.713467E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-18.41354f, 4.914402f, 36.85653f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-19.80226f, 4.914402f, 36.47321f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-21.18476f, 4.868361f, 36.09162f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-22.58134f, 4.868361f, 35.70615f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-23.9826f, 4.868361f, 35.31937f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.37702f, 4.868361f, 34.9345f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-26.7695f, 4.868361f, 34.55013f)), GetGlobalRotation(new Vector3(5.088888E-14f, 254.5694f, -1.499283E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.15763f, 4.868361f, 34.16698f)), GetGlobalRotation(new Vector3(-2.774764E-06f, 254.5694f, 4.352646f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-29.56016f, 4.868361f, 33.77986f)), GetGlobalRotation(new Vector3(-2.988208E-06f, 254.5694f, 9.808861f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.9512f, 4.868361f, 33.3578f)), GetGlobalRotation(new Vector3(-1.109906E-05f, 249.4921f, 14.90752f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.3177f, 4.912947f, 32.834f)), GetGlobalRotation(new Vector3(356.7325f, 243.8921f, 22.81341f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.61839f, 4.967962f, 32.20939f)), GetGlobalRotation(new Vector3(358.4795f, 244.627f, 22.78272f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.93047f, 4.989939f, 31.52032f)), GetGlobalRotation(new Vector3(358.4795f, 239.8279f, 22.78273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.14624f, 4.992508f, 30.66612f)), GetGlobalRotation(new Vector3(358.4796f, 231.3132f, 22.78273f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.28057f, 4.991479f, 29.77889f)), GetGlobalRotation(new Vector3(359.73f, 231.8383f, 22.77452f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.36874f, 4.998253f, 28.78254f)), GetGlobalRotation(new Vector3(359.73f, 223.3043f, 22.77451f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.31267f, 4.995759f, 27.66595f)), GetGlobalRotation(new Vector3(359.73f, 218.5296f, 22.77452f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.21711f, 4.997959f, 26.53323f)), GetGlobalRotation(new Vector3(359.73f, 218.5296f, 22.77452f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.04332f, 5.004761f, 25.31424f)), GetGlobalRotation(new Vector3(359.73f, 209.6208f, 22.77453f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.71762f, 4.988449f, 24.01133f)), GetGlobalRotation(new Vector3(359.73f, 204.2565f, 22.77453f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.31314f, 4.995279f, 22.68976f)), GetGlobalRotation(new Vector3(359.73f, 204.2565f, 22.77453f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.90903f, 5.002115f, 21.36732f)), GetGlobalRotation(new Vector3(359.73f, 204.2565f, 22.77453f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-43.50946f, 5.009002f, 20.03483f)), GetGlobalRotation(new Vector3(359.73f, 204.2565f, 22.77453f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.13882f, 4.907776f, 18.7485f)), GetGlobalRotation(new Vector3(4.16004f, 206.1197f, 22.83782f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.70749f, 4.868243f, 17.34581f)), GetGlobalRotation(new Vector3(3.201651E-06f, 197.6981f, 22.83783f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-45.05161f, 4.868243f, 15.91262f)), GetGlobalRotation(new Vector3(3.308373E-06f, 188.3731f, 22.83781f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-45.16465f, 4.868243f, 14.44491f)), GetGlobalRotation(new Vector3(2.961527E-06f, 181.5302f, 22.83781f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-45.09992f, 4.868243f, 12.99593f)), GetGlobalRotation(new Vector3(3.521816E-06f, 173.5761f, 22.83784f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.93847f, 4.868243f, 11.56214f)), GetGlobalRotation(new Vector3(4.268868E-06f, 173.576f, 16.98936f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.77723f, 4.868243f, 10.12997f)), GetGlobalRotation(new Vector3(4.055425E-06f, 173.576f, 11.93826f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.61724f, 4.868243f, 8.708987f)), GetGlobalRotation(new Vector3(4.37559E-06f, 173.576f, 6.801109f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.45704f, 4.868243f, 7.286276f)), GetGlobalRotation(new Vector3(4.055425E-06f, 173.576f, 2.196173f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.29543f, 4.868243f, 5.850873f)), GetGlobalRotation(new Vector3(4.108788E-06f, 173.576f, 2.544443E-14f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-44.07214f, 4.868243f, 4.433122f)), GetGlobalRotation(new Vector3(4.089093E-06f, 167.9639f, -4.018115E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-43.77153f, 4.868243f, 3.023224f)), GetGlobalRotation(new Vector3(4.089093E-06f, 167.9639f, -4.018115E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-43.46442f, 4.868243f, 1.582856f)), GetGlobalRotation(new Vector3(4.089093E-06f, 167.9639f, -4.018115E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-43.16518f, 4.868243f, 0.1793783f)), GetGlobalRotation(new Vector3(4.089093E-06f, 167.9639f, -4.018115E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.86258f, 4.933434f, -1.239943f)), GetGlobalRotation(new Vector3(357.0863f, 167.9639f, -3.472945E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.57005f, 4.956704f, -2.61192f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-42.27042f, 4.956704f, -4.017231f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.96777f, 4.956704f, -5.436692f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.67002f, 4.956704f, -6.833221f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.37147f, 4.881761f, -8.233471f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.07135f, 4.881761f, -9.64103f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.77236f, 4.881761f, -11.04334f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.47211f, 4.881761f, -12.45152f)), GetGlobalRotation(new Vector3(0f, 167.9639f, -3.472946E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-40.17072f, 4.881761f, -13.8651f)), GetGlobalRotation(new Vector3(4.268868E-07f, 167.9639f, 7.249409f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.76835f, 4.894957f, -15.2726f)), GetGlobalRotation(new Vector3(1.920991E-06f, 158.4457f, 13.99816f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.16021f, 4.894957f, -16.63093f)), GetGlobalRotation(new Vector3(7.257076E-06f, 150.4729f, 22.69188f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.45561f, 4.894957f, -17.87491f)), GetGlobalRotation(new Vector3(7.257076E-06f, 150.4729f, 22.69188f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.74961f, 4.894957f, -19.12142f)), GetGlobalRotation(new Vector3(7.257076E-06f, 150.4729f, 22.69188f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.04641f, 4.894957f, -20.36292f)), GetGlobalRotation(new Vector3(7.257076E-06f, 150.4729f, 22.69188f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.14923f, 4.894957f, -21.57321f)), GetGlobalRotation(new Vector3(5.122642E-06f, 137.8444f, 22.69189f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.18382f, 4.894957f, -22.63956f)), GetGlobalRotation(new Vector3(5.122642E-06f, 137.8444f, 22.69189f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.21969f, 4.894957f, -23.7045f)), GetGlobalRotation(new Vector3(5.122642E-06f, 137.8444f, 22.69189f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.09646f, 4.894957f, -24.66747f)), GetGlobalRotation(new Vector3(2.561321E-06f, 125.2839f, 22.69188f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.87279f, 4.929365f, -25.52353f)), GetGlobalRotation(new Vector3(357.3777f, 124.673f, 13.11853f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.71019f, 4.944693f, -26.34234f)), GetGlobalRotation(new Vector3(-8.537737E-07f, 124.6731f, 6.360819f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-29.531f, 4.944693f, -27.15803f)), GetGlobalRotation(new Vector3(-3.201651E-06f, 124.6731f, 3.726794f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.34881f, 4.944693f, -27.9758f)), GetGlobalRotation(new Vector3(-3.308373E-06f, 124.6731f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-27.16468f, 4.944693f, -28.7949f)), GetGlobalRotation(new Vector3(-3.308373E-06f, 124.6731f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.97825f, 4.944693f, -29.6156f)), GetGlobalRotation(new Vector3(-3.308373E-06f, 124.6731f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-24.8004f, 4.944693f, -30.43037f)), GetGlobalRotation(new Vector3(-1.28066E-05f, 118.0654f, 8.157363f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-23.47883f, 4.944693f, -31.03734f)), GetGlobalRotation(new Vector3(-1.835613E-05f, 112.3584f, 8.157364f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-22.10023f, 4.944693f, -31.50427f)), GetGlobalRotation(new Vector3(-1.920991E-05f, 105.1792f, 8.157362f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-20.71529f, 4.944693f, -31.88001f)), GetGlobalRotation(new Vector3(-1.920991E-05f, 105.1792f, 8.157362f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-19.2947f, 4.944693f, -32.15502f)), GetGlobalRotation(new Vector3(-1.408727E-05f, 96.87421f, 14.17282f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-17.83771f, 4.944693f, -32.22063f)), GetGlobalRotation(new Vector3(-1.408727E-05f, 89.00822f, 14.17282f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-16.99263f, 4.992206f, -32.19398f)), GetGlobalRotation(new Vector3(352.5877f, 87.12553f, 14.29477f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-15.63147f, 5.055443f, -32.15544f)), GetGlobalRotation(new Vector3(0f, 88.28793f, 14.19072f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-14.19242f, 5.055443f, -31.933f)), GetGlobalRotation(new Vector3(-9.391511E-06f, 74.8437f, 18.33727f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-12.84631f, 5.055443f, -31.33496f)), GetGlobalRotation(new Vector3(-9.391511E-06f, 59.3727f, 18.33727f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-11.58195f, 5.055443f, -30.58641f)), GetGlobalRotation(new Vector3(-1.707547E-06f, 59.37266f, 9.808967f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-10.38783f, 4.999704f, -29.89063f)), GetGlobalRotation(new Vector3(4.954561f, 60.23142f, 9.846126f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-9.155129f, 4.93416f, -29.06057f)), GetGlobalRotation(new Vector3(2.134434E-06f, 51.96849f, 9.846125f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-8.084157f, 4.93416f, -28.0552f)), GetGlobalRotation(new Vector3(1.067217E-06f, 42.88869f, 9.846133f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-7.170632f, 4.93416f, -26.91448f)), GetGlobalRotation(new Vector3(-1.920991E-06f, 34.53019f, 9.846137f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-6.435742f, 4.93416f, -25.64426f)), GetGlobalRotation(new Vector3(-3.628538E-06f, 25.11121f, 9.846141f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.82252f, 4.93416f, -24.33583f)), GetGlobalRotation(new Vector3(-5.65625E-06f, 25.11123f, 5.065487f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.21639f, 4.93416f, -23.04254f)), GetGlobalRotation(new Vector3(-5.843013E-06f, 25.11123f, 1.168321f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-4.916965f, 4.93416f, -22.40365f)), GetGlobalRotation(new Vector3(0f, 25.11123f, 1.076604E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-4.284563f, 4.93416f, -21.05431f)), GetGlobalRotation(new Vector3(0f, 25.11123f, 6.175989E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-3.982438f, 4.93416f, -20.40968f)), GetGlobalRotation(new Vector3(0f, 25.11124f, 4.605547E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-3.41063f, 4.915262f, -19.18961f)), GetGlobalRotation(new Vector3(0f, 25.11123f, 4.819758E-06f)).eulerAngles)
                                },
                                ForwardForce = 0f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 0f
                            }
                        }
                    },
                    new MotorbikeRoute
                    {
                        Spawn = new PointAnimationTransform(GetGlobalPosition(new Vector3(4.194f, 14.301f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                        Steps = new HashSet<MotorbikeStep>
                        {
                            new MotorbikeStep
                            {
                                StartSpeed = 0f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(5.617f, 14.301f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(7.05f, 14.301f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(8.488f, 14.301f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(9.931f, 14.301f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(11.373f, 14.301f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(12.145f, 14.335f, -13.19f)), GetGlobalRotation(new Vector3(357.874f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(12.864f, 14.385f, -13.19f)), GetGlobalRotation(new Vector3(354.564f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(14.297f, 14.522f, -13.19f)), GetGlobalRotation(new Vector3(354.564f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(14.477f, 14.539f, -13.19f)), GetGlobalRotation(new Vector3(354.564f, 90f, 0f)).eulerAngles)
                                },
                                ForwardForce = 29000f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 1.3f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 6f,
                                NumberBrakingPoints = 0,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(25.293f, 10.577f, -13.19f)), GetGlobalRotation(new Vector3(0f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(26.765f, 10.663f, -13.19f)), GetGlobalRotation(new Vector3(352.77f, 90f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(27.173f, 10.71f, -13.19f)), GetGlobalRotation(new Vector3(352.77f, 90f, 0f)).eulerAngles)
                                },
                                ForwardForce = 30000f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 1.4f
                            },
                            new MotorbikeStep
                            {
                                StartSpeed = 5f,
                                NumberBrakingPoints = 10,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(38.41496f, 5.064861f, -13.10541f)), GetGlobalRotation(new Vector3(359.007f, 80.54955f, 10.22027f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(39.82506f, 5.089636f, -12.65707f)), GetGlobalRotation(new Vector3(359.007f, 62.95163f, 16.90172f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(40.47989f, 5.085422f, -12.16413f)), GetGlobalRotation(new Vector3(359.007f, 51.60022f, 16.90171f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(41.48423f, 5.110016f, -11.07862f)), GetGlobalRotation(new Vector3(359.007f, 39.44549f, 24.53438f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(42.26447f, 5.090196f, -9.774053f)), GetGlobalRotation(new Vector3(0.7393031f, 24.94415f, 24.53266f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(42.78446f, 5.071504f, -8.410307f)), GetGlobalRotation(new Vector3(0.739302f, 17.43195f, 24.53267f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.20485f, 5.081133f, -7.028318f)), GetGlobalRotation(new Vector3(0f, 17.43195f, 24.53267f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.49312f, 5.081133f, -5.591317f)), GetGlobalRotation(new Vector3(-1.067217E-07f, 7.13733f, 24.53269f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.53719f, 5.081133f, -4.112612f)), GetGlobalRotation(new Vector3(3.201651E-07f, 358.3414f, 24.53272f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.49578f, 5.081133f, -2.681407f)), GetGlobalRotation(new Vector3(5.202683E-07f, 358.3414f, 15.41661f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.45414f, 5.081133f, -1.243469f)), GetGlobalRotation(new Vector3(6.403303E-07f, 358.3414f, 7.8702f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.41288f, 5.081133f, 0.1819843f)), GetGlobalRotation(new Vector3(7.15369E-07f, 358.3414f, 1.227969f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.37107f, 5.081133f, 1.627107f)), GetGlobalRotation(new Vector3(7.15369E-07f, 358.3414f, 1.227969f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.32943f, 5.079648f, 3.066816f)), GetGlobalRotation(new Vector3(7.15369E-07f, 358.3414f, 1.227969f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.28768f, 5.079648f, 4.509703f)), GetGlobalRotation(new Vector3(6.136498E-07f, 358.3414f, 9.579438f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(43.08815f, 5.079648f, 5.937269f)), GetGlobalRotation(new Vector3(4.268868E-07f, 346.8679f, 17.09658f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(42.67883f, 5.079648f, 7.350802f)), GetGlobalRotation(new Vector3(2.134434E-06f, 340.6742f, 17.09658f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(42.22123f, 5.022083f, 8.709148f)), GetGlobalRotation(new Vector3(3.965571f, 341.8959f, 17.1389f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(41.63246f, 4.955167f, 10.11287f)), GetGlobalRotation(new Vector3(5.122642E-06f, 331.1857f, 17.13892f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(40.78131f, 4.955167f, 11.30655f)), GetGlobalRotation(new Vector3(6.830189E-06f, 317.6422f, 17.13894f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(39.70953f, 4.955167f, 12.28815f)), GetGlobalRotation(new Vector3(1.067217E-05f, 306.44f, 17.13894f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(38.45958f, 4.955167f, 13.01989f)), GetGlobalRotation(new Vector3(1.62217E-05f, 294.1474f, 22.54467f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(37.02445f, 4.955167f, 13.40955f)), GetGlobalRotation(new Vector3(9.391511E-06f, 277.6345f, 22.54466f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(35.5684f, 4.955167f, 13.47461f)), GetGlobalRotation(new Vector3(7.683963E-06f, 267.5345f, 22.54466f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(34.12678f, 4.955167f, 13.31219f)), GetGlobalRotation(new Vector3(3.415095E-06f, 261.6471f, 22.54465f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(32.70899f, 4.955167f, 12.97074f)), GetGlobalRotation(new Vector3(-1.536793E-05f, 251.822f, 22.54465f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(31.35666f, 4.955167f, 12.42692f)), GetGlobalRotation(new Vector3(-1.707547E-05f, 245.5067f, 22.54465f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(30.0378f, 4.955167f, 11.82607f)), GetGlobalRotation(new Vector3(-1.707547E-05f, 245.5067f, 22.54465f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(28.7361f, 4.955167f, 11.23304f)), GetGlobalRotation(new Vector3(-1.28066E-05f, 245.5067f, 14.8268f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(27.43684f, 4.955167f, 10.64112f)), GetGlobalRotation(new Vector3(-1.62217E-05f, 245.5067f, 8.137883f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(26.12365f, 4.955167f, 10.04284f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 2.102271f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(24.81835f, 4.955167f, 9.448167f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(23.50224f, 4.955167f, 8.848569f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(22.19047f, 4.955167f, 8.25095f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(20.88741f, 4.955167f, 7.657296f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(19.57311f, 4.955167f, 7.058518f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(18.25904f, 4.955167f, 6.459853f)), GetGlobalRotation(new Vector3(-1.590153E-05f, 245.5067f, 0f)).eulerAngles)
                                },
                                ForwardForce = 0f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 0f
                            }
                        }
                    },
                    new MotorbikeRoute
                    {
                        Spawn = new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.14762f, 5.161506f, -11.38122f)), GetGlobalRotation(new Vector3(0f, 13.43213f, 0f)).eulerAngles),
                        Steps = new HashSet<MotorbikeStep>
                        {
                            new MotorbikeStep
                            {
                                StartSpeed = 0f,
                                NumberBrakingPoints = 10,
                                Path = new HashSet<PointAnimationTransform>
                                {
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.81347f, 5.17456f, -9.982014f)), GetGlobalRotation(new Vector3(359.0076f, 13.43214f, -1.200799E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.61935f, 5.255986f, -9.169276f)), GetGlobalRotation(new Vector3(355.1762f, 13.43213f, 1.071011E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.46907f, 5.281466f, -8.540095f)), GetGlobalRotation(new Vector3(353.2453f, 13.43213f, 2.149353E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.13918f, 5.41703f, -7.158491f)), GetGlobalRotation(new Vector3(353.2453f, 13.43213f, 2.149353E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.9367f, 5.407152f, -6.310714f)), GetGlobalRotation(new Vector3(340.263f, 13.43215f, -2.040887E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.74504f, 5.306859f, -5.508252f)), GetGlobalRotation(new Vector3(342.9247f, 13.43214f, -2.23286E-06f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.53227f, 5.133132f, -4.617284f)), GetGlobalRotation(new Vector3(353.8642f, 13.43212f, -6.976878E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.35668f, 5.057131f, -3.882129f)), GetGlobalRotation(new Vector3(-1.272222E-14f, 13.43212f, -6.976877E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.02182f, 5.057314f, -2.480072f)), GetGlobalRotation(new Vector3(0.6103461f, 18.60505f, 353.2891f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-29.55942f, 5.068936f, -1.128469f)), GetGlobalRotation(new Vector3(0.6702778f, 27.0818f, 345.3071f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.89157f, 5.070909f, 0.1557123f)), GetGlobalRotation(new Vector3(0.2353989f, 36.72023f, 338.4128f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.01872f, 5.079406f, 1.312082f)), GetGlobalRotation(new Vector3(0.635195f, 43.26174f, 333.3991f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-26.92157f, 5.076503f, 2.325266f)), GetGlobalRotation(new Vector3(359.6077f, 54.02058f, 333.8813f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.73812f, 5.086513f, 3.184437f)), GetGlobalRotation(new Vector3(359.6077f, 54.02058f, 333.8813f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-24.56499f, 5.096435f, 4.036128f)), GetGlobalRotation(new Vector3(359.1723f, 54.23409f, 333.879f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-23.36176f, 5.088866f, 4.830966f)), GetGlobalRotation(new Vector3(359.9129f, 60.04352f, 333.9241f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-22.10848f, 5.091059f, 5.553274f)), GetGlobalRotation(new Vector3(359.9129f, 60.04352f, 337.9078f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-20.85898f, 5.093252f, 6.273407f)), GetGlobalRotation(new Vector3(359.9129f, 60.04359f, 343.3153f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-19.60987f, 5.084806f, 6.996142f)), GetGlobalRotation(new Vector3(359.9129f, 60.04354f, 347.0627f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-18.36482f, 5.086991f, 7.71371f)), GetGlobalRotation(new Vector3(359.9129f, 60.04353f, 351.7325f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-17.11548f, 5.089182f, 8.433749f)), GetGlobalRotation(new Vector3(359.9129f, 60.04353f, 351.7325f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-15.86882f, 5.091367f, 9.152244f)), GetGlobalRotation(new Vector3(359.9129f, 60.04355f, 356.1133f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-14.62646f, 5.093549f, 9.868255f)), GetGlobalRotation(new Vector3(359.9129f, 60.04355f, 356.1133f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-13.38653f, 4.999961f, 10.59038f)), GetGlobalRotation(new Vector3(4.830964f, 59.70863f, 356.0995f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-12.06082f, 4.930219f, 11.22941f)), GetGlobalRotation(new Vector3(0f, 66.96129f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-10.71109f, 4.930219f, 11.72611f)), GetGlobalRotation(new Vector3(0f, 72.85213f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-9.329195f, 4.930219f, 12.08907f)), GetGlobalRotation(new Vector3(0f, 77.85392f, 0f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-7.911972f, 4.913696f, 12.31544f)), GetGlobalRotation(new Vector3(1.655375f, 84.17203f, 2.669156E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-6.481366f, 4.872136f, 12.46146f)), GetGlobalRotation(new Vector3(1.655375f, 84.17203f, 2.669156E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-5.032727f, 4.869598f, 12.60932f)), GetGlobalRotation(new Vector3(359.5845f, 84.172f, 3.601952E-07f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-3.621973f, 4.879809f, 12.65304f)), GetGlobalRotation(new Vector3(359.5912f, 94.50682f, 354.3062f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-2.20167f, 4.877882f, 12.41713f)), GetGlobalRotation(new Vector3(0.129271f, 105.318f, 354.3306f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-0.7825396f, 4.908173f, 12.01972f)), GetGlobalRotation(new Vector3(357.1057f, 110.5077f, 349.8943f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(0.5171483f, 4.925994f, 11.45412f)), GetGlobalRotation(new Vector3(0.5122232f, 117.9421f, 349.8812f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(1.779204f, 4.924152f, 10.69433f)), GetGlobalRotation(new Vector3(359.9814f, 123.8297f, 349.9839f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(2.951666f, 4.921121f, 9.862755f)), GetGlobalRotation(new Vector3(359.9814f, 123.8297f, 354.7533f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(4.15066f, 4.921588f, 9.059206f)), GetGlobalRotation(new Vector3(359.9814f, 123.8297f, 358.2244f)).eulerAngles),
                                    new PointAnimationTransform(GetGlobalPosition(new Vector3(5.347787f, 4.922059f, 8.257f)), GetGlobalRotation(new Vector3(359.9814f, 123.8297f, 0f)).eulerAngles)
                                },
                                ForwardForce = 0f,
                                UpTorque = 0f,
                                RightTorque = 0f,
                                Time = 0f
                            }
                        }
                    }
                };
            }

            internal void FinishPathBike(Bike bike)
            {
                Motorbike motorbike = Motorbikes.FirstOrDefault(x => x.Bike == bike);
                motorbike.FinishPath();
            }

            private void DestroyMotorbikes()
            {
                foreach (Motorbike motorbike in Motorbikes) motorbike.Destroy();
                MotorbikeRoutes = null;
                foreach (ScientistNPC npc in Drivers) if (npc.IsExists()) npc.Kill();
            }

            internal class NoDamageDriver { public ulong Id; public int Time; }
            internal HashSet<NoDamageDriver> NoDamageDrivers { get; set; } = new HashSet<NoDamageDriver>();

            internal void AddNoDamageDriver(ScientistNPC npc) { NoDamageDrivers.Add(new NoDamageDriver { Id = npc.net.ID.Value, Time = 2 }); }

            private void UpdateNoDamageDrivers()
            {
                if (NoDamageDrivers.Count == 0) return;
                foreach (NoDamageDriver driver in NoDamageDrivers.ToHashSet())
                {
                    driver.Time--;
                    if (driver.Time <= 0) NoDamageDrivers.Remove(driver);
                }
            }

            private void SpawnMotorbikes(bool isNextStep)
            {
                foreach (MotorbikeRoute path in MotorbikeRoutes)
                    Motorbikes.Add(new Motorbike(path.Spawn, path.Steps, isNextStep));
            }
            #endregion Motorbike

            #region Attack
            internal Coroutine AttackCoroutine { get; set; } = null;

            internal void StartAttack(BasePlayer attacker)
            {
                _ins.AlertToAllPlayers("StartAttack", _config.Chat.Prefix, attacker.displayName);
                AttackCoroutine = ServerMgr.Instance.StartCoroutine(AttackProcess());
            }

            private IEnumerator AttackProcess()
            {
                Vendor.FinishPath();
                Ch47 = new CustomCh47();
                CargoShipContainer = new CustomCargoShipContainer();

                while (!Ch47.IsFinishPath || !CargoShipContainer.IsFinishPath) yield return CoroutineEx.waitForSeconds(1f);

                if (!_config.DisableMotorbikeNpcs) SpawnMotorbikes(false);
                yield return CoroutineEx.waitForSeconds(1f);

                ContainerShore.OpenDoor();
                CargoShipContainer.OpenDoor();
                yield return CoroutineEx.waitForSeconds(2f);

                foreach (Motorbike motorbike in Motorbikes) motorbike.NextStep();

                while (Hostages.Count + HostagesEvacuations.Where(x => !x.IsFinishPath1).Count > 0)
                {
                    if (!_config.DisableMotorbikeNpcs && Motorbikes.Count + Drivers.Count <= 2) SpawnMotorbikes(true);
                    yield return CoroutineEx.waitForSeconds(5f);
                }

                Ch47.Departure();
            }
            #endregion Attack

            private void TryTeleportPlayer(BasePlayer player)
            {
                if (player._limitedNetworking) return;
                if (IsInsideRoom(player))
                {
                    Vector3 pos = GetGlobalPosition(new Vector3(-39f, 6.183f, -81.024f));
                    player.Teleport(pos);
                }
            }

            private bool IsInsideRoom(BasePlayer player)
            {
                Vector3 localPos = transform.InverseTransformPoint(player.transform.position);
                if (localPos.x is < -24f or > 24f) return false;
                if (localPos.y is < 1.5f or > 10.5f) return false;
                if (localPos.z is < -18f or > 0f) return false;
                return true;
            }

            internal void EnablePveMode(PveModeConfig config, BasePlayer player)
            {
                if (!_ins.ActivePveMode) return;

                Dictionary<string, object> dic = new Dictionary<string, object>
                {
                    ["Damage"] = config.Damage,
                    ["ScaleDamage"] = config.ScaleDamage,
                    ["LootCrate"] = config.LootCrate,
                    ["HackCrate"] = false,
                    ["LootNpc"] = config.LootNpc,
                    ["DamageNpc"] = config.DamageNpc,
                    ["DamageTank"] = false,
                    ["DamageHelicopter"] = false,
                    ["DamageTurret"] = false,
                    ["TargetNpc"] = config.TargetNpc,
                    ["TargetTank"] = false,
                    ["TargetHelicopter"] = false,
                    ["TargetTurret"] = false,
                    ["CanEnter"] = config.CanEnter,
                    ["CanEnterCooldownPlayer"] = config.CanEnterCooldownPlayer,
                    ["TimeExitOwner"] = config.TimeExitOwner,
                    ["AlertTime"] = config.AlertTime,
                    ["RestoreUponDeath"] = config.RestoreUponDeath,
                    ["CooldownOwner"] = config.CooldownOwner,
                    ["Darkening"] = config.Darkening
                };

                _ins.PveMode.Call("EventAddPveMode", _ins.Name, dic, transform.position, _config.Radius, new HashSet<ulong>(), Scientists.Select(x => x.net.ID.Value), new HashSet<ulong>(), new HashSet<ulong>(), new HashSet<ulong>(), new HashSet<ulong>(), player);
            }
        }
        #endregion Controller

        #region Brain Animation
        public class BrainPositions
        {
            public Vector3 SpawnPosition { get; set; }
            public Vector3 InitialPathPoint { get; set; }
            public List<Vector3> HomePositions { get; set; }
        }

        public class BrainPath
        {
            public BrainPositions Wolf { get; set; }
            public BrainPositions Hostage { get; set; }
            public BrainPositions Guard { get; set; }
            public HashSet<Vector3> PathPoints { get; set; }
        }

        internal class BrainAnimation
        {
            private PluginConfig _config => _ins._config;
            private ControllerFerryTerminalEvent _event => _ins.Controller;

            internal BaseCombatEntity Entity { get; set; } = null;

            private AnimationTransformBrain Animation { get; set; } = null;
            private HashSet<Vector3> Path { get; set; }
            private bool IsFinishPoint { get; set; } = false;

            private float Delay { get; }
            private Coroutine Coroutine { get; set; } = null;

            private Vector3 HomePosition { get; set; } = Vector3.zero;
            private Plugin ControllingPlugin { get; }
            private string NameSpawnApi { get; }

            private const float Speed = 1.5f;

            internal bool IsHostage => ControllingPlugin == null;

            internal BrainAnimation(Plugin plugin, string api, float delay, HashSet<Vector3> path, BrainPositions data, JObject config)
            {
                ControllingPlugin = plugin;
                NameSpawnApi = api;
                Delay = delay;
                Path = path;
                Spawn(data, config);
            }

            private void Spawn(BrainPositions data, JObject config)
            {
                if (IsHostage) SpawnHostage(data);
                else
                {
                    Entity = (BaseCombatEntity)ControllingPlugin.Call(NameSpawnApi, data.SpawnPosition, config);
                    if (Entity is BaseAnimalNPC wolf) SwitchNavAgent(wolf);
                }
                Animation = Entity.gameObject.AddComponent<AnimationTransformBrain>();
                if (IsHostage) Animation.IsUpdate = true;
                Animation.AddPoint(data.InitialPathPoint, Speed);
            }

            private void SpawnHostage(BrainPositions data)
            {
                HomePosition = data.HomePositions.GetRandom();
                data.HomePositions.Remove(HomePosition);

                Entity = GameManager.server.CreateEntity("assets/prefabs/player/player.prefab", data.SpawnPosition) as BaseCombatEntity;
                Entity.enableSaving = false;

                BasePlayer basePlayer = Entity as BasePlayer;

                basePlayer.userID = (ulong)UnityEngine.Random.Range(0, 10000000);
                basePlayer.UserIDString = basePlayer.userID.ToString();
                BasePlayer.bots.Add(basePlayer);

                Entity.Spawn();

                basePlayer.displayName = _config.Hostage.Name;

                Entity.startHealth = Entity._health = Entity._maxHealth = _config.Hostage.Health;

                foreach (Item item in _config.Hostage.WearItems.Select(x => ItemManager.CreateByName(x.ShortName, 1, x.SkinId)))
                {
                    if (item == null) continue;
                    if (!basePlayer.inventory.containerWear.Insert(item)) item.Remove();
                }

                Item handcuffs = ItemManager.CreateByName("handcuffs");
                if (handcuffs.MoveToContainer(basePlayer.inventory.containerBelt))
                {
                    basePlayer.UpdateActiveItem(handcuffs.uid);
                    Handcuffs held = basePlayer.GetHeldEntity() as Handcuffs;
                    held.SetLocked(true);
                    Effect.server.Run("assets/prefabs/tools/handcuffs/effects/handcuffs-lock.prefab", Entity, 0, Vector3.zero, Vector3.zero);
                    Entity.SetFlag(BaseEntity.Flags.Busy, true);
                }
            }

            internal void Destroy()
            {
                DestroyCoroutineAndAnimation();
                Path = null;
                if (Entity.IsExists()) Entity.Kill();
            }

            private void DestroyCoroutineAndAnimation()
            {
                if (Coroutine != null) ServerMgr.Instance.StopCoroutine(Coroutine);
                if (Animation != null) UnityEngine.Object.DestroyImmediate(Animation);
            }

            internal void FinishPath()
            {
                if (!IsFinishPoint)
                {
                    IsFinishPoint = true;
                    Coroutine = ServerMgr.Instance.StartCoroutine(DelayedAddPath());
                }
                else
                {
                    DestroyCoroutineAndAnimation();
                    Path = null;
                    if (!IsHostage)
                    {
                        if (Entity is BaseAnimalNPC wolf) SwitchNavAgent(wolf);
                        AddStates();
                    }
                    _event.BrainAnimations.Remove(this);
                    switch (Entity)
                    {
                        case BaseAnimalNPC wolf:
                            _event.Wolves.Add(wolf);
                            if (_ins.ActivePveMode) _ins.PveMode.Call("EventAddScientists", _ins.Name, new HashSet<ulong> { wolf.net.ID.Value });
                            break;
                        case ScientistNPC guard:
                            _event.Guards.Add(guard);
                            if (_ins.ActivePveMode) _ins.PveMode.Call("EventAddScientists", _ins.Name, new HashSet<ulong> { guard.net.ID.Value });
                            break;
                        case BasePlayer hostage:
                            _event.Hostages.Add(hostage);
                            _event.SpawnSecurityPoint(hostage);
                            break;
                    }
                }
            }

            private IEnumerator DelayedAddPath()
            {
                yield return CoroutineEx.waitForSeconds(Delay);
                Animation.AddPath(Path, Speed);
                if (IsHostage) Animation.AddPoint(HomePosition);
            }

            private void AddStates() => ControllingPlugin.Call("AddStates", Entity, new HashSet<string> { "RoamState", "ChaseState", "CombatState" });

            private static void SwitchNavAgent(BaseAnimalNPC wolf)
            {
                Rust.Ai.Gen2.RustNavMeshAgent agent = wolf.NavAgent;
                if (agent == null) return;
                agent.enabled = !agent.enabled;
            }
        }
        #endregion Brain Animation

        #region Hostage Evacuation
        public class HostageEvacuationData
        {
            public Dictionary<int, List<Vector3>> InitialPoints { get; set; }
            public HashSet<Vector3> Path1 { get; set; }
            public Vector3 AnimationStartPoint { get; set; }
            public Vector3 AnimationEndPoint { get; set; }
            public HashSet<Vector3> Path2 { get; set; }
            public HashSet<Vector3> HackCratePoints { get; set; }
            public List<Vector3> ExitPoints { get; set; }
            public Vector3 WaveLookPoint { get; set; }
        }

        internal class HostageEvacuation
        {
            private PluginConfig _config => _ins._config;
            private ControllerFerryTerminalEvent _event => _ins.Controller;

            internal BasePlayer Hostage { get; set; } = null;
            private AnimationTransformBrain Animation { get; set; } = null;
            internal HackableLockedCrate HackCrate { get; set; } = null;

            private Coroutine Coroutine { get; set; } = null;

            internal bool IsFinishPath1 { get; set; } = false;
            private bool IsFinishPath2 { get; set; } = false;

            internal float Health { get; set; } = _ins._config.Hostage.Health;

            private float Speed { get; } = 3f;

            internal HostageEvacuation(BasePlayer hostage)
            {
                _event.Hostages.Remove(hostage);
                Hostage = hostage;

                Hostage.skinID = 8151920175;
                // foreach (ScientistNPC npc in _event.Drivers) _ins.NpcSpawn.Call("AddTargetGuard", npc, Hostage);

                UnlockHostage();

                Animation = Hostage.gameObject.AddComponent<AnimationTransformBrain>();
                Animation.IsUpdate = true;

                Coroutine = ServerMgr.Instance.StartCoroutine(ThumbsUp());
            }

            internal void Destroy()
            {
                if (Coroutine != null) ServerMgr.Instance.StopCoroutine(Coroutine);
                if (Animation != null) UnityEngine.Object.DestroyImmediate(Animation);
                if (Hostage.IsExists()) Hostage.Kill();
            }

            internal void FinishPath()
            {
                if (!IsFinishPath1)
                {
                    IsFinishPath1 = true;
                    Hostage.skinID = 0;
                    Coroutine = ServerMgr.Instance.StartCoroutine(HardFall());
                    return;
                }

                if (!IsFinishPath2)
                {
                    IsFinishPath2 = true;
                    Coroutine = ServerMgr.Instance.StartCoroutine(Point());
                    return;
                }

                Coroutine = ServerMgr.Instance.StartCoroutine(Wave());
            }

            private IEnumerator ThumbsUp()
            {
                List<Vector3> path = Pool.Get<List<Vector3>>();
                path.AddRange(_event.HostageEvacuationData.InitialPoints[GetIndexInitialPoints()]);
                path.AddRange(_event.HostageEvacuationData.Path1);

                LookTo(path[0]);

                StartCinematic("thumbsup");
                yield return CoroutineEx.waitForSeconds(2f);
                StopCinematic();

                Animation.AddPath(path, Speed);

                Pool.FreeUnmanaged(ref path);
            }

            private IEnumerator HardFall()
            {
                Hostage.limitNetworking = true;
                Hostage.transform.position = _event.HostageEvacuationData.AnimationStartPoint;
                Hostage.limitNetworking = false;
                yield return CoroutineEx.waitForSeconds(0.25f);

                StartCinematic("hardfall_01");
                yield return CoroutineEx.waitForSeconds(2.5f);

                Hostage.limitNetworking = true;
                StopCinematic();
                Hostage.transform.position = _event.HostageEvacuationData.AnimationEndPoint;
                Hostage.limitNetworking = false;

                HackCrate = _event.Barge.HackCrates.FirstOrDefault(x => x != null && x.HasFlag(BaseEntity.Flags.Busy) && !_event.HostagesEvacuations.Any(y => y.HackCrate == x));

                Animation.AddPath(_event.HostageEvacuationData.Path2, Speed);
                Animation.AddPoint(_event.HostageEvacuationData.HackCratePoints.Min(x => Vector3.Distance(x, HackCrate.transform.position)));
            }

            private IEnumerator Point()
            {
                LookTo(HackCrate.transform.position);

                StartCinematic("point");
                yield return CoroutineEx.waitForSeconds(2f);
                StopCinematic();

                _event.Barge.UnlockHackCrate(HackCrate);
                _ins.AlertToAllPlayers("OpenCrate", _config.Chat.Prefix);

                Animation.AddPoint(_event.HostageEvacuationData.ExitPoints.GetRandom(), Speed);
            }

            private IEnumerator Wave()
            {
                LookTo(_event.HostageEvacuationData.WaveLookPoint);

                StartCinematic("wave");
                yield return CoroutineEx.waitForSeconds(3f);
                StopCinematic();

                FinishEvacuation();
            }

            private int GetIndexInitialPoints()
            {
                int result = 0;
                float distanceResult = float.MaxValue;
                foreach (KeyValuePair<int, List<Vector3>> point in _event.HostageEvacuationData.InitialPoints)
                {
                    Vector3 firstPos = point.Value[0];
                    float distance = Vector3.Distance(firstPos, Hostage.transform.position);
                    if (distance < distanceResult)
                    {
                        result = point.Key;
                        distanceResult = distance;
                    }
                }
                return result;
            }

            private void LookTo(Vector3 pos)
            {
                Vector3 direction = pos - Hostage.transform.position;
                Hostage.viewAngles = Quaternion.LookRotation(direction).eulerAngles;
                Hostage.SendNetworkUpdate();
            }

            private void UnlockHostage()
            {
                Handcuffs held = Hostage.GetHeldEntity() as Handcuffs;
                if (held != null) held.SetLocked(false);
                RemoveItemFromContainer("handcuffs", Hostage.inventory.containerBelt);
                RemoveItemFromContainer("prisonerhood", Hostage.inventory.containerWear);
            }

            private static void RemoveItemFromContainer(string shortname, ItemContainer container)
            {
                Item item = container.itemList.FirstOrDefault(x => x.info.shortname == shortname);
                if (item != null)
                {
                    item.RemoveFromContainer();
                    item.Remove();
                }
            }

            private void StartCinematic(string name) => ConsoleNetwork.BroadcastToAllClients($"cinematic_play {name} {Hostage.userID} 1", Array.Empty<object>());

            private void StopCinematic() => ConsoleNetwork.BroadcastToAllClients($"cinematic_stop {Hostage.userID}", Array.Empty<object>());

            internal void TakeDamage(float dmg)
            {
                if (IsFinishPath1) return;
                Health -= dmg;
                if (Health <= 0f)
                {
                    Health = 0f;
                    _ins.AlertToAllPlayers("KillHostage", _config.Chat.Prefix);
                    FinishEvacuation();
                }
            }

            private void FinishEvacuation()
            {
                _event.HostagesEvacuations.Remove(this);
                if (_event.Hostages.Count + _event.HostagesEvacuations.Count == 0) _event.AllHostagesLiberatedOrDied();
                Destroy();
            }
        }
        #endregion Hostage Evacuation

        #region Vendor
        internal class CustomTravellingVendor
        {
            private ControllerFerryTerminalEvent _event => _ins.Controller;

            private static Vector3 GetGlobalPosition(Vector3 local) => _ins.Controller.GetGlobalPosition(local);
            private static Quaternion GetGlobalRotation(Vector3 local) => _ins.Controller.GetGlobalRotation(local);

            internal BaseEntity Main { get; set; } = null;
            internal HashSet<DecorDeployable> Decor { get; set; } = new HashSet<DecorDeployable>();
            private VendorStage Stage { get; set; } = VendorStage.Idle;

            private AnimationTransformVehicle Animation { get; set; } = null;
            private HashSet<PointAnimationTransform> PointsForward { get; set; } = null;
            private HashSet<PointAnimationTransform> PointsBack { get; set; } = null;
            private HashSet<PointAnimationTransform> PointsOut { get; set; } = null;

            private Coroutine Coroutine { get; set; } = null;

            internal CustomTravellingVendor()
            {
                CalculateAnimation();
                Coroutine = ServerMgr.Instance.StartCoroutine(Spawn());
            }

            private IEnumerator Spawn()
            {
                TravellingVendor vendor = GameManager.server.CreateEntity("assets/prefabs/npc/travelling vendor/travellingvendor.prefab", GetGlobalPosition(new Vector3(-37.03f, 5.579f, -83.78f)), GetGlobalRotation(new Vector3(0f, 0f, 0f))) as TravellingVendor;

                Main = vendor.gameObject.AddComponent<BaseEntity>();
                CopySerializableFields(vendor, Main);
                UnityEngine.Object.DestroyImmediate(vendor, true);
                Main.enableSaving = false;
                Main.Spawn();

                Main.SetFlag(BaseEntity.Flags.Busy, true);

                Rigidbody rigidbody = Main.GetComponent<Rigidbody>();
                rigidbody.detectCollisions = false;
                rigidbody.isKinematic = true;

                Decor.Add(SpawnTable(new Vector3(0.39f, 1.253f, -0.17f), new Vector3(0f, 180f, 90f)));
                Decor.Add(SpawnTable(new Vector3(-0.032f, 1.253f, -1.599f), new Vector3(0f, 270f, 90f)));
                Decor.Add(SpawnTable(new Vector3(-0.39f, 1.253f, -0.17f), new Vector3(0f, 0f, 90f)));

                Animation = Main.gameObject.AddComponent<AnimationTransformVehicle>();

                yield return CoroutineEx.waitForSeconds(0.1f);

                if (Main == null) yield break;

                for (int i = Main.children.Count - 1; i >= 0; i--)
                {
                    BaseEntity entity = Main.children[i];
                    if (entity is DecorDeployable decor && Decor.Contains(decor)) continue;
                    if (entity.IsExists()) entity.Kill();
                }

                Stage = VendorStage.GoForward;

                GoForward();
            }

            internal void Destroy()
            {
                if (Coroutine != null) ServerMgr.Instance.StopCoroutine(Coroutine);

                if (Animation != null) UnityEngine.Object.Destroy(Animation);
                PointsForward = null;
                PointsBack = null;
                PointsOut = null;

                foreach (DecorDeployable decor in Decor) if (decor.IsExists()) decor.Kill();
                Decor = null;

                if (Main.IsExists()) Main.Kill();
            }

            private DecorDeployable SpawnTable(Vector3 pos, Vector3 rot)
            {
                DecorDeployable table = GameManager.server.CreateEntity("assets/prefabs/deployable/table/table.deployed.prefab") as DecorDeployable;
                table.enableSaving = false;

                table.transform.localPosition = pos;
                table.transform.localRotation = Quaternion.Euler(rot);

                table.SetParent(Main);

                UnityEngine.Object.DestroyImmediate(table.GetComponent<GroundWatch>());
                UnityEngine.Object.DestroyImmediate(table.GetComponent<DestroyOnGroundMissing>());

                table.Spawn();

                table.pickup.enabled = false;
                table.lastDecayTick = float.MaxValue;

                table.SetFlag(BaseEntity.Flags.Busy, true);

                table.skinID = 879859568;
                table.SendNetworkUpdate();

                return table;
            }

            private void CalculateAnimation()
            {
                PointsForward = new HashSet<PointAnimationTransform>
                {
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.03f, 5.579f, -81.024f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.03f, 5.579f, -78.359f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.03f, 5.579f, -75.71f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.03f, 5.579f, -73.127f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.874f, 5.579f, -71.39f)), GetGlobalRotation(new Vector3(0f, 4f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.575f, 5.579f, -69.334f)), GetGlobalRotation(new Vector3(0f, 8f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.107f, 5.579f, -67.098f)), GetGlobalRotation(new Vector3(0f, 12f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.526f, 5.579f, -65.101f)), GetGlobalRotation(new Vector3(0f, 16f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.957f, 5.579f, -63.116f)), GetGlobalRotation(new Vector3(0f, 16f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.393f, 5.579f, -61.149f)), GetGlobalRotation(new Vector3(0f, 16f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.985f, 5.579f, -59.171f)), GetGlobalRotation(new Vector3(0f, 12f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.575f, 5.579f, -57.238f)), GetGlobalRotation(new Vector3(0f, 12f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.293f, 5.579f, -55.178f)), GetGlobalRotation(new Vector3(0f, 8f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.007f, 5.579f, -53.144f)), GetGlobalRotation(new Vector3(0f, 8f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.859f, 5.579f, -50.991f)), GetGlobalRotation(new Vector3(0f, 4f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.715f, 5.579f, -48.928f)), GetGlobalRotation(new Vector3(0f, 4f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.573f, 5.579f, -46.898f)), GetGlobalRotation(new Vector3(0f, 4f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.43f, 5.579f, -44.855f)), GetGlobalRotation(new Vector3(0f, 4f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.44f, 5.579f, -42.808f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.44f, 5.579f, -40.668f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.44f, 5.579f, -38.49f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.594f, 5.579f, -36.324f)), GetGlobalRotation(new Vector3(0f, 356f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.891f, 5.579f, -34.279f)), GetGlobalRotation(new Vector3(0f, 352f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.336f, 5.579f, -32.223f)), GetGlobalRotation(new Vector3(0f, 348f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.777f, 5.579f, -30.147f)), GetGlobalRotation(new Vector3(0f, 348f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.224f, 5.579f, -28.044f)), GetGlobalRotation(new Vector3(0f, 348f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.809f, 5.579f, -26.004f)), GetGlobalRotation(new Vector3(0f, 344f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.524f, 5.579f, -24.064f)), GetGlobalRotation(new Vector3(0f, 340f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.41f, 5.579f, -22.074f)), GetGlobalRotation(new Vector3(0f, 336f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-37.408f, 5.579f, -20.206f)), GetGlobalRotation(new Vector3(0f, 332f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-38.568f, 5.579f, -18.348f)), GetGlobalRotation(new Vector3(0f, 328f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.833f, 5.579f, -16.61f)), GetGlobalRotation(new Vector3(0f, 324f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-41.239f, 5.579f, -14.945f)), GetGlobalRotation(new Vector3(0f, 320f, 0f)).eulerAngles)
                };
                PointsBack = new HashSet<PointAnimationTransform>
                {
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-39.293f, 5.579f, -16.549f)), GetGlobalRotation(new Vector3(0f, 303f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.851f, 5.579f, -17.918f)), GetGlobalRotation(new Vector3(0f, 296f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.386f, 5.579f, -19.008f)), GetGlobalRotation(new Vector3(0f, 292f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.888f, 5.579f, -19.821f)), GetGlobalRotation(new Vector3(0f, 285f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-29.397f, 5.579f, -20.356f)), GetGlobalRotation(new Vector3(0f, 280f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-26.658f, 5.579f, -20.683f)), GetGlobalRotation(new Vector3(0f, 274f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-23.935f, 5.579f, -20.772f)), GetGlobalRotation(new Vector3(0f, 270f, 0f)).eulerAngles)
                };
                PointsOut = new HashSet<PointAnimationTransform>
                {
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-25.637f, 5.579f, -20.969f)), GetGlobalRotation(new Vector3(0f, 265f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-28.389f, 5.579f, -21.374f)), GetGlobalRotation(new Vector3(0f, 260f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-30.274f, 5.579f, -21.81f)), GetGlobalRotation(new Vector3(0f, 255f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-31.711f, 5.579f, -22.626f)), GetGlobalRotation(new Vector3(0f, 243.396f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-32.871f, 5.579f, -23.671f)), GetGlobalRotation(new Vector3(0f, 232.869f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.965f, 5.579f, -25.025f)), GetGlobalRotation(new Vector3(0f, 222.426f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.796f, 5.579f, -26.389f)), GetGlobalRotation(new Vector3(0f, 214.303f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.374f, 5.579f, -27.647f)), GetGlobalRotation(new Vector3(0f, 208.374f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.881f, 5.579f, -29.189f)), GetGlobalRotation(new Vector3(0f, 201.038f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.118f, 5.579f, -30.617f)), GetGlobalRotation(new Vector3(0f, 193.516f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.256f, 5.579f, -32.293f)), GetGlobalRotation(new Vector3(0f, 186.833f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.151f, 5.579f, -33.865f)), GetGlobalRotation(new Vector3(0f, 179.269f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.904f, 5.579f, -35.382f)), GetGlobalRotation(new Vector3(0f, 173.336f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.552f, 5.579f, -37.014f)), GetGlobalRotation(new Vector3(0f, 169.069f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.256f, 5.579f, -38.549f)), GetGlobalRotation(new Vector3(0f, 169.069f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.893f, 5.579f, -40.425f)), GetGlobalRotation(new Vector3(0f, 169.069f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.423f, 5.579f, -42.859f)), GetGlobalRotation(new Vector3(0f, 169.069f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.078f, 5.579f, -44.648f)), GetGlobalRotation(new Vector3(0f, 169.069f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.794f, 5.579f, -46.663f)), GetGlobalRotation(new Vector3(0f, 172.304f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.662f, 5.579f, -48.605f)), GetGlobalRotation(new Vector3(0f, 176.047f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.474f, 5.579f, -51.322f)), GetGlobalRotation(new Vector3(0f, 176.047f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.365f, 5.579f, -52.895f)), GetGlobalRotation(new Vector3(0f, 176.047f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.468f, 5.579f, -55.141f)), GetGlobalRotation(new Vector3(0f, 182.711f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-33.738f, 5.579f, -56.794f)), GetGlobalRotation(new Vector3(0f, 187.806f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.154f, 5.579f, -58.742f)), GetGlobalRotation(new Vector3(0f, 191.29f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-34.569f, 5.579f, -60.817f)), GetGlobalRotation(new Vector3(0f, 191.29f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.003f, 5.579f, -62.992f)), GetGlobalRotation(new Vector3(0f, 191.29f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-35.553f, 5.579f, -65.747f)), GetGlobalRotation(new Vector3(0f, 191.29f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.221f, 5.579f, -69.094f)), GetGlobalRotation(new Vector3(0f, 191.29f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.534f, 5.579f, -71.354f)), GetGlobalRotation(new Vector3(0f, 187.791f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.656f, 5.579f, -73.3f)), GetGlobalRotation(new Vector3(0f, 184.062f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.676f, 5.579f, -75.724f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.676f, 5.579f, -77.812f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.676f, 5.579f, -80.572f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles),
                    new PointAnimationTransform(GetGlobalPosition(new Vector3(-36.676f, 5.579f, -83.87f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)).eulerAngles)
                };
            }

            internal void FinishPath()
            {
                switch (Stage)
                {
                    case VendorStage.GoForward:
                        Stage = VendorStage.GoBack;
                        Main.SetFlag(BaseEntity.Flags.Reserved2, true);
                        Main.SetFlag(BaseEntity.Flags.Reserved4, true);
                        GoBack();
                        break;
                    case VendorStage.GoBack:
                        Stage = VendorStage.SpawnBrains;
                        _event.SpawnBrainsCoroutine = ServerMgr.Instance.StartCoroutine(_event.SpawnBrains());
                        break;
                    case VendorStage.SpawnBrains:
                        Stage = VendorStage.GoOut;
                        Main.SetFlag(BaseEntity.Flags.Reserved2, false);
                        Main.SetFlag(BaseEntity.Flags.Reserved4, false);
                        GoOut();
                        break;
                    case VendorStage.GoOut:
                        _event.Vendor = null;
                        Destroy();
                        break;
                }
            }

            private void GoForward() => Animation.AddPath(PointsForward, 0f, 6f, 0.1f, -0.83f, 10, 6f);

            private void GoBack() => Animation.AddPath(PointsBack, 0.25f, 2f, 0.1f, -0.18f, 4, 0f);

            private void GoOut() => Animation.AddPath(PointsOut, 0.47f, 6f, 0.1f, -0.83f, 0, 0f);

            public enum VendorStage
            {
                Idle,
                GoForward,
                GoBack,
                SpawnBrains,
                GoOut
            }
        }
        #endregion Vendor

        #region Barge
        internal class CustomBarge
        {
            private HackCrateConfig _crateConfig => _ins._config.HackCrates;

            private static Vector3 GetGlobalPosition(Vector3 local) => _ins.Controller.GetGlobalPosition(local);
            private static Quaternion GetGlobalRotation(Vector3 local) => _ins.Controller.GetGlobalRotation(local);

            internal BaseEntity Main { get; set; } = null;
            private AnimationTransformVehicle Animation { get; set; } = null;
            internal HashSet<HackableLockedCrate> HackCrates { get; set; } = new HashSet<HackableLockedCrate>();
            private Coroutine Coroutine { get; set; } = null;

            internal CustomBarge() => Coroutine = ServerMgr.Instance.StartCoroutine(Spawn());

            private IEnumerator Spawn()
            {
                BaseEntity barge = GameManager.server.CreateEntity("assets/content/nexus/ferry/nexusferry.entity.prefab", GetGlobalPosition(new Vector3(69f, -1.2f, 200f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)));

                Main = barge.gameObject.AddComponent<BaseEntity>();
                CopySerializableFields(barge, Main);
                UnityEngine.Object.DestroyImmediate(barge, true);

                Main.enableSaving = false;
                Main.Spawn();

                List<Transform> list = Main.transform.GetChildren();
                for (int i = list.Count - 1; i >= 0; i--)
                {
                    GameObject game = list[i].gameObject;
                    if (game.name is "TriggerParent" or "SafeZone" or "prevent_building")
                        UnityEngine.Object.DestroyImmediate(game, true);
                }

                Animation = Main.gameObject.AddComponent<AnimationTransformVehicle>();

                yield return CoroutineEx.waitForSeconds(0.1f);

                if (Main == null) yield break;

                for (int i = Main.children.Count - 1; i >= 0; i--)
                {
                    BaseEntity entity = Main.children[i];
                    if (entity.IsExists()) entity.Kill();
                }

                SpawnHackCrates();

                Animation.AddPath(new HashSet<PointAnimationTransform> { new PointAnimationTransform(GetGlobalPosition(new Vector3(69f, -1.2f, 89.5f)), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles) }, 0f, 5f, 0f, -0.113f, 1, 5f);
            }

            internal void Destroy()
            {
                if (Coroutine != null) ServerMgr.Instance.StopCoroutine(Coroutine);
                if (Animation != null) UnityEngine.Object.Destroy(Animation);

                foreach (HackableLockedCrate hackCrate in HackCrates) if (hackCrate.IsExists()) hackCrate.Kill();
                HackCrates = null;

                if (Main.IsExists()) Main.Kill();
            }

            private void SpawnHackCrates()
            {
                Dictionary<Vector3, Vector3> locations = new Dictionary<Vector3, Vector3>
                {
                    [new Vector3(-2.325f, 3.5f, -0.106f)] = new Vector3(0f, 90f, 0f),
                    [new Vector3(2.329f, 3.5f, -0.106f)] = new Vector3(0f, 270f, 0f),
                    [new Vector3(-2.325f, 3.5f, -4.886f)] = new Vector3(0f, 90f, 0f),
                    [new Vector3(2.329f, 3.5f, -4.886f)] = new Vector3(0f, 270f, 0f)
                };

                foreach (KeyValuePair<Vector3, Vector3> location in locations)
                {
                    HackableLockedCrate hackCrate = GameManager.server.CreateEntity("assets/prefabs/deployable/chinooklockedcrate/codelockedhackablecrate.prefab") as HackableLockedCrate;
                    hackCrate.enableSaving = false;

                    hackCrate.transform.localPosition = location.Key;
                    hackCrate.transform.localRotation = Quaternion.Euler(location.Value);
                    hackCrate.SetParent(Main);

                    hackCrate.Spawn();

                    hackCrate.hackSeconds = HackableLockedCrate.requiredHackSeconds - _crateConfig.UnlockTime;

                    hackCrate.shouldDecay = false;
                    hackCrate.CancelInvoke(hackCrate.DelayedDestroy);

                    hackCrate.KillMapMarker();

                    Rigidbody rb = hackCrate.GetComponent<Rigidbody>();
                    rb.detectCollisions = false;
                    rb.isKinematic = true;

                    hackCrate.SetFlag(BaseEntity.Flags.Busy, true);

                    if (_crateConfig.TypeLootTable is 1 or 4 or 5)
                    {
                        hackCrate.inventory.ClearItemsContainer();
                        if (_crateConfig.TypeLootTable is 4 or 5) _ins.AddToContainerPrefab(hackCrate.inventory, _crateConfig.PrefabLootTable);
                        if (_crateConfig.TypeLootTable is 1 or 5) _ins.AddToContainerItem(hackCrate.inventory, _crateConfig.OwnLootTable);
                    }

                    HackCrates.Add(hackCrate);
                }

                locations = null;

                if (_ins.ActivePveMode) _ins.PveMode.Call("EventAddCrates", _ins.Name, HackCrates.Select(x => x.net.ID.Value));
            }

            internal void UnlockHackCrate(HackableLockedCrate hackCrate)
            {
                hackCrate.SetFlag(BaseEntity.Flags.Busy, false);
                hackCrate.StartHacking();
            }

            internal void FinishPath()
            {
                Main.limitNetworking = true;
                Main.limitNetworking = false;
                foreach (HackableLockedCrate hackCrate in HackCrates) hackCrate.SetParent(null, true, true);
            }
        }
        #endregion Barge

        #region Container Shore
        internal class CustomContainerShore
        {
            internal class Prefab { public string Path; public Vector3 Pos; public Vector3 Rot; }

            private static Vector3 GetGlobalPosition(Vector3 local) => _ins.Controller.GetGlobalPosition(local);
            private static Quaternion GetGlobalRotation(Vector3 local) => _ins.Controller.GetGlobalRotation(local);

            internal HashSet<BaseEntity> Entities { get; set; } = new HashSet<BaseEntity>();

            internal CustomContainerShore() => Spawn();

            private void Spawn()
            {
                HashSet<Prefab> prefabs = new HashSet<Prefab>
                {
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-61.646f, 0.687f, 12.203f), Rot = new Vector3(352.054f, 163.841f, 1.096f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-62.473f, 0.272f, 15.057f), Rot = new Vector3(352.054f, 163.841f, 1.096f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-63.3f, -0.143f, 17.911f), Rot = new Vector3(352.054f, 163.841f, 1.096f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-62.272f, -0.379f, 19.751f), Rot = new Vector3(358.915f, 253.993f, 352.053f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-60.417f, -0.2f, 18.738f), Rot = new Vector3(7.946f, 343.841f, 358.904f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-59.59f, 0.215f, 15.884f), Rot = new Vector3(7.946f, 343.841f, 358.904f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-58.763f, 0.63f, 13.03f), Rot = new Vector3(7.946f, 343.841f, 358.904f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-62.332f, 2.592f, 20.166f), Rot = new Vector3(358.915f, 253.992f, 82.052f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-61.505f, 3.007f, 17.312f), Rot = new Vector3(358.915f, 253.992f, 82.052f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-60.679f, 3.422f, 14.458f), Rot = new Vector3(358.915f, 253.992f, 82.052f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-62.272f, -0.379f, 19.751f), Rot = new Vector3(1.085f, 73.992f, 277.948f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-61.445f, 0.036f, 16.897f), Rot = new Vector3(1.085f, 73.992f, 277.948f) },
                    new Prefab { Path = "assets/prefabs/building core/wall/wall.prefab", Pos = new Vector3(-60.618f, 0.451f, 14.044f), Rot = new Vector3(1.085f, 73.992f, 277.948f) },
                    new Prefab { Path = "assets/prefabs/building core/wall.frame/wall.frame.prefab", Pos = new Vector3(-59.791f, 0.866f, 11.19f), Rot = new Vector3(1.085f, 73.993f, 7.947f) },
                    new Prefab { Path = "assets/prefabs/building/wall.frame.garagedoor/wall.frame.garagedoor.prefab", Pos = new Vector3(-59.791f, 0.866f, 11.19f), Rot = new Vector3(1.085f, 73.993f, 7.947f) }
                };

                foreach (Prefab prefab in prefabs)
                {
                    BaseEntity entity = GameManager.server.CreateEntity(prefab.Path, GetGlobalPosition(prefab.Pos), GetGlobalRotation(prefab.Rot));
                    entity.enableSaving = false;
                    entity.Spawn();
                    switch (entity)
                    {
                        case BuildingBlock buildingBlock:
                            {
                                buildingBlock.grounded = true;
                                buildingBlock.ChangeGradeAndSkin(BuildingGrade.Enum.Metal, 10221);
                                buildingBlock.SetCustomColour(1);
                                break;
                            }
                        case Door door:
                            {
                                door.grounded = true;
                                door.pickup.enabled = false;

                                door.canTakeLock = false;
                                door.canTakeCloser = false;
                                door.canTakeKnocker = false;

                                door.canNpcOpen = false;
                                door.canHandOpen = false;
                                door.hasHatch = false;

                                door.skinID = 1731321365;
                                door.SendNetworkUpdate();
                                break;
                            }
                    }
                    Entities.Add(entity);
                }

                prefabs = null;
            }

            internal void Destroy()
            {
                foreach (BaseEntity entity in Entities) if (entity.IsExists()) entity.Kill();
                Entities = null;
            }

            internal void OpenDoor()
            {
                Door door = Entities.FirstOrDefault(x => x is Door) as Door;
                if (door != null) door.SetOpen(true);
            }
        }
        #endregion Container Shore

        #region Ch47
        internal class CustomCh47
        {
            private ControllerFerryTerminalEvent _event => _ins.Controller;

            private static Vector3 GetGlobalPosition(Vector3 local) => _ins.Controller.GetGlobalPosition(local);
            private static Quaternion GetGlobalRotation(Vector3 local) => _ins.Controller.GetGlobalRotation(local);

            internal CH47HelicopterAIController Main { get; set; } = null;
            private AnimationTransformVehicle Animation { get; set; } = null;
            private Coroutine Coroutine { get; set; } = null;
            internal bool IsFinishPath { get; set; } = false;

            internal CustomCh47() => Coroutine = ServerMgr.Instance.StartCoroutine(ArrivalProcess());

            private void CreateAndSetupCh47(Vector3 position, Quaternion rotation, Vector3 target)
            {
                CH47HelicopterAIController ch47 = GameManager.server.CreateEntity("assets/prefabs/npc/ch47/ch47scientists.entity.prefab", position, rotation) as CH47HelicopterAIController;
                ch47.SetLandingTarget(target);
                if (Main.IsExists()) Main.Kill();
                Main = ch47;
                Main.Spawn();
                Main.CancelInvoke(Main.GetPrivateAction("CheckSpawnScientists"));
                Main.rigidBody.detectCollisions = false;
                Main.numCrates = 0;
                Main.SetMinHoverHeight(0f);
            }

            private IEnumerator ArrivalProcess()
            {
                Vector3 targetPos = GetGlobalPosition(new Vector3(85f, 12.5f, 112.6f));
                Vector3 landingTarget = targetPos + new Vector3(0f, 40f, 0f);

                CreateAndSetupCh47(GetGlobalPosition(new Vector3(85f, 52.5f, 200f)), GetGlobalRotation(new Vector3(0f, 180f, 0f)), landingTarget);

                Vector2 targetPosVector2 = new Vector2(targetPos.x, targetPos.z);
                float elapsed = 0f;
                float maxWaitTime = 120f;
                while (Vector2.Distance(new Vector2(Main.transform.position.x, Main.transform.position.z), targetPosVector2) > 1f)
                {
                    elapsed += 1f;
                    if (elapsed % 10f == 0f && Main.IsExists()) Main.SetLandingTarget(landingTarget);
                    if (elapsed >= maxWaitTime)
                    {
                        if (Main.IsExists())
                        {
                            Main.rigidBody.isKinematic = true;
                            Main.transform.position = landingTarget;
                            Main.transform.rotation = GetGlobalRotation(new Vector3(0f, 0f, 0f));
                            Main.TransformChanged();
                            Main.SendNetworkUpdate();
                        }
                        break;
                    }
                    yield return CoroutineEx.waitForSeconds(1f);
                }

                Main.AiAltitudeForce = 0f;
                Main.rigidBody.isKinematic = true;

                Animation = Main.gameObject.AddComponent<AnimationTransformVehicle>();
                Animation.AddPath(new HashSet<PointAnimationTransform>
                {
                    new PointAnimationTransform(targetPos + new Vector3(0f, 10f, 0f), GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles),
                    new PointAnimationTransform(targetPos, GetGlobalRotation(new Vector3(0f, 0f, 0f)).eulerAngles)
                }, 0.08f, 3f, 1f, -0.4f, 1, 1.5f);
            }

            internal void Departure() { Coroutine = ServerMgr.Instance.StartCoroutine(DepartureProcess()); }

            private IEnumerator DepartureProcess()
            {
                Vector3 targetPos = GetGlobalPosition(new Vector3(85f, 52.5f, 200f));
                Vector2 targetPosVector2 = new Vector2(targetPos.x, targetPos.z);

                CreateAndSetupCh47(Main.transform.position, Main.transform.rotation, targetPos);

                float elapsed = 0f;
                float maxWaitTime = 120f;
                while (Vector2.Distance(new Vector2(Main.transform.position.x, Main.transform.position.z), targetPosVector2) > 1f)
                {
                    elapsed += 1f;
                    if (elapsed % 10f == 0f && Main.IsExists()) Main.SetLandingTarget(targetPos);
                    if (elapsed >= maxWaitTime) break;
                    yield return CoroutineEx.waitForSeconds(1f);
                }

                _event.Ch47 = null;
                Destroy();
            }

            internal void Destroy()
            {
                if (Coroutine != null) ServerMgr.Instance.StopCoroutine(Coroutine);
                if (Animation != null) UnityEngine.Object.Destroy(Animation);
                if (Main.IsExists()) Main.Kill();
            }

            internal void FinishPath()
            {
                if (Animation != null) UnityEngine.Object.Destroy(Animation);
                IsFinishPath = true;
            }
        }
        #endregion Ch47

        #region CargoShip Container
        internal class CustomCargoShipContainer
        {
            private static Vector3 GetGlobalPosition(Vector3 local) => _ins.Controller.GetGlobalPosition(local);
            private static Quaternion GetGlobalRotation(Vector3 local) => _ins.Controller.GetGlobalRotation(local);

            internal SupplySignal Supply { get; set; } = null;
            internal CargoPlane Plane { get; set; } = null;
            internal CargoShipContainer Main { get; set; } = null;
            internal BaseEntity Parachute1 { get; set; } = null;
            internal BaseEntity Parachute2 { get; set; } = null;
            private AnimationTransformVehicle Animation { get; set; } = null;
            private Vector3 SupplyPos { get; set; } = Vector3.zero;
            internal bool IsFinishPath { get; set; } = false;

            internal CustomCargoShipContainer() => SpawnSupply();

            private void SpawnSupply()
            {
                SupplyPos = GetGlobalPosition(new Vector3(3.557f, 18.231f, -4.829f));
                Supply = GameManager.server.CreateEntity("assets/prefabs/tools/supply signal/grenade.supplysignal.deployed.prefab", SupplyPos) as SupplySignal;
                Supply.enableSaving = false;
                UnityEngine.Object.DestroyImmediate(Supply.GetComponent<EntityCollisionMessage>());
                UnityEngine.Object.DestroyImmediate(Supply.GetComponent<CapsuleCollider>());
                UnityEngine.Object.DestroyImmediate(Supply.GetComponent<Rigidbody>());
                Supply.Spawn();
            }

            internal void SpawnContainer()
            {
                Quaternion supplyRot = GetGlobalRotation(new Vector3(0f, 90f, 0f));

                Main = GameManager.server.CreateEntity("assets/content/props/shipping_containers/harbor_dynamic_container.prefab", new Vector3(SupplyPos.x, SupplyPos.y + 300f, SupplyPos.z), supplyRot) as CargoShipContainer;
                Main.enableSaving = false;
                UnityEngine.Object.DestroyImmediate(Main.GetComponent<MeshCollider>());
                UnityEngine.Object.DestroyImmediate(Main.GetComponent<EntityFlag_Toggle>());
                Main.Spawn();

                foreach (BaseEntity entity in Main.children)
                {
                    Door door = entity as Door;
                    if (door == null) continue;
                    door.canTakeLock = false;
                    door.canTakeCloser = false;
                    door.canTakeKnocker = false;
                    door.canNpcOpen = false;
                    door.canHandOpen = false;
                    door.canReverseOpen = false;
                    door.hasHatch = false;
                    door.SetFlag(BaseEntity.Flags.Locked, true);
                }

                Parachute1 = SpawnParachute(new Vector3(3.2f, 0.2f, 0f));
                Parachute2 = SpawnParachute(new Vector3(-3.2f, 0.2f, 0f));

                Animation = Main.gameObject.AddComponent<AnimationTransformVehicle>();
                Animation.AddPath(new HashSet<PointAnimationTransform> { new PointAnimationTransform(SupplyPos, supplyRot.eulerAngles) }, 0f, 25f, 1f, -1.04f, 1, 25f);
            }

            private BaseEntity SpawnParachute(Vector3 pos)
            {
                SupplyDrop drop = GameManager.server.CreateEntity("assets/prefabs/misc/supply drop/supply_drop.prefab") as SupplyDrop;

                UnityEngine.Object.DestroyImmediate(drop.GetComponent<EntityTimedDestroy>());
                UnityEngine.Object.DestroyImmediate(drop.GetComponent<Rigidbody>());

                BaseEntity result = drop.gameObject.AddComponent<BaseEntity>();
                CopySerializableFields(drop, result);
                UnityEngine.Object.DestroyImmediate(drop, true);

                result.enableSaving = false;

                result.transform.localPosition = pos;
                result.transform.localRotation = Quaternion.Euler(0f, 90f, 0f);
                result.SetParent(Main);

                result.Spawn();

                result.SetFlag(BaseEntity.Flags.Reserved1, true);
                result.SetFlag(BaseEntity.Flags.Reserved2, true);
                result.SetFlag(BaseEntity.Flags.Busy, true);

                return result;
            }

            internal void Destroy()
            {
                if (Animation != null) UnityEngine.Object.Destroy(Animation);
                if (Supply.IsExists()) Supply.Kill();
                if (Plane.IsExists()) Plane.Kill();
                DestroyParachutes();
                if (Main.IsExists()) Main.Kill();
            }

            private void DestroyParachutes()
            {
                if (Parachute1.IsExists()) Parachute1.Kill();
                if (Parachute2.IsExists()) Parachute2.Kill();
            }

            internal void FinishPath()
            {
                if (Animation != null) UnityEngine.Object.Destroy(Animation);
                if (Supply.IsExists()) Supply.Kill();
                DestroyParachutes();
                IsFinishPath = true;
            }

            internal void OpenDoor()
            {
                float single = float.MaxValue;
                Door result = null;

                foreach (BaseEntity entity in Main.children)
                {
                    Door door = entity as Door;
                    if (door == null) continue;

                    float z = _ins.Controller.transform.InverseTransformPoint(door.transform.position).z;

                    if (z < single)
                    {
                        single = z;
                        result = door;
                    }
                }

                if (result != null) result.SetOpen(true);
            }
        }
        #endregion CargoShip Container

        #region Security Point
        internal class SecurityPoint : FacepunchBehaviour
        {
            private PluginConfig _config => _ins._config;
            private ControllerFerryTerminalEvent _event => _ins.Controller;

            private SphereEntity SphereEntity { get; set; } = null;
            private SphereCollider SphereCollider { get; set; } = null;
            private float SecurityPercent { get; set; } = 0f;
            internal HashSet<BasePlayer> InsidePlayers { get; set; } = new HashSet<BasePlayer>();
            internal BasePlayer Hostage { get; set; } = null;

            internal void Init(BasePlayer hostage)
            {
                Hostage = hostage;

                transform.position = Hostage.transform.position;
                transform.rotation = Hostage.transform.rotation;

                gameObject.layer = 3;
                SphereCollider = gameObject.AddComponent<SphereCollider>();
                SphereCollider.isTrigger = true;
                SphereCollider.radius = 1f;

                if (_config.Hostage.IsSpawnRedBubble)
                {
                    SphereEntity = GameManager.server.CreateEntity("assets/bundled/prefabs/modding/events/twitch/br_sphere_red.prefab", transform.position, transform.rotation) as SphereEntity;
                    SphereEntity.currentRadius = SphereCollider.radius * 2f;
                    SphereEntity.lerpRadius = SphereEntity.currentRadius;
                    SphereEntity.lerpSpeed = 0f;
                    SphereEntity.enableSaving = false;
                    SphereEntity.Spawn();
                }
            }

            private void OnDestroy()
            {
                CancelInvoke(IncreasePercent);
                if (SphereCollider != null) Destroy(SphereCollider);
                foreach (BasePlayer player in InsidePlayers) CuiHelper.DestroyUi(player, "SecurityPoint_KpucTaJl");
                InsidePlayers = null;
                if (SphereEntity.IsExists()) SphereEntity.Kill();
            }

            private void OnTriggerEnter(Collider other) => EnterPlayer(other.GetComponentInParent<BasePlayer>());

            internal void EnterPlayer(BasePlayer player)
            {
                if (!player.IsPlayer() || InsidePlayers.Contains(player)) return;
                if (player.IsSleeping() || player.IsWounded() || player._limitedNetworking) return;
                if (_event.SecurityPoints.Any(x => x.InsidePlayers.Contains(player))) return;
                if (_ins.ActivePveMode && _ins.PveMode.Call("CanActionEvent", _ins.Name, player) != null) return;
                InsidePlayers.Add(player);
                if (InsidePlayers.Count == 1) InvokeRepeating(IncreasePercent, 0.5f, 0.5f);
            }

            private void OnTriggerExit(Collider other) => ExitPlayer(other.GetComponentInParent<BasePlayer>());

            internal void ExitPlayer(BasePlayer player)
            {
                if (!player.IsPlayer() || !InsidePlayers.Contains(player)) return;
                InsidePlayers.Remove(player);
                CuiHelper.DestroyUi(player, "SecurityPoint_KpucTaJl");
                if (InsidePlayers.Count == 0) CancelInvoke(IncreasePercent);
            }

            private void IncreasePercent()
            {
                foreach (BasePlayer player in InsidePlayers.ToHashSet())
                {
                    if (player.IsSleeping() || player.IsWounded() || player._limitedNetworking)
                    {
                        ExitPlayer(player);
                        if (InsidePlayers.Count == 0) return;
                    }
                }
                SecurityPercent += _config.Hostage.PercentPerSec * 0.5f * InsidePlayers.Count;
                if (_config.Gui.IsGui) foreach (BasePlayer player in InsidePlayers) UpdateGui(player);
                if (SecurityPercent >= 100f) FinishSecurity();
            }

            private void UpdateGui(BasePlayer player)
            {
                CuiHelper.DestroyUi(player, "SecurityPoint_KpucTaJl");

                CuiElementContainer container = new CuiElementContainer();

                container.Add(new CuiPanel
                {
                    Image = { Color = "0.38 0.38 0.38 1" },
                    RectTransform = { AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = $"-199 {_config.Gui.SecurityPointOffsetMinY}", OffsetMax = $"181 {_config.Gui.SecurityPointOffsetMinY + 10}" },
                    CursorEnabled = false,
                }, "Hud", "SecurityPoint_KpucTaJl");

                container.Add(new CuiElement
                {
                    Parent = "SecurityPoint_KpucTaJl",
                    Components =
                    {
                        new CuiImageComponent { Color = "0.67 0.28 0.21 1" },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = $"{SecurityPercent / 100f} 0.9" }
                    }
                });

                container.Add(new CuiElement
                {
                    Parent = "SecurityPoint_KpucTaJl",
                    Components =
                    {
                        new CuiTextComponent { Color = "1 1 1 1", Text = $"{Math.Round(SecurityPercent, 1)}%", Align = TextAnchor.MiddleCenter, FontSize = 8 },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1" }
                    }
                });

                CuiHelper.AddUi(player, container);
            }

            private void FinishSecurity()
            {
                CancelInvoke(IncreasePercent);
                SecurityPercent = 100f;
                foreach (BasePlayer player in InsidePlayers) _ins.ActionEconomy(player.userID, "UnlockHostage");
                _ins.Controller.FinishSecurity(this);
            }
        }
        #endregion Security Point

        #region Motorbike
        public class MotorbikeStep
        {
            public float StartSpeed { get; set; }
            public int NumberBrakingPoints { get; set; }
            public HashSet<PointAnimationTransform> Path { get; set; }
            public float ForwardForce { get; set; }
            public float UpTorque { get; set; }
            public float RightTorque { get; set; }
            public float Time { get; set; }
        }

        public class MotorbikeRoute
        {
            public PointAnimationTransform Spawn { get; set; }
            public HashSet<MotorbikeStep> Steps { get; set; }
        }

        internal class Motorbike
        {
            private PluginConfig _config => _ins._config;
            private ControllerFerryTerminalEvent _event => _ins.Controller;

            private float Acceleration { get; } = 2f;
            private float Braking { get; } = -3.5f;
            private float MaxSpeed { get; } = 10f;
            private float MinSpeed { get; } = 0f;

            internal Bike Bike { get; set; } = null;

            internal ScientistNPC Driver { get; set; } = null;
            private BikeDriverSeat DriverSeat { get; set; } = null;

            private AnimationTransformVehicle Animation { get; set; } = null;
            private Coroutine Coroutine { get; set; } = null;
            private Dictionary<int, MotorbikeStep> Steps { get; set; } = new Dictionary<int, MotorbikeStep>();
            private int CurrentStep { get; set; } = 0;

            internal Motorbike(PointAnimationTransform spawn, HashSet<MotorbikeStep> steps, bool isNextStep)
            {
                int index = 1;
                foreach (MotorbikeStep data in steps)
                {
                    Steps.Add(index, data);
                    index++;
                }

                Bike = GameManager.server.CreateEntity("assets/content/vehicles/bikes/motorbike.prefab", spawn.Pos, Quaternion.Euler(spawn.Rot)) as Bike;
                Bike.enableSaving = false;

                Spawnable spawnable = Bike.GetComponent<Spawnable>();
                if (spawnable != null) UnityEngine.Object.DestroyImmediate(spawnable);

                MagnetLiftable magnetLiftable = Bike.GetComponent<MagnetLiftable>();
                if (magnetLiftable != null) UnityEngine.Object.DestroyImmediate(magnetLiftable);

                Bike.Spawn();

                Bike.SetFlag(BaseEntity.Flags.Busy, true);

                foreach (BaseEntity entity in Bike.children)
                {
                    switch (entity)
                    {
                        case StorageContainer container:
                            {
                                ItemManager.CreateByName("lowgradefuel", 100).MoveToContainer(container.inventory);
                                container.SetFlag(BaseEntity.Flags.Locked, true);
                                break;
                            }
                        case BikeDriverSeat seat:
                            DriverSeat = seat;
                            Driver = (ScientistNPC)_ins.NpcSpawn.Call("SpawnNpc", Bike.transform.position, _event.GetObjectConfig(_config.MotorbikeNpc, string.Empty, true, false));
                            DriverSeat.MountPlayer(Driver);
                            Bike.engineController.TryStartEngine(Driver);
                            Bike.LightToggle(Driver);
                            break;
                    }
                }

                Bike.rigidBody.detectCollisions = false;
                Bike.rigidBody.isKinematic = true;

                Animation = Bike.gameObject.AddComponent<AnimationTransformVehicle>();

                if (isNextStep) NextStep();
            }

            internal void Destroy()
            {
                if (Coroutine != null) ServerMgr.Instance.StopCoroutine(Coroutine);
                UnityEngine.Object.Destroy(Animation);
                Steps = null;
                if (Driver.IsExists()) Driver.Kill();
                if (Bike.IsExists()) Bike.Kill();
            }

            internal void NextStep()
            {
                CurrentStep++;
                if (Steps.TryGetValue(CurrentStep, out MotorbikeStep data)) Animation.AddPath(data.Path, Acceleration, MaxSpeed, MinSpeed, Braking, data.NumberBrakingPoints, data.StartSpeed);
                else Coroutine = ServerMgr.Instance.StartCoroutine(FinishSteps());
            }

            internal void FinishPath() { Coroutine = ServerMgr.Instance.StartCoroutine(FinishStep()); }

            private IEnumerator FinishStep()
            {
                MotorbikeStep data = Steps[CurrentStep];

                if (data.Time > 0f)
                {
                    SetStabVariables(false);
                    Bike.rigidBody.isKinematic = false;

                    if (data.ForwardForce != 0f) Bike.rigidBody.AddForce(Bike.transform.forward * data.ForwardForce, ForceMode.Force);
                    if (data.UpTorque != 0f) Bike.rigidBody.AddTorque(Bike.transform.up * data.UpTorque, ForceMode.Force);
                    if (data.RightTorque != 0f) Bike.rigidBody.AddTorque(Bike.transform.right * data.RightTorque, ForceMode.Force);

                    yield return CoroutineEx.waitForSeconds(data.Time);

                    SetStabVariables(true);
                    Bike.rigidBody.isKinematic = true;
                }

                NextStep();
            }

            private IEnumerator FinishSteps()
            {
                yield return CoroutineEx.waitForSeconds(1f);

                if (Driver.IsExists()) Driver.Kill();

                string home = _event.GetGlobalPosition(new Vector3(50.705f, 5.155f, 28.516f)).ToString();
                ScientistNPC npc = (ScientistNPC)_ins.NpcSpawn.Call("SpawnNpc", Bike.transform.position + new Vector3(0f, 0.3f, 0f), _event.GetObjectConfig(_config.MotorbikeNpc, home, false, true));
                _event.AddNoDamageDriver(npc);
                _event.Drivers.Add(npc);

                if (_ins.ActivePveMode) _ins.PveMode.Call("EventAddScientists", _ins.Name, new HashSet<ulong> { npc.net.ID.Value });

                yield return CoroutineEx.waitForSeconds(0.5f);

                _event.Motorbikes.Remove(this);
                Destroy();
            }

            private void SetStabVariables(bool isDefault)
            {
                Bike.SetPrivateFieldValue("airControlTorquePower", isDefault ? 0.075f : 0f);
                Bike.SetPrivateFieldValue("pitchStabP", isDefault ? 0.005f : 0f);
                Bike.SetPrivateFieldValue("pitchStabD", isDefault ? 0.001f : 0f);
                Bike.SetPrivateFieldValue("twoWheelRollStabP", isDefault ? 0.15f : 0f);
                Bike.SetPrivateFieldValue("twoWheelRollStabD", isDefault ? 0.01f : 0f);
                Bike.SetPrivateFieldValue("manyWheelStabP", isDefault ? 30f : 0f);
            }
        }
        #endregion Motorbike

        #region Animation
        internal class AnimationTransformBrain : FacepunchBehaviour
        {
            private BaseEntity Main { get; set; } = null;
            private Queue<Vector3> Path { get; } = new Queue<Vector3>();
            private float Speed { get; set; } = 0f;
            private Vector3 CurrentTarget { get; set; } = Vector3.zero;
            internal bool IsUpdate { get; set; } = false;

            private void Awake()
            {
                Main = GetComponent<BaseEntity>();
                enabled = false;
            }

            internal void AddPath(IEnumerable<Vector3> path, float speed = 0f)
            {
                foreach (Vector3 point in path) Path.Enqueue(point);
                TryStartMovement(speed);
            }

            internal void AddPoint(Vector3 point, float speed = 0f)
            {
                Path.Enqueue(point);
                TryStartMovement(speed);
            }

            private void TryStartMovement(float speed)
            {
                if (enabled) return;
                Speed = speed;
                SetNextTarget();
                enabled = true;
            }

            private void Update()
            {
                if (PositionEquals(transform.position, CurrentTarget))
                {
                    if (Path.Count == 0)
                    {
                        ResetMovement();
                        FinishPath();
                        return;
                    }
                    SetNextTarget();
                }
                MoveTowardsTarget();
            }

            private void ResetMovement()
            {
                Speed = 0f;
                CurrentTarget = Vector3.zero;
                enabled = false;
            }

            private void SetNextTarget()
            {
                CurrentTarget = Path.Dequeue();
                UpdateLookToTarget();
            }

            private void MoveTowardsTarget()
            {
                float distancePerTime = Speed * Time.deltaTime;
                float distanceToTarget = Vector3.Distance(transform.position, CurrentTarget);
                float progress = Mathf.Clamp01(distancePerTime / distanceToTarget);
                transform.position = Vector3.Lerp(transform.position, CurrentTarget, progress);
                if (!IsUpdate) return;
                Main.TransformChanged();
                Main.SendNetworkUpdate();
            }

            private static bool PositionEquals(Vector3 v1, Vector3 v2) => Vector3.Distance(v1, v2) <= 0.001f;

            private void UpdateLookToTarget()
            {
                Vector3 direction = CurrentTarget - transform.position;
                if (direction.sqrMagnitude < 0.0001f) return;
                Quaternion look = Quaternion.LookRotation(direction);
                switch (Main)
                {
                    case BaseAnimalNPC animal:
                        animal.transform.rotation = look;
                        break;
                    case BasePlayer basePlayer:
                        basePlayer.viewAngles = look.eulerAngles;
                        break;
                }
            }

            private void FinishPath()
            {
                _ins.Controller.FinishPathBrain(Main);
            }
        }

        internal class PointAnimationTransform
        {
            public Vector3 Pos;
            public Vector3 Rot;

            public PointAnimationTransform(Vector3 pos, Vector3 rot)
            {
                Pos = pos;
                Rot = rot;
            }
        }

        internal class AnimationTransformVehicle : FacepunchBehaviour
        {
            private BaseEntity Main { get; set; } = null;

            private Queue<PointAnimationTransform> Path { get; } = new Queue<PointAnimationTransform>();

            private float PointDone { get; set; } = 1f;

            private Vector3 TargetPos { get; set; } = Vector3.zero;
            private Quaternion TargetRot { get; set; } = Quaternion.identity;

            private float Acceleration { get; set; } = 0f;
            private float SpeedMax { get; set; } = 0f;

            private float SpeedMin { get; set; } = 0f;
            private float Braking { get; set; } = 0f;
            private int NumberBrakingPoints { get; set; } = 0;

            private float Speed { get; set; } = 0f;

            internal bool IsUpdate { get; set; } = false;

            private void Awake()
            {
                Main = GetComponent<BaseEntity>();
                enabled = false;
            }

            internal void AddPath(HashSet<PointAnimationTransform> path, float acceleration, float speedMax, float speedMin, float braking, int numberBrakingPoints, float speedStart)
            {
                foreach (PointAnimationTransform point in path) Path.Enqueue(point);
                Acceleration = acceleration;
                SpeedMax = speedMax;
                SpeedMin = speedMin;
                Braking = braking;
                NumberBrakingPoints = numberBrakingPoints;
                Speed = speedStart;
                enabled = true;
            }

            private void Update()
            {
                if (Mathf.Abs(1f - PointDone) <= 0.01f)
                {
                    if (Path.Count == 0)
                    {
                        ResetMovement();
                        FinishPath();
                        return;
                    }
                    SetNextTarget();
                }
                UpdateValues();
                if (Speed == 0f)
                {
                    if (Path.Count > 0) Path.Clear();
                    PointDone = 1f;
                    return;
                }
                if (!PositionEquals(transform.position, TargetPos)) transform.position = Vector3.Lerp(transform.position, TargetPos, PointDone);
                if (!RotationEquals(transform.rotation, TargetRot)) transform.rotation = Quaternion.Lerp(transform.rotation, TargetRot, PointDone);
                if (IsUpdate)
                {
                    Main.TransformChanged();
                    Main.SendNetworkUpdate();
                }
            }

            private void ResetMovement()
            {
                PointDone = 1f;
                TargetPos = Vector3.zero;
                TargetRot = Quaternion.identity;
                Acceleration = SpeedMax = SpeedMin = Braking = Speed = 0f;
                NumberBrakingPoints = 0;
                enabled = false;
            }

            private void SetNextTarget()
            {
                PointDone = 0f;
                PointAnimationTransform point = Path.Dequeue();
                TargetPos = point.Pos;
                TargetRot = Quaternion.Euler(point.Rot);
            }

            private void UpdateValues()
            {
                float speed0 = Speed;
                float speedChangePerUnitTime = Path.Count < NumberBrakingPoints ? Braking : Acceleration;
                float time = Time.deltaTime;

                float distancePerTime = 0f, distanceToTarget = 0f;

                if (speedChangePerUnitTime == 0f)
                {
                    Speed = speed0;
                    if (Speed > SpeedMax) Speed = SpeedMax;
                    if (Speed < SpeedMin) Speed = SpeedMin;
                    distancePerTime = Speed * time;
                    distanceToTarget = Vector3.Distance(transform.position, TargetPos);
                }
                else
                {
                    CheckSoundBike(speedChangePerUnitTime);
                    Speed = speed0 + speedChangePerUnitTime * time;
                    if (Speed < SpeedMin) Speed = SpeedMin;
                    if (Speed > SpeedMax) Speed = SpeedMax;
                    distancePerTime = speed0 * time + (speedChangePerUnitTime * time * time) / 2f;
                    distanceToTarget = Vector3.Distance(transform.position, TargetPos);
                }

                PointDone = Mathf.InverseLerp(0f, distanceToTarget, distancePerTime);
                if (PointDone > 1f) PointDone = 1f;
            }

            private static bool PositionEquals(Vector3 v1, Vector3 v2) => Vector3.Distance(v1, v2) <= 0.001f;

            private static bool RotationEquals(Quaternion r1, Quaternion r2) => Mathf.Abs(Quaternion.Dot(r1, r2)) >= 1f;

            private void FinishPath()
            {
                switch (Main.ShortPrefabName)
                {
                    case "travellingvendor":
                        _ins.Controller.Vendor.FinishPath();
                        break;
                    case "motorbike":
                        Bike bike = Main as Bike;
                        bike.SetPrivateFieldValue("_throttle", 0f);
                        bike.SetPrivateFieldValue("_brake", 0f);
                        _ins.Controller.FinishPathBike(bike);
                        break;
                    case "ch47scientists.entity":
                        _ins.Controller.Ch47.FinishPath();
                        break;
                    case "harbor_dynamic_container":
                        _ins.Controller.CargoShipContainer.FinishPath();
                        break;
                    case "nexusferry.entity":
                        _ins.Controller.Barge.FinishPath();
                        break;
                }
            }

            private void CheckSoundBike(float speedChangePerUnitTime)
            {
                Bike bike = Main as Bike;
                if (bike == null) return;
                if (speedChangePerUnitTime > 0f)
                {
                    Main.SetPrivateFieldValue("_throttle", 1f);
                    Main.SetPrivateFieldValue("_brake", 0f);
                }
                else if (speedChangePerUnitTime < 0f && Speed > SpeedMin)
                {
                    Main.SetPrivateFieldValue("_throttle", 0f);
                    Main.SetPrivateFieldValue("_brake", 1f);
                }
            }
        }
        #endregion Animation

        #region Find Position
        internal MonumentInfo GetMonument()
        {
            List<MonumentInfo> list = Pool.Get<List<MonumentInfo>>();
            foreach (MonumentInfo monument in TerrainMeta.Path.Monuments)
            {
                if (monument.displayPhrase.english != "Ferry Terminal") continue;
                list.Add(monument);
            }
            MonumentInfo result = list.Count > 0 ? list.GetRandom() : null;
            Pool.FreeUnmanaged(ref list);
            return result;
        }
        #endregion Find Position

        #region Radiation Puzzle Reset
        private static PuzzleReset? GetPuzzleReset(MonumentInfo monument)
        {
            PuzzleReset result = null;
            float distance = float.MaxValue;
            foreach (PuzzleReset puzzleReset in PuzzleReset.AllResets)
            {
                if (!puzzleReset.radiationReset) continue;
                float single = Vector3.Distance(monument.transform.position, puzzleReset.transform.position);
                if (single < distance)
                {
                    result = puzzleReset;
                    distance = single;
                }
            }
            return result;
        }

        private static void DisableRadiation(PuzzleReset? puzzleReset)
        {
            if (puzzleReset == null) return;
            SetPuzzleRadiationAmount(puzzleReset, 0f);
            puzzleReset.radiationReset = false;
        }

        private static void EnableRadiation(PuzzleReset? puzzleReset)
        {
            if (puzzleReset == null) return;
            puzzleReset.radiationReset = true;
        }

        private static void SetPuzzleRadiationAmount(PuzzleReset pr, float amount)
        {
            var mi = typeof(PuzzleReset).GetMethod("SetRadiusRadiationAmount", System.Reflection.BindingFlags.Instance | System.Reflection.BindingFlags.NonPublic | System.Reflection.BindingFlags.Public);
            mi?.Invoke(pr, new object[] { amount });
        }
        #endregion Radiation Puzzle Reset

        #region Spawn Loot
        #region Npc
        private void OnCorpsePopulate(ScientistNPC entity, NPCPlayerCorpse corpse)
        {
            if (entity == null) return;

            if (Controller.Scientists.Contains(entity))
            {
                Controller.Scientists.Remove(entity);
                PresetConfig preset = _config.NpcPresets.FirstOrDefault(x => x.Config.Name == entity.displayName);
                if (preset == null) return;
                CorpsePopulate(corpse, preset.TypeLootTable, preset.PrefabLootTable, preset.OwnLootTable, preset.Config.IsRemoveCorpse);
                return;
            }

            if (Controller.Guards.Contains(entity))
            {
                Controller.Guards.Remove(entity);
                CorpsePopulate(corpse, _config.SecurityNpc.TypeLootTable, _config.SecurityNpc.PrefabLootTable, _config.SecurityNpc.OwnLootTable, _config.SecurityNpc.IsRemoveCorpse);
                return;
            }

            if (Controller.Drivers.Contains(entity))
            {
                Controller.Drivers.Remove(entity);
                CorpsePopulate(corpse, _config.MotorbikeNpc.TypeLootTable, _config.MotorbikeNpc.PrefabLootTable, _config.MotorbikeNpc.OwnLootTable, _config.MotorbikeNpc.IsRemoveCorpse);
                return;
            }
        }

        private object CanPopulateLoot(ScientistNPC entity, NPCPlayerCorpse corpse)
        {
            if (entity == null || corpse == null || Controller == null) return null;

            if (Controller.Scientists.Contains(entity))
            {
                PresetConfig preset = _config.NpcPresets.FirstOrDefault(x => x.Config.Name == entity.displayName);
                if (preset == null) return null;
                if (preset.TypeLootTable == 2) return null;
                else return true;
            }

            if (Controller.Guards.Contains(entity))
            {
                if (_config.SecurityNpc.TypeLootTable == 2) return null;
                else return true;
            }

            if (Controller.Drivers.Contains(entity))
            {
                if (_config.MotorbikeNpc.TypeLootTable == 2) return null;
                else return true;
            }

            return null;
        }

        private object OnCustomLootNPC(NetworkableId netId)
        {
            if (Controller == null) return null;

            ScientistNPC scientist = Controller.Scientists.FirstOrDefault(x => x.IsExists() && x.net.ID.Value == netId.Value);
            if (scientist != null)
            {
                PresetConfig preset = _config.NpcPresets.FirstOrDefault(x => x.Config.Name == scientist.displayName);
                if (preset == null) return null;
                if (preset.TypeLootTable == 3) return null;
                else return true;
            }

            ScientistNPC guard = Controller.Guards.FirstOrDefault(x => x.IsExists() && x.net.ID.Value == netId.Value);
            if (guard != null)
            {
                if (_config.SecurityNpc.TypeLootTable == 3) return null;
                else return true;
            }

            ScientistNPC driver = Controller.Drivers.FirstOrDefault(x => x.IsExists() && x.net.ID.Value == netId.Value);
            if (driver != null)
            {
                if (_config.MotorbikeNpc.TypeLootTable == 3) return null;
                else return true;
            }

            return null;
        }

        private void CorpsePopulate(NPCPlayerCorpse corpse, int typeLootTable, PrefabLootTableConfig prefabLootTable, LootTableConfig ownLootTable, bool isRemoveCorpse)
        {
            NextTick(() =>
            {
                if (corpse == null) return;
                ItemContainer container = corpse.containers[0];
                if (typeLootTable is 1 or 4 or 5)
                {
                    container.ClearItemsContainer();
                    if (typeLootTable is 4 or 5) AddToContainerPrefab(container, prefabLootTable);
                    if (typeLootTable is 1 or 5) AddToContainerItem(container, ownLootTable);
                }
                if (isRemoveCorpse && corpse.IsExists()) corpse.Kill();
            });
        }
        #endregion Npc

        #region Crates
        private object CanPopulateLoot(HackableLockedCrate container)
        {
            if (container == null || Controller == null || Controller.Barge == null) return null;
            if (Controller.Barge.HackCrates.Contains(container))
            {
                if (_config.HackCrates.TypeLootTable == 2) return null;
                else return true;
            }
            else return null;
        }

        private object OnCustomLootContainer(NetworkableId netId)
        {
            if (Controller == null || Controller.Barge == null) return null;
            if (Controller.Barge.HackCrates.Any(x => x.IsExists() && x.net.ID.Value == netId.Value))
            {
                if (_config.HackCrates.TypeLootTable == 3) return null;
                else return true;
            }
            else return null;
        }

        private object OnContainerPopulate(HackableLockedCrate container)
        {
            if (container == null || Controller == null || Controller.Barge == null) return null;
            if (Controller.Barge.HackCrates.Contains(container))
            {
                if (_config.HackCrates.TypeLootTable == 6) return null;
                else return true;
            }
            else return null;
        }
        #endregion Crates

        private void AddToContainerPrefab(ItemContainer container, PrefabLootTableConfig lootTable)
        {
            if (lootTable.UseCount)
            {
                int count = 0, max = UnityEngine.Random.Range(lootTable.Min, lootTable.Max + 1);
                while (count < max)
                {
                    foreach (PrefabConfig prefab in lootTable.Prefabs)
                    {
                        if (UnityEngine.Random.Range(0f, 100f) > prefab.Chance) continue;
                        SpawnIntoContainer(container, prefab.PrefabDefinition);
                        count++;
                        if (count == max) break;
                    }
                }
            }
            else foreach (PrefabConfig prefab in lootTable.Prefabs) if (UnityEngine.Random.Range(0f, 100f) <= prefab.Chance) SpawnIntoContainer(container, prefab.PrefabDefinition);
        }

        private void SpawnIntoContainer(ItemContainer container, string prefab)
        {
            if (AllLootSpawnSlots.ContainsKey(prefab))
            {
                foreach (LootContainer.LootSpawnSlot lootSpawnSlot in AllLootSpawnSlots[prefab])
                    for (int j = 0; j < lootSpawnSlot.numberToSpawn; j++)
                        if (UnityEngine.Random.Range(0f, 1f) <= lootSpawnSlot.probability)
                            lootSpawnSlot.definition.SpawnIntoContainer(container);
            }
            else AllLootSpawn[prefab].SpawnIntoContainer(container);
        }

        private void AddToContainerItem(ItemContainer container, LootTableConfig lootTable)
        {
            if (lootTable.UseCount)
            {
                HashSet<int> indexMove = new HashSet<int>();
                int count = UnityEngine.Random.Range(lootTable.Min, lootTable.Max + 1);
                while (indexMove.Count < count)
                {
                    for (int i = 0; i < lootTable.Items.Count; i++)
                    {
                        if (indexMove.Contains(i)) continue;
                        if (SpawnIntoContainer(container, lootTable.Items[i]))
                        {
                            indexMove.Add(i);
                            if (indexMove.Count == count) break;
                        }
                    }
                }
                indexMove = null;
            }
            else foreach (ItemConfig item in lootTable.Items) SpawnIntoContainer(container, item);
        }

        private bool SpawnIntoContainer(ItemContainer container, ItemConfig config)
        {
            if (UnityEngine.Random.Range(0f, 100f) > config.Chance) return false;
            Item item = config.IsBluePrint ? ItemManager.CreateByName("blueprintbase") : ItemManager.CreateByName(config.ShortName, UnityEngine.Random.Range(config.MinAmount, config.MaxAmount + 1), config.SkinId);
            if (item == null)
            {
                PrintWarning($"Failed to create item! ({config.ShortName})");
                return false;
            }
            if (config.IsBluePrint) item.blueprintTarget = ItemManager.FindItemDefinition(config.ShortName).itemid;
            if (!string.IsNullOrEmpty(config.Name)) item.name = config.Name;
            if (container.capacity < container.itemList.Count + 1) container.capacity++;
            if (!item.MoveToContainer(container))
            {
                item.Remove();
                return false;
            }
            return true;
        }

        private void CheckAllLootTables()
        {
            foreach (PresetConfig preset in _config.NpcPresets)
            {
                CheckLootTable(preset.OwnLootTable);
                CheckPrefabLootTable(preset.PrefabLootTable);
            }

            CheckLootTable(_config.SecurityNpc.OwnLootTable);
            CheckPrefabLootTable(_config.SecurityNpc.PrefabLootTable);

            CheckLootTable(_config.MotorbikeNpc.OwnLootTable);
            CheckPrefabLootTable(_config.MotorbikeNpc.PrefabLootTable);

            CheckLootTable(_config.HackCrates.OwnLootTable);
            CheckPrefabLootTable(_config.HackCrates.PrefabLootTable);
        }

        private void CheckLootTable(LootTableConfig lootTable)
        {
            for (int i = lootTable.Items.Count - 1; i >= 0; i--)
            {
                ItemConfig item = lootTable.Items[i];

                if (!ItemManager.itemList.Any(x => x.shortname == item.ShortName))
                {
                    PrintWarning($"Unknown item removed! ({item.ShortName})");
                    lootTable.Items.Remove(item);
                    continue;
                }
                if (item.Chance <= 0f)
                {
                    PrintWarning($"An item with an incorrect probability has been removed from the loot table ({item.ShortName})");
                    lootTable.Items.Remove(item);
                    continue;
                }

                if (item.MinAmount <= 0) item.MinAmount = 1;
                if (item.MaxAmount < item.MinAmount) item.MaxAmount = item.MinAmount;
            }

            lootTable.Items = lootTable.Items.OrderByQuickSort(x => x.Chance);
            if (lootTable.Items.Any(x => x.Chance >= 100f))
            {
                HashSet<ItemConfig> newItems = new HashSet<ItemConfig>();

                for (int i = lootTable.Items.Count - 1; i >= 0; i--)
                {
                    ItemConfig itemConfig = lootTable.Items[i];
                    if (itemConfig.Chance < 100f) break;
                    newItems.Add(itemConfig);
                    lootTable.Items.Remove(itemConfig);
                }

                int count = newItems.Count;

                if (count > 0)
                {
                    foreach (ItemConfig itemConfig in lootTable.Items) newItems.Add(itemConfig);
                    lootTable.Items.Clear();
                    foreach (ItemConfig itemConfig in newItems) lootTable.Items.Add(itemConfig);
                }

                newItems = null;

                if (lootTable.Min < count) lootTable.Min = count;
                if (lootTable.Max < count) lootTable.Max = count;
            }

            if (lootTable.Max > lootTable.Items.Count) lootTable.Max = lootTable.Items.Count;
            if (lootTable.Min > lootTable.Max) lootTable.Min = lootTable.Max;
            if (lootTable.Items.Count == 0) lootTable.UseCount = false;
        }

        private void CheckPrefabLootTable(PrefabLootTableConfig lootTable)
        {
            HashSet<string> prefabs = new HashSet<string>();

            for (int i = lootTable.Prefabs.Count - 1; i >= 0; i--)
            {
                PrefabConfig prefab = lootTable.Prefabs[i];
                if (prefabs.Any(x => x == prefab.PrefabDefinition))
                {
                    lootTable.Prefabs.Remove(prefab);
                    PrintWarning($"Duplicate prefab removed from loot table! ({prefab.PrefabDefinition})");
                }
                else
                {
                    GameObject gameObject = GameManager.server.FindPrefab(prefab.PrefabDefinition);
                    global::HumanNPC humanNpc = gameObject.GetComponent<global::HumanNPC>();
                    ScarecrowNPC scarecrowNpc = gameObject.GetComponent<ScarecrowNPC>();
                    LootContainer lootContainer = gameObject.GetComponent<LootContainer>();
                    if (humanNpc != null && humanNpc.LootSpawnSlots.Length != 0)
                    {
                        if (!AllLootSpawnSlots.ContainsKey(prefab.PrefabDefinition)) AllLootSpawnSlots.Add(prefab.PrefabDefinition, humanNpc.LootSpawnSlots);
                        prefabs.Add(prefab.PrefabDefinition);
                    }
                    else if (scarecrowNpc != null && scarecrowNpc.LootSpawnSlots.Length != 0)
                    {
                        if (!AllLootSpawnSlots.ContainsKey(prefab.PrefabDefinition)) AllLootSpawnSlots.Add(prefab.PrefabDefinition, scarecrowNpc.LootSpawnSlots);
                        prefabs.Add(prefab.PrefabDefinition);
                    }
                    else if (lootContainer != null && lootContainer.LootSpawnSlots.Length != 0)
                    {
                        if (!AllLootSpawnSlots.ContainsKey(prefab.PrefabDefinition)) AllLootSpawnSlots.Add(prefab.PrefabDefinition, lootContainer.LootSpawnSlots);
                        prefabs.Add(prefab.PrefabDefinition);
                    }
                    else if (lootContainer != null && lootContainer.lootDefinition != null)
                    {
                        if (!AllLootSpawn.ContainsKey(prefab.PrefabDefinition)) AllLootSpawn.Add(prefab.PrefabDefinition, lootContainer.lootDefinition);
                        prefabs.Add(prefab.PrefabDefinition);
                    }
                    else
                    {
                        lootTable.Prefabs.Remove(prefab);
                        PrintWarning($"Unknown prefab removed! ({prefab.PrefabDefinition})");
                    }
                }
            }

            prefabs = null;

            lootTable.Prefabs = lootTable.Prefabs.OrderByQuickSort(x => x.Chance);
            if (lootTable.Prefabs.Any(x => x.Chance >= 100f))
            {
                HashSet<PrefabConfig> newPrefabs = new HashSet<PrefabConfig>();

                for (int i = lootTable.Prefabs.Count - 1; i >= 0; i--)
                {
                    PrefabConfig prefabConfig = lootTable.Prefabs[i];
                    if (prefabConfig.Chance < 100f) break;
                    newPrefabs.Add(prefabConfig);
                    lootTable.Prefabs.Remove(prefabConfig);
                }

                int count = newPrefabs.Count;

                if (count > 0)
                {
                    foreach (PrefabConfig prefabConfig in lootTable.Prefabs) newPrefabs.Add(prefabConfig);
                    lootTable.Prefabs.Clear();
                    foreach (PrefabConfig prefabConfig in newPrefabs) lootTable.Prefabs.Add(prefabConfig);
                }

                newPrefabs = null;

                if (lootTable.Min < count) lootTable.Min = count;
                if (lootTable.Max < count) lootTable.Max = count;
            }

            if (lootTable.Min > lootTable.Max) lootTable.Min = lootTable.Max;
            if (lootTable.Prefabs.Count == 0) lootTable.UseCount = false;
        }

        private Dictionary<string, LootSpawn> AllLootSpawn { get; } = new Dictionary<string, LootSpawn>();
        private Dictionary<string, LootContainer.LootSpawnSlot[]> AllLootSpawnSlots { get; } = new Dictionary<string, LootContainer.LootSpawnSlot[]>();
        #endregion Spawn Loot

        #region PveMode
        [PluginReference] private readonly Plugin PveMode;

        internal bool ActivePveMode => _config.PveMode.Pve && plugins.Exists("PveMode");

        private void SetOwnerPveMode(string shortname, BasePlayer player)
        {
            if (string.IsNullOrEmpty(shortname) || shortname != Name || !player.IsPlayer()) return;
            Controller.Owner = player;
            AlertToAllPlayers("SetOwner", _config.Chat.Prefix, player.displayName);
        }

        private void ClearOwnerPveMode(string shortname)
        {
            if (string.IsNullOrEmpty(shortname) || shortname != Name) return;
            Controller.Owner = null;
        }
        #endregion PveMode

        #region TruePVE
        private object CanEntityTakeDamage(BasePlayer victim, HitInfo hitinfo)
        {
            if (!_config.IsCreateZonePvp || victim == null || hitinfo == null || Controller == null) return null;
            BasePlayer attacker = hitinfo.InitiatorPlayer;
            if (Controller.Players.Contains(victim) && (attacker == null || Controller.Players.Contains(attacker))) return true;
            else return null;
        }
        #endregion TruePVE

        #region NTeleportation
        private object CanTeleport(BasePlayer player, Vector3 to)
        {
            if (_config.NTeleportationInterrupt && Controller != null && (Controller.Players.Contains(player) || Vector3.Distance(Controller.transform.position, to) < _config.Radius)) return GetMessage("NTeleportation", player.UserIDString, _config.Chat.Prefix);
            else return null;
        }

        private void OnPlayerTeleported(BasePlayer player, Vector3 oldPos, Vector3 newPos)
        {
            if (Controller == null) return;

            foreach (SecurityPoint point in Controller.SecurityPoints)
            {
                float distanceToPoint = Vector3.Distance(point.transform.position, newPos);
                if (distanceToPoint < 1f) point.EnterPlayer(player);
                if (distanceToPoint > 1f) point.ExitPlayer(player);
            }

            float distanceToController = Vector3.Distance(Controller.transform.position, newPos);
            if (distanceToController < _config.Radius) Controller.EnterPlayer(player);
            if (distanceToController > _config.Radius) Controller.ExitPlayer(player);
        }
        #endregion NTeleportation

        #region Economy
        [PluginReference] private readonly Plugin Economics, ServerRewards, IQEconomic, XPerience;

        private Dictionary<ulong, double> PlayersBalance { get; } = new Dictionary<ulong, double>();

        private void ActionEconomy(ulong playerId, string type, string arg = "")
        {
            AddBalance(playerId, type switch
            {
                "LockedCrate" => _config.Economy.LockedCrate,
                "KillNpc" => _config.Economy.KillNpc,
                "KillSecurityNpc" => _config.Economy.KillSecurityNpc,
                "KillWolf" => _config.Economy.KillWolf,
                "KillMotorbikeNpc" => _config.Economy.KillMotorbikeNpc,
                "UnlockHostage" => _config.Economy.UnlockHostage
            });
        }

        private void AddBalance(ulong playerId, double balance)
        {
            if (balance == 0) return;
            if (PlayersBalance.ContainsKey(playerId)) PlayersBalance[playerId] += balance;
            else PlayersBalance.Add(playerId, balance);
        }

        private void SendBalance()
        {
            if (PlayersBalance.Count == 0) return;
            if (_config.Economy.Plugins.Count > 0)
            {
                foreach (KeyValuePair<ulong, double> dic in PlayersBalance)
                {
                    if (dic.Value < _config.Economy.Min) continue;
                    int intCount = Convert.ToInt32(dic.Value);
                    if (_config.Economy.Plugins.Contains("Economics") && plugins.Exists("Economics") && dic.Value > 0) Economics.Call("Deposit", dic.Key.ToString(), dic.Value);
                    if (_config.Economy.Plugins.Contains("Server Rewards") && plugins.Exists("ServerRewards") && intCount > 0) ServerRewards.Call("AddPoints", dic.Key, intCount);
                    if (_config.Economy.Plugins.Contains("IQEconomic") && plugins.Exists("IQEconomic") && intCount > 0) IQEconomic.Call("API_SET_BALANCE", dic.Key, intCount);
                    BasePlayer player = BasePlayer.FindByID(dic.Key);
                    if (player != null)
                    {
                        if (_config.Economy.Plugins.Contains("XPerience") && plugins.Exists("XPerience") && dic.Value > 0) XPerience?.Call("GiveXP", player, dic.Value);
                        AlertToPlayer(player, GetMessage("SendEconomy", player.UserIDString, _config.Chat.Prefix, dic.Value));
                    }
                }
            }
            ulong winnerId = PlayersBalance.Max(x => x.Value).Key;
            Interface.Oxide.CallHook($"On{Name}Winner", winnerId);
            foreach (string command in _config.Economy.Commands) Server.Command(command.Replace("{steamid}", $"{winnerId}"));
            PlayersBalance.Clear();
        }
        #endregion Economy

        #region Alerts
        [PluginReference] private readonly Plugin GUIAnnouncements, DiscordMessages, Notify;

        private string ClearColorAndSize(string message)
        {
            message = message.Replace("</color>", string.Empty);
            message = message.Replace("</size>", string.Empty);
            while (message.Contains("<color="))
            {
                int index = message.IndexOf("<color=", StringComparison.Ordinal);
                message = message.Remove(index, message.IndexOf(">", index, StringComparison.Ordinal) - index + 1);
            }
            while (message.Contains("<size="))
            {
                int index = message.IndexOf("<size=", StringComparison.Ordinal);
                message = message.Remove(index, message.IndexOf(">", index, StringComparison.Ordinal) - index + 1);
            }
            if (!string.IsNullOrEmpty(_config.Chat.Prefix)) message = message.Replace(_config.Chat.Prefix + " ", string.Empty);
            return message;
        }

        private bool CanSendDiscordMessage => _config.Discord.IsDiscord && !string.IsNullOrEmpty(_config.Discord.WebhookUrl) && _config.Discord.WebhookUrl != "https://support.discordapp.com/hc/en-us/articles/228383668-Intro-to-Webhooks";

        private void AlertToAllPlayers(string langKey, params object[] args)
        {
            if (CanSendDiscordMessage && _config.Discord.Keys.Contains(langKey))
            {
                object fields = new[] { new { name = Title, value = ClearColorAndSize(GetMessage(langKey, null, args)), inline = false } };
                DiscordMessages?.Call("API_SendFancyMessage", _config.Discord.WebhookUrl, "", _config.Discord.EmbedColor, JsonConvert.SerializeObject(fields), null, this);
            }
            foreach (BasePlayer player in BasePlayer.activePlayerList)
            {
                if (player == null || !player.IsConnected) continue;
                if (_config.DistanceAlerts != 0f && Controller != null)
                {
                    if (player.transform == null || Controller.transform == null) continue;
                    if (Vector3.Distance(player.transform.position, Controller.transform.position) > _config.DistanceAlerts) continue;
                }
                AlertToPlayer(player, GetMessage(langKey, player.UserIDString, args));
            }
        }

        private void AlertToPlayer(BasePlayer player, string message)
        {
            if (_config.Chat.IsChat) PrintToChat(player, message);
            if (_config.GameTip.IsGameTip) player.SendConsoleCommand("gametip.showtoast", _config.GameTip.Style, ClearColorAndSize(message), string.Empty);
            if (_config.GuiAnnouncements.IsGuiAnnouncements) GUIAnnouncements?.Call("CreateAnnouncement", ClearColorAndSize(message), _config.GuiAnnouncements.BannerColor, _config.GuiAnnouncements.TextColor, player, _config.GuiAnnouncements.ApiAdjustVPosition);
            if (_config.Notify.IsNotify && plugins.Exists("Notify")) Notify?.Call("SendNotify", player, _config.Notify.Type, ClearColorAndSize(message));
        }
        #endregion Alerts

        #region GUI
        private HashSet<string> Names { get; } = new HashSet<string>
        {
            "Tab_KpucTaJl",
            "Clock_KpucTaJl",
            "Npc_KpucTaJl",
            "Hostage_KpucTaJl",
            "Crate_KpucTaJl"
        };
        private Dictionary<string, string> Images { get; } = new Dictionary<string, string>();

        private IEnumerator DownloadImages()
        {
            foreach (string name in Names)
            {
                string url = "file://" + Interface.Oxide.DataDirectory + Path.DirectorySeparatorChar + "Images" + Path.DirectorySeparatorChar + name + ".png";
                using (UnityWebRequest unityWebRequest = UnityWebRequestTexture.GetTexture(url))
                {
                    yield return unityWebRequest.SendWebRequest();
                    if (unityWebRequest.result != UnityWebRequest.Result.Success)
                    {
                        PrintError($"Image {name} was not found. Maybe you didn't upload it to the .../oxide/data/Images/ folder");
                        break;
                    }
                    else
                    {
                        Texture2D tex = DownloadHandlerTexture.GetContent(unityWebRequest);
                        Images.Add(name, FileStorage.server.Store(tex.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID).ToString());
                        Puts($"Image {name} download is complete");
                        UnityEngine.Object.DestroyImmediate(tex);
                    }
                }
            }
            if (Images.Count < Names.Count) Interface.Oxide.UnloadPlugin(Name);
        }

        private void CreateTabs(BasePlayer player, Dictionary<string, string> tabs)
        {
            CuiHelper.DestroyUi(player, "Tabs_KpucTaJl");

            CuiElementContainer container = new CuiElementContainer();

            float border = 52.5f + 54.5f * (tabs.Count - 1);
            container.Add(new CuiPanel
            {
                Image = { Color = "0 0 0 0" },
                RectTransform = { AnchorMin = "0.5 1", AnchorMax = "0.5 1", OffsetMin = $"{-border} {_config.Gui.TabsOffsetMinY}", OffsetMax = $"{border} {_config.Gui.TabsOffsetMinY + 20}" },
                CursorEnabled = false,
            }, "Under", "Tabs_KpucTaJl");

            int i = 0;

            foreach (KeyValuePair<string, string> dic in tabs)
            {
                i++;
                float xmin = 109f * (i - 1);
                container.Add(new CuiElement
                {
                    Name = $"Tab_{i}_KpucTaJl",
                    Parent = "Tabs_KpucTaJl",
                    Components =
                    {
                        new CuiRawImageComponent { Png = Images["Tab_KpucTaJl"] },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = $"{xmin} 0", OffsetMax = $"{xmin + 105f} 20" }
                    }
                });
                container.Add(new CuiElement
                {
                    Parent = $"Tab_{i}_KpucTaJl",
                    Components =
                    {
                        new CuiRawImageComponent { Png = Images[dic.Key] },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = "9 3", OffsetMax = "23 17" }
                    }
                });
                container.Add(new CuiElement
                {
                    Parent = $"Tab_{i}_KpucTaJl",
                    Components =
                    {
                        new CuiTextComponent() { Color = "1 1 1 1", Text = dic.Value, Align = TextAnchor.MiddleCenter, FontSize = 10, Font = "robotocondensed-bold.ttf" },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = "28 0", OffsetMax = "100 20" }
                    }
                });
            }

            CuiHelper.AddUi(player, container);
        }
        #endregion GUI

        #region Helpers
        [PluginReference] private readonly Plugin NpcSpawn, AnimalSpawn, BetterNpc, MonumentOwner;

        private HashSet<string> HooksInsidePlugin { get; } = new HashSet<string>
        {
            "OnEntityTakeDamage",
            "OnPlayerConnected",
            "OnPlayerDeath",
            "OnEntityDeath",
            "CanBuild",
            "CanChangeGrade",
            "OnStructureRotate",
            "OnNpcTarget",
            "CanHelicopterTarget",
            "CanHelicopterStrafeTarget",
            "OnHelicopterTarget",
            "OnCargoPlaneSignaled",
            "OnSupplyDropDropped",
            "CanDismountEntity",
            "OnCustomNpcGuardTargetEnd",
            "OnEntityKill",
            "OnLootEntity",
            "OnPlayerCommand",
            "OnServerCommand",
            "OnCorpsePopulate",
            "OnCustomLootNPC",
            "CanPopulateLoot",
            "OnCustomLootContainer",
            "OnContainerPopulate",
            "SetOwnerPveMode",
            "ClearOwnerPveMode",
            "CanEntityTakeDamage",
            "CanTeleport",
            "OnPlayerTeleported"
        };

        private void ToggleHooks(bool subscribe)
        {
            foreach (string hook in HooksInsidePlugin)
            {
                if (subscribe) Subscribe(hook);
                else Unsubscribe(hook);
            }
        }

        private const string StrSec = En ? "sec." : "сек.";
        private const string StrMin = En ? "min." : "мин.";
        private const string StrH = En ? "h." : "ч.";

        private static string GetTimeFormat(int time)
        {
            if (time <= 60) return $"{time} {StrSec}";
            else if (time <= 3600)
            {
                int sec = time % 60;
                int min = (time - sec) / 60;
                return sec == 0 ? $"{min} {StrMin}" : $"{min} {StrMin} {sec} {StrSec}";
            }
            else
            {
                int minSec = time % 3600;
                int hour = (time - minSec) / 3600;
                int sec = minSec % 60;
                int min = (minSec - sec) / 60;
                if (min == 0 && sec == 0) return $"{hour} {StrH}";
                else if (sec == 0) return $"{hour} {StrH} {min} {StrMin}";
                else return $"{hour} {StrH} {min} {StrMin} {sec} {StrSec}";
            }
        }

        private static void UpdateMarkerForPlayer(BasePlayer player, Vector3 pos, PointConfig config)
        {
            if (player == null || player.IsSleeping()) return;
            bool isAdmin = player.IsAdmin;
            if (!isAdmin)
            {
                player.SetPlayerFlag(BasePlayer.PlayerFlags.IsAdmin, true);
                player.SendNetworkUpdateImmediate();
            }
            try
            {
                player.SendConsoleCommand("ddraw.text", 1f, Color.white, pos, $"<size={config.Size}><color={config.Color}>{config.Text}</color></size>");
            }
            finally
            {
                if (!isAdmin)
                {
                    player.SetPlayerFlag(BasePlayer.PlayerFlags.IsAdmin, false);
                    player.SendNetworkUpdateImmediate();
                }
            }
        }

        private static void CopySerializableFields<T>(T src, T dst)
        {
            FieldInfo[] srcFields = typeof(T).GetFields(BindingFlags.Public | BindingFlags.Instance);
            foreach (FieldInfo field in srcFields)
            {
                object value = field.GetValue(src);
                field.SetValue(dst, value);
            }
        }

        

        private bool PluginExistsForStart(string pluginName)
        {
            if (plugins.Exists(pluginName)) return true;
            PrintError($"{pluginName} plugin doesn`t exist! (https://drive.google.com/drive/folders/1-18L-mG7yiGxR-PQYvd11VvXC2RQ4ZCu?usp=sharing)");
            Interface.Oxide.UnloadPlugin(Name);
            return false;
        }
        #endregion Helpers

        #region Commands
        [ChatCommand("ftstart")]
        private void ChatStartEvent(BasePlayer player)
        {
            if (player.IsAdmin)
            {
                if (!Active) Start(null);
                else PrintToChat(player, GetMessage("EventActive", player.UserIDString, _config.Chat.Prefix));
            }
        }

        [ChatCommand("ftstop")]
        private void ChatStopEvent(BasePlayer player)
        {
            if (player.IsAdmin)
            {
                if (Controller != null) Finish();
                else Interface.Oxide.ReloadPlugin(Name);
            }
        }

        [ChatCommand("ftpos")]
        private void ChatCommandPos(BasePlayer player)
        {
            if (!player.IsAdmin || Controller == null) return;
            Vector3 pos = Controller.transform.InverseTransformPoint(player.transform.position);
            Puts($"Position: {pos}");
            PrintToChat(player, $"Position: {pos}");
        }

        [ConsoleCommand("ftstart")]
        private void ConsoleStartEvent(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null) return;
            if (!Active)
            {
                if (arg.Args == null || arg.Args.Length != 1)
                {
                    Start(null);
                    return;
                }
                ulong steamId = Convert.ToUInt64(arg.Args[0]);
                BasePlayer target = BasePlayer.FindByID(steamId);
                if (target == null)
                {
                    Start(null);
                    Puts($"Player with SteamID {steamId} not found!");
                    return;
                }
                Start(target);
            }
            else Puts("This event is active now. To finish this event (ftstop), then to start the next one");
        }

        [ConsoleCommand("ftstop")]
        private void ConsoleStopEvent(ConsoleSystem.Arg arg)
        {
            if (arg.Player() == null)
            {
                if (Controller != null) Finish();
                else Interface.Oxide.ReloadPlugin(Name);
            }
        }
        #endregion Commands
    }
}

namespace Oxide.Plugins.FerryTerminalEventExtensionMethods
{
    public static class ExtensionMethods
    {
        public static bool Any<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) return true;
            return false;
        }

        public static HashSet<TSource> Where<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            HashSet<TSource> result = new HashSet<TSource>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) result.Add(enumerator.Current);
            return result;
        }

        public static TSource FirstOrDefault<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) return enumerator.Current;
            return default(TSource);
        }

        public static TSource Min<TSource>(this IEnumerable<TSource> source, Func<TSource, float> predicate)
        {
            TSource result = default(TSource);
            float resultValue = float.MaxValue;
            using (var enumerator = source.GetEnumerator())
            {
                while (enumerator.MoveNext())
                {
                    TSource element = enumerator.Current;
                    float elementValue = predicate(element);
                    if (elementValue < resultValue)
                    {
                        result = element;
                        resultValue = elementValue;
                    }
                }
            }
            return result;
        }

        public static TSource Max<TSource>(this IEnumerable<TSource> source, Func<TSource, double> predicate)
        {
            TSource result = default(TSource);
            double resultValue = double.MinValue;
            using (var enumerator = source.GetEnumerator())
            {
                while (enumerator.MoveNext())
                {
                    TSource element = enumerator.Current;
                    double elementValue = predicate(element);
                    if (elementValue > resultValue)
                    {
                        result = element;
                        resultValue = elementValue;
                    }
                }
            }
            return result;
        }

        public static HashSet<TResult> Select<TSource, TResult>(this IEnumerable<TSource> source, Func<TSource, TResult> predicate)
        {
            HashSet<TResult> result = new HashSet<TResult>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(predicate(enumerator.Current));
            return result;
        }

        public static HashSet<TSource> ToHashSet<TSource>(this IEnumerable<TSource> source)
        {
            HashSet<TSource> result = new HashSet<TSource>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(enumerator.Current);
            return result;
        }

        private static void Replace<TSource>(this IList<TSource> source, int x, int y)
        {
            TSource t = source[x];
            source[x] = source[y];
            source[y] = t;
        }

        private static List<TSource> QuickSort<TSource>(this List<TSource> source, Func<TSource, float> predicate, int minIndex, int maxIndex)
        {
            if (minIndex >= maxIndex) return source;

            int pivotIndex = minIndex - 1;
            for (int i = minIndex; i < maxIndex; i++)
            {
                if (predicate(source[i]) < predicate(source[maxIndex]))
                {
                    pivotIndex++;
                    source.Replace(pivotIndex, i);
                }
            }
            pivotIndex++;
            source.Replace(pivotIndex, maxIndex);

            QuickSort(source, predicate, minIndex, pivotIndex - 1);
            QuickSort(source, predicate, pivotIndex + 1, maxIndex);

            return source;
        }

        public static List<TSource> OrderByQuickSort<TSource>(this List<TSource> source, Func<TSource, float> predicate) => source.QuickSort(predicate, 0, source.Count - 1);

        public static bool IsPlayer(this BasePlayer player) => player != null && player.userID.IsSteamId();

        public static bool IsExists(this BaseNetworkable entity) => entity != null && !entity.IsDestroyed;

        public static void ClearItemsContainer(this ItemContainer container)
        {
            for (int i = container.itemList.Count - 1; i >= 0; i--)
            {
                Item item = container.itemList[i];
                item.RemoveFromContainer();
                item.Remove();
            }
        }

        public static void KillMapMarker(this HackableLockedCrate crate)
        {
            if (!crate.mapMarkerInstance.IsExists()) return;
            crate.mapMarkerInstance.Kill();
            crate.mapMarkerInstance = null;
        }

        public static Action GetPrivateAction(this object obj, string methodName)
        {
            MethodInfo mi = obj.GetType().GetMethod(methodName, BindingFlags.Public | BindingFlags.NonPublic | BindingFlags.Instance);
            if (mi != null) return (Action)Delegate.CreateDelegate(typeof(Action), obj, mi);
            else return null;
        }

        public static FieldInfo GetPrivateFieldInfo(Type type, string fieldName)
        {
            foreach (FieldInfo fi in type.GetFields(BindingFlags.Public | BindingFlags.NonPublic | BindingFlags.Instance)) if (fi.Name == fieldName) return fi;
            return null;
        }

        public static void SetPrivateFieldValue(this object obj, string fieldName, object value)
        {
            FieldInfo info = GetPrivateFieldInfo(obj.GetType(), fieldName);
            if (info != null) info.SetValue(obj, value);
        }
    }
}
