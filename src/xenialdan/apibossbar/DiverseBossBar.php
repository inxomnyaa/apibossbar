<?php

namespace xenialdan\apibossbar;

use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;

/**
 * Class DiverseBossBar
 * This Bar should be used if the data is different for each player
 * This means if you want coordinates or player names in the title, you must use this!
 * You can use methods of @see BossBar to set defaults
 * @package xenialdan\apibossbar
 */
class DiverseBossBar extends BossBar
{
	private array $titles = [];
	private array $subTitles = [];
	/** @var AttributeMap[] */
	private array $attributeMaps = [];

	/**
	 * DiverseBossBar constructor.
	 * @see BossBar::__construct
	 * TODO might be useless, remove?
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
	 * Use @param Player $player
	 * @return BossBar
	 * @see BossBar::hideFrom() when just removing temporarily to save some performance / bandwidth
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
		}
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
		$percentage = (float)min(1.0, max(0.00, $percentage));
		foreach ($players as $player) {
			$this->getAttributeMap($player)->get(Attribute::HEALTH)->setValue($percentage * $this->getAttributeMap($player)->get(Attribute::HEALTH)->getMaxValue(), true, true);
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
		return $this->getAttributeMap($player)->get(Attribute::HEALTH)->getValue() / 100;
	}

	/**
	 * TODO: Only registered players validation
	 * Displays the bar to the specified players
	 * @param Player[] $players
	 */
	public function showTo(array $players): void
	{
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_SHOW;
		foreach ($players as $player) {
			if(!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($this->addDefaults($player, $pk));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossPacket(array $players): void
	{
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_SHOW;
		foreach ($players as $player) {
			if(!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$player->getNetworkSession()->sendDataPacket($this->addDefaults($player, $pk));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossTextPacket(array $players): void
	{
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_TITLE;
		foreach ($players as $player) {
			if(!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$pk->title = $this->getFullTitleFor($player);
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendAttributesPacket(array $players): void
	{//TODO might not be needed anymore
		if ($this->actorId === null) return;
		$pk = new UpdateAttributesPacket();
		$pk->actorRuntimeId = $this->actorId;
		foreach ($players as $player) {
			if(!$player->isConnected()) continue;
			$pk->entries = $this->getAttributeMap($player)->needSend();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossHealthPacket(array $players): void
	{
		$pk = new BossEventPacket();
		$pk->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
		foreach ($players as $player) {
			if(!$player->isConnected()) continue;
			$pk->bossActorUniqueId = $this->actorId ?? $player->getId();
			$pk->healthPercent = $this->getPercentageFor($player);
			$player->getNetworkSession()->sendDataPacket($pk);
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
			return $this->attributeMaps[$player->getId()] ?? parent::getAttributeMap();
		}
		return parent::getAttributeMap();
	}

	public function getPropertyManager(Player $player = null): EntityMetadataCollection
	{
		$propertyManager = /*clone*/
			$this->propertyManager;//TODO check if memleak
		if ($player instanceof Player) $propertyManager->setString(EntityMetadataProperties::NAMETAG, $this->getFullTitleFor($player));
		else $propertyManager->setString(EntityMetadataProperties::NAMETAG, $this->getFullTitle());
		return $propertyManager;
	}

	public function __toString(): string
	{
		return __CLASS__ . " ID: $this->actorId, Titles: " . count($this->titles) . ", Subtitles: " . count($this->subTitles) . " [Defaults: " . parent::__toString() . "]";
	}
}