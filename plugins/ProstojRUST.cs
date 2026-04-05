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
    [Info("ProstojRUST", "prostoj.store", "0.4.3")]
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
                [JsonProperty("Базовый URL legacy-магазина (v1, без / на конце), не prostoj.store/api")]
                public string LegacyShopApiRoot = "https://api.prostoj.store/v1/rust-legacy-store";
                [JsonProperty("Резервный URL legacy-магазина (опционально, без / на конце)")]
                public string LegacyShopApiReserveRoot = "";
            }

            public class Interface
            {
                [JsonProperty("Включить интерфейс /store")]
                public bool EnableStoreUI = true;
                [JsonProperty("Загружать стандартные изображения через клиент")]
                public bool LoadSpriteImages = true;
                [JsonProperty("Выполнять instant-команды, если игрок не на сервере")]
                public bool ExecuteInstantCommandsWhenNotInServer = true;
                [JsonProperty("Включить изображение корзины")]
                public bool BucketEnable = false;
                [JsonProperty("Включить отображение названий предметов")]
                public bool TextShow = true;
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
                    if (string.IsNullOrEmpty(ID) && data.ContainsKey("basketId")) ID = data["basketId"].ToString();

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

                    if (data.ContainsKey("block_date"))
                        Block_Date = ParseBlockDate(data["block_date"]);
                    if (data.ContainsKey("blocked_at"))
                        Block_Date = Math.Max(Block_Date, ParseBlockDate(data["blocked_at"]));
                    if (data.ContainsKey("blockedAt"))
                        Block_Date = Math.Max(Block_Date, ParseBlockDate(data["blockedAt"]));

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

                    var imageLibrary = instance.plugins.Find("ImageLibrary");
                    if (imageLibrary != null)
                    {
                        //if (ItemID == 0)
                        //{
                            if ((bool)imageLibrary.Call("HasImage", $"IconGS.{ID}"))
                            {
                                string probablyId = (string)imageLibrary.Call("GetImage", $"IconGS.{ID}");
                                if (!probablyId.IsNullOrEmpty() && probablyId != instance.NoImageID && probablyId != instance.LoadingImageID)
                                    ImageUrl = probablyId;
                                return;
                            }

                            if (!ImageUrl.IsNullOrEmpty())
                            {
                                imageLibrary.Call("AddImage", ImageUrl.Replace("https", "http"), $"IconGS.{ID}");
                            }
                        //}
                        //else
                        //{
                        //    string probablyId = (string)imageLibrary.Call("GetImage", ShortName);
                        //    if (!probablyId.IsNullOrEmpty() && probablyId != instance.NoImageID && probablyId != instance.LoadingImageID)
                        //        ImageUrl = probablyId;
                        //}
                    }
                }
                catch (NullReferenceException e)
                {
                    Interface.Oxide.LogError(JsonConvert.SerializeObject(data));
                }
            }

            private static double ParseBlockDate(object value)
            {
                if (value == null) return 0;
                var text = value.ToString();
                if (string.IsNullOrEmpty(text)) return 0;

                if (double.TryParse(text, out var unix))
                {
                    if (unix > 9999999999) unix /= 1000d;
                    return unix;
                }

                if (DateTime.TryParse(text, out var dt))
                    return (dt.ToUniversalTime() - new DateTime(1970, 1, 1)).TotalSeconds;

                return 0;
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
        private string ShopURL = "PROSTOJ.STORE";
        private int StartBalance = 0;
        public string NoImageID = "";
        public string LoadingImageID = "";
        private Coroutine LoadingCoroutine;
        private Dictionary<int, Dictionary<ulong, int>> ListTimeOutCommand = new Dictionary<int, Dictionary<ulong, int>>();
        private Dictionary<ulong, List<int>> playersBasketCache = new Dictionary<ulong, List<int>>();
        private readonly Dictionary<ulong, List<WItem>> playerBaskets = new Dictionary<ulong, List<WItem>>();
        private HashSet<ulong> ListBannedCommandUserID = new HashSet<ulong>();
        private Timer TimerCheckInstant;
        private const string StoreLayer = "ProstojRUST.Store";
        private const string HelpLayer = "ProstojRUST.Help";
        private const string IconLayer = "ProstojRUST.Icon";
        private string MainApiLink => NormalizeLegacyShopRoot(Settings?.APISettings?.LegacyShopApiRoot);
        private string ReserveApiLink
        {
            get
            {
                if (string.IsNullOrWhiteSpace(Settings?.APISettings?.LegacyShopApiReserveRoot))
                    return MainApiLink;
                return NormalizeLegacyShopRoot(Settings.APISettings.LegacyShopApiReserveRoot);
            }
        }

        private static string NormalizeLegacyShopRoot(string root)
        {
            if (string.IsNullOrWhiteSpace(root))
                return "https://api.prostoj.store/v1/rust-legacy-store/";
            return root.Trim().TrimEnd('/') + "/";
        }

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
            foreach (var pl in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(pl, IconLayer);
                CuiHelper.DestroyUi(pl, StoreLayer);
                CuiHelper.DestroyUi(pl, HelpLayer);
            }
        }

        #endregion

        #region Hooks

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            StatHandler.AddStat(new StatHandler.TimeStat(player));
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (player.IsReceivingSnapshot)
            {
                NextTick(() => OnPlayerConnected(player));
                return;
            }

            InitializeIcon(player);
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
            if (!Initialized || initialization)
            {
                player.ChatMessage(_(player, "PluginNotInitialized"));
                return;
            }

            if (!Settings.InterfaceSettings.EnableStoreUI)
            {
                player.SendConsoleCommand("chat.add", 0, 76561198394504608, "Чтобы вывести предметы перейдите по ссылке prostoj.store/store");
                return;
            }

            ShowStoreUI(player, 0);
        }

        [ConsoleCommand("UI_ProstojRUST")]
        private void CmdStoreUi(ConsoleSystem.Arg args)
        {
            var player = args.Player();
            if (player == null || !args.HasArgs()) return;

            var action = args.Args[0];
            switch (action)
            {
                case "page":
                    ShowStoreUI(player, args.GetInt(1), false);
                    return;
                case "help":
                    ShowHelpUI(player);
                    return;
                case "take":
                    RequestTakeFromBasket(player, args.GetInt(1), args.GetInt(2));
                    return;
                case "close":
                    CuiHelper.DestroyUi(player, StoreLayer);
                    CuiHelper.DestroyUi(player, HelpLayer);
                    return;
            }
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
			if (args.Args[2] == "true") {
				isBlockedBuilding = true;
			}
            if (!ulong.TryParse(args.Args[0], out steam_id) || !int.TryParse(args.Args[1], out id)) 
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
                player.SendConsoleCommand("chat.add", 0, 76561198394504608, _(player, "PlayerFloodBlock"));
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

        private int BasketItemsPerPage => Settings.InterfaceSettings.ItemOnString * Settings.InterfaceSettings.StringAmount;

        private void ShowStoreUI(BasePlayer player, int page, bool first = true)
        {
            CuiHelper.DestroyUi(player, HelpLayer);
            CuiHelper.DestroyUi(player, StoreLayer);

            var container = new CuiElementContainer();
            container.Add(new CuiPanel
            {
                CursorEnabled = true,
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                Image = { Color = "0 0 0 0.9", Material = "assets/content/ui/uibackgroundblur.mat" }
            }, "Overlay", StoreLayer, StoreLayer);

            container.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 0.98" },
                Text = { Text = _(player, "BASKET"), Align = TextAnchor.MiddleCenter, FontSize = 30, Font = "robotocondensed-bold.ttf" }
            }, StoreLayer);

            container.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0 0.87", AnchorMax = "1 0.92" },
                Text = { Text = _(player, "BASKET.DESCRIPTION"), Align = TextAnchor.MiddleCenter, FontSize = 18, Font = "robotocondensed-regular.ttf" }
            }, StoreLayer);

            container.Add(new CuiButton
            {
                RectTransform = { AnchorMin = "0.02 0.92", AnchorMax = "0.13 0.975" },
                Button = { Color = "0.2 0.2 0.2 0.9", Command = "UI_ProstojRUST help" },
                Text = { Text = _(player, "HELP"), Align = TextAnchor.MiddleCenter, FontSize = 18, Font = "robotocondensed-bold.ttf" }
            }, StoreLayer);

            container.Add(new CuiButton
            {
                RectTransform = { AnchorMin = "0.87 0.92", AnchorMax = "0.98 0.975" },
                Button = { Color = "0.55 0.2 0.2 0.9", Command = "UI_ProstojRUST close", Close = StoreLayer },
                Text = { Text = _(player, "EXIT"), Align = TextAnchor.MiddleCenter, FontSize = 18, Font = "robotocondensed-bold.ttf" }
            }, StoreLayer);

            container.Add(new CuiPanel
            {
                RectTransform = { AnchorMin = "0.05 0.08", AnchorMax = "0.95 0.84" },
                Image = { Color = "0 0 0 0.25" }
            }, StoreLayer, StoreLayer + ".BlockPanel", StoreLayer + ".BlockPanel");

            CuiHelper.AddUi(player, container);

            if (first) RequestBasket(player, page);
            else ShowBasketItemsUI(player, page);
        }

        private void ShowHelpUI(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, StoreLayer);
            CuiHelper.DestroyUi(player, HelpLayer);

            var container = new CuiElementContainer();
            container.Add(new CuiPanel
            {
                CursorEnabled = true,
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                Image = { Color = "0 0 0 0.9", Material = "assets/content/ui/uibackgroundblur.mat" }
            }, "Overlay", HelpLayer, HelpLayer);

            container.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0 0.6", AnchorMax = "1 0.78" },
                Text = { Text = _(player, "USER.MANUAL"), Align = TextAnchor.MiddleCenter, FontSize = 34, Font = "robotocondensed-bold.ttf" }
            }, HelpLayer);

            container.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0 0.3", AnchorMax = "1 0.72" },
                Text = { Text = _(player, "USER.MANUAL.DESCRIPTION"), Align = TextAnchor.MiddleCenter, FontSize = 22, Font = "robotocondensed-regular.ttf" }
            }, HelpLayer);

            var addText = StartBalance > 0 ? _(player, "USER.MANUAL.BALANCE", StartBalance) : string.Empty;
            container.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0 0.1", AnchorMax = "1 0.48" },
                Text = { Text = $"{addText}\n{ShopURL.ToUpper()}", Align = TextAnchor.MiddleCenter, FontSize = 30, Font = "robotocondensed-regular.ttf" }
            }, HelpLayer);

            container.Add(new CuiButton
            {
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                Button = { Color = "0 0 0 0", Command = "chat.say /store", Close = HelpLayer },
                Text = { Text = string.Empty }
            }, HelpLayer);

            CuiHelper.AddUi(player, container);
        }

        private void ShowNotify(BasePlayer player, string text, float destroyTime = 2.5f)
        {
            var notifyName = StoreLayer + ".Notify";
            CuiHelper.DestroyUi(player, notifyName);
            var container = new CuiElementContainer();
            container.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0 0.77", AnchorMax = "1 0.87" },
                Text = { Text = text, Align = TextAnchor.UpperCenter, Font = "robotocondensed-regular.ttf", FontSize = 16 }
            }, StoreLayer, notifyName, notifyName);
            CuiHelper.AddUi(player, container);
            if (destroyTime > 0f) timer.Once(destroyTime, () => CuiHelper.DestroyUi(player, notifyName));
        }

        private void RequestBasket(BasePlayer player, int page)
        {
            Request($"&method=basket&basket=true&steam_id={player.UserIDString}", (code, response) =>
            {
                if (code != 200 || string.IsNullOrEmpty(response))
                {
                    ShowNotify(player, _(player, "BASKET.UNAVAILABLE"));
                    return;
                }

                var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                if (data == null || !data.ContainsKey("result") || data["result"]?.ToString() != "success")
                {
                    ShowNotify(player, _(player, "BASKET.NO.AUTH"));
                    return;
                }

                var newItems = new List<WItem>();
                var list = data.ContainsKey("data") ? data["data"] as List<object> : null;
                if (list != null)
                {
                    foreach (var obj in list)
                    {
                        var raw = obj as Dictionary<string, object>;
                        if (raw == null) continue;
                        var item = new WItem(raw);
                        if (!string.IsNullOrEmpty(item.ID)) newItems.Add(item);
                    }
                }

                playerBaskets[player.userID] = newItems;
                ShowBasketItemsUI(player, page);
            }, player);
        }

        private void ShowBasketItemsUI(BasePlayer player, int page)
        {
            CuiHelper.DestroyUi(player, StoreLayer + ".BlockPanel.Content");

            if (!playerBaskets.TryGetValue(player.userID, out var basket))
            {
                ShowNotify(player, _(player, "BASKET.EMPTY"));
                return;
            }

            var content = new CuiElementContainer();
            var contentRoot = StoreLayer + ".BlockPanel.Content";
            content.Add(new CuiPanel
            {
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                Image = { Color = "0 0 0 0" }
            }, StoreLayer + ".BlockPanel", contentRoot, contentRoot);

            var start = Math.Max(0, page * BasketItemsPerPage);
            var pageItems = basket.Skip(start).Take(BasketItemsPerPage).ToList();

            content.Add(new CuiLabel
            {
                RectTransform = { AnchorMin = "0.45 -0.04", AnchorMax = "0.55 0.04" },
                Text = { Text = (page + 1).ToString(), Align = TextAnchor.MiddleCenter, FontSize = 28, Font = "robotocondensed-bold.ttf" }
            }, contentRoot);

            content.Add(new CuiButton
            {
                RectTransform = { AnchorMin = "0.37 -0.04", AnchorMax = "0.44 0.04" },
                Button = { Color = "0 0 0 0.35", Command = page > 0 ? $"UI_ProstojRUST page {page - 1}" : string.Empty },
                Text = { Text = "<", Align = TextAnchor.MiddleCenter, FontSize = 34 }
            }, contentRoot);

            content.Add(new CuiButton
            {
                RectTransform = { AnchorMin = "0.56 -0.04", AnchorMax = "0.63 0.04" },
                Button = { Color = "0 0 0 0.35", Command = (start + BasketItemsPerPage) < basket.Count ? $"UI_ProstojRUST page {page + 1}" : string.Empty },
                Text = { Text = ">", Align = TextAnchor.MiddleCenter, FontSize = 34 }
            }, contentRoot);

            for (var i = 0; i < pageItems.Count; i++)
            {
                double x = 0;
                double y = 0;
                UI_RecountPosition(ref x, ref y, i, BasketItemsPerPage);
                var item = pageItems[i];
                var itemRoot = $"{contentRoot}.item.{i}";
                var itemId = 0;
                int.TryParse(item.ID, out itemId);

                content.Add(new CuiPanel
                {
                    RectTransform =
                    {
                        AnchorMin = "0.5 0.58",
                        AnchorMax = "0.5 0.58",
                        OffsetMin = $"{x} {y - Settings.InterfaceSettings.ItemSide}",
                        OffsetMax = $"{x + Settings.InterfaceSettings.ItemSide} {y}"
                    },
                    Image = { Color = "0.12 0.12 0.12 0.85" }
                }, contentRoot, itemRoot, itemRoot);

                if (Settings.InterfaceSettings.LoadSpriteImages && item.ItemID != 0)
                {
                    content.Add(new CuiElement
                    {
                        Parent = itemRoot,
                        Components =
                        {
                            new CuiImageComponent { ItemId = item.ItemID },
                            new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5" }
                        }
                    });
                }
                else if (!string.IsNullOrEmpty(item.ImageUrl))
                {
                    var isUrl = item.ImageUrl.StartsWith("http", StringComparison.OrdinalIgnoreCase);
                    if (isUrl)
                    {
                        content.Add(new CuiElement
                        {
                            Parent = itemRoot,
                            Components =
                            {
                                new CuiRawImageComponent { Url = item.ImageUrl },
                                new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5" }
                            }
                        });
                    }
                    else
                    {
                        content.Add(new CuiElement
                        {
                            Parent = itemRoot,
                            Components =
                            {
                                new CuiRawImageComponent { Png = item.ImageUrl },
                                new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 5", OffsetMax = "-5 -5" }
                            }
                        });
                    }
                }

                if (item.Amount > 1)
                {
                    content.Add(new CuiLabel
                    {
                        RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 4", OffsetMax = "-6 -4" },
                        Text = { Text = "x" + item.Amount, Align = TextAnchor.LowerRight, FontSize = 20, Font = "robotocondensed-bold.ttf" }
                    }, itemRoot);
                }

                content.Add(new CuiButton
                {
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                    Button = { Color = "0 0 0 0", Command = $"UI_ProstojRUST take {i} {itemId}" },
                    Text = { Text = string.Empty }
                }, itemRoot);

                var blockedLeft = item.Block_Date - CurrentTime();
                if (item.Blocked || blockedLeft > 0)
                {
                    content.Add(new CuiButton
                    {
                        RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0" },
                        Button = { FadeIn = 1f, Color = "1 0.5 0.5 0.2", Command = $"UI_ProstojRUST take {i} {itemId}" },
                        Text =
                        {
                            Text = $"ЗАБЛОКИРОВАНО\n{FormatLeftTime(blockedLeft)}",
                            Align = TextAnchor.MiddleCenter,
                            Font = "robotocondensed-bold.ttf",
                            Color = "1 0.7 0.7 1",
                            FontSize = 12
                        }
                    }, itemRoot, itemRoot + ".blocked", itemRoot + ".blocked");
                }

                if (Settings.InterfaceSettings.TextShow)
                {
                    content.Add(new CuiLabel
                    {
                        RectTransform = { AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "0 -22", OffsetMax = "0 0" },
                        Text = { Text = item.Name ?? "Item", Align = TextAnchor.MiddleCenter, FontSize = 14, Color = "1 1 1 0.8" }
                    }, itemRoot);
                }
            }

            if (pageItems.Count == 0)
            {
                content.Add(new CuiLabel
                {
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                    Text = { Text = _(player, "BASKET.EMPTY"), Align = TextAnchor.MiddleCenter, FontSize = 22, Font = "robotocondensed-bold.ttf" }
                }, contentRoot);
            }

            CuiHelper.AddUi(player, content);
        }

        private void RequestTakeFromBasket(BasePlayer player, int index, int basketId)
        {
            if (basketId <= 0)
            {
                ShowNotify(player, _(player, "ItemNotFound"));
                return;
            }

            if (playerBaskets.TryGetValue(player.userID, out var basketItems))
            {
                var selected = basketItems.FirstOrDefault(x => int.TryParse(x.ID, out var id) && id == basketId);
                if (selected != null)
                {
                    var leftTime = selected.Block_Date - CurrentTime();
                    if (selected.Blocked || leftTime > 0)
                    {
                        ShowNotify(player, _(player, "TAKE.ITEM.BLOCKED", FormatLeftTime(leftTime)));
                        return;
                    }
                }
            }

            if (ListBannedCommandUserID.Contains(player.userID))
            {
                ShowNotify(player, _(player, "PlayerFloodBlock"));
                ShowItemStateOverlay(player, index, basketId, "TAKE.GIVE.ERROR", "1 0.5 0.5 0.2", "1 0.7 0.7 1");
                return;
            }

            if (player.IsDead() || player.IsWounded())
            {
                ShowNotify(player, "Игрок мертв или ранен");
                ShowItemStateOverlay(player, index, basketId, "TAKE.GIVE.ERROR", "1 0.5 0.5 0.2", "1 0.7 0.7 1");
                return;
            }

            ShowNotify(player, _(player, "TAKE.REQUEST.PROCESSING"));
            ShowItemStateOverlay(player, index, basketId, "TAKE.WAIT", "1 1 1 0.2", "1 1 1 0.4", false);

            Request($"&method=item&item=true&steam_id={player.UserIDString}&id={basketId}", (code, response) =>
            {
                if (code != 200 || string.IsNullOrEmpty(response))
                {
                    ShowNotify(player, _(player, "TAKE.GIVE.ERROR.NOTIFY"));
                    ShowItemStateOverlay(player, index, basketId, "TAKE.GIVE.ERROR", "1 0.5 0.5 0.2", "1 0.7 0.7 1");
                    return;
                }

                var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response, new KeyValuesConverter());
                if (data == null || !data.ContainsKey("data"))
                {
                    ShowNotify(player, _(player, "TAKE.GIVE.ERROR.NOTIFY"));
                    ShowItemStateOverlay(player, index, basketId, "TAKE.GIVE.ERROR", "1 0.5 0.5 0.2", "1 0.7 0.7 1");
                    return;
                }

                Request($"&method=gived&gived=true&id={basketId}", (markCode, markResponse) =>
                {
                    if (markCode != 200 || JsonConvert.DeserializeObject<JObject>(markResponse)?["result"]?.ToString() != "success")
                    {
                        ShowNotify(player, _(player, "TAKE.GIVE.ERROR.NOTIFY"));
                        ShowItemStateOverlay(player, index, basketId, "TAKE.GIVE.ERROR", "1 0.5 0.5 0.2", "1 0.7 0.7 1");
                        return;
                    }

                    ProcessTake(player, data["data"] as Dictionary<string, object>);
                    ShowNotify(player, _(player, "TAKE.GIVE.SUCCESS"));
                    ShowItemStateOverlay(player, index, basketId, "TAKE.GIVE.SUCCESS", "0.5 1 0.5 0.2", "0.7 1 0.7 1", false);

                    if (playerBaskets.TryGetValue(player.userID, out var list))
                    {
                        var idx = list.FindIndex(x => int.TryParse(x.ID, out var id) && id == basketId);
                        if (idx >= 0) list.RemoveAt(idx);
                    }
                }, player);
            }, player);
        }

        private void ShowItemStateOverlay(BasePlayer player, int index, int basketId, string textKey, string color, string textColor, bool keepClickable = true)
        {
            var itemRoot = StoreLayer + ".BlockPanel.Content.item." + index;
            var overlay = itemRoot + ".Open";
            CuiHelper.DestroyUi(player, overlay);

            var container = new CuiElementContainer();
            container.Add(new CuiButton
            {
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMax = "0 0" },
                Button =
                {
                    FadeIn = 1f,
                    Color = color,
                    Close = keepClickable ? overlay : string.Empty,
                    Command = keepClickable ? $"UI_ProstojRUST take {index} {basketId}" : string.Empty
                },
                Text =
                {
                    Text = _(player, textKey),
                    Align = TextAnchor.MiddleCenter,
                    Font = "robotocondensed-bold.ttf",
                    Color = textColor,
                    FontSize = 12
                }
            }, itemRoot, overlay, overlay);
            CuiHelper.AddUi(player, container);
        }

        private string FormatLeftTime(double seconds)
        {
            var sec = Math.Max(0, (int)Math.Ceiling(seconds));
            if (sec <= 0) return "0 сек.";
            var ts = TimeSpan.FromSeconds(sec);
            if (ts.TotalDays >= 1) return $"{(int)ts.TotalDays} дн. {ts.Hours} ч.";
            if (ts.TotalHours >= 1) return $"{(int)ts.TotalHours} ч. {ts.Minutes} мин.";
            if (ts.TotalMinutes >= 1) return $"{(int)ts.TotalMinutes} мин. {ts.Seconds} сек.";
            return $"{ts.Seconds} сек.";
        }

        private void InitializeIcon(BasePlayer player)
        {
            if (player == null || !Settings.InterfaceSettings.EnableStoreUI || !Settings.InterfaceSettings.BucketEnable) return;

            CuiHelper.DestroyUi(player, IconLayer);
            var container = new CuiElementContainer();

            if (Settings.InterfaceSettings.BucketURL.Contains("http") && ImageLibrary != null && (bool)ImageLibrary.Call("HasImage", "ProstojRUSTBucket"))
            {
                container.Add(new CuiElement
                {
                    Parent = "Overlay",
                    Name = IconLayer,
                    DestroyUi = IconLayer,
                    Components =
                    {
                        new CuiRawImageComponent { Png = (string) ImageLibrary.Call("GetImage", "ProstojRUSTBucket") },
                        new CuiRectTransformComponent
                        {
                            AnchorMin = Settings.InterfaceSettings.BucketPosition.AnchorMin,
                            AnchorMax = Settings.InterfaceSettings.BucketPosition.AnchorMax,
                            OffsetMin = Settings.InterfaceSettings.BucketPosition.OffsetMin,
                            OffsetMax = Settings.InterfaceSettings.BucketPosition.OffsetMax
                        }
                    }
                });

                container.Add(new CuiButton
                {
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                    Button = { Color = "0 0 0 0", Command = "chat.say /store" },
                    Text = { Text = string.Empty }
                }, IconLayer);
            }
            else
            {
                container.Add(new CuiButton
                {
                    RectTransform =
                    {
                        AnchorMin = Settings.InterfaceSettings.BucketPosition.AnchorMin,
                        AnchorMax = Settings.InterfaceSettings.BucketPosition.AnchorMax,
                        OffsetMin = Settings.InterfaceSettings.BucketPosition.OffsetMin,
                        OffsetMax = Settings.InterfaceSettings.BucketPosition.OffsetMax
                    },
                    Button = { Color = "1 1 1 0.6", Sprite = "assets/icons/open.png", Command = "chat.say /store" },
                    Text = { Text = string.Empty }
                }, "Overlay", IconLayer, IconLayer);
            }

            CuiHelper.AddUi(player, container);
        }
        #endregion

        #region Utils

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
					if (itemInfo.isFullOnly && itemInfo.SubDrop != null && itemInfo.SubDrop.Count > 0)
					{   
					  LogAction(player, $"Выдаём SubDrop вместо основного предмета {itemInfo.ShortName}");

						Item mainWeapon = null;

						// 1️⃣ Выдаём оружие в первый слот пояса
						foreach (var sub in itemInfo.SubDrop)
						{
							if (sub.Type != "item") continue;

							var def = ItemManager.FindItemDefinition(sub.ItemID);
							if (def == null) continue;

							if (def.category == ItemCategory.Weapon && mainWeapon == null)
							{
								var belt = player.inventory.containerBelt;
								var firstSlot = belt.GetSlot(0);

								// Если слот занят — переносим в инвентарь
								if (firstSlot != null)
								{
									LogAction(player, $"Первый слот занят {firstSlot.info.shortname}, перемещаем в инвентарь");
									if (!player.inventory.GiveItem(firstSlot))
									{
										firstSlot.Drop(player.transform.position, Vector3.down * 3);
										LogAction(player, $"Инвентарь переполнен, {firstSlot.info.shortname} выброшен");
									}
								}

								mainWeapon = ItemManager.Create(def, sub.Count);
								if (mainWeapon != null)
								{
									mainWeapon.MoveToContainer(belt, 0);
									LogAction(player, $"Выдано оружие {def.shortname} в первый слот пояса");
								}
							}
						}

						// 2️⃣ Выдаём все остальные предметы
						foreach (var sub in itemInfo.SubDrop)
						{
							if (sub.Type == "command")
							{
								LogAction(player, $"Выполняем команду: {sub.Command}");
								ConsoleSystem.Run(ConsoleSystem.Option.Server, sub.Command.Replace("{playerid}", player.UserIDString));
								continue;
							}

							var def = ItemManager.FindItemDefinition(sub.ItemID);
							if (def == null) continue;

							// Пропускаем уже выданное оружие
							if (def.category == ItemCategory.Weapon && mainWeapon != null && def == mainWeapon.info)
								continue;

							var item = ItemManager.Create(def, sub.Count);
							if (item == null) continue;

							bool given = false;

							// 1️⃣ Модули → в оружие
							if (def.shortname.StartsWith("weapon.mod.") && mainWeapon != null)
							{
								var weaponEntity = mainWeapon.GetHeldEntity() as BaseProjectile;
								if (weaponEntity != null)
								{
									var weaponItem = weaponEntity.GetItem();
									if (weaponItem != null && weaponItem.contents != null)
									{
										item.MoveToContainer(weaponItem.contents);
										LogAction(player, $"Модуль {def.shortname} установлен на {mainWeapon.info.shortname}");
										given = true;
									}
								}
							}

							// 2️⃣ Одежда → на игрока, если слот свободен
							else if (def.category == ItemCategory.Attire)
							{
								var wear = player.inventory.containerWear;

								// Если слот для этой одежды свободен
								if (item.MoveToContainer(wear))
								{
									LogAction(player, $"Одежда {def.shortname} надета на игрока");
									given = true;
								}
								else
								{
									// Слот занят → в инвентарь
									if (!player.inventory.GiveItem(item))
									{
										item.Drop(player.transform.position, Vector3.down * 3);
										LogAction(player, $"Нет места, {def.shortname} выброшен");
									}
									else
									{
										LogAction(player, $"Слот занят, {def.shortname} отправлена в инвентарь");
									}
									given = true;
								}
							}

							// 3️⃣ Остальное → в инвентарь
							if (!given)
							{
								if (!player.inventory.GiveItem(item))
								{
									item.Drop(player.transform.position, Vector3.down * 3);
									LogAction(player, $"Нет места, {def.shortname} выброшен");
								}
								else
								{
									LogAction(player, $"Выдан предмет {def.shortname} в инвентарь");
								}
							}
						}					} else {
						LogAction(player, $"Попытка получения предмета: {itemInfo.ShortName} [{itemInfo.Amount}]");
						var info = ItemManager.FindItemDefinition(itemInfo.ShortName);
						if (info == null) return;

						var item = ItemManager.Create(info, itemInfo.Amount);
						if (!player.inventory.GiveItem(item))
						{
							LogAction(player, $"У игрока не было места для получения предмета, предмет выброшен {itemInfo.ShortName} [{itemInfo.Amount} {player.transform.position}]");

							item.Drop(player.transform.position, Vector3.down * 3);
						}
						else
						{
							LogAction(player, $"Предмет выдан игроку в инвентарь");
						}
					}
                }

                if (itemInfo.IsCommand)
                {
                    LogAction(player, $"Попытка получения команды");

                    string command = itemInfo.Command.Replace("\n", "|").Replace("%steamid%", player.UserIDString, StringComparison.OrdinalIgnoreCase).Replace("%username%", player.displayName, StringComparison.OrdinalIgnoreCase);
                    foreach (var check in command.Split('|'))
                    {
                        LogAction(player, $"Исполнение команды: {check}");
                        Server.Command(check);
                    }

                }

                if (itemInfo.IsBlueprint)
                {
                    LogAction(player, $"Попытка получения рецепта {itemInfo.ShortName}");
                    Item create = ItemManager.CreateByItemID(-996920608);

                    var info = ItemManager.FindItemDefinition(itemInfo.ShortName);
                    create.blueprintTarget = info.itemid;

                    if (!player.inventory.GiveItem(create))
                    {
                        LogAction(player, $"У игрока не было места для получения рецепта, рецепт выброшен {itemInfo.ShortName} [{itemInfo.Amount} {player.transform.position}]");

                        create.Drop(player.transform.position, Vector3.down * 3);
                    }
                    else
                    {
                        LogAction(player, $"Рецепт выдан игроку в инвентарь");
                    }
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
                                            if (!Settings.InterfaceSettings.ExecuteInstantCommandsWhenNotInServer && product.ContainsKey("steam_id"))
                                            {
                                                var steamId = product["steam_id"]?.ToString();
                                                if (!string.IsNullOrEmpty(steamId) && covalence.Players.FindPlayerById(steamId) == null)
                                                    continue;
                                            }

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
            Request($"&method=info&info=true", (code, response) =>
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

        private void LogAction(BasePlayer player, string text, bool printToConsole = false, bool printError = false)
        {
            /*LogToFile($"!global", $"{DateTime.Now.ToShortTimeString()} {(player != null ? "[" + player.userID + "]" : " ")}{text}", this);

            if (printToConsole)
                if (printError)
                    instance.PrintError($"{text}");
                else
                    PrintWarning($"{text}");
            if (player != null)
                LogPlayerAction(player, text);*/
        }
        private void LogPlayerAction(BasePlayer player, string text) => LogToFile($"{player.userID}", $"{DateTime.Now.ToShortTimeString()} {text}", this);
        private static double CurrentTime() => DateTime.UtcNow.Subtract(new DateTime(1970, 1, 1)).TotalSeconds;

        #endregion
    }
}