<?php

namespace Authors\PixelgamesItemCloud;

use pocketmine\scheduler\PluginTask;

class SaveTask extends PluginTask{

  public function __construct(MainClass $plugin){
    parent::__construct($plugin);
  }


  public function onRun($currentTick){
    $this->save();
  }

    public function save() {
        $this->getOwner()->save();
    }

}