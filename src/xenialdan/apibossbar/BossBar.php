<?php

namespace xenialdan\apibossbar;

use GlobalLogger;
use InvalidArgumentException;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;
use pocketmine\Server;

class BossBar
{
	/** @var Player[] */
	private array $players = [];
	private string $title = "";
	private string $subTitle = "";
	private int $color = BossBarColor::PURPLE;
	public ?int $actorId = null;
	private AttributeMap $attributeMap;
	protected EntityMetadataCollection $propertyManager;

	/**
	 * BossBar constructor.
	 * This will not spawn the bar, since there would be no players to spawn it to
	 */
	public function __construct(){
		$attributeMap = new AttributeMap();
		$this->setAttributeMap($attributeMap);
		/** @var AttributeFactory $attributeFactory */
		$attributeFactory = AttributeFactory::getInstance();
		$this->getAttributeMap()->add($attributeFactory->mustGet(Attribute::HEALTH)->setMaxValue(100.0)->setMinValue(0.0)->setDefaultValue(100.0));
	}

	/**
	 * Creates a new bossbar for an entity
	 *
	 * @param Entity $entity
	 *
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function createForEntity(Entity $entity) : self{
		return (new self())->setEntity($entity);
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
	 *
	 * @return static
	 */
	public function addPlayers(array $players) : static{
		foreach($players as $player){
			$this->addPlayer($player);
		}
		return $this;
	}

	public function addPlayer(Player $player) : static{
		if(isset($this->players[$player->getId()])) return $this;
		#if (!$this->getEntity() instanceof Player) $this->sendSpawnPacket([$player]);
		$this->sendBossPacket([$player]);
		$this->players[$player->getId()] = $player;
		return $this;
	}

	/**
	 * Removes a single player from this bar.
	 * Use @param Player $player
	 * @return static
	 * @see BossBar::hideFrom() when just removing temporarily to save some performance / bandwidth
	 */
	public function removePlayer(Player $player) : static{
		if(!isset($this->players[$player->getId()])){
			GlobalLogger::get()->debug("Removed player that was not added to the boss bar (" . $this . ")");
			return $this;
		}
		$this->sendRemoveBossPacket([$player]);
		unset($this->players[$player->getId()]);
		return $this;
	}

	/**
	 * @param Player[] $players
	 * @return static
	 */
	public function removePlayers(array $players) : static{
		foreach($players as $player){
			$this->removePlayer($player);
		}
		return $this;
	}

	/**
	 * Removes all players from this bar
	 * @return static
	 */
	public function removeAllPlayers() : static{
		foreach($this->getPlayers() as $player) $this->removePlayer($player);
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
	 * @return static
	 */
	public function setTitle(string $title = "") : static{
		$this->title = $title;
		$this->sendBossTextPacket($this->getPlayers());
		return $this;
	}

	public function getSubTitle(): string
	{
		return $this->subTitle;
	}

	/**
	 * Optional text below the bar. Can be empty
	 * @param string $subTitle
	 * @return static
	 */
	public function setSubTitle(string $subTitle = "") : static{
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
	 * @return static
	 */
	public function setPercentage(float $percentage) : static{
		$percentage = (float) min(1.0, max(0.0, $percentage));
		$this->getAttributeMap()->get(Attribute::HEALTH)->setValue($percentage * $this->getAttributeMap()->get(Attribute::HEALTH)->getMaxValue(), true, true);
		#$this->sendAttributesPacket($this->getPlayers());
		$this->sendBossHealthPacket($this->getPlayers());

		return $this;
	}

	public function getPercentage() : float{
		return $this->getAttributeMap()->get(Attribute::HEALTH)->getValue() / $this->getAttributeMap()->get(Attribute::HEALTH)->getMaxValue();
	}

	public function getColor() : int{
		return $this->color;
	}

	public function setColor(int $color) : static{
		$this->color = $color;
		$this->sendBossPacket($this->getPlayers());

		return $this;
	}

	/**
	 * TODO: Only registered players validation
	 * Hides the bar from the specified players without removing it.
	 * Useful when saving some bandwidth or when you'd like to keep the entity
	 *
	 * @param Player[] $players
	 */
	public function hideFrom(array $players) : void{
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->actorId ?? $player->getId()));
		}
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
		$this->sendBossPacket($players);
	}

	/**
	 * Displays the bar to all registered players
	 */
	public function showToAll(): void
	{
		$this->showTo($this->getPlayers());
	}

	public function getEntity(): ?Entity
	{
		if ($this->actorId === null) return null;
		return Server::getInstance()->getWorldManager()->findEntity($this->actorId);
	}

	/**
	 * STILL TODO, SHOULD NOT BE USED YET
	 * @param null|Entity $entity
	 * @return static
	 * TODO: use attributes and properties of the custom entity
	 */
	public function setEntity(?Entity $entity = null) : static{
		if($entity instanceof Entity && ($entity->isClosed() || $entity->isFlaggedForDespawn())) throw new InvalidArgumentException("Entity $entity can not be used since its not valid anymore (closed or flagged for despawn)");
		if($this->getEntity() instanceof Entity && !$entity instanceof Player) $this->getEntity()->flagForDespawn();
		elseif($this->actorId !== null){
			$pk = new RemoveActorPacket();
			$pk->actorUniqueId = $this->actorId;
			NetworkBroadcastUtils::broadcastPackets($this->getPlayers(), [$pk]);
		}
		if($entity instanceof Entity){
			$this->actorId = $entity->getId();
			$attributeMap = $entity->getAttributeMap();
			$this->setAttributeMap($attributeMap);
		} else {
			$this->actorId = Entity::nextRuntimeId();
		}
		#if (!$entity instanceof Player) $this->sendSpawnPacket($this->getPlayers());
		$this->sendBossPacket($this->getPlayers());
		return $this;
	}

	/**
	 * @param bool $removeEntity Be careful with this. If set to true, the entity will be deleted.
	 * @return static
	 */
	public function resetEntity(bool $removeEntity = false) : static{
		if($removeEntity && $this->getEntity() instanceof Entity && !$this->getEntity() instanceof Player) $this->getEntity()->close();
		return $this->setEntity();
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossPacket(array $players): void
	{
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::show($this->actorId ?? $player->getId(), $this->getFullTitle(), $this->getPercentage(), false, $this->getColor()));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendRemoveBossPacket(array $players): void
	{
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->actorId ?? $player->getId()));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossTextPacket(array $players): void
	{
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::title($this->actorId ?? $player->getId(), $this->getFullTitle()));
		}
	}

	/**
	 * @param Player[] $players
	 */
	public function sendBossHealthPacket(array $players) : void
	{
		foreach ($players as $player) {
			if (!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::healthPercent($this->actorId ?? $player->getId(), $this->getPercentage()));
		}
	}

	public function __toString(): string{
		return __CLASS__ . " ID: $this->actorId, Players: " . count($this->players) . ", Title: \"$this->title\", Subtitle: \"$this->subTitle\", Percentage: \"" . $this->getPercentage() . "\", Color: \"" . $this->color . "\"";
	}

	/**
	 * @param Player|null $player Only used for DiverseBossBar
	 * @return AttributeMap
	 */
	public function getAttributeMap(Player $player = null): AttributeMap
	{
		return $this->attributeMap;
	}

	public function setAttributeMap(AttributeMap &$attributeMap) : void{
		$this->attributeMap = &$attributeMap;
	}

	//TODO callable on client2server register/unregister request
}
