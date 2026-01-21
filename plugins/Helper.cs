using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries.Covalence;
using Oxide.Core.Plugins;
using Rust;
using System;
using System.Collections;
using System.Collections.Generic;

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
    [Info("Helper Prostoj", "moscow77.store", "1.0.0")]
    [Description("Плагин, для помощи работы сайта.")]
    public class Helper : RustPlugin 
    {
        private Configuration config;
        
        public class Configuration
        {
            [JsonProperty(PropertyName = "Sender Steam ID")]
            public ulong SenderSteamId { get; set; } = 76561198394504608;
        }
        
        private const Boolean LanguageEn = false;
        
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

        private void LoadConfigFromAPI()
        {
            try
            {
                // Получаем IP и порт сервера
                String serverIp = ConVar.Server.ip;
                Int32 serverPort = ConVar.Server.port;
                String pluginName = Name; // "Helper"
                
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
        
        void OnServerInitialized()
        {
            // Загружаем конфиг из API при инициализации сервера (когда IP/порт доступны)
            LoadConfigFromAPI();
        }
       
        [ConsoleCommand("helper")]
        private void ConsoleCommandHelper(ConsoleSystem.Arg arg)
        {
	        String Actions = arg.Args[0];
	        BasePlayer player = arg.Player();
            if (player != null && !player.IsAdmin)
            {
                return;
            }
	        if (Actions.Contains("ban"))
	        {
				if (!arg.HasArgs(2)) return;
                Puts(string.Format("User {0} banned", arg.Args[1]));
                ServerUsers.Set(Convert.ToUInt64(arg.Args[1]), global::ServerUsers.UserGroup.Banned, "asd", arg.Args[2]);
                BasePlayer user = BasePlayer.FindAwakeOrSleeping(arg.Args[1]);
				if (user && user != null) {
					Net.sv.Kick(user.net.connection, arg.Args[2], false);
				}
				ServerUsers.Save();
			}
			// helper giveto "76561199160689130" "-148794216" "1" "3310903792"
	        if (Actions.Contains("giveto"))
	        {
				if (!arg.HasArgs(4)) return;
                BasePlayer user = BasePlayer.FindAwakeOrSleeping(arg.Args[1]);
				Item newItem = ItemManager.CreateByItemID(int.Parse(arg.Args[2]), int.Parse(arg.Args[3]), ulong.Parse(arg.Args[4]));
				user.GiveItem(newItem); 
			}
			// helper message "ру" "en" "sound" "76561198394504608"
	        if (Actions.Contains("message"))
	        {
				if (!arg.HasArgs(5)) return;
                BasePlayer recepient = BasePlayer.FindAwakeOrSleeping(arg.Args[4]);
                if (recepient == null) return;
				string messageRu = arg.Args[1].Replace("\\n", "\r\n");
				string messageEn = arg.Args[2].Replace("\\n", "\r\n");
				string sound_prefab = arg.Args[3];
				string message = lang.GetLanguage(recepient.UserIDString) == "ru" ? messageRu : messageEn;
				if (!string.IsNullOrEmpty(sound_prefab)) {
                    SendEffect(recepient, sound_prefab);
				}
                ulong senderId = config != null ? config.SenderSteamId : 76561198394504608;
                recepient.SendConsoleCommand("chat.add", (object) 0, senderId, message); 
			}
			// helper globalMessage "ру" "en" "sound"
	        if (Actions.Contains("globalMessage"))
	        {
				if (!arg.HasArgs(4)) return;
				string messageRu = arg.Args[1].Replace("\\n", "\r\n");
				string messageEn = arg.Args[2].Replace("\\n", "\r\n");
				string sound_prefab = arg.Args[3];
                foreach (var recepient in BasePlayer.activePlayerList)
                { 
					string message = lang.GetLanguage(recepient.UserIDString) == "ru" ? messageRu : messageEn;
					if (!string.IsNullOrEmpty(sound_prefab)) {
                        SendEffect(recepient, sound_prefab);
					}
                    ulong senderId = config != null ? config.SenderSteamId : 76561198394504608;
                    recepient.SendConsoleCommand("chat.add", (object) 0, senderId, message); 
                }
			}

	        if (Actions.Contains("spawn"))
	        {
				if (!arg.HasArgs(2)) return;
                BasePlayer user = BasePlayer.FindByID(ulong.Parse(arg.Args[1]));
				var position = user.eyes.position + user.eyes.HeadRay().direction * 10;
				position.y = user.transform.position.y + 5;
				var entity = GameManager.server.CreateEntity(arg.Args[2], position, user.transform.rotation);
				entity.Spawn();
			}
        }

		private void SendEffect(BasePlayer player, string sound)
        {
            var effect = new Effect(sound, player, 0, Vector3.zero, Vector3.forward);
            EffectNetwork.Send(effect, player.net.connection);
        }

    }
}