<?php

namespace SkyWars;

use SkyWars\SWmain;

Class MapYenile
{
    public $main;

    public function __construct(SWmain $main){
        $this->main = $main;
    }

    public function reload($levelArena)
    {
            $name = $levelArena->getFolderName();
            if ($this->main->getServer()->isLevelLoaded($name))
            {
                    $this->main->getServer()->unloadLevel($this->main->getServer()->getLevelByName($name));
            }
            $zip = new \ZipArchive;
            $zip->open($this->main->getDataFolder() . 'arenalar/' . $name . '.zip');
            $zip->extractTo($this->main->getServer()->getDataPath() . 'worlds');
            $zip->close();
            unset($zip);
            $this->main->getServer()->loadLevel($name);
            return true;
    }
}
