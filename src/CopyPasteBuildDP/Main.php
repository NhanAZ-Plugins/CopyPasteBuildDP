<?php

declare(strict_types=1);

namespace CopyPasteBuildDP;

use pocketmine\block\tile\Tile;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use RuntimeException;
use Throwable;

final class Main extends PluginBase{
	public const PREFIX = "§f[§bCopyPasteBuildDP§f]§r";
	private const MAX_DIMENSION = RegionCodec::MAX_BLOCKS;

	/** @var array<string, array{first: array{x: int, y: int, z: int, worldId: int}|null, second: array{x: int, y: int, z: int, worldId: int}|null}> */
	private array $selections = [];

	protected function onEnable() : void{
		$regionsPath = $this->getDataFolder() . "regions" . DIRECTORY_SEPARATOR;
		if(!is_dir($regionsPath) && !mkdir($regionsPath, 0770, true) && !is_dir($regionsPath)){
			throw new RuntimeException("Could not create the region data directory.");
		}
	}

	/** @param list<string> $args */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(self::PREFIX . " §cThis command can only be used in-game.");
			return true;
		}

		$action = strtolower($args[0] ?? "");
		if($action === "save"){
			if(!$sender->hasPermission("cp.save")){
				$sender->sendMessage(self::PREFIX . " §cBạn Không Có Quyền Thực Hiện Câu Lệnh Này");
				return true;
			}
			$this->handleSave($sender, array_slice($args, 1));
			return true;
		}
		if($action === "paste"){
			if(!$sender->hasPermission("cp.paste")){
				$sender->sendMessage(self::PREFIX . " §cBạn Không Có Quyền Thực Hiện Câu Lệnh Này");
				return true;
			}
			$this->handlePaste($sender, array_slice($args, 1));
			return true;
		}

		$sender->sendMessage($this->help());
		return true;
	}

	/** @param list<string> $args */
	private function handleSave(Player $player, array $args) : void{
		if(count($args) !== 1){
			$player->sendMessage($this->help());
			return;
		}
		$playerKey = strtolower($player->getName());
		$selection = $this->selections[$playerKey] ?? ["first" => null, "second" => null];
		$point = strtolower($args[0]);
		if($point === "reset"){
			unset($this->selections[$playerKey]);
			$player->sendMessage(self::PREFIX . " §aKhu Vực Được Chọn Đã Được Khởi Động Lại");
			return;
		}
		if($point === "pos" || $point === "pos1" || $point === "pos2"){
			$slot = $point === "pos2" ? "second" : "first";
			$position = $player->getPosition();
			$selection[$slot] = ["x" => $position->getFloorX(), "y" => $position->getFloorY(), "z" => $position->getFloorZ(), "worldId" => $player->getWorld()->getId()];
			$this->selections[$playerKey] = $selection;
			$player->sendMessage(self::PREFIX . " §aĐiểm Đã Được Chọn");
			return;
		}
		if(!RegionCodec::isSafeName($point)){
			$player->sendMessage(self::PREFIX . " §cTên khu vực chỉ được dùng chữ thường, số, dấu gạch ngang hoặc gạch dưới (tối đa 32 ký tự).");
			return;
		}
		if($selection["first"] === null || $selection["second"] === null){
			$player->sendMessage(self::PREFIX . " §cVui Lòng Chọn Hai Tọa Độ Bằng /cp save pos1 Và /cp save pos2.");
			return;
		}
		if($selection["first"]["worldId"] !== $player->getWorld()->getId() || $selection["second"]["worldId"] !== $player->getWorld()->getId()){
			$player->sendMessage(self::PREFIX . " §cHai điểm phải nằm trong cùng thế giới hiện tại.");
			return;
		}

		$minX = min($selection["first"]["x"], $selection["second"]["x"]);
		$minY = min($selection["first"]["y"], $selection["second"]["y"]);
		$minZ = min($selection["first"]["z"], $selection["second"]["z"]);
		$size = RegionCodec::checkedSize(
			abs($selection["first"]["x"] - $selection["second"]["x"]) + 1,
			abs($selection["first"]["y"] - $selection["second"]["y"]) + 1,
			abs($selection["first"]["z"] - $selection["second"]["z"]) + 1
		);
		if($size === null){
			$player->sendMessage(self::PREFIX . " §cKhu vực vượt giới hạn lưu an toàn " . self::MAX_DIMENSION . " khối.");
			return;
		}
		$path = $this->regionPath($point);
		if(file_exists($path)){
			$player->sendMessage(self::PREFIX . " §eTên Khu Vực Đã Tồn Tại. Vui Lòng Đổi Tên Khác");
			return;
		}

		$world = $player->getWorld();
		$records = [];
		try{
			for($x = 0; $x < $size["x"]; ++$x){
				for($y = 0; $y < $size["y"]; ++$y){
					for($z = 0; $z < $size["z"]; ++$z){
						$block = $world->getBlockAt($minX + $x, $minY + $y, $minZ + $z);
						$tile = $world->getTileAt($minX + $x, $minY + $y, $minZ + $z);
						$records[] = [
							"state" => RegionCodec::encodeBlockState($block),
							"tile" => RegionCodec::encodeTile($tile, $block)
						];
					}
				}
			}
			$json = json_encode(["format" => RegionCodec::FORMAT_VERSION, "size" => $size, "blocks" => $records], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
			if(strlen($json) > RegionCodec::MAX_FILE_BYTES){
				throw new RuntimeException("The region file exceeds the safe storage limit.");
			}
			$tempPath = $path . ".tmp";
			if(file_put_contents($tempPath, $json, LOCK_EX) === false || !rename($tempPath, $path)){
				if(is_file($tempPath)){
					unlink($tempPath);
				}
				throw new RuntimeException("Could not write the region file.");
			}
		}catch(Throwable $e){
			$this->getLogger()->error("Failed to save region '" . $point . "': " . $e->getMessage());
			$player->sendMessage(self::PREFIX . " §cKhông thể lưu khu vực an toàn: " . $e->getMessage());
			return;
		}

		unset($this->selections[$playerKey]);
		$player->sendMessage(self::PREFIX . " §aKhu Vực: " . $point . " Đã Được Lưu (" . count($records) . " khối)");
	}

	/** @param list<string> $args */
	private function handlePaste(Player $player, array $args) : void{
		if(count($args) !== 1 || !RegionCodec::isSafeName(strtolower($args[0]))){
			$player->sendMessage(self::PREFIX . " §aSử Dụng: §b/cp paste <Tên Khu Vực>");
			return;
		}
		$name = strtolower($args[0]);
		$path = $this->regionPath($name);
		if(!is_file($path)){
			$player->sendMessage(self::PREFIX . " §cTên Khu Vực Này Không Được Lưu");
			return;
		}
		$fileSize = filesize($path);
		if($fileSize === false || $fileSize < 2 || $fileSize > RegionCodec::MAX_FILE_BYTES){
			$player->sendMessage(self::PREFIX . " §cTệp khu vực rỗng hoặc vượt giới hạn an toàn.");
			return;
		}
		$content = file_get_contents($path);
		if($content === false){
			$player->sendMessage(self::PREFIX . " §cKhông thể đọc tệp khu vực.");
			return;
		}

		try{
			$data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
			if(!is_array($data) || array_is_list($data)){
				throw new RuntimeException("The region root must be an object.");
			}
			$region = RegionCodec::decodeRegion($data);
		}catch(Throwable $e){
			$player->sendMessage(self::PREFIX . " §cTệp khu vực không hợp lệ: " . $e->getMessage());
			return;
		}

		$world = $player->getWorld();
		$origin = $player->getPosition();
		$startX = $origin->getFloorX();
		$startY = $origin->getFloorY();
		$startZ = $origin->getFloorZ();
		$size = $region["size"];
		$loadedChunks = [];
		for($x = 0; $x < $size["x"]; ++$x){
			for($y = 0; $y < $size["y"]; ++$y){
				for($z = 0; $z < $size["z"]; ++$z){
					$targetX = $startX + $x;
					$targetY = $startY + $y;
					$targetZ = $startZ + $z;
					if(!$world->isInWorld($targetX, $targetY, $targetZ)){
						$player->sendMessage(self::PREFIX . " §cPhần xây dựng nằm ngoài giới hạn thế giới; chưa thay đổi khối nào.");
						return;
					}
					$chunkX = $targetX >> 4;
					$chunkZ = $targetZ >> 4;
					$chunkKey = $chunkX . ":" . $chunkZ;
					if(!isset($loadedChunks[$chunkKey])){
						if(!$world->isChunkLoaded($chunkX, $chunkZ)){
							$player->sendMessage(self::PREFIX . " §cKhu vực đích có chunk chưa tải; hãy đến gần khu vực cần dán trước.");
							return;
						}
						$loadedChunks[$chunkKey] = true;
					}
				}
			}
		}

		$task = new BuildTask($this, $player, $world, $startX, $startY, $startZ, $size, $region["blocks"]);
		$this->getScheduler()->scheduleRepeatingTask($task, 1);
		$player->sendMessage(self::PREFIX . " §aĐang dán khu vực an toàn...");
	}

	private function regionPath(string $name) : string{
		return $this->getDataFolder() . "regions" . DIRECTORY_SEPARATOR . $name . ".json";
	}

	private function help() : string{
		return implode("\n", [
			self::PREFIX . "§a Cách Dùng:",
			"§b/cp save pos1 §f- §aChọn điểm đầu khu vực",
			"§b/cp save pos2 §f- §aChọn điểm cuối khu vực",
			"§b/cp save reset §f- §aXóa hai điểm đã chọn",
			"§b/cp save <tên> §f- §aLưu khu vực đã chọn",
			"§b/cp paste <tên> §f- §aDán khu vực tại vị trí hiện tại"
		]);
	}
}
