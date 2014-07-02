<?php

include(__DIR__ . "/TableSplitter.php");

$input_file = $_SERVER['argv'][1];
if (!file_exists($input_file)) {
    throw new Exception("請用 php cut-9.php [檔名] 來將一張圖切成多張圖");
}

$table_splitter = new TableSplitter;
$table_splitter->_debug = true;
$gd = $table_splitter->getImageFromFile($input_file);
$ret = $table_splitter->splitReductionPrintImage($gd, 3, 3);

foreach ($ret["found_rects"] as $index => $rect) {
    $croped = imagecrop($gd, $rect);
    imagepng($croped, __DIR__ . "/output-{$index}.png");
    imagedestroy($croped);
}
imagedestroy($gd);
