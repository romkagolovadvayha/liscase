using Network;
using System;
using System.Collections;
using Oxide.Core;
using Oxide.Plugins.BargesExtensionMethods;
using Rust;
using Facepunch;
using UnityEngine;
using static StabilityEntity;
using Oxide.Core.Plugins;
using System.Reflection;
using System.Collections.Generic;
using Newtonsoft.Json;

namespace Oxide.Plugins.BargesExtensionMethods
{
    public static class ExtensionMethods
    {
        public static bool Any<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) return true;
            return false;
        }

        public static HashSet<TSource> Where<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            HashSet<TSource> result = new HashSet<TSource>();

            using (var enumerator = source.GetEnumerator())
                while (enumerator.MoveNext())
                    if (predicate(enumerator.Current))
                        result.Add(enumerator.Current);

            return result;
        }

        public static TSource FirstOrDefault<TSource>(this IEnumerable<TSource> source, Func<TSource, bool> predicate)
        {
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) return enumerator.Current;
            return default(TSource);
        }

        public static HashSet<TResult> Select<TSource, TResult>(this IEnumerable<TSource> source, Func<TSource, TResult> predicate)
        {
            HashSet<TResult> result = new HashSet<TResult>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(predicate(enumerator.Current));
            return result;
        }

        public static List<TResult> Select<TSource, TResult>(this IList<TSource> source, Func<TSource, TResult> predicate)
        {
            List<TResult> result = new List<TResult>();
            for (int i = 0; i < source.Count; i++)
            {
                TSource element = source[i];
                result.Add(predicate(element));
            }
            return result;
        }

        public static bool IsExists(this BaseNetworkable entity) => entity != null && !entity.IsDestroyed;
		   		 		  						  	   		   					  			 		   					  	 		
        public static bool IsRealPlayer(this BasePlayer player) => player != null && player.userID.IsSteamId();

        public static List<TSource> OrderBy<TSource>(this IEnumerable<TSource> source, Func<TSource, float> predicate)
        {
            List<TSource> result = source.ToList();
            for (int i = 0; i < result.Count; i++)
            {
                for (int j = 0; j < result.Count - 1; j++)
                {
                    if (predicate(result[j]) > predicate(result[j + 1]))
                    {
                        TSource z = result[j];
                        result[j] = result[j + 1];
                        result[j + 1] = z;
                    }
                }
            }
            return result;
        }

        public static List<TSource> ToList<TSource>(this IEnumerable<TSource> source)
        {
            List<TSource> result = new List<TSource>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(enumerator.Current);
            return result;
        }

        public static HashSet<TSource> ToHashSet<TSource>(this IEnumerable<TSource> source)
        {
            HashSet<TSource> result = new HashSet<TSource>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) result.Add(enumerator.Current);
            return result;
        }
		   		 		  						  	   		   					  			 		   					  	 		
        public static HashSet<T> OfType<T>(this IEnumerable<BaseNetworkable> source)
        {
            HashSet<T> result = new HashSet<T>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (enumerator.Current is T) result.Add((T)(object)enumerator.Current);
            return result;
        }

        public static TSource Max<TSource>(this IEnumerable<TSource> source, Func<TSource, float> predicate)
        {
            TSource result = source.ElementAt(0);
            float resultValue = predicate(result);
            using (var enumerator = source.GetEnumerator())
            {
                while (enumerator.MoveNext())
                {
                    TSource element = enumerator.Current;
                    float elementValue = predicate(element);
                    if (elementValue > resultValue)
                    {
                        result = element;
                        resultValue = elementValue;
                    }
                }
            }
            return result;
        }

        public static TSource Min<TSource>(this IEnumerable<TSource> source, Func<TSource, float> predicate)
        {
            TSource result = source.ElementAt(0);
            float resultValue = predicate(result);
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

        private static TSource ElementAt<TSource>(this IEnumerable<TSource> source, int index)
        {
            int movements = 0;
            using (var enumerator = source.GetEnumerator())
            {
                while (enumerator.MoveNext())
                {
                    if (movements == index) return enumerator.Current;
                    movements++;
                }
            }
            return default(TSource);
        }

        public static TSource First<TSource>(this IList<TSource> source) => source[0];

        public static TSource Last<TSource>(this IList<TSource> source) => source[source.Count - 1];

        public static bool IsEqualVector3(this Vector3 a, Vector3 b) => Vector3.Distance(a, b) < 0.1f;

        public static List<TSource> OrderByQuickSort<TSource>(this List<TSource> source, Func<TSource, float> predicate)
        {
            return source.QuickSort(predicate, 0, source.Count - 1);
        }

        private static List<TSource> QuickSort<TSource>(this List<TSource> source, Func<TSource, float> predicate, int minIndex, int maxIndex)
        {
            if (minIndex >= maxIndex) return source;

            int pivotIndex = minIndex - 1;
            for (int i = minIndex; i < maxIndex; i++)
            {
                if (predicate(source[i]) < predicate(source[maxIndex]))
                {
                    pivotIndex++;
                    source.Replace(pivotIndex, i);
                }
            }
            pivotIndex++;
            source.Replace(pivotIndex, maxIndex);

            QuickSort(source, predicate, minIndex, pivotIndex - 1);
            QuickSort(source, predicate, pivotIndex + 1, maxIndex);

            return source;
        }

        private static void Replace<TSource>(this IList<TSource> source, int x, int y)
        {
            TSource t = source[x];
            source[x] = source[y];
            source[y] = t;
        }

        public static object GetPrivateFieldValue(this object obj, string fieldName)
        {
            FieldInfo fi = GetPrivateFieldInfo(obj.GetType(), fieldName);
            if (fi != null) return fi.GetValue(obj);
            else return null;
        }

        public static void SetPrivateFieldValue(this object obj, string fieldName, object value)
        {
            FieldInfo info = GetPrivateFieldInfo(obj.GetType(), fieldName);
            if (info != null) info.SetValue(obj, value);
        }

        public static FieldInfo GetPrivateFieldInfo(Type type, string fieldName)
        {
            foreach (FieldInfo fi in type.GetFields(BindingFlags.NonPublic | BindingFlags.Instance)) if (fi.Name == fieldName) return fi;
            return null;
        }

        public static Action GetPrivateAction(this object obj, string methodName)
        {
            MethodInfo mi = obj.GetType().GetMethod(methodName, BindingFlags.NonPublic | BindingFlags.Instance);
            if (mi != null) return (Action)Delegate.CreateDelegate(typeof(Action), obj, mi);
            else return null;
        }

        public static object CallPrivateMethod(this object obj, string methodName, params object[] args)
        {
            MethodInfo mi = obj.GetType().GetMethod(methodName, BindingFlags.NonPublic | BindingFlags.Instance);
            if (mi != null) return mi.Invoke(obj, args);
            else return null;
        }
    }
}
namespace Oxide.Plugins
{
    [Info("Barges", "Adem", "1.2.5")]
    internal class Barges : RustPlugin
    {

        private object CanEntityTakeDamage(SkyLantern skyLantern, HitInfo info)
        {
            if (skyLantern == null || info == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(skyLantern);
            if (barge == null)
                return null;

            return false;
        }

        private void OnExplosiveThrown(BasePlayer player, RoadFlare roadFlare, ThrownWeapon thrownWeapon)
        {
            OnPlayerDropFlare(player, roadFlare, thrownWeapon);
        }
        private readonly HashSet<FishingVillageZoneController> _fishingVillageZoneControllers = new HashSet<FishingVillageZoneController>();

        private void OnServerSave()
        {
            Barge.SaveBarges();
        }

        private class EngineConfig
        {
            [JsonProperty(En ? "Engine Power Multiplier" : "Множитель мощности двигателя")] 
            public float PowerScale { get; set; }
            
            [JsonProperty(En ? "Fuel consumption multiplier" : "Множитель потребления топлива")] 
            public float FuelScale { get; set; }
            
            [JsonProperty(En ? "Turning speed Multiplier" : "Множитель скорости поворота")] 
            public float RotateScale { get; set; }
        }

        private class AnchorModule : BaseModule
        {
            private IOEntity _sirenLight;

            public static AnchorModule SpawnAnchorModule(BaseEntity parentEntity, Barge barge, CustomPrefabData customPrefabData)
            {
                AnchorModule anchorModule = parentEntity.gameObject.AddComponent<AnchorModule>();
                anchorModule.Init(parentEntity, barge);
                anchorModule.LoadModule();

                return anchorModule;
            }

            private void LoadModule()
            {
                _sirenLight = parentEntity.children.FirstOrDefault(x => x.IsExists() && x is IOEntity && x.ShortPrefabName == "sirenlightorange") as IOEntity;
                PressButton.pressDuration = 2f;
            }

            public override void OnButtonPressed(BasePlayer player)
            {
                if (Barge.IsStopped())
                {
                    Effect.server.Run("assets/bundled/prefabs/fx/oiljack/pump_up.prefab", parentEntity.transform.position + Vector3.up, broadcast: true);
                    parentEntity.Invoke(() => Barge.StartMoving(), 1);
                }
                else
                {
                    Effect.server.Run("assets/bundled/prefabs/fx/oiljack/pump_down.prefab", parentEntity.transform.position + Vector3.up, broadcast: true);
                    SwitchSirenLight(true);

                    parentEntity.Invoke(() =>
                    {
                        SwitchSirenLight(false);
                        Barge.shouldAnchor = true;
                        Effect.server.Run("assets/content/vehicles/submarine/effects/submarine collision effect abovewater.prefab", parentEntity.transform.position - Vector3.up * 2, broadcast: true);
                    }, 1);
                }
            }

            private void SwitchSirenLight(bool isEnable)
            {
                if (_sirenLight != null)
                    _sirenLight.UpdateFromInput(isEnable ? 1 : 0, 0);
            }
        }

        private class MovableBaseMountable : BaseMountable
        {
            public static MovableBaseMountable CreateMovableBaseMountable(string chairPrefab, BaseEntity parentEntity, Vector3 localPosition, Vector3 localRotation)
            {
                BaseMountable baseMountable = GameManager.server.CreateEntity(chairPrefab, parentEntity.transform.position) as BaseMountable;
                baseMountable.enableSaving = false;
                MovableBaseMountable movableBaseMountable = baseMountable.gameObject.AddComponent<MovableBaseMountable>();
                BuildManager.CopySerializableFields(baseMountable, movableBaseMountable);
                baseMountable.StopAllCoroutines();
                UnityEngine.GameObject.DestroyImmediate(baseMountable, true);
                BuildManager.SetParent(parentEntity, movableBaseMountable, localPosition, localRotation);
                movableBaseMountable.Spawn();
                return movableBaseMountable;
            }

            public override void DismountAllPlayers()
            {
            }

            public override bool GetDismountPosition(BasePlayer player, out Vector3 res, bool silent = false)
            {
                res = player.transform.position + player.transform.right * 1.5f;
                return true;
            }

            public override void ScaleDamageForPlayer(BasePlayer player, HitInfo info)
            {

            }
        }

        private void OnServerInitialized()
        {
            _ins = this;
            UpdateConfig();

            if (!TryLoadData())
            {
                NotifyManager.PrintError(null, "DataNotFound_Exeption");
                NextTick(() => Interface.Oxide.UnloadPlugin(Name));
                return;
            }

            LoadDefaultMessages();
            Subscribes();
            BoatConnectionTrigger.UpdateAllBoats();
            FishingVillageZoneController.CacheVillages();
            CargoShipManager.UpdateAllCargos();
            Barge.LoadBarges();
            BargeSpawner.StartPeriodicSpawn();
        }

        private readonly Dictionary<string, BargeCustomizeData> _bargeCustomizations = new Dictionary<string, BargeCustomizeData>();

        protected override void LoadConfig()
        {
            base.LoadConfig();
            _config = Config.ReadObject<PluginConfig>();
            Config.WriteObject(_config, true);
        }
        private readonly HashSet<CargoShipManager> _cargoShipManagers = new HashSet<CargoShipManager>();

        private object CanEntityTakeDamage(BuildingBlock buildingBlock, HitInfo info)
        {
            if (buildingBlock == null || info == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(buildingBlock);
            if (barge == null)
                return null;

            if (Barge.IsBasicBuildingBlock(buildingBlock))
                return false;

            return null;
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private object OnEntityTakeDamage(BaseBoat baseBoat, HitInfo info)
        {
            if (baseBoat == null || info == null)
                return null;

            BaseEntity parentEntity = baseBoat.GetParentEntity();
            if (parentEntity == null || parentEntity.net == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(parentEntity);
            if (barge == null)
                return null;

            if (info.damageTypes.GetMajorityDamageType() == DamageType.Decay)
                return true;

            return null;
        }

        private class MarkerConfig
        {
            [JsonProperty(En ? "Use a vending marker? [true/false]" : "Добавить маркер магазина? [true/false]")] 
            public bool UseShopMarker { get; set; }
            
            [JsonProperty(En ? "Use a circular marker? [true/false]" : "Добавить круговой маркер? [true/false]")] 
            public bool UseRingMarker { get; set; }
            
            [JsonProperty(En ? "The marker will only appear on barges that were spawned automatically? [true/false]" : "Маркер будет отображаться только на баржах, заспавненных автоматически? [true/false]")] 
            public bool OnlyForServerBarges { get; set; }
            
            [JsonProperty(En ? "Display Name" : "Отображаемое название")] 
            public string DisplayName { get; set; }
            
            [JsonProperty(En ? "Radius" : "Радиус")] 
            public float Radius { get; set; }
            
            [JsonProperty(En ? "Alpha" : "Прозрачность")] 
            public float Alpha { get; set; }
            
            [JsonProperty(En ? "Marker color" : "Цвет маркера")] 
            public ColorConfig Color1 { get; set; }
            
            [JsonProperty(En ? "Outline color" : "Цвет контура")] 
            public ColorConfig Color2 { get; set; }
        }

        private object OnPoweredLightsPointAdd(StringLights stringLights, BasePlayer player, Vector3 vector31, Vector3 vector32)
        {
            if (player == null)
                return null;

            Barge barge = Barge.GetBargeByCollider(player);
            if (barge == null)
                return null;

            NotifyManager.SendMessageToPlayer(player, "BlockedOnBarge");
            return true;
        }

        private class BaseMessageConfig
        {
            [JsonProperty(En ? "Chat Message setting" : "Настройки сообщений в чате", Order = 1)] 
            public ChatConfig ChatConfig { get; set; }
            
            [JsonProperty(En ? "Facepunch Game Tips setting" : "Настройка сообщений Facepunch Game Tip", Order = 2)] 
            public GameTipConfig GameTipConfig { get; set; }
            
            [JsonProperty(En ? "GUI Announcements setting (only for GUIAnnouncements plugin)" : "Настройка GUI Announcements (только для тех, кто использует плагин GUI Announcements)", Order = 3)] 
            public GUIAnnouncementsConfig GUIAnnouncementsConfig { get; set; }
            
            [JsonProperty(En ? "Notify setting (only for Notify plugin)" : "Настройка Notify (только для тех, кто использует плагин Notify)", Order = 4)] 
            public NotifyPluginConfig NotifyPluginConfig { get; set; }
        }

        private class IoEntitySaveData
        {
            public readonly ulong IOEntityNetId;
            public readonly HashSet<IOSlotData> InputSlotsData;
            public readonly HashSet<IOSlotData> OutputSlotsData;

            public IoEntitySaveData(ulong ioEntityNetId, HashSet<IOSlotData> inputSlotsData, HashSet<IOSlotData> outputSlotsData)
            {
                IOEntityNetId = ioEntityNetId;
                InputSlotsData = inputSlotsData;
                OutputSlotsData = outputSlotsData;
            }
        }

        private readonly HashSet<Barge> _barges = new HashSet<Barge>();

        private void Unload()
        {
            if (_ins == null)
                return;

            Barge.SaveBarges(true);
            Barge.UnloadBarges();
            BargeSpawner.StopSpawning();
            BoatConnectionTrigger.Unload();
            FishingVillageZoneController.Unload();
            CargoShipManager.Unload();
            StorageItemsInstaller.Unload();
            _ins = null;
        }

        private bool TryLoadBargeDataFile(string path)
        {
            BargeCustomizeData bargeProfile = LoadDataFile<BargeCustomizeData>($"Platforms/{path}");

            if (bargeProfile == null || bargeProfile.BuoyancyPoints == null)
                return false;

            _bargeCustomizations.TryAdd(path, bargeProfile);
            return true;
        }
        
        private class PluginConfig
        {
            [JsonProperty(En ? "Version" : "Версия")] 
            public VersionNumber Version { get; set; }
            
            [JsonProperty(En ? "Chat Prefix" : "Префикс в чате")] 
            public string Prefix { get; set; }
            
            [JsonProperty(En ? "Spawn Config" : "Настройка спавна")] 
            public SpawnConfig SpawnConfig { get; set; }
            
            [JsonProperty(En ? "Performance Config" : "Настроки производительности")] 
            public PerformanceConfig PerformanceConfig { get; set; }
            
            [JsonProperty(En ? "General Setting" : "Основные настройки")] 
            public MainConfig MainConfig { get; set; }
            
            [JsonProperty(En ? "Presets of towed barges" : "Пресеты буксируемых барж")] 
            public HashSet<BargeConfig> BargeConfigs { get; set; }
            
            [JsonProperty(En ? "Notification Settings" : "Настройки уведомлений")] 
            public NotifyConfig NotifyConfig { get; set; }
            
            [JsonProperty(En ? "Map marker for unoccupied barges" : "Маркер на карте для свободных барж")] 
            public MarkerConfig MarkerConfig { get; set; }

            public static PluginConfig DefaultConfig()
            {
                return new PluginConfig
                {
                    Version = new VersionNumber(1, 2, 5),
                    Prefix = "[Barges]",
                    SpawnConfig = new SpawnConfig
                    {
                        AssignedTime = 300f,
                        IsSpawnEnabled = false,
                        MaxBargeCount = 10,
                        MinSpawnTime = 3600,
                        MaxSpawnTime = 43200,
                        Probabilities = new Dictionary<string, float>
                        {
                            ["rect_10x5"] = 5,
                            ["round_3"] = 5,
                            ["rect_5x5"] = 10,
                            ["rect_6x3"] = 10,
                            ["rect_3x3"] = 20,
                            ["rect_3x2"] = 20,
                            ["round_2"] = 20,
                        },
                        Monuments = new HashSet<SpawnMonumentConfig>
                        {
                            new SpawnMonumentConfig
                            {
                                MonumentName = "assets/bundled/prefabs/autospawn/monument/harbor/harbor_1.prefab",
                                IsEnabled = true,
                                Locations = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(115, 0, 170)",
                                        Rotation = "(0, 0, 0)"
                                    }
                                }
                            },
                            new SpawnMonumentConfig
                            {
                                MonumentName = "assets/bundled/prefabs/autospawn/monument/harbor/ferry_terminal_1.prefab",
                                IsEnabled = true,
                                Locations = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(100, 0, 145)",
                                        Rotation = "(0, 0, 0)"
                                    }
                                }
                            },
                        }
                    },
                    PerformanceConfig = new PerformanceConfig
                    {
                        AutoSkin = true,
                        AllowWood = true,
                        AllowMetal = true,
                        AllowStone = false,
                        AllowHqm = false,
                        UpdatePerTick = 15,
                        DontAnchorIfConnected = true,
                        AnchorTime = 300
                    },
                    MainConfig = new MainConfig
                    {
                        BlockFishingVillage = true,
                        MaxShoreDistance = 600,
                        BlockedItemShortnames = new HashSet<string>
                        {
                            "autoturret",
                            "samsite"
                        },
                        BlockedCommands = new HashSet<string>
                        {
                            "home add",
                            "sethome"
                        }
                    },
                    BargeConfigs = new HashSet<BargeConfig>
                    {
                        new BargeConfig
                        {
                            PresetName = "rect_10x5",
                            DataFileName = "rect_10x5",
                            MaxFloors = 3,
                            Mass = 17000,
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 1.5f,
                                PowerScale = 1,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "ramp_1",
                                    Position = "(0, 0, 15.7)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "cabine_1",
                                    Position = "(-5.326, 0, 15.93)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "connector_1",
                                    Position = "(0, 0, -15.93)",
                                    Rotation = "(0, 180, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-8.225, 1, 17)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-8.1, 0, 7)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(8.1, 0, 7)",
                                    Rotation = "(0, 180, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-8.1, 0, -7)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(8.1, 0, -7)",
                                    Rotation = "(0, 180, 0)"
                                }
                            },
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358067991,
                            }
                        },
                        new BargeConfig
                        {
                            PresetName = "rect_5x5",
                            DataFileName = "rect_5x5",
                            MaxFloors = 4,
                            Mass = 9000,
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 1,
                                PowerScale = 1,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "connector_1",
                                    Position = "(0, 0, 8.75)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-8.225, 1, 8.225)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-8.1, 0, 0)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(8.1, 0, 0)",
                                    Rotation = "(0, 180, 0)"
                                }
                            },
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358067724,
                            }
                        },
                        new BargeConfig
                        {
                            PresetName = "rect_6x3",
                            DataFileName = "rect_6x3",
                            MaxFloors = 4,
                            Mass = 6000,
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 1,
                                PowerScale = 1f,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "cabine_1",
                                    Position = "(-3, 0, 10.12)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "ramp_1",
                                    Position = "(0, 0, -9.5)",
                                    Rotation = "(0, 180, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-5.35, 1, 9.8)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(5.1, 0, 0)",
                                    Rotation = "(0, 180, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-5.1, 0, 0)",
                                    Rotation = "(0, 0, 0)"
                                }
                            },
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358067847,
                            }
                        },
                        new BargeConfig
                        {
                            PresetName = "rect_3x3",
                            DataFileName = "rect_3x3",
                            MaxFloors = 4,
                            Mass = 3000,
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 1,
                                PowerScale = 1,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "connector_1",
                                    Position = "(0, 0, 5.55)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-5.35, 1, 5.3)",
                                    Rotation = "(0, 0, 0)"
                                }
                            },
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358064514,
                            }
                        },
                        new BargeConfig
                        {
                            PresetName = "rect_3x2",
                            DataFileName = "rect_3x2",
                            MaxFloors = 3,
                            Mass = 2000,
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358106980,
                            },
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 0.5f,
                                PowerScale = 1f,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "cabine_1",
                                    Position = "(0, 0, 5.2)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-3.5, 1, 5.3)",
                                    Rotation = "(0, 0, 0)"
                                },
                            },
                        },
                        new BargeConfig
                        {
                            PresetName = "round_2",
                            DataFileName = "round_2",
                            MaxFloors = 5,
                            Mass = 7000,
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 1,
                                PowerScale = 1,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "connector_1",
                                    Position = "(0, 0, 6.2)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-3.6, 1, 6)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-5.105, 0, 2.924)",
                                    Rotation = "(0, 30, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(5.105, 0, 2.924)",
                                    Rotation = "(0, 150, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(5.105, 0, -2.924)",
                                    Rotation = "(0, 210, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-5.105, 0, -2.924)",
                                    Rotation = "(0, 330, 0)"
                                }
                            },
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358068089,
                            }
                        },
                        new BargeConfig
                        {
                            PresetName = "round_3",
                            DataFileName = "round_3",
                            MaxFloors = 6,
                            Mass = 12000,
                            EngineConfig = new EngineConfig
                            {
                                FuelScale = 1,
                                PowerScale = 1,
                                RotateScale = 1f
                            },
                            Modules = new HashSet<BargeModuleConfig>
                            {
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "connector_1",
                                    Position = "(0, 0, 9)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "anchor_1",
                                    Position = "(-4.5, 1, 8.5)",
                                    Rotation = "(0, 0, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-7.444, 0, -4.106)",
                                    Rotation = "(0, 330, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(-7.315, 0, 4.248)",
                                    Rotation = "(0, 30, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(7.376, 0, 4.186)",
                                    Rotation = "(0, 150, 0)"
                                },
                                new BargeModuleConfig
                                {
                                    IsEnable = true,
                                    PresetName = "dock_1",
                                    Position = "(7.32, 0, -4.25)",
                                    Rotation = "(0, 210, 0)"
                                },
                            },
                            ItemConfig = new ItemConfig
                            {
                                Shortname = "flare",
                                Name = "Barge",
                                Skin = 3358068268,
                            }
                        },
                    },
                    NotifyConfig = new NotifyConfig
                    {
                        ChatConfig = new ChatConfig
                        {
                            IsEnabled = false,
                        },
                        GameTipConfig = new GameTipConfig
                        {
                            IsEnabled = true,
                            Style = 1,
                        },
                        GUIAnnouncementsConfig = new GUIAnnouncementsConfig
                        {
                            IsEnabled = false,
                            BannerColor = "Grey",
                            TextColor = "White",
                            APIAdjustVPosition = 0.03f
                        },
                        NotifyPluginConfig = new NotifyPluginConfig
                        {
                            IsEnabled = false,
                            Type = 0
                        },
                        RedefinedMessages = new HashSet<RedefinedMessageConfig>
                        {
                            new RedefinedMessageConfig
                            {
                                IsEnable = true,
                                LangKey = "GotBarge",
                                ChatConfig = new ChatConfig
                                {
                                    IsEnabled = false,
                                },
                                GameTipConfig = new GameTipConfig
                                {
                                    IsEnabled = true,
                                    Style = 2,
                                },
                                GUIAnnouncementsConfig = new GUIAnnouncementsConfig
                                {
                                    IsEnabled = false,
                                    BannerColor = "Grey",
                                    TextColor = "White",
                                    APIAdjustVPosition = 0.03f
                                },
                                NotifyPluginConfig = new NotifyPluginConfig
                                {
                                    IsEnabled = false,
                                    Type = 0
                                },
                            }
                        }
                    },
                    MarkerConfig = new MarkerConfig
                    {
                        UseRingMarker = true,
                        UseShopMarker = true,
                        OnlyForServerBarges = true,
                        DisplayName = En ? "Unoccupied Barge" : "Свободная баржа",
                        Radius = 0.2f,
                        Alpha = 0.6f,
                        Color1 = new ColorConfig { R = 0.2f, G = 0.8f, B = 0.1f },
                        Color2 = new ColorConfig { R = 0f, G = 0f, B = 0f }
                    },
                };
            }
        }

        private class BuildingVisibilityManager : FacepunchBehaviour
        {
            private BaseEntity _parentEntity;
            private Coroutine _badBuildingBlocksCoroutine;
            private Coroutine _goodBuildingBlocksCoroutine;
            private readonly HashSet<BuildingBlock> _badBuildingBlocks = new HashSet<BuildingBlock>();
            private readonly HashSet<BuildingBlock> _goodBuildingBlocks = new HashSet<BuildingBlock>();
            private int _updatesPerTick;
            private readonly HashSet<IoEntitySaveData> _ioEntitiesSaveData = new HashSet<IoEntitySaveData>();

            public static void UpdateBuildingBlockVisibility(BuildingBlock buildingBlock)
            {
                if (buildingBlock.net.group.subscribers.Count == 0)
                    return;

                NetWrite newWrite = Net.sv.StartWrite();
                newWrite.PacketID(Message.Type.RPCMessage);
                newWrite.EntityID(buildingBlock.net.ID);
                newWrite.UInt32(StringPool.Get("RefreshSkin"));
                newWrite.UInt64(0);
                newWrite.Send(new SendInfo(buildingBlock.net.group.subscribers));
            }

            public static BuildingVisibilityManager AttachVisibilityUpdater(BaseEntity parentEntity)
            {
                GameObject triggerGameObject = new GameObject("BuildingVisibilityUpdater");
                triggerGameObject.transform.SetParent(parentEntity.transform, false);
                BuildingVisibilityManager buildingVisibilityUpdater = triggerGameObject.AddComponent<BuildingVisibilityManager>();
                buildingVisibilityUpdater.Init(parentEntity);
                _ins._buildingVisibilityUpdaters.Add(buildingVisibilityUpdater);
                return buildingVisibilityUpdater;
            }

            public void UpdateBuildingBlockModels()
            {
                foreach (BaseEntity entity in _parentEntity.children)
                {
                    BuildingBlock buildingBlock = entity as BuildingBlock;

                    if (buildingBlock != null)
                    {
                        UpdateBuildingBlockModel(buildingBlock);
                    }
                }
            }

            private static void DefineUpdateFrequency()
            {
                _ins._buildingVisibilityUpdaters.RemoveWhere(x => x == null);

                int sumBadBuildingBlocksCount = 0;

                foreach (BuildingVisibilityManager buildingVisibilityUpdater in _ins._buildingVisibilityUpdaters)
                {
                    if (buildingVisibilityUpdater == null)
                        continue;

                    sumBadBuildingBlocksCount += buildingVisibilityUpdater._badBuildingBlocks.Count;
                }
		   		 		  						  	   		   					  			 		   					  	 		
                foreach (BuildingVisibilityManager buildingVisibilityUpdater in _ins._buildingVisibilityUpdaters)
                {
                    if (buildingVisibilityUpdater == null)
                        continue;

                    buildingVisibilityUpdater._updatesPerTick = sumBadBuildingBlocksCount == 0 ? 0 : buildingVisibilityUpdater._badBuildingBlocks.Count * _ins._config.PerformanceConfig.UpdatePerTick / sumBadBuildingBlocksCount;

                    if (buildingVisibilityUpdater._updatesPerTick > buildingVisibilityUpdater._badBuildingBlocks.Count)
                        buildingVisibilityUpdater._updatesPerTick = buildingVisibilityUpdater._badBuildingBlocks.Count;
                }
            }

            private void Init(BaseEntity parentEntity)
            {
                this._parentEntity = parentEntity;
            }

            public void OnStartMoving()
            {
                _badBuildingBlocks.Clear();
                _goodBuildingBlocks.Clear();
                _ioEntitiesSaveData.Clear();

                foreach (BaseEntity entity in _parentEntity.children)
                {
                    BuildingBlock buildingBlock = entity as BuildingBlock;
                    if (buildingBlock != null)
                    {
                        CacheBuildingBlock(buildingBlock);
                        continue;
                    }

                    IOEntity iOEntity = entity as IOEntity;
                    if (iOEntity != null && iOEntity.ioType != IOEntity.IOType.Industrial)
                    {
                        CacheIOEntity(iOEntity);

                        if (iOEntity is PoweredWaterPurifier)
                        { 
                            foreach (BaseEntity baseEntity in iOEntity.children)
                            {
                                if (baseEntity is IOEntity ioEntity)
                                    CacheIOEntity(ioEntity);
                            }
                        }

                        continue;
                    }

                    if (entity is DoorCloser)
                    {
                        foreach (BaseEntity subChildren in entity.children)
                        {
                            BuildingBlock subBuildingBlock = subChildren as BuildingBlock;
                            if (subBuildingBlock != null)
                                CacheBuildingBlock(subBuildingBlock);
                        }
                    }
                }
		   		 		  						  	   		   					  			 		   					  	 		
                StopCoroutines();
                _badBuildingBlocksCoroutine = ServerMgr.Instance.StartCoroutine(BadBuildingBlocksCoroutine());
                _goodBuildingBlocksCoroutine = ServerMgr.Instance.StartCoroutine(GoodBuildingBlocksCoroutine());
                DefineUpdateFrequency();
            }

            private void CacheBuildingBlock(BuildingBlock buildingBlock)
            {
                UpdateBuildingBlockModel(buildingBlock);

                if (IsBuildingBlockGoodMoving(buildingBlock))
                    _goodBuildingBlocks.Add(buildingBlock);
                else
                    _badBuildingBlocks.Add(buildingBlock);
            }

            private static void UpdateBuildingBlockModel(BuildingBlock buildingBlock)
            {
                BuildingBlockModelInfo buildingBlockModelInfo = _ins._buildingBlockModelInfos.FirstOrDefault(x => x.Prefab == buildingBlock.PrefabName && x.BadModelStates.Contains((int)buildingBlock.modelState));

                if (buildingBlockModelInfo == null)
                    return;

                buildingBlock.SetConditionalModel((ulong)buildingBlockModelInfo.GoodModelState);
                buildingBlock.SendNetworkUpdate();
            }

            private static bool IsBuildingBlockGoodMoving(BuildingBlock buildingBlock)
            {
                if (buildingBlock.grade == BuildingGrade.Enum.Wood && buildingBlock.skinID == 10232)
                    return true;

                // if (buildingBlock.grade == BuildingGrade.Enum.Metal && buildingBlock.skinID == 10221)
                // {
                //     if (!buildingBlock.ShortPrefabName.Contains("floor"))
                //         return true;
                // }
		   		 		  						  	   		   					  			 		   					  	 		
                return false;
            }

            private void CacheIOEntity(IOEntity iOEntity)
            {
                HashSet<IOSlotData> inputSlots = new HashSet<IOSlotData>();
                HashSet<IOSlotData> outputSlots = new HashSet<IOSlotData>();

                foreach (var input in iOEntity.inputs)
                {
                    IOSlotData iOSlotData = GetSlotData(input);
                    inputSlots.Add(iOSlotData);
                }

                foreach (var output in iOEntity.outputs)
                {
                    IOSlotData iOSlotData = GetSlotData(output);
                    outputSlots.Add(iOSlotData);
                }

                iOEntity.SendNetworkUpdate();

                if (inputSlots.Count == 0 && outputSlots.Count == 0)
                    return;

                IoEntitySaveData ioEntitySaveData = new IoEntitySaveData(iOEntity.net.ID.Value, inputSlots, outputSlots);
                _ioEntitiesSaveData.Add(ioEntitySaveData);
            }

            private static IOSlotData GetSlotData(IOEntity.IOSlot slot)
            {
                HashSet<Vector3> linePoints = new HashSet<Vector3>();

                if (slot.linePoints != null)
                    foreach (Vector3 localPosition in slot.linePoints)
                        linePoints.Add(localPosition);

                slot.linePoints = new[] { Vector3.up };
                return new IOSlotData(linePoints);
            }

            private IEnumerator BadBuildingBlocksCoroutine()
            {
                while (true)
                {
                    int updatesInFrame = _updatesPerTick;

                    foreach (BuildingBlock buildingBlock in _badBuildingBlocks)
                    {
                        if (!buildingBlock.IsExists() || buildingBlock.IsDead())
                            continue;

                        updatesInFrame--;
                        UpdateBuildingBlockVisibility(buildingBlock);

                        if (updatesInFrame <= 0)
                        {
                            updatesInFrame = _updatesPerTick;
                            yield return CoroutineEx.waitForSeconds(0.05f);
                        }
                    }
		   		 		  						  	   		   					  			 		   					  	 		
                    yield return CoroutineEx.waitForSeconds(0.05f);
                }
            }

            private IEnumerator GoodBuildingBlocksCoroutine()
            {
                float lastWallFrameUpdate = Time.realtimeSinceStartup;

                while (true)
                {
                    bool shouldUpdateWallFrames = Time.realtimeSinceStartup - lastWallFrameUpdate > 30;
                    if (shouldUpdateWallFrames)
                        lastWallFrameUpdate = Time.realtimeSinceStartup;
		   		 		  						  	   		   					  			 		   					  	 		
                    foreach (BuildingBlock buildingBlock in _goodBuildingBlocks)
                    {
                        if (!buildingBlock.IsExists() || buildingBlock.IsDead())
                            continue;

                        if (shouldUpdateWallFrames && buildingBlock.grade == BuildingGrade.Enum.Metal && buildingBlock.skinID == 10221 && buildingBlock.ShortPrefabName == "wall.frame")
                        {
                            buildingBlock.limitNetworking = true;
                            buildingBlock.limitNetworking = false;
                        }
                        else
                        {
                            UpdateBuildingBlockVisibility(buildingBlock);
                        }
                        yield return CoroutineEx.waitForSeconds(0.025f);
                    }

                    yield return CoroutineEx.waitForSeconds(2.5f);
                }
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public void OnStopMoving()
            {
                StopCoroutines();

                foreach (BaseEntity entity in _parentEntity.children)
                {
                    BuildingBlock buildingBlock = entity as BuildingBlock;
                    if (buildingBlock != null && buildingBlock.net != null)
                    {
                        if (IsBuildingBlockGoodMoving(buildingBlock) && !buildingBlock.ShortPrefabName.Contains("stair"))
                        {
                            UpdateBuildingBlockVisibility(buildingBlock);
                        }
		   		 		  						  	   		   					  			 		   					  	 		
                        buildingBlock.limitNetworking = true;
                        buildingBlock.limitNetworking = false;
                        buildingBlock.SetConditionalModel(buildingBlock.currentSkin.DetermineConditionalModelState(buildingBlock));
                        buildingBlock.SendNetworkUpdate();

                        continue;
                    }

                    IOEntity iOEntity = entity as IOEntity;
                    if (iOEntity != null && iOEntity.net != null)
                    {
                        if (iOEntity is PoweredWaterPurifier)
                        { 
                            foreach (BaseEntity baseEntity in iOEntity.children)
                            {
                                if (baseEntity is IOEntity ioEntity)
                                    ResetIoEntity(ioEntity);
                            }
                        }
                        ResetIoEntity(iOEntity);
                    }
                }

                DefineUpdateFrequency();
            }

            private void ResetIoEntity(IOEntity iOEntity)
            {
                IoEntitySaveData ioEntitySaveData = _ioEntitiesSaveData.FirstOrDefault(x => x.IOEntityNetId == iOEntity.net.ID.Value);
                if (ioEntitySaveData != null)
                    ResetWires(iOEntity, ioEntitySaveData);

                iOEntity.limitNetworking = true;
                iOEntity.limitNetworking = false;
            }

            private void ResetWires(IOEntity iOEntity, IoEntitySaveData ioEntitySaveData)
            {
                int counter = 0;
                foreach (IOSlotData slotData in ioEntitySaveData.InputSlotsData)
                {
                    if (counter >= iOEntity.inputs.Length)
                        break;

                    IOEntity.IOSlot slot = iOEntity.inputs[counter];

                    if (slot == null)
                        break;

                    ResetSlot(iOEntity, slot, slotData);
                    counter++;
                }

                counter = 0;
                foreach (IOSlotData slotData in ioEntitySaveData.OutputSlotsData)
                {
                    if (counter >= iOEntity.outputs.Length)
                        break;

                    IOEntity.IOSlot slot = iOEntity.outputs[counter];

                    if (slot == null)
                        break;

                    ResetSlot(iOEntity, slot, slotData);
                    counter++;
                }

                iOEntity.SendNetworkUpdate();
            }

            private void ResetSlot(IOEntity iOEntity, IOEntity.IOSlot slot, IOSlotData iOSlotData)
            {
                slot.originPosition = iOEntity.transform.position;
                slot.originRotation = iOEntity.transform.rotation.eulerAngles;

                Vector3[] array = new Vector3[iOSlotData.LinePoints.Count];
                int counter = 0;

                foreach (Vector3 linePoint in iOSlotData.LinePoints)
                {
                    array[counter] = linePoint;
                    counter++;
                }

                slot.linePoints = array;
            }

            public void DestroyUpdater()
            {
                StopCoroutines();
                Destroy(this.gameObject);
            }

            private void StopCoroutines()
            {
                if (_badBuildingBlocksCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_badBuildingBlocksCoroutine);

                if (_goodBuildingBlocksCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_goodBuildingBlocksCoroutine);
            }

            private void OnDestroy()
            {
                StopCoroutines();
            }
        }

        private class BargeCustomizeData : CustomPrefabData
        {
            [JsonProperty("Buoyancy Points")] 
            public List<string> BuoyancyPoints { get; set; }
            
            [JsonProperty("Parent Collider Size")] 
            public string Size { get; set; }
        }

        private object OnEntityTakeDamage(BaseSubmarine baseSubmarine, HitInfo info)
        {
            if (baseSubmarine == null || info == null)
                return null;

            BaseEntity parentEntity = baseSubmarine.GetParentEntity();
            if (parentEntity == null || parentEntity.net == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(parentEntity);
            if (barge == null)
                return null;

            if (info.damageTypes.GetMajorityDamageType() == DamageType.Decay)
                return true;

            return null;
        }

        [ConsoleCommand("givebarge")]
        private void ConsoleGiveBargeCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null || arg.Args.Length < 2)
                return;

            string presetName = arg.Args[0].ToString();
            BargeConfig bargeConfig = _config.BargeConfigs.FirstOrDefault(x => x.PresetName == presetName);
            if (bargeConfig == null)
            {
                NotifyManager.PrintError(null, "ConfigNotFound_Exeption", presetName);
                return;
            }

            ulong targetUserId = ulong.Parse(arg.Args[1]);
            BasePlayer targetPlayer = BasePlayer.FindByID(targetUserId);
            if (targetPlayer == null)
            {
                NotifyManager.PrintError(null, "PlayerNotFound_Exeption", arg.Args[1]);
                return;
            }

            LootManager.GiveItemToPLayer(targetPlayer, bargeConfig.ItemConfig, 1);
            NotifyManager.SendMessageToPlayer(targetPlayer, "GotBarge");
        }

        private class RampModule : BaseModule
        {
            private Door _door;

            public static RampModule SpawnRampModule(BaseEntity parentEntity, Barge barge, CustomPrefabData customPrefabData)
            {
                RampModule rampModule = parentEntity.gameObject.AddComponent<RampModule>();
                rampModule.Init(parentEntity, barge);
                return rampModule;
            }

            protected override void Init(BaseEntity entity, Barge barge)
            {
                base.Init(entity, barge);
                _door = entity.children.FirstOrDefault(x => x.IsExists() && x is Door) as Door;
                PressButton.pressDuration = 15f;
                entity.Invoke(() => LoadModule(), 1f);
            }

            private void LoadModule()
            {
                foreach (BaseEntity entity in parentEntity.children)
                {
                    SimpleLight simpleLight = entity as SimpleLight;
                    if (simpleLight != null)
                    {
                        simpleLight.SetFlag(BaseEntity.Flags.On, true);
                        simpleLight.SetFlag(BaseEntity.Flags.InUse, true);
                    }
                }
            }

            public override void OnButtonPressed(BasePlayer player)
            {
                if (!_door.IsExists())
                    return;

                _door.SetOpen(!_door.IsOpen());
            }
        }

        private void OnEntitySpawned(BaseBoat baseBoat)
        {
            if (baseBoat == null)
                return;

            BoatConnectionTrigger.TryAddBoatConnectionTrigger(baseBoat);
        }

        private object OnEntityStabilityCheck(BuildingBlock buildingBlock)
        {
            if (buildingBlock == null || buildingBlock.net == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(buildingBlock);

            if (barge == null)
                return null;

            return true;
        }

        private BaseEntity RaycastAll<T>(Ray ray, float distance = 50) where T : BaseEntity
        {
            RaycastHit[] hits = Physics.RaycastAll(ray);
            GamePhysics.Sort(hits);
            BaseEntity target = null;

            foreach (RaycastHit hit in hits)
            {
                BaseEntity ent = hit.GetEntity();

                if (ent is T && hit.distance < distance)
                {
                    target = ent;
                    break;
                }
            }

            return target;
        }

        private void OnActiveItemChanged(BasePlayer player, Item oldItem, Item newItem)
        {
            if (!player.IsRealPlayer() || newItem == null)
                return;
		   		 		  						  	   		   					  			 		   					  	 		
            if (newItem.info.shortname is "storageadaptor" or "hopper")
                StorageItemsInstaller.TryAttachController(player, newItem);
        }

        private class ColorConfig
        {
            [JsonProperty("r")] 
            public float R { get; set; }
            
            [JsonProperty("g")] 
            public float G { get; set; }
            
            [JsonProperty("b")] 
            public float B { get; set; }
        }

        [ChatCommand("givebarge")]
        private void ChatGiveBargeCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin || arg.Length < 1)
                return;

            string presetName = arg[0];
            BargeConfig bargeConfig = _config.BargeConfigs.FirstOrDefault(x => x.PresetName == presetName);
            if (bargeConfig == null)
            {
                NotifyManager.PrintError(player, "ConfigNotFound_Exeption", presetName);
                return;
            }

            LootManager.GiveItemToPLayer(player, bargeConfig.ItemConfig, 1);
            NotifyManager.SendMessageToPlayer(player, "GotBarge");
        }

        private class GUIAnnouncementsConfig
        {
            [JsonProperty(En ? "Do you use GUI Announcements integration? [true/false]" : "Использовать ли GUI Announcements? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Banner color" : "Цвет баннера")] 
            public string BannerColor { get; set; }
            
            [JsonProperty(En ? "Text color" : "Цвет текста")] 
            public string TextColor { get; set; }
            
            [JsonProperty(En ? "Adjust Vertical Position" : "Отступ от верхнего края")] 
            public float APIAdjustVPosition { get; set; }
        }
        
        private const bool En = false;

        private  object CanEntityTakeDamage(BaseBoat baseBoat, HitInfo info)
        {
            if (baseBoat == null || info == null)
                return null;

            BaseEntity parentEntity = baseBoat.GetParentEntity();
            if (parentEntity == null || parentEntity.net == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(parentEntity);
            if (barge == null)
                return null;

            if (info.damageTypes.GetMajorityDamageType() == DamageType.Decay)
                return false;

            return null;
        }

        private class MarkerController : FacepunchBehaviour
        {
            private MapMarkerGenericRadius _radiusMarker;
            private VendingMachineMapMarker _vendingMarker;
            private Coroutine _updateCounter;
            private Barge _barge;

            public static MarkerController CreateMarker(Barge barge)
            {
                if (!_ins._config.MarkerConfig.UseRingMarker && !_ins._config.MarkerConfig.UseShopMarker)
                    return null;

                GameObject gameObject = new GameObject();
                gameObject.transform.position = barge.transform.position;
                gameObject.layer = 18;
                MarkerController mapMarker = gameObject.AddComponent<MarkerController>();
                mapMarker.Init(barge);
                return mapMarker;
            }

            private void Init(Barge barge)
            {
                this._barge = barge;
                CreateRadiusMarker();
                CreateVendingMarker();
                _updateCounter = ServerMgr.Instance.StartCoroutine(MarkerUpdateCounter());
            }

            private void CreateRadiusMarker()
            {
                if (!_ins._config.MarkerConfig.UseRingMarker)
                    return;

                _radiusMarker = GameManager.server.CreateEntity("assets/prefabs/tools/map/genericradiusmarker.prefab", this.gameObject.transform.position) as MapMarkerGenericRadius;
                _radiusMarker.enableSaving = false;
                _radiusMarker.Spawn();
                _radiusMarker.radius = _ins._config.MarkerConfig.Radius;
                _radiusMarker.alpha = _ins._config.MarkerConfig.Alpha;
                _radiusMarker.color1 = new Color(_ins._config.MarkerConfig.Color1.R, _ins._config.MarkerConfig.Color1.G, _ins._config.MarkerConfig.Color1.B);
                _radiusMarker.color2 = new Color(_ins._config.MarkerConfig.Color2.R, _ins._config.MarkerConfig.Color2.G, _ins._config.MarkerConfig.Color2.B);
            }

            private void CreateVendingMarker()
            {
                if (!_ins._config.MarkerConfig.UseShopMarker)
                    return;

                _vendingMarker = GameManager.server.CreateEntity("assets/prefabs/deployable/vendingmachine/vending_mapmarker.prefab", this.gameObject.transform.position) as VendingMachineMapMarker;
                _vendingMarker.enableSaving = false;
                _vendingMarker.Spawn();
                _vendingMarker.markerShopName = $"{_ins._config.MarkerConfig.DisplayName}";
                _vendingMarker.SetFlag(BaseEntity.Flags.Busy, false);
                _vendingMarker.SendNetworkUpdate();
            }

            private IEnumerator MarkerUpdateCounter()
            {
                while (_barge != null)
                {
                    UpdateVendingMarker();
                    UpdateRadiusMarker();
                    yield return CoroutineEx.waitForSeconds(1f);
                }
            }

            private void UpdateRadiusMarker()
            {
                if (!_radiusMarker.IsExists())
                    return;

                _radiusMarker.transform.position = _barge.transform.position;
                _radiusMarker.SendUpdate();
                _radiusMarker.SendNetworkUpdate();
            }

            private void UpdateVendingMarker()
            {
                if (!_vendingMarker.IsExists())
                    return;

                _vendingMarker.transform.position = _barge.transform.position;
                _vendingMarker.SetFlag(BaseEntity.Flags.Busy, true);
                _vendingMarker.SendNetworkUpdate();
            }

            public void Delete()
            {
                if (_radiusMarker.IsExists())
                    _radiusMarker.Kill();

                if (_vendingMarker.IsExists())
                    _vendingMarker.Kill();
		   		 		  						  	   		   					  			 		   					  	 		
                if (_updateCounter != null)
                    ServerMgr.Instance.StopCoroutine(_updateCounter);

                Destroy(this.gameObject);
            }
        }

        private void OnEntityKill(BuildingBlock buildingBlock)
        {
            if (buildingBlock == null || buildingBlock.net == null)
                return;
		   		 		  						  	   		   					  			 		   					  	 		
            Barge barge = Barge.GetBargeByEntity(buildingBlock);

            if (barge == null)
                return;

            barge.Invoke(() => barge.shouldUpdateStability = true, 0.1f);
        }
        
                private void UpdateConfig()
        {
            if (_config.Version != Version)
            {
                PluginConfig defaultConfig = PluginConfig.DefaultConfig();

                if (_config.Version.Minor == 0)
                {
                    if (_config.Version.Patch <= 1)
                    {
                        foreach (BargeConfig bargeConfig in _config.BargeConfigs)
                        {
                            bargeConfig.EngineConfig.RotateScale = 1f;
                        }
                    }
                    if (_config.Version.Patch <= 4)
                    {
                        _config.MainConfig.BlockedCommands = defaultConfig.MainConfig.BlockedCommands;
                    }
                    if (_config.Version.Patch <= 8)
                    {
                        _config.PerformanceConfig.DontAnchorIfConnected = true;
                        _config.PerformanceConfig.AnchorTime = 300;
                    }
                    _config.Version = new VersionNumber(1, 1, 0);
                }
                if (_config.Version.Minor == 1)
                {
                    if (_config.Version.Patch <= 2)
                    {
                        _config.MarkerConfig = defaultConfig.MarkerConfig;
                    }

                    _config.Version = new VersionNumber(1, 2, 0);
                }

                _config.Version = Version;
                SaveConfig();
            }
        }

        private void OnStructureUpgraded(BuildingBlock buildingBlock, BasePlayer player, BuildingGrade.Enum grade, ulong skin)
        {
            if (buildingBlock == null)
                return;

            Barge barge = Barge.GetBargeByEntity(buildingBlock);
            if (barge == null)
                return;

            if (!_config.PerformanceConfig.AutoSkin)
                return;

            if (grade == BuildingGrade.Enum.Wood && skin != 10232 && player.blueprints.CheckSkinOwnership(10232, player))
            {
                buildingBlock.ChangeGradeAndSkin(BuildingGrade.Enum.Wood, 10232, true);
            }
            // else if (grade == BuildingGrade.Enum.Metal && skin != 10221 && player.blueprints.CheckSkinOwnership(10221, player))
            // {
            //     buildingBlock.ChangeGradeAndSkin(BuildingGrade.Enum.Metal, 10221, true);
            //     buildingBlock.SetCustomColour(11);
            // }
        }

        private static class StabilityManager
        {
            public static void UpdateChildEntitiesStability(BaseEntity parentEntity)
            {
                HashSet<BuildingBlock> allBuildingBlocks = new HashSet<BuildingBlock>();
		   		 		  						  	   		   					  			 		   					  	 		
                foreach (BaseEntity entity in parentEntity.children)
                {
                    BuildingBlock buildingBlock = entity as BuildingBlock;

                    if (!buildingBlock.IsExists() || buildingBlock.grounded)
                        continue;

                    allBuildingBlocks.Add(buildingBlock);
                }
		   		 		  						  	   		   					  			 		   					  	 		
                allBuildingBlocks.OrderBy(x => x.transform.localPosition.y);

                foreach (BuildingBlock buildingBlock in allBuildingBlocks)
                    StabilityCheck(buildingBlock);

                UpdateGroundEntities(parentEntity);
            }

            private static void UpdateGroundEntities(BaseEntity parentEntity)
            {
                for (int i = 0; i < parentEntity.children.Count; i++)
                {
                    DecayEntity decayEntity = parentEntity.children[i] as DecayEntity;

                    if (!decayEntity.IsExists() || decayEntity is BuildingBlock)
                        continue;

                    decayEntity.parentEntity.Set(null);
                    decayEntity.BroadcastMessage("OnPhysicsNeighbourChanged", SendMessageOptions.DontRequireReceiver);
                    decayEntity.SetParent(parentEntity, false, true);
                }
            }

            private static void StabilityCheck(StabilityEntity stabilityEntity)
            {
                if (stabilityEntity.grounded)
                    return;

                int distanceFromGround = stabilityEntity.DistanceFromGround();

                stabilityEntity.cachedDistanceFromGround = distanceFromGround;
                float supportValue = SupportValue(stabilityEntity);

                if (supportValue <= ConVar.Stability.collapse)
                {
                    stabilityEntity.Kill(BaseNetworkable.DestroyMode.Gib);
                    return;
                }
		   		 		  						  	   		   					  			 		   					  	 		
                if (Mathf.Abs(stabilityEntity.cachedStability - supportValue) > ConVar.Stability.accuracy)
                    stabilityEntity.cachedStability = supportValue;

                stabilityEntity.SendNetworkUpdate();
            }

            private static float SupportValue(StabilityEntity buildingBlock)
            {
                if (buildingBlock.grounded)
                    return 1f;

                List<Support> supports = GetSupports(buildingBlock);
                float result = 0f;

                foreach (Support support in supports)
                {
                    StabilityEntity stabilityEntity = support.SupportEntity(buildingBlock);

                    if (stabilityEntity != null)
                    {
                        float supportValue = CachedSupportValue(stabilityEntity);

                        if (supportValue != 0f)
                            result += supportValue * support.factor;
                    }
                }

                return Mathf.Clamp01(result);
            }

            private static float CachedSupportValue(StabilityEntity buildingBlock)
            {
                if (buildingBlock.grounded)
                    return 1f;

                List<Support> supports = GetSupports(buildingBlock);
                float supportValue = 0f;

                foreach (Support item in supports)
                {
                    StabilityEntity stabilityEntity = item.SupportEntity(buildingBlock);
                    if (stabilityEntity != null)
                    {
                        float cachedStability = stabilityEntity.cachedStability;

                        if (cachedStability != 0f)
                            supportValue += cachedStability * item.factor;
                    }
                }
                return Mathf.Clamp01(supportValue);
            }

            private static List<Support> GetSupports(StabilityEntity buildingBlock)
            {
                List<Support> supports = new List<Support>();

                List<EntityLink> entityLinks = buildingBlock.GetEntityLinks();
                foreach (EntityLink entityLink in entityLinks)
                {
                    if (entityLink.IsMale())
                    {
                        if (entityLink.socket is StabilitySocket socket)
                        {
                            supports.Add(new Support(buildingBlock, entityLink, socket.support));
                        }

                        if (entityLink.socket is ConstructionSocket constructionSocket)
                        {
                            supports.Add(new Support(buildingBlock, entityLink, constructionSocket.support));
                        }
                    }
                }

                return supports;
            }
        }
        private readonly HashSet<BuildingVisibilityManager> _buildingVisibilityUpdaters = new HashSet<BuildingVisibilityManager>();

        private object CanEntityTakeDamage(BaseSubmarine baseSubmarine, HitInfo info)
        {
            if (baseSubmarine == null || info == null)
                return null;
		   		 		  						  	   		   					  			 		   					  	 		
            BaseEntity parentEntity = baseSubmarine.GetParentEntity();
            if (parentEntity == null || parentEntity.net == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(parentEntity);
            if (barge == null)
                return null;

            if (info.damageTypes.GetMajorityDamageType() == DamageType.Decay)
                return false;

            return null;
        }

        private class FishingVillageZoneController : FacepunchBehaviour
        {
            
            private SphereCollider _sphereCollider;
            private const float ZoneRadius = 55;

            public static void CacheVillages()
            {
                if (!_ins._config.MainConfig.BlockFishingVillage)
                    return;

                foreach (MonumentInfo monumentInfo in TerrainMeta.Path.Monuments)
                {
                    if (monumentInfo.name.Contains("fishing"))
                    {
                        GameObject gameObject = new GameObject("FishingVillageZoneController")
                        {
                            layer = (int)Layer.Reserved1,
                            transform =
                            {
                                position = monumentInfo.transform.position
                            }
                        };
                        FishingVillageZoneController zoneController = gameObject.AddComponent<FishingVillageZoneController>();
                        _ins._fishingVillageZoneControllers.Add(zoneController);
                    }
                }
            }
		   		 		  						  	   		   					  			 		   					  	 		
            private void Awake()
            {
                _sphereCollider = gameObject.AddComponent<SphereCollider>();
                _sphereCollider.isTrigger = true;
                _sphereCollider.radius = ZoneRadius;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            private void OnTriggerEnter(Collider other)
            {
                if (other == null)
                    return;

                BaseEntity entity = other.ToBaseEntity();
                if (entity == null || entity.net == null || entity.ShortPrefabName != "kayak")
                    return;
		   		 		  						  	   		   					  			 		   					  	 		
                Barge barge = Barge.GetBargeByPhysicsEntityNetId(entity.net.ID.Value);

                if (barge == null)
                    return;

                BargePhysics bargePhysics = barge.GetBargePhysics();
                bargePhysics.OnBargeEnterToBlockZoneZone(transform.position);
            }

            public static void Unload()
            {
                foreach (FishingVillageZoneController zoneController in _ins._fishingVillageZoneControllers)
                    if (zoneController != null)
                        zoneController.DeleteZone();
            }

            private void DeleteZone()
            {
                Destroy(this.gameObject);
            }
        }

        private object OnSetupTurret(AutoTurret autoTurret)
        {
            if (autoTurret == null || autoTurret.net == null)
                return null;

            if (Barge.GetBargeByEntity(autoTurret) != null)
                return true;

            return null;
        }

        private static class NotifyManager
        {
            public static void PrintError(BasePlayer player, string langKey, params object[] args)
            {
                if (player == null)
                    _ins.PrintError(ClearColorAndSize(GetMessage(langKey, null, args)));
                else
                    _ins.PrintToChat(player, GetMessage(langKey, player.UserIDString, args));
            }

            private static string ClearColorAndSize(string message)
            {
                message = message.Replace("</color>", string.Empty);
                message = message.Replace("</size>", string.Empty);
                while (message.Contains("<color="))
                {
                    int index = message.IndexOf("<color=");
                    message = message.Remove(index, message.IndexOf(">", index) - index + 1);
                }
                while (message.Contains("<size="))
                {
                    int index = message.IndexOf("<size=");
                    message = message.Remove(index, message.IndexOf(">", index) - index + 1);
                }
                return message;
            }

            public static void SendMessageToPlayer(BasePlayer player, string langKey, params object[] args)
            {
                for (int i = 0; i < args.Length; i++)
                    if (args[i] is int)
                        args[i] = GetTimeMessage(player.UserIDString, (int)args[i]);

                RedefinedMessageConfig redefinedMessageConfig = GetRedefinedMessageConfig(langKey);

                if (redefinedMessageConfig != null && !redefinedMessageConfig.IsEnable)
                    return;

                string playerMessage = GetMessage(langKey, player.UserIDString, args);

                if (redefinedMessageConfig != null)
                    SendMessage(redefinedMessageConfig, player, playerMessage);
                else
                    SendMessage(_ins._config.NotifyConfig, player, playerMessage);
            }

            private static void SendMessage(BaseMessageConfig baseMessageConfig, BasePlayer player, string playerMessage)
            {
                if (baseMessageConfig.ChatConfig.IsEnabled)
                    _ins.PrintToChat(player, playerMessage);

                if (baseMessageConfig.GameTipConfig.IsEnabled)
                    player.SendConsoleCommand("gametip.showtoast", baseMessageConfig.GameTipConfig.Style, ClearColorAndSize(playerMessage), string.Empty);
		   		 		  						  	   		   					  			 		   					  	 		
                if (baseMessageConfig.GUIAnnouncementsConfig.IsEnabled && _ins.plugins.Exists("guiAnnouncementsConfig"))
                    _ins.GUIAnnouncements?.Call("CreateAnnouncement", ClearColorAndSize(playerMessage), baseMessageConfig.GUIAnnouncementsConfig.BannerColor, baseMessageConfig.GUIAnnouncementsConfig.TextColor, player, baseMessageConfig.GUIAnnouncementsConfig.APIAdjustVPosition);

                if (baseMessageConfig.NotifyPluginConfig.IsEnabled && _ins.plugins.Exists("Notify"))
                    _ins.Notify?.Call("SendNotify", player, baseMessageConfig.NotifyPluginConfig.Type, ClearColorAndSize(playerMessage));
            }

            private static RedefinedMessageConfig GetRedefinedMessageConfig(string langKey)
            {
                return _ins._config.NotifyConfig.RedefinedMessages.FirstOrDefault(x => x.LangKey == langKey);
            }

            private static string GetTimeMessage(string userIDString, int seconds)
            {
                string message = "";

                TimeSpan timeSpan = TimeSpan.FromSeconds(seconds);
                if (timeSpan.Hours > 0) message += $" {timeSpan.Hours} {GetMessage("Hours", userIDString)}";
                if (timeSpan.Minutes > 0) message += $" {timeSpan.Minutes} {GetMessage("Minutes", userIDString)}";
                if (message == "") message += $" {timeSpan.Seconds} {GetMessage("Seconds", userIDString)}";

                return message;
            }
            
        }

        private static TYpe LoadDataFile<TYpe>(string path)
        {
            string fullPath = $"{_ins.Name}/{path}";
            return Interface.Oxide.DataFileSystem.ReadObject<TYpe>(fullPath);
        }

        private static string GetMessage(string langKey, string userID) => _ins.lang.GetMessage(langKey, _ins, userID);

        private class SpawnPositionInfo
        {
            public readonly Vector3 Position;
            public readonly Quaternion Rotation;

            public SpawnPositionInfo(Vector3 position, Quaternion rotation)
            {
                Position = position;
                Rotation = rotation;
            }
        }

        private static void SaveDataFile<TYpe>(TYpe objectForSaving, string path)
        {
            string fullPath = $"{_ins.Name}/{path}";
            Interface.Oxide.DataFileSystem.WriteObject(fullPath, objectForSaving);
        }
        
                [ChatCommand("spawnbarge")]
        private void ChatSpawnBargeCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            string bargePreset = "";

            if (arg.Length > 0)
                bargePreset = arg[0];

            Barge.SpawnBarge(player.transform.position, Quaternion.identity, bargePreset, false);
        }

        private void OnEntitySpawned(CargoShip cargoShip)
        {
            if (cargoShip == null)
                return;

            CargoShipManager.AttachController(cargoShip);
        }
        
                private PluginConfig _config;

        private void OnEntityMounted(BaseMountable baseMountable, BasePlayer player)
        {
            if (baseMountable == null || player == null)
                return;

            BaseVehicle baseVehicle = baseMountable.VehicleParent();
            if (baseVehicle == null || !baseVehicle.IsDriver(player))
                return;

            if (baseVehicle is not BaseBoat && baseVehicle is not BaseSubmarine)
                return;

            BaseEntity parentEntity = baseVehicle.GetParentEntity();
            if (parentEntity == null)
                return;

            Barge barge = Barge.GetBargeByEntity(parentEntity);
            if (barge == null)
                return;

            DockModule dockModule = parentEntity.GetComponentInChildren<DockModule>();

            if (dockModule == null || !barge.IsPlayerCanInteract(player, true))
                return;

            dockModule.ReleaseBoat();
        }

        private object OnStructureRotate(BaseCombatEntity entity, BasePlayer player)
        {
            if (entity == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(entity);
            if (barge == null)
                return null;

            if (!barge.IsStopped())
            {
                NotifyManager.SendMessageToPlayer(player, "AnchorBarge");
                return true;
            }

            return null;
        }
        private static Barges _ins;

        private object OnEntityTakeDamage(BuildingBlock buildingBlock, HitInfo info)
        {
            if (buildingBlock == null || info == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(buildingBlock);
            if (barge == null)
                return null;

            if (Barge.IsBasicBuildingBlock(buildingBlock))
                return true;

            return null;
        }
        [PluginReference] Plugin GUIAnnouncements, Notify, DiscordMessages;
        private readonly HashSet<SpawnPositionInfo> _spawnLocations = new HashSet<SpawnPositionInfo>();
        
        
        private HashSet<BargeSaveData> _bargeSaveData = new HashSet<BargeSaveData>();

        private object OnWireClear(BasePlayer player, IOEntity entity1, int connected, IOEntity entity2, bool flag)
        {
            if (entity1 == null || entity2 == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(entity1);
            if (barge == null)
                barge = Barge.GetBargeByEntity(entity2);

            if (barge == null)
                return null;

            if (!barge.IsStopped())
            {
                NotifyManager.SendMessageToPlayer(player, "BlockedWhileMoving");
                return true;
            }

            return null;
        }

        private class CustomPrefabData
        {
            [JsonProperty("The location of the basic building blocks (Cannot be destroyed)")] 
            public HashSet<BuildingBlockData> BuildingBlocks { get; set; }
            
            [JsonProperty("Regular Entities")] 
            public HashSet<EntityData> RegularEntities { get; set; }
            
            [JsonProperty("Decor Entities")] 
            public HashSet<EntityData> DecorEntities { get; set; }
            
            [JsonProperty("Box Colliders")] 
            public HashSet<BoxColliderData> BoxCollidersData { get; set; }
        }

        private void OnButtonPress(PressButton button, BasePlayer player)
        {
            if (player == null || button == null)
                return;

            Barge barge = Barge.GetBargeByEntity(button);

            if (barge == null)
                return;

            BaseEntity parentEntity = button.GetParentEntity();

            if (parentEntity == null)
                return;

            BaseModule baseModule = parentEntity.GetComponentInChildren<BaseModule>();

            if (baseModule == null || !barge.IsPlayerCanInteract(player, true))
                return;

            baseModule.OnButtonPressed(player);
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private void OnExplosiveDropped(BasePlayer player, RoadFlare roadFlare, ThrownWeapon thrownWeapon)
        {
            OnPlayerDropFlare(player, roadFlare, thrownWeapon);
        }

        private class NotifyPluginConfig
        {
            [JsonProperty(En ? "Do you use Notify integration? [true/false]" : "Использовать ли Notify? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Type" : "Тип")] 
            public int Type { get; set; }
        }

        private class BargePhysics : FacepunchBehaviour
        {
            private Barge _barge;
            private Rigidbody _rigidbody;
            private Buoyancy _buoyancy;
            private List<string> _buoyancyPoints;
            private HashSet<BoxColliderInfo> _boxColliderInfos = new HashSet<BoxColliderInfo>();
            private BaseEntity _fakePhysicsEntity;
            private ConnectorModule _connectorModule;
            private float _lastBadZoneEnterTime;

            public void Init(Barge barge, BargeCustomizeData bargeData, HashSet<BoxColliderInfo> boxColliserInfos, ConnectorModule connectorModule)
            {
                this._barge = barge;
                this._boxColliderInfos = boxColliserInfos;
                this._connectorModule = connectorModule;
                _buoyancyPoints = bargeData.BuoyancyPoints;
            }

            public void SwitchPhysics(bool isPhysicsEnable)
            {
                if (isPhysicsEnable)
                {
                    if (_fakePhysicsEntity.IsExists())
                        return;

                    CreateFakePhysicEntity();
                    CreateFakeEntityColliders();
                }
                else
                {
                    if (_fakePhysicsEntity.IsExists())
                        _fakePhysicsEntity.Kill();
                }
            }

            public bool IsPhysicsEnable()
            {
                return _fakePhysicsEntity.IsExists();
            }

            public int GetSpeed()
            {
                if (!IsPhysicsEnable())
                    return 0;

                return (int)_rigidbody.velocity.magnitude;
            }

            private void CreateFakePhysicEntity()
            {
                Kayak kayak = GameManager.server.CreateEntity("assets/content/vehicles/boats/kayak/kayak.prefab", _barge.transform.position, _barge.transform.rotation) as Kayak;
                kayak.enableSaving = false;
                BaseEntity baseVehicle = kayak.gameObject.AddComponent<BaseEntity>();
                BuildManager.CopySerializableFields(kayak, baseVehicle);
                kayak.StopAllCoroutines();
                DestroyImmediate(kayak, true);
                baseVehicle.SetFlag(BaseEntity.Flags.On, true);
                _fakePhysicsEntity = baseVehicle;

                _fakePhysicsEntity.Spawn();
                BuildManager.DestroyEntityComponents<EntityCollisionMessage>(_fakePhysicsEntity);
                BuildManager.DestroyEntityComponents<BuoyancyPoint>(_fakePhysicsEntity);
                BuildManager.DestroyEntityComponent<TriggerPlayerTimer>(_fakePhysicsEntity);
                BuildManager.DestroyEntityComponent<TriggerNotify>(_fakePhysicsEntity);

                _rigidbody = _fakePhysicsEntity.GetComponentInChildren<Rigidbody>();
                _rigidbody.centerOfMass = new Vector3(0, -10f, 0);
                _rigidbody.mass = _barge.BargeConfig.Mass;
                _rigidbody.drag = 1;
                _rigidbody.angularDrag = 1;
                _rigidbody.sleepThreshold = 0;
                _rigidbody.isKinematic = true;

                _buoyancy = _fakePhysicsEntity.gameObject.GetComponentInChildren<Buoyancy>();
                _buoyancy.requiredSubmergedFraction = 0.5f;
                _buoyancy.wavesEffect = 0.6f;
                _buoyancy.points = new BuoyancyPoint[_buoyancyPoints.Count];
                _buoyancy.SetPrivateFieldValue("timeInWater", 0f);
                _buoyancy.InvokeRepeating(() => _buoyancy.SetPrivateFieldValue("timeInWater", 1f), 1f, 4f);

                for (int i = 0; i < _buoyancyPoints.Count; i++)
                {
                    GameObject gameObj = new GameObject("BuoyancyPoint");
                    gameObj.transform.parent = _fakePhysicsEntity.gameObject.transform;
                    gameObj.transform.localPosition = _buoyancyPoints[i].ToVector3();
                    BuoyancyPoint buoyancyPoint = gameObj.AddComponent<BuoyancyPoint>();
                    buoyancyPoint.buoyancyForce = (_rigidbody.mass / (_buoyancyPoints.Count / 2f) * -Physics.gravity.y);
                    buoyancyPoint.waveFrequency = 0.5f;
                    buoyancyPoint.waveScale = 1;
                    buoyancyPoint.size = 1;
                    _buoyancy.points[i] = buoyancyPoint;
                }

                _buoyancy.SavePointData(true);
                CreateFakeEntityColliders();

                _fakePhysicsEntity.Invoke(() =>
                {
                    CollisionDisabler.AttachCollisionDisabler(_fakePhysicsEntity, _barge);
                    _rigidbody.isKinematic = false;
                    _rigidbody.WakeUp();
                    _buoyancy.SetPrivateFieldValue("timeInWater", 1f);
                    _buoyancy.Wake();
		   		 		  						  	   		   					  			 		   					  	 		
                    if (_barge.IsBargeOnShoal())
                    {
                        _fakePhysicsEntity.transform.position += Vector3.up * 1.25f;
                    }

                }, 1f);
            }

            private void CreateFakeEntityColliders()
            {
                if (_boxColliderInfos.Any(x => x.LocalRotation != Vector3.zero))
                {
                    foreach (BoxColliderInfo boxColliderInfo in _boxColliderInfos)
                    {
                        GameObject gameObj = new GameObject("BuildingBlockCustomCollider");
                        gameObj.transform.localPosition = boxColliderInfo.LocalPosition;
                        gameObj.transform.localEulerAngles = boxColliderInfo.LocalRotation;
                        gameObj.transform.SetParent(_fakePhysicsEntity.transform, false);
                        gameObj.layer = 9;

                        BoxCollider boxCollider = gameObj.AddComponent<BoxCollider>();
                        boxCollider.size = boxColliderInfo.Size;
                        boxCollider.center = Vector3.zero;
                        boxCollider.material.dynamicFriction = 0f;
                        boxCollider.material.staticFriction = 0f;
                    }
                }
                else
                {
                    GameObject gameObj = new GameObject("BuildingBlockCustomCollider");
                    gameObj.transform.SetParent(_fakePhysicsEntity.transform, false);
                    gameObj.layer = 9;

                    foreach (BoxColliderInfo boxColliderInfo in _boxColliderInfos)
                    {
                        BoxCollider boxCollider = gameObj.AddComponent<BoxCollider>();
                        boxCollider.size = boxColliderInfo.Size;
                        boxCollider.center = boxColliderInfo.LocalPosition;
                        boxCollider.material.dynamicFriction = 0;
                        boxCollider.material.staticFriction = 0;
                    }
                }
            }

            public bool IsMyKayak(ulong netID)
            {
                return _fakePhysicsEntity != null && _fakePhysicsEntity.net != null && _fakePhysicsEntity.net.ID.Value == netID;
            }

            private void FixedUpdate()
            {
                if (!_fakePhysicsEntity.IsExists())
                    return;

                CheckShoreDistance();
                _barge.mainEntity.transform.position = _fakePhysicsEntity.transform.position;
                _barge.mainEntity.transform.rotation = _fakePhysicsEntity.transform.rotation;
            }

            private void CheckShoreDistance()
            {
                if (_ins._config.MainConfig.MaxShoreDistance <= 0)
                    return;

                float xDistanceToShore = Mathf.Abs(_fakePhysicsEntity.transform.position.x) - World.Size / 2f;
                float zDistanceToShore = Mathf.Abs(_fakePhysicsEntity.transform.position.z) - World.Size / 2f;
                float distanceToShore = xDistanceToShore > zDistanceToShore ? xDistanceToShore : zDistanceToShore;

                if (!(distanceToShore > _ins._config.MainConfig.MaxShoreDistance)) 
                    return;
                
                if (Time.realtimeSinceStartup - _lastBadZoneEnterTime < 5)
                    return;

                PushBarge(-_fakePhysicsEntity.transform.position.normalized);
            }

            public void OnBargeEnterToBlockZoneZone(Vector3 safeZonePosition)
            {
                Vector3 direction = (_fakePhysicsEntity.transform.position - safeZonePosition).normalized;
                PushBarge(direction);

                if (_connectorModule != null)
                    _connectorModule.BreakConnection();
            }

            public void PushBarge(Vector3 direction)
            {
                _rigidbody.velocity = 10 * direction;
                _lastBadZoneEnterTime = Time.realtimeSinceStartup;
            }

            public void AddForceAtPosition(Vector3 force, Vector3 position)
            {
                if (_rigidbody == null)
                    return;

                if (Time.realtimeSinceStartup - _lastBadZoneEnterTime < 5)
                    return;

                _rigidbody.AddForceAtPosition(force * (_rigidbody.mass * 5), position);
            }

            public void OnBoatWantConnect(BaseBoat baseBoat)
            {
                if (_connectorModule == null || _fakePhysicsEntity == null || _connectorModule.springJoint != null || !_connectorModule.isActive)
                    return;

                Vector3 boatConnectorLocalPosition = baseBoat is Tugboat ? new Vector3(0, 0, -11.5f) : baseBoat is RHIB ? new Vector3(0, 0.75f, -4.5f) : Vector3.zero;
                Vector3 boatConnectorGlobalPosition = PositionDefiner.GetGlobalPosition(baseBoat.transform, boatConnectorLocalPosition);
                Vector3 connectorLocalPosition = new Vector3(0, 0f, 5.3f);
                Vector3 connectorGlobalPosition = PositionDefiner.GetGlobalPosition(_connectorModule.transform, connectorLocalPosition);
                float distanceToConnector = Vector3.Distance(boatConnectorGlobalPosition, connectorGlobalPosition);

                if (distanceToConnector < 2.5f)
                {
                    _connectorModule.springJoint = _fakePhysicsEntity.gameObject.AddComponent<SpringJoint>();
                    _connectorModule.springJoint.connectedBody = baseBoat.rigidBody;
                    _connectorModule.springJoint.autoConfigureConnectedAnchor = false;

                    _connectorModule.springJoint.breakForce = float.MaxValue;
                    _connectorModule.springJoint.breakTorque = float.MaxValue;
                    _connectorModule.springJoint.connectedAnchor = boatConnectorLocalPosition;
                    _connectorModule.springJoint.anchor = PositionDefiner.GetLocalPosition(_fakePhysicsEntity.transform, connectorGlobalPosition);
                    _connectorModule.springJoint.enableCollision = true;

                    _connectorModule.springJoint.spring = _rigidbody.mass * 10;
                    _connectorModule.springJoint.minDistance = 0.4f;
                    _connectorModule.springJoint.maxDistance = 0.45f;

                    _connectorModule.SwitchConnector(false);
                    _connectorModule.connectedRigidbody = baseBoat.rigidBody;
                }
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public void OnBargeWantConnect(BargePhysics bargePhysics)
            {
                if (bargePhysics == this)
                    return;

                if (_connectorModule == null || bargePhysics._connectorModule == null)
                    return;

                if (!_connectorModule.isActive || !bargePhysics._connectorModule.isActive)
                    return;

                if (_connectorModule == null || _fakePhysicsEntity == null || _connectorModule.springJoint != null)
                    return;

                if (bargePhysics._connectorModule == null || bargePhysics._fakePhysicsEntity == null || bargePhysics._connectorModule.springJoint != null)
                    return;

                if (_fakePhysicsEntity.net.ID.Value < bargePhysics._fakePhysicsEntity.net.ID.Value)
                    return;

                Vector3 connectorLocalPosition = new Vector3(0, 0f, 5.3f);
                Vector3 connectorGlobalPosition = PositionDefiner.GetGlobalPosition(_connectorModule.transform, connectorLocalPosition);
                Vector3 otherConnectorGlobalPosition = PositionDefiner.GetGlobalPosition(bargePhysics._connectorModule.transform, connectorLocalPosition);
                float distanceToConnector = Vector3.Distance(otherConnectorGlobalPosition, connectorGlobalPosition);

                if (distanceToConnector < 2.5f)
                {
                    SpringJoint springJoint = _fakePhysicsEntity.gameObject.AddComponent<SpringJoint>();
                    springJoint.connectedBody = bargePhysics._rigidbody;
                    springJoint.autoConfigureConnectedAnchor = false;

                    springJoint.breakForce = float.MaxValue;
                    springJoint.breakTorque = float.MaxValue;
                    springJoint.connectedAnchor = PositionDefiner.GetLocalPosition(bargePhysics._fakePhysicsEntity.transform, otherConnectorGlobalPosition);
                    springJoint.anchor = PositionDefiner.GetLocalPosition(_fakePhysicsEntity.transform, connectorGlobalPosition);
                    springJoint.enableCollision = true;

                    springJoint.spring = _rigidbody.mass * 10;
                    springJoint.minDistance = 0.4f;
                    springJoint.maxDistance = 0.45f;

                    _connectorModule.SwitchConnector(false);
                    bargePhysics._connectorModule.SwitchConnector(false);
                    _connectorModule.connectedRigidbody = bargePhysics._rigidbody;
                    _connectorModule.springJoint = springJoint;
                    bargePhysics._connectorModule.connectedRigidbody = _rigidbody;
                }
            }

            public void DestroyPhysics()
            {
                if (_fakePhysicsEntity.IsExists())
                    _fakePhysicsEntity.Kill();

                UnityEngine.GameObject.Destroy(this);
            }
        }
        
                private void Init()
        {
            Unsubscribes();
        }

        private  class BargeSaveData
        {
            public ulong NetId;
            public string BargePreset;
            public bool IsServerSpawn;
            public int FuelAmount;
        }
        private readonly HashSet<StorageItemsInstaller> _adaptorControllers = new HashSet<StorageItemsInstaller>();

        private object CanBuild(Planner planner, Construction prefab, Construction.Target constructionTarget)
        {
            if (planner == null || constructionTarget.entity == null)
                return null;

            BasePlayer player = planner.GetOwnerPlayer();
            if (player == null)
                return null;

            BaseEntity parentEntity = constructionTarget.entity.GetParentEntity();
            if (parentEntity == null || parentEntity.net == null)
                return null;

            Barge barge = Barge.GetBargeByParentEntityNetId(parentEntity.net.ID.Value);
            if (barge == null)
            {
                if (Barge.GetBargeByEntity(parentEntity) != null)
                    return true;
		   		 		  						  	   		   					  			 		   					  	 		
                return null;
            }

            Item item = planner.GetItem();
            if (item != null && item.info != null && _config.MainConfig.BlockedItemShortnames.Contains(item.info.shortname))
            {
                NotifyManager.SendMessageToPlayer(player, "BlockedOnBarge");
                return true;
            }

            if (!barge.IsPlayerCanInteract(player, true))
                return true;

            if (planner.ShortPrefabName.Contains("wallpaper") || (prefab != null && prefab.deployable != null && (prefab.deployable.fullName.Contains("frankensteintable") || prefab.deployable.fullName == "assets/prefabs/deployable/elevator/elevator.prefab")))
            {
                NotifyManager.SendMessageToPlayer(player, "BlockedOnBarge");
                return true;
            }

            if (!barge.IsPlayerCanBuild(player, true))
                return true;
		   		 		  						  	   		   					  			 		   					  	 		
            return null;
        }

        private object CanPickupEntity(BasePlayer player, PressButton preseButton)
        {
            if (preseButton == null)
                return null;

            if (preseButton.HasFlag(BaseEntity.Flags.InUse))
                return false;

            return true;
        }

        private object CanLootEntity(BasePlayer player, ResourceExtractorFuelStorage container)
        {
            if (container == null || player == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(container);
            if (barge == null)
                return null;

            if (container.GetParentEntity() is DoorCloser && !barge.IsPlayerCanInteract(player, true))
                return true;

            return null;
        }

        private class DockModule : BaseModule
        {
            private SphereCollider _sphereCollider;
            private BaseVehicle _grabBoat;
            private bool _isFullyGrab;
            private float _idealLocalRotation;
            private Vector3 _idealLocalPosition;
            private float _lastReleaseTime;

            public static DockModule SpawnDockModule(BaseEntity parentEntity, Barge barge, CustomPrefabData customPrefabData)
            {
                DockModule dockModule = parentEntity.gameObject.AddComponent<DockModule>();
                dockModule.Init(parentEntity, barge);
                dockModule.LoadModule();
                return dockModule;
            }

            private void LoadModule()
            {
                parentEntity.gameObject.layer = 18;
                _sphereCollider = parentEntity.gameObject.AddComponent<SphereCollider>();
                _sphereCollider.radius = 3f;
                _sphereCollider.center = new Vector3(-2.523f, 0, 0);
                _sphereCollider.isTrigger = true;

                BaseVehicle baseVehicle = parentEntity.children.FirstOrDefault(x => x != null && x is BaseVehicle) as BaseVehicle;
                if (baseVehicle != null)
                    TryGrabBoat(baseVehicle, false);
            }

            public override void OnButtonPressed(BasePlayer player)
            {
                ElectricEffect();

                if (_grabBoat != null)
                    ReleaseBoat();
            }

            private void ElectricEffect()
            {
                Effect.server.Run("assets/prefabs/locks/keypad/effects/lock.code.shock.prefab", PositionDefiner.GetGlobalPosition(parentEntity.transform, new Vector3(-1.742f, 0.162f, 0.557f)), broadcast: true);
                Effect.server.Run("assets/prefabs/locks/keypad/effects/lock.code.shock.prefab", PositionDefiner.GetGlobalPosition(parentEntity.transform, new Vector3(-1.742f, 0.162f, -0.557f)), broadcast: true);
            }

            private void OnTriggerEnter(Collider other)
            {
                if (_grabBoat != null)
                    return;

                BaseEntity baseEntity = other.ToBaseEntity();

                if (baseEntity == null)
                    return;

                BaseVehicle vehicle = baseEntity as BaseVehicle;

                if (vehicle == null || vehicle.HasParent() || vehicle is Tugboat || !(vehicle is RHIB or MotorRowboat or BaseSubmarine))
                    return;
		   		 		  						  	   		   					  			 		   					  	 		
                if (Time.realtimeSinceStartup - _lastReleaseTime < 10)
                    return;

                TryGrabBoat(vehicle, true);
            }

            private void TryGrabBoat(BaseVehicle baseVehicle, bool checkPlayers)
            {
                if (checkPlayers)
                {
                    List<BasePlayer> mountedPlayers = Pool.Get<List<BasePlayer>>();
                    baseVehicle.GetMountedPlayers(mountedPlayers);

                    if (mountedPlayers.Count > 0 && !mountedPlayers.Any(x => x != null && Barge.IsPlayerCanInteract(x, false)))
                    {
                        Pool.FreeUnmanaged(ref mountedPlayers);
                        return;
                    }

                    Pool.FreeUnmanaged(ref mountedPlayers);
                }

                ElectricEffect();
                _grabBoat = baseVehicle;
                _idealLocalRotation = Vector3.Angle(parentEntity.transform.forward, _grabBoat.transform.forward) <= 90 ? 0 : 180;
                _idealLocalPosition = _grabBoat is RHIB ? new Vector3(-2.731f, -1.115f, 0) : _grabBoat is MotorRowboat ? new Vector3(-2.7f, -0.453f, 0) : _grabBoat is SubmarineDuo ? new Vector3(-2.498f, -0.345f, 0) : new Vector3(-2.529f, -0.526f, 0);
                _grabBoat.SetToKinematic();
                _grabBoat.SetParent(parentEntity, true);
                _isFullyGrab = false;
            }

            public void ReleaseBoat()
            {
                if (_grabBoat == null)
                    return;

                _grabBoat.SetParent(null, true);
                _grabBoat.SetToNonKinematic();
                _grabBoat.rigidBody.detectCollisions = true;
                _grabBoat = null;
                _lastReleaseTime = Time.realtimeSinceStartup;
                _isFullyGrab = false;
            }

            private void FixedUpdate()
            {
                if (_grabBoat != null && _grabBoat.HasParent())
                {
                    if (!_isFullyGrab)
                    {
                        Vector3 targetRotation = _idealLocalRotation == 0 ? new Vector3(0, (_grabBoat.transform.localEulerAngles.y + _idealLocalRotation) / 1.05f, 0) : new Vector3(0, 180 + (_grabBoat.transform.localEulerAngles.y - 180 + 0) / 1.05f, 0);
                        _grabBoat.transform.localPosition = Vector3.Lerp(_grabBoat.transform.localPosition, _idealLocalPosition, 0.1f);
                        _grabBoat.transform.localEulerAngles = targetRotation;

                        if (Vector3.Distance(_grabBoat.transform.localPosition, _idealLocalPosition) < 0.02f)
                        {
                            _isFullyGrab = true;
                            _grabBoat.transform.localPosition = _idealLocalPosition;
                            _grabBoat.transform.localEulerAngles = new Vector3(0, _idealLocalRotation, 0);
                        }
                    }
                }
                else
                {
                    ReleaseBoat();
                }
            }
        }

        private object OnPlayerCommand(BasePlayer player, string command, string[] args)
        {
            if (player == null)
                return null;

            string checkCommand = command;

            foreach (string arg in args)
                checkCommand += $" {arg}";

            if (_config.MainConfig.BlockedCommands.Any(x => x.ToLower() == checkCommand.ToLower()))
            {
                Barge barge = Barge.GetBargeByCollider(player);
                if (barge == null)
                    return null;

                NotifyManager.SendMessageToPlayer(player, "BlockedOnBarge");
                return true;
            }

            return null;
        }

        private void OnHammerHit(BasePlayer player, HitInfo info)
        {
            if (player == null || info == null || info.HitEntity == null)
                return;

            if (info.HitEntity.ShortPrefabName != "coaling_tower_fuel_storage.entity")
                return;

            Barge barge = Barge.GetBargeByEntity(info.HitEntity);
            if (barge == null || barge.IsStopped())
                return;

            BargePhysics bargePhysics = barge.GetBargePhysics();
            Vector3 pushDirection = (barge.transform.position - player.transform.position).normalized;
            pushDirection.y = 0;

            if (!barge.IsBargeOnShoal())
                return;

            if (TerrainMeta.HeightMap.GetHeight(barge.transform.position + pushDirection) > TerrainMeta.HeightMap.GetHeight(barge.transform.position))
                return;

            barge.StartMoving();
            bargePhysics.PushBarge(pushDirection);
        }

        private static string GetMessage(string langKey, string userID, params object[] args) => (args.Length == 0) ? GetMessage(langKey, userID) : string.Format(GetMessage(langKey, userID), args);
        
                protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GotBarge"] = "Вы получили Баржу!",

                ["BlockGrade"] = "Постройка из камня/МВК слишком тяжелая и не может плавать!",
                ["TooHigh"] = "Постройка слишком высокая. Максимальное количество этажей - {0}",
                ["OutsideBarge"] = "Запрещено строиться за пределами баржи",
                ["NotAuthorized"] = "Вы не авторизованы!",
                ["AnchorBarge"] = "Поставьте баржу на якорь!",

                ["WrongPosition"] = "Баржа в неверном положении. Поставьте ее на якорь еще раз.",
                ["WrongPositionOnShole"] = "Баржа на мелководье. Строительство запрещено!",

                ["BlockedOnBarge"] = "Запрещено использовать на барже!",
                ["BlockedWhileMoving"] = "Запрещено во время движения!",
                ["NotEnoughSpace"] = "Недостаточно места!",

            }, this, "ru");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["ConfigNotFound_Exeption"] = "Barge configuration not found! ({0})",
                ["DataFileNotFound_Exeption"] = "Data file not found! ({0})",
                ["PlayerNotFound_Exeption"] = "Player not found! ({0})",
                ["DataNotFound_Exeption"] = "Data files were not found, or are corrupted. Move the contents of the data folder from the archive to the oxide/data folder on your server!",

                ["GotBarge"] = "You've got a Barge!",

                ["BlockGrade"] = "The building made of stone/HQM is too heavy and cannot float!",
                ["TooHigh"] = "The building is too high. Maximum number of floors - {0}",
                ["OutsideBarge"] = "It is forbidden to build outside the barge",
                ["NotAuthorized"] = "You are not authorized!",
                ["AnchorBarge"] = "Anchor the barge!",

                ["WrongPosition"] = "The barge is in the wrong position. Try anchoring it again!",
                ["WrongPositionOnShole"] = "A barge in shallow water! Building blocked!",

                ["BlockedOnBarge"] = "It is not allowed to use it on a barge!",
                ["BlockedWhileMoving"] = "It is prohibited while moving!",
                ["NotEnoughSpace"] = "Not enough space!",
            }, this);
        }

        private object OnEntityKill(SkyLantern skyLantern)
        {
            if (skyLantern == null || skyLantern.net == null)
                return null;

            Barge barge = Barge.GetBargeByParentEntityNetId(skyLantern.net.ID.Value);
            if (barge == null)
                return null;
		   		 		  						  	   		   					  			 		   					  	 		
            if (!barge.shouldKill)
                return true;

            return null;
        }

        protected override void LoadDefaultConfig()
        {
            _config = PluginConfig.DefaultConfig();
        }

        private class BuildingBlockModelInfo
        {
            public string Prefab;
            public HashSet<int> BadModelStates;
            public int GoodModelState;
        }
        
                
                private bool IsEntityOnBarge(BaseEntity entity)
        {
            if (entity == null || entity.net == null)
                return false;

            return Barge.GetBargeByEntity(entity) != null;
        }

        private static class LootManager
        {
            public static void GiveItemToPLayer(BasePlayer player, ItemConfig itemConfig, int amount)
            {
                Item item = CreateItem(itemConfig, amount);
                if (item == null)
                    return;

                GiveItemToPLayer(player, item);
            }

            private static void GiveItemToPLayer(BasePlayer player, Item item)
            {
                int slots = player.inventory.containerMain.capacity + player.inventory.containerBelt.capacity;
                int taken = player.inventory.containerMain.itemList.Count + player.inventory.containerBelt.itemList.Count;

                if (slots - taken > 0)
                    player.inventory.GiveItem(item);
                else
                    item.Drop(player.transform.position, Vector3.up);
            }

            public static Item CreateItem(ItemConfig itemConfig, int amount)
            {
                Item item = ItemManager.CreateByName(itemConfig.Shortname, amount, itemConfig.Skin);

                if (itemConfig.Name != "")
                    item.name = itemConfig.Name;
		   		 		  						  	   		   					  			 		   					  	 		
                return item;
            }
        }

        protected override void SaveConfig()
        {
            Config.WriteObject(_config);
        }

        private static class BuildManager
        {
            public static BuildingBlock SpawnChildBuildingBlock(BuildingBlockData buildingBlockData, BaseEntity parentEntity)
            {
                BuildingBlock buildingBlock = CreateEntity(buildingBlockData.PrefabName, parentEntity.transform.position, Quaternion.identity, 0, true) as BuildingBlock;
                SetParent(parentEntity, buildingBlock, buildingBlockData.Position.ToVector3(), buildingBlockData.Rotation.ToVector3());
                buildingBlock.AttachToBuilding(BuildingManager.server.NewBuildingID());
                buildingBlock.grounded = true;
                buildingBlock.cachedStability = 1;
                buildingBlock.Spawn();
                BuildingManager.server.decayEntities.Remove(buildingBlock);

                BuildingGrade.Enum buildingGrade = (BuildingGrade.Enum)buildingBlockData.Grade;
                buildingBlock.ChangeGradeAndSkin(buildingGrade, buildingBlockData.Skin);

                if (buildingBlockData.Color != 0)
                    buildingBlock.SetCustomColour(buildingBlockData.Color);

                return buildingBlock;
            }

            public static BaseEntity SpawnRegularEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId = 0, bool enableSaving = false)
            {
                BaseEntity entity = CreateEntity(prefabName, position, rotation, skinId, enableSaving);
                entity.Spawn();
                return entity;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public static BaseEntity SpawnChildEntity(BaseEntity parentEntity, string prefabName, Vector3 localPosition, Vector3 localRotation, ulong skinId = 0, bool isDecor = true, bool enableSaving = false)
            {
                BaseEntity entity = isDecor ? CreateDecorEntity(prefabName, parentEntity.transform.position, Quaternion.identity, skinId) : CreateEntity(prefabName, parentEntity.transform.position, Quaternion.identity, skinId, enableSaving);
                SetParent(parentEntity, entity, localPosition, localRotation);
                DestroyUnnecessaryComponents(entity);
                if (isDecor)
                    DestroyDecorComponents(entity);

                entity.Spawn();
                return entity;
            }

            private static BaseEntity CreateEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId, bool enableSaving)
            {
                BaseEntity entity = GameManager.server.CreateEntity(prefabName, position, rotation);
                entity.enableSaving = enableSaving;
                entity.skinID = skinId;
                return entity;
            }

            private static BaseEntity CreateDecorEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId = 0, bool enableSaving = false)
            {
                BaseEntity entity = CreateEntity(prefabName, position, rotation, skinId, enableSaving);

                BaseEntity trueBaseEntity = entity.gameObject.AddComponent<BaseEntity>();
                CopySerializableFields(entity, trueBaseEntity);
                UnityEngine.Object.DestroyImmediate(entity, true);
                entity.SetFlag(BaseEntity.Flags.Busy, true);
                entity.SetFlag(BaseEntity.Flags.Locked, true);

                return trueBaseEntity;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public static void SetParent(BaseEntity parentEntity, BaseEntity childEntity, Vector3 localPosition, Vector3 localRotation)
            {
                childEntity.transform.localPosition = localPosition;
                childEntity.transform.localEulerAngles = localRotation;
                childEntity.SetParent(parentEntity);
            }

            private static void DestroyDecorComponents(BaseEntity entity)
            {
                Component[] components = entity.GetComponentsInChildren<Component>();

                foreach (Component component in components)
                {
                    EntityCollisionMessage entityCollisionMessage = component as EntityCollisionMessage;

                    if (entityCollisionMessage != null || (component != null && component.name != entity.PrefabName))
                    {
                        Transform transform = component as Transform;
                        if (transform != null)
                            continue;

                        Collider collider = component as Collider;
                        if (collider != null && collider is not MeshCollider)
                            continue;

                        if (component is Model)
                            continue;

                        UnityEngine.Object.DestroyImmediate(component);
                    }
                }
            }

            private static void DestroyUnnecessaryComponents(BaseEntity entity)
            {
                DestroyEntityComponent<GroundWatch>(entity);
                DestroyEntityComponent<DestroyOnGroundMissing>(entity);
                DestroyEntityComponent<TriggerHurtEx>(entity);

                if (entity is BradleyAPC == false)
                    DestroyEntityComponent<Rigidbody>(entity);
            }

            public static void DestroyEntityComponent<TYpeForDestroy>(BaseEntity entity)
            {
                if (entity == null)
                    return;
		   		 		  						  	   		   					  			 		   					  	 		
                TYpeForDestroy component = entity.GetComponent<TYpeForDestroy>();
                if (component != null)
                    UnityEngine.Object.DestroyImmediate(component as UnityEngine.Object);
            }

            public static void DestroyEntityComponents<TYpeForDestroy>(BaseEntity entity)
            {
                if (entity == null)
                    return;
		   		 		  						  	   		   					  			 		   					  	 		
                TYpeForDestroy[] components = entity.GetComponentsInChildren<TYpeForDestroy>();

                for (int i = 0; i < components.Length; i++)
                {
                    TYpeForDestroy component = components[i];

                    if (component != null)
                        UnityEngine.Object.DestroyImmediate(component as UnityEngine.Object);
                }
            }

            public static void CopySerializableFields<T>(T src, T dst)
            {
                FieldInfo[] srcFields = typeof(T).GetFields(BindingFlags.Public | BindingFlags.Instance);
                foreach (FieldInfo field in srcFields)
                {
                    object value = field.GetValue(src);
                    field.SetValue(dst, value);
                }
            }
        }

        private object OnSetupTurret(SamSite samSite)
        {
            if (samSite == null || samSite.net == null)
                return null;

            if (Barge.GetBargeByEntity(samSite) != null)
                return true;

            return null;
        }

        private class BargeCaller : FacepunchBehaviour
        {
            private RoadFlare _roadFlare;
            private BasePlayer _player;
            private BargeConfig _bargeConfig;
            private bool _failed;

            public static void Attach(RoadFlare roadFlare, BasePlayer player, BargeConfig bargeConfig)
            {
                BargeCaller bargeCaller = roadFlare.gameObject.AddComponent<BargeCaller>();
                bargeCaller.Init(roadFlare, player, bargeConfig);
            }

            private void Init(RoadFlare roadFlare, BasePlayer player, BargeConfig bargeConfig)
            {
                _roadFlare = roadFlare;
                _player = player;
                _bargeConfig = bargeConfig;
            }

            private void OnCollisionEnter(Collision collision)
            {
                _failed = true;
                _roadFlare.Kill();
            }

            private void OnDestroy()
            {
                if (_failed || TerrainMeta.HeightMap.GetHeight(this.transform.position) > -10 || IsAnyBlockedEntityNear())
                {
                    LootManager.GiveItemToPLayer(_player, _bargeConfig.ItemConfig, 1);
                    NotifyManager.SendMessageToPlayer(_player, "NotEnoughSpace");
                }
                else
                {
                    CallBarge();
                }
            }

            private bool IsAnyBlockedEntityNear()
            {
                HashSet<Collider> colliders = Physics.OverlapSphere(_roadFlare.transform.position, 20).ToHashSet();

                foreach (Collider collider in colliders)
                {
                    BaseEntity baseEntity = collider.ToBaseEntity();

                    if (baseEntity == null)
                        continue;

                    if (baseEntity is BaseBoat || baseEntity is PercentFullStorageContainer || baseEntity is JunkPileWater)
                        return true;
                }

                return false;
            }

            private void CallBarge()
            {
                Barge barge = Barge.SpawnBarge(_roadFlare.transform.position + Vector3.up * 20, Quaternion.identity, _bargeConfig.PresetName, false);
                if (barge == null)
                    return;

                barge.SetOwner(_player.userID);
            }
        }

        private static bool IsTeam(BasePlayer player, ulong targetId)
        {
            if (player.userID == targetId)
                return true;

            if (player.currentTeam != 0)
            {
                RelationshipManager.PlayerTeam playerTeam = RelationshipManager.ServerInstance.FindTeam(player.currentTeam);

                if (playerTeam == null)
                    return false;

                if (playerTeam.members.Contains(targetId))
                    return true;
            }
            return false;
        }

        private bool TryLoadData()
        {
            foreach (BargeConfig bargeConfig in _config.BargeConfigs)
            {
                if (!TryLoadBargeDataFile(bargeConfig.DataFileName))
                    return false;

                foreach (BargeModuleConfig presetLocationConfig in bargeConfig.Modules)
                    if (!TryLoadModuleDataFile(presetLocationConfig.PresetName))
                        return false;
            }

            _bargeSaveData = LoadDataFile<HashSet<BargeSaveData>>("save");

            if (_bargeSaveData == null)
                _bargeSaveData = new HashSet<BargeSaveData>();

            return true;
        }
        
                private static class BargeSpawner
        {

            private static bool IsPositionSuitable(Vector3 position)
            {
                if (_ins._barges.Any(x => x != null && Vector3.Distance(x.transform.position, position) < 40f))
                    return false;

                HashSet<BaseEntity> entitiesForKill = new HashSet<BaseEntity>();

                foreach (Collider collider in Physics.OverlapSphere(position, 20f))
                {
                    if (collider == null)
                        continue;

                    BaseEntity entity = collider.ToBaseEntity();

                    if (entity == null)
                        continue;
		   		 		  						  	   		   					  			 		   					  	 		
                    if (entity is BaseBoat or BaseSubmarine or CargoShip or BasePlayer)
                        return false;
                    else if (entity is JunkPile or DiveSite)
                        entitiesForKill.Add(entity);
                }

                foreach (BaseEntity entity in entitiesForKill)
                    if (entity.IsExists())
                        entity.Kill();

                return true;
            }

            public static void StopSpawning()
            { 
                _ins._spawnLocations.Clear();

                if (_ins._spawnCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_ins._spawnCoroutine);
            }

            private static IEnumerator SpawnCoroutine()
            {
                while (true)
                {
                    yield return CoroutineEx.waitForSeconds(UnityEngine.Random.Range(_ins._config.SpawnConfig.MinSpawnTime, _ins._config.SpawnConfig.MaxSpawnTime));

                    List<SpawnPositionInfo> suitablePositions = Pool.Get<List<SpawnPositionInfo>>();

                    foreach (SpawnPositionInfo suitablePosition in _ins._spawnLocations)
                        if (IsPositionSuitable(suitablePosition.Position))
                            suitablePositions.Add(suitablePosition);

                    if (suitablePositions.Count > 0)
                    {
                        SpawnPositionInfo spawnPositionInfo = suitablePositions.GetRandom();
                        SpawnBarge(spawnPositionInfo);
                    }

                    Pool.FreeUnmanaged(ref suitablePositions);
                }
            }
            public static void StartPeriodicSpawn()
            {
                if (!_ins._config.SpawnConfig.IsSpawnEnabled)
                    return;

                CacheSuitableMonuments();

                if (_ins._spawnLocations.Count == 0)
                    return;

                _ins._spawnCoroutine = ServerMgr.Instance.StartCoroutine(SpawnCoroutine());
            }

            private static void SpawnBarge(SpawnPositionInfo spawnPositionInfo)
            {
                if (Barge.GetBargePopulation(true) >= _ins._config.SpawnConfig.MaxBargeCount)
                    return;

                string bargePresetName = GetRandomBargePreset();

                if (bargePresetName == null)
                    return;

                Barge.SpawnBarge(spawnPositionInfo.Position, spawnPositionInfo.Rotation, bargePresetName, true);
            }

            private static string GetRandomBargePreset()
            {
                float sumChance = 0;

                foreach (var pair in _ins._config.SpawnConfig.Probabilities)
                    sumChance += pair.Value;

                float random = UnityEngine.Random.Range(0, sumChance);

                foreach (var pair in _ins._config.SpawnConfig.Probabilities)
                {
                    random -= pair.Value;

                    if (random <= 0)
                        return pair.Key;
                }

                return null;
            }

            private static void CacheSuitableMonuments()
            {
                foreach (MonumentInfo monumentInfo in TerrainMeta.Path.Monuments)
                {
                    if (monumentInfo == null || monumentInfo.transform == null)
                        continue;

                    SpawnMonumentConfig spawnMonumentConfig = _ins._config.SpawnConfig.Monuments.FirstOrDefault(x => x.IsEnabled && x.MonumentName == monumentInfo.name);
                    if (spawnMonumentConfig == null)
                        continue;

                    SaveMonumentPositions(monumentInfo, spawnMonumentConfig);
                }
            }

            private static void SaveMonumentPositions(MonumentInfo monumentInfo, SpawnMonumentConfig spawnMonumentConfig)
            {
                foreach (LocationConfig locationConfig in spawnMonumentConfig.Locations)
                {
                    Vector3 localPosition = locationConfig.Position.ToVector3();
                    Vector3 localRotation = locationConfig.Rotation.ToVector3();

                    Vector3 globalPosition = PositionDefiner.GetGlobalPosition(monumentInfo.transform, localPosition);
                    Quaternion globalRotation = PositionDefiner.GetGlobalRotation(monumentInfo.transform, localRotation);

                    SpawnPositionInfo spawnPositionInfo = new SpawnPositionInfo(globalPosition, globalRotation);
                    _ins._spawnLocations.Add(spawnPositionInfo);
                }
            }
        }

        private class GameTipConfig
        {
            [JsonProperty(En ? "Use Facepunch Game Tips (notification bar above hotbar)? [true/false]" : "Использовать ли Facepunch Game Tip (оповещения над слотами быстрого доступа игрока)? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Style (0 - Blue Normal, 1 - Red Normal, 2 - Blue Long, 3 - Blue Short, 4 - Server Event)" : "Стиль (0 - Blue Normal, 1 - Red Normal, 2 - Blue Long, 3 - Blue Short, 4 - Server Event)")] 
            public int Style { get; set; }
        }

        private class CustomTriggerParent : TriggerParent
        {
            public override bool ShouldParent(BaseEntity ent, bool bypassOtherTriggerCheck = false)
            {
                if (ent == null)
                    return false;

                if (ent.ShortPrefabName.Contains("kayak"))
                    return false;

                if (ent is BaseVehicleModule or SkyLantern)
                    return false;

                if (ent is BasePlayer or BaseVehicle or DroppedItemContainer or DroppedItem)
                    return base.ShouldParent(ent, bypassOtherTriggerCheck);

                return false;
            }
        }

        private static class PositionDefiner
        {
            public static Vector3 GetGroundPositionInPoint(Vector3 position)
            {
                position.y = 100;

                if (Physics.Raycast(position, Vector3.down, out RaycastHit raycastHit, 500, 1 << 16 | 1 << 23))
                    position.y = raycastHit.point.y;

                return position;
            }

            public static Vector3 GetGlobalPosition(Transform parentTransform, Vector3 position)
            {
                return parentTransform.transform.TransformPoint(position);
            }

            public static Vector3 GetLocalPosition(Transform parentTransform, Vector3 globalPosition)
            {
                return parentTransform.transform.InverseTransformPoint(globalPosition);
            }

            public static Quaternion GetGlobalRotation(Transform parentTransform, Vector3 rotation)
            {
                return parentTransform.rotation * Quaternion.Euler(rotation);
            }
            private static float GetDistanceToCargoPath(Vector3 position)
            {
                int index = GetNearIndexPathCargo(position);
                int indexNext = TerrainMeta.Path.OceanPatrolFar.Count - 1 == index ? 0 : index + 1;
                int indexPrevious = index == 0 ? TerrainMeta.Path.OceanPatrolFar.Count - 1 : index - 1;
                float distanceNext = GetDistanceToCargoPath(position, index, indexNext);
                float distancePrevious = GetDistanceToCargoPath(position, indexPrevious, index);
                return distanceNext < distancePrevious ? distanceNext : distancePrevious;
            }

            private static int GetNearIndexPathCargo(Vector3 position)
            {
                int index = 0;
                float distance = float.MaxValue;

                for (int i = 0; i < TerrainMeta.Path.OceanPatrolFar.Count; i++)
                {
                    Vector3 vector3 = TerrainMeta.Path.OceanPatrolFar[i];
                    float single = Vector3.Distance(position, vector3);

                    if (single < distance)
                    {
                        index = i;
                        distance = single;
                    }
                }

                return index;
            }

            private static float GetDistanceToCargoPath(Vector3 position, int index1, int index2)
            {
                Vector3 pos1 = TerrainMeta.Path.OceanPatrolFar[index1];
                Vector3 pos2 = TerrainMeta.Path.OceanPatrolFar[index2];

                float distance1 = Vector3.Distance(position, pos1);
                float distance2 = Vector3.Distance(position, pos2);
                float distance12 = Vector3.Distance(pos1, pos2);

                float p = (distance1 + distance2 + distance12) / 2;

                return (2 / distance12) * (float)Math.Sqrt(p * (p - distance1) * (p - distance2) * (p - distance12));
            }
        }

        private object CanPickupEntity(BasePlayer player, SimpleLight simpleLight)
        {
            if (simpleLight == null || simpleLight.net == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(simpleLight);
            if (barge == null)
                return null;

            if (simpleLight.GetParentEntity() is not DoorCloser)
                return null;

            return false;
        }

        private class LocationConfig
        {
            [JsonProperty(En ? "Position" : "Позиция")] 
            public string Position { get; set; }
            
            [JsonProperty(En ? "Rotation" : "Вращение")] 
            public string Rotation { get; set; }
        }

        private void CheckIfOtherPluginSpawnedEntity(BaseEntity baseEntity)
        {
            if (baseEntity == null || baseEntity.HasParent())
                return;
            
            if (baseEntity is StorageContainer or Fridge && !baseEntity.enableSaving)
                return;
            
            Barge barge = Barge.GetBargeByCollider(baseEntity);
            if (barge == null)
                return;
            
            if (!Barge.IsEntityShouldParent(baseEntity) || Barge.IsEntityShoutParentToTargetEntity(baseEntity))
                return;
            
            baseEntity.SetParent(barge.mainEntity, true);
        }

        private class MainConfig
        {
            [JsonProperty(En ? "The distance outside the grid on the map that a barge can travel (-1 is not limited)" : "Расстояние за пределами сетки на карте, на которое может уплыть баржа (-1 не ограничивать)")] 
            public float MaxShoreDistance { get; set; }
            
            [JsonProperty(En ? "Prohibit barges from approaching fishing villages [true/false]" : "Запретить баржам приближаться к рыбацким деревням [true/false]")] 
            public bool BlockFishingVillage { get; set; }
            
            [JsonProperty(En ? "Items whose installation is prohibited on the barge" : "Предметы, установка которых запрещена на барже")] 
            public HashSet<string> BlockedItemShortnames { get; set; }
            
            [JsonProperty(En ? "Commands that are prohibited on the barge" : "Список команд, которые запрещены на барже")] 
            public HashSet<string> BlockedCommands { get; set; }
        }

        private object CanEntityTakeDamage(PressButton button, HitInfo info)
        {
            if (button == null || info == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(button);
            if (barge == null)
                return null;

            BaseEntity parentEntity = button.GetParentEntity();
            if (parentEntity == null)
                return null;

            BaseModule baseModule = parentEntity.GetComponentInChildren<BaseModule>();
            if (baseModule == null)
                return null;

            return false;
        }

        private class DecayManager : FacepunchBehaviour
        {
            private BaseEntity _parentEntity;
            private Coroutine _decayCoroutine;

            public static DecayManager AttachDecayManager(BaseEntity parentEntity)
            {
                GameObject triggerGameObject = new GameObject("DecayManager");
                triggerGameObject.transform.SetParent(parentEntity.transform, false);
                DecayManager decayManager = triggerGameObject.AddComponent<DecayManager>();
                decayManager.Init(parentEntity);
                return decayManager;
            }

            private void Init(BaseEntity parentEntity)
            {
                this._parentEntity = parentEntity;
                _decayCoroutine = ServerMgr.Instance.StartCoroutine(DecayCoroutine());
            }
		   		 		  						  	   		   					  			 		   					  	 		
            private IEnumerator DecayCoroutine()
            {
                while (true)
                {
                    for (int i = 0; i < _parentEntity.children.Count; i++)
                    {
                        BaseEntity baseEntity = _parentEntity.children[i];

                        DecayEntity decayEntity = baseEntity as DecayEntity;
                        if (decayEntity != null)
                            OnDecay(decayEntity);
                    }

                    yield return CoroutineEx.waitForSeconds(600f);
                }
            }

            public void OnDecay(DecayEntity decayEntity)
            {
                BuildingBlock buildingBlock = decayEntity as BuildingBlock;
		   		 		  						  	   		   					  			 		   					  	 		
                if (buildingBlock != null && buildingBlock.grounded)
                    return;

                if (decayEntity.decay == null || ConVar.Decay.scale == 0)
                    return;

                float timeScienceLastDecay = Time.time - decayEntity.lastDecayTick;
                decayEntity.lastDecayTick = Time.time;
		   		 		  						  	   		   					  			 		   					  	 		
                if (!decayEntity.decay.ShouldDecay(decayEntity))
                    return;

                float single = timeScienceLastDecay * ConVar.Decay.scale;

                if (ConVar.Decay.upkeep)
                {
                    decayEntity.upkeepTimer += single;

                    if (decayEntity.upkeepTimer > 0f)
                    {
                        BuildingPrivlidge buildingPrivilege = decayEntity.GetBuildingPrivilege();

                        if (buildingPrivilege != null)
                            decayEntity.upkeepTimer -= buildingPrivilege.PurchaseUpkeepTime(decayEntity, Mathf.Max(decayEntity.upkeepTimer, 600f));
                    }

                    if (decayEntity.upkeepTimer < 1f)
                    {
                        if (decayEntity.healthFraction < 1f && decayEntity.GetEntityHealScale() > 0f && decayEntity.SecondsSinceAttacked > 600f)
                        {
                            if (Interface.CallHook("OnDecayHeal", this) != null)
                                return;

                            float single1 = timeScienceLastDecay / decayEntity.GetEntityDecayDuration() * decayEntity.GetEntityHealScale();
                            decayEntity.Heal(decayEntity.MaxHealth() * single1);
                        }

                        return;
                    }

                    decayEntity.upkeepTimer = 1f;
                }

                decayEntity.decayTimer += single;
		   		 		  						  	   		   					  			 		   					  	 		
                if (decayEntity.decayTimer < decayEntity.GetEntityDecayDelay())
                    return;

                using (TimeWarning.New("DecayTick"))
                {
                    float upkeepInsideDecayScale = 1f;
                    if (!ConVar.Decay.upkeep)
                    {
                        for (int i = 0; i < decayEntity.decayPoints.Length; i++)
                        {
                            DecayPoint decayPoint = decayEntity.decayPoints[i];

                            if (decayPoint.IsOccupied(decayEntity))
                                upkeepInsideDecayScale -= decayPoint.protection;
                        }
                    }
                    else if (!decayEntity.BypassInsideDecayMultiplier && !decayEntity.IsOutside())
                    {
                        upkeepInsideDecayScale *= ConVar.Decay.upkeep_inside_decay_scale;
                    }

                    if (Interface.CallHook("OnDecayDamage", this) != null)
                        return;

                    if (upkeepInsideDecayScale > 0f)
                    {
                        float entityDecayDuration = single / decayEntity.GetEntityDecayDuration() * decayEntity.MaxHealth();
                        decayEntity.Hurt(entityDecayDuration * upkeepInsideDecayScale * decayEntity.decayVariance, DamageType.Decay);
                    }
                }
            }

            public void DestroyUpdater()
            {
                StopCoroutines();
                Destroy(gameObject);
            }
		   		 		  						  	   		   					  			 		   					  	 		
            private void StopCoroutines()
            {
                if (_decayCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_decayCoroutine);
            }
        }
        private readonly Dictionary<string, CustomPrefabData> _moduleCustomizations = new Dictionary<string, CustomPrefabData>();

        private void OnConstructionPlace(BaseCombatEntity entity, Construction component, Construction.Target constructionTarget, BasePlayer player)
        {
            if (entity == null || constructionTarget.entity == null || player == null)
                return;

            BaseEntity parentEntity = constructionTarget.entity.GetParentEntity();
            if (parentEntity == null || parentEntity.net == null)
                return;

            Barge barge = Barge.GetBargeByParentEntityNetId(parentEntity.net.ID.Value);
            if (barge == null)
                return;

            barge.OnPlayerBuild(player, entity, constructionTarget.entity);
        }

        private void OnEntitySpawned(BasePortal basePortal)
        {
            CheckIfOtherPluginSpawnedEntity(basePortal);
        }

        [ChatCommand("killallbarges")]
        private void ChatKillAllCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            Barge.KillAllBarges();
        }
        private readonly HashSet<string> _subscribeMethods = new HashSet<string>
        {
            "OnButtonPress",
            "CanBuild",
            "CanPickupEntity",
            "CanChangeGrade",
            "OnStructureRotate",
            "OnStructureUpgraded",
            "OnConstructionPlace",
            "OnPoweredLightsPointAdd",
            "OnEntityStabilityCheck",
            "OnEntityKill",
            "OnEntitySpawned",
            "CanMountEntity",
            "OnEntityMounted",
            "OnHammerHit",
            "OnWireClear",
            "OnWireConnect",
            "OnExplosiveThrown",
            "OnEntityTakeDamage",
            "CanLootEntity",
            "CanEntityTakeDamage",
            "OnSetupTurret"
        };

        private class CollisionDisabler : FacepunchBehaviour
        {
            private Barge _barge;
            private readonly HashSet<Collider> _colliders = new HashSet<Collider>();

            public static void AttachCollisionDisabler(BaseEntity baseEntity, Barge barge)
            {
                CollisionDisabler collisionDisabler = baseEntity.gameObject.AddComponent<CollisionDisabler>();
                collisionDisabler._barge = barge;

                foreach (Collider collider in baseEntity.GetComponentsInChildren<Collider>())
                    if (collider != null)
                        collisionDisabler._colliders.Add(collider);
            }

            private void OnCollisionEnter(Collision collision)
            {
                if (collision == null || _barge == null || collision.collider == null)
                    return;

                BaseEntity baseEntity = collision.GetEntity();
                if (baseEntity == null)
                    return;

                if (_barge.IsBargeEntity(baseEntity))
                    IgnoreCollider(collision.collider);
            }

            private void IgnoreCollider(Collider otherCollider)
            {
                foreach (Collider collider in _colliders)
                    if (collider != null)
                        Physics.IgnoreCollision(collider, otherCollider);
            }
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private class BuildingBlockData : EntityData
        {
            [JsonProperty("Grade [0 - 4]", Order = 102)] 
            public int Grade { get; set; }
            
            [JsonProperty("Color", Order = 103)] 
            public uint Color { get; set; }
        }

        private  object OnEntityTakeDamage(SkyLantern skyLantern, HitInfo info)
        {
            if (skyLantern == null || info == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(skyLantern);
            if (barge == null)
                return null;
		   		 		  						  	   		   					  			 		   					  	 		
            return true;
        }

        private object CanPickupEntity(BasePlayer player, BaseEntity entity)
        {
            if (player == null || entity == null)
                return null;
		   		 		  						  	   		   					  			 		   					  	 		
            Barge barge = Barge.GetBargeByEntity(entity);
            if (barge == null)
            {
                BuildingPrivlidge buildingPrivilege = player.GetBuildingPrivilege();
		   		 		  						  	   		   					  			 		   					  	 		
                if (buildingPrivilege != null && Barge.GetBargeByEntity(buildingPrivilege) != null)
                {
                    BuildingPrivlidge entityBuildingPrivilege = entity.GetBuildingPrivilege();

                    if (entityBuildingPrivilege != null && !entityBuildingPrivilege.IsAuthed(player))
                        return false;
                }

                return null;
            }

            if (!barge.IsPlayerCanInteract(player, false))
                return false;

            return null;
        }

        private class SpawnConfig
        {
            [JsonProperty(En ? "Time the barge is assigned to the player after being summoned [sec]" : "Время, на которое баржа закрепляется за игроком после призыва [sec]")] 
            public float AssignedTime { get; set; }
            
            [JsonProperty(En ? "Turn on the spawn of barges on the map? [true/false]" : "Включить спавн барж на карте? [true/false]")] 
            public bool IsSpawnEnabled { get; set; }
            
            [JsonProperty(En ? "Maximum number of automatically spawned barges" : "Максимальное количество автоматически заспавненных барж на сервере")] 
            public int MaxBargeCount { get; set; }
            
            [JsonProperty(En ? "Minimum time between the spawning of barges" : "Минимальное время между спавном барж [sec]")] 
            public int MinSpawnTime { get; set; }
            
            [JsonProperty(En ? "Maximum time between the spawning of barges" : "Maксимальное время между спавном барж [sec]")] 
            public int MaxSpawnTime { get; set; }
            
            [JsonProperty(En ? "Barge Preset - probability" : "Пресет баржи - вероятность")] 
            public Dictionary<string, float> Probabilities { get; set; }
            
            [JsonProperty(En ? "List of monuments for spawn" : "Список монументов для спавна")] 
            public HashSet<SpawnMonumentConfig> Monuments { get; set; }
        }

        private class StorageItemsInstaller : FacepunchBehaviour
        {
            private BasePlayer _player;
            private Barge _barge;
            private BaseEntity _fakeContainer;
            private BaseEntity _targetContainer;
            private Item _placeItem;

            public static void TryAttachController(BasePlayer player, Item item)
            {
                StorageItemsInstaller adaptorInstallController = _ins._adaptorControllers.FirstOrDefault(x => x._player != null && x._player.userID == player.userID);
                if (adaptorInstallController != null)
                    return;

                Barge barge = Barge.GetBargeByCollider(player);
                if (barge == null || !barge.IsStopped())
                    return;

                adaptorInstallController = player.gameObject.AddComponent<StorageItemsInstaller>();
                adaptorInstallController.Init(player, barge, item);
                _ins._adaptorControllers.Add(adaptorInstallController);
            }

            private void Init(BasePlayer player, Barge barge, Item item)
            {
                _player = player;
                _barge = barge;
                _placeItem = item;
            }

            private void FixedUpdate()
            {
                if (_player == null || _player.IsSleeping() || !_player.IsConnected || _player.IsWounded() || !_barge.IsStopped())
                {
                    DestroyController();
                    return;
                }

                Item activeItem = _player.GetActiveItem();
                if (activeItem == null || _placeItem == null || activeItem.uid != _placeItem.uid)
                {
                    DestroyController();
                    return;
                }

                BaseEntity entity = _ins.RaycastAll<BaseEntity>(_player.eyes.HeadRay(), 5);
                if (entity == null || (entity is not StorageContainer && entity is not Fridge))
                {
                    TryTransferAdapters();
                    return;
                }

                if (!entity.HasParent())
                    return;

                if (entity != _targetContainer)
                {
                    if (_fakeContainer.IsExists())
                        _fakeContainer.Kill();
                    
                    TryTransferAdapters();
                    _targetContainer = entity;
                    _fakeContainer = BuildManager.SpawnRegularEntity(entity.PrefabName, entity.transform.position, entity.transform.rotation, entity.skinID, enableSaving: false);
                }
            }

            private void TryTransferAdapters()
            {
                if (!_fakeContainer.IsExists() || !_targetContainer.IsExists() || _targetContainer.transform.position != _fakeContainer.transform.position)
                    return;

                foreach (BaseEntity entity in _fakeContainer.children.ToHashSet())
                    if (entity != null && entity is IndustrialStorageAdaptor or Hopper)
                        entity.SetParent(_targetContainer);

                _fakeContainer.Kill();
                _fakeContainer = null;
                _targetContainer = null;
            }

            public static void Unload()
            {
                foreach (StorageItemsInstaller adaptorInstallController in _ins._adaptorControllers)
                    if (adaptorInstallController != null)
                        adaptorInstallController.DestroyController();
            }

            private void DestroyController()
            {
                TryTransferAdapters();

                if (_fakeContainer.IsExists())
                    _fakeContainer.Kill();

                Destroy(this);
            }
        }

        private object CanMountEntity(BasePlayer player, BaseMountable baseMountable)
        {
            if (player == null || baseMountable == null)
                return null;

            if ((baseMountable is BaseVehicleSeat && baseMountable.ShortPrefabName != "arcadeuser") || baseMountable is MovableBaseMountable)
                return null;

            Barge barge = Barge.GetBargeByEntity(baseMountable);
            if (barge == null)
            {
                BaseEntity parentEntity = baseMountable.VehicleParent();
		   		 		  						  	   		   					  			 		   					  	 		
                if (parentEntity == null)
                    return null;

                barge = Barge.GetBargeByEntity(parentEntity);
            }
		   		 		  						  	   		   					  			 		   					  	 		
            if (barge == null)
                return null;

            if (!barge.IsStopped())
            {
                NotifyManager.SendMessageToPlayer(player, "BlockedWhileMoving");
                return true;
            }

            return null;
        }

        private class EntityData
        {
            [JsonProperty("Prefab")] 
            public string PrefabName { get; set; }
            
            [JsonProperty("Skin")] 
            public ulong Skin { get; set; }
            
            [JsonProperty("Position", Order = 100)] 
            public string Position { get; set; }
            
            [JsonProperty("Rotation", Order = 101)] 
            public string Rotation { get; set; }
        }

        private object OnWireConnect(BasePlayer player, IOEntity entity1, int inputs, IOEntity entity2, int outputs)
        {
            if (entity1 == null || entity2 == null)
                return null;
            
            Barge barge1 = Barge.GetBargeByEntity(entity1);
            Barge barge2 = Barge.GetBargeByEntity(entity2);
            
            if (barge1 != barge2)
                return true;
            
            if (barge1 == null)
                return null;
            
            if (!barge1.IsStopped())
            {
                NotifyManager.SendMessageToPlayer(player, "BlockedWhileMoving");
                return true;
            }
            
            return null;
        }

        private void Unsubscribes()
        {
            foreach (string hook in _subscribeMethods)
                Unsubscribe(hook);
        }

        private class Barge : FacepunchBehaviour
        {
            public BargeConfig BargeConfig;
            public BaseEntity mainEntity;
            private BargePhysics _physics;
            private BuildingVisibilityManager _buildingVisibilityUpdater;
            private CustomTriggerParent _triggerParent;
            private DecayManager _decayManager;
            private MarkerController _markerController;
            private BuildingPrivlidge _buildingPrivilege;
            private readonly HashSet<BaseModule> _modules = new HashSet<BaseModule>();
            public bool shouldUpdateStability;
            public bool shouldKill;
            public bool shouldAnchor;
            private bool _isServerSpawn;
            private ulong _ownerID;
            private float _lastMovingTime;
            private float _summonTime;
            private Vector3 _colliderSize;
            private Vector3 _bargeSize;
            private Coroutine _bargeUpdateCoroutine;
            
            public static void SaveBarges(bool isUnloading = false)
            {
                if (isUnloading && _ins._bargeSaveData.Count == 0)
                    return;

                _ins._bargeSaveData.Clear();

                foreach (Barge barge in _ins._barges)
                {
                    if (barge == null || !barge.mainEntity.IsExists() || barge.mainEntity.net == null)
                        continue;
                    int fuelAmount = 0;
                    CabinModule cabinModule = barge._modules.FirstOrDefault(x => x != null && x is CabinModule) as CabinModule;
                    if (cabinModule != null)
                    {
                        fuelAmount = cabinModule.CalculateFuelAmount();
                    }

                    BargeSaveData bargeSaveData = new BargeSaveData
                    {
                        NetId = barge.mainEntity.net.ID.Value,
                        BargePreset = barge.BargeConfig.PresetName,
                        IsServerSpawn = barge._isServerSpawn,
                        FuelAmount = fuelAmount,
                    };

                    _ins._bargeSaveData.Add(bargeSaveData);
                }

                SaveDataFile(_ins._bargeSaveData, "save");
            }

            public static void LoadBarges()
            {
                HashSet<SkyLantern> entities = BaseNetworkable.serverEntities.OfType<SkyLantern>();

                foreach (SkyLantern entity in entities)
                {
                    if (entity == null || entity.net == null)
                        continue;

                    BargeSaveData bargeSaveData = _ins._bargeSaveData.FirstOrDefault(x => x.NetId == entity.net.ID.Value);

                    if (bargeSaveData == null)
                    {
                        if (entity.skinID == 0)
                            continue;

                        BargeConfig bargeConfig = _ins._config.BargeConfigs.FirstOrDefault(x => x.ItemConfig.Skin == entity.skinID);
                        if (bargeConfig != null)
                        {
                            Barge barge1 = TryAttachBargeClass(entity, bargeConfig.PresetName, false, false);

                            if (barge1 != null)
                                barge1._buildingVisibilityUpdater.OnStopMoving();
                        }

                        continue;
                    }

                    Barge barge = TryAttachBargeClass(entity, bargeSaveData.BargePreset, false, bargeSaveData.IsServerSpawn);

                    if (barge != null)
                        barge._buildingVisibilityUpdater.OnStopMoving();
                }
            }

            public static void UnloadBarges()
            {
                foreach (Barge barge in _ins._barges)
                    if (barge != null)
                        barge.UnloadBarge();
            }

            public static int GetBargePopulation(bool onlyServerSpawn)
            {
                _ins._barges.RemoveWhere(x => x == null);

                if (onlyServerSpawn)
                    return _ins._barges.Where(x => x._isServerSpawn).Count;

                return _ins._barges.Count;
            }

            public static void KillAllBarges()
            {
                foreach (Barge barge in _ins._barges)
                    if (barge != null && barge.mainEntity.IsExists())
                        barge.KillBarge();

                SaveBarges();
            }

            public static Barge GetBargeByParentEntityNetId(ulong netId)
            {
                return _ins._barges.FirstOrDefault(x => x != null && x.mainEntity.IsExists() && x.mainEntity.net != null && x.mainEntity.net.ID.Value == netId);
            }

            public static Barge GetBargeByEntity(BaseEntity entity)
            {
                if (entity.ShortPrefabName is "electricfurnace.io" or "poweredwaterpurifier.storage" or "weaponracklight" || entity is IndustrialStorageAdaptor or IndustrialCrafter or Door or CustomDoorManipulator)
                {
                    BaseEntity parentEntity = entity.GetParentEntity();

                    if (parentEntity != null && parentEntity.ShortPrefabName == "hab_storage")
                    {
                        BaseEntity parentPlanter = parentEntity.GetParentEntity();
                        if (parentPlanter != null && parentPlanter is PlanterBox)
                        {
                            return GetBargeByEntity(parentPlanter);
                        }
                    }

                    if (parentEntity is Door)
                        return GetBargeByEntity(parentEntity);
                    if (parentEntity != null)
                        entity = parentEntity;
                }

                return _ins._barges.FirstOrDefault(x => x != null && x.mainEntity.IsExists() && x.IsBargeEntity(entity));
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public static Barge GetBargeByCollider(BaseEntity entity)
            {
                int allowedTopologies = (int)(TerrainTopology.Enum.Ocean | TerrainTopology.Enum.Oceanside);
                TerrainMeta.TopologyMap.GetTopology(entity.transform.position);
                if ((TerrainMeta.TopologyMap.GetTopology(entity.transform.position) & allowedTopologies) == 0)
                    return null;

                return _ins._barges.FirstOrDefault(x => x != null && x.IsInsideBargeCollider(entity));
            }

            public static Barge GetBargeByPhysicsEntityNetId(ulong netId)
            {
                return _ins._barges.FirstOrDefault(x => x != null && x._physics.IsMyKayak(netId));
            }

            public static Barge SpawnBarge(Vector3 position, Quaternion rotation, string presetName, bool isServerSpawn)
            {
                BargeConfig bargeConfig = _ins._config.BargeConfigs.FirstOrDefault(x => x.PresetName == presetName);
                if (bargeConfig == null)
                {
                    NotifyManager.PrintError(null, "ConfigNotFound_Exeption", presetName);
                    return null;
                }

                BaseEntity mainEntity = BuildManager.SpawnRegularEntity("assets/prefabs/misc/chinesenewyear/sky_lantern/skylantern.deployed.prefab", position, rotation, bargeConfig.ItemConfig.Skin, true);
                Barge barge = TryAttachBargeClass(mainEntity, presetName, true, isServerSpawn);

                if (barge == null)
                    mainEntity.Kill();

                SaveBarges();
                return barge;
            }

            private static Barge TryAttachBargeClass(BaseEntity mainEntity, string presetName, bool firstSpawn, bool isServerSpawn)
            {
                BargeConfig bargeConfig = _ins._config.BargeConfigs.FirstOrDefault(x => x.PresetName == presetName);

                if (bargeConfig == null)
                {
                    NotifyManager.PrintError(null, "ConfigNotFound_Exeption", presetName);
                    return null;
                }

                _ins._bargeCustomizations.TryGetValue(bargeConfig.DataFileName, out BargeCustomizeData bargeData);

                if (bargeData == null)
                {
                    NotifyManager.PrintError(null, "DataFileNotFound_Exeption", bargeConfig.DataFileName);
                    return null;
                }

                Barge barge = mainEntity.gameObject.AddComponent<Barge>();
                _ins._barges.Add(barge);
                barge.BuildBarge(mainEntity, bargeConfig, bargeData, firstSpawn, isServerSpawn);
                return barge;
            }

            private void BuildBarge(BaseEntity parentEntity, BargeConfig bargeConfig, BargeCustomizeData bargeData, bool firstSpawn, bool isServerSpawn)
            {
                this.mainEntity = parentEntity;
                this.BargeConfig = bargeConfig;
                this._isServerSpawn = isServerSpawn;

                UpdateMainEntity();

                HashSet<BoxColliderInfo> boxColliderInfos = new HashSet<BoxColliderInfo>();

                foreach (BoxColliderData boxColliderData in bargeData.BoxCollidersData)
                {
                    BoxColliderInfo boxColliderInfo = new BoxColliderInfo(boxColliderData);
                    boxColliderInfos.Add(boxColliderInfo);
                }

                SpawnPrefab(bargeData, parentEntity, firstSpawn);

                HashSet<BargeModuleConfig> enabledModules = bargeConfig.Modules.Where(x => x.IsEnable);
                foreach (DoorCloser doorCloser in mainEntity.children.OfType<DoorCloser>())
                {
                    if (!doorCloser.IsExists())
                        continue;

                    if (enabledModules.Count == 0)
                    {
                        doorCloser.Kill();
                        continue;
                    }
                    BargeModuleConfig bargeModuleConfig = enabledModules.Min(x => Vector3.Distance(doorCloser.transform.localPosition, x.Position.ToVector3()));

                    if (bargeModuleConfig == null || Vector3.Distance(doorCloser.transform.localPosition, bargeModuleConfig.Position.ToVector3()) > 0.1f)
                        doorCloser.Kill();
                }

                foreach (BargeModuleConfig presetLocationConfig in bargeConfig.Modules)
                    SpawnModule(presetLocationConfig, firstSpawn, ref boxColliderInfos);

                ConnectorModule connectorModule = _modules.FirstOrDefault(x => x is ConnectorModule) as ConnectorModule;

                _physics = this.gameObject.AddComponent<BargePhysics>();
                _physics.Init(this, bargeData, boxColliderInfos, connectorModule);

                _colliderSize = bargeData.Size.ToVector3();

                HashSet<BaseEntity> surroundingTanks = parentEntity.children.Where(x => x != null && x.ShortPrefabName == "coaling_tower_fuel_storage.entity");
                if (surroundingTanks.Count > 0)
                {
                    BaseEntity maxXEntity = surroundingTanks.Max(x => Math.Abs(x.transform.localPosition.x));
                    BaseEntity maxZEntity = surroundingTanks.Max(x => Math.Abs(x.transform.localPosition.z));

                    if (maxXEntity != null && maxZEntity != null)
                        _bargeSize = new Vector3(Math.Abs(maxXEntity.transform.localPosition.x), 0, Math.Abs(maxZEntity.transform.localPosition.z));
                }

                _bargeUpdateCoroutine = ServerMgr.Instance.StartCoroutine(BargeUpdateCoroutine());
                _buildingVisibilityUpdater = BuildingVisibilityManager.AttachVisibilityUpdater(parentEntity);
                _decayManager = DecayManager.AttachDecayManager(parentEntity);
                mainEntity.SetFlag(BaseEntity.Flags.Broken, true);

                if (!firstSpawn)
                {
                    _buildingPrivilege = parentEntity.children.FirstOrDefault(x => x.IsExists() && x is BuildingPrivlidge) as BuildingPrivlidge;
                }

                if (_buildingPrivilege == null && (isServerSpawn || !_ins._config.MarkerConfig.OnlyForServerBarges))
                {
                    _markerController = MarkerController.CreateMarker(this);
                }

                if (firstSpawn)
                    StartMoving();
            }

            private void UpdateMainEntity()
            {
                BuildManager.DestroyEntityComponent<GroundWatch>(mainEntity);
                mainEntity.enableSaving = true;
                mainEntity.skinID = BargeConfig.ItemConfig.Skin;

                SkyLantern skyLantern = mainEntity as SkyLantern;
                if (skyLantern != null)
                {
                    skyLantern.CancelInvoke(skyLantern.StartSinking);
                    skyLantern.CancelInvoke(skyLantern.SelfDestroy);
                }
            }

            private void SpawnModule(BargeModuleConfig presetLocationConfig, bool firstSpawn, ref HashSet<BoxColliderInfo> boxColliderInfos)
            {
                if (!presetLocationConfig.IsEnable)
                    return;

                if (!_ins._moduleCustomizations.TryGetValue(presetLocationConfig.PresetName, out CustomPrefabData customPrefabData))
                {
                    NotifyManager.PrintError(null, "DataFileNotFound_Exeption", presetLocationConfig.PresetName);
                    return;
                }

                BaseEntity parentEntity = mainEntity.children.FirstOrDefault(x => x != null && x.PrefabName == "assets/prefabs/misc/doorcloser/doorcloser.prefab" && Vector3.Distance(x.transform.localPosition, presetLocationConfig.Position.ToVector3()) < 0.1f);
                if (parentEntity == null)
                {
                    parentEntity = BuildManager.SpawnChildEntity(mainEntity, "assets/prefabs/misc/doorcloser/doorcloser.prefab", presetLocationConfig.Position.ToVector3(), presetLocationConfig.Rotation.ToVector3(), 0, false, true);
                }
                parentEntity.enableSaving = true;

                HelicopterDebris helicopterDebris = parentEntity as HelicopterDebris;
                if (helicopterDebris != null)
                {
                    helicopterDebris.CancelInvoke(helicopterDebris.RemoveMe);
                }
		   		 		  						  	   		   					  			 		   					  	 		
                BaseModule baseModule = null;

                foreach (BoxColliderData boxColliderData in customPrefabData.BoxCollidersData)
                {
                    Vector3 relativeLocalPosition = boxColliderData.LocalPosition.ToVector3();
                    Vector3 relativeLocalRotation = boxColliderData.LocalRotation.ToVector3();

                    Vector3 globalPosition = parentEntity.transform.TransformPoint(relativeLocalPosition);
                    Quaternion globalRotation = parentEntity.transform.rotation * Quaternion.Euler(relativeLocalRotation);

                    Vector3 localPosition = mainEntity.transform.InverseTransformPoint(globalPosition);
                    Vector3 localRotation = (Quaternion.Inverse(mainEntity.transform.rotation) * globalRotation).eulerAngles;
		   		 		  						  	   		   					  			 		   					  	 		
                    BoxColliderInfo boxColliderInfo = new BoxColliderInfo(localPosition, localRotation, boxColliderData.Size.ToVector3());
                    boxColliderInfos.Add(boxColliderInfo);
                }

                SpawnPrefab(customPrefabData, parentEntity, firstSpawn);

                if (presetLocationConfig.PresetName.Contains("connector") && !_modules.Any(x => x is ConnectorModule))
                    baseModule = ConnectorModule.SpawnConnectorModule(parentEntity, this, customPrefabData);
                else if (presetLocationConfig.PresetName.Contains("ramp"))
                    baseModule = RampModule.SpawnRampModule(parentEntity, this, customPrefabData);
                else if (presetLocationConfig.PresetName.Contains("cabine"))
                    baseModule = CabinModule.SpawnCabinModule(parentEntity, this, customPrefabData);
                else if (presetLocationConfig.PresetName.Contains("anchor"))
                    baseModule = AnchorModule.SpawnAnchorModule(parentEntity, this, customPrefabData);
                else if (presetLocationConfig.PresetName.Contains("dock"))
                    baseModule = DockModule.SpawnDockModule(parentEntity, this, customPrefabData);

                if (baseModule != null)
                    _modules.Add(baseModule);
                else if (parentEntity.IsExists())
                    parentEntity.Kill();
            }

            private void SpawnPrefab(CustomPrefabData customPrefabData, BaseEntity parentEntity, bool firstSpawn)
            {
                foreach (EntityData decorEntityData in customPrefabData.DecorEntities)
                {
                    Vector3 localPosition = decorEntityData.Position.ToVector3();
                    Vector3 localRotation = decorEntityData.Rotation.ToVector3();

                    if (!firstSpawn && parentEntity.children.Any(x => x != null && x.PrefabName == decorEntityData.PrefabName && x.transform.localPosition == localPosition))
                        continue;

                    BaseEntity entity = BuildManager.SpawnChildEntity(parentEntity, decorEntityData.PrefabName, localPosition, localRotation, isDecor: true, enableSaving: true);
                    if (entity.ShortPrefabName == "coaling_tower_fuel_storage.entity" || entity.ShortPrefabName == "mailbox.deployed" || entity.ShortPrefabName == "bbq.deployed" || entity.ShortPrefabName == "hazmat_youtooz.deployed" || entity.ShortPrefabName.Contains("innertube"))
                        entity.SetFlag(BaseEntity.Flags.Busy, true);
                }

                foreach (BuildingBlockData buildingBlockData in customPrefabData.BuildingBlocks)
                {
                    Vector3 localPosition = buildingBlockData.Position.ToVector3();
                    Vector3 localRotation = buildingBlockData.Rotation.ToVector3();

                    BuildingBlock thisBuildingBlock = parentEntity.children.FirstOrDefault(x => x != null && x.PrefabName == buildingBlockData.PrefabName && x.transform.localPosition == localPosition && x.transform.localEulerAngles == localRotation) as BuildingBlock;
                    if (thisBuildingBlock != null)
                    {
                        thisBuildingBlock.grounded = true;
                        continue;
                    }

                    BuildingBlock buildingBlock = BuildManager.SpawnChildBuildingBlock(buildingBlockData, parentEntity);
                    buildingBlock.grounded = true;
                }

                foreach (EntityData regularEntityData in customPrefabData.RegularEntities)
                {
                    Vector3 localPosition = regularEntityData.Position.ToVector3();
                    Vector3 localRotation = regularEntityData.Rotation.ToVector3();

                    if (!firstSpawn && parentEntity.children.Any(x => x != null && x.PrefabName == regularEntityData.PrefabName && x.transform.localPosition == localPosition))
                        continue;

                    BaseEntity baseEntity = BuildManager.SpawnChildEntity(parentEntity, regularEntityData.PrefabName, localPosition, localRotation, isDecor: false, enableSaving: true);
                    if (baseEntity is PercentFullStorageContainer)
                        baseEntity.SetFlag(BaseEntity.Flags.Busy, true);
                }
            }

            public bool IsStopped()
            {
                return !_physics.IsPhysicsEnable();
            }

            public bool IsBargeEntity(BaseEntity entity)
            {
                if (entity == mainEntity)
                    return true;

                BaseEntity parentEntity = entity.GetParentEntity();

                if (parentEntity == null || parentEntity.net == null)
                    return false;

                if (parentEntity.net.ID.Value == mainEntity.net.ID.Value)
                    return true;

                if (_modules.Any(x => x != null && x.IsModuleEntity(entity)))
                    return true;

                return false;
            }

            public void SetOwner(ulong ownerID)
            {
                _ownerID = ownerID;
                _summonTime = Time.realtimeSinceStartup;
            }

            public bool IsPlayerCanInteract(BasePlayer player, bool sendMessage)
            {
                if (_buildingPrivilege.IsExists() && !_buildingPrivilege.IsAuthed(player))
                {
                    if (sendMessage)
                        NotifyManager.SendMessageToPlayer(player, "NotAuthorized");

                    return false;
                }

                if (_ins._config.SpawnConfig.AssignedTime > 0 && _ownerID != 0 && _summonTime > 0)
                {
                    if (player.userID != _ownerID && !IsTeam(player, _ownerID))
                    {
                        float currentTime = Time.realtimeSinceStartup;
                        if (currentTime - _summonTime < _ins._config.SpawnConfig.AssignedTime)
                        {
                            if (sendMessage)
                                NotifyManager.SendMessageToPlayer(player, "NotAuthorized");

                            return false;
                        }
                    }
                }

                return true;
            }

            public bool IsPlayerCanBuild(BasePlayer player, bool sendMessage)
            {
                if (!IsStopped())
                {
                    if (sendMessage)
                        NotifyManager.SendMessageToPlayer(player, "AnchorBarge");

                    return false;
                }

                Vector3 bargeRotation = mainEntity.transform.rotation.eulerAngles;

                if (bargeRotation.x != 0 || bargeRotation.z != 0)
                {
                    if (sendMessage)
                    {
                        if (IsBargeOnShoal())
                            NotifyManager.SendMessageToPlayer(player, "WrongPositionOnShole");
                        else
                            NotifyManager.SendMessageToPlayer(player, "WrongPosition");
                    }

                    return false;
                }

                return true;
            }

            public static bool IsBasicBuildingBlock(BuildingBlock buildingBlock)
            {
                return buildingBlock.grounded && buildingBlock.OwnerID == 0;
            }

            public void OnPlayerBuild(BasePlayer player, BaseEntity entity, BaseEntity targetEntity)
            {
                if (entity == null)
                    return;

                if (!IsEntityShouldParent(entity))
                    return;

                BuildingBlock buildingBlock = entity as BuildingBlock;
                if (buildingBlock != null)
                {
                    entity.SetParent(mainEntity, true, true);

                    if (IsPlayerCanBuild(player, true) && IsPlayerCanBuild(buildingBlock, player))
                    {
                        shouldUpdateStability = true;
                    }
                    else
                    {
                        buildingBlock.Invoke(() => buildingBlock.Kill(BaseNetworkable.DestroyMode.Gib), 0.1f);
                    }

                    return;
                }
		   		 		  						  	   		   					  			 		   					  	 		
                if (IsEntityShoutParentToTargetEntity(entity))
                    entity.SetParent(targetEntity, true, true);
                else
                    entity.SetParent(mainEntity, true, true);

                BuildingPrivlidge buildingPrivilege = entity as BuildingPrivlidge;
                if (buildingPrivilege != null)
                {
                    if (_buildingPrivilege.IsExists())
                    {
                        buildingPrivilege.Kill();
                        return;
                    }

                    _buildingPrivilege = buildingPrivilege;

                    if (_markerController != null)
                        _markerController.Delete();
                }
            }

            public static bool IsEntityShouldParent(BaseEntity baseEntity)
            {
                if (baseEntity is BaseLock or DoorCloser or DoorKnocker or GrowableEntity or IndustrialStorageAdaptor or StorageMonitor or IndustrialCrafter or TorchWeapon or IndustrialCrafter)
                    return false;

                return true;
            }

            public static bool IsEntityShoutParentToTargetEntity(BaseEntity entity)
            {
                if (entity is Door or ShopFront or TorchDeployableLightSource)
                    return true;

                if (entity.ShortPrefabName.Contains("wall.window.bars"))
                    return true;

                if (entity.ShortPrefabName.Contains("ballistagun.static.entity"))
                    return true;

                if (entity.ShortPrefabName.Contains("wall.window.glass"))
                    return true;

                if (entity.ShortPrefabName.Contains("shutter.metal"))
                    return true;

                if (entity.ShortPrefabName.Contains("wall.frame.fence") || entity.ShortPrefabName.Contains("wall.frame.netting") || entity.ShortPrefabName.Contains("wall.frame.cell"))
                    return true;

                if (entity.ShortPrefabName.Contains("floor.grill") || entity.ShortPrefabName.Contains("floor.triangle.grill"))
                    return true;

                return false;
            }

            private bool IsPlayerCanBuild(BuildingBlock buildingBlock, BasePlayer player)
            {
                if (buildingBlock.ShortPrefabName.Contains("floor") && !IsInsideBargeCollider(buildingBlock))
                {
                    if (player != null)
                        NotifyManager.SendMessageToPlayer(player, "OutsideBarge");

                    return false;
                }
                else if (BargeConfig.MaxFloors > 0 && !buildingBlock.ShortPrefabName.Contains("floor"))
                {
                    float maxHeight = (BargeConfig.MaxFloors - 1) * 3 + 1.75f + 0.9f;
                    float currentHeight = buildingBlock.transform.position.y - mainEntity.transform.position.y;

                    if (currentHeight > maxHeight)
                    {
                        if (player != null)
                            NotifyManager.SendMessageToPlayer(player, "TooHigh", BargeConfig.MaxFloors.ToString());

                        return false;
                    }
                }

                return true;
            }

            public bool IsInsideBargeCollider(BaseEntity entity)
            {
                Vector3 positionForCheck = new Vector3(entity.transform.position.x, mainEntity.transform.position.y, entity.transform.position.z);

                if (entity is BuildingBlock && entity.PrefabName.Contains("triangle"))
                    positionForCheck += entity.transform.forward * 2;

                if (_colliderSize.z == 0)
                {
                    if (Vector3.Distance(positionForCheck, mainEntity.transform.position) > _colliderSize.x / 2 - 1)
                    {
                        return false;
                    }
                }
                else
                {
                    Vector3 localCheckPosition = PositionDefiner.GetLocalPosition(mainEntity.transform, positionForCheck);

                    if (Math.Abs(localCheckPosition.x) >= _bargeSize.x || Math.Abs(localCheckPosition.z) >= _bargeSize.z)
                        return false;
                }

                return true;
            }

            public BargePhysics GetBargePhysics()
            {
                return _physics;
            }

            private void SwitchParentTrigger(bool isEnable)
            {
                if (isEnable)
                {
                    if (_triggerParent != null)
                    {
                        _triggerParent.enabled = true;
                        return;
                    }

                    GameObject triggerGameObject = new GameObject("TriggerPlayerParent")
                    {
                        layer = 18
                    };
                    triggerGameObject.transform.SetParent(mainEntity.transform, false);
                    AttachCollider(triggerGameObject);

                    _triggerParent = triggerGameObject.AddComponent<CustomTriggerParent>();
                    _triggerParent.entityContents = new HashSet<BaseEntity>();
                    _triggerParent.InterestLayers = (1 << 0 | 1 << 9 | 1 << 11 | 1 << 15 | 1 << 17 | 1 << 31);
                    _triggerParent.doClippingCheck = false;
                    _triggerParent.parentMountedPlayers = true;
                    _triggerParent.parentSleepers = true;
                    _triggerParent.ParentNPCPlayers = false;
                    _triggerParent.overrideOtherTriggers = false;
                    _triggerParent.checkForObjUnderFeet = false;

                    foreach (BasePlayer player in BasePlayer.allPlayerList)
                        if (player.IsRealPlayer() && player.IsSleeping() && IsInsideBargeCollider(player))
                            _triggerParent.OnEntityEnter(player);
                }
                else
                {
                    if (_triggerParent == null)
                        return;

                    if (_triggerParent.entityContents != null)
                    {
                        List<BaseEntity> content = Pool.Get<List<BaseEntity>>();

                        foreach (BaseEntity entity in _triggerParent.entityContents)
                            if (entity != null)
                                content.Add(entity);

                        foreach (BaseEntity entity in content)
                        {
                            _triggerParent.OnEntityLeave(entity);

                            RidableHorse horse = entity as RidableHorse;
                            if (horse != null && !horse.AnyMounted())
                            {
                                horse.limitNetworking = true;
                                horse.limitNetworking = false;
                            }
                        }

                        Pool.FreeUnmanaged(ref content);
                    }

                    UnityEngine.Object.DestroyImmediate(_triggerParent.gameObject);
                }
            }

            public void StartMoving()
            {
                if (!IsStopped())
                {
                    _lastMovingTime = Time.realtimeSinceStartup;
                    return;
                }

                OnStartMovingEntitiesUpdate();
                _buildingVisibilityUpdater.OnStartMoving();
                SwitchParentTrigger(true);
                _physics.SwitchPhysics(true);
                _lastMovingTime = Time.realtimeSinceStartup;
                mainEntity.SendNetworkUpdate();
            }

            private void OnStartMovingEntitiesUpdate()
            {
                _bargeSize.y = float.MinValue;

                foreach (BaseEntity baseEntity in mainEntity.children)
                {
                    BuildingBlock buildingBlock = baseEntity as BuildingBlock;
                    if (buildingBlock != null)
                    {
                        if (buildingBlock.transform.localPosition.y > _bargeSize.y)
                            _bargeSize.y = buildingBlock.transform.localPosition.y;

                        continue;
                    }

                    BaseMountable baseMountable = baseEntity as BaseMountable;
                    if (baseMountable != null)
                    {
                        if ((baseMountable is BaseVehicleSeat && baseMountable.ShortPrefabName != "arcadeuser") || baseMountable is MovableBaseMountable)
                            continue;

                        baseMountable.DismountAllPlayers();
                        continue;
                    }

                    ModularCarGarage modularCarGarage = baseEntity as ModularCarGarage;
                    if (modularCarGarage != null)
                    {
                        modularCarGarage.enabled = false;
                        continue;
                    }

                    ChickenCoop chickenCoop = baseEntity as ChickenCoop;
                    if (chickenCoop != null)
                    {
                        foreach (BaseEntity childEntity in chickenCoop.children)
                        {
                            FarmableAnimal farmableAnimal = childEntity as FarmableAnimal;
                            if (farmableAnimal == null)
                                continue;

                            farmableAnimal.CancelInvoke(farmableAnimal.GetPrivateAction("MoveToNewLocation"));
                        }
                    }
                }
            }

            public void StopMoving(bool isUnloading)
            {
                _physics.SwitchPhysics(false);

                if (!IsBargeOnShoal())
                {
                    Vector3 rotation = mainEntity.transform.rotation.eulerAngles;
                    mainEntity.transform.rotation = Quaternion.Euler(new Vector3(0, rotation.y, 0));
                }

                SwitchParentTrigger(false);

                Invoke((() =>
                {
                    _buildingVisibilityUpdater.OnStopMoving();
                    OnStopMovingEntitiesUpdate();
		   		 		  						  	   		   					  			 		   					  	 		
                    foreach (BaseModule baseModule in _modules)
                        if (baseModule != null)
                            baseModule.UpdateModuleVisibility();
                }), 0.5f);

                foreach (BaseModule baseModule in _modules)
                    if (baseModule != null)
                        baseModule.OnBargeStopMoving();
            }

            private void OnStopMovingEntitiesUpdate()
            {
                for (int i = 0; i < mainEntity.children.Count; i++)
                {
                    BaseEntity entity = mainEntity.children[i];

                    ModularCarGarage modularCarGarage = entity as ModularCarGarage;
                    if (modularCarGarage != null)
                    {
                        Transform snapLocation = modularCarGarage.magnetSnap.GetPrivateFieldValue("snapLocation") as Transform;
                        modularCarGarage.magnetSnap.SetPrivateFieldValue("prevSnapLocation", snapLocation.position);
                        modularCarGarage.enabled = true;
                        continue;
                    }
		   		 		  						  	   		   					  			 		   					  	 		
                    ChickenCoop chickenCoop = entity as ChickenCoop;
                    if (chickenCoop != null)
                    {
                        for (int j = 0; j < chickenCoop.children.Count; j++)
                        {
                            FarmableAnimal farmableAnimal = chickenCoop.children[j] as FarmableAnimal;
                            if (farmableAnimal == null)
                                continue;

                            farmableAnimal.CancelInvoke(farmableAnimal.GetPrivateAction("MoveToNewLocation"));
                            farmableAnimal.CallPrivateMethod("MoveToNewLocation");
                        }
                    }
                }
            }

            private void AttachCollider(GameObject gameObj)
            {
                float colliderHeight = _bargeSize.y + 7.5f;

                if (_colliderSize.z == 0)
                {
                    CapsuleCollider capsuleCollider = gameObj.AddComponent<CapsuleCollider>();
                    capsuleCollider.isTrigger = true;
                    capsuleCollider.center = new Vector3(0, 0.8f + colliderHeight / 2, 0);
                    capsuleCollider.radius = _colliderSize.x / 2;
                    capsuleCollider.height = colliderHeight;
                }
                else
                {
                    BoxCollider boxCollider = gameObj.AddComponent<BoxCollider>();
                    boxCollider.isTrigger = true;
                    boxCollider.size = new Vector3(_colliderSize.x, colliderHeight, _colliderSize.z);
                    boxCollider.center = new Vector3(0, 0.8f + colliderHeight / 2, 0);
                }
            }

            public bool IsBargeOnShoal(float maxYPos = -3.5f)
            {
                if (IsOnShoal(transform.position, maxYPos))
                    return true;

                Vector3 test1 = PositionDefiner.GetGlobalPosition(mainEntity.transform, new Vector3(_bargeSize.x, 0, _bargeSize.z));
                Vector3 test2 = PositionDefiner.GetGlobalPosition(mainEntity.transform, new Vector3(_bargeSize.x, 0, -_bargeSize.z));
                Vector3 test3 = PositionDefiner.GetGlobalPosition(mainEntity.transform, new Vector3(-_bargeSize.x, 0, _bargeSize.z));
                Vector3 test4 = PositionDefiner.GetGlobalPosition(mainEntity.transform, new Vector3(-_bargeSize.x, 0, -_bargeSize.z));

                return IsOnShoal(test1, maxYPos) || IsOnShoal(test2, maxYPos) || IsOnShoal(test3, maxYPos) || IsOnShoal(test4, maxYPos);
            }

            private static bool IsOnShoal(Vector3 position, float maxYPos)
            {
                return TerrainMeta.HeightMap.GetHeight(position) > maxYPos;
            }

            private void FixedUpdate()
            {
                if (shouldAnchor)
                {
                    if (IsBargeOnShoal(0) || (Math.Abs(mainEntity.transform.position.y) <= 0.5f && Vector3.Angle(mainEntity.transform.forward, new Vector3(mainEntity.transform.forward.x, 0, mainEntity.transform.forward.z)) < 2.5f && Vector3.Angle(mainEntity.transform.right, new Vector3(mainEntity.transform.right.x, 0, mainEntity.transform.right.z)) < 3.5f))
                    {
                        shouldAnchor = false;
                        StopMoving(false);
                    }
                }
            }

            private IEnumerator BargeUpdateCoroutine()
            {
                while (true)
                {
                    if (shouldUpdateStability)
                    {
                        StabilityManager.UpdateChildEntitiesStability(mainEntity);
                        shouldUpdateStability = false;
                    }

                    if (!IsStopped() && !shouldAnchor)
                    {
                        foreach (BaseModule baseModule in _modules)
                        {
                            if (baseModule == null)
                                continue;

                            if (baseModule.IsModuleMoving())
                            {
                                _lastMovingTime = Time.realtimeSinceStartup;
                                break;
                            }
                        }

                        if (Time.realtimeSinceStartup - _lastMovingTime > _ins._config.PerformanceConfig.AnchorTime)
                            shouldAnchor = true;
                    }
                    yield return CoroutineEx.waitForSeconds(0.25f);

                    if (!IsStopped())
                        _buildingVisibilityUpdater.UpdateBuildingBlockModels();

                    yield return CoroutineEx.waitForSeconds(2f);
                }
            }

            private void UnloadBarge()
            {
                StopMoving(true);
                _physics.DestroyPhysics();

                foreach (BaseModule baseModule in _modules)
                    if (baseModule != null)
                        baseModule.UnloadModule();

                if (_triggerParent != null)
                    UnityEngine.GameObject.Destroy(_triggerParent.gameObject);

                if (_buildingVisibilityUpdater != null)
                    _buildingVisibilityUpdater.DestroyUpdater();

                if (_decayManager != null)
                    _decayManager.DestroyUpdater();

                if (_markerController != null)
                    _markerController.Delete();

                Destroy(this);
            }

            public void KillBarge()
            {
                shouldKill = true;

                if (mainEntity.IsExists())
                    mainEntity.Kill();

                if (_markerController != null)
                    _markerController.Delete();

                _physics.DestroyPhysics();
            }

            private void OnDestroy()
            {
                if (_bargeUpdateCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_bargeUpdateCoroutine);
            }
        }

        private class PresetLocationConfig : LocationConfig
        {
            [JsonProperty(En ? "Preset name" : "Название пресета")] 
            public string PresetName { get; set; }
        }
        private readonly HashSet<BuildingBlockModelInfo> _buildingBlockModelInfos = new HashSet<BuildingBlockModelInfo>
        {
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/wall/wall.prefab",
                BadModelStates = new HashSet<int> { 9, 17, 25 },
                GoodModelState = 1
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof/roof.prefab",
                BadModelStates = new HashSet<int> { 12, 20, 28, 36, 44, 60, 68, 76, 84, 92, 100, 108, 124 },
                GoodModelState = 4
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof/roof.prefab",
                BadModelStates = new HashSet<int> { 8228, 8260, 8292, 24620, 24652, 24684, 24588, },
                GoodModelState = 8196
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof/roof.prefab",
                BadModelStates = new HashSet<int> {32852, 32788, 32836, 98316, 98332, 98380, 98396 },
                GoodModelState = 32772
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof/roof.prefab",
                BadModelStates = new HashSet<int> {33, 65, 97},
                GoodModelState = 1
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof/roof.prefab",
                BadModelStates = new HashSet<int> {18, 66, 82},
                GoodModelState = 2
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof/roof.prefab",
                BadModelStates = new HashSet<int> {67},
                GoodModelState = 3
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof.triangle/roof.triangle.prefab",
                BadModelStates = new HashSet<int> {3, 5, 7, 9, 11, 13, 15 },
                GoodModelState = 1
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/roof.triangle/roof.triangle.prefab",
                BadModelStates = new HashSet<int> {25, 49, 51, 57, 59, 8392713},
                GoodModelState = 17
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/wall.half/wall.half.prefab",
                BadModelStates = new HashSet<int> {1, 2, 3},
                GoodModelState = 0
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/wall.low/wall.low.prefab",
                BadModelStates = new HashSet<int> {1, 2, 3},
                GoodModelState = 0
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/wall.window/wall.window.prefab",
                BadModelStates = new HashSet<int> {1, 2},
                GoodModelState = 0
            },
            new BuildingBlockModelInfo
            {
                Prefab = "assets/prefabs/building core/wall.doorway/wall.doorway.prefab",
                BadModelStates = new HashSet<int> {1, 2},
                GoodModelState = 0
            }
        };

        private class BoxColliderData
        {
            [JsonProperty("Local Position")] 
            public string LocalPosition { get; set; }
            
            [JsonProperty("Local Rotation")] 
            public string LocalRotation { get; set; }
            
            [JsonProperty("Size")] 
            public string Size { get; set; }
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private object OnEntityTakeDamage(PressButton button, HitInfo info)
        {
            if (button == null || info == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(button);
            if (barge == null)
                return null;

            BaseEntity parentEntity = button.GetParentEntity();
            if (parentEntity == null)
                return null;

            BaseModule baseModule = parentEntity.GetComponentInChildren<BaseModule>();
            if (baseModule == null)
                return null;

            return true;
        }
        private Coroutine _spawnCoroutine;
		   		 		  						  	   		   					  			 		   					  	 		
        private class PerformanceConfig
        {
            [JsonProperty(En ? "Automatically apply the most optimized skins to buildings (It is not recommended to change!) [true/false]" : "Автоматически применять наиболее оптимизированные скины к постройкам (Не рекомендуется изменять!) [true/false]")]
            public bool AutoSkin { get; set; }

            [JsonProperty(En ? "Allow grade building in Wood [true/false]" : "Разрешить грейдить постройку в Дерево [true/false]")]
            public bool AllowWood { get; set; }

            [JsonProperty(En ? "Allow grade building in Metal [true/false]" : "Разрешить грейдить постройку в Металл [true/false]")]
            public bool AllowMetal { get; set; }

            [JsonProperty(En ? "Allow grade building in Stone (It is not recommended to change it!) [true/false]" : "Разрешить грейдить постройку в Камень (Не рекомендуется изменять!) [true/false]")]
            public bool AllowStone { get; set; }

            [JsonProperty(En ? "Allow grade building in HQM (It is not recommended to change it!) [true/false]" : "Разрешить грейдить постройку в МВК (Не рекомендуется изменять!) [true/false]")]
            public bool AllowHqm { get; set; }

            [JsonProperty(En ? "The number of building blocks updated per tick (It is not recommended to increase!)" : "Количество билдинг блоков, обновляемых за тик (Не рекомендуется увеличивать!)")]
            public int UpdatePerTick { get; set; }

            [JsonProperty(En ? "Do not anchor the barge automatically if it is connected to another barge or tugboat (disable if there are a large number of barges on the server)" : "Не ставить баржу на якорь автоматически, если она присоединена к другой барже или буксиру (отключить при большом количестве барж на сервере)")]
            public bool DontAnchorIfConnected { get; set; }

            [JsonProperty(En ? "The time after which the barge is automatically anchored after stopping [sec]" : "Время через которое баржа автоматически ставится на якорь после остановки [sec]")]
            public int AnchorTime { get; set; }
        }

        private void OnPlayerDropFlare(BasePlayer player, RoadFlare roadFlare, ThrownWeapon thrownWeapon)
        {
            if (!player.IsRealPlayer() || roadFlare == null || thrownWeapon == null)
                return;

            Item item = thrownWeapon.GetItem();
            if (item == null)
                return;

            BargeConfig bargeConfig = _config.BargeConfigs.FirstOrDefault(x => x.ItemConfig.Shortname == item.info.shortname && x.ItemConfig.Skin == item.skin);
            if (bargeConfig == null)
                return;

            BargeCaller.Attach(roadFlare, player, bargeConfig);
        }
        private readonly HashSet<BoatConnectionTrigger> _boatConnectionTriggers = new HashSet<BoatConnectionTrigger>();

        private object CanChangeGrade(BasePlayer player, BuildingBlock block, BuildingGrade.Enum grade)
        {
            if (player == null)
                return null;

            Barge barge = Barge.GetBargeByEntity(block);
            if (barge == null)
                return null;

            if (!barge.IsPlayerCanBuild(player, true))
                return false;

            if (block.grade == grade && _config.PerformanceConfig.AutoSkin)
                return false;

            if (Barge.IsBasicBuildingBlock(block))
                return false;

            if ((!_ins._config.PerformanceConfig.AllowStone && grade == BuildingGrade.Enum.Stone) || (!_ins._config.PerformanceConfig.AllowHqm && grade == BuildingGrade.Enum.TopTier))
            {
                NotifyManager.SendMessageToPlayer(player, "BlockGrade");
                return false;
            }
            else if ((!_ins._config.PerformanceConfig.AllowMetal && grade == BuildingGrade.Enum.Metal) || (!_ins._config.PerformanceConfig.AllowWood && grade == BuildingGrade.Enum.Wood))
            {
                return false;
            }

            return null;
        }

        private class BoxColliderInfo
        {
            public readonly Vector3 LocalPosition;
            public readonly Vector3 LocalRotation;
            public readonly Vector3 Size;

            public BoxColliderInfo(BoxColliderData boxColliderData)
            {
                LocalPosition = boxColliderData.LocalPosition.ToVector3();
                LocalRotation = boxColliderData.LocalRotation.ToVector3();
                Size = boxColliderData.Size.ToVector3();
            }

            public BoxColliderInfo(Vector3 localPosition, Vector3 localRotation, Vector3 size)
            {
                this.LocalPosition = localPosition;
                this.LocalRotation = localRotation;
                this.Size = size;
            }
        }

        private class ItemConfig
        {
            [JsonProperty("ShortName")] 
            public string Shortname { get; set; }
            
            [JsonProperty("SkinID (0 - default)")] 
            public ulong Skin { get; set; }
            
            [JsonProperty(En ? "Name (empty - default)" : "Название (empty - default)")] 
            public string Name { get; set; }
        }

        private class BargeModuleConfig : PresetLocationConfig
        {
            [JsonProperty(En ? "Turn it on? [true/false]" : "Включить? [true/false]")] 
            public bool IsEnable { get; set; }
        }
        
        private void OnEntitySpawned(TorchDeployableLightSource torchDeployable)
        {
            CheckIfOtherPluginSpawnedEntity(torchDeployable);
        }
		   		 		  						  	   		   					  			 		   					  	 		
        private class CabinModule : BaseModule
        {
            private BaseMountable _driverSeat;
            private BargePhysics _physics;
            private BaseEntity _engineSoundEntity;
            private ResourceExtractorFuelStorage _fuelStorage;
            private Dashboard _dashboard;
            private float _lastFuelConsumptionTime;
            private float _targetConsumptionAmount;
            private bool _haveFuel;
            private bool _isEngineOne;

            public static CabinModule SpawnCabinModule(BaseEntity parentEntity, Barge barge, CustomPrefabData customPrefabData)
            {
                CabinModule cabinModule = parentEntity.gameObject.AddComponent<CabinModule>();
                cabinModule.Init(parentEntity, barge);
                parentEntity.Invoke(() => cabinModule.LoadModule(), 1f);
                return cabinModule;
            }

            private void LoadModule()
            {
                SpawnDriverSeat();
                _fuelStorage = parentEntity.children.FirstOrDefault(x => x is ResourceExtractorFuelStorage) as ResourceExtractorFuelStorage;
                _fuelStorage.inventory.SetOnlyAllowedItem(ItemManager.FindItemDefinition(-946369541));
                _fuelStorage.inventory.capacity = 1;
                _fuelStorage.enableSaving = true;
                _fuelStorage.SendNetworkUpdate();
                _dashboard = Dashboard.SpawnDashboard(this, new Vector3(0.549f, 1.859f, 2.256f), new Vector3(341.874f, 180, 0), Barge.GetBargePhysics());
                PressButton.pressDuration = 3f;
                _physics = Barge.GetBargePhysics();
                _engineSoundEntity = BuildManager.SpawnChildEntity(parentEntity, "assets/content/vehicles/dpv/dpv.deployed.prefab", new Vector3(0, 0.5f, 0), Vector3.zero, isDecor: true);

                if (CalculateFuelAmount() == 0)
                {
                    BargeSaveData bargeSaveData = _ins._bargeSaveData.FirstOrDefault(x => x.NetId == Barge.mainEntity.net.ID.Value);

                    if (bargeSaveData == null || bargeSaveData.FuelAmount == 0)
                        return;

                    Item fuelItem = ItemManager.CreateByName("lowgradefuel", bargeSaveData.FuelAmount);
                    _fuelStorage.inventory.GiveItem(fuelItem);
                }
            }

            private void SpawnDriverSeat()
            {
                _driverSeat = MovableBaseMountable.CreateMovableBaseMountable("assets/prefabs/vehicle/seats/modularcardriverseat.prefab", parentEntity, new Vector3(0.17f, 1.15f, 2), Vector3.zero);
                _driverSeat = parentEntity.children.FirstOrDefault(x => x is BaseMountable) as BaseMountable;
                GameObject gameObj = new GameObject("CustomDismountPosition");
                gameObj.transform.localPosition = new Vector3(-1.25f, 0, 0.2f);
                gameObj.transform.SetParent(_driverSeat.transform, false);
                Transform[] dismountPositions = {
                    gameObj.transform
                };
                _driverSeat.dismountPositions = dismountPositions;
            }

            public override void OnButtonPressed(BasePlayer player)
            {
                if (player.isMounted)
                    return;

                _driverSeat.AttemptMount(player, false);
                Barge.StartMoving();
            }

            public override bool IsModuleMoving()
            {
                return _driverSeat.GetMounted() != null;
            }

            public void FixedUpdate()
            {
                if (_driverSeat == null)
                    return;

                BasePlayer driver = _driverSeat.GetMounted();

                if (driver == null)
                    return;

                if (driver.serverInput.IsDown(BUTTON.JUMP) || driver.serverInput.WasJustPressed(BUTTON.JUMP) || driver.serverInput.WasJustReleased(BUTTON.JUMP))
                {
                    DismountPlayer(driver);
                    return;
                }

                if (_fuelStorage == null)
                    return;

                if (!_haveFuel)
                {
                    ConsumeFuel();
                    return;
                }

                float throttle;
                float steering;

                if (driver.serverInput.IsDown(BUTTON.FORWARD))
                    throttle = 1f;
                else if (driver.serverInput.IsDown(BUTTON.BACKWARD))
                    throttle = -1f;
                else
                    throttle = 0f;

                if (driver.serverInput.IsDown(BUTTON.LEFT))
                    steering = 1f;
                else if (driver.serverInput.IsDown(BUTTON.RIGHT))
                    steering = -1f;
                else
                    steering = 0f;

                Vector3 force = Barge.transform.forward * (throttle * Barge.BargeConfig.EngineConfig.PowerScale * 0.5f);

                if (force.magnitude > 0)
                {
                    ConsumeFuel();

                    if (Barge.IsBargeOnShoal(-1f))
                    {
                        if (TerrainMeta.HeightMap.GetHeight(Barge.transform.position) < TerrainMeta.HeightMap.GetHeight(Barge.transform.position + force.normalized))
                            return;
                    }

                    if (steering > 0)
                    {
                        _physics.AddForceAtPosition(force * Barge.BargeConfig.EngineConfig.RotateScale, PositionDefiner.GetGlobalPosition(Barge.transform, new Vector3(8, -5f, -13)));
                    }
                    else if (steering < 0)
                    {
                        _physics.AddForceAtPosition(force * Barge.BargeConfig.EngineConfig.RotateScale, PositionDefiner.GetGlobalPosition(Barge.transform, new Vector3(-8, -5f, -13)));
                    }
                    else
                    {
                        _physics.AddForceAtPosition(force, PositionDefiner.GetGlobalPosition(Barge.transform, new Vector3(-8, -10f, -13)));
                        _physics.AddForceAtPosition(force, PositionDefiner.GetGlobalPosition(Barge.transform, new Vector3(8, -10f, -13)));
                    }

                    SwitchEngine(true);
                }
            }

            private void ConsumeFuel()
            {
                if (Barge.BargeConfig.EngineConfig.FuelScale == 0)
                {
                    _haveFuel = true;
                    return;
                }

                float timeScienceConsumption = Time.realtimeSinceStartup - _lastFuelConsumptionTime;

                if (timeScienceConsumption >= 2)
                {
                    Item fuelItem = _fuelStorage.inventory.itemList.FirstOrDefault(x => x != null && x.info.shortname == "lowgradefuel");

                    if (fuelItem == null)
                    {
                        if (_haveFuel)
                        {
                            _haveFuel = false;
                            SwitchEngine(false);
                        }
                        return;
                    }

                    _haveFuel = true;
                    _lastFuelConsumptionTime = Time.realtimeSinceStartup;
                    _targetConsumptionAmount += 1 * Barge.BargeConfig.EngineConfig.FuelScale;
                    int consumptionInInt = (int)_targetConsumptionAmount;
		   		 		  						  	   		   					  			 		   					  	 		
                    if (consumptionInInt > 0)
                    {
                        _targetConsumptionAmount -= consumptionInInt;

                        if (fuelItem.amount > consumptionInInt)
                        {
                            fuelItem.amount = fuelItem.amount - consumptionInInt;
                            fuelItem.MarkDirty();
                        }
                        else
                        {
                            fuelItem.Remove();
                        }
                    }
                }
            }

            public int CalculateFuelAmount()
            {
                if (_fuelStorage == null)
                    return 0;

                int result = 0;

                foreach (Item item in _fuelStorage.inventory.itemList)
                    if (item != null && item.info.shortname == "lowgradefuel")
                        result += item.amount;

                return result;
            }

            private void DismountPlayer(BasePlayer player)
            {
                _driverSeat.DismountPlayer(player);
                player.Invoke(() => player.Teleport(_driverSeat.dismountPositions[0].position), 0.01f);
                SwitchEngine(false);
            }

            private void SwitchEngine(bool on)
            {
                if (_isEngineOne == on)
                    return;

                _isEngineOne = on;

                if (on)
                {
                    _engineSoundEntity.SetFlag(BaseEntity.Flags.On, true);
                    _dashboard.OnComponent();
                }
                else
                {
                    _engineSoundEntity.SetFlag(BaseEntity.Flags.On, false);
                    _dashboard.OffComponent();
                }
            }

            public override void UnloadModule()
            {
                if (_driverSeat.IsExists())
                    _driverSeat.Kill();

                if (_dashboard != null)
                    _dashboard.KillDashboard();

                if (_engineSoundEntity.IsExists())
                    _engineSoundEntity.Kill();

                base.UnloadModule();
            }

            private class Dashboard : FacepunchBehaviour
            {
                private CabinModule _cabinModule;
                private BargePhysics _bargePhysics;
                private BaseEntity _digitalClock;
                private PowerCounter _fuelDisplay;
                private PowerCounter _speedDisplay;
                private Coroutine _updateCoroutine;

                public static Dashboard SpawnDashboard(CabinModule cabinModule, Vector3 localPosition, Vector3 localRotation, BargePhysics bargePhysics)
                {
                    BaseEntity digitalClock = BuildManager.SpawnChildEntity(cabinModule.parentEntity, "assets/prefabs/deployable/playerioents/digitalclock/electric.digitalclock.deployed.prefab", localPosition, localRotation, isDecor: true);
                    digitalClock.SetFlag(BaseEntity.Flags.Busy, true);
                    Dashboard dashboard = digitalClock.gameObject.AddComponent<Dashboard>();
                    dashboard.Init(cabinModule, digitalClock, bargePhysics);
                    return dashboard;
                }

                private void Init(CabinModule cabinModule, BaseEntity digitalClock, BargePhysics bargePhysics)
                {
                    this._cabinModule = cabinModule;
                    this._digitalClock = digitalClock;
                    this._bargePhysics = bargePhysics;
                    _fuelDisplay = SpawnDisplay(new Vector3(-0.124f, -0.069f, -0.032f));
                    _speedDisplay = SpawnDisplay(new Vector3(0.124f, -0.069f, -0.032f));
                    _updateCoroutine = ServerMgr.Instance.StartCoroutine(UpdateCoroutine());
                }

                private PowerCounter SpawnDisplay(Vector3 position)
                {
                    PowerCounter powerCounter = BuildManager.SpawnChildEntity(_digitalClock, "assets/prefabs/deployable/playerioents/counter/counter.prefab", position, Vector3.zero, isDecor: false) as PowerCounter;
                    powerCounter.targetCounterNumber = int.MaxValue;
                    powerCounter.SetFlag(BaseEntity.Flags.Busy, true);
                    return powerCounter;
                }

                private IEnumerator UpdateCoroutine()
                {
                    while (_cabinModule != null)
                    {
                        int speed = _bargePhysics.GetSpeed();
                        int fuel = _cabinModule.CalculateFuelAmount();

                        UpdateDisplay(_fuelDisplay, fuel);
                        UpdateDisplay(_speedDisplay, speed);

                        yield return CoroutineEx.waitForSeconds(0.7f);
                    }
                }

                private static void UpdateDisplay(PowerCounter display, int value)
                {
                    if (display.counterNumber == value)
                        return;

                    display.counterNumber = value;
                    display.SendNetworkUpdate();
                }

                public void OnComponent()
                {
                    if (_updateCoroutine != null)
                        ServerMgr.Instance.StopCoroutine(_updateCoroutine);

                    _updateCoroutine = ServerMgr.Instance.StartCoroutine(UpdateCoroutine());
                    _fuelDisplay.UpdateFromInput(1, 0);
                    _speedDisplay.UpdateFromInput(1, 0);
                }

                public void OffComponent()
                {
                    if (_updateCoroutine != null)
                        ServerMgr.Instance.StopCoroutine(_updateCoroutine);

                    _updateCoroutine = null;
                    _fuelDisplay.UpdateFromInput(0, 0);
                    _speedDisplay.UpdateFromInput(0, 0);
                }

                public void KillDashboard()
                {
                    _digitalClock.Kill();
                }

                private void OnDestroy()
                {
                    if (_updateCoroutine != null)
                        ServerMgr.Instance.StopCoroutine(_updateCoroutine);
                }
            }
        }

        private bool TryLoadModuleDataFile(string path)
        {
            if (_moduleCustomizations.ContainsKey(path))
                return true;

            CustomPrefabData moduleProfile = LoadDataFile<CustomPrefabData>($"Modules/{path}");

            if (moduleProfile == null || moduleProfile.DecorEntities == null)
                return false;
		   		 		  						  	   		   					  			 		   					  	 		
            _moduleCustomizations.Add(path, moduleProfile);
            return true;
        }

        private void OnEntitySpawned(DecayEntity decayEntity)
        {
            CheckIfOtherPluginSpawnedEntity(decayEntity);
        }

        private class BargeConfig
        {
            [JsonProperty(En ? "Preset name" : "Название пресета")] 
            public string PresetName { get; set; }
            
            [JsonProperty(En ? "Data file path" : "Имя дата-файла")] 
            public string DataFileName { get; set; }
            
            [JsonProperty(En ? "The maximum number of floors (-1 is not limited)" : "Максимальное количество этажей (-1 не ограничивать)")] 
            public int MaxFloors { get; set; }
            
            [JsonProperty(En ? "Weight" : "Масса")] 
            public int Mass { get; set; }
            
            [JsonProperty(En ? "Engine (Cab must be installed)" : "Двигатель (Должна быть установлена кабина)")] 
            public EngineConfig EngineConfig { get; set; }
            
            [JsonProperty(En ? "Modules" : "Модули барж")] 
            public HashSet<BargeModuleConfig> Modules { get; set; }
            
            [JsonProperty(En ? "A flare for calling a barge" : "Флаер для вызова баржи")] 
            public ItemConfig ItemConfig { get; set; }
        }

        private class ChatConfig
        {
            [JsonProperty(En ? "Use chat notifications? [true/false]" : "Использовать ли чат? [true/false]")] 
            public bool IsEnabled { get; set; }
        }

        private class IOSlotData
        {
            public readonly HashSet<Vector3> LinePoints;

            public IOSlotData(HashSet<Vector3> linePoints)
            {
                LinePoints = linePoints;
            }
        }

        private void Subscribes()
        {
            foreach (string hook in _subscribeMethods)
                Subscribe(hook);
        }

        private abstract class BaseModule : FacepunchBehaviour
        {
            public BaseEntity parentEntity;
            protected Barge Barge;
            protected PressButton PressButton;

            protected virtual void Init(BaseEntity entity, Barge barge)
            {
                parentEntity = entity;
                Barge = barge;
                PressButton = entity.children.FirstOrDefault(x => x.IsExists() && x is PressButton) as PressButton;
                PressButton.inputs = Array.Empty<IOEntity.IOSlot>();
                PressButton.outputs = Array.Empty<IOEntity.IOSlot>();
                PressButton.SetFlag(BaseEntity.Flags.InUse, true);
            }

            public bool IsModuleEntity(BaseEntity entity)
            {
                BaseEntity entityParent = entity.GetParentEntity();

                if (entityParent == null || entityParent.net == null)
                    return false;

                return entityParent.net.ID.Value == parentEntity.net.ID.Value;
            }

            public virtual void OnBargeStopMoving()
            {

            }

            public virtual bool IsModuleMoving()
            {
                return false;
            }

            public abstract void OnButtonPressed(BasePlayer player);

            public void UpdateModuleVisibility()
            {
                if (!parentEntity.IsExists())
                    return;

                foreach (BaseEntity entity in parentEntity.children)
                {
                    BuildingBlock buildingBlock = entity as BuildingBlock;
                    if (!buildingBlock.IsExists())
                        continue;

                    buildingBlock.limitNetworking = true;
                    buildingBlock.limitNetworking = false;
                }
            }

            public virtual void UnloadModule()
            {
                Destroy(this);
            }
        }

        private void OnEntitySpawned(Recycler recycler)
        {
            CheckIfOtherPluginSpawnedEntity(recycler);
        }

        private class NotifyConfig : BaseMessageConfig
        {
            [JsonProperty(En ? "Redefined messages" : "Переопределенные сообщения )", Order = 101)] 
            public HashSet<RedefinedMessageConfig> RedefinedMessages { get; set; }
        }

        private class CargoShipManager : FacepunchBehaviour
        {
            private CargoShip _cargoShip;

            public static void UpdateAllCargos()
            {
                HashSet<CargoShip> cargoShips = BaseNetworkable.serverEntities.OfType<CargoShip>();

                foreach (CargoShip cargoShip in cargoShips)
                    if (cargoShip != null)
                        AttachController(cargoShip);
            }

            public static void AttachController(CargoShip cargoShip)
            {
                CargoShipManager cargoShipManager = cargoShip.gameObject.AddComponent<CargoShipManager>();
                cargoShipManager.Init(cargoShip);
                _ins._cargoShipManagers.Add(cargoShipManager);
            }

            private void Init(CargoShip cargoShip)
            {
                _cargoShip = cargoShip;
                cargoShip.CancelInvoke(cargoShip.BuildingCheck);
                cargoShip.InvokeRepeating(CustomBuildingCheck, 1f, 5f);
            }

            private void CustomBuildingCheck()
            {
                List<BaseEntity> entities = Pool.Get<List<BaseEntity>>();
                Vis.Entities(_cargoShip.WorldSpaceBounds(), entities, 2162689);

                for (int i = 0; i < entities.Count; i++)
                {
                    BaseEntity entity = entities[i];
                    if (entity == null)
                        continue;

                    JunkPileWater junkPileWater = entity as JunkPileWater;
                    if (junkPileWater != null)
                    {
                        junkPileWater.SinkAndDestroy();
                        continue;
                    }
                    
                    DecayEntity decayEntity = entity as DecayEntity;
                    if (decayEntity != null)
                    {
                        if (!decayEntity.IsAlive() || !decayEntity.isServer || decayEntity.AllowOnCargoShip)
                            continue;
		   		 		  						  	   		   					  			 		   					  	 		
                        if (decayEntity.HasParent())
                        {
                            Barge barge = Barge.GetBargeByEntity(decayEntity);
                            if (barge != null || decayEntity.parentEntity.Get(serverside: true) == _cargoShip)
                            {
                                Pool.FreeUnmanaged(ref entities);
                                return;
                            }
                        }

                        decayEntity.Kill(BaseNetworkable.DestroyMode.Gib);
                    }
                }

                Pool.FreeUnmanaged(ref entities);
            }

            public static void Unload()
            {
                foreach (CargoShipManager cargoShipManager in _ins._cargoShipManagers)
                {
                    if (cargoShipManager != null)
                    {
                        cargoShipManager._cargoShip.CancelInvoke(cargoShipManager.CustomBuildingCheck);
                        Destroy(cargoShipManager);
                    }
                }
            }
        }

        private class ConnectorModule : BaseModule
        {
            private BargePhysics _physics;
            private IOEntity _sirenLight;
            private readonly HashSet<GameObject> _gameObjects = new HashSet<GameObject>();
            private ConnectorTrigger _connectorTrigger;
            public bool isActive;
            public Rigidbody connectedRigidbody;
            public SpringJoint springJoint;

            public static ConnectorModule SpawnConnectorModule(BaseEntity parentEntity, Barge barge, CustomPrefabData customPrefabData)
            {
                ConnectorModule connectorModule = parentEntity.gameObject.AddComponent<ConnectorModule>();
                connectorModule.Init(parentEntity, barge);
                parentEntity.Invoke(() => connectorModule.LoadModule(), 1f);
                return connectorModule;
            }

            private void LoadModule()
            {
                _sirenLight = parentEntity.children.FirstOrDefault(x => x.IsExists() && x is IOEntity && x.ShortPrefabName == "sirenlightorange") as IOEntity;
                _sirenLight.SetFlag(BaseEntity.Flags.InUse, true);
                parentEntity.gameObject.layer = (int)Layer.Reserved1;
                _physics = Barge.GetBargePhysics();
            }

            public override void OnButtonPressed(BasePlayer player)
            {
                if (springJoint != null)
                {
                    BreakConnection();
                    return;
                }

                SwitchConnector(!isActive);
            }

            public void SwitchConnector(bool isEnable)
            {
                isActive = isEnable;

                if (_sirenLight != null)
                    _sirenLight.UpdateFromInput(isActive ? 1 : 0, 0);

                if (isEnable)
                {
                    Barge.StartMoving();
                    _connectorTrigger = ConnectorTrigger.AttachConnectorTrigger(parentEntity, _physics);
                }
                else
                {
                    if (_connectorTrigger != null)
                        Destroy(_connectorTrigger.gameObject);
                }
            }

            public void DestroyConnector()
            {
                GameObject.Destroy(this);
            }

            public override void OnBargeStopMoving()
            {
                SwitchConnector(false);
            }

            public override bool IsModuleMoving()
            {
                if (springJoint == null || connectedRigidbody == null)
                    return false;

                if (!_ins._config.PerformanceConfig.DontAnchorIfConnected && _physics.GetSpeed() < 0.5f)
                    return false;

                return true;
            }

            public void BreakConnection()
            {
                if (springJoint != null)
                    Destroy(springJoint);

                connectedRigidbody = null;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            public override void UnloadModule()
            {
                base.UnloadModule();

                foreach (GameObject gameObj in _gameObjects)
                    if (gameObj != null)
                        Destroy(gameObj);

                SwitchConnector(false);
            }
		   		 		  						  	   		   					  			 		   					  	 		
            private class ConnectorTrigger : FacepunchBehaviour
            {
                private BargePhysics _physics;

                public static ConnectorTrigger AttachConnectorTrigger(BaseEntity parentEntity, BargePhysics physics)
                {
                    GameObject gameObject = new GameObject("ConnectorTrigger");
                    gameObject.layer = (int)Layer.Reserved1;
                    gameObject.transform.localPosition = new Vector3(0, 0, 5.3f);
                    gameObject.transform.SetParent(parentEntity.transform, false);
                    ConnectorTrigger connectorTrigger = gameObject.AddComponent<ConnectorTrigger>();
                    connectorTrigger.Init(physics);
                    return connectorTrigger;
                }

                public void Init(BargePhysics physics)
                {
                    _physics = physics;
                    CreateSphereCollider();
                }

                private void CreateSphereCollider()
                {
                    SphereCollider sphereCollider = this.gameObject.AddComponent<SphereCollider>();
                    sphereCollider.isTrigger = true;
                    sphereCollider.radius = 2.5f;
                }

                private void OnTriggerEnter(Collider other)
                {
                    if (other == null)
                        return;

                    BaseEntity entity = other.ToBaseEntity();
                    if (entity == null || entity.net == null || entity.ShortPrefabName != "kayak")
                        return;

                    Barge barge = Barge.GetBargeByPhysicsEntityNetId(entity.net.ID.Value);

                    if (barge == null)
                        return;

                    BargePhysics bargePhysics = barge.GetBargePhysics();
                    _physics.OnBargeWantConnect(bargePhysics);
                }
            }
        }

        private class BoatConnectionTrigger : FacepunchBehaviour
        {
            private BaseBoat _baseBoat;

            public static void UpdateAllBoats()
            {
                foreach (BaseMountable baseMountable in BaseMountable.AllMountables)
                {
                    BaseBoat baseBoat = baseMountable as BaseBoat;
                    if (baseBoat == null)
                        continue;

                    TryAddBoatConnectionTrigger(baseBoat);
                }
            }

            public static void TryAddBoatConnectionTrigger(BaseBoat baseBoat)
            {
                BoatConnectionTrigger boatConnectionTrigger = baseBoat.gameObject.AddComponent<BoatConnectionTrigger>();
                _ins._boatConnectionTriggers.Add(boatConnectionTrigger);
                boatConnectionTrigger.Init(baseBoat);
            }

            private void Init(BaseBoat baseBoat)
            {
                this._baseBoat = baseBoat;
            }

            private void OnCollisionEnter(Collision collision)
            {
                if (collision == null || collision.collider == null)
                    return;

                BaseEntity baseEntity = collision.GetEntity();
                if (baseEntity == null)
                    return;

                if (baseEntity.ShortPrefabName != "newyeargong.deployed")
                    return;

                Barge barge = Barge.GetBargeByEntity(baseEntity);
                if (barge == null)
                    return;

                BargePhysics bargePhysics = barge.GetBargePhysics();
                bargePhysics.OnBoatWantConnect(_baseBoat);
            }

            public static void Unload()
            {
                foreach (BoatConnectionTrigger boatConnectionTrigger in _ins._boatConnectionTriggers)
                    if (boatConnectionTrigger != null)
                        GameObject.Destroy(boatConnectionTrigger);
            }
        }

        private class SpawnMonumentConfig
        {
            [JsonProperty(En ? "The name of the monument" : "Название монумента")] 
            public string MonumentName { get; set; }
            
            [JsonProperty(En ? "Turn on spawn on this monument? [true/false]" : "Включить спавн на этом монументе? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Spawn Locations" : "Локации для спавна")] 
            public HashSet<LocationConfig> Locations { get; set; }
        }

        [ChatCommand("killbarge")]
        private void ChatKillCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            BaseEntity target = RaycastAll<BaseEntity>(player.eyes.HeadRay());
            if (target == null)
                return;
		   		 		  						  	   		   					  			 		   					  	 		
            Barge barge = Barge.GetBargeByEntity(target);
            if (barge == null)
                return;

            barge.KillBarge();
        }

        private class RedefinedMessageConfig : BaseMessageConfig
        {
            [JsonProperty(En ? "Enable this message? [true/false]" : "Включить сообщение? [true/false]", Order = 1)] 
            public bool IsEnable { get; set; }
            
            [JsonProperty("Lang Key", Order = 1)] 
            public string LangKey { get; set; }
        }

        private static void Debug(params object[] arg)
        {
            string result = "";

            foreach (object obj in arg)
                if (obj != null)
                    result += obj.ToString() + " ";

            _ins.Puts(result);
        }
            }
}
