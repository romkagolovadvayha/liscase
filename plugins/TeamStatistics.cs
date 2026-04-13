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
    [Info("Team Statistics", "Prostoj", "1.1.0")]
    [Description("Team Statistics.")]
    public class TeamStatistics : CovalencePlugin
    {
        private const string DefaultTeamsIngestUrl = "https://api.prostoj.store/v1/plugin-ingest/teams";

        private class PluginConfiguration
        {
            [JsonProperty("teamsIngestUrl")]
            public string TeamsIngestUrl = DefaultTeamsIngestUrl;
        }

        private PluginConfiguration _config;

        protected override void LoadDefaultConfig()
        {
            _config = new PluginConfiguration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                _config = Config.ReadObject<PluginConfiguration>() ?? new PluginConfiguration();
            }
            catch
            {
                PrintError("Team Statistics: ошибка конфига, сброс к умолчаниям.");
                LoadDefaultConfig();
                return;
            }

            if (string.IsNullOrWhiteSpace(_config.TeamsIngestUrl))
            {
                _config.TeamsIngestUrl = DefaultTeamsIngestUrl;
                SaveConfig();
            }
        }

        protected override void SaveConfig() => Config.WriteObject(_config);

        private void Init()
        {
            LoadConfig();
        }

        #region State
        private class TeamInfo
        {
            public string LeaderSteamId { get; set; } // SteamID лидера команды
            public List<string> Members { get; set; } // Список SteamID всех участников команды
            public string Action { get; set; } // Тип изменения (created, updated, disbanded)
            public string Timestamp { get; set; } // Время события
        }

        private List<TeamInfo> teamEvents = new List<TeamInfo>();
        #endregion

        void OnServerInitialized(bool initial)
        {
            Puts("Team Statistics: OnServerInitialized 1.");

			// начальный снимок всех команд
			TrySnapshotAllTeams();

            timer.Every(1 * 60, () =>
            {
                SaveAll();
            });
        }
        void Unload()
        {
            teamEvents.Clear();
            SaveAll();
        }

		private void TrySnapshotAllTeams()
		{
			var rm = RelationshipManager.ServerInstance;
			if (rm?.teams == null) return; // в разных версиях поле может называться иначе

			foreach (var kv in rm.teams) // kv.Value: RelationshipManager.PlayerTeam
			{
				var team = kv.Value;

				teamEvents.Add(new TeamInfo
				{
					LeaderSteamId = team.teamLeader.ToString(),
					Members = team.members.Select(m => m.ToString()).ToList(),
					Action = "updated",
					Timestamp = DateTime.UtcNow.ToString("yyyy-MM-dd HH:mm:ss")
				});
			}
		}

        #region Save
        void SaveAll()
        {
            if (teamEvents.Count == 0) {
                return;
            }

            string requestBody = JsonConvert.SerializeObject(
                new {
                    teams = teamEvents,
                    ip = Server.ip,
                    port = Server.port,
                }).Replace("\n", "").Replace("  ", "");

            Dictionary<string, string> header = new Dictionary<string, string>();
            header.Add("Content-Type", "application/json");
            webrequest.Enqueue(_config.TeamsIngestUrl, requestBody, (code, response) => {}, this, RequestMethod.POST, header, timeout: 1F);
            teamEvents.Clear();
        }

        [Command("team.save")]
        private void SaveTeamCMD(IPlayer iPlayer, string command, string[] args)
        {
            Puts("Team Statistics: SaveTeamCMD.");
            if (!iPlayer.IsServer) return;
            SaveAll();
        }
        #endregion

        #region Hooks
      private void OnTeamCreate(RelationshipManager.PlayerTeam team, BasePlayer leader)
        {
            // Когда создается новая команда
            teamEvents.Add(new TeamInfo
            {
                LeaderSteamId = leader.UserIDString,
                Members = team.members.Select(m => m.ToString()).ToList(),
                Action = "created",
                Timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
            });
        }

        private void OnTeamAcceptInvite(RelationshipManager.PlayerTeam team, BasePlayer player)
        {
            // Когда игрок принимает приглашение в команду
            teamEvents.Add(new TeamInfo
            {
                LeaderSteamId = team.teamLeader.ToString(),
                Members = team.members.Select(m => m.ToString()).ToList(),
                Action = "updated",
                Timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
            });
        }

        private void OnTeamLeave(RelationshipManager.PlayerTeam team, BasePlayer player)
        {
            // Когда игрок покидает команду
            teamEvents.Add(new TeamInfo
            {
                LeaderSteamId = team.teamLeader.ToString(),
                Members = team.members.Select(m => m.ToString()).ToList(),
                Action = "updated",
                Timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
            });
        }

        private void OnTeamKick(RelationshipManager.PlayerTeam team, BasePlayer player, ulong target)
        {
            // Когда игрока выгоняют из команды
            teamEvents.Add(new TeamInfo
            {
                LeaderSteamId = team.teamLeader.ToString(),
                Members = team.members.Select(m => m.ToString()).ToList(),
                Action = "updated",
                Timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
            });
        }

        private void OnTeamDisband(RelationshipManager.PlayerTeam team)
        {
            // Когда команда распадается
            teamEvents.Add(new TeamInfo
            {
                LeaderSteamId = team.teamLeader.ToString(),
                Members = new List<string>(), // Команда распалась, участников нет
                Action = "disbanded",
                Timestamp = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss")
            });
        }
        #endregion
    }
}
