using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;
using ConVar;
using Color = UnityEngine.Color;

namespace Oxide.Plugins
{
    [Info("Help Info", "Prostoj", "1.0.0")]
    [Description("Adds the ablity to see help cycles")]
    public class HelpText : RustPlugin
    {
		private Configuration config;
		public class Configuration
		{
			[JsonProperty(PropertyName = "Server Tag")]
			public string serverTag { get; set; } = "pve";
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

		public class ServerInfo
		{
			public string ru { get; set; }
			public string en { get; set; }
			public int code { get; set; }
		}

        string messageEn = null;
        string messageRu = null;
        string api = "https://prostoj.store/api";
        void OnServerInitialized()
        {
            webrequest.Enqueue(api + $"/help-info?serverTag=" + config.serverTag, null, (code, response) =>
            {
                if (code != 200) return;
				ServerInfo response_deserializeds = JsonConvert.DeserializeObject<ServerInfo>(response);
				messageRu = response_deserializeds.ru;
				messageEn = response_deserializeds.en;
            }, this, Core.Libraries.RequestMethod.GET, null);
        }

        [ChatCommand("help")]
        private void cmdNextHelp(BasePlayer player, string command, string[] args)
        {
			string message = messageEn;
			if (lang.GetLanguage(player.UserIDString) == "ru") {
				message = messageRu;
			}
			SendReply(player, message);
        }

        private string MSG(string key, string playerid = null) => lang.GetMessage(key, this, playerid);

        void ChatMessage(BasePlayer player, string message)
        {
            player.SendConsoleCommand("chat.add", Chat.ChatChannel.Global, 76561198394504608, message);
        }
    }
}