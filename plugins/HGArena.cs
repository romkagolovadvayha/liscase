// Requires: CopyPaste
using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using UnityEngine;
using System.Reflection;

namespace Oxide.Plugins
{ 
    [Info("HGArena", "prostoj.ai", "1.6.1")]
    [Description("Голодные Игры: клетки CopyPaste (HG_Cell), Default-зона для не-участников, видимый купол, радиация снаружи, автоприсоединение, вайп инвентаря, гарантированный PVP, аирдроп в зоне, трупов нет.")]
    public class HGArena : RustPlugin
    {
        #region Plugins

        [PluginReference] private Plugin CopyPaste;
		[PluginReference] private Plugin SignArtist; // для рисования на табличке
		[PluginReference] private Plugin CustomCrateSpawner;
        // Медиа воспроизведение через стандартные возможности Rust

        #endregion

        #region Config

        private ConfigData Cfg;

        private class ConfigData
        {
            [JsonProperty("Префикс в чате")] public string ChatPrefix = "<color=#DFF008>[Голодные Игры]</color>";

            [JsonProperty("Автозапуск каждые N секунд (0 = выкл)")] public float AutoStartSeconds = 3600f;
            [JsonProperty("Лобби: длительность (сек)")] public int LobbySeconds = 60;
            [JsonProperty("Минимум игроков для старта")] public int MinPlayers = 2;

            [JsonProperty("Автоприсоединять всех онлайн при запуске лобби")] public bool AutoEnrollOnlineOnLobby = true;
            [JsonProperty("Автоприсоединять новых игроков во время лобби")] public bool AutoEnrollJoinersDuringLobby = true;
            [JsonProperty("Режим запуска: auto (все игроки), manual (выбранные), invite (по приглашениям)")] public string StartMode = "auto";
            [JsonProperty("Разрешить участие админов в ивенте")] public bool AllowAdminParticipation = false;

            [JsonProperty("Команда старта (чат)")] public string CmdStart = "starthg";
            [JsonProperty("Команда окончания (чат)")] public string CmdEnd = "endhg";
            [JsonProperty("Команда выхода игрока")] public string CmdLeave = "leave";

            [JsonProperty("Копипаст-схема клетки (oxide/data/copypaste/<имя>.json)")] public string CopyNameCell = "HG_Cell";
            [JsonProperty("Копипаст-схема общей зоны для не-участников")] public string CopyNameDefault = "HG_Default";
            [JsonProperty("Радиус окружности для расстановки клеток (м)")] public float CellsCircleRadius = 80f;
            [JsonProperty("Разворачивать клетки лицом к центру")] public bool CellsFaceToCenter = true;
 
            // Купол / радиация
            [JsonProperty("Купол: задержка появления (сек)")] public float CircleDelayAfterStart = 15f;
            [JsonProperty("Купол: стартовый радиус (м)")] public float CircleStartRadius = 220f;
            [JsonProperty("Купол: минимальный радиус (м)")] public float CircleMinRadius = 30f;
            [JsonProperty("Купол: шаг сжатия (м)")] public float CircleShrinkStep = 0.8f;
            [JsonProperty("Купол: период сжатия (сек)")] public float CircleShrinkPeriod = 1f;
            [JsonProperty("Купол: слоёв сферы (визуальная плотность)")] public int DomeDarkness = 8;
            [JsonProperty("Купол: рисовать видимую сферу")] public bool VisibleShrinkingDome = true;

            [JsonProperty("Радиация: период тика (сек) вне купола")] public float RadTick = 2f;
            [JsonProperty("Радиация: величина урона за тик вне купола (HP)")] public float RadAmount = 4f;

            [JsonProperty("Максимальная длительность матча (сек)")] public int MaxMatchSeconds = 1800;
            [JsonProperty("Анонсировать смерти")] public bool AnnounceDeaths = true;

            [JsonProperty("Показывать обратный отсчет лобби и HUD матча")] public bool ShowUI = true;

            [JsonProperty("Всегда держать еду/воду на максимуме у участников")] public bool KeepFoodWaterFull = true;
            [JsonProperty("Период обновления еды/воды (сек)")] public float KeepFoodWaterPeriod = 2f;

            // Аирдропы
            [JsonProperty("Аирдроп: включен во время матча")] public bool AirDropEnabled = true;
            [JsonProperty("Аирдроп: период (сек)")] public float AirDropPeriod = 120f;
            [JsonProperty("Аирдроп: высота сброса (м) (до ускорения)")] public float AirDropHeight = 300f;
            [JsonProperty("Аирдроп: ускорение падения (во сколько раз быстрее)")] public float AirDropFallFaster = 20f;
            [JsonProperty("Аирдроп: радиус разброса цели в пределах круга (0..1)")] public float AirDropTargetFactor = 0.6f;

            [JsonProperty("Аирдроп: лут (shortname -> количество)")]
            public Dictionary<string, int> AirDropLoot = new Dictionary<string, int>
            {
                { "metal.shield", 1 },
                { "minicrossbow", 1 },
                { "bow.compound", 1 }
            };

            [JsonProperty("Принудительный телепорт не-участников в HG_Default каждые (сек)")] public float EnforceDefaultPeriod = 3f;
			
            [JsonProperty("Купол: длительность фазы сжатия (сек)")] public int CircleShrinkBurstSeconds = 180;
			[JsonProperty("Купол: длительность паузы между сжатиями (сек)")] public int CircleShrinkPauseSeconds = 60;
			[JsonProperty("Купол: сообщения о фазах сжатия в чат")] public bool CircleShrinkAnnouncements = true;

            // Медиа настройки
            [JsonProperty("Медиа: включить воспроизведение")] public bool MediaEnabled = true;
            [JsonProperty("Медиа: метод воспроизведения (console/browser/chat)")] public string MediaMethod = "browser";
            [JsonProperty("Медиа: приветственный файл (URL)")] public string WelcomeMediaUrl = "http://storage.prostoj.store/HGArena/welcome.mp4";
            [JsonProperty("Медиа: длительность приветствия (сек)")] public float WelcomeMediaDuration = 12f;
            [JsonProperty("Медиа: файл окончания матча (URL)")] public string EndMediaUrl = "http://storage.prostoj.store/HGArena/end.mp4";
            [JsonProperty("Медиа: длительность окончания (сек)")] public float EndMediaDuration = 10f;
        } 
 
        protected override void LoadDefaultConfig() => Cfg = new ConfigData();

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try { Cfg = Config.ReadObject<ConfigData>(); if (Cfg == null) throw new Exception(); }
            catch { PrintWarning("Конфиг поврежден — загружены значения по умолчанию."); LoadDefaultConfig(); }
            SaveConfig();
        }

        protected override void SaveConfig() => Config.WriteObject(Cfg, true);

        #endregion

        #region Media Methods

        void PlayMediaForPlayer(BasePlayer player, string url, float duration)
        {
            if (!Cfg.MediaEnabled || string.IsNullOrEmpty(url) || player == null || !player.IsConnected)
                return;

            // Выбираем метод воспроизведения
            string method = Cfg.MediaMethod.ToLower();
            if (method == "browser")
            {
                PlayMediaViaBrowser(player, url, duration);
            }
            else if (method == "chat")
            {
                PlayMediaViaChat(player, url, duration);
            }
            else
            {
                PlayMediaViaConsole(player, url, duration);
            }
        }

        void PlayMediaViaConsole(BasePlayer player, string url, float duration)
        {
            try
            {
                // Определяем тип медиа по расширению
                bool isVideo = url.EndsWith(".mp4", StringComparison.OrdinalIgnoreCase);
                bool isAudio = url.EndsWith(".mp3", StringComparison.OrdinalIgnoreCase);

                // Пробуем разные способы воспроизведения
                if (isVideo)
                {
                    // Способы для видео
                    player.SendConsoleCommand($"video.play {url}");
                    player.SendConsoleCommand($"play {url}");
                    player.SendConsoleCommand($"client.connect {url}");
                }
                else if (isAudio)
                {
                    // Способы для аудио
                    player.SendConsoleCommand($"audio.play {url}");
                    player.SendConsoleCommand($"play {url}");
                    player.SendConsoleCommand($"sound.play {url}");
                }
                
                // Пробуем через веб-браузер игрока как fallback
                player.SendConsoleCommand($"global.browse {url}");
                
                // Логируем для отладки
                string mediaType = isVideo ? "видео" : (isAudio ? "аудио" : "медиа");
                Puts($"[HGArena] Воспроизведение {mediaType} (консоль) для {player.displayName}: {url} ({duration}s)");
            }
            catch (Exception ex)
            {
                PrintWarning($"[HGArena] Ошибка воспроизведения медиа через консоль для {player.displayName}: {ex.Message}");
                // Fallback на браузерный метод
                PlayMediaViaBrowser(player, url, duration);
            }
        }

        void PlayMediaForAll(string url, float duration)
        {
            if (!Cfg.MediaEnabled || string.IsNullOrEmpty(url))
                return;

            try
            {
                foreach (var player in BasePlayer.activePlayerList)
                {
                    if (player != null && player.IsConnected)
                    {
                        PlayMediaForPlayer(player, url, duration);
                    }
                }
                
                Puts($"[HGArena] Воспроизведение медиа для всех игроков: {url} ({duration}s)");
            }
            catch (Exception ex)
            {
                PrintWarning($"[HGArena] Ошибка воспроизведения медиа для всех: {ex.Message}");
            }
        }

        void ShowMediaNotification(BasePlayer player, string message, float duration = 5f)
        {
            if (player == null || !player.IsConnected) return;

            // Показываем уведомление игроку
            var container = new CuiElementContainer();
            
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.8" },
                RectTransform = { AnchorMin = "0.3 0.85", AnchorMax = "0.7 0.95" }
            }, "Overlay", "MediaNotification");

            container.Add(new CuiLabel
            {
                Text = { Text = message, FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" }
            }, "MediaNotification");

            CuiHelper.AddUi(player, container);

            // Убираем уведомление через указанное время
            timer.Once(duration, () => CuiHelper.DestroyUi(player, "MediaNotification"));
        }

        void PlayMediaViaBrowser(BasePlayer player, string url, float duration)
        {
            if (player == null || !player.IsConnected) return;

            try
            {
                // Простой способ - открываем URL напрямую в браузере
                player.SendConsoleCommand($"global.browse {url}");
                
                // Альтернативный способ через CUI с iframe
                ShowMediaInUI(player, url, duration);
                
                Puts($"[HGArena] Медиа через браузер для {player.displayName}: {url} ({duration}s)");
            }
            catch (Exception ex)
            {
                PrintWarning($"[HGArena] Ошибка воспроизведения через браузер для {player.displayName}: {ex.Message}");
            }
        }

        void ShowMediaInUI(BasePlayer player, string url, float duration)
        {
            if (player == null || !player.IsConnected) return;

            try
            {
                var container = new CuiElementContainer();
                
                // Создаем полноэкранную панель
                container.Add(new CuiPanel
                {
                    Image = { Color = "0 0 0 0.9" },
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" },
                    CursorEnabled = true
                }, "Overlay", "MediaPlayer");

                bool isVideo = url.EndsWith(".mp4", StringComparison.OrdinalIgnoreCase);
                
                if (isVideo)
                {
                    // Для видео создаем элемент с HTML5 video
                    container.Add(new CuiElement
                    {
                        Name = "MediaContent",
                        Parent = "MediaPlayer",
                        Components =
                        {
                            new CuiRawImageComponent { Url = url },
                            new CuiRectTransformComponent { AnchorMin = "0.1 0.1", AnchorMax = "0.9 0.9" }
                        }
                    });
                }
                else
                {
                    // Для аудио показываем информацию
                    container.Add(new CuiLabel
                    {
                        Text = { Text = "🎵 Воспроизведение аудио\n\nЕсли звук не слышен, проверьте настройки браузера", FontSize = 20, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" },
                        RectTransform = { AnchorMin = "0.2 0.4", AnchorMax = "0.8 0.6" }
                    }, "MediaPlayer");
                }

                // Кнопка закрытия
                container.Add(new CuiButton
                {
                    Button = { Command = "hg.media.close", Color = "0.8 0.2 0.2 0.8" },
                    RectTransform = { AnchorMin = "0.9 0.9", AnchorMax = "0.98 0.98" },
                    Text = { Text = "✕", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, "MediaPlayer");

                CuiHelper.AddUi(player, container);

                // Автоматически закрываем через указанное время
                timer.Once(duration, () => CuiHelper.DestroyUi(player, "MediaPlayer"));

                // Пытаемся воспроизвести через браузер
                player.SendConsoleCommand($"global.browse {url}");
            }
            catch (Exception ex)
            {
                PrintWarning($"[HGArena] Ошибка создания UI медиаплеера для {player.displayName}: {ex.Message}");
            }
        }

        [ConsoleCommand("hg.media.close")]
        private void CmdMediaClose(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            CuiHelper.DestroyUi(player, "MediaPlayer");
        }

        // Простой и надежный метод через чат-сообщение с ссылкой
        void PlayMediaViaChat(BasePlayer player, string url, float duration)
        {
            if (player == null || !player.IsConnected) return;

            try
            {
                bool isVideo = url.EndsWith(".mp4", StringComparison.OrdinalIgnoreCase);
                string mediaType = isVideo ? "видео" : "аудио";
                
                // Отправляем сообщение в чат с кликабельной ссылкой
                string message = $"<color=#00FF00>🎵 Воспроизведение {mediaType}</color>\n<color=#FFFF00>Нажмите для просмотра:</color> <color=#00FFFF>{url}</color>";
                player.ChatMessage(message);
                
                // Также пытаемся открыть в браузере
                player.SendConsoleCommand($"global.browse {url}");
                
                Puts($"[HGArena] Медиа через чат для {player.displayName}: {url} ({duration}s)");
            }
            catch (Exception ex)
            {
                PrintWarning($"[HGArena] Ошибка отправки медиа через чат для {player.displayName}: {ex.Message}");
            }
        }

        #endregion

        #region State

        private const string PermAdmin = "hgarena.admin";

        private class PlayerInfo { public int CellIndex = -1; }

        private class Cell
        {
            public Vector3 Pos;
            public Quaternion Rot;
            public List<BaseEntity> Entities = new List<BaseEntity>();
            public Door Door;
            public Vector3? Spawn;
        }

        private class Area // для HG_Default
        {
            public Vector3 Pos;
            public Quaternion Rot;
            public List<BaseEntity> Entities = new List<BaseEntity>();
            public Vector3? Spawn;

            // вычисленные габариты
            public Bounds? Bounds;          // общий AABB
            public Vector3 Size;            // Bounds.Value.size
            public Vector3 Center;          // Bounds.Value.center

            // кэш pookie
            public BaseEntity Pookie;
        }

        private class State
        {
            public bool LobbyOpen;
            public bool Running;

            public Dictionary<BasePlayer, PlayerInfo> Players = new Dictionary<BasePlayer, PlayerInfo>();
            public HashSet<ulong> InvitedPlayers = new HashSet<ulong>(); // Приглашенные игроки
            public HashSet<ulong> SelectedPlayers = new HashSet<ulong>(); // Выбранные администратором игроки
            public List<Cell> Cells = new List<Cell>();
            public Area DefaultArea;

            public Vector3 ArenaCenter = Vector3.zero;
            public float CircleRadius;

            public int TotalAtStart;
            public int MatchSecondsLeft;

            public Timer T_Lobby, T_UILobby, T_UIMatch, T_CircleShrink, T_Rads, T_MaxMatch, T_Metabolism, T_Airdrop, T_EnforceDefault, T_ScoreboardLive, T_ShrinkCycle;

            public HashSet<ulong> OurSupplyDrops = new HashSet<ulong>();
            public List<Vector3> PlannedDropTargets = new List<Vector3>();

            // вставка Default
            public bool DefaultPasteInProgress = false;
            public float DefaultPasteLastAt = -999f;
			
			public Signage ScoreSign;              // выбранная табличка в HG_Default
			public List<string> LastResults = new List<string>(); // итоги прошлого матча (строки)
			public List<string> EliminatedOrder = new List<string>(); // порядок выбывания (имена)
			public bool ShrinkActive;
        }

        private State S = new State();

        // Кандидаты позиций на океане (как в FishingContest)
        private List<Vector3> OceanSpawnLocations = new List<Vector3>();
        private bool OceanScanInProgress = false;

        #endregion

        #region Init / Commands

        void Init()
        {
            permission.RegisterPermission(PermAdmin, this);
            cmd.AddChatCommand(Cfg.CmdStart, this, nameof(CmdStart));
            cmd.AddChatCommand(Cfg.CmdEnd, this, nameof(CmdEnd));
            cmd.AddChatCommand(Cfg.CmdLeave, this, nameof(CmdLeave));
            cmd.AddChatCommand("hg", this, nameof(CmdHGUI));
            cmd.AddChatCommand("hginvite", this, nameof(CmdInvite));
            cmd.AddChatCommand("hgjoin", this, nameof(CmdJoin));
        }
 
        void OnServerInitialized()
        {
            // Попробовать привязаться к уже существующей HG_Default (после перезагрузки плагина)
            if (!TryBindExistingDefaultArea())
            {
                EnsureDefaultArea(always: true);
            }

            // Всегда держим не-участников в HG_Default
            StartEnforcer();

            if (Cfg.AutoStartSeconds > 0f) 
                timer.Every(Cfg.AutoStartSeconds, () =>
                {
                    if (!S.Running && !S.LobbyOpen)
                        StartLobby(Cfg.LobbySeconds);
                }); 

				//HGVoiceVideo?.Call("API_PlayAll", "https://storage.prostoj.store/HGArena/welcome.mp4", 12f);
            // Соберём точки над океаном
            if (!OceanScanInProgress)
            {
                OceanScanInProgress = true;
                ServerMgr.Instance.StartCoroutine(Co_OceanCollectLocations());
            }
        }

        void Unload() => EndAll(false);

        [ChatCommand("starthg")]
        void CmdStart(BasePlayer p, string cmd, string[] args)
        {
            if (p != null && !permission.UserHasPermission(p.UserIDString, PermAdmin)) return;
            int lobby = Cfg.LobbySeconds;
            if (args.Length > 0 && int.TryParse(args[0], out var sec)) lobby = Mathf.Max(10, sec);
            StartLobby(lobby);
        }

        [ChatCommand("endhg")]
        void CmdEnd(BasePlayer p, string cmd, string[] args)
        {
            if (p != null && !permission.UserHasPermission(p.UserIDString, PermAdmin)) return;
            Broadcast("Игра принудительно завершена администратором.");
            EndAll(true);
        }

        [ChatCommand("leave")]
        void CmdLeave(BasePlayer p, string cmd, string[] args)
        {
            if (p == null) return;
            if (!S.Running && !S.LobbyOpen) { Msg(p, "Сейчас нет активной игры."); return; }
            ForceLeave(p);
        }

        [ChatCommand("hg")]
        void CmdHGUI(BasePlayer p, string cmd, string[] args)
        {
            if (p == null) return;
            if (!permission.UserHasPermission(p.UserIDString, PermAdmin)) 
            {
                ShowPlayerHGUI(p);
                return;
            }
            ShowAdminHGUI(p);
        }

        [ChatCommand("hginvite")]
        void CmdInvite(BasePlayer p, string cmd, string[] args)
        {
            if (p == null || !permission.UserHasPermission(p.UserIDString, PermAdmin)) return;
            if (args.Length == 0) { Msg(p, "Использование: /hginvite <имя игрока>"); return; }

            var target = BasePlayer.Find(args[0]);
            if (target == null) { Msg(p, "Игрок не найден."); return; }

            S.InvitedPlayers.Add(target.userID);
            Msg(p, $"Игрок {target.displayName} приглашен в Голодные Игры.");
            Msg(target, "Вы приглашены в Голодные Игры! Используйте /hgjoin для участия.");
        }

        [ChatCommand("hgjoin")]
        void CmdJoin(BasePlayer p, string cmd, string[] args)
        {
            if (p == null) return;
            
            if (Cfg.StartMode == "invite" && !S.InvitedPlayers.Contains(p.userID))
            {
                Msg(p, "Вы не приглашены в эту игру.");
                return;
            }

            if (!S.LobbyOpen) { Msg(p, "Лобби закрыто."); return; }
            
            Enroll(p);
        }

        #endregion

        #region Player Hooks / Damage / Spawned
		// Найти самую большую рисуемую табличку в HG_Default
		void TryBindScoreboard()
		{
			try
			{
				S.ScoreSign = null;
				if (S.DefaultArea == null || S.DefaultArea.Entities == null) return;

				// Берём все Signage внутри DefaultArea и выбираем с наибольшей площадью коллайдера
				float best = 0f;
				foreach (var sign in S.DefaultArea.Entities.OfType<Signage>())
				{
					var col = sign.GetComponentInChildren<Collider>();
					if (col == null) continue;
					var size = col.bounds.size;
					float area = size.x * size.y; // «площадь» фасада, достаточно для сравнения
					if (area > best)
					{
						best = area;
						S.ScoreSign = sign;
					}
				}
				if (S.ScoreSign != null)
					Puts($"[HGArena] Табличка для табло найдена: {S.ScoreSign.ShortPrefabName} @ {S.ScoreSign.transform.position}");
				else
					Puts("[HGArena] В HG_Default не нашёл табличку для табло (Signage).");
			}
			catch (Exception e)
			{
				PrintWarning($"[HGArena] TryBindScoreboard error: {e.Message}");
			}
		}

		// Универсальная отрисовка большого текста на табличке через SignArtist
		void DrawScoreboard(string title, IEnumerable<string> lines)
		{
			if (SignArtist == null || !SignArtist.IsLoaded || S.ScoreSign == null) return;

			// Собираем финальный текст (SignArtist сам экранирует \n в API_SignText)
			var msg = title + "\n";
			foreach (var l in lines)
				msg += l + "\n";

			// Нужен любой BasePlayer для вызова API СА (используется для логов/лимитов).
			var any = BasePlayer.activePlayerList.FirstOrDefault()
					  ?? S.Players.Keys.FirstOrDefault();

			try
			{
				// Крупный шрифт, светлый текст на тёмном фоне
				SignArtist.Call("API_SignText", any, S.ScoreSign, msg.TrimEnd('\n'), 18, "FFFFFF", "000000", 0u);
			}
			catch (Exception e)
			{
				PrintWarning($"[HGArena] DrawScoreboard error: {e.Message}");
			}
		}

		// Быстрые пресеты контента
		void SB_ShowLobby(int secondsLeft)
		{
			var lines = new List<string>
			{
				$"Старт через: {FormatTime(secondsLeft)}",
				$"Записано: {S.Players.Count}"
			};
			DrawScoreboard("ГОЛОДНЫЕ ИГРЫ • ЛОББИ", lines);
		}

		void SB_ShowMatch()
		{
			var lines = new List<string>
			{
				$"Живых: {S.Players.Count} / {S.TotalAtStart}",
				$"Время: {FormatTime(S.MatchSecondsLeft)}",
				$"Радиус купола: {(int)Mathf.Max(S.CircleRadius, 0f)}м"
			};
			DrawScoreboard("ГОЛОДНЫЕ ИГРЫ • МАТЧ", lines);
		}

		void SB_ShowResults()
		{
			var lines = S.LastResults.Count > 0 ? S.LastResults : new List<string> { "Нет данных." };
			DrawScoreboard("ГОЛОДНЫЕ ИГРЫ • ИТОГИ", lines);
		}
        void OnPlayerInit(BasePlayer p)
        {
            if (p != null && !S.Players.ContainsKey(p))
                TeleportNonParticipantToDefault(p);
        }

        void OnPlayerConnected(BasePlayer p)
        {
            // Автозапись новых игроков во время лобби в зависимости от режима
            if (S.LobbyOpen && Cfg.AutoEnrollJoinersDuringLobby && (Cfg.AllowAdminParticipation || !permission.UserHasPermission(p.UserIDString, PermAdmin)))
            {
                if (Cfg.StartMode == "auto")
                {
                    Enroll(p);
                }
                else if (Cfg.StartMode == "manual" && S.SelectedPlayers.Contains(p.userID))
                {
                    Enroll(p);
                }
                // В режиме invite игроки должны сами присоединяться через /hgjoin
            }
            
            if (p != null && !S.Players.ContainsKey(p))
                TeleportNonParticipantToDefault(p);
			
			// Приветственное медиа
			if (Cfg.MediaEnabled && !string.IsNullOrEmpty(Cfg.WelcomeMediaUrl))
			{
				PlayMediaForPlayer(p, Cfg.WelcomeMediaUrl, Cfg.WelcomeMediaDuration);
				ShowMediaNotification(p, "🎵 Добро пожаловать на сервер!", 3f);
			}
        }

        void OnPlayerRespawned(BasePlayer p)
        {
            if (p != null && !S.Players.ContainsKey(p))
                TeleportNonParticipantToDefault(p);
        }

        void OnPlayerSleepEnded(BasePlayer p)
        {
            if (p != null && !S.Players.ContainsKey(p))
                TeleportNonParticipantToDefault(p);
        }

        void OnPlayerDisconnected(BasePlayer p, string reason)
        {
            // Закрываем UI при отключении
            CuiHelper.DestroyUi(p, HG_ADMIN_UI);
            CuiHelper.DestroyUi(p, HG_PLAYER_UI);
            CuiHelper.DestroyUi(p, HG_PLAYERS_LIST);
            CuiHelper.DestroyUi(p, HG_CONFIG_UI);
            CuiHelper.DestroyUi(p, HG_TIME_UI);
            
			if (S.Running && !string.IsNullOrEmpty(p.displayName) && S.Players.ContainsKey(p))
				S.EliminatedOrder.Add($"{p.displayName} (disconnect)");
            if (S.Players.Remove(p) && S.Running)
            {
                UpdateMatchHUD();
                CheckWin();
            }
        }

        void OnPlayerDeath(BasePlayer p, HitInfo info)
        {
            if (!S.Running || !S.Players.ContainsKey(p)) return;

            if (Cfg.AnnounceDeaths)
                Broadcast($"Игрок <color=#15F5E4>{p.displayName}</color> выбыл. Осталось <color=#F5AA15>{S.Players.Count - 1}</color>.");

            S.Players.Remove(p);
            UpdateMatchHUD();
			SB_ShowMatch();
            CheckWin();
			if (!string.IsNullOrEmpty(p.displayName))
				S.EliminatedOrder.Add(p.displayName);
        }

        // никаких трупов — удаляем моментально
        void OnEntitySpawned(BaseNetworkable ent)
        {
            if (ent == null) return;

            // 1) Трупы убираем всегда, независимо от матча
            if (ent is PlayerCorpse)
            {
                NextTick(() =>
                {
                    if (ent != null && !ent.IsDestroyed) ent.KillMessage();
                });
                return;
            }

            // ниже — только во время матча
            if (!S.Running) return;

            // 2) Наши supply_drop — ускоряем падение и заменяем лут
            string sn = ent.ShortPrefabName?.ToLowerInvariant() ?? string.Empty;
            string path = (ent.PrefabName ?? "").ToLowerInvariant();
            if (sn.Contains("supply_drop") || path.Contains("supply_drop"))
            {
                var be = ent as BaseEntity;
                if (be == null) return;

                // ускорим падение
                try
                {
                    var rb = be.GetComponent<Rigidbody>();
                    if (rb != null)
                    {
                        rb.drag = 0f;
                        rb.angularDrag = 0f;
                        rb.velocity = Vector3.down * (100f * Mathf.Max(1f, Cfg.AirDropFallFaster));
                    }
                }
                catch { }

                // если рядом с запланированной целью — считаем «нашим» и наполняем
                if (S.PlannedDropTargets.Any(t => Vector3.Distance(t, be.transform.position) < 150f))
                {
                    S.OurSupplyDrops.Add(be.net.ID.Value);
                    NextTick(() => TryFillOurSupplyDrop(be as LootContainer));
                }
                return;
            }
        }

        // Гарантируем PVP-урон между участниками; если обнулён — добавляем страховочный Hurt
        object OnEntityTakeDamage(BaseCombatEntity entity, HitInfo info)
        {
            if (!S.Running || info == null) return null;

            var victim = entity as BasePlayer;
            var attacker = info.InitiatorPlayer;

            if (victim != null && attacker != null &&
                S.Players.ContainsKey(victim) && S.Players.ContainsKey(attacker))
            {
                float total = info.damageTypes?.Total() ?? 0f;
                if (total <= 0.01f)
                {
                    var dtype = (info.damageTypes != null)
                        ? info.damageTypes.GetMajorityDamageType()
                        : Rust.DamageType.Generic;

                    float fallback = 10f;
                    NextTick(() =>
                    {
                        if (victim != null && !victim.IsDead())
                            victim.Hurt(fallback, dtype, attacker, false);
                    });
                }
                return null;
            }
            return null;
        }

        void TryFillOurSupplyDrop(LootContainer lc)
        {
            if (lc == null || lc.IsDestroyed) return;
            try
            {
                lc.inventory?.Clear();

                foreach (var kv in Cfg.AirDropLoot)
                {
                    var def = ItemManager.FindItemDefinition(kv.Key);
                    if (def == null) continue;
                    var it = ItemManager.Create(def, Math.Max(1, kv.Value));
                    if (it == null) continue;
                    if (!it.MoveToContainer(lc.inventory)) it.Remove();
                }

                lc.SendNetworkUpdate();
            }
            catch (Exception e) { PrintWarning($"[HGArena] Ошибка наполнения аирдропа: {e.Message}"); }
        }

        #endregion

        #region Lobby / Start / End

        void StartLobby(int lobbySeconds)
        {
            if (S.Running || S.LobbyOpen) { MsgAdmins("Набор уже открыт или игра идёт."); return; }

            S = new State();
            S.LobbyOpen = true;

            // Автозапись игроков в зависимости от режима
            if (Cfg.StartMode == "auto" && Cfg.AutoEnrollOnlineOnLobby)
            {
                foreach (var pl in BasePlayer.activePlayerList) 
                {
                    if (Cfg.AllowAdminParticipation || !permission.UserHasPermission(pl.UserIDString, PermAdmin))
                        Enroll(pl);
                }
            }
            else if (Cfg.StartMode == "manual")
            {
                // Записываем только выбранных игроков (автоматически)
                foreach (var userId in S.SelectedPlayers.ToList())
                {
                    var pl = BasePlayer.FindByID(userId);
                    if (pl != null && pl.IsConnected)
                    {
                        // Принудительно записываем выбранного игрока
                        if (!S.Players.ContainsKey(pl))
                        {
                            S.Players[pl] = new PlayerInfo();
                            Msg(pl, "Вы автоматически добавлены в турнир администратором.");
                        }
                    }
                }
            }
            // В режиме invite игроки присоединяются сами через /hgjoin

            Broadcast($"Набор на «Голодные игры» открыт! Старт через {lobbySeconds} сек.");

			SB_ShowLobby(lobbySeconds);
			S.T_UILobby?.Destroy();
			S.T_UILobby = timer.Every(5f, () => SB_ShowLobby(Mathf.Max(0, lobbySeconds - (int)(Time.realtimeSinceStartup % (lobbySeconds+1)))));

            if (Cfg.ShowUI)
            {
                int left = lobbySeconds;
                S.T_UILobby?.Destroy();
                S.T_UILobby = timer.Every(1f, () =>
                {
                    left = Math.Max(0, left - 1);
                    foreach (var pl in S.Players.Keys.ToList()) ShowLobbyUI(pl, left);
                });
            }

            S.T_Lobby?.Destroy();
            S.T_Lobby = timer.Once(lobbySeconds, () =>
            {
                S.LobbyOpen = false;
                S.T_UILobby?.Destroy(); S.T_UILobby = null;
                foreach (var pl in S.Players.Keys.ToList()) HideLobbyUI(pl);

                if (S.Players.Count < Cfg.MinPlayers)
                {
                    Broadcast($"Недостаточно игроков ({S.Players.Count}/{Cfg.MinPlayers}). Игра отменена.");
                    EndAll(false);
                    return;
                }

                // Полный вайп инвентаря перед стартом
                foreach (var pl in S.Players.Keys.ToList())
                {
                    try
                    {
                        pl.inventory.Strip();
                        pl.SendNetworkUpdateImmediate();
                    }
                    catch { }
                }
				
				CustomCrateSpawner?.Call("API_Spawn");

                if (!BuildCagesAndTeleport())
                {
                    Broadcast("Не удалось расставить клетки через CopyPaste. Проверьте наличие схемы HG_Cell.");
                    EndAll(false);
                    return;
                }
            });
        }

        void EndAll(bool announce)
        {
			// Сформировать TOP-3, если они ещё не были записаны (например, при таймауте/админ-стопе)
			if (S.LastResults.Count == 0)
			{
				// кандидаты на «живых» по окончании
				var aliveNames = S.Players.Keys.Where(x => x != null).Select(x => x.displayName).ToList();

				string first = null, second = null, third = null;

				if (aliveNames.Count == 1)
				{
					first = aliveNames[0];
					second = S.EliminatedOrder.Count >= 1 ? S.EliminatedOrder[S.EliminatedOrder.Count - 1] : null;
					third  = S.EliminatedOrder.Count >= 2 ? S.EliminatedOrder[S.EliminatedOrder.Count - 2] : null;
				}
				else if (aliveNames.Count >= 2)
				{
					// Если матч прерван с несколькими живыми — просто покажем их как лидеров
					first = aliveNames[0];
					second = aliveNames.Count >= 2 ? aliveNames[1] : null;
					third  = aliveNames.Count >= 3 ? aliveNames[2] : null;
				}
				else // никого живых — возьмём последних трёх выбывших
				{
					first = S.EliminatedOrder.Count >= 1 ? S.EliminatedOrder[S.EliminatedOrder.Count - 1] : null;
					second = S.EliminatedOrder.Count >= 2 ? S.EliminatedOrder[S.EliminatedOrder.Count - 2] : null;
					third  = S.EliminatedOrder.Count >= 3 ? S.EliminatedOrder[S.EliminatedOrder.Count - 3] : null;
				}

				S.LastResults.Clear();
				if (!string.IsNullOrEmpty(first))  S.LastResults.Add($"1 место: {first}");
				if (!string.IsNullOrEmpty(second)) S.LastResults.Add($"2 место: {second}");
				if (!string.IsNullOrEmpty(third))  S.LastResults.Add($"3 место: {third}");

				SB_ShowResults(); // вывести на табличку
			}
            // UI
            if (Cfg.ShowUI) 
            {
                foreach (var pl in BasePlayer.activePlayerList)
                {
                    HideLobbyUI(pl);
                    HideMatchHUD(pl);
                }
            }
			
			// Сформировать простые итоги (топ-3, если есть)
			S.LastResults.Clear();
			var aliveNow = S.Players.Keys.Select(x => x.displayName).ToList();
			if (aliveNow.Count == 1) S.LastResults.Add($"1 место: {aliveNow[0]}");
			else if (aliveNow.Count >= 2)
			{
				S.LastResults.Add($"1 место: {aliveNow[0]}");
				S.LastResults.Add($"2 место: {aliveNow[1]}");
				if (aliveNow.Count >= 3) S.LastResults.Add($"3 место: {aliveNow[2]}");
			}

			// Показать итоги на табличке
			SB_ShowResults();

			// Остановить живой апдейт табло
			S.T_ScoreboardLive?.Destroy(); S.T_ScoreboardLive = null;

            // Чистка клеток
            foreach (var c in S.Cells)
                foreach (var e in c.Entities)
                    try { if (e && !e.IsDestroyed) e.KillMessage(); } catch { }
            S.Cells.Clear();

            // Удалить сферы купола
            DismissSpheres();

            // Таймеры
            S.T_Lobby?.Destroy(); S.T_Lobby = null;
            S.T_UILobby?.Destroy(); S.T_UILobby = null;
            S.T_UIMatch?.Destroy(); S.T_UIMatch = null;
            S.T_CircleShrink?.Destroy(); S.T_CircleShrink = null;
            S.T_Rads?.Destroy(); S.T_Rads = null;
            S.T_MaxMatch?.Destroy(); S.T_MaxMatch = null;
            S.T_Metabolism?.Destroy(); S.T_Metabolism = null;
            S.T_Airdrop?.Destroy(); S.T_Airdrop = null;
			S.EliminatedOrder.Clear();
            S.PlannedDropTargets.Clear();
            S.OurSupplyDrops.Clear();

            S.Running = false;
            S.LobbyOpen = false;
            S.Players.Clear();

            // HG_Default не трогаем — остаётся как лобби
            StartEnforcer();
        }

        void Enroll(BasePlayer p)
        { 
            if (p == null || !p.IsConnected || p.IsDead()) return;
            if (S.Players.ContainsKey(p)) return;
            if (!Cfg.AllowAdminParticipation && permission.UserHasPermission(p.UserIDString, PermAdmin)) return;

            S.Players[p] = new PlayerInfo();
            Msg(p, "Ты добавлен в список участников (инвентарь будет очищен перед стартом).");
        }

        void ForceLeave(BasePlayer p)
        {
            if (!S.Players.ContainsKey(p)) { Msg(p, "Ты не участвуешь."); return; }
			if (S.Running && !string.IsNullOrEmpty(p.displayName) && S.Players.ContainsKey(p))
				S.EliminatedOrder.Add($"{p.displayName} (disconnect)");
            S.Players.Remove(p);
            Msg(p, "Ты покинул игру.");
            UpdateMatchHUD();
            CheckWin();

            if (S.Running) TeleportNonParticipantToDefault(p);
        }

        #endregion

        #region CopyPaste cells / Default / Teleport

        List<Vector3> MakeCirclePoints(int count, float radius)
        {
            var list = new List<Vector3>(count);
            var center = Vector3.zero;
            for (int i = 0; i < count; i++)
            {
                float t = (Mathf.PI * 2f) * (i / (float)count);
                var dir = new Vector3(Mathf.Cos(t), 0f, Mathf.Sin(t));
                list.Add(center + (dir * radius));
            }
            return list;
        }

        bool BuildCagesAndTeleport()
        {
            int need = S.Players.Count;
            if (need <= 0) return false;

            var points = MakeCirclePoints(need, Mathf.Max(8f, Cfg.CellsCircleRadius));
            var centers = S.Cells
				.Select(c => (c.Spawn ?? (c.Pos + Vector3.up)))
				.ToList();

			if (centers.Count > 0)
			{
				float cx = 0f, cz = 0f;
				foreach (var v in centers) { cx += v.x; cz += v.z; }
				cx /= centers.Count; cz /= centers.Count;

				// сохраним XZ, а Y нормализуем по безопасной поверхности
				var cc = new Vector3(cx, SafeSurfaceY(new Vector3(cx, 0f, cz)), cz);
				S.ArenaCenter = cc;
			}
			else
			{
				S.ArenaCenter = Vector3.zero; // fallback
			}

            S.Cells.Clear();
            int toBuild = need, built = 0; bool failed = false;

            int idx = 0;
            foreach (var p in S.Players.Keys.ToList())
            {
                var pos = points[idx++];
                Quaternion rot = Quaternion.identity;
                if (Cfg.CellsFaceToCenter)
                {
                    var dir = (S.ArenaCenter - pos); dir.y = 0f;
                    if (dir.sqrMagnitude > 0.01f) rot = Quaternion.LookRotation(dir.normalized);
                }

                if (!PasteCellViaCopyPaste(pos, rot, (cell) =>
                {
                    if (cell == null || cell.Entities.Count == 0) failed = true;
                    else S.Cells.Add(cell);
                    built++;
                }))
                {
                    failed = true;
                    break;
                }
            }

            float timeout = 10f + need * 0.5f, waited = 0f;
            Timer waiter = null;
            waiter = timer.Every(0.2f, () =>
            {
                if (failed)
                {
                    waiter?.Destroy();
                    Broadcast("Не удалось расставить клетки через CopyPaste.");
                    EndAll(false);
                    return;
                }

                waited += 0.2f;
                if (built >= toBuild || waited >= timeout)
                {
                    waiter?.Destroy();

                    if (S.Cells.Count < need)
                    {
                        Broadcast("Не удалось расставить все клетки (таймаут/ошибка CopyPaste).");
                        EndAll(false);
                        return;
                    }

                    // Телепорт участников по клеткам (строго по pookie/полу)
                    int i = 0;
                    foreach (var kv in S.Players.ToList())
                    {
                        var pl = kv.Key;
                        var cell = S.Cells[i++];
                        var pt = cell.Spawn ?? (cell.Pos + Vector3.up * 1f);
                        TeleportPlayer(pl, pt);

                        // статы на старт
                        pl.health = 100f;
                        pl.metabolism.bleeding.value = 0f;
                        pl.metabolism.radiation_level.value = 0f;
                        pl.metabolism.radiation_poison.value = 0f;
                        if (Cfg.KeepFoodWaterFull)
                        {
                            pl.metabolism.calories.value = pl.metabolism.calories.max;
                            pl.metabolism.hydration.value = pl.metabolism.hydration.max;
                        }
                        pl.SendNetworkUpdateImmediate();

                        S.Players[pl].CellIndex = i - 1;
                    }

                    // Пастим/привязываем Default-зону и уводим туда всех не-участников
                    if (!TryBindExistingDefaultArea())
                        EnsureDefaultArea();
                    foreach (var other in BasePlayer.activePlayerList)
                        if (!S.Players.ContainsKey(other))
                            TeleportNonParticipantToDefault(other);

                    // Старт матча
                    StartMatch();
                }
            });

            return true;
        }

        bool PasteCellViaCopyPaste(Vector3 pos, Quaternion rot, Action<Cell> onReady)
        {
            if (CopyPaste == null || !CopyPaste.IsLoaded)
            {
                Puts("[HGArena] CopyPaste не найден.");
                return false;
            }

            var cell = new Cell { Pos = pos, Rot = rot };
            float rotationCorrectionRad = rot.eulerAngles.y * Mathf.Deg2Rad;

            var spawned = new List<BaseEntity>();

            Action<BaseEntity> onSpawned = (be) =>
            {
                if (be == null || be.IsDestroyed) return;
                spawned.Add(be);
            };

            Action onFinished = () =>
            {
                cell.Entities = spawned.Where(e => e != null && !e.IsDestroyed).ToList();
                cell.Door = cell.Entities.OfType<Door>().FirstOrDefault();

                // Ищем pookie как маркер спавна
                BaseEntity pookie = cell.Entities.FirstOrDefault(e =>
                    !string.IsNullOrEmpty(e.ShortPrefabName) &&
                    e.ShortPrefabName.IndexOf("pookie", StringComparison.OrdinalIgnoreCase) >= 0);

                Vector3 spawnMarker = (pookie != null ? pookie.transform.position : pos) + new Vector3(0f, 0.20f, 0f);

                // Найти верх пола клетки ниже метки
                var floorPos = FindCellFloorTopBelow(cell, spawnMarker, 1.05f);
                if (floorPos.HasValue)
                {
                    cell.Spawn = LiftUntilFree(floorPos.Value);
                }
                else
                {
                    RaycastHit hit;
                    int mask = UnityEngine.LayerMask.GetMask("Construction", "Deployed", "World", "Default");
                    if (UnityEngine.Physics.Raycast(spawnMarker + Vector3.up * 0.30f, Vector3.down, out hit, 1.2f, mask))
                        cell.Spawn = LiftUntilFree(hit.point + Vector3.up * 1.05f);
                    else
                        cell.Spawn = LiftUntilFree(spawnMarker + Vector3.up * 1.05f);
                }

                onReady?.Invoke(cell);
            };

            var args = new[]
            {
                "autoheight","true",
                "blockcollision","0",
                "auth","false",
                "entityowner","false",
                "checkplaced","false",
                "stability","true",
                "dlc","true",
                "skins","1"
            };

            var result = CopyPaste.Call(
                "TryPasteFromVector3",
                pos,
                rotationCorrectionRad,
                Cfg.CopyNameCell,
                args,
                onFinished,
                onSpawned
            );

            if (result is string err && !string.IsNullOrEmpty(err) && !err.Equals("true", StringComparison.OrdinalIgnoreCase))
            {
                Puts($"[HGArena] CopyPaste ошибка: {err}");
                return false;
            }

            return true;
        }

        // ======= HG_Default: привязка/вставка, телепорт =======

        void StartEnforcer()
        {
            if (Cfg.EnforceDefaultPeriod <= 0f) Cfg.EnforceDefaultPeriod = 3f;

            S.T_EnforceDefault?.Destroy();
            S.T_EnforceDefault = timer.Every(Mathf.Max(1f, Cfg.EnforceDefaultPeriod), () =>
            {
                try
                {
                    if (!TryBindExistingDefaultArea()) // если кто-то удалил сущности — попробуем привязаться
                        EnsureDefaultArea(always: true); // если не нашли — вставим

                    foreach (var pl in BasePlayer.activePlayerList)
                    {
                        if (pl == null || !pl.IsConnected) continue;
                        if (S.Players != null && S.Players.ContainsKey(pl)) continue;
                        TeleportNonParticipantToDefault(pl);
                    }
                }
                catch (Exception ex)
                {
                    PrintWarning($"[HGArena] Enforcer tick error: {ex.Message}");
                }
            });
        }

        // Пытаемся найти уже существующую HG_Default по pookie и собрать её Area
        bool TryBindExistingDefaultArea()
        {
            try
            {
                // если уже есть и живы сущности — всё ок
                if (S.DefaultArea != null && S.DefaultArea.Entities != null && S.DefaultArea.Entities.Any(e => e && !e.IsDestroyed))
                    return true;

                BaseEntity pookie = null;
                foreach (var e in BaseNetworkable.serverEntities)
                {
                    var be = e as BaseEntity;
                    if (!be || be.IsDestroyed) continue;
                    var n = be.ShortPrefabName ?? "";
                    if (n.IndexOf("pookie", StringComparison.OrdinalIgnoreCase) >= 0)
                    {
                        // Prefer pookie, который далеко за краем/на воде
                        var pos = be.transform.position;
                        float half = 2000f;
                        try { half = Mathf.Max(500f, global::ConVar.Server.worldsize * 0.5f); } catch { }
                        bool far = pos.magnitude > half * 0.9f || WaterLevel.GetWaterDepth(new Vector3(pos.x, TerrainMeta.HeightMap.GetHeight(pos), pos.z), true, false, null) > 5f;
                        if (far) { pookie = be; break; }
                        if (pookie == null) pookie = be; // запасной
                    }
                }

                if (pookie == null) return false;

                // Соберём все сущности базы вокруг pookie (радиус ~50м)
                var area = new Area { Pos = pookie.transform.position, Rot = Quaternion.identity, Entities = new List<BaseEntity>(), Pookie = pookie };
                var cols = Physics.OverlapSphere(pookie.transform.position, 60f, ~0, QueryTriggerInteraction.Collide);
                foreach (var c in cols)
                {
                    var be2 = c.GetComponentInParent<BaseEntity>();
                    if (be2 != null && !be2.IsDestroyed && !area.Entities.Contains(be2))
                        area.Entities.Add(be2);
                }

                if (area.Entities.Count == 0)
                    return false;

                ComputeAreaBounds(area);

                // Вычислим спавн строго по pookie
                Vector3 pm = pookie.transform.position + new Vector3(0f, 0.20f, 0f);
                var topY = TryFindTopSurfaceYUnderPoint(area, pm, 3.0f);
                if (topY.HasValue) area.Spawn = LiftUntilFree(new Vector3(pm.x, topY.Value + 1.05f, pm.z));
                else area.Spawn = LiftUntilFree(pm + Vector3.up * 1.05f);

                S.DefaultArea = area;
				TryBindScoreboard();
				SB_ShowResults(); // при старте сервера/перепривязке покажем последние итоги
                return true;
            }
            catch { return false; }
        }

        void EnsureDefaultArea(bool always = false)
        {
            // Уже есть и живые сущности зоны — ничего не делаем
            if (S.DefaultArea != null && S.DefaultArea.Entities != null && S.DefaultArea.Entities.Any(e => e && !e.IsDestroyed))
                return;

            // Уже идёт вставка — выходим
            if (S.DefaultPasteInProgress) return;

            // Анти-дребезг: не чаще, чем раз в 15 сек.
            if (Time.realtimeSinceStartup - S.DefaultPasteLastAt < 15f) return;
            S.DefaultPasteInProgress = true;
            S.DefaultPasteLastAt = Time.realtimeSinceStartup;

            if (CopyPaste == null || !CopyPaste.IsLoaded)
            {
                PrintWarning("[HGArena] CopyPaste не найден для HG_Default.");
                S.DefaultPasteInProgress = false;
                return;
            }

            // Позиция из океан-точек, с корректной высотой по воде
            Vector3 pos;
            if (OceanSpawnLocations != null && OceanSpawnLocations.Count > 0)
                pos = OceanSpawnLocations.GetRandom();
            else
            {
                if (!OceanScanInProgress)
                {
                    OceanScanInProgress = true;
                    ServerMgr.Instance.StartCoroutine(Co_OceanCollectLocations());
                }
                var center = (S.Running || S.Cells.Count > 0) ? S.ArenaCenter : Vector3.zero;
                pos = center + new Vector3(0f, 0f, -Mathf.Max(120f, 500f));
            }
            float waterY = 0f;
            try { waterY = TerrainMeta.WaterMap.GetHeight(pos); } catch { }
            pos.y = Mathf.Max(0f, waterY) + 10f;

            var rot = Quaternion.identity;
            var area = new Area { Pos = pos, Rot = rot };
            var spawned = new List<BaseEntity>();

            Action<BaseEntity> onSpawned = (be) =>
            {
                if (be == null || be.IsDestroyed) return;
                spawned.Add(be);
            };

            Action onFinished = () =>
            {
                try
                {
                    area.Entities = spawned.Where(e => e != null && !e.IsDestroyed).ToList();

                    ComputeAreaBounds(area);

                    // Ищем pookie как маркер
                    BaseEntity pookie = area.Entities.FirstOrDefault(e =>
                        !string.IsNullOrEmpty(e.ShortPrefabName) &&
                        e.ShortPrefabName.IndexOf("pookie", StringComparison.OrdinalIgnoreCase) >= 0);
                    area.Pookie = pookie;

                    if (pookie != null)
                    {
                        Vector3 marker = pookie.transform.position + new Vector3(0f, 0.2f, 0f);
                        RaycastHit hit;
                        int mask = UnityEngine.LayerMask.GetMask("Construction", "Deployed", "World", "Default");
                        if (UnityEngine.Physics.Raycast(marker + Vector3.up * 0.30f, Vector3.down, out hit, 3.0f, mask))
                            area.Spawn = LiftUntilFree(hit.point + Vector3.up * 1.05f);
                        else
                            area.Spawn = LiftUntilFree(marker + Vector3.up * 1.05f);
                    }
                    else
                    {
                        if (area.Bounds.HasValue)
                        {
                            var b = area.Bounds.Value;
                            var p = new Vector3(b.center.x, Mathf.Max(b.center.y, SafeSurfaceY(b.center)), b.center.z) + Vector3.up * 1.05f;
                            area.Spawn = LiftUntilFree(p);
                        }
                        else
                        {
                            area.Spawn = LiftUntilFree(pos + Vector3.up * 1.05f);
                        }
                    }

                    S.DefaultArea = area;
					TryBindScoreboard();
					SB_ShowResults();
                    var sz = area.Bounds.HasValue ? area.Size : Vector3.zero;
                    Puts($"[HGArena] HG_Default размещён: {S.DefaultArea.Pos}  size=({sz.x:0.0}x{sz.y:0.0}x{sz.z:0.0})");
                }
                finally
                {
                    S.DefaultPasteInProgress = false;
                }
            };

            var args = new[]
            {
                "autoheight","false", // контролируем Y сами
                "blockcollision","0",
                "auth","false",
                "entityowner","false",
                "checkplaced","false",
                "stability","true",
                "dlc","true",
                "skins","1"
            };

            var result = CopyPaste.Call("TryPasteFromVector3", pos, rot.eulerAngles.y * Mathf.Deg2Rad, Cfg.CopyNameDefault, args, onFinished, onSpawned);

            if (result is string err && !string.IsNullOrEmpty(err) && !err.Equals("true", StringComparison.OrdinalIgnoreCase))
            {
                PrintWarning($"[HGArena] CopyPaste HG_Default ошибка: {err}");
                S.DefaultPasteInProgress = false;
            } 
        }

        void TeleportNonParticipantToDefault(BasePlayer p)
        {
            if (p == null || !p.IsConnected) return;

            // Если зоны нет — инициируем и выходим
            if (S.DefaultArea == null || S.DefaultArea.Entities == null || S.DefaultArea.Entities.Count == 0)
            {
                EnsureDefaultArea(always: true);
                return; 
            }
            // Если мертва — пересоздадим
            if (!S.DefaultArea.Entities.Any(e => e != null && !e.IsDestroyed))
            {
                S.DefaultArea = null;
                EnsureDefaultArea(always: true);
                return;
            } 
            // Если Bounds ещё не посчитаны — попросим досчитать и подождём
            if (!S.DefaultArea.Bounds.HasValue)
            {
                ComputeAreaBounds(S.DefaultArea);
                if (!S.DefaultArea.Bounds.HasValue) return;
            }
            // Уже внутри HG_Default — не дёргаем
            if (IsInsideAreaXZ(S.DefaultArea, p.transform.position))
                return;

            // Точка спавна — строго pookie/пол
            Vector3 dst;
            if (S.DefaultArea.Pookie != null && S.DefaultArea.Pookie && !S.DefaultArea.Pookie.IsDestroyed)
            {
                var pm = S.DefaultArea.Pookie.transform.position + new Vector3(0f, 0.2f, 0f);
                var topY = TryFindTopSurfaceYUnderPoint(S.DefaultArea, pm, 3.0f);
                if (topY.HasValue) dst = LiftUntilFree(new Vector3(pm.x, topY.Value + 1.05f, pm.z));
                else dst = LiftUntilFree(pm + Vector3.up * 1.05f);
            }
            else
            {
                // Фолбэк: внутренняя точка
                dst = PickPointInsideDefaultArea(S.DefaultArea);
            }

            TeleportPlayer(p, dst);
            p.health = 100f;
            p.metabolism.bleeding.value = 0f;
            p.metabolism.radiation_level.value = 0f;
            p.metabolism.radiation_poison.value = 0f; 
            p.SendNetworkUpdateImmediate();
			
            p.inventory.Strip();
            p.SendNetworkUpdateImmediate();
        }

        // ======= Вспомогалки HG_Default =======

        bool ColliderBelongsToArea(Collider col, Area area)
        {
            if (col == null || area == null) return false;
            var be = col.GetComponentInParent<BaseEntity>();
            return be != null && area.Entities != null && area.Entities.Contains(be);
        }

        Vector3 PickPointInsideDefaultArea(Area area, float pad = 0.75f)
        {
            if (area == null) return Vector3.zero;

            // если заранее рассчитали спавн — используем его
            if (area.Spawn.HasValue) return area.Spawn.Value;

            if (!area.Bounds.HasValue)
                return LiftUntilFree(area.Pos + Vector3.up * 1.05f);

            var b = area.Bounds.Value;
            for (int i = 0; i < 12; i++)
            {
                float x = UnityEngine.Random.Range(b.min.x + pad, b.max.x - pad);
                float z = UnityEngine.Random.Range(b.min.z + pad, b.max.z - pad);
                var from = new Vector3(x, b.max.y + 3f, z);
                RaycastHit hit;
                int mask = UnityEngine.LayerMask.GetMask("Construction", "Deployed", "Default", "World");
                if (UnityEngine.Physics.Raycast(from, Vector3.down, out hit, 20f, mask))
                {
                    if (!ColliderBelongsToArea(hit.collider, area)) continue;
                    return LiftUntilFree(hit.point + Vector3.up * 1.05f);
                }
            }
            var c = b.center;
            return LiftUntilFree(new Vector3(c.x, c.y + 1.05f, c.z));
        }

        // верх поверхности пола/фундамента под точкой (внутри нашей Area)
        float? TryFindTopSurfaceYUnderPoint(Area area, Vector3 from, float extraDown = 2.5f)
        {
            var origin = from + Vector3.up * (1.5f + extraDown * 0.2f);
            var dist = 1.5f + extraDown;

            int mask = UnityEngine.LayerMask.GetMask("Construction", "Deployed", "Default", "World");
            var hits = UnityEngine.Physics.RaycastAll(origin, Vector3.down, dist, mask);

            float best = float.NegativeInfinity;
            foreach (var rh in hits)
            {
                if (!ColliderBelongsToArea(rh.collider, area)) continue;
                var n = rh.collider?.name?.ToLowerInvariant() ?? string.Empty;
                if (!(n.Contains("floor") || n.Contains("foundation") || n.Contains("found"))) continue;
                if (rh.point.y > best) best = rh.point.y;
            }
            if (best == float.NegativeInfinity) return null;
            return best;
        }

        bool IsInsideAreaXZ(Area area, Vector3 pos, float pad = 0.25f)
        {
            if (area == null || !area.Bounds.HasValue) return false;
            var b = area.Bounds.Value;
            return pos.x >= (b.min.x + pad) && pos.x <= (b.max.x - pad)
                && pos.z >= (b.min.z + pad) && pos.z <= (b.max.z - pad);
        }

        void ComputeAreaBounds(Area area)
        {
            if (area == null || area.Entities == null || area.Entities.Count == 0) { area.Bounds = null; area.Size = Vector3.zero; area.Center = area.Pos; return; }

            bool hasAny = false;
            Bounds total = new Bounds();

            foreach (var e in area.Entities)
            {
                if (!e) continue;
                var cols = e.GetComponentsInChildren<Collider>(true);
                if (cols == null || cols.Length == 0) continue;

                foreach (var col in cols)
                {
                    if (!hasAny)
                    {
                        total = col.bounds;
                        hasAny = true;
                    }
                    else total.Encapsulate(col.bounds);
                }
            }

            if (hasAny)
            {
                area.Bounds = total;
                area.Size = total.size;
                area.Center = total.center;
            }
            else
            {
                area.Bounds = null;
                area.Size = Vector3.zero;
                area.Center = area.Pos;
            }
        }

        static readonly int HG_RaycastMask = UnityEngine.LayerMask.GetMask("Terrain", "World", "Construction", "Deployed", "Default");

        Vector3 LiftUntilFree(Vector3 pos, float capsuleHeight = 1.8f, float capsuleRadius = 0.35f, int tries = 6)
        {
            for (int i = 0; i < tries; i++)
            {
                var p1 = pos + Vector3.up * (capsuleRadius);
                var p2 = pos + Vector3.up * (capsuleHeight - capsuleRadius);
                if (!UnityEngine.Physics.CheckCapsule(p1, p2, capsuleRadius, HG_RaycastMask))
                    return pos;
                pos += Vector3.up * 0.25f;
            }
            return pos + Vector3.up * 0.25f;
        }

        float SafeSurfaceY(Vector3 p)
        {
            float y = p.y;
            try
            {
                float terrain = TerrainMeta.HeightMap.GetHeight(p);
                float water = TerrainMeta.WaterMap.GetHeight(p);
                y = Mathf.Max(terrain, water) + 0.1f;
            }
            catch { }
            return y;
        }

        // океан-сканер (FishingContest-like)
        private System.Collections.IEnumerator Co_OceanCollectLocations()
        {
            OceanSpawnLocations.Clear();

            float mapSizeX = TerrainMeta.Size.x * 0.5f;
            float mapSizeZ = TerrainMeta.Size.z * 0.5f;

            int step = 20;
            int cycle = 0;
            for (int x = Mathf.RoundToInt(-mapSizeX); x < mapSizeX; x += step)
            {
                for (int z = Mathf.RoundToInt(-mapSizeZ); z < mapSizeZ; z += step)
                {
                    var pos = new Vector3(x, 0f, z);

                    if (!InDeepWater(pos, 20f) || !ContainsTopology(TerrainTopology.Enum.Ocean, pos, 20f))
                        continue;

                    pos.y = TerrainMeta.WaterMap.GetHeight(pos);

                    if (NearIce(pos, 50f))
                        continue;

                    OceanSpawnLocations.Add(pos);

                    cycle++;
                    if (cycle > 10)
                    {
                        cycle = 0;
                        yield return CoroutineEx.waitForEndOfFrame;
                    }
                }
            }

            Puts($"[HGArena] Найдено океан-точек для HG_Default: {OceanSpawnLocations.Count}");
            OceanScanInProgress = false;
            yield return null;
        }

        private static bool InDeepWater(Vector3 p, float depth)
        {
            p.y = TerrainMeta.HeightMap.GetHeight(p);
            return WaterLevel.GetWaterDepth(p, true, false, null) >= depth;
        }

        private static bool ContainsTopology(TerrainTopology.Enum mask, Vector3 position, float radius)
        {
            return (TerrainMeta.TopologyMap.GetTopology(position, radius) & (int)mask) != 0;
        }

        private static readonly Collider[] _hgTmpCols = new Collider[64];
        private static bool NearIce(Vector3 pos, float radius)
        {
            int hits = UnityEngine.Physics.OverlapSphereNonAlloc(pos, radius, _hgTmpCols, ~0, QueryTriggerInteraction.Collide);
            for (int i = 0; i < hits; i++)
            {
                var go = _hgTmpCols[i]?.gameObject;
                if (go == null) continue;
                string n = go.name ?? string.Empty;
                if (n.Contains("ice_sheet") || n.Contains("iceberg_"))
                    return true;
            }
            return false;
        }

        #endregion

        #region StartMatch / Dome / Radiation / Airdrop

        List<BaseEntity> Spheres = new List<BaseEntity>();

        void StartMatch()
        {
            try { global::ConVar.Server.pve = false; } catch { }

            S.Running = true;
            Broadcast("Игра началась! Вперёд!");

			SB_ShowMatch();
			S.T_ScoreboardLive?.Destroy();
			S.T_ScoreboardLive = timer.Every(5f, SB_ShowMatch); // редкое обновление HUD табло

            // HUD
            S.TotalAtStart = S.Players.Count;
            S.MatchSecondsLeft = Cfg.MaxMatchSeconds;

            if (Cfg.ShowUI)
            {
                foreach (var pl in S.Players.Keys.ToList())
                    ShowMatchHUD(pl, S.Players.Count, S.TotalAtStart, S.MatchSecondsLeft);

                S.T_UIMatch?.Destroy(); 
                S.T_UIMatch = timer.Every(1f, () =>
                {
                    S.MatchSecondsLeft = Math.Max(0, S.MatchSecondsLeft - 1);
                    UpdateMatchHUD();
                    if (S.MatchSecondsLeft <= 0)
                    {
                        Broadcast("Время истекло!");
                        EndAll(true);
                    }
                });
            }

            // Еда/вода — всегда полные
            if (Cfg.KeepFoodWaterFull && Cfg.KeepFoodWaterPeriod > 0f)
            {
                S.T_Metabolism?.Destroy();
                S.T_Metabolism = timer.Every(Mathf.Max(1f, Cfg.KeepFoodWaterPeriod), () =>
                {
                    foreach (var pl in S.Players.Keys.ToList())
                    {
                        if (!pl || !pl.IsConnected) continue;
                        pl.metabolism.calories.value = pl.metabolism.calories.max;
                        pl.metabolism.hydration.value = pl.metabolism.hydration.max;
                        pl.SendNetworkUpdate();
                    }
                });
            }

            // Купол
            timer.Once(Mathf.Max(1f, Cfg.CircleDelayAfterStart), BeginCircle);
 
            // Аирдропы  
            if (Cfg.AirDropEnabled && Cfg.AirDropPeriod > 0f) 
            {
                S.T_Airdrop?.Destroy();
                DoHG_Airdrop();
                S.T_Airdrop = timer.Every(Mathf.Max(30f, Cfg.AirDropPeriod), DoHG_Airdrop);
            }

            // Контроль не-участников — таймер уже запущен StartEnforcer()
        } 

        void BeginCircle()
        {
            S.CircleRadius = Cfg.CircleStartRadius;
			
			foreach (var pl in S.Players.Keys.ToList())
			{
				if (!pl || !pl.IsConnected) continue;
				pl.metabolism.radiation_level.value = 0f;
				pl.metabolism.radiation_poison.value = 0f;
				pl.SendNetworkUpdate();
			} 
			
            if (Cfg.VisibleShrinkingDome)
                CreateSphere(S.ArenaCenter, S.CircleRadius, Cfg.DomeDarkness, Cfg.CircleShrinkStep);

            // Радиация только вне купола
            S.T_Rads?.Destroy();
            S.T_Rads = timer.Every(Cfg.RadTick, () =>
            {
                foreach (var pl in S.Players.Keys.ToList())
                {
                    if (!pl || !pl.IsConnected || pl.IsDead()) continue;
 
					var a = pl.transform.position; var b = S.ArenaCenter;
					float dist = Vector2.Distance(new Vector2(a.x, a.z), new Vector2(b.x, b.z));

                    if (dist > S.CircleRadius)
                    {
                        pl.Hurt(Mathf.Max(1f, Cfg.RadAmount), Rust.DamageType.Radiation, null, false);
                        pl.metabolism.radiation_level.value = Mathf.Min(500f, pl.metabolism.radiation_level.value + Cfg.RadAmount * 0.5f);
                    }
                    else
                    {
                        float clear = Mathf.Max(1f, Cfg.RadAmount * 0.75f);
                        pl.metabolism.radiation_level.value = Mathf.Max(0f, pl.metabolism.radiation_level.value - clear);
                        pl.metabolism.radiation_poison.value = Mathf.Max(0f, pl.metabolism.radiation_poison.value - clear);
                    }

                    pl.SendNetworkUpdate();
                }
            });

            S.T_CircleShrink?.Destroy();

			// запускаем цикл «3 минуты сжатие → пауза → ...»
			StartShrinkCycle();
        }

        void DoHG_Airdrop()
        {
            if (!S.Running) return;

            // цель внутри текущего круга
            float r = Mathf.Max(Cfg.CircleMinRadius, S.CircleRadius) * Mathf.Clamp01(Cfg.AirDropTargetFactor);
            float ang = UnityEngine.Random.Range(0f, Mathf.PI * 2f);
            Vector3 target = S.ArenaCenter + new Vector3(Mathf.Cos(ang) * r, 0f, Mathf.Sin(ang) * r);
            target.y = TerrainMeta.HeightMap.GetHeight(target) + 1f;

            // самолёт — летит низко (в 20 раз ниже)
            float h = Mathf.Max(50f, Cfg.AirDropHeight / Mathf.Max(1f, Cfg.AirDropFallFaster));
            var start = target + new Vector3(-1200f, h, -1200f);

            var plane = GameManager.server.CreateEntity("assets/prefabs/npc/cargo plane/cargo_plane.prefab", start) as CargoPlane;
            if (plane == null) return;
            plane.InitDropPosition(target);
            plane.Spawn();

            S.PlannedDropTargets.Add(target);
            Broadcast("Аирдроп направлен в зону!");
        }
		
		void StartShrinkCycle()
		{
			if (S.CircleRadius <= Cfg.CircleMinRadius)
			{
				FinalizeShrink();
				return;
			}

			S.ShrinkActive = true;
			if (Cfg.CircleShrinkAnnouncements) Broadcast("Сжатие купола началось!");

			// запускаем «шаги» сжатия по Cfg.CircleShrinkPeriod
			S.T_CircleShrink?.Destroy();
			S.T_CircleShrink = timer.Every(Mathf.Max(0.2f, Cfg.CircleShrinkPeriod), () =>
			{
				var next = S.CircleRadius - Mathf.Max(0.01f, Cfg.CircleShrinkStep);
				if (next > Cfg.CircleMinRadius)
				{
					S.CircleRadius = next;
				}
				else
				{
					S.CircleRadius = Cfg.CircleMinRadius;
					RefreshDomeVisual(paused: true);
					FinalizeShrink();
				}
			});

			RefreshDomeVisual(paused: false);

			// через BurstSeconds — пауза
			S.T_ShrinkCycle?.Destroy();
			S.T_ShrinkCycle = timer.Once(Mathf.Max(5f, Cfg.CircleShrinkBurstSeconds), PauseShrink);
		}

		void PauseShrink()
		{
			S.ShrinkActive = false;
			S.T_CircleShrink?.Destroy();

			if (Cfg.CircleShrinkAnnouncements)
				Broadcast($"Сжатие купола приостановлено на {Mathf.Max(1, Cfg.CircleShrinkPauseSeconds)} сек.");

			RefreshDomeVisual(paused: true);

			if (S.CircleRadius <= Cfg.CircleMinRadius)
			{
				FinalizeShrink();
				return;
			}

			// после паузы — новая «трёхминутка» сжатия
			S.T_ShrinkCycle?.Destroy();
			S.T_ShrinkCycle = timer.Once(Mathf.Max(5f, Cfg.CircleShrinkPauseSeconds), StartShrinkCycle);
		}

		void FinalizeShrink()
		{
			S.ShrinkActive = false;
			S.T_CircleShrink?.Destroy();  S.T_CircleShrink = null;
			S.T_ShrinkCycle?.Destroy();   S.T_ShrinkCycle  = null;

			if (Cfg.VisibleShrinkingDome)
			{
				DismissSpheres();
				// статический купол на минимальном радиусе
				CreateSphere(S.ArenaCenter, S.CircleRadius, Cfg.DomeDarkness, 0f);
			}
		}

		void RefreshDomeVisual(bool paused)
		{
			if (!Cfg.VisibleShrinkingDome) return;

			// перерисовываем купол, speed=0 во время паузы
			DismissSpheres();
			float visualSpeed = paused ? 0f : Mathf.Max(0.01f, Cfg.CircleShrinkStep);
			CreateSphere(S.ArenaCenter, S.CircleRadius, Cfg.DomeDarkness, visualSpeed);
		}


        void CreateSphere(Vector3 position, float radius, int darkness, float speed)
        {
            foreach (var sOld in Spheres) try { if (sOld) sOld.KillMessage(); } catch { }
            Spheres.Clear();

            for (int i = 0; i < darkness; i++)
            {
                var s = GameManager.server.CreateEntity("assets/prefabs/visualization/sphere.prefab", position) as SphereEntity;
                if (s == null) continue;
                s.currentRadius = radius * 2f - 10;
                s.lerpSpeed = speed * 2f;
                s.Spawn();
                Spheres.Add(s);
            }
        }

        void DismissSpheres()
        {
            foreach (var s in Spheres) try { if (s) s.KillMessage(); } catch { }
            Spheres.Clear();
        }

        #endregion

        #region Win / Check

        void CheckWin()
        {
            if (!S.Running) return;

           if (S.Players.Count == 1)
			{
				var winner = S.Players.Keys.First();
				Broadcast($"{winner.displayName} — победитель!");

				// Сформируем TOP-3 в S.LastResults для таблички
				S.LastResults.Clear();
				string first = winner.displayName;
                winner.inventory.Strip();
                winner.SendNetworkUpdateImmediate();
				string second = S.EliminatedOrder.Count >= 1 ? S.EliminatedOrder[S.EliminatedOrder.Count - 1] : null;
				string third  = S.EliminatedOrder.Count >= 2 ? S.EliminatedOrder[S.EliminatedOrder.Count - 2] : null;

				S.LastResults.Add($"1 место: {first}");
				if (!string.IsNullOrEmpty(second)) S.LastResults.Add($"2 место: {second}");
				if (!string.IsNullOrEmpty(third))  S.LastResults.Add($"3 место: {third}");

				// Показать на табличке сразу
				SB_ShowResults();
				
				// Воспроизвести медиа окончания матча для всех
				if (Cfg.MediaEnabled && !string.IsNullOrEmpty(Cfg.EndMediaUrl))
				{
					PlayMediaForAll(Cfg.EndMediaUrl, Cfg.EndMediaDuration);
					// Показываем уведомление всем игрокам
					foreach (var pl in BasePlayer.activePlayerList)
					{
						ShowMediaNotification(pl, "🏆 Матч завершен!", 5f);
					}
				}

				EndAll(true);
			}
            else if (S.Players.Count == 0)
            { 
                Broadcast("Все погибли!");
				
				// Воспроизвести медиа окончания матча для всех
				if (Cfg.MediaEnabled && !string.IsNullOrEmpty(Cfg.EndMediaUrl))
				{
					PlayMediaForAll(Cfg.EndMediaUrl, Cfg.EndMediaDuration);
					// Показываем уведомление всем игрокам
					foreach (var pl in BasePlayer.activePlayerList)
					{
						ShowMediaNotification(pl, "💀 Все погибли!", 5f);
					}
				}
				
                EndAll(true);
            }
        }
		// Ищет верхнюю точку пола/фундамента ВНУТРИ данной клетки под маркером (плюшкой)
		// Возвращает позицию для спавна (с заданным смещением по Y), либо null если не нашли
		Vector3? FindCellFloorTopBelow(Cell cell, Vector3 markerPos, float yOffset = 1.05f)
		{
			if (cell == null || cell.Entities == null || cell.Entities.Count == 0)
				return null;

			// Оборачиваем сущности клетки во временную Area, чтобы переиспользовать TryFindTopSurfaceYUnderPoint
			var tmpArea = new Area { Entities = cell.Entities };

			// Немного приподнимем луч, чтобы наверняка пройти перекрытия
			var topY = TryFindTopSurfaceYUnderPoint(tmpArea, markerPos + new Vector3(0f, 0.20f, 0f), 3.0f);
			if (topY.HasValue)
				return LiftUntilFree(new Vector3(markerPos.x, topY.Value + Mathf.Max(0.1f, yOffset), markerPos.z));

			return null;
		}

		// Надёжный телепорт игрока (без застреваний и недогрузки снапшота)
		void TeleportPlayer(BasePlayer p, Vector3 pos)
		{
			if (p == null || !p.IsConnected) return;

            if (!Cfg.AllowAdminParticipation && permission.UserHasPermission(p.UserIDString, PermAdmin)) return;
			try
			{
				// на всякий — слезть с транспорта/лошадей/сидений
				p.EnsureDismounted();
			}
			catch { }

			// убираем родителя (если был прикреплён к чему-то)
			p.SetParent(null);
			// помечаем, что клиент ожидает снапшот
			p.SetPlayerFlag(BasePlayer.PlayerFlags.ReceivingSnapshot, true);

			// сам перенос
			p.Teleport(pos);

			// прогоняем стандартный «ритуал» синхронизации
			p.ClientRPCPlayer(null, p, "StartLoading");
			p.SendNetworkUpdateImmediate();
			p.ClearEntityQueue();
			p.UpdateNetworkGroup();
			p.SendFullSnapshot();
		}

        #endregion

        #region UI

        void ShowLobbyUI(BasePlayer p, int secondsLeft)
        {
            if (!Cfg.ShowUI || p == null || !p.IsConnected) return;
            CuiHelper.DestroyUi(p, "HG_Lobby"); 

            var c = new CuiElementContainer();
            c.Add(new CuiPanel
            {
                Image = { Color = "0 0 0 0.55" },
                RectTransform = { AnchorMin = "0.42 0.90", AnchorMax = "0.58 0.98" }
            }, "Overlay", "HG_Lobby");

            c.Add(new CuiLabel
            {
                Text = { Text = $"СТАРТ ЧЕРЕЗ: {FormatTime(secondsLeft)}", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.95 0 1" },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1" }
            }, "HG_Lobby");

            CuiHelper.AddUi(p, c);
        }

        void HideLobbyUI(BasePlayer p) { if (!Cfg.ShowUI) return; CuiHelper.DestroyUi(p, "HG_Lobby"); }

        void ShowMatchHUD(BasePlayer p, int alive, int total, int secondsLeft)
        {
            if (!Cfg.ShowUI || p == null || !p.IsConnected) return;
            CuiHelper.DestroyUi(p, "HG_HUD");

            var c = new CuiElementContainer();
            c.Add(new CuiPanel
            {
                Image = { Color = "0 0 0 0.35" },
                RectTransform = { AnchorMin = "0.015 0.92", AnchorMax = "0.24 0.98" }
            }, "Overlay", "HG_HUD");

            c.Add(new CuiLabel
            {
                Text = { Text = $"ЖИВЫХ: {alive} / {total}", FontSize = 14, Align = TextAnchor.UpperLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = "0.05 0.40", AnchorMax = "0.95 0.95" }
            }, "HG_HUD");

            c.Add(new CuiLabel
            {
                Text = { Text = $"ВРЕМЯ: {FormatTime(secondsLeft)}", FontSize = 14, Align = TextAnchor.LowerLeft, Color = "1 0.95 0 1" }
            , RectTransform = { AnchorMin = "0.05 0.05", AnchorMax = "0.95 0.60" } }, "HG_HUD");

            CuiHelper.AddUi(p, c);
        }

        void HideMatchHUD(BasePlayer p) { if (!Cfg.ShowUI) return; CuiHelper.DestroyUi(p, "HG_HUD"); }

        void UpdateMatchHUD() 
        {
            if (!Cfg.ShowUI) return;
            int alive = S.Players.Count;
            foreach (var pl in S.Players.Keys.ToList())
                ShowMatchHUD(pl, alive, S.TotalAtStart, S.MatchSecondsLeft);
        }

        string FormatTime(int seconds)
        {
            if (seconds < 0) seconds = 0;
            int m = seconds / 60, s = seconds % 60;
            return $"{m:00}:{s:00}";
        }

        #endregion

        #region HG UI Interface

        private const string HG_ADMIN_UI = "HGArena_Admin";
        private const string HG_PLAYER_UI = "HGArena_Player";
        private const string HG_PLAYERS_LIST = "HGArena_PlayersList";
        private const string HG_CONFIG_UI = "HGArena_Config";
        private const string HG_TIME_UI = "HGArena_Time";
        private const string HG_MAIN_UI = "HGArena_Main";
        private const string HG_LOOT_UI = "HGArena_Loot";
        private const string HG_DOME_UI = "HGArena_Dome";

        void ShowAdminHGUI(BasePlayer player)
        {
            ShowMainAdminUI(player, "main");
        }

        void ShowMainAdminUI(BasePlayer player, string activeTab = "main")
        {
            // Закрываем все UI
            CuiHelper.DestroyUi(player, HG_MAIN_UI);
            CuiHelper.DestroyUi(player, HG_ADMIN_UI);
            CuiHelper.DestroyUi(player, HG_CONFIG_UI);
            CuiHelper.DestroyUi(player, HG_TIME_UI);
            CuiHelper.DestroyUi(player, HG_LOOT_UI);
            CuiHelper.DestroyUi(player, HG_DOME_UI);
            CuiHelper.DestroyUi(player, HG_PLAYERS_LIST);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.15 0.1", AnchorMax = "0.85 0.9" },
                CursorEnabled = true
            }, "Overlay", HG_MAIN_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "ГОЛОДНЫЕ ИГРЫ - АДМИН ПАНЕЛЬ", FontSize = 20, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 1" }
            }, HG_MAIN_UI);

            // Вкладки
            float tabWidth = 0.14f;
            string[] tabs = { "main", "players", "time", "config", "loot", "dome", "media" };
            string[] tabNames = { "Главная", "Игроки", "Время", "Настройки", "Лут", "Купол", "Медиа" };
            
            for (int i = 0; i < tabs.Length; i++)
            {
                bool isActive = tabs[i] == activeTab;
                string tabColor = isActive ? "0.3 0.6 0.3 0.9" : "0.2 0.2 0.2 0.8";
                
                container.Add(new CuiButton
                {
                    Button = { Command = $"hg.tab {tabs[i]}", Color = tabColor },
                    RectTransform = { AnchorMin = $"{0.02 + i * tabWidth} 0.85", AnchorMax = $"{0.02 + (i + 1) * tabWidth - 0.01} 0.91" },
                    Text = { Text = tabNames[i], FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, HG_MAIN_UI);
            }

            // Контент в зависимости от активной вкладки
            switch (activeTab)
            {
                case "main":
                    ShowMainTabContent(container);
                    break;
                case "players":
                    ShowPlayersTabContent(container);
                    break;
                case "time":
                    ShowTimeTabContent(container);
                    break;
                case "config":
                    ShowConfigTabContent(container);
                    break;
                case "loot":
                    ShowLootTabContent(container);
                    break;
                case "dome":
                    ShowDomeTabContent(container);
                    break;
                case "media":
                    ShowMediaTabContent(container);
                    break;
            }

            // Кнопка закрытия
            container.Add(new CuiButton
            {
                Button = { Command = "hg.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.92 0.02", AnchorMax = "0.98 0.08" },
                Text = { Text = "✕", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            CuiHelper.AddUi(player, container);
        }

        void ShowMainTabContent(CuiElementContainer container)
        {
            // Статус игры
            string status = S.Running ? "ИДЕТ МАТЧ" : (S.LobbyOpen ? "ЛОББИ ОТКРЫТО" : "ОЖИДАНИЕ");
            string statusColor = S.Running ? "1 0.2 0.2 1" : (S.LobbyOpen ? "1 1 0.2 1" : "0.7 0.7 0.7 1");
            
            container.Add(new CuiLabel
            {
                Text = { Text = $"Статус: {status} | Участников: {S.Players.Count}", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = statusColor },
                RectTransform = { AnchorMin = "0.05 0.75", AnchorMax = "0.95 0.82" }
            }, HG_MAIN_UI);

            // Режим запуска
            container.Add(new CuiLabel
            {
                Text = { Text = $"Режим: {Cfg.StartMode.ToUpper()}", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = "0.05 0.68", AnchorMax = "0.95 0.74" }
            }, HG_MAIN_UI);

            // Кнопки управления
            float btnY = 0.55f;
            float btnHeight = 0.08f;
            float btnSpacing = 0.03f;

            // Кнопка старта
            if (!S.Running && !S.LobbyOpen)
            {
                container.Add(new CuiButton
                {
                    Button = { Command = "hg.startlobby", Color = "0.2 0.6 0.2 0.8" },
                    RectTransform = { AnchorMin = $"0.25 {btnY}", AnchorMax = $"0.75 {btnY + btnHeight}" },
                    Text = { Text = "Запустить лобби", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, HG_MAIN_UI);
                btnY -= btnHeight + btnSpacing;
            }

            // Кнопка остановки
            if (S.Running || S.LobbyOpen)
            {
                container.Add(new CuiButton
                {
                    Button = { Command = "hg.stop", Color = "0.6 0.2 0.2 0.8" },
                    RectTransform = { AnchorMin = $"0.25 {btnY}", AnchorMax = $"0.75 {btnY + btnHeight}" },
                    Text = { Text = "Остановить игру", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, HG_MAIN_UI);
                btnY -= btnHeight + btnSpacing;
            }

            // Кнопки режимов
            container.Add(new CuiLabel
            {
                Text = { Text = "Режим запуска:", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = "0.1 0.35", AnchorMax = "0.9 0.4" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.mode auto", Color = Cfg.StartMode == "auto" ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = "0.15 0.28", AnchorMax = "0.35 0.34" },
                Text = { Text = "AUTO", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.mode manual", Color = Cfg.StartMode == "manual" ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = "0.4 0.28", AnchorMax = "0.6 0.34" },
                Text = { Text = "MANUAL", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.mode invite", Color = Cfg.StartMode == "invite" ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = "0.65 0.28", AnchorMax = "0.85 0.34" },
                Text = { Text = "INVITE", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            // Список участников
            container.Add(new CuiLabel
            {
                Text = { Text = "УЧАСТНИКИ:", FontSize = 14, Align = TextAnchor.UpperCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.18", AnchorMax = "0.9 0.24" }
            }, HG_MAIN_UI);

            string participantsList = "";
            if (S.Players.Count > 0)
            {
                var names = S.Players.Keys.Select(p => p.displayName).Take(8).ToList();
                participantsList = string.Join(", ", names);
                if (S.Players.Count > 8) participantsList += $" и еще {S.Players.Count - 8}...";
            }
            else
            {
                participantsList = "Нет участников";
            }

            container.Add(new CuiLabel
            {
                Text = { Text = participantsList, FontSize = 11, Align = TextAnchor.UpperCenter, Color = "0.9 0.9 0.9 1" },
                RectTransform = { AnchorMin = "0.05 0.1", AnchorMax = "0.95 0.18" }
            }, HG_MAIN_UI);
        }

        void ShowPlayersTabContent(CuiElementContainer container)
        {
            // Список всех игроков (включая админов если разрешено)
            var allPlayers = BasePlayer.activePlayerList.Where(p => Cfg.AllowAdminParticipation || !permission.UserHasPermission(p.UserIDString, PermAdmin)).ToList();
            
            container.Add(new CuiLabel
            {
                Text = { Text = "УПРАВЛЕНИЕ ИГРОКАМИ", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.75", AnchorMax = "0.9 0.82" }
            }, HG_MAIN_UI);

            float startY = 0.7f;
            float itemHeight = 0.05f;
            float spacing = 0.01f;

            for (int i = 0; i < allPlayers.Count && i < 12; i++)
            {
                var player = allPlayers[i];
                float y = startY - i * (itemHeight + spacing);

                bool isParticipant = S.Players.ContainsKey(player);
                bool isSelected = S.SelectedPlayers.Contains(player.userID);
                bool isInvited = S.InvitedPlayers.Contains(player.userID);

                string bgColor = isParticipant ? "0.2 0.6 0.2 0.4" : "0.2 0.2 0.2 0.4";

                // Панель игрока
                container.Add(new CuiPanel
                {
                    Image = { Color = bgColor },
                    RectTransform = { AnchorMin = $"0.05 {y}", AnchorMax = $"0.95 {y + itemHeight}" }
                }, HG_MAIN_UI, $"Player_{i}");

                // Имя игрока
                container.Add(new CuiLabel
                {
                    Text = { Text = player.displayName, FontSize = 11, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                    RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.4 1" }
                }, $"Player_{i}");

                // Статус
                string status = isParticipant ? "УЧАСТВУЕТ" : (isSelected ? "ВЫБРАН" : (isInvited ? "ПРИГЛАШЕН" : ""));
                if (!string.IsNullOrEmpty(status))
                {
                    container.Add(new CuiLabel
                    {
                        Text = { Text = status, FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 0.2 1" },
                        RectTransform = { AnchorMin = "0.4 0", AnchorMax = "0.6 1" }
                    }, $"Player_{i}");
                }

                // Кнопки действий
                if (Cfg.StartMode == "manual" && !isParticipant)
                {
                    string selectText = isSelected ? "Убрать" : "Выбрать";
                    string selectColor = isSelected ? "0.6 0.2 0.2 0.8" : "0.2 0.6 0.2 0.8";
                    
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"hg.select {player.userID}", Color = selectColor },
                        RectTransform = { AnchorMin = "0.65 0.1", AnchorMax = "0.8 0.9" },
                        Text = { Text = selectText, FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, $"Player_{i}");
                }

                if (Cfg.StartMode == "invite" && !isParticipant)
                {
                    string inviteText = isInvited ? "Отозвать" : "Пригласить";
                    string inviteColor = isInvited ? "0.6 0.2 0.2 0.8" : "0.2 0.4 0.6 0.8";
                    
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"hg.invite {player.userID}", Color = inviteColor },
                        RectTransform = { AnchorMin = "0.65 0.1", AnchorMax = "0.8 0.9" },
                        Text = { Text = inviteText, FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, $"Player_{i}");
                }

                // Кнопка исключения (если участвует)
                if (isParticipant)
                {
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"hg.kick {player.userID}", Color = "0.6 0.2 0.2 0.8" },
                        RectTransform = { AnchorMin = "0.82 0.1", AnchorMax = "0.95 0.9" },
                        Text = { Text = "Исключить", FontSize = 8, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, $"Player_{i}");
                }
            }
        }

        void ShowTimeTabContent(CuiElementContainer container)
        {
            container.Add(new CuiLabel
            {
                Text = { Text = "НАСТРОЙКИ ВРЕМЕНИ", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.75", AnchorMax = "0.9 0.82" }
            }, HG_MAIN_UI);

            float yPos = 0.65f;
            float spacing = 0.1f;

            // Время лобби
            container.Add(new CuiLabel
            {
                Text = { Text = $"Время лобби: {Cfg.LobbySeconds} сек", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            // Кнопки изменения времени лобби
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setlobby 30", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "30с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setlobby 60", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "60с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setlobby 120", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "120с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Максимальное время матча
            container.Add(new CuiLabel
            {
                Text = { Text = $"Время матча: {Cfg.MaxMatchSeconds / 60} мин", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            // Кнопки изменения времени матча
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setmatch 900", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "15м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setmatch 1800", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "30м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setmatch 3600", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "60м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Автозапуск
            container.Add(new CuiLabel
            {
                Text = { Text = $"Автозапуск: {(Cfg.AutoStartSeconds > 0 ? $"{Cfg.AutoStartSeconds / 60:0} мин" : "выкл")}", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            // Кнопки автозапуска
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setauto 0", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "Выкл", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setauto 1800", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "30м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setauto 3600", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "60м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);
        }

        void ShowConfigTabContent(CuiElementContainer container)
        {
            container.Add(new CuiLabel
            {
                Text = { Text = "ОСНОВНЫЕ НАСТРОЙКИ", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.75", AnchorMax = "0.9 0.82" }
            }, HG_MAIN_UI);

            float yPos = 0.65f;
            float spacing = 0.08f;

            // Минимум игроков
            container.Add(new CuiLabel
            {
                Text = { Text = $"Минимум игроков: {Cfg.MinPlayers}", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            // Кнопки изменения минимума игроков
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 2", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.72 {yPos + 0.03}" },
                Text = { Text = "2", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 4", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.74 {yPos - 0.03}", AnchorMax = $"0.81 {yPos + 0.03}" },
                Text = { Text = "4", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 6", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.83 {yPos - 0.03}", AnchorMax = $"0.9 {yPos + 0.03}" },
                Text = { Text = "6", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 8", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.92 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "8", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Участие админов
            container.Add(new CuiLabel
            {
                Text = { Text = "Участие админов:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            string adminText = Cfg.AllowAdminParticipation ? "РАЗРЕШЕНО" : "ЗАПРЕЩЕНО";
            string adminColor = Cfg.AllowAdminParticipation ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggleadmin", Color = adminColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = adminText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Показывать UI
            container.Add(new CuiLabel
            {
                Text = { Text = "Показывать UI:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            string uiText = Cfg.ShowUI ? "ВКЛЮЧЕНО" : "ВЫКЛЮЧЕНО";
            string uiColor = Cfg.ShowUI ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggleui", Color = uiColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = uiText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Анонсировать смерти
            container.Add(new CuiLabel
            {
                Text = { Text = "Анонсировать смерти:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            string deathText = Cfg.AnnounceDeaths ? "ВКЛЮЧЕНО" : "ВЫКЛЮЧЕНО";
            string deathColor = Cfg.AnnounceDeaths ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggledeaths", Color = deathColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = deathText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Аирдропы
            container.Add(new CuiLabel
            {
                Text = { Text = "Аирдропы:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            string airdropText = Cfg.AirDropEnabled ? "ВКЛЮЧЕНЫ" : "ВЫКЛЮЧЕНЫ";
            string airdropColor = Cfg.AirDropEnabled ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggleairdrops", Color = airdropColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = airdropText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);
        }

        void ShowLootTabContent(CuiElementContainer container)
        {
            container.Add(new CuiLabel
            {
                Text = { Text = "НАСТРОЙКИ ЛУТА", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.75", AnchorMax = "0.9 0.82" }
            }, HG_MAIN_UI);

            container.Add(new CuiLabel
            {
                Text = { Text = "Лут в аирдропах:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = "0.1 0.65", AnchorMax = "0.9 0.7" }
            }, HG_MAIN_UI);

            float yPos = 0.6f;
            float itemHeight = 0.05f;
            float spacing = 0.01f;
            int i = 0;

            foreach (var lootItem in Cfg.AirDropLoot)
            {
                if (i >= 8) break; // Показываем только первые 8 предметов
                
                float y = yPos - i * (itemHeight + spacing);

                // Панель предмета
                container.Add(new CuiPanel
                {
                    Image = { Color = "0.2 0.2 0.2 0.4" },
                    RectTransform = { AnchorMin = $"0.1 {y}", AnchorMax = $"0.9 {y + itemHeight}" }
                }, HG_MAIN_UI, $"LootItem_{i}");

                // Название предмета
                container.Add(new CuiLabel
                {
                    Text = { Text = lootItem.Key, FontSize = 11, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                    RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.5 1" }
                }, $"LootItem_{i}");

                // Количество
                container.Add(new CuiLabel
                {
                    Text = { Text = $"x{lootItem.Value}", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 0.2 1" },
                    RectTransform = { AnchorMin = "0.5 0", AnchorMax = "0.65 1" }
                }, $"LootItem_{i}");

                // Кнопки изменения количества
                container.Add(new CuiButton
                {
                    Button = { Command = $"hg.loot.decrease {lootItem.Key}", Color = "0.6 0.2 0.2 0.8" },
                    RectTransform = { AnchorMin = "0.67 0.1", AnchorMax = "0.75 0.9" },
                    Text = { Text = "-", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, $"LootItem_{i}");

                container.Add(new CuiButton
                {
                    Button = { Command = $"hg.loot.increase {lootItem.Key}", Color = "0.2 0.6 0.2 0.8" },
                    RectTransform = { AnchorMin = "0.77 0.1", AnchorMax = "0.85 0.9" },
                    Text = { Text = "+", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, $"LootItem_{i}");

                // Кнопка удаления
                container.Add(new CuiButton
                {
                    Button = { Command = $"hg.loot.remove {lootItem.Key}", Color = "0.4 0.2 0.2 0.8" },
                    RectTransform = { AnchorMin = "0.87 0.1", AnchorMax = "0.95 0.9" },
                    Text = { Text = "✕", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, $"LootItem_{i}");

                i++;
            }

            // Кнопка добавления нового предмета
            container.Add(new CuiButton
            {
                Button = { Command = "hg.loot.add", Color = "0.2 0.4 0.6 0.8" },
                RectTransform = { AnchorMin = "0.3 0.15", AnchorMax = "0.7 0.22" },
                Text = { Text = "Добавить предмет", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);
        }

        void ShowDomeTabContent(CuiElementContainer container)
        {
            container.Add(new CuiLabel
            {
                Text = { Text = "НАСТРОЙКИ КУПОЛА", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.75", AnchorMax = "0.9 0.82" }
            }, HG_MAIN_UI);

            float yPos = 0.65f;
            float spacing = 0.08f;

            // Задержка появления купола
            container.Add(new CuiLabel
            {
                Text = { Text = $"Задержка появления: {Cfg.CircleDelayAfterStart} сек", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.delay 10", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "10с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.delay 15", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "15с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.delay 30", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "30с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Стартовый радиус
            container.Add(new CuiLabel
            {
                Text = { Text = $"Стартовый радиус: {Cfg.CircleStartRadius} м", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.radius 150", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "150м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.radius 220", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "220м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.radius 300", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "300м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Минимальный радиус
            container.Add(new CuiLabel
            {
                Text = { Text = $"Минимальный радиус: {Cfg.CircleMinRadius} м", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.minradius 20", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "20м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.minradius 30", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "30м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.minradius 50", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "50м", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Видимый купол
            container.Add(new CuiLabel
            {
                Text = { Text = "Видимый купол:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            string domeText = Cfg.VisibleShrinkingDome ? "ВКЛЮЧЕН" : "ВЫКЛЮЧЕН";
            string domeColor = Cfg.VisibleShrinkingDome ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.toggle", Color = domeColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = domeText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Урон радиации
            container.Add(new CuiLabel
            {
                Text = { Text = $"Урон радиации: {Cfg.RadAmount} HP/тик", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.damage 2", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "2", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.damage 4", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "4", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.dome.damage 8", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "8", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);
        }

        void ShowMediaTabContent(CuiElementContainer container)
        {
            container.Add(new CuiLabel
            {
                Text = { Text = "НАСТРОЙКИ МЕДИА", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0.1 0.75", AnchorMax = "0.9 0.82" }
            }, HG_MAIN_UI);

            float yPos = 0.65f;
            float spacing = 0.08f;

            // Включить медиа
            container.Add(new CuiLabel
            {
                Text = { Text = "Воспроизведение медиа:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            string mediaText = Cfg.MediaEnabled ? "ВКЛЮЧЕНО" : "ВЫКЛЮЧЕНО";
            string mediaColor = Cfg.MediaEnabled ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.toggle", Color = mediaColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = mediaText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Метод воспроизведения
            container.Add(new CuiLabel
            {
                Text = { Text = "Метод воспроизведения:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.method console", Color = Cfg.MediaMethod.ToLower() == "console" ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.8 {yPos + 0.03}" },
                Text = { Text = "Консоль", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.method browser", Color = Cfg.MediaMethod.ToLower() == "browser" ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.82 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.03}" },
                Text = { Text = "Браузер", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // URL приветственного медиа
            container.Add(new CuiLabel
            {
                Text = { Text = "Приветственное медиа:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.01}" }
            }, HG_MAIN_UI);

            string welcomeUrl = Cfg.WelcomeMediaUrl.Length > 50 ? Cfg.WelcomeMediaUrl.Substring(0, 47) + "..." : Cfg.WelcomeMediaUrl;
            container.Add(new CuiLabel
            {
                Text = { Text = welcomeUrl, FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.05}", AnchorMax = $"0.7 {yPos - 0.01}" }
            }, HG_MAIN_UI);

            // Кнопки для смены формата приветственного медиа
            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.welcome mp3", Color = Cfg.WelcomeMediaUrl.Contains(".mp3") ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.72 {yPos - 0.05}", AnchorMax = $"0.82 {yPos - 0.01}" },
                Text = { Text = "MP3", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.welcome mp4", Color = Cfg.WelcomeMediaUrl.Contains(".mp4") ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.84 {yPos - 0.05}", AnchorMax = $"0.94 {yPos - 0.01}" },
                Text = { Text = "MP4", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Длительность приветственного медиа
            container.Add(new CuiLabel
            {
                Text = { Text = $"Длительность приветствия: {Cfg.WelcomeMediaDuration} сек", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.welcomeduration 8", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.72 {yPos + 0.03}" },
                Text = { Text = "8с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.welcomeduration 12", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.74 {yPos - 0.03}", AnchorMax = $"0.82 {yPos + 0.03}" },
                Text = { Text = "12с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.welcomeduration 15", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.84 {yPos - 0.03}", AnchorMax = $"0.92 {yPos + 0.03}" },
                Text = { Text = "15с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // URL медиа окончания
            container.Add(new CuiLabel
            {
                Text = { Text = "Медиа окончания матча:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.95 {yPos + 0.01}" }
            }, HG_MAIN_UI);

            string endUrl = Cfg.EndMediaUrl.Length > 50 ? Cfg.EndMediaUrl.Substring(0, 47) + "..." : Cfg.EndMediaUrl;
            container.Add(new CuiLabel
            {
                Text = { Text = endUrl, FontSize = 10, Align = TextAnchor.MiddleLeft, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.05}", AnchorMax = $"0.7 {yPos - 0.01}" }
            }, HG_MAIN_UI);

            // Кнопки для смены формата медиа окончания
            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.end mp3", Color = Cfg.EndMediaUrl.Contains(".mp3") ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.72 {yPos - 0.05}", AnchorMax = $"0.82 {yPos - 0.01}" },
                Text = { Text = "MP3", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.end mp4", Color = Cfg.EndMediaUrl.Contains(".mp4") ? "0.4 0.6 0.4 0.8" : "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.84 {yPos - 0.05}", AnchorMax = $"0.94 {yPos - 0.01}" },
                Text = { Text = "MP4", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Длительность медиа окончания
            container.Add(new CuiLabel
            {
                Text = { Text = $"Длительность окончания: {Cfg.EndMediaDuration} сек", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.1 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.endduration 8", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.72 {yPos + 0.03}" },
                Text = { Text = "8с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.endduration 10", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.74 {yPos - 0.03}", AnchorMax = $"0.82 {yPos + 0.03}" },
                Text = { Text = "10с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.endduration 15", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.84 {yPos - 0.03}", AnchorMax = $"0.92 {yPos + 0.03}" },
                Text = { Text = "15с", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);

            yPos -= spacing;

            // Кнопка тестирования
            container.Add(new CuiButton
            {
                Button = { Command = "hg.media.test", Color = "0.2 0.4 0.6 0.8" },
                RectTransform = { AnchorMin = $"0.3 {yPos - 0.03}", AnchorMax = $"0.7 {yPos + 0.03}" },
                Text = { Text = "Тестировать приветствие", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_MAIN_UI);
        }

        void ShowPlayerHGUI(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, HG_PLAYER_UI);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.9" },
                RectTransform = { AnchorMin = "0.3 0.3", AnchorMax = "0.7 0.7" },
                CursorEnabled = true
            }, "Overlay", HG_PLAYER_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "ГОЛОДНЫЕ ИГРЫ", FontSize = 18, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.85", AnchorMax = "1 0.95" }
            }, HG_PLAYER_UI);

            // Статус
            string status = S.Running ? "ИДЕТ МАТЧ" : (S.LobbyOpen ? "ЛОББИ ОТКРЫТО" : "ОЖИДАНИЕ");
            string statusColor = S.Running ? "1 0.2 0.2 1" : (S.LobbyOpen ? "1 1 0.2 1" : "0.7 0.7 0.7 1");
            
            container.Add(new CuiLabel
            {
                Text = { Text = status, FontSize = 14, Align = TextAnchor.MiddleCenter, Color = statusColor },
                RectTransform = { AnchorMin = "0.1 0.7", AnchorMax = "0.9 0.8" }
            }, HG_PLAYER_UI);

            // Информация об участии
            bool isParticipant = S.Players.ContainsKey(player);
            bool canJoin = S.LobbyOpen && (Cfg.StartMode != "invite" || S.InvitedPlayers.Contains(player.userID));

            string participantStatus = isParticipant ? "Вы участвуете в игре" : 
                                     (canJoin ? "Вы можете присоединиться" : "Вы не можете участвовать");
            string participantColor = isParticipant ? "0.2 1 0.2 1" : (canJoin ? "1 1 0.2 1" : "1 0.2 0.2 1");

            container.Add(new CuiLabel
            {
                Text = { Text = participantStatus, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = participantColor },
                RectTransform = { AnchorMin = "0.1 0.55", AnchorMax = "0.9 0.65" }
            }, HG_PLAYER_UI);

            // Кнопки действий
            if (S.LobbyOpen && !isParticipant && canJoin)
            {
                container.Add(new CuiButton
                {
                    Button = { Command = "hgjoin", Color = "0.2 0.6 0.2 0.8" },
                    RectTransform = { AnchorMin = "0.2 0.35", AnchorMax = "0.8 0.45" },
                    Text = { Text = "Присоединиться", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, HG_PLAYER_UI);
            }

            if (isParticipant && (S.LobbyOpen || S.Running))
            {
                container.Add(new CuiButton
                {
                    Button = { Command = "leave", Color = "0.6 0.2 0.2 0.8" },
                    RectTransform = { AnchorMin = "0.2 0.35", AnchorMax = "0.8 0.45" },
                    Text = { Text = "Покинуть игру", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                }, HG_PLAYER_UI);
            }

            // Участники
            container.Add(new CuiLabel
            {
                Text = { Text = $"Участников: {S.Players.Count}", FontSize = 11, Align = TextAnchor.MiddleCenter, Color = "0.8 0.8 0.8 1" },
                RectTransform = { AnchorMin = "0.1 0.25", AnchorMax = "0.9 0.32" }
            }, HG_PLAYER_UI);

            // Кнопка закрытия
            container.Add(new CuiButton
            {
                Button = { Command = "hg.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.85 0.05", AnchorMax = "0.95 0.15" },
                Text = { Text = "✕", FontSize = 14, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_PLAYER_UI);

            CuiHelper.AddUi(player, container);
        }

        void ShowPlayersListUI(BasePlayer admin)
        {
            CuiHelper.DestroyUi(admin, HG_PLAYERS_LIST);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.1 0.1", AnchorMax = "0.9 0.9" },
                CursorEnabled = true
            }, "Overlay", HG_PLAYERS_LIST);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "УПРАВЛЕНИЕ ИГРОКАМИ", FontSize = 18, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 1" }
            }, HG_PLAYERS_LIST);

            // Кнопка назад
            container.Add(new CuiButton
            {
                Button = { Command = "hg.admin", Color = "0.4 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = "0.02 0.92", AnchorMax = "0.12 0.98" },
                Text = { Text = "← Назад", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_PLAYERS_LIST);

            // Список всех игроков (включая админов если разрешено)
            var allPlayers = BasePlayer.activePlayerList.Where(p => Cfg.AllowAdminParticipation || !permission.UserHasPermission(p.UserIDString, PermAdmin)).ToList();
            
            float startY = 0.85f;
            float itemHeight = 0.06f;
            float spacing = 0.01f;

            for (int i = 0; i < allPlayers.Count && i < 12; i++)
            {
                var player = allPlayers[i];
                float y = startY - i * (itemHeight + spacing);

                bool isParticipant = S.Players.ContainsKey(player);
                bool isSelected = S.SelectedPlayers.Contains(player.userID);
                bool isInvited = S.InvitedPlayers.Contains(player.userID);

                string bgColor = isParticipant ? "0.2 0.6 0.2 0.6" : "0.2 0.2 0.2 0.6";

                // Панель игрока
                container.Add(new CuiPanel
                {
                    Image = { Color = bgColor },
                    RectTransform = { AnchorMin = $"0.05 {y}", AnchorMax = $"0.95 {y + itemHeight}" }
                }, HG_PLAYERS_LIST, $"Player_{i}");

                // Имя игрока
                container.Add(new CuiLabel
                {
                    Text = { Text = player.displayName, FontSize = 12, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                    RectTransform = { AnchorMin = "0.02 0", AnchorMax = "0.4 1" }
                }, $"Player_{i}");

                // Статус
                string status = isParticipant ? "УЧАСТВУЕТ" : (isSelected ? "ВЫБРАН" : (isInvited ? "ПРИГЛАШЕН" : ""));
                if (!string.IsNullOrEmpty(status))
                {
                    container.Add(new CuiLabel
                    {
                        Text = { Text = status, FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 0.2 1" },
                        RectTransform = { AnchorMin = "0.4 0", AnchorMax = "0.6 1" }
                    }, $"Player_{i}");
                }

                // Кнопки действий
                if (Cfg.StartMode == "manual" && !isParticipant)
                {
                    string selectText = isSelected ? "Убрать" : "Выбрать";
                    string selectColor = isSelected ? "0.6 0.2 0.2 0.8" : "0.2 0.6 0.2 0.8";
                    
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"hg.select {player.userID}", Color = selectColor },
                        RectTransform = { AnchorMin = "0.65 0.1", AnchorMax = "0.8 0.9" },
                        Text = { Text = selectText, FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, $"Player_{i}");
                }

                if (Cfg.StartMode == "invite" && !isParticipant)
                {
                    string inviteText = isInvited ? "Отозвать" : "Пригласить";
                    string inviteColor = isInvited ? "0.6 0.2 0.2 0.8" : "0.2 0.4 0.6 0.8";
                    
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"hg.invite {player.userID}", Color = inviteColor },
                        RectTransform = { AnchorMin = "0.65 0.1", AnchorMax = "0.8 0.9" },
                        Text = { Text = inviteText, FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, $"Player_{i}");
                }

                // Кнопка исключения (если участвует)
                if (isParticipant)
                {
                    container.Add(new CuiButton
                    {
                        Button = { Command = $"hg.kick {player.userID}", Color = "0.6 0.2 0.2 0.8" },
                        RectTransform = { AnchorMin = "0.82 0.1", AnchorMax = "0.95 0.9" },
                        Text = { Text = "Исключить", FontSize = 9, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
                    }, $"Player_{i}");
                }
            }

            CuiHelper.AddUi(admin, container);
        }

        void ShowTimeConfigUI(BasePlayer admin)
        {
            CuiHelper.DestroyUi(admin, HG_TIME_UI);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.25 0.2", AnchorMax = "0.75 0.8" },
                CursorEnabled = true
            }, "Overlay", HG_TIME_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "НАСТРОЙКИ ВРЕМЕНИ", FontSize = 18, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.9", AnchorMax = "1 1" }
            }, HG_TIME_UI);

            // Кнопка назад
            container.Add(new CuiButton
            {
                Button = { Command = "hg.admin", Color = "0.4 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = "0.02 0.9", AnchorMax = "0.15 0.98" },
                Text = { Text = "← Назад", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            float yPos = 0.8f;
            float spacing = 0.08f;

            // Время лобби
            container.Add(new CuiLabel
            {
                Text = { Text = $"Время лобби: {Cfg.LobbySeconds} сек", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_TIME_UI);

            // Кнопки изменения времени лобби
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setlobby 30", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "30с", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setlobby 60", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "60с", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setlobby 120", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = "120с", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            yPos -= spacing;

            // Максимальное время матча
            container.Add(new CuiLabel
            {
                Text = { Text = $"Время матча: {Cfg.MaxMatchSeconds / 60} мин", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_TIME_UI);

            // Кнопки изменения времени матча
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setmatch 900", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "15м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setmatch 1800", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "30м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setmatch 3600", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = "60м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            yPos -= spacing;

            // Автозапуск
            container.Add(new CuiLabel
            {
                Text = { Text = $"Автозапуск: {(Cfg.AutoStartSeconds > 0 ? $"{Cfg.AutoStartSeconds / 60:0} мин" : "выкл")}", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_TIME_UI);

            // Кнопки автозапуска
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setauto 0", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.75 {yPos + 0.03}" },
                Text = { Text = "Выкл", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setauto 1800", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.77 {yPos - 0.03}", AnchorMax = $"0.87 {yPos + 0.03}" },
                Text = { Text = "30м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setauto 3600", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.89 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = "60м", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            // Кнопка закрытия
            container.Add(new CuiButton
            {
                Button = { Command = "hg.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.85 0.05", AnchorMax = "0.95 0.15" },
                Text = { Text = "✕", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_TIME_UI);

            CuiHelper.AddUi(admin, container);
        }

        void ShowConfigUI(BasePlayer admin)
        {
            CuiHelper.DestroyUi(admin, HG_CONFIG_UI);

            var container = new CuiElementContainer();

            // Главная панель
            container.Add(new CuiPanel
            {
                Image = { Color = "0.1 0.1 0.1 0.95" },
                RectTransform = { AnchorMin = "0.2 0.15", AnchorMax = "0.8 0.85" },
                CursorEnabled = true
            }, "Overlay", HG_CONFIG_UI);

            // Заголовок
            container.Add(new CuiLabel
            {
                Text = { Text = "НАСТРОЙКИ КОНФИГА", FontSize = 18, Align = TextAnchor.MiddleCenter, Color = "1 0.8 0 1" },
                RectTransform = { AnchorMin = "0 0.92", AnchorMax = "1 1" }
            }, HG_CONFIG_UI);

            // Кнопка назад
            container.Add(new CuiButton
            {
                Button = { Command = "hg.admin", Color = "0.4 0.4 0.4 0.8" },
                RectTransform = { AnchorMin = "0.02 0.92", AnchorMax = "0.15 0.98" },
                Text = { Text = "← Назад", FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            float yPos = 0.85f;
            float spacing = 0.08f;

            // Минимум игроков
            container.Add(new CuiLabel
            {
                Text = { Text = $"Минимум игроков: {Cfg.MinPlayers}", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_CONFIG_UI);

            // Кнопки изменения минимума игроков
            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 2", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.72 {yPos + 0.03}" },
                Text = { Text = "2", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 4", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.74 {yPos - 0.03}", AnchorMax = $"0.81 {yPos + 0.03}" },
                Text = { Text = "4", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 6", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.83 {yPos - 0.03}", AnchorMax = $"0.9 {yPos + 0.03}" },
                Text = { Text = "6", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            container.Add(new CuiButton
            {
                Button = { Command = "hg.setminplayers 8", Color = "0.3 0.3 0.3 0.8" },
                RectTransform = { AnchorMin = $"0.92 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = "8", FontSize = 10, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            yPos -= spacing;

            // Участие админов
            container.Add(new CuiLabel
            {
                Text = { Text = "Участие админов:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_CONFIG_UI);

            string adminText = Cfg.AllowAdminParticipation ? "РАЗРЕШЕНО" : "ЗАПРЕЩЕНО";
            string adminColor = Cfg.AllowAdminParticipation ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggleadmin", Color = adminColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = adminText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            yPos -= spacing;

            // Показывать UI
            container.Add(new CuiLabel
            {
                Text = { Text = "Показывать UI:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_CONFIG_UI);

            string uiText = Cfg.ShowUI ? "ВКЛЮЧЕНО" : "ВЫКЛЮЧЕНО";
            string uiColor = Cfg.ShowUI ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggleui", Color = uiColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = uiText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            yPos -= spacing;

            // Анонсировать смерти
            container.Add(new CuiLabel
            {
                Text = { Text = "Анонсировать смерти:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_CONFIG_UI);

            string deathText = Cfg.AnnounceDeaths ? "ВКЛЮЧЕНО" : "ВЫКЛЮЧЕНО";
            string deathColor = Cfg.AnnounceDeaths ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggledeaths", Color = deathColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = deathText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            yPos -= spacing;

            // Аирдропы
            container.Add(new CuiLabel
            {
                Text = { Text = "Аирдропы:", FontSize = 14, Align = TextAnchor.MiddleLeft, Color = "1 1 1 1" },
                RectTransform = { AnchorMin = $"0.05 {yPos - 0.03}", AnchorMax = $"0.6 {yPos + 0.03}" }
            }, HG_CONFIG_UI);

            string airdropText = Cfg.AirDropEnabled ? "ВКЛЮЧЕНЫ" : "ВЫКЛЮЧЕНЫ";
            string airdropColor = Cfg.AirDropEnabled ? "0.2 0.6 0.2 0.8" : "0.6 0.2 0.2 0.8";

            container.Add(new CuiButton
            {
                Button = { Command = "hg.toggleairdrops", Color = airdropColor },
                RectTransform = { AnchorMin = $"0.65 {yPos - 0.03}", AnchorMax = $"0.98 {yPos + 0.03}" },
                Text = { Text = airdropText, FontSize = 12, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            // Кнопка закрытия
            container.Add(new CuiButton
            {
                Button = { Command = "hg.close", Color = "0.6 0.2 0.2 0.8" },
                RectTransform = { AnchorMin = "0.85 0.05", AnchorMax = "0.95 0.15" },
                Text = { Text = "✕", FontSize = 16, Align = TextAnchor.MiddleCenter, Color = "1 1 1 1" }
            }, HG_CONFIG_UI);

            CuiHelper.AddUi(admin, container);
        }

        // Консольные команды для UI
        [ConsoleCommand("hg.startlobby")]
        private void CmdUIStartLobby(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            StartLobby(Cfg.LobbySeconds);
            ShowAdminHGUI(player);
        }

        [ConsoleCommand("hg.stop")]
        private void CmdUIStop(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            EndAll(true);
            ShowAdminHGUI(player);
        }

        [ConsoleCommand("hg.players")]
        private void CmdUIPlayers(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            ShowPlayersListUI(player);
        }

        [ConsoleCommand("hg.admin")]
        private void CmdUIAdmin(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            ShowAdminHGUI(player);
        }

        [ConsoleCommand("hg.mode")]
        private void CmdUIMode(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string mode = arg.Args[0].ToLower();
            if (mode == "auto" || mode == "manual" || mode == "invite")
            {
                Cfg.StartMode = mode;
                SaveConfig();
                
                // Очищаем списки при смене режима
                S.SelectedPlayers.Clear();
                S.InvitedPlayers.Clear();
                
                Msg(player, $"Режим изменен на: {mode.ToUpper()}");
                ShowAdminHGUI(player);
            }
        }

        [ConsoleCommand("hg.select")]
        private void CmdUISelect(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (ulong.TryParse(arg.Args[0], out ulong userId))
            {
                if (S.SelectedPlayers.Contains(userId))
                    S.SelectedPlayers.Remove(userId);
                else
                    S.SelectedPlayers.Add(userId);
                
                ShowPlayersListUI(player);
            }
        }

        [ConsoleCommand("hg.invite")]
        private void CmdUIInvite(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (ulong.TryParse(arg.Args[0], out ulong userId))
            {
                var target = BasePlayer.FindByID(userId);
                if (target != null)
                {
                    if (S.InvitedPlayers.Contains(userId))
                    {
                        S.InvitedPlayers.Remove(userId);
                        Msg(target, "Ваше приглашение в Голодные Игры отозвано.");
                    }
                    else
                    {
                        S.InvitedPlayers.Add(userId);
                        Msg(target, "Вы приглашены в Голодные Игры! Используйте /hgjoin для участия.");
                    }
                }
                
                ShowPlayersListUI(player);
            }
        }

        [ConsoleCommand("hg.kick")]
        private void CmdUIKick(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (ulong.TryParse(arg.Args[0], out ulong userId))
            {
                var target = BasePlayer.FindByID(userId);
                if (target != null && S.Players.ContainsKey(target))
                {
                    ForceLeave(target);
                    Msg(player, $"Игрок {target.displayName} исключен из игры.");
                }
                
                ShowPlayersListUI(player);
            }
        }

        [ConsoleCommand("hg.timeconfig")]
        private void CmdUITimeConfig(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            ShowTimeConfigUI(player);
        }

        [ConsoleCommand("hg.config")]
        private void CmdUIConfig(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            
            ShowConfigUI(player);
        }

        // Команды настройки времени
        [ConsoleCommand("hg.setlobby")]
        private void CmdSetLobby(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (int.TryParse(arg.Args[0], out int seconds))
            {
                Cfg.LobbySeconds = Mathf.Max(10, seconds);
                SaveConfig();
                Msg(player, $"Время лобби установлено: {Cfg.LobbySeconds} сек");
                ShowMainAdminUI(player, "time");
            }
        }

        [ConsoleCommand("hg.setmatch")]
        private void CmdSetMatch(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (int.TryParse(arg.Args[0], out int seconds))
            {
                Cfg.MaxMatchSeconds = Mathf.Max(300, seconds);
                SaveConfig();
                Msg(player, $"Время матча установлено: {Cfg.MaxMatchSeconds / 60} мин");
                ShowMainAdminUI(player, "time");
            }
        }

        [ConsoleCommand("hg.setauto")]
        private void CmdSetAuto(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (float.TryParse(arg.Args[0], out float seconds))
            {
                Cfg.AutoStartSeconds = Mathf.Max(0f, seconds);
                SaveConfig();
                string msg = Cfg.AutoStartSeconds > 0 ? $"Автозапуск установлен: {Cfg.AutoStartSeconds / 60:0} мин" : "Автозапуск отключен";
                Msg(player, msg);
                ShowMainAdminUI(player, "time");
            }
        }

        // Команды настройки конфига
        [ConsoleCommand("hg.setminplayers")]
        private void CmdSetMinPlayers(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (int.TryParse(arg.Args[0], out int count))
            {
                Cfg.MinPlayers = Mathf.Max(1, count);
                SaveConfig();
                Msg(player, $"Минимум игроков установлен: {Cfg.MinPlayers}");
                ShowMainAdminUI(player, "config");
            }
        }

        [ConsoleCommand("hg.toggleadmin")]
        private void CmdToggleAdmin(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;

            Cfg.AllowAdminParticipation = !Cfg.AllowAdminParticipation;
            SaveConfig();
            string status = Cfg.AllowAdminParticipation ? "разрешено" : "запрещено";
            Msg(player, $"Участие админов {status}");
            ShowConfigUI(player);
        }

        [ConsoleCommand("hg.toggleui")]
        private void CmdToggleUI(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;

            Cfg.ShowUI = !Cfg.ShowUI;
            SaveConfig();
            string status = Cfg.ShowUI ? "включен" : "выключен";
            Msg(player, $"UI интерфейс {status}");
            ShowConfigUI(player);
        }

        [ConsoleCommand("hg.toggledeaths")]
        private void CmdToggleDeaths(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;

            Cfg.AnnounceDeaths = !Cfg.AnnounceDeaths;
            SaveConfig();
            string status = Cfg.AnnounceDeaths ? "включены" : "выключены";
            Msg(player, $"Анонсы смертей {status}");
            ShowConfigUI(player);
        }

        [ConsoleCommand("hg.toggleairdrops")]
        private void CmdToggleAirdrops(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;

            Cfg.AirDropEnabled = !Cfg.AirDropEnabled;
            SaveConfig();
            string status = Cfg.AirDropEnabled ? "включены" : "выключены";
            Msg(player, $"Аирдропы {status}");
            ShowConfigUI(player);
        }

        [ConsoleCommand("hg.tab")]
        private void CmdUITab(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            ShowMainAdminUI(player, arg.Args[0]);
        }

        [ConsoleCommand("hg.close")]
        private void CmdUIClose(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null) return;
            
            CuiHelper.DestroyUi(player, HG_ADMIN_UI);
            CuiHelper.DestroyUi(player, HG_PLAYER_UI);
            CuiHelper.DestroyUi(player, HG_PLAYERS_LIST);
            CuiHelper.DestroyUi(player, HG_CONFIG_UI);
            CuiHelper.DestroyUi(player, HG_TIME_UI);
            CuiHelper.DestroyUi(player, HG_MAIN_UI);
            CuiHelper.DestroyUi(player, HG_LOOT_UI);
            CuiHelper.DestroyUi(player, HG_DOME_UI);
        }

        // Команды для медиа настроек
        [ConsoleCommand("hg.media.toggle")]
        private void CmdMediaToggle(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;

            Cfg.MediaEnabled = !Cfg.MediaEnabled;
            SaveConfig();
            string status = Cfg.MediaEnabled ? "включено" : "выключено";
            Msg(player, $"Воспроизведение медиа {status}");
            ShowMainAdminUI(player, "media");
        }

        [ConsoleCommand("hg.media.method")]
        private void CmdMediaMethod(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string method = arg.Args[0].ToLower();
            if (method == "console" || method == "browser")
            {
                Cfg.MediaMethod = method;
                SaveConfig();
                string methodName = method == "console" ? "консоль" : "браузер";
                Msg(player, $"Метод воспроизведения медиа изменен на: {methodName}");
                ShowMainAdminUI(player, "media");
            }
        }

        [ConsoleCommand("hg.media.welcome")]
        private void CmdMediaWelcome(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string format = arg.Args[0].ToLower();
            if (format == "mp3")
                Cfg.WelcomeMediaUrl = "http://storage.prostoj.store/HGArena/welcome.mp3";
            else if (format == "mp4")
                Cfg.WelcomeMediaUrl = "http://storage.prostoj.store/HGArena/welcome.mp4";
            
            SaveConfig();
            Msg(player, $"Формат приветственного медиа изменен на {format.ToUpper()}");
            ShowMainAdminUI(player, "media");
        }

        [ConsoleCommand("hg.media.end")]
        private void CmdMediaEnd(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            string format = arg.Args[0].ToLower();
            if (format == "mp3")
                Cfg.EndMediaUrl = "http://storage.prostoj.store/HGArena/end.mp3";
            else if (format == "mp4")
                Cfg.EndMediaUrl = "http://storage.prostoj.store/HGArena/end.mp4";
            
            SaveConfig();
            Msg(player, $"Формат медиа окончания изменен на {format.ToUpper()}");
            ShowMainAdminUI(player, "media");
        }

        [ConsoleCommand("hg.media.welcomeduration")]
        private void CmdMediaWelcomeDuration(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (float.TryParse(arg.Args[0], out float duration))
            {
                Cfg.WelcomeMediaDuration = Mathf.Max(1f, duration);
                SaveConfig();
                Msg(player, $"Длительность приветствия установлена: {Cfg.WelcomeMediaDuration} сек");
                ShowMainAdminUI(player, "media");
            }
        }

        [ConsoleCommand("hg.media.endduration")]
        private void CmdMediaEndDuration(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;
            if (!arg.HasArgs()) return;

            if (float.TryParse(arg.Args[0], out float duration))
            {
                Cfg.EndMediaDuration = Mathf.Max(1f, duration);
                SaveConfig();
                Msg(player, $"Длительность медиа окончания установлена: {Cfg.EndMediaDuration} сек");
                ShowMainAdminUI(player, "media");
            }
        }

        [ConsoleCommand("hg.media.test")]
        private void CmdMediaTest(ConsoleSystem.Arg arg)
        {
            var player = arg.Player();
            if (player == null || !permission.UserHasPermission(player.UserIDString, PermAdmin)) return;

            if (Cfg.MediaEnabled && !string.IsNullOrEmpty(Cfg.WelcomeMediaUrl))
            {
                PlayMediaForPlayer(player, Cfg.WelcomeMediaUrl, Cfg.WelcomeMediaDuration);
                ShowMediaNotification(player, "🧪 Тестирование медиа", 3f);
                Msg(player, "Тестирование приветственного медиа запущено");
            }
            else
            {
                Msg(player, "Медиа отключено или URL не задан");
            }
        }

        #endregion

        #region Chat helpers

        string Pfx => Cfg.ChatPrefix;
        void Broadcast(string msg) => Server.Broadcast($"{Pfx} {msg}");
        void Msg(BasePlayer p, string msg) => PrintToChat(p, $"{Pfx} {msg}");
        void MsgAdmins(string text) 
        {
            foreach (var pl in BasePlayer.activePlayerList)
                if (permission.UserHasPermission(pl.UserIDString, PermAdmin))
                    Msg(pl, text);
        }

        #endregion
    }
}
