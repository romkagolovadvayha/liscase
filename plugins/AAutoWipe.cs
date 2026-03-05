//Reference: Ionic.Zip.Reduced
//Reference: Cronos

using System;
using System.Collections.Generic;
using System.IO;
using Ionic.Zip;
using System.Linq;
using System.Threading.Tasks;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries;
using UnityEngine;
using Cronos;
using Newtonsoft.Json.Converters;
using Oxide.Core.Plugins;
using HarmonyLib;

namespace Oxide.Plugins
{
    [Info("Авто Вайп", "Tangerine", "1.1.6")]
    [Description("Система автоматического вайпа сервера")]
    class AAutoWipe : RustPlugin 
    {
        #region Classes
        
        private class CronJob
        {
            [JsonProperty("Cron выражение (Секунда Минута Час День Месяц ДеньНедели)")]
            public string Expression { get; set; }

            [JsonProperty("Даты следующих запусков", ObjectCreationHandling = ObjectCreationHandling.Replace)] 
            public List<DateTime> NextRun { get; set; } = new();

            [JsonProperty("Дата последнего запуска")] 
            public DateTime LastRun { get; set; } = DateTime.Now;
            
            [JsonProperty("Использовать UTC время? (Если false, будет использоваться локальное время)")]
            public bool UseUtc { get; set; }
            
            public bool IsMatchingNow()
            {
                var currentTime = UseUtc ? DateTime.UtcNow : DateTime.Now;
                if (NextRun.Count == 0)
                {
                    FindValidNextRun();
                }

                if (NextRun.Count == 0)
                {
                    return false; 
                }

                var nextRun = NextRun[0];
                if (nextRun < currentTime)
                {
                    var timePassed = currentTime - nextRun;
                    var shouldRun = timePassed.TotalMinutes < 1;
                    LastRun = nextRun;
                    FindValidNextRun();
                    
                    if(shouldRun == false)
                        _plugin.PrintWarning("Обнаружен пропущенный запуск по расписанию. Пропускаем...");
                    
                    return shouldRun;
                }
                
                return false;
            }

            private void FindValidNextRun()
            {
                var cronExpression = CronExpression.Parse(Expression, Expression.Split(" ").Length >= 6 ? CronFormat.IncludeSeconds : CronFormat.Standard);
                
                var curDate = DateTime.UtcNow;
                NextRun = cronExpression.GetOccurrences(curDate, curDate.AddYears(1), UseUtc ? TimeZoneInfo.Utc : TimeZoneInfo.Local).Take(5).ToList();
                
                _plugin.SaveConfig();
            }
        }
        
        private class WipePreset
        {
            [JsonProperty("Задержка входа игроков после вайпа (предотвращает вход игроков сразу после вайпа)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public TimeSpan EnterDelay = TimeSpan.FromMinutes(5);
            [JsonProperty("Файлы Identity/Карты, которые должны быть удалены", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string[] IdentityFiles = Array.Empty<string>();
            [JsonProperty("Файлы Oxide, которые должны быть удалены", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string[] OxideFiles = Array.Empty<string>();
            [JsonProperty("ConVar команды (выполняются при старте сервера)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string[] ConVarCommands = Array.Empty<string>();
            [JsonProperty("Команды после вайпа (выполняются после полной загрузки сервера)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string[] AfterWipeCommands = Array.Empty<string>();
            [JsonProperty("Является ли пресет постоянным, если true, он не будет автоматически удален после выполнения")]
            public bool Persistent = false;
            [JsonProperty("Расписание вайпа по Cron", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public CronJob WipeSchedule = new CronJob();
            [JsonProperty("Файлы сообщений Discord для объявления вайпа (data/AAutoWipe/filename.json) (Используйте: 'https://share.discohook.app/go/j1elnjf1' для получения json)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string[] DiscordMessageFiles = Array.Empty<string>();
            [JsonProperty("Замена файлов (ПОДДЕРЖИВАЕТСЯ ТОЛЬКО ZIP)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public FileReplacement[] FileReplacements = Array.Empty<FileReplacement>();
        }

        private class FileReplacement
        {
            public FileReplacement(string fileSource, string destination)
            {
                FileSource = fileSource;
                Destination = destination;
            }
            
            [JsonProperty("Исходный ZIP файл (Расположен в data/AAutoWipe/filename.zip)")]
            public string FileSource;
            [JsonProperty("Назначение (от корневой папки)")]
            public string Destination;
        }

        private enum EnabledQueueMode
        {
            Static,
            Queue
        }
        
        #endregion 

        #region Variables
        
        private static AAutoWipe _plugin;

        private readonly List<WipePreset> _activePresets = new();

        private WipePreset _wipePreset = null;

        private Timer wipeCheckTimer;
        private Timer wipeCountdownTimer;

        private RealTimeUntil _playersCanJoin = 0;
        private int _restartSecondsRemaining = 0;
        
        #endregion

        #region OxideHooks

        private void Init()
        {
            _plugin = this;
            PluginData.LoadData();
            
#if CARBON
            _plugin.RunConVarCommands();
#endif
            TryRunWipe();
        }
        
#if !CARBON
        [AutoPatch] 
        [HarmonyPatch(typeof(Bootstrap), "StartupShared", new Type[] { })] 
        public class Bootstrap_StartupShared_Patch
        {
            public static void Prefix()
            {
                Debug.Log($"[{_plugin.Name}] StartupShared_Patch вызван префикс.");
                _plugin.RunConVarCommands();
            }
        }
#endif
        
        private void Loaded()
        {
            ValidatePathsInConfig();
            LoadActivePresets();

            wipeCheckTimer = timer.Every(5f, WipeCheck);
        }

        void OnServerInitialized()
        {
            // small delay to let all plugins load fully
            timer.Once(2f, () =>
            {
                if (_wipePreset == null)
                    return;

                SendDiscordWipeMessage();

                if (_wipePreset.AfterWipeCommands == null || _wipePreset.AfterWipeCommands.Length == 0)
                    return;
                
                foreach (var command in _wipePreset.AfterWipeCommands)
                {
                    Server.Command(command);
                }
            });
        }
        
        private void Unload()
        {
            wipeCountdownTimer?.Destroy();
            wipeCheckTimer?.Destroy();
            PluginData.SaveData();
            _plugin = null;
        }
        
        object CanClientLogin(Network.Connection connection)
        {
            if (_playersCanJoin > 0)
            {
                return string.Format(GetLocal(connection.userid.ToString(), LangKey.WipeDelayKickMessage), TimeSpan.FromSeconds(_playersCanJoin).ToShortString());
            }

            return null;
        }
        #endregion
        
        #region Commands
        
        [ConsoleCommand("autowipe.run")]
        private void Console_RunPreset(ConsoleSystem.Arg arg)
        {
            if (arg.IsAdmin == false) 
                return;

            if (arg.HasArgs(1) == false)
            {
                SendReply(arg, "Неверный синтаксис! Использование: autowipe.run <пресет>");
                return;
            }

            var preset = arg.GetString(0);
            if (string.IsNullOrEmpty(preset) || _config.WipePresets.ContainsKey(preset) == false)
            {
                SendReply(arg, "Неверное имя пресета вайпа!");
                return;
            }
            
            InitPreset(preset);
            SendReply(arg, $"Пресет вайпа '{preset}' активирован! Начинается вайп сервера...");
        }
        
        [ConsoleCommand("autowipe.cancel")]
        private void Console_CancelPreset(ConsoleSystem.Arg arg)
        {
            if (arg.IsAdmin == false) 
                return;
            
            CancelPreset();
            SendReply(arg, $"Вайп отменен...");
        }
        
        [ConsoleCommand("autowipe.runnow")]
        private void Console_RunNow(ConsoleSystem.Arg arg)
        {
            if (arg.IsAdmin == false) 
                return;

            if (arg.HasArgs(2) == false)
            {
                SendReply(arg, "Неверный синтаксис! Использование: autowipe.runnow <seed> <worldsize> [имя_пресета] [gamemode] [description] [maxplayers] [hostname] [tags]");
                SendReply(arg, "Пример: autowipe.runnow 12345 4000");
                SendReply(arg, "Пример: autowipe.runnow 12345 4000 monthly");
                SendReply(arg, "Пример: autowipe.runnow 12345 4000 monthly survival \"Мой сервер\" 100 \"Rust Server\" \"pvp,vanilla\"");
                return;
            }

            var seed = arg.GetString(0);
            var worldsize = arg.GetString(1);
            var basePresetName = arg.HasArgs(3) ? arg.GetString(2) : "monthly";
            
            // Опциональные параметры
            string gamemode = null;
            string description = null;
            string maxplayers = null;
            string hostname = null;
            string tags = null;
            
            // Определяем, где начинаются опциональные параметры
            // Если 3-й аргумент не является именем пресета (не найден в конфиге), то это gamemode
            int optionalStartIndex = 3;
            if (arg.HasArgs(3) && _config.WipePresets.ContainsKey(arg.GetString(2)) == false)
            {
                // 3-й аргумент не пресет, значит это gamemode
                basePresetName = "monthly";
                optionalStartIndex = 2;
            }
            
            if (arg.HasArgs(optionalStartIndex + 1))
                gamemode = arg.GetString(optionalStartIndex);
            if (arg.HasArgs(optionalStartIndex + 2))
                description = arg.GetString(optionalStartIndex + 1);
            if (arg.HasArgs(optionalStartIndex + 3))
                maxplayers = arg.GetString(optionalStartIndex + 2);
            if (arg.HasArgs(optionalStartIndex + 4))
                hostname = arg.GetString(optionalStartIndex + 3);
            if (arg.HasArgs(optionalStartIndex + 5))
                tags = arg.GetString(optionalStartIndex + 4);
            
            // Проверяем, существует ли базовый пресет
            if (_config.WipePresets.ContainsKey(basePresetName) == false)
            {
                SendReply(arg, $"Базовый пресет '{basePresetName}' не найден! Используются настройки по умолчанию.");
                basePresetName = null;
            }

            // Создаем временный пресет с указанными параметрами
            var tempPresetName = $"temp_wipe_{DateTime.UtcNow:yyyyMMddHHmmss}";
            WipePreset tempPreset;
            
            if (basePresetName != null && _config.WipePresets.TryGetValue(basePresetName, out var basePreset))
            {
                // Клонируем базовый пресет
                tempPreset = new WipePreset
                {
                    EnterDelay = basePreset.EnterDelay,
                    IdentityFiles = basePreset.IdentityFiles,
                    OxideFiles = basePreset.OxideFiles,
                    AfterWipeCommands = basePreset.AfterWipeCommands,
                    Persistent = false,
                    DiscordMessageFiles = basePreset.DiscordMessageFiles,
                    FileReplacements = basePreset.FileReplacements
                };
                
                // Создаем список команд для замены/добавления
                var commandsList = new List<string>();
                var replacedCommands = new HashSet<string>();
                
                // Обновляем существующие ConVar команды с новыми параметрами
                for (int i = 0; i < basePreset.ConVarCommands.Length; i++)
                {
                    var cmd = basePreset.ConVarCommands[i];
                    bool replaced = false;
                    
                    if (cmd.StartsWith("server.seed") || cmd.StartsWith("seed "))
                    {
                        commandsList.Add($"server.seed \"{seed}\"");
                        replacedCommands.Add("server.seed");
                        replaced = true;
                    }
                    else if (cmd.StartsWith("server.worldsize") || cmd.StartsWith("worldsize "))
                    {
                        commandsList.Add($"server.worldsize \"{worldsize}\"");
                        replacedCommands.Add("server.worldsize");
                        replaced = true;
                    }
                    else if (gamemode != null && (cmd.StartsWith("server.gamemode") || cmd.StartsWith("gamemode ")))
                    {
                        commandsList.Add($"server.gamemode \"{gamemode}\"");
                        replacedCommands.Add("server.gamemode");
                        replaced = true;
                    }
                    else if (description != null && (cmd.StartsWith("server.description") || cmd.StartsWith("description ")))
                    {
                        commandsList.Add($"server.description \"{description}\"");
                        replacedCommands.Add("server.description");
                        replaced = true;
                    }
                    else if (maxplayers != null && (cmd.StartsWith("server.maxplayers") || cmd.StartsWith("maxplayers ")))
                    {
                        commandsList.Add($"server.maxplayers \"{maxplayers}\"");
                        replacedCommands.Add("server.maxplayers");
                        replaced = true;
                    }
                    else if (hostname != null && (cmd.StartsWith("server.hostname") || cmd.StartsWith("hostname ")))
                    {
                        commandsList.Add($"server.hostname \"{hostname}\"");
                        replacedCommands.Add("server.hostname");
                        replaced = true;
                    }
                    else if (tags != null && (cmd.StartsWith("server.tags") || cmd.StartsWith("tags ")))
                    {
                        commandsList.Add($"server.tags \"{tags}\"");
                        replacedCommands.Add("server.tags");
                        replaced = true;
                    }
                    
                    if (!replaced)
                    {
                        commandsList.Add(cmd);
                    }
                }
                
                // Добавляем новые команды, если они не были в пресете
                if (gamemode != null && !replacedCommands.Contains("server.gamemode"))
                {
                    commandsList.Add($"server.gamemode \"{gamemode}\"");
                }
                if (description != null && !replacedCommands.Contains("server.description"))
                {
                    commandsList.Add($"server.description \"{description}\"");
                }
                if (maxplayers != null && !replacedCommands.Contains("server.maxplayers"))
                {
                    commandsList.Add($"server.maxplayers \"{maxplayers}\"");
                }
                if (hostname != null && !replacedCommands.Contains("server.hostname"))
                {
                    commandsList.Add($"server.hostname \"{hostname}\"");
                }
                if (tags != null && !replacedCommands.Contains("server.tags"))
                {
                    commandsList.Add($"server.tags \"{tags}\"");
                }
                
                tempPreset.ConVarCommands = commandsList.ToArray();
            }
            else
            {
                // Создаем пресет с настройками по умолчанию
                var defaultCommands = new List<string>
                {
                    "server.level \"Procedural Map\"",
                    $"server.seed \"{seed}\"",
                    $"server.worldsize \"{worldsize}\"",
                    "server.saveinterval \"300\"",
                    "server.levelurl \"\""
                };
                
                if (gamemode != null)
                    defaultCommands.Add($"server.gamemode \"{gamemode}\"");
                if (description != null)
                    defaultCommands.Add($"server.description \"{description}\"");
                if (maxplayers != null)
                    defaultCommands.Add($"server.maxplayers \"{maxplayers}\"");
                if (hostname != null)
                    defaultCommands.Add($"server.hostname \"{hostname}\"");
                if (tags != null)
                    defaultCommands.Add($"server.tags \"{tags}\"");
                
                tempPreset = new WipePreset
                {
                    IdentityFiles = new[]
                    {
                        ".sav",
                        "player.deaths.",
                        "player.identities.",
                        "player.states.",
                        "player.tokens.",
                        "sv.files.",
                    },
                    OxideFiles = new[]
                    {
                        "data/WIPE",
                        "config/wipe.json",
                        "logs",
                    },
                    ConVarCommands = defaultCommands.ToArray(),
                    AfterWipeCommands = Array.Empty<string>(),
                    Persistent = false
                };
            }
            
            // Добавляем временный пресет в конфиг
            _config.WipePresets[tempPresetName] = tempPreset;
            SaveConfig();
            
            // Запускаем вайп
            InitPreset(tempPresetName);
            
            // Формируем сообщение с параметрами
            var paramsList = new List<string> { $"seed: {seed}", $"worldsize: {worldsize}" };
            if (gamemode != null) paramsList.Add($"gamemode: {gamemode}");
            if (description != null) paramsList.Add($"description: {description}");
            if (maxplayers != null) paramsList.Add($"maxplayers: {maxplayers}");
            if (hostname != null) paramsList.Add($"hostname: {hostname}");
            if (tags != null) paramsList.Add($"tags: {tags}");
            
            SendReply(arg, $"Вайп запущен с {string.Join(", ", paramsList)} (используется пресет: {basePresetName ?? "по умолчанию"})");
        }
        
        [ConsoleCommand("autowipe.seedonly")]
        private void Console_SeedOnlyWipe(ConsoleSystem.Arg arg)
        {
            if (arg.IsAdmin == false) 
                return;

            // Генерируем случайный seed от 1 до 5000
            var random = new System.Random();
            var randomSeed = random.Next(1, 5001);
            
            // Создаем временный пресет только для смены seed
            var tempPresetName = $"temp_seedonly_{DateTime.UtcNow:yyyyMMddHHmmss}";
            var tempPreset = new WipePreset
            {
                EnterDelay = TimeSpan.FromMinutes(2), // Минимальная задержка
                IdentityFiles = Array.Empty<string>(), // Не удаляем данные игроков
                OxideFiles = Array.Empty<string>(), // Не удаляем Oxide файлы
                ConVarCommands = new[]
                {
                    $"server.seed \"{randomSeed}\""
                },
                AfterWipeCommands = Array.Empty<string>(),
                Persistent = false,
                DiscordMessageFiles = Array.Empty<string>(),
                FileReplacements = Array.Empty<FileReplacement>()
            };
            
            // Добавляем временный пресет в конфиг
            _config.WipePresets[tempPresetName] = tempPreset;
            SaveConfig();
            
            // Запускаем вайп
            InitPreset(tempPresetName);
            
            SendReply(arg, $"Вайп только seed карты запущен! Новый seed: {randomSeed} (данные игроков сохранены)");
        }
        
        [ConsoleCommand("autowipe.fullwipe")]
        private void Console_FullWipe(ConsoleSystem.Arg arg)
        {
            if (arg.IsAdmin == false) 
                return;

            // Генерируем случайный seed от 1 до 5000
            var random = new System.Random();
            var randomSeed = random.Next(1, 5001);
            
            // Получаем текущий worldsize или используем значение по умолчанию
            string worldsize = "4000";
            if (_config.WipePresets.TryGetValue("monthly", out var monthlyPreset))
            {
                foreach (var cmd in monthlyPreset.ConVarCommands)
                {
                    if (cmd.StartsWith("server.worldsize") || cmd.StartsWith("worldsize "))
                    {
                        worldsize = GetValueFromConVar(cmd);
                        break;
                    }
                }
            }
            
            // Создаем временный пресет для полного вайпа
            var tempPresetName = $"temp_fullwipe_{DateTime.UtcNow:yyyyMMddHHmmss}";
            var tempPreset = new WipePreset
            {
                EnterDelay = TimeSpan.FromMinutes(5),
                IdentityFiles = new[]
                {
                    ".sav",
                    "player.blueprints",
                    "player.deaths",
                    "player.identities",
                    "player.states",
                    "player.tokens",
                    "sv.files",
                },
                OxideFiles = new[]
                {
                    "data/Kits/kits_data.json",
                    "data/wipe",
                    "config/wipe.json",
                    "logs",
                },
                ConVarCommands = new[]
                {
                    "server.level \"Procedural Map\"",
                    $"server.seed \"{randomSeed}\"",
                    $"server.worldsize \"{worldsize}\"",
                    "server.saveinterval \"300\"",
                    "server.levelurl \"\""
                },
                AfterWipeCommands = Array.Empty<string>(),
                Persistent = false,
                DiscordMessageFiles = Array.Empty<string>(),
                FileReplacements = Array.Empty<FileReplacement>()
            };
            
            // Добавляем временный пресет в конфиг
            _config.WipePresets[tempPresetName] = tempPreset;
            SaveConfig();
            
            // Запускаем вайп
            InitPreset(tempPresetName);
            
            SendReply(arg, $"Глобальный вайп запущен! Новый seed: {randomSeed}, worldsize: {worldsize} (все данные удалены)");
        }
        
        [ConsoleCommand("autowipe.mapwipe")]
        private void Console_MapWipe(ConsoleSystem.Arg arg)
        {
            if (arg.IsAdmin == false) 
                return;

            // Генерируем случайный seed от 1 до 5000
            var random = new System.Random();
            var randomSeed = random.Next(1, 5001);
            
            // Получаем текущий worldsize или используем значение по умолчанию
            string worldsize = "4000";
            if (_config.WipePresets.TryGetValue("monthly", out var monthlyPreset))
            {
                foreach (var cmd in monthlyPreset.ConVarCommands)
                {
                    if (cmd.StartsWith("server.worldsize") || cmd.StartsWith("worldsize "))
                    {
                        worldsize = GetValueFromConVar(cmd);
                        break;
                    }
                }
            }
            
            // Создаем временный пресет для вайпа только карты
            var tempPresetName = $"temp_mapwipe_{DateTime.UtcNow:yyyyMMddHHmmss}";
            var tempPreset = new WipePreset
            {
                EnterDelay = TimeSpan.FromMinutes(3),
                IdentityFiles = new[]
                {
                    ".sav", // Удаляем только файлы карты
                    "sv.files.", // Файлы сервера связанные с картой
                },
                OxideFiles = Array.Empty<string>(), // Не удаляем Oxide файлы
                ConVarCommands = new[]
                {
                    "server.level \"Procedural Map\"",
                    $"server.seed \"{randomSeed}\"",
                    $"server.worldsize \"{worldsize}\"",
                    "server.saveinterval \"300\"",
                    "server.levelurl \"\""
                },
                AfterWipeCommands = Array.Empty<string>(),
                Persistent = false,
                DiscordMessageFiles = Array.Empty<string>(),
                FileReplacements = Array.Empty<FileReplacement>()
            };
            
            // Добавляем временный пресет в конфиг
            _config.WipePresets[tempPresetName] = tempPreset;
            SaveConfig();
            
            // Запускаем вайп
            InitPreset(tempPresetName);
            
            SendReply(arg, $"Вайп карты запущен! Новый seed: {randomSeed}, worldsize: {worldsize} (данные игроков сохранены)");
        }
        
        #endregion
        
        #region Config v0.0.1

        private void ValidateConfig()
        {
            if (Interface.Oxide.CallHook("OnConfigValidate") != null)
            {
                PrintWarning("Используется конфигурация по умолчанию...");
                _config = GetDefaultConfig();
            }
        }
        
        private class PluginConfig
        {
            [JsonProperty("Секунд до перезапуска при вайпе")]
            public int RestartSeconds = 300;
            [JsonProperty("Включенные пресеты вайпа", ObjectCreationHandling = ObjectCreationHandling.Replace)] 
            public List<string> EnabledPresets = new();

            [JsonProperty("Режим очереди включенных пресетов", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            [JsonConverter(typeof(StringEnumConverter))]
            public EnabledQueueMode PresetsQueueMode = EnabledQueueMode.Static; 
            
            [JsonProperty("Создавать резервную копию файлов перед удалением?")] 
            public bool BackupFilesBeforeWipe = false;
            
            [JsonProperty("URL Discord webhook", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string DiscordWebHook = "";
            
            [JsonProperty("Пресеты вайпа", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public Dictionary<string, WipePreset> WipePresets = new()
            {
                ["weekly"] = new WipePreset()
                {
                    IdentityFiles = new []
                    {
                        ".sav",
                        "player.deaths.",
                        "player.identities.",
                        "player.states.",
                        "player.tokens.",
                        "sv.files.",
                    },
                    OxideFiles = new []
                    {
                       "data/WIPE",
                       "config/wipe.json",
                       "logs",
                    },
                    ConVarCommands = new []
                    { 
                        "server.level \"Procedural Map \"",
                        "server.seed \"55555\"",
                        "server.worldsize \"400\"",
                        "server.saveinterval \"300\"",
                        "server.levelurl \"\""
                    },
                    AfterWipeCommands = new []
                    { 
                        "test command here",
                        "and another one",
                    },
                    Persistent = true,
                    WipeSchedule = new CronJob()
                    {
                        UseUtc = false,
                        Expression = "* 30 15 */7 * 1"
                    },
                    DiscordMessageFiles = new []
                    {
                        "weeklywipe"
                    },
                    FileReplacements =  new []
                    {
                        new FileReplacement("test", "oxide/")
                    }
                },
                ["biweekly"] = new WipePreset()
                {
                    IdentityFiles = new []
                    {
                        ".sav",
                        "player.deaths.",
                        "player.identities.",
                        "player.states.",
                        "player.tokens.",
                        "sv.files.",
                    },
                    OxideFiles = new []
                    {
                        "data/WIPE",
                        "config/wipe.json",
                        "logs",
                    },
                    ConVarCommands = new []
                    { 
                        "server.level \"Procedural Map \"",
                        "server.seed \"55555\"",
                        "server.worldsize \"400\"",
                        "server.saveinterval \"300\"",
                        "server.levelurl \"\""
                    },
                    AfterWipeCommands = new []
                    { 
                        "test command here",
                        "and another one",
                    },
                    Persistent = true,
                    WipeSchedule = new CronJob()
                    {
                        UseUtc = false,
                        Expression = "* 30 15 */14 * 1"
                    },
                    DiscordMessageFiles = new []
                    {
                        "weeklywipe"
                    },
                    FileReplacements =  new []
                    {
                        new FileReplacement("test", "oxide/")
                    }
                },
                ["monthly"] = new WipePreset()
                {
                    IdentityFiles = new []
                    {
                        ".sav",
                        "player.blueprints",
                        "player.deaths",
                        "player.identities",
                        "player.states",
                        "player.tokens",
                        "sv.files",
                    },
                    OxideFiles = new []
                    {
                        "data/Kits/kits_data.json",
                        "data/wipe",
                        "config/wipe.json",
                        "logs",
                    },
                    ConVarCommands = new []
                    { 
                        "server.level \"Procedural Map\"",
                        "server.seed \"55555\"",
                        "server.worldsize \"400\"",
                        "server.saveinterval \"300\"",
                        "server.levelurl \"\""
                    },
                    AfterWipeCommands = new []
                    { 
                        "test command here",
                        "and another one",
                    },
                    Persistent = true,
                    WipeSchedule = new CronJob()
                    {
                        UseUtc = true,
                        Expression = "* 30 15 1-7 * 1"
                    },
                    DiscordMessageFiles = new []
                    {
                        "monthlywipe"
                    },
                    FileReplacements =  new []
                    {
                        new FileReplacement("test", "oxide/")
                    }
                }
            };
        }
        
        private static PluginConfig _config;
        
        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                _config = Config.ReadObject<PluginConfig>();
                if (_config == null)
                {
                    LoadDefaultConfig();
                }
            }
            catch
            {
                for (var i = 0; i < 3; i++)
                {
                    PrintError("Файл конфигурации поврежден! Проверьте ваш конфиг на https://jsonlint.com/");
                }
                
                LoadDefaultConfig();
                return;
            }

            // ValidateConfig();
            SaveConfig();   
            
            
            if (_config == null)
                return;
            
            foreach (var configWipePreset in _config.WipePresets)
            {
                configWipePreset.Value.WipeSchedule.NextRun = new List<DateTime>();
            }
        }

        protected override void SaveConfig()
        {
            Config.WriteObject(_config);
        }

        protected override void LoadDefaultConfig()
        {
            _config = GetDefaultConfig();
        }
        
        private PluginConfig GetDefaultConfig()
        {
            return new PluginConfig
            {
                
            };
        }
        #endregion

        #region Utils

        private DateTime? GetNextWipeDate()
        {
            var minDate = DateTime.MaxValue;
            foreach (var preset in _config.EnabledPresets)
            {
                if(_config.WipePresets.TryGetValue(preset, out var presetCfg) == false)
                    continue;
                
                if(presetCfg.WipeSchedule.NextRun.Count == 0 || presetCfg.WipeSchedule.NextRun[0] > minDate)
                    continue;

                minDate = presetCfg.WipeSchedule.NextRun[0];
            }

            return minDate == DateTime.MaxValue ? null : minDate;
        }
        
        private void ValidatePathsInConfig()
        {
            foreach (var keyPair in _config.WipePresets)
            {
                var preset = keyPair.Value;
                for (var i = 0; i < preset.OxideFiles.Length; i++)
                {
                    var path = preset.OxideFiles[i];
                    if (Path.HasExtension(path))
                        continue;

                    path = Utility.CleanPath(path);
                    if (path.EndsWith(Path.DirectorySeparatorChar) == false)
                        path += Path.DirectorySeparatorChar;

                    preset.OxideFiles[i] = path;
                }
            }
        }
        
        private void SafeExtract(string file, string dest)
        {
            var fileName = file.EndsWith(".zip") ? file : $"{file}.zip";
            var zipPath = Path.GetFullPath(Path.Combine(Interface.Oxide.DataDirectory, $"AAutoWipe/{fileName}")); 
            
            try
            {
                if (File.Exists(zipPath) == false)
                {
                    Debug.LogError($"[AAutoWipe] Не удалось извлечь '{zipPath}', файл не существует.");
                    return;
                }

                if (ZipFile.CheckZip(zipPath) == false)
                {
                    Debug.LogError($"[AAutoWipe] Не удалось извлечь '{zipPath}', невалидный zip.");
                    return;
                }

                var fullDest = Path.Combine(Path.GetFullPath(Interface.Oxide.RootDirectory), dest);
                if (Directory.Exists(fullDest) == false)
                    Directory.CreateDirectory(fullDest);
                
                using var zip = new ZipFile(zipPath);
                zip.ExtractAll(fullDest, ExtractExistingFileAction.OverwriteSilently);
                Debug.Log($"[AAutoWipe] Извлечен '{zipPath}' в '{dest}'.");
            }
            catch (Exception e)
            {
                Debug.LogError($"[AAutoWipe] Не удалось извлечь '{zipPath}' в '{dest}'. Причина: {e}");
            }
        }
        
        private void SendDiscordWipeMessage()
        {
            if (string.IsNullOrEmpty(_config.DiscordWebHook) != false) 
                return;
            
            foreach (var wipePresetDiscordMessageFile in _wipePreset.DiscordMessageFiles)
            {
                var path = Path.Combine(Name, wipePresetDiscordMessageFile);
                var json = Interface.Oxide.DataFileSystem.ReadObject<Dictionary<string, object>>(path);
                if (json == null)
                {
                    PrintError($"Не удалось распарсить сообщение Discord ('{path}'), содержимое невалидно.");
                    continue;
                }
                
                SendDiscordMessage(JsonConvert.SerializeObject(json), _config.DiscordWebHook);
            }
        }
        
        private void TryRunWipe()
        {
            var wipePreset = _data.WipeData.RestartWipePreset;  
            if (string.IsNullOrWhiteSpace(wipePreset))
                return;
            
            _data.WipeData.RestartWipePreset = string.Empty;
            _data.WipeData.LastPresetConVars = Array.Empty<string>();
                
            if (_config.WipePresets.TryGetValue(wipePreset, out var preset))
            {
                Debug.Log($"Вайп сервера с пресетом: {wipePreset}");
                _data.WipeData.LastPresetConVars = preset.ConVarCommands;
                _wipePreset = preset;
                ExecuteWipe(preset);
                _playersCanJoin = (float)preset.EnterDelay.TotalSeconds;
                
                // Удаляем временный пресет после использования
                if (wipePreset.StartsWith("temp_wipe_"))
                {
                    _config.WipePresets.Remove(wipePreset);
                    SaveConfig();
                }
            }
            else
            {
                Debug.LogError($"Неверное имя включенного пресета в конфиге! {wipePreset}");
            }
            
            PluginData.SaveData();
        }
        
        private void LoadActivePresets()
        {
            foreach (var enabledPreset in _config.EnabledPresets)
            {
                if (_config.WipePresets.TryGetValue(enabledPreset, out var preset) == false)
                {
                    PrintError($"Неверное имя включенного пресета в конфиге! {enabledPreset}");
                    continue;
                }

                _activePresets.Add(preset);
            }
        }
        
        private void WipeCheck() 
        {
            if (string.IsNullOrEmpty(_data.WipeData.RestartWipePreset) == false)
                return;
            
            foreach (var activePreset in _activePresets)
            {
                if (activePreset.WipeSchedule.IsMatchingNow() == false)
                    continue;
                
                var presetName = _config.WipePresets.FirstOrDefault(x => x.Value == activePreset).Key;
                Puts($"Расписание пресета вайпа совпало ({presetName}), запущен вайп сервера!"); 
                if (activePreset.Persistent == false)
                {
                    _config.EnabledPresets.Remove(presetName);
                }

                InitPreset(presetName);
                activePreset.WipeSchedule.LastRun = DateTime.UtcNow;
                UpdatePresetsQueue(presetName);
                SaveConfig();  
                break;
            }
        }

        private void UpdatePresetsQueue(string presetName)
        {
            if(_config.EnabledPresets == null || _config.EnabledPresets.Count <= 1)
                return;
            
            switch (_config.PresetsQueueMode)
            {
                case EnabledQueueMode.Queue:
                {
                    _config.EnabledPresets.Remove(presetName);
                    _config.EnabledPresets.Add(presetName);
                    
                    break;
                }
            }
        }

        private void InitPreset(string presetName)
        {
            _data.WipeData.RestartWipePreset = presetName;
            PluginData.SaveData();
            
            _restartSecondsRemaining = _config.RestartSeconds;
            
            // Отправляем начальное сообщение
            BroadcastWipeMessage(_restartSecondsRemaining);
            
            // Запускаем таймер обратного отсчета
            StartWipeCountdown();
            
            Server.Command($"restart {_config.RestartSeconds}");
            
            wipeCheckTimer?.Destroy();
        }
        
        private void StartWipeCountdown()
        {
            // Уничтожаем предыдущий таймер, если он существует
            wipeCountdownTimer?.Destroy();
            
            // Запускаем таймер каждую секунду
            wipeCountdownTimer = timer.Every(1f, () =>
            {
                _restartSecondsRemaining--;
                
                if (_restartSecondsRemaining <= 0)
                {
                    wipeCountdownTimer?.Destroy();
                    wipeCountdownTimer = null;
                    return;
                }
                
                // Отправляем сообщения на определенных интервалах
                if (_restartSecondsRemaining == _config.RestartSeconds)
                {
                    // Начальное сообщение уже отправлено
                    return;
                }
                
                // Отправляем сообщения каждые 30 секунд, или на критических интервалах
                if (_restartSecondsRemaining % 30 == 0 || 
                    _restartSecondsRemaining == 300 || // 5 минут
                    _restartSecondsRemaining == 180 || // 3 минуты
                    _restartSecondsRemaining == 60 ||  // 1 минута
                    _restartSecondsRemaining == 30 || // 30 секунд
                    _restartSecondsRemaining == 10 || // 10 секунд
                    _restartSecondsRemaining <= 5)     // Последние 5 секунд
                {
                    BroadcastWipeMessage(_restartSecondsRemaining);
                }
            });
        }
        
        private void BroadcastWipeMessage(int secondsRemaining)
        {
            var timeSpan = TimeSpan.FromSeconds(secondsRemaining);
            string timeString;
            
            if (timeSpan.TotalHours >= 1)
            {
                timeString = $"{(int)timeSpan.TotalHours}ч {timeSpan.Minutes}м {timeSpan.Seconds}с";
            }
            else if (timeSpan.TotalMinutes >= 1)
            {
                timeString = $"{timeSpan.Minutes}м {timeSpan.Seconds}с";
            }
            else
            {
                timeString = $"{timeSpan.Seconds}с";
            }
            
            var message = $"<color=#ff6b6b>⚠ ВНИМАНИЕ!</color> Вайп сервера через: <color=#ffd93d>{timeString}</color>";
            
            // Отправляем сообщение всем игрокам
            foreach (var player in BasePlayer.activePlayerList)
            {
                if (player != null && player.IsConnected)
                {
                    Player.Message(player, message, 0);
                }
            }
            
            // Также отправляем в консоль сервера
            Puts($"[AAutoWipe] Вайп через: {timeString}");
        }
        
        private void CancelPreset()
        {
            _data.WipeData.RestartWipePreset = "";
            PluginData.SaveData();
            
            // Останавливаем таймер обратного отсчета
            wipeCountdownTimer?.Destroy();
            wipeCountdownTimer = null;
            _restartSecondsRemaining = 0;
            
            // Уведомляем игроков об отмене
            var cancelMessage = "<color=#51cf66>✓ Вайп отменен администратором</color>";
            foreach (var player in BasePlayer.activePlayerList)
            {
                if (player != null && player.IsConnected)
                {
                    Player.Message(player, cancelMessage, 0);
                }
            }
            
            Server.Command($"restart -1");
            
            wipeCheckTimer?.Destroy();
            wipeCheckTimer = timer.Every(5f, WipeCheck);
        }

        private void ExecuteWipe(WipePreset preset)
        {
            
#if CARBON
            WipeIdentityFolder(preset);
#else
            // needs to be async to get correct identity folder name
            WipeIdentityFolderAsync(preset);
#endif
                
            var rootPath = Path.GetFullPath(Interface.Oxide.InstanceDirectory);
                Debug.Log($"Очищена папка инстанса (({WipeFiles(rootPath, preset.OxideFiles, false)} файлов)): {rootPath}");

            DoReplacements(preset);
        }

        private void DoReplacements(WipePreset preset)
        {
            if (preset.FileReplacements == null || preset.FileReplacements.Length == 0)
                return;

            foreach (var replacement in preset.FileReplacements)
            {
                SafeExtract(replacement.FileSource, replacement.Destination);
            }
        }

        private async void WipeIdentityFolderAsync(WipePreset preset)
        {
            // small delay to let default server commands run first 
            await Task.Delay(1000);
            WipeIdentityFolder(preset);
        }
        
        private void WipeIdentityFolder(WipePreset preset)
        {
            try
            {
                var identityPath = GetIdentityPath();
                
                if (Directory.Exists(identityPath) == false)
                {
                    Debug.LogWarning($"Не удалось найти валидную директорию identity! Путь: {identityPath}");
                    return;
                }
                
                Debug.Log($"Очищена папка identity ({WipeFiles(identityPath, preset.IdentityFiles)} файлов): {identityPath}");
            }
            catch (Exception e)
            {
                Debug.LogError($"Не удалось выполнить команды.\n{e}");
            }
        }

        private void RunConVarCommands()
        {
            try
            {
                if (_data == null || _data.WipeData == null)
                    return;
                
                if (_data.WipeData.LastPresetConVars == null || _data.WipeData.LastPresetConVars.Length == 0)
                    return;
                
                foreach (var presetWipeCommand in _data.WipeData.LastPresetConVars)
                {
                    ConsoleSystem.Run(ConsoleSystem.Option.Server.Quiet(), presetWipeCommand);
                    
#if CARBON
                    if (presetWipeCommand.StartsWith("server.seed") || presetWipeCommand.StartsWith("seed "))
                    {
                        World.Seed = uint.Parse(GetValueFromConVar(presetWipeCommand));
                    }
                    
                    if (presetWipeCommand.StartsWith("server.salt") || presetWipeCommand.StartsWith("salt "))
                    {
                        World.Salt = uint.Parse(GetValueFromConVar(presetWipeCommand));
                    }
                    
                    if (presetWipeCommand.StartsWith("server.levelurl") || presetWipeCommand.StartsWith("levelurl "))
                    {
                        World.Url = GetValueFromConVar(presetWipeCommand);
                    }

                    if (presetWipeCommand.StartsWith("server.worldsize") || presetWipeCommand.StartsWith("worldsize "))
                    {
                        World.Size = uint.Parse(GetValueFromConVar(presetWipeCommand));
                    }
#endif
                }
            }
            catch (Exception e)
            {
                Debug.LogError($"Не удалось выполнить команды.\n{e}");
            }
        }

        private string GetValueFromConVar(string fullConVar)
        {
            var split = fullConVar.Split(" ");
            return split[split.Length - 1].Trim('"');
        }
        
        private int WipeFiles(string rootPath, string[] matches, bool matchCheck = true)
        {
            if (matches == null || matches.Length == 0)
                return -1;

            List<string> matchingFiles;
            if (matchCheck)
            {
                var files = Directory.GetFiles(rootPath, "*", SearchOption.AllDirectories).Select(Path.GetFullPath).ToArray();
                matchingFiles = FindMatchingFiles(files, matches);
            }
            else
            {
                matchingFiles = new List<string>();
                foreach (var match in matches)
                {
                    matchingFiles.Add(Path.GetFullPath(match, rootPath));
                }
            }
            
            if(_config.BackupFilesBeforeWipe)
                BackupFilesInto(matchingFiles, Path.Combine(rootPath, $"wipe_backup_{DateTime.UtcNow:MM_dd_yy_HH_mm}"));
            
            return DeleteFiles(matchingFiles);
        }
        
        private List<string> FindMatchingFiles(string[] files, string[] matches)
        {
            var matchingFiles = new List<string>();
            
            foreach (var file in files)
            {
                if (Array.Exists(matches, x => file.Contains(Utility.CleanPath(x))) == false) 
                    continue;

                matchingFiles.Add(file);
            }

            return matchingFiles;
        }
        
        private int DeleteFiles(IEnumerable<string> files)
        {
            int deleted = 0;
            foreach (var file in files)
            {
                if (SafeDelete(file))
                    deleted++;
            }

            return deleted;
        }
        
        private bool SafeDelete(string fileFullPath)
        {
            try
            {
                if (File.Exists(fileFullPath))
                {
                    File.Delete(fileFullPath);
                    Debug.Log($"[AAutoWipe] Файл удален '{fileFullPath}'");
                }
                else if (Directory.Exists(fileFullPath))
                {
                    Directory.Delete(fileFullPath, true);
                    Debug.Log($"[AAutoWipe] Директория удалена '{fileFullPath}'");
                }
                return true;
            }
            catch (Exception e)
            {
                    PrintError($"Не удалось удалить файл: '{fileFullPath}'!\nОшибка: {e.Message}");
            }

            return false;
        }
        
        private string GetIdentityPath() => Path.GetFullPath(Path.Combine(Directory.GetCurrentDirectory(), Path.Combine(World.SaveFolderName)));

        private void BackupFilesInto(IEnumerable<string> filesPath, string backupFolderPath)
        {
            try
            {
                if(Directory.Exists(backupFolderPath))
                    Directory.Delete(backupFolderPath, true);  
            
                var folder = Directory.CreateDirectory(backupFolderPath); 
                foreach (var file in filesPath)
                {
                    File.Copy(file, Path.Combine(folder.FullName, Path.GetFileName(file)), overwrite: true);
                }
            }
            catch (Exception e)
            {
                PrintError($"Не удалось создать резервную копию файлов: '{backupFolderPath}'!\nОшибка: {e.Message}");
            }
        }
        
        #endregion
        
        #region Data v0.0.3

        private class WipeData
        {
            public string RestartWipePreset = string.Empty;
            [JsonProperty(ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public string[] LastPresetConVars = Array.Empty<string>();
        }

        private static PluginData _data => PluginData.Instance;
        
        private class PluginData
        {
            private const string BasePath = "AAutoWipe/Data";
            public static PluginData Instance;

            [JsonProperty("Данные вайпа", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public WipeData WipeData = new WipeData();
            
            public static void SaveData<T>(string path, T data)
            {
                if (data == null) 
                    return;
                
                if (path.StartsWith(BasePath) == false)
                    path = Path.Combine(BasePath, path);
                
                Interface.Oxide.DataFileSystem.WriteObject(path, data);
            }

            public static T LoadData<T>(string path)
            {
                if (Interface.Oxide.DataFileSystem.ExistsDatafile(path) == false)
                    return default(T);
                
                if (path.StartsWith(BasePath) == false)
                    path = Path.Combine(BasePath, path);
                
                return Interface.Oxide.DataFileSystem.ReadObject<T>(path);
            }

            public static void LoadData()
            {
                if (Interface.Oxide.DataFileSystem.ExistsDatafile(BasePath))
                {
                    try
                    {
                        Instance = LoadData<PluginData>(BasePath);
                    }
                    catch (Exception e)
                    {
                        Debug.Log("Не удалось загрузить данные! " + e);
                        Interface.Oxide.UnloadPlugin(_plugin.Name);
                    }
                    return;
                }
                
                Instance = new PluginData();
                SaveData(BasePath, Instance);
            }
            
            public static void SaveData() => SaveData(BasePath, Instance);
        }
        
        #endregion

        #region Discord

        private void SendDiscordMessage(string json, string webhook)
        {
            if (string.IsNullOrEmpty(webhook) == true || webhook.Contains("/api") == false)
            {
                return;
            }
            
            Puts("Отправка сообщения в Discord...");
            webrequest.Enqueue(webhook, json, RequestCallback, this, RequestMethod.POST, new Dictionary<string, string>{{"Content-Type", "application/json"}});
        }

        private void RequestCallback(int code, string response)
        {
            if(code != 200 && code != 204)
            {
                PrintWarning($"Не удалось отправить сообщение, Discord ответил с кодом {code}: {response}");
            }
        }

        #endregion
        
        #region Localisation v0.0.1
        private enum LangKey
        {
            WipeDelayKickMessage,
        }
        
        protected override void LoadDefaultMessages()
        {
            var dict = new Dictionary<LangKey, string>
            {
                [LangKey.WipeDelayKickMessage] = "Сервер загружается, пожалуйста подождите: {0}",
            };
            
            lang.RegisterMessages(dict.ToDictionary(x => x.Key.ToString(), x => x.Value), this);
        }
        
        private void PlayerMessage(BasePlayer player, LangKey message, params object[] args)
        {
            Player.Message(player, string.Format(GetLocal(player.UserIDString, message.ToString()), args), 0);// _config.ChatIconId);
        }
        
        private void PlayerMessage(BasePlayer player, string message, params object[] args)
        {
            Player.Message(player, string.Format(message, args), 0);// _config.ChatIconId);
        }
        
        private string GetLocal(string playerId, string key) => lang.GetMessage(key, this, playerId);
        
        private string GetLocal(string playerId, LangKey key) => lang.GetMessage(key.ToString(), this, playerId);
        
        #endregion
    }
} 