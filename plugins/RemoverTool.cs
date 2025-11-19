using Oxide.Game.Rust;
using Rust;
using UnityEngine;

namespace Oxide.Plugins
{
    [Info("Remover Tool", "GPT-5 Codex", "1.0.0")]
    [Description("Удаляет строительные блоки правой кнопкой мыши киянкой при авторизации в шкафу.")]
    public class RemoverTool : RustPlugin
    {
        private const string HammerShortName = "hammer";
        private const float MaxRemoveDistance = 5f;

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
    }
}

