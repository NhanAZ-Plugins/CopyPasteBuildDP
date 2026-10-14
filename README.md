# CopyPasteBuildDP

CopyPasteBuildDP saves a bounded selection as a region file and pastes it at the player's current block position. It targets Axolotl-PM API 5.0.0 and uses Axolotl-PM's block-state and tile-data formats.

## Commands

- `/cp save pos1` and `/cp save pos2` select the two corners.
- `/cp save reset` clears the selection.
- `/cp save <name>` stores the selected region.
- `/cp paste <name>` pastes the saved region.

Region names are limited to 32 lowercase letters, digits, underscores, or hyphens. A region is limited to 32,768 blocks. Pasting is rejected before it changes blocks if the file is malformed, the target crosses world bounds, or any target chunk is not already loaded. The paste task processes a bounded number of blocks per tick.

Current saves store portable named block-state data and tile NBT. Older JSON regions using numeric block IDs are accepted only when Axolotl-PM can safely map those IDs. A malformed, oversized, unknown, or mismatched region is rejected before paste begins.

## Permissions

`cp.save` and `cp.paste` default to operator. The command can only be used by an in-game player.

## Compatibility and archive status

The plugin manifest requires API 5.0.0. CI type-checks and builds against pinned Axolotl-PM 5.0.0 and 5.49.1 source revisions, and performs an isolated startup smoke test on the pinned 5.49.1 server. These exact checks do not certify every 5.x release or connected-player world editing.

The source retains the `khavmc` author credit and the historical Ghast Noob edit credit. The repository has no LICENSE file or verified distribution grant. No stable release or license was added.

For questions about the NhanAZ ecosystem, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
