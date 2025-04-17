<?php

namespace xenialdan\apibossbar;

use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;

/**
 * Class DiverseBossBar
 * This Bar should be used if the data is different for each player
 * This means if you want coordinates or player names in the title, you must use this!
 * You can use methods of @see BossBar to set defaults
 * @package xenialdan\apibossbar
 */
class DiverseBossBar extends BossBar{
	private array $titles = [];
	private array $subTitles = [];
	/** @var AttributeMap[] */
	private array $attributeMaps = [];
	private array $colors = [];

	/**
	 * DiverseBossBar constructor.
	 * @see BossBar::__construct
	 * TODO might be useless, remove?
	 */
	public function __construct(){
		parent::__construct();
	}

	public function addPlayer(Player $player) : static{
		$this->attributeMaps[$player->getId()] = clone parent::getAttributeMap();
		return parent::addPlayer($player);
	}

	/**
	 * Removes a single player from this bar.
	 * Use @param Player $player
	 * @return static
	 * @see BossBar::hideFrom() when just removing temporarily to save some performance / bandwidth
	 */
	public function removePlayer(Player $player) : static{
		unset($this->attributeMaps[$player->getId()]);
		return parent::removePlayer($player);
	}

	public function resetFor(Player $player) : static{
		unset($this->attributeMaps[$player->getId()], $this->titles[$player->getId()], $this->subTitles[$player->getId()], $this->colors[$player->getId()]);
		$this->sendBossPacket([$player]);
		return $this;
	}

	public function resetForAll() : static{
		foreach($this->getPlayers() as $player){
			$this->resetFor($player);
		}
		return $this;
	}

	public function getTitleFor(Player $player) : string{
		return $this->titles[$player->getId()] ?? $this->getTitle();
	}

	/**
	 * @param Player[] $players
	 * @param string   $title
	 *
	 * @return static
	 */
	public function setTitleFor(array $players, string $title = "") : static{
		foreach($players as $player){
			$this->titles[$player->getId()] = $title;
			$this->sendBossTextPacket([$player]);
		}
		return $this;
	}

	public function getSubTitleFor(Player $player) : string{
		return $this->subTitles[$player->getId()] ?? $this->getSubTitle();
	}

	/**
	 * @param Player[] $players
	 * @param string   $subTitle
	 *
	 * @return static
	 */
	public function setSubTitleFor(array $players, string $subTitle = "") : static{
		foreach($players as $player){
			$this->subTitles[$player->getId()] = $subTitle;
			$this->sendBossTextPacket([$player]);
		}
		return $this;
	}

	/**
	 * The full title as a combination of the title and its subtitle. Automatically fixes encoding issues caused by newline characters
	 *
	 * @param Player $player
	 *
	 * @return string
	 */
	public function getFullTitleFor(Player $player) : string{
		$text = $this->titles[$player->getId()] ?? "";
		if(!empty($this->subTitles[$player->getId()] ?? "")){
			$text .= "\n\n" . $this->subTitles[$player->getId()] ?? "";//?? "" even necessary?
		}
		if(empty($text)) $text = $this->getFullTitle();
		return mb_convert_encoding($text, 'UTF-8');
	}

	/**
	 * @param Player[] $players
	 * @param float    $percentage 0-1
	 *
	 * @return static
	 */
	public function setPercentageFor(array $players, float $percentage) : static{
		$percentage = (float) min(1.0, max(0.00, $percentage));
		foreach($players as $player){
			$this->getAttributeMap($player)->get(Attribute::HEALTH)->setValue($percentage * $this->getAttributeMap($player)->get(Attribute::HEALTH)->getMaxValue(), true, true);
		}
		$this->sendBossHealthPacket($players);

		return $this;
	}

	public function getPercentageFor(Player $player) : float{
		return $this->getAttributeMap($player)->get(Attribute::HEALTH)->getValue() / $this->getAttributeMap($player)->get(Attribute::HEALTH)->getMaxValue();
	}

	/**
	 * @param Player[] $players
	 * @param int      $color
	 *
	 * @return static
	 */
	public function setColorFor(array $players, int $color) : static{
		foreach($players as $player){
			$this->colors[$player->getId()] = $color;
			$this->sendBossPacket([$player]);
		}
		return $this;
	}

	public function getColorFor(Player $player) : int{
		return $this->colors[$player->getId()] ?? $this->getColor();
	}

	/**
	 * TODO: Only registered players validation
	 * Displays the bar to the specified players
	 *
	 * @param Player[] $players
	 */
	public function showTo(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::show($this->actorId ?? $player->getId(), $this->getFullTitleFor($player), $this->getPercentageFor($player), false, $this->getColorFor($player)));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossPacket(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::show($this->actorId ?? $player->getId(), $this->getFullTitleFor($player), $this->getPercentageFor($player), false, $this->getColorFor($player)));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendBossTextPacket(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::title($this->actorId ?? $player->getId(), $this->getFullTitleFor($player)));
		}
	}

	/**
	 * @param Player[] $players
	 */
	protected function sendAttributesPacket(array $players) : void{//TODO might not be needed anymore
		if($this->actorId === null) return;
		$pk = new UpdateAttributesPacket();
		$pk->actorRuntimeId = $this->actorId;
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$pk->entries = $this->getAttributeMap($player)->needSend();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	/**
	 * @param Player[] $players
	 */
	public function sendBossHealthPacket(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::healthPercent($this->actorId ?? $player->getId(), $this->getPercentageFor($player)));
		}
	}

	public function getAttributeMap(Player $player = null) : AttributeMap{
		$attributeMap = $this->attributeMaps[$player?->getId()] ?? parent::getAttributeMap();
		return $attributeMap;
	}

	public function __toString() : string{
		return __CLASS__ . " ID: $this->actorId, Titles: " . count($this->titles) . ", Subtitles: " . count($this->subTitles) . " [Defaults: " . parent::__toString() . "]";
	}
}