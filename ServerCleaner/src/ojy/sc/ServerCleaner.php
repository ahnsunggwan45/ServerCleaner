<?php

namespace ojy\sc;

use CoinMarket\entity\MarketItemEntity;
use ojy\area\AreaPlugin;
use ojy\monster\entity\Data;
use pocketmine\command\CommandSender;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ServerCleaner extends PluginBase implements Listener
{


    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        \o\c\c::command("청소", "해당 월드의 엔티티를 모두 제거합니다.", "/청소", [],
            function (CommandSender $sender, string $commandLabel, array $args) {
                if ($sender instanceof Player) {
                    foreach ($sender->level->getEntities() as $e) {
                        if ($e instanceof ItemEntity || $e instanceof Data) {
                            $e->kill();
                        }
                    }
                }
            }, true);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function (int $currentTick): void {
            foreach (Server::getInstance()->getLevels() as $level) {
                foreach ($level->getEntities() as $e) {
                    if ($e instanceof Data) {
                        $e->setDrops([]);
                        $e->kill();
                    }
                }
            }
        }), 20 * 60 * 5, 20 * 60 * 5);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (int $currentTick): void {
            if (count(Server::getInstance()->getOnlinePlayers()) > 210)
                $this->gc();
        }), 150);
    }

    /*public function i(PlayerDropItemEvent $event)
    {
        $p = $event->getPlayer();
        if (!$p->isOp())
            if (count(AreaPlugin::getInstance()->getAreaManager()->getAllArea($p->level->getFolderName())) <= 0) {
                $p->sendPopup("§l§b< §r§7이 월드에서는 아이템을 버릴 수 없습니다! §l§b>\n");
                $event->setCancelled();
            } else {
                $p->sendPopup("§l§b< §r§7버린 아이템은 45초 후 소멸합니다. §l§b>\n");
            }
    }*/

    public function gc()
    {
        $start_time = microtime();

        $memory = memory_get_usage();

        foreach ($this->getServer()->getLevels() as $level) {
            $level->doChunkGarbageCollection();
            $level->unloadChunks(true);
            $level->clearCache(true);
        }

        $this->getServer()->getMemoryManager()->triggerGarbageCollector();

        $mem_freed = number_format(round((($memory - memory_get_usage()) / 1024) / 1024, 2), 2);
        $end_time = microtime();
        $start_sec = explode(" ", $start_time);
        $end_sec = explode(" ", $end_time);
        $rap_micsec = $end_sec[0] - $start_sec[0];
        $rap_sec = $end_sec[1] - $start_sec[1];
        $rap = $rap_sec + $rap_micsec;
        $this->getLogger()->info("memory {$mem_freed}MB freed - {$rap}s");
    }

    /**
     * @param ItemSpawnEvent $event
     * @throws \ReflectionException
     */
    public function onItemSpawn(ItemSpawnEvent $event)
    {
        $item = $event->getEntity();
        if ($item instanceof MarketItemEntity) return;

        $ref = new \ReflectionClass($item);
        $property = $ref->getProperty("age");
        $property->setAccessible(true);
        $property->setValue($item, 5100);
    }
}