using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using Oxide.Core.Libraries.Covalence;
using Rust;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using ConVar;

namespace Oxide.Plugins
{
    [Info("Pop", "Sigilo", "1.7.2")]
    [Description("Displays the number of connected, connecting, and queued players.")]

    public class Pop : CovalencePlugin
    {
        private Configuration config;
        private DateTime lastBroadcast;

        public class Configuration
        {
            [JsonProperty(PropertyName = "Command")]
            public string Command { get; set; } = "pop";
            [JsonProperty(PropertyName = "BroadcastCommand")]
            public string BroadcastCommand { get; set; } = "!pop";
            [JsonProperty(PropertyName = "OnlineColor")]
            public string OnlineColor { get; set; } = "#ff686b";
            [JsonProperty(PropertyName = "JoiningColor")]
            public string JoiningColor { get; set; } = "#ff686b";
            [JsonProperty(PropertyName = "QueuedColor")]
            public string QueuedColor { get; set; } = "#ff686b";
            [JsonProperty(PropertyName = "BroadcastDelay")]
            public int BroadcastDelay { get; set; } = 10;
            [JsonProperty(PropertyName = "EnablePersonalCommand")]
            public bool EnablePersonalCommand { get; set; } = true;
            [JsonProperty(PropertyName = "EnableGlobalCommand")]
            public bool EnableGlobalCommand { get; set; } = true;
            [JsonProperty(PropertyName = "CustomIconSteamId")]
            public ulong CustomIconSteamId { get; set; } = 76561198028953589;
        }

        private const Boolean LanguageEn = false;

        private string command => config?.Command ?? "pop";
        private string broadcastCommand => config?.BroadcastCommand ?? "!pop";
        private string onlineColor => config?.OnlineColor ?? "#ff686b";
        private string joiningColor => config?.JoiningColor ?? "#ff686b";
        private string queuedColor => config?.QueuedColor ?? "#ff686b";
        private int broadcastDelay => config?.BroadcastDelay ?? 10;
        private bool enablePersonalCommand => config?.EnablePersonalCommand ?? true;
        private bool enableGlobalCommand => config?.EnableGlobalCommand ?? true;
        private ulong customIconSteamId => config?.CustomIconSteamId ?? 76561198028953589;

        private void Init()
        {
            permission.RegisterPermission("pop.use", this);
            LoadConfig();
            // Регистрируем команды с начальными настройками (будут обновлены после загрузки из API)
            RegisterCommands();
        }

        private void RegisterCommands()
        {
            if (enablePersonalCommand && !string.IsNullOrEmpty(command))
            {
                AddCovalenceCommand(command, "PopCommand");
            }
            if (enableGlobalCommand && !string.IsNullOrEmpty(broadcastCommand))
            {
                AddCovalenceCommand(broadcastCommand, "BroadcastPopCommand");
            }
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
            catch (Exception ex)
            {
                PrintError($"Error loading config: {ex.Message}");
                LoadDefaultConfig();
            }
            SaveConfig();
        }

        protected override void LoadDefaultConfig()
        {
            config = new Configuration();
        }

        protected override void SaveConfig() => Config.WriteObject(config);

        private void LoadConfigFromAPI()
        {
            try
            {
                // Получаем IP и порт сервера
                String serverIp = ConVar.Server.ip;
                Int32 serverPort = ConVar.Server.port;
                String pluginName = Name; // "Pop"

                String apiUrl = $"https://api.moscow77.store/rust-plugin-config/get?ip={serverIp}&port={serverPort}&name={pluginName}";

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

                                    NextTick(() => {
                                        SaveConfig();
                                        RegisterCommands();
                                    });
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

                    // Регистрируем команды даже если конфиг из API не загрузился
                    RegisterCommands();
                }, this, RequestMethod.GET, null, 10f);
            }
            catch (Exception ex)
            {
                PrintError(LanguageEn
                    ? $"Error loading config from API: {ex.Message}. Using default config."
                    : $"Ошибка загрузки конфига из API: {ex.Message}. Используется конфиг по умолчанию.");

                // Регистрируем команды даже если произошла ошибка
                RegisterCommands();
            }
        }

        void OnServerInitialized()
        {
            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();
        }

        private int GetQueuedPlayersCount()
        {
            return ServerMgr.Instance.connectionQueue.Queued;
        }

        private void PopCommand(IPlayer player, string cmd, string[] args)
        {
            int connectedPlayers = BasePlayer.activePlayerList.Count;
            int joiningPlayers = ServerMgr.Instance.connectionQueue.Joining;
            int playersInQueue = GetQueuedPlayersCount();

            string formatMessage = lang.GetMessage("PopMessage", this, player.Id);
            string onlineText = string.Format("<color={0}>{1}</color>", onlineColor, connectedPlayers);
            string joiningText = string.Format("<color={0}>{1}</color>", joiningColor, joiningPlayers);
            string queuedText = string.Format("<color={0}>{1}</color>", queuedColor, playersInQueue);

            string message;
            try
            {
                message = string.Format(formatMessage, onlineText, joiningText, queuedText);
            }
            catch (FormatException)
            {
                // Если формат не соответствует, используем простое сообщение
                message = $"Игроки онлайн: {onlineText} | Присоединение: {joiningText} | В очереди: {queuedText}";
            }

            if (customIconSteamId != 0)
            {
                BasePlayer basePlayer = player.Object as BasePlayer;
                if (basePlayer != null)
                {
                    basePlayer.SendConsoleCommand("chat.add", 0, customIconSteamId, message);
                    return;
                }
            }

            player.Reply(message);
        }

        private void BroadcastPopCommand(IPlayer player, string cmd, string[] args)
        {
            if (DateTime.Now < lastBroadcast.AddMinutes(broadcastDelay))
            {
                string tooSoonMessage = string.Format(lang.GetMessage("TooSoon", this, player.Id), broadcastDelay);
                player.Reply(tooSoonMessage);
                return;
            }

            lastBroadcast = DateTime.Now;

            int connectedPlayers = BasePlayer.activePlayerList.Count;
            int joiningPlayers = ServerMgr.Instance.connectionQueue.Joining;
            int playersInQueue = GetQueuedPlayersCount();

            string formatMessage = lang.GetMessage("PopMessage", this, player.Id);
            string onlineText = string.Format("<color={0}>{1}</color>", onlineColor, connectedPlayers);
            string joiningText = string.Format("<color={0}>{1}</color>", joiningColor, joiningPlayers);
            string queuedText = string.Format("<color={0}>{1}</color>", queuedColor, playersInQueue);

            string message;
            try
            {
                message = string.Format(formatMessage, onlineText, joiningText, queuedText);
            }
            catch (FormatException)
            {
                // Если формат не соответствует, используем простое сообщение
                message = $"Игроки онлайн: {onlineText} | Присоединение: {joiningText} | В очереди: {queuedText}";
            }

            if (customIconSteamId != 0)
            {
                foreach (BasePlayer basePlayer in BasePlayer.activePlayerList)
                {
                    basePlayer.SendConsoleCommand("chat.add", 0, customIconSteamId, message);
                }
                return;
            }

            server.Broadcast(message);
        }

        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                {"NoPermission", "У вас нет разрешения на использование этой команды."},
                {"TooSoon", "Между сообщениями необходимо подождать {0} минут (попробуйте /pop).."},
                {"PopMessage", "Игроки онлайн: {0} | Присоединение: {1} | В очереди: {2}"}
            }, this);
        }

        void OnUserChat(IPlayer player, string message)
        {
            if (message == "!pop")
            {
                BroadcastPopCommand(player, message, null);
            }
        }
    }
}
