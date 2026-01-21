/*
 * Copyright © 2025 Scalbox - All rights reserved.
 * Developer: Scalbox - Contact: https://discord.gg/yryp3MjkF8
 *
 * DIGITAL CONTENT COPYRIGHT LICENSE
 *
 * INTELLECTUAL PROPERTY:
 * This digital content is protected by copyright under international and local laws.
 * All rights are reserved and any unauthorized use is prohibited.
 * The content purchased may not be reproduced, distributed, transmitted, or used in any form or by any means
 * without the written permission of the copyright holder, except as required by law.
 *
 * TERMS OF USE:
 * 1. Personal Use: Your purchase of this digital content grants you a non-exclusive, non-transferable, revocable license for personal use. The content may not be shared or distributed to third parties.
 * 2. Limitations: You may not modify, alter, adapt, create derivative works from, sell, or license this content without the written permission of the copyright holder.
 * 3. Backup: You may make one backup copy of the content for your personal use, provided that it is not distributed.
 *
 * WARRANTIES AND RESPONSIBILITIES:
 * The copyright holder makes no warranties regarding the accuracy, completeness or suitability of the content for a particular purpose.
 * In no event shall the copyright holder be liable for any damages arising from the use or inability to use the purchased digital content.
 *
 * PERMISSION REQUESTS:
 * For requests for permission to use or other information, please contact:
 *
 * Scalbox
 * info@scalbox.com
 */

using System;
using System.Collections;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using HarmonyLib;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using Oxide.Core;
using Oxide.Core.Libraries;
using Oxide.Core.Libraries.Covalence;
using Oxide.Core.Plugins;
using Oxide.Game.Rust.Cui;
using Rust;
using UnityEngine;
using Physics = UnityEngine.Physics;
using Harmony = HarmonyLib.Harmony;

//==================================================
//CHANGELOG
//==================================================

//3.0.8     =>      20/07/2025
// - Updated: Modified to comply with the new Rust TOS (prohibit use of DLC/Skins you don't own)
// - Updated: Blocked the usage of DLC/Skins that players do not own

//3.0.9     =>      18/08/2025
// - Fixed: Compile error due to the new Oxide update (2.0.6550)

//3.1.0     =>      22/08/2025
// - Added: Permission to bypass DLC or Skin ownership checks

//3.1.1     =>      05/09/2025
// - Fixed: Plugin loading NRE

namespace Oxide.Plugins
{
    [Info("BuildingSkin", "Scalbox", "3.1.1")]
    [Description("Change building skin, color & wallpaper")]
    public class BuildingSkin : RustPlugin
    {
        #region Fields

        [PluginReference] private Plugin? ImageLibrary;
        [PluginReference] private Plugin? Clans;

        private static BuildingSkin? self;
        private Harmony _harmony;

        private bool _steamWorkshopInitialized;

        // Workshop Skin ID to item ID mapping
        private readonly Dictionary<ulong, int> _workshopIdToItemId = new();

        // Item ID to skin mapping
        private readonly Dictionary<int, ItemSkinDirectory.Skin> _itemIdToSkin = new();

        private const string PropertyWorkshopID = "workshopid";

        private const string PermissionUse = "buildingskin.use";
        private const string PermissionAll = "buildingskin.all";
        private const string PermissionBuild = "buildingskin.build";
        private const string PermissionAdmin = "buildingskin.admin";
        private const string PermissionBypassDlcSkinRequired = "buildingskin.bypass.dlc_skin_required";
        private const string PermissionWallpaperPlaceNoCost = "buildingskin.wallpaper.nocost";

        //With this role, you can use skins that are not present in the configuration, and which can be taken directly from Steam
        private const string PermissionWallpaperUseSkinFromSteam = "buildingskin.wallpaper.useskinfromsteam";

        private const string STEAM_WORKSHOP_FILE_DETAILS_END_POINT = "https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/";
        private const string STEAM_WORKSHOP_COLLECTION_DETAILS_END_POINT = "https://api.steampowered.com/ISteamRemoteStorage/GetCollectionDetails/v1/";

        private const string DownloadRustWallpaperUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Wallpaper/RustWallpaperData.json";
        private const string DownloadSteamWorkshopWallpaperUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Wallpaper/SteamWorkshopWallpaperV2.json";
        private const ulong DownloadSteamWorkshopWallpaperCoroutineName = ulong.MaxValue;

        private const int HTTP_STATUS_CODE_OK = 200;

        private enum SkinId
        {
            Default = 0,

            // Wood = 0,
            WoodLegacyWood = 10232,
            WoodGingerbread = 2,

            // Stone = 0,
            StoneAdobe = 10220,
            StoneBrick = 10223,
            StoneBrutalist = 10225,
            StoneJungleTemple = 10326,

            // Metal = 0,
            MetalShippingContainer = 10221,

            // TopTier = 0
        }

        private enum WallpaperApplyModeEnum
        {
            Building = 0,
            Manual = 1,
        }

        private enum WallpaperSideEnum
        {
            Internal = 0,
            External = 1,
            Both = 2
        }

        private enum WallpaperCategory
        {
            Wall = 1,
            Floor = 2,
            Ceiling = 3
        }

        private enum WallpaperDownloadType
        {
            Rust = 1,
            SteamWorkshop = 2
        }

        private readonly Dictionary<ulong, Coroutine> _runningCoroutines = new();

        private readonly Dictionary<ulong, bool> _playerColorPanelCursor = new();
        private readonly Dictionary<ulong, bool> _playerWallpaperPanelCursor = new();

        //SteamID - WallpaperCategory
        private readonly Dictionary<ulong, WallpaperCategory> _playersWallpaperCategory = new();

        //SteamID - ItemsPagination
        private readonly Dictionary<ulong, ItemsPagination> _playersWallpaperPagination = new();

        private static readonly Dictionary<BuildingGrade.Enum, List<ulong>> GradesSkin = new()
        {
            { BuildingGrade.Enum.Wood, new List<ulong> { (ulong)SkinId.WoodLegacyWood, (ulong)SkinId.WoodGingerbread } }, // 10232 = Legacy Wood, 2 = Gingerbread
            {
                BuildingGrade.Enum.Stone, new List<ulong> { (ulong)SkinId.StoneAdobe, (ulong)SkinId.StoneBrick, (ulong)SkinId.StoneBrutalist, (ulong)SkinId.StoneJungleTemple }
            }, // 10220 = Adobe, 10223 = Brick, 10225 = Brutalist, Jungle Temple = 10326
            { BuildingGrade.Enum.Metal, new List<ulong> { (ulong)SkinId.MetalShippingContainer } }, // 10221 = Shipping Container,
            { BuildingGrade.Enum.TopTier, new List<ulong> { (ulong)SkinId.Default } }
        };

        private readonly Dictionary<uint, string> _metalGradeColor = new()
        {
            { 0, "0 0 0" }, //This is not a valid color, it is only used to identify RAINBOW coloring (all colors)
            { 1, "0.376 0.561 0.741" },
            { 2, "0.447 0.714 0.341" },
            { 3, "0.569 0.286 0.827" },
            { 4, "0.416 0.169 0.11" },
            { 5, "0.816 0.459 0.133" },
            { 6, "0.722 0.725 0.733" },
            { 7, "0.196 0.2 0.18" },
            { 8, "0.4 0.329 0.275" },
            { 9, "0.2 0.22 0.337" },
            { 10, "0.243 0.345 0.2" },
            { 11, "0.725 0.298 0.184" },
            { 12, "0.776 0.533 0.388" },
            { 13, "0.843 0.663 0.224" },
            { 14, "0.337 0.325 0.306" },
            { 15, "0.208 0.337 0.373" },
            { 16, "0.659 0.612 0.565" },
        };

        private const bool DefaultWallpaperDisableDecay = true;
        private const bool DefaultWallpaperDisableDamage = true;
        private const int DefaultWallpaperPlaceCost = 5;

        private readonly ItemDefinition _wallpaperWallItemDef = ItemManager.FindItemDefinition("wallpaper.wall");
        private readonly ItemDefinition _wallpaperFloorItemDef = ItemManager.FindItemDefinition("wallpaper.flooring");
        private readonly ItemDefinition _wallpaperCeilingItemDef = ItemManager.FindItemDefinition("wallpaper.ceiling");

        // private const int WallpaperWallItemId = 553967074;
        // private const int WallpaperFloorItemId = -551431036;
        // private const int WallpaperCeilingItemId = 1730664641;

        private const int ToolGunItemId = 1803831286;
        private const int HammerItemId = 200773292;
        private const ulong WallpaperRotationToolSkinID = 3494618061;
        private const string WallpaperRotationToolDefaultItemName = "Wallpaper Rotation Tool";

        //Used to identify the deletion of wallpapers. Use int.MaxValue because ulong.MaxValue throws this error: JSON integer 18446744073709551615 is too large or small
        private const ulong WallpaperIdForRemoval = int.MaxValue;
        private const int WallpaperItemsPerPages = 30;

        //Muri consentiti per i wallpaper
        private readonly List<string> _wallShortPrefabName = new()
        {
            "wall",
            "wall.half",
            "wall.low",
            "wall.doorway",
            "wall.window"
        };

        //Pavimenti consentiti per i wallpaper
        private readonly List<string> _floorShortPrefabName = new()
        {
            "foundation",
            "foundation.triangle",
            "floor",
            "floor.triangle"
        };

        //Soffitti consentiti per i wallpaper
        private readonly List<string> _ceilingShortPrefabName = new()
        {
            "floor",
            "floor.triangle",
            "roof",
            "roof.triangle"
        };

        //SteamID : Dictionary of (SkinID, Owned)
        private static readonly Dictionary<ulong, Dictionary<ulong, bool>> PlayerDlcSkinOwnedCache = new();

        private static readonly HashSet<ulong> SkinIdWhitelisted = new()
        {
            WallpaperIdForRemoval,
            0,
            2, //Gingerbread
            10242,
            10243,
            10246,
            10372,
            10386,
            10384,
            10388,
            10401,
            10406
        };

        //WallpaperCategory - SkinID, WallpaperSkinInfo
        private readonly Dictionary<WallpaperCategory, Dictionary<ulong, WallpaperSkinInfo>> _wallpaperSkinList = new();

        //Wallpaper per i muri
        private static readonly Dictionary<ulong, WallpaperSkinInfo> DefaultWallWallpaperSkinList = new()
        {
            {
                //This is not a valid wallpaper skin, it is only used to identify the deletion of wallpapers
                WallpaperIdForRemoval, new WallpaperSkinInfo
                {
                    SkinID = 0,
                    SkinName = "ND",
                    SkinDescription = "",
                    ForWall = true,
                    FromSteamWorkshop = false
                }
            },
            {
                0, new WallpaperSkinInfo
                {
                    SkinID = 0,
                    SkinName = "Blue",
                    SkinDescription = "",
                    ForWall = true,
                    FromSteamWorkshop = false
                }
            }
        };

        //Wallpaper per i pavimenti
        private static readonly Dictionary<ulong, WallpaperSkinInfo> DefaultFloorWallpaperSkinList = new()
        {
            {
                //This is not a valid wallpaper skin, it is only used to identify the deletion of wallpapers
                WallpaperIdForRemoval, new WallpaperSkinInfo
                {
                    SkinID = 0,
                    SkinName = "ND",
                    SkinDescription = "",
                    ForFloor = true,
                    FromSteamWorkshop = false
                }
            },
            {
                0, new WallpaperSkinInfo
                {
                    SkinID = 0,
                    SkinName = "Default",
                    SkinDescription = "",
                    ForFloor = true,
                    FromSteamWorkshop = false
                }
            }
        };

        //Wallpaper per i soffitti
        private static readonly Dictionary<ulong, WallpaperSkinInfo> DefaultCeilingWallpaperSkinList = new()
        {
            {
                //This is not a valid wallpaper skin, it is only used to identify the deletion of wallpapers
                WallpaperIdForRemoval, new WallpaperSkinInfo
                {
                    SkinID = 0,
                    SkinName = "ND",
                    SkinDescription = "",
                    ForCeiling = true,
                    FromSteamWorkshop = false
                }
            },
            {
                0, new WallpaperSkinInfo
                {
                    SkinID = 0,
                    SkinName = "Default",
                    SkinDescription = "",
                    ForCeiling = true,
                    FromSteamWorkshop = false
                }
            }
        };

        private InputState input;

        private const string RainbowImageURL = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Rainbow_Image_1.png";
        private const string WallpaperRemoveImageURL = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/wallpaper_remove.png";
        private const string CursorImageURL = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Cursor.png";

        private const string Transparent = "0 0 0 0";

        private const string UIPanel = "UI.BuildingSkin";

        private const string UIPanelColor = "0.055 0.055 0.055 0.90";
        private const string UIPanelColor2 = "0.055 0.055 0.055 0.95";

        // private const string UIPanelHeaderColor = "0.055 0.055 0.055 1.00";
        private const string UIPanelHeaderColor = "0.129 0.533 0.220 1.00";

        private const string UIPanelCloseBtnColor = "0.000 0.000 0.000 1.00";
        private const string UIPanelCloseTextColor = "1.000 0.165 0.000 1.00";

        private const string UIRedColor = "1.000 0.165 0.000 1.00";
        private const string UIGreenColor = "0.380 0.455 0.239 1.00";

        // private const string UIOrangeColor = "0.98 0.612 0 0.5";
        private const string UIOrangeColor = "1 0.471 0.078 0.5";
        private const string UiInfoBtnColor = "0 0.627 0.169 1";
        private const string UiWallpaperShowDetailColor = "0.2196079 0.2196079 0.2196079 1";

        private const string UIGradeTextColor = "1 0.62 0 1.00";
        private const string UISelectedGradeTextColor = "0 1 0.1 1.00";

        private const string UIItemColor = "0.137 0.129 0.118 1.00";
        private const string UIItemEditBtnColor = "0.380 0.455 0.239 1.00";
        private const string UICheckColor = "0 0.588 0 1";
        private const string UIUnCheckColor = "1 0 0 1";

        private const string UICheckText = "\u2714";
        private const string UIUnCheckText = "\u2716";


        private const string HammerItemShortname = "hammer";

        private Configuration _config;
        private RustWallpaperData _rustWallpaperData;
        private SteamWallpaperData _wallpaperData;
        private PlayerData _playerData;

        #endregion

        #region Hook

        private void OnServerInitialized()
        {
            if (ImageLibrary == null)
            {
                PrintError("[ImageLibrary] not found or unloaded! Plugin is disabled!");
                Interface.Oxide.UnloadPlugin(Title);
                return;
            }

            //Merge Downloaded wallpaper
            MergeDownloadedWallpaper();

            if ((Steamworks.SteamInventory.Definitions?.Length ?? 0) == 0)
            {
                Puts("Waiting for Steam Workshop to update item definitions....");
                Steamworks.SteamInventory.OnDefinitionsUpdated += ProcessSteamWorkshopDefinitions;
            }
            else ProcessSteamWorkshopDefinitions();

            if (!permission.PermissionExists(PermissionUse, this)) permission.RegisterPermission(PermissionUse, this);
            if (!permission.PermissionExists(PermissionAll, this)) permission.RegisterPermission(PermissionAll, this);
            if (!permission.PermissionExists(PermissionBuild, this)) permission.RegisterPermission(PermissionBuild, this);
            if (!permission.PermissionExists(PermissionAdmin, this)) permission.RegisterPermission(PermissionAdmin, this);
            if (!permission.PermissionExists(PermissionBypassDlcSkinRequired, this)) permission.RegisterPermission(PermissionBypassDlcSkinRequired, this);
            if (!permission.PermissionExists(PermissionWallpaperPlaceNoCost, this)) permission.RegisterPermission(PermissionWallpaperPlaceNoCost, this);
            if (!permission.PermissionExists(PermissionWallpaperUseSkinFromSteam, this)) permission.RegisterPermission(PermissionWallpaperUseSkinFromSteam, this);

            foreach (var skinInfo in _config.SkinList.Values.SelectMany(skinList => skinList))
            {
                ImageLibrary.Call("AddImage", skinInfo.SkinImageUrl, skinInfo.SkinImageUrl);
            }

            ImageLibrary.Call("AddImage", RainbowImageURL, RainbowImageURL);
            ImageLibrary.Call("AddImage", WallpaperRemoveImageURL, WallpaperRemoveImageURL);
            ImageLibrary.Call("AddImage", CursorImageURL, CursorImageURL);

            self = this;
            _harmony = new Harmony($"{Name}_Patch_{Version.Major}_{Version.Minor}_{Version.Patch}");

            if (_config.WallpaperDisableDecay)
            {
                _harmony.Patch(original: AccessTools.Method(typeof(BuildingBlock), nameof(BuildingBlock.CheckWallpaper)),
                    prefix: new HarmonyMethod(typeof(BuildingBlock_Settings), nameof(BuildingBlock_Settings.Prefix_CheckWallpaper)));
            }

            if (_config.WallpaperDisableDamage)
            {
                _harmony.Patch(original: AccessTools.Method(typeof(BuildingBlock), "DamageWallpaper"),
                    prefix: new HarmonyMethod(typeof(BuildingBlock_Settings), nameof(BuildingBlock_Settings.Prefix_DamageWallpaper)));
            }

            //Blocca il rimborso della stoffa, quando si ha il permesso buildingskin.wallpaper.nocost
            _harmony.Patch(original: AccessTools.Method(typeof(BuildingBlock), "RPC_PickupWallpaperStart"),
                prefix: new HarmonyMethod(typeof(BuildingBlock_Settings), nameof(BuildingBlock_Settings.Prefix_RPC_PickupWallpaperStart)));

            StartDownloadWallpaperData(WallpaperDownloadType.Rust);

            if (_config.DownloadSteamWorkshopWallpaper)
                StartDownloadWallpaperData(WallpaperDownloadType.SteamWorkshop);

            // //For debug
            // var player = BasePlayer.Find("Username or SteamID");
            // player?.SendConsoleCommand("bskin", "wp");
        }

        void Init()
        {
            AddCovalenceCommand("bskin", nameof(CmdHandler));

            _rustWallpaperData = Interface.Oxide.DataFileSystem.ReadObject<RustWallpaperData>($"{Name}/{nameof(RustWallpaperData)}");
            _wallpaperData = Interface.Oxide.DataFileSystem.ReadObject<SteamWallpaperData>($"{Name}/{nameof(SteamWallpaperData)}");
            _playerData = Interface.Oxide.DataFileSystem.ReadObject<PlayerData>($"{Name}/{nameof(PlayerData)}");

            foreach (var basePlayer in BasePlayer.activePlayerList)
            {
                OnPlayerConnected(basePlayer);
            }
        }

        private void OnPlayerConnected(BasePlayer player)
        {
            if (player == null) return;

            if (!permission.UserHasPermission(player.UserIDString, PermissionUse)) return;
            if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out var playerSettings) || playerSettings.DisableBskin) return;

            //Load default Shipping Container skin color
            if (!playerSettings.PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out var playerSettingsGrade))
                return;

            if (playerSettingsGrade is { AutoApplyColor: true, SkinColor: > 0 })
                SetShippingContainerBlockColourForPlayer(player, playerSettingsGrade.SkinColor);

            //Svuoto la cache dei DLC/Skin del player, cosi che se il DLC/Skin viene acquistato prima della connessione al server, non siano presenti dati sporchi nella cache
            PlayerDlcSkinOwnedCache.Remove(player.userID);
        }

        void OnServerSave() => SaveData();

        private void Unload()
        {
            foreach (var player in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(player, UIPanel);
                CuiHelper.DestroyUi(player, UIPanel + "c");
                CuiHelper.DestroyUi(player, UIPanel + ".ColorChoice");
                CuiHelper.DestroyUi(player, UIPanel + ".WallpaperChoice");
                CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");
            }

            foreach (var coroutine in _runningCoroutines)
            {
                ServerMgr.Instance.StopCoroutine(coroutine.Value);
            }

            _runningCoroutines.Clear();
            _playerColorPanelCursor.Clear();
            _playerWallpaperPanelCursor.Clear();
            _playersWallpaperCategory.Clear();
            _playersWallpaperPagination.Clear();

            SaveData();

            _harmony.UnpatchAll($"{Name}_Patch_{Version.Major}_{Version.Minor}_{Version.Patch}");
            self = null;
        }

        private void OnNewSave(string filename)
        {
            _playerData.PlayerSkinData.Clear();
            SaveData();
        }

        protected override void LoadConfig()
        {
            base.LoadConfig();
            _config = Config.ReadObject<Configuration>();
            if (_config == null) LoadDefaultConfig();

            if (!Equals(_config!.VersionNumber, Version))
            {
                UpdateConfigValues();
                _config.VersionNumber = Version;
            }

            // //For debug
            // DeleteLangFiles();

            SaveConfig();
        }

        protected override void LoadDefaultConfig()
        {
            _config = Configuration.CreateConfig();
        }

        private void UpdateConfigValues()
        {
            UpdateDataValues();

            if (_config.VersionNumber < new VersionNumber(2, 0, 4))
            {
                Puts("Configuration file is outdated. Updating...");
                _config.WallpaperPlaceCost = DefaultWallpaperPlaceCost;
                PrintWarning("Config update completed!");
            }

            if (_config.VersionNumber < new VersionNumber(2, 1, 6))
            {
                DeleteLangFiles();
            }

            if (_config.VersionNumber < new VersionNumber(2, 2, 3))
            {
                Puts("Configuration file is outdated. Updating...");
                _config.SkinList[BuildingGrade.Enum.Stone].Add(new Configuration.SkinInfo
                {
                    Grade = BuildingGrade.Enum.Stone,
                    SkinID = (ulong)SkinId.StoneJungleTemple,
                    SkinName = "Jungle Temple",
                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Jungle_temple_skin.png"
                });
                SaveConfig();
                PrintWarning("Config update completed!");
            }

            if (_config.VersionNumber < new VersionNumber(2, 2, 5))
            {
                if (_config.SkinList[BuildingGrade.Enum.Stone].Any(x => x.SkinID == (ulong)SkinId.StoneJungleTemple))
                {
                    _config.SkinList[BuildingGrade.Enum.Stone].RemoveAll(x => x.SkinID == (ulong)SkinId.StoneJungleTemple);
                }

                Puts("Configuration file is outdated. Updating...");
                _config.SkinList[BuildingGrade.Enum.Stone].Add(new Configuration.SkinInfo
                {
                    Grade = BuildingGrade.Enum.Stone,
                    SkinID = (ulong)SkinId.StoneJungleTemple,
                    SkinName = "Jungle Temple",
                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Jungle_temple_skin.png"
                });
                SaveConfig();
                PrintWarning("Config update completed!");
            }

            if (_config.VersionNumber < new VersionNumber(3, 0, 0))
            {
                Interface.Oxide.DataFileSystem.DeleteDataFile($"{Name}/WallpaperData");
                DeleteLangFiles();
            }

            if (_config.VersionNumber < new VersionNumber(3, 0, 2))
            {
                DeleteLangFiles();
            }

            if (_config.VersionNumber < new VersionNumber(3, 0, 3))
            {
                DeleteLangFiles();
            }

            if (_config.VersionNumber < new VersionNumber(3, 0, 4))
            {
                DeleteLangFiles();
            }

            if (_config.VersionNumber < new VersionNumber(3, 0, 8))
            {
                DeleteLangFiles();
            }
        }

        private void UpdateDataValues()
        {
            if (_config.VersionNumber < new VersionNumber(3, 0, 0))
            {
                _wallpaperData = Interface.Oxide.DataFileSystem.ReadObject<SteamWallpaperData>($"{Name}/{nameof(SteamWallpaperData)}");
                Puts("Updating Data file...");

                _wallpaperData.SteamWorkshopWallpaperSkinList.Clear();
                SaveWallpaperData();

                Puts("Data file updated.");
            }

            if (_config.VersionNumber < new VersionNumber(3, 0, 8))
            {
                _playerData = Interface.Oxide.DataFileSystem.ReadObject<PlayerData>($"{Name}/{nameof(PlayerData)}");
                _playerData.PlayerSkinData.Clear();
                SavePlayerData();
            }
        }

        protected override void SaveConfig() => Config.WriteObject(_config);

        private void SaveData()
        {
            Interface.Oxide.DataFileSystem.WriteObject($"{Name}/{nameof(RustWallpaperData)}", _rustWallpaperData);
            Interface.Oxide.DataFileSystem.WriteObject($"{Name}/{nameof(SteamWallpaperData)}", _wallpaperData);
            Interface.Oxide.DataFileSystem.WriteObject($"{Name}/{nameof(PlayerData)}", _playerData);
        }

        private void SaveRustWallpaperData()
        {
            Interface.Oxide.DataFileSystem.WriteObject($"{Name}/{nameof(RustWallpaperData)}", _rustWallpaperData);
        }

        private void SaveWallpaperData()
        {
            Interface.Oxide.DataFileSystem.WriteObject($"{Name}/{nameof(SteamWallpaperData)}", _wallpaperData);
        }

        private void SavePlayerData()
        {
            Interface.Oxide.DataFileSystem.WriteObject($"{Name}/{nameof(PlayerData)}", _playerData);
        }

        private void DeleteLangFiles()
        {
            var langDir = Interface.Oxide.LangDirectory;
            string[] languages = lang.GetLanguages(this);
            foreach (var language in languages)
            {
                var langFile = Path.Combine(langDir, language, $"{Name}.json");
                if (!File.Exists(langFile)) continue;
                File.Delete(langFile);
                Puts($"########## Delete old lang file: {langFile} ##########");
            }
        }

        private void OnActiveItemChanged(BasePlayer player, Item oldItem, Item newItem)
        {
            if (newItem == null || string.IsNullOrEmpty(newItem.info.shortname))
                return;

            // // For debug for new entities
            // Puts($"########## ITEM NAME: {newItem.info.name} - ITEMID: {newItem.info.itemid} ##########");

            if (!newItem.info.shortname.Equals(HammerItemShortname)) return;
            if (!permission.UserHasPermission(player.UserIDString, PermissionUse)) return;


            if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out var playerSettings))
            {
                SendMessage(player, Lang("UIMessageTextInfo", player.UserIDString));
                return;
            }

            if (!playerSettings.DisableBskin && playerSettings.EnableMessage)
            {
                SendMessage(player, Lang("UIMessageTextInfo", player.UserIDString));
                return;
            }
        }

        //Previene il riciclaggio dell'item Wallpaper Rotation Tool
        private object? CanBeRecycled(Item item, Recycler recycler)
        {
            if (item == null || !recycler) return null;

            if (item.info.itemid == HammerItemId && item.skin == WallpaperRotationToolSkinID) return false;

            return null;
        }

        private void OnItemAddedToContainer(ItemContainer container, Item item)
        {
            if (item == null) return;
            if (item.info.itemid != HammerItemId || item.skin != WallpaperRotationToolSkinID) return;

            var ownerPlayer = item.GetOwnerPlayer();
            if (ownerPlayer)
            {
                item.name = Lang("UiWallpaperRotationTool", ownerPlayer.UserIDString);
                item.MarkDirty();
                return;
            }

            item.name = WallpaperRotationToolDefaultItemName;
            item.MarkDirty();
        }

        private void ProcessSteamWorkshopDefinitions()
        {
            Puts("Processing Steam Workshop definitions...");

            // Popola _itemIdToSkin e _workshopIdToItemId con le skin e skin.id di ItemSkinDirectory
            foreach (var skin in ItemSkinDirectory.Instance.skins)
            {
                _itemIdToSkin[skin.id] = skin;
                _workshopIdToItemId[(ulong)skin.id] = skin.id;
            }

            // Popola _workshopIdToItemId con le definizioni di Steam Workshop
            foreach (var inventoryDef in Steamworks.SteamInventory.Definitions)
            {
                var skinId = _itemIdToSkin.ContainsKey(inventoryDef.Id)
                    ? (ulong)inventoryDef.Id
                    : ulong.TryParse(inventoryDef.GetProperty(PropertyWorkshopID), out var parsedId)
                        ? parsedId
                        : 0;

                if (skinId > 0)
                    _workshopIdToItemId[skinId] = inventoryDef.Id;
            }

            _steamWorkshopInitialized = true;
        }

        private static bool IsHitDirectionUp(HitInfo info)
        {
            return info.HitNormalLocal.normalized.y < 0;
        }

        private static bool IsBlockTriangle(BuildingBlock block)
        {
            return block != null && block.ShortPrefabName.Contains("triangle", StringComparison.OrdinalIgnoreCase);
        }

        private static bool IsBlockRoof(BuildingBlock block)
        {
            return block != null && block.ShortPrefabName.Contains("roof", StringComparison.OrdinalIgnoreCase);
        }

        private static bool IsPlayerUsingWallpaperRotationToolItem(BasePlayer player)
        {
            if (!player) return false;
            var activeItem = player.GetActiveItem();
            return activeItem != null && activeItem.info.itemid == HammerItemId && activeItem.skin == WallpaperRotationToolSkinID;
        }

        private void HammerWallpaperRotation(BasePlayer player, HitInfo info)
        {
            if (info == null || !info.HitEntity) return;
            var block = info.HitEntity as BuildingBlock;
            if (block == null) return;


            if ((_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
            {
                SendMessage(player, Lang("UINotOwnerBuilding", player.UserIDString));
                return;
            }

            if (!_floorShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase) &&
                !_ceilingShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase))
                return;

            var blockHasWallpaper = block.HasWallpaper(0) || block.HasWallpaper(1);
            if (!blockHasWallpaper) return;

            var hammerHitDirectionUp = IsHitDirectionUp(info);
            WallpaperCategory? wallpaperCategory = hammerHitDirectionUp switch
            {
                false when _floorShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase) => WallpaperCategory.Floor,
                true when _ceilingShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase) => WallpaperCategory.Ceiling,
                _ => null
            };

            if (wallpaperCategory == null) return;

            RotateWallpaper(player, block, wallpaperCategory.Value);
        }

        private void RotateWallpaper(BasePlayer player, BuildingBlock initialBuildingBlock, WallpaperCategory wallpaperCategory, bool byPassButton = false)
        {
            if (player == null || player.IsNpc || initialBuildingBlock == null || initialBuildingBlock.IsDestroyed)
                return;

            BuildingBlock[]? blocks = null;

            // Recupera il BuildingBlock iniziale corretto, in base al WallpaperCategory. 
            // Questo per evitare il bug in cui la rotazione del wallpaper rimuoveva tutti i wallpaper.
            // Se ad esempio si aggiorna il wallpaper del soffitto ma si sta guardando il pavimento, tutti i wallpaper del soffitto vengono rimossi.
            if (byPassButton)
            {
                blocks = initialBuildingBlock.GetBuilding()?.buildingBlocks.ToArray();
                if (blocks is { Length: > 0 })
                {
                    var correctInitialBuildingBlock = wallpaperCategory switch
                    {
                        WallpaperCategory.Wall => !_wallShortPrefabName.Contains(initialBuildingBlock.ShortPrefabName, StringComparer.OrdinalIgnoreCase)
                            ? blocks.FirstOrDefault(b => _wallShortPrefabName.Contains(b.ShortPrefabName, StringComparer.OrdinalIgnoreCase))
                            : null,
                        WallpaperCategory.Floor => !_floorShortPrefabName.Contains(initialBuildingBlock.ShortPrefabName, StringComparer.OrdinalIgnoreCase)
                            ? blocks.FirstOrDefault(b => _floorShortPrefabName.Contains(b.ShortPrefabName, StringComparer.OrdinalIgnoreCase))
                            : null,
                        WallpaperCategory.Ceiling => !_ceilingShortPrefabName.Contains(initialBuildingBlock.ShortPrefabName, StringComparer.OrdinalIgnoreCase)
                            ? blocks.FirstOrDefault(b => _ceilingShortPrefabName.Contains(b.ShortPrefabName, StringComparer.OrdinalIgnoreCase))
                            : null,
                        _ => null
                    };

                    if (correctInitialBuildingBlock != null)
                        initialBuildingBlock = correctInitialBuildingBlock;
                }
            }


            var currentWallpaperSide = wallpaperCategory switch
            {
                WallpaperCategory.Floor when initialBuildingBlock.ShortPrefabName.Equals("foundation", StringComparison.OrdinalIgnoreCase) ||
                                             initialBuildingBlock.ShortPrefabName.Equals("foundation.triangle", StringComparison.OrdinalIgnoreCase) => (int)WallpaperSideEnum.Internal,
                WallpaperCategory.Floor when initialBuildingBlock.ShortPrefabName.Equals("floor", StringComparison.OrdinalIgnoreCase) ||
                                             initialBuildingBlock.ShortPrefabName.Equals("floor.triangle", StringComparison.OrdinalIgnoreCase) => (int)WallpaperSideEnum.External,
                WallpaperCategory.Ceiling => (int)WallpaperSideEnum.Internal,
                _ => -1
            };


            var currentWallpaperID = initialBuildingBlock.GetWallpaperSkin(currentWallpaperSide);
            if (currentWallpaperID == 0UL || currentWallpaperSide == -1) return;

            var newWallpaperRotation = (initialBuildingBlock.GetWallpaperRotation(currentWallpaperSide) + (IsBlockTriangle(initialBuildingBlock) ? 120f : 90f)) % 360f;

            //Rotate multiple wallpaper
            if (byPassButton || player.serverInput.IsDown(BUTTON.SPRINT))
            {
                if (blocks.IsNullOrEmpty())
                    blocks = initialBuildingBlock.GetBuilding()?.buildingBlocks.ToArray();

                if (blocks == null) return;

                var direction = BlockGetDirection(initialBuildingBlock);

                SendMessage(player, Lang("UIWallpaperUpdateBuilding", player.UserIDString));
                StartCoroutine(player.userID * 2, UpdateWallpaperRotation(player, blocks, wallpaperCategory, currentWallpaperID, newWallpaperRotation, direction));
            }
            //Rotate single wallpaper
            else
            {
                if (IsBlockRoof(initialBuildingBlock)) return;
                initialBuildingBlock.SetWallpaper(currentWallpaperID, currentWallpaperSide, newWallpaperRotation);
                SendMessage(player, Lang("UIWallpaperUpdateRotationInfoText", player.UserIDString));
            }
        }

        private void OnHammerHit(BasePlayer player, HitInfo info)
        {
            if (player == null || info.HitEntity == null) return;

            if (!permission.UserHasPermission(player.UserIDString, PermissionUse)) return;


            if (IsPlayerUsingWallpaperRotationToolItem(player))
            {
                HammerWallpaperRotation(player, info);
                return;
            }


            if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out var playerSkinData) || playerSkinData.DisableBskin) return;

            var block = info.HitEntity as BuildingBlock;
            if (block == null || block.IsDestroyed) return;

            //For debug
            // Puts($"Block: wallpaperID={block.wallpaperID}, skinID={block.skinID}, prefabID={block.prefabID}, grade={block.grade}");

            // #################### WALLPAPER ####################
            if (player.serverInput.IsDown(BUTTON.RELOAD))
            {
                if (!_wallShortPrefabName.Contains(block.ShortPrefabName.ToLower()) && !_floorShortPrefabName.Contains(block.ShortPrefabName.ToLower()) &&
                    !_ceilingShortPrefabName.Contains(block.ShortPrefabName.ToLower()))
                {
                    SendMessage(player, Lang("UIWallpaperNoWall", player.UserIDString));
                    return;
                }

                var playerWallpaperSettings = playerSkinData.PlayerWallpaperSettings;
                if (playerWallpaperSettings == null || playerWallpaperSettings.WallpaperApplyMode == WallpaperApplyModeEnum.Building) return;


                var hammerHitDirectionUp = IsHitDirectionUp(info);

                WallpaperCategory? wallpaperCategory = null;
                if (_wallShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase))
                    wallpaperCategory = WallpaperCategory.Wall;

                wallpaperCategory = hammerHitDirectionUp switch
                {
                    false when _floorShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase) => WallpaperCategory.Floor,
                    true when _ceilingShortPrefabName.Contains(block.ShortPrefabName, StringComparer.OrdinalIgnoreCase) => WallpaperCategory.Ceiling,
                    _ => wallpaperCategory
                };

                if (wallpaperCategory == null)
                {
                    SendMessage(player, Lang("UIWallpaperNoWall", player.UserIDString));
                    return;
                }

                switch (wallpaperCategory)
                {
                    case WallpaperCategory.Wall when playerWallpaperSettings.WallpaperApplyToWall == false || !_wallShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                    case WallpaperCategory.Floor when playerWallpaperSettings.WallpaperApplyToFloor == false || !_floorShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                    case WallpaperCategory.Ceiling
                        when playerWallpaperSettings.WallpaperApplyToCeiling == false || !_ceilingShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                    {
                        SendMessage(player, Lang("UIUnableApplySkin", player.UserIDString));
                        return;
                    }
                }

                var wallpaperID = wallpaperCategory switch
                {
                    WallpaperCategory.Wall => playerWallpaperSettings.WallpaperWallSelectedID,
                    WallpaperCategory.Floor => playerWallpaperSettings.WallpaperFloorSelectedID,
                    WallpaperCategory.Ceiling => playerWallpaperSettings.WallpaperCeilingSelectedID
                };

                ulong selectedWallpaperSkinID;
                if (!_wallpaperSkinList[wallpaperCategory.Value].TryGetValue(wallpaperID, out var wpSkin))
                {
                    //Use skins that are not present in the configuration and which can be taken directly from Steam
                    if (permission.UserHasPermission(player.UserIDString, PermissionWallpaperUseSkinFromSteam))
                        selectedWallpaperSkinID = wallpaperID;
                    else
                    {
                        SendMessage(player, Lang("UIWallpaperIdError", player.UserIDString));
                        return;
                    }
                }
                else
                    selectedWallpaperSkinID = wpSkin.SkinID;


                if (!IsSkinOwned(player, selectedWallpaperSkinID))
                {
                    SendMessage(player, Lang("DlcSkinRequiredErrorMessage", player.UserIDString));
                    return;
                }


                var wallpaperApplyToWallSide = wallpaperCategory switch
                {
                    WallpaperCategory.Floor when block.ShortPrefabName.Equals("foundation", StringComparison.OrdinalIgnoreCase) ||
                                                 block.ShortPrefabName.Equals("foundation.triangle", StringComparison.OrdinalIgnoreCase) => WallpaperSideEnum.Internal,
                    WallpaperCategory.Floor when block.ShortPrefabName.Equals("floor", StringComparison.OrdinalIgnoreCase) ||
                                                 block.ShortPrefabName.Equals("floor.triangle", StringComparison.OrdinalIgnoreCase) => WallpaperSideEnum.External,
                    WallpaperCategory.Ceiling => WallpaperSideEnum.Internal,
                    _ => playerWallpaperSettings.WallpaperApplyToWallSide
                };

                if (selectedWallpaperSkinID != WallpaperIdForRemoval && CheckBlockHasWallpaper(block, wallpaperApplyToWallSide) &&
                    block.wallpaperID == selectedWallpaperSkinID)
                {
                    SendMessage(player, Lang("UIWallpaperWallUpdateNotRequired", player.UserIDString));
                    return;
                }

                var replaceExistingWallpaper = playerWallpaperSettings.ReplaceExistingWallpaper;
                if (selectedWallpaperSkinID != WallpaperIdForRemoval && CheckBlockHasWallpaper(block, wallpaperApplyToWallSide) &&
                    !replaceExistingWallpaper)
                {
                    SendMessage(player, Lang("UIWallpaperRemoveExistingIsDisabled", player.UserIDString));
                    return;
                }

                //Don't apply the wallpaper if you don't have building permission
                if ((_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                    !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    return;


                if (selectedWallpaperSkinID == WallpaperIdForRemoval)
                {
                    switch (wallpaperApplyToWallSide)
                    {
                        case WallpaperSideEnum.Both:
                            block.RemoveWallpaper((int)WallpaperSideEnum.Internal);
                            block.RemoveWallpaper((int)WallpaperSideEnum.External);
                            break;
                        case WallpaperSideEnum.Internal:
                        case WallpaperSideEnum.External:
                        default:
                            block.RemoveWallpaper((int)wallpaperApplyToWallSide);
                            break;
                    }
                }
                else
                {
                    if (!PayFowWallpaper(player, block, wallpaperApplyToWallSide, replaceExistingWallpaper))
                    {
                        SendMessage(player, Lang("UIWallpaperNotEnoughCloth", player.UserIDString,
                            GetWallpaperClothCost(block, wallpaperApplyToWallSide, replaceExistingWallpaper), 0, 1, 1));
                        return;
                    }

                    switch (wallpaperCategory)
                    {
                        case WallpaperCategory.Wall:
                            switch (wallpaperApplyToWallSide)
                            {
                                case WallpaperSideEnum.Both:
                                    block.SetWallpaper(selectedWallpaperSkinID, (int)WallpaperSideEnum.Internal);
                                    block.SetWallpaper(selectedWallpaperSkinID, (int)WallpaperSideEnum.External);
                                    break;
                                case WallpaperSideEnum.Internal:
                                case WallpaperSideEnum.External:
                                default:
                                    block.SetWallpaper(selectedWallpaperSkinID, (int)wallpaperApplyToWallSide);
                                    break;
                            }

                            break;
                        case WallpaperCategory.Floor:
                        {
                            if (block.ShortPrefabName.Equals("foundation", StringComparison.OrdinalIgnoreCase) ||
                                block.ShortPrefabName.Equals("foundation.triangle", StringComparison.OrdinalIgnoreCase))
                            {
                                block.SetWallpaper(selectedWallpaperSkinID, 0);
                            }

                            if (block.ShortPrefabName.Equals("floor", StringComparison.OrdinalIgnoreCase) ||
                                block.ShortPrefabName.Equals("floor.triangle", StringComparison.OrdinalIgnoreCase))
                            {
                                block.SetWallpaper(selectedWallpaperSkinID, 1);
                            }

                            break;
                        }
                        case WallpaperCategory.Ceiling:
                            block.SetWallpaper(selectedWallpaperSkinID, 0);
                            break;
                        default:
                            SendMessage(player, Lang("GenericError", player.UserIDString, "WallpaperCategory not valid - #ERR 3"));
                            return;
                    }
                }

                return;
            }
            // ###################################################

            // #################### SKIN #########################
            if (block == null || !GradesSkin.ContainsKey(block.grade)) return;

            if (!playerSkinData.PlayerSettingsGrade.ContainsKey((int)block.grade))
                return;

            var playerSettingsGrade = playerSkinData.PlayerSettingsGrade[(int)block.grade];

            var gradeID = (int)playerSettingsGrade.Grade;
            var skinID = playerSettingsGrade.SkinID;


            if (!IsSkinOwned(player, skinID))
            {
                SendMessage(player, Lang("DlcSkinRequiredErrorMessage", player.UserIDString));
                return;
            }


            if (skinID != 0 && (_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
            {
                SendMessage(player, Lang("UINotOwnerBuilding", player.UserIDString));
                return;
            }


            //Upgrade the building skin by pressing the SHIFT key and hitting with the hammer
            input = player.serverInput;
            if (input != null && input.IsDown(BUTTON.SPRINT))
            {
                CmdHandler(player.IPlayer, "", new[] { "build" });
                return;
            }

            if (gradeID == 0) return;
            // if (gradeID != 0 && player.blueprints.steamInventory.HasItem(gradeID)) return;
            // if (skinID != 0 && player.blueprints.steamInventory.HasItem((int)skinID)) return;

            //Excludes the triangle roof for Gingerbread (wood) skin, as it doesn't currently work
            /*if (gradeID == (int)BuildingGrade.Enum.Wood && skinID == 2 && block.prefabID == 870964632)
            {
                skinID = 0;
            }*/

            if (block.skinID == skinID) return;
            if (playerSkinData.EnableAnimation) block.ClientRPC(null, "DoUpgradeEffect", (int)block.grade, skinID);
            block.skinID = skinID;

            playerSettingsGrade.SkinColor = GetPlayerSkinColor(player, block.grade, playerSettingsGrade);

            if (_config.ApplyPaintColor)
                block.SetCustomColour(playerSettingsGrade.SkinColor);
            else
            {
                SetShippingContainerBlockColourForPlayer(player, 0);
                block.SetCustomColour(0);
            }

            block.ChangeGradeAndSkin(block.grade, skinID, true, true);
            SaveData();
        }

        private object? OnStructureUpgrade(BuildingBlock block, BasePlayer player, BuildingGrade.Enum grade, ulong skin)
        {
            if (player == null || block == null || !GradesSkin.ContainsKey(grade)) return null;

            //TODO verificare se puo essere rimosso, il controllo viene fatto più in basso: IsSkinOwned()
            if (skin != 0 && player.blueprints.steamInventory.HasItem((int)skin)) return null;

            if (!permission.UserHasPermission(player.UserIDString, PermissionUse)) return null;
            PlayerSettings playerSettings;
            if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.DisableBskin) return null;

            if (!_playerData.PlayerSkinData.ContainsKey(player.userID) ||
                !_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey((int)grade))
                return null;

            var playerSettingsGrade = _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[(int)grade];

            var gradeID = (int)playerSettingsGrade.Grade;
            var skinID = playerSettingsGrade.SkinID;


            if (!IsSkinOwned(player, skinID))
            {
                SendMessage(player, Lang("DlcSkinRequiredErrorMessage", player.UserIDString));
                return null;
            }


            if (gradeID == 0) return null;

            //Don't apply the skin if you don't have building permission
            if ((_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
            {
                SendMessage(player, Lang("UINotOwnerBuilding", player.UserIDString));
                skinID = 0;
            }

            if (block.skinID != 0 && block.skinID == skinID) return false;
            if (playerSettings.EnableAnimation) block.ClientRPC(null, "DoUpgradeEffect", (int)grade, skinID);
            block.skinID = skinID;

            playerSettingsGrade.SkinColor = GetPlayerSkinColor(player, grade, playerSettingsGrade);

            NextTick(() =>
            {
                if (block == null || block.IsDestroyed) return;

                block.ChangeGradeAndSkin(block.grade, skinID, true, true);

                if (_config.ApplyPaintColor)
                {
                    block.SetCustomColour(playerSettingsGrade.SkinColor);
                }
                else
                {
                    SetShippingContainerBlockColourForPlayer(player, 0);
                    block.SetCustomColour(0);
                }
            });

            SaveData();
            return null;
        }

        private uint GetPlayerSkinColor(BasePlayer player, BuildingGrade.Enum grade, PlayerSettingsGrade playerSettingsGrade)
        {
            if (player == null || playerSettingsGrade == null)
                return 0;

            if (grade != BuildingGrade.Enum.Metal)
                return 0;

            if (!_config.ApplyPaintColor)
                return 0;

            if (playerSettingsGrade.SkinColor.Equals(GetSelectedShippingContainerBlockColourForPlayer(player)))
                return playerSettingsGrade.SkinColor;

            playerSettingsGrade.SkinColor = GetSelectedShippingContainerBlockColourForPlayer(player);
            return playerSettingsGrade.SkinColor;
        }

        //Handle Building Grade Plugin
        private void OnStructureGradeUpdated(BuildingBlock buildingBlock, BasePlayer player, BuildingGrade.Enum oldGrade, BuildingGrade.Enum newGrade)
        {
            if (!permission.UserHasPermission(player.UserIDString, PermissionUse)) return;
            PlayerSettings playerSettings;
            if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.DisableBskin) return;

            if (!_playerData.PlayerSkinData.ContainsKey(player.userID) ||
                !_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey((int)newGrade))
                return;

            var animation = playerSettings.EnableAnimation;

            playerSettings.EnableAnimation = false;
            OnStructureUpgrade(buildingBlock, player, newGrade, 0);
            playerSettings.EnableAnimation = animation;
        }

        #endregion

        #region Core

        //TODO to optimize
        private void MergeDownloadedWallpaper()
        {
            _wallpaperSkinList.Clear();

            if (!_wallpaperSkinList.ContainsKey(WallpaperCategory.Wall))
                _wallpaperSkinList[WallpaperCategory.Wall] = new Dictionary<ulong, WallpaperSkinInfo>();

            if (!_wallpaperSkinList.ContainsKey(WallpaperCategory.Floor))
                _wallpaperSkinList[WallpaperCategory.Floor] = new Dictionary<ulong, WallpaperSkinInfo>();

            if (!_wallpaperSkinList.ContainsKey(WallpaperCategory.Ceiling))
                _wallpaperSkinList[WallpaperCategory.Ceiling] = new Dictionary<ulong, WallpaperSkinInfo>();


            foreach (var wallpaper in DefaultWallWallpaperSkinList)
            {
                if (_wallpaperSkinList[WallpaperCategory.Wall].ContainsKey(wallpaper.Key)) continue;
                _wallpaperSkinList[WallpaperCategory.Wall].Add(wallpaper.Key, wallpaper.Value);
            }

            foreach (var wallpaper in DefaultFloorWallpaperSkinList)
            {
                if (_wallpaperSkinList[WallpaperCategory.Floor].ContainsKey(wallpaper.Key)) continue;
                _wallpaperSkinList[WallpaperCategory.Floor].Add(wallpaper.Key, wallpaper.Value);
            }

            foreach (var wallpaper in DefaultCeilingWallpaperSkinList)
            {
                if (_wallpaperSkinList[WallpaperCategory.Ceiling].ContainsKey(wallpaper.Key)) continue;
                _wallpaperSkinList[WallpaperCategory.Ceiling].Add(wallpaper.Key, wallpaper.Value);
            }

            //Auto add new Rust wallpapers
            var rustDefaultWallWallpaperList = WallpaperSettings.WallpaperItemDef?.skins?.ToList();
            var rustDefaultFloorWallpaperList = WallpaperSettings.FlooringItemDef?.skins?.ToList();
            var rustDefaultCeilingWallpaperList = WallpaperSettings.CeilingItemDef?.skins?.ToList();

            AddNewRustWallpaperList(rustDefaultWallWallpaperList, WallpaperCategory.Wall);
            AddNewRustWallpaperList(rustDefaultFloorWallpaperList, WallpaperCategory.Floor);
            AddNewRustWallpaperList(rustDefaultCeilingWallpaperList, WallpaperCategory.Ceiling);


            // Merge Downloaded Rust Wallpaper Data
            if (!_rustWallpaperData.wallpaperDataWallList.IsNullOrEmpty())
            {
                foreach (var wallpaper in _rustWallpaperData.wallpaperDataWallList)
                {
                    if (_wallpaperSkinList[WallpaperCategory.Wall].ContainsKey(wallpaper.Key)) continue;
                    _wallpaperSkinList[WallpaperCategory.Wall].Add(wallpaper.Key, wallpaper.Value);
                }
            }

            if (!_rustWallpaperData.wallpaperDataFloorList.IsNullOrEmpty())
            {
                foreach (var wallpaper in _rustWallpaperData.wallpaperDataFloorList)
                {
                    if (_wallpaperSkinList[WallpaperCategory.Floor].ContainsKey(wallpaper.Key)) continue;
                    _wallpaperSkinList[WallpaperCategory.Floor].Add(wallpaper.Key, wallpaper.Value);
                }
            }

            if (!_rustWallpaperData.wallpaperDataCeilingList.IsNullOrEmpty())
            {
                foreach (var wallpaper in _rustWallpaperData.wallpaperDataCeilingList)
                {
                    if (_wallpaperSkinList[WallpaperCategory.Ceiling].ContainsKey(wallpaper.Key)) continue;
                    _wallpaperSkinList[WallpaperCategory.Ceiling].Add(wallpaper.Key, wallpaper.Value);
                }
            }


            // Merge Downloaded Steam Workshop Wallpaper Data
            foreach (var wallpaper in _wallpaperData.SteamWorkshopWallpaperSkinList.Where(wp => wp.Value.Enabled))
            {
                if (wallpaper.Value.ForWall == true && !_wallpaperSkinList[WallpaperCategory.Wall].ContainsKey(wallpaper.Key))
                {
                    _wallpaperSkinList[WallpaperCategory.Wall].Add(wallpaper.Key, wallpaper.Value);
                }
                else if (wallpaper.Value.ForFloor == true && !_wallpaperSkinList[WallpaperCategory.Floor].ContainsKey(wallpaper.Key))
                {
                    _wallpaperSkinList[WallpaperCategory.Floor].Add(wallpaper.Key, wallpaper.Value);
                }
                else if (wallpaper.Value.ForCeiling == true && !_wallpaperSkinList[WallpaperCategory.Ceiling].ContainsKey(wallpaper.Key))
                {
                    _wallpaperSkinList[WallpaperCategory.Ceiling].Add(wallpaper.Key, wallpaper.Value);
                }
            }
        }

        private void AddNewRustWallpaperList(List<ItemSkinDirectory.Skin>? wallpaperSkinList, WallpaperCategory category)
        {
            if (wallpaperSkinList == null || wallpaperSkinList.IsEmpty()) return;

            foreach (var wallWallpaper in wallpaperSkinList)
            {
                var wallpaper = new WallpaperSkinInfo
                {
                    SkinID = (ulong)wallWallpaper.id,
                    SkinName = wallWallpaper.invItem.displayName.english,
                    SkinDescription = "",
                    FromSteamWorkshop = false
                };

                switch (category)
                {
                    case WallpaperCategory.Wall:
                        wallpaper.ForWall = true;
                        break;
                    case WallpaperCategory.Floor:
                        wallpaper.ForFloor = true;
                        break;
                    case WallpaperCategory.Ceiling:
                        wallpaper.ForCeiling = true;
                        break;
                    default:
                        continue;
                }

                if (_wallpaperSkinList[category].ContainsKey(wallpaper.SkinID)) continue;
                _wallpaperSkinList[category].Add(wallpaper.SkinID, wallpaper);
            }
        }

        private void OpenMainPanel(BasePlayer player)
        {
            CuiHelper.DestroyUi(player, UIPanel + ".ColorChoice");
            CuiHelper.DestroyUi(player, UIPanel + ".WallpaperChoice");
            CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");

            var containerPanel = GuiHelper.Container(UIPanel, UIPanelColor, "0.1 0.1", "0.9 0.9", true);
            var headerPanel = GuiHelper.ParentPanel(containerPanel, UIPanel, UIPanel + ".h", UIPanelHeaderColor,
                "0 0.95", "0.999 1");
            GuiHelper.Text(containerPanel, headerPanel, Lang("UIHeader", player.UserIDString), "0.02 0", "1 1", 12, TextAnchor.MiddleLeft);
            GuiHelper.CloseButton(containerPanel, headerPanel, UIPanel, "X", UIPanelCloseBtnColor, "0.96 0.2", "0.985 0.8", textColor: UIPanelCloseTextColor);

            var contentPanel =
                GuiHelper.ParentPanel(containerPanel, UIPanel, UIPanel + ".c", "0 0 0 0", "0 0", "0.998 0.885");

            const float sizeX = 0.16f;
            const float sizeMaxX = 0.165f;
            const float sizeY = 0.21f;

            var anchorMaxY = 0.996f;
            var anchorMaxX = sizeX;

            GuiHelper.Text(containerPanel, contentPanel, Lang("UIGradeWood", player.UserIDString), "0 0.969", "0.15 1.08", 14,
                TextAnchor.MiddleCenter, UIGradeTextColor);

            foreach (var skinInfo in _config.SkinList[BuildingGrade.Enum.Wood])
            {
                MainItemDataEntry(ref containerPanel, contentPanel, $"{anchorMaxX - (sizeX - 0.01f)} {anchorMaxY - (sizeY - 0.01f)}",
                    $"{anchorMaxX} {anchorMaxY}",
                    skinInfo, player, null);

                anchorMaxX += sizeMaxX;
            }

            // //To be removed as soon as the Gingerbread skin for the triangular roof skin is placed
            // GuiHelper.Text(containerPanel, contentPanel, "IMPORTANT", "0.65 0.92", "0.75 0.98", 16,
            //     TextAnchor.MiddleCenter, "1 0.608 0");
            // GuiHelper.Text(containerPanel, contentPanel,
            //     "The GINGERBREAD skin works at 99%, it just doesn't work for the triangular roof. Waiting for an update from RUST", "0.60 0.82",
            //     "0.80 0.94", 12,
            //     TextAnchor.MiddleCenter, "1 0.608 0");


            GuiHelper.Text(containerPanel, contentPanel, Lang("UIGradeStone", player.UserIDString), "0 0.750", "0.15 0.790", 14,
                TextAnchor.MiddleCenter, UIGradeTextColor);

            anchorMaxX = sizeX;
            anchorMaxY = 0.745f;

            foreach (var skinInfo in _config.SkinList[BuildingGrade.Enum.Stone])
            {
                MainItemDataEntry(ref containerPanel, contentPanel, $"{anchorMaxX - (sizeX - 0.01f)} {anchorMaxY - (sizeY - 0.01f)}",
                    $"{anchorMaxX} {anchorMaxY}",
                    skinInfo, player, null);

                anchorMaxX += sizeMaxX;
            }

            GuiHelper.Text(containerPanel, contentPanel, Lang("UIGradeMetal", player.UserIDString), "0 0.500", "0.15 0.540", 14,
                TextAnchor.MiddleCenter, UIGradeTextColor);

            anchorMaxX = sizeX;
            anchorMaxY = 0.496f;

            foreach (var skinInfo in _config.SkinList[BuildingGrade.Enum.Metal])
            {
                MainItemDataEntry(ref containerPanel, contentPanel, $"{anchorMaxX - (sizeX - 0.01f)} {anchorMaxY - (sizeY - 0.01f)}",
                    $"{anchorMaxX} {anchorMaxY}",
                    skinInfo, player, null);

                anchorMaxX += sizeMaxX;
            }

            GuiHelper.Text(containerPanel, contentPanel, Lang("UIGradeTopTier", player.UserIDString), "0 0.250", "0.15 0.290", 14,
                TextAnchor.MiddleCenter, UIGradeTextColor);

            anchorMaxX = sizeX;
            anchorMaxY = 0.246f;

            foreach (var skinInfo in _config.SkinList[BuildingGrade.Enum.TopTier])
            {
                MainItemDataEntry(ref containerPanel, contentPanel, $"{anchorMaxX - (sizeX - 0.01f)} {anchorMaxY - (sizeY - 0.01f)}",
                    $"{anchorMaxX} {anchorMaxY}",
                    skinInfo, player, null);

                anchorMaxX += sizeMaxX;
            }

            GuiHelper.Text(containerPanel, contentPanel, Lang("UIBskinDisableText", player.UserIDString), "0.68 0.07", "0.78 0.11", 12,
                TextAnchor.MiddleCenter);
            PlayerSettings value;
            var isBskinDisabled = !_playerData.PlayerSkinData.TryGetValue(player.userID, out value) || value.DisableBskin;
            GuiHelper.Button(containerPanel, contentPanel, $"buildingskin.cmd disableBskin",
                isBskinDisabled ? "YES" : "NO",
                isBskinDisabled ? UIRedColor : UIGreenColor, "0.68 0.02", "0.78 0.06", 12);

            GuiHelper.Text(containerPanel, contentPanel, Lang("UIMessageText", player.UserIDString), "0.79 0.07", "0.89 0.11", 12,
                TextAnchor.MiddleCenter);
            PlayerSettings value2;
            var showMessage = !_playerData.PlayerSkinData.TryGetValue(player.userID, out value2) || value2.EnableMessage;
            GuiHelper.Button(containerPanel, contentPanel, $"buildingskin.cmd message",
                showMessage ? Lang("UIMessageActivated", player.UserIDString) : Lang("UIMessageDeactivated", player.UserIDString),
                showMessage ? UIGreenColor : UIRedColor, "0.79 0.02", "0.89 0.06", 12);

            GuiHelper.Text(containerPanel, contentPanel, Lang("UIColorChoiceText", player.UserIDString), "0.90 0.07", "0.99 0.11", 12,
                TextAnchor.MiddleCenter);
            GuiHelper.Button(containerPanel, contentPanel, $"bskin color", Lang("UIColorChoiceText", player.UserIDString), UIGreenColor,
                "0.90 0.02", "0.99 0.06", 12);

            var wallpaperPanel = GuiHelper.ParentPanel(containerPanel, UIPanel, UIPanel + ".w", UIItemColor, "0.56 0.015", "0.66 0.175");
            GuiHelper.Text(containerPanel, wallpaperPanel, Lang("UIWallpaperText", player.UserIDString).ToUpper(), "0 0.8", "1 1.0", 12,
                TextAnchor.MiddleCenter, UIGreenColor);
            // GuiHelper.Button(containerPanel, wallpaperPanel, $"bskin color", Lang("UIWallpaperText", player.UserIDString), 
            //     UIGreenColor, "0 0.8", "1 1.0", 12);
            GuiHelper.ImageSkin(containerPanel, wallpaperPanel, _wallpaperWallItemDef.itemid, 0, "0.1 0.0", "0.90 0.8");
            GuiHelper.Button(containerPanel, wallpaperPanel, "buildingskin.cmd wallpaperChoice", "", Transparent, "0 0.0", "1 1");

            CuiHelper.AddUi(player, containerPanel);
        }

        private void MainItemDataEntry(ref CuiElementContainer container, string panel, string uiMin, string uiMax,
            Configuration.SkinInfo skinInfo, BasePlayer player, string useCommand)
        {
            var newPanel = GuiHelper.ParentPanel(container, panel, CuiHelper.GetGuid(), UIItemColor, uiMin, uiMax);
            GuiHelper.Image(container, newPanel, GetImage(skinInfo.SkinImageUrl), "0.10 0.2", "0.9 0.8");
            GuiHelper.Text(container, newPanel, skinInfo.SkinName, "0.05 0.78", "0.95 1.0", 12,
                TextAnchor.MiddleCenter, HasSelectedGrade(player, skinInfo.Grade, skinInfo.SkinID) ? UISelectedGradeTextColor : "1 1 1 1");

            if (!IsSkinOwned(player, skinInfo.SkinID))
            {
                GuiHelper.Text(container, newPanel, $"<b>{Lang("UIDlcSkinRequired", player.UserIDString)}</b>", "0.05 0.0", "0.95 0.14", 12, TextAnchor.MiddleCenter, UIRedColor);
                return;
            }

            GuiHelper.Button(container, newPanel, useCommand, "", Transparent, "0 0.225", "1 1");
            GuiHelper.Button(container, newPanel, $"buildingskin.cmd apply {(int)skinInfo.Grade} {skinInfo.SkinID}", Lang("UIApply", player.UserIDString),
                UIItemEditBtnColor, "0 0.00", "0.48 0.16", 12);
        }

        private void OpenColorChoicePanel(BasePlayer player, bool requireCursor = true)
        {
            CuiHelper.DestroyUi(player, UIPanel + ".WallpaperChoice");
            CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");

            var mainContainer = GuiHelper.Container(UIPanel + ".ColorChoice", UIPanelColor, "0.40 0.30", "0.60 0.78", requireCursor);
            var headerPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".ColorChoice", UIPanel + ".ColorChoice.h", UIPanelHeaderColor, "0 0.92",
                "0.997 1");

            GuiHelper.Text(mainContainer, headerPanel, Lang("UIHeaderColorChoice", player.UserIDString), "0.02 0", "1 1", 12, TextAnchor.MiddleLeft);
            GuiHelper.CloseButton(mainContainer, headerPanel, UIPanel + ".ColorChoice", "X", UIPanelCloseBtnColor, "0.92 0.2", "0.985 0.8",
                textColor: UIPanelCloseTextColor);

            var contentPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".ColorChoice", UIPanel + ".ColorChoice.h", Transparent, "0 0",
                "0.998 0.92");

            const float sizeX = 0.22f;
            const float sizeY = 0.16f;

            var anchorMaxY = 0.975f;
            var anchorMaxX = sizeX + 0.008f;

            foreach (var color in _metalGradeColor)
            {
                ColorChoiceEntry(ref mainContainer, contentPanel, $"{anchorMaxX - (sizeX - 0.01f)} {anchorMaxY - (sizeY - 0.01f)}",
                    $"{anchorMaxX} {anchorMaxY}", color.Value, color.Key);
                anchorMaxX += 0.25f;
                if (!(anchorMaxX > 1)) continue;
                anchorMaxX = sizeX + 0.008f;
                anchorMaxY = anchorMaxY - sizeY - 0.01f;
            }

            PlayerSettings playerSettings;
            _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);

            var autoApplyColor = false;
            var autoApplySkin = false;

            if (playerSettings != null)
            {
                PlayerSettingsGrade playerSettingsGrade;
                playerSettings.PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out playerSettingsGrade);
                if (playerSettingsGrade != null)
                {
                    autoApplyColor = playerSettingsGrade.AutoApplyColor;
                    autoApplySkin = playerSettingsGrade.AutoApplySkin;
                }
            }

            //Auto Apply Color button
            GuiHelper.ButtonCircle(mainContainer, contentPanel, $"buildingskin.cmd changeAutoApplyColor",
                autoApplyColor ? UICheckText : UIUnCheckText,
                autoApplyColor ? UICheckColor : UIUnCheckColor, "0.02 0.02", "0.12 0.10");

            GuiHelper.Text(mainContainer, contentPanel, Lang("UIColorAutoApplyColor", player.UserIDString), "0.14 0.02", "0.44 0.14", 9);

            //Auto Apply Skin button
            GuiHelper.ButtonCircle(mainContainer, contentPanel, $"buildingskin.cmd changeAutoApplySkin",
                autoApplySkin ? UICheckText : UIUnCheckText,
                autoApplySkin ? UICheckColor : UIUnCheckColor, "0.50 0.02", "0.60 0.10");
            GuiHelper.Text(mainContainer, contentPanel, Lang("UIColorAutoApplySkin", player.UserIDString), "0.62 0.02", "0.94 0.14", 9);

            CuiHelper.AddUi(player, mainContainer);
        }

        private void ColorChoiceEntry(ref CuiElementContainer container, string panel, string uiMin, string uiMax, string color, uint colorId)
        {
            var newPanel = GuiHelper.ParentPanel(container, panel, CuiHelper.GetGuid(), UIItemColor, uiMin, uiMax);
            if (colorId == 0)
            {
                GuiHelper.Image(container, newPanel, GetImage(RainbowImageURL), "0 0", "1 1");
                GuiHelper.Button(container, newPanel, $"bskin color {colorId}", "", Transparent, "0 0", "1 1");
            }
            else
            {
                GuiHelper.Button(container, newPanel, $"bskin color {colorId}", "", color, "0 0", "1 1");
            }
        }

        private void OpenWallpaperChoicePanel(BasePlayer player, ItemsPagination itemsPagination, bool requireCursor = true)
        {
            CuiHelper.DestroyUi(player, UIPanel + ".ColorChoice");
            CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");

            var mainContainer = GuiHelper.Container(UIPanel + ".WallpaperChoice", UIPanelColor2, "0.30 0.10", "0.70 0.90", requireCursor);
            var headerPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.h",
                UIPanelHeaderColor, "0 0.95", "0.997 1");

            GuiHelper.Text(mainContainer, headerPanel, Lang("UIHeaderWallpaperChoice", player.UserIDString), "0.02 0", "1 1", 12, TextAnchor.MiddleLeft);
            GuiHelper.CloseButton(mainContainer, headerPanel, UIPanel + ".WallpaperChoice", "X", UIPanelCloseBtnColor, "0.92 0.2", "0.985 0.8",
                textColor: UIPanelCloseTextColor, command: "buildingskin.cmd closeWallpaperChoice");

            var contentPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.h",
                Transparent, "0 0", "0.998 0.92");

            GuiHelper.Text(mainContainer, contentPanel, Lang("UIModeText", player.UserIDString), "0.02 0.95", "0.10 1.0", 12,
                TextAnchor.MiddleLeft, UIGradeTextColor);

            var wallpaperModePanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.wmp",
                Transparent, "0.14 0.88", "0.998 0.92");

            PlayerSettings? playerSettings;
            _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);

            playerSettings ??= new PlayerSettings();


            var wallpaperApplyMode = playerSettings.PlayerWallpaperSettings!.WallpaperApplyMode;
            var wallpaperWallMode = playerSettings.PlayerWallpaperSettings!.WallpaperApplyToWall;
            var wallpaperFloorMode = playerSettings.PlayerWallpaperSettings!.WallpaperApplyToFloor;
            var wallpaperCeilingMode = playerSettings.PlayerWallpaperSettings!.WallpaperApplyToCeiling;

            var wallpaperCategory = GetPlayerWallpaperCategory(player);

            //Wallpaper Apply Mode buttons
            GuiHelper.ButtonCircle(mainContainer, wallpaperModePanel, $"buildingskin.cmd changeWallpaperApplyMode",
                wallpaperApplyMode == WallpaperApplyModeEnum.Building ? UICheckText : UIUnCheckText,
                wallpaperApplyMode == WallpaperApplyModeEnum.Building ? UICheckColor : UIUnCheckColor, "0.0 0.0", "0.05 1");
            GuiHelper.Text(mainContainer, wallpaperModePanel, Lang("UIModeBuildingText", player.UserIDString),
                "0.06 0", "0.26 1", 12, TextAnchor.MiddleLeft);

            //Auto Apply Skin button
            GuiHelper.ButtonCircle(mainContainer, wallpaperModePanel, $"buildingskin.cmd changeWallpaperApplyMode",
                wallpaperApplyMode == WallpaperApplyModeEnum.Manual ? UICheckText : UIUnCheckText,
                wallpaperApplyMode == WallpaperApplyModeEnum.Manual ? UICheckColor : UIUnCheckColor, "0.32 0.0", "0.37 1");
            GuiHelper.Text(mainContainer, wallpaperModePanel, Lang("UIModeManualText", player.UserIDString),
                "0.38 0", "0.68 1", 12, TextAnchor.MiddleLeft);

            var cursorImagePanel = GuiHelper.ParentPanelCircle(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.cip",
                requireCursor ? UIUnCheckColor : UICheckColor, "0.78 0.88", "0.83 0.92");
            GuiHelper.Image(mainContainer, cursorImagePanel, GetImage(CursorImageURL), "0.1 0.1", "0.9 0.9");
            GuiHelper.ButtonCircle(mainContainer, cursorImagePanel, $"buildingskin.cmd wallpaperPanelCursor",
                "", Transparent, "0 0", "1 1");

            GuiHelper.Text(mainContainer, wallpaperModePanel, Lang("UiCursorText", player.UserIDString),
                "0.83 0", "0.98 1", 12, TextAnchor.MiddleLeft);


            var wallpaperReplace = playerSettings.PlayerWallpaperSettings!.ReplaceExistingWallpaper;

            var wallpaperReplacePanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.wr",
                Transparent, "0.14 0.82", "0.998 0.86");

            GuiHelper.Text(mainContainer, contentPanel, Lang("UIReplaceText", player.UserIDString), "0.02 0.885", "0.10 0.935", 12,
                TextAnchor.MiddleLeft, UIGradeTextColor);

            //Wallpaper Replace buttons
            GuiHelper.ButtonCircle(mainContainer, wallpaperReplacePanel, "buildingskin.cmd changeWallpaperReplaceMode",
                wallpaperReplace ? UICheckText : UIUnCheckText,
                wallpaperReplace ? UICheckColor : UIUnCheckColor, "0.0 0.0", "0.05 1");
            GuiHelper.Text(mainContainer, wallpaperReplacePanel, Lang("UIReplaceExistingWallpaperText", player.UserIDString),
                "0.06 0", "0.46 1", 12, TextAnchor.MiddleLeft);


            GuiHelper.Button(mainContainer, wallpaperReplacePanel, "buildingskin.cmd giveWallpaperRotationTool", Lang("UiWallpaperRotationTool", player.UserIDString),
                UIOrangeColor, "0.66 0.0", "0.98 1", fontSize: 12);


            var wallpaperApplyToWallSide = playerSettings.PlayerWallpaperSettings!.WallpaperApplyToWallSide;

            var wallpaperSideToApply = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.wsta",
                Transparent, "0.36 0.76", "0.998 0.80");

            GuiHelper.Text(mainContainer, contentPanel, Lang("UiApplyToWallSide", player.UserIDString), "0.02 0.82", "0.30 0.87", 12,
                TextAnchor.MiddleLeft, UIGradeTextColor);

            //Wallpaper Apply To Wall Side button: Internal
            GuiHelper.ButtonCircle(mainContainer, wallpaperSideToApply, $"buildingskin.cmd changeWallpaperApplyToWallSide {(int)WallpaperSideEnum.Internal}",
                wallpaperApplyToWallSide == WallpaperSideEnum.Internal ? UICheckText : UIUnCheckText,
                wallpaperApplyToWallSide == WallpaperSideEnum.Internal ? UICheckColor : UIUnCheckColor, "0.0 0.0", "0.06 1");
            GuiHelper.Text(mainContainer, wallpaperSideToApply, Lang("UiApplyToWallSide_Internal", player.UserIDString),
                "0.07 0", "0.27 1", 12, TextAnchor.MiddleLeft);

            //Wallpaper Apply To Wall Side button: External
            GuiHelper.ButtonCircle(mainContainer, wallpaperSideToApply, $"buildingskin.cmd changeWallpaperApplyToWallSide {(int)WallpaperSideEnum.External}",
                wallpaperApplyToWallSide == WallpaperSideEnum.External ? UICheckText : UIUnCheckText,
                wallpaperApplyToWallSide == WallpaperSideEnum.External ? UICheckColor : UIUnCheckColor, "0.30 0.0", "0.36 1");
            GuiHelper.Text(mainContainer, wallpaperSideToApply, Lang("UiApplyToWallSide_External", player.UserIDString),
                "0.37 0", "0.57 1", 12, TextAnchor.MiddleLeft);

            //Wallpaper Apply To Wall Side button: Both
            GuiHelper.ButtonCircle(mainContainer, wallpaperSideToApply, $"buildingskin.cmd changeWallpaperApplyToWallSide {(int)WallpaperSideEnum.Both}",
                wallpaperApplyToWallSide == WallpaperSideEnum.Both ? UICheckText : UIUnCheckText,
                wallpaperApplyToWallSide == WallpaperSideEnum.Both ? UICheckColor : UIUnCheckColor, "0.60 0.0", "0.66 1");
            GuiHelper.Text(mainContainer, wallpaperSideToApply, Lang("UiApplyToWallSide_Both", player.UserIDString),
                "0.67 0", "0.87 1", 12, TextAnchor.MiddleLeft);


            //Wall, Floor, Ceiling wallpaper settings
            GuiHelper.Text(mainContainer, contentPanel, Lang("UiApplyToFloorCeiling", player.UserIDString), "0.02 0.77", "0.20 0.82", 12,
                TextAnchor.MiddleLeft, UIGradeTextColor);

            var wallpaperWallFloorCeilingSettings = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.wwfcs",
                Transparent, "0.14 0.71", "0.998 0.75");

            //Wall wallpaper settings
            GuiHelper.ButtonCircle(mainContainer, wallpaperWallFloorCeilingSettings, "buildingskin.cmd changeWallpaperApplyToWall",
                wallpaperWallMode ? UICheckText : UIUnCheckText,
                wallpaperWallMode ? UICheckColor : UIUnCheckColor, "0.07 0.0", "0.12 1");
            GuiHelper.Text(mainContainer, wallpaperWallFloorCeilingSettings, Lang("UiWall", player.UserIDString),
                "0.13 0", "0.33 1", 12, TextAnchor.MiddleLeft);

            //Floor wallpaper settings
            GuiHelper.ButtonCircle(mainContainer, wallpaperWallFloorCeilingSettings, "buildingskin.cmd changeWallpaperApplyToFloor",
                wallpaperFloorMode ? UICheckText : UIUnCheckText,
                wallpaperFloorMode ? UICheckColor : UIUnCheckColor, "0.34 0.0", "0.39 1");
            GuiHelper.Text(mainContainer, wallpaperWallFloorCeilingSettings, Lang("UiFloor", player.UserIDString),
                "0.40 0", "0.60 1", 12, TextAnchor.MiddleLeft);

            //Ceiling wallpaper settings
            GuiHelper.ButtonCircle(mainContainer, wallpaperWallFloorCeilingSettings, "buildingskin.cmd changeWallpaperApplyToCeiling",
                wallpaperCeilingMode ? UICheckText : UIUnCheckText,
                wallpaperCeilingMode ? UICheckColor : UIUnCheckColor, "0.61 0.0", "0.66 1");
            GuiHelper.Text(mainContainer, wallpaperWallFloorCeilingSettings, Lang("UiCeiling", player.UserIDString),
                "0.67 0", "0.87 1", 12, TextAnchor.MiddleLeft);


            //Wallpaper show by type: Wall, Floor, Ceiling
            GuiHelper.Text(mainContainer, contentPanel, Lang("UiWallpaperShow", player.UserIDString), "0.02 0.71", "0.30 0.75", 12,
                TextAnchor.MiddleLeft, UIGradeTextColor);

            var wallpaperShowSettings = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.wss",
                Transparent, "0.22 0.65", "0.998 0.69");

            GuiHelper.Button(mainContainer, wallpaperShowSettings, "buildingskin.cmd wallpaperShowWall", Lang("UiWall", player.UserIDString),
                wallpaperCategory == WallpaperCategory.Wall ? UIGreenColor : UiWallpaperShowDetailColor,
                "0.15 0.0", "0.35 1");

            GuiHelper.Button(mainContainer, wallpaperShowSettings, "buildingskin.cmd wallpaperShowFloor", Lang("UiFloor", player.UserIDString),
                wallpaperCategory == WallpaperCategory.Floor ? UIGreenColor : UiWallpaperShowDetailColor,
                "0.40 0.0", "0.60 1");

            GuiHelper.Button(mainContainer, wallpaperShowSettings, "buildingskin.cmd wallpaperShowCeiling", Lang("UiCeiling", player.UserIDString),
                wallpaperCategory == WallpaperCategory.Ceiling ? UIGreenColor : UiWallpaperShowDetailColor,
                "0.65 0.0", "0.85 1");

            var wallpaperSkinListPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperChoice", UIPanel + ".WallpaperChoice.wsl",
                Transparent, "0 0.14", "0.998 0.64");

            const float sizeX = 0.16f;
            const float sizeY = 0.185f;

            var anchorMaxY = 0.975f;
            var anchorMaxX = sizeX + 0.008f;

            //Pagination of wallpapers
            var pagedWallpaperSkinList = _wallpaperSkinList[wallpaperCategory]
                .Where(wsi => IsSkinOwned(player, wsi.Key))
                .Skip(itemsPagination.CurrentPage * WallpaperItemsPerPages)
                .Take(WallpaperItemsPerPages)
                .ToDictionary(x => x.Key, x => x.Value);

            foreach (var wallpaperSkin in pagedWallpaperSkinList)
            {
                WallpaperChoiceEntry(ref mainContainer, wallpaperSkinListPanel, $"{anchorMaxX - (sizeX - 0.01f)} {anchorMaxY - (sizeY - 0.01f)}",
                    $"{anchorMaxX} {anchorMaxY}", wallpaperSkin.Key, wallpaperSkin.Value, wallpaperCategory);
                anchorMaxX += 0.16f;
                if (!(anchorMaxX > 1)) continue;
                anchorMaxX = sizeX + 0.008f;
                anchorMaxY = anchorMaxY - sizeY - 0.01f;
            }

            var wallpaperCostValue = permission.UserHasPermission(player.UserIDString, PermissionWallpaperPlaceNoCost)
                ? Lang("UiWallpaperCostValueZero", player.UserIDString)
                : Lang("UiWallpaperCostValue", player.UserIDString, GetWallpaperClothCost(null, null, false));

            GuiHelper.Text(mainContainer, wallpaperSkinListPanel, Lang("UiCostText", player.UserIDString) + ":",
                "0.52 -0.085", "0.60 0.0", 12, TextAnchor.MiddleLeft, UIGradeTextColor);
            GuiHelper.Text(mainContainer, wallpaperSkinListPanel, wallpaperCostValue,
                "0.60 -0.12", "0.98 -0.02", 12, TextAnchor.UpperLeft, UIGradeTextColor);


            var wallpapersHasPreviousPage = WallpapersHasPreviousPage(player);
            var wallpapersHasNextPage = WallpapersHasNextPage(player, wallpaperCategory);

            if (wallpapersHasPreviousPage)
                GuiHelper.Button(mainContainer, wallpaperSkinListPanel, $"buildingskin.cmd previousWallpapersPage", "", UIRedColor,
                    "0.02 -0.12", "0.12 0.0", "assets/icons/dir_left.png", "assets/icons/iconmaterial.mat", 16);

            if (wallpapersHasPreviousPage || wallpapersHasNextPage)
                GuiHelper.Text(mainContainer, wallpaperSkinListPanel, Lang("UIPageText", player.UserIDString),
                    "0.10 -0.12", "0.22 0.0", 12, TextAnchor.MiddleCenter, UIGradeTextColor);

            if (wallpapersHasNextPage)
                GuiHelper.Button(mainContainer, wallpaperSkinListPanel, $"buildingskin.cmd nextWallpapersPage", "", UIGreenColor,
                    "0.22 -0.12", "0.32 0.0", "assets/icons/dir_right.png", "assets/icons/iconmaterial.mat", 16);


            GuiHelper.Text(mainContainer, contentPanel, Lang("UIModeManualUsageInfoText", player.UserIDString),
                "0.02 0", "0.99 0.10", 12, TextAnchor.MiddleCenter);

            CuiHelper.AddUi(player, mainContainer);
        }

        private void WallpaperChoiceEntry(ref CuiElementContainer container, string panel, string uiMin, string uiMax, ulong wallpaperID, WallpaperSkinInfo wallpaperSkinInfo,
            WallpaperCategory wallpaperCategory)
        {
            var newPanel = GuiHelper.ParentPanel(container, panel, CuiHelper.GetGuid(), UIItemColor, uiMin, uiMax);
            if (wallpaperID == WallpaperIdForRemoval)
            {
                GuiHelper.Image(container, newPanel, GetImage(WallpaperRemoveImageURL), "0 0", "1 1");
            }
            else
            {
                var itemID = wallpaperSkinInfo switch
                {
                    { ForFloor: true } => _wallpaperFloorItemDef.itemid,
                    { ForCeiling: true } => _wallpaperCeilingItemDef.itemid,
                    _ => _wallpaperWallItemDef.itemid
                };

                GuiHelper.ImageSkin(container, newPanel, itemID, wallpaperSkinInfo.SkinID, "0 0", "1 1");
            }

            GuiHelper.Button(container, newPanel, $"bskin wallpaper {wallpaperCategory} {wallpaperID}", "", Transparent, "0 0", "1 1");

            if (wallpaperID != WallpaperIdForRemoval)
                GuiHelper.Button(container, newPanel, $"buildingskin.cmd wallpaperShowDetail {wallpaperID}", "", UiInfoBtnColor,
                    "0.75 0.75", "1.0 1.0", "assets/icons/connection.png", "assets/icons/iconmaterial.mat", 16, textColor: UIRedColor);
        }

        #endregion

        #region Commands

        private void CmdHandler(IPlayer ipPlayer, string command, string[] arg)
        {
            var player = ipPlayer?.Object as BasePlayer;
            if (player == null) return;
            if (!permission.UserHasPermission(player.UserIDString, PermissionUse))
            {
                SendMessage(player, Lang("UINoPermissions", player.UserIDString));
                return;
            }

            if (arg.Length == 0)
            {
                OpenMainPanel(player);
                return;
            }

            if (_runningCoroutines.ContainsKey(player.userID))
            {
                SendMessage(player, Lang("UIUpdateProgress", player.UserIDString));
                return;
            }

            if (arg.Length > 0 && _config.BuildingBlocked && !player.CanBuild() && !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
            {
                SendMessage(player, Lang("UIBuildingBlocked", player.UserIDString));
                return;
            }

            switch (arg[0].ToLower())
            {
                case "b":
                case "build":
                {
                    if (!permission.UserHasPermission(player.UserIDString, PermissionBuild))
                    {
                        SendMessage(player, Lang("UINoPermissions", player.UserIDString));
                        return;
                    }

                    PlayerSettings playerSettings;
                    if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.DisableBskin) return;

                    var entity = GetLookEntity(player);
                    if (entity == null)
                    {
                        SendMessage(player, Lang("UINotFoundBuilding", player.UserIDString));
                        return;
                    }

                    if (!IsBuildingBlockOwner(entity, player.userID) && !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    {
                        SendMessage(player, Lang("UINotOwnerBuilding", player.UserIDString));
                        return;
                    }

                    var blocks = entity.GetBuilding()?.buildingBlocks.ToArray();
                    if (blocks == null) return;
                    SendMessage(player, Lang("UIUpdateBuilding", player.UserIDString));
                    StartCoroutine(player.userID, UpgradeSkin(player, blocks, null, true));
                    break;
                }
                case "a":
                case "all":
                {
                    if (!permission.UserHasPermission(player.UserIDString, PermissionAll))
                    {
                        SendMessage(player, Lang("UINoPermissions", player.UserIDString));
                        return;
                    }

                    PlayerSettings playerSettings;
                    if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.DisableBskin) return;

                    ulong owner = 0;
                    if (arg.Length > 1 && !ulong.TryParse(arg[1], out owner))
                    {
                        SendMessage(player, lang.GetMessage("UINotFoundPlayer", this, player.UserIDString));
                        return;
                    }

                    var targetOwner = arg.Length > 1 ? owner : player.userID.Get();
                    if (!permission.UserHasPermission(player.UserIDString, PermissionAdmin) && targetOwner != player.userID)
                    {
                        SendMessage(player, Lang("UINoPermissions", player.UserIDString));
                        return;
                    }

                    var blockOwner = BaseNetworkable.serverEntities.OfType<BuildingBlock>().Where(x => x.OwnerID == targetOwner).ToArray();
                    if (blockOwner.Length == 0)
                    {
                        SendMessage(player, Lang("UINotFoundBlocks", player.UserIDString));
                        return;
                    }

                    SendMessage(player,
                        targetOwner != player.userID
                            ? Lang("UIUpdateAllTarget", player.UserIDString)
                            : Lang("UIUpdateAll", player.UserIDString));
                    StartCoroutine(player.userID, UpgradeSkin(player, blockOwner, null, true));
                    break;
                }
                case "c":
                case "color":
                {
                    if (!permission.UserHasPermission(player.UserIDString, PermissionAll))
                    {
                        SendMessage(player, Lang("UINoPermissions", player.UserIDString));
                        return;
                    }

                    PlayerSettings playerSettings;
                    if (!_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.DisableBskin) return;

                    if (!_playerData.PlayerSkinData.ContainsKey(player.userID) ||
                        !_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey((int)BuildingGrade.Enum.Metal))
                    {
                        SendMessage(player, Lang("UIMetalSkinError", player.UserIDString));
                        return;
                    }

                    if (arg.Length == 1)
                    {
                        OpenColorChoicePanel(player);
                        return;
                    }

                    uint skinColor;
                    if (!(arg.Length > 1) || !uint.TryParse(arg[1], out skinColor) || skinColor > 16)
                    {
                        SendMessage(player, Lang("UIColorError", player.UserIDString));
                        return;
                    }

                    var entity = GetLookEntity(player);

                    PlayerSettingsGrade playerSettingsGrade;
                    if (entity == null || entity.grade != BuildingGrade.Enum.Metal)
                    {
                        if (!_config.ApplyPaintColor) return;

                        //Save skin as a preferred color
                        _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out playerSettingsGrade);
                        if (playerSettingsGrade == null) return;

                        playerSettingsGrade.SkinColor = skinColor;
                        SetShippingContainerBlockColourForPlayer(player, skinColor);

                        SendMessage(player, Lang("UISaveDefaultColor", player.UserIDString));
                        SaveData();

                        return;
                    }

                    if (!IsBuildingBlockOwner(entity, player.userID) && !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    {
                        SendMessage(player, Lang("UINotOwnerBuilding", player.UserIDString));
                        return;
                    }

                    var blocks = entity.GetBuilding()?.buildingBlocks.ToArray();
                    if (blocks == null) return;

                    //Save skin as a preferred color
                    if (_config.ApplyPaintColor)
                    {
                        _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out playerSettingsGrade);
                        if (playerSettingsGrade != null)
                        {
                            playerSettingsGrade.SkinColor = skinColor;
                            SetShippingContainerBlockColourForPlayer(player, skinColor);

                            SendMessage(player, Lang("UISaveDefaultColor", player.UserIDString));
                            SaveData();
                        }
                    }

                    var autoApplyColor = false;
                    var autoApplySkin = false;

                    playerSettings.PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out playerSettingsGrade);
                    if (playerSettingsGrade != null)
                    {
                        autoApplyColor = playerSettingsGrade.AutoApplyColor;
                        autoApplySkin = playerSettingsGrade.AutoApplySkin;
                    }

                    if (autoApplyColor)
                    {
                        SendMessage(player, Lang("UIUpdateBuilding", player.UserIDString));
                        StartCoroutine(player.userID, UpgradeSkin(player, blocks, skinColor, autoApplySkin));
                    }

                    break;
                }
                case "wp":
                case "wallpaper":
                {
                    if (!permission.UserHasPermission(player.UserIDString, PermissionAll))
                    {
                        SendMessage(player, Lang("UINoPermissions", player.UserIDString));
                        return;
                    }

                    _playerData.PlayerSkinData.TryGetValue(player.userID, out var playerSettings);
                    if (playerSettings == null)
                    {
                        playerSettings = new PlayerSettings
                        {
                            SteamID = player.userID,
                            Username = player.displayName
                        };
                        _playerData.PlayerSkinData.Add(player.userID, playerSettings);
                        SaveData();
                    }

                    var playerWallpaperSettings = playerSettings.PlayerWallpaperSettings;
                    if (playerWallpaperSettings == null)
                    {
                        playerWallpaperSettings = new PlayerWallpaperSettings
                        {
                            WallpaperApplyMode = WallpaperApplyModeEnum.Building,
                            WallpaperApplyToWallSide = WallpaperSideEnum.Internal,
                            WallpaperApplyToFloor = false,
                            WallpaperApplyToCeiling = false,
                            ReplaceExistingWallpaper = false
                        };
                        playerSettings.PlayerWallpaperSettings = playerWallpaperSettings;
                        SaveData();
                    }

                    if (playerSettings.DisableBskin)
                    {
                        SendMessage(player, Lang("UIBskinIsDisabledText", player.UserIDString));
                        return;
                    }

                    if (arg.Length == 1)
                    {
                        if (_playersWallpaperPagination.TryGetValue(player.userID, out var value))
                            value.CurrentPage = 0;
                        else
                            _playersWallpaperPagination.Add(player.userID, new ItemsPagination());

                        OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                        return;
                    }

                    //#################### Wallpaper usage by wallpaperID or Steam Direct wallpaper usage by wallpaperID ####################
                    if (arg.Length < 3)
                    {
                        SendMessage(player, Lang("UISteamWallpaperDirectUseInvalidSyntax", player.UserIDString));
                        SendConsoleMessage(Lang("UISteamWallpaperDirectUseInvalidSyntax"));
                        return;
                    }

                    var wallpaperType = CapitalizeFirstLetter(arg[1]);
                    if (!Enum.IsDefined(typeof(WallpaperCategory), wallpaperType) || !Enum.TryParse(wallpaperType, out WallpaperCategory wallpaperCategory))
                    {
                        SendMessage(player, Lang("UIImportWallpaperMissingParameter", player.UserIDString));
                        SendConsoleMessage(Lang("UIImportWallpaperMissingParameter"));
                        return;
                    }

                    if (!ulong.TryParse(arg[2], out var wallpaperID))
                    {
                        SendMessage(player, Lang("UIWallpaperIdNotValid", player.UserIDString));
                        SendConsoleMessage(Lang("UIWallpaperIdNotValid"));
                        return;
                    }

                    if (!permission.UserHasPermission(player.UserIDString, PermissionWallpaperUseSkinFromSteam) && !_wallpaperSkinList[wallpaperCategory].ContainsKey(wallpaperID))
                    {
                        SendMessage(player, Lang("UIWallpaperIdError", player.UserIDString));
                        SendConsoleMessage(Lang("UIWallpaperIdError"));
                        return;
                    }


                    if (!IsSkinOwned(player, wallpaperID))
                    {
                        SendMessage(player, Lang("DlcSkinRequiredErrorMessage", player.UserIDString));
                        return;
                    }


                    playerWallpaperSettings = playerSettings.PlayerWallpaperSettings!;
                    switch (wallpaperCategory)
                    {
                        //Save ID as preferred Wallpaper
                        case WallpaperCategory.Wall:
                            playerWallpaperSettings.WallpaperWallSelectedID = wallpaperID;
                            break;
                        case WallpaperCategory.Floor:
                            playerWallpaperSettings.WallpaperFloorSelectedID = wallpaperID;
                            break;
                        case WallpaperCategory.Ceiling:
                            playerWallpaperSettings.WallpaperCeilingSelectedID = wallpaperID;
                            break;
                        default:
                            SendMessage(player, Lang("GenericError", player.UserIDString, "WallpaperCategory not valid - #ERR 1"));
                            SendConsoleMessage(Lang("GenericError", player.UserIDString, "WallpaperCategory not valid - #ERR 1"));
                            return;
                    }

                    SaveData();

                    var initialBuildingBlock = GetLookEntity(player);
                    if (initialBuildingBlock == null || playerWallpaperSettings.WallpaperApplyMode == WallpaperApplyModeEnum.Manual)
                    {
                        SendMessage(player, Lang("UISaveDefaultWallpaper", player.UserIDString));
                        SendConsoleMessage(Lang("UISaveDefaultWallpaper", player.UserIDString));
                        return;
                    }

                    if (!IsBuildingBlockOwner(initialBuildingBlock, player.userID) && !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    {
                        SendMessage(player, Lang("UINotOwnerBuilding", player.UserIDString));
                        SendConsoleMessage(Lang("UINotOwnerBuilding", player.UserIDString));
                        return;
                    }

                    var blocks = initialBuildingBlock.GetBuilding()?.buildingBlocks.ToArray();
                    if (blocks == null) return;

                    if (playerWallpaperSettings.WallpaperApplyMode == WallpaperApplyModeEnum.Building)
                    {
                        SendMessage(player, Lang("UIWallpaperUpdateBuilding", player.UserIDString));
                        SendConsoleMessage(Lang("UIWallpaperUpdateBuilding", player.UserIDString));
                        StartCoroutine(player.userID, UpgradeWallpaperSkin(player, blocks, wallpaperCategory, wallpaperID, initialBuildingBlock));
                    }

                    break;
                }
            }
        }

        [ConsoleCommand("buildingskin.cmd")]
        private void UICommandHandler(ConsoleSystem.Arg arg)
        {
            BasePlayer player = arg.Player();
            var action = arg.Args?.Length > 0 ? arg.Args[0] : null;

            PlayerSettings? playerSettings;
            PlayerSettingsGrade playerSettingsGrade;
            PlayerWallpaperSettings? playerWallpaperSettings;
            var wallpaperCategory = WallpaperCategory.Wall;
            ItemsPagination? wallpapersPagination;
            switch (action)
            {
                //apply building skin
                case "apply":

                    if (arg.Args[1] == null || arg.Args[2] == null)
                    {
                        SendMessage(player, Lang("UIUnableApplySkin", player.UserIDString));
                        return;
                    }

                    int gradeID = Convert.ToInt32(arg.Args[1]);
                    var skinID = Convert.ToUInt64(arg.Args[2]);


                    if (!IsSkinOwned(player, skinID))
                    {
                        SendMessage(player, Lang("DlcSkinRequiredErrorMessage", player.UserIDString));
                        OpenMainPanel(player);
                        return;
                    }


                    _playerData.PlayerSkinData.TryAdd(player.userID, new PlayerSettings()
                    {
                        SteamID = player.userID,
                        Username = player.displayName
                    });

                    switch (gradeID)
                    {
                        case (int)BuildingGrade.Enum.Wood:
                            if (!_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey(gradeID))
                            {
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.Add(gradeID, new PlayerSettingsGrade()
                                {
                                    Grade = BuildingGrade.Enum.Wood,
                                    SkinID = skinID,
                                    SkinColor = 0
                                });
                            }
                            else
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[gradeID].SkinID = skinID;

                            break;
                        case (int)BuildingGrade.Enum.Stone:
                            if (!_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey(gradeID))
                            {
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.Add(gradeID, new PlayerSettingsGrade()
                                {
                                    Grade = BuildingGrade.Enum.Stone,
                                    SkinID = skinID,
                                    SkinColor = 0
                                });
                            }
                            else
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[gradeID].SkinID = skinID;

                            break;
                        case (int)BuildingGrade.Enum.Metal:
                            if (!_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey(gradeID))
                            {
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.Add(gradeID, new PlayerSettingsGrade()
                                {
                                    Grade = BuildingGrade.Enum.Metal,
                                    SkinID = skinID,
                                    SkinColor = 0
                                });
                            }
                            else
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[gradeID].SkinID = skinID;

                            break;
                        case (int)BuildingGrade.Enum.TopTier:
                            if (!_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey(gradeID))
                            {
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.Add(gradeID, new PlayerSettingsGrade()
                                {
                                    Grade = BuildingGrade.Enum.TopTier,
                                    SkinID = skinID,
                                    SkinColor = 0
                                });
                            }
                            else
                                _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[gradeID].SkinID = skinID;

                            break;
                    }

                    SaveData();
                    OpenMainPanel(player);
                    break;
                case "disableBskin":

                    var isBskinDisabled = !_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.DisableBskin;

                    if (!_playerData.PlayerSkinData.ContainsKey(player.userID))
                    {
                        _playerData.PlayerSkinData.Add(player.userID, new PlayerSettings
                        {
                            DisableBskin = false
                        });
                    }

                    _playerData.PlayerSkinData[player.userID].DisableBskin = !isBskinDisabled;

                    SaveData();
                    OpenMainPanel(player);

                    break;
                case "message":

                    var showMessage = !_playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings) || playerSettings.EnableMessage;

                    if (!_playerData.PlayerSkinData.ContainsKey(player.userID))
                    {
                        _playerData.PlayerSkinData.Add(player.userID, new PlayerSettings
                        {
                            EnableMessage = true
                        });
                    }

                    _playerData.PlayerSkinData[player.userID].EnableMessage = !showMessage;

                    SaveData();
                    OpenMainPanel(player);

                    break;
                case "changeAutoApplyColor":
                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                        break;

                    playerSettings.PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out playerSettingsGrade);
                    if (playerSettingsGrade == null)
                        break;

                    playerSettingsGrade.AutoApplyColor = !playerSettingsGrade.AutoApplyColor;
                    SaveData();
                    OpenColorChoicePanel(player);
                    break;
                case "changeAutoApplySkin":
                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                        break;

                    playerSettings.PlayerSettingsGrade.TryGetValue((int)BuildingGrade.Enum.Metal, out playerSettingsGrade);
                    if (playerSettingsGrade == null)
                        break;

                    playerSettingsGrade.AutoApplySkin = !playerSettingsGrade.AutoApplySkin;
                    SaveData();
                    OpenColorChoicePanel(player);
                    break;
                case "changeWallpaperApplyMode":
                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                        break;

                    playerWallpaperSettings = playerSettings.PlayerWallpaperSettings;
                    if (playerWallpaperSettings == null)
                    {
                        playerWallpaperSettings = new PlayerWallpaperSettings
                        {
                            WallpaperApplyMode = WallpaperApplyModeEnum.Building,
                            WallpaperApplyToWallSide = WallpaperSideEnum.Internal,
                            WallpaperApplyToFloor = false,
                            WallpaperApplyToCeiling = false,
                            ReplaceExistingWallpaper = false
                        };
                        playerSettings.PlayerWallpaperSettings = playerWallpaperSettings;
                        SaveData();
                    }

                    playerWallpaperSettings.WallpaperApplyMode =
                        playerWallpaperSettings.WallpaperApplyMode == WallpaperApplyModeEnum.Building
                            ? WallpaperApplyModeEnum.Manual
                            : WallpaperApplyModeEnum.Building;
                    SaveData();
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;
                case "wallpaperChoice":
                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                    {
                        playerSettings = new PlayerSettings
                        {
                            SteamID = player.userID,
                            Username = player.displayName
                        };
                        _playerData.PlayerSkinData.Add(player.userID, playerSettings);
                        SaveData();
                    }

                    playerWallpaperSettings = playerSettings.PlayerWallpaperSettings;
                    if (playerWallpaperSettings == null)
                    {
                        playerWallpaperSettings = new PlayerWallpaperSettings
                        {
                            WallpaperApplyMode = WallpaperApplyModeEnum.Building,
                            WallpaperApplyToWallSide = WallpaperSideEnum.Internal,
                            WallpaperApplyToFloor = false,
                            WallpaperApplyToCeiling = false,
                            ReplaceExistingWallpaper = false
                        };
                        playerSettings.PlayerWallpaperSettings = playerWallpaperSettings;
                        SaveData();
                    }

                    if (_playersWallpaperPagination.TryGetValue(player.userID, out var value))
                        value.CurrentPage = 0;
                    else
                        _playersWallpaperPagination.Add(player.userID, new ItemsPagination());

                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;
                case "closeWallpaperChoice":
                    CuiHelper.DestroyUi(player, UIPanel + ".WallpaperChoice");
                    CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");
                    break;
                case "colorPanelCursor":
                    OpenColorChoicePanel(player);
                    break;
                case "wallpaperPanelCursor":
                    _playerWallpaperPanelCursor[player.userID] = !_playerWallpaperPanelCursor[player.userID];
                    if (!_playerWallpaperPanelCursor[player.userID])
                        CuiHelper.DestroyUi(player, UIPanel);
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], _playerWallpaperPanelCursor[player.userID]);
                    break;
                case "changeWallpaperReplaceMode":
                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                        break;

                    playerWallpaperSettings = playerSettings.PlayerWallpaperSettings;
                    if (playerWallpaperSettings == null)
                    {
                        playerWallpaperSettings = new PlayerWallpaperSettings
                        {
                            WallpaperApplyMode = WallpaperApplyModeEnum.Building,
                            WallpaperApplyToWallSide = WallpaperSideEnum.Internal,
                            WallpaperApplyToFloor = false,
                            WallpaperApplyToCeiling = false,
                            ReplaceExistingWallpaper = false
                        };
                        playerSettings.PlayerWallpaperSettings = playerWallpaperSettings;
                        SaveData();
                    }

                    playerWallpaperSettings.ReplaceExistingWallpaper = !playerWallpaperSettings.ReplaceExistingWallpaper;
                    SaveData();
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;
                case "changeWallpaperApplyToWallSide":
                    if (arg.Args?.Length < 2)
                    {
                        SendMessage(player, Lang("UIMissingParameter", player.UserIDString));
                        return;
                    }

                    var wallpaperSideID = Convert.ToInt32(arg.Args![1]);
                    if (!Enum.IsDefined(typeof(WallpaperSideEnum), wallpaperSideID))
                    {
                        SendMessage(player, Lang("UIWallpaperApplyToWallSide_InvalidType", player.UserIDString));
                        return;
                    }

                    var wallpaperSideEnum = (WallpaperSideEnum)wallpaperSideID;

                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                        break;

                    playerWallpaperSettings = playerSettings.PlayerWallpaperSettings;
                    if (playerWallpaperSettings == null)
                    {
                        playerWallpaperSettings = new PlayerWallpaperSettings
                        {
                            WallpaperApplyMode = WallpaperApplyModeEnum.Building,
                            WallpaperApplyToWallSide = wallpaperSideEnum,
                            WallpaperApplyToFloor = false,
                            WallpaperApplyToCeiling = false,
                            ReplaceExistingWallpaper = false
                        };
                        playerSettings.PlayerWallpaperSettings = playerWallpaperSettings;
                        SaveData();
                    }

                    playerWallpaperSettings.WallpaperApplyToWallSide = wallpaperSideEnum;
                    SaveData();
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;
                case "changeWallpaperApplyToWall":
                case "changeWallpaperApplyToFloor":
                case "changeWallpaperApplyToCeiling":
                    _playerData.PlayerSkinData.TryGetValue(player.userID, out playerSettings);
                    if (playerSettings == null)
                        break;

                    playerWallpaperSettings = playerSettings.PlayerWallpaperSettings;
                    if (playerWallpaperSettings == null)
                    {
                        playerWallpaperSettings = new PlayerWallpaperSettings
                        {
                            WallpaperApplyMode = WallpaperApplyModeEnum.Building,
                            WallpaperApplyToWallSide = WallpaperSideEnum.Internal,
                            WallpaperApplyToFloor = false,
                            WallpaperApplyToCeiling = false,
                            ReplaceExistingWallpaper = false
                        };
                        playerSettings.PlayerWallpaperSettings = playerWallpaperSettings;
                        SaveData();
                    }

                    if (action.Equals("changeWallpaperApplyToWall", StringComparison.OrdinalIgnoreCase))
                        playerWallpaperSettings.WallpaperApplyToWall = !playerWallpaperSettings.WallpaperApplyToWall;
                    else if (action.Equals("changeWallpaperApplyToFloor", StringComparison.OrdinalIgnoreCase))
                        playerWallpaperSettings.WallpaperApplyToFloor = !playerWallpaperSettings.WallpaperApplyToFloor;
                    else if (action.Equals("changeWallpaperApplyToCeiling", StringComparison.OrdinalIgnoreCase))
                        playerWallpaperSettings.WallpaperApplyToCeiling = !playerWallpaperSettings.WallpaperApplyToCeiling;

                    SaveData();
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;

                case "wallpaperShowWall":
                case "wallpaperShowFloor":
                case "wallpaperShowCeiling":

                    _playersWallpaperCategory.Remove(player.userID);

                    if (action.Equals("wallpaperShowWall", StringComparison.OrdinalIgnoreCase))
                        wallpaperCategory = WallpaperCategory.Wall;
                    else if (action.Equals("wallpaperShowFloor", StringComparison.OrdinalIgnoreCase))
                        wallpaperCategory = WallpaperCategory.Floor;
                    else if (action.Equals("wallpaperShowCeiling", StringComparison.OrdinalIgnoreCase))
                        wallpaperCategory = WallpaperCategory.Ceiling;

                    _playersWallpaperCategory.TryAdd(player.userID, wallpaperCategory);


                    if (_playersWallpaperPagination.TryGetValue(player.userID, out var pagination))
                        pagination.CurrentPage = 0;
                    else
                        _playersWallpaperPagination.Add(player.userID, new ItemsPagination());

                    SaveData();
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;

                case "previousWallpapersPage":
                    if (!WallpapersHasPreviousPage(player))
                        return;

                    wallpapersPagination = _playersWallpaperPagination[player.userID];
                    wallpapersPagination.CurrentPage--;
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;
                case "nextWallpapersPage":
                    if (!WallpapersHasNextPage(player, GetPlayerWallpaperCategory(player)))
                        return;

                    wallpapersPagination = _playersWallpaperPagination[player.userID];
                    wallpapersPagination.CurrentPage++;
                    OpenWallpaperChoicePanel(player, _playersWallpaperPagination[player.userID], WallpaperPanelRequiresCursor(player));
                    break;
                case "wallpaperShowDetail":
                    if (arg.Args?.Length < 2)
                    {
                        SendMessage(player, Lang("UIMissingParameter", player.UserIDString));
                        return;
                    }

                    if (!ulong.TryParse(arg.Args![1], out var wallpaperID))
                    {
                        SendMessage(player, Lang("UIWallpaperIdNotValid", player.UserIDString));
                        return;
                    }

                    wallpaperCategory = GetPlayerWallpaperCategory(player);
                    if (!_wallpaperSkinList[wallpaperCategory].TryGetValue(wallpaperID, out var wallpaperSkin))
                    {
                        SendMessage(player, Lang("UIWallpaperIdNotFound", player.UserIDString));
                        return;
                    }

                    CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");

                    var mainContainer = GuiHelper.Container(UIPanel + ".WallpaperShowDetail", Transparent, "0.0 0.0", "1.0 1.0");

                    //########## Background (For close) ##########
                    //Add a transparent panel to detect mouse clicks outside the window and close the window
                    var transparentPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperShowDetail", UIPanel + ".WallpaperShowDetail.Transparent",
                        Transparent, "0 0", "1 1");
                    GuiHelper.Button(mainContainer, transparentPanel, "buildingskin.cmd closeWallpaperShowDetail", "", Transparent, "0 0", "1 1");
                    //############################################

                    //########## Header ##########
                    var headerPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperShowDetail", UIPanel + ".WallpaperShowDetail.h",
                        UIPanelHeaderColor, "0.40 0.60", "0.60 0.6358");

                    GuiHelper.Text(mainContainer, headerPanel, Lang("UIWallpaperShowDetailPanelTitle", player.UserIDString), "0.02 0", "1 1", 12,
                        TextAnchor.MiddleLeft);
                    GuiHelper.CloseButton(mainContainer, headerPanel, UIPanel + ".WallpaperShowDetail", "X", UIPanelCloseBtnColor, "0.84 0.2", "0.97 0.8",
                        textColor: UIPanelCloseTextColor);
                    //############################


                    var contentPanel = GuiHelper.ParentPanel(mainContainer, UIPanel + ".WallpaperShowDetail", UIPanel + ".WallpaperShowDetail.cp",
                        UiWallpaperShowDetailColor, "0.40 0.50", "0.60 0.60");

                    //########## SkinID ##########
                    GuiHelper.Text(mainContainer, contentPanel, $"{Lang("UISkinIDText", player.UserIDString)}: ", "0.02 0.72", "0.26 0.90", 12,
                        TextAnchor.MiddleLeft, UIGradeTextColor);

                    GuiHelper.Text(mainContainer, contentPanel, wallpaperSkin.SkinID.ToString(), "0.28 0.72", "0.96 0.90", 12, TextAnchor.MiddleLeft);
                    //############################

                    //########## Skin Name ##########
                    GuiHelper.Text(mainContainer, contentPanel, $"{Lang("UISkinNameText", player.UserIDString)}: ", "0.02 0.38", "0.26 0.56", 12,
                        TextAnchor.MiddleLeft, UIGradeTextColor);

                    GuiHelper.Text(mainContainer, contentPanel, wallpaperSkin.SkinName, "0.28 0.38", "0.96 0.56", 12, TextAnchor.MiddleLeft);
                    //###############################

                    //########## From Steam ##########
                    GuiHelper.Text(mainContainer, contentPanel, $"{Lang("UIFromSteamWorkshop", player.UserIDString)}: ", "0.02 0.06", "0.26 0.24", 12,
                        TextAnchor.MiddleLeft, UIGradeTextColor);

                    GuiHelper.Text(mainContainer, contentPanel, wallpaperSkin.FromSteamWorkshop.ToString(), "0.28 0.06", "0.96 0.24", 12,
                        TextAnchor.MiddleLeft);
                    //################################

                    CuiHelper.AddUi(player, mainContainer);
                    break;

                case "closeWallpaperShowDetail":
                    CuiHelper.DestroyUi(player, UIPanel + ".WallpaperShowDetail");
                    break;

                case "giveWallpaperRotationTool":

                    var rotationToolItem = ItemManager.CreateByItemID(HammerItemId, 1, WallpaperRotationToolSkinID);
                    if (rotationToolItem == null)
                    {
                        SendMessage(player, Lang("UiWallpaperRotationToolGiveError", player.UserIDString));
                        return;
                    }

                    rotationToolItem.name = Lang("UiWallpaperRotationTool", player.UserIDString);

                    player.GiveItem(rotationToolItem);
                    SendMessage(player, Lang("UiWallpaperRotationToolGive", player.UserIDString));

                    break;

                case "settings":
                    break;
            }
        }

        #region Wallpaper command for add/remove skins from Steam Workshop

        [ConsoleCommand("buildingskin.wp.addskin")]
        private void ConsoleHandlerWpAddSkin(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !permission.UserHasPermission(arg.Connection.userid.ToString(), PermissionAdmin))
            {
                SendConsoleResponse(Lang("UINoPermissions", arg.Connection.userid.ToString()), arg);
                return;
            }

            if (arg.Args == null || arg.Args.Length < 2)
            {
                SendConsoleResponse(Lang("UIImportWallpaperInvalidSyntax"), arg);
                return;
            }

            var wallpaperType = CapitalizeFirstLetter(arg.Args[0]);
            if (!Enum.IsDefined(typeof(WallpaperCategory), wallpaperType) || !Enum.TryParse(wallpaperType, out WallpaperCategory wallpaperCategory))
            {
                SendConsoleResponse(Lang("UIImportWallpaperMissingParameter"), arg);
                return;
            }

            var wallpaperIdString = arg.Args[1];
            if (string.IsNullOrEmpty(wallpaperIdString) || !ulong.TryParse(wallpaperIdString, out var wallpaperID))
            {
                SendConsoleResponse(Lang("UIWallpaperIdNotValid"), arg);
                return;
            }

            SendSteamWorkshopRequest(arg, wallpaperCategory, wallpaperID);
        }

        [ConsoleCommand("buildingskin.wp.removeskin")]
        private void ConsoleHandlerWpRemoveSkin(ConsoleSystem.Arg arg)
        {
            var wallpaperIdString = arg.Args?.Length > 0 ? arg.Args[0] : null;

            if (arg.Connection != null && !permission.UserHasPermission(arg.Connection.userid.ToString(), PermissionAdmin))
            {
                SendConsoleResponse(Lang("UINoPermissions", arg.Connection.userid.ToString()), arg);
                return;
            }

            if (string.IsNullOrEmpty(wallpaperIdString) || !ulong.TryParse(wallpaperIdString, out var wallpaperID))
            {
                SendConsoleResponse(Lang("UIWallpaperIdNotValid"), arg);
                return;
            }

            if (!_wallpaperData.SteamWorkshopWallpaperSkinList.ContainsKey(wallpaperID))
            {
                SendConsoleResponse(Lang("UIWallpaperIdNotFound"), arg);
                return;
            }

            _wallpaperData.SteamWorkshopWallpaperSkinList.Remove(wallpaperID);
            SaveWallpaperData();

            if (_wallpaperSkinList[WallpaperCategory.Wall].ContainsKey(wallpaperID))
                _wallpaperSkinList[WallpaperCategory.Wall].Remove(wallpaperID);

            if (_wallpaperSkinList[WallpaperCategory.Floor].ContainsKey(wallpaperID))
                _wallpaperSkinList[WallpaperCategory.Floor].Remove(wallpaperID);

            if (_wallpaperSkinList[WallpaperCategory.Ceiling].ContainsKey(wallpaperID))
                _wallpaperSkinList[WallpaperCategory.Ceiling].Remove(wallpaperID);

            SendConsoleResponse(Lang("UIWallpaperIdRemoved"), arg);
        }

        //Verifica se il giocatore ha comprato il DLC/Skin, se è una skin a pagamento o di un DLC, o se è una skin gratuita.
        private bool IsSkinOwned(BasePlayer player, ulong skinId)
        {
            if (!_steamWorkshopInitialized) return false;

            if (skinId == 0 || _config.DisableDlcSkinOwnershipCheck)
                return true;

            if (SkinIdWhitelisted.Contains(skinId))
                return true;

            if (permission.UserHasPermission(player.UserIDString, PermissionBypassDlcSkinRequired))
                return true;

            var playerId = player.userID;

            // Ottieni o crea la cache per il giocatore
            if (!PlayerDlcSkinOwnedCache.TryGetValue(playerId, out var ownedSkins))
            {
                ownedSkins = new Dictionary<ulong, bool>();
                PlayerDlcSkinOwnedCache[playerId] = ownedSkins;
            }

            // Cerca nella cache del player
            if (ownedSkins.TryGetValue(skinId, out var isOwned))
            {
                return isOwned;
            }

            if (!_workshopIdToItemId.TryGetValue(skinId, out var itemId) || itemId == 0 || !_itemIdToSkin.TryGetValue(itemId, out var skin))
            {
                ownedSkins.Add(skinId, true);
                return true;
            }

            var hasOwnership = (skin.invItem && skin.invItem.HasUnlocked(player.userID)) || player.blueprints.steamInventory.HasItem(itemId);
            ownedSkins.Add(skinId, hasOwnership);
            return hasOwnership;
        }

        private static string CapitalizeFirstLetter(string input)
        {
            if (string.IsNullOrEmpty(input))
                return input;

            // Converte tutto in minuscolo
            var lowerCase = input.ToLower();

            // Capitalizza solo la prima lettera
            return char.ToUpper(lowerCase[0]) + lowerCase.Substring(1);
        }

        private void SendSteamWorkshopRequest(ConsoleSystem.Arg arg, WallpaperCategory wallpaperCategory, ulong skin = 0)
        {
            if (_wallpaperData.SteamWorkshopWallpaperSkinList.ContainsKey(skin))
            {
                SendConsoleResponse(Lang("UIWallpaperIdAlreadyAdded"), arg);
                return;
            }

            if (_runningCoroutines.ContainsKey(skin))
            {
                SendConsoleResponse(Lang("UISteamWorkshopRequestProgress"), arg);
                return;
            }

            SendConsoleResponse(Lang("UISteamWorkshopRequestStart"), arg);

            var steamWorkshopBodyRequest = new SteamWorkshopBodyRequest
            {
                ItemCount = 1,
                PublishedFileId = skin
            };

            webrequest.Enqueue(STEAM_WORKSHOP_FILE_DETAILS_END_POINT, steamWorkshopBodyRequest.GetRequestBody(),
                (code, response) => { StartCoroutine(skin, ParseSteamWorkshopResponse(wallpaperCategory, skin, code, response)); }, this, RequestMethod.POST);
        }

        private IEnumerator ParseSteamWorkshopResponse(WallpaperCategory wallpaperCategory, ulong skin, int code, string response)
        {
            try
            {
                if (code != HTTP_STATUS_CODE_OK)
                {
                    SendConsoleResponse($"Steam Workshop Request failed with code: {code}");
                    yield break;
                }

                var responseBody = JsonConvert.DeserializeObject<SteamResponseBody>(response);
                if (responseBody is { Response: { Publishedfiledetails: { Length: > 0 } } })
                {
                    foreach (SteamPublishedFileDetails publishedFileDetail in responseBody.Response.Publishedfiledetails)
                    {
                        if (string.IsNullOrWhiteSpace(publishedFileDetail.Creator))
                        {
                            SendConsoleResponse($"Steam Workshop wallpaperID: {skin} not found");
                            continue;
                        }

                        if (_wallpaperData.SteamWorkshopWallpaperSkinList.ContainsKey(skin))
                        {
                            SendConsoleResponse($"Steam Workshop wallpaperID: {skin} already added");
                            continue;
                        }

                        var title = publishedFileDetail.Title ?? "";
                        var description = publishedFileDetail.Description ?? "";
                        var wallpaper = new WallpaperSkinInfo
                        {
                            Enabled = true,
                            SkinID = skin,
                            SkinName = title,
                            SkinDescription = description,
                            FromSteamWorkshop = true
                        };

                        switch (wallpaperCategory)
                        {
                            case WallpaperCategory.Wall:
                                wallpaper.ForWall = true;
                                break;
                            case WallpaperCategory.Floor:
                                wallpaper.ForFloor = true;
                                break;
                            case WallpaperCategory.Ceiling:
                                wallpaper.ForCeiling = true;
                                break;
                            default:
                                SendConsoleResponse($"Steam Workshop: invalid wallpaper category");
                                continue;
                        }

                        _wallpaperData.SteamWorkshopWallpaperSkinList.Add(skin, wallpaper);

                        SaveWallpaperData();

                        if (!_wallpaperSkinList[wallpaperCategory].ContainsKey(skin))
                            _wallpaperSkinList[wallpaperCategory].Add(skin, _wallpaperData.SteamWorkshopWallpaperSkinList[skin]);

                        SendConsoleResponse(
                            $"Steam Workshop wallpaperID: {wallpaperCategory} - {skin} added. Total Steam Workshop Skins: {_wallpaperData.SteamWorkshopWallpaperSkinList.Count}");
                    }
                }
            }
            catch (Exception ex)
            {
                SendConsoleResponse($"Error parsing Steam Workshop Response: {ex.Message}");
            }
            finally
            {
                StopCoroutine(skin);
            }
        }

        private void StartDownloadWallpaperData(WallpaperDownloadType downloadType)
        {
            if (downloadType == WallpaperDownloadType.SteamWorkshop)
            {
                if (!_config.DownloadSteamWorkshopWallpaper)
                {
                    SendConsoleResponse("Download Steam Workshop Wallpaper - DISABLED");
                    return;
                }
            }

            SendConsoleResponse($"Start Download {downloadType} Wallpaper....");

            var downloadUrl = downloadType switch
            {
                WallpaperDownloadType.Rust => DownloadRustWallpaperUrl,
                WallpaperDownloadType.SteamWorkshop => DownloadSteamWorkshopWallpaperUrl
            };

            webrequest.Enqueue(downloadUrl, null,
                (code, response) => { StartCoroutine(DownloadSteamWorkshopWallpaperCoroutineName - (ulong)downloadType, ParseDownloadSteamWorkshopWallpaper(downloadType, code, response)); }, this,
                RequestMethod.GET);
        }

        private IEnumerator ParseDownloadSteamWorkshopWallpaper(WallpaperDownloadType downloadType, int code, string response)
        {
            try
            {
                if (code != HTTP_STATUS_CODE_OK)
                {
                    SendConsoleResponse($"Download {downloadType} Wallpaper - Request failed with code: {code}");
                    yield break;
                }

                var count = 0;
                if (downloadType == WallpaperDownloadType.Rust)
                {
                    var responseBody = JsonConvert.DeserializeObject<RustWallpaperData>(response);

                    if (responseBody == null)
                    {
                        SendConsoleResponse($"Download {downloadType} Wallpaper - Response Body is null");
                        yield break;
                    }

                    if (responseBody.ResetRustWallpaper)
                    {
                        Puts("########## Resetting Rust Wallpaper Data ##########");
                        _rustWallpaperData.wallpaperDataWallList.Clear();
                        _rustWallpaperData.wallpaperDataFloorList.Clear();
                        _rustWallpaperData.wallpaperDataCeilingList.Clear();
                        SaveRustWallpaperData();
                    }

                    if (responseBody.wallpaperDataWallList.IsNullOrEmpty() && responseBody.wallpaperDataFloorList.IsNullOrEmpty() &&
                        responseBody.wallpaperDataCeilingList.IsNullOrEmpty())
                    {
                        SendConsoleResponse($"Download {downloadType} Wallpaper - No wallpaper to download");
                        yield break;
                    }

                    if (!responseBody.wallpaperDataWallList.IsNullOrEmpty())
                    {
                        foreach (var wallpaper in responseBody.wallpaperDataWallList)
                        {
                            if (_rustWallpaperData.wallpaperDataWallList.ContainsKey(wallpaper.Key)) continue;
                            _rustWallpaperData.wallpaperDataWallList.Add(wallpaper.Key, wallpaper.Value);
                            count++;
                        }
                    }

                    if (!responseBody.wallpaperDataFloorList.IsNullOrEmpty())
                    {
                        foreach (var wallpaper in responseBody.wallpaperDataFloorList)
                        {
                            if (_rustWallpaperData.wallpaperDataFloorList.ContainsKey(wallpaper.Key)) continue;
                            _rustWallpaperData.wallpaperDataFloorList.Add(wallpaper.Key, wallpaper.Value);
                            count++;
                        }
                    }

                    if (!responseBody.wallpaperDataCeilingList.IsNullOrEmpty())
                    {
                        foreach (var wallpaper in responseBody.wallpaperDataCeilingList)
                        {
                            if (_rustWallpaperData.wallpaperDataCeilingList.ContainsKey(wallpaper.Key)) continue;
                            _rustWallpaperData.wallpaperDataCeilingList.Add(wallpaper.Key, wallpaper.Value);
                            count++;
                        }
                    }

                    SaveRustWallpaperData();
                    MergeDownloadedWallpaper();
                }

                if (downloadType == WallpaperDownloadType.SteamWorkshop)
                {
                    var responseBody = JsonConvert.DeserializeObject<SteamWallpaperData>(response);

                    if (responseBody == null)
                    {
                        SendConsoleResponse($"Download {downloadType} Wallpaper - Response Body is null");
                        yield break;
                    }

                    if (responseBody.ResetSteamWallpaper)
                    {
                        Puts("########## Resetting Steam Wallpaper Data ##########");
                        _wallpaperData.SteamWorkshopWallpaperSkinList.Clear();
                        SaveWallpaperData();
                    }

                    if (responseBody.SteamWorkshopWallpaperSkinList.IsNullOrEmpty())
                    {
                        SendConsoleResponse($"Download {downloadType} Wallpaper - No wallpaper to download");
                        yield break;
                    }

                    foreach (var wallpaper in responseBody.SteamWorkshopWallpaperSkinList)
                    {
                        if (_wallpaperData.SteamWorkshopWallpaperSkinList.ContainsKey(wallpaper.Key)) continue;
                        _wallpaperData.SteamWorkshopWallpaperSkinList.Add(wallpaper.Key, wallpaper.Value);
                        count++;
                    }

                    SaveWallpaperData();
                    MergeDownloadedWallpaper();
                }

                SendConsoleResponse($"Download {downloadType} Wallpaper - Finished. Total wallpaper downloaded: {count}");
            }
            catch (Exception ex)
            {
                SendConsoleResponse($"Error parsing Download {downloadType} - Response: {ex.Message}");
            }
            finally
            {
                StopCoroutine(DownloadSteamWorkshopWallpaperCoroutineName - (ulong)downloadType);
            }
        }

        #endregion

        private bool WallpapersHasPreviousPage(BasePlayer player)
        {
            if (player == null) return false;

            if (!_playersWallpaperPagination.TryGetValue(player.userID, out var wallpapersPagination))
                _playersWallpaperPagination.Add(player.userID, new ItemsPagination());

            wallpapersPagination = _playersWallpaperPagination[player.userID];
            return wallpapersPagination.CurrentPage != 0;
        }

        private bool WallpapersHasNextPage(BasePlayer player, WallpaperCategory category)
        {
            if (player == null) return false;

            if (!_playersWallpaperPagination.TryGetValue(player.userID, out var wallpapersPagination))
                _playersWallpaperPagination.Add(player.userID, new ItemsPagination());

            wallpapersPagination = _playersWallpaperPagination[player.userID];

            return _wallpaperSkinList[category]
                .Skip((wallpapersPagination.CurrentPage + 1) * WallpaperItemsPerPages)
                .Take(WallpaperItemsPerPages).Any();
        }

        private WallpaperCategory GetPlayerWallpaperCategory(BasePlayer player)
        {
            if (_playersWallpaperCategory.TryGetValue(player.userID, out var wallpaperCategory)) return wallpaperCategory;

            wallpaperCategory = WallpaperCategory.Wall;
            _playersWallpaperCategory.Add(player.userID, wallpaperCategory);

            return wallpaperCategory;
        }

        private bool WallpaperPanelRequiresCursor(BasePlayer player)
        {
            if (!_playerWallpaperPanelCursor.TryGetValue(player.userID, out var cursor))
            {
                _playerWallpaperPanelCursor.TryAdd(player.userID, true);
            }

            return _playerWallpaperPanelCursor[player.userID];
        }

        private bool IsBuildingBlockOwner(BuildingBlock buildingBlock, ulong steamID)
        {
            if (buildingBlock == null || !buildingBlock.OwnerID.IsSteamId() || steamID == 0)
                return false;

            if (buildingBlock.OwnerID.Equals(steamID))
                return true;

            var isPlayerInSameClanOrTeam = false;
            if (Clans != null)
            {
                var clanInfo = GetClanInfo(steamID);
                if (clanInfo == null || clanInfo.ClanMemberUserIdList.IsNullOrEmpty()) return false;

                isPlayerInSameClanOrTeam = clanInfo.ClanMemberUserIdList.Contains(buildingBlock.OwnerID.ToString());
            }

            var player = BasePlayer.FindByID(steamID);
            if (!isPlayerInSameClanOrTeam && player != null && player.Team != null)
            {
                isPlayerInSameClanOrTeam = player.Team.members.Contains(buildingBlock.OwnerID);
            }

            return isPlayerInSameClanOrTeam;
        }

        private ClanInfo? GetClanInfo(ulong userID)
        {
            if (Clans == null)
                return null;

            var player = BasePlayer.FindByID(userID);
            if (player == null || GetClanOf(player.userID) == null)
            {
                return null;
            }

            var clanInfo = new ClanInfo();
            var clanName = GetClanOf(player.userID);

            clanInfo.ClanName = clanName;

            var clanMembers = GetClanMembers(clanName);
            foreach (var member in clanMembers)
            {
                clanInfo.ClanMemberUserIdList.Add((string)member);
            }

            return clanInfo;
        }

        private string GetClanOf(ulong playerID) => Clans?.Call<string>("GetClanOf", playerID);
        private JObject GetClan(string tag) => Clans?.Call<JObject>("GetClan", tag);
        private JArray GetClanMembers(string tag) => (JArray)GetClan(tag)?.SelectToken("members");

        #endregion

        #region Utils

        private string GetImage(string fileName, ulong skin = 0)
        {
            var id = ImageLibrary.Call<string>("GetImage", fileName, skin);
            return id ?? string.Empty;
        }

        private bool HasSelectedGrade(BasePlayer player, BuildingGrade.Enum grade, ulong skinID)
        {
            if (player == null)
                return false;

            if (!_playerData.PlayerSkinData.ContainsKey(player.userID) ||
                !_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey((int)grade))
                return false;

            var playerSettingsGrade = _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[(int)grade];
            return playerSettingsGrade.SkinID == skinID;
        }

        private void StartCoroutine(ulong key, IEnumerator routine)
        {
            if (_runningCoroutines.ContainsKey(key)) return;
            var coroutine = ServerMgr.Instance.StartCoroutine(routine);
            if (coroutine != null) _runningCoroutines[key] = coroutine;
        }

        private void StopCoroutine(ulong key)
        {
            if (!_runningCoroutines.TryGetValue(key, out var coroutine)) return;
            if (coroutine != null) ServerMgr.Instance.StopCoroutine(coroutine);
            _runningCoroutines.Remove(key);
        }

        private IEnumerator UpgradeSkin(BasePlayer player, BuildingBlock[] blocks, uint? skinColor, bool autoApplySkin)
        {
            var count = 0;
            var blockCount = blocks.Length;

            uint colorIndex = 0;
            var maxColor = _metalGradeColor.Count;
            for (var i = 0; i < blockCount; i++)
            {
                var block = blocks[i];
                if (block == null || block.IsDestroyed || !GradesSkin.ContainsKey(block.grade)) continue;

                if (!_playerData.PlayerSkinData.ContainsKey(player.userID) ||
                    !_playerData.PlayerSkinData[player.userID].PlayerSettingsGrade.ContainsKey((int)block.grade))
                    continue;

                //Don't apply the skin if you don't have building permission
                if ((_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                    !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    continue;

                var playerSettingsGrade = _playerData.PlayerSkinData[player.userID].PlayerSettingsGrade[(int)block.grade];

                var skinID = autoApplySkin ? playerSettingsGrade.SkinID : block.skinID;

                //Excludes the triangle roof for Gingerbread (wood) skin, as it doesn't currently work
                /*if (playerSettingsGrade.Grade == BuildingGrade.Enum.Wood && skinID == 2 && block.prefabID == 870964632)
                {
                    skinID = 0;
                }*/

                if (block.skinID == skinID && (skinColor == null || block.customColour.Equals(skinColor.Value))) continue;


                if (!IsSkinOwned(player, skinID))
                    continue;


                block.skinID = skinID;

                if (skinColor != null)
                    block.SetCustomColour(skinColor.Value);

                playerSettingsGrade.SkinColor = GetPlayerSkinColor(player, block.grade, playerSettingsGrade);

                if (_config.ApplyPaintColor) block.playerCustomColourToApply = playerSettingsGrade.SkinColor;

                if (block.grade == BuildingGrade.Enum.Metal && skinColor == 0)
                {
                    colorIndex++;
                    block.SetCustomColour(colorIndex);

                    if (colorIndex >= maxColor - 1)
                        colorIndex = 0;
                }

                block.ChangeGradeAndSkin(block.grade, skinID, true, true);
                count++;
                if (i % _config.UpdatesPerTick == 0)
                    yield return CoroutineEx.waitForFixedUpdate;
            }

            SendMessage(player,
                count == 0 ? Lang("UIUpdateNotRequired", player.UserIDString) : Lang("UIUpdateCompleted", player.UserIDString, count, blocks.Length));
            StopCoroutine(player.userID);
        }

        private IEnumerator UpgradeWallpaperSkin(BasePlayer player, BuildingBlock[] blocks, WallpaperCategory wallpaperCategory,
            ulong wallpaperID, BuildingBlock? initialBuildingBlock = null)
        {
            var wallpaperPlaced = 0;
            var wallpaperSkipped = 0;
            var wallCount = blocks.Count(b =>
                _wallShortPrefabName.Contains(b.ShortPrefabName.ToLower()) || _floorShortPrefabName.Contains(b.ShortPrefabName.ToLower()) ||
                _ceilingShortPrefabName.Contains(b.ShortPrefabName.ToLower()));

            var blockCount = blocks.Length;


            ulong selectedWallpaperSkinID;
            if (!_wallpaperSkinList[wallpaperCategory].TryGetValue(wallpaperID, out var wpSkin))
            {
                //Use skins that are not present in the configuration and which can be taken directly from Steam
                if (permission.UserHasPermission(player.UserIDString, PermissionWallpaperUseSkinFromSteam))
                    selectedWallpaperSkinID = wallpaperID;
                else
                {
                    SendMessage(player, Lang("UIWallpaperIdError", player.UserIDString));
                    yield break;
                }
            }
            else
                selectedWallpaperSkinID = wpSkin.SkinID;


            if (!IsSkinOwned(player, selectedWallpaperSkinID))
            {
                SendMessage(player, Lang("DlcSkinRequiredErrorMessage", player.UserIDString));
                yield break;
            }


            var replaceExistingWallpaper = false;
            if (_playerData.PlayerSkinData.TryGetValue(player.userID, out var playerSettings))
            {
                if (playerSettings.PlayerWallpaperSettings != null)
                {
                    replaceExistingWallpaper = playerSettings.PlayerWallpaperSettings.ReplaceExistingWallpaper;
                }
            }

            for (var i = 0; i < blockCount; i++)
            {
                var block = blocks[i];

                if (block == null || block.IsDestroyed) continue;

                switch (wallpaperCategory)
                {
                    case WallpaperCategory.Wall when playerSettings?.PlayerWallpaperSettings?.WallpaperApplyToWall == false ||
                                                     !_wallShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                    case WallpaperCategory.Floor when playerSettings?.PlayerWallpaperSettings?.WallpaperApplyToFloor == false ||
                                                      !_floorShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                    case WallpaperCategory.Ceiling
                        when playerSettings?.PlayerWallpaperSettings?.WallpaperApplyToCeiling == false || !_ceilingShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                        continue;
                }

                var wallpaperApplyToWallSide = wallpaperCategory switch
                {
                    WallpaperCategory.Floor when block.ShortPrefabName.Equals("foundation", StringComparison.OrdinalIgnoreCase) ||
                                                 block.ShortPrefabName.Equals("foundation.triangle", StringComparison.OrdinalIgnoreCase) => WallpaperSideEnum.Internal,
                    WallpaperCategory.Floor when block.ShortPrefabName.Equals("floor", StringComparison.OrdinalIgnoreCase) ||
                                                 block.ShortPrefabName.Equals("floor.triangle", StringComparison.OrdinalIgnoreCase) => WallpaperSideEnum.External,
                    WallpaperCategory.Ceiling => WallpaperSideEnum.Internal,
                    _ => playerSettings is { PlayerWallpaperSettings: not null } ? playerSettings.PlayerWallpaperSettings.WallpaperApplyToWallSide : WallpaperSideEnum.Internal
                };

                if (wallpaperID != WallpaperIdForRemoval && CheckBlockHasWallpaper(block, wallpaperApplyToWallSide) &&
                    block.wallpaperID == selectedWallpaperSkinID)
                {
                    wallpaperSkipped++;
                    continue;
                }

                if (wallpaperID != WallpaperIdForRemoval && CheckBlockHasWallpaper(block, wallpaperApplyToWallSide) && !replaceExistingWallpaper)
                {
                    wallpaperSkipped++;
                    continue;
                }

                //Don't apply the wallpaper if you don't have building permission
                if ((_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                    !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    continue;

                if (wallpaperID == WallpaperIdForRemoval)
                {
                    switch (wallpaperApplyToWallSide)
                    {
                        case WallpaperSideEnum.Both:
                            block.RemoveWallpaper((int)WallpaperSideEnum.Internal);
                            block.RemoveWallpaper((int)WallpaperSideEnum.External);
                            break;
                        case WallpaperSideEnum.Internal:
                        case WallpaperSideEnum.External:
                        default:
                            block.RemoveWallpaper((int)wallpaperApplyToWallSide);
                            break;
                    }
                }
                else
                {
                    if (!PayFowWallpaper(player, block, wallpaperApplyToWallSide, replaceExistingWallpaper))
                    {
                        var requiredCloth = (wallCount - wallpaperPlaced) * GetWallpaperClothCost(block, wallpaperApplyToWallSide, replaceExistingWallpaper);
                        var wallRemaining = wallCount - wallpaperPlaced - wallpaperSkipped;
                        SendMessage(player,
                            Lang("UIWallpaperNotEnoughCloth", player.UserIDString, requiredCloth, wallpaperPlaced, wallRemaining, wallCount));

                        StopCoroutine(player.userID);
                        yield break;
                    }

                    switch (wallpaperCategory)
                    {
                        case WallpaperCategory.Wall:
                            switch (wallpaperApplyToWallSide)
                            {
                                case WallpaperSideEnum.Both:
                                    BlockSetWallpaper(block, selectedWallpaperSkinID, (int)WallpaperSideEnum.Internal);
                                    BlockSetWallpaper(block, selectedWallpaperSkinID, (int)WallpaperSideEnum.External);
                                    break;
                                case WallpaperSideEnum.Internal:
                                case WallpaperSideEnum.External:
                                default:
                                    BlockSetWallpaper(block, selectedWallpaperSkinID, (int)wallpaperApplyToWallSide);
                                    break;
                            }

                            break;
                        case WallpaperCategory.Floor:
                        {
                            if (block.ShortPrefabName.Equals("foundation", StringComparison.OrdinalIgnoreCase) ||
                                block.ShortPrefabName.Equals("foundation.triangle", StringComparison.OrdinalIgnoreCase))
                            {
                                BlockSetWallpaper(block, selectedWallpaperSkinID, 0);
                            }

                            if (block.ShortPrefabName.Equals("floor", StringComparison.OrdinalIgnoreCase) ||
                                block.ShortPrefabName.Equals("floor.triangle", StringComparison.OrdinalIgnoreCase))
                            {
                                BlockSetWallpaper(block, selectedWallpaperSkinID, 1);
                            }

                            break;
                        }
                        case WallpaperCategory.Ceiling:
                            BlockSetWallpaper(block, selectedWallpaperSkinID, 0);
                            break;
                        default:
                            SendMessage(player, Lang("GenericError", player.UserIDString, "WallpaperCategory not valid - #ERR 2"));
                            yield break; //Interrompe immediatamente la coroutine
                    }
                }

                wallpaperPlaced++;
                if (i % _config.UpdatesPerTick == 0)
                    yield return CoroutineEx.waitForFixedUpdate;
            }

            if (wallpaperID <= 0)
            {
                SendMessage(player, Lang("UIWallpaperRemoveCompleted", player.UserIDString, wallpaperPlaced, wallCount));
            }
            else
            {
                SendMessage(player,
                    wallpaperPlaced == 0
                        ? Lang("UIWallpaperUpdateNotRequired", player.UserIDString)
                        : Lang("UIWallpaperUpdateCompleted", player.UserIDString, wallpaperPlaced, wallCount));
            }

            var rotateWallpaper = wallpaperPlaced > 0;

            StopCoroutine(player.userID);

            if (rotateWallpaper && initialBuildingBlock != null && !initialBuildingBlock.IsDestroyed && wallpaperCategory is WallpaperCategory.Floor or WallpaperCategory.Ceiling)
            {
                var shouldStopTimer = false;
                timer.Repeat(2f, 20, () =>
                {
                    if (shouldStopTimer)
                        return; // Stop the timer if the wallpaper rotation has already been applied

                    //Waiting, for Wallpaper apply coroutine to finish, and start coroutine to rotate Wallpaper
                    if (_runningCoroutines.ContainsKey(player.userID)) return;

                    shouldStopTimer = true;
                    RotateWallpaper(player, initialBuildingBlock, wallpaperCategory, true);
                });
            }
        }

        private IEnumerator UpdateWallpaperRotation(BasePlayer player, BuildingBlock[] blocks, WallpaperCategory wallpaperCategory, ulong wallpaperID, float wallpaperRotation,
            float direction = 0)
        {
            var wallpaperChanged = 0;
            var wallCount = blocks.Count(b =>
                _wallShortPrefabName.Contains(b.ShortPrefabName.ToLower()) || _floorShortPrefabName.Contains(b.ShortPrefabName.ToLower()) ||
                _ceilingShortPrefabName.Contains(b.ShortPrefabName.ToLower()));

            var blockCount = blocks.Length;

            for (var i = 0; i < blockCount; i++)
            {
                var block = blocks[i];
                if (block == null || block.IsDestroyed) continue;

                //Don't apply the wallpaper if you don't have building permission
                if ((_config.BuildingBlocked && !player.CanBuild() || !IsBuildingBlockOwner(block, player.userID)) &&
                    !permission.UserHasPermission(player.UserIDString, PermissionAdmin))
                    continue;

                switch (wallpaperCategory)
                {
                    case WallpaperCategory.Floor when !_floorShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                    case WallpaperCategory.Ceiling when !_ceilingShortPrefabName.Contains(block.ShortPrefabName.ToLower()):
                        continue;
                }

                var wallpaperApplyToWallSide = -1;
                switch (wallpaperCategory)
                {
                    case WallpaperCategory.Floor:
                    {
                        if (block.ShortPrefabName.Equals("foundation", StringComparison.OrdinalIgnoreCase) ||
                            block.ShortPrefabName.Equals("foundation.triangle", StringComparison.OrdinalIgnoreCase))
                        {
                            wallpaperApplyToWallSide = 0;
                        }

                        if (block.ShortPrefabName.Equals("floor", StringComparison.OrdinalIgnoreCase) ||
                            block.ShortPrefabName.Equals("floor.triangle", StringComparison.OrdinalIgnoreCase))
                        {
                            wallpaperApplyToWallSide = 1;
                        }

                        break;
                    }
                    case WallpaperCategory.Ceiling:
                        wallpaperApplyToWallSide = 0;
                        break;
                    default:
                        SendMessage(player, Lang("GenericError", player.UserIDString, "WallpaperCategory not valid - #ERR 3"));
                        yield break; //Interrompe immediatamente la coroutine
                }

                if (wallpaperApplyToWallSide == -1) continue;
                if (!block.HasWallpaper(wallpaperApplyToWallSide)) continue;

                var rotation = wallpaperRotation;

                if (!IsBlockTriangle(block) && wallpaperRotation % 120 == 0)
                {
                    rotation -= 30f * (int)(wallpaperRotation / 120f);
                }

                BlockSetWallpaper(block, wallpaperID, wallpaperApplyToWallSide, true, rotation, direction);

                wallpaperChanged++;
                if (i % _config.UpdatesPerTick == 0)
                    yield return CoroutineEx.waitForFixedUpdate;
            }

            SendMessage(player, Lang("UIWallpaperUpdateRotation", player.UserIDString, wallpaperChanged, wallCount));
            StopCoroutine(player.userID * 2);
        }

        private static void BlockSetWallpaper(BuildingBlock block, ulong wallpaperID, int side, bool forRotation = false, float rotation = -1, float direction = 0)
        {
            if (block == null || block.IsDestroyed) return;

            if (forRotation && rotation >= 0)
            {
                if (IsBlockTriangle(block) || IsBlockRoof(block))
                {
                    block.SetWallpaper(wallpaperID, side);
                    return;
                }

                var offset = direction - BlockGetDirection((block));
                rotation += offset;

                block.SetWallpaper(wallpaperID, side, rotation);
                return;
            }

            block.SetWallpaper(wallpaperID, side);
        }

        private static int BlockGetDirection(BuildingBlock block)
        {
            if (block == null || block.IsDestroyed) return 0;

            var angle = Mathf.Atan2(block.transform.forward.x, block.transform.forward.z) * Mathf.Rad2Deg;
            return angle switch
            {
                > -45 and <= 45 => 90, //Nord
                > 45 and <= 135 => 180, //Est
                > 135 or <= -135 => 270, //Sud
                _ => 360 //Ovest
            };
        }

        private static bool CheckBlockHasWallpaper(BuildingBlock block, WallpaperSideEnum wallpaperSideEnum)
        {
            return wallpaperSideEnum switch
            {
                WallpaperSideEnum.Internal => block.HasWallpaper((int)WallpaperSideEnum.Internal),
                WallpaperSideEnum.External => block.HasWallpaper((int)WallpaperSideEnum.External),
                WallpaperSideEnum.Both => block.HasWallpaper((int)WallpaperSideEnum.Internal) && block.HasWallpaper((int)WallpaperSideEnum.External),
                _ => false
            };
        }

        private static uint GetSelectedShippingContainerBlockColourForPlayer(BasePlayer player)
        {
            return (uint)(player == null ? 0 : player.GetInfoInt("client.SelectedShippingContainerBlockColour", 0));
        }

        private static void SetShippingContainerBlockColourForPlayer(BasePlayer player, uint skinColor)
        {
            player.SetInfo("client.SelectedShippingContainerBlockColour", skinColor.ToString());
        }


        private static void SendMessage(BasePlayer player, string message)
        {
            var prefix = $"<color=#62de32>Building Skin\n</color>";
            player.ChatMessage($"{prefix}{message}");
        }

        private void SendConsoleResponse(string message, ConsoleSystem.Arg? arg = null)
        {
            if (arg is { Connection: not null })
                SendReply(arg, $"[{Name}] - {message}");
            else Puts($"[{Name}] - {message}");
        }

        private void SendConsoleMessage(string message)
        {
            Puts($"[{Name}] - {message}");
        }

        private bool PayFowWallpaper(BasePlayer player, BuildingBlock block, WallpaperSideEnum wallpaperSideEnum, bool replaceExistingWallpaper)
        {
            if (permission.UserHasPermission(player.UserIDString, PermissionWallpaperPlaceNoCost))
                return true;

            var playerInventoryAllItems = new List<Item>();
            player.inventory.GetAllItems(playerInventoryAllItems);
            foreach (var item in playerInventoryAllItems.Where(item => item.info.shortname.Equals("cloth")))
            {
                var wallpaperClothCost = GetWallpaperClothCost(block, wallpaperSideEnum, replaceExistingWallpaper);
                if (item.amount < wallpaperClothCost) return false;
                item.UseItem(wallpaperClothCost);
                return true;
            }

            return false;
        }

        private int GetWallpaperClothCost(BuildingBlock? block, WallpaperSideEnum? wallpaperSideEnum, bool replaceExistingWallpaper)
        {
            var wallpaperClothCost = _config.WallpaperPlaceCost;

            if (block == null || wallpaperSideEnum is not WallpaperSideEnum.Both) return wallpaperClothCost;

            if (replaceExistingWallpaper || (!block.HasWallpaper((int)WallpaperSideEnum.Internal) && !block.HasWallpaper((int)WallpaperSideEnum.External)))
                wallpaperClothCost *= 2;

            return wallpaperClothCost;
        }

        private static BuildingBlock GetLookEntity(BasePlayer player)
        {
            RaycastHit raycastHit;
            return Physics.Raycast(player.eyes.HeadRay(), out raycastHit, 10f, Layers.Mask.Construction) ? raycastHit.GetEntity() as BuildingBlock : null;
        }

        private class GuiHelper
        {
            public static CuiElementContainer Container(string panelName, string backgroundColor, string anchorMin,
                string anchorMax, bool requireCursor = false, string parent = "Overlay")
            {
                return new CuiElementContainer
                {
                    {
                        new CuiPanel
                        {
                            Image = { Color = backgroundColor },
                            RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                            CursorEnabled = requireCursor
                        },
                        new CuiElement().Parent = parent, panelName, panelName
                    }
                };
            }

            public static string ParentPanel(CuiElementContainer container, string parentName, string panelName,
                string backgroundColor, string anchorMin, string anchorMax, bool cursorEnabled = false)
            {
                container.Add(new CuiPanel
                {
                    Image = { Color = backgroundColor },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                    CursorEnabled = cursorEnabled,
                }, parentName, panelName);
                return panelName;
            }

            public static string ParentPanelCircle(CuiElementContainer container, string parentName, string panelName,
                string backgroundColor, string anchorMin, string anchorMax, bool cursorEnabled = false)
            {
                container.Add(new CuiPanel
                {
                    Image = { Color = backgroundColor, Sprite = "assets/icons/circle_closed.png" },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                    CursorEnabled = cursorEnabled,
                }, parentName, panelName);
                return panelName;
            }

            public static void Image(CuiElementContainer container, string parentName, string image, string anchorMin, string anchorMax)
            {
                container.Add(new CuiElement
                {
                    Name = CuiHelper.GetGuid(),
                    Components =
                    {
                        new CuiRawImageComponent { Png = image },
                        new CuiRectTransformComponent { AnchorMin = anchorMin, AnchorMax = anchorMax }
                    },
                    Parent = parentName
                });
            }

            public static void ImageSkin(CuiElementContainer container, string parentName, int itemId, ulong skinId, string anchorMin, string anchorMax)
            {
                container.Add(new CuiElement
                {
                    Name = CuiHelper.GetGuid(),
                    Parent = parentName,
                    Components =
                    {
                        new CuiImageComponent { ItemId = itemId, SkinId = skinId },
                        new CuiRectTransformComponent { AnchorMin = anchorMin, AnchorMax = anchorMax }
                    }
                });
            }

            public static void CloseButton(CuiElementContainer container, string parentName, string closePanelName,
                string buttonText, string color, string anchorMin, string anchorMax, int fontSize = 14,
                string font = "robotocondensed-bold.ttf", string textColor = "1 1 1 1", string command = "")
            {
                container.Add(new CuiButton
                {
                    Button = { Command = command, Close = closePanelName, Color = color },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                    Text = { Text = buttonText, FontSize = fontSize, Font = font, Align = TextAnchor.MiddleCenter, Color = textColor }
                }, parentName);
            }

            public static void Button(CuiElementContainer container, string parentName, string command,
                string buttonText, string buttonColor, string anchorMin, string anchorMax, int fontSize = 14,
                string textColor = "1 1 1 1", TextAnchor align = TextAnchor.MiddleCenter,
                string font = "robotocondensed-bold.ttf")
            {
                container.Add(new CuiButton
                {
                    Button = { Command = command, Color = buttonColor },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                    Text = { Text = buttonText, FontSize = fontSize, Font = font, Align = align, Color = textColor }
                }, parentName);
            }

            public static void Button(CuiElementContainer container, string parentName, string command,
                string buttonText, string buttonColor, string anchorMin, string anchorMax, string sprite, string material, int fontSize = 14,
                string textColor = "1 1 1 1", TextAnchor align = TextAnchor.MiddleCenter, string font = "robotocondensed-bold.ttf")
            {
                container.Add(new CuiButton
                {
                    Button = { Command = command, Color = buttonColor, Sprite = sprite, Material = material },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                    Text = { Text = buttonText, FontSize = fontSize, Font = font, Align = align, Color = textColor }
                }, parentName);
            }

            public static void ButtonCircle(CuiElementContainer container, string parentName, string command,
                string buttonText, string buttonColor, string anchorMin, string anchorMax, int fontSize = 14,
                string textColor = "1 1 1 1", TextAnchor align = TextAnchor.MiddleCenter,
                string font = "robotocondensed-bold.ttf")
            {
                container.Add(new CuiButton
                {
                    Button = { Command = command, Color = buttonColor, Sprite = "assets/icons/circle_closed.png" },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax },
                    Text = { Text = buttonText, FontSize = fontSize, Font = font, Align = align, Color = textColor }
                }, parentName);
            }

            public static void Text(CuiElementContainer container, string parentName, string text, string anchorMin,
                string anchorMax, int fontSize = 14, TextAnchor align = TextAnchor.LowerLeft,
                string textColor = "1 1 1 1", string font = "robotocondensed-bold.ttf")
            {
                container.Add(new CuiLabel
                {
                    Text = { Text = text, FontSize = fontSize, Font = font, Align = align, Color = textColor },
                    RectTransform = { AnchorMin = anchorMin, AnchorMax = anchorMax }
                }, parentName);
            }
        }

        #endregion

        #region Class

        private static class BuildingBlock_Settings
        {
            //Prevents Rust from removing wallpaper on external walls at base
            public static bool Prefix_CheckWallpaper(BuildingBlock __instance)
            {
                try
                {
                    return false; //Block Original code
                }
                catch (Exception e)
                {
                    self.Puts($"Prefix_CheckWallpaper - Exception: {e.Message}");
                }

                return true; //Run Normal code
            }

            public static bool Prefix_DamageWallpaper(BuildingBlock __instance, float totalDamage, int side = 0)
            {
                try
                {
                    return false; //Block Original code
                }
                catch (Exception e)
                {
                    self.Puts($"Prefix_DamageWallpaper - Exception: {e.Message}");
                }

                return true; //Run Normal code
            }

            //Blocca il rimborso della stoffa, quando si ha il permesso buildingskin.wallpaper.nocost
            public static bool Prefix_RPC_PickupWallpaperStart(BuildingBlock __instance, BaseEntity.RPCMessage msg)
            {
                try
                {
                    if (self == null || msg.player == null) return true; //Run Normal code

                    if (!msg.player.CanInteract() || !__instance.ShouldDisplayPickupOption(msg.player) || !__instance.CanCompletePickup(msg.player))
                        return false; //Block Original code return;
                    bool flag = msg.read.Bool();
                    if (!__instance.HasWallpaper(flag ? 0 : 1))
                        return false; //Block Original code return;

                    if (!self.permission.UserHasPermission(msg.player.UserIDString, PermissionWallpaperPlaceNoCost))
                    {
                        Item obj = ItemManager.Create(global::WallpaperPlanner.Settings.PlacementPrice.itemDef, (int)global::WallpaperPlanner.Settings.PlacementPrice.amount);
                        msg.player.GiveItem(obj, BaseEntity.GiveItemReason.PickedUp);
                    }

                    __instance.RemoveWallpaper(flag ? 0 : 1);

                    return false; //Block Original code
                }
                catch (Exception e)
                {
                    self?.Puts($"Prefix_RPC_PickupWallpaperStart - Exception: {e.Message}");
                }

                return true; //Run Normal code
            }
        }

        private class Configuration
        {
            [JsonProperty("Chat steamID icon")] public ulong SteamIDIcon = 76561198394504608;

            [JsonProperty("Disable ownership checking on DLC/Skins. (⚠ Only allowed on creative/test servers, otherwise you violate Facepunch's TOS! ⚠) Default false")]
            public bool DisableDlcSkinOwnershipCheck = false;

            [JsonProperty("Download Steam Workshop wallpaper")]
            public bool DownloadSteamWorkshopWallpaper = true;

            [JsonProperty("Block building skin in building blocked")]
            public bool BuildingBlocked;

            [JsonProperty("Apply selected paint color to all building blocks")]
            public bool ApplyPaintColor;

            [JsonProperty("Number of blocks updated per tick")]
            public int UpdatesPerTick;

            [JsonProperty("Wallpaper: Prevents Rust from removing wallpaper on external walls at base. Default TRUE")]
            public bool WallpaperDisableDecay = DefaultWallpaperDisableDecay;

            [JsonProperty("Wallpaper: Disable damage against wallpapers. Default TRUE")]
            public bool WallpaperDisableDamage = DefaultWallpaperDisableDamage;

            [JsonProperty("Wallpaper place Cost (Cloth). Default: 5 Cloth x Wall side")]
            public int WallpaperPlaceCost = DefaultWallpaperPlaceCost;

            [JsonProperty("SkinList")] public Dictionary<BuildingGrade.Enum, List<SkinInfo>> SkinList = new();

            public VersionNumber VersionNumber;

            public static Configuration CreateConfig()
            {
                return new Configuration
                {
                    SteamIDIcon = 0,
                    DisableDlcSkinOwnershipCheck = false,
                    BuildingBlocked = true,
                    ApplyPaintColor = true,
                    UpdatesPerTick = 5,
                    WallpaperDisableDecay = DefaultWallpaperDisableDecay,
                    WallpaperDisableDamage = DefaultWallpaperDisableDamage,
                    WallpaperPlaceCost = 5,
                    SkinList = new Dictionary<BuildingGrade.Enum, List<SkinInfo>>
                    {
                        {
                            BuildingGrade.Enum.Wood, new List<SkinInfo>
                            {
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Wood,
                                    SkinID = (ulong)SkinId.Default,
                                    SkinName = "Wooden",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Wood_Skin_Image1.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Wood,
                                    SkinID = (ulong)SkinId.WoodLegacyWood,
                                    SkinName = "Legacy Wood",
                                    SkinImageUrl = "https://dl.scalbox.com//Rust/Plugins/BuildingSkin/Frontier_Skin_Image1.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Wood,
                                    SkinID = (ulong)SkinId.WoodGingerbread,
                                    SkinName = "Gingerbread",
                                    SkinImageUrl = "https://dl.scalbox.com//Rust/Plugins/BuildingSkin/Gingerbread_Skin_Image1.png"
                                }
                            }
                        },
                        {
                            BuildingGrade.Enum.Stone, new List<SkinInfo>
                            {
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Stone,
                                    SkinID = (ulong)SkinId.Default,
                                    SkinName = "Stone",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/wDMwXFh.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Stone,
                                    SkinID = (ulong)SkinId.StoneAdobe,
                                    SkinName = "Adobe",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/6CcMIYE.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Stone,
                                    SkinID = (ulong)SkinId.StoneBrick,
                                    SkinName = "Brick",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/brick_skin.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Stone,
                                    SkinID = (ulong)SkinId.StoneBrutalist,
                                    SkinName = "Brutalist",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/brutalist_skin.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Stone,
                                    SkinID = (ulong)SkinId.StoneJungleTemple,
                                    SkinName = "Jungle Temple",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/Jungle_temple_skin.png"
                                }
                            }
                        },
                        {
                            BuildingGrade.Enum.Metal, new List<SkinInfo>
                            {
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Metal,
                                    SkinID = (ulong)SkinId.Default,
                                    SkinName = "Metal",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/eKiQ1zc.png"
                                },
                                new()
                                {
                                    Grade = BuildingGrade.Enum.Metal,
                                    SkinID = (ulong)SkinId.MetalShippingContainer,
                                    SkinName = "Container",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/A0OHBvj.png"
                                }
                            }
                        },
                        {
                            BuildingGrade.Enum.TopTier, new List<SkinInfo>
                            {
                                new()
                                {
                                    Grade = BuildingGrade.Enum.TopTier,
                                    SkinID = (ulong)SkinId.Default,
                                    SkinName = "TopTier",
                                    SkinImageUrl = "https://dl.scalbox.com/Rust/Plugins/BuildingSkin/ZvXnpO4.png"
                                }
                            }
                        }
                    },
                    VersionNumber = OxideMod.Version
                };
            }

            public class SkinInfo
            {
                [JsonProperty("Grade")] public BuildingGrade.Enum Grade;
                [JsonProperty("SkinID")] public ulong SkinID;
                [JsonProperty("SkinName")] public string SkinName;
                [JsonProperty("SkinImageUrl")] public string SkinImageUrl;
            }
        }

        private class RustWallpaperData
        {
            [JsonProperty("Reset Rust Wallpaper Data")]
            public bool ResetRustWallpaper = false;

            // wallpaperID, WallpaperSkinInfo
            [JsonProperty("Rust Wallpaper Data - Wall")]
            public readonly Dictionary<ulong, WallpaperSkinInfo> wallpaperDataWallList = new();

            // wallpaperID, WallpaperSkinInfo
            [JsonProperty("Rust Wallpaper Data - Floor")]
            public readonly Dictionary<ulong, WallpaperSkinInfo> wallpaperDataFloorList = new();

            // wallpaperID, WallpaperSkinInfo
            [JsonProperty("Rust Wallpaper Data - Ceiling")]
            public readonly Dictionary<ulong, WallpaperSkinInfo> wallpaperDataCeilingList = new();
        }

        private class SteamWallpaperData
        {
            [JsonProperty("Reset Steam Workshop Wallpaper Data")]
            public bool ResetSteamWallpaper = false;

            // wallpaperID, WallpaperSkinInfo
            [JsonProperty("Steam Workshop Wallpaper")]
            public readonly Dictionary<ulong, WallpaperSkinInfo> SteamWorkshopWallpaperSkinList = new();
        }

        private class PlayerData
        {
            // userID, PlayerSettings
            [JsonProperty("PlayerData")] public readonly Dictionary<ulong, PlayerSettings> PlayerSkinData = new();
        }

        private class WallpaperSkinInfo
        {
            [JsonProperty("Enabled")] public bool Enabled = true; //TODO da implementare
            [JsonProperty("SkinID")] public ulong SkinID;
            [JsonProperty("SkinName")] public string SkinName = "";
            [JsonProperty("SkinDescription")] public string SkinDescription = "";

            [JsonProperty("For Wall", NullValueHandling = NullValueHandling.Ignore)]
            public bool? ForWall;

            [JsonProperty("For Floor", NullValueHandling = NullValueHandling.Ignore)]
            public bool? ForFloor;

            [JsonProperty("For Ceiling", NullValueHandling = NullValueHandling.Ignore)]
            public bool? ForCeiling;

            [JsonProperty("FromSteamWorkshop")] public bool FromSteamWorkshop;
        }

        private class PlayerSettings
        {
            [JsonProperty("SteamID")] public ulong SteamID;
            [JsonProperty("Username")] public string Username;
            [JsonProperty("EnableAnimation")] public bool EnableAnimation = true;
            [JsonProperty("EnableMessage")] public bool EnableMessage = true;
            [JsonProperty("DisableBskin")] public bool DisableBskin;

            [JsonProperty("Grades")]
            //gradeID - PlayerSettingsGrade
            public readonly Dictionary<int, PlayerSettingsGrade> PlayerSettingsGrade = new();

            [JsonProperty("Wallpaper Settings")]
            //PlayerSettingsGrade
            public PlayerWallpaperSettings? PlayerWallpaperSettings = new();
        }

        private class PlayerSettingsGrade
        {
            [JsonProperty("Grade")] public BuildingGrade.Enum Grade;
            [JsonProperty("SkinID")] public ulong SkinID;
            [JsonProperty("SkinColor")] public uint SkinColor;
            [JsonProperty("AutoApplyColor")] public bool AutoApplyColor = true;
            [JsonProperty("AutoApplySkin")] public bool AutoApplySkin = false;
        }

        private class PlayerWallpaperSettings
        {
            [JsonProperty("Wallpaper Apply Mode")] public WallpaperApplyModeEnum WallpaperApplyMode = WallpaperApplyModeEnum.Building;

            [JsonProperty("Wallpaper Apply To Wall Side")]
            public WallpaperSideEnum WallpaperApplyToWallSide = WallpaperSideEnum.Internal;

            [JsonProperty("Wallpaper Apply To Wall")]
            public bool WallpaperApplyToWall = false;

            [JsonProperty("Wallpaper Apply To Floor")]
            public bool WallpaperApplyToFloor = false;

            [JsonProperty("Wallpaper Apply To Ceiling")]
            public bool WallpaperApplyToCeiling = false;

            [JsonProperty("Replace Existing Wallpaper")]
            public bool ReplaceExistingWallpaper = false;

            [JsonProperty("Wallpaper Wall Selected ID")]
            public ulong WallpaperWallSelectedID = WallpaperIdForRemoval;

            [JsonProperty("Wallpaper Floor Selected ID")]
            public ulong WallpaperFloorSelectedID = WallpaperIdForRemoval;

            [JsonProperty("Wallpaper Ceiling Selected ID")]
            public ulong WallpaperCeilingSelectedID = WallpaperIdForRemoval;
        }

        private class ClanInfo
        {
            public string ClanName;
            public List<string> ClanMemberUserIdList = new();
        }

        private class ItemsPagination
        {
            public int CurrentPage = 0;
        }

        private class SteamWorkshopBodyRequest
        {
            public uint SteamAPIKey;
            public uint ItemCount;
            public ulong PublishedFileId;

            public string GetRequestBody()
            {
                return string.Format("?itemcount={0}&publishedfileids[0]={1}", ItemCount, PublishedFileId);
            }
        }

        #endregion

        #region Steam JSON Response Deserialization

        public class SteamResponseBody
        {
            [JsonProperty("response")] public SteamResponse? Response;
        }

        public class SteamResponse
        {
            [JsonProperty("result")] public int Result;
            [JsonProperty("resultcount")] public int Resultcount;

            [JsonProperty("publishedfiledetails")] public SteamPublishedFileDetails?[] Publishedfiledetails;
        }

        public class SteamPublishedFileDetails
        {
            [JsonProperty("publishedfileid")] public string PublishedFileid;
            [JsonProperty("result")] public int Result;
            [JsonProperty("creator")] public string Creator;
            [JsonProperty("creator_app_id")] public int CreatorAppId;
            [JsonProperty("consumer_app_id")] public int ConsumerAppId;
            [JsonProperty("filename")] public string Filename;
            [JsonProperty("file_size")] public string FileSize;
            [JsonProperty("file_url")] public string FileUrl;
            [JsonProperty("hcontent_file")] public string HContentFile;
            [JsonProperty("preview_url")] public string PreviewUrl;
            [JsonProperty("hcontent_preview")] public string HContentPreview;
            [JsonProperty("title")] public string Title;
            [JsonProperty("description")] public string Description;
            [JsonProperty("time_created")] public int TimeCreated;
            [JsonProperty("time_updated")] public int TimeUpdated;
            [JsonProperty("visibility")] public int Visibility;
            [JsonProperty("banned")] public bool Banned;
            [JsonProperty("ban_reason")] public string BanReason;
            [JsonProperty("subscriptions")] public int Subscriptions;
            [JsonProperty("favorited")] public int Favorited;

            [JsonProperty("lifetime_subscriptions")]
            public int LifetimeSubscriptions;

            [JsonProperty("lifetime_favorited")] public int LifetimeFavorited;
            [JsonProperty("views")] public int Views;
            [JsonProperty("tags")] public SteamTag[] Tags;

            public class SteamTag
            {
                public string tag;
            }
        }

        #endregion

        #region Localization

        private string Lang(string key, string playerID = null, params object[] args)
        {
            return string.Format(lang.GetMessage(key, this, playerID), args);
        }

        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GenericError"] = "<color=red>An error occurred: [{0}].</color>",
                ["UIHeader"] = "Building Skin",
                ["UIHeaderColorChoice"] = "Color choice",
                ["UIGradeWood"] = "Wood",
                ["UIGradeStone"] = "Stone",
                ["UIGradeMetal"] = "Metal",
                ["UIGradeTopTier"] = "TopTier",
                ["UIApply"] = "Apply",
                ["UIModeText"] = "Mode",
                ["UIReplaceText"] = "Replace",
                ["UIReplaceExistingWallpaperText"] = "Replace Existing Wallpaper",
                ["UIPageText"] = "PAGE",
                ["UISkinIDText"] = "SkinID",
                ["UISkinNameText"] = "Skin Name",
                ["UIFromSteamWorkshop"] = "From Steam",
                ["UIDlcSkinRequired"] = "REQUIRES DLC",
                ["DlcSkinRequiredErrorMessage"] = "<color=red>You do not own the DLC/Skin used</color>",

                ["UIModeBuildingText"] = "Apply to building",
                ["UIModeManualText"] = "Manual apply with hammer",
                ["UiCursorText"] = "Free move",
                ["UiApplyToWallSide"] = "Apply to the wall side",
                ["UiApplyToFloorCeiling"] = "Apply on",
                ["UiWallpaperShow"] = "View Wallpaper for",
                ["UiWallpaperRotationTool"] = "Wallpaper Rotation Tool",
                ["UiWallpaperRotationToolGive"] = "You have received in your inventory, the item to rotate wallpapers",
                ["UiWallpaperRotationToolGiveError"] = "<color=red>Unable to create item to rotate wallpaper</color>",

                ["UiApplyToWallSide_Internal"] = "Internal",
                ["UiApplyToWallSide_External"] = "External",
                ["UiApplyToWallSide_Both"] = "Both",
                ["UiWall"] = "Wall",
                ["UiFloor"] = "Floor",
                ["UiCeiling"] = "Ceiling",

                ["UiCostText"] = "Cost",
                ["UiWallpaperCostValue"] = "<color=#4A95CC>{0} of cloth</color> for each wall side or building block",
                ["UiWallpaperCostValueZero"] = "<color=#4A95CC>Free</color>",

                ["UIModeManualUsageInfoText"] =
                    "<color=orange>Manual Mode:</color> Hold down the <color=#4A95CC>R</color> (RELOAD) button and <color=#4A95CC>hit the building block with the hammer</color> to apply the selected Wallpaper",
                ["UIWallpaperUpdateBuilding"] = "Wallpaper update for selected building has started...",
                ["UIWallpaperUpdateNotRequired"] = "All wall already have your chosen Wallpaper.",
                ["UIWallpaperWallUpdateNotRequired"] = "This side of the wall or building block already has the wallpaper you have chosen.",
                ["UIWallpaperRemoveExistingIsDisabled"] = "You have disabled the replacement of existing wallpaper",
                ["UIWallpaperUpdateCompleted"] = "Skin update complete.\nUpdated {0} of {1} Building Block Wallpaper.",
                ["UIWallpaperUpdateRotation"] = "Wallpaper rotation completed.\nRotate {0} Wallpaper",
                ["UIWallpaperUpdateRotationInfoText"] =
                    "<color=orange>Rotate Wallpaper</color>\nHold down the <color=#4A95CC>SHIFT</color> button (SPRINT) and <color=#4A95CC>hit the wallpaper</color> to rotate all wallpapers",
                ["UIWallpaperRemoveCompleted"] = "Update completed.\nRemoved {0} of {1} wall Wallpaper.",
                ["UIWallpaperNotEnoughCloth"] =
                    "<color=red>You don't have enough cloth.</color> You need <color=#4A95CC>{0} cloth</color>.\nWallpapers placed: {1} - Remaining: {2} - Total building blocks: {3}",
                ["UIWallpaperApplyToWallSide_InvalidType"] = "<color=red>Wall side for wallpaper application is invalid</color>.",
                ["UIWallpaperNoWall"] = "You can place wallpapers only on walls, floors, ceilings.",

                ["UIWallpaperShowDetailPanelTitle"] = "Wallpaper detail",

                ["UIHeaderWallpaperChoice"] = "Wallpaper choice",
                ["UIWallpaperText"] = "Wallpaper",
                ["UIWallpaperIdNotValid"] = "You must enter a valid Wallpaper ID.",
                ["UIWallpaperIdError"] = "You cannot use this Wallpaper ID.",
                ["UIWallpaperIdAlreadyAdded"] = "WallpaperID has already been added.",
                ["UIWallpaperIdNotFound"] = "WallpaperID not found.",
                ["UISteamWorkshopRequestStart"] = "Steam Workshop wallpaper download request sent.",
                ["UISteamWorkshopRequestProgress"] = "Please wait for this Steam request to complete.",
                ["UIWallpaperIdRemoved"] = "WallpaperID removed.",
                ["UISaveDefaultWallpaper"] = "The selected Wallpaper has been saved as the default Wallpaper",

                ["UIBskinDisableText"] = "Bskin is disabled:",
                ["UIBskinIsDisabledText"] = "Bskin is disabled. Enable Building Skin before.",
                ["UIMessageText"] = "Message setting:",
                ["UIColorChoiceText"] = "Color choice",
                ["UIMessageTextInfo"] =
                    "Type in chat <color=#4A95CC>/bskin</color> to change the Skin or Wallpaper of the building <color=#4A95CC>or to disable this message</color>",
                ["UIMessageActivated"] = "Activated",
                ["UIMessageDeactivated"] = "Deactivated",

                ["UIUpdateBuilding"] = "Skin update for selected building has started...",
                ["UIUpdateNotRequired"] = "All building blocks already have your chosen skin.",
                ["UIUpdateCompleted"] = "Building skin update completed.\nUpdated {0} of {1} building blocks.",
                ["UINoPermissions"] = "You don't have permission to use this command.",
                ["UINotFoundBuilding"] = "Building not found. Get closer to the building and repeat again.",
                ["UINotOwnerBuilding"] = "You are not the owner of this building.",
                ["UIColorError"] = "You must also enter the color number from 0 to 16.",
                ["UIMetalSkinError"] = "You must first select at least 1 metal skin.",

                ["UIColorAutoApplyColor"] = "Auto apply color",
                ["UIColorAutoApplySkin"] = "Auto apply skin if selected",
                ["UISaveDefaultColor"] = "The selected color has been saved as the default color for the Container skin",
                ["UIUnableApplySkin"] = "Unable to apply skin",

                ["UINotFoundPlayer"] = "Player not found. Use only the Steam Id of the player.",
                ["UIUpdateAll"] = "Skin update for all your buildings has started...",
                ["UINotFoundBlocks"] = "No available buildings found for the selected player.",
                ["UIUpdateAllTarget"] = "Skin update for all players buildings has started...",
                ["UIUpdateProgress"] = "Please wait for the building skin update to finish.",
                ["UIBuildingBlocked"] = "You can't use this command if the building is blocked.",
                ["UIMissingParameter"] = "<color=red>Missing parameter</color>",
                ["UIImportWallpaperInvalidSyntax"] = "Invalid syntax, example: buildingskin.wp.addskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIRemoveWallpaperInvalidSyntax"] = "Invalid syntax, example: buildingskin.wp.removeskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UISteamWallpaperDirectUseInvalidSyntax"] = "Invalid syntax, example: bskin wp <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIImportWallpaperMissingParameter"] = "Missing parameter 1 of these parameters is required: Wall, Floor, Ceiling"
            }, this);

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GenericError"] = "<color=red>Si è verificato un errore: [{0}].</color>",
                ["UIHeader"] = "Building Skin",
                ["UIHeaderColorChoice"] = "Scelta del colore",
                ["UIGradeWood"] = "Legna",
                ["UIGradeStone"] = "Pietra",
                ["UIGradeMetal"] = "Metallo",
                ["UIGradeTopTier"] = "Metallo di alta qualità",
                ["UIApply"] = "Applicare",
                ["UIModeText"] = "Modalità",
                ["UIReplaceText"] = "Sostituisci",
                ["UIReplaceExistingWallpaperText"] = "Sostituisci Wallpaper esistente",
                ["UIPageText"] = "PAGINA",
                ["UISkinIDText"] = "SkinID",
                ["UISkinNameText"] = "Skin Name",
                ["UIFromSteamWorkshop"] = "Da Steam",
                ["UIDlcSkinRequired"] = "RICHIEDE IL DLC",
                ["DlcSkinRequiredErrorMessage"] = "<color=red>Non possiedi il DLC/Skin utilizzato</color>",

                ["UIModeBuildingText"] = "Applica all'edificio",
                ["UIModeManualText"] = "Applica con il martello",
                ["UiCursorText"] = "Movimento libero",
                ["UiApplyToWallSide"] = "Applicare sul lato della parete",
                ["UiApplyToFloorCeiling"] = "Applica sul",
                ["UiWallpaperShow"] = "Visualizza Wallpaper per",
                ["UiWallpaperRotationTool"] = "Tool Rotazione Wallpaper",
                ["UiWallpaperRotationToolGive"] = "Hai ricevuto nell'inventario, l'item per ruotare i wallpaper",
                ["UiWallpaperRotationToolGiveError"] = "<color=red>Impossibile creare l'item per ruotare i wallpaper</color>",

                ["UiApplyToWallSide_Internal"] = "Interno",
                ["UiApplyToWallSide_External"] = "Esterno",
                ["UiApplyToWallSide_Both"] = "Entrambi",
                ["UiWall"] = "Muro",
                ["UiFloor"] = "Pavimento",
                ["UiCeiling"] = "Soffitto",

                ["UiCostText"] = "Costo",
                ["UiWallpaperCostValue"] = "<color=#4A95CC>{0} di stoffa</color> per ogni lato della parete o blocco di costruzione",
                ["UiWallpaperCostValueZero"] = "<color=#4A95CC>Gratuito</color>",

                ["UIModeManualUsageInfoText"] =
                    "<color=orange>Modalità manuale:</color> Tieni premuto il pulsante <color=#4A95CC>R</color> (RICARICA) e <color=#4A95CC>colpisci il blocco di costruzione con il martello</color> per applicare lo sfondo selezionato",
                ["UIWallpaperUpdateBuilding"] = "L'aggiornamento dello sfondo per l'edificio selezionato è iniziato...",
                ["UIWallpaperUpdateNotRequired"] = "Tutte le pareti hanno già la carta da parati che hai scelto.",
                ["UIWallpaperWallUpdateNotRequired"] = "Questo lato della parete o blocco di costruzione ha già la carta da parati che hai scelto.",
                ["UIWallpaperRemoveExistingIsDisabled"] = "Hai disabilitato la sostituzione dello sfondo esistente",
                ["UIWallpaperUpdateCompleted"] = "Aggiornamento skin edificio completato.\nAggiornato {0} di {1} Wallpaper per blocco di costruzione.",
                ["UIWallpaperUpdateRotation"] = "Rotazione wallpaper completata.\nRuotati {0} Wallpaper",
                ["UIWallpaperUpdateRotationInfoText"] =
                    "<color=orange>Rotazione Wallpaper</color>\nTieni premuto il pulsante <color=#4A95CC>SHIFT</color> (SPRINT) e <color=#4A95CC>colpisci il wallpaper</color> per ruotare tutti i wallpaper",
                ["UIWallpaperRemoveCompleted"] = "Aggiornamento skin completato.\nRimossi {0} Wallpaper su {1}.",
                ["UIWallpaperNotEnoughCloth"] =
                    "<color=red>Non hai abbastanza stoffa.</color> Hai bisogno di <color=#4A95CC>{0} stoffa</color>.\nSfondi posizionati: {1} - Rimanenti: {2} - Totale blocchi di costruzione: {3}",
                ["UIWallpaperApplyToWallSide_InvalidType"] = "<color=red>Il lato della parete su cui applicare la carta da parati non è valido</color>.",
                ["UIWallpaperNoWall"] = "Puoi posizionare i wallpaper solo sui muri, pavimenti, soffitti.",

                ["UIWallpaperShowDetailPanelTitle"] = "Wallpaper Info",

                ["UIHeaderWallpaperChoice"] = "Selezione Wallpaper",
                ["UIWallpaperText"] = "Wallpaper",
                ["UIWallpaperIdNotValid"] = "Devi inserire un Wallpaper ID valido.",
                ["UIWallpaperIdError"] = "Non puoi utilizzare questo Wallpaper ID.",
                ["UIWallpaperIdAlreadyAdded"] = "WallpaperID già esistente.",
                ["UIWallpaperIdNotFound"] = "WallpaperID non trovato.",
                ["UISteamWorkshopRequestStart"] = "Richiesta di download del Wallpaper da Steam Workshop inviata.",
                ["UISteamWorkshopRequestProgress"] = "Attendi il completamento della richiesta a Steam.",
                ["UIWallpaperIdRemoved"] = "WallpaperID rimosso.",
                ["UISaveDefaultWallpaper"] = "Il Wallpaper selezionato è stato salvato come Wallpaper predefinito",

                ["UIBskinDisableText"] = "Bskin è disabilitato:",
                ["UIBskinIsDisabledText"] = "Bskin è disabilitato. Abilita prima Bskin.",
                ["UIMessageText"] = "Impostazione messaggio:",
                ["UIColorChoiceText"] = "Scelta del colore",
                ["UIMessageTextInfo"] =
                    "Digita nella chat <color=#4A95CC>/bskin</color> per cambiare la skin o lo sfondo dell'edificio <color=#4A95CC>o per disattivare questo messaggio</color>",
                ["UIMessageActivated"] = "Attivato",
                ["UIMessageDeactivated"] = "Disattivato",

                ["UIUpdateBuilding"] = "L'aggiornamento della skin per l'edificio selezionato è iniziato...",
                ["UIUpdateNotRequired"] = "Tutti i blocchi di costruzione hanno già la skin che hai scelto.",
                ["UIUpdateCompleted"] = "Aggiornamento skin completato.\nAggiornati {0} di {1} blocchi di costruzione.",
                ["UINoPermissions"] = "Non hai l'autorizzazione per usare questo comando.",
                ["UINotFoundBuilding"] = "Edificio non trovato. Avvicinati all'edificio e riprova di nuovo.",
                ["UINotOwnerBuilding"] = "Non sei il proprietario di questo edificio.",
                ["UIColorError"] = "È necessario inserire anche il numero del colore da 0 a 16.",
                ["UIMetalSkinError"] = "Per prima cosa devi selezionare almeno 1 skin container.",

                ["UIColorAutoApplyColor"] = "Applica automaticamente il colore",
                ["UIColorAutoApplySkin"] = "Applica automaticamente la skin se selezionata",
                ["UISaveDefaultColor"] = "Il colore selezionato è stato salvato come colore predefinito per la skin del container.",
                ["UIUnableApplySkin"] = "Impossibile applicare la skin.",

                ["UINotFoundPlayer"] = "Giocatore non trovato. Utilizza solo lo Steam ID del giocatore.",
                ["UIUpdateAll"] = "L'aggiornamento delle skin per tutti i tuoi edifici è iniziato...",
                ["UINotFoundBlocks"] = "Nessun edificio trovato per il giocatore selezionato.",
                ["UIUpdateAllTarget"] = "È iniziato l'aggiornamento delle skin per tutti gli edifici del giocatore...",
                ["UIUpdateProgress"] = "Attendi il completamento dell'aggiornamento delle skin dell'edificio.",
                ["UIBuildingBlocked"] = "Non puoi usare questo comando su questo edificio.",
                ["UIMissingParameter"] = "<color=red>Parametro mancante</color>",
                ["UIImportWallpaperInvalidSyntax"] = "Sintassi non valida, esempio: buildingskin.wp.addskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIRemoveWallpaperInvalidSyntax"] = "Sintassi non valida, esempio: buildingskin.wp.removeskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UISteamWallpaperDirectUseInvalidSyntax"] = "Sintassi non valida, esempio: bskin wp <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIImportWallpaperMissingParameter"] = "Parametro mancante è necessario 1 di questi parametri: Wall, Floor, Ceiling"
            }, this, "it");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GenericError"] = "<color=red>Ocurrió un error: [{0}].</color>",
                ["UIHeader"] = "Building Skin",
                ["UIHeaderColorChoice"] = "Elección de color",
                ["UIGradeWood"] = "Madera",
                ["UIGradeStone"] = "Piedra",
                ["UIGradeMetal"] = "Metálico",
                ["UIGradeTopTier"] = "Metales de alta calidad",
                ["UIApply"] = "Aplicar",
                ["UIModeText"] = "Modalidad",
                ["UIReplaceText"] = "Sustituir",
                ["UIReplaceExistingWallpaperText"] = "Reemplazar el fondo de pantalla existente",
                ["UIPageText"] = "PÁGINA",
                ["UISkinIDText"] = "SkinID",
                ["UISkinNameText"] = "Skin Name",
                ["UIFromSteamWorkshop"] = "De Steam",
                ["UIDlcSkinRequired"] = "REQUIERE DLC",
                ["DlcSkinRequiredErrorMessage"] = "<color=red>No eres dueño del DLC/Skin utilizado</color>",

                ["UIModeBuildingText"] = "Aplicar al edificio",
                ["UIModeManualText"] = "Aplicar con martillo",
                ["UiCursorText"] = "Libre movimiento",
                ["UiApplyToWallSide"] = "Aplicar al costado de la pared.",
                ["UiApplyToFloorCeiling"] = "Aplicar en",
                ["UiWallpaperShow"] = "Ver fondo de pantalla para",
                ["UiWallpaperRotationTool"] = "Herramienta de rotación de fondos",
                ["UiWallpaperRotationToolGive"] = "Has recibido en tu inventario el artículo para rotar fondos de pantalla",
                ["UiWallpaperRotationToolGiveError"] = "<color=red>No se puede crear el elemento para rotar el fondo de pantalla</color>",

                ["UiApplyToWallSide_Internal"] = "Interno",
                ["UiApplyToWallSide_External"] = "Externo",
                ["UiApplyToWallSide_Both"] = "Ambos",
                ["UiWall"] = "Muro",
                ["UiFloor"] = "Piso",
                ["UiCeiling"] = "Techo",

                ["UiCostText"] = "Costo",
                ["UiWallpaperCostValue"] = "<color=#4A95CC>{0} de tela</color> para cada lado de la pared o bloque de construcción",
                ["UiWallpaperCostValueZero"] = "<color=#4A95CC>Gratis</color>",

                ["UIModeManualUsageInfoText"] =
                    "<color=orange>Modo manual:</color> Mantenga presionado el botón <color=#4A95CC>R</color> (RECARGAR) y <color=#4A95CC>golpee el bloque de construcción con el martillo</color> para aplicar el fondo seleccionado",
                ["UIWallpaperUpdateBuilding"] = "La actualización del fondo de pantalla para el edificio seleccionado ha comenzado...",
                ["UIWallpaperUpdateNotRequired"] = "Todas las paredes ya tienen el papel pintado que elegiste.",
                ["UIWallpaperWallUpdateNotRequired"] = "Este lado de la pared o bloque de construcción ya tiene el papel tapiz que has elegido.",
                ["UIWallpaperRemoveExistingIsDisabled"] = "Has desactivado el reemplazo del fondo de pantalla existente.",
                ["UIWallpaperUpdateCompleted"] = "Se completó la actualización del aspecto del edificio.\nSe actualizó {0} de {1} fondo de pantalla de bloques de construcción.",
                ["UIWallpaperUpdateRotation"] = "Rotación de fondo de pantalla completada.\nRotar {0} fondo de pantalla",
                ["UIWallpaperUpdateRotationInfoText"] =
                    "<color=orange>Rotar fondo de pantalla</color>\nMantenga presionada la tecla <color=#4A95CC>SHIFT</color> (SPRINT) y <color=#4A95CC>presione el fondo de pantalla</color> para rotar todos los fondos de pantalla",
                ["UIWallpaperRemoveCompleted"] = "Actualización de máscara completa.\nSe eliminó el fondo de pantalla {0} en {1}.",
                ["UIWallpaperNotEnoughCloth"] =
                    "<color=red>No tienes suficiente tela.</color> Necesitas <color=#4A95CC>{0} tela</color>.\nFondos de pantalla colocados: {1} - Restantes: {2} - Total de bloques de construcción: {3}",
                ["UIWallpaperApplyToWallSide_InvalidType"] = "<color=red>El lado de la pared para aplicar el papel tapiz no es válido</color>.",
                ["UIWallpaperNoWall"] = "Puedes colocar papeles pintados solo en paredes, suelos y techos.",

                ["UIWallpaperShowDetailPanelTitle"] = "Wallpaper Info",

                ["UIHeaderWallpaperChoice"] = "Selección Wallpaper",
                ["UIWallpaperText"] = "Wallpaper",
                ["UIWallpaperIdNotValid"] = "Debes ingresar una Wallpaper ID válida.",
                ["UIWallpaperIdError"] = "No puedes utilizar esta Wallpaper ID.",
                ["UIWallpaperIdAlreadyAdded"] = "WallpaperID ya existe.",
                ["UIWallpaperIdNotFound"] = "WallpaperID no encontrado.",
                ["UISteamWorkshopRequestStart"] = "Se envió la solicitud de descarga de fondo de pantalla de Steam Workshop.",
                ["UISteamWorkshopRequestProgress"] = "Espere a que se complete su solicitud de Steam.",
                ["UIWallpaperIdRemoved"] = "Se eliminó el WallpaperID.",
                ["UISaveDefaultWallpaper"] = "El Wallpaper seleccionado se ha guardado como Wallpaper predeterminado",

                ["UIBskinDisableText"] = "Bskin está deshabilitado:",
                ["UIBskinIsDisabledText"] = "Bskin está deshabilitado. Habilite Bskin primero.",
                ["UIMessageText"] = "Configuración de mensaje:",
                ["UIColorChoiceText"] = "Elección de color",
                ["UIMessageTextInfo"] =
                    "Escribe <color=#4A95CC>/bskin</color> en el chat para cambiar la apariencia o el fondo del edificio <color=#4A95CC> o para desactivar este mensaje</color>",
                ["UIMessageActivated"] = "Activado",
                ["UIMessageDeactivated"] = "Desactivado",

                ["UIUpdateBuilding"] = "La actualización de la apariencia para el edificio seleccionado ha comenzado...",
                ["UIUpdateNotRequired"] = "Todos los bloques de construcción ya tienen la apariencia que elegiste.",
                ["UIUpdateCompleted"] = "Actualización de máscara completa.\nSe actualizó {0} de {1} bloques de construcción.",
                ["UINoPermissions"] = "No tienes permiso para utilizar este comando.",
                ["UINotFoundBuilding"] = "Edificio no encontrado. Acércate al edificio e inténtalo de nuevo.",
                ["UINotOwnerBuilding"] = "No eres dueño de este edificio.",
                ["UIColorError"] = "También debes ingresar el número de color del 0 al 16.",
                ["UIMetalSkinError"] = "Primero debes seleccionar al menos 1 diseño de contenedor.",

                ["UIColorAutoApplyColor"] = "Aplica color automáticamente",
                ["UIColorAutoApplySkin"] = "Aplicar automáticamente la piel si se selecciona",
                ["UISaveDefaultColor"] = "El color seleccionado se ha guardado como color predeterminado para el diseño del contenedor.",
                ["UIUnableApplySkin"] = "No se puede aplicar la piel.",

                ["UINotFoundPlayer"] = "Jugador no encontrado. Utilice únicamente el ID de Steam del jugador.",
                ["UIUpdateAll"] = "La actualización de aspectos para todos tus edificios ha comenzado...",
                ["UINotFoundBlocks"] = "No se encontraron edificios para el jugador seleccionado.",
                ["UIUpdateAllTarget"] = "La actualización de máscaras para todos los edificios de jugadores ha comenzado...",
                ["UIUpdateProgress"] = "Espere a que se complete la actualización de los aspectos del edificio.",
                ["UIBuildingBlocked"] = "No puedes usar este comando en este edificio.",
                ["UIMissingParameter"] = "<color=rojo>Parámetro faltante</color>",
                ["UIImportWallpaperInvalidSyntax"] = "Sintaxis no válida, ejemplo: buildingskin.wp.addskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIRemoveWallpaperInvalidSyntax"] = "Sintaxis no válida, ejemplo: buildingskin.wp.removeskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UISteamWallpaperDirectUseInvalidSyntax"] = "Sintaxis no válida, ejemplo: bskin wp <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIImportWallpaperMissingParameter"] = "Falta el parámetro 1 de estos parámetros: Wall, Floor, Ceiling"
            }, this, "es");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GenericError"] = "<color=red>Ocurrió un error: [{0}].</color>",
                ["UIHeader"] = "Building Skin",
                ["UIHeaderColorChoice"] = "Elección de color",
                ["UIGradeWood"] = "Madera",
                ["UIGradeStone"] = "Piedra",
                ["UIGradeMetal"] = "Metálico",
                ["UIGradeTopTier"] = "Metales de alta calidad",
                ["UIApply"] = "Aplicar",
                ["UIModeText"] = "Modalidad",
                ["UIReplaceText"] = "Sustituir",
                ["UIReplaceExistingWallpaperText"] = "Reemplazar el fondo de pantalla existente",
                ["UIPageText"] = "PÁGINA",
                ["UISkinIDText"] = "SkinID",
                ["UISkinNameText"] = "Skin Name",
                ["UIFromSteamWorkshop"] = "De Steam",
                ["UIDlcSkinRequired"] = "REQUIERE DLC",
                ["DlcSkinRequiredErrorMessage"] = "<color=red>No eres dueño del DLC/Skin utilizado</color>",

                ["UIModeBuildingText"] = "Aplicar al edificio",
                ["UIModeManualText"] = "Aplicar con martillo",
                ["UiCursorText"] = "Libre movimiento",
                ["UiApplyToWallSide"] = "Aplicar al costado de la pared.",
                ["UiApplyToFloorCeiling"] = "Aplicar en",
                ["UiWallpaperShow"] = "Ver fondo de pantalla para",
                ["UiWallpaperRotationTool"] = "Herramienta de rotación de fondos",
                ["UiWallpaperRotationToolGive"] = "Has recibido en tu inventario el artículo para rotar fondos de pantalla",
                ["UiWallpaperRotationToolGiveError"] = "<color=red>No se puede crear el elemento para rotar el fondo de pantalla</color>",

                ["UiApplyToWallSide_Internal"] = "Interno",
                ["UiApplyToWallSide_External"] = "Externo",
                ["UiApplyToWallSide_Both"] = "Ambos",
                ["UiWall"] = "Muro",
                ["UiFloor"] = "Piso",
                ["UiCeiling"] = "Techo",

                ["UiCostText"] = "Costo",
                ["UiWallpaperCostValue"] = "<color=#4A95CC>{0} de tela</color> para cada lado de la pared o bloque de construcción",
                ["UiWallpaperCostValueZero"] = "<color=#4A95CC>Gratis</color>",

                ["UIModeManualUsageInfoText"] =
                    "<color=orange>Modo manual:</color> Mantenga presionado el botón <color=#4A95CC>R</color> (RECARGAR) y <color=#4A95CC>golpee el bloque de construcción con el martillo</color> para aplicar el fondo seleccionado",
                ["UIWallpaperUpdateBuilding"] = "La actualización del fondo de pantalla para el edificio seleccionado ha comenzado...",
                ["UIWallpaperUpdateNotRequired"] = "Todas las paredes ya tienen el papel pintado que elegiste.",
                ["UIWallpaperWallUpdateNotRequired"] = "Este lado de la pared o bloque de construcción ya tiene el papel tapiz que has elegido.",
                ["UIWallpaperRemoveExistingIsDisabled"] = "Has desactivado el reemplazo del fondo de pantalla existente.",
                ["UIWallpaperUpdateCompleted"] = "Se completó la actualización del aspecto del edificio.\nSe actualizó {0} de {1} fondo de pantalla de bloques de construcción.",
                ["UIWallpaperUpdateRotation"] = "Rotación de fondo de pantalla completada.\nRotar {0} fondo de pantalla",
                ["UIWallpaperUpdateRotationInfoText"] =
                    "<color=orange>Rotar fondo de pantalla</color>\nMantenga presionada la tecla <color=#4A95CC>SHIFT</color> (SPRINT) y <color=#4A95CC>presione el fondo de pantalla</color> para rotar todos los fondos de pantalla",
                ["UIWallpaperRemoveCompleted"] = "Actualización de máscara completa.\nSe eliminó el fondo de pantalla {0} en {1}.",
                ["UIWallpaperNotEnoughCloth"] =
                    "<color=red>No tienes suficiente tela.</color> Necesitas <color=#4A95CC>{0} tela</color>.\nFondos de pantalla colocados: {1} - Restantes: {2} - Total de bloques de construcción: {3}",
                ["UIWallpaperApplyToWallSide_InvalidType"] = "<color=red>El lado de la pared para aplicar el papel tapiz no es válido</color>.",
                ["UIWallpaperNoWall"] = "Puedes colocar papeles pintados solo en paredes, suelos y techos.",

                ["UIWallpaperShowDetailPanelTitle"] = "Wallpaper Info",

                ["UIHeaderWallpaperChoice"] = "Selección Wallpaper",
                ["UIWallpaperText"] = "Wallpaper",
                ["UIWallpaperIdNotValid"] = "Debes ingresar una Wallpaper ID válida.",
                ["UIWallpaperIdError"] = "o puedes utilizar esta Wallpaper ID.",
                ["UIWallpaperIdAlreadyAdded"] = "WallpaperID ya existe.",
                ["UIWallpaperIdNotFound"] = "WallpaperID no encontrado.",
                ["UISteamWorkshopRequestStart"] = "Se envió la solicitud de descarga de fondo de pantalla de Steam Workshop.",
                ["UISteamWorkshopRequestProgress"] = "Espere a que se complete su solicitud de Steam.",
                ["UIWallpaperIdRemoved"] = "Se eliminó el WallpaperID.",
                ["UISaveDefaultWallpaper"] = "El Wallpaper seleccionado se ha guardado como Wallpaper predeterminado",

                ["UIBskinDisableText"] = "Bskin está deshabilitado:",
                ["UIBskinIsDisabledText"] = "Bskin está deshabilitado. Habilite Bskin primero.",
                ["UIMessageText"] = "Configuración de mensaje:",
                ["UIColorChoiceText"] = "Elección de color",
                ["UIMessageTextInfo"] =
                    "Escribe <color=#4A95CC>/bskin</color> en el chat para cambiar la apariencia o el fondo del edificio <color=#4A95CC> o para desactivar este mensaje</color>",
                ["UIMessageActivated"] = "Activado",
                ["UIMessageDeactivated"] = "Desactivado",

                ["UIUpdateBuilding"] = "La actualización de la apariencia para el edificio seleccionado ha comenzado...",
                ["UIUpdateNotRequired"] = "Todos los bloques de construcción ya tienen la apariencia que elegiste.",
                ["UIUpdateCompleted"] = "Actualización de máscara completa.\nSe actualizó {0} de {1} bloques de construcción.",
                ["UINoPermissions"] = "No tienes permiso para utilizar este comando.",
                ["UINotFoundBuilding"] = "Edificio no encontrado. Acércate al edificio e inténtalo de nuevo.",
                ["UINotOwnerBuilding"] = "No eres dueño de este edificio.",
                ["UIColorError"] = "También debes ingresar el número de color del 0 al 16.",
                ["UIMetalSkinError"] = "Primero debes seleccionar al menos 1 diseño de contenedor.",

                ["UIColorAutoApplyColor"] = "Aplica color automáticamente",
                ["UIColorAutoApplySkin"] = "Aplicar automáticamente la piel si se selecciona",
                ["UISaveDefaultColor"] = "El color seleccionado se ha guardado como color predeterminado para el diseño del contenedor.",
                ["UIUnableApplySkin"] = "No se puede aplicar la piel.",

                ["UINotFoundPlayer"] = "Jugador no encontrado. Utilice únicamente el ID de Steam del jugador.",
                ["UIUpdateAll"] = "La actualización de aspectos para todos tus edificios ha comenzado...",
                ["UINotFoundBlocks"] = "No se encontraron edificios para el jugador seleccionado.",
                ["UIUpdateAllTarget"] = "La actualización de máscaras para todos los edificios de jugadores ha comenzado...",
                ["UIUpdateProgress"] = "Espere a que se complete la actualización de los aspectos del edificio.",
                ["UIBuildingBlocked"] = "No puedes usar este comando en este edificio.",
                ["UIMissingParameter"] = "<color=rojo>Parámetro faltante</color>",
                ["UIImportWallpaperInvalidSyntax"] = "Sintaxis no válida, ejemplo: buildingskin.wp.addskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIRemoveWallpaperInvalidSyntax"] = "Sintaxis no válida, ejemplo: buildingskin.wp.removeskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UISteamWallpaperDirectUseInvalidSyntax"] = "Sintaxis no válida, ejemplo: bskin wp <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIImportWallpaperMissingParameter"] = "Falta el parámetro 1 de estos parámetros: Wall, Floor, Ceiling"
            }, this, "es-ES");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GenericError"] = "<color=red>Es ist ein Fehler aufgetreten: [{0}].</color>",
                ["UIHeader"] = "Building Skin",
                ["UIHeaderColorChoice"] = "Farbauswahl",
                ["UIGradeWood"] = "Holz",
                ["UIGradeStone"] = "Stein",
                ["UIGradeMetal"] = "Metall",
                ["UIGradeTopTier"] = "Hochwertiges Metall",
                ["UIApply"] = "Anwenden",
                ["UIModeText"] = "Modus",
                ["UIReplaceText"] = "Ersetzen",
                ["UIReplaceExistingWallpaperText"] = "Ersetzen Sie das vorhandene Hintergrundbild",
                ["UIPageText"] = "SEITE",
                ["UISkinIDText"] = "SkinID",
                ["UISkinNameText"] = "Skin Name",
                ["UIFromSteamWorkshop"] = "Von Steam",
                ["UIDlcSkinRequired"] = "ERFORDERT DLC",
                ["DlcSkinRequiredErrorMessage"] = "<color=red>Du besitzt den verwendeten DLC/Skin nicht</color>",

                ["UIModeBuildingText"] = "Bewerben Sie sich beim Bau",
                ["UIModeManualText"] = "Mit Hammer auftragen",
                ["UiCursorText"] = "Bewegungsfreiheit",
                ["UiApplyToWallSide"] = "Auf die Wandseite auftragen",
                ["UiApplyToFloorCeiling"] = "Anwenden auf die",
                ["UiWallpaperShow"] = "Hintergrundbild anzeigen für",
                ["UiWallpaperRotationTool"] = "Werkzeug zur Tapetendrehung",
                ["UiWallpaperRotationToolGive"] = "Sie haben in Ihrem Inventar den Artikel zum Drehen von Tapeten erhalten",
                ["UiWallpaperRotationToolGiveError"] = "<color=red>Element zum Drehen des Hintergrundbilds konnte nicht erstellt werden</color>",

                ["UiApplyToWallSide_Internal"] = "Intern",
                ["UiApplyToWallSide_External"] = "Extern",
                ["UiApplyToWallSide_Both"] = "Beide",
                ["UiWall"] = "Wand",
                ["UiFloor"] = "Fußboden",
                ["UiCeiling"] = "Decke",

                ["UiCostText"] = "Preis",
                ["UiWallpaperCostValue"] = "<color=#4A95CC>{0} Stoff</color> für jede Seite der Wand oder des Bausteins",
                ["UiWallpaperCostValueZero"] = "<color=#4A95CC>Kostenlos</color>",

                ["UIModeManualUsageInfoText"] =
                    "<color=orange>Manueller Modus:</color> Halten Sie die Taste <color=#4A95CC>R</color> (AUFLADEN) gedrückt und <color=#4A95CC>schlagen Sie mit dem Hammer auf den Baustein</color>, um den ausgewählten Hintergrund anzuwenden",
                ["UIWallpaperUpdateBuilding"] = "Das Hintergrundbild-Update für das ausgewählte Gebäude hat begonnen...",
                ["UIWallpaperUpdateNotRequired"] = "Alle Wände sind bereits mit der von Ihnen gewählten Tapete versehen.",
                ["UIWallpaperWallUpdateNotRequired"] = "Auf dieser Seite der Wand bzw. des Bausteins ist bereits die von Ihnen gewählte Tapete angebracht.",
                ["UIWallpaperRemoveExistingIsDisabled"] = "Alle Wände sind bereits mit der von Ihnen gewählten Tapete versehen.",
                ["UIWallpaperUpdateCompleted"] = "Gebäude-Skin-Update abgeschlossen.\n{0} von {1} Baustein-Hintergrundbildern aktualisiert.",
                ["UIWallpaperUpdateRotation"] = "Hintergrundbildrotation abgeschlossen.\n{0} Hintergrundbild rotieren",
                ["UIWallpaperUpdateRotationInfoText"] =
                    "<color=orange>Hintergrundbild drehen</color>\nHalten Sie die <color=#4A95CC>SHIFT</color> (SPRINT) gedrückt und <color=#4A95CC>drücken Sie auf das Hintergrundbild</color>, um alle Hintergrundbilder zu drehen",
                ["UIWallpaperRemoveCompleted"] = "Skin-Update abgeschlossen.\nHintergrundbild {0} auf {1} entfernt.",
                ["UIWallpaperNotEnoughCloth"] =
                    "<color=red>Du hast nicht genug Stoff.</color> Du brauchst <color=#4A95CC>{0} Stoff</color>.\nHintergründe platziert: {1} - Verbleibend: {2} - Gesamtzahl der Bausteine: {3}",
                ["UIWallpaperApplyToWallSide_InvalidType"] = "<color=red>Die Seite der Wand, auf der das Hintergrundbild angebracht werden soll, ist ungültig</color>.",
                ["UIWallpaperNoWall"] = "Sie können Tapeten nur auf Wänden, Böden und Decken anbringen.",

                ["UIWallpaperShowDetailPanelTitle"] = "Wallpaper Info",

                ["UIHeaderWallpaperChoice"] = "Selektion Wallpaper",
                ["UIWallpaperText"] = "Wallpaper",
                ["UIWallpaperIdNotValid"] = "Sie müssen eine gültige Wallpaper-ID eingeben.",
                ["UIWallpaperIdError"] = "Sie können diese Wallpaper-ID nicht verwenden.",
                ["UIWallpaperIdAlreadyAdded"] = "WallpaperID existiert bereits.",
                ["UIWallpaperIdNotFound"] = "WallpaperID nicht gefunden.",
                ["UISteamWorkshopRequestStart"] = "Wallpaper-Download-Anfrage von Steam Workshop gesendet.",
                ["UISteamWorkshopRequestProgress"] = "Warten Sie, bis Ihre Steam-Anfrage abgeschlossen ist.",
                ["UIWallpaperIdRemoved"] = "WallpaperID entfernt.",
                ["UISaveDefaultWallpaper"] = "Das ausgewählte Hintergrundbild wurde als Standard-Hintergrundbild gespeichert",

                ["UIBskinDisableText"] = "Bskin ist deaktiviert:",
                ["UIBskinIsDisabledText"] = "Bskin ist deaktiviert. Aktivieren Sie zuerst Bskin.",
                ["UIMessageText"] = "Nachrichteneinstellung:",
                ["UIColorChoiceText"] = "Farbauswahl",
                ["UIMessageTextInfo"] =
                    "Geben Sie <color=#4A95CC>/bskin</color> in den Chat ein, um die Haut oder den Hintergrund des Gebäudes zu ändern <color=#4A95CC> oder diese Nachricht zu deaktivieren</color>",
                ["UIMessageActivated"] = "Aktiviert",
                ["UIMessageDeactivated"] = "Deaktiviert",

                ["UIUpdateBuilding"] = "Das Skin-Update für das ausgewählte Gebäude hat begonnen ...",
                ["UIUpdateNotRequired"] = "Das Skin-Update für das ausgewählte Gebäude hat begonnen ...",
                ["UIUpdateCompleted"] = "Skin-Update abgeschlossen.\n{0} von {1} Bausteinen aktualisiert.",
                ["UINoPermissions"] = "Sie haben keine Berechtigung, diesen Befehl zu verwenden.",
                ["UINotFoundBuilding"] = "Gebäude nicht gefunden. Nähern Sie sich dem Gebäude und versuchen Sie es erneut.",
                ["UINotOwnerBuilding"] = "Sie sind nicht Eigentümer dieses Gebäudes.",
                ["UIColorError"] = "Sie müssen außerdem die Farbnummer von 0 bis 16 eingeben.",
                ["UIMetalSkinError"] = "Zuerst müssen Sie mindestens 1 Container-Skin auswählen.",

                ["UIColorAutoApplyColor"] = "Wendet automatisch Farbe an",
                ["UIColorAutoApplySkin"] = "Bei Auswahl die Skin automatisch anwenden",
                ["UISaveDefaultColor"] = "Die ausgewählte Farbe wurde als Standardfarbe für die Container-Skin gespeichert.",
                ["UIUnableApplySkin"] = "Haut kann nicht aufgetragen werden.",

                ["UINotFoundPlayer"] = "Spieler nicht gefunden. Verwenden Sie nur die Steam-ID des Spielers.",
                ["UIUpdateAll"] = "Die Aktualisierung der Skins für alle Ihre Gebäude hat begonnen ...",
                ["UINotFoundBlocks"] = "Für den ausgewählten Spieler wurden keine Gebäude gefunden.",
                ["UIUpdateAllTarget"] = "Die Aktualisierung der Skins für alle Spielergebäude hat begonnen ...",
                ["UIUpdateProgress"] = "Warten Sie, bis die Aktualisierung der Gebäude-Skins abgeschlossen ist.",
                ["UIBuildingBlocked"] = "Sie können diesen Befehl für dieses Gebäude nicht verwenden.",
                ["UIMissingParameter"] = "<color=red>Fehlender Parameter</color>",
                ["UIImportWallpaperInvalidSyntax"] = "Ungültige Syntax, Beispiel: buildingskin.wp.addskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIRemoveWallpaperInvalidSyntax"] = "Ungültige Syntax, Beispiel: buildingskin.wp.removeskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UISteamWallpaperDirectUseInvalidSyntax"] = "Ungültige Syntax, Beispiel: bskin wp <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIImportWallpaperMissingParameter"] = "Fehlender Parameter. 1 dieser Parameter ist erforderlich: Wall, Floor, Ceiling"
            }, this, "de");

            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["GenericError"] = "<color=red>Произошла ошибка: [{0}].</color>",
                ["UIHeader"] = "Building Skin",
                ["UIHeaderColorChoice"] = "Выбор цвета",
                ["UIGradeWood"] = "Древесина",
                ["UIGradeStone"] = "Камень",
                ["UIGradeMetal"] = "Металл",
                ["UIGradeTopTier"] = "Высококачественный металл",
                ["UIApply"] = "Применять",
                ["UIModeText"] = "Режим",
                ["UIReplaceText"] = "Заменять",
                ["UIReplaceExistingWallpaperText"] = "Заменить существующие обои",
                ["UIPageText"] = "СТРАНИЦА",
                ["UISkinIDText"] = "SkinID",
                ["UISkinNameText"] = "Skin Name",
                ["UIFromSteamWorkshop"] = "Через Steam",
                ["UIDlcSkinRequired"] = "ТРЕБУЕТСЯ DLC",
                ["DlcSkinRequiredErrorMessage"] = "<color=red>Вы не являетесь владельцем используемого DLC/скина.</color>",

                ["UIModeBuildingText"] = "Подать заявку на строительство",
                ["UIModeManualText"] = "Наносить молотком",
                ["UiCursorText"] = "Свободное перемещение",
                ["UiApplyToWallSide"] = "Нанесите на боковую часть стены",
                ["UiApplyToFloorCeiling"] = "Подать заявку на",
                ["UiWallpaperShow"] = "Посмотреть обои для",
                ["UiWallpaperRotationTool"] = "Инструмент для поворота обоев",
                ["UiWallpaperRotationToolGive"] = "Вы получили в свой инвентарь предмет для поворота обоев",
                ["UiWallpaperRotationToolGiveError"] = "<color=red>Невозможно создать элемент для поворота обоев</color>",

                ["UiApplyToWallSide_Internal"] = "Внутренний",
                ["UiApplyToWallSide_External"] = "Внешний",
                ["UiApplyToWallSide_Both"] = "Оба",
                ["UiWall"] = "Стена",
                ["UiFloor"] = "Пол",
                ["UiCeiling"] = "Потолок",

                ["UiCostText"] = "Расходы",
                ["UiWallpaperCostValue"] = "<color=#4A95CC>{0} ткани</color> для каждой стороны стены или строительного блока",
                ["UiWallpaperCostValueZero"] = "<color=#4A95CC>Бесплатно</color>",

                ["UIModeManualUsageInfoText"] =
                    "<color=orange>Ручной режим:</color> Удерживайте кнопку <color=#4A95CC>R</color> (ПЕРЕЗАРЯДКА) и <color=#4A95CC>ударите молотком по строительному блоку</color>, чтобы применить выбранный фон",
                ["UIWallpaperUpdateBuilding"] = "Обновление обоев для выбранного здания началось...",
                ["UIWallpaperUpdateNotRequired"] = "На всех стенах уже есть выбранные вами обои.",
                ["UIWallpaperWallUpdateNotRequired"] = "На этой стороне стены или строительного блока уже оклеены выбранные вами обои.",
                ["UIWallpaperRemoveExistingIsDisabled"] = "На всех стенах уже есть выбранные вами обои.",
                ["UIWallpaperUpdateCompleted"] = "Обновление скина здания завершено.\nОбновлены {0} из {1} обоев Building Block.",
                ["UIWallpaperUpdateRotation"] = "Ротация обоев завершена.\nПовернуть {0} обои",
                ["UIWallpaperUpdateRotationInfoText"] =
                    "<color=orange>Поворот обоев</color>\nУдерживайте кнопку <color=#4A95CC>SHIFT</color> (SPRINT) и <color=#4A95CC>нажмите на обои</color>, чтобы повернуть все обои.",
                ["UIWallpaperRemoveCompleted"] = "Обновление скина завершено.\nУдалены {0} обои на {1}.",
                ["UIWallpaperNotEnoughCloth"] =
                    "<color=red>У вас недостаточно ткани.</color> Вам нужно <color=#4A95CC>{0} ткани</color>.\nРазмещено фонов: {1} - Осталось: {2} - Всего строительных блоков: {3}",
                ["UIWallpaperApplyToWallSide_InvalidType"] = "<color=red>Недопустимая сторона стены, на которую нужно наклеить обои</color>.",
                ["UIWallpaperNoWall"] = "Обои можно клеить только на стены, полы и потолки.",

                ["UIWallpaperShowDetailPanelTitle"] = "Wallpaper Info",

                ["UIHeaderWallpaperChoice"] = "Подбор Wallpaper",
                ["UIWallpaperText"] = "Wallpaper",
                ["UIWallpaperIdNotValid"] = "Вы должны ввести действительный идентификатор обоев.",
                ["UIWallpaperIdError"] = "Вы не можете использовать этот идентификатор обоев.",
                ["UIWallpaperIdAlreadyAdded"] = "WallpaperID уже существует.",
                ["UIWallpaperIdNotFound"] = "ОбоиID не найден.",
                ["UISteamWorkshopRequestStart"] = "Запрос на загрузку обоев из Мастерской Steam отправлен.",
                ["UISteamWorkshopRequestProgress"] = "Подождите, пока ваш запрос Steam завершится.",
                ["UIWallpaperIdRemoved"] = "WallpaperID удален.",
                ["UISaveDefaultWallpaper"] = "Выбранные обои сохранены как обои по умолчанию.",

                ["UIBskinDisableText"] = "Бскин отключен:",
                ["UIBskinIsDisabledText"] = "Бскин отключен. Сначала включите Bskin.",
                ["UIMessageText"] = "Настройка сообщения:",
                ["UIColorChoiceText"] = "Выбор цвета",
                ["UIMessageTextInfo"] =
                    "Введите <color=#4A95CC>/bskin</color> в чате, чтобы изменить скин или фон здания <color=#4A95CC> или деактивировать это сообщение</color>",
                ["UIMessageActivated"] = "Активировано",
                ["UIMessageDeactivated"] = "Деактивирован",

                ["UIUpdateBuilding"] = "Обновление скина для выбранного здания началось...",
                ["UIUpdateNotRequired"] = "Обновление скина для выбранного здания началось...",
                ["UIUpdateCompleted"] = "Обновление скина завершено.\nОбновлено {0} из {1} стандартных блоков.",
                ["UINoPermissions"] = "У вас нет разрешения на использование этой команды.",
                ["UINotFoundBuilding"] = "Здание не найдено. Подойдите к зданию и попробуйте еще раз.",
                ["UINotOwnerBuilding"] = "Вы не являетесь владельцем этого здания.",
                ["UIColorError"] = "Также необходимо ввести номер цвета от 0 до 16.",
                ["UIMetalSkinError"] = "Сначала вам нужно выбрать хотя бы 1 скин контейнера.",

                ["UIColorAutoApplyColor"] = "Автоматически применяет цвет",
                ["UIColorAutoApplySkin"] = "Автоматически применять скин, если выбран этот параметр.",
                ["UISaveDefaultColor"] = "Выбранный цвет сохранен как цвет по умолчанию для оформления контейнера.",
                ["UIUnableApplySkin"] = "Невозможно нанести кожу.",

                ["UINotFoundPlayer"] = "Игрок не найден. Используйте только Steam ID игрока.",
                ["UIUpdateAll"] = "Началось обновление скинов для всех ваших построек...",
                ["UINotFoundBlocks"] = "Для выбранного игрока не найдено зданий.",
                ["UIUpdateAllTarget"] = "Началось обновление скинов всех построек игроков...",
                ["UIUpdateProgress"] = "Дождитесь завершения обновления скинов зданий.",
                ["UIBuildingBlocked"] = "Вы не можете использовать эту команду в этом здании.",
                ["UIMissingParameter"] = "<color=red>Отсутствует параметр</color>",
                ["UIImportWallpaperInvalidSyntax"] = "Неверный синтаксис, пример: buildingskin.wp.addskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIRemoveWallpaperInvalidSyntax"] = "Неверный синтаксис, пример: buildingskin.wp.removeskin <Wall, Floor, Ceiling> <wallpaperID>",
                ["UISteamWallpaperDirectUseInvalidSyntax"] = "Неверный синтаксис, пример: bskin wp <Wall, Floor, Ceiling> <wallpaperID>",
                ["UIImportWallpaperMissingParameter"] = "Отсутствует параметр 1 из этих параметров, требуется: Wall, Floor, Ceiling"
            }, this, "ru");
        }

        #endregion
    }
}