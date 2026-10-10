# CopyPasteBuildDP

CopyPasteBuildDP is a historical PocketMine-MP plugin for saving a selected region to JSON and pasting its blocks into a world.

## Compatibility and status

The manifest declares PocketMine-MP API `2.0.0`, `3.0.0`, `3.0.1` and `3.0.0-ALPHA7`. The source uses numeric block IDs, legacy tile NBT, `Level` and `PluginTask`. It has not been ported to Axolotl-PM 5. Do not run it against a real world or use old region files without a migration test.

## Historical behavior and data risk

The `/cp` command selects two points, saves block and tile data under the plugin data folder, then pastes it near the player through a scheduled task. A future port needs path validation for region names, bounds checks for large selections and saved data, block and tile migration, and disposable world tests before it can change live terrain.

The manifest credits khavmc and records an edit by Ghast Noob. Preserve both credits. There is no LICENSE file, CI workflow, DevTools PHAR, Axolotl-PM 5 runtime test or verified current release. Resolve redistribution rights with the rights holders before publishing a modified build.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
