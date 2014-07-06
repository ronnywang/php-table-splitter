<?php

class TableSplitter
{
    public $_debug = false;

    public function getImageFromFile($file)
    {
        if (strpos($file, 'png')) {
            $func = 'imagecreatefrompng';
        } elseif (strpos($file, 'jpg') or strpos($file, 'jpeg')) {
            $func = 'imagecreatefromjpeg';
        } else {
            throw new Exception("必需是 jpeg, jpg 或是 png");
        }

        // 先把圖讀去 GD
        $gd = $func($file);
        return $gd;
    }

    public function countRed($gd, $width, $height, $center_x, $center_y)
    {
        for ($i = 1; true; $i ++) {
            $over = true;
            $x = $center_x - $i;
            $y = $center_y - $i;
            foreach (array(array(0,1),array(1,0),array(0,-1),array(-1,0)) as $way) {
                list($x_delta, $y_delta) = $way;
                for ($j = 0; $j < 2 * $i; $j ++) {
                    $x += $x_delta;
                    $y += $y_delta;

                    if ($x < 0 or $x >= $width) {
                        continue;
                    }
                    if ($y < 0 or $y >= $height) {
                        continue;
                    }

                    if ($this->isColor($gd, $x, $y, 'red')) {
                        $over = false;
                        break;
                    }
                }
            }

            if ($over) {
                break;
            }
        }
        return $i;
    }

    /**
     * Transfer image to black and white 
     * 
     * @param Image $gd 
     * @access public
     * @return Image monochromed image
     */
    public function createMonochromeImage($gd)
    {
        $start = microtime(true);
        $this->debug_log('monochrome start');

        $width = imagesx($gd);
        $height = imagesy($gd);

        if (true) {
            $monochromed_gd = imagecreatetruecolor($width, $height);
            for ($x = $width; $x--;) {
                for ($y = $height; $y--;) {
                    $rgb = imagecolorat($gd, $x, $y);
                    $colors = imagecolorsforindex($gd, $rgb);
                    $gray = ($colors['red'] + $colors['green'] + $colors['blue']) / 3;
                    if ($colors['alpha'] == 127 or $gray < 180) {
                        imagesetpixel($monochromed_gd, $x, $y, 0x000000);
                    } else {
                        imagesetpixel($monochromed_gd, $x, $y, 0xFFFFFF);
                    }
                }
            }
        } else {
            $tmp_gd = imagecreatetruecolor($width, $height);
            imagecopy($tmp_gd, $gd, 0, 0, 0, 0, $width, $height);
            imagefilter($tmp_gd, IMG_FILTER_GRAYSCALE); //first, convert to grayscale 
            imagefilter($tmp_gd, IMG_FILTER_CONTRAST, -255); //then, apply a full contrast 
            $monochromed_gd = imagecreatetruecolor($width, $height);
            imagecopy($monochromed_gd, $tmp_gd, 0, 0, 0, 0, $width, $height);
            imagedestroy($tmp_gd);
        }
        $delta = microtime(true) - $start;
        $this->debug_log("monochrome end, spent: $delta");
        return $monochromed_gd;
    }

    public function isColor($gd, $x, $y, $color)
    {
        $rgb = imagecolorat($gd, $x, $y);
        if (!$colors = imagecolorsforindex($gd, $rgb)) {
            throw new Exception("imagecolorallocate failed");
        }

        switch ($color) {
        case 'blue':
            return $colors['red'] == 0 and $colors['green'] == 0 and $colors['blue'] == 255;
        case 'green':
            return $colors['red'] == 0 and $colors['green'] == 255 and $colors['blue'] == 0;
        case 'red':
            return $colors['red'] == 255 and $colors['green'] == 0 and $colors['blue'] == 0;
        case 'black':
            return $colors['red'] == 0 and $colors['green'] == 0 and $colors['blue'] == 0;
        case 'white':
            return $colors['red'] == 255 and $colors['green'] == 255 and $colors['blue'] == 255;
        case 'white2':
            return ($colors['red'] + $colors['green'] + $colors['blue']) / 3 > 230;
        case 'black2':
            return ($colors['red'] + $colors['green'] + $colors['blue']) / 3 < 23;
        }
    }

    public function searchLineFromPoint($gd, $top_x, $top_y, $min_angle = 0, $max_angle = 90, $angle_chunk = 1000, $skip = 5)
    {
        $this->debug_log("searchLineFromPoint({$top_x}, {$top_y})");
        $max_count = 0;

        $angle_mid = ($min_angle + $max_angle) / 2;
        $angle_delta = ($max_angle - $min_angle) / $angle_chunk;
        $width = imagesx($gd);
        $height = imagesy($gd);

        for ($i = 1; $i < $angle_chunk; $i ++) {
            $angle = $angle_mid + $angle_delta * floor($i / 2) * ($i % 2 ? 1 : -1);
            $theta = deg2rad($angle + 90);
            $r = ($top_y * sin($theta) + $top_x * cos($theta));
            $count = 0;
            $max_point[0] = $max_point[1] = array($top_x, $top_y);
            $no_point_counter = array(0 => 0, 1 => 0);

            for ($pos = 0; true; $pos ++) {
                if ($angle_mid < 45) {
                    $x = $top_x + floor($pos / 2) * (($pos % 2) ? -1 : 1);
                    $y = floor(($r - $x * cos($theta)) / sin($theta));
                } else {
                    $y = $top_y + floor($pos / 2) * (($pos % 2) ? -1: 1);
                    $x = floor(($r - $y * sin($theta)) / (cos($theta)));
                }

                if ($no_point_counter[0] > $skip and $no_point_counter[1] > $skip) {
                    break;
                }

                if ($y < 0 or $y > $height or $x < 0 or $x > $width) {
                    $no_point_counter[$pos % 2] ++;
                    continue;
                }

                // 如果有一個方向已經連續 $skip px 找不到任何東西，視為已經沒有了
                if ($no_point_counter[$pos % 2] > $skip) {
                    continue;
                }

                foreach (range(0, 5) as $range) {
                    if ($angle_mid < 45) {
                        list($sx, $sy) = array($x, $y + $range);
                    } else {
                        list($sx, $sy) = array($x + $range, $y);
                    }
                    if ($sx > $width or $sy > $height) {
                        continue;
                    }
                    if (!$this->isColor($gd, $sx, $sy, 'white')) {
                        $count ++;
                        $no_point_counter[$pos % 2] = 0;
                        $max_point[$pos % 2] = array(floor($x), floor($y));
                    }
                }
                $no_point_counter[$pos % 2] ++;
            }

            if ($count > $max_count) {
                $max_count = $count;
                $answer = array(
                    'theta' => $theta,
                    'score' => $max_count,
                    'points' => $max_point,
                );
            }
        }
        return $answer;
    }

    public function debug_log($str)
    {
        if ($this->_debug) {
            error_log($str);
        }
    }

    /**
     * Split reduction print image with border
     * 
     * @param Image $gd 
     * @param int $rows 
     * @param int $cols 
     * @access public
     * @return array(
     *     "found_rects": [ {x: int, y: int, height: int, width: int} ... ]
     *   )
     */
    public function splitReductionPrintImage($gd, $rows, $cols)
    {
        // 轉成只有黑跟白
        $monochromed_gd = $this->createMonochromeImage($gd);

        $ret = array(
            "found_rects" => array(),
        );
        $red = imagecolorallocate($gd, 255, 0, 0);
        $green = imagecolorallocate($gd, 0, 255, 0);
        $black = imagecolorallocate($gd, 0, 0, 0);
        $white = imagecolorallocate($gd, 255, 255, 255);

        $width = imagesx($gd);
        $height = imagesy($gd);

        $prev_x = array();
        foreach (range(0, $rows - 1) as $y_pos) {
            foreach (range(0, $cols - 1) as $x_pos) {
                $this->debug_log("Finding [$x_pos, $y_pos]");
                $max_point = null;
                $max_count = 0;
                $y = floor(($y_pos * 2 + 1) * $height / $rows / 2);
                if ($prev_x[$y_pos] == -1) {
                    continue;
                }
                // 只需要掃到高度 1/6 的部份就好了
                for ($i = 0; $i < $width / $cols / 2; $i ++) {
                    $found_point = false;
                    // 檢查 -10, 0, 10 三個點，避免正好 $y 的部份遇到斷掉的地方就穿過去了
                    foreach (array(-10, 0, 10) as $y_delta) {
                        $x = $i + intval($prev_x[$y_pos]);
                        $y = floor(($y_pos * 2 + 1) * $height / $rows / 2) + $y_delta;
                        if ($this->isColor($monochromed_gd, $x, $y, 'white')) {
                            continue;
                        }
                        if ($this->isColor($monochromed_gd, $x, $y, 'green')) {
                            continue;
                        }
                        $found_point = array($x, $y);
                    }
                    if (false === $found_point) {
                        continue;
                    }
                    $y = $found_point[1];

                    // 遇到了把他填滿成紅色
                    imagefill($monochromed_gd, $x, $y, $red);

                    // 掃一遍看看總共有多少 pixel 變成紅色
                    $count = $this->countRed($monochromed_gd, $width, $height, $x, $y);
                    $this->debug_log("fill ({$x}, {$y}) $count");

                    if ($count > $max_count) {
                        if ($max_point) {
                            imagefill($monochromed_gd, $max_point[0], $max_point[1], $white);
                        }
                        $max_count = $count;
                        $max_point = array($x, $y);
                        imagefill($monochromed_gd, $x, $y, $green);
                    } else {
                        imagefill($monochromed_gd, $x, $y, $white);
                    }
                    if ($count > 100) {
                        break;
                    }
                }
                if ($i >= $width / 6) {
                    $prev_x[$y_pos] = -1;
                    continue;
                }

                // 已知外框線條有經過 ($x, $y)，利用霍夫轉換找到線條具體位置
                $answer = $this->searchLineFromPoint($monochromed_gd, $x, $y, 88, 92, 400);
                var_dump($answer);
                $max_point = $answer['points'];
                if ($max_point[1][1] < $max_point[0][1]) {
                    list($left_bottom, $left_top) = $max_point;
                } else {
                    list($left_top, $left_bottom) = $max_point;
                }
                if (!$this->isColor($monochromed_gd, $left_bottom[0] + 3, $left_bottom[1] - 3, 'white')) {
                    $left_bottom[1] -= 3;
                    $left_bottom[0] += 3;
                }

                // 找垂直線
                $answer = $this->searchLineFromPoint($monochromed_gd, $left_bottom[0], $left_bottom[1], -2, 2, 300);
                var_dump($answer);
                $max_point = $answer['points'];
                if ($max_point[0][0] > $max_point[1][0]) {
                    $right_bottom = $max_point[0];
                } else {
                    $right_bottom = $max_point[1];
                }
                $right_top = array(
                    $right_bottom[0] - $left_bottom[0] + $left_top[0],
                    $right_bottom[1] - $left_bottom[1] + $left_top[1],
                );

                $prev_x[$y_pos] = max($right_bottom[0], $right_top[0]) + 10;

                $rect = array();
                $rect['x'] = intval(max($left_top[0], $left_bottom[0]) + 10);
                $rect['y'] = intval(max($left_top[1], $right_top[1]) + 10);
                $rect['width'] = intval(min($right_top[0], $right_bottom[0]) - 10 - $rect['x']);
                $rect['height'] = intval(min($right_bottom[1], $left_bottom[1]) - 10 - $rect['y']);
                print_r(array('left_top' => $left_top, 'right_top' => $right_top, 'left_bottom' => $left_bottom, 'right_bottom' => $right_bottom));
                var_dump($rect);

                if ($this->_debug) {
                    imagepng($monochromed_gd, 'tmp.png');
                }
                $ret["found_rects"][] = $rect;

                imagerectangle($monochromed_gd, $rect['x'], $rect['y'], $rect['x'] + $rect['width'], $rect['y'] + $rect['height'], $blue);
                foreach (array($left_top, $left_bottom, $right_top, $right_bottom) as $point) {
                    imagearc($monochromed_gd, $point[0], $point[1], 10, 10, 0, 360, $blue);
                }
            }
        }
        if ($this->_debug) {
            imagepng($monochromed_gd, 'tmp.png');
        }
        imagedestroy($monochromed_gd);
        return $ret;
    }
}
