<?php

declare(strict_types=1);

require dirname(__DIR__) . "/src/CopyPasteBuildDP/RegionCodec.php";

use CopyPasteBuildDP\RegionCodec;

function assertSameValue(mixed $expected, mixed $actual, string $message) : void{
	if($expected !== $actual){
		throw new RuntimeException($message . ": expected " . var_export($expected, true) . ", got " . var_export($actual, true));
	}
}

assertSameValue(true, RegionCodec::isSafeName("castle-01"), "valid region name");
assertSameValue(true, RegionCodec::isSafeName("a_b"), "underscores are allowed");
assertSameValue(false, RegionCodec::isSafeName("../world"), "path traversal is rejected");
assertSameValue(false, RegionCodec::isSafeName("A"), "uppercase names are rejected");
assertSameValue(false, RegionCodec::isSafeName(str_repeat("a", 33)), "long names are rejected");
assertSameValue(["x" => 1, "y" => 1, "z" => 1], RegionCodec::checkedSize(1, 1, 1), "single block region");
assertSameValue(["x" => 32, "y" => 32, "z" => 32], RegionCodec::checkedSize(32, 32, 32), "maximum cube");
assertSameValue(null, RegionCodec::checkedSize(33, 32, 32), "oversized volume is rejected");
assertSameValue(null, RegionCodec::checkedSize(0, 1, 1), "zero dimensions are rejected");

echo "Region codec safety guards passed.\n";
