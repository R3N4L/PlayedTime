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

class Main extends PluginBase implements Listener {

    public $antri = [];
	
    public $played = [];
	
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
            $this->saveResource("config.yml");
            $this->config = (new Config($this->getDataFolder() . "config.yml", Config::YAML))->getAll();
            $this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML, ["leaderboard-point" => array(), "time" => array()]);
            $this->data->save(true);
         while(true){
         	if(date('s') == 59) {
         	    break;
             }
         	$cd = 59 - date('s');
         	$this->getServer()->getLogger()->info('played time enable on: ' . $cd . ' seccond');
             sleep(1);
         }
         $this->updateLeaderboard();
         $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function() : void {
             $antri = $this->antri;
             $played = $this->played;
             if(count($this->played) == 0 and count($this->antri) == 0) {

             } else {
             	foreach ($antri as $antriName) {
                    $this->addPlayed($antriName);
                	$this->removeAntri($antriName);
                 };
                 foreach ($played as $playedName) {
                    $this->onAddTime($playedName);
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
    	$player = $event->getPlayer();
    	$playername = $player->getName();
    	if (in_array($playername, $this->antri)) {
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
                    } else {
                        $sender->sendMessage("§aUsage: §e/playedtime leaderboard add/remove");
                        return true;
                    }
                } else {
                    $sender->sendMessage("§aUsage: §e/playedtime leaderboard add/remove");
                    return true;
                }
            break;
            default:
                $sender->sendMessage("§aUsage: §e/playedtime leaderboard add/remove");
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
		$leaderboard = $this->data->get("leaderboard-point");
		arsort($leaderboard);
		$leaderboard = array_slice($leaderboard, 0, 9);
		foreach($leaderboard as $name => $result) {
			$res[] = $name;
		}
		return $res;
	}
    
    public function updateLeaderboard() {
    	$res = $this->config["leaderboard-title"];
        $topName = $this->onTopNameLeaderboard();
        $count = 1;
        foreach($topName as $name) {
            $timePlayer = $this->data->get("time")[$name];
            $hour = $timePlayer['hour'];
            $min = $timePlayer['min'];
            $res .= TF::EOL . str_replace(["{RANK}", "{NAME}", "{HOURS}", "{MINUTES}"], [$count, $name, $hour, $min], $this->config["leaderboard-text"]);
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
    
    public function onAddLeaderboard($playerName) {
		if(!isset($this->data->get("leaderboard-point")[$playerName])) {
			$all = $this->data->get("leaderboard-point");
			$all[$playerName] = array("result" => 00);
			$this->data->set("leaderboard-point", $all);
			$this->data->save(true);
		}
		if($this->data->get("time")[$playerName]["hour"] == 0) {
			$res = $this->data->get("time")[$playerName]["hour"] . $this->data->get("time")[$playerName]["min"];
		} else {
			$res = $this->data->get("time")[$playerName]["hour"] . $this->data->get("time")[$playerName]["min"] . "0";
		}
		$this->data->setNested("leaderboard-point." . $playerName, array("result" => (int)$res));
		$this->data->save(true);
	}

    public function onAddTime($playerName) {
		if(!isset($this->data->get("time")[$playerName])) {
			$all = $this->data->get("time");
			$all[$playerName] = array("hour" => 00, "min" => 00);
			$this->data->set("time", $all);
			$this->data->save(true);
		}
		if($this->data->get("time")[$playerName]["min"] == 59) {
			$this->data->setNested("time." . $playerName, array("hour" => $this->data->get("time")[$playerName]["hour"] + 1, "min" => 00));
			$this->data->save(true);
			return $this->onAddLeaderboard($playerName);
		}
		$this->data->setNested("time." . $playerName, array("hour" => $this->data->get("time")[$playerName]["hour"], "min" => $this->data->get("time")[$playerName]["min"] + 1));
		$this->data->save(true);
		$this->onAddLeaderboard($playerName);
	}

    public function addPlayed($playerName) {
    	$this->played[] = $playerName;
    }

    public function removePlayed($playerName) {
    	unset($this->played[array_search($playerName, $this->played)]);
    }

    public function addAntri($playerName) {
    	$this->antri[] = $playerName;
    }

    public function removeAntri($playerName) {
    	unset($this->antri[array_search($playerName, $this->antri)]);
    }
}

?>
