<?php

declare(strict_types=1);

namespace CopyPasteBuildDP;

use pocketmine\block\Block;
use pocketmine\block\tile\Spawnable;
use pocketmine\block\tile\Tile;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\world\World;
use Throwable;

final class BuildTask extends Task{
	/**
	 * @param array{x: int, y: int, z: int} $size
	 * @param list<array{block: Block, tile: CompoundTag|null}> $blocks
	 */
	public function __construct(
		private Main $plugin,
		private Player $player,
		private World $world,
		private int $startX,
		private int $startY,
		private int $startZ,
		private array $size,
		private array $blocks
	){}

	public function onRun() : void{
		$count = count($this->blocks);
		$sizeY = $this->size["y"];
		$sizeZ = $this->size["z"];

		try{
			for($done = 0; $done < 128 && $this->index < $count; ++$done, ++$this->index){
				$xOffset = intdiv($this->index, $sizeY * $sizeZ);
				$yOffset = intdiv($this->index, $sizeZ) % $sizeY;
				$zOffset = $this->index % $sizeZ;
				$x = $this->startX + $xOffset;
				$y = $this->startY + $yOffset;
				$z = $this->startZ + $zOffset;
				$record = $this->blocks[$this->index];
				$this->world->setBlockAt($x, $y, $z, $record["block"]);

				$tile = $record["tile"];
				if($tile !== null){
					$targetTile = $this->world->getTileAt($x, $y, $z);
					$expectedClass = $record["block"]->getIdInfo()->getTileClass();
					if($targetTile === null || $expectedClass === null || get_class($targetTile) !== $expectedClass){
						throw new \RuntimeException("The server did not create the expected tile for a pasted block.");
					}
					$tile->setInt(Tile::TAG_X, $x)->setInt(Tile::TAG_Y, $y)->setInt(Tile::TAG_Z, $z);
					$targetTile->readSaveData($tile);
					if($targetTile instanceof Spawnable){
						$targetTile->clearSpawnCompoundCache();
						$position = new Vector3($x, $y, $z);
						$this->world->broadcastPacketToViewers(
							$position,
							BlockActorDataPacket::create(BlockPosition::fromVector3($position), $targetTile->getSerializedSpawnCompound())
						);
					}
				}
			}

			if($this->index >= $count){
				if($this->player->isConnected()){
					$this->player->sendMessage(Main::PREFIX . " Region pasted successfully.");
				}
				$this->getHandler()?->cancel();
			}
		}catch(Throwable $e){
			$this->plugin->getLogger()->error("Region paste stopped after " . $this->index . " of " . $count . " blocks: " . $e->getMessage());
			if($this->player->isConnected()){
				$this->player->sendMessage(Main::PREFIX . " Region paste stopped at block " . $this->index . "; check the world and server log.");
			}
			$this->getHandler()?->cancel();
		}
	}

	private int $index = 0;
}
