<?php

include(__DIR__ . "/TableSplitter.php");

$input_file = $_SERVER['argv'][1];
if (!file_exists($input_file)) {
    throw new Exception("請用 php cut-cell.php [檔名] 來將一張圖的格子切出來");
}

$table_splitter = new TableSplitter;
$table_splitter->_debug = true;
$gd = $table_splitter->getImageFromFile($input_file);
$ret = $table_splitter->findCellsFromImage($gd);
file_put_contents('tmp.json', json_encode($ret));
