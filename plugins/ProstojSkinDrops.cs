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
    [Info("ProstojSkinDrops", "Prostoj Team", "1.0.0")]
    [Description("Admin-only SkinDrops checklist and Steam trade-link editor for ProstojMenu")]
    public class ProstojSkinDrops : RustPlugin
    {
        [PluginReference] private Plugin ProstojMenu;

        private const string PreviewSteamId = "76561198394504608";
        private Configuration config;
        private Timer refreshTimer;
        private bool menuTabRegistered;
        private bool serverAvailable;
        private readonly Dictionary<ulong, PlayerState> states = new Dictionary<ulong, PlayerState>();

        private class Configuration
        {
            [JsonProperty("API URL")] public string ApiUrl = "https://api.prostoj.store/v1/rust-menu/skindrops";
            [JsonProperty("Private admin Steam ID")] public string PrivateAdminSteamId = PreviewSteamId;
            [JsonProperty("Refresh seconds")] public float RefreshSeconds = 60f;
        }

        private class PlayerState
        {
            public StatusData Status;
            public string Draft;
            public string Error;
            public bool Loading;
            public bool Saving;
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

        private class StatusData
        {
            [JsonProperty("available")] public bool Available;
            [JsonProperty("eligible")] public bool Eligible;
            [JsonProperty("prefix")] public string Prefix;
            [JsonProperty("server")] public ServerData Server;
            [JsonProperty("user")] public UserData User;
        }

        private class ServerData
        {
            [JsonProperty("name")] public string Name;
            [JsonProperty("skindrops_enabled")] public bool SkinDropsEnabled;
        }

        private class UserData
        {
            [JsonProperty("registered")] public bool Registered;
            [JsonProperty("username")] public string Username;
            [JsonProperty("steam_id")] public string SteamId;
            [JsonProperty("username_completed")] public bool UsernameCompleted;
            [JsonProperty("trade_link")] public string TradeLink;
            [JsonProperty("trade_link_completed")] public bool TradeLinkCompleted;
            [JsonProperty("all_completed")] public bool AllCompleted;
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
            config.RefreshSeconds = Mathf.Clamp(config.RefreshSeconds, 30f, 180f);
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(config, true);

        private void OnServerInitialized()
        {
            RefreshAdmins();
            refreshTimer = timer.Every(config.RefreshSeconds, RefreshAdmins);
        }

        private void Unload()
        {
            refreshTimer?.Destroy();
            UnregisterMenuTab();
            states.Clear();
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (IsAllowedAdmin(player)) timer.Once(3f, () => RequestStatus(player, false));
        }

        private void OnPlayerDisconnected(BasePlayer player, string reason)
        {
            if (player != null) states.Remove(player.userID);
        }

        private void OnPluginLoaded(Plugin plugin)
        {
            if (plugin == null || plugin.Name != "ProstojMenu") return;
            ProstojMenu = plugin;
            if (serverAvailable) RegisterMenuTab();
            RefreshAdmins();
        }

        private void OnPluginUnloaded(Plugin plugin)
        {
            if (plugin == null || plugin.Name != "ProstojMenu") return;
            ProstojMenu = null;
            menuTabRegistered = false;
        }

        private void RefreshAdmins()
        {
            foreach (var player in BasePlayer.activePlayerList.Where(IsAllowedAdmin))
                RequestStatus(player, false);
        }

        private void RegisterMenuTab()
        {
            if (menuTabRegistered || ProstojMenu == null || !serverAvailable) return;
            ProstojMenu.Call("API_RegisterTab", this, "skindrops", "РАЗДАЧА СКИНОВ", "SKIN GIFT", 65);
            menuTabRegistered = true;
            CacheAssets();
        }

        private void UnregisterMenuTab()
        {
            if (!menuTabRegistered) return;
            ProstojMenu?.Call("API_UnregisterTab", this, "skindrops");
            menuTabRegistered = false;
        }

        private object ProstojMenu_CanView(BasePlayer player)
        {
            PlayerState state;
            return IsAllowedAdmin(player)
                && states.TryGetValue(player.userID, out state)
                && state.Status != null
                && state.Status.Available
                && state.Status.Eligible;
        }

        private object ProstojMenu_Render(BasePlayer player, string parent, int page)
        {
            if (!IsAllowedAdmin(player)) return false;
            var state = GetState(player.userID);
            DrawMenu(player, parent, state);
            if (state.Status == null && !state.Loading) RequestStatus(player, true);
            return true;
        }

        [ConsoleCommand("prostojskindrops.ui")]
        private void SkinDropsUiCommand(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (!IsAllowedAdmin(player) || arg.Args == null || arg.Args.Length == 0) return;
            var state = GetState(player.userID);
            switch (arg.Args[0].ToString().ToLowerInvariant())
            {
                case "input":
                    var input = string.Join(" ", arg.Args.Skip(1).Select(value => value.ToString()).ToArray()).Trim();
                    state.Draft = input.Length > 255 ? input.Substring(0, 255) : input;
                    state.Error = null;
                    break;
                case "save":
                    SaveTradeLink(player, state);
                    break;
                case "refresh":
                    RequestStatus(player, true);
                    break;
            }
        }

        private void RequestStatus(BasePlayer player, bool redraw)
        {
            if (player == null || !player.IsConnected || !IsAllowedAdmin(player) || string.IsNullOrEmpty(config.ApiUrl)) return;
            var state = GetState(player.userID);
            if (state.Loading || state.Saving) return;
            state.Loading = true;
            var url = BuildPlayerUrl(player, config.ApiUrl);
            webrequest.Enqueue(url, null, (code, response) =>
            {
                if (player == null) return;
                state.Loading = false;
                ApiEnvelope<StatusData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                {
                    state.Error = ReadError(response, "Не удалось получить условия раздачи.");
                }
                else
                {
                    state.Status = envelope.Data;
                    state.Error = null;
                    if (state.Draft == null) state.Draft = envelope.Data.User?.TradeLink ?? string.Empty;
                    serverAvailable = envelope.Data.Available;
                    if (serverAvailable) RegisterMenuTab(); else UnregisterMenuTab();
                }
                if (redraw && player.IsConnected && IsTabActive(player)) RefreshTab(player);
            }, this, RequestMethod.GET, Headers(false), 12f);
        }

        private void SaveTradeLink(BasePlayer player, PlayerState state)
        {
            if (state.Saving || state.Loading || state.Status == null) return;
            var tradeLink = (state.Draft ?? string.Empty).Trim();
            if (string.IsNullOrEmpty(tradeLink))
            {
                ShowToast(player, "Введите Steam Trade URL", "error");
                return;
            }
            state.Saving = true;
            state.Error = null;
            RefreshTab(player);
            var url = BuildPlayerUrl(player, config.ApiUrl + "/trade-link");
            var body = JsonConvert.SerializeObject(new Dictionary<string, string> { ["trade_link"] = tradeLink });
            webrequest.Enqueue(url, body, (code, response) =>
            {
                state.Saving = false;
                ApiEnvelope<StatusData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                {
                    state.Error = ReadError(response, "Не удалось сохранить трейд-ссылку.");
                    if (player.IsConnected && IsTabActive(player))
                    {
                        RefreshTab(player);
                        ShowToast(player, state.Error, "error");
                    }
                    return;
                }
                state.Status = envelope.Data;
                state.Draft = envelope.Data.User?.TradeLink ?? tradeLink;
                state.Error = null;
                if (player.IsConnected && IsTabActive(player))
                {
                    RefreshTab(player);
                    ShowToast(player, "Трейд-ссылка сохранена", "success");
                }
            }, this, RequestMethod.POST, Headers(true), 12f);
        }

        private void DrawMenu(BasePlayer player, string parent, PlayerState state)
        {
            var ui = new CuiElementContainer();
            var root = parent + ".SkinDrops";
            var theme = ProstojMenu?.Call("API_GetTheme") as Dictionary<string, string>;
            var bg = Theme(theme, "bg_secondary", "0.035 0.043 0.043 0.94");
            var raised = Theme(theme, "bg_raised", "0.060 0.071 0.070 0.96");
            var accent = Theme(theme, "accent_primary", "0.35 0.71 0.52 1");
            var success = Theme(theme, "success", "0.43 0.77 0.57 1");
            var warning = Theme(theme, "warning", "0.79 0.66 0.42 1");
            var danger = Theme(theme, "danger", "0.77 0.40 0.36 1");
            var textMain = Theme(theme, "text_main", "0.92 0.95 0.93 1");
            var textSecondary = Theme(theme, "text_secondary", "0.60 0.63 0.61 1");
            AddPanel(ui, parent, root, "0 0", "1 1", "0.025 0.032 0.035 0.25");

            var status = state.Status;
            if (status == null)
            {
                AddLabel(ui, root, "РАЗДАЧА СКИНОВ", "0.1 0.56", "0.9 0.70", 28, textMain, TextAnchor.MiddleCenter, true);
                AddLabel(ui, root, state.Error ?? "Проверяем условия участия…", "0.1 0.43", "0.9 0.55", 12, state.Error == null ? textSecondary : danger, TextAnchor.MiddleCenter, false);
                CuiHelper.AddUi(player, ui);
                return;
            }

            var user = status.User ?? new UserData();
            var header = root + ".Header";
            AddPanel(ui, root, header, "0.02 0.845", "0.98 0.975", bg);
            AddPanel(ui, header, header + ".Rail", "0 0", "0.004 1", accent);
            AddLabel(ui, header, "РАЗДАЧА СКИНОВ", "0.025 0.43", "0.58 0.91", 22, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, header, "Выполните три условия — и участвуйте в автоматических розыгрышах", "0.026 0.10", "0.68 0.44", 9, textSecondary, TextAnchor.MiddleLeft, false);
            AddPanel(ui, header, header + ".Ready", "0.76 0.22", "0.97 0.78", user.AllCompleted ? "0.14 0.38 0.27 0.90" : "0.34 0.25 0.16 0.90");
            AddLabel(ui, header, user.AllCompleted ? "ВСЁ ГОТОВО" : "НУЖНА НАСТРОЙКА", "0.77 0.22", "0.96 0.78", 10, user.AllCompleted ? success : warning, TextAnchor.MiddleCenter, true);

            var steps = root + ".Steps";
            AddPanel(ui, root, steps, "0.02 0.365", "0.765 0.82", bg);
            var imageId = GetImage(InstructionImageUrl(theme));
            if (!string.IsNullOrEmpty(imageId)) AddPng(ui, steps, imageId, "0.015 0.025", "0.985 0.975", "1 1 1 0.90");
            var done = new[] { user.UsernameCompleted, user.Registered, user.TradeLinkCompleted };
            var titles = new[] { "ДОБАВЬТЕ ПРИПИСКУ", "ОТКРОЙТЕ ПРОФИЛЬ", "УКАЖИТЕ TRADE URL" };
            var notes = new[] { "В нике должна быть «" + Safe(status.Prefix) + "»", "Войдите на сайт через Steam", "Ссылка нужна для отправки приза" };
            for (var i = 0; i < 3; i++)
            {
                var xMin = 0.015f + i * 0.3233f;
                var xMax = xMin + 0.3233f;
                var card = steps + ".Step" + i;
                AddPanel(ui, steps, card, F(xMin, 0.025f), F(xMax, 0.975f), "0 0 0 0");
                AddPanel(ui, card, card + ".Shade", "0.02 0.02", "0.98 0.30", "0.018 0.024 0.024 0.91");
                AddPanel(ui, card, card + ".Status", "0.07 0.82", "0.28 0.95", done[i] ? success : warning);
                AddLabel(ui, card, done[i] ? "ГОТОВО" : "ШАГ " + (i + 1), "0.07 0.82", "0.28 0.95", 8, textMain, TextAnchor.MiddleCenter, true);
                AddLabel(ui, card, titles[i], "0.06 0.16", "0.94 0.29", 10, textMain, TextAnchor.MiddleCenter, true);
                AddLabel(ui, card, notes[i], "0.06 0.04", "0.94 0.16", 8, textSecondary, TextAnchor.MiddleCenter, false);
            }

            var summary = root + ".Summary";
            AddPanel(ui, root, summary, "0.785 0.365", "0.98 0.82", bg);
            AddSteamAvatar(ui, summary, player.UserIDString, "0.33 0.69", "0.67 0.91", "1 1 1 1");
            AddLabel(ui, summary, Short(string.IsNullOrEmpty(user.Username) ? player.displayName : user.Username, 20), "0.08 0.57", "0.92 0.69", 13, textMain, TextAnchor.MiddleCenter, true);
            AddLabel(ui, summary, Safe(status.Prefix), "0.08 0.48", "0.92 0.58", 9, accent, TextAnchor.MiddleCenter, true);
            AddChecklistRow(ui, summary, "0.09 0.34", "0.91 0.45", "ПРИПИСКА В НИКЕ", user.UsernameCompleted, success, warning, textMain);
            AddChecklistRow(ui, summary, "0.09 0.21", "0.91 0.32", "ПРОФИЛЬ STEAM", user.Registered, success, warning, textMain);
            AddChecklistRow(ui, summary, "0.09 0.08", "0.91 0.19", "TRADE URL", user.TradeLinkCompleted, success, warning, textMain);

            var form = root + ".Form";
            AddPanel(ui, root, form, "0.02 0.045", "0.98 0.335", bg);
            AddPanel(ui, form, form + ".Rail", "0 0", "0.004 1", accent);
            AddLabel(ui, form, "STEAM TRADE URL", "0.03 0.68", "0.50 0.88", 11, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, form, "Steam → Инвентарь → Предложения обмена → Кто может отправлять мне предложения?", "0.03 0.51", "0.90 0.70", 9, textSecondary, TextAnchor.MiddleLeft, false);
            var field = form + ".Field";
            AddPanel(ui, form, field, "0.03 0.16", "0.79 0.48", raised);
            AddInput(ui, field, field + ".Input", "0.025 0.08", "0.975 0.92", state.Draft ?? user.TradeLink ?? string.Empty, "prostojskindrops.ui input", 255, 11, textMain);
            AddButton(ui, form, "0.81 0.16", "0.97 0.48", "prostojskindrops.ui save", state.Saving ? "СОХРАНЕНИЕ…" : "СОХРАНИТЬ", state.Saving ? "0.18 0.20 0.19 1" : accent, textMain);
            if (!string.IsNullOrEmpty(state.Error))
                AddLabel(ui, form, Short(state.Error, 110), "0.03 0.01", "0.97 0.14", 8, danger, TextAnchor.MiddleLeft, false);
            CuiHelper.AddUi(player, ui);
        }

        private static void AddChecklistRow(CuiElementContainer ui, string parent, string min, string max, string label, bool done, string success, string warning, string text)
        {
            var row = parent + ".Check." + label.Replace(" ", string.Empty);
            AddPanel(ui, parent, row, min, max, "0.06 0.071 0.070 0.96");
            AddPanel(ui, row, row + ".Dot", "0.06 0.30", "0.12 0.70", done ? success : warning);
            AddLabel(ui, row, label, "0.17 0", "0.75 1", 8, text, TextAnchor.MiddleLeft, true);
            AddLabel(ui, row, done ? "✓" : "—", "0.78 0", "0.93 1", 11, done ? success : warning, TextAnchor.MiddleRight, true);
        }

        private PlayerState GetState(ulong userId)
        {
            PlayerState state;
            if (!states.TryGetValue(userId, out state)) states[userId] = state = new PlayerState();
            return state;
        }

        private bool IsAllowedAdmin(BasePlayer player)
        {
            return player != null && (player.net?.connection?.authLevel > 0 || player.UserIDString == (config.PrivateAdminSteamId ?? PreviewSteamId));
        }

        private string BuildPlayerUrl(BasePlayer player, string endpoint)
        {
            var identity = ProstojMenu?.Call("API_GetServerIdentity") as Dictionary<string, string>;
            var query = "steam_id=" + Uri.EscapeDataString(player.UserIDString) + "&server_admin=1";
            string value;
            if (identity != null && identity.TryGetValue("server_tag", out value) && !string.IsNullOrWhiteSpace(value))
                query += "&server_tag=" + Uri.EscapeDataString(value);
            else
            {
                var ip = identity != null && identity.TryGetValue("server_ip", out value) ? value : (ConVar.Server.ip ?? string.Empty);
                var port = identity != null && identity.TryGetValue("server_port", out value) ? value : ConVar.Server.port.ToString(CultureInfo.InvariantCulture);
                query += "&server_ip=" + Uri.EscapeDataString(ip) + "&server_port=" + Uri.EscapeDataString(port);
            }
            return endpoint + (endpoint.Contains("?") ? "&" : "?") + query;
        }

        private bool TryEnvelope(int code, string response, out ApiEnvelope<StatusData> envelope)
        {
            envelope = null;
            if (code < 200 || code >= 300 || string.IsNullOrWhiteSpace(response)) return false;
            try { envelope = JsonConvert.DeserializeObject<ApiEnvelope<StatusData>>(response); }
            catch { return false; }
            return envelope != null && envelope.Success;
        }

        private static string ReadError(string response, string fallback)
        {
            try
            {
                var envelope = JsonConvert.DeserializeObject<ApiEnvelope<StatusData>>(response);
                return envelope?.Error != null && !string.IsNullOrEmpty(envelope.Error.Message) ? envelope.Error.Message : fallback;
            }
            catch { return fallback; }
        }

        private Dictionary<string, string> Headers(bool json)
        {
            var headers = new Dictionary<string, string> { ["Accept"] = "application/json" };
            if (json) headers["Content-Type"] = "application/json";
            return headers;
        }

        private bool IsTabActive(BasePlayer player)
        {
            var result = ProstojMenu?.Call("API_IsTabActive", player, "skindrops");
            return result is bool && (bool) result;
        }

        private void RefreshTab(BasePlayer player) => ProstojMenu?.Call("API_RefreshTab", player);
        private void ShowToast(BasePlayer player, string message, string kind) => ProstojMenu?.Call("API_ShowToast", player, message, kind);

        private void CacheAssets()
        {
            ProstojMenu?.Call("API_CacheImage", InstructionImageUrl(ProstojMenu?.Call("API_GetTheme") as Dictionary<string, string>));
        }

        private string InstructionImageUrl(Dictionary<string, string> theme)
        {
            string name;
            var domain = theme != null && theme.TryGetValue("name", out name) && name == "moscow77" ? "https://moscow77.store" : "https://prostoj.store";
            return domain + "/images/skindrops/rust-menu-instructions.jpg";
        }

        private string GetImage(string url)
        {
            var id = ProstojMenu?.Call("API_GetImage", url) as string;
            if (string.IsNullOrEmpty(id)) ProstojMenu?.Call("API_CacheImage", url);
            return id;
        }

        private static string Theme(Dictionary<string, string> theme, string key, string fallback)
        {
            string value;
            return theme != null && theme.TryGetValue(key, out value) && !string.IsNullOrEmpty(value) ? value : fallback;
        }

        private static string Safe(string value) => string.IsNullOrWhiteSpace(value) ? string.Empty : value.Replace("<", string.Empty).Replace(">", string.Empty);
        private static string Short(string value, int length) => string.IsNullOrEmpty(value) || value.Length <= length ? value ?? string.Empty : value.Substring(0, Math.Max(1, length - 1)) + "…";
        private static string F(float x, float y) => x.ToString("0.###", CultureInfo.InvariantCulture) + " " + y.ToString("0.###", CultureInfo.InvariantCulture);

        private static void AddPanel(CuiElementContainer ui, string parent, string name, string min, string max, string color)
        {
            ui.Add(new CuiPanel { Image = { Color = color }, RectTransform = { AnchorMin = min, AnchorMax = max } }, parent, name);
        }

        private static void AddLabel(CuiElementContainer ui, string parent, string text, string min, string max, int size, string color, TextAnchor align, bool bold)
        {
            ui.Add(new CuiLabel
            {
                Text = { Text = text ?? string.Empty, FontSize = size, Color = color, Align = align, Font = bold ? "robotocondensed-bold.ttf" : "robotocondensed-regular.ttf" },
                RectTransform = { AnchorMin = min, AnchorMax = max }
            }, parent);
        }

        private static void AddButton(CuiElementContainer ui, string parent, string min, string max, string command, string text, string color, string textColor)
        {
            ui.Add(new CuiButton
            {
                Button = { Color = color, Command = command },
                RectTransform = { AnchorMin = min, AnchorMax = max },
                Text = { Text = text, FontSize = 10, Color = textColor, Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf" }
            }, parent);
        }

        private static void AddInput(CuiElementContainer ui, string parent, string name, string min, string max, string text, string command, int limit, int size, string color)
        {
            ui.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiInputFieldComponent { Text = text ?? string.Empty, Command = command, CharsLimit = limit, FontSize = size, Font = "robotocondensed-regular.ttf", Align = TextAnchor.MiddleLeft, Color = color, NeedsKeyboard = true },
                    new CuiRectTransformComponent { AnchorMin = min, AnchorMax = max }
                }
            });
        }

        private static void AddPng(CuiElementContainer ui, string parent, string png, string min, string max, string color)
        {
            ui.Add(new CuiElement
            {
                Parent = parent,
                Components = { new CuiRawImageComponent { Png = png, Color = color }, new CuiRectTransformComponent { AnchorMin = min, AnchorMax = max } }
            });
        }

        private static void AddSteamAvatar(CuiElementContainer ui, string parent, string steamId, string min, string max, string color)
        {
            ulong parsed;
            if (!ulong.TryParse(steamId, out parsed)) return;
            ui.Add(new CuiElement
            {
                Parent = parent,
                Components = { new CuiRawImageComponent { SteamId = parsed.ToString(CultureInfo.InvariantCulture), Color = color }, new CuiRectTransformComponent { AnchorMin = min, AnchorMax = max } }
            });
        }
    }
}
