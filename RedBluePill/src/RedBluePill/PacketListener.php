<?php

declare(strict_types=1);

namespace RedBluePill;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

final class PacketListener implements Listener{

    public function __construct(private Main $plugin){}

    public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
        $pk = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        if($player === null){
            return;
        }

        if($pk instanceof ModalFormResponsePacket){
            $this->plugin->handleFormResponse(
                $player,
                $pk->formId,
                $pk->formData
            );
        }
    }
}
