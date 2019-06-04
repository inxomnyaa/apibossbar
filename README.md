# apibossbar
A simple api virion to create, send, modify and use Minecraft's boss mob indicator bars in Plugins for PocketMine-MP
## Advantages
It is quite easy to use this API
- It uses a single object
- Fluent setters (use multiple functions in 1 line)
- Cleaner code
- Easier to understand
- No worrying about the used entity id
- 2 types of bars (BossBar is same for all players, DiverseBossBar can be modified per player)
- No "API::function()" calls, just object methods
- Changeable entity (Can be used for actual boss mobs i.e.)
## Types
There are 2 types of boss bars.
- BossBar: is used for shared data, so all registered players see the same bar.
- DiverseBossBar: is used for unique data, their data can be changed per player. They can also be set in a batch for multiple players, and will use default values if no specific data is set for a player. The default data is set like on a shared BossBar
## API & usage
A very basic example can be seen here: [BossBarTest](https://github.com/thebigsmileXD/BossBarTest). For a more advanced example you could check out [BossAnnouncement](https://github.com/thebigsmileXD/BossAnnouncement)

Create a new boss bar
```php
/** @var BossBar */
$bar = new BossBar();
```
Set the title and/or subtitle
```php
$bar->setTitle(string $title = "");
$bar->setSubTitle(string $subTitle = "");
```
Set the fill percentage
```php
// Half-filled
$bar->setPercentage(0.5);
```
Add and remove players
```php
// Single
$bar->addPlayer(Player $player);// This will spawn the bar to the player
$bar->removePlayer(Player $player);
// Multiple
/** @var Player[] $players */
$bar->addPlayers(array $players);
$bar->removePlayers(array $players);
$bar->removeAllPlayers();
```
Hide and show bar
```php
/** @var Player[] $players */
$bar->hideFrom(array $players);
$bar->showTo(array $players);
```
Get and set the entity the bar is assigned to
```php
/** @var Entity|Player $entity */
$bar->getEntity();
$bar->setEntity(Entity $entity);
$bar->resetEntity();
```
Single line example
```php
/** @var Player $player */
$player = Server::getInstance()->getPlayerByName("Steve");
/** @var BossBar */
$bar = (new BossBar())->setTitle("Hello world!")->setSubTitle("Foo Bar")->setPercentage(0.5)->addPlayer($player);
```
---
**DiverseBossBar has some additional methods to set data per player:**

Reset the data to its defaults
```php
$bar->resetFor(Player $player);
$bar->resetForAll();
```
Set & get title for players
```php
/** @var Player[] $players */
$bar->setTitleFor(array $players);
$bar->setSubTitleFor(array $players);
$bar->getTitleFor(Player $player);
$bar->getSubTitleFor(Player $player);
$bar->getFullTitleFor(Player $player);// Combined and encoded title & subtitle
```
Set percentage for players
```php
/** @var Player[] $players */
$bar->setPercentageFor(array $players);
$bar->getPercentageFor(Player $player);
```
## Disclaimer & Information
Coded and maintained by XenialDan

Feel free to open issues with suggestions and bug reports. Please leave as much information as possible to help speeding up the debugging of the issues.

This is a full rework of [BossBarAPI](https://github.com/thebigsmileXD/BossBarAPI). Plugins that used this virion should be upgraded to apibossbar ASAP

Colors and overlays do not work due to not being properly implemented in the client (They use data from the resource pack definitions file). #blamemojang for copy-paste leftovers from Minecraft: Java Edition