using Oxide.Core.Libraries.Covalence;
using System.Collections.Generic;
using Oxide.Core;
using Oxide.Core.Libraries;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using Rust;
using ConVar;
using System;

namespace Oxide.Plugins
{
    [Info("WelcomeMessages", "Ankawi", "1.0.5", ResourceId = 2219)]
    [Description("Отправляет приветственные сообщения игроку")]

    class WelcomeMessages : CovalencePlugin
    {
		public class ServerInfo
		{
			public string ru { get; set; }
			public string en { get; set; }
			public int code { get; set; }
		}

		private Configuration config;
		public class Configuration
		{
			[JsonProperty(PropertyName = "Server Tag")]
			public string serverTag { get; set; } = "max3";
			[JsonProperty(PropertyName = "WaitIntervalInSeconds")]
			public float waitIntervalInSeconds { get; set; } = 25f;
			[JsonProperty(PropertyName = "Sender Steam ID")]
			public ulong senderSteamId { get; set; } = 76561198394504608;
			[JsonProperty(PropertyName = "Базовый URL текстов плагина (v1, без / на конце)")]
			public string PluginChatBaseUrl { get; set; } = "https://api.moscow77.store.store/v1/rust-plugin-chat";
			[JsonProperty(PropertyName = "Базовый URL API конфига из панели (v1, без / на конце)")]
			public string RustPluginConfigApiBase { get; set; } = "https://api.moscow77.store.store/v1/rust-plugin-config";
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
                String pluginName = Name; // "WelcomeMessages"

                string cfgBase = string.IsNullOrWhiteSpace(config.RustPluginConfigApiBase)
                    ? "https://api.moscow77.store.store/v1/rust-plugin-config"
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
                                        ? $"Configuration loaded successfully from API! ServerTag: {config.serverTag}"
                                        : $"Конфигурация успешно загружена из API! ServerTag: {config.serverTag}");

                                    NextTick(SaveConfig);

                                    // Загружаем welcome messages после успешной загрузки конфига
                                    // Используем NextTick чтобы убедиться, что config обновлен
                                    NextTick(() => LoadWelcomeMessages());
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

                        // Загружаем welcome messages даже если конфиг из API не загрузился
                        LoadWelcomeMessages();
                    }
                }, this, RequestMethod.GET, null, 10f);
            }
            catch (Exception ex)
            {
                PrintError(LanguageEn
                    ? $"Error loading config from API: {ex.Message}. Using default config."
                    : $"Ошибка загрузки конфига из API: {ex.Message}. Используется конфиг по умолчанию.");

                // Загружаем welcome messages даже если произошла ошибка
                LoadWelcomeMessages();
            }
        }

        string messageEn = null;
        string messageRu = null;

        private void LoadWelcomeMessages()
        {
            // Проверяем, что config загружен и serverTag установлен
            if (config == null)
            {
                PrintError(LanguageEn
                    ? "Config is null, cannot load welcome messages"
                    : "Конфиг не загружен, невозможно загрузить приветственные сообщения");
                return;
            }

            if (string.IsNullOrEmpty(config.serverTag))
            {
                PrintError(LanguageEn
                    ? "ServerTag is empty, cannot load welcome messages"
                    : "ServerTag пуст, невозможно загрузить приветственные сообщения");
                return;
            }

            string chatBase = string.IsNullOrWhiteSpace(config.PluginChatBaseUrl)
                ? "https://api.moscow77.store.store/v1/rust-plugin-chat"
                : config.PluginChatBaseUrl.TrimEnd('/');
            string url = $"{chatBase}/welcome/{config.serverTag}";

            PrintWarning(LanguageEn
                ? $"Loading welcome messages from: {url}"
                : $"Загрузка приветственных сообщений из: {url}");

            webrequest.Enqueue(url, null, (code, response) =>
            {
                if (code != 200)
                {
                    PrintWarning(LanguageEn
                        ? $"Failed to load welcome messages (Code: {code})"
                        : $"Не удалось загрузить приветственные сообщения (Код: {code})");
                    return;
                }

                try
                {
                    ServerInfo response_deserializeds = JsonConvert.DeserializeObject<ServerInfo>(response);
                    if (response_deserializeds != null)
                    {
                        messageRu = response_deserializeds.ru;
                        messageEn = response_deserializeds.en;

                        PrintWarning(LanguageEn
                            ? "Welcome messages loaded successfully!"
                            : "Приветственные сообщения успешно загружены!");
                    }
                }
                catch (Exception ex)
                {
                    PrintError(LanguageEn
                        ? $"Error parsing welcome messages: {ex.Message}"
                        : $"Ошибка парсинга приветственных сообщений: {ex.Message}");
                }
            }, this, Core.Libraries.RequestMethod.GET, null);
        }

        void OnServerInitialized()
        {
            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();
        }

        void Init()
        {
            LoadDefaultConfig();
        }

        void OnUserConnected(IPlayer player)
        {
			string message = messageEn;
			if (lang.GetLanguage(player.Id.ToString()) == "ru") {
				message = messageRu;
			}
            timer.Once((float)(config.waitIntervalInSeconds), () =>
            {
                // Находим BasePlayer для отправки сообщения от указанного Steam ID
                BasePlayer basePlayer = BasePlayer.FindByID(ulong.Parse(player.Id));
                if (basePlayer != null)
                {
                    string formattedMessage = covalence.FormatText(string.Format(message, player.Name.Sanitize()));
                    // Отправляем сообщение от указанного Steam ID из конфига
                    basePlayer.SendConsoleCommand("chat.add", 0, config.senderSteamId, formattedMessage);
                }
            });
        }
    }
}
