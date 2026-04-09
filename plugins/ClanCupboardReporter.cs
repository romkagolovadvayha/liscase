using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Plugins;
using UnityEngine;
using ConVar;

namespace Oxide.Plugins
{
    /// <summary>
    /// Сбор шкафов по кланам: «главный» = с максимальным числом строительных блоков на upkeep (один проход по BuildingBlock).
    /// Дополнение к ClanManager: при загруженном ClanManager список кланов берётся через API без лишнего GET.
    /// </summary>
    [Info("ClanCupboardReporter", "Prostoj", "1.0.1")]
    public class ClanCupboardReporter : RustPlugin
    {
        [PluginReference] private Plugin ClanManager;

        private Configuration _config;
        private bool _warnedNoClanSource;

        private class Configuration
        {
            [JsonProperty("debugMode")]
            public bool DebugMode = false;

            /// <summary>Интервал автоотправки, секунд (0 = только вручную).</summary>
            [JsonProperty("sendIntervalSeconds")]
            public int SendIntervalSeconds = 300;

            [JsonProperty("pluginIngestBaseUrl")]
            public string PluginIngestBaseUrl = "https://api.moscow77.store/v1/plugin-ingest";

            /// <summary>База API для /clans/list, если нет ClanManager и не задан clanListUrlOverride.</summary>
            [JsonProperty("clanApiBaseUrl")]
            public string ClanApiBaseUrl = "https://api.moscow77.store/v1";

            /// <summary>Полный URL списка кланов; если пусто — строится из clanApiBaseUrl + ip/port сервера.</summary>
            [JsonProperty("clanListUrlOverride")]
            public string ClanListUrlOverride = "";

            /// <summary>full: все шкафа клана + main_cupboard (подсказка; бэкенд пересчитывает по max блоков); summary: только один на клан.</summary>
            [JsonProperty("payloadMode")]
            public string PayloadMode = "full";

            [JsonProperty("logMaxChars")]
            public int LogMaxChars = 12000;
        }

        private class ClanUserDto
        {
            [JsonProperty("steam_id")] public string steam_id;
        }

        private class ClanDataDto
        {
            [JsonProperty("tag")] public string tag;
            [JsonProperty("users")] public List<ClanUserDto> users = new List<ClanUserDto>();
        }

        private static ulong ParseSteamId(string s)
        {
            return ulong.TryParse(s, out var id) ? id : 0UL;
        }

        protected override void LoadDefaultConfig()
        {
            _config = new Configuration();
            SaveConfig();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                _config = Config.ReadObject<Configuration>() ?? new Configuration();
            }
            catch
            {
                PrintError("ClanCupboardReporter: ошибка конфига, сброс к умолчаниям.");
                LoadDefaultConfig();
            }
        }

        protected override void SaveConfig() => Config.WriteObject(_config);

        private void Init()
        {
            LoadConfig();
        }

        private void OnServerInitialized()
        {
            if (_config.SendIntervalSeconds > 0)
                timer.Every(_config.SendIntervalSeconds, () => BeginSendReport());
        }

        private void Unload()
        {
        }

        [ConsoleCommand("clancupboards.send")]
        private void CmdSend(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null && !arg.Player().IsAdmin)
                return;
            BeginSendReport();
        }

        private void BeginSendReport()
        {
            if (_config.DebugMode)
                Puts("ClanCupboardReporter: сбор данных (debug: HTTP POST не выполняется)...");

            var fromPlugin = TryGetClansJsonFromClanManager();
            if (fromPlugin != null)
            {
                ProcessAndSend(fromPlugin);
                return;
            }

            var url = ResolveClanListUrl();
            if (string.IsNullOrEmpty(url))
            {
                if (!_warnedNoClanSource)
                {
                    _warnedNoClanSource = true;
                    PrintError("ClanCupboardReporter: нет источника кланов (загрузите ClanManager или задайте clanListUrlOverride).");
                }
                return;
            }

            webrequest.Enqueue(url, null, (code, body) =>
            {
                if (code != 200 || string.IsNullOrEmpty(body))
                {
                    PrintError($"ClanCupboardReporter: clans/list HTTP {code}");
                    return;
                }
                NextTick(() => ProcessAndSend(body));
            }, this, RequestMethod.GET);
        }

        private string TryGetClansJsonFromClanManager()
        {
            if (ClanManager == null || !ClanManager.IsLoaded)
                return null;
            try
            {
                return ClanManager.Call("API_GetClansSnapshotJson") as string;
            }
            catch
            {
                return null;
            }
        }

        private string ResolveClanListUrl()
        {
            if (!string.IsNullOrWhiteSpace(_config.ClanListUrlOverride))
                return _config.ClanListUrlOverride.Trim();

            var baseUrl = (_config.ClanApiBaseUrl ?? string.Empty).TrimEnd('/');
            if (string.IsNullOrEmpty(baseUrl))
                return null;
            var ip = Uri.EscapeDataString(ConVar.Server.ip);
            int port = ConVar.Server.port;
            return $"{baseUrl}/clans/list?ip={ip}&port={port}";
        }

        private void ProcessAndSend(string clansJson)
        {
            List<ClanDataDto> clans;
            try
            {
                clans = JsonConvert.DeserializeObject<List<ClanDataDto>>(clansJson) ?? new List<ClanDataDto>();
            }
            catch (Exception ex)
            {
                PrintError("ClanCupboardReporter: разбор JSON кланов: " + ex.Message);
                return;
            }

            var steamToTag = new Dictionary<ulong, string>();
            foreach (var clan in clans)
            {
                if (string.IsNullOrEmpty(clan.tag) || clan.users == null)
                    continue;
                foreach (var u in clan.users)
                {
                    var id = ParseSteamId(u?.steam_id);
                    if (id == 0UL)
                        continue;
                    steamToTag[id] = clan.tag;
                }
            }

            var blockCounts = new Dictionary<BuildingPrivlidge, int>();
            var allCupboards = new List<BuildingPrivlidge>();
            foreach (var ent in BaseNetworkable.serverEntities)
            {
                var c = ent as BuildingPrivlidge;
                if (c != null && !c.IsDestroyed)
                    allCupboards.Add(c);

                var block = ent as BuildingBlock;
                if (block == null || block.IsDestroyed)
                    continue;
                var priv = block.GetBuildingPrivilege() as BuildingPrivlidge;
                if (priv == null || priv.IsDestroyed)
                    continue;
                if (!blockCounts.ContainsKey(priv))
                    blockCounts[priv] = 0;
                blockCounts[priv]++;
            }

            var byTag = new Dictionary<string, List<CupboardRow>>(StringComparer.Ordinal);
            var unassigned = new List<CupboardRow>();

            foreach (var cup in allCupboards)
            {
                var row = BuildRow(cup, blockCounts);
                var owner = cup.OwnerID;
                if (!steamToTag.TryGetValue(owner, out var tag))
                {
                    unassigned.Add(row);
                    continue;
                }
                if (!byTag.TryGetValue(tag, out var list))
                {
                    list = new List<CupboardRow>();
                    byTag[tag] = list;
                }
                list.Add(row);
            }

            var mode = (_config.PayloadMode ?? "full").ToLowerInvariant();
            var clanPayloads = new List<object>();

            foreach (var kv in byTag.OrderBy(k => k.Key, StringComparer.Ordinal))
            {
                var tag = kv.Key;
                var rows = kv.Value;
                var main = rows.OrderByDescending(r => r.protected_blocks).First();
                var mainId = main.entity_id;

                if (mode == "summary")
                {
                    clanPayloads.Add(new
                    {
                        tag,
                        main_cupboard = main,
                        main_entity_id = mainId,
                    });
                }
                else
                {
                    var withFlags = rows
                        .Select(r => new
                        {
                            r.entity_id,
                            r.map_square,
                            r.placer_steam_id,
                            r.protected_blocks,
                            main_cupboard = r.entity_id == mainId,
                        })
                        .OrderByDescending(x => x.protected_blocks)
                        .ToList();
                    clanPayloads.Add(new
                    {
                        tag,
                        cupboards = withFlags,
                        main_entity_id = mainId,
                    });
                }
            }

            var unassignedOut = mode == "summary"
                ? (object)new List<object>()
                : unassigned
                    .OrderByDescending(r => r.protected_blocks)
                    .ToList();

            var root = new
            {
                ip = ConVar.Server.ip,
                port = ConVar.Server.port,
                world_size = (int)ConVar.Server.worldsize,
                generated_at = DateTimeOffset.UtcNow.ToUnixTimeSeconds(),
                payload_mode = mode,
                clans = clanPayloads,
                unassigned_cupboards = unassignedOut,
            };

            var jsonSettings = new JsonSerializerSettings { NullValueHandling = NullValueHandling.Ignore };
            var json = JsonConvert.SerializeObject(root, Formatting.None, jsonSettings);
            if (_config.DebugMode)
            {
                var lim = Math.Max(500, _config.LogMaxChars);
                var snippet = json.Length <= lim ? json : json.Substring(0, lim) + "…";
                Puts("ClanCupboardReporter DEBUG payload:\n" + snippet);
                return;
            }

            var ingest = string.IsNullOrWhiteSpace(_config.PluginIngestBaseUrl)
                ? "https://api.moscow77.store/v1/plugin-ingest"
                : _config.PluginIngestBaseUrl.TrimEnd('/');
            var postUrl = $"{ingest}/clan-cupboards";
            var headers = new Dictionary<string, string> { ["Content-Type"] = "application/json" };
            webrequest.Enqueue(postUrl, json, (code, resp) =>
            {
                if (code != 200)
                    PrintError($"ClanCupboardReporter: POST {postUrl} -> HTTP {code} {resp}");
            }, this, RequestMethod.POST, headers, 60f);
        }

        private static CupboardRow BuildRow(BuildingPrivlidge cup, Dictionary<BuildingPrivlidge, int> blockCounts)
        {
            var pos = cup.transform.position;
            blockCounts.TryGetValue(cup, out var n);
            return new CupboardRow
            {
                entity_id = cup.net.ID.Value.ToString(),
                map_square = GetRustMapSquare(pos),
                placer_steam_id = cup.OwnerID.ToString(),
                protected_blocks = n,
            };
        }

        /// <summary>Квадрат карты как SignStatistics / RustApp MapHelper (сетка 146.3).</summary>
        private static string GetRustMapSquare(Vector3 pos)
        {
            float ws = ConVar.Server.worldsize;
            const float cell = 146.3f;
            int fx = (int)Mathf.Floor((pos.x + ws / 2f) / cell);
            int xi = fx % 26;
            if (xi < 0)
                xi += 26;
            char letter = (char)('A' + xi);
            int z = (int)(Mathf.Floor(ws / cell) - Mathf.Floor((pos.z + ws / 2f) / cell));
            return $"{letter}{z}";
        }

        private class CupboardRow
        {
            public string entity_id;
            public string map_square;
            public string placer_steam_id;
            public int protected_blocks;
        }

    }
}
