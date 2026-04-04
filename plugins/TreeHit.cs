using Newtonsoft.Json;
using System.Collections.Generic;

namespace Oxide.Plugins
{
    [Info("TreeHit", "Drop Dead", "1.0.0")]
    class TreeHit : RustPlugin
    {
        #region Config

        private PluginConfig cfg;

        public class PluginConfig
        {
            [JsonProperty("Пермишн для использования плагина | Permission for use plugin")]
            public string perm = "TreeHit.use";
            [JsonProperty("Список запрещенных предметов (с ними плагин взаимодействовать не будет) | List of prohibited items (the plugin will not interact with them)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public List<string> blacklist = new List<string>()
            {
                "rock",
                "example"
            };
        }

        protected override void LoadDefaultConfig()
        {
            Config.WriteObject(new PluginConfig(), true);
        }

        private void Init()
        {
            cfg = Config.ReadObject<PluginConfig>();
            Config.WriteObject(cfg);
        }

        #endregion

        #region Hooks

        void OnServerInitialized()
        {
            if (!permission.PermissionExists(cfg.perm)) permission.RegisterPermission(cfg.perm, this);
        }

        bool? OnTreeMarkerHit(TreeEntity tree, HitInfo hitInfo)
        {
            if (tree == null || hitInfo == null || hitInfo.Initiator == null || hitInfo.InitiatorPlayer == null) return null;
            if (hitInfo.InitiatorPlayer.GetActiveItem() == null) return null;
            if (cfg.blacklist.Contains(hitInfo.InitiatorPlayer.GetActiveItem().info.shortname)) return null;
            if (permission.UserHasPermission(hitInfo.InitiatorPlayer.UserIDString, cfg.perm)) return true;
            return null;
        }

        #endregion
    }
}