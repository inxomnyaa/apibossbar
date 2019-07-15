<?php


namespace xenialdan\apibossbar;


use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\DataPropertyManager;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\Player;

/**
 * Class DiverseBossBar
 * This Bar should be used if the data is different for each player
 * You can use methods of @see BossBar to set defaults
 * @package xenialdan\apibossbar
 */
class DiverseBossBar extends BossBar
{
    private $titles = [];
    private $subTitles = [];
    /** @var AttributeMap[] */
    private $attributeMaps = [];

    /**
     * DiverseBossBar constructor.
     * @see BossBar::__construct
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param Player $player
     * @return BossBar
     */
    public function addPlayer(Player $player): BossBar
    {
        $this->attributeMaps[$player->getId()] = clone parent::getAttributeMap();
        return parent::addPlayer($player);
    }

    /**
     * Removes a single player from this bar.
     * Use @see BossBar::hideFrom() when just removing temporarily to save some performance / bandwidth
     * @param Player $player
     * @return BossBar
     */
    public function removePlayer(Player $player): BossBar
    {
        unset($this->attributeMaps[$player->getId()]);
        return parent::removePlayer($player);
    }

    public function resetFor(Player $player): DiverseBossBar
    {
        unset($this->attributeMaps[$player->getId()], $this->titles[$player->getId()], $this->subTitles[$player->getId()]);
        $this->sendAttributesPacket([$player]);
        $this->sendBossPacket([$player]);
        return $this;
    }

    public function resetForAll(): DiverseBossBar
    {
        foreach ($this->getPlayers() as $player) {
            $this->resetFor($player);
        };
        return $this;
    }

    public function getTitleFor(Player $player): string
    {
        return $this->titles[$player->getId()] ?? $this->getTitle();
    }

    /**
     * @param Player[] $players
     * @param string $title
     * @return DiverseBossBar
     */
    public function setTitleFor(array $players, string $title = ""): DiverseBossBar
    {
        foreach ($players as $player) {
            $this->titles[$player->getId()] = $title;
            $this->sendEntityDataPacket([$player]);
            $this->sendBossTextPacket([$player]);
        }
        return $this;
    }

    public function getSubTitleFor(Player $player): string
    {
        return $this->subTitles[$player->getId()] ?? $this->getSubTitle();
    }

    /**
     * @param Player[] $players
     * @param string $subTitle
     * @return DiverseBossBar
     */
    public function setSubTitleFor(array $players, string $subTitle = ""): DiverseBossBar
    {
        foreach ($players as $player) {
            $this->subTitles[$player->getId()] = $subTitle;
            $this->sendEntityDataPacket([$player]);
            $this->sendBossTextPacket([$player]);
        }
        return $this;
    }

    /**
     * The full title as a combination of the title and its subtitle. Automatically fixes encoding issues caused by newline characters
     * @param Player $player
     * @return string
     */
    public function getFullTitleFor(Player $player): string
    {
        $text = $this->titles[$player->getId()] ?? "";
        if (!empty($this->subTitles[$player->getId()] ?? "")) {
            $text .= "\n\n" . $this->subTitles[$player->getId()] ?? "";//?? "" even necessary?
        }
        if (empty($text)) $text = $this->getFullTitle();
        return mb_convert_encoding($text, 'UTF-8');
    }

    /**
     * @param Player[] $players
     * @param float $percentage 0-1
     * @return DiverseBossBar
     */
    public function setPercentageFor(array $players, float $percentage): DiverseBossBar
    {
        $percentage = (float)max(0.01, $percentage);
        foreach ($players as $player) {
            $this->getAttributeMap($player)->getAttribute(Attribute::HEALTH)->setValue($percentage * $this->getAttributeMap($player)->getAttribute(Attribute::HEALTH)->getMaxValue(), true, true);
        }
        $this->sendAttributesPacket($players);
        $this->sendBossHealthPacket($players);

        return $this;
    }

    /**
     * @param Player $player
     * @return float
     */
    public function getPercentageFor(Player $player): float
    {
        return $this->getAttributeMap($player)->getAttribute(Attribute::HEALTH)->getValue() / 100;
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
        foreach ($players as $player) {
            $player->sendDataPacket($this->addDefaults($player, clone $pk));
        }
    }

    /**
     * @param Player[] $players
     */
    protected function sendSpawnPacket(array $players): void
    {
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->type = $this->getEntity() instanceof Entity ? $this->getEntity()::NETWORK_ID : static::NETWORK_ID;
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pkc->attributes = $this->getAttributeMap($player)->getAll();
            $pkc->metadata = $this->getPropertyManager($player)->getAll();
            #var_dump($this->getPropertyManager()->getAll());
            $pkc->position = $player->asVector3()->subtract(0, 28);
            $player->dataPacket($pkc);
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
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pkc->title = $this->getFullTitleFor($player);
            $pkc->healthPercent = $this->getPercentageFor($player);
            $player->dataPacket($this->addDefaults($player, $pkc));
        }
    }

    /**
     * @param Player[] $players
     */
    protected function sendBossTextPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_TITLE;
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pkc->title = $this->getFullTitleFor($player);
            $player->dataPacket($pkc);
        }
    }

    /**
     * @param Player[] $players
     */
    protected function sendAttributesPacket(array $players): void
    {
        $pk = new UpdateAttributesPacket();
        $pk->entityRuntimeId = $this->entityId;
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pk->entries = $this->getAttributeMap($player)->needSend();
            $player->dataPacket($pkc);
        }
    }

    /**
     * @param Player[] $players
     */
    protected function sendEntityDataPacket(array $players): void
    {
        $pk = new SetActorDataPacket();
        $pk->entityRuntimeId = $this->entityId;
        foreach ($players as $player) {
            //$this->getPropertyManager($player)->setString(Entity::DATA_NAMETAG, $this->getFullTitleFor($player));
            $pkc = clone $pk;
            $pkc->metadata = $this->getPropertyManager($player)->getDirty();
            $player->dataPacket($pkc);

            //$this->getPropertyManager($player)->clearDirtyProperties();
        }
    }

    /**
     * @param Player[] $players
     */
    protected function sendBossHealthPacket(array $players): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
        foreach ($players as $player) {
            $pkc = clone $pk;
            $pkc->healthPercent = $this->getPercentageFor($player);
            $player->dataPacket($pkc);
        }
    }

    private function addDefaults(Player $player, BossEventPacket $pk): BossEventPacket
    {
        $pk->title = $this->getFullTitleFor($player);
        $pk->healthPercent = $this->getPercentageFor($player);
        $pk->unknownShort = 1;
        $pk->color = 0;//Does not function anyways
        $pk->overlay = 0;//neither. Typical for Mojang: Copy-pasted from Java edition
        return $pk;
    }

    public function getAttributeMap(Player $player = null): AttributeMap
    {
        if ($player instanceof Player) {
            $attributeMap = $this->attributeMaps[$player->getId()] ?? parent::getAttributeMap();
        } else $attributeMap = parent::getAttributeMap();
        return $attributeMap;
    }

    public function getPropertyManager(Player $player = null): DataPropertyManager
    {
        $propertyManager = clone $this->propertyManager;//TODO check if memleak
        if ($player instanceof Player) $propertyManager->setString(Entity::DATA_NAMETAG, $this->getFullTitleFor($player));
        else $propertyManager->setString(Entity::DATA_NAMETAG, $this->getFullTitle());
        return $propertyManager;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return __CLASS__ . " ID: $this->entityId, Titles: " . count($this->titles) . ", Subtitles: " . count($this->subTitles) . " [Defaults: " . parent::__toString() . "]";
    }
}