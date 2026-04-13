using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using Oxide.Core;
using Oxide.Core.Configuration;
using Oxide.Core.Libraries;
using Oxide.Core.Libraries.Covalence;
using System;
using System.Collections.Generic;
using System.Linq;
using WebSocketSharp;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;
using ConVar;

namespace Oxide.Plugins
{
    [Info("Users Online", "prostoj.store", "1.0.1")]
    [Description("Плагин, синхронизирует игроков с сайтом.")]
    public class UsersOnline : RustPlugin
    {
        #region Config
        static Configuration config;
		public class ServerInfo
		{
			public string radioList { get; set; }
		}
        public class Configuration
        {
            [JsonProperty(PropertyName = "Server Tag")] public string server_tag;
            [JsonProperty(PropertyName = "Базовый URL отправки онлайна (v1, без / на конце)")]
            public string PluginStatsIngestBaseUrl = "https://api.moscow77.store/v1/plugin-ingest";
            [JsonProperty(PropertyName = "URL списка радио для BoomBox (GET, v1)")]
            public string RadioBoomboxListUrl = "https://api.moscow77.store/v1/radio/boombox-list";
            [JsonProperty(PropertyName = "Базовый URL API конфига из панели (v1, без / на конце)")]
            public string RustPluginConfigApiBase = "https://api.moscow77.store/v1/rust-plugin-config";

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
                String pluginName = Name; // "UsersOnline"

                string cfgBase = string.IsNullOrWhiteSpace(config.RustPluginConfigApiBase)
                    ? "https://api.moscow77.store/v1/rust-plugin-config"
                    : config.RustPluginConfigApiBase.TrimEnd('/');
                String apiUrl = $"{cfgBase}/get?ip={serverIp}&port={serverPort}&name={pluginName}";

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

        public class User
        {
            public string steam_id = "";
            public string username = "";
            public string ip = "";
            public int ping = 1;
        };

        public class UsersData
        {
            public List<User> Users = new List<User>();
        }
        #endregion

        #region Init
		UsersData usersData = new UsersData();

        void OnServerInitialized(bool initial)
        {
            Puts("Users Online: OnServerInitialized.");

            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();

			UpdateBoomBox();
            timer.Every(5 * 60, () =>
            {
                SaveAll();
            });
        }

        void Unload()
        {
            usersData.Users.Clear();
            SaveAll();
        }

        void UpdateBoomBox()
        {
            string radioUrl = string.IsNullOrWhiteSpace(config.RadioBoomboxListUrl)
                ? "https://api.moscow77.store/v1/radio/boombox-list"
                : config.RadioBoomboxListUrl.TrimEnd('/');
            webrequest.Enqueue(radioUrl, null, (code, response) =>
            {
                if (code != 200) return;
				ServerInfo rd = JsonConvert.DeserializeObject<ServerInfo>(response);
				Server.Command($"BoomBox.ServerUrlList \"{rd.radioList}\"");
            }, this, Core.Libraries.RequestMethod.GET, null);
        }
        #endregion

        #region Save
        void SaveAll()
        {
            foreach (var player in BasePlayer.activePlayerList.ToList()) {
				User user = new User();
                user.steam_id = player.UserIDString;
                user.username = player.displayName;
                user.ip = IPAddressWithoutPort(player.Connection.ipaddress);
                user.ping = Network.Net.sv.GetAveragePing(player.Connection);
                usersData.Users.Add(user);
            }

            string requestBody = JsonConvert.SerializeObject(
                new {
                    users = usersData.Users,
                }).Replace("\n", "").Replace("  ", "");

            Dictionary<string, string> header = new Dictionary<string, string>();
            header.Add("Content-Type", "application/json");

            string ingest = string.IsNullOrWhiteSpace(config.PluginStatsIngestBaseUrl)
                ? "https://api.moscow77.store/v1/plugin-ingest"
                : config.PluginStatsIngestBaseUrl.TrimEnd('/');
            webrequest.Enqueue($"{ingest}/update-users/{config.server_tag}", requestBody, (code, response) => {}, this, RequestMethod.POST, header, timeout: 1F);
            usersData.Users.Clear();
        }

		public static string IPAddressWithoutPort(string ipWithPort)
		{
		  int num = ipWithPort.LastIndexOf(':');
		  if (num != -1)
		  {
			return ipWithPort.Substring(0, num);
		  }

		  return ipWithPort;
		}

        [Command("users.save")]
        private void SaveCMD(IPlayer iPlayer, string command, string[] args)
        {
            Puts("Users Online: SaveStatsCMD.");
            if (!iPlayer.IsServer) return;
            SaveAll();
        }
        #endregion
    }
}
