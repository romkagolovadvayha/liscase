using Oxide.Core;
using Oxide.Core.Plugins;
using Rust;
using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Door Skin Finder", "Prostoj", "1.0.0")]
    [Description("Находит и удаляет МВК двери с определенными скинами")]
    public class DoorSkinFinder : RustPlugin
    {
        // Список скинов для поиска
        private readonly HashSet<ulong> targetSkins = new HashSet<ulong>
        {
            2396716738,
            2271001808,
            2286118971
        };

        // Shortname дверей для проверки
        private readonly HashSet<string> doorShortnames = new HashSet<string>
        {
            "door.hinged",
            "door.double.hinged",
            "door.hinged.toptier",
            "door.double.hinged.toptier"
        };

        // Структура для хранения информации о найденной двери/предмете
        private class DoorInfo
        {
            public Door DeployedDoor { get; set; }
            public Item DoorItem { get; set; }
            public string Location { get; set; } // "deployed", "player_inventory", "container", "dropped"
            public ulong OwnerId { get; set; }
            public string OwnerName { get; set; }
            public Vector3 Position { get; set; }
            public ulong SkinId { get; set; }
            public string ContainerInfo { get; set; } // Дополнительная информация о контейнере
        }

        private void Init()
        {
            cmd.AddConsoleCommand("doorskin.find", this, nameof(FindDoorsCommand));
            cmd.AddConsoleCommand("doorskin.remove", this, nameof(RemoveDoorsCommand));
        }

        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["NoDoorsFound"] = "Двери с указанными скинами не найдены.",
                ["DoorsFound"] = "Найдено дверей: {0}",
                ["DoorInfo"] = "[{0}] Скин: {1}, Позиция: {2}, Владелец: {3}, Расположение: {4}",
                ["DoorsRemoved"] = "Удалено дверей/предметов: {0}",
                ["Searching"] = "Поиск дверей (развернутые, в инвентарях, контейнерах, выброшенные)...",
                ["NoPermission"] = "У вас нет прав для использования этой команды."
            }, this, "ru");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["NoDoorsFound"] = "No doors with specified skins found.",
                ["DoorsFound"] = "Found doors: {0}",
                ["DoorInfo"] = "[{0}] Skin: {1}, Position: {2}, Owner: {3}, Location: {4}",
                ["DoorsRemoved"] = "Removed doors/items: {0}",
                ["Searching"] = "Searching doors (deployed, in inventories, containers, dropped)...",
                ["NoPermission"] = "You don't have permission to use this command."
            }, this, "en");
        }

        // Проверяет, является ли предмет дверью
        private bool IsDoorItem(Item item)
        {
            if (item == null || item.info == null)
                return false;
            
            return doorShortnames.Contains(item.info.shortname);
        }

        // Ищет все двери и предметы дверей с указанными скинами
        private List<DoorInfo> FindAllDoorsWithTargetSkins()
        {
            var foundDoors = new List<DoorInfo>();

            // 1. Ищем развернутые двери на сервере
            foreach (var entity in BaseNetworkable.serverEntities)
            {
                if (entity == null || entity.IsDestroyed)
                    continue;

                var door = entity as Door;
                if (door == null || door.transform == null)
                    continue;

                if (door.skinID != 0 && targetSkins.Contains(door.skinID))
                {
                    var ownerId = door.OwnerID;
                    var ownerName = ownerId.IsSteamId() 
                        ? covalence.Players.FindPlayerById(ownerId.ToString())?.Name ?? "Unknown" 
                        : "Unknown";

                    foundDoors.Add(new DoorInfo
                    {
                        DeployedDoor = door,
                        Location = "Развернута",
                        OwnerId = ownerId,
                        OwnerName = ownerName,
                        Position = door.transform.position,
                        SkinId = door.skinID
                    });
                }
            }

            // 2. Ищем в инвентарях всех игроков (активных и спящих)
            foreach (var player in BasePlayer.allPlayerList)
            {
                if (player == null || player.inventory == null)
                    continue;

                CheckPlayerInventory(player, foundDoors);
            }

            // 3. Ищем в контейнерах на сервере
            foreach (var entity in BaseNetworkable.serverEntities)
            {
                if (entity == null || entity.IsDestroyed)
                    continue;

                var container = entity as StorageContainer;
                if (container == null || container.inventory == null)
                    continue;

                CheckContainer(container, foundDoors);
            }

            // 4. Ищем выброшенные предметы
            foreach (var entity in BaseNetworkable.serverEntities)
            {
                if (entity == null || entity.IsDestroyed)
                    continue;

                var droppedItem = entity as DroppedItem;
                if (droppedItem == null || droppedItem.item == null)
                    continue;

                if (IsDoorItem(droppedItem.item) && 
                    droppedItem.item.skin != 0 && 
                    targetSkins.Contains(droppedItem.item.skin))
                {
                    var ownerId = droppedItem.DroppedBy;
                    var ownerName = ownerId.IsSteamId() 
                        ? covalence.Players.FindPlayerById(ownerId.ToString())?.Name ?? "Unknown" 
                        : "Unknown";

                    foundDoors.Add(new DoorInfo
                    {
                        DoorItem = droppedItem.item,
                        Location = "Выброшена",
                        OwnerId = ownerId,
                        OwnerName = ownerName,
                        Position = droppedItem.transform.position,
                        SkinId = droppedItem.item.skin,
                        ContainerInfo = $"DroppedItem ID: {droppedItem.net.ID}"
                    });
                }
            }

            return foundDoors;
        }

        // Проверяет инвентарь игрока
        private void CheckPlayerInventory(BasePlayer player, List<DoorInfo> foundDoors)
        {
            if (player.inventory == null)
                return;

            var containers = new[] 
            { 
                player.inventory.containerBelt,
                player.inventory.containerMain,
                player.inventory.containerWear
            };

            foreach (var container in containers)
            {
                if (container == null || container.itemList == null)
                    continue;

                foreach (var item in container.itemList)
                {
                    if (IsDoorItem(item) && item.skin != 0 && targetSkins.Contains(item.skin))
                    {
                        var ownerName = player.displayName ?? "Unknown";
                        foundDoors.Add(new DoorInfo
                        {
                            DoorItem = item,
                            Location = $"Инвентарь игрока ({GetContainerName(container)})",
                            OwnerId = player.userID,
                            OwnerName = ownerName,
                            Position = player.transform != null ? player.transform.position : Vector3.zero,
                            SkinId = item.skin,
                            ContainerInfo = $"Player: {ownerName}, Container: {GetContainerName(container)}"
                        });
                    }
                }
            }
        }

        // Проверяет контейнер
        private void CheckContainer(StorageContainer container, List<DoorInfo> foundDoors)
        {
            if (container.inventory == null || container.inventory.itemList == null)
                return;

            foreach (var item in container.inventory.itemList)
            {
                if (IsDoorItem(item) && item.skin != 0 && targetSkins.Contains(item.skin))
                {
                    var ownerId = container.OwnerID;
                    var ownerName = ownerId.IsSteamId() 
                        ? covalence.Players.FindPlayerById(ownerId.ToString())?.Name ?? "Unknown" 
                        : "Unknown";

                    var containerName = container.ShortPrefabName ?? "Unknown";
                    var position = container.transform != null ? container.transform.position : Vector3.zero;

                    foundDoors.Add(new DoorInfo
                    {
                        DoorItem = item,
                        Location = $"Контейнер ({containerName})",
                        OwnerId = ownerId,
                        OwnerName = ownerName,
                        Position = position,
                        SkinId = item.skin,
                        ContainerInfo = $"Container: {containerName}, ID: {container.net.ID}"
                    });
                }
            }
        }

        // Получает имя контейнера
        private string GetContainerName(ItemContainer container)
        {
            if (container == null)
                return "Unknown";

            if (container == container.playerOwner?.inventory?.containerBelt)
                return "Belt";
            if (container == container.playerOwner?.inventory?.containerMain)
                return "Main";
            if (container == container.playerOwner?.inventory?.containerWear)
                return "Wear";

            return "Unknown";
        }

        private void FindDoorsCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null && !arg.Player().IsAdmin)
            {
                SendReply(arg, lang.GetMessage("NoPermission", this, arg.Player()?.UserIDString ?? ""));
                return;
            }

            SendReply(arg, lang.GetMessage("Searching", this, arg.Player()?.UserIDString ?? ""));
            
            var doors = FindAllDoorsWithTargetSkins();

            if (doors.Count == 0)
            {
                SendReply(arg, lang.GetMessage("NoDoorsFound", this, arg.Player()?.UserIDString ?? ""));
                return;
            }

            SendReply(arg, string.Format(lang.GetMessage("DoorsFound", this, arg.Player()?.UserIDString ?? ""), doors.Count));
            SendReply(arg, "---");

            foreach (var doorInfo in doors)
            {
                var positionStr = doorInfo.Position != Vector3.zero 
                    ? $"X:{doorInfo.Position.x:F1} Y:{doorInfo.Position.y:F1} Z:{doorInfo.Position.z:F1}"
                    : "N/A";
                
                var idStr = doorInfo.DeployedDoor != null 
                    ? $"Door ID: {doorInfo.DeployedDoor.net.ID}" 
                    : $"Item ID: {(doorInfo.DoorItem != null ? doorInfo.DoorItem.uid.ToString() : "0")}";
                
                var info = string.Format(
                    lang.GetMessage("DoorInfo", this, arg.Player()?.UserIDString ?? ""),
                    idStr,
                    doorInfo.SkinId,
                    positionStr,
                    $"{doorInfo.OwnerName} ({doorInfo.OwnerId})",
                    doorInfo.Location
                );
                
                SendReply(arg, info);
                if (!string.IsNullOrEmpty(doorInfo.ContainerInfo))
                {
                    SendReply(arg, $"  Доп. инфо: {doorInfo.ContainerInfo}");
                }
            }

            SendReply(arg, "---");
            Puts($"[DoorSkinFinder] Found {doors.Count} doors/items with target skins");
        }

        private void RemoveDoorsCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null && !arg.Player().IsAdmin)
            {
                SendReply(arg, lang.GetMessage("NoPermission", this, arg.Player()?.UserIDString ?? ""));
                return;
            }

            SendReply(arg, lang.GetMessage("Searching", this, arg.Player()?.UserIDString ?? ""));
            
            var doors = FindAllDoorsWithTargetSkins();

            if (doors.Count == 0)
            {
                SendReply(arg, lang.GetMessage("NoDoorsFound", this, arg.Player()?.UserIDString ?? ""));
                return;
            }

            SendReply(arg, string.Format(lang.GetMessage("DoorsFound", this, arg.Player()?.UserIDString ?? ""), doors.Count));
            SendReply(arg, "---");

            int removedCount = 0;
            foreach (var doorInfo in doors)
            {
                var positionStr = doorInfo.Position != Vector3.zero 
                    ? $"X:{doorInfo.Position.x:F1} Y:{doorInfo.Position.y:F1} Z:{doorInfo.Position.z:F1}"
                    : "N/A";
                
                var idStr = doorInfo.DeployedDoor != null 
                    ? $"Door ID: {doorInfo.DeployedDoor.net.ID}" 
                    : $"Item ID: {(doorInfo.DoorItem != null ? doorInfo.DoorItem.uid.ToString() : "0")}";
                
                var info = string.Format(
                    lang.GetMessage("DoorInfo", this, arg.Player()?.UserIDString ?? ""),
                    idStr,
                    doorInfo.SkinId,
                    positionStr,
                    $"{doorInfo.OwnerName} ({doorInfo.OwnerId})",
                    doorInfo.Location
                );
                
                SendReply(arg, info + " - УДАЛЕНО");
                
                // Удаляем дверь или предмет
                if (doorInfo.DeployedDoor != null && !doorInfo.DeployedDoor.IsDestroyed)
                {
                    // Удаляем развернутую дверь
                    doorInfo.DeployedDoor.Kill(BaseNetworkable.DestroyMode.Gib);
                    removedCount++;
                }
                else if (doorInfo.DoorItem != null)
                {
                    // Удаляем предмет двери
                    doorInfo.DoorItem.Remove();
                    removedCount++;
                }
            }

            SendReply(arg, "---");
            SendReply(arg, string.Format(lang.GetMessage("DoorsRemoved", this, arg.Player()?.UserIDString ?? ""), removedCount));
            Puts($"[DoorSkinFinder] Removed {removedCount} doors/items with target skins");
        }
    }
}

