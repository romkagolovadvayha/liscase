using Oxide.Game.Rust;
using Rust;
using UnityEngine;
using System.Collections.Generic;

namespace Oxide.Plugins
{
    [Info("RemoverToolEntity", "GPT-5 Codex", "1.0.0")]
    [Description("Удаляет строительные блоки правой кнопкой мыши киянкой при авторизации в шкафу.")]
    public class RemoverToolEntity : RustPlugin
    {
        private const string HammerShortName = "hammer";
        private const float MaxRemoveDistance = 5f;

        protected override void LoadDefaultMessages()
        {
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["helptext"] = "Нажмите колесико мыши для удаления блока"
            }, this, "ru");
            
            lang.RegisterMessages(new Dictionary<string, string>
            {
                ["helptext"] = "Press mouse wheel to remove block"
            }, this, "en");
        }

        private void OnActiveItemChanged(BasePlayer player, Item oldItem, Item newItem)
        {
            if (player == null) return;

            if (newItem != null && newItem.info != null && newItem.info.shortname == HammerShortName)
            {
                // Показываем сообщение только если игрок авторизован в шкафу
                if (IsAuthorizedInCupboard(player))
                {
                    SendGameTip(player, lang.GetMessage("helptext", this, player.UserIDString));
                }
            }
        }

        private bool IsAuthorizedInCupboard(BasePlayer player)
        {
            if (player == null) return false;

            // Проверяем, находится ли игрок в зоне шкафа и авторизован ли он
            return player.IsBuildingAuthed();
        }

        private void OnPlayerInput(BasePlayer player, InputState input)
        {
            if (player == null || input == null || !input.WasJustPressed(BUTTON.FIRE_THIRD))
            {
                return;
            }

            var activeItem = player.GetActiveItem();
            if (activeItem == null || activeItem.info == null || activeItem.info.shortname != HammerShortName)
            {
                return;
            }

            RaycastHit hit;
            if (!Physics.Raycast(player.eyes.HeadRay(), out hit, MaxRemoveDistance, Layers.Mask.Construction | Layers.Mask.Deployed))
            {
                return;
            }

            var block = hit.GetEntity() as BuildingBlock;
            if (block == null || block.IsDestroyed)
            {
                return;
            }

            if (!IsAuthorized(player, block))
            {
                return;
            }

            RemoveBlock(block);
        }

        private bool IsAuthorized(BasePlayer player, BaseEntity entity)
        {
            if (player == null || entity == null)
            {
                return false;
            }

            return player.IsBuildingAuthed(entity.WorldSpaceBounds());
        }

        private void RemoveBlock(BuildingBlock block)
        {
            if (block == null || block.IsDestroyed)
            {
                return;
            }

            block.Kill(BaseNetworkable.DestroyMode.Gib);
        }

        private static void SendGameTip(BasePlayer player, string message)
        {
            if (player != null)
                player.ShowToast(GameTip.Styles.Blue_Normal, message, true);
        }
    }
}

