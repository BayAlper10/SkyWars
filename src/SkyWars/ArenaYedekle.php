<?php

namespace SkyWars;

use SkyWars\SWmain;

Class ArenaYedekle
{
    public function __construct(SWmain $main){
        $this->main = $main;
    }

    public function zip($player, $name)
    {
    $path = realpath($player->getServer()->getDataPath() . 'worlds/' . $name);
                            $zip = new \ZipArchive;
                            @mkdir($this->main->getDataFolder() . 'arenalar/', 0755);
                            $zip->open($this->main->getDataFolder() . 'arenalar/' . $name . '.zip', $zip::CREATE | $zip::OVERWRITE);
                            $files = new \RecursiveIteratorIterator(
                                    new \RecursiveDirectoryIterator($path),
                                    \RecursiveIteratorIterator::LEAVES_ONLY
                            );
                            foreach ($files as $file) {
                                    if (!$file->isDir()) {
                                            $relativePath = $name . '/' . substr($file, strlen($path) + 1);
                                            $zip->addFile($file, $relativePath);
                                    }
                            }
                            $zip->close();
                            $player->getServer()->loadLevel($name);
                            unset($zip, $path, $files);
    }
}
