using Oxide.Core.Libraries.Covalence;
using System.Collections.Generic;
using Oxide.Core;
using Newtonsoft.Json;

namespace Oxide.Plugins
{
    [Info("WelcomeMessages", "Ankawi", "1.0.4", ResourceId = 2219)]
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

        string messageEn = null;
        string messageRu = null;
        string api = "https://prostoj.store/api";
        void OnServerInitialized()
        { 
            webrequest.Enqueue(api + $"/welcome-message?serverTag=" + config.serverTag, null, (code, response) =>
            {
                if (code != 200) return;
				ServerInfo response_deserializeds = JsonConvert.DeserializeObject<ServerInfo>(response);
				messageRu = response_deserializeds.ru;
				messageEn = response_deserializeds.en;
            }, this, Core.Libraries.RequestMethod.GET, null);
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
                player.Reply(covalence.FormatText(string.Format(message, player.Name.Sanitize())));
            });        
        }
    }
}