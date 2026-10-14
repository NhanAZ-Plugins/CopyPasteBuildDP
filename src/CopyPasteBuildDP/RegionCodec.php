<?php

declare(strict_types=1);

namespace CopyPasteBuildDP;

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use RuntimeException;
use Throwable;

final class RegionCodec{
	public const FORMAT_VERSION = 2;
	public const MAX_BLOCKS = 32768;
	public const MAX_FILE_BYTES = 16777216;

	/** @return array{x: int, y: int, z: int}|null */
	public static function checkedSize(int $x, int $y, int $z) : ?array{
		if($x < 1 || $y < 1 || $z < 1 || $x > self::MAX_BLOCKS || $y > self::MAX_BLOCKS || $z > self::MAX_BLOCKS){
			return null;
		}
		if($x > intdiv(self::MAX_BLOCKS, $y)){
			return null;
		}
		$xy = $x * $y;
		if($xy > intdiv(self::MAX_BLOCKS, $z)){
			return null;
		}
		return ["x" => $x, "y" => $y, "z" => $z];
	}

	public static function isSafeName(string $name) : bool{
		return preg_match('/\A[a-z0-9_-]{1,32}\z/D', $name) === 1;
	}

	public static function encodeBlockState(Block $block) : string{
		$tag = GlobalBlockStateHandlers::getSerializer()->serialize($block->getStateId())->toNbt();
		return base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($tag)));
	}

	public static function encodeTile(?Tile $tile, Block $block) : ?string{
		if($tile === null){
			return null;
		}
		$expectedClass = $block->getIdInfo()->getTileClass();
		if($expectedClass === null || !$tile instanceof $expectedClass){
			throw new RuntimeException("Tile data does not match its block type.");
		}
		TileFactory::getInstance()->getSaveId($expectedClass);
		return base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($tile->saveNBT())));
	}

	/**
	 * @param array<mixed, mixed> $data
	 * @return array{size: array{x: int, y: int, z: int}, blocks: list<array{block: Block, tile: CompoundTag|null}>}
	 */
	public static function decodeRegion(array $data) : array{
		if(array_key_exists("format", $data) && $data["format"] !== self::FORMAT_VERSION){
			throw new RuntimeException("The region format version is not supported.");
		}
		$currentFormat = ($data["format"] ?? null) === self::FORMAT_VERSION;
		if($currentFormat){
			$rawSize = $data["size"] ?? null;
			$rawBlocks = $data["blocks"] ?? null;
			if(!is_array($rawSize) || !isset($rawSize["x"], $rawSize["y"], $rawSize["z"]) ||
				!is_int($rawSize["x"]) || !is_int($rawSize["y"]) || !is_int($rawSize["z"]) ||
				!is_array($rawBlocks) || !array_is_list($rawBlocks)){
				throw new RuntimeException("The region dimensions or block list are invalid.");
			}
			$size = self::checkedSize($rawSize["x"], $rawSize["y"], $rawSize["z"]);
			if($size === null || count($rawBlocks) !== $size["x"] * $size["y"] * $size["z"]){
				throw new RuntimeException("The region dimensions do not match its block data.");
			}
		}else{
			$legacyX = $data["x"] ?? null;
			$legacyY = $data["y"] ?? null;
			$legacyZ = $data["z"] ?? null;
			$rawBlocks = $data["blocks"] ?? null;
			if(!is_int($legacyX) || !is_int($legacyY) || !is_int($legacyZ) ||
				!is_array($rawBlocks) || !array_is_list($rawBlocks)){
				throw new RuntimeException("The region file format is not supported.");
			}
			$size = self::checkedSize($legacyX + 1, $legacyY + 1, $legacyZ + 1);
			if($size === null || count($rawBlocks) !== $size["x"] * $size["y"] * $size["z"]){
				throw new RuntimeException("The legacy region dimensions do not match its block data.");
			}
		}

		$blocks = [];
		foreach($rawBlocks as $rawBlock){
			if(!is_array($rawBlock)){
				throw new RuntimeException("The region contains an invalid block record.");
			}
			if($currentFormat){
				$state = $rawBlock["state"] ?? null;
				$tile = $rawBlock["tile"] ?? null;
				if(!is_string($state) || ($tile !== null && !is_string($tile))){
					throw new RuntimeException("The region contains invalid block state data.");
				}
				$block = self::decodeBlockState($state);
				$tileTag = $tile === null ? null : self::decodeNbt($tile);
			}else{
				$block = self::decodeLegacyBlock($rawBlock);
				$tileTag = self::decodeLegacyTile($rawBlock);
			}
			self::validateTile($block, $tileTag);
			$blocks[] = ["block" => $block, "tile" => $tileTag];
		}

		return ["size" => $size, "blocks" => $blocks];
	}

	/** @param array<mixed, mixed> $record */
	private static function decodeLegacyBlock(array $record) : Block{
		$id = $record["id"] ?? null;
		$meta = $record["meta"] ?? null;
		if(!is_int($id) || !is_int($meta) || $id < 0 || $meta < 0 || $meta > 255){
			throw new RuntimeException("A legacy block has an invalid numeric ID or metadata.");
		}
		if(($record["type"] ?? null) === "door"){
			$meta ^= 0x04;
		}
		try{
			$state = GlobalBlockStateHandlers::getUpgrader()->upgradeIntIdMeta($id, $meta);
			return self::blockFromStateData($state);
		}catch(Throwable $e){
			throw new RuntimeException("A legacy block ID cannot be mapped safely on this Axolotl-PM version.", 0, $e);
		}
	}

	private static function decodeBlockState(string $encoded) : Block{
		$tag = self::decodeNbt($encoded);
		try{
			$state = GlobalBlockStateHandlers::getUpgrader()->upgradeBlockStateNbt($tag);
			return self::blockFromStateData($state);
		}catch(Throwable $e){
			throw new RuntimeException("A saved block state is not supported on this Axolotl-PM version.", 0, $e);
		}
	}

	private static function blockFromStateData(BlockStateData $state) : Block{
		$stateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($state);
		$block = RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId);
		if($block instanceof \pocketmine\block\UnknownBlock){
			throw new RuntimeException("The saved block state is unknown to this server.");
		}
		return $block;
	}

	private static function decodeNbt(string $encoded) : CompoundTag{
		$binary = base64_decode($encoded, true);
		if($binary === false || strlen($binary) > self::MAX_FILE_BYTES){
			throw new RuntimeException("The region contains malformed or oversized NBT data.");
		}
		$offset = 0;
		$root = (new LittleEndianNbtSerializer())->read($binary, $offset, 64);
		if($offset !== strlen($binary)){
			throw new RuntimeException("The region contains trailing bytes after NBT data.");
		}
		return $root->mustGetCompoundTag();
	}

	/** @param array<mixed, mixed> $record */
	private static function decodeLegacyTile(array $record) : ?CompoundTag{
		$type = $record["type"] ?? "block";
		if(!is_string($type)){
			throw new RuntimeException("A legacy block has an invalid tile type.");
		}
		$tag = CompoundTag::create()->setInt(Tile::TAG_X, 0)->setInt(Tile::TAG_Y, 0)->setInt(Tile::TAG_Z, 0);
		switch($type){
			case "sign":
				$expected = \pocketmine\block\tile\Sign::class;
				$lines = $record["text"] ?? null;
				if(!is_array($lines) || count($lines) !== 4){
					throw new RuntimeException("A legacy sign is missing its four text lines.");
				}
				$tag->setString(Tile::TAG_ID, TileFactory::getInstance()->getSaveId($expected));
				for($i = 0; $i < 4; ++$i){
					if(!is_string($lines[$i] ?? null)){
						throw new RuntimeException("A legacy sign contains invalid text.");
					}
					$tag->setString("Text" . ($i + 1), $lines[$i]);
				}
				return $tag;
			case "chest":
				$expected = \pocketmine\block\tile\Chest::class;
				$tag->setString(Tile::TAG_ID, TileFactory::getInstance()->getSaveId($expected));
				if(isset($record["customname"])){
					if(!is_string($record["customname"])){
						throw new RuntimeException("A legacy chest contains an invalid custom name.");
					}
					$tag->setString("CustomName", $record["customname"]);
				}
				return $tag;
			case "pot":
				$expected = \pocketmine\block\tile\FlowerPot::class;
				$item = $record["item"] ?? null;
				if(!is_array($item) || !is_int($item["id"] ?? null) || !is_int($item["meta"] ?? null) ||
					$item["id"] < 0 || $item["id"] > 32767 || $item["meta"] < 0 || $item["meta"] > 255){
					throw new RuntimeException("A legacy flower pot contains invalid item data.");
				}
				$tag->setString(Tile::TAG_ID, TileFactory::getInstance()->getSaveId($expected));
				$tag->setShort("item", $item["id"])->setInt("mData", $item["meta"]);
				return $tag;
			case "block":
			case "door":
				return null;
			default:
				throw new RuntimeException("The region contains an unsupported legacy tile type.");
		}
	}

	private static function validateTile(Block $block, ?CompoundTag $tile) : void{
		if($tile === null){
			return;
		}
		$expectedClass = $block->getIdInfo()->getTileClass();
		if($expectedClass === null){
			throw new RuntimeException("Tile data is attached to a block that cannot contain a tile.");
		}
		try{
			$expectedId = TileFactory::getInstance()->getSaveId($expectedClass);
		}catch(Throwable $e){
			throw new RuntimeException("This block's tile type is not supported by the server.", 0, $e);
		}
		if($tile->getString(Tile::TAG_ID, "") !== $expectedId){
			throw new RuntimeException("The saved tile data does not match its block type.");
		}
	}
}
