<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions;

use DaPigGuy\PiggyAuctions\menu\pages\AuctionCreatorMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;

class EventListener implements Listener
{
    public function __construct(private PiggyAuctions $plugin)
    {
    }

    public function onPlayerLogin(PlayerLoginEvent $event): void
    {
        $this->plugin->getStatsManager()->loadStatistics($event->getPlayer());
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $this->plugin->getStatsManager()->unloadStatistics($event->getPlayer());
    }

    /**
     * @priority MONITOR
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        foreach ($transaction->getActions() as $action) {
            if ($action instanceof SlotChangeAction) {
                if ($event->isCancelled()) {
                    if ($player->isOnline()) {
                        $player->getNetworkSession()->getInvManager()?->syncSlot($action->getInventory(), $action->getSlot());
                    }
                } else {
                    $menu = InvMenuHandler::getPlayerManager()->get($player)->getCurrent()?->menu;
                    if ($action->getSlot() === 13 && $menu instanceof AuctionCreatorMenu && $menu->getInventory() === $action->getInventory()) {
                        $menu->setItem($action->getTargetItem());
                    }
                }
            }
        }
    }
}