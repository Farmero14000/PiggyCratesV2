<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyCrates\tasks;

use DaPigGuy\PiggyCrates\crates\Crate;
use DaPigGuy\PiggyCrates\crates\CrateItem;
use DaPigGuy\PiggyCrates\PiggyCrates;
use DaPigGuy\PiggyCrates\tiles\CrateTile;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\PlayerNetworkSessionAdapter;

class RouletteTask extends Task
{
    const INVENTORY_ROW_COUNT = 9;

    private Player $player;
    private Crate $crate;
    private CrateTile $tile;
    private InvMenu $menu;

    private int $currentTick = 0;
    private bool $showReward = false;
    private int $itemsLeft;

    /** @var CrateItem[] */
    private array $lastRewards = [];

    public function __construct(CrateTile $tile)
    {
        /** @var Player $player */
        $player = $tile->getCurrentPlayer();
        $this->player = $player;

        /** @var Crate $crate */
        $crate = $tile->getCrateType();
        $this->crate = $crate;

        $this->tile = $tile;

        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $this->menu->setName(PiggyCrates::getInstance()->getMessage("crates.menu-name", ["{CRATE}" => $crate->getName()]));
        $this->menu->getInventory()->setContents([
            4 => ($endRod = VanillaBlocks::END_ROD()->asItem()->setCustomName(TextFormat::ITALIC)),
            22 => $endRod
        ]);
        $this->menu->setListener(InvMenu::readonly());
        $this->menu->send($player);

        $this->itemsLeft = $crate->getDropCount();
    }
    
    private function PlaySound(Player $player, $sound): void {
		$position = $player->getPosition();
		$player->getNetworkSession()->sendDataPacket(PlaySoundPacket::create(
			soundName: $sound, 
			x: $position->getX(),
			y: $position->getY(),
			z: $position->getZ(),
			volume: 1.0,
			pitch: 1.0
		));
	}

    public function onRun(): void
    {
        if (!$this->player->isOnline()) {
            $this->tile->closeCrate();
            if (($handler = $this->getHandler()) !== null) $handler->cancel();
            return;
        }
        $this->currentTick++;
        $speed = PiggyCrates::getInstance()->getConfig()->getNested("crates.roulette.speed");
        $safeSpeed = max($speed, 1);
        $duration = PiggyCrates::getInstance()->getConfig()->getNested("crates.roulette.duration");
        $safeDuration = (($duration / $safeSpeed) >= 5.5) ? $duration : (5.5 * $safeSpeed);
        if ($this->currentTick >= $safeDuration) {
            if (!$this->showReward) {
                $this->showReward = true;
            } elseif ($this->currentTick - $safeDuration > 20) {
                $this->itemsLeft--;
                $reward = $this->lastRewards[floor(self::INVENTORY_ROW_COUNT / 2)];
                if ($reward->getType() === "item") $this->player->getInventory()->addItem($reward->getItem());
                $server = $this->player->getServer();
                foreach ($reward->getCommands() as $command) {
                    $server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), str_replace("{PLAYER}", $this->player->getName(), $command));
                }
                if ($this->itemsLeft === 0) {
                    foreach ($this->crate->getCommands() as $command) {
                        $server->dispatchCommand(new ConsoleCommandSender($server, $server->getLanguage()), str_replace("{PLAYER}", $this->player->getName(), $command));
                    }
                    $this->player->removeCurrentWindow();
                    $sound = PiggyCrates::getInstance()->getConfig()->getNested("crates.getsound");
                    $this->PlaySound($this->player, $sound);
                    $this->tile->closeCrate();
                    if (($handler = $this->getHandler()) !== null) $handler->cancel();
                } else {
                    $this->currentTick = 0;
                    $this->showReward = false;
                }
            }
            return;
        }

        if ($this->currentTick % $safeSpeed === 0) {
            $this->lastRewards[self::INVENTORY_ROW_COUNT] = $this->crate->getDrop(1)[0];
            /**
             * @var int $slot
             * @var CrateItem $lastReward
             */
            foreach ($this->lastRewards as $slot => $lastReward) {
                if ($slot !== 0) {
                    $this->lastRewards[$slot - 1] = $lastReward;
                    $this->menu->getInventory()->setItem($slot + self::INVENTORY_ROW_COUNT - 1, $lastReward->getItem());
                    $sound = PiggyCrates::getInstance()->getConfig()->getNested("crates.rollsound");
                    $this->PlaySound($this->player, $sound);
                }
            }
        }
    }
}