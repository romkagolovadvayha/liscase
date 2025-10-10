using System;
using System.Collections;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using Oxide.Core;
using Oxide.Core.Configuration;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;
using WebSocketSharp;

namespace Oxide.Plugins
{
    [Info("ProstojRUST", "prostoj.store", "0.7.0")]
    public class ProstojRUST : RustPlugin
    {
        #region References

        [PluginReference] private Plugin ImageLibrary;

        #endregion

        #region Classes

        private static class Delays
        {
            //[JsonProperty("Игроки с активными запросами к АПИ")]
            public static List<ulong> ItemList = new List<ulong>();
            //[JsonProperty("Количество запросов за последнюю секунду")]
            public static int RequestPerSecond = 0;
            //[JsonProperty("Ограничение запросов в секунду")]
            public static int RequestPerSecondLimit = 20;

            public static bool CanRequest(BasePlayer player)
            {
                if (RequestPerSecond > RequestPerSecondLimit)
                {
                    return false;
                }

                if (ItemList.Contains(player.userID))
                {
                    //player.ChatMessage(instance._(player, "WaitPreviousAction"));
                    return false;
                }
                return true;
            }

            public static void FinishRequest(BasePlayer player)
            {
                if (ItemList.Contains(player.userID))
                    ItemList.Remove(player.userID);
            }

            public static IEnumerator MakeRequest(BasePlayer player)
            {
                RequestPerSecond++;

                if (ItemList.Contains(player.userID)) yield return null;
                ItemList.Add(player.userID);

                yield return new WaitForSeconds(3f);
                if (ItemList.Contains(player.userID))
                    ItemList.Remove(player.userID);
            }
        }

        private class Stats
        {

        }

        private class Configuration
        {
            public class API
            {
                [JsonProperty("ИД магазина в сервисе")]
                public string ShopID = "UNDEFINED";
                [JsonProperty("ИД сервера в сервисе")]
                public string ServerID = "UNDEFINED";
                [JsonProperty("Секретный ключ (не распространяйте его)")]
                public string SecretKey = "UNDEFINED";
            }

            public class Interface
            {
                [JsonProperty("Включить изображение корзины")]
                public bool BucketEnable = true;
                [JsonProperty("Включить отображение названий предметов")]
                public bool TextShow = true;
                [JsonProperty("Загружать встроенные изображения предметов")]
                public bool LoadSpriteImages = true;
                [JsonProperty("Ссылка на изображение корзины (BUCKET - стандартное изображение)")]
                public string BucketURL = "BUCKET";
                [JsonProperty("Количество предметов на строке")]
                public int ItemOnString = 7;
                [JsonProperty("Количество строк в интерфейсе")]
                public int StringAmount = 3;
                [JsonProperty("Сторона одного предмета")]
                public int ItemSide = 150;
                [JsonProperty("Отступ между предметами")]
                public int ItemMargin = 5;
				[JsonProperty("Настройки позиции изображение корзины")]
                public Position BucketPosition = new Position();

				public class Position
				{
					public string AnchorMin = "0 1";
					public string AnchorMax = "0 1";
					public string OffsetMin = "8 -40";
					public string OffsetMax = "43 -6";
				}
            }

            public class TOP
            {
                [JsonProperty("Отправлять данные топа игроков")]
                public bool UseTop = false;
            }

            [JsonProperty("Настройки API плагина")]
            public API APISettings = new API();
            [JsonProperty("Настройки интерфейса плагина")]
            public Interface InterfaceSettings = new Interface();
            [JsonProperty("Настройки статистики сервера")]
            public TOP TOPSettings = new TOP();
        }

        private class WItem
        {
            public string ID;
            public string Name;
            public int ItemID;
            public int Amount;
            public string ShortName;
            public string Command;
            public string ImageUrl;

            public bool Blocked;
            public double Block_Date = 0;
            public bool IsBlueprint;
            public bool IsCommand;
            public bool IsItem;
            public bool isFullOnly = true;
            public bool isBlockedBuilding = true;
			// 🔹 Новое свойство для поддропов
			public List<WSubDrop> SubDrop = new List<WSubDrop>();

			// 🔹 Дополнительный класс для subDrop
			public class WSubDrop
			{
				public string Command;
				public string Type;
				public int ItemID;
				public int Count;
			}

            public WItem(Dictionary<string, object> data, bool show = true)
            {
                try
                {
                    if (data.ContainsKey("id")) ID = data["id"].ToString();

                    if (data.ContainsKey("name")) Name = data["name"].ToString();
                    if (data.ContainsKey("item_id"))
					{
						if (int.TryParse(data["item_id"].ToString(), out int mainItemId))
						{
							ItemID = mainItemId;
						}
						else
						{
							ItemID = 0;
						}
					}
                    if (data.ContainsKey("amount")) Amount = System.Convert.ToInt32(data["amount"]);
                    if (data.ContainsKey("command")) Command = data["command"].ToString();
                    if (data.ContainsKey("img")) ImageUrl = data["img"].ToString();

                    if (data.ContainsKey("blocked")) Blocked = bool.Parse(data["blocked"].ToString());
                    if (data.ContainsKey("full_only"))
					{
						var val = data["full_only"].ToString();
						// Поддержка "0"/"1" и "true"/"false"
						if (val == "1" || val.ToLower() == "true")
							isFullOnly = true;
						else
							isFullOnly = false;
					}
                    if (data.ContainsKey("is_blocked_building"))
					{
						var val = data["is_blocked_building"].ToString();
						// Поддержка "0"/"1" и "true"/"false"
						if (val == "1" || val.ToLower() == "true")
							isBlockedBuilding = true;
						else
							isBlockedBuilding = false;
					}

                    if (data.ContainsKey("block_date") && data["block_date"] is int)
                    {
                        double.TryParse(data["block_date"].ToString(), out Block_Date);
                    }

                    if (data.ContainsKey("type"))
                    {
                        IsBlueprint = data["type"].ToString() == "bp";
                        IsCommand = data["type"].ToString() == "command";
                        IsItem = data["type"].ToString() == "item";
                    }
					// 🔹 Обработка subDrop
					if (data.ContainsKey("subDrop") && data["subDrop"] != null)
					{
						var subDropObj = data["subDrop"];

						// 1. Если это JArray
						if (subDropObj is JArray jArray)
						{
							foreach (var sub in jArray)
							{
								ParseSubDrop(sub as JObject);
							}
						}
						// 2. Если это List<object>
						else if (subDropObj is List<object> list)
						{
							foreach (var sub in list)
							{
								if (sub is JObject subObj)
									ParseSubDrop(subObj);
								else if (sub is Dictionary<string, object> dict)
									ParseSubDrop(JObject.FromObject(dict));
							}
						}
						// 3. Если это JToken (универсальный случай)
						else if (subDropObj is JToken token && token.Type == JTokenType.Array)
						{
							foreach (var sub in token)
								ParseSubDrop(sub as JObject);
						}
					}


                    if (ItemID != 0)
                    {
                        var itemInfo = ItemManager.FindItemDefinition(ItemID);
                        if (itemInfo != null) ShortName = itemInfo.shortname;
                        else if (IDToShortName.ContainsKey(ItemID))
                        {
                            itemInfo = ItemManager.FindItemDefinition(IDToShortName[ItemID]);
                            if (itemInfo == null) return;

                            ShortName = itemInfo.shortname;
                        }
                    }

                    // Оптимизированная система изображений с кэшированием
                    var imageLibrary = instance.plugins.Find("ImageLibrary");
                    if (imageLibrary != null)
                    {
                        string cacheKey = $"IconGS.{ID}";
                        
                        // Проверяем кэш изображений
                        string cachedImage = instance.GetCachedImage(cacheKey);
                        if (!string.IsNullOrEmpty(cachedImage) && cachedImage != instance.NoImageID && cachedImage != instance.LoadingImageID)
                        {
                            ImageUrl = cachedImage;
                                return;
                            }

                        // Если есть внешнее изображение и ItemID пустой или равен 0
                        if (!ImageUrl.IsNullOrEmpty() && ImageUrl.StartsWith("http") && ItemID == 0)
                        {
                            // Загружаем в кэш асинхронно
                            instance.LoadImageToCache(ImageUrl, cacheKey);
                            // Пока загружается, используем fallback
                            ImageUrl = instance.NoImageID;
                        }
                        // Если есть внешнее изображение и ItemID не пустой
                        else if (!ImageUrl.IsNullOrEmpty() && ImageUrl.StartsWith("http") && ItemID != 0)
                        {
                            // Загружаем в кэш асинхронно, но используем встроенную иконку как fallback
                            instance.LoadImageToCache(ImageUrl, cacheKey);
                        }
                        
                        // Используем встроенные спрайты игры как основной источник или fallback
                        if (ItemID != 0 && !string.IsNullOrEmpty(ShortName))
                        {
                            string gameSprite = IsBlueprint ? "assets/icons/blueprint_base.png" : $"assets/icons/{ShortName}.png";
                            if (string.IsNullOrEmpty(ImageUrl) || !ImageUrl.StartsWith("http"))
                            {
                                ImageUrl = gameSprite;
                            }
                        }
                    }
                    else
                    {
                        // Без ImageLibrary используем только встроенные спрайты
                        if (ItemID != 0 && !string.IsNullOrEmpty(ShortName))
                        {
                            ImageUrl = IsBlueprint ? "assets/icons/blueprint_base.png" : $"assets/icons/{ShortName}.png";
                        }
                    }
                }
                catch (NullReferenceException e)
                {
                    Interface.Oxide.LogError(JsonConvert.SerializeObject(data));
                }
            }
			// Хелпер для добавления SubDrop
			void ParseSubDrop(JObject subObj)
			{
				if (subObj == null) return;

				var sd = new WSubDrop
				{
					Command = subObj["command"]?.ToString(),
					Type = subObj["type"]?.ToString(),
					ItemID = int.TryParse(subObj["item_id"]?.ToString(), out var id) ? id : 0,
					Count = int.TryParse(subObj["count"]?.ToString(), out var count) ? count : 0
				};
				SubDrop.Add(sd);
			}
        }
        #endregion

        #region Variables

        private static bool initialization = false;
        private static bool Initialized = false;
        private static bool SecureConnection = true;
        private static ProstojRUST instance;
        private static Configuration Settings = new Configuration();
        private string ShopURL = "UNDEFINED";
        private int StartBalance = 0;
        public string NoImageID = "";
        public string LoadingImageID = "";
        private Coroutine LoadingCoroutine;
        private Dictionary<int, Dictionary<ulong, int>> ListTimeOutCommand = new Dictionary<int, Dictionary<ulong, int>>();
        private Dictionary<ulong, List<int>> playersBasketCache = new Dictionary<ulong, List<int>>();
        private HashSet<ulong> ListBannedCommandUserID = new HashSet<ulong>();
        private Timer TimerCheckInstant;
        
        // Кэш изображений для оптимизации
        private Dictionary<string, string> imageCache = new Dictionary<string, string>();
        private HashSet<string> loadingImages = new HashSet<string>();
        
        // Отслеживание состояний кнопок
        private HashSet<string> processingItems = new HashSet<string>();

        #region Request Queue System
        
        private class BasketRequest
        {
            public ulong SteamId;
            public BasePlayer Player;
            public int Page;
        }

        private class GetItemRequest
        {
            public BasePlayer Player;
            public string RequestId;
            public bool Instant;
            public int BasketId;
            public int Index;
        }

        private LinkedList<BasketRequest> basketQueue = new LinkedList<BasketRequest>();
        private LinkedList<GetItemRequest> getItemQueue = new LinkedList<GetItemRequest>();
        
        private int basketConcurrentRequestCount = 0;
        private int basketMaxConcurrentRequests = 10;
        
        private int getItemConcurrentRequestCount = 0;
        private int getItemMaxConcurrentRequests = 10;

        #endregion
        private string MainApiLink = "https://prostoj.store/api/";
        private string ReserveApiLink = "https://prostoj.store/api/";
        //private string BaseRequest => $"https://gamestores.app/api/?shop_id={Settings.APISettings.ShopID}&secret={Settings.APISettings.SecretKey}{(!Settings.APISettings.ServerID.IsNullOrEmpty() && Settings.APISettings.ServerID != "0" && Settings.APISettings.ServerID != "1" && Settings.APISettings.ServerID != "UNDEFINED" ? $"&server={Settings.APISettings.ServerID}" : "")}";
        private string BaseRequestParams => $"?secret={Settings.APISettings.SecretKey}{(!Settings.APISettings.ServerID.IsNullOrEmpty() && Settings.APISettings.ServerID != "0" && Settings.APISettings.ServerID != "1" && Settings.APISettings.ServerID != "UNDEFINED" ? $"&server={Settings.APISettings.ServerID}" : "")}";
        private string BaseRequest = "";
        #endregion

        #region Language
        private void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>()
            {
                // Global
                { "days", "дн." },
                { "hour", "ч." },
                { "minutes", "мин." },
                { "seconds", "сек." },


                // Chat messages
                { "PluginNotInitialized", "Плагин магазина запущен некорректно" },
                { "PlayerFloodBlock", "Вы сделали слишком много запросов. Отдохните немного, скоро возможность выполнять действие к вам вернется." },
                { "ItemNotFound", "Предмет не найден, попробуйте перезапустить корзину" },
                { "UnexpectedError", "Непредвиденная ошибка со стороны сервера, просим прощения!" },
                { "WaitPreviousAction", "Пожалуйста, дождитесь <<окончания>> предыдущего действия!"},

                // UI
                { "BASKET", "КОРЗИНА СЕРВЕРА" },
                { "EXIT", "ВЫХОД" },
                { "BASKET.DESCRIPTION", "Это ваша корзина с покупками, вы можете забрать их в любой момент" },
                { "BASKET.EMPTY", "Ваша корзина пуста" },
                { "HELP", "ПОМОЩЬ" },
                { "REQUEST.PROCESSING", "Подождите, мы обрабатываем ваш запрос..." },
                { "BASKET.UNAVAILABLE", "Корзина временно недоступна, попробуйте позже" },
                { "BASKET.NO.AUTH", "Вы не авторизованы в магазине!\n" +
                                    "Ссылку на авторизацию вы можете найти в разделе 'ПОМОЩЬ'" },


                { "USER.MANUAL", "ИНСТРУКЦИЯ ПОЛЬЗОВАТЕЛЯ" },
                { "USER.MANUAL.DESCRIPTION", "Здесь вы можете забрать товары приобретенные у нас в магазине!\n" +
                                             "Пополнить счёт можно различными способами: электронные кошельки, карты и т.д.\n" +
                                             "Авторизация в магазине происходит при помощи вашего аккаунта STEAM" },

                { "USER.MANUAL.BALANCE", "\n<size=18>При первой авторизации вы получите в подарок <b>{0} рублей</b>!</size>" },
                { "TAKE.ITEM.BLOCKED", "Вы не можете забрать этот предмет из корзины!\n" +
                                       "До его разблокировки осталось: {0}" },
                { "TAKE.REQUEST.PROCESSING", "Подождите, ваш запрос обрабатывается!" },
                { "TAKE.LAST.REQUEST.PROCESSING", "Подождите, ваш предыдущий запрос ещё обрабатывается!" },
                { "TAKE.WAIT", "ПОДОЖДИТЕ" },
                { "TAKE.GIVE.ERROR", "ОШИБКА\nПОЛУЧЕНИЯ" },
                { "TAKE.GIVE.ERROR.NOTIFY", "Произошла непредвиденная ошибка, попробуйте позже!\n" +
                                            "Ваш предмет в безопасности, не переживайте!" },
                { "TAKE.GIVE.SUCCESS", "УСПЕШНО\nПОЛУЧЕНО" },
                { "TAKE.GIVE.FEET", "Вы успешно получили предмет  › <size=20>{0}</size> ‹\n" +
                                    "У вас недостаточно места в инвентаре, <b>предмет брошен</b> под ноги!" },
                { "TAKE.GIVE.INVENTORY", "Вы успешно получили предмет  › <size=20>{0}</size> ‹" },
                { "TAKE.GIVE.COMMAND", "Вы успешно получили предмет  › <size=20>{0}</size> ‹\n" +
                                       "Теперь вам доступны новые привилегии!" },
                { "TAKE.GIVE.BLUEPRINT.FEET", "Вы успешно получили рецепт предмета  › <size=20>{0}</size> ‹\n" +
                                              "У вас недостаточно места в инвентаре, <b>предмет брошен</b> под ноги!" },
                { "TAKE.GIVE.BLUEPRINT.INVENTORY", "Вы успешно получили рецепт предмета  › <size=20>{0}</size> ‹" },
                { "MANY.REQUESTS", "Вы делаете слишком много запросов к серверу!\n" +
                                   "Подождите <b>одну секунду</b>!" }
            }, this, "ru");

            lang.RegisterMessages(new Dictionary<string, string>()
            {
                // Global
                { "days", "d." },
                { "hour", "h." },
                { "minutes", "m." },
                { "seconds", "s." },


                // Chat messages
                { "PluginNotInitialized", "The store plugin is not launched correctly" },
                { "PlayerFloodBlock", "You have made too many requests. Relax a little, soon the ability to perform the action will return to you." },
                { "ItemNotFound", "Item not found, try restarting your cart" },
                { "UnexpectedError", "Unexpected error from server side, sorry!" },
                { "WaitPreviousAction", "Please wait for the <<end>> of the previous step!"},

                // UI
                { "BASKET", "SERVER BASKET" },
                { "EXIT", "EXIT" },
                { "BASKET.DESCRIPTION", "This is your shopping cart, you can pick them up at any time" },
                { "BASKET.EMPTY", "Basket empty" },
                { "HELP", "HELP" },
                { "REQUEST.PROCESSING", "Please wait, we are processing your request ..." },
                { "BASKET.UNAVAILABLE", "Cart is temporarily unavailable, please try again later" },
                { "BASKET.NO.AUTH", "You are not authorized in the store!\n" +
                                    "You can find a link to authorization in the 'HELP' section" },


                { "USER.MANUAL", "USER'S MANUAL" },
                { "USER.MANUAL.DESCRIPTION", "Here you can pick up the products purchased from our store!\n" +
                                             "You can replenish your account in various ways: e-wallets, cards, etc.\n" +
                                             "Authorization in the store takes place using your STEAM account" },

                { "USER.MANUAL.BALANCE", "\n<size=18>At the first authorization, you will receive a gift of <b>{0} rubles</b>!</size>" },
                { "TAKE.ITEM.BLOCKED", "You cannot pick up this item from your cart!\n" +
                                       "Before its unlocking, there are: {0}" },
                { "TAKE.REQUEST.PROCESSING", "Please wait, your request is being processed!" },
                { "TAKE.LAST.REQUEST.PROCESSING", "Please wait, your previous request is still being processed!" },
                { "TAKE.WAIT", "WAIT" },
                { "TAKE.GIVE.ERROR", "ERROR\nRECEIVING" },
                { "TAKE.GIVE.ERROR.NOTIFY", "An unexpected error occured, please try again later!\n" +
                                            "Your item is safe, don't worry!" },
                { "TAKE.GIVE.SUCCESS", "SUCCESSFULLY\nRECEIVED" },
                { "TAKE.GIVE.FEET", "You have successfully obtained an item  › <size=20>{0}</size> ‹\n" +
                                    "You don't have enough space in your inventory, <b>the item was thrown</b> at your feet!" },
                { "TAKE.GIVE.INVENTORY", "You have successfully obtained an item  › <size=20>{0}</size> ‹" },
                { "TAKE.GIVE.COMMAND", "You have successfully obtained an item  › <size=20>{0}</size> ‹\n" +
                                       "New privileges are now available to you!" },
                { "TAKE.GIVE.BLUEPRINT.FEET", "You have successfully obtained the item blueprint  › <size=20>{0}</size> ‹\n" +
                                              "You don't have enough space in your inventory, <b>the item was thrown</b> at your feet!" },
                { "TAKE.GIVE.BLUEPRINT.INVENTORY", "You have successfully obtained the item blueprint  › <size=20>{0}</size> ‹" },
                { "MANY.REQUESTS", "You are making too many server requests!\n" +
                                   "Wait <b>one second</b>!" }
            }, this, "en");
        }

        private string _(BasePlayer player, string key, params object[] args)
        {
            return string.Format(lang.GetMessage(key, this, player?.UserIDString), args);
        }
        #endregion

        #region Interface

        private int TryImageCounter = 0;
        private void OnServerInitialized()
        {
            BaseRequest = MainApiLink + BaseRequestParams;
            if (Settings.APISettings.ServerID != "0" && Settings.APISettings.ServerID != "UNDEFINED")
            {
                TimerCheckInstant = timer.Repeat(60, 0, CheckInstant);
            } else
            {
                LogAction(null, $"Некорректный ServerID, автоматическая выдача команд невозможна");
                PrintError($"Incorrect ServerID, reciving auto commands (Products) disabled");
            }

            if (!ImageLibrary)
            {
                if (TryImageCounter < 3)
                {
                    LogAction(null, $"ImageLibrary is not initialized, try again in 1 sec.", true);
                    TryImageCounter++;

                    timer.Once(1, OnServerInitialized);
                    return;
                }
                else LogAction(null, $"Starting without ImageLibrary", true);
            }

            if (ImageLibrary)
            {
                NoImageID = (string)ImageLibrary.Call("GetImage", "NONE");
                LoadingImageID = (string)ImageLibrary.Call("GetImage", "LOADING");
            }

            if (Settings.InterfaceSettings.BucketURL.Contains("http") && plugins.Find("ImageLibrary") != null)
            {
                ImageLibrary.Call("AddImage", Settings.InterfaceSettings.BucketURL, "ProstojRUSTBucket");
                LoadingCoroutine = ServerMgr.Instance.StartCoroutine(WaitForLoad());
            }
            else
            {
                //BasePlayer.activePlayerList.ForEach(OnPlayerInit);
                for (var i = 0; i < BasePlayer.activePlayerList.Count; i++)
                {
                    OnPlayerConnected(BasePlayer.activePlayerList[i]);
                }
            }

			if (plugins.Find("ImageLibrary") != null && ImageLibrary != null && !(bool)ImageLibrary.Call("HasImage", $"blueprintbase"))
				ImageLibrary.Call("AddImage", "http://gamestores.ru/img/games/rust/blueprintbase.png", "blueprintbase");

            instance = this;
            Settings.TOPSettings.UseTop = false; //Принудительное отключение топа игроков
            if (!Settings.TOPSettings.UseTop) Unsubscribe(nameof(OnEntityDeath));
            if (!Settings.TOPSettings.UseTop) Unsubscribe(nameof(OnPlayerDisconnected));

            if (Settings.APISettings.ShopID == "UNDEFINED" || Settings.APISettings.SecretKey == "UNDEFINED")
            {
                LogAction(null, $"Verify that plugin is installed correct! Some of API settings are 'UNDEFINED'", true, true);
                // Временно разрешаем работу без API для тестирования UI
                Initialized = true;
                Puts("ProstojRUST: Working in TEST MODE without API");
                return;
            }

            timer.Once(2, FetchShopUrl);

            timer.Every(1, () => Delays.RequestPerSecond = 0);

            ListTimeOutCommand[0] = new Dictionary<ulong, int>();
            ListTimeOutCommand[1] = new Dictionary<ulong, int>();
            ListTimeOutCommand[2] = new Dictionary<ulong, int>();

            int totalTick = 0;
            int tick = 0;
            timer.Repeat(2, 0, () =>
            {
                if (totalTick == 30)
                {
                    totalTick = 0;
                    ListBannedCommandUserID.Clear();
                }
                ListTimeOutCommand[0].Clear();
                if (tick == 3 || tick == 6)
                {
                    ListTimeOutCommand[1].Clear();
                }

                if (tick == 6)
                {
                    tick = 0;
                    ListTimeOutCommand[2].Clear();
                }
                tick++;
                totalTick++;
            });
        }

        private IEnumerator WaitForLoad()
        {
            while (!(bool)ImageLibrary.Call("HasImage", "ProstojRUSTBucket"))
            {
                PrintError($"Image of bucket is loading!");
                yield return new WaitForSeconds(1);
            }

            PrintWarning("Image of bucket loaded correct!");
            //BasePlayer.activePlayerList.ForEach(OnPlayerInit);
            for (var i = 0; i < BasePlayer.activePlayerList.Count; i++)
            {
                OnPlayerConnected(BasePlayer.activePlayerList[i]);
            }
            yield return 0;
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                Settings = Config.ReadObject<Configuration>();
                if (Settings?.APISettings == null) LoadDefaultConfig();
            }
            catch
            {
                LogAction(null, $"Error reading config, creating one new config!", true, true);
                LoadDefaultConfig();
            }

            NextTick(SaveConfig);
        }

        protected override void LoadDefaultConfig()
        {
            Settings = new Configuration();
        }

        protected override void SaveConfig() => Config.WriteObject(Settings);

        private void Unload()
        {
            //if (Initialized) StatHandler.SendStats();

            if (LoadingCoroutine != null) ServerMgr.Instance.StopCoroutine(LoadingCoroutine);
            
            // Очищаем очереди запросов
            basketQueue.Clear();
            getItemQueue.Clear();
            basketConcurrentRequestCount = 0;
            getItemConcurrentRequestCount = 0;
            
            // Очищаем кэш изображений
            ClearImageCache();
            
            // Очищаем состояния кнопок
            processingItems.Clear();
            
            // Закрываем UI для всех игроков
            foreach (var pl in BasePlayer.activePlayerList)
            {
                if (pl != null && pl.IsConnected)
                {
                    CuiHelper.DestroyUi(pl, MAIN_UI);
                    CuiHelper.DestroyUi(pl, BASKET_UI);
                    CuiHelper.DestroyUi(pl, HELP_UI);
                }
            }
            
            LogMessage(null, "Плагин ProstojRUST выгружен", LogLevel.Info, true);
        }

        #endregion

        #region Hooks

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            // Закрываем UI при отключении игрока
            CuiHelper.DestroyUi(player, MAIN_UI);
            CuiHelper.DestroyUi(player, BASKET_UI);
            CuiHelper.DestroyUi(player, HELP_UI);
            
            StatHandler.AddStat(new StatHandler.TimeStat(player));
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (player.IsReceivingSnapshot)
            {
                NextTick(() => OnPlayerConnected(player));
                return;
            }
        }

        #endregion

        #region Stats

        private static class StatHandler
        {
            internal class Stat
            {
                [JsonProperty("dataType")]
                public string DataType;
            }
            internal class KillStat : Stat
            {
                [JsonProperty("player_id")]
                public string PlayerUserId;
                [JsonProperty("victim_id")]
                public string VictimUserID;
                [JsonProperty("type")]
                public string Type;
                [JsonProperty("time")]
                public string Time;
            }
            internal class TimeStat : Stat
            {
                [JsonProperty("player_id")]
                public string PlayerID;
                [JsonProperty("username")]
                public string PlayerName;
                [JsonProperty("played")]
                public string Played;
                [JsonProperty("time")]
                public string Time;

                public TimeStat(BasePlayer player)
                {
                    PlayerID = player.UserIDString;
                    PlayerName = player.displayName;
                    DataType = "leave";
                    Played = player.secondsConnected.ToString();
                    Time = CurrentTime().ToString();
                }
            }

            private static List<Stat> Stats = new List<Stat>();
            public static void AddStat(Stat stat)
            {
                Stats.Add(stat);
                if (Stats.Count > 10)
                {
                    SendStats();
                    Stats.Clear();
                }
            }

            public static void SendStats()
            {
                try
                {


                    if (!Initialized)
                    {
                        instance.LogAction(null, $"Sending stats error! Plugin not initialized", true, true);
                        return;
                    }
                    if (Stats.Count == 0)
                    {
                        instance.LogAction(null, $"Nothing to send", true);
                        return;
                    }

                    var obj = JsonConvert.SerializeObject(Stats);
                    RequestPost($"&method=topData&data={obj}", (i, s) => {
                        if (i != 200)
                            return;

                        Dictionary<string, object> response = JsonConvert.DeserializeObject<Dictionary<string, object>>(s, new KeyValuesConverter());
                        if (response.ContainsKey("result") && response["result"].ToString() == "success")
                        {
                            instance.LogAction(null, $"ProstojStore sent stats successful!", true);
                            Stats.Clear();
                        }
                        else
                        {
                            instance.LogAction(null, $"Sending stats error!", true, true);
                        }
                    });


                }
                catch
                {
                    // ignored
                }
            }
        }

        [ConsoleCommand("sendtop2")]
        private void CmdSendTop2(ConsoleSystem.Arg args)
        {
            if (args.Player() != null && !args.Player().IsAdmin) return;

            StatHandler.SendStats();
        }

        private void OnEntityDeath(BaseCombatEntity entity, HitInfo info)
        {
            if (entity == null || info == null || info.Initiator == null)
                return;
            if (Settings.TOPSettings.UseTop)
            {
                BaseEntity initiator = info.Initiator;

                if (entity as BasePlayer == null && initiator as BasePlayer == null)
                    return;
                StatHandler.KillStat stat = new StatHandler.KillStat();

                if (initiator as BasePlayer != null) stat.PlayerUserId = initiator.ToPlayer().UserIDString;
                else if (initiator.PrefabName.Contains("agents")) stat.PlayerUserId = "1";
                else return;

                if (entity as BasePlayer != null)
                {
                    stat.VictimUserID = entity.ToPlayer().UserIDString;
                    stat.Type = entity.ToPlayer().IsSleeping() ? "sleeper" : "kill";
                }
                else if (entity.PrefabName.Contains("agents"))
                {
                    stat.VictimUserID = "1";
                    stat.Type = "kill";
                }
                else
                {
                    return;
                }

                stat.DataType = "death";

                stat.Time = CurrentTime().ToString();
                StatHandler.AddStat(stat);
            }
        }

        #endregion

        #region Commands

        [ChatCommand("store")]
        private void CmdChatStore(BasePlayer player, string command, string[] args)
        {
            if (!Initialized)
            {
                player.ChatMessage(_(player, "PluginNotInitialized"));
			return;
            }

            ShowBasketUI(player);
        }
		
       [ConsoleCommand("store.take")]
        private void GoDraw(ConsoleSystem.Arg args) {
            if (!args.IsAdmin || args.IsClientside) 
            {
                args.ReplyWith("{\"success\": false, \"error\": \"Не админ или клиентский вызов\"}");
                return;
            }

            ulong steam_id = 0;
            int id = 0;
            bool isBlockedBuilding = false;
            if (!ulong.TryParse(args.Args[0], out steam_id) || !int.TryParse(args.Args[1], out id) || !bool.TryParse(args.Args[2], out isBlockedBuilding)) 
            {
                args.ReplyWith("{\"success\": false, \"error\": \"Ошибка выполнения запроса, обратитесь в тех. поддержку\"}");
                return;
            }

            var player = BasePlayer.FindByID(steam_id);
            if (player == null) 
            {
                args.ReplyWith("{\"success\": false, \"error\": \"Игрок не найден или не в сети. Пожалуйста подключитесь к серверу\"}");
                return;
            }

            if (player.IsDead()) 
            {
                args.ReplyWith("{\"success\": false, \"error\": \"Игрок мертв, возродитесь чтобы вывести предмет\"}");
                return;
            }

            if (ListBannedCommandUserID.Contains(player.userID))
            {
                player.ChatMessage(_(player, "PlayerFloodBlock"));
                args.ReplyWith("{\"success\": false, \"error\": \"Игрок заблокирован за флуд, нельзя выполнять много запросов подряд. Подождите 30 секунд.\"}");
                return;
            }

			if (isBlockedBuilding && IsBlockedByForeignTCNearFoundationOrCeiling(player))
			{
				// Игрок в зоне чужого шкафа без авторизации
				args.ReplyWith("{\"success\": false, \"error\": \"Вывод предметов в зоне чужого шкафа без авторизации запрещен!\"}");
				return;
			}

            // Логика с ListTimeOutCommand (если нужно, можно добавить в ответ)
            // ...
			
            args.ReplyWith("{\"success\": true, \"message\": \"Предмет успешно выдан\"}");
            Request($"&method=item&item=true&steam_id={player.UserIDString}&id={id}", (i, s) =>
            {
                switch (i)
                {
                    case 0:
                        LogAction(player, $"API не ответило на запрос: {id}");
                        break;
                    case 200:
                        Dictionary<string, object> response = JsonConvert.DeserializeObject<Dictionary<string, object>>(s, new KeyValuesConverter());
                        if (!response.ContainsKey("data"))
                        {
                            return;
                        }

                        Request($"&method=gived&gived=true&id={id}", (code, newResponse) =>
                        {
                            if (code != 200 || JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() != "success")
                            {
                                return;
                            }
                            
                            ProcessTake(player, response["data"] as Dictionary<string, object>);
                        }, player);
                        break;
                    case 404:
                        break;
                    default:
                        break;
                }
            }, player);
        }

		bool IsBlockedByForeignTCNearFoundationOrCeiling(BasePlayer player, float r = 15f)
		{
			// 1) Должен действовать общий билд-блок (иначе дальше не проверяем)
			if (!player.IsBuildingBlocked())
				return false;

			// 2) Ищем ближайший фундамент/потолок
			var blocks = new List<BuildingBlock>();
			Vis.Entities(player.transform.position, r, blocks);

			BuildingBlock nearest = null;
			float best = float.MaxValue;

			foreach (var b in blocks)
			{
				var name = b?.ShortPrefabName;
				if (string.IsNullOrEmpty(name)) continue;

				// foundation / foundation.triangle / floor / floor.triangle
				bool isFoundationOrFloor = name.Contains("foundation") || name.Contains("floor");
				if (!isFoundationOrFloor) continue;

				float d = Vector3.Distance(player.transform.position, b.transform.position);
				if (d < best)
				{
					best = d;
					nearest = b;
				}
			}

			if (nearest == null)
				return false; // рядом нет фундамента/потолка → скорее всего РТ или просто пусто

			// 3) Шкаф, контролирующий этот блок
			var priv = nearest.GetBuildingPrivilege();
			if (priv != null)
				return !priv.IsAuthed(player); // чужой шкаф = нет авторизации

			// 4) Fallback: если по какой-то причине привилегию блока не получили,
			//   ищем ближайший шкаф вокруг блока и проверяем авторизацию
			var tcs = new List<BuildingPrivlidge>();
			Vis.Entities(nearest.transform.position, 30f, tcs); // разумный радиус до TC

			BuildingPrivlidge closestTc = null;
			float bestTc = float.MaxValue;
			foreach (var tc in tcs)
			{
				float d = Vector3.Distance(nearest.transform.position, tc.transform.position);
				if (d < bestTc)
				{
					bestTc = d;
					closestTc = tc;
				}
			}

			if (closestTc == null)
				return false; // рядом вообще нет шкафов → не считаем чужой зоной (например, РТ)

			return !closestTc.IsAuthed(player);
		}
        #endregion

        #region UI Interface

        private const string MAIN_UI = "ProstojStore_Main";
        private const string BASKET_UI = "ProstojStore_Basket";
        private const string HELP_UI = "ProstojStore_Help";

        private void ShowStoreUI(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, MAIN_UI);
            
            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.2 0.15", AnchorMax = "0.8 0.85" },
                CursorEnabled = true
            }, "Overlay", MAIN_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = _(player, "BASKET"), FontSize = 24, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.9", AnchorMax = "1 1" }
            }, MAIN_UI);

            // Описание
            container.Add(new CuiLabel
            {
                Text = { Text = _(player, "BASKET.DESCRIPTION"), FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = "0.05 0.82", AnchorMax = "0.95 0.88" }
            }, MAIN_UI);

            // Кнопка "Корзина"
            container.Add(new CuiButton
            {
                Button = { Command = "store.basket", Color = "0.2 0.6 0.2 0.8" },
                RectTransform = { AnchorMin = "0.05 0.7", AnchorMax = "0.3 0.8" },
                Text = { Text = _(player, "BASKET"), FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, MAIN_UI);

            // Кнопка "Помощь"
            container.Add(new CuiButton
            {
                Button = { Command = "store.help", Color = "0.2 0.4 0.6 0.8" },
                RectTransform = { AnchorMin = "0.35 0.7", AnchorMax = "0.6 0.8" },
                Text = { Text = _(player, "HELP"), FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, MAIN_UI);

            // Кнопка "Выход"
            container.Add(new CuiButton
            {
                Button = { Command = "store.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.7 0.7", AnchorMax = "0.95 0.8" },
                Text = { Text = _(player, "EXIT"), FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, MAIN_UI);

            // Информационная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.15 0.15 0.15 0.8" },
                RectTransform = { AnchorMin = "0.05 0.1", AnchorMax = "0.95 0.65" }
            }, MAIN_UI, "InfoPanel");

            // Ссылка на магазин
            container.Add(new CuiLabel
            {
                Text = { Text = $"Магазин: {ShopURL}", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 1 1" },
                RectTransform = { AnchorMin = "0.05 0.45", AnchorMax = "0.95 0.55" }
            }, "InfoPanel");

            // Инструкция
            container.Add(new CuiLabel
            {
                Text = { Text = "Для покупки предметов перейдите на сайт магазина\nи авторизуйтесь через Steam", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "0.7 0.7 0.7 1" },
                RectTransform = { AnchorMin = "0.05 0.25", AnchorMax = "0.95 0.4" }
            }, "InfoPanel");

            CuiHelper.AddUi(player, container);
        }

        private void ShowBasketUI(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, BASKET_UI);

            var container = new CuiElementContainer();

            // Главная панель корзины в стиле RustCraft
            container.Add(new CuiPanel
            {
                Image = { Color = "0.12 0.12 0.12 0.98" }, // Темно-серый фон как на скриншоте
                RectTransform = { AnchorMin = "0.1 0.1", AnchorMax = "0.9 0.9" },
                CursorEnabled = true
            }, "Overlay", BASKET_UI);

            // Заголовок корзины в стиле RustCraft
            container.Add(new CuiLabel
            {
                Text = { Text = _(player, "BASKET"), FontSize = 22, Align = TextAnchor.MiddleCenter, Color = "0.85 0.85 0.85 1" }, // Светло-серый текст
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 1" }
            }, BASKET_UI);

            // Интерактивная кнопка "Закрыть"
            CreateInteractiveButton(container, BASKET_UI, "store.close", "✕", "0.92 0.92", "0.98 0.98");

            // Интерактивная кнопка "Обновить"
            CreateInteractiveButton(container, BASKET_UI, "store.refresh", "🔄 Обновить", "0.02 0.92", "0.15 0.98");

            // Область для предметов корзины с темным фоном
            container.Add(new CuiPanel
            {
                Image = { Color = "0.08 0.08 0.08 0.9" }, // Еще более темный фон для контента
                RectTransform = { AnchorMin = "0.02 0.05", AnchorMax = "0.98 0.9" }
            }, BASKET_UI, "BasketContent");

            CuiHelper.AddUi(player, container);
            
            // Загружаем корзину игрока с небольшой задержкой для лучшего UX
            timer.Once(0.1f, () => {
                if (player != null && player.IsConnected)
                    LoadPlayerBasket(player);
            });
        }

        private void ShowHelpUI(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, HELP_UI);

            var container = new CuiElementContainer();

            // Главная панель помощи
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.2 0.2", AnchorMax = "0.8 0.8" },
                CursorEnabled = true
            }, "Overlay", HELP_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = _(player, "USER.MANUAL"), FontSize = 20, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.9", AnchorMax = "1 1" }
            }, HELP_UI);

            // Кнопка "Назад"
            container.Add(new CuiButton
            {
                Button = { Command = "store.main", Color = "0.4 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = "0.02 0.9", AnchorMax = "0.15 0.98" },
                Text = { Text = "← Назад", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HELP_UI);

            // Описание
            container.Add(new CuiLabel
            {
                Text = { Text = _(player, "USER.MANUAL.DESCRIPTION"), FontSize = 14, Align = TextAnchor.UpperLeft, Color = "0.9 0.9 0.9 1" },
                RectTransform = { AnchorMin = "0.05 0.5", AnchorMax = "0.95 0.85" }
            }, HELP_UI);

            // Ссылка на магазин
            container.Add(new CuiLabel
            {
                Text = { Text = $"Ссылка на магазин:\n{ShopURL}", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 1 1" },
                RectTransform = { AnchorMin = "0.05 0.3", AnchorMax = "0.95 0.45" }
            }, HELP_UI);

            // Бонус при регистрации
            if (StartBalance > 0)
            {
                container.Add(new CuiLabel
                {
                    Text = { Text = string.Format(_(player, "USER.MANUAL.BALANCE"), StartBalance), FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "0.8 1 0.8 1" },
                    RectTransform = { AnchorMin = "0.05 0.1", AnchorMax = "0.95 0.25" }
                }, HELP_UI);
            }

            CuiHelper.AddUi(player, container);
        }

        #region Queue Management

        public void RequestBasket(BasePlayer player, int page = 0)
        {
            if (!CanRequestBasket(player))
            {
                ShowBasketMessage(player, _(player, "WaitPreviousAction"));
                return;
            }

            var basketRequest = new BasketRequest
            {
                SteamId = player.userID,
                Player = player,
                Page = page
            };

            basketQueue.AddLast(basketRequest);

            if (basketConcurrentRequestCount < basketMaxConcurrentRequests)
            {
                StartBasketOpeningProcess(basketRequest);
            }
        }

        private void StartBasketOpeningProcess(BasketRequest basketRequest)
        {
            basketConcurrentRequestCount++;
            basketQueue.Remove(basketRequest);

            ShowBasketMessage(basketRequest.Player, _(basketRequest.Player, "REQUEST.PROCESSING"), true);

            Request($"&method=basket&basket=true&steam_id={basketRequest.Player.UserIDString}", (code, response) =>
            {
                HandleBasketResponse(basketRequest.Player, code, response);
                basketConcurrentRequestCount--;
                TriggerNextBasketRequest();
            }, basketRequest.Player);
        }

        private void HandleBasketResponse(BasePlayer player, int code, string response)
        {
            switch (code)
            {
                case 0:
                    ShowBasketMessage(player, _(player, "BASKET.UNAVAILABLE"));
                    break;
                case 200:
                    try
                    {
                        Puts(response);
                        var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                        if (data.ContainsKey("result") && data["result"].ToString() == "success")
                        {
                            if (data.ContainsKey("data"))
                            {
                                var items = data["data"] as List<object>;
                                ShowBasketItems(player, items);
                            }
                            else
                            {
                                ShowBasketMessage(player, _(player, "BASKET.EMPTY"));
                            }
                        }
                        else if (data.ContainsKey("result") && data["result"].ToString() == "no_auth")
                        {
                            ShowBasketMessage(player, _(player, "BASKET.NO.AUTH"));
                        }
                        else
                        {
                            ShowBasketMessage(player, _(player, "UnexpectedError"));
                        }
                    }
                    catch
                    {
                        ShowBasketMessage(player, _(player, "UnexpectedError"));
                    }
                    break;
                default:
                    ShowBasketMessage(player, _(player, "BASKET.UNAVAILABLE"));
                    break;
            }
        }

        private void TriggerNextBasketRequest()
        {
            if (basketQueue.Count > 0 && basketConcurrentRequestCount < basketMaxConcurrentRequests)
            {
                var nextRequest = basketQueue.First.Value;
                StartBasketOpeningProcess(nextRequest);
            }
        }

        private bool CanRequestBasket(BasePlayer player)
        {
            foreach (var request in basketQueue)
            {
                if (request.SteamId == player.userID)
                    return false;
            }
            return true;
        }

        private void LoadPlayerBasket(BasePlayer player)
        {
            RequestBasket(player, 0);
        }

        #endregion

        private void ShowBasketMessage(BasePlayer player, string message, bool isLoading = false)
        {
            CuiHelper.DestroyUi(player, "BasketMessage");

            var container = new CuiElementContainer();
            
            // Фон сообщения
            container.Add(new CuiPanel
            {
                Image = { Color = isLoading ? "0.2 0.4 0.6 0.8" : "0.2 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.2 0.4", AnchorMax = "0.8 0.6" }
            }, "BasketContent", "BasketMessage");
            
            // Текст сообщения
            container.Add(new CuiLabel
            {
                Text = { Text = isLoading ? $"⏳ {message}" : message, FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "0.9 0.9 0.9 1" },
                RectTransform = { AnchorMin = "0.05 0.2", AnchorMax = "0.95 0.8" }
            }, "BasketMessage");

            CuiHelper.AddUi(player, container);
            
            // Автоматически скрываем сообщение через 3 секунды, если это не загрузка
            if (!isLoading)
            {
                timer.Once(3f, () => {
                    if (player != null && player.IsConnected)
                        CuiHelper.DestroyUi(player, "BasketMessage");
                });
            }
        }

        private void ShowBasketItems(BasePlayer player, List<object> items)
        {
            CuiHelper.DestroyUi(player, "BasketMessage");
            CuiHelper.DestroyUi(player, "BasketItems");

            if (items == null || items.Count == 0)
            {
                ShowBasketMessage(player, _(player, "BASKET.EMPTY"));
                return;
            }

            var container = new CuiElementContainer();
            
            // Создаем скроллируемую область для предметов
            container.Add(new CuiPanel
            {
                Image = { Color = "0 0 0 0" },
                RectTransform = { AnchorMin = "0.02 0.02", AnchorMax = "0.98 0.98" }
            }, "BasketContent", "BasketItems");

            int itemsPerRow = Settings.InterfaceSettings.ItemOnString;
            int maxItems = itemsPerRow * Settings.InterfaceSettings.StringAmount;
            
            for (int i = 0; i < items.Count && i < maxItems; i++)
            {
                var itemData = items[i] as Dictionary<string, object>;
                if (itemData == null) continue;

                var item = new WItem(itemData);
                
                int row = i / itemsPerRow;
                int col = i % itemsPerRow;
                
                // Вычисляем позицию как в GameStoresRUST
                float itemWidth = 1f / itemsPerRow;
                float itemHeight = 1f / Settings.InterfaceSettings.StringAmount;
                
                float xMin = col * itemWidth + 0.01f;
                float xMax = (col + 1) * itemWidth - 0.01f;
                float yMax = 1f - (row * itemHeight) - 0.02f;
                float yMin = 1f - ((row + 1) * itemHeight) + 0.02f;

                // Панель предмета (квадратная) в стиле RustCraft
                string itemPanelName = $"Item_{i}";
                container.Add(new CuiPanel
                {
                    Image = { 
                        Color = item.Blocked ? "0.3 0.15 0.15 0.9" : "0.2 0.2 0.2 0.9",
                        Material = "assets/content/ui/uibackgroundblur-ingamemenu.mat"
                    },
                    RectTransform = { 
                        AnchorMin = $"{xMin} {yMin}", 
                        AnchorMax = $"{xMax} {yMax}" 
                    }
                }, "BasketItems", itemPanelName);

                // Изображение предмета - используем систему как в GameStoresRUST
                if (Settings.InterfaceSettings.LoadSpriteImages && item.ItemID != 0)
                {
                    // Используем встроенную иконку предмета по ItemID (квадратная)
                    container.Add(new CuiElement
                    {
                        Parent = itemPanelName,
                        Components = {
                            new CuiImageComponent { ItemId = item.ItemID },
                            new CuiRectTransformComponent { AnchorMin = "0.1 0.4", AnchorMax = "0.9 0.9", OffsetMin = "5 5", OffsetMax = "-5 -5" }
                        }
                    });

                    // Если это рецепт, добавляем иконку blueprint поверх
                    if (item.IsBlueprint)
                    {
                        var imageLibrary = plugins.Find("ImageLibrary");
                        if (imageLibrary != null && (bool)imageLibrary.Call("HasImage", "blueprintbase"))
                        {
                            string blueprintIcon = (string)imageLibrary.Call("GetImage", "blueprintbase");
                            container.Add(new CuiElement
                            {
                                Parent = itemPanelName,
                                Components = {
                                    new CuiRawImageComponent { Url = blueprintIcon },
                                    new CuiRectTransformComponent { AnchorMin = "0.65 0.65", AnchorMax = "0.95 0.95" }
                                }
                            });
                        }
                    }
                }
                else if (!string.IsNullOrEmpty(item.ImageUrl))
                {
                    // Fallback на внешнее изображение из кэша
                    string cachedImageId = GetCachedImage($"IconGS.{item.ID}");
                    if (!string.IsNullOrEmpty(cachedImageId))
                    {
                        container.Add(new CuiElement
                        {
                            Parent = itemPanelName,
                            Components = {
                                new CuiRawImageComponent { Url = cachedImageId },
                                new CuiRectTransformComponent { AnchorMin = "0.1 0.4", AnchorMax = "0.9 0.9", OffsetMin = "5 5", OffsetMax = "-5 -5" }
                            }
                        });
                    }
                    else
                    {
                        // Если изображение не в кэше, загружаем его
                        if (item.ItemID == 0 && item.ImageUrl.StartsWith("http"))
                        {
                            LoadImageToCache(item.ImageUrl, $"IconGS.{item.ID}");
                        }
                    }
                }

                // Название предмета (только если включено отображение текста) в стиле RustCraft
                if (Settings.InterfaceSettings.TextShow)
                {
                    container.Add(new CuiLabel
                    {
                        Text = { Text = item.Name, FontSize = 8, Align = TextAnchor.MiddleCenter, Color = "0.85 0.85 0.85 1" }, // Светло-серый текст
                        RectTransform = { AnchorMin = "0.02 0.02", AnchorMax = "0.98 0.35" }
                    }, itemPanelName);
                }

                // Создаем оверлей для заблокированных предметов
                if (item.Blocked)
                {
                    CreateWipeBlockOverlay(container, itemPanelName, item);
                }

                // Интерактивная кнопка взять предмет
                bool isProcessing = processingItems.Contains($"{player.userID}_{item.ID}");
                string buttonText = isProcessing ? "⏳ Ожидание..." : (item.Blocked ? "🔒 Заблокирован" : "📦 Взять");
                
                CreateInteractiveButton(container, itemPanelName, $"store.take {item.ID}", buttonText, 
                    "0.02 0.02", "0.98 0.15", isProcessing, item.Blocked);
            }

            CuiHelper.AddUi(player, container);
        }

        private string FormatTime(int seconds)
        {
            if (seconds <= 0) return "0" + _(null, "seconds");
            
            var time = TimeSpan.FromSeconds(seconds);
            if (time.Days > 0) return $"{time.Days}{_(null, "days")} {time.Hours}{_(null, "hour")}";
            if (time.Hours > 0) return $"{time.Hours}{_(null, "hour")} {time.Minutes}{_(null, "minutes")}";
            if (time.Minutes > 0) return $"{time.Minutes}{_(null, "minutes")} {time.Seconds}{_(null, "seconds")}";
            return $"{time.Seconds}{_(null, "seconds")}";
        }

        [ConsoleCommand("store.main")]
        private void CmdStoreMain(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            CuiHelper.DestroyUi(player, BASKET_UI);
            CuiHelper.DestroyUi(player, HELP_UI);
            ShowStoreUI(player);
        }

        [ConsoleCommand("store.basket")]
        private void CmdStoreBasket(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            CuiHelper.DestroyUi(player, MAIN_UI);
            CuiHelper.DestroyUi(player, HELP_UI);
            ShowBasketUI(player);
        }

        [ConsoleCommand("store.help")]
        private void CmdStoreHelp(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            CuiHelper.DestroyUi(player, MAIN_UI);
            CuiHelper.DestroyUi(player, BASKET_UI);
            ShowHelpUI(player);
        }

        [ConsoleCommand("store.close")]
        private void CmdStoreClose(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            CuiHelper.DestroyUi(player, MAIN_UI);
            CuiHelper.DestroyUi(player, BASKET_UI);
            CuiHelper.DestroyUi(player, HELP_UI);
        }

        [ConsoleCommand("store.refresh")]
        private void CmdStoreRefresh(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            // Показываем индикатор обновления
            ShowBasketMessage(player, "Обновление корзины...", true);
            
            // Обновляем корзину с небольшой задержкой для лучшего UX
            timer.Once(0.2f, () => {
                if (player != null && player.IsConnected)
                    LoadPlayerBasket(player);
            });
        }

        [ConsoleCommand("store.take")]
        private void CmdStoreTake(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !arg.HasArgs()) return;

            if (!int.TryParse(arg.Args[0], out int basketId))
            {
                ShowBasketMessage(player, "❌ " + _(player, "ItemNotFound"));
                return;
            }

            // Проверяем, не выполняется ли уже запрос для этого предмета
            if (!CanRequestGetItem(basketId))
            {
                ShowBasketMessage(player, "⏳ " + _(player, "WaitPreviousAction"));
                return;
            }

            // Добавляем предмет в список обрабатываемых
            string itemKey = $"{player.userID}_{basketId}";
            processingItems.Add(itemKey);
            
            // Обновляем UI для показа состояния ожидания
            timer.Once(0.1f, () => {
                if (player != null && player.IsConnected)
                    LoadPlayerBasket(player);
            });
            
            RequestGetItem(player, basketId);
        }

        private void RequestGetItem(BasePlayer player, int basketID, int index = 0)
        {
            if (!CanRequestGetItem(basketID))
            {
                ShowBasketMessage(player, _(player, "WaitPreviousAction"));
                return;
            }

            var getItemRequest = new GetItemRequest
            {
                Player = player,
                RequestId = Guid.NewGuid().ToString(),
                Instant = false,
                BasketId = basketID,
                Index = index
            };

            getItemQueue.AddLast(getItemRequest);

            if (getItemConcurrentRequestCount < getItemMaxConcurrentRequests)
            {
                StartGetItemProcess(getItemRequest);
            }
        }

        private void StartGetItemProcess(GetItemRequest getItemRequest)
        {
            getItemConcurrentRequestCount++;
            getItemQueue.Remove(getItemRequest);

            if (getItemRequest.Player != null)
            {
                ShowBasketMessage(getItemRequest.Player, _(getItemRequest.Player, "TAKE.REQUEST.PROCESSING"), true);
            }

            Request($"&method=item&item=true&steam_id={getItemRequest.Player?.UserIDString}&id={getItemRequest.BasketId}", (code, response) =>
            {
                HandleTakeResponse(getItemRequest, code, response);
                getItemConcurrentRequestCount--;
                TriggerNextGetItemRequest();
            }, getItemRequest.Player);
        }

        private void HandleTakeResponse(GetItemRequest getItemRequest, int code, string response)
        {
            var player = getItemRequest.Player;
            var basketId = getItemRequest.BasketId;
            
            // Удаляем предмет из списка обрабатываемых
            string itemKey = $"{player?.userID}_{basketId}";
            processingItems.Remove(itemKey);

            switch (code)
            {
                case 0:
                    if (player != null) ShowBasketMessage(player, _(player, "UnexpectedError"));
                    break;
                case 200:
                    try
                    {
                        var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                        if (data.ContainsKey("data"))
                        {
                            Request($"&method=gived&gived=true&id={basketId}", (giveCode, giveResponse) =>
                            {
                                if (giveCode == 200)
                                {
                                    var giveData = JsonConvert.DeserializeObject<JObject>(giveResponse);
                                    if (giveData["result"].ToString() == "success")
                                    {
                                        ProcessTake(player, data["data"] as Dictionary<string, object>);
                                        if (player != null)
                                        {
                                            // Показываем сообщение об успешном получении
                                            ShowBasketMessage(player, "✅ Предмет успешно получен!");
                                            // Автоматически обновляем корзину через короткое время
                                            timer.Once(0.5f, () => {
                                                if (player != null && player.IsConnected)
                                                    LoadPlayerBasket(player);
                                            });
                                        }
                                    }
                                    else
                                    {
                                        if (player != null) ShowBasketMessage(player, _(player, "TAKE.GIVE.ERROR.NOTIFY"));
                                    }
                                }
                                else
                                {
                                    if (player != null) ShowBasketMessage(player, _(player, "TAKE.GIVE.ERROR.NOTIFY"));
                                }
                            }, player);
                        }
                        else
                        {
                            if (player != null) ShowBasketMessage(player, _(player, "ItemNotFound"));
                        }
                    }
                    catch
                    {
                        if (player != null) ShowBasketMessage(player, _(player, "UnexpectedError"));
                    }
                    break;
                case 404:
                    if (player != null) ShowBasketMessage(player, _(player, "ItemNotFound"));
                    break;
                default:
                    if (player != null) ShowBasketMessage(player, _(player, "UnexpectedError"));
                    break;
            }
        }

        private void TriggerNextGetItemRequest()
        {
            if (getItemQueue.Count > 0 && getItemConcurrentRequestCount < getItemMaxConcurrentRequests)
            {
                var nextRequest = getItemQueue.First.Value;
                StartGetItemProcess(nextRequest);
            }
        }

        private bool CanRequestGetItem(int basketID)
        {
            foreach (var request in getItemQueue)
            {
                if (request.BasketId == basketID)
                    return false;
            }
            return true;
        }

        #endregion

        #region Image Cache System

        private void LoadImageToCache(string imageUrl, string cacheKey)
        {
            if (string.IsNullOrEmpty(imageUrl) || string.IsNullOrEmpty(cacheKey))
                return;

            // Проверяем, не загружается ли уже это изображение
            if (loadingImages.Contains(cacheKey))
                return;

            // Проверяем, есть ли уже в кэше
            if (imageCache.ContainsKey(cacheKey))
                return;

            var imageLibrary = plugins.Find("ImageLibrary");
            if (imageLibrary == null)
                return;

            // Проверяем, есть ли уже в ImageLibrary
            if ((bool)imageLibrary.Call("HasImage", cacheKey))
            {
                string cachedImageId = (string)imageLibrary.Call("GetImage", cacheKey);
                if (!string.IsNullOrEmpty(cachedImageId))
                {
                    imageCache[cacheKey] = cachedImageId;
                    return;
                }
            }

            // Добавляем в список загружающихся
            loadingImages.Add(cacheKey);

            // Загружаем изображение
            imageLibrary.Call("AddImage", imageUrl.Replace("https", "http"), cacheKey);
            
            // Через некоторое время проверяем результат загрузки
            timer.Once(3f, () =>
            {
                loadingImages.Remove(cacheKey);
                
                if ((bool)imageLibrary.Call("HasImage", cacheKey))
                {
                    string loadedImageId = (string)imageLibrary.Call("GetImage", cacheKey);
                    if (!string.IsNullOrEmpty(loadedImageId))
                    {
                        imageCache[cacheKey] = loadedImageId;
                        LogAction(null, $"Изображение загружено в кэш: {cacheKey}");
                    }
                }
            });
        }

        private string GetCachedImage(string cacheKey)
        {
            if (imageCache.ContainsKey(cacheKey))
                return imageCache[cacheKey];

            var imageLibrary = plugins.Find("ImageLibrary");
            if (imageLibrary != null && (bool)imageLibrary.Call("HasImage", cacheKey))
            {
                string imageId = (string)imageLibrary.Call("GetImage", cacheKey);
                if (!string.IsNullOrEmpty(imageId))
                {
                    imageCache[cacheKey] = imageId;
                    return imageId;
                }
            }

            return null;
        }

        private void ClearImageCache()
        {
            imageCache.Clear();
            loadingImages.Clear();
        }

        #endregion

        #region Interactive UI Elements

        private void CreateInteractiveButton(CuiElementContainer container, string parent, string command, string text, 
            string anchorMin, string anchorMax, bool isProcessing = false, bool isBlocked = false)
        {
            string buttonId = $"btn_{Guid.NewGuid().ToString("N")[..8]}";
            
            // Определяем цвета в зависимости от состояния
            string normalColor, textColor;
            int fontSize = 9;
            
            // Специальная обработка для кнопки закрытия
            if (command == "store.close")
            {
                normalColor = "0.7 0.3 0.3 0.9";
                textColor = "1 1 1 1";
                fontSize = 16;
            }
            else if (isBlocked)
            {
                normalColor = "0.4 0.2 0.2 0.9";
                textColor = "0.8 0.4 0.4 1";
            }
            else if (isProcessing)
            {
                normalColor = "0.2 0.4 0.6 0.9";
                textColor = "0.9 0.9 0.9 1";
            }
            else
            {
                normalColor = "0.4 0.4 0.4 0.9";
                textColor = "0.85 0.85 0.85 1";
            }

            // Создаем основную кнопку
            container.Add(new CuiButton
            {
                Button = { 
                    Command = isBlocked || isProcessing ? "" : command, 
                    Color = normalColor,
                    Material = "assets/content/ui/uibackgroundblur-ingamemenu.mat"
                },
                RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                Text = { Text = text, FontSize = fontSize, Align = TextAnchor.MiddleCenter, Color = textColor }
            }, parent, buttonId);

            // Добавляем hover эффект для активных кнопок
            if (!isBlocked && !isProcessing)
            {
                // Создаем невидимую панель для hover эффекта
                container.Add(new CuiPanel
                {
                    Image = { Color = "0.1 0.1 0.1 0" },
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" }
                }, buttonId, $"{buttonId}_hover");
                
                // Добавляем анимацию через изменение прозрачности
                container.Add(new CuiElement
                {
                    Name = $"{buttonId}_glow",
                    Parent = buttonId,
                    Components = {
                        new CuiImageComponent { 
                            Color = command == "store.close" ? "0.8 0.4 0.4 0.2" : "0.6 0.6 0.6 0.2",
                            Material = "assets/content/ui/uibackgroundblur-ingamemenu.mat"
                        },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1" }
                    }
                });
            }
        }

        private void CreateWipeBlockOverlay(CuiElementContainer container, string parent, WItem item)
        {
            if (!item.Blocked) return;

            var timeLeft = TimeSpan.FromSeconds(item.Block_Date - CurrentTime());
            
            // Красный оверлей для заблокированного предмета
            container.Add(new CuiPanel
            {
                Image = { Color = "0.6 0.1 0.1 0.8" },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" }
            }, parent, $"{parent}_block_overlay");

            // Иконка блокировки
            container.Add(new CuiLabel
            {
                Text = { Text = "🔒", FontSize = 20, Align = TextAnchor.MiddleCenter, Color = "1 0.3 0.3 1" },
                RectTransform = { AnchorMin = "0.3 0.6", AnchorMax = "0.7 0.9" }
            }, $"{parent}_block_overlay");

            // Текст "ВАЙП БЛОК"
            container.Add(new CuiLabel
            {
                Text = { Text = "ВАЙП БЛОК", FontSize = 8, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0.8 1" },
                RectTransform = { AnchorMin = "0.05 0.4", AnchorMax = "0.95 0.6" }
            }, $"{parent}_block_overlay");

            // Время до разблокировки
            container.Add(new CuiLabel
            {
                Text = { Text = FormatTime((int)timeLeft.TotalSeconds), FontSize = 7, Align = TextAnchor.MiddleCenter, Color = "1 0.9 0.9 1" },
                RectTransform = { AnchorMin = "0.05 0.2", AnchorMax = "0.95 0.4" }
            }, $"{parent}_block_overlay");
        }

        #endregion

        #region Utils

        private string GetItemImage(WItem item)
        {
            // Сначала проверяем кастомное изображение
            if (!string.IsNullOrEmpty(item.ImageUrl))
            {
                var imageLibrary = plugins.Find("ImageLibrary");
                if (imageLibrary != null && (bool)imageLibrary.Call("HasImage", $"IconGS.{item.ID}"))
                {
                    return (string)imageLibrary.Call("GetImage", $"IconGS.{item.ID}");
                }
            }

            // Используем встроенные спрайты игры для предметов
            if (item.ItemID != 0 && !string.IsNullOrEmpty(item.ShortName))
            {
                return GetGameItemSprite(item.ShortName, item.IsBlueprint);
            }

            // Fallback на стандартное изображение
            return NoImageID;
        }

        private string GetGameItemSprite(string shortName, bool isBlueprint = false)
        {
            if (isBlueprint)
            {
                return "assets/icons/blueprint_base.png";
            }

            // Используем встроенные иконки предметов
            return $"assets/icons/{shortName}.png";
        }

        #region Item Processing

        private void ProcessItemGive(BasePlayer player, WItem itemInfo)
        {
            if (itemInfo.isFullOnly && itemInfo.SubDrop != null && itemInfo.SubDrop.Count > 0)
            {
                ProcessSubDropItems(player, itemInfo);
            }
            else
            {
                ProcessSingleItem(player, itemInfo);
            }
        }

        private void ProcessSubDropItems(BasePlayer player, WItem itemInfo)
        {
            LogAction(player, $"Выдаём SubDrop вместо основного предмета {itemInfo.ShortName}");

            Item mainWeapon = null;

            // Сначала выдаём основное оружие
            foreach (var sub in itemInfo.SubDrop)
            {
                if (sub.Type != "item") continue;

                var def = ItemManager.FindItemDefinition(sub.ItemID);
                if (def?.category == ItemCategory.Weapon && mainWeapon == null)
                {
                    mainWeapon = GiveWeaponToFirstSlot(player, def, sub.Count);
                    break;
                }
            }

            // Затем выдаём остальные предметы
						foreach (var sub in itemInfo.SubDrop)
						{
							if (sub.Type == "command")
							{
                    ExecuteCommand(player, sub.Command);
								continue;
							}

							var def = ItemManager.FindItemDefinition(sub.ItemID);
							if (def == null) continue;

							// Пропускаем уже выданное оружие
                if (def.category == ItemCategory.Weapon && mainWeapon?.info == def)
								continue;

							var item = ItemManager.Create(def, sub.Count);
                if (item != null)
                {
                    GiveItemSmart(player, item, mainWeapon);
                }
            }
        }

        private void ProcessSingleItem(BasePlayer player, WItem itemInfo)
        {
            LogAction(player, $"Попытка получения предмета: {itemInfo.ShortName} [{itemInfo.Amount}]");
            
            var info = ItemManager.FindItemDefinition(itemInfo.ShortName);
            if (info == null)
            {
                LogAction(player, $"Предмет не найден: {itemInfo.ShortName}");
                return;
            }

            var item = ItemManager.Create(info, itemInfo.Amount);
            if (item == null)
            {
                LogAction(player, $"Не удалось создать предмет: {itemInfo.ShortName}");
                return;
            }

            if (!player.inventory.GiveItem(item))
            {
                item.Drop(player.transform.position, Vector3.down * 3);
                LogAction(player, $"Предмет выброшен из-за нехватки места: {itemInfo.ShortName}");
            }
            else
            {
                LogAction(player, $"Предмет выдан в инвентарь: {itemInfo.ShortName}");
            }
        }

        private Item GiveWeaponToFirstSlot(BasePlayer player, ItemDefinition def, int count)
        {
            var belt = player.inventory.containerBelt;
            var firstSlot = belt.GetSlot(0);

            // Освобождаем первый слот если нужно
            if (firstSlot != null)
            {
                if (!player.inventory.GiveItem(firstSlot))
                {
                    firstSlot.Drop(player.transform.position, Vector3.down * 3);
                    LogAction(player, $"Предмет из первого слота выброшен: {firstSlot.info.shortname}");
								}
								else
								{
                    LogAction(player, $"Предмет из первого слота перемещен в инвентарь: {firstSlot.info.shortname}");
                }
            }

            var weapon = ItemManager.Create(def, count);
            if (weapon?.MoveToContainer(belt, 0) == true)
            {
                LogAction(player, $"Оружие выдано в первый слот: {def.shortname}");
                return weapon;
            }

            // Fallback в инвентарь
            if (weapon != null && !player.inventory.GiveItem(weapon))
            {
                weapon.Drop(player.transform.position, Vector3.down * 3);
                LogAction(player, $"Оружие выброшено: {def.shortname}");
            }

            return weapon;
        }

        private void GiveItemSmart(BasePlayer player, Item item, Item mainWeapon = null)
        {
            var def = item.info;
            bool given = false;

            // Модули оружия - пытаемся установить на основное оружие
            if (def.shortname.StartsWith("weapon.mod.") && mainWeapon != null)
            {
                given = TryAttachMod(item, mainWeapon);
            }

            // Одежда - пытаемся надеть
            if (!given && def.category == ItemCategory.Attire)
            {
                given = item.MoveToContainer(player.inventory.containerWear);
                if (given)
                {
                    LogAction(player, $"Одежда надета: {def.shortname}");
                }
            }

            // Fallback в инвентарь или на землю
							if (!given)
							{
								if (!player.inventory.GiveItem(item))
								{
									item.Drop(player.transform.position, Vector3.down * 3);
                    LogAction(player, $"Предмет выброшен: {def.shortname}");
								}
								else
								{
                    LogAction(player, $"Предмет выдан в инвентарь: {def.shortname}");
                }
            }
        }

        private bool TryAttachMod(Item mod, Item weapon)
        {
            try
            {
                var weaponEntity = weapon.GetHeldEntity() as BaseProjectile;
                if (weaponEntity?.GetItem()?.contents != null)
                {
                    if (mod.MoveToContainer(weaponEntity.GetItem().contents))
                    {
                        LogAction(null, $"Модуль {mod.info.shortname} установлен на {weapon.info.shortname}");
                        return true;
                    }
                }
            }
            catch (Exception ex)
            {
                LogAction(null, $"Ошибка установки модуля: {ex.Message}");
            }
            return false;
        }

        private void ExecuteCommand(BasePlayer player, string command)
        {
            try
            {
                string processedCommand = command.Replace("{playerid}", player?.UserIDString ?? "0");
                LogAction(player, $"Выполняем команду: {processedCommand}");
                ConsoleSystem.Run(ConsoleSystem.Option.Server, processedCommand);
            }
            catch (Exception ex)
            {
                LogAction(player, $"Ошибка выполнения команды: {ex.Message}");
            }
        }

        private void ProcessCommandGive(BasePlayer player, WItem itemInfo)
                {
                    LogAction(player, $"Попытка получения команды");

            try
            {
                string commands = itemInfo.Command
                    .Replace("\n", "|")
                    .Replace("%steamid%", player.UserIDString, StringComparison.OrdinalIgnoreCase)
                    .Replace("%username%", player.displayName, StringComparison.OrdinalIgnoreCase)
                    .Replace("{playerid}", player.UserIDString)
                    .Replace("{username}", player.displayName);

                foreach (var command in commands.Split('|'))
                {
                    if (string.IsNullOrWhiteSpace(command)) continue;
                    
                    LogAction(player, $"Исполнение команды: {command.Trim()}");
                    Server.Command(command.Trim());
                }
            }
            catch (Exception ex)
            {
                LogAction(player, $"Ошибка обработки команд: {ex.Message}");
            }
        }

        private void ProcessBlueprintGive(BasePlayer player, WItem itemInfo)
                {
                    LogAction(player, $"Попытка получения рецепта {itemInfo.ShortName}");
            
            try
            {
                Item blueprint = ItemManager.CreateByItemID(-996920608);
                if (blueprint == null)
                {
                    LogAction(player, $"Не удалось создать рецепт");
                    return;
                }

                var itemDefinition = ItemManager.FindItemDefinition(itemInfo.ShortName);
                if (itemDefinition == null)
                {
                    LogAction(player, $"Предмет для рецепта не найден: {itemInfo.ShortName}");
                    blueprint.Remove();
                    return;
                }

                blueprint.blueprintTarget = itemDefinition.itemid;

                if (!player.inventory.GiveItem(blueprint))
                {
                    blueprint.Drop(player.transform.position, Vector3.down * 3);
                    LogAction(player, $"Рецепт выброшен из-за нехватки места: {itemInfo.ShortName}");
                    }
                    else
                    {
                    LogAction(player, $"Рецепт выдан в инвентарь: {itemInfo.ShortName}");
                }
            }
            catch (Exception ex)
            {
                LogAction(player, $"Ошибка создания рецепта: {ex.Message}");
            }
        }

        #endregion

        private void ProcessTake(BasePlayer player, Dictionary<string, object> obj)
        {
            //foreach (var check in obj)
            //   PrintError(check.Key + " -> " + check.Value);
            LogAction(player, $"Начало обработки товара");
            WItem itemInfo = new WItem(obj);
            try
            {
                if (itemInfo.IsItem)
                {
                    ProcessItemGive(player, itemInfo);
                }

                if (itemInfo.IsCommand)
                {
                    ProcessCommandGive(player, itemInfo);
                }

                if (itemInfo.IsBlueprint)
                {
                    ProcessBlueprintGive(player, itemInfo);
                }
            }
            catch (Exception e)
            {
                LogAction(player, $"Ошибка получения товара [{itemInfo.ID}][{itemInfo.Name}]");
                LogToFile("!Errors", $"{DateTime.Now.ToShortTimeString()}| Error: '{e.ToString()}'", this);
                PrintError($"Error receiving product [{itemInfo.ID}], info saved to log");
            }

        }

        private static readonly Dictionary<int, string> IDToShortName = new Dictionary<int, string> { [-1461508848] = "rifle.ak", [2115555558] = "ammo.handmade.shell", [-533875561] = "ammo.pistol", [1621541165] = "ammo.pistol.fire", [-422893115] = "ammo.pistol.hv", [815896488] = "ammo.rifle", [805088543] = "ammo.rifle.explosive", [449771810] = "ammo.rifle.incendiary", [1152393492] = "ammo.rifle.hv", [1578894260] = "ammo.rocket.basic", [1436532208] = "ammo.rocket.fire", [542276424] = "ammo.rocket.hv", [1594947829] = "ammo.rocket.smoke", [-1035059994] = "ammo.shotgun", [1818890814] = "ammo.shotgun.fire", [1819281075] = "ammo.shotgun.slug", [1685058759] = "antiradpills", [93029210] = "apple", [-1565095136] = "apple.spoiled", [-1775362679] = "arrow.bone", [-1775249157] = "arrow.fire", [-1280058093] = "arrow.hv", [-420273765] = "arrow.wooden", [563023711] = "autoturret", [790921853] = "axe.salvaged", [-337261910] = "bandage", [498312426] = "barricade.concrete", [504904386] = "barricade.metal", [-1221200300] = "barricade.sandbags", [510887968] = "barricade.stone", [-814689390] = "barricade.wood", [1024486167] = "barricade.woodwire", [2021568998] = "battery.small", [97329] = "bbq", [1046072789] = "trap.bear", [97409] = "bed", [-1480119738] = "tool.binoculars", [1611480185] = "black.raspberries", [-1386464949] = "bleach", [93832698] = "blood", [-1063412582] = "blueberries", [-1887162396] = "blueprintbase", [-55660037] = "rifle.bolt", [919780768] = "bone.club", [-365801095] = "bone.fragments", [68998734] = "botabag", [-853695669] = "bow.hunting", [271534758] = "box.wooden.large", [-770311783] = "box.wooden", [-1192532973] = "bucket.water", [-307490664] = "building.planner", [707427396] = "burlap.shirt", [707432758] = "burlap.shoes", [-2079677721] = "cactusflesh", [-1342405573] = "tool.camera", [-139769801] = "campfire", [-1043746011] = "can.beans", [2080339268] = "can.beans.empty", [-171664558] = "can.tuna", [1050986417] = "can.tuna.empty", [-1693683664] = "candycaneclub", [523409530] = "candycane", [1300054961] = "cctv.camera", [-2095387015] = "ceilinglight", [1428021640] = "chainsaw", [94623429] = "chair", [1436001773] = "charcoal", [1711323399] = "chicken.burned", [1734319168] = "chicken.cooked", [-1658459025] = "chicken.raw", [-726947205] = "chicken.spoiled", [-341443994] = "chocholate", [1540879296] = "xmasdoorwreath", [94756378] = "cloth", [3059095] = "coal", [3059624] = "corn", [2045107609] = "clone.corn", [583366917] = "seed.corn", [2123300234] = "crossbow", [1983936587] = "crude.oil", [1257201758] = "cupboard.tool", [-1144743963] = "diving.fins", [-1144542967] = "diving.mask", [-1144334585] = "diving.tank", [1066729526] = "diving.wetsuit", [-1598790097] = "door.double.hinged.metal", [-933236257] = "door.double.hinged.toptier", [-1575287163] = "door.double.hinged.wood", [-2104481870] = "door.hinged.metal", [-1571725662] = "door.hinged.toptier", [1456441506] = "door.hinged.wood", [1200628767] = "door.key", [-778796102] = "door.closer", [1526866730] = "xmas.door.garland", [1925723260] = "dropbox", [1891056868] = "ducttape", [1295154089] = "explosive.satchel", [498591726] = "explosive.timed", [1755466030] = "explosives", [726730162] = "facialhair.style01", [-1034048911] = "fat.animal", [252529905] = "femalearmpithair.style01", [471582113] = "femaleeyebrow.style01", [-1138648591] = "femalepubichair.style01", [305916740] = "female_hairstyle_01", [305916742] = "female_hairstyle_03", [305916744] = "female_hairstyle_05", [1908328648] = "fireplace.stone", [-2078972355] = "fish.cooked", [-533484654] = "fish.raw", [1571660245] = "fishingrod.handmade", [1045869440] = "flamethrower", [1985408483] = "flameturret", [97513422] = "flare", [1496470781] = "flashlight.held", [1229879204] = "weapon.mod.flashlight", [-1722829188] = "floor.grill", [1849912854] = "floor.ladder.hatch", [-1266285051] = "fridge", [-1749787215] = "boots.frog", [28178745] = "lowgradefuel", [-505639592] = "furnace", [1598149413] = "furnace.large", [-1779401418] = "gates.external.high.stone", [-57285700] = "gates.external.high.wood", [98228420] = "gears", [1422845239] = "geiger.counter", [277631078] = "generator.wind.scrap", [115739308] = "burlap.gloves", [-522149009] = "gloweyes", [3175989] = "glue", [718197703] = "granolabar", [384204160] = "grenade.beancan", [-1308622549] = "grenade.f1", [-217113639] = "fun.guitar", [-1580059655] = "gunpowder", [-1832205789] = "male_hairstyle_01", [305916741] = "female_hairstyle_02", [936777834] = "attire.hide.helterneck", [-1224598842] = "hammer", [-1976561211] = "hammer.salvaged", [-1406876421] = "hat.beenie", [-1397343301] = "hat.boonie", [1260209393] = "bucket.helmet", [-1035315940] = "burlap.headwrap", [-1381682752] = "hat.candle", [696727039] = "hat.cap", [-2128719593] = "coffeecan.helmet", [-1178289187] = "deer.skull.mask", [1351172108] = "heavy.plate.helmet", [-450738836] = "hat.miner", [-966287254] = "attire.reindeer.headband", [340009023] = "riot.helmet", [124310981] = "hat.wolf", [1501403549] = "wood.armor.helmet", [698310895] = "hatchet", [523855532] = "hazmatsuit", [2045246801] = "clone.hemp", [583506109] = "seed.hemp", [-148163128] = "attire.hide.boots", [-132588262] = "attire.hide.skirt", [-1666761111] = "attire.hide.vest", [-465236267] = "weapon.mod.holosight", [-1211618504] = "hoodie", [2133577942] = "hq.metal.ore", [-1014825244] = "humanmeat.burned", [-991829475] = "humanmeat.cooked", [-642008142] = "humanmeat.raw", [661790782] = "humanmeat.spoiled", [-1440143841] = "icepick.salvaged", [569119686] = "bone.armor.suit", [1404466285] = "heavy.plate.jacket", [-1616887133] = "jacket.snow", [-1167640370] = "jacket", [-1284735799] = "jackolantern.angry", [-1278649848] = "jackolantern.happy", [776005741] = "knife.bone", [108061910] = "ladder.wooden.wall", [255101535] = "trap.landmine", [-51678842] = "lantern", [-789202811] = "largemedkit", [516382256] = "weapon.mod.lasersight", [50834473] = "leather", [-975723312] = "lock.code", [1908195100] = "lock.key", [-1097452776] = "locker", [146685185] = "longsword", [-1716193401] = "rifle.lr300", [193190034] = "lmg.m249", [371156815] = "pistol.m92", [3343606] = "mace", [825308669] = "machete", [830965940] = "mailbox", [1662628660] = "male.facialhair.style02", [1662628661] = "male.facialhair.style03", [1662628662] = "male.facialhair.style04", [-1832205788] = "male_hairstyle_02", [-1832205786] = "male_hairstyle_04", [1625090418] = "malearmpithair.style01", [-1269800768] = "maleeyebrow.style01", [429648208] = "malepubichair.style01", [-1832205787] = "male_hairstyle_03", [-1832205785] = "male_hairstyle_05", [107868] = "map", [997973965] = "mask.balaclava", [-46188931] = "mask.bandana", [-46848560] = "metal.facemask", [-2066726403] = "bearmeat.burned", [-2043730634] = "bearmeat.cooked", [1325935999] = "bearmeat", [-225234813] = "deermeat.burned", [-202239044] = "deermeat.cooked", [-322501005] = "deermeat.raw", [-1851058636] = "horsemeat.burned", [-1828062867] = "horsemeat.cooked", [-1966381470] = "horsemeat.raw", [968732481] = "meat.pork.burned", [991728250] = "meat.pork.cooked", [-253819519] = "meat.boar", [-1714986849] = "wolfmeat.burned", [-1691991080] = "wolfmeat.cooked", [179448791] = "wolfmeat.raw", [431617507] = "wolfmeat.spoiled", [688032252] = "metal.fragments", [-1059362949] = "metal.ore", [1265861812] = "metal.plate.torso", [374890416] = "metal.refined", [1567404401] = "metalblade", [-1057402571] = "metalpipe", [-758925787] = "mining.pumpjack", [-1411620422] = "mining.quarry", [88869913] = "fish.minnows", [-2094080303] = "smg.mp5", [843418712] = "mushroom", [-1569356508] = "weapon.mod.muzzleboost", [-1569280852] = "weapon.mod.muzzlebrake", [449769971] = "pistol.nailgun", [590532217] = "ammo.nailgun.nails", [3387378] = "note", [1767561705] = "burlap.trousers", [106433500] = "pants", [-1334615971] = "heavy.plate.pants", [-135651869] = "attire.hide.pants", [-1595790889] = "roadsign.kilt", [-459156023] = "pants.shorts", [106434956] = "paper", [-578028723] = "pickaxe", [-586116979] = "jar.pickle", [-1379225193] = "pistol.eoka", [-930579334] = "pistol.revolver", [548699316] = "pistol.semiauto", [142147109] = "planter.large", [148953073] = "planter.small", [102672084] = "attire.hide.poncho", [640562379] = "pookie.bear", [-1732316031] = "xmas.present.large", [-2130280721] = "xmas.present.medium", [-1725510067] = "xmas.present.small", [1974032895] = "propanetank", [-225085592] = "pumpkin", [509654999] = "clone.pumpkin", [466113771] = "seed.pumpkin", [2033918259] = "pistol.python", [2069925558] = "target.reactive", [-1026117678] = "box.repair.bench", [1987447227] = "research.table", [540154065] = "researchpaper", [1939428458] = "riflebody", [-288010497] = "roadsign.jacket", [-847065290] = "roadsigns", [3506021] = "rock", [649603450] = "rocket.launcher", [3506418] = "rope", [569935070] = "rug.bear", [113284] = "rug", [1916127949] = "water.salt", [-1775234707] = "salvaged.cleaver", [-388967316] = "salvaged.sword", [2007564590] = "santahat", [-1705696613] = "scarecrow", [670655301] = "hazmatsuit_scientist", [1148128486] = "hazmatsuit_scientist_peacekeeper", [-141135377] = "weapon.mod.small.scope", [109266897] = "scrap", [-527558546] = "searchlight", [-1745053053] = "rifle.semiauto", [1223860752] = "semibody", [-419069863] = "sewingkit", [-1617374968] = "sheetmetal", [2057749608] = "shelves", [24576628] = "shirt.collared", [-1659202509] = "shirt.tanktop", [2107229499] = "shoes.boots", [191795897] = "shotgun.double", [-1009492144] = "shotgun.pump", [2077983581] = "shotgun.waterpipe", [378365037] = "guntrap", [-529054135] = "shutter.metal.embrasure.a", [-529054134] = "shutter.metal.embrasure.b", [486166145] = "shutter.wood.a", [1628490888] = "sign.hanging.banner.large", [1498516223] = "sign.hanging", [-632459882] = "sign.hanging.ornate", [-626812403] = "sign.pictureframe.landscape", [385802761] = "sign.pictureframe.portrait", [2117976603] = "sign.pictureframe.tall", [1338515426] = "sign.pictureframe.xl", [-1455694274] = "sign.pictureframe.xxl", [1579245182] = "sign.pole.banner.large", [-587434450] = "sign.post.double", [-163742043] = "sign.post.single", [-1224714193] = "sign.post.town", [644359987] = "sign.post.town.roof", [-1962514734] = "sign.wooden.huge", [-705305612] = "sign.wooden.large", [-357728804] = "sign.wooden.medium", [-698499648] = "sign.wooden.small", [1213686767] = "weapon.mod.silencer", [386382445] = "weapon.mod.simplesight", [1859976884] = "skull_fire_pit", [960793436] = "skull.human", [1001265731] = "skull.wolf", [1253290621] = "sleepingbag", [470729623] = "small.oil.refinery", [1051155022] = "stash.small", [865679437] = "fish.troutsmall", [927253046] = "smallwaterbottle", [109552593] = "smg.2", [-2092529553] = "smgbody", [691633666] = "snowball", [-2055888649] = "snowman", [621575320] = "shotgun.spas12", [-2118132208] = "spear.stone", [-1127699509] = "spear.wooden", [-685265909] = "spikes.floor", [552706886] = "spinner.wheel", [1835797460] = "metalspring", [-892259869] = "sticks", [-1623330855] = "stocking.large", [-1616524891] = "stocking.small", [789892804] = "stone.pickaxe", [-1289478934] = "stonehatchet", [-892070738] = "stones", [-891243783] = "sulfur", [889398893] = "sulfur.ore", [-1625468793] = "supply.signal", [1293049486] = "surveycharge", [1369769822] = "fishtrap.small", [586484018] = "syringe.medical", [110115790] = "table", [1490499512] = "targeting.computer", [3552619] = "tarp", [1471284746] = "techparts", [456448245] = "smg.thompson", [110547964] = "torch", [1588977225] = "xmas.decoration.baubels", [918540912] = "xmas.decoration.candycanes", [-471874147] = "xmas.decoration.gingerbreadmen", [205978836] = "xmas.decoration.lights", [-1044400758] = "xmas.decoration.pinecone", [-2073307447] = "xmas.decoration.star", [435230680] = "xmas.decoration.tinsel", [-864578046] = "tshirt", [1660607208] = "tshirt.long", [260214178] = "tunalight", [-1847536522] = "vending.machine", [-496055048] = "wall.external.high.stone", [-1792066367] = "wall.external.high", [562888306] = "wall.frame.cell.gate", [-427925529] = "wall.frame.cell", [995306285] = "wall.frame.fence.gate", [-378017204] = "wall.frame.fence", [447918618] = "wall.frame.garagedoor", [313836902] = "wall.frame.netting", [1175970190] = "wall.frame.shopfront", [525244071] = "wall.frame.shopfront.metal", [-1021702157] = "wall.window.bars.metal", [-402507101] = "wall.window.bars.toptier", [-1556671423] = "wall.window.bars.wood", [61936445] = "wall.window.glass.reinforced", [112903447] = "water", [1817873886] = "water.catcher.large", [1824679850] = "water.catcher.small", [-1628526499] = "water.barrel", [547302405] = "waterjug", [1840561315] = "water.purifier", [-460592212] = "xmas.window.garland", [3655341] = "wood", [1554697726] = "wood.armor.jacket", [-1883959124] = "wood.armor.pants", [-481416622] = "workbench1", [-481416621] = "workbench2", [-481416620] = "workbench3", [-1151126752] = "xmas.lightstring", [-1926458555] = "xmas.tree" };

        private void UI_RecountPosition(ref double xSwitch, ref double ySwitch, int count, int max)
        {
            var stringAmount = Math.Ceiling((double)max / Settings.InterfaceSettings.ItemOnString);
            var currentString = Math.Floor((double)count / Settings.InterfaceSettings.ItemOnString);
            var currentPosition = count % Settings.InterfaceSettings.ItemOnString;


            var topYPosition = 0 + (float)stringAmount / 2 * Settings.InterfaceSettings.ItemSide + ((float)stringAmount / 2 - 1) * Settings.InterfaceSettings.ItemMargin;
            var topXPosition = 0 - (float)Settings.InterfaceSettings.ItemOnString / 2 * Settings.InterfaceSettings.ItemSide - ((float)Settings.InterfaceSettings.ItemOnString / 2 - 1) * Settings.InterfaceSettings.ItemMargin;

            var curYPosition = topYPosition - currentString * Settings.InterfaceSettings.ItemSide - (currentString) * (Settings.InterfaceSettings.ItemMargin + (Settings.InterfaceSettings.TextShow ? 20 : 0));
            var curXPosition = topXPosition + currentPosition * Settings.InterfaceSettings.ItemSide + (currentPosition) * Settings.InterfaceSettings.ItemMargin;

            xSwitch = curXPosition;
            ySwitch = curYPosition;
        }

        public static bool CheckInstantWork = false;
        public Timer CheckInstantWorkGlobalTimer = null;

        private void CheckInstant()
        {
            if (CheckInstantWork == true)
                return;

            CheckInstantWork = true;
            if (CheckInstantWorkGlobalTimer != null)
                CheckInstantWorkGlobalTimer.Destroy();

            CheckInstantWorkGlobalTimer = timer.Once(300, () => { CheckInstantWork = false; });

            Request($"&method=basket.commands.instant", (code, response) =>
            {
                switch (code)
                {
                    case 0:
                        {
                            LogAction(null, $"Ошибка выполнения запроса для выдачи автоматических команд");
                            CheckInstantWork = false;
                            break;
                        }
                    case 200:
                        {
                            var firstInfo = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                            if (firstInfo.ContainsKey("result"))
                            {
                                if (firstInfo["result"].ToString() == "success")
                                {
                                    List<object> data = firstInfo["data"] as List<object>;
                                    int i = 1;
                                    timer.Once(data.Count + 2, () => { CheckInstantWork = false; });

                                    foreach (Dictionary<string, object> product in data)
                                    {
                                        if (product.ContainsKey("id"))
                                        {
                                            timer.Once(i, () => { TakeInstant(Convert.ToInt32(product["id"]), product["steam_id"].ToString()); } );
                                            i++;
                                        }

                                    }
                                } else CheckInstantWork = false;
                            } else CheckInstantWork = false;
                            break;
                        }
                    default:
                        {
                            LogAction(null, $"Ошибка выполнения запроса для выдачи автоматических команд");
                            CheckInstantWork = false;
                            break;
                        }
                }
            }, null);
        }

        private void TakeInstant(int GsProductId, string SteamId)
        {
            LogAction(null, $"---------------------------------");
            LogAction(null, $"[AutoCommands] Запрос на получение товара [{GsProductId}][{SteamId}]");
            Request($"&method=take&item=true&id={GsProductId}", (i, s) =>
            {
                switch (i)
                {
                    case 0:
                        LogAction(null, $"[AutoCommands] API не ответило на запрос: {GsProductId}");
                        PrintError("Api does not responded to a request");
                        break;
                    case 200:
                        Dictionary<string, object> response = JsonConvert.DeserializeObject<Dictionary<string, object>>(s, new KeyValuesConverter());
                        if (!response.ContainsKey("data"))
                        {
                            LogAction(null, $"[AutoCommands] Ошибка получения товара, отсутствует Data [{GsProductId}]");
                            return;
                        } else
                        {
                            Request($"&method=gived&gived=true&id={GsProductId}", (code, newResponse) =>
                            {
                                if (code != 200 || JsonConvert.DeserializeObject<JObject>(newResponse)["result"].ToString() != "success")
                                {
                                    LogAction(null, $"[AutoCommands] Ошибка получения товара [{GsProductId}][{SteamId}]");
                                    return;
                                }
                                else
                                {
                                    WItem itemInfo = new WItem(response["data"] as Dictionary<string, object>);
                                    if (itemInfo.IsCommand)
                                    {
                                        LogAction(null, $"[AutoCommands] Товар отмечен полученным [{GsProductId}][{SteamId}]");
                                        string command = itemInfo.Command.Replace("\n", "|").Replace("%steamid%", SteamId, StringComparison.OrdinalIgnoreCase);
                                        foreach (var check in command.Split('|'))
                                        {
                                            LogAction(null, $"[AutoCommands] Исполнение команды: {check} [{GsProductId}][{SteamId}]");
                                            Server.Command(check);
                                        }
                                    }
                                    else
                                    {
                                        LogAction(null, $"[AutoCommands] Ошибка получения (товар не является командой) [{GsProductId}][{SteamId}]");
                                    }
                                }
                            }, null);
                        }

                        break;
                    case 404:
                        LogAction(null, $"[AutoCommands] Api не ответило на запрос: {GsProductId}");
                        LogAction(null, "Response code: 404, please check your configurations", true);
                        break;
                }
            }, null);
        }

        private Timer RestartTimer = null;
        private void SetReserveApiLink()
        {
            if (BaseRequest.Contains(MainApiLink))
            {
                /*LogAction(null, $"Trying use reserve api link", true);
                BaseRequest = ReserveApiLink + BaseRequestParams;
                if (!Initialized)
                    FetchShopUrl();
                timer.Once(300, () => {
                    TryChangeLink();
                });*/
            } else
            {
                LogAction(null, $"Plugin will automatically restart after 10 minutes", true);
                RestartTimer = timer.Once(600, () => { LogAction(null, $"Restarting...", true); Server.Command("o.reload ProstojRUST"); });
            }
        }

        private void TryChangeLink()
        {
            string newLink = ReserveApiLink + BaseRequestParams;
            if (BaseRequest.Contains(ReserveApiLink))
            {
                newLink = MainApiLink + BaseRequestParams;
            }
            CheckRequest($"{newLink}&method=info&info=true", (code, response) =>
            {
                switch (code)
                {
                    case 200:
                        {
                            if (response.Length < 1)
                            {
                                return;
                            }
                            var firstInfo = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                            if (!firstInfo.ContainsKey("data"))
                            {
                                initialization = false;
                            }
                            else
                            {
                                if (RestartTimer != null)
                                {
                                    LogAction(null, $"Restart was canceled");
                                    RestartTimer.Destroy();
                                    RestartTimer = null;
                                }
                                LogAction(null, $"API link was changed", true);
                                BaseRequest = newLink;
                                if (!Initialized)
                                    FetchShopUrl();
                                if (BaseRequest.Contains(ReserveApiLink))
                                    timer.Once(600, () => { TryChangeLink(); });
                            }
                            break;
                        }
                    default:
                        break;

                }
            });
        }

        private void FetchShopUrl()
        {
            initialization = true;
            timer.Once(5, () => { initialization = false; });
            Initialized = true;
            string newLink = ReserveApiLink + BaseRequestParams;
            if (BaseRequest.Contains(ReserveApiLink))
            {
                newLink = MainApiLink + BaseRequestParams;
            }
            Request($"{newLink}&method=info&info=true", (code, response) =>
            {
                LogAction(null, $"-----------------------------", true);
                LogAction(null, $" ProstojStore {Version} (c) 2023", true);
                try
                {
                    if (response.Length < 1)
                    {
                        LogToFile("!Errors", $"{DateTime.Now.ToShortTimeString()}| Response: '{response}'", this);
                        LogAction(null, " Incorrect API response! Saved to log!", true, true);
                        LogAction(null, $"-----------------------------", true);
                        SetReserveApiLink();
                        initialization = false;
                        return;
                    }
                    var firstInfo = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                    if (!firstInfo.ContainsKey("data"))
                    {
                        LogAction(null, $"     Wrong Secret Key", true, true);
                        LogAction(null, $"-----------------------------", true);
                        initialization = false;
                    }
                    else
                    {
                        firstInfo = firstInfo["data"] as Dictionary<string, object>;
                        ShopURL = firstInfo["link"].ToString();
                        StartBalance = int.Parse(firstInfo["default_balance"].ToString());
                        LogAction(null, "      Initialized - OK", true);
                        LogAction(null, $"-----------------------------", true);
                        //BasePlayer.activePlayerList.ForEach(OnPlayerInit);
                        foreach (var pl in BasePlayer.activePlayerList)
                        {
                            OnPlayerConnected(pl);
                        }

                        Initialized = true;
                        initialization = false;
                    }
                }
                catch (JsonException e)
                {

                    LogToFile("Errors", $"{DateTime.Now.ToShortTimeString()}| JsonError | Response: '{response}'", this);
                    LogAction(null, " JSON Error! Saved to log!", true, true);
                    LogAction(null, $"-----------------------------", true);
                    SetReserveApiLink();
                    initialization = false;
                }

            });
        }

        private static int errorsReq = 0;
        private static void Request(string ask, Action<int, string> callback, BasePlayer player = null, bool cancel = true)
        {
            float timeout = 3000f;
            if (player != null && !Delays.CanRequest(player))
            {
                instance.timer.Once(1f, () =>
                {
                    Request(ask, callback, player, cancel);
                });

                return;
            };
            if (player != null) ServerMgr.Instance.StartCoroutine(Delays.MakeRequest(player));
            Dictionary<string, string> reqHeaders = new Dictionary<string, string>{{ "User-Agent", "Prostoj Plugin" }};
            if (!SecureConnection && instance.BaseRequest.Contains("https://")) instance.BaseRequest = instance.BaseRequest.Replace("https://", "http://");
            instance.webrequest.Enqueue(instance.BaseRequest + ask, "", (code, response) =>
            {
                if (instance == null) return;

                switch (code)
                {
                    case 200:
                        {
                            break;
                        }

                    case 404:
                        {
                            instance.LogAction(null, $"Please check your configuration! [404] #2", true);
                            break;
                        }

                    default:
                        {
                            instance.LogAction(null, $"Time out waiting for GS API #1");
                            errorsReq++;

                            if (errorsReq >= 10)
                            {
                                errorsReq = 0;
                                instance.TryChangeLink();
                            }

                            break;
                        }
                }

                if (SecureConnection && response.Contains("The authentication or decryption has failed."))
                {
                    instance.LogAction(null, "HTTPS request is broken (broken CA certificate?). Changed to non secure connection!", true, true);

                    SecureConnection = false;
                    instance.BaseRequest = instance.BaseRequest.Replace("https://", "http://");

                    Request(ask, callback, player, cancel);
                    return;
                }

                if (player != null && cancel) Delays.FinishRequest(player);

                callback?.Invoke(code, response);
            }, instance, RequestMethod.GET, reqHeaders, timeout);

        }
        private static void CheckRequest(string link, Action<int, string> callback)
        {
            float timeout = 3000f;
            Dictionary<string, string> reqHeaders = new Dictionary<string, string> { { "User-Agent", "Prostoj Plugin" } };
            if (!SecureConnection && link.Contains("https://")) link = link.Replace("https://", "http://");
            instance.webrequest.Enqueue(link, "", (code, response) =>
            {
                if (instance == null) return;

                switch (code)
                {
                    case 0:
                        {

                            break;
                        }
                    case 404:
                        {

                            break;
                        }
                }

                callback?.Invoke(code, response);
            }, instance, RequestMethod.GET, reqHeaders, timeout);

        }

    private static void RequestPost(string ask, Action<int, string> callback, BasePlayer player = null, bool cancel = true)
        {
            if (player != null && !Delays.CanRequest(player))
            {
                instance.timer.Once(1f, () =>
                {
                    RequestPost(ask, callback, player, cancel);
                }); 
                return;
            };
            if (player != null) ServerMgr.Instance.StartCoroutine(Delays.MakeRequest(player));
            string body, reqLink;
            int pos = instance.BaseRequest.IndexOf("?");
            if (pos < 0)
            {
                body = ask;
                reqLink = instance.BaseRequest;
            }
            else
            {
                reqLink = instance.BaseRequest.Substring(0, pos);
                body = $"{instance.BaseRequest.Substring(pos + 1)}{ask}";
            }
            Dictionary<string, string> reqHeaders = new Dictionary<string, string> { { "User-Agent", "Prostoj Plugin" } };
            if (!SecureConnection && reqLink.Contains("https://")) reqLink = reqLink.Replace("https://", "http://");
            instance.webrequest.Enqueue(reqLink, body, (code, response) =>
            {
                if (instance == null) return;

                switch (code)
                {
                    case 0:
                        {
                            instance.PrintError($"Time out waiting for GS API #1");
                            break;
                        }
                    case 404:
                        {
                            instance.PrintError($"Plese check your configuration! [404] #2");
                            break;
                        }
                }

                if (player != null && cancel) Delays.FinishRequest(player);

                callback?.Invoke(code, response);
            }, instance, RequestMethod.POST, reqHeaders);
        }

        #region Enhanced Logging

        private enum LogLevel
        {
            Info,
            Warning,
            Error,
            Debug
        }

        private void LogAction(BasePlayer player, string text, bool printToConsole = false, bool printError = false)
        {
            LogMessage(player, text, printError ? LogLevel.Error : LogLevel.Info, printToConsole);
        }

        private void LogMessage(BasePlayer player, string message, LogLevel level = LogLevel.Info, bool printToConsole = false)
        {
            string timestamp = DateTime.Now.ToString("HH:mm:ss");
            string playerInfo = player != null ? $"[{player.userID}|{player.displayName}]" : "[SERVER]";
            string levelStr = level.ToString().ToUpper();
            
            string logEntry = $"{timestamp} [{levelStr}] {playerInfo} {message}";

            // Логируем в файл
            LogToFile("!global", logEntry, this);
            
            // Логируем для конкретного игрока
            if (player != null)
            {
                LogToFile($"{player.userID}", logEntry, this);
            }

            // Выводим в консоль если нужно
            if (printToConsole)
            {
                switch (level)
                {
                    case LogLevel.Error:
                        PrintError(logEntry);
                        break;
                    case LogLevel.Warning:
                        PrintWarning(logEntry);
                        break;
                    case LogLevel.Debug:
                        if (Settings.APISettings.ServerID == "DEBUG")
                            Puts($"[DEBUG] {logEntry}");
                        break;
                    default:
                        Puts(logEntry);
                        break;
                }
            }
        }

        private void LogError(BasePlayer player, string error, Exception ex = null)
        {
            string message = ex != null ? $"{error}: {ex.Message}" : error;
            LogMessage(player, message, LogLevel.Error, true);
            
            if (ex != null)
            {
                LogToFile("!Errors", $"{DateTime.Now} | {error} | {ex}", this);
            }
        }

        private void LogWarning(BasePlayer player, string warning)
        {
            LogMessage(player, warning, LogLevel.Warning, true);
        }

        private void LogDebug(BasePlayer player, string debug)
        {
            LogMessage(player, debug, LogLevel.Debug);
        }

        private void LogPlayerAction(BasePlayer player, string text) => LogMessage(player, text);

        #endregion

        #region Error Handling

        private bool HandleSafeOperation(BasePlayer player, string operation, Action action)
        {
            try
            {
                action?.Invoke();
                return true;
            }
            catch (Exception ex)
            {
                LogError(player, $"Ошибка в операции '{operation}'", ex);
                return false;
            }
        }

        private T HandleSafeOperation<T>(BasePlayer player, string operation, Func<T> func, T defaultValue = default(T))
        {
            try
            {
                return func != null ? func() : defaultValue;
            }
            catch (Exception ex)
            {
                LogError(player, $"Ошибка в операции '{operation}'", ex);
                return defaultValue;
            }
        }

        #endregion

        private static double CurrentTime() => DateTime.UtcNow.Subtract(new DateTime(1970, 1, 1)).TotalSeconds;

        #endregion
    }
}