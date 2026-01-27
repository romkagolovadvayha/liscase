using System;
using System.Collections.Generic;
using System.Linq;
using Oxide.Core;
using Oxide.Core.Plugins;
using Rust;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Team Restrictions", "GPT-5 Codex", "1.0.0")]
    [Description("Ограничения на команды: лимит участников, запрет удаления команды, ограничение авторизаций в турелях, запрет обмена ресурсами")]
    public class TeamRestrictions : RustPlugin
    {
        #region Configuration

        private Configuration config;

        private class Configuration
        {
            public int TeamLimit { get; set; } = 3;
            public int TurretAuthLimit { get; set; } = 3;
        }

        protected override void LoadDefaultConfig()
        {
            config = new Configuration();
            SaveConfig();
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

        protected override void SaveConfig() => Config.WriteObject(config);

        #endregion

        #region Data

        private StoredData storedData;

        private class StoredData
        {
            public Dictionary<ulong, TeamData> Teams = new Dictionary<ulong, TeamData>();
            public Dictionary<uint, DroppedItemData> DroppedItems = new Dictionary<uint, DroppedItemData>();
        }

        private class TeamData
        {
            public ulong TeamId { get; set; }
            public List<ulong> Members { get; set; } = new List<ulong>();
            public DateTime CreatedAt { get; set; } = DateTime.Now;
        }

        private class DroppedItemData
        {
            public ulong DroppedBy { get; set; }
            public DateTime DroppedAt { get; set; } = DateTime.Now;
            public string ItemShortName { get; set; }
            public int ItemAmount { get; set; }
        }

        private void LoadData()
        {
            storedData = Interface.Oxide.DataFileSystem.ReadObject<StoredData>(Name);
            if (storedData == null)
            {
                storedData = new StoredData();
                SaveData();
            }
        }

        private void SaveData()
        {
            Interface.Oxide.DataFileSystem.WriteObject(Name, storedData);
        }

        #endregion

        #region Messages

        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["TeamLimitReached"] = "Достигнут лимит команды! Максимум {0} участников.",
                ["TeamDisbandForbidden"] = "Нельзя удалить команду! Команда сохраняется весь вайп.",
                ["TeamLeaveForbidden"] = "Нельзя покинуть команду! Команда сохраняется весь вайп.",
                ["TeamKickForbidden"] = "Нельзя удалить игрока из команды! Команда сохраняется весь вайп.",
                ["TurretAuthLimitReached"] = "Достигнут лимит авторизаций в турели! Максимум {0} авторизаций.",
                ["ResourceExchangeForbidden"] = "Запрещен обмен ресурсами с игроками вне команды!",
                ["ItemDroppedBy"] = "Предмет выброшен игроком {0}",
                ["ItemPickedBy"] = "Предмет поднят игроком {0}"
            }, this, "ru");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["TeamLimitReached"] = "Team limit reached! Maximum {0} members.",
                ["TeamDisbandForbidden"] = "Cannot disband team! Team persists for the entire wipe.",
                ["TeamLeaveForbidden"] = "Cannot leave team! Team persists for the entire wipe.",
                ["TeamKickForbidden"] = "Cannot kick player from team! Team persists for the entire wipe.",
                ["TurretAuthLimitReached"] = "Turret authorization limit reached! Maximum {0} authorizations.",
                ["ResourceExchangeForbidden"] = "Resource exchange with players outside the team is forbidden!",
                ["ItemDroppedBy"] = "Item dropped by player {0}",
                ["ItemPickedBy"] = "Item picked up by player {0}"
            }, this, "en");
        }

        private string GetMessage(string key, BasePlayer player = null, params object[] args)
        {
            var message = lang.GetMessage(key, this, player?.UserIDString);
            return args.Length > 0 ? string.Format(message, args) : message;
        }

        #endregion

        #region Oxide Hooks

        private void Init()
        {
            LoadData();
        }

        private void OnServerInitialized()
        {
            // Очищаем старые выброшенные предметы (старше 1 минуты)
            timer.Every(30f, () =>
            {
                CleanupOldDroppedItems();
            });
        }

        private void OnServerSave()
        {
            SaveData();
        }

        #endregion

        #region Team Hooks

        private object OnTeamCreate(RelationshipManager.PlayerTeam team)
        {
            if (team == null) return null;

            // Сохраняем информацию о команде
            if (!storedData.Teams.ContainsKey(team.teamID))
            {
                var teamData = new TeamData
                {
                    TeamId = team.teamID,
                    Members = new List<ulong>(team.members),
                    CreatedAt = DateTime.Now
                };
                storedData.Teams[team.teamID] = teamData;
                SaveData();
            }

            return null;
        }

        private object OnTeamAcceptInvite(RelationshipManager.PlayerTeam team, BasePlayer player)
        {
            if (team == null || player == null) return null;

            // Проверяем лимит команды (учитываем, что игрок еще не добавлен)
            if (team.members.Count + 1 > config.TeamLimit)
            {
                var leader = BasePlayer.FindByID(team.teamLeader);
                if (leader != null && leader.IsConnected)
                {
                    SendWarning(leader, GetMessage("TeamLimitReached", leader, config.TeamLimit));
                }
                SendWarning(player, GetMessage("TeamLimitReached", player, config.TeamLimit));
                return false; // Блокируем принятие приглашения
            }

            // Обновляем данные команды после добавления
            NextTick(() => UpdateTeamData(team));

            return null;
        }

        private object OnTeamLeave(RelationshipManager.PlayerTeam team, BasePlayer player)
        {
            if (team == null || player == null) return null;

            // Запрещаем выход из команды
            SendWarning(player, GetMessage("TeamLeaveForbidden", player));
            return false; // Блокируем выход
        }

        private object OnTeamKick(RelationshipManager.PlayerTeam team, BasePlayer player, ulong target)
        {
            if (team == null || player == null) return null;

            // Запрещаем удаление игрока из команды
            SendWarning(player, GetMessage("TeamKickForbidden", player));
            return false; // Блокируем кик
        }

        private object OnTeamDisband(RelationshipManager.PlayerTeam team)
        {
            if (team == null) return null;

            // Разрешаем удаление команды только если в ней никого нет (или только лидер)
            if (team.members.Count > 1)
            {
                // Запрещаем удаление команды, если в ней есть участники
                var leader = BasePlayer.FindByID(team.teamLeader);
                if (leader != null && leader.IsConnected)
                {
                    SendWarning(leader, GetMessage("TeamDisbandForbidden", leader));
                }

                // Уведомляем всех участников
                foreach (var memberId in team.members)
                {
                    var member = BasePlayer.FindByID(memberId);
                    if (member != null && member.IsConnected && member.userID != team.teamLeader)
                    {
                        SendWarning(member, GetMessage("TeamDisbandForbidden", member));
                    }
                }

                return false; // Блокируем удаление команды
            }

            // Разрешаем удаление пустой команды
            // Удаляем из сохраненных данных
            if (storedData.Teams.ContainsKey(team.teamID))
            {
                storedData.Teams.Remove(team.teamID);
                SaveData();
            }

            return null;
        }

        #endregion

        #region Turret Hooks

        private object CanUseLockedEntity(BasePlayer player, BaseLock baseLock)
        {
            if (player == null || baseLock == null) return null;

            var turret = baseLock.GetParentEntity() as AutoTurret;
            if (turret == null) return null;

            // Проверяем лимит авторизаций
            if (turret.authorizedPlayers.Count >= config.TurretAuthLimit)
            {
                // Если игрок уже авторизован, разрешаем доступ
                if (turret.authorizedPlayers.Contains(player.userID))
                {
                    return null;
                }

                // Иначе блокируем
                SendWarning(player, GetMessage("TurretAuthLimitReached", player, config.TurretAuthLimit));
                return false;
            }

            return null;
        }

        #endregion

        #region Item Drop/Pickup Hooks

        private void OnEntitySpawned(BaseEntity entity)
        {
            if (entity == null) return;

            var droppedItem = entity as DroppedItem;
            if (droppedItem == null || droppedItem.item == null) return;

            // Запоминаем кто выбросил предмет
            var droppedBy = droppedItem.DroppedBy;
            if (droppedBy != 0 && droppedBy.IsSteamId())
            {
                var itemData = new DroppedItemData
                {
                    DroppedBy = droppedBy,
                    DroppedAt = DateTime.Now,
                    ItemShortName = droppedItem.item.info.shortname,
                    ItemAmount = droppedItem.item.amount
                };
                storedData.DroppedItems[(uint)droppedItem.net.ID.Value] = itemData;
                SaveData();
            }
        }

        private object OnItemPickup(Item item, BasePlayer player)
        {
            if (item == null || player == null) return null;

            // Ищем выброшенный предмет
            var droppedItem = item.GetWorldEntity() as DroppedItem;
            if (droppedItem == null) return null;

            if (!storedData.DroppedItems.TryGetValue((uint)droppedItem.net.ID.Value, out var itemData))
            {
                return null; // Не наш предмет или уже забыли
            }

            // Проверяем, не прошла ли минута с момента выброса
            var timeSinceDropped = (DateTime.Now - itemData.DroppedAt).TotalMinutes;
            if (timeSinceDropped > 1)
            {
                // Предмет старше минуты - удаляем из памяти и разрешаем подбор
                storedData.DroppedItems.Remove((uint)droppedItem.net.ID.Value);
                SaveData();
                return null;
            }

            var droppedBy = itemData.DroppedBy;
            if (droppedBy == 0 || !droppedBy.IsSteamId())
            {
                storedData.DroppedItems.Remove((uint)droppedItem.net.ID.Value);
                SaveData();
                return null;
            }

            // Если игрок поднимает свой предмет - разрешаем
            if (droppedBy == player.userID)
            {
                storedData.DroppedItems.Remove((uint)droppedItem.net.ID.Value);
                SaveData();
                return null;
            }

            // Проверяем, в одной ли команде
            var dropper = BasePlayer.FindByID(droppedBy);
            bool inSameTeam = false;

            if (dropper != null && dropper.IsConnected)
            {
                // Оба игрока онлайн - проверяем через RelationshipManager
                var playerTeam = RelationshipManager.ServerInstance.FindTeam(player.userID);
                var dropperTeam = RelationshipManager.ServerInstance.FindTeam(droppedBy);

                inSameTeam = playerTeam != null && dropperTeam != null && playerTeam.teamID == dropperTeam.teamID;
            }
            else
            {
                // Игрок вышел, проверяем по сохраненным данным команды
                inSameTeam = ArePlayersInSameTeam(player.userID, droppedBy);
            }

            if (!inSameTeam)
            {
                SendWarning(player, GetMessage("ResourceExchangeForbidden", player));
                if (dropper != null && dropper.IsConnected)
                {
                    SendWarning(dropper, GetMessage("ResourceExchangeForbidden", dropper));
                }
                storedData.DroppedItems.Remove((uint)droppedItem.net.ID.Value);
                SaveData();
                return false; // Блокируем подбор
            }

            // Разрешаем подбор - они в одной команде
            storedData.DroppedItems.Remove((uint)droppedItem.net.ID.Value);
            SaveData();
            return null;
        }

        private void OnEntityKill(BaseEntity entity)
        {
            if (entity == null) return;

            var droppedItem = entity as DroppedItem;
            if (droppedItem != null)
            {
                // Удаляем из памяти при уничтожении предмета
                storedData.DroppedItems.Remove((uint)droppedItem.net.ID.Value);
                SaveData();
            }
        }

        #endregion

        #region Helper Methods

        private void UpdateTeamData(RelationshipManager.PlayerTeam team)
        {
            if (team == null) return;

            if (!storedData.Teams.ContainsKey(team.teamID))
            {
                storedData.Teams[team.teamID] = new TeamData
                {
                    TeamId = team.teamID,
                    Members = new List<ulong>(team.members),
                    CreatedAt = DateTime.Now
                };
            }
            else
            {
                storedData.Teams[team.teamID].Members = new List<ulong>(team.members);
            }
            SaveData();
        }

        private bool ArePlayersInSameTeam(ulong player1Id, ulong player2Id)
        {
            // Проверяем по сохраненным данным
            foreach (var teamData in storedData.Teams.Values)
            {
                if (teamData.Members.Contains(player1Id) && teamData.Members.Contains(player2Id))
                {
                    return true;
                }
            }

            // Проверяем через RelationshipManager (если игроки онлайн)
            var team1 = RelationshipManager.ServerInstance.FindTeam(player1Id);
            var team2 = RelationshipManager.ServerInstance.FindTeam(player2Id);

            return team1 != null && team2 != null && team1.teamID == team2.teamID;
        }

        private void CleanupOldDroppedItems()
        {
            var now = DateTime.Now;
            var toRemove = new List<uint>();

            foreach (var kvp in storedData.DroppedItems)
            {
                // Удаляем предметы старше 1 минуты
                if ((now - kvp.Value.DroppedAt).TotalMinutes > 1)
                {
                    toRemove.Add(kvp.Key);
                }
            }

            foreach (var key in toRemove)
            {
                storedData.DroppedItems.Remove(key);
            }

            if (toRemove.Count > 0)
            {
                SaveData();
            }
        }

        private void SendWarning(BasePlayer player, string message)
        {
            if (player == null || !player.IsConnected) return;
            player.ShowToast(GameTip.Styles.Red_Normal, message, true);
        }

        #endregion
    }
}

