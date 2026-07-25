<?php

declare(strict_types=1);

namespace xenialdan\apibossbar;

use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;

class DiverseBossBar extends BossBar{
	private array $titles = [];
	private array $subTitles = [];
	/** @var AttributeMap[] */
	private array $attributeMaps = [];
	private array $colors = [];

	public function __construct(){
		parent::__construct();
	}

	public function addPlayer(Player $player) : static{
		$this->attributeMaps[$player->getId()] = clone parent::getAttributeMap();
		return parent::addPlayer($player);
	}

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

	public function setSubTitleFor(array $players, string $subTitle = "") : static{
		foreach($players as $player){
			$this->subTitles[$player->getId()] = $subTitle;
			$this->sendBossTextPacket([$player]);
		}
		return $this;
	}

	public function getFullTitleFor(Player $player) : string{
		$text = $this->titles[$player->getId()] ?? "";
		if(!empty($this->subTitles[$player->getId()] ?? "")){
			$text .= "\n\n" . $this->subTitles[$player->getId()] ?? "";
		}
		if(empty($text)) $text = $this->getFullTitle();
		return mb_convert_encoding($text, 'UTF-8');
	}

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

	public function showTo(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(
				BossEventPacket::show(
					$this->actorId ?? $player->getId(),
					$this->getFullTitleFor($player),
					$this->getPercentageFor($player),
					false,
					$this->getColorFor($player)
				)
			);
		}
	}

	protected function sendBossPacket(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(
				BossEventPacket::show(
					$this->actorId ?? $player->getId(),
					$this->getFullTitleFor($player),
					$this->getPercentageFor($player),
					false,
					$this->getColorFor($player)
				)
			);
		}
	}

	protected function sendBossTextPacket(array $players) : void{
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$player->getNetworkSession()->sendDataPacket(BossEventPacket::title($this->actorId ?? $player->getId(), $this->getFullTitleFor($player)));
		}
	}

	protected function sendAttributesPacket(array $players) : void{
		if($this->actorId === null) return;
		$pk = new UpdateAttributesPacket();
		$pk->actorRuntimeId = $this->actorId;
		foreach($players as $player){
			if(!$player->isConnected()) continue;
			$pk->entries = $this->getAttributeMap($player)->needSend();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

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