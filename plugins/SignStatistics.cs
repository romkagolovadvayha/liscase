using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.RegularExpressions;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Libraries.Covalence;
using Rust;
using UnityEngine;
using ProtoBuf;
using Server = ConVar.Server;

namespace Oxide.Plugins
{
    [Info("Sign Statistics", "Prostoj", "1.0.1")]
    [Description("Sign Statistics.")]
    public class SignStatistics : CovalencePlugin
    {
       #region Config
        private class Configuration
        {
            [JsonProperty(PropertyName = "Базовый URL отправки табличек (v1, без / на конце)")]
            public string PluginStatsIngestBaseUrl = "https://api.prostoj.store/v1/plugin-ingest";
        }

        private Configuration config = new Configuration();

        protected override void LoadDefaultConfig()
        {
            config = new Configuration();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                config = Config.ReadObject<Configuration>();
                if (config == null) LoadDefaultConfig();
            }
            catch
            {
                LoadDefaultConfig();
            }
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(config);

       private class SignInfo
        {
            public string signId { get; set; } // SignID
            public string steamId { get; set; } // SteamID лидера команды
            public byte[] base64Image { get; set; } // base64Image
            public string type { get; set; } // type
            public string position { get; set; } // position
        };
        private Dictionary<string, SignInfo> signEvents = new Dictionary<string, SignInfo>();

        void OnServerInitialized(bool initial)
        {
            Puts("Sign Statistics: OnServerInitialized.");

            timer.Every(1 * 60, () =>
            {
                SaveAll();
            });
        }
        void Unload()
        {
            signEvents.Clear();
            SaveAll();
        }
        #endregion

        #region Save
        void SaveAll()
        {
            if (signEvents.Count == 0) {
                return;
            }
                
            string requestBody = JsonConvert.SerializeObject(
                new { 
                    signs = signEvents,
                    ip = Server.ip,
                    port = Server.port,
                }).Replace("\n", "").Replace("  ", "");

            Dictionary<string, string> header = new Dictionary<string, string>();
            header.Add("Content-Type", "application/json");
            string ingest = string.IsNullOrWhiteSpace(config.PluginStatsIngestBaseUrl)
                ? "https://api.prostoj.store/v1/plugin-ingest"
                : config.PluginStatsIngestBaseUrl.TrimEnd('/');
            webrequest.Enqueue($"{ingest}/signs", requestBody, (code, response) => {}, this, RequestMethod.POST, header, timeout: 1F);
            signEvents.Clear();
        }

        [Command("sign.save")]
        private void SaveTeamCMD(IPlayer iPlayer, string command, string[] args)
        {
            Puts("Sign Statistics: SaveTeamCMD.");
            if (!iPlayer.IsServer) return;
            SaveAll();
        }
        #endregion

        #region Hooks
		void OnSignUpdated(Signage sign, BasePlayer player, int textureIndex = 0)
		{
			if (player == null || sign.GetTextureCRCs()[textureIndex] == 0)
			{
				return;
			}
			
            uint crc = sign.GetTextureCRCs()[textureIndex];
			byte[] image = FileStorage.server.Get(crc, FileStorage.Type.png, sign.NetworkID, (uint)textureIndex);
            
            //try
            //{
				if (signEvents.ContainsKey(sign.net.ID.Value.ToString()))
				{
					signEvents.Remove(sign.net.ID.Value.ToString());
				}
                signEvents.Add(sign.net.ID.Value.ToString(), new SignInfo
				{
					signId = sign.net.ID.Value.ToString(),
					steamId = player.UserIDString,
					base64Image = image,
					type = sign.ShortPrefabName,
					position = GetGrid(sign.transform.position)
				});
            //} catch {}
		}
        
        #endregion

        #region Utils

        private static string GetGrid(Vector3 pos)
        {
            var letter = 'A';
            var x = Mathf.Floor((pos.x + Server.worldsize / 2f) / 146.3f) % 26;
            var z = Mathf.Floor(Server.worldsize / 146.3f) -
                    Mathf.Floor((pos.z + Server.worldsize / 2f) / 146.3f);
            letter = (char)(letter + x);
            return $"{letter}{z}";
        }

        #endregion
    }
}