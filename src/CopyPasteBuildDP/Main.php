<?php

	namespace CopyPasteBuildDP;

	use pocketmine\plugin\PluginBase;
	use pocketmine\utils\Config;
	use pocketmine\math\Vector3;

	use pocketmine\block\Block;
	use pocketmine\block\Air;
	use pocketmine\block\WallSign;
	use pocketmine\block\SignPost;
	use pocketmine\block\Door;
	use pocketmine\block\Chest;
	use pocketmine\block\FlowerPot;

	use pocketmine\tile\Tile;
	use pocketmine\tile\Sign as SignTile;
	use pocketmine\tile\Chest as ChestTile;
	use pocketmine\tile\FlowerPot as PotTile;

	use pocketmine\command\Command;
	use pocketmine\command\CommandSender;

	use pocketmine\level\Level;

	use pocketmine\nbt\tag\CompoundTag;
	use pocketmine\nbt\tag\IntTag;
	use pocketmine\nbt\tag\StringTag;

	use pocketmine\Player;

	use CopyPasteBuildDP\BuildTask;

	class Main extends PluginBase {
		private $position = [
							'x' => [],
							'y' => [],
							'z' => []
						];

		const PREFIX = '§f[§bCopyPasteBuildDP§f]§r';

		public function onEnable() {
			$f = $this->getDataFolder();
			if(!is_dir($f)) {
				@mkdir($f);
				@mkdir($f.'regions');
			}
		}

		public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
			if(!$sender instanceof Player)
				return;

			if(count($args) != 2) {
				$sender->sendMessage($this->help());
				return;
			}

			$folder = $this->getDataFolder();

			switch(mb_strtolower($args[0])) {
				case 'save':
						if(!$sender->hasPermission('cp.save')) {
							$sender->sendmessage(self::PREFIX.' §cBạn Không Có Quyền Thực Hiện Câu Lệnh Này');
							return;
						}

						switch(mb_strtolower($args[1])) {

							case 'pos':
							case 'pos1':
							case 'pos2':
									if(count($this->position['x']) == 2) {
										$sender->sendMessage(self::PREFIX." §cHai Điểm Đã Được Chọn. Nếu Muốn Xóa Sử Dụng Lệnh: §b/cp save reset");
										return;
									}
									$this->position['x'][] = $sender->getFloorX();
									$this->position['y'][] = $sender->getFloorY();
									$this->position['z'][] = $sender->getFloorZ();
									$sender->sendMessage(self::PREFIX." §aĐiểm Đã Được Chọn");
								break;

							case 'reset':
									$this->position = [
										'x' => [],
										'y' => [],
										'z' => []
									];
									$sender->sendMessage(self::PREFIX." §aKhu Vực Được Chọn Đã Được Khởi Động Lại");
								break;

							default: 
									if(count($this->position['x']) != 2) {
										$sender->sendMessage(self::PREFIX." §cVui Lòng Chọn Tọa Độ Khu Vực Để Lưu...");
										return;
									}
									$rgname = mb_strtolower($args[1]);
									if(file_exists($folder.'regions/'.$rgname)) {
										$sender->sendMessage(self::PREFIX." §eTên Khu Vực Đã Tồn Tại. Vui Lòng Đổi Tên Khác");
										return;
									}
									$sender->sendMessage(self::PREFIX." §aĐang Thu Thập Tọa Độ Các Khối...");
									$position = [
										'x' => [
											min($this->position['x'][0], $this->position['x'][1]),
											max($this->position['x'][0], $this->position['x'][1])
										],
										'y' => [
											min($this->position['y'][0], $this->position['y'][1]),
											max($this->position['y'][0], $this->position['y'][1])
										],
										'z' => [
											min($this->position['z'][0], $this->position['z'][1]),
											max($this->position['z'][0], $this->position['z'][1])
										],
									];
									$this->position = ['x' => [], 'y' => [], 'z' => []];
									$level = $sender->getLevel();
									$region = [
										'x'      => $position['x'][1] - $position['x'][0], 
										'y'      => $position['y'][1] - $position['y'][0], 
										'z'      => $position['z'][1] - $position['z'][0], 
										'blocks' => []
									];
									for($x = $position['x'][0]; $x <= $position['x'][1]; $x++)
										for($y = $position['y'][0]; $y <= $position['y'][1]; $y++)
											for($z = $position['z'][0]; $z <= $position['z'][1]; $z++)
												$region['blocks'][] = $level->getBlock(new Vector3($x, $y, $z));
									$sender->sendMessage(self::PREFIX." §aThu Thập Tọa Độ Các Khối Thành Công");
									$sender->sendMessage(self::PREFIX." §aĐang Lưu Lại Khu Vực...");

									$i = 0;
									$block = [];
									foreach($region['blocks'] as $bl) {

										$block[$i] = [
											'id'   => $bl->getId(),
											'meta' => $bl->getDamage()
										];

										if($bl instanceof WallSign or $bl instanceof SignPost) {
											$block[$i]['type'] = 'sign';
											$tile = $level->getTile(new Vector3($bl->x, $bl->y, $bl->z));
											if($tile instanceof SignTile)
												$block[$i]['text'] = $tile->getText();
											else
												$block[$i]['text'] = ['', '', '', ''];
										}
										elseif($bl instanceof Door) {
											$block[$i]['type'] = 'door';
											$block[$i]['meta'] ^= 0x04;
										}
										elseif($bl instanceof Chest) {
											$block[$i]['type'] = 'chest';
											$block[$i]['customname'] = $bl->getName();
										}
										elseif($bl instanceof FlowerPot) {
											$block[$i]['type'] = 'pot';
											$item = $level->getTile($bl)->getItem();
											$block[$i]['item'] = [
												'id' => $item->getId(),
												'meta' => $item->getDamage()
											];
										}
										else 
											$block[$i]['type'] = 'block';

										$i++;
									}
									$region['blocks'] = $block;

									$rg = new Config($folder.'regions/'.$rgname.'.json', Config::JSON);
									$rg->setAll($region);
									$rg->save();

									$sender->sendMessage(self::PREFIX.' §aKhu Vực: '.$rgname.' Đã Được Lưu');

						}

					break;

				case 'paste':
						if(!$sender->hasPermission('cp.paste')) {
							$sender->sendmessage(self::PREFIX.' §cBạn Không Có Quyền Thực Hiện Câu Lệnh Này');
							return;
						}

						$level = $sender->level;
						if(!isset($args[1])) {
							$sender->sendMessage(self::PREFIX." §aSử Dụng: §b/cp paste <Tên Khu Vực>");
							return;
						}
						$rgname = mb_strtolower($args[1]);
						$f = "{$folder}regions/$rgname.json";

						if(!file_exists($f)) {
							$sender->sendMessage(self::PREFIX.' §cTên Khu Vực Này Không Được Lưu');
							return;
						}

						$sender->sendMessage(self::PREFIX.' §aĐang Xây Dựng Khu Vực...');

						$region = new Config($f, Config::JSON);
						$region = $region->getAll();

						$this->getServer()->getScheduler()->scheduleRepeatingTask(new BuildTask($this, $region, $level, $sender), 1);

					break;

				default:
						$sender->sendMessage($this->help());
			}
		}

		public function help() {
			$help = [
				self::PREFIX."§a Cách Dùng:",
				"§b/cp <save> <pos1/pos2> §f- §aChọn Tọa Độ Khu Vực",
				"§b/cp <save> <Tên Khu Vực> §f- §aLưu Khu Vực Đã Chọn",
				"§b/cp <paste> <Tên Khu Vực> §f- §aXây Dựng Khu Vực",
			];
			return implode("\n", $help);
		}

	}

?>