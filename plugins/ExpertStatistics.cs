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
    [Info("Expert Statistics", "prostoj.store", "1.0.0")]
    [Description("Плагин, синхронизирует статистику игроков с сайтом.")]
    public class ExpertStatistics : CovalencePlugin
    {
        #region Config
        static Configuration config;
        public class Configuration
        {
            [JsonProperty(PropertyName = "Server Tag")] public string server_tag;

            public static Configuration DefaultConfig()
            {
                return new Configuration
                {
                    server_tag = "pve"
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

        private const Boolean LanguageEn = false;

        private void LoadConfigFromAPI()
        {
            try
            {
                // Получаем IP и порт сервера
                String serverIp = ConVar.Server.ip;
                Int32 serverPort = ConVar.Server.port;
                String pluginName = Name; // "ExpertStatistics"
                
                String apiUrl = $"https://api.prostoj.store/rust-plugin-config/get?ip={serverIp}&port={serverPort}&name={pluginName}";
                
                PrintWarning(LanguageEn
                    ? $"Loading configuration from API: {apiUrl}"
                    : $"Загрузка конфигурации из API: {apiUrl}");
                
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
                                    
                                    PrintWarning(LanguageEn
                                        ? $"Configuration loaded successfully from API!"
                                        : $"Конфигурация успешно загружена из API!");
                                    
                                    NextTick(SaveConfig);
                                    return;
                                }
                            }
                        }
                        catch (Exception ex)
                        {
                            PrintError(LanguageEn
                                ? $"Error parsing API response: {ex.Message}. Using default config."
                                : $"Ошибка парсинга ответа API: {ex.Message}. Используется конфиг по умолчанию.");
                        }
                    }
                    else
                    {
                        PrintWarning(LanguageEn
                            ? $"Failed to load config from API (Code: {code}). Using default config."
                            : $"Не удалось загрузить конфиг из API (Код: {code}). Используется конфиг по умолчанию.");
                    }
                }, this, RequestMethod.GET, null, 10f);
            }
            catch (Exception ex)
            {
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

        void OnServerInitialized(bool initial)
        {
            Puts("Expert Statistics: OnServerInitialized.");
            
            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();

            timer.Every(1 * 60, () =>
            {
                SaveAllStats();
            });
        }
        void Unload()
        {
            killsData.Kills.Clear();
            disconnects.Clear();
            list.Clear();
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

            Dictionary<string, string> header = new Dictionary<string, string>();
            header.Add("Content-Type", "application/json");
            webrequest.Enqueue($"https://prostoj.store/api-stats/update?serverTag={config.server_tag}", requestBody, (code, response) => {}, this, RequestMethod.POST, header, timeout: 1F);
            list.Clear();
            chatsData.Chats.Clear();
            reportsData.Reports.Clear();
            teamsData.Teams.Clear();
            killsData.Kills.Clear();
            teams.Clear();
            disconnects.Clear();
        }

        [Command("stats.save")]
        private void SaveStatsCMD(IPlayer iPlayer, string command, string[] args)
        {
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
            if (entity != null)
            {
                addParametr(player.UserIDString, entity.ShortPrefabName, 1);
            }
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
            if (!list.ContainsKey(steamId))
            {
                list.Add(steamId, new Dictionary<string, int>());
            }
            if (!list[steamId].ContainsKey(parametr)) {
                list[steamId][parametr] = 0;
            }
            list[steamId][parametr] += count;
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
			if (privilege == null || player == null || !player.userID.IsSteamId()) return;
			addParametr(player.UserIDString, "cupboard_authorized", 1);
		}
		void OnPlayerAttack(BasePlayer attacker, HitInfo info)
        {
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
            if (player.IsNpc) {
                return;
            }
            addParametr(player.UserIDString, "mod_" + item.info.shortname, 1);
		}

        void OnFishCatch(Item item, BaseFishingRod rod, BasePlayer player)
        {
            addParametr(player.UserIDString, "f_" + item.info.shortname, item.amount);
        }

        void OnEntityDeath(BasePlayer player, HitInfo info)
        {   
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
            if (attacker.currentTeam == player.currentTeam) {
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
        void OnEntityDeath(SimpleShark shark, HitInfo info)
        {
            if (shark == null || info == null) return;
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;

            Kill kill = new Kill();
			kill.steam_id = attacker.UserIDString;
			kill.type = "animal";
			kill.dead = shark.ShortPrefabName;
			kill.distance = (int)info.ProjectileDistance;
			kill.date = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
			Item weaponItem = info.Weapon?.GetItem();
			if (weaponItem != null) {
			    kill.weapon = weaponItem.info.shortname;
			}
			killsData.Kills.Add(kill);
			addParametr(attacker.UserIDString, shark.ShortPrefabName, 1);
        }

        void OnEntityDeath(BuildingPrivlidge entity, HitInfo info)
        {
            if (entity == null || info == null) return;
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;

            addParametr(attacker.UserIDString, "tcsdestroyed", 1);
        }

        void OnEntityDeath(NPCPlayer scientist, HitInfo info)
        {
            if (scientist == null || info == null) return;
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;

            addParametr(attacker.UserIDString, "scientists", 1);
        }

        void OnEntityDeath(BaseAnimalNPC animal, HitInfo info)
        {
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;
            Kill kill = new Kill();
            kill.steam_id = attacker.UserIDString;
            kill.type = "animal";
            kill.dead = animal.ShortPrefabName;
            kill.distance = (int)info.ProjectileDistance;
            kill.date = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            Item weaponItem = info.Weapon?.GetItem();
            if (weaponItem != null)
            {
                kill.weapon = weaponItem.info.shortname;
            }
            killsData.Kills.Add(kill);
            addParametr(attacker.UserIDString, animal.ShortPrefabName, 1);
        }
		
        void OnEntityDeath(BaseNPC2 animal, HitInfo info)
        {
            BasePlayer attacker = info?.InitiatorPlayer;
            if (attacker == null || !attacker.userID.IsSteamId()) return;
            Kill kill = new Kill();
            kill.steam_id = attacker.UserIDString;
            kill.type = "animal";
            kill.dead = animal.ShortPrefabName;
            kill.distance = (int)info.ProjectileDistance;
            kill.date = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            Item weaponItem = info.Weapon?.GetItem();
            if (weaponItem != null)
            {
                kill.weapon = weaponItem.info.shortname;
            }
            killsData.Kills.Add(kill);
            addParametr(attacker.UserIDString, animal.ShortPrefabName, 1);
        }

        void OnGrowableGathered(GrowableEntity plant, Item item, BasePlayer player)
        {
            addParametr(player.UserIDString, "gathered_" + item.info.shortname, item.amount);
        }

        void OnDispenserBonus(ResourceDispenser dispenser, BasePlayer player, Item item)
        {
            addParametr(player.UserIDString, item.info.shortname, item.amount);
        }


        void OnDispenserGather(ResourceDispenser dispenser, BaseEntity entity, Item item)
        {
            BasePlayer player = entity.ToPlayer();
            if (player == null) return;

            addParametr(player.UserIDString, item.info.shortname, item.amount);
        }

        void OnEntityDeath(BradleyAPC bradley, HitInfo info)
        {
            if (bradley == null || info == null) return;
            
            BasePlayer player = info.InitiatorPlayer;

            if (player != null && player.userID.IsSteamId())
            {
                addParametr(player.UserIDString, "bradleys", 1);
            }
        }

        void OnExplosiveThrown(BasePlayer player, BaseEntity entity, ThrownWeapon item)
        {
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
            }
        }

        void OnEntityDismounted(BaseMountable mountable, BasePlayer player)
        {
            if (mountable == null || player == null) return;

            if (mountable.ShortPrefabName == "parachuteseat") {
                addParametr(player.UserIDString, "parachuteseat", 1);
            }
        }

        void OnRocketLaunched(BasePlayer player, BaseEntity entity)
        {
            if (player == null || entity == null)
                return;
            ExplosionProgressAdd(player, entity);
        }

        void OnPlayerWound(BasePlayer player, HitInfo hitInfo)
        {
            addParametr(player.UserIDString, "wounded", 1);
        }

        void OnWeaponFired(BaseProjectile projectile, BasePlayer player, ItemModProjectile mod, ProtoBuf.ProjectileShoot projectiles)
        {
            if (projectile == null || player == null)
                return;
  
            ExplosionProgressAdd(player, null, projectile.primaryMagazine?.ammoType?.shortname);
        }

        private void OnEntityDeath(LootContainer entity, HitInfo info)
        {
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

        private void OnLootEntity(BasePlayer player, LootContainer entity)
        {
            if (entity == null || player == null || entity.OwnerID.IsSteamId() || entity.net == null)
                return;

            if (entity.ShortPrefabName.Contains("codelockedhackablecrate")) {
                addParametr(player.UserIDString, "codelockedhackablecrate", 1);
            } else {
                addParametr(player.UserIDString, "crate_open", 1);
            }
            foreach (var item in entity.inventory.itemList)
            {
                if (item.info.shortname == "scrap")
                {
                    addParametr(player.UserIDString, "scrap", item.amount);
                }
            }
        }
		private void OnTeamLeave(RelationshipManager.PlayerTeam team, BasePlayer player)
		{
            Team model = new Team();
            model.steam_id = player.UserIDString;
            model.type = "leaved";
            model.team_author = team.teamLeader.ToString();
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            teamsData.Teams.Add(model);
		}

		private void OnTeamKick(RelationshipManager.PlayerTeam team, BasePlayer player, ulong target)
		{
            Team model = new Team();
            model.steam_id = player.UserIDString;
            model.type = "kicked";
            model.team_author = team.teamLeader.ToString();
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            teamsData.Teams.Add(model);
		}

		private void OnTeamAcceptInvite(RelationshipManager.PlayerTeam team, BasePlayer player)
		{
            Team model = new Team();
            model.steam_id = player.UserIDString;
            model.type = "invite_accepted";
            model.team_author = team.teamLeader.ToString();
            model.created_at = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            teamsData.Teams.Add(model);
		}

        private void OnTeamDisband(RelationshipManager.PlayerTeam team)
        {
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