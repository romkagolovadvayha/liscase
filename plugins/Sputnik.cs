using Rust;
using Oxide.Core;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using Oxide.Plugins.SputnikExtensionMethods;
using System;
using System.IO;
using System.Reflection;
using System.Collections;
using System.Collections.Generic;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using UnityEngine;
using UnityEngine.AI;
using Facepunch;
using UnityEngine.Networking;
using Random = UnityEngine.Random;

namespace Oxide.Plugins
{
    [Info("Sputnik", "Adem", "1.5.6")]
    internal class Sputnik : RustPlugin
    {
        #region Variables
        private const bool En = false;
        private static Sputnik _ins;
        [PluginReference] private Plugin NpcSpawn, PveMode, GUIAnnouncements, DiscordMessages, ZoneManager, RaidableBases, Economics, ServerRewards, IQEconomic, Notify, DynamicPVP, IQDronePatrol, AlphaLoot, CustomLoot, Loottable;

        private readonly HashSet<string> _subscribeMethods = new HashSet<string>
        {
           "OnEntitySpawned",
           "OnEntityTakeDamage",
           "OnEntityDeath",
           "OnCorpsePopulate",
           "OnPlayerSleep",
           "CanHelicopterTarget",
           "OnTrapTrigger",
           "OnEntityEnter",
           "OnCardSwipe",
           "OnLootSpawn",
           "CanHackCrate",
           "CanLootEntity",
           "OnLootEntity",

           "OnCustomNpcTarget",
           "CanEntityTakeDamage",
           "CanEntityTrapTrigger",
           "CanEntityBeTargeted",
           "CanPopulateLoot",
           "OnCustomLootContainer",
           "OnCustomLootNPC",
           "OnRestoreUponDeath",
           "SetOwnerPveMode",
           "ClearOwnerPveMode",
           "OnCreateDynamicPVP"
        };

        private readonly HashSet<string> _permanentHooks = new HashSet<string>
        {
            "OnLootSpawn"
        };

        private EventController _eventController;
        #endregion Variables
        
        #region Api
        private bool IsSputnikHeli(ulong netID)
        {
            return EventHeli.GetEventHeliByNetId(netID) != null;
        }
        #endregion Api

        #region Hooks
        private void Init()
        {
            Unsubscribes(true);
        }

        private void OnServerInitialized()
        {
            _ins = this;

            if (!NpcSpawnManager.IsNpcSpawnReady())
                return;
            if (!DataFileManager.TryLoadData())
                return;

            LoadDefaultMessages();
            UpdateConfig();
            UnsubscribesPermanentHooks();

            PrefabController.CachePrefabs();
            GuiManager.LoadImages();
            SpawnPositionFinder.InitialUpdate();
            EventLauncher.AutoStartEvent();
        }

        private void Unload()
        {
            EventLauncher.StopEvent(true);
            SpawnPositionFinder.StopCachingSpawnPoints();
            _ins = null;
        }

        private object OnEntityTakeDamage(PatrolHelicopter patrolHelicopter, HitInfo info)
        {
            if (patrolHelicopter == null || patrolHelicopter.net == null || info == null)
                return null;
            
            if (!info.InitiatorPlayer.IsRealPlayer())
                return null;

            EventHeli eventHeli = EventHeli.GetEventHeliByNetId(patrolHelicopter.net.ID.Value);
            if (eventHeli == null)
                return null;

            if (!SputnikDebris.IsPlayerCanDealDamage(info.InitiatorPlayer, patrolHelicopter, true))
                return true;
            
            eventHeli.OnHeliAttacked(info.InitiatorPlayer.userID);
            return null;
        }

        private object OnEntityTakeDamage(AutoTurret autoTurret, HitInfo info)
        {
            if (autoTurret == null || autoTurret.net == null || info == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByTurretUid(autoTurret.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!info.InitiatorPlayer.IsRealPlayer())
                return true;
            if (!SputnikDebris.IsPlayerCanDealDamage(info.InitiatorPlayer, autoTurret, true))
                return true;
            
            sputnikDebris.OnSputnikAttacked();
            return null;
        }

        private object OnEntityTakeDamage(Landmine landmine, HitInfo info)
        {
            if (landmine == null || landmine.net == null || info == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByLandmineUid(landmine.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!info.InitiatorPlayer.IsRealPlayer())
                return true;
            if (!SputnikDebris.IsPlayerCanDealDamage(info.InitiatorPlayer, landmine, true) || sputnikDebris.IsPveModeBlockAction(info.InitiatorPlayer))
                return true;
            
            sputnikDebris.OnSputnikAttacked();
            return null;
        }

        private object OnEntityTakeDamage(ScientistNPC scientistNpc, HitInfo info)
        {
            if (scientistNpc == null || scientistNpc.net == null || info == null)
                return null;
            
            if (!info.InitiatorPlayer.IsRealPlayer())
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByNpcUid(scientistNpc.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!SputnikDebris.IsPlayerCanDealDamage(info.InitiatorPlayer, scientistNpc, true))
                return true;
            
            sputnikDebris.OnSputnikAttacked();
            return null;
        }

        private void OnEntityDeath(PatrolHelicopter patrolHelicopter, HitInfo info)
        {
            if (patrolHelicopter == null || patrolHelicopter.net == null)
                return;

            EventHeli eventHeli = EventHeli.GetEventHeliByNetId(patrolHelicopter.net.ID.Value);
            if (eventHeli == null || eventHeli.LastAttackedPlayer == 0)
                return;
            
            EconomyManager.AddBalance(eventHeli.LastAttackedPlayer, _config.SupportedPluginsConfig.EconomicsConfig.HeliPoint);
        }

        private void OnEntityDeath(AutoTurret autoTurret, HitInfo info)
        {
            if (autoTurret == null || autoTurret.net == null || info == null)
                return;
            
            if (!info.InitiatorPlayer.IsRealPlayer())
                return;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByTurretUid(autoTurret.net.ID.Value);
            if (sputnikDebris == null)
                return;

            EconomyManager.AddBalance(info.InitiatorPlayer.userID, _config.SupportedPluginsConfig.EconomicsConfig.TurretPoint);
        }

        private void OnEntityDeath(ScientistNPC scientistNpc, HitInfo info)
        {
            if (scientistNpc == null || scientistNpc.net == null || info == null)
                return;
            
            if (!info.InitiatorPlayer.IsRealPlayer())
                return;

            if (NpcSpawnManager.GetScientistByNetId(scientistNpc.net.ID.Value))
                EconomyManager.AddBalance(info.InitiatorPlayer.userID, _config.SupportedPluginsConfig.EconomicsConfig.NpcPoint);
        }

        private void OnPlayerDeath(BasePlayer player, HitInfo info)
        {
            if (player == null)
                return;

            ZoneController zoneController = ZoneController.GetZoneControllerByPlayerUserID(player.userID);
            if (zoneController == null)
                return;
            
            zoneController.OnPlayerLeaveZone(player);
        }

        private void OnPlayerSleep(BasePlayer player)
        {
            if (!player.IsRealPlayer())
                return;

            ZoneController zoneController = ZoneController.GetZoneControllerByPlayerUserID(player.userID);
            if (zoneController == null)
                return;
            
            zoneController.OnPlayerLeaveZone(player);
        }

        private object CanHelicopterTarget(PatrolHelicopterAI heli, BasePlayer player)
        {
            if (heli == null || heli.helicopterBase == null || heli.helicopterBase.net == null)
                return null;

            EventHeli eventHeli = EventHeli.GetEventHeliByNetId(heli.helicopterBase.net.ID.Value);
            if (eventHeli == null)
                return null;
            
            if (!eventHeli.IsHeliCanTarget())
                return false;

            return null;
        }

        private object OnTrapTrigger(Landmine landmine, GameObject gameObject)
        {
            if (landmine == null)
                return null;

            ScientistNPC scientistNpc = gameObject.ToBaseEntity() as ScientistNPC;
            if (scientistNpc == null)
                return null;
            
            if (NpcSpawnManager.GetNpcConfigByDisplayName(scientistNpc.displayName) != null)
                return true;

            return null;
        }

        private object OnCardSwipe(CardReader cardReader, Keycard keycard, BasePlayer player)
        {
            if (player == null || cardReader == null || cardReader.net == null || keycard == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByCardReaderUid(cardReader.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            sputnikDebris.OnCardSwipe(keycard, player);
            return true;
        }

        private void OnLootSpawn(LootContainer container)
        {
            if (container == null || container.inventory == null || container.net == null)
                return;

            if (_config.CustomCardConfig.EnableSpawnInDefaultCrates && SputnikDebris.GetSputnikByCrateUid(container.net.ID.Value) == null)
                ItemSpawner.SpawnSpaceCardInCrate(container);
        }

        private object CanHackCrate(BasePlayer player, HackableLockedCrate crate)
        {
            if (player == null || crate == null || crate.net == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByCrateUid(crate.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!sputnikDebris.IsAggressive())
            {
                sputnikDebris.OnSputnikAttacked();
                return true;
            }

            if (sputnikDebris.IsNeedCardForCrate(crate.net.ID.Value) && sputnikDebris.IsCardReaderExistAndClosed())
            {
                NotifyManager.SendMessageToPlayer(player, "NeedUseCard", _config.Prefix);
                return true;
            }

            if (sputnikDebris.IsPveModeBlockAction(player))
                return null;

            EconomyManager.AddBalance(player.userID, _config.SupportedPluginsConfig.EconomicsConfig.HackCratePoint);
            return null;
        }

        private object CanLootEntity(BasePlayer player, LootContainer container)
        {
            if (player == null || container == null || container.net == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByCrateUid(container.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!sputnikDebris.IsAggressive())
            {
                sputnikDebris.OnSputnikAttacked();
                return true;
            }

            if (sputnikDebris.IsNeedCardForCrate(container.net.ID.Value) && sputnikDebris.IsCardReaderExistAndClosed())
            {
                NotifyManager.SendMessageToPlayer(player, "NeedUseCard", _config.Prefix);
                return true;
            }

            return null;
        }

        private void OnLootEntity(BasePlayer player, LootContainer container)
        {
            if (player == null || container == null || container.net == null)
                return;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByCrateUid(container.net.ID.Value);
            if (sputnikDebris == null)
                return;

            sputnikDebris.OnEventCrateLooted(container, player.userID);
        }

        #region OtherPlugins
        private object OnCustomNpcTarget(ScientistNPC scientistNpc, BasePlayer player)
        {
            if (scientistNpc == null || scientistNpc.net == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByNpcUid(scientistNpc.net.ID.Value);
            if (sputnikDebris == null)
                return null;
            
            if (!sputnikDebris.IsAggressive() && !_config.AggressiveConfig.NpcAlwaysAggressive)
                return false;

            return null;
        }

        private object CanEntityTakeDamage(AutoTurret autoTurret, HitInfo info)
        {
            if (autoTurret == null || autoTurret.net == null || info == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByTurretUid(autoTurret.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!info.InitiatorPlayer.IsRealPlayer())
                return false;
            
            if (!SputnikDebris.IsPlayerCanDealDamage(info.InitiatorPlayer, autoTurret, false) || sputnikDebris.IsPveModeBlockAction(info.InitiatorPlayer))
                return false;

            return true;
        }

        private object CanEntityTakeDamage(Landmine landmine, HitInfo info)
        {
            if (landmine == null || landmine.net == null || info == null)
                return null;

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByLandmineUid(landmine.net.ID.Value);
            if (sputnikDebris == null)
                return null;

            if (!info.InitiatorPlayer.IsRealPlayer())
                return false;
            
            if (!SputnikDebris.IsPlayerCanDealDamage(info.InitiatorPlayer, landmine, false) || sputnikDebris.IsPveModeBlockAction(info.InitiatorPlayer))
                return false;

            return true;
        }

        private object CanEntityTakeDamage(BasePlayer player, HitInfo hitInfo)
        {
            if (!player.IsRealPlayer() || hitInfo == null)
                return null;

            if (hitInfo.InitiatorPlayer.IsRealPlayer() && !_config.SupportedPluginsConfig.PveMode.Enable)
            {
                ZoneController zoneController = ZoneController.GetZoneControllerByPlayerUserID(player.userID);

                if (zoneController != null && zoneController.IsPvpZone() && ZoneController.GetZoneControllerByPlayerUserID(hitInfo.InitiatorPlayer.userID) != null)
                    return true;
            }
            else if (hitInfo.Initiator != null && hitInfo.Initiator.net != null)
            {
                if (hitInfo.Initiator is AutoTurret && SputnikDebris.GetSputnikByTurretUid(hitInfo.Initiator.net.ID.Value) != null)
                    return true;
                
                if (hitInfo.Initiator is Landmine && SputnikDebris.GetSputnikByLandmineUid(hitInfo.Initiator.net.ID.Value) != null)
                    return true;
            }

            return null;
        }

        private object CanEntityTrapTrigger(Landmine landmine, BasePlayer player)
        {
            if (landmine == null || !player.IsRealPlayer())
                return null;

            if (SputnikDebris.GetSputnikByLandmineUid(landmine.net.ID.Value) != null)
                return true;

            return null;
        }

        private object CanEntityTrapTrigger(Landmine landmine, ScientistNPC scientistNpc)
        {
            if (_eventController == null || scientistNpc == null)
                return null;

            if (NpcSpawnManager.GetNpcConfigByDisplayName(scientistNpc.displayName) != null)
                return false;

            return null;
        }

        private object OnRestoreUponDeath(BasePlayer player)
        {
            if (player == null || _eventController == null)
                return null;

            if (_config.SupportedPluginsConfig.RestoreUponDeath.DisableRestore)
            {
                ZoneController zoneController = ZoneController.GetZoneControllerByPlayerUserID(player.userID);

                if (zoneController != null)
                    return true;
            }

            return null;
        }

        private void SetOwnerPveMode(string eventName, BasePlayer owner)
        {
            if (_eventController == null)
                return;

            PveModeManager pveModeController = PveModeManager.GetPveControllerByZoneName(eventName);

            if (pveModeController != null)
                pveModeController.OnNewOwnerSet(owner);
        }

        private void ClearOwnerPveMode(string eventName)
        {
            if (_eventController == null)
                return;

            PveModeManager pveModeController = PveModeManager.GetPveControllerByZoneName(eventName);

            if (pveModeController != null)
                pveModeController.OnOwnerDeleted();
        }

        private object OnCreateDynamicPVP(string eventName, PatrolHelicopter patrolHelicopter)
        {
            if (patrolHelicopter == null || patrolHelicopter.net == null)
                return null;

            if (_ins._config.SupportedPluginsConfig.BetterNpcConfig.IsHeliNpc)
                return null;

            EventHeli eventHeli = EventHeli.GetEventHeliByNetId(patrolHelicopter.net.ID.Value);
            if (eventHeli != null)
                return true;

            return null;
        }
        
        private object CanHelicopterSpawnNpc(PatrolHelicopter helicopter)
        {
            if (helicopter == null || helicopter.net == null)
                return null;
            
            if (_ins._config.SupportedPluginsConfig.BetterNpcConfig.IsHeliNpc)
                return null;

            if (EventHeli.GetEventHeliByNetId(helicopter.net.ID.Value) != null)
                return true;

            return null;
        }
        #endregion OtherPlugins
        
        #region Loot
        private void OnCorpsePopulate(ScientistNPC scientistNpc, NPCPlayerCorpse corpse)
        {
            if (scientistNpc == null || scientistNpc.net == null || corpse == null)
                return;
            
            EntityPresetInfo entityPresetInfo = scientistNpc.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return;
            
            NpcConfig npcConfig = NpcSpawnManager.GetNpcConfigByPresetName(entityPresetInfo.presetName);
            if (npcConfig == null)
                return;
            
            LootController.UpdateNpcCorpse(corpse, npcConfig);
        }

        private void OnCrateSpawned(PatrolHelicopter patrolHelicopter, LockedByEntCrate crate)
        {
            if (patrolHelicopter == null || patrolHelicopter.net == null || crate == null)
                return;
            
            EntityPresetInfo entityPresetInfo = patrolHelicopter.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return;
            
            HeliConfig heliConfig = _config.HeliConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (heliConfig == null)
                return;
            
            if (heliConfig.InstCrateOpen)
            {
                crate.SetLockingEnt(null);
                crate.SetLocked(false);
            }

            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByHeliUid(patrolHelicopter.net.ID.Value);
            sputnikDebris.AddEventCrate(crate);
            LootController.UpdateHeliOrBradleyCrate(crate, heliConfig.LootTableConfig, heliConfig.PresetName);
        }
        
        #region LootTablePlugin
        private object OnContainerPopulate(LootContainer lootContainer)
        {
            if (lootContainer == null)
                return null;
            
            LockedByEntCrate lockedByEntCrate = lootContainer as LockedByEntCrate;
            if (lockedByEntCrate != null)
            {
                LootTableConfig lootTableConfig = null;
                
                float minDistance = float.MaxValue;
                EventHeli minDistanceHeli = null;

                foreach (SputnikDebris sputnikDebris in SputnikDebris.AllSputnikDebris)
                {
                    if (sputnikDebris._eventHeli == null)
                        continue;
                    
                    float distance = Vector3.Distance(sputnikDebris._eventHeli.transform.position, lockedByEntCrate.transform.position);
                    if (distance < minDistance)
                    {
                        minDistance = distance;
                        minDistanceHeli = sputnikDebris._eventHeli;
                    }
                }

                if (minDistanceHeli != null && Vector3.Distance(minDistanceHeli.transform.position, lockedByEntCrate.transform.position) < 15f)
                    lootTableConfig = minDistanceHeli.HeliConfig.LootTableConfig;
                
                if (lootTableConfig == null)
                    return null;

                if (!lootTableConfig.IsLoottablePlugin)
                    return true;
                    
                return null;
            }
            
            EntityPresetInfo entityPresetInfo = lootContainer.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return null;

            CrateConfig crateConfig = _config.CrateConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (crateConfig == null)
                return null;
            
            if (!crateConfig.LootTableConfig.IsLoottablePlugin)
                return true;
            
            return null;
        }

        private object OnCorpsePopulate(NPCPlayerCorpse corpse)
        {
            if (corpse == null)
                return null;

            EntityPresetInfo entityPresetInfo = corpse.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return null;
            
            NpcConfig npcConfig =  _config.NpcConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (npcConfig == null)
                return null;
            
            if (!npcConfig.LootTableConfig.IsLoottablePlugin)
                return true;
            
            return null;
        }
        #endregion LootTablePlugin
        
        #region AlphaLoot
        private object CanPopulateLoot(LootContainer lootContainer)
        {
            if (lootContainer == null)
                return null;

            EntityPresetInfo entityPresetInfo = lootContainer.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return null;

            LockedByEntCrate lockedByEntCrate = lootContainer as LockedByEntCrate;
            if (lockedByEntCrate != null)
            {
                LootTableConfig lootTableConfig = null;
                
                HeliConfig heliConfig = _config.HeliConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
                if (heliConfig != null)
                    lootTableConfig = heliConfig.LootTableConfig;
                
                if (lootTableConfig == null)
                    return null;

                if (!lootTableConfig.IsAlphaLoot)
                    return true;
                    
                return null;
            }

            CrateConfig crateConfig = _config.CrateConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (crateConfig == null)
                return null;
            
            if (!crateConfig.LootTableConfig.IsAlphaLoot)
                return true;
            
            return null;
        }

        private object CanPopulateLoot(ScientistNPC scientistNpc, NPCPlayerCorpse corpse)
        {
            if (corpse == null)
                return null;

            EntityPresetInfo entityPresetInfo = corpse.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return null;
            
            NpcConfig npcConfig =  _config.NpcConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (npcConfig == null)
                return null;
            
            if (!npcConfig.LootTableConfig.IsAlphaLoot)
                return true;
            
            return null;
        }
        #endregion AlphaLoot
        
        #region CustomLoot
        private object OnCustomLootContainer(NetworkableId net)
        {
            SputnikDebris sputnikDebris = SputnikDebris.GetSputnikByCrateUid(net.Value);
            if (sputnikDebris == null)
                return null;
            
            BaseEntity crateEntity = sputnikDebris.GetCrateByNetId(net.Value);
            if (crateEntity == null)
                return null;
            
            EntityPresetInfo entityPresetInfo = crateEntity.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return null;
            
            LockedByEntCrate lockedByEntCrate = crateEntity as LockedByEntCrate;
            if (lockedByEntCrate != null)
            {
                LootTableConfig lootTableConfig = null;
                
                HeliConfig heliConfig = _config.HeliConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
                if (heliConfig != null)
                    lootTableConfig = heliConfig.LootTableConfig;
                
                if (lootTableConfig == null)
                    return null;
                
                if (!lootTableConfig.IsCustomLootPlugin)
                    return true;
                    
                return null;
            }
            
            CrateConfig crateConfig = _config.CrateConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (crateConfig == null)
                return null;
            
            if (!crateConfig.LootTableConfig.IsCustomLootPlugin)
                return true;
            
            return null;
        }

        private object OnCustomLootNPC(NetworkableId net)
        {
            ScientistNPC scientistNpc = NpcSpawnManager.GetScientistByNetId(net.Value);
            if (scientistNpc == null)
                return null;
            
            EntityPresetInfo entityPresetInfo = scientistNpc.GetComponent<EntityPresetInfo>();
            if (entityPresetInfo == null)
                return null;
            
            NpcConfig npcConfig =  _config.NpcConfigs.FirstOrDefault(x => x.PresetName == entityPresetInfo.presetName);
            if (npcConfig == null)
                return null;

            if (!npcConfig.LootTableConfig.IsCustomLootPlugin)
                return true;
            
            return null;
        }
        #endregion CustomLoot
        #endregion Loot
        
        #endregion Hooks

        #region Commands
        [ChatCommand("sputnikstart")]
        private void ChatStartCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            if (arg != null && arg.Length >= 1)
                EventLauncher.DelayStartEvent(false, player, arg[0]);
            else
                EventLauncher.DelayStartEvent(false, player);
        }

        [ChatCommand("sputnikstop")]
        private void ChatStopCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            EventLauncher.StopEvent();
        }

        [ConsoleCommand("sputnikstart")]
        private void ConsoleStartCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() != null)
                return;

            if (arg.Args != null && arg.Args.Length > 0)
                EventLauncher.DelayStartEvent(false, null, arg.Args[0]);
            else
                EventLauncher.DelayStartEvent();
        }

        [ConsoleCommand("sputnikstop")]
        private void ConsoleStopCommand(ConsoleSystem.Arg arg)
        {
            if (arg.Player() == null)
                EventLauncher.StopEvent();
        }

        [ChatCommand("givespacecard")]
        private void GiveCustomItemChatCommand(BasePlayer player, string command, string[] arg)
        {
            if (player == null || !player.IsAdmin || arg == null)
                return;

            ItemSpawner.GiveSpaceCardToPlayer(player);
            PrintToChat(player, GetMessage("GetSpaceCard", player.UserIDString, _config.Prefix));
        }

        [ConsoleCommand("givespacecard")]
        private void GiveCustomItemCommand(ConsoleSystem.Arg arg)
        {
            BasePlayer player = arg.Player();
            BasePlayer target = null;

            if (arg.Args.Length >= 1)
            {
                ulong userId = Convert.ToUInt64(arg.Args[0]);
                target = BasePlayer.FindByID(userId);
            }

            if (target == null)
            {
                PrintToConsole(player, "Player not found");
                return;
            }

            ItemSpawner.GiveSpaceCardToPlayer(target);
            PrintToChat(target, GetMessage("GetSpaceCard", target.UserIDString, _config.Prefix));
            Puts($"A space card was given to {target.displayName}");
        }

        [ChatCommand("sputnikspawnpoint")]
        private void SpawnPointCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            if (arg.Length <= 0)
            {
                PrintToChat(player, _config.Prefix + " Use the command: /sputnikspawnpoint <DebrisPresetName>");
                return;
            }

            string sputnikPresetName = arg[0];

            SputnikDebrisConfig sputnikDebrisConfig = _config.SputnikDebrisConfigs.FirstOrDefault(x => x.PresetName == sputnikPresetName);
            if (sputnikDebrisConfig == null)
            {
                PrintToChat(player, _config.Prefix + " <color=#ce3f27>Couldn't</color> find the preset");
                return;
            }

            sputnikDebrisConfig.CustomSpawnPoints.Add(player.transform.position.ToString());
            SaveConfig();
            PrintToChat(player, _config.Prefix + " New spawn point <color=#738d43>successfully</color> added");
        }

        [ChatCommand("getlocalposition")]
        private void SpawnGetLocalPositonCommand(BasePlayer player, string command, string[] arg)
        {
            if (!player.IsAdmin)
                return;

            if (_eventController == null)
            {
                NotifyManager.PrintError(player, $"{_config.Prefix} The event must be launched!");
                return;
            }

            SputnikDebris sputnikDebris = SputnikDebris.AllSputnikDebris.Where(x => x != null).Min(x => Vector3.Distance(player.transform.position, x.transform.position));
            if (sputnikDebris == null) 
                return;
            
            string position = PositionDefiner.GetLocalPosition(sputnikDebris.transform, player.transform.position).ToString();
            float rotation = (player.viewAngles - sputnikDebris.transform.rotation.eulerAngles).y;
            Debug("Position", position);
            Debug("Rotation", new Vector3(0, rotation, 0));

        }
        #endregion Commands

        #region Methods

        private void Unsubscribes(bool includePermanentHooks)
        {
            foreach (string hook in _subscribeMethods)
                if (includePermanentHooks || !_permanentHooks.Contains(hook))
                    Unsubscribe(hook);
        }

        private void UnsubscribesPermanentHooks()
        {
            foreach (string hook in _permanentHooks)
                Unsubscribe(hook);
        }

        private void Subscribes(bool includePermanentHooks)
        {
            foreach (string hook in _subscribeMethods)
                if (includePermanentHooks || !_permanentHooks.Contains(hook))
                    Subscribe(hook);
        }

        private static void Debug(params object[] arg)
        {
            string result = "";

            foreach (object obj in arg)
                if (obj != null)
                    result += obj.ToString() + " ";

            _ins.Puts(result);
        }
        #endregion Methods
        
        #region Classes
        private static class EventLauncher
        {
            private static Coroutine _autoEventCoroutine;
            private static Coroutine _delayedEventStartCoroutine;

            internal static bool IsEventActive()
            {
                return _ins != null && _ins._eventController != null;
            }

            internal static void AutoStartEvent()
            {
                if (!_ins._config.MainConfig.IsAutoEvent)
                    return;

                if (_autoEventCoroutine != null)
                    return;

                _autoEventCoroutine = ServerMgr.Instance.StartCoroutine(AutoEventCoroutine());
            }

            private static IEnumerator AutoEventCoroutine()
            {
                yield return CoroutineEx.waitForSeconds(UnityEngine.Random.Range(_ins._config.MainConfig.MinTimeBetweenEvents, _ins._config.MainConfig.MaxTimeBetweenEvents));
                DelayStartEvent(true);
                _autoEventCoroutine = null;
            }

            internal static void DelayStartEvent(bool isAutoActivated = false, BasePlayer activator = null, string presetName = "")
            {
                if (IsEventActive() || _delayedEventStartCoroutine != null)
                {
                    NotifyManager.PrintError(activator, "EventActive_Exeption");
                    return;
                }

                if (_autoEventCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_autoEventCoroutine);

                EventConfig eventConfig = DefineEventConfig(presetName);
                if (eventConfig == null)
                {
                    NotifyManager.PrintError(activator, "ConfigurationNotFound_Exeption");
                    StopEvent(shouldSendEndMessage: false);
                    return;
                }

                _delayedEventStartCoroutine = ServerMgr.Instance.StartCoroutine(DelayedStartEventCoroutine(eventConfig));

                if (!isAutoActivated && _ins._config.NotifyConfig.PreStartTime > 0)
                    NotifyManager.PrintInfoMessage(activator, "SuccessfullyLaunched");
            }

            private static EventConfig DefineEventConfig(string eventPresetName = "")
            {
                if (eventPresetName != "")
                    return _ins._config.EventConfigs.FirstOrDefault(x => x.PresetName == eventPresetName);

                HashSet<EventConfig> suitableEventConfigs = _ins._config.EventConfigs.Where(x => x.Chance > 0 && IsEventConfigSuitableByTime(x));

                if (suitableEventConfigs == null || suitableEventConfigs.Count == 0)
                    return null;

                float sumChance = 0;
                foreach (EventConfig eventConfig in suitableEventConfigs)
                    sumChance += eventConfig.Chance;

                float random = UnityEngine.Random.Range(0, sumChance);

                foreach (EventConfig eventConfig in suitableEventConfigs)
                {
                    random -= eventConfig.Chance;

                    if (random <= 0)
                        return eventConfig;
                }

                return null;
            }

            private static IEnumerator DelayedStartEventCoroutine(EventConfig eventConfig)
            {
                if (_ins._config.NotifyConfig.PreStartTime > 0)
                    NotifyManager.SendMessageToAll("PreStartEvent", _ins._config.Prefix, eventConfig.DisplayName, _ins._config.NotifyConfig.PreStartTime);

                yield return CoroutineEx.waitForSeconds(_ins._config.NotifyConfig.PreStartTime);

                StartEvent(eventConfig);
            }

            private static void StartEvent(EventConfig eventConfig)
            {
                GameObject gameObject = new GameObject();
                _ins._eventController = gameObject.AddComponent<EventController>();
                _ins._eventController.Init(eventConfig);

                if (_ins._config.MainConfig.EnableStartStopLogs)
                    NotifyManager.PrintLogMessage("EventStart_Log", eventConfig.PresetName);

                Interface.CallHook("OnSputnikEventStart");
            }

            private static bool IsEventConfigSuitableByTime(EventConfig eventConfig)
            {
                if (eventConfig.MinTimeAfterWipe <= 0 && eventConfig.MaxTimeAfterWipe <= 0)
                    return true;

                int timeScienceWipe = GetTimeScienceLastWipe();

                if (timeScienceWipe < eventConfig.MinTimeAfterWipe)
                    return false;
                if (eventConfig.MaxTimeAfterWipe > 0 && timeScienceWipe > eventConfig.MaxTimeAfterWipe)
                    return false;

                return true;
            }

            private static int GetTimeScienceLastWipe()
            {
                DateTime startTime = new DateTime(2019, 1, 1, 0, 0, 0);

                double realTime = DateTime.UtcNow.Subtract(startTime).TotalSeconds;
                double wipeTime = SaveRestore.SaveCreatedTime.Subtract(startTime).TotalSeconds;

                return Convert.ToInt32(realTime - wipeTime);
            }

            internal static void StopEvent(bool isPluginUnloading = false, bool shouldSendEndMessage = true)
            {
                if (IsEventActive())
                {
                    _ins.Unsubscribes(false);
                    _ins._eventController.KillController();
                    SputnikDebris.KillAllSputniks();
                    Interface.CallHook("OnSputnikEventStop");

                    if (shouldSendEndMessage)
                        NotifyManager.SendMessageToAll("EndEvent", _ins._config.Prefix);

                    if (_ins._config.MainConfig.EnableStartStopLogs)
                        NotifyManager.PrintLogMessage("EventStop_Log");

                    EconomyManager.OnEventEnd();
                    NpcSpawnManager.ClearData(false);
                    PveModeManager.SendCooldownAndClearData();
                    EventHeli.ClearData();
                    GuiManager.DestroyAllGui();

                    if (!isPluginUnloading)
                        AutoStartEvent();
                }

                ZoneController.ClearData();

                if (_delayedEventStartCoroutine != null)
                {
                    ServerMgr.Instance.StopCoroutine(_delayedEventStartCoroutine);
                    _delayedEventStartCoroutine = null;
                }
            }
        }

        private class EventController : FacepunchBehaviour
        {
            private EventConfig _eventConfig;
            private Coroutine _eventCoroutine;
            private int _eventTime;

            internal int GetEventTime()
            {
                return _eventTime;
            }

            internal void Init(EventConfig eventConfig)
            {
                _eventConfig = eventConfig;
                _eventTime = eventConfig.EventTime;
                SpawnPositionFinder.UpdateCachedPoints();

                if (TrySpawnSputniks())
                {
                    NotifyManager.SendMessageToAll("StartEvent", _ins._config.Prefix, eventConfig.DisplayName);
                    SputnikDebris.StartAllSputniksFalling();
                    SpawnPositionFinder.StartCachingSpawnPoints();
                    _eventCoroutine = ServerMgr.Instance.StartCoroutine(EventCoroutine());
                    _ins.Subscribes(true);
                }
                else
                {
                    EventLauncher.StopEvent(shouldSendEndMessage: false);
                }
            }

            private bool TrySpawnSputniks()
            {
                if (_eventConfig.FixedSputniksPresets == null || _eventConfig.FixedSputniksPresets.Count <= 0) 
                    return false;
                
                if (SpawnPositionFinder.GetCountSpawnPoints() < _eventConfig.FixedSputniksPresets.Count)
                    return false;

                Vector3 lastSputnikPosition = Vector3.zero;

                foreach (string sputnikDebrisPresetName in _eventConfig.FixedSputniksPresets)
                {
                    SputnikDebrisConfig sputnikDebrisConfig = _ins._config.SputnikDebrisConfigs.FirstOrDefault(x => x.PresetName == sputnikDebrisPresetName);
                    if (sputnikDebrisConfig == null)
                    {
                        _ins.PrintError($"Sputnik debris preset not found! (PresetName - {sputnikDebrisPresetName})");
                        continue;
                    }

                    Vector3 sputnikPosition = SpawnPositionFinder.GetSpawnPosition(sputnikDebrisConfig, lastSputnikPosition);
                    if (sputnikPosition == Vector3.zero)
                    {
                        _ins.PrintError("The event could not be started! Increase the number of cached spawn points!");
                        return false;
                    }

                    SputnikDebris.CreateSputnik(sputnikDebrisConfig, sputnikPosition);
                    lastSputnikPosition = sputnikPosition;
                }

                return SputnikDebris.IsAnySputnikAlive();
            }

            private IEnumerator EventCoroutine()
            {
                while (_eventTime > 0 && SputnikDebris.IsAnySputnikAlive())
                {
                    _eventTime -= 1;

                    if (_ins._config.NotifyConfig.TimeNotifications.Contains(_eventTime))
                        NotifyManager.SendMessageToAll("RemainTime", _ins._config.Prefix, _eventConfig.DisplayName, _eventTime);

                    yield return CoroutineEx.waitForSeconds(1);
                }

                EventLauncher.StopEvent();
            }

            internal void KillController()
            {
                if (_eventCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_eventCoroutine);

                Destroy(this);
                _ins._eventController = null;
            }
        }

        private class SputnikDebris : FacepunchBehaviour
        {
            internal static readonly HashSet<SputnikDebris> AllSputnikDebris = new HashSet<SputnikDebris>();

            internal SputnikDebrisConfig SputnikDebrisConfig;
            private PveModeManager _pveModeController;
            public EventHeli _eventHeli;
            private ZoneController _zoneController;
            private Coroutine _sputnikCoroutine;
            private FallingSputnikEffect _fallingSputnikEffect;
            private EventMapMarker _eventMapMarker;
            private CardReader _cardReader;
            private readonly HashSet<ScientistNPC> _npcs = new HashSet<ScientistNPC>();
            private readonly HashSet<AutoTurret> _turrets = new HashSet<AutoTurret>();
            private readonly HashSet<Landmine> _mines = new HashSet<Landmine>();
            private readonly HashSet<BaseEntity> _decorEntities = new HashSet<BaseEntity>();
            private bool _cardReaderOpen = true;
            private int _destroyTime;
            private int _aggressiveTime;
            private bool _isEventLooted;
            
            private readonly HashSet<ulong> _lootedContainersUids = new HashSet<ulong>();
            private readonly HashSet<BaseEntity> _crates = new HashSet<BaseEntity>();
            private readonly HashSet<ulong> _cardCrates = new HashSet<ulong>();
            private int _countOfUnlootedCrates;

            internal static bool IsAnySputnikAlive()
            {
                return AllSputnikDebris.Any(x => x != null);
            }

            internal static SputnikDebris GetSputnikByCrateUid(ulong netID)
            {
                return AllSputnikDebris.FirstOrDefault(x => x != null && x._crates.Any(y => y.IsExists() && y.net != null && y.net.ID.Value == netID));
            }

            internal static SputnikDebris GetSputnikByCardReaderUid(ulong netID)
            {
                return AllSputnikDebris.FirstOrDefault(x => x != null && x._cardReader != null && x._cardReader.net.ID.Value == netID);
            }

            internal static SputnikDebris GetSputnikByTurretUid(ulong netID)
            {
                return AllSputnikDebris.FirstOrDefault(x => x != null && x._turrets.Any(y => y.IsExists() && y.net.ID.Value == netID));
            }

            internal static SputnikDebris GetSputnikByLandmineUid(ulong netID)
            {
                return AllSputnikDebris.FirstOrDefault(x => x != null && x._mines.Any(y => y.IsExists() && y.net.ID.Value == netID));
            }

            internal static SputnikDebris GetSputnikByNpcUid(ulong netID)
            {
                return AllSputnikDebris.FirstOrDefault(x => x != null && x._npcs.Any(y => y.IsExists() && y.net.ID.Value == netID));
            }
            
            internal static SputnikDebris GetSputnikByHeliUid(ulong netID)
            {
                return AllSputnikDebris.FirstOrDefault(x => x != null && x._eventHeli != null && x._eventHeli._patrolHelicopter != null && x._eventHeli.GetHeliNetId() == netID);
            }
            
            
            public void AddEventCrate(BaseEntity entity)
            {
                _crates.Add(entity);
            }
            
            public BaseEntity GetCrateByNetId(ulong netID)
            {
                return _crates.FirstOrDefault(x => x != null && x.net != null && x.net.ID.Value == netID);
            }

            public void OnEventCrateLooted(BaseEntity baseEntity, ulong userId)
            {
                if (baseEntity.net == null)
                    return;

                if (!IsCrateLooted(baseEntity.net.ID.Value))
                {
                    if (_ins._config.SupportedPluginsConfig.EconomicsConfig.Crates.TryGetValue(baseEntity.PrefabName, out double cratePoint))
                        EconomyManager.AddBalance(userId, cratePoint);

                    _lootedContainersUids.Add(baseEntity.net.ID.Value);
                }
                
                UpdateCountOfUnlootedCrates();
                OnSputnikAttacked();
                EventPassingCheck();
            }
            
            public void UpdateCountOfUnlootedCrates()
            {
                _countOfUnlootedCrates = _crates.Where(x => x != null && x.IsExists() && x.net != null && !IsCrateLooted(x.net.ID.Value)).Count;
            }
            
            public int GetCountOfUnlootedCrates()
            {
                return _countOfUnlootedCrates;
            }
            
            public bool IsEventCrate(ulong netID)
            {
                return _crates.Any(x => x != null && x.net != null && x.net.ID.Value == netID);
            }
            
            public bool IsCrateLooted(ulong netID)
            {
                return _lootedContainersUids.Contains(netID);
            }
            
            public HashSet<ulong> GetEventCratesNetIDs()
            {
                HashSet<ulong> eventCrates = new HashSet<ulong>();

                foreach (BaseEntity crate in _crates)
                    if (crate != null && crate.net != null)
                        eventCrates.Add(crate.net.ID.Value);

                return eventCrates;
            }

            public bool IsNeedCardForCrate(ulong netID)
            {
                return _cardCrates.Contains(netID);
            }
            

            internal Vector3 GetEventPosition()
            {
                return transform.position;
            }

            internal static void StartAllSputniksFalling()
            {
                foreach (SputnikDebris sputnikClass in AllSputnikDebris)
                    if (sputnikClass != null)
                        sputnikClass.StartFalling();
            }

            internal int GetCountOfAliveNpc()
            {
                return _npcs.Where(x => x.IsExists()).Count;
            }

            internal int GetEventTime()
            {
                return _destroyTime;
            }

            internal static bool IsPlayerCanDealDamage(BasePlayer player, BaseCombatEntity sputnikEntity, bool shouldSendMessages)
            {
                Vector3 playerGroundPosition = new Vector3(player.transform.position.x, 0, player.transform.position.z);
                Vector3 entityGroundPosition = new Vector3(sputnikEntity.transform.position.x, 0, sputnikEntity.transform.position.z);
                float distance = Vector3.Distance(playerGroundPosition, entityGroundPosition);
                float maxDamageDistance = sputnikEntity is PatrolHelicopter ? _ins._config.MainConfig.MaxHeliDamageDistance : _ins._config.MainConfig.MaxGroundDamageDistance;

                if (maxDamageDistance > 0 && distance > maxDamageDistance)
                {
                    if (shouldSendMessages)
                        NotifyManager.SendMessageToPlayer(player, "DamageDistance", _ins._config.Prefix);

                    return false;
                }

                return true;
            }

            internal bool IsPveModeBlockAction(BasePlayer player)
            {
                return _pveModeController != null && PveModeManager.IsPveModeBlockAction(player);
            }

            internal bool IsAggressive()
            {
                return _ins._config.AggressiveConfig.AggressiveSecurityMode || _aggressiveTime > 0;
            }

            internal void OnSputnikAttacked()
            {
                if (_ins._config.AggressiveConfig.AggressiveSecurityMode)
                    return;

                if (!_ins._config.AggressiveConfig.MakeAllSputniksAggressive)
                    UpdateAggressive();
                else
                    foreach (SputnikDebris sputnikClass in AllSputnikDebris)
                        if (sputnikClass != null)
                            sputnikClass.UpdateAggressive();
            }

            private void UpdateAggressive()
            {
                if (_aggressiveTime <= 0)
                    MakeSputnikAggressive();

                _aggressiveTime = _ins._config.AggressiveConfig.AggressiveTime;
            }

            internal bool IsCardReaderExistAndClosed()
            {
                return _cardReader.IsExists() && !_cardReaderOpen;
            }

            internal void EventPassingCheck()
            {
                if (_isEventLooted)
                    return;

                if (_countOfUnlootedCrates > 0) 
                    return;
                
                _isEventLooted = true;

                if (_ins._config.MainConfig.DestroyAfterLootingTime < _destroyTime)
                    _destroyTime = _ins._config.MainConfig.DestroyAfterLootingTime;
            }

            internal void OnCardSwipe(Keycard keycard, BasePlayer player)
            {
                Item keyCardItem = keycard.GetCachedItem();

                if (keyCardItem == null)
                    return;

                if (!IsAggressive())
                {
                    OnSputnikAttacked();
                    return;
                }

                if (keyCardItem.info.shortname == _ins._config.CustomCardConfig.ShortName && keyCardItem.skin == _ins._config.CustomCardConfig.SkinID)
                {
                    Effect.server.Run(_cardReader.accessGrantedEffect.resourcePath, _cardReader.audioPosition.position, Vector3.up);
                    keyCardItem.LoseCondition(_ins._config.CustomCardConfig.HealthLossScale);
                    _cardReaderOpen = true;
                }
                else
                {
                    NotifyManager.SendMessageToPlayer(player, "NeedUseCard", _ins._config.Prefix);
                    Effect.server.Run(_cardReader.accessDeniedEffect.resourcePath, _cardReader.audioPosition.position, Vector3.up);
                    _cardReader.CancelInvoke(_cardReader.GrantCard);
                }
            }
            
            internal static void CreateSputnik(SputnikDebrisConfig sputnikDebrisConfig, Vector3 position)
            {
                GameObject gameObject = new GameObject();
                gameObject.layer = (int)Layer.Reserved1;
                gameObject.transform.position = position;
                SputnikDebris sputnik = gameObject.AddComponent<SputnikDebris>();
                sputnik.Init(sputnikDebrisConfig);
                AllSputnikDebris.Add(sputnik);
            }

            private void Init(SputnikDebrisConfig sputnikDebrisConfig)
            {
                SputnikDebrisConfig = sputnikDebrisConfig;
            }

            private void StartFalling()
            {
                if (transform.position.y >= _ins._config.FallingConfig.MaxFallHeight)
                    OnSputnikFell();
                else
                    _fallingSputnikEffect = FallingSputnikEffect.CreateFallingEffect(this);
            }

            internal void OnSputnikFell()
            {
                if (_sputnikCoroutine != null)
                    return;

                _sputnikCoroutine = ServerMgr.Instance.StartCoroutine(SputnikCoroutine());
            }

            private IEnumerator SputnikCoroutine()
            {
                for (int i = 0; i < _ins._config.FallingConfig.CountEffects && gameObject != null && gameObject.transform != null; i++)
                {
                    CreateCrushEffect();
                    yield return CoroutineEx.waitForSeconds(0.1f);
                }

                BuildSputnik();
                UpdateCountOfUnlootedCrates();

                for (int i = 0; i < _ins._config.FallingConfig.CountEffects && gameObject != null && gameObject.transform != null; i++)
                {
                    CreateCrushEffect();
                    yield return CoroutineEx.waitForSeconds(0.1f);
                }

                if (_ins._config.AggressiveConfig.AggressiveSecurityMode)
                    MakeSputnikAggressive();
                else
                    MakeSputnikNoAggressive();

                NotifyManager.SendMessageToAll("Crash", _ins._config.Prefix, MapHelper.PositionToString(gameObject.transform.position));
                _destroyTime = _ins._eventController.GetEventTime();

                while (_destroyTime > 0)
                {
                    _destroyTime -= 1;

                    if (_aggressiveTime > 0)
                    {
                        _aggressiveTime--;

                        if (_aggressiveTime <= 0)
                            MakeSputnikNoAggressive();
                    }

                    if (_destroyTime % 10 == 0)
                    {
                        UpdateCountOfUnlootedCrates();
                        EventPassingCheck();
                    }

                    yield return CoroutineEx.waitForSeconds(1);
                }

                KillSputnik();
            }

            private void CreateCrushEffect()
            {
                Effect.server.Run("assets/bundled/prefabs/fx/explosions/explosion_03.prefab", gameObject.transform.position + new Vector3(UnityEngine.Random.Range(-7.5f, 7.5f), 0, UnityEngine.Random.Range(-7.5f, 7.5f)));
                Effect.server.Run("assets/bundled/prefabs/fx/explosions/explosion_02.prefab", gameObject.transform.position + new Vector3(UnityEngine.Random.Range(-7.5f, 7.5f), 0, UnityEngine.Random.Range(-7.5f, 7.5f)));
            }

            private void MakeSputnikAggressive()
            {
                if (_ins._config.AggressiveConfig.TurretAggressiveMode)
                    return;

                foreach (AutoTurret autoTurret in _turrets)
                    if (autoTurret.IsExists())
                        autoTurret.UpdateFromInput(10, 0);
            }

            private void MakeSputnikNoAggressive()
            {
                if (_ins._config.AggressiveConfig.TurretAggressiveMode)
                    return;

                foreach (AutoTurret autoTurret in _turrets)
                    if (autoTurret.IsExists())
                        autoTurret.UpdateFromInput(0, 0);
            }

            private void BuildSputnik()
            {
                CreateGroundSputnik();
                CreateCardReader();
                CreateCrates();
                CreateMines();
                CreateHeli();
                CreateTurrets();
                CreateNpCs();
                IqDroneManager.SpawnDrones(transform.position, SputnikDebrisConfig.IqDronePresetConfig, (int)(SputnikDebrisConfig.ZoneConfig.Radius / 1.5f));

                _pveModeController = CreatePveModeController();
                _zoneController = ZoneController.CreateZone(this, _pveModeController, SputnikDebrisConfig.ZoneConfig.IsPvpZone);
                _eventMapMarker = EventMapMarker.CreateMarker(this, _pveModeController);
            }

            private PveModeManager CreatePveModeController()
            {
                if (!PveModeManager.IsPveModeReady())
                    return null;

                HashSet<ulong> npcUIDs = new HashSet<ulong>();

                foreach (ScientistNPC scientistNpc in _npcs)
                    if (scientistNpc.IsExists() && scientistNpc.net != null)
                        npcUIDs.Add(scientistNpc.net.ID.Value);

                HashSet<ulong> turretsUIDs = new HashSet<ulong>();

                foreach (AutoTurret autoTurret in _turrets)
                    if (autoTurret.IsExists() && autoTurret.net != null)
                        turretsUIDs.Add(autoTurret.net.ID.Value);

                ulong heliNetId = _eventHeli != null ? _eventHeli.GetHeliNetId() : 0;

                return PveModeManager.CreatePveModeController(this, npcUIDs, GetEventCratesNetIDs(), turretsUIDs, heliNetId);
            }

            private void CreateGroundSputnik()
            {
                HashSet<EntData> entDatas = DataFileManager.GetSputnikData(SputnikDebrisConfig.LocationPreset);
                if (entDatas == null)
                {
                    _ins.PrintError($"Sputnik debris preset not found! ({SputnikDebrisConfig.LocationPreset})");
                    EventLauncher.StopEvent(shouldSendEndMessage: false);
                    return;
                }

                foreach (EntData entData in entDatas)
                {
                    Vector3 localPosition = entData.Pos.ToVector3();
                    Vector3 localRotation = entData.Rot.ToVector3();

                    Vector3 globalPosition = PositionDefiner.GetGlobalPosition(transform, localPosition);
                    Quaternion globalRotation = PositionDefiner.GetGlobalRotation(transform, localRotation);

                    BaseEntity decorEntity = BuildManager.SpawnDecorEntity(entData.Prefab, globalPosition, globalRotation);
                    decorEntity.SetFlag(BaseEntity.Flags.Busy, true);
                    _decorEntities.Add(decorEntity);
                }
            }

            private void CreateNpCs()
            {
                foreach (KeyValuePair<string, HashSet<string>> pair in SputnikDebrisConfig.NpCs)
                {
                    foreach (string positionString in pair.Value)
                    {
                        Vector3 globalPosition = PositionDefiner.GetGlobalPosition(gameObject.transform, positionString.ToVector3());
                        Vector3 spawnPosition = PositionDefiner.GetGroundPositionInPoint(globalPosition);

                        if (spawnPosition == Vector3.zero)
                            continue;

                        if (PositionDefiner.GetNavmeshInPoint(spawnPosition, 2, out NavMeshHit navMeshHit))
                            spawnPosition = navMeshHit.position;

                        ScientistNPC scientistNpc = NpcSpawnManager.SpawnScientistNpc(pair.Key, spawnPosition, 1, false);
                        _npcs.Add(scientistNpc);
                    }
                }
            }

            private void CreateCrates()
            {
                foreach (var pair in SputnikDebrisConfig.GroundCrates)
                    foreach (LocationConfig locationConfig in pair.Value)
                        CreateCrate(pair.Key, PositionDefiner.GetGroundPositionInPoint(PositionDefiner.GetGlobalPosition(gameObject.transform, locationConfig.Position.ToVector3())), PositionDefiner.GetGlobalRotation(gameObject.transform, locationConfig.Rotation.ToVector3()));

                foreach (var pair in SputnikDebrisConfig.Crates)
                    foreach (LocationConfig locationConfig in pair.Value)
                        CreateCrate(pair.Key, PositionDefiner.GetGlobalPosition(gameObject.transform, locationConfig.Position.ToVector3()), PositionDefiner.GetGlobalRotation(gameObject.transform, locationConfig.Rotation.ToVector3()));
            }

            private void CreateCrate(string presetName, Vector3 position, Quaternion rotation)
            {
                CrateConfig crateConfig = _ins._config.CrateConfigs.FirstOrDefault(x => x.PresetName == presetName);
                if (crateConfig == null)
                {
                    NotifyManager.PrintError(null, "PresetNotFound_Exeption", presetName);
                    return;
                }

                BaseEntity crateEntity = BuildManager.SpawnStaticEntity(crateConfig.PrefabName, position, rotation, crateConfig.Skin);
                if (crateEntity == null)
                    return;
                
                _crates.Add(crateEntity);
                LootController.UpdateCrate(crateEntity, crateConfig);
                
                if (crateConfig.NeedSpaceCard && crateEntity.net != null)
                    _cardCrates.Add(crateEntity.net.ID.Value);

                if (crateEntity is HackableLockedCrate hackableLockedCrate)
                {
                    hackableLockedCrate.Invoke(() => hackableLockedCrate.hackSeconds = HackableLockedCrate.requiredHackSeconds - crateConfig.HackTime, 1.1f);
                    hackableLockedCrate.InvokeRepeating(() => hackableLockedCrate.SendNetworkUpdate(), 1f, 1f);
                }
            }

            private void CreateCardReader()
            {
                if (!SputnikDebrisConfig.EnableCardReader)
                    return;

                Vector3 localPosition = SputnikDebrisConfig.CardReaderLocation.Position.ToVector3();
                Vector3 localRotation = SputnikDebrisConfig.CardReaderLocation.Rotation.ToVector3();
                Vector3 globalPosition = PositionDefiner.GetGlobalPosition(gameObject.transform, localPosition);
                Quaternion globalRotation = PositionDefiner.GetGlobalRotation(gameObject.transform, localRotation);

                _cardReader = BuildManager.SpawnStaticEntity("assets/prefabs/io/electric/switches/cardreader.prefab", globalPosition, globalRotation) as CardReader;
                _cardReader.SetFlag(_cardReader.AccessLevel1, false);
                _cardReader.SetFlag(_cardReader.AccessLevel2, false);
                _cardReader.SetFlag(_cardReader.AccessLevel3, false);

                if (_ins._config.CustomCardConfig.ShortName.Contains("red"))
                    _cardReader.SetFlag(_cardReader.AccessLevel3, true);
                else if (_ins._config.CustomCardConfig.ShortName.Contains("blue"))
                    _cardReader.SetFlag(_cardReader.AccessLevel2, true);
                else
                    _cardReader.SetFlag(_cardReader.AccessLevel1, true);

                _cardReaderOpen = false;
                _cardReader.UpdateFromInput(100, 0);
            }

            private void CreateMines()
            {
                foreach (string positionString in SputnikDebrisConfig.Mines)
                {
                    Vector3 globalPosition = PositionDefiner.GetGlobalPosition(gameObject.transform, positionString.ToVector3());
                    Vector3 groundPosition = PositionDefiner.GetGroundPositionInPoint(globalPosition);

                    if (groundPosition == Vector3.zero)
                        continue;

                    Landmine landmine = BuildManager.SpawnStaticEntity("assets/prefabs/deployable/landmine/landmine.prefab", groundPosition, Quaternion.identity) as Landmine;
                    _mines.Add(landmine);
                }
            }

            private void CreateHeli()
            {
                if (SputnikDebrisConfig.HeliPresetName == "")
                    return;

                HeliConfig heliConfig = _ins._config.HeliConfigs.FirstOrDefault(x => x.PresetName == SputnikDebrisConfig.HeliPresetName);
                if (heliConfig == null)
                {
                    _ins.PrintError("Heli configuration not found!");
                    return;
                }

                _eventHeli = EventHeli.SpawnHeli(heliConfig, this);
            }

            private void CreateTurrets()
            {
                foreach (KeyValuePair<string, HashSet<LocationConfig>> turretPair in SputnikDebrisConfig.Turrets)
                {
                    TurretConfig turretConfig = _ins._config.TurretConfigs.FirstOrDefault(x => x.PresetName == turretPair.Key);
                    if (turretConfig == null)
                    {
                        _ins.PrintError("Turret configuration not found!");
                        continue;
                    }

                    foreach (LocationConfig locationConfig in turretPair.Value)
                        CreateTurret(turretConfig, locationConfig);
                }
            }

            private void CreateTurret(TurretConfig turretConfig, LocationConfig locationConfig)
            {
                Vector3 globalPosition = PositionDefiner.GetGlobalPosition(gameObject.transform, locationConfig.Position.ToVector3());
                Vector3 spawnPosition = turretConfig.AutoHeight ? PositionDefiner.GetGroundPositionInPoint(globalPosition) : globalPosition;
                Vector3 localRotation = locationConfig.Rotation.ToVector3();
                Quaternion globalRotation = PositionDefiner.GetGlobalRotation(gameObject.transform, localRotation);
                if (spawnPosition == Vector3.zero)
                    return;

                AutoTurret autoTurret = BuildManager.SpawnStaticEntity("assets/prefabs/npc/autoturret/autoturret_deployed.prefab", spawnPosition, globalRotation) as AutoTurret;
                ContainerIOEntity containerIO = autoTurret.GetComponent<ContainerIOEntity>();
                containerIO.inventory.Insert(ItemManager.CreateByName(turretConfig.ShortNameWeapon));
                containerIO.inventory.Insert(ItemManager.CreateByName(turretConfig.ShortNameAmmo, turretConfig.CountAmmo));
                containerIO.SendNetworkUpdate();
                autoTurret.InitializeHealth(turretConfig.Hp, turretConfig.Hp);
                autoTurret.UpdateFromInput(10, 0);
                autoTurret.isLootable = false;
                autoTurret.dropFloats = false;
                autoTurret.dropsLoot = false;
                
                TurretOptimizer.Attach(autoTurret, turretConfig.TargetDetectionRange, this);
                _turrets.Add(autoTurret);
            }

            internal static void KillAllSputniks()
            {
                foreach (SputnikDebris sputnikClass in AllSputnikDebris)
                    if (sputnikClass != null)
                        sputnikClass.KillSputnik();

                AllSputnikDebris.Clear();
            }

            private void KillSputnik()
            {
                if (_sputnikCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_sputnikCoroutine);

                if (_zoneController != null)
                    _zoneController.DeleteZone();

                if (_eventMapMarker != null)
                    _eventMapMarker.Delete();

                if (_pveModeController != null)
                    _pveModeController.DeletePveModeZone();

                KillSputnikEntities();

                if (_fallingSputnikEffect != null)
                    _fallingSputnikEffect.KillFallingSputnik();

                IqDroneManager.KillDrones(transform.position);
                Destroy(this);
            }

            private void KillSputnikEntities()
            {
                if (_cardReader.IsExists())
                    _cardReader.Kill();

                if (_eventHeli != null)
                    _eventHeli.Kill();

                foreach (BaseEntity entity in _decorEntities)
                    if (entity.IsExists())
                        entity.Kill();

                foreach (ScientistNPC entity in _npcs)
                    if (entity.IsExists())
                        entity.Kill();

                foreach (BaseEntity entity in _crates)
                    if (entity.IsExists())
                        entity.Kill();

                foreach (Landmine landmine in _mines)
                    if (landmine.IsExists())
                        landmine.Kill();

                foreach (AutoTurret autoTurret in _turrets)
                {
                    if (!autoTurret.IsExists())
                        continue;
                    
                    AutoTurret.interferenceUpdateList.Remove(autoTurret);
                    autoTurret.Kill();
                }
            }
        }

        private class FallingSputnikEffect : FacepunchBehaviour
        {
            private SputnikDebris _sputnikDebris;
            private BaseEntity _mainFireballEntity;
            private Vector3 _fallDirection;

            internal static FallingSputnikEffect CreateFallingEffect(SputnikDebris sputnikDebris)
            {
                Vector3 startPosition = GetStartFallPosition(sputnikDebris.transform.position);
                BaseEntity fireBallEntity = BuildManager.SpawnDecorEntity("assets/bundled/prefabs/oilfireballsmall.prefab", startPosition, Quaternion.identity);
                FallingSputnikEffect fallingSputnikEffect = fireBallEntity.gameObject.AddComponent<FallingSputnikEffect>();
                fallingSputnikEffect.Init(fireBallEntity, sputnikDebris);
                return fallingSputnikEffect;
            }

            private static Vector3 GetStartFallPosition(Vector3 sputnikPosition)
            {
                int counter = 0;
                Vector3 startPosition = Vector3.zero;

                while (counter < 20)
                {
                    float angle = UnityEngine.Random.Range(0, 360);
                    float radius = UnityEngine.Random.Range(_ins._config.FallingConfig.MinFallOffset, _ins._config.FallingConfig.MaxFallOffset);
                    float radian = 2f * Mathf.PI * angle / 360;
                    float x = sputnikPosition.x + radius * Mathf.Cos(radian);
                    float z = sputnikPosition.z + radius * Mathf.Sin(radian);
                    float y = UnityEngine.Random.Range(_ins._config.FallingConfig.MinFallHeight, _ins._config.FallingConfig.MaxFallHeight);
                    startPosition = new Vector3(x, y, z);
                    Vector3 fallDirection = (sputnikPosition - startPosition).normalized;
                    counter++;

                    if (!Physics.Raycast(startPosition, fallDirection, Vector3.Distance(startPosition, sputnikPosition) - 5, 1 << 16 | 1 << 21))
                        break;
                }

                return startPosition;
            }

            private void Init(BaseEntity mainFireballEntity, SputnikDebris sputnikDebris)
            {
                _mainFireballEntity = mainFireballEntity;
                _sputnikDebris = sputnikDebris;

                _fallDirection = (sputnikDebris.transform.position - mainFireballEntity.transform.position).normalized;
                SpawnSubFireballs();
            }

            private void SpawnSubFireballs()
            {
                const float radius = 1.5f;

                for (int angle = 0; angle < 360; angle += 45)
                {
                    float radian = 2f * Mathf.PI * angle / 360;
                    float x = radius * Mathf.Cos(radian);
                    float y = 1;
                    float z = radius * Mathf.Sin(radian);
                    Vector3 localPosition = new Vector3(x, y, z);
                    BuildManager.SpawnChildEntity(_mainFireballEntity, "assets/bundled/prefabs/oilfireballsmall.prefab", localPosition, Vector3.zero);
                }
            }

            private void FixedUpdate()
            {
                _mainFireballEntity.transform.position = _mainFireballEntity.transform.position += _fallDirection * _ins._config.FallingConfig.FallingSpeedScale;

                if (_mainFireballEntity.transform.position.y - _sputnikDebris.transform.position.y < 1)
                {
                    _sputnikDebris.OnSputnikFell();
                    KillFallingSputnik();
                }
            }

            private void OnDestroy()
            {
                if (EventLauncher.IsEventActive())
                    _sputnikDebris.OnSputnikFell();
            }

            internal void KillFallingSputnik()
            {
                _mainFireballEntity.Kill();
            }
        }

        private class EventMapMarker : FacepunchBehaviour
        {
            private SputnikDebris _sputnikDebris;
            private PveModeManager _pveModeController;
            private MapMarkerGenericRadius _radiusMarker;
            private VendingMachineMapMarker _vendingMarker;
            private Coroutine _updateCounter;

            internal static EventMapMarker CreateMarker(SputnikDebris sputnikDebris, PveModeManager pveModeController)
            {
                if (!sputnikDebris.SputnikDebrisConfig.MarkerConfig.Enable)
                    return null;

                GameObject gameObject = new GameObject();
                gameObject.layer = (int)Layer.Reserved1;
                EventMapMarker eventMapMarker = gameObject.AddComponent<EventMapMarker>();
                eventMapMarker.Init(sputnikDebris, pveModeController);
                return eventMapMarker;
            }

            private void Init(SputnikDebris sputnikDebris, PveModeManager pveModeController)
            {
                _sputnikDebris = sputnikDebris;
                _pveModeController = pveModeController;

                Vector3 eventPosition = sputnikDebris.transform.position;
                CreateRadiusMarker(eventPosition);
                CreateVendingMarker(eventPosition);
                _updateCounter = ServerMgr.Instance.StartCoroutine(MarkerUpdateCounter());
            }

            private void CreateRadiusMarker(Vector3 position)
            {
                if (!_sputnikDebris.SputnikDebrisConfig.MarkerConfig.IsRingMarker)
                    return;

                _radiusMarker = GameManager.server.CreateEntity("assets/prefabs/tools/map/genericradiusmarker.prefab", position) as MapMarkerGenericRadius;
                _radiusMarker.enableSaving = false;
                _radiusMarker.Spawn();
                _radiusMarker.radius = _sputnikDebris.SputnikDebrisConfig.MarkerConfig.Radius;
                _radiusMarker.alpha = _sputnikDebris.SputnikDebrisConfig.MarkerConfig.Alpha;
                _radiusMarker.color1 = new Color(_sputnikDebris.SputnikDebrisConfig.MarkerConfig.Color1.R, _sputnikDebris.SputnikDebrisConfig.MarkerConfig.Color1.G, _sputnikDebris.SputnikDebrisConfig.MarkerConfig.Color1.B);
                _radiusMarker.color2 = new Color(_sputnikDebris.SputnikDebrisConfig.MarkerConfig.Color2.R, _sputnikDebris.SputnikDebrisConfig.MarkerConfig.Color2.G, _sputnikDebris.SputnikDebrisConfig.MarkerConfig.Color2.B);
            }

            private void CreateVendingMarker(Vector3 position)
            {
                if (!_sputnikDebris.SputnikDebrisConfig.MarkerConfig.IsShopMarker)
                    return;

                _vendingMarker = GameManager.server.CreateEntity("assets/prefabs/deployable/vendingmachine/vending_mapmarker.prefab", position) as VendingMachineMapMarker;
                _vendingMarker.Spawn();
                _vendingMarker.markerShopName = $"{_sputnikDebris.SputnikDebrisConfig.MarkerConfig.DisplayName} ({NotifyManager.GetTimeMessage(null, _ins._eventController.GetEventTime())})";
            }

            private IEnumerator MarkerUpdateCounter()
            {
                while (EventLauncher.IsEventActive())
                {
                    Vector3 position = _sputnikDebris.transform.position;
                    UpdateVendingMarker(position);
                    UpdateRadiusMarker(position);
                    yield return CoroutineEx.waitForSeconds(5f);
                }
            }

            private void UpdateRadiusMarker(Vector3 position)
            {
                if (!_radiusMarker.IsExists())
                    return;

                _radiusMarker.transform.position = position;
                _radiusMarker.SendUpdate();
                _radiusMarker.SendNetworkUpdate();
            }

            private void UpdateVendingMarker(Vector3 position)
            {
                if (!_vendingMarker.IsExists())
                    return;

                _vendingMarker.transform.position = position;
                BasePlayer pveModeEventOwner = _pveModeController != null ? _pveModeController.Owner : null;
                string displayEventOwnerName = _ins._config.SupportedPluginsConfig.PveMode.ShowEventOwnerNameOnMap && pveModeEventOwner != null ? GetMessage("Marker_EventOwner", null, pveModeEventOwner.displayName) : "";
                _vendingMarker.markerShopName = $"{_sputnikDebris.SputnikDebrisConfig.MarkerConfig.DisplayName} ({NotifyManager.GetTimeMessage(null, _ins._eventController.GetEventTime())}) {displayEventOwnerName}";
                _vendingMarker.SetFlag(BaseEntity.Flags.Busy, pveModeEventOwner == null);
                _vendingMarker.SendNetworkUpdate();
            }

            internal void Delete()
            {
                if (_radiusMarker.IsExists())
                    _radiusMarker.Kill();

                if (_vendingMarker.IsExists())
                    _vendingMarker.Kill();

                if (_updateCounter != null)
                    ServerMgr.Instance.StopCoroutine(_updateCounter);

                Destroy(gameObject);
            }
        }

        private class ZoneController : FacepunchBehaviour
        {
            private static readonly HashSet<ZoneController> ZoneControllers = new HashSet<ZoneController>();

            private SputnikDebris _sputnikDebris;
            private bool _isPvpZone;
            private SphereCollider _sphereCollider;
            private Coroutine _zoneUpdateCoroutine;
            private TriggerRadiation _radiation;
            private readonly HashSet<BaseEntity> _spheres = new HashSet<BaseEntity>();
            private readonly HashSet<BasePlayer> _playersInZone = new HashSet<BasePlayer>();

            internal static ZoneController GetZoneControllerByPlayerUserID(ulong userID)
            {
                return ZoneControllers.FirstOrDefault(x => x != null && x._playersInZone.Any(y => y != null && y.userID == userID));
            }

            internal bool IsPvpZone()
            {
                return _isPvpZone;
            }

            internal void OnPlayerLeaveZone(BasePlayer player)
            {
                _playersInZone.Remove(player);
                GuiManager.DestroyGui(player);
                Interface.CallHook($"OnPlayerExit{_ins.Name}", player);

                if (_sputnikDebris.SputnikDebrisConfig.ZoneConfig.IsPvpZone)
                {
                    if (_ins.plugins.Exists("DynamicPVP") && (bool)_ins.DynamicPVP.Call("IsPlayerInPVPDelay", (ulong)player.userID))
                        return;

                    NotifyManager.SendMessageToPlayer(player, "ExitPVP", _ins._config.Prefix);
                }
                
                if (_radiation != null)
                    player.LeaveTrigger(_radiation);
            }

            internal static ZoneController CreateZone(SputnikDebris sputnikDebris, PveModeManager pveModeManager, bool isPvpZone)
            {
                GameObject gameObject = new GameObject();
                gameObject.transform.position = sputnikDebris.transform.position;
                gameObject.layer = (int)Layer.Reserved1;
                ZoneController zoneController = gameObject.AddComponent<ZoneController>();
                zoneController.Init(sputnikDebris, isPvpZone);
                ZoneControllers.Add(zoneController);
                return zoneController;
            }

            private void Init(SputnikDebris sputnikDebris, bool isPvpZone)
            {
                _sputnikDebris = sputnikDebris;
                _isPvpZone = isPvpZone;

                CreateTriggerSphere();
                CreateSpheres();
                
                if (sputnikDebris.SputnikDebrisConfig.ZoneConfig.Radiation > 0)
                {
                    _radiation = _sphereCollider.gameObject.AddComponent<TriggerRadiation>();
                    _radiation.RadiationAmountOverride = sputnikDebris.SputnikDebrisConfig.ZoneConfig.Radiation;
                    _radiation.InterestLayers = 131072;
                    _radiation.enabled = true;
                }

                _zoneUpdateCoroutine = ServerMgr.Instance.StartCoroutine(ZoneUpdateCoroutine());
            }

            private void CreateTriggerSphere()
            {
                _sphereCollider = gameObject.AddComponent<SphereCollider>();
                _sphereCollider.isTrigger = true;
                _sphereCollider.radius = _sputnikDebris.SputnikDebrisConfig.ZoneConfig.Radius;
            }

            private void CreateSpheres()
            {
                if (_sputnikDebris.SputnikDebrisConfig.ZoneConfig.IsDome)
                    for (int i = 0; i < _sputnikDebris.SputnikDebrisConfig.ZoneConfig.Darkening; i++)
                        CreateSphere("assets/prefabs/visualization/sphere.prefab");

                if (_sputnikDebris.SputnikDebrisConfig.ZoneConfig.IsColoredBorder)
                {
                    string spherePrefab = _sputnikDebris.SputnikDebrisConfig.ZoneConfig.BorderColor == 0 ? "assets/bundled/prefabs/modding/events/twitch/br_sphere.prefab" : _sputnikDebris.SputnikDebrisConfig.ZoneConfig.BorderColor == 1 ? "assets/bundled/prefabs/modding/events/twitch/br_sphere_green.prefab" :
                         _sputnikDebris.SputnikDebrisConfig.ZoneConfig.BorderColor == 2 ? "assets/bundled/prefabs/modding/events/twitch/br_sphere_purple.prefab" : "assets/bundled/prefabs/modding/events/twitch/br_sphere_red.prefab";

                    for (int i = 0; i < _sputnikDebris.SputnikDebrisConfig.ZoneConfig.Brightness; i++)
                        CreateSphere(spherePrefab);
                }
            }

            private void CreateSphere(string prefabName)
            {
                BaseEntity sphere = GameManager.server.CreateEntity(prefabName, gameObject.transform.position);
                SphereEntity entity = sphere.GetComponent<SphereEntity>();
                entity.currentRadius = _sputnikDebris.SputnikDebrisConfig.ZoneConfig.Radius * 2;
                entity.lerpSpeed = 0f;
                sphere.enableSaving = false;
                sphere.Spawn();
                _spheres.Add(sphere);
            }

            private void OnTriggerEnter(Collider other)
            {
                BasePlayer player = other.GetComponentInParent<BasePlayer>();
                if (!player.IsRealPlayer()) 
                    return;
                
                _playersInZone.Add(player);

                Interface.CallHook($"OnPlayerEnter{_ins.Name}", player);

                if (_sputnikDebris.SputnikDebrisConfig.ZoneConfig.IsPvpZone)
                    NotifyManager.SendMessageToPlayer(player, "EnterPVP", _ins._config.Prefix);

                if (_ins._config.GUIConfig.IsEnable)
                    GuiManager.CreateGui(player, NotifyManager.GetTimeMessage(player.UserIDString, _sputnikDebris.GetEventTime()), _sputnikDebris.GetCountOfUnlootedCrates().ToString(), _sputnikDebris.GetCountOfAliveNpc().ToString());
            }

            private void OnTriggerExit(Collider other)
            {
                BasePlayer player = other.GetComponentInParent<BasePlayer>();

                if (player.IsRealPlayer())
                    OnPlayerLeaveZone(player);
            }

            private IEnumerator ZoneUpdateCoroutine()
            {
                while (_sputnikDebris != null)
                {
                    int countOfCrates = _sputnikDebris.GetCountOfUnlootedCrates();
                    int countOfGuardNpc = _sputnikDebris.GetCountOfAliveNpc();

                    if (_ins._config.GUIConfig.IsEnable)
                        foreach (BasePlayer player in _playersInZone)
                            if (player != null)
                                GuiManager.CreateGui(player, NotifyManager.GetTimeMessage(player.UserIDString, _sputnikDebris.GetEventTime()), countOfCrates.ToString(), countOfGuardNpc.ToString());

                    yield return CoroutineEx.waitForSeconds(1f);
                }
            }

            internal static void ClearData()
            {
                foreach (ZoneController zoneController in ZoneControllers)
                    if (zoneController != null)
                        zoneController.DeleteZone();

                ZoneControllers.Clear();
            }

            internal void DeleteZone()
            {
                if (_zoneUpdateCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_zoneUpdateCoroutine);

                foreach (BaseEntity sphere in _spheres)
                    if (sphere.IsExists())
                        sphere.Kill();

                foreach (BasePlayer player in _playersInZone)
                    if (player != null)
                        GuiManager.DestroyGui(player);

                Destroy(gameObject);
            }
        }

        private class PveModeManager
        {
            private static readonly HashSet<PveModeManager> PveModeControllers = new HashSet<PveModeManager>();
            private static readonly HashSet<ulong> Owners = new HashSet<ulong>();
            private SputnikDebris _sputnikDebris;
            internal BasePlayer Owner;
            private string _zoneName;

            internal static bool IsPveModeReady()
            {
                return _ins._config.SupportedPluginsConfig.PveMode.Enable && _ins.plugins.Exists("PveMode");
            }

            internal static PveModeManager GetPveControllerByZoneName(string zoneName)
            {
                return PveModeControllers.FirstOrDefault(x => x != null && x._zoneName == zoneName);
            }

            internal static PveModeManager CreatePveModeController(SputnikDebris sputnikDebris, HashSet<ulong> npcs, HashSet<ulong> crates, HashSet<ulong> turrets, ulong heliNetId)
            {
                PveModeManager pveModeController = new PveModeManager();
                pveModeController.Init(sputnikDebris, npcs, crates, turrets, heliNetId);
                PveModeControllers.Add(pveModeController);
                return pveModeController;
            }

            private void Init(SputnikDebris sputnikDebris, HashSet<ulong> npcs, HashSet<ulong> crates, HashSet<ulong> turrets, ulong heliNetId)
            {
                _sputnikDebris = sputnikDebris;
                _zoneName = _ins.Name + "_" + sputnikDebris.transform.position;

                Dictionary<string, object> config = GetPveModeConfig();
                HashSet<ulong> bradleys = new HashSet<ulong>();
                HashSet<ulong> helicopters = heliNetId != 0 ? new HashSet<ulong> { heliNetId } : new HashSet<ulong>();

                _ins.PveMode.Call("EventAddPveMode", _zoneName, config, sputnikDebris.transform.position, sputnikDebris.SputnikDebrisConfig.ZoneConfig.Radius, crates, npcs, bradleys, helicopters, turrets, new HashSet<ulong>(), null);
            }

            private Dictionary<string, object> GetPveModeConfig()
            {
                return new Dictionary<string, object>
                {
                    ["Damage"] = _ins._config.SupportedPluginsConfig.PveMode.Damage,
                    ["ScaleDamage"] = _ins._config.SupportedPluginsConfig.PveMode.ScaleDamage,
                    ["LootCrate"] = _ins._config.SupportedPluginsConfig.PveMode.LootCrate,
                    ["HackCrate"] = _ins._config.SupportedPluginsConfig.PveMode.HackCrate,
                    ["LootNpc"] = _ins._config.SupportedPluginsConfig.PveMode.LootNpc,
                    ["DamageNpc"] = _ins._config.SupportedPluginsConfig.PveMode.DamageNpc,
                    ["DamageTank"] = false,
                    ["DamageHelicopter"] = _ins._config.SupportedPluginsConfig.PveMode.DamageHeli,
                    ["DamageTurret"] = _ins._config.SupportedPluginsConfig.PveMode.DamageTurret,
                    ["TargetNpc"] = _ins._config.SupportedPluginsConfig.PveMode.TargetNpc,
                    ["TargetTank"] = false,
                    ["TargetHelicopter"] = _ins._config.SupportedPluginsConfig.PveMode.TargetHeli,
                    ["TargetTurret"] = _ins._config.SupportedPluginsConfig.PveMode.TargetTurret,
                    ["CanEnter"] = _ins._config.SupportedPluginsConfig.PveMode.CanEnter,
                    ["CanEnterCooldownPlayer"] = _ins._config.SupportedPluginsConfig.PveMode.CanEnterCooldownPlayer,
                    ["TimeExitOwner"] = _ins._config.SupportedPluginsConfig.PveMode.TimeExitOwner,
                    ["AlertTime"] = _ins._config.SupportedPluginsConfig.PveMode.AlertTime,
                    ["RestoreUponDeath"] = false,
                    ["CooldownOwner"] = _ins._config.SupportedPluginsConfig.PveMode.CooldownOwner,
                    ["Darkening"] = _sputnikDebris.SputnikDebrisConfig.ZoneConfig.IsDome ? _sputnikDebris.SputnikDebrisConfig.ZoneConfig.Darkening : 0
                };
            }

            internal void OnNewOwnerSet(BasePlayer player)
            {
                Owner = player;
            }

            internal void OnOwnerDeleted()
            {
                Owner = null;
            }

            internal static bool IsPveModeBlockAction(BasePlayer player)
            {
                if (IsPveModeReady())
                    return _ins.PveMode.Call("CanActionEvent", _ins.Name, player) != null;

                return false;
            }

            internal void DeletePveModeZone()
            {
                if (!IsPveModeReady())
                    return;

                HashSet<ulong> newOwners = (HashSet<ulong>)_ins.PveMode.Call("GetEventOwners", _zoneName);

                if (newOwners != null)
                    foreach (ulong ownerId in newOwners)
                        Owners.Add(ownerId);

                _ins.PveMode.Call("EventRemovePveMode", _zoneName, false);
            }

            internal static void SendCooldownAndClearData()
            {
                if (Owners.Count > 0)
                    _ins.PveMode.Call("EventAddCooldown", _ins.Name, Owners, _ins._config.SupportedPluginsConfig.PveMode.CooldownOwner);

                Owners.Clear();
                PveModeControllers.Clear();
            }
        }

        private class EventHeli : FacepunchBehaviour
        {
            private static readonly HashSet<EventHeli> EventHelies = new HashSet<EventHeli>();

            internal SputnikDebris SputnikDebris;
            internal HeliConfig HeliConfig;
            public PatrolHelicopter _patrolHelicopter;
            private Vector3 _patrolPosition;
            private int _outsideTime;
            internal ulong LastAttackedPlayer;

            internal static EventHeli SpawnHeli(HeliConfig heliConfig, SputnikDebris sputnikDebris)
            {
                Vector3 position = sputnikDebris.GetEventPosition() + Vector3.up * heliConfig.Height;

                PatrolHelicopter patrolHelicopter = BuildManager.SpawnRegularEntity("assets/prefabs/npc/patrol helicopter/patrolhelicopter.prefab", position, Quaternion.identity) as PatrolHelicopter;
                patrolHelicopter.transform.position = position;
                EventHeli eventHeli = patrolHelicopter.gameObject.AddComponent<EventHeli>();
                eventHeli.Init(heliConfig, patrolHelicopter, sputnikDebris);
                EventHelies.Add(eventHeli);
                
                EntityPresetInfo.Attach(patrolHelicopter, heliConfig.PresetName);
                return eventHeli;
            }

            internal static EventHeli GetEventHeliByNetId(ulong netId)
            {
                return EventHelies.FirstOrDefault(x => x != null && x._patrolHelicopter != null && x._patrolHelicopter.net != null && x._patrolHelicopter.net.ID.Value == netId);
            }

            internal static EventHeli GetClosestHeli(Vector3 position)
            {
                HashSet<EventHeli> aliveHelies = EventHelies.Where(x => x != null);

                if (aliveHelies == null || aliveHelies.Count == 0)
                    return null;

                return aliveHelies.Min(x => Vector3.Distance(position, x.transform.position));
            }

            private void Init(HeliConfig heliConfig, PatrolHelicopter patrolHelicopter, SputnikDebris sputnikDebris)
            {
                HeliConfig = heliConfig;
                _patrolHelicopter = patrolHelicopter;
                SputnikDebris = sputnikDebris;
                UpdateHelicopter();
                StartPatrol();
                patrolHelicopter.InvokeRepeating(UpdatePosition, 1, 1);
            }

            private void UpdateHelicopter()
            {
                _patrolHelicopter.startHealth = HeliConfig.Hp;
                _patrolHelicopter.InitializeHealth(HeliConfig.Hp, HeliConfig.Hp);
                _patrolHelicopter.maxCratesToSpawn = HeliConfig.CratesAmount;
                _patrolHelicopter.bulletDamage = HeliConfig.BulletDamage;
                _patrolHelicopter.bulletSpeed = HeliConfig.BulletSpeed;

                PatrolHelicopter.weakspot[] weakspots = _patrolHelicopter.weakspots;
                if (weakspots == null || weakspots.Length <= 1) 
                    return;
                
                weakspots[0].maxHealth = HeliConfig.MainRotorHealth;
                weakspots[0].health = HeliConfig.MainRotorHealth;
                weakspots[1].maxHealth = HeliConfig.RearRotorHealth;
                weakspots[1].health = HeliConfig.RearRotorHealth;
            }

            private void UpdatePosition()
            {
                _patrolHelicopter.myAI.spawnTime = Time.realtimeSinceStartup;

                if (_patrolHelicopter.myAI._currentState is PatrolHelicopterAI.aiState.DEATH or PatrolHelicopterAI.aiState.STRAFE)
                    return;

                DoPatrol();
            }

            private void DoPatrol()
            {
                if (_patrolHelicopter.myAI.leftGun.HasTarget() || _patrolHelicopter.myAI.rightGun.HasTarget())
                {
                    if (Vector3.Distance(_patrolPosition, _patrolHelicopter.transform.position) > HeliConfig.Distance)
                    {
                        _outsideTime++;

                        if (_outsideTime > HeliConfig.OutsideTime)
                            _patrolHelicopter.myAI.State_Move_Enter(_patrolPosition);
                    }
                    else
                    {
                        _outsideTime = 0;
                    }
                }
                else if (Vector3.Distance(_patrolPosition, _patrolHelicopter.transform.position) > HeliConfig.Distance)
                {
                    _patrolHelicopter.myAI.State_Move_Enter(_patrolPosition);
                    _outsideTime = 0;
                }
                else
                    _outsideTime = 0;
            }

            private void StartPatrol()
            {
                _outsideTime = 0;
                _patrolPosition = SputnikDebris.GetEventPosition() + Vector3.up * HeliConfig.Height;
            }

            internal ulong GetHeliNetId()
            {
                return _patrolHelicopter.net.ID.Value;
            }

            internal bool IsHeliCanTarget()
            {
                return SputnikDebris.IsAggressive();
            }

            internal void OnHeliAttacked(ulong userId)
            {
                if (!_patrolHelicopter.myAI.isDead)
                    LastAttackedPlayer = userId;
            }

            internal void Kill()
            {
                if (_patrolHelicopter.IsExists())
                    _patrolHelicopter.Kill();
            }

            internal static void ClearData()
            {
                EventHelies.Clear();
            }
        }
        
        private class TurretOptimizer : FacepunchBehaviour
        {
            private AutoTurret _autoTurret;
            private float _targetRadius;
            private SputnikDebris _sputnikDebris;
            
            public static void Attach(AutoTurret autoTurret, float targetRadius, SputnikDebris sputnikDebris)
            {
                TurretOptimizer turretOptimizer = autoTurret.gameObject.AddComponent<TurretOptimizer>();
                turretOptimizer.Init(autoTurret, targetRadius, sputnikDebris);
            }

            private void Init(AutoTurret autoTurret, float targetRadius, SputnikDebris sputnikDebris)
            {
                _autoTurret = autoTurret;
                _targetRadius = targetRadius;
                _sputnikDebris = sputnikDebris;
                
                SphereCollider sphereCollider = autoTurret.targetTrigger.GetComponent<SphereCollider>();
                sphereCollider.enabled = false;
                autoTurret.InvokeRepeating(ScanTargets, 3f, 1f);
            }
            
            private void ScanTargets()
            {
                if (_autoTurret.target != null && _autoTurret.target is not BasePlayer)
                    _autoTurret.SetTarget(null);
                
                if (_autoTurret.targetTrigger.entityContents == null)
                    _autoTurret.targetTrigger.entityContents = new HashSet<BaseEntity>();
                else
                    _autoTurret.targetTrigger.entityContents.Clear();

                if (!_sputnikDebris.IsAggressive())
                    return;

                int count = BaseEntity.Query.Server.GetPlayersInSphereFast(transform.position, _targetRadius, AIBrainSenses.playerQueryResults, IsPlayerCanBeTargeted);

                if (count == 0)
                    return;

                _autoTurret.authDirty = true;

                for (int i = 0; i < count; i++)
                {
                    BasePlayer player = AIBrainSenses.playerQueryResults[i];

                    if (Interface.CallHook("OnEntityEnter", _autoTurret.targetTrigger, player) != null)
                        continue;

                    if (player.IsSleeping() || (player.InSafeZone() && !player.IsHostile()))
                        continue;

                    _autoTurret.targetTrigger.entityContents.Add(player);
                }
            }
            
            private bool IsPlayerCanBeTargeted(BasePlayer player)
            {
                if (!player.IsRealPlayer())
                    return false;

                if (player.IsDead() || player.IsSleeping() || player.IsWounded())
                    return false;

                if (player.InSafeZone() || player._limitedNetworking)
                    return false;

                return true;
            }
        }

        private static class GuiManager
        {
            private static bool _isLoadingImageFailed;
            private const float TabWidth = 109;
            private const float TabHeight = 25;
            private static readonly ImageInfo TabImageInfo = new ImageInfo("Tab_Adem");

            private static readonly List<ImageInfo> IconImageInfos = new List<ImageInfo>
            {
                new ImageInfo("Clock_Adem"),
                new ImageInfo("Crates_Adem"),
                new ImageInfo("Astronauts_Adem"),
            };

            internal static void LoadImages()
            {
                ServerMgr.Instance.StartCoroutine(LoadImagesCoroutine());
            }

            private static IEnumerator LoadImagesCoroutine()
            {
                yield return LoadTabCoroutine();

                if (!_isLoadingImageFailed)
                    yield return LoadIconsCoroutine();
            }

            private static IEnumerator LoadTabCoroutine()
            {
                string url = "file://" + Interface.Oxide.DataDirectory + Path.DirectorySeparatorChar + "Images/" + TabImageInfo.ImageName + ".png";
                using UnityWebRequest unityWebRequest = UnityWebRequestTexture.GetTexture(url);
                yield return unityWebRequest.SendWebRequest();
                
                if (unityWebRequest.result != UnityWebRequest.Result.Success)
                {
                    OnImageSaveFailed();
                    _isLoadingImageFailed = true;
                }
                
                Texture2D texture = DownloadHandlerTexture.GetContent(unityWebRequest);
                uint imageId = FileStorage.server.Store(texture.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID);
                TabImageInfo.ImageId = imageId.ToString();
                UnityEngine.Object.DestroyImmediate(texture);
            }

            private static IEnumerator LoadIconsCoroutine()
            {
                foreach (ImageInfo imageInfo in IconImageInfos)
                {
                    string url = "file://" + Interface.Oxide.DataDirectory + Path.DirectorySeparatorChar + "Images/" + imageInfo.ImageName + ".png";
                    using UnityWebRequest unityWebRequest = UnityWebRequestTexture.GetTexture(url);
                    yield return unityWebRequest.SendWebRequest();
                    
                    if (unityWebRequest.result != UnityWebRequest.Result.Success)
                    {
                        OnImageSaveFailed();
                        break;
                    }
                    
                    Texture2D texture = DownloadHandlerTexture.GetContent(unityWebRequest);
                    uint imageId = FileStorage.server.Store(texture.EncodeToPNG(), FileStorage.Type.png, CommunityEntity.ServerInstance.net.ID);
                    imageInfo.ImageId = imageId.ToString();
                    UnityEngine.Object.DestroyImmediate(texture);
                }
            }

            private static void OnImageSaveFailed()
            {
                NotifyManager.PrintError(null, "Move the contents of the data folder from the archive you downloaded from the website into the oxide/data folder on your server!");
                Interface.Oxide.UnloadPlugin(_ins.Name);
            }

            internal static void CreateGui(BasePlayer player, params string[] args)
            {
                CuiHelper.DestroyUi(player, "Tabs_Adem");
                CuiElementContainer container = new CuiElementContainer();
                float halfWidth = TabWidth / 2 + TabWidth / 2 * (IconImageInfos.Count - 1);

                container.Add(new CuiPanel
                {
                    Image = { Color = "0 0 0 0" },
                    RectTransform = { AnchorMin = "0.5 1", AnchorMax = "0.5 1", OffsetMin = $"{-halfWidth} {_ins._config.GUIConfig.OffsetMinY}", OffsetMax = $"{halfWidth} {_ins._config.GUIConfig.OffsetMinY + TabHeight}" },
                    CursorEnabled = false,
                }, "Under", "Tabs_Adem");

                float xMin = 0;

                for (int i = 0; i < args.Length; i++)
                {
                    string arg = args[i];
                    DrawTab(ref container, i, arg, xMin);
                    xMin += TabWidth;
                }

                CuiHelper.AddUi(player, container);
            }

            private static void DrawTab(ref CuiElementContainer container, int index, string text, float xMin)
            {
                ImageInfo imageInfo = IconImageInfos[index];

                container.Add(new CuiElement
                {
                    Name = $"Tab_{index}_Adem",
                    Parent = "Tabs_Adem",
                    Components =
                    {
                        new CuiRawImageComponent { Png = TabImageInfo.ImageId },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = $"{xMin} 0", OffsetMax = $"{xMin + TabWidth} {TabHeight}" }
                    }
                });
                container.Add(new CuiElement
                {
                    Parent = $"Tab_{index}_Adem",
                    Components =
                    {
                        new CuiRawImageComponent { Png = imageInfo.ImageId },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = "9 5", OffsetMax = "23 19" }
                    }
                });
                container.Add(new CuiElement
                {
                    Parent = $"Tab_{index}_Adem",
                    Components =
                    {
                        new CuiTextComponent() { Color = "1 1 1 1", Text = text, Align = TextAnchor.MiddleCenter, FontSize = 10, Font = "robotocondensed-bold.ttf" },
                        new CuiRectTransformComponent { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = "23 5", OffsetMax = $"{TabWidth - 9} 19" }
                    }
                });
            }

            internal static void DestroyAllGui()
            {
                foreach (BasePlayer player in BasePlayer.activePlayerList)
                    if (player != null)
                        DestroyGui(player);
            }

            internal static void DestroyGui(BasePlayer player)
            {
                CuiHelper.DestroyUi(player, "Tabs_Adem");
            }

            private class ImageInfo
            {
                public readonly string ImageName;
                public string ImageId;

                internal ImageInfo(string imageName)
                {
                    ImageName = imageName;
                }
            }
        }

        private static class BuildManager
        {

            public static BaseEntity SpawnRegularEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId = 0, bool enableSaving = false)
            {
                BaseEntity entity = CreateEntity(prefabName, position, rotation, skinId, enableSaving);
                entity.Spawn();
                return entity;
            }

            public static BaseEntity SpawnStaticEntity(string prefabName, Vector3 localPosition, Vector3 localRotation, Transform parentTransform, ulong skinId = 0)
            {
                Vector3 globalPosition = PositionDefiner.GetGlobalPosition(parentTransform, localPosition);
                Quaternion globalRotation = PositionDefiner.GetGlobalRotation(parentTransform, localRotation);
                return SpawnStaticEntity(prefabName, globalPosition, globalRotation, skinId);
            }

            public static BaseEntity SpawnStaticEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId = 0)
            {
                BaseEntity entity = CreateEntity(prefabName, position, rotation, skinId, false);
                DestroyUnnecessaryComponents(entity);

                StabilityEntity stabilityEntity = entity as StabilityEntity;
                if (stabilityEntity != null)
                    stabilityEntity.grounded = true;

                BaseCombatEntity baseCombatEntity = entity as BaseCombatEntity;
                if (baseCombatEntity != null)
                    baseCombatEntity.pickup.enabled = false;

                entity.Spawn();
                return entity;
            }

            public static BaseEntity SpawnDecorEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId = 0)
            {
                BaseEntity entity = CreateDecorEntity(prefabName, position, rotation, skinId);
                DestroyUnnecessaryComponents(entity);
                DestroyDecorComponents(entity);
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

            public static BaseEntity SpawnDecorCargo(Vector3 position, Quaternion rotation)
            {
                CargoShip cargoShip = CreateEntity("assets/content/vehicles/boats/cargoship/cargoshiptest.prefab", position, rotation, 0, false) as CargoShip;
                cargoShip.layouts[0].SetActive(true);
                cargoShip.SendNetworkUpdate();
                cargoShip.scientistSpawnPoints = Array.Empty<Transform>();
                DestroyDecorComponents(cargoShip);
                BaseEntity customCargoShip = cargoShip.gameObject.AddComponent<BaseEntity>();
                CopySerializableFields(cargoShip, customCargoShip);
                UnityEngine.Object.DestroyImmediate(cargoShip, true);

                customCargoShip.Spawn();
                SpawnChildEntity(customCargoShip, "assets/bundled/prefabs/static/door.hinged.cargo_ship_side.prefab", new Vector3(11.90f, 3.50f, 2.25f), new Vector3(0.00f, 180.00f, 0.00f));
                SpawnChildEntity(customCargoShip, "assets/bundled/prefabs/static/door.hinged.cargo_ship_side.prefab", new Vector3(11.90f, 3.50f, 18.75f), new Vector3(0.00f, 180.00f, 0.00f));
                SpawnChildEntity(customCargoShip, "assets/bundled/prefabs/static/door.hinged.cargo_ship_side.prefab", new Vector3(-11.90f, 3.50f, 18.75f), new Vector3(0.00f, 0.00f, 0.00f));
                SpawnChildEntity(customCargoShip, "assets/bundled/prefabs/static/door.hinged.cargo_ship_side.prefab", new Vector3(-11.90f, 3.50f, 2.25f), new Vector3(0.00f, 0.00f, 0.00f));
                return customCargoShip;
            }
            
            public static BaseEntity CreateEntity(string prefabName, Vector3 position, Quaternion rotation, ulong skinId, bool enableSaving)
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

            private static void SetParent(BaseEntity parentEntity, BaseEntity childEntity, Vector3 localPosition, Vector3 localRotation)
            {
                childEntity.transform.localPosition = localPosition;
                childEntity.transform.localEulerAngles = localRotation;
                childEntity.SetParent(parentEntity);
            }

            private static void DestroyDecorComponents(BaseEntity entity)
            {
                DestroyEntityComponents<TriggerParent>(entity);

                Component[] components = entity.GetComponentsInChildren<Component>();

                foreach (Component component in components)
                {
                    EntityCollisionMessage entityCollisionMessage = component as EntityCollisionMessage;

                    if (entityCollisionMessage || (component && component.name != entity.PrefabName))
                    {
                        Transform transform = component as Transform;
                        if (transform)
                            continue;

                        Collider collider = component as Collider;
                        if (collider && !collider.isTrigger && collider.gameObject.layer != 29)
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

                if (entity.ShortPrefabName is "catapult.entity" or "siegetower.entity")
                {
                    Rigidbody rigidbody = entity.GetComponent<Rigidbody>();
                    if (rigidbody != null)
                        rigidbody.isKinematic = true;

                    return;
                }


                if (entity is not HotAirBalloon && entity is not FreeableLootContainer)
                {
                    DestroyEntityComponent<Rigidbody>(entity);
                }
            }

            private static void DestroyEntityComponent<TYpeForDestroy>(BaseEntity entity) where TYpeForDestroy : UnityEngine.Object
            {
                TYpeForDestroy component = entity.GetComponent<TYpeForDestroy>();
                if (component)
                    UnityEngine.Object.DestroyImmediate(component);
            }

            private static void DestroyEntityComponents<TYpeForDestroy>(BaseEntity entity) where TYpeForDestroy : UnityEngine.Object
            {
                TYpeForDestroy[] components = entity.GetComponentsInChildren<TYpeForDestroy>();

                foreach (TYpeForDestroy component in components)
                {
                    if (component != null)
                        UnityEngine.Object.DestroyImmediate(component);
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

        private static class SpawnPositionFinder
        {
            private static readonly float CheckingEntitiesRadius = _ins._config.SputnikDebrisConfigs.Max(x => x.ZoneConfig.Radius).ZoneConfig.Radius;
            private static Coroutine _findCoroutine;
            private static readonly HashSet<Vector3> SpawnPositions = new HashSet<Vector3>();
            private static bool _isFrontierMap;

            internal static void InitialUpdate()
            {
                _isFrontierMap = IsFrontierMap();
                StartCachingSpawnPoints();
            }

            private static bool IsFrontierMap()
            {
                return BaseNetworkable.serverEntities.OfType<RANDSwitch>().Any(x => x != null && x.transform != null && Mathf.Approximately(x.transform.position.x + x.transform.position.y + x.transform.position.z, 557));
            }

            internal static void StartCachingSpawnPoints()
            {
                if (_findCoroutine != null)
                    return;

                UpdateCachedPoints();
                _findCoroutine = ServerMgr.Instance.StartCoroutine(FindSpawnPosition());
            }

            internal static void StopCachingSpawnPoints()
            {
                if (_findCoroutine != null)
                    ServerMgr.Instance.StopCoroutine(_findCoroutine);
            }

            internal static int GetCountSpawnPoints()
            {
                return SpawnPositions.Where(x => IsGroundPositionAvailable(x, false)).Count;
            }

            internal static void UpdateCachedPoints()
            {
                OtherPluginsChecker.CacheData();
                SpawnPositions.RemoveWhere(x => !IsGroundPositionAvailable(x, false));
            }

            internal static Vector3 GetSpawnPosition(SputnikDebrisConfig sputnikDebrisConfig, Vector3 lastSputnikPosition)
            {
                Vector3 position = Vector3.zero;

                if (sputnikDebrisConfig.UseCustomSpawnPoints && sputnikDebrisConfig.CustomSpawnPoints.Count > 0)
                {
                    string stringPosition = sputnikDebrisConfig.CustomSpawnPoints.GetRandom();

                    if (stringPosition == null)
                        _ins.PrintError($"Couldn't find a custom position (PresetName - {sputnikDebrisConfig.PresetName})");
                    else
                        position = stringPosition.ToVector3();
                }

                if (position != Vector3.zero) 
                    return position;
                
                if (_ins._config.SpawnConfig.IsNearestPoint)
                {
                    if (lastSputnikPosition == Vector3.zero)
                        position = SpawnPositions.FirstOrDefault(x => true);
                    else
                        position = SpawnPositions.Min(x => Vector3.Distance(lastSputnikPosition, x));
                }
                else
                {
                    position = SpawnPositions.FirstOrDefault(x => IsGroundPositionAvailable(x, false));
                }

                SpawnPositions.Remove(position);

                return position;
            }

            private static IEnumerator FindSpawnPosition()
            {
                int maxCyclesCount = _ins._config.SpawnConfig.CountSpawnPoints * 10000;
                int counter = 0;

                while (SpawnPositions.Count < _ins._config.SpawnConfig.CountSpawnPoints && counter < maxCyclesCount)
                {
                    TryFindNewSpawnPoint();
                    counter++;

                    if (counter % 100 == 0)
                        yield return null;
                }

                yield return null;
                _findCoroutine = null;
            }

            private static void TryFindNewSpawnPoint()
            {
                Vector3 skyPosition = GetRandomSkyPoint();

                if (!TopologyChecker.IsPointAvailableByTopology(skyPosition))
                    return;

                Vector3 groundPosition = GetGroundPosition(skyPosition);

                if (IsGroundPositionAvailable(groundPosition, true))
                    SpawnPositions.Add(groundPosition);
            }

            private static Vector3 GetRandomSkyPoint()
            {
                Vector2 random = World.Size * 0.475f * UnityEngine.Random.insideUnitCircle;
                return new Vector3(random.x, 500f, random.y);
            }

            private static Vector3 GetGroundPosition(Vector3 position)
            {
                position.y = 500f;

                if (Physics.Raycast(position, Vector3.down, out RaycastHit raycastHit, 550f))
                    return raycastHit.point;
                
                return Vector3.zero;
            }

            private static bool IsGroundPositionAvailable(Vector3 position, bool fullCheck)
            {
                if (fullCheck)
                {
                    if (position == Vector3.zero)
                        return false;

                    if (SpawnPositions.Any(x => Vector3.Distance(x, position) < _ins._config.SpawnConfig.MinPointDistance))
                        return false;

                    if (Math.Abs(TerrainMeta.HeightMap.GetHeight(position) - position.y) > 0.5f)
                        return false;

                    if (!IsFlatSurface(position, 5))
                        return false;
                }

                if (IsAnyEntityBlockSpawn(position))
                    return false;

                if (!OtherPluginsChecker.IsPointAvailable(position))
                    return false;

                if (_isFrontierMap && position.y > 50)
                    return false;

                return true;
            }

            private static bool IsFlatSurface(Vector3 position, float radius)
            {
                for (int angle = 0; angle < 360; angle += 45)
                {
                    float radian = 2f * Mathf.PI * angle / 360;
                    float x = position.x + radius * Mathf.Cos(radian);
                    float z = position.z + radius * Mathf.Sin(radian);
                    Vector3 positionInRadius = GetGroundPosition(new Vector3(x, position.y, z));

                    if (positionInRadius == Vector3.zero)
                        return false;

                    if (Math.Abs(position.y - positionInRadius.y) > 0.75f)
                        return false;
                }

                return true;
            }

            private static bool IsAnyEntityBlockSpawn(Vector3 position)
            {
                foreach (Collider collider in Physics.OverlapSphere(position, CheckingEntitiesRadius))
                {
                    if (collider.name.Contains("heatSource"))
                        continue;

                    if (collider.name.Contains("Safe") || collider.name.Contains("Trigger (8)"))
                        return true;

                    SphereCollider sphereCollider = collider as SphereCollider;

                    if (sphereCollider != null && sphereCollider.isTrigger)
                        return true;

                    BaseEntity entity = collider.ToBaseEntity();

                    if (entity == null)
                        continue;

                    if (entity.GetBuildingPrivilege() != null)
                        return true;

                    if (entity is BuildingBlock or SimpleBuildingBlock)
                        return true;
                }

                return false;
            }

            private static class TopologyChecker
            {
                private const int BlockedTopologies = (int)(TerrainTopology.Enum.Cliff | TerrainTopology.Enum.Cliffside | TerrainTopology.Enum.Ocean | TerrainTopology.Enum.Oceanside | TerrainTopology.Enum.Building | TerrainTopology.Enum.Road | TerrainTopology.Enum.Roadside | TerrainTopology.Enum.Rail | TerrainTopology.Enum.Railside);
                private const int MonumentTopologies = (int)(TerrainTopology.Enum.Monument | TerrainTopology.Enum.Building);
                private const int BeachTopologies = (int)(TerrainTopology.Enum.Beach | TerrainTopology.Enum.Beachside);
                private const int RiverTopologies = (int)(TerrainTopology.Enum.River | TerrainTopology.Enum.Riverside | TerrainTopology.Enum.Lake | TerrainTopology.Enum.Lakeside);

                internal static bool IsPointAvailableByTopology(Vector3 position)
                {
                    int pointTopologies = TerrainMeta.TopologyMap.GetTopology(position);

                    if ((pointTopologies & BlockedTopologies) != 0)
                        return false;

                    if (_ins._config.SpawnConfig.IsMonumentsDisabled && (pointTopologies & MonumentTopologies) != 0)
                        return false;

                    if (_ins._config.SpawnConfig.IsBeachDisabled && (pointTopologies & BeachTopologies) != 0)
                        return false;

                    if (_ins._config.SpawnConfig.IsRiverDisabled && (pointTopologies & RiverTopologies) != 0)
                        return false;

                    return true;
                }
            }

            private static class OtherPluginsChecker
            {
                private static bool _isRaidableBasesActive;
                private static bool _isZoneManagerActive;
                private static readonly HashSet<ZoneManagerData> ZoneManagerDatas = new HashSet<ZoneManagerData>();

                internal static bool IsPointAvailable(Vector3 position)
                {
                    if (_isZoneManagerActive && IsZoneManagerBlockPoint(position))
                        return false;

                    if (_isRaidableBasesActive && IsRaidableBasesBlockPoint(position))
                        return false;

                    return true;
                }

                private static bool IsZoneManagerBlockPoint(Vector3 position)
                {
                    return ZoneManagerDatas.Any(x => Vector3.Distance(x.Position, position) < x.Radius);
                }

                private static bool IsRaidableBasesBlockPoint(Vector3 position)
                {
                    return (bool)_ins.RaidableBases.Call("EventTerritory", position, CheckingEntitiesRadius);
                }

                internal static void CacheData()
                {
                    _isRaidableBasesActive = _ins.plugins.Exists("RaidableBases");

                    ZoneManagerDatas.Clear();
                    _isZoneManagerActive = _ins.plugins.Exists("ZoneManager");

                    if (!_isZoneManagerActive)
                        return;

                    string[] zoneArray = _ins.ZoneManager?.Call("GetZoneIDs") as string[];

                    if (zoneArray == null)
                        return;

                    foreach (string zoneName in zoneArray)
                    {
                        if (!_ins._config.SupportedPluginsConfig.ZoneManager.BlockIDs.Contains(zoneName) && !_ins._config.SupportedPluginsConfig.ZoneManager.BlockFlags.Any(x => (bool)_ins.ZoneManager.Call("HasFlag", zoneName, x))) 
                            continue;
                        
                        Vector3 zonePosition = (Vector3)_ins.ZoneManager.Call("GetZoneLocation", zoneName);
                        float zoneRadius = (float)_ins.ZoneManager.Call("GetZoneRadius", zoneName);
                        ZoneManagerData zoneManagerData = new ZoneManagerData(zonePosition, zoneRadius);
                        ZoneManagerDatas.Add(zoneManagerData);
                    }

                    if (ZoneManagerDatas.Count == 0)
                        _isZoneManagerActive = false;
                }

                private class ZoneManagerData
                {
                    internal readonly Vector3 Position;
                    internal readonly float Radius;

                    internal ZoneManagerData(Vector3 position, float radius)
                    {
                        Position = position;
                        Radius = radius;
                    }
                }
            }
        }

        private static class PositionDefiner
        {
            internal static Vector3 GetGlobalPosition(Transform parentTransform, Vector3 position)
            {
                return parentTransform.transform.TransformPoint(position);
            }

            internal static Vector3 GetGroundPositionInPoint(Vector3 position)
            {
                position.y = 500;

                if (Physics.Raycast(position, Vector3.down, out RaycastHit raycastHit, 500, 1 << 16 | 1 << 23))
                    position.y = raycastHit.point.y;

                return position;
            }

            internal static Quaternion GetGlobalRotation(Transform parentTransform, Vector3 rotation)
            {
                return parentTransform.rotation * Quaternion.Euler(rotation);
            }

            internal static bool GetNavmeshInPoint(Vector3 position, float radius, out NavMeshHit navMeshHit)
            {
                return NavMesh.SamplePosition(position, out navMeshHit, radius, 1);
            }

            internal static Vector3 GetLocalPosition(Transform parentTransform, Vector3 globalPosition)
            {
                return parentTransform.transform.InverseTransformPoint(globalPosition);
            }
        }

        private static class DataFileManager
        {
            private static Dictionary<string, HashSet<EntData>> _saveData;

            internal static bool TryLoadData()
            {
                _saveData = Interface.Oxide.DataFileSystem.ReadObject<Dictionary<string, HashSet<EntData>>>(_ins.Title);

                if (_saveData == null || _saveData.Count == 0)
                {
                    _ins.PrintError("Data file not found");
                    _ins.NextTick(() => Interface.Oxide.UnloadPlugin(_ins.Name));
                    return false;
                }

                return true;
            }

            internal static HashSet<EntData> GetSputnikData(string presetName)
            {
                _saveData.TryGetValue(presetName, out HashSet<EntData> entityDatas);
                return entityDatas;
            }
        }

        private class EntData
        {
            public string Prefab;
            public string Pos;
            public string Rot;
        }

        private static class NpcSpawnManager
        {
            private static readonly HashSet<ScientistNPC> EventNpcs = new HashSet<ScientistNPC>();

            internal static ScientistNPC GetScientistByNetId(ulong netId)
            {
                return EventNpcs.FirstOrDefault(x => x != null && x.net != null && x.net.ID.Value == netId);
            }

            internal static bool IsNpcSpawnReady()
            {
                if (!_ins.plugins.Exists("NpcSpawn"))
                {
                    _ins.PrintError("NpcSpawn plugin doesn`t exist! Please read the file ReadMe.txt. NPCs will not spawn!");
                    _ins.NextTick(() => Interface.Oxide.UnloadPlugin(_ins.Name));
                    return false;
                }

                return true;
            }

            internal static ScientistNPC SpawnScientistNpc(string npcPresetName, Vector3 position, float healthFraction, bool isStationary, bool isPassive = false)
            {
                NpcConfig npcConfig = GetNpcConfigByPresetName(npcPresetName);
                if (npcConfig == null)
                {
                    NotifyManager.PrintError(null, "PresetNotFound_Exeption", npcPresetName);
                    return null;
                }

                ScientistNPC scientistNpc = SpawnScientistNpc(npcConfig, position, healthFraction, isStationary, isPassive);

                if (isStationary)
                    UpdateClothesWeight(scientistNpc);

                return scientistNpc;
            }

            private static ScientistNPC SpawnScientistNpc(NpcConfig npcConfig, Vector3 position, float healthFraction, bool isStationary, bool isPassive)
            {
                JObject baseNpcConfigObj = GetBaseNpcConfig(npcConfig, healthFraction, isStationary, isPassive);
                ScientistNPC scientistNpc = (ScientistNPC)_ins.NpcSpawn.Call("SpawnNpc", position, baseNpcConfigObj, isPassive);
                if (scientistNpc == null)
                    return null;
                
                EventNpcs.Add(scientistNpc);
                EntityPresetInfo.Attach(scientistNpc, npcConfig.PresetName);
                return scientistNpc;
            }

            internal static NpcConfig GetNpcConfigByDisplayName(string displayName)
            {
                return _ins._config.NpcConfigs.FirstOrDefault(x => x.DisplayName == displayName);
            }

            public static NpcConfig GetNpcConfigByPresetName(string npcPresetName)
            {
                return _ins._config.NpcConfigs.FirstOrDefault(x => x.PresetName == npcPresetName);
            }

            private static JObject GetBaseNpcConfig(NpcConfig config, float healthFraction, bool isStationary, bool isPassive)
            {
                return new JObject
                {
                    ["Name"] = config.DisplayName,
                    ["WearItems"] = new JArray
                    {
                        config.WearItems.Select(x => new JObject
                        {
                            ["ShortName"] = x.ShortName,
                            ["SkinID"] = x.SkinID
                        })
                    },
                    ["BeltItems"] = isPassive ? new JArray() : new JArray { config.BeltItems.Select(x => new JObject { ["ShortName"] = x.ShortName, ["Amount"] = x.Amount, ["SkinID"] = x.SkinID, ["mods"] = new JArray { x.Mods.ToHashSet() }, ["Ammo"] = x.Ammo }) },
                    ["Kit"] = config.Kit,
                    ["Health"] = config.Health * healthFraction,
                    ["RoamRange"] = isStationary ? 0 : config.RoamRange,
                    ["ChaseRange"] = isStationary ? 0 : config.ChaseRange,
                    ["SenseRange"] = config.SenseRange,
                    ["ListenRange"] = config.SenseRange / 2,
                    ["AttackRangeMultiplier"] = config.AttackRangeMultiplier,
                    ["CheckVisionCone"] = false,
                    ["VisionCone"] = config.VisionCone,
                    ["HostileTargetsOnly"] = false,
                    ["DamageScale"] = config.DamageScale,
                    ["TurretDamageScale"] = config.TurretDamageScale,
                    ["AimConeScale"] = config.AimConeScale,
                    ["DisableRadio"] = config.DisableRadio,
                    ["CanRunAwayWater"] = false,
                    ["CanSleep"] = true,
                    ["SleepDistance"] = 100f,
                    ["Speed"] = isStationary ? 0 : config.Speed,
                    ["AreaMask"] = 1,
                    ["AgentTypeID"] = -1372625422,
                    ["HomePosition"] = string.Empty,
                    ["MemoryDuration"] = config.MemoryDuration,
                    ["States"] = isPassive ? new JArray() : isStationary ? new JArray { "IdleState", "CombatStationaryState" } : config.BeltItems.Any(x => x.ShortName is "rocket.launcher" or "explosive.timed") ? new JArray { "RaidState", "RoamState", "ChaseState", "CombatState" } : new JArray { "RoamState", "ChaseState", "CombatState" },
                    ["IsRemoveCorpse"] = config.DeleteCorpse
                };
            }

            private static void UpdateClothesWeight(ScientistNPC scientistNpc)
            {
                foreach (Item item in scientistNpc.inventory.containerWear.itemList)
                {
                    ItemModWearable component = item.info.GetComponent<ItemModWearable>();

                    if (component != null)
                        component.weight = 0;
                }
            }

            internal static void ClearData(bool shouldKillNpcs)
            {
                if (shouldKillNpcs)
                    foreach (ScientistNPC scientistNpc in EventNpcs)
                        if (scientistNpc.IsExists())
                            scientistNpc.Kill();

                EventNpcs.Clear();
            }
        }

        private static class ItemSpawner
        {
            internal static void GiveSpaceCardToPlayer(BasePlayer player)
            {
                Item item = CreateSpaceCardItem();
                PlayerItemGiver.GiveItemToPLayer(player, item);
            }

            internal static void SpawnSpaceCardInCrate(LootContainer container)
            {
                if (!_ins._config.CustomCardConfig.SpawnSetting.TryGetValue(container.PrefabName, out float chance) || chance == 0)
                    return;

                if (UnityEngine.Random.Range(0f, 100f) > chance)
                    return;

                container.Invoke(() =>
                {
                    if (container.IsExists())
                    {
                        Item item = CreateSpaceCardItem();

                        if (!item.MoveToContainer(container.inventory))
                            item.Remove();
                    }
                }, 1.1f);
            }

            internal static Item CreateSpaceCardItem()
            {
                Item item = ItemManager.CreateByName(_ins._config.CustomCardConfig.ShortName, 1, _ins._config.CustomCardConfig.SkinID);

                if (_ins._config.CustomCardConfig.Name != "")
                    item.name = _ins._config.CustomCardConfig.Name;

                return item;
            }

            private static class PlayerItemGiver
            {
                internal static void GiveItemToPLayer(BasePlayer player, Item item)
                {
                    int spaceCountItem = GetMaxItemCount(player, item.info.shortname, item.MaxStackable(), item.skin);
                    int inventoryItemCount;

                    if (spaceCountItem > item.amount)
                        inventoryItemCount = item.amount;
                    else
                        inventoryItemCount = spaceCountItem;

                    if (inventoryItemCount > 0)
                    {
                        Item itemInventory = ItemManager.CreateByName(item.info.shortname, inventoryItemCount, item.skin);

                        if (item.skin != 0)
                            itemInventory.name = item.name;

                        item.amount -= inventoryItemCount;
                        PlayerItemGiver.MoveInventoryItem(player, itemInventory);
                    }

                    if (item.amount > 0)
                        PlayerItemGiver.DropExtraItem(player, item);
                }

                internal static int GetMaxItemCount(BasePlayer player, string shortname, int stack, ulong skinID)
                {
                    int slots = player.inventory.containerMain.capacity + player.inventory.containerBelt.capacity;
                    int taken = player.inventory.containerMain.itemList.Count + player.inventory.containerBelt.itemList.Count;
                    int result = (slots - taken) * stack;

                    List<Item> allItems = Pool.Get<List<Item>>();
                    player.inventory.GetAllItems(allItems);

                    foreach (Item item in allItems)
                        if (item.info.shortname == shortname && item.skin == skinID && item.amount < stack)
                            result += stack - item.amount;

                    Pool.FreeUnmanaged(ref allItems);
                    return result;
                }

                internal static void MoveInventoryItem(BasePlayer player, Item item)
                {
                    if (item.amount <= item.MaxStackable())
                    {
                        List<Item> allItems = Pool.Get<List<Item>>();
                        player.inventory.GetAllItems(allItems);

                        foreach (Item itemInv in allItems)
                        {
                            if (itemInv.info.shortname == item.info.shortname && itemInv.skin == item.skin && itemInv.amount < itemInv.MaxStackable())
                            {
                                if (itemInv.amount + item.amount <= itemInv.MaxStackable())
                                {
                                    Pool.FreeUnmanaged(ref allItems);
                                    itemInv.amount += item.amount;
                                    itemInv.MarkDirty();
                                    return;
                                }
                                else
                                {
                                    item.amount -= itemInv.MaxStackable() - itemInv.amount;
                                    itemInv.amount = itemInv.MaxStackable();
                                }
                            }
                        }

                        Pool.FreeUnmanaged(ref allItems);

                        if (item.amount > 0) player.inventory.GiveItem(item);
                    }
                    else
                    {
                        while (item.amount > item.MaxStackable())
                        {
                            Item thisItem = ItemManager.CreateByName(item.info.shortname, item.MaxStackable(), item.skin);

                            if (item.skin != 0)
                                thisItem.name = item.name;

                            player.inventory.GiveItem(thisItem);
                            item.amount -= item.MaxStackable();
                        }
                        if (item.amount > 0)
                            player.inventory.GiveItem(item);
                    }
                }

                internal static void DropExtraItem(BasePlayer player, Item item)
                {
                    if (item.amount <= item.MaxStackable())
                    {
                        item.Drop(player.transform.position, Vector3.up);
                    }
                    else
                    {
                        while (item.amount > item.MaxStackable())
                        {
                            Item thisItem = ItemManager.CreateByName(item.info.shortname, item.MaxStackable(), item.skin);
                            if (item.skin != 0) thisItem.name = item.name;
                            thisItem.Drop(player.transform.position, Vector3.up);
                            item.amount -= item.MaxStackable();
                        }
                        if (item.amount > 0) item.Drop(player.transform.position, Vector3.up);
                    }
                }
            }

        }

        private static class EconomyManager
        {
            private static readonly Dictionary<ulong, double> PlayersBalance = new Dictionary<ulong, double>();

            internal static void AddBalance(ulong playerId, double balance)
            {
                if (balance == 0 || playerId == 0)
                    return;

                if (!PlayersBalance.TryAdd(playerId, balance))
                    PlayersBalance[playerId] += balance;
            }

            internal static void OnEventEnd()
            {
                DefineEventWinner();

                if (!_ins._config.SupportedPluginsConfig.EconomicsConfig.Enable || PlayersBalance.Count == 0)
                {
                    PlayersBalance.Clear();
                    return;
                }

                SendBalanceToPlayers();
                PlayersBalance.Clear();
            }

            private static void DefineEventWinner()
            {
                KeyValuePair<ulong, double> winnerPair = PlayersBalance.Max(x => (float)x.Value);

                if (winnerPair.Value > 0)
                    Interface.CallHook("OnSputnikEventWin", winnerPair.Key);

                if (winnerPair.Value >= _ins._config.SupportedPluginsConfig.EconomicsConfig.MinCommandPoint)
                    foreach (string command in _ins._config.SupportedPluginsConfig.EconomicsConfig.Commands)
                        _ins.Server.Command(command.Replace("{steamid}", $"{winnerPair.Key}"));
            }

            private static void SendBalanceToPlayers()
            {
                foreach (KeyValuePair<ulong, double> pair in PlayersBalance)
                    SendBalanceToPlayer(pair.Key, pair.Value);
            }

            private static void SendBalanceToPlayer(ulong userID, double amount)
            {
                if (amount < _ins._config.SupportedPluginsConfig.EconomicsConfig.MinEconomyPoint)
                    return;

                int intAmount = Convert.ToInt32(amount);

                if (intAmount <= 0)
                    return;

                if (_ins._config.SupportedPluginsConfig.EconomicsConfig.Plugins.Contains("Economics") && _ins.plugins.Exists("Economics"))
                    _ins.Economics.Call("Deposit", userID.ToString(), amount);

                if (_ins._config.SupportedPluginsConfig.EconomicsConfig.Plugins.Contains("Server Rewards") && _ins.plugins.Exists("ServerRewards"))
                    _ins.ServerRewards.Call("AddPoints", userID, intAmount);

                if (_ins._config.SupportedPluginsConfig.EconomicsConfig.Plugins.Contains("IQEconomic") && _ins.plugins.Exists("IQEconomic"))
                    _ins.IQEconomic.Call("API_SET_BALANCE", userID, intAmount);

                BasePlayer player = BasePlayer.FindByID(userID);
                if (player != null)
                    NotifyManager.SendMessageToPlayer(player, "SendEconomy", _ins._config.Prefix, amount);
            }
        }

        private static class NotifyManager
        {
            internal static void PrintInfoMessage(BasePlayer player, string langKey, params object[] args)
            {
                if (player == null)
                    _ins.PrintWarning(ClearColorAndSize(GetMessage(langKey, null, args)));
                else
                    _ins.PrintToChat(player, GetMessage(langKey, player.UserIDString, args));
            }

            internal static void PrintError(BasePlayer player, string langKey, params object[] args)
            {
                if (player == null)
                    _ins.PrintError(ClearColorAndSize(GetMessage(langKey, null, args)));
                else
                    _ins.PrintToChat(player, GetMessage(langKey, player.UserIDString, args));
            }

            internal static void PrintLogMessage(string langKey, params object[] args)
            {
                for (int i = 0; i < args.Length; i++)
                    if (args[i] is int)
                        args[i] = GetTimeMessage(null, (int)args[i]);

                _ins.Puts(ClearColorAndSize(GetMessage(langKey, null, args)));
            }

            internal static void PrintWarningMessage(string langKey, params object[] args)
            {
                _ins.PrintWarning(ClearColorAndSize(GetMessage(langKey, null, args)));
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

            internal static void SendMessageToAll(string langKey, params object[] args)
            {
                foreach (BasePlayer player in BasePlayer.activePlayerList)
                    if (player != null)
                        SendMessageToPlayer(player, langKey, args);

                TrySendDiscordMessage(langKey, args);
            }

            internal static void SendMessageToPlayer(BasePlayer player, string langKey, params object[] args)
            {
                for (int i = 0; i < args.Length; i++)
                    if (args[i] is int)
                        args[i] = GetTimeMessage(player.UserIDString, (int)args[i]);

                string playerMessage = GetMessage(langKey, player.UserIDString, args);

                if (_ins._config.NotifyConfig.IsChatEnable)
                    _ins.PrintToChat(player, playerMessage);

                if (_ins._config.NotifyConfig.GameTipConfig.IsEnabled)
                    player.SendConsoleCommand("gametip.showtoast", _ins._config.NotifyConfig.GameTipConfig.Style, ClearColorAndSize(playerMessage), string.Empty);

                if (_ins._config.SupportedPluginsConfig.GUIAnnouncementsConfig.IsEnabled && _ins.plugins.Exists("guiAnnouncementsConfig"))
                    _ins.GUIAnnouncements?.Call("CreateAnnouncement", ClearColorAndSize(playerMessage), _ins._config.SupportedPluginsConfig.GUIAnnouncementsConfig.BannerColor, _ins._config.SupportedPluginsConfig.GUIAnnouncementsConfig.TextColor, player, _ins._config.SupportedPluginsConfig.GUIAnnouncementsConfig.APIAdjustVPosition);

                if (_ins._config.SupportedPluginsConfig.NotifyPluginConfig.IsEnabled && _ins.plugins.Exists("Notify"))
                    _ins.Notify?.Call("SendNotify", player, _ins._config.SupportedPluginsConfig.NotifyPluginConfig.Type, ClearColorAndSize(playerMessage));
            }

            internal static string GetTimeMessage(string userIDString, int seconds)
            {
                string message = "";

                TimeSpan timeSpan = TimeSpan.FromSeconds(seconds);
                if (timeSpan.Hours > 0) message += $" {timeSpan.Hours} {GetMessage("Hours", userIDString)}";
                if (timeSpan.Minutes > 0) message += $" {timeSpan.Minutes} {GetMessage("Minutes", userIDString)}";
                if (message == "") message += $" {timeSpan.Seconds} {GetMessage("Seconds", userIDString)}";

                return message;
            }

            private static void TrySendDiscordMessage(string langKey, params object[] args)
            {
                if (CanSendDiscordMessage(langKey))
                {
                    for (int i = 0; i < args.Length; i++)
                        if (args[i] is int)
                            args[i] = GetTimeMessage(null, (int)args[i]);

                    object fields = new[] { new { name = _ins.Title, value = ClearColorAndSize(GetMessage(langKey, null, args)), inline = false } };
                    _ins.DiscordMessages?.Call("API_SendFancyMessage", _ins._config.SupportedPluginsConfig.DiscordMessagesConfig.WebhookUrl, "", _ins._config.SupportedPluginsConfig.DiscordMessagesConfig.EmbedColor, JsonConvert.SerializeObject(fields), null, _ins);
                }
            }

            private static bool CanSendDiscordMessage(string langKey)
            {
                return _ins._config.SupportedPluginsConfig.DiscordMessagesConfig.Keys.Contains(langKey) && _ins._config.SupportedPluginsConfig.DiscordMessagesConfig.IsEnabled && !string.IsNullOrEmpty(_ins._config.SupportedPluginsConfig.DiscordMessagesConfig.WebhookUrl) && _ins._config.SupportedPluginsConfig.DiscordMessagesConfig.WebhookUrl != "https://support.discordapp.com/hc/en-us/articles/228383668-Intro-to-Webhooks";
            }
        }

        private static class IqDroneManager
        {
            internal static void SpawnDrones(Vector3 position, string presetName, int zoneRadius)
            {
                if (!_ins._config.SupportedPluginsConfig.IqDroneConfig.IsDroneEnable)
                    return;

                if (!_ins.plugins.Exists("IQDronePatrol"))
                {
                    _ins.PrintError("IQDronePatrol plugin doesn`t exist!");
                    return;
                }

                CustomPatrol customPatrol = GetPresetPatrol(position, presetName, zoneRadius);
                if (customPatrol == null)
                {
                    _ins.PrintError("IQDronePatrol the preset is specified incorrectly!");
                    return;
                }

                string json = JsonConvert.SerializeObject(customPatrol);
                _ins.IQDronePatrol.Call<Dictionary<Drone, AutoTurret>>("SendPatrolPoint", json, false);
            }

            internal static void KillDrones(Vector3 position)
            {
                if (!_ins._config.SupportedPluginsConfig.IqDroneConfig.IsDroneEnable)
                    return;

                if (!_ins.plugins.Exists("IQDronePatrol"))
                    return;

                _ins.IQDronePatrol.Call("CancellPatrol", position);
            }

            private static CustomPatrol GetPresetPatrol(Vector3 position, string presetName, int zoneRadius)
            {
                IqDronePreset iQDronePreset = _ins._config.SupportedPluginsConfig.IqDroneConfig.DronePresets.FirstOrDefault(x => x.PresetName == presetName);

                CustomPatrol myPatrol = new CustomPatrol
                {
                    PluginName = _ins.Name,
                    Position = position,
                    SettingDrone = new CustomPatrol.DroneSetting
                    {
                        DroneCountSpawned = iQDronePreset.DroneNumber,
                        DroneAttackedCount = iQDronePreset.TargetDroneNumber,
                        KeyDrones = iQDronePreset.DroneKeys
                    },
                    SettingPosition = new CustomPatrol.PositionSetting
                    {
                        CountSpawnPoint = 200,
                        RadiusFindedPoints = zoneRadius
                    },
                };

                return myPatrol;
            }

            private class CustomPatrol
            {
                public string PluginName;
                public Vector3 Position;
                public PositionSetting SettingPosition = new PositionSetting();
                public DroneSetting SettingDrone = new DroneSetting();

                internal class DroneSetting
                {
                    public Int32 DroneCountSpawned;
                    public Int32 DroneAttackedCount;
                    public Dictionary<string, Int32> KeyDrones = new Dictionary<string, int>();
                }
                internal class PositionSetting
                {
                    public Int32 CountSpawnPoint;
                    public Int32 RadiusFindedPoints;
                }
            }
        }
        #endregion Classes
        
        #region Loot
        private readonly Dictionary<string, PrefabLootInfo> _prefabLootTables = new Dictionary<string, PrefabLootInfo>();
        
        private static class LootController
        {
            public static LootTableConfig GetDefaultLootTable()
            {
                return new LootTableConfig
                {
                    IsAlphaLoot = true,
                    AlphaLootPreset = string.Empty,
                    IsLoottablePlugin = true,
                    LoottablePreset = string.Empty,
                    IsCustomLootPlugin = true,
                    CustomLootPreset = string.Empty,
                    ClearDefaultLoot = false,
                    
                    ItemsTable = new ItemsLootTableConfig
                    {
                        IsEnabled = false,
                        DisableMinMax = false,
                        MinItemsAmount = 1,
                        MaxItemsAmount =1,
                        Items = new List<LootItemConfig>
                        {
                            new LootItemConfig
                            {
                                Shortname = "scrap",
                                Skin = 0,
                                Chance = 100,
                                MinAmount = 100,
                                MaxAmount = 200,
                                DisplayName = string.Empty,
                                OwnerName = string.Empty,
                                IsBlueprint = false,
                                Genomes = new List<string>()
                            }
                        }
                    },
                    PrefabsTable = new PrefabsLootTableConfig
                    {
                        IsEnabled = false,
                        MinPrefabsAmount = 1,
                        MaxPrefabsAmount = 1,
                        Prefabs = new List<LootPrefabConfig>
                        {
                            new LootPrefabConfig
                            {
                                PrefabName = "assets/bundled/prefabs/radtown/crate_normal.prefab",
                                Chance = 100,
                                MinAmount = 1,
                                MaxAmount = 1
                            }
                        }
                    }
                };
            }

            public static HashSet<LootTableConfig> GetAllLootTables()
            {
                HashSet<LootTableConfig> result = new HashSet<LootTableConfig>();
                
                foreach (NpcConfig npcConfig in _ins._config.NpcConfigs)
                    result.Add(npcConfig.LootTableConfig);
                
                foreach (CrateConfig crateConfig in _ins._config.CrateConfigs)
                    result.Add(crateConfig.LootTableConfig);
                
                foreach (HeliConfig heliConfig in _ins._config.HeliConfigs)
                    result.Add(heliConfig.LootTableConfig);
                
                return result;
            }

            public static void UpdateNpcCorpse(NPCPlayerCorpse corpse, NpcConfig npcConfig)
            {
                if (corpse.containers.IsNullOrEmpty())
                    return;
                
                EntityPresetInfo.Attach(corpse, npcConfig.PresetName);
                
                if (IsNoAdditionLoot(npcConfig.LootTableConfig))
                    return;
                
                ItemContainer container = corpse.containers[0];
                if (container == null)
                    return;
                
                corpse.Invoke(() =>
                {
                    if (!corpse.IsExists())
                        return;
                    
                    FillContainer(container, npcConfig.LootTableConfig);
                }, 0.1f);
            }

            public static void UpdateCrate(BaseEntity crateEntity, CrateConfig crateConfig)
            {
                if (crateEntity == null)
                    return;
                
                EntityPresetInfo.Attach(crateEntity, crateConfig.PresetName);

                if (IsNoAdditionLoot(crateConfig.LootTableConfig))
                    return;
                
                Fridge fridge = crateEntity as Fridge;
                if (fridge != null)
                {
                    fridge.OnlyAcceptCategory = ItemCategory.All;
                    FillContainer(fridge.inventory, crateConfig.LootTableConfig);
                    return;
                }
                
                LootContainer lootContainer = crateEntity as LootContainer;
                if (lootContainer != null)
                {
                    lootContainer.Invoke(() =>
                    {
                        if (lootContainer.inventory != null)
                            FillContainer(lootContainer.inventory, crateConfig.LootTableConfig);
                    }, 2f);
                    
                    return;
                }
                
                DroppedItemContainer droppedItemContainer = crateEntity as DroppedItemContainer;
                if (droppedItemContainer != null)
                {
                    FillContainer(droppedItemContainer.inventory, crateConfig.LootTableConfig);
                    return;
                }
                
                StorageContainer storageContainer = crateEntity as StorageContainer;
                if (storageContainer != null)
                {
                    FillContainer(storageContainer.inventory, crateConfig.LootTableConfig);
                }
            }

            public static void UpdateHeliOrBradleyCrate(LockedByEntCrate lockedByEntCrate, LootTableConfig lootTableConfig, string presetName)
            {
                EntityPresetInfo.Attach(lockedByEntCrate, presetName);
                
                if (IsNoAdditionLoot(lootTableConfig))
                    return;
                
                lockedByEntCrate.Invoke(() =>
                {
                    if (lockedByEntCrate.inventory != null)
                        FillContainer(lockedByEntCrate.inventory, lootTableConfig);
                }, 2f);
            }
            
            private static bool IsNoAdditionLoot(LootTableConfig lootTableConfig)
            {
                return !lootTableConfig.ClearDefaultLoot &&
                       !lootTableConfig.ItemsTable.IsEnabled &&
                       !lootTableConfig.PrefabsTable.IsEnabled &&
                       string.IsNullOrEmpty(lootTableConfig.AlphaLootPreset) &&
                       string.IsNullOrEmpty(lootTableConfig.LoottablePreset) &&
                       string.IsNullOrEmpty(lootTableConfig.CustomLootPreset);
            }

            private static void FillContainer(ItemContainer container, LootTableConfig lootTable)
            {
                if (lootTable.ClearDefaultLoot)
                    ClearContainer(container);
                
                if (lootTable.ItemsTable.IsEnabled)
                {
                    if (lootTable.ItemsTable.DisableMinMax)
                    {
                        ItemListController.FillContainerWithoutMinMax(container, lootTable.ItemsTable.Items);
                    }
                    else if (lootTable.ItemsTable.Items.Count > 0)
                    {
                        int itemsAmount = Random.Range(lootTable.ItemsTable.MinItemsAmount, lootTable.ItemsTable.MaxItemsAmount + 1);
                        HashSet<LootItemConfig> itemsForSpawn = GetElementsForSpawn(lootTable.ItemsTable.Items, itemsAmount);

                        if (itemsForSpawn.Count > 0)
                            ItemListController.FillContainer(container, itemsForSpawn);
                    }
                }
                
                if (lootTable.PrefabsTable.IsEnabled)
                {
                    int prefabsAmount = Random.Range(lootTable.PrefabsTable.MinPrefabsAmount, lootTable.PrefabsTable.MaxPrefabsAmount + 1);
                    HashSet<LootPrefabConfig> prefabsForSpawn = GetElementsForSpawn(lootTable.PrefabsTable.Prefabs, prefabsAmount);
                    PrefabController.FillContainer(container, prefabsForSpawn);
                }
                
                if (!string.IsNullOrEmpty(lootTable.AlphaLootPreset))
                {
                    if (_ins.plugins.Exists("AlphaLoot") && (bool)_ins.AlphaLoot.Call("ProfileExists", lootTable.AlphaLootPreset))
                    {
                        _ins.AlphaLoot.Call("PopulateLoot", container, lootTable.AlphaLootPreset);
                    }
                }

                if (!string.IsNullOrEmpty(lootTable.CustomLootPreset))
                {
                    if (!_ins.plugins.Exists("CustomLoot"))
                        return;

                    List<Item> items = _ins.CustomLoot?.Call<List<Item>>("MakeLoot", lootTable.CustomLootPreset);
                    if (items != null)
                        foreach (Item item in items)
                            if (item != null && !item.MoveToContainer(container))
                                item.Remove();
                }

                if (!string.IsNullOrEmpty(lootTable.LoottablePreset))
                {
                    if (!_ins.plugins.Exists("Loottable"))
                        return;

                    List<Item> items = _ins.Loottable?.Call<List<Item>>("MakeLoot", lootTable.LoottablePreset);
                    if (items != null)
                    {
                        foreach (Item item in items)
                            if (item != null && !item.MoveToContainer(container))
                                item.Remove();

                        Pool.FreeUnmanaged(ref items);
                    }
                }
            }
            
            private static void ClearContainer(ItemContainer container)
            {
                for (int i = container.itemList.Count - 1; i >= 0; i--)
                {
                    Item item = container.itemList[i];
                    item.RemoveFromContainer();
                    item.Remove();
                }
            }
            
            private static HashSet<T> GetElementsForSpawn<T>(List<T> elements, int targetAmount) where T : LootElementChanceConfig
            {
                HashSet<T> result = new HashSet<T>();
                if (elements.Count == 0 || targetAmount <= 0)
                    return result;

                HashSet<int> includedIndexes = new HashSet<int>();
                
                for (int i = 0; i < elements.Count; i++)
                {
                    T elementConfig = elements[i];
                    
                    if (elementConfig.Chance >= 100f)
                    {
                        includedIndexes.Add(i);
                        result.Add(elementConfig);
                        
                        if (result.Count >= targetAmount)
                            break;
                    }
                }

                if (result.Count >= targetAmount)
                    return result;
                    
                int counter = 200;
                while (result.Count < targetAmount && counter-- > 0)
                {
                    float sumChance = 0f;
                        
                    for (int i = 0; i < elements.Count; i++)
                    {
                        if (includedIndexes.Contains(i))
                            continue;

                        sumChance += elements[i].Chance;
                    }
                        
                    if (sumChance <= 0f) 
                        break;

                    float random = Random.Range(0f, sumChance);
                    for (int i = 0; i < elements.Count; i++)
                    {
                        if (includedIndexes.Contains(i))
                            continue;

                        T lootElement = elements[i];
                        random -= lootElement.Chance;
                        if (random <= 0f)
                        {
                            includedIndexes.Add(i);
                            result.Add(lootElement);
                            break;
                        }
                    }
                }

                return result;
            }
        }
        
        private static class ItemListController
        {
            public static void FillContainer(ItemContainer container, HashSet<LootItemConfig> items)
            {
                int itemsCount = container.itemList.Count + items.Count;
                if (container.capacity < itemsCount)
                    container.capacity = itemsCount;
                
                foreach (LootItemConfig lootItemConfig in items)
                {
                    Item item = CreateItem(lootItemConfig);
                    if (item == null)
                        continue;

                    if (!item.MoveToContainer(container))
                        item.Remove();
                }
            }

            public static void FillContainerWithoutMinMax(ItemContainer container, List<LootItemConfig> items)
            {
                foreach (LootItemConfig itemConfig in items)
                {
                    float roll = Random.Range(0f, 100f);
                    if (roll > itemConfig.Chance)
                        continue;

                    Item item = CreateItem(itemConfig);
                    if (item == null)
                        continue;

                    if (!item.MoveToContainer(container))
                        item.Remove();
                }
            }

            private static Item CreateItem(LootItemConfig lootItemConfig)
            {
                int amount = Random.Range(lootItemConfig.MinAmount, lootItemConfig.MaxAmount + 1);
                if (amount == 0)
                    return null;

                Item item;

                if (lootItemConfig.IsBlueprint)
                {
                    ItemDefinition itemDefinition = ItemManager.FindItemDefinition(lootItemConfig.Shortname);
                    if (itemDefinition == null)
                        return null;
                    
                    item = ItemManager.CreateByName("blueprintbase");
                    item.blueprintTarget = itemDefinition.itemid;
                }
                else
                {
                    item = ItemManager.CreateByName(lootItemConfig.Shortname, amount, lootItemConfig.Skin);
                }

                if (item == null)
                {
                    _ins.PrintWarning($"Failed to create item! ({lootItemConfig.Shortname})");
                    return null;
                }

                if (!string.IsNullOrEmpty(lootItemConfig.DisplayName))
                    item.name = lootItemConfig.DisplayName;

                if (lootItemConfig.Genomes != null && lootItemConfig.Genomes.Count > 0)
                {
                    string genome = lootItemConfig.Genomes.GetRandom();
                    UpdateGenome(item, genome);
                }

                return item;
            }

            private static void UpdateGenome(Item item, string genome)
            {
                if (genome.Length != 6)
                    return;

                genome = genome.ToLower();
                GrowableGenes growableGenes = new GrowableGenes();

                for (int i = 0; i < 6 && i < genome.Length; ++i)
                {

                    GrowableGenetics.GeneType geneType = GrowableGenetics.GeneType.Empty;

                    switch (genome[i])
                    {
                        case 'g':
                            geneType = GrowableGenetics.GeneType.GrowthSpeed;
                            break;
                        case 'y':
                            geneType = GrowableGenetics.GeneType.Yield;
                            break;
                        case 'h':
                            geneType = GrowableGenetics.GeneType.Hardiness;
                            break;
                        case 'w':
                            geneType = GrowableGenetics.GeneType.WaterRequirement;
                            break;
                    }

                    growableGenes.Genes[i].Set(geneType, true);
                }

                GrowableGeneEncoding.EncodeGenesToItem(GrowableGeneEncoding.EncodeGenesToInt(growableGenes), item);
            }
        }
        
        private static class PrefabController
        {
            public static void CachePrefabs()
            {
                HashSet<LootTableConfig> lootTableConfigs = LootController.GetAllLootTables();
                
                foreach (LootTableConfig lootTableConfig in lootTableConfigs)
                {
                    if (!lootTableConfig.PrefabsTable.IsEnabled)
                        continue;
        
                    foreach (LootPrefabConfig lootPrefabConfig in lootTableConfig.PrefabsTable.Prefabs)
                        CachePrefab(lootPrefabConfig);
                }
            }

            private static void CachePrefab(LootPrefabConfig lootPrefabConfig)
            {
                if (_ins._prefabLootTables.ContainsKey(lootPrefabConfig.PrefabName))
                    return;
        
                GameObject gameObject = GameManager.server.FindPrefab(lootPrefabConfig.PrefabName);
                if (gameObject == null)
                    return;
        
                LootContainer lootContainer = gameObject.GetComponent<LootContainer>();
                if (lootContainer != null)
                {
                    SavePrefabLootInfo(lootPrefabConfig.PrefabName, lootContainer.LootSpawnSlots, lootContainer.scrapAmount, lootContainer.lootDefinition, lootContainer.maxDefinitionsToSpawn);
                    return;
                }
        
                global::HumanNPC humanNpc = gameObject.GetComponent<global::HumanNPC>();
                if (humanNpc != null)
                {
                    SavePrefabLootInfo(lootPrefabConfig.PrefabName, humanNpc.LootSpawnSlots);
                    return;
                }
        
                global::ScarecrowNPC scarecrowNpc = gameObject.GetComponent<global::ScarecrowNPC>();
                if (scarecrowNpc != null)
                {
                    SavePrefabLootInfo(lootPrefabConfig.PrefabName, scarecrowNpc.LootSpawnSlots);
                }
            }
        
            private static void SavePrefabLootInfo(string prefabName, LootContainer.LootSpawnSlot[] lootSpawnSlot, int scrapAmount = 0, LootSpawn lootDefinition = null, int maxDefinitionsToSpawn = 0)
            {
                PrefabLootInfo prefabLootInfo = new PrefabLootInfo
                {
                    LootSpawnSlots = lootSpawnSlot,
                    LootDefinition = lootDefinition,
                    MaxDefinitionsToSpawn = maxDefinitionsToSpawn,
                    ScrapAmount = scrapAmount
                };
        
                _ins._prefabLootTables.TryAdd(prefabName, prefabLootInfo);
            }
        
            public static void FillContainer(ItemContainer container, HashSet<LootPrefabConfig> prefabs)
            {
                foreach (LootPrefabConfig lootPrefabConfig in prefabs)
                    FillContainer(container, lootPrefabConfig);
            }
        
            private static void FillContainer(ItemContainer container, LootPrefabConfig prefab)
            {
                if (!_ins._prefabLootTables.TryGetValue(prefab.PrefabName, out PrefabLootInfo prefabLootInfo))
                    return;
        
                int lootScale = Random.Range(prefab.MinAmount, prefab.MaxAmount + 1);
        
                for (int i = 0; i < lootScale; i++)
                {
                    if (prefabLootInfo.LootSpawnSlots != null)
                    {
                        foreach (LootContainer.LootSpawnSlot lootSpawnSlot in prefabLootInfo.LootSpawnSlots)
                        {
                            if (lootSpawnSlot.eras == null || lootSpawnSlot.eras.Length == 0 || Array.IndexOf(lootSpawnSlot.eras, ConVar.Server.Era) != -1)
                            {
                                for (int j = 0; j < lootSpawnSlot.numberToSpawn; ++j)
                                {
                                    if (Random.Range(0f, 1f) <= lootSpawnSlot.probability)
                                    {
                                        lootSpawnSlot.definition.SpawnIntoContainer(container);
                                    }
                                }
                            }
                        }
                    }
        
                    if (prefabLootInfo.LootDefinition != null)
                        for (int j = 0; j < prefabLootInfo.MaxDefinitionsToSpawn; ++j)
                            prefabLootInfo.LootDefinition.SpawnIntoContainer(container);
        
                    if (prefabLootInfo.ScrapAmount > 0)
                    {
                        Item item = ItemManager.CreateByName("scrap", prefabLootInfo.ScrapAmount);
                        if (!item.MoveToContainer(container))
                            item.Remove();
                    }
                }
            }
        }
        
        private class PrefabLootInfo
        {
            public LootContainer.LootSpawnSlot[] LootSpawnSlots;
            public LootSpawn LootDefinition;
            public int MaxDefinitionsToSpawn;
            public int ScrapAmount;
        }

        private class EntityPresetInfo : FacepunchBehaviour
        {
            public string presetName;

            public static void Attach(BaseEntity entity, string presetName)
            {
                EntityPresetInfo entityPresetInfo = entity.gameObject.AddComponent<EntityPresetInfo>();
                entityPresetInfo.presetName = presetName;
            }
        }
        
        
        private static class LootMigrator
        {
            public static void MigrateFromLootManager()
            {
                foreach (CrateConfig crateConfig in _ins._config.CrateConfigs)
                {
                    crateConfig.LootTableConfig = GetLootTableConfig(crateConfig.LootManagerPreset);
                    
                    crateConfig.LootManagerPreset = null;
                    crateConfig.LootTableConfigOld = null;
                }

                foreach (NpcConfig npcConfig in _ins._config.NpcConfigs)
                {
                    npcConfig.LootTableConfig = GetLootTableConfig(npcConfig.LootManagerPreset);
                    
                    npcConfig.LootManagerPreset = null;
                    npcConfig.LootTableConfigOld = null;
                }
                
                foreach (HeliConfig heliConfig in _ins._config.HeliConfigs)
                {
                    heliConfig.LootTableConfig = GetLootTableConfig(heliConfig.LootManagerPreset);
                    
                    heliConfig.LootManagerPreset = null;
                    heliConfig.BaseLootTableConfigOld = null;
                }
            }

            private static LootTableConfig GetLootTableConfig(string lootManagerPreset)
            {
                if (string.IsNullOrEmpty(lootManagerPreset))
                    return LootController.GetDefaultLootTable();

                LootTableData lootTableData = GetLootManagerTableData(lootManagerPreset);
                if (lootTableData == null)
                    return LootController.GetDefaultLootTable();
                
                return GetLootTable(lootTableData);
            }

            private static LootTableData GetLootManagerTableData(string lootManagerPreset)
            {
                string path = $"LootManager/LootTables/{lootManagerPreset}";
                LootTableData lootTableData = Interface.Oxide.DataFileSystem.ReadObject<LootTableData>(path);
                return lootTableData;
            }

            private static LootTableConfig GetLootTable(LootTableData lootTableData)
            {
                LootTableConfig lootTableConfig = new LootTableConfig
                {
                    IsAlphaLoot = lootTableData.IsAlphaLoot,
                    AlphaLootPreset = string.IsNullOrEmpty(lootTableData.AlphaLootPreset) ? string.Empty : lootTableData.AlphaLootPreset,
                    IsLoottablePlugin = lootTableData.IsLootTablePlugin,
                    LoottablePreset = string.IsNullOrEmpty(lootTableData.LootTablePluginLootPreset) ? string.Empty : lootTableData.LootTablePluginLootPreset,
                    IsCustomLootPlugin = lootTableData.IsCustomLoot,
                    CustomLootPreset = string.IsNullOrEmpty(lootTableData.CustomLootPreset) ? string.Empty : lootTableData.CustomLootPreset,
                    ClearDefaultLoot = lootTableData.ClearDefaultItems,
                    ItemsTable = new ItemsLootTableConfig
                    {
                        IsEnabled = lootTableData.UseItemList,
                        DisableMinMax = !lootTableData.UseMinMaxForItems,
                        MinItemsAmount = lootTableData.MinItemsAmount,
                        MaxItemsAmount = lootTableData.MaxItemsAmount,
                        Items = new List<LootItemConfig>()
                    },
                    PrefabsTable = new PrefabsLootTableConfig
                    {
                        IsEnabled = lootTableData.UsePrefabList,
                        MinPrefabsAmount = lootTableData.MinPrefabsAmount,
                        MaxPrefabsAmount = lootTableData.MaxPrefabsAmount,
                        Prefabs = new List<LootPrefabConfig>()
                    }
                };

                if (lootTableData.Items != null)
                {
                    foreach (ItemData itemData in lootTableData.Items)
                    {
                        if (string.IsNullOrEmpty(itemData.ShortName))
                            continue;
                        
                        LootItemConfig lootItemConfig = new LootItemConfig
                        {
                            Shortname = itemData.ShortName,
                            Skin = itemData.Skin,
                            Chance = itemData.Chance,
                            MinAmount = itemData.MinAmount,
                            MaxAmount = itemData.MaxAmount,
                            DisplayName = string.IsNullOrEmpty(itemData.CustomDisplayName) ? string.Empty : itemData.CustomDisplayName,
                            OwnerName = string.IsNullOrEmpty(itemData.OwnerDisplayName) ? string.Empty : itemData.OwnerDisplayName,
                            Genomes = new List<string>(),
                            IsBlueprint = itemData.IsBluePrint
                        };
                        
                        lootTableConfig.ItemsTable.Items.Add(lootItemConfig);
                    }
                }
                
                if (lootTableData.Prefabs != null)
                {
                    foreach (PrefabData prefabData in lootTableData.Prefabs)
                    {
                        if (string.IsNullOrEmpty(prefabData.PrefabName))
                            continue;

                        LootPrefabConfig lootPrefabConfig = new LootPrefabConfig
                        {
                            PrefabName = prefabData.PrefabName,
                            Chance = prefabData.Chance,
                            MinAmount = prefabData.MinAmount,
                            MaxAmount = prefabData.MaxAmount
                        };
                        
                        lootTableConfig.PrefabsTable.Prefabs.Add(lootPrefabConfig);
                    }
                }

                return lootTableConfig;
            }


            public static void MigrateFromOldLootTables() 
            {
                foreach (CrateConfig crateConfig in _ins._config.CrateConfigs)
                {
                    crateConfig.LootTableConfig = GetLootTableConfig(crateConfig.LootTableConfigOld);
                    
                    crateConfig.LootManagerPreset = null;
                    crateConfig.LootTableConfigOld = null;
                }

                foreach (NpcConfig npcConfig in _ins._config.NpcConfigs)
                {
                    npcConfig.LootTableConfig = GetLootTableConfig(npcConfig.LootTableConfigOld);
                    
                    npcConfig.LootManagerPreset = null;
                    npcConfig.LootTableConfigOld = null;
                }
                
                foreach (HeliConfig heliConfig in _ins._config.HeliConfigs)
                {
                    heliConfig.LootTableConfig = GetLootTableConfig(heliConfig.BaseLootTableConfigOld);
                    
                    heliConfig.LootManagerPreset = null;
                    heliConfig.BaseLootTableConfigOld = null;
                }
            }
            
            private static LootTableConfig GetLootTableConfig(LootTableConfigOld lootTableConfigOld)
            {
                if (lootTableConfigOld == null || lootTableConfigOld.Items == null || lootTableConfigOld.PrefabConfigsOld == null)
                    return LootController.GetDefaultLootTable();
                
                LootTableConfig lootTableConfig = new LootTableConfig
                {
                    IsAlphaLoot = lootTableConfigOld.IsAlphaLoot,
                    AlphaLootPreset = string.IsNullOrEmpty(lootTableConfigOld.AlphaLootPresetName) ? string.Empty : lootTableConfigOld.AlphaLootPresetName,
                    IsLoottablePlugin = lootTableConfigOld.IsLootTablePlugin,
                    LoottablePreset = string.Empty,
                    IsCustomLootPlugin = lootTableConfigOld.IsCustomLoot,
                    CustomLootPreset = string.Empty,
                    ClearDefaultLoot = lootTableConfigOld.ClearDefaultItemList,
                    ItemsTable = new ItemsLootTableConfig
                    {
                        IsEnabled = lootTableConfigOld.IsRandomItemsEnable,
                        DisableMinMax = false,
                        MinItemsAmount = lootTableConfigOld.MinItemsAmount,
                        MaxItemsAmount = lootTableConfigOld.MaxItemsAmount,
                        Items = new List<LootItemConfig>()
                    },
                    PrefabsTable = new PrefabsLootTableConfig
                    {
                        IsEnabled = lootTableConfigOld.PrefabConfigsOld.IsEnable,
                        MinPrefabsAmount = 1,
                        MaxPrefabsAmount = 1,
                        Prefabs = new List<LootPrefabConfig>()
                    }
                };

                foreach (LootItemConfigOld itemConfigOld in lootTableConfigOld.Items)
                {
                    if (itemConfigOld == null || string.IsNullOrEmpty(itemConfigOld.Shortname))
                        continue;
                    
                    LootItemConfig lootItemConfig = new LootItemConfig
                    {
                        Shortname = itemConfigOld.Shortname,
                        Skin = itemConfigOld.Skin,
                        Chance = itemConfigOld.Chance,
                        MinAmount = itemConfigOld.MinAmount,
                        MaxAmount = itemConfigOld.MaxAmount,
                        DisplayName = string.IsNullOrEmpty(itemConfigOld.Name) ? string.Empty : itemConfigOld.Name,
                        OwnerName = string.Empty,
                        Genomes = new List<string>(),
                        IsBlueprint = itemConfigOld.IsBlueprint
                    };
                        
                    lootTableConfig.ItemsTable.Items.Add(lootItemConfig);
                }

                if (lootTableConfigOld.PrefabConfigsOld.Prefabs != null)
                {
                    foreach (PrefabConfigOld prefabConfigOld in lootTableConfigOld.PrefabConfigsOld.Prefabs)
                    {
                        if (string.IsNullOrEmpty(prefabConfigOld.PrefabName))
                            continue;

                        LootPrefabConfig lootPrefabConfig = new LootPrefabConfig
                        {
                            PrefabName = prefabConfigOld.PrefabName,
                            Chance = 100,
                            MinAmount = prefabConfigOld.MinLootScale,
                            MaxAmount = prefabConfigOld.MaxLootScale
                        };

                        lootTableConfig.PrefabsTable.Prefabs.Add(lootPrefabConfig);
                    }
                }

                return lootTableConfig;
            }
            
            private static LootTableConfig GetLootTableConfig(BaseLootTableConfigOld configOld)
            {
                LootTableConfig result = LootController.GetDefaultLootTable();
                
                result.ItemsTable.IsEnabled = configOld.IsRandomItemsEnable;
                result.ItemsTable.MinItemsAmount = configOld.MinItemsAmount;
                result.ItemsTable.MaxItemsAmount = configOld.MaxItemsAmount;
                if (configOld.Items != null)
                {
                    result.ItemsTable.Items.Clear();
                    
                    foreach (LootItemConfigOld itemConfigOld in configOld.Items)
                    {
                        if (itemConfigOld == null || string.IsNullOrEmpty(itemConfigOld.Shortname))
                            continue;
                    
                        LootItemConfig lootItemConfig = new LootItemConfig
                        {
                            Shortname = itemConfigOld.Shortname,
                            Skin = itemConfigOld.Skin,
                            Chance = itemConfigOld.Chance,
                            MinAmount = itemConfigOld.MinAmount,
                            MaxAmount = itemConfigOld.MaxAmount,
                            DisplayName = string.IsNullOrEmpty(itemConfigOld.Name) ? string.Empty : itemConfigOld.Name,
                            OwnerName = string.Empty,
                            Genomes = new List<string>(),
                            IsBlueprint = itemConfigOld.IsBlueprint
                        };
                        
                        result.ItemsTable.Items.Add(lootItemConfig);
                    }
                }

                if (configOld.PrefabConfigsOld != null)
                {
                    result.PrefabsTable.IsEnabled = configOld.PrefabConfigsOld.IsEnable;
                    
                    if (configOld.PrefabConfigsOld.Prefabs != null)
                    {
                        result.PrefabsTable.Prefabs.Clear();
                        
                        foreach (PrefabConfigOld prefabConfigOld in configOld.PrefabConfigsOld.Prefabs)
                        {
                            if (string.IsNullOrEmpty(prefabConfigOld.PrefabName))
                                continue;

                            LootPrefabConfig lootPrefabConfig = new LootPrefabConfig
                            {
                                PrefabName = prefabConfigOld.PrefabName,
                                Chance = 100,
                                MinAmount = prefabConfigOld.MinLootScale,
                                MaxAmount = prefabConfigOld.MaxLootScale
                            };

                            result.PrefabsTable.Prefabs.Add(lootPrefabConfig);
                        }
                    }
                }

                return result;
            }
        }
        
        private class LootTableData
        {
            public bool ClearDefaultItems;
            public bool IsAlphaLoot;
            public string AlphaLootPreset;
            public bool IsCustomLoot;
            public string CustomLootPreset;
            public bool IsLootTablePlugin;
            public string LootTablePluginLootPreset;

            public bool UseItemList;
            public bool UseMinMaxForItems;
            public int MinItemsAmount;
            public int MaxItemsAmount;
            public List<ItemData> Items = new List<ItemData>();

            public bool UsePrefabList;
            public int MinPrefabsAmount;
            public int MaxPrefabsAmount;
            public List<PrefabData> Prefabs = new List<PrefabData>();
        }

        private class ItemData : LootElementChance
        {
            public string ShortName;
            public string CustomDisplayName;
            public string OwnerDisplayName;
            public ulong Skin;
            public bool IsBluePrint;
            public string Genomes;
        }

        private class PrefabData : LootElementChance
        {
            public string PrefabName;
            public string ShortPrefabName;
        }

        private class LootElementChance
        {
            public int MinAmount;
            public int MaxAmount;
            public float Chance;
        }
        #endregion Loot

        #region Lang
        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["EventActive_Exeption"] = "Ивент в данный момент активен, сначала завершите текущий ивент (<color=#ce3f27>/sputnikstop</color>)!",
                ["PresetNotFound_Exeption"] = "Пресет {0} не найден в конфиге!",

                ["SuccessfullyLaunched"] = "Ивент <color=#738d43>успешно</color> запущен!",
                ["PreStartEvent"] = "{0} <color=#738d43>{1}</color> войдет в атмосферу через <color=#738d43>{2}</color>!",
                ["StartEvent"] = "{0} <color=#738d43>{1}</color> вошел в атмосферу!",
                ["Crash"] = "{0} <color=#738d43>Обломки</color> обнаружены в квадрате <color=#ce3f27>{1}</color>!",
                ["NeedUseCard"] = "{0} Используйте <color=#ce3f27>космическую карту</color> чтобы разблокировать ящик!",
                ["RemainTime"] = "{0} {1} будет уничтожен через <color=#ce3f27>{2}</color>!",
                ["EndEvent"] = "{0} Ивент <color=#ce3f27>окончен</color>!",
                ["GetSpaceCard"] = "{0} Вы получили <color=#ce3f27>космическую карту</color>!",

                ["EnterPVP"] = "{0} Вы <color=#ce3f27>вошли</color> в PVP зону, теперь другие игроки <color=#ce3f27>могут</color> наносить вам урон!",
                ["ExitPVP"] = "{0} Вы <color=#738d43>вышли</color> из PVP зоны, теперь другие игроки <color=#738d43>не могут</color> наносить вам урон!",
                ["GUI"] = "Спутник будет уничтожен через <color=#ce3f27>{0}</color>",

                ["SendEconomy"] = "{0} Вы <color=#738d43>получили</color> <color=#55aaff>{1}</color> баллов в экономику за прохождение ивента",

                ["Hours"] = "ч.",
                ["Minutes"] = "м.",
                ["Seconds"] = "с.",

                ["DamageDistance"] = "{0} Подойдите <color=#ce3f27>ближе</color>!",
            }, this, "ru");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["EventActive_Exeption"] = "This event is active now. Finish the current event! (<color=#ce3f27>/sputnikstop</color>)!",
                ["PresetNotFound_Exeption"] = "{0} preset was not found in the config!",
                ["ConfigurationNotFound_Exeption"] = "The event configuration <color=#ce3f27>could not</color> be found!",

                ["SuccessfullyLaunched"] = "The event has been <color=#738d43>successfully</color> launched!",
                ["PreStartEvent"] = "{0} <color=#738d43>{1}</color> will enter the atmosphere in <color=#738d43>{2}</color>!",
                ["StartEvent"] = "{0} <color=#738d43>{1}</color> entered the atmosphere!",
                ["Crash"] = "{0} <color=#738d43>Debris</color> detected at grid <color=#ce3f27>{1}</color>!",
                ["NeedUseCard"] = "{0} Use the <color=#ce3f27>space card</color> to unlock the crate!",
                ["RemainTime"] = "{0} {1} will be destroyed in <color=#ce3f27>{2}</color>!",
                ["EndEvent"] = "{0} The event is <color=#ce3f27>over</color>!",
                ["GetSpaceCard"] = "{0} You got a <color=#ce3f27>space card</color>!",
                ["Marker_EventOwner"] = "Event Owner: {0}",

                ["EnterPVP"] = "{0} You <color=#ce3f27>have entered</color> the PVP zone, now other players <color=#ce3f27>can damage</color> you!",
                ["ExitPVP"] = "{0} You <color=#738d43>have gone out</color> the PVP zone, now other players <color=#738d43>can’t damage</color> you!",
                ["GUI"] = "The sputnik will be destroyed in <color=#ce3f27>{0}</color>",

                ["SendEconomy"] = "{0} You <color=#738d43>have earned</color> <color=#55aaff>{1}</color> points in economics for participating in the event",

                ["Hours"] = "h.",
                ["Minutes"] = "m.",
                ["Seconds"] = "s.",

                ["DamageDistance"] = "{0} Come <color=#ce3f27>closer</color>!",

                ["EventStart_Log"] = "The event has begun! (Preset displayName - {0})",
                ["EventStop_Log"] = "The event is over!",

            }, this);
        }

        private static string GetMessage(string langKey, string userID) => _ins.lang.GetMessage(langKey, _ins, userID);

        private static string GetMessage(string langKey, string userID, params object[] args) => (args.Length == 0) ? GetMessage(langKey, userID) : string.Format(GetMessage(langKey, userID), args);
        #endregion Lang

        #region Config
        private PluginConfig _config;
        
        private void UpdateConfig()
        {
            PluginConfig defaultConfig = PluginConfig.DefaultConfig();

            if (_config.Version != Version)
            {
                bool isLootMigrated = false;
                
                if (_config.Version.Minor == 4)
                {
                    if (_config.Version.Patch == 0)
                    {
                        _config.SupportedPluginsConfig.BetterNpcConfig = new BetterNpcConfig
                        {
                            IsHeliNpc = false,
                        };
                    }

                    if (_config.Version.Patch <= 1)
                    {
                        foreach (SputnikDebrisConfig sputnikDebrisConfig in _config.SputnikDebrisConfigs)
                        {
                            sputnikDebrisConfig.ZoneConfig.IsColoredBorder = sputnikDebrisConfig.ZoneConfig.IsDome;
                            sputnikDebrisConfig.ZoneConfig.Brightness = 5;
                            sputnikDebrisConfig.ZoneConfig.BorderColor = 2;
                        }
                    }

                    if (_config.Version.Patch <= 3)
                    {
                        _config.SupportedPluginsConfig.IqDroneConfig = defaultConfig.SupportedPluginsConfig.IqDroneConfig;

                        foreach (SputnikDebrisConfig sputnikDebrisConfig in _config.SputnikDebrisConfigs)
                            sputnikDebrisConfig.IqDronePresetConfig = "lite";
                    }

                    if (_config.Version.Patch <= 5)
                    {
                        foreach (NpcConfig npcConfig in _config.NpcConfigs)
                        {
                            npcConfig.PresetName = npcConfig.DisplayName;
                        }

                        foreach (SputnikDebrisConfig sputnikDebrisConfig in _config.SputnikDebrisConfigs)
                            if (sputnikDebrisConfig.IqDronePresetConfig == null)
                                sputnikDebrisConfig.IqDronePresetConfig = "";
                    }

                    if (_config.Version.Patch <= 8)
                    {
                        foreach (HeliConfig heliConfig in _config.HeliConfigs)
                        {
                            heliConfig.CratesLifeTime = 1800;
                        }
                    }

                    if (_config.Version.Patch <= 9)
                    {
                        isLootMigrated = true;
                        LootMigrator.MigrateFromOldLootTables();
                    }
                    
                    _config.Version = new VersionNumber(1, 5, 0);
                }

                if (_config.Version.Minor == 5)
                {
                    if (_config.Version.Patch <= 4)
                    {
                        if (!isLootMigrated)
                            LootMigrator.MigrateFromLootManager();
                    }
                }

                _config.Version = Version;
            }

            UpdateConfigValues();
            SaveConfig();
        }
        
        private void UpdateConfigValues()
        {
            foreach (CrateConfig crateConfig in _config.CrateConfigs)
            {
                if (crateConfig.LootTableConfig == null)
                    crateConfig.LootTableConfig = LootController.GetDefaultLootTable();
                else
                    UpdateLootTableValues(crateConfig.LootTableConfig);
            }
            
            foreach (NpcConfig npcConfig in _config.NpcConfigs)
            {
                if (npcConfig.LootTableConfig == null)
                    npcConfig.LootTableConfig = LootController.GetDefaultLootTable();
                else
                    UpdateLootTableValues(npcConfig.LootTableConfig);
            }
            
            foreach (HeliConfig heliConfig in _config.HeliConfigs)
            {
                if (heliConfig.LootTableConfig == null)
                    heliConfig.LootTableConfig = LootController.GetDefaultLootTable();
                else
                    UpdateLootTableValues(heliConfig.LootTableConfig);
            }
        }

        private void UpdateLootTableValues(LootTableConfig lootTableConfig)
        {
            if (lootTableConfig.ItemsTable.MaxItemsAmount > lootTableConfig.ItemsTable.Items.Count) 
                lootTableConfig.ItemsTable.MaxItemsAmount = lootTableConfig.ItemsTable.Items.Count;
            
            lootTableConfig.ItemsTable.Items = lootTableConfig.ItemsTable.Items.OrderByQuickSort(x => x.Chance);
            
            for (int i = lootTableConfig.ItemsTable.Items.Count - 1; i >= 0; i--)
            {
                LootItemConfig lootItemConfig = lootTableConfig.ItemsTable.Items[i];
                
                if (!ItemManager.itemList.Any(x => x.shortname == lootItemConfig.Shortname))
                {
                    PrintWarning($"Unknown item removed! ({lootItemConfig.Shortname})");
                    lootTableConfig.ItemsTable.Items.RemoveAt(i);
                    continue;
                }

                lootItemConfig.Chance = Math.Clamp(lootItemConfig.Chance, 0f, 100f);
                
                if (lootItemConfig.MaxAmount < lootItemConfig.MinAmount) 
                    lootItemConfig.MaxAmount = lootItemConfig.MinAmount;
                
            }
            
            if (lootTableConfig.PrefabsTable.MaxPrefabsAmount > lootTableConfig.PrefabsTable.Prefabs.Count) 
                lootTableConfig.PrefabsTable.MaxPrefabsAmount = lootTableConfig.PrefabsTable.Prefabs.Count;
            
            lootTableConfig.PrefabsTable.Prefabs = lootTableConfig.PrefabsTable.Prefabs.OrderByQuickSort(x => x.Chance);

            for (int i = lootTableConfig.PrefabsTable.Prefabs.Count - 1; i >= 0; i--)
            {
                LootPrefabConfig lootPrefabConfig = lootTableConfig.PrefabsTable.Prefabs[i];
                
                lootPrefabConfig.Chance = Math.Clamp(lootPrefabConfig.Chance, 0f, 100f);
                
                if (lootPrefabConfig.MaxAmount < lootPrefabConfig.MinAmount) 
                    lootPrefabConfig.MaxAmount = lootPrefabConfig.MinAmount;
            }
        }

        protected override void LoadDefaultConfig() => _config = PluginConfig.DefaultConfig();

        protected override void LoadConfig()
        {
            base.LoadConfig();
            _config = Config.ReadObject<PluginConfig>();
            Config.WriteObject(_config, true);
        }

        protected override void SaveConfig() => Config.WriteObject(_config);

        private class MainConfig
        {
            [JsonProperty(En ? "Enable automatic event holding [true/false]" : "Включить автоматическое проведение ивента [true/false]")] 
            public bool IsAutoEvent { get; set; }
            
            [JsonProperty(En ? "Minimum time between events [sec]" : "Минимальное вермя между ивентами [sec]")] 
            public int MinTimeBetweenEvents { get; set; }
            
            [JsonProperty(En ? "Maximum time between events [sec]" : "Максимальное вермя между ивентами [sec]")] 
            public int MaxTimeBetweenEvents { get; set; }
            
            [JsonProperty(En ? "The time until the destruction of the debris after the looting of all the crates (0 - do not destroy) [sec]" : "Время до уничтожения обломков спутника после лутания всех ящиков (0 - не уничтожать) [sec]")] 
            public int DestroyAfterLootingTime { get; set; }
            
            [JsonProperty(En ? "Maximum range for damage to turrets/NPCs/mines (-1 - do not limit)" : "Максимальная дистанция для нанесения урона по турелям/нпс/минам (-1 - не ограничивать)")] 
            public int MaxGroundDamageDistance { get; set; }
            
            [JsonProperty(En ? "Maximum range for damage to heli (-1 - do not limit)" : "Максимальная дистанция для нанесения урона по вертолету (-1 - не ограничивать)")] 
            public int MaxHeliDamageDistance { get; set; }
            
            [JsonProperty(En ? "Enable logging of the start and end of the event? [true/false]" : "Включить логирование начала и окончания ивента? [true/false]")] 
            public bool EnableStartStopLogs { get; set; }
        }

        private class AggressiveConfig
        {
            [JsonProperty(En ? "Aggressive mode is active all the time" : "Агрессивный режим активен постоянно")] 
            public bool AggressiveSecurityMode { get; set; }
            
            [JsonProperty(En ? "The time for which the sputnik goes into aggressive mode after receiving damage" : "Время, на которое спутник переходит в агрессивных режим после получения урона")]
            public int AggressiveTime { get; set; }
            
            [JsonProperty(En ? "NPCs are constantly in aggressive mode" : "НПС постоянно находятся в агрессивном режиме")] 
            public bool NpcAlwaysAggressive { get; set; }
            
            [JsonProperty(En ? "Turrets are constantly in aggressive mode" : "Турели постоянно находятся в агрессивном режиме")] 
            public bool TurretAggressiveMode { get; set; }
            
            [JsonProperty(En ? "Helicopters are constantly in aggressive mode" : "Вертолеты постоянно находятся в агрессивном режиме")]
            public bool HeliAggressiveMode { get; set; }
            
            [JsonProperty(En ? "If one of the satellites is attacked, all the satellites will become aggressive (Useful when using settings 'Use the nearest drop points')" : "При атаке одного из спутников все спутники станут агрессивными (Полезно при использовании настройки 'Использовать ближайшие точки падения')")]
            public bool MakeAllSputniksAggressive { get; set; }
        }

        private class SpawnConfig
        {
            [JsonProperty(En ? "Disable spawn on beaches? [true/false]" : "Отключить спавн на пляжах? [true/false]")] 
            public bool IsBeachDisabled { get; set; }
            
            [JsonProperty(En ? "Disable spawn on rivers/lakes? [true/false]" : "Отключить спавн на реках/озерах? [true/false]")] 
            public bool IsRiverDisabled { get; set; }
            
            [JsonProperty(En ? "Disable spawn on monuments? [true/false]" : "Отключить спавн на монументах? [true/false]")] 
            public bool IsMonumentsDisabled { get; set; }
            
            [JsonProperty(En ? "Use the nearest drop points (satellites will fall next to each other)" : "Использовать ближайшие точки падения (спутники будут падать рядом друг с другом)")] 
            public bool IsNearestPoint { get; set; }
            
            [JsonProperty(En ? "Number of cached spawn points" : "Число кэшированных точек спавна")] 
            public int CountSpawnPoints { get; set; }
            
            [JsonProperty(En ? "Minimum distance between event points" : "Минимальное расстояние между точками падения")]
            public float MinPointDistance { get; set; }
        }

        private class FallingConfig
        {
            [JsonProperty(En ? "Falling Speed Multiplier" : "Множитель скорости падения")] 
            public float FallingSpeedScale { get; set; }
            
            [JsonProperty(En ? "Minimum height of the beginning of the fall" : "Минимальная высота начала падения")] 
            public float MinFallHeight { get; set; }
            
            [JsonProperty(En ? "Maximum height of the beginning of the fall" : "Максимальная высота начала падения")] 
            public float MaxFallHeight { get; set; }
            
            [JsonProperty(En ? "Minimum offset from the vertical axis when falling" : "Минимальное смещение от вертикальной оси при падении")]
            public float MinFallOffset { get; set; }
            
            [JsonProperty(En ? "Maximum offset from the vertical axis when falling" : "Максимальное смещение от вертикальной оси при падении")] 
            public float MaxFallOffset { get; set; }
            
            [JsonProperty(En ? "Number of effects when falling" : "Количество эффектов при падении")] 
            public int CountEffects { get; set; }
        }

        private class EventConfig
        {
            [JsonProperty(En ? "Preset name" : "Название пресета")]
            public string PresetName { get; set; }
            
            [JsonProperty(En ? "Display name" : "Отображаемое имя")] 
            public string DisplayName { get; set; }
            
            [JsonProperty(En ? "Duration [sec.]" : "Продолжительность [sec.]")] 
            public int EventTime { get; set; }
            
            [JsonProperty(En ? "Probability" : "Вероятность")] 
            public float Chance { get; set; }
            
            [JsonProperty(En ? "The minimum time after the server's wipe when this preset can be selected automatically [sec]" : "Минимальное время после вайпа сервера, когда этот пресет может быть выбран автоматически [sec]")] 
            public int MinTimeAfterWipe { get; set; }
            
            [JsonProperty(En ? "The maximum time after the server's wipe when this preset can be selected automatically [sec] (-1 - do not use this parameter)" : "Максимальное время после вайпа сервера, когда этот пресет может быть выбран автоматически [sec] (-1 - не использовать)")]
            public int MaxTimeAfterWipe { get; set; }
            
            [JsonProperty(En ? "Set of sputniks" : "Набор спутников")] 
            public List<string> FixedSputniksPresets { get; set; }
        }

        private class SputnikDebrisConfig
        {
            [JsonProperty(En ? "Preset name" : "Название пресета")] 
            public string PresetName { get; set; }
            
            [JsonProperty(En ? "Location preset (Data file)" : "Пресет локации (Data файл)")]
            public string LocationPreset { get; set; }
            
            [JsonProperty(En ? "NPC name - locations" : "Имя NPC - расположения")]
            public Dictionary<string, HashSet<string>> NpCs { get; set; }
            
            [JsonProperty(En ? "Turn on the card reader spawn? [true/false]" : "Включить спавн считывателя карт? [true/false]")] 
            public bool EnableCardReader { get; set; }
            
            [JsonProperty(En ? "Location of the card reader" : "Расположение считывателя карт")]
            public LocationConfig CardReaderLocation { get; set; }
            
            [JsonProperty(En ? "Heli preset name" : "Пресет вертолета")]
            public string HeliPresetName { get; set; }
            
            [JsonProperty(En ? "Turret preset - locations" : "Пресет турели - расположения")] 
            public Dictionary<string, HashSet<LocationConfig>> Turrets { get; set; }
            
            [JsonProperty(En ? "Locations of crates with automatic ground level detection (Crate preset - locations)" : "Расположения ящиков с автоматическим определением уровня земли (Пресет крейта - расположения)")] 
            public Dictionary<string, HashSet<LocationConfig>> GroundCrates { get; set; }
            
            [JsonProperty(En ? "Locations of crates without automatic ground level detection (Crate preset - locations)" : "Расположения ящиков без автоматического определения уровня земли (Пресет крейта - расположения)")] 
            public Dictionary<string, HashSet<LocationConfig>> Crates { get; set; }
            
            [JsonProperty(En ? "Locations of mines" : "Расположения мин")] 
            public HashSet<string> Mines { get; set; }
            
            [JsonProperty(En ? "Map marker setting" : "Настройка маркера на карте")] 
            public MarkerConfig MarkerConfig { get; set; }
            
            [JsonProperty(En ? "Zone Setting" : "Настройки зоны ивента")] 
            public ZoneConfig ZoneConfig { get; set; }
            
            [JsonProperty(En ? "Use custom spawn points? [true/false]" : "Использовать кастомные точки спавна? [true/false]")] 
            public bool UseCustomSpawnPoints { get; set; }
            
            [JsonProperty(En ? "Custom spawn points" : "Кастомные точки спавна")] 
            public List<string> CustomSpawnPoints { get; set; }
            
            [JsonProperty(En ? "IQDrone preset" : "Пресет IQDrone")] 
            public string IqDronePresetConfig { get; set; }
        }

        private class MarkerConfig
        {
            [JsonProperty(En ? "Do you use the Marker? [true/false]" : "Использовать ли маркер? [true/false]")] 
            public bool Enable { get; set; }
            
            [JsonProperty(En ? "Use a vending marker? [true/false]" : "Добавить маркер магазина? [true/false]")] 
            public bool IsShopMarker { get; set; }
            
            [JsonProperty(En ? "Use a circular marker? [true/false]" : "Добавить круговой маркер? [true/false]")] 
            public bool IsRingMarker { get; set; }
            
            [JsonProperty(En ? "Display name" : "Отображаемое имя")]
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

        private class CrateConfig
        {
            [JsonProperty(En ? "Preset Name" : "Название пресета")] 
            public string PresetName { get; set; }
            
            [JsonProperty("Prefab")]
            public string PrefabName { get; set; }
            
            [JsonProperty("Skin")]
            public ulong Skin { get; set; }
            
            [JsonProperty(En ? "Do you need to use a space card to open the box? [true/false]" : "Для открытия ящика требуется применить космическую карту? [true/false]")]
            public bool NeedSpaceCard { get; set; }
            
            [JsonProperty(En ? "Time to unlock the crates (LockedCrate) [sec.]" : "Время до открытия заблокированного ящика (LockedCrate) [sec.]")] 
            public float HackTime { get; set; }
            
            [JsonProperty(En ? "Loot" : "Лут")]
            public LootTableConfig LootTableConfig { get; set; }
            
            [JsonProperty(En ? "LootManager Preset" : "Пресет LootManager", NullValueHandling = NullValueHandling.Ignore)] 
            public string LootManagerPreset { get; set; }
            
            [JsonProperty(En ? "Own loot table" : "Собственная таблица предметов", NullValueHandling = NullValueHandling.Ignore)] 
            public LootTableConfigOld LootTableConfigOld { get; set; }
        }

        private class NpcConfig
        {
            [JsonProperty(En ? "Preset Name" : "Название пресета")] 
            public string PresetName { get; set; }
            
            [JsonProperty(En ? "Name" : "Название")]
            public string DisplayName { get; set; }
            
            [JsonProperty(En ? "Health" : "Кол-во ХП")]
            public float Health { get; set; }
            
            [JsonProperty(En ? "Wear items" : "Одежда")] 
            public HashSet<NpcWear> WearItems { get; set; }
            
            [JsonProperty(En ? "Belt items" : "Быстрые слоты")]
            public HashSet<NpcBelt> BeltItems { get; set; }
            
            [JsonProperty("Kit")] 
            public string Kit { get; set; }
            
            [JsonProperty(En ? "Roam Range" : "Дальность патрулирования местности")]
            public float RoamRange { get; set; }
            
            [JsonProperty(En ? "Chase Range" : "Дальность погони за целью")]
            public float ChaseRange { get; set; }
            
            [JsonProperty(En ? "Attack Range Multiplier" : "Множитель радиуса атаки")]
            public float AttackRangeMultiplier { get; set; }
            
            [JsonProperty(En ? "Sense Range" : "Радиус обнаружения цели")]
            public float SenseRange { get; set; }
            
            [JsonProperty(En ? "Memory duration [sec.]" : "Длительность памяти цели [sec.]")] 
            public float MemoryDuration { get; set; }
            
            [JsonProperty(En ? "Scale damage" : "Множитель урона")] 
            public float DamageScale { get; set; }
            
            [JsonProperty(En ? "Aim Cone Scale" : "Множитель разброса")] 
            public float AimConeScale { get; set; }
            
            [JsonProperty(En ? "Detect the target only in the NPC's viewing vision cone?" : "Обнаруживать цель только в углу обзора NPC? [true/false]")] 
            public bool CheckVisionCone { get; set; }
            
            [JsonProperty(En ? "Vision Cone" : "Угол обзора")] 
            public float VisionCone { get; set; }
            
            [JsonProperty(En ? "Speed" : "Скорость")]
            public float Speed { get; set; }
            
            [JsonProperty(En ? "Should remove the corpse?" : "Удалять труп?")]
            public bool DeleteCorpse { get; set; }
            
            [JsonProperty(En ? "Disable radio effects? [true/false]" : "Отключать эффекты рации? [true/false]")] 
            public bool DisableRadio { get; set; }
            
            [JsonProperty(En ? "Turret damage scale" : "Множитель урона от турелей")] 
            public float TurretDamageScale { get; set; }
            
            [JsonProperty(En ? "Loot" : "Лут")]
            public LootTableConfig LootTableConfig { get; set; }
            
            [JsonProperty(En ? "LootManager Preset" : "Пресет LootManager", NullValueHandling = NullValueHandling.Ignore)] 
            public string LootManagerPreset { get; set; }
            
            [JsonProperty(En ? "Own loot table" : "Собственная таблица лута", NullValueHandling = NullValueHandling.Ignore)] 
            public LootTableConfigOld LootTableConfigOld { get; set; }
        }

        private class NpcWear
        {
            [JsonProperty("ShortName")] 
            public string ShortName { get; set; }
            
            [JsonProperty(En ? "skinID (0 - default)" : "SkinID (0 - default)")] 
            public ulong SkinID { get; set; }
        }

        private class NpcBelt
        {
            [JsonProperty("ShortName")] 
            public string ShortName { get; set; }
            
            [JsonProperty(En ? "Amount" : "Кол-во")] 
            public int Amount { get; set; }
            
            [JsonProperty(En ? "skin (0 - default)" : "SkinID (0 - default)")]
            public ulong SkinID { get; set; }
            
            [JsonProperty(En ? "Mods" : "Модификации на оружие")]
            public HashSet<string> Mods { get; set; }
            
            [JsonProperty(En ? "Ammo" : "Патроны")] 
            public string Ammo { get; set; }
        }

        private class HeliConfig
        {
            [JsonProperty(En ? "Name" : "Название пресета")] 
            public string PresetName { get; set; }
            
            [JsonProperty("HP")] 
            public float Hp { get; set; }
            
            [JsonProperty(En ? "HP of the main rotor" : "HP главного винта")] 
            public float MainRotorHealth { get; set; }
            
            [JsonProperty(En ? "HP of tail rotor" : "HP хвостового винта")]
            public float RearRotorHealth { get; set; }
            
            [JsonProperty(En ? "Flying height" : "Высота полета")] 
            public float Height { get; set; }
            
            [JsonProperty(En ? "Bullet speed" : "Скорость пуль")] 
            public float BulletSpeed { get; set; }
            
            [JsonProperty(En ? "Bullet Damage" : "Урон пуль")] 
            public float BulletDamage { get; set; }
            
            [JsonProperty(En ? "The distance to which the helicopter can move away from the sputnik" : "Дистанция, на которую вертолет может отдаляться от спутника")] 
            public float Distance { get; set; }
            
            [JsonProperty(En ? "The time for which the helicopter can leave the satellite to attack the target [sec.]" : "Время, на которое верталет может покидать спутник для атаки цели [sec.]")] 
            public float OutsideTime { get; set; }
            
            [JsonProperty(En ? "Open the crates immediately after spawn" : "Открывать ящики сразу после спавна")] 
            public bool InstCrateOpen { get; set; }
            
            [JsonProperty(En ? "Lifetime of crates [sec]" : "Время жизни крейтов [sec]")] 
            public float CratesLifeTime { get; set; }
            
            [JsonProperty(En ? "Numbers of crates" : "Количество ящиков")] 
            public int CratesAmount { get; set; }
            
            [JsonProperty(En ? "Loot" : "Лут")]
            public LootTableConfig LootTableConfig { get; set; }
            
            [JsonProperty(En ? "LootManager Preset" : "Пресет LootManager", NullValueHandling = NullValueHandling.Ignore)] 
            public string LootManagerPreset { get; set; }
            
            [JsonProperty(En ? "Own loot table" : "Собственная таблица предметов", NullValueHandling = NullValueHandling.Ignore)] 
            public BaseLootTableConfigOld BaseLootTableConfigOld { get; set; }
        }

        private class TurretConfig
        {
            [JsonProperty(En ? "Preset Name" : "Название пресета")] 
            public string PresetName { get; set; }
            
            [JsonProperty(En ? "Choose the spawn height automatically?" : "Выбирать высоту спавна автоматически?")]
            public bool AutoHeight { get; set; }
            
            [JsonProperty(En ? "Health" : "Кол-во ХП")] 
            public float Hp { get; set; }
            
            [JsonProperty(En ? "Weapon ShortName" : "ShortName оружия")] 
            public string ShortNameWeapon { get; set; }
            
            [JsonProperty(En ? "Ammo ShortName" : "ShortName патронов")]
            public string ShortNameAmmo { get; set; }
            
            [JsonProperty(En ? "Number of ammo" : "Кол-во патронов")] 
            public int CountAmmo { get; set; }
            
            [JsonProperty(En ? "Target detection range (0 - do not change)" : "Дальность обнаружения цели (0 - не изменять)")] 
            public float TargetDetectionRange { get; set; }
            
            [JsonProperty(En ? "Target loss range (0 - do not change)" : "Дальность потери цели (0 - не изменять)")] 
            public float TargetLossRange { get; set; }
        }

        private class ZoneConfig
        {
            [JsonProperty(En ? "Create a PVP zone? (only for those who use the TruePVE plugin)[true/false]" : "Создавать зону PVP в зоне проведения ивента? (только для тех, кто использует плагин TruePVE) [true/false]")]
            public bool IsPvpZone { get; set; }
            
            [JsonProperty(En ? "Use the dome? [true/false]" : "Использовать ли купол? [true/false]")] 
            public bool IsDome { get; set; }
            
            [JsonProperty(En ? "Darkening the dome" : "Затемнение купола")] 
            public int Darkening { get; set; }
            
            [JsonProperty(En ? "Use a colored border? [true/false]" : "Использовать цветную границу? [true/false]")]
            public bool IsColoredBorder { get; set; }
            
            [JsonProperty(En ? "Border color (0 - blue, 1 - green, 2 - purple, 3 - red)" : "Цвет границы (0 - синий, 1 - зеленый, 2 - фиолетовый, 3 - красный)")] 
            public int BorderColor { get; set; }
            
            [JsonProperty(En ? "Brightness of the color border" : "Яркость цветной границы")]
            public int Brightness { get; set; }
            
            [JsonProperty(En ? "Radius" : "Радиус")] 
            public float Radius { get; set; }
            
            [JsonProperty(En ? "Radiation power" : "Сила радиации")] 
            public float Radiation { get; set; }
        }

        private class GUIConfig
        {
            [JsonProperty(En ? "Use the Countdown GUI? [true/false]" : "Использовать ли GUI обратного отсчета? [true/false]")] 
            public bool IsEnable { get; set; }
            
            [JsonProperty(En ? "Vertical offset" : "Смещение по вертикали")]
            public int OffsetMinY { get; set; }
        }

        private class NotifyConfig
        {
            [JsonProperty(En ? "The time from the notification to the start of the event [sec]" : "Время от оповещения до начала ивента [sec]")] 
            public int PreStartTime { get; set; }
            
            [JsonProperty(En ? "Use a Chat? [true/false]" : "Использовать ли чат? [true/false]")]
            public bool IsChatEnable { get; set; }
            
            [JsonProperty(En ? "The time until the end of the event, when a message is displayed about the time until the end of the event [sec]" : "Время до конца ивента, когда выводится сообщение о сокром окончании ивента [sec]")] 
            public HashSet<int> TimeNotifications { get; set; }
            
            [JsonProperty(En ? "Facepunch Game Tips setting" : "Настройка сообщений Facepunch Game Tip")]
            public GameTipConfig GameTipConfig { get; set; }

        }

        private class GameTipConfig
        {
            [JsonProperty(En ? "Use Facepunch Game Tips (notification bar above hotbar)? [true/false]" : "Использовать ли Facepunch Game Tip (оповещения над слотами быстрого доступа игрока)? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Style (0 - Blue Normal, 1 - Red Normal, 2 - Blue Long, 3 - Blue Short, 4 - Server Event)" : "Стиль (0 - Blue Normal, 1 - Red Normal, 2 - Blue Long, 3 - Blue Short, 4 - Server Event)")] 
            public int Style { get; set; }
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

        private class LocationConfig
        {
            [JsonProperty(En ? "Position" : "Позиция")] 
            public string Position { get; set; }
            
            [JsonProperty(En ? "Rotation" : "Вращение")] 
            public string Rotation { get; set; }
        }
        
        private class LootTableConfig
        {
            [JsonProperty(En ? "Allow AlphaLoot to Modify Loot" : "Разрешить плагину AlphaLoot изменять лут")]
            public bool IsAlphaLoot { get; set; }
            
            [JsonProperty(En ? "Use AlphaLoot Preset with This Name" : "Использовать пресет AlphaLoot с этим названием")]
            public string AlphaLootPreset { get; set; }
            
            [JsonProperty(En ? "Allow LoottableStacksizeGUI to Modify Loot" : "Разрешить плагину LoottableStacksizeGUI изменять лут")]
            public bool IsLoottablePlugin { get; set; }
            
            [JsonProperty(En ? "Use LoottableStacksizeGUI Preset with This Name" : "Использовать пресет LoottableStacksizeGUI с этим названием")]
            public string LoottablePreset { get; set; }
            
            [JsonProperty(En ? "Allow CustomLoot to Modify Loot" : "Разрешить плагину CustomLoot изменять лут")]
            public bool IsCustomLootPlugin { get; set; }
            
            [JsonProperty(En ? "Use CustomLoot Preset with This Name" : "Использовать пресет CustomLoot с этим названием")]
            public string CustomLootPreset { get; set; }

            
            [JsonProperty(En ? "Clear the container before adding items/prefabs" : "Очистить контейнер перед добавлением предметов/префабов")]
            public bool ClearDefaultLoot { get; set; }
            
            [JsonProperty(En ? "Prefabs Table" : "Таблица префабов")]
            public PrefabsLootTableConfig PrefabsTable { get; set; }
            
            [JsonProperty(En ? "Items Table" : "Таблица предметов")]
            public ItemsLootTableConfig ItemsTable { get; set; }
        }

        private class ItemsLootTableConfig
        {
            [JsonProperty(En ? "Enabled" : "Включить")]
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Use Chance-Based Spawn (Ignore Min/Max Unique Items)" : "Выпадение по шансу (игнорировать Min/Max разных предметов)")]
            public bool DisableMinMax { get; set; }
                
            [JsonProperty(En ? "Minimum Unique Items" : "Минимум разных предметов")]
            public int MinItemsAmount { get; set; }

            [JsonProperty(En ? "Maximum Unique Items" : "Максимум разных предметов")]
            public int MaxItemsAmount { get; set; }
            
            [JsonProperty(En ? "Item Pool" : "Пул предметов")]
            public List<LootItemConfig> Items { get; set; }
        }
        
        private class PrefabsLootTableConfig
        {
            [JsonProperty(En ? "Enabled" : "Включить")]
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Minimum Unique Prefabs" : "Минимум разных префабов")]
            public int MinPrefabsAmount { get; set; }

            [JsonProperty(En ? "Maximum Unique Prefabs" : "Максимум разных префабов")]
            public int MaxPrefabsAmount { get; set; }
            
            [JsonProperty(En ? "Prefab Pool" : "Пул префабов")]
            public List<LootPrefabConfig> Prefabs { get; set; }
        }
        
        private class LootItemConfig : LootElementChanceConfig
        {
            [JsonProperty("Shortname")]
            public string Shortname { get; set; }
            
            [JsonProperty("SkinID")]
            public ulong Skin { get; set; }
            
            [JsonProperty(En ? "Display Name (empty - default)" : "Отображаемое название (оставить пустым - стандартное)")]
            public string DisplayName { get; set; }
            
            [JsonProperty(En ? "Owner Display Name" : "Имя владельца предмета")]
            public string OwnerName { get; set; }
            
            [JsonProperty(En ? "Is Blueprint" : "Это чертеж")]
            public bool IsBlueprint { get; set; }
            
            [JsonProperty(En ? "Genomes" : "Геномы")]
            public List<string> Genomes { get; set; }
        }

        private class LootPrefabConfig : LootElementChanceConfig
        {
            [JsonProperty(En ? "Prefab" : "Префаб")]
            public string PrefabName { get; set; }
        }

        private class LootElementChanceConfig
        {
            [JsonProperty(En ? "Chance [0.0-100.0]" : "Шанс [0.0-100.0]")]
            public float Chance { get; set; }
            
            [JsonProperty(En ? "Minimum Amount" : "Минимальное количество")]
            public int MinAmount { get; set; }
            
            [JsonProperty(En ? "Maximum Amount" : "Максимальное количество")]
            public int MaxAmount { get; set; }
        }
        

        private class LootTableConfigOld : BaseLootTableConfigOld
        {
            [JsonProperty(En ? "Allow the AlphaLoot plugin to spawn items in this crate" : "Разрешить плагину AlphaLoot спавнить предметы в этом ящике")] 
            public bool IsAlphaLoot { get; set; }
            
            [JsonProperty(En ? "The name of the loot preset for AlphaLoot" : "Название пресета лута AlphaLoot")] 
            public string AlphaLootPresetName { get; set; }
            
            [JsonProperty(En ? "Allow the CustomLoot plugin to spawn items in this crate" : "Разрешить плагину CustomLoot спавнить предметы в этом ящике")] 
            public bool IsCustomLoot { get; set; }
            
            [JsonProperty(En ? "Allow the Loot Table Stacksize GUI plugin to spawn items in this crate" : "Разрешить плагину Loot Table Stacksize GUI спавнить предметы в этом ящике")] 
            public bool IsLootTablePlugin { get; set; }
        }

        private class BaseLootTableConfigOld
        {
            [JsonProperty(En ? "Clear the standard content of the crate" : "Отчистить стандартное содержимое крейта")] 
            public bool ClearDefaultItemList { get; set; }
            
            [JsonProperty(En ? "Setting up loot from the loot table" : "Настройка лута из лутовой таблицы")]
            public PrefabLootTableConfigsOld PrefabConfigsOld { get; set; }
            
            [JsonProperty(En ? "Enable spawn of items from the list" : "Включить спавн предметов из списка")] 
            public bool IsRandomItemsEnable { get; set; }
            
            [JsonProperty(En ? "Minimum numbers of items" : "Минимальное кол-во элементов")] 
            public int MinItemsAmount { get; set; }
            
            [JsonProperty(En ? "Maximum numbers of items" : "Максимальное кол-во элементов")]
            public int MaxItemsAmount { get; set; }
            
            [JsonProperty(En ? "List of items" : "Список предметов")]
            public List<LootItemConfigOld> Items { get; set; }
        }

        private class PrefabLootTableConfigsOld
        {
            [JsonProperty(En ? "Enable spawn loot from prefabs" : "Включить спавн лута из префабов")]
            public bool IsEnable { get; set; }
            
            [JsonProperty(En ? "List of prefabs (one is randomly selected)" : "Список префабов (выбирается один рандомно)")]
            public List<PrefabConfigOld> Prefabs { get; set; }
        }

        private class PrefabConfigOld
        {
            [JsonProperty(En ? "Prefab displayName" : "Название префаба")] 
            public string PrefabName { get; set; }
            
            [JsonProperty(En ? "Minimum Loot multiplier" : "Минимальный множитель лута")] 
            public int MinLootScale { get; set; }
            
            [JsonProperty(En ? "Maximum Loot multiplier" : "Максимальный множитель лута")] 
            public int MaxLootScale { get; set; }
        }

        private class LootItemConfigOld
        {
            [JsonProperty("ShortName")] 
            public string Shortname { get; set; }
            
            [JsonProperty(En ? "Minimum" : "Минимальное кол-во")] 
            public int MinAmount { get; set; }
            
            [JsonProperty(En ? "Maximum" : "Максимальное кол-во")] 
            public int MaxAmount { get; set; }
            
            [JsonProperty(En ? "Chance [0.0-100.0]" : "Шанс выпадения предмета [0.0-100.0]")]
            public float Chance { get; set; }
            
            [JsonProperty(En ? "Is this a blueprint? [true/false]" : "Это чертеж? [true/false]")] 
            public bool IsBlueprint { get; set; }
            
            [JsonProperty("SkinID (0 - default)")]
            public ulong Skin { get; set; }
            
            [JsonProperty(En ? "Name (empty - default)" : "Название (empty - default)")]
            public string Name { get; set; }
            
            [JsonProperty(En ? "List of genomes" : "Список геномов")]
            public List<string> Genomes { get; set; }
        }
        
        

        private class CustomCardConfig
        {
            [JsonProperty("ShortName")]
            public string ShortName { get; set; }
            
            [JsonProperty(En ? "Name (empty - default)" : "Название (empty - default)")]
            public string Name { get; set; }
            
            [JsonProperty("SkinID (0 - default)")] 
            public ulong SkinID { get; set; }
            
            [JsonProperty(En ? "Multiplier of card health loss when using" : "Множитель потери прочности карты при использовании")] 
            public float HealthLossScale { get; set; }
            
            [JsonProperty(En ? "Enable spawn in crates" : "Включить спавн в ящиках")]
            public bool EnableSpawnInDefaultCrates { get; set; }
            
            [JsonProperty(En ? "Setting up spawn in crates (prefab - probability)" : "Настройка спавна в ящиках (префаб - вероятность)")]
            public Dictionary<string, float> SpawnSetting { get; set; }
        }

        private class SupportedPluginsConfig
        {
            [JsonProperty(En ? "PVE Mode Setting" : "Настройка PVE Mode")] 
            public PveModeConfig PveMode { get; set; }
            
            [JsonProperty(En ? "Economy Setting" : "Настройка экономики")] 
            public EconomyConfig EconomicsConfig { get; set; }
            
            [JsonProperty(En ? "GUI Announcements setting" : "Настройка GUI Announcements")] 
            public GUIAnnouncementsConfig GUIAnnouncementsConfig { get; set; }
            
            [JsonProperty(En ? "Notify setting" : "Настройка Notify")] 
            public NotifyPluginConfig NotifyPluginConfig { get; set; }
            
            [JsonProperty(En ? "DiscordMessages setting" : "Настройка DiscordMessages")] 
            public DiscordConfig DiscordMessagesConfig { get; set; }
            
            [JsonProperty(En ? "ZoneManager setting" : "Настройка ZoneManager")]
            public ZoneManagerConfig ZoneManager { get; set; }
            
            [JsonProperty(En ? "RaidableBases setting" : "Настройка RaidableBases")]
            public RaidableBasesConfig RaidableBases { get; set; }
            
            [JsonProperty(En ? "RestoreUponDeath setting" : "Настройка RestoreUponDeath")]
            public RestoreUponDeathConfig RestoreUponDeath { get; set; }
            
            [JsonProperty(En ? "BetterNpc setting" : "Настройка BetterNpc")] 
            public BetterNpcConfig BetterNpcConfig { get; set; }
            
            [JsonProperty(En ? "IQDronePatrol setting" : "Настройка IQDronePatrol")]
            public IqDroneConfig IqDroneConfig { get; set; }
        }

        private class PveModeConfig
        {
            [JsonProperty(En ? "Use the PVE mode of the plugin? [true/false]" : "Использовать PVE режим работы плагина? [true/false]")] 
            public bool Enable { get; set; }
            
            [JsonProperty(En ? "Show the displayName of the event owner on a marker on the map? [true/false]" : "Отображать имя владелца ивента на маркере на карте? [true/false]")] 
            public bool ShowEventOwnerNameOnMap { get; set; }
            
            [JsonProperty(En ? "The amount of damage that the player has to do to become the Event Owner" : "Кол-во урона, которое должен нанести игрок, чтобы стать владельцем ивента")]
            public float Damage { get; set; }
            
            [JsonProperty(En ? "Damage coefficients for calculate to become the Event Owner." : "Коэффициенты урона для подсчета, чтобы стать владельцем события.")] 
            public Dictionary<string, float> ScaleDamage { get; set; }
            
            [JsonProperty(En ? "Can the non-owner of the event loot the crates? [true/false]" : "Может ли не владелец ивента грабить ящики? [true/false]")] 
            public bool LootCrate { get; set; }
            
            [JsonProperty(En ? "Can the non-owner of the event hack locked crates? [true/false]" : "Может ли не владелец ивента взламывать заблокированные ящики? [true/false]")] 
            public bool HackCrate { get; set; }
            
            [JsonProperty(En ? "Can the non-owner of the event loot NPC corpses? [true/false]" : "Может ли не владелец ивента грабить трупы NPC? [true/false]")] 
            public bool LootNpc { get; set; }
            
            [JsonProperty(En ? "Can the non-owner of the event deal damage to the NPC? [true/false]" : "Может ли не владелец ивента наносить урон по NPC? [true/false]")] 
            public bool DamageNpc { get; set; }
            
            [JsonProperty(En ? "Can an Npc attack a non-owner of the event? [true/false]" : "Может ли Npc атаковать не владельца ивента? [true/false]")] 
            public bool TargetNpc { get; set; }
            
            [JsonProperty(En ? "Can Helicopter attack a non-owner of the event? [true/false]" : "Может ли Вертолет атаковать не владельца ивента? [true/false]")] 
            public bool TargetHeli { get; set; }
            
            [JsonProperty(En ? "Can the non-owner of the event do damage to Helicopter? [true/false]" : "Может ли не владелец ивента наносить урон по Вертолету? [true/false]")] 
            public bool DamageHeli { get; set; }
            
            [JsonProperty(En ? "Can Turret attack a non-owner of the event? [true/false]" : "Может ли Турель атаковать не владельца ивента? [true/false]")] 
            public bool TargetTurret { get; set; }
            
            [JsonProperty(En ? "Can the non-owner of the event do damage to Turret? [true/false]" : "Может ли не владелец ивента наносить урон по Турелям? [true/false]")]
            public bool DamageTurret { get; set; }
            
            [JsonProperty(En ? "Allow the non-owner of the event to enter the event zone? [true/false]" : "Разрешать входить внутрь зоны ивента не владельцу ивента? [true/false]")] 
            public bool CanEnter { get; set; }
            
            [JsonProperty(En ? "Allow a player who has an active cooldown of the Event Owner to enter the event zone? [true/false]" : "Разрешать входить внутрь зоны ивента игроку, у которого активен кулдаун на получение статуса владельца ивента? [true/false]")] 
            public bool CanEnterCooldownPlayer { get; set; }
            
            [JsonProperty(En ? "The time that the Event Owner may not be inside the event zone [sec.]" : "Время, которое владелец ивента может не находиться внутри зоны ивента [сек.]")] 
            public int TimeExitOwner { get; set; }
            
            [JsonProperty(En ? "The time until the end of Event Owner status when it is necessary to warn the player [sec.]" : "Время таймера до окончания действия статуса владельца ивента, когда необходимо предупредить игрока [сек.]")] 
            public int AlertTime { get; set; }
            
            [JsonProperty(En ? "The time that the player can`t become the Event Owner, after the end of the event and the player was its owner [sec.]" : "Время, которое игрок не сможет стать владельцем ивента, после того как ивент окончен и игрок был его владельцем [sec.]")]
            public double CooldownOwner { get; set; }
        }

        private class EconomyConfig
        {
            [JsonProperty(En ? "Enable economy" : "Включить экономику?")] 
            public bool Enable { get; set; }
            
            [JsonProperty(En ? "Which economy plugins do you want to use? (Economics, Server Rewards, IQEconomic)" : "Какие плагины экономики вы хотите использовать? (Economics, Server Rewards, IQEconomic)")]
            public HashSet<string> Plugins { get; set; }
            
            [JsonProperty(En ? "The minimum value that a player must collect to get points for the economy" : "Минимальное значение, которое игрок должен заработать, чтобы получить баллы за экономику")] 
            public double MinEconomyPoint { get; set; }
            
            [JsonProperty(En ? "The minimum value that a winner must collect to make the commands work" : "Минимальное значение, которое победитель должен заработать, чтобы сработали команды")] 
            public double MinCommandPoint { get; set; }
            
            [JsonProperty(En ? "Looting of crates" : "Ограбление ящиков")] 
            public Dictionary<string, double> Crates { get; set; }
            
            [JsonProperty(En ? "Killing an NPC" : "Убийство NPC")] 
            public double NpcPoint { get; set; }
            
            [JsonProperty(En ? "Killing an Turret" : "Уничтожение Турели")] 
            public double TurretPoint { get; set; }
            
            [JsonProperty(En ? "Killing an Heli" : "Уничтожение Вертолета")] 
            public double HeliPoint { get; set; }
            
            [JsonProperty(En ? "Hacking a locked crate" : "Взлом заблокированного ящика")]
            public double HackCratePoint { get; set; }
            
            [JsonProperty(En ? "List of commands that are executed in the console at the end of the event ({steamid} - the player who collected the highest number of points)" : "Список команд, которые выполняются в консоли по окончанию ивента ({steamid} - игрок, который набрал наибольшее кол-во баллов)")]
            public HashSet<string> Commands { get; set; }
        }

        private class GUIAnnouncementsConfig
        {
            [JsonProperty(En ? "Do you use the GUI Announcements? [true/false]" : "Использовать ли GUI Announcements? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Banner color" : "Цвет баннера")] 
            public string BannerColor { get; set; }
            
            [JsonProperty(En ? "Text color" : "Цвет текста")] 
            public string TextColor { get; set; }
            
            [JsonProperty(En ? "Adjust Vertical Position" : "Отступ от верхнего края")] 
            public float APIAdjustVPosition { get; set; }
        }

        private class NotifyPluginConfig
        {
            [JsonProperty(En ? "Do you use the Notify? [true/false]" : "Использовать ли Notify? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty(En ? "Type" : "Тип")] 
            public int Type { get; set; }
        }

        private class DiscordConfig
        {
            [JsonProperty(En ? "Do you use the Discord? [true/false]" : "Использовать ли Discord? [true/false]")] 
            public bool IsEnabled { get; set; }
            
            [JsonProperty("Webhook URL")] 
            public string WebhookUrl { get; set; }
            
            [JsonProperty(En ? "Embed Color (DECIMAL)" : "Цвет полосы (DECIMAL)")]
            public int EmbedColor { get; set; }
            
            [JsonProperty(En ? "Keys of required messages" : "Ключи необходимых сообщений")] 
            public HashSet<string> Keys { get; set; }
        }

        private class ZoneManagerConfig
        {
            [JsonProperty(En ? "Do you use the ZoneManager? [true/false]" : "Использовать ли ZoneManager? [true/false]")]
            public bool Enable { get; set; }
            
            [JsonProperty(En ? "List of zone flags that block spawn" : "Список флагов, при наличии в зоне которого спутник не будет спавниться")]
            public HashSet<string> BlockFlags { get; set; }
            
            [JsonProperty(En ? "List of zone IDs that block spawn" : "Список ID зон, которые запретят спавн спутника")] 
            public HashSet<string> BlockIDs { get; set; }
        }

        private class RaidableBasesConfig
        {
            [JsonProperty(En ? "Do you use the RaidableBases? [true/false]" : "Использовать ли RaidableBases? [true/false]")] 
            public bool Enable { get; set; }
        }

        private class RestoreUponDeathConfig
        {
            [JsonProperty(En ? "Prevent the actions of the RestoreUponDeath plugin in the event zone? [true/false]" : "Запрещать работу плагина RestoreUponDeath в зоне действия ивента? [true/false]")]
            public bool DisableRestore { get; set; }
        }

        private class BetterNpcConfig
        {
            [JsonProperty(En ? "Allow Npc spawn after destroying Heli" : "Разрешить спавн Npc после уничтожения Вертолета")]
            public bool IsHeliNpc { get; set; }
        }

        private class IqDroneConfig
        {
            [JsonProperty(En ? "Allow drone spawn?" : "Разрешить спавн дронов")] 
            public bool IsDroneEnable { get; set; }
            
            [JsonProperty(En ? "Presets" : "Пресеты")]
            public HashSet<IqDronePreset> DronePresets { get; set; }
        }

        private class IqDronePreset
        {
            [JsonProperty(En ? "Preset Name" : "Название пресета")]
            public string PresetName { get; set; }
            
            [JsonProperty(En ? "Number of drones" : "Количество дронов")] 
            public int DroneNumber { get; set; }
            
            [JsonProperty(En ? "The number of drones that can be targeted at the same time" : "Количество дронов, которые могут таргетится одновременно")] 
            public int TargetDroneNumber { get; set; }
            
            [JsonProperty(En ? "Drone Key - probability [0 - 100]" : "Ключ дрона - вероятность [0 - 100]")] 
            public Dictionary<string, int> DroneKeys { get; set; }
        }

        private class PluginConfig
        {
            [JsonProperty(En ? "Version" : "Версия")] 
            public VersionNumber Version { get; set; }
            
            [JsonProperty(En ? "Prefix of chat messages" : "Префикс в чате")] 
            public string Prefix { get; set; }
            
            [JsonProperty(En ? "Main Setting" : "Основные настройки")] 
            public MainConfig MainConfig { get; set; }
            
            [JsonProperty(En ? "Settings of the event aggression" : "Настройка агрессивности ивента")] 
            public AggressiveConfig AggressiveConfig { get; set; }
            
            [JsonProperty(En ? "Settings of the falling sputnik" : "Настройка падения спутника")] 
            public FallingConfig FallingConfig { get; set; }
            
            [JsonProperty(En ? "Settings of the spawning sputnik debris" : "Настройка спавна обломков спутника")] 
            public SpawnConfig SpawnConfig { get; set; }
            
            [JsonProperty(En ? "Event presets" : "Пресеты ивента")] 
            public HashSet<EventConfig> EventConfigs { get; set; }
            
            [JsonProperty(En ? "Sputnik Debris Presets" : "Пресеты обломков спутников")]
            public HashSet<SputnikDebrisConfig> SputnikDebrisConfigs { get; set; }
            
            [JsonProperty(En ? "Space card setting" : "Настройка космической карты")] 
            public CustomCardConfig CustomCardConfig { get; set; }
            
            [JsonProperty(En ? "Crate presets" : "Пресеты ящиков")]
            public HashSet<CrateConfig> CrateConfigs { get; set; }
            
            [JsonProperty(En ? "NPC presets" : "Пресеты NPC")]
            public HashSet<NpcConfig> NpcConfigs { get; set; }
            
            [JsonProperty(En ? "Heli presets" : "Пресеты вертолетов")]
            public HashSet<HeliConfig> HeliConfigs { get; set; }
            
            [JsonProperty(En ? "Turrets presets" : "Пресеты турелей")]
            public HashSet<TurretConfig> TurretConfigs { get; set; }
            
            [JsonProperty(En ? "Notification Settings" : "Настройки уведомлений")] 
            public NotifyConfig NotifyConfig { get; set; }
            
            [JsonProperty(En ? "GUI Setting" : "Настройки GUI")] 
            public GUIConfig GUIConfig { get; set; }
            
            [JsonProperty(En ? "Supported Plugins" : "Поддерживаемые плагины")]
            public SupportedPluginsConfig SupportedPluginsConfig { get; set; }

            public static PluginConfig DefaultConfig()
            {
                return new PluginConfig
                {
                    Version = new VersionNumber(1, 5, 6),
                    Prefix = "[Sputnik]",
                    MainConfig = new MainConfig
                    {
                        IsAutoEvent = true,
                        MinTimeBetweenEvents = 7200,
                        MaxTimeBetweenEvents = 7200,
                        DestroyAfterLootingTime = 300,
                        MaxGroundDamageDistance = 100,
                        MaxHeliDamageDistance = 250,
                        EnableStartStopLogs = false,
                    },
                    AggressiveConfig = new AggressiveConfig
                    {
                        AggressiveSecurityMode = false,
                        AggressiveTime = 120,
                        NpcAlwaysAggressive = true,
                        TurretAggressiveMode = true,
                        HeliAggressiveMode = false,
                    },
                    FallingConfig = new FallingConfig()
                    {
                        FallingSpeedScale = 1,
                        MinFallHeight = 500,
                        MaxFallHeight = 1000,
                        MinFallOffset = 200,
                        MaxFallOffset = 300,
                        CountEffects = 10
                    },
                    SpawnConfig = new SpawnConfig
                    {
                        IsBeachDisabled = true,
                        IsRiverDisabled = true,
                        IsMonumentsDisabled = true,
                        IsNearestPoint = true,
                        CountSpawnPoints = 25,
                        MinPointDistance = 50,
                    },
                    EventConfigs = new HashSet<EventConfig>
                    {
                        new EventConfig
                        {
                            PresetName = "station",
                            DisplayName = En ? "Fragment of the space station" : "Обломок космической станции",
                            EventTime = 3600,
                            Chance = 20,
                            MinTimeAfterWipe = 0,
                            MaxTimeAfterWipe = 172800,
                            FixedSputniksPresets = new List<string>
                            {
                                "debris_2",
                                "debris_3",
                                "debris_4",
                            }
                        },
                        new EventConfig
                        {
                            PresetName = "sputnik",
                            DisplayName = En ? "Sputnik" : "Спутник",
                            EventTime = 3600,
                            Chance = 30,
                            MinTimeAfterWipe = 0,
                            MaxTimeAfterWipe = -1,
                            FixedSputniksPresets = new List<string>
                            {
                                "sputnik_1",
                                "debris_1",
                            }
                        },
                        new EventConfig
                        {
                            PresetName = "spaceship",
                            DisplayName = En ? "Spaceship" : "Космический корабль",
                            EventTime = 3600,
                            Chance = 30,
                            MinTimeAfterWipe = 0,
                            MaxTimeAfterWipe = -1,
                            FixedSputniksPresets = new List<string>
                            {
                                "sputnik_1",
                                "debris_1",
                                "debris_4",
                            }
                        },
                        new EventConfig
                        {
                            PresetName = "big_sputnik",
                            DisplayName = En ? "Huge sputnik" : "Огромный спутник",
                            EventTime = 3600,
                            Chance = 20,
                            MinTimeAfterWipe = 0,
                            MaxTimeAfterWipe = -1,
                            FixedSputniksPresets = new List<string>
                            {
                                "sputnik_1",
                                "debris_1",
                                "debris_3",
                                "debris_4",
                            }
                        }
                    },
                    SputnikDebrisConfigs = new HashSet<SputnikDebrisConfig>
                    {
                        new SputnikDebrisConfig
                        {
                            PresetName = "sputnik_1",
                            LocationPreset = "sputnik_1",
                            HeliPresetName = "heli_1",
                            Turrets = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["turret_ak"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(-0.103, 0, 4.888)",
                                        Rotation = "(0, 88, 0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(-3.922, 0, 7.955)",
                                        Rotation = "(0, 0, 0)"
                                    }
                                }
                            },
                            EnableCardReader = true,
                            CardReaderLocation = new LocationConfig
                            {
                                Position = "(-3.69, 0.055, 7.180)",
                                Rotation = "(341.285, 38.870, 0.638)"
                            },
                            NpCs = new Dictionary<string, HashSet<string>>
                            {
                                ["cosmonaut"] = new HashSet<string>
                                {
                                    "(4, 0, -1)",
                                    "(5, 0, 2.5)",
                                    "(1.5, 0, -1.5)",
                                    "(0.5, 0, 3.3)",
                                    "(-2.1, 0, 1.1)",
                                    "(-0.15, 0, 5.6)",
                                    "(-6.7, 0, 3.3)",
                                    "(-3.5, 0, 7.8)"
                                }
                            },
                            GroundCrates = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["crateelite_default"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(0.0, 0.0, 1.9)",
                                        Rotation = "(0.0, 0.0, 0.0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(-2.9, 0.0, 2.0)",
                                        Rotation = "(0.0, 115.9, 0.0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(2.5, 0.0, -3.2)",
                                        Rotation = "(0.0, 0.0, 0.0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(-4.0, 0.0, -1.6)",
                                        Rotation = "(0.0, 58.8, 0.0)"
                                    }
                                }
                            },
                            Crates = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["chinooklockedcrate_spacecard"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(-3.279, 0.487, 6.231)",
                                        Rotation = "(340.056, 38.203, 0)"
                                    }
                                },
                            },
                            Mines = new HashSet<string>
                            {
                                "(1.7, 0.0, 4.3)",
                                "(0.3, 0.0, 4.3)",
                                "(2.6, 0.0, 1.0)",
                                "(0.7, 0.0, 2.5)",
                                "(-7.2, 0.0, 6.6)",
                                "(-7.2, 0.0, 4.2)",
                                "(-0.9, 0.0, 6.1)",
                                "(-2.1, 0.0, 7.1)",
                                "(-3.6, 0.0, 8.2)",
                                "(-5.8, 0.0, 6.6)",
                                "(-1.7, 0.0, 0.5)",
                                "(-3.9, 0.0, 0.5)",
                                "(-4.4, 0.0, 2.3)",
                                "(-4.4, 0.0, 4.2)",
                                "(2.6, 0.0, -1.4)",
                                "(4.9, 0.0, -4.0)",
                                "(2.1, 0.0, -6.3)",
                                "(2.0, 0.0, -8.2)",
                                "(-1.7, 0.0, -1.4)",
                                "(-0.2, 0.0, -8.7)",
                                "(-3.4, 0.0, -8.7)",
                                "(-3.9, 0.0, -6.3)",
                                "(-5.4, 0.0, -2.6)",
                                "(-8.6, 0.0, -0.8)"
                            },
                            MarkerConfig = new MarkerConfig
                            {
                                Enable = true,
                                IsRingMarker = true,
                                IsShopMarker = true,
                                DisplayName = En ? "Sputnik" : "Спутник",
                                Radius = 0.25f,
                                Alpha = 0.6f,
                                Color1 = new ColorConfig { R = 0.81f, G = 0.25f, B = 0.15f },
                                Color2 = new ColorConfig { R = 0f, G = 0f, B = 0f }
                            },
                            ZoneConfig = new ZoneConfig
                            {
                                IsPvpZone = false,
                                IsDome = false,
                                Darkening = 5,
                                IsColoredBorder = false,
                                Brightness = 5,
                                BorderColor = 2,
                                Radius = 25,
                                Radiation = 10
                            },
                            UseCustomSpawnPoints = false,
                            CustomSpawnPoints = new List<string>(),
                            IqDronePresetConfig = ""
                        },
                        new SputnikDebrisConfig
                        {
                            PresetName = "debris_1",
                            LocationPreset = "debris_1",
                            HeliPresetName = "",
                            Turrets = new Dictionary<string, HashSet<LocationConfig>>(),
                            EnableCardReader = false,
                            CardReaderLocation = new LocationConfig
                            {
                                Position = "(0, 0, 0)",
                                Rotation = "(0, 0, 0)"
                            },
                            NpCs = new Dictionary<string, HashSet<string>>
                            {
                                ["cosmonaut"] = new HashSet<string>
                                {
                                    "(0.9, 0.0, 3.6)",
                                    "(3.7, 0.0, -0.7)",
                                    "(-1.9, 0.0, -3.2)",
                                    "(-0.1, 0.0, -1.9)",
                                    "(-0.5, -12.1, -9.2)",
                                    "(-4.5, 0.0, -0.5)"
                                }
                            },
                            GroundCrates = new Dictionary<string, HashSet<LocationConfig>>(),
                            Crates = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["cratenormal_underwater_1"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(2.2, 0.0, 3.0)",
                                        Rotation = "(0.0, 344.2, 0.0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(-1.4, 0.0, -2.2)",
                                        Rotation = "(0.0, 333.5, 0.0)"
                                    }
                                },
                                ["cratenormal_underwater_2"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(-0.4, 0.0, 0.2)",
                                        Rotation = "(0.0, 0, 0.0)"
                                    }
                                }
                            },
                            Mines = new HashSet<string>
                            {
                                "(4.9, 0.0, 2.1)",
                                "(4.7, 0.0, 0.8)",
                                "(3.5, 0.0, 2.6)",
                                "(1.8, 0.0, 3.6)",
                                "(1.3, 0.0, 2.7)",
                                "(0.1, 0.0, 2.4)",
                                "(-1.8, 0.0, 3.2)",
                                "(-4.2, 0.0, 0.7)",
                                "(3.4, 0.0, -0.4)",
                                "(4.4, 0.0, -2.0)",
                                "(2.6, 0.0, -3.0)",
                                "(2.9, 0.0, -4.5)",
                                "(-5.4, 0.0, -2.5)",
                                "(-0.1, 0.0, -5.4)",
                                "(-2.7, 0.0, -4.6)",
                                "(-0.9, 0.0, -2.5)",

                            },
                            MarkerConfig = new MarkerConfig
                            {
                                Enable = true,
                                IsRingMarker = true,
                                IsShopMarker = true,
                                DisplayName = En ? "(Space Card) Radioactive space debris" : "(Космическая карта) Радиоактивные космические обломки",
                                Radius = 0.2f,
                                Alpha = 0.6f,
                                Color1 = new ColorConfig { R = 0.81f, G = 0.25f, B = 0.15f },
                                Color2 = new ColorConfig { R = 0f, G = 0f, B = 0f }
                            },
                            ZoneConfig = new ZoneConfig
                            {
                                IsPvpZone = false,
                                IsDome = false,
                                Darkening = 5,
                                IsColoredBorder = false,
                                Brightness = 5,
                                BorderColor = 2,
                                Radius = 25,
                                Radiation = 10
                            },
                            UseCustomSpawnPoints = false,
                            CustomSpawnPoints = new List<string>(),
                            IqDronePresetConfig = ""
                        },
                        new SputnikDebrisConfig
                        {
                            PresetName = "debris_2",
                            LocationPreset = "debris_2",
                            HeliPresetName = "",
                            Turrets = new Dictionary<string, HashSet<LocationConfig>>(),
                            EnableCardReader = false,
                            CardReaderLocation = new LocationConfig
                            {
                                Position = "(0, 0, 0)",
                                Rotation = "(0, 0, 0)"
                            },
                            NpCs = new Dictionary<string, HashSet<string>>
                            {
                                ["cosmonaut"] = new HashSet<string>
                                {
                                    "(0, 0, -2)",
                                    "(-1, 0, 3)",
                                    "(3, 0, 0)",
                                }
                            },
                            GroundCrates = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["cratenormal_default"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(-2.2, 0, -1.8)",
                                        Rotation = "(0, 50, 0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(0.75, 0, 2.25)",
                                        Rotation = "(0, 50, 0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(3, 0, -2.25)",
                                        Rotation = "(0, 304, 0)"
                                    }
                                },
                            },
                            Crates = new Dictionary<string, HashSet<LocationConfig>>(),
                            Mines = new HashSet<string>(),
                            MarkerConfig = new MarkerConfig
                            {
                                Enable = true,
                                IsRingMarker = true,
                                IsShopMarker = true,
                                DisplayName = En ? "Space debris" : "Космические обломки",
                                Radius = 0.2f,
                                Alpha = 0.6f,
                                Color1 = new ColorConfig { R = 0.81f, G = 0.25f, B = 0.15f },
                                Color2 = new ColorConfig { R = 0f, G = 0f, B = 0f }
                            },
                            ZoneConfig = new ZoneConfig
                            {
                                IsPvpZone = false,
                                IsDome = false,
                                Darkening = 5,
                                IsColoredBorder = false,
                                Brightness = 5,
                                BorderColor = 2,
                                Radius = 25,
                                Radiation = 0
                            },
                            UseCustomSpawnPoints = false,
                            CustomSpawnPoints = new List<string>(),
                            IqDronePresetConfig = ""
                        },
                        new SputnikDebrisConfig
                        {
                            PresetName = "debris_3",
                            LocationPreset = "debris_3",
                            HeliPresetName = "",
                            Turrets = new Dictionary<string, HashSet<LocationConfig>>(),
                            EnableCardReader = false,
                            CardReaderLocation = new LocationConfig
                            {
                                Position = "(0, 0, 0)",
                                Rotation = "(0, 0, 0)"
                            },
                            NpCs = new Dictionary<string, HashSet<string>>
                            {
                                ["cosmonaut"] = new HashSet<string>
                                {
                                    "(-1.621, 0, 1.95)",
                                    "(1.08, 0, -2.04)",
                                    "(0.111, 0, 2.941)"
                                }
                            },
                            GroundCrates = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["cratenormal_default"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(0.13, 0, 1.9)",
                                        Rotation = "(0, 50, 0)"
                                    }
                                },
                                ["crateelite_default"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(1.465, 0.0, -0.625)",
                                        Rotation = "(0.0, 290, 0.0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(-0.264, 0.0, -1.919)",
                                        Rotation = "(0.0, 0, 0.0)"
                                    }
                                }
                            },
                            Crates = new Dictionary<string, HashSet<LocationConfig>>(),
                            Mines = new HashSet<string>(),
                            MarkerConfig = new MarkerConfig
                            {
                                Enable = true,
                                IsRingMarker = true,
                                IsShopMarker = true,
                                DisplayName = En ? "Space debris" : "Космические обломки",
                                Radius = 0.2f,
                                Alpha = 0.6f,
                                Color1 = new ColorConfig { R = 0.81f, G = 0.25f, B = 0.15f },
                                Color2 = new ColorConfig { R = 0f, G = 0f, B = 0f }
                            },
                            ZoneConfig = new ZoneConfig
                            {
                                IsPvpZone = false,
                                IsDome = false,
                                Darkening = 5,
                                IsColoredBorder = false,
                                Brightness = 5,
                                BorderColor = 2,
                                Radius = 25,
                                Radiation = 0
                            },
                            UseCustomSpawnPoints = false,
                            CustomSpawnPoints = new List<string>(),
                            IqDronePresetConfig = ""
                        },
                        new SputnikDebrisConfig
                        {
                            PresetName = "debris_4",
                            LocationPreset = "debris_4",
                            HeliPresetName = "",
                            Turrets = new Dictionary<string, HashSet<LocationConfig>>(),
                            EnableCardReader = false,
                            CardReaderLocation = new LocationConfig
                            {
                                Position = "(0, 0, 0)",
                                Rotation = "(0, 0, 0)"
                            },
                            NpCs = new Dictionary<string, HashSet<string>>
                            {
                                ["cosmonaut"] = new HashSet<string>
                                {
                                    "(1.334, 0, 3.326",
                                    "(1.793, 0, -1.614)"
                                }
                            },
                            GroundCrates = new Dictionary<string, HashSet<LocationConfig>>
                            {
                                ["cratenormal_default"] = new HashSet<LocationConfig>
                                {
                                    new LocationConfig
                                    {
                                        Position = "(2.481, 0, 2.517)",
                                        Rotation = "(0, 25, 0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(0.911, 0, 2.207)",
                                        Rotation = "(0, 320, 0)"
                                    },
                                    new LocationConfig
                                    {
                                        Position = "(2.186, 0, -0.275)",
                                        Rotation = "(0, 347, 0)"
                                    }
                                },
                            },
                            Crates = new Dictionary<string, HashSet<LocationConfig>>(),
                            Mines = new HashSet<string>(),
                            MarkerConfig = new MarkerConfig
                            {
                                Enable = true,
                                IsRingMarker = true,
                                IsShopMarker = true,
                                DisplayName = En ? "Space debris" : "Космические обломки",
                                Radius = 0.2f,
                                Alpha = 0.6f,
                                Color1 = new ColorConfig { R = 0.81f, G = 0.25f, B = 0.15f },
                                Color2 = new ColorConfig { R = 0f, G = 0f, B = 0f }
                            },
                            ZoneConfig = new ZoneConfig
                            {
                                IsPvpZone = false,
                                IsDome = false,
                                Darkening = 5,
                                IsColoredBorder = false,
                                Brightness = 5,
                                BorderColor = 2,
                                Radius = 25,
                                Radiation = 0
                            },
                            UseCustomSpawnPoints = false,
                            CustomSpawnPoints = new List<string>(),
                            IqDronePresetConfig = ""
                        }
                    },
                    CustomCardConfig = new CustomCardConfig
                    {
                        ShortName = "keycard_green",
                        Name = En ? "SPACE CARD" : "КОСМИЧЕСКАЯ КАРТА",
                        SkinID = 2841475252,
                        HealthLossScale = 1,
                        EnableSpawnInDefaultCrates = false,
                        SpawnSetting = new Dictionary<string, float>
                        {
                            ["assets/bundled/prefabs/radtown/crate_elite.prefab"] = 5f
                        }
                    },
                    CrateConfigs = new HashSet<CrateConfig>
                    {
                        new CrateConfig
                        {
                            PresetName = "chinooklockedcrate_spacecard",
                            PrefabName = "assets/prefabs/deployable/chinooklockedcrate/codelockedhackablecrate.prefab",
                            NeedSpaceCard = true,
                            HackTime = 0
                        },
                        new CrateConfig
                        {
                            PresetName = "chinooklockedcrate_default",
                            PrefabName = "assets/prefabs/deployable/chinooklockedcrate/codelockedhackablecrate.prefab",
                            HackTime = 0
                        },
                        new CrateConfig
                        {
                            PresetName = "crateelite_default",
                            PrefabName = "assets/bundled/prefabs/radtown/underwater_labs/crate_elite.prefab",
                            HackTime = 0
                        },
                        new CrateConfig
                        {
                            PresetName = "cratenormal_underwater_1",
                            PrefabName = "assets/bundled/prefabs/radtown/underwater_labs/crate_normal.prefab",
                            HackTime = 0
                        },
                        new CrateConfig
                        {
                            PresetName = "cratenormal_underwater_2",
                            PrefabName = "assets/bundled/prefabs/radtown/underwater_labs/crate_normal_2.prefab",
                            HackTime = 0,
                            LootTableConfig = new LootTableConfig
                            {
                                IsAlphaLoot = true,
                                AlphaLootPreset = string.Empty,
                                IsLoottablePlugin = true,
                                LoottablePreset = string.Empty,
                                IsCustomLootPlugin = true,
                                CustomLootPreset = string.Empty,
                                ClearDefaultLoot = false,
                                PrefabsTable = new PrefabsLootTableConfig
                                {
                                    IsEnabled = false,
                                    MinPrefabsAmount = 1,
                                    MaxPrefabsAmount = 1,
                                    Prefabs = new List<LootPrefabConfig>
                                    {
                                        new LootPrefabConfig
                                        {
                                            PrefabName = "assets/bundled/prefabs/radtown/underwater_labs/tech_parts_1.prefab",
                                            Chance = 100,
                                            MinAmount = 1,
                                            MaxAmount = 1
                                        }
                                    }
                                },
                                ItemsTable = new ItemsLootTableConfig
                                {
                                    IsEnabled = true,
                                    DisableMinMax = false,
                                    MinItemsAmount = 1,
                                    MaxItemsAmount = 1,
                                    Items = new List<LootItemConfig>
                                    {
                                        new LootItemConfig
                                        {
                                            Shortname = "keycard_green",
                                            Skin = 2841475252,
                                            Chance = 100,
                                            MinAmount = 1,
                                            MaxAmount = 1,
                                            DisplayName = "SPACE CARD",
                                            OwnerName = string.Empty,
                                            IsBlueprint = false,
                                            Genomes = new List<string>()
                                        }
                                    }
                                }
                            }
                        },
                        new CrateConfig
                        {
                            PresetName = "cratenormal_default",
                            PrefabName = "assets/bundled/prefabs/radtown/crate_normal.prefab",
                            HackTime = 0
                        }
                    },
                    NpcConfigs = new HashSet<NpcConfig>
                    {
                        new NpcConfig
                        {
                            PresetName = "cosmonaut",
                            DisplayName = "Cosmonaut",
                            Health = 200f,
                            WearItems = new HashSet<NpcWear>
                            {
                                new NpcWear
                                {
                                    ShortName = "hazmatsuit",
                                    SkinID = 10180
                                }
                            },
                            BeltItems = new HashSet<NpcBelt>
                            {
                                new NpcBelt
                                {
                                    ShortName = "rifle.lr300",
                                    Amount = 1,
                                    SkinID = 0,
                                    Mods = new HashSet<string> { "weapon.mod.flashlight", "weapon.mod.holosight" },
                                    Ammo = ""
                                },
                                new NpcBelt
                                {
                                    ShortName = "syringe.medical",
                                    Amount = 10,
                                    SkinID = 0,
                                    Mods = new HashSet<string>(),
                                    Ammo = ""
                                },
                                new NpcBelt
                                {
                                    ShortName = "grenade.f1",
                                    Amount = 10,
                                    SkinID = 0,
                                    Mods = new HashSet<string>(),
                                    Ammo = ""
                                }
                            },
                            Kit = "",
                            DeleteCorpse = true,
                            DisableRadio = false,
                            RoamRange = 5f,
                            ChaseRange = 15,
                            AttackRangeMultiplier = 1f,
                            SenseRange = 100,
                            MemoryDuration = 60f,
                            DamageScale = 1f,
                            AimConeScale = 1f,
                            CheckVisionCone = false,
                            VisionCone = 135f,
                            Speed = 7.5f,
                            TurretDamageScale = 0f
                        }
                    },
                    HeliConfigs = new HashSet<HeliConfig>
                    {
                        new HeliConfig
                        {
                            PresetName = "heli_1",
                            Hp = 10000f,
                            MainRotorHealth = 750f,
                            RearRotorHealth = 375f,
                            Height = 50f,
                            BulletDamage = 20f,
                            BulletSpeed = 250f,
                            Distance = 100f,
                            OutsideTime = 30,
                            CratesLifeTime = 1800,
                            CratesAmount = 3
                        }
                    },
                    TurretConfigs = new HashSet<TurretConfig>
                    {
                        new TurretConfig
                        {
                            PresetName = "turret_ak",
                            AutoHeight = true,
                            Hp = 250f,
                            ShortNameWeapon = "rifle.ak",
                            ShortNameAmmo = "ammo.rifle",
                            CountAmmo = 200
                        },
                        new TurretConfig
                        {
                            PresetName = "turret_m249",
                            AutoHeight = false,
                            Hp = 300f,
                            ShortNameWeapon = "lmg.m249",
                            ShortNameAmmo = "ammo.rifle",
                            CountAmmo = 400
                        }
                    },
                    NotifyConfig = new NotifyConfig
                    {
                        PreStartTime = 0,
                        IsChatEnable = true,
                        TimeNotifications = new HashSet<int>
                        {
                            300,
                            60,
                            30,
                            5
                        },
                        GameTipConfig = new GameTipConfig
                        {
                            IsEnabled = false,
                            Style = 2,
                        }
                    },
                    GUIConfig = new GUIConfig
                    {
                        IsEnable = true,
                        OffsetMinY = -56
                    },
                    SupportedPluginsConfig = new SupportedPluginsConfig
                    {
                        PveMode = new PveModeConfig
                        {
                            Enable = false,
                            ShowEventOwnerNameOnMap = true,
                            Damage = 500f,
                            ScaleDamage = new Dictionary<string, float>
                            {
                                ["Npc"] = 1f,
                                ["Helicopter"] = 2f,
                                ["Turret"] = 2f,
                            },
                            LootCrate = false,
                            HackCrate = false,
                            LootNpc = false,
                            DamageNpc = false,
                            TargetNpc = false,
                            DamageHeli = false,
                            TargetHeli = false,
                            CanEnter = false,
                            CanEnterCooldownPlayer = true,
                            TimeExitOwner = 300,
                            AlertTime = 60,
                            CooldownOwner = 86400,
                        },
                        EconomicsConfig = new EconomyConfig
                        {
                            Enable = false,
                            Plugins = new HashSet<string> { "Economics", "Server Rewards", "IQEconomic" },
                            MinCommandPoint = 0,
                            MinEconomyPoint = 0,
                            Crates = new Dictionary<string, double>
                            {
                                ["assets/prefabs/deployable/chinooklockedcrate/codelockedhackablecrate.prefab"] = 0.4
                            },
                            NpcPoint = 2,
                            HackCratePoint = 5,
                            TurretPoint = 2,
                            HeliPoint = 5,
                            Commands = new HashSet<string>()
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
                            Type = 1
                        },
                        DiscordMessagesConfig = new DiscordConfig
                        {
                            IsEnabled = false,
                            WebhookUrl = "https://support.discordapp.com/hc/en-us/articles/228383668-Intro-to-Webhooks",
                            EmbedColor = 13516583,
                            Keys = new HashSet<string>
                            {
                                "PreStartEvent",
                                "StartEvent",
                                "Crash"
                            }
                        },
                        ZoneManager = new ZoneManagerConfig
                        {
                            Enable = false,
                            BlockFlags = new HashSet<string>
                            {
                                "eject",
                                "pvegod"
                            },
                            BlockIDs = new HashSet<string>
                            {
                                "Example"
                            }
                        },
                        RaidableBases = new RaidableBasesConfig
                        {
                            Enable = false
                        },
                        RestoreUponDeath = new RestoreUponDeathConfig
                        {
                            DisableRestore = false
                        },
                        BetterNpcConfig = new BetterNpcConfig
                        {
                            IsHeliNpc = false,
                        },
                        IqDroneConfig = new IqDroneConfig
                        {
                            IsDroneEnable = false,
                            DronePresets = new HashSet<IqDronePreset>
                            {
                                new IqDronePreset
                                {
                                    PresetName = "lite",
                                    DroneNumber = 5,
                                    TargetDroneNumber = 5,
                                    DroneKeys = new Dictionary<string, int>
                                    {
                                        ["LITE_DRONE"] = 100,
                                    },
                                }
                            }
                        }
                    }
                };
            }
        }
        #endregion Config
    }
}

namespace Oxide.Plugins.SputnikExtensionMethods
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
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (predicate(enumerator.Current)) result.Add(enumerator.Current);
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

        public static HashSet<T> OfType<T>(this IEnumerable<BaseNetworkable> source)
        {
            HashSet<T> result = new HashSet<T>();
            using (var enumerator = source.GetEnumerator()) while (enumerator.MoveNext()) if (enumerator.Current is T) result.Add((T)(object)enumerator.Current);
            return result;
        }

        public static bool IsExists(this BaseNetworkable entity) => entity != null && !entity.IsDestroyed;

        public static void ClearItemsContainer(this ItemContainer container)
        {
            for (int i = container.itemList.Count - 1; i >= 0; i--)
            {
                Item item = container.itemList[i];
                item.RemoveFromContainer();
                item.Remove();
            }
        }

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

        public static TSource ElementAt<TSource>(this IEnumerable<TSource> source, int index)
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
        
        public static TSource Last<TSource>(this IList<TSource> source) => source[source.Count - 1];

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
    }
}