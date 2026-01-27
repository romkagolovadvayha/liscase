using Oxide.Core;
using System.Collections.Generic;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Redirect", "Ghosty", "1.0.1")]
    [Description("Redirects players to your new server")]
    class Redirect : RustPlugin
    {
        private ConfigData configData;

        class ConfigData
        {
            public string ServerIP { get; set; }
            public string ServerPort { get; set; }
            public string RedirectMessage { get; set; }
            public float RedirectDelay { get; set; }
        }

        protected override void LoadDefaultConfig()
        {
            configData = new ConfigData
            {
                ServerIP = "185.189.255.147",
                ServerPort = "35000",
                RedirectMessage = "<size=20><color=red>Оповещение</color>: Сервер сменил IP, вы будете перенаправлены через {0} секунд.</size>",
                RedirectDelay = 10.0f
            };
            SaveConfig(configData);
        }

        private void OnPlayerSleepEnded(BasePlayer player)
        {
            if (permission.UserHasPermission(player.UserIDString, "redirect.admin"))
            {
                PrintToChat(player, "<size=20>Admin permission detected.\n<color=green>Redirecting bypassed..</color></size>");
                return;
            }

            timer.Once(configData.RedirectDelay, () =>
            {
                if (player.IsConnected)
                {
                    ConsoleNetwork.SendClientCommandImmediate(player.Connection, "nexus.redirect", configData.ServerIP, configData.ServerPort, "");
                    ConnectionAuth.Reject(player.Connection, "Redirecting to new server...");
                    PlatformService.Instance.EndPlayerSession(player.userID);
                }
            });

            PrintToChat(player, string.Format(configData.RedirectMessage, configData.RedirectDelay));
        }

        private void SaveConfig(ConfigData config)
        {
            Config.WriteObject(config, true);
        }

        private void LoadConfig()
        {
            try
            {
                configData = Config.ReadObject<ConfigData>();
            }
            catch
            {
                LoadDefaultConfig();
            }
        }

        private void Init()
        {
            LoadConfig();
            permission.RegisterPermission("redirect.admin", this);
        }
    }
}
 