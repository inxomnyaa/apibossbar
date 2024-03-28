<?php

namespace xenialdan\apibossbar;

use pocketmine\network\mcpe\protocol\types\BossBarColor;

class BarColor
{
    const PINK = BossBarColor::PINK;
    const BLUE = BossBarColor::BLUE;
    const RED = BossBarColor::RED;
    const GREEN = BossBarColor::GREEN;
    const YELLOW = BossBarColor::YELLOW;
    const PURPLE = BossBarColor::PURPLE;
    const REBECCA_PURPLE = BossBarColor::REBECCA_PURPLE;
    const WHITE = BossBarColor::WHITE;

    /** @var int[] */
    public static array $colors = [
        self::PINK,
        self::BLUE,
        self::RED,
        self::GREEN,
        self::YELLOW,
        self::PURPLE,
        self::REBECCA_PURPLE,
        self::WHITE,
    ];

    /**
     * Get all available boss bar colors.
     *
     * @return int[]
     */
    public static function getColors(): array
    {
        return self::$colors;
    }
}