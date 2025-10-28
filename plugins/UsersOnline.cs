using Newtonsoft.Json;
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

namespace Oxide.Plugins
{
    [Info("Users Online", "prostoj.store", "1.0.0")]
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
		
        string api = "https://api.prostoj.store";
        void UpdateBoomBox()
        {
            webrequest.Enqueue(api + $"/radio/list", null, (code, response) =>
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

            webrequest.Enqueue($"https://prostoj.store/api-stats/update-users?serverTag={config.server_tag}", requestBody, (code, response) => {}, this, RequestMethod.POST, header, timeout: 1F);
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