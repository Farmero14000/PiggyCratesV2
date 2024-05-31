<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyCrates;

use DaPigGuy\PiggyCrates\tiles\CrateTile;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\ShulkerBox as SBX;
use pocketmine\block\tile\{Chest, Barrel, ShulkerBox, Beacon, EnderChest, EnchantTable};
use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

class EventListener implements Listener
{
    public function __construct(private readonly PiggyCrates $plugin)
    {
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $item = $player->getInventory()->getItemInHand();
        $blocktypeid = $block->getTypeId();

        if ($blocktypeid === BlockTypeIds::CHEST ||
           $blocktypeid === BlockTypeIds::BARREL ||
           $blocktypeid === BlockTypeIds::ENCHANTING_TABLE ||
           $blocktypeid === BlockTypeIds::ENDER_CHEST ||
           $blocktypeid === BlockTypeIds::BEACON || 
           $block instanceof SBX) {
            $tile = $world->getTile($block->getPosition());
            if ($tile instanceof CrateTile) {
                if ($tile->getCrateType() === null) {
                    $player->sendTip($this->plugin->getMessage("crates.error.invalid-crate"));
                } elseif ($tile->getCrateType()->isValidKey($item)) {
                    $tile->openCrate($player, $item);
                } elseif ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
                    $tile->previewCrate($player);
                }
                $event->cancel();
                return;
            }
            if ($tile instanceof Chest || 
            $tile instanceof Barrel || 
            $tile instanceof EnchantTable || 
            $tile instanceof EnderChest ||
            $tile instanceof ShulkerBox || 
            $tile instanceof Beacon) {
                if (($crate = $this->plugin->getCrateToCreate($player)) !== null) {
                    $newTile = new CrateTile($world, $block->getPosition());
                    $newTile->setCrateType($crate);
                    $tile->close();
                    $world->addTile($newTile);
                    $player->sendMessage($this->plugin->getMessage("crates.success.crate-created", ["{CRATE}" => $crate->getName()]));
                    $this->plugin->setInCrateCreationMode($player, null);
                    $event->cancel();
                    return;
                }
            }
        }
        if ($item->getNamedTag()->getTag("KeyType") !== null) $event->cancel();
    }
}