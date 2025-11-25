using System;
using System.Collections.Generic;
using System.Linq;
using Newtonsoft.Json;
using Oxide.Core;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("DisableVehicleDecay", "Auto", "1.0.0")]
    [Description("Отключает ломание (decay) всех транспортных средств на сервере")]
    public class DisableVehicleDecay : RustPlugin
    {
        private Configuration _config;
        private HashSet<BaseEntity> _protectedVehicles = new HashSet<BaseEntity>();

        #region Config

        private class Configuration
        {
            [JsonProperty(PropertyName = "Включить плагин")]
            public bool Enabled = true;

            [JsonProperty(PropertyName = "Отключить decay для вертолетов")]
            public bool DisableHelicopterDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для лодок")]
            public bool DisableBoatDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для машин")]
            public bool DisableCarDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для велосипедов")]
            public bool DisableBikeDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для снегоходов")]
            public bool DisableSnowmobileDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для подводных лодок")]
            public bool DisableSubmarineDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для осадных орудий")]
            public bool DisableSiegeWeaponDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для воздушных шаров")]
            public bool DisableHotAirBalloonDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для лошадей")]
            public bool DisableHorseDecay = true;

            [JsonProperty(PropertyName = "Отключить decay для саней")]
            public bool DisableSledDecay = true;

            [JsonProperty(PropertyName = "Логировать защиту транспорта")]
            public bool LogProtection = true;
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
                PrintError("Конфигурация повреждена. Загружены значения по умолчанию.");
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

        #region Oxide Hooks

        private void OnServerInitialized()
        {
            if (!_config.Enabled)
            {
                PrintWarning("[DisableVehicleDecay] Плагин отключен в конфигурации.");
                return;
            }

            // Регистрация прав
            permission.RegisterPermission("disablevehicledecay.admin", this);

            // Защищаем все существующие транспортные средства
            ProtectAllVehicles();

            PrintWarning($"[DisableVehicleDecay] Плагин загружен. Защищено транспортных средств: {_protectedVehicles.Count}");
        }

        private void Unload()
        {
            // Восстанавливаем decay для всех транспортных средств
            foreach (var vehicle in _protectedVehicles.ToList())
            {
                if (vehicle != null && !vehicle.IsDestroyed)
                {
                    RestoreDecay(vehicle);
                }
            }
            _protectedVehicles.Clear();
        }

        private void OnEntitySpawned(BaseVehicle entity)
        {
            if (!_config.Enabled) return;
            NextTick(() => ProtectVehicle(entity));
        }

        private void OnEntitySpawned(HotAirBalloon entity)
        {
            if (!_config.Enabled) return;
            NextTick(() => ProtectVehicle(entity));
        }

        #endregion

        #region Vehicle Protection

        private void ProtectAllVehicles()
        {
            int protectedCount = 0;

            foreach (var networkable in BaseNetworkable.serverEntities)
            {
                var entity = networkable as BaseEntity;
                if (entity == null) continue;

                if (IsVehicle(entity) && ShouldProtectVehicle(entity))
                {
                    ProtectVehicle(entity);
                    protectedCount++;
                }
            }

            if (_config.LogProtection && protectedCount > 0)
            {
                Puts($"[DisableVehicleDecay] Защищено транспортных средств: {protectedCount}");
            }
        }

        private void ProtectVehicle(BaseEntity vehicle)
        {
            if (vehicle == null || vehicle.IsDestroyed) return;
            if (!IsVehicle(vehicle)) return;
            if (!ShouldProtectVehicle(vehicle)) return;
            if (_protectedVehicles.Contains(vehicle)) return;

            // Отменяем vanilla decay методы
            CancelVanillaDecay(vehicle);

            // Устанавливаем иммунитет к decay для ModularCar
            if (vehicle is ModularCar car)
            {
                car.immuneToDecay = true;
            }

            _protectedVehicles.Add(vehicle);

            if (_config.LogProtection)
            {
                var vehicleType = GetVehicleType(vehicle);
                var ownerId = vehicle.OwnerID;
                Puts($"[DisableVehicleDecay] Защищен транспорт: {vehicleType} (Owner: {ownerId})");
            }
        }

        private void RestoreDecay(BaseEntity vehicle)
        {
            if (vehicle == null || vehicle.IsDestroyed) return;

            // Восстанавливаем иммунитет для ModularCar
            if (vehicle is ModularCar car)
            {
                car.immuneToDecay = false;
            }

            _protectedVehicles.Remove(vehicle);
        }

        private void CancelVanillaDecay(BaseEntity vehicle)
        {
            if (vehicle == null || vehicle.IsDestroyed) return;

            // Отменяем различные методы decay в зависимости от типа транспорта
            if (vehicle is PlayerHelicopter heli)
            {
                vehicle.CancelInvoke(heli.DecayTick);
            }
            else if (vehicle is Minicopter mini)
            {
                vehicle.CancelInvoke(mini.DecayTick);
            }
            else if (vehicle is ScrapTransportHelicopter scrapHeli)
            {
                vehicle.CancelInvoke(scrapHeli.DecayTick);
            }
            else if (vehicle is AttackHelicopter attackHeli)
            {
                vehicle.CancelInvoke(attackHeli.DecayTick);
            }
            else if (vehicle is MotorRowboat rowboat)
            {
                vehicle.CancelInvoke(rowboat.BoatDecay);
            }
            else if (vehicle is RHIB rhib)
            {
                vehicle.CancelInvoke(rhib.BoatDecay);
            }
            else if (vehicle is Tugboat tugboat)
            {
                vehicle.CancelInvoke(tugboat.BoatDecay);
            }
            else if (vehicle is Kayak kayak)
            {
                vehicle.CancelInvoke(kayak.BoatDecay);
            }
            else if (vehicle is ModularCar car)
            {
                vehicle.CancelInvoke(car.DecayTick);
            }
            else if (vehicle is Bike bike)
            {
                vehicle.CancelInvoke(bike.BikeDecay);
            }
            else if (vehicle is Snowmobile snowmobile)
            {
                vehicle.CancelInvoke(snowmobile.SnowmobileDecay);
            }
            else if (vehicle is BaseSubmarine submarine)
            {
                vehicle.CancelInvoke(submarine.SubmarineDecay);
            }
            else if (vehicle is BaseSiegeWeapon siegeWeapon)
            {
                vehicle.CancelInvoke(siegeWeapon.DecayTick);
            }
            else if (vehicle is HotAirBalloon balloon)
            {
                vehicle.CancelInvoke(balloon.DecayTick);
            }
            else if (vehicle is RidableHorse horse)
            {
                vehicle.CancelInvoke(horse.HorseDecay);
            }
            else if (vehicle is Sled sled)
            {
                vehicle.CancelInvoke(sled.DecayOverTime);
            }
        }

        private bool IsVehicle(BaseEntity entity)
        {
            return entity is BaseVehicle 
                || entity is HotAirBalloon
                || entity is RidableHorse
                || entity is Sled;
        }

        private bool ShouldProtectVehicle(BaseEntity vehicle)
        {
            if (vehicle == null) return false;

            if (vehicle is PlayerHelicopter || vehicle is Minicopter || 
                vehicle is ScrapTransportHelicopter || vehicle is AttackHelicopter)
            {
                return _config.DisableHelicopterDecay;
            }

            if (vehicle is MotorRowboat || vehicle is RHIB || 
                vehicle is Tugboat || vehicle is Kayak)
            {
                return _config.DisableBoatDecay;
            }

            if (vehicle is ModularCar)
            {
                return _config.DisableCarDecay;
            }

            if (vehicle is Bike)
            {
                return _config.DisableBikeDecay;
            }

            if (vehicle is Snowmobile)
            {
                return _config.DisableSnowmobileDecay;
            }

            if (vehicle is BaseSubmarine)
            {
                return _config.DisableSubmarineDecay;
            }

            if (vehicle is BaseSiegeWeapon)
            {
                return _config.DisableSiegeWeaponDecay;
            }

            if (vehicle is HotAirBalloon)
            {
                return _config.DisableHotAirBalloonDecay;
            }

            if (vehicle is RidableHorse)
            {
                return _config.DisableHorseDecay;
            }

            if (vehicle is Sled)
            {
                return _config.DisableSledDecay;
            }

            return false;
        }

        private string GetVehicleType(BaseEntity vehicle)
        {
            if (vehicle == null) return "Unknown";

            if (vehicle is PlayerHelicopter) return "PlayerHelicopter";
            if (vehicle is Minicopter) return "Minicopter";
            if (vehicle is ScrapTransportHelicopter) return "ScrapTransportHelicopter";
            if (vehicle is AttackHelicopter) return "AttackHelicopter";
            if (vehicle is MotorRowboat) return "Rowboat";
            if (vehicle is RHIB) return "RHIB";
            if (vehicle is Tugboat) return "Tugboat";
            if (vehicle is Kayak) return "Kayak";
            if (vehicle is ModularCar) return "ModularCar";
            if (vehicle is Bike) return "Bike";
            if (vehicle is Snowmobile) return "Snowmobile";
            if (vehicle is BaseSubmarine) return "Submarine";
            if (vehicle is BaseSiegeWeapon) return "SiegeWeapon";
            if (vehicle is HotAirBalloon) return "HotAirBalloon";
            if (vehicle is RidableHorse) return "Horse";
            if (vehicle is Sled) return "Sled";

            return vehicle.ShortPrefabName;
        }

        #endregion

        #region Commands

        [ChatCommand("vehicledecay")]
        private void CmdVehicleDecay(BasePlayer player, string command, string[] args)
        {
            if (!permission.UserHasPermission(player.UserIDString, "disablevehicledecay.admin"))
            {
                player.ChatMessage("<color=#ff6b6b>❌</color> У вас нет прав для использования этой команды.");
                return;
            }

            if (args.Length == 0)
            {
                player.ChatMessage("<color=#4ecdc4>Использование:</color>");
                player.ChatMessage("  /vehicledecay protect - Защитить все транспортные средства");
                player.ChatMessage("  /vehicledecay count - Показать количество защищенных транспортных средств");
                player.ChatMessage("  /vehicledecay enable - Включить плагин");
                player.ChatMessage("  /vehicledecay disable - Выключить плагин");
                player.ChatMessage("  /vehicledecay status - Показать статус");
                return;
            }

            switch (args[0].ToLower())
            {
                case "protect":
                    var countBefore = _protectedVehicles.Count;
                    ProtectAllVehicles();
                    var countAfter = _protectedVehicles.Count;
                    player.ChatMessage($"<color=#6BCF7F>✓</color> Защищено транспортных средств: {countAfter} (добавлено: {countAfter - countBefore})");
                    break;

                case "count":
                    var totalVehicles = BaseNetworkable.serverEntities
                        .OfType<BaseEntity>()
                        .Count(IsVehicle);
                    player.ChatMessage($"<color=#4ecdc4>📊</color> Всего транспорта: {totalVehicles} | Защищено: {_protectedVehicles.Count}");
                    break;

                case "enable":
                    _config.Enabled = true;
                    SaveConfig();
                    ProtectAllVehicles();
                    player.ChatMessage("<color=#6BCF7F>✓</color> Плагин включен.");
                    break;

                case "disable":
                    _config.Enabled = false;
                    SaveConfig();
                    player.ChatMessage("<color=#ff6b6b>✗</color> Плагин выключен.");
                    break;

                case "status":
                    var status = _config.Enabled ? "<color=#6BCF7F>Включен</color>" : "<color=#ff6b6b>Выключен</color>";
                    player.ChatMessage($"<color=#4ecdc4>📋</color> Статус плагина: {status}");
                    player.ChatMessage($"  Защищено транспортных средств: {_protectedVehicles.Count}");
                    player.ChatMessage($"  Вертолеты: {(_config.DisableHelicopterDecay ? "Защищены" : "Не защищены")}");
                    player.ChatMessage($"  Лодки: {(_config.DisableBoatDecay ? "Защищены" : "Не защищены")}");
                    player.ChatMessage($"  Машины: {(_config.DisableCarDecay ? "Защищены" : "Не защищены")}");
                    player.ChatMessage($"  Велосипеды: {(_config.DisableBikeDecay ? "Защищены" : "Не защищены")}");
                    break;

                default:
                    player.ChatMessage("<color=#ff6b6b>❌</color> Неизвестная команда. Используйте /vehicledecay для справки.");
                    break;
            }
        }

        [ConsoleCommand("vehicledecay.protect")]
        private void CmdConsoleProtect(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            var countBefore = _protectedVehicles.Count;
            ProtectAllVehicles();
            var countAfter = _protectedVehicles.Count;
            arg.ReplyWith($"[DisableVehicleDecay] Защищено транспортных средств: {countAfter} (добавлено: {countAfter - countBefore})");
        }

        [ConsoleCommand("vehicledecay.count")]
        private void CmdConsoleCount(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            var totalVehicles = BaseNetworkable.serverEntities
                .OfType<BaseEntity>()
                .Count(IsVehicle);
            arg.ReplyWith($"[DisableVehicleDecay] Всего транспорта: {totalVehicles} | Защищено: {_protectedVehicles.Count}");
        }

        [ConsoleCommand("vehicledecay.enable")]
        private void CmdConsoleEnable(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            _config.Enabled = true;
            SaveConfig();
            ProtectAllVehicles();
            arg.ReplyWith("[DisableVehicleDecay] Плагин включен.");
        }

        [ConsoleCommand("vehicledecay.disable")]
        private void CmdConsoleDisable(ConsoleSystem.Arg arg)
        {
            if (arg.Connection != null && !arg.IsAdmin) return;

            _config.Enabled = false;
            SaveConfig();
            arg.ReplyWith("[DisableVehicleDecay] Плагин выключен.");
        }

        #endregion
    }
}

