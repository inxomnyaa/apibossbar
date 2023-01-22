<?php


namespace xenialdan\apibossbar;

use pocketmine\entity\AttributeMap;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataTypes;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;

class BossBar
{
    protected const NETWORK_ID = EntityIds::SLIME;

    /** @var Player[] */
    private $players = [];
    /**
     * @var string
     */
    private $title = "";
    /**
     * @var string
     */
    private $subTitle = "";
    public $entityCount = 1;
    /**
     * @var int|null
     */
    public $entityId = null;
    /** @var AttributeMap */
    private $attributeMap;
    /** @var EntityMetadataCollection */
    protected $propertyManager;

    /**
     * BossBar constructor.
     * This will not spawn the bar, since there would be no players to spawn it to
     */
    public function __construct()
    {
        $this->entityId = Self::$entityCount++;
        $this->attributeMap = new AttributeMap();
        
        $this->getAttributeMap()->add(AttributeFactory::getInstance()->get(Attribute::HEALTH)->setMaxValue(100.0)->setMinValue(0.0)->setDefaultValue(100.0));
        $this->propertyManager = new EntityMetadataCollection();
        $this->propertyManager->setLong(EntityMetadataProperties::FLAGS, 0
            ^ 1 << EntityMetadataFlags::SILENT
            ^ 1 << EntityMetadataFlags::INVISIBLE
            ^ 1 << EntityMetadataFlags::NO_AI
            ^ 1 << EntityMetadataFlags::FIRE_IMMUNE);
        $this->propertyManager->setShort(EntityMetadataProperties::MAX_AIR, 400);
        $this->propertyManager->setString(EntityMetadataProperties::NAMETAG, $this->getFullTitle());
        $this->propertyManager->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, -1);
        $this->propertyManager->setFloat(EntityMetadataProperties::SCALE, 0);
        $this->propertyManager->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);
        $this->propertyManager->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @param Player[] $players
     * @return BossBar
     */
    public function addPlayers(array $players): BossBar
    {
        foreach ($players as $player) {
            $this->addPlayer($player);
        };
        return $this;
    }

    /**
     * @param Player $player
     * @return BossBar
     */
    public function addPlayer(Player $player): BossBar
    {
        if (isset($this->players[$player->getId()])) return $this;
        if (!$this->getEntity() instanceof Player) $this->sendSpawnPacket([$player]);
        $this->sendBossPacket([$player]);
        $this->players[$player->getId()] = $player;
        return $this;
    }

    /**
     * Removes a single player from this bar.
     * Use @see BossBar::hideFrom() when just removing temporarily to save some performance / bandwidth
     * @param Player $player
     * @return BossBar
     */
    public function removePlayer(Player $player): BossBar
    {
        if (!isset($this->players[$player->getId()])) {
            Server::getInstance()->getLogger()->debug("Removed player that was not added to the boss bar (" . $this . ")");
            return $this;
        }
        $this->sendRemoveBossPacket([$player]);
        unset($this->players[$player->getId()]);
        return $this;
    }

    /**
     * @param Player[] $players
     * @return BossBar
     */
    public function removePlayers(array $players): BossBar
    {
        foreach ($players as $player) {
            $this->removePlayer($player);
        };
        return $this;
    }

    /**
     * Removes all players from this bar
     * @return BossBar
     */
    public function removeAllPlayers(): BossBar
    {
        foreach ($this->getPlayers() as $player) $this->removePlayer($player);
        return $this;
    }


    /**
     * The text above the bar
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Text above the bar. Can be empty. Should be single-line
     * @param string $title
     * @return BossBar
     */
    public function setTitle(string $title = ""): BossBar
    {
        $this->title = $title;
        #$this->sendEntityDataPacket($this->getPlayers());
        $this->sendBossTextPacket($this->getPlayers());
        return $this;
    }

    /**
     * @return string
     */
    public function getSubTitle(): string
    {
        return $this->subTitle;
    }

    /**
     * Optional text below the bar. Can be empty
     * @param string $subTitle
     * @return BossBar
     */
    public function setSubTitle(string $subTitle = ""): BossBar
    {
        $this->subTitle = $subTitle;
        #$this->sendEntityDataPacket($this->getPlayers());
        $this->sendBossTextPacket($this->getPlayers());
        return $this;
    }

    /**
     * The full title as a combination of the title and its subtitle. Automatically fixes encoding issues caused by newline characters
     * @return string
     */
    public function getFullTitle(): string
    {
        $text = $this->title;
        if (!empty($this->subTitle)) {
            $text .= "\n\n" . $this->subTitle;
        }
        return mb_convert_encoding($text, 'UTF-8');
    }

    /**
     * @param float $percentage 0-1
     * @return BossBar
     */
    public function setPercentage(float $percentage): BossBar
    {
        $percentage = (float)max(0.0, $percentage);
        $this->getAttributeMap()->get(Attribute::HEALTH)->setValue($percentage* $this->getAttributeMap()->get(Attribute::HEALTH)->getMaxValue(), true, true);
        $this->sendAttributesPacket($this->getPlayers());
        $this->sendBossHealthPacket($this->getPlayers());

        return $this;
    }

    /**
     * @return float
     */
    public function getPercentage(): float
    {
        return $this->getAttributeMap()->get(Attribute::HEALTH)->getValue()/100;
    }

    /**
     * TODO: Only registered players validation
     * Hides the bar from the specified players without removing it.
     * Useful when saving some bandwidth or when you'd like to keep the entity
     * @param Player[] $players
     */
    public function hideFrom(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HIDE;
        Server::getInstance()->broadcastPackets($players, [$pk]);
    }

    /**
     * Hides the bar from all registered players
     */
    public function hideFromAll(): void
    {
        $this->hideFrom($this->getPlayers());
    }

    /**
     * TODO: Only registered players validation
     * Displays the bar to the specified players
     * @param Player[] $players
     */
    public function showTo(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_SHOW;
        Server::getInstance()->broadcastPackets($players, [$this->addDefaults($pk)]);
    }

    /**
     * Displays the bar to all registered players
     */
    public function showToAll(): void
    {
        $this->showTo($this->getPlayers());
    }

    /**
     * @return null|Entity
     */
    public function getEntity(): ?Entity
    {
        return Server::getInstance()->getWorldManager()->findEntity($this->entityId);
    }

    /**
     * STILL TODO, SHOULD NOT BE USED YET
     * @param null|Entity $entity
     * @return BossBar
     * TODO: use attributes and properties of the custom entity
     */
    public function setEntity(?Entity $entity = null): BossBar
    {
        if ($entity instanceof Entity && ($entity->isClosed() || $entity->isFlaggedForDespawn())) throw new \InvalidArgumentException("Entity $entity can not be used since its not valid anymore (closed or flagged for despawn)");
        if ($this->getEntity() instanceof Entity && !$entity instanceof Player) $this->getEntity()->flagForDespawn();
        else {
            $pk = new RemoveActorPacket();
            $pk->entityUniqueId = $this->entityId;
            Server::getInstance()->broadcastPackets($this->getPlayers(), [$pk]);
        }
        if ($entity instanceof Entity) {
            $this->entityId = $entity->getId();
            $this->attributeMap = $entity->getAttributeMap();//TODO try some kind of auto-updating reference
            $this->getAttributeMap()->add($entity->getAttributeMap()->get(Attribute::HEALTH));//TODO Auto-update bar for entity? Would be cool, so the api can be used for actual bosses
            $this->propertyManager = $entity->getNetworkProperties();
            if(!$entity instanceof Player) $entity->despawnFromAll();
        } else {
            $this->entityId = self::$entityCount++;
        }
        if(!$entity instanceof Player) $this->sendSpawnPacket($this->getPlayers());
        $this->sendBossPacket($this->getPlayers());
        return $this;
    }

    /**
     * @param bool $removeEntity Be careful with this. If set to true, the entity will be deleted.
     * @return BossBar
     */
    public function resetEntity(bool $removeEntity = false): BossBar
    {
        if ($removeEntity && $this->getEntity() instanceof Entity && !$this->getEntity() instanceof Player) $this->getEntity()->close();
        return $this->setEntity();
    }

    /**
     * TODO if entity exists and is spawned, don't send again
     * TODO slime is not needed anymore. Use player
     * @param Player[] $players
     */
    protected function sendSpawnPacket(array $players): void
    {
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->type = $this->getEntity()->getNetworkTypeId();
        $pk->attributes = $this->getAttributeMap()->getAll();
        var_dump($this->getPropertyManager()->getAll());
        $pk->metadata = $this->getPropertyManager()->getAll();
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pkc->position = $player->getPosition()->asVector3()->subtract(0, 28, 0);
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    /**
     * @param Player[] $players
     */
    protected function sendBossPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_SHOW;
        $pk->title = $this->getFullTitle();
        $pk->healthPercent = $this->getPercentage();
        Server::getInstance()->broadcastPackets($players, [$this->addDefaults($pk)]);
    }

    /**
     * @param Player[] $players
     */
    protected function sendRemoveBossPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HIDE;
        Server::getInstance()->broadcastPackets($players, [$pk]);
    }

    /**
     * @param Player[] $players
     */
    protected function sendBossTextPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_TITLE;
        $pk->title = $this->getFullTitle();
        Server::getInstance()->broadcastPackets($players, [$pk]);
    }

    /**
     * @param Player[] $players
     */
    protected function sendAttributesPacket(array $players): void
    {
        $pk = new UpdateAttributesPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->entries = $this->getAttributeMap()->needSend();
        Server::getInstance()->broadcastPackets($players, [$pk]);
    }

    /**
     * @param Player[] $players
     *@deprecated
     */
    protected function sendEntityDataPacket(array $players): void
    {
        return;
        $this->getPropertyManager()->setString(EntityMetadataProperties::NAMETAG, $this->getFullTitle());
        $pk = new SetActorDataPacket();
        $pk->metadata = $this->getPropertyManager()->getDirty();
        $pk->entityRuntimeId = $this->entityId;
        Server::getInstance()->broadcastPackets($players, [$pk]);

        $this->getPropertyManager()->clearDirtyProperties();
    }

    /**
     * @param Player[] $players
     */
    protected function sendBossHealthPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
        $pk->healthPercent = $this->getPercentage();
        Server::getInstance()->broadcastPackets($players, [$pk]);
    }

    private function addDefaults(BossEventPacket $pk):BossEventPacket{
        $pk->title = $this->getFullTitle();
        $pk->healthPercent = $this->getPercentage();
        $pk->unknownShort = 1;
        $pk->color = 0;//Does not function anyways
        $pk->overlay = 0;//Neither. Typical for Mojang: Copy-pasted from Java edition
        return $pk;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return __CLASS__ . " ID: $this->entityId, Players: " . count($this->players) . ", Title: \"$this->title\", Subtitle: \"$this->subTitle\", Percentage: \"".$this->getPercentage()."\"";
    }

    /**
     * @param Player|null $player Only used for DiverseBossBar
     * @return AttributeMap
     */
    public function getAttributeMap(Player $player = null): AttributeMap
    {
        return $this->attributeMap;
    }

    /**
     * @return DataPropertyManager
     */
    protected function getPropertyManager(): EntityMetadataCollection
    {
        return $this->propertyManager;
    }

    //TODO callable on client2server register/unregister request
}
