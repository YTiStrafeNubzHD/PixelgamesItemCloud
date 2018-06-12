<?php

namespace Authors\PixelgamesItemCloud;

use pocketmine\scheduler\Task;

class SaveTask extends Task{

  public function __construct(MainClass $plugin){
    $this->plugin = $plugin;
  }


  public function onRun($currentTick){
    $this->save();
  }

    public function save() {
        $this->plugin->save();
    }

}
