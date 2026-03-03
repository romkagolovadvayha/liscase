using System;
using System.Collections.Generic;
using System.Linq;
using Oxide.Core;
using Oxide.Core.Libraries.Covalence;
using Oxide.Core.Plugins;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Clan System", "Akusik", "0.0.2")]
    [Description("Клановая система")]
    public class ClanSystem : RustPlugin
    {
        #region Fields
        private Dictionary<string, Clan> clans = new Dictionary<string, Clan>();
        private Dictionary<string, string> playerClans = new Dictionary<string, string>();
        private Dictionary<string, ClanInvite> pendingInvites = new Dictionary<string, ClanInvite>();
        private Dictionary<string, ClanWar> activeWars = new Dictionary<string, ClanWar>();
        private DataFileSystem dataFileSystem;
        #endregion

        #region Data Classes
        public class Clan
        {
            public string Name { get; set; }
            public string Tag { get; set; }
            public string Leader { get; set; }
            public List<ClanMember> Members { get; set; } = new List<ClanMember>();
            public Vector3 BaseLocation { get; set; }
            public int Level { get; set; } = 1;
            public int Experience { get; set; } = 0;
            public int MaxMembers { get; set; } = 10;
            public string Motto { get; set; } = "";
            public ClanStatistics Statistics { get; set; } = new ClanStatistics();
            public List<ClanAchievement> Achievements { get; set; } = new List<ClanAchievement>();
            public DateTime CreatedDate { get; set; } = DateTime.Now;
        }

        public class ClanMember
        {
            public string SteamId { get; set; }
            public string Name { get; set; }
            public ClanRole Role { get; set; }
            public DateTime JoinDate { get; set; } = DateTime.Now;
            public int Kills { get; set; } = 0;
            public int Deaths { get; set; } = 0;
        }

        public class ClanData
        {
            public Dictionary<string, Clan> Clans { get; set; } = new Dictionary<string, Clan>();
            public Dictionary<string, string> PlayerClans { get; set; } = new Dictionary<string, string>();
        }

        public class LegacyClanMember
        {
            public ulong SteamId { get; set; }
            public string Name { get; set; }
            public ClanRole Role { get; set; }
            public DateTime JoinDate { get; set; } = DateTime.Now;
            public int Kills { get; set; } = 0;
            public int Deaths { get; set; } = 0;
        }

        public class LegacyClanData
        {
            public Dictionary<string, Clan> Clans { get; set; } = new Dictionary<string, Clan>();
            public Dictionary<ulong, string> PlayerClans { get; set; } = new Dictionary<ulong, string>();
        }

        public class ClanInvite
        {
            public string ClanName { get; set; }
            public string InviterId { get; set; }
            public DateTime ExpireTime { get; set; }
        }

        public class ClanWar
        {
            public string AttackerClan { get; set; }
            public string DefenderClan { get; set; }
            public WarStatus Status { get; set; }
            public DateTime StartTime { get; set; }
            public DateTime EndTime { get; set; }
            public int AttackerScore { get; set; } = 0;
            public int DefenderScore { get; set; } = 0;
            public List<WarEvent> Events { get; set; } = new List<WarEvent>();
        }

        public class WarEvent
        {
            public string EventType { get; set; }
            public string Description { get; set; }
            public DateTime Timestamp { get; set; } = DateTime.Now;
        }

        public class ClanStatistics
        {
            public int TotalKills { get; set; } = 0;
            public int TotalDeaths { get; set; } = 0;
            public int WarsWon { get; set; } = 0;
            public int WarsLost { get; set; } = 0;
            public int RaidsCompleted { get; set; } = 0;
            public int RaidsDefended { get; set; } = 0;
        }

        public class ClanAchievement
        {
            public string Name { get; set; }
            public string Description { get; set; }
            public DateTime UnlockedDate { get; set; } = DateTime.Now;
        }

        public enum ClanRole
        {
            Member = 0,
            Officer = 1,
            Leader = 2
        }

        public enum WarStatus
        {
            Pending = 0,
            Active = 1,
            Completed = 2,
            Cancelled = 3
        }
        #endregion

        #region Oxide Hooks
        void Init()
        {
            LoadConfig();
            LoadData();
            RegisterPermissions();
        }

        void OnServerSave()
        {
            SaveData();
        }

        void OnPlayerDeath(BasePlayer player, HitInfo info)
        {
            if (player == null || info?.InitiatorPlayer == null) return;

            var victimClan = GetPlayerClan(player.userID);
            var killerClan = GetPlayerClan(info.InitiatorPlayer.userID);

            if (victimClan != null)
            {
                var victimMember = victimClan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
                if (victimMember != null)
                {
                    victimMember.Deaths++;
                    victimClan.Statistics.TotalDeaths++;
                }
            }

            if (killerClan != null)
            {
                var killerMember = killerClan.Members.FirstOrDefault(m => m.SteamId == info.InitiatorPlayer.UserIDString);
                if (killerMember != null)
                {
                    killerMember.Kills++;
                    killerClan.Statistics.TotalKills++;
                    AddExperience(killerClan, 10);
                }
            }
        }

        void OnPlayerConnected(BasePlayer player)
        {
            if (player == null) return;
            
            if (pendingInvites.ContainsKey(player.UserIDString))
            {
                var invite = pendingInvites[player.UserIDString];
                if (DateTime.Now > invite.ExpireTime)
                {
                    pendingInvites.Remove(player.UserIDString);
                    player.ChatMessage("Ваше приглашение в клан истекло.");
                }
            }
        }

        void OnPlayerDisconnected(BasePlayer player)
        {
        }
        #endregion

        #region Data Management
        void LoadData()
        {
            dataFileSystem = Interface.Oxide.DataFileSystem;
            
            try
            {
                var data = dataFileSystem.ReadObject<ClanData>("ClanSystem");
                
                if (data != null)
                {
                    clans = data.Clans ?? new Dictionary<string, Clan>();
                    playerClans = data.PlayerClans ?? new Dictionary<string, string>();
                    
                    MigrateOldData();
                }
                else
                {
                    Puts("No existing data found, starting fresh");
                    clans = new Dictionary<string, Clan>();
                    playerClans = new Dictionary<string, string>();
                }
            }
            catch (Exception ex)
            {
                Puts($"Error loading clan data: {ex.Message}");
                Puts("Attempting to recover corrupted data...");
                
                LoadLegacyData();
            }
        }

        void SaveData()
        {
            var data = new ClanData
            {
                Clans = clans,
                PlayerClans = playerClans
            };
            dataFileSystem.WriteObject("ClanSystem", data);
        }

        void MigrateOldData()
        {
            bool needsSave = false;
            
            foreach (var clan in clans.Values)
            {
                foreach (var member in clan.Members)
                {
                    if (member.SteamId != null && ulong.TryParse(member.SteamId, out ulong steamId))
                    {
                        continue;
                    }
                    
                    if (member.SteamId == null)
                    {
                        Puts($"Warning: Found clan member with null SteamId in clan {clan.Name}");
                        continue;
                    }
                    
                    if (member.SteamId is string)
                    {
                        continue;
                    }
                    
                    try
                    {
                        var oldSteamId = Convert.ToInt64(member.SteamId);
                        member.SteamId = oldSteamId.ToString();
                        needsSave = true;
                        Puts($"Migrated SteamId for member {member.Name} in clan {clan.Name}");
                    }
                    catch (Exception ex)
                    {
                        Puts($"Error migrating SteamId for member {member.Name} in clan {clan.Name}: {ex.Message}");
                    }
                }
            }
            
            var newPlayerClans = new Dictionary<string, string>();
            foreach (var kvp in playerClans)
            {
                string key;
                if (ulong.TryParse(kvp.Key, out ulong steamId))
                {
                    key = steamId.ToString();
                }
                else
                {
                    key = kvp.Key;
                }
                newPlayerClans[key] = kvp.Value;
            }
            
            if (!newPlayerClans.SequenceEqual(playerClans))
            {
                playerClans = newPlayerClans;
                needsSave = true;
                Puts("Migrated playerClans dictionary keys to string format");
            }
            
            if (needsSave)
            {
                SaveData();
                Puts("Data migration completed and saved");
            }
        }

        void LoadLegacyData()
        {
            try
            {
                var rawData = dataFileSystem.ReadObject<object>("ClanSystem");
                if (rawData == null)
                {
                    Puts("No data file found, starting fresh");
                    clans = new Dictionary<string, Clan>();
                    playerClans = new Dictionary<string, string>();
                    return;
                }
                
                Puts("Found corrupted data, attempting manual recovery...");
                
                var jsonString = dataFileSystem.ReadObject<string>("ClanSystem");
                if (jsonString != null)
                {
                    Puts("Attempting to fix corrupted data format...");
                    
                    clans = new Dictionary<string, Clan>();
                    playerClans = new Dictionary<string, string>();
                    
                    SaveData();
                    Puts("Created fresh data structure");
                }
                else
                {
                    Puts("Could not recover data, starting fresh");
                    clans = new Dictionary<string, Clan>();
                    playerClans = new Dictionary<string, string>();
                }
            }
            catch (Exception ex)
            {
                Puts($"Error in legacy data recovery: {ex.Message}");
                Puts("Starting with fresh data");
                clans = new Dictionary<string, Clan>();
                playerClans = new Dictionary<string, string>();
            }
        }
        #endregion

        #region Configuration
        private ConfigData config;

        class ConfigData
        {
            public ClanSettings ClanSettings { get; set; } = new ClanSettings();
            public Permissions Permissions { get; set; } = new Permissions();
            public Messages Messages { get; set; } = new Messages();
            public Commands Commands { get; set; } = new Commands();
        }

        class ClanSettings
        {
            public int MaxClanNameLength { get; set; } = 20;
            public int MinClanNameLength { get; set; } = 3;
            public int MaxClanTagLength { get; set; } = 5;
            public int MinClanTagLength { get; set; } = 2;
            public int DefaultMaxMembers { get; set; } = 10;
            public int MaxClanLevel { get; set; } = 10;
            public int ExperiencePerLevel { get; set; } = 1000;
            public bool AllowClanChat { get; set; } = true;
            public bool AllowClanBase { get; set; } = true;
            public bool AllowClanTeleport { get; set; } = true;
            public int ClanBaseCooldown { get; set; } = 300;
            public int InviteExpireTime { get; set; } = 300;
        }

        class Permissions
        {
            public string CreateClan { get; set; } = "clansystem.create";
            public string InvitePlayers { get; set; } = "clansystem.invite";
            public string AdminAccess { get; set; } = "clansystem.admin";
        }

        class Messages
        {
            public string ClanCreated { get; set; } = "Клан '{0}' успешно создан! Тег: [{1}]";
            public string PlayerInvited { get; set; } = "Игрок {0} приглашен в клан.";
            public string InviteReceived { get; set; } = "Вас пригласили в клан '{0}'. Введите /clan accept для вступления.";
            public string PlayerJoined { get; set; } = "{0} присоединился к клану!";
            public string PlayerLeft { get; set; } = "{0} покинул клан.";
            public string PlayerKicked { get; set; } = "{0} был исключен из клана.";
            public string PlayerPromoted { get; set; } = "{0} повышен до {1}.";
            public string PlayerDemoted { get; set; } = "{0} понижен до {1}.";
            public string BaseSet { get; set; } = "Местоположение базы клана установлено.";
            public string TeleportedToBase { get; set; } = "Вы телепортированы на базу клана.";
            public string NoPermission { get; set; } = "У вас нет прав для использования этой команды.";
            public string ClanNotFound { get; set; } = "Клан не найден.";
            public string PlayerNotFound { get; set; } = "Игрок не найден.";
            public string AlreadyInClan { get; set; } = "Вы уже состоите в клане.";
            public string NotInClan { get; set; } = "Вы не состоите в клане.";
            public string ClanFull { get; set; } = "Клан переполнен.";
            public string WarDeclared { get; set; } = "Объявлена война клану '{0}'!";
            public string WarAccepted { get; set; } = "Война с кланом '{0}' принята!";
            public string WarWon { get; set; } = "Ваш клан победил в войне против '{0}'!";
            public string WarLost { get; set; } = "Ваш клан проиграл войну против '{0}'.";
            public string MottoSet { get; set; } = "Девиз клана установлен: {0}";
            public string LeadershipTransferred { get; set; } = "Лидерство передано {0}.";
        }

        class Commands
        {
            public string ClanCommand { get; set; } = "clan";
            public string ClanChatCommand { get; set; } = "c";
            public string AdminCommand { get; set; } = "clan.admin";
        }

        void LoadConfig()
        {
            config = Config.ReadObject<ConfigData>();
            if (config == null)
            {
                LoadDefaultConfig();
            }
            SaveConfig();
        }

        void LoadDefaultConfig()
        {
            config = new ConfigData();
        }

        void SaveConfig()
        {
            Config.WriteObject(config);
        }
        #endregion

        #region Permissions
        void RegisterPermissions()
        {
            if (config?.Permissions == null)
            {
                Puts("Warning: Config not loaded, using default permissions");
                permission.RegisterPermission("clansystem.create", this);
                permission.RegisterPermission("clansystem.invite", this);
                permission.RegisterPermission("clansystem.admin", this);
                return;
            }
            
            permission.RegisterPermission(config.Permissions.CreateClan, this);
            permission.RegisterPermission(config.Permissions.InvitePlayers, this);
            permission.RegisterPermission(config.Permissions.AdminAccess, this);
        }
        #endregion

        #region Commands
        [ChatCommand("clan")]
        void ClanCommand(BasePlayer player, string command, string[] args)
        {
            if (args.Length == 0)
            {
                ShowClanHelp(player);
                return;
            }

            switch (args[0].ToLower())
            {
                case "create":
                    if (args.Length < 3)
                    {
                        player.ChatMessage("Использование: /clan create <название> <тег>");
                        return;
                    }
                    CreateClan(player, args[1], args[2]);
                    break;

                case "invite":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("Использование: /clan invite <игрок>");
                        return;
                    }
                    InvitePlayer(player, args[1]);
                    break;

                case "accept":
                    AcceptInvite(player);
                    break;

                case "leave":
                    LeaveClan(player);
                    break;

                case "kick":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("Использование: /clan kick <игрок>");
                        return;
                    }
                    KickPlayer(player, args[1]);
                    break;

                case "promote":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("Использование: /clan promote <игрок>");
                        return;
                    }
                    PromotePlayer(player, args[1]);
                    break;

                case "demote":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("Использование: /clan demote <игрок>");
                        return;
                    }
                    DemotePlayer(player, args[1]);
                    break;

                case "base":
                    SetClanBase(player);
                    break;

                case "tp":
                    TeleportToBase(player);
                    break;

                case "info":
                    ShowClanInfo(player);
                    break;

                case "members":
                    ShowClanMembers(player);
                    break;

                case "war":
                    if (args.Length < 3)
                    {
                        player.ChatMessage("Использование: /clan war <declare/accept> <клан>");
                        return;
                    }
                    HandleWarCommand(player, args[1], args[2]);
                    break;

                case "motto":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("Использование: /clan motto <текст>");
                        return;
                    }
                    SetClanMotto(player, string.Join(" ", args.Skip(1)));
                    break;

                case "transfer":
                    if (args.Length < 2)
                    {
                        player.ChatMessage("Использование: /clan transfer <игрок>");
                        return;
                    }
                    TransferLeadership(player, args[1]);
                    break;

                case "stats":
                    ShowClanStats(player);
                    break;

                case "achievements":
                    ShowClanAchievements(player);
                    break;

                default:
                    ShowClanHelp(player);
                    break;
            }
        }

        [ChatCommand("c")]
        void ClanChatCommand(BasePlayer player, string command, string[] args)
        {
            if (config?.ClanSettings == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            if (!config.ClanSettings.AllowClanChat)
            {
                player.ChatMessage("Чат клана отключен.");
                return;
            }

            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var message = string.Join(" ", args);
            if (string.IsNullOrEmpty(message))
            {
                player.ChatMessage("Использование: /c <сообщение>");
                return;
            }

            SendClanMessage(clan, $"[{player.displayName}]: {message}");
        }

        [ConsoleCommand("clan.admin")]
        void AdminCommand(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || config?.Permissions == null || !permission.UserHasPermission(player.UserIDString, config.Permissions.AdminAccess))
            {
                return;
            }

            if (arg.Args == null || arg.Args.Length == 0)
            {
                player.ChatMessage("Использование: clan.admin <команда> [аргументы]");
                return;
            }

            switch (arg.Args[0].ToLower())
            {
                case "delete":
                    if (arg.Args.Length < 2)
                    {
                        player.ChatMessage("Использование: clan.admin delete <клан>");
                        return;
                    }
                    DeleteClan(player, arg.Args[1]);
                    break;

                case "list":
                    ListAllClans(player);
                    break;

                case "info":
                    if (arg.Args.Length < 2)
                    {
                        player.ChatMessage("Использование: clan.admin info <клан>");
                        return;
                    }
                    ShowAdminClanInfo(player, arg.Args[1]);
                    break;

                default:
                    player.ChatMessage("Неизвестная админская команда.");
                    break;
            }
        }
        #endregion

        #region Clan Management
        void CreateClan(BasePlayer player, string name, string tag)
        {
            if (config?.Permissions == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            if (!permission.UserHasPermission(player.UserIDString, config.Permissions.CreateClan))
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            if (GetPlayerClan(player.userID) != null)
            {
                player.ChatMessage(config.Messages.AlreadyInClan);
                return;
            }

            if (name.Length < config.ClanSettings.MinClanNameLength || name.Length > config.ClanSettings.MaxClanNameLength)
            {
                player.ChatMessage($"Название клана должно содержать от {config.ClanSettings.MinClanNameLength} до {config.ClanSettings.MaxClanNameLength} символов.");
                return;
            }

            if (tag.Length < config.ClanSettings.MinClanTagLength || tag.Length > config.ClanSettings.MaxClanTagLength)
            {
                player.ChatMessage($"Тег клана должен содержать от {config.ClanSettings.MinClanTagLength} до {config.ClanSettings.MaxClanTagLength} символов.");
                return;
            }

            if (clans.ContainsKey(name))
            {
                player.ChatMessage("Клан с таким названием уже существует.");
                return;
            }

            var clan = new Clan
            {
                Name = name,
                Tag = tag,
                Leader = player.UserIDString,
                MaxMembers = config.ClanSettings.DefaultMaxMembers
            };

            clan.Members.Add(new ClanMember
            {
                SteamId = player.UserIDString,
                Name = player.displayName,
                Role = ClanRole.Leader
            });

            clans[name] = clan;
            playerClans[player.UserIDString] = name;

            player.ChatMessage(string.Format(config.Messages.ClanCreated, name, tag));
        }

        void InvitePlayer(BasePlayer player, string targetName)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var member = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (member == null || member.Role < ClanRole.Officer)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            var targetPlayer = BasePlayer.Find(targetName);
            if (targetPlayer == null)
            {
                player.ChatMessage(config.Messages.PlayerNotFound);
                return;
            }

            if (GetPlayerClan(targetPlayer.userID) != null)
            {
                player.ChatMessage("Игрок уже состоит в клане.");
                return;
            }

            if (clan.Members.Count >= clan.MaxMembers)
            {
                player.ChatMessage(config.Messages.ClanFull);
                return;
            }

            var invite = new ClanInvite
            {
                ClanName = clan.Name,
                InviterId = player.UserIDString,
                ExpireTime = DateTime.Now.AddSeconds(config.ClanSettings.InviteExpireTime)
            };

            pendingInvites[targetPlayer.UserIDString] = invite;
            player.ChatMessage(string.Format(config.Messages.PlayerInvited, targetPlayer.displayName));
            targetPlayer.ChatMessage(string.Format(config.Messages.InviteReceived, clan.Name));
        }

        void AcceptInvite(BasePlayer player)
        {
            if (!pendingInvites.ContainsKey(player.UserIDString))
            {
                player.ChatMessage("У вас нет активных приглашений.");
                return;
            }

            var invite = pendingInvites[player.UserIDString];
            if (DateTime.Now > invite.ExpireTime)
            {
                pendingInvites.Remove(player.UserIDString);
                player.ChatMessage("Ваше приглашение истекло.");
                return;
            }

            if (!clans.ContainsKey(invite.ClanName))
            {
                pendingInvites.Remove(player.UserIDString);
                player.ChatMessage("Клан больше не существует.");
                return;
            }

            var clan = clans[invite.ClanName];
            if (clan.Members.Count >= clan.MaxMembers)
            {
                pendingInvites.Remove(player.UserIDString);
                player.ChatMessage(config.Messages.ClanFull);
                return;
            }

            clan.Members.Add(new ClanMember
            {
                SteamId = player.UserIDString,
                Name = player.displayName,
                Role = ClanRole.Member
            });

            playerClans[player.UserIDString] = clan.Name;
            pendingInvites.Remove(player.UserIDString);

            SendClanMessage(clan, string.Format(config.Messages.PlayerJoined, player.displayName));
            player.ChatMessage($"You have joined clan '{clan.Name}'!");
        }

        void LeaveClan(BasePlayer player)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var member = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (member == null) return;

            if (member.Role == ClanRole.Leader && clan.Members.Count > 1)
            {
                player.ChatMessage("Вы не можете покинуть клан как лидер. Сначала передайте лидерство или повысьте кого-то другого.");
                return;
            }

            clan.Members.Remove(member);
            playerClans.Remove(player.UserIDString);

            if (clan.Members.Count == 0)
            {
                clans.Remove(clan.Name);
                player.ChatMessage("Клан распущен.");
            }
            else
            {
                SendClanMessage(clan, string.Format(config.Messages.PlayerLeft, player.displayName));
                player.ChatMessage("Вы покинули клан.");
            }
        }

        void KickPlayer(BasePlayer player, string targetName)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var kicker = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (kicker == null || kicker.Role < ClanRole.Officer)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            var targetPlayer = BasePlayer.Find(targetName);
            if (targetPlayer == null)
            {
                player.ChatMessage(config.Messages.PlayerNotFound);
                return;
            }

            var targetMember = clan.Members.FirstOrDefault(m => m.SteamId == targetPlayer.UserIDString);
            if (targetMember == null)
            {
                player.ChatMessage("Игрок не состоит в вашем клане.");
                return;
            }

            if (targetMember.Role >= kicker.Role)
            {
                player.ChatMessage("Вы не можете исключить игрока с равным или более высоким рангом.");
                return;
            }

            clan.Members.Remove(targetMember);
            playerClans.Remove(targetPlayer.UserIDString);

            SendClanMessage(clan, string.Format(config.Messages.PlayerKicked, targetPlayer.displayName));
            targetPlayer.ChatMessage($"Вы были исключены из клана '{clan.Name}'.");
        }

        void PromotePlayer(BasePlayer player, string targetName)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var promoter = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (promoter == null || promoter.Role < ClanRole.Leader)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            var targetPlayer = BasePlayer.Find(targetName);
            if (targetPlayer == null)
            {
                player.ChatMessage(config.Messages.PlayerNotFound);
                return;
            }

            var targetMember = clan.Members.FirstOrDefault(m => m.SteamId == targetPlayer.UserIDString);
            if (targetMember == null)
            {
                player.ChatMessage("Игрок не состоит в вашем клане.");
                return;
            }

            if (targetMember.Role >= ClanRole.Officer)
            {
                player.ChatMessage("Игрок уже имеет максимальный ранг.");
                return;
            }

            targetMember.Role = ClanRole.Officer;
            SendClanMessage(clan, string.Format(config.Messages.PlayerPromoted, targetPlayer.displayName, "Officer"));
        }

        void DemotePlayer(BasePlayer player, string targetName)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var demoter = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (demoter == null || demoter.Role < ClanRole.Leader)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            var targetPlayer = BasePlayer.Find(targetName);
            if (targetPlayer == null)
            {
                player.ChatMessage(config.Messages.PlayerNotFound);
                return;
            }

            var targetMember = clan.Members.FirstOrDefault(m => m.SteamId == targetPlayer.UserIDString);
            if (targetMember == null)
            {
                player.ChatMessage("Игрок не состоит в вашем клане.");
                return;
            }

            if (targetMember.Role <= ClanRole.Member)
            {
                player.ChatMessage("Игрок уже имеет минимальный ранг.");
                return;
            }

            targetMember.Role = ClanRole.Member;
            SendClanMessage(clan, string.Format(config.Messages.PlayerDemoted, targetPlayer.displayName, "Member"));
        }

        void SetClanBase(BasePlayer player)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var member = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (member == null || member.Role < ClanRole.Officer)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            clan.BaseLocation = player.transform.position;
            player.ChatMessage(config.Messages.BaseSet);
        }

        void TeleportToBase(BasePlayer player)
        {
            if (!config.ClanSettings.AllowClanTeleport)
            {
                player.ChatMessage("Телепортация клана отключена.");
                return;
            }

            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            if (clan.BaseLocation == Vector3.zero)
            {
                player.ChatMessage("База клана не установлена.");
                return;
            }

            player.Teleport(clan.BaseLocation);
            player.ChatMessage(config.Messages.TeleportedToBase);
        }

        void SetClanMotto(BasePlayer player, string motto)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var member = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (member == null || member.Role < ClanRole.Officer)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            clan.Motto = motto;
            player.ChatMessage(string.Format(config.Messages.MottoSet, motto));
        }

        void TransferLeadership(BasePlayer player, string targetName)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var currentLeader = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (currentLeader == null || currentLeader.Role != ClanRole.Leader)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            var targetPlayer = BasePlayer.Find(targetName);
            if (targetPlayer == null)
            {
                player.ChatMessage(config.Messages.PlayerNotFound);
                return;
            }

            var targetMember = clan.Members.FirstOrDefault(m => m.SteamId == targetPlayer.UserIDString);
            if (targetMember == null)
            {
                player.ChatMessage("Игрок не состоит в вашем клане.");
                return;
            }

            currentLeader.Role = ClanRole.Member;
            targetMember.Role = ClanRole.Leader;
            clan.Leader = targetPlayer.UserIDString;

            SendClanMessage(clan, string.Format(config.Messages.LeadershipTransferred, targetPlayer.displayName));
        }
        #endregion

        #region War System
        void HandleWarCommand(BasePlayer player, string action, string targetClan)
        {
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            var member = clan.Members.FirstOrDefault(m => m.SteamId == player.UserIDString);
            if (member == null || member.Role < ClanRole.Officer)
            {
                player.ChatMessage(config.Messages.NoPermission);
                return;
            }

            switch (action.ToLower())
            {
                case "declare":
                    DeclareWar(clan, targetClan, player);
                    break;

                case "accept":
                    AcceptWar(clan, targetClan, player);
                    break;

                default:
                    player.ChatMessage("Использование: /clan war <declare/accept> <клан>");
                    break;
            }
        }

        void DeclareWar(Clan attackerClan, string defenderClanName, BasePlayer player)
        {
            if (!clans.ContainsKey(defenderClanName))
            {
                player.ChatMessage(config.Messages.ClanNotFound);
                return;
            }

            var warKey = $"{attackerClan.Name}_{defenderClanName}";
            if (activeWars.ContainsKey(warKey))
            {
                player.ChatMessage("Война с этим кланом уже активна.");
                return;
            }

            var war = new ClanWar
            {
                AttackerClan = attackerClan.Name,
                DefenderClan = defenderClanName,
                Status = WarStatus.Pending,
                StartTime = DateTime.Now,
                EndTime = DateTime.Now.AddDays(1)
            };

            activeWars[warKey] = war;
            war.Events.Add(new WarEvent
            {
                EventType = "Declaration",
                Description = $"War declared by {attackerClan.Name} on {defenderClanName}"
            });

            SendClanMessage(clans[defenderClanName], string.Format(config.Messages.WarDeclared, attackerClan.Name));
            SendClanMessage(attackerClan, $"Война объявлена клану {defenderClanName}. Ожидание принятия...");
        }

        void AcceptWar(Clan defenderClan, string attackerClanName, BasePlayer player)
        {
            var warKey = $"{attackerClanName}_{defenderClan.Name}";
            if (!activeWars.ContainsKey(warKey))
            {
                player.ChatMessage("Не найдено ожидающих объявлений войны.");
                return;
            }

            var war = activeWars[warKey];
            if (war.Status != WarStatus.Pending)
            {
                player.ChatMessage("Эта война не ожидает принятия.");
                return;
            }

            war.Status = WarStatus.Active;
            war.Events.Add(new WarEvent
            {
                EventType = "Acceptance",
                Description = $"War accepted by {defenderClan.Name}"
            });

            SendClanMessage(clans[attackerClanName], string.Format(config.Messages.WarAccepted, defenderClan.Name));
            SendClanMessage(defenderClan, $"Война с {attackerClanName} теперь активна!");
        }
        #endregion

        #region Extended Features
        void ShowClanStats(BasePlayer player)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            player.ChatMessage($"=== Статистика клана ===");
            player.ChatMessage($"Всего убийств: {clan.Statistics.TotalKills}");
            player.ChatMessage($"Всего смертей: {clan.Statistics.TotalDeaths}");
            player.ChatMessage($"Соотношение У/С: {(clan.Statistics.TotalDeaths > 0 ? (double)clan.Statistics.TotalKills / clan.Statistics.TotalDeaths : clan.Statistics.TotalKills):F2}");
            player.ChatMessage($"Войн выиграно: {clan.Statistics.WarsWon}");
            player.ChatMessage($"Войн проиграно: {clan.Statistics.WarsLost}");
            player.ChatMessage($"Рейдов завершено: {clan.Statistics.RaidsCompleted}");
            player.ChatMessage($"Рейдов отбито: {clan.Statistics.RaidsDefended}");
        }

        void ShowClanAchievements(BasePlayer player)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            if (clan.Achievements.Count == 0)
            {
                player.ChatMessage("У вашего клана пока нет достижений.");
                return;
            }

            player.ChatMessage($"=== Достижения клана ===");
            foreach (var achievement in clan.Achievements)
            {
                player.ChatMessage($"[{achievement.UnlockedDate:MM/dd/yyyy}] {achievement.Name}: {achievement.Description}");
            }
        }

        void AddExperience(Clan clan, int amount)
        {
            clan.Experience += amount;
            var requiredExp = clan.Level * config.ClanSettings.ExperiencePerLevel;

            if (clan.Experience >= requiredExp && clan.Level < config.ClanSettings.MaxClanLevel)
            {
                clan.Level++;
                clan.Experience -= requiredExp;
                clan.MaxMembers += 2;

                SendClanMessage(clan, $"🎉 Clan has reached level {clan.Level}! Max members increased to {clan.MaxMembers}.");

                CheckLevelAchievements(clan);
            }
        }

        void CheckLevelAchievements(Clan clan)
        {
            var achievements = new List<ClanAchievement>();

            if (clan.Level >= 5 && !clan.Achievements.Any(a => a.Name == "Veteran Clan"))
            {
                achievements.Add(new ClanAchievement
                {
                    Name = "Veteran Clan",
                    Description = "Reached level 5"
                });
            }

            if (clan.Level >= 10 && !clan.Achievements.Any(a => a.Name == "Elite Clan"))
            {
                achievements.Add(new ClanAchievement
                {
                    Name = "Elite Clan",
                    Description = "Reached maximum level"
                });
            }

            if (clan.Statistics.TotalKills >= 1000 && !clan.Achievements.Any(a => a.Name == "Bloodthirsty"))
            {
                achievements.Add(new ClanAchievement
                {
                    Name = "Bloodthirsty",
                    Description = "Achieved 1000 total kills"
                });
            }

            foreach (var achievement in achievements)
            {
                clan.Achievements.Add(achievement);
                SendClanMessage(clan, $"🏆 Achievement Unlocked: {achievement.Name} - {achievement.Description}");
            }
        }
        #endregion

        #region Admin Commands
        void DeleteClan(BasePlayer player, string clanName)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            if (!clans.ContainsKey(clanName))
            {
                player.ChatMessage(config.Messages.ClanNotFound);
                return;
            }

            var clan = clans[clanName];
            foreach (var member in clan.Members)
            {
                playerClans.Remove(member.SteamId);
            }

            clans.Remove(clanName);
            player.ChatMessage($"Clan '{clanName}' has been deleted.");
        }

        void ListAllClans(BasePlayer player)
        {
            if (clans.Count == 0)
            {
                player.ChatMessage("Кланов не существует.");
                return;
            }

            player.ChatMessage("=== Все кланы ===");
            foreach (var clan in clans.Values)
            {
                player.ChatMessage($"{clan.Name} [{clan.Tag}] - Level {clan.Level} - {clan.Members.Count}/{clan.MaxMembers} members");
            }
        }

        void ShowAdminClanInfo(BasePlayer player, string clanName)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            if (!clans.ContainsKey(clanName))
            {
                player.ChatMessage(config.Messages.ClanNotFound);
                return;
            }

            var clan = clans[clanName];
            player.ChatMessage($"=== Информация о клане: {clan.Name} ===");
            player.ChatMessage($"Тег: [{clan.Tag}]");
            player.ChatMessage($"Уровень: {clan.Level}");
            player.ChatMessage($"Опыт: {clan.Experience}/{clan.Level * config.ClanSettings.ExperiencePerLevel}");
            player.ChatMessage($"Участники: {clan.Members.Count}/{clan.MaxMembers}");
            player.ChatMessage($"Создан: {clan.CreatedDate:dd.MM.yyyy}");
            player.ChatMessage($"Девиз: {clan.Motto}");
            player.ChatMessage($"Лидер: {clan.Leader}");
        }
        #endregion

        #region Utility Methods
        Clan GetPlayerClan(ulong playerId)
        {
            var playerIdString = playerId.ToString();
            if (!playerClans.ContainsKey(playerIdString))
                return null;

            var clanName = playerClans[playerIdString];
            return clans.ContainsKey(clanName) ? clans[clanName] : null;
        }

        void SendClanMessage(Clan clan, string message)
        {
            foreach (var member in clan.Members)
            {
                if (ulong.TryParse(member.SteamId, out ulong steamId))
                {
                    var player = BasePlayer.FindByID(steamId);
                    if (player != null)
                    {
                        player.ChatMessage($"[{clan.Tag}] {message}");
                    }
                }
            }
        }

        void ShowClanHelp(BasePlayer player)
        {
            player.ChatMessage("=== Команды системы кланов ===");
            player.ChatMessage("/clan create <название> <тег> - Создать новый клан");
            player.ChatMessage("/clan invite <игрок> - Пригласить игрока в клан");
            player.ChatMessage("/clan accept - Принять приглашение в клан");
            player.ChatMessage("/clan leave - Покинуть текущий клан");
            player.ChatMessage("/clan kick <игрок> - Исключить игрока из клана");
            player.ChatMessage("/clan promote <игрок> - Повысить игрока до Офицера");
            player.ChatMessage("/clan demote <игрок> - Понизить игрока до Участника");
            player.ChatMessage("/clan base - Установить местоположение базы клана");
            player.ChatMessage("/clan tp - Телепортироваться на базу клана");
            player.ChatMessage("/clan info - Показать информацию о клане");
            player.ChatMessage("/clan members - Показать участников клана");
            player.ChatMessage("/clan war <declare/accept> <клан> - Управление войнами кланов");
            player.ChatMessage("/clan motto <текст> - Установить девиз клана");
            player.ChatMessage("/clan transfer <игрок> - Передать лидерство");
            player.ChatMessage("/clan stats - Показать статистику клана");
            player.ChatMessage("/clan achievements - Показать достижения клана");
            player.ChatMessage("/c <сообщение> - Отправить сообщение в чат клана");
        }

        void ShowClanInfo(BasePlayer player)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            player.ChatMessage($"=== Клан: {clan.Name} [{clan.Tag}] ===");
            player.ChatMessage($"Уровень: {clan.Level}");
            player.ChatMessage($"Опыт: {clan.Experience}/{clan.Level * config.ClanSettings.ExperiencePerLevel}");
            player.ChatMessage($"Участники: {clan.Members.Count}/{clan.MaxMembers}");
            player.ChatMessage($"Лидер: {clan.Leader}");
            if (!string.IsNullOrEmpty(clan.Motto))
            {
                player.ChatMessage($"Девиз: {clan.Motto}");
            }
            player.ChatMessage($"Создан: {clan.CreatedDate:dd.MM.yyyy}");
        }

        void ShowClanMembers(BasePlayer player)
        {
            if (config?.Messages == null)
            {
                player.ChatMessage("Система кланов не настроена.");
                return;
            }
            
            var clan = GetPlayerClan(player.userID);
            if (clan == null)
            {
                player.ChatMessage(config.Messages.NotInClan);
                return;
            }

            player.ChatMessage($"=== Участники клана: {clan.Name} ===");
            foreach (var member in clan.Members.OrderByDescending(m => m.Role))
            {
                var roleText = member.Role switch
                {
                    ClanRole.Leader => "Лидер",
                    ClanRole.Officer => "Офицер",
                    ClanRole.Member => "Участник",
                    _ => "Неизвестно"
                };

                player.ChatMessage($"{member.Name} - {roleText} (У/С: {member.Kills}/{member.Deaths})");
            }
        }
        #endregion
    }
} 