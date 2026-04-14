<?php
/**
 * Generic Info/Setting Page
 * 通用設定頁面 - 根據模組配置顯示設定表單
 */

require_once('../Connections/connect2data.php');
require_once('../config/config.php');
require_once 'auth.php';

// 載入 Element 模組
require_once(__DIR__ . '/includes/elements/ModuleConfigElement.php');
require_once(__DIR__ . '/includes/elements/PermissionElement.php');
require_once(__DIR__ . '/includes/elements/FormProcessElement.php');

// 載入其他輔助函數
require_once(__DIR__ . '/includes/formRenderer.php');
require_once(__DIR__ . '/includes/buttonElement.php');
require_once(__DIR__ . '/includes/permissionCheck.php');
require_once(__DIR__ . '/includes/DynamicFieldsHelper.php');
require_once(__DIR__ . '/upload_process.php');

// 獲取模組名稱
$module = $_GET['module'] ?? '';

try {
    // 載入模組配置(使用 Element)
    $moduleConfig = ModuleConfigElement::loadConfig($module);
    
    // 檢查權限(使用 Element) - info 頁面需要檢查所有四種權限
    list($hasViewPermission, $hasAddPermission, $hasEditPermission, $hasDeletePermission) = PermissionElement::checkInfoPermission($conn, $module);
    
    // 要求檢視權限
    PermissionElement::requireViewPermission($hasViewPermission);
    
} catch (Exception $e) {
    die($e->getMessage());
}

$menu_is = $moduleConfig['module'];
$_SESSION['nowMenu'] = $menu_is;

// 自動從 settingPage 配置生成 imagesSize
$globalImgConfigs = [];
foreach ($moduleConfig['detailPage'] as $sheet) {
    foreach ($sheet['items'] as $item) {
        if ($item['type'] === 'image_upload' && isset($item['size']) && !empty($item['size'])) {
            $fileType = $item['fileType'] ?? 'image';
            $size = $item['size'][0]; // 取第一個尺寸配置
            
            $cfg = [
            'IW' => $size['w'] ?? 0,
            'IH' => $size['h'] ?? 0,
            'note' => $item['note'] ?? ''
        ];
        // 合併到全局 $imagesSize
        $imagesSize[$fileType] = $cfg;
        $globalImgConfigs[$fileType] = $cfg;
        }
    }
}
?>
<?php

// 2. 定義動態欄位變數
$tableName  = $moduleConfig['tableName'];
$primaryKey = $moduleConfig['primaryKey'];
$customCols = $moduleConfig['cols'] ?? [];

$col_active = $customCols['active'] ?? 'd_active';
$col_file_fk = $customCols['file_fk'] ?? 'file_d_id';

// 【新增】語系支援
$currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;
$_SESSION['editing_lang'] = $currentLang;

// 檢查資料表是否有 lang 欄位
$tableHasLang = false;
try {
    $checkLangStmt = $conn->query("SHOW COLUMNS FROM {$tableName} LIKE 'lang'");
    $tableHasLang = (bool)$checkLangStmt->fetch();
} catch (Exception $e) {
    $tableHasLang = false;
}

// 取得所有啟用的語系
$activeLanguages = [];
if ($tableHasLang) {
    $langStmt = $conn->query("SELECT l_slug, l_name, l_name_en FROM languages WHERE l_active = 1 ORDER BY l_sort ASC");
    $activeLanguages = $langStmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3. 查詢資料（加入語系過濾）
$query_RecData = "SELECT * FROM {$tableName} WHERE d_class1 = :d_class1";
if ($tableHasLang) {
    $query_RecData .= " AND lang = :lang";
}
$query_RecData .= " ORDER BY {$primaryKey} DESC LIMIT 1";

$RecData = $conn->prepare($query_RecData);
$RecData->bindParam(':d_class1', $menu_is, PDO::PARAM_STR);
if ($tableHasLang) {
    $RecData->bindParam(':lang', $currentLang, PDO::PARAM_STR);
}
$RecData->execute();
$rowData = $RecData->fetch(PDO::FETCH_ASSOC);
$totalRows = $RecData->rowCount();

// 簡化邏輯：如果沒有資料，使用空陣列，表單中用 ?? 運算子處理預設值
if ($totalRows > 0) {
    $dataId = $rowData[$primaryKey];
} else {
    $dataId = 0; // 【修正】使用 0 而不是 -1，避免圖片查詢錯誤
    $rowData = [];
}

// 4. 查詢圖片（只有當 dataId > 0 時才查詢）
if ($dataId > 0) {
    $query_RecImage = "SELECT * FROM file_set WHERE {$col_file_fk} = :file_d_id ORDER BY file_sort ASC";
    $RecImage = $conn->prepare($query_RecImage);
    $RecImage->bindParam(':file_d_id', $dataId, PDO::PARAM_INT);
    $RecImage->execute();
    $rowData['images'] = $RecImage->fetchAll(PDO::FETCH_ASSOC);

    // 【新增】載入動態欄位資料
    $dynamicFieldsHelper = new DynamicFieldsHelper($conn);
    foreach ($moduleConfig['detailPage'] as $sheet) {
        foreach ($sheet['items'] as $item) {
            if ($item['type'] === 'dynamic_fields') {
                $fieldName = $item['field'];
                $fieldGroup = $item['fieldGroup'] ?? 'd_data1';
                $rowData[$fieldName] = $dynamicFieldsHelper->getFields($dataId, $fieldGroup);
            }
        }
    }
} else {
    $rowData['images'] = []; // 沒有資料時，圖片陣列為空
}

$editFormAction = $_SERVER['PHP_SELF'];
if (isset($_SERVER['QUERY_STRING'])) {
    $editFormAction .= "?" . htmlentities($_SERVER['QUERY_STRING']);
}
?>

<?php
// -----------------------------------------------------------------------
// 【後端處理】動態存檔邏輯
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['MM_update'])) {
    // 1. 收集欄位資料 (使用 Element)
    $fields = FormProcessElement::collectFormFields($moduleConfig['detailPage'], $_POST, $moduleConfig['hiddenFields'] ?? []);

    // --- ⭐ 新增：Slug 自動生成與唯一性檢查 (info.php) ⭐ ---
    $prefix = substr($moduleConfig['primaryKey'], 0, 1) . '_';
    $col_slug = $moduleConfig['cols']['slug'] ?? null;
    $col_title = $moduleConfig['cols']['title'] ?? ($prefix . 'title');
    $slugSourceField = $moduleConfig['cols']['slug_source'] ?? $col_title;
    
    // 如果沒有明確定義 slug 欄位，嘗試搜尋欄位配置中有無包含 'slug' 字樣的欄位
    if (!$col_slug) {
        foreach ($moduleConfig['detailPage'] as $sheet) {
            foreach ($sheet['items'] as $item) {
                if (isset($item['field']) && (strpos($item['field'], 'slug') !== false)) {
                    $col_slug = $item['field'];
                    break 2;
                }
            }
        }
    }

    if ($col_slug) {
        // A. 如果 Slug 為空，則從來源欄位自動生成
        if (empty($fields[$col_slug]) && isset($fields[$slugSourceField])) {
            $fields[$col_slug] = FormProcessElement::generateSlug($fields[$slugSourceField]);
        }

        // B. 確保 Slug 唯一性
        if (!empty($fields[$col_slug])) {
            $dataIdForCheck = $_POST[$primaryKey] ?? 0;
            $fields[$col_slug] = FormProcessElement::ensureUniqueSlug(
                $conn, 
                $tableName, 
                $col_slug, 
                $fields[$col_slug], 
                (int)$dataIdForCheck, 
                array_merge($fields, ['lang' => $currentLang]), // 加入語系上下文
                $moduleConfig
            );
        }
    }
    // --- ⭐ 新增：檢查欄位重複 (info.php) ⭐ ---
    $duplicateFieldsToCheck = [];
    foreach ($moduleConfig['detailPage'] as $sheet) {
        $items = isset($sheet['items']) ? $sheet['items'] : [$sheet];
        foreach ($items as $item) {
            if (!empty($item['checkDuplicate']) && isset($item['field'])) {
                $duplicateFieldsToCheck[] = $item;
            }
        }
    }

    if (!empty($duplicateFieldsToCheck)) {
        $errorMessages = [];
        $dataIdForCheck = $_POST[$primaryKey] ?? 0;
        
        foreach ($duplicateFieldsToCheck as $df) {
            $fName = $df['field'];
            $fLabel = $df['label'] ?? $fName;
            $fValue = $fields[$fName] ?? '';

            if (empty($fValue)) continue;

            $duplicateConfig = ['enabled' => true, 'label' => $fLabel];
            if (isset($df['errorMessage'])) $duplicateConfig['errorMessage'] = $df['errorMessage'];

            $checkContext = array_merge($fields, [
                'lang' => $fields['lang'] ?? $currentLang ?? $_SESSION['language'] ?? DEFAULT_LANG_SLUG,
                $moduleConfig['menuKey'] ?? '' => $moduleConfig['menuValue'] ?? null
            ]);

            $duplicateCheck = FormProcessElement::checkDuplicateField(
                $conn,
                $tableName,
                $fName,
                $fValue,
                $duplicateConfig,
                $checkContext,
                $moduleConfig,
                (int)$dataIdForCheck
            );

            if ($duplicateCheck['isDuplicate']) {
                $errorMessages[] = $duplicateCheck['message'];
            }
        }

        if (!empty($errorMessages)) {
            $isForceSubmit = isset($_POST['force_submit']) && $_POST['force_submit'] == '1';
            
            if (!$isForceSubmit) {
                $combinedMessage = implode("<br>", $errorMessages);
                
                // 準備重新送出表單的資料
                $hiddenInputs = "";
                foreach ($_POST as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $subVal) {
                            $hiddenInputs .= "<input type='hidden' name='" . htmlspecialchars($key) . "[]' value='" . htmlspecialchars($subVal) . "'>";
                        }
                    } else {
                        $hiddenInputs .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
                    }
                }
                $hiddenInputs .= "<input type='hidden' name='force_submit' value='1'>";

                echo "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <script src='js/sweetalert2@11.js'></script>
                    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'>
                    <style>body { font-family: sans-serif; }</style>
                </head>
                <body>
                    <form id='forceSubmitForm' method='POST' action=''>
                        {$hiddenInputs}
                    </form>
                    <script>
                        Swal.fire({
                            icon: 'warning',
                            title: '發現重複資料',
                            html: '{$combinedMessage}<br><br>確定要儲存嗎？',
                            showCancelButton: true,
                            confirmButtonText: '強制送出',
                            cancelButtonText: '返回修改',
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#3498db',
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('forceSubmitForm').submit();
                            } else {
                                history.back();
                            }
                        });
                    </script>
                </body>
                </html>";
                exit;
            }
        }
    }

    try {
        $conn->beginTransaction();

        $dataId = $_POST[$primaryKey] ?? '';
        if (!empty($dataId)) {
            // 更新模式
            FormProcessElement::executeUpdate($conn, $tableName, $fields, $primaryKey, $dataId);
            $redirectId = $dataId;
        } else {
            // 新增模式 - 自動加入語系欄位
            if ($tableHasLang) {
                $fields['lang'] = $currentLang;
            }
            $redirectId = FormProcessElement::executeInsert($conn, $tableName, $fields);
        }

        // --- 3. 處理圖片管理 (說明更新、更換、刪除) ---
        
        // 3.1 更新圖片說明
        if (isset($_POST['update_file_title']) && is_array($_POST['update_file_title'])) {
            foreach ($_POST['update_file_title'] as $fId => $fTitle) {
                $stmtImg = $conn->prepare("UPDATE file_set SET file_title = :title WHERE file_id = :id");
                $stmtImg->execute([':title' => $fTitle, ':id' => $fId]);
            }
        }

        // 3.2 處理圖片/檔案更換 (替換現有圖片)
        $updatedFileIds = [];
        foreach ($moduleConfig['detailPage'] as $sheet) {
            foreach ($sheet['items'] as $item) {
                if ($item['type'] === 'image_upload' || $item['type'] === 'image' || $item['type'] === 'file_upload') {
                    $fieldName = $item['field'] . '_update';
                    if (isset($_FILES[$fieldName]) && !empty($_FILES[$fieldName]['name'])) {
                        foreach ($_FILES[$fieldName]['name'] as $fId => $fName) {
                            if (!empty($fName)) {
                                // 重要：設定目前處理的 file_id 供 image_process 使用
                                $_POST['file_id'] = $fId;
                                $updatedFileIds[] = (int)$fId;

                                $singleFile = [
                                    'name' => [$_FILES[$fieldName]['name'][$fId]],
                                    'type' => [$_FILES[$fieldName]['type'][$fId]],
                                    'tmp_name' => [$_FILES[$fieldName]['tmp_name'][$fId]],
                                    'error' => [$_FILES[$fieldName]['error'][$fId]],
                                    'size' => [$_FILES[$fieldName]['size'][$fId]],
                                ];

                                $stmtOrig = $conn->prepare("SELECT * FROM file_set WHERE file_id = :id");
                                $stmtOrig->execute([':id' => $fId]);
                                $origRow = $stmtOrig->fetch(PDO::FETCH_ASSOC);

                                if ($origRow) {
                                    $targetW = $item['size'][0]['w'] ?? 800;
                                    $targetH = $item['size'][0]['h'] ?? 600;

                                    if ($item['type'] === 'file_upload') {
                                        $format = $item['format'] ?? '*';
                                        $maxSize = $item['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 10);
                                        $acceptParam = ['format' => $format, 'maxSize' => $maxSize];
                                        $img_result = file_process($conn, $singleFile, [$origRow['file_title']], $menu_is, "edit", $acceptParam);
                                    } else {
                                        $img_result = image_process($conn, $singleFile, [$origRow['file_title']], $menu_is, "edit", $targetW, $targetH);
                                    }

                                    // 檢查狀態碼 (index 0) 是否為 0 (成功)
                                    if (isset($img_result[0][0]) && $img_result[0][0] == 0 && count($img_result) >= 2) {
                                        for ($i = 1; $i <= 5; $i++) {
                                            $link = "file_link{$i}";
                                            if (!empty($origRow[$link]) && file_exists("../" . $origRow[$link])) {
                                                @unlink("../" . $origRow[$link]);
                                            }
                                        }

                                        if ($item['type'] === 'file_upload') {
                                            $updateImgSQL = "UPDATE file_set SET file_name=?, file_link1=? WHERE file_id=?";
                                            $stmtUpdateImg = $conn->prepare($updateImgSQL);
                                            $stmtUpdateImg->execute([
                                                $img_result[1][0], $img_result[1][1], $fId
                                            ]);
                                        } else {
                                            $updateImgSQL = "UPDATE file_set SET file_name=?, file_link1=?, file_link2=?, file_link3=? WHERE file_id=?";
                                            $stmtUpdateImg = $conn->prepare($updateImgSQL);
                                            $stmtUpdateImg->execute([
                                                $img_result[1][0], $img_result[1][1], $img_result[1][2], $img_result[1][3], $fId
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3.3 執行正式刪除
        if (isset($_POST['delete_file']) && is_array($_POST['delete_file'])) {
            foreach ($_POST['delete_file'] as $fId) {
                // 如果這張圖片剛被更換 (update)，則不執行刪除
                if (in_array((int)$fId, $updatedFileIds)) {
                    continue;
                }

                $stmtGet = $conn->prepare("SELECT file_link1, file_link2, file_link3 FROM file_set WHERE file_id = :id");
                $stmtGet->execute([':id' => $fId]);
                $fileData = $stmtGet->fetch(PDO::FETCH_ASSOC);
                if ($fileData) {
                    @unlink("../" . $fileData['file_link1']);
                    @unlink("../" . $fileData['file_link2']);
                    @unlink("../" . $fileData['file_link3']);
                }
                $stmtDel = $conn->prepare("DELETE FROM file_set WHERE file_id = :id");
                $stmtDel->execute([':id' => $fId]);
            }
        }

        // 4. 動態圖片處理邏輯 (新增圖片 - 支援 image_upload, image, file_upload)
        foreach ($moduleConfig['detailPage'] as $sheet) {
            foreach ($sheet['items'] as $item) {
                if ($item['type'] == 'image_upload' || $item['type'] == 'image' || $item['type'] == 'file_upload') {
                    $fName = $item['field'];
                    
                    // 檢查是否有任何檔案被上傳
                    $hasAnyFile = false;
                    if (isset($_FILES[$fName]['name'])) {
                        if (is_array($_FILES[$fName]['name'])) {
                            foreach ($_FILES[$fName]['name'] as $fileName) {
                                if (!empty($fileName)) {
                                    $hasAnyFile = true;
                                    break;
                                }
                            }
                        } elseif (!empty($_FILES[$fName]['name'])) {
                            $hasAnyFile = true;
                        }
                    }

                    if ($hasAnyFile) {
                        $targetW = $item['size'][0]['w'] ?? 800;
                        $targetH = $item['size'][0]['h'] ?? 600;
                        $dbFileType = $item['fileType'] ?? ($item['type'] == 'file_upload' ? 'file' : 'image');

                        // 判斷是用 image_process 還是 file_process
                        if ($item['type'] == 'file_upload') {
                            $format = $item['format'] ?? '*';
                            $maxSize = $item['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 10);
                            $acceptParam = ['format' => $format, 'maxSize' => $maxSize];
                            
                            $image_result = file_process($conn, $_FILES[$fName], $_REQUEST[$fName . '_title'] ?? [], $menu_is, ($dataId > 0) ? "edit" : "add", $acceptParam);
                        } else {
                            $image_result = image_process($conn, $_FILES[$fName], $_REQUEST[$fName . '_title'] ?? [], $menu_is, ($dataId > 0) ? "edit" : "add", $targetW, $targetH);
                        }

                        // 取得目前該 fileType 的最大 file_sort 值
                        $maxSortStmt = $conn->prepare("SELECT COALESCE(MAX(file_sort), 0) as max_sort FROM file_set WHERE {$col_file_fk} = ? AND file_type = ?");
                        $maxSortStmt->execute([$redirectId, $dbFileType]);
                        $maxSort = $maxSortStmt->fetch(PDO::FETCH_ASSOC)['max_sort'];

                        for ($j = 1; $j < count($image_result); $j++) {
                            $newSort = $maxSort + $j;
                            
                            if ($item['type'] == 'file_upload') {
                                // 一般檔案上傳只存 link1
                                $stmtFile = $conn->prepare("INSERT INTO file_set (file_name, file_link1, file_type, {$col_file_fk}, file_title, file_sort) VALUES (?,?,?,?,?,?)");
                                $stmtFile->execute([
                                    $image_result[$j][0], 
                                    $image_result[$j][1], 
                                    $dbFileType, 
                                    $redirectId, 
                                    $image_result[$j][2], 
                                    $newSort
                                ]);
                            } else {
                                // 圖片上傳存 link1, link2, link3
                                $stmtFile = $conn->prepare("INSERT INTO file_set (file_name, file_link1, file_link2, file_link3, file_type, {$col_file_fk}, file_title, file_show_type, file_sort) VALUES (?,?,?,?,?,?,?,?,?)");
                                $stmtFile->execute([
                                    $image_result[$j][0], 
                                    $image_result[$j][1], 
                                    $image_result[$j][2], 
                                    $image_result[$j][3], 
                                    $dbFileType, 
                                    $redirectId, 
                                    $image_result[$j][4], 
                                    $image_result[$j][5],
                                    $newSort
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // 【新增】處理動態欄位儲存
        $dynamicFieldsHelper = new DynamicFieldsHelper($conn);
        foreach ($moduleConfig['detailPage'] as $sheet) {
            foreach ($sheet['items'] as $item) {

                if ($item['type'] !== 'dynamic_fields') {
                    continue;
                }

                $fieldName = $item['field'];
                $fieldGroup = $item['fieldGroup'] ?? null;

                $uidIndexMap = [];
                if (isset($_POST[$fieldName]) && is_array($_POST[$fieldName])) {
                    foreach ($_POST[$fieldName] as $idx => $group) {
                        if (!empty($group['_uid'])) {
                            $uidIndexMap[$group['_uid']] = $idx;
                        }
                    }
                }
                foreach ($item['fields'] as $field) {

                    if ($field['type'] !== 'image') {
                        continue;
                    }

                    $imageFieldName = $field['name'];
                    $fileType = $field['fileType'] ?? 'image';
                    $targetW = $field['size'][0]['w'] ?? 800;
                    $targetH = $field['size'][0]['h'] ?? 600;
                    $isMultiple = $field['multiple'] ?? false;

                    if (
                        !isset($_FILES[$fieldName]['name']) ||
                        !is_array($_FILES[$fieldName]['name'])
                    ) {
                        continue;
                    }

                    foreach ($_FILES[$fieldName]['name'] as $uid => $groupFiles) {

                        if (empty($uid) || !isset($uidIndexMap[$uid])) {
                            continue;
                        }

                        $realIndex = $uidIndexMap[$uid];

                        $uploadKey = "{$imageFieldName}_upload";

                        if (
                            !isset($groupFiles[$uploadKey]) ||
                            empty($groupFiles[$uploadKey])
                        ) {
                            continue;
                        }

                        // 判斷是單圖還是多圖模式
                        if ($isMultiple && is_array($groupFiles[$uploadKey])) {
                            // 多圖模式：處理多張圖片
                            foreach ($groupFiles[$uploadKey] as $imgIndex => $fileName) {
                                if (empty($fileName)) {
                                    continue;
                                }

                                /* 單檔轉換成 image_process 可吃的格式 */
                                $singleFileArray = [
                                    'name' => [$fileName],
                                    'type' => [$_FILES[$fieldName]['type'][$uid][$uploadKey][$imgIndex]],
                                    'tmp_name' => [$_FILES[$fieldName]['tmp_name'][$uid][$uploadKey][$imgIndex]],
                                    'error' => [$_FILES[$fieldName]['error'][$uid][$uploadKey][$imgIndex]],
                                    'size' => [$_FILES[$fieldName]['size'][$uid][$uploadKey][$imgIndex]],
                                ];

                                // 取得圖片說明
                                $imageTitle = '';
                                if (isset($_POST[$fieldName][$realIndex][$imageFieldName][$imgIndex]['title'])) {
                                    $imageTitle = $_POST[$fieldName][$realIndex][$imageFieldName][$imgIndex]['title'];
                                }

                                $image_result = image_process(
                                    $conn,
                                    $singleFileArray,
                                    [$imageTitle],
                                    $menu_is,
                                    'add',
                                    $targetW,
                                    $targetH
                                );

                                if (count($image_result) <= 1) {
                                    continue;
                                }

                                for ($j = 1; $j < count($image_result); $j++) {
                                    $stmtFile = $conn->prepare("
                                        INSERT INTO file_set
                                        (file_name, file_link1, file_link2, file_link3,
                                        file_type, {$col_file_fk}, file_title, file_show_type)
                                        VALUES (?,?,?,?,?,?,?,?)
                                    ");

                                    if (
                                        $stmtFile->execute([
                                            $image_result[$j][0],
                                            $image_result[$j][1],
                                            $image_result[$j][2],
                                            $image_result[$j][3],
                                            $fileType,
                                            $redirectId,
                                            $image_result[$j][4],
                                            $image_result[$j][5]
                                        ])
                                    ) {
                                        $newFileId = $conn->lastInsertId();
                                        // 將 file_id 存入對應的索引位置
                                        if (!isset($_POST[$fieldName][$realIndex][$imageFieldName])) {
                                            $_POST[$fieldName][$realIndex][$imageFieldName] = [];
                                        }
                                        if (!isset($_POST[$fieldName][$realIndex][$imageFieldName][$imgIndex])) {
                                            $_POST[$fieldName][$realIndex][$imageFieldName][$imgIndex] = [];
                                        }
                                        $_POST[$fieldName][$realIndex][$imageFieldName][$imgIndex]['file_id'] = $newFileId;
                                    }
                                }
                            }
                        } else {
                            // 單圖模式（原有邏輯）
                            /* 單檔轉換成 image_process 可吃的格式 */
                            $singleFileArray = [
                                'name' => [$_FILES[$fieldName]['name'][$uid][$uploadKey]],
                                'type' => [$_FILES[$fieldName]['type'][$uid][$uploadKey]],
                                'tmp_name' => [$_FILES[$fieldName]['tmp_name'][$uid][$uploadKey]],
                                'error' => [$_FILES[$fieldName]['error'][$uid][$uploadKey]],
                                'size' => [$_FILES[$fieldName]['size'][$uid][$uploadKey]],
                            ];

                            $imageTitle = '';

                            if (isset($_POST[$fieldName][$realIndex][$imageFieldName]['title'])) {
                                $imageTitle = $_POST[$fieldName][$realIndex][$imageFieldName]['title'];
                            }

                            $image_result = image_process(
                                $conn,
                                $singleFileArray,
                                [$imageTitle], // 傳入圖片說明
                                $menu_is,
                                'add',
                                $targetW,
                                $targetH
                            );

                            if (count($image_result) <= 1) {
                                continue;
                            }

                            for ($j = 1; $j < count($image_result); $j++) {

                                $stmtFile = $conn->prepare("
                                    INSERT INTO file_set
                                    (file_name, file_link1, file_link2, file_link3,
                                    file_type, {$col_file_fk}, file_title, file_show_type)
                                    VALUES (?,?,?,?,?,?,?,?)
                                ");

                                if (
                                    $stmtFile->execute([
                                        $image_result[$j][0],
                                        $image_result[$j][1],
                                        $image_result[$j][2],
                                        $image_result[$j][3],
                                        $fileType,
                                        $redirectId,
                                        $image_result[$j][4],
                                        $image_result[$j][5]
                                    ])
                                ) {
                                    $newFileId = $conn->lastInsertId();
                                    $_POST[$fieldName][$realIndex][$imageFieldName]['file_id'] = $newFileId;
                                }
                            }
                        }
                    }
                }

                /* =====================================================
                 * 2️⃣ 組 DynamicFieldsHelper 專用格式的 $dynamicData
                 * ===================================================== */
                $dynamicData = [];
                if (isset($_POST[$fieldName]) && is_array($_POST[$fieldName])) {

                    foreach ($_POST[$fieldName] as $groupIndex => $group) {

                        $groupData = [];

                        foreach ($group as $key => $value) {

                            // 跳過 upload 欄位
                            if (strpos($key, '_upload') !== false) {
                                continue;
                            }

                            // ⭐ UID 一定要保留(helper 以 UID 判斷)
                            if ($key === '_uid') {
                                $groupData['_uid'] = $value;
                                continue;
                            }

                            // 跳過 *_title 欄位,稍後統一處理
                            if (strpos($key, '_title') !== false) {
                                continue;
                            }

                            // 檢查是否為圖片欄位
                            $isImageField = false;
                            foreach ($item['fields'] as $field) {
                                if ($field['type'] === 'image' && $field['name'] === $key) {
                                    $isImageField = true;
                                    $isMultiple = $field['multiple'] ?? false;

                                    if ($isMultiple && is_array($value)) {
                                        // 多圖模式：value 是陣列，需要確保每個元素都有 file_id
                                        $imageArray = [];
                                        foreach ($value as $imgIndex => $imgData) {
                                            if (is_array($imgData) && isset($imgData['file_id']) && !empty($imgData['file_id'])) {
                                                $imageArray[] = [
                                                    'file_id' => $imgData['file_id']
                                                ];
                                            }
                                        }
                                        if (!empty($imageArray)) {
                                            $groupData[$key] = $imageArray;
                                        }
                                    } else {
                                        // 單圖模式：如果已經是陣列，就直接使用，否則包裝一下
                                        if (is_array($value)) {
                                            $groupData[$key] = $value;
                                        } else {
                                            // 容錯邏輯：如果 POST 裡還有 legacy 命名
                                            $fileIdKey = $key . '_file_id';
                                            if (isset($group[$fileIdKey])) {
                                                $groupData[$key] = [
                                                    'file_id' => $group[$fileIdKey]
                                                ];
                                            }
                                        }
                                    }
                                    break;
                                }
                            }

                            // 如果不是圖片欄位，當作文字欄位處理
                            if (!$isImageField && strpos($key, '_file_id') === false) {
                                $groupData[$key] = $value;
                            }
                        }

                        $dynamicData[$groupIndex] = $groupData;
                    }
                }

                /* =====================================================
                 * 2.5️⃣ 處理動態欄位的圖片說明更新（UID-based）
                 * ===================================================== */
                $uidTitleMap = [];

                if (isset($_POST[$fieldName]) && is_array($_POST[$fieldName])) {
                    foreach ($_POST[$fieldName] as $key => $value) {
                        // 如果 key 不是數字,就是 UID
                        if (!is_numeric($key) && is_array($value)) {
                            $uid = $key;

                            // 遍歷這個 UID 下的所有欄位
                            foreach ($value as $fieldKey => $fieldValue) {
                                if (strpos($fieldKey, '_title') !== false) {
                                    // 這是圖片說明欄位
                                    $imageName = str_replace('_title', '', $fieldKey);
                                    if (!isset($uidTitleMap[$uid])) {
                                        $uidTitleMap[$uid] = [];
                                    }
                                    $uidTitleMap[$uid][$imageName] = $fieldValue;
                                }
                            }
                        }
                    }
                }

                // ⭐ 步驟2: 遍歷每個群組,使用 UID 查找對應的 title
                if (isset($_POST[$fieldName]) && is_array($_POST[$fieldName])) {
                    foreach ($_POST[$fieldName] as $groupIndex => $groupData) {
                        // 跳過 UID-based 的資料(已經在步驟1處理過了)
                        if (!is_numeric($groupIndex)) {
                            continue;
                        }

                        if (!is_array($groupData)) {
                            continue;
                        }

                        // 取得這個群組的 UID
                        $uid = $groupData['_uid'] ?? null;
                        if (!$uid) {
                            continue;
                        }

                        // 遍歷設定檔中的所有圖片欄位
                        foreach ($item['fields'] as $fieldDef) {
                            if ($fieldDef['type'] !== 'image') {
                                continue;
                            }

                            $imageName = $fieldDef['name'];

                            // ⭐ 從 UID-Title Map 中取得 title
                            $title = $uidTitleMap[$uid][$imageName] ?? null;

                            if ($title === null) {
                                error_log("⚠ No title found for UID={$uid}, image={$imageName}");
                                continue;
                            }

                            // 取得 file_id
                            $fileId = null;
                            $fileIdKey = "{$imageName}_file_id";

                            // 方法1: 從 POST 取得 (上傳新圖片時)
                            if (isset($groupData[$fileIdKey]) && $groupData[$fileIdKey] !== '' && $groupData[$fileIdKey] !== null) {
                                $fileId = $groupData[$fileIdKey];
                            }
                            // 方法2: 從資料庫查詢 (只改標題時)
                            else {
                                try {
                                    $stmtFindFile = $conn->prepare("
                                        SELECT df_file_id
                                        FROM data_dynamic_fields
                                        WHERE df_d_id = :d_id
                                          AND df_field_group = :field_group
                                          AND df_group_uid = :uid
                                          AND df_field_name = :field_name
                                          AND df_file_id IS NOT NULL
                                        LIMIT 1
                                    ");
                                    $stmtFindFile->execute([
                                        ':d_id' => $redirectId,
                                        ':field_group' => $fieldGroup,
                                        ':uid' => $uid,
                                        ':field_name' => $imageName
                                    ]);

                                    $existingFileId = $stmtFindFile->fetchColumn();

                                    if ($existingFileId) {
                                        $fileId = $existingFileId;
                                    } else {
                                    }
                                } catch (PDOException $e) {
                                    error_log("❌ DB query failed: " . $e->getMessage());
                                }
                            }

                            // ⭐ 如果找到 file_id,執行更新
                            if ($fileId && $fileId !== '__DELETE__' && is_numeric($fileId)) {
                                try {
                                    $stmtUpdateTitle = $conn->prepare("
                                        UPDATE file_set
                                        SET file_title = :title
                                        WHERE file_id = :id
                                    ");
                                    $stmtUpdateTitle->execute([
                                        ':title' => $title,
                                        ':id' => $fileId
                                    ]);

                                    $rowsAffected = $stmtUpdateTitle->rowCount();
                                } catch (PDOException $e) {
                                    error_log("❌ UPDATE FAILED: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }

                /* =====================================================
                 * 3️⃣ 儲存
                 * ===================================================== */
                $fieldConfig = $item['fields'] ?? [];

                $result = $dynamicFieldsHelper->saveFields(
                    $redirectId,
                    $fieldGroup,
                    $dynamicData,
                    $fieldConfig
                );
            }
        }

        $conn->commit();

        // 重定向
        $redirectUrl = PORTAL_AUTH_URL."tpl=" . $module . "/info";
        header("Location: " . $redirectUrl);
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        die("存檔失敗: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html class="sidebar-left-big-icons">
<head>
    <title><?php require_once('cmsTitle.php'); ?></title>
    <?php require_once('head.php'); ?>
    <?php require_once('script.php'); ?>

    <!-- 動態欄位編輯器 CSS & JS -->
    <link rel="stylesheet" href="css/dynamicFields.css">
    <script src="js/dynamicFieldsEditor.js"></script>
</head>
<body>
    <section class="body">
        <?php require_once('header.php'); ?>
        <div class="inner-wrapper">
            <?php require_once('sidebar.php'); ?>
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2><?php echo $moduleConfig['moduleName']; ?></h2>

                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <?php 
                            require_once(__DIR__ . '/includes/menuHelper.php');
                            echo renderBreadcrumbsHtml($conn, $module, '設定');
                            ?>
                        </ol>

                        <a class="sidebar-right-toggle" data-open="sidebar-right" style="pointer-events: none;"></a>
                    </div>
                </header>

                <form aclass="ecommerce-form" action="<?php echo $editFormAction; ?>" method="POST" enctype="multipart/form-data" name="form1" id="form1">
                    <div class="row">
                        <div class="col">

                            <div class="datatable-header">
                                <div class="row align-items-center mb-3">
                                    <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                                        <?php if ($hasEditPermission): ?>
                                            <?php echo renderSubmitButton('儲存 (alt+s)'); ?>
                                        <?php else: ?>
                                            <p style="color: #999;">您沒有編輯權限</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($tableHasLang && count($activeLanguages) > 1): ?>
                                    <div class="col-12 col-lg-auto ms-auto">
                                        <ul class="nav nav-pills nav-pills-primary">
                                            <?php foreach ($activeLanguages as $lang): 
                                                $activeClass = ($lang['l_slug'] == $currentLang) ? 'active' : '';
                                                $langUrl = PORTAL_AUTH_URL."tpl={$module}/info?language=" . $lang['l_slug'];
                                            ?>
                                                <li class="nav-item">
                                                    <a class="nav-link <?= $activeClass ?> py-1 px-3" href="<?= $langUrl ?>"><?= $lang['l_name'] ?></a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (isset($moduleConfig['hiddenFields'])): ?>
                                <?php foreach ($moduleConfig['hiddenFields'] as $hField => $hValue): ?>
                                    <input type="hidden" name="<?php echo $hField; ?>" value="<?php echo $hValue; ?>" />
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <section class="card card-modern card-big-info">
                                <div class="card-body">
                                    <div class="tabs-modern row" style="min-height: 490px;">
                                        <div class="col-lg-2-5 col-xl-1-5">
                                            <div class="nav flex-column" id="tab" role="tablist" aria-orientation="vertical">
                                                <?php if (count($moduleConfig['detailPage']) > 1): ?>
                                                    <?php $i=0; foreach ($moduleConfig['detailPage'] as $index => $sheet): $i++; ?>
                                                        <a class="nav-link <?php echo $index == 0 ? 'active' : ''; ?>" id="tab-<?= $i; ?>" data-bs-toggle="pill" data-bs-target="#tab<?= $i; ?>" role="tab" aria-controls="tab<?= $i; ?>" aria-selected="true"><i class="bx bx-cog me-2"></i> <?= $sheet['sheetTitle']; ?></a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-lg-3-5 col-xl-4-5">
                                            <div class="tab-content" id="tabContent">
                                                <?php $i=0; foreach ($moduleConfig['detailPage'] as $index => $sheet): $i++; ?>
                                                    <div class="tab-pane fade <?php echo $index == 0 ? 'show' : ''; ?> <?php echo $index == 0 ? 'active' : ''; ?>" id="tab<?= $i; ?>" role="tabpanel" aria-labelledby="tab<?= $i; ?>">
                                                        <?php foreach ($sheet['items'] as $item):
                                                            $fieldValue = $rowData[$item['field']] ?? '';
                                                            // 處理預設值
                                                            if (empty($fieldValue) && isset($item['default'])) {
                                                                if ($item['default'] === 'now' && $item['type'] === 'datetime') {
                                                                    $fieldValue = date("Y-m-d H:i:s");
                                                                } else {
                                                                    $fieldValue = $item['default'];
                                                                }
                                                            }
                                                            echo renderFormField($item, $fieldValue, $rowData);
                                                        endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            
                            <!-- 延後刪除的圖片 ID 容器 -->
                            <div id="delete_file_container"></div>

                            <input type="hidden" name="MM_update" value="form1" />
                            <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo $rowData[$primaryKey] ?? ''; ?>" />
                        </div>
                    </div>
                </form>
                
                <?php if ($tableHasLang && count($activeLanguages) > 1): ?>
                <div class="datatable-footer">
                    <div class="row align-items-center justify-content-between mt-3">
                        <div class="col-md-auto order-1 mb-3 mb-lg-0">
                            <div class="d-flex align-items-stretch">
                                <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">
                                        <select class="form-control select-style-1 bulk-action" name="bulk-action" style="min-width: 170px;">
                                            <option value="" selected>批次操作</option>
                                            <option value="clone">複製到語系</option>
                                        </select>
                                        <select class="form-control select-style-1 bulk-action-lang d-none" name="bulk-action-lang" style="min-width: 140px;">
                                        <option value="">選擇語系...</option>
                                        <?php foreach ($activeLanguages as $lang): ?>
                                            <?php if($lang['l_slug'] !== $currentLang): ?>
                                                <option value="<?= $lang['l_slug'] ?>"><?= $lang['l_name'] ?> (<?= $lang['l_slug'] ?>)</option>
                                                <?php endif; ?>
                                        <?php endforeach; ?>
                                        </select>
                                        <a href="javascript:void(0);" class="bulk-action-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3" style="min-width: 90px;">執行</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-auto text-center order-3 order-lg-2">
                            <div class="results-info-wrapper"></div>
                        </div>
                        <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                            <div class="pagination-wrapper"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</body>
</html>

<script type="text/javascript">
    $(document).ready(function() {
        // 初始化拖拽排序
        $("[id^='draggable_']").each(function() {
            var sortable = Sortable.create(this, {
                animation: 100,
                handle: ".drag-handle",
                dataIdAttr: 'data-id',
                ghostClass: "ryder-ghost",
                chosenClass: "ryder-chosen",
                onSort(e) {
                    $.ajax({
                        data: {
                            ids: sortable.toArray()
                        },
                        url: "image_sort.php",
                        type: "POST",
                        success: function(res){}
                    });
                }
            });
        });

        // Fancybox - 已上傳圖片預覽
        $("a.fancyboxImg").fancybox({
            autoSize: true,
            openEffect: 'elastic',
            closeEffect: 'elastic',
            helpers: {
                overlay: {
                    css: {
                        'background': 'rgba(0, 0, 0, 0.7)'
                    }
                }
            }
        });

        // Fancybox - 圖片預覽（支援所有 rel 屬性）
        $("a[rel^='group']").fancybox({
            autoSize: true,
            openEffect: 'elastic',
            closeEffect: 'elastic',
            helpers: {
                overlay: {
                    css: {
                        'background': 'rgba(0, 0, 0, 0.7)'
                    }
                }
            }
        });

        // Fancybox - 批次上傳 (Iframe)
        $("a.fancyboxUpload").fancybox({
            type: 'iframe',
            openEffect: 'fade',
            closeEffect: 'fade',
            autoSize: false,
            width: '1000',
            closeBtn: true,
            helpers: {
                overlay: {
                    closeClick: true,
                    css: {
                        'background': 'rgba(0, 0, 0, 0.7)'
                    }
                }
            },
            afterClose: function() {
                window.location.reload();
            }
        });
    });

    /**
     * 標記圖片為刪除狀態 (介面重置為空白狀態，延後刪除)
     */
    function markImageForDeletion(fileId) {
        const uploaderId = 'ex_' + fileId;
        if ($('#del_input_' + fileId).length > 0) return;

        // 1. 重置預覽圖為預設佔位圖
        const $preview = $('#croppedImagePreview' + uploaderId);
        $preview.attr('src', 'crop/demo.jpg');

        // 2. 清空圖片說明
        $('#title_' + uploaderId).val('');

        // 3. 隱藏移除按鈕、重置檔名顯示 (因為已經清空)
        $('#remove_btn_' + fileId).hide();
        $('#fileNameDisplay' + uploaderId).text('未選擇').hide();

        // 4. 重置底層 Uploader 狀態 (重要：這樣重新選擇才會觸發裁切)
        if (window.uploaders && window.uploaders[uploaderId]) {
            window.uploaders[uploaderId].reset();
        }

        // 5. 加入刪除清單
        $('#delete_file_container').append('<input type="hidden" name="delete_file[]" value="' + fileId + '" id="del_input_' + fileId + '">');
    }

    /**
     * 直接刪除整個圖片區塊 (image-manage-item)
     */
    function deleteImageItem(fileId) {
        Swal.fire({
            title: '確定刪除?',
            text: '此操作將會刪除這張圖片',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '刪除',
            cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                // 1. 刪除整個 tr 元素 (image-manage-item)
                $('#img_item_' + fileId).remove();

                // 2. 刪除對應的 uploader
                const uploaderId = 'ex_' + fileId;
                if (window.uploaders && window.uploaders[uploaderId]) {
                    delete window.uploaders[uploaderId];
                }

                // 3. 加入刪除清單（後端處理）
                if ($('#del_input_' + fileId).length === 0) {
                    $('#delete_file_container').append('<input type="hidden" name="delete_file[]" value="' + fileId + '" id="del_input_' + fileId + '">');
                }

                Swal.fire('已刪除', '圖片已從列表中移除', 'success');
            }
        });
    }

    /**
     * 當圖片被更換 (更新) 時，取消刪除標記
     */
    $(document).on('imageUpdated', '.hidden-file-input', function(e, id) {
        const fileId = id.toString().replace('ex_', '');
        $('#remove_btn_' + fileId).show();
        $('#del_input_' + fileId).remove();
        $('#croppedImagePreview' + id).css('opacity', '1');
    });

    // 【新增】批次操作功能
    $('.bulk-action').on('change', function() {
        const action = $(this).val();
        if (action === 'clone') {
            $('.bulk-action-lang').removeClass('d-none');
        } else {
            $('.bulk-action-lang').addClass('d-none');
        }
    });

    $('.bulk-action-apply').on('click', function(e) {
        e.preventDefault();
        const action = $('.bulk-action').val();
        const targetLang = $('.bulk-action-lang').val();
        const currentLang = '<?php echo $currentLang; ?>';
        const module = '<?php echo $module; ?>';
        const dataId = <?php echo $dataId > 0 ? $dataId : 0; ?>;

        if (!action) {
            Swal.fire('提示', '請選擇批次操作', 'info');
            return;
        }

        if (action === 'clone') {
            if (!targetLang) {
                Swal.fire('錯誤', '請選擇目標語系', 'error');
                return;
            }

            if (dataId <= 0) {
                Swal.fire('錯誤', '無法複製：找不到資料 ID', 'error');
                return;
            }

            Swal.fire({
                title: '確認複製',
                html: `確定要將當前語系的資料複製到 <strong>${$('.bulk-action-lang option:selected').text()}</strong> 嗎？`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '確定複製',
                cancelButtonText: '取消',
                input: 'checkbox',
                inputPlaceholder: '覆蓋已存在的資料'
            }).then((result) => {
                if (result.isConfirmed) {
                    const overwrite = result.value ? 1 : 0;

                    $.ajax({
                        url: 'ajax_batch_translate_clone.php',
                        method: 'POST',
                        data: {
                            module: module,
                            item_ids: [dataId],
                            target_lang: targetLang,
                            overwrite: overwrite,
                            is_info: 1  // 標記這是來自 info.php 的請求
                        },
                        success: function(response) {
                            if (response.success) {
                                // 檢查是否有錯誤
                                if (response.errors && response.errors.length > 0) {
                                    // 有部分失敗
                                    let errorHtml = '<div style="text-align: left;">';
                                    errorHtml += '<p>' + response.message + '</p>';
                                    errorHtml += '<p><strong>失敗原因：</strong></p>';
                                    errorHtml += '<ul>';
                                    response.errors.forEach(function(error) {
                                        errorHtml += '<li>' + error + '</li>';
                                    });
                                    errorHtml += '</ul>';
                                    errorHtml += '</div>';
                                    
                                    Swal.fire({
                                        title: '複製結果',
                                        html: errorHtml,
                                        icon: 'warning',
                                        confirmButtonText: '確定'
                                    });
                                } else {
                                    // 完全成功
                                    Swal.fire({
                                        title: '複製成功',
                                        text: response.message || '資料已複製到 ' + $('.bulk-action-lang option:selected').text(),
                                        icon: 'success',
                                        confirmButtonText: '前往查看'
                                    }).then(() => {
                                        window.location.href = '<?=PORTAL_AUTH_URL?>' + 'tpl=' + module + '/info?language=' + targetLang;
                                    });
                                }
                            } else {
                                Swal.fire('複製失敗', response.message || '發生未知錯誤', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('錯誤', '無法連接到伺服器', 'error');
                        }
                    });
                }
            });
        }
    });

</script>