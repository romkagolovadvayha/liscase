using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("ProstojShop", "Prostoj Team", "1.0.12")]
    [Description("Admin preview of the Prostoj store inside ProstojMenu")]
    public class ProstojShop : RustPlugin
    {
        [PluginReference] private Plugin ProstojMenu;
        [PluginReference] private Plugin ProstojRUST;

        private const string PreviewSteamId = "76561198394504608";
        private const string TabKey = "shop";
        private const int ProductsPerPage = 16;
        private Configuration config;
        private bool registered;
        private readonly Dictionary<ulong, PlayerState> states = new Dictionary<ulong, PlayerState>();

        private class Configuration
        {
            [JsonProperty("API URL")] public string ApiUrl = "https://api.prostoj.store/v1/rust-menu/shop";
            [JsonProperty("Private admin Steam ID")] public string PrivateAdminSteamId = PreviewSteamId;
            [JsonProperty("Balance poll seconds")] public float BalancePollSeconds = 5f;
        }

        private class PlayerState
        {
            public ShopData Shop;
            public int CategoryId;
            public int CategoryOffset;
            public int Page = 1;
            public bool Loading;
            public bool CatalogPrefetching;
            public int RequestVersion;
            public int BusyDropId;
            public int PurchasedDropId;
            public int PurchaseFeedbackVersion;
            public string Error;
            public bool TopupOpen;
            public int TopupAmount = 500;
            public bool TopupLoading;
            public bool BalancePolling;
            public TopupData Topup;
            public bool TopupSuccess;
            public Timer BalanceTimer;
            public Timer PurchaseFeedbackTimer;
        }

        private class ApiEnvelope<T>
        {
            [JsonProperty("success")] public bool Success;
            [JsonProperty("data")] public T Data;
            [JsonProperty("error")] public ApiError Error;
        }

        private class ApiError
        {
            [JsonProperty("message")] public string Message;
        }

        private class ShopData
        {
            [JsonProperty("available")] public bool Available;
            [JsonProperty("eligible")] public bool Eligible;
            [JsonProperty("balance")] public int Balance;
            [JsonProperty("currency")] public string Currency;
            [JsonProperty("categories")] public List<CategoryData> Categories = new List<CategoryData>();
            [JsonProperty("products")] public List<ProductData> Products = new List<ProductData>();
            [JsonProperty("pagination")] public PaginationData Pagination;
            [JsonProperty("catalog_complete")] public bool CatalogComplete;
        }

        private class CategoryData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("name")] public string Name;
            [JsonProperty("tag")] public string Tag;
        }

        private class ProductData
        {
            [JsonProperty("id")] public int Id;
            [JsonProperty("name")] public string Name;
            [JsonProperty("price")] public int Price;
            [JsonProperty("image")] public string Image;
            [JsonProperty("rust_id")] public int RustId;
            [JsonProperty("count")] public int Count;
            [JsonProperty("category_id")] public int CategoryId;
            [JsonProperty("favorite")] public bool Favorite;
            [JsonProperty("popular")] public bool Popular;
            [JsonProperty("popularity")] public int Popularity;
            [JsonProperty("blocked")] public bool Blocked;
            [JsonProperty("blocked_seconds")] public int BlockedSeconds;
            [JsonProperty("can_buy")] public bool CanBuy;
        }

        private class PaginationData
        {
            [JsonProperty("page")] public int Page;
            [JsonProperty("pages")] public int Pages;
            [JsonProperty("total")] public int Total;
        }

        private class FavoriteData
        {
            [JsonProperty("drop_id")] public int DropId;
            [JsonProperty("favorite")] public bool Favorite;
        }

        private class BuyData
        {
            [JsonProperty("drop_id")] public int DropId;
            [JsonProperty("new_balance")] public int NewBalance;
        }

        private class BalanceData
        {
            [JsonProperty("balance")] public int Balance;
        }

        private class TopupData
        {
            [JsonProperty("amount")] public int Amount;
            [JsonProperty("initial_balance")] public int InitialBalance;
            [JsonProperty("expires_at")] public long ExpiresAt;
            [JsonProperty("qr_url")] public string QrUrl;
        }

        protected override void LoadDefaultConfig()
        {
            config = new Configuration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try { config = Config.ReadObject<Configuration>() ?? new Configuration(); }
            catch { config = new Configuration(); PrintWarning("Invalid config was replaced with defaults."); }
            config.ApiUrl = (config.ApiUrl ?? string.Empty).Trim().TrimEnd('/');
            config.BalancePollSeconds = Mathf.Clamp(config.BalancePollSeconds, 3f, 15f);
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(config, true);

        private void OnServerInitialized()
        {
            CacheCategoryIcons();
            RegisterTab();
            foreach (var player in BasePlayer.activePlayerList.Where(IsAllowedAdmin))
                RequestCatalog(player, false);
        }

        private void Unload()
        {
            foreach (var state in states.Values)
            {
                state.BalanceTimer?.Destroy();
                state.PurchaseFeedbackTimer?.Destroy();
            }
            states.Clear();
            if (registered) ProstojMenu?.Call("API_UnregisterTab", this, TabKey);
        }

        private void OnPluginLoaded(Plugin plugin)
        {
            if (plugin == null || plugin.Name != "ProstojMenu") return;
            ProstojMenu = plugin;
            registered = false;
            CacheCategoryIcons();
            RegisterTab();
        }

        private void OnPluginUnloaded(Plugin plugin)
        {
            if (plugin == null || plugin.Name != "ProstojMenu") return;
            ProstojMenu = null;
            registered = false;
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (IsAllowedAdmin(player)) timer.Once(3f, () => RequestCatalog(player, false));
        }

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            if (player == null) return;
            PlayerState state;
            if (states.TryGetValue(player.userID, out state)) state.BalanceTimer?.Destroy();
            states.Remove(player.userID);
        }

        private void RegisterTab()
        {
            if (registered || ProstojMenu == null) return;
            var result = ProstojMenu.Call("API_RegisterTab", this, TabKey, "МАГАЗИН", "SHOP", 5);
            registered = result is bool && (bool) result;
        }

        private object ProstojMenu_CanView(BasePlayer player) => IsAllowedAdmin(player);

        private object ProstojMenu_Render(BasePlayer player, string parent, int page)
        {
            if (!IsAllowedAdmin(player)) return false;
            var state = State(player.userID);
            if (state.TopupOpen && TopupExpired(state))
            {
                state.Error = "Срок действия QR-кода истёк. Создайте новый.";
                state.Topup = null;
                StopBalancePoll(state);
            }
            Draw(player, parent, state);
            if (state.Shop == null && !state.Loading) RequestCatalog(player, false);
            if (state.TopupOpen && state.Topup != null && !state.TopupSuccess && state.BalanceTimer == null && !TopupExpired(state))
                StartBalancePoll(player, state);
            return true;
        }

        [ConsoleCommand("prostojshop.ui")]
        private void ShopCommand(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (!IsAllowedAdmin(player) || arg.Args == null || arg.Args.Length == 0) return;
            var state = State(player.userID);
            var action = arg.Args[0].ToString().ToLowerInvariant();
            int value;
            switch (action)
            {
                case "category":
                    if (!TryInt(arg, 1, out value)) return;
                    state.CategoryId = Math.Max(0, value);
                    state.Page = 1;
                    ClearPurchaseFeedback(state);
                    if (state.Shop != null && state.Shop.CatalogComplete) RefreshCatalogView(player, state);
                    else RequestCatalog(player, true);
                    break;
                case "catprev":
                    state.CategoryOffset = Math.Max(0, state.CategoryOffset - 4);
                    Refresh(player);
                    break;
                case "catnext":
                    state.CategoryOffset += 4;
                    Refresh(player);
                    break;
                case "page":
                    if (!TryInt(arg, 1, out value)) return;
                    state.Page = Math.Max(1, value);
                    ClearPurchaseFeedback(state);
                    if (state.Shop != null && state.Shop.CatalogComplete) RefreshCatalogView(player, state);
                    else RequestCatalog(player, true);
                    break;
                case "favorite":
                    if (TryInt(arg, 1, out value)) ToggleFavorite(player, state, value);
                    break;
                case "buy":
                    if (TryInt(arg, 1, out value)) Buy(player, state, value);
                    break;
                case "refresh":
                    RequestCatalog(player, true);
                    break;
                case "topup":
                    state.TopupOpen = true;
                    state.TopupSuccess = false;
                    state.Topup = null;
                    StopBalancePoll(state);
                    Refresh(player);
                    break;
                case "amount":
                    if (TryInt(arg, 1, out value)) state.TopupAmount = Mathf.Clamp(value, 50, 50000);
                    Refresh(player);
                    break;
                case "preset":
                    if (TryInt(arg, 1, out value)) state.TopupAmount = Mathf.Clamp(value, 50, 50000);
                    Refresh(player);
                    break;
                case "qr":
                    CreateQr(player, state);
                    break;
                case "topupclose":
                    state.TopupOpen = false;
                    state.Topup = null;
                    state.TopupSuccess = false;
                    StopBalancePoll(state);
                    Refresh(player);
                    break;
            }
        }

        private void RequestCatalog(BasePlayer player, bool redraw)
        {
            if (player == null || !player.IsConnected || !IsAllowedAdmin(player)) return;
            var state = State(player.userID);
            if (state.Loading) return;
            state.Loading = true;
            state.Error = null;
            var hadCatalog = state.Shop != null;
            var version = ++state.RequestVersion;
            if (redraw) Refresh(player);
            var url = PlayerUrl(player, Endpoint()) + "&all=1&category_id=" + state.CategoryId + "&page=" + state.Page + "&page_size=20";
            webrequest.Enqueue(url, null, (code, response) =>
            {
                if (player == null || version != state.RequestVersion) return;
                state.Loading = false;
                ApiEnvelope<ShopData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                    state.Error = ErrorText(response, "Магазин временно недоступен.");
                else
                {
                    var previousBalance = state.Shop != null ? state.Shop.Balance : -1;
                    state.Page = envelope.Data.CatalogComplete
                        ? Math.Max(1, state.Page)
                        : envelope.Data.Pagination != null ? Math.Max(1, envelope.Data.Pagination.Page) : Math.Max(1, state.Page);
                    state.Error = null;
                    state.Shop = envelope.Data;
                    var remotePages = envelope.Data.Pagination != null ? Math.Max(1, envelope.Data.Pagination.Pages) : 1;
                    if (!envelope.Data.CatalogComplete && state.CategoryId == 0 && remotePages > 1)
                    {
                        // Old API compatibility: switch to local navigation immediately,
                        // then hydrate the remaining catalogue once in the background.
                        envelope.Data.CatalogComplete = true;
                        state.CatalogPrefetching = true;
                        timer.Once(0f, () => PrefetchCatalogPage(player, state, version, 2, remotePages, 0));
                    }
                    var imagesPending = CacheImages(VisibleProducts(state));
                    if (previousBalance != envelope.Data.Balance)
                        ProstojMenu?.Call("API_UpdateBalance", player, envelope.Data.Balance);
                    if (imagesPending)
                        timer.Once(1.2f, () => { if (player != null && player.IsConnected && IsActive(player)) Refresh(player); });
                }
                if ((redraw || !hadCatalog) && IsActive(player)) Refresh(player);
            }, this, RequestMethod.GET, Headers(false), 12f);
        }

        private void PrefetchCatalogPage(BasePlayer player, PlayerState state, int version, int page, int pages, int retry)
        {
            if (player == null || !player.IsConnected || state == null || version != state.RequestVersion || state.Shop == null)
                return;
            if (page > pages)
            {
                state.CatalogPrefetching = false;
                state.Error = null;
                if (IsActive(player)) RefreshCatalogView(player, state);
                return;
            }

            var url = PlayerUrl(player, Endpoint()) + "&category_id=0&page=" + page + "&page_size=20";
            webrequest.Enqueue(url, null, (code, response) =>
            {
                if (player == null || !player.IsConnected || version != state.RequestVersion || state.Shop == null)
                    return;
                ApiEnvelope<ShopData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                {
                    if (retry < 2)
                    {
                        timer.Once(0.5f * (retry + 1), () => PrefetchCatalogPage(player, state, version, page, pages, retry + 1));
                        return;
                    }
                    state.CatalogPrefetching = false;
                    state.Error = "Не удалось полностью подготовить каталог. Нажмите обновить позже.";
                    if (IsActive(player)) Refresh(player);
                    return;
                }

                var existing = new HashSet<int>(state.Shop.Products.Where(product => product != null).Select(product => product.Id));
                foreach (var product in envelope.Data.Products ?? new List<ProductData>())
                    if (product != null && existing.Add(product.Id)) state.Shop.Products.Add(product);
                if ((state.Shop.Categories == null || state.Shop.Categories.Count == 0) && envelope.Data.Categories != null)
                    state.Shop.Categories = envelope.Data.Categories;
                PrefetchCatalogPage(player, state, version, page + 1, pages, 0);
            }, this, RequestMethod.GET, Headers(false), 12f);
        }

        private void ToggleFavorite(BasePlayer player, PlayerState state, int dropId)
        {
            if (state.BusyDropId != 0 || state.Shop == null) return;
            var product = state.Shop.Products.FirstOrDefault(x => x.Id == dropId);
            if (product == null) return;
            product.Favorite = !product.Favorite;
            var expected = product.Favorite;
            state.BusyDropId = dropId;
            Refresh(player);
            var body = JsonConvert.SerializeObject(new { drop_id = dropId, favorite = expected });
            webrequest.Enqueue(PlayerUrl(player, Endpoint() + "/favorite"), body, (code, response) =>
            {
                state.BusyDropId = 0;
                ApiEnvelope<FavoriteData> envelope;
                if (!TryEnvelope(code, response, out envelope))
                {
                    product.Favorite = !expected;
                    state.Error = ErrorText(response, "Не удалось изменить избранное.");
                    if (player != null && player.IsConnected && IsActive(player)) Refresh(player);
                    return;
                }
                if (expected) state.Page = 1;
                if (player != null && player.IsConnected && IsActive(player))
                {
                    if (state.Shop.CatalogComplete) RefreshCatalogView(player, state);
                    else RequestCatalog(player, true);
                }
            }, this, RequestMethod.POST, Headers(true), 12f);
        }

        private void Buy(BasePlayer player, PlayerState state, int dropId)
        {
            if (state.BusyDropId != 0 || state.Shop == null) return;
            var product = state.Shop.Products.FirstOrDefault(x => x.Id == dropId);
            if (product == null || product.Blocked || product.Price > state.Shop.Balance) return;
            state.BusyDropId = dropId;
            ClearPurchaseFeedback(state);
            state.Error = null;
            Refresh(player);
            var body = JsonConvert.SerializeObject(new { drop_id = dropId });
            webrequest.Enqueue(PlayerUrl(player, Endpoint() + "/buy"), body, (code, response) =>
            {
                state.BusyDropId = 0;
                ApiEnvelope<BuyData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                    state.Error = ErrorText(response, "Покупка не завершена.");
                else
                {
                    state.Shop.Balance = envelope.Data.NewBalance;
                    ShowPurchaseFeedback(player, state, dropId);
                    ProstojMenu?.Call("API_UpdateBalance", player, envelope.Data.NewBalance);
                    ProstojRUST?.Call("API_RefreshBasket", player);
                }
                if (player != null && player.IsConnected && IsActive(player)) Refresh(player);
            }, this, RequestMethod.POST, Headers(true), 15f);
        }

        private void CreateQr(BasePlayer player, PlayerState state)
        {
            if (state.TopupLoading || state.TopupAmount < 50 || state.TopupAmount > 50000) return;
            state.TopupLoading = true;
            state.Error = null;
            Refresh(player);
            var body = JsonConvert.SerializeObject(new { amount = state.TopupAmount });
            webrequest.Enqueue(PlayerUrl(player, Endpoint() + "/topup"), body, (code, response) =>
            {
                state.TopupLoading = false;
                ApiEnvelope<TopupData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                    state.Error = ErrorText(response, "Не удалось создать QR-код.");
                else
                {
                    state.Topup = envelope.Data;
                    state.TopupSuccess = false;
                    ProstojMenu?.Call("API_CacheImage", envelope.Data.QrUrl);
                    StartBalancePoll(player, state);
                    timer.Once(1.2f, () => { if (player != null && player.IsConnected && state.TopupOpen) Refresh(player); });
                }
                if (player != null && player.IsConnected && IsActive(player)) Refresh(player);
            }, this, RequestMethod.POST, Headers(true), 15f);
        }

        private void StartBalancePoll(BasePlayer player, PlayerState state)
        {
            StopBalancePoll(state);
            state.BalanceTimer = timer.Every(config.BalancePollSeconds, () => PollBalance(player, state));
        }

        private void StopBalancePoll(PlayerState state)
        {
            state.BalanceTimer?.Destroy();
            state.BalanceTimer = null;
            state.BalancePolling = false;
        }

        private void PollBalance(BasePlayer player, PlayerState state)
        {
            if (player == null || !player.IsConnected || !IsActive(player) || !state.TopupOpen || state.Topup == null || state.TopupSuccess)
            {
                StopBalancePoll(state);
                return;
            }
            if (TopupExpired(state))
            {
                state.Error = "Срок действия QR-кода истёк. Создайте новый.";
                state.Topup = null;
                StopBalancePoll(state);
                Refresh(player);
                return;
            }
            if (state.BalancePolling) return;
            state.BalancePolling = true;
            var topup = state.Topup;
            webrequest.Enqueue(PlayerUrl(player, Endpoint() + "/balance"), null, (code, response) =>
            {
                if (!ReferenceEquals(state.Topup, topup)) return;
                state.BalancePolling = false;
                ApiEnvelope<BalanceData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null) return;
                var balanceChanged = state.Shop == null || state.Shop.Balance != envelope.Data.Balance;
                if (state.Shop != null) state.Shop.Balance = envelope.Data.Balance;
                if (balanceChanged) ProstojMenu?.Call("API_UpdateBalance", player, envelope.Data.Balance);
                if (envelope.Data.Balance > topup.InitialBalance)
                {
                    state.TopupSuccess = true;
                    StopBalancePoll(state);
                    if (player != null && player.IsConnected && IsActive(player)) Refresh(player);
                    timer.Once(2f, () => RequestCatalog(player, state.TopupOpen));
                }
            }, this, RequestMethod.GET, Headers(false), 10f);
        }

        private void Draw(BasePlayer player, string parent, PlayerState state)
        {
            var ui = new CuiElementContainer();
            var root = parent + ".Shop";
            var theme = ProstojMenu?.Call("API_GetTheme") as Dictionary<string, string>;
            var bg = Theme(theme, "bg_secondary", "0.035 0.043 0.043 0.96");
            var raised = Theme(theme, "bg_raised", "0.060 0.071 0.070 0.98");
            var accent = Theme(theme, "accent_primary", "0.35 0.71 0.52 1");
            var warm = Theme(theme, "accent", "0.93 0.46 0.28 1");
            var success = Theme(theme, "success", "0.43 0.77 0.57 1");
            var danger = Theme(theme, "danger", "0.77 0.40 0.36 1");
            var text = Theme(theme, "text_main", "0.92 0.95 0.93 1");
            var muted = Theme(theme, "text_secondary", "0.60 0.63 0.61 1");
            // The menu shell already owns the frosted surface. Keeping module
            // roots transparent prevents stacked alpha from making tabs darker.
            AddPanel(ui, parent, root, "0 0", "1 1", "0 0 0 0");

            if (state.Shop == null)
            {
                AddLabel(ui, root, "МАГАЗИН", "0.1 0.56", "0.9 0.70", 28, text, TextAnchor.MiddleCenter, true);
                AddLabel(ui, root, state.Error ?? "Загружаем популярные товары…", "0.1 0.42", "0.9 0.55", 12, state.Error == null ? muted : danger, TextAnchor.MiddleCenter, false);
                if (state.Error != null) AddButton(ui, root, "0.40 0.30", "0.60 0.38", "prostojshop.ui refresh", "ОБНОВИТЬ", accent, text, true);
                CuiHelper.AddUi(player, ui);
                return;
            }

            var header = root + ".Header";
            AddPanel(ui, root, header, "0 0.90", "1 1", bg);
            AddLabel(ui, header, "ПОПУЛЯРНОЕ СНАЧАЛА", "0.018 0.16", "0.50 0.84", 10, text, TextAnchor.MiddleLeft, true);
            AddLabel(ui, header, "БАЛАНС  " + Number(state.Shop.Balance) + " ₽", "0.58 0.18", "0.79 0.82", 13, text, TextAnchor.MiddleRight, true);
            AddButton(ui, header, "0.81 0.18", "0.985 0.82", "prostojshop.ui topup", "ПОПОЛНИТЬ", raised, text, true);

            DrawCategories(ui, root, state, bg, raised, accent, text, muted);
            DrawProducts(ui, player, root, state, bg, raised, warm, accent, success, danger, text, muted);
            DrawPagination(ui, root, state, bg, accent, text, muted);
            if (!string.IsNullOrEmpty(state.Error))
                AddLabel(ui, root, Short(state.Error, 95), "0.23 0.025", "0.78 0.075", 9, danger, TextAnchor.MiddleCenter, false);
            if (state.Loading)
                AddLabel(ui, root, "ОБНОВЛЯЕМ…", "0.82 0.025", "0.98 0.075", 8, muted, TextAnchor.MiddleRight, true);
            if (state.TopupOpen) DrawTopup(ui, player, root, state, bg, raised, accent, success, danger, text, muted);
            CuiHelper.AddUi(player, ui);
        }

        private void DrawCategories(CuiElementContainer ui, string root, PlayerState state, string bg, string raised, string accent, string text, string muted)
        {
            var panel = root + ".Categories";
            AddPanel(ui, root, panel, "0 0.08", "0.205 0.895", bg);
            var cats = state.Shop.Categories ?? new List<CategoryData>();
            state.CategoryOffset = Mathf.Clamp(state.CategoryOffset, 0, Math.Max(0, cats.Count - 9));
            var visible = cats.Skip(state.CategoryOffset).Take(9).ToList();
            AddLabel(ui, panel, "КАТЕГОРИИ", "0.07 0.92", "0.93 0.985", 10, text, TextAnchor.MiddleLeft, true);
            for (var i = 0; i < visible.Count; i++)
            {
                var cat = visible[i];
                var yMax = 0.905f - i * 0.089f;
                var yMin = yMax - 0.082f;
                var selected = cat.Id == state.CategoryId;
                var row = panel + ".Category." + cat.Id;
                AddPanel(ui, panel, row, "0 " + A(yMin), "1 " + A(yMax), selected ? raised : "0 0 0 0");
                AddCategoryIcon(ui, row, cat, "0.13 0.5", 15f, selected ? text : muted);
                AddLabel(ui, row, Short((cat.Name ?? "КАТЕГОРИЯ").ToUpperInvariant(), 18),
                    "0.255 0", "0.96 1", 10, selected ? text : muted, TextAnchor.MiddleLeft, true);
                // Keep the hit target above every decorative child so the
                // complete category row is clickable, including icon and text.
                AddButton(ui, row, "0 0", "1 1", "prostojshop.ui category " + cat.Id,
                    string.Empty, "0 0 0 0", selected ? text : muted, !state.Loading, 10);
            }
            AddButton(ui, panel, "0.07 0.015", "0.43 0.07", "prostojshop.ui catprev", "‹", raised, text, state.CategoryOffset > 0);
            AddButton(ui, panel, "0.57 0.015", "0.93 0.07", "prostojshop.ui catnext", "›", raised, text, state.CategoryOffset + 9 < cats.Count);
        }

        private void DrawProducts(CuiElementContainer ui, BasePlayer player, string root, PlayerState state, string bg, string raised, string warm, string accent, string success, string danger, string text, string muted)
        {
            var products = VisibleProducts(state);
            if (products.Count == 0)
            {
                AddPanel(ui, root, root + ".Empty", "0.21 0.08", "1 0.895", bg);
                AddLabel(ui, root + ".Empty", "В ЭТОЙ КАТЕГОРИИ НЕТ ТОВАРОВ", "0.1 0.46", "0.9 0.60", 18, text, TextAnchor.MiddleCenter, true);
                AddLabel(ui, root + ".Empty", "Выберите другую категорию", "0.1 0.35", "0.9 0.47", 10, muted, TextAnchor.MiddleCenter, false);
                return;
            }
            var hitIds = new HashSet<int>((state.Shop.Products ?? new List<ProductData>())
                .Where(product => product != null && product.Popularity > 0)
                .OrderByDescending(product => product.Popularity)
                .ThenBy(product => product.Id)
                .Take(6)
                .Select(product => product.Id));
            const float gridTop = 0.895f;
            const float gridBottom = 0.08f;
            const float rowGap = 0.003f;
            const float cardHeight = (gridTop - gridBottom - rowGap * 3f) / 4f;
            for (var i = 0; i < products.Count && i < ProductsPerPage; i++)
            {
                var product = products[i];
                var col = i % 4;
                var row = i / 4;
                var x1 = 0.21f + col * 0.1975f;
                var x2 = x1 + 0.1945f;
                var y2 = gridTop - row * (cardHeight + rowGap);
                var y1 = y2 - cardHeight;
                var card = root + ".Product." + product.Id;
                AddPanel(ui, root, card, A(x1) + " " + A(y1), A(x2) + " " + A(y2), bg);
                AddPanel(ui, card, card + ".ImageBg", "0.05 0.38", "0.95 0.94", raised);
                AddProductImage(ui, card + ".ImageBg", card + ".Image", product);
                if (hitIds.Contains(product.Id)) AddLabel(ui, card, "ХИТ", "0.07 0.82", "0.30 0.94", 7, warm, TextAnchor.MiddleLeft, true);
                AddButton(ui, card, "0.78 0.80", "0.95 0.94", "prostojshop.ui favorite " + product.Id, product.Favorite ? "★" : "☆", "0 0 0 0.28", product.Favorite ? warm : text, state.BusyDropId == 0, 10);
                if (product.Count > 1)
                {
                    var quantity = card + ".Quantity";
                    AddPanel(ui, card, quantity, "0.77 0.405", "0.92 0.53", warm);
                    AddLabel(ui, quantity, product.Count.ToString(CultureInfo.InvariantCulture), "0 0", "1 1", 8, text, TextAnchor.MiddleCenter, true);
                }
                AddLabel(ui, card, Short(product.Name, 24), "0.055 0.25", "0.95 0.34", 9, text, TextAnchor.MiddleLeft, true);
                AddLabel(ui, card, Number(product.Price) + " ₽", "0.055 0.045", "0.48 0.18", 9, text, TextAnchor.MiddleLeft, true);
                var busy = state.BusyDropId == product.Id;
                var bought = state.PurchasedDropId == product.Id;
                var lacking = product.Price > state.Shop.Balance;
                var label = bought ? "КУПЛЕНО" : busy ? "ПОКУПКА…" : product.Blocked ? "ВАЙП-БЛОК" : lacking ? "НЕ ХВАТАЕТ" : "КУПИТЬ";
                var color = bought ? success : product.Blocked || lacking ? "0.20 0.22 0.22 0.95" : accent;
                AddButton(ui, card, "0.50 0.045", "0.95 0.18", "prostojshop.ui buy " + product.Id, label, color, text,
                    !bought && !busy && !product.Blocked && !lacking && state.BusyDropId == 0, 8, bought);
            }
        }

        private void DrawPagination(CuiElementContainer ui, string root, PlayerState state, string bg, string accent, string text, string muted)
        {
            if (state.Shop != null && !state.Shop.CatalogComplete && state.Shop.Pagination != null)
            {
                var remote = state.Shop.Pagination;
                state.Page = Mathf.Clamp(remote.Page, 1, Math.Max(1, remote.Pages));
                AddButton(ui, root, "0.435 0.015", "0.48 0.07", "prostojshop.ui page " + (state.Page - 1), "‹", bg, text, state.Page > 1 && !state.Loading);
                AddLabel(ui, root, state.Page + " / " + Math.Max(1, remote.Pages), "0.485 0.015", "0.585 0.07", 10, muted, TextAnchor.MiddleCenter, true);
                AddButton(ui, root, "0.59 0.015", "0.635 0.07", "prostojshop.ui page " + (state.Page + 1), "›", bg, text, state.Page < remote.Pages && !state.Loading);
                return;
            }
            var total = FilteredProducts(state).Count;
            var pages = Math.Max(1, (total + ProductsPerPage - 1) / ProductsPerPage);
            state.Page = Mathf.Clamp(state.Page, 1, pages);
            AddButton(ui, root, "0.435 0.015", "0.48 0.07", "prostojshop.ui page " + (state.Page - 1), "‹", bg, text, state.Page > 1);
            AddLabel(ui, root, state.Page + " / " + pages, "0.485 0.015", "0.585 0.07", 10, muted, TextAnchor.MiddleCenter, true);
            AddButton(ui, root, "0.59 0.015", "0.635 0.07", "prostojshop.ui page " + (state.Page + 1), "›", bg, text, state.Page < pages);
        }

        private static List<ProductData> FilteredProducts(PlayerState state)
        {
            if (state == null || state.Shop == null || state.Shop.Products == null)
                return new List<ProductData>();
            return state.Shop.Products
                .Where(product => product != null && (state.CategoryId <= 0 || product.CategoryId == state.CategoryId))
                .OrderByDescending(product => product.Favorite)
                .ThenByDescending(product => product.Popularity)
                .ThenBy(product => product.Id)
                .ToList();
        }

        private static List<ProductData> VisibleProducts(PlayerState state)
        {
            var products = FilteredProducts(state);
            if (state != null && state.Shop != null && !state.Shop.CatalogComplete)
                return products.Take(ProductsPerPage).ToList();
            var pages = Math.Max(1, (products.Count + ProductsPerPage - 1) / ProductsPerPage);
            state.Page = Mathf.Clamp(state.Page, 1, pages);
            return products.Skip((state.Page - 1) * ProductsPerPage).Take(ProductsPerPage).ToList();
        }

        private void DrawTopup(CuiElementContainer ui, BasePlayer player, string root, PlayerState state, string bg, string raised, string accent, string success, string danger, string text, string muted)
        {
            var shade = root + ".TopupShade";
            AddPanel(ui, root, shade, "0 0", "1 1", "0 0 0 0.78");
            var modal = shade + ".Modal";
            AddPanel(ui, shade, modal, "0.285 0.16", "0.715 0.84", bg);
            AddLabel(ui, modal, "ПОПОЛНЕНИЕ БАЛАНСА", "0.07 0.87", "0.82 0.96", 18, text, TextAnchor.MiddleLeft, true);
            AddButton(ui, modal, "0.89 0.88", "0.96 0.95", "prostojshop.ui topupclose", "×", "0 0 0 0", text, true);

            if (state.TopupSuccess)
            {
                AddLabel(ui, modal, "✓", "0.1 0.53", "0.9 0.76", 52, success, TextAnchor.MiddleCenter, true);
                AddLabel(ui, modal, "БАЛАНС УСПЕШНО ЗАЧИСЛЕН", "0.08 0.40", "0.92 0.55", 18, text, TextAnchor.MiddleCenter, true);
                AddLabel(ui, modal, "Новый баланс: " + Number(state.Shop != null ? state.Shop.Balance : 0) + " ₽", "0.08 0.31", "0.92 0.42", 11, muted, TextAnchor.MiddleCenter, false);
                AddButton(ui, modal, "0.32 0.13", "0.68 0.23", "prostojshop.ui topupclose", "ГОТОВО", success, "0.05 0.12 0.08 1", true);
                return;
            }

            if (state.Topup != null)
            {
                AddPanel(ui, modal, modal + ".QrBg", "0.29 0.34", "0.71 0.77", "1 1 1 1");
                var png = ProstojMenu?.Call("API_GetImage", state.Topup.QrUrl) as string;
                if (!string.IsNullOrEmpty(png)) AddRawImage(ui, modal + ".QrBg", "0.04 0.04", "0.96 0.96", png, "1 1 1 1");
                else AddLabel(ui, modal + ".QrBg", "QR", "0 0", "1 1", 34, "0.12 0.14 0.14 1", TextAnchor.MiddleCenter, true);
                AddLabel(ui, modal, "ОТСКАНИРУЙТЕ КАМЕРОЙ ТЕЛЕФОНА", "0.08 0.25", "0.92 0.34", 11, text, TextAnchor.MiddleCenter, true);
                AddLabel(ui, modal, Number(state.Topup.Amount) + " ₽ · ожидаем оплату", "0.08 0.17", "0.92 0.26", 10, accent, TextAnchor.MiddleCenter, false);
                AddLabel(ui, modal, "На телефоне откроется защищённая страница со способами оплаты", "0.08 0.08", "0.92 0.17", 8, muted, TextAnchor.MiddleCenter, false);
                return;
            }

            AddLabel(ui, modal, "Укажите сумму — QR-код будет действовать 15 минут", "0.07 0.75", "0.93 0.84", 10, muted, TextAnchor.MiddleLeft, false);
            AddPanel(ui, modal, modal + ".Amount", "0.07 0.58", "0.93 0.72", raised);
            AddInput(ui, modal + ".Amount", state.TopupAmount.ToString(CultureInfo.InvariantCulture), "prostojshop.ui amount", 6, 20, text);
            var presets = new[] { 100, 500, 1000, 2000 };
            for (var i = 0; i < presets.Length; i++)
            {
                var x = 0.07f + i * 0.222f;
                AddButton(ui, modal, A(x) + " 0.43", A(x + 0.202f) + " 0.53", "prostojshop.ui preset " + presets[i], presets[i] + " ₽",
                    state.TopupAmount == presets[i] ? accent : raised, text, !state.TopupLoading);
            }
            AddButton(ui, modal, "0.20 0.23", "0.80 0.35", "prostojshop.ui qr", state.TopupLoading ? "СОЗДАЁМ QR…" : "ПОКАЗАТЬ QR-КОД", accent, text, !state.TopupLoading);
            AddLabel(ui, modal, "После оплаты баланс обновится автоматически", "0.08 0.11", "0.92 0.20", 9, muted, TextAnchor.MiddleCenter, false);
        }

        private void AddProductImage(CuiElementContainer ui, string parent, string name, ProductData product)
        {
            if (product.RustId != 0 && ItemManager.FindItemDefinition(product.RustId) != null)
            {
                AddSquareItemImage(ui, parent, name, product.RustId, 50f);
                return;
            }
            var image = ProstojMenu?.Call("API_GetImageInfo", product.Image) as Dictionary<string, string>;
            string png;
            string widthValue;
            string heightValue;
            int width;
            int height;
            if (image != null
                && image.TryGetValue("png", out png)
                && image.TryGetValue("width", out widthValue)
                && image.TryGetValue("height", out heightValue)
                && int.TryParse(widthValue, NumberStyles.Integer, CultureInfo.InvariantCulture, out width)
                && int.TryParse(heightValue, NumberStyles.Integer, CultureInfo.InvariantCulture, out height))
                AddAspectFittedRawImage(ui, parent, name, png, width, height, 50f);
        }

        private bool CacheImages(IEnumerable<ProductData> products)
        {
            if (products == null) return false;
            var pending = false;
            foreach (var product in products)
            {
                if (product.RustId != 0 || string.IsNullOrWhiteSpace(product.Image)) continue;
                var png = ProstojMenu?.Call("API_GetImage", product.Image) as string;
                if (string.IsNullOrEmpty(png)) pending = true;
            }
            return pending;
        }

        private void AddCategoryIcon(CuiElementContainer ui, string parent, CategoryData category,
            string center, float halfSizePixels, string color)
        {
            var png = GetCachedImage(CategoryIconUrl(category));
            if (string.IsNullOrEmpty(png)) return;
            var half = Mathf.Max(1f, halfSizePixels).ToString("0.###", CultureInfo.InvariantCulture);
            ui.Add(new CuiElement
            {
                Name = parent + ".Icon",
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = color },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = center,
                        AnchorMax = center,
                        OffsetMin = "-" + half + " -" + half,
                        OffsetMax = half + " " + half
                    }
                }
            });
        }

        private static string CategoryIconKind(CategoryData category)
        {
            if (category == null) return "other";
            if (category.Id == 0) return "top";

            // IDs are database keys, not category meanings. The API tag is the
            // stable contract shared by every server and site theme.
            var tag = (category.Tag ?? string.Empty).Trim().ToLowerInvariant();
            switch (tag)
            {
                case "popular": return "top";
                case "weapon":
                case "weapons": return "weapon";
                case "ammunition":
                case "ammo": return "ammunition";
                case "resources":
                case "resource": return "resources";
                case "tools":
                case "tool": return "tools";
                case "food": return "food";
                case "constructions":
                case "construction":
                case "building": return "building";
                case "compochi":
                case "components":
                case "component": return "components";
                case "electro":
                case "electric": return "electric";
                case "cloth":
                case "clothing": return "clothing";
                case "medicines":
                case "medicine": return "medicine";
                case "farmer":
                case "farming": return "farming";
                case "sets":
                case "set":
                case "kits":
                case "kit": return "kit";
                case "other": return "other";
            }

            var value = (tag + " " + (category.Name ?? string.Empty)).ToLowerInvariant();
            if (value.Contains("popular") || value.Contains("популяр")) return "top";
            if (value.Contains("ammo") || value.Contains("боеприпас")) return "ammunition";
            if (value.Contains("weapon") || value.Contains("gun") || value.Contains("оруж")) return "weapon";
            if (value.Contains("resource") || value.Contains("ресурс")) return "resources";
            if (value.Contains("tool") || value.Contains("инстру")) return "tools";
            if (value.Contains("food") || value.Contains("еда")) return "food";
            if (value.Contains("build") || value.Contains("construct") || value.Contains("конструк")) return "building";
            if (value.Contains("component") || value.Contains("compochi") || value.Contains("компо")) return "components";
            if (value.Contains("electric") || value.Contains("electro") || value.Contains("элект")) return "electric";
            if (value.Contains("cloth") || value.Contains("одеж") || value.Contains("ткан")) return "clothing";
            if (value.Contains("medicine") || value.Contains("мед")) return "medicine";
            if (value.Contains("farm") || value.Contains("ферм")) return "farming";
            if (value.Contains("kit") || value.Contains("set") || value.Contains("набор")) return "kit";
            return "other";
        }

        private string CategoryIconUrl(CategoryData category)
        {
            var relative = "rust-menu/icons/shop-category-" + CategoryIconKind(category) + ".png";
            var themed = ProstojMenu?.Call("API_GetImageUrl", relative) as string;
            return string.IsNullOrWhiteSpace(themed) ? "https://prostoj.store/images/" + relative : themed;
        }

        private string GetCachedImage(string url)
        {
            var png = ProstojMenu?.Call("API_GetImage", url) as string;
            if (string.IsNullOrEmpty(png)) ProstojMenu?.Call("API_CacheImage", url);
            return png;
        }

        private void CacheCategoryIcons()
        {
            if (ProstojMenu == null) return;
            foreach (var kind in new[]
            {
                "top", "weapon", "ammunition", "resources", "tools", "food", "building", "components",
                "electric", "clothing", "medicine", "farming", "kit", "other"
            })
            {
                var relative = "rust-menu/icons/shop-category-" + kind + ".png";
                var themed = ProstojMenu.Call("API_GetImageUrl", relative) as string;
                ProstojMenu.Call("API_CacheImage", string.IsNullOrWhiteSpace(themed)
                    ? "https://prostoj.store/images/" + relative
                    : themed);
            }

            timer.Once(2.5f, () =>
            {
                foreach (var player in BasePlayer.activePlayerList.Where(IsAllowedAdmin))
                    if (IsActive(player)) Refresh(player);
            });
        }

        private static void AddCategoryGlyph(CuiElementContainer ui, string root, string icon, string color)
        {
            if (icon == "top")
            {
                AddIconPart(ui, root, "Base", "0.15 0.18", "0.85 0.27", color);
                AddIconPart(ui, root, "Second", "0.18 0.27", "0.40 0.55", color);
                AddIconPart(ui, root, "First", "0.41 0.27", "0.63 0.82", color);
                AddIconPart(ui, root, "Third", "0.64 0.27", "0.82 0.44", color);
                return;
            }
            if (icon == "weapon")
            {
                AddIconPart(ui, root, "Barrel", "0.45 0.52", "0.86 0.61", color);
                AddIconPart(ui, root, "Body", "0.28 0.43", "0.58 0.68", color);
                AddIconPart(ui, root, "Stock", "0.13 0.38", "0.32 0.58", color);
                AddIconPart(ui, root, "Grip", "0.42 0.22", "0.53 0.45", color);
                return;
            }
            if (icon == "resources")
            {
                AddIconPart(ui, root, "StackA", "0.18 0.22", "0.72 0.35", color);
                AddIconPart(ui, root, "StackB", "0.25 0.43", "0.79 0.56", color);
                AddIconPart(ui, root, "StackC", "0.32 0.64", "0.86 0.77", color);
                return;
            }
            if (icon == "tools")
            {
                AddIconPart(ui, root, "Handle", "0.45 0.18", "0.56 0.64", color);
                AddIconPart(ui, root, "Head", "0.22 0.61", "0.78 0.77", color);
                AddIconPart(ui, root, "Neck", "0.37 0.54", "0.64 0.68", color);
                return;
            }
            if (icon == "food")
            {
                AddIconPart(ui, root, "BowlTop", "0.18 0.52", "0.82 0.62", color);
                AddIconPart(ui, root, "BowlLeft", "0.24 0.30", "0.34 0.55", color);
                AddIconPart(ui, root, "BowlRight", "0.66 0.30", "0.76 0.55", color);
                AddIconPart(ui, root, "BowlBottom", "0.31 0.24", "0.69 0.34", color);
                AddIconPart(ui, root, "Spoon", "0.70 0.58", "0.78 0.84", color);
                return;
            }
            if (icon == "building")
            {
                AddIconPart(ui, root, "Wall", "0.24 0.20", "0.76 0.58", color);
                AddIconPart(ui, root, "RoofA", "0.18 0.57", "0.49 0.70", color);
                AddIconPart(ui, root, "RoofB", "0.49 0.57", "0.82 0.70", color);
                AddIconPart(ui, root, "Chimney", "0.64 0.68", "0.73 0.82", color);
                return;
            }
            if (icon == "components")
            {
                AddIconPart(ui, root, "Core", "0.38 0.38", "0.62 0.62", color);
                AddIconPart(ui, root, "Horizontal", "0.16 0.45", "0.84 0.55", color);
                AddIconPart(ui, root, "Vertical", "0.45 0.16", "0.55 0.84", color);
                AddIconPart(ui, root, "CornerA", "0.25 0.25", "0.36 0.36", color);
                AddIconPart(ui, root, "CornerB", "0.64 0.64", "0.75 0.75", color);
                return;
            }
            if (icon == "electric")
            {
                AddIconPart(ui, root, "BoltTop", "0.43 0.58", "0.70 0.82", color);
                AddIconPart(ui, root, "BoltMid", "0.30 0.43", "0.60 0.61", color);
                AddIconPart(ui, root, "BoltBottom", "0.31 0.18", "0.50 0.46", color);
                return;
            }
            if (icon == "clothing")
            {
                AddIconPart(ui, root, "Body", "0.34 0.20", "0.66 0.70", color);
                AddIconPart(ui, root, "SleeveL", "0.16 0.48", "0.35 0.72", color);
                AddIconPart(ui, root, "SleeveR", "0.65 0.48", "0.84 0.72", color);
                return;
            }
            if (icon == "medicine")
            {
                AddIconPart(ui, root, "Vertical", "0.42 0.18", "0.58 0.82", color);
                AddIconPart(ui, root, "Horizontal", "0.18 0.42", "0.82 0.58", color);
                return;
            }
            if (icon == "farming")
            {
                AddIconPart(ui, root, "Stem", "0.46 0.20", "0.55 0.72", color);
                AddIconPart(ui, root, "LeafL", "0.22 0.48", "0.48 0.64", color);
                AddIconPart(ui, root, "LeafR", "0.53 0.60", "0.79 0.76", color);
                AddIconPart(ui, root, "Ground", "0.20 0.18", "0.80 0.28", color);
                return;
            }
            if (icon == "kit")
            {
                AddIconPart(ui, root, "Box", "0.20 0.22", "0.80 0.64", color);
                AddIconPart(ui, root, "Lid", "0.16 0.61", "0.84 0.72", color);
                AddIconPart(ui, root, "HandleL", "0.36 0.71", "0.44 0.82", color);
                AddIconPart(ui, root, "HandleR", "0.56 0.71", "0.64 0.82", color);
                AddIconPart(ui, root, "HandleTop", "0.36 0.78", "0.64 0.86", color);
                return;
            }

            AddIconPart(ui, root, "A", "0.20 0.56", "0.43 0.80", color);
            AddIconPart(ui, root, "B", "0.57 0.56", "0.80 0.80", color);
            AddIconPart(ui, root, "C", "0.20 0.20", "0.43 0.44", color);
            AddIconPart(ui, root, "D", "0.57 0.20", "0.80 0.44", color);
        }

        private static void AddIconPart(CuiElementContainer ui, string parent, string suffix, string min, string max, string color)
        {
            AddPanel(ui, parent, parent + "." + suffix, min, max, color);
        }

        private void RefreshCatalogView(BasePlayer player, PlayerState state)
        {
            var imagesPending = CacheImages(VisibleProducts(state));
            Refresh(player);
            if (imagesPending)
                timer.Once(1.2f, () => { if (player != null && player.IsConnected && IsActive(player)) Refresh(player); });
        }

        private void ShowPurchaseFeedback(BasePlayer player, PlayerState state, int dropId)
        {
            state.PurchaseFeedbackTimer?.Destroy();
            state.PurchasedDropId = dropId;
            var version = ++state.PurchaseFeedbackVersion;
            state.PurchaseFeedbackTimer = timer.Once(2f, () =>
            {
                if (state.PurchaseFeedbackVersion != version) return;
                state.PurchasedDropId = 0;
                state.PurchaseFeedbackTimer = null;
                if (player != null && player.IsConnected && IsActive(player)) Refresh(player);
            });
        }

        private static void ClearPurchaseFeedback(PlayerState state)
        {
            state.PurchaseFeedbackTimer?.Destroy();
            state.PurchaseFeedbackTimer = null;
            state.PurchasedDropId = 0;
            state.PurchaseFeedbackVersion++;
        }

        private static bool TopupExpired(PlayerState state) => state.Topup != null && state.Topup.ExpiresAt > 0 && DateTimeOffset.UtcNow.ToUnixTimeSeconds() >= state.Topup.ExpiresAt;

        private PlayerState State(ulong userId)
        {
            PlayerState state;
            if (!states.TryGetValue(userId, out state)) states[userId] = state = new PlayerState();
            return state;
        }

        private bool IsAllowedAdmin(BasePlayer player) => player != null && (player.net?.connection?.authLevel > 0 || player.UserIDString == (config.PrivateAdminSteamId ?? PreviewSteamId));
        private bool IsActive(BasePlayer player) { var value = ProstojMenu?.Call("API_IsTabActive", player, TabKey); return value is bool && (bool)value; }
        private void Refresh(BasePlayer player) { if (player != null && player.IsConnected && IsActive(player)) ProstojMenu?.Call("API_RefreshTab", player); }

        private string Endpoint()
        {
            var identity = ProstojMenu?.Call("API_GetServerIdentity") as Dictionary<string, string>;
            string value;
            return identity != null && identity.TryGetValue("shop_api_url", out value) && !string.IsNullOrWhiteSpace(value) ? value.TrimEnd('/') : config.ApiUrl;
        }

        private string PlayerUrl(BasePlayer player, string endpoint)
        {
            var identity = ProstojMenu?.Call("API_GetServerIdentity") as Dictionary<string, string>;
            var query = "steam_id=" + Uri.EscapeDataString(player.UserIDString) + "&server_admin=1";
            string value;
            if (identity != null && identity.TryGetValue("server_tag", out value) && !string.IsNullOrWhiteSpace(value)) query += "&server_tag=" + Uri.EscapeDataString(value);
            else
            {
                var ip = identity != null && identity.TryGetValue("server_ip", out value) ? value : (ConVar.Server.ip ?? string.Empty);
                var port = identity != null && identity.TryGetValue("server_port", out value) ? value : ConVar.Server.port.ToString(CultureInfo.InvariantCulture);
                query += "&server_ip=" + Uri.EscapeDataString(ip) + "&server_port=" + Uri.EscapeDataString(port);
            }
            return endpoint + (endpoint.Contains("?") ? "&" : "?") + query;
        }

        private static Dictionary<string, string> Headers(bool json)
        {
            var headers = new Dictionary<string, string> { ["Accept"] = "application/json" };
            if (json) headers["Content-Type"] = "application/json";
            return headers;
        }

        private static bool TryEnvelope<T>(int code, string response, out ApiEnvelope<T> envelope)
        {
            envelope = null;
            if (code < 200 || code >= 300 || string.IsNullOrWhiteSpace(response)) return false;
            try { envelope = JsonConvert.DeserializeObject<ApiEnvelope<T>>(response); }
            catch { return false; }
            return envelope != null && envelope.Success;
        }

        private static string ErrorText(string response, string fallback)
        {
            try
            {
                var value = JsonConvert.DeserializeObject<ApiEnvelope<object>>(response);
                return value?.Error != null && !string.IsNullOrWhiteSpace(value.Error.Message) ? value.Error.Message : fallback;
            }
            catch { return fallback; }
        }

        private static bool TryInt(ConsoleSystem.Arg arg, int index, out int value)
        {
            value = 0;
            return arg.Args != null && arg.Args.Length > index && int.TryParse(arg.Args[index].ToString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out value);
        }

        private static string Theme(Dictionary<string, string> theme, string key, string fallback) { string value; return theme != null && theme.TryGetValue(key, out value) && !string.IsNullOrEmpty(value) ? value : fallback; }
        private static string Number(int value) => value.ToString("N0", CultureInfo.GetCultureInfo("ru-RU"));
        private static string Short(string value, int length) => string.IsNullOrEmpty(value) || value.Length <= length ? value ?? string.Empty : value.Substring(0, Math.Max(1, length - 1)) + "…";
        private static string A(float value) => value.ToString("0.###", CultureInfo.InvariantCulture);
        private static string F(float x, float y) => A(x) + " " + A(y);

        private static void AddPanel(CuiElementContainer ui, string parent, string name, string min, string max, string color) => ui.Add(new CuiPanel { Image = { Color = color }, RectTransform = { AnchorMin = min, AnchorMax = max } }, parent, name);
        private static void AddLabel(CuiElementContainer ui, string parent, string text, string min, string max, int size, string color, TextAnchor align, bool bold) => ui.Add(new CuiLabel { Text = { Text = text ?? string.Empty, FontSize = size, Color = color, Align = align, Font = bold ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf" }, RectTransform = { AnchorMin = min, AnchorMax = max } }, parent);
        private static void AddButton(CuiElementContainer ui, string parent, string min, string max, string command, string text, string color, string textColor, bool enabled, int fontSize = 10, bool keepDisabledColor = false) => ui.Add(new CuiButton { Button = { Color = enabled || keepDisabledColor ? color : "0.09 0.10 0.10 0.72", Command = enabled ? command : string.Empty }, Text = { Text = text ?? string.Empty, FontSize = fontSize, Color = enabled || keepDisabledColor ? textColor : "0.42 0.44 0.43 1", Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf" }, RectTransform = { AnchorMin = min, AnchorMax = max } }, parent);
        private static void AddRawImage(CuiElementContainer ui, string parent, string min, string max, string png, string color) => ui.Add(new CuiElement { Parent = parent, Components = { new CuiRawImageComponent { Png = png, Color = color }, new CuiRectTransformComponent { AnchorMin = min, AnchorMax = max } } });
        private static void AddSquareItemImage(CuiElementContainer ui, string parent, string name, int itemId, float size)
        {
            var half = size * 0.5f;
            ui.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiImageComponent { ItemId = itemId },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5",
                        AnchorMax = "0.5 0.5",
                        OffsetMin = F(-half, -half),
                        OffsetMax = F(half, half)
                    }
                }
            });
        }
        private static void AddAspectFittedRawImage(CuiElementContainer ui, string parent, string name, string png, int sourceWidth, int sourceHeight, float maxSize)
        {
            if (string.IsNullOrEmpty(png) || sourceWidth <= 0 || sourceHeight <= 0) return;
            var ratio = sourceWidth / (float) sourceHeight;
            var width = ratio >= 1f ? maxSize : maxSize * ratio;
            var height = ratio >= 1f ? maxSize / ratio : maxSize;
            ui.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = "1 1 1 1" },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5",
                        AnchorMax = "0.5 0.5",
                        OffsetMin = F(width * -0.5f, height * -0.5f),
                        OffsetMax = F(width * 0.5f, height * 0.5f)
                    }
                }
            });
        }
        private static void AddInput(CuiElementContainer ui, string parent, string text, string command, int limit, int size, string color) => ui.Add(new CuiElement { Parent = parent, Components = { new CuiInputFieldComponent { Text = text, Command = command, CharsLimit = limit, FontSize = size, Font = "robotocondensed-bold.ttf", Align = TextAnchor.MiddleCenter, Color = color, NeedsKeyboard = true }, new CuiRectTransformComponent { AnchorMin = "0.03 0.05", AnchorMax = "0.97 0.95" } } });
    }
}
