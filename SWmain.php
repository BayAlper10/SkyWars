<?php

namespace SkyWars;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\{CommandSender, Command, ConsoleCommandSender};
use pocketmine\{Player, Server};
use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\inventory\ChestInventory;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\tile\Sign;
use pocketmine\tile\Chest;
use pocketmine\item\Item;
use pocketmine\scheduler\Task as PluginTask;
use SkyWars\formapi\SimpleForm;
use Core\Loader;

use SkyWars\ArenaYedekle;

class SWmain extends PluginBase implements Listener{

  public $arenalar = array();
  public $yer = "";
  public $kurulum;

  public function onEnable(){
    $this->getLogger()->info("SkyWars aktif edildi");
    $this->getServer()->getPluginManager()->registerEvents($this, $this);
    @mkdir($this->getDataFolder());
    $this->getScheduler()->scheduleRepeatingTask(new TabelaYenile($this), 10);
    $this->getScheduler()->scheduleRepeatingTask(new Oyun($this), 20);

    $this->coin = new Config($this->getDataFolder()."coin.yml", Config::YAML);
    $this->win = new Config($this->getDataFolder()."win.yml", Config::YAML);

    $config = new Config($this->getDataFolder()."config.yml", Config::YAML);
    if($config->get("arenalar") != null){
      $this->arenalar = $config->get("arenalar");
    }
    foreach($this->arenalar as $arena){
      $this->getServer()->loadLevel($arena);
    }
    $itemler = array(array(1,0,32), array(1,0,10), array(261, 0, 1), array(262, 0 ,32), array(466, 0, 1), array(276, 0, 0));
    if($config->get("sandik") == null){
      $config->set("sandik", $itemler);
    }
    $config->save();
  }

  public function onJoin(PlayerJoinEvent $e){
    $player = $e->getPlayer();
    $w = $this->getConfig()->get("world");
    $world = $player->getLevel()->getName() === "$w";
    $top = $this->getConfig()->get("enable");
    $name = $player->getName();
    if(!$this->coin->exists($name)){
      $this->coin->set($name, 0);
      $this->coin->save();
    }
    if(!$this->win->exists($name)){
      $this->win->set($name, 0);
      $this->win->save();
    }

    if($world){
      if($top == "true"){
        $this->topCoin($player);
        $this->topWin($player);
      }
    }
  }

  public function topWin($p){
    $player = $p->getPlayer();
    $swallet = $this->win->getAll();
    $c = count($swallet);
    $message = "";
    $top = "§aEn Çok Kazananlar";
    arsort($swallet);
    $i = 1;
    foreach($swallet as $name => $amount){
      $message .= "§b ".$i.". §7".$name."  §egalibiyet  §f".$amount."\n";
      if($i > 9){
        break;
      }
      ++$i;
    }
    $x = $this->getConfig()->get("win-x");
    $y = $this->getConfig()->get("win-y");
    $z = $this->getConfig()->get("win-z");
    $p = new FloatingTextParticle(new Vector3($x, $y + 1, $z), $message, $top);
		$player->getLevel()->addParticle($p);
  }

  public function topCoin($p){
    $player = $p->getPlayer();
    $swallet = $this->coin->getAll();
    $c = count($swallet);
    $message = "";
    $top = "§aEn Fazla Coin Listesi";
    arsort($swallet);
    $i = 1;
    foreach($swallet as $name => $amount){
      $message .= "§b ".$i.". §7".$name."  §emiktar  §f".$amount."\n";
      if($i > 9){
        break;
      }
      ++$i;
    }
    $x = $this->getConfig()->get("coin-x");
    $y = $this->getConfig()->get("coin-y");
    $z = $this->getConfig()->get("coin-z");
    $p = new FloatingTextParticle(new Vector3($x, $y + 1, $z), $message, $top);
		$player->getLevel()->addParticle($p);
  }

  public function getZip(){
    return new ArenaYedekle($this);
  }

  public function mapYenile(){
    return new MapYenile($this);
  }

  public function arenaYenile(){
    $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
    $config->set("arenalar", $this->arenalar);
    foreach($this->arenalar as $arena){
      $config->set($arena . "OyunSuresi", 780);
      $config->set($arena . "BaslamaSuresi", 30);
    }
    $config->save();
  }

  public function swVurma(EntityDamageEvent $e): void{
    if($e instanceof EntityDamageByEntityEvent){
      if($e->getEntity() instanceof Player){
        if($e->getDamager() instanceof Player){
          $harita = $e->getEntity()->getLevel()->getFolderName();
          $config = new Config($this->getDataFolder()."config.yml", Config::YAML);
          if($config->get($harita . "OyunSuresi") != null){
            if($config->get($harita . "OyunSuresi") > 750){
              $e->setCancelled(true);
            }
          }
        }
      }
    }
  }

  public function swKirma(BlockBreakEvent $e): void{
    $player = $e->getPlayer();
    $harita = $player->getLevel()->getFolderName();
    if(in_array($harita, $this->arenalar)){
      $config = new Config($this->getDataFolder()."config.yml", Config::YAML);
      $bsure = $config->get($harita . "BaslamaSuresi");
      if($bsure > 0){
        $e->setCancelled(true);
      }
    }
  }

  public function swKoyma(BlockPlaceEvent $e): void{
    $player = $e->getPlayer();
    $harita = $player->getLevel()->getFolderName();
    if(in_array($harita, $this->arenalar)){
      $config = new Config($this->getDataFolder()."config.yml", Config::YAML);
      $bsure = $config->get($harita . "BaslamaSuresi");
      if($bsure > 0){
        $e->setCancelled(true);
      }
    }
  }

  public function swOlme(PlayerDeathEvent $e){
		$oyuncu = $e->getEntity();
		$harita = $oyuncu->getLevel()->getFolderName();
		if(in_array($harita,$this->arenalar)){
			if($e->getEntity()->getLastDamageCause() instanceof EntityDamageByEntityEvent){
				$olduren = $e->getEntity()->getLastDamageCause()->getDamager();
				if($olduren instanceof Player){
					$e->setDeathMessage("");
					foreach($oyuncu->getLevel()->getPlayers() as $oy){
						$oy->sendMessage("§8» §f" . $oyuncu->getName() . " §cisimli oyuncu §f" . $olduren->getName() . " cisimli oyuncu tarafından öldürüldü.");
					}
				}
			}
		}
	}

  public function swDokunma(PlayerInteractEvent $e): void{
    $player = $e->getPlayer();
    $harita = $player->getLevel()->getFolderName();
    if(in_array($harita, $this->arenalar)){
      if($e->getBlock()->getId() == "54"){
        $config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        $bsure = $config->get($harita . "BaslamaSuresi");
        if($bsure > 0){
          $e->setCancelled(true);
        }
      }
    }
    if($player->getInventory()->getItemInHand()->getId() == 340){
      if($player->getInventory()->getItemInHand()->getCustomName() == "§6Tıkla Ve Kit Seç"){
        $this->kitForm($player);
      }
    }
  }

  public function kitForm($player){
    $form = new SimpleForm(function (Player $event, $data){
      $player = $event->getPlayer();
      $oyuncu = $player->getName();
      if($data===null){
        return;
      }
      switch($data){
        case 0:
        $kitc = new Config($this->getDataFolder()."kit.yml", Config::YAML);
        $kitc->set($player->getName(), "savasci");
        $player->sendMessage("§1> Kit başarıyla seçildi");
        $kitc->save();
        break;
        case 1:
        $kitc = new Config($this->getDataFolder()."kit.yml", Config::YAML);
        $kitc->set($player->getName(), "insaatci");
        $player->sendMessage("§1> Kit başarıyla seçildi");
        $kitc->save();
        break;
        case 2:
        $kitc = new Config($this->getDataFolder()."kit.yml", Config::YAML);
        $kitc->set($player->getName(), "tank");
        $player->sendMessage("§1> Kit başarıyla seçildi");
        $kitc->save();
        break;
      }
    });
    $form->setTitle("§8SkyWars Kit Menüsü");
    $form->addButton("Savaşçı");
    $form->addButton("İnşaatçı");
    $form->addButton("Tank");
    $form->sendToPlayer($player);
  }

  public function onCommand(CommandSender $cs, Command $cmd, string $label, array $args): bool{
    switch($cmd->getName()){
      case "sw":
      if($cs->isOp()){
        if(!empty($args[0])){
           if($args[0] == "olustur"){
             if(!empty($args[1])){
               if(file_exists($this->getServer()->getDataPath()."/worlds/".$args[1])){
                 $this->getServer()->loadLevel($args[1]);
                 $this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
                 array_push($this->arenalar, $args[1]);
                 $this->yer = $args[1];
                 $this->kurulum = 1;
                 $cs->sendMessage("§8» §aOyuncu bölgelerini belirlemek için bir bölgeye dokun.");
                 $cs->setGameMode(1);
                 $cs->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(), 0, 0);
                 $disim = $args[1];
                 $this->getZip()->zip($cs, $disim); //Dünya yedekleme
               }else{
                 $cs->sendMessage("§8» §cBelirtilen dünya worlds klasöründe bulunamadı.");
               }
             }else{
               $cs->sendMessage("§8» §cBir dünya belirtmeniz gerek.");
             }
           }else{
             $cs->sendMessage("§8» §cGeçersiz bir komut girdin.");
           }
        }else{
          $cs->sendMessage("§l§bSkyWars Komutları");
          $cs->sendMessage("§b/sw olustur <world> §eBir oyun oluşturmanızı sağlar");
          $cs->sendMessage("§b/sw katil <oyun> §eBir oyuna katılmanızı sağlar");
        }
      }else{
        $cs->sendMessage("§8» §cBu komutu kullanmak için yetkin yok.");
      }
    }
    return true;
  }

  public function onInteract(PlayerInteractEvent $e): void{
    $player = $e->getPlayer();
    $name = $player->getName();
    $block = $e->getBlock();
    $tile = $player->getLevel()->getTile($block);
    $config = new Config($this->getDataFolder()."config.yml", Config::YAML);

    //kit

    if($tile instanceof Sign){
      if($this->kurulum == 26){
        $tile->setText("§aOyna", "0/12", "§f" . $this->yer, "§eTıkla Ve Katıl");
        $this->arenaYenile();
        $this->yer = "";
        $this->kurulum = 0;
        $player->sendMessage("§8» §aArena başarı ile kuruldu.");
      }
    }
    if($this->kurulum >= 1 && $this->kurulum <= 12){
      $config->set($this->yer . "Spawn" . $this->kurulum, array($block->getX(), $block->getY()+1, $block->getZ()));
      $config->save();
      $player->sendMessage("§8» §aSpawn ayarlandı §f" . $this->kurulum);
      $this->kurulum++;
    }

      if($this->kurulum >= 13 && $this->kurulum <=14){
        $player->sendMessage("§8» §aBir lobi belirlemek için tıkla.");
        $config->set($this->yer . "Lobi", array($block->getX(), $block->getY()+1, $block->getZ()));
        $config->save();
        $this->kurulum++;
      }
        if($this->kurulum == 15){
        $player->sendMessage("§8» §aTıkla ve geri dön.");
        $level = $this->getServer()->getLevelByName($this->yer);
				$level->setSpawn = (new Vector3($block->getX(),$block->getY()+2,$block->getZ()));
        $config->save("arenalar", $this->arenalar);
        $player->sendMessage("§8» §aBir tabelaya tıkla ve kurulumu tamamla.");
        $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
        $this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
        $player->teleport($spawn, 0, 0);
        $config->save();
        $this->kurulum = 26;
    }

    if($tile instanceof Sign){
      $text = $tile->getText();
    if($text[3] == "§eTıkla Ve Katıl"){
      if($text[0] == "§aOyna"){
        $mapisim = str_replace("§f", "", $text[2]);
        $level = $this->getServer()->getLevelByName($mapisim);

        if($text[1] == "0/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "1/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "2/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "3/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "4/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "5/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "6/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "7/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "8/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "9/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "10/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }elseif($text[1] == "11/12"){
          $player->setNameTag("§l§3BEKLEMEDE");
          $lobi = $config->get($mapisim . "Lobi");
          $player->sendMessage("§1> Lobiye katıldın.");
          foreach($level->getPlayers() as $pl){
            $pl->sendMessage("§1> §f$name §1lobiye katıldı.");
          }
        }
        $spawn = new Position($lobi[0]+0.5,$lobi[1],$lobi[2]+0.5,$level);
        $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
        $player->teleport($spawn, 0, 0);
        $player->getInventory()->clearAll();
        $player->removeAllEffects();
        $player->setHealth(20);
        $player->getInventory()->setItem(1, Item::get(340)->setCustomName("§6Tıkla Ve Kit Seç"));
      }else{
        $player->sendMessage("§8» §aBu oyuna malesef katılamazsın.");
        }
      }
    }
  }
}

class TabelaYenile extends PluginTask{

  public function __construct($plugin){
    $this->p = $plugin;
  }

  public function onRun($tick){
    $tumoyuncular = $this->p->getServer()->getOnlinePlayers();
		$level = $this->p->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
    $config = new Config($this->p->getDataFolder() . "config.yml", Config::YAML);

    foreach($tiles as $t){
      if($t instanceof Sign){
        $text = $t->getText();
        if($text[3] == "§eTıkla Ve Katıl"){
          $aop = 0;
          $mapisim = str_replace("§f", "", $text[2]);
          foreach ($tumoyuncular as $oyuncu){
            if($oyuncu->getLevel()->getFolderName() == $mapisim){
              $aop = $aop+1;
            }
          }
          $oyunda = "§aOyna";
          if($config->get($mapisim . "OyunSuresi") != 780){
            $oyunda = "§cOynanıyor";
          }elseif($aop >= 12){
            $oyunda = "§6Dolu";
          }
          $t->setText($oyunda, $aop . "/12", $text[2], "§eTıkla Ve Katıl");
        }
      }
    }
  }
}

class Oyun extends PluginTask{
  public function __construct($plugin){
    $this->p = $plugin;
  }

  public function onRun($tick){
    $config = new Config($this->p->getDataFolder()."config.yml", Config::YAML);
    $arenalar = $config->get("arenalar");
    if(!empty($arenalar)){
      foreach($arenalar as $arena){
        $sure = $config->get($arena . "OyunSuresi", 780);
        $bsure = $config->get($arena . "BaslamaSuresi", 30);
        $levelArena = $this->p->getServer()->getLevelByName($arena);
        if($levelArena instanceof Level){
          $oarena = $levelArena->getPlayers();
          if(count($oarena)==0){
            $config->set($arena . "OyunSuresi", 780);
            $config->set($arena . "BaslamaSuresi", 30);
          }else{
            if(count($oarena)>=2){
              if($bsure > 0){
                $bsure--;
                foreach ($oarena as $oy) {
                  $oy->sendTip("§aOyunun başlamasına §f$bsure §akaldı.");
                }
                if($bsure <= 0){
                  $this->sandikYenile($levelArena, $oarena);
                }
                $config->set($arena . "BaslamaSuresi", $bsure);
              }else{
                $aop = count($levelArena->getPlayers());
                if($aop<=1){
                  foreach($oarena as $oy){
                    foreach($this->p->getServer()->getOnlinePlayers() as $oyoy){
                      $oyoy->sendMessage("§1> §f" . $oy->getName() . " §1isimli oyuncu §f$arena §1haritasını kazandı.");
                    }
                    $oy->getInventory()->clearAll();
                    $oy->setNameTag($oy->getName());
                    $spawn = $this->p->getServer()->getDefaultLevel()->getSafeSpawn();
                    $this->p->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                    $oy->teleport($spawn, 0, 0);
                    $oy->setHealth(20);
                    $coini = $this->p->coin->get($oy->getName());
                    $this->p->coin->set($oy->getName(), $coini+10);
                    $oy->sendMessage("§1> 10 coin kazandın.");
                    $wini = $this->p->win->get($oy->getName());
                    $this->p->win->set($oy->getName(), $wini+1);
                    $this->p->win->save();
                    $this->p->coin->save();
                    $this->p->mapYenile()->reload($levelArena);
                    $config->set($arena . "OyunSuresi", 780);
                    $config->set($arena . "BaslamaSuresi", 30);
                  }
                }
                if(($aop>=2)){
                  foreach($oarena as $oy){
                    $oy->sendTip("§6Yaşayan oyuncular: $aop");
                  }
                }
                $sure--;
                if($sure == 779){
                  $i = 1;
                  foreach($oarena as $oy){
                    $yerr = $config->get($arena . "Spawn" . $i);
                    $level = $this->p->getServer()->getLevelByName($arena);
                    $spawn = new Position($yerr[0]+0.5,$yerr[1],$yerr[2]+0.5,$level);
                    $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
                    $oy->teleport($spawn, 0, 0);
                    $oy->getInventory()->clearAll();
                    $oy->removeAllEffects();
                    $oy->setHealth(20);
                    $oy->setNameTag("§cOYNANIYOR");
                    $oy->sendMessage("§1> §1Oyun başladı, iyi oyunlar dileriz.");
                    $kitc = new Config($this->p->getDataFolder()."kit.yml", Config::YAML);
                    $kit = $kitc->get($oy->getName());
                    if($kit == "savasci"){
                      $oy->sendMessage("§l§6Kitin başarıyla alındı.");
                      $kitc->set($oy->getName(), "bos");
                      $oy->getInventory()->addItem(Item::get(272, 0, 1));
                      $oy->getInventory()->addItem(Item::get(260, 0, 5));
                      $kitc->save();
                    }elseif($kit == "insaatci"){
                      $oy->sendMessage("§l§6Kitin başarıyla alındı.");
                      $kitc->set($oy->getName(), "bos");
                      $oy->getInventory()->addItem(Item::get(45, 0, 32));
                      $kitc->save();
                    }elseif($kit == "tank"){
                      $oy->sendMessage("§l§6Kitin başarıyla alındı.");
                      $oy->getInventory()->addItem(Item::get(307, 0, 1));
                      $kitc->set($oy->getName(), "bos");
                      $kitc->save();
                    }
                    $i++;
                  }
                }
                if($sure == 765){
                  foreach ($oarena as $oy){
                  $oy->sendMessage("§1> §1Mücadelenin başlamasına 15 saniye var.");
                  }
                }
                if($sure == 750){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Mücadele başladı, elini çabuk tut.");
                  }
                }
                if($sure == 550){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Kalitenin adresi §fSunucu Adı.");
                  }
                }
                if($sure == 480){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Sandıklar yenilendi.");
                  }
                  $this->sandikYenile($levelArena);
                }
                if($sure == 5){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f5 §1saniye.");
                  }
                }
                if($sure == 4){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f4 §1saniye.");
                  }
                }
                if($sure == 3){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f3 §1saniye.");
                  }
                }
                if($sure == 2){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f2 §1saniye.");
                  }
                }
                if($sure == 1){
                  foreach($oarena as $oy){
                    $oy->sendMessage("§1> §1Oyunun bitmesine §f1 §1saniye.");
                  }
                }
                if($sure <= 0){
                  $spawn = $this->p->getServer()->getDefaultLevel()->getSafeSpawn();
                  $this->p->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                  foreach($oarena as $oy){
                    $oy->teleport($spawn, 0, 0);
                    $oy->sendMessage("§1> §f$arena §1mapinde kazanan yok.");
                    $oy->getInventory()->clearAll();
           	        $oy->setHealth(20);
           	        $oy->setNameTag($oy->getName());
                   	$this->p->mapYenile()->reload($levelArena);
                  }
                  $sure = 780;
                }
              }
              $config->set($arena . "OyunSuresi", $sure);
            }else{
              if($bsure <= 0){
                foreach($oarena as $oy){
                  foreach($this->p->getServer()->getOnlinePlayers() as $oyoy){
                    $oyoy->sendMessage("§1> §f" . $oy->getName() . " §1isimli oyuncu §f" . $arena . " §1isimli oyunu kazandı.");
                  }
                  $spawn = $this->p->getServer()->getDefaultLevel()->getSafeSpawn();
                  $this->p->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                  $oy->getInventory()->clearAll();
                  $oy->teleport($spawn,0,0);
                  $oy->setHealth(20);
                  $coini = $this->p->coin->get($oy->getName());
                  $this->p->coin->set($oy->getName(), $coini+10);
                  $oy->sendMessage("§1> 10 coin kazandın.");
                  $wini = $this->p->win->get($oy->getName());
                  $this->p->win->set($oy->getName(), $wini+1);
                  $this->p->win->save();
                  $this->p->coin->save();
                  $this->p->coin->save();
                  $oy->setNameTag($oy->getName());
                  $this->p->mapYenile()->reload($levelArena);
                  }
                  $config->set($arena . "OyunSuresi", 780);
							    $config->set($arena . "BaslamaSuresi", 30);
                }else{
                  foreach ($oarena as $oy) {
                    $oy->sendTip("§cOyuncular bekleniyor..");
                  }
                  $config->set($arena . "OyunSuresi", 780);
							    $config->set($arena . "BaslamaSuresi", 30);
                }
              }
            }
          }
        }
      }
      $config->save();
    }
    public function sandikYenile(Level $level)
    {
      $config = new Config($this->p->getDataFolder() . "/config.yml", Config::YAML);
      $tiles = $level->getTiles();
      foreach($tiles as $t) {
        if($t instanceof Chest)
        {
          $chest = $t;
          $chest->getInventory()->clearAll();
          if($chest->getInventory() instanceof ChestInventory)
          {
            for($i=0;$i<=26;$i++)
            {
              $rand = rand(1,3);
              if($rand==1)
              {
                $k = array_rand($config->get("sandik"));
                $v = $config->get("sandik")[$k];
                $chest->getInventory()->setItem($i, Item::get($v[0],$v[1],$v[2]));
              }
            }
          }
        }
      }
    }
  }
