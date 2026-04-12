using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;
using ConVar;
using Color = UnityEngine.Color;

namespace Oxide.Plugins
{
    [Info("Help Info", "Prostoj", "1.0.1")]
    [Description("Adds the ablity to see help cycles")]
    public class HelpText : RustPlugin
    {
		private Configuration config;
		public class Configuration
		{
			[JsonProperty(PropertyName = "Server Tag")]
			public string serverTag { get; set; } = "pve";
			[JsonProperty(PropertyName = "CustomIconSteamId")]
			public ulong customIconSteamId { get; set; } = 76561198028953589;
			[JsonProperty(PropertyName = "Базовый URL текстов плагина (v1, без / на конце)")]
			public string PluginChatBaseUrl { get; set; } = "https://api.prostoj.store/v1/rust-plugin-chat";
			[JsonProperty(PropertyName = "Базовый URL API конфига из панели (v1, без / на конце)")]
			public string RustPluginConfigApiBase { get; set; } = "https://api.prostoj.store/v1/rust-plugin-config";
		}

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                config = Config.ReadObject<Configuration>();
                if (config == null)
                {
                    LoadDefaultConfig();
                }
            }
            catch
            {
                LoadDefaultConfig();
            }
            SaveConfig();
        }

        protected override void LoadDefaultConfig()
        {
            config = new Configuration();
        }

        protected override void SaveConfig() => Config.WriteObject(config);

        private const Boolean LanguageEn = false;

        private void LoadConfigFromAPI()
        {
            try
            {
                // Получаем IP и порт сервера
                String serverIp = ConVar.Server.ip;
                Int32 serverPort = ConVar.Server.port;
                String pluginName = Name; // "HelpText"

                string cfgBase = string.IsNullOrWhiteSpace(config.RustPluginConfigApiBase)
                    ? "https://api.prostoj.store/v1/rust-plugin-config"
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

                                    // Загружаем help info после успешной загрузки конфига
                                    LoadHelpInfo();
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

                    // Загружаем help info даже если конфиг из API не загрузился
                    LoadHelpInfo();
                }, this, RequestMethod.GET, null, 10f);
            }
            catch (Exception ex)
            {
                PrintError(LanguageEn
                    ? $"Error loading config from API: {ex.Message}. Using default config."
                    : $"Ошибка загрузки конфига из API: {ex.Message}. Используется конфиг по умолчанию.");

                // Загружаем help info даже если произошла ошибка
                LoadHelpInfo();
            }
        }

		public class ServerInfo
		{
			public string ru { get; set; }
			public string en { get; set; }
			public int code { get; set; }
		}

        string messageEn = null;
        string messageRu = null;

        private void LoadHelpInfo()
        {
            string chatBase = string.IsNullOrWhiteSpace(config.PluginChatBaseUrl)
                ? "https://api.prostoj.store/v1/rust-plugin-chat"
                : config.PluginChatBaseUrl.TrimEnd('/');
            webrequest.Enqueue($"{chatBase}/help/{config.serverTag}", null, (code, response) =>
            {
                if (code != 200) return;
				ServerInfo response_deserializeds = JsonConvert.DeserializeObject<ServerInfo>(response);
				messageRu = response_deserializeds.ru;
				messageEn = response_deserializeds.en;
            }, this, Core.Libraries.RequestMethod.GET, null);
        }

        void OnServerInitialized()
        {
            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();
        }

        [ChatCommand("help")]
        private void cmdNextHelp(BasePlayer player, string command, string[] args)
        {
			string message = messageEn;
			if (lang.GetLanguage(player.UserIDString) == "ru") {
				message = messageRu;
			}

			if (config.customIconSteamId != 0)
			{
				player.SendConsoleCommand("chat.add", 0, config.customIconSteamId, message);
				return;
			}

			SendReply(player, message);
        }

        private string MSG(string key, string playerid = null) => lang.GetMessage(key, this, playerid);

        void ChatMessage(BasePlayer player, string message)
        {
            ulong steamId = config.customIconSteamId != 0 ? config.customIconSteamId : 76561198394504608;
            player.SendConsoleCommand("chat.add", 0, steamId, message);
        }
    }
}
