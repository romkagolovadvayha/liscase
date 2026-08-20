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
    [Info("ProstojNotifications", "Prostoj Team", "1.0.4")]
    [Description("Admin-only raid and ban notification settings for ProstojMenu")]
    public class ProstojNotifications : RustPlugin
    {
        [PluginReference] private Plugin ProstojMenu;

        private const string PreviewSteamId = "76561198394504608";
        private Configuration config;
        private Timer refreshTimer;
        private bool menuTabRegistered;
        private readonly Dictionary<ulong, PlayerState> states = new Dictionary<ulong, PlayerState>();

        private class Configuration
        {
            [JsonProperty("API URL")] public string ApiUrl = "https://api.prostoj.store/v1/rust-menu/notifications";
            [JsonProperty("Private admin Steam ID")] public string PrivateAdminSteamId = PreviewSteamId;
            [JsonProperty("Refresh seconds")] public float RefreshSeconds = 60f;
        }

        private class PlayerState
        {
            public StatusData Status;
            public bool? RaidDraft;
            public bool? BanDraft;
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
            [JsonProperty("registered")] public bool Registered;
            [JsonProperty("can_manage")] public bool CanManage;
            [JsonProperty("delivery_ready")] public bool DeliveryReady;
            [JsonProperty("server")] public ServerData Server;
            [JsonProperty("channels")] public ChannelSet Channels;
            [JsonProperty("settings")] public NotificationSettings Settings;
        }

        private class ServerData
        {
            [JsonProperty("name")] public string Name;
        }

        private class ChannelSet
        {
            [JsonProperty("telegram")] public TelegramChannel Telegram;
            [JsonProperty("vk")] public VkChannel Vk;
        }

        private class TelegramChannel
        {
            [JsonProperty("connected")] public bool Connected;
            [JsonProperty("blocked")] public bool Blocked;
            [JsonProperty("bot_username")] public string BotUsername;
        }

        private class VkChannel
        {
            [JsonProperty("connected")] public bool Connected;
            [JsonProperty("community_url")] public string CommunityUrl;
        }

        private class NotificationSettings
        {
            [JsonProperty("raid_notify")] public bool RaidNotify;
            [JsonProperty("ban_notify")] public bool BanNotify;
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
            CacheImages();
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
            CacheImages();
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
            if (menuTabRegistered || ProstojMenu == null) return;
            ProstojMenu.Call("API_RegisterTab", this, "notifications", "УВЕДОМЛЕНИЯ", "ALERTS", 70);
            menuTabRegistered = true;
        }

        private void UnregisterMenuTab()
        {
            if (!menuTabRegistered) return;
            ProstojMenu?.Call("API_UnregisterTab", this, "notifications");
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

        [ConsoleCommand("prostojnotifications.ui")]
        private void NotificationsUiCommand(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (!IsAllowedAdmin(player) || arg.Args == null || arg.Args.Length == 0) return;
            var state = GetState(player.userID);
            if (state.Status == null) return;

            switch (arg.Args[0].ToString().ToLowerInvariant())
            {
                case "toggle":
                    if (!state.Status.CanManage || state.Saving) return;
                    var key = arg.Args.Length > 1 ? arg.Args[1].ToString().ToLowerInvariant() : string.Empty;
                    if (key == "raid") state.RaidDraft = !(state.RaidDraft ?? state.Status.Settings?.RaidNotify ?? false);
                    else if (key == "ban") state.BanDraft = !(state.BanDraft ?? state.Status.Settings?.BanNotify ?? false);
                    else return;
                    state.Error = null;
                    SaveSettings(player, state);
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
            webrequest.Enqueue(BuildPlayerUrl(player, ApiEndpoint()), null, (code, response) =>
            {
                if (player == null) return;
                state.Loading = false;
                ApiEnvelope<StatusData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                {
                    state.Error = ReadError(response, "Не удалось получить настройки уведомлений.");
                }
                else
                {
                    var raidDirty = state.Status != null
                        && (state.RaidDraft ?? state.Status.Settings?.RaidNotify ?? false)
                            != (state.Status.Settings?.RaidNotify ?? false);
                    var banDirty = state.Status != null
                        && (state.BanDraft ?? state.Status.Settings?.BanNotify ?? false)
                            != (state.Status.Settings?.BanNotify ?? false);
                    state.Status = envelope.Data;
                    if (!raidDirty) state.RaidDraft = envelope.Data.Settings?.RaidNotify ?? false;
                    if (!banDirty) state.BanDraft = envelope.Data.Settings?.BanNotify ?? false;
                    state.Error = null;
                    if (envelope.Data.Available && envelope.Data.Eligible) RegisterMenuTab();
                }
                if (redraw && player.IsConnected && IsTabActive(player)) RefreshTab(player);
            }, this, RequestMethod.GET, Headers(false), 12f);
        }

        private void SaveSettings(BasePlayer player, PlayerState state)
        {
            if (state.Saving || state.Loading || state.Status == null || !state.Status.CanManage) return;
            var previousRaid = state.Status.Settings?.RaidNotify ?? false;
            var previousBan = state.Status.Settings?.BanNotify ?? false;
            state.Saving = true;
            state.Error = null;
            RefreshTab(player);
            var body = JsonConvert.SerializeObject(new Dictionary<string, bool>
            {
                ["raid_notify"] = state.RaidDraft ?? false,
                ["ban_notify"] = state.BanDraft ?? false
            });
            webrequest.Enqueue(BuildPlayerUrl(player, ApiEndpoint() + "/settings"), body, (code, response) =>
            {
                state.Saving = false;
                ApiEnvelope<StatusData> envelope;
                if (!TryEnvelope(code, response, out envelope) || envelope.Data == null)
                {
                    state.RaidDraft = previousRaid;
                    state.BanDraft = previousBan;
                    state.Error = ReadError(response, "Не удалось сохранить настройки уведомлений.");
                    if (player.IsConnected && IsTabActive(player))
                    {
                        RefreshTab(player);
                        ShowToast(player, state.Error, "error");
                    }
                    return;
                }
                state.Status = envelope.Data;
                state.RaidDraft = envelope.Data.Settings?.RaidNotify ?? false;
                state.BanDraft = envelope.Data.Settings?.BanNotify ?? false;
                state.Error = null;
                if (player.IsConnected && IsTabActive(player))
                {
                    RefreshTab(player);
                }
            }, this, RequestMethod.POST, Headers(true), 12f);
        }

        private void DrawMenu(BasePlayer player, string parent, PlayerState state)
        {
            var ui = new CuiElementContainer();
            var root = parent + ".Notifications";
            var theme = ProstojMenu?.Call("API_GetTheme") as Dictionary<string, string>;
            var bg = Theme(theme, "bg_secondary", "0.035 0.043 0.043 0.94");
            var raised = Theme(theme, "bg_raised", "0.060 0.071 0.070 0.96");
            var muted = Theme(theme, "bg_tertiary", "0.330 0.314 0.290 1");
            var accent = Theme(theme, "accent_primary", "0.35 0.71 0.52 1");
            var success = Theme(theme, "success", "0.43 0.77 0.57 1");
            var warning = Theme(theme, "warning", "0.79 0.66 0.42 1");
            var danger = Theme(theme, "danger", "0.77 0.40 0.36 1");
            var textMain = Theme(theme, "text_main", "0.92 0.95 0.93 1");
            var textSecondary = Theme(theme, "text_secondary", "0.60 0.63 0.61 1");
            AddPanel(ui, parent, root, "0 0", "1 1", "0 0 0 0");

            var status = state.Status;
            if (status == null)
            {
                AddLabel(ui, root, "УВЕДОМЛЕНИЯ", "0.1 0.56", "0.9 0.70", 28, textMain, TextAnchor.MiddleCenter, true);
                AddLabel(ui, root, state.Error ?? "Проверяем подключённые каналы…", "0.1 0.43", "0.9 0.55", 12, state.Error == null ? textSecondary : danger, TextAnchor.MiddleCenter, false);
                CuiHelper.AddUi(player, ui);
                return;
            }

            var channels = status.Channels ?? new ChannelSet();
            var telegram = channels.Telegram ?? new TelegramChannel();
            var vk = channels.Vk ?? new VkChannel();
            var settings = status.Settings ?? new NotificationSettings();
            var raid = state.RaidDraft ?? settings.RaidNotify;
            var ban = state.BanDraft ?? settings.BanNotify;
            var header = root + ".Header";
            AddPanel(ui, root, header, "0 0.885", "1 1", bg);
            AddLabel(ui, header, "УВЕДОМЛЕНИЯ В TELEGRAM И ВКОНТАКТЕ", "0.022 0.50", "0.62 0.88", 11, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, header, status.DeliveryReady ? "КАНАЛ ДОСТАВКИ ПОДКЛЮЧЁН" : "ПРИВЯЖИТЕ БОТА В ПРОФИЛЕ САЙТА", "0.022 0.14", "0.62 0.52", 8, status.DeliveryReady ? textSecondary : warning, TextAnchor.MiddleLeft, false);
            AddLabel(ui, header, state.Saving ? "СОХРАНЯЕМ…" : "АВТОСОХРАНЕНИЕ", "0.72 0.20", "0.978 0.80", 9,
                state.Saving ? accent : textSecondary, TextAnchor.MiddleRight, true);

            var channelPanel = root + ".Channels";
            AddPanel(ui, root, channelPanel, "0 0.49", "1 0.875", bg);
            AddLabel(ui, channelPanel, "КАНАЛЫ ДОСТАВКИ", "0.022 0.82", "0.75 0.97", 11, textMain, TextAnchor.MiddleLeft, true);
            AddChannelCard(ui, channelPanel, ".Telegram", "0 0.43", "1 0.76", "social-telegram.png", "TELEGRAM-БОТ",
                telegram.Connected, telegram.Blocked ? "БОТ ЗАБЛОКИРОВАН" : BotHint(telegram.BotUsername, "ОТКРОЙТЕ ПРОФИЛЬ НА САЙТЕ"),
                raised, success, warning, textMain, textSecondary);
            AddChannelCard(ui, channelPanel, ".Vk", "0 0.06", "1 0.39", "social-vk.png", "БОТ ВКОНТАКТЕ",
                vk.Connected, VkHint(vk.CommunityUrl), raised, success, warning, textMain, textSecondary);

            var eventPanel = root + ".Events";
            AddPanel(ui, root, eventPanel, "0 0.075", "1 0.48", bg);
            AddLabel(ui, eventPanel, "СОБЫТИЯ", "0.022 0.83", "0.75 0.97", 11, textMain, TextAnchor.MiddleLeft, true);
            AddEventRow(ui, eventPanel, ".Raid", "0 0.44", "1 0.77", "event-raid.png", "ОПОВЕЩЕНИЯ О РЕЙДАХ",
                "Сообщим, когда вашу постройку начнут рейдить", raid, status.CanManage, !state.Saving, "prostojnotifications.ui toggle raid",
                raised, muted, success, textMain, textSecondary);
            AddEventRow(ui, eventPanel, ".Ban", "0 0.07", "1 0.40", "event-ban.png", "ОПОВЕЩЕНИЯ О БАНАХ",
                "Сообщим о бане игрока, на которого вы пожаловались", ban, status.CanManage, !state.Saving, "prostojnotifications.ui toggle ban",
                raised, muted, success, textMain, textSecondary);

            var footer = root + ".Footer";
            AddPanel(ui, root, footer, "0 0.005", "1 0.065", "0 0 0 0");
            var footerTitle = !status.Registered ? "НУЖЕН ПРОФИЛЬ НА САЙТЕ" : status.DeliveryReady ? "КАНАЛ ДОСТАВКИ ПОДКЛЮЧЁН" : "ПРИВЯЖИТЕ TELEGRAM ИЛИ ВКОНТАКТЕ";
            var footerNote = !status.Registered ? "Войдите на сайт через Steam" : status.DeliveryReady ? DeliveryLabel(telegram.Connected, vk.Connected) : "Профиль → Социальные сети → подключите бота";
            AddLabel(ui, footer, string.IsNullOrEmpty(state.Error) ? footerTitle + "  ·  " + footerNote : Short(state.Error, 100),
                "0.022 0", "0.97 1", 8, !string.IsNullOrEmpty(state.Error) ? danger : status.DeliveryReady ? textSecondary : warning,
                TextAnchor.MiddleLeft, false);

            CuiHelper.AddUi(player, ui);
        }

        private void AddChannelCard(CuiElementContainer ui, string parent, string suffix, string min, string max,
            string iconFile, string title, bool connected, string hint, string raised, string success, string warning,
            string textMain, string textSecondary)
        {
            var card = parent + suffix;
            AddPanel(ui, parent, card, min, max, raised);
            AddCachedIcon(ui, card, card + ".Icon", iconFile, "0.052 0.5", 15f, connected ? success : textSecondary);
            AddLabel(ui, card, title, "0.11 0.20", "0.55 0.80", 10, textMain, TextAnchor.MiddleLeft, true);
            AddPanel(ui, card, card + ".State", "0.64 0.20", "0.978 0.80", connected ? "0.330 0.390 0.300 0.85" : "0.330 0.314 0.290 0.85");
            AddLabel(ui, card, connected ? "ПОДКЛЮЧЁН" : hint, "0.66 0.20", "0.955 0.80", 8, connected ? success : textSecondary, TextAnchor.MiddleCenter, false);
        }

        private void AddEventRow(CuiElementContainer ui, string parent, string suffix, string min, string max,
            string iconFile, string title, string note, bool active, bool enabled, bool interactive, string command, string raised,
            string muted, string success, string textMain, string textSecondary)
        {
            var row = parent + suffix;
            AddPanel(ui, parent, row, min, max, raised);
            AddCachedIcon(ui, row, row + ".Icon", iconFile, "0.052 0.5", 15f, enabled ? textMain : textSecondary);
            AddLabel(ui, row, title, "0.11 0.49", "0.69 0.82", 10, enabled ? textMain : "0.48 0.50 0.49 1", TextAnchor.MiddleLeft, true);
            AddLabel(ui, row, note, "0.11 0.16", "0.69 0.49", 8, enabled ? textSecondary : "0.40 0.42 0.41 1", TextAnchor.MiddleLeft, false);
            AddStateButton(ui, row, "0.79 0.26", "0.978 0.74", active, enabled && interactive, command, muted, success, textMain, textSecondary);
        }

        private static void AddStateButton(CuiElementContainer ui, string parent, string min, string max, bool active,
            bool enabled, string command, string muted, string success, string textMain, string textSecondary)
        {
            AddButton(ui, parent, min, max, command, active ? "ВКЛЮЧЕНО" : "ВЫКЛЮЧЕНО",
                enabled ? (active ? success : muted) : muted,
                enabled ? textMain : textSecondary, enabled);
        }

        private void CacheImages()
        {
            if (ProstojMenu == null) return;
            foreach (var file in new[] { "event-raid.png", "event-ban.png", "social-telegram.png", "social-vk.png" })
                ProstojMenu.Call("API_CacheImage", IconUrl(file));

            // A hot reload can happen while the tab is already open. Give the
            // shared downloader time to persist the PNGs, then repaint only
            // players who are still looking at this module.
            timer.Once(2.5f, () =>
            {
                foreach (var player in BasePlayer.activePlayerList.Where(IsAllowedAdmin))
                    if (IsTabActive(player)) RefreshTab(player);
            });
        }

        private string IconUrl(string file)
        {
            var relative = "rust-menu/icons/" + (file ?? string.Empty).TrimStart('/');
            var themed = ProstojMenu?.Call("API_GetImageUrl", relative) as string;
            var url = string.IsNullOrWhiteSpace(themed) ? "https://prostoj.store/images/" + relative : themed;
            return url + (url.Contains("?") ? "&" : "?") + "v=1";
        }

        private string GetImage(string url)
        {
            var png = ProstojMenu?.Call("API_GetImage", url) as string;
            if (string.IsNullOrEmpty(png)) ProstojMenu?.Call("API_CacheImage", url);
            return png;
        }

        private void AddCachedIcon(CuiElementContainer ui, string parent, string name, string file, string anchor,
            float halfSizePixels, string color)
        {
            var png = GetImage(IconUrl(file));
            if (string.IsNullOrEmpty(png)) return;
            var half = halfSizePixels.ToString("0.##", CultureInfo.InvariantCulture);
            ui.Add(new CuiElement
            {
                Name = name,
                Parent = parent,
                Components =
                {
                    new CuiRawImageComponent { Png = png, Color = color },
                    new CuiRectTransformComponent
                    {
                        AnchorMin = anchor,
                        AnchorMax = anchor,
                        OffsetMin = "-" + half + " -" + half,
                        OffsetMax = half + " " + half
                    }
                }
            });
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

        private string ApiEndpoint()
        {
            var identity = ProstojMenu?.Call("API_GetServerIdentity") as Dictionary<string, string>;
            string endpoint;
            return identity != null
                && identity.TryGetValue("notifications_api_url", out endpoint)
                && !string.IsNullOrWhiteSpace(endpoint)
                    ? endpoint.Trim().TrimEnd('/')
                    : config.ApiUrl;
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
            var result = ProstojMenu?.Call("API_IsTabActive", player, "notifications");
            return result is bool && (bool) result;
        }

        private void RefreshTab(BasePlayer player) => ProstojMenu?.Call("API_RefreshTab", player);
        private void ShowToast(BasePlayer player, string message, string kind) => ProstojMenu?.Call("API_ShowToast", player, message, kind);

        private static string Theme(Dictionary<string, string> theme, string key, string fallback)
        {
            string value;
            return theme != null && theme.TryGetValue(key, out value) && !string.IsNullOrEmpty(value) ? value : fallback;
        }

        private static string BotHint(string username, string fallback)
        {
            return string.IsNullOrEmpty(username) ? fallback : "ОТКРОЙТЕ @" + Short(username, 24);
        }

        private static string VkHint(string url)
        {
            if (string.IsNullOrEmpty(url)) return "ОТКРОЙТЕ ПРОФИЛЬ НА САЙТЕ";
            return "НАПИШИТЕ БОТУ СООБЩЕСТВА";
        }

        private static string DeliveryLabel(bool telegram, bool vk)
        {
            if (telegram && vk) return "Сообщения будут приходить в Telegram и ВКонтакте";
            if (telegram) return "Сообщения будут приходить в Telegram";
            if (vk) return "Сообщения будут приходить во ВКонтакте";
            return string.Empty;
        }

        private static string Short(string value, int length) => string.IsNullOrEmpty(value) || value.Length <= length ? value ?? string.Empty : value.Substring(0, Math.Max(1, length - 1)) + "…";

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

        private static void AddButton(CuiElementContainer ui, string parent, string min, string max, string command, string text, string color, string textColor, bool enabled)
        {
            ui.Add(new CuiButton
            {
                Button = { Color = color, Command = enabled ? command : string.Empty },
                RectTransform = { AnchorMin = min, AnchorMax = max },
                Text = { Text = text, FontSize = 10, Color = textColor, Align = TextAnchor.MiddleCenter, Font = "robotocondensed-bold.ttf" }
            }, parent);
        }
    }
}
