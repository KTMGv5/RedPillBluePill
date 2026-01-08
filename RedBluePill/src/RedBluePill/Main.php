<?php

declare(strict_types=1);

namespace RedBluePill;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerPostChunkSendEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;

final class Main extends PluginBase implements Listener{

    private const EFFECT_DURATION = 2147483647;

    private Config $data;
    private int $nextFormId = 1;

    /** @var array<string, array<int, callable>> */
    private array $formHandlers = [];

    /** @var array<string, TaskHandler> */
    private array $sparkleTasks = [];

    /** @var array<string, bool> */
    private array $introStarted = [];

    public function onEnable() : void{
        @mkdir($this->getDataFolder());
        $this->data = new Config($this->getDataFolder() . "choices.yml", Config::YAML);

        $pm = $this->getServer()->getPluginManager();
        $pm->registerEvents($this, $this);
        $pm->registerEvents(new PacketListener($this), $this);
    }

    /* -------------------- EVENTS -------------------- */

    public function onJoin(PlayerJoinEvent $event) : void{
        $p = $event->getPlayer();
        $key = strtolower($p->getName());

        if($this->data->exists($key)){
            $choice = (string) $this->data->get($key);
            ($choice === "red") ? $this->applyRedPill($p) : $this->applyBluePill($p);
            $this->startSparkles($p, $choice);
        }
    }

    public function onPostChunkSend(PlayerPostChunkSendEvent $event) : void{
        $p = $event->getPlayer();
        $key = strtolower($p->getName());

        if($this->data->exists($key)) return;
        if(isset($this->introStarted[$key])) return;

        $this->introStarted[$key] = true;

        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(fn() => $p->isOnline() && $this->runCinematicIntro($p)),
            20
        );
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $key = strtolower($event->getPlayer()->getName());

        if(isset($this->sparkleTasks[$key])){
            $this->sparkleTasks[$key]->cancel();
            unset($this->sparkleTasks[$key]);
        }

        unset($this->formHandlers[$key], $this->introStarted[$key]);
    }

    public function onRespawn(PlayerRespawnEvent $event) : void{
        $p = $event->getPlayer();
        $key = strtolower($p->getName());

        if(!$this->data->exists($key)) return;

        $choice = (string) $this->data->get($key);
        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(fn() => $p->isOnline() &&
                ($choice === "red" ? $this->applyRedPill($p) : $this->applyBluePill($p))
            ),
            1
        );
    }

    /* -------------------- INTRO -------------------- */

    private function runCinematicIntro(Player $p) : void{
        $p->sendTitle("§l§eHello!", "§fWelcome to our world", 10, 40, 10);
        $this->playSound($p, "random.levelup", 0.5, 1.2);

        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($p) : void{
                if(!$p->isOnline()) return;
                $p->sendTitle("§l§bMagic is Awakening", "§3Something special is happening...", 0, 20, 10);
                $this->playSound($p, "random.click", 0.6, 0.8);
                $p->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), 30));
            }),
            40
        );

        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($p) : void{
                if(!$p->isOnline()) return;
                $p->sendTitle("§l§fThe Big Choice", "§7Choose your magic", 10, 30, 10);
            }),
            80
        );

        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(fn() => $p->isOnline() && $this->sendPillForm($p)),
            120
        );
    }

    /* -------------------- PARTICLES -------------------- */

    private function getPillParticle(string $type) : Particle{
    return $type === "red"
        ? new HappyVillagerParticle()
        : new HeartParticle();
}


    private function spawnPillParticles(Player $p, string $type) : void{
        $particle = $this->getPillParticle($type);
        $center = $p->getPosition();
        $world = $p->getWorld();

        for($i = 0; $i < 12; $i++){
            $this->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($world, $center, $particle, $i) : void{
                    $a = ($i / 12) * 2 * M_PI;
                    $world->addParticle(
                        $center->add(cos($a) * 1.1, 0.6, sin($a) * 1.1),
                        $particle
                    );
                }),
                $i
            );
        }
    }

    private function startSparkles(Player $p, string $type) : void{
        $key = strtolower($p->getName());

        if(isset($this->sparkleTasks[$key])){
            $this->sparkleTasks[$key]->cancel();
            unset($this->sparkleTasks[$key]);
        }

        $particle = $this->getPillParticle($type);

        $this->sparkleTasks[$key] = $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() use ($p, $particle) : void{
                if(!$p->isOnline()) return;
                $p->getWorld()->addParticle(
                    $p->getPosition()->add(mt_rand(-5, 5) / 10, 1.2, mt_rand(-5, 5) / 10),
                    $particle
                );
            }),
            40
        );
    }

    /* -------------------- FORM -------------------- */

    private function sendPillForm(Player $p) : void{
        $this->sendJsonForm($p, [
            "type" => "form",
            "title" => "§l§dChoose Your Magic ✨",
            "content" => "§7This choice is special — and permanent",
            "buttons" => [
                ["text" => "§c Red Pill\n§7Fast & Fearless"],
                ["text" => "§9 Blue Pill\n§7Strong & Safe"]
            ]
        ], function(Player $p, $res) : void{
            if($res === null){
                $this->getScheduler()->scheduleDelayedTask(
                    new ClosureTask(fn() => $p->isOnline() && $this->sendPillForm($p)),
                    20
                );
                return;
            }

            $choice = ($res === 0) ? "red" : "blue";
            ($choice === "red") ? $this->applyRedPill($p) : $this->applyBluePill($p);
            $this->startSparkles($p, $choice);
        });

        $this->playSound($p, "random.orb", 0.5, 1.4);
    }

    /* -------------------- POWERS -------------------- */

    private function applyEffect(Player $p, $effect, int $amp = 0) : void{
        $p->getEffects()->add(new EffectInstance($effect, self::EFFECT_DURATION, $amp));
    }

    private function applyRedPill(Player $p) : void{
        $p->getEffects()->clear();
        $p->setMaxHealth(16);
        $p->setHealth(16);

        $p->sendTitle("§l§cBRAVE EXPLORER", "§fYou are fast and fearless", 10, 40, 10);
        $this->spawnPillParticles($p, "red");

        $this->applyEffect($p, VanillaEffects::SPEED(), 1);
        $this->applyEffect($p, VanillaEffects::NIGHT_VISION());
        $this->applyEffect($p, VanillaEffects::FIRE_RESISTANCE());

        $this->saveChoice($p, "red");
    }

    private function applyBluePill(Player $p) : void{
        $p->getEffects()->clear();
        $p->setMaxHealth(40);
        $p->setHealth(40);

        $p->sendTitle("§l§9UNSTOPPABLE HERO", "§fYou are safe and strong", 10, 40, 10);
        $this->spawnPillParticles($p, "blue");

        $this->applyEffect($p, VanillaEffects::RESISTANCE(), 1);
        $this->applyEffect($p, VanillaEffects::SATURATION());
        $this->applyEffect($p, VanillaEffects::REGENERATION());

        $this->saveChoice($p, "blue");
    }

    /* -------------------- RESET -------------------- */

    private function resetChoice(Player $p) : void{
        $key = strtolower($p->getName());

        if(isset($this->sparkleTasks[$key])){
            $this->sparkleTasks[$key]->cancel();
            unset($this->sparkleTasks[$key]);
        }

        $p->getEffects()->clear();
        $p->setMaxHealth(20);
        $p->setHealth(20);

        $this->data->remove($key);
        $this->data->save();

        $p->sendTitle("§l§dChoice Reset", "§fYou can choose again!", 10, 40, 10);
        $this->playSound($p, "random.orb", 0.6, 1.2);

        unset($this->introStarted[$key]);

        $this->getScheduler()->scheduleDelayedTask(
            new ClosureTask(fn() => $p->isOnline() && $this->runCinematicIntro($p)),
            20
        );
    }

    /* -------------------- COMMAND -------------------- */

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() !== "redochoice"){
            return false;
        }

        if(!$sender instanceof Player){
            $sender->sendMessage("Run this command in-game.");
            return true;
        }

        if(!$sender->hasPermission("redbluepill.redo")){
            $sender->sendMessage("§cOnly parents can do that!");
            return true;
        }

        $this->resetChoice($sender);
        return true;
    }

    /* -------------------- STORAGE -------------------- */

    private function saveChoice(Player $p, string $choice) : void{
        $this->data->set(strtolower($p->getName()), $choice);
        $this->data->save();
        $this->playSound($p, "random.levelup", 0.7, 1.0);
    }

    /* -------------------- FORM CORE -------------------- */

    public function sendJsonForm(Player $p, array $json, callable $cb) : void{
        if($this->nextFormId > 0x7FFFFFFF){
            $this->nextFormId = 1;
        }

        $id = $this->nextFormId++;
        $this->formHandlers[strtolower($p->getName())][$id] = $cb;

        $p->getNetworkSession()->sendDataPacket(
            ModalFormRequestPacket::create($id, json_encode($json))
        );
    }

    public function handleFormResponse(Player $p, int $id, ?string $json) : void{
        $key = strtolower($p->getName());
        if(!isset($this->formHandlers[$key][$id])) return;

        $cb = $this->formHandlers[$key][$id];
        unset($this->formHandlers[$key][$id]);

        $cb($p, ($json !== null && $json !== "null") ? json_decode($json, true) : null);
    }

    /* -------------------- SOUND -------------------- */

    private function playSound(Player $p, string $name, float $vol = 1.0, float $pitch = 1.0) : void{
        $pos = $p->getPosition();
        $p->getNetworkSession()->sendDataPacket(
            PlaySoundPacket::create($name, $pos->getX(), $pos->getY(), $pos->getZ(), $vol, $pitch)
        );
    }
}
