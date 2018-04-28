<?php

namespace Authors\PixelgamesItemCloud;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class MainClass extends PluginBase implements Listener{

	/**
	 * @var MainClass
	 */

	private static $instance;

	/**
	 * @var ItemCloud[]
	 */

	private $clouds;

	/**
	 * @return MainClass
	 */

	public static function getInstance(){
		return self::$instance;
	}

	/**
	 * @param Player|string $player
	 *
	 * @return ItemCloud|bool
	 */

	public function getCloudForPlayer($player){
		if($player instanceof Player){
			$player = $player->getName();
		}

		$player = strtolower($player);

		if(isset($this->clouds[$player])){
			return $this->clouds[$player];
		}
		return false;
	}

	/**************************   Non-API part   ***********************************/

	public function onLoad(){
		if(!self::$instance instanceof MainClass){
			self::$instance = $this;
		}
                $this->getLogger()->info("Laden...");
	}

	public function onEnable(){
		@mkdir($this->getDataFolder());

		if(!is_file($this->getDataFolder()."ItemCloud.dat")){
			file_put_contents($this->getDataFolder()."ItemCloud.dat", serialize([]));
		}

		$data = unserialize(file_get_contents($this->getDataFolder()."ItemCloud.dat"));
		$this->saveDefaultConfig();

		if(is_numeric($interval = $this->getConfig()->get("auto-save-interval"))){
			$this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new SaveTask($this), $interval * 1200, $interval * 1200);
		}

		$this->clouds = [];

		foreach($data as $datam){
			$this->clouds[$datam[1]] = new ItemCloud($datam[0], $datam[1]);
		}
                $this->getLogger()->info("Aktiviert");
	}


	public function onCommand(CommandSender $sender, Command $command, string $label, array $params): bool{
		switch($command->getName()){

			case "itemcloud":

				if(!$sender instanceof Player){
					$sender->sendMessage("[PGItemCloud] Dieser Befehl muss ingame ausgeführt werden");
					return true;
				}

				$sub = array_shift($params);

				switch($sub){

					case "register":
					case "reg":

						if(!$sender->hasPermission("itemcloud.command.register")){
							$sender->sendMessage(TextFormat::RED."[PGItemCloud] Du hast nicht das Recht, diesen Befehl zu benutzen");
							return true;
						}

						if(isset($this->clouds[strtolower($sender->getName())])){
							$sender->sendMessage("§c[PGItemCloud] Du bist bereits bei ItemCloud registriert!");
							break;
						}

						$this->clouds[strtolower($sender->getName())] = new ItemCloud([], $sender->getName());
						$sender->sendMessage("§a[PGItemCloud] Du hast dich bei ItemCloud registriert und deinen Account erstellt.");
						break;

					case "upload":
					case "up":

						if(!$sender->hasPermission("itemcloud.command.upload")){
							$sender->sendMessage(TextFormat::RED."[PGItemCloud] Du hast nicht das Recht, diesen Befehl zu benutzen");
							return true;
						}

						if(!isset($this->clouds[strtolower($sender->getName())])){
							$sender->sendMessage("§c[PGItemCloud] Bitte registriere zuerst einen ItemCloud-Account!");
							break;
						}

						$item = array_shift($params);
						$amount = array_shift($params);

						if(trim($item) === "" or !is_numeric($amount)){
							$sender->sendMessage("§c[PGItemCloud] Benutzung: /itemcloud upload <ItemID[:Meta]/ItemName> <Anzahl>");
							break;
						}

						$amount = (int) $amount;

						if($amount < 1){
							$sender->sendMessage("§c[PGItemCloud] Fehler: Die Anzahl muss mindestens 1 sein.");
							break;
						}

						$item = Item::fromString($item);
						$item->setCount($amount);

						$count = 0;

						foreach($sender->getInventory()->getContents() as $i){
							if($i->getID() == $item->getID() and $i->getDamage() == $item->getDamage()){
								$count += $i->getCount();
							}
						}

						if($amount <= $count){
							$this->clouds[strtolower($sender->getName())]->addItem($item->getID(), $item->getDamage(), $amount, true);
							$sender->sendMessage("§a[PGItemCloud] Deine Items wurden auf deinen ItemCloud-Account hochgeladen.");

						}else{
							$sender->sendMessage("§c[PGItemCloud] Du hast davon nicht genug Items im Inventar.");
						}
						break;

					case "download":
					case "down":

						if(!$sender->hasPermission("itemcloud.command.download")){
							$sender->sendMessage(TextFormat::RED."[PGItemCloud] Du hast nicht das Recht, diesen Befehl zu benutzen");
							return true;
						}

						$name = strtolower($sender->getName());

						if(!isset($this->clouds[$name])){
							$sender->sendMessage("§c[PGItemCloud] Bitte registriere zuerst einen ItemCloud-Account!");
							break;
						}

						$item = array_shift($params);
						$amount = array_shift($params);

						if(trim($item) === "" or !is_numeric($amount)){
							$sender->sendMessage("§c[PGItemCloud] Benutzung: /itemcloud download <ItemID[:Meta]/ItemName> <Anzahl>");
							break;
						}

						$amount = (int)$amount;

						if($amount < 1){
							$sender->sendMessage("§c[PGItemCloud] Fehler: Die Anzahl muss mindestens 1 sein.");
							break;
						}

						$item = Item::fromString($item);
						$item->setCount($amount);


						if(!$this->clouds[$name]->itemExists($item->getID(), $item->getDamage(), $amount)){
							$sender->sendMessage("§c[PGItemCloud] Du hast davon nicht genug Items auf deinem ItemCloud-Account.");
							break;
						}

						if($sender->getInventory()->canAddItem($item)){
							$this->clouds[$name]->removeItem($item->getID(), $item->getDamage(), $amount);
							$sender->getInventory()->addItem($item);
							$sender->sendMessage("§a[PGItemCloud] Die Items wurden von deinem ItemCloud-Account in dein Inventar heruntergeladen.");

						}else{
							$sender->sendMessage("§c[PGItemCloud] Du hast nicht genug Platz im Inventar, um die Items herunterzuladen.");
						}
						break;

					case "list":

						if(!$sender->hasPermission("itemcloud.command.list")){
							$sender->sendMessage(TextFormat::RED."[PGItemCloud] Du hast nicht das Recht, diesen Befehl zu benutzen");
							return true;

						}

						$name = strtolower($sender->getName());

						if(!isset($this->clouds[$name])){
							$sender->sendMessage("§c[PGItemCloud] Bitte registriere zuerst einen ItemCloud-Account!");
							break;
						}

						$output = "§6[PGItemCloud] Liste aller Items auf deinem ItemCloud-Account: \n";

						foreach($this->clouds[$name]->getItems() as $item => $count){
							$output .= "§6$item : $count\n";
						}

						$sender->sendMessage($output);
						break;

					case "count":
                                            
						if(!$sender->hasPermission("itemcloud.command.count")){
							$sender->sendMessage(TextFormat::RED."[PGItemCloud] Du hast nicht das Recht, diesen Befehl zu benutzen");
							return true;
						}

						$name = strtolower($sender->getName());

						if(!isset($this->clouds[$name])){
							$sender->sendMessage("§c[PGItemCloud] Bitte registriere zuerst einen ItemCloud-Account!");
							return true;
						}

						$item = array_shift($params);

						if(trim($item) === ""){
							$sender->sendMessage("§c[PGItemCloud] Benutzung: /itemcloud count <ItemID[:Meta]/ItemName>");
							return true;
						}

						$item = Item::fromString($item);

						if(($count = $this->clouds[$name]->getCount($item->getID(), $item->getDamage())) === false){
							$sender->sendMessage("§e[PGItemCloud] Auf deinem ItemCloud-Account gibt es das Item ".$item->getName()." nicht.");
							break;

						}else{
							$sender->sendMessage("§e[PGItemCloud] Anzahl des Items ".$item->getName()." auf deinem ItemCloud-Account = ".$count);
						}
						break;
                                                
                                        case "info":
                                            
                                                $sender->sendMessage("§e---------------------------------");
                                                $sender->sendMessage("§ePlugin von onebone, iStrafeNubzHDyt");
                                                $sender->sendMessage("§bName: PixelgamesItemCloud");
                                                $sender->sendMessage("§bOriginal: ItemCloud");
                                                $sender->sendMessage("§bVersion: 2.3#");
                                                $sender->sendMessage("§bFür PocketMine-API: 3.0.0-ALPHA12");
                                                $sender->sendMessage("§6Permissions: itemcloud.*, itemcloud.command.*, itemcloud.command.register, itemcloud.command.upload, itemcloud.command.download, itemcloud.command.list, itemcloud.command.count");
                                                $sender->sendMessage("§eSpeziell für PIXELGAMES entwickelt");
                                                $sender->sendMessage("§e---------------------------------");
                                                return true;
                                                break;
                                            
                                        case "help":
                                            
                                                $sender->sendMessage("§9---§aItemCloud-Plugin§9---");
                                                $sender->sendMessage("§a/itemcloud <reg/register> §b-> Registriert einen ItemCloud-Account");
                                                $sender->sendMessage("§a/itemcloud <up/upload> <ItemID[:Meta]/ItemName> <Anzahl> §b-> Lädt deine Items aus dem Inventar auf deinen ItemCloud-Account");
                                                $sender->sendMessage("§a/itemcloud <down/download> <ItemID[:Meta]/ItemName> <Anzahl> §b-> Lädt Items von deinem ItemCloud-Account in dein Inventar");
                                                $sender->sendMessage("§a/itemcloud list §b-> Zeigt eine Liste aller Items auf deinem ItemCloud-Account");
                                                $sender->sendMessage("§a/itemcloud count <ItemID[:Meta]/ItemName> §b-> Sucht nach einem bestimmten Item auf deinem ItemCloud-Account und zeigt die Anzahl an");
                                                $sender->sendMessage("§6/itemcloud info §b-> Zeigt Details über das Plugin");
                                                $sender->sendMessage("§6/itemcloud help §b-> Zeigt dieses Hilfemenü an");
                                                return true;
                                                break;
                                            
					default:
						$sender->sendMessage("§c[PGItemCloud] Benutzung: ".$command->getUsage());
                                                $sender->sendMessage("§6[PGItemCloud] Benutzung: /itemcloud info");
                                                $sender->sendMessage("§6[PGItemCloud] Benutzung: /itemcloud help");
				}
				return true;
		}
		return false;
	}

        
	public function save(){

		$save = [];

		foreach($this->clouds as $cloud){
			$save[] = $cloud->getAll();
		}
		file_put_contents($this->getDataFolder()."ItemCloud.dat", serialize($save));
	}


	public function onDisable(){
		$this->save();
		$this->clouds = [];
                $this->getLogger()->info("Deaktiviert");
	}
}
