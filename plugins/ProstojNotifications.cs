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
    [Info("ProstojNotifications", "Prostoj Team", "1.0.0")]
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
                    if (key == "ban") state.BanDraft = !(state.BanDraft ?? state.Status.Settings?.BanNotify ?? false);
                    state.Error = null;
                    RefreshTab(player);
                    break;
                case "save":
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
                    state.Status = envelope.Data;
                    state.RaidDraft = envelope.Data.Settings?.RaidNotify ?? false;
                    state.BanDraft = envelope.Data.Settings?.BanNotify ?? false;
                    state.Error = null;
                    if (envelope.Data.Available && envelope.Data.Eligible) RegisterMenuTab();
                }
                if (redraw && player.IsConnected && IsTabActive(player)) RefreshTab(player);
            }, this, RequestMethod.GET, Headers(false), 12f);
        }

        private void SaveSettings(BasePlayer player, PlayerState state)
        {
            if (state.Saving || state.Loading || state.Status == null || !state.Status.CanManage) return;
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
                    ShowToast(player, "Настройки уведомлений сохранены", "success");
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
            var dirty = raid != settings.RaidNotify || ban != settings.BanNotify;

            var header = root + ".Header";
            AddPanel(ui, root, header, "0.02 0.845", "0.98 0.975", bg);
            AddPanel(ui, header, header + ".Rail", "0 0", "0.004 1", accent);
            AddLabel(ui, header, "ЦЕНТР УВЕДОМЛЕНИЙ", "0.025 0.43", "0.62 0.91", 22, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, header, "Выберите события — сообщения придут во все подключённые каналы", "0.026 0.10", "0.70 0.44", 9, textSecondary, TextAnchor.MiddleLeft, false);
            AddPanel(ui, header, header + ".Status", "0.76 0.22", "0.97 0.78", status.DeliveryReady ? "0.14 0.38 0.27 0.90" : "0.34 0.25 0.16 0.90");
            AddLabel(ui, header, status.DeliveryReady ? "ДОСТАВКА ГОТОВА" : "КАНАЛЫ НЕ ПРИВЯЗАНЫ", "0.77 0.22", "0.96 0.78", 9, status.DeliveryReady ? success : warning, TextAnchor.MiddleCenter, true);

            var channelPanel = root + ".Channels";
            AddPanel(ui, root, channelPanel, "0.02 0.24", "0.42 0.82", bg);
            AddLabel(ui, channelPanel, "КАНАЛЫ ДОСТАВКИ", "0.045 0.86", "0.75 0.96", 12, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, channelPanel, "Привязка выполняется один раз в профиле сайта", "0.045 0.78", "0.95 0.87", 8, textSecondary, TextAnchor.MiddleLeft, false);
            AddChannelCard(ui, channelPanel, ".Telegram", "0.045 0.44", "0.955 0.74", "TG", "TELEGRAM-БОТ",
                telegram.Connected, telegram.Blocked ? "БОТ ЗАБЛОКИРОВАН" : BotHint(telegram.BotUsername, "ОТКРОЙТЕ ПРОФИЛЬ НА САЙТЕ"),
                raised, success, warning, textMain, textSecondary);
            AddChannelCard(ui, channelPanel, ".Vk", "0.045 0.09", "0.955 0.39", "VK", "БОТ ВКОНТАКТЕ",
                vk.Connected, VkHint(vk.CommunityUrl), raised, success, warning, textMain, textSecondary);

            var eventPanel = root + ".Events";
            AddPanel(ui, root, eventPanel, "0.44 0.24", "0.98 0.82", bg);
            AddLabel(ui, eventPanel, "КАКИЕ СОБЫТИЯ ОТПРАВЛЯТЬ", "0.035 0.86", "0.75 0.96", 12, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, eventPanel, status.DeliveryReady ? "Настройка действует одновременно для Telegram и ВКонтакте" : "Сначала подключите хотя бы один канал", "0.035 0.78", "0.95 0.87", 8, status.DeliveryReady ? textSecondary : warning, TextAnchor.MiddleLeft, false);
            AddEventRow(ui, eventPanel, ".Raid", "0.035 0.45", "0.965 0.74", "R", "ОПОВЕЩЕНИЯ О РЕЙДАХ",
                "Сообщим, когда вашу постройку начнут рейдить", raid, status.CanManage, "prostojnotifications.ui toggle raid",
                raised, accent, textMain, textSecondary);
            AddEventRow(ui, eventPanel, ".Ban", "0.035 0.10", "0.965 0.39", "!", "ОПОВЕЩЕНИЯ О БАНАХ",
                "Сообщим о бане игрока, на которого вы пожаловались", ban, status.CanManage, "prostojnotifications.ui toggle ban",
                raised, accent, textMain, textSecondary);

            var footer = root + ".Footer";
            AddPanel(ui, root, footer, "0.02 0.045", "0.98 0.205", bg);
            AddPanel(ui, footer, footer + ".Rail", "0 0", "0.004 1", status.DeliveryReady ? accent : warning);
            var footerTitle = !status.Registered ? "НУЖЕН ПРОФИЛЬ НА САЙТЕ" : status.DeliveryReady ? "КАНАЛ ДОСТАВКИ ПОДКЛЮЧЁН" : "ПРИВЯЖИТЕ TELEGRAM ИЛИ ВКОНТАКТЕ";
            var footerNote = !status.Registered ? "Войдите на сайт через Steam" : status.DeliveryReady ? DeliveryLabel(telegram.Connected, vk.Connected) : "Профиль → Социальные сети → подключите бота";
            AddLabel(ui, footer, footerTitle, "0.03 0.48", "0.68 0.83", 11, status.DeliveryReady ? textMain : warning, TextAnchor.MiddleLeft, true);
            AddLabel(ui, footer, footerNote, "0.03 0.16", "0.72 0.49", 8, textSecondary, TextAnchor.MiddleLeft, false);
            AddButton(ui, footer, "0.77 0.20", "0.97 0.80", "prostojnotifications.ui save",
                state.Saving ? "СОХРАНЕНИЕ…" : dirty ? "СОХРАНИТЬ" : "СОХРАНЕНО",
                status.CanManage && dirty && !state.Saving ? accent : "0.18 0.20 0.19 1", textMain,
                status.CanManage && dirty && !state.Saving);
            if (!string.IsNullOrEmpty(state.Error))
                AddLabel(ui, footer, Short(state.Error, 100), "0.03 0.01", "0.73 0.18", 8, danger, TextAnchor.MiddleLeft, false);

            CuiHelper.AddUi(player, ui);
        }

        private static void AddChannelCard(CuiElementContainer ui, string parent, string suffix, string min, string max,
            string mark, string title, bool connected, string hint, string raised, string success, string warning,
            string textMain, string textSecondary)
        {
            var card = parent + suffix;
            AddPanel(ui, parent, card, min, max, raised);
            AddPanel(ui, card, card + ".Mark", "0.035 0.20", "0.17 0.80", connected ? success : "0.13 0.15 0.15 1");
            AddLabel(ui, card, mark, "0.035 0.20", "0.17 0.80", 12, textMain, TextAnchor.MiddleCenter, true);
            AddLabel(ui, card, title, "0.21 0.48", "0.72 0.80", 10, textMain, TextAnchor.MiddleLeft, true);
            AddLabel(ui, card, connected ? "ПОДКЛЮЧЁН" : hint, "0.21 0.18", "0.86 0.48", 8, connected ? success : textSecondary, TextAnchor.MiddleLeft, false);
            AddPanel(ui, card, card + ".Dot", "0.90 0.42", "0.93 0.58", connected ? success : warning);
        }

        private static void AddEventRow(CuiElementContainer ui, string parent, string suffix, string min, string max,
            string mark, string title, string note, bool active, bool enabled, string command, string raised,
            string accent, string textMain, string textSecondary)
        {
            var row = parent + suffix;
            AddPanel(ui, parent, row, min, max, raised);
            AddPanel(ui, row, row + ".Mark", "0.03 0.20", "0.14 0.80", active && enabled ? accent : "0.13 0.15 0.15 1");
            AddLabel(ui, row, mark, "0.03 0.20", "0.14 0.80", 13, textMain, TextAnchor.MiddleCenter, true);
            AddLabel(ui, row, title, "0.18 0.51", "0.70 0.80", 10, enabled ? textMain : "0.48 0.50 0.49 1", TextAnchor.MiddleLeft, true);
            AddLabel(ui, row, note, "0.18 0.19", "0.74 0.50", 8, enabled ? textSecondary : "0.40 0.42 0.41 1", TextAnchor.MiddleLeft, false);
            AddSwitch(ui, row, "0.79 0.31", "0.95 0.69", active, enabled, command, accent);
        }

        private static void AddSwitch(CuiElementContainer ui, string parent, string min, string max, bool active, bool enabled, string command, string accent)
        {
            var name = parent + ".Switch";
            AddPanel(ui, parent, name, min, max, enabled ? (active ? accent : "0.16 0.18 0.18 1") : "0.10 0.11 0.11 1");
            AddPanel(ui, name, name + ".Knob", active ? "0.57 0.12" : "0.07 0.12", active ? "0.93 0.88" : "0.43 0.88", enabled ? "0.94 0.95 0.94 1" : "0.38 0.40 0.39 1");
            if (enabled)
            {
                ui.Add(new CuiButton
                {
                    Button = { Color = "0 0 0 0", Command = command },
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                    Text = { Text = string.Empty }
                }, name);
            }
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
