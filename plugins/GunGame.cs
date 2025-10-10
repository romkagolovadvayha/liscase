using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Game.Rust.Cui;
using UnityEngine;

namespace Oxide.Plugins;

[Info("GunGame", "CASHR#6906", "1.0.0")]
internal class GunGame : RustPlugin
{
    private Configuration _config;
    private static GunGame _;
    private GameController _gameController;
    private Dictionary<BasePlayer, DateTime> PlayerToIgnoreDamage = new();
    private Dictionary<BasePlayer, VoteBanSettings> VoteBanList = new();

    private class VoteBanSettings
    {
        public int VoteCount { get; set; } = 0;
        public DateTime TimeToUnban { get; set; } = DateTime.MinValue;
        public List<string> UserToSendVote { get; set; } = new List<string>();
    }
    #region Config

    private class Configuration
    {
        [JsonProperty(PropertyName = "Количество голосов для бана", ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int BanAmount = 4;

        [JsonProperty(PropertyName = "На сколько банить в секунда",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int TimeToBan = 600;
        [JsonProperty(PropertyName = "Сколько игнорировать урон после спавна",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int TimeToSave = 5;
        [JsonProperty(PropertyName = "Через сколько запускать новую игру?",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int TimeToRestart = 30;

        [JsonProperty(PropertyName = "Сколько хп давать за убийство",
            ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public int HealAmount = 20;
        [JsonProperty(PropertyName = "Список оружие(в каком порядке их получают игроки)", ObjectCreationHandling = ObjectCreationHandling.Replace)]
        public readonly List<WeaponSettings> WeaponList = new List<WeaponSettings>()
        {
            new WeaponSettings(),
            new WeaponSettings(),
            new WeaponSettings(),
            new WeaponSettings(),
        };


        internal class WeaponSettings
        {
            
            [JsonProperty("Index weapon")] public readonly int IndexWeapon = 1;
            [JsonProperty("Сколько убийств до нового уровня")]
            public readonly int NeedKills = 5;
            [JsonProperty("Weapon shortName")] public readonly string WeaponShortName = "rifle.bolt";

            [JsonProperty(PropertyName = "Список одежды который получит игрок на этом уровне",
                ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public List<string> WearList = new List<string>()
            {
            };
            
            
            [JsonProperty(PropertyName = "Список модулей для оружия",
                ObjectCreationHandling = ObjectCreationHandling.Replace)]
            public List<string> ModuleList = new List<string>()
            {
            };
        }
    }

    protected override void LoadConfig()
    {
        base.LoadConfig();
        try
        {
            _config = Config.ReadObject<Configuration>();
            if (_config == null) throw new Exception();
            SaveConfig();
        }
        catch
        {
            PrintError("Your configuration file contains an error. Using default configuration values.");
            LoadDefaultConfig();
        }
    }

    protected override void SaveConfig()
    {
        Config.WriteObject(_config);
    }

    protected override void LoadDefaultConfig()
    {
        _config = new Configuration();
    }

    #endregion

    #region OxideHooks

    private void OnServerInitialized()
    {
        _ = this;
        _gameController = ServerMgr.Instance.gameObject.AddComponent<GameController>();
        PrintError("|-----------------------------------|");
        PrintWarning($"|  Plugin {Title} v{Version} is loaded  |");
        PrintWarning("|          Discord: CASHR#6906      |");
        PrintError("|-----------------------------------|");
    }
    private object OnPlayerWound( BasePlayer player, HitInfo info )
    {
        return false;
    }
   
    private object OnEntityTakeDamage(BasePlayer player, HitInfo info)
    {
        var attacker = info?.InitiatorPlayer;
        if (PlayerToIgnoreDamage.ContainsKey(player))
        {
            if (PlayerToIgnoreDamage[player] < DateTime.Now)
            {
                PlayerToIgnoreDamage.Remove(player);
                return null;
            }
            if (attacker != null)
            {
                attacker.ChatMessage($"Нельзя наносить урон еще: {(PlayerToIgnoreDamage[player] - DateTime.Now).TotalSeconds})");
            }
            return false;
        }

        if (attacker != null)
        {
            if (PlayerToIgnoreDamage.ContainsKey(attacker))
            {
                if (PlayerToIgnoreDamage[attacker] < DateTime.Now)
                {
                    PlayerToIgnoreDamage.Remove(attacker);
                    return null;
                }
                if (attacker != null)
                {
                    attacker.ChatMessage($"Нельзя наносить урон еще: {(PlayerToIgnoreDamage[attacker] - DateTime.Now).TotalSeconds})");
                }
                return false;
            }
        }
        return null;
    }
    private void OnPlayerConnected(BasePlayer player)
    {
        if (VoteBanList.TryGetValue(player, out var userBans))
        {
            if (userBans.TimeToUnban > DateTime.Now)
            {
                player.Kick($"Вы сможете подключиться через {(userBans.TimeToUnban - DateTime.Now).TotalSeconds} секунд");
                return;
            }
            else
            {
                VoteBanList.Remove(player);
                
                SendUserMessage($"Игрок {player.displayName} побывавший во временном бане, снова на сервере");
            }
        }
        _gameController.AddPlayer(player);
    }

    private void OnPlayerDisconnected(BasePlayer player, string reason)
    {
        _gameController.RemovePlayer(player);
    }

    private void OnPlayerDeath(BasePlayer player, HitInfo info)
    {
        if(info == null || info.InitiatorPlayer == null)return;
        var attacker = info.InitiatorPlayer;
        if(attacker.userID == player.userID)return;
        _gameController.GiveKill(attacker);
        attacker.Heal(_config.HealAmount);

    }
    private void OnPlayerRespawned(BasePlayer player)
    {
        if(!PlayerToIgnoreDamage.ContainsKey(player))
            PlayerToIgnoreDamage.Add(player, DateTime.MinValue);

        player.metabolism.calories.value = player.metabolism.calories.max;
        player.metabolism.hydration.value = player.metabolism.hydration.max;
        PlayerToIgnoreDamage[player] = DateTime.Now.AddSeconds(_config.TimeToSave);
        _gameController.GiveWeaponAndWear(player);
        
        
    }
    private void OnEntitySpawned(PlayerCorpse entity)
    {
        entity.Kill();
    }
    private void OnEntitySpawned(DroppedItemContainer entity)
    {
        entity.Kill();
    }
    private object CanDropActiveItem( BasePlayer instance )
    {
        return false;
    }
    private void Unload()
    {
        UnityEngine.Object.Destroy(_gameController);
        _ = null;
    }

    #endregion


    #region Function
   [ChatCommand("voteban")]
    private void CmdChatvoteban(BasePlayer player, string command, string[] args)
    {
        if (args.Length < 1)
        {
            player.ChatMessage($"/voteban NickName");
            return;
        }
        if (!TryFindPlayer(args[0], out var targetPlayer))
        {
            if (targetPlayer != null)
            {
                player.ChatMessage("Было найдено несколько игроков с таким ником");
            }
            else
            {
                player.ChatMessage("Игрок не найден");
            }
            return;
        }
        if(!VoteBanList.ContainsKey(targetPlayer))
            VoteBanList.Add(targetPlayer, new VoteBanSettings());

        if (VoteBanList[targetPlayer].UserToSendVote.Contains(player.UserIDString))
        {
            player.ChatMessage("Вы уже отдали голос за этого игрока");
            return;
        }
        VoteBanList[targetPlayer].VoteCount++;
        VoteBanList[targetPlayer].UserToSendVote.Add(player.UserIDString);
        if (VoteBanList[targetPlayer].VoteCount >= _config.BanAmount)
        {
            targetPlayer.Kick($"Игроки проголосовали за временный бан на {_._config.TimeToBan} секунд");
            VoteBanList[targetPlayer].TimeToUnban = DateTime.Now.AddSeconds(_config.TimeToBan);

           SendUserMessage($"Игрок {targetPlayer.displayName} был выгнан на голосовании");
        }
        else
        {
            SendUserMessage($"Игрок {player.displayName} голосует за бан игрока {targetPlayer.displayName} [{VoteBanList[targetPlayer].VoteCount}/{_config.BanAmount}]");
        }
    }

    
    private void SendUserMessage(string msg)
    {
        foreach (var check in BasePlayer.activePlayerList)
        {
            check.ChatMessage(msg);
        }
    }
    private bool TryFindPlayer(string NameOrSteamID, out BasePlayer player)
    {
        var amount = 0;
        player = null;
        foreach (var check in BasePlayer.activePlayerList)
        {
            if (check.displayName.ToLower().Contains(NameOrSteamID.ToLower()) ||
                check.UserIDString.Contains(NameOrSteamID))
            {
                amount++;
                player = check;
            }
        }

        if (amount == 0)
        {
            return false;
        }
        return true;
    }
    private void DestroyAll<T>()
    {
        var objects = UnityEngine.Object.FindObjectsOfType(typeof(T));
        objects?.ToList().ForEach(UnityEngine.Object.Destroy);
    }

  

    private class GameController : FacepunchBehaviour
    {
        private Dictionary<BasePlayer, PlayerStat> _players = new();

        private void Awake()
        {
            StartGame();
        }

        public void AddPlayer(BasePlayer player)
        {
            if(_players.ContainsKey(player)) return;
            _players.Add(player, new PlayerStat()
            {
                Kills = 0,
                WeaponIndex = 0
            });
            GiveWeaponAndWear(player);
        }
        
        
        public void GiveWeaponAndWear(BasePlayer player)
        {
            player.inventory.Strip();
            player.Heal(200);

            var settings = _players[player];
            var weaponSettings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == settings.WeaponIndex);
            if (weaponSettings == null)
            {
                EndGame(player);
                return;
            }

            var weapon = ItemManager.CreateByName(weaponSettings.WeaponShortName);
            weapon.MoveToContainer(player.inventory.containerBelt);
            player.svActiveItemID = weapon.uid;
            player.SendNetworkUpdateImmediate();
           
            var projectile = weapon.GetHeldEntity() as BaseProjectile;
            if (projectile != null)
            {
                var ammo = ItemManager.CreateByName(projectile.primaryMagazine.ammoType.shortname, 600);
                player.GiveItem(ammo);
                projectile.primaryMagazine.contents = projectile.primaryMagazine.capacity;
                projectile.SendNetworkUpdateImmediate();
            }

            foreach (var check in weaponSettings.ModuleList)
            {
                var item = ItemManager.CreateByName(check);
                if(item != null && weapon.contents != null)
                    item.MoveToContainer(weapon.contents);
            }
            foreach (var check in weaponSettings.WearList)
            {
                var wear = ItemManager.CreateByName(check);
                wear.MoveToContainer(player.inventory.containerWear);
            }
            
            player.inventory.containerWear.SetLocked(true);
            player.inventory.containerBelt.SetLocked(true);
            player.inventory.containerMain.SetLocked(true);
        }
       
        public void EndGame(BasePlayer winner = null)
        {
            if (winner != null)
            {
                _.SendUserMessage($"{winner?.displayName} победил в текущей игре!\nСледующая игра начнется через {_._config.TimeToRestart}");
            }
            Invoke(StartGame,_._config.TimeToRestart);
            
        }
        public void GiveKill(BasePlayer player)
        {
            if(!_players.ContainsKey(player))return;
          
            var stat = _players[player];
            stat.Kills++;
            var settings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == stat.WeaponIndex);
            if (stat.Kills >= settings.NeedKills)
            {
                stat.WeaponIndex++;
                stat.Kills = 0;
                ShowTable();
                GiveWeaponAndWear(player);
            }

            UpdateUserInfo(player);
        }

       
      
        
        public void RemovePlayer(BasePlayer player)
        {
            if (!_players.ContainsKey(player)) return;
            _players.Remove(player);
        }
        private void StartGame()
        {
            _players.Clear();
            foreach (var check in BasePlayer.activePlayerList)
            {
                AddPlayer(check);
            }
            _.SendUserMessage("Началась новая игра!");
            ShowTable();
        }

        private void ShowTable()
        {
            var container = new CuiElementContainer();
            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "0 0 0 0.6941177" },
                RectTransform =
                    { AnchorMin = "1 1", AnchorMax = "1 1", OffsetMin = "-161.557 -169.842", OffsetMax = "-6.4 -9.8" }
            }, "Overlay", "Panel_3977");

            container.Add(new CuiElement
            {
                Name = "Label_163",
                Parent = "Panel_3977",
                Components =
                {
                    new CuiTextComponent
                    {
                        Text = "GUN GAME", Font = "robotocondensed-bold.ttf", FontSize = 20,
                        Align = TextAnchor.MiddleCenter, Color = "1 1 1 1"
                    },
                    
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0 1", AnchorMax = "0 1", OffsetMin = "0.42 -31.762", OffsetMax = "155.58 -0.368"
                    }
                }
            });

            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "1 1 1 1" },
                RectTransform =
                {
                    AnchorMin = "0 1", AnchorMax = "0 1", OffsetMin = "11.286 -33.278", OffsetMax = "150.481 -31.762"
                }
            }, "Panel_3977", "Panel_7687");

            container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "1 1 1 1" },
                RectTransform =
                    { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = "11.284 17.792", OffsetMax = "150.479 19.308" }
            }, "Panel_3977", "Panel_7687 (1)");
            var posy = -53.815;
            var height = -36.385 - posy;
            int i = 1;
            int maxLevel =  _._config.WeaponList.Count ;
            foreach (var check in _players.OrderByDescending(p => p.Value.WeaponIndex))
            {
                container.Add(new CuiPanel
                {
                    CursorEnabled = false,
                    Image = { Color = "1 1 1 0" },
                    RectTransform =
                    {
                        AnchorMin = "0 1", AnchorMax = "0 1", OffsetMin = $"11.289 {posy}",
                        OffsetMax = $"150.479 {posy + height}"
                    }
                }, "Panel_3977", "Panel_8136");

                container.Add(new CuiElement
                {
                    Name = "Label_5483",
                    Parent = "Panel_8136",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = $"{i}.", Font = "robotocondensed-bold.ttf", FontSize = 12,
                            Align = TextAnchor.MiddleCenter,
                            Color = "1 1 1 1"
                        },
                        
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-69.595 -8.715",
                            OffsetMax = "-52.912 8.715"
                        }
                    }
                });
                i++;
                container.Add(new CuiElement
                {
                    Name = "Label_1789",
                    Parent = "Panel_8136",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = $"{check.Key.displayName}", Font = "robotocondensed-bold.ttf", FontSize = 12,
                            Align = TextAnchor.MiddleCenter,
                            Color = "1 1 1 1"
                        },
                        
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-52.912 -8.715",
                            OffsetMax = "45.045 8.715"
                        }
                    }
                });

                container.Add(new CuiElement
                {
                    Name = "Label_1789 (1)",
                    Parent = "Panel_8136",
                    Components =
                    {
                        new CuiTextComponent
                        {
                            Text = $"{check.Value.WeaponIndex + 1}/{maxLevel}", Font = "robotocondensed-bold.ttf", FontSize = 12,
                            Align = TextAnchor.MiddleCenter,
                            Color = "1 1 1 1"
                        },
                        
                        new CuiRectTransformComponent
                        {
                            AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "45.045 -8.715",
                            OffsetMax = "69.595 8.715"
                        }
                    }
                });
                posy -= height + 2;
            }

            foreach (var check in BasePlayer.activePlayerList)
            {
                CuiHelper.DestroyUi(check, "Panel_3977");
                CuiHelper.AddUi(check, container);
                UpdateUserInfo(check);
            }
        }

        private void UpdateUserInfo(BasePlayer player)
        {
            var container = new CuiElementContainer();
                container.Add(new CuiPanel
            {
                CursorEnabled = false,
                Image = { Color = "1 1 1 0" },
                RectTransform =
                    { AnchorMin = "0 0", AnchorMax = "0 0", OffsetMin = "13.505 0.365", OffsetMax = "152.695 17.795" }
            }, "Panel_3977", "Panel_8136 (1)");
            container.Add(new CuiElement
            {
                Name = "Label_5483",
                Parent = "Panel_8136 (1)",
                Components =
                {
                    new CuiTextComponent
                    {
                        Text = $"{GetPlayerPlacement(player)}.", Font = "robotocondensed-bold.ttf", FontSize = 12, Align = TextAnchor.MiddleCenter,
                        Color = "1 1 1 1"
                    },
                    
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-69.595 -8.715",
                        OffsetMax = "-52.912 8.715"
                    }
                }
            });
            container.Add(new CuiElement
            {
                Name = "Label_1789",
                Parent = "Panel_8136 (1)",
                Components =
                {
                    new CuiTextComponent
                    {
                        Text = $"{player.displayName}", Font = "robotocondensed-bold.ttf", FontSize = 12, Align = TextAnchor.MiddleCenter,
                        Color = "1 1 1 1"
                    },
                    
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-52.912 -8.715",
                        OffsetMax = "45.045 8.715"
                    }
                }
            });
            var settings = _players[player];
            container.Add(new CuiElement
            {
                Name = "Label_1789 (1)",
                Parent = "Panel_8136 (1)",
                Components =
                {
                    new CuiTextComponent
                    {
                        Text = $"{settings.WeaponIndex + 1 }/{_._config.WeaponList.Count}", Font = "robotocondensed-bold.ttf", FontSize = 12, Align = TextAnchor.MiddleCenter,
                        Color = "1 1 1 1"
                    },
                    
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "45.045 -8.715",
                        OffsetMax = "69.595 8.715"
                    }
                }
            });
            var levelSettings = _._config.WeaponList.FirstOrDefault(p => p.IndexWeapon == settings.WeaponIndex);
            container.Add(new CuiElement
            {
                Name = "Label_1789 (1)",
                Parent = "Panel_8136 (1)",
                Components =
                {
                    new CuiTextComponent
                    {
                        Text = $"Сделано фрагов: {settings.Kills}/{levelSettings.NeedKills}", Font = "robotocondensed-bold.ttf", FontSize = 12, Align = TextAnchor.MiddleRight,
                        Color = "1 1 1 1"
                    },
                    
                    new CuiRectTransformComponent
                    {
                        AnchorMin = "0.5 0.5", AnchorMax = "0.5 0.5", OffsetMin = "-35.045 -29.715",
                        OffsetMax = "69.595 -9.715"
                    }
                }
            });
            CuiHelper.DestroyUi(player, "Panel_8136 (1)");
            CuiHelper.AddUi(player, container);
        }
        private int GetPlayerPlacement(BasePlayer player)
        {
            int i = 1;
            foreach (var check in _players.OrderByDescending(p=> p.Value.WeaponIndex))
            {
                if (check.Key.userID == player.userID)
                {
                    return i;
                }

                i++;
            }

            return 0;
        }
        private class PlayerStat
        {
            public int Kills;
            public int WeaponIndex;
        }
    }

    #endregion
}