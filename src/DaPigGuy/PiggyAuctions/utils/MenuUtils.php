<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions\utils;

use DaPigGuy\PiggyAuctions\auction\Auction;
use DaPigGuy\PiggyAuctions\auction\AuctionBid;
use DaPigGuy\PiggyAuctions\PiggyAuctions;
use jojoe77777\FormAPI\CustomForm;
use muqsit\invmenu\InvMenu;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\nbt\tag\IntTag;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

/**
 * Class MenuUtils
 * @package DaPigGuy\PiggyAuctions\utils
 */
class MenuUtils
{
    /** @var InvMenu[][] */
    private static $personalMenu;
    /** @var Task[] */
    private static $personalTasks;

    const TF_RESET = TextFormat::RESET . TextFormat::GRAY;

    /**
     * @param Player $player
     */
    public static function displayMainMenu(Player $player): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("Auction House");
        $menu->getInventory()->setContents([
            11 => Item::get(Item::GOLD_BLOCK)->setCustomName(TextFormat::RESET . TextFormat::WHITE . "Browse Auctions"),
            13 => Item::get(Item::GOLDEN_CARROT)->setCustomName(TextFormat::RESET . TextFormat::WHITE . "View Bids"),
            15 => Item::get(Item::GOLDEN_HORSE_ARMOR)->setCustomName(TextFormat::RESET . TextFormat::WHITE . "Manage Auctions")
        ]);
        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action) use ($menu): bool {
            switch ($itemClicked->getId()) {
                case Item::GOLD_BLOCK:
                    $player->removeWindow($action->getInventory());
                    self::displayAuctionBrowser($player);
                    break;
                case Item::GOLDEN_CARROT:
                    self::displayBidsPage($player);
                    break;
                case Item::GOLDEN_HORSE_ARMOR:
                    if (count(PiggyAuctions::getInstance()->getAuctionManager()->getAuctionsHeldBy($player)) < 1) {
                        $player->removeWindow($action->getInventory());
                        self::displayAuctionCreator($player);
                        break;
                    }
                    self::displayAuctionManager($player);
                    break;
            }
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Player $player
     * @param int $page
     */
    public static function displayAuctionBrowser(Player $player, int $page = 1): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->setName("Auction Browser");
        self::displayPageAuctions($menu->getInventory(), $page);

        PiggyAuctions::getInstance()->getScheduler()->scheduleRepeatingTask((self::$personalTasks[$player->getName()] = new ClosureTask(function () use ($menu, $page) : void {
            foreach ($menu->getInventory()->getContents() as $slot => $content) {
                if ($content->getNamedTagEntry("AuctionID") !== null) {
                    $auction = PiggyAuctions::getInstance()->getAuctionManager()->getAuction($content->getNamedTagEntry("AuctionID")->getValue());
                    if ($auction === null || $auction->hasExpired()) {
                        self::displayPageAuctions($menu->getInventory(), $page);
                        break;
                    }
                    $lore = $content->getLore();
                    $lore[count($lore) - 1] = self::TF_RESET . "Ends in " . self::formatDuration($auction->getEndDate() - time());
                    $content->setLore($lore);
                    $menu->getInventory()->setItem($slot, $content);
                }
            }
        })), 20);

        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action): bool {
            if ($itemClicked->getNamedTagEntry("AuctionID") !== null) {
                $auction = PiggyAuctions::getInstance()->getAuctionManager()->getAuction($itemClicked->getNamedTagEntry("AuctionID")->getValue());
                $returnPage = $action->getInventory()->getItem($action->getInventory()->getItem(53)->getNamedTagEntry("Page") === null ? 45 : 53)->getNamedTagEntry("Page")->getValue() - 1;
                self::displayItemPage($player, $auction, function (Player $player) use ($returnPage) {
                    self::displayAuctionBrowser($player, $returnPage);
                });
            }
            if ($itemClicked->getId() === Item::ARROW && $itemClicked->getNamedTagEntry("Page") !== null) {
                self::displayPageAuctions($action->getInventory(), $itemClicked->getNamedTagEntry("Page")->getValue());
            }
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Inventory $inventory
     * @param int $page
     * @return Auction[]
     */
    public static function displayPageAuctions(Inventory $inventory, int $page): array
    {
        $inventory->clearAll(false);
        $activeAuctions = PiggyAuctions::getInstance()->getAuctionManager()->getActiveAuctions();
        $displayedAuctions = self::updateDisplayedItems($inventory, $activeAuctions, ($page - 1) * 45, 0, 45);
        if ($page > 1) {
            $previousPage = Item::get(Item::ARROW, 0, 1)->setCustomName("Previous Page\n(" . ($page - 1) . "/" . ceil(count($activeAuctions) / 45) . ")");
            $previousPage->setNamedTagEntry(new IntTag("Page", $page - 1));
            $inventory->setItem(45, $previousPage);
        }
        if ($page < ceil(count($activeAuctions) / 45)) {
            $nextPage = Item::get(Item::ARROW, 0, 1)->setCustomName("Next Page\n(" . ($page + 1) . "/" . ceil(count($activeAuctions) / 45) . ")");
            $nextPage->setNamedTagEntry(new IntTag("Page", $page + 1));
            $inventory->setItem(53, $nextPage);
        }
        return $displayedAuctions;
    }

    /**
     * @param Player $player
     */
    public static function displayBidsPage(Player $player): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("Your Bids");
        PiggyAuctions::getInstance()->getScheduler()->scheduleRepeatingTask((self::$personalTasks[$player->getName()] = new ClosureTask(function () use ($menu, $player): void {
            $auctions = array_filter(array_map(function (AuctionBid $bid): Auction {
                return $bid->getAuction();
            }, PiggyAuctions::getInstance()->getAuctionManager()->getBidsBy($player)), function (Auction $auction) use ($player): bool {
                return count($auction->getUnclaimedBidsHeldBy($player->getName())) > 0;
            });
            self::updateDisplayedItems($menu->getInventory(), $auctions, 0, 10, 7);
        })), 20);
        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action): bool {
            switch ($action->getSlot()) {
                default:
                    $player->removeWindow($action->getInventory());
                    self::displayItemPage($player, PiggyAuctions::getInstance()->getAuctionManager()->getAuction($itemClicked->getNamedTagEntry("AuctionID")->getValue()), function (Player $player) {
                        self::displayBidsPage($player);
                    }, true);
                    break;
            }
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Player $player
     */
    public static function displayAuctionCreator(Player $player): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->setName("Create Auction");
        for ($i = 0; $i < $menu->getInventory()->getSize(); $i++) $menu->getInventory()->setItem($i, Item::get(Item::BLEACH)->setCustomName(" "));
        $menu->getInventory()->setItem(13, Item::get(Item::AIR));
        $menu->getInventory()->setItem(29, Item::get(Item::STAINED_CLAY, 14)->setCustomName("Create Auction"));
        $menu->getInventory()->setItem(31, Item::get(Item::GOLD_INGOT)->setCustomName("Starting Bid: 50"));
        $menu->getInventory()->setItem(33, Item::get(Item::CLOCK)->setCustomName("Duration: 2 Hours"));
        $menu->getInventory()->setItem(49, Item::get(Item::ARROW)->setCustomName("Go Back"));
        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action) use ($menu): bool {
            switch ($action->getSlot()) {
                case 13:
                    $action->getInventory()->setItem(13, $itemClickedWith);
                    $action->getInventory()->setItem(29, $action->getInventory()->getItem(29)->setDamage(Item::AIR ? 14 : 13));
                    return true;
                case 29:
                    if ($itemClicked->getDamage() === 13) {
                        PiggyAuctions::getInstance()->getAuctionManager()->addAuction($player->getName(), $action->getInventory()->getItem(13), time(), time() + ($action->getInventory()->getItem(33)->getNamedTagEntry("Duration") ? $action->getInventory()->getItem(33)->getNamedTagEntry("Duration")->getValue() : 60 * 60 * 2), $action->getInventory()->getItem(31)->getNamedTagEntry("StartingBid") ? $action->getInventory()->getItem(31)->getNamedTagEntry("StartingBid")->getValue() : 50);
                        $action->getInventory()->clear(13);
                        $player->removeWindow($action->getInventory());
                        PiggyAuctions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
                            self::displayAuctionManager($player);
                        }), 5);
                    }
                    break;
                case 31:
                    $item = $action->getInventory()->getItem(13);
                    $action->getInventory()->clear(13);
                    $player->removeWindow($action->getInventory());
                    $form = new CustomForm(function (Player $player, ?array $data = null) use ($menu, $item): void {
                        if ($data !== null && is_numeric($data[0])) {
                            $menu->getInventory()->setItem(13, $item);
                            $menu->send($player);

                            $item = $menu->getInventory()->getItem(31);
                            $item->setNamedTagEntry(new IntTag("StartingBid", (int)$data[0]));
                            $menu->getInventory()->setItem(31, $item->setCustomName("Starting Bid: " . (int)$data[0]));
                        }
                    });
                    $form->setTitle("Create Auction");
                    $form->addInput("Starting Bid");
                    $player->sendForm($form);
                    break;
                case 33:
                    $item = $action->getInventory()->getItem(13);
                    $action->getInventory()->clear(13);
                    $player->removeWindow($action->getInventory());
                    $form = new CustomForm(function (Player $player, ?array $data = null) use ($menu, $item): void {
                        if ($data !== null && is_numeric($data[0])) {
                            $menu->getInventory()->setItem(13, $item);
                            $menu->send($player);

                            $item = $menu->getInventory()->getItem(33);
                            $item->setNamedTagEntry(new IntTag("Duration", (int)$data[0]));
                            $menu->getInventory()->setItem(33, $item);
                        }
                    });
                    $form->setTitle("Create Auction");
                    $form->addInput("Duration (Seconds)");
                    $player->sendForm($form);
                    break;
                case 49:
                    $player->removeWindow($action->getInventory());
                    PiggyAuctions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
                        if (count(PiggyAuctions::getInstance()->getAuctionManager()->getAuctionsHeldBy($player)) < 1) {
                            self::displayMainMenu($player);
                            return;
                        }
                        self::displayAuctionManager($player);
                    }), 5);
                    break;
            }
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Player $player
     */
    public static function displayAuctionManager(Player $player): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("Auction Manager");
        PiggyAuctions::getInstance()->getScheduler()->scheduleRepeatingTask((self::$personalTasks[$player->getName()] = new ClosureTask(function () use ($menu, $player): void {
            $auctions = array_filter(PiggyAuctions::getInstance()->getAuctionManager()->getAuctionsHeldBy($player), function (Auction $auction): bool {
                return !$auction->isClaimed();
            });
            self::updateDisplayedItems($menu->getInventory(), $auctions, 0, 10, 7);
        })), 20);
        $menu->getInventory()->setItem(24, Item::get(Item::GOLDEN_HORSE_ARMOR)->setCustomName("Create Auction"));
        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action) use ($menu): bool {
            switch ($action->getSlot()) {
                case 24:
                    $player->removeWindow($action->getInventory());
                    self::displayAuctionCreator($player);
                    break;
                default:
                    $player->removeWindow($action->getInventory());
                    self::displayItemPage($player, PiggyAuctions::getInstance()->getAuctionManager()->getAuction($itemClicked->getNamedTagEntry("AuctionID")->getValue()), function (Player $player) {
                        self::displayAuctionManager($player);
                    }, true);
                    break;
            }
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Player $player
     * @param string $auctioneer
     */
    public static function displayAuctioneerPage(Player $player, string $auctioneer): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName($auctioneer . "'s Auctions");
        PiggyAuctions::getInstance()->getScheduler()->scheduleRepeatingTask((self::$personalTasks[$player->getName()] = new ClosureTask(function () use ($menu, $auctioneer): void {
            $auctions = PiggyAuctions::getInstance()->getAuctionManager()->getActiveAuctionsHeldBy($auctioneer);
            if (isset(array_values($auctions)[0])) $menu->setName(array_values($auctions)[0]->getAuctioneer() . "'s Auctions");
            self::updateDisplayedItems($menu->getInventory(), $auctions, 0, 10, 7);
        })), 20);
        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action) use ($auctioneer): bool {
            $player->removeWindow($action->getInventory());
            self::displayItemPage($player, PiggyAuctions::getInstance()->getAuctionManager()->getAuction($itemClicked->getNamedTagEntry("AuctionID")->getValue()), function (Player $player) use ($auctioneer) {
                self::displayAuctioneerPage($player, $auctioneer);
            });
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Player $player
     * @param Auction $auction
     * @param callable $callback
     * @param bool $removeWindow
     */
    public static function displayItemPage(Player $player, Auction $auction, callable $callback, bool $removeWindow = false): void
    {
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->setName("Auction View");
        PiggyAuctions::getInstance()->getScheduler()->scheduleRepeatingTask((self::$personalTasks[$player->getName()] = new ClosureTask(function () use ($menu, $auction): void {
            $menu->getInventory()->setItem(13, self::getDisplayItem($auction));
            $menu->getInventory()->setItem(33, Item::get(Item::FILLED_MAP)->setCustomName("Bid History")->setLore(count($auction->getBids()) < 1 ? [self::TF_RESET . "No bids have been placed on this item yet."] : array_merge([self::TF_RESET . "Total Bids: " . count($auction->getBids()), ""], ...array_map(function (AuctionBid $auctionBid): array {
                return ["Bid: " . $auctionBid->getBidAmount(), "By: " . $auctionBid->getBidder()];
            }, array_reverse($auction->getBids())))));
        })), 20);
        $menu->getInventory()->setItem(29, Item::get(Item::POISONOUS_POTATO));
        $menu->getInventory()->setItem(33, Item::get(Item::FILLED_MAP));
        $menu->getInventory()->setItem(49, Item::get(Item::ARROW)->setCustomName("Go Back"));
        $menu->setListener(function (Player $player, Item $itemClicked, Item $itemClickedWith, SlotChangeAction $action) use ($auction, $callback, $removeWindow): bool {
            switch ($action->getSlot()) {
                case 29:
                    if (!$auction->hasExpired()) {
                        if ($auction->getAuctioneer() !== $player->getName()) {
                            $player->removeWindow($action->getInventory());
                            $form = new CustomForm(function (Player $player, ?array $data) use ($auction) {
                                if ($data !== null && is_numeric($data[0])) {
                                    $bid = (int)$data[0];
                                    if (($auction->getTopBid() === null && $bid >= $auction->getStartingBid()) || $bid >= (int)($auction->getTopBid() * 1.15)) {
                                        if ($auction->getTopBid() === null || $auction->getTopBid()->getBidder() !== $player->getName()) {
                                            if (PiggyAuctions::getInstance()->getEconomyProvider()->getMoney($player) >= $bid) {
                                                PiggyAuctions::getInstance()->getEconomyProvider()->takeMoney($player, $bid - ($auction->getTopBidBy($player->getName()) ?? 0));
                                                $auction->addBid(new AuctionBid($auction->getId(), $player->getName(), $bid, time()));
                                            }
                                        }
                                    }
                                }
                            });
                            $form->setTitle("Bid on Item");
                            $form->addInput("Bid Amount", "", (string)($auction->getTopBid() === null ? $auction->getStartingBid() : (int)($auction->getTopBid()->getBidAmount() * 1.15)));
                            $player->sendForm($form);
                        }
                    } else {
                        if ($auction->getAuctioneer() === $player->getName()) {
                            $auction->claim($player);
                        } else if ($auction->getTopBidBy($player->getName())) {
                            $auction->bidderClaim($player);
                        }
                    }
                    break;
                case 49:
                    if ($removeWindow) $player->removeWindow($action->getInventory());
                    PiggyAuctions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($callback, $player): void {
                        ($callback)($player);
                    }), 5);
                    break;
            }
            return false;
        });
        $menu->send($player);
    }

    /**
     * @param Inventory $inventory
     * @param Auction[] $auctions
     * @param int $arrayOffset
     * @param int $offsetSlot
     * @param int $displayCount
     * @param callable|null $sortFunction
     * @return Auction[]
     */
    public static function updateDisplayedItems(Inventory $inventory, array $auctions, int $arrayOffset, int $offsetSlot, int $displayCount, ?callable $sortFunction = null): array
    {
        if ($sortFunction === null) {
            $sortFunction = function (Auction $a, Auction $b): bool {
                return $a->getEndDate() > $b->getEndDate();
            };
        }
        uasort($auctions, $sortFunction);
        foreach (array_slice($auctions, $arrayOffset, $displayCount) as $index => $auction) {
            $inventory->setItem($offsetSlot + $index, self::getDisplayItem($auction));
        }
        return array_slice($auctions, $arrayOffset, $displayCount);
    }

    /**
     * @param Auction $auction
     * @return Item
     */
    public static function getDisplayItem(Auction $auction): Item
    {
        $item = clone $auction->getItem();

        $lore = array_merge($item->getLore(), [
            "",
            self::TF_RESET . "Seller: " . $auction->getAuctioneer(),
            self::TF_RESET . "Bids: " . TextFormat::GREEN . count($auction->getBids()),
            ""
        ]);
        if ($auction->getTopBid() !== null) {
            $lore = array_merge($lore, [
                self::TF_RESET . "Top Bid: " . TextFormat::GOLD . $auction->getTopBid()->getBidAmount(),
                self::TF_RESET . "Bidder: " . TextFormat::GOLD . $auction->getTopBid()->getBidder(),
            ]);
        } else {
            $lore[] = self::TF_RESET . "Starting Bid: " . TextFormat::GOLD . $auction->getStartingBid();
        }
        $lore = array_merge($lore, [
            "",
            self::TF_RESET . "Ends in " . self::formatDuration($auction->getEndDate() - time())
        ]);

        $item->setNamedTagEntry(new IntTag("AuctionID", $auction->getId()));
        return $item->setLore($lore);
    }

    /**
     * @param int $duration
     * @return string
     */
    public static function formatDuration(int $duration): string
    {
        $days = floor($duration / 86400);
        $hours = floor($duration / 3600 % 24);
        $minutes = floor($duration / 60 % 60);
        $seconds = floor($duration % 60);

        if ($days >= 1) {
            $dateString = $days . "d";
        } elseif ($hours > 6) {
            $dateString = $hours . "h";
        } elseif ($minutes >= 1) {
            $dateString = ($hours > 0 ? $hours . "h" : "") . $minutes . "m" . ($seconds == 0 ? "" : $seconds . "s");
        } else {
            $dateString = $seconds . "s";
        }

        return $dateString;
    }
}