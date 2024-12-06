<?php

/* Credits: xXKHaLeD098Xx, VsrStudio
* Discord: https://discord.gg/PMEBQHGy
*/

namespace xXKHaLeD098Xx\WorldPlayerCount;

use pocketmine\player\Player;
use brokiem\snpc\entity\BaseNPC;
use brokiem\snpc\entity\CustomHuman;
use brokiem\snpc\event\SNPCCreationEvent;
use brokiem\snpc\event\SNPCDeletionEvent;
use brokiem\snpc\SimpleNPC;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityEvent;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\WorldManager;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use xXKHaLeD098Xx\WorldPlayerCount\Task\RefreshCount;

class WorldPlayerCount extends PluginBase implements Listener {

    /** @return SimpleNPC|null */
    public function getSlapper(): ?SimpleNPC {
        $plugin = $this->getServer()->getPluginManager()->getPlugin("SimpleNPC");
        return $plugin instanceof SimpleNPC ? $plugin : null;
    }

    public function onEnable(): void {
        $map = $this->getDescription()->getMap();
        $ver = $this->getDescription()->getVersion();

        if (isset($map["author"])) {
            if ($map["author"] !== "xXKHaLeD098Xx, VsrStudio" or $ver !== "3.0-beta") {
                $this->getLogger()->emergency("§cPlugin info has been changed, please give the author the proper credits, set the author to \"xXKHaLeD098Xx, VsrStudio\" and set the version to \"3.0-beta\" if required, or else the server will shutdown on every start-up");
                $this->getServer()->shutdown();
                return;
            }
        } else {
            $this->getLogger()->emergency("§cPlugin info has been changed, please give the author the proper credits, set the author to \"xXKHaLeD098Xx, VsrStudio\" and set the version to \"3.0-beta\" if required, or else the server will shutdown on every start-up");
            $this->getServer()->shutdown();
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->saveResource("config.yml");

        $this->getScheduler()->scheduleRepeatingTask(new RefreshCount($this), (int)$this->getConfig()->get("count-interval") * 20);

        $worlds = $this->getConfig()->get("worlds");
        $worldManager = $this->getServer()->getWorldManager();

        foreach ($worlds as $key => $world) {
            $worldPath = $this->getServer()->getDataPath() . "/worlds/" . $world;

            if (file_exists($worldPath)) {
                if (!$worldManager->isWorldLoaded($world)) {
                    $worldManager->loadWorld($world);
                }
            } else {
                unset($worlds[$key]);
                $this->getConfig()->set("worlds", $worlds);
                $this->getConfig()->save();
            }
        }
    }

    public function slapperCreation(SNPCCreationEvent $ev): void {
        $entity = $ev->getEntity();
        $name = $entity->getNameTag();
        $worldManager = $this->getServer()->getWorldManager();

        if (strpos($name, "\n") !== false) {
            $allLines = explode("\n", $name);

            $pos = strpos($allLines[1], "count ");
            if ($pos !== false) {
                $levelName = str_replace("count ", "", $allLines[1]);
                $worldPath = $this->getServer()->getDataPath() . "worlds/" . $levelName;

                if (is_dir($worldPath)) {
                    if (!$worldManager->isWorldLoaded($levelName)) {
                        $worldManager->loadWorld($levelName);
                    }

                    $namedTag = $entity->getNamedTag();
                    $namedTag->setString("playerCount", $levelName);
                    $entity->setNamedTag($namedTag);

                    $worlds = $this->getConfig()->get("worlds");
                    if (!in_array($levelName, $worlds, true)) {
                        $worlds[] = $levelName;
                        $this->getConfig()->set("worlds", $worlds);
                        $this->getConfig()->save();
                    }
                    return;
                }
            }

            $combinedPos = strpos($allLines[1], "combinedcounts ");
            if ($combinedPos !== false) {
                $symbolPos = strpos($allLines[1], "&");
                if ($symbolPos !== false) {
                    $levelNamesString = str_replace("combinedcounts ", "", $allLines[1]);
                    $levelNamesArray = explode("&", $levelNamesString);

                    if (!in_array("", $levelNamesArray, true)) {
                        $namedTag = $entity->getNamedTag();
                        $namedTag->setString("combinedPlayerCounts", $levelNamesString);
                        $entity->setNamedTag($namedTag);

                        $this->combinedPlayerCounts();
                    }
                }
            }
        }
    }

    public function onSlapperDeletion(SNPCDeletionEvent $event): void {
        $entity = $event->getEntity();
        $namedTag = $entity->getNamedTag();

        if ($namedTag->getTag("playerCount") !== null) {
            $tag = $namedTag->getString("playerCount");
            $namedTag->removeTag("playerCount");
            $entity->setNamedTag($namedTag);

            $worlds = $this->getConfig()->get("worlds", []);
            unset($worlds[array_search($tag, $worlds, true)]);
            $this->getConfig()->set("worlds", $worlds);
            $this->getConfig()->save();
        }

        if ($namedTag->getTag("combinedPlayerCounts") !== null) {
            $tag = $namedTag->getString("combinedPlayerCounts");
            $namedTag->removeTag("combinedPlayerCounts");
            $entity->setNamedTag($namedTag);

            $worlds = $this->getConfig()->get("worlds", []);
            $arrayOfNames = explode("&", $tag);

            foreach ($arrayOfNames as $name) {
                if (in_array($name, $worlds, true)) {
                    unset($worlds[array_search($name, $worlds, true)]);
                }
            }

            $this->getConfig()->set("worlds", $worlds);
            $this->getConfig()->save();
        }
    }

    public function DamageEvent(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if (($entity instanceof BaseNPC || $entity instanceof CustomHuman) and $damager instanceof Player) {
            $slapper = $this->getSlapper();
            if (isset($slapper->hitSessions[$damager->getName()])) {
                $slapperDelete = new SNPCDeletionEvent($event->getEntity());
                $slapperDelete->call();
            }
        }
    }

    public function combinedPlayerCounts(): void {
        $worlds = $this->getServer()->getWorldManager()->getWorlds();
        foreach ($worlds as $world) {
            foreach ($world->getEntities() as $entity) {
                $nbt = $entity->getNamedTag();

                if ($nbt->hasTag("combinedPlayerCounts") && !$nbt->hasTag("playerCount")) {
                    $worldsNames = explode("&", $nbt->getString("combinedPlayerCounts"));

                    foreach ($worldsNames as $name) {
                        if (!file_exists($this->getServer()->getDataPath() . "/worlds/" . $name)) {
                            unset($worldsNames[array_search($name, $worldsNames, true)]);
                            $slapperDelete = new SNPCDeletionEvent($entity);
                            $slapperDelete->call();
                            $entity->close();
                        }
                    }

                    if (count($worldsNames) > 1) {
                        $counts = 0;

                        foreach ($worldsNames as $name) {
                            if (empty($name)) {
                                continue;
                            }

                            if ($this->getServer()->isLevelLoaded($name)) {
                                $worldsConfig = $this->getConfig()->get("worlds");

                                if (!in_array($name, $worldsConfig, true)) {
                                    $worldsConfig[] = $name;
                                    $this->getConfig()->set("worlds", $worldsConfig);
                                    $this->getConfig()->save();
                                }
                               
                                $level = $this->getServer()->getWorldManager()->getWorldByName($name);
                                $counts += $level->getPlayers()->count();
                            }
                        }

                        if ($counts > 0) {
                            $entity->setNameTag("Player Count: " . $counts);
                            $entity->setCustomName("Player Count: " . $counts);
                            $nbt->setInt("playerCount", $counts);
                            $entity->setNamedTag($nbt);
                        }
                    }
                }
            }
        }
    }

    public function onPlayerJoin(EntityEvent $event): void {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }

        $this->updateWorldPlayerCount();
    }

    public function onPlayerQuit(EntityEvent $event): void {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }

        $this->updateWorldPlayerCount();
    }

    public function updateWorldPlayerCount(): void {
        $worlds = $this->getConfig()->get("worlds");
        foreach ($worlds as $worldName) {
            $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);
            if ($world !== null) {
                $playerCount = $world->getPlayers()->count();
                $this->updateNPCCount($worldName, $playerCount);
            }
        }
    }

    public function updateNPCCount(string $worldName, int $count): void {
        $slapper = $this->getSlapper();
        if ($slapper !== null) {
            foreach ($slapper->getNPCs() as $npc) {
                $namedTag = $npc->getNamedTag();
                if ($namedTag->hasTag("playerCount") && $namedTag->getString("playerCount") === $worldName) {
                    $npc->setNameTag("World: $worldName | Players: $count");
                    $npc->setCustomName("World: $worldName | Players: $count");
                    $namedTag->setInt("playerCount", $count);
                    $npc->setNamedTag($namedTag);
                }
            }
        }
    }
}
