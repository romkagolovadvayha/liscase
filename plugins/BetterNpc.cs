using UnityEngine.AI;
using System.Text;
using System.Collections;
using Newtonsoft.Json.Linq;
using Oxide.Core.Plugins;
using System.Collections.Generic;
using Newtonsoft.Json;
using Oxide.Plugins.BetterNpcExtensionMethods;
using System;
using Facepunch;
using Prefabs.Misc;
using UnityEngine;
using Rust.Ai.Gen2;
using Oxide.Core;
using System.IO;
using Newtonsoft.Json.Converters;

namespace Oxide.Plugins
{
    [Info("BetterNpc", "KpucTaJl", "2.2.7")]
    internal class BetterNpc : RustPlugin
    {

        private object CanDeployScientists(BradleyAPC bradley, BaseEntity attacker, List<GameObjectRef> scientistPrefabs, List<Vector3> spawnPositions)
        {
            if (bradley == null || bradley.net == null || BradleyCrates == null) return null;
		   		 		  						  	   		   					  			 		   					  	 		
            if (!EventSpawnPoints.TryGetValue("Bradley", out EventSpawnPoint spawnPoint)) return null;
            BradleySpawnPoint bradleySpawnPoint = spawnPoint as BradleySpawnPoint;
            if (bradleySpawnPoint is not { Enabled: true }) return null;

            if (bradleySpawnPoint.RemoveDefaultNpc) return false;

            return null;
        }
        
                public class CrateCh47
        {
            public string Name { get; set; }
            public Vector3 Position { get; set; }
            public HackableLockedCrate Crate { get; set; }
        }

        private IEnumerator LoadTunnelSpawnPoints()
        {
            string shortPath = "BetterNpc/Monument/Tunnel/";

            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            foreach (string filePath in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = filePath.GetFileName();
                TryReadMonumentSpawnPoint(TunnelSpawnPoints, fileName, shortPath);
                yield return CoroutineEx.waitForSeconds(0.1f);
            }
        }

        private void AnalyzingSpawnPoint(SpawnPoint spawnPoint, string name)
        {
            spawnPoint.Presets ??= new List<PresetConfig>();
            foreach (PresetConfig preset in spawnPoint.Presets) AnalyzingPreset(preset);
            if (spawnPoint.Enabled && (spawnPoint.Presets.Count == 0 || !spawnPoint.Presets.Any(x => x.Enabled))) spawnPoint.Enabled = false;
            TryRegisterPresetsUsage(spawnPoint, name);
        }

        public class LootItemConfig : LootElementChanceConfig
        {
            [JsonProperty(En ? "Item Short Name" : "ShortName предмета", Order = 0)] public string Shortname { get; set; }
            [JsonProperty(En ? "Skin ID (0 = default)" : "SkinID (0 = стандартный)", Order = 4)] public ulong Skin { get; set; }
            [JsonProperty(En ? "Custom Display Name (optional)" : "Пользовательское имя предмета (необязательно)", Order = 5)] public string DisplayName { get; set; }
            [JsonProperty(En ? "Owner Name (optional)" : "Имя владельца предмета (необязательно)", Order = 6)] public string OwnerName { get; set; }
            [JsonProperty(En ? "Is Blueprint [true/false]" : "Это чертёж [true/false]", Order = 7)] public bool IsBlueprint { get; set; }

            public static LootItemConfig CreateDefault()
            {
                return new LootItemConfig
                {
                    Shortname = "scrap",
                    Skin = 0,
                    DisplayName = string.Empty,
                    OwnerName = string.Empty,
                    IsBlueprint = false,
                    Chance = 50f,
                    MinAmount = 5,
                    MaxAmount = 5
                };
            }

            public static LootItemConfig FromJObject(JObject obj)
            {
                LootItemConfig cfg = CreateDefault();

                cfg.Shortname = obj.Read("Shortname", cfg.Shortname);
                cfg.Skin = obj.Read("Skin", cfg.Skin);
                cfg.DisplayName = obj.Read("DisplayName", cfg.DisplayName);
                cfg.OwnerName = obj.Read("OwnerName", cfg.OwnerName);
                cfg.IsBlueprint = obj.Read("IsBlueprint", cfg.IsBlueprint);

                cfg.Chance = obj.Read("Chance", cfg.Chance);
                cfg.MinAmount = obj.Read("MinAmount", cfg.MinAmount);
                cfg.MaxAmount = obj.Read("MaxAmount", cfg.MaxAmount);

                return cfg;
            }
        }

        private void OnEntityKill(CargoShip cargo)
        {
            if (cargo == null || CargoConfig is not { Enabled: true }) return;

            CargoControllerSpawnPoint controller = CargoControllers.FirstOrDefault(x => x.Cargo == cargo);
            if (controller == null) return;

            CargoControllers.Remove(controller);
            UnityEngine.Object.DestroyImmediate(controller);
        }

        [ChatCommand("SpawnPointReload")]
        private void ChatCommandSpawnPointReload(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }

            string name = GetNameArgs(args, 0);

            DestroyController(name);

            bool success = false;

            if (MonumentSpawnPoints.ContainsKey(name)) success = TryReadMonumentSpawnPoint(MonumentSpawnPoints, name, "BetterNpc/Monument/");
            else if (UnderwaterLabSpawnPoints.ContainsKey(name)) success = TryReadMonumentSpawnPoint(UnderwaterLabSpawnPoints, name, "BetterNpc/Monument/Underwater Lab/");
            else if (TunnelSpawnPoints.ContainsKey(name)) success = TryReadMonumentSpawnPoint(TunnelSpawnPoints, name, "BetterNpc/Monument/Tunnel/");
            else if (CustomSpawnPoints.ContainsKey(name)) success = TryReadCustomMonumentSpawnPoint(CustomSpawnPoints, name);
            else if (EventSpawnPoints.ContainsKey(name))
            {
                DestroyEventControllers();
                if (name == "Bradley") TryReadBradleySpawnPoint();
                else TryReadEventSpawnPoint(name);
                success = true;
            }
            else if (name == "CargoShip")
            {
                DestroyCargoControllers();
                TryReadCargoSpawnPoint();
                success = true;
            }
            else if (BiomeSpawnPoints.ContainsKey(name)) success = TryReadRoadOrBiomeSpawnPoint(BiomeSpawnPoints, name, "BetterNpc/Biome/");
            else if (RoadSpawnPoints.ContainsKey(name)) success = TryReadRoadOrBiomeSpawnPoint(RoadSpawnPoints, name, "BetterNpc/Road/");

            CreateController(name);

            if (success) PrintToChat(player, $"SpawnPoint with the name <color=#55aaff>{name}</color> <color=#738d43>has been reloaded</color>!");
        }

        protected override void LoadDefaultConfig()
        {
            Puts("Creating a default config...");
            Cfg = PluginConfig.DefaultConfig();
            Cfg.PluginVersion = Version;
            SaveConfig();
            Puts("Creation of the default config completed!");
        }
        private void LoadMonumentMarkers()
        {
            foreach (KeyValuePair<string, HashSet<GameObject>> kvp in World.SpawnedPrefabs)
            {
                if (string.IsNullOrEmpty(kvp.Key) || kvp.Value == null || kvp.Value.Count == 0) continue;
                foreach (GameObject gameObject in kvp.Value)
                {
                    if (gameObject == null || string.IsNullOrEmpty(gameObject.name)) continue;
                    if (!gameObject.name.Contains("monument_marker")) continue;
                    MonumentMarkers.TryAdd(kvp.Key, gameObject.transform);
                }
            }
        }

        [ConsoleCommand("ShowAllNpc")]
        private void ConsoleCommandShowAllNpc(ConsoleSystem.Arg arg)
        {
            if (arg.Player() == null)
            {
                string message = "The number of NPCs from the BetterNpc plugin:";
                int all = 0;
                foreach (ControllerSpawnPoint controller in Controllers)
                {
                    message += $"\n- {controller.Name} = {controller.ActiveNpcStates.Count}";
                    all += controller.ActiveNpcStates.Count;
                }
                if (CargoControllers != null)
                {
                    foreach (CargoControllerSpawnPoint controller in CargoControllers)
                    {
                        if (controller.Cargo == null || controller.Cargo.net == null) continue;
                        message += $"\n- {controller.Cargo.net.ID.Value} = {controller.ActiveNpcStates.Count}";
                        all += controller.ActiveNpcStates.Count;
                    }
                }
                message += $"\nTotal number = {all}";
                Puts(message);
            }
        }

        private void Init() => _ = this;

        private void DestroyController(string name, Vector3 position)
        {
            ControllerSpawnPoint controller = GetNearController(position, name);
            Controllers.Remove(controller);
            UnityEngine.Object.Destroy(controller.gameObject);
        }

        private void OnCorpsePopulate(ScientistNPC npc, NPCPlayerCorpse corpse)
        {
            if (npc == null || npc.skinID != 11162132011012) return;

            ControllerSpawnPoint controller = Controllers.FirstOrDefault(x => x.Contains(npc));
            if (controller != null)
            {
                controller.DieNpc(npc);
                return;
            }

            if (CargoControllers is { Count: > 0 })
            {
                CargoControllerSpawnPoint cargoController = CargoControllers.FirstOrDefault(x => x.Contains(npc));
                if (cargoController != null)
                {
                    cargoController.DieNpc(npc);
                    return;
                }
            }
        }

        [ChatCommand("SpawnPointRemovePos")]
        private void ChatCommandSpawnPointRemovePos(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the preset number!");
                return;
            }

            if (args.Length == 1)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            int number = Convert.ToInt32(args[0]) - 1;
            if (number < 0) return;

            string name = GetNameArgs(args, 1);

            ControllerSpawnPoint controller = GetNearController(player.transform.position, name);
            if (controller == null)
            {
                PrintToChat(player, $"Spawn Point with the name <color=#55aaff>{name}</color> was <color=#ce3f27>not</color> found!");
                return;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            Vector3 pos = controller.transform.GetLocalPosition(player.transform.position);
		   		 		  						  	   		   					  			 		   					  	 		
            if (MonumentSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint1))
            {
                if (spawnPoint1.Presets.Count < number + 1) return;
                PresetConfig preset = spawnPoint1.Presets[number];

                string remove = preset.CustomPositions.Min(x => Vector3.Distance(x.ToVector3(), pos));
                preset.CustomPositions.Remove(remove);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/{name}", spawnPoint1);
                PrintToChat(player, $"You <color=#738d43>have successfully removed</color> a position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
            else if (UnderwaterLabSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint2))
            {
                if (spawnPoint2.Presets.Count < number + 1) return;
                PresetConfig preset = spawnPoint2.Presets[number];

                string remove = preset.CustomPositions.Min(x => Vector3.Distance(x.ToVector3(), pos));
                preset.CustomPositions.Remove(remove);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/Underwater Lab/{name}", spawnPoint2);
                PrintToChat(player, $"You <color=#738d43>have successfully removed</color> a position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
            else if (TunnelSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint3))
            {
                if (spawnPoint3.Presets.Count < number + 1) return;
                PresetConfig preset = spawnPoint3.Presets[number];

                string remove = preset.CustomPositions.Min(x => Vector3.Distance(x.ToVector3(), pos));
                preset.CustomPositions.Remove(remove);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/Tunnel/{name}", spawnPoint3);
                PrintToChat(player, $"You <color=#738d43>have successfully removed</color> a position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
            else if (CustomSpawnPoints.TryGetValue(name, out CustomMonumentSpawnPoint spawnPoint4))
            {
                if (spawnPoint4.Presets.Count < number + 1) return;
                PresetConfig preset = spawnPoint4.Presets[number];

                string remove = preset.CustomPositions.Min(x => Vector3.Distance(x.ToVector3(), pos));
                preset.CustomPositions.Remove(remove);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Custom/{name}", spawnPoint4);
                PrintToChat(player, $"You <color=#738d43>have successfully removed</color> a position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
        }

        private void ReloadController(string name)
        {
            DestroyController(name);

            if (MonumentSpawnPoints.ContainsKey(name)) TryReadMonumentSpawnPoint(MonumentSpawnPoints, name, "BetterNpc/Monument/");
            else if (UnderwaterLabSpawnPoints.ContainsKey(name)) TryReadMonumentSpawnPoint(UnderwaterLabSpawnPoints, name, "BetterNpc/Monument/Underwater Lab/");
            else if (TunnelSpawnPoints.ContainsKey(name)) TryReadMonumentSpawnPoint(TunnelSpawnPoints, name, "BetterNpc/Monument/Tunnel/");
            else if (CustomSpawnPoints.ContainsKey(name)) TryReadCustomMonumentSpawnPoint(CustomSpawnPoints, name);
            else if (EventSpawnPoints.ContainsKey(name))
            {
                DestroyEventControllers();
                if (name == "Bradley") TryReadBradleySpawnPoint();
                else TryReadEventSpawnPoint(name);
            }
            else if (name == "CargoShip")
            {
                DestroyCargoControllers();
                TryReadCargoSpawnPoint();
            }
            else if (BiomeSpawnPoints.ContainsKey(name)) TryReadRoadOrBiomeSpawnPoint(BiomeSpawnPoints, name, "BetterNpc/Biome/");
            else if (RoadSpawnPoints.ContainsKey(name)) TryReadRoadOrBiomeSpawnPoint(RoadSpawnPoints, name, "BetterNpc/Road/");

            CreateController(name);
        }

        private IEnumerator LoadEventSpawnPoints()
        {
            string shortPath = "BetterNpc/Event/";

            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            foreach (string filePath in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = filePath.GetFileName();
                switch (fileName)
                {
                    case "CargoShip":
                        TryReadCargoSpawnPoint();
                        break;
                    case "Bradley":
                        TryReadBradleySpawnPoint();
                        break;
                    default:
                        TryReadEventSpawnPoint(fileName);
                        break;
                }
                yield return CoroutineEx.waitForSeconds(0.1f);
            }

            if (!EventSpawnPoints.TryGetValue("AirDrop", out EventSpawnPoint spawnPoint1) || !spawnPoint1.Enabled) DisableAirDrop();
            if (!EventSpawnPoints.TryGetValue("CH47", out EventSpawnPoint spawnPoint2) || !spawnPoint2.Enabled) DisableCh47();
            if (!EventSpawnPoints.TryGetValue("Bradley", out EventSpawnPoint spawnPoint3) || !spawnPoint3.Enabled) DisableBradley();
            if (!EventSpawnPoints.TryGetValue("Helicopter", out EventSpawnPoint spawnPoint4) || !spawnPoint4.Enabled) DisableHelicopter();
            if (CargoConfig is not { Enabled: true }) DisableCargoShip();
        }
        
                [PluginReference] private readonly Plugin Economics, ServerRewards, IQEconomic, XPerience;
        
                private HashSet<ControllerSpawnPoint> Controllers { get; set; } = new HashSet<ControllerSpawnPoint>();

        private void TryClearPresetUsage(SpawnPoint spawnPoint, string name)
        {
            if (spawnPoint is not { Enabled: true }) return;
            foreach (PresetConfig preset in spawnPoint.Presets)
            {
                if (!preset.Enabled || string.IsNullOrEmpty(preset.PresetName)) continue;
                NpcSpawn?.Call("UnregisterPresetUsage", preset.PresetName, "BetterNpc", name);
            }
        }

        private IEnumerator SpawnBiomeSpawnPoints()
        {
            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in BiomeSpawnPoints)
            {
                if (!kvp.Value.Enabled) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.Init(kvp.Value, Vector3.zero, Quaternion.identity, kvp.Key);
                Controllers.Add(controller);

                if (!Cfg.EnabledMinLogs) Puts($"Biome {kvp.Key} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.4f);
            }
        }

        private void DisableCargoShip()
        {
            Unsubscribe("OnCargoShipSpawnCrate");
            Unsubscribe("OnCargoShipHarborArrived");
            CargoControllers.Clear();
            CargoControllers = null;
        }

        private IEnumerator LoadCustomSpawnPoints()
        {
            string shortPath = "BetterNpc/Custom/";

            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            foreach (string name in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = name.GetFileName();
                TryReadCustomMonumentSpawnPoint(CustomSpawnPoints, fileName);
                yield return CoroutineEx.waitForSeconds(0.1f);
            }
        }
        
                public class FailedNavMeshPoint
        {
            public string ControllerName { get; set; }
            public string PresetName { get; set; }
            public int PresetIndex { get; set; }
            public int Index { get; set; }
            public Vector3 Position { get; set; }
            public bool IsStationary { get; set; }
            public int AreaMask { get; set; }
            public bool HasGround { get; set; }
            public bool HasNavMesh { get; set; }
            public bool HasAltNavMesh { get; set; }
        }
        
                [PluginReference] private readonly Plugin NpcSpawn, PveMode;

        [ChatCommand("SpawnPointAddPos")]
        private void ChatCommandSpawnPointAddPos(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the preset number!");
                return;
            }

            if (args.Length == 1)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }

            int number = Convert.ToInt32(args[0]) - 1;
            if (number < 0) return;

            string name = GetNameArgs(args, 1);

            ControllerSpawnPoint controller = GetNearController(player.transform.position, name);
            if (controller == null)
            {
                PrintToChat(player, $"Spawn Point with the name <color=#55aaff>{name}</color> was <color=#ce3f27>not</color> found!");
                return;
            }

            string pos = controller.transform.GetLocalPosition(player.transform.position).ToString();

            if (MonumentSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint1))
            {
                if (spawnPoint1.Presets.Count < number + 1) return;

                spawnPoint1.Presets[number].CustomPositions.Add(pos);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/{name}", spawnPoint1);
                PrintToChat(player, $"You <color=#738d43>have successfully saved</color> a new position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
            else if (UnderwaterLabSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint2))
            {
                if (spawnPoint2.Presets.Count < number + 1) return;

                spawnPoint2.Presets[number].CustomPositions.Add(pos);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/Underwater Lab/{name}", spawnPoint2);
                PrintToChat(player, $"You <color=#738d43>have successfully saved</color> a new position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
            else if (TunnelSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint3))
            {
                if (spawnPoint3.Presets.Count < number + 1) return;

                spawnPoint3.Presets[number].CustomPositions.Add(pos);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/Tunnel/{name}", spawnPoint3);
                PrintToChat(player, $"You <color=#738d43>have successfully saved</color> a new position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
            else if (CustomSpawnPoints.TryGetValue(name, out CustomMonumentSpawnPoint spawnPoint4))
            {
                if (spawnPoint4.Presets.Count < number + 1) return;

                spawnPoint4.Presets[number].CustomPositions.Add(pos);

                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Custom/{name}", spawnPoint4);
                PrintToChat(player, $"You <color=#738d43>have successfully saved</color> a new position for Spawn Point <color=#55aaff>{name}</color> and for preset <color=#55aaff>{number + 1}</color>!");
            }
        }

        private IEnumerator PluginInitialization()
        {
            PrintWarning("Starting plugin initialization process...");

            string path = Interface.Oxide.DataDirectory + "/BetterNpc/";
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            yield return LoadMonumentSpawnPoints();
            PrintWarning("Plugin loading progress at 8%");

            yield return LoadUnderwaterLabSpawnPoints();
            PrintWarning("Plugin loading progress at 14%");

            yield return LoadTunnelSpawnPoints();
            PrintWarning("Plugin loading progress at 23%");

            LoadIds();
            LoadMonumentMarkers();
            yield return LoadCustomSpawnPoints();
            PrintWarning("Plugin loading progress at 27%");

            yield return LoadEventSpawnPoints();
            PrintWarning("Plugin loading progress at 28%");

            yield return LoadRoadSpawnPoints();
            PrintWarning("Plugin loading progress at 29%");

            yield return LoadBiomeSpawnPoints();
            PrintWarning("Plugin loading progress at 30%");

            yield return SpawnMonumentSpawnPoints();
            yield return SpawnDeepSeaSpawnPoints();
            PrintWarning("Plugin loading progress at 46%");

            yield return SpawnUnderwaterLabSpawnPoints();
            PrintWarning("Plugin loading progress at 58%");

            yield return SpawnTunnelSpawnPoints();
            PrintWarning("Plugin loading progress at 74%");

            yield return SpawnCustomSpawnPoints();
            PrintWarning("Plugin loading progress at 82%");

            yield return SpawnRoadSpawnPoints();
            PrintWarning("Plugin loading progress at 85%");

            yield return SpawnBiomeSpawnPoints();
            PrintWarning("Plugin loading progress at 90%");

            StartCheckDay();
            StartRespawnCounter();
            PrintWarning("Plugin loading progress at 92%");

            if (Cfg.SpawnGroupFill)
            {
                SpawnHandler handler = SingletonComponent<SpawnHandler>.Instance;
                if (handler != null) handler.FillGroups();
                if (!Cfg.EnabledMinLogs) Puts("All spawn groups have been successfully filled!");
            }
            PrintWarning("Plugin loading progress at 96%");

            if (Cfg.PuzzleReset)
            {
                PuzzleReset[] puzzleResetArray = UnityEngine.Object.FindObjectsOfType<PuzzleReset>();
                for (int i = 0; i < puzzleResetArray.Length; i++)
                {
                    PuzzleReset puzzleReset = puzzleResetArray[i];
                    puzzleReset.DoReset();
                    puzzleReset.ResetTimer();
                }
                if (!Cfg.EnabledMinLogs) Puts("All puzzles have been successfully reset!");
            }

            PrintWarning("Completed plugin initialization successfully!");
        }
        private HashSet<CrateCh47> Ch47Crates { get; set; } = new HashSet<CrateCh47>();

        private void ClearVariablesForCustomMonuments()
        {
            CustomSpawnPoints.Clear();
            CustomSpawnPoints = null;

            if (Ids != null)
            {
                Ids.Clear();
                Ids = null;
            }

            if (MonumentMarkers != null)
            {
                MonumentMarkers.Clear();
                MonumentMarkers = null;
            }
        }
        
                private void TryRegisterPresetsUsage(SpawnPoint spawnPoint, string name)
        {
            if (!spawnPoint.Enabled) return;
            foreach (PresetConfig preset in spawnPoint.Presets)
            {
                if (!preset.Enabled || string.IsNullOrEmpty(preset.PresetName)) continue;
                NpcSpawn?.Call("RegisterPresetUsage", preset.PresetName, "BetterNpc", name);
            }
        }

        private void OnCrateSpawned(PatrolHelicopter helicopter, LockedByEntCrate crate)
        {
            if (HelicopterCrates == null) return;
            if (crate == null || crate.net == null) return;
            if (helicopter == null || helicopter.net == null) return;
            if (HelicopterCrates.TryGetValue(helicopter.net.ID.Value, out HashSet<ulong> crates)) crates.Add(crate.net.ID.Value);
        }

        [ChatCommand("SpawnPointShowPos")]
        private void ChatCommandSpawnPointShowPos(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the preset number!");
                return;
            }

            if (args.Length == 1)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }

            int number = Convert.ToInt32(args[0]) - 1;
            if (number < 0) return;

            string name = GetNameArgs(args, 1);

            ControllerSpawnPoint controller = GetNearController(player.transform.position, name);
            if (controller == null)
            {
                PrintToChat(player, $"Spawn Point with the name <color=#55aaff>{name}</color> was <color=#ce3f27>not</color> found!");
                return;
            }

            if (MonumentSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint1))
            {
                if (spawnPoint1.Presets.Count < number + 1) return;
                ShowPositions(spawnPoint1.Presets[number]);
            }
            else if (UnderwaterLabSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint2))
            {
                if (spawnPoint2.Presets.Count < number + 1) return;
                ShowPositions(spawnPoint2.Presets[number]);
            }
            else if (TunnelSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint3))
            {
                if (spawnPoint3.Presets.Count < number + 1) return;
                ShowPositions(spawnPoint3.Presets[number]);
            }
            else if (CustomSpawnPoints.TryGetValue(name, out CustomMonumentSpawnPoint spawnPoint4))
            {
                if (spawnPoint4.Presets.Count < number + 1) return;
                ShowPositions(spawnPoint4.Presets[number]);
            }
		   		 		  						  	   		   					  			 		   					  	 		
            return;

            void ShowPositions(PresetConfig preset)
            {
                for (int index = 0; index < preset.CustomPositions.Count; index++)
                {
                    string str = preset.CustomPositions[index];
                    Vector3 position = controller.transform.GetGlobalPosition(str.ToVector3());
                    DebugDrawEx.Sphere(player, position, Color.green, 2f);
                    DebugDrawEx.Line(player, position + Vector3.up * 200f, position, Color.green);
                    DebugDrawEx.Text(player, position, $"{index + 1}", Color.green, 40);
                }
            }
        }

        private IEnumerator LoadUnderwaterLabSpawnPoints()
        {
            string shortPath = "BetterNpc/Monument/Underwater Lab/";

            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            foreach (string filePath in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = filePath.GetFileName();
                TryReadMonumentSpawnPoint(UnderwaterLabSpawnPoints, fileName, shortPath);
                yield return CoroutineEx.waitForSeconds(0.1f);
            }
        }
		   		 		  						  	   		   					  			 		   					  	 		
        public class PrefabsLootTableConfig
        {
            [JsonProperty(En ? "Enable Prefab-Based Loot [true/false]" : "Включить лут на основе префабов [true/false]", Order = 0)] public bool IsEnabled { get; set; }
            [JsonProperty(En ? "Minimum Prefabs Amount" : "Минимальное количество префабов", Order = 1)] public int MinPrefabsAmount { get; set; }
            [JsonProperty(En ? "Maximum Prefabs Amount" : "Максимальное количество префабов", Order = 2)] public int MaxPrefabsAmount { get; set; }
            [JsonProperty(En ? "Loot Prefabs List" : "Список префабов лута", Order = 3)] public List<LootPrefabConfig> Prefabs { get; set; }

            public static PrefabsLootTableConfig CreateDefault()
            {
                return new PrefabsLootTableConfig
                {
                    IsEnabled = false,
                    MinPrefabsAmount = 1,
                    MaxPrefabsAmount = 1,
                    Prefabs = null
                };
            }

            public static PrefabsLootTableConfig FromJObject(JObject obj)
            {
                PrefabsLootTableConfig cfg = CreateDefault();

                cfg.IsEnabled = obj.Read("IsEnabled", cfg.IsEnabled);
                cfg.MinPrefabsAmount = obj.Read("MinPrefabsAmount", cfg.MinPrefabsAmount);
                cfg.MaxPrefabsAmount = obj.Read("MaxPrefabsAmount", cfg.MaxPrefabsAmount);

                if (obj.TryGetValue("Prefabs", out JToken itemsTok) && itemsTok is JArray arr)
                {
                    List<LootPrefabConfig> list = new List<LootPrefabConfig>();
                    foreach (var t in arr)
                    {
                        if (t is JObject itemObj)
                            list.Add(LootPrefabConfig.FromJObject(itemObj));
                    }
                    cfg.Prefabs = list.Count > 0 ? list : null;
                }

                return cfg;
            }
        }

        private void OnEntitySpawned(global::HumanNPC npc) => TryKillDefaultNpc(npc);

        private void DisableBradley()
        {
            Unsubscribe("CanDeployScientists");
            BradleyCrates.Clear();
            BradleyCrates = null;
        }

        public class ControllerSpawnPoint : BaseControllerSpawnPoint
        {
            public string Name { get; set; }
            public bool RemoveDefaultNpc { get; set; } = false;
            public Vector3 Size { get; set; }

            public bool IsEvent { get; set; } = false;
            public bool IsRoadOrBiome { get; set; } = false;
            public bool IsTunnel { get; set; } = false;
            public bool IsUnderwaterLab { get; set; } = false;
            public bool IsIsland { get; set; } = false;
            public bool IsGhostShip { get; set; } = false;
            public bool IsCustom { get; set; } = false;

            private void OnDestroy() => Destroy();

            public override int GetShouldAmountNpc(PresetConfig preset)
            {
                if (!IsRoadOrBiome && !IsEvent && preset is { SpawnType: SpawnType.Custom, SpawnAtAllCustomPositions: true }) return preset.CustomPositions.Count;
                else return base.GetShouldAmountNpc(preset);
            }

            public void Init(SpawnPoint spawnPoint, Vector3 position, Quaternion rotation, string spawnPointName)
            {
                transform.position = position;
                transform.rotation = rotation;

                Name = spawnPointName;

                SquareDistanceBetweenNpc = 36f;
                
                foreach (PresetConfig preset in spawnPoint.Presets)
                {
                    if (!preset.Enabled) continue;
                    PresetConfig clone = preset.Clone();
                    clone.CalculatePositions(transform);
                    Presets.Add(clone);
                }
		   		 		  						  	   		   					  			 		   					  	 		
                if (spawnPoint is MonumentSpawnPoint monumentSpawnPoint)
                {
                    Size = monumentSpawnPoint.Size.ToVector3();
                    RemoveDefaultNpc = monumentSpawnPoint.RemoveDefaultNpc;
                }

                if (spawnPoint is EventSpawnPoint eventSpawnPoint)
                {
                    IsEvent = true;
                    float radius = eventSpawnPoint.Radius;
                    Size = new Vector3(radius, radius, radius);
                }

                if (spawnPoint is RoadOrBiomeSpawnPoint roadOrBiomeSpawnPoint)
                {
                    IsRoadOrBiome = true;
                    float distanceBetweenNpc = roadOrBiomeSpawnPoint.MinDistanceBetweenNpc;
                    SquareDistanceBetweenNpc = distanceBetweenNpc * distanceBetweenNpc;
                }

                if (RemoveDefaultNpc)
                {
                    List<BaseCombatEntity> list = Facepunch.Pool.Get<List<BaseCombatEntity>>();
                    float radius = Mathf.Max(Size.x, Size.y, Size.z);
                    Vis.Entities(transform.position, radius, list, 133120); // 1 << 11 | 1 << 17
                    foreach (BaseCombatEntity npc in list) if (CanRemoveNpc(npc)) npc.Kill();
                    Facepunch.Pool.FreeUnmanaged(ref list);
                }

                foreach (PresetConfig preset in Presets)
                {
                    int amount = GetShouldAmountNpc(preset);
                    for (int i = 0; i < amount; i++) SpawnNpc(preset);
                }
            }

            public override void SpawnNpc(PresetConfig preset)
            {
                SpawnNpcByPos(preset, TryFindPosition(preset));
            }

            public override void DieNpc(ScientistNPC npc)
            {
                base.DieNpc(npc);
                if (!IsEvent || ActiveNpcStates.Count > 0) return;
                _.NextTick(() =>
                {
                    _.Controllers.Remove(this);
                    Destroy(gameObject);
                });
            }

            private Vector3 TryFindPosition(PresetConfig preset)
            {
                int attempts = 0;

                while (attempts < 100)
                {
                    attempts++;

                    Vector3 pos = Vector3.zero;

                    if (preset.IsRoadOrBiomeFile)
                    {
                        object point = Name switch
                        {
                            "Arid" or "Temperate" or "Tundra" or "Arctic" or "Jungle" => _.NpcSpawn.Call("GetSpawnPoint", Name),
                            "ExtraNarrow" or "ExtraWide" or "Standard" => _.NpcSpawn.Call("GetRoadSpawnPoint", Name)
                        };
                        if (point is not Vector3 vector3 || IsInsideSafeZone(vector3)) continue;
                        pos = vector3;
                    }
                    else if (preset.IsEventFile || preset.SpawnType == SpawnType.Random)
                        pos = GetRandomSpawnPos();
                    else if (preset.SpawnType == SpawnType.Custom)
                        pos = preset.PositionsForSpawn.GetRandom();

                    if (pos == Vector3.zero) continue;

                    if (HasNpcTooClose(pos)) continue;

                    return pos;
                }

                return Vector3.zero;

                static bool IsInsideSafeZone(Vector3 pos)
                {
                    if (TriggerSafeZone.allSafeZones.Count == 0) return false;

                    float range = _.Cfg.SafeZoneRange;
                    float rangeSqr = range * range;

                    foreach (TriggerSafeZone safeZone in TriggerSafeZone.allSafeZones)
                    {
                        Vector3 center = safeZone.transform.position;
                        float sqr = (pos - center).sqrMagnitude;
                        if (sqr < rangeSqr) return true;
                    }

                    return false;
                }

                Vector3 GetRandomSpawnPos()
                {
                    int attempts = 0;
                    while (attempts < 10)
                    {
                        attempts++;

                        float x = UnityEngine.Random.Range(-Size.x, Size.x);
                        float z = UnityEngine.Random.Range(-Size.z, Size.z);
                        Vector3 pos = transform.GetGlobalPosition(new Vector3(x, 100f, z));

                        if (pos.IsAvailableTopology(82050, false)) continue; //TerrainTopology.Enum.Ocean | TerrainTopology.Enum.River | TerrainTopology.Enum.Lake | TerrainTopology.Enum.Cliff

                        if (!pos.IsRaycast(200f, 8454144, out RaycastHit raycastHit)) continue; //1 << 16 | 1 << 23
                        pos.y = raycastHit.point.y;

                        if (!pos.IsNavMesh(2f, 1, -1372625422, out NavMeshHit navmeshHit)) continue;
                        pos = navmeshHit.position;

                        if (IsInsideSafeZone(pos)) continue;

                        if (Math.Abs(transform.position.y - pos.y) > 3f) continue;

                        return pos;
                    }
                    return Vector3.zero;
                }
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public void UpdateDeadNpcTime(int lossTime)
            {
                if (RespawnQueue.Count == 0 || IsEvent) return;
                for (int i = RespawnQueue.Count - 1; i >= 0; i--)
                {
                    RespawnNpcState info = RespawnQueue[i];
                    info.TimeToSpawn -= lossTime;
                    if (info.TimeToSpawn <= 0)
                    {
                        RespawnQueue.Remove(info);
                        int amountPresetConfig = GetShouldAmountNpc(info.Preset);
                        int amountPreset = GetAmountNpc(info.Preset);
                        if (amountPresetConfig > amountPreset) SpawnNpc(info.Preset);
                    }
                }
            }

            public bool CanRemoveNpc(BaseCombatEntity npc)
            {
                if (!npc.IsExists()) return false;

                string displayName = npc switch
                {
                    global::HumanNPC humanNpc => humanNpc.displayName,
                    ScientistNPC2 scientistNpc2 => scientistNpc2.displayName,
                    _ => string.Empty
                };

                if (_.Cfg.WhitelistNpc.Contains(npc.GetType().Name)) return false;
                if (!string.IsNullOrEmpty(displayName) && _.Cfg.WhitelistNpc.Contains(displayName)) return false;
                if (_.Cfg.WhitelistNpc.Contains(npc.skinID.ToString())) return false;
                if (_.Cfg.WhitelistNpc.Contains(npc.ShortPrefabName)) return false;

                if (!IsInsidePos(npc.transform.position)) return false;

                string key = IsTunnel ? "Tunnel" : IsUnderwaterLab ? "Underwater Lab" : IsIsland ? "Deep Sea Island" : IsGhostShip ? "Ghost Ship" : IsCustom ? "Custom" : Name;
                return _.DefaultScientists.TryGetValue(key, out HashSet<string> shortnames) && shortnames.Contains(npc.ShortPrefabName);
            }

            private bool IsInsidePos(Vector3 pos)
            {
                Vector3 localPos = transform.GetLocalPosition(pos);
                if (localPos.x < -Size.x || localPos.x > Size.x) return false;
                if (localPos.y < -Size.y || localPos.y > Size.y) return false;
                if (localPos.z < -Size.z || localPos.z > Size.z) return false;
                return true;
            }
        }

        private void DisableCh47()
        {
            Unsubscribe("OnHelicopterDropCrate");
            Ch47Crates.Clear();
            Ch47Crates = null;
        }
        private static bool IsGhostShip(string name) => name.Contains("Ghostship");

        private IEnumerator LoadRoadSpawnPoints()
        {
            string shortPath = "BetterNpc/Road/";
		   		 		  						  	   		   					  			 		   					  	 		
            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            foreach (string filePath in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = filePath.GetFileName();
                TryReadRoadOrBiomeSpawnPoint(RoadSpawnPoints, fileName, shortPath);
                yield return CoroutineEx.waitForSeconds(0.1f);
            }
        }
        
                [ChatCommand("SpawnPointAdd")]
        private void ChatCommandSpawnPointAdd(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }

            string name = GetNameArgs(args, 0);

            CustomMonumentSpawnPoint spawnPoint = new CustomMonumentSpawnPoint
            {
                Enabled = true,
                Presets = new List<PresetConfig>
                {
                    new PresetConfig
                    {
                        Enabled = true,
                        MinDay = 1,
                        MaxDay = 1,
                        MinNight = 1,
                        MaxNight = 1,
                        RespawnMinTime = 600,
                        RespawnMaxTime = 600,
                        PresetName = "default",
                        Config = null,
                        Economics = new Dictionary<string, double>(),
                        SpawnType = SpawnType.Random,
                        CustomPositions = new List<string>()
                    }
                },
                Size = "(9, 9, 9)",
                RemoveDefaultNpc = true,
                Id = 0f,
                MapMarkerName = string.Empty,
                Position = player.transform.position.ToString(),
                Rotation = "(0, 0, 0)"
            };

            Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Custom/{name}", spawnPoint);
            CustomSpawnPoints.Add(name, spawnPoint);

            ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
            controller.Init(spawnPoint, player.transform.position, Quaternion.identity, name);
            Controllers.Add(controller);

            PrintToChat(player, $"You <color=#738d43>have successfully added</color> a new spawn point named <color=#55aaff>{name}</color>. You <color=#738d43>can edit</color> this spawn point in the file <color=#55aaff>BetterNpc/Custom/{name}</color>");
            Puts($"Custom location {name} has been successfully loaded!");
        }

        public class RoadOrBiomeSpawnPoint : SpawnPoint
        {
            [JsonProperty(En ? "Minimum distance that must be maintained between nearby NPCs [m]" : "Минимальное расстояние, которое должно соблюдаться между ближайшими NPC [м]", Order = 1)] public float MinDistanceBetweenNpc { get; set; }
        }

        private void AnalyzingMonumentSpawnPoint(MonumentSpawnPoint spawnPoint, string name)
        {
            if (!spawnPoint.Size.CorrectVector3()) spawnPoint.Size = "(9.0, 9.0, 9.0)";
            AnalyzingSpawnPoint(spawnPoint, name);
        }

        private IEnumerator LoadMonumentSpawnPoints()
        {
            string shortPath = "BetterNpc/Monument/";

            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);

            foreach (string filePath in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = filePath.GetFileName();
                TryReadMonumentSpawnPoint(MonumentSpawnPoints, fileName, shortPath);
                yield return CoroutineEx.waitForSeconds(0.1f);
            }
        }

        private void OnEntitySpawned(HackableLockedCrate crate)
        {
            if (crate == null || Ch47Crates == null) return;
		   		 		  						  	   		   					  			 		   					  	 		
            Vector3 pos = crate.transform.position;
            pos.y = TerrainMeta.HeightMap.GetHeight(crate.transform.position);
		   		 		  						  	   		   					  			 		   					  	 		
            CrateCh47 crateCh47 = Ch47Crates.FirstOrDefault(x => x.Crate == null && Vector3.Distance(x.Position, pos) < EventSpawnPoints["CH47"].Radius);
            if (crateCh47 == null) return;

            crateCh47.Crate = crate;
        }

        private bool TryReadCustomMonumentSpawnPoint(Dictionary<string, CustomMonumentSpawnPoint> dictionary, string fileName)
        {
            string shortPath = "BetterNpc/Custom/";

            if (dictionary.TryGetValue(fileName, out CustomMonumentSpawnPoint oldSpawnPoint))
            {
                TryClearPresetUsage(oldSpawnPoint, fileName);
                dictionary.Remove(fileName);
            }

            CustomMonumentSpawnPoint spawnPoint = Interface.Oxide.DataFileSystem.ReadObject<CustomMonumentSpawnPoint>($"{shortPath}{fileName}");
            if (spawnPoint == null)
            {
                PrintError($"File {fileName} is corrupted and cannot be loaded!");
                return false;
            }

            if (!spawnPoint.Id.AreEqual(0f) && !Ids.Any(x => x.AreEqual(spawnPoint.Id)))
            {
                if (!Cfg.EnabledMinLogs) PrintWarning($"File {fileName} cannot be loaded on the current map!");
                return false;
            }

            if (!Cfg.EnabledMinLogs) Puts($"File {fileName} has been loaded successfully!");

            AnalyzingCustomMonumentSpawnPoint(spawnPoint, fileName);
            Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{fileName}", spawnPoint);
            dictionary.Add(fileName, spawnPoint);

            return true;
        }

        public class CargoControllerSpawnPoint : BaseControllerSpawnPoint
        {
            public CargoShip Cargo { get; set; } = null;

            private Queue<NPCSpawner> Spawners { get; set; } = new Queue<NPCSpawner>();

            public void Init(CargoSpawnPoint spawnPoint, CargoShip cargo)
            {
                Cargo = cargo;

                foreach (PresetConfig preset in spawnPoint.Presets) if (preset.Enabled) Presets.Add(preset);

                UpdateSpawners(cargo);

                foreach (PresetConfig preset in Presets)
                {
                    int amount = GetShouldAmountNpc(preset);
                    for (int i = 0; i < amount; i++) SpawnNpc(preset);
                }
            }

            private void OnDestroy() => Destroy();
            public override void Destroy()
            {
                base.Destroy();
                if (Spawners != null)
                {
                    Spawners.Clear();
                    Spawners = null;
                }
            }

            public void RespawnPresets()
            {
                RespawnQueue.Clear();
                UpdatePopulation();
            }

            private void UpdateSpawners(CargoShip cargo)
            {
                foreach (Component component in cargo.GetComponentsInChildren<Component>())
                    if (component is NPCSpawner spawner && spawner.AStarGraph != null)
                        Spawners.Enqueue(spawner);
            }

            public override void SpawnNpc(PresetConfig preset)
            {
                (Vector3 local, Vector3 global, NPCSpawner spawner) = TryFindPosition(preset);

                ScientistNPC npc = SpawnNpcByPos(preset, global);
                if (npc == null) return;

                _.NpcSpawn.Call("SetParent", npc, transform, local, 0.5f);

                if (spawner != null)
                {
                    _.NextTick(() =>
                    {
                        BaseNavigator navigator = npc.Brain.Navigator;
                        navigator.CanUseNavMesh = false;
                        navigator.Path = spawner.Path;
                        navigator.AStarGraph = spawner.AStarGraph;
                        navigator.CanUseAStar = true;
                    });
                }
            }
		   		 		  						  	   		   					  			 		   					  	 		
            private (Vector3 local, Vector3 global, NPCSpawner spawner) TryFindPosition(PresetConfig preset)
            {
                bool stationary = (bool)_.NpcSpawn.Call("IsStationaryPreset", preset.PresetName);

                if (stationary)
                {
                    foreach (string str in preset.CustomPositions)
                    {
                        Vector3 local = str.ToVector3();
                        Vector3 global1 = transform.GetGlobalPosition(local);
                        if (HasNpcTooClose(global1)) continue;
                        return (local, global1, null);
                    }
                }

                NPCSpawner spawner = Spawners.Dequeue();
                Spawners.Enqueue(spawner);

                BasePathNode node = spawner.AStarGraph.nodes.GetRandom();
                Vector3 global = node.Position;

                return (transform.GetLocalPosition(global), global, spawner);
            }
        }

        private void DisableHelicopter()
        {
            HelicopterCrates.Clear();
            HelicopterCrates = null;
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private HashSet<float> Ids { get; set; } = new HashSet<float>();

        protected override void LoadConfig()
        {
            base.LoadConfig();
            Cfg = Config.ReadObject<PluginConfig>();

            if (Cfg.PluginVersion < new VersionNumber(2, 0, 0))
            {
                IsOldData = true;
                return;
            }

            if (Cfg.SafeZoneRange < 0f) Cfg.SafeZoneRange = 0f;
            if (string.IsNullOrWhiteSpace(Cfg.StartDayTime) || string.IsNullOrWhiteSpace(Cfg.StartNightTime) || Cfg.WhitelistNpc == null)
            {
                PluginConfig defaultConfig = PluginConfig.DefaultConfig();
                if (string.IsNullOrWhiteSpace(Cfg.StartDayTime)) Cfg.StartDayTime = defaultConfig.StartDayTime;
                if (string.IsNullOrWhiteSpace(Cfg.StartNightTime)) Cfg.StartNightTime = defaultConfig.StartNightTime;
                Cfg.WhitelistNpc ??= defaultConfig.WhitelistNpc;
                SaveConfig();
            }

            if (Cfg.PluginVersion < Version) UpdateConfigValues();
        }

        private void OnNpcSpawnInitialized()
        {
            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in MonumentSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);
            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in UnderwaterLabSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);
            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in TunnelSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);

            foreach (KeyValuePair<string, CustomMonumentSpawnPoint> kvp in CustomSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);

            foreach (KeyValuePair<string, EventSpawnPoint> kvp in EventSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);
            if (CargoConfig != null) TryRegisterPresetsUsage(CargoConfig, "CargoShip");

            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in RoadSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);
            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in BiomeSpawnPoints) TryRegisterPresetsUsage(kvp.Value, kvp.Key);
        }
        
                private Dictionary<ulong, HashSet<ulong>> BradleyCrates { get; set; } = new Dictionary<ulong, HashSet<ulong>>();

        private static bool IsInDayRange(float current, float startDay, float startNight)
        {
            if (startDay.AreEqual(startNight)) return false;
            if (startDay < startNight) return current >= startDay && current < startNight;
            return current >= startDay || current < startNight;
        }

        private void CreateController(string name, Vector3 position)
        {
            if (MonumentSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint1))
            {
                if (!spawnPoint1.Enabled) return;

                Transform closest = null;
                float closestDistSqr = float.MaxValue;

                if (IsDeepSeaIsland(name))
                {
                    if (!IsDeepSeaActive) return;
                    foreach (DeepSeaIsland island in DeepSeaManager.ServerIslands)
                    {
                        string monumentName = GetNameMonument(island);
                        if (string.IsNullOrEmpty(monumentName) || monumentName != name) continue;
                        if (IsNearest(island.transform, closestDistSqr, out float distSqr))
                        {
                            closest = island.transform;
                            closestDistSqr = distSqr;
                        }
                    }
                    TryCreateMonument(spawnPoint1, closest, false, false, true);
                }
                else if (IsGhostShip(name))
                {
                    if (!IsDeepSeaActive) return;
                    foreach (GhostShip ship in DeepSeaManager.ServerGhostShips)
                    {
                        string monumentName = GetNameMonument(ship);
                        if (string.IsNullOrEmpty(monumentName) || monumentName != name) continue;
                        if (IsNearest(ship.transform, closestDistSqr, out float distSqr))
                        {
                            closest = ship.transform;
                            closestDistSqr = distSqr;
                        }
                    }
                    TryCreateMonument(spawnPoint1, closest, false, false, true);
                }
                else
                {
                    foreach (MonumentInfo monument in TerrainMeta.Path.Monuments)
                    {
                        string monumentName = GetNameMonument(monument);
                        if (string.IsNullOrEmpty(monumentName) || monumentName != name) continue;
                        if (IsNearest(monument.transform, closestDistSqr, out float distSqr))
                        {
                            closest = monument.transform;
                            closestDistSqr = distSqr;
                        }
                    }
                    TryCreateMonument(spawnPoint1, closest, false, false, false);
                }
            }
            else if (UnderwaterLabSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint2))
            {
                if (!spawnPoint2.Enabled) return;

                Transform closest = null;
                float closestDistSqr = float.MaxValue;

                foreach (DungeonBaseInfo baseModule in TerrainMeta.Path.DungeonBaseEntrances)
                {
                    if (baseModule.name == name)
                    {
                        if (IsNearest(baseModule.transform, closestDistSqr, out float distSqr))
                        {
                            closest = baseModule.transform;
                            closestDistSqr = distSqr;
                        }
                    }
                    foreach (GameObject module in baseModule.Links)
                    {
                        string moduleName = module.name.GetFileName();
                        if (moduleName != name) continue;
                        if (IsNearest(module.transform, closestDistSqr, out float distSqr))
                        {
                            closest = baseModule.transform;
                            closestDistSqr = distSqr;
                        }
                    }
                }

                TryCreateMonument(spawnPoint2, closest, true, false, false);
            }
            else if (TunnelSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint3))
            {
                if (!spawnPoint3.Enabled) return;

                Transform closest = null;
                float closestDistSqr = float.MaxValue;

                foreach (DungeonGridCell gridCell in TerrainMeta.Path.DungeonGridCells)
                {
                    string cellName = gridCell.name.GetFileName();
                    if (cellName != name) continue;
                    if (IsNearest(gridCell.transform, closestDistSqr, out float distSqr))
                    {
                        closest = gridCell.transform;
                        closestDistSqr = distSqr;
                    }
                }

                TryCreateMonument(spawnPoint3, closest, false, true, false);
            }

            return;

            void TryCreateMonument(MonumentSpawnPoint spawnPoint, Transform transform, bool isUnderWater, bool isTunnel, bool isIsland)
            {
                if (transform == null || HasController(transform.position, name)) return;
                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsUnderwaterLab = isUnderWater;
                controller.IsTunnel = isTunnel;
                controller.IsIsland = isIsland;
                controller.Init(spawnPoint, transform.position, transform.rotation, name);
                Controllers.Add(controller);
            }

            bool IsNearest(Transform tr, float closestDistSqr, out float distSqr)
            {
                distSqr = (position - tr.position).sqrMagnitude;
                return distSqr < closestDistSqr;
            }
        }

        private void TryReadCargoSpawnPoint()
        {
            TryClearPresetUsage(CargoConfig, "CargoShip");

            CargoConfig = Interface.Oxide.DataFileSystem.ReadObject<CargoSpawnPoint>("BetterNpc/Event/CargoShip");
            if (CargoConfig == null)
            {
                PrintError("File CargoShip is corrupted and cannot be loaded!");
                return;
            }

            foreach (PresetConfig preset in CargoConfig.Presets) preset.IsCargoFile = true;
            if (!Cfg.EnabledMinLogs) Puts("File CargoShip has been loaded successfully!");
            AnalyzingSpawnPoint(CargoConfig, "CargoShip");
            Interface.Oxide.DataFileSystem.WriteObject("BetterNpc/Event/CargoShip", CargoConfig);
            if (CargoConfig is { RemoveDefaultNpc: true, Enabled: true }) ConVar.AI.npc_spawn_on_cargo_ship = false;
        }

        private void OnEntitySpawned(CargoShip cargo)
        {
            if (cargo == null || CargoConfig is not { Enabled: true }) return;

            if (Interface.CallHook("CanCargoShipSpawnNpc", cargo) is bool) return;

            CargoControllerSpawnPoint controller = cargo.gameObject.AddComponent<CargoControllerSpawnPoint>();
            controller.Init(CargoConfig, cargo);
            CargoControllers.Add(controller);
        }

        private void OnEntityDeath(PatrolHelicopter helicopter, HitInfo info)
        {
            if (helicopter == null || helicopter.net == null || HelicopterCrates == null) return;

            if (Interface.CallHook("CanHelicopterSpawnNpc", helicopter) is bool) return;

            Vector3 pos = helicopter.transform.position;
            pos.y = TerrainMeta.HeightMap.GetHeight(pos);
            if (pos.IsAvailableTopology(82048, false)) return; //TerrainTopology.Enum.Ocean | TerrainTopology.Enum.River | TerrainTopology.Enum.Lake

            if (!EventSpawnPoints.TryGetValue("Helicopter", out EventSpawnPoint spawnPoint)) return;

            ulong id = helicopter.net.ID.Value;
            string name = id.ToString();

            ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
            controller.Init(spawnPoint, pos, Quaternion.identity, name);
            Controllers.Add(controller);

            HelicopterCrates.Add(id, new HashSet<ulong>());
        }

        internal void SendBalance(ulong playerId, Dictionary<string, double> dic)
        {
            if (dic == null || dic.Count == 0) return;

            if (plugins.Exists("Economics") && TryGetAmount(dic, out double amount1, "economics"))
                Economics?.Call("Deposit", playerId.ToString(), amount1);

            if (plugins.Exists("ServerRewards") && TryGetAmount(dic, out double amount2, "serverrewards"))
            {
                int amount = amount2.SafeToInt();
                if (amount > 0) ServerRewards?.Call("AddPoints", playerId, amount);
            }

            if (plugins.Exists("IQEconomic") && TryGetAmount(dic, out double amount3, "iqeconomic"))
            {
                int amount = amount3.SafeToInt();
                if (amount > 0) IQEconomic?.Call("API_SET_BALANCE", playerId, amount);
            }

            if (plugins.Exists("XPerience") && TryGetAmount(dic, out double amount4, "xperience"))
            {
                BasePlayer player = BasePlayer.FindByID(playerId);
                if (player != null) XPerience?.Call("GiveXP", player, amount4);
            }
        }
        
                private Dictionary<string, RoadOrBiomeSpawnPoint> BiomeSpawnPoints { get; set; } = new Dictionary<string, RoadOrBiomeSpawnPoint>();

        private object OnCargoShipSpawnCrate(CargoShip cargo)
        {
            if (cargo == null || CargoConfig is not { RespawnNpcCrates: true }) return null;

            CargoControllerSpawnPoint controller = CargoControllers.FirstOrDefault(x => x.Cargo == cargo);
            if (controller == null) return null;

            controller.RespawnPresets();

            return null;
        }

        public class BradleySpawnPoint : EventSpawnPoint
        {
            [JsonProperty(En ? "Remove default NPCs? [true/false]" : "Удалить стандартных NPC? [true/false]", Order = 2)] public bool RemoveDefaultNpc { get; set; }
        }

        private static float ParseHourOrDefaultNormalized(string input, float fallback)
        {
            float hours;

            if (string.IsNullOrWhiteSpace(input)) hours = fallback;
            else if (TimeSpan.TryParse(input, out TimeSpan ts)) hours = (float)ts.TotalHours;
            else if (!float.TryParse(input, System.Globalization.NumberStyles.Float, System.Globalization.CultureInfo.InvariantCulture, out hours)) hours = fallback;

            hours %= 24f;
            if (hours < 0f) hours += 24f;

            return hours;
        }

        private bool IsOldData { get; set; } = false;
		   		 		  						  	   		   					  			 		   					  	 		
        private static string RemoveWhitespace(string input)
        {
            if (string.IsNullOrEmpty(input)) return string.Empty;
		   		 		  						  	   		   					  			 		   					  	 		
            System.Text.StringBuilder sb = new System.Text.StringBuilder(input.Length);

            for (int i = 0; i < input.Length; i++)
            {
                char c = input[i];
                if (!char.IsWhiteSpace(c)) sb.Append(c);
            }

            return sb.ToString();
        }

        private void OnCargoPlaneSignaled(CargoPlane cargoPlane, SupplySignal supplySignal)
        {
            if (cargoPlane == null) return;
            if (!CargoPlanesSignaled.Contains(cargoPlane))
                CargoPlanesSignaled.Add(cargoPlane);
        }
        
                private CargoSpawnPoint CargoConfig { get; set; } = null;

        private void OnEntityDeath(BradleyAPC bradley, HitInfo info)
        {
            if (bradley == null || bradley.net == null || BradleyCrates == null) return;

            if (Interface.CallHook("CanBradleySpawnNpc", bradley) is bool) return;

            Vector3 pos = bradley.transform.position;
            pos.y = TerrainMeta.HeightMap.GetHeight(pos);

            if (!EventSpawnPoints.TryGetValue("Bradley", out EventSpawnPoint spawnPoint)) return;

            ulong id = bradley.net.ID.Value;
            string name = id.ToString();

            ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
            controller.Init(spawnPoint, pos, Quaternion.identity, name);
            Controllers.Add(controller);

            BradleyCrates.Add(id, new HashSet<ulong>());
        }
        
                private Coroutine InitializationCoroutine { get; set; } = null;
		   		 		  						  	   		   					  			 		   					  	 		
        [ChatCommand("ShowFailedNavMesh")]
        private void ChatCommandShowFailedNavMesh(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;
            float duration = args is { Length: 1 } ? Convert.ToSingle(args[0]) : 60f;
            CheckFailedNavMesh(player, duration);
            PrintToChat(player, "NavMesh validation completed. All spawn points have been checked for ground and navigation grid issues. Please review the console for warnings or errors");
        }

        private void UpdateConfigValues()
        {
            Puts("Config update detected! Updating config values...");
            Cfg.PluginVersion = Version;
            Puts("Config update completed!");
            SaveConfig();
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private IEnumerator SpawnRoadSpawnPoints()
        {
            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in RoadSpawnPoints)
            {
                if (!kvp.Value.Enabled) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.Init(kvp.Value, Vector3.zero, Quaternion.identity, kvp.Key);
                Controllers.Add(controller);

                if (!Cfg.EnabledMinLogs) Puts($"Road {kvp.Key} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.4f);
            }
        }

        private bool TryReadMonumentSpawnPoint(Dictionary<string, MonumentSpawnPoint> dictionary, string fileName, string shortPath)
        {
            if (dictionary.TryGetValue(fileName, out MonumentSpawnPoint oldSpawnPoint))
            {
                TryClearPresetUsage(oldSpawnPoint, fileName);
                dictionary.Remove(fileName);
            }

            MonumentSpawnPoint spawnPoint = Interface.Oxide.DataFileSystem.ReadObject<MonumentSpawnPoint>($"{shortPath}{fileName}");
            if (spawnPoint == null)
            {
                PrintError($"File {fileName} is corrupted and cannot be loaded!");
                return false;
            }

            if (!Cfg.EnabledMinLogs) Puts($"File {fileName} has been loaded successfully!");
            AnalyzingMonumentSpawnPoint(spawnPoint, fileName);
            Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{fileName}", spawnPoint);
            dictionary.Add(fileName, spawnPoint);

            return true;
        }
                
                private Dictionary<string, RoadOrBiomeSpawnPoint> RoadSpawnPoints { get; set; } = new Dictionary<string, RoadOrBiomeSpawnPoint>();

        private IEnumerator SpawnTunnelSpawnPoints()
        {
            foreach (DungeonGridCell gridCell in TerrainMeta.Path.DungeonGridCells)
            {
                string cellName = gridCell.name.GetFileName();

                if (!TunnelSpawnPoints.TryGetValue(cellName, out MonumentSpawnPoint spawnPoint)) continue;
                if (!spawnPoint.Enabled) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsTunnel = true;
                controller.Init(spawnPoint, gridCell.transform.position, gridCell.transform.rotation, cellName);
                Controllers.Add(controller);

                if (!Cfg.EnabledMinLogs) Puts($"Tunnel Module {cellName} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.2f);
            }
        }

        private IEnumerator SpawnDeepSeaIslandSpawnPoints()
        {
            foreach (DeepSeaIsland island in DeepSeaManager.ServerIslands)
            {
                string monumentName = GetNameMonument(island);
                if (string.IsNullOrEmpty(monumentName)) continue;

                if (!MonumentSpawnPoints.TryGetValue(monumentName, out MonumentSpawnPoint spawnPoint)) continue;
                if (!spawnPoint.Enabled) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsIsland = true;
                controller.Init(spawnPoint, island.transform.position, island.transform.rotation, monumentName);
                Controllers.Add(controller);

                if (!Cfg.EnabledMinLogs) Puts($"Monument {monumentName} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.2f);
            }
        }

        private void TryReadBradleySpawnPoint()
        {
            if (EventSpawnPoints.TryGetValue("Bradley", out EventSpawnPoint oldSpawnPoint))
            {
                TryClearPresetUsage(oldSpawnPoint, "Bradley");
                EventSpawnPoints.Remove("Bradley");
            }

            string shortPath = "BetterNpc/Event/";

            BradleySpawnPoint spawnPoint = Interface.Oxide.DataFileSystem.ReadObject<BradleySpawnPoint>($"{shortPath}Bradley");
            if (spawnPoint == null)
            {
                PrintError("File Bradley is corrupted and cannot be loaded!");
                return;
            }

            foreach (PresetConfig preset in spawnPoint.Presets) preset.IsEventFile = true;
            if (!Cfg.EnabledMinLogs) Puts("File Bradley has been loaded successfully!");
            AnalyzingEventSpawnPoint(spawnPoint, "Bradley");
            Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}Bradley", spawnPoint);
            EventSpawnPoints.Add("Bradley", spawnPoint);
        }
                private const bool En = false;

        private void OnCrateSpawned(BradleyAPC bradley, LockedByEntCrate crate)
        {
            if (BradleyCrates == null) return;
            if (crate == null || crate.net == null) return;
            if (bradley == null || bradley.net == null) return;
            if (BradleyCrates.TryGetValue(bradley.net.ID.Value, out HashSet<ulong> crates)) crates.Add(crate.net.ID.Value);
        }

        private void AnalyzingEventSpawnPoint(EventSpawnPoint spawnPoint, string name)
        {
            if (spawnPoint.Radius < 9f) spawnPoint.Radius = 9f;
            AnalyzingSpawnPoint(spawnPoint, name);
        }
		   		 		  						  	   		   					  			 		   					  	 		
        [ChatCommand("SpawnPointPos")]
        private void ChatCommandSpawnPointPos(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }

            string name = GetNameArgs(args, 0);
		   		 		  						  	   		   					  			 		   					  	 		
            ControllerSpawnPoint controller = GetNearController(player.transform.position, name);
            if (controller == null)
            {
                PrintToChat(player, $"Spawn Point with the name <color=#55aaff>{name}</color> was <color=#ce3f27>not</color> found!");
                return;
            }

            Vector3 local = controller.transform.GetLocalPosition(player.transform.position);

            Puts($"Spawn Point: {name}. Position: {local}");
            PrintToChat(player, $"Spawn Point: <color=#55aaff>{name}</color>\nPosition: <color=#55aaff>{local}</color>");
        }

        protected override void SaveConfig() => Config.WriteObject(Cfg);

        public class SpawnPoint
        {
            [JsonProperty(En ? "Enabled? [true/false]" : "Включен? [true/false]", Order = 0)] public bool Enabled { get; set; }
            [JsonProperty(En ? "Presets" : "Наборы NPC", Order = 7)] public List<PresetConfig> Presets { get; set; }
        }

        private Dictionary<string, Transform> MonumentMarkers { get; set; } = new Dictionary<string, Transform>();

        private void DestroyEventControllers()
        {
            foreach (ControllerSpawnPoint point in Controllers.Where(x => x.IsEvent))
            {
                Controllers.Remove(point);
                UnityEngine.Object.Destroy(point.gameObject);
            }
        }

        [ConsoleCommand("ShowFailedNavMesh")]
        private void ConsoleCommandShowFailedNavMesh(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null) return;
            CheckFailedNavMesh();
            Puts("NavMesh validation completed. All spawn points have been checked for ground and navigation grid issues. Please review the console for warnings or errors");
        }

        private void CheckFailedNavMesh(BasePlayer player = null, float duration = 60f)
        {
            HashSet<FailedNavMeshPoint> points = new HashSet<FailedNavMeshPoint>();

            foreach (ControllerSpawnPoint controller in Controllers)
            {
                int presetIndex = 0;
                foreach (PresetConfig preset in controller.Presets)
                {
                    presetIndex++;

                    if (preset.PositionsForSpawn == null || preset.PositionsForSpawn.Count == 0) continue;

                    bool isCheckByPresetName = preset.Config == null;

                    int areaMask = isCheckByPresetName ? (int)NpcSpawn.Call("GetAreaMask", preset.PresetName) : (preset.Config.NavigationGridType == 0 ? 1 : 25);
                    if (areaMask == 0) continue;

                    int agentTypeId = areaMask == 1 ? -1372625422 : 0;

                    int altAreaMask = areaMask == 1 ? 25 : 1;
                    int altAgentTypeId = agentTypeId == 0 ? -1372625422 : 0;

                    bool isStationary = isCheckByPresetName ? (bool)NpcSpawn.Call("IsStationaryPreset", preset.PresetName) : preset.Config.IsStationary;
		   		 		  						  	   		   					  			 		   					  	 		
                    int posIndex = 0;
                    foreach (Vector3 pos in preset.PositionsForSpawn)
                    {
                        posIndex++;

                        bool hasGround = Physics.Raycast(pos.AddToY(0.75f), Vector3.down, out RaycastHit raycastHit, 1.5f);
                        bool hasNavMesh = pos.IsNavMesh(1.5f, areaMask, agentTypeId, out NavMeshHit navMeshHit);

                        if (hasGround && hasNavMesh) continue;

                        if (points.Any(x => x.ControllerName == controller.Name && ((isCheckByPresetName && x.PresetName == preset.PresetName) || (!isCheckByPresetName && x.PresetIndex == presetIndex)) && x.Index == posIndex)) continue;

                        bool hasAltNavMesh = pos.IsNavMesh(1.5f, altAreaMask, altAgentTypeId, out navMeshHit);

                        points.Add(new FailedNavMeshPoint
                        {
                            ControllerName = controller.Name,
                            PresetName = isCheckByPresetName ? preset.PresetName : string.Empty,
                            PresetIndex = !isCheckByPresetName ? presetIndex : -1,
                            Index = posIndex,
                            Position = pos,
                            IsStationary = isStationary,
                            AreaMask = areaMask,
                            HasGround = hasGround,
                            HasNavMesh = hasNavMesh,
                            HasAltNavMesh = hasAltNavMesh
                        });
                    }
                }
            }

            foreach (FailedNavMeshPoint point in points)
            {
                if (!point.HasNavMesh && point.IsStationary && point.HasGround) continue;

                string presetInfo = point.PresetIndex == -1 ? $"Preset: {point.PresetName}" : $"Preset index (only enabled presets are counted): {point.PresetIndex}";

                if (!point.HasNavMesh && !point.HasGround)
                {
                    PrintError($"SpawnPoint: {point.ControllerName}. {presetInfo}. Index: {point.Index}. No surface and no navigation grid");
                    if (player != null)
                    {
                        DebugDrawEx.Line(player, point.Position.AddToY(100f), point.Position, Color.red, duration);
                        DebugDrawEx.Sphere(player, point.Position, Color.red, 1.5f, duration);
                    }
                }
                else if (!point.HasGround)
                {
                    PrintWarning($"SpawnPoint: {point.ControllerName}. {presetInfo}. Index: {point.Index}. No surface");
                    if (player != null)
                    {
                        DebugDrawEx.Line(player, point.Position.AddToY(100f), point.Position, Color.yellow, duration);
                        DebugDrawEx.Sphere(player, point.Position, Color.yellow, 1.5f, duration);
                    }
                }
                else if (!point.HasNavMesh)
                {
                    if (point.HasAltNavMesh)
                    {
                        PrintWarning($"SpawnPoint: {point.ControllerName}. {presetInfo}. Index: {point.Index}. Wrong type of navigation grid ({(point.AreaMask == 1 ? 0 : 1)} -> {(point.AreaMask == 1 ? 1 : 0)})");
                        if (player != null)
                        {
                            DebugDrawEx.Line(player, point.Position.AddToY(100f), point.Position, Color.yellow, duration);
                            DebugDrawEx.Sphere(player, point.Position, Color.yellow, 1.5f, duration);
                        }
                    }
                    else
                    {
                        PrintWarning($"SpawnPoint: {point.ControllerName}. {presetInfo}. Index: {point.Index}. No navigation grid");
                        if (player != null)
                        {
                            DebugDrawEx.Line(player, point.Position.AddToY(100f), point.Position, Color.yellow, duration);
                            DebugDrawEx.Sphere(player, point.Position, Color.yellow, 1.5f, duration);
                        }
                    }
                }
            }

            points.Clear();
            points = null;
        }

        private static string GetNameMonument(MonumentInfo monument)
        {
            if (monument == null || monument.name == null || monument.displayPhrase?.english == null) return string.Empty;

            string name = monument.displayPhrase.english.Replace("\n", string.Empty);

            if (monument.name.Contains("harbor_1")) return "Small " + name;
            else if (monument.name.Contains("harbor_2")) return "Large " + name;
            else if (monument.name.Contains("desert_military_base_a")) return name + " A";
            else if (monument.name.Contains("desert_military_base_b")) return name + " B";
            else if (monument.name.Contains("desert_military_base_c")) return name + " C";
            else if (monument.name.Contains("desert_military_base_d")) return name + " D";
            else if (monument.name.Contains("power_sub_small_1")) return "Power Sub Small 1";
            else if (monument.name.Contains("power_sub_small_2")) return "Power Sub Small 2";
            else if (monument.name.Contains("power_sub_big_1")) return "Power Sub Big 1";
            else if (monument.name.Contains("power_sub_big_2")) return "Power Sub Big 2";
            else if (monument.name.Contains("jungle_ruins_a")) return "Jungle Ruins A";
            else if (monument.name.Contains("jungle_ruins_b")) return "Jungle Ruins B";
            else if (monument.name.Contains("jungle_ruins_c")) return "Jungle Ruins C";
            else if (monument.name.Contains("jungle_ruins_d")) return "Jungle Ruins D";
            else if (monument.name.Contains("jungle_ruins_e")) return "Jungle Ruins E";
            else return name;
        }

        public class MonumentSpawnPoint : SpawnPoint
        {
            [JsonProperty(En ? "Monument size (X, Y, Z)" : "Размер монумента (X, Y, Z)", Order = 1)] public string Size { get; set; }
            [JsonProperty(En ? "Remove default NPCs? [true/false]" : "Удалить стандартных NPC? [true/false]", Order = 2)] public bool RemoveDefaultNpc { get; set; }
        }

        private static bool IsDeepSeaIsland(string name) => name.Contains("Deep Sea Island Tropical");
		   		 		  						  	   		   					  			 		   					  	 		
        public class BaseControllerSpawnPoint : FacepunchBehaviour
        {
            public float SquareDistanceBetweenNpc { get; set; } = 9f;

            public HashSet<PresetConfig> Presets { get; set; } = new HashSet<PresetConfig>();

            public HashSet<ActiveNpcState> ActiveNpcStates { get; set; } = new HashSet<ActiveNpcState>();
            public List<RespawnNpcState> RespawnQueue { get; set; } = new List<RespawnNpcState>();

            public int GetAmountNpc(PresetConfig preset) => ActiveNpcStates.Where(x => x.Preset == preset).Count + RespawnQueue.Where(x => x.Preset == preset).Count;
            public virtual int GetShouldAmountNpc(PresetConfig preset) => _.IsDay ? UnityEngine.Random.Range(preset.MinDay, preset.MaxDay + 1) : UnityEngine.Random.Range(preset.MinNight, preset.MaxNight + 1);

            public virtual void Destroy()
            {
                if (ActiveNpcStates != null)
                {
                    foreach (ActiveNpcState active in ActiveNpcStates) if (active.Npc.IsExists()) active.Npc.Kill();
                    ActiveNpcStates.Clear();
                    ActiveNpcStates = null;
                }

                if (RespawnQueue != null)
                {
                    RespawnQueue.Clear();
                    RespawnQueue = null;
                }

                if (Presets != null)
                {
                    Presets.Clear();
                    Presets = null;
                }
            }

            public void UpdatePopulation()
            {
                foreach (PresetConfig preset in Presets)
                {
                    int shouldAmountNpc = GetShouldAmountNpc(preset);
                    int amountNpc = GetAmountNpc(preset);
                    if (shouldAmountNpc > amountNpc)
                    {
                        int amount = shouldAmountNpc - amountNpc;
                        for (int i = 0; i < amount; i++) SpawnNpc(preset);
                    }
                    else if (shouldAmountNpc < amountNpc)
                    {
                        int amount = amountNpc - shouldAmountNpc;
                        for (int i = 0; i < amount; i++) KillNpc(preset);
                    }
                }
            }

            public virtual void DieNpc(ScientistNPC npc)
            {
                ActiveNpcState activeNpc = ActiveNpcStates.FirstOrDefault(x => x.Npc == npc);
                if (activeNpc == null) return;

                PresetConfig preset = activeNpc.Preset;

                RespawnQueue.Add(new RespawnNpcState { Preset = preset, TimeToSpawn = preset.IsEventFile || preset.IsCargoFile ? 0 : UnityEngine.Random.Range(preset.RespawnMinTime, preset.RespawnMaxTime) });
                ActiveNpcStates.Remove(activeNpc);

                BasePlayer attacker = npc.lastAttacker as BasePlayer;
                if (attacker.IsPlayer()) _.SendBalance(attacker.userID, preset.Economics);
            }

            public virtual void SpawnNpc(PresetConfig preset)
            {
            }

            public ScientistNPC SpawnNpcByPos(PresetConfig preset, Vector3 pos)
            {
                if (pos == Vector3.zero) return null;

                ScientistNPC npc = !string.IsNullOrEmpty(preset.PresetName) ?
                    (ScientistNPC)_.NpcSpawn.Call("SpawnPreset", pos, preset.PresetName) :
                    (ScientistNPC)_.NpcSpawn.Call("SpawnNpc", pos, GetNpcConfigJObject(preset.Config));
                if (npc == null) return null;

                ActiveNpcStates.Add(new ActiveNpcState { Preset = preset, Npc = npc });

                TrySendNpcToPveMode(npc);

                return npc;
            }

            public bool HasNpcTooClose(Vector3 pos)
            {
                foreach (ActiveNpcState info in ActiveNpcStates)
                {
                    if (!info.Npc.IsExists()) continue;
                    Vector3 npcPos = info.Npc.transform.position;
                    float sqr = (npcPos - pos).sqrMagnitude;
                    if (sqr < SquareDistanceBetweenNpc) return true;
                }
                return false;
            }

            private void KillNpc(PresetConfig preset)
            {
                RespawnNpcState dead = RespawnQueue.FirstOrDefault(x => x.Preset == preset);
                if (dead != null)
                {
                    RespawnQueue.Remove(dead);
                    return;
                }

                ActiveNpcState active = ActiveNpcStates.FirstOrDefault(x => x.Preset == preset);
                if (active != null)
                {
                    if (active.Npc.IsExists()) active.Npc.Kill();
                    ActiveNpcStates.Remove(active);
                }
            }

            public bool Contains(ScientistNPC npc) => ActiveNpcStates.Any(x => x.Npc == npc);

            public void TrySendNpcToPveMode(ScientistNPC npc)
            {
                if (_.Cfg.Pve && _.plugins.Exists("PveMode") && _.PveMode != null)
                    _.PveMode.Call("ScientistAddPveMode", npc);
            }

            private static JObject GetNpcConfigJObject(NpcConfig config)
            {
                return new JObject
                {
                    ["Prefab"] = config.Prefab,
                    ["Names"] = config.Names,
                    ["Gender"] = config.Gender,
                    ["SkinTone"] = config.SkinTone,
                    ["Underwear"] = config.Underwear,
                    ["WearItems"] = new JArray { config.WearItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["SkinID"] = x.SkinID }) },
                    ["BeltItems"] = new JArray { config.BeltItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["Amount"] = x.Amount, ["SkinID"] = x.SkinID, ["Mods"] = new JArray { x.Mods }, ["Ammo"] = x.Ammo }) },
                    ["Kit"] = config.Kit,
                    ["DestroyTrapsOnDeath"] = config.DestroyTrapsOnDeath,
                    ["Health"] = config.Health,
                    ["InstantDeathIfHitHead"] = config.InstantDeathIfHitHead,
                    ["RoamRange"] = config.RoamRange,
                    ["ChaseRange"] = config.ChaseRange,
                    ["SenseRange"] = config.SenseRange,
                    ["ListenRange"] = config.ListenRange,
                    ["DamageRange"] = config.DamageRange,
                    ["ShortRange"] = config.ShortRange,
                    ["AttackLengthMaxShortRangeScale"] = config.AttackLengthMaxShortRangeScale,
                    ["AttackRangeMultiplier"] = config.AttackRangeMultiplier,
                    ["CheckVisionCone"] = config.CheckVisionCone,
                    ["VisionCone"] = config.VisionCone,
                    ["HostileTargetsOnly"] = config.HostileTargetsOnly,
                    ["DisplaySashTargetsOnly"] = config.DisplaySashTargetsOnly,
                    ["IgnoreSafeZonePlayers"] = config.IgnoreSafeZonePlayers,
                    ["IgnoreSleepingPlayers"] = config.IgnoreSleepingPlayers,
                    ["IgnoreWoundedPlayers"] = config.IgnoreWoundedPlayers,
                    ["NpcAttackMode"] = config.NpcAttackMode,
                    ["NpcSenseRange"] = config.NpcSenseRange,
                    ["NpcDamageScale"] = config.NpcDamageScale,
                    ["NpcWhitelist"] = config.NpcWhitelist,
                    ["NpcBlacklist"] = config.NpcBlacklist,
                    ["AnimalAttackMode"] = config.AnimalAttackMode,
                    ["AnimalSenseRange"] = config.AnimalSenseRange,
                    ["AnimalDamageScale"] = config.AnimalDamageScale,
                    ["AnimalWhitelist"] = config.AnimalWhitelist,
                    ["AnimalBlacklist"] = config.AnimalBlacklist,
                    ["DamageScale"] = config.DamageScale,
                    ["CanTurretTarget"] = config.CanTurretTarget,
                    ["DamageScaleFromTurret"] = config.DamageScaleFromTurret,
                    ["DamageScaleToTurret"] = config.DamageScaleToTurret,
                    ["AimConeScale"] = config.AimConeScale,
                    ["DisableRadio"] = config.DisableRadio,
                    ["CanRunAwayWater"] = config.CanRunAwayWater,
                    ["CanSleep"] = config.CanSleep,
                    ["SleepDistance"] = config.SleepDistance,
                    ["Speed"] = config.Speed,
                    ["AreaMask"] = config.NavigationGridType == 0 ? 1 : 25,
                    ["AgentTypeID"] = config.NavigationGridType == 0 ? -1372625422 : 0,
                    ["BaseOffSet"] = config.BaseOffSet,
                    ["HomePosition"] = string.Empty,
                    ["MemoryDuration"] = config.MemoryDuration,
                    ["States"] = new JArray { config.IsStationary ? new HashSet<string> { "IdleState", "CombatStationaryState" } : new HashSet<string> { "RoamState", "ChaseState", "CombatState" } },
                    ["LootTable"] = GetLootTableJObject(config.LootTable),
                    ["CratePrefab"] = config.CratePrefab,
                    ["IsRemoveCorpse"] = config.IsRemoveCorpse,
                    ["GroupAlertEnabled"] = config.GroupAlertEnabled,
                    ["GroupAlertRadius"] = config.GroupAlertRadius,
                    ["GroupAlertReceivers"] = config.GroupAlertEnabled ? "All" : string.Empty,
                    ["HeadDamageScale"] = config.HeadDamageScale,
                    ["BodyDamageScale"] = config.BodyDamageScale,
                    ["LegDamageScale"] = config.LegDamageScale
                };
            }

            private static JObject GetLootTableJObject(LootTableConfig config)
            {
                return new JObject
                {
                    ["IsAlphaLoot"] = config.IsAlphaLoot,
                    ["AlphaLootPreset"] = config.AlphaLootPreset,
                    ["IsLoottablePlugin"] = config.IsLoottablePlugin,
                    ["LoottablePreset"] = config.LoottablePreset,
                    ["IsCustomLootPlugin"] = config.IsCustomLootPlugin,
                    ["CustomLootPreset"] = config.CustomLootPreset,
                    ["ClearDefaultLoot"] = config.ClearDefaultLoot,
                    ["PrefabsTable"] = GetPrefabsLootTableJObject(config.PrefabsTable),
                    ["ItemsTable"] = GetItemsLootTableJObject(config.ItemsTable),
                };
            }

            private static JObject GetPrefabsLootTableJObject(PrefabsLootTableConfig config)
            {
                return new JObject
                {
                    ["IsEnabled"] = config.IsEnabled,
                    ["MinPrefabsAmount"] = config.MinPrefabsAmount,
                    ["MaxPrefabsAmount"] = config.MaxPrefabsAmount,
                    ["Prefabs"] = config.Prefabs is { Count: > 0 } ? new JArray { config.Prefabs.Select(x => new JObject { ["PrefabName"] = x.PrefabName, ["Chance"] = x.Chance, ["MinAmount"] = x.MinAmount, ["MaxAmount"] = x.MaxAmount }) } : null
                };
            }

            private static JObject GetItemsLootTableJObject(ItemsLootTableConfig config)
            {
                return new JObject
                {
                    ["IsEnabled"] = config.IsEnabled,
                    ["DisableMinMax"] = config.DisableMinMax,
                    ["MinItemsAmount"] = config.MinItemsAmount,
                    ["MaxItemsAmount"] = config.MaxItemsAmount,
                    ["Items"] = config.Items is { Count: > 0 } ? new JArray { config.Items.Select(x => new JObject { ["Shortname"] = x.Shortname, ["Skin"] = x.Skin, ["DisplayName"] = x.DisplayName, ["OwnerName"] = x.OwnerName, ["IsBlueprint"] = x.IsBlueprint, ["Chance"] = x.Chance, ["MinAmount"] = x.MinAmount, ["MaxAmount"] = x.MaxAmount }) } : null
                };
            }

            public class ActiveNpcState : NpcState
            {
                public ScientistNPC Npc { get; set; }
            }
            public class RespawnNpcState : NpcState
            {
                public int TimeToSpawn { get; set; }
            }
            public class NpcState
            {
                public PresetConfig Preset { get; set; }
            }
        }

        [ChatCommand("TeleportToSpawnPoint")]
        private void ChatCommandTeleportToSpawnPoint(BasePlayer player, string command, string[] args)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            if (args == null || args.Length == 0)
            {
                PrintToChat(player, "You <color=#ce3f27>didn't</color> write the name of the spawn point!");
                return;
            }

            string name = GetNameArgs(args, 0);

            ControllerSpawnPoint controller = Controllers.Where(x => x.Name == name).Min(x => Vector3.Distance(player.transform.position, x.transform.position));
            if (controller == null)
            {
                PrintToChat(player, $"Spawn Point with the name <color=#55aaff>{name}</color> was <color=#ce3f27>not</color> found!");
                return;
            }

            Teleport(player, controller.transform.position);
        }
        
                private Dictionary<string, CustomMonumentSpawnPoint> CustomSpawnPoints { get; set; } = new Dictionary<string, CustomMonumentSpawnPoint>();

        private bool IsInDayRange()
        {
            float currentTime = TOD_Sky.Instance.Cycle.Hour;
		   		 		  						  	   		   					  			 		   					  	 		
            float startDay = ParseHourOrDefaultNormalized(Cfg.StartDayTime, 8f);
            float startNight = ParseHourOrDefaultNormalized(Cfg.StartNightTime, 20f);

            return IsInDayRange(currentTime, startDay, startNight);
        }

        public class CargoSpawnPoint : SpawnPoint
        {
            [JsonProperty(En ? "Remove default NPCs? [true/false]" : "Удалять стандартных NPC? [true/false]", Order = 1)] public bool RemoveDefaultNpc { get; set; }
            [JsonProperty(En ? "Respawn NPCs when the cargo ship docks at the harbor? [true/false]" : "Респавнить NPC, когда корабль заплывает в порт? [true/false]", Order = 2)] public bool RespawnNpcHarbor { get; set; }
            [JsonProperty(En ? "Respawn NPCs when new crates spawn? [true/false]" : "Респавнить NPC, когда на корабле появляются новые ящики? [true/false]", Order = 3)] public bool RespawnNpcCrates { get; set; }
        }

        private IEnumerator SpawnMonumentSpawnPoints()
        {
            foreach (MonumentInfo monument in TerrainMeta.Path.Monuments)
            {
                string monumentName = GetNameMonument(monument);
                if (string.IsNullOrEmpty(monumentName)) continue;

                if (!MonumentSpawnPoints.TryGetValue(monumentName, out MonumentSpawnPoint spawnPoint)) continue;
                if (!spawnPoint.Enabled) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.Init(spawnPoint, monument.transform.position, monument.transform.rotation, monumentName);
                Controllers.Add(controller);
		   		 		  						  	   		   					  			 		   					  	 		
                if (!Cfg.EnabledMinLogs) Puts($"Monument {monumentName} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.2f);
            }
        }

        private void OnDeepSeaClose(DeepSeaManager deepSeaManager)
        {
            HashSet<ControllerSpawnPoint> controllers = new HashSet<ControllerSpawnPoint>();

            foreach (ControllerSpawnPoint point in Controllers)
                if (point.IsIsland || point.IsGhostShip)
                    controllers.Add(point);

            foreach (ControllerSpawnPoint point in controllers)
            {
                Controllers.Remove(point);
                UnityEngine.Object.Destroy(point.gameObject);
            }

            controllers.Clear();
            controllers = null;
        }

        private void OnEntityKill(SupplyDrop supplyDrop)
        {
            if (supplyDrop == null || supplyDrop.net == null || CargoPlanesSignaled == null) return;

            ControllerSpawnPoint controller = Controllers.FirstOrDefault(x => x.Name == supplyDrop.net.ID.Value.ToString());
            if (controller == null) return;

            Controllers.Remove(controller);
            UnityEngine.Object.Destroy(controller.gameObject);
        }

        private void OnSupplyDropDropped(SupplyDrop supplyDrop, CargoPlane cargoPlane)
        {
            if (supplyDrop == null || supplyDrop.net == null || cargoPlane == null) return;

            if (CargoPlanesSignaled.Contains(cargoPlane))
            {
                CargoPlanesSignaled.Remove(cargoPlane);
                return;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            if (!EventSpawnPoints.TryGetValue("AirDrop", out EventSpawnPoint spawnPoint)) return;

            if (Interface.CallHook("CanAirDropSpawnNpc", supplyDrop) is bool) return;

            Vector3 pos = supplyDrop.transform.position;
            pos.y = TerrainMeta.HeightMap.GetHeight(pos);

            MonumentInfo monument = TerrainMeta.Path.Monuments.FirstOrDefault(x => GetNameMonument(x) == "Giant Excavator Pit");
            if (monument != null)
            {
                Vector3 localPos = monument.transform.GetLocalPosition(pos);
                if (localPos.x is > -110f and < 110f && localPos.y is > -40f and < 40f && localPos.z is > -90f and < 90f) return;
            }

            ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
            controller.Init(spawnPoint, pos, Quaternion.identity, supplyDrop.net.ID.Value.ToString());
            Controllers.Add(controller);
        }

        private Dictionary<string, MonumentSpawnPoint> TunnelSpawnPoints { get; set; } = new Dictionary<string, MonumentSpawnPoint>();

        private IEnumerator LoadBiomeSpawnPoints()
        {
            string shortPath = "BetterNpc/Biome/";

            string path = Interface.Oxide.DataDirectory + "/" + shortPath;
            if (!Directory.Exists(path)) Directory.CreateDirectory(path);
		   		 		  						  	   		   					  			 		   					  	 		
            foreach (string filePath in Interface.Oxide.DataFileSystem.GetFiles(shortPath))
            {
                string fileName = filePath.GetFileName();
                TryReadRoadOrBiomeSpawnPoint(BiomeSpawnPoints, fileName, shortPath);
                yield return CoroutineEx.waitForSeconds(0.1f);
            }
        }

        private void AnalyzingPreset(PresetConfig preset)
        {
            if (preset.RespawnMaxTime < preset.RespawnMinTime) preset.RespawnMaxTime = preset.RespawnMinTime;

            preset.Economics ??= new Dictionary<string, double>();
            preset.CustomPositions ??= new List<string>();

            for (int i = preset.CustomPositions.Count - 1; i >= 0; i--)
            {
                string str = preset.CustomPositions[i];
                if (str.CorrectVector3()) continue;
                preset.CustomPositions.Remove(str);
            }

            int amountPositions = preset.CustomPositions.Count;

            if (preset.SpawnType != SpawnType.Random && preset.SpawnType != SpawnType.Custom)
                preset.SpawnType = amountPositions == 0 ? SpawnType.Random : SpawnType.Custom;

            if (amountPositions == 0 && preset.SpawnType == SpawnType.Custom) preset.SpawnType = SpawnType.Random;

            if (preset.SpawnType == SpawnType.Custom)
            {
                if (preset.MinDay > amountPositions) preset.MinDay = amountPositions;
                if (preset.MaxDay > amountPositions) preset.MaxDay = amountPositions;
                if (preset.MinNight > amountPositions) preset.MinNight = amountPositions;
                if (preset.MaxNight > amountPositions) preset.MaxNight = amountPositions;
            }

            if (preset.MaxDay < preset.MinDay) preset.MaxDay = preset.MinDay;
            if (preset.MaxNight < preset.MinNight) preset.MaxNight = preset.MinNight;

            if (preset.Config != null) preset.Config.UpdateValues();

            if (preset.Enabled)
            {
                if (preset is { MinDay: 0, MaxDay: 0, MinNight: 0, MaxNight: 0 }) preset.Enabled = false;
                if (string.IsNullOrWhiteSpace(preset.PresetName) && preset.Config == null) preset.Enabled = false;
            }
        }
        private Dictionary<ulong, HashSet<ulong>> HelicopterCrates { get; set; } = new Dictionary<ulong, HashSet<ulong>>();

        [ChatCommand("ShowAllZones")]
        private void ChatCommandShowAllZones(BasePlayer player)
        {
            if (!player.IsAdmin && !permission.UserHasPermission(player.UserIDString, "betternpc.admin")) return;

            float duration = 30f;

            foreach (ControllerSpawnPoint controller in Controllers)
            {
                Vector3 center = controller.transform.position;

                Vector3 pos1 = center + controller.transform.rotation * (new Vector3(center.x + controller.Size.x, center.y + controller.Size.y, center.z + controller.Size.z) - center);
                Vector3 pos2 = center + controller.transform.rotation * (new Vector3(center.x + controller.Size.x, center.y - controller.Size.y, center.z + controller.Size.z) - center);
                Vector3 pos3 = center + controller.transform.rotation * (new Vector3(center.x - controller.Size.x, center.y - controller.Size.y, center.z + controller.Size.z) - center);
                Vector3 pos4 = center + controller.transform.rotation * (new Vector3(center.x - controller.Size.x, center.y + controller.Size.y, center.z + controller.Size.z) - center);
                Vector3 pos5 = center + controller.transform.rotation * (new Vector3(center.x + controller.Size.x, center.y + controller.Size.y, center.z - controller.Size.z) - center);
                Vector3 pos6 = center + controller.transform.rotation * (new Vector3(center.x + controller.Size.x, center.y - controller.Size.y, center.z - controller.Size.z) - center);
                Vector3 pos7 = center + controller.transform.rotation * (new Vector3(center.x - controller.Size.x, center.y - controller.Size.y, center.z - controller.Size.z) - center);
                Vector3 pos8 = center + controller.transform.rotation * (new Vector3(center.x - controller.Size.x, center.y + controller.Size.y, center.z - controller.Size.z) - center);

                DebugDrawEx.Sphere(player, center, Color.red, 1f, duration, false, true);
                DebugDrawEx.Arrow(player, center + Vector3.down * controller.Size.y, center + Vector3.up * controller.Size.y, Color.red, 1f, duration, false, true);

                DebugDrawEx.Line(player, pos1, pos2, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos2, pos3, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos3, pos4, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos4, pos1, Color.green, duration, false, true);

                DebugDrawEx.Line(player, pos5, pos6, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos6, pos7, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos7, pos8, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos8, pos5, Color.green, duration, false, true);

                DebugDrawEx.Line(player, pos1, pos5, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos2, pos6, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos3, pos7, Color.green, duration, false, true);
                DebugDrawEx.Line(player, pos4, pos8, Color.green, duration, false, true);

                DebugDrawEx.Line(player, pos2, pos7, Color.yellow, duration, false, true);
                DebugDrawEx.Line(player, pos3, pos6, Color.yellow, duration, false, true);
                DebugDrawEx.Line(player, pos1, pos8, Color.yellow, duration, false, true);
                DebugDrawEx.Line(player, pos4, pos5, Color.yellow, duration, false, true);

                DebugDrawEx.Sphere(player, pos1, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos2, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos3, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos4, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos5, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos6, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos7, Color.cyan, 1f, duration, false, true);
                DebugDrawEx.Sphere(player, pos8, Color.cyan, 1f, duration, false, true);
            }
        }

        public class LootElementChanceConfig
        {
            [JsonProperty(En ? "Drop Chance (0–100)" : "Шанс выпадения (0–100)", Order = 1)] public float Chance { get; set; }
            [JsonProperty(En ? "Minimum Amount" : "Минимальное количество", Order = 2)] public int MinAmount { get; set; }
            [JsonProperty(En ? "Maximum Amount" : "Максимальное количество", Order = 3)] public int MaxAmount { get; set; }
        }

        private void ClearVariablesForEvents()
        {
            EventSpawnPoints.Clear();
            EventSpawnPoints = null;

            if (CargoPlanesSignaled != null)
            {
                CargoPlanesSignaled.Clear();
                CargoPlanesSignaled = null;
            }

            if (Ch47Crates != null)
            {
                Ch47Crates.Clear();
                Ch47Crates = null;
            }

            if (BradleyCrates != null)
            {
                BradleyCrates.Clear();
                BradleyCrates = null;
            }

            if (HelicopterCrates != null)
            {
                HelicopterCrates.Clear();
                HelicopterCrates = null;
            }

            CargoConfig = null;
        }
        
                private void CheckVersionPlugin()
        {
            webrequest.Enqueue("http://37.153.157.216:5000/Api/GetPluginVersions?pluginName=BetterNpc", null, (code, response) =>
            {
                if (code != 200 || string.IsNullOrEmpty(response)) return;
                string[] array = response.Replace("\"", string.Empty).Split('.');
                VersionNumber latestVersion = new VersionNumber(Convert.ToInt32(array[0]), Convert.ToInt32(array[1]), Convert.ToInt32(array[2]));
                if (Version < latestVersion) PrintWarning($"A new version ({latestVersion}) of the plugin is available! You need to update the plugin:\n- https://lone.design/product/betternpc\n- https://codefling.com/plugins/better-npc");
            }, this);
        }

        private void OnEntityKill(HackableLockedCrate crate)
        {
            if (crate == null || Ch47Crates == null) return;

            CrateCh47 crateCh47 = Ch47Crates.FirstOrDefault(x => x.Crate == crate);
            if (crateCh47 == null) return;
            Ch47Crates.Remove(crateCh47);

            ControllerSpawnPoint controller = Controllers.FirstOrDefault(x => x.Name == crateCh47.Name);
            if (controller == null) return;

            Controllers.Remove(controller);
            UnityEngine.Object.Destroy(controller.gameObject);
        }

        private bool TryReadRoadOrBiomeSpawnPoint(Dictionary<string, RoadOrBiomeSpawnPoint> dictionary, string fileName, string shortPath)
        {
            if (dictionary.TryGetValue(fileName, out RoadOrBiomeSpawnPoint oldSpawnPoint))
            {
                TryClearPresetUsage(oldSpawnPoint, fileName);
                dictionary.Remove(fileName);
            }

            RoadOrBiomeSpawnPoint spawnPoint = Interface.Oxide.DataFileSystem.ReadObject<RoadOrBiomeSpawnPoint>($"{shortPath}{fileName}");
            if (spawnPoint == null)
            {
                PrintError($"File {fileName} is corrupted and cannot be loaded!");
                return false;
            }

            foreach (PresetConfig preset in spawnPoint.Presets) preset.IsRoadOrBiomeFile = true;
            if (!Cfg.EnabledMinLogs) Puts($"File {fileName} has been loaded successfully!");
            AnalyzingSpawnPoint(spawnPoint, fileName);
            Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{fileName}", spawnPoint);
            dictionary.Add(fileName, spawnPoint);

            return true;
        }

        private void StartCheckDay()
        {
            StopCheckDay();

            float startDay = ParseHourOrDefaultNormalized(Cfg.StartDayTime, 8f);
            float startNight = ParseHourOrDefaultNormalized(Cfg.StartNightTime, 20f);

            CheckDayCoroutine = CheckDay(startDay, startNight).Start();
        }

        [ConsoleCommand("SpawnPointCreate")]
        private void ConsoleCommandSpawnPointCreate(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null) return;

            if (arg.Args.IsNullOrEmpty())
            {
                Puts("You didn't write the name of the spawn point!");
                return;
            }

            string name = arg.Args.GetNameArgs(0);

            CreateController(name);
            Puts($"SpawnPoint with the name {name} has been created!");
        }
        
                private Coroutine RespawnCounterCoroutine { get; set; }

        private static bool EqualsIgnoreWhitespace(string a, string b)
        {
            if (a == null || b == null) return false;
		   		 		  						  	   		   					  			 		   					  	 		
            string cleanA = RemoveWhitespace(a);
            string cleanB = RemoveWhitespace(b);

            return string.Equals(cleanA, cleanB, StringComparison.OrdinalIgnoreCase);
        }
        
                private static bool IsDeepSeaActive
        {
            get
            {
                DeepSeaManager serverInstance = PointEntity<DeepSeaManager>.ServerInstance;
                return serverInstance != null && serverInstance.IsOpen();
            }
        }
        
                private Coroutine CheckDayCoroutine { get; set; }
        
                private Dictionary<string, HashSet<string>> DefaultScientists { get; } = new Dictionary<string, HashSet<string>>
        {
            ["Abandoned Military Base A"] = new HashSet<string>
            {
                "scientistnpc_roamtethered"
            },
            ["Abandoned Military Base B"] = new HashSet<string>
            {
                "scientistnpc_roamtethered"
            },
            ["Abandoned Military Base C"] = new HashSet<string>
            {
                "scientistnpc_roamtethered"
            },
            ["Abandoned Military Base D"] = new HashSet<string>
            {
                "scientistnpc_roamtethered"
            },
            ["Giant Excavator Pit"] = new HashSet<string>
            {
                "scientistnpc_excavator"
            },
            ["Military Tunnel"] = new HashSet<string>
            {
                "scientistnpc_full_lr300",
                "scientistnpc_full_shotgun"
            },
            ["Oil Rig"] = new HashSet<string>
            {
                "scientistnpc_oilrig",
                "scientist2"
            },
            ["Large Oil Rig"] = new HashSet<string>
            {
                "scientistnpc_oilrig",
                "scientist2"
            },
            ["Missile Silo"] = new HashSet<string>
            {
                "scientistnpc_roam",
                "scientistnpc_roam_nvg_variant"
            },
            ["Arctic Research Base"] = new HashSet<string>
            {
                "scientistnpc_roam",
                "scientistnpc_roamtethered",
                "scientistnpc_patrol"
            },
            ["Launch Site"] = new HashSet<string>
            {
                "scientistnpc_patrol"
            },
            ["Airfield"] = new HashSet<string>
            {
                "scientistnpc_patrol"
            },
            ["Train Yard"] = new HashSet<string>
            {
                "scientistnpc_patrol"
            },
            ["Tunnel"] = new HashSet<string>
            {
                "npc_tunneldweller"
            },
            ["Underwater Lab"] = new HashSet<string>
            {
                "npc_underwaterdweller"
            },
            ["Custom"] = new HashSet<string>
            {
                "scientistnpc_roam",
                "scientistnpc_peacekeeper",
                "scientistnpc_heavy",
                "scientistnpc_junkpile_pistol",
                "npc_bandit_guard",
                "scarecrow"
            },
            ["Deep Sea Island"] = new HashSet<string>
            {
                "scientist2"
            },
            ["Ghost Ship"] = new HashSet<string>
            {
                "scientist2"
            }
        };
        private bool IsDay { get; set; }

        public class CustomMonumentSpawnPoint : MonumentSpawnPoint
        {
            [JsonProperty(En ? "Map ID" : "ID карты", Order = 3)] public float Id { get; set; }
            [JsonProperty(En ? "Map marker name (leave empty if using Position+Rotation)" : "Название маркера на карте (оставить пустым, если используется Позиция+Вращение)", Order = 4)] public string MapMarkerName { get; set; }
            [JsonProperty(En ? "Position (X, Y, Z) (leave empty if using Map marker name)" : "Позиция (X, Y, Z) (оставить пустым, если используется название маркера на карте)", Order = 5)] public string Position { get; set; }
            [JsonProperty(En ? "Rotation (X, Y, Z) (leave empty if using Map marker name)" : "Вращение (X, Y, Z) (оставить пустым, если используется название маркера на карте)", Order = 6)] public string Rotation { get; set; }
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private void OnServerInitialized()
        {
            if (IsOldData)
            {
                PrintError("You are using an outdated version of the configuration files. You need to first update your configuration files using the UpdaterBetterNpc plugin");
                NextTick(() => Interface.Oxide.UnloadPlugin(Name));
                return;
            }

            if (!plugins.Exists("NpcSpawn"))
            {
                PrintError("NpcSpawn plugin doesn`t exist! (https://drive.google.com/drive/folders/1-18L-mG7yiGxR-PQYvd11VvXC2RQ4ZCu?usp=sharing)");
                NextTick(() => Interface.Oxide.UnloadPlugin(Name));
                return;
            }

            permission.RegisterPermission("betternpc.admin", this);

            IsDay = IsInDayRange();

            InitializationCoroutine = PluginInitialization().Start();
            
            CheckVersionPlugin();
        }

        public class LootTableConfig
        {
            [JsonProperty(En ? "Use AlphaLoot Plugin (Prefab Loot) [true/false]" : "Использовать плагин AlphaLoot (лут префаба) [true/false]", Order = 0)] public bool IsAlphaLoot { get; set; }
            [JsonProperty(En ? "AlphaLoot Preset Name" : "Название пресета AlphaLoot", Order = 1)] public string AlphaLootPreset { get; set; }
            [JsonProperty(En ? "Use Loottable Plugin (Prefab Loot) [true/false]" : "Использовать плагин Loottable (лут префаба) [true/false]", Order = 2)] public bool IsLoottablePlugin { get; set; }
            [JsonProperty(En ? "Loottable Preset Name" : "Название пресета Loottable", Order = 3)] public string LoottablePreset { get; set; }
            [JsonProperty(En ? "Use CustomLoot Plugin (Prefab Loot) [true/false]" : "Использовать плагин CustomLoot (лут префаба) [true/false]", Order = 4)] public bool IsCustomLootPlugin { get; set; }
            [JsonProperty(En ? "CustomLoot Preset Name" : "Название пресета CustomLoot", Order = 5)] public string CustomLootPreset { get; set; }
            [JsonProperty(En ? "Clear Default NPC Loot Before Applying Custom Loot [true/false]" : "Очищать стандартный лут NPC перед применением кастомного [true/false]", Order = 6)] public bool ClearDefaultLoot { get; set; }
            [JsonProperty(En ? "Prefab-Based Loot Configuration" : "Конфигурация лута на основе префабов", Order = 7)] public PrefabsLootTableConfig PrefabsTable { get; set; }
            [JsonProperty(En ? "Custom Items Loot Configuration" : "Конфигурация кастомного лута (предметы)", Order = 8)] public ItemsLootTableConfig ItemsTable { get; set; }

            public static LootTableConfig CreateDefault()
            {
                return new LootTableConfig
                {
                    IsAlphaLoot = false,
                    AlphaLootPreset = string.Empty,
                    IsLoottablePlugin = false,
                    LoottablePreset = string.Empty,
                    IsCustomLootPlugin = false,
                    CustomLootPreset = string.Empty,
                    ClearDefaultLoot = false,
                    PrefabsTable = PrefabsLootTableConfig.CreateDefault(),
                    ItemsTable = ItemsLootTableConfig.CreateDefault()
                };
            }

            public static LootTableConfig FromJObject(JObject obj)
            {
                LootTableConfig cfg = CreateDefault();

                cfg.IsAlphaLoot = obj.Read("IsAlphaLoot", cfg.IsAlphaLoot);
                cfg.AlphaLootPreset = obj.Read("AlphaLootPreset", cfg.AlphaLootPreset);

                cfg.IsLoottablePlugin = obj.Read("IsLoottablePlugin", cfg.IsLoottablePlugin);
                cfg.LoottablePreset = obj.Read("LoottablePreset", cfg.LoottablePreset);

                cfg.IsCustomLootPlugin = obj.Read("IsCustomLootPlugin", cfg.IsCustomLootPlugin);
                cfg.CustomLootPreset = obj.Read("CustomLootPreset", cfg.CustomLootPreset);

                cfg.ClearDefaultLoot = obj.Read("ClearDefaultLoot", cfg.ClearDefaultLoot);

                if (obj.TryGetValue("PrefabsTable", out JToken prefabsTok) && prefabsTok is JObject prefabsObj)
                    cfg.PrefabsTable = PrefabsLootTableConfig.FromJObject(prefabsObj);

                if (obj.TryGetValue("ItemsTable", out JToken itemsTok) && itemsTok is JObject itemsObj)
                    cfg.ItemsTable = ItemsLootTableConfig.FromJObject(itemsObj);

                return cfg;
            }
        }

        private void OnDeepSeaOpened(DeepSeaManager deepSeaManager)
        {
            SpawnDeepSeaIslandSpawnPoints().Start();
            SpawnGhostShipSpawnPoints().Start();
        }

        private IEnumerator UpdateRespawnTime(int timeRepeat)
        {
            while (true)
            {
                foreach (ControllerSpawnPoint controller in Controllers)
                    controller.UpdateDeadNpcTime(timeRepeat);

                yield return CoroutineEx.waitForSeconds(timeRepeat);
            }
        }

        private class PluginConfig
        {
            [JsonProperty(En ? "Start time of day" : "Время начала дня")] public string StartDayTime { get; set; }
            [JsonProperty(En ? "Start time of night" : "Время начала ночи")] public string StartNightTime { get; set; }
            [JsonProperty(En ? "Use the plugin's PVE mode? (only if you use the PveMode plugin)" : "Использовать PVE-режим работы плагина? (только если используется плагин PveMode)")] public bool Pve { get; set; }
            [JsonProperty(En ? "Distance from the center of the safe zone to the nearest NPC spawn point [m]" : "Расстояние от центра безопасной зоны до ближайшего места спавна NPC [м]")] public float SafeZoneRange { get; set; }
            [JsonProperty(En ? "List of NPC types that should not be deleted (can use class name, NPC name, SkinID, or ShortPrefabName)" : "Список типов NPC, которые не должны удаляться (можно использовать имя класса, имя NPC, SkinID или ShortPrefabName)")] public HashSet<string> WhitelistNpc { get; set; }
            [JsonProperty(En ? "Run the debug.puzzlereset command when the plugin loads or reloads to refresh puzzles, IO, and NPCs at Facepunch monuments [true/false]" : "Обновлять головоломки, IO и NPC на монументах при загрузке или перезагрузке плагина? (debug.puzzlereset) [true/false]")] public bool PuzzleReset { get; set; }
            [JsonProperty(En ? "Refresh NPCs and crates at monuments when the plugin loads or reloads? [true/false]" : "Обновлять NPC и ящики на монументах при загрузке или перезагрузке плагина? [true/false]")] public bool SpawnGroupFill { get; set; }
            [JsonProperty(En ? "Enable simplified loading logs in the server console? (intended for advanced users who want fewer loading messages) [true/false]" : "Включить упрощённые сообщения о загрузке в консоли сервера? (для опытных пользователей, которые хотят меньше логов) [true/false]")] public bool EnabledMinLogs { get; set; }
            [JsonProperty(En ? "Configuration version" : "Версия конфигурации")] public VersionNumber PluginVersion { get; set; }

            public static PluginConfig DefaultConfig()
            {
                return new PluginConfig
                {
                    StartDayTime = "8:00",
                    StartNightTime = "20:00",
                    Pve = false,
                    SafeZoneRange = 150f,
                    WhitelistNpc = new HashSet<string>
                    {
                        "11162132011012",
                        "NpcRaider",
                        "RandomRaider",
                        "56485621526987",
                        "CustomScientistNpc"
                    },
                    PuzzleReset = true,
                    SpawnGroupFill = true,
                    EnabledMinLogs = false,
                    PluginVersion = new VersionNumber()
                };
            }
        }

        private void StartRespawnCounter()
        {
            StopRespawnCounter();
            RespawnCounterCoroutine = UpdateRespawnTime(5).Start();
        }

        private Dictionary<string, MonumentSpawnPoint> MonumentSpawnPoints { get; set; } = new Dictionary<string, MonumentSpawnPoint>();

        private static bool TryGetAmount(Dictionary<string, double> dic, out double amount, string alias)
        {
            amount = 0d;

            foreach (KeyValuePair<string, double> kvp in dic)
            {
                if (string.IsNullOrWhiteSpace(kvp.Key)) continue;
                if (kvp.Value < 0.001d) continue;
                string keyNorm = kvp.Key.Trim().ToLower();
                if (keyNorm != alias) continue;
                amount = kvp.Value;
                return true;
            }

            return false;
        }

        private Dictionary<string, MonumentSpawnPoint> UnderwaterLabSpawnPoints { get; set; } = new Dictionary<string, MonumentSpawnPoint>();

        private void OnEntityKill(LockedByEntCrate crate)
        {
            if (crate == null || crate.net == null) return;

            ulong crateId = crate.net.ID.Value;
            string name = string.Empty;

            if (crate.ShortPrefabName == "bradley_crate")
            {
                if (BradleyCrates == null) return;

                ulong bradleyId = 0;
                foreach (KeyValuePair<ulong, HashSet<ulong>> kvp in BradleyCrates)
                {
                    foreach (ulong id in kvp.Value)
                    {
                        if (id != crateId) continue;
                        bradleyId = kvp.Key;
                        break;
                    }
                    if (bradleyId != 0) break;
                }
                if (bradleyId == 0) return;

                HashSet<ulong> crates = BradleyCrates[bradleyId];

                crates.Remove(crateId);
                if (crates.Count > 0) return;

                crates = null;
                BradleyCrates.Remove(bradleyId);

                name = bradleyId.ToString();
            }
            else if (crate.ShortPrefabName == "heli_crate")
            {
                if (HelicopterCrates == null) return;

                ulong helicopterId = 0;
                foreach (KeyValuePair<ulong, HashSet<ulong>> kvp in HelicopterCrates)
                {
                    foreach (ulong id in kvp.Value)
                    {
                        if (id != crateId) continue;
                        helicopterId = kvp.Key;
                        break;
                    }
                    if (helicopterId != 0) break;
                }
                if (helicopterId == 0) return;

                HashSet<ulong> crates = HelicopterCrates[helicopterId];

                crates.Remove(crateId);
                if (crates.Count > 0) return;

                crates = null;
                HelicopterCrates.Remove(helicopterId);

                name = helicopterId.ToString();
            }

            if (string.IsNullOrEmpty(name)) return;

            ControllerSpawnPoint controller = Controllers.FirstOrDefault(x => x.Name == name);
            if (controller == null) return;

            Controllers.Remove(controller);
            UnityEngine.Object.Destroy(controller.gameObject);
        }
        private void OnEntitySpawned(ScientistNPC2 npc) => TryKillDefaultNpc(npc);
        private void TryKillDefaultNpc(BaseCombatEntity npc)
        {
            ControllerSpawnPoint controller = Controllers.FirstOrDefault(s => s.RemoveDefaultNpc && s.CanRemoveNpc(npc));
            if (controller != null)
            {
                NextTick(() =>
                {
                    if (npc.IsExists())
                        npc.Kill();
                });
                return;
            }
        }
        private void LoadIds()
        {
            foreach (BaseNetworkable baseNetworkable in BaseNetworkable.serverEntities)
            {
                if (baseNetworkable is not RANDSwitch randSwitch) continue;
                if (randSwitch.OwnerID != 0) continue;
                Vector3 pos = randSwitch.transform.position;
                Ids.Add(pos.x + pos.y + pos.z);
            }
        }
        
                private Dictionary<string, EventSpawnPoint> EventSpawnPoints { get; set; } = new Dictionary<string, EventSpawnPoint>();

        private static string GetNameMonument(DeepSeaIsland island)
        {
            if (island == null || island.name == null) return string.Empty;
            if (island.name.Contains("deepsea_island_tropical1")) return "Deep Sea Island Tropical 1";
            else if (island.name.Contains("deepsea_island_tropical2")) return "Deep Sea Island Tropical 2";
            else if (island.name.Contains("deepsea_island_tropical3")) return "Deep Sea Island Tropical 3";
            else if (island.name.Contains("deepsea_island_tropical4")) return "Deep Sea Island Tropical 4";
            else return string.Empty;
        }

        private void OnHelicopterDropCrate(CH47HelicopterAIController ai)
        {
            if (ai == null || ai.net == null) return;

            if (Interface.CallHook("CanCh47SpawnNpc", ai) is bool) return;

            Vector3 pos = ai.transform.position;
            pos.y = TerrainMeta.HeightMap.GetHeight(pos);

            if (!EventSpawnPoints.TryGetValue("CH47", out EventSpawnPoint spawnPoint)) return;

            string name = ai.net.ID.Value.ToString();

            ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
            controller.Init(spawnPoint, pos, Quaternion.identity, name);
            Controllers.Add(controller);

            Ch47Crates.Add(new CrateCh47 { Name = name, Position = pos, Crate = null });
        }

        public class ItemsLootTableConfig
        {
            [JsonProperty(En ? "Enable Custom Items Loot Table [true/false]" : "Включить кастомную таблицу предметов [true/false]", Order = 0)] public bool IsEnabled { get; set; }
            [JsonProperty(En ? "Disable Min/Max Item Amount Limits [true/false]" : "Отключить ограничение Min/Max количества предметов [true/false]", Order = 1)] public bool DisableMinMax { get; set; }
            [JsonProperty(En ? "Minimum Items Amount" : "Минимальное количество предметов", Order = 2)] public int MinItemsAmount { get; set; }
            [JsonProperty(En ? "Maximum Items Amount" : "Максимальное количество предметов", Order = 3)] public int MaxItemsAmount { get; set; }
            [JsonProperty(En ? "Loot Items List" : "Список предметов лута", Order = 4)] public List<LootItemConfig> Items { get; set; }

            public static ItemsLootTableConfig CreateDefault()
            {
                return new ItemsLootTableConfig
                {
                    IsEnabled = false,
                    DisableMinMax = true,
                    MinItemsAmount = 1,
                    MaxItemsAmount = 1,
                    Items = null
                };
            }

            public static ItemsLootTableConfig FromJObject(JObject obj)
            {
                ItemsLootTableConfig cfg = CreateDefault();

                cfg.IsEnabled = obj.Read("IsEnabled", cfg.IsEnabled);
                cfg.DisableMinMax = obj.Read("DisableMinMax", cfg.DisableMinMax);
                cfg.MinItemsAmount = obj.Read("MinItemsAmount", cfg.MinItemsAmount);
                cfg.MaxItemsAmount = obj.Read("MaxItemsAmount", cfg.MaxItemsAmount);

                if (obj.TryGetValue("Items", out JToken itemsTok) && itemsTok is JArray arr)
                {
                    List<LootItemConfig> list = new List<LootItemConfig>();
                    foreach (var t in arr)
                    {
                        if (t is JObject itemObj)
                            list.Add(LootItemConfig.FromJObject(itemObj));
                    }
                    cfg.Items = list.Count > 0 ? list : null;
                }

                return cfg;
            }
        }

        private void CreateControllerDelayed(string name, Vector3 position, float seconds) => timer.In(seconds, () => CreateController(name, position));

        private (Vector3 pos, Quaternion rot) GetLocationCustomSpawnPoint(CustomMonumentSpawnPoint spawnPoint)
        {
            Vector3 pos = Vector3.zero;
            Quaternion rot = Quaternion.identity;

            if (string.IsNullOrEmpty(spawnPoint.MapMarkerName))
            {
                pos = spawnPoint.Position.ToVector3();
                rot = Quaternion.Euler(spawnPoint.Rotation.ToVector3());
            }
            else
            {
                if (MonumentMarkers.TryGetValue(spawnPoint.MapMarkerName, out Transform transform))
                {
                    pos = transform.position;
                    rot = transform.rotation;
                }
                else if (!string.IsNullOrEmpty(spawnPoint.Position))
                {
                    pos = spawnPoint.Position.ToVector3();
                    rot = Quaternion.Euler(spawnPoint.Rotation.ToVector3());
                }
            }

            return (pos, rot);
        }

        public class NpcWear
        {
            [JsonProperty(En ? "Item Short Name" : "ShortName предмета", Order = 0)] public string ShortName { get; set; }
            [JsonProperty(En ? "Skin ID (0 = default)" : "SkinID (0 = стандартный)", Order = 1)] public ulong SkinID { get; set; }
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private void DestroyCargoControllers()
        {
            if (CargoControllers == null || CargoControllers.Count == 0) return;
            foreach (CargoControllerSpawnPoint point in CargoControllers.ToHashSet())
            {
                CargoControllers.Remove(point);
                UnityEngine.Object.DestroyImmediate(point);
            }
        }

        private void TryReadEventSpawnPoint(string fileName)
        {
            if (EventSpawnPoints.TryGetValue(fileName, out EventSpawnPoint oldSpawnPoint))
            {
                TryClearPresetUsage(oldSpawnPoint, fileName);
                EventSpawnPoints.Remove(fileName);
            }

            string shortPath = "BetterNpc/Event/";

            EventSpawnPoint spawnPoint = Interface.Oxide.DataFileSystem.ReadObject<EventSpawnPoint>($"{shortPath}{fileName}");
            if (spawnPoint == null)
            {
                PrintError($"File {fileName} is corrupted and cannot be loaded!");
                return;
            }

            foreach (PresetConfig preset in spawnPoint.Presets) preset.IsEventFile = true;
            if (!Cfg.EnabledMinLogs) Puts($"File {fileName} has been loaded successfully!");
            AnalyzingEventSpawnPoint(spawnPoint, fileName);
            Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{fileName}", spawnPoint);
            EventSpawnPoints.Add(fileName, spawnPoint);
        }

        private void CreateController(string name)
        {
            if (MonumentSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint1))
            {
                if (!spawnPoint1.Enabled) return;
                if (IsDeepSeaIsland(name))
                {
                    if (!IsDeepSeaActive) return;
                    foreach (DeepSeaIsland island in DeepSeaManager.ServerIslands)
                    {
                        string monumentName = GetNameMonument(island);
                        if (string.IsNullOrEmpty(monumentName) || monumentName != name) continue;
                        TryCreateMonument(spawnPoint1, island.transform, false, false, true);
                    }
                }
                else if (IsGhostShip(name))
                {
                    if (!IsDeepSeaActive) return;
                    foreach (GhostShip ship in DeepSeaManager.ServerGhostShips)
                    {
                        string monumentName = GetNameMonument(ship);
                        if (string.IsNullOrEmpty(monumentName) || monumentName != name) continue;
                        TryCreateMonument(spawnPoint1, ship.transform, false, false, true);
                    }
                }
                else
                {
                    foreach (MonumentInfo monument in TerrainMeta.Path.Monuments)
                    {
                        string monumentName = GetNameMonument(monument);
                        if (string.IsNullOrEmpty(monumentName) || monumentName != name) continue;
                        TryCreateMonument(spawnPoint1, monument.transform, false, false, false);
                    }
                }
            }
            else if (UnderwaterLabSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint2))
            {
                if (!spawnPoint2.Enabled) return;
                foreach (DungeonBaseInfo baseModule in TerrainMeta.Path.DungeonBaseEntrances)
                {
                    if (baseModule.name == name) TryCreateMonument(spawnPoint2, baseModule.transform, true, false, false);
                    foreach (GameObject module in baseModule.Links)
                    {
                        string moduleName = module.name.GetFileName();
                        if (moduleName != name) continue;
                        TryCreateMonument(spawnPoint2, module.transform, true, false, false);
                    }
                }
            }
            else if (TunnelSpawnPoints.TryGetValue(name, out MonumentSpawnPoint spawnPoint3))
            {
                if (!spawnPoint3.Enabled) return;
                foreach (DungeonGridCell gridCell in TerrainMeta.Path.DungeonGridCells)
                {
                    string cellName = gridCell.name.GetFileName();
                    if (cellName != name) continue;
                    TryCreateMonument(spawnPoint3, gridCell.transform, false, true, false);
                }
            }
            else if (CustomSpawnPoints.TryGetValue(name, out CustomMonumentSpawnPoint spawnPoint4))
            {
                if (!spawnPoint4.Enabled) return;

                (Vector3 pos, Quaternion rot) = GetLocationCustomSpawnPoint(spawnPoint4);
                if (pos == Vector3.zero) return;

                if (HasController(pos, name)) return;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsCustom = true;
                controller.Init(spawnPoint4, pos, rot, name);
                Controllers.Add(controller);
            }
            else if (BiomeSpawnPoints.TryGetValue(name, out RoadOrBiomeSpawnPoint spawnPoint5)) TryCreateRoadOrBiome(spawnPoint5);
            else if (RoadSpawnPoints.TryGetValue(name, out RoadOrBiomeSpawnPoint spawnPoint6)) TryCreateRoadOrBiome(spawnPoint6);
		   		 		  						  	   		   					  			 		   					  	 		
            return;

            void TryCreateMonument(MonumentSpawnPoint spawnPoint, Transform transform, bool isUnderWater, bool isTunnel, bool isIsland)
            {
                if (transform == null || HasController(transform.position, name)) return;
                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsUnderwaterLab = isUnderWater;
                controller.IsTunnel = isTunnel;
                controller.IsIsland = isIsland;
                controller.Init(spawnPoint, transform.position, transform.rotation, name);
                Controllers.Add(controller);
            }

            void TryCreateRoadOrBiome(RoadOrBiomeSpawnPoint spawnPoint)
            {
                if (!spawnPoint.Enabled) return;
                if (Controllers.Any(x => x.Name == name)) return;
                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.Init(spawnPoint, Vector3.zero, Quaternion.identity, name);
                Controllers.Add(controller);
            }
        }

        private IEnumerator SpawnCustomSpawnPoints()
        {
            foreach (KeyValuePair<string, CustomMonumentSpawnPoint> kvp in CustomSpawnPoints)
            {
                if (!kvp.Value.Enabled) continue;

                (Vector3 pos, Quaternion rot) = GetLocationCustomSpawnPoint(kvp.Value);
                if (pos == Vector3.zero) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsCustom = true;
                controller.Init(kvp.Value, pos, rot, kvp.Key);
                Controllers.Add(controller);

                if (!Cfg.EnabledMinLogs) Puts($"Custom location {kvp.Key} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.2f);
            }
        }

        private void StopCheckDay()
        {
            CheckDayCoroutine.Stop();
            CheckDayCoroutine = null;
        }

        private static string GetNameMonument(GhostShip ship)
        {
            if (ship == null || ship.name == null) return string.Empty;
            if (ship.name.Contains("ghostship_b")) return "Ghostship B";
            else if (ship.name.Contains("ghostship_c")) return "Ghostship C";
            else if (ship.name.Contains("ghostship_d")) return "Ghostship D";
            else if (ship.name.Contains("ghostship")) return "Ghostship A";
            else return string.Empty;
        }

        public class NpcConfig
        {
            [JsonProperty(En ? "Prefab Path (ScientistNPC only)" : "Путь к префабу NPC (только ScientistNPC)", Order = 0)] public string Prefab { get; set; }
            [JsonProperty(En ? "NPC Display Names (comma separated)" : "Имена NPC (через запятую)", Order = 1)] public string Names { get; set; }
            [JsonProperty(En ? "Enable Sleep Mode (Performance Optimization) [true/false]" : "Включить режим сна (оптимизация производительности) [true/false]", Order = 2)] public bool CanSleep { get; set; }
            [JsonProperty(En ? "Sleep Mode Activation Distance (distance from players)" : "Дистанция перехода в режим сна (расстояние до игроков)", Order = 3)] public float SleepDistance { get; set; }
            [JsonProperty(En ? "Destroy NPC Traps On Death [true/false]" : "Удалять ловушки NPC при смерти [true/false]", Order = 4)] public bool DestroyTrapsOnDeath { get; set; }
            [JsonProperty(En ? "Disable Scientist Radio Chatter [true/false]" : "Отключить радио-переговоры Scientist [true/false]", Order = 5)] public bool DisableRadio { get; set; }
            [JsonProperty(En ? "Barricade Heal Health Threshold (NPC places a barricade and heals if health is below this value)" : "Порог здоровья для лечения за баррикадой (NPC устанавливает баррикаду и начинает лечение, если здоровье ниже этого значения)", Order = 6)] public float BarricadeHealthThreshold { get; set; }
            [JsonProperty(En ? "Minimum Safe Distance To Place Barricade (NPC will not place a barricade if the player is closer than this distance)" : "Минимальная безопасная дистанция для установки баррикады (NPC не будет ставить баррикаду, если игрок находится ближе этого расстояния)", Order = 7)] public float BarricadeDistanceThreshold { get; set; }
            [JsonProperty(En ? "Healing Amount Scale (Multiplier for health restored by healing items)" : "Множитель лечения NPC (Множитель количества здоровья, восстанавливаемого при использовании лечебных предметов)", Order = 8)] public float HealingScale { get; set; }

            [JsonProperty(En ? "NPC Health" : "Здоровье NPC", Order = 9)] public float Health { get; set; }
            [JsonProperty(En ? "Global Damage Multiplier" : "Глобальный множитель урона", Order = 10)] public float DamageScale { get; set; }
            [JsonProperty(En ? "Melee Damage Scale (Overrides global NPC damage scale for melee attacks only)" : "Множитель урона ближнего боя (Переопределяет глобальный множитель урона NPC только для атак ближнего боя)", Order = 11)] public float MeleeDamageScale { get; set; }
            [JsonProperty(En ? "Aim Cone Multiplier (Default = 2)" : "Множитель разброса стрельбы (по умолчанию = 2)", Order = 12)] public float AimConeScale { get; set; }
            [JsonProperty(En ? "Attack Range Multiplier (Weapon Range Scale)" : "Множитель дальности атаки (множитель дальности оружия)", Order = 13)] public float AttackRangeMultiplier { get; set; }
            [JsonProperty(En ? "Instant Death On Headshot [true/false]" : "Мгновенная смерть от выстрела в голову [true/false]", Order = 14)] public bool InstantDeathIfHitHead { get; set; }
            [JsonProperty(En ? "Maximum Player Damage Distance (-1 = Unlimited)" : "Максимальная дистанция урона от игроков (-1 = без ограничений)", Order = 15)] public float DamageRange { get; set; }
            [JsonProperty(En ? "Short Range Distance (Spray Boost Trigger)" : "Дистанция активации усиления продолжительности спрея", Order = 16)] public float ShortRange { get; set; }
            [JsonProperty(En ? "Short Range Spray Length Multiplier" : "Множитель продолжительности спрея на ближней дистанции", Order = 17)] public float AttackLengthMaxShortRangeScale { get; set; }
            [JsonProperty(En ? "Head Damage Multiplier" : "Множитель урона по голове", Order = 18)] public float HeadDamageScale { get; set; }
            [JsonProperty(En ? "Body Damage Multiplier" : "Множитель урона по телу", Order = 19)] public float BodyDamageScale { get; set; }
            [JsonProperty(En ? "Leg Damage Multiplier" : "Множитель урона по ногам", Order = 20)] public float LegDamageScale { get; set; }
		   		 		  						  	   		   					  			 		   					  	 		
            [JsonProperty(En ? "Roam Range (Patrol Distance From Spawn)" : "Дистанция патрулирования от точки спавна", Order = 21)] public float RoamRange { get; set; }
            [JsonProperty(En ? "Chase Range (Max Distance From Spawn)" : "Максимальная дистанция погони от точки спавна", Order = 22)] public float ChaseRange { get; set; }
            [JsonProperty(En ? "Movement Speed (Default = 5)" : "Скорость передвижения (по умолчанию = 5)", Order = 23)] public float Speed { get; set; }
            [JsonProperty(En ? "Stationary NPC [true/false]" : "Стационарный NPC [true/false]", Order = 24)] public bool IsStationary { get; set; }
            [JsonProperty(En ? "Navigation Grid Type (0 = Normal, 1 = Special Zones)" : "Тип навигационной сетки (0 = Обычная, 1 = Спец. зоны/вне карты)", Order = 25)] public int NavigationGridType { get; set; }
            [JsonProperty(En ? "NavMesh Base Vertical Offset" : "Вертикальное смещение относительно NavMesh", Order = 26)] public float BaseOffSet { get; set; }
            [JsonProperty(En ? "Return From Deep Water [true/false]" : "Возвращаться к точке спавна при входе в воду [true/false]", Order = 27)] public bool CanRunAwayWater { get; set; }

            [JsonProperty(En ? "Player Detection Range (Sense Range)" : "Дистанция обнаружения игроков (Sense Range)", Order = 28)] public float SenseRange { get; set; }
            [JsonProperty(En ? "Player Hearing Range (Listen Range)" : "Дистанция слуха NPC (Listen Range)", Order = 29)] public float ListenRange { get; set; }
            [JsonProperty(En ? "Enable Vision Cone Detection [true/false]" : "Включить ограничение углом обзора NPC [true/false]", Order = 30)] public bool CheckVisionCone { get; set; }
            [JsonProperty(En ? "Vision Cone Angle (20–340 degrees)" : "Угол обзора NPC (20–340 градусов)", Order = 31)] public float VisionCone { get; set; }
            [JsonProperty(En ? "Target Memory Duration (seconds)" : "Время памяти цели (в секундах)", Order = 32)] public float MemoryDuration { get; set; }
            [JsonProperty(En ? "Attack Hostile Targets Only [true/false]" : "Атаковать только враждебные цели [true/false]", Order = 33)] public bool HostileTargetsOnly { get; set; }
            [JsonProperty(En ? "Display Sash Targets Only [true/false]" : "Атаковать только цели с повязкой (Sash) [true/false]", Order = 34)] public bool DisplaySashTargetsOnly { get; set; }
            [JsonProperty(En ? "Ignore Players Inside Safe Zones [true/false]" : "Игнорировать игроков в безопасной зоне [true/false]", Order = 35)] public bool IgnoreSafeZonePlayers { get; set; }
            [JsonProperty(En ? "Ignore Sleeping Players [true/false]" : "Игнорировать спящих игроков [true/false]", Order = 36)] public bool IgnoreSleepingPlayers { get; set; }
            [JsonProperty(En ? "Ignore Wounded Players [true/false]" : "Игнорировать раненых игроков [true/false]", Order = 37)] public bool IgnoreWoundedPlayers { get; set; }
            [JsonProperty(En ? "NPC Attack Mode (0 = None, 1 = All, 2 = Detailed)" : "Режим атаки по NPC (0 = Не атаковать, 1 = Всех, 2 = Детально)", Order = 38)] public int NpcAttackMode { get; set; }
            [JsonProperty(En ? "NPC Detection Range" : "Дистанция обнаружения других NPC", Order = 39)] public float NpcSenseRange { get; set; }
            [JsonProperty(En ? "NPC Damage Multiplier" : "Множитель урона по NPC", Order = 40)] public float NpcDamageScale { get; set; }
            [JsonProperty(En ? "NPC Whitelist (ShortPrefabName, SkinID, ClassName, NPC Name)" : "Белый список NPC (ShortPrefabName, SkinID, ClassName, имя NPC)", Order = 41)] public string NpcWhitelist { get; set; }
            [JsonProperty(En ? "NPC Blacklist (ShortPrefabName, SkinID, ClassName, NPC Name)" : "Чёрный список NPC (ShortPrefabName, SkinID, ClassName, имя NPC)", Order = 42)] public string NpcBlacklist { get; set; }
            [JsonProperty(En ? "Animal Attack Mode (0 = None, 1 = All, 2 = Detailed)" : "Режим атаки по животным (0 = Не атаковать, 1 = Всех, 2 = Детально)", Order = 43)] public int AnimalAttackMode { get; set; }
            [JsonProperty(En ? "Animal Detection Range" : "Дистанция обнаружения животных", Order = 44)] public float AnimalSenseRange { get; set; }
            [JsonProperty(En ? "Animal Damage Multiplier" : "Множитель урона по животным", Order = 45)] public float AnimalDamageScale { get; set; }
            [JsonProperty(En ? "Animal Whitelist (ShortPrefabName, SkinID, ClassName)" : "Белый список животных (ShortPrefabName, SkinID, ClassName)", Order = 46)] public string AnimalWhitelist { get; set; }
            [JsonProperty(En ? "Animal Blacklist (ShortPrefabName, SkinID, ClassName)" : "Чёрный список животных (ShortPrefabName, SkinID, ClassName)", Order = 47)] public string AnimalBlacklist { get; set; }

            [JsonProperty(En ? "Allow Turrets To Target This NPC [true/false]" : "Разрешить турелям атаковать NPC [true/false]", Order = 48)] public bool CanTurretTarget { get; set; }
            [JsonProperty(En ? "Damage Taken From Turrets Multiplier" : "Множитель получаемого урона от турелей", Order = 49)] public float DamageScaleFromTurret { get; set; }
            [JsonProperty(En ? "Damage Dealt To Turrets Multiplier" : "Множитель урона по турелям", Order = 50)] public float DamageScaleToTurret { get; set; }

            [JsonProperty(En ? "Loot Table Configuration" : "Конфигурация таблицы лута", Order = 51)] public LootTableConfig LootTable { get; set; }
            [JsonProperty(En ? "Remove Corpse On Death [true/false]" : "Удалять труп NPC при смерти [true/false]", Order = 52)] public bool IsRemoveCorpse { get; set; }
            [JsonProperty(En ? "Death Crate Prefab Path" : "Путь к префабу ящика при смерти NPC", Order = 53)] public string CratePrefab { get; set; }

            [JsonProperty(En ? "Enable Group Alert System [true/false]" : "Включить систему группового мышления [true/false]", Order = 54)] public bool GroupAlertEnabled { get; set; }
            [JsonProperty(En ? "Group Alert Radius" : "Радиус группового мышления", Order = 55)] public float GroupAlertRadius { get; set; }

            [JsonProperty(En ? "NPC Wear Items (Clothing & Armor)" : "Экипировка NPC (Одежда и броня)", Order = 56)] public HashSet<NpcWear> WearItems { get; set; }
            [JsonProperty(En ? "NPC Belt Items (Weapons & Items)" : "Предметы пояса NPC (Оружие и предметы)", Order = 57)] public HashSet<NpcBelt> BeltItems { get; set; }
            [JsonProperty(En ? "NPC Underwear Item (SkinID)" : "Нижнее бельё NPC (SkinID)", Order = 58)] public uint Underwear { get; set; }
            [JsonProperty(En ? "Kit name (Not recommended for performance reasons)" : "Название кита для выдачи NPC (не рекомендуется из-за влияния на производительность)", Order = 59)] public string Kit { get; set; }
            [JsonProperty(En ? "Gender (0 = Random, 1 = Female, 2 = Male)" : "Пол (0 = Случайно, 1 = Женский, 2 = Мужской)", Order = 60)] public int Gender { get; set; }
            [JsonProperty(En ? "Skin Tone (0 = Random, 1 = Very Light, 2 = Light, 3 = Dark, 4 = Very Dark)" : "Тон кожи (0 = Случайно, 1 = Очень светлый, 2 = Светлый, 3 = Тёмный, 4 = Очень тёмный)", Order = 61)] public int SkinTone { get; set; }

            public static NpcConfig CreateDefault()
            {
                return new NpcConfig
                {
                    Prefab = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_heavy.prefab",
                    Names = "Scientist",
                    Gender = 0,
                    SkinTone = 0,
                    Underwear = 0,
                    WearItems = new HashSet<NpcWear>
                    {
                        new NpcWear { ShortName = "hazmatsuit_scientist", SkinID = 0 }
                    },
                    BeltItems = new HashSet<NpcBelt>
                    {
                        new NpcBelt { ShortName = "rifle.lr300", Amount = 1, SkinID = 0, Mods = new HashSet<string> { "weapon.mod.lasersight", "weapon.mod.holosight" }, Ammo = string.Empty },
                        new NpcBelt { ShortName = "smg.mp5", Amount = 1, SkinID = 0, Mods = new HashSet<string> { "weapon.mod.flashlight" }, Ammo = string.Empty },
                        new NpcBelt { ShortName = "pistol.m92", Amount = 1, SkinID = 0, Mods = new HashSet<string>(), Ammo = string.Empty },
                        new NpcBelt { ShortName = "knife.combat", Amount = 1, SkinID = 0, Mods = new HashSet<string>(), Ammo = string.Empty },
                        new NpcBelt { ShortName = "syringe.medical", Amount = 5, SkinID = 0, Mods = new HashSet<string>(), Ammo = string.Empty },
                        new NpcBelt { ShortName = "grenade.f1", Amount = 5, SkinID = 0, Mods = new HashSet<string>(), Ammo = string.Empty }
                    },
                    Kit = string.Empty,
                    DestroyTrapsOnDeath = false,
                    Health = 100f,
                    InstantDeathIfHitHead = false,
                    RoamRange = 10f,
                    ChaseRange = 80f,
                    SenseRange = 40f,
                    ListenRange = 20f,
                    DamageRange = -1f,
                    ShortRange = 10f,
                    AttackLengthMaxShortRangeScale = 2f,
                    AttackRangeMultiplier = 1f,
                    CheckVisionCone = false,
                    VisionCone = 286f,
                    HostileTargetsOnly = false,
                    DisplaySashTargetsOnly = false,
                    IgnoreSafeZonePlayers = true,
                    IgnoreSleepingPlayers = true,
                    IgnoreWoundedPlayers = true,
                    NpcAttackMode = 2,
                    NpcSenseRange = 40f,
                    NpcDamageScale = 1f,
                    NpcWhitelist = "FrankensteinPet,14922524",
                    NpcBlacklist = "11162132011012",
                    AnimalAttackMode = 0,
                    AnimalSenseRange = 20f,
                    AnimalDamageScale = 1f,
                    AnimalWhitelist = string.Empty,
                    AnimalBlacklist = "11491311214163",
                    DamageScale = 0.75f,
                    MeleeDamageScale = 0.75f,
                    CanTurretTarget = true,
                    DamageScaleFromTurret = 0.5f,
                    DamageScaleToTurret = 1f,
                    AimConeScale = 2f,
                    DisableRadio = false,
                    CanRunAwayWater = true,
                    CanSleep = true,
                    SleepDistance = 100f,
                    Speed = 5f,
                    NavigationGridType = 0,
                    BaseOffSet = 0f,
                    MemoryDuration = 10f,
                    IsStationary = false,
                    LootTable = LootTableConfig.CreateDefault(),
                    CratePrefab = string.Empty,
                    IsRemoveCorpse = true,
                    GroupAlertEnabled = false,
                    GroupAlertRadius = 40f,
                    HeadDamageScale = 1f,
                    BodyDamageScale = 1f,
                    LegDamageScale = 1f,
                    BarricadeHealthThreshold = 30f,
                    BarricadeDistanceThreshold = 12f,
                    HealingScale = 1f
                };
            }

            public void UpdateValues()
            {
                if (string.IsNullOrEmpty(Prefab)) Prefab = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_heavy.prefab";
		   		 		  						  	   		   					  			 		   					  	 		
                if (string.IsNullOrEmpty(Names)) Names = "Scientist";

                if (Gender < 0) Gender = 0;
                if (Gender > 2) Gender = 2;

                if (SkinTone < 0) SkinTone = 0;
                if (SkinTone > 4) SkinTone = 4;

                if ((Underwear == 359039573 && Gender != 1) || (Underwear == 2059471831 && Gender != 2)) Underwear = 0;

                WearItems ??= new HashSet<NpcWear>();
                BeltItems ??= new HashSet<NpcBelt>();
		   		 		  						  	   		   					  			 		   					  	 		
                Kit ??= string.Empty;

                if (Health <= 0f) Health = 1f;

                if (Speed < 0f) Speed = 0f;
                if (Speed == 0f && !IsStationary) IsStationary = true;

                if (RoamRange <= 2f) RoamRange = 0f;
                if (RoamRange > ChaseRange) RoamRange = ChaseRange;

                if (ChaseRange < 0f) ChaseRange = 0f;

                if (RoamRange == 0f && ChaseRange == 0f && !IsStationary) IsStationary = true;

                if (SenseRange < 0f) SenseRange = 0f;
                if (ListenRange < 0f) ListenRange = 0f;
                if (DamageRange < -1f) DamageRange = -1f;
                if (ShortRange < 0f) ShortRange = 0f;
                if (AttackLengthMaxShortRangeScale < 1f) AttackLengthMaxShortRangeScale = 1f;
                if (AttackRangeMultiplier < 0f) AttackRangeMultiplier = 0f;

                if (VisionCone < 20f) VisionCone = 20f;
                if (VisionCone > 340f) VisionCone = 340f;

                if (DamageScale < 0f) DamageScale = 0f;
                if (MeleeDamageScale <= 0f) MeleeDamageScale = DamageScale;

                if (AimConeScale < 0f) AimConeScale = 0f;

                if (DamageScaleFromTurret < 0f) DamageScaleFromTurret = 0f;
                if (DamageScaleToTurret < 0f) DamageScaleToTurret = 0f;
                if (CanTurretTarget && DamageScaleFromTurret == 0f && DamageScaleToTurret == 0f) CanTurretTarget = false;

                if (SleepDistance < 0f) SleepDistance = 0f;
                if (CanSleep && SleepDistance == 0f) CanSleep = false;

                if (MemoryDuration < 1f) MemoryDuration = 1f;
		   		 		  						  	   		   					  			 		   					  	 		
                if (NavigationGridType < 0) NavigationGridType = 0;
                if (NavigationGridType > 1) NavigationGridType = 1;

                if (NpcAttackMode < 0) NpcAttackMode = 0;
                if (NpcAttackMode > 2) NpcAttackMode = 2;

                if (AnimalAttackMode < 0) AnimalAttackMode = 0;
                if (AnimalAttackMode > 2) AnimalAttackMode = 2;

                if (NpcSenseRange < 0f) NpcSenseRange = 0f;
                if (NpcSenseRange > SenseRange) NpcSenseRange = SenseRange;

                if (AnimalSenseRange < 0f) AnimalSenseRange = 0f;
                if (AnimalSenseRange > SenseRange) AnimalSenseRange = SenseRange;

                if (NpcDamageScale < 0f) NpcDamageScale = 0f;
                if (AnimalDamageScale < 0f) AnimalDamageScale = 0f;

                NpcWhitelist ??= string.Empty;
                AnimalWhitelist ??= string.Empty;

                NpcBlacklist ??= string.Empty;
                AnimalBlacklist ??= string.Empty;

                CratePrefab ??= string.Empty;

                if (GroupAlertRadius < 0f) GroupAlertRadius = 0f;
                if (GroupAlertEnabled && GroupAlertRadius == 0f) GroupAlertEnabled = false;

                if (HeadDamageScale <= 0f) HeadDamageScale = 1f;
                if (BodyDamageScale <= 0f) BodyDamageScale = 1f;
                if (LegDamageScale <= 0f) LegDamageScale = 1f;

                if (BarricadeHealthThreshold < 1f) BarricadeHealthThreshold = 30f;
                if (BarricadeDistanceThreshold < 3f) BarricadeDistanceThreshold = 12f;

                if (HealingScale <= 0f) HealingScale = 1f;

                LootTable ??= LootTableConfig.CreateDefault();

                LootTable.AlphaLootPreset ??= string.Empty;
                LootTable.CustomLootPreset ??= string.Empty;
                LootTable.LoottablePreset ??= string.Empty;

                LootTable.PrefabsTable ??= PrefabsLootTableConfig.CreateDefault();
                LootTable.ItemsTable ??= ItemsLootTableConfig.CreateDefault();

                if (LootTable.PrefabsTable.Prefabs is { Count: > 0 })
                    foreach (LootPrefabConfig lootPrefabConfig in LootTable.PrefabsTable.Prefabs)
                        lootPrefabConfig.PrefabName ??= string.Empty;

                if (LootTable.ItemsTable.Items is { Count: > 0 })
                {
                    foreach (LootItemConfig lootItemConfig in LootTable.ItemsTable.Items)
                    {
                        lootItemConfig.Shortname ??= string.Empty;
                        lootItemConfig.DisplayName ??= string.Empty;
                        lootItemConfig.OwnerName ??= string.Empty;
                    }
                }
            }

            public static NpcConfig FromJObject(JObject obj)
            {
                NpcConfig cfg = CreateDefault();

                cfg.Prefab = obj.Read("Prefab", cfg.Prefab);
                cfg.Names = obj.Read("Names", cfg.Names);
                cfg.Gender = obj.Read("Gender", cfg.Gender);
                cfg.SkinTone = obj.Read("SkinTone", cfg.SkinTone);
                cfg.Underwear = obj.Read("Underwear", cfg.Underwear);
                cfg.Kit = obj.Read("Kit", cfg.Kit);
                cfg.DestroyTrapsOnDeath = obj.Read("DestroyTrapsOnDeath", cfg.DestroyTrapsOnDeath);
                cfg.Health = obj.Read("Health", cfg.Health);
                cfg.InstantDeathIfHitHead = obj.Read("InstantDeathIfHitHead", cfg.InstantDeathIfHitHead);
                cfg.RoamRange = obj.Read("RoamRange", cfg.RoamRange);
                cfg.ChaseRange = obj.Read("ChaseRange", cfg.ChaseRange);
                cfg.SenseRange = obj.Read("SenseRange", cfg.SenseRange);
                cfg.ListenRange = obj.Read("ListenRange", cfg.ListenRange);
                cfg.DamageRange = obj.Read("DamageRange", cfg.DamageRange);
                cfg.ShortRange = obj.Read("ShortRange", cfg.ShortRange);
                cfg.AttackLengthMaxShortRangeScale = obj.Read("AttackLengthMaxShortRangeScale", cfg.AttackLengthMaxShortRangeScale);
                cfg.AttackRangeMultiplier = obj.Read("AttackRangeMultiplier", cfg.AttackRangeMultiplier);
                cfg.CheckVisionCone = obj.Read("CheckVisionCone", cfg.CheckVisionCone);
                cfg.VisionCone = obj.Read("VisionCone", cfg.VisionCone);
                cfg.HostileTargetsOnly = obj.Read("HostileTargetsOnly", cfg.HostileTargetsOnly);
                cfg.DisplaySashTargetsOnly = obj.Read("DisplaySashTargetsOnly", cfg.DisplaySashTargetsOnly);
                cfg.IgnoreSafeZonePlayers = obj.Read("IgnoreSafeZonePlayers", cfg.IgnoreSafeZonePlayers);
                cfg.IgnoreSleepingPlayers = obj.Read("IgnoreSleepingPlayers", cfg.IgnoreSleepingPlayers);
                cfg.IgnoreWoundedPlayers = obj.Read("IgnoreWoundedPlayers", cfg.IgnoreWoundedPlayers);
                cfg.NpcAttackMode = obj.Read("NpcAttackMode", cfg.NpcAttackMode);
                cfg.NpcSenseRange = obj.Read("NpcSenseRange", cfg.NpcSenseRange);
                cfg.NpcDamageScale = obj.Read("NpcDamageScale", cfg.NpcDamageScale);
                cfg.NpcWhitelist = obj.Read("NpcWhitelist", cfg.NpcWhitelist);
                cfg.NpcBlacklist = obj.Read("NpcBlacklist", cfg.NpcBlacklist);
                cfg.AnimalAttackMode = obj.Read("AnimalAttackMode", cfg.AnimalAttackMode);
                cfg.AnimalSenseRange = obj.Read("AnimalSenseRange", cfg.AnimalSenseRange);
                cfg.AnimalDamageScale = obj.Read("AnimalDamageScale", cfg.AnimalDamageScale);
                cfg.AnimalWhitelist = obj.Read("AnimalWhitelist", cfg.AnimalWhitelist);
                cfg.AnimalBlacklist = obj.Read("AnimalBlacklist", cfg.AnimalBlacklist);
                cfg.DamageScale = obj.Read("DamageScale", cfg.DamageScale);
                cfg.MeleeDamageScale = obj.Read("MeleeDamageScale", cfg.MeleeDamageScale);
                bool hasMeleeDamageScale = obj.TryGetValue("MeleeDamageScale", out JToken tmp) && tmp != null && tmp.Type != JTokenType.Null && tmp.Type != JTokenType.Undefined;
                bool hasDamageScale = obj.TryGetValue("DamageScale", out tmp) && tmp != null && tmp.Type != JTokenType.Null && tmp.Type != JTokenType.Undefined;
                if (!hasMeleeDamageScale && hasDamageScale) cfg.MeleeDamageScale = cfg.DamageScale;
                cfg.CanTurretTarget = obj.Read("CanTurretTarget", cfg.CanTurretTarget);
                cfg.DamageScaleFromTurret = obj.Read("DamageScaleFromTurret", cfg.DamageScaleFromTurret);
                cfg.DamageScaleToTurret = obj.Read("DamageScaleToTurret", cfg.DamageScaleToTurret);
                cfg.AimConeScale = obj.Read("AimConeScale", cfg.AimConeScale);
                cfg.DisableRadio = obj.Read("DisableRadio", cfg.DisableRadio);
                cfg.CanRunAwayWater = obj.Read("CanRunAwayWater", cfg.CanRunAwayWater);
                cfg.CanSleep = obj.Read("CanSleep", cfg.CanSleep);
                cfg.SleepDistance = obj.Read("SleepDistance", cfg.SleepDistance);
                cfg.Speed = obj.Read("Speed", cfg.Speed);
                cfg.NavigationGridType = obj.Read("AreaMask", 1) == 1 ? 0 : 1;
                cfg.BaseOffSet = obj.Read("BaseOffSet", cfg.BaseOffSet);
                cfg.MemoryDuration = obj.Read("MemoryDuration", cfg.MemoryDuration);
                cfg.CratePrefab = obj.Read("CratePrefab", cfg.CratePrefab);
                cfg.IsRemoveCorpse = obj.Read("IsRemoveCorpse", cfg.IsRemoveCorpse);
                cfg.GroupAlertEnabled = obj.Read("GroupAlertEnabled", cfg.GroupAlertEnabled);
                cfg.GroupAlertRadius = obj.Read("GroupAlertRadius", cfg.GroupAlertRadius);
                cfg.HeadDamageScale = obj.Read("HeadDamageScale", cfg.HeadDamageScale);
                cfg.BodyDamageScale = obj.Read("BodyDamageScale", cfg.BodyDamageScale);
                cfg.LegDamageScale = obj.Read("LegDamageScale", cfg.LegDamageScale);
                cfg.BarricadeHealthThreshold = obj.Read("NpcBarricadeHealthThreshold", cfg.BarricadeHealthThreshold);
                cfg.BarricadeDistanceThreshold = obj.Read("BarricadeDistanceThreshold", cfg.BarricadeDistanceThreshold);
                cfg.HealingScale = obj.Read("HealingScale", cfg.HealingScale);

                if (obj.TryGetValue("WearItems", out JToken wearToken) && wearToken != null && wearToken.Type != JTokenType.Null && wearToken.Type != JTokenType.Undefined)
                {
                    JArray wearArray = UnwrapArray(wearToken);
                    HashSet<NpcWear> wearSet = new HashSet<NpcWear>();

                    foreach (JToken itemToken in wearArray)
                    {
                        JObject itemObj = itemToken as JObject;
                        if (itemObj == null) continue;

                        NpcWear wear = new NpcWear
                        {
                            ShortName = itemObj.Read("ShortName", string.Empty),
                            SkinID = itemObj.Read("SkinID", 0UL)
                        };

                        if (!string.IsNullOrEmpty(wear.ShortName)) wearSet.Add(wear);
                    }

                    if (wearSet.Count > 0) cfg.WearItems = wearSet;
                }

                if (obj.TryGetValue("BeltItems", out JToken beltToken) && beltToken != null && beltToken.Type != JTokenType.Null && beltToken.Type != JTokenType.Undefined)
                {
                    JArray beltArray = UnwrapArray(beltToken);
                    HashSet<NpcBelt> beltSet = new HashSet<NpcBelt>();

                    foreach (JToken itemToken in beltArray)
                    {
                        JObject itemObj = itemToken as JObject;
                        if (itemObj == null) continue;

                        NpcBelt belt = new NpcBelt
                        {
                            ShortName = itemObj.Read("ShortName", string.Empty),
                            Amount = itemObj.Read("Amount", 1),
                            SkinID = itemObj.Read("SkinID", 0UL),
                            Ammo = itemObj.Read("Ammo", string.Empty),
                            Mods = ReadStringHashSet(itemObj, "Mods")
                        };
		   		 		  						  	   		   					  			 		   					  	 		
                        if (!string.IsNullOrEmpty(belt.ShortName)) beltSet.Add(belt);
                    }

                    cfg.BeltItems = beltSet;
                }

                if (obj.TryGetValue("States", out JToken statesToken) && statesToken != null && statesToken.Type != JTokenType.Null && statesToken.Type != JTokenType.Undefined)
                {
                    JArray statesArray = UnwrapArray(statesToken);
                    HashSet<string> statesSet = new HashSet<string>();

                    foreach (JToken t in statesArray)
                    {
                        string stateName = t.ToString();
                        if (!string.IsNullOrEmpty(stateName)) statesSet.Add(stateName);
                    }

                    if (statesSet.Contains("IdleState") || statesSet.Contains("CombatStationaryState")) cfg.IsStationary = true;
                    else cfg.IsStationary = false;
                }

                if (obj.TryGetValue("LootTable", out JToken lootToken) && lootToken != null && lootToken.Type != JTokenType.Null && lootToken.Type != JTokenType.Undefined && lootToken is JObject lootObj)
                    cfg.LootTable = LootTableConfig.FromJObject(lootObj);

                cfg.UpdateValues();

                return cfg;
            }

            private static JArray UnwrapArray(JToken token)
            {
                if (token is JArray array)
                {
                    if (array.Count == 1 && array[0] is JArray) return (JArray)array[0];
                    return array;
                }
                if (token is JObject obj) return new JArray { obj };
                return new JArray();
            }

            private static HashSet<string> ReadStringHashSet(JObject obj, string name)
            {
                if (!obj.TryGetValue(name, out JToken token) || token == null || token.Type == JTokenType.Null || token.Type == JTokenType.Undefined) return new HashSet<string>();

                HashSet<string> result = new HashSet<string>();

                if (token is JArray array)
                {
                    if (array.Count == 1 && array[0] is JArray) array = (JArray)array[0];

                    for (int i = 0; i < array.Count; i++)
                    {
                        string s = array[i].ToString();
                        if (!string.IsNullOrEmpty(s)) result.Add(s);
                    }
                    return result;
                }

                if (token.Type == JTokenType.String)
                {
                    string single = (string)token;
                    if (!string.IsNullOrEmpty(single)) result.Add(single);
                }

                return result;
            }
        }

        private static string GetNameArgs(string[] args, int first)
        {
            string result = "";
            for (int i = first; i < args.Length; i++) result += i == first ? args[i] : $" {args[i]}";
            return result;
        }

        private IEnumerator SpawnDeepSeaSpawnPoints()
        {
            if (!IsDeepSeaActive)
            {
                if (!Cfg.EnabledMinLogs) Puts("Deep Sea is not available right now!");
                yield break;
            }
            yield return SpawnDeepSeaIslandSpawnPoints();
            yield return SpawnGhostShipSpawnPoints();
        }

        public void Teleport(BasePlayer player, Vector3 newPosition)
        {
            if (!player.IsValid() || Vector3.Distance(newPosition, Vector3.zero) < 5f) return;

            newPosition.y += 0.1f;

            player.PauseFlyHackDetection(5f);
            player.PauseSpeedHackDetection(5f);
            player.ApplyStallProtection(4f);
            player.UpdateActiveItem(default);
            player.EnsureDismounted();
            player.Server_CancelGesture();

            if (player.HasParent()) player.SetParent(null, true, true);

            if (player.IsConnected)
            {
                player.StartSleeping();
                if (player.IsAdmin) player.RunOfflineMetabolism(state: false);
                player.SetPlayerFlag(BasePlayer.PlayerFlags.ReceivingSnapshot, true);
                player.ClientRPC(RpcTarget.Player("StartLoading_Quick", player), arg1: true);
            }

            player.Teleport(newPosition);

            if (player.IsConnected)
            {
                if (!player.limitNetworking && !player.isInvisible)
                {
                    player.UpdateNetworkGroup();
                    player.SendNetworkUpdateImmediate();
                }

                player.ClearEntityQueue(null);
                player.SendSubscribedGroupsSnapshot();
                if (player.IsOnGround() || player.limitNetworking || player.isInvisible || player.IsFlying || player.IsAdmin) player.Invoke(() =>
                {
                    if (player && player.IsConnected)
                    {
                        if (player.limitNetworking || player.isInvisible) player.EndSleeping();
                        else player.EndSleeping();
                    }
                }, 0.5f);
            }

            if (!player.limitNetworking && !player.isInvisible) player.ForceUpdateTriggers();
        }

        private void OnNpcSpawnPresetRename(string currentName, string newName)
        {
            HashSet<string> reloadSpawnPoints = new HashSet<string>();

            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in MonumentSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Monument/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in UnderwaterLabSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Monument/Underwater Lab/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in TunnelSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Monument/Tunnel/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, CustomMonumentSpawnPoint> kvp in CustomSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Custom/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, EventSpawnPoint> kvp in EventSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Event/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            if (CargoConfig != null)
            {
                if (TryRenameSpawnPoint(CargoConfig, "CargoShip"))
                    Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Event/CargoShip", CargoConfig);
            }

            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in RoadSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Road/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in BiomeSpawnPoints)
            {
                if (!TryRenameSpawnPoint(kvp.Value, kvp.Key)) continue;
                string shortPath = "BetterNpc/Biome/";
                Interface.Oxide.DataFileSystem.WriteObject($"{shortPath}{kvp.Key}", kvp.Value);
            }

            foreach (string spawnPoint in reloadSpawnPoints) ReloadController(spawnPoint);
            reloadSpawnPoints.Clear();
            reloadSpawnPoints = null;

            return;

            bool TryRenameSpawnPoint(SpawnPoint spawnPoint, string fileName)
            {
                bool hasRename = false;

                foreach (PresetConfig preset in spawnPoint.Presets)
                    if (TryRenamePreset(preset, fileName))
                        if (!hasRename) hasRename = true;

                if (hasRename) reloadSpawnPoints.Add(fileName);

                return hasRename;
            }

            bool TryRenamePreset(PresetConfig preset, string fileName)
            {
                if (preset.PresetName != currentName) return false;
                preset.PresetName = newName;
                Puts($"Preset {currentName} was renamed to {newName} in file {fileName}");
                return true;
            }
        }
        private HashSet<CargoControllerSpawnPoint> CargoControllers { get; set; } = new HashSet<CargoControllerSpawnPoint>();
        
                [ConsoleCommand("ConvertNpcSpawn")]
        private void ConsoleCommandConvertNpcSpawn(ConsoleSystem.Arg arg)
        {
            if (arg == null || arg.Player() != null) return;

            if (NpcSpawn == null || !plugins.Exists("NpcSpawn"))
            {
                PrintError("NpcSpawn plugin is not installed on your server");
                return;
            }

            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in MonumentSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in UnderwaterLabSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/Underwater Lab/{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, MonumentSpawnPoint> kvp in TunnelSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Monument/Tunnel/{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, CustomMonumentSpawnPoint> kvp in CustomSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Custom/{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, EventSpawnPoint> kvp in EventSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Event/{kvp.Key}", kvp.Key == "Bradley" ? kvp.Value as BradleySpawnPoint : kvp.Value);
            }

            if (CargoConfig != null)
            {
                foreach (PresetConfig preset in CargoConfig.Presets) UpdatePreset(preset, "CargoShip");
                Interface.Oxide.DataFileSystem.WriteObject("BetterNpc/Event/CargoShip", CargoConfig);
            }

            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in RoadSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Road/{kvp.Key}", kvp.Value);
            }

            foreach (KeyValuePair<string, RoadOrBiomeSpawnPoint> kvp in BiomeSpawnPoints)
            {
                foreach (PresetConfig preset in kvp.Value.Presets) UpdatePreset(preset, kvp.Key);
                Interface.Oxide.DataFileSystem.WriteObject($"BetterNpc/Biome/{kvp.Key}", kvp.Value);
            }

            Interface.Oxide.ReloadPlugin(Name);

            return;

            void UpdatePreset(PresetConfig preset, string fileName)
            {
                if (string.IsNullOrEmpty(preset.PresetName)) return;
                JObject jObject = (JObject)NpcSpawn.Call("GetJObject", preset.PresetName);
                if (jObject == null)
                {
                    PrintError($"There is no preset named {preset.PresetName} in the .../data/NpcSpawn/Preset/ folder on your server");
                    return;
                }
                preset.Config = NpcConfig.FromJObject(jObject);
                Puts($"You have successfully converted the preset parameters {preset.PresetName} to file {fileName}");
                preset.PresetName = string.Empty;
            }
        }

        private ControllerSpawnPoint GetNearController(Vector3 pos, string name)
        {
            ControllerSpawnPoint closest = null;
            float closestDistSqr = float.MaxValue;

            foreach (ControllerSpawnPoint point in Controllers)
            {
                if (point.IsEvent || point.IsRoadOrBiome) continue;
                if (!EqualsIgnoreWhitespace(point.Name, name)) continue;

                float distSqr = (pos - point.transform.position).sqrMagnitude;
                if (distSqr < closestDistSqr)
                {
                    closest = point;
                    closestDistSqr = distSqr;
                }
            }

            return closest;
        }

        private IEnumerator SpawnUnderwaterLabSpawnPoint(string moduleName, Transform transform, MonumentSpawnPoint spawnPoint)
        {
            if (!spawnPoint.Enabled) yield break;

            ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
            controller.IsUnderwaterLab = true;
            controller.Init(spawnPoint, transform.position, transform.rotation, moduleName);
            Controllers.Add(controller);

            if (!Cfg.EnabledMinLogs) Puts($"Underwater Module {moduleName} has been successfully loaded!");

            yield return CoroutineEx.waitForSeconds(0.2f);
        }

        [ConsoleCommand("ShowAllMonumentMarkers")]
        private void ConsoleCommandShowAllMonumentMarkers(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null) return;
            string text = "Monument Markers:";
            foreach (KeyValuePair<string, Transform> kvp in MonumentMarkers) text += $"\n- {kvp.Key}";
            Puts(text);
        }

        private void DisableAirDrop()
        {
            Unsubscribe("OnCargoPlaneSignaled");
            Unsubscribe("OnSupplyDropDropped");
            Unsubscribe("OnSupplyDropDropped");
            CargoPlanesSignaled.Clear();
            CargoPlanesSignaled = null;
        }

        public class NpcBelt
        {
            [JsonProperty(En ? "Item Short Name" : "ShortName предмета", Order = 0)] public string ShortName { get; set; }
            [JsonProperty(En ? "Amount" : "Количество", Order = 1)] public int Amount { get; set; }
            [JsonProperty(En ? "Skin ID (0 = default)" : "SkinID (0 = стандартный)", Order = 2)] public ulong SkinID { get; set; }
            [JsonProperty(En ? "Weapon Mods (ShortNames)" : "Модули оружия (ShortNames)", Order = 3)] public HashSet<string> Mods { get; set; }
            [JsonProperty(En ? "Ammo Type (ShortName)" : "Тип патронов (ShortName)", Order = 4)] public string Ammo { get; set; }
        }

        private IEnumerator CheckDay(float startDay, float startNight)
        {
            while (true)
            {
                float currentTime = TOD_Sky.Instance == null || TOD_Sky.Instance.Cycle == null ? 12f : TOD_Sky.Instance.Cycle.Hour;
                bool dayNow = IsInDayRange(currentTime, startDay, startNight);

                if (dayNow != IsDay)
                {
                    IsDay = dayNow;

                    if (Controllers != null)
                        foreach (ControllerSpawnPoint controller in Controllers)
                            controller?.UpdatePopulation();

                    if (CargoControllers != null)
                        foreach (CargoControllerSpawnPoint controller in CargoControllers)
                            controller?.UpdatePopulation();
                }

                yield return CoroutineEx.waitForSeconds(30f);
            }
        }

        private void CreateControllerDelayed(string name, float seconds) => timer.In(seconds, () => CreateController(name));

                private HashSet<CargoPlane> CargoPlanesSignaled { get; set; } = new HashSet<CargoPlane>();
		   		 		  						  	   		   					  			 		   					  	 		
        private IEnumerator SpawnGhostShipSpawnPoints()
        {
            foreach (GhostShip ship in DeepSeaManager.ServerGhostShips)
            {
                string monumentName = GetNameMonument(ship);
                if (string.IsNullOrEmpty(monumentName)) continue;

                if (!MonumentSpawnPoints.TryGetValue(monumentName, out MonumentSpawnPoint spawnPoint)) continue;
                if (!spawnPoint.Enabled) continue;

                ControllerSpawnPoint controller = new GameObject().AddComponent<ControllerSpawnPoint>();
                controller.IsGhostShip = true;
                controller.Init(spawnPoint, ship.transform.position, ship.transform.rotation, monumentName);
                Controllers.Add(controller);

                if (!Cfg.EnabledMinLogs) Puts($"Monument {monumentName} has been successfully loaded!");

                yield return CoroutineEx.waitForSeconds(0.2f);
            }
        }
        
                private void DestroyController(string name)
        {
            HashSet<ControllerSpawnPoint> controllers = new HashSet<ControllerSpawnPoint>();

            foreach (ControllerSpawnPoint point in Controllers)
            {
                if (point.IsEvent) continue;
                if (!EqualsIgnoreWhitespace(point.Name, name)) continue;
                controllers.Add(point);
            }

            foreach (ControllerSpawnPoint point in controllers)
            {
                Controllers.Remove(point);
                UnityEngine.Object.Destroy(point.gameObject);
            }

            controllers.Clear();
            controllers = null;
        }

        public class EventSpawnPoint : SpawnPoint
        {
            [JsonProperty(En ? "Radius" : "Радиус", Order = 1)] public float Radius { get; set; }
        }

        [ConsoleCommand("SpawnPointDestroy")]
        private void ConsoleCommandSpawnPointDestroy(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null) return;

            if (arg.Args.IsNullOrEmpty())
            {
                Puts("You didn't write the name of the spawn point!");
                return;
            }

            string name = arg.Args.GetNameArgs(0);

            DestroyController(name);
            Puts($"SpawnPoint with the name {name} has been destroyed!");
        }
        
                private void AnalyzingCustomMonumentSpawnPoint(CustomMonumentSpawnPoint spawnPoint, string name)
        {
            if (string.IsNullOrWhiteSpace(spawnPoint.MapMarkerName)) spawnPoint.MapMarkerName = string.Empty;

            if (!spawnPoint.Position.CorrectVector3()) spawnPoint.Position = string.Empty;
            if (!spawnPoint.Rotation.CorrectVector3()) spawnPoint.Rotation = string.Empty;
		   		 		  						  	   		   					  			 		   					  	 		
            if (spawnPoint.Position != string.Empty && spawnPoint.Rotation == string.Empty) spawnPoint.Rotation = "(0.0, 0.0, 0.0)";

            if (spawnPoint.MapMarkerName == string.Empty && spawnPoint.Position == string.Empty) spawnPoint.Enabled = false;

            AnalyzingMonumentSpawnPoint(spawnPoint, name);
        }

        private void ClearVariablesForMonuments()
        {
            MonumentSpawnPoints.Clear();
            MonumentSpawnPoints = null;

            UnderwaterLabSpawnPoints.Clear();
            UnderwaterLabSpawnPoints = null;

            TunnelSpawnPoints.Clear();
            TunnelSpawnPoints = null;
        }

        private static BetterNpc _;

        private void OnCargoShipHarborArrived(CargoShip cargo)
        {
            if (cargo == null || !CargoConfig.RespawnNpcHarbor) return;
		   		 		  						  	   		   					  			 		   					  	 		
            CargoControllerSpawnPoint controller = CargoControllers.FirstOrDefault(x => x.Cargo == cargo);
            if (controller == null) return;

            controller.RespawnPresets();
        }

        private void StopRespawnCounter()
        {
            RespawnCounterCoroutine.Stop();
            RespawnCounterCoroutine = null;
        }

        private void Unload()
        {
            if (InitializationCoroutine != null)
            {
                InitializationCoroutine.Stop();
                InitializationCoroutine = null;
            }

            StopRespawnCounter();
            StopCheckDay();

            foreach (ControllerSpawnPoint controller in Controllers)
            {
                if (controller == null) continue;
                UnityEngine.Object.Destroy(controller.gameObject);
            }
            Controllers.Clear();
            Controllers = null;

            if (CargoControllers != null)
            {
                foreach (CargoControllerSpawnPoint controller in CargoControllers)
                {
                    if (controller == null) continue;
                    UnityEngine.Object.DestroyImmediate(controller);
                }
                CargoControllers.Clear();
                CargoControllers = null;
            }

            if (CargoConfig is { RemoveDefaultNpc: true }) ConVar.AI.npc_spawn_on_cargo_ship = true;

            ClearVariablesForMonuments();

            ClearVariablesForCustomMonuments();

            ClearVariablesForEvents();

            RoadSpawnPoints.Clear();
            RoadSpawnPoints = null;

            BiomeSpawnPoints.Clear();
            BiomeSpawnPoints = null;

            _ = null;
        }

        private IEnumerator SpawnUnderwaterLabSpawnPoints()
        {
            foreach (DungeonBaseInfo baseModule in TerrainMeta.Path.DungeonBaseEntrances)
            {
                if (UnderwaterLabSpawnPoints.TryGetValue(baseModule.name, out MonumentSpawnPoint spawnPoint1))
                    yield return SpawnUnderwaterLabSpawnPoint(baseModule.name, baseModule.transform, spawnPoint1);
		   		 		  						  	   		   					  			 		   					  	 		
                foreach (GameObject module in baseModule.Links)
                {
                    string moduleName = module.name.GetFileName();
                    if (UnderwaterLabSpawnPoints.TryGetValue(moduleName, out MonumentSpawnPoint spawnPoint2))
                        yield return SpawnUnderwaterLabSpawnPoint(moduleName, module.transform, spawnPoint2);
                }
            }
        }

        [JsonConverter(typeof(StringEnumConverter))]
        public enum SpawnType
        {
            Random,
            Custom
        }

        private PluginConfig Cfg { get; set; }

        private bool HasController(Vector3 pos, string name)
        {
            ControllerSpawnPoint controller = GetNearController(pos, name);
            if (controller == null) return false;
            return Vector3.Distance(controller.transform.position, pos) < 1f;
        }

        public class LootPrefabConfig : LootElementChanceConfig
        {
            [JsonProperty(En ? "Prefab Name (loot source)" : "Название префаба (источник лута)", Order = 0)] public string PrefabName { get; set; }

            public static LootPrefabConfig CreateDefault()
            {
                return new LootPrefabConfig
                {
                    PrefabName = "assets/rust.ai/agents/npcplayer/humannpc/scientist/scientistnpc_oilrig.prefab",
                    Chance = 100f,
                    MinAmount = 1,
                    MaxAmount = 1
                };
            }

            public static LootPrefabConfig FromJObject(JObject obj)
            {
                LootPrefabConfig cfg = CreateDefault();
		   		 		  						  	   		   					  			 		   					  	 		
                cfg.PrefabName = obj.Read("PrefabName", cfg.PrefabName);

                cfg.Chance = obj.Read("Chance", cfg.Chance);
                cfg.MinAmount = obj.Read("MinAmount", cfg.MinAmount);
                cfg.MaxAmount = obj.Read("MaxAmount", cfg.MaxAmount);
		   		 		  						  	   		   					  			 		   					  	 		
                return cfg;
            }
        }

        public class PresetConfig
        {

            public bool ShouldSerializeRespawnMaxTime()
            {
                if (IsEventFile) return false;
                if (IsCargoFile) return false;
                return true;
            }
            [JsonProperty(En ? "Maximum numbers - Day" : "Максимальное кол-во днем", Order = 2)] public int MaxDay { get; set; }
            [JsonProperty(En ? "Maximum numbers - Night" : "Максимальное кол-во ночью", Order = 4)] public int MaxNight { get; set; }
            [JsonProperty(En ? "NPC Configuration (used if preset is empty)" : "Конфигурация NPC (если пресет не указан)", Order = 8)] public NpcConfig Config { get; set; }

            public bool ShouldSerializeSpawnAtAllCustomPositions()
            {
                if (IsRoadOrBiomeFile) return false;
                if (IsEventFile) return false;
                if (IsCargoFile) return false;
                return true;
            }
            [JsonProperty(En ? "Spawn Type [Random/Custom]" : "Тип спавна [Random/Custom]", Order = 10)] public SpawnType SpawnType { get; set; }
            [JsonProperty(En ? "Enabled? [true/false]" : "Включен? [true/false]", Order = 0)] public bool Enabled { get; set; }
            [JsonIgnore] public bool IsCargoFile { get; set; }
            [JsonProperty(En ? "Minimum respawn time after death [sec.]" : "Минимальное время респавна после смерти [sec.]", Order = 5)] public int RespawnMinTime { get; set; }

            public bool ShouldSerializeCustomPositions()
            {
                if (IsRoadOrBiomeFile) return false;
                if (IsEventFile) return false;
                return true;
            }
            [JsonProperty(En ? "Minimum numbers - Night" : "Минимальное кол-во ночью", Order = 3)] public int MinNight { get; set; }

            [JsonIgnore] public bool IsEventFile { get; set; }

            public bool ShouldSerializeRespawnMinTime()
            {
                if (IsEventFile) return false;
                if (IsCargoFile) return false;
                return true;
            }
            [JsonProperty(En ? "The name of the NPC preset from the NpcSpawn plugin" : "Название пресета NPC из плагина NpcSpawn", Order = 7)] public string PresetName { get; set; }

            public void CalculatePositions(Transform transform)
            {
                if (SpawnType != SpawnType.Custom) return;
                PositionsForSpawn = new List<Vector3>();
                foreach (string str in CustomPositions)
                {
                    Vector3 local = str.ToVector3();
                    Vector3 global = transform.GetGlobalPosition(local);
                    PositionsForSpawn.Add(global);
                }
            }
            [JsonProperty(En ? "List of spawn positions (X, Y, Z) (used if Spawn Type - Custom)" : "Список точек спавна (X, Y, Z) (используется если тип спавна - Custom)", Order = 11)] public List<string> CustomPositions { get; set; }
            [JsonProperty(En ? "Minimum numbers - Day" : "Минимальное кол-во днем", Order = 1)] public int MinDay { get; set; }
            [JsonProperty(En ? "Spawn at all custom positions? (only works if Spawn Type = Custom) [true/false]" : "Спавнить во всех кастомных позициях? (работает только если тип спавна = Custom) [true/false]", Order = 12)] public bool SpawnAtAllCustomPositions { get; set; }
            [JsonProperty(En ? "Maximum respawn time after death [sec.]" : "Максимальное время респавна после смерти [sec.]", Order = 6)] public int RespawnMaxTime { get; set; }

            public PresetConfig Clone()
            {
                return new PresetConfig
                {
                    Enabled = Enabled,
                    MinDay = MinDay,
                    MaxDay = MaxDay,
                    MinNight = MinNight,
                    MaxNight = MaxNight,
                    RespawnMinTime = RespawnMinTime,
                    RespawnMaxTime = RespawnMaxTime,
                    PresetName = PresetName,
                    Config = Config,
                    Economics = Economics,
                    SpawnType = SpawnType,
                    CustomPositions = CustomPositions,
                    SpawnAtAllCustomPositions = SpawnAtAllCustomPositions,
                    IsEventFile = IsEventFile,
                    IsCargoFile = IsCargoFile,
                    IsRoadOrBiomeFile = IsRoadOrBiomeFile,
                    PositionsForSpawn = null
                };
            }

            public bool ShouldSerializeSpawnType()
            {
                if (IsRoadOrBiomeFile) return false;
                if (IsEventFile) return false;
                if (IsCargoFile) return false;
                return true;
            }

            [JsonIgnore] public List<Vector3> PositionsForSpawn { get; set; }
            [JsonProperty(En ? "Rewards for killing the NPC (key - plugin name, value - points to give)" : "Награды за убийство NPC (ключ - название плагина, значение - сколько очков выдать)", Order = 9)] public Dictionary<string, double> Economics { get; set; }
            [JsonIgnore] public bool IsRoadOrBiomeFile { get; set; }
        }
            }
}

namespace Oxide.Plugins.BetterNpcExtensionMethods
{
    public static class LinqManager
    {
        public static bool Any<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) return true;
            return false;
        }

        public static HashSet<TSource> Where<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            HashSet<TSource> result = new HashSet<TSource>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) result.Add(enumerator.Current);
            return result;
        }

        public static TSource FirstOrDefault<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) return enumerator.Current;
            return default(TSource);
        }
		   		 		  						  	   		   					  			 		   					  	 		
        public static HashSet<TSource> ToHashSet<TSource>(this IEnumerable<TSource> source)
        {
            HashSet<TSource> result = new HashSet<TSource>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(enumerator.Current);
            return result;
        }

        public static HashSet<TResult> Select<TSource, TResult>(this IEnumerable<TSource> source, Func<TSource, TResult> predicate)
        {
            HashSet<TResult> result = new HashSet<TResult>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(predicate(enumerator.Current));
            return result;
        }

        public static string GetFileName(this string path) => path.Split('/')[^1].Split('.')[0];

        public static TSource Min<TSource>(this IEnumerable<TSource> source, Func<TSource, float> predicate)
        {
            TSource result = default(TSource);
            float resultValue = float.MaxValue;
            using (var enumerator = source.GetEnumerator())
            {
                while (enumerator.MoveNext())
                {
                    TSource element = enumerator.Current;
                    float elementValue = predicate(element);
                    if (elementValue < resultValue)
                    {
                        result = element;
                        resultValue = elementValue;
                    }
                }
            }
            return result;
        }

        public static bool AreEqual(this float a, float b, float epsilon = 0.001f) => Math.Abs(a - b) < epsilon;

        public static int SafeToInt(this double value)
        {
            double rounded = Math.Round(value, 0, MidpointRounding.AwayFromZero);
            if (rounded > int.MaxValue) return int.MaxValue;
            if (rounded < int.MinValue) return int.MinValue;
            return (int)rounded;
        }

        public static bool CorrectVector3(this string str)
        {
            if (string.IsNullOrWhiteSpace(str)) return false;
            if (str.ToVector3() == default) return false;
            return true;
        }
    }

    public static class FindPositionManager
    {
        public static bool IsRaycast(this Vector3 position, float height, int layers, out RaycastHit raycastHit) => Physics.Raycast(position, Vector3.down, out raycastHit, height, layers);

        public static bool IsNavMesh(this Vector3 position, float radius, int areaMask, int agentTypeId, out NavMeshHit navMeshHit) => NavMesh.SamplePosition(position, out navMeshHit, radius, new NavMeshQueryFilter { areaMask = areaMask, agentTypeID = agentTypeId });

        public static bool IsAvailableTopology(this Vector3 position, int findTopology, bool isBlocked)
        {
            int topology = TerrainMeta.TopologyMap.GetTopology(position);
            if (isBlocked) return (topology & findTopology) == 0;
            else return (topology & findTopology) != 0;
        }
    }

    public static class EntityManager
    {
        public static bool IsExists(this BaseNetworkable entity) => entity != null && !entity.IsDestroyed;
        public static bool IsPlayer(this BasePlayer player) => player != null && player.userID.IsSteamId();
        public static Vector3 AddToY(this Vector3 vector3, float offset) => vector3.WithY(vector3.y + offset);
        public static Vector3 GetGlobalPosition(this Transform tr, Vector3 local) => tr.TransformPoint(local);
        public static Vector3 GetLocalPosition(this Transform tr, Vector3 global) => tr.InverseTransformPoint(global);
    }

    public static class CoroutineManager
    {
        public static Coroutine Start(this IEnumerator action) => ServerMgr.Instance.StartCoroutine(action);

        public static void Stop(this Coroutine coroutine)
        {
            if (coroutine == null) return;
            ServerMgr.Instance.StopCoroutine(coroutine);
        }
    }

    public static class DebugDrawEx
    {
        public static void Sphere(BasePlayer player, Vector3 pos, Color color, float radius = 1f, float duration = 10f, bool distanceFade = false, bool zTest = false)
        {
            player.SendConsoleCommand("ddraw.sphere", duration, color, pos, radius, distanceFade, zTest);
        }

        public static void Line(BasePlayer player, Vector3 start, Vector3 end, Color color, float duration = 10f, bool distanceFade = false, bool zTest = false)
        {
            player.SendConsoleCommand("ddraw.line", duration, color, start, end, distanceFade, zTest);
        }

        public static void Text(BasePlayer player, Vector3 pos, string text, Color color, int textSize = 12, float duration = 10f, bool distanceFade = false, bool zTest = false)
        {
            player.SendConsoleCommand("ddraw.text", duration, color, pos, $"<size={textSize}>{text}</size>", distanceFade, zTest);
        }

        public static void Arrow(BasePlayer player, Vector3 start, Vector3 end, Color color, float radius = 0f, float duration = 10f, bool distanceFade = false, bool zTest = false)
        {
            player.SendConsoleCommand("ddraw.arrow", duration, color, start, end, radius, distanceFade, zTest);
        }
    }

    public static class JObjectManager
    {
        public static T Read<T>(this JObject obj, string name, T fallback)
        {
            if (obj.TryGetValue(name, out JToken token) && token != null && token.Type != JTokenType.Null && token.Type != JTokenType.Undefined) return token.ToObject<T>();
            return fallback;
        }

        public static string Read(this JObject obj, string name, string fallback) => obj.Read<string>(name, fallback);
    }

    public static class StringViewEx
    {
        public static string GetNameArgs(this StringView[] args, int first)
        {
            if (first >= args.Length) return string.Empty;
		   		 		  						  	   		   					  			 		   					  	 		
            StringBuilder builder = new StringBuilder();
		   		 		  						  	   		   					  			 		   					  	 		
            for (int i = first; i < args.Length; i++)
            {
                if (i > first) builder.Append(' ');
                builder.Append(args[i].ToString());
            }

            return builder.ToString();
        }
    }
}
