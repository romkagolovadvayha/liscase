using System.Text;
using Pool = Facepunch.Pool;
using System.Collections.Generic;
using System;
using Oxide.Core.Plugins;
using System.Linq;
using UnityEngine;
using ConVar;
using Object = System.Object;
using Newtonsoft.Json;

namespace Oxide.Plugins
{
    [Info("IQRates", "Mercury", "2.9.24")]
    [Description("Настройка рейтинга на сервере")]
    public class IQRates : RustPlugin
    {

        private void OnPlayerDisconnected(BasePlayer player)
        {
            _carry.Remove(player.UserIDString);
            rateDataCache.Remove(player.UserIDString);
            bonusRatesPlayer.Remove(player.UserIDString);
        }

        private void ConvertRate(TypeConverted type, String userID, Item item)
        {
            Configuration.RateController.ControllerListRate controllerList = config.rateController.controllerList;
            if (!controllerList.IsConvertedItem(item)) return;

            Configuration.RateController.RateData rateData = config.rateController.GetRateData(userID);
            if (rateData == null) return;

            Single multiplicer = type switch
            {
                TypeConverted.Gather => rateData.gatherRate,
                TypeConverted.Loot => rateData.lootRate,
                TypeConverted.PickUP => rateData.pickUpRate,
                TypeConverted.Quarry => rateData.quarryRate.GetQuarryRate(item.info.itemid),
                TypeConverted.Excavator => rateData.excavatorRate,
                TypeConverted.Growable => rateData.growableRate,
                TypeConverted.Fishing => rateData.fishRate,
                _ => throw new ArgumentOutOfRangeException(nameof(type), type, null)
            };

            Single correctlyRateToPermission = rateData.GetCorrectlyRate(item.info.itemid);
            if (correctlyRateToPermission != 0)
                multiplicer = correctlyRateToPermission;

            Single bonusRate = GetBonusRate(userID);
            multiplicer += bonusRate;

            if (multiplicer < 0f) multiplicer = 0f;

            Int32 itemId = item.info.itemid;

            Double carry = GetCarry(userID, type, itemId);
            Double expected = item.amount * (Double)multiplicer + carry; 
            Int32 newAmount = (Int32)Math.Floor(expected);
            Double newCarry = expected - newAmount; 

            if (newAmount < 1)
            {
                Double debt = 1 - newAmount; 
                newAmount = 1;
                newCarry -= debt; 
            }
            
            if (newCarry >= 1.0)
            {
                Int32 k = (Int32)Math.Floor(newCarry);
                newAmount += k;
                newCarry -= k;
            }
            else if (newCarry <= -1.0)
            {
                Int32 k = (Int32)Math.Floor(-newCarry);
                newAmount -= k;
                newCarry += k;
                if (newAmount < 1)
                {
                    newCarry -= (1 - newAmount);
                    newAmount = 1;
                }
            }

            SetCarry(userID, type, itemId, newCarry);
            item.amount = newAmount;
        }
        
        private void Unload()
        {
            if (_ == null) return;
            
            WriteData();
            BackupDefaultEvents();

            if (triggersEvents != null)
            {
                foreach (EventController eventController in triggersEvents.Values)
                {
                    if (eventController.timerEvent is { Destroyed: false })
                    {
                        eventController.timerEvent.Destroy();
                        eventController.timerEvent = null;
                    }
                }
                triggersEvents.Clear();
                triggersEvents = null;
            }

            if (bonusRatesPlayer != null)
            {
                bonusRatesPlayer.Clear();
                bonusRatesPlayer = null;
            }

            rateDataCache.Clear();
            rateDataCache = null;
            
            rateBonusDay = null;
            
            teaModifers?.Clear();
		   		 		  						  	   		   					  			 		   					  	 		
            if (excavatorUsePlayer)
                excavatorUsePlayer = null;

            if (lootedEntity != null)
            {
                lootedEntity.Clear();
                lootedEntity = null;
            } 
            
            if (permissionsWipeBonusSorted != null)
            {
                permissionsWipeBonusSorted.Clear();
                permissionsWipeBonusSorted = null;
            }
            
            if (quarryToggled != null)
            {
                quarryToggled.Clear();
                quarryToggled = null;
            }
            
            if (eventMapping != null)
            {
                eventMapping.Clear();
                eventMapping = null;
            }
            
            if (timerBradley is { Destroyed: false })
            {
                timerBradley.Destroy();
                timerBradley = null;
            }     
            
            if (timerUpdateBonusRate is { Destroyed: false })
            {
                timerUpdateBonusRate.Destroy();
                timerUpdateBonusRate = null;
            }         
            
            if (timerUpdateBonusAllRate is { Destroyed: false })
            {
                timerUpdateBonusAllRate.Destroy();
                timerUpdateBonusAllRate = null;
            }
            
            if (ovenCotrollers != null)
            {
                foreach (OvenController ovenController in ovenCotrollers.Values)
                {
                    if (ovenController)
                        UnityEngine.Object.DestroyImmediate(ovenController);
                }
            
                ovenCotrollers.Clear();
                ovenCotrollers = null;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            if (permissionsOvensSorted != null)
            {
                permissionsOvensSorted.Clear();
                permissionsOvensSorted = null;
            }    
            
            if (permissionsCharacoalChanceSorted != null)
            {
                permissionsCharacoalChanceSorted.Clear();
                permissionsCharacoalChanceSorted = null;
            }    
            
            if (permissionsMixingTablesSorted != null)
            {
                permissionsMixingTablesSorted.Clear();
                permissionsMixingTablesSorted = null;
            }        
            
            if (permissionsSpeedRecyclerSorted != null)
            {
                permissionsSpeedRecyclerSorted.Clear();
                permissionsSpeedRecyclerSorted = null;
            }

            if (initializeTimeComponentTimer is { Destroyed: false })
            {
                initializeTimeComponentTimer.Destroy();
                initializeTimeComponentTimer = null;
            }

            if (timeComponent != null)
            {
                UnsubscribeFromEvents();
                timeComponent.ProgressTime = true;
                timeComponent = null;
            }

            _ = null;
        }
        
        private void OnGroupPermissionGranted(String name, String perm)
        {
            foreach (BasePlayer player in BasePlayer.activePlayerList)
                RefreshPermissionPlayer(player.UserIDString);
        }

        private void ReadData()
        {
            lootedEntity = Oxide.Core.Interface.Oxide.DataFileSystem.ReadObject<HashSet<UInt64>>("IQSystem/IQRates/LootedContainers");
            quarryToggled = Oxide.Core.Interface.Oxide.DataFileSystem.ReadObject<Dictionary<UInt64, String>>("IQSystem/IQRates/QuarryToggled");
        }
        
        private void BackupDefaultEvents()
        {
            Bradley.enabled = defaultBradleyStatus;
            if (defaultEvents == null || defaultEvents.Count == 0) return;

            foreach (EventSchedule defaultEvent in defaultEvents)
            {
                defaultEvent.InvokeRepeating(defaultEvent.RunSchedule, 1f, 1f);
                EventSchedule.allEvents.Add(defaultEvent);
            }
            
            defaultEvents.Clear();
            defaultEvents = null;
        }
        
                
        
                
        private void InitController()
        {
            Configuration.OvenController ovenController = config.ovenController;
            Configuration.MixingTableController mixingTableController = config.mixingTableController;
            Configuration.RecyclerController recyclerController = config.recyclerController;
            Configuration.RateBonusController rateBonusWipe = config.rateBonusController;
            
            config.rateController.controllerList.GenerationCasheControllerListRate();
            config.rateController.BuildCacheRate();
            
            if (config.rateBonusController.dayOfWeekBonusRated.useBonusDayOfWeek)
                config.rateBonusController.dayOfWeekBonusRated.BuildCache();
            
            if (!ovenController.useSpeedOven)
            {
                Unsubscribe(nameof(OnOvenStarted));
                Unsubscribe(nameof(OnOvenToggle));
            }
            else
            {
                permissionsOvensSorted.AddRange(ovenController.permissionList.OrderByDescending(p => p.rate));
                permissionsCharacoalChanceSorted.AddRange(ovenController.characoalChanceRate.characoalChancePermissions.OrderByDescending(c => c.chance));
            }
            
            if (config.rateBonusController.dayOfWeekBonusRated.useBonusDayOfWeek || config.rateBonusController.wipeBonusRated.useWipeBonus)
                timerUpdateBonusAllRate = timer.Once(300f, AutoUpdateBonusRate); 

            if (!mixingTableController.useSpeedMixingTable)
                Unsubscribe(nameof(OnMixingTableToggle));
            else permissionsMixingTablesSorted.AddRange(mixingTableController.permissionList.OrderByDescending(p => p.rate));    
            
            if (!recyclerController.speedRecycler.useRecyclerSpeed)
                Unsubscribe(nameof(OnRecyclerToggle));
            else permissionsSpeedRecyclerSorted.AddRange(recyclerController.speedRecycler.permissionRate.OrderByDescending(p => p.rate));
            
            if(!config.teaController.useTeaController)
                Unsubscribe(nameof(OnPlayerAddModifiers));
            
            if (!config.fuelTransportController.fuelPerSecTransport.useConsumedFuel && !config.fuelTransportController.fuelBuyingShopKeeper.useAutoFillFuel)
                Unsubscribe(nameof(OnEntitySpawned));
		   		 		  						  	   		   					  			 		   					  	 		
            if (rateBonusWipe.wipeBonusRated.useWipeBonus)
                permissionsWipeBonusSorted.AddRange(rateBonusWipe.wipeBonusRated.permissionRateBonus.OrderByDescending(p => p.rate));
        }
        private List<Configuration.PermissionsRateList> permissionsOvensSorted = new();
        private class EventController
        {
            public TriggeredEvent trigger;
            public Timer timerEvent;
        }
        
        private void OnLootEntity(BasePlayer player, BaseEntity targetEntity)
        {
            if (!targetEntity || targetEntity.net == null) return;
            
            if (!config.rateController.controllerList.IsConvetedPrefab(targetEntity.ShortPrefabName)) 
                return;
            
            UInt64 netID = targetEntity.net.ID.Value;
            if (!lootedEntity.Add(netID)) return;
            
            switch (targetEntity)
            {
                case LootableCorpse corpse:
                {
                    if (!corpse) return;
                    if (corpse.playerSteamID.IsSteamId()) return;
		   		 		  						  	   		   					  			 		   					  	 		
                    foreach (ItemContainer corpseContainer in corpse.containers)
                    {
                        foreach (Item item in corpseContainer.itemList)
                            ConvertRate(TypeConverted.Loot, player.UserIDString, item);
                    }

                    break;
                }
                case LootContainer lootContainer:
                {
                    if (!lootContainer) return;
                    foreach (Item item in lootContainer.inventory.itemList)
                        ConvertRate(TypeConverted.Loot, player.UserIDString, item);
                    break;
                }
            }
        }

        private void OnEntityKill(LootableCorpse corpse)
        {
            if (!corpse) return;
            if (corpse.net == null) return;
            if (corpse.playerSteamID.IsSteamId()) return;
		   		 		  						  	   		   					  			 		   					  	 		
            lootedEntity.Remove(corpse.net.ID.Value);
        }

                
        
        
        private class OvenController : FacepunchBehaviour
        {
            private BaseOven oven;
            private String ownerIDOven;
            private Boolean isElectical;
            
            private Single defaultSmeltingSpeed;
            private Single smeltingSpeed;
            private Int32 fuelRate; 
            private Int32 characoalRate;
            private Single characoalChance;
            
                    
            private void Awake()
            {
                oven = (BaseOven)gameObject.ToBaseEntity();
                defaultSmeltingSpeed = oven.smeltSpeed;
                smeltingSpeed = defaultSmeltingSpeed;
                ownerIDOven = oven.OwnerID.ToString();
                isElectical = oven is ElectricOven;
            }
        
            private void OnDestroy()
            {
                Boolean isOnStatus = oven.IsOn();
                StopCooking();
                SingletonComponent<NpcFireManager>.Instance.Remove(oven);

                oven.smeltSpeed = (Int32)defaultSmeltingSpeed;

                if (isOnStatus)
                    oven.StartCooking();
            }
            
                    
            private void UpdateModifiers()
            {
                Single playerRate = config.ovenController.GetOvenRate(ownerIDOven);
                Int32 cellingRate = (Int32)Math.Ceiling(playerRate);
                
                smeltingSpeed = defaultSmeltingSpeed * playerRate;
                fuelRate = cellingRate;
                characoalRate = cellingRate;
                characoalChance = config.ovenController.characoalChanceRate.GetCharacoalChanse(ownerIDOven);
            }
        
            public void Restart()
            {
                if (!oven.IsOn()) return;
                 UpdateModifiers();
                     
                 StartCooking();
            }
            
            public void SwitchOven(Boolean isIgnite = false)
            {
                Boolean shouldTurnOn = isElectical || isIgnite ? oven.IsOn() : !oven.IsOn();
		   		 		  						  	   		   					  			 		   					  	 		
                if (shouldTurnOn)
                    StartCooking();
                else StopCooking();
            }
            
            
            public void Cook()
            {
                if (!oven.HasFlag(BaseEntity.Flags.On))
                {
                    StopCooking();
                    return;
                }
                
                Item burnable = oven.FindBurnable();
                
                if (burnable == null && !isElectical)
                {
                    StopCooking();
                    return;
                }
		   		 		  						  	   		   					  			 		   					  	 		
                foreach (Item obj in oven.inventory.itemList)
                {
                    if (obj.position >= oven._inputSlotIndex && obj.position < oven._inputSlotIndex + oven.inputSlots && !obj.HasFlag(global::Item.Flag.Cooking))
                    {
                        obj.SetFlag(global::Item.Flag.Cooking, true);
                        obj.MarkDirty();
                    }
                }
                
                oven.IncreaseCookTime(0.5f * smeltingSpeed);
                
                BaseEntity slot = oven.GetSlot(BaseEntity.Slot.FireMod);
                if (slot) slot.SendMessage(nameof (Cook), 0.5f , SendMessageOptions.DontRequireReceiver);
                
                if (burnable != null && !isElectical)
                {
                    ItemModBurnable itemModBurnable = burnable.info.ItemModBurnable;
                    burnable.fuel -= (Single) (0.5 * (oven.cookingTemperature / 200.0));
                    
                    if (!burnable.HasFlag(global::Item.Flag.OnFire))
                    {
                        burnable.SetFlag(global::Item.Flag.OnFire, true);
                        burnable.MarkDirty();
                    }
        
                    if (burnable.fuel <= 0.0)
                        ConsumeFuel(burnable, itemModBurnable);
                }
            }
            
            public void ConsumeFuel(Item fuel, ItemModBurnable burnable)
            {
                for (Int32 i = 0; i < characoalRate; i++)
                {
                    if (oven.allowByproductCreation && burnable.byproductItem != null &&
                        UnityEngine.Random.Range(0.0f, 1f) > characoalChance)
                    {
                        Item obj = ItemManager.Create(burnable.byproductItem, burnable.byproductAmount);
                        if (!obj.MoveToContainer(oven.inventory))
                        {
                            StopCooking();
                            obj.Drop(oven.inventory.dropPosition, oven.inventory.dropVelocity);
                        }
                    }
                }
		   		 		  						  	   		   					  			 		   					  	 		
                if (fuel.amount <= fuelRate)
                    fuel.Remove();
                else
                {
                    fuel.UseItem(fuelRate);
                    fuel.fuel = burnable.fuelAmount;
                    fuel.MarkDirty();
                }
            }
        
                        
            public virtual void StartCooking()
            {
                UpdateModifiers();
                oven.SetFlag(BaseEntity.Flags.Reserved8, true);
                //oven.CancelInvoke(oven.Cook);
                oven.smeltSpeed = (Int32)smeltingSpeed;
                oven.inventory.temperature = oven.cookingTemperature;
                oven.UpdateAttachmentTemperature();
                
                InvokeRepeating(Cook, 0.5f, 0.5f);
                
                oven.SetFlag(BaseEntity.Flags.On, true);
            }
            
            public virtual void StopCooking()
            {
                CancelInvoke(Cook);
                oven.UpdateAttachmentTemperature();
                
                if (oven.inventory != null)
                {
                    oven.inventory.temperature = 15f;
                    foreach (Item obj in oven.inventory.itemList)
                    {
                        if (obj.HasFlag(global::Item.Flag.OnFire))
                        {
                            obj.SetFlag(global::Item.Flag.OnFire, false);
                            obj.MarkDirty();
                        }
                        else if (obj.HasFlag(global::Item.Flag.Cooking))
                        {
                            obj.SetFlag(global::Item.Flag.Cooking, false);
                            obj.MarkDirty();
                        }
                    }
                }
                
                oven.SetFlag(BaseEntity.Flags.On, false);
                oven.SetFlag(BaseEntity.Flags.Reserved8, false);
            }
        }
		   		 		  						  	   		   					  			 		   					  	 		
        
                
        private void OnQuarryToggled(MiningQuarry quarry, BasePlayer player) =>
            quarryToggled[quarry.net.ID.Value] = player.UserIDString;
        
        private void ChangeEventSchedule()
        {
            List<EventSchedule> removedEventSchedule = Pool.Get<List<EventSchedule>>();
            defaultEvents.AddRange(EventSchedule.allEvents);
            
            Configuration.EventController eventController = config.eventController;
            EventMapping(eventController);
            
            if (eventController.eventBradley.disableFullEvent)
            {
                defaultBradleyStatus = Bradley.enabled;
                Bradley.enabled = false;
                if (BradleySpawner.singleton && BradleySpawner.singleton.spawned)
                    BradleySpawner.singleton.spawned.Kill();
            }
            
            foreach (EventSchedule allEvent in EventSchedule.allEvents)
            {
                if (eventMapping.TryGetValue(allEvent.name, out (TypeEvent eventType, Configuration.EventController.EventTemplate eventConfig) eventInfo))
                {
                    (TypeEvent eventType, Configuration.EventController.EventTemplate eventConfig) = eventInfo;

                    if (!eventConfig.disableFullEvent && eventConfig.useEvent)
                    {
                        triggersEvents.TryAdd(eventType, new EventController()
                        {
                            trigger = allEvent.GetComponent<TriggeredEvent>(),
                            timerEvent = null,
                        });
                    }

                    if (eventConfig.disableFullEvent || eventConfig.useEvent)
                        removedEventSchedule.Add(allEvent);
                }
            }

            foreach (EventSchedule removeEvent in removedEventSchedule)
            {
                removeEvent.CancelInvoke(removeEvent.RunSchedule);
                EventSchedule.allEvents.Remove(removeEvent);
            }
            
            Pool.FreeUnmanaged(ref removedEventSchedule);
        }

        private void OnEntityKill(LootContainer lootContainer)
        {
            if (!lootContainer) return;
            if (lootContainer.net == null) return;
            lootedEntity.Remove(lootContainer.net.ID.Value);
        }
        
        
                
        private Object OnPlayerAddModifiers(BasePlayer player, Item item, ItemModConsumable consumable)
        {
            if (!teaModifers.TryGetValue(item.info.shortname, out ModiferTea teaLocal)) return null;
            Configuration.RateController.RateData playerRateData = config.rateController.GetRateData(player.UserIDString);
            if (playerRateData == null) return null;

            Configuration.RateController.RateData defaultRateData = IsDayTime() ? config.rateController.dayRate : config.rateController.nightRate;
            
            List<ModifierDefintion> mods = Pool.Get<List<ModifierDefintion>>();

            try
            {
                ProcessModifier(player.UserIDString, teaLocal, playerRateData, defaultRateData, mods);
                player.modifiers.Add(mods);
            }
            finally
            {
                mods.Clear();
                Pool.FreeUnmanaged(ref mods);
            }

            return true;
        }
        
                
        
        private static Configuration config = new Configuration();
        private void OnUserGroupRemoved(String id, String groupName) => RefreshPermissionPlayer(id);

        private void OnHour()
        {
            Single hour = TOD_Sky.Instance.Cycle.Hour; 
            Int32 dayStart = config.timeController.timeStartDay; 
            Int32 nightStart = config.timeController.timeStartNight; 
            
            if (hour >= dayStart && hour < nightStart)
            {
                if (!activatedDay) 
                    OnSunrise();
                
                SendDayNightMessages(hour, dayStart, nightStart);
                return;
            }

            if (hour < dayStart || hour >= nightStart)
            {
                if (activatedDay) 
                    OnSunset();
                
                SendDayNightMessages(hour, dayStart, nightStart);
            }
        }
        
        private TOD_Time timeComponent = null;
        private Timer timerUpdateBonusRate;
        
        private void Init()
        {
            _ = this;
            
            ReadData();

            InitController();
        }

        private void UnsubscribeFromEvents()
        {
            timeComponent.OnSunrise -= OnSunrise;
            timeComponent.OnSunset -= OnSunset;
            timeComponent.OnHour -= OnHour;
        }
		   		 		  						  	   		   					  			 		   					  	 		
        
        
        private void FuelFillingTransport(BaseVehicle vehicle)
        {
            if (!config.fuelTransportController.fuelBuyingShopKeeper.useAutoFillFuel) return;
            Configuration.FuelTransportController.FuelBuingShopKeeper fuelTransportController = config.fuelTransportController.fuelBuyingShopKeeper;

            IFuelSystem fuelSystem = vehicle.GetFuelSystem();
            if (fuelSystem is not EntityFuelSystem entityFuelSystem) return;

            Int32 fuelAmount = vehicle switch
            {
                ScrapTransportHelicopter => fuelTransportController.fuelScrapTransport,
                Minicopter => fuelTransportController.fuelMinicopter,
                AttackHelicopter => fuelTransportController.fuelAttackHelicopter,
                RHIB or MotorRowboat => fuelTransportController.fuelBoat,
                BaseSubmarine => fuelTransportController.fuelSubmarine,
                _ => 0
            };

            if (fuelAmount == 0) return;
            NextTick(() =>
            {
                Item Fuel = entityFuelSystem.GetFuelItem();
                if (Fuel == null) return;
                
                if (Fuel.amount is 50 or 100)
                    Fuel.amount = fuelAmount;
            });
        }
        protected override void SaveConfig() => Config.WriteObject(config);
        
        
        private void InitializeTimeComponent()
        {
            timeComponent = TOD_Sky.Instance.Components.Time;
            if (timeComponent == null) return;
            SpeedTimes();
            FreezeTimes();
        }
        private Timer timerBradley;
        private readonly List<Int64> itemDlcRemoved = new List<Int64>()
        {
            1545779598, -1335497659, -139037392, 139037392, 44605728, -194953424, -1478855279, 110116923, 857465230, -1315992997,
        };
        
        private void OnUserPermissionGranted(String id, String permName) => RefreshPermissionPlayer(id);
        /// <summary>
        /// - Теперь новые NPC Gen2 - будут аналогично поддерживать увеличение лута
        /// - Предотвращение возможного NRE в хуке OnEntityKill
        /// </summary>
        
        private const Boolean LanguageEn = false;
        private Boolean IsUsedEvents()
        {
            Configuration.EventController eventController = config.eventController;
            
            return eventController.eventHelicopter.disableFullEvent || eventController.eventHelicopter.useEvent
                || eventController.eventAirdrop.disableFullEvent || eventController.eventAirdrop.useEvent
                || eventController.eventCh47.disableFullEvent || eventController.eventCh47.useEvent
                || eventController.eventBradley.disableFullEvent || eventController.eventBradley.useEvent
                || eventController.eventCargoShip.disableFullEvent || eventController.eventCargoShip.useEvent;
        }
        private List<Configuration.PermissionsRateList> permissionsMixingTablesSorted = new();

        private enum TypeList
        {
            Disabled,
            WhiteList,
            BlackList,
        }

        private Object OnQuarryGathered(MiningQuarry quarry, Item item)
        {
            if (itemDlcRemoved.Contains(item.info.itemid)) return false;
            return null;
        }

        private void SendChat(String Message, BasePlayer player, Chat.ChatChannel channel = Chat.ChatChannel.Global)
        {
            if (IQChat && config.referencePlugins.iqchatReference.useIQChat)
                IQChat.Call("API_ALERT_PLAYER", player, Message, config.referencePlugins.iqchatReference.prefixChat, config.referencePlugins.iqchatReference.customAvatar);
            else player.SendConsoleCommand("chat.add", channel, 0, Message);
        }

                
                
        private void OnRecyclerToggle(Recycler recycler, BasePlayer player)
        {
            if (recycler.IsOn() || recycler.OwnerID.IsSteamId()) return;
            Single speedRecycler = config.recyclerController.speedRecycler.GetSpeedRecycler(player);
            Single recyclerTime = recycler.GetRecycleThinkDuration() / speedRecycler;

            NextTick(() =>
            {
                recycler.CancelInvoke(recycler.RecycleThink);
                recycler.InvokeRepeating(recycler.RecycleThink, recyclerTime, recyclerTime);
            });
        }

        
                
        private void OnContainerDropItems(ItemContainer container)
        {
            LootContainer lootContainer = container.entityOwner as LootContainer;
            if (!lootContainer) return;
            UInt64 netID = lootContainer.net.ID.Value;
            if (lootedEntity.Contains(netID)) return;
            
            BasePlayer player = lootContainer.lastAttacker as BasePlayer;
            if (!player) return;
            
            foreach (Item item in container.itemList)
                ConvertRate(TypeConverted.Loot, player.UserIDString, item);
        }

        private void OnSunrise()
        {
            Single currentHour = TOD_Sky.Instance.Cycle.Hour;
            Int32 dayStart = config.timeController.timeStartDay; 
            Int32 nightStart = config.timeController.timeStartNight; 

            if (currentHour < dayStart || currentHour >= nightStart) 
                return;

            activatedDay = true;

            Single dayLength = config.timeController.passageTime.minutesDay;
            timeComponent.DayLengthInMinutes = dayLength * (24.0f / (nightStart - dayStart));
        }

        private Boolean activatedDay;
        private Dictionary<TypeEvent, EventController> triggersEvents = new Dictionary<TypeEvent, EventController>();
        private Timer timerUpdateBonusAllRate;
		   		 		  						  	   		   					  			 		   					  	 		
        protected override void LoadDefaultConfig() => config = Configuration.GetNewConfiguration();
        private List<Configuration.OvenController.CharacoalSetting.PermissionsChanceList> permissionsCharacoalChanceSorted = new(); 
        
        private void UpdateBonusRate(String userID)
        {
            Single bonusRate = config.rateBonusController.GetBonusRate(userID);
            if (!bonusRatesPlayer.TryAdd(userID, bonusRate))
                bonusRatesPlayer[userID] = bonusRate;
        }
        
        private Single GetBonusRate(String userID) => bonusRatesPlayer.GetValueOrDefault(userID, 0);
        
                
        private void SpeedTimes()
        {
            Configuration.TimeController timeController = config.timeController;
            if (!timeController.passageTime.useTimeSpeed) return;

            Int32 dayStart = timeController.timeStartDay;
            Int32 nightStart = timeController.timeStartNight;

            UpdateDayNightState(TOD_Sky.Instance.Cycle.Hour, dayStart, nightStart);
		   		 		  						  	   		   					  			 		   					  	 		
            timeComponent.ProgressTime = true;
            timeComponent.UseTimeCurve = false;

            UnsubscribeFromEvents();
            SubscribeToEvents();

            if (TOD_Sky.Instance.Cycle.Hour > dayStart && TOD_Sky.Instance.Cycle.Hour < nightStart)
                OnSunrise();
            else
                OnSunset();
        }
        private Boolean sendMessageNight;
        
                
        private void FreezeTimes()
        {
            Configuration.TimeController timeController = config.timeController;
            if (!timeController.freezeTime.useTimeFreeze) return;
            if (timeController.passageTime.useTimeSpeed)
            {
                _.PrintWarning(LanguageEn ? "Unable to freeze time, please disable time acceleration in the configuration!" : "Невозможно заморозить время, выключите ускорение времени в конфигурации!");
                return;
            }
                        
            timeComponent.ProgressTime = false;
            ConVar.Env.time = timeController.freezeTime.timeFreeze;
        }
        
        private void OnPlayerConnected(BasePlayer player)
        {
            if (config.rateBonusController.dayOfWeekBonusRated.useBonusDayOfWeek || config.rateBonusController.wipeBonusRated.useWipeBonus)
                UpdateBonusRate(player.UserIDString);
            
            GenerateCacheRatePlayer(player);
        }
        private List<Configuration.PermissionsRateList> permissionsWipeBonusSorted = new();
        private void OnUserPermissionRevoked(String id, String permName) => RefreshPermissionPlayer(id);
        
        private void RefreshPermissionPlayer(String id)
        {
            BasePlayer player = BasePlayer.Find(id);
            if (player == null) return;
		   		 		  						  	   		   					  			 		   					  	 		
            GenerateCacheRatePlayer(player);
        }

        
        
        private void OnDispenserGather(ResourceDispenser resource, BasePlayer player, Item item) => ConvertRate(TypeConverted.Gather, player.UserIDString, item);

        
        
        private Item OnFishCatch(Item item, BaseFishingRod rod, BasePlayer player)
        {
            ConvertRate(TypeConverted.Fishing, player.UserIDString, item);
            return null;
        }
        private void WriteData()
        {
            Oxide.Core.Interface.Oxide.DataFileSystem.WriteObject("IQSystem/IQRates/LootedContainers", lootedEntity);
            Oxide.Core.Interface.Oxide.DataFileSystem.WriteObject("IQSystem/IQRates/QuarryToggled", quarryToggled);
        }
        
        
                
        private Double GetCarry(String userID, TypeConverted type, Int32 itemId)
        {
            if (_carry.TryGetValue(userID, out Dictionary<TypeConverted, Dictionary<Int32, Double>> byType) &&
                byType.TryGetValue(type, out Dictionary<Int32, Double> byItem) &&
                byItem.TryGetValue(itemId, out Double c))
                return c;
            return 0d;
        }

        private void UpdateDayNightState(Single hour, Int32 dayStart, Int32 nightStart)
        {
            Boolean isDay = hour >= dayStart && hour < nightStart;
		   		 		  						  	   		   					  			 		   					  	 		
            sendMessageDay = isDay;
            sendMessageNight = !isDay;
        }

        private void BroadcastRatesAlert(String langKey)
        {
            foreach (BasePlayer player in BasePlayer.activePlayerList)
            {
                Configuration.RateController.RateData rateData = config.rateController.GetRateData(player.UserIDString);
                if (rateData == null)
                    continue;

                Single bonusRate = GetBonusRate(player.UserIDString);

                String messageInfo = GetLang("INFO_MY_RATE", player.UserIDString, rateData.gatherRate, rateData.lootRate,
                    rateData.pickUpRate, rateData.growableRate, rateData.quarryRate.GetQuarryRate(0),
                    rateData.excavatorRate, rateData.fishRate);

                if (bonusRate != 0)
                    messageInfo += GetLang("INFO_MY_RATE_BONUS", player.UserIDString, bonusRate);

                SendChat(GetLang(langKey, player.UserIDString, messageInfo), player);
            }        
        }

        private Dictionary<String, PlayerRateCache> rateDataCache = new();
        
        private Timer initializeTimeComponentTimer;
		   		 		  						  	   		   					  			 		   					  	 		
        
        
        private void OnCollectiblePickedup(CollectibleEntity collectible, BasePlayer player, Item item) => ConvertRate(TypeConverted.PickUP, player.UserIDString, item);
        
        private class ModiferTea
        {
            public Modifier.ModifierType type;
            public Single value;
            public Single duration;
        }

                
                
        private void OnOvenToggle(BaseOven oven, BasePlayer player)
        {
            if (oven is BaseFuelLightSource)
                return;

            if (config.ovenController.blackListOvenPrefab.Contains(oven.ShortPrefabName))
                return;
            
            Item burnable = oven.FindBurnable();
            if (burnable == null) return;
            
            OvenController controller = GetOrAddOvenController(oven);
            controller.SwitchOven();
        }
        private Object OnExcavatorGather(ExcavatorArm arm, Item item)
        {
            if (!excavatorUsePlayer) return null;
            ConvertRate(TypeConverted.Excavator, excavatorUsePlayer.UserIDString, item);
            return null;
        }
        private Dictionary<BaseOven, OvenController> ovenCotrollers = new Dictionary<BaseOven, OvenController>();
        private void OnDispenserBonus(ResourceDispenser resource, BasePlayer player, Item item) => ConvertRate(TypeConverted.Gather, player.UserIDString, item);
        private Dictionary<UInt64, String> quarryToggled = new Dictionary<UInt64, String>();
		   		 		  						  	   		   					  			 		   					  	 		
        
        
        private void InitializeEvents()
        {
            RunEvent(TypeEvent.Airdrop, true);
            RunEvent(TypeEvent.Bradley, true);
            RunEvent(TypeEvent.CargoShip, true);
            RunEvent(TypeEvent.Ch47, true);
            RunEvent(TypeEvent.PatrolHelicopter, true);
        }

                
                
        private HashSet<UInt64> lootedEntity = new HashSet<UInt64>();
        private Boolean sendMessageDay;
        
        private void SubscribeToEvents()
        {
            timeComponent.OnSunrise += OnSunrise;
            timeComponent.OnSunset += OnSunset;
            timeComponent.OnHour += OnHour;
        }

                
        
        
        private void OnUserGroupAdded(String id, String groupName) => RefreshPermissionPlayer(id);

        private Int32 GetTimeEvent(TypeEvent eventType)
        {
            Configuration.EventController eventController = config.eventController;
            Configuration.EventController.EventTemplate.SpawnController spawnControllerEvent = eventType switch
            {
                TypeEvent.Bradley => eventController.eventBradley.spawnController,
                TypeEvent.Airdrop => eventController.eventAirdrop.spawnController,
                TypeEvent.CargoShip => eventController.eventCargoShip.spawnController,
                TypeEvent.Ch47 => eventController.eventCh47.spawnController,
                TypeEvent.PatrolHelicopter => eventController.eventHelicopter.spawnController,
                _ => throw new ArgumentOutOfRangeException(nameof(eventType), eventType, null)
            };

            return spawnControllerEvent.GetSpawnTimeSecods();
        }
        
        
        
        private void OnGrowableGathered(GrowableEntity plant, Item item, BasePlayer player) => ConvertRate(TypeConverted.Growable, player.UserIDString, item);
        
        private enum TypeConverted
        {
            Gather,
            Loot,
            PickUP,
            Quarry,
            Excavator,
            Growable,
            Fishing
        }
        
        
                
        private void OnServerInitialized()
        {
            InitializeOvens();
		   		 		  						  	   		   					  			 		   					  	 		
            Configuration.TimeController timeController = config.timeController;
            if (timeController.passageTime.useTimeSpeed || timeController.freezeTime.useTimeFreeze)
                initializeTimeComponentTimer = timer.Once(5f, InitializeTimeComponent);
            
            if (IsUsedEvents())
            {
                ChangeEventSchedule();
                InitializeEvents();
            }

            if (config.rateBonusController.dayOfWeekBonusRated.useBonusDayOfWeek)
                config.rateBonusController.dayOfWeekBonusRated.UpdateBonusStatus(true);
            
            foreach (Configuration.PermissionsRateList permissionOven in permissionsOvensSorted)
                if (!permission.PermissionExists(permissionOven.permission, this))
                    permission.RegisterPermission(permissionOven.permission, this);
            
            foreach (Configuration.OvenController.CharacoalSetting.PermissionsChanceList permissionCharacoal in permissionsCharacoalChanceSorted)
                if (!permission.PermissionExists(permissionCharacoal.permission, this))
                    permission.RegisterPermission(permissionCharacoal.permission, this);
            
            foreach (Configuration.PermissionsRateList permissionMixingTable in permissionsMixingTablesSorted)
                if (!permission.PermissionExists(permissionMixingTable.permission, this))
                    permission.RegisterPermission(permissionMixingTable.permission, this);
            
            foreach (Configuration.PermissionsRateList permissionRecycler in permissionsSpeedRecyclerSorted)
                if (!permission.PermissionExists(permissionRecycler.permission, this))
                    permission.RegisterPermission(permissionRecycler.permission, this);
            
            foreach (Configuration.PermissionsRateList permissionWipeBonus in permissionsWipeBonusSorted)
                if (!permission.PermissionExists(permissionWipeBonus.permission, this))
                    permission.RegisterPermission(permissionWipeBonus.permission, this);
            
            foreach (KeyValuePair<String, Configuration.RateController.RateData> permissionRateDay in config.rateController.permissionRateDataDay)
            {
                if (!permission.PermissionExists(permissionRateDay.Key, this))
                    permission.RegisterPermission(permissionRateDay.Key, this);
            }
            
            foreach (KeyValuePair<String, Configuration.RateController.RateData> permissionRateNight in config.rateController.permissionRateDataNight)
            {
                if (!permission.PermissionExists(permissionRateNight.Key, this))
                    permission.RegisterPermission(permissionRateNight.Key, this);
            }
            
            foreach (Configuration.RateBonusController.RateControllerDayOfWeek.RateBonusDays permissionDayOfWeek in config.rateBonusController.dayOfWeekBonusRated.rateBonusDayOfWeek)
            {
                foreach (Configuration.PermissionsRateList dayOfWeek in permissionDayOfWeek.permissionRateBonus)
                    if (!permission.PermissionExists(dayOfWeek.permission, this))
                        permission.RegisterPermission(dayOfWeek.permission, this);
            }

            foreach (BasePlayer player in BasePlayer.activePlayerList)
                OnPlayerConnected(player);
        }

        private void EventMapping(Configuration.EventController eventController)
        {
            eventMapping.TryAdd("assets/bundled/prefabs/world/event_cargoheli.prefab", new ValueTuple<TypeEvent, Configuration.EventController.EventTemplate>(TypeEvent.Ch47, eventController.eventCh47));
            eventMapping.TryAdd("assets/bundled/prefabs/world/event_helicopter.prefab", new ValueTuple<TypeEvent, Configuration.EventController.EventTemplate>(TypeEvent.PatrolHelicopter, eventController.eventHelicopter));
            eventMapping.TryAdd("assets/bundled/prefabs/world/event_cargoship.prefab", new ValueTuple<TypeEvent, Configuration.EventController.EventTemplate>(TypeEvent.CargoShip, eventController.eventCargoShip));
            eventMapping.TryAdd("assets/bundled/prefabs/world/event_airdrop.prefab", new ValueTuple<TypeEvent, Configuration.EventController.EventTemplate>(TypeEvent.Airdrop, eventController.eventAirdrop));
        }
        
        
        
        private void GenerateCacheRatePlayer(BasePlayer player)
        {
            Configuration.RateController.RateData dayRate = null;
            Configuration.RateController.RateData nightRate = null;
            
            foreach (KeyValuePair<String, Configuration.RateController.RateData> permissionDays in config.rateController.permissionRateDataDay)
            {
                if (!_.permission.UserHasPermission(player.UserIDString, permissionDays.Key)) continue;
                dayRate = permissionDays.Value;
                break;
            }

            dayRate ??= config.rateController.dayRate;
            
            foreach (KeyValuePair<String, Configuration.RateController.RateData> permissionNight in config.rateController.permissionRateDataNight)
            {
                if (!_.permission.UserHasPermission(player.UserIDString, permissionNight.Key)) continue;
                nightRate = permissionNight.Value;
                break;
            }

            nightRate ??= config.rateController.nightRate;

            rateDataCache[player.UserIDString] = new PlayerRateCache { dayRate = dayRate, nightRate = nightRate };
        }

        private void ProcessModifier(String userID, ModiferTea tea, Configuration.RateController.RateData playerRateData, Configuration.RateController.RateData defaultRateData, List<ModifierDefintion> mods)
        {
            Single defaultRate = tea.type switch
            {
                Modifier.ModifierType.Ore_Yield => defaultRateData.gatherRate,
                Modifier.ModifierType.Wood_Yield => defaultRateData.gatherRate,
                Modifier.ModifierType.Scrap_Yield => defaultRateData.gatherRate,
                _ => throw new InvalidOperationException("Unknown modifier type")
            };

            Single playerRate = tea.type switch
            {
                Modifier.ModifierType.Ore_Yield => playerRateData.gatherRate,
                Modifier.ModifierType.Wood_Yield => playerRateData.gatherRate,
                Modifier.ModifierType.Scrap_Yield => playerRateData.lootRate,
                _ => throw new InvalidOperationException("Unknown modifier type")
            };
            
            Single bonusRate = GetBonusRate(userID);
            playerRate += bonusRate;

            Single modifierDifference = CalculateModifierDifference(defaultRate, playerRate);
            mods.Add(CreateModifier(tea, modifierDifference));
        }
        
        private void RunEvent(TypeEvent eventType, Boolean isInit = false)
        {
            Int32 randomTimeEvent = GetTimeEvent(eventType);
            if (eventType == TypeEvent.Bradley)
            {
                if (config.eventController.eventBradley.disableFullEvent || !config.eventController.eventBradley.useEvent) return;
                BradleySpawner bradleySpawner = BradleySpawner.singleton;
                if (!bradleySpawner)
                {
                    if (timerBradley is { Destroyed: false })
                    {
                        timerBradley.Destroy();
                        timerBradley = null;
                    }
                    return;
                }
                
                if (!isInit)
                {
                    if (!bradleySpawner.spawned.isSpawned)
                    {
                        Bradley.enabled = true;
                        bradleySpawner.SpawnBradley();
                        Bradley.enabled = false;
                    }
                }
                
                if (timerBradley is { Destroyed: false })
                {
                    timerBradley.Destroy();
                    timerBradley = null;
                }
                
                timerBradley = timer.Once(randomTimeEvent, () => RunEvent(eventType));
                return;
            }

            if (!triggersEvents.TryGetValue(eventType, out EventController triggerEvent))
                return;
            
            if (!isInit)
                triggerEvent.trigger.RunEvent();
            
            if (triggerEvent.timerEvent is { Destroyed: false })
            {
                triggerEvent.timerEvent.Destroy();
                triggerEvent.timerEvent = null;
            }
		   		 		  						  	   		   					  			 		   					  	 		
            triggerEvent.timerEvent = timer.Once(randomTimeEvent, () => RunEvent(eventType));
        }


        private void SendDayNightMessages(Single hour, Int32 dayStart, Int32 nightStart)
        {
            if (config.timeController.passageTime.skipNight || !config.timeController.passageTime.alertTimeController) return;

            if (!sendMessageDay && sendMessageNight && Mathf.Abs(hour - dayStart) <= 0.1f)
            {
                BroadcastRatesAlert("DAY_RATES_ALERT");
                sendMessageDay = true;
                sendMessageNight = false;
            }
            else if (!sendMessageNight && sendMessageDay && Mathf.Abs(hour - nightStart) <= 0.1f)
            {
                BroadcastRatesAlert("NIGHT_RATES_ALERT");
                sendMessageNight = true;
                sendMessageDay = false;
            }
        }
        private enum TypeEvent
        {
            Bradley,
            Airdrop,
            CargoShip,
            Ch47,
            PatrolHelicopter,
        }
		   		 		  						  	   		   					  			 		   					  	 		
        
        
        private ModifierDefintion GetDefintionModifer(Modifier.ModifierType type, Single duration, Single value)
        {
            return new ModifierDefintion
            {
                source = Modifier.ModifierSource.Tea,
                type = type,
                duration = duration,
                value = value <= 0 ? 1.0f : value
            };
        }
        
        private Boolean IsDayTime() => TOD_Sky.Instance.Cycle.Hour >= config.timeController.timeStartDay && TOD_Sky.Instance.Cycle.Hour < config.timeController.timeStartNight;

        
        
        private void OnExcavatorResourceSet(ExcavatorArm arm, String resourceName, BasePlayer player) => excavatorUsePlayer = player;

        private void AutoUpdateBonusRate()
        {
            foreach (BasePlayer player in BasePlayer.activePlayerList)
                UpdateBonusRate(player.UserIDString);
        }

        
        
        private void OnEntitySpawned(BaseVehicle vehicle)
        {
            FuelFillingTransport(vehicle);
            FuelPerSecController(vehicle);
        }
        private readonly Dictionary<String, ModiferTea> teaModifers = new Dictionary<String, ModiferTea>
        {
            { "oretea.advanced", new ModiferTea { duration = 1800f, value = 0.35f, type = Modifier.ModifierType.Ore_Yield } },
            { "oretea", new ModiferTea { duration = 1800f, value = 0.2f, type = Modifier.ModifierType.Ore_Yield } },
            { "oretea.pure", new ModiferTea { duration = 1800f, value = 0.5f, type = Modifier.ModifierType.Ore_Yield } },
            { "woodtea.advanced", new ModiferTea { duration = 1800f, value = 1.0f, type = Modifier.ModifierType.Wood_Yield } },
            { "woodtea", new ModiferTea { duration = 1800f, value = 0.5f, type = Modifier.ModifierType.Wood_Yield } },
            { "woodtea.pure", new ModiferTea { duration = 1800f, value = 2.0f, type = Modifier.ModifierType.Wood_Yield } },
            { "scraptea.advanced", new ModiferTea { duration = 2700f, value = 2.25f, type = Modifier.ModifierType.Scrap_Yield } },
            { "scraptea", new ModiferTea { duration = 1800f, value = 1.0f, type = Modifier.ModifierType.Scrap_Yield } },
            { "scraptea.pure", new ModiferTea { duration = 3600f, value = 3.5f, type = Modifier.ModifierType.Scrap_Yield } }
        };
        
        private void OnGroupPermissionRevoked(String name, String perm)
        {
            foreach (BasePlayer player in BasePlayer.activePlayerList)
                RefreshPermissionPlayer(player.UserIDString);
        }
        private Dictionary<String, (TypeEvent eventType, Configuration.EventController.EventTemplate eventConfig)> eventMapping = new();
        
                
                
        private String GetLang(in String LangKey, in String userID = null, params Object[] args)
        {
            if (args == null) 
                return lang.GetMessage(LangKey, this, userID);

            StringBuilder sb = Pool.Get<StringBuilder>();

            try
            {
                sb.AppendFormat(lang.GetMessage(LangKey, this, userID), args);
                return sb.ToString();
            }
            finally
            {
                sb.Clear();
                Pool.FreeUnmanaged(ref sb);
            }
        }
        
        private void FuelPerSecController(BaseVehicle vehicle)
        {
            if (!config.fuelTransportController.fuelPerSecTransport.useConsumedFuel) return;

            Configuration.FuelTransportController.FuelPerSecTransport fuelPerSecController = config.fuelTransportController.fuelPerSecTransport;

            switch (vehicle)
            {
                case ScrapTransportHelicopter scrapTransportHelicopter:
                    scrapTransportHelicopter.fuelPerSec *= fuelPerSecController.consumedScrapTransport;
                    break;
                case Minicopter minicopter:
                    minicopter.fuelPerSec *= fuelPerSecController.consumedCopter;
                    break;
                case AttackHelicopter attackHelicopter:
                    attackHelicopter.fuelPerSec *= fuelPerSecController.consumedAttackHelicopter;
                    break;
                case RHIB rhib:
                    rhib.fuelPerSec *= fuelPerSecController.consumedBoat;
                    break;
                case MotorRowboat boat:
                    boat.fuelPerSec *= fuelPerSecController.consumedBoat;
                    break;
                case BaseSubmarine submarine:
                    submarine.maxFuelPerSec *= fuelPerSecController.consumedSubmarine;
                    break;
                case Snowmobile snowmobile:
                    snowmobile.maxFuelPerSec *= fuelPerSecController.consumedSnowmobile;
                    break;
                case TrainCar trainCar:
                {
                    StorageContainer fuelContainer = (trainCar.GetFuelSystem() as EntityFuelSystem)?.GetFuelContainer();
                    if (!fuelContainer) return;
                    if (!fuelContainer.TryGetComponent(out TrainEngine trainEngine)) return;
                    trainEngine.maxFuelPerSec *= fuelPerSecController.consumedTrain;
                    break;
                }
            }
        }
        
        private void OnQuarryGather(MiningQuarry quarry, Item item)
        {
            if (!quarryToggled.TryGetValue(quarry.net.ID.Value, out String playerUserIDString))
            {
                if(quarry.OwnerID.IsSteamId())
                    playerUserIDString = quarry.OwnerID.ToString();
                else return;
            }
            
            ConvertRate(TypeConverted.Quarry, playerUserIDString, item);
        }

                
                private readonly Dictionary<String, Dictionary<TypeConverted, Dictionary<Int32, Double>>> _carry = new(StringComparer.Ordinal);
        
       // private Int32 wipeLeftHourse;
        
        private BasePlayer excavatorUsePlayer = null;

        private Configuration.RateBonusController.RateControllerDayOfWeek.RateBonusDays rateBonusDay;
        private static IQRates _;
        
        private void OnEntitySpawned(HotAirBalloon balloon)
        {
            if (!config.fuelTransportController.fuelPerSecTransport.useConsumedFuel) return;
            balloon.fuelPerSec *= config.fuelTransportController.fuelPerSecTransport.consumedHotAirBalloon;
        }
        
        private OvenController GetOrAddOvenController(BaseOven oven)
        {
            if (ovenCotrollers.TryGetValue(oven, out OvenController controller)) return controller;
            controller = oven.gameObject.AddComponent<OvenController>();
            ovenCotrollers.TryAdd(oven, controller);
            return controller;
        
        }
        private List<Configuration.PermissionsRateList> permissionsSpeedRecyclerSorted = new();

                
        
        private void InitializeOvens()
        {
            if (!config.ovenController.useSpeedOven) return;
            
            List<BaseNetworkable> networkables = Pool.Get<List<BaseNetworkable>>();
            networkables.AddRange(BaseNetworkable.serverEntities.entityList.Get().Values);
            
            foreach (BaseNetworkable entity in networkables)
            {
                if (entity is not BaseOven oven)
                    continue;
                
                if(!oven) continue;
                
                OvenController controller = GetOrAddOvenController(oven);
                controller.Restart();
            }
            
            Pool.FreeUnmanaged(ref networkables);
        }
       
        protected override void LoadConfig()
        {
            base.LoadConfig();
            try
            {
                config = Config.ReadObject<Configuration>();
                if (config == null) LoadDefaultConfig();

                if (config.rateController.dayRate.rateCorrectly == null)
                {
                    config.rateController.dayRate.rateCorrectly = new Dictionary<String, Single>()
                    {
                        ["shortname"] = 2.0f,
                        ["shortname2"] = 2.0f,
                    };
                }
                
                if (config.rateController.nightRate.rateCorrectly == null)
                {
                    config.rateController.nightRate.rateCorrectly = new Dictionary<String, Single>()
                    {
                        ["shortname"] = 2.0f,
                        ["shortname2"] = 2.0f,
                    };
                }

                foreach (KeyValuePair<String, Configuration.RateController.RateData> pRateDay in config.rateController
                             .permissionRateDataDay)
                {
                    if (pRateDay.Value.rateCorrectly == null)
                    {
                        pRateDay.Value.rateCorrectly = new Dictionary<String, Single>()
                        {
                            ["shortname"] = 2.0f,
                            ["shortname2"] = 2.0f,
                        };
                    }
                }
                
                foreach (KeyValuePair<String, Configuration.RateController.RateData> pRateNight in config.rateController
                             .permissionRateDataNight)
                {
                    if (pRateNight.Value.rateCorrectly == null)
                    {
                        pRateNight.Value.rateCorrectly = new Dictionary<String, Single>()
                        {
                            ["shortname"] = 2.0f,
                            ["shortname2"] = 2.0f,
                        };
                    }
                }

                if (config.ovenController.characoalChanceRate == null)
                {
                    config.ovenController.characoalChanceRate = new Configuration.OvenController.CharacoalSetting
                    {
                        characoalChanceDefault = 75,
                        characoalChancePermissions = new List<Configuration.OvenController.CharacoalSetting.PermissionsChanceList>()
                        {
                            new Configuration.OvenController.CharacoalSetting.PermissionsChanceList()
                            {
                                permission = "iqrates.premium",
                                chance = 100,
                            },
                            new Configuration.OvenController.CharacoalSetting.PermissionsChanceList()
                            {
                                permission = "iqrates.vip",
                                chance = 75,
                            }
                        }
                    };

                    if (config.ovenController.characoalChanceRate.characoalChanceDefault is 0 or 50)
                        config.ovenController.characoalChanceRate.characoalChanceDefault = 75;

                    if (config.ovenController.characoalChanceRate.characoalChancePermissions.Count == 0)
                        config.ovenController.characoalChanceRate.characoalChancePermissions =
                            new List<Configuration.OvenController.CharacoalSetting.PermissionsChanceList>()
                            {
                                new Configuration.OvenController.CharacoalSetting.PermissionsChanceList()
                                {
                                    permission = "iqrates.premium",
                                    chance = 100,
                                },
                                new Configuration.OvenController.CharacoalSetting.PermissionsChanceList()
                                {
                                    permission = "iqrates.vip",
                                    chance = 75,
                                }
                            };

                    if (config.rateController.dayRate.rateCorrectly == null)
                        config.rateController.dayRate.rateCorrectly = new Dictionary<String, Single>()
                        {
                            ["shortname"] = 2.0f,
                        };
                    
                    if (config.rateController.nightRate.rateCorrectly == null)
                        config.rateController.nightRate.rateCorrectly = new Dictionary<String, Single>()
                        {
                            ["shortname"] = 2.0f,
                        };

                    foreach (KeyValuePair<String, Configuration.RateController.RateData> dayP in config.rateController.permissionRateDataDay)
                    {
                        if (dayP.Value.rateCorrectly == null)
                            dayP.Value.rateCorrectly = new Dictionary<String, Single>()
                            {
                                ["shortname"] = 2.0f,
                            };
                    }
                    
                    foreach (KeyValuePair<String, Configuration.RateController.RateData> nightP in config.rateController.permissionRateDataNight)
                    {
                        if (nightP.Value.rateCorrectly == null)
                            nightP.Value.rateCorrectly = new Dictionary<String, Single>()
                            {
                                ["shortname"] = 2.0f,
                            };
                    }
                }
            }
            catch
            {                       
                PrintWarning(LanguageEn ? "Error #334332143" + $"read configuration 'oxide/config/{Name}', create a new configuration!!" : "Ошибка #334343" + $"чтения конфигурации 'oxide/config/{Name}', создаём новую конфигурацию!!"); 
                LoadDefaultConfig();
            }
            NextTick(SaveConfig);
        }
        
        private void OnOvenStarted(BaseOven oven)
        {
            if (oven is BaseFuelLightSource)
                return;
            
            if (config.ovenController.blackListOvenPrefab.Contains(oven.ShortPrefabName))
                return;
            
            //if(oven is not ElectricOven) return;
            OvenController controller = GetOrAddOvenController(oven);
            controller.SwitchOven(true);
        }

        private void SetCarry(String userID, TypeConverted type, Int32 itemId, Double value)
        {
            if (!_carry.TryGetValue(userID, out var byType))
                _carry[userID] = byType = new Dictionary<TypeConverted, Dictionary<Int32, Double>>();
            if (!byType.TryGetValue(type, out var byItem))
                byType[type] = byItem = new Dictionary<Int32, Double>();
            byItem[itemId] = value;
        }
        private List<EventSchedule> defaultEvents = new List<EventSchedule>();

        private void OnSunset()
        {
            Single currentHour = TOD_Sky.Instance.Cycle.Hour;
            Int32 dayStart = config.timeController.timeStartDay; 
            Int32 nightStart = config.timeController.timeStartNight; 

            if (currentHour >= dayStart && currentHour < nightStart) 
                return;
            
            activatedDay = false;
            
            if (config.timeController.passageTime.skipNight) 
            {
                TOD_Sky.Instance.Cycle.Hour = dayStart;
                return;
            }

            Single nightLength = config.timeController.passageTime.minutesNight;
            timeComponent.DayLengthInMinutes = nightLength * (24.0f / (24.0f - (nightStart - dayStart)));
        }

                
        
        
        [ChatCommand("rates")]
        private void GetInfoMyRates(BasePlayer player)
        {
            if (!player) return;
            Configuration.RateController.RateData rateData = config.rateController.GetRateData(player.UserIDString);
            if (rateData == null)
                return;
            
            Single bonusRate = GetBonusRate(player.UserIDString);
            
            String messageInfo = GetLang("INFO_MY_RATE", player.UserIDString, rateData.gatherRate, rateData.lootRate,
                rateData.pickUpRate, rateData.growableRate, rateData.quarryRate.GetQuarryRate(0),
                rateData.excavatorRate, rateData.fishRate);

            if (bonusRate != 0)
                messageInfo += GetLang("INFO_MY_RATE_BONUS", player.UserIDString, bonusRate);
                    
            SendChat(messageInfo, player);
        }
        private class Configuration
        {
            [JsonProperty(LanguageEn ? "Time data settings" : "Настройка данных о времени")]
            public TimeController timeController = new TimeController();
            [JsonProperty(LanguageEn ? "Plugin rate settings" : "Настройка рейтов плагина")]
            public RateController rateController = new RateController();
            [JsonProperty(LanguageEn ? "Bonus rate settings" : "Настройка бонусных рейтов")]
            public RateBonusController rateBonusController = new RateBonusController();
            [JsonProperty(LanguageEn ? "Furnace settings" : "Настройка печей")]
            public OvenController ovenController = new OvenController();
            [JsonProperty(LanguageEn ? "Tea mixing speed settings [Data is specified in %, from 0 to 100]" : "Настройка скорости смешивания чая [Данные указываются в %, от 0 до 100]")]
            public MixingTableController mixingTableController = new MixingTableController();
            [JsonProperty(LanguageEn ? "Tea influence on rate increase settings" : "Настройка влияния чая на увеличения рейтов")]
            public TeaController teaController = new TeaController();
            [JsonProperty(LanguageEn ? "Server events settings" : "Настройка ивентов сервера")]
            public EventController eventController = new EventController();
            [JsonProperty(LanguageEn ? "Recycler settings" : "Настройка переработчиков")]
            public RecyclerController recyclerController = new RecyclerController();
            [JsonProperty(LanguageEn ? "Transport fuel settings" : "Настройка топлива в транспорте")]
            public FuelTransportController fuelTransportController = new FuelTransportController();
            [JsonProperty(LanguageEn ? "Additional plugins settings" : "Настройка дополнительных плагинов")]
            public ReferencePlugins referencePlugins = new ReferencePlugins();
            
            internal class RateController
            {
                [JsonProperty(LanguageEn ? "Exclusion/Rate adjustment list settings" : "Настройка списков исключений/корретировки рейтов")]
                public ControllerListRate controllerList = new ControllerListRate();
                [JsonProperty(LanguageEn ? "Day rates" : "Дневные рейты")]
                public RateData dayRate = new RateData();
                [JsonProperty(LanguageEn ? "Night rates" : "Ночные рейты")]
                public RateData nightRate = new RateData();
                [JsonProperty(LanguageEn ? "Day rate list by permissions [Permission = Day rate settings]" : "Список дневных рейтов по разрешениям [Разрешение = Настройка дневных рейтов]")]
                public Dictionary<String, RateData> permissionRateDataDay = new Dictionary<String, RateData>();
                [JsonProperty(LanguageEn ? "Night rate list by permissions [Permission = Night rate settings]" : "Список ночных рейтов по разрешениям [Разрешение = Настройка ночных рейтов]")]
                public Dictionary<String, RateData> permissionRateDataNight = new Dictionary<String, RateData>();
                
                public RateData GetRateData(String userID)
                {
                    if (!_.rateDataCache.TryGetValue(userID, out PlayerRateCache cache))
                        return dayRate;
                    
                    return _.IsDayTime() ? cache.dayRate : cache.nightRate;
                }

                public void BuildCacheRate()
                {
                    dayRate.BuildCacheQuarry();
                    nightRate.BuildCacheQuarry();
                    foreach (RateData pDayRate in permissionRateDataDay.Values)
                        pDayRate.BuildCacheQuarry();
                    
                    foreach (RateData pNightRate in permissionRateDataNight.Values)
                        pNightRate.BuildCacheQuarry();
                }

                internal class ControllerListRate
                {
                    [JsonProperty(LanguageEn ? "Blacklist or whitelist settings" : "Настройка черного или белого списка")]
                    public ItemLists itemLists = new ItemLists();
                    [JsonProperty(LanguageEn ? "Category blacklist (From F1 -> Items) to which rates will not apply [Leave empty if not needed]" : "Черный список категорий (Из F1 -> Items) на которые не будут действовать рейты [Если не нужно - оставьте пустым]")]
                    public List<String> blackListCategory = new List<String>();
                    [JsonProperty(LanguageEn ? "Prefab blacklist (shortPrefabName) to which rates will not apply [Leave empty if not needed]" : "Черный список префабов (shortPrefabName) на которые не будут действовать рейты [Если не нужно - оставьте пустым]")]
                    public HashSet<String> blackListPrefabs = new HashSet<String>();
                    [JsonProperty(LanguageEn ? "Blacklist of items by SkinID that the rates will not apply to [If not needed, leave blank]" : "Черный список предметов с SkinID на которые не будут действовать рейты [Если не нужно - оставьте пустым]")]
                    public HashSet<UInt64> blackListSkinIds = new HashSet<UInt64>();
                    internal class ItemLists
                    {
                        [JsonProperty(LanguageEn ? "List type: 0 - Disabled, 1 - Whitelist, 2 - Blacklist" : "Тип списка : 0 - Выключено, 1 - Белый список, 2 - Черный список")]
                        public TypeList typeList;
                        [JsonProperty(LanguageEn ? "Item blacklist (shortname) to which rates will not apply" : "Черный список предметов (shortname) на которые не будут дейстовать рейты")]
                        public List<String> blackListShortname = new List<String>();
                        [JsonProperty(LanguageEn ? "Item whitelist (shortname) to which rates will apply, excluding other items" : "Белый список предметов (shortname) на которые будут дейстовать рейты, исключая другие предметы")]
                        public List<String> whiteListShortname = new List<String>();
                        
                        [JsonIgnore]
                        public HashSet<Int32> whiteListItemIds = new HashSet<Int32>();
                        [JsonIgnore]
                        public HashSet<Int32> blackListItemIds = new HashSet<Int32>();
                    }
		   		 		  						  	   		   					  			 		   					  	 		
                    [JsonIgnore]
                    public HashSet<ItemCategory> blackListCategoryEnums = new HashSet<ItemCategory>();
		   		 		  						  	   		   					  			 		   					  	 		
                    public void GenerationCasheControllerListRate()
                    {
                        if (blackListCategory != null && blackListCategory.Count != 0)
                        {
                            foreach (String blackCategory in blackListCategory)
                            {
                                if (!Enum.TryParse(blackCategory, out ItemCategory itemCategory))
                                {
                                    _.PrintError(LanguageEn
                                        ? $"Invalid item category in 'Category BlackList'. Value: '{blackCategory}'"
                                        : $"Некорректная категория предмета в 'Черном списке категорий'. Значение: '{blackCategory}'");
                                    continue;
                                }

                                blackListCategoryEnums.Add(itemCategory);
                            }
                        }
                        else blackListCategoryEnums = null;

                        if (itemLists.blackListShortname != null && itemLists.blackListShortname.Count != 0)
                        {
                            foreach (String blackShortname in itemLists.blackListShortname)
                            {
                                if (blackShortname.Contains("shortname")) continue;
                                ItemDefinition itemInfo = ItemManager.FindItemDefinition(blackShortname);
                                if (!itemInfo)
                                {
                                    _.PrintError(LanguageEn
                                        ? $"Invalid item shortname in 'BlackList (shortname)'. Value: '{blackShortname}'"
                                        : $"Некорректный shortname предмета в 'Черном списке предметов'. Значение: '{blackShortname}'");
                                    continue;
                                }

                                itemLists.blackListItemIds.Add(itemInfo.itemid);
                            }
                        }
                        else itemLists.blackListItemIds = null;

                        if (itemLists.whiteListShortname != null && itemLists.whiteListShortname.Count != 0)
                        {
                            foreach (String whiteShortname in itemLists.whiteListShortname)
                            {
                                if (whiteShortname.Contains("shortname")) continue;
                                ItemDefinition itemInfo = ItemManager.FindItemDefinition(whiteShortname);
                                if (!itemInfo)
                                {
                                    _.PrintError(LanguageEn
                                        ? $"Invalid item shortname in 'WhihiteList (shortname)'. Value: '{whiteShortname}'"
                                        : $"Некорректный shortname предмета в 'Белом списке предметов'. Значение: '{whiteShortname}'");
                                    continue;
                                }

                                itemLists.whiteListItemIds.Add(itemInfo.itemid);
                            }
                        }
                        else itemLists.whiteListItemIds = null;
                    }
                    
                    public Boolean IsConvertedItem(Item item) => IsConvertedItem(item.info, item.skin);
                    public Boolean IsConvertedItem(ItemDefinition item, UInt64 skinId = 0)
                    {
                        if (skinId != 0 && blackListSkinIds.Contains(skinId)) return false;

                        if (blackListCategoryEnums != null && blackListCategoryEnums.Contains(item.category)) 
                            return false;

                        return itemLists.typeList switch
                        {
                            TypeList.WhiteList => itemLists.whiteListItemIds != null && itemLists.whiteListItemIds.Contains(item.itemid),
                            TypeList.BlackList => itemLists.blackListItemIds == null || !itemLists.blackListItemIds.Contains(item.itemid),
                            _ => true
                        };
                    }
                    public Boolean IsConvetedPrefab(String prefabName) => !blackListPrefabs.Contains(prefabName);
                }
                
                internal class RateData
                {
                    [JsonProperty(LanguageEn ? "Gathered items rates" : "Рейты добываемых предметов")]
                    public Single gatherRate;
                    [JsonProperty(LanguageEn ? "Found items rates" : "Рейты найденных предметов")]
                    public Single lootRate;
                    [JsonProperty(LanguageEn ? "Picked-up items rates" : "Рейты поднятых с земли предметов")]
                    public Single pickUpRate;
                    [JsonProperty(LanguageEn ? "Harvested items rates" : "Рейты поднятых с грядки предметов")]
                    public Single growableRate;
                    [JsonProperty(LanguageEn ? "Excavator output rates" : "Рейты добычи экскаватора")]
                    public Single excavatorRate;
                    [JsonProperty(LanguageEn ? "Fishing catch rates" : "Рейты пойманных с моря предметов (рыбалка)")]
                    public Single fishRate;
                    [JsonProperty(LanguageEn ? "Quarry rates settings" : "Настройка рейтов карьеров")]
                    public QuarryController quarryRate = new QuarryController();
                    [JsonProperty(LanguageEn ? "Setting individual rates. [Shortname] = [Rate]. This is a priority list, and the rates for items in this list will override all other settings." : "Настройка отдельных рейтов. [Shortname] = [Rate]. Это приоритетный список, рейты к предметам из него будут главнее всех других настроек.")]
                    public Dictionary<String, Single> rateCorrectly = new Dictionary<String, Single>();
                    
                    [JsonIgnore]
                    public readonly Dictionary<Int32, Single> rateCorrectlyCache = new Dictionary<Int32, Single>();
                    
                    public Single GetCorrectlyRate(Int32 itemId) => rateCorrectlyCache.GetValueOrDefault(itemId, 0);
                    
                    internal class QuarryController
                    {
                        [JsonProperty(LanguageEn ? "Quarry output rates" : "Рейты добычи карьера")]
                        public Single quarryRate;
                        [JsonProperty(LanguageEn ? "Detailed quarry output rates [shortname] - Rate" : "Детальные рейты добычи карьера [shortname] - Рейт")]
                        public Dictionary<String, Single> detailRateQuarry = new Dictionary<String, Single>();

                        [JsonIgnore]
                        public readonly Dictionary<Int32, Single> detailRateCache = new Dictionary<Int32, Single>();
                        
                        public Single GetQuarryRate(Int32 itemID) => detailRateCache.GetValueOrDefault(itemID, quarryRate);
                    }

                    public void BuildCacheQuarry()
                    {
                        foreach (KeyValuePair<String, Single> rCorrectly in rateCorrectly)
                        {
                            if (rCorrectly.Key.Contains("shortname")) continue;

                            ItemDefinition itemInfo = ItemManager.FindItemDefinition(rCorrectly.Key);
                            if (!itemInfo)
                            {
                                _.PrintError(LanguageEn
                                    ? $"Invalid item shortname in 'Setting individual rates'. Value: '{rCorrectly.Key}'"
                                    : $"Некорректный shortname предмета в 'Настройке отдельных рейтов карьера'. Значение: '{rCorrectly.Key}'");
                                continue;
                            }
		   		 		  						  	   		   					  			 		   					  	 		
                            if (!rateCorrectlyCache.TryAdd(itemInfo.itemid, rCorrectly.Value))
                            {
                                _.PrintError(LanguageEn
                                    ? $"Duplicate found in 'Setting individual rates'. Value: '{rCorrectly.Key}'"
                                    : $"Обнаружен дубликат в 'Настройке отдельных рейтов карьера'. Значение: '{rCorrectly.Key}'");
                                continue;
                            }
                        }

                        foreach (KeyValuePair<String, Single> dRate in quarryRate.detailRateQuarry)
                        {
                            if (dRate.Key.Contains("shortname")) continue;

                            ItemDefinition itemInfo = ItemManager.FindItemDefinition(dRate.Key);
                            if (!itemInfo)
                            {
                                _.PrintError(LanguageEn
                                    ? $"Invalid item shortname in 'Detailed quarry output rates'. Value: '{dRate.Key}'"
                                    : $"Некорректный shortname предмета в 'Детальные рейты добычи карьер'. Значение: '{dRate.Key}'");
                                continue;
                            }
                            
                            if (!quarryRate.detailRateCache.TryAdd(itemInfo.itemid, dRate.Value))
                            {
                                _.PrintError(LanguageEn
                                    ? $"Duplicate found in 'Detailed quarry output rates'. Value: '{dRate.Key}'"
                                    : $"Обнаружен дубликат в 'Детальные рейты добычи карьер'. Значение: '{dRate.Key}'");
                                continue;
                            }
                        }
                    }
                }
            }

            internal class RateBonusController
            {
                [JsonProperty(LanguageEn ? "Settings for bonus increase of all rates after a wipe" : "Настройка бонусного увеличения всех рейтов после вайпа")]
                public WipeBonusRated wipeBonusRated = new WipeBonusRated();
                [JsonProperty(LanguageEn ? "Settings for bonus increase of all rates by days of the week and time" : "Настройка бонусного увеличения всех рейтов по дням недели и времени")]
                public RateControllerDayOfWeek dayOfWeekBonusRated = new RateControllerDayOfWeek();
                
                internal class WipeBonusRated
                {
                    [JsonProperty(LanguageEn ? "Enable bonus rate increase after a wipe [true - yes/false - no]" : "Использовать бонусное увеличение рейтов после вайпа [true - да/false - нет]")]
                    public Boolean useWipeBonus;
                    [JsonProperty(LanguageEn ? "Number of hours the bonus will be active after a wipe" : "Сколько часов будет действовать бонус после вайпа")]
                    public Int32 hourseActiveBonus;
                    [JsonProperty(LanguageEn ? "Bonus rate" : "Бонус к рейтам")]
                    public Single defaultRate;
                    [JsonProperty(LanguageEn ? "Bonus rates by permissions" : "Бонус к рейтам по разрешениям")]
                    public List<PermissionsRateList> permissionRateBonus = new List<PermissionsRateList>();
                    
                    public Boolean IsActiveBonusWipeTime() => useWipeBonus && DateTime.UtcNow.Date.Subtract(SaveRestore.SaveCreatedTime.Date).TotalHours < hourseActiveBonus;
                    
                    public Single GetWipeBonus(String userID)
                    {
                        foreach (PermissionsRateList permissionsWipeBonus in _.permissionsWipeBonusSorted)
                        {
                            if (_.permission.UserHasPermission(userID, permissionsWipeBonus.permission))
                                return permissionsWipeBonus.rate;
                        }

                        return defaultRate;
                    }
                }

                internal class RateControllerDayOfWeek
                {
                    [JsonProperty(LanguageEn ? "Enable bonus increase of all rates by days of the week" : "Использовать бонусное увеличение всех рейтов по дням недели")]
                    public Boolean useBonusDayOfWeek;
                    [JsonProperty(LanguageEn ? "List of days of the week and times with increased rates [Do not allow date overlap, as it may cause conflicts]" : "Список дней недели и времени с увеличенными рейтами [Не допускайте пересечение дат, иначе это может вызвать конфликт]")]
                    public List<RateBonusDays> rateBonusDayOfWeek = new();

                    [JsonIgnore] private RateBonusDays[] _weekHourMap; 
                    
                    public void BuildCache()
                    {
                        _weekHourMap = new RateBonusDays[7 * 24];

                        for (Int32 i = 0; i < rateBonusDayOfWeek.Count; i++)
                        {
                            RateBonusDays w = rateBonusDayOfWeek[i];

                            if (!Enum.TryParse(w.timeStartBonus.dayOfWeek, ignoreCase: true, out DayOfWeek sd) ||
                                !Enum.TryParse(w.timeStopBonus.dayOfWeek, ignoreCase: true, out DayOfWeek ed))
                            {
                                _.PrintError(LanguageEn
                                    ? $"Invalid DayOfWeek in config: '{w.timeStartBonus.dayOfWeek}' -> '{w.timeStopBonus.dayOfWeek}'"
                                    : $"Некорректный день недели в конфиге: '{w.timeStartBonus.dayOfWeek}' -> '{w.timeStopBonus.dayOfWeek}'");
                                continue; 
                            }
                            
                            w.startDay = sd;
                            w.stopDay  = ed;
                            w.startHour = w.timeStartBonus.timeHours;
                            w.stopHour  = w.timeStopBonus.timeHours;

                            if (w.permissionRateBonus is { Count: > 0 })
                            {
                                PermissionsRateList[] arr = w.permissionRateBonus.ToArray();
                                Array.Sort(arr, (a, b) => b.rate.CompareTo(a.rate));
                                w.permissionRateBonusSorted = arr;
                            }
                            else
                            {
                                w.permissionRateBonusSorted = Array.Empty<PermissionsRateList>();
                            }

                            Int32 start = ((Int32)w.startDay) * 24 + w.startHour;
                            Int32 stop  = ((Int32)w.stopDay) * 24 + w.stopHour;

                            if ((UInt32)start >= 168 || (UInt32)stop >= 168)
                            {
                                _.PrintError(LanguageEn
                                    ? $"Invalid hours range. Start={w.startDay} {w.startHour}, Stop={w.stopDay} {w.stopHour}. Expected hours 0..23."
                                    : $"Некорректный диапазон часов. Start={w.startDay} {w.startHour}, Stop={w.stopDay} {w.stopHour}. Ожидаются часы 0..23.");
                                continue;
                            }

                            void SetRange(Int32 from, Int32 toInclusive)
                            {
                                for (Int32 h = from; h <= toInclusive; h++)
                                {
                                    if (_weekHourMap[h] != null && _weekHourMap[h] != w)
                                    {
                                        _.PrintError(LanguageEn
                                            ? $"Overlap detected at weekHour={h}. Existing window will be kept."
                                            : $"Обнаружено пересечение окон в weekHour={h}. Будет использовано ранее заданное окно.");
                                        continue; 
                                    }
                                    _weekHourMap[h] = w;
                                }
                            }

                            if (start <= stop)
                                SetRange(start, stop);
                            else
                            {
                                SetRange(start, 167);
                                SetRange(0, stop);
                            }
                        }
                    }
                    
                    internal class RateBonusDays
                    {
                        [JsonProperty(LanguageEn ? "Bonus rate" : "Бонус к рейтам")]
                        public Single defaultRate;
                        [JsonProperty(LanguageEn ? "Bonus rates by permissions" : "Бонус к рейтам по разрешениям")]
                        public List<PermissionsRateList> permissionRateBonus = new List<PermissionsRateList>();
                        [JsonProperty(LanguageEn ? "Bonus start settings" : "Настройки запуска бонуса")]
                        public TimeController timeStartBonus = new();
                        [JsonProperty(LanguageEn ? "Bonus stop settings" : "Настройки завершения бонуса")]
                        public TimeController timeStopBonus = new();

                        internal class TimeController
                        {
                            [JsonProperty(LanguageEn ? "Time in hours [1-24]" : "Время в часах [1-24]")]
                            public Int32 timeHours;
                            [JsonProperty(LanguageEn ? "Day of the week: Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday" : "День недели : Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday")]
                            public String dayOfWeek;
                        }

                        [JsonIgnore] public DayOfWeek startDay;
                        [JsonIgnore] public DayOfWeek stopDay;
                        [JsonIgnore] public Int32 startHour;
                        [JsonIgnore] public Int32 stopHour;
                        [JsonIgnore] public PermissionsRateList[] permissionRateBonusSorted;
                    }
                    
                    public void UpdateBonusStatus(Boolean isInit = false)
                    {
                        RateBonusDays currentRateBonusDay = GetRateBonusForCurrentTime();
    
                        if (currentRateBonusDay != _.rateBonusDay || isInit)
                        {
                            _.rateBonusDay = currentRateBonusDay;
		   		 		  						  	   		   					  			 		   					  	 		
                            if (currentRateBonusDay != null)
                            {
                                String startDayOfWeek = currentRateBonusDay.timeStartBonus.dayOfWeek.ToUpper();
                                String stopDayOfWeek = currentRateBonusDay.timeStopBonus.dayOfWeek.ToUpper();
                    
                                foreach (BasePlayer player in BasePlayer.activePlayerList)
                                {
                                    _.SendChat(_.GetLang("RATE_BONUS_DAY_OF_WEEK", player.UserIDString, GetDayOfWeekBonus(player.UserIDString), 
                                        _.GetLang(startDayOfWeek, player.UserIDString), $"{currentRateBonusDay.timeStartBonus.timeHours}:00",
                                        _.GetLang(stopDayOfWeek, player.UserIDString), $"{currentRateBonusDay.timeStopBonus.timeHours}:00"), player);
                                }
                            }
                            else
                            {
                                foreach (BasePlayer player in BasePlayer.activePlayerList)
                                    _.SendChat(_.GetLang("RATE_BONUS_DAY_OF_WEEK_END", player.UserIDString), player);
                            }
                        }

                        _.timerUpdateBonusRate = _.timer.Once(300f, () => UpdateBonusStatus());
                    }

                    public Single GetDayOfWeekBonus(String userID)
                    {
                        RateBonusDays window = GetRateBonusForCurrentTime();
                        if (window == null) return 0f;

                        PermissionsRateList[] sorted = window.permissionRateBonusSorted;
                        for (Int32 i = 0; i < sorted.Length; i++)
                        {
                            PermissionsRateList p = sorted[i];
                            if (_.permission.UserHasPermission(userID, p.permission))
                                return p.rate;
                        }

                        return window.defaultRate;
                    }

                    
                    private RateBonusDays GetRateBonusForCurrentTime()
                    {
                        DateTime now = DateTime.UtcNow;
                        Int32 idx = ((Int32)now.DayOfWeek) * 24 + now.Hour;
                        return _weekHourMap[idx];
                    }
                }

                public Single GetBonusRate(String userID)
                {
                    Single bonusRate = 0f;
                    if (wipeBonusRated.IsActiveBonusWipeTime())
                        bonusRate += wipeBonusRated.GetWipeBonus(userID);
                    
                    if(dayOfWeekBonusRated.useBonusDayOfWeek)
                        bonusRate += dayOfWeekBonusRated.GetDayOfWeekBonus(userID);

                    return bonusRate;
                }
            }
            
            internal class TimeController
            {
                [JsonProperty(LanguageEn ? "Server day start time [0-23]" : "Время начала дня на сервере [0-23]")]
                public Int32 timeStartDay;
                [JsonProperty(LanguageEn ? "Server night start time [0-23]" : "Время начала ночи на сервере [0-23]")]
                public Int32 timeStartNight;
                [JsonProperty(LanguageEn ? "Time acceleration settings [Note: Acceleration may cause standard events to appear faster, use custom event settings]" : "Настройка ускорения времени [Учтите, от ускорения стандартные ивенты могут появляться быстрее, используйте кастомную настройку ивентов]")]
                public PassageTime passageTime = new PassageTime();
                [JsonProperty(LanguageEn ? "Time freeze settings [Note: Freezing time may prevent standard events from appearing, use custom event settings]" : "Настройка заморозки времени [Учтите, от заморозки времени стандартные ивенты могут не появляться, используйте кастомную настройку ивентов]")]
                public FreezeTime freezeTime = new FreezeTime();
		   		 		  						  	   		   					  			 		   					  	 		
                internal class PassageTime
                {
                    [JsonProperty(LanguageEn ? "Use instant night skip [true - yes/false - no]" : "Использовать моментальный пропуск ночи [true - да/false - нет]")]
                    public Boolean skipNight;
                    [JsonProperty(LanguageEn ? "Use chat notification for day-night change" : "Использовать уведомление в чате о смене времени суток")]
                    public Boolean alertTimeController;
                    [JsonProperty(LanguageEn ? "Use time acceleration [true - yes/false - no]" : "Использовать ускорение времени [true - да/false - нет]")]
                    public Boolean useTimeSpeed;
                    [JsonProperty(LanguageEn ? "Number of minutes in a day" : "Сколько минут будет длиться день")]
                    public Int32 minutesDay;
                    [JsonProperty(LanguageEn ? "Number of minutes in a night" : "Сколько минут будет длиться ночь")]
                    public Int32 minutesNight;
                }
		   		 		  						  	   		   					  			 		   					  	 		
                internal class FreezeTime
                {
                    [JsonProperty(LanguageEn ? "Use time freeze [true - yes/false - no]" : "Использовать заморозку времени [true - да/false - нет]")]
                    public Boolean useTimeFreeze;
                    [JsonProperty(LanguageEn ? "Frozen time [0-23]" : "Замороженное время [0-23]")]
                    public Int32 timeFreeze;
                }
            }
		   		 		  						  	   		   					  			 		   					  	 		
            internal class EventController
            {
                [JsonProperty(LanguageEn ? "Cargo ship event settings" : "Настройка грузового корабля")]
                public EventTemplate eventCargoShip = new EventTemplate();
                [JsonProperty(LanguageEn ? "Bradley tank event settings" : "Настройка танка")]
                public EventTemplate eventBradley = new EventTemplate();
                [JsonProperty(LanguageEn ? "Patrol helicopter event settings" : "Настройка патрульного вертолета")]
                public EventTemplate eventHelicopter = new EventTemplate();
                [JsonProperty(LanguageEn ? "Chinook event settings" : "Настройка чинука")]
                public EventTemplate eventCh47 = new EventTemplate();
                [JsonProperty(LanguageEn ? "Airdrop event settings" : "Настройка аирдропа")]
                public EventTemplate eventAirdrop = new EventTemplate();
                
                internal class EventTemplate
                {
                    [JsonProperty(LanguageEn ? "Use custom settings for this event [true - yes/false - no]" : "Использовать собственные настройки для данного ивента [true - да/false - нет]")]
                    public Boolean useEvent;
                    [JsonProperty(LanguageEn ? "Completely disable this event [true - yes/false - no]" : "Полностью выключить данный ивент [true - да/false - нет]")]
                    public Boolean disableFullEvent;
                    [JsonProperty(LanguageEn ? "Event spawn settings" : "Настройка появления ивента")]
                    public SpawnController spawnController = new SpawnController();
                
                    internal class SpawnController
                    {
                        [JsonProperty(LanguageEn ? "Timer type: true - Static, false - Random" : "Тип таймера : true - Статичный, false - Рандомный")]
                        public Boolean staticTimeOrRandom;
                        [JsonProperty(LanguageEn ? "Static event spawn time" : "Статичное время появления ивента")]
                        public Int32 staticTime;
                        [JsonProperty(LanguageEn ? "Random event spawn time settings" : "Настройка случайного времени появления ивента")]
                        public RandomTime randomTimePreset = new RandomTime();
                        
                        internal class RandomTime
                        {
                            [JsonProperty(LanguageEn ? "Minimum time" : "Минимальное время")]
                            public Int32 minTime;
                            [JsonProperty(LanguageEn ? "Maximum time" : "Максимальное время")]
                            public Int32 maxTime;
                        
                            public Int32 GetRandomSpawnTime() => Oxide.Core.Random.Range(minTime, maxTime);
                        }

                        public Int32 GetSpawnTimeSecods() => staticTimeOrRandom ? staticTime : randomTimePreset.GetRandomSpawnTime();
                    }
                }
            }

            internal class MixingTableController
            {
                [JsonProperty(LanguageEn ? "Use tea mixing speed acceleration [true - yes/false - no]" : "Использовать ускорение смешивания чая [true - да/false - нет]")]
                public Boolean useSpeedMixingTable;
                [JsonProperty(LanguageEn ? "Default acceleration [Specify in %, from 0 to 100]" : "Стандартное ускорение [Указывайте в %, от 0 до 100]")]
                public Single defaultRate;
                [JsonProperty(LanguageEn ? "Acceleration by permissions [Specify in %, from 0 to 100]" : "Ускорение по разрешениям [Указывайте в %, от 0 до 100]")]
                public List<PermissionsRateList> permissionList = new List<PermissionsRateList>();
                
                public Single GetSpeeedMixingTable(BasePlayer player)
                {
                    foreach (PermissionsRateList permissionsMixingTable in _.permissionsMixingTablesSorted)
                    {
                        if (_.permission.UserHasPermission(player.UserIDString, permissionsMixingTable.permission))
                            return 1.0f - (permissionsMixingTable.rate / 100.0f);
                    }

                    return 1.0f - (defaultRate / 100.0f);
                }
            }

            internal class TeaController
            {
                [JsonProperty(LanguageEn ? "Enable tea controller [true - yes/false - no]. Supports scrap, ore, and wood tea. If enabled, the bonus to gathering will be calculated based on the difference between standard and privileged rates." : "Включить контроллер чая [true - да/false - нет]. Поддерживает скрап, рудный и древесный чай. Если включено, бонус к добыче будет рассчитываться на основе разницы стандартных и привилегированных рейтов.")]
                public Boolean useTeaController;
            }

            internal class FuelTransportController
            {
                [JsonProperty(LanguageEn ? "Standard amount of fuel in transport when purchased from NPC" : "Настройка стандартного количества топлива в транспорте при покупке его у NPC")]
                public FuelBuingShopKeeper fuelBuyingShopKeeper = new FuelBuingShopKeeper();
                [JsonProperty(LanguageEn ? "Fuel consumption rates in transport" : "Настройка рейтов потребления топлива в транспорте")]
                public FuelPerSecTransport fuelPerSecTransport = new FuelPerSecTransport();
                
                internal class FuelBuingShopKeeper
                {
                    [JsonProperty(LanguageEn ? "Use change in standard fuel amount in transport when purchased from NPC [true - yes/false - no]" : "Использовать изменение стандартнтого кол-во топлива в транспорте при покупке у NPC [true - да/false - нет]")]
                    public Boolean useAutoFillFuel;
                    [JsonProperty(LanguageEn ? "Fuel amount in boats" : "Кол-во топлива в лодках")]
                    public Int32 fuelBoat;
                    [JsonProperty(LanguageEn ? "Fuel amount in submarines" : "Кол-во топлива в субмаринах")]
                    public Int32 fuelSubmarine;
                    [JsonProperty(LanguageEn ? "Fuel amount in minicopters" : "Кол-во топлива в миникоптере")]
                    public Int32 fuelMinicopter;
                    [JsonProperty(LanguageEn ? "Fuel amount in cargo helicopter" : "Кол-во топлива в грузовом вертолете")]
                    public Int32 fuelScrapTransport;
                    [JsonProperty(LanguageEn ? "Fuel amount in attack helicopter" : "Кол-во топлива в боевом вертолете")]
                    public Int32 fuelAttackHelicopter;
                }
		   		 		  						  	   		   					  			 		   					  	 		
                internal class FuelPerSecTransport
                {
                    [JsonProperty(LanguageEn ? "Use change in standard fuel consumption in transport [true - yes/false - no]" : "Использовать изменениеи стандартного потребления топлива в транспорте [true - да/false - нет]")]
                    public Boolean useConsumedFuel;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in hot air balloon" : "Рейт потребления топлива в воздушном шаре")]
                    public Single consumedHotAirBalloon;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in snowmobile" : "Рейт потребления топлива в снегоходе")]
                    public Single consumedSnowmobile;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in train" : "Рейт потребления топлива в поезде")]
                    public Single consumedTrain;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in boats" : "Рейт потребления топлива в лодках")]
                    public Single consumedBoat;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in submarines" : "Рейт потребления топлива в субмаринах")]
                    public Single consumedSubmarine;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in minicopter" : "Рейт потребления топлива в миникоптере")]
                    public Single consumedCopter;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in cargo helicopter" : "Рейт потребления топлива в грузовом вертолете")]
                    public Single consumedScrapTransport;
                    [JsonProperty(LanguageEn ? "Fuel consumption rate in attack helicopter" : "Рейт потребления топлива в боевеом вертолете")]
                    public Single consumedAttackHelicopter;
                }
            }
            
            internal class RecyclerController
            {
                [JsonProperty(LanguageEn ? "Settings for item recycling speed in recyclers" : "Настройка ускорения переработки предметов в переработчиках")]
                public SpeedRecycler speedRecycler = new SpeedRecycler();
                
                internal class SpeedRecycler
                {
                    [JsonProperty(LanguageEn ? "Use item recycling speed acceleration in recyclers [true - yes/false - no]" : "Использовать ускорение переработки предметов в переработчиках [true - да/false - нет]")]
                    public Boolean useRecyclerSpeed;
                    [JsonProperty(LanguageEn ? "Recycling speed rate" : "Рейт ускорения для переработки")]
                    public Single defaultRate;
                    [JsonProperty(LanguageEn ? "Recycling speed rate by permissions" : "Рейт ускорения для переработки по разрешениям")]
                    public List<PermissionsRateList> permissionRate = new List<PermissionsRateList>();
                    
                    public Single GetSpeedRecycler(BasePlayer player)
                    {
                        foreach (PermissionsRateList permissionsRecycler in _.permissionsSpeedRecyclerSorted)
                        {
                            if (_.permission.UserHasPermission(player.UserIDString, permissionsRecycler.permission))
                                return permissionsRecycler.rate;
                        }

                        return defaultRate;
                    }
                }
            }
            
            internal class OvenController
            {
                [JsonProperty(LanguageEn ? "Use oven speed acceleration [true - yes/false - no]" : "Использовать ускорение в печах [true - да/false - нет]")]
                public Boolean useSpeedOven;
                [JsonProperty(LanguageEn ? "Oven speed rate" : "Рейт ускорения печей")]
                public Single defaultRate;
                [JsonProperty(LanguageEn ? "Oven speed rate by permissions" : "Рейт ускорения печей по разрешениям")]
                public List<PermissionsRateList> permissionList = new List<PermissionsRateList>();
                [JsonProperty(LanguageEn ? "Blacklist of oven prefabs on which speed acceleration will not apply" : "Черный список префабов печей на которые не будет действовать ускорение")]
                public HashSet<String> blackListOvenPrefab = new HashSet<String>(StringComparer.Ordinal);
                [JsonProperty(LanguageEn ? "Configuration of charcoal drop chance when burning wood in furnaces" : "Настройка шанса выпадения угля при сжигании дерева в печах")]
                public CharacoalSetting characoalChanceRate = new CharacoalSetting();

                internal class CharacoalSetting
                {
                    [JsonProperty(LanguageEn ? "The chance of charcoal dropping from burning wood (0-100%) [Default in the game is 75%]." : "Шанс выпадения угля с сжигания древесины (0 - 100%) [Стандартно в игре - 75%]")]
                    public Int32 characoalChanceDefault;

                    [JsonProperty(LanguageEn ? "The chance of charcoal dropping from burning wood by permissions (0-100%) [Default in the game is 75%]. [Permission] = Chance" : "Шанс выпадения угля с сжигания древесины по разрешениям (0 - 100%) [Стандартно в игре - 75%]. [Разрешение] = Шанс")]
                    public List<PermissionsChanceList> characoalChancePermissions = new List<PermissionsChanceList>();
                    
                    internal class PermissionsChanceList
                    {
                        [JsonProperty(LanguageEn ? "Permission" : "Разрешение")]
                        public String permission;
                        [JsonProperty(LanguageEn ? "Chance" : "Шанс")]
                        public Single chance;
                    }
                    
                    public Single GetCharacoalChanse(String userID)
                    {
                        foreach (PermissionsChanceList permissionsCharacoal in _.permissionsCharacoalChanceSorted)
                        {
                            if (_.permission.UserHasPermission(userID, permissionsCharacoal.permission))
                                return 1.0f - (permissionsCharacoal.chance / 100f);
                        }

                        return 1.0f - (characoalChanceDefault / 100f);
                    }
                }
                
                public Single GetOvenRate(String userID)
                {
                    foreach (PermissionsRateList permissionsOven in _.permissionsOvensSorted)
                    {
                        if (_.permission.UserHasPermission(userID, permissionsOven.permission))
                            return permissionsOven.rate;
                    }

                    return defaultRate;
                }
            }

            internal class ReferencePlugins
            {
                [JsonProperty(LanguageEn ? "IQChat settings" : "Настройка IQChat")]
                public IQChat iqchatReference = new IQChat();

                internal class IQChat
                {
                    [JsonProperty(LanguageEn ? "Use IQChat" : "Использовать IQChat")]
                    public Boolean useIQChat;
                    [JsonProperty(LanguageEn ? "Avatar for IQChat [Steam64ID]" : "Аватарка для IQChat [Steam64ID]")]
                    public String customAvatar;
                    [JsonProperty(LanguageEn ? "Prefix for IQChat" : "Префикс для IQChat")]
                    public String prefixChat;
                }
            }
            
            internal class PermissionsRateList
            {
                [JsonProperty(LanguageEn ? "Permission" : "Разрешение")]
                public String permission;
                [JsonProperty(LanguageEn ? "Rate" : "Рейт")]
                public Single rate;
            }
            
            public static Configuration GetNewConfiguration()
            {
                return new Configuration
                {
                    timeController = new TimeController
                    {
                        timeStartDay = 9,
                        timeStartNight = 22,
                        passageTime = new TimeController.PassageTime
                        {
                            skipNight = false,
                            useTimeSpeed = true,
                            alertTimeController = true,
                            minutesDay = 55,
                            minutesNight = 5
                        },
                        freezeTime = new TimeController.FreezeTime
                        {
                            useTimeFreeze = false,
                            timeFreeze = 13
                        }
                    },
                    rateController = new RateController
                    {
                        controllerList = new RateController.ControllerListRate
                        {
                            itemLists = new RateController.ControllerListRate.ItemLists
                            {
                                typeList = TypeList.BlackList,
                                blackListShortname = new List<String>()
                                {
                                    "vehicle.1mod.storage",
                                    "vehicle.1mod.cockpit.armored",
                                    "vehicle.1mod.passengers.armored",
                                    "vehicle.1mod.cockpit.with.engine",
                                    "carburetor3",
                                    "carburetor1",
                                    "carburetor2",
                                    "valve3",
                                    "valve1",
                                    "valve2",
                                    "crankshaft3",
                                    "crankshaft1",
                                    "crankshaft2",
                                    "vehicle.2mod.passengers",
                                    "piston3",
                                    "piston1",
                                    "piston2",
                                    "vehicle.1mod.taxi",
                                    "vehicle.1mod.flatbed",
                                    "vehicle.1mod.cockpit",
                                    "vehicle.1mod.rear.seats",
                                    "vehicle.2mod.flatbed",
                                    "vehicle.1mod.engine",
                                    "vehicle.2mod.camper",
                                    "sparkplug2",
                                    "sparkplug1",
                                    "sparkplug3",
                                    "vehicle.2mod.fuel.tank"
                                },
                                whiteListShortname = new List<String>()
                                {
                                    "shortnameExamaple",
                                }
                            },
                            blackListCategory = new List<String>()
                            {
                                "Weapon",
                                "Construction",
                                "Items",
                                "Attire",
                                "Tool",
                                "Traps",
                                "Misc",
                                "Electrical",
                                "Fun",
                            },
                            blackListPrefabs = new HashSet<String>()
                            {
                                "shortPrefabNameExample"
                            },
                            blackListSkinIds = new HashSet<UInt64>()
                            {
                                1234567890,
                            }
                        },
                        dayRate = new RateController.RateData
                        {
                            gatherRate = 1.5f,
                            lootRate = 1.0f,
                            pickUpRate = 1.5f,
                            growableRate = 2.0f,
                            excavatorRate = 1.0f,
                            fishRate = 2.0f,
                            quarryRate = new RateController.RateData.QuarryController
                            {
                                quarryRate = 1.5f,
                                detailRateQuarry = new Dictionary<String, Single>()
                                {
                                    ["stones"] = 1.8f,
                                    ["hq.metal.ore"] = 1.2f,
                                    ["sulfur.ore"] = 1.3f,
                                }
                            },
                            rateCorrectly = new Dictionary<String, Single>()
                            {
                                ["shortname"] = 2.0f,
                                ["shortname2"] = 2.0f,
                            }
                        },
                        nightRate = new RateController.RateData
                        {
                            gatherRate = 2.0f,
                            lootRate = 1.0f,
                            pickUpRate = 2.0f,
                            growableRate = 2.0f,
                            excavatorRate = 1.0f,
                            fishRate = 2.0f,
                            quarryRate = new RateController.RateData.QuarryController
                            {
                                quarryRate = 2.0f,
                                detailRateQuarry = new Dictionary<String, Single>()
                                {
                                    ["stones"] = 2.3f,
                                    ["hq.metal.ore"] = 2.2f,
                                    ["sulfur.ore"] = 2.3f,
                                }
                            },
                            rateCorrectly = new Dictionary<String, Single>()
                            {
                                ["shortname"] = 2.0f,
                                ["shortname2"] = 2.0f,
                            }
                        },
                        permissionRateDataDay = new Dictionary<String, RateController.RateData>()
                        {
                            ["iqrates.gold"] = new RateController.RateData
                            {
                                gatherRate = 3.5f,
                                lootRate = 2.0f,
                                pickUpRate = 3.5f,
                                growableRate = 4.0f,
                                excavatorRate = 2.0f,
                                fishRate = 4.0f,
                                quarryRate = new RateController.RateData.QuarryController
                                {
                                    quarryRate = 3.5f,
                                    detailRateQuarry = new Dictionary<String, Single>()
                                    {
                                        ["stones"] = 3.8f,
                                        ["hq.metal.ore"] = 3.2f,
                                        ["sulfur.ore"] = 3.3f,
                                    }
                                },
                                rateCorrectly = new Dictionary<String, Single>()
                                {
                                    ["shortname"] = 2.0f,
                                    ["shortname2"] = 2.0f,
                                }
                            },
                            ["iqrates.vip"] = new RateController.RateData
                            {
                                gatherRate = 2.5f,
                                lootRate = 2.0f,
                                pickUpRate = 2.5f,
                                growableRate = 3.0f,
                                excavatorRate = 2.0f,
                                fishRate = 3.0f,
                                quarryRate = new RateController.RateData.QuarryController
                                {
                                    quarryRate = 2.5f,
                                    detailRateQuarry = new Dictionary<String, Single>()
                                    {
                                        ["stones"] = 2.8f,
                                        ["hq.metal.ore"] = 2.2f,
                                        ["sulfur.ore"] = 2.3f,
                                    }
                                },
                                rateCorrectly = new Dictionary<String, Single>()
                                {
                                    ["shortname"] = 2.0f,
                                    ["shortname2"] = 2.0f,
                                }
                            },
                        },
                        permissionRateDataNight = new Dictionary<String, RateController.RateData>()
                        {
                            ["iqrates.gold"] = new RateController.RateData
                            {
                                gatherRate = 4.5f,
                                lootRate = 2.0f,
                                pickUpRate = 4.5f,
                                growableRate = 5.0f,
                                excavatorRate = 2.0f,
                                fishRate = 5.0f,
                                quarryRate = new RateController.RateData.QuarryController
                                {
                                    quarryRate = 4.5f,
                                    detailRateQuarry = new Dictionary<String, Single>()
                                    {
                                        ["stones"] = 4.8f,
                                        ["hq.metal.ore"] = 4.2f,
                                        ["sulfur.ore"] = 4.3f,
                                    }
                                },
                                rateCorrectly = new Dictionary<String, Single>()
                                {
                                    ["shortname"] = 2.0f,
                                    ["shortname2"] = 2.0f,
                                }
                            },
                            ["iqrates.vip"] = new RateController.RateData
                            {
                                gatherRate = 3.5f,
                                lootRate = 2.0f,
                                pickUpRate = 3.5f,
                                growableRate = 4.0f,
                                excavatorRate = 2.0f,
                                fishRate = 4.0f,
                                quarryRate = new RateController.RateData.QuarryController
                                {
                                    quarryRate = 3.5f,
                                    detailRateQuarry = new Dictionary<String, Single>()
                                    {
                                        ["stones"] = 3.8f,
                                        ["hq.metal.ore"] = 3.2f,
                                        ["sulfur.ore"] = 3.3f,
                                    }
                                },
                                rateCorrectly = new Dictionary<String, Single>()
                                {
                                    ["shortname"] = 2.0f,
                                    ["shortname2"] = 2.0f,
                                }
                            },
                        }
                    },
                    rateBonusController = new RateBonusController
                    {
                        wipeBonusRated = new RateBonusController.WipeBonusRated
                        {
                            useWipeBonus = false,
                            hourseActiveBonus = 12,
                            defaultRate = 1.0f,
                            permissionRateBonus = new List<PermissionsRateList>()
                            {
                                new PermissionsRateList
                                {
                                    permission = "iqrates.gold",
                                    rate = 2.0f
                                },
                                new PermissionsRateList
                                {
                                    permission = "iqrates.vip",
                                    rate = 1.5f
                                },
                            }
                        },
                        dayOfWeekBonusRated = new RateBonusController.RateControllerDayOfWeek
                        {
                            useBonusDayOfWeek = false,
                            rateBonusDayOfWeek = new List<RateBonusController.RateControllerDayOfWeek.RateBonusDays>()
                            {
                                new RateBonusController.RateControllerDayOfWeek.RateBonusDays
                                {
                                    defaultRate = 0.5f,
                                    permissionRateBonus = new List<PermissionsRateList>()
                                    {
                                        new PermissionsRateList
                                        {
                                            permission = "iqrates.gold",
                                            rate = 1.5f
                                        },
                                        new PermissionsRateList
                                        {
                                            permission = "iqrates.vip",
                                            rate = 1.0f
                                        },
                                    },
                                    timeStartBonus = new RateBonusController.RateControllerDayOfWeek.RateBonusDays.TimeController()
                                    {
                                        dayOfWeek = "Saturday",
                                        timeHours = 18,
                                    },
                                    timeStopBonus = new RateBonusController.RateControllerDayOfWeek.RateBonusDays.TimeController()
                                    {
                                        dayOfWeek = "Monday",
                                        timeHours = 12,
                                    }
                                },
                                new RateBonusController.RateControllerDayOfWeek.RateBonusDays
                                {
                                    defaultRate = 0.5f,
                                    permissionRateBonus = new List<PermissionsRateList>()
                                    {
                                        new PermissionsRateList
                                        {
                                            permission = "iqrates.gold",
                                            rate = 1.5f
                                        },
                                        new PermissionsRateList
                                        {
                                            permission = "iqrates.vip",
                                            rate = 1.0f
                                        },
                                    },
                                    timeStartBonus = new RateBonusController.RateControllerDayOfWeek.RateBonusDays.TimeController()
                                    {
                                        dayOfWeek = "Tuesday",
                                        timeHours = 18,
                                    },
                                    timeStopBonus = new RateBonusController.RateControllerDayOfWeek.RateBonusDays.TimeController()
                                    {
                                        dayOfWeek = "Friday",
                                        timeHours = 12,
                                    }
                                },
                            }
                        }
                    },
                    ovenController = new OvenController
                    {
                        useSpeedOven = false,
                        defaultRate = 1.0f,
                        blackListOvenPrefab = new HashSet<String>()
                        {
                            "fireplace.deployed",
                        },
                        permissionList = new List<PermissionsRateList>()
                        {
                            new PermissionsRateList()
                            {
                                permission = "iqrates.vip",
                                rate = 2.0f
                            },
                            new PermissionsRateList()
                            {
                                permission = "iqrates.premium",
                                rate = 3.0f
                            },
                        },
                        characoalChanceRate = new OvenController.CharacoalSetting
                        {
                            characoalChanceDefault = 75,
                            characoalChancePermissions = new List<OvenController.CharacoalSetting.PermissionsChanceList>()
                            {
                                new OvenController.CharacoalSetting.PermissionsChanceList
                                {
                                    permission = "iqrates.premium",
                                    chance = 100,
                                },
                                new OvenController.CharacoalSetting.PermissionsChanceList
                                {
                                    permission = "iqrates.vip",
                                    chance = 85,
                                }
                            }
                        }
                    },
                    mixingTableController = new MixingTableController
                    {
                        useSpeedMixingTable = false,
                        defaultRate = 0f,
                        permissionList = new List<PermissionsRateList>()
                        {
                            new PermissionsRateList()
                            {
                                permission = "iqrates.vip",
                                rate = 20f
                            },
                            new PermissionsRateList()
                            {
                                permission = "iqrates.premium",
                                rate = 30f
                            },
                        },
                    },
                    teaController = new TeaController
                    {
                        useTeaController = false
                    },
                    eventController = new EventController
                    {
                        eventCargoShip = new EventController.EventTemplate
                        {
                            useEvent = false,
                            disableFullEvent = false,
                            spawnController = new EventController.EventTemplate.SpawnController
                            {
                                staticTimeOrRandom = false,
                                staticTime = 3500,
                                randomTimePreset = new EventController.EventTemplate.SpawnController.RandomTime
                                {
                                    minTime = 2000,
                                    maxTime = 5000
                                }
                            }
                        },
                        eventBradley = new EventController.EventTemplate
                        {
                            useEvent = false,
                            disableFullEvent = false,
                            spawnController = new EventController.EventTemplate.SpawnController
                            {
                                staticTimeOrRandom = false,
                                staticTime = 3000,
                                randomTimePreset = new EventController.EventTemplate.SpawnController.RandomTime
                                {
                                    minTime = 1000,
                                    maxTime = 3000
                                }
                            }
                        },
                        eventHelicopter = new EventController.EventTemplate
                        {
                            useEvent = false,
                            disableFullEvent = false,
                            spawnController = new EventController.EventTemplate.SpawnController
                            {
                                staticTimeOrRandom = false,
                                staticTime = 5000,
                                randomTimePreset = new EventController.EventTemplate.SpawnController.RandomTime
                                {
                                    minTime = 3500,
                                    maxTime = 6000
                                }
                            }
                        },
                        eventCh47 = new EventController.EventTemplate
                        {
                            useEvent = false,
                            disableFullEvent = false,
                            spawnController = new EventController.EventTemplate.SpawnController
                            {
                                staticTimeOrRandom = false,
                                staticTime = 6000,
                                randomTimePreset = new EventController.EventTemplate.SpawnController.RandomTime
                                {
                                    minTime = 2000,
                                    maxTime = 6000
                                }
                            }
                        },
                        eventAirdrop = new EventController.EventTemplate
                        {
                            useEvent = false,
                            disableFullEvent = false,
                            spawnController = new EventController.EventTemplate.SpawnController
                            {
                                staticTimeOrRandom = false,
                                staticTime = 4500,
                                randomTimePreset = new EventController.EventTemplate.SpawnController.RandomTime
                                {
                                    minTime = 2500,
                                    maxTime = 4500
                                }
                            }
                        }
                    },
                    recyclerController = new RecyclerController
                    {
                        speedRecycler = new RecyclerController.SpeedRecycler
                        {
                            useRecyclerSpeed = false,
                            defaultRate = 5.0f,
                            permissionRate = new List<PermissionsRateList>()
                            {
                                new PermissionsRateList()
                                {
                                    permission = "iqrates.gold",
                                    rate = 2.5f,
                                },
                                new PermissionsRateList()
                                {
                                    permission = "iqrates.vip",
                                    rate = 3.5f,
                                },
                            }
                        }
                    },
                    fuelTransportController = new FuelTransportController
                    {
                        fuelBuyingShopKeeper = new FuelTransportController.FuelBuingShopKeeper
                        {
                            useAutoFillFuel = false,
                            fuelBoat = 200,
                            fuelSubmarine = 200,
                            fuelMinicopter = 200,
                            fuelScrapTransport = 200,
                            fuelAttackHelicopter = 200
                        },
                        fuelPerSecTransport = new FuelTransportController.FuelPerSecTransport
                        {
                            useConsumedFuel = false,
                            consumedHotAirBalloon = 1.0f,
                            consumedSnowmobile = 1.0f,
                            consumedTrain = 1.0f,
                            consumedBoat = 1.0f,
                            consumedSubmarine = 1.0f,
                            consumedCopter = 1.0f,
                            consumedScrapTransport = 1.0f,
                            consumedAttackHelicopter = 1.0f
                        }
                    },
                    referencePlugins = new ReferencePlugins
                    {
                        iqchatReference = new ReferencePlugins.IQChat
                        {
                            useIQChat = false,
                            customAvatar = "",
                            prefixChat = "[<color=#7B6EF1>IQRates</color>]\n"
                        }
                    },
                };
            }
        }

                
        
        private void OnMixingTableToggle(MixingTable table, BasePlayer player)
        {
            if (table.IsOn())
                return;

            Single speedMixing = config.mixingTableController.GetSpeeedMixingTable(player);
            
            NextTick(() =>
            {
                table.RemainingMixTime *= speedMixing;
                table.TotalMixTime *= speedMixing;
                table.SendNetworkUpdateImmediate();
		   		 		  						  	   		   					  			 		   					  	 		
                if (!(table.RemainingMixTime < 1f)) return;
                table.CancelInvoke(table.TickMix);
                table.Invoke(table.TickMix, table.RemainingMixTime);
            });
        }
        
        private new void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<String, String>
            {
                ["NIGHT_RATES_ALERT"] = ":torch:<color=#7B6EF1>Night has fallen!</color>\n\n{0}",
                ["DAY_RATES_ALERT"] = ":granolabar:<color=#6EDDF1>Day has begun!</color>\n\n{0}",
                ["INFO_MY_RATE"] = "<color=#6EBBF1><b>Your rates:</b></color>" +
                                   "\nGathered resources: <color=#6EBBF1>x{0}</color>" +
                                   "\nFound resources: <color=#6EBBF1>x{1}</color>" +
                                   "\nLifted resources: <color=#6EBBF1>x{2}</color>" +
                                   "\nHarvested from plants: <color=#6EBBF1>x{3}</color>" +
                                   "\nQuarries: <color=#6EBBF1>x{4}</color>" +
                                   "\nExcavators: <color=#6EBBF1>x{5}</color>" +
                                   "\nFishing: <color=#6EBBF1>x{6}</color>",
                ["INFO_MY_RATE_BONUS"] = "\n+ bonus rates: <color=#6EBBF1>x{0}</color>",

                ["RATE_BONUS_DAY_OF_WEEK"] = ":exclamation:Attention, survivors!\n" +
                                             "\nThe increased rate period has begun!" +
                                             "\nAll <color=#6EBBF1>your</color> rates are boosted by a coefficient of <color=#6EBBF1>x{0}</color>" +
                                             "\nIt will be active from <color=#6EBBF1>{1} {2}</color>, until <color=#6EBBF1>{3} {4}</color>\n\nHurry to make the most of these bonuses!",

                ["RATE_BONUS_DAY_OF_WEEK_END"] = ":exclamation:Attention!\n" +
                                                 "\nThe increased rate period has ended." +
                                                 "\nThank you for participating!" +
                                                 "\nStay tuned for new bonuses and continue your adventures!",

                ["MONDAY"] = "Monday",
                ["TUESDAY"] = "Tuesday",
                ["WEDNESDAY"] = "Wednesday",
                ["THURSDAY"] = "Thursday",
                ["FRIDAY"] = "Friday",
                ["SATURDAY"] = "Saturday",
                ["SUNDAY"] = "Sunday",
            }, this);

            lang.RegisterMessages(new Dictionary<String, String>
            {
                ["NIGHT_RATES_ALERT"] = ":torch:<color=#7B6EF1>Наступила ночь!</color>\n\n{0}",
                ["DAY_RATES_ALERT"] = ":granolabar:<color=#6EDDF1>Наступил день!</color>\n\n{0}",
                ["INFO_MY_RATE"] = "<color=#6EBBF1><b>Ваши рейты:</b></color>" +
                                   "\nДобываемые ресурсы: <color=#6EBBF1>x{0}</color>" +
                                   "\nНайденные ресурсы: <color=#6EBBF1>x{1}</color>" +
                                   "\nПоднимаемые ресурсы: <color=#6EBBF1>x{2}</color>" +
                                   "\nПоднимаемых с грядок: <color=#6EBBF1>x{3}</color>" +
                                   "\nКарьеров: <color=#6EBBF1>x{4}</color>" +
                                   "\nЭкскаваторы: <color=#6EBBF1>x{5}</color>" +
                                   "\nРыбалка: <color=#6EBBF1>x{6}</color>",
                ["INFO_MY_RATE_BONUS"] = "\n+ бонусные рейты : <color=#6EBBF1>x{0}</color>",
                
                ["RATE_BONUS_DAY_OF_WEEK"] = ":exclamation:Внимание, выжившие!\n" + 
                                             "\nНаступил период увеличенного рейтинга!" + 
                                             "\nКо всем <color=#6EBBF1>вашим</color> рейтингам прибавлен коэффициент <color=#6EBBF1>x{0}</color>" + 
                                             "\nОн будет действовать с <color=#6EBBF1>{1} {2}</color>, до <color=#6EBBF1>{3} {4}</color>\n\nПоспешите воспользоваться бонусами!",
                
                ["RATE_BONUS_DAY_OF_WEEK_END"] =  ":exclamation:Внимание!\n" +
                                                  "\nПериод увеличенного рейтинга подошел к концу." +
                                                  "\nСпасибо за участие!" +
                                                  "\nСледите за новыми бонусами и продолжайте свои приключения!",

                
                ["MONDAY"] = "понедельника",
                ["TUESDAY"] = "вторника",
                ["WEDNESDAY"] = "средаы",
                ["THURSDAY"] = "четверга",
                ["FRIDAY"] = "пятницы",
                ["SATURDAY"] = "субботы",
                ["SUNDAY"] = "воскресенья",
            }, this, "ru");
            
            Puts(LanguageEn ? "Language file loaded successfully" : "Языковой файл загружен успешно");
        }
        private ModifierDefintion CreateModifier(ModiferTea tea, Single difference) => GetDefintionModifer(tea.type, tea.duration, tea.value / difference);
        
        
        [PluginReference] Plugin IQChat;
        private Boolean defaultBradleyStatus = true;

        private void OnNewSave(String filename)
        {
            lootedEntity.Clear();
            quarryToggled.Clear();
            
            WriteData();
        }
        
        private Dictionary<String, Single> bonusRatesPlayer = new Dictionary<String, Single>();
        
        private struct PlayerRateCache
        {
            public Configuration.RateController.RateData dayRate;
            public Configuration.RateController.RateData nightRate;
        }

        private List<String> npcTriggers = new List<String>();
        
        private Single CalculateModifierDifference(Single defaultRate, Single playerRate) => (playerRate - defaultRate) <= 0 ? 1 : (playerRate - defaultRate);
        
            }
}
