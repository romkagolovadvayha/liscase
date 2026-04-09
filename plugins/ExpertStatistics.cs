using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries.Covalence;
using Oxide.Core.Plugins;
using Rust;
using System;
using System.Collections;
using System.Collections.Generic;
using Rust.Ai.Gen2;

using Oxide.Core.Libraries;
using System.Linq;
using Oxide.Game.Rust.Cui;
using UnityEngine.Networking;
using Network;
using Time = UnityEngine.Time;
using UnityEngine;
using Object = System.Object;
using System.Collections;
using ConVar;
using Net = Network.Net;
using System.Text.RegularExpressions;
using Newtonsoft.Json.Linq;

namespace Oxide.Plugins
{
    [Info("Expert Statistics", "prostoj.store", "1.0.9")]
    [Description("Плагин, синхронизирует статистику игроков с сайтом.")]
    public class ExpertStatistics : CovalencePlugin
    {
        #region Config
        static Configuration config;
        public class Configuration
        {
            [JsonProperty(PropertyName = "Server Tag")] public string server_tag;
            [JsonProperty(PropertyName = "API Base URL")] public string api_base_url;

            public static Configuration DefaultConfig()
            {
                return new Configuration
                {
                    server_tag = "pve",
                    api_base_url = "https://api.moscow77.store"
                };
            }
        }

        /// <summary>true — запросы не отправляются, вывод в консоль и в чат (LogHookEvent). false — запросы отправляются, вывод отключён.</summary>
        public bool Debug = false;
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
                LoadDefaultConfig();
                if (Debug)
                    PrintWarning("Создание нового файла конфигурации.");
            }
        }
        protected override void LoadDefaultConfig() => config = Configuration.DefaultConfig();
        protected override void SaveConfig() => Config.WriteObject(config);

        private const Boolean LanguageEn = false;

        private void LoadConfigFromAPI()
        {
            try
            {
                // Получаем IP и порт сервера
                String serverIp = ConVar.Server.ip;
                Int32 serverPort = ConVar.Server.port;
                String pluginName = Name; // "ExpertStatistics"
                String baseUrl = !String.IsNullOrEmpty(config.api_base_url) ? config.api_base_url.TrimEnd('/') : "https://api.moscow77.store";
                String apiUrl = $"{baseUrl}/rust-plugin-config/get?ip={serverIp}&port={serverPort}&name={pluginName}";

                if (Debug)
                {
                    Puts($"[ExpertStatistics] DEBUG GET (request not sent): {apiUrl}");
                    return;
                }

                webrequest.Enqueue(apiUrl, null, (code, response) =>
                {
                    if (code == 200 && !String.IsNullOrEmpty(response))
                    {
                        try
                        {
                            // Парсим ответ API
                            JObject apiResponse = JObject.Parse(response);
                            JToken contentToken = apiResponse["content"];

                            if (contentToken != null)
                            {
                                // Десериализуем content в Configuration
                                Configuration apiConfig = contentToken.ToObject<Configuration>();

                                if (apiConfig != null)
                                {
                                    config = apiConfig;
                                    NextTick(SaveConfig);
                                    return;
                                }
                            }
                        }
                        catch (Exception ex)
                        {
                            if (Debug)
                                PrintError(LanguageEn
                                    ? $"Error parsing API response: {ex.Message}. Using default config."
                                    : $"Ошибка парсинга ответа API: {ex.Message}. Используется конфиг по умолчанию.");
                        }
                    }
                    else if (Debug)
                    {
                        PrintWarning(LanguageEn
                            ? $"Failed to load config from API (Code: {code}). Using default config."
                            : $"Не удалось загрузить конфиг из API (Код: {code}). Используется конфиг по умолчанию.");
                    }
                }, this, RequestMethod.GET, null, 10f);
            }
            catch (Exception ex)
            {
                if (Debug)
                    PrintError(LanguageEn
                        ? $"Error loading config from API: {ex.Message}. Using default config."
                        : $"Ошибка загрузки конфига из API: {ex.Message}. Используется конфиг по умолчанию.");
            }
        }

        public class Kill
        {
            public string steam_id = "";
            public string type = "";
            public string dead = "";
            public List<string> inventoryWear = new List<string>();
            public List<string> signs = new List<string>();
            public string weapon = "";
            public int distance = 0;
            public string date = "";
        };
        public class Team
        {
            public string steam_id = "";
            public string type = "";
            public string team_author = "";
            public string created_at = "";
        };
        public class Report
        {
            public string steam_id = "";
            public string recepient_steam_id = "";
            public string reason = "";
            public string created_at = "";
        };
        public class Chat
        {
            public string steam_id = "";
            public string message = "";
            public string created_at = "";
        };

        public class KillsData
        {
            public List<Kill> Kills = new List<Kill>();
        }
        public class TeamsData
        {
            public List<Team> Teams = new List<Team>();
        }
        public class ReportsData
        {
            public List<Report> Reports = new List<Report>();
        }
        public class ChatsData
        {
            public List<Chat> Chats = new List<Chat>();
        }
        #endregion

        #region Init
        Dictionary<string, int> _item;
        DamageType[] suicideDamageTypes = { DamageType.Suicide, DamageType.Radiation, DamageType.RadiationExposure, DamageType.Poison, DamageType.Hunger, DamageType.Thirst, DamageType.Fall, DamageType.Drowned };
        Dictionary<string, Dictionary<string, int>> list = new Dictionary<string, Dictionary<string, int>>();
        List<string> disconnects = new List<string>();
		KillsData killsData = new KillsData();
		TeamsData teamsData = new TeamsData();
		ReportsData reportsData = new ReportsData();
		ChatsData chatsData = new ChatsData();
        Dictionary<ulong, List<ulong>> teams = new Dictionary<ulong, List<ulong>>();
        // Уникальные луты: один и тот же крейт засчитывается игроку только один раз (entity+player)
        private readonly Dictionary<ulong, HashSet<string>> _uniqueLootCounted = new Dictionary<ulong, HashSet<string>>();
        private readonly object _uniqueLootLock = new object();
        // basicblueprintfragment: один раз за 5 мин на игрока
        private readonly HashSet<string> _countedBasicBlueprintFragment = new HashSet<string>();
        /// <summary>PatrolHelicopter: netId → последний Steam-игрок, нанёсший урон (смерть часто приходит с пустым InitiatorPlayer).</summary>
        private readonly Dictionary<ulong, ulong> _patrolHeliLastSteamDamager = new Dictionary<ulong, ulong>();
        private readonly object _patrolHeliDamagerLock = new object();

        void OnServerInitialized(bool initial)
        {
            if (Debug)
                Puts("[Stats] OnServerInitialized | initial=" + initial);

            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();

            timer.Every(1 * 60, () =>
            {
                SaveAllStats();
            });
            timer.Every(5 * 60, () =>
            {
                lock (_uniqueLootLock) { _uniqueLootCounted.Clear(); }
                _countedBasicBlueprintFragment.Clear();
            });
        }
        void Unload()
        {
            killsData.Kills.Clear();
            disconnects.Clear();
            list.Clear();
            lock (_patrolHeliDamagerLock) { _patrolHeliLastSteamDamager.Clear(); }
            SaveAllStats();
        }
        #endregion

        #region Save
        void SaveAllStats()
        {
            string requestBody = JsonConvert.SerializeObject(
                new {
                    users = list,
                    kills = killsData.Kills,
                    teams = teamsData.Teams,
                    reports = reportsData.Reports,
                    chats = chatsData.Chats,
                    server = new {
                        online = BasePlayer.activePlayerList.Count,
                        join = ServerMgr.Instance.connectionQueue.Joining,
                        queue = ServerMgr.Instance.connectionQueue.Queued
                    }
                }).Replace("\n", "").Replace("  ", "");
            String statsBaseUrl = !String.IsNullOrEmpty(config.api_base_url) ? config.api_base_url.TrimEnd('/') : "https://api.moscow77.store";
            string statsUrl = $"{statsBaseUrl}/stats/update?serverTag={config.server_tag}";
            if (Debug)
            {
                Puts($"[ExpertStatistics] DEBUG POST (request not sent): {statsUrl}");
                Puts(requestBody);
                list.Clear();
                chatsData.Chats.Clear();
                reportsData.Reports.Clear();
                teamsData.Teams.Clear();
                killsData.Kills.Clear();
                teams.Clear();
                disconnects.Clear();
                return;
            }
            Dictionary<string, string> header = new Dictionary<string, string>();
            header.Add("Content-Type", "application/json");
            webrequest.Enqueue(statsUrl, requestBody, (code, response) =>
            {
                if (code >= 200 && code < 300)
                {
                    list.Clear();
                    chatsData.Chats.Clear();
                    reportsData.Reports.Clear();
                    teamsData.Teams.Clear();
                    killsData.Kills.Clear();
                    teams.Clear();
                    disconnects.Clear();
                }
            }, this, RequestMethod.POST, header, timeout: 1F);
        }

        [Command("stats.save")]
        private void SaveStatsCMD(IPlayer iPlayer, string command, string[] args)
        {
            if (Debug)
                Puts("Expert Statistics: SaveStatsCMD.");
            if (!iPlayer.IsServer) return;
            SaveAllStats();
        }

        // [Command("stats.team.save")]
        // private void SaveStatsTeamCMD(IPlayer iPlayer, string command, string[] args)
        // {
        //     if (!iPlayer.IsServer) return;
        //     Puts("Expert Statistics: SaveStatsTeamCMD.");
        //     foreach (var player in BasePlayer.activePlayerList.ToList()) {
        //         if (player.Team != null && !teams.ContainsKey(player.Team.teamLeader))
        //         {
        //             teams.Add(player.Team.teamLeader, player.Team.members);
        //         }
        //     }
        //     string requestBody = JsonConvert.SerializeObject(
        //         new {
        //             teams = teams
        //         }).Replace("\n", "").Replace("  ", "");
        //     Puts(requestBody);
        // }
        #endregion

        #region Functions
        private void ExplosionProgressAdd(BasePlayer player, BaseEntity entity, string shortname = "")
        {
            string WeaponName = string.IsNullOrWhiteSpace(shortname) == true ? string.Empty : shortname;
            if (entity != null && !string.IsNullOrEmpty(entity.ShortPrefabName))
                addParametr(player.UserIDString, entity.ShortPrefabName, 1);
            if (!string.IsNullOrEmpty(WeaponName))
            {
                switch (WeaponName)
                {
                    // разрывной патрон
                    case "ammo.rifle.explosive":
                        addParametr(player.UserIDString, "ammo_explosive", 1);
                        break;
                }
            }
        }
        void addParametr(string steamId, string parametr, int count)
        {
            if (string.IsNullOrEmpty(steamId)) return;
            if (count <= 0) return;
            if (string.IsNullOrEmpty(parametr)) parametr = "unknown";
            if (!list.ContainsKey(steamId))
                list.Add(steamId, new Dictionary<string, int>());
            if (!list[steamId].ContainsKey(parametr))
                list[steamId][parametr] = 0;
            list[steamId][parametr] += count;
        }

        /// <summary>Игрок-источник урона из сущности (турель, SAM, снаряд с creatorEntity и т.д.).</summary>
        private static BasePlayer TryPlayerFromDamageEntity(BaseEntity ent, int depth = 0)
        {
            if (ent == null || depth > 5) return null;
            if (ent is BasePlayer bp && bp.userID.IsSteamId()) return bp;
            if (ent is HeldEntity held)
            {
                BasePlayer op = held.GetOwnerPlayer();
                if (op != null && op.userID.IsSteamId()) return op;
            }
            if (ent is NPCAutoTurret) return null;
            if (ent is AutoTurret at && at.OwnerID.IsSteamId())
                return BasePlayer.FindByID(at.OwnerID);
            if (ent is SamSite ss && ss.OwnerID.IsSteamId())
                return BasePlayer.FindByID(ss.OwnerID);
            if (ent is FlameTurret ft && ft.OwnerID.IsSteamId())
                return BasePlayer.FindByID(ft.OwnerID);
            if (ent is GunTrap gt && gt.OwnerID.IsSteamId())
                return BasePlayer.FindByID(gt.OwnerID);
            BaseEntity cr = ent.creatorEntity;
            if (cr != null && !ReferenceEquals(cr, ent))
                return TryPlayerFromDamageEntity(cr, depth + 1);
            return null;
        }

        private static BasePlayer TryResolveAttackerFromHitInfo(HitInfo info)
        {
            if (info == null) return null;
            if (info.InitiatorPlayer != null && info.InitiatorPlayer.userID.IsSteamId())
                return info.InitiatorPlayer;
            BasePlayer fromChain =
                TryPlayerFromDamageEntity(info.Initiator)
                ?? TryPlayerFromDamageEntity(info.WeaponPrefab)
                ?? TryPlayerFromDamageEntity(info.Weapon as BaseEntity);
            if (fromChain != null) return fromChain;
            return null;
        }

        /// <summary>
        /// Bradley/Heli: InitiatorPlayer часто пуст (турели/SAM/цепочка снаряда); добираем игрока из Initiator/creatorEntity и lastAttacker.
        /// </summary>
        private static BasePlayer ResolvePlayerCreditForNpcVehicleKill(HitInfo info, BaseCombatEntity victim)
        {
            BasePlayer p = TryResolveAttackerFromHitInfo(info);
            if (p != null) return p;
            if (victim != null && victim.lastAttacker != null)
            {
                if (victim.lastAttacker is BasePlayer lp && lp.userID.IsSteamId())
                    return lp;
                p = TryPlayerFromDamageEntity(victim.lastAttacker);
                if (p != null) return p;
            }
            return null;
        }

        /// <summary>При Debug: вывод в консоль; в чат — только если игрок админ. При Debug выключен — ничего не выводится.</summary>
        private void LogHookEvent(BasePlayer player, string hookName, string details = null)
        {
            if (!Debug) return;
            string msg = $"[Stats] {hookName}" + (string.IsNullOrEmpty(details) ? "" : $" | {details}");
            Puts(msg);
            if (player != null && player.IsAdmin)
                player.ChatMessage(msg);
        }

        #endregion

        #region Hooks
        // void OnPlayerConnected(BasePlayer player)
        // {
        //     if (!list.ContainsKey(player.UserIDString))
        //     {
        //         list.Add(player.UserIDString, new Dictionary<string, int>());
        //     }
        //     if (disconnects.IndexOf(player.UserIDString) > -1)
        //     {
        //         disconnects.Remove(player.UserIDString);
        //     }
		// }
        // void OnPlayerDisconnected(BasePlayer player, string reason)
        // {
        //    disconnects.Add(player.UserIDString);
		// }
		// void OnClientDisconnect(Network.Connection connection, string reason)
		// {
        //    disconnects.Add(connection.userid.ToString());
		// }
		void OnPlayerChat(BasePlayer player, string message, ConVar.Chat.ChatChannel channel)
		{
			LogHookEvent(player, "OnPlayerChat", $"channel={channel} msg={message}");
			if (channel == ConVar.Chat.ChatChannel.Global) {
				Chat model = new Chat();
				model.steam_id = player.UserIDString;
				model.message = message;
				model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
				chatsData.Chats.Add(model);
			}
		}
        private void OnPlayerReported(BasePlayer reporter, string targetName, string targetId, string subject, string message, string type)
		{
			LogHookEvent(reporter, "OnPlayerReported", $"type={type} target={targetId}");
			if (!type.Equals("cheat")) return;
            Report model = new Report();
            model.steam_id = reporter.UserIDString;
            model.recepient_steam_id = targetId.ToString();
            model.reason = message;
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            reportsData.Reports.Add(model);
		}
		void OnCardSwipe(CardReader cardReader, Keycard card, BasePlayer player)
		{
			LogHookEvent(player, "OnCardSwipe", $"accessLevel={card?.accessLevel}");
            switch (card.accessLevel)
            {
                case 1:
                    addParametr(player.UserIDString, "card_level_1", 1);
                    break;
                case 2:
                    addParametr(player.UserIDString, "card_level_2", 1);
                    break;
                case 3:
                    addParametr(player.UserIDString, "card_level_3", 1);
                    break;
            }
		}
		void OnCupboardAuthorize(BuildingPrivlidge privilege, BasePlayer player)
		{
			LogHookEvent(player, "OnCupboardAuthorize", "");
			if (privilege == null || player == null || !player.userID.IsSteamId()) return;
			addParametr(player.UserIDString, "cupboard_authorized", 1);
		}
		void OnPlayerAttack(BasePlayer attacker, HitInfo info)
        {
			LogHookEvent(attacker, "OnPlayerAttack", $"bone={info?.boneName} target={info?.HitEntity?.ShortPrefabName}");
			BasePlayer player = info.HitEntity?.ToPlayer();
            if (player != null && !player.IsNpc)
            {
                switch (info.boneName.ToLower())
                {
                    case "head":
						addParametr(attacker.UserIDString, "hits_head", 1);
                        break;
                    case "neck":
						addParametr(attacker.UserIDString, "hits_neck", 1);
                        break;
                    case "chest":
						addParametr(attacker.UserIDString, "hits_chest", 1);
                        break;
                    case "lowerspine":
						addParametr(attacker.UserIDString, "hits_lowerspine", 1);
                        break;
                    case "pelvis":
                    case "hip":
						addParametr(attacker.UserIDString, "hits_leftleg", 1);
						addParametr(attacker.UserIDString, "hits_rightleg", 1);
                        break;
                    case "left knee":
						addParametr(attacker.UserIDString, "hits_leftleg", 1);
                        break;
                    case "right knee":
						addParametr(attacker.UserIDString, "hits_rightleg", 1);
                        break;
                    case "left foot":
						addParametr(attacker.UserIDString, "hits_leftfoot", 1);
                        break;
                    case "right foot":
						addParametr(attacker.UserIDString, "hits_rightfoot", 1);
                        break;
                    case "left arm":
                    case "left forearm":
                    case "left hand":
						addParametr(attacker.UserIDString, "hits_lefthand", 1);
                        break;
                    case "right arm":
                    case "right forearm":
                    case "right hand":
						addParametr(attacker.UserIDString, "hits_righthand", 1);
                        break;
                }
            }
        }
        void OnHealingItemUse(MedicalTool tool, BasePlayer player)
		{
			LogHookEvent(player, "OnHealingItemUse", $"tool={tool?.ShortPrefabName}");
            if (player.IsNpc) {
                return;
            }
            switch (tool.ShortPrefabName)
            {
                case "syringe_medical.entity":
                    addParametr(player.UserIDString, "syringe", 1);
                    break;
                case "largemedkit":
                    addParametr(player.UserIDString, "first_aid_kit", 1);
                    break;
                case "bandage.entity":
                    addParametr(player.UserIDString, "bandage", 1);
                    break;
            }
		}

		void OnPlayerAddModifiers(BasePlayer player, Item item, ItemModConsumable consumable)
		{
			LogHookEvent(player, "OnPlayerAddModifiers", $"item={item?.info?.shortname}");
            if (player.IsNpc) {
                return;
            }
            addParametr(player.UserIDString, "mod_" + item.info.shortname, 1);
		}

        void OnFishCatch(Item item, BaseFishingRod rod, BasePlayer player)
        {
			LogHookEvent(player, "OnFishCatch", $"item={item?.info?.shortname} amount={item?.amount}");
            addParametr(player.UserIDString, "f_" + item.info.shortname, item.amount);
        }

        void OnEntityDeath(BasePlayer player, HitInfo info)
        {
			LogHookEvent(info?.InitiatorPlayer ?? player, "OnEntityDeath(BasePlayer)", $"victim={player?.displayName} weapon={info?.Weapon?.GetItem()?.info?.shortname}");
            BasePlayer attacker = info?.InitiatorPlayer;
			if (player == null || info == null) return;
            if (!player.userID.IsSteamId()) return;
            Kill kill = new Kill();
            if (!suicideDamageTypes.Contains(player.lastDamage))
            {
                if (info?.Initiator is BaseNpc)
                {
                    kill.steam_id = player.UserIDString;
                    kill.type = "deaths";
                    kill.dead = info?.Initiator.ShortPrefabName ?? "unknown";
                    kill.distance = (int)info.ProjectileDistance;
                    kill.date = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                    killsData.Kills.Add(kill);
                } else {
                    if (attacker != null) {
                        addParametr(player.UserIDString, "deaths", 1);
                    }
                }
            }

            if (attacker == null || attacker == player || attacker.IsNpc)
            {
                return;
            }

            /*foreach (var item in player.inventory.containerBelt.itemList)
            {
				kill.inventoryWear.Add(item.info.shortname);
            }*/
			if (player.inventory?.containerMain?.itemList != null) {
				foreach (var item in player.inventory.containerMain.itemList)
				{
					kill.inventoryWear.Add(item.info.shortname);
				}
			}
			if (player.inventory?.containerWear?.itemList != null) {
				foreach (var item in player.inventory.containerWear.itemList) {
					kill.inventoryWear.Add(item.info.shortname);
				}
			}
            if (player.IsSleeping()) {
				kill.signs.Add("sleep");
            }
            // Только если оба в команде и это одна и та же команда (currentTeam == 0 = не в команде)
            if (attacker.currentTeam != 0 && attacker.currentTeam == player.currentTeam) {
				kill.signs.Add("team");
            }
            Item weaponItem = info.Weapon?.GetItem();
            kill.steam_id = attacker.UserIDString;
            kill.type = "kill";
            kill.dead = player.UserIDString;
            kill.distance = (int)info.ProjectileDistance;
            kill.date = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            if (weaponItem != null)
            {
                kill.weapon = weaponItem.info.shortname;
				if (kill.signs.Count == 0) {
					addParametr(attacker.UserIDString, weaponItem.info.shortname, 1);
				}
            }

            killsData.Kills.Add(kill);

			if (kill.signs.Count == 0) {
				addParametr(attacker.UserIDString, "kills", 1);
			}
        }
        void OnEntityDeath(BuildingPrivlidge entity, HitInfo info)
        {
			LogHookEvent(info?.InitiatorPlayer, "OnEntityDeath(BuildingPrivlidge)", "TC");
            if (entity == null || info == null) return;
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;

            addParametr(attacker.UserIDString, "tcsdestroyed", 1);
        }

        // Убийства ботов (NPCPlayer) обрабатываются в OnEntityDeath(BaseCombatEntity), т.к. в новых версиях Oxide вызывается только он.

        // Один хук на все смерти сущностей (животные, акула, змеи, боты) — Oxide может вызывать только BaseCombatEntity
        void OnEntityDeath(BaseCombatEntity entity, HitInfo info)
        {
            if (entity == null || info == null) return;
            if (Debug) Puts($"[Stats] OnEntityDeath(BaseCombatEntity) entity={entity.ShortPrefabName}");

            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;

            string prefab = entity.ShortPrefabName ?? "";

            // Боты (учёные, рейдеры и т.д.) — засчитываем убийство; иначе в новых версиях Oxide может не вызваться OnEntityDeath(NPCPlayer)
            if (entity is NPCPlayer)
            {
                addParametr(attacker.UserIDString, "scientists", 1);
                return;
            }

            bool isAnimal = entity is BaseAnimalNPC || entity is BaseNPC2 || entity is SimpleShark || prefab.Contains("snake");
            if (!isAnimal) return;

            Kill kill = new Kill();
            kill.steam_id = attacker.UserIDString;
            kill.type = "animal";
            kill.dead = prefab;
            kill.distance = (int)info.ProjectileDistance;
            kill.date = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            Item weaponItem = info.Weapon?.GetItem();
            if (weaponItem != null)
                kill.weapon = weaponItem.info.shortname;
            killsData.Kills.Add(kill);
            addParametr(attacker.UserIDString, entity.ShortPrefabName, 1);
        }

        void OnGrowableGathered(GrowableEntity plant, Item item, BasePlayer player)
        {
            if (Debug) Puts($"[Stats] OnGrowableGathered item={item?.info?.shortname}");
            if (player == null || item?.info == null) return;
            addParametr(player.UserIDString, "gathered_" + item.info.shortname, item.amount);
        }

        void OnDispenserBonus(ResourceDispenser dispenser, BasePlayer player, Item item)
        {
            if (Debug) Puts($"[Stats] OnDispenserBonus item={item?.info?.shortname}");
            if (player == null || item?.info == null) return;
            addParametr(player.UserIDString, item.info.shortname, item.amount);
        }

        void OnDispenserGather(ResourceDispenser dispenser, BaseEntity entity, Item item)
        {
            if (Debug) Puts($"[Stats] OnDispenserGather entity={entity?.ShortPrefabName} item={item?.info?.shortname}");
            BasePlayer player = entity?.ToPlayer();
            if (player == null || item?.info == null) return;
            addParametr(player.UserIDString, item.info.shortname, item.amount);
        }

        // Поднятие ресурсов с земли — дерево, сера, камни, металл пишем в gathered_ как при добыче
        private static readonly HashSet<string> GatheredCollectibles = new HashSet<string> { "wood", "stones", "sulfur.ore", "metal.ore" };

        void OnCollectiblePickedup(CollectibleEntity instance, BasePlayer reciever, Item component)
        {
            if (reciever == null || reciever.IsNpc || !reciever.userID.IsSteamId() || component?.info == null) return;
            string shortname = component.info.shortname;
            LogHookEvent(reciever, "OnCollectiblePickedup", $"item={shortname} amount={component.amount}");
            string key;
            if (shortname == "diesel_barrel") {
                key = "diesel_barrel";
                addParametr(reciever.UserIDString, key, component.amount);
            } else if (GatheredCollectibles.Contains(shortname)) {
                key = "gathered_" + shortname;
                addParametr(reciever.UserIDString, key, component.amount);
            }
        }

        // Подбор предметов с земли (world item)
        // void OnItemPickup(Item item, BasePlayer player, WorldItem worldItem)
        // {
        //     if (!Debug) return;
        //     if (player == null || player.IsNpc || !player.userID.IsSteamId() || item?.info == null) return;
        //     LogHookEvent(player, "OnItemPickup", $"item={item.info?.shortname} amount={item.amount}");
        //     if (item.info.shortname == "basicblueprintfragment")
        //     {
        //         lock (_uniqueLootLock)
        //         {
        //             if (_countedBasicBlueprintFragment.Add(player.UserIDString))
        //                 addParametr(player.UserIDString, "basicblueprintfragment", item.amount);
        //         }
        //     }
        //     else
        //     {
        //         addParametr(player.UserIDString, "pickup_item_" + item.info.shortname, item.amount);
        //     }
        // }

        /// <summary>Копим последнего Steam-наносителя урона по сетевому id — на смерти heli HitInfo часто «пустой».</summary>
        void OnEntityTakeDamage(PatrolHelicopter heli, HitInfo info)
        {
            if (heli?.net == null || info == null) return;
            BasePlayer attacker = TryResolveAttackerFromHitInfo(info);
            if (attacker == null && heli is BaseCombatEntity bce && bce.lastAttacker != null)
            {
                if (bce.lastAttacker is BasePlayer lp && lp.userID.IsSteamId())
                    attacker = lp;
                else
                    attacker = TryPlayerFromDamageEntity(bce.lastAttacker);
            }
            if (attacker != null && attacker.userID.IsSteamId())
            {
                lock (_patrolHeliDamagerLock)
                    _patrolHeliLastSteamDamager[heli.net.ID.Value] = attacker.userID;
            }
        }

        void OnEntityDeath(BradleyAPC bradley, HitInfo info)
        {
            if (bradley == null) return;
            BasePlayer player = ResolvePlayerCreditForNpcVehicleKill(info, bradley);
            string who = player != null ? $"{player.displayName}({player.UserIDString})" : "null";
            LogHookEvent(player, "OnEntityDeath(BradleyAPC)", $"bradley | killer={who}");
            if (player != null)
                addParametr(player.UserIDString, "bradleys", 1);
        }

        // Взрыв вертолёта
        void OnEntityDeath(PatrolHelicopter helicopter, HitInfo info)
        {
            if (helicopter == null) return;
            ulong netId = helicopter.net?.ID.Value ?? 0;

            BasePlayer player = ResolvePlayerCreditForNpcVehicleKill(info, helicopter);
            if (player == null && netId != 0)
            {
                ulong uid = 0;
                lock (_patrolHeliDamagerLock)
                    _patrolHeliLastSteamDamager.TryGetValue(netId, out uid);
                if (uid != 0 && uid.IsSteamId())
                {
                    player = BasePlayer.FindByID(uid);
                    if (player == null)
                    {
                        addParametr(uid.ToString(), "helicopters", 1);
                        LogHookEvent(null, "OnEntityDeath(PatrolHelicopter)", $"heli | killerSteam={uid} (damage cache, offline)");
                        lock (_patrolHeliDamagerLock) { _patrolHeliLastSteamDamager.Remove(netId); }
                        return;
                    }
                }
            }

            if (netId != 0)
            {
                lock (_patrolHeliDamagerLock)
                    _patrolHeliLastSteamDamager.Remove(netId);
            }

            string detail = player != null ? $"killer={player.displayName}({player.UserIDString})" : "killer=null";
            LogHookEvent(player, "OnEntityDeath(PatrolHelicopter)", $"heli | {detail}");
            if (player != null)
                addParametr(player.UserIDString, "helicopters", 1);
        }

        void OnExplosiveThrown(BasePlayer player, BaseEntity entity, ThrownWeapon item)
        {
			LogHookEvent(player, "OnExplosiveThrown", $"item={player?.GetActiveItem()?.info?.shortname}");
            if (player == null || item == null)
                return;

            ExplosionProgressAdd(player, entity);
            switch (player.GetActiveItem()?.info.shortname)
            {
                case "explosive.timed":
                    if (player.GetActiveItem()?.flags.ToString() != "IsOn") {
                        addParametr(player.UserIDString, "c4thrown", 1);
                    }
                    break;
                case "explosive.satchel":
                    addParametr(player.UserIDString, "satchelsthrown", 1);
                    break;
                case "supply.signal":
                    addParametr(player.UserIDString, "grenade.supplysignal.deployed", 1);
                    break;
            }
        }

        void OnEntityDismounted(BaseMountable mountable, BasePlayer player)
        {
            if (mountable == null || player == null || player.IsNpc || !player.userID.IsSteamId()) return;
            if (mountable.ShortPrefabName == "parachuteseat")
                addParametr(player.UserIDString, "parachuteseat", 1);
        }

        void OnRocketLaunched(BasePlayer player, BaseEntity entity)
        {
			LogHookEvent(player, "OnRocketLaunched", $"entity={entity?.ShortPrefabName}");
            if (player == null || entity == null)
                return;
            ExplosionProgressAdd(player, entity);
        }

        void OnPlayerWound(BasePlayer player, HitInfo hitInfo)
        {
            if (player == null || player.IsNpc || !player.userID.IsSteamId()) return;
            addParametr(player.UserIDString, "wounded", 1);
        }

        void OnWeaponFired(BaseProjectile projectile, BasePlayer player, ItemModProjectile mod, ProtoBuf.ProjectileShoot projectiles)
        {
			LogHookEvent(player, "OnWeaponFired", $"projectile={projectile?.ShortPrefabName} ammo={projectile?.primaryMagazine?.ammoType?.shortname}");
            if (projectile == null || player == null)
                return;

            ExplosionProgressAdd(player, null, projectile.primaryMagazine?.ammoType?.shortname);
        }

        // Строительство (постановка блока/объекта)
        void OnEntityBuilt(Planner plan, GameObject result)
        {
            if (plan == null || result == null) return;
            BasePlayer player = plan.GetOwnerPlayer();
            if (player == null || player.IsNpc || !player.userID.IsSteamId()) return;
            BuildingBlock block = result?.ToBaseEntity()?.GetComponent<BuildingBlock>();
            string blockInfo = block != null ? block.ShortPrefabName : result.name;
            LogHookEvent(player, "OnEntityBuilt", $"block={blockInfo}");
            addParametr(player.UserIDString, "built", 1);
        }

        // Апгрейд постройки — отдельный ключ на каждый грейд
        void OnStructureUpgrade(BuildingBlock block, BasePlayer player, BuildingGrade.Enum grade, ulong skin)
        {
            if (block == null || player == null || player.IsNpc) return;
            LogHookEvent(player, "OnStructureUpgrade", $"grade={grade}");
            string key;
            switch (grade)
            {
                case BuildingGrade.Enum.Wood:    key = "upgrade_wood"; break;
                case BuildingGrade.Enum.Stone:   key = "upgrade_stone"; break;
                case BuildingGrade.Enum.Metal:   key = "upgrade_metal"; break;
                case BuildingGrade.Enum.TopTier: key = "upgrade_toptier"; break;
                default: key = "upgrade_" + grade.ToString().ToLower(); break;
            }
            addParametr(player.UserIDString, key, 1);
        }

        // Крафт (оружие, одежда и т.д.)
        void OnItemCraft(ItemCraftTask task, BasePlayer player, Item fromTempBlueprint)
        {
            if (task == null || player == null || player.IsNpc) return;
            ItemDefinition def = task.blueprint?.targetItem;
            if (def == null) return;
            string shortname = def.shortname;
            LogHookEvent(player, "OnItemCraft", $"item={shortname} amount={task.amount}");
            addParametr(player.UserIDString, "craft_" + shortname, task.amount);
            if (def.category == ItemCategory.Weapon)
                addParametr(player.UserIDString, "craft_weapon", task.amount);
            else if (def.category == ItemCategory.Attire)
                addParametr(player.UserIDString, "craft_attire", task.amount);
        }

        private void OnEntityDeath(LootContainer entity, HitInfo info)
        {
			LogHookEvent(info?.InitiatorPlayer, "OnEntityDeath(LootContainer)", $"entity={entity?.ShortPrefabName}");
            if (entity == null || info == null)
                return;
            BasePlayer player = info.InitiatorPlayer;
            if (player == null || player.IsNpc)
                return;
            if (entity.ShortPrefabName.Contains("barrel"))
            {
                addParametr(player.UserIDString, "barrel", 1);
            }
        }

        /// <summary>Засчитывает открытие только один раз на пару (игрок, сущность). Возвращает true, если засчитано.</summary>
        private bool TryCountUniqueLootOnce(BasePlayer player, LootContainer entity)
        {
            if (entity?.net == null) return false;
            ulong netId = entity.net.ID.Value;
            string userId = player?.UserIDString;
            if (string.IsNullOrEmpty(userId)) return false;
            lock (_uniqueLootLock)
            {
                if (!_uniqueLootCounted.TryGetValue(netId, out var players))
                {
                    players = new HashSet<string>();
                    _uniqueLootCounted[netId] = players;
                }
                if (players.Add(userId))
                    return true;
                return false;
            }
        }

        private void OnLootEntity(BasePlayer player, LootContainer entity)
        {
			LogHookEvent(player, "OnLootEntity", $"entity={entity?.ShortPrefabName}");
            if (entity == null || player == null || entity.OwnerID.IsSteamId() || entity.net == null)
                return;

            bool isUniqueOpen = false;
            if (entity.ShortPrefabName.Contains("codelockedhackablecrate_oilrig"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "codelockedhackablecrate_oilrig", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else if (entity.ShortPrefabName.Contains("codelockedhackablecrate"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "codelockedhackablecrate", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else if (entity.ShortPrefabName.Contains("crate_elite") || entity.ShortPrefabName.Contains("bradley_crate") || entity.ShortPrefabName.Contains("heli_crate"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "crate_elite", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else if (entity.ShortPrefabName.Contains("crate_normal"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "crate_normal", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else if (entity.ShortPrefabName.Contains("crate_underwater_advanced"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "crate_underwater_advanced", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else if (entity.ShortPrefabName.Contains("crate_underwater_basic"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "crate_underwater_basic", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else if (entity.ShortPrefabName.Contains("supply_drop") || entity.ShortPrefabName.Contains("supply_drop_"))
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "supply_drop", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            else
            {
                if (TryCountUniqueLootOnce(player, entity))
                {
                    addParametr(player.UserIDString, "crate_open", 1);
                    isUniqueOpen = true;
                }
                else
                    LogHookEvent(player, "OnLootEntity", $"already opened | entity={entity.ShortPrefabName}");
            }
            if (isUniqueOpen)
            {
                foreach (var item in entity.inventory.itemList)
                {
                    if (item?.info == null) continue;
                    string sn = item.info.shortname;
                    if (sn == "scrap")
                        addParametr(player.UserIDString, "scrap", item.amount);
                    else if (sn == "advancedblueprintfragment" || sn == "basicblueprintfragment")
                        addParametr(player.UserIDString, sn, item.amount);
                }
                if (Debug)
                {
                    var itemParts = new List<string>();
                    foreach (var item in entity.inventory.itemList)
                    {
                        if (item?.info == null) continue;
                        itemParts.Add($"{item.info.shortname} x{item.amount}");
                    }
                    if (itemParts.Count > 0)
                        LogHookEvent(player, "OnLootEntity", "items=" + string.Join(", ", itemParts));
                }
            }
        }

        // Учитываем только чертежи, забранные из элитных ящиков (crate_elite, bradley_crate). Работает только при Debug.
        private object CanMoveItem(Item item, PlayerInventory inventory, ItemContainerId targetContainerID, int targetSlot, int amount, ItemMoveModifier itemMoveModifier)
        {
            if (!Debug) return null;
            BasePlayer player = inventory?.GetComponent<BasePlayer>();
            if (player == null || player.IsNpc || !player.userID.IsSteamId() || item?.info == null)
                return null;
            if (!item.IsBlueprint())
                return null;
            BaseEntity sourceOwner = item.parent?.entityOwner;
            if (sourceOwner == null || sourceOwner == player || sourceOwner is BasePlayer)
                return null;
            var lootContainer = sourceOwner as LootContainer;
            if (lootContainer == null)
                return null;
            string prefab = lootContainer.ShortPrefabName ?? "";
            bool isEliteCrate = prefab.Contains("crate_elite") || prefab.Contains("bradley_crate");
            if (!isEliteCrate)
                return null;
            int amountMoved = amount > 0 ? amount : item.amount;
            addParametr(player.UserIDString, item.info.shortname, amountMoved);
            return null;
        }

		private void OnTeamLeave(RelationshipManager.PlayerTeam team, BasePlayer player)
		{
			LogHookEvent(player, "OnTeamLeave", "");
            Team model = new Team();
            model.steam_id = player.UserIDString;
            model.type = "leaved";
            model.team_author = team.teamLeader.ToString();
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            teamsData.Teams.Add(model);
		}

		private void OnTeamKick(RelationshipManager.PlayerTeam team, BasePlayer player, ulong target)
		{
			LogHookEvent(player, "OnTeamKick", $"target={target}");
            Team model = new Team();
            model.steam_id = player.UserIDString;
            model.type = "kicked";
            model.team_author = team.teamLeader.ToString();
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            teamsData.Teams.Add(model);
		}

		private void OnTeamAcceptInvite(RelationshipManager.PlayerTeam team, BasePlayer player)
		{
			LogHookEvent(player, "OnTeamAcceptInvite", "");
            Team model = new Team();
            model.steam_id = player.UserIDString;
            model.type = "invite_accepted";
            model.team_author = team.teamLeader.ToString();
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            teamsData.Teams.Add(model);
		}

        private void OnTeamDisband(RelationshipManager.PlayerTeam team)
        {
			Puts("[Stats] OnTeamDisband | members=" + (team?.members?.Count ?? 0));
            foreach (var item in team.members)
            {
                Team model = new Team();
                model.steam_id = item.ToString();
                model.type = "disband";
                model.team_author = team.teamLeader.ToString();
                model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
                teamsData.Teams.Add(model);
            }
        }

        #endregion

		private void OnStashExposed(StashContainer stash, BasePlayer player)
		{
			LogHookEvent(player, "OnStashExposed", "stash");
		  if (stash == null)
		  {
			return;
		  }
		  var owner = stash.OwnerID;

		   if (player.userID != owner) {
			addParametr(player.UserIDString, "stash", 1);
		   }

		  var team = player.Team;
		  if (team != null)
		  {
			if (team.members.Contains(stash.OwnerID))
			{
			  return;
			}
		  }


		  if (player.userID == stash.OwnerID || owner == 0)
		  {
			return;
		  }

		}

       private static string GridReference(Vector3 position)
        {
            // Массив символов для обозначения столбцов (A, B, ..., Z, AA, AB, ...)
            var chars = new string[] { "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "AA", "AB", "AC", "AD", "AE", "AF", "AG", "AH", "AI", "AJ", "AK", "AL", "AM", "AN", "AO", "AP", "AQ", "AR", "AS", "AT", "AU", "AV", "AW", "AX", "AY", "AZ" };

            // Размер одного блока на карте (уточните это значение для вашей версии игры)
            const float block = 146;

            // Размер карты (обычно 4000 для стандартного размера карты Rust)
            float size = ConVar.Server.worldsize;

            // Смещение для перевода координат в систему с началом в углу карты
            float offset = size / 2;

            // Перевод координат в систему с началом в углу карты
            float xpos = position.x + offset;
            float zpos = position.z + offset;

            // Максимальное количество блоков по одной оси
            int maxgrid = (int)(size / block);

            // Расчет координат в сетке
            float xcoord = Mathf.Clamp(xpos / block, 0, maxgrid - 1);
            float zcoord = Mathf.Clamp(maxgrid - (zpos / block), 0, maxgrid - 1);

            // Проверка на выход за пределы массива (на всякий случай)
            if (xcoord < 0 || xcoord >= chars.Length || zcoord < 1 || zcoord > maxgrid)
            {
                return "Out of bounds";
            }

            // Формирование текстовой позиции (например, "A1", "B2" и т.д.)
            string pos = string.Concat(chars[(int)xcoord], (int)zcoord);

            return pos;
        }
    }
}
