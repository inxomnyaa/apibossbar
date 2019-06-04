<?php

namespace xenialdan\apibossbar;

use pocketmine\plugin\Plugin;

class API{

    /**
     * Needs to be run by plugins using the virion in onEnable(), used to register a listener for BossBarPacket
     * @param Plugin $plugin
     */
    public static function load(Plugin $plugin){
        //Handle packets related to boss bars
        PacketListener::register($plugin);
    }
}