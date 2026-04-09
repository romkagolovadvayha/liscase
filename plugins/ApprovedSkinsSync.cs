using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Configuration;
using Oxide.Core.Libraries;
using Oxide.Core.Libraries.Covalence;
using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Approved Skins Sync", "prostoj.store", "1.0.0")]
    [Description("Синхронизирует список одобренных скинов с API сервиса")]
    public class ApprovedSkinsSync : RustPlugin
    {
        #region Config
        static Configuration config;

        public class Configuration
        {
            [JsonProperty(PropertyName = "API URL")]
            public string apiUrl = "https://api.moscow77.store/skins/approved";

            [JsonProperty(PropertyName = "Sync Interval (minutes)")]
            public int syncInterval = 60;

            [JsonProperty(PropertyName = "Enable Auto Sync")]
            public bool enableAutoSync = true;
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
                PrintWarning($"Ошибка загрузки конфигурации: {e.Message}. Создание нового файла.");
                LoadDefaultConfig();
            }
        }

        protected override void LoadDefaultConfig() => config = new Configuration
        {
            apiUrl = "https://api.moscow77.store/skins/approved",
            syncInterval = 60,
            enableAutoSync = true
        };
        protected override void SaveConfig() => Config.WriteObject(config);
        #endregion

        #region Fields
        private Timer _syncTimer;
        private readonly HashSet<ulong> _approvedSkinIds = new HashSet<ulong>();
        #endregion

        #region Oxide Hooks
        void OnServerInitialized()
        {
            Puts("Approved Skins Sync: Инициализация плагина...");

            // Ждем немного, чтобы SteamInventory успел инициализироваться
            timer.Once(10f, () =>
            {
                // Первая синхронизация при загрузке сервера
                CollectAndSendApprovedSkins();
            });

            // Настройка автоматической синхронизации
            if (config.enableAutoSync)
            {
                int intervalSeconds = config.syncInterval * 60;
                _syncTimer = timer.Every(intervalSeconds, () =>
                {
                    CollectAndSendApprovedSkins();
                });
                Puts($"Approved Skins Sync: Автоматическая синхронизация включена (каждые {config.syncInterval} минут)");
            }
            else
            {
                Puts("Approved Skins Sync: Автоматическая синхронизация отключена");
            }
        }

        void Unload()
        {
            if (_syncTimer != null)
            {
                _syncTimer.Destroy();
                _syncTimer = null;
            }
        }
        #endregion

        #region Core Logic
        /// <summary>
        /// Собирает список всех одобренных скинов и отправляет на API
        /// </summary>
        private void CollectAndSendApprovedSkins()
        {
            try
            {
                Puts("Approved Skins Sync: Начало сбора одобренных скинов...");

                _approvedSkinIds.Clear();

                // Проверяем, что SteamInventory инициализирован
                if (Steamworks.SteamInventory.Definitions == null)
                {
                    PrintError("Approved Skins Sync: SteamInventory.Definitions недоступен. Попробуйте позже.");
                    return;
                }

                // Создаем словарь для маппинга itemId -> skin из ItemSkinDirectory
                Dictionary<int, ItemSkinDirectory.Skin> itemIdToSkin = new Dictionary<int, ItemSkinDirectory.Skin>();

                // Заполняем маппинг из ItemSkinDirectory (если доступен)
                if (ItemSkinDirectory.Instance != null && ItemSkinDirectory.Instance.skins != null)
                {
                    for (int i = 0; i < ItemSkinDirectory.Instance.skins.Length; i++)
                    {
                        var skin = ItemSkinDirectory.Instance.skins[i];
                        if (skin.id > 0)
                        {
                            itemIdToSkin[skin.id] = skin;
                        }
                    }
                }

                // Проходим по всем определениям предметов
                foreach (ItemDefinition itemDefinition in ItemManager.itemList)
                {
                    // Проходим по всем определениям Steam Inventory
                    foreach (Steamworks.InventoryDef item in Steamworks.SteamInventory.Definitions)
                    {
                        string shortname = item.GetProperty("itemshortname");

                        // Пропускаем невалидные записи
                        if (string.IsNullOrEmpty(shortname) || item.Id < 100)
                            continue;

                        // Проверяем соответствие shortname
                        if (!shortname.Equals(itemDefinition.shortname, StringComparison.OrdinalIgnoreCase))
                            continue;

                        ulong skinId = 0;

                        // Логика получения skinId как в SkinBox.cs
                        if (itemIdToSkin.ContainsKey(item.Id))
                        {
                            // Используем item.Id как skinId (для одобренных скинов item.Id часто равен workshop ID)
                            skinId = (ulong)item.Id;
                        }
                        else if (ulong.TryParse(item.GetProperty("workshopid"), out skinId))
                        {
                            // Используем workshop ID из свойства
                        }
                        else
                        {
                            // Пропускаем, если не можем определить ID
                            continue;
                        }

                        // Добавляем ID в список (HashSet автоматически уберет дубликаты)
                        if (skinId > 0)
                        {
                            _approvedSkinIds.Add(skinId);
                        }
                    }
                }

                Puts($"Approved Skins Sync: Найдено {_approvedSkinIds.Count} одобренных скинов");

                if (_approvedSkinIds.Count == 0)
                {
                    PrintWarning("Approved Skins Sync: Не найдено ни одного одобренного скина. Возможно, SteamInventory еще не загружен.");
                    return;
                }

                // Отправляем на API
                SendToAPI(_approvedSkinIds.ToList());
            }
            catch (Exception ex)
            {
                PrintError($"Approved Skins Sync: Ошибка при сборе скинов: {ex.Message}");
                PrintError($"Stack trace: {ex.StackTrace}");
            }
        }

        /// <summary>
        /// Отправляет список скинов на API
        /// </summary>
        private void SendToAPI(List<ulong> skinIds)
        {
            if (skinIds == null || skinIds.Count == 0)
            {
                Puts("Approved Skins Sync: Список скинов пуст, отправка не требуется");
                return;
            }

            try
            {
                // Формируем JSON запрос
                var requestData = new
                {
                    approved_ids = skinIds
                };

                string jsonData = JsonConvert.SerializeObject(requestData);

                // Настраиваем заголовки
                Dictionary<string, string> headers = new Dictionary<string, string>();
                headers.Add("Content-Type", "application/json");

                Puts($"Approved Skins Sync: Отправка {skinIds.Count} скинов на API: {config.apiUrl}");

                // Отправляем запрос
                webrequest.Enqueue(
                    config.apiUrl,
                    jsonData,
                    (code, response) =>
                    {
                        if (code == 200)
                        {
                            Puts($"Approved Skins Sync: Успешно отправлено {skinIds.Count} скинов на API");
                        }
                        else
                        {
                            PrintError($"Approved Skins Sync: Ошибка при отправке на API. Код ответа: {code}");
                            if (!string.IsNullOrEmpty(response))
                            {
                                PrintError($"Ответ сервера: {response}");
                            }
                        }
                    },
                    this,
                    RequestMethod.POST,
                    headers,
                    timeout: 30f
                );
            }
            catch (Exception ex)
            {
                PrintError($"Approved Skins Sync: Ошибка при отправке на API: {ex.Message}");
                PrintError($"Stack trace: {ex.StackTrace}");
            }
        }
        #endregion

        #region Commands
        [Command("skins.sync")]
        private void SyncCommand(IPlayer player, string command, string[] args)
        {
            // Команда доступна только с сервера или администраторам
            if (!player.IsServer && !permission.UserHasPermission(player.Id, "approvedskinsync.admin"))
            {
                player.Reply("У вас нет прав для выполнения этой команды");
                return;
            }

            player.Reply("Запуск синхронизации одобренных скинов...");
            CollectAndSendApprovedSkins();
        }

        [Command("skins.sync.status")]
        private void StatusCommand(IPlayer player, string command, string[] args)
        {
            if (!player.IsServer && !permission.UserHasPermission(player.Id, "approvedskinsync.admin"))
            {
                player.Reply("У вас нет прав для выполнения этой команды");
                return;
            }

            player.Reply($"=== Approved Skins Sync Status ===");
            player.Reply($"API URL: {config.apiUrl}");
            player.Reply($"Интервал синхронизации: {config.syncInterval} минут");
            player.Reply($"Автосинхронизация: {(config.enableAutoSync ? "Включена" : "Отключена")}");
            player.Reply($"Таймер активен: {(_syncTimer != null ? "Да" : "Нет")}");
            player.Reply($"Последний собранный список: {_approvedSkinIds.Count} скинов");
        }
        #endregion

        #region Permissions
        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["NoPermission"] = "У вас нет прав для выполнения этой команды"
            }, this);
        }
        #endregion
    }
}

