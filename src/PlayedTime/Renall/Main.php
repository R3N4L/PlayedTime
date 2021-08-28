<?php

//Collaborator github.com/MulqiGaming64

namespace PlayedTime\Renall;

use pocketmine\Server;
use pocketmine\Player;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;

use slapper\events\SlapperCreationEvent;
use slapper\events\SlapperDeletionEvent;

use pocketmine\entity\Entity;

use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\CompoundTag;

class Main extends PluginBase implements Listener {

    private $particles = [];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
            $this->saveResource("config.yml");
            $this->config = (new Config($this->getDataFolder() . "config.yml", Config::YAML))->getAll();
            $this->antri = new Config($this->getDataFolder() . "antri.json", Config::JSON);
            $this->antri->save();
            $this->played = new Config($this->getDataFolder() . "played.json", Config::JSON);
            $this->played->save();
             $this->time = new Config($this->getDataFolder() . "time.json", Config::JSON);
             $this->time->save();
             $this->leaderboard = new Config($this->getDataFolder() . "leaderboard.json", Config::JSON);
             $this->leaderboard->save();
         while(true){
         	if(date('s') == 59) {
         	    break;
             }
         	$cd = 59 - date('s');
         	$this->getServer()->getLogger()->info('played time enable on: ' . $cd . ' seccond');
             sleep(1);
         }
         $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function() : void {
             $antri = $this->antri->antri;
             $played = $this->played->played;
             if(!$played){
             	$this->played->set('played', []);
                 $this->played->save();
             }
             if(!$antri) {
             	$this->antri->set('antri', []);
                 $this->antri->save();
             }
             if(count($this->played->played) == 0 and count($this->antri->antri) == 0) {

             } else {
             	foreach ($antri as $antriName) {
                    $this->addPlayed($antriName);
                	$this->removeAntri($antriName);
                 };
                 foreach ($played as $playedName) {
                    $this->addTimes($playedName);
                    $this->getServer()->getLogger()->info('adding time');
                 }
                 $this->updateLeaderboard();
             }
         }), 20, 20*60);
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
        $this->addAntri($playername);
    }

    public function onQuit(PlayerQuitEvent $event) {
        $this->getServer()->getLogger()->info("Ada player Keluar!");
    	$player = $event->getPlayer();
    	$playername = $player->getName();
    	if (in_array($playername, $this->antri->antri)) {
    		$this->removeAntri($playername);
    	} else {
    	    $this->removePlayed($playername);
    	}
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool {
        switch($cmd->getName()) {
            case 'playedtime':
                if ($args[0] == 'leaderboard') {
                    if($args[1] == 'add') {
                    	$this->getServer()->getCommandMap()->dispatch($sender, 'slapper spawn human topplayed');
                    } else if($args[1] == 'remove') {
                	    $this->getServer()->getCommandMap()->dispatch($sender, 'slapper remove');
                    }
                }
            break;
        }
        return true;
    }
    
    public function onSlapperCreate(SlapperCreationEvent $event) {
    	$entity = $event->getEntity();
        $name = $entity->getNameTag();
        if($name == 'topplayed') {
        	$entity->namedtag->setString('topplayed', 'topplayed');
            $this->updateLeaderboard();
        }
    }
    
    public function onTopNameLeaderboard() {
    	$res = [];
    	$leaderboard = $this->leaderboard->getAll();
        arsort($leaderboard);
        $leaderboard = array_slice($leaderboard, 0, 9);
        foreach($leaderboard as $name => $result) {
        	$text = $name;
        	$res[] = $text;
        }
        return $res;
    }
    
    public function updateLeaderboard() {
    	$res = 'Top Played';
        $topName = $this->onTopNameLeaderboard();
        $timeDb = $this->time;
        $count = 1;
        foreach($topName as $name) {
            $timePlayer = $timeDb->$name;
            $hour = $timePlayer['hour'];
            $min = $timePlayer['min'];
            $res .= TF::EOL . $count . '. ' . $name . ' - ' . $hour . ' jam ' . $min . ' menit';
            $count++;
        }
        foreach($this->getServer()->getLevels() as $level) {
        	foreach($level->getEntities() as $entity) {
        	    if($entity->namedtag->hasTag('topplayed', StringTag::class)) {
        	        if($entity->namedtag->getString('topplayed') == 'topplayed') {
        	            $entity->setNameTag($res);
                        $entity->getDataPropertyManager()->setFloat(Entity::DATA_BOUNDING_BOX_HEIGHT, 3);
                        $entity->getDataPropertyManager()->setFloat(Entity::DATA_SCALE, 0.0);
                    }
                }
            }
        }
    }
    
    public function addLeaderboard($playerName) {
    	$timedb = $this->time->$playerName;
        $hour = $timedb['hour'];
        $min = $timedb['min'];
        if($hour == 0) {
        	$res = $hour . $min;
        } else {
            $res = $hour . $min . '0' ;
        }
    	$this->leaderboard->$playerName = array('result' => (int)$res);
        $this->leaderboard->save();
    }

    public function addTimes($playerName) {
        if (!isset($this->time->$playerName)) {
               $this->time->$playerName = array("hour" => 00, "min" => 00);
               $this->time->save();
        }
        $hour = $this->time->$playerName["hour"];
        $min = $this->time->$playerName["min"];
        if ($min == 59) {
        	$this->time->$playerName = array("hour" => $hour + 1, "min" => 00);
        	$this->time->save();
            return $this->addLeaderboard($playerName);
        }
        $this->time->$playerName = array("hour" => $hour, "min" => $min + 1);
        $this->time->save();
        $this->addLeaderboard($playerName);
    }

    public function addPlayed($playerName) {
    	$allplayed = array($playerName);
    	$playeddb = $this->played->played;
    	if (!$playeddb) {
    		$this->played->set("played", [$playerName]);
    	} else {
    		foreach ($playeddb as $played) {
    			$allplayed[] = $played;
    		}
    		$this->played->set("played", $allplayed);
    	}
    	$this->played->save();
    }

    public function removePlayed($playerName) {
    	$playeddb = $this->played->played;
    	$index = array_search($playerName, $playeddb);
    	unset($playeddb[$index]);
        $this->played->set("played", $playeddb);
   	    $this->played->save();
    }

    public function addAntri($playerName) {
        $allantri = array($playerName);
        $antridb = $this->antri->antri;
        if (!$antridb) {
            $this->antri->set("antri", [$playerName]);
        } else {
            foreach ($antridb as $antri) {
                $allantri[] = $antri;
            }
            $this->antri->set("antri", $allantri);
        }
        $this->antri->save();
    }

    public function removeAntri($playerName) {
    	$antridb = $this->antri->antri;
    	$index = array_search($playerName, $antridb);
    	unset($antridb[$index]);
    	$this->antri->set("antri", $antridb);
    	$this->antri->save();
    }
}

?>
