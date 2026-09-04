using HarmonyLib;
using System;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Reflection.Emit;
using System.Collections.Generic;
using Rust;
using Rust.Ai.Gen2;
using Facepunch;
using UnityEngine;
using UnityEngine.UI;
using Newtonsoft.Json;
using Oxide.Core;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using Oxide.Core.Libraries.Covalence;
using Oxide.Plugins.ExtensionsRealPVE;

namespace Oxide.Plugins
{
    [Info("RealPVE", "IIIaKa", "0.1.221")]
	[Description("Plugin for Real PvE servers, featuring damage prevention, anti-griefing measures, customizable PvP zones, an automatic loot queue in radtowns and raid zones, and much more.")]
	class RealPVE : RustPlugin
    {

		object OnExcavatorSuppliesRequest(ExcavatorSignalComputer computer, BasePlayer player)
		{
			if (player != null && !_unrestrictedLooters.Contains(player.userID) && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
			{
				object result = monumentData.CanLoot(player);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return result;
			}
			return null;
		}
		private BuildingGatherRestrictions _gatherFlags = BuildingGatherRestrictions.None;
		object CanLock(BasePlayer player, BaseLock baseLock) => !baseLock.OwnerID.IsSteamId() || !baseLock.IsValid() || _unrestrictedLooters.Contains(player.userID) || IsEntityInPvP(player.userID, baseLock.net.ID.Value) ? null : player.TasirMumkin(baseLock.OwnerID);
		private static MonumentConfig _monumentsConfig;

		object OnCrateLaptopAttack(HackableLockedCrate crate, HitInfo info)
		{
			if (info != null && info.InitiatorPlayer is BasePlayer attacker && _monumentsList.TryGetValue(GetEntityMonument(crate), out var monumentData))
				return monumentData.CanLoot(attacker);
			return false;
		}
		
		object CanLootEntity(BasePlayer player, RidableHorse horse) => HandleVehicleInteraction(player, horse);
		
		public class SharedEntity
        {
			public bool ForEveryOne { get; set; }
			public HashSet<ulong> PlayersList { get; set; } = new HashSet<ulong>();
		}
		object OnSiegeWeaponDoorClose(BatteringRam batteringRam, BasePlayer player) => HandleVehicleInteraction(player, batteringRam, "MsgVehicleCantClose");
		object CanLootEntity(BasePlayer player, TorchDeployableLightSource torchDeployable) => CanLootStorage(player, torchDeployable);
		
		object CanLootEntity(BasePlayer player, MarketTerminal marketTerminal)
        {
			if (marketTerminal._customerSteamId > 0uL)
            {
				if (_unrestrictedLooters.Contains(player.userID))
					return AdminOpenLoot(player, marketTerminal);
				return null;
			}
			return CanLootStorage(player, marketTerminal);
		}

		private double GetVehiclePrice(string userID, VehicleType type)
        {
			PvEPermission perm;
			double num, result = _permissionsConfig.PermissionsList[0].Allowed_Vehicles[type].Price;
			for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
			{
				perm = _permissionsConfig.PermissionsList[i];
				num = perm.Allowed_Vehicles[type].Price;
				if (num < result && permission.UserHasPermission(userID, perm.Name))
					result = num;
			}
			return result;
		}
		
		private void ConfigItemPickupToggled()
        {
			if (!_config.NoPickupRestrictions)
				Subscribe(nameof(OnItemPickup));
			else
				Unsubscribe(nameof(OnItemPickup));
		}
		
		object CanLootEntity(BasePlayer player, BuildingPrivlidge privlidge) => CanLootStorage(player, privlidge);
		
		public static void ModSpawnChicken(ChickenCoop __instance, ref FarmableAnimal __result, int index)
		{
			if (__result.IsValid() && __instance.IsValid())
				__result.OwnerID = __instance.OwnerID;
        }
		
		void OnUserGroupAdded(string userIDString, string groupName) => HandlePermissionsUpdate(userIDString);
        
        		object OnNpcTarget(global::HumanNPC npc, BasePlayer target)
        {
			if (target.userID.IsSteamId())
                return HandleNpcTarget(npc, target);
            if (target is BasePet pet)
            {
                var ownerPlayer = pet.Brain?.OwningPlayer ?? BasePlayer.FindAwakeOrSleepingByID(pet.OwnerID);
                if (ownerPlayer.IsValid())
                    return HandleNpcTarget(npc, ownerPlayer);
            }
            return null;
		}
		object OnEntityTakeDamage(ScrapTransportHelicopter scrapCopter, HitInfo info) => HandleDamageToVehicle(scrapCopter, info);
		
		void OnEntityEnterZone(string zoneID, BaseEntity entity)
        {
			if (entity is not BasePlayer && _dynamicPvPs.Contains(zoneID))
				OnEntityEnterPVP(entity);
		}
		
		object CanAdministerVending(BasePlayer player, VendingMachine machine)
		{
			if (_unrestrictedLooters.Contains(player.userID)) return true;
			if (machine.OwnerID.IsSteamId() && !IsEntityInPvP(player.userID, machine.net.ID.Value))
            {
                object result = CanInteractSharedEntity(player, machine);
                if (result != null)
                    SendMessageText(player, lang.GetMessage("MsgCantAdministerVending", this, player.UserIDString));
                return result;
            }
			return null;
		}
		
        		private static void GetOrCreatePlayerData(string userIDStr, out PlayerData result)
        {
			ulong.TryParse(userIDStr, out var userID);
			if (!_playersList.TryGetValue(userID, out result))
                _playersList[userID] = result = new PlayerData(userID);
		}
		
		void OnPlayerCorpseSpawned(BasePlayer player, PlayerCorpse corpse)
        {
			if (_pvpPlayers.ContainsKey(player.userID))
                OnEntityEnterPVP(corpse);
            if (corpse.containers != null)
            {
                foreach (var container in corpse.containers)
                    container.containerVolume = 28;
            }
        }
		void OnPortalUsed(BasePlayer player, XmasDungeon xmas) => HandlePortalUsed(player, xmas);
		
		public static bool ModHorseSaddleSwap(RidableHorse __instance, BaseEntity.RPCMessage msg)
        {
			if (__instance.IsValid())
            {
                var player = msg.player;
                if (player != null && !__instance.IsForSale && __instance.HasSaddle && !__instance.AnyMounted() && Instance.OnSaddleSwap(__instance, player) == null)
                {
					int tokenItemID = msg.read.Int32();
					var purchaseToken = __instance.GetPurchaseToken(player, tokenItemID);
					if (purchaseToken != null)
                    {
						var itemDef = __instance.HasSingleSaddle ? __instance.PurchaseOptions[0].tokenItem : __instance.PurchaseOptions[1].tokenItem;
						__instance.OnClaimedWithToken(purchaseToken);
                        purchaseToken.UseItem();
						var item = ItemManager.Create(itemDef, 1, 0uL);
                        player.GiveItem(item);
                        __instance.SendNetworkUpdateImmediate();
                    }
				}
            }
            return false;
        }
		
		object OnVehiclePush(BaseVehicle vehicle, BasePlayer player) => player.Uyda() ? null : HandleVehicleInteractionM(player, vehicle, "MsgVehicleCantPush");

		protected override void LoadConfig()
		{
			base.LoadConfig();
			try { _config = Config.ReadObject<Configuration>(); }
			catch (Exception ex) { PrintError($"{ex.Message}\n\n[{Title}] Your configuration file contains an error."); }
			if (_config == null || _config.Version == new VersionNumber())
			{
				PrintWarning("The configuration file is not found or contains errors. Creating a new one...");
				LoadDefaultConfig();
			}
			else if (_config.Version < Version)
			{
				PrintWarning($"Your configuration file version({_config.Version}) is outdated. Updating it to {Version}.");
				_config.Version = Version;
				PrintWarning($"The configuration file has been successfully updated to version {_config.Version}!");
			}
			
			if (string.IsNullOrWhiteSpace(_config.Command))
                _config.Command = "realpve";
            if (string.IsNullOrWhiteSpace(_config.AdminCommand))
                _config.AdminCommand = "adminpve";
            if (_config.Command.Equals(_config.AdminCommand, StringComparison.OrdinalIgnoreCase))
                _config.Command += "_1";

			if (_config.BuildingPrivilegeGathering == null)
				_config.BuildingPrivilegeGathering = new HashSet<int> { 1, 2, 3 };
			else if (!_config.BuildingPrivilegeGathering.Any())
				_config.BuildingPrivilegeGathering.Add(0);
			else
            {
                if (_config.BuildingPrivilegeGathering.Contains(0))
                {
                    _config.BuildingPrivilegeGathering.Clear();
                    _config.BuildingPrivilegeGathering.Add(0);
                }
                else
                    _config.BuildingPrivilegeGathering.RemoveWhere(x => x < 0 || x > 3);
            }
			VerifyBuildingPrivilegeGathering();
			
			if (string.IsNullOrWhiteSpace(_config.PriceFormat))
				_config.PriceFormat = "${0}";
			_config.VehiclesMarkerTime = Mathf.Clamp(_config.VehiclesMarkerTime, 0f, 60f);
			_config.AntiSleeper = Mathf.Max(_config.AntiSleeper, 0f);
			
			if (_config.LanguageKeys == null)
                _config.LanguageKeys = new List<string>();
            if (_config.LanguageKeys.Any())
            {
                for (int i = _config.LanguageKeys.Count - 1; i >= 0; i--)
                {
					string langKey = _config.LanguageKeys[i].ToLangKey();
					if (langKey.Equals("en", StringComparison.OrdinalIgnoreCase) || langKey.Equals("ru", StringComparison.OrdinalIgnoreCase))
						_config.LanguageKeys.RemoveAt(i);
					else
						_config.LanguageKeys[i] = langKey;
				}
            }
			_config.LanguageKeys.Add("en");
			
			if (string.IsNullOrWhiteSpace(_config.PvPMapMarkersName))
				_config.PvPMapMarkersName = "PvP Zone!";
			if (_config.BarPvP == null)
			{
				_config.BarPvP = new BarSettings()
				{
					Order = 9,
					Main_Color = "1 0.39 0.28 0.7",
					Image_Url = "https://i.imgur.com/oi5vIkk.png",
					Image_Local = "RealPVE_PvP",
					Image_Color = "1 0.39 0.28 1"
				};
			}

			if (_config.ProgressBarPvP == null)
				_config.ProgressBarPvP = new ProgressBarSettings() { Progress_Color = "#FF6347" };

			var barSettings = _config.BarPvP;
			_pvpBar = new Dictionary<int, object>
			{
				{ 1, Name },
				{ 2, "Default" },
				{ 3, "PvP" },
				{ 4, barSettings.Order },
				{ 5, barSettings.Height },
				{ 6, barSettings.Main_Color },
				{ 11, barSettings.Image_IsRawImage },
				{ 12, barSettings.Image_Color },
				{ 16, barSettings.Text_Size },
				{ 17, barSettings.Text_Color },
				{ 18, barSettings.Text_Font },
				{ 23, barSettings.SubText_Size },
				{ 24, barSettings.SubText_Color },
				{ 25, barSettings.SubText_Font }
			};
			if (barSettings.Main_Color.StartsWith("#"))
				_pvpBar.Add(-6, barSettings.Main_Transparency);
			if (!string.IsNullOrWhiteSpace(barSettings.Main_Material))
				_pvpBar.Add(7, barSettings.Main_Material);
			if (barSettings.Image_Color.StartsWith("#"))
				_pvpBar.Add(-12, barSettings.Image_Transparency);
			if (barSettings.Image_Outline_Enabled)
			{
				_pvpBar.Add(13, barSettings.Image_Outline_Color);
				if (barSettings.Image_Outline_Color.StartsWith("#"))
					_pvpBar.Add(-13, barSettings.Image_Outline_Transparency);
				_pvpBar.Add(14, barSettings.Image_Outline_Distance);
			}
			if (barSettings.Text_Outline_Enabled)
			{
				_pvpBar.Add(20, barSettings.Text_Outline_Color);
				if (barSettings.Text_Outline_Color.StartsWith("#"))
					_pvpBar.Add(-20, barSettings.Text_Outline_Transparency);
				_pvpBar.Add(21, barSettings.Text_Outline_Distance);
			}
			if (barSettings.SubText_Outline_Enabled)
			{
				_pvpBar.Add(26, barSettings.SubText_Outline_Color);
				if (barSettings.SubText_Outline_Color.StartsWith("#"))
					_pvpBar.Add(-26, barSettings.SubText_Outline_Transparency);
				_pvpBar.Add(27, barSettings.SubText_Outline_Distance);
			}

			SaveConfig();
		}
		
		private void ToggleRaidableBases(bool isLoaded)
		{
			if (!isLoaded)
			{
				Unsubscribe(nameof(OnPlayerEnteredRaidableBase));
                Unsubscribe(nameof(OnPlayerExitedRaidableBase));
                Unsubscribe(nameof(OnRaidableLootDestroyed));
                Unsubscribe(nameof(OnRaidableDespawnUpdate));
                Unsubscribe(nameof(OnRaidableBasePurchased));
                Unsubscribe(nameof(OnRaidableBaseStarted));
                Unsubscribe(nameof(OnRaidableBaseEnded));
                foreach (var rbData in _rbList.Values.ToList())
                    rbData.Destroy();
			}
			else if (_rbsConfig.IsEnabled)
			{
				LoadRBsImages();
                var rbsList = RaidableBases?.Call("GetAllEvents") as List<(Vector3 pos, string mode, int level, bool allowPVP, string a, float b, float c, float loadTime, ulong ownerID, BasePlayer owner, List<BasePlayer> raiders, List<BasePlayer> intruders, HashSet<BaseEntity> entities, string baseName, DateTime spawnDateTime, DateTime despawnDateTime, float radius, int lootRemain)>;
                if (rbsList != null && rbsList.Any())
                {
                    string raidID;
                    foreach (var rbInfo in rbsList)
                    {
						raidID = rbInfo.pos.ToString();
						_rbList[raidID] = new RBData(raidID, rbInfo.pos, rbInfo.level, rbInfo.allowPVP, rbInfo.radius, rbInfo.ownerID, rbInfo.despawnDateTime, rbInfo.lootRemain, rbInfo.intruders);
					}
                }
                Subscribe(nameof(OnPlayerEnteredRaidableBase));
                Subscribe(nameof(OnPlayerExitedRaidableBase));
                Subscribe(nameof(OnRaidableLootDestroyed));
                Subscribe(nameof(OnRaidableDespawnUpdate));
                Subscribe(nameof(OnRaidableBasePurchased));
                Subscribe(nameof(OnRaidableBaseStarted));
                Subscribe(nameof(OnRaidableBaseEnded));
			}
		}
        
        		void OnDefaultItemsReceive(PlayerInventory inventory)
        {
			if (inventory.baseEntity is BasePlayer player && Interface.CallHook(Hooks_CanRedeemKit, player) == null)
				NextTick(() => GiveDefaultItems(player));
		}
		
		object CanLootEntity(BasePlayer player, VendingMachine vending) => null;
		void OnEntitySpawned(BaseSubmarine submarine) => HandleVehicleSpawn(submarine);
		object CanChangeCode(BasePlayer player, CodeLock codeLock, string newCode, bool isGuestCode) => !codeLock.OwnerID.IsSteamId() || _unrestrictedLooters.Contains(player.userID) || IsEntityInPvP(player.userID, codeLock.net.ID.Value) ? null : player.TasirMumkin(codeLock.OwnerID);
		
		public static bool ModHorseTow(RidableHorse __instance, BaseEntity.RPCMessage msg)
        {
			if (__instance.IsValid() && __instance.towableEntity != null && !__instance.IsTowing && !__instance.HasFlag(BaseEntity.Flags.Reserved12))
			{
				var player = msg.player;
				if (player != null && Instance.OnRidableAnimalTow(__instance, player) == null)
                {
                    __instance.TowAttach(player);
                    Effect.server.Run(__instance.towingAttachEffect.resourcePath, __instance, 0u, Vector3.zero, Vector3.zero);
                }
			}
			return false;
		}
		
		object OnEntityTakeDamage(BasePet victimPet, HitInfo info)
        {
            if (info == null) return null;
            ulong victimID = victimPet.Brain?.OwningPlayer?.userID ?? victimPet.OwnerID;
            if (!victimID.IsSteamId()) return null;
            switch (TryGetAttacker(info, out var initiator))
            {
                case AttackerIndex.PatrolHelicopter:
                    
                    if (initiator.IsValid() && _eventsList.TryGetValue(initiator.net.ID.Value, out var eventPatrol) && !eventPatrol.CanBeAttackedBy(victimID))
                        info.Urma();
                    break;
                case AttackerIndex.BradleyAPC:
                    if (_eventsList.TryGetValue(initiator.net.ID.Value, out var eventBradley) && !eventBradley.CanBeAttackedBy(victimID))
                        info.Urma();
                    break;
                case AttackerIndex.BasePlayer:
                    var attacker = (BasePlayer)initiator;
					if (!attacker.userID.IsSteamId())
                    {
                        if (victimID.IsSteamId())
                        {
                            if (attacker.skinID == _rrPluginID)
                            {
                                if (_config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(attacker.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(victimID))
									info.Urma();
							}
                        }
                    }
                    else if (!IsPlayerInPvP(attacker.userID, victimID))
                    {
                        if (victimID.IsSteamId())
                        {
                            if (!UrishMumkin(attacker, victimID))
								info.Urma();
						}
                        else if (TryGetRaidBase(victimPet.transform.position, out var rbData))
                        {
                            if (!rbData.CanInteractWithRaid(attacker.userID))
								info.Urma();
						}
                        else if (_monumentsList.TryGetValue(GetEntityMonument(victimPet), out var monumentData))
                        {
                            if (monumentData.CanLoot(attacker) != null)
								info.Urma();
						}
					}
					break;
                case AttackerIndex.PlayerOwnerMissing:
					info.Urma();
					break;
			}
            return null;
        }

		object OnCupboardAuthorize(BuildingPrivlidge privilege, BasePlayer player)
        {
			if (IsEntityInPvP(player.userID, privilege.net.ID.Value)) return null;
			object result = CanInteractSharedEntity(player, privilege);
			if (_config.RandomRaids_Enabled && result == null && _randomRaidsList.TryGetValue(privilege.transform.position.ToString(), out var rrData))
			{
				NextTick(() =>
				{
					if (player != null && privilege.IsAuthed(player))
					{
						_rrAllPlayers[player.userID] = rrData;
						rrData.PlayersList.Add(player.userID);
					}
				});
			}
			if (result != null)
            {
				if (_unrestrictedLooters.Contains(player.userID))
					AdminOpenLoot(player, privilege);
                else
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                return true;
            }
			return null;
        }
        
        		void OnUserPermissionGranted(string userIDString, string permName)
        {
			if (_permNames.Contains(permName, StringComparer.OrdinalIgnoreCase))
				HandlePermissionsUpdate(userIDString);
		}
		
		public static bool ModDroppedContainer(LootableCorpse __instance)
        {
			if (__instance.IsValid())
			{
				if (__instance.OwnerID == 0uL || __instance.playerSteamID.IsSteamId())
					__instance.OwnerID = __instance.playerSteamID;
			}
            return true;
        }
        void OnCargoPlaneSignaled(CargoPlane plane, SupplySignal signal) => plane.OwnerID = signal.OwnerID;
		
		private static object CanInteractSharedEntity(BasePlayer player, BaseEntity entity)
        {
			if (player.userID == entity.OwnerID || (player.Team != null && player.Team.members.Contains(entity.OwnerID)) || IsEntitySharedToPlayer(player.userID, entity.net.ID.Value))
				return null;
			return false;
		}

        void OnCreatedDynamicPVP(string zoneID, string eventName, Vector3 pos, float duration)
        {
			_dynamicPvPs.Add(zoneID);
			
			var entities = ZoneManager?.Call("GetEntitiesInZone", zoneID) as List<BaseEntity>;
			if (entities != null && entities.Any())
            {
				foreach (var entity in entities)
                {
                    if (entity is not BasePlayer)
                        OnEntityEnterPVP(entity);
                }
            }
			
			string monumentID = GetMonumentByPos(pos);
            if (_monumentsList.TryGetValue(monumentID, out var monumentData) && monumentData.SetAsPvP(false))
            {
				if (!_pvpChangedMonuments.TryGetValue(monumentID, out var zones))
                    _pvpChangedMonuments[monumentID] = zones = new HashSet<string>();
                zones.Add(zoneID);
            }
			
			if (_config.PvPMapMarkers)
				CreatePVPMapMarker(zoneID, pos, (float)(ZoneManager?.Call("GetZoneRadius", zoneID) ?? 0.25f), _config.PvPMapMarkersName);
		}
		object OnPlayerDrink(BasePlayer player, LiquidContainer container) => CanLootByOwnerID(player, container);
        object OnBackpackDrop(Item backpack, PlayerInventory inventory) => true;
		private void SendMessage(IPlayer player, string replyKey, string[] replyArgs = null, bool isWarning = true)
		{
			if (player == null) return;
			
			string text = lang.GetMessage(replyKey, this, player.Id);
			if (replyArgs != null)
				text = string.Format(text, replyArgs);
			
			if (_config.GameTips_Enabled && !player.IsServer)
				player.Command(Str_Showtoast, (int)(isWarning ? GameTip.Styles.Error : GameTip.Styles.Blue_Long), text, string.Empty);
			else
				player.Reply(text);
		}
		
		void OnLootEntityEnd(BasePlayer player, ModularCarGarage garage) => DestroyVehiclePanels(player);
		object OnEntityTakeDamage(BaseVehicleModule module, HitInfo info) => HandleDamageToVehicle(module.VehicleParent(), info);
		
		void ToggleDynamicPVP(bool isLoaded)
		{
			if (isLoaded)
			{
				if (_dynamicPvPs == null)
                    _dynamicPvPs = new HashSet<string>();
                else
                    _dynamicPvPs.Clear();
				
				string zoneID;
				string[] array = DynamicPVP.Call("AllDynamicPVPZones") as string[] ?? Array.Empty<string>();
				for (int i = 0; i < array.Length; i++)
                {
                    zoneID = array[i];
                    OnCreatedDynamicPVP(zoneID, string.Empty, (Vector3)(ZoneManager?.Call("GetZoneLocation", zoneID) ?? Vector3.zero), 0f);

                    var players = ZoneManager?.Call("GetPlayersInZone", zoneID) as List<BasePlayer>;
                    if (players == null || !players.Any()) continue;
                    foreach (var player in players)
                        OnPlayerEnterPVP(player, zoneID);
                }
				
				Subscribe(nameof(OnEntityEnterZone));
                Subscribe(nameof(OnEntityExitZone));
                Subscribe(nameof(OnCreateDynamicPVP));
                Subscribe(nameof(OnCreatedDynamicPVP));
                Subscribe(nameof(OnDeletedDynamicPVP));
			}
			else
            {
				_dynamicPvPs?.Clear();
                Unsubscribe(nameof(OnEntityEnterZone));
                Unsubscribe(nameof(OnEntityExitZone));
                Unsubscribe(nameof(OnCreateDynamicPVP));
                Unsubscribe(nameof(OnCreatedDynamicPVP));
                Unsubscribe(nameof(OnDeletedDynamicPVP));
			}
		}
		object OnEntityTakeDamage(RHIB rhib, HitInfo info) => HandleDamageToVehicle(rhib, info);
		private const string PERMISSION_ADMIN = "realpve.admin", TimeFormat = "yyyy-MM-dd HH:mm:ss", Str_Showtoast = "gametip.showtoast", Str_IsFriend = "IsFriend",
			Hooks_OnPlayerPVPDelay = "OnPlayerPVPDelay", Hooks_OnPlayerPVPDelayed = "OnPlayerPVPDelayed", Hooks_OnPlayerPVPDelayRemoved = "OnPlayerPVPDelayRemoved", Hooks_OnZoneStatusText = "OnZoneStatusText", Hooks_CanRedeemKit = "CanRedeemKit";
		private Dictionary<ulong, string> _respawnMessage = new Dictionary<ulong, string>();

        private CuiElementContainer GetVehicleCarPanel(string userID, VehicleData vehicleData)
        {
            string description, descriptionValue;
            bool notOwner = vehicleData.OwnerID != 0uL && !vehicleData.IsOwner(userID);
            var container = new CuiElementContainer();
            container.Add(new CuiPanel
            {
                RectTransform = { AnchorMin = "0.5 0", AnchorMax = "0.5 0", OffsetMin = "385 110", OffsetMax = "573 200" },
                Image = { Color = "0.35 0.35 0.35 1", Material = "assets/content/ui/ui.background.tiletex.psd" }
            }, "Overlay", _uiVehiclePanel);
            if (notOwner)
            {
                container.Add(new CuiElement
                {
                    Parent = _uiVehiclePanel,
                    Components =
                    {
                        new CuiImageComponent { Color = "0 0 0 0" },
                        new CuiRectTransformComponent { AnchorMin = "1 1", AnchorMax = "1 1", OffsetMin = "-382 230", OffsetMax = "2 381" }
                    }
                });
            }
            container.Add(new CuiElement
            {
                Parent = _uiVehiclePanel,
                Components =
                {
                    new CuiImageComponent { Color = "0 0 0 0" },
                    new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "-50 0", OffsetMax = "0 0" }
                }
            });
            string[] values = new string[5];
            values[0] = vehicleData.ID.ToString();
            values[1] = lang.GetMessage($"MsgVehicle{vehicleData.Type}", this, userID).FirstToUpper();
            values[2] = lang.GetMessage($"MsgVehicle{vehicleData.Category}", this, userID);
            values[3] = !string.IsNullOrWhiteSpace(vehicleData.RegistrationDate) ? $"{vehicleData.RegistrationDate}(UTC)" : lang.GetMessage("MsgNoDate", this, userID);
            if (notOwner)
            {
                var owner = BasePlayer.FindByID(vehicleData.OwnerID);
                values[4] = owner != null ? owner.displayName : vehicleData.OwnerID.ToString();
                description = lang.GetMessage("MsgVehicleCarDialogDescriptionNotOwner", this, userID);
                descriptionValue = string.Format(lang.GetMessage("MsgVehicleCarDialogDescriptionNotOwnerValue", this, userID), values);
            }
            else if (vehicleData.OwnerID == 0uL)
            {
                if (_economicsIsLoaded)
					values[4] = $"{string.Format(_config.PriceFormat, $"{GetVehiclePrice(userID, vehicleData.Type)}")}({string.Format(_config.PriceFormat, $"{GetBalance(userID)}")})";
				else
                    values[4] = lang.GetMessage("MsgFree", this, userID);
                description = lang.GetMessage("MsgVehicleCarDialogDescription", this, userID);
                descriptionValue = string.Format(lang.GetMessage("MsgVehicleCarDialogDescriptionValue", this, userID), values);
            }
            else
            {
                description = lang.GetMessage("MsgVehicleCarDialogDescriptionRegistered", this, userID);
                descriptionValue = string.Format(lang.GetMessage("MsgVehicleCarDialogDescriptionRegisteredValue", this, userID), values);
            }
            container.Add(new CuiLabel
            {
                Text =
                {
                    Text = lang.GetMessage("MsgVehicleDialogTitle", this, userID),
                    FontSize = 12,
                    Color = WhiteColor,
                    Align = TextAnchor.MiddleCenter
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "5 75", OffsetMax = "-5 92" }
            }, _uiVehiclePanel);
            container.Add(new CuiElement
            {
                Name = "Description",
                Parent = _uiVehiclePanel,
                Components =
                {
                    new CuiImageComponent { Color = "0 0 0 0" },
                    new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = $"5 {(notOwner ? "5" : "25")}", OffsetMax = "-5 -20" },
                    new CuiScrollViewComponent
                    {
                        ContentTransform = new CuiRectTransform { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = $"0 -{(11 *  description.Split('\n').Length * 1.2) - (notOwner ? 67 : 47)}", OffsetMax = "0 0" },
                        Vertical = true,
                        MovementType = ScrollRect.MovementType.Elastic,
                        ScrollSensitivity = 20f,
                        HorizontalScrollbar = null,
                        VerticalScrollbar = null
                    }
                }
            });
            container.Add(new CuiLabel
            {
                Text =
                {
                    Text = description,
                    Font = "RobotoCondensed-Regular.ttf",
                    FontSize = 11,
                    Color = WhiteColor,
                    Align = TextAnchor.MiddleLeft
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 0", OffsetMax = "0 0" }
            }, "Description");
            container.Add(new CuiLabel
            {
                Text =
                {
                    Text = descriptionValue,
                    Font = "RobotoCondensed-Regular.ttf",
                    FontSize = 11,
                    Color = WhiteColor,
                    Align = TextAnchor.MiddleRight
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "-5 0" }
            }, "Description");
            if (!notOwner)
            {
                container.Add(new CuiElement
                {
                    Name = "Button",
                    Parent = _uiVehiclePanel,
                    Components =
                    {
                        new CuiImageComponent { Color = "0.41 0.55 0.41 0.8" },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "5 5", OffsetMax = "-5 22" }
                    }
                });
                if (vehicleData.OwnerID == 0uL)
                {
                    container.Add(new CuiLabel
                    {
                        Text =
                        {
                            Text = $"{lang.GetMessage("MsgVehicleDialogLink", this, userID)}:",
                            FontSize = 12,
                            Color = WhiteColor,
                            Align = TextAnchor.MiddleLeft
                        },
                        RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 0", OffsetMax = "-35 0" }
                    }, "Button");
                    container.Add(new CuiElement
                    {
                        Parent = "Button",
                        Components =
                        {
                            new CuiInputFieldComponent
                            {
                                FontSize = 12,
                                Align = TextAnchor.MiddleRight,
                                Color =  WhiteColor,
                                CharsLimit = 4,
                                Command = $"{_commandUI} vehicle link {vehicleData.ID}",
                                IsPassword = true
                            },
                            new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "-7 0" }
                        }
                    });
                }
                else
                {
                    container.Add(new CuiButton
                    {
                        Text =
                        {
                            Text = lang.GetMessage("MsgVehicleDialogUnLink", this, userID),
                            Font = "RobotoCondensed-Regular.ttf",
                            FontSize = 12,
                            Color = WhiteColor,
                            Align = TextAnchor.MiddleCenter
                        },
                        Button =
                        {
                            Command = $"{_commandUI} vehicle unlink {vehicleData.ID}",
                            Color = "1 0.4 0.4 0.8"
                        },
                        RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0" }
                    }, "Button");
                }

                container.Add(new CuiLabel
                {
                    Text =
                    {
                        Text = lang.GetMessage(vehicleData.IsOwner(userID) ? "MsgVehicleDialogOwnerWarning" : "MsgVehicleDialogWarning", this, userID),
                        FontSize = 12,
                        Color = "1 0.4 0.4 0.8",
                        Align = TextAnchor.MiddleRight
                    },
                    RectTransform = { AnchorMin = "1 1", AnchorMax = "1 1", OffsetMin = "-260 147", OffsetMax = "0 181" }
                }, _uiVehiclePanel);
            }
            return container;
        }
		
		private Dictionary<string, string> _ruLang = new Dictionary<string, string>
		{
			["MsgNoteText"] = "Добро пожаловать на наш PvE сервер!\nДанный сервер использует RealPVE плагин.\nПодробней о плагине можно узнать по ссылке: https://codefling.com/plugins/real-pve",
			["CmdAdmin"] = string.Join("\n", new string[]
			{
				"Доступные админ команды:\n",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>autobuy</color> - Управление автоматической покупки монументов, ванильных ивентов и рейд баз",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>config</color> - Управление настройками значений в конфиг файле",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>loot</color> - Управление доступа игроков к сущностям без ограничений",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>monument</color> - Управление монументами",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>perm</color> - Управление пермишенами",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>pickup</color> - Управление доступом на поднятие предметов другого игрока с земли",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>share</color> - Управление доступом на лутание сущностей другими игроками",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>tc</color> - Управление привилегией на строительство",
				"<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>vehicle types</color> - Список всех доступных типов транспортных средств",
				"\n--------------------------------------------------"
			}),
			["CmdAdminAutoBuy"] = string.Join("\n", new string[]
			{
				"Управление автоматической покупки монументов, ванильных ивентов и рейд баз, итоговая цена которых больше 0:\n",
				"<color=#D1CBCB>/adminpve autobuy</color> <color=#D1AB9A>*имяИлиАйди*</color> - Переключение автопокупки для указанного игрока",
				"<color=#D1CBCB>/adminpve autobuy</color> <color=#D1AB9A>force monument/event/rb</color> - Переключение принудительной автопокупки. Если включено настройки игроков будут игнорированы",
				"<color=#D1CBCB>/adminpve autobuy</color> <color=#D1AB9A>clear</color> - Выключение автопокупки для всех",
				"\n--------------------------------------------------"
			}),
			["CmdAdminAutoBuyClear"] = "Автопокупка выключена у {0} игроков!",
			["CmdAdminAutoBuyForce"] = "Автопокупка '{0}' переключена на: {1}!",
			["CmdAdminAutoBuyEnabled"] = "Вы включили автопокупку для игрока '{0}'!",
			["CmdAdminAutoBuyDisabled"] = "Вы выключили автопокупку для игрока '{0}'!",
			["CmdAdminConfig"] = string.Join("\n", new string[]
			{
				"Управление настройками значений в конфиг файле:\n",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>forcepve *булевоеЗначение*(опционально)</color> - Следует ли принудительно включить PvE для сервера?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>forcetutorial *булевоеЗначение*(опционально)</color> - Следует ли принудительно включить поддержку обучающего режима?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>gametips *булевоеЗначение*(опционально)</color> - Следует ли включить GameTips для сообщений?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>perm_limits *булевоеЗначение*(опционально)</color> - Следует ли проверять лимиты при снятии пермишенов?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>safe_death *булевоеЗначение*(опционально)</color> - Следует ли отменять смерть игроков вышедших из игры в сейф зонах?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>plant_privilege *булевоеЗначение*(опционально)</color> - Следует ли предотвратить подбор растений, созданных сервером, в чужой базе?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>heli_damage *булевоеЗначение*(опционально)</color> - Следует ли блокировать урон от патрульного вертолета по строительным блокам и размещаемым объектам?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>handcuffs *булевоеЗначение*(опционально)</color> - Следует ли закреплять порталы(Halloween и Christmas) за первым игроком?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>portals *булевоеЗначение*(опционально)</color> - Следует ли запрещать игрокам заковывать других игроков в наручники?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>backpack_drop *булевоеЗначение*(опционально)</color> - Следует ли предотвратить падение рюкзака при смерти игрока?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>laptop_damage *булевоеЗначение*(опционально)</color> - Следует ли предотвратить урон по ноутбуку от Hackable Crate?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>recycler_safezone *булевоеЗначение*(опционально)</color> - Следует ли убрать штрафы для переработчиков в безопасных зонах?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>item_pickup *булевоеЗначение*(опционально)</color> - Следует ли разрешить всем игрокам подбирать предметы, выброшенные другими игроками? Если включено, личные настройки будут игнорироваться",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>safe_sleep *булевоеЗначение*(опционально)</color> - Следует ли защитить спящих игроков от животных?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>resource_privilege *числовоеЗначение*</color> - Список запрещённых типов добычи ресурсов в чужой базе. 0 - без ограничений, 1 - деревья, 2 - руды, 3 - мясо",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>priceformat *строковоеЗначение*</color> - Формат, который будет использоваться для цен",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>vehicle_marker_time *дробноеЗначение*</color> - Vehicles - Время в секундах для отображения маркера при поиске транспортного средства. Значение 0 отключает маркер",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>antisleeper *дробноеЗначение*</color> - Anti-Sleeper - Время в секундах после которого игрок будет убит, если он отключится, находясь на территории чужой базы. Установите 0, чтобы отключить",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>randomraids *булевоеЗначение*(опционально)</color> - Следует ли включить поддержку плагина 'Npc Random Raids'?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>teamff *булевоеЗначение*(опционально)</color> - Включен ли урон по своим по умолчанию при создании новой команды?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>pvpmarkers *булевоеЗначение*(опционально)</color> - PvP - Следует ли добавить маркеры на карту для PvP зон?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>pvpmarkersname *строковоеЗначение*</color> - PvP - Название маркера на карте",
				"\n--------------------------------------------------"
			}),
			["CmdAdminConfigEditSucceed"] = "{1} - {0}",
			["CmdAdminLoot"] = string.Join("\n", new string[]
			{
				"Управление доступа игроков к сущностям без ограничений:\n",
				"<color=#D1CBCB>/adminpve loot</color> <color=#D1AB9A>*имяИлиАйди*</color> - Переключение доступа без ограничений для указанного игрока",
				"<color=#D1CBCB>/adminpve loot</color> <color=#D1AB9A>self</color> - Переключение доступа без ограничений для себя",
				"<color=#D1CBCB>/adminpve loot</color> <color=#D1AB9A>clear</color> - Отзыв доступа без ограничений у всех игроков",
				"\n--------------------------------------------------"
			}),
			["CmdAdminLootClear"] = "У {0} игроков было отозвано лутание с правами администатора!",
			["CmdAdminLootEnabled"] = "Вам были выданы администраторские права на лутание!",
			["CmdAdminLootDisabled"] = "У вас были отозваны администраторские права на лутание!",
			["CmdAdminLootPlayerEnabled"] = "Игроку {0} были выданы администраторские права на лутание!",
			["CmdAdminLootPlayerDisabled"] = "У игрока {0} были отозваны администраторские права на лутание!",
			["CmdAdminMonument"] = string.Join("\n", new string[]
			{
				"Управление монументами:\n",
				"<color=#D1CBCB>/adminpve monument</color> <color=#D1AB9A>list</color> - Список доступных монументов",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>suffix *булевоеЗначение*(опционально)</color> - Переключение отображения суффикса в названии монумента",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>broadcast *булевоеЗначение*(опционально)</color> - Переключение оповещений о занимании/освобождении монумента",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>time *числовоеЗначение*</color> - Установка времени в секундах отведенное на лутание монумента",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>price *дробноеЗначение*</color> - Установка стоимости за право лутание монумента. Значение 0 делает монумент бесплатным",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>offer *дробноеЗначение*</color> - Установка времени в секундах отведенное на покупку монумента",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>map_mode *числовоеЗначение*</color> - Установка режима отображения маркеров монумента на карте. 0 - выключено, 1 - включено, 2 - включено только при пвп",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>map_circle *булевоеЗначение*(опционально)</color> - Переключение отображения маркера круга монумента на карте",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>pvp *булевоеЗначение*(опционально)</color> - Переключение пвп режима у монумента",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>pvp_delay *дробноеЗначение*</color> - Установка времени в секундах на которое у игрока сохранится пвп режим после выхода из пвп монумента",
				"<color=#D1CBCB>/adminpve monument *айдиМонумента*</color> <color=#D1AB9A>bar_progress *булевоеЗначение*(опционально)</color> - Переключение между TimeProgressCounter и TimeCounter барами у монумента",
				"\n--------------------------------------------------"
			}),
			["CmdAdminMonumentNotFound"] = "Монумент '{0}' не найден!",
			["CmdAdminMonumentOcupied"] = "Монумент '{0}' занят! Вы можете применять подобные изменения только у свободных монументов.",
			["CmdAdminMonumentList"] = string.Join("\n", new string[]
			{
				"Список доступных монументов:\n",
				"{0}",
				"\n<color=#D1CBCB>Примечание:</color> Вместо айди монумента, вы можете указать слово 'this', но вы должны быть внутри монумента. Настройки карго возможна только через слово 'this'",
				"\n--------------------------------------------------"
			}),
			["CmdAdminMonumentWrongValue"] = "Неверный тип значения для этой команды!",
			["CmdAdminMonumentSucceed"] = "Новое значение свойства '{1}' для монумента '{0}': {2}.",
			["CmdAdminPerm"] = string.Join("\n", new string[]
			{
				"Управление пермишенами:\n",
				"<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>add *имяПермишена*</color> - Добавление нового пермишена в список с копированием значений у первого(дефолтного) пермишена из списка. Если имя пермишена начинается с 'realpve' это также зарегистрирует новый пермишен",
				"<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>add *имяПермишена* *имяИсточника*</color> - Добавление нового пермишена в список с копированием значений у существующего пермишена из списка",
				"<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>remove *имяПермишена*</color> - Удаление существующего пермишена из списка",
				"<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>edit *имяПермишена*</color> - Редактирование пермишена",
				"<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>clear</color> - Удаление всех кроме первого пермишена из списка",
				"\n--------------------------------------------------"
			}),
			["CmdAdminPermNotFound"] = "Пермишен '{0}' не найден!",
			["CmdAdminPermNotEnough"] = "Для удаления пермишенов, необходимо иметь хотя бы больше чем 1 пермишен!",
			["CmdAdminPermCleared"] = "Успешно удалено {0} пермишенов!",
			["CmdAdminPermAlreadyExists"] = "Пермишен '{0}' уже имеется!",
			["CmdAdminPermAdded"] = "Пермишен '{0}' был успешно добавлен!",
			["CmdAdminPermRemoved"] = "Пермишен '{0}' успешно удален!",
			["CmdAdminPermEdit"] = string.Join("\n", new string[]
			{
				"Редактирование пермишена:\n",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>queue</color> - Переключение разрешения подключения к серверу в обход очереди",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>unlockRespawn</color> - Переключение наличия точки респавна в городе",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>beds *числовоеЗначение*</color> - Ограничение по количеству доступных кроватей",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>shelters *числовоеЗначение*</color> - Ограничение по количеству доступных шелтеров",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>turrets *числовоеЗначение*</color> - Ограничение по количеству доступных турелей",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>hackable *дробноеЗначение*</color> - Кол-во секунд(0-900) которое пропустится при открытии ящика с таймером",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>monuments *дробноеЗначение*</color> - Множитель цены для монументов",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>events *дробноеЗначение*</color> - Множитель цены для ванильных ивентов",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>rb_limit *числовоеЗначение*</color> - Ограничение по количеству одновременно доступных рейд баз",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>rb_mult *дробноеЗначение*</color> - Множитель цены для рейд баз",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена*</color> <color=#D1AB9A>vehicles *типТС*</color> - Настройка транспортных средств",
				"\n--------------------------------------------------"
			}),
			["CmdAdminPermEditSucceed"] = "Новое значение свойства '{1}' для пермишена '{0}': {2}.",
			["CmdAdminPermEditVehicles"] = string.Join("\n", new string[]
			{
				"Настройка транспортных средств:\n",
				"<color=#D1CBCB>/adminpve vehicle types</color> - Список доступных типов транспортных средств",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена* vehicles *типТС*</color> <color=#D1AB9A>limit *числовоеЗначение*</color> - Ограничение по количеству доступных транспортных средств по типу",
				"<color=#D1CBCB>/adminpve perm edit *имяПермишена* vehicles *типТС*</color> <color=#D1AB9A>price *дробноеЗначение*</color> - Цена для регистрации транспортного средства по типу",
				"\n--------------------------------------------------"
			}),
			["CmdAdminPermEditVehiclesSucceed"] = "Новое значение свойства '{2}' для типа '{1}' у пермишена '{0}': {3}.",
			["CmdAdminPickup"] = string.Join("\n", new string[]
			{
				"Управление доступом на поднятие предметов другого игрока с земли:\n",
				"<color=#D1CBCB>/adminpve pickup</color> <color=#D1AB9A>*имяИлиАйди*</color> - Переключение доступа для поднятия предметов игрока с земли для указанного игрока",
				"<color=#D1CBCB>/adminpve pickup</color> <color=#D1AB9A>clear</color> - Отзыв доступа для поднятия предметов игрока с земли у всех игроков",
				"\n--------------------------------------------------"
			}),
			["CmdAdminPickupClear"] = "Удалено {0} игроков из списка, позволяющего всем поднимать их вещи!",
			["CmdAdminPickupEnabled"] = "Доступ для поднятия предметов игрока '{0}' с земли был включен!",
			["CmdAdminPickupDisabled"] = "Доступ для поднятия предметов игрока '{0}' с земли был выключен!",
			["CmdAdminShare"] = string.Join("\n", new string[]
			{
				"Управление доступом на лутание сущностей другими игроками(за пределами группы):\n",
				"<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>status *айдиСущности*(опционально)</color> - Вывод информации о настройках сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>add *имяИлиАйди* *айдиСущности*(опционально)</color> - Добавить указаного игрока в список сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>remove *имяИлиАйди* *айдиСущности*(опционально)</color> - Удалить указаного игрока из списока сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>toggle *айдиСущности*(опционально)</color> - Переключить назначение списка сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>delete *айдиСущности*(опционально)</color> - Удалить настройки сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>clear *имяИлиАйди*(опционально)</color> - Удалить настройки всех сущностей, либо всех сущностей указаного игрока",
				"\n--------------------------------------------------"
			}),
			["CmdAdminShareStatusDefault"] = string.Join("\n", new string[]
			{
				"Игрок '{1}' <u>запретил</u> доступ к сущности '{0}' <u>всем кроме</u>:",
				"{2}",
				"\n--------------------------------------------------"
			}),
			["CmdAdminShareStatusEveryOne"] = string.Join("\n", new string[]
			{
				"Игрок '{1}' <u>разрешил</u> доступ к сущности '{0}' всем кроме</u>:",
				"{2}",
				"\n--------------------------------------------------"
			}),
			["CmdAdminShareGranted"] = "Игрок '{1}' <u>разрешил</u> игроку '{2}' доступ к сущности '{0}'!",
			["CmdAdminShareDenied"] = "Игрок '{1}' <u>запретил</u> игроку '{2}' доступ к сущности '{0}'!",
			["CmdAdminShareToggleDefault"] = "Игрок '{1}' <u>запретил всем</u> игрокам доступ к сущности '{0}', <u>кроме тех кто в списке</u>!",
			["CmdAdminShareToggleEveryOne"] = "Игрок '{1}' <u>разрешил всем</u> игрокам доступ к сущности '{0}', <u>кроме тех кто в списке</u>!",
			["CmdAdminShareDelete"] = "Разрешение доступа к сущности '{0}' игрока '{1}' было успешно удалено!",
			["CmdAdminShareClearTarget"] = "У игрока '{1}' удалено {0} разрешений доступа к сущностям!",
			["CmdAdminShareClear"] = "Удалено {0} разрешений доступа к сущностям!",
			["CmdAdminTC"] = string.Join("\n", new string[]
			{
				"Управление привилегией на строительство:\n",
				"<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>add self/*айдиСущности* *имяИлиАйди*(опционально)</color> - Добавить себя или указанного игрока в строительную привилегию, в которой находитесь вы или указанная сущность",
				"<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>remove self/*айдиСущности* *имяИлиАйди*(опционально)</color> - Удалить себя или указанного игрока из строительной привилегии, в которой находитесь вы или указанная сущность",
				"<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>clear self/*айдиСущности*</color> - Очистить список авторизованных игроков в строительной привилегии, в которой находитесь вы или указанная сущность",
				"<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>info self/*айдиСущности*</color> - Получить информацию о строительной привилегии, в которой находитесь вы или указанная сущность",
				"\n--------------------------------------------------"
			}),
			["CmdAdminTCAdd"] = "Вы успешно авторизовали игрока '{1}' в строительной привилегии '{0}'!",
			["CmdAdminTCRemove"] = "Вы успешно удалили авторизацию игрока '{1}' в строительной привилегии '{0}'!",
			["CmdAdminTCClear"] = "Из строительной привилегии '{0}' успешно удалено {1} игроков!",
			["CmdAdminTCInfo"] = string.Join("\n", new string[]
			{
				"Информация о строительной привилегии '{0}':\n",
				"Время в минутах до начала гниения: {1}",
				"Список авторизованных игроков({2}):",
				"{3}",
				"\n--------------------------------------------------"
			}),
			["CmdAdminVehicleTypes"] = string.Join("\n", new string[]
			{
				"Список доступных типов транспортных средств:\n",
				"{0}",
				"\n--------------------------------------------------"
			}),
			["CmdMainEditWrongValue"] = "Неверный тип значения для этой команды!",
			["CmdPlayerNotFound"] = "Игрок '{0}' не найден! Вы должны указать имя или ID игрока.",
			["CmdMultiplePlayers"] = "По значению '{0}' найдено несколько игроков: {1}",
			["CmdEntityNotFound"] = "Сущность не найдена! Вы должны указать ID сущности или смотреть прямо на нее.",
			["CmdEntityNotOwned"] = "Сущность '{0}' не имеет владельца!",
			["CmdEntityNotOwner"] = "Вы не являетесь владельцем этой сущности!",
			["CmdPrivilegeNotFound"] = "Строительная привилегия не найдена!",
			["CmdMain"] = string.Join("\n", new string[]
			{
				"Доступные команды:\n",
				"<color=#D1CBCB>/realpve</color> <color=#D1AB9A>autobuy</color> - Переключение автоматической покупки монументов, ванильных ивентов и рейд баз, итоговая цена которых больше 0",
				"<color=#D1CBCB>/realpve</color> <color=#D1AB9A>pickup</color> - Переключение доступа на поднятие ваших предметов с земли для всех игроков",
				"<color=#D1CBCB>/realpve</color> <color=#D1AB9A>share</color> - Управление доступом на лутание ваших сущностей другими игроками",
				"<color=#D1CBCB>/realpve</color> <color=#D1AB9A>team</color> - Управление вашей командой",
				"<color=#D1CBCB>/realpve</color> <color=#D1AB9A>vehicle</color> - Управление вашими транспортными средствами",
				"\n--------------------------------------------------"
			}),
			["CmdMainAutoBuyEnabled"] = "Вы включили автопокупку монументов, ванильных ивентов и рейд баз, итоговая цена которых больше 0!",
			["CmdMainAutoBuyDisabled"] = "Вы выключили автопокупку монументов, ванильных ивентов и рейд баз, итоговая цена которых больше 0!",
			["CmdMainPickupEnabled"] = "Вы разрешили поднятие ваших предметов для всех игроков!",
			["CmdMainPickupDisabled"] = "Вы запретили поднятие ваших предметов для всех игроков!",
			["CmdMainShare"] = string.Join("\n", new string[]
			{
				"Управление доступом на лутание ваших сущностей другими игроками(за пределами команды):\n",
				"<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>status *айдиСущности*(опционально)</color> - Вывод информации о настройках сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>add *имяИлиАйди* *айдиСущности*(опционально)</color> - Добавить указаного игрока в список сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>remove *имяИлиАйди* *айдиСущности*(опционально)</color> - Удалить указаного игрока из списока сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>toggle *айдиСущности*(опционально)</color> - Переключить назначение списка сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>delete *айдиСущности*(опционально)</color> - Удалить настройки сущности, на которую вы смотрите или указали",
				"<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>clear</color> - Удалить настройки всех ваших сущностей",
				"\n--------------------------------------------------"
			}),
			["CmdMainShareStatusDefault"] = string.Join("\n", new string[]
			{
				"Вы <u>запретили</u> доступ к сущности '{0}' <u>всем кроме</u>:",
				"{1}",
				"\n--------------------------------------------------"
			}),
			["CmdMainShareStatusEveryOne"] = string.Join("\n", new string[]
			{
				"Вы <u>разрешили</u> доступ к сущности '{0}' всем кроме</u>:",
				"{1}",
				"\n--------------------------------------------------"
			}),
			["CmdMainShareGranted"] = "Вы <u>разрешили</u> игроку '{1}' доступ к сущности '{0}'!",
			["CmdMainShareDenied"] = "Вы <u>запретили</u> игроку '{1}' доступ к сущности '{0}'!",
			["CmdMainShareToggleDefault"] = "Вы <u>запретили всем</u> игрокам доступ к сущности '{0}', <u>кроме тех кто в списке</u>!",
			["CmdMainShareToggleEveryOne"] = "Вы <u>разрешили всем</u> игрокам доступ к сущности '{0}', <u>кроме тех кто в списке</u>!",
			["CmdMainShareDelete"] = "Разрешение доступа к сущности '{0}' было успешно удалено!",
			["CmdMainShareClear"] = "Удалено {0} разрешений доступа к сущностям!",
			["CmdMainTeamNotFound"] = "Для использования этой команды вы должны быть в группе!",
			["CmdMainTeamNotLeader"] = "Для использования этой команды вы должны быть лидером группы!",
			["CmdMainTeam"] = string.Join("\n", new string[]
			{
				"Управление вашей командой:\n",
				"<color=#D1CBCB>/realpve team</color> <color=#D1AB9A>ff</color> - Переключение возможности наносить урон по своим союзникам",
				"\n--------------------------------------------------"
			}),
			["CmdMainTeamFireEnabled"] = "{0} включил дружественный огонь!",
			["CmdMainTeamFireDisabled"] = "{0} выключил дружественный огонь!",
			["CmdMainVehicle"] = string.Join("\n", new string[]
			{
				"Управление вашими транспортными средствами:\n",
				"<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>list</color> - Список айди всех ваших транспортных средств",
				"<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>find *айдиТС*(опционально)</color> - Помощь в поиске транспортного средства, на которое вы смотрите или указали",
				"<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>unlink *айдиТС*(опционально)</color> - Снятие с регистрации транспортное средство, на которое вы смотрите или указали",
				"<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>clear</color> - Снятие регистрации со всех ваших транспортных средств",
				"\n--------------------------------------------------"
			}),
			["CmdMainVehicleNotFound"] = "Транспортное средство не найдено!",
			["CmdMainVehicleClear"] = "Снято с регистрации {0} транспортных средств!",
			["CmdMainVehicleList"] = string.Join("\n", new string[]
			{
				"Список айди ваших транспортных средств:\n",
				"{0}",
				"\n--------------------------------------------------"
			}),
			["CmdMainVehicleListEmpty"] = "У вас нет ни одного транспортного средства!",
			["CmdMainVehicleFind"] = "Ваше транспортное средство '{0}' находится в квадрате {1}!",
			["MsgCantInteract"] = "Вы не можете взаимодействовать с чужими вещами!",
			["MsgCantInteractPlayer"] = "Вы не можете взаимодействовать с другими игроками, только с друзьями!",
			["MsgCantGatherInBase"] = "Вы не можете собирать ресурсы в чужих базах!",
			["MsgCantPickup"] = "Вы не можете подбирать чужие вещи!",
			["MsgCantRenameBed"] = "Вы не можете переименовывать чужие кровати!",
			["MsgCantAdministerVending"] = "Вы не можете управлять чужими торговыми автоматами!",
			["MsgCantInteractWeaponRack"] = "Вы не можете взаимодействовать с чужими оружейными стойками!",
			["MsgCantHandcuffing"] = "Вы не можете заковывать других игроков в наручники за пределами ПвП зоны!",
			["MsgPrivlidgeClear"] = "Из шкафа выписано {0} ироков.",
			["MsgPrivlidgeClearEmpty"] = "Кроме вас в шкафу ни кто не авторизован.",
			["MsgFree"] = "Бесплатно",
			["MsgNoDate"] = "пусто",
			["MsgEconomicsNotEnough"] = "Не достаточно средств!",
			["MsgPvPEnter"] = "Вы вошли в ПвП зону! Здесь вас могут убить и залутать!",
			["MsgPvPBar"] = "Зона ПвП!",
			["MsgPvPDelay"] = "Вы покинули ПвП зону, но ПвП останется активным еще {0} секунд!",
			["MsgPvPDelayBar"] = "ПвП еще активно:",
			["MsgMonumentOccupied"] = "{1} занял {0} на {2} минут.",
			["MsgMonumentFree"] = "{0} можно лутать!",
			["MsgMonumentOfferTitle"] = "Откройте сокровища {0}!",
			["MsgMonumentOfferDescription"] = "Нажми на уведомление для оплаты {0}.\nИ разблокируй доступ к неизведанным богатствам!",
			["MsgMonumentCantPickup"] = "Вы не можете подбирать вещи в чужих монументах!",
			["MsgMonumentLooterDeath"] = "Вы умерли во время лутания {0}. У вас есть {1} секунд.",
			["MsgMonumentLooterExit"] = "Вы покинули монумент. У вас есть {0} секунд на возвращение!",
			["MsgMonumentLooterRemoved"] = "Время вышло! Вы были удалены из монумента!",
			["MsgMonumentFriendEnter"] = "Вам предоставлено право на лут вне очереди, так как ваш друг '{0}' приобрёл это право.",
			["MsgMonumentQueueNotFree"] = "Вас добавили в очередь на лутание({0}/{1}). Стоимость лутания: {2}",
			["MsgMonumentQueue"] = "Вас добавили в очередь на лутание({0}/{1}). Лутание бесплатное.",
			["MsgMonumentNotInQueue"] = "Вас нет в очереди! Вам необходимо перезайти в монумент!",
			["MsgMonumentIsPvP"] = "Зона ПвП!",
			["MsgMonumentNoAccess"] = "нет доступа",
			["MsgVehicleDialogTitle"] = "ГИБДД",
			["MsgVehicleDialogDescription"] = "ID: \nТип: \nСтоимость регистрации: \nКатегория: ",
			["MsgVehicleDialogDescriptionValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{4}</b>\n<b>{2}</b>",
			["MsgVehicleDialogDescriptionRegistered"] = "ID: \nТип: \nДата регистрации: \nКатегория: ",
			["MsgVehicleDialogDescriptionRegisteredValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{3}</b>\n<b>{2}</b>",
			["MsgVehicleDialogDescriptionNotOwner"] = "ID: \nВладелец: \nДата регистрации: \nТип: \nКатегория: ",
			["MsgVehicleDialogDescriptionNotOwnerValue"] = "<b>{0}</b>\n<b>{4}</b>\n<b>{3}</b>\n<b>{1}</b>\n<b>{2}</b>",
			["MsgVehicleCarDialogDescription"] = "ID: \nТип: \nСтоимость регистрации: \nКатегория: ",
			["MsgVehicleCarDialogDescriptionValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{4}</b>\n<b>{2}</b>",
			["MsgVehicleCarDialogDescriptionRegistered"] = "ID: \nТип: \nДата: \nКатегория: ",
			["MsgVehicleCarDialogDescriptionRegisteredValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{3}</b>\n<b>{2}</b>",
			["MsgVehicleCarDialogDescriptionNotOwner"] = "ID: \nВладелец: \nДата: \nТип: \nКатегория: ",
			["MsgVehicleCarDialogDescriptionNotOwnerValue"] = "<b>{0}</b>\n<b>{4}</b>\n<b>{3}</b>\n<b>{1}</b>\n<b>{2}</b>",
			["MsgVehicleCarGarageEmpty"] = "Подъемник пустой!",
			["MsgVehicleDialogLink"] = "Поставить на учет",
			["MsgVehicleDialogUnLink"] = "Снять с учета",
			["MsgVehicleDialogOwnerWarning"] = "Снятие всех водительских модулей приведет к отмене регистрации!",
			["MsgVehicleDialogWarning"] = "Регистрация возможна только при наличии водительского модуля!",
			["MsgVehicleDialogIncorrectPassword"] = "Пароль должен состоять из 4-х цифр!",
			["MsgVehicleNotOwner"] = "Вы не являетесь владельцем!",
			["MsgVehicleWrongName"] = "Не верный формат имени для транспортного средства!",
			["MsgVehicleNewName"] = "Ваше транспортное средство переименовано в: {0}!",
			["MsgVehicleCantInteract"] = "Вы не являетесь владельцем или другом данного транспортного средства!",
			["MsgVehicleCantSeat"] = "Вы не можете сесть на это место!",
			["MsgVehicleCantPush"] = "Вы не можете толкать данное транспортное средство в этом месте!",
			["MsgVehicleCantPull"] = "Вы не можете тянуть данное транспортное средство в этом месте!",
			["MsgVehicleCantOpen"] = "Вы не можете открыть дверь данного транспортного средства!",
			["MsgVehicleCantClose"] = "Вы не можете закрыть дверь данного транспортного средства!",
			["MsgVehicleCantEngineStart"] = "Вы не можете завести двигатель данного транспортного средства!",
			["MsgVehicleCantBalloonToggle"] = "Вы не можете управлять данным воздушным шаром!",
			["MsgVehicleCantLead"] = "Вы не можете вести за собой данную лошадь в этом месте!",
			["MsgVehicleCantTow"] = "Вы не можете запрячь данную лошадь в это осадное орудие!",
			["MsgVehicleCantDetach"] = "Вы не можете отцепить данную лошадь от этого осадного орудия!",
			["MsgVehicleCantSaddleSwap"] = "Вы не можете заменить седло у данной лошади!",
			["MsgVehicleLinked"] = "{0} успешно привязан(а)! У вас {1} из {2} доступных.",
			["MsgVehicleUnLinked"] = "{0} успешно отвязан(а)!",
			["MsgVehicleFailedDeauthorize"] = "Вы можете выписаться только при отвязки транспорта от вас.",
			["MsgVehicleLimit"] = "Лимит превышен! Вы использовали {1} из {2} доступных регистрации.",
			["MsgVehicleDestroyed"] = "Ваше транспортное средство {0}({1}) было уничтожено!",
			["MsgVehicleTugboatAuthorization"] = "Для авторизации в буксире, его необходимо поставить на учет!",
			["MsgVehicleLandVehicle"] = "Наземный",
			["MsgVehicleAirVehicle"] = "Воздушный",
			["MsgVehicleWaterVehicle"] = "Водный",
			["MsgVehicleWinterVehicle"] = "Зимний",
			["MsgVehicleSiegeVehicle"] = "Осадное орудие",
			["MsgVehicleTrainVehicle"] = "ЖД",
			["MsgVehicleHorse"] = "Лошадь",
			["MsgVehicleBike"] = "Велосипед",
			["MsgVehicleMotorBike"] = "Мотоцикл",
			["MsgVehicleCar"] = "Машина",
			["MsgVehicleBalloon"] = "Воздушный шар",
			["MsgVehicleMinicopter"] = "Мини коптер",
			["MsgVehicleTransportHeli"] = "Корова",
			["MsgVehicleAttackHeli"] = "Боевой вертолет",
			["MsgVehicleRowBoat"] = "Лодка",
			["MsgVehicleRHIB"] = "Патрульная лодка",
			["MsgVehicleTugBoat"] = "Буксир",
			["MsgVehicleSubmarineOne"] = "Маленькая подлодка",
			["MsgVehicleSubmarineTwo"] = "Подлодка",
			["MsgVehicleSnowmobile"] = "Снегоход",
			["MsgVehicleBatteringRam"] = "Осадный таран",
			["MsgVehicleTrain"] = "Поезд",
			["MsgEventOccupied"] = "Ивент '{0}' уже занят игроком '{1}'!",
			["MsgEventOfferTitle"] = "Займите '{0}'!",
			["MsgEventOfferDescription"] = "Нажми на уведомление для оплаты {0}.\nИ разблокируй доступ к неизведанным богатствам!",
			["MsgEventNewLooter"] = "Вы успешно заняли '{0}' ивент! Лимит смертей {1}(на команду), лимит по времени {2} мин",
			["MsgEventDeath"] = "Игрок '{1}' был убит! Счетчик смертей для ивента '{0}': {2}/{3}",
			["MsgEventDeathLimit"] = "Игрок '{1}' был убит! Тем самым исчерпав лимит смертей для ивента '{0}'!",
			["MsgEventTimeLimit"] = "Время отведенное на ивент '{0}' истекло!",
			["MsgEventComplete"] = "Ивент '{0}' был уничтожен в координатах: {1}!",
			["MsgEventPatrolHelicopter"] = "Патрульный вертолет",
			["MsgEventBradleyAPC"] = "Танк Бредли",
			["MsgRaidableBasesDisabled"] = "Эта Рейд база выключена или не найдена!",
			["MsgRaidableBasesOccupied"] = "Эта Рейд база уже занята игроком {0}!",
			["MsgRaidableBasesLimit"] = "Лимит превышен! У вас {0} из {1} доступных Рейд баз.",
			["MsgRaidableBasesPurchaseStart"] = "Оплата прошла! Ожидайте...",
			["MsgRaidableBasesPurchased"] = "Вы успешно приобрели Рейд базу!",
			["MsgRaidableBasesPurchaseFailed"] = "Вам не удалось приобрести Рейд базу! Деньги возвращены.",
			["MsgRaidableBasesOfferTitle"] = "Займите Рейд базу уровня: {0}!",
			["MsgRaidableBasesOfferDescription"] = "Нажми на уведомление для оплаты {0}.\nИ разблокируй доступ к неизведанным богатствам!",
			["MsgRaidableBasesBarText"] = "Уровень: {0}",
			["MsgRaidableBasesBarTextLootRemaining"] = "Осталось лута",
			["MsgRaidableBasesBarTextLootCompleted"] = "Выполнено",
			["MsgRaidableBasesBarNoAccess"] = "нет доступа",
			["MsgRaidableBasesEasy"] = "Легко",
			["MsgRaidableBasesMedium"] = "Средне",
			["MsgRaidableBasesHard"] = "Сложно",
			["MsgRaidableBasesExpert"] = "Эксперт",
			["MsgRaidableBasesNightmare"] = "Кошмар",
			["MsgRaidableBasesIsPvP"] = "Зона ПвП!",
			["MsgSurvivalArena"] = "Арена",
			["MsgArenaWhilePvP"] = "Вы не можете попасть на арену пока у вас имеется активное ПвП!"
		};
		
		void OnPlayerEnteredMonument(string monumentID, BasePlayer player, string category, string oldMonumentID)
        {
			if (_monumentsConfig.TrackedCategories.Contains(category) && _monumentsList.TryGetValue(monumentID, out var monumentData))
            {
				MonumentData oldMonumentData = null;
				if (!string.IsNullOrWhiteSpace(oldMonumentID) && _monumentsList.TryGetValue(oldMonumentID, out oldMonumentData))
                    oldMonumentData.OnPlayerExit(player);
                monumentData.OnPlayerEnter(player);
            }
        }
		
		object OnEntityEnter(TargetTrigger trigger, BasePlayer target)
        {
			ulong userID = target.userID;
			if (!userID.IsSteamId() && target is BasePet pet)
			{
				var owner = pet.Brain?.OwningPlayer;
				userID = owner.IsValid() ? owner.userID : pet.OwnerID;
			}
			if (userID.IsSteamId())
			{
				var entity = trigger.GetComponentInParent<BaseEntity>();
				if (entity != null)
					return HandleEntityEnterTrap(entity, userID) ? null : true;
			}
			return null;
		}
		
		private static string GetConfigPropertyName(string fieldName)
        {
			if (!string.IsNullOrWhiteSpace(fieldName))
			{
				var jsonPropAttribute = _config.GetType().GetField(fieldName, BindingFlags.Public | BindingFlags.Instance)?.GetCustomAttribute<JsonPropertyAttribute>();
				if (jsonPropAttribute != null)
					return jsonPropAttribute.PropertyName;
			}
			return string.Empty;
		}
		object CanUnlock(BasePlayer player, BaseLock baseLock) => !baseLock.OwnerID.IsSteamId() || !baseLock.IsValid() || _unrestrictedLooters.Contains(player.userID) || IsEntityInPvP(player.userID, baseLock.net.ID.Value) ? null : player.TasirMumkin(baseLock.OwnerID);
		object CanLootEntity(BasePlayer player, CookingWorkbench cookingTable) => CanLootStorage(player, cookingTable);
		
		private object HandleDamageToVehicle(BaseCombatEntity vehicle, HitInfo info)
        {
			if (info == null || info.damageTypes == null || info.damageTypes.GetMajorityDamageType() == DamageType.Decay || !vehicle.IsValid() || _vehiclesList == null || !_vehiclesList.TryGetValue(vehicle.net.ID.Value, out var vehicleData) || vehicleData == null || !vehicleData.OwnerID.IsSteamId()) return null;
			switch (TryGetAttacker(info, out var initiator))
            {
				case AttackerIndex.PatrolHelicopter:
                    if (_eventsList.TryGetValue(initiator.net.ID.Value, out var eventData) && (eventData.Owner == null || !UrishMumkin(eventData.Owner, vehicleData.OwnerID)))
                        info.Urma();
                    break;
				case AttackerIndex.BasePlayer:
                    var attacker = (BasePlayer)initiator;
					if (attacker.userID.IsSteamId() && !IsEntityInPvP(attacker.userID, vehicle.net.ID.Value) && vehicleData.CanInteract(attacker) != null)
						info.Urma();
					break;
				case AttackerIndex.PlayerOwnerMissing:
					info.Urma();
					break;
			}
			return null;
		}
		
		void RandomRaidEventEnd(Vector3 pos)
		{
			string posStr = pos.ToString();
			if (_randomRaidsList.TryGetValue(posStr, out var rrData))
				rrData.Destroy();
			_randomRaidsList.Remove(posStr);
		}
		
		private bool TryGetRaidBaseByUser(ulong userID, out RBData result)
        {
            result = null;
            if (!_rbsConfig.IsEnabled) return false;
			foreach (var rbData in _rbList.Values)
            {
				if (rbData.IsPlayerInside(userID))
                {
					result = rbData;
					break;
				}
			}
            return result != null;
        }
		void OnEntityKill(Minicopter minicopter) => HandleVehicleKill(minicopter);

		private class Configuration
		{
			[JsonProperty(PropertyName = "Chat command")]
			public string Command = string.Empty;

			[JsonProperty(PropertyName = "Chat admin command")]
			public string AdminCommand = string.Empty;

			[JsonProperty(PropertyName = "Is it worth forcibly implementing PvE for a server?")]
			public bool Force_PvE = true;
			
			[JsonProperty(PropertyName = "Is it worth forcing the tutorial mode support?")]
            public bool Force_Tutorial = true;
			
			[JsonProperty(PropertyName = "Is it worth enabling GameTips for messages?")]
			public bool GameTips_Enabled = true;
			
			[JsonProperty(PropertyName = "Is it worth rechecking the limits when removing permissions?")]
            public bool RecheckPermissionLimits = true;
			
			[JsonProperty(PropertyName = "Is it worth preventing death on logout in safe zones?")]
            public bool PreventSafeZoneLogoutDeath = true;
			
			[JsonProperty(PropertyName = "Is it worth preventing the pickup of plants spawned by the server in someone else's building privilege zone?")]
			public bool PreventPickUpCollectible = false;

			[JsonProperty(PropertyName = "Is it worth forcibly blocking damage from the patrol helicopter to building blocks and deployables?")]
			public bool PreventPatrolToDeployables = false;
			
			[JsonProperty(PropertyName = "Is it worth preventing players from handcuffing others?")]
			public bool PreventHandcuffing = true;
			
			[JsonProperty(PropertyName = "Is it worth assigning portals(Halloween and Christmas) to the first player?")]
            public bool AssignPortals = true;
			
			[JsonProperty(PropertyName = "Is it worth preventing a backpack from dropping upon player death?")]
			public bool PreventBackpackDrop = true;

			[JsonProperty(PropertyName = "Is it worth preventing damage to the laptop of the Hackable Crate?")]
			public bool PreventLaptopAttack = true;

			[JsonProperty(PropertyName = "Is it worth removing the penalties for recyclers in safe zones?")]
			public bool RecyclerNoPenalties = true;
			
			[JsonProperty(PropertyName = "Is it worth allowing all players to pick up items dropped by others? If enabled, personal settings will be ignored")]
			public bool NoPickupRestrictions = false;
			
			[JsonProperty(PropertyName = "Is it worth protecting sleeping players from animals?")]
            public bool ProtectSleepers = true;
			
			[JsonProperty(PropertyName = "List of forbidden resource gathering types in someone else's building privilege area. 0 - no restrictions, 1 - trees, 2 - ores, 3 - flesh")]
			public HashSet<int> BuildingPrivilegeGathering;
			
			[JsonProperty(PropertyName = "The format that will be used for prices")]
			public string PriceFormat = string.Empty;

			[JsonProperty(PropertyName = "Vehicles - Time in seconds to display the marker when searching for a vehicle. A value of 0 disables the marker")]
			public float VehiclesMarkerTime = 15f;

			[JsonProperty(PropertyName = "Anti-Sleeper - Time in seconds after which a player will be killed if they disconnect while inside someone else's Building Privilege. Set to 0 to disable")]
			public float AntiSleeper = 1200f;

			[JsonProperty(PropertyName = "Is it worth enabling support for the 'Npc Random Raids' plugin?")]
			public bool RandomRaids_Enabled = true;
			
			[JsonProperty(PropertyName = "List of language keys for creating language files(excluding ru)")]
            public List<string> LanguageKeys;
			
			[JsonProperty(PropertyName = "Is friendly fire enabled by default when creating a new team?")]
			public bool PvPTeamFF = false;

			[JsonProperty(PropertyName = "PvP - Is it worth adding map markers for PvP zones?")]
			public bool PvPMapMarkers = true;

			[JsonProperty(PropertyName = "PvP - Name of the map maker")]
			public string PvPMapMarkersName = string.Empty;

			[JsonProperty(PropertyName = "PvP - Settings for the status bar")]
			public BarSettings BarPvP;

			[JsonProperty(PropertyName = "PvP - Settings for the progress status bar")]
			public ProgressBarSettings ProgressBarPvP;

			[JsonProperty(PropertyName = "Wipe ID")]
			public string WipeID = string.Empty;

			public Oxide.Core.VersionNumber Version;
		}
		
		public class PlayerPvP
        {
			public double DelayEnd { get; set; } = 0d;
			public List<string> ActiveZones { get; set; } = new List<string>();
			public string LastZone { get; set; } = string.Empty;
		}
		private bool IsFriend(ulong playerID, ulong friendID) => _friendsIsLoaded && (bool)(Friends.Call(Str_IsFriend, playerID, friendID) ?? false);
		
		void OnEntityKill(BuildingPrivlidge privlidge)
		{
			OnEntityExitPVP(privlidge);
			if (_config.AntiSleeper > 0f && privlidge.lastNoiseTime != DateTimeOffset.UtcNow.Day)
			{
				var players = Pool.Get<List<BasePlayer>>();
				Vis.Entities(privlidge.transform.position, 20f, players);
				foreach (var sleeper in players)
					sleeper.CancelInvoke(sleeper.ScheduledDeath);
				Pool.FreeUnmanaged(ref players);
			}
		}
		
		public static bool ModSiegeExplosiveHurt(DeployableSiegeExplosive __instance, HitInfo info)
        {
            if (Instance != null && __instance.IsValid() && !__instance.isClient && !__instance.HasFlag(BaseEntity.Flags.Reserved1) && Instance.CancelDamageToEntity(__instance, info))
				return false;
			return true;
        }
		
		void OnRandomRaidHeliSpawned(Vector3 pos, PatrolHelicopter patrol)
        {
			string rrPos = new Vector3(pos.x, pos.y - 100f, pos.z).ToString();
			if (_randomRaidsList.TryGetValue(rrPos.ToString(), out var rrData))
			{
				_rrallPatrols[patrol.net.ID.Value] = rrData;
				rrData.PatrolID = patrol.net.ID.Value;
			}
		}
		
		private static string ReplacePlaceholders(string str, params string[] args)
        {
            for (int i = 0; i < args.Length; i++)
            {
                if (args[i] != null)
                    str = str.Replace($"{{{i}}}", args[i]);
            }
            return str;
        }
		
		void OnEntityKill(BaseEntity entity) => OnEntityExitPVP(entity);
		object CanLootEntity(BasePlayer player, Stocking stocking) => CanLootStorage(player, stocking);
		

        object OnNpcConversationRespond(NPCTalking npcTalking, BasePlayer player, ConversationData conversationData, ConversationData.ResponseNode responseNode)
		{
			VehicleType type;
            switch (responseNode.resultingSpeechNode)
            {
                case "habbuy":
                    type = VehicleType.Balloon;
                    break;
                case "minicopterbuy":
                    type = VehicleType.Minicopter;
                    break;
                case "transportbuy":
                    type = VehicleType.TransportHeli;
                    break;
                case "attackbuy":
                    type = VehicleType.AttackHeli;
                    break;
                case "pay_rowboat":
                    type = VehicleType.RowBoat;
                    break;
                case "pay_rhib":
                    type = VehicleType.RHIB;
                    break;
                case "pay_sub":
                    type = VehicleType.SubmarineOne;
                    break;
                case "pay_duosub":
                    type = VehicleType.SubmarineTwo;
                    break;
                default:
                    return null;
            }
			
			int limit = GetVehicleLimit(player.UserIDString, type), totalCars = CountVehiclesByType(player.userID, type);
            if (limit >= 0 && totalCars >= limit)
            {
				SendMessageText(player, string.Format(lang.GetMessage("MsgVehicleLimit", this, player.UserIDString), string.Empty, totalCars, limit));
				npcTalking.ForceEndConversation(player);
            }
            else
            {
                bool canPay = true;
                if (_economicsIsLoaded)
                    canPay = GetBalance(player.UserIDString) >= GetVehiclePrice(player.UserIDString, type);
                if (!canPay)
                {
					SendMessageText(player, lang.GetMessage("MsgEconomicsNotEnough", this, player.UserIDString));
					npcTalking.ForceEndConversation(player);
                }
			}
            return null;
		}
		
		private void ToggleRandomRaids(bool isLoaded)
        {
			if (!isLoaded)
			{
                Unsubscribe(nameof(OnRandomRaidStart));
                Unsubscribe(nameof(RandomRaidEventEnd));
                Unsubscribe(nameof(OnRandomRaidRaiderSpawned));
                Unsubscribe(nameof(OnRandomRaidHeliSpawned));
                Unsubscribe(nameof(OnRandomRaidWin));
                
                Unsubscribe(nameof(OnBuildingSplit));
			}
			else if (_config.RandomRaids_Enabled)
			{
				Subscribe(nameof(OnRandomRaidStart));
                Subscribe(nameof(RandomRaidEventEnd));
                Subscribe(nameof(OnRandomRaidRaiderSpawned));
                Subscribe(nameof(OnRandomRaidHeliSpawned));
                Subscribe(nameof(OnRandomRaidWin));
                
                Subscribe(nameof(OnBuildingSplit));
			}
		}
		
		
        
        
        void OnEventJoined(BasePlayer player, string zoneID) => OnPlayerEnterPVP(player, zoneID);
		public static Dictionary<ulong, RRData> _rrAllRaiders = new Dictionary<ulong, RRData>();
		object OnEntityTakeDamage(SubmarineDuo submarineDuo, HitInfo info) => HandleDamageToVehicle(submarineDuo, info);
		
		void OnEntityKill(LootableCorpse corpse)
        {
			if (corpse.playerSteamID.IsSteamId() && OnEntityExitPVP(corpse))
			{
				if (!ConVar.Global.disableBagDropping && corpse.containers != null)
                {
					var container = ItemContainer.Drop("assets/prefabs/misc/item drop/item_drop_backpack.prefab", corpse.transform.position, Quaternion.identity, corpse.containers);
					if (container != null)
                    {
						container.playerName = corpse.playerName;
						container.playerSteamID = corpse.playerSteamID;
						OnEntityEnterPVP(container);
					}
				}
				corpse.blockBagDrop = true;
			}
			if (corpse.skinID == _bradleySkinId)
                _eventScientistsList.Remove(corpse.net.ID);
        }
		
		object OnQuarryToggle(MiningQuarry mining, BasePlayer player)
        {
			if (player != null && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, mining.net.ID.Value))
			{
                object result = null;
                if (!mining.isStatic && mining.OwnerID.IsSteamId())
					result = CanInteractSharedEntity(player, mining);
				else if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
                    result = monumentData.CanLoot(player);
				
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return result;
            }
            return null;
        }

		void OnHarborEventStart(Vector3 pos, float radius)
		{
			_harborEventMonument = GetMonumentByPos(pos);
			if (!string.IsNullOrWhiteSpace(_harborEventMonument) && _monumentsList.TryGetValue(_harborEventMonument, out var monumentData))
			{
				if (!_monumentsConfig.HarborEvent_HarborsToPvP)
					monumentData.Destroy(_harborEventMonument);
				else if (!monumentData.IsPvP)
					monumentData.SetAsPvP();
				else
					_harborEventMonument = string.Empty;
			}
			else
				_harborEventMonument = string.Empty;
		}
		void OnUserGroupRemoved(string userIDString, string groupName) => HandlePermissionsUpdate(userIDString);
		
		private void ShowEventOffer(BasePlayer player, EventData eventData, double price)
		{
			DestroyUI(player, EventOfferUI);
			CuiHelper.AddUi(player, ReplacePlaceholders(_vanillaEventsUiOffer, null, (string)ImageLibrary?.Call("GetImage", EventOfferUI),
				string.Format(lang.GetMessage("MsgEventOfferTitle", this, player.UserIDString), new string[] { lang.GetMessage(eventData.TypeKey, this, player.UserIDString) }),
				string.Format(lang.GetMessage("MsgEventOfferDescription", this, player.UserIDString), new string[] { string.Format(_config.PriceFormat, price) }),
				$"{_commandUI} event pay {eventData.ID}"));
			_playerUI[player.userID].Add(EventOfferUI);
		}
		void OnEntityKill(BatteringRam batteringRam) => HandleVehicleKill(batteringRam);
		private void OnFriendUpdated(string userID, string friendID)
		{
			var player = BasePlayer.Find(userID);
			if (player == null) return;
			var friend = BasePlayer.Find(friendID);
			if (friend == null) return;
			
			if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
				monumentData.OnFriendUpdated(player, friend);
			if (TryGetRaidBaseByUser(player.userID, out var rbData))
				rbData.OnFriendUpdated(player, friend);
		}
		private object HandleNpcTarget(global::HumanNPC npc, BasePlayer ownerPlayer)
        {
			if (npc.skinID == 0uL)
            {
                if (_monumentsList.TryGetValue(GetNpcMonument(npc), out var monumentData))
                    return monumentData.CanLoot(ownerPlayer) != null ? true : null;
                if (_monumentsList.TryGetValue(GetPlayerMonument(ownerPlayer.userID), out monumentData))
                    return monumentData.CanLoot(ownerPlayer) != null ? true : null;
            }
            else if (npc.skinID == _bradleySkinId)
            {
                if (_eventScientistsList.TryGetValue(npc.net.ID, out var eventData))
                    return eventData.CanBeTargeted(ownerPlayer) != null ? true : null;
            }
			return null;
		}
		private static bool IsEntityInPvP(ulong a) => _pvpEntities.Contains(a);

		void OnLootEntity(BasePlayer player, ItemBasedFlowRestrictor restrictor)
		{
			if (!_unrestrictedLooters.Contains(player.userID) && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData) && monumentData.CanLoot(player) != null)
			{
				ulong lastLooter = 0uL;
				ILootableEntity lootableEntity = restrictor as ILootableEntity;
				if (lootableEntity != null)
					lastLooter = lootableEntity.LastLootedBy;
				NextTick(() =>
				{
					using (var setFlags = restrictor.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
					{
					    setFlags.Set(BaseEntity.Flags.Open, false);
					}
					player.inventory.loot.RemoveContainer(restrictor.inventory);
					player.inventory.loot.SendImmediate();
					if (lootableEntity != null)
						lootableEntity.LastLootedBy = lastLooter;
					restrictor.SendNetworkUpdate();
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				});
			}
		}
		
		
        object OnVehicleLockRequest(ModularCarGarage garage, BasePlayer player, string password) => false;

		private float GetEventPriceMultiplier(string userID)
		{
			PvEPermission perm;
            float result = _permissionsConfig.PermissionsList[0].Event_Multiplier;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
                perm = _permissionsConfig.PermissionsList[i];
                if (perm.Event_Multiplier > result && permission.UserHasPermission(userID, perm.Name))
                    result = perm.Event_Multiplier;
            }
            return result;
		}
		
		private static readonly string[] _cmdKeysAdminTC = { "add", "remove", "clear", "info" };
		private static bool IsPlayerInPvP(ulong a) => _pvpPlayers.ContainsKey(a);
		
		private void ConfigLaptopAttackToggled()
        {
			if (_config.PreventLaptopAttack)
				Subscribe(nameof(OnCrateLaptopAttack));
			else
				Unsubscribe(nameof(OnCrateLaptopAttack));
		}
		
		
        object CanBeTargeted(BasePlayer target, HelicopterTurret turret)
        {
			if (target.userID.IsSteamId() && turret._heliAI?.helicopterBase is PatrolHelicopter patrolHeli && patrolHeli.IsValid())
            {
				if (patrolHeli.skinID == _rrPluginID)
                {
					if (_config.RandomRaids_Enabled && _rrallPatrols.TryGetValue(patrolHeli.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(target.userID))
                        return false;
				}
                else if (_eventsList.TryGetValue(patrolHeli.net.ID.Value, out var patrolData))
                {
					if (patrolData.CanBeTargeted(target) != null)
                        return false;
				}
			}
			return null;
        }
		object CanLootEntity(BasePlayer player, RepairBench repairBench) => CanLootStorage(player, repairBench);
		
		private static readonly string[] _cmdKeysMainShare = { "status", "add", "remove", "toggle", "delete", "clear" };
		private void Command_AdminConfig(IPlayer player, string[] args)
        {
			int index = Array.FindIndex(_cmdKeysAdminConfig, key => key.Equals(args[0], StringComparison.OrdinalIgnoreCase));
            if (index < 0)
            {
                player.Reply(lang.GetMessage("CmdAdminConfig", this, player.Id));
                return;
            }
			
			string propName = string.Empty;
			object propValue = null;
			int newValI;
			float newValF;
			bool caseFound = true, newVal;
			switch (index)
            {
				case 0:
					
					_config.Force_PvE = bool.TryParse(args[1], out newVal) ? newVal : !_config.Force_PvE;
					ConfigForcePvEToggled();
					propName = nameof(_config.Force_PvE);
					propValue = _config.Force_PvE;
					break;
				case 1:
                    
                    _config.Force_Tutorial = bool.TryParse(args[1], out newVal) ? newVal : !_config.Force_Tutorial;
					if (_config.Force_Tutorial)
						ConVar.Server.tutorialEnabled = true;
					propName = nameof(_config.Force_Tutorial);
                    propValue = _config.Force_Tutorial;
                    break;
				case 2:
                    
					_config.GameTips_Enabled = bool.TryParse(args[1], out newVal) ? newVal : !_config.GameTips_Enabled;
					propName = nameof(_config.GameTips_Enabled);
					propValue = _config.GameTips_Enabled;
					break;
				case 3:
                    
                    _config.RecheckPermissionLimits = bool.TryParse(args[1], out newVal) ? newVal : !_config.RecheckPermissionLimits;
                    ConfigPermissionLimitsToggled();
                    propName = nameof(_config.RecheckPermissionLimits);
                    propValue = _config.RecheckPermissionLimits;
                    break;
				case 4:
                    
                    _config.PreventSafeZoneLogoutDeath = bool.TryParse(args[1], out newVal) ? newVal : !_config.PreventSafeZoneLogoutDeath;
                    ConfigSafeZoneLogoutDeathToggled();
                    propName = nameof(_config.PreventSafeZoneLogoutDeath);
                    propValue = _config.PreventSafeZoneLogoutDeath;
                    break;
				case 5:
                    
                    _config.PreventPickUpCollectible = bool.TryParse(args[1], out newVal) ? newVal : !_config.PreventPickUpCollectible;
					propName = nameof(_config.PreventPickUpCollectible);
					propValue = _config.PreventPickUpCollectible;
					break;
                case 6:
                    
					_config.PreventPatrolToDeployables = bool.TryParse(args[1], out newVal) ? newVal : !_config.PreventPatrolToDeployables;
					propName = nameof(_config.PreventPatrolToDeployables);
					propValue = _config.PreventPatrolToDeployables;
					break;
                case 7:
                    
					_config.PreventHandcuffing = bool.TryParse(args[1], out newVal) ? newVal : !_config.PreventHandcuffing;
                    ConfigHandcuffToggled();
					propName = nameof(_config.PreventHandcuffing);
					propValue = _config.PreventHandcuffing;
					break;
                case 8:
                    
					_config.AssignPortals = bool.TryParse(args[1], out newVal) ? newVal : !_config.AssignPortals;
                    ConfigPortalsToggled();
					propName = nameof(_config.AssignPortals);
					propValue = _config.AssignPortals;
					break;
                case 9:
                    
					_config.PreventBackpackDrop = bool.TryParse(args[1], out newVal) ? newVal : !_config.PreventBackpackDrop;
					ConfigBackpackDropToggled();
					propName = nameof(_config.PreventBackpackDrop);
					propValue = _config.PreventBackpackDrop;
					break;
                case 10:
                    
					_config.PreventLaptopAttack = bool.TryParse(args[1], out newVal) ? newVal : !_config.PreventLaptopAttack;
					ConfigLaptopAttackToggled();
					propName = nameof(_config.PreventLaptopAttack);
					propValue = _config.PreventLaptopAttack;
					break;
                case 11:
                    
					_config.RecyclerNoPenalties = bool.TryParse(args[1], out newVal) ? newVal : !_config.RecyclerNoPenalties;
					foreach (var entity in BaseNetworkable.serverEntities)
					{
						if (entity is not Recycler recycler || !recycler.IsValid()) continue;
						if (_config.RecyclerNoPenalties)
							using (var setFlags = recycler.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
							{
							    setFlags.Set(BaseEntity.Flags.Reserved9, false);
							}
						else
							recycler.UpdateInSafeZone();
                    }
					propName = nameof(_config.RecyclerNoPenalties);
					propValue = _config.RecyclerNoPenalties;
					break;
                case 12:
                    
					_config.NoPickupRestrictions = bool.TryParse(args[1], out newVal) ? newVal : !_config.NoPickupRestrictions;
					ConfigItemPickupToggled();
					propName = nameof(_config.NoPickupRestrictions);
					propValue = _config.NoPickupRestrictions;
					break;
				case 13:
                    
                    _config.ProtectSleepers = bool.TryParse(args[1], out newVal) ? newVal : !_config.ProtectSleepers;
                    ConfigItemPickupToggled();
                    propName = nameof(_config.ProtectSleepers);
                    propValue = _config.ProtectSleepers;
                    break;
				case 14:
					
					if (!int.TryParse(args[2], out newValI) || newValI < 0 || newValI > 3)
						break;
					if (args[1].Equals("add", StringComparison.OrdinalIgnoreCase))
					{
						if (newValI == 0)
							_config.BuildingPrivilegeGathering.Clear();
						else
							_config.BuildingPrivilegeGathering.Remove(0);
						_config.BuildingPrivilegeGathering.Add(newValI);
					}
					else if (args[1].Equals("remove", StringComparison.OrdinalIgnoreCase))
					{
						_config.BuildingPrivilegeGathering.Remove(newValI);
						if (!_config.BuildingPrivilegeGathering.Any())
						{
							if (newValI == 0)
								_config.BuildingPrivilegeGathering.UnionWith(new[] { 1, 2, 3 });
							else
								_config.BuildingPrivilegeGathering.Add(0);
						}
					}
					else
						break;
					VerifyBuildingPrivilegeGathering();
					propName = nameof(_config.BuildingPrivilegeGathering);
                    propValue = string.Join(", ", _config.BuildingPrivilegeGathering);
					break;
				case 15:
                    
                    if (!string.IsNullOrWhiteSpace(args[1]))
					{
						_config.PriceFormat = args[1];
						propName = nameof(_config.PriceFormat);
						propValue = _config.PriceFormat;
					}
					break;
				case 16:
                    
                    if (float.TryParse(args[1], out newValF))
                    {
						_config.VehiclesMarkerTime = Mathf.Clamp(newValF, 0f, 60f);
						propName = nameof(_config.VehiclesMarkerTime);
						propValue = _config.VehiclesMarkerTime;
					}
                    break;
				case 17:
                    
                    if (float.TryParse(args[1], out newValF))
                    {
						_config.AntiSleeper = Mathf.Max(newValF, 0f);
						foreach (var tPlayer in BasePlayer.allPlayerList)
						{
							if (tPlayer.IsConnected || !tPlayer.IsInvoking(tPlayer.ScheduledDeath) || tPlayer.BinoMumkin() == null) continue;
							tPlayer.CancelInvoke(tPlayer.ScheduledDeath);
							if (_config.AntiSleeper > 0f)
								tPlayer.Invoke(tPlayer.ScheduledDeath, _config.AntiSleeper);
                        }
						propName = nameof(_config.AntiSleeper);
						propValue = _config.AntiSleeper;
					}
                    break;
				case 18:
                    
                    _config.RandomRaids_Enabled = bool.TryParse(args[1], out newVal) ? newVal : !_config.RandomRaids_Enabled;
					ToggleRandomRaids(RandomRaids != null && RandomRaids.IsLoaded);
					propName = nameof(_config.RandomRaids_Enabled);
					propValue = _config.RandomRaids_Enabled;
					break;
				case 19:
                    
                    _config.PvPTeamFF = bool.TryParse(args[1], out newVal) ? newVal : !_config.PvPTeamFF;
					propName = nameof(_config.PvPTeamFF);
					propValue = _config.PvPTeamFF;
                    break;
                case 20:
                    
                    _config.PvPMapMarkers = bool.TryParse(args[1], out newVal) ? newVal : !_config.PvPMapMarkers;
					if (_config.PvPMapMarkers)
                    {
						foreach (var zoneID in _dynamicPvPs)
						{
							var pos = (Vector3)(ZoneManager?.Call("GetZoneLocation", zoneID) ?? Vector3.zero);
							if (pos != Vector3.zero)
								CreatePVPMapMarker(zoneID, pos, (float)(ZoneManager?.Call("GetZoneRadius", zoneID) ?? 0.25f), _config.PvPMapMarkersName);
						}
					}
					else
					{
						foreach (var pvpMarkers in _pvpMarkers.Values)
							pvpMarkers.Destroy();
						_pvpMarkers.Clear();
					}
					propName = nameof(_config.PvPMapMarkers);
					propValue = _config.PvPMapMarkers;
                    break;
                case 21:
                    
                    if (!string.IsNullOrWhiteSpace(args[1]))
                    {
                        _config.PvPMapMarkersName = args[1];
						if (_config.PvPMapMarkers)
						{
							foreach (var pvpMarkers in _pvpMarkers.Values)
								pvpMarkers.SetMarkerName(_config.PvPMapMarkersName);
						}
						propName = nameof(_config.PvPMapMarkersName);
						propValue = _config.PvPMapMarkersName;
                    }
                    break;
				default:
                    caseFound = false;
                    break;
            }
			
			if (propValue != null)
			{
				SaveConfig();
				SendMessageText(player, string.Format(lang.GetMessage("CmdAdminConfigEditSucceed", this, player.Id), propValue, GetConfigPropertyName(propName)), false);
			}
			else
            {
				player.Reply(lang.GetMessage("CmdAdminConfig", this, player.Id));
                if (caseFound)
                    SendMessageText(player, lang.GetMessage("CmdMainEditWrongValue", this, player.Id));
			}
		}
		
		object OnGrowableGather(GrowableEntity plant, BasePlayer player) => HandlePlantInteracting(player, plant);
		
		public class MarkersPvP
        {
			private VendingMachineMapMarker MainMarker;
			private MapMarkerGenericRadius CircleMarker;
			
			public MarkersPvP(Vector3 pos, string title, float radius = 0f, BaseEntity parentEntity = null)
            {
				if (radius <= 0f)
					radius = World.Size <= 3600 ? 0.5f : 0.25f;
				else
				{
					radius = radius / 135;
					if (radius > 1.5f)
						radius = 1.5f;
                }
				
				MainMarker = GameManager.server.CreateEntity(StringPool.Get(3459945130), pos) as VendingMachineMapMarker;
                if (MainMarker != null)
                {
					MainMarker.enabled = false;
                    MainMarker.Spawn();
                    if (parentEntity != null)
                    {
                        MainMarker.SetParent(parentEntity);
                        MainMarker.transform.localPosition = Vector3.zero;
                    }
                }
				SetMarkerName(title);
				
				CircleMarker = GameManager.server.CreateEntity(StringPool.Get(2849728229), pos) as MapMarkerGenericRadius;
                if (CircleMarker != null)
                {
                    CircleMarker.alpha = 0.5f;
                    CircleMarker.color1 = Color.red;
                    CircleMarker.color2 = Color.black;
					CircleMarker.radius = radius;
                    CircleMarker.Spawn();
                    if (parentEntity != null)
                    {
                        CircleMarker.SetParent(parentEntity);
                        CircleMarker.transform.localPosition = Vector3.zero;
                    }
                    CircleMarker.SendUpdate();
                }
            }
			
			public void SetMarkerName(string name)
            {
				if (MainMarker != null)
                {
					MainMarker.markerShopName = name;
					MainMarker.SendNetworkUpdate();
				}
			}
			
			public void Destroy()
            {
				if (MainMarker != null)
					MainMarker.Kill();
				if (CircleMarker != null)
					CircleMarker.Kill();
			}
        }

		void OnPluginUnloaded(Plugin plugin)
		{
			if (plugin.Name == "ImageLibrary")
				ToggleImageLib(false);
			else if (plugin.Name == "Economics")
				_economicsIsLoaded = false;
			else if (plugin.Name == "AdvancedStatus")
				_statusIsLoaded = false;
			else if (plugin.Name == "MonumentsWatcher")
			{
				_watcherIsLoaded = false;
				Unsubscribe(nameof(OnEntityEnteredMonument));
				Unsubscribe(nameof(OnEntityExitedMonument));
				Unsubscribe(nameof(OnPlayerEnteredMonument));
				Unsubscribe(nameof(OnPlayerExitedMonument));
				Unsubscribe(nameof(OnCargoWatcherCreated));
				Unsubscribe(nameof(OnCargoWatcherDeleted));
				Unsubscribe(nameof(OnCargoShipHarborArrived));
				Unsubscribe(nameof(OnCargoShipHarborLeave));
				Unsubscribe(nameof(OnHarborEventStart));
				Unsubscribe(nameof(OnHarborEventEnd));
				Unsubscribe(nameof(OnExcavatorResourceSet));
				Unsubscribe(nameof(OnExcavatorSuppliesRequest));
				Unsubscribe(nameof(OnExcavatorSuppliesRequested));
				Unsubscribe(nameof(CanHackCrate));
				foreach (var monumentData in _monumentsList.Values)
					monumentData.Destroy();
				_monumentsList.Clear();
				if (_statusIsLoaded)
					DestroyAllBars();
				_harborEventMonument = string.Empty;
			}
			else if (plugin.Name == "HarborEvent")
			{
				NextTick(() =>
				{
					if (!string.IsNullOrWhiteSpace(_harborEventMonument))
						OnHarborEventEnd();
				});
			}
			else if (plugin.Name == "RaidableBases")
				ToggleRaidableBases(false);
			else if (plugin.Name == "RandomRaids")
				ToggleRandomRaids(false);
			else if (plugin.Name == "Friends")
				_friendsIsLoaded = false;
			else if (plugin.Name == "DynamicPVP")
				ToggleDynamicPVP(false);
		}
		private void SaveMonumentsConfig() => Interface.Oxide.DataFileSystem.WriteObject(_monumentsPath, _monumentsConfig);
		
		private void SendMessage(BasePlayer player, string replyKey, string[] replyArgs = null, bool isWarning = true) => SendMessage(player.IPlayer, replyKey, replyArgs, isWarning);
		private const string EventOfferUI = "RealPVE_EventOffer";
		
		void OnEntitySpawned(BuildingPrivlidge privlidge) => privlidge.lastNoiseTime = DateTimeOffset.UtcNow.Day;
		private string GetEntityMonument(BaseEntity entity) => (string)(MonumentsWatcher?.Call(MonumentGetEntityMonument, entity.net.ID) ?? string.Empty);
		private void Command_AdminMonument(IPlayer player, string[] args)
        {
			if (args[0].Equals("list", StringComparison.OrdinalIgnoreCase))
            {
                
                
                player.Reply(string.Format(lang.GetMessage("CmdAdminMonumentList", this, player.Id), string.Join(", ", _monumentsList.Values.Where(m => !m.IsMoveable).Select(m => m.MonumentID).ToArray())));
                return;
            }
			int index = !string.IsNullOrWhiteSpace(args[0]) && !args[0].Equals("help", StringComparison.OrdinalIgnoreCase) ? Array.FindIndex(_cmdKeysAdminMonument, key => key.Equals(args[1], StringComparison.OrdinalIgnoreCase)) : -1;
            if (index < 0)
            {
				player.Reply(lang.GetMessage("CmdAdminMonument", this, player.Id));
				return;
			}
			
			
            
            ulong.TryParse(player.Id, out var targetID);
			if (!_monumentsList.TryGetValue(targetID.IsSteamId() && args[0].Equals("this", StringComparison.OrdinalIgnoreCase) ? GetPlayerMonument(targetID) : args[0], out var monumentData))
            {
                SendMessageText(player, string.Format(lang.GetMessage("CmdAdminMonumentNotFound", this, player.Id), args[0]));
                return;
            }

            string valRes = string.Empty;
            bool caseFound = true, isEdited = false;
			switch (index)
            {
                case 0:
					
					if (!bool.TryParse(args[2], out var suffix))
                        suffix = !monumentData.Settings.ShowSuffix;
                    monumentData.Settings.ShowSuffix = suffix;
                    monumentData.MonumentNameEng = monumentData.GetMonumentName();
                    monumentData.UpdateMapMarkers();
                    valRes = monumentData.Settings.ShowSuffix.ToString();
                    isEdited = true;
                    break;
                case 1:
					
					if (!bool.TryParse(args[2], out var broadcast))
                        broadcast = !monumentData.Settings.Broadcast;
                    monumentData.Settings.Broadcast = broadcast;
                    valRes = monumentData.Settings.Broadcast.ToString();
                    isEdited = true;
                    break;
                case 2:
					
					if (int.TryParse(args[2], out var lTime))
                    {
                        monumentData.Settings.LootingTime = lTime;
                        monumentData.TrySetLootTimer();
                        valRes = monumentData.Settings.LootingTime.ToString();
                        isEdited = true;
                    }
                    break;
                case 3:
					
					if (double.TryParse(args[2], out var price))
                    {
                        monumentData.Settings.Price = price;
                        valRes = monumentData.Settings.Price.ToString();
                        isEdited = true;
                    }
                    break;
                case 4:
					
					if (float.TryParse(args[2], out var offer))
                    {
                        monumentData.Settings.OfferTime = offer;
                        valRes = monumentData.Settings.OfferTime.ToString();
                        isEdited = true;
                    }
                    break;
                case 5:
					
					if (int.TryParse(args[2], out var map_mode))
                    {
                        monumentData.Settings.MapMarkerMode = map_mode;
                        monumentData.VerifyMapMarkers();
                        valRes = monumentData.Settings.MapMarkerMode.ToString();
                        isEdited = true;
                    }
                    break;
                case 6:
					
					if (!bool.TryParse(args[2], out var map_circle))
                        map_circle = !monumentData.Settings.MapMarkerCircle;
                    monumentData.Settings.MapMarkerCircle = map_circle;
                    monumentData.VerifyMapMarkers();
                    valRes = monumentData.Settings.MapMarkerCircle.ToString();
                    isEdited = true;
                    break;
                case 7:
					
					if (monumentData.IsOwned && monumentData.OwnerID != targetID)
                    {
                        SendMessageText(player, string.Format(lang.GetMessage("CmdAdminMonumentOcupied", this, player.Id), monumentData.GetMonumentName(player.Id)));
                        return;
                    }

                    if (!bool.TryParse(args[2], out var pvp))
                        pvp = !monumentData.Settings.IsPvP;
                    monumentData.Settings.IsPvP = pvp;
                    pvp = monumentData.Settings.IsPvP;
                    if (pvp)
                        monumentData.SetAsPvP();
                    else
                        monumentData.RemovePvP();
                    valRes = pvp.ToString();
                    isEdited = true;
                    break;
                case 8:
					
					if (float.TryParse(args[2], out var pvp_delay))
                    {
                        monumentData.Settings.PvPDelay = pvp_delay;
                        valRes = monumentData.Settings.PvPDelay.ToString();
                        isEdited = true;
                    }
                    break;
                case 9:
					
					if (!bool.TryParse(args[2], out var bar_progress))
                        bar_progress = !monumentData.Settings.UseProgressBar;
                    monumentData.Settings.UseProgressBar = bar_progress;
                    monumentData.UpdateLootersBar();
                    valRes = monumentData.Settings.UseProgressBar.ToString();
                    isEdited = true;
                    break;
                default:
                    caseFound = false;
                    break;
            }
			
			if (isEdited)
            {
                SaveMonumentsConfig();
                SendMessageText(player, string.Format(lang.GetMessage("CmdAdminMonumentSucceed", this, player.Id), monumentData.GetMonumentName(player.Id), args[1], valRes), false);
            }
            else
            {
                player.Reply(lang.GetMessage("CmdAdminMonument", this, player.Id));
                if (caseFound)
                    SendMessageText(player, lang.GetMessage("CmdAdminMonumentWrongValue", this, player.Id));
            }
		}
		
		void OnTeamCreated(BasePlayer player, RelationshipManager.PlayerTeam team) => _teamsList[team.teamID] = new TeamData(team.teamID, _config.PvPTeamFF);
		
                private const string WhiteColor = "1 1 1 1";
		
		private object RRNpc(ulong netID, BuildingBlock block)
		{
			if (block != null && _rrAllRaiders.TryGetValue(netID, out var rrData))
			{
				var buildID = block.GetBuilding()?.ID ?? 0;
				if (rrData.BuildingIDs.Contains(buildID) || rrData.PlayersList.Contains(block.OwnerID))
					return null;
			}
			return false;
		}
		
		private void DestroyBar(ulong userID, string barID) => AdvancedStatus?.Call(StatusDeleteBar, userID, barID, Name);
		
		private void RemoveDemolishable(DecayEntity decayEntity)
        {
            if (decayEntity.IsDemolishSupported && decayEntity.OwnerID.IsSteamId())
            {
				using (var setFlags = decayEntity.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
				{
				    setFlags.Set(BaseEntity.Flags.Reserved2, false);
				}
				if (decayEntity is BuildingBlock block)
                    using (var setFlags = block.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
                    {
                        setFlags.Set(BaseEntity.Flags.Reserved1, false);
                    }
			}
		}
		private static Dictionary<ulong, EventData> _eventsList;
		
		void OnRaidableBasePurchased(string userIDStr, Vector3 pos)
        {
			if (ulong.TryParse(userIDStr, out var userID) && _rbList.TryGetValue(pos.ToString(), out var rbData))
				rbData.SetNewOwner(userID);
		}
		private static bool UrishMumkin(BasePlayer a, ulong b) => b == a.userID || (a.Team != null && a.Team.members.Contains(b) && _teamsList.TryGetValue(a.currentTeam, out var c) && c.FriendlyFire);
		
		
        object OnCupboardAuthorize(VehiclePrivilege privilege, BasePlayer player)
        {
			var tugBoat = privilege.GetParentEntity() as Tugboat;
			if (tugBoat.IsValid())
			{
				object result = null;
				if (_vehiclesList.TryGetValue(tugBoat.net.ID.Value, out var vehicleData) && vehicleData.OwnerID != 0uL)
					result = vehicleData.CanLoot(player);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgVehicleTugboatAuthorization", this, player.UserIDString));
				return result;
			}
			return null;
		}

		private void ApplyDemolishable(DecayEntity decayEntity)
		{
			if (decayEntity.IsDemolishSupported && decayEntity.OwnerID.IsSteamId())
			{
				decayEntity.CancelInvoke(decayEntity.StopBeingDemolishable);
				using (var setFlags = decayEntity.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
				{
				    setFlags.Set(BaseEntity.Flags.Reserved2, true);
				}
				
				if (decayEntity is BuildingBlock block)
				{
					block.CancelInvoke(block.StopBeingRotatable);
					using (var setFlags = block.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
					{
					    setFlags.Set(BaseEntity.Flags.Reserved1, true);
					}
				}
			}
		}
		
		private List<string> _permNames;
		void OnEntityKill(Tugboat tugBoat) => HandleVehicleKill(tugBoat);
		private readonly string[] HttpScheme = new string[] { "http://", "https://" };
        private readonly VersionNumber _permissionsVersion = new VersionNumber(0, 1, 1);
				
		        private void Command_UI(IPlayer player, string command, string[] args)
        {
            if (args == null || args.Length < 3 || player.Object is not BasePlayer bPlayer || bPlayer == null) return;
            string replyKey = string.Empty, effectName = string.Empty;
			string[] replyArgs = new string[5];
			bool isWarning = false;
			object isPayed = null;
			if (args[0] == "vehicle")
            {
				if (ulong.TryParse(args[2], out var vehicleID) && _vehiclesList.TryGetValue(vehicleID, out var vehicleData) && BaseNetworkable.serverEntities.Find(new NetworkableId(vehicleID)) is BaseEntity entity)
                {
                    var car = entity as ModularCar;
					if (entity is BaseVehicle || entity is HotAirBalloon)
					{
						var privilege = entity.children.OfType<VehiclePrivilege>().FirstOrDefault();
						if (args[1] == "link")
						{
							if (car != null && !car.HasDriverMountPoints())
								effectName = "assets/prefabs/locks/keypad/effects/lock.code.denied.prefab";
							else if (car != null && (args.Length < 4 || !car.CarLock.IsValidLockCode(args[3])))
							{
								replyKey = "MsgVehicleDialogIncorrectPassword";
								isWarning = true;
								effectName = "assets/prefabs/locks/keypad/effects/lock.code.denied.prefab";
							}
							else
							{
								if (_economicsIsLoaded)
								{
									double regPrice = GetVehiclePrice(bPlayer.UserIDString, vehicleData.Type);
									if (regPrice > 0d) isPayed = MakeWithdraw(bPlayer.UserIDString, regPrice);
								}
								if (isPayed is bool && !(bool)isPayed)
								{
									replyKey = "MsgEconomicsNotEnough";
									isWarning = true;
									effectName = "assets/prefabs/locks/keypad/effects/lock.code.denied.prefab";
								}
								else if (vehicleData.AssignNewOwner(bPlayer) == null)
								{
									if (car != null)
										car.CarLock.TryAddALock(args[3], bPlayer.userID);
									else if (privilege != null)
									{
										privilege.authorizedPlayers.Clear();
										privilege.UpdateMaxAuthCapacity();
										privilege.SendNetworkUpdate();
										privilege.AddPlayer(bPlayer);
										privilege.SendNetworkUpdate();
									}
									effectName = "assets/prefabs/locks/keypad/effects/lock-code-deploy.prefab";
								}
							}
						}
						else if (args[1] == "unlink")
						{
							if (vehicleData.RemoveOwner(bPlayer))
							{
								if (car != null)
								{
									if (car.CarLock != null)
									{
										car.CarLock.RemoveLock();
										foreach (var wId in car.CarLock.WhitelistPlayers.ToArray())
											car.CarLock.TryRemovePlayer(wId);
									}
								}
								else if (privilege != null)
								{
									privilege.authorizedPlayers.Clear();
									privilege.UpdateMaxAuthCapacity();
									privilege.SendNetworkUpdate();
								}
								effectName = "assets/prefabs/locks/keypad/effects/lock.code.unlock.prefab";
							}
						}
						else if (args[1] == "rename")
						{
							if (!vehicleData.IsOwner(bPlayer.userID))
							{
								replyKey = "MsgVehicleNotOwner";
								isWarning = true;
							}
							else if (args.Length < 4 || args[3].Length > 16)
							{
								replyKey = "MsgVehicleWrongName";
								isWarning = true;
							}
							else
							{
								vehicleData.Name = args[3];
								replyKey = "MsgVehicleNewName";
								replyArgs[0] = vehicleData.Name;
							}
						}
					}
				}
            }
            else if (args[0] == "monument")
            {
                if (args[1] == "pay" && _monumentsList.TryGetValue(args[2], out var monumentData) && !monumentData.IsPvP)
                {
                    if (!monumentData.PlayersQueue.Contains(bPlayer))
                    {
                        replyKey = "MsgMonumentNotInQueue";
                        isWarning = true;
                    }
                    else
                    {
                        if (_economicsIsLoaded)
                        {
                            double price = monumentData.Settings.Price <= 0d ? 0d : monumentData.Settings.Price * GetMonumentPriceMultiplier(bPlayer.UserIDString);
                            if (price > 0d) isPayed = MakeWithdraw(bPlayer.UserIDString, price);
                        }
                        if (isPayed is bool && !(bool)isPayed)
                        {
                            monumentData.OfferOwnership();
                            replyKey = "MsgEconomicsNotEnough";
                            isWarning = true;
                        }
                        else
                            monumentData.SetNewOwner(bPlayer);
                    }
                }
            }
            else if (args[0] == "event")
            {
                if (args[1] == "pay" && ulong.TryParse(args[2], out var eventID) && _eventsList.TryGetValue(eventID, out var eventData))
                {
                    if (eventData.OwnerID.IsSteamId())
                    {
                        replyKey = "MsgEventOccupied";
						replyArgs[0] = eventData.OwnerName;
                        isWarning = true;
                    }
                    else
                    {
                        if (_economicsIsLoaded)
                        {
                            double price = eventData.Settings.Price <= 0d ? 0d : eventData.Settings.Price * GetEventPriceMultiplier(bPlayer.UserIDString);
                            if (price > 0d) isPayed = MakeWithdraw(bPlayer.UserIDString, price);
                        }
                        if (isPayed is bool && !(bool)isPayed)
                        {
                            replyKey = "MsgEconomicsNotEnough";
                            isWarning = true;
                        }
                        else
                            eventData.SetNewOwner(bPlayer);
                    }
                }
            }
            else if (args[0] == "rb")
            {
                if (args[1] == "pay" && args[2].TryParseVector3(out var pos) && _rbList.TryGetValue(pos.ToString(), out var rbData) && !rbData.IsPvP)
                {
                    if (rbData.OwnerID.IsSteamId())
                    {
                        replyKey = "MsgRaidableBasesOccupied";
						var rbOwner = BasePlayer.FindByID(rbData.OwnerID);
                        replyArgs[0] = rbOwner != null ? rbOwner.displayName : rbData.OwnerIDString;
                        isWarning = true;
                    }
                    else if (rbData.IsPlayerInside(bPlayer.userID))
                    {
						int limit = GetRaidableBasesLimit(bPlayer.UserIDString), total = CountRaids(bPlayer.UserIDString);
                        if (limit >= 0 && total >= limit)
                        {
                            replyKey = "MsgRaidableBasesLimit";
                            replyArgs[0] = total.ToString();
                            replyArgs[1] = limit.ToString();
                            isWarning = true;
                        }
                        else
                        {
							double price = rbData.Settings.Price <= 0d ? 0d : rbData.Settings.Price * GetRaidableBasesPriceMultiplier(bPlayer.UserIDString);
							if (_economicsIsLoaded && price > 0d)
								isPayed = MakeWithdraw(bPlayer.UserIDString, price);
                            if (isPayed is bool && !(bool)isPayed)
                            {
                                replyKey = "MsgEconomicsNotEnough";
                                isWarning = true;
                            }
                            else
                            {
                                Instance.covalence.Server.Command($"{_rbsConfig.ConsoleCommand} setowner {bPlayer.UserIDString}");
								effectName = false ? "assets/prefabs/cached/effects/Zmp0ZQD=.prefab" : "";
								replyKey = "MsgRaidableBasesPurchaseStart";
								NextTick(() =>
                                {
                                    if (rbData.OwnerID != bPlayer.userID)
                                    {
                                        if (_economicsIsLoaded && price > 0d)
                                        {
											MakeDeposit(bPlayer.UserIDString, price);
											SendMessageText(player, lang.GetMessage("MsgRaidableBasesPurchaseFailed", this, player.Id));
										}
                                    }
                                    else
										SendMessageText(player, lang.GetMessage("MsgRaidableBasesPurchased", this, player.Id), false);
								});
                            }
                        }
                    }
                }
            }

            if (!string.IsNullOrWhiteSpace(replyKey))
                SendMessage(player, replyKey, replyArgs, isWarning);
            if (!string.IsNullOrWhiteSpace(effectName))
                bPlayer.RunEffect(effectName);
        }
		object CanLootEntity(BasePlayer player, GunTrap gunTrap) => CanLootStorage(player, gunTrap);
		
		void OnEntityDeath(BasePlayer player)
        {
			if (_pvpPlayers.TryGetValue(player.userID, out var playerPvP))
			{
				DestroyBar(player.userID, playerPvP.LastZone);
				playerPvP.ActiveZones.Clear();
			}
		}
		
		object OnTrapArm(BearTrap bearTrap, BasePlayer player)
        {
			if (bearTrap.IsValid() && bearTrap.OwnerID.IsSteamId() && player.IsValid())
			{
				if (!IsEntityInPvP(player.userID, bearTrap.net.ID.Value) && !UrishMumkin(player, bearTrap.OwnerID) && !IsEntitySharedToPlayer(player.userID, bearTrap.net.ID.Value))
					return true;
            }
			return null;
        }
		
		private class MonumentConfig
        {
			[JsonProperty(PropertyName = "Time in seconds(3-60) to return to the monument if the owner has left its boundaries")]
            public float TimeToComeBack = 15f;
			
			[JsonProperty(PropertyName = "Is it worth prohibiting players without access from picking up items in occupied monuments?")]
            public bool OnlyOwnerPickup = true;
			
			[JsonProperty(PropertyName = "Is it worth enabling forced auto-buy for monuments where the final price is greater than 0?")]
            public bool AutoBuy = false;
			
			[JsonProperty(PropertyName = "Harbor — Is it worth making the Harbor monument a PvP zone when the PvP Cargo Ship(Vanilla) is docked?")]
			public bool CargoShip_HarborToPvP = true;
			
			[JsonProperty(PropertyName = "Large Harbor — Is it worth making the Large Harbor monument a PvP zone when the PvP Cargo Ship(Vanilla) is docked?")]
			public bool CargoShip_LargeHarborToPvP = true;
			
			[JsonProperty(PropertyName = "HarborEvent - Is it worth making the Harbors a PvP zone during the event?")]
            public bool HarborEvent_HarborsToPvP = true;
			
			[JsonProperty(PropertyName = "Map marker settings for an unclaimed monuments")]
			public MapCircleStyle MapCircle_Free = null;
			
			[JsonProperty(PropertyName = "Map marker settings for an occupied monuments")]
			public MapCircleStyle MapCircle_Owned = null;
			
			[JsonProperty(PropertyName = "Map marker settings for a PvP monuments")]
			public MapCircleStyle MapCircle_PvP = null;
			
			[JsonProperty(PropertyName = "List of tracked categories of monuments")]
			public string[] TrackedCategories = null;

			[JsonProperty(PropertyName = "List of IGNORED monument names. Example: powerplant_1")]
			public string[] IgnoredNames = null;
			
			[JsonProperty(PropertyName = "Settings for each monument")]
            public Dictionary<string, MonumentSettings> MonumentsSettings = null;
			
			public Oxide.Core.VersionNumber Version;
		}
		object OnRackedWeaponMount(Item item, BasePlayer player, WeaponRack rack) => CanLootWeaponRack(player, rack);
		
		object CanLootPlayer(BasePlayer target, BasePlayer looter)
        {
			if (_unrestrictedLooters.Contains(looter.userID)) return true;
			if (target.userID.IsSteamId() && !IsPlayerInPvP(looter.userID, target.userID))
			{
				object result = looter.TasirMumkin(target.userID) == null || IsFriend(looter.UserIDString, target.UserIDString) ? null : false;
				if (result != null)
					SendMessageText(looter, lang.GetMessage("MsgCantInteractPlayer", this, looter.UserIDString));
				return result;
			}
			return null;
		}
        public static readonly HashSet<string> _patrolHeliPrefabs = new HashSet<string>(StringComparer.OrdinalIgnoreCase) { "oilfireballsmall", "napalm", "rocket_heli", "rocket_heli_napalm" };
		private static NewbieConfig _newbieConfig;
		
		private bool TryGetRaidBase(Vector3 pos, out RBData result)
        {
			result = null;
			if (!_rbsConfig.IsEnabled) return false;
			float distance = float.MaxValue;
			foreach (var rbData in _rbList.Values)
            {
				float newDistance = (pos - rbData.Position).sqrMagnitude;
				if (newDistance < distance && newDistance <= rbData.RadiusSquared)
				{
					distance = newDistance;
					result = rbData;
				}
            }
			return result != null;
		}
		
		private void InitPermissions()
        {
			_permNames = new List<string>();
			PvEPermission perm;
			int beds = 0, shelters = 0, turrets = 0;
			var vehicleTypes = GetVehicleTypes();
			for (int i = 0; i < _permissionsConfig.PermissionsList.Count; i++)
			{
				perm = _permissionsConfig.PermissionsList[i];
				if (perm.Name.StartsWith("realpve", StringComparison.OrdinalIgnoreCase))
					permission.RegisterPermission(perm.Name, this);
				if (perm.Beds > beds)
					beds = perm.Beds;
				if (perm.Shelters > shelters)
					shelters = perm.Shelters;
				if (perm.Turrets > turrets)
					turrets = perm.Turrets;
				if (perm.Allowed_Vehicles == null)
					perm.Allowed_Vehicles = new Dictionary<VehicleType, VehicleProperties>();
				for (int j = 0; j < vehicleTypes.Length; j++)
					perm.Allowed_Vehicles.TryAdd(vehicleTypes[j], new VehicleProperties());
				_permNames.Add(perm.Name);
			}
			if (beds > ConVar.Server.max_sleeping_bags)
				ConVar.Server.max_sleeping_bags = beds;
			if (shelters > LegacyShelter.max_shelters)
				LegacyShelter.max_shelters = shelters;
			if (turrets > ConVar.Sentry.maxinterference)
				ConVar.Sentry.maxinterference = turrets;
			_bedMissionId = "safezonespawnunlock".ManifestHash();
		}
		
		private string GetMonumentCategory(string monumentID) => (string)(MonumentsWatcher?.Call(MonumentGetMonumentCategory, monumentID) ?? string.Empty);
		private readonly VersionNumber _monumentsVersion = new VersionNumber(0, 1, 4);
		
		void OnPlayerEnteredRaidableBase(BasePlayer player, Vector3 pos)
        {
			if (!_rbList.TryGetValue(pos.ToString(), out var rbData))
            {
				SendMessageText(player, lang.GetMessage("MsgRaidableBasesDisabled", this, player.UserIDString));
				return;
            }
			rbData.OnPlayerEnter(player);
			if (!rbData.IsPvP && !rbData.OwnerID.IsSteamId())
            {
				double price = rbData.Settings.Price <= 0d ? 0d : rbData.Settings.Price * GetRaidableBasesPriceMultiplier(player.UserIDString);
				if (price <= 0d || _rbsConfig.AutoBuy || _autoBuyPlayers.Contains(player.userID))
					player.Command($"{_commandUI} rb pay {rbData.RaidID.Replace(" ", "")}");
				else
                {
					ShowRaidableBasesOffer(player, rbData);
					timer.Once(rbData.Settings.OfferTime, () => { DestroyUI(player, RBOfferUI); });
                }
				player.SendEffect();
			}
		}
		
		private void Command_AdminPickup(IPlayer player, string[] args)
        {
			if (string.IsNullOrWhiteSpace(args[0]) || args[0].Equals("help", StringComparison.OrdinalIgnoreCase))
				player.Reply(lang.GetMessage("CmdAdminPickup", this, player.Id));
			else if (args[0].Equals("clear", StringComparison.OrdinalIgnoreCase))
            {
				foreach (var playerData in _playersList.Values)
                    playerData.AllowPickup = false;
                int total = _pickupPlayers.Count;
				_pickupPlayers.Clear();
                SaveData(_dataPlayersPath, _playersList);
                SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPickupClear", this, player.Id), total), false);
            }
			else if (TryGetPlayer(player, args[0], out var tPlayer))
			{
				GetOrCreatePlayerData(tPlayer.Id, out var playerData);
				playerData.AllowPickup = !playerData.AllowPickup;
				if (playerData.AllowPickup)
				{
					_pickupPlayers.Add(playerData.UserID);
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPickupEnabled", this, player.Id), tPlayer.Name));
				}
				else
				{
					_pickupPlayers.Remove(playerData.UserID);
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPickupDisabled", this, player.Id), tPlayer.Name), false);
				}
			}
		}
		[Flags] public enum BuildingGatherRestrictions { None = 0, Trees = 1 << 0, Ores = 1 << 1, Flesh = 1 << 2 }
		object CanLootEntity(BasePlayer player, HitchTrough hitch) => CanLootStorage(player, hitch);
		private void SaveRBsConfig() => Interface.Oxide.DataFileSystem.WriteObject(_rbsPath, _rbsConfig);
		private Dictionary<string, MonumentData> _monumentsList = new Dictionary<string, MonumentData>();
		
		private bool TryGetPlayers(string nameOrId, out List<IPlayer> result, IPlayer initiator = null, int limit = -1, bool all = true)
        {
            result = new List<IPlayer>();
            if (string.IsNullOrWhiteSpace(nameOrId))
                return false;

            bool onlyDigits = nameOrId.All(char.IsDigit);
            foreach (var player in all ? covalence.Players.All : covalence.Players.Connected)
            {
                if (limit == 0) break;
                if (!player.IsServer &&
                    ((onlyDigits && player.Id.Contains(nameOrId, StringComparison.OrdinalIgnoreCase)) || player.Name.Contains(nameOrId, StringComparison.OrdinalIgnoreCase)))
                {
                    result.Add(player);
                    limit--;
                }
            }
            if (initiator != null)
                result.Remove(initiator);
            return result.Any();
        }

		private int GetRaidableBasesLimit(string userID)
		{
			PvEPermission perm;
            int result = _permissionsConfig.PermissionsList[0].RB_Limit;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				if (result < 0)
					break;
				perm = _permissionsConfig.PermissionsList[i];
                if ((perm.RB_Limit < 0 || perm.RB_Limit > result) && permission.UserHasPermission(userID, perm.Name))
                    result = perm.RB_Limit;
            }
            return result;
		}
		
		private void ConfigForcePvEToggled()
        {
			if (_config.Force_PvE && !ConVar.Server.pve)
            {
                ConVar.Server.pve = true;
                PrintWarning("The PvE settings were forcibly enabled!");
            }
        }
		object OnRidableAnimalTow(RidableHorse horse, BasePlayer player)
		{
			if (player.IsValid())
            {
				object result = HandleVehicleInteraction(player, horse, "MsgVehicleCantTow");
				if (result != null)
					return result;
				var towEntity = horse.towableEntity.TowEntity;
				if (towEntity.IsValid() && towEntity.OwnerID.IsSteamId())
					result = player.TasirMumkin(towEntity.OwnerID);
				
				if (result != null)
                    SendMessageText(player, lang.GetMessage("MsgVehicleCantTow", this, player.UserIDString));
                return result;
			}
			return null;
		}

		void OnHarborEventEnd()
		{
			if (_watcherIsLoaded && !string.IsNullOrWhiteSpace(_harborEventMonument) && !_monumentsConfig.IgnoredNames.Contains(_harborEventMonument) &&
				_monumentsConfig.TrackedCategories.Contains(GetMonumentCategory(_harborEventMonument)))
			{
				if (!_monumentsList.TryGetValue(_harborEventMonument, out var monumentData))
                    _monumentsList[_harborEventMonument] = new MonumentData(_harborEventMonument, _monumentsConfig.MonumentsSettings[_harborEventMonument]);
                else
                    monumentData.RemovePvP();
			}
			_harborEventMonument = string.Empty;
		}
		object CanLootEntity(BasePlayer player, Mailbox box) => null;
		
		private object AdminOpenLoot(BasePlayer player, StorageContainer container)
        {
			if (player.inventory.loot.StartLootingEntity(container, false))
            {
                using (var setFlags = container.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
                {
                    setFlags.Set(BaseEntity.Flags.Open, true);
                }
                container.AddContainers(player.inventory.loot);
                player.inventory.loot.SendImmediate();
                player.ClientRPC(RpcTarget.Player("RPC_OpenLootPanel", player), container.panelName);
                container.SendNetworkUpdate();
                return true;
            }
			return null;
		}
		object OnRackedWeaponSwap(Item item, WeaponRackSlot weaponAtIndex, BasePlayer player, WeaponRack rack) => CanLootWeaponRack(player, rack);
		void OnEntitySpawned(SubmarineDuo submarineDuo) => HandleVehicleSpawn(submarineDuo);
		
		void OnEntitySpawned(BradleyAPC bradley)
		{
			if (_vanillaEventsConfig.BradleyAPC.IsEnabled)
				NextTick(() => { new EventData(bradley); });
		}
		object CanLootEntity(BasePlayer player, Locker locker)
		{
			if (_unrestrictedLooters.Contains(player.userID)) return AdminOpenLoot(player, locker);
			if (IsEntityInPvP(player.userID, locker.net.ID.Value)) return null;
			if (locker.GetParentEntity() is BaseVehicleModule module)
				return CanLootCar(player, module.VehicleParent());
			return CanLootStorage(player, locker, true);
		}
		
		private void LoadVanillaEventsConfig()
        {
			_vanillaEventsPath = $"{Name}{Path.DirectorySeparatorChar}VanillaEventsConfig";
			string uiOfferPath = $"{Name}{Path.DirectorySeparatorChar}UI{Path.DirectorySeparatorChar}VanillaEventsOffer";
			List<CuiElement> uiList = null;
			if (Interface.Oxide.DataFileSystem.ExistsDatafile(_vanillaEventsPath))
            {
				try
				{
					_vanillaEventsConfig = Interface.Oxide.DataFileSystem.ReadObject<VanillaEventsConfig>(_vanillaEventsPath);
					uiList = Interface.Oxide.DataFileSystem.ReadObject<List<CuiElement>>(uiOfferPath);
				}
				catch (Exception ex) { UnityEngine.Debug.LogException(ex); }
            }
			
			if (_vanillaEventsConfig == null || _vanillaEventsConfig.Version < _vanillaEventsVersion)
            {
                if (_vanillaEventsConfig != null)
                {
					string pathOld = $"{Name}{Path.DirectorySeparatorChar}_old_VanillaEventsConfig({_vanillaEventsConfig.Version})";
					PrintWarning($"Your settings version for vanilla events is outdated. The config file has been updated, and your old settings have been saved in {Interface.Oxide.DataDirectory}{Path.DirectorySeparatorChar}{pathOld}.json");
					Interface.Oxide.DataFileSystem.WriteObject(pathOld, _vanillaEventsConfig);
				}
				_vanillaEventsConfig = new VanillaEventsConfig() { Version = _vanillaEventsVersion };
            }
			
			if (_vanillaEventsConfig.PatrolHelicopter == null)
				_vanillaEventsConfig.PatrolHelicopter = new EventSettings();
			else
				_vanillaEventsConfig.PatrolHelicopter.OfferTime = Math.Clamp(_vanillaEventsConfig.PatrolHelicopter.OfferTime, 1f, 15f);
			if (_vanillaEventsConfig.BradleyAPC == null)
                _vanillaEventsConfig.BradleyAPC = new EventSettings();
            else
				_vanillaEventsConfig.BradleyAPC.OfferTime = Math.Clamp(_vanillaEventsConfig.BradleyAPC.OfferTime, 1f, 15f);
			
			if (uiList == null || !uiList.Any())
            {
                uiList = GetDefaultClaimOffer();
                Interface.Oxide.DataFileSystem.WriteObject(uiOfferPath, uiList);
            }
			_vanillaEventsUiOffer = ReplacePlaceholders(CuiHelper.ToJson(uiList), EventOfferUI);
			
			SaveVanillaEventsConfig();
        }

		object OnCupboardClearList(BuildingPrivlidge privilege, BasePlayer player)
        {
			if (IsEntityInPvP(player.userID, privilege.net.ID.Value)) return null;
			object result = CanInteractSharedEntity(player, privilege);
			if (_config.RandomRaids_Enabled && result == null && _randomRaidsList.TryGetValue(privilege.transform.position.ToString(), out var rrData))
			{
				List<ulong> list = privilege.authorizedPlayers.ToList();
				NextTick(() =>
				{
					foreach (var userID in list)
					{
						if (!privilege.IsAuthed(userID))
						{
							_rrAllPlayers.Remove(userID);
							rrData.PlayersList.Remove(userID);
						}
					}
					list.Clear();
				});
			}
			if (result != null)
			{
				SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return true;
			}
            return null;
        }
		private void SaveBeachConfig() => Interface.Oxide.DataFileSystem.WriteObject(_newbiePath, _newbieConfig);
		
		object CanMountEntity(BasePlayer player, BaseMountable mountable) => HandleVehicleSeat(player, mountable);
		
                private void SendCounterBar(BasePlayer player, MonumentData monumentData, double endTime, double startTime = 0d)
        {
			if (!_statusIsLoaded) return;
			
			string monumentName = monumentData.GetMonumentName(player.userID);
			Dictionary<int, object> parameters;
			if (startTime > 0d && endTime > startTime)
			{
				parameters = new Dictionary<int, object>(monumentData.StatusProgressBar)
				{
					{ 15, monumentName },
					{ 28, startTime },
					{ 29, endTime }
				};
			}
			else
			{
				parameters = new Dictionary<int, object>(monumentData.StatusBar)
				{
					{ 15, monumentName },
					{ 29, endTime }
				};
				parameters[2] = BarTimeCounter;
			}
			
			AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
		}
		
		private void HandleVehicleKill(BaseEntity vehicle)
        {
			OnEntityExitPVP(vehicle);
            if (_vehiclesList.TryGetValue(vehicle.net.ID.Value, out var vehicleData))
                vehicleData.OnDestroy();
        }
		
		void OnEntitySpawned(LootableCorpse corpse)
        {
			if (corpse.playerSteamID.IsSteamId()) return;
			var parentEnt = corpse.parentEnt;
            if (parentEnt != null && parentEnt.skinID != 0uL)
            {
                corpse.skinID = parentEnt.skinID;
                if (parentEnt.skinID == _rrPluginID && _config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(parentEnt.net.ID.Value, out var rrData))
                {
                    _rrAllRaiders.Add(corpse.net.ID.Value, rrData);
                    rrData.Raiders.Add(corpse.net.ID.Value);
                }
            }
		}
		object OnEntityTakeDamage(Tugboat tugBoat, HitInfo info) => HandleDamageToVehicle(tugBoat, info);
		object CanLootEntity(BasePlayer player, LockedByEntCrate crate) => crate.skinID == 0uL ? CanLootByOwnerID(player, crate) : null;
		
		object OnTrapTrigger(BaseTrap trap, GameObject obj)
        {
			if (trap.IsValid() && trap.OwnerID.IsSteamId())
			{
				var victim = obj?.ToBaseEntity() as BasePlayer;
				if (victim.IsValid() && !IsEntityInPvP(victim.userID, trap.net.ID.Value) && !UrishMumkin(victim, trap.OwnerID))
					return true;
            }
			return null;
		}
		
		private void SendMonumentsBar(BasePlayer player, MonumentData monumentData, string subText = "", int displayTime = 0)
        {
            if (!_statusIsLoaded) return;
            if (string.IsNullOrWhiteSpace(subText))
                subText = lang.GetMessage("MsgMonumentNoAccess", this, player.UserIDString);

            var parameters = new Dictionary<int, object>(monumentData.StatusBar)
            {
                { 15, monumentData.GetMonumentName(player.userID) },
                { 22, subText }
            };
            if (displayTime > 0)
            {
                parameters[2] = BarTimed;
                parameters.Add(29, _unixSeconds + displayTime);
            }

            AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
        }
		
		private void ConfigPermissionLimitsToggled()
        {
            if (_config.RecheckPermissionLimits)
            {
				Subscribe(nameof(OnUserPermissionGranted));
				Subscribe(nameof(OnUserPermissionRevoked));
				Subscribe(nameof(OnUserGroupAdded));
				Subscribe(nameof(OnUserGroupRemoved));
				Subscribe(nameof(OnGroupPermissionGranted));
				Subscribe(nameof(OnGroupPermissionRevoked));
				Subscribe(nameof(OnGroupDeleted));
			}
            else
            {
				Unsubscribe(nameof(OnUserPermissionGranted));
				Unsubscribe(nameof(OnUserPermissionRevoked));
				Unsubscribe(nameof(OnUserGroupAdded));
				Unsubscribe(nameof(OnUserGroupRemoved));
				Unsubscribe(nameof(OnGroupPermissionGranted));
				Unsubscribe(nameof(OnGroupPermissionRevoked));
				Unsubscribe(nameof(OnGroupDeleted));
			}
        }
		
		private static readonly string[] _cmdKeysAdminMonument = { "suffix", "broadcast", "time", "price", "offer", "map_mode", "map_circle", "pvp", "pvp_delay", "bar_progress" };

		object OnCupboardClearList(VehiclePrivilege privilege, BasePlayer player)
        {
			var tugBoat = privilege.GetParentEntity() as Tugboat;
			if (tugBoat.IsValid() && _vehiclesList.TryGetValue(tugBoat.net.ID.Value, out var vehicleData) && vehicleData.IsOwner(player.userID))
			{
				int totalPlayers = privilege.authorizedPlayers.Count() - 1;
                if (totalPlayers < 1)
					SendMessageText(player, lang.GetMessage("MsgPrivlidgeClearEmpty", this, player.UserIDString));
				else
                {
                    privilege.authorizedPlayers.Clear();
                    privilege.UpdateMaxAuthCapacity();
                    privilege.SendNetworkUpdate();
                    privilege.AddPlayer(player);
                    privilege.UpdateMaxAuthCapacity();
                    privilege.SendNetworkUpdate();
					SendMessageText(player, string.Format(lang.GetMessage("MsgPrivlidgeClear", this, player.UserIDString), totalPlayers), false);
				}
                return false;
            }
			return null;
		}
		
		private bool HasBypassQueuePermission(string userID)
        {
			if (_permissionsConfig.PermissionsList[0].BypassQueue)
				return true;
			
			PvEPermission perm;
			for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
			{
				perm = _permissionsConfig.PermissionsList[i];
				if (perm.BypassQueue && permission.UserHasPermission(userID, perm.Name))
					return true;
			}
			return false;
		}
		private string GetMonumentByPos(Vector3 pos) { var a = GetMonumentsByPos(pos); return a.Length > 0 ? a[^1] : string.Empty; }
		
		private void ShowRaidableBasesOffer(BasePlayer player, RBData rbData)
		{
			DestroyUI(player, RBOfferUI);
			CuiHelper.AddUi(player, ReplacePlaceholders(_rbsUiOffer, null, (string)ImageLibrary?.Call("GetImage", RBOfferUI),
				string.Format(lang.GetMessage("MsgRaidableBasesOfferTitle", this, player.UserIDString), new string[] { lang.GetMessage(rbData.Settings.TextKey, this, player.UserIDString) }),
				string.Format(lang.GetMessage("MsgRaidableBasesOfferDescription", this, player.UserIDString), new string[] { string.Format(_config.PriceFormat, rbData.Settings.Price.ToString()) }),
				$"{_commandUI} rb pay {rbData.RaidID.Replace(" ", "")}"));
			_playerUI[player.userID].Add(RBOfferUI);
		}
		
		void CreatePVPMapMarker(string zoneID, Vector3 pos, float radius, string displayName = "", BaseEntity entity = null) =>
			_pvpMarkers[zoneID] = new MarkersPvP(pos, !string.IsNullOrWhiteSpace(displayName) ? displayName : _config.PvPMapMarkersName, radius, entity);
		
		private void SendPvPDelayBar(BasePlayer player, string zoneID, double delay, double delayEnd)
        {
			if (!_statusIsLoaded) return;
			
			bool isCustomZone = false;
			Dictionary<int, object> bar;
			if (_monumentsList.TryGetValue(zoneID, out var monumentData))
				bar = monumentData.StatusProgressBar;
			else if (_rbList.TryGetValue(zoneID, out var rbData))
				bar = rbData.StatusProgressBar;
			else
			{
				bar = _pvpDelayBar;
				isCustomZone = true;
			}
			
			var parameters = new Dictionary<int, object>(bar)
            {
                { 15, lang.GetMessage("MsgPvPDelayBar", this, player.UserIDString) },
                { 28, _unixSeconds },
                { 29, delayEnd }
			};
			if (isCustomZone)
				parameters[0] = zoneID;
			
			AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
			
			player.SendEffect();
			SendMessage(player, "MsgPvPDelay", new string[] { delay.ToString() });
		}
		
		object CanAssignBed(BasePlayer player, SleepingBag bag, ulong targetPlayerId)
		{
			int limit = GetBedsLimit(targetPlayerId.ToString()), total = CountBeds(targetPlayerId);
			var basePlayer = RelationshipManager.FindByID(targetPlayerId);
			if (limit >= 0 && total >= limit)
			{
				player.ShowToast(GameTip.Styles.Red_Normal, bag.cannotAssignBedPhrase, false, basePlayer?.displayName ?? "other player");
				return true;
            }
			NextTick(() => player.ShowToast(GameTip.Styles.Blue_Long, SleepingBag.bagLimitPhrase, false, CountBeds(player.userID).ToString(), limit.ToString()));
			return null;
		}
		
		object CanLootEntity(BasePlayer player, ResourceExtractorFuelStorage container)
        {
			if (_unrestrictedLooters.Contains(player.userID)) return AdminOpenLoot(player, container);
			if (IsEntityInPvP(player.userID, container.net.ID.Value)) return null;
			object result = null;
			var parent = container.GetParentEntity() as MiningQuarry;
			if (parent != null && !parent.isStatic && parent.OwnerID.IsSteamId())
				result = CanInteractSharedEntity(player, parent);
			else if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
				result = monumentData.CanLoot(player);
			
			if (result != null)
				SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
			return result;
		}
		void OnFireBallSpread(FireBall fireBall, BaseEntity entity) => entity.OwnerID = fireBall.OwnerID;
		
		private static bool TryGetPlayerData(string userIDStr, out PlayerData result)
        {
			ulong.TryParse(userIDStr, out var userID);
			return _playersList.TryGetValue(userID, out result);
		}
		
		private object CanLootCar(BasePlayer player, BaseVehicle vehicle = null)
        {
			if (vehicle.IsValid() && _vehiclesList.TryGetValue(vehicle.net.ID.Value, out var vehicleData))
            {
				object result = vehicleData.CanLoot(player);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgVehicleCantInteract", this, player.UserIDString));
				return result;
			}
			return null;
		}
		
		object OnCollectiblePickup(CollectibleEntity collectible, BasePlayer player)
        {
			if (player.IsValid() && collectible.IsValid() && collectible.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, collectible.net.ID.Value))
            {
				object result = null;
				
				if (collectible.prefabID == 1388355532u)
                {
                    if (_monumentsList.TryGetValue(GetEntityMonument(collectible), out var monumentData))
                        result = monumentData.CanPickup(player);
                }
                else if (_config.PreventPickUpCollectible)
                    result = player.BinoMumkin();
				
				if (result != null)
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                return result;
			}
            return null;
		}
		private Vector3 GetMonumentPosition(string monumentID) => (Vector3)(MonumentsWatcher?.Call(MonumentGetMonumentPosition, monumentID) ?? Vector3.zero);
		
		object OnStructureUpgrade(BaseCombatEntity entity, BasePlayer player, BuildingGrade.Enum grade) => !entity.OwnerID.IsSteamId() || IsEntityInPvP(player.userID, entity.net.ID.Value) ? null : player.TasirMumkin(entity.OwnerID);
		public static void RealPVE_DroneItemDrop(DroneStorage droneStorage, DroppedItem droppedItem)
		{
			if (droppedItem != null && droppedItem.DroppedBy == 0uL)
				droppedItem.DroppedBy = droneStorage?.Drone?.ToPlayer()?.userID ?? 0uL;
		}

		private object CanLootWeaponRack(BasePlayer player, WeaponRack rack)
		{
			if (rack.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, rack.net.ID.Value))
			{
				object result = CanInteractSharedEntity(player, rack);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteractWeaponRack", this, player.UserIDString));
				return result;
			}
			return null;
		}
		void OnRocketLaunched(BasePlayer player, BaseEntity entity) => entity.OwnerID = player.userID;

        private CuiElementContainer GetVehicleDefaultPanel(string userID, VehicleData vehicleData)
        {
            string description, descriptionValue;
            bool notOwner = vehicleData.OwnerID != 0uL && !vehicleData.IsOwner(userID);
            var container = new CuiElementContainer();
            container.Add(new CuiPanel
            {
                RectTransform = { AnchorMin = "0.5 0", AnchorMax = "0.5 0", OffsetMin = "193 230", OffsetMax = "573 339" },
                Image = { Color = "0.969 0.922 0.882 0.035", Material = "assets/content/ui/ui.background.tiletex.psd" }
            }, "Overlay", _uiVehiclePanel);

            string[] values = new string[5];
            values[0] = vehicleData.ID.ToString();
            values[1] = lang.GetMessage($"MsgVehicle{vehicleData.Type}", this, userID).FirstToUpper();
            values[2] = lang.GetMessage($"MsgVehicle{vehicleData.Category}", this, userID);
            values[3] = !string.IsNullOrWhiteSpace(vehicleData.RegistrationDate) ? $"{vehicleData.RegistrationDate}(UTC)" : lang.GetMessage("MsgNoDate", this, userID);
            if (notOwner)
            {
                var owner = BasePlayer.FindByID(vehicleData.OwnerID);
                values[4] = owner != null ? owner.displayName : vehicleData.OwnerID.ToString();
                description = lang.GetMessage("MsgVehicleDialogDescriptionNotOwner", this, userID);
                descriptionValue = string.Format(lang.GetMessage("MsgVehicleDialogDescriptionNotOwnerValue", this, userID), values);
            }
            else if (vehicleData.OwnerID == 0)
            {
                if (_economicsIsLoaded)
					values[4] = $"{string.Format(_config.PriceFormat, GetVehiclePrice(userID, vehicleData.Type))}({string.Format(_config.PriceFormat, GetBalance(userID))})";
				else
                    values[4] = lang.GetMessage("MsgFree", this, userID);
                description = lang.GetMessage("MsgVehicleDialogDescription", this, userID);
                descriptionValue = string.Format(lang.GetMessage("MsgVehicleDialogDescriptionValue", this, userID), values);
            }

            else
            {
                description = lang.GetMessage("MsgVehicleDialogDescriptionRegistered", this, userID);
                descriptionValue = string.Format(lang.GetMessage("MsgVehicleDialogDescriptionRegisteredValue", this, userID), values);
            }
            container.Add(new CuiLabel
            {
                Text =
                {
                    Text = lang.GetMessage("MsgVehicleDialogTitle", this, userID),
                    FontSize = 12,
                    Color = WhiteColor,
                    Align = TextAnchor.UpperCenter
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "5 85", OffsetMax = "-5 104" }
            }, _uiVehiclePanel);
            container.Add(new CuiElement
            {
                Name = "Description",
                Parent = _uiVehiclePanel,
                Components =
                {
                    new CuiImageComponent { Color = "0 0 0 0" },
                    new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = $"5 {(notOwner ? "5" : "30")}", OffsetMax = "-5 -25" },
                    new CuiScrollViewComponent
                    {
                        ContentTransform = new CuiRectTransform { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = $"0 -{(12 *  description.Split('\n').Length * 1.2) - (notOwner ? 80 : 53)}", OffsetMax = "0 0" },
                        Vertical = true,
                        MovementType = ScrollRect.MovementType.Elastic,
                        ScrollSensitivity = 20f,
                        HorizontalScrollbar = null,
                        VerticalScrollbar = null
                    }
                }
            });
            container.Add(new CuiLabel
            {
                Text =
                {
                    Text = description,
                    Font = "RobotoCondensed-Regular.ttf",
                    FontSize = 12,
                    Color = WhiteColor,
                    Align = TextAnchor.MiddleLeft
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "5 0", OffsetMax = "0 0" }
            }, "Description");
            container.Add(new CuiLabel
            {
                Text =
                {
                    Text = descriptionValue,
                    Font = "RobotoCondensed-Regular.ttf",
                    FontSize = 12,
                    Color = WhiteColor,
                    Align = TextAnchor.MiddleRight
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "-5 0" }
            }, "Description");
            if (!notOwner)
            {
                container.Add(new CuiButton
                {
                    Text =
                    {
                        Text = lang.GetMessage(vehicleData.OwnerID == 0uL ? "MsgVehicleDialogLink" : "MsgVehicleDialogUnLink", this, userID),
                        Font = "RobotoCondensed-Regular.ttf",
                        FontSize = 12,
                        Color = WhiteColor,
                        Align = TextAnchor.MiddleCenter
                    },
                    Button =
                    {
                        Command = $"{_commandUI} vehicle {(vehicleData.OwnerID == 0uL ? "link" : "unlink")} {vehicleData.ID}",
                        Color = vehicleData.OwnerID == 0uL ? "0.41 0.55 0.41 0.8" : "1 0.4 0.4 0.8"
                    },
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 0", OffsetMin = "5 5", OffsetMax = "-5 25" }
                }, _uiVehiclePanel);
            }
            return container;
        }
        
        		object CanBypassQueue(Network.Connection connection) => ServerUsers.Is(connection.userid, ServerUsers.UserGroup.Owner) || HasBypassQueuePermission(connection.userid.ToString()) ? true : null;
		
		void OnEntitySpawned(DroppedItemContainer container)
        {
			if (!container.playerSteamID.IsSteamId() && TryGetRaidBase(container.transform.position, out var rbData))
                container.skinID = _rbPluginID;
        }
		
		private class NewbieConfig
		{
			[JsonProperty(PropertyName = "Is it worth changing the list of items given at spawn on the beach?")]
			public bool Respawn_Override = true;
			
			[JsonProperty(PropertyName = "Is it worth changing the list of items given at spawn in tutorial mode?")]
            public bool Tutorial_Override = false;
			
			[JsonProperty(PropertyName = "List of items for the main inventory")]
			public HashSet<NewbieItem> Respawn_Main;
			
			[JsonProperty(PropertyName = "List of items for the belt")]
			public HashSet<NewbieItem> Respawn_Belt;
			
			[JsonProperty(PropertyName = "List of items for clothing")]
			public HashSet<NewbieItem> Respawn_Wear;
			
			public Oxide.Core.VersionNumber Version;
		}
		private static Dictionary<ulong, TeamData> _teamsList;
		private const string RBUI = "RealPVE_RaidableBases", RBLootUI = "RealPVE_RaidableBases_Loot", RBOfferUI = "RealPVE_RaidableBasesOffer", RBTextLootRemaining = "MsgRaidableBasesBarTextLootRemaining", RBTextLootCompleted = "MsgRaidableBasesBarTextLootCompleted";

		void OnMonumentsWatcherLoaded()
		{
			_watcherIsLoaded = true;
			_harborEventMonument = string.Empty;
            _monumentsList.Clear();
            var pvpMonuments = new HashSet<string>();
            var monuments = (Dictionary<string, string>)(MonumentsWatcher?.Call("GetAllMonumentsCategories") ?? new Dictionary<string, string>());
            MonumentData monumentData;
            MonumentSettings monumentSettings;
            foreach (var kvp in monuments)
            {
                if (!_monumentsConfig.TrackedCategories.Contains(kvp.Value) || _monumentsConfig.IgnoredNames.Contains(kvp.Key)) continue;
                var monumentID = kvp.Key;
                if (monumentID.Contains("CargoShip"))
                {
                    if (!_monumentsConfig.IgnoredNames.Contains("CargoShip") && IsMonumentCargoValid(monumentID))
                    {
                        monumentSettings = _monumentsConfig.MonumentsSettings["CargoShip"];
                        _monumentsList[monumentID] = monumentData = new MonumentData(monumentID, monumentSettings, true);
                        if (monumentSettings.IsPvP)
                            pvpMonuments.Add(monumentData.MonumentNameEng);
                    }
                    continue;
                }
                if (!_monumentsConfig.MonumentsSettings.TryGetValue(monumentID, out monumentSettings) || monumentSettings == null)
                    _monumentsConfig.MonumentsSettings[monumentID] = monumentSettings = new MonumentSettings(monumentID, kvp.Value);
                _monumentsList[monumentID] = monumentData = new MonumentData(monumentID, monumentSettings);
                if (monumentSettings.IsPvP)
                    pvpMonuments.Add(monumentData.MonumentNameEng);
            }
            SaveConfig();
            LoadMonumentsImages();
            if (!_monumentsList.Any())
                return;
            if (pvpMonuments.Any())
                PrintWarning($"PvP flagged monuments: {string.Join(", ", pvpMonuments)}");
            foreach (var player in BasePlayer.activePlayerList)
            {
                if (player.userID.IsSteamId() && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out monumentData))
                    monumentData.OnPlayerEnter(player);
            }
            Subscribe(nameof(OnNpcTarget));
            Subscribe(nameof(OnEntityEnteredMonument));
            Subscribe(nameof(OnEntityExitedMonument));
            Subscribe(nameof(OnPlayerEnteredMonument));
            Subscribe(nameof(OnPlayerExitedMonument));
            Subscribe(nameof(OnCargoWatcherCreated));
            Subscribe(nameof(OnCargoWatcherDeleted));
            if (_monumentsConfig.CargoShip_HarborToPvP || _monumentsConfig.CargoShip_LargeHarborToPvP)
            {
                Subscribe(nameof(OnCargoShipHarborArrived));
                Subscribe(nameof(OnCargoShipHarborLeave));
            }
            Subscribe(nameof(OnHarborEventStart));
            Subscribe(nameof(OnHarborEventEnd));
            if (_monumentsList.ContainsKey("excavator_1"))
            {
                Subscribe(nameof(OnExcavatorResourceSet));
                Subscribe(nameof(OnExcavatorSuppliesRequest));
                Subscribe(nameof(OnExcavatorSuppliesRequested));
            }
            Subscribe(nameof(CanHackCrate));
            SaveMonumentsConfig();
		}
		object OnMixingTableToggle(MixingTable table, BasePlayer player) => HandleContainerToggle(player, table);

        object CanBradleyApcTarget(BradleyAPC bradley, BasePlayer player)
            => _eventsList.TryGetValue(bradley.net.ID.Value, out var patrolData) ? patrolData.CanBeTargeted(player) : null;

        private void SendRaidableBasesLootBar(BasePlayer player, RBData rbData)
        {
            if (!_statusIsLoaded) return;

            var barSettings = rbData.Settings.Bar;
            var parameters = new Dictionary<int, object>(rbData.StatusBar)
            {
                { 15, lang.GetMessage(RBTextLootRemaining, this, player.UserIDString) },
                { 22, rbData.LootRemain > 0 ? rbData.LootRemain.ToString() : lang.GetMessage(RBTextLootCompleted, this, player.UserIDString) },
                { 29, _unixSeconds + 5 }
            };
            parameters[0] = RBLootUI;
            parameters[2] = BarTimed;
            parameters[4] = barSettings.Order + 1;

            AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
        }
		private static bool _imgLibIsLoaded = false, _economicsIsLoaded = false, _watcherIsLoaded = false, _statusIsLoaded = false, _friendsIsLoaded = false;
		void OnEntityKill(Bike bike) => HandleVehicleKill(bike);
		object CanLootEntity(BasePlayer player, FishMount fishMount) => CanLootStorage(player, fishMount);
		object CanLootEntity(BasePlayer player, DroppedItemContainer container) => CanLootCombatEntity(player, container, container.playerSteamID);
		
		public class MonumentSettings
        {
			[JsonProperty(PropertyName = "Category(This parameter is just a hint. Changes won’t have any effect)", Order = 0)]
			public string Category { get; set; } = string.Empty;
			
			[JsonProperty(PropertyName = "Is it worth displaying the suffix(if any) in the monument's name?", Order = 1)]
            public bool ShowSuffix { get; set; } = true;
			
			[JsonProperty(PropertyName = "Is it worth notifying all players about the occupation/release of the monument?", Order = 2)]
            public bool Broadcast { get; set; } = true;
			
			private double _price = 0d;
            [JsonProperty(PropertyName = "The cost for the right to loot the monument. A value of 0 makes the monument free", Order = 3)]
            public double Price
            {
                get => _price;
                set => _price = value < 0d ? 0d : value;
            }
			
			private int _lootingTime = 900;
            [JsonProperty(PropertyName = "The time in seconds(1-3600) given for looting the monument", Order = 4)]
            public int LootingTime
            {
                get => _lootingTime;
                set => _lootingTime = Math.Clamp(value, 1, 3600);
            }
			
			private float _offerTime = 5f;
			[JsonProperty(PropertyName = "The time in seconds(1-15) given to make a decision to purchase the monument", Order = 5)]
			public float OfferTime
            {
                get => _offerTime;
                set => _offerTime = value < 0f ? 0f : value;
            }
			
			private int _mapMarkerMode = 0;
			[JsonProperty(PropertyName = "Map marker display mode: 0 - disabled, 1 - enabled, 2 - enabled during PvP mode", Order = 6)]
			public int MapMarkerMode
            {
                get => _mapMarkerMode;
                set => _mapMarkerMode = Math.Clamp(value, 0, 2);
            }
			
			[JsonProperty(PropertyName = "Is it worth creating a circle in the map marker?", Order = 7)]
			public bool MapMarkerCircle { get; set; } = true;
			
			[JsonProperty(PropertyName = "PvP - Is PvP enabled at this monument? If so, players will be able to kill each other and loot will be publicly accessible", Order = 8)]
			public bool IsPvP { get; set; } = false;
			
			private float _pvpDelay = 10f;
			[JsonProperty(PropertyName = "PvP - The time in seconds(0-60) during which the player retains PvP mode after leaving the PvP monument", Order = 9)]
			public float PvPDelay
            {
                get => _pvpDelay;
                set => _pvpDelay = Mathf.Clamp(value, 0f, 60f);
            }
			
			[JsonProperty(PropertyName = "Is it worth using a progress bar for bars with a counter?", Order = 10)]
			public bool UseProgressBar { get; set; } = true;
			
			[JsonProperty(PropertyName = "Settings for the status bar", Order = 11)]
			public BarSettings Bar { get; set; }
			
			[JsonProperty(PropertyName = "Settings for the progress status bar", Order = 12)]
			public ProgressBarSettings ProgressBar { get; set; }
			
			public MonumentSettings() {}
			public MonumentSettings(string monumentID, string type)
			{
				Category = type;
				Bar = new BarSettings();
				ProgressBar = new ProgressBarSettings();
				switch (monumentID)
                {
					case "CargoShip":
						ShowSuffix = false;
						IsPvP = true;
						LootingTime = 3600;
						Price = 50d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/6FYEEqJ.png";
						Bar.Image_Local = "RealPVE_CargoShip";
						break;
					case "airfield_1":
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/nMvE8pS.png";
						Bar.Image_Local = "RealPVE_airfield_1";
						break;
					case "arctic_research_base_a":
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/kerlYFh.png";
						Bar.Image_Local = "RealPVE_arctic_research_base_a";
						break;
					case "bandit_town":
						Bar.Image_Url = "https://i.imgur.com/CwjNgXf.png";
						Bar.Image_Local = "RealPVE_bandit_town";
						break;
					case "compound":
						Bar.Image_Url = "https://i.imgur.com/KnGihg3.png";
						Bar.Image_Local = "RealPVE_compound";
						break;
					case var id when id.Contains("desert_military_base_"):
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/F4fkg2W.png";
						Bar.Image_Local = "RealPVE_desert_military_base";
						break;
					case "excavator_1":
						LootingTime = 1800;
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/3drbedg.png";
						Bar.Image_Local = "RealPVE_excavator_1";
						break;
					case "ferry_terminal_1":
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/awUrIwA.png";
						Bar.Image_Local = "RealPVE_ferry_terminal_1";
						break;
					case var id when id.Contains("gas_station_1"):
						Broadcast = false;
						Bar.Image_Url = "https://i.imgur.com/aaSmHZE.png";
						Bar.Image_Local = "RealPVE_gas_station_1";
						break;
					case "harbor_1":
						LootingTime = 1800;
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/oPZOaRC.png";
						Bar.Image_Local = "RealPVE_harbor_1";
						break;
					case "harbor_2":
						LootingTime = 1200;
						Price = 10d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/mc6rDqV.png";
						Bar.Image_Local = "RealPVE_harbor_2";
						break;
					case "jungle_ziggurat_a":
                        Price = 15d;
                        MapMarkerMode = 1;
                        Bar.Image_Url = "https://i.imgur.com/mzeq5od.png";
                        Bar.Image_Local = "RealPVE_jungle_ziggurat_a";
                        break;
					case "junkyard_1":
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/yvj6Yxj.png";
                        Bar.Image_Local = "RealPVE_junkyard_1";
                        break;
					case "launch_site_1":
						IsPvP = true;
						LootingTime = 1800;
						Price = 25d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/AEzabIG.png";
						Bar.Image_Local = "RealPVE_launch_site_1";
						break;
					case var id when id.Contains("lighthouse"):
						Broadcast = false;
						Bar.Image_Url = "https://i.imgur.com/YFEo2kX.png";
						Bar.Image_Local = "RealPVE_lighthouse";
						break;
					case "military_tunnel_1":
						LootingTime = 1800;
						Price = 25d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/71gSdrf.png";
						Bar.Image_Local = "RealPVE_military_tunnel_1";
						break;
					case "nuclear_missile_silo":
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/QBW2i7O.png";
						Bar.Image_Local = "RealPVE_nuclear_missile_silo";
						break;
					case "oilrig_1":
                        LootingTime = 1800;
                        Price = 25d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/iqWG4dk.png";
                        Bar.Image_Local = "RealPVE_oilrig_1";
                        break;
					case "oilrig_2":
						LootingTime = 1800;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/sZzZHHi.png";
						Bar.Image_Local = "RealPVE_oilrig_2";
						break;
					case "powerplant_1":
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/PP1qXmZ.png";
						Bar.Image_Local = "RealPVE_powerplant_1";
						break;
					case "radtown_1":
                        Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/HyXnrbu.png";
						Bar.Image_Local = "RealPVE_radtown_1";
						break;
					case "radtown_small_3":
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/GHvhPNc.png";
						Bar.Image_Local = "RealPVE_radtown_small_3";
						break;
					case "satellite_dish":
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/kJxFYH9.png";
						Bar.Image_Local = "RealPVE_satellite_dish";
						break;
					case "sphere_tank":
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/V2b9pSm.png";
						Bar.Image_Local = "RealPVE_sphere_tank";
						break;
					case "stables_a":
						Bar.Image_Url = "https://i.imgur.com/D3aG1Tm.png";
						Bar.Image_Local = "RealPVE_stables_a";
						break;
					case "stables_b":
						Bar.Image_Url = "https://i.imgur.com/YbGhH89.png";
						Bar.Image_Local = "RealPVE_stables_b";
						break;
					case var id when id.Contains("station-"):
						Price = 10d;
						Bar.Image_Url = "https://i.imgur.com/33snptw.png";
						Bar.Image_Local = "RealPVE_Station";
						break;
					case var id when id.Contains("supermarket_1"):
						Broadcast = false;
						Bar.Image_Url = "https://i.imgur.com/160Wsti.png";
						Bar.Image_Local = "RealPVE_supermarket_1";
						break;
					case "trainyard_1":
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/6QeCaNP.png";
						Bar.Image_Local = "RealPVE_trainyard_1";
						break;
					case var id when id.Contains("underwater_lab_"):
						LootingTime = 1200;
						Price = 20d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/4nEZryz.png";
						Bar.Image_Local = "RealPVE_underwater_lab";
						break;
					case var id when id.Contains("warehouse"):
						Broadcast = false;
						Bar.Image_Url = "https://i.imgur.com/8rEzWNP.png";
						Bar.Image_Local = "RealPVE_warehouse";
						break;
					case "water_treatment_plant_1":
						Price = 15d;
						MapMarkerMode = 1;
						Bar.Image_Url = "https://i.imgur.com/jmE44e8.png";
						Bar.Image_Local = "RealPVE_water_treatment_plant_1";
						break;
					default:
						Broadcast = false;
						break;
				}
			}
		}

        void OnDeletedDynamicPVP(string zoneID, string eventName)
        {
			_dynamicPvPs.Remove(zoneID);
			DeletePVPMapMarker(zoneID);
			
			string monumentID;
            HashSet<string> zones;
            var remMonuments = new List<string>();
            foreach (var kvp in _pvpChangedMonuments)
            {
                monumentID = kvp.Key;
                zones = kvp.Value;
                if (zones.Contains(zoneID))
                {
                    zones.Remove(zoneID);
                    if (!zones.Any())
                    {
                        remMonuments.Add(monumentID);
                        if (_monumentsList.TryGetValue(monumentID, out var monumentData))
                            monumentData.RemovePvP();
                    }
                }
            }
            foreach (var id in remMonuments)
                _pvpChangedMonuments.Remove(id);
		}
		
		private void UpdateTurretTrigger(GunTrap gunTrap)
        {
            if (gunTrap != null)
				UpdateTurretTrigger(gunTrap.trigger);
		}
		
		private void GiveDefaultItems(BasePlayer player)
        {
			if (!player.IsValid() || (!_newbieConfig.Tutorial_Override && player.IsInTutorial) || player.isMounted) return;
			var inventory = player.inventory;
			bool canOverride = true;
            var bags = Pool.Get<List<SleepingBag>>();
            Vis.Entities(inventory.baseEntity.transform.position, 0.1f, bags);
            foreach (var bag in bags)
            {
                if (bag.deployerUserID == player.userID)
                {
                    canOverride = false;
                    break;
                }
            }
            Pool.FreeUnmanaged(ref bags);
			if (!canOverride)
				return;
			
			inventory.Strip();
            foreach (var rItem in _newbieConfig.Respawn_Main)
            {
                var item = ItemManager.CreateByName(rItem.ShortName, rItem.Amount, rItem.SkinID);
                if (item != null)
                {
                    if (!string.IsNullOrWhiteSpace(rItem.Text))
                        item.text = lang.GetMessage(rItem.Text, this, player.UserIDString);
                    item.MoveToContainer(inventory.containerMain, rItem.Slot);
                }
            }
            foreach (var rItem in _newbieConfig.Respawn_Belt)
            {
                var item = ItemManager.CreateByName(rItem.ShortName, rItem.Amount, rItem.SkinID);
                if (item != null)
                {
                    if (!string.IsNullOrWhiteSpace(rItem.Text))
                        item.text = lang.GetMessage(rItem.Text, this, player.UserIDString);
                    item.MoveToContainer(inventory.containerBelt, rItem.Slot);
                }
            }
            foreach (var rItem in _newbieConfig.Respawn_Wear)
            {
                var item = ItemManager.CreateByName(rItem.ShortName, rItem.Amount, rItem.SkinID);
                if (item != null)
                {
                    if (!string.IsNullOrWhiteSpace(rItem.Text))
                        item.text = lang.GetMessage(rItem.Text, this, player.UserIDString);
                    item.MoveToContainer(inventory.containerWear, rItem.Slot);
                }
            }

            if (PlayerInventory.IsBirthday())
            {
                inventory.GiveItem(ItemManager.CreateByName("cakefiveyear", 1, 0uL), inventory.containerBelt);
                inventory.GiveItem(ItemManager.CreateByName("partyhat", 1, 0uL), inventory.containerWear);
            }
            if (PlayerInventory.IsChristmas())
            {
                inventory.GiveItem(ItemManager.CreateByName("snowball", 1, 0uL), inventory.containerBelt);
                inventory.GiveItem(ItemManager.CreateByName("snowball", 1, 0uL), inventory.containerBelt);
                inventory.GiveItem(ItemManager.CreateByName("snowball", 1, 0uL), inventory.containerBelt);
            }
        }
		
		private void InitTeams()
        {
            var existedTeams = Pool.Get<List<ulong>>();
			foreach (var team in RelationshipManager.ServerInstance.teams.Values)
            {
				existedTeams.Add(team.teamID);
				if (!_teamsList.ContainsKey(team.teamID) || _teamsList[team.teamID] == null)
					_teamsList[team.teamID] = new TeamData(team.teamID, _config.PvPTeamFF);
            }
			foreach (var team in _teamsList.Values.ToList())
            {
				if (!existedTeams.Contains(team.TeamID))
					_teamsList.Remove(team.TeamID);
            }
			Pool.FreeUnmanaged(ref existedTeams);
		}

		private int GetSheltersLimit(string userID)
        {
			PvEPermission perm;
            int result = _permissionsConfig.PermissionsList[0].Shelters;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				if (result < 0)
					break;
				perm = _permissionsConfig.PermissionsList[i];
                if ((perm.Shelters < 0 || perm.Shelters > result) && permission.UserHasPermission(userID, perm.Name))
                    result = perm.Shelters;
            }
            return result;
		}
		private static Dictionary<ulong, SharedEntity> _sharedEntities;
		object OnFrankensteinPetSleep(FrankensteinPet pet, FrankensteinTable table, BasePlayer player) => HandleFrankensteinTableUse(table, player);
		
		private static bool UrishMumkin(ulong a, ulong b) => b == a || (RelationshipManager.ServerInstance.playerToTeam.TryGetValue(a, out var c) && c.members.Contains(b) && _teamsList.TryGetValue(c.teamID, out var d) && d.FriendlyFire);
		
		void OnCrateSpawned(BradleyAPC bradley, LockedByEntCrate lockedCrate)
        {
			if (_eventsList.TryGetValue(bradley.net.ID.Value, out var eventData))
				eventData.LockedCrateSpawned(lockedCrate);
		}
		private static void SendMessageText(IPlayer player, string text, bool isWarning = true)
        {
			if (_config.GameTips_Enabled && !player.IsServer)
                player.Command(Str_Showtoast, (int)(isWarning ? GameTip.Styles.Error : GameTip.Styles.Blue_Long), text, string.Empty);
            else
                player.Reply(text);
        }

        
		
		public static IEnumerable<CodeInstruction> ModFireBall(IEnumerable<CodeInstruction> instructions, ILGenerator generator)
        {
            var result = new List<CodeInstruction>(instructions);
            int index = -1;
            LocalBuilder item = null;
            CodeInstruction code;
            for (int i = 0; i < result.Count; i++)
            {
                code = result[i];
                if (code.opcode == OpCodes.Stloc_S && code.operand is LocalBuilder local)
                {
                    if (local.LocalIndex == 7)
                        item = local;
                }
                else if (code.opcode == OpCodes.Callvirt && code.operand is MethodInfo method && method.Name == "Spawn")
                    index = i;
			}
            if (index >= 0 && item != null)
            {
				result.Insert(index + 1, new CodeInstruction(OpCodes.Ldloc_S, item));
                result.Insert(index + 2, new CodeInstruction(OpCodes.Ldarg_1));
				result.Insert(index + 3, new CodeInstruction(OpCodes.Ldfld, AccessTools.Field(typeof(HitInfo), "Initiator")));
				result.Insert(index + 4, new CodeInstruction(OpCodes.Stfld, AccessTools.Field(typeof(BaseEntity), "creatorEntity")));
			}
            return result;
        }

		object OnExcavatorResourceSet(ExcavatorArm arm, string resourceName, BasePlayer player)
		{
			if (player != null && !_unrestrictedLooters.Contains(player.userID) && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
			{
				object result = monumentData.CanLoot(player);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return result;
			}
			return null;
		}
		object CanLootEntity(BasePlayer player, FlameTurret flameTurret) => CanLootStorage(player, flameTurret);

        private void SendRBStrangerBar(BasePlayer player, RBData rbData)
        {
            if (!_statusIsLoaded) return;

            var parameters = new Dictionary<int, object>(rbData.StatusBar)
            {
                { 15, rbData.OwnerName },
                { 22, lang.GetMessage("MsgRaidableBasesBarNoAccess", this, player.UserIDString) }
            };

            AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
        }

		object CanLootEntity(BasePlayer player, Workbench workbench) => null;

		object OnConveyorFiltersChange(IOEntity entity, BasePlayer player)
        {
			if (!_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, entity.net.ID.Value))
            {
                object result = null;
                if (entity.OwnerID.IsSteamId())
                    result = CanInteractSharedEntity(player, entity);
                else if (_monumentsList.TryGetValue(GetEntityMonument(entity), out var monumentData))
                    result = monumentData.CanLoot(player);
				
				if (result != null)
                {
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                    return true;
                }
                return null;
			}
            return null;
		}
		
		object OnEntityTakeDamage(HelicopterDebris debris, HitInfo info)
        {
			if (info == null || !debris.IsValid() || debris.skinID != 0uL || !debris.OwnerID.IsSteamId()) return null;
			switch (TryGetAttacker(info, out var initiator))
            {
				case AttackerIndex.BasePlayer:
					var attacker = (BasePlayer)initiator;
					if (attacker.userID.IsSteamId() && !_unrestrictedLooters.Contains(attacker.userID) && attacker.TasirMumkin(debris.OwnerID) != null)
                    {
                        if (IsFriend(attacker.UserIDString, debris.OwnerID.ToString()))
                            return null;
                        info.Urma();
                    }
					break;
				case AttackerIndex.PlayerOwnerMissing:
					info.Urma();
					break;
			}
			return null;
		}
		
		void OnLootEntity(BasePlayer player, ModularCarGarage garage)
        {
			var modularCar = garage?.carOccupant;
			if (modularCar.IsValid() && _vehiclesList.TryGetValue(modularCar.net.ID.Value, out var vehicleData))
				ShowVehiclePanels(player, vehicleData);
		}
		
		protected override void SaveConfig() => Config.WriteObject(_config);
		
		void OnPortalUsed(BasePlayer player, HalloweenDungeon halloween) => HandlePortalUsed(player, halloween);
		private string _commandUI = string.Empty;
		
		public static bool ModEntityEnter(AutoTurret __instance, BaseNetworkable entity)
		{
			if (__instance.IsValid() && entity is Drone drone)
			{
				ulong userID = drone.ToPlayer()?.userID ?? drone.OwnerID;
				if (userID.IsSteamId() && !Instance.HandleEntityEnterTrap(drone, userID))
                {
					if (__instance.target == drone)
						__instance.SetTarget(null);
					__instance.targetTrigger?.entityContents?.Remove(drone);
					return false;
				}
			}
			return true;
        }
		
		void OnGroupPermissionGranted(string groupName, string permName)
        {
			if (!_permNames.Contains(permName, StringComparer.OrdinalIgnoreCase)) return;
			foreach (var userStr in permission.GetUsersInGroup(groupName))
				HandlePermissionsUpdate(userStr.Substring(0, userStr.IndexOf('(')).Trim());
		}
		
		public static void ModAssignInitiator(BaseProjectile __instance, HitInfo info)
		{
			if (__instance == null || info == null) return;
			var turret = info.Initiator as AutoTurret;
            if (turret != null)
            {
                ulong userID = turret.ControllingViewerId?.SteamId ?? 0uL;
                if (userID.IsSteamId())
                {
                    var player = BasePlayer.FindAwakeOrSleepingByID(userID);
                    if (player != null)
                        info.Initiator = player;
                }
            }
        }
		
		public static bool ModHorseDetach(RidableHorse __instance, BaseEntity.RPCMessage msg)
        {
			if (__instance.IsValid() && __instance.IsTowing)
            {
                var player = msg.player;
                if (player != null && (!__instance.AnyMounted() || player.GetMounted().VehicleParent() == __instance) && Instance.OnRidableAnimalDetach(__instance, player) == null)
                {
                    __instance.TowDetach();
                    Effect.server.Run(__instance.towingDetachEffect.resourcePath, __instance, 0u, Vector3.zero, Vector3.zero);
                }
            }
            return false;
		}
		
		private bool HandleEntityEnterTrap(BaseEntity entity, ulong userID)
        {
			if (entity.skinID == 0uL)
            {
                if (entity.OwnerID.IsSteamId() && !IsEntityInPvP(userID, entity.net.ID.Value))
                    return false;
			}
            else if (entity.skinID == _rbPluginID)
            {
                if (TryGetRaidBase(entity.transform.position, out var rbData) && !rbData.CanInteractWithRaid(userID))
                    return false;
			}
			return true;
		}
		
		public class RBData
        {
			public string RaidID { get; private set; }
			public Vector3 Position { get; private set; } = Vector3.zero;
			public float RadiusSquared { get; private set; } = 2500f;
			
			private ulong _ownerID = 0uL;
			public ulong OwnerID
			{
				get => _ownerID;
				set
				{
					_ownerID = value;
					OwnerIDString = _ownerID.ToString();
					var owner = BasePlayer.FindAwakeOrSleepingByID(_ownerID);
					OwnerName = owner != null ? owner.displayName : (_ownerID == 0 ? "None" : OwnerIDString);
				}
			}
			public string OwnerIDString { get; private set; } = string.Empty;
			public string OwnerName { get; private set; } = string.Empty;
			
			public double StartTime { get; set; }
			public double DespawnTime { get; set; }
			public Dictionary<ulong, BasePlayer> ParticipantsList = Pool.Get<Dictionary<ulong, BasePlayer>>();
			public Dictionary<ulong, BasePlayer> PlayersList = Pool.Get<Dictionary<ulong, BasePlayer>>();
			public int LootRemain { get; set; }
			public RBSettings Settings { get; set; }
			public Dictionary<int, object> StatusBar { get; private set; }
			public Dictionary<int, object> StatusProgressBar { get; private set; }
			
			public bool IsPvP { get; private set; }
			
			public RBData(string raidID, Vector3 pos, int level, bool isPVP, float radius, ulong ownerID, DateTime despawnTime, int lootRemain, List<BasePlayer> intruders = null)
			{
				RaidID = raidID;
				Position = pos;
				RadiusSquared = radius * radius;
				OwnerID = ownerID;
				
				bool val = false;
				RBSettings rbSettings;
				for (int i = 0; i < _rbsConfig.Settings.Count; i++)
                {
					rbSettings = _rbsConfig.Settings[i];
                    if (rbSettings.Levels.Contains(level))
                    {
						Settings = rbSettings;
						val = true;
						break;
					}
				}
				if (!val)
                {
					Settings = _rbsConfig.Settings[0];
					Settings.Levels = Settings.Levels.Concat(new[] { level }).ToArray();
				}
				
				StartTime = _unixSeconds;
				DespawnTime = StartTime + despawnTime.Subtract(DateTime.Now).TotalSeconds;
				LootRemain = lootRemain;
				IsPvP = isPVP;
				
				var barSettings = Settings.Bar;
				StatusBar = new Dictionary<int, object>
                {
                    { 0, RaidID },
                    { 1, Instance.Name },
                    { 2, "Default" },
                    { 3, "RaidableBases" },
                    { 4, barSettings.Order },
                    { 5, barSettings.Height },
                    { 6, barSettings.Main_Color },
					{ 11, barSettings.Image_IsRawImage },
                    { 12, barSettings.Image_Color },
					{ 16, barSettings.Text_Size },
                    { 17, barSettings.Text_Color },
                    { 18, barSettings.Text_Font },
                    { 23, barSettings.SubText_Size },
                    { 24, barSettings.SubText_Color },
                    { 25, barSettings.SubText_Font }
                };
				
				if (barSettings.Main_Color.StartsWith("#"))
					StatusBar.Add(-6, barSettings.Main_Transparency);
				if (!string.IsNullOrWhiteSpace(barSettings.Main_Material))
					StatusBar.Add(7, barSettings.Main_Material);
				if (barSettings.Image_Color.StartsWith("#"))
                    StatusBar.Add(-12, barSettings.Image_Transparency);
				if (barSettings.Image_Outline_Enabled)
                {
					StatusBar.Add(13, barSettings.Image_Outline_Color);
					if (barSettings.Image_Outline_Color.StartsWith("#"))
						StatusBar.Add(-13, barSettings.Image_Outline_Transparency);
					StatusBar.Add(14, barSettings.Image_Outline_Distance);
                }
                if (barSettings.Text_Outline_Enabled)
                {
					StatusBar.Add(20, barSettings.Text_Outline_Color);
					if (barSettings.Text_Outline_Color.StartsWith("#"))
						StatusBar.Add(-20, barSettings.Text_Outline_Transparency);
					StatusBar.Add(21, barSettings.Text_Outline_Distance);
                }
                if (barSettings.SubText_Outline_Enabled)
                {
					StatusBar.Add(26, barSettings.SubText_Outline_Color);
					if (barSettings.SubText_Outline_Color.StartsWith("#"))
						StatusBar.Add(-26, barSettings.SubText_Outline_Transparency);
					StatusBar.Add(27, barSettings.SubText_Outline_Distance);
                }
				
				UpdateBars();

                if (intruders != null)
                {
                    foreach (var player in intruders)
                        OnPlayerEnter(player);
                }
            }
			
			public void OnPlayerEnter(BasePlayer player)
			{
				if (IsPvP || IsOwnerOrFriend(player))
                {
					ParticipantsList[player.userID] = player;
					Instance.SendRaidableBasesLootBar(player, this);
					if (IsPvP)
						Instance.OnPlayerEnterPVP(player, RaidID);
					else
						Instance.SendCounterBar(player, this);
				}
                else
				{
					PlayersList[player.userID] = player;
					Instance.SendRBStrangerBar(player, this);
                }
			}
			
			public void OnPlayerExit(BasePlayer player)
            {
				Instance.DestroyBar(player.userID, RaidID);
				if (ParticipantsList.Remove(player.userID))
					Instance.DestroyBar(player.userID, RBLootUI);
				else
					PlayersList.Remove(player.userID);
				
				if (IsPvP && _pvpPlayers.TryGetValue(player.userID, out var playerPvP))
					Instance.OnPlayerExitPVP(player, RaidID, Settings.PvPDelay);
			}
			
			public void OnLootUpdated(int lootRemain)
            {
				if (LootRemain == lootRemain) return;
				LootRemain = lootRemain;
				foreach (var player in ParticipantsList.Values)
					Instance.SendRaidableBasesLootBar(player, this);
			}
			
			public void DespawnTimeUpdated(DateTime despawnTime)
            {
				var newStamp = _unixSeconds + despawnTime.Subtract(DateTime.Now).TotalSeconds;
				if (DespawnTime == newStamp) return;
				DespawnTime = newStamp;
				if (IsPvP) return;
				foreach (var player in ParticipantsList.Values)
					Instance.RaidableBaseTimeUpdatedBar(player, this);
			}
			
			public bool IsPlayerInside(ulong userID) => ParticipantsList.ContainsKey(userID) || PlayersList.ContainsKey(userID);
			
			public void SetNewOwner(ulong userID)
            {
				if (IsPvP) return;
				OwnerID = userID;
                var playersList = ParticipantsList.Values.ToList();
                playersList.AddRange(PlayersList.Values);
                foreach (var rPlayer in playersList)
                {
                    OnPlayerExit(rPlayer);
                    OnPlayerEnter(rPlayer);
                }
			}
			
			public void OnTeamUpdated(BasePlayer player)
            {
				if (IsPvP || !OwnerID.IsSteamId()) return;
				if (OwnerID == player.userID)
                {
                    var playersList = ParticipantsList.Values.ToList();
                    playersList.AddRange(PlayersList.Values);
                    foreach (var rPlayer in playersList)
                    {
						OnPlayerExit(rPlayer);
						OnPlayerEnter(rPlayer);
                    }
				}
                else
				{
					OnPlayerExit(player);
					OnPlayerEnter(player);
				}
            }
			
			public void OnFriendUpdated(BasePlayer player, BasePlayer friend)
            {
				if (IsPvP || !OwnerID.IsSteamId()) return;
				if (OwnerID == player.userID)
				{
					OnPlayerExit(friend);
					OnPlayerEnter(friend);
				}
				else if (OwnerID == friend.userID)
				{
					OnPlayerExit(player);
					OnPlayerEnter(player);
				}
			}
			
			public bool IsOwnerOrFriend(BasePlayer looter)
            {
                if (looter.userID == OwnerID || (looter.Team != null && looter.Team.members.Contains(OwnerID)) || Instance.IsFriend(looter.UserIDString, OwnerIDString))
                    return true;
                return false;
            }
			
			public bool CanInteractWithRaid(ulong userID) => OwnerID == userID || ParticipantsList.ContainsKey(userID);
			
			public void UpdateBars()
            {
				var barSettings = Settings.Bar;
                StatusBar.Remove(10);
                StatusBar.Remove(9);
                StatusBar.Remove(8);
                if (!string.IsNullOrWhiteSpace(barSettings.Image_Sprite))
                    StatusBar.Add(10, barSettings.Image_Sprite);
                else if (!string.IsNullOrWhiteSpace(barSettings.Image_Local))
                    StatusBar.Add(9, barSettings.Image_Local);
                else
                    StatusBar.Add(8, _imgLibIsLoaded && barSettings.Image_Url.StartsWithAny(Instance.HttpScheme) ? Settings.ImgLibId : barSettings.Image_Url);
				
				var progressBar = Settings.ProgressBar;
                StatusProgressBar = new Dictionary<int, object>(StatusBar)
                {
                    { 32, progressBar.Progress_Reverse },
                    { 33, progressBar.Progress_Color },
                    { -33, progressBar.Progress_Transparency },
                    { 34, progressBar.Progress_OffsetMin },
                    { 35, progressBar.Progress_OffsetMax }
                };
                StatusProgressBar[2] = "TimeProgressCounter";
                StatusProgressBar[6] = progressBar.Main_Color;

                if (progressBar.Main_Color.StartsWith("#"))
                    StatusProgressBar[-6] = progressBar.Main_Transparency;
                else
                    StatusProgressBar.Remove(-6);
			}
			
			public void Destroy()
			{
				foreach (var player in ParticipantsList.Values)
					Instance.DestroyBar(player.userID, RaidID);
				ParticipantsList.Clear();
				Pool.FreeUnmanaged(ref ParticipantsList);
				foreach (var player in PlayersList.Values)
					Instance.DestroyBar(player.userID, RaidID);
				PlayersList.Clear();
				Pool.FreeUnmanaged(ref PlayersList);
				Instance._rbList.Remove(RaidID);
			}
		}
		
		public class EventData
		{
			public ulong ID { get; }
			public string TypeKey { get; } = string.Empty;
			public BasePlayer Owner { get; private set; }
			public ulong OwnerID { get; private set; }
			public string OwnerIDString { get; private set; } = string.Empty;
			public string OwnerName { get { return Owner != null ? Owner.displayName : OwnerIDString; } }
			public EventSettings Settings { get; }
			
			public double EndTime;
			public int DeathCounter { get; private set; }
			
			private BaseCombatEntity _entity;
			private bool _isSmoked = false;
			
			public EventData() {}
			public EventData(BaseCombatEntity entity)
            {
				if (!entity.IsValid() || entity.skinID != 0uL) return;
				
				BasePlayer target = null;
				if (entity is PatrolHelicopter patrol)
				{
					Settings = _vanillaEventsConfig.PatrolHelicopter;
					TypeKey = "MsgEventPatrolHelicopter";
					target = patrol.myAI.leftGun._target as BasePlayer ?? patrol.myAI.rightGun._target as BasePlayer;
				}
				else if (entity is BradleyAPC bradley)
                {
					Settings = _vanillaEventsConfig.BradleyAPC;
					TypeKey = "MsgEventBradleyAPC";
					target = bradley.mainGunTarget as BasePlayer;
				}
				else
					return;
				
				ID = entity.net.ID.Value;
				_entity = entity;
				_eventsList[ID] = this;
				if (target != null && target.userID.IsSteamId())
					SetNewOwner(target);
			}
			
			public void SetNewOwner(BasePlayer owner = null)
            {
				if (_entity == null)
                {
					_eventsList.Remove(ID);
					return;
				}
				
				DeathCounter = 0;
				if (owner != null)
                {
					Owner = owner;
					OwnerID = owner.userID;
					OwnerIDString = owner.UserIDString;
					if (Settings.TimeLimit > 0d)
						EndTime = _unixSeconds + Settings.TimeLimit;
					SendMessageText(Owner.IPlayer, string.Format(Instance.lang.GetMessage("MsgEventNewLooter", Instance, OwnerIDString), Instance.lang.GetMessage(TypeKey, Instance, OwnerIDString), Settings.DeathLimit, Math.Round(Settings.TimeLimit / 60d, 2)), false);
				}
				else
				{
					Owner = null;
					OwnerID = 0uL;
					OwnerIDString = string.Empty;
					EndTime = 0d;
				}
				
				if (_entity is PatrolHelicopter patrol)
                {
                    patrol.myAI.ClearAimTarget();
                    patrol.myAI.leftGun.ClearTarget();
                    patrol.myAI.rightGun.ClearTarget();
				}
                else if (_entity is BradleyAPC bradley)
                {
					bradley.targetList.Clear();
					bradley.mainGunTarget = null;
				}
				
				if (Owner != null)
					_entity.Hurt(new HitInfo(Owner, _entity, DamageType.Blunt, 1f));
			}
			
			public void OnLooterDeath(BasePlayer player)
            {
				DeathCounter++;
				if (Settings.DeathLimit < 1) return;
				
				if (DeathCounter < Settings.DeathLimit)
                {
					if (Owner != null && Owner.IsConnected)
						SendMessageText(Owner.IPlayer, string.Format(Instance.lang.GetMessage("MsgEventDeath", Instance, OwnerIDString), Instance.lang.GetMessage(TypeKey, Instance, OwnerIDString), player.displayName, DeathCounter, Settings.DeathLimit));
				}
				else
				{
					if (Owner != null && Owner.IsConnected)
						SendMessageText(Owner.IPlayer, string.Format(Instance.lang.GetMessage("MsgEventDeathLimit", Instance, OwnerIDString), Instance.lang.GetMessage(TypeKey, Instance, OwnerIDString), player.displayName));
                    SetNewOwner(null);
                }
			}
			
			public void OnLootTimeEnded()
            {
				if (Owner != null && Owner.IsConnected)
					SendMessageText(Owner.IPlayer, string.Format(Instance.lang.GetMessage("MsgEventTimeLimit", Instance, OwnerIDString), Instance.lang.GetMessage(TypeKey, Instance, OwnerIDString)));
				SetNewOwner(null);
			}
			
			public bool CanBeAttackedBy(ulong userID) => OwnerID == userID || (Owner != null && Owner.Team != null && Owner.Team.members.Contains(userID)) || Instance.IsFriend(userID, OwnerID);
			public bool CanBeAttackedBy(BasePlayer player) => OwnerID == player.userID || (player.Team != null && player.Team.members.Contains(OwnerID)) || Instance.IsFriend(player.UserIDString, OwnerIDString);
			
			public object CanBeTargeted(BasePlayer player)
			{
				if (OwnerID == player.userID || (player.Team != null && player.Team.members.Contains(OwnerID)) || Instance.IsFriend(player.UserIDString, OwnerIDString))
					return null;
				return false;
			}
			
			public void LockedCrateSpawned(LockedByEntCrate lockedCrate)
            {
                lockedCrate.OwnerID = OwnerID;
                var lockedEntity = lockedCrate.lockingEnt;
                if (lockedEntity == null) return;
                if (Settings.StopFire)
					lockedEntity.Kill();
				else if (!_isSmoked)
                {
                    Effect.server.Run("assets/bundled/prefabs/fx/smoke_signal_full.prefab", lockedEntity, 0, Vector3.zero, Vector3.zero, null, false);
                    _isSmoked = true;
                }
            }

            public void DebrisSpawned(List<ServerGib> serverGibs)
            {
                if (Settings.StopFire)
                {
                    foreach (var serverGib in serverGibs)
                    {
						serverGib.OwnerID = OwnerID;
						if (serverGib is HelicopterDebris debris)
							debris.tooHotUntil = 0f;
					}
                }
                else
                {
					foreach (var serverGib in serverGibs)
						serverGib.OwnerID = OwnerID;
				}
            }
			
			public void OnParentDestroy(Vector3 pos)
            {
				if (Owner != null && Owner.IsConnected)
                {
					SendMessageText(Owner, string.Format(Instance.lang.GetMessage("MsgEventComplete", Instance, OwnerIDString), Instance.lang.GetMessage(TypeKey, Instance, OwnerIDString), pos), false);
					Owner.AddPingAtLocation(BasePlayer.PingType.Loot, pos, 15f, default);
				}
				if (Settings.StopFire)
				{
					var fbList = Pool.Get<List<FireBall>>();
					Vis.Entities(pos, 5f, fbList);
					foreach (var fireball in fbList)
						fireball.Extinguish();
					Pool.FreeUnmanaged(ref fbList);
				}
				_eventsList.Remove(ID);
			}
		}
		object OnEntityTakeDamage(VehicleModuleTaxi module, HitInfo info) => HandleDamageToVehicle(module.VehicleParent(), info);
		void OnEntitySpawned(AttackHelicopter attackCopter) => HandleVehicleSpawn(attackCopter);
		private object HandlePortalUse(BasePlayer player, BasePortal portal)
        {
			if (portal.skinID == 0uL && portal.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, portal.net.ID.Value))
            {
                object result = CanInteractSharedEntity(player, portal) == null || IsFriend(player.UserIDString, portal.OwnerID.ToString()) ? null : false;
				if (result != null)
                {
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                    return true;
                }
                return null;
			}
            return null;
		}
		void OnEntitySpawned(BatteringRam batteringRam) => HandleVehicleSpawn(batteringRam);
		private static HashSet<ulong> _pvpEntities;
		
		object OnFrankensteinPetWake(FrankensteinTable table, BasePlayer player) => HandleFrankensteinTableUse(table, player);
		private void Command_AdminShare(IPlayer player, string[] args)
        {
            int index = Array.FindIndex(_cmdKeysAdminShare, key => key.Equals(args[0], StringComparison.OrdinalIgnoreCase));
            if (index < 0)
            {
                player.Reply(lang.GetMessage("CmdAdminShare", this, player.Id));
                return;
            }
			
			if (index == 5)
			{
				
				if (string.IsNullOrWhiteSpace(args[1]))
                {
					foreach (var playerData in _playersList.Values)
                        playerData.SharedEntities.Clear();
                    int total = _sharedEntities.Count();
                    _sharedEntities.Clear();
                    SaveData(_dataPlayersPath, _playersList);
                    SendMessageText(player, string.Format(lang.GetMessage("CmdAdminShareClear", this, player.Id), total), false);
                }
				else if (TryGetPlayer(player, args[1], out var tPlayer))
				{
					if (!TryGetPlayerData(tPlayer.Id, out var playerData))
						SendMessageText(player, string.Format(lang.GetMessage("CmdPlayerNotFound", this, player.Id), args[1]));
					else
					{
						int total = playerData.SharedEntities.Count();
                        foreach (var netID in playerData.SharedEntities.Keys)
                            _sharedEntities.Remove(netID);
                        playerData.SharedEntities.Clear();
                        SendMessageText(player, string.Format(lang.GetMessage("CmdAdminShareClearTarget", this, player.Id), total, playerData.UserID), false);
					}
				}
            }
			else if (!TryGetEntity(index == 1 || index == 2 ? args[2] : args[1], out var entity, player.Object as BasePlayer))
				SendMessageText(player, lang.GetMessage("CmdEntityNotFound", this, player.Id));
			else if (!entity.OwnerID.IsSteamId())
				SendMessageText(player, string.Format(lang.GetMessage("CmdEntityNotOwned", this, player.Id), entity.net.ID.Value));
			else
			{
				GetOrCreatePlayerData(player.Id, out var playerData);
				ulong netID = entity.net.ID.Value;
                if (index == 4)
                {
					
					playerData.SharedEntities.Remove(netID);
					_sharedEntities.Remove(netID);
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminShareDelete", this, player.Id), netID, playerData.UserID), false);
				}
                else
                {
					GetOrCreateSharedEntity(playerData, netID, out var sharedEntity);
					if (index == 0)
                    {
                        
                        player.Reply(string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdAdminShareStatusEveryOne" : "CmdAdminShareStatusDefault", this, player.Id), netID, playerData.UserID, string.Join(", ", sharedEntity.PlayersList)));
                    }
                    else if (index == 3)
                    {
                        
                        sharedEntity.ForEveryOne = !sharedEntity.ForEveryOne;
                        SendMessageText(player, string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdAdminShareToggleEveryOne" : "CmdAdminShareToggleDefault", this, player.Id), netID, playerData.UserID), false);
                    }
                    else if (TryGetPlayer(player, args[1], out var tPlayer, false))
					{
						ulong.TryParse(tPlayer.Id, out var tUserID);
                        if (index == 1)
                        {
                            
                            sharedEntity.PlayersList.Add(tUserID);
                            SendMessageText(player, string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdAdminShareDenied" : "CmdAdminShareGranted", this, player.Id), netID, playerData.UserID, tPlayer.Name), false);
                        }
                        else if (index == 2)
                        {
                            
                            sharedEntity.PlayersList.Remove(tUserID);
                            SendMessageText(player, string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdAdminShareGranted" : "CmdAdminShareDenied", this, player.Id), netID, playerData.UserID, tPlayer.Name), false);
                        }
                    }
                }
            }
        }
		
		public static void ModDroppedContainer2(ItemContainer __instance, ref DroppedItemContainer __result)
        {
			if (!__result.IsValid()) return;
			var entity = __instance?.entityOwner;
			if (entity.IsValid())
				__result.OwnerID = entity.OwnerID;
		}
		
		void OnTeamAcceptInvite(RelationshipManager.PlayerTeam team, BasePlayer player) => OnTeamUpdated(player);
		object CanLootEntity(BasePlayer player, ShopFront shopFront) => null;
		
		object OnOvenToggle(BaseOven oven, BasePlayer player) => HandleContainerToggle(player, oven);
		void OnEntityKill(BaseSubmarine submarine) => HandleVehicleKill(submarine);
		
		void DeletePVPMapMarker(string zoneID)
		{
			if (_pvpMarkers.TryGetValue(zoneID, out var pvpMarkers))
			{
				pvpMarkers.Destroy();
				_pvpMarkers.Remove(zoneID);
			}
		}
		private Timer _updatesTimer;
		
		void OnEntityExitZone(string zoneID, BaseEntity entity)
        {
			if (_dynamicPvPs.Contains(zoneID))
				OnEntityExitPVP(entity);
		}
		
		object OnCreateDynamicPVP(string eventName, BaseEntity entity)
        {
            switch (eventName)
            {
                case "Bradley":
                case "Helicopter":
                    if (_eventsList.ContainsKey(entity.net.ID.Value))
                        return false;
                    break;
                case "SupplyDrop":
                case "SupplySignal":
                    entity.OwnerID = 0uL;
                    break;
                default:
                    break;
            }
            return null;
        }
		
		void OnRaidableDespawnUpdate(Vector3 pos, int level, bool allowPVP, ulong ownerID, string baseName, float radius, int lootRemain, DateTime despawnTime)
        {
			if (_rbList.TryGetValue(pos.ToString(), out var rbData))
				rbData.DespawnTimeUpdated(despawnTime);
		}
		
		public class EventSettings
        {
			public bool IsEnabled { get; set; } = true;
			
			[JsonProperty(PropertyName = "Time in seconds (1-15) given to respond for purchasing this event. Note: This is shown to everyone who deals damage, and the first person to buy it will claim it")]
            public float OfferTime { get; set; } = 5f;
			
			[JsonProperty(PropertyName = "Is it worth removing fire from crates?")]
            public bool StopFire { get; set; } = true;
			
			[JsonProperty(PropertyName = "The price to claim the event. A value of 0 means it's free")]
			public double Price { get; set; } = 50d;
			
			[JsonProperty(PropertyName = "The number of deaths after which the event becomes public. A value of 0 disables the limit")]
			public int DeathLimit { get; set; } = 5;
			
			[JsonProperty(PropertyName = "The time in seconds for which the event is locked to the player. A value of 0 disables the time limit")]
            public float TimeLimit { get; set; } = 1800f;
		}
		object CanLootEntity(BasePlayer player, ContainerIOEntity container) => CanLootByOwnerID(player, container);
		
        
        
        






        
        

        
        


        
        


        
        


        
        
        
        
        


        
        
        


        
        


        
        


        
        
        


        
        
        
        


        
        
        

        

        





        
        


        
        


        
        
        


        
        
        


        
        
        


        
        


        
        


        
        


        
        

        

        


        
        
        


        
        


        
        
        
        
        
        
        


        [PluginReference]
		private Plugin ImageLibrary, ZoneManager, Economics, RaidableBases, RandomRaids, Friends, DynamicPVP, AdvancedStatus, MonumentsWatcher, ServerPanels;
		
		public static IEnumerable<CodeInstruction> ModSpawnSwarm(IEnumerable<CodeInstruction> instructions, ILGenerator generator)
        {
            var result = new List<CodeInstruction>(instructions);
            CodeInstruction code;
            for (int i = 0; i < result.Count; i++)
            {
                code = result[i];
                if (code.opcode == OpCodes.Callvirt && code.operand is MethodInfo method && method.Name == "SetHasCameFromAHive")
                {
					result.Insert(i + 1, new CodeInstruction(OpCodes.Ldloc_0));
					result.Insert(i + 2, new CodeInstruction(OpCodes.Ldarg_0));
                    result.Insert(i + 3, new CodeInstruction(OpCodes.Call, AccessTools.PropertyGetter(typeof(BaseEntity), "OwnerID")));
                    result.Insert(i + 4, new CodeInstruction(OpCodes.Call, AccessTools.PropertySetter(typeof(BaseEntity), "OwnerID")));
					break;
                }
            }
            return result;
        }
		
		object OnHorseLead(RidableHorse horse, BasePlayer player) => player.Uyda() ? null : HandleVehicleInteractionM(player, horse, "MsgVehicleCantLead");
		
		private string _rbsPath = string.Empty, _rbsUiOffer = string.Empty;
		private void Command_RealPVE(IPlayer player, string command, string[] args)
        {
			int index = args != null && args.Length > 0 ? Array.FindIndex(_cmdKeysMain, key => key.Equals(args[0], StringComparison.OrdinalIgnoreCase)) : -1;
			if (index < 0)
            {
                player.Reply(lang.GetMessage("CmdMain", this, player.Id));
                return;
            }
			if (player.Object is not BasePlayer bPlayer || bPlayer == null)
			{
				player.Reply("This command is only available to players!");
				return;
			}
			
			GetOrCreatePlayerData(player.Id, out var playerData);
			if (index == 0)
            {
				
				playerData.AutoBuy = !playerData.AutoBuy;
                if (playerData.AutoBuy)
                {
                    _autoBuyPlayers.Add(bPlayer.userID);
                    SendMessageText(player, lang.GetMessage("CmdMainAutoBuyEnabled", this, player.Id));
                }
                else
                {
                    _autoBuyPlayers.Remove(bPlayer.userID);
                    SendMessageText(player, lang.GetMessage("CmdMainAutoBuyDisabled", this, player.Id), false);
                }
            }
            else if (index == 1)
            {
				
				playerData.AllowPickup = !playerData.AllowPickup;
                if (playerData.AllowPickup)
                {
                    _pickupPlayers.Add(bPlayer.userID);
                    SendMessageText(player, lang.GetMessage("CmdMainPickupEnabled", this, player.Id));
                }
                else
                {
                    _pickupPlayers.Remove(bPlayer.userID);
                    SendMessageText(player, lang.GetMessage("CmdMainPickupDisabled", this, player.Id), false);
                }
            }
            else if (index == 2)
				Command_Share(bPlayer, args.Skip(1).Concat(Enumerable.Repeat(string.Empty, Math.Max(0, 6 - args.Length))).ToArray(), playerData);
			else if (index == 3)
            {
				
				var team = bPlayer.Team;
                if (team == null || !_teamsList.TryGetValue(team.teamID, out var teamData))
                    SendMessageText(player, lang.GetMessage("CmdMainTeamNotFound", this, player.Id));
                else if (team.teamLeader != bPlayer.userID)
                    SendMessageText(player, lang.GetMessage("CmdMainTeamNotLeader", this, player.Id));
                else if (args.Length > 1 && args[1].Equals("ff", StringComparison.OrdinalIgnoreCase))
                {
                    string text;
                    bool isWarning = true;
                    teamData.FriendlyFire = !teamData.FriendlyFire;
                    if (teamData.FriendlyFire)
                        text = string.Format(lang.GetMessage("CmdMainTeamFireEnabled", this, player.Id), bPlayer.displayName);
                    else
                    {
                        text = string.Format(lang.GetMessage("CmdMainTeamFireDisabled", this, player.Id), bPlayer.displayName);
                        isWarning = false;
                    }
                    foreach (var mateID in team.members)
                    {
                        if (RelationshipManager.FindByID(mateID) is BasePlayer mate && mate != null && mate.IsConnected)
                            SendMessageText(player, text, isWarning);
                    }
                }
                else
                    player.Reply(lang.GetMessage("CmdMainTeam", this, player.Id));
            }
            else if (index == 4)
            {
                
				int index2 = args.Length > 1 ? Array.FindIndex(_cmdKeysMainVehicle, key => key.Equals(args[1], StringComparison.OrdinalIgnoreCase)) : -1;
                if (index2 < 0)
                {
					player.Reply(lang.GetMessage("CmdMainVehicle", this, player.Id));
					return;
				}
				
				BaseEntity targetVeh;
                if (index2 == 0)
                {
					
					ulong[] vehList = _vehiclesList.Values.Where(vehicleData => vehicleData.OwnerID == bPlayer.userID).Select(vehicleData => vehicleData.ID).ToArray();
                    if (vehList.Any())
                        player.Reply(string.Format(lang.GetMessage("CmdMainVehicleList", this, player.Id), string.Join(", ", vehList)));
                    else
                        SendMessageText(player, lang.GetMessage("CmdMainVehicleListEmpty", this, player.Id));
                }
                else if (index2 == 3)
                {
					
					int counter = 0;
                    foreach (var vehicleData in _vehiclesList.Values)
                    {
                        if (vehicleData.OwnerID != bPlayer.userID) continue;
                        targetVeh = BaseNetworkable.serverEntities.Find(new NetworkableId(vehicleData.ID)) as BaseEntity;
                        if (vehicleData.RemoveOwner(bPlayer, false) && targetVeh != null)
                        {
                            if (targetVeh is ModularCar car && car != null)
                                car.CarLock.RemoveLock();
                            else if (targetVeh.children.OfType<VehiclePrivilege>().FirstOrDefault() is VehiclePrivilege privilege && privilege != null)
                            {
                                privilege.authorizedPlayers.Clear();
                                privilege.UpdateMaxAuthCapacity();
                                privilege.SendNetworkUpdate();
                            }
                            counter++;
                        }
                    }
                    SendMessageText(player, string.Format(lang.GetMessage("CmdMainVehicleClear", this, player.Id), counter), false);
                }
                else if (!TryGetEntity(args.Length > 2 ? args[2] : string.Empty, out targetVeh, bPlayer) || !_vehiclesList.TryGetValue(targetVeh.net.ID.Value, out var vehicleData))
                    SendMessageText(player, lang.GetMessage("CmdMainVehicleNotFound", this, player.Id));
                else if (vehicleData.OwnerID != bPlayer.userID)
                    SendMessageText(player, lang.GetMessage("MsgVehicleNotOwner", this, player.Id));
                else if (index2 == 1)
                {
					
					if (_config.VehiclesMarkerTime > 0f)
                        bPlayer.AddPingAtLocation(BasePlayer.PingType.GoTo, targetVeh.transform.position + targetVeh.transform.up * 1f, _config.VehiclesMarkerTime, targetVeh.net.ID);
                    SendMessageText(player, string.Format(lang.GetMessage("CmdMainVehicleFind", this, player.Id), vehicleData.Name, targetVeh.transform.position.GetGrid()), false);
                }
                else if (index2 == 2)
                {
					
					if (vehicleData.RemoveOwner(bPlayer, false))
                    {
                        if (targetVeh is ModularCar car && car != null)
                            car.CarLock.RemoveLock();
                        else if (targetVeh.children.OfType<VehiclePrivilege>().FirstOrDefault() is VehiclePrivilege privilege && privilege != null)
                        {
                            privilege.authorizedPlayers.Clear();
                            privilege.UpdateMaxAuthCapacity();
                            privilege.SendNetworkUpdate();
                        }
                        bPlayer.RunEffect("assets/prefabs/locks/keypad/effects/lock.code.unlock.prefab");
                    }
                }
			}
		}
		private bool CanInteractMannequin(Mannequin mannequin, BasePlayer player)
		{
			if (_unrestrictedLooters.Contains(player.userID) || IsEntityInPvP(player.userID, mannequin.OwnerID))
                return true;
			if (mannequin.OwnerID.IsSteamId())
            {
				if (CanInteractSharedEntity(player, mannequin) == null)
					return true;
            }
			else if (_monumentsList.TryGetValue(GetEntityMonument(mannequin), out var monumentData))
            {
                if (monumentData.CanLoot(player) == null)
					return true;
			}
            else if (TryGetRaidBase(mannequin.transform.position, out var rbData))
            {
                if (rbData.CanInteractWithRaid(player.userID))
					return true;
            }
			else if (player.CanBuild(cached: true))
                return true;
			
			SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
			return false;
		}
		
		object CanLootEntity(BasePlayer player, Mannequin mannequin) => CanLootStorage(player, mannequin);
		
		public class PlayerData
		{
			[JsonIgnore] public ulong UserID { get; set; }
			public bool AdminLoot { get; set; }
			public bool AutoBuy { get; set; }
			public bool AllowPickup { get; set; }
			public Dictionary<ulong, SharedEntity> SharedEntities { get; set; } = new Dictionary<ulong, SharedEntity>();
			public List<BaseMission.MissionInstance> Missions { get; set; } = new List<BaseMission.MissionInstance>();
			
			public PlayerData() {}
			public PlayerData(ulong userID)
            {
				UserID = userID;
			}
		}
        private uint _bedMissionId = 0u;
		
		
		
		
        
                void OnExplosiveThrown(BasePlayer player, BaseEntity entity, ThrownWeapon item) => entity.OwnerID = player.userID;
		
		private bool CancelDamageToEntity(BaseCombatEntity entity, HitInfo info)
        {
            if (info == null || info.damageTypes.GetMajorityDamageType() == DamageType.Decay || !entity.IsValid()) return false;
            switch (TryGetAttacker(info, out var initiator))
            {
                case AttackerIndex.PatrolHelicopter:
                    if (entity.OwnerID.IsSteamId())
                    {
                        if (_config.PreventPatrolToDeployables && (entity is BuildingBlock || IsDeployableEntity(entity)))
							return true;
						
						if (initiator.IsValid() && _eventsList.TryGetValue(initiator.net.ID.Value, out var eventPatrol) && !eventPatrol.CanBeAttackedBy(entity.OwnerID))
							return true;
					}
                    break;
                case AttackerIndex.BradleyAPC:
                    if (entity.OwnerID.IsSteamId() && _eventsList.TryGetValue(initiator.net.ID.Value, out var eventBradley) && !eventBradley.CanBeAttackedBy(entity.OwnerID))
						return true;
					break;
                case AttackerIndex.BasePlayer:
                    var attacker = (BasePlayer)initiator;
					if (!attacker.userID.IsSteamId())
					{
						if (entity.OwnerID.IsSteamId())
						{
							if (attacker.skinID == _rrPluginID)
                            {
                                if (_config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(attacker.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(entity.OwnerID))
                                    return true;
                            }
						}
					}
					else if (!IsEntityInPvP(attacker.userID, entity.net.ID.Value))
                    {
						if (entity.OwnerID.IsSteamId())
                        {
							if (!UrishMumkin(attacker, entity.OwnerID))
								return true;
						}
                        else if (TryGetRaidBase(entity.transform.position, out var rbData))
						{
                            if (!rbData.CanInteractWithRaid(attacker.userID))
                                return true;
                        }
                        else if (_monumentsList.TryGetValue(GetEntityMonument(entity), out var monumentData))
                        {
                            if (monumentData.CanLoot(attacker) != null)
                                return true;
                        }
                    }
					break;
                case AttackerIndex.PlayerOwnerMissing:
					return true;
			}
            return false;
		}
		
		bool OnEntityExitPVP(BaseEntity entity, string zoneID = "", float delay = 0f)
        {
			if (entity.IsValid() && _pvpEntities.Remove(entity.net.ID.Value))
            {
				if (entity is AutoTurret turret)
					UpdateTurretTrigger(turret);
				else if (entity is FlameTurret flameTurret)
					UpdateTurretTrigger(flameTurret);
				else if (entity is GunTrap gunTrap)
					UpdateTurretTrigger(gunTrap);
				else if (entity is DecayEntity decayEntity)
					ApplyDemolishable(decayEntity);
				return true;
			}
			return false;
		}
		
		void OnLootEntityEnd(BasePlayer player, StorageContainer container)
		{
			if (container.panelName == "fuelsmall")
				DestroyVehiclePanels(player);
		}
		object CanDestroyLock(BasePlayer player, ModularCar modularCar, BaseVehicleModule carModule) => false;
		
		object OnSamSiteTarget(SamSite samSite, BaseEntity targetEnt)
        {
			if (samSite.OwnerID == 0uL && (!_rbsConfig.IsEnabled || samSite.skinID != _rbPluginID)) return null;
			if (_pvpEntities.Contains(samSite.net.ID.Value) && _pvpEntities.Contains(targetEnt.net.ID.Value)) return null;
			
			object result = null;
			var mountedPlayers = Pool.Get<List<BasePlayer>>();
			var targetPlayer = targetEnt.ToPlayer();
			if (targetPlayer.IsValid())
				mountedPlayers.Add(targetPlayer);
			else
			{
				mountedPlayers.AddRange(targetEnt.GetComponentsInChildren<BasePlayer>());
				if (targetEnt is BaseHelicopter heli)
				{
					foreach (var mountPoint in heli.allMountPoints)
					{
						if (mountPoint != null && mountPoint.mountable != null && mountPoint.mountable.GetMounted() is BasePlayer passenger)
							mountedPlayers.Add(passenger);
					}
				}
			}
			
			int totalPassengers = mountedPlayers.Count;
			if (totalPassengers > 0)
			{
				BasePlayer passenger;
				if (samSite.OwnerID.IsSteamId())
                {
					for (int i = 0; i < totalPassengers; i++)
					{
						passenger = mountedPlayers[i];
						if (passenger.userID.IsSteamId())
                        {
                            result = true;
                            break;
                        }
                    }
				}
				else if (samSite.skinID == _rbPluginID)
                {
					if (TryGetRaidBase(samSite.transform.position, out var rbData) && rbData != null)
                    {
						for (int i = 0; i < totalPassengers; i++)
                        {
							passenger = mountedPlayers[i];
                            if (passenger.userID.IsSteamId() && !rbData.CanInteractWithRaid(passenger.userID))
                            {
                                result = true;
                                break;
                            }
                        }
					}
				}
			}
            Pool.FreeUnmanaged(ref mountedPlayers);
            return result;
        }
		private static void SaveData<T>(string path, T obj) => Interface.Oxide.DataFileSystem.WriteObject(path, obj);
		void OnEntitySpawned(Tugboat tugBoat) => HandleVehicleSpawn(tugBoat);
		
		public class MonumentData
		{
			public string MonumentID { get; private set; }
			public Vector3 MonumentPos { get; set; }
			public string BarID { get; private set; }
			public string MonumentNameEng { get; set; }
			
			private BasePlayer _owner;
			public BasePlayer Owner
            {
				get => _owner;
				private set
				{
					_owner = value;
					if (_owner == null)
                    {
						OwnerID = 0uL;
						OwnerIDString = string.Empty;
						OwnerName = string.Empty;
						IsOwned = false;
						LootStartTime = 0d;
						LootEndTime = 0d;
					}
					else
					{
						OwnerID = _owner.userID;
						OwnerIDString = _owner.UserIDString;
						OwnerName = _owner.displayName;
						IsOwned = true;
					}
				}
			}
			public ulong OwnerID { get; private set; }
			public string OwnerIDString { get; private set; } = string.Empty;
			public string OwnerName { get; private set; } = string.Empty;
			public bool IsOwned { get; private set; }
			
			public MonumentSettings Settings { get; private set; }
			public Dictionary<int, object> StatusBar { get; private set; }
			public Dictionary<int, object> StatusProgressBar { get; private set; }
			
			public List<BasePlayer> PlayersQueue = Pool.Get<List<BasePlayer>>();
			public HashSet<BasePlayer> FriendsList = Pool.Get<HashSet<BasePlayer>>();
			
			private double LootStartTime { get; set; }
			public double LootEndTime { get; private set; }
			private double BroadcastNext { get; set; }
			private Timer MonumentTimer = null;
			
			public bool IsMoveable { get; private set; }
			public bool IsPvP { get; private set; }
			
			public static MapCircleStyle MapCircle_Free;
			public static MapCircleStyle MapCircle_Owned;
			public static MapCircleStyle MapCircle_PvP;
			private VendingMachineMapMarker mainMapMarker;
			private MapMarkerGenericRadius circleMapMarker;
			public double mapMarkerNextTime = 0d;
			
			public MonumentData(string monumentID, MonumentSettings monumentSettings, bool isMoveable = false)
			{
				MonumentID = monumentID;
				Settings = monumentSettings;
                BarID = $"{StatusBarID}{MonumentID}";
				MonumentNameEng = GetMonumentName();
				MonumentPos = Instance.GetMonumentPosition(MonumentID);
				IsMoveable = isMoveable;
				IsPvP = Settings.IsPvP;
				VerifyMapMarkers();
				
				var barSettings = Settings.Bar;
				StatusBar = new Dictionary<int, object>
				{
					{ 0, BarID },
					{ 1, Instance.Name },
					{ 2, "Default" },
					{ 3, "Monuments" },
					{ 4, barSettings.Order },
					{ 5, barSettings.Height },
					{ 6, barSettings.Main_Color },
					{ 11, barSettings.Image_IsRawImage },
					{ 12, barSettings.Image_Color },
					{ 16, barSettings.Text_Size },
					{ 17, barSettings.Text_Color },
					{ 18, barSettings.Text_Font },
					{ 23, barSettings.SubText_Size },
					{ 24, barSettings.SubText_Color },
					{ 25, barSettings.SubText_Font }
				};
				
				if (barSettings.Main_Color.StartsWith("#"))
					StatusBar.Add(-6, barSettings.Main_Transparency);
				if (!string.IsNullOrWhiteSpace(barSettings.Main_Material))
					StatusBar.Add(7, barSettings.Main_Material);
				if (barSettings.Image_Color.StartsWith("#"))
                    StatusBar.Add(-12, barSettings.Image_Transparency);
				if (barSettings.Image_Outline_Enabled)
				{
					StatusBar.Add(13, barSettings.Image_Outline_Color);
					if (barSettings.Image_Outline_Color.StartsWith("#"))
						StatusBar.Add(-13, barSettings.Image_Outline_Transparency);
					StatusBar.Add(14, barSettings.Image_Outline_Distance);
				}
				if (barSettings.Text_Outline_Enabled)
                {
					StatusBar.Add(20, barSettings.Text_Outline_Color);
					if (barSettings.Text_Outline_Color.StartsWith("#"))
						StatusBar.Add(-20, barSettings.Text_Outline_Transparency);
					StatusBar.Add(21, barSettings.Text_Outline_Distance);
                }
				if (barSettings.SubText_Outline_Enabled)
                {
					StatusBar.Add(26, barSettings.SubText_Outline_Color);
					if (barSettings.SubText_Outline_Color.StartsWith("#"))
						StatusBar.Add(-26, barSettings.SubText_Outline_Transparency);
					StatusBar.Add(27, barSettings.SubText_Outline_Distance);
				}
				
				UpdateBars();
				
				var players = GetMonumentPlayers();
				for (int i = 0; i < players.Length; i++)
					OnPlayerEnter(players[i]);
				
				if (IsPvP)
                {
					var entities = GetMonumentEntities();
					for (int i = 0; i < entities.Length; i++)
						Instance.OnEntityEnterPVP(entities[i]);
                }
			}

            public void OnPlayerEnter(BasePlayer player)
            {
				if (player == null) return;
				if (IsPvP)
                {
					Instance.OnPlayerEnterPVP(player, MonumentID);
					return;
				}
				if (PlayersQueue.Contains(player) || !player.IsConnected || player.IsDead()) return;
				if (IsOwned)
                {
					if (OwnerID == player.userID)
						Instance.SendCounterBar(player, this, LootEndTime, Settings.UseProgressBar ? LootStartTime : 0d);
					else if (IsOwnerFriend(player))
                    {
						Instance.SendCounterBar(player, this, LootEndTime, Settings.UseProgressBar ? LootStartTime : 0d);
						FriendsList.Add(player);
						SendMessageText(player, string.Format(Instance.lang.GetMessage("MsgMonumentFriendEnter", Instance, player.UserIDString), OwnerName));
					}
					else
					{
						PlayersQueue.Add(player);
						int queuePos = PlayersQueue.Count;
						double price = !_economicsIsLoaded || Settings.Price <= 0d ? 0d : Settings.Price * Instance.GetMonumentPriceMultiplier(player.UserIDString);
                        if (price > 0d)
							SendMessageText(player, string.Format(Instance.lang.GetMessage("MsgMonumentQueueNotFree", Instance, player.UserIDString), queuePos, queuePos, string.Format(_config.PriceFormat, price)));
                        else
							SendMessageText(player, string.Format(Instance.lang.GetMessage("MsgMonumentQueue", Instance, player.UserIDString), queuePos, queuePos, OwnerName));
						UpdateQueueBars();
					}
				}
                else
				{
					PlayersQueue.Add(player);
					if (PlayersQueue.IndexOf(player) == 0)
						OfferOwnership();
					UpdateQueueBars();
				}
            }
			
			public void OnPlayerExit(BasePlayer player, string reason = "leave")
            {
				if (player == null) return;
				Instance.DestroyBar(player.userID, BarID);
				if (IsPvP)
                {
					Instance.OnPlayerExitPVP(player, MonumentID, Settings.PvPDelay);
					return;
				}
				if (OwnerID == player.userID)
                {
                    if (reason == "death")
                        Instance._respawnMessage[player.userID] = string.Format(Instance.lang.GetMessage("MsgMonumentLooterDeath", Instance, player.UserIDString), new string[] { GetMonumentName(player.userID), $"{(int)(LootEndTime - _unixSeconds)}" });
                    else
                    {
						Instance.SendCounterBar(player, this, _unixSeconds + _monumentsConfig.TimeToComeBack, _unixSeconds);
						player.SendEffect();
                        Instance.SendMessage(player, "MsgMonumentLooterExit", new string[] { _monumentsConfig.TimeToComeBack.ToString() });
                        if (MonumentTimer != null)
                            MonumentTimer.Destroy();
						MonumentTimer = Instance.timer.Once(_monumentsConfig.TimeToComeBack, () =>
                        {
							if (player == null || !Instance.IsPlayerInMonument(MonumentID, player))
							{
								RemoveOwner();
								if (player != null)
									Instance.SendMessage(player, "MsgMonumentLooterRemoved");
							}
                        });
                    }
                }
				else if (!FriendsList.Remove(player))
				{
					if (PlayersQueue.Remove(player))
						UpdateQueueBars();
					else
						Instance.DestroyUI(player, MonumentOfferUI);
				}
			}
			
			public void SetNewOwner(BasePlayer newOwner)
			{
				PlayersQueue.Remove(newOwner);
				if (IsOwned)
					PlayersQueue.Add(Owner);
				Owner = newOwner;
				LootStartTime = _unixSeconds;
				LootEndTime = LootStartTime + Settings.LootingTime;
				double startTime = Settings.UseProgressBar ? LootStartTime : 0d;
				Instance.SendCounterBar(newOwner, this, LootEndTime, startTime);
				var friends = Pool.Get<List<BasePlayer>>();
				foreach (var player in PlayersQueue)
				{
					if (IsOwnerFriend(player))
						friends.Add(player);
				}
				FriendsList.Clear();
				foreach (var friend in friends)
				{
					PlayersQueue.Remove(friend);
					FriendsList.Add(friend);
					Instance.SendCounterBar(friend, this, LootEndTime, startTime);
				}
				Pool.FreeUnmanaged(ref friends);
				UpdateQueueBars();
				if (Settings.Broadcast && _unixSeconds > BroadcastNext)
                {
					string timeStr = $"{(int)((LootEndTime - _unixSeconds) / 60)}";
					foreach (var player in BasePlayer.activePlayerList)
					{
						if (player.userID.IsSteamId())
							SendMessageText(player.IPlayer, string.Format(Instance.lang.GetMessage("MsgMonumentOccupied", Instance, player.UserIDString), GetMonumentName(player.userID), newOwner.displayName, timeStr));
					}
					BroadcastNext = _unixSeconds + 5d;
				}
				if (Settings.MapMarkerMode == 1)
					UpdateMapMarkers();
			}
			
			public void RemoveOwner()
            {
				if (IsOwned)
				{
					if (Owner != null && Instance.IsPlayerInMonument(MonumentID, Owner))
						PlayersQueue.Add(Owner);
					PlayersQueue.AddRange(FriendsList);
				}
				Owner = null;
				FriendsList.Clear();
				if (Settings.Broadcast && _unixSeconds > BroadcastNext)
                {
					foreach (var player in BasePlayer.activePlayerList)
                    {
                        if (player.userID.IsSteamId())
                            SendMessageText(player.IPlayer, string.Format(Instance.lang.GetMessage("MsgMonumentFree", Instance, player.UserIDString), GetMonumentName(player.userID)));
                    }
					BroadcastNext = _unixSeconds + 5d;
				}
				OfferOwnership();
				UpdateQueueBars();
				if (Settings.MapMarkerMode == 1)
					UpdateMapMarkers();
			}
			
			public void OfferOwnership()
            {
				if (IsPvP || IsOwned) return;
				BasePlayer firstPlayer = null;
				while (PlayersQueue.Count > 0)
                {
                    firstPlayer = PlayersQueue[0];
					if (firstPlayer == null || !firstPlayer.IsConnected)
					{
						firstPlayer = null;
						PlayersQueue.RemoveAt(0);
					}
					else
                        break;
                }
                if (firstPlayer == null) return;
				
				double price = Settings.Price <= 0d ? 0d : Settings.Price * Instance.GetMonumentPriceMultiplier(firstPlayer.UserIDString);
				if (!_economicsIsLoaded || price <= 0d)
					SetNewOwner(firstPlayer);
				else
                {
					if (_monumentsConfig.AutoBuy || _autoBuyPlayers.Contains(firstPlayer.userID))
						firstPlayer.Command($"{Instance._commandUI} monument pay {MonumentID}");
					else
					{
						Instance.ShowMonumentOffer(firstPlayer, this, price);
						MonumentTimer?.Destroy();
						MonumentTimer = Instance.timer.Once(Settings.OfferTime, () =>
						{
							if (firstPlayer != null)
								Instance.DestroyUI(firstPlayer, MonumentOfferUI);
							if (this != null && !IsPvP)
							{
								if (firstPlayer != null && OwnerID != firstPlayer.userID && Instance.IsPlayerInMonument(MonumentID, firstPlayer))
                                {
									PlayersQueue.Remove(firstPlayer);
									Instance.SendMonumentsBar(firstPlayer, this, displayTime: 5);
								}
								OfferOwnership();
							}
						});
					}
					firstPlayer.SendEffect();
				}
			}
			
			public void TrySetLootTimer(float seconds = 0f)
            {
                if (IsPvP || !IsOwned) return;
                double newTime, num;
                if (seconds == 0f)
                    newTime = LootStartTime + Settings.LootingTime;
				else
				{
					if (seconds > (LootEndTime - _unixSeconds))
						newTime = seconds + _unixSeconds;
					else
						return;
				}
				LootEndTime = newTime;
				UpdateLootersBar();
			}
			
			public void UpdateLootersBar()
            {
				if (LootEndTime > _unixSeconds)
                {
					double startTime = Settings.UseProgressBar ? LootStartTime : 0d;
                    Instance.SendCounterBar(Owner, this, LootEndTime, startTime);
                    foreach (var friend in FriendsList)
                        Instance.SendCounterBar(friend, this, LootEndTime, startTime);
                }
            }
			
			public void UpdateQueueBars()
			{
				int queueTotal = PlayersQueue.Count;
				for (int i = 0; i < queueTotal; i++)
					Instance.SendMonumentsBar(PlayersQueue[i], this, $"{i + 1}/{queueTotal}");
			}
			
			public void OnTeamUpdated(BasePlayer player)
            {
				if (IsPvP || !IsOwned) return;
				if (OwnerID == player.userID)
                {
					var players = GetMonumentPlayers();
					for (int i = 0; i < players.Length; i++)
                    {
                        var tPlayer = players[i];
                        if (tPlayer.userID != OwnerID)
                            UpdateFriendList(tPlayer);
                    }
				}
				else
					UpdateFriendList(player);
			}
			
			public void OnFriendUpdated(BasePlayer player, BasePlayer friend)
            {
				if (IsPvP || !IsOwned) return;
				if (OwnerID == player.userID)
					UpdateFriendList(friend);
				else
					UpdateFriendList(player);
			}
			
			private void UpdateFriendList(BasePlayer player)
            {
				bool isFriend = IsOwnerFriend(player);
				if ((isFriend && !FriendsList.Contains(player)) || (!isFriend && FriendsList.Contains(player)))
                {
					OnPlayerExit(player);
                    OnPlayerEnter(player);
                }
			}
			
			public bool SetAsPvP(bool addMarkers = true)
            {
                if (IsPvP) return false;
				IsPvP = true;
                Owner = null;
				FriendsList.Clear();
				PlayersQueue.Clear();
				
				var players = GetMonumentPlayers();
				for (int i = 0; i < players.Length; i++)
                {
                    var player = players[i];
                    Instance.DestroyBar(player.userID, BarID);
                    OnPlayerEnter(player);
                }
				
				var entities = GetMonumentEntities();
				for (int i = 0; i < entities.Length; i++)
					Instance.OnEntityEnterPVP(entities[i]);
				
				if (addMarkers)
					VerifyMapMarkers();
				else
				{
					if (mainMapMarker != null)
						mainMapMarker.Kill();
					if (circleMapMarker != null)
						circleMapMarker.Kill();
				}
				return true;
            }

            public bool RemovePvP()
            {
                if (!IsPvP) return false;
                IsPvP = false;
				
				var players = GetMonumentPlayers();
				for (int i = 0; i < players.Length; i++)
                {
                    var player = players[i];
                    Instance.DestroyBar(player.userID, BarID);
                    Instance.OnPlayerExitPVP(player, MonumentID, Settings.PvPDelay);
                    OnPlayerEnter(player);
                }
				
				var entities = GetMonumentEntities();
				for (int i = 0; i < entities.Length; i++)
					Instance.OnEntityExitPVP(entities[i]);
				
				VerifyMapMarkers();
				return true;
			}
			
			public void VerifyMapMarkers()
            {
				if (Settings.MapMarkerMode == 0 || (Settings.MapMarkerMode == 2 && !IsPvP))
				{
					if (mainMapMarker != null)
						mainMapMarker.Kill();
					if (circleMapMarker != null)
						circleMapMarker.Kill();
					mapMarkerNextTime = 0d;
					return;
				}
				
				CargoShip parentEnt = null;
                if (IsMoveable)
                {
                    string[] parts = MonumentID.Split('_');
                    if (parts.Length > 0 && ulong.TryParse(parts[^1], out ulong cargoID))
                        parentEnt = BaseNetworkable.serverEntities.Find(new NetworkableId(cargoID)) as CargoShip;
                }
				
				string stringPool;
				if (mainMapMarker == null)
				{
					stringPool = StringPool.Get(3459945130u);
					mainMapMarker = GameManager.server.CreateEntity(stringPool, MonumentPos) as VendingMachineMapMarker;
					if (mainMapMarker != null)
					{
						mainMapMarker.enabled = false;
						mainMapMarker.Spawn();
						if (parentEnt != null)
						{
							mainMapMarker.SetParent(parentEnt);
							mainMapMarker.transform.localPosition = Vector3.zero;
						}
					}
					else
						Instance.PrintError($"[Monuments] Failed to create a vending machine marker using (3459945130u): {stringPool}");
				}
				if (Settings.MapMarkerCircle)
				{
					if (circleMapMarker == null)
					{
                        stringPool = StringPool.Get(2849728229u);
                        circleMapMarker = GameManager.server.CreateEntity(stringPool, MonumentPos) as MapMarkerGenericRadius;
                        if (circleMapMarker != null)
                        {
							circleMapMarker.radius = World.Size <= 3600 ? 0.5f : 0.25f;
                            circleMapMarker.Spawn();
                            if (parentEnt != null)
                            {
                                circleMapMarker.SetParent(parentEnt);
                                circleMapMarker.transform.localPosition = Vector3.zero;
                            }
                        }
                        else
                            Instance.PrintError($"[Monuments] Failed to create a generic radius marker using (2849728229u): {stringPool}");
                    }
				}
				else if (circleMapMarker != null)
					circleMapMarker.Kill();
				UpdateMapMarkers();
			}
			
			public void UpdateMapMarkers()
            {
				if (mainMapMarker != null)
                {
					mainMapMarker.markerShopName = $"[{(IsPvP ? "PvP" : "PvE")}] {MonumentNameEng}{(IsOwned ? $"({(int)((LootEndTime - _unixSeconds) / 60)}m) - {OwnerName}" : string.Empty)}";
					mainMapMarker.SendNetworkUpdate();
				}
				if (circleMapMarker != null)
                {
					if (IsPvP)
					{
						circleMapMarker.color1 = MapCircle_PvP.FirstColor;
						circleMapMarker.color2 = MapCircle_PvP.SecondColor;
						circleMapMarker.alpha = MapCircle_PvP.Alpha;
					}
					else if (IsOwned)
					{
						circleMapMarker.color1 = MapCircle_Owned.FirstColor;
						circleMapMarker.color2 = MapCircle_Owned.SecondColor;
						circleMapMarker.alpha = MapCircle_Owned.Alpha;
					}
					else
					{
						circleMapMarker.color1 = MapCircle_Free.FirstColor;
						circleMapMarker.color2 = MapCircle_Free.SecondColor;
						circleMapMarker.alpha = MapCircle_Free.Alpha;
					}
					circleMapMarker.SendUpdate();
				}
				mapMarkerNextTime = _unixSeconds + 5d;
			}
			
			public void UpdateMapMarkerValues()
			{
				if (mapMarkerNextTime > _unixSeconds) return;
				if (mainMapMarker != null && IsOwned)
                {
					mainMapMarker.markerShopName = $"[PvE] {MonumentNameEng}({(int)((LootEndTime - _unixSeconds) / 60)}m) - {OwnerName}";
					mainMapMarker.SendNetworkUpdate();
				}
				if (circleMapMarker != null)
					circleMapMarker.SendUpdate();
				mapMarkerNextTime = _unixSeconds + 5d;
			}
			
			public object CanPickup(BasePlayer looter)
			{
				if (IsPvP || (IsOwned && (OwnerID == looter.userID || FriendsList.Contains(looter) || _pickupPlayers.Contains(OwnerID))))
					return null;
				return false;
			}
			
			public object CanLoot(BasePlayer looter)
            {
                if (IsPvP || (IsOwned && (OwnerID == looter.userID || FriendsList.Contains(looter))))
                    return null;
                return false;
			}
			
			public bool IsOwnerFriend(BasePlayer looter)
            {
				if ((looter.Team != null && looter.Team.members.Contains(OwnerID)) || (Instance.IsFriend(looter.UserIDString, OwnerIDString)))
					return true;
				return false;
			}
			
			public void UpdateBars()
            {
				var barSettings = Settings.Bar;
				StatusBar.Remove(10);
                StatusBar.Remove(9);
                StatusBar.Remove(8);
                if (!string.IsNullOrWhiteSpace(barSettings.Image_Sprite))
                    StatusBar.Add(10, barSettings.Image_Sprite);
                else if (!string.IsNullOrWhiteSpace(barSettings.Image_Local))
                    StatusBar.Add(9, barSettings.Image_Local);
                else
                    StatusBar.Add(8, _imgLibIsLoaded && barSettings.Image_Url.StartsWithAny(Instance.HttpScheme) ? BarID : barSettings.Image_Url);
				
				var progressBar = Settings.ProgressBar;
                StatusProgressBar = new Dictionary<int, object>(StatusBar)
                {
                    { 32, progressBar.Progress_Reverse },
                    { 33, progressBar.Progress_Color },
                    { -33, progressBar.Progress_Transparency },
                    { 34, progressBar.Progress_OffsetMin },
                    { 35, progressBar.Progress_OffsetMax }
                };
                StatusProgressBar[2] = "TimeProgressCounter";
                StatusProgressBar[6] = progressBar.Main_Color;

                if (progressBar.Main_Color.StartsWith("#"))
                    StatusProgressBar[-6] = progressBar.Main_Transparency;
                else
                    StatusProgressBar.Remove(-6);
			}
			
			public string GetMonumentName(ulong userID = 0uL) => (string)(Instance.MonumentsWatcher?.Call(MonumentGetMonumentDisplayName, MonumentID, userID, Settings.ShowSuffix) ?? MonumentID);
			public string GetMonumentName(string userID) => (string)(Instance.MonumentsWatcher?.Call(MonumentGetMonumentDisplayName, MonumentID, userID, Settings.ShowSuffix) ?? MonumentID);

			public BasePlayer[] GetMonumentPlayers() => (BasePlayer[])(Instance.MonumentsWatcher?.Call(MonumentGetMonumentPlayers, MonumentID) ?? Array.Empty<BasePlayer>());
			public BaseEntity[] GetMonumentEntities() => (BaseEntity[])(Instance.MonumentsWatcher?.Call(MonumentGetMonumentEntities, MonumentID) ?? Array.Empty<BaseEntity>());
			
			public void Destroy(string monumentID = "")
            {
				if (!string.IsNullOrWhiteSpace(monumentID))
					Instance._monumentsList.Remove(monumentID);
				
				if (MonumentTimer != null)
					MonumentTimer.Destroy();
				
				if (_statusIsLoaded)
				{
					if (IsOwned)
						Instance.DestroyBar(OwnerID, BarID);
					foreach (var friend in FriendsList)
						Instance.DestroyBar(friend.userID, BarID);
					foreach (var player in PlayersQueue)
						Instance.DestroyBar(player.userID, BarID);
                }
				FriendsList.Clear();
				PlayersQueue.Clear();
				Pool.FreeUnmanaged(ref FriendsList);
				Pool.FreeUnmanaged(ref PlayersQueue);
				
				if (mainMapMarker != null)
                    mainMapMarker.Kill();
                if (circleMapMarker != null)
                    circleMapMarker.Kill();
			}
		}
		private object HandleFrankensteinTableUse(FrankensteinTable table, BasePlayer player)
        {
			if (table.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, table.net.ID.Value))
            {
				object result = CanInteractSharedEntity(player, table);
				if (result != null)
                {
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                    return true;
                }
                return null;
			}
            return null;
		}
        private bool MakeDeposit(string userID, double amount) => (bool)(Economics?.Call("Deposit", userID, amount) ?? false);
		void OnTeamKick(RelationshipManager.PlayerTeam team, BasePlayer player, ulong targetID)
        {
			if (BasePlayer.TryFindByID(targetID, out var tPlayer))
				OnTeamUpdated(tPlayer);
		}
		
		private void ForceFailBedMission(BasePlayer player)
        {
            using HashSet<StaticRespawnArea>.Enumerator enumerator = StaticRespawnArea.staticRespawnAreas.GetEnumerator();
            if (enumerator.MoveNext() && enumerator.Current.IsAuthed(player.userID))
            {
                enumerator.Current.Deauthorize(player.userID);
                player.SendRespawnOptions();
            }

            BaseMission.MissionInstance missionInstance = null;
			GetOrCreatePlayerData(player.userID.ToString(), out var playerData);
			for (int i = 0; i < playerData.Missions.Count; i++)
			{
                missionInstance = playerData.Missions[i];
                if (missionInstance.missionID == _bedMissionId)
                {
					if (missionInstance.status == BaseMission.MissionStatus.Completed)
						missionInstance.GetMission().MissionFailed(missionInstance, player, BaseMission.MissionFailReason.Abandon);
					break;
				}
			}
		}
		private static bool UrishMumkin(ulong a, BasePlayer b) => b.userID == a || (b.Team != null && b.Team.members.Contains(a) && _teamsList.TryGetValue(b.currentTeam, out var c) && c.FriendlyFire);
		
		object OnSwitchToggle(IOEntity entity, BasePlayer player)
        {
			if (!_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, entity.net.ID.Value))
            {
				object result = null;
				if (entity.OwnerID.IsSteamId())
                    result = CanInteractSharedEntity(player, entity);
                else if (_monumentsList.TryGetValue(GetEntityMonument(entity), out var monumentData))
                    result = monumentData.CanLoot(player);
				
				if (result != null)
                {
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                    return true;
                }
                return null;
			}
			return null;
		}
		
                private const string Bar_PvP = "RealPVE_PvP";
		
		object OnPortalUse(BasePlayer player, HalloweenDungeon halloween) => HandlePortalUse(player, halloween);
		private void DestroyAllBars() => AdvancedStatus?.Call(StatusDeleteAllPluginBars, Name);

		void OnPlayerDisconnected(BasePlayer player, string reason)
		{
			_playerUI.Remove(player.userID);
			if (_config.AntiSleeper > 0f && player.BinoMumkin() != null)
				player.Invoke(player.ScheduledDeath, _config.AntiSleeper);
			if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
				monumentData.OnPlayerExit(player, "disconnect");
		}

		private int GetTurretsLimit(string userID)
		{
			PvEPermission perm;
            int result = _permissionsConfig.PermissionsList[0].Turrets;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				if (result < 0)
					break;
				perm = _permissionsConfig.PermissionsList[i];
                if ((perm.Turrets < 0 || perm.Turrets > result) && permission.UserHasPermission(userID, perm.Name))
                    result = perm.Turrets;
            }
            return result;
		}

		private float GetMonumentPriceMultiplier(string userID)
        {
			PvEPermission perm;
            float result = _permissionsConfig.PermissionsList[0].Monument_Multiplier;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
                perm = _permissionsConfig.PermissionsList[i];
                if (perm.Monument_Multiplier > result && permission.UserHasPermission(userID, perm.Name))
                    result = perm.Monument_Multiplier;
            }
            return result;
		}
		
		public static BuildingPrivlidge GetBuildingPrivilege(BasePlayer player)
        {
            var obb = new OBB(player.transform.position, player.transform.lossyScale, player.transform.rotation, player.bounds);
            BuildingBlock other = null;
            BuildingPrivlidge result = null;
            var obj2 = Pool.Get<List<BuildingBlock>>();
            Vis.Entities(obb.position, 16f + obb.extents.magnitude, obj2, 2097152);
            for (int i = 0; i < obj2.Count; i++)
            {
                var buildingBlock = obj2[i];
                if (buildingBlock.isServer != player.isServer || !buildingBlock.IsOlderThan(other) || obb.Distance(buildingBlock.WorldSpaceBounds()) > 16f)
                    continue;

                var building = buildingBlock.GetBuilding();
                if (building != null)
                {
                    var dominatingBuildingPrivilege = building.GetDominatingBuildingPrivilege();
                    if (!(dominatingBuildingPrivilege == null))
                    {
                        other = buildingBlock;
                        result = dominatingBuildingPrivilege;
                    }
                }
            }
            Pool.FreeUnmanaged(ref obj2);
            return result;
        }

		void OnCargoWatcherCreated(string monumentID, string category, CargoShip cargoShip)
		{
			if (!_monumentsConfig.TrackedCategories.Contains(category) || _monumentsConfig.IgnoredNames.Contains("CargoShip")) return;
			NextTick(() =>
			{
				if (cargoShip.IsValid() && cargoShip.skinID == 0uL)
					_monumentsList[monumentID] = new MonumentData(monumentID, _monumentsConfig.MonumentsSettings["CargoShip"], true);
			});
		}
		
		private bool IsDeployableEntity(BaseCombatEntity entity) => entity.PrefabName.Contains("building") || entity.PrefabName.Contains("modular") || entity is BaseMountable || entity is LegacyShelter || entity is LegacyShelterDoor || _deployables.Contains(entity.PrefabName);

        private void DestroyVehiclePanels(BasePlayer player)
        {
            if (player == null) return;
            DestroyUI(player, _uiVehiclePanel);
        }
		
		private void InitVehicles()
        {
            var existList = new List<ulong>();
            foreach (var entity in BaseNetworkable.serverEntities)
            {
				if (!entity.IsValid()) continue;
				if (entity is BaseVehicle vehicle)
                    InitVehicle(vehicle);
                else if (entity is HotAirBalloon balloon)
                    InitVehicle(balloon);
                else
                    continue;
				existList.Add(entity.net.ID.Value);
			}
            foreach (var vehID in _vehiclesList.Keys.ToList())
            {
                if (!existList.Contains(vehID))
                    _vehiclesList.Remove(vehID);
            }
		}
		
		private void DestroyUI(BasePlayer player, string uiName)
		{
			CuiHelper.DestroyUi(player, uiName);
			if (_playerUI.ContainsKey(player.userID))
				_playerUI[player.userID].Remove(uiName);
        }
		object CanSwapToSeat(BasePlayer player, BaseMountable mountable) => HandleVehicleSeat(player, mountable);
		object OnEntityTakeDamage(Bike bike, HitInfo info) => HandleDamageToVehicle(bike, info);
		public static Dictionary<ulong, RRData> _rrAllPlayers = new Dictionary<ulong, RRData>();
		object OnSiegeWeaponPull(BaseSiegeWeapon siegeWeapon, BasePlayer player)
		{
			if (!player.Uyda())
			{
				object result = HandleVehicleInteractionM(player, siegeWeapon, "MsgVehicleCantPull");
				if (result != null)
					siegeWeapon.ClientRPC(RpcTarget.NetworkGroup("CLIENT_StopPulling"));
				return result;
			}
			return null;
		}
		void OnTeamDisbanded(RelationshipManager.PlayerTeam team) => _teamsList.Remove(team.teamID);
		
		object OnEngineStart(BaseVehicle vehicle, BasePlayer driver) => HandleVehicleInteraction(driver, vehicle, "MsgVehicleCantEngineStart");
		
		private static void SendMessageText(BasePlayer player, string text, bool isWarning = true) => SendMessageText(player.IPlayer, text, isWarning);
		private static double _unixSeconds = 0d;
		
		private void HandleLanguageFile(Dictionary<string, string> langFile, string langKey)
        {
            var existFile = lang.GetMessages(langKey, this);
            if (existFile == null || !existFile.Any())
            {
                if (!Directory.Exists(Path.Combine(Interface.Oxide.LangDirectory, langKey)))
                    Directory.CreateDirectory(Path.Combine(Interface.Oxide.LangDirectory, langKey));
                File.WriteAllText(Path.Combine(Interface.Oxide.LangDirectory, $"{langKey}{Path.DirectorySeparatorChar}{Name}.json"), JsonConvert.SerializeObject(langFile, Formatting.Indented));
            }
            lang.RegisterMessages(langFile, this, langKey);
        }

		void OnServerInitialized(bool initial)
        {
            for (int i = 0; i < _config.LanguageKeys.Count; i++)
                HandleLanguageFile(_enLang, _config.LanguageKeys[i]);
            HandleLanguageFile(_ruLang, "ru");
            _enLang.Clear();
            _ruLang.Clear();
			
			_unixSeconds = Network.TimeEx.currentTimestamp;
			UnityEngine.Application.logMessageReceived += HookConflict;
			UnityEngine.Application.logMessageReceived -= Facepunch.Output.LogHandler;
			_defaultBeds = ConVar.Server.max_sleeping_bags;
			_defaultShelters = LegacyShelter.max_shelters;
			_defaultTurrets = ConVar.Sentry.maxinterference;
			if (string.IsNullOrWhiteSpace(_config.WipeID) || _config.WipeID != SaveRestore.WipeId)
			{
				_config.WipeID = SaveRestore.WipeId;
				_vehiclesList.Clear();
				_teamsList.Clear();
				PrintWarning("Wipe detected! Stored data was reset!");
				SaveConfig();
				SaveData(_dataVehiclesPath, _vehiclesList);
				SaveData(_dataTeamsPath, _teamsList);
			}
			_pvpPlayers = new Dictionary<ulong, PlayerPvP>();
			_pvpEntities = new HashSet<ulong>();
			InitPermissions();
			foreach (var itemDef in ItemManager.GetItemDefinitions())
            {
                if (itemDef.TryGetComponent<ItemModDeployable>(out var deployable))
                    _deployables.Add(deployable.entityPrefab.resourcePath);
            }
			foreach (var kvp in _playersList)
            {
				var playerData = kvp.Value;
				playerData.UserID = kvp.Key;
				if (playerData.AdminLoot)
                    _unrestrictedLooters.Add(playerData.UserID);
				if (playerData.AutoBuy)
					_autoBuyPlayers.Add(playerData.UserID);
				if (playerData.AllowPickup)
                    _pickupPlayers.Add(playerData.UserID);
				if (playerData.SharedEntities == null)
					playerData.SharedEntities = new Dictionary<ulong, SharedEntity>();
				else if (playerData.SharedEntities.Any())
                {
					var list = playerData.SharedEntities;
					playerData.SharedEntities = new Dictionary<ulong, SharedEntity>();
					foreach (var kvp2 in list)
                    {
						var sharedEntity = kvp2.Value;
						if (sharedEntity == null)
							continue;
						var netID = kvp2.Key;
						var entity = BaseNetworkable.serverEntities.Find(new NetworkableId(netID)) as BaseEntity;
						if (entity != null && entity.OwnerID == playerData.UserID)
							playerData.SharedEntities[netID] = _sharedEntities[netID] = sharedEntity;
					}
				}
            }
			
			ConfigForcePvEToggled();
			if (_config.Force_Tutorial)
				ConVar.Server.tutorialEnabled = true;
			
			_harmony = new Harmony(IdForHarmony);
			PatchMethod(typeof(DecayEntity), "StartBeingDemolishable", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModStartDemolishable)));
			PatchMethod(typeof(Mannequin), "Server_ChangePose", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModChangePose)), new Type[] { typeof(BaseEntity.RPCMessage) });
			PatchMethod(typeof(Mannequin), "Server_RequestSwap", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModSwapMannequin)), new Type[] { typeof(BaseEntity.RPCMessage) });
			PatchMethod(typeof(AutoTurret), "OnEntityEnterTrigger", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModEntityEnter)), new Type[] { typeof(BaseNetworkable) });
			PatchMethod(typeof(ItemModProjectileDart), "ServerProjectileHitEntity", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModDartHit)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(SmokeGrenade), "Explode", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModSmokeExplode)));
			PatchMethod(typeof(Flashbang), "Explode", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModFlashExplode)));
			PatchMethod(typeof(DeployableSiegeExplosive), "Hurt", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModSiegeExplosiveHurt)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(RidableHorse), "SERVER_RequestTow", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModHorseTow)), new Type[] { typeof(BaseEntity.RPCMessage) });
			PatchMethod(typeof(RidableHorse), "SERVER_RequestDetach", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModHorseDetach)), new Type[] { typeof(BaseEntity.RPCMessage) });
			PatchMethod(typeof(RidableHorse), "SERVER_RequestSaddleSwap", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModHorseSaddleSwap)), new Type[] { typeof(BaseEntity.RPCMessage) });
			PatchMethod(typeof(NaturalBeehive), "OnDied", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModNaturalBeehiveDied)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(LootableCorpse), "DropItems", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModDroppedContainer)));
			PatchMethod(typeof(BaseProjectile), "AssignInitiator", 1, new HarmonyMethod(typeof(RealPVE), nameof(ModAssignInitiator)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(RemoteControlEntity), "InitializeControl", 0, new HarmonyMethod(typeof(RealPVE), nameof(ModRemoteEntityControl)), new Type[] { typeof(CameraViewerId) });
			PatchMethod(typeof(ChickenCoop), "SpawnChicken", 1, new HarmonyMethod(typeof(RealPVE), nameof(ModSpawnChicken)), new Type[] { typeof(int) });
			PatchMethod(typeof(ItemContainer), "Drop", 1, new HarmonyMethod(typeof(RealPVE), nameof(ModDroppedContainer2)), new Type[] { typeof(string), typeof(Vector3), typeof(Quaternion), typeof(float) });
			PatchMethod(typeof(ItemContainer), "Drop", 1, new HarmonyMethod(typeof(RealPVE), nameof(ModDroppedContainer3)), new Type[] { typeof(string), typeof(Vector3), typeof(Quaternion), typeof(ItemContainer[]) });
			PatchMethod(typeof(ThrownWeapon), "DoThrowImpl", 1, new HarmonyMethod(typeof(RealPVE), nameof(ModDroneWeaponThrown)));
			PatchMethod(typeof(BasePlayer), "Hurt", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModMirror)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(BuildingBlock), "Hurt", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModMirror)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(BeeSwarmAI), "ThinkAI", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModBeeThink)));
			PatchMethod(typeof(Drone), "OnCollisionEnter", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModDroneCollision)), new Type[] { typeof(Collision) });
			PatchMethod(typeof(NaturalBeehive), "SpawnSwarm", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModSpawnSwarm)));
			PatchMethod(typeof(ItemModProjectileSpawn), "ServerProjectileHit", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModFireBall)), new Type[] { typeof(HitInfo) });
			PatchMethod(typeof(DroneStorage), "TryServerItemDrop", 2, new HarmonyMethod(typeof(RealPVE), nameof(ModDroneItemDrop)), new Type[] { typeof(Item) });
			
			foreach (var player in BasePlayer.activePlayerList)
                OnPlayerConnected(player);
			
			ToggleImageLib(ImageLibrary != null && ImageLibrary.IsLoaded);
			if (AdvancedStatus != null && AdvancedStatus?.Call("IsReady") != null)
				OnAdvancedStatusLoaded();
			Subscribe(nameof(OnAdvancedStatusLoaded));
			_economicsIsLoaded = Economics != null && Economics.IsLoaded;
            _friendsIsLoaded = Friends != null && Friends.IsLoaded;
            if (MonumentsWatcher != null && MonumentsWatcher.IsLoaded)
				OnMonumentsWatcherLoaded();
			Subscribe(nameof(OnMonumentsWatcherLoaded));
			
			InitVehicles();
			InitTeams();
			InitVanillaEvents();
			
			UpdatePvPBars();
			
			ToggleRaidableBases(RaidableBases != null && RaidableBases.IsLoaded);
			
			foreach (var entity in BaseNetworkable.serverEntities)
			{
				if (!entity.IsValid()) continue;
				if (_config.RecyclerNoPenalties && entity is Recycler recycler)
					using (var setFlags = recycler.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
					{
					    setFlags.Set(BaseEntity.Flags.Reserved9, false);
					}
				else if (entity is DecayEntity decayEntity)
					ApplyDemolishable(decayEntity);
			}
            ConfigPermissionLimitsToggled();
			ConfigSafeZoneLogoutDeathToggled();
			ConfigHandcuffToggled();
			ConfigPortalsToggled();
			ConfigBackpackDropToggled();
			ConfigLaptopAttackToggled();
			ConfigItemPickupToggled();
			if (_newbieConfig.Respawn_Override)
				Subscribe(nameof(OnDefaultItemsReceive));
			ToggleRandomRaids(RandomRaids != null && RandomRaids.IsLoaded);
			ToggleDynamicPVP(DynamicPVP != null && DynamicPVP.IsLoaded);
			for (int i = 0; i < _defaultHooks.Length; i++)
				Subscribe(_defaultHooks[i]);
			_defaultHooks = null;
			Subscribe(nameof(OnPluginLoaded));
			Subscribe(nameof(OnPluginUnloaded));
			
			foreach (var entity in BaseNetworkable.serverEntities)
            {
                if (!entity.IsValid()) continue;
				if (entity is AutoTurret turret)
					UpdateTurretTrigger(turret);
				else if (entity is FlameTurret flameTurret)
					UpdateTurretTrigger(flameTurret);
				else if (entity is GunTrap gunTrap)
					UpdateTurretTrigger(gunTrap);
			}
			
			_updatesTimer = timer.Every(1f, CheckForUpdates);
			
			if (!_economicsIsLoaded)
				PrintWarning("Economy plugin not found! For enhanced functionality, it is recommended to install it!\n* https://umod.org/plugins/economics");
			if (!_watcherIsLoaded)
				PrintWarning("MonumentsWatcher plugin not found! MonumentsWatcher is required to work with monuments!\n* https://codefling.com/plugins/monuments-watcher");
			if (!_statusIsLoaded)
			{
				if (initial && AdvancedStatus != null)
                    PrintWarning("AdvancedStatus plugin found, but not ready yet. Waiting for it to load...");
                else
                    PrintWarning("AdvancedStatus plugin not found! AdvancedStatus is required to work with status bars!\n* https://codefling.com/plugins/advanced-status");
			}
			PrintError($"{Title} has been successfully loaded! If you encounter any issues, please create a thread in the support section on the plugin's page.");
			
			void PatchMethod(Type targetType, string methodName, int patchIndex, HarmonyMethod patchMethod, Type[] parameters = null)
            {
                var targetMethod = parameters == null ? AccessTools.Method(targetType, methodName) : AccessTools.Method(targetType, methodName, parameters);
				if (targetMethod != null)
                {
                    if (patchIndex == 3)
                        _harmony.Patch(targetMethod, finalizer: patchMethod);
                    else if (patchIndex == 2)
                        _harmony.Patch(targetMethod, transpiler: patchMethod);
                    else if (patchIndex == 1)
                        _harmony.Patch(targetMethod, postfix: patchMethod);
                    else
                        _harmony.Patch(targetMethod, prefix: patchMethod);
                }
			}
		}
		void OnFriendRemoved(string userID, string friendID) => OnFriendUpdated(userID, friendID);
		
		void OnEntityKill(ScientistNPC scientist)
        {
            if (scientist.skinID == _bradleySkinId)
                _eventScientistsList.Remove(scientist.net.ID);
        }
		void OnEntityKill(SubmarineDuo submarineDuo) => HandleVehicleKill(submarineDuo);
		
		private void Command_AdminPermissions(IPlayer player, string[] args)
        {
			if (args[0].Equals("clear", StringComparison.OrdinalIgnoreCase))
            {
				
				
                int count = _permissionsConfig.PermissionsList.Count - 1;
				if (count < 1)
					SendMessageText(player, lang.GetMessage("CmdAdminPermNotEnough", this, player.Id));
				else
                {
					_permissionsConfig.PermissionsList.RemoveRange(1, count);
					SavePermissionsConfig();
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermCleared", this, player.Id), count), false);
				}
				return;
			}
			
			if (!string.IsNullOrWhiteSpace(args[0]) && !string.IsNullOrWhiteSpace(args[1]) && !args[0].Equals("help", StringComparison.OrdinalIgnoreCase))
            {
				var perm = _permissionsConfig.PermissionsList.FirstOrDefault(p => p.Name.Equals(args[1], StringComparison.OrdinalIgnoreCase));
                if (args[0].Equals("add", StringComparison.OrdinalIgnoreCase))
                {
					
					
					if (perm != null)
						SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermAlreadyExists", this, player.Id), args[1]));
					else
					{
						string permName = args[1];
						if (permName.StartsWith("realpve", StringComparison.OrdinalIgnoreCase))
                        {
                            permName = permName.ToLower();
                            permission.RegisterPermission(permName, this);
                        }
                        var sourcePerm = !string.IsNullOrWhiteSpace(args[2]) ? _permissionsConfig.PermissionsList.FirstOrDefault(p => p.Name.Equals(args[2], StringComparison.OrdinalIgnoreCase)) : null;
                        if (sourcePerm == null)
                            sourcePerm = _permissionsConfig.PermissionsList[0];
                        _permissionsConfig.PermissionsList.Add(sourcePerm.Clone(permName));
						SavePermissionsConfig();
						SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermAdded", this, player.Id), permName), false);
					}
					return;
				}
				
				if (perm == null)
				{
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermNotFound", this, player.Id), args[1]));
					return;
				}
				
				if (args[0].Equals("remove", StringComparison.OrdinalIgnoreCase))
                {
					
					
					if (_permissionsConfig.PermissionsList.Count <= 1)
						SendMessageText(player, lang.GetMessage("CmdAdminPermNotEnough", this, player.Id));
					else
					{
						_permissionsConfig.PermissionsList.Remove(perm);
						SavePermissionsConfig();
						SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermRemoved", this, player.Id), args[1]), false);
					}
					return;
				}
				
				if (args[0].Equals("edit", StringComparison.OrdinalIgnoreCase))
                {
                    
                    
                    string valRes = string.Empty;
					bool caseFound = true, isEdited = false;
					switch (args[2])
                    {
						case "queue":
                            if (!bool.TryParse(args[3], out var queue))
                                queue = !perm.BypassQueue;
                            perm.BypassQueue = queue;
							valRes = perm.BypassQueue.ToString();
                            isEdited = true;
                            break;
						case "unlockRespawn":
                            if (!bool.TryParse(args[3], out var unlockRespawn))
                                unlockRespawn = !perm.UnlockStaticRespawn;
                            perm.UnlockStaticRespawn = unlockRespawn;
							foreach (var tBPlayer in BasePlayer.activePlayerList)
                            {
								if (HasUnlockStaticRespawn(tBPlayer))
									ForceCompleteBedMission(tBPlayer);
								else
									ForceFailBedMission(tBPlayer);
							}
							valRes = perm.UnlockStaticRespawn.ToString();
                            isEdited = true;
							break;
						case "beds":
                            if (int.TryParse(args[3], out var beds))
                            {
                                perm.Beds = beds;
                                if (ConVar.Server.max_sleeping_bags < perm.Beds)
                                    ConVar.Server.max_sleeping_bags = perm.Beds;
                                valRes = perm.Beds.ToString();
                                isEdited = true;
                            }
                            break;
                        case "shelters":
                            if (int.TryParse(args[3], out var shelters))
                            {
                                perm.Shelters = shelters;
                                if (LegacyShelter.max_shelters < perm.Shelters)
                                    LegacyShelter.max_shelters = perm.Shelters;
                                valRes = perm.Shelters.ToString();
                                isEdited = true;
                            }
                            break;
                        case "turrets":
                            if (int.TryParse(args[3], out var turrets))
                            {
                                perm.Turrets = turrets;
                                if (ConVar.Sentry.maxinterference < perm.Turrets)
                                    ConVar.Sentry.maxinterference = perm.Turrets;
                                valRes = perm.Turrets.ToString();
                                isEdited = true;
                            }
                            break;
                        case "hackable":
                            if (float.TryParse(args[3], out var hackSec))
                            {
                                perm.HackableCrateSkip = hackSec;
                                valRes = perm.HackableCrateSkip.ToString();
                                isEdited = true;
                            }
                            break;
                        case "monuments":
                            if (float.TryParse(args[3], out var monuments))
                            {
                                perm.Monument_Multiplier = monuments;
                                valRes = perm.Monument_Multiplier.ToString();
                                isEdited = true;
                            }
                            break;
                        case "events":
                            if (float.TryParse(args[3], out var events))
                            {
                                perm.Event_Multiplier = events;
                                valRes = perm.Event_Multiplier.ToString();
                                isEdited = true;
                            }
                            break;
                        case "rb_limit":
                            if (int.TryParse(args[3], out var rb_limit))
                            {
                                perm.RB_Limit = rb_limit;
                                valRes = perm.RB_Limit.ToString();
                                isEdited = true;
                            }
                            break;
                        case "rb_mult":
                            if (float.TryParse(args[3], out var rb_mult))
                            {
                                perm.RB_Multiplier = rb_mult;
                                valRes = perm.RB_Multiplier.ToString();
                                isEdited = true;
                            }
                            break;
                        case "vehicles":
							
							
							caseFound = false;
							if (args.Length > 5 && Enum.TryParse<VehicleType>(args[3], true, out var vehicleType) && vehicleType != VehicleType.None && perm.Allowed_Vehicles.TryGetValue(vehicleType, out var vehicleProp))
                            {
								if (args[4].Equals("limit", StringComparison.OrdinalIgnoreCase))
                                {
									caseFound = true;
									if (int.TryParse(args[5], out var veh_limit))
									{
										vehicleProp.Limit = veh_limit;
										valRes = vehicleProp.Limit.ToString();
                                        isEdited = true;
									}
								}
								else if (args[4].Equals("price", StringComparison.OrdinalIgnoreCase))
                                {
									caseFound = true;
									if (float.TryParse(args[5], out var veh_price))
									{
										vehicleProp.Price = veh_price;
										valRes = vehicleProp.Price.ToString();
                                        isEdited = true;
									}
								}
							}
							
							if (isEdited)
                            {
								SavePermissionsConfig();
								SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermEditVehiclesSucceed", this, player.Id), perm.Name, args[3], args[4], valRes), false);
							}
							else
							{
								player.Reply(lang.GetMessage("CmdAdminPermEditVehicles", this, player.Id));
								if (caseFound)
									SendMessageText(player, lang.GetMessage("CmdMainEditWrongValue", this, player.Id));
							}
							return;
                        default:
                            caseFound = false;
                            break;
                    }
					
					if (isEdited)
                    {
						SavePermissionsConfig();
						SendMessageText(player, string.Format(lang.GetMessage("CmdAdminPermEditSucceed", this, player.Id), perm.Name, args[2], valRes), false);
					}
                    else
                    {
						player.Reply(lang.GetMessage("CmdAdminPermEdit", this, player.Id));
						if (caseFound)
							SendMessageText(player, lang.GetMessage("CmdMainEditWrongValue", this, player.Id));
					}
					return;
				}
			}
			
			player.Reply(lang.GetMessage("CmdAdminPerm", this, player.Id));
		}
		object CanDemolish(BasePlayer player, BuildingBlock block, BuildingGrade.Enum grade) => _unrestrictedLooters.Contains(player.userID) ? true : (!block.OwnerID.IsSteamId() || IsEntityInPvP(player.userID, block.net.ID.Value) ? null : player.TasirMumkin(block.OwnerID));
		private Harmony _harmony;

		public class BarSettings
		{
			public int Order { get; set; } = 10;
			public int Height { get; set; } = 26;
			
			[JsonProperty(PropertyName = "Main_Color(Hex or RGBA)")]
			public string Main_Color { get; set; } = "#FFBF99";

			public float Main_Transparency { get; set; } = 0.8f;
			public string Main_Material { get; set; } = string.Empty;
			public string Image_Url { get; set; } = "https://i.imgur.com/mn8reWg.png";
			
			[JsonProperty(PropertyName = "Image_Local(Leave empty to use Image_Url)")]
			public string Image_Local { get; set; } = "RealPVE_Default";
			
			[JsonProperty(PropertyName = "Image_Sprite(Leave empty to use Image_Local or Image_Url)")]
			public string Image_Sprite { get; set; } = string.Empty;
			
			public bool Image_IsRawImage { get; set; }
			
			[JsonProperty(PropertyName = "Image_Color(Hex or RGBA)")]
			public string Image_Color { get; set; } = "#FFDCB6";
			
			public float Image_Transparency { get; set; } = 1f;
			
			[JsonProperty(PropertyName = "Is it worth enabling an outline for the image?")]
			public bool Image_Outline_Enabled { get; set; }
			
			[JsonProperty(PropertyName = "Image_Outline_Color(Hex or RGBA)")]
            public string Image_Outline_Color { get; set; } = "0.1 0.3 0.8 0.9";
			
			public float Image_Outline_Transparency { get; set; }
			public string Image_Outline_Distance { get; set; } = "0.75 0.75";
			public int Text_Size { get; set; } = 12;
			
			[JsonProperty(PropertyName = "Text_Color(Hex or RGBA)")]
			public string Text_Color { get; set; } = "1 1 1 1";
			
			[JsonProperty(PropertyName = "Text_Font(https://umod.org/guides/rust/basic-concepts-of-gui#fonts)")]
			public string Text_Font { get; set; } = "RobotoCondensed-Bold.ttf";
			
			[JsonProperty(PropertyName = "Is it worth enabling an outline for the text?")]
			public bool Text_Outline_Enabled { get; set; }
			
			[JsonProperty(PropertyName = "Text_Outline_Color(Hex or RGBA)")]
			public string Text_Outline_Color { get; set; } = "#000000";
			
			public float Text_Outline_Transparency { get; set; } = 1f;
			public string Text_Outline_Distance { get; set; } = "0.75 0.75";
			public int SubText_Size { get; set; } = 12;
			
			[JsonProperty(PropertyName = "SubText_Color(Hex or RGBA)")]
			public string SubText_Color { get; set; } = "1 1 1 1";
			public string SubText_Font { get; set; } = "RobotoCondensed-Bold.ttf";
			
			[JsonProperty(PropertyName = "Is it worth enabling an outline for the sub text?")]
			public bool SubText_Outline_Enabled { get; set; }
			
			[JsonProperty(PropertyName = "SubText_Outline_Color(Hex or RGBA)")]
			public string SubText_Outline_Color { get; set; } = "0.5 0.6 0.7 0.5";
			
			public float SubText_Outline_Transparency { get; set; }
			public string SubText_Outline_Distance { get; set; } = "0.75 0.75";
		}
				
				private const ulong _bradleySkinId = 3074297551uL;
		
		private void ConfigHandcuffToggled()
        {
			if (_config.PreventHandcuffing)
				Subscribe(nameof(OnPlayerHandcuff));
			else
				Unsubscribe(nameof(OnPlayerHandcuff));
		}
		object OnEntityTakeDamage(HotAirBalloon balloon, HitInfo info) => HandleDamageToVehicle(balloon, info);
		
		private object HandleVehicleInteraction(BasePlayer player, BaseEntity vehicle, string textKey = "MsgVehicleCantInteract")
		{
            if (!IsEntityInPvP(player.userID, vehicle.net.ID.Value))
            {
                object result = null;
                if (_vehiclesList.TryGetValue(vehicle.net.ID.Value, out var vehicleData))
                    result = vehicleData.CanInteract(player);
                else if (vehicle.OwnerID.IsSteamId())
                    result = CanInteractSharedEntity(player, vehicle);
				
				if (result != null)
                    SendMessageText(player, lang.GetMessage(textKey, this, player.UserIDString));
                return result;
            }
            return null;
        }

        private void LoadPermissionsConfig()
        {
			_permissionsPath = $"{Name}{Path.DirectorySeparatorChar}PermissionConfig";
			if (Interface.Oxide.DataFileSystem.ExistsDatafile(_permissionsPath))
            {
                try { _permissionsConfig = Interface.Oxide.DataFileSystem.ReadObject<PermissionConfig>(_permissionsPath); }
                catch (Exception ex) { UnityEngine.Debug.LogException(ex); }
            }

            if (_permissionsConfig == null || _permissionsConfig.Version < _permissionsVersion)
            {
                if (_permissionsConfig != null)
                {
					string pathOld = $"{Name}{Path.DirectorySeparatorChar}_old_PermissionConfig({_permissionsConfig.Version})";
					PrintWarning($"Your settings version for permissions is outdated. The config file has been updated, and your old settings have been saved in {Interface.Oxide.DataDirectory}{Path.DirectorySeparatorChar}{pathOld}.json");
					Interface.Oxide.DataFileSystem.WriteObject(pathOld, _permissionsConfig);
				}
                _permissionsConfig = new PermissionConfig() { Version = _permissionsVersion };
            }
			
			if (_permissionsConfig.PermissionsList == null || !_permissionsConfig.PermissionsList.Any())
                _permissionsConfig.PermissionsList = new List<PvEPermission>() { new PvEPermission("realpve.default", false, false, 15, 1, 12, 0f, 1f, 1f, 1, 1f, 1, 1f), new PvEPermission("realpve.vip", true, true, 20, 2, 15, 450f, 0.9f, 0.9f, 2, 0.9f, 5, 0.9f) };
			else
            {
				PvEPermission perm;
				for (int i = 0; i < _permissionsConfig.PermissionsList.Count; i++)
                {
					perm = _permissionsConfig.PermissionsList[i];
					if (perm.Name.StartsWith("realpve", StringComparison.OrdinalIgnoreCase))
						perm.Name = perm.Name.ToLower();
					
					var values = (VehicleType[])Enum.GetValues(typeof(VehicleType));
					for (int j = 1; j < values.Length; j++)
					{
						VehicleType type = values[j];
						if (!perm.Allowed_Vehicles.ContainsKey(type))
							perm.Allowed_Vehicles[type] = new VehicleProperties();
					}
				}
			}
			
			SavePermissionsConfig();
		}

        
        object OnRidableAnimalClaim(RidableHorse horse, BasePlayer player) => _vehiclesList.TryGetValue(horse.net.ID.Value, out var vehicleData) ? vehicleData.AssignNewOwner(player, false) : null;

				private static RealPVE Instance { get; set; }
		
		object OnEntityTakeDamage(PlayerCorpse corpse, HitInfo info)
        {
			if (info == null || !corpse.playerSteamID.IsSteamId()) return null;
			switch (TryGetAttacker(info, out var initiator))
            {
                case AttackerIndex.BasePlayer:
					var attacker = (BasePlayer)initiator;
                    if (attacker.userID.IsSteamId() && !IsEntityInPvP(attacker.userID, corpse.net.ID.Value) && !UrishMumkin(attacker, corpse.playerSteamID))
                        info.Urma();
					break;
                case AttackerIndex.PlayerOwnerMissing:
                    info.Urma();
					break;
			}
            return null;
		}
		void OnEntitySpawned(MotorRowboat boat) => HandleVehicleSpawn(boat);
		
		private void ConfigSafeZoneLogoutDeathToggled()
        {
            if (_config.PreventSafeZoneLogoutDeath)
				Subscribe(nameof(OnPlayerSleep));
			else
				Unsubscribe(nameof(OnPlayerSleep));
		}
		private Dictionary<string, MarkersPvP> _pvpMarkers = new Dictionary<string, MarkersPvP>();
        
        		void OnEntitySpawned(RidableHorse horse) => HandleVehicleSpawn(horse);
		
		private class RBConfig
        {
			[JsonProperty(PropertyName = "Is RaidableBases enabled?")]
			public bool IsEnabled = true;
			
			[JsonProperty(PropertyName = "RaidableBases Console command")]
			public string ConsoleCommand = "rbevent";
			
			[JsonProperty(PropertyName = "Is it worth enabling forced auto-buy for raidable bases where the final price is greater than 0?")]
            public bool AutoBuy = false;
			
			[JsonProperty(PropertyName = "Settings for the RaidableBases. WARNING: The first value will be used as a template for new levels")]
			public List<RBSettings> Settings = null;
			
			public Oxide.Core.VersionNumber Version;
		}

		public static bool ModChangePose(Mannequin __instance, BaseEntity.RPCMessage msg)
        {
			var player = msg.player;
			if (Instance == null || __instance == null || player == null)
				return true;
			if (Instance.CanInteractMannequin(__instance, player))
            {
                int num = __instance.PoseIndex + 1;
                __instance.PoseIndex = num >= __instance.AvailablePoses.Length ? 0 : num;
                if (__instance.ChangePoseSound.isValid)
                    Effect.server.Run(__instance.ChangePoseSound.resourcePath, __instance.transform.position);
            }
            return false;
		}
		void OnEntitySpawned(RHIB rhib) => HandleVehicleSpawn(rhib);
		void OnExplosiveDropped(BasePlayer player, BaseEntity entity, ThrownWeapon item) => entity.OwnerID = player.userID;
				
		        private const string _uiVehiclePanel = "RealPVE_VehiclePanel";
		
		public class NewbieItem
		{
			public string ShortName { get; set; }
			public int Slot { get; set; }
			public int Amount { get; set; }
			public ulong SkinID { get; set; }
			public string Text { get; set; }
			
			public NewbieItem() {}
			public NewbieItem(string name, int slot = 0, int amount = 1, ulong skinID = 0uL, string text = "")
			{
				ShortName = name;
				Slot = slot;
				Amount = amount;
				SkinID = skinID;
				Text = text;
			}
		}
		private Dictionary<string, HashSet<string>> _pvpChangedMonuments = new Dictionary<string, HashSet<string>>();
        		
				private void ShowVehiclePanels(BasePlayer player, VehicleData vehicleData)
        {
            CuiElementContainer container;
            switch (vehicleData.Type)
            {
                case VehicleType.Horse:
                    container = GetVehicleHorsePanel(player.UserIDString, vehicleData);
                    break;
                case VehicleType.Car:
                    container = GetVehicleCarPanel(player.UserIDString, vehicleData);
                    break;
                default:
                    container = GetVehicleDefaultPanel(player.UserIDString, vehicleData);
                    break;
            }
            DestroyUI(player, _uiVehiclePanel);
            CuiHelper.AddUi(player, container);
			if (!_playerUI.TryGetValue(player.userID, out var uiList))
				_playerUI[player.userID] = uiList = new HashSet<string>();
			uiList.Add(_uiVehiclePanel);
		}
		
		private object HandleVehicleSeat(BasePlayer player, BaseMountable mount)
        {
            if (!mount.isMobile || IsEntityInPvP(player.userID, mount.net.ID.Value)) return null;
            object result = null;
            if (mount.mountPose != PlayerModel.MountPoses.Sit_Crane)
            {
                var parent = mount.GetParentEntity();
                if (parent.IsValid())
                {
                    ulong vehicleID = 0uL;
                    if (parent is BaseVehicleModule module)
                        vehicleID = module.VehicleParent()?.net.ID.Value ?? 0uL;
                    else
                        vehicleID = parent.net.ID.Value;

                    if (_vehiclesList.TryGetValue(vehicleID, out var vehicleData))
                    {
                        result = vehicleData.CanInteract(player, false) == null ||
                            (parent is not VehicleModuleCamper && _allowSitVehicles.Contains(vehicleData.Type) && !_driverSit.Contains((int)mount.mountPose)) ? null : false;
                    }
                }
            }
            else if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
                result = monumentData.CanLoot(player);

            if (result != null)
                SendMessageText(player, lang.GetMessage("MsgVehicleCantSeat", this, player.UserIDString));
            return result;
        }
        
        		object OnEntityTakeDamage(ResourceEntity resource, HitInfo info)
        {
			if (_gatherFlags == BuildingGatherRestrictions.None || info == null) return null;
			switch (resource.resourceDispenser?.gatherType ?? ResourceDispenser.GatherType.UNSET)
            {
				case ResourceDispenser.GatherType.Tree:
					
					if ((_gatherFlags & BuildingGatherRestrictions.Trees) == 0)
						return null;
					break;
				case ResourceDispenser.GatherType.Ore:
					if ((_gatherFlags & BuildingGatherRestrictions.Ores) == 0)
						return null;
					break;
				case ResourceDispenser.GatherType.Flesh:
					if ((_gatherFlags & BuildingGatherRestrictions.Flesh) == 0)
						return null;
					break;
				default:
					return null;
			}
			
			if (TryGetAttacker(info, out var initiator) != AttackerIndex.BasePlayer) return null;
			var attacker = (BasePlayer)initiator;
			if (attacker.userID.IsSteamId() && !_unrestrictedLooters.Contains(attacker.userID) && !IsEntityInPvP(attacker.userID, resource.net.ID.Value))
			{
				object result = attacker.BinoMumkin();
				if (result != null)
				{
					SendMessageText(attacker, lang.GetMessage("MsgCantGatherInBase", this, attacker.UserIDString));
					return true;
				}
				
            }
            return null;
        }
		object OnEntityTakeDamage(BaseNPC2 npc, HitInfo info) => null;
		public static bool ModSwapMannequin(Mannequin __instance, BaseEntity.RPCMessage msg)
        {
			var player = msg.player;
			if (Instance == null || __instance == null || __instance.IsEquipping() || player == null || player.IsDead())
				return true;
			if (Instance.CanInteractMannequin(__instance, player) && Mannequin.SwapPlayerInventoryWithContainer(player, __instance.inventory, __instance.GetDropPosition(), __instance.GetDropVelocity(), __instance.FilterItems))
            {
                if (__instance.EquipSound != null)
                    Effect.server.Run(__instance.EquipSound.resourcePath, player, StringPool.Get("spine3"), Vector3.zero, Vector3.zero);
                using (var setFlags = __instance.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
                {
                    setFlags.Set(BaseEntity.Flags.Reserved1, true);
                }
                __instance.Invoke(__instance.ClearEquipping, 1.5f);
            }
            return false;
		}
		private Dictionary<NetworkableId, EventData> _eventScientistsList = new Dictionary<NetworkableId, EventData>();
		object OnPortalUse(BasePlayer player, XmasDungeon xmas) => HandlePortalUse(player, xmas);
		
		private static readonly string[] _cmdKeysAdminConfig = { "forcepve", "forcetutorial", "gametips", "perm_limits", "safe_death", "plant_privilege", "heli_damage", "handcuffs", "portals", "backpack_drop",
			"laptop_damage", "recycler_safezone", "item_pickup", "safe_sleep", "resource_privilege", "priceformat", "vehicle_marker_time", "antisleeper", "randomraids", "teamff",
			"pvpmarkers", "pvpmarkersname" };
		
        		private static readonly string[] _cmdKeysAdmin = { "autobuy", "config", "loot", "monument", "perm", "pickup", "share", "tc", "vehicle" };
        
        		private void SendPvPBar(BasePlayer player, string zoneID)
        {
			if (!_statusIsLoaded) return;
			
			Dictionary<int, object> parameters;
			if (_monumentsList.TryGetValue(zoneID, out var monumentData))
			{
				parameters = new Dictionary<int, object>(monumentData.StatusBar)
				{
					{ 15, monumentData.GetMonumentName(player.userID) },
					{ 22, lang.GetMessage("MsgMonumentIsPvP", this, player.UserIDString) }
				};
			}
			else if (_rbList.TryGetValue(zoneID, out var rbData))
			{
				parameters = new Dictionary<int, object>(rbData.StatusBar)
                {
                    { 15, string.Format(lang.GetMessage("MsgRaidableBasesBarText", this, player.UserIDString), lang.GetMessage(rbData.Settings.TextKey, this, player.UserIDString)) },
                    { 22, lang.GetMessage("MsgRaidableBasesIsPvP", Instance, player.UserIDString) }
                };
            }
			else
            {
				parameters = new Dictionary<int, object>(_pvpBar)
				{
					{ 0, zoneID }
				};
				
				string text = lang.GetMessage("MsgPvPBar", this, player.UserIDString);
				var text2 = Interface.CallHook(Hooks_OnZoneStatusText, player, zoneID) as string;
				if (text2 != null)
				{
					parameters[15] = text2;
					parameters[22] = text;
				}
				else
					parameters[15] = text;
			}

            AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
		}

		void OnCargoWatcherDeleted(string monumentID)
		{
			if (_monumentsList.TryGetValue(monumentID, out var monumentData))
				monumentData.Destroy(monumentID);
		}
        
        		private const ulong _rbPluginID = 14922524uL;
		object OnHotAirBalloonToggle(HotAirBalloon balloon, BasePlayer driver) => HandleVehicleInteraction(driver, balloon, "MsgVehicleCantBalloonToggle");
		private void OnTeamUpdated(BasePlayer player)
		{
			NextTick(() =>
            {
                if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
					monumentData.OnTeamUpdated(player);
                if (TryGetRaidBaseByUser(player.userID, out var rbData))
                    rbData.OnTeamUpdated(player);
            });
        }
		object OnEntityTakeDamage(MotorRowboat boat, HitInfo info) => HandleDamageToVehicle(boat, info);
		private void SavePermissionsConfig() => Interface.Oxide.DataFileSystem.WriteObject(_permissionsPath, _permissionsConfig);
		
		private static bool IsEntitySharedToPlayer(ulong userID, ulong netID)
        {
			if (_sharedEntities.TryGetValue(netID, out var sharedEntity))
            {
                if (sharedEntity.ForEveryOne)
                {
                    if (!sharedEntity.PlayersList.Contains(userID))
                        return true;
                }
                else if (sharedEntity.PlayersList.Contains(userID))
                    return true;
            }
            return false;
        }
		void OnEntityKill(MotorRowboat boat) => HandleVehicleKill(boat);
		
		private void UpdateTurretTrigger(AutoTurret turret)
		{
			if (turret != null)
			{
				turret.SetTarget(null);
				UpdateTurretTrigger(turret.targetTrigger);
			}
		}
		private static bool IsEntityInPvP(ulong a, ulong b) => _pvpPlayers.ContainsKey(a) && _pvpEntities.Contains(b);
		private static Dictionary<ulong, PlayerData> _playersList;
		
		private void UpdateTurretTrigger(TargetTrigger trigger)
        {
			if (trigger == null) return;
			BaseEntity entity;
			if (trigger.entityContents != null)
            {
				var list = trigger.entityContents.ToArray();
				for (int i = list.Length - 1; i >= 0; i--)
                {
					entity = list[i];
					if (entity.IsValid())
						trigger.OnEntityLeave(entity);
				}
			}
			if (trigger.contents != null)
            {
                foreach (var gameObject in trigger.contents)
                {
					entity = gameObject?.ToBaseEntity();
					if (entity != null)
                        trigger.OnEntityEnter(entity);
                }
            }
        }
		private static Dictionary<ulong, VehicleData> _vehiclesList;
		
		object OnZoneStatusText(BasePlayer player, string zoneID)
		{
			switch (zoneID)
            {
                case "SurvivalArena":
                    return lang.GetMessage("MsgSurvivalArena", this, player.UserIDString);
                default:
                    return null;
            }
		}
        
        		public static IEnumerable<CodeInstruction> ModMirror(IEnumerable<CodeInstruction> instructions, ILGenerator generator)
        {
			var result = new List<CodeInstruction>(instructions);
			CodeInstruction code;
			for (int i = 0; i < result.Count; i++)
            {
                code = result[i];
                if (code.opcode == OpCodes.Ldsfld && code.operand is FieldInfo field && field.Name == "pve")
                {
                    code = result[i + 1];
                    if (code.opcode == OpCodes.Brfalse_S)
                    {
                        result[i] = new CodeInstruction(OpCodes.Nop);
                        result[i + 1] = new CodeInstruction(OpCodes.Br_S, (Label)code.operand);
                    }
                    break;
                }
            }
			return result;
        }
		
		private void VerifyBuildingPrivilegeGathering()
        {
			_gatherFlags = BuildingGatherRestrictions.None;
			foreach (var v in _config.BuildingPrivilegeGathering)
            {
                switch (v)
                {
                    case 1:
                        _gatherFlags |= BuildingGatherRestrictions.Trees;
                        break;
                    case 2:
                        _gatherFlags |= BuildingGatherRestrictions.Ores;
                        break;
                    case 3:
                        _gatherFlags |= BuildingGatherRestrictions.Flesh;
                        break;
                }
            }
        }
		
		public static void ModNaturalBeehiveDied(NaturalBeehive __instance, HitInfo info)
        {
            var attacker = info?.InitiatorPlayer;
            if (attacker.IsValid() && __instance.IsValid())
                __instance.OwnerID = attacker.userID;
        }

        private void ConfigPortalsToggled()
        {
            if (_config.AssignPortals)
            {
                Subscribe(nameof(OnPortalUse));
                Subscribe(nameof(OnPortalUsed));
            }
            else
            {
                Unsubscribe(nameof(OnPortalUse));
                Unsubscribe(nameof(OnPortalUsed));
            }
        }
		public static Dictionary<ulong, RRData> _rrallPatrols = new Dictionary<ulong, RRData>();
		void OnEntityKill(HotAirBalloon balloon) => HandleVehicleKill(balloon);
				
				private string _dataTeamsPath = string.Empty;
		
		
		object OnVehicleModuleMove(BaseVehicleModule moduleForItem, ModularCar modularCar, BasePlayer player)
		{
			if (_vehiclesList.TryGetValue(modularCar.net.ID.Value, out var vehicleData) && vehicleData.OwnerID != 0uL)
			{
				if (!vehicleData.IsOwner(player.userID))
					return false;
				NextTick(() => { if (!modularCar.HasDriverMountPoints()) vehicleData.RemoveOwnerServerSide(); });
			}
			return null;
		}
		
		object OnEntityTakeDamage(BaseAnimalNPC npc, HitInfo info) => null;
        
        		private void ToggleImageLib(bool isLoaded)
        {
            _imgLibIsLoaded = isLoaded;
            if (_imgLibIsLoaded)
            {
				var imgList = new Dictionary<string, string>
				{
					{ MonumentOfferUI, "https://i.imgur.com/4Adzkb8.png" },
					{ EventOfferUI, "https://i.imgur.com/4Adzkb8.png" },
					{ RBOfferUI, "https://i.imgur.com/4Adzkb8.png" }
				};

                BarSettings barSettings = _config.BarPvP;
                if (string.IsNullOrWhiteSpace(barSettings.Image_Sprite) && string.IsNullOrWhiteSpace(barSettings.Image_Local) && barSettings.Image_Url.StartsWithAny(HttpScheme))
                    imgList.Add(Bar_PvP, barSettings.Image_Url);
                foreach (var kvp in _monumentsConfig.MonumentsSettings)
                {
                    barSettings = kvp.Value.Bar;
                    if (string.IsNullOrWhiteSpace(barSettings.Image_Sprite) && string.IsNullOrWhiteSpace(barSettings.Image_Local) && barSettings.Image_Url.StartsWithAny(HttpScheme))
                        imgList.Add($"{StatusBarID}{kvp.Key}", barSettings.Image_Url);
                }
                foreach (var rbSettings in _rbsConfig.Settings)
                {
                    barSettings = rbSettings.Bar;
                    if (string.IsNullOrWhiteSpace(barSettings.Image_Sprite) && string.IsNullOrWhiteSpace(barSettings.Image_Local) && barSettings.Image_Url.StartsWithAny(HttpScheme))
                        imgList.Add(rbSettings.ImgLibId, barSettings.Image_Url);
                }
				if (imgList.Any())
					ImageLibrary?.Call("ImportImageList", Name, imgList, 0uL, true);
			}
			
			foreach (var monumentData in _monumentsList.Values)
                monumentData.UpdateBars();
            UpdatePvPBars();
            foreach (var rbData in _rbList.Values)
                rbData.UpdateBars();
		}
		
		object CanLootEntity(BasePlayer player, BaseOven oven)
		{
            
			if (_unrestrictedLooters.Contains(player.userID)) return AdminOpenLoot(player, oven);
			if (IsEntityInPvP(player.userID, oven.net.ID.Value)) return null;
			if (oven.GetParentEntity() is BaseVehicleModule module)
				return CanLootCar(player, module.VehicleParent());
			return CanLootStorage(player, oven, true);
		}
		object CanLootEntity(BasePlayer player, Composter composter) => CanLootStorage(player, composter);
		
                private static Configuration _config;
		private string GetPlayerMonument(ulong userID) => (string)(MonumentsWatcher?.Call(MonumentGetPlayerMonument, userID) ?? string.Empty);

		void OnCrateHack(HackableLockedCrate crate)
		{
			NextTick(() =>
            {
				if (!crate.IsValid()) return;
				crate.OwnerID = crate.originalHackerPlayerId;
				if (crate.OwnerID.IsSteamId() && !IsEntityInPvP(crate.net.ID.Value))
					crate.hackSeconds += GetHackableCrateSkip(crate.OwnerID.ToString());
				if (HackableLockedCrate.requiredHackSeconds > crate.hackSeconds && _monumentsList.TryGetValue(GetEntityMonument(crate), out var monumentData))
					monumentData.TrySetLootTimer(HackableLockedCrate.requiredHackSeconds - crate.hackSeconds);
			});
		}
		
		private void LoadMonumentsConfig()
        {
			_monumentsPath = $"{Name}{Path.DirectorySeparatorChar}MonumentsConfig";
			string uiOfferPath = $"{Name}{Path.DirectorySeparatorChar}UI{Path.DirectorySeparatorChar}MonumentsOffer";
			List<CuiElement> uiList = null;
			if (Interface.Oxide.DataFileSystem.ExistsDatafile(_monumentsPath))
            {
				try
				{
					_monumentsConfig = Interface.Oxide.DataFileSystem.ReadObject<MonumentConfig>(_monumentsPath);
					uiList = Interface.Oxide.DataFileSystem.ReadObject<List<CuiElement>>(uiOfferPath);
				}
				catch (Exception ex) { UnityEngine.Debug.LogException(ex); }
			}
			
			if (_monumentsConfig == null || _monumentsConfig.Version < _monumentsVersion)
            {
                if (_monumentsConfig != null)
                {
					string pathOld = $"{Name}{Path.DirectorySeparatorChar}_old_MonumentsConfig({_monumentsConfig.Version})";
					PrintWarning($"Your settings version for monuments is outdated. The config file has been updated, and your old settings have been saved in {Interface.Oxide.DataDirectory}{Path.DirectorySeparatorChar}{pathOld}.json");
					Interface.Oxide.DataFileSystem.WriteObject(pathOld, _monumentsConfig);
				}
				_monumentsConfig = new MonumentConfig() { Version = _monumentsVersion };
            }
			
			_monumentsConfig.TimeToComeBack = Mathf.Clamp(_monumentsConfig.TimeToComeBack, 3f, 60f);
			if (_monumentsConfig.MapCircle_Free == null)
				_monumentsConfig.MapCircle_Free = new MapCircleStyle(Color.green, Color.black);
			else
				_monumentsConfig.MapCircle_Free.VerifyValues();
			MonumentData.MapCircle_Free = _monumentsConfig.MapCircle_Free;
			if (_monumentsConfig.MapCircle_Owned == null)
                _monumentsConfig.MapCircle_Owned = new MapCircleStyle(Color.yellow, Color.black);
			else
				_monumentsConfig.MapCircle_Owned.VerifyValues();
			MonumentData.MapCircle_Owned = _monumentsConfig.MapCircle_Owned;
			if (_monumentsConfig.MapCircle_PvP == null)
				_monumentsConfig.MapCircle_PvP = new MapCircleStyle(Color.red, Color.black);
			else
				_monumentsConfig.MapCircle_PvP.VerifyValues();
			MonumentData.MapCircle_PvP = _monumentsConfig.MapCircle_PvP;
			if (_monumentsConfig.TrackedCategories == null)
                _monumentsConfig.TrackedCategories = new string[] { "RadTown", "RadTownWater", "RadTownSmall", "TunnelStation", "Custom" };
            if (_monumentsConfig.IgnoredNames == null)
                _monumentsConfig.IgnoredNames = new string[] { "example" };
            if (_monumentsConfig.MonumentsSettings == null)
                _monumentsConfig.MonumentsSettings = new Dictionary<string, MonumentSettings>();
			if (!_monumentsConfig.MonumentsSettings.ContainsKey("CargoShip"))
				_monumentsConfig.MonumentsSettings["CargoShip"] = new MonumentSettings("CargoShip", "RadTownWater");
			
			if (uiList == null || !uiList.Any())
            {
                uiList = GetDefaultClaimOffer();
                Interface.Oxide.DataFileSystem.WriteObject(uiOfferPath, uiList);
            }
            _monumentsUiOffer = ReplacePlaceholders(CuiHelper.ToJson(uiList), MonumentOfferUI);
			
			SaveMonumentsConfig();
		}

        private CuiElementContainer GetVehicleHorsePanel(string userID, VehicleData vehicleData)
        {
            var container = new CuiElementContainer();
            container.Add(new CuiPanel
            {
                RectTransform = { AnchorMin = "0.5 0", AnchorMax = "0.5 0", OffsetMin = "400 176", OffsetMax = "572 196" },
                Image = { Color = "0 0 0 0" }
            }, "Overlay", _uiVehiclePanel);
            if (vehicleData.OwnerID == 0uL || vehicleData.IsOwner(userID))
            {
                container.Add(new CuiButton
                {
                    Text =
                    {
                        Text = lang.GetMessage(vehicleData.OwnerID == 0uL ? "MsgVehicleDialogLink" : "MsgVehicleDialogUnLink", this,  userID),
                        Font = "RobotoCondensed-Regular.ttf",
                        FontSize = 12,
                        Color = WhiteColor,
                        Align = TextAnchor.MiddleCenter
                    },
                    Button =
                    {
                        Command = $"{_commandUI} vehicle {(vehicleData.OwnerID == 0 ? "link" : "unlink")} {vehicleData.ID}",
                        Color = vehicleData.OwnerID == 0 ? "0.41 0.55 0.41 0.8" : "1 0.4 0.4 0.8"
                    },
                    RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 2", OffsetMax = "-2 -2" }
                }, _uiVehiclePanel);
            }
            return container;
        }
		
		private int CountVehiclesByType(ulong userID, VehicleType type)
        {
            int result = 0;
            foreach (var vehicleData in _vehiclesList.Values)
            {
                if (vehicleData.OwnerID == userID && vehicleData.Type == type)
                    result++;
            }
            return result;
        }
		private object HandleContainerToggle(BasePlayer player, StorageContainer container)
		{
			if (player.IsValid() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, container.net.ID.Value))
				return CanLootStorage(player, container, true);
			return null;
		}
		
		private class VanillaEventsConfig
        {
			[JsonProperty(PropertyName = "Is it worth enabling forced auto-buy for vanilla events where the final price is greater than 0?")]
			public bool AutoBuy = false;
			
			[JsonProperty(PropertyName = "Settings for the PatrolHelicopter events")]
            public EventSettings PatrolHelicopter = null;

            [JsonProperty(PropertyName = "Settings for the BradleyAPC events")]
            public EventSettings BradleyAPC = null;
			
			public Oxide.Core.VersionNumber Version;
		}
		private readonly VersionNumber _rbsVersion = new VersionNumber(0, 1, 2);
        void OnSupplyDropDropped(SupplyDrop supplyDrop, CargoPlane plane) => supplyDrop.OwnerID = plane.OwnerID;
		
		private string _dataPlayersPath = string.Empty;
		
		void OnFireBallDamage(FireBall fireBall, BaseCombatEntity entity, HitInfo info)
        {
            if (info != null && entity.IsValid() && entity.OwnerID.IsSteamId() &&
				!fireBall.OwnerID.IsSteamId() && (!info.Initiator.IsValid() || info.Initiator is FireBall) && !info.WeaponPrefab.IsValid())
			{
				info.Urma();
			}
		}
		
		private void Command_AdminLoot(IPlayer player, string[] args)
        {
            if (string.IsNullOrWhiteSpace(args[0]) || args[0].Equals("help", StringComparison.OrdinalIgnoreCase))
                player.Reply(lang.GetMessage("CmdAdminLoot", this, player.Id));
            else if (args[0].Equals("clear", StringComparison.OrdinalIgnoreCase))
            {
                foreach (var playerData in _playersList.Values)
                    playerData.AdminLoot = false;
                int total = _unrestrictedLooters.Count;
                _unrestrictedLooters.Clear();
                SaveData(_dataPlayersPath, _playersList);
                SendMessageText(player, string.Format(lang.GetMessage("CmdAdminLootClear", this, player.Id), total), false);
            }
            else if (args[0].Equals("self", StringComparison.OrdinalIgnoreCase) || args[0].Equals(player.Id, StringComparison.OrdinalIgnoreCase))
            {
                if (player.IsServer)
                    player.Reply("This command is only applicable to players!");
                else
                {
                    GetOrCreatePlayerData(player.Id, out var playerData);
                    playerData.AdminLoot = !playerData.AdminLoot;
                    if (playerData.AdminLoot)
                    {
                        _unrestrictedLooters.Add(playerData.UserID);
                        SendMessageText(player, lang.GetMessage("CmdAdminLootEnabled", this, player.Id), false);
                    }
                    else
                    {
                        _unrestrictedLooters.Remove(playerData.UserID);
                        SendMessageText(player, lang.GetMessage("CmdAdminLootDisabled", this, player.Id));
                    }
                }
            }
            else if (TryGetPlayer(player, args[0], out var tPlayer))
			{
				GetOrCreatePlayerData(tPlayer.Id, out var playerData);
                playerData.AdminLoot = !playerData.AdminLoot;
                if (playerData.AdminLoot)
                {
                    _unrestrictedLooters.Add(playerData.UserID);
                    if (tPlayer.IsConnected)
                        SendMessageText(tPlayer, lang.GetMessage("CmdAdminLootEnabled", this, tPlayer.Id), false);
                    SendMessageText(player, string.Format(lang.GetMessage("CmdAdminLootPlayerEnabled", this, player.Id), tPlayer.Name), false);
                }
                else
                {
                    _unrestrictedLooters.Remove(playerData.UserID);
                    if (tPlayer.IsConnected)
                        SendMessageText(tPlayer, lang.GetMessage("CmdAdminLootDisabled", this, tPlayer.Id));
                    SendMessageText(player, string.Format(lang.GetMessage("CmdAdminLootPlayerDisabled", this, player.Id), tPlayer.Name));
                }
            }
        }
				
		        void Unload()
        {
			_harmony?.UnpatchAll(IdForHarmony);
			SaveData(_dataPlayersPath, _playersList);
			SaveData(_dataVehiclesPath, _vehiclesList);
			SaveData(_dataTeamsPath, _teamsList);
			UnityEngine.Application.logMessageReceived += Facepunch.Output.LogHandler;
			UnityEngine.Application.logMessageReceived -= HookConflict;
			ConVar.Server.max_sleeping_bags = _defaultBeds;
			LegacyShelter.max_shelters = _defaultShelters;
			ConVar.Sentry.maxinterference = _defaultTurrets;
			foreach (var entity in BaseNetworkable.serverEntities)
            {
				if (!entity.IsValid()) continue;
				if (entity is Recycler recycler)
					recycler.UpdateInSafeZone();
				else if (entity is AutoTurret turret)
					UpdateTurretTrigger(turret);
				else if (entity is FlameTurret flameTurret)
                    UpdateTurretTrigger(flameTurret);
                else if (entity is GunTrap gunTrap)
                    UpdateTurretTrigger(gunTrap);
				else if (entity is DecayEntity decayEntity)
					RemoveDemolishable(decayEntity);
			}
			if (_updatesTimer != null)
                _updatesTimer.Destroy();
			foreach (var player in BasePlayer.activePlayerList)
			{
				if (!_playerUI.TryGetValue(player.userID, out var uiNames)) continue;
				foreach (var uiName in uiNames)
					CuiHelper.DestroyUi(player, uiName);
			}
			_playerUI = null;
			if (_watcherIsLoaded)
			{
				foreach (var monumentData in _monumentsList.Values)
					monumentData.Destroy();
				_monumentsList.Clear();
            }
			foreach (var markersPvP in _pvpMarkers.Values)
				markersPvP.Destroy();
			_pvpMarkers.Clear();
			if (RaidableBases != null && RaidableBases.IsLoaded)
			{
				foreach (var rbData in _rbList.Values.ToList())
					rbData.Destroy();
			}
			_pvpPlayers = null;
			_pvpEntities = null;
			_unrestrictedLooters = null;
			_autoBuyPlayers = null;
			_pickupPlayers = null;
			_sharedEntities = null;
			_playersList = null;
			_vehiclesList = null;
            _teamsList = null;
			_eventsList = null;
			Instance = null;
			_config = null;
			_permissionsConfig = null;
			_monumentsConfig = null;
			_vanillaEventsConfig = null;
			_vanillaEventsUiOffer = null;
			_rbsConfig = null;
			_rbsUiOffer = null;
			_newbieConfig = null;
		}
		
		void OnPlayerExitPVP(BasePlayer player, string zoneID, float delay = 0f)
        {
			if (!_pvpPlayers.TryGetValue(player.userID, out var playerPvP)) return;
			int index = playerPvP.ActiveZones.IndexOf(zoneID);
			if (!playerPvP.ActiveZones.Remove(zoneID)) return;
			
			if (playerPvP.ActiveZones.Any())
				playerPvP.LastZone = playerPvP.ActiveZones[^1];
			else
			{
				delay = (float)(Interface.CallHook(Hooks_OnPlayerPVPDelay, player, delay, zoneID) ?? delay);
				if (delay > 0f)
                {
					playerPvP.DelayEnd = _unixSeconds + delay;
					Interface.CallHook(Hooks_OnPlayerPVPDelayed, player, delay, zoneID);
				}
			}
			
			if (index != playerPvP.ActiveZones.Count || !player.IsConnected) return;
			
			DestroyBar(player.userID, zoneID);
			if (playerPvP.ActiveZones.Any())
				SendPvPBar(player, playerPvP.LastZone);
			else if (delay > 0f)
				SendPvPDelayBar(player, zoneID, delay, playerPvP.DelayEnd);
		}
		
		void OnEntitySpawned(PatrolHelicopter patrol)
        {
			if (_vanillaEventsConfig.PatrolHelicopter.IsEnabled)
				NextTick(() => { new EventData(patrol); });
		}

        public static VehiclePrivilege GetVehiclePrivilege(BasePlayer player)
        {
            var obb = new OBB(player.transform.position, player.transform.lossyScale, player.transform.rotation, player.bounds);
			Tugboat other = null;
			var tugList = Pool.Get<List<Tugboat>>();
            Vis.Entities(obb.position, 1f, tugList);
            for (int i = 0; i < tugList.Count; i++)
            {
                var tug = tugList[i];
                if (tug.isServer != player.isServer || tug.children == null || !tug.IsOlderThan(other) || obb.Distance(tug.WorldSpaceBounds()) > 3f) continue;
				other = tug;
			}
            Pool.FreeUnmanaged(ref tugList);
			return other?.children.Cast<VehiclePrivilege>().FirstOrDefault();
		}
		public static readonly int[] _driverSit = new int[] { 1, 5, 9, 11, 26 };
		object OnRackedWeaponLoad(Item item, ItemDefinition itemDefinition, BasePlayer player, WeaponRack rack) => CanLootWeaponRack(player, rack);
		
		void OnFriendAdded(string userID, string friendID) => OnFriendUpdated(userID, friendID);
		
		void OnPluginLoaded(Plugin plugin)
		{
			if (plugin == ImageLibrary)
				ToggleImageLib(true);
			else if (plugin == Economics)
				_economicsIsLoaded = Economics != null && Economics.IsLoaded;
			else if (plugin == RaidableBases)
				ToggleRaidableBases(true);
			else if (plugin == RandomRaids)
				ToggleRandomRaids(true);
			else if (plugin == Friends)
				_friendsIsLoaded = Friends != null && Friends.IsLoaded;
			else if (plugin == DynamicPVP)
				ToggleDynamicPVP(true);
		}
        

        void OnEntityKill(RidableHorse horse) => HandleVehicleKill(horse);
		object OnStructureRotate(BaseCombatEntity entity, BasePlayer player) => !entity.OwnerID.IsSteamId() || IsEntityInPvP(player.userID, entity.net.ID.Value) ? null : player.TasirMumkin(entity.OwnerID);
		private bool IsPlayerInMonument(string monumentID, BasePlayer player) => (bool)(MonumentsWatcher?.Call(MonumentIsPlayerInMonument, monumentID, player) ?? false);
		
		private bool IsMonumentCargoValid(string monumentID)
		{
			string[] parts = monumentID.Split('_');
			if (parts.Length > 0 && ulong.TryParse(parts[^1], out ulong cargoID) && BaseNetworkable.serverEntities.Find(new NetworkableId(cargoID)) is CargoShip cargoShip &&
				cargoShip.IsValid() && cargoShip.skinID == 0uL)
				return true;
			return false;
		}
		private static Dictionary<ulong, PlayerPvP> _pvpPlayers;
        
        		public static bool ModStartDemolishable(DecayEntity __instance)
        {
            if (__instance.IsValid() && !IsEntityInPvP(__instance.net.ID.Value))
            {
				Instance.ApplyDemolishable(__instance);
				return false;
            }
            return true;
        }
		object CanLootEntity(BasePlayer player, PlanterBox planter) => CanLootStorage(player, planter);
		private void CheckIfPlaced(BasePlayer player, int oldTotal, int limit, bool sleepingBag)
		{
			if (player.IsValid())
			{
				int newTotal = sleepingBag ? CountBeds(player.userID) : LegacyShelter.GetShelterCount(player.userID);
				if (oldTotal < newTotal)
					player.ShowToast(GameTip.Styles.Blue_Long, sleepingBag ? SleepingBag.bagLimitPhrase : LegacyShelter.shelterLimitPhrase, false, newTotal.ToString(), limit.ToString());
			}
		}
		
		void OnPlayerLootEnd(PlayerLoot inventory)
		{
			if (inventory.entitySource is RidableHorse && inventory.baseEntity is BasePlayer player)
				DestroyVehiclePanels(player);
		}
		
		object OnInterferenceUpdate(AutoTurret turret)
		{
			if (!turret.OwnerID.IsSteamId()) return null;
            int limit = GetTurretsLimit(turret.OwnerID.ToString());
			if (limit < 0)
				using (var setFlags = turret.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
				{
				    setFlags.Set(BaseEntity.Flags.OnFire, true);
				}
			else
			{
				int num = 0;
                foreach (var nearbyTurret in turret.nearbyTurrets)
                {
                    if (!nearbyTurret.isClient && nearbyTurret.IsValid() && nearbyTurret.gameObject.activeSelf && !nearbyTurret.EqualNetID(turret.net.ID) && nearbyTurret.IsOn() && !nearbyTurret.HasInterference())
                        num++;
                }
                using (var setFlags = turret.StartSetFlags(BaseEntity.FlagsUpdateMode.SendNetworkUpdate_Flags))
                {
                    setFlags.Set(BaseEntity.Flags.OnFire, num >= limit);
                }
            }
			return true;
		}

		object CanLootEntity(BasePlayer player, StorageContainer container)
        {
			if (_unrestrictedLooters.Contains(player.userID)) return AdminOpenLoot(player, container);
			if (IsEntityInPvP(player.userID, container.net.ID.Value)) return null;
			var parentEnt = container.GetParentEntity();
			if (parentEnt != null)
            {
				ulong parentID = parentEnt.net.ID.Value;
				if (parentEnt is BaseVehicleModule module)
					parentID = module.VehicleParent()?.net.ID.Value ?? parentID;
				if (_vehiclesList.TryGetValue(parentID, out var vehicleData))
                {
                    object result = vehicleData.CanLoot(player);
					if (result != null)
                        SendMessageText(player, lang.GetMessage("MsgVehicleCantInteract", this, player.UserIDString));
                    return result;
                }
            }
			return CanLootStorage(player, container, true);
		}
		
		object OnEntityEnter(TriggerMagnet trigger, ModularCar modularCar)
        {
			if (_vehiclesList.TryGetValue(modularCar.net.ID.Value, out var vehicleData) && vehicleData.OwnerID != 0uL)
			{
				var driver = (trigger.GetComponentInParent<BaseMagnet>()?.entityOwner as MagnetCrane)?.GetDriver();
				if (driver != null && !vehicleData.IsOwner(driver.userID))
				{
					trigger.entityContents.Remove(modularCar);
					return true;
				}
			}
			return null;
		}
		
		private void LoadBeachConfig()
		{
			_newbiePath = $"{Name}{Path.DirectorySeparatorChar}NewbieConfig";
			if (Interface.Oxide.DataFileSystem.ExistsDatafile(_newbiePath))
			{
				try { _newbieConfig = Interface.Oxide.DataFileSystem.ReadObject<NewbieConfig>(_newbiePath); }
				catch (Exception ex) { UnityEngine.Debug.LogException(ex); }
			}
			
			if (_newbieConfig == null || _newbieConfig.Version < _beachVersion)
			{
				if (_newbieConfig != null)
				{
					string pathOld = $"{Name}{Path.DirectorySeparatorChar}_old_NewbieConfig({_newbieConfig.Version})";
					PrintWarning($"Your settings version for starter kits is outdated. The config file has been updated, and your old settings have been saved in {Interface.Oxide.DataDirectory}{Path.DirectorySeparatorChar}{pathOld}.json");
					Interface.Oxide.DataFileSystem.WriteObject(pathOld, _newbieConfig);
				}
				_newbieConfig = new NewbieConfig() { Version = _beachVersion };
			}
			
			if (_newbieConfig.Respawn_Main == null)
				_newbieConfig.Respawn_Main = new HashSet<NewbieItem>() { new NewbieItem("note", 0, text: "MsgNoteText") };
			else if (_newbieConfig.Respawn_Main.Count > 24)
				_newbieConfig.Respawn_Main = _newbieConfig.Respawn_Main.Take(24).ToHashSet();
			if (_newbieConfig.Respawn_Belt == null)
				_newbieConfig.Respawn_Belt = new HashSet<NewbieItem>() { new NewbieItem("rock", 0, skinID: 2995142032uL), new NewbieItem("torch", 1) };
			else if (_newbieConfig.Respawn_Belt.Count > 6)
				_newbieConfig.Respawn_Belt = _newbieConfig.Respawn_Belt.Take(6).ToHashSet();
			if (_newbieConfig.Respawn_Wear == null)
				_newbieConfig.Respawn_Wear = new HashSet<NewbieItem>() { new NewbieItem("hat.boonie", 0, skinID: 2214894763uL), new NewbieItem("hoodie", 3, skinID: 1394831690uL), new NewbieItem("burlap.trousers", 4, skinID: 2215139481uL), new NewbieItem("burlap.gloves", 5), new NewbieItem("attire.hide.boots", 6, skinID: 861468674uL) };
			else if (_newbieConfig.Respawn_Wear.Count > 7)
				_newbieConfig.Respawn_Wear = _newbieConfig.Respawn_Wear.Take(7).ToHashSet();
			
			SaveBeachConfig();
		}
		
		object OnEntityTakeDamage(NPCPlayerCorpse corpse, HitInfo info)
        {
			if (info == null || TryGetAttacker(info, out var initiator) != AttackerIndex.BasePlayer) return null;
			var attacker = (BasePlayer)initiator;
			if (!attacker.userID.IsSteamId()) return null;
			if (corpse.skinID != 0uL)
            {
				if (corpse.skinID == _bradleySkinId)
                {
                    if (_eventScientistsList.TryGetValue(corpse.net.ID, out var eventData) && !eventData.CanBeAttackedBy(attacker))
						info.Urma();
				}
                else if (corpse.skinID == _rbPluginID)
                {
                    if (TryGetRaidBase(corpse.transform.position, out var rbData) && !rbData.CanInteractWithRaid(attacker.userID))
						info.Urma();
				}
                else if (corpse.skinID == _rrPluginID)
                {
                    if (_config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(corpse.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(attacker.userID))
						info.Urma();
				}
            }
			else if (_monumentsList.TryGetValue(GetEntityMonument(corpse), out var monumentData))
			{
				if (monumentData.CanLoot(attacker) != null)
					info.Urma();
			}
			else if ((_gatherFlags & BuildingGatherRestrictions.Flesh) != 0 && attacker.BinoMumkin() != null)
				info.Urma();
			return null;
		}
		void OnEntitySpawned(Snowmobile snowmobile) => HandleVehicleSpawn(snowmobile);
		void OnEntityKill(ModularCar car) => HandleVehicleKill(car);
		
		private void HandlePermissionsUpdate(string userIDString, BasePlayer player = null)
		{
			if (!ulong.TryParse(userIDString, out ulong userID)) return;
			if (player == null)
				player = BasePlayer.FindByID(userID);
			if (player != null && player.IsConnected)
			{
				if (HasUnlockStaticRespawn(player))
					ForceCompleteBedMission(player);
				else
					ForceFailBedMission(player);
			}
			
			int limit, total;
            VehicleType vehType;
            var vehicleTypes = GetVehicleTypes();
            for (int i = 0; i < vehicleTypes.Length; i++)
            {
                vehType = vehicleTypes[i];
                limit = GetVehicleLimit(userIDString, vehType);
                total = 0;
                foreach (var vehicleData in _vehiclesList.Values)
                {
                    if (vehicleData.OwnerID != userID || vehicleData.Type != vehType) continue;
                    total++;
                    if (total > limit)
                        vehicleData.RemoveOwnerServerSide(player, false);
                }
            }
		}
        
        		void OnPlayerEnterPVP(BasePlayer player, string zoneID)
        {
			if (!player.userID.IsSteamId()) return;
			if (!_pvpPlayers.TryGetValue(player.userID, out var playerPvP))
				_pvpPlayers[player.userID] = playerPvP = new PlayerPvP();
			
			if (playerPvP.ActiveZones.Contains(zoneID))
				return;
			
			playerPvP.ActiveZones.Add(zoneID);
			if (playerPvP.DelayEnd != 0d)
			{
				playerPvP.DelayEnd = 0d;
				Interface.CallHook(Hooks_OnPlayerPVPDelayRemoved, player);
			}
			if (player.IsConnected)
            {
				if (!string.IsNullOrWhiteSpace(playerPvP.LastZone))
                {
					string lastBar;
					if (_monumentsList.TryGetValue(playerPvP.LastZone, out var monumentData))
						lastBar = (string)monumentData.StatusBar[0];
					else if (_rbList.TryGetValue(playerPvP.LastZone, out var rbData))
						lastBar = (string)rbData.StatusBar[0];
					else
						lastBar = playerPvP.LastZone;
					DestroyBar(player.userID, lastBar);
				}
				
				SendPvPBar(player, zoneID);
				if (playerPvP.ActiveZones.Count == 1)
				{
					player.SendEffect();
					SendMessageText(player, lang.GetMessage("MsgPvPEnter", this, player.UserIDString));
				}
            }
			playerPvP.LastZone = zoneID;
		}
		
                private const string MonumentOfferUI = "RealPVE_MonumentOffer",
			MonumentGetMonumentDisplayName = "GetMonumentDisplayName", MonumentGetMonumentCategory = "GetMonumentCategory", MonumentGetMonumentPlayers = "GetMonumentPlayers", MonumentGetMonumentEntities = "GetMonumentEntities", MonumentGetPlayerMonument = "GetPlayerMonument", MonumentGetNpcMonument = "GetNpcMonument", MonumentGetEntityMonument = "GetEntityMonument", MonumentGetMonumentPosition = "GetMonumentPosition", MonumentGetMonumentsByPos = "GetMonumentsByPos", MonumentIsPlayerInMonument = "IsPlayerInMonument";
		object CanLootEntity(BasePlayer player, FrankensteinTable table) => CanLootStorage(player, table);
		
		private string _monumentsPath = string.Empty, _monumentsUiOffer = string.Empty;

		void OnPlayerExitedMonument(string monumentID, BasePlayer player, string category, string reason, string newMonumentID)
		{
			if (_monumentsConfig.TrackedCategories.Contains(category) && _monumentsList.TryGetValue(monumentID, out var monumentData))
			{
				MonumentData newMonumentData = null;
				if (!string.IsNullOrWhiteSpace(newMonumentID))
					_monumentsList.TryGetValue(newMonumentID, out newMonumentData);
				monumentData.OnPlayerExit(player, reason);
				if (newMonumentData != null)
					newMonumentData.OnPlayerEnter(player);
			}
		}
		
		public class VehicleData
        {
			[JsonIgnore] public ulong ID { get; set; }
			public string Name { get; set; } = "Unnamed Vehicle";
            public VehicleType Type { get; set; }
            public VehicleCategory Category { get; set; }
            public ulong OwnerID { get; set; }
            public string RegistrationDate { get; set; } = string.Empty;

            public VehicleData() {}
            public VehicleData(ulong id, VehicleType type, ulong ownerID = 0uL)
            {
                ID = id;
                Type = type;
				switch (Type)
				{
                    case VehicleType.Horse:
                    case VehicleType.Bike:
                    case VehicleType.MotorBike:
                    case VehicleType.Car:
						Category = VehicleCategory.LandVehicle;
						break;
					case VehicleType.Balloon:
                    case VehicleType.Minicopter:
                    case VehicleType.TransportHeli:
                    case VehicleType.AttackHeli:
						Category = VehicleCategory.AirVehicle;
                        break;
					case VehicleType.RowBoat:
                    case VehicleType.RHIB:
                    case VehicleType.TugBoat:
                    case VehicleType.SubmarineOne:
                    case VehicleType.SubmarineTwo:
						Category = VehicleCategory.WaterVehicle;
                        break;
					case VehicleType.Snowmobile:
						Category = VehicleCategory.WinterVehicle;
                        break;
					case VehicleType.BatteringRam:
						Category = VehicleCategory.SiegeVehicle;
						break;
					case VehicleType.Train:
						Category = VehicleCategory.TrainVehicle;
                        break;
					default:
						Category = VehicleCategory.None;
                        break;
				}
				if (ownerID != 0uL)
                {
                    OwnerID = ownerID;
                    RegistrationDate = DateTime.UtcNow.ToString(TimeFormat);
                }
            }

            public object AssignNewOwner(ulong userID, bool showButtons = true) => AssignNewOwner(BasePlayer.FindByID(userID), showButtons);
            public object AssignNewOwner(BasePlayer player, bool showButtons = true)
            {
                object result = false;
                string replyKey;
                string[] replyArgs = new string[5];
                bool isWarning = true;
                if (OwnerID == 0uL || IsOwner(player.userID))
                {
                    int limit = Instance?.GetVehicleLimit(player.UserIDString, Type) ?? 0, totalCars = Instance?.CountVehiclesByType(player.userID, Type) ?? 0;
                    if (OwnerID == 0uL && limit >= 0 && totalCars >= limit)
                    {
                        replyKey = "MsgVehicleLimit";
                        player.RunEffect();
                    }
                    else
                    {
                        if (OwnerID == 0)
                            totalCars++;
                        OwnerID = player.userID;
                        RegistrationDate = DateTime.UtcNow.ToString(TimeFormat);
                        result = null;
                        replyKey = "MsgVehicleLinked";
                        isWarning = false;
						if (showButtons)
							ShowVehicleButtons(player);
					}
                    replyArgs[0] = Instance?.lang.GetMessage($"MsgVehicle{Type}", Instance, player.UserIDString) ?? $"MsgVehicle{Type}";
                    replyArgs[1] = totalCars.ToString();
                    replyArgs[2] = limit < 0 ? "∞" : limit.ToString();
                }
                else
                    replyKey = "MsgVehicleNotOwner";

                Instance?.SendMessage(player, replyKey, replyArgs, isWarning);
                return result;
            }
			
			public bool RemoveOwner(BasePlayer player, bool showButtons = true)
            {
                if (IsOwner(player.userID))
                {
                    RemoveOwnerServerSide(player, showButtons);
                    return true;
                }
				if (Instance != null)
					SendMessageText(player.IPlayer, Instance.lang.GetMessage("MsgVehicleNotOwner", Instance, player.UserIDString));
                return false;
            }

            public void RemoveOwnerServerSide(BasePlayer player = null, bool showButtons = true)
            {
                if (OwnerID == 0uL) return;
                if (player == null)
                    player = BasePlayer.FindByID(OwnerID);
                OwnerID = 0uL;
                RegistrationDate = string.Empty;
                if (player != null)
                {
                    if (showButtons)
                        ShowVehicleButtons(player);
					if (Instance != null)
						SendMessageText(player.IPlayer, string.Format(Instance.lang.GetMessage("MsgVehicleUnLinked", Instance, player.UserIDString), Instance.lang.GetMessage($"MsgVehicle{Type}", Instance, player.UserIDString)), false);
				}
            }

            public bool IsOwner(string userID) => OwnerID.ToString() == userID;
            public bool IsOwner(ulong userID) => OwnerID == userID;

            public bool CanBeNewOwner(BasePlayer player)
            {
                if (OwnerID == 0uL)
                {
                    int limit = Instance?.GetVehicleLimit(player.UserIDString, Type) ?? 0;
                    if (limit < 0 || (Instance?.CountVehiclesByType(player.userID, Type) ?? 0) < limit)
                        return true;
                }
                return false;
            }

            public object CanInteract(BasePlayer player, bool sendMsg = true) => CanPerformAction(player, true, sendMsg);
			public object CanLoot(BasePlayer player) => CanPerformAction(player);
			private object CanPerformAction(BasePlayer player, bool checkFriends = false, bool sendMsg = true)
            {
				if (OwnerID == 0uL || player.userID == OwnerID || (player.Team != null && player.Team.members.Contains(OwnerID)) ||
					(checkFriends && Instance.IsFriend(player.UserIDString, OwnerID.ToString())) || IsEntitySharedToPlayer(player.userID, ID))
					return null;
				if (sendMsg && Instance != null)
					SendMessageText(player.IPlayer, Instance.lang.GetMessage("MsgVehicleCantInteract", Instance, player.UserIDString));
				return false;
            }

            public void OnDestroy()
            {
				if (OwnerID != 0uL && Instance != null && BasePlayer.FindByID(OwnerID) is BasePlayer owner)
					SendMessageText(owner.IPlayer, string.Format(Instance.lang.GetMessage("MsgVehicleDestroyed", Instance, owner.UserIDString), Type, ID));
				_vehiclesList.Remove(ID);
			}
			
			private void ShowVehicleButtons(BasePlayer player)
            {
                if (!_playerUI.ContainsKey(player.userID)) return;
                if (Type == VehicleType.Horse)
                    Instance?.ShowVehiclePanels(player, this);
                else if (Type == VehicleType.Car)
                    Instance?.ShowVehiclePanels(player, this);
                else
                    Instance?.ShowVehiclePanels(player, this);
            }
		}
		object OnEntityTakeDamage(Minicopter minicopter, HitInfo info) => HandleDamageToVehicle(minicopter, info);
		object CanUpdateSign(BasePlayer player, PhotoFrame photoFrame) => _unrestrictedLooters.Contains(player.userID) ? true : (!photoFrame.OwnerID.IsSteamId() || IsEntityInPvP(player.userID, photoFrame.net.ID.Value) ? null : CanInteractSharedEntity(player, photoFrame));
		
		void OnRandomRaidWin(SupplyDrop drop, List<ulong> winners)
		{
			if (winners.Any())
			{
				ulong ownerID = winners[0];
				NextTick(() => { if (drop.IsValid()) drop.OwnerID = ownerID; });
			}
		}
		
		private void CheckForUpdates()
		{
			_unixSeconds = Network.TimeEx.currentTimestamp;
			foreach (var monumentData in _monumentsList.Values)
			{
				if (monumentData.IsMoveable)
					monumentData.MonumentPos = GetMonumentPosition(monumentData.MonumentID);
				if (monumentData.LootEndTime != 0d && _unixSeconds > monumentData.LootEndTime)
					monumentData.RemoveOwner();
				else if (monumentData.mapMarkerNextTime != 0d)
					monumentData.UpdateMapMarkerValues();
			}
			
			foreach (var eventData in _eventsList.Values)
            {
				if (eventData.EndTime > 0d && eventData.EndTime <= _unixSeconds)
					eventData.OnLootTimeEnded();
			}
			
			var pvpToRemove = new HashSet<ulong>();
			PlayerPvP playerPvP;
			foreach (var kvp in _pvpPlayers)
			{
				playerPvP = kvp.Value;
				if (!playerPvP.ActiveZones.Any() && _unixSeconds > playerPvP.DelayEnd)
					pvpToRemove.Add(kvp.Key);
			}
			foreach (ulong userID in pvpToRemove)
				_pvpPlayers.Remove(userID);
		}
        private readonly VersionNumber _vanillaEventsVersion = new VersionNumber(0, 1, 0);
        
        		public static bool ModDartHit(HitInfo info)
        {
			if (info == null)
				return true;
			var victim = info.HitEntity as BasePlayer;
			if (victim != null && victim.userID.IsSteamId())
            {
				if (info.Initiator is AutoTurret turret)
                {
					if (turret.skinID == 0uL && turret.OwnerID.IsSteamId() && !IsEntityInPvP(victim.userID, turret.net.ID.Value) && !UrishMumkin(turret.OwnerID, victim))
						return false;
				}
				else
                {
					var attacker = info.Weapon?.GetOwnerPlayer();
					if (attacker != null && attacker.userID.IsSteamId() && !IsPlayerInPvP(attacker.userID, victim.userID) && !UrishMumkin(attacker, victim.userID))
						return false;
				}
			}
			return true;
        }

		void Init()
        {
			Unsubscribe(nameof(OnPluginLoaded));
			Unsubscribe(nameof(OnPluginUnloaded));
			for (int i = 0; i < _defaultHooks.Length; i++)
				Unsubscribe(_defaultHooks[i]);
			Unsubscribe(nameof(OnUserPermissionGranted));
            Unsubscribe(nameof(OnUserPermissionRevoked));
            Unsubscribe(nameof(OnUserGroupAdded));
            Unsubscribe(nameof(OnUserGroupRemoved));
            Unsubscribe(nameof(OnGroupPermissionGranted));
            Unsubscribe(nameof(OnGroupPermissionRevoked));
            Unsubscribe(nameof(OnGroupDeleted));
			Unsubscribe(nameof(OnPlayerSleep));
			Unsubscribe(nameof(OnItemPickup));
			Unsubscribe(nameof(OnPlayerHandcuff));
			Unsubscribe(nameof(OnPortalUse));
			Unsubscribe(nameof(OnPortalUsed));
			Unsubscribe(nameof(OnBackpackDrop));
			Unsubscribe(nameof(OnEntityEnteredMonument));
			Unsubscribe(nameof(OnEntityExitedMonument));
			Unsubscribe(nameof(OnPlayerEnteredMonument));
			Unsubscribe(nameof(OnPlayerExitedMonument));
			Unsubscribe(nameof(OnCargoWatcherCreated));
			Unsubscribe(nameof(OnCargoWatcherDeleted));
			Unsubscribe(nameof(OnCargoShipHarborArrived));
			Unsubscribe(nameof(OnCargoShipHarborLeave));
			Unsubscribe(nameof(OnHarborEventStart));
			Unsubscribe(nameof(OnHarborEventEnd));
			Unsubscribe(nameof(OnCrateLaptopAttack));
			Unsubscribe(nameof(OnExcavatorResourceSet));
			Unsubscribe(nameof(OnExcavatorSuppliesRequest));
			Unsubscribe(nameof(OnExcavatorSuppliesRequested));
			Unsubscribe(nameof(CanHackCrate));
			Unsubscribe(nameof(OnMonumentsWatcherLoaded));
			Unsubscribe(nameof(OnNpcTarget));
			Unsubscribe(nameof(OnCrateSpawned));
			Unsubscribe(nameof(OnGibsSpawned));
			Unsubscribe(nameof(OnNoGoZoneAdded));
			Unsubscribe(nameof(CanBradleyApcTarget));
			Unsubscribe(nameof(OnEntityEnterZone));
			Unsubscribe(nameof(OnEntityExitZone));
			Unsubscribe(nameof(OnCreateDynamicPVP));
			Unsubscribe(nameof(OnCreatedDynamicPVP));
			Unsubscribe(nameof(OnDeletedDynamicPVP));
			Unsubscribe(nameof(OnScientistInitialized));
			Unsubscribe(nameof(OnScientistRecalled));
			Unsubscribe(nameof(OnPlayerEnteredRaidableBase));
			Unsubscribe(nameof(OnPlayerExitedRaidableBase));
			Unsubscribe(nameof(OnRaidableLootDestroyed));
			Unsubscribe(nameof(OnRaidableDespawnUpdate));
			Unsubscribe(nameof(OnRaidableBasePurchased));
			Unsubscribe(nameof(OnRaidableBaseStarted));
			Unsubscribe(nameof(OnRaidableBaseEnded));
			Unsubscribe(nameof(OnRandomRaidStart));
			Unsubscribe(nameof(RandomRaidEventEnd));
			Unsubscribe(nameof(OnRandomRaidRaiderSpawned));
			Unsubscribe(nameof(OnRandomRaidHeliSpawned));
			Unsubscribe(nameof(OnRandomRaidWin));
			Unsubscribe(nameof(OnBuildingSplit));
			Unsubscribe(nameof(OnDefaultItemsReceive));
			Unsubscribe(nameof(OnAdvancedStatusLoaded));
			
			Instance = this;
			permission.RegisterPermission(PERMISSION_ADMIN, this);
			_commandUI = $"{Name}_{Guid.NewGuid():N}";
			_playerUI = new Dictionary<ulong, HashSet<string>>();
			AddCovalenceCommand(_commandUI, nameof(Command_UI));
			AddCovalenceCommand(_config.AdminCommand, nameof(Command_Admin));
			AddCovalenceCommand(_config.Command, nameof(Command_RealPVE));
			LoadPermissionsConfig();
			LoadMonumentsConfig();
			
			string dataPath = $"{Name}{Path.DirectorySeparatorChar}Data{Path.DirectorySeparatorChar}";
			_dataPlayersPath = $"{dataPath}PlayersData";
			LoadData(_dataPlayersPath, out _playersList);
			_dataVehiclesPath = $"{dataPath}VehiclesData";
			LoadData(_dataVehiclesPath, out _vehiclesList);
			_dataTeamsPath = $"{dataPath}TeamsData";
			LoadData(_dataTeamsPath, out _teamsList);
			
			LoadVanillaEventsConfig();
			_eventsList = new Dictionary<ulong, EventData>();
			LoadRBsConfig();
			LoadBeachConfig();
			_unrestrictedLooters = new HashSet<ulong>();
            _autoBuyPlayers = new HashSet<ulong>();
            _pickupPlayers = new HashSet<ulong>();
            _sharedEntities = new Dictionary<ulong, SharedEntity>();
		}
		
		private static readonly string[] _cmdKeysAdminShare = { "status", "add", "remove", "toggle", "delete", "clear" };
		
		object CanLootEntity(BasePlayer player, Rust.Modular.EngineStorage storage)
        {
			if (_unrestrictedLooters.Contains(player.userID)) return AdminOpenLoot(player, storage);
			if (!IsEntityInPvP(player.userID, storage.net.ID.Value) && storage.GetParentEntity() is BaseVehicleModule module)
				return CanLootCar(player, module.VehicleParent());
			return null;
		}
		void OnEntityKill(RHIB rhib) => HandleVehicleKill(rhib);
		
		void OnEntityKill(PatrolHelicopter patrol)
        {
			if (patrol.skinID == _rrPluginID)
            {
				if (_config.RandomRaids_Enabled && _rrallPatrols.TryGetValue(patrol.net.ID.Value, out var rrData))
					rrData.OnPatrolDestroy(patrol.transform.position);
            }
			else if (_eventsList.TryGetValue(patrol.net.ID.Value, out var eventData))
				eventData.OnParentDestroy(patrol.transform.position);
		}
		
		object OnItemPickup(Item item, BasePlayer player, DroppedItem droppedItem)
        {
			if (_unrestrictedLooters.Contains(player.userID) || IsEntityInPvP(player.userID, droppedItem.net.ID.Value)) return null;

            object result = null;
            string replyKey = string.Empty;
            if (droppedItem.DroppedBy.IsSteamId())
            {
                if (!_pickupPlayers.Contains(droppedItem.DroppedBy))
                {
                    result = player.TasirMumkin(droppedItem.DroppedBy);
                    replyKey = "MsgCantPickup";
                }
            }
            else if (_monumentsConfig.OnlyOwnerPickup && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
            {
                result = monumentData.CanPickup(player);
                replyKey = "MsgMonumentCantPickup";
            }

            if (result != null)
			{
				SendMessageText(player, lang.GetMessage(replyKey, this, player.UserIDString));
				return true;
			}
			return null;
        }
		
		private static bool IsPlayerInPvP(ulong a, ulong b) => _pvpPlayers.ContainsKey(a) && _pvpPlayers.ContainsKey(b);
		object OnCodeChange(ModularCar modularCar, BasePlayer player, string newPass) => false;
		
		private bool HasUnlockStaticRespawn(BasePlayer player)
        {
			PvEPermission perm;
            for (int i = 0; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				perm = _permissionsConfig.PermissionsList[i];
				if (perm.UnlockStaticRespawn && permission.UserHasPermission(player.UserIDString, perm.Name))
					return true;
			}
			return false;
		}
		
		private string _vanillaEventsPath = string.Empty, _vanillaEventsUiOffer = string.Empty;
		void OnEntitySpawned(Minicopter minicopter) => HandleVehicleSpawn(minicopter);
		public static readonly HashSet<VehicleType> _allowSitVehicles = new HashSet<VehicleType>() { VehicleType.MotorBike, VehicleType.Car, VehicleType.Minicopter, VehicleType.TransportHeli, VehicleType.RowBoat, VehicleType.RHIB, VehicleType.Snowmobile };
		private Dictionary<string, RBData> _rbList = new Dictionary<string, RBData>(StringComparer.OrdinalIgnoreCase);
		object CanLootEntity(BasePlayer player, IOEntity entity) => CanLootByOwnerID(player, entity);
		
		public static AttackerIndex TryGetAttacker(HitInfo info, out BaseEntity result)
        {
			result = info.Initiator ?? info.WeaponPrefab;
			if (result == null) return AttackerIndex.None;
			
			var player = result.ToPlayer();
			if (player.IsValid())
			{
				result = player;
				if (result is BasePet pet)
                {
					player = pet.Brain?.OwningPlayer ?? BasePlayer.FindAwakeOrSleepingByID(pet.OwnerID);
					if (!player.IsValid())
						return AttackerIndex.BaseEntity;
					result = player;
				}
				return AttackerIndex.BasePlayer;
			}
			
			if (result is PatrolHelicopter || _patrolHeliPrefabs.Contains(result.ShortPrefabName))
            {
                
                
                

                

                return AttackerIndex.PatrolHelicopter;
			}
            else if (result is BradleyAPC)
                return AttackerIndex.BradleyAPC;
			
			if (result.OwnerID.IsSteamId())
            {
				player = BasePlayer.FindAwakeOrSleepingByID(result.OwnerID);
				if (player.IsValid())
				{
					result = player;
					return AttackerIndex.BasePlayer;
				}
				return AttackerIndex.PlayerOwnerMissing;
			}
            return AttackerIndex.BaseEntity;
		}
		
		private void HandleVehicleSpawn(BaseEntity vehicle) => NextTick(() => { InitVehicle(vehicle); });
        

        object OnEntityTakeDamage(RidableHorse horse, HitInfo info) => HandleDamageToVehicle(horse, info);
		object OnRackedWeaponUnload(Item item, BasePlayer player, WeaponRack rack) => CanLootWeaponRack(player, rack);
		
                private readonly string[] _hooksConflict = new string[] { "resulted in a conflict between the following plugins:", "Hook conflict while calling" };
		object OnEntityTakeDamage(VehicleModuleEngine module, HitInfo info) => HandleDamageToVehicle(module.VehicleParent(), info);
		protected override void LoadDefaultConfig() => _config = new Configuration() { Version = Version };
		
		public enum AttackerIndex
		{
			None,
			BaseEntity,
			PatrolHelicopter,
			BradleyAPC,
			BasePlayer,
            PlayerOwnerMissing
		}
        
        		private Dictionary<string, string> _enLang = new Dictionary<string, string>
        {
            ["MsgNoteText"] = "Welcome to our PvE server!\nThis server utilizes the RealPVE plugin.\nYou can find more details about the plugin at the following link: https://codefling.com/plugins/real-pve",
            ["CmdAdmin"] = string.Join("\n", new string[]
            {
                "Available admin commands:\n",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>autobuy</color> - Manage autobuy for monuments, vanilla events and raid bases",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>config</color> - Manage settings for values in the configuration file",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>loot</color> - Manage player access to entities without restrictions",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>monument</color> - Manage monuments",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>perm</color> - Manage permissions",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>pickup</color> - Manage access to picking up another player's items from the ground",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>share</color> - Manage access to looting entities by other players",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>tc</color> - Manage building privilege",
                "<color=#D1CBCB>/adminpve</color> <color=#D1AB9A>vehicle types</color> - List of all available vehicle types",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminAutoBuy"] = string.Join("\n", new string[]
            {
                "Manage autobuy for monuments, vanilla events and raid bases with a price greater than 0:\n",
                "<color=#D1CBCB>/adminpve autobuy</color> <color=#D1AB9A>*nameOrId*</color> - Toggle autobuy for the specified player",
                "<color=#D1CBCB>/adminpve autobuy</color> <color=#D1AB9A>force monument/event/rb</color> - Toggle forced autobuy. If enabled, player settings will be ignored",
                "<color=#D1CBCB>/adminpve autobuy</color> <color=#D1AB9A>clear</color> - Disable autobuy for everyone",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminAutoBuyClear"] = "Autobuy has been disabled for {0} players!",
            ["CmdAdminAutoBuyForce"] = "Autobuy for '{0}' has been switched to: {1}!",
            ["CmdAdminAutoBuyEnabled"] = "You have enabled autobuy for player '{0}'!",
            ["CmdAdminAutoBuyDisabled"] = "You have disabled autobuy for player '{0}'!",
            ["CmdAdminConfig"] = string.Join("\n", new string[]
            {
                "Manage settings for values in the configuration file:\n",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>forcepve *boolValue*(optional)</color> - Is it worth forcibly implementing PvE for a server?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>forcetutorial *boolValue*(optional)</color> - Is it worth forcing the tutorial mode support?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>gametips *boolValue*(optional)</color> - Is it worth enabling GameTips for messages?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>perm_limits *boolValue*(optional)</color> - Is it worth rechecking the limits when removing permissions?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>safe_death *boolValue*(optional)</color> - Is it worth preventing death on logout in safe zones?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>plant_privilege *boolValue*(optional)</color> - Is it worth preventing the pickup of plants spawned by the server in someone else's building privilege zone?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>heli_damage *boolValue*(optional)</color> - Is it worth forcibly blocking damage from the patrol helicopter to building blocks and deployables?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>handcuffs *boolValue*(optional)</color> - Is it worth assigning portals(Halloween and Christmas) to the first player?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>portals *boolValue*(optional)</color> - Is it worth preventing players from handcuffing others?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>backpack_drop *boolValue*(optional)</color> - Is it worth preventing a backpack from dropping upon player death?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>laptop_damage *boolValue*(optional)</color> - Is it worth preventing damage to the laptop of the Hackable Crate?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>recycler_safezone *boolValue*(optional)</color> - Is it worth removing the penalties for recyclers in safe zones?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>item_pickup *boolValue*(optional)</color> - Is it worth allowing all players to pick up items dropped by others? If enabled, personal settings will be ignored",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>safe_sleep *boolValue*(optional)</color> - Is it worth protecting sleeping players from animals?",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>resource_privilege *intValue*</color> - List of forbidden resource gathering types in someone else's building privilege area. 0 - no restrictions, 1 - trees, 2 - ores, 3 - flesh",
				"<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>priceformat *stringValue*</color> - The format that will be used for prices",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>vehicle_marker_time *floatValue*</color> - Vehicles - Time in seconds to display the marker when searching for a vehicle. A value of 0 disables the marker",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>antisleeper *floatValue*</color> - Anti-Sleeper - Time in seconds after which a player will be killed if they disconnect while inside someone else's Building Privilege. Set to 0 to disable",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>randomraids *boolValue*(optional)</color> - Is it worth enabling support for the 'Npc Random Raids' plugin?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>teamff *boolValue*(optional)</color> - Is friendly fire enabled by default when creating a new team?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>pvpmarkers *boolValue*(optional)</color> - PvP - Is it worth adding map markers for PvP zones?",
                "<color=#D1CBCB>/adminpve config</color> <color=#D1AB9A>pvpmarkersname *stringValue*</color> - PvP - Name of the map maker",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminConfigEditSucceed"] = "{1} - {0}",
            ["CmdAdminLoot"] = string.Join("\n", new string[]
            {
                "Manage player access to entities without restrictions:\n",
                "<color=#D1CBCB>/adminpve loot</color> <color=#D1AB9A>*nameOrId*</color> - Toggle unrestricted access for the specified player",
                "<color=#D1CBCB>/adminpve loot</color> <color=#D1AB9A>self</color> - Toggle unrestricted access for yourself",
                "<color=#D1CBCB>/adminpve loot</color> <color=#D1AB9A>clear</color> - Revoke unrestricted access for all players",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminLootClear"] = "Admin loot rights have been revoked for {0} players!",
            ["CmdAdminLootEnabled"] = "Admin looting rights have been granted to you!",
            ["CmdAdminLootDisabled"] = "Admin looting rights have been revoked from you!",
            ["CmdAdminLootPlayerEnabled"] = "Admin looting rights have been granted to player {0}!",
            ["CmdAdminLootPlayerDisabled"] = "Admin looting rights have been revoked from player {0}!",
            ["CmdAdminMonument"] = string.Join("\n", new string[]
            {
                "Manage monuments:\n",
                "<color=#D1CBCB>/adminpve monument</color> <color=#D1AB9A>list</color> - List available monuments",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>suffix *boolValue*(optional)</color> - Toggle the suffix display in the monument's name",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>broadcast *boolValue*(optional)</color> - Toggle notifications about monument occupancy/release",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>time *intValue*</color> - Set the looting time limit for the monument in seconds",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>price *floatValue*</color> - Set the cost for looting rights. A value of 0 makes the monument free",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>offer *floatValue*</color> - Set the offer duration for purchasing the monument in seconds",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>map_mode *intValue*</color> - Set the marker display mode on the map. 0 - disabled, 1 - enabled, 2 - enabled during PvP mode",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>map_circle *boolValue*(optional)</color> - Toggle the display of the monument's circle marker on the map",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>pvp *boolValue*(optional)</color> - Toggle PvP mode for the monument",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>pvp_delay *floatValue*</color> - Set the PvP mode duration in seconds for players after leaving the PvP monument",
                "<color=#D1CBCB>/adminpve monument *monumentID*</color> <color=#D1AB9A>bar_progress *boolValue*(optional)</color> - Toggle between TimeProgressCounter and TimeCounter bars for the monument",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminMonumentNotFound"] = "Monument '{0}' not found!",
            ["CmdAdminMonumentOcupied"] = "Monument '{0}' is occupied! You can only apply such changes to unoccupied monuments.",
            ["CmdAdminMonumentList"] = string.Join("\n", new string[]
            {
                "List of available monuments:\n",
                "{0}",
                "\n<color=#D1CBCB>Note:</color> Instead of the monument ID, you can use the word 'this', but you must be inside the monument. CargoShip settings can only be configured using the word 'this'",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminMonumentWrongValue"] = "Incorrect value type for this command!",
            ["CmdAdminMonumentSucceed"] = "New value for property '{1}' in monument '{0}': {2}.",
            ["CmdAdminPerm"] = string.Join("\n", new string[]
            {
                "Manage permissions:\n",
                "<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>add *permName*</color> - Adds a new permission to the list by copying values from the first(default) permission in the list. If the permission name starts with 'realpve', it will also register a new permission",
                "<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>add *permName* *sourcePermName*</color> - Adds a new permission to the list by copying values from an existing permission in the list",
                "<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>remove *permName*</color> - Removes an existing permission from the list",
                "<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>edit *permName*</color> - Edits a permission",
                "<color=#D1CBCB>/adminpve perm</color> <color=#D1AB9A>clear</color> - Removes all permissions from the list except the first one",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminPermNotFound"] = "Permission '{0}' not found!",
            ["CmdAdminPermNotEnough"] = "To remove permissions, there must be more than one permission available!",
            ["CmdAdminPermCleared"] = "Successfully removed {0} permissions!",
            ["CmdAdminPermAlreadyExists"] = "Permission '{0}' already exists!",
            ["CmdAdminPermAdded"] = "Permission '{0}' has been successfully added!",
            ["CmdAdminPermRemoved"] = "Permission '{0}' has been successfully removed!",
            ["CmdAdminPermEdit"] = string.Join("\n", new string[]
            {
                "Editing permission:\n",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>queue</color> - Toggle the permission to bypass the server queue",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>unlockRespawn</color> - Toggle the availability of the Outpost respawn point",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>beds *intValue*</color> - Restriction on the number of available beds",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>shelters *intValue*</color> - Restriction on the number of available shelters",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>turrets *intValue*</color> - Restriction on the number of available turrets",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>hackable *floatValue*</color> - Number of seconds(0-900) to skip when opening a hackable crate",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>monuments *floatValue*</color> - Price multiplier for monuments",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>events *floatValue*</color> - Price multiplier for vanilla events",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>rb_limit *intValue*</color> - Restriction on the number of raid bases available simultaneously",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>rb_mult *floatValue*</color> - Price multiplier for raid bases",
                "<color=#D1CBCB>/adminpve perm edit *permName*</color> <color=#D1AB9A>vehicles *vehType*</color> - Vehicles settings",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminPermEditSucceed"] = "New value for property '{1}' in permission '{0}': {2}.",
            ["CmdAdminPermEditVehicles"] = string.Join("\n", new string[]
            {
                "Vehicles settings:\n",
                "<color=#D1CBCB>/adminpve vehicle types</color> - List of available vehicle types",
                "<color=#D1CBCB>/adminpve perm edit *permName* vehicles *vehType*</color> <color=#D1AB9A>limit *intValue*</color> - Limit on the number of available vehicles by type",
                "<color=#D1CBCB>/adminpve perm edit *permName* vehicles *vehType*</color> <color=#D1AB9A>price *floatValue*</color> - Price for registering a vehicle by type",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminPermEditVehiclesSucceed"] = "New value for the property '{2}' of vehicle type '{1}' in permission '{0}': {3}.",
            ["CmdAdminPickup"] = string.Join("\n", new string[]
            {
                "Manage access to picking up another player's items from the ground:\n",
                "<color=#D1CBCB>/adminpve pickup</color> <color=#D1AB9A>*nameOrId*</color> - Toggle access to picking up a specific player's items from the ground",
                "<color=#D1CBCB>/adminpve pickup</color> <color=#D1AB9A>clear</color> - Revoke access for all players to pick up items from the ground",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminPickupClear"] = "Removed {0} players from the list allowing others to pick up their items!",
            ["CmdAdminPickupEnabled"] = "Access to pick up player {0}'s items from the ground has been enabled!",
            ["CmdAdminPickupDisabled"] = "Access to pick up player {0}'s items from the ground has been disabled!",
            ["CmdAdminShare"] = string.Join("\n", new string[]
            {
                "Manage access to looting entities by other players(outside of the team):\n",
                "<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>status *entityID*(optional)</color> - Display information about the settings of the entity you are looking at or the one you specified",
                "<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>add *nameOrID* *entityID*(optional)</color> - Add the specified player to the entity list you are looking at or the one you specified",
                "<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>remove *nameOrID* *entityID*(optional)</color> - Remove the specified player from the entity list you are looking at or the one you specified",
                "<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>toggle *entityID*(optional)</color> - Toggle the entity list you are looking at or the one you specified",
                "<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>delete *entityID*(optional)</color> - Delete the settings for the entity you are looking at or the one you specified",
                "<color=#D1CBCB>/adminpve share</color> <color=#D1AB9A>clear *nameOrID*(optional)</color> - Delete the settings for all entities or all entities of the specified player",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminShareStatusDefault"] = string.Join("\n", new string[]
            {
                "Player '{1}' has <u>denied</u> access to the entity '{0}' <u>for everyone except</u>:",
                "{2}",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminShareStatusEveryOne"] = string.Join("\n", new string[]
            {
                "Player '{1}' has <u>granted</u> access to the entity '{0}' <u>for everyone except</u>:",
                "{2}",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminShareGranted"] = "Player '{1}' has <u>granted</u> access to entity '{0}' to player '{2}'!",
            ["CmdAdminShareDenied"] = "Player '{1}' has <u>denied</u> access to entity '{0}' to player '{2}'!",
            ["CmdAdminShareToggleDefault"] = "Player '{1}' has <u>denied</u> access to entity '{0}' <u>for everyone except those in the list</u>!",
            ["CmdAdminShareToggleEveryOne"] = "Player '{1}' has <u>granted</u> access to entity '{0}' <u>for everyone except those in the list</u>!",
            ["CmdAdminShareDelete"] = "Access permissions for entity '{0}' belonging to player '{1}' have been successfully removed!",
            ["CmdAdminShareClearTarget"] = "Player '{1}' has had {0} access permissions to entities removed!",
            ["CmdAdminShareClear"] = "Removed {0} access permissions to entities!",
            ["CmdAdminTC"] = string.Join("\n", new string[]
            {
                "Manage building privilege:\n",
                "<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>add self/*entityID* *nameOrID*(optional)</color> - Add yourself or a specified player to the building privilege of the area you or the specified entity are in",
                "<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>remove self/*entityID* *nameOrID*(optional)</color> - Remove yourself or a specified player from the building privilege of the area you or the specified entity are in",
                "<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>clear self/*entityID*</color> - Clear the list of authorized players in the building privilege of the area you or the specified entity are in",
                "<color=#D1CBCB>/adminpve tc</color> <color=#D1AB9A>info self/*entityID*</color> - Get information about the building privilege of the area you or the specified entity are in",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminTCAdd"] = "You have successfully authorized player '{1}' in the building privilege '{0}'!",
            ["CmdAdminTCRemove"] = "You have successfully removed player '{1}' from the building privilege '{0}'!",
            ["CmdAdminTCClear"] = "{1} players have been successfully removed from the building privilege '{0}'!",
            ["CmdAdminTCInfo"] = string.Join("\n", new string[]
            {
                "Building privilege information for '{0}':\n",
                "Time in minutes until decay starts: {1}",
                "List of authorized players({2}):",
                "{3}",
                "\n--------------------------------------------------"
            }),
            ["CmdAdminVehicleTypes"] = string.Join("\n", new string[]
            {
                "List of available vehicle types:\n",
                "{0}",
                "\n--------------------------------------------------"
            }),
            ["CmdMainEditWrongValue"] = "Incorrect value type for this command!",
            ["CmdPlayerNotFound"] = "Player '{0}' not found! You must provide the player's name or ID.",
            ["CmdMultiplePlayers"] = "Multiple players found for '{0}': {1}",
            ["CmdEntityNotFound"] = "Entity not found! You must specify the entity ID or look directly at it.",
            ["CmdEntityNotOwned"] = "The entity '{0}' has no owner!",
            ["CmdEntityNotOwner"] = "You are not the owner of this entity!",
            ["CmdPrivilegeNotFound"] = "Building privilege not found!",
            ["CmdMain"] = string.Join("\n", new string[]
            {
                "Available commands:\n",
                "<color=#D1CBCB>/realpve</color> <color=#D1AB9A>autobuy</color> - Toggle autobuy for monuments, vanilla events and raid bases with a total price greater than 0",
                "<color=#D1CBCB>/realpve</color> <color=#D1AB9A>pickup</color> - Toggle access to pick up your items from the ground for all players",
                "<color=#D1CBCB>/realpve</color> <color=#D1AB9A>share</color> - Manage access to looting your entities by other players",
                "<color=#D1CBCB>/realpve</color> <color=#D1AB9A>team</color> - Manage your team",
                "<color=#D1CBCB>/realpve</color> <color=#D1AB9A>vehicle</color> - Manage your vehicles",
                "\n--------------------------------------------------"
            }),
            ["CmdMainAutoBuyEnabled"] = "You have enabled autobuy for monuments, vanilla events and raid bases with a final price greater than 0!",
            ["CmdMainAutoBuyDisabled"] = "You have disabled autobuy for monuments, vanilla events and raid bases with a final price greater than 0!",
            ["CmdMainPickupEnabled"] = "You have allowed all players to pick up your items!",
            ["CmdMainPickupDisabled"] = "You have forbidden all players from picking up your items!",
            ["CmdMainShare"] = string.Join("\n", new string[]
            {
                "Manage access to looting your entities by other players(outside of the team):\n",
                "<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>status *entityID*(optional)</color> - Display information about the settings of the entity you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>add *nameOrID* *entityID*(optional)</color> - Add the specified player to the entity list you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>remove *nameOrID* *entityID*(optional)</color> - Remove the specified player from the entity list you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>toggle *entityID*(optional)</color> - Toggle the entity list you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>delete *entityID*(optional)</color> - Delete the settings for the entity you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve share</color> <color=#D1AB9A>clear</color> - Delete the settings for all your entities",
                "\n--------------------------------------------------"
            }),
            ["CmdMainShareStatusDefault"] = string.Join("\n", new string[]
            {
                "You have <u>denied</u> access to the entity '{0}' <u>for everyone except</u>:",
                "{1}",
                "\n--------------------------------------------------"
            }),
            ["CmdMainShareStatusEveryOne"] = string.Join("\n", new string[]
            {
                "You have <u>granted</u> access to the entity '{0}' <u>for everyone except</u>:",
                "{1}",
                "\n--------------------------------------------------"
            }),
            ["CmdMainShareGranted"] = "You have <u>granted</u> player '{1}' access to entity '{0}'!",
            ["CmdMainShareDenied"] = "You have <u>denied</u> player '{1}' access to entity '{0}'!",
            ["CmdMainShareToggleDefault"] = "You have <u>denied</u> access to entity '{0}' <u>for everyone except those in the list</u>!",
            ["CmdMainShareToggleEveryOne"] = "You have <u>granted</u> access to entity '{0}' <u>for everyone except those in the list</u>!",
            ["CmdMainShareDelete"] = "Access permissions for entity '{0}' have been successfully removed!",
            ["CmdMainShareClear"] = "Removed {0} access permissions to entities!",
            ["CmdMainTeamNotFound"] = "To use this command, you must be in a group!",
            ["CmdMainTeamNotLeader"] = "To use this command, you must be the group leader!",
            ["CmdMainTeam"] = string.Join("\n", new string[]
            {
                "Manage your team:\n",
                "<color=#D1CBCB>/realpve team</color> <color=#D1AB9A>ff</color> - Toggle the ability to damage your teammates",
                "\n--------------------------------------------------"
            }),
            ["CmdMainTeamFireEnabled"] = "Friendly fire enabled by {0}!",
            ["CmdMainTeamFireDisabled"] = "Friendly fire disabled by {0}!",
            ["CmdMainVehicle"] = string.Join("\n", new string[]
            {
                "Manage your vehicles:\n",
                "<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>list</color> - List of IDs for all your vehicles",
                "<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>find *vehicleID*(optional)</color> - Help finding the vehicle you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>unlink *vehicleID*(optional)</color> - Unlink the vehicle you are looking at or the one you specified",
                "<color=#D1CBCB>/realpve vehicle</color> <color=#D1AB9A>clear</color> - Unlink all your vehicles",
                "\n--------------------------------------------------"
            }),
            ["CmdMainVehicleNotFound"] = "Vehicle not found!",
            ["CmdMainVehicleClear"] = "{0} vehicles have been unregistered!",
            ["CmdMainVehicleList"] = string.Join("\n", new string[]
            {
                "List of your vehicle IDs:\n",
                "{0}",
                "\n--------------------------------------------------"
            }),
            ["CmdMainVehicleListEmpty"] = "You do not own any vehicles!",
            ["CmdMainVehicleFind"] = "Your vehicle '{0}' is located in grid {1}!",
            ["MsgCantInteract"] = "You can't interact with others' belongings!",
            ["MsgCantInteractPlayer"] = "You can't interact with other players, only your friends!",
            ["MsgCantGatherInBase"] = "You can't gather resources in others' bases!",
            ["MsgCantPickup"] = "You can't pick up others' items!",
            ["MsgCantRenameBed"] = "You can't rename someone else's beds!",
            ["MsgCantAdministerVending"] = "You can't administer someone else's vending machines!",
            ["MsgCantInteractWeaponRack"] = "You can't interact with others' weapon racks!",
            ["MsgCantHandcuffing"] = "You can't handcuff other players outside the PvP zone!",
            ["MsgPrivlidgeClear"] = "{0} players have been removed from the Building Privilege.",
            ["MsgPrivlidgeClearEmpty"] = "Only you are authorized in the Building Privilege.",
            ["MsgFree"] = "Free",
            ["MsgNoDate"] = "null",
            ["MsgEconomicsNotEnough"] = "Not enough funds!",
            ["MsgPvPEnter"] = "You have entered the PvP zone! You can be killed and looted here!",
            ["MsgPvPBar"] = "PvP Zone!",
            ["MsgPvPDelay"] = "You have left the PvP zone, but PvP will remain active for {0} seconds!",
            ["MsgPvPDelayBar"] = "PvP ends in:",
            ["MsgMonumentOccupied"] = "{1} occupied {0} in {2} minutes.",
            ["MsgMonumentFree"] = "{0} is available for looting!",
            ["MsgMonumentOfferTitle"] = "Unlock Treasures of {0}!",
            ["MsgMonumentOfferDescription"] = "Tap the notification to pay {0}.\nAnd unlock access to undiscovered riches!",
            ["MsgMonumentCantPickup"] = "You can't pick up items in others' monuments!",
            ["MsgMonumentLooterDeath"] = "You died while looting {0}. You have {1} seconds.",
            ["MsgMonumentLooterExit"] = "You have left the monument. You have {0} seconds to return!",
            ["MsgMonumentLooterRemoved"] = "Time's up! You have been removed from the monument!",
            ["MsgMonumentFriendEnter"] = "You have been granted the right to loot bypassing the queue as your friend '{0}' already has this right.",
            ["MsgMonumentQueueNotFree"] = "You have been added to the looting queue({0}/{1}). Looting cost: {2}",
            ["MsgMonumentQueue"] = "You have been added to the looting queue({0}/{1}). Looting is free.",
            ["MsgMonumentNotInQueue"] = "You are not in the queue! You need to re-enter the monument!",
            ["MsgMonumentIsPvP"] = "PvP Zone!",
            ["MsgMonumentNoAccess"] = "no access",
            ["MsgVehicleDialogTitle"] = "Department of Motor Vehicles",
            ["MsgVehicleDialogDescription"] = "ID: \nType: \nRegistration fee: \nCategory: ",
            ["MsgVehicleDialogDescriptionValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{4}</b>\n<b>{2}</b>",
            ["MsgVehicleDialogDescriptionRegistered"] = "ID: \nType: \nRegistration date: \nCategory: ",
            ["MsgVehicleDialogDescriptionRegisteredValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{3}</b>\n<b>{2}</b>",
            ["MsgVehicleDialogDescriptionNotOwner"] = "ID: \nOwner: \nRegistration date: \nType: \nCategory: ",
            ["MsgVehicleDialogDescriptionNotOwnerValue"] = "<b>{0}</b>\n<b>{4}</b>\n<b>{3}</b>\n<b>{1}</b>\n<b>{2}</b>",
            ["MsgVehicleCarDialogDescription"] = "ID: \nType: \nRegistration fee: \nCategory: ",
            ["MsgVehicleCarDialogDescriptionValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{4}</b>\n<b>{2}</b>",
            ["MsgVehicleCarDialogDescriptionRegistered"] = "ID: \nType: \nReg date: \nCategory: ",
            ["MsgVehicleCarDialogDescriptionRegisteredValue"] = "<b>{0}</b>\n<b>{1}</b>\n<b>{3}</b>\n<b>{2}</b>",
            ["MsgVehicleCarDialogDescriptionNotOwner"] = "ID: \nOwner: \nReg date: \nType: \nCategory: ",
            ["MsgVehicleCarDialogDescriptionNotOwnerValue"] = "<b>{0}</b>\n<b>{4}</b>\n<b>{3}</b>\n<b>{1}</b>\n<b>{2}</b>",
            ["MsgVehicleCarGarageEmpty"] = "The car lift is empty!",
            ["MsgVehicleDialogLink"] = "Register Vehicle",
            ["MsgVehicleDialogUnLink"] = "Cancel registration",
            ["MsgVehicleDialogOwnerWarning"] = "Removing all driver modules will result in the cancellation of registration!",
            ["MsgVehicleDialogWarning"] = "Registration is only possible with a driver module present!",
            ["MsgVehicleDialogIncorrectPassword"] = "The password must consist of 4 digits!",
            ["MsgVehicleNotOwner"] = "You are not the owner!",
            ["MsgVehicleWrongName"] = "Invalid name format for the vehicle!",
            ["MsgVehicleNewName"] = "Your vehicle has been renamed to: {0}!",
            ["MsgVehicleCantInteract"] = "You are not the owner or a friend of this vehicle!",
            ["MsgVehicleCantSeat"] = "You can't sit in this seat!",
            ["MsgVehicleCantPush"] = "You can't push this vehicle in this area!",
            ["MsgVehicleCantPull"] = "You can't pull this vehicle in this area!",
            ["MsgVehicleCantOpen"] = "You can't open the door of this vehicle!",
            ["MsgVehicleCantClose"] = "You can't close the door of this vehicle!",
            ["MsgVehicleCantEngineStart"] = "You can't start the engine of this vehicle!",
            ["MsgVehicleCantBalloonToggle"] = "You can't control this hot air balloon!",
            ["MsgVehicleCantLead"] = "You can't lead this horse in this area!",
            ["MsgVehicleCantTow"] = "You can't hitch this horse to this siege weapon!",
            ["MsgVehicleCantDetach"] = "You can't detach this horse from this siege weapon!",
            ["MsgVehicleCantSaddleSwap"] = "You can't replace the saddle on this horse!",
            ["MsgVehicleLinked"] = "The {0} has been successfully linked! You have {1} out of {2} available.",
            ["MsgVehicleUnLinked"] = "The {0} has been successfully unlinked!",
            ["MsgVehicleFailedDeauthorize"] = "You can only deauthorize by unlinking the vehicle from you.",
            ["MsgVehicleLimit"] = "Limit exceeded! You have used {1} out of {2} registrations.",
            ["MsgVehicleDestroyed"] = "Your vehicle {0}({1}) has been destroyed!",
            ["MsgVehicleTugboatAuthorization"] = "To authorize in the tugboat, it must be claim!",
            ["MsgVehicleLandVehicle"] = "Land",
            ["MsgVehicleAirVehicle"] = "Air",
            ["MsgVehicleWaterVehicle"] = "Water",
            ["MsgVehicleWinterVehicle"] = "Winter",
            ["MsgVehicleSiegeVehicle"] = "Siege Vehicle",
            ["MsgVehicleTrainVehicle"] = "Train",
            ["MsgVehicleHorse"] = "horse",
            ["MsgVehicleBike"] = "bike",
            ["MsgVehicleMotorBike"] = "motor bike",
            ["MsgVehicleCar"] = "car",
            ["MsgVehicleBalloon"] = "air balloon",
            ["MsgVehicleMinicopter"] = "minicopter",
            ["MsgVehicleTransportHeli"] = "transportHeli",
            ["MsgVehicleAttackHeli"] = "attack heli",
            ["MsgVehicleRowBoat"] = "row boat",
            ["MsgVehicleRHIB"] = "RHIB",
            ["MsgVehicleTugBoat"] = "tugboat",
            ["MsgVehicleSubmarineOne"] = "small submarine",
            ["MsgVehicleSubmarineTwo"] = "submarine",
            ["MsgVehicleSnowmobile"] = "snowmobile",
            ["MsgVehicleBatteringRam"] = "battering ram",
            ["MsgVehicleTrain"] = "train",
            ["MsgEventOccupied"] = "Event '{0}' is already occupied by player '{1}'!",
            ["MsgEventOfferTitle"] = "Claim '{0}'!",
            ["MsgEventOfferDescription"] = "Tap the notification to pay {0}.\nAnd unlock access to undiscovered riches!",
            ["MsgEventNewLooter"] = "You have successfully claimed the '{0}' event! Death limit: {1}(total for the team), time limit: {2} min",
            ["MsgEventDeath"] = "Player '{1}' has been killed! Death counter for event '{0}': {2}/{3}",
            ["MsgEventDeathLimit"] = "Player '{1}' has been killed! Thus exhausting the death limit for event '{0}'!",
            ["MsgEventTimeLimit"] = "The time allotted for the '{0}' event has expired!",
            ["MsgEventComplete"] = "The '{0}' event has been destroyed at coordinates: {1}!",
            ["MsgEventPatrolHelicopter"] = "Patrol Helicopter",
            ["MsgEventBradleyAPC"] = "Bradley APC",
            ["MsgRaidableBasesDisabled"] = "This Raidable Base is either disabled or not found!",
            ["MsgRaidableBasesOccupied"] = "The Raidable Base is already occupied by {0}!",
            ["MsgRaidableBasesLimit"] = "Limit exceeded! You have {0} out of {1} available Raidable Bases.",
            ["MsgRaidableBasesPurchaseStart"] = "Payment successful! Please wait...",
            ["MsgRaidableBasesPurchased"] = "You have successfully purchased the Raidable Base!",
            ["MsgRaidableBasesPurchaseFailed"] = "You were unable to purchase the Raidable Base! Funds refunded.",
            ["MsgRaidableBasesOfferTitle"] = "Claim {0} Raidable Base!",
            ["MsgRaidableBasesOfferDescription"] = "Tap the notification to pay {0}.\nAnd unlock access to undiscovered riches!",
            ["MsgRaidableBasesBarText"] = "{0} Base",
            ["MsgRaidableBasesBarTextLootRemaining"] = "Loot Remaining",
            ["MsgRaidableBasesBarTextLootCompleted"] = "Completed",
            ["MsgRaidableBasesBarNoAccess"] = "no access",
            ["MsgRaidableBasesEasy"] = "Easy",
            ["MsgRaidableBasesMedium"] = "Medium",
            ["MsgRaidableBasesHard"] = "Hard",
            ["MsgRaidableBasesExpert"] = "Expert",
            ["MsgRaidableBasesNightmare"] = "Nightmare",
            ["MsgRaidableBasesIsPvP"] = "PvP Zone!",
            ["MsgSurvivalArena"] = "Survival Arena",
            ["MsgArenaWhilePvP"] = "You can't enter the arena while you have an active PvP!"
        };
		
		private double GetBalance(string userID) => (double)(Economics?.Call("Balance", userID) ?? 0d);
		
		object CanRenameBed(BasePlayer player, SleepingBag bed, string bedName)
        {
			if (bed.deployerUserID != player.userID && bed.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, bed.net.ID.Value))
            {
                object result = CanInteractSharedEntity(player, bed);
                if (result != null)
                    SendMessageText(player, lang.GetMessage("MsgCantRenameBed", this, player.UserIDString));
                return result;
            }
			return null;
		}
		private static PermissionConfig _permissionsConfig;
		
		public enum VehicleCategory
        {
            None,
            LandVehicle,
            AirVehicle,
            WaterVehicle,
            WinterVehicle,
			SiegeVehicle,
			TrainVehicle
		}
		void OnEntityKill(AttackHelicopter attackCopter) => HandleVehicleKill(attackCopter);
		void OnEntityKill(Snowmobile snowmobile) => HandleVehicleKill(snowmobile);
		object CanLootEntity(BasePlayer player, StashContainer stash) => CanLootStorage(player, stash);
		
		void OnEntityEnteredMonument(string monumentID, BaseEntity entity, string category, string oldMonumentID)
        {
			if (_monumentsConfig.TrackedCategories.Contains(category) && _monumentsList.TryGetValue(monumentID, out var monumentData) && monumentData.IsPvP)
				OnEntityEnterPVP(entity);
		}
		
		private void UpdatePvPBars()
        {
			if (_pvpBar == null) return;
			_pvpBar.Remove(10);
            _pvpBar.Remove(9);
            _pvpBar.Remove(8);
            if (!string.IsNullOrWhiteSpace(_config.BarPvP.Image_Sprite))
                _pvpBar.Add(10, _config.BarPvP.Image_Sprite);
            else if (!string.IsNullOrWhiteSpace(_config.BarPvP.Image_Local))
                _pvpBar.Add(9, _config.BarPvP.Image_Local);
            else
                _pvpBar.Add(8, _imgLibIsLoaded && _config.BarPvP.Image_Url.StartsWithAny(HttpScheme) ? Bar_PvP : _config.BarPvP.Image_Url);
			
			if (_pvpDelayBar != null)
				_pvpDelayBar.Clear();
			var progressBar = _config.ProgressBarPvP;
			_pvpDelayBar = new Dictionary<int, object>(_pvpBar)
			{
                { 32, progressBar.Progress_Reverse },
                { 33, progressBar.Progress_Color },
                { -33, progressBar.Progress_Transparency },
                { 34, progressBar.Progress_OffsetMin },
                { 35, progressBar.Progress_OffsetMax }
            };
			_pvpDelayBar[2] = "TimeProgressCounter";
			_pvpDelayBar[6] = progressBar.Main_Color;
			
			if (progressBar.Main_Color.StartsWith("#"))
				_pvpDelayBar[-6] = progressBar.Main_Transparency;
			else
				_pvpDelayBar.Remove(-6);
		}
		
		void OnRandomRaidStart(string waveType, Vector3 pos)
		{
			var tcList = Pool.Get<List<BuildingPrivlidge>>();
			Vis.Entities(pos, 1f, tcList);
			if (tcList.Any())
                _randomRaidsList[pos.ToString()] = new RRData(tcList[0]);
			Pool.FreeUnmanaged(ref tcList);
		}

		object CanLootEntity(BasePlayer player, MixingTable table) => CanLootStorage(player, table);
		void OnEntityKill(ScrapTransportHelicopter scrapCopter) => HandleVehicleKill(scrapCopter);
		
		object OnButtonPress(PressButton button, BasePlayer player)
        {
			if (button.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, button.net.ID.Value))
            {
				
				object result = CanInteractSharedEntity(player, button);
				if (result != null)
                {
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                    return true;
                }
                return null;
            }
            return null;
		}
		
		public class RRData
		{
			public BuildingPrivlidge Privlidge { get; set; }
			public HashSet<uint> BuildingIDs = Pool.Get<HashSet<uint>>();
			public HashSet<ulong> Raiders = Pool.Get<HashSet<ulong>>();
			public ulong PatrolID = 0;
			public List<ulong> PlayersList = Pool.Get<List<ulong>>();
			
			public RRData(BuildingPrivlidge privlidge)
			{
				Privlidge = privlidge;
				UpdateBuildings();
				foreach (var userID in privlidge.authorizedPlayers)
				{
					_rrAllPlayers[userID] = this;
					PlayersList.Add(userID);
				}
			}
			
			public void UpdateBuildings()
			{
				BuildingIDs.Clear();
				BuildingIDs.Add(Privlidge.buildingID);
				var build = Privlidge.GetBuilding();
				if (build == null || build.buildingBlocks == null) return;
				var fList = Pool.Get<List<BuildingBlock>>();
				foreach (var block in build.buildingBlocks)
				{
					if (block.ShortPrefabName != "foundation") continue;
					Vis.Entities(block.transform.position, 18f, fList);
					foreach (var subBlock in fList)
					{
						if (subBlock.ShortPrefabName == "foundation")
							BuildingIDs.Add(subBlock.buildingID);
					}
					fList.Clear();
				}
				Pool.FreeUnmanaged(ref fList);
			}
			
			public void OnPatrolDestroy(Vector3 pos)
			{
				ulong ownerID = PlayersList.Any() ? PlayersList[0] : 0uL;
				var crates = Pool.Get<List<LockedByEntCrate>>();
				Vis.Entities(pos, 5f, crates);
				if (crates.Any())
				{
					foreach (var container in crates)
					{
						if (container.OwnerID == 0uL)
							container.OwnerID = ownerID;
					}
				}
				Pool.FreeUnmanaged(ref crates);
				var debris = Pool.Get<List<HelicopterDebris>>();
				Vis.Entities(pos, 5f, debris);
				foreach (var helicopterDebris in debris)
					helicopterDebris.OwnerID = ownerID;
				Pool.FreeUnmanaged(ref debris);
				_rrallPatrols.Remove(PatrolID);
				PatrolID = 0;
			}
			
			public void Destroy()
			{
				foreach (var netID in Raiders)
					_rrAllRaiders.Remove(netID);
				_rrallPatrols.Remove(PatrolID);
				foreach (var userID in PlayersList)
					_rrAllPlayers.Remove(userID);
				Pool.FreeUnmanaged(ref BuildingIDs);
				Pool.FreeUnmanaged(ref Raiders);
				Pool.FreeUnmanaged(ref PlayersList);
			}
		}
		void OnTeamLeave(RelationshipManager.PlayerTeam team, BasePlayer player) => OnTeamUpdated(player);
		
		private void LoadRBsConfig()
        {
			_rbsPath = $"{Name}{Path.DirectorySeparatorChar}RaidableBasesConfig";
			try { _rbsConfig = Interface.Oxide.DataFileSystem.ReadObject<RBConfig>(_rbsPath); }
			catch (Exception ex) { UnityEngine.Debug.LogException(ex); }
			
			if (_rbsConfig == null || _rbsConfig.Version.IsDefault())
				_rbsConfig = new RBConfig() { Version = _rbsVersion };
			else if (_rbsConfig.Version < _rbsVersion)
            {
				string rbCfgPath = Path.Combine(Interface.Oxide.DataDirectory, $"{_rbsPath}.json");
                if (File.Exists(rbCfgPath))
                {
                    string rbCfgOldPath = Path.Combine(Interface.Oxide.DataDirectory, $"{Name}{Path.DirectorySeparatorChar}_old_RaidableBasesConfig({_rbsConfig.Version}).json");
                    if (File.Exists(rbCfgOldPath))
                        File.Delete(rbCfgOldPath);
                    PrintWarning($"Your settings version for raidable bases is outdated. The config file has been updated, and your old settings have been saved in {rbCfgOldPath}");
                    File.Move(rbCfgPath, rbCfgOldPath);
                }
				_rbsConfig = new RBConfig() { Version = _rbsVersion };
			}
			
			RBSettings rbSettings;
			if (_rbsConfig.Settings != null && _rbsConfig.Settings.Any())
			{
				for (int i = _rbsConfig.Settings.Count - 1; i >= 0; i--)
				{
					rbSettings = _rbsConfig.Settings[i];
					if (rbSettings == null)
                    {
						_rbsConfig.Settings.RemoveAt(i);
						continue;
                    }
					
					if (string.IsNullOrWhiteSpace(rbSettings.TextKey))
						rbSettings.TextKey = "MsgRaidableBasesEasy";
					if (rbSettings.Levels.IsNullOrEmpty())
						rbSettings.Levels = new int[] { i };
					rbSettings.OfferTime = Mathf.Clamp(rbSettings.OfferTime, 1f, 15f);
					rbSettings.Price = Math.Max(rbSettings.Price, 0d);
					rbSettings.PvPDelay = Math.Max(rbSettings.PvPDelay, 0f);
					if (rbSettings.Bar == null)
                        rbSettings.Bar = new BarSettings();
                    if (rbSettings.ProgressBar == null)
                        rbSettings.ProgressBar = new ProgressBarSettings();
				}
			}
			else
			{
				_rbsConfig.Settings = new List<RBSettings>()
                {
                    new RBSettings()
                    {
                        Levels = new int[] { 0 },
						Price = 75d,
                        Bar = new BarSettings()
                        {
                            Main_Color = "#60BF91",
                            Main_Transparency = 0.7f,
                            Main_Material = "assets/content/ui/uibackgroundblur.mat",
                            Image_Url = "https://i.imgur.com/5lkjFih.png",
                            Image_Local = "RealPVE_RaidableBases_Default",
                            Image_Color = "#94EDC2",
                            Text_Color = "#94EDC2",
                            SubText_Color = "#94EDC2"
                        },
                        ProgressBar = new ProgressBarSettings() { Progress_Color = "#60BF91" }
                    },
                    new RBSettings()
                    {
                        Levels = new int[] { 1 },
						TextKey = "MsgRaidableBasesMedium",
						Price = 150d,
                        Bar = new BarSettings()
                        {
                            Main_Color = "#EFA287",
                            Main_Transparency = 0.7f,
                            Main_Material = "assets/content/ui/uibackgroundblur.mat",
                            Image_Url = "https://i.imgur.com/5lkjFih.png",
                            Image_Local = "RealPVE_RaidableBases_Default",
                            Image_Color = "#FAE197",
                            Text_Color = "#FAE197",
                            SubText_Color = "#FAE197"
                        },
                        ProgressBar = new ProgressBarSettings() { Progress_Color = "#EFA287" }
                    },
                    new RBSettings()
                    {
                        Levels = new int[] { 2 },
						TextKey = "MsgRaidableBasesHard",
						Price = 225d,
                        Bar = new BarSettings()
                        {
                            Main_Color = "#F75C5F",
                            Main_Transparency = 0.7f,
                            Main_Material = "assets/content/ui/uibackgroundblur.mat",
                            Image_Url = "https://i.imgur.com/5lkjFih.png",
                            Image_Local = "RealPVE_RaidableBases_Default",
                            Image_Color = "#FABBC4",
                            Text_Color = "#FABBC4",
                            SubText_Color = "#FABBC4"
                        },
                        ProgressBar = new ProgressBarSettings() { Progress_Color = "#F75C5F" }
                    },
                    new RBSettings()
                    {
                        Levels = new int[] { 3 },
						TextKey = "MsgRaidableBasesExpert",
						Price = 300d,
                        Bar = new BarSettings()
                        {
                            Main_Color = "#E1402A",
                            Main_Transparency = 0.7f,
                            Main_Material = "assets/content/ui/uibackgroundblur.mat",
                            Image_Url = "https://i.imgur.com/5lkjFih.png",
                            Image_Local = "RealPVE_RaidableBases_Default",
                            Image_Color = "#FFD272",
                            Text_Color = "#FFD272",
                            SubText_Color = "#FFD272"
                        },
                        ProgressBar = new ProgressBarSettings() { Progress_Color = "#E1402A" }
                    },
                    new RBSettings()
                    {
                        Levels = new int[] { 4 },
						TextKey = "MsgRaidableBasesNightmare",
						Price = 400d,
                        Bar = new BarSettings()
                        {
                            Main_Color = "#D0B321",
                            Main_Transparency = 0.7f,
                            Main_Material = "assets/content/ui/uibackgroundblur.mat",
                            Image_Url = "https://i.imgur.com/5lkjFih.png",
                            Image_Local = "RealPVE_RaidableBases_Default",
                            Image_Color = "#FFEC5A",
                            Text_Color = "#FFEC5A",
                            SubText_Color = "#FFEC5A"
                        },
                        ProgressBar = new ProgressBarSettings() { Progress_Color = "#D0B321" }
                    }
                };
			}
			
			string textKey;
			for (int i = 0; i < _rbsConfig.Settings.Count; i++)
            {
				rbSettings = _rbsConfig.Settings[i];
				textKey = rbSettings.TextKey;
				if (!string.IsNullOrWhiteSpace(textKey))
				{
					_enLang.TryAdd(textKey, textKey);
					_ruLang.TryAdd(textKey, textKey);
				}
				rbSettings.ImgLibId = $"{RBUI}_{string.Join("_", rbSettings.Levels)}";
			}
			
			List<CuiElement> uiList = null;
			string uiOfferPath = $"{Name}{Path.DirectorySeparatorChar}UI{Path.DirectorySeparatorChar}RaidableBasesOffer";
			try { uiList = Interface.Oxide.DataFileSystem.ReadObject<List<CuiElement>>(uiOfferPath); }
            catch (Exception ex) { UnityEngine.Debug.LogException(ex); }
			if (uiList == null || !uiList.Any())
            {
                uiList = GetDefaultClaimOffer();
                Interface.Oxide.DataFileSystem.WriteObject(uiOfferPath, uiList);
            }
            _rbsUiOffer = ReplacePlaceholders(CuiHelper.ToJson(uiList), RBOfferUI);
			
			SaveRBsConfig();
		}
		
		void OnPlayerRespawned(BasePlayer player)
		{
			
			if (_respawnMessage.TryGetValue(player.userID, out var msg))
			{
				if (_config.GameTips_Enabled)
					player.SendConsoleCommand(Str_Showtoast, (int)GameTip.Styles.Blue_Long, msg, string.Empty);
				else
					player.ChatMessage(msg);
				_respawnMessage.Remove(player.userID);
			}
		}
		private HashSet<string> _deployables = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
		private void SaveVanillaEventsConfig() => Interface.Oxide.DataFileSystem.WriteObject(_vanillaEventsPath, _vanillaEventsConfig);
		private bool IsFriend(string playerID, string friendID) => _friendsIsLoaded && (bool)(Friends.Call(Str_IsFriend, playerID, friendID) ?? false);
		void OnEntitySpawned(Bike bike) => HandleVehicleSpawn(bike);

        public static bool TryGetEntityPrivilege(BasePlayer player, out SimplePrivilege result)
        {
            var obb = new OBB(player.transform.position, player.transform.lossyScale, player.transform.rotation, player.bounds);
            LegacyShelter other = null;
            var shelterList = Pool.Get<List<LegacyShelter>>();
            Vis.Entities(obb.position, 10f, shelterList, 2097152);
            for (int i = 0; i < shelterList.Count; i++)
            {
                var shelter = shelterList[i];
                if (shelter.isServer != player.isServer || !shelter.IsOlderThan(other) || obb.Distance(shelter.WorldSpaceBounds()) > 3f) continue;
                other = shelter;
            }
            Pool.FreeUnmanaged(ref shelterList);
            result = other?.GetEntityBuildingPrivilege();
            return result != null;
        }
		object OnRackedWeaponTake(Item item, BasePlayer player, WeaponRack rack) => CanLootWeaponRack(player, rack);
		object OnSaddleSwap(RidableHorse horse, BasePlayer player) => HandleVehicleInteraction(player, horse, "MsgVehicleCantSaddleSwap");
		object OnEntityTakeDamage(BatteringRam batteringRam, HitInfo info) => HandleDamageToVehicle(batteringRam, info);
		
		object CanUpdateSign(BasePlayer player, Signage sign) => _unrestrictedLooters.Contains(player.userID) ? true : (!sign.OwnerID.IsSteamId() || IsEntityInPvP(player.userID, sign.net.ID.Value) ? null : CanInteractSharedEntity(player, sign));
		
		private object HandleVehicleInteractionM(BasePlayer player, BaseEntity vehicle, string textKey)
        {
            if (!IsEntityInPvP(player.userID, vehicle.net.ID.Value))
            {
                object result = null;
                if (_vehiclesList.TryGetValue(vehicle.net.ID.Value, out var vehicleData))
                    result = vehicleData.CanInteract(player);
                else if (vehicle.OwnerID.IsSteamId())
                    result = CanInteractSharedEntity(player, vehicle);
				if (result != null && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
                    result = monumentData.CanLoot(player);
				
				if (result != null)
                    SendMessageText(player, lang.GetMessage(textKey, this, player.UserIDString));
                return result;
            }
            return null;
        }

		private float GetHackableCrateSkip(string userID)
		{
			PvEPermission perm;
            float result = _permissionsConfig.PermissionsList[0].HackableCrateSkip, max = HackableLockedCrate.requiredHackSeconds;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				if (result >= max)
				{
					result = max;
					break;
				}
				perm = _permissionsConfig.PermissionsList[i];
                if (perm.HackableCrateSkip > result && permission.UserHasPermission(userID, perm.Name))
                    result = perm.HackableCrateSkip;
            }
            return result;
		}
		
		object OnDispenserGather(ResourceDispenser dispenser, BasePlayer player, Item item)
        {
			if (dispenser?.baseEntity is HelicopterDebris debris && debris.IsValid() && debris.skinID == 0uL && debris.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, debris.net.ID.Value))
            {
				object result = CanInteractSharedEntity(player, debris) == null || IsFriend(player.UserIDString, debris.OwnerID.ToString()) ? null : false;
				if (result != null)
                    SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                return result;
            }
            return null;
		}
		
		void OnRaidableBaseEnded(Vector3 pos)
		{
			if (_rbList.TryGetValue(pos.ToString(), out var rbData) && rbData != null)
				rbData.Destroy();
			else
				_rbList.Remove(pos.ToString());
		}
		object OnTurretAuthorize(AutoTurret turret, BasePlayer player) => !turret.OwnerID.IsSteamId() || _unrestrictedLooters.Contains(player.userID) || IsEntityInPvP(player.userID, turret.net.ID.Value) ? null : CanInteractSharedEntity(player, turret);
		
		private static readonly string[] _cmdKeysMain = { "autobuy", "pickup", "share", "team", "vehicle" },
			_cmdKeysMainVehicle = { "list", "find", "unlink", "clear" };
		
		object CanLootEntity(BasePlayer player, ModularCarGarage garage)
		{
			if (garage.carOccupant == null)
            {
				SendMessageText(player, lang.GetMessage("MsgVehicleCarGarageEmpty", this, player.UserIDString));
				return false;
			}
			return null;
		}
		public static bool RealPVE_CanTakeCollisionDamage(Drone drone, Collision collision)
        {
			var entity = collision.gameObject?.ToBaseEntity();
			if (entity.IsValid() && entity is DroppedItem)
				return false;
			return true;
		}
		
		private void ShowMonumentOffer(BasePlayer player, MonumentData monumentData, double price)
		{
			DestroyUI(player, MonumentOfferUI);
			CuiHelper.AddUi(player, ReplacePlaceholders(_monumentsUiOffer, null, (string)ImageLibrary?.Call("GetImage", MonumentOfferUI),
				string.Format(lang.GetMessage("MsgMonumentOfferTitle", this, player.UserIDString), new string[] { monumentData.GetMonumentName(player.userID) }),
				string.Format(lang.GetMessage("MsgMonumentOfferDescription", this, player.UserIDString), new string[] { string.Format(_config.PriceFormat, price) }),
				$"{_commandUI} monument pay {monumentData.MonumentID}"));
			_playerUI[player.userID].Add(MonumentOfferUI);
		}
		
		object CanUseLockedEntity(BasePlayer player, BaseLock baseLock)
        {
			if (_unrestrictedLooters.Contains(player.userID)) return true;
			if (baseLock.OwnerID.IsSteamId() && !IsEntityInPvP(player.userID, baseLock.net.ID.Value))
				return CanInteractSharedEntity(player, baseLock);
			return null;
		}
				
		        object OnEventJoin(BasePlayer player)
        {
			if (_pvpPlayers.ContainsKey(player.userID))
				return lang.GetMessage("MsgArenaWhilePvP", this, player.UserIDString);
			return null;
		}
		private int _defaultBeds = 15, _defaultShelters = 1, _defaultTurrets = 12;
		object CanUpdateSign(BasePlayer player, CarvablePumpkin pumpkin) => _unrestrictedLooters.Contains(player.userID) ? true : (!pumpkin.OwnerID.IsSteamId() || IsEntityInPvP(player.userID, pumpkin.net.ID.Value) ? null : CanInteractSharedEntity(player, pumpkin));
		private string _harborEventMonument = string.Empty;
		object OnEntityTakeDamage(Snowmobile snowmobile, HitInfo info) => HandleDamageToVehicle(snowmobile, info);
		private static VanillaEventsConfig _vanillaEventsConfig;
		
		
		
		void OnBuildingSplit(BuildingManager.Building building, uint newBuildingId)
		{
			var oldID = building.ID;
			foreach (var rrData in _randomRaidsList.Values)
            {
				if (rrData.BuildingIDs.Contains(oldID))
                {
					rrData.BuildingIDs.Add(newBuildingId);
					break;
				}
			}
		}
		
		public static void ModDroneWeaponThrown(ThrownWeapon __instance, Vector3 eyePos, Vector3 eyeDir, BasePlayer owningPlayer, ref BaseEntity thrownEntity)
        {
			if (__instance != null && thrownEntity != null && thrownEntity.OwnerID == 0uL)
				thrownEntity.OwnerID = owningPlayer?.userID ?? __instance.OwnerID;
		}
		
		private void InitVanillaEvents()
        {
			bool patrolEnabled = _vanillaEventsConfig.PatrolHelicopter.IsEnabled;
			bool bradleyEnabled = _vanillaEventsConfig.BradleyAPC.IsEnabled;
			if (patrolEnabled || bradleyEnabled)
			{
				foreach (var entity in BaseNetworkable.serverEntities)
                {
					if (entity is PatrolHelicopter patrol)
                    {
                        if (patrolEnabled)
							new EventData(patrol);
					}
                    else if (entity is BradleyAPC bradley)
                    {
                        if (bradleyEnabled)
							new EventData(bradley);
					}
                }
				Subscribe(nameof(OnCrateSpawned));
                Subscribe(nameof(OnGibsSpawned));
			}
			if (patrolEnabled)
				Subscribe(nameof(OnNoGoZoneAdded));
			if (bradleyEnabled)
			{
				Subscribe(nameof(CanBradleyApcTarget));
				Subscribe(nameof(OnScientistInitialized));
				Subscribe(nameof(OnScientistRecalled));
			}
		}
		
		void OnCrateSpawned(PatrolHelicopter patrol, LockedByEntCrate lockedCrate)
        {
			if (_eventsList.TryGetValue(patrol.net.ID.Value, out var eventData))
				eventData.LockedCrateSpawned(lockedCrate);
		}
		
		public class TeamData
        {
			public ulong TeamID { get; set; }
			public bool FriendlyFire { get; set; }
			
			public TeamData() {}
			public TeamData(ulong id, bool fFire = false)
			{
				TeamID = id;
				FriendlyFire = fFire;
			}
		}
		private void Command_Admin(IPlayer player, string command, string[] args)
        {
			if (!player.IsAdmin && !permission.UserHasPermission(player.Id, PERMISSION_ADMIN)) return;
			int index = args != null && args.Length > 0 ? Array.FindIndex(_cmdKeysAdmin, key => key.Equals(args[0], StringComparison.OrdinalIgnoreCase)) : -1;
            if (index < 0)
            {
				player.Reply(lang.GetMessage("CmdAdmin", this, player.Id));
				return;
            }
			
			string[] newArgs = args.Skip(1).Concat(Enumerable.Repeat(string.Empty, Math.Max(0, 6 - args.Length))).ToArray();
			if (index == 0)
				Command_AdminAutoBuy(player, newArgs);
			else if (index == 1)
				Command_AdminConfig(player, newArgs);
			else if (index == 2)
				Command_AdminLoot(player, newArgs);
			else if (index == 3)
				Command_AdminMonument(player, newArgs);
			else if (index == 4)
				Command_AdminPermissions(player, newArgs);
			else if (index == 5)
				Command_AdminPickup(player, newArgs);
			else if (index == 6)
				Command_AdminShare(player, newArgs);
			else if (index == 7)
				Command_AdminTC(player, newArgs);
			else if (index == 8)
			{
				
				if (args.Length > 1 && args[1].Equals("types", StringComparison.OrdinalIgnoreCase))
                {
                    player.Reply(string.Format(lang.GetMessage("CmdAdminVehicleTypes", this, player.Id),
                        string.Join(", ", Enum.GetValues(typeof(VehicleType)).Cast<VehicleType>().Where(vT => vT != VehicleType.None && vT != VehicleType.Train).Select(vT => vT.ToString()).ToArray())));
                }
			}
		}
		
		void OnRaidableBaseStarted(Vector3 pos, int level, bool allowPVP, string raidID, float f1, float f2, float loadTime, ulong ownerID, BasePlayer player, List<BasePlayer> raiders, List<BasePlayer> intruders, List<BaseEntity> entities, string baseName, DateTime spawnDateTime, DateTime despawnDateTime, float radius, int lootRemain)
		{
			raidID = pos.ToString();
			_rbList[raidID] = new RBData(raidID, pos, level, allowPVP, radius, ownerID, despawnDateTime, lootRemain, intruders);
		}
		
		public static void ModRemoteEntityControl(RemoteControlEntity __instance)
        {
			
			if (!__instance.IsValid() || __instance is not Drone drone || drone.triggers == null) return;
			ulong userID = drone.ToPlayer()?.userID ?? drone.OwnerID;
			if (!userID.IsSteamId() || Instance.HandleEntityEnterTrap(drone, userID)) return;
			for (int i = drone.triggers.Count - 1; i >= 0; i--)
            {
                if (drone.triggers[i] is TargetTrigger targetTrigger)
                    targetTrigger.OnEntityLeave(drone);
            }
		}
		
		void OnPlayerSleep(BasePlayer player)
        {
			if (!player.InSafeZone()) return;
			NextTick(() =>
			{
				if (player != null && !player.IsConnected)
					player.CancelInvoke(player.ScheduledDeath);
			});
		}
		
		object OnEntityTakeDamage(BasePlayer victim, HitInfo info)
        {
			if (info == null || !victim.userID.IsSteamId()) return null;
			switch (TryGetAttacker(info, out var initiator))
            {
                case AttackerIndex.PatrolHelicopter:
                case AttackerIndex.BradleyAPC:
                    
                    if (initiator.IsValid() && _eventsList.TryGetValue(initiator.net.ID.Value, out var eventData) && !eventData.CanBeAttackedBy(victim))
						info.Urma();
					break;
                case AttackerIndex.BasePlayer:
					var attacker = (BasePlayer)initiator;
					if (victim.userID.IsSteamId() && attacker.userID.IsSteamId() && !IsPlayerInPvP(attacker.userID, victim.userID) && !UrishMumkin(attacker, victim.userID))
						info.Urma();
					break;
                case AttackerIndex.PlayerOwnerMissing:
					info.Urma();
					break;
				case AttackerIndex.BaseEntity:
					if (_config.ProtectSleepers && victim.IsSleeping())
					{
						if (initiator is BaseAnimalNPC || initiator is BaseNPC2)
						{
							info.Urma();
							initiator.AdminKill();
						}
					}
					break;
			}
			return null;
		}

		void OnExcavatorSuppliesRequested(ExcavatorSignalComputer computer, BasePlayer player, BaseEntity cargoPlane)
		{
			if (player.IsValid() && cargoPlane != null && _monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData) && !monumentData.IsPvP)
				cargoPlane.OwnerID = player.userID;
		}

		private object CanLootByOwnerID(BasePlayer player, BaseEntity entity)
		{
			if (entity.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, entity.net.ID.Value))
			{
				object result = CanInteractSharedEntity(player, entity);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return result;
			}
			return null;
		}
		
		private object CanLootStorage(BasePlayer player, StorageContainer container, bool isPreChecked = false)
        {
			if (!isPreChecked)
            {
				if (_unrestrictedLooters.Contains(player.userID)) return AdminOpenLoot(player, container);
				if (IsEntityInPvP(player.userID, container.net.ID.Value)) return null;
			}
			object result = null;
            if (container.OwnerID.IsSteamId())
                result = CanInteractSharedEntity(player, container);
            else if (_monumentsList.TryGetValue(GetEntityMonument(container), out var monumentData))
                result = monumentData.CanLoot(player);
            else if (TryGetRaidBase(container.transform.position, out var rbData) && !rbData.CanInteractWithRaid(player.userID))
                result = false;
			
			if (result != null)
				SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
			return result;
		}
		object OnEntityTakeDamage(BaseSubmarine submarine, HitInfo info) => HandleDamageToVehicle(submarine, info);
        private bool MakeWithdraw(string userID, double amount) => (bool)(Economics?.Call("Withdraw", userID, amount) ?? false);
		
		private string _newbiePath = string.Empty;
		
		object OnSiegeWeaponDoorOpen(BatteringRam batteringRam, BasePlayer player) => HandleVehicleInteraction(player, batteringRam, "MsgVehicleCantOpen");
		
		private string _permissionsPath = string.Empty;
		void OnEntitySpawned(ModularCar car) => HandleVehicleSpawn(car);
		
		private bool TryGetPlayer(IPlayer initiator, string nameOrId, out IPlayer result, bool skipInitiator = true, int limit = -1, bool all = true)
        {
            result = null;
            if (!TryGetPlayers(nameOrId, out var tPlayers, skipInitiator ? initiator : null, limit, all))
				SendMessageText(initiator, string.Format(lang.GetMessage("CmdPlayerNotFound", this, initiator.Id), nameOrId));
			else if (tPlayers.Count > 1)
				initiator.Reply(string.Format(lang.GetMessage("CmdMultiplePlayers", this, initiator.Id), nameOrId, string.Join(", ", tPlayers.Select(t => t.Name).ToArray())));
			else
                result = tPlayers[0];
            return result != null;
        }
		
		void OnPlayerDeath(BasePlayer player, HitInfo info)
        {
            if (player.userID.IsSteamId() && info != null && info.Initiator is BaseEntity killerEnt)
            {
                if (killerEnt is ScientistNPC)
                {
                    if (killerEnt.skinID == _bradleySkinId && _eventScientistsList.TryGetValue(killerEnt.net.ID, out var eventData) && eventData.CanBeAttackedBy(player))
                        eventData.OnLooterDeath(player);
                }
                else if (killerEnt is BradleyAPC || killerEnt is PatrolHelicopter)
                {
                    if (_eventsList.TryGetValue(killerEnt.net.ID.Value, out var eventData) && eventData.CanBeAttackedBy(player))
                        eventData.OnLooterDeath(player);
                }
            }
        }
		
		private static bool TryGetEntity(string argID, out BaseEntity entity, BasePlayer player = null)
        {
			entity = ulong.TryParse(argID, out var entID) ? BaseNetworkable.serverEntities.Find(new NetworkableId(entID)) as BaseEntity : null;
            if ((entity == null || entity is BasePlayer) && player != null)
				entity = Physics.Raycast(player.eyes.HeadRay(), out RaycastHit hit, 10f, Physics.DefaultRaycastLayers, QueryTriggerInteraction.Ignore) ? hit.GetEntity() : null;
            if (entity != null)
            {
				if (entity is BasePlayer)
					entity = null;
				else if (entity is BaseVehicleModule module)
                {
					var parentVeh = module.VehicleParent();
					if (parentVeh.IsValid())
						entity = BaseNetworkable.serverEntities.Find(new NetworkableId(parentVeh.net.ID.Value)) as BaseEntity;
				}
			}
            return entity != null;
        }
		void OnGroupPermissionRevoked(string groupName, string permName) => OnGroupPermissionGranted(groupName, permName);
		object OnEntityTakeDamage(VehicleModuleStorage module, HitInfo info) => HandleDamageToVehicle(module.VehicleParent(), info);
		
		private static void LoadData<T>(string filePath, out T result)
        {
            try { result = Interface.Oxide.DataFileSystem.ReadObject<T>(filePath); }
            catch (Exception ex) { UnityEngine.Debug.LogException(ex); result = default; }
            if (result == null)
                result = default;
        }
		
		private void LoadRBsImages()
		{
			RBSettings rbSettings;
			if (_statusIsLoaded)
			{
				var imgList = new HashSet<string>();
				for (int i = 0; i < _rbsConfig.Settings.Count; i++)
                {
					rbSettings = _rbsConfig.Settings[i];
					if (!string.IsNullOrWhiteSpace(rbSettings.Bar.Image_Local))
                        imgList.Add(rbSettings.Bar.Image_Local);
				}
				if (imgList.Any())
					AdvancedStatus?.Call("LoadImages", imgList);
			}
			if (_imgLibIsLoaded)
			{
				var imgList = new Dictionary<string, string>();
				BarSettings barSettings;
				for (int i = 0; i < _rbsConfig.Settings.Count; i++)
                {
                    rbSettings = _rbsConfig.Settings[i];
					barSettings = rbSettings.Bar;
					if (string.IsNullOrWhiteSpace(barSettings.Image_Sprite) && string.IsNullOrWhiteSpace(barSettings.Image_Local) && barSettings.Image_Url.StartsWithAny(HttpScheme))
                        imgList.Add(rbSettings.ImgLibId, barSettings.Image_Url);
                }
				if (imgList.Any())
					ImageLibrary?.Call("ImportImageList", Name, imgList, 0uL, true);
			}
		}
		
		object OnPlayerDropActiveItem(BasePlayer player, Item item) => player.userID.IsSteamId() ? true : null;
		
		void OnScientistInitialized(BradleyAPC bradley, ScientistNPC scientist, Vector3 spawnPos)
        {
			if (_eventsList.TryGetValue(bradley.net.ID.Value, out var eventData))
			{
				_eventScientistsList[scientist.net.ID] = eventData;
				scientist.skinID = _bradleySkinId;
			}
		}
		object OnEntityTakeDamage(VehicleModuleCamper module, HitInfo info) => HandleDamageToVehicle(module.VehicleParent(), info);
		
		public class PvEPermission
		{
			[JsonProperty(PropertyName = "Permission Name")]
			public string Name { get; set; } = string.Empty;
			
			[JsonProperty(PropertyName = "Bypass Queue")]
			public bool BypassQueue { get; set; }
			
			[JsonProperty(PropertyName = "Unlock all static respawn points")]
            public bool UnlockStaticRespawn { get; set; }
			
			private int _beds;
			[JsonProperty(PropertyName = "Limit of beds")]
			public int Beds
			{
				get => _beds;
				set => _beds = value < 0 ? 0 : value;
            }
			
			private int _shelters;
			[JsonProperty(PropertyName = "Limit of shelters")]
			public int Shelters
			{
				get => _shelters;
				set => _shelters = value < 0 ? 0 : value;
            }
			
			private int _turrets;
			[JsonProperty(PropertyName = "Limit of auto turrets")]
			public int Turrets
			{
				get => _turrets;
				set => _turrets = value < 0 ? 0 : value;
            }
			
			private float _hackableCrateSkip;
			[JsonProperty(PropertyName = "Seconds that will be skipped when opening HackableLockedCrate")]
			public float HackableCrateSkip
			{
				get => _hackableCrateSkip;
				set => _hackableCrateSkip = value < 0f ? 0f : value;
            }
			
			private float _monumentMultiplier;
			[JsonProperty(PropertyName = "Monuments price multiplier")]
			public float Monument_Multiplier
			{
				get => _monumentMultiplier;
				set => _monumentMultiplier = value < 0f ? 0f : value;
            }
			
			private float _eventMultiplier;
			[JsonProperty(PropertyName = "Events price multiplier")]
			public float Event_Multiplier
			{
				get => _eventMultiplier;
				set => _eventMultiplier = value < 0f ? 0f : value;
            }
			
			private int _rbLimit;
			[JsonProperty(PropertyName = "Limit of RaidableBases(at the time)")]
			public int RB_Limit
			{
				get => _rbLimit;
				set => _rbLimit = value < 0 ? 0 : value;
            }
			
			private float _rbMultiplier;
			[JsonProperty(PropertyName = "RaidableBases price multiplier")]
			public float RB_Multiplier
			{
				get => _rbMultiplier;
				set => _rbMultiplier = value < 0f ? 0f : value;
			}
			
			[JsonProperty(PropertyName = "Vehicles settings")]
			public Dictionary<VehicleType, VehicleProperties> Allowed_Vehicles { get; set; }
			
			public PvEPermission() {}
			public PvEPermission(string name, bool queue, bool unlockRespawn, int beds, int shelters, int turrets, float hackable, float monuments, float events, int rb_limit, float rb_multiplier, Dictionary<VehicleType, VehicleProperties> vehicles)
            {
                Name = name;
                BypassQueue = queue;
                UnlockStaticRespawn = unlockRespawn;
				Beds = beds;
                Shelters = shelters;
                Turrets = turrets;
                HackableCrateSkip = hackable;
                Monument_Multiplier = monuments;
                Event_Multiplier = events;
                RB_Limit = rb_limit;
                RB_Multiplier = rb_multiplier;
                Allowed_Vehicles = vehicles;
            }
			public PvEPermission(string name, bool queue, bool unlockRespawn, int beds, int shelters, int turrets, float hackable, float monuments, float events, int rb_limit, float rb_multiplier, int veh_limit, float veh_multiplier)
			{
				Name = name;
				BypassQueue = queue;
				UnlockStaticRespawn = unlockRespawn;
				Beds = beds;
				Shelters = shelters;
				Turrets = turrets;
				HackableCrateSkip = hackable;
				Monument_Multiplier = monuments;
				Event_Multiplier = events;
				RB_Limit = rb_limit;
				RB_Multiplier = rb_multiplier;
				Allowed_Vehicles = InitVehicleLimits(veh_limit, veh_multiplier);
			}
			
			public Dictionary<VehicleType, VehicleProperties> InitVehicleLimits(int veh_limit, float veh_multiplier)
			{
				var result = new Dictionary<VehicleType, VehicleProperties>()
				{
					{ VehicleType.Horse, new VehicleProperties(veh_limit, 10 * veh_multiplier) },
					{ VehicleType.Bike, new VehicleProperties(veh_limit, 5 * veh_multiplier) },
					{ VehicleType.MotorBike, new VehicleProperties(veh_limit, 20 * veh_multiplier) },
					{ VehicleType.Car, new VehicleProperties(veh_limit, 25 * veh_multiplier) },
					{ VehicleType.Balloon, new VehicleProperties(veh_limit, 20 * veh_multiplier) },
					{ VehicleType.Minicopter, new VehicleProperties(veh_limit, 25 * veh_multiplier) },
					{ VehicleType.TransportHeli, new VehicleProperties(veh_limit, 30 * veh_multiplier) },
					{ VehicleType.AttackHeli, new VehicleProperties(veh_limit, 30 * veh_multiplier) },
					{ VehicleType.RHIB, new VehicleProperties(veh_limit, 25 * veh_multiplier) },
					{ VehicleType.TugBoat, new VehicleProperties(veh_limit, 50 * veh_multiplier) },
					{ VehicleType.RowBoat, new VehicleProperties(veh_limit, 20 * veh_multiplier) },
					{ VehicleType.SubmarineTwo, new VehicleProperties(veh_limit, 30 * veh_multiplier) },
					{ VehicleType.SubmarineOne, new VehicleProperties(veh_limit, 25 * veh_multiplier) },
					{ VehicleType.Snowmobile, new VehicleProperties(veh_limit, 20 * veh_multiplier) },
					{ VehicleType.BatteringRam, new VehicleProperties(veh_limit, 25 * veh_multiplier) }
				};
				return result;
			}
			
			public PvEPermission Clone(string name) => new PvEPermission(name, this.BypassQueue, this.UnlockStaticRespawn, this.Beds, this.Shelters, this.Turrets, this.HackableCrateSkip, this.Monument_Multiplier,
				this.Event_Multiplier, this.RB_Limit, this.RB_Multiplier, this.Allowed_Vehicles.ToDictionary(entry => entry.Key, entry => entry.Value.Clone()));
		}
		
		object CanUnlock(BasePlayer player, ModularCarCodeLock carCodeLock, string password)
		{
			var modularCar = carCodeLock?.owner;
			if (modularCar.IsValid())
				return HandleVehicleInteraction(player, modularCar);
			return null;
		}
		
		public static void ModDroppedContainer3(ref DroppedItemContainer __result, string prefab, Vector3 pos, Quaternion rot, params ItemContainer[] containers)
        {
			if (!__result.IsValid()) return;
			BaseEntity entity;
			ulong lastId = 0uL;
			for (int i = 0; i < containers.Length; i++)
			{
				entity = containers[i]?.entityOwner;
				if (entity.IsValid())
                {
					lastId = entity.OwnerID;
					if (lastId.IsSteamId())
						break;
				}
            }
			__result.OwnerID = lastId;
        }
		object CanLootEntity(BasePlayer player, LootableCorpse corpse) => CanLootCombatEntity(player, corpse, corpse.playerSteamID);
		
		private void ForceCompleteBedMission(BasePlayer player)
        {
            using HashSet<StaticRespawnArea>.Enumerator enumerator = StaticRespawnArea.staticRespawnAreas.GetEnumerator();
            if (enumerator.MoveNext() && !enumerator.Current.IsAuthed(player.userID))
            {
                enumerator.Current.Authorize(player.userID);
                player.SendRespawnOptions();
            }

            GetOrCreatePlayerData(player.userID.ToString(), out var playerData);
            BaseMission.MissionInstance missionInstance = null;
            for (int i = 0; i < playerData.Missions.Count; i++)
            {
                missionInstance = playerData.Missions[i];
                if (missionInstance.missionID == _bedMissionId)
                    break;
                missionInstance = null;
            }
            if (missionInstance == null)
            {
                var mission = MissionManifest.GetFromID(_bedMissionId);
                missionInstance = Facepunch.Pool.Get<BaseMission.MissionInstance>();
                playerData.Missions.Add(missionInstance);
                missionInstance.missionID = mission.id;
				missionInstance.startTimeUtcSeconds = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
				missionInstance.status = BaseMission.MissionStatus.Active;
                missionInstance.objectiveStatuses = new BufferList<BaseMission.MissionInstance.ObjectiveStatus>(mission.objectives.Length);
                for (int j = 0; j < mission.objectives.Length; j++)
                    missionInstance.objectiveStatuses.Add(new BaseMission.MissionInstance.ObjectiveStatus());
                mission.MissionStart(missionInstance, player);
                                            }
            if (missionInstance.status != BaseMission.MissionStatus.Completed)
                missionInstance.GetMission().MissionComplete(missionInstance, player);
        }
		object CanLootEntity(BasePlayer player, ResearchTable table) => CanLootStorage(player, table);
		private static RBConfig _rbsConfig;

        
        
        

        
        
        

        
        
        

        
        
        
        
                private const string StatusBarID = "RealPVE_Bar_", BarTimed = "Timed", BarTimeCounter = "TimeCounter", StatusCreateBar = "CreateBar", StatusDeleteBar = "DeleteBar", StatusDeleteAllPluginBars = "DeleteAllPluginBars";
		object OnLockRemove(ModularCar modularCar, BasePlayer player) => false;
		
		object CanBuild(Planner planner, Construction prefab, Construction.Target target)
        {
			var player = target.player;
			if (!player.IsValid()) return null;
			
			if (!_pvpPlayers.ContainsKey(player.userID) && player.BinoMumkin() != null && (!TryGetRaidBase((target.entity?.transform?.position ?? player.transform.position), out var rbData) || (!rbData.CanInteractWithRaid(player.userID) && prefab.prefabID != 2150203378u)))
            {
                player.ShowToast(GameTip.Styles.Error, ConstructionErrors.NoPermission, false);
                return true;
            }
			if (prefab.isSleepingBag)
            {
                int limit = GetBedsLimit(player.UserIDString), total = CountBeds(player.userID);
                if (limit >= 0 && total >= limit)
                {
                    player.ShowToast(GameTip.Styles.Error, SleepingBag.bagLimitReachedPhrase, false);
                    return true;
                }
                NextTick(() => { CheckIfPlaced(player, total, limit, true); });
            }
            else if (prefab.prefabID == 2243018404u)
            {
                int limit = GetSheltersLimit(player.UserIDString), total = LegacyShelter.GetShelterCount(player.userID);
                if (limit >= 0 && total >= limit)
                {
                    player.ShowToast(GameTip.Styles.Error, LegacyShelter.shelterLimitReachedPhrase, false);
                    return true;
                }
                NextTick(() => { CheckIfPlaced(player, total, limit, false); });
            }
			return null;
		}
		
		void OnEntityKill(BradleyAPC bradley)
		{
			if (bradley.IsValid() && _eventsList.TryGetValue(bradley.net.ID.Value, out var eventData))
				eventData.OnParentDestroy(bradley.transform.position);
		}
		private void HookConflict(string message, string stackTrace, UnityEngine.LogType type)
        {
            if (!string.IsNullOrEmpty(message) && !_hooksConflict.Any(message.Contains))
                Facepunch.Output.LogHandler(message, stackTrace, type);
        }
		
		private static void GetOrCreateSharedEntity(PlayerData playerData, ulong netID, out SharedEntity result)
        {
			if (!playerData.SharedEntities.TryGetValue(netID, out result))
				playerData.SharedEntities[netID] = _sharedEntities[netID] = result = new SharedEntity();
		}
		
		private void LoadMonumentsImages()
		{
			if (_statusIsLoaded)
			{
				var imgList = new HashSet<string>();
				foreach (var monumentSettings in _monumentsConfig.MonumentsSettings.Values)
                {
                    if (!string.IsNullOrWhiteSpace(monumentSettings.Bar.Image_Local))
                        imgList.Add(monumentSettings.Bar.Image_Local);
                }
				if (imgList.Any())
					AdvancedStatus?.Call("LoadImages", imgList);
			}
			if (_imgLibIsLoaded)
			{
				var imgList = new Dictionary<string, string>();
				BarSettings barSettings;
				foreach (var kvp in _monumentsConfig.MonumentsSettings)
                {
                    barSettings = kvp.Value.Bar;
                    if (string.IsNullOrWhiteSpace(barSettings.Image_Sprite) && string.IsNullOrWhiteSpace(barSettings.Image_Local) && barSettings.Image_Url.StartsWithAny(HttpScheme))
                        imgList.Add($"{StatusBarID}{kvp.Key}", barSettings.Image_Url);
                }
				if (imgList.Any())
					ImageLibrary?.Call("ImportImageList", Name, imgList, 0uL, true);
			}
		}
		
		object OnEntityTakeDamage(BaseCorpse corpse, HitInfo info)
        {
			if (info == null || corpse.parentEnt is BaseAnimalNPC || corpse.parentEnt is BaseNPC2 || TryGetAttacker(info, out var initiator) != AttackerIndex.BasePlayer) return null;
			var attacker = (BasePlayer)initiator;
			if (!attacker.userID.IsSteamId()) return null;
			if (TryGetRaidBase(corpse.transform.position, out var rbData))
			{
				if (!rbData.CanInteractWithRaid(attacker.userID))
					info.Urma();
			}
			else if (_monumentsList.TryGetValue(GetEntityMonument(corpse), out var monumentData))
			{
				if (monumentData.CanLoot(attacker) != null)
					info.Urma();
			}
			else if ((_gatherFlags & BuildingGatherRestrictions.Flesh) != 0 && attacker.BinoMumkin() != null)
				info.Urma();
			return null;
		}
		
		private class PermissionConfig
        {
            [JsonProperty(PropertyName = "List of permissions. NOTE: The first permission will be used by default for those who do not have any permissions.")]
            public List<PvEPermission> PermissionsList;

            public Oxide.Core.VersionNumber Version;
        }
		private string[] GetMonumentsByPos(Vector3 pos) => (string[])(MonumentsWatcher?.Call(MonumentGetMonumentsByPos, pos) ?? Array.Empty<string>());
		
		void OnLootEntity(BasePlayer player, StorageContainer container)
		{
			if (container.panelName.Equals("fuelsmall", StringComparison.OrdinalIgnoreCase) && container.GetParentEntity() is BaseEntity parent && parent.IsValid() && parent is not ModularCar &&
				_vehiclesList.TryGetValue(parent.net.ID.Value, out var vehicleData))
			{
				ShowVehiclePanels(player, vehicleData);
			}
		}

		private int GetVehicleLimit(string userID, VehicleType type)
        {
			PvEPermission perm;
			int num, result = _permissionsConfig.PermissionsList[0].Allowed_Vehicles[type].Limit;
			for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				if (result < 0)
					break;
				perm = _permissionsConfig.PermissionsList[i];
				num = perm.Allowed_Vehicles[type].Limit;
                if ((num < 0 || num > result) && permission.UserHasPermission(userID, perm.Name))
                    result = num;
			}
            return result;
		}
		
		public static bool ModFlashExplode(Flashbang __instance)
        {
			if (__instance == null || !__instance.OwnerID.IsSteamId() || IsPlayerInPvP(__instance.OwnerID))
                return true;
            __instance.Explode(__instance.PivotPoint());
            return false;
        }
		
		object OnNoGoZoneAdded(PatrolHelicopterAI heliAI, PatrolHelicopterAI.DangerZone zone) => _eventsList.ContainsKey(heliAI.helicopterBase.net.ID.Value) ? false : null;
		
		public class VehicleProperties
        {
			public int Limit { get; set; }
			private double _price;
			public double Price
            {
                get => _price;
				set => _price = Math.Round(value, 2);
			}
			
			public VehicleProperties() {}
			public VehicleProperties(int limit, double price)
			{
				Limit = limit;
				Price = price;
			}
			
			public VehicleProperties Clone() => new VehicleProperties(this.Limit, this.Price);
		}
		private string _dataVehiclesPath = string.Empty;
        
        		private static HashSet<ulong> _unrestrictedLooters, _autoBuyPlayers, _pickupPlayers;
		
		object OnEntityTakeDamage(PatrolHelicopter patrol, HitInfo info)
        {
			if (info == null || !patrol.IsValid() || TryGetAttacker(info, out var initiator) != AttackerIndex.BasePlayer) return null;
            var attacker = (BasePlayer)initiator;
            if (!attacker.userID.IsSteamId() || IsEntityInPvP(attacker.OwnerID, patrol.net.ID.Value)) return null;
			if (patrol.skinID != 0uL)
			{
				if (patrol.skinID == _rrPluginID)
                {
                    if (_config.RandomRaids_Enabled && _rrallPatrols.TryGetValue(patrol.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(attacker.userID))
						info.Urma();
				}
			}
			else if (_eventsList.TryGetValue(patrol.net.ID.Value, out var patrolData))
            {
                if (patrolData.OwnerID == 0uL)
                {
					double price = patrolData.Settings.Price <= 0d ? 0d : patrolData.Settings.Price * GetEventPriceMultiplier(attacker.UserIDString);
					if (!_economicsIsLoaded || price <= 0d)
                        patrolData.SetNewOwner(attacker);
                    else if (_playerUI.TryGetValue(attacker.userID, out var uiList) && !uiList.Contains(EventOfferUI))
                    {
						if (_vanillaEventsConfig.AutoBuy || _autoBuyPlayers.Contains(attacker.userID))
							attacker.Command($"{_commandUI} event pay {patrolData.ID}");
						else
						{
							ShowEventOffer(attacker, patrolData, price);
							timer.Once(patrolData.Settings.OfferTime, () => { DestroyUI(attacker, EventOfferUI); });
                        }
						attacker.SendEffect();
                    }
					info.Urma();
				}
                else if (!patrolData.CanBeAttackedBy(attacker))
                {
					info.Urma();
					SendMessageText(attacker.IPlayer, string.Format(lang.GetMessage("MsgEventOccupied", this, attacker.UserIDString), lang.GetMessage(patrolData.TypeKey, this, attacker.UserIDString), patrolData.OwnerName));
				}
            }
			return null;
		}
		
		void OnRaidableLootDestroyed(Vector3 pos, float radius, int lootRemain)
		{
			if (_rbList.TryGetValue(pos.ToString(), out var rbData))
				rbData.OnLootUpdated(lootRemain);
		}
		
		void OnPlayerExitedRaidableBase(BasePlayer player, Vector3 pos)
		{
			if (_rbList.TryGetValue(pos.ToString(), out var rbData))
				rbData.OnPlayerExit(player);
		}
		private void Command_AdminTC(IPlayer player, string[] args)
        {
            int index = Array.FindIndex(_cmdKeysAdminTC, key => key.Equals(args[0], StringComparison.OrdinalIgnoreCase));
			ulong entID = 0uL;
			if (index < 0 || (!args[1].Equals("self", StringComparison.OrdinalIgnoreCase) && !ulong.TryParse(args[1], out entID)))
            {
                player.Reply(lang.GetMessage("CmdAdminTC", this, player.Id));
                return;
            }
			
			BuildingPrivlidge privilege = null;
			SimplePrivilege privilegeSimple = null;
			if (entID == 0uL)
            {
				
				if (player.Object is not BasePlayer bPlayer || bPlayer == null)
				{
					player.Reply("This command is only available to players!");
					return;
				}
				privilege = GetBuildingPrivilege(bPlayer);
				if (privilege == null)
				{
					if (!TryGetEntityPrivilege(bPlayer, out privilegeSimple))
						privilegeSimple = GetVehiclePrivilege(bPlayer);
				}
			}
			else
			{
				
				var entity = BaseNetworkable.serverEntities.Find(new NetworkableId(entID)) as BaseEntity;
				if (entity.IsValid())
				{
					if (entity is BuildingPrivlidge)
						privilege = entity as BuildingPrivlidge;
					else
						privilege = entity.GetBuildingPrivilege();
					if (privilege == null)
					{
						if (entity is SimplePrivilege)
							privilegeSimple = entity as SimplePrivilege;
						else if (entity is LegacyShelter shelter)
							privilegeSimple = shelter.GetEntityBuildingPrivilege();
						else if (entity is Tugboat tug)
							privilegeSimple = tug.children?.Cast<VehiclePrivilege>().FirstOrDefault();
					}
				}
			}
			
			bool isBuild = privilege.IsValid();
			if (!isBuild && !privilegeSimple.IsValid())
				SendMessageText(player, lang.GetMessage("CmdPrivilegeNotFound", this, player.Id));
			else if (index == 2)
			{
				
				int totalPlayers = 0;
				if (isBuild)
                {
					entID = privilege.net.ID.Value;
					totalPlayers = privilege.authorizedPlayers.Count();
					privilege.authorizedPlayers.Clear();
					privilege.UpdateMaxAuthCapacity();
					privilege.SendNetworkUpdate();
				}
				else
                {
					entID = privilegeSimple.net.ID.Value;
					totalPlayers = privilegeSimple.authorizedPlayers.Count();
					if (totalPlayers > 1)
                    {
						totalPlayers--;
						var first = privilegeSimple.authorizedPlayers.FirstOrDefault();
						privilegeSimple.authorizedPlayers.Clear();
						privilegeSimple.UpdateMaxAuthCapacity();
						privilegeSimple.SendNetworkUpdate();

                        privilegeSimple.authorizedPlayers.Add(first);
                        privilegeSimple.UpdateMaxAuthCapacity();
                        privilegeSimple.SendNetworkUpdate();
                    }
					else
						totalPlayers = 0;
				}
				SendMessageText(player, string.Format(lang.GetMessage("CmdAdminTCClear", this, player.Id), entID, totalPlayers), false);
			}
            else if (index == 3)
            {
				
				int totalPlayers = 0, totalMinutes;
				string playersInfo;
				if (isBuild)
				{
					entID = privilege.net.ID.Value;
					totalPlayers = privilege.authorizedPlayers.Count();
					totalMinutes = (int)privilege.cachedProtectedMinutes;
					playersInfo = string.Join(", ", privilege.authorizedPlayers.Select(p => p.ToString()));
				}
				else
				{
					entID = privilegeSimple.net.ID.Value;
					totalPlayers = privilegeSimple.authorizedPlayers.Count();
					totalMinutes = -1;
					playersInfo = string.Join(", ", privilegeSimple.authorizedPlayers.Select(p => p.ToString()));
					playersInfo = string.Join(", ", privilegeSimple.authorizedPlayers.Select(id => id.ToString()));
				}
				player.Reply(string.Format(lang.GetMessage("CmdAdminTCInfo", this, player.Id), entID, totalMinutes < 0 ? "∞" : totalMinutes, totalPlayers, playersInfo));
			}
			else
            {
				IPlayer tPlayer = null;
				if (!string.IsNullOrWhiteSpace(args[2]))
					TryGetPlayer(player, args[2], out tPlayer);
				else
					tPlayer = player;
				
				if (tPlayer == null || !ulong.TryParse(tPlayer.Id, out var tID))
				{
					player.Reply("This command is only available to players!");
					return;
				}
				
				BasePlayer bPlayer = tPlayer.Object as BasePlayer;
				if (index == 0)
				{
					
					if (isBuild)
					{
						entID = privilege.net.ID.Value;
						if (!privilege.AtMaxAuthCapacity())
						{
							privilege.authorizedPlayers.Remove(tID);
							privilege.authorizedPlayers.Add(tID);
							if (bPlayer.IsValid())
								Facepunch.Rust.Analytics.Azure.OnEntityAuthChanged(privilege, bPlayer, privilege.authorizedPlayers, "added", bPlayer.userID);
							privilege.UpdateMaxAuthCapacity();
							privilege.SendNetworkUpdate();
						}
					}
					else
					{
						entID = privilegeSimple.net.ID.Value;
						if (!privilegeSimple.AtMaxAuthCapacity())
						{
							privilegeSimple.authorizedPlayers.Remove(tID);
							privilegeSimple.authorizedPlayers.Add(tID);
							if (bPlayer.IsValid())
								Facepunch.Rust.Analytics.Azure.OnEntityAuthChanged(privilegeSimple, bPlayer, privilegeSimple.authorizedPlayers, "added", bPlayer.userID);
							privilegeSimple.UpdateMaxAuthCapacity();
							privilegeSimple.SendNetworkUpdate();
						}
					}
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminTCAdd", this, player.Id), entID, tPlayer.Name), false);
				}
				else if (index == 1)
				{
					
					if (isBuild)
					{
						entID = privilege.net.ID.Value;
						privilege.authorizedPlayers.Remove(tID);
						if (bPlayer.IsValid())
							Facepunch.Rust.Analytics.Azure.OnEntityAuthChanged(privilege, bPlayer, privilege.authorizedPlayers, "removed", bPlayer.userID);
						privilege.UpdateMaxAuthCapacity();
						privilege.SendNetworkUpdate();
					}
					else
					{
						entID = privilegeSimple.net.ID.Value;
						privilegeSimple.authorizedPlayers.Remove(tID);
						if (bPlayer.IsValid())
							Facepunch.Rust.Analytics.Azure.OnEntityAuthChanged(privilegeSimple, bPlayer, privilegeSimple.authorizedPlayers, "removed", bPlayer.userID);
						privilegeSimple.UpdateMaxAuthCapacity();
						privilegeSimple.SendNetworkUpdate();
					}
					SendMessageText(player, string.Format(lang.GetMessage("CmdAdminTCRemove", this, player.Id), entID, tPlayer.Name), false);
				}
			}
        }
		private Dictionary<string, RRData> _randomRaidsList = new Dictionary<string, RRData>();
		
		private void UpdateTurretTrigger(FlameTurret flameTurret)
        {
            if (flameTurret != null)
            {
				flameTurret.SetTriggered(false);
				UpdateTurretTrigger(flameTurret.trigger);
            }
        }
		private readonly VersionNumber _beachVersion = new VersionNumber(0, 1, 0);
		private static Dictionary<ulong, HashSet<string>> _playerUI;
		
		private void ConfigBackpackDropToggled()
        {
			if (_config.PreventBackpackDrop)
				Subscribe(nameof(OnBackpackDrop));
			else
				Unsubscribe(nameof(OnBackpackDrop));
		}
		
		private int GetBedsLimit(string userID)
		{
			PvEPermission perm;
			int result = _permissionsConfig.PermissionsList[0].Beds;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
				if (result < 0)
					break;
				perm = _permissionsConfig.PermissionsList[i];
				if ((perm.Beds < 0 || perm.Beds > result) && permission.UserHasPermission(userID, perm.Name))
                    result = perm.Beds;
            }
            return result;
		}
		object CanLootEntity(BasePlayer player, PhotoFrame frame) => CanLootStorage(player, frame);

		private int CountBeds(ulong targetID)
		{
			int result = 0;
			var sleepingBags = SleepingBag.sleepingBags;
			for (int i = 0; i < sleepingBags.Count; i++)
			{
				if (sleepingBags[i].deployerUserID == targetID)
					result++;
			}
			return result;
		}
		object CanTakeCutting(BasePlayer player, GrowableEntity plant) => HandlePlantInteracting(player, plant);

		void OnCargoShipHarborArrived(CargoShip cargoShip)
		{
			if (cargoShip.skinID != 0uL) return;
			MonumentData cargoData = null, harborData = null;
			string[] monuments = GetMonumentsByPos(cargoShip.transform.position);
			foreach (var monumentID in monuments)
			{
				if (!_monumentsList.ContainsKey(monumentID))
					continue;
				if (cargoData == null && monumentID.StartsWith("CargoShip"))
					cargoData = _monumentsList[monumentID];
				else if (harborData == null && monumentID.Contains("harbor"))
					harborData = _monumentsList[monumentID];
			}
			if (cargoData == null || !cargoData.IsPvP || harborData == null || harborData.IsPvP ||
				!(harborData.MonumentID.Contains("harbor_1") ? _monumentsConfig.CargoShip_HarborToPvP : _monumentsConfig.CargoShip_LargeHarborToPvP))
				return;
			harborData.SetAsPvP();
		}
		object OnMixingTableToggle(CookingWorkbench cookingTable, BasePlayer player) => HandleContainerToggle(player, cookingTable);
		object CanLootEntity(BasePlayer player, ChristmasTree tree) => CanLootStorage(player, tree);
		
		object OnEntityTakeDamage(BradleyAPC bradley, HitInfo info)
        {
			if (info == null || !bradley.IsValid() || TryGetAttacker(info, out var initiator) != AttackerIndex.BasePlayer) return null;
            var attacker = (BasePlayer)initiator;
			if (!attacker.userID.IsSteamId() || IsEntityInPvP(attacker.OwnerID, bradley.net.ID.Value)) return null;
			if (bradley.skinID != 0uL) {}
			else if (_eventsList.TryGetValue(bradley.net.ID.Value, out var bradleyData))
            {
                if (bradleyData.OwnerID == 0uL)
                {
					double price = bradleyData.Settings.Price <= 0d ? 0d : bradleyData.Settings.Price * GetEventPriceMultiplier(attacker.UserIDString);
					if (!_economicsIsLoaded || price <= 0d)
                        bradleyData.SetNewOwner(attacker);
                    else if (_playerUI.TryGetValue(attacker.userID, out var uiList) && !uiList.Contains(EventOfferUI))
                    {
						if (_vanillaEventsConfig.AutoBuy || _autoBuyPlayers.Contains(attacker.userID))
                            attacker.Command($"{_commandUI} event pay {bradleyData.ID}");
                        else
                        {
							ShowEventOffer(attacker, bradleyData, price);
							timer.Once(bradleyData.Settings.OfferTime, () => { DestroyUI(attacker, EventOfferUI); });
                        }
						attacker.SendEffect();
                    }
					info.Urma();
				}
                else if (!bradleyData.CanBeAttackedBy(attacker))
                {
					info.Urma();
					SendMessageText(attacker.IPlayer, string.Format(lang.GetMessage("MsgEventOccupied", this, attacker.UserIDString), lang.GetMessage(bradleyData.TypeKey, this, attacker.UserIDString), bradleyData.OwnerName));
				}
            }
            return null;
		}
		void OnEntitySpawned(ScrapTransportHelicopter scrapCopter) => HandleVehicleSpawn(scrapCopter);
		
		public class RBSettings
        {
			[JsonProperty(PropertyName = "Language key for displaying the difficulty name")]
            public string TextKey { get; set; } = "MsgRaidableBasesEasy";
			
			[JsonProperty(PropertyName = "List of difficulty levels to which these settings apply")]
			public int[] Levels { get; set; }
			
			[JsonProperty(PropertyName = "Time in seconds (1-15) given to respond for purchasing a Raidable Base. Note: This is shown to everyone who enters, and the first person to buy it will claim it")]
			public float OfferTime { get; set; } = 5f;
			
			public double Price { get; set; }
			
			[JsonProperty(PropertyName = "Is it worth using a progress bar for bars with a counter?")]
			public bool UseProgress { get; set; } = true;

			[JsonProperty(PropertyName = "PvP - Sets the delay in seconds that a player remains in PvP mode after leaving a PvP RaidableBase. 0 disables the delay")]
			public float PvPDelay { get; set; } = 10f;
			
			[JsonProperty(PropertyName = "Settings for the status bar")]
			public BarSettings Bar { get; set; }
			
			[JsonProperty(PropertyName = "Settings for the progress status bar")]
			public ProgressBarSettings ProgressBar { get; set; }
			
			[JsonIgnore] public string ImgLibId;
		}
		
		object OnEntityTakeDamage(BaseCombatEntity entity, HitInfo info)
		{
			if (CancelDamageToEntity(entity, info))
				info.Urma();
			return null;
		}
		
		public static IEnumerable<CodeInstruction> ModDroneItemDrop(IEnumerable<CodeInstruction> instructions, ILGenerator generator)
        {
            var result = new List<CodeInstruction>(instructions);
            int index = -1;
			CodeInstruction code;
            for (int i = 0; i < result.Count; i++)
            {
                code = result[i];
                if (code.opcode == OpCodes.Callvirt && code.operand is MethodInfo method && method.Name == "Drop")
                {
					index = i;
					break;
				}
			}
            if (index >= 0)
            {
				index++;
				result.Insert(index + 1, new CodeInstruction(OpCodes.Ldarg_0));
				result.Insert(index + 2, new CodeInstruction(OpCodes.Ldloc_2));
				result.Insert(index + 3, new CodeInstruction(OpCodes.Call, AccessTools.Method(typeof(RealPVE), nameof(RealPVE_DroneItemDrop))));
			}
            return result;
        }

        private void RaidableBaseTimeUpdatedBar(BasePlayer player, RBData rbData)
        {
            if (!_statusIsLoaded) return;

            var parameters = new Dictionary<int, object>
            {
                { 0, rbData.RaidID },
                { 1, Name },
                { 29, rbData.DespawnTime }
            };

            AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
        }
		
		void OnScientistRecalled(BradleyAPC bradley, ScientistNPC scientist) => _eventScientistsList.Remove(scientist.net.ID);
		
		public static void RealPVE_BeeTarget(BeeSwarmAI beeSwarm, BasePlayer targetPlayer)
		{
			if (beeSwarm.OwnerID.IsSteamId() && targetPlayer != null && targetPlayer.userID.IsSteamId() && !IsPlayerInPvP(beeSwarm.OwnerID, targetPlayer.userID) && !UrishMumkin(beeSwarm.OwnerID, targetPlayer))
				beeSwarm.targetPlayer = null;
		}
		private Dictionary<int, object> _pvpBar, _pvpDelayBar;
		object CanLootEntity(BasePlayer player, BoxStorage storage) => CanLootStorage(player, storage);
		void OnUserPermissionRevoked(string userIDString, string permName) => OnUserPermissionGranted(userIDString, permName);
		
		void OnRandomRaidRaiderSpawned(Vector3 pos, NPCPlayer raider)
		{
			if (_randomRaidsList.TryGetValue(pos.ToString(), out var rrData))
			{
				_rrAllRaiders[raider.net.ID.Value] = rrData;
				rrData.Raiders.Add(raider.net.ID.Value);
				_rrAllRaiders[raider.userID] = rrData;
				rrData.Raiders.Add(raider.userID);
			}
		}
		
		void OnAdvancedStatusLoaded()
		{
			_statusIsLoaded = true;
			var imgList = new HashSet<string>();
			
			if (!string.IsNullOrWhiteSpace(_config.BarPvP.Image_Local))
				imgList.Add(_config.BarPvP.Image_Local);
			foreach (var monumentSettings in _monumentsConfig.MonumentsSettings.Values)
			{
				if (!string.IsNullOrWhiteSpace(monumentSettings.Bar.Image_Local))
					imgList.Add(monumentSettings.Bar.Image_Local);
			}
			foreach (var rbSettings in _rbsConfig.Settings)
			{
				if (!string.IsNullOrWhiteSpace(rbSettings.Bar.Image_Local))
					imgList.Add(rbSettings.Bar.Image_Local);
			}
			if (imgList.Any())
				AdvancedStatus?.Call("LoadImages", imgList);
			
			foreach (var monumentData in _monumentsList.Values)
				monumentData.UpdateBars();
			UpdatePvPBars();
			foreach (var rbData in _rbList.Values)
				rbData.UpdateBars();
		}

		void OnCupboardDeauthorize(BuildingPrivlidge privilege, BasePlayer player)
		{
			if (_config.RandomRaids_Enabled && _randomRaidsList.TryGetValue(privilege.transform.position.ToString(), out var rrData))
			{
				NextTick(() =>
				{
					if (player != null && (privilege == null || !privilege.IsAuthed(player)))
					{
						_rrAllPlayers.Remove(player.userID);
						rrData.PlayersList.Remove(player.userID);
					}
				});
			}
		}
		
		object OnCupboardDeauthorize(VehiclePrivilege privilege, BasePlayer player)
        {
			var tugBoat = privilege.GetParentEntity() as Tugboat;
			if (tugBoat.IsValid() && _vehiclesList.TryGetValue(tugBoat.net.ID.Value, out var vehicleData) && vehicleData.IsOwner(player.userID))
            {
				SendMessageText(player, lang.GetMessage("MsgVehicleFailedDeauthorize", this, player.UserIDString));
				return false;
			}
			return null;
		}
		
                private void SendCounterBar(BasePlayer player, RBData rbData)
        {
			if (!_statusIsLoaded) return;
			
			string text = string.Format(lang.GetMessage("MsgRaidableBasesBarText", this, player.UserIDString), lang.GetMessage(rbData.Settings.TextKey, this, player.UserIDString));
			Dictionary<int, object> parameters;
			if (rbData.Settings.UseProgress)
            {
				parameters = new Dictionary<int, object>(rbData.StatusProgressBar)
				{
					{ 15, text },
					{ 28, rbData.StartTime },
					{ 29, rbData.DespawnTime }
				};
			}
            else
			{
				parameters = new Dictionary<int, object>(rbData.StatusBar)
				{
					{ 15, text },
					{ 29, rbData.DespawnTime }
				};
				parameters[2] = BarTimeCounter;
			}
			
			AdvancedStatus?.Call(StatusCreateBar, player.userID.Get(), parameters);
        }
		
		private VehicleType[] GetVehicleTypes() => Enum.GetValues(typeof(VehicleType)).Cast<VehicleType>().Where(v => v != VehicleType.None).ToArray();
		object CanLootEntity(BasePlayer player, SupplyDrop drop) => drop.skinID == 0uL ? CanLootByOwnerID(player, drop) : null;
		
		object OnRotateVendingMachine(VendingMachine machine, BasePlayer player)
        {
			if (machine.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, machine.net.ID.Value))
            {
                object result = CanInteractSharedEntity(player, machine);
                if (result != null)
                    SendMessageText(player, lang.GetMessage("MsgCantAdministerVending", this, player.UserIDString));
                return result;
            }
            return null;
		}
		
		private object CanLootCombatEntity(BasePlayer player, BaseCombatEntity entity, ulong playerSteamID)
        {
			if (!_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, entity.net.ID.Value))
			{
				object result = null;
				if (playerSteamID.IsSteamId())
					result = player.TasirMumkin(playerSteamID);
				else if (entity.OwnerID.IsSteamId())
					result = player.TasirMumkin(entity.OwnerID);
				else if (entity.skinID == _rbPluginID)
				{
					if (TryGetRaidBase(entity.transform.position, out var rbData) && !rbData.CanInteractWithRaid(player.userID))
						result = false;
				}
				else if (entity.skinID == _rrPluginID)
				{
					if (_config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(entity.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(player.userID))
						result = false;
				}
				else if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
					result = monumentData.CanLoot(player);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return result;
			}
			return null;
		}
		void OnEventLeave(BasePlayer player, string zoneID) => OnPlayerExitPVP(player, zoneID);
		private string[] _defaultHooks = new string[] { "CanBypassQueue", "OnPlayerConnected", "OnPlayerDisconnected", "OnPlayerDeath", "OnEntityDeath",
            "OnPlayerRespawned", "OnButtonPress", "OnSwitchToggle", "OnConveyorFiltersChange", "OnFrankensteinPetWake",
            "OnFrankensteinPetSleep", "OnPluginLoaded", "OnPluginUnloaded", "OnEntitySpawned", "OnEntityKill",
            "OnCupboardAuthorize", "OnCupboardDeauthorize", "OnCupboardClearList", "CanBuild", "OnStructureUpgrade",
            "OnStructureRotate", "CanDemolish", "CanLock", "CanUnlock", "CanChangeCode",
            "CanUseLockedEntity", "CanAssignBed", "CanRenameBed", "CanAdministerVending", "OnRotateVendingMachine",
            "CanUpdateSign", "OnTurretAuthorize", "OnInterferenceUpdate", "OnEntityTakeDamage", "OnDispenserGather",
            "OnCollectiblePickup", "OnGrowableGather", "CanTakeCutting", "OnRemoveDying", "OnQuarryToggle",
			"CanLootPlayer", "OnTryLootEntity", "OnOvenToggle", "OnMixingTableToggle", "OnPlayerDropActiveItem",
			"OnPlayerDrink", "OnRackedWeaponMount", "OnRackedWeaponSwap", "OnRackedWeaponTake", "OnRackedWeaponLoad",
			"OnRackedWeaponUnload", "OnCrateHack", "OnLootEntity", "OnNpcConversationRespond", "CanMountEntity",
			"CanSwapToSeat", "OnVehiclePush", "OnSiegeWeaponPull", "OnSiegeWeaponDoorOpen", "OnSiegeWeaponDoorClose",
			"OnEngineStart", "OnHotAirBalloonToggle", "OnLootEntityEnd", "OnRidableAnimalClaim", "OnHorseLead",
			"OnPlayerLootEnd", "OnVehicleLockRequest", "OnCodeChange", "OnLockRemove", "CanDestroyLock",
			"OnVehicleModuleMove", "OnEntityEnter", "OnTeamCreated", "OnTeamDisbanded", "OnTeamAcceptInvite",
			"OnTeamLeave", "OnTeamKick", "OnFriendAdded", "OnFriendRemoved", "OnSamSiteTarget",
			"OnNpcTargetSense", "OnPlayerCorpseSpawned", "OnPlayerEnterPVP", "OnPlayerExitPVP", "OnEntityEnterPVP",
			"OnEntityExitPVP", "OnEventJoin", "OnEventJoined", "OnEventLeave", "OnZoneStatusText",
			"CreatePVPMapMarker", "DeletePVPMapMarker", "CanBeTargeted", "OnFireBallDamage", "CanLootEntity",
			"OnFireBallSpread", "CanPickupEntity", "OnExplosiveThrown", "OnExplosiveDropped", "OnRocketLaunched",
			"OnCargoPlaneSignaled", "OnSupplyDropDropped" };
		object OnRemoveDying(GrowableEntity plant, BasePlayer player) => HandlePlantInteracting(player, plant);
		
		private void Command_AdminAutoBuy(IPlayer player, string[] args)
        {
            if (string.IsNullOrWhiteSpace(args[0]) || args[0].Equals("help", StringComparison.OrdinalIgnoreCase))
                player.Reply(lang.GetMessage("CmdAdminAutoBuy", this, player.Id));
            else if (args[0].Equals("clear", StringComparison.OrdinalIgnoreCase))
            {
                foreach (var playerData in _playersList.Values)
                    playerData.AutoBuy = false;
                int total = _autoBuyPlayers.Count;
                _autoBuyPlayers.Clear();
                SaveData(_dataPlayersPath, _playersList);
                SendMessageText(player, string.Format(lang.GetMessage("CmdAdminAutoBuyClear", this, player.Id), total), false);
            }
            else if (args[0].Equals("force", StringComparison.OrdinalIgnoreCase))
            {
                bool val;
                switch (args[1])
                {
                    case "monument":
                        if (!bool.TryParse(args[2], out val))
                            val = !_monumentsConfig.AutoBuy;
                        _monumentsConfig.AutoBuy = val;
                        SaveMonumentsConfig();
                        break;
                    case "event":
                        if (!bool.TryParse(args[2], out val))
                            val = !_vanillaEventsConfig.AutoBuy;
                        _vanillaEventsConfig.AutoBuy = val;
                        SaveVanillaEventsConfig();
                        break;
                    case "rb":
                        if (!bool.TryParse(args[2], out val))
                            val = !_rbsConfig.AutoBuy;
                        _rbsConfig.AutoBuy = val;
                        SaveRBsConfig();
                        break;
                    default:
                        player.Reply(lang.GetMessage("CmdAdminAutoBuy", this, player.Id));
                        return;
                }
                SendMessageText(player, string.Format(lang.GetMessage("CmdAdminAutoBuyForce", this, player.Id), args[1], val), false);
            }
            else if (TryGetPlayer(player, args[0], out var tPlayer))
			{
				GetOrCreatePlayerData(tPlayer.Id, out var playerData);
                playerData.AutoBuy = !playerData.AutoBuy;
                if (playerData.AutoBuy)
                {
                    _autoBuyPlayers.Add(playerData.UserID);
                    if (tPlayer.IsConnected)
                        SendMessageText(tPlayer, lang.GetMessage("CmdMainAutoBuyEnabled", this, tPlayer.Id));
                    SendMessageText(player, string.Format(lang.GetMessage("CmdAdminAutoBuyEnabled", this, player.Id), tPlayer.Name));
                }
                else
                {
                    _autoBuyPlayers.Remove(playerData.UserID);
                    if (tPlayer.IsConnected)
                        SendMessageText(tPlayer, lang.GetMessage("CmdMainAutoBuyDisabled", this, tPlayer.Id), false);
                    SendMessageText(player, string.Format(lang.GetMessage("CmdAdminAutoBuyDisabled", this, player.Id), tPlayer.Name), false);
                }
            }
        }
		
		bool OnEntityEnterPVP(BaseEntity entity, string zoneID = "")
        {
			if (entity.IsValid() && _pvpEntities.Add(entity.net.ID.Value))
			{
				if (entity is AutoTurret turret)
					UpdateTurretTrigger(turret);
				else if (entity is FlameTurret flameTurret)
                    UpdateTurretTrigger(flameTurret);
                else if (entity is GunTrap gunTrap)
                    UpdateTurretTrigger(gunTrap);
				else if (entity is DecayEntity decayEntity)
					RemoveDemolishable(decayEntity);
				return true;
			}
			return false;
		}
		object OnEntityTakeDamage(VehicleModuleSeating module, HitInfo info) => HandleDamageToVehicle(module.VehicleParent(), info);
		
		void OnGibsSpawned(List<ServerGib> serverGibs, GameObject gameObject)
        {
			var entity = gameObject.ToBaseEntity();
			if (entity.IsValid() && _eventsList.TryGetValue(entity.net.ID.Value, out var eventData))
				eventData.DebrisSpawned(serverGibs);
		}
		object OnEntityTakeDamage(AttackHelicopter attackCopter, HitInfo info) => HandleDamageToVehicle(attackCopter, info);
		private void Command_Share(BasePlayer player, string[] args, PlayerData playerData)
		{
			int index = Array.FindIndex(_cmdKeysMainShare, key => key.Equals(args[0], StringComparison.OrdinalIgnoreCase));
            if (index < 0)
            {
				player.IPlayer.Reply(lang.GetMessage("CmdMainShare", this, player.UserIDString));
				return;
            }
			
			if (index == 5)
            {
				
				int total = playerData.SharedEntities.Count();
				if (total > 0)
				{
					foreach (var netID in playerData.SharedEntities.Keys)
						_sharedEntities.Remove(netID);
					playerData.SharedEntities.Clear();
				}
				SendMessageText(player, string.Format(lang.GetMessage("CmdMainShareClear", this, player.UserIDString), total), false);
			}
			else if (!TryGetEntity(index == 1 || index == 2 ? args[2] : args[1], out var entity, player))
				SendMessageText(player, lang.GetMessage("CmdEntityNotFound", this, player.UserIDString));
			else if (entity.OwnerID != player.userID)
				SendMessageText(player, lang.GetMessage("CmdEntityNotOwner", this, player.UserIDString));
			else
			{
				ulong netID = entity.net.ID.Value;
				if (index == 4)
                {
					
					if (playerData.SharedEntities.Remove(netID))
						_sharedEntities.Remove(netID);
					SendMessageText(player, string.Format(lang.GetMessage("CmdMainShareDelete", this, player.UserIDString), netID), false);
				}
				else
                {
					GetOrCreateSharedEntity(playerData, netID, out var sharedEntity);
					if (index == 0)
					{
						
						player.IPlayer.Reply(string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdMainShareStatusEveryOne" : "CmdMainShareStatusDefault", this, player.UserIDString), netID, string.Join(", ", sharedEntity.PlayersList)));
					}
					else if (index == 3)
                    {
						
						sharedEntity.ForEveryOne = !sharedEntity.ForEveryOne;
						SendMessageText(player, string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdMainShareToggleEveryOne" : "CmdMainShareToggleDefault", this, player.UserIDString), netID), false);
					}
					else if (TryGetPlayer(player.IPlayer, args[1], out var tPlayer))
					{
						ulong.TryParse(tPlayer.Id, out var tUserID);
						if (index == 1)
						{
							
							sharedEntity.PlayersList.Add(tUserID);
							SendMessageText(player, string.Format(lang.GetMessage(sharedEntity.ForEveryOne? "CmdMainShareDenied" : "CmdMainShareGranted", this, player.UserIDString), netID, tPlayer.Name), false);
						}
                        else if (index == 2)
						{
							
							sharedEntity.PlayersList.Remove(tUserID);
							SendMessageText(player, string.Format(lang.GetMessage(sharedEntity.ForEveryOne ? "CmdMainShareGranted" : "CmdMainShareDenied", this, player.UserIDString), netID, tPlayer.Name), false);
						}
					}
				}
            }
		}
		
		object OnEntityTakeDamage(NPCPlayer victimNPC, HitInfo info)
        {
			if (info == null || TryGetAttacker(info, out var initiator) != AttackerIndex.BasePlayer) return null;
            var attacker = (BasePlayer)initiator;
            if (!attacker.userID.IsSteamId()) return null;
            if (victimNPC.skinID != 0uL)
            {
                if (victimNPC.skinID == _bradleySkinId)
                {
                    if (_eventScientistsList.TryGetValue(victimNPC.net.ID, out var eventData) && !eventData.CanBeAttackedBy(attacker))
                        info.UrmaB();
                }
                else if (victimNPC.skinID == _rbPluginID)
                {
                    if (TryGetRaidBase(victimNPC.transform.position, out var rbData) && !rbData.CanInteractWithRaid(attacker.userID))
                        info.UrmaB();
                }
                else if (victimNPC.skinID == _rrPluginID)
                {
                    if (_config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(victimNPC.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(attacker.userID))
                        info.UrmaB();
                }
            }
            else if (_monumentsList.TryGetValue(GetNpcMonument(victimNPC), out var monumentData) && monumentData.CanLoot(attacker) != null)
                info.UrmaB();
            return null;
        }
		
		void OnGroupDeleted(string groupName)
        {
			foreach (var userID in _playersList.Keys)
                HandlePermissionsUpdate(userID.ToString());
		}
		
		object CanPickupEntity(BasePlayer player, BaseCombatEntity entity)
        {
			if (entity.IsValid() && entity.OwnerID.IsSteamId() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, entity.net.ID.Value))
			{
				object result = CanInteractSharedEntity(player, entity);
				if (result != null)
                {
                    SendMessageText(player, lang.GetMessage("MsgCantPickup", this, player.UserIDString));
                    return true;
                }
                return null;
			}
			return null;
		}
		
		void OnLootEntity(BasePlayer player, RidableHorse horse)
		{
			if (horse.HasSaddle && _vehiclesList.TryGetValue(horse.net.ID.Value, out var vehicleData))
				ShowVehiclePanels(player, vehicleData);
		}
		
		public enum VehicleType
        {
            None,
            Horse,
            Bike,
            MotorBike,
            Car,
            Balloon,
            Minicopter,
            TransportHeli,
            AttackHeli,
            RowBoat,
            RHIB,
            TugBoat,
            SubmarineOne,
            SubmarineTwo,
            Snowmobile,
			BatteringRam,
			Train
		}

		object CanHackCrate(BasePlayer player, HackableLockedCrate crate)
		{
			if (!_unrestrictedLooters.Contains(player.userID) && _monumentsList.TryGetValue(GetEntityMonument(crate), out var monumentData))
			{
				object result = monumentData.CanLoot(player);
				if (result != null)
					SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
				return result;
			}
			return null;
		}
		private object HandlePlantInteracting(BasePlayer player, GrowableEntity plant)
		{
			if (player.IsValid() && plant.IsValid() && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, plant.net.ID.Value))
            {
				var planter = plant.GetPlanter();
                if (planter.IsValid() && planter.OwnerID.IsSteamId())
                {
                    if (CanInteractSharedEntity(player, planter) != null)
                    {
                        SendMessageText(player, lang.GetMessage("MsgCantInteract", this, player.UserIDString));
                        return false;
                    }
				}
				else if (_config.PreventPickUpCollectible && player.BinoMumkin() != null)
				{
					SendMessageText(player, lang.GetMessage("MsgCantGatherInBase", this, player.UserIDString));
					return false;
				}
			}
            return null;
		}
        private string GetNpcMonument(BasePlayer npcPlayer) => (string)(MonumentsWatcher?.Call(MonumentGetNpcMonument, npcPlayer.net.ID) ?? string.Empty);
		
		object OnPlayerHandcuff(BasePlayer victim, BasePlayer attacker)
        {
			if (victim.userID.IsSteamId() && !IsPlayerInPvP(attacker.userID, victim.userID) && !UrishMumkin(attacker, victim.userID))
            {
				SendMessageText(attacker, lang.GetMessage("MsgCantHandcuffing", this, attacker.UserIDString));
				return true;
            }
			return null;
		}
		private void HandlePortalUsed(BasePlayer player, BasePortal portal)
        {
			if (portal.skinID == 0uL && portal.OwnerID == 0uL && !_unrestrictedLooters.Contains(player.userID) && !IsEntityInPvP(player.userID, portal.net.ID.Value))
				portal.OwnerID = player.userID;
		}
		
		public static IEnumerable<CodeInstruction> ModBeeThink(IEnumerable<CodeInstruction> instructions, ILGenerator generator)
        {
            var result = new List<CodeInstruction>(instructions);
			CodeInstruction code;
            for (int i = 0; i < result.Count; i++)
            {
				code = result[i];
				if (code.opcode == OpCodes.Stfld && code.operand is FieldInfo field && field.Name == "targetPlayer")
                {
					result.Insert(i + 1, new CodeInstruction(OpCodes.Ldarg_0));
					result.Insert(i + 2, new CodeInstruction(OpCodes.Ldarg_0));
					result.Insert(i + 3, new CodeInstruction(OpCodes.Ldfld, AccessTools.Field(typeof(BeeSwarmAI), "targetPlayer")));
					result.Insert(i + 4, new CodeInstruction(OpCodes.Call, AccessTools.Method(typeof(RealPVE), nameof(RealPVE_BeeTarget))));
                    break;
                }
			}
			return result;
        }
		
		private int CountRaids(string targetID)
		{
			int result = 0;
			foreach (var rbData in _rbList.Values)
			{
				if (rbData.OwnerIDString.Equals(targetID, StringComparison.OrdinalIgnoreCase))
					result++;
			}
			return result;
		}
		object OnRidableAnimalDetach(RidableHorse horse, BasePlayer player) => HandleVehicleInteraction(player, horse, "MsgVehicleCantDetach");
		private const string IdForHarmony = "iiiaka.realpve";
		
		public class ProgressBarSettings
        {
			[JsonProperty(PropertyName = "Main_Color(Hex or RGBA)")]
            public string Main_Color { get; set; } = "1 1 1 0.15";
			
			public float Main_Transparency { get; set; } = 0.15f;
			public bool Progress_Reverse { get; set; } = true;
			public string Progress_Color { get; set; } = "#FFBF99";
            public float Progress_Transparency { get; set; } = 0.7f;
            public string Progress_OffsetMin { get; set; } = "0 0";
            public string Progress_OffsetMax { get; set; } = "0 0";
		}
		
		void OnEntityExitedMonument(string monumentID, BaseEntity entity, string category, string reason, string newMonumentID)
        {
            if (_monumentsConfig.TrackedCategories.Contains(category) && _monumentsList.TryGetValue(monumentID, out var monumentData) && monumentData.IsPvP &&
				(!_monumentsList.TryGetValue(newMonumentID, out var newMonumentData) || !newMonumentData.IsPvP))
				OnEntityExitPVP(entity);
		}
		
		private void InitVehicle(BaseEntity vehicle)
        {
            if (!vehicle.IsValid()) return;
            VehicleType type;
            switch (vehicle)
            {
                case RidableHorse:
                    type = VehicleType.Horse;
                    break;
                case Bike bike:
                    if (bike.poweredBy == Bike.PoweredBy.Fuel)
                        type = VehicleType.MotorBike;
                    else
                        type = VehicleType.Bike;
                    break;
                case ModularCar:
                    type = VehicleType.Car;
                    break;
                case HotAirBalloon:
                    type = VehicleType.Balloon;
                    break;
                case Minicopter:
                    type = VehicleType.Minicopter;
                    break;
                case ScrapTransportHelicopter:
                    type = VehicleType.TransportHeli;
                    break;
                case AttackHelicopter:
                    type = VehicleType.AttackHeli;
                    break;
                case RHIB:
                    type = VehicleType.RHIB;
                    break;
                case Tugboat:
                    type = VehicleType.TugBoat;
                    break;
                case MotorRowboat:
                    type = VehicleType.RowBoat;
                    break;
                case SubmarineDuo:
                    type = VehicleType.SubmarineTwo;
                    break;
                case BaseSubmarine:
                    type = VehicleType.SubmarineOne;
                    break;
                case Snowmobile:
                    type = VehicleType.Snowmobile;
                    break;
				case BatteringRam:
                    type = VehicleType.BatteringRam;
                    break;
				default:
                    return;
            }
			
			ulong vehicleID = vehicle.net.ID.Value;
			if (!_vehiclesList.TryGetValue(vehicleID, out var vehicleData) || vehicleData == null)
            {
				var ownerPlayer = vehicle.creatorEntity as BasePlayer;
				if (ownerPlayer == null || !ownerPlayer.userID.IsSteamId())
					_vehiclesList[vehicleID] = vehicleData = new VehicleData(vehicleID, type, 0uL);
				else
				{
					_vehiclesList[vehicleID] = vehicleData = new VehicleData(vehicleID, type, ownerPlayer.userID);
                    if (vehicle.HasFlag(BaseVehicle.Flag_OnlyOwnerEntry))
                    {
						MakeWithdraw(ownerPlayer.UserIDString, GetVehiclePrice(ownerPlayer.UserIDString, type));
						if (ownerPlayer.IsConnected)
                        {
							string[] replyArgs = new string[5];
							int limit = GetVehicleLimit(ownerPlayer.UserIDString, type);
							replyArgs[0] = lang.GetMessage($"MsgVehicle{type}", this, ownerPlayer.UserIDString);
                            replyArgs[1] = CountVehiclesByType(ownerPlayer.userID, type).ToString();
                            replyArgs[2] = limit < 0 ? "∞" : limit.ToString();
							SendMessage(ownerPlayer, "MsgVehicleLinked", replyArgs, false);
						}
					}
				}
			}
			else
				vehicleData.ID = vehicleID;
			if (vehicle is ModularCar car && car.CarLock != null && car.CarLock.WhitelistPlayers.Any())
            {
                if (vehicleData.OwnerID == 0uL || !car.CarLock.WhitelistPlayers.Contains(vehicleData.OwnerID))
                {
                    vehicleData.OwnerID = car.CarLock.WhitelistPlayers[0];
                    vehicleData.RegistrationDate = DateTime.UtcNow.ToString(TimeFormat);
                }
            }
        }

		private float GetRaidableBasesPriceMultiplier(string userID)
        {
			PvEPermission perm;
            float result = _permissionsConfig.PermissionsList[0].RB_Multiplier;
            for (int i = 1; i < _permissionsConfig.PermissionsList.Count; i++)
            {
                perm = _permissionsConfig.PermissionsList[i];
                if (perm.RB_Multiplier > result && permission.UserHasPermission(userID, perm.Name))
                    result = perm.RB_Multiplier;
            }
            return result;
		}
		
		public class MapCircleStyle
        {
			[JsonIgnore] public UnityEngine.Color FirstColor;
			[JsonIgnore] public UnityEngine.Color SecondColor;
			
			[JsonProperty(PropertyName = "FirstColor(RGB)")]
			private Vector3 _firstColor;
			
			[JsonProperty(PropertyName = "SecondColor(RGB)")]
			private Vector3 _secondColor;
			
			public float Alpha = 0.5f;
			
			public MapCircleStyle() { VerifyValues(); }
			public MapCircleStyle(Color first, Color second, float alpha = 0.5f)
			{
				FirstColor = first;
				SecondColor = second;
				_firstColor = new Vector3(FirstColor.r, FirstColor.g, FirstColor.b);
				_secondColor = new Vector3(SecondColor.r, SecondColor.g, SecondColor.b);
				Alpha = Mathf.Clamp01(alpha);
			}
			
			public void VerifyValues()
			{
				FirstColor = new Color(_firstColor.x, _firstColor.y, _firstColor.z);
				SecondColor = new Color(_secondColor.x, _secondColor.y, _secondColor.z);
				Alpha = Mathf.Clamp01(Alpha);
			}
		}
		
		public static bool ModSmokeExplode(SmokeGrenade __instance)
        {
			if (__instance == null || !__instance.OwnerID.IsSteamId() || IsPlayerInPvP(__instance.OwnerID))
				return true;
			__instance.FinishUp();
			return false;
		}
		
		void OnPlayerConnected(BasePlayer player)
        {
			_playerUI[player.userID] = new HashSet<string>();
			player.CancelInvoke(player.ScheduledDeath);
			
			HandlePermissionsUpdate(player.UserIDString, player);
			if (_monumentsList.TryGetValue(GetPlayerMonument(player.userID), out var monumentData))
				monumentData.OnPlayerEnter(player);
			if (_pvpPlayers.TryGetValue(player.userID, out var playerPvP) && playerPvP.ActiveZones.Any())
            {
				SendPvPBar(player, playerPvP.ActiveZones[^1]);
				player.SendEffect();
				SendMessageText(player, lang.GetMessage("MsgPvPEnter", this, player.UserIDString));
			}
		}
		
		public static IEnumerable<CodeInstruction> ModDroneCollision(IEnumerable<CodeInstruction> instructions, ILGenerator generator)
        {
            var result = new List<CodeInstruction>(instructions);
            CodeInstruction code;
            for (int i = 0; i < result.Count; i++)
            {
                code = result[i];
                if (code.opcode == OpCodes.Ble_Un_S && code.operand is Label label)
                {
					result.Insert(i + 1, new CodeInstruction(OpCodes.Ldarg_0));
					result.Insert(i + 2, new CodeInstruction(OpCodes.Ldarg_1));
					result.Insert(i + 3, new CodeInstruction(OpCodes.Call, AccessTools.Method(typeof(RealPVE), nameof(RealPVE_CanTakeCollisionDamage))));
					result.Insert(i + 4, new CodeInstruction(OpCodes.Brfalse_S, label));
					break;
                }
            }
            return result;
        }
		
		private CuiElementContainer GetDefaultClaimOffer()
        {
            var result = new CuiElementContainer();
            result.Add(new CuiPanel
            {
                RectTransform = { AnchorMin = "0 1", AnchorMax = "0 1", OffsetMin = "30 -100", OffsetMax = "360 -40" },
                Image = { Color = "0 0 0 0" }
            }, "Overlay", "{0}");
            result.Add(new CuiElement
            {
                Parent = "{0}",
                Name = "{0}_Image",
                Components =
                {
                    new CuiImageComponent { Color = "0.8 0.9 0.6 0.8", Png = "{1}" },
                    new CuiRectTransformComponent { AnchorMin = "0 0.5", AnchorMax = "0 0.5", OffsetMin = "7 -17.5", OffsetMax = "42 17.5" }
                }
            });
            result.Add(new CuiLabel
            {
                Text =
                {
                    Text = "{2}",
                    Font = "RobotoCondensed-Bold.ttf",
                    FontSize = 14,
                    Color = WhiteColor,
                    Align = TextAnchor.UpperLeft,
                    FadeIn = 1f
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "50 7", OffsetMax = "-5 -7" }
            }, "{0}", "{0}_Title");
            result.Add(new CuiLabel
            {
                Text =
                {
                    Text = "{3}",
                    Font = "RobotoCondensed-Regular.ttf",
                    FontSize = 12,
                    Color = WhiteColor,
                    Align = TextAnchor.LowerLeft,
                    FadeIn = 1f
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "50 7", OffsetMax = "-5 -7" }
            }, "{0}", "{0}_Description");
            result.Add(new CuiButton
            {
                Button =
                {
                    Close = "{0}",
                    Command = "{4}",
                    Color = "0 0 0 0"
                },
                RectTransform = { AnchorMin = "0 0", AnchorMax = "1 1", OffsetMin = "0 0", OffsetMax = "0 0" }
            }, "{0}", "{0}_Button");
			
			return result;
		}
		
                private const ulong _rrPluginID = 8675309uL;
		object CanLootEntity(BasePlayer player, HuntingTrophy trophy) => CanLootStorage(player, trophy);
		void OnEntitySpawned(HotAirBalloon balloon) => HandleVehicleSpawn(balloon);
		
		object OnNpcTargetSense(NPCPlayer npc, BasePlayer target, AIBrainSenses npcBrain)
        {
			if (npc.skinID == _rbPluginID)
			{
				if (TryGetRaidBase(npc.transform.position, out var rbData) && !rbData.CanInteractWithRaid(target.userID))
					return true;
            }
			else if (npc.skinID == _rrPluginID)
			{
				if (_config.RandomRaids_Enabled && _rrAllRaiders.TryGetValue(npc.net.ID.Value, out var rrData) && !rrData.PlayersList.Contains(target.userID))
					return true;
            }
			return null;
		}

		void OnCargoShipHarborLeave(CargoShip cargoShip)
		{
			if (cargoShip.skinID != 0uL) return;
			MonumentData cargoData = null, harborData = null;
			string[] monuments = GetMonumentsByPos(cargoShip.transform.position);
			foreach (var monumentID in monuments)
			{
				if (!_monumentsList.ContainsKey(monumentID))
					continue;
				if (cargoData == null && monumentID.StartsWith("CargoShip"))
					cargoData = _monumentsList[monumentID];
				else if (harborData == null && monumentID.Contains("harbor"))
					harborData = _monumentsList[monumentID];
			}
			if (cargoData == null || !cargoData.IsPvP || harborData == null || harborData.Settings.IsPvP)
				return;
			harborData.RemovePvP();
		}
		
                private HashSet<string> _dynamicPvPs;
            }
}

namespace Oxide.Plugins.ExtensionsRealPVE
{
    public static class ExtensionMethods
	{
		public static string GetGrid(this Vector3 a) { var b = TerrainMeta.Size.x / 1024f; var c = 7f; var d = new Vector2(TerrainMeta.NormalizeX(a.x), TerrainMeta.NormalizeZ(a.z)) * b * c; var e = Mathf.Floor(d.x) + 1f; return $"{(e / 26f > 1f ? (char)(64 + (int)(e / 26f)) : "")}{(char)(64 + (int)((e - 1) % 26 + 1))}{Mathf.Floor(b * c - d.y)}"; }
		public static Vector3 ToVector3(this string a) { try { a = a.Replace("(", "").Replace(")", "").Replace(" ", ""); var b = a.Split(','); return new Vector3(float.Parse(b[0]), float.Parse(b[1]), float.Parse(b[2])); } catch { return Vector3.zero; } }
		public static bool TryParseVector3(this string a, out Vector3 b) { b = a.ToVector3(); return b != Vector3.zero; }
		public static string FirstToUpper(this string a) => !string.IsNullOrWhiteSpace(a) ? char.ToUpper(a[0]) + a.Substring(1) : a;
		public static string ToLangKey(this string langKey) => string.IsNullOrWhiteSpace(langKey) || langKey.Length != 2 || !langKey.All(c => c is >= 'A' and <= 'Z' or >= 'a' and <= 'z') ? "en" : langKey.ToLower(System.Globalization.CultureInfo.InvariantCulture);
		public static void Urma(this HitInfo a) { a.damageTypes.Clear(); a.DidHit = false; a.DoHitEffects = false; }
		public static void UrmaB(this HitInfo a) { a.Initiator = null; a.damageTypes.Clear(); a.DidHit = false; a.DoHitEffects = false; }
		public static object TasirMumkin(this BasePlayer a, ulong b) => b == a.userID || (a.Team != null && a.Team.members.Contains(b)) ? null : false;
		public static object BinoMumkin(this BasePlayer a) { var b = a.GetBuildingPrivilege(); return b == null || b.IsAuthed(a) ? null : false; }
		public static bool Uyda(this BasePlayer a) { var b = a.GetBuildingPrivilege(); return b != null && b.IsAuthed(a); }
		public static bool IsDefault(this VersionNumber a) => a.Major == 0 && a.Minor == 0 && a.Patch == 0;
		public static void SendEffect(this BasePlayer a, string b = "assets/bundled/prefabs/fx/invite_notice.prefab") => EffectNetwork.Send(new Effect(b, a.transform.position, Vector3.zero), a.Connection);
        public static void RunEffect(this BaseEntity a, string b = "assets/prefabs/locks/keypad/effects/lock.code.denied.prefab") => Effect.server.Run(b, a, 0u, Vector3.zero, Vector3.zero);
	}
}
