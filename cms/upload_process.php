<?php
// 不需要重複載入 connect2data.php，因為 detail.php 已經載入過了
// 如果直接執行此檔案，則需要載入
if (!isset($conn)) {
    require_once '../Connections/connect2data.php';
}

function image_process(PDO $pdo, $FILES_A, $file_title, $file_name, $deal_type, $image_width, $image_height, $multiple = 0) {
    ////echo count($FILES_A['name']);//上傳物件數量
    ////echo count($_REQUEST[image_title]);//上傳物件的說明之數量
    //echo "imageH = ".$image_height."<br/>";
    $all_image_name = array(); //建立回傳的資料陣列
    $no_image = 0;

    //******如果是插入記錄的上傳圖片begin*******/
    if ($deal_type == "add") {

        // pdo 已經是不同的了所以 lastInsertId 不能用只好用這樣
        $sql_max_pic = "SELECT MAX(file_id) FROM file_set";
        $sth = $pdo->query($sql_max_pic)->fetch();
        $new_pic_num = $sth[0] + 1;

    }
    //******如果是插入記錄的上傳圖片end*******/

    //******如果是更新記錄的上傳圖片begin*******/
    if ($deal_type == "edit") {

        $new_pic_num = $_POST['file_id'];

        ////echo $new_pic_num;

    }
    //******如果是更新記錄的上傳圖片end*******/

    //******如果是更新記錄的上傳圖片begin*******/
    if ($deal_type == "multiple") {

        // pdo 已經是不同的了所以 lastInsertId 不能用只好用這樣
        $sql_max_pic = "SELECT MAX(file_id) FROM file_set";
        $sth = $pdo->query($sql_max_pic)->fetch();
        $new_pic_num = $sth[0] + 1;

    }
    //******如果是更新記錄的上傳圖片end*******/

    $successCount = 0; // 成功處理的檔案計數器

    foreach ($FILES_A['name'] as $j => $tmp_name_val) {
        // 檢查 PHP 上傳錯誤 - 跳過沒有檔案的欄位 (UPLOAD_ERR_NO_FILE = 4)
        if ($FILES_A['error'][$j] === UPLOAD_ERR_NO_FILE) {
            continue; // 跳過空欄位，繼續處理後面的檔案
        }

        if ($FILES_A['error'][$j] !== UPLOAD_ERR_OK) {
            continue; // 跳過其他錯誤
        }

        if (!empty($FILES_A['tmp_name'][$j]) && @is_uploaded_file($FILES_A['tmp_name'][$j]))
        {
            $image_path = "upload_image";
            check_path($image_path);

            $image_path .= "/" . $file_name;
            check_path($image_path);

            $file_parts = explode(".", $FILES_A['name'][$j]);
            $image_type = strtolower(end($file_parts));

            if (in_array($image_type, ["jpg", "jpeg", "gif", "bmp", "png", "tif"])) {

                // 先增加計數器，確保每個檔案都有唯一的編號
                $successCount++;
                $photo_name = md5($file_name . $new_pic_num + $successCount . microtime() . $j . uniqid());
                $FILES_A['name'][$j] = str_replace(" ", "", $FILES_A['name'][$j]);

                $size = @getimagesize($FILES_A['tmp_name'][$j]);
                if (!$size) {
                    $successCount--; // 如果失敗，減回計數器
                    continue;
                }

                $orginal_width = $size[0];
                $orginal_height = $size[1];
                $MAX_ALLOWED_WIDTH = 2560;

                if ($image_width == 0)
                {
                    $image_width = $orginal_width;
                    $image_height = $orginal_height;
                }
                else
                {
                    if ($orginal_width > $MAX_ALLOWED_WIDTH) {
                        $ratio = $MAX_ALLOWED_WIDTH / $orginal_width;
                        $image_width = $MAX_ALLOWED_WIDTH;
                        $image_height = round($orginal_height * $ratio);
                    }
                    elseif ($orginal_width < $image_width) {
                        // Keep requested $image_width and $image_height
                    }
                    else {
                        $image_width = $orginal_width;
                        $image_height = $orginal_height;
                    }
                }

                $image_path_new = "../" . $image_path;
                $this_path1 = $image_path . "/" . $file_name . "_" . $photo_name . "." . $image_type;
                $this_image_path1 = "../" . $this_path1;

                if (copy($FILES_A['tmp_name'][$j], $this_image_path1)) {
                    $srcSize = getimagesize($this_image_path1);

                    if ($srcSize[0] > $image_width) {
                        imagesResize_5($this_image_path1, $this_image_path1, $image_width, $image_height);
                    }

                    $format = ($srcSize[0] >= $srcSize[1]) ? 1 : 0;

                    $this_path2 = $image_path . "/" . $file_name . "_" . $photo_name . "_s100." . $image_type;
                    $this_image_path2 = "../" . $this_path2;
                    copy($FILES_A['tmp_name'][$j], $this_image_path2);
                    $destW = 100;
                    $destH = 66;
                    imagesResize_4($this_image_path2, $this_image_path2, $destW, $destH);

                    $destW = 460;
                    $destH = 265;

                    $this_path3 = $image_path . "/" . $file_name . "_" . $photo_name . "_s" . intval($destW) . "." . $image_type;
                    $this_image_path3 = "../" . $this_path3;
                    copy($FILES_A['tmp_name'][$j], $this_image_path3);
                    imagesResize_4($this_image_path3, $this_image_path3, $destW, $destH);

                    $db_file_name = $file_name . "_" . $photo_name . "." . $image_type;

                    // 使用連續的計數器作為 key（已經在前面增加過了）
                    $key = $successCount;
                    $all_image_name[$key][0] = $db_file_name;
                    $all_image_name[$key][1] = $this_path1;
                    $all_image_name[$key][2] = $this_path2;
                    $all_image_name[$key][3] = $this_path3;
                    $all_image_name[$key][4] = (isset($file_title[$j])) ? $file_title[$j] : (is_array($file_title) ? (reset($file_title) ?: '') : $file_title);
                    $all_image_name[$key][5] = $format;

                }
            } else
            {
                $no_image = 1;
            }
        }
    }

    $all_image_name[0][0] = ($no_image == 1) ? 1 : 0;
    return $all_image_name;
}

function file_process(PDO $pdo, $FILES_A, $file_title, $file_name, $deal_type, $accept = null) {
    $all_file_name = array();
    $no_file = 0;

    // 解析 accept 參數
    $acceptFormat = '*';
    $maxSize = 0; // 0 表示不限制 (MB)

    if (is_array($accept)) {
        $acceptFormat = $accept['format'] ?? '*';
        $maxSize = $accept['maxSize'] ?? 0;
    } elseif (is_string($accept)) {
        $acceptFormat = $accept;
    }

    // 根據 acceptFormat 動態生成允許的副檔名
    $allowed_extensions = [];

    if ($acceptFormat !== '*' && $acceptFormat !== 'image/*') {
        // 解析 acceptFormat (例如: ".pdf,.jpg,.png")
        $allowed_extensions = array_map(function($ext) {
            return strtolower(trim(str_replace('.', '', $ext)));
        }, explode(',', $acceptFormat));
    } elseif ($acceptFormat === 'image/*') {
        // 如果是 image/*，允許所有常見圖片格式
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    }

    if ($deal_type == "add" || $deal_type == "multiple") {
        $sql_max_pic = "SELECT MAX(file_id) FROM file_set";
        $sth = $pdo->query($sql_max_pic)->fetch();
        $new_pic_num = $sth[0] + 1;
    }

    if ($deal_type == "edit") {
        $new_pic_num = $_POST['file_id'] ?? 0;
    }

    $successCount = 0; // 成功處理的檔案計數器

    foreach ($FILES_A['name'] as $j => $tmp_name_val) {
        // 檢查 PHP 上傳錯誤 - 跳過沒有檔案的欄位 (UPLOAD_ERR_NO_FILE = 4)
        if ($FILES_A['error'][$j] === UPLOAD_ERR_NO_FILE) {
            continue; // 跳過空欄位，繼續處理後面的檔案
        }

        if ($FILES_A['error'][$j] !== UPLOAD_ERR_OK) {
            continue; // 跳過其他錯誤
        }

        if (!empty($FILES_A['tmp_name'][$j]) && @is_uploaded_file($FILES_A['tmp_name'][$j])) {
            // ---------------------------------------------------------
            // ⭐ (1) 檔案大小驗證
            // ---------------------------------------------------------
            if ($maxSize > 0) {
                $fileSize = $FILES_A['size'][$j];
                $maxSizeBytes = $maxSize * 1024 * 1024; // 轉換為 bytes

                if ($fileSize > $maxSizeBytes) {
                    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                    die("檔案「{$FILES_A['name'][$j]}」大小超過限制！最大允許：{$maxSize}MB，檔案大小：{$fileSizeMB}MB");
                }
            }

            $file_path = "upload_file";
            check_path($file_path);

            $file_path .= "/" . $file_name;
            check_path($file_path);

            $file_parts = explode(".", $FILES_A['name'][$j]);
            $file_type = strtolower(end($file_parts));

            // ---------------------------------------------------------
            // ⭐ (2) 檔案格式驗證
            // ---------------------------------------------------------
            if (!empty($allowed_extensions) && !in_array($file_type, $allowed_extensions)) {
                die("不允許的檔案類型：.{$file_type}。允許的格式：{$acceptFormat}");
            }

            // 先增加計數器，確保每個檔案都有唯一的編號
            $successCount++;
            $new_file_name = md5($file_name . $new_pic_num + $successCount . time() . $j) . "." . $file_type;
            $FILES_A['name'][$j] = str_replace(" ", "", $FILES_A['name'][$j]);

            $this_path = $file_path . "/" . $file_name . "_" . $new_file_name;
            $this_file_path = "../" . $this_path;

            if (copy($FILES_A['tmp_name'][$j], $this_file_path)) {
                // 使用連續的計數器作為 key（已經在前面增加過了）
                $key = $successCount;
                $all_file_name[$key][0] = $new_file_name;
                $all_file_name[$key][1] = $this_path;
                $all_file_name[$key][2] = (isset($file_title[$j])) ? $file_title[$j] : (is_array($file_title) ? (reset($file_title) ?: '') : $file_title);
            } else {
                $successCount--; // 如果複製失敗，減回計數器
                $no_file = 1;
            }
        }
    }

    $all_file_name[0][0] = $no_file;
    return $all_file_name;
}

function check_path($image_path) {

    if (!is_dir("../" . $image_path)) //如果沒有資料夾
    {
        mkdir("../" . $image_path); //產生資料夾
    } else {
        //dont do thing
    }
}

//ini_set("memory_limit","100M");
//依設定尺寸裁切
function imagesResize($src, $dest, $destW, $destH) {
    if (file_exists($src) && isset($dest)) {
        //取得檔案資訊
        $srcSize = getimagesize($src);
        /*
         * 這裡$srcSize為一個數組類型
         * $srcSize[0] 為圖像的寬度
         * $srcSize[1] 為圖像的高度
         * $srcSize[2] 為圖像的格式，包括jpg、gif和png等
         * $srcSize[3] 為圖像的寬度和高度，內容為 width="xxx" height="yyy"
         */

        //判斷橫式或直式圖
        $format = 0;
        if ($srcSize[0] >= $srcSize[1]) {
            //横式圖或正方形圖
            $format = 1;
        } elseif ($srcSize[0] < $srcSize[1]) {
            //直式圖
            $format = 0;
        }

        if ($format) {

            //依width為主 W /H
            $srcRatio = $srcSize[0] / $srcSize[1];

            //echo "横式圖或正方形圖<br>";
            if (($srcSize[0] > $destW) && ($srcSize[1] > $destH)) {

                //echo "W > , H > | ";

                //依長寬比判斷長寬像素
                $destH_2 = $destW / $srcRatio;
                $destRatio = $srcSize[0] / $destW;
                ////echo "destW = ".$destW."<br/>";
                ////echo "destH = ".$destH."<br/>";
                //echo "destH_2 = ".$destH_2."<br/>";
                if ($destH_2 > $destH) {
                    //依hight為主 H/W
                    $srcRatio = $srcSize[1] / $srcSize[0];
                    $destW_2 = $destH / $srcRatio;
                    $destRatio = $srcSize[1] / $destH;
                    $destW = $destW_2;
                    //$destH = $destH_2;
                    //echo "大於<br/>";
                    //$disX = ($destW_2 - $destW)/2;
                    //$disY = ($destH - $destH)/2;
                } else {
                    $destH = $destH_2;
                    //echo "小於<br/>";
                    //$disX = ($destW - $destW)/2;
                    //$disY = ($destH_2 - $destH)/2;
                }

                $srcW = $destW * $destRatio;
                $srcH = $destH * $destRatio;
                $srcX = abs(($srcSize[0] - $srcW)) / 2;
                $srcY = abs(($srcSize[1] - $srcH)) / 2;

            } elseif (($srcSize[0] > $destW) && ($srcSize[1] <= $destH)) {

                //echo "W > , H <= | ";
                //依長寬比判斷長寬像素
                $destH = $destW / $srcRatio;
                $destRatio = $srcSize[0] / $destW;

                $srcW = $destW * $destRatio;
                $srcH = $destH * $destRatio;
                $srcX = abs(($srcSize[0] - $srcW)) / 2;
                $srcY = abs(($srcSize[1] - $srcH)) / 2;

            } elseif (($srcSize[0] <= $destW) && ($srcSize[1] > $destH)) {

                //echo "W <= , H > | ";
                $srcRatio = $srcSize[1] / $srcSize[0];
                $destW_2 = $destH / $srcRatio;
                $destRatio = $srcSize[1] / $destH;
                $destW = $destW_2;

                $srcW = $destW * $destRatio;
                $srcH = $destH * $destRatio;
                $srcX = abs(($srcSize[0] - $srcW)) / 2;
                $srcY = abs(($srcSize[1] - $srcH)) / 2;

            } elseif (($srcSize[0] <= $destW) && ($srcSize[1] <= $destH)) {

                //echo "W <= , H <= | ";
                //$srcRatio = $destRatio = $srcSize[1] / $srcSize[0];
                $destW = $srcSize[0];
                $destH = $srcSize[1];

                $srcW = $destW;
                $srcH = $destH;
                $srcX = 0;
                $srcY = 0;

            }

        } else {

            //echo "直式圖<br>";
            //依hight為主 H/W
            $srcRatio = $srcSize[1] / $srcSize[0];
            $destW = $destH;

            if (($srcSize[1] > $destH) && ($srcSize[0] > $destW)) {

                $destW_2 = $destH / $srcRatio;
                $destRatio = $srcSize[1] / $destH;
                $destW = $destW_2;

                $srcW = $destW * $destRatio;
                $srcH = $destH * $destRatio;
                $srcX = abs(($srcSize[0] - $srcW)) / 2;
                $srcY = abs(($srcSize[1] - $srcH)) / 2;

            } elseif (($srcSize[1] > $destH) && ($srcSize[0] <= $destW)) {

                $destW_2 = $destH / $srcRatio;
                $destRatio = $srcSize[1] / $destH;
                $destW = $destW_2;

                $srcW = $destW * $destRatio;
                $srcH = $destH * $destRatio;
                $srcX = abs(($srcSize[0] - $srcW)) / 2;
                $srcY = abs(($srcSize[1] - $srcH)) / 2;

            }if (($srcSize[1] <= $destH) && ($srcSize[0] <= $destW)) {

                //$srcRatio = $destRatio = $srcSize[1] / $srcSize[0];
                $destW = $srcSize[0];
                $destH = $srcSize[1];

                $srcW = $destW;
                $srcH = $destH;
                $srcX = 0;
                $srcY = 0;

            }

        }

        $srcExtension = $srcSize[2]; //格式

        /*$srcRatio  = $srcSize[0] / $srcSize[1]; //依width為主 W /H
        //依長寬比判斷長寬像素

        $destH_2 = $destW / $srcRatio;
        $destRatio = $srcSize[0] / $destW;
        ////echo "destW = ".$destW."<br/>";
        ////echo "destH = ".$destH."<br/>";
        ////echo "destH_2 = ".$destH_2."<br/>";
        if($destH_2<$destH){//依hight為主
        $srcRatio  = $srcSize[1] / $srcSize[0];
        $destW_2 = $destH / $srcRatio;
        $destRatio = $srcSize[1] / $destH;

        ////echo "大於<br/>";
        //$disX = ($destW_2 - $destW)/2;
        //$disY = ($destH - $destH)/2;
        }else{
        //$destH = $destH_2;
        ////echo "小於<br/>";
        //$disX = ($destW - $destW)/2;
        //$disY = ($destH_2 - $destH)/2;
        }*/

        //$srcX = $destW - $disX;
        //$srcY = $destH - $disY;
        //$srcX = ($destW*$srcRatio_B)/2;
        //$srcY = ($destH*$srcRatio_B)/2;

        /*$srcW = $destW*$destRatio;
    $srcH = $destH*$destRatio;
    $srcX = abs(($srcSize[0] - $srcW))/2;
    $srcY = abs(($srcSize[1] - $srcH))/2;*/
    }
    //echo "<br/>srcRatio = $srcRatio<br/>";
    //echo "destRatio = $destRatio<br>";
    //echo "destW = ".$destW."<br/>";
    //echo "destH = ".$destH."<br/>";
    /*echo "srcW = ".$srcW."<br/>";
    echo "srcH = ".$srcH."<br/>";
    echo "srcX = ".$srcX."<br/>";
    echo "srcY = ".$srcY."<br/><br/>";*/
    //建立影像
    $destImage = imagecreatetruecolor($destW, $destH);
    ////echo "destImage = ".$destImage."<br/>";
    //根據檔案格式讀取圖檔
    switch ($srcExtension) {
    case 1:$srcImage = imagecreatefromgif($src);
        break;
    case 2:$srcImage = imagecreatefromjpeg($src);
        break;
    case 3:$srcImage = imagecreatefrompng($src);
        break;
    }

    //有處理透明背景
    if (($srcExtension == 1) || ($srcExtension == 3)) {
        $transparency = imagecolortransparent($srcImage);

        // If we have a specific transparent color
        if ($transparency >= 0) {
            // Get the original image's transparent color's RGB values
            $transparent_color = imagecolorsforindex($srcImage, $transparency);
            // Allocate the same color in the new image resource
            $transparency = imagecolorallocate($destImage, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
            // Completely fill the background of the new image with allocated color.
            imagefill($destImage, 0, 0, $transparency);
            // Set the background color for new image to transparent
            imagecolortransparent($destImage, $transparency);
        }
        // Always make a transparent background color for PNGs that don't have one allocated already
        elseif ($srcExtension == 3) {

            // Turn off transparency blending (temporarily)
            imagealphablending($destImage, false);
            // Create a new transparent color for image
            $color = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            // Completely fill the background of the new image with allocated color.
            imagefill($destImage, 0, 0, $color);
            // Restore transparency blending
            imagesavealpha($destImage, true);
        }
    }

    //取樣縮圖
    $srcW = $srcSize[0];
    $srcH = $srcSize[1];
    $srcX = 0;
    $srcY = 0;
    
    imagecopyresampled($destImage, $srcImage, 0, 0, $srcX, $srcY, $destW, $destH, $srcW, $srcH);
    //imagefilledrectangle($destImage, 0, 0, $destW, $destH, $white_color);

    //輸出圖檔
    switch ($srcExtension) {
    case 1:imagegif($destImage, $dest);
        break;
    case 2:imagejpeg($destImage, $dest, 100);
        break;
    case 3:imagepng($destImage, $dest);
        break;

        //釋放資源
        imagedestroy($destImage);
    }
}

//縮小圖專用,依設定尺寸
function imagesResize_4($src, $dest, $destW, $destH) {
    if (file_exists($src) && isset($dest)) {
        //取得檔案資訊
        $srcSize = getimagesize($src);
        $srcExtension = $srcSize[2];
        $srcRatio = $srcSize[0] / $srcSize[1]; 

        $destH_2 = $destW / $srcRatio;
        $destRatio = $srcSize[0] / $destW;

        if ($destH_2 < $destH) {
            $srcRatio = $srcSize[1] / $srcSize[0];
            $destW_2 = $destH / $srcRatio;
            $destRatio = $srcSize[1] / $destH;
        }

        $srcW = $destW * $destRatio;
        $srcH = $destH * $destRatio;
        $srcX = ($srcSize[0] - $srcW) / 2;
        $srcY = ($srcSize[1] - $srcH) / 2;

        //建立影像
        $destImage = imagecreatetruecolor($destW, $destH);

        //根據檔案格式讀取圖檔
        switch ($srcExtension) {
            case 1: $srcImage = imagecreatefromgif($src); break;
            case 2: $srcImage = imagecreatefromjpeg($src); break;
            case 3: $srcImage = imagecreatefrompng($src); break;
        }

        // --- 修正開始：PNG/GIF 透明背景處理 ---
        if ($srcExtension == 3 || $srcExtension == 1) {
            // 關閉混合模式，以便直接拷貝 Alpha 通道
            imagealphablending($destImage, false);
            // 開啟儲存 Alpha 通道資訊
            imagesavealpha($destImage, true);
            // 建立一個完全透明的顏色 (R, G, B, Alpha: 127為全透明)
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            // 將新畫布填滿透明色
            imagefilledrectangle($destImage, 0, 0, $destW, $destH, $transparent);
        }
        // --- 修正結束 ---

        //取樣縮圖
        imagecopyresampled($destImage, $srcImage, 0, 0, (int)$srcX, (int)$srcY, (int)$destW, (int)$destH, (int)$srcW, (int)$srcH);

        //輸出圖檔 (修正 Switch 結構與參數)
        switch ($srcExtension) {
            case 1:
                imagegif($destImage, $dest);
                break;
            case 2:
                imagejpeg($destImage, $dest, 90); // JPG 品質設為 90
                break;
            case 3:
                // PNG 壓縮級別 0-9 (不需設100)
                imagepng($destImage, $dest, 9); 
                break;
        }

        //釋放資源 (移到 Switch 外面確保執行)
        imagedestroy($destImage);
        if(isset($srcImage)) imagedestroy($srcImage);
    }
}

//取正方形
function imagesResize_2($src, $dest, $destW, $destH) {
    if (file_exists($src) && isset($dest)) {

        $imgSetW = $destW;
        $imgSetH = $destH;

        //取得檔案資訊
        $srcSize = getimagesize($src);
        $srcExtension = $srcSize[2];
        $srcRatio = $srcSize[0] / $srcSize[1];
        //依長寬比判斷長寬像素

        $destH_2 = $destW / $srcRatio;
        ////echo "destW = ".$destW."<br/>";
        ////echo "destH = ".$destH."<br/>";
        ////echo "destH_2 = ".$destH_2."<br/>";
        if ($destH_2 < $destH) {
            $srcRatio = $srcSize[1] / $srcSize[0];
            $destW = $destH / $srcRatio;
            //$destH = $destH_2;
            ////echo "大於<br/>";
        } else {
            $destH = $destH_2;
            ////echo "小於<br/>";
        }
        $srcX = ($destW - $imgSetW) / 2;
        $srcY = ($destH - $imgSetH) / 2;

    }
    ////echo "小destW = ".$destW."<br/>";
    ////echo "小destH = ".$destH."<br/>";
    ////echo "小srcX = ".$srcX."<br/>";
    ////echo "小srcY = ".$srcY."<br/>";
    //建立影像
    $destImage = imagecreatetruecolor($imgSetW, $imgSetH);
    $srcImage = null;

    //根據檔案格式讀取圖檔
    switch ($srcExtension) {
        case 1: $srcImage = imagecreatefromgif($src); break;
        case 2: $srcImage = imagecreatefromjpeg($src); break;
        case 3: $srcImage = imagecreatefrompng($src); break;
    }

    if (!$srcImage) return;

    if (imagesx($srcImage) < imagesy($srcImage)) {
        $tgY = ((imagesy($srcImage) - imagesx($srcImage)) / 2);
        $tgX = 0;
        $tgS = imagesx($srcImage);
    } else if (imagesx($srcImage) > imagesy($srcImage)) {
        $tgX = ((imagesx($srcImage) - imagesy($srcImage)) / 2);
        $tgY = 0;
        $tgS = imagesy($srcImage);
    } else {
        $tgX = 0;
        $tgY = 0;
        $tgS = imagesx($srcImage);
    }

    //取樣縮圖
    imagecopyresampled($destImage, $srcImage, 0, 0, $tgX, $tgY, $imgSetW, $imgSetH, $tgS, $tgS);

    //輸出圖檔
    switch ($srcExtension) {
        case 1: imagegif($destImage, $dest); break;
        case 2: imagejpeg($destImage, $dest, 100); break;
        case 3: imagepng($destImage, $dest); break;
    }

    //釋放資源
    imagedestroy($destImage);
    imagedestroy($srcImage);
}
//強制大小
function imagesResize_3($src, $dest, $destW, $destH) {
    if (file_exists($src) && isset($dest)) {
        //取得檔案資訊
        $srcSize = getimagesize($src);
        $srcExtension = $srcSize[2];
        $srcRatio = $srcSize[0] / $srcSize[1]; //依原圖width為主算出比例

        $destRatio = $destW / $destH;
        $dest_W2 = $srcSize[0] * $destRatio;
        $destH_2 = $srcSize[1] * $destRatio;

        //依長寬比判斷長寬像素
        //if( $srcSize[0]<$destW && $srcSize[1]<$destH ){            //原圖的寬高小於720x480

        $destRatio = $destW / $srcSize[0];
        //echo "destW = $destW<br>";
        $dest_W2 = $destW;
        $destH_2 = $srcSize[1] * $destRatio;
        /*echo "destRatio = $destRatio<br>";
        echo "dest_W2 = $dest_W2<br>";
        echo "destH_2 = $destH_2<br>";*/
        if ($destH_2 < $destH) {
//依hight為主
            $destRatio = $destH / $srcSize[1];
            $destH_2 = $destH;
            $dest_W2 = $srcSize[0] * $destRatio;
        }
        /*echo "destRatio = $destRatio<br>";
        echo "dest_W2 = $dest_W2<br>";
        echo "destH_2 = $destH_2<br>";*/
        //}elseif( $srcSize[0]<$destW && $srcSize[1]>$destH ){    //原圖的寬小於720 高大於480

        //}elseif( $srcSize[0]>$destW && $srcSize[1]<$destH ){    //原圖的寬小於720 高大於480

        //}elseif( $srcSize[0]>$destW && $srcSize[1]>$destH ){    //原圖的寬高大於720x480

        //}

        //$destH_2 = $destW / $srcRatio;            //依比例算出height
        //$destRatio = $srcSize[0] / $destW;        //依設定的width算出原圖比例
        /*if($destH_2<$destH){//依hight為主
        $srcRatio  = $srcSize[1] / $srcSize[0];
        $destW_2 = $destH / $srcRatio;
        $destRatio = $srcSize[1] / $destH;
        //$destH = $destH_2;
        ////echo "大於<br/>";
        //$disX = ($destW_2 - $destW)/2;
        //$disY = ($destH - $destH)/2;
        }else{
        //$destH = $destH_2;
        ////echo "小於<br/>";
        //$disX = ($destW - $destW)/2;
        //$disY = ($destH_2 - $destH)/2;
        }*/

        ////echo "destW = ".$destW."<br/>";
        ////echo "destH = ".$destH."<br/>";
        ////echo "destH_2 = ".$destH_2."<br/>";

        //$srcX = $destW - $disX;
        //$srcY = $destH - $disY;
        //$srcX = ($destW*$srcRatio_B)/2;
        //$srcY = ($destH*$srcRatio_B)/2;

        //$srcW = $dest_W2*$destRatio;
        //$srcH = $destH_2*$destRatio;

        $srcW = $dest_W2;
        $srcH = $destH_2;
        //echo "srcW = ".$srcW."<br>";
        //echo "srcH = ".$srcH."<br>";
        $srcX = ($srcSize[0] - $srcW) / 2;
        $srcY = ($srcSize[1] - $srcH) / 2;
    }
    //echo "<br/>srcRatio = $srcRatio<br/>";
    //echo "destRatio = $destRatio<br>";
    //echo "destW = ".$destW."<br/>";
    //echo "destH = ".$destH."<br/>";
    //echo "srcW = ".$srcW."<br/>";
    //echo "srcH = ".$srcH."<br/>";
    //echo "srcX = ".$srcX."<br/>";
    //echo "srcY = ".$srcY."<br/><br/>";
    //建立影像
    $destImage = imagecreatetruecolor($destW, $destH);
    $srcImage = null;

    //根據檔案格式讀取圖檔
    switch ($srcExtension) {
        case 1: $srcImage = imagecreatefromgif($src); break;
        case 2: $srcImage = imagecreatefromjpeg($src); break;
        case 3: $srcImage = imagecreatefrompng($src); break;
    }

    if (!$srcImage) return;

    //取樣縮圖
    imagecopyresampled($destImage, $srcImage, 0, 0, (int)$srcX, (int)$srcY, (int)$destW, (int)$destH, (int)$srcW, (int)$srcH);

    //輸出圖檔
    switch ($srcExtension) {
        case 1: imagegif($destImage, $dest); break;
        case 2: imagejpeg($destImage, $dest, 100); break;
        case 3: imagepng($destImage, $dest); break;
    }

    //釋放資源
    imagedestroy($destImage);
    imagedestroy($srcImage);
}

function imagesResize_5($src, $dest, $destW, $destH) {
    if (file_exists($src) && isset($dest)) {
        //取得檔案資訊
        $srcSize = getimagesize($src);
        $srcExtension = $srcSize[2];
        $srcRatio = $srcSize[0] / $srcSize[1]; 

        $destH_2 = $destW / $srcRatio;
        $destRatio = $srcSize[0] / $destW;

        if ($destH_2 < $destH) {
            $srcRatio = $srcSize[1] / $srcSize[0];
            $destW_2 = $destH / $srcRatio;
            $destRatio = $srcSize[1] / $destH;
        }

        $srcW = $destW * $destRatio;
        $srcH = $destH * $destRatio;
        $srcX = ($srcSize[0] - $srcW) / 2;
        $srcY = ($srcSize[1] - $srcH) / 2;

        //建立影像
        $destImage = imagecreatetruecolor($destW, $destH);

        //根據檔案格式讀取圖檔
        switch ($srcExtension) {
            case 1: $srcImage = imagecreatefromgif($src); break;
            case 2: $srcImage = imagecreatefromjpeg($src); break;
            case 3: $srcImage = imagecreatefrompng($src); break;
        }

        // --- 修正開始：加入 imagesResize_5 缺少的透明處理 ---
        if ($srcExtension == 3 || $srcExtension == 1) {
            // 關閉混合模式
            imagealphablending($destImage, false);
            // 開啟儲存透明資訊
            imagesavealpha($destImage, true);
            // 建立透明色
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            // 填滿背景
            imagefilledrectangle($destImage, 0, 0, $destW, $destH, $transparent);
        }
        // --- 修正結束 ---

        //取樣縮圖
        imagecopyresampled($destImage, $srcImage, 0, 0, (int)$srcX, (int)$srcY, (int)$destW, (int)$destH, (int)$srcW, (int)$srcH);

        //輸出圖檔
        switch ($srcExtension) {
            case 1:
                imagegif($destImage, $dest);
                break;
            case 2:
                imagejpeg($destImage, $dest, 90);
                break;
            case 3:
                imagepng($destImage, $dest, 9);
                break;
        }

        //釋放資源
        imagedestroy($destImage);
        if(isset($srcImage)) imagedestroy($srcImage);
    }
}
?>