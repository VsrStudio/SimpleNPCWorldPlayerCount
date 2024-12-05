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
use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use xXKHaLeD098Xx\WorldPlayerCount\Task\RefreshCount;

class WorldPlayerCount extends PluginBase implements Listener {

    /** @return SimpleNPC */
    public function getSlapper(): Plugin{
        return $this->getServer()->getPluginManager()->getPlugin("SimpleNPC");
    }

    public function onEnable(): void{
        $map = $this->getDescription()->getMap();
        $ver = $this->getDescription()->getVersion();
        if(isset($map["author"])){
            if($map["author"] !== "xXKHaLeD098Xx" or $ver !== "2.0-beta"){
                $this->getLogger()->emergency("§cPlugin info has been changed, please give the author the proper credits, set the author to \"xXKHaLeD098Xx\" and setting the version to \"2.0-beta\" if required, or else the server will shutdown on every start-up");
                $this->getServer()->shutdown();
                return;
            }
        }else{
            $this->getLogger()->emergency("§cPlugin info has been changed, please give the author the proper credits, set the author to \"xXKHaLeD098Xx\" and setting the version to \"2.0-beta\" if required, or else the server will shutdown on every start-up");
            $this->getServer()->shutdown();
        }
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->saveResource("config.yml");
        $this->getScheduler()->scheduleRepeatingTask(new RefreshCount($this), (int)$this->getConfig()->get("count-interval") * 20);
        $worlds = $this->getConfig()->get("worlds");
        foreach($worlds as $key => $world){
            if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $world)){
                $this->getServer()->loadLevel($world);
            }else{
                unset($worlds[$key]);
                $this->getConfig()->set("worlds", $worlds);
                $this->getConfig()->save();
            }
        }
    }

    public function slapperCreation(SNPCCreationEvent $ev): void{
        $entity = $ev->getEntity();
        $name = $entity->getNameTag();
        if(strpos($name, "\n") !== false){
            $allines = explode("\n", $name);
            $pos = strpos($allines[1], "count ");
            if($pos !== false){
                //Single world
                $levelname = str_replace("count ", "", $allines[1]);
                if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $levelname)){
                    if(!$this->getServer()->isLevelLoaded($levelname)){
                        $this->getServer()->loadLevel($levelname);
                    }
                    $entity->namedtag->setString("playerCount", $levelname);
                    $worlds = $this->getConfig()->get("worlds");
                    if(!in_array($levelname, $worlds, true)){
                        $worlds[] = $levelname;
                        $this->getConfig()->set("worlds", $worlds);
                        $this->getConfig()->save();
                        return;
                    }
                }
            }
            //Multi-world
            $combinedPos = strpos($allines[1], "combinedcounts ");
            if($combinedPos !== false){
                $symbolPos = strpos($allines[1], "&");
                if($symbolPos !== false){
                    $levelnameS = str_replace("combinedcounts ", "", $allines[1]);
                    $levelnamesInArray = explode("&", $levelnameS);
                    if(in_array("", $levelnamesInArray, true)){
                        return;
                    }
                    $entity->namedtag->setString("combinedPlayerCounts", $levelnameS);
                    $this->combinedPlayerCounts();
                }
            }
        }
    }

    public function onSlapperDeletion(SNPCDeletionEvent $event): void{
        // single world
        if($event->getEntity()->namedtag->hasTag("playerCount")){
            $tag = $event->getEntity()->namedtag->getString("playerCount");
            $event->getEntity()->namedtag->removeTag("playerCount");
            $worlds = $this->getConfig()->get("worlds");
            unset($worlds[array_search($tag, $worlds, true)]);
            $this->getConfig()->set("worlds", $worlds);
            $this->getConfig()->save();
        }
        // combined
        if($event->getEntity()->namedtag->hasTag("combinedPlayerCounts")){
            $tag = $event->getEntity()->namedtag->getString("combinedPlayerCounts");
            $event->getEntity()->namedtag->removeTag("combinedPlayerCounts");
            $worlds = $this->getConfig()->get("worlds");
            $arrayOfNames = explode("&", $tag);
            foreach($arrayOfNames as $name){
                if(in_array($name, $worlds, true)){
                    unset($worlds[array_search($name, $worlds, true)]);
                    $this->getConfig()->set("worlds", $worlds);
                    $this->getConfig()->save();
                }
            }
        }
    }

    public function DamageEvent(EntityDamageByEntityEvent $event): void{
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if(($entity instanceof BaseNPC || $entity instanceof CustomHuman) and $damager instanceof Player){
            if(isset($slapper->hitSessions[$damager->getName()])){ 
                $slapperDelete = new SNPCDeletionEvent($event->getEntity());
                $slapperDelete->call();
            }
        }
    }

    // here no single world allowed, only combined ones

    public function combinedPlayerCounts(): void{
        $levels = $this->getServer()->getLevels();
        foreach($levels as $level){
            foreach($level->getEntities() as $entity){
                $nbt = $entity->namedtag;
                if($nbt->hasTag("combinedPlayerCounts") && !$nbt->hasTag("playerCount")){
                    $worldsNames = explode("&", $nbt->getString("combinedPlayerCounts"));
                    foreach($worldsNames as $name){
                        if(!file_exists($this->getServer()->getDataPath() . "/worlds/" . $name)){
                            unset($worldsNames[array_search($name, $worldsNames, true)]);
                            $slapperDelete = new SNPCDeletionEvent($entity);
                            $slapperDelete->call();
                            $entity->close();
                        }
                    }
                    // extra checks just in case
                    if(count($worldsNames) > 1){
                        $counts = 0;
                        foreach($worldsNames as $name){
                            if($name === ""){
                                continue;
                            }
                            if($this->getServer()->isLevelLoaded($name)){
                                $worlds = $this->getConfig()->get("worlds");
                                if(!in_array($name, $worlds, true)){
                                    $worlds[] = $name;
                                    $this->getConfig()->set("worlds", $worlds);
                                    $this->getConfig()->save();
                                }
                                $pmLevel = $this->getServer()->getLevelByName($name);
                                $countOfLevel = count($pmLevel->getPlayers());
                                $counts += $countOfLevel;
                            }else{
                                $worlds = $this->getConfig()->get("worlds");
                                if(!in_array($name, $worlds, true)){
                                    $worlds[] = $name;
                                    $this->getConfig()->set("worlds", $worlds);
                                    $this->getConfig()->save();
                                }
                                $this->getServer()->loadLevel($name);
                            }
                        }
                        $count = $this->getConfig()->get("count");
                        $str = str_replace("{number}", $counts, $count);
                        $allines = explode("\n", $entity->getNameTag());
                        $entity->setNameTag($allines[0] . "\n" . $str);
                    }
                }
            }
        }
    }

    public function playerCount(): void{
        $levels = $this->getServer()->getLevels();
        foreach($levels as $level){
            $entities = $level->getEntities();
            foreach($entities as $entity){
                $nbt = $entity->namedtag;
                if($nbt->hasTag("playerCount") && !$nbt->hasTag("combinedPlayerCounts")){
                    $levelName = $nbt->getString("playerCount");
                    if($this->getServer()->isLevelLoaded($levelName)){
                        $level = $this->getServer()->getLevelByName($levelName);
                        $count = count($level->getPlayers());
                        $countStr = str_replace("{number}", $count, $this->getConfig()->get("count"));
                        $lines = explode("\n", $entity->getNameTag());
                        $entity->setNameTag($lines[0] . "\n" . $countStr);
                    } else {
                        $this->getServer()->loadLevel($levelName);
                    }
                }
            }
        }
    }

    public function onDisable(): void {
        $this->getLogger()->info("WorldPlayerCount plugin has been disabled.");
    }
}
