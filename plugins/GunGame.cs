using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;

namespace Oxide.Plugins;

[Info("GunGame", "CASHR#6906", "2.3.0")]
internal class GunGame : RustPlugin
{
    [PluginReference] private Plugin CopyPaste;
    [PluginReference] private Plugin Spawns;
    
    private Configuration _config;
    private static GunGame _;
    private GameController _gameController;
    private Dictionary<BasePlayer, DateTime> PlayerToIgnoreDamage = new();
    private Dictionary<BasePlayer, VoteBanSettings> VoteBanList = new();
    private Dictionary<BasePlayer, BasePlayer> LastAttacker = new(); // Хранит последнего атакующего для каждого игрока
    private WaitingArea _waitingArea = null;
    private Timer _enforcerTimer = null;

    private class VoteBanSettings
    {
        public int VoteCount { get; set; } = 0;
        public DateTime TimeToUnban { get; set; } = DateTime.MinValue;
        public List<string> UserToSendVote { get; set; } = new List<string>();
    }
    
    private class WaitingArea
    {
        public Vector3 Pos;
        public Quaternion Rot;
        public List<BaseEntity> Entities = new List<BaseEntity>();
        public Vector3? Spawn;
        public Bounds? Bounds;
        public Vector3 Size;
        public Vector3 Center;
        public BaseEntity Pookie;
    }
    
    #region Config

    private class Configuration
    {
        [JsonProperty(PropertyName = "Количество голосов для бана", ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int BanAmount = 4;

        [JsonProperty(PropertyName = "На сколько банить в секунда",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int TimeToBan = 600;
        
        [JsonProperty(PropertyName = "Сколько игнорировать урон после спавна",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int TimeToSave = 2;
        
        [JsonProperty(PropertyName = "Через сколько запускать новую игру?",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int TimeToRestart = 30;

        [JsonProperty(PropertyName = "Сколько хп давать за убийство",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int HealAmount = 20;
        
        [JsonProperty(PropertyName = "Показывать уведомления о событиях",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public bool ShowNotifications = true;
        
        [JsonProperty(PropertyName = "Зона ожидания: название схемы CopyPaste")]
        public string WaitingAreaCopyPasteName = "GG_Lobby";
        
        [JsonProperty(PropertyName = "Зона ожидания: принудительный телепорт каждые N секунд")]
        public float WaitingAreaEnforcePeriod = 3f;
        
        [JsonProperty(PropertyName = "Зона ожидания: показывать сообщение при телепорте")]
        public bool WaitingAreaShowMessage = true;
        
        [JsonProperty(PropertyName = "Точки спавна: название файла из Spawns плагина")]
        public string SpawnsFileName = "GunGameGorod";
        
        [JsonProperty(PropertyName = "Точки спавна: использовать случайный спавн")]
        public bool UseRandomSpawn = true;
        
        [JsonProperty(PropertyName = "Точки спавна: телепортировать при смерти")]
        public bool TeleportOnDeath = true;
        
        [JsonProperty(PropertyName = "Автоматический респавн после смерти (секунды)")]
        public float AutoRespawnDelay = 1f;
        
        [JsonProperty(PropertyName = "Список оружие(в каком порядке их получают игроки)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public readonly List<WeaponSettings> WeaponList = new List<WeaponSettings>()
        {
            // Уровень 1 - AK47 с модами (самое мощное)
            new WeaponSettings() 
            { 
                IndexWeapon = 0, 
                NeedKills = 2, 
                WeaponShortName = "rifle.ak", 
                DisplayName = "AK-47",
                ModuleList = new List<string>() { "weapon.mod.holosight", "weapon.mod.lasersight" },
                WearList = new List<string>() { "metal.plate.torso", "metal.facemask", "pants", "hoodie", "shoes.boots" }
            },
            
            // Уровень 2 - LR-300 с модами
            new WeaponSettings() 
            { 
                IndexWeapon = 1, 
                NeedKills = 2, 
                WeaponShortName = "rifle.lr300", 
                DisplayName = "LR-300",
                ModuleList = new List<string>() { "weapon.mod.holosight", "weapon.mod.lasersight" },
                WearList = new List<string>() { "metal.plate.torso", "metal.facemask", "pants", "hoodie" }
            },
            
            // Уровень 3 - M39 с прицелом
            new WeaponSettings() 
            { 
                IndexWeapon = 2, 
                NeedKills = 2, 
                WeaponShortName = "rifle.m39", 
                DisplayName = "M39 Rifle",
                ModuleList = new List<string>() { "weapon.mod.holosight" },
                WearList = new List<string>() { "metal.plate.torso", "pants", "hoodie" }
            },
            
            // Уровень 4 - MP5
            new WeaponSettings() 
            { 
                IndexWeapon = 3, 
                NeedKills = 2, 
                WeaponShortName = "smg.mp5", 
                DisplayName = "MP5",
                ModuleList = new List<string>() { "weapon.mod.holosight", "weapon.mod.lasersight" },
                WearList = new List<string>() { "roadsign.jacket", "roadsign.kilt", "hoodie" }
            },
            
            // Уровень 5 - Thompson
            new WeaponSettings() 
            { 
                IndexWeapon = 4, 
                NeedKills = 2, 
                WeaponShortName = "smg.thompson", 
                DisplayName = "Thompson",
                ModuleList = new List<string>() { "weapon.mod.simplesight", "weapon.mod.flashlight" },
                WearList = new List<string>() { "roadsign.jacket", "roadsign.kilt" }
            },
            
            // Уровень 6 - Semi-Auto Rifle
            new WeaponSettings() 
            { 
                IndexWeapon = 5, 
                NeedKills = 2, 
                WeaponShortName = "rifle.semiauto", 
                DisplayName = "Semi-Auto Rifle",
                ModuleList = new List<string>() { },
                WearList = new List<string>() { "jacket", "pants" }
            },
            
            // Уровень 7 - Custom SMG
            new WeaponSettings() 
            { 
                IndexWeapon = 6, 
                NeedKills = 2, 
                WeaponShortName = "smg.2", 
                DisplayName = "Custom SMG",
                ModuleList = new List<string>() { "weapon.mod.flashlight" },
                WearList = new List<string>() { "jacket", "pants" }
            },
            
            // Уровень 8 - Python Revolver
            new WeaponSettings() 
            { 
                IndexWeapon = 7, 
                NeedKills = 2, 
                WeaponShortName = "pistol.python", 
                DisplayName = "Python Revolver",
                ModuleList = new List<string>() { "weapon.mod.lasersight" },
                WearList = new List<string>() { "jacket", "pants" }
            },
            
            // Уровень 9 - Bolt Action Rifle (снайперка с прицелом 8x)
            new WeaponSettings() 
            { 
                IndexWeapon = 8, 
                NeedKills = 2, 
                WeaponShortName = "rifle.bolt", 
                DisplayName = "Bolt Rifle",
                ModuleList = new List<string>() { "weapon.mod.8x.scope" },
                WearList = new List<string>() { "hoodie", "pants" }
            },
            
            // Уровень 10 - M92 Pistol
            new WeaponSettings() 
            { 
                IndexWeapon = 9, 
                NeedKills = 2, 
                WeaponShortName = "pistol.m92", 
                DisplayName = "M92 Pistol",
                ModuleList = new List<string>() { "weapon.mod.flashlight" },
                WearList = new List<string>() { "hoodie" }
            },
            
            // Уровень 11 - Pump Shotgun
            new WeaponSettings() 
            { 
                IndexWeapon = 10, 
                NeedKills = 2, 
                WeaponShortName = "shotgun.pump", 
                DisplayName = "Pump Shotgun",
                ModuleList = new List<string>() { },
                WearList = new List<string>() { "tshirt", "pants" }
            },
            
            // Уровень 12 - Semi-Auto Pistol
            new WeaponSettings() 
            { 
                IndexWeapon = 11, 
                NeedKills = 2, 
                WeaponShortName = "pistol.semiauto", 
                DisplayName = "Semi Pistol",
                ModuleList = new List<string>() { },
                WearList = new List<string>() { "hoodie", "pants" }
            },
            
            // Уровень 13 - Revolver
            new WeaponSettings() 
            { 
                IndexWeapon = 12, 
                NeedKills = 2, 
                WeaponShortName = "pistol.revolver", 
                DisplayName = "Revolver",
                ModuleList = new List<string>() { },
                WearList = new List<string>() { "hoodie", "pants" }
            },
            
            // Уровень 14 - Crossbow
            new WeaponSettings() 
            { 
                IndexWeapon = 13, 
                NeedKills = 2, 
                WeaponShortName = "crossbow", 
                DisplayName = "Crossbow",
                ModuleList = new List<string>() { },
                WearList = new List<string>() { }
            },
            
            // Уровень 15 - Hunting Bow (ФИНАЛ!)
            new WeaponSettings() 
            { 
                IndexWeapon = 14, 
                NeedKills = 2, 
                WeaponShortName = "bow.hunting", 
                DisplayName = "Hunting Bow",
                ModuleList = new List<string>() { },
                WearList = new List<string>() { }
            }
        };

        internal class WeaponSettings
        {
            [JsonProperty("Index weapon")] 
            public int IndexWeapon { get; set; } = 0;
            
            [JsonProperty("Сколько убийств до нового уровня")]
            public int NeedKills { get; set; } = 5;
            
            [JsonProperty("Weapon shortName")] 
            public string WeaponShortName { get; set; } = "rifle.bolt";
            
            [JsonProperty("Отображаемое имя оружия")]
            public string DisplayName { get; set; } = "Weapon";

            [JsonProperty(PropertyName = "Список одежды который получит игрок на этом уровне",
                ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public List<string> WearList { get; set; } = new List<string>();
            
            [JsonProperty(PropertyName = "Список модулей для оружия",
                ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public List<string> ModuleList { get; set; } = new List<string>();
        }
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
            PrintError("Your configuration file contains an error. Using default configuration values.");
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

    #region OxideHooks

    private void OnServerInitialized()
    {
        _ = this;
        
        // Регистрация прав доступа
        permission.RegisterPermission("gungame.admin", this);
        
        _gameController = ServerMgr.Instance.gameObject.AddComponent<GameController>();
        
        // Инициализация зоны ожидания
        if (!TryBindExistingWaitingArea())
        {
            EnsureWaitingArea(always: true);
        }
        
        // Запуск принудительного телепорта не-участников
        StartWaitingAreaEnforcer();
        
        PrintError("|-----------------------------------|");
        PrintWarning($"|  Plugin {Title} v{Version} is loaded  |");
        PrintWarning("|          Discord: CASHR#6906      |");
        PrintError("|-----------------------------------|");
    }
    
    private object OnPlayerWound(BasePlayer player, HitInfo info)
    {
        // Если игрок в игре - предотвращаем ранение и сразу убиваем
        if (player != null && _gameController != null && _gameController.IsPlayerInGame(player))
        {
            // Сохраняем информацию об атакующем перед принудительной смертью
            if (info != null && info.InitiatorPlayer != null)
            {
                LastAttacker[player] = info.InitiatorPlayer;
            }
            
            // Предотвращаем ранение
            NextTick(() =>
            {
                if (player != null && !player.IsDead())
                {
                    player.Die();
                }
            });
            return true; // Блокируем стандартное ранение
        }
        
        return null; // Для остальных игроков - стандартная логика
    }
   
    private object OnEntityTakeDamage(BasePlayer player, HitInfo info)
    {
        var attacker = info?.InitiatorPlayer;
        
        // Проверка защиты жертвы
        if (PlayerToIgnoreDamage.ContainsKey(player))
        {
            if (PlayerToIgnoreDamage[player] < DateTime.Now)
            {
                PlayerToIgnoreDamage.Remove(player);
            }
            else
            {
            if (attacker != null)
            {
                    var timeLeft = (PlayerToIgnoreDamage[player] - DateTime.Now).TotalSeconds;
                    attacker.ChatMessage($"<color=#ff6b6b>⚠</color> Игрок защищен еще {timeLeft:F1} сек");
            }
            return false;
            }
        }

        // Проверка защиты атакующего
        if (attacker != null && PlayerToIgnoreDamage.ContainsKey(attacker))
            {
                if (PlayerToIgnoreDamage[attacker] < DateTime.Now)
                {
                    PlayerToIgnoreDamage.Remove(attacker);
                }
            else
                {
                var timeLeft = (PlayerToIgnoreDamage[attacker] - DateTime.Now).TotalSeconds;
                attacker.ChatMessage($"<color=#ff6b6b>⚠</color> Вы не можете атаковать еще {timeLeft:F1} сек");
                return false;
            }
        }
        
        return null;
    }
    
    private void OnPlayerConnected(BasePlayer player)
    {
        if (VoteBanList.TryGetValue(player, out var userBans))
        {
            if (userBans.TimeToUnban > DateTime.Now)
            {
                var timeLeft = (userBans.TimeToUnban - DateTime.Now).TotalSeconds;
                player.Kick($"Вы сможете подключиться через {timeLeft:F0} секунд");
                return;
            }
            else
            {
                VoteBanList.Remove(player);
                SendUserMessage($"<color=#4ecdc4>ℹ</color> Игрок <color=#ffd93d>{player.displayName}</color> вернулся после бана");
            }
        }
        
        // Если игра не идет - пытаемся добавить игрока
        // Если игра идет - телепортируем в зону ожидания
        if (_gameController != null && _gameController.IsGameActive())
        {
            // Игра идет - отправляем в зону ожидания
            NextTick(() => TeleportToWaitingArea(player));
        }
        else
        {
            // Игра не идет - можем добавить
            _gameController?.AddPlayer(player);
        }
    }

    private void OnPlayerDisconnected(BasePlayer player, string reason)
    {
        // Очистка UI
        CuiHelper.DestroyUi(player, "GunGame_Main");
        CuiHelper.DestroyUi(player, "GunGame_Notification");
        
        // Отмена голосований за этого игрока
        if (VoteBanList.ContainsKey(player))
        {
            VoteBanList[player].UserToSendVote.Clear();
            VoteBanList[player].VoteCount = 0;
        }
        
        // Очистка последнего атакующего
        if (LastAttacker.ContainsKey(player))
        {
            LastAttacker.Remove(player);
        }
        
        // Удаление из игры
        _gameController?.RemovePlayer(player);
    }

    private void OnPlayerDeath(BasePlayer player, HitInfo info)
    {
        if (player == null || !player.IsConnected) return;
        
        // Проверяем, участвует ли игрок в игре
        if (_gameController == null || !_gameController.IsPlayerInGame(player))
            return;
        
        // Обработка убийства - проверяем info или сохраненного атакующего
        BasePlayer attacker = null;
        
        // Сначала пытаемся получить атакующего из HitInfo
        if (info != null && info.InitiatorPlayer != null)
        {
            attacker = info.InitiatorPlayer;
        }
        // Если HitInfo пустой, проверяем сохраненного атакующего (для случая с player.Die())
        else if (LastAttacker.ContainsKey(player))
        {
            attacker = LastAttacker[player];
            LastAttacker.Remove(player); // Удаляем после использования
        }
        
        // Засчитываем убийство
        if (attacker != null && attacker.userID != player.userID)
        {
            _gameController.GiveKill(attacker, player);
        attacker.Heal(_config.HealAmount);
        }
        
        // Автоматический респавн игрока через указанное время
        float respawnDelay = Mathf.Max(0.5f, _config.AutoRespawnDelay);
        
        // Показываем уведомление о респавне
        if (respawnDelay > 0.5f)
        {
            ShowNotification(player, $"⏳ Респавн через {respawnDelay:F1} сек...", "#ffa502", respawnDelay);
        }
        
        timer.Once(respawnDelay, () =>
        {
            if (player == null || !player.IsConnected) return;
            if (_gameController == null || !_gameController.IsPlayerInGame(player)) return;
            
            // Респавним игрока автоматически
            player.Respawn();
            
            // Разбудим игрока сразу после респавна
            NextTick(() =>
            {
                if (player != null && player.IsConnected && player.IsSleeping())
                {
                    player.EndSleeping();
                }
            });
        });
    }
    
    private void OnPlayerInit(BasePlayer player)
    {
        // При инициализации игрока проверяем, нужно ли его в зону ожидания
        if (player != null && _gameController != null && !_gameController.IsPlayerInGame(player))
        {
            NextTick(() => TeleportToWaitingArea(player));
        }
    }
    
    private void OnPlayerSleepEnded(BasePlayer player)
    {
        // Когда игрок просыпается, проверяем его статус
        if (player != null && _gameController != null && !_gameController.IsPlayerInGame(player))
        {
            NextTick(() => TeleportToWaitingArea(player));
        }
    }
    
    private void OnPlayerRespawned(BasePlayer player)
    {
        // Разбудим игрока сразу после респавна
        NextTick(() =>
        {
            if (player != null && player.IsConnected && player.IsSleeping())
            {
                player.EndSleeping();
            }
        });
        
        // Если игрок участвует в игре - даем оружие и защиту
        if (_gameController != null && _gameController.IsPlayerInGame(player))
        {
            // Телепортируем на случайный спавн при смерти
            if (_config.TeleportOnDeath)
            {
                NextTick(() => TeleportToSpawn(player));
            }
            
            if (!PlayerToIgnoreDamage.ContainsKey(player))
            PlayerToIgnoreDamage.Add(player, DateTime.MinValue);

        player.metabolism.calories.value = player.metabolism.calories.max;
        player.metabolism.hydration.value = player.metabolism.hydration.max;
        PlayerToIgnoreDamage[player] = DateTime.Now.AddSeconds(_config.TimeToSave);
            
        _gameController.GiveWeaponAndWear(player);
        
            // Показываем уведомление о защите
            ShowNotification(player, $"🛡 Защита на {_config.TimeToSave} сек", "#4ecdc4", 3f);
        }
        else
        {
            // Не участвует - отправляем в зону ожидания
            NextTick(() => TeleportToWaitingArea(player));
        }
    }
    
    private void OnEntitySpawned(PlayerCorpse entity)
    {
        entity.Kill();
    }
    
    private void OnEntitySpawned(DroppedItemContainer entity)
    {
        entity.Kill();
    }
    
    private object CanDropActiveItem(BasePlayer instance)
    {
        return false;
    }
    
    private void Unload()
    {
        // Очистка UI для всех игроков
        foreach (var player in BasePlayer.activePlayerList)
        {
            CuiHelper.DestroyUi(player, "GunGame_Main");
            CuiHelper.DestroyUi(player, "GunGame_Notification");
            CuiHelper.DestroyUi(player, "GunGame_Countdown");
        }
        
        // Остановка таймера enforcer'а
        _enforcerTimer?.Destroy();
        _enforcerTimer = null;
        
        // Очистка словарей
        PlayerToIgnoreDamage?.Clear();
        VoteBanList?.Clear();
        LastAttacker?.Clear();
        
        // Уничтожение контроллера
        if (_gameController != null)
        UnityEngine.Object.Destroy(_gameController);
        
        _ = null;
    }

    #endregion

    #region Functions
    
    [ChatCommand("gg")]
    private void CmdGunGame(BasePlayer player, string command, string[] args)
    {
        // Проверка прав администратора
        if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "gungame.admin"))
        {
            player.ChatMessage("<color=#ff6b6b>❌</color> У вас нет прав для использования этой команды");
            return;
        }

        if (args.Length < 1)
        {
            player.ChatMessage("<color=#4ecdc4>━━━━━━━ GUN GAME ADMIN ━━━━━━━</color>");
            player.ChatMessage("<color=#ffd93d>/gg start [секунды]</color> - Запустить игру (с обратным отсчетом)");
            player.ChatMessage("<color=#ffd93d>/gg stop</color> - Остановить текущую игру");
            player.ChatMessage("<color=#ffd93d>/gg restart</color> - Перезапустить игру");
            player.ChatMessage("<color=#ffd93d>/gg status</color> - Статус игры");
            player.ChatMessage("<color=#ffd93d>/gg autostart <on/off></color> - Авто-рестарт");
            player.ChatMessage("<color=#6BCF7F>Примеры:</color>");
            player.ChatMessage("  /gg start - запустить сразу");
            player.ChatMessage("  /gg start 30 - запустить через 30 сек");
            player.ChatMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━━━━</color>");
            return;
        }

        switch (args[0].ToLower())
        {
            case "start":
                if (_gameController.IsGameActive())
                {
                    player.ChatMessage("<color=#ffa502>⚠</color> Игра уже идет!");
                    return;
                }
                
                // Проверяем есть ли параметр с задержкой
                if (args.Length > 1 && int.TryParse(args[1], out int delaySeconds))
                {
                    if (delaySeconds < 1 || delaySeconds > 300)
                    {
                        player.ChatMessage("<color=#ff6b6b>❌</color> Задержка должна быть от 1 до 300 секунд");
                        return;
                    }
                    
                    _gameController.StartGameWithCountdown(delaySeconds);
                    SendUserMessage($"<color=#4ecdc4>🎮</color> Администратор <color=#ffd93d>{player.displayName}</color> запустил игру. Начало через <color=#ffd93d>{delaySeconds}</color> сек!");
            }
            else
            {
                    _gameController.StartGameManual();
                    SendUserMessage($"<color=#6BCF7F>🎮</color> Администратор <color=#ffd93d>{player.displayName}</color> запустил новую игру!");
                }
                break;

            case "stop":
                if (!_gameController.IsGameActive())
                {
                    player.ChatMessage("<color=#ffa502>⚠</color> Игра уже остановлена!");
            return;
        }
                _gameController.StopGame();
                SendUserMessage($"<color=#ff6b6b>⏸</color> Администратор <color=#ffd93d>{player.displayName}</color> остановил игру");
                break;

            case "restart":
                _gameController.RestartGame();
                SendUserMessage($"<color=#4ecdc4>🔄</color> Администратор <color=#ffd93d>{player.displayName}</color> перезапустил игру!");
                break;

            case "status":
                var status = _gameController.GetGameStatus();
                player.ChatMessage("<color=#4ecdc4>━━━━━━━ СТАТУС ИГРЫ ━━━━━━━</color>");
                player.ChatMessage($"<color=#ffd93d>Статус:</color> {status.IsActive}");
                player.ChatMessage($"<color=#ffd93d>Игроков:</color> {status.PlayerCount}");
                player.ChatMessage($"<color=#ffd93d>Лидер:</color> {status.Leader}");
                player.ChatMessage($"<color=#ffd93d>Авто-рестарт:</color> {status.AutoRestart}");
                player.ChatMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━</color>");
                break;

            case "autostart":
                if (args.Length < 2)
                {
                    player.ChatMessage("<color=#ff6b6b>❌</color> Использование: /gg autostart <on/off>");
                    return;
                }
                
                bool enable = args[1].ToLower() == "on" || args[1].ToLower() == "true" || args[1].ToLower() == "1";
                _gameController.SetAutoRestart(enable);
                
                if (enable)
                {
                    player.ChatMessage("<color=#6BCF7F>✓</color> Авто-рестарт <color=#6BCF7F>включен</color>");
                    SendUserMessage($"<color=#4ecdc4>ℹ</color> Администратор включил автоматический рестарт игр");
            }
            else
            {
                    player.ChatMessage("<color=#ff6b6b>✓</color> Авто-рестарт <color=#ff6b6b>отключен</color>");
                    SendUserMessage($"<color=#4ecdc4>ℹ</color> Администратор отключил автоматический рестарт игр");
                }
                break;

            default:
                player.ChatMessage("<color=#ff6b6b>❌</color> Неизвестная команда. Используйте <color=#ffd93d>/gg</color> для справки");
                break;
        }
    }
    
    [ChatCommand("voteban")]
    private void CmdChatvoteban(BasePlayer player, string command, string[] args)
    {
        if (args.Length < 1)
        {
            player.ChatMessage("<color=#ff6b6b>❌</color> Использование: /voteban <NickName>");
            return;
        }
        
        var foundPlayers = FindPlayers(args[0]);
        
        if (foundPlayers.Count == 0)
        {
            player.ChatMessage("<color=#ff6b6b>❌</color> Игрок не найден");
            return;
        }
        
        if (foundPlayers.Count > 1)
        {
            player.ChatMessage($"<color=#ff6b6b>❌</color> Найдено несколько игроков ({foundPlayers.Count}). Уточните запрос");
            return;
        }
        
        var targetPlayer = foundPlayers[0];
        
        if (targetPlayer.userID == player.userID)
        {
            player.ChatMessage("<color=#ff6b6b>❌</color> Вы не можете голосовать против себя");
            return;
        }
        
        if (!VoteBanList.ContainsKey(targetPlayer))
            VoteBanList.Add(targetPlayer, new VoteBanSettings());

        if (VoteBanList[targetPlayer].UserToSendVote.Contains(player.UserIDString))
        {
            player.ChatMessage("<color=#ffa502>⚠</color> Вы уже проголосовали против этого игрока");
            return;
        }
        
        VoteBanList[targetPlayer].VoteCount++;
        VoteBanList[targetPlayer].UserToSendVote.Add(player.UserIDString);
        
        if (VoteBanList[targetPlayer].VoteCount >= _config.BanAmount)
        {
            targetPlayer.Kick($"Игроки проголосовали за временный бан на {_config.TimeToBan} секунд");
            VoteBanList[targetPlayer].TimeToUnban = DateTime.Now.AddSeconds(_config.TimeToBan);

            SendUserMessage($"<color=#ff6b6b>🔨</color> Игрок <color=#ffd93d>{targetPlayer.displayName}</color> забанен голосованием");
        }
        else
        {
            SendUserMessage($"<color=#ffa502>📢</color> <color=#ffd93d>{player.displayName}</color> голосует за бан <color=#ff6b6b>{targetPlayer.displayName}</color> [{VoteBanList[targetPlayer].VoteCount}/{_config.BanAmount}]");
        }
    }
    
    private void SendUserMessage(string msg)
    {
        foreach (var check in BasePlayer.activePlayerList)
        {
            check.ChatMessage(msg);
        }
    }
    
    // Исправленный метод поиска игроков
    private List<BasePlayer> FindPlayers(string nameOrSteamID)
    {
        var foundPlayers = new List<BasePlayer>();
        
        foreach (var check in BasePlayer.activePlayerList)
        {
            if (check.displayName.ToLower().Contains(nameOrSteamID.ToLower()) ||
                check.UserIDString.Contains(nameOrSteamID))
            {
                foundPlayers.Add(check);
            }
        }
        
        return foundPlayers;
    }
    
    private void TeleportToSpawn(BasePlayer player)
    {
        if (player == null || !player.IsConnected) return;

        // Проверяем наличие плагина Spawns
        if (Spawns == null || !Spawns.IsLoaded)
        {
            Puts($"[GunGame] Spawns плагин не найден. Телепортация пропущена.");
            return;
        }

        // Получаем случайный спавн из файла
        object spawnResult = Spawns.Call("GetRandomSpawn", _config.SpawnsFileName);
        
        if (spawnResult == null || spawnResult is string)
        {
            PrintWarning($"[GunGame] Не удалось получить спавн из файла '{_config.SpawnsFileName}'. Создайте точки спавна: /spawns new");
            return;
        }

        Vector3 spawnPos = (Vector3)spawnResult;
        
        // Телепортируем игрока
        TeleportPlayer(player, spawnPos);
        
        // Восстанавливаем здоровье и метаболизм
        player.health = 100f;
        player.metabolism.bleeding.value = 0f;
        player.metabolism.radiation_level.value = 0f;
        player.metabolism.radiation_poison.value = 0f;
        player.metabolism.calories.value = player.metabolism.calories.max;
        player.metabolism.hydration.value = player.metabolism.hydration.max;
        player.SendNetworkUpdateImmediate();
    }
    
    private void ShowNotification(BasePlayer player, string message, string color, float duration = 3f)
    {
        if (!_config.ShowNotifications) return;
        
        var container = new CuiElementContainer();
        
        container.Add(new CuiPanel
        {
            CursorEnabled = false,
            Image = { Color = "0 0 0 0.8" },
            RectTransform = { AnchorMin = "0.5 0.85", AnchorMax = "0.5 0.85", OffsetMin = "-200 -25", OffsetMax = "200 25" }
        }, "Overlay", "GunGame_Notification");

        container.Add(new CuiElement
        {
            Name = "NotificationText",
            Parent = "GunGame_Notification",
            Components =
            {
                new CuiTextComponent
                {
                    Text = message,
                    Font = "robotocondensed-bold.ttf",
                    FontSize = 16,
                    Align = TextAnchor.MiddleCenter,
                    Color = "1 1 1 1"
                },
                new CuiRectTransformComponent
                {
                    AnchorMin = "0 0", AnchorMax = "1 1"
                }
            }
        });
        
        CuiHelper.DestroyUi(player, "GunGame_Notification");
        CuiHelper.AddUi(player, container);
        
        timer.Once(duration, () =>
        {
            if (player != null && player.IsConnected)
                CuiHelper.DestroyUi(player, "GunGame_Notification");
        });
    }

    #endregion

    #region GameController

    private class GameController : FacepunchBehaviour
    {
        private Dictionary<BasePlayer, PlayerStat> _players = new();
        private bool _gameActive = false;
        private bool _autoRestart = true;
        private Timer _countdownTimer;
        private int _countdownSeconds;

        private void Awake()
        {
            StartGame();
        }
        
        // Публичные методы управления игрой
        public bool IsGameActive() => _gameActive;
        
        public bool IsPlayerInGame(BasePlayer player) => _players.ContainsKey(player);
        
        public void SetAutoRestart(bool enable)
        {
            _autoRestart = enable;
        }
        
        public void StartGameManual()
        {
            // Отменяем таймер обратного отсчета если он есть
            _countdownTimer?.Destroy();
            
            // Скрываем UI отсчета
            foreach (var player in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(player, "GunGame_Countdown");
            }
            
            StartGame();
        }
        
        public void StartGameWithCountdown(int seconds)
        {
            // Отменяем предыдущий таймер если есть
            _countdownTimer?.Destroy();
            
            // Устанавливаем режим "подготовка к старту"
            _gameActive = false;
            _countdownSeconds = seconds;
            
            // Показываем UI подготовки
            UpdateStartCountdownUI();
            
            // Запускаем таймер
            _countdownTimer = _.timer.Repeat(1f, seconds, () =>
            {
                _countdownSeconds--;
                
                if (_countdownSeconds <= 0)
                {
                    // Скрываем UI отсчета
                    foreach (var player in BasePlayer.activePlayerList)
                    {
                        CuiHelper.DestroyUi(player, "GunGame_Countdown");
                    }
                    
                    // Запускаем игру
                    StartGame();
                }
                else
                {
                    UpdateStartCountdownUI();
                    
                    // Звуковые эффекты на последних 3 секундах
                    if (_countdownSeconds <= 3)
                    {
                        foreach (var player in BasePlayer.activePlayerList)
                        {
                            Effect.server.Run("assets/prefabs/deployable/research table/effects/research-success.prefab", player.transform.position);
                        }
                    }
                }
            });
        }
        
        public void StopGame()
        {
            _gameActive = false;
            
            // Отменяем таймер
            _countdownTimer?.Destroy();
            
            // Скрываем UI
            foreach (var player in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(player, "GunGame_Main");
                CuiHelper.DestroyUi(player, "GunGame_Countdown");
                CuiHelper.DestroyUi(player, "GunGame_Notification");
            }
            
            // Очищаем игроков
            _players.Clear();
        }
        
        public void RestartGame()
        {
            StopGame();
            _.timer.Once(1f, () => StartGameManual());
        }
        
        public GameStatus GetGameStatus()
        {
            var leader = "Нет";
            if (_players.Count > 0)
            {
                var topPlayer = _players.OrderByDescending(p => p.Value.WeaponIndex)
                    .ThenByDescending(p => p.Value.Kills)
                    .FirstOrDefault();
                    
                if (topPlayer.Key != null)
                {
                    var weaponSettings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == topPlayer.Value.WeaponIndex);
                    leader = $"{topPlayer.Key.displayName} (Ур. {topPlayer.Value.WeaponIndex + 1}, {topPlayer.Value.Kills}/{weaponSettings?.NeedKills ?? 0})";
                }
            }
            
            return new GameStatus
            {
                IsActive = _gameActive ? "<color=#6BCF7F>Активна</color>" : "<color=#ff6b6b>Остановлена</color>",
                PlayerCount = _players.Count.ToString(),
                Leader = leader,
                AutoRestart = _autoRestart ? "<color=#6BCF7F>Включен</color>" : "<color=#ff6b6b>Выключен</color>"
            };
        }

        public void AddPlayer(BasePlayer player)
        {
            if (_players.ContainsKey(player)) return;
            
            _players.Add(player, new PlayerStat()
            {
                Kills = 0,
                WeaponIndex = 0
            });
            
            // Телепортируем на спавн из Spawns плагина
            _.TeleportToSpawn(player);
            
            GiveWeaponAndWear(player);
            ShowTable();
        }
        
        public void GiveWeaponAndWear(BasePlayer player)
        {
            // Проверка на существование игрока в словаре
            if (!_players.ContainsKey(player))
            {
                _.Puts($"Warning: Player {player.displayName} not in players dictionary");
                return;
            }
            
            player.inventory.Strip();
            player.Heal(200);

            var settings = _players[player];
            var weaponSettings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == settings.WeaponIndex);
            
            if (weaponSettings == null)
            {
                EndGame(player);
                return;
            }

            var weapon = ItemManager.CreateByName(weaponSettings.WeaponShortName);
            if (weapon == null)
            {
                _.Puts($"Warning: Could not create weapon {weaponSettings.WeaponShortName}");
                return;
            }
            
            weapon.MoveToContainer(player.inventory.containerBelt);
            player.svActiveItemID = weapon.uid;
            player.SendNetworkUpdateImmediate();
           
            var projectile = weapon.GetHeldEntity() as BaseProjectile;
            if (projectile != null)
            {
                var ammo = ItemManager.CreateByName(projectile.primaryMagazine.ammoType.shortname, 600);
                if (ammo != null)
                player.GiveItem(ammo);
                    
                projectile.primaryMagazine.contents = projectile.primaryMagazine.capacity;
                projectile.SendNetworkUpdateImmediate();
            }

            foreach (var check in weaponSettings.ModuleList)
            {
                var item = ItemManager.CreateByName(check);
                if (item != null && weapon.contents != null)
                    item.MoveToContainer(weapon.contents);
            }
            
            foreach (var check in weaponSettings.WearList)
            {
                var wear = ItemManager.CreateByName(check);
                if (wear != null)
                wear.MoveToContainer(player.inventory.containerWear);
            }
            
            // Выдаем бинты и шприц в пояс (где оружие)
            var bandages = ItemManager.CreateByName("bandage", 5);
            if (bandages != null)
                bandages.MoveToContainer(player.inventory.containerBelt);
            
            var syringe = ItemManager.CreateByName("syringe.medical", 1);
            if (syringe != null)
                syringe.MoveToContainer(player.inventory.containerBelt);
            
            player.inventory.containerWear.SetLocked(true);
            player.inventory.containerBelt.SetLocked(true);
            player.inventory.containerMain.SetLocked(true);
        }
       
        public void EndGame(BasePlayer winner = null)
        {
            _gameActive = false;
            
            if (winner != null)
            {
                _.SendUserMessage($"<color=#ffd93d>👑 {winner.displayName}</color> <color=#6BCF7F>победил в игре!</color>");
                
                // Показываем большое уведомление победителю
                _.ShowNotification(winner, "🏆 ПОБЕДА! 🏆", "#ffd93d", 5f);
            }
            
            // Показываем топ-3 игроков
            ShowTop3Results();
            
            // Сохраняем список игроков перед очисткой
            var playersToTeleport = _players.Keys.ToList();
            
            // Очищаем список участников (важно сделать ДО телепортации, чтобы TeleportToWaitingArea сработал)
            _players.Clear();
            
            // Скрываем UI для всех игроков
            foreach (var player in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(player, "GunGame_Main");
                CuiHelper.DestroyUi(player, "GunGame_Countdown");
                CuiHelper.DestroyUi(player, "PlayerInfo");
            }
            
            // Телепортируем всех участников в зону ожидания (они будут голыми)
            _.timer.Once(1f, () =>
            {
                foreach (var player in playersToTeleport)
                {
                    if (player != null && player.IsConnected)
                    {
                        _.TeleportToWaitingArea(player);
                    }
                }
            });
            
            // Запускаем обратный отсчет только если авто-рестарт включен
            if (_autoRestart)
            {
                _.SendUserMessage($"<color=#4ecdc4>ℹ</color> Следующая игра через <color=#ffd93d>{_._config.TimeToRestart}</color> секунд");
                StartCountdown(_._config.TimeToRestart);
            }
            else
            {
                _.SendUserMessage($"<color=#4ecdc4>ℹ</color> Игра остановлена. Ожидание запуска администратором...");
            }
        }
        
        private void ShowTop3Results()
        {
            if (_players.Count == 0) return;
            
            // Получаем топ-3 игроков
            var topPlayers = _players
                .OrderByDescending(p => p.Value.WeaponIndex)
                .ThenByDescending(p => p.Value.Kills)
                .Take(3)
                .ToList();
            
            if (topPlayers.Count == 0) return;
            
            _.SendUserMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</color>");
            _.SendUserMessage("<color=#ffd93d>🏆 ИТОГИ ИГРЫ - ТОП 3 🏆</color>");
            _.SendUserMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</color>");
            
            for (int i = 0; i < topPlayers.Count; i++)
            {
                var playerEntry = topPlayers[i];
                var player = playerEntry.Key;
                var stats = playerEntry.Value;
                
                string medal = "";
                string color = "";
                
                switch (i)
                {
                    case 0:
                        medal = "👑";
                        color = "#ffd93d"; // Золото
                        break;
                    case 1:
                        medal = "🥈";
                        color = "#c0c0c0"; // Серебро
                        break;
                    case 2:
                        medal = "🥉";
                        color = "#cd7f32"; // Бронза
                        break;
                }
                
                var weaponSettings = _._config.WeaponList.FirstOrDefault(w => w.IndexWeapon == stats.WeaponIndex);
                string weaponName = weaponSettings != null ? weaponSettings.DisplayName : "N/A";
                
                _.SendUserMessage($"<color={color}>{medal} #{i + 1} {player.displayName}</color> - <color=#6BCF7F>Уровень {stats.WeaponIndex + 1}</color> | <color=#ff6b6b>Оружие: {weaponName}</color>");
            }
            
            _.SendUserMessage("<color=#4ecdc4>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</color>");
        }
        
        private void StartCountdown(int seconds)
        {
            _countdownSeconds = seconds;
            
            // Отменяем предыдущий таймер если есть
            _countdownTimer?.Destroy();
            
            // Показываем начальный отсчет
            UpdateCountdownUI();
            
            // Запускаем таймер
            _countdownTimer = _.timer.Repeat(1f, seconds, () =>
            {
                _countdownSeconds--;
                
                if (_countdownSeconds <= 0)
                {
                    // Скрываем UI отсчета
                    foreach (var player in BasePlayer.activePlayerList)
                    {
                        CuiHelper.DestroyUi(player, "GunGame_Countdown");
                    }
                    
                    StartGame();
                }
                else
                {
                    UpdateCountdownUI();
                }
            });
        }
        
        private void UpdateCountdownUI()
        {
            foreach (var player in BasePlayer.activePlayerList)
        {
            var container = new CuiElementContainer();
                
            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                    Image = { Color = "0 0 0 0.85" },
                    RectTransform = { AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-150 -60", OffsetMax = "150 60" }
                }, "Overlay", "GunGame_Countdown");

                // Градиентная полоса сверху
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = "0.26 0.8 0.77 1" },
                    RectTransform = { AnchorMin = "0 1", AnchorMax = "1 1", OffsetMin = "0 -3", OffsetMax = "0 0" }
                }, "GunGame_Countdown");

            container.Add(new CuiElement
            {
                    Parent = "GunGame_Countdown",
                Components =
                {
                    new CuiTextComponent
                    {
                            Text = "НОВАЯ ИГРА ЧЕРЕЗ",
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 14,
                            Align = TextAnchor.MiddleCenter,
                            Color = "0.7 0.7 0.7 1"
                        },
                    new CuiRectTransformComponent
                    {
                            AnchorMin = "0 0.6", AnchorMax = "1 1"
                    }
                }
            });

                container.Add(new CuiElement
                {
                    Parent = "GunGame_Countdown",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = _countdownSeconds.ToString(),
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 36,
                            Align = TextAnchor.MiddleCenter,
                            Color = "1 0.85 0.24 1"
                        },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0 0", AnchorMax = "1 0.6"
                        }
                    }
                });
                
                CuiHelper.DestroyUi(player, "GunGame_Countdown");
                CuiHelper.AddUi(player, container);
            }
        }
        
        private void UpdateStartCountdownUI()
        {
            foreach (var player in BasePlayer.activePlayerList)
            {
                var container = new CuiElementContainer();
                
                // Увеличенная панель для подготовки к старту
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = "0 0 0 0.9" },
                    RectTransform = { AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-250 -120", OffsetMax = "250 120" }
                }, "Overlay", "GunGame_Countdown");

                // Градиентная полоса сверху (зеленая для старта)
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = "0.42 0.8 0.5 1" },
                    RectTransform = { AnchorMin = "0 1", AnchorMax = "1 1", OffsetMin = "0 -4", OffsetMax = "0 0" }
                }, "GunGame_Countdown");

                // Заголовок
                container.Add(new CuiElement
                {
                    Parent = "GunGame_Countdown",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = "🎮 ПОДГОТОВКА К СТАРТУ 🎮",
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 18,
                            Align = TextAnchor.MiddleCenter,
                            Color = "0.42 0.8 0.5 1"
                        },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0 0.7", AnchorMax = "1 0.95"
                        }
                    }
                });

                // Определяем цвет и текст в зависимости от оставшегося времени
                var timeColor = "1 0.85 0.24 1"; // Желтый по умолчанию
                var pulseEffect = "";
                
                if (_countdownSeconds <= 3)
                {
                    timeColor = "1 0.42 0.42 1"; // Красный для последних 3 секунд
                    pulseEffect = " ⚡";
                }
                else if (_countdownSeconds <= 5)
                {
                    timeColor = "1 0.65 0.01 1"; // Оранжевый для 4-5 секунд
                }

                // Большой счетчик
                container.Add(new CuiElement
                {
                    Parent = "GunGame_Countdown",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = _countdownSeconds.ToString() + pulseEffect,
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 72,
                            Align = TextAnchor.MiddleCenter,
                            Color = timeColor
                        },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0 0.3", AnchorMax = "1 0.7"
                        }
                    }
                });

                // Текст снизу
                var bottomText = _countdownSeconds <= 3 
                    ? "ПРИГОТОВЬТЕСЬ!" 
                    : "Игра начинается через";

                container.Add(new CuiElement
                {
                    Parent = "GunGame_Countdown",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = bottomText,
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 16,
                            Align = TextAnchor.MiddleCenter,
                            Color = "0.8 0.8 0.8 1"
                        },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0 0.1", AnchorMax = "1 0.3"
                        }
                    }
                });

                // Прогресс-бар
                var progressWidth = 1f - ((float)_countdownSeconds / 30f); // Предполагаем максимум 30 секунд для визуализации
                if (progressWidth < 0) progressWidth = 0;
                if (progressWidth > 1) progressWidth = 1;

                // Фон прогресс-бара
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = "0.2 0.2 0.2 0.5" },
                    RectTransform = { AnchorMin = "0.1 0.05", AnchorMax = "0.9 0.08", OffsetMin = "0 0", OffsetMax = "0 0" }
                }, "GunGame_Countdown", "ProgressBg");

                // Заполнение прогресс-бара
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = "0.42 0.8 0.5 0.8" },
                    RectTransform = { AnchorMin = "0 0", AnchorMax = $"{progressWidth} 1", OffsetMin = "0 0", OffsetMax = "0 0" }
                }, "ProgressBg");
                
                CuiHelper.DestroyUi(player, "GunGame_Countdown");
                CuiHelper.AddUi(player, container);
            }
        }
        
        public void GiveKill(BasePlayer attacker, BasePlayer victim)
        {
            if (!_players.ContainsKey(attacker)) return;
          
            var stat = _players[attacker];
            stat.Kills++;
            
            var settings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == stat.WeaponIndex);
            if (settings == null) return;
            
            // Оповещение в чат о убийстве
            string weaponNameRu = GetWeaponNameRu(settings.DisplayName);
            _.SendUserMessage($"<color=#ff6b6b>{attacker.displayName}</color> убил <color=#ff6b6b>{victim.displayName}</color> с <color=#ffd93d>{weaponNameRu}</color>");
            
            if (stat.Kills >= settings.NeedKills)
            {
                stat.WeaponIndex++;
                stat.Kills = 0;
                
                // Показываем уведомление о повышении уровня с именем жертвы
                _.ShowNotification(attacker, $"💀 Убит {victim.displayName}\n⬆ ПОВЫШЕНИЕ! Уровень {stat.WeaponIndex + 1}", "#6BCF7F", 3f);
                
                ShowTable();
                GiveWeaponAndWear(attacker);
            }
            else
            {
                // Показываем прогресс с именем жертвы
                var killsLeft = settings.NeedKills - stat.Kills;
                _.ShowNotification(attacker, $"💀 Убит {victim.displayName}\n+1 Убийство | Осталось: {killsLeft}", "#ffd93d", 2f);
            }

            UpdateUserInfo(attacker);
        }
        
        public void RemovePlayer(BasePlayer player)
        {
            if (!_players.ContainsKey(player)) return;
            _players.Remove(player);
            
            // Обновляем таблицу для всех
            ShowTable();
        }
        
        private void StartGame()
        {
            _gameActive = true;
            _players.Clear();

            foreach (var check in BasePlayer.activePlayerList)
            {
                AddPlayer(check);
            }
            
            _.SendUserMessage("<color=#6BCF7F>🎮 НОВАЯ ИГРА НАЧАЛАСЬ!</color>");
            ShowTable();
        }

        private void ShowTable()
        {
            var container = new CuiElementContainer();
            
            // Подсчитываем количество игроков (максимум 8)
            int playerCount = Mathf.Min(8, _players.Count);
            if (playerCount == 0) return; // Нет игроков - не показываем таблицу
            
            // Точный расчет высоты панели
            const int rowHeight = 24;      // Высота одной строки игрока
            const int rowSpacing = 2;      // Отступ между строками
            const int topBarHeight = 3;    // Высота оранжевой полосы
            const int topPadding = 5;      // Отступ после полосы
            const int bottomPadding = 5;   // Отступ снизу
            
            int totalHeight = topBarHeight + topPadding + (playerCount * (rowHeight + rowSpacing)) + bottomPadding;
            
            // Основная панель в стиле Rust (точная динамическая высота)
                container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "0.09 0.09 0.09 0.85" },
                RectTransform = { AnchorMin = "1 1", AnchorMax = "1 1", OffsetMin = $"-230 -{totalHeight}", OffsetMax = "-10 -10" }
            }, "Overlay", "GunGame_Main");

            // Акцентная полоса сверху (3px)
            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "0.82 0.35 0.11 0.9" },
                RectTransform = { AnchorMin = "0 1", AnchorMax = "1 1", OffsetMin = "0 -3", OffsetMax = "0 0" }
            }, "GunGame_Main", "TopAccent");

            // Начинаем строки после полосы и отступа
            int posy = -(topBarHeight + topPadding);
            int i = 1;
            int maxLevel = _._config.WeaponList.Count;
            
            foreach (var check in _players.OrderByDescending(p => p.Value.WeaponIndex).ThenByDescending(p => p.Value.Kills))
            {
                if (i > 8) break; // Показываем топ-8
                
                // Минимальный фон в стиле Rust
                var bgColor = "0.13 0.13 0.13 0.4";
                
                // Акцент для первого места
                if (i == 1) bgColor = "0.82 0.35 0.11 0.15"; // Rust оранжевый для первого
                
                // Фон строки
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = bgColor },
                RectTransform =
                    {
                        AnchorMin = "0 1", AnchorMax = "1 1",
                        OffsetMin = $"5 {posy}",
                        OffsetMax = $"-5 {posy + rowHeight}"
                    }
                }, "GunGame_Main", $"PlayerRow_{i}");

                // Номер места
                var rankColor = i == 1 ? "0.82 0.35 0.11 1" : "0.7 0.7 0.7 1";
                
            container.Add(new CuiElement
            {
                    Name = $"Rank_{i}",
                    Parent = $"PlayerRow_{i}",
                Components =
                {
                    new CuiTextComponent
                    {
                            Text = $"{i}",
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 9,
                            Align = TextAnchor.MiddleCenter,
                            Color = rankColor
                        },
                    new CuiRectTransformComponent
                    {
                            AnchorMin = "0 0", AnchorMax = "0 0",
                            OffsetMin = "2 0", OffsetMax = "17 24"
                    }
                }
            });

                // Имя игрока
                var playerName = check.Key.displayName;
                if (playerName.Length > 7)
                    playerName = playerName.Substring(0, 6) + "..";

            container.Add(new CuiElement
            {
                    Name = $"PlayerName_{i}",
                    Parent = $"PlayerRow_{i}",
                Components =
                {
                    new CuiTextComponent
                    {
                            Text = playerName,
                            Font = "robotocondensed-regular.ttf",
                            FontSize = 9,
                            Align = TextAnchor.MiddleLeft,
                            Color = "0.9 0.9 0.9 1"
                        },
                    new CuiRectTransformComponent
                    {
                            AnchorMin = "0 0", AnchorMax = "0 0",
                            OffsetMin = "20 0", OffsetMax = "70 24"
                    }
                }
            });

                // Получаем оружие игрока
                var weaponIndex = check.Value.WeaponIndex;
                var playerWeaponSettings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == weaponIndex);
                string weaponName = playerWeaponSettings != null ? playerWeaponSettings.DisplayName : "N/A";
                
                // Сокращаем названия оружий
                if (weaponName.Length > 9)
                {
                    weaponName = weaponName.Replace("Rifle", "R.")
                                           .Replace("Action", "A.")
                                           .Replace("Pistol", "P.")
                                           .Replace("Shotgun", "SG");
                    if (weaponName.Length > 9)
                        weaponName = weaponName.Substring(0, 8) + ".";
                }
                
                // Оружие
            container.Add(new CuiElement
            {
                    Name = $"WeaponText_{i}",
                    Parent = $"PlayerRow_{i}",
                Components =
                {
                    new CuiTextComponent
                    {
                            Text = weaponName,
                            Font = "robotocondensed-regular.ttf",
                            FontSize = 8,
                            Align = TextAnchor.MiddleLeft,
                            Color = "0.82 0.35 0.11 1"
                        },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0 0", AnchorMax = "0 0",
                            OffsetMin = "75 0", OffsetMax = "145 24"
                        }
                    }
                });
                
                // Уровень
                container.Add(new CuiElement
                {
                    Name = $"LevelText_{i}",
                    Parent = $"PlayerRow_{i}",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = $"{weaponIndex + 1}",
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 9,
                            Align = TextAnchor.MiddleCenter,
                            Color = "0.7 0.7 0.7 1"
                        },
                    new CuiRectTransformComponent
                    {
                            AnchorMin = "0 0", AnchorMax = "0 0",
                            OffsetMin = "150 0", OffsetMax = "170 24"
                    }
                }
            });

                // Киллы
                var killText = playerWeaponSettings != null ? $"{check.Value.Kills}/{playerWeaponSettings.NeedKills}" : $"{check.Value.Kills}";
            container.Add(new CuiElement
            {
                    Name = $"Kills_{i}",
                    Parent = $"PlayerRow_{i}",
                Components =
                {
                    new CuiTextComponent
                    {
                            Text = killText,
                            Font = "robotocondensed-bold.ttf",
                            FontSize = 9,
                            Align = TextAnchor.MiddleRight,
                            Color = "0.7 0.7 0.7 1"
                        },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0 0", AnchorMax = "0 0",
                            OffsetMin = "175 0", OffsetMax = "215 24"
                        }
                    }
                });

                posy -= rowHeight + rowSpacing;
                i++;
            }

            // Отправляем UI всем игрокам
            foreach (var player in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(player, "GunGame_Main");
                CuiHelper.AddUi(player, container);
                
                // Обновляем личную информацию
                if (_players.ContainsKey(player))
                    UpdateUserInfo(player);
            }
        }

        private void UpdateUserInfo(BasePlayer player)
        {
            if (!_players.ContainsKey(player)) return;
            
            var container = new CuiElementContainer();
            var settings = _players[player];
            var weaponSettings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == settings.WeaponIndex);
            
            if (weaponSettings == null) return;

            // Минималистичная панель в стиле Rust (темная, полупрозрачная, внизу экрана)
            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "0.09 0.09 0.09 0.75" },
                RectTransform = { AnchorMin = "0.5 0", AnchorMax = "0.5 0", OffsetMin = "-110 10", OffsetMax = "110 32" }
            }, "Overlay", "PlayerInfo");

            // Тонкая акцентная полоса сверху (Rust оранжевый)
            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "0.82 0.35 0.11 0.8" },
                RectTransform = { AnchorMin = "0 1", AnchorMax = "1 1", OffsetMin = "0 -1", OffsetMax = "0 0" }
            }, "PlayerInfo", "PlayerInfoAccent");

            var placement = GetPlayerPlacement(player);

            // Компактная информация: место, уровень, киллы
            var infoText = $"#{placement}  •  Lv.{settings.WeaponIndex + 1}/{_._config.WeaponList.Count}  •  {settings.Kills}/{weaponSettings.NeedKills}";
            
            container.Add(new CuiElement
            {
                Name = "PlayerInfoText",
                Parent = "PlayerInfo",
                Components =
                {
                    new CuiTextComponent
                    {
                        Text = infoText,
                        Font = "robotocondensed-bold.ttf",
                        FontSize = 10,
                        Align = TextAnchor.MiddleCenter,
                        Color = "0.9 0.9 0.9 1"
                    },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0 0", AnchorMax = "1 1",
                        OffsetMin = "5 0", OffsetMax = "-5 0"
                    }
                }
            });

            CuiHelper.DestroyUi(player, "PlayerInfo");
            CuiHelper.AddUi(player, container);
        }

        private int GetPlayerPlacement(BasePlayer player)
        {
            int i = 1;
            foreach (var check in _players.OrderByDescending(p => p.Value.WeaponIndex).ThenByDescending(p => p.Value.Kills))
            {
                if (check.Key.userID == player.userID)
                {
                    return i;
                }
                i++;
            }
            return 0;
        }
        
        private string GetWeaponNameRu(string weaponDisplayName)
        {
            // Преобразуем название оружия в родительный падеж для "убил с..."
            switch (weaponDisplayName)
            {
                case "AK-47": return "автомата";
                case "LR-300": return "автомата";
                case "M39 Rifle": return "винтовки";
                case "MP5": return "пистолета-пулемета";
                case "Thompson": return "томпсона";
                case "Semi-Auto Rifle": return "полуавтоматической винтовки";
                case "Custom SMG": return "пистолета-пулемета";
                case "Python Revolver": return "револьвера";
                case "Bolt Rifle": return "болтовки";
                case "M92 Pistol": return "пистолета";
                case "Pump Shotgun": return "дробовика";
                case "Semi Pistol": return "пистолета";
                case "Revolver": return "револьвера";
                case "Crossbow": return "арбалета";
                case "Hunting Bow": return "лука";
                default: return weaponDisplayName.ToLower();
            }
        }

        private class PlayerStat
        {
            public int Kills;
            public int WeaponIndex;
        }
    }
    
    // Класс для возвращения статуса игры
    public class GameStatus
    {
        public string IsActive { get; set; }
        public string PlayerCount { get; set; }
        public string Leader { get; set; }
        public string AutoRestart { get; set; }
    }

    #endregion
    
    #region WaitingArea
    
    void StartWaitingAreaEnforcer()
    {
        if (_config.WaitingAreaEnforcePeriod <= 0f) _config.WaitingAreaEnforcePeriod = 3f;

        _enforcerTimer?.Destroy();
        _enforcerTimer = timer.Every(Mathf.Max(1f, _config.WaitingAreaEnforcePeriod), () =>
        {
            try
            {
                if (!TryBindExistingWaitingArea())
                    EnsureWaitingArea(always: true);

                // Телепортируем только тех, кто не участвует в игре
                foreach (var pl in BasePlayer.activePlayerList)
                {
                    if (pl == null || !pl.IsConnected) continue;
                    if (_gameController != null && _gameController.IsPlayerInGame(pl)) continue;
                    
                    TeleportToWaitingArea(pl);
                }
            }
            catch (Exception ex)
            {
                PrintWarning($"[GunGame] Waiting area enforcer error: {ex.Message}");
            }
        });
    }

    bool TryBindExistingWaitingArea()
    {
        try
        {
            if (_waitingArea != null && _waitingArea.Entities != null && _waitingArea.Entities.Any(e => e && !e.IsDestroyed))
                return true;

            // Ищем pookie как маркер зоны ожидания
            BaseEntity pookie = null;
            foreach (var e in BaseNetworkable.serverEntities)
            {
                var be = e as BaseEntity;
                if (!be || be.IsDestroyed) continue;
                var n = be.ShortPrefabName ?? "";
                if (n.IndexOf("pookie", StringComparison.OrdinalIgnoreCase) >= 0)
                {
                    // Берем первую найденную плюшку
                    pookie = be;
                    break;
                }
            }

            if (pookie == null) return false;

            var area = new WaitingArea { Pos = pookie.transform.position, Rot = Quaternion.identity, Entities = new List<BaseEntity>(), Pookie = pookie };
            var cols = Physics.OverlapSphere(pookie.transform.position, 50f, ~0, QueryTriggerInteraction.Collide);
            foreach (var c in cols)
            {
                var be2 = c.GetComponentInParent<BaseEntity>();
                if (be2 != null && !be2.IsDestroyed && !area.Entities.Contains(be2))
                    area.Entities.Add(be2);
            }

            if (area.Entities.Count == 0)
                return false;

            ComputeAreaBounds(area);

            // Вычислим спавн по pookie
            Vector3 pm = pookie.transform.position + new Vector3(0f, 0.20f, 0f);
            area.Spawn = pm + Vector3.up * 1.05f;

            _waitingArea = area;
            Puts($"[GunGame] Зона ожидания найдена и привязана: {_waitingArea.Pos}");
            return true;
        }
        catch { return false; }
    }

    void EnsureWaitingArea(bool always = false)
    {
        if (_waitingArea != null && _waitingArea.Entities != null && _waitingArea.Entities.Any(e => e && !e.IsDestroyed))
            return;

        if (CopyPaste == null || !CopyPaste.IsLoaded)
        {
            PrintWarning("[GunGame] CopyPaste не найден для создания зоны ожидания.");
            return;
        }

        // Позиция - далеко от центра карты
        Vector3 pos = new Vector3(0f, 0f, -500f);
        float waterY = 0f;
        try { waterY = TerrainMeta.WaterMap.GetHeight(pos); } catch { }
        pos.y = Mathf.Max(0f, waterY) + 10f;

        var rot = Quaternion.identity;
        var area = new WaitingArea { Pos = pos, Rot = rot };
        var spawned = new List<BaseEntity>();

        Action<BaseEntity> onSpawned = (be) =>
        {
            if (be == null || be.IsDestroyed) return;
            spawned.Add(be);
        };

        Action onFinished = () =>
        {
            try
            {
                area.Entities = spawned.Where(e => e != null && !e.IsDestroyed).ToList();
                ComputeAreaBounds(area);

                BaseEntity pookie = area.Entities.FirstOrDefault(e =>
                    !string.IsNullOrEmpty(e.ShortPrefabName) &&
                    e.ShortPrefabName.IndexOf("pookie", StringComparison.OrdinalIgnoreCase) >= 0);
                area.Pookie = pookie;

                if (pookie != null)
                {
                    Vector3 marker = pookie.transform.position + new Vector3(0f, 0.2f, 0f);
                    area.Spawn = marker + Vector3.up * 1.05f;
                }
                else
                {
                    area.Spawn = pos + Vector3.up * 1.05f;
                }

                _waitingArea = area;
                Puts($"[GunGame] Зона ожидания создана: {_waitingArea.Pos}");
            }
            catch (Exception e)
            {
                PrintWarning($"[GunGame] Ошибка создания зоны ожидания: {e.Message}");
            }
        };

        var args = new[]
        {
            "autoheight","false",
            "blockcollision","0",
            "auth","false",
            "entityowner","false",
            "checkplaced","false",
            "stability","true",
            "dlc","true",
            "skins","1"
        };

        var result = CopyPaste.Call("TryPasteFromVector3", pos, rot.eulerAngles.y * Mathf.Deg2Rad, _config.WaitingAreaCopyPasteName, args, onFinished, onSpawned);

        if (result is string err && !string.IsNullOrEmpty(err) && !err.Equals("true", StringComparison.OrdinalIgnoreCase))
        {
            PrintWarning($"[GunGame] CopyPaste ошибка создания зоны ожидания: {err}");
        }
    }

    void TeleportToWaitingArea(BasePlayer p)
    {
        if (p == null || !p.IsConnected) return;

        // Если игрок участвует в игре - не телепортируем
        if (_gameController != null && _gameController.IsPlayerInGame(p)) return;

        if (_waitingArea == null || _waitingArea.Entities == null || _waitingArea.Entities.Count == 0)
        {
            EnsureWaitingArea(always: true);
            return;
        }

        if (!_waitingArea.Entities.Any(e => e != null && !e.IsDestroyed))
        {
            _waitingArea = null;
            EnsureWaitingArea(always: true);
            return;
        }

        // Проверяем, не находится ли уже внутри
        if (IsInsideAreaXZ(_waitingArea, p.transform.position))
            return;

        Vector3 dst = _waitingArea.Spawn ?? (_waitingArea.Pos + Vector3.up * 1.05f);
        TeleportPlayer(p, dst);
        
        // Разблокируем и очищаем инвентарь (игрок становится голым)
        p.inventory.containerWear.SetLocked(false);
        p.inventory.containerBelt.SetLocked(false);
        p.inventory.containerMain.SetLocked(false);
        
        p.inventory.Strip();
        p.SendNetworkUpdateImmediate();
        
        // Выдаем бинокль для наблюдения в лобби
        var binoculars = ItemManager.CreateByName("tool.binoculars", 1);
        if (binoculars != null)
        {
            binoculars.MoveToContainer(p.inventory.containerBelt);
        }
        
        // Восстанавливаем здоровье
        p.health = 100f;
        p.metabolism.bleeding.value = 0f;
        p.metabolism.radiation_level.value = 0f;
        p.metabolism.radiation_poison.value = 0f;
        p.metabolism.calories.value = p.metabolism.calories.max;
        p.metabolism.hydration.value = p.metabolism.hydration.max;
        p.SendNetworkUpdateImmediate();

        if (_config.WaitingAreaShowMessage)
        {
            ShowNotification(p, "⏳ Дождитесь начала следующей игры!", "#ffa502", 4f);
        }
    }

    void TeleportPlayer(BasePlayer p, Vector3 pos)
    {
        if (p == null || !p.IsConnected) return;

        try
        {
            p.EnsureDismounted();
        }
        catch { }

        p.SetParent(null);
        p.SetPlayerFlag(BasePlayer.PlayerFlags.ReceivingSnapshot, true);
        p.Teleport(pos);
        p.ClientRPCPlayer(null, p, "StartLoading");
        p.SendNetworkUpdateImmediate();
        p.ClearEntityQueue();
        p.UpdateNetworkGroup();
        p.SendFullSnapshot();
    }

    void ComputeAreaBounds(WaitingArea area)
    {
        if (area == null || area.Entities == null || area.Entities.Count == 0)
        {
            area.Bounds = null;
            area.Size = Vector3.zero;
            area.Center = area.Pos;
            return;
        }

        bool hasAny = false;
        Bounds total = new Bounds();

        foreach (var e in area.Entities)
        {
            if (!e) continue;
            var cols = e.GetComponentsInChildren<Collider>(true);
            if (cols == null || cols.Length == 0) continue;

            foreach (var col in cols)
            {
                if (!hasAny)
                {
                    total = col.bounds;
                    hasAny = true;
                }
                else total.Encapsulate(col.bounds);
            }
        }

        if (hasAny)
        {
            area.Bounds = total;
            area.Size = total.size;
            area.Center = total.center;
        }
        else
        {
            area.Bounds = null;
            area.Size = Vector3.zero;
            area.Center = area.Pos;
        }
    }

    bool IsInsideAreaXZ(WaitingArea area, Vector3 pos, float pad = 0.25f)
    {
        if (area == null || !area.Bounds.HasValue) return false;
        var b = area.Bounds.Value;
        return pos.x >= (b.min.x + pad) && pos.x <= (b.max.x - pad)
            && pos.z >= (b.min.z + pad) && pos.z <= (b.max.z - pad);
    }

    #endregion
}

