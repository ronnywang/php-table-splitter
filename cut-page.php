<?php

include(__DIR__ . "/TableSplitter.php");

$cell_count = $_SERVER['argv'][1];
$input_files = array_slice($_SERVER['argv'], 2);

if (count($input_files) != array_sum(array_map('file_exists', $input_files))) {
    throw new Exception("請用 php cut-script.php [格數] [檔名, 檔名 ...] 來將一張圖切成多張圖");
}

foreach ($input_files as $input_file) {
    if (!preg_match('#(.*)(\.[^.]*)$#', $input_file, $matches)) {
        throw new Exception("找不到 $input_file 的副檔名");
    }
    $table_splitter = new TableSplitter;
    $table_splitter->_debug = true;
    $gd = $table_splitter->getImageFromFile($input_file);
    $ret = $table_splitter->splitReductionPrintImage($gd, $cell_count, $cell_count);
    var_dump($ret);

    foreach ($ret["found_rects"] as $index => $rect) {
        $croped = imagecrop($gd, $rect);
        $target = "{$matches[1]}-output-{$index}{$matches[2]}";
        imagepng($croped, $target);
        imagedestroy($croped);
    }
    imagedestroy($gd);
}
