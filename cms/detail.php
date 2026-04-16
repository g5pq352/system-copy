<?php
/**
 * Generic Detail Page - 動態欄位版本
 * 通用新增/編輯頁面 - 根據模組配置顯示表單並處理存檔
 */

require_once('../Connections/connect2data.php');
require_once('../config/config.php');
require_once 'auth.php';

// 載入 Element 模組
require_once(__DIR__ . '/includes/elements/ModuleConfigElement.php');
require_once(__DIR__ . '/includes/elements/PermissionElement.php');
require_once(__DIR__ . '/includes/elements/FormProcessElement.php');
require_once(__DIR__ . '/includes/elements/SwalConfirmElement.php');

// 載入其他輔助函數
require_once(__DIR__ . '/includes/categoryHelper.php');
require_once(__DIR__ . '/includes/buttonElement.php');
require_once(__DIR__ . '/includes/formRenderer.php');
require_once(__DIR__ . '/includes/permissionCheck.php');
require_once(__DIR__ . '/includes/taxonomyMapHelper.php');
require_once(__DIR__ . '/includes/DynamicFieldsHelper.php');
require_once(__DIR__ . '/cms_media_helper.php');
require_once(__DIR__ . '/upload_process.php');

// 獲取模組名稱
$module = $_GET['module'] ?? '';

try {
    // 載入模組配置（使用 Element）
    $moduleConfig = ModuleConfigElement::loadConfig($module);

    // 檢查權限（使用 Element）
    $isNewRecord = !isset($_GET[$moduleConfig['primaryKey']]);
    PermissionElement::requireDetailPermission($conn, $module, $isNewRecord);

} catch (Exception $e) {
    die($e->getMessage());
}

$menu_is = $moduleConfig['module'];
$_SESSION['nowMenu'] = $menu_is;

// 自動從 settingPage 配置生成 imagesSize
foreach ($moduleConfig['detailPage'] ?? [] as $sheet) {
    foreach ($sheet['items'] ?? [] as $item) {
        if ($item['type'] === 'image_upload' && isset($item['size']) && !empty($item['size'])) {
            $fileType = $item['fileType'] ?? 'image';
            $size = $item['size'][0]; // 取第一個尺寸配置

            // 合併到全局 $imagesSize
            $imagesSize[$fileType] = [
                'IW' => $size['w'] ?? 0,
                'IH' => $size['h'] ?? 0,
                'note' => $item['note'] ?? ''
            ];
        }
    }
}

// -----------------------------------------------------------------------
// 定義動態欄位變數
// -----------------------------------------------------------------------
$tableName = $moduleConfig['tableName'];
$primaryKey = $moduleConfig['primaryKey'];
$customCols = $moduleConfig['cols'] ?? [];

$hasHierarchicalNav = ($moduleConfig['listPage']['hasHierarchy'] ?? false) && isset($moduleConfig['cols']['parent_id']);
$parentIdField = $customCols['parent_id'] ?? null;

$col_date = array_key_exists('date', $customCols) ? $customCols['date'] : 'd_date';
$col_title = array_key_exists('title', $customCols) ? $customCols['title'] : 'd_title';
$col_sort = array_key_exists('sort', $customCols) ? $customCols['sort'] : 'd_sort';
$col_top = array_key_exists('top', $customCols) ? $customCols['top'] : 'd_top';
$col_active = array_key_exists('active', $customCols) ? $customCols['active'] : 'd_active';
$col_delete_time = array_key_exists('delete_time', $customCols) ? $customCols['delete_time'] : 'd_delete_time';
$col_file_fk = (!empty($customCols['file_fk'])) ? $customCols['file_fk'] : 'file_d_id';

// 獲取資料 ID
$d_id = isset($_GET[$primaryKey]) ? intval($_GET[$primaryKey]) : (isset($_GET['d_id']) ? intval($_GET['d_id']) : 0);
$isTrashView = isset($_GET['trash_view']) && $_GET['trash_view'] == 1;

// --- [新增] 自動處理語系欄位 ---
// 獲取當前應用的語系 (優先順序：資料本身 -> URL/Session -> 預設 tw)
$autoLangContext = null;
$tableHasLang = false; // [新增] 用於判斷是否真的有語系欄位

if ($d_id > 0) {
    // 編輯模式：嘗試從資料庫讀取該項目的語系
    try {
        $langQuery = $conn->prepare("SELECT lang FROM {$tableName} WHERE {$primaryKey} = :id");
        $langQuery->execute([':id' => $d_id]);
        $autoLangContext = $langQuery->fetchColumn();
        $tableHasLang = true; // 能讀到或不報錯，表示欄位存在
    } catch (Exception $e) {
        $autoLangContext = null;
        $tableHasLang = false;
    }
} else {
    // 新增模式：再次檢查欄位是否存在
    try {
        $checkLangStmt = $conn->query("SHOW COLUMNS FROM {$tableName} LIKE 'lang'");
        $tableHasLang = (bool) $checkLangStmt->fetch();
    } catch (Exception $e) {
        $tableHasLang = false;
    }
}

// 如果表格支援語系且當前沒讀到語系 (新增、舊資料或資料異常)，從 URL 或 Session 獲取
if ($tableHasLang && !$autoLangContext) {
    $autoLangContext = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;
}
// ----------------------------

// 如果是回收桶查看模式，檢查項目是否真的已刪除
if ($isTrashView && $d_id > 0) {
    $checkQuery = "SELECT {$col_delete_time} FROM {$tableName} WHERE {$primaryKey} = :id";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([':id' => $d_id]);
    $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$checkRow || $checkRow[$col_delete_time] === null) {
        die('錯誤：項目不在回收桶中');
    }
}

// 【新增】自動標記為已讀
if ($d_id > 0 && isset($tableName) && $tableName === 'message_set') {
    // 檢查是否有 m_read 欄位
    // 根據 config 讀取欄位名稱，預設 m_read (因為 contactusSet 是這樣設的)
    $col_read = $customCols['read'] ?? 'm_read';

    // 檢查資料表是否有此欄位
    try {
        $checkReadCol = $conn->query("SHOW COLUMNS FROM {$tableName} LIKE '{$col_read}'");
        if ($checkReadCol->rowCount() > 0) {
            // 更新為已讀 (1)
            $updateReadSql = "UPDATE {$tableName} SET {$col_read} = 1 WHERE {$primaryKey} = :id";
            $updateParams = [':id' => $d_id];

            // 【新增】如果資料表有語系欄位，加上語系判斷以確保安全性
            if ($tableHasLang && !empty($autoLangContext)) {
                $updateReadSql .= " AND lang = :lang";
                $updateParams[':lang'] = $autoLangContext;
            }

            $updateReadStmt = $conn->prepare($updateReadSql);
            $updateReadStmt->execute($updateParams);
        }
    } catch (PDOException $e) {
        // 忽略錯誤，不影響主要流程
        error_log("Auto read update failed: " . $e->getMessage());
    }
}

// =======================================================================
// 【關鍵修正】將存檔邏輯移至 HTML 輸出之前
// 這樣 header() 跳轉才不會失效，且能正確處理 POST 請求
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isTrashView) {

    // 簡易 Debug：如果懷疑沒有進入這個區塊，請取消下一行的註解
    // var_dump($_POST); die(); 

    if (isset($_POST['MM_insert']) || isset($_POST['MM_update'])) {
        $isUpdate = isset($_POST['MM_update']);

        // 1. 收集欄位資料 (使用 Element)
        $fields = FormProcessElement::collectFormFields($moduleConfig['detailPage'], $_POST, $moduleConfig['hiddenFields'] ?? []);

        // 2. 定義系統欄位與參數
        $prefix = substr($moduleConfig['primaryKey'], 0, 1);
        $col_title = $customCols['title'] ?? ($prefix . '_title');
        $col_slug = $customCols['slug'] ?? ($prefix . '_slug');
        $slugSourceField = $customCols['slug_source'] ?? $col_title;
        $categoryField = $moduleConfig['listPage']['categoryField'] ?? null;
        $menuKey = $moduleConfig['menuKey'] ?? null;
        $menuValue = $moduleConfig['menuValue'] ?? null;

        // 3. 處理 Slug 自動生成與唯一性檢查
        $skipSlugModules = ['admin', 'authorityCate', 'contactus', 'service'];
        if (!in_array($module, $skipSlugModules)) {
            // A. 如果 Slug 為空，則從來源欄位自動生成
            if (empty($fields[$col_slug]) && isset($fields[$slugSourceField])) {
                $fields[$col_slug] = FormProcessElement::generateSlug($fields[$slugSourceField]);
            }

            // B. 確保 Slug 唯一性 (若有值則檢查)
            if (!empty($fields[$col_slug])) {
                $fields[$col_slug] = FormProcessElement::ensureUniqueSlug(
                    $conn, 
                    $tableName, 
                    $col_slug, 
                    $fields[$col_slug], 
                    $isUpdate ? $d_id : 0, 
                    $fields, 
                    $moduleConfig
                );
            }
        }

        // 4. 【修正】自動填入語系欄位（新增與更新模式）
        // 優先使用 POST 傳來的 lang 值（語系切換時會更新），若無則使用 autoLangContext
        if ($tableHasLang) {
            if (isset($_POST['lang']) && !empty($_POST['lang'])) {
                // 使用表單傳來的語系值（支援語系切換）
                $fields['lang'] = $_POST['lang'];
            } elseif (!$isUpdate && $autoLangContext) {
                // 新增模式且沒有 POST lang 時，使用 autoLangContext
                $fields['lang'] = $autoLangContext;
            }
        }

        // 【新增】自動處理 canCreate 標籤
        foreach ($moduleConfig['detailPage'] as $sheet) {
            $items = isset($sheet['items']) ? $sheet['items'] : [$sheet];
            foreach ($items as $item) {
                if (!empty($item['canCreate']) && !empty($item['field'])) {
                    $fName = $item['field'];
                    $categoryName = $item['category'] ?? '';
                    if (!empty($fields[$fName]) && !empty($categoryName)) {
                        $fields[$fName] = processAutoCreateTags($conn, $categoryName, $fields[$fName], [
                            'lang' => $fields['lang'] ?? $autoLangContext
                        ]);
                    }
                }
            }
        }

        // 5. 管理員密碼處理
        if ($module === 'admin' && isset($fields['user_password'])) {
            if (!empty($fields['user_password'])) {
                $passwordData = FormProcessElement::processPassword($fields['user_password']);
                $fields['user_password'] = $passwordData['password'];
                $fields['user_salt'] = $passwordData['salt'];
            } else {
                unset($fields['user_password']);
            }
        }

        // 6. 【新增】檢查欄位重複 (根據欄位配置中的 checkDuplicate => true)
        $duplicateFieldsToCheck = [];
        foreach ($moduleConfig['detailPage'] as $sheet) {
            $items = isset($sheet['items']) ? $sheet['items'] : [$sheet];
            foreach ($items as $item) {
                if (!empty($item['checkDuplicate']) && isset($item['field'])) {
                    $duplicateFieldsToCheck[] = $item;
                }
            }
        }

        // 如果配置項目中沒找到，但有全域舊配置，也加入檢查 (相容性)
        if (empty($duplicateFieldsToCheck) && isset($moduleConfig['checkDuplicateTitle']) && !empty($moduleConfig['checkDuplicateTitle']['enabled'])) {
            $duplicateFieldsToCheck[] = [
                'field' => $customCols['title'] ?? ($prefix . '_title'),
                'label' => '標題',
                'checkDuplicate' => true
            ];
        }

        if (!empty($duplicateFieldsToCheck)) {
            $isForceSubmit = isset($_POST['force_submit']) && $_POST['force_submit'] == '1';
            $errorMessages = [];
            
            foreach ($duplicateFieldsToCheck as $df) {
                $fName = $df['field'];
                $fLabel = $df['label'] ?? $fName;
                $fValue = $fields[$fName] ?? '';

                if (empty($fValue)) continue;

                $duplicateConfig = $moduleConfig['checkDuplicateTitle'] ?? [];
                $duplicateConfig['enabled'] = true; // 強制啟用，因為欄位有標記
                $duplicateConfig['label'] = $fLabel; // 傳入 Label
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
                    $isUpdate ? $d_id : 0
                );

                if ($duplicateCheck['isDuplicate']) {
                    $errorMessages[] = $duplicateCheck['message'];
                }
            }

            if (!empty($errorMessages) && !$isForceSubmit) {
                if ($conn->inTransaction()) $conn->rollBack();
                $combinedMessage = implode("<br>", $errorMessages);
                
                // 準備重新送出表單的資料 (排除 MM_insert/MM_update 以免重複觸發，且加入 force_submit)
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
                    <style>body { font-family: 'Poppins', sans-serif; }</style>
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

        // 5. 執行資料庫存檔
        try {
            $conn->beginTransaction();

            if ($isUpdate) {
                // --- 更新模式 ---
                // 【新增】階層層級計算
                if ($hasHierarchicalNav && $parentIdField && isset($fields[$parentIdField])) {
                    $levelField = ($tableName === 'taxonomies') ? 't_level' : (($tableName === 'cms_menus') ? 'menu_level' : null);
                    if ($levelField) {
                        $fields[$levelField] = FormProcessElement::calculateLevel($conn, $tableName, $primaryKey, $fields[$parentIdField], $levelField);
                    }
                }

                // 處理跨分類搬移排序
                // 【重要】只有在不使用 taxonomy map 時才處理主表的排序
                $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;
                // 【修正】如果 categoryField 是陣列（連動分類），使用第一個欄位
                $actualCategoryField = is_array($categoryField) ? $categoryField[0] : $categoryField;
                if ($actualCategoryField && isset($fields[$actualCategoryField]) && !$configUseTaxonomyMapSort) {
                    $hasCategoryChanged = FormProcessElement::handleCrossCategoryMove($conn, $tableName, $primaryKey, $d_id, $actualCategoryField, $fields[$actualCategoryField], $col_sort, $menuKey, $menuValue);
                    if ($hasCategoryChanged) {
                        $fields[$col_sort] = 1;
                    }
                }

                // 執行更新
                FormProcessElement::executeUpdate($conn, $tableName, $fields, $primaryKey, $d_id);
                $redirectId = $d_id;

            } else {
                // --- 新增模式 ---
                // 【新增】階層層級計算
                if ($hasHierarchicalNav && $parentIdField && isset($fields[$parentIdField])) {
                    $levelField = ($tableName === 'taxonomies') ? 't_level' : (($tableName === 'cms_menus') ? 'menu_level' : null);
                    if ($levelField) {
                        $fields[$levelField] = FormProcessElement::calculateLevel($conn, $tableName, $primaryKey, $fields[$parentIdField], $levelField);
                    }
                }

                // 處理新增排序
                if ($col_sort !== null) {
                    $sortConditions = [];
                    if ($menuKey && $menuValue !== null)
                        $sortConditions[$menuKey] = $menuValue;
                    if ($categoryField) {
                        $globalSort = $moduleConfig['listPage']['globalSort'] ?? false;
                        $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;

                        if (!$globalSort && !$configUseTaxonomyMapSort) {
                            if (is_array($categoryField)) {
                                foreach ($categoryField as $cf) {
                                    if (isset($fields[$cf])) $sortConditions[$cf] = $fields[$cf];
                                }
                            } else {
                                if (isset($fields[$categoryField])) $sortConditions[$categoryField] = $fields[$categoryField];
                            }
                        }
                    }

                    $parentIdField = $customCols['parent_id'] ?? null;
                    if ($parentIdField && isset($fields[$parentIdField]))
                        $sortConditions[$parentIdField] = $fields[$parentIdField];

                    // 【修正】加入語系條件，確保排序正確
                    if (isset($fields['lang'])) {
                        $sortConditions['lang'] = $fields['lang'];
                    }

                    $fields[$col_sort] = FormProcessElement::handleSortOnInsert($conn, $tableName, $col_sort, $sortConditions);
                }

                if (!empty($customCols['top']) && !isset($fields[$col_top])) {
                    $fields[$col_top] = 0;
                }

                $redirectId = FormProcessElement::executeInsert($conn, $tableName, $fields);
            }

            // --- 4. 處理圖片說明更新、更換與刪除 ---
            // 4.1 更新圖片說明
            if (isset($_POST['update_file_title']) && is_array($_POST['update_file_title'])) {
                foreach ($_POST['update_file_title'] as $fId => $fTitle) {
                    $stmtImg = $conn->prepare("UPDATE file_set SET file_title = :title WHERE file_id = :id");
                    $stmtImg->execute([':title' => $fTitle, ':id' => $fId]);
                }
            }

            // 4.2 處理圖片更換 (替換現有圖片)
            foreach ($moduleConfig['detailPage'] as $sheet) {
                foreach ($sheet['items'] as $item) {
                    if ($item['type'] === 'image_upload') {
                        $fieldName = $item['field'] . '_update';
                        if (isset($_FILES[$fieldName]) && !empty($_FILES[$fieldName]['name'])) {
                            foreach ($_FILES[$fieldName]['name'] as $fId => $fName) {
                                if (!empty($fName)) {
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
                                        $img_result = image_process($conn, $singleFile, [$origRow['file_title']], $menu_is, "edit", $targetW, $targetH);

                                        if (count($img_result) == 2) {
                                            for ($i = 1; $i <= 5; $i++) {
                                                $link = "file_link{$i}";
                                                if (!empty($origRow[$link]) && file_exists("../" . $origRow[$link])) {
                                                    @unlink("../" . $origRow[$link]);
                                                }
                                            }
                                            $updateImgSQL = "UPDATE file_set SET file_name=?, file_link1=?, file_link2=?, file_link3=?, file_link4=?, file_link5=? WHERE file_id=?";
                                            $stmtUpdateImg = $conn->prepare($updateImgSQL);
                                            $stmtUpdateImg->execute([
                                                $img_result[1][0],
                                                $img_result[1][1],
                                                $img_result[1][2],
                                                $img_result[1][3],
                                                $img_result[1][6],
                                                $img_result[1][8],
                                                $fId
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // 4.3 執行正式刪除
            if (isset($_POST['delete_file']) && is_array($_POST['delete_file'])) {
                foreach ($_POST['delete_file'] as $fId) {
                    $stmtFind = $conn->prepare("SELECT * FROM file_set WHERE file_id = :id");
                    $stmtFind->execute([':id' => $fId]);
                    $fData = $stmtFind->fetch(PDO::FETCH_ASSOC);
                    if ($fData) {
                        for ($i = 1; $i <= 5; $i++) {
                            $link = "file_link{$i}";
                            if (!empty($fData[$link]) && file_exists("../" . $fData[$link])) {
                                @unlink("../" . $fData[$link]);
                            }
                        }
                        $stmtDel = $conn->prepare("DELETE FROM file_set WHERE file_id = :id");
                        $stmtDel->execute([':id' => $fId]);
                    }
                }
            }

            // 5. 動態圖片處理邏輯 (新增上傳)
            foreach ($moduleConfig['detailPage'] as $sheet) {
                foreach ($sheet['items'] as $item) {
                    if ($item['type'] == 'image_upload') {
                        $fName = $item['field'];
                        // 檢查是否有任何檔案被上傳（不只檢查第一個）
                        $hasAnyFile = false;
                        if (isset($_FILES[$fName]['name']) && is_array($_FILES[$fName]['name'])) {
                            foreach ($_FILES[$fName]['name'] as $fileName) {
                                if (!empty($fileName)) {
                                    $hasAnyFile = true;
                                    break;
                                }
                            }
                        }

                        if ($hasAnyFile) {
                            $targetW = $item['size'][0]['w'] ?? 800;
                            $targetH = $item['size'][0]['h'] ?? 600;
                            $dbFileType = $item['fileType'] ?? 'image';

                            $maxSizeMB = $item['maxSize'] ?? $item['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 2);

                            $image_result = image_process($conn, $_FILES[$fName], $_REQUEST[$fName . '_title'] ?? [], $menu_is, $isUpdate ? "edit" : "add", $targetW, $targetH);

                            // 取得目前該 fileType 的最大 file_sort 值
                            $maxSortStmt = $conn->prepare("SELECT COALESCE(MAX(file_sort), 0) as max_sort FROM file_set WHERE {$col_file_fk} = ? AND file_type = ?");
                            $maxSortStmt->execute([$redirectId, $dbFileType]);
                            $maxSort = $maxSortStmt->fetch(PDO::FETCH_ASSOC)['max_sort'];

                            for ($j = 1; $j < count($image_result); $j++) {
                                $newSort = $maxSort + $j;
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

            // 6. 處理簡單圖片上傳 (type='image')
            foreach ($moduleConfig['detailPage'] as $sheet) {
                foreach ($sheet['items'] as $item) {
                    if ($item['type'] == 'image') {
                        $fName = $item['field'];
                        // 檢查是否有任何檔案被上傳（不只檢查第一個）
                        $hasAnyFile = false;
                        if (isset($_FILES[$fName]['name']) && is_array($_FILES[$fName]['name'])) {
                            foreach ($_FILES[$fName]['name'] as $fileName) {
                                if (!empty($fileName)) {
                                    $hasAnyFile = true;
                                    break;
                                }
                            }
                        }

                        if ($hasAnyFile) {
                            $dbFileType = $item['fileType'] ?? 'simple_image';

                            // 取得 size 參數，如果沒有設定則預設為 0（不裁切）
                            $targetW = $item['size'][0]['w'] ?? 0;
                            $targetH = $item['size'][0]['h'] ?? 0;

                            // 統一使用 image_process 處理圖片上傳
                            $image_result = image_process($conn, $_FILES[$fName], $_REQUEST[$fName . '_title'] ?? [], $menu_is, $isUpdate ? "edit" : "add", $targetW, $targetH);

                            // 取得目前該 fileType 的最大 file_sort 值
                            $maxSortStmt = $conn->prepare("SELECT COALESCE(MAX(file_sort), 0) as max_sort FROM file_set WHERE {$col_file_fk} = ? AND file_type = ?");
                            $maxSortStmt->execute([$redirectId, $dbFileType]);
                            $maxSort = $maxSortStmt->fetch(PDO::FETCH_ASSOC)['max_sort'];

                            for ($j = 1; $j < count($image_result); $j++) {
                                $newSort = $maxSort + $j;
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

            // 7. 處理一般檔案上傳 (file_upload)
            foreach ($moduleConfig['detailPage'] as $sheet) {
                foreach ($sheet['items'] as $item) {
                    if ($item['type'] == 'file_upload') {
                        $fName = $item['field'];
                        // 檢查是否有任何檔案被上傳（不只檢查第一個）
                        $hasAnyFile = false;
                        if (isset($_FILES[$fName]['name']) && is_array($_FILES[$fName]['name'])) {
                            foreach ($_FILES[$fName]['name'] as $fileName) {
                                if (!empty($fileName)) {
                                    $hasAnyFile = true;
                                    break;
                                }
                            }
                        }

                        if ($hasAnyFile) {
                            $dbFileType = $item['fileType'] ?? 'file';

                            // 新格式：format 在外層，maxSize 在 size 內
                            $format = $item['format'] ?? '*';
                            $maxSize = $item['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 10);

                            // 構建 accept 參數傳給 file_process
                            $acceptParam = [
                                'format' => $format,
                                'maxSize' => $maxSize
                            ];

                            // 使用 file_process 代替 image_process
                            $file_result = file_process($conn, $_FILES[$fName], $_REQUEST[$fName . '_title'] ?? [], $menu_is, $isUpdate ? "edit" : "add", $acceptParam);

                            // 取得目前該 fileType 的最大 file_sort 值
                            $maxSortStmt = $conn->prepare("SELECT COALESCE(MAX(file_sort), 0) as max_sort FROM file_set WHERE {$col_file_fk} = ? AND file_type = ?");
                            $maxSortStmt->execute([$redirectId, $dbFileType]);
                            $maxSort = $maxSortStmt->fetch(PDO::FETCH_ASSOC)['max_sort'];

                            for ($j = 1; $j < count($file_result); $j++) {
                                $newSort = $maxSort + $j;
                                $stmtFile = $conn->prepare("INSERT INTO file_set (file_name, file_link1, file_type, {$col_file_fk}, file_title, file_sort) VALUES (?,?,?,?,?,?)");
                                $stmtFile->execute([
                                    $file_result[$j][0],
                                    $file_result[$j][1],
                                    $dbFileType,
                                    $redirectId,
                                    $file_result[$j][2],
                                    $newSort
                                ]);
                            }
                        }
                    }
                }
            }

            // 權限群組管理
            if ($module === 'authorityCate') {
                require_once(__DIR__ . '/includes/authorityHelper.php');
                $groupId = $isUpdate ? $d_id : $redirectId;
                saveGroupPermissions($conn, $groupId, $_POST);
            }

            // 【新增】data_taxonomy_map 多對多分類管理 (必須在排序重整之前執行)
            $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;
            
            // 【修正】處理連動分類（陣列）的情況
            $categoryIdToSave = null;
            if ($categoryField) {
                if (is_array($categoryField)) {
                    // 連動分類：使用最後一層（最具體的）分類
                    foreach (array_reverse($categoryField) as $field) {
                        if (isset($fields[$field]) && $fields[$field]) {
                            $categoryIdToSave = $fields[$field];
                            break;
                        }
                    }
                } else {
                    // 單一分類
                    $categoryIdToSave = isset($fields[$categoryField]) ? $fields[$categoryField] : null;
                }
            }
            
            if ($configUseTaxonomyMapSort && $categoryIdToSave && hasTaxonomyMapTable($conn)) {
                // 儲存產品與分類的多對多關係
                saveTaxonomyMap($conn, $redirectId, $categoryIdToSave, [
                    'menuKey' => $menuKey,
                    'menuValue' => $menuValue,
                    'tableName' => $tableName,
                    'lang' => $fields['lang'] ?? null
                ]);

                // 【重要】分類變更時，只重排分類排序，不重排主表排序
                // saveTaxonomyMap 已經處理了分類排序的重排，這裡不需要再呼叫 UnifiedSortManager
            } else {
                // 沒有使用 taxonomy map 或沒有分類變更時，才重排主表排序
                // Step 1: 【新增】使用統一排序管理器重新整理排序
                require_once(__DIR__ . '/includes/SortReorganizer.php');
                require_once(__DIR__ . '/includes/UnifiedSortManager.php');

                UnifiedSortManager::updateAfterDataChange(
                    $conn,
                    $moduleConfig,
                    $redirectId,
                    [
                        'lang' => $fields['lang'] ?? null
                    ]
                );
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

                        if ($field['type'] !== 'image' && $field['type'] !== 'file') {
                            continue;
                        }

                        $isImage = ($field['type'] === 'image');
                        $mediaFieldName = $field['name'];
                        $fileType = $field['fileType'] ?? ($isImage ? 'image' : 'file');
                        $targetW = $field['size'][0]['w'] ?? ($isImage ? 800 : 0);
                        $targetH = $field['size'][0]['h'] ?? ($isImage ? 600 : 0);
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
                            $uploadKey = "{$mediaFieldName}_upload";

                            if (
                                !isset($groupFiles[$uploadKey]) ||
                                empty($groupFiles[$uploadKey])
                            ) {
                                continue;
                            }

                            // 判斷是單媒體還是多媒體模式
                            if ($isMultiple && is_array($groupFiles[$uploadKey])) {
                                foreach ($groupFiles[$uploadKey] as $imgIndex => $fileName) {
                                    if (empty($fileName)) continue;

                                    $singleFileArray = [
                                        'name' => [$fileName],
                                        'type' => [$_FILES[$fieldName]['type'][$uid][$uploadKey][$imgIndex]],
                                        'tmp_name' => [$_FILES[$fieldName]['tmp_name'][$uid][$uploadKey][$imgIndex]],
                                        'error' => [$_FILES[$fieldName]['error'][$uid][$uploadKey][$imgIndex]],
                                        'size' => [$_FILES[$fieldName]['size'][$uid][$uploadKey][$imgIndex]],
                                    ];

                                    $mediaTitle = $_POST[$fieldName][$realIndex][$mediaFieldName][$imgIndex]['title'] ?? '';

                                    if ($isImage) {
                                        $media_result = image_process($conn, $singleFileArray, [$mediaTitle], $menu_is, 'add', $targetW, $targetH);
                                    } else {
                                        $accept = ['format' => $field['format'] ?? '*', 'maxSize' => $field['maxSize'] ?? (128)];
                                        $media_result = file_process($conn, $singleFileArray, [$mediaTitle], $menu_is, 'add', $accept);
                                    }

                                    if (count($media_result) <= 1) continue;

                                    for ($j = 1; $j < count($media_result); $j++) {
                                        $stmtFile = $conn->prepare("
                                            INSERT INTO file_set
                                            (file_name, file_link1, file_link2, file_link3,
                                            file_type, {$col_file_fk}, file_title, file_show_type)
                                            VALUES (?,?,?,?,?,?,?,?)
                                        ");
                                        if ($stmtFile->execute([
                                            $media_result[$j][0],
                                            $media_result[$j][1],
                                            $media_result[$j][2] ?? '',
                                            $media_result[$j][3] ?? '',
                                            $fileType,
                                            $redirectId,
                                            $media_result[$j][4] ?? $media_result[$j][2],
                                            $media_result[$j][5] ?? 0
                                        ])) {
                                            $newFileId = $conn->lastInsertId();
                                            if (!isset($_POST[$fieldName][$realIndex][$mediaFieldName])) $_POST[$fieldName][$realIndex][$mediaFieldName] = [];
                                            if (!isset($_POST[$fieldName][$realIndex][$mediaFieldName][$imgIndex])) $_POST[$fieldName][$realIndex][$mediaFieldName][$imgIndex] = [];
                                            $_POST[$fieldName][$realIndex][$mediaFieldName][$imgIndex]['file_id'] = $newFileId;
                                        }
                                    }
                                }
                            } else {
                                // 單媒體模式
                                if (empty($groupFiles[$uploadKey])) continue;

                                $singleFileArray = [
                                    'name' => [$_FILES[$fieldName]['name'][$uid][$uploadKey]],
                                    'type' => [$_FILES[$fieldName]['type'][$uid][$uploadKey]],
                                    'tmp_name' => [$_FILES[$fieldName]['tmp_name'][$uid][$uploadKey]],
                                    'error' => [$_FILES[$fieldName]['error'][$uid][$uploadKey]],
                                    'size' => [$_FILES[$fieldName]['size'][$uid][$uploadKey]],
                                ];

                                $mediaTitle = $_POST[$fieldName][$realIndex][$mediaFieldName]['title'] ?? '';

                                if ($isImage) {
                                    $media_result = image_process($conn, $singleFileArray, [$mediaTitle], $menu_is, 'add', $targetW, $targetH);
                                } else {
                                    $accept = ['format' => $field['format'] ?? '*', 'maxSize' => $field['maxSize'] ?? (128)];
                                    $media_result = file_process($conn, $singleFileArray, [$mediaTitle], $menu_is, 'add', $accept);
                                }

                                if (count($media_result) <= 1) continue;

                                for ($j = 1; $j < count($media_result); $j++) {
                                    $stmtFile = $conn->prepare("
                                        INSERT INTO file_set
                                        (file_name, file_link1, file_link2, file_link3,
                                        file_type, {$col_file_fk}, file_title, file_show_type)
                                        VALUES (?,?,?,?,?,?,?,?)
                                    ");
                                    if ($stmtFile->execute([
                                        $media_result[$j][0],
                                        $media_result[$j][1],
                                        $media_result[$j][2] ?? '',
                                        $media_result[$j][3] ?? '',
                                        $fileType,
                                        $redirectId,
                                        $media_result[$j][4] ?? $media_result[$j][2],
                                        $media_result[$j][5] ?? 0
                                    ])) {
                                        $newFileId = $conn->lastInsertId();
                                        $_POST[$fieldName][$realIndex][$mediaFieldName]['file_id'] = $newFileId;
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

                                // 檢查是否為媒體欄位 (圖片或檔案)
                                $isMediaField = false;
                                foreach ($item['fields'] as $field) {
                                    if (($field['type'] === 'image' || $field['type'] === 'file') && $field['name'] === $key) {
                                        $isMediaField = true;
                                        $isMultiple = $field['multiple'] ?? false;

                                        if ($isMultiple && is_array($value)) {
                                            // 多媒體模式
                                            $mediaArray = [];
                                            foreach ($value as $idx => $mediaData) {
                                                if (is_array($mediaData) && isset($mediaData['file_id']) && !empty($mediaData['file_id'])) {
                                                    $mediaArray[] = [
                                                        'file_id' => $mediaData['file_id'],
                                                        'title'   => $mediaData['title'] ?? ($mediaData['file_info']['file_title'] ?? '')
                                                    ];
                                                }
                                            }
                                            if (!empty($mediaArray)) {
                                                $groupData[$key] = $mediaArray;
                                            }
                                        } else {
                                            // 單媒體模式
                                            if (is_array($value)) {
                                                // 包含 file_id_hidden 的邏輯
                                                $fId = $value['file_id'] ?? $value['file_id_hidden'] ?? '';
                                                if (!empty($fId)) {
                                                    $groupData[$key] = [
                                                        'file_id' => $fId,
                                                        'title'   => $value['title'] ?? ''
                                                    ];
                                                }
                                            } else {
                                                // 容錯
                                                $fileIdKey = $key . '_file_id';
                                                if (isset($group[$fileIdKey])) {
                                                    $groupData[$key] = ['file_id' => $group[$fileIdKey]];
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }

                                // 如果不是媒體欄位，且不是特殊結尾，當作文字欄位處理
                                if (!$isMediaField && strpos($key, '_file_id') === false && strpos($key, '_file_id_hidden') === false) {
                                    $groupData[$key] = $value;
                                }
                            }

                            $dynamicData[$groupIndex] = $groupData;
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

            // -----------------------------------------------------------------------
            // 【新增】子網站工廠 Hook
            // -----------------------------------------------------------------------
            if ($module === 'websites') {
                SubsiteHelper::factory($conn, $redirectId, $_POST);
            }

            // -----------------------------------------------------------------------
            // 【新增】儲存成功後,刪除對應的草稿
            // -----------------------------------------------------------------------
            if (isset($_SESSION['MM_LoginAccountUserId'])) {
                $userId = $_SESSION['MM_LoginAccountUserId'];
                $deleteDraftSql = "DELETE FROM cms_drafts WHERE user_id = :uid AND module = :mod AND record_id = :rid";
                $deleteDraftStmt = $conn->prepare($deleteDraftSql);
                $deleteDraftStmt->execute([
                    ':uid' => $userId,
                    ':mod' => $module,
                    ':rid' => $redirectId
                ]);
            }

            // -----------------------------------------------------------------------
            // 重定向處理
            // -----------------------------------------------------------------------
            // 【修正】統一使用 PORTAL_AUTH_URL，確保與 Clean URL 格式一致，避免跳回實體路徑
            $redirectUrl = PORTAL_AUTH_URL . "tpl={$module}/list";

            $queryParams = [];
            
            // 【修改】動態處理多層級分類重定向參數
            if ($categoryField) {
                $categoryFields = is_array($categoryField) ? $categoryField : [$categoryField];
                foreach ($categoryFields as $index => $field) {
                    $paramName = 'selected' . ($index + 1);
                    if (isset($fields[$field]) && $fields[$field] !== '' && $fields[$field] !== 'all') {
                        $queryParams[$paramName] = $fields[$field];
                    }
                }
            }
            
            $parentIdField = $customCols['parent_id'] ?? 'parent_id';
            if (isset($fields[$parentIdField]) && $fields[$parentIdField] > 0) {
                $queryParams['parent_id'] = $fields[$parentIdField];
            }
            
            // 保持語系
            if ($autoLangContext) {
                $queryParams['language'] = $autoLangContext;
            }
            
            // 如果是從垃圾桶來的，跳轉回去也帶著 trash=1
            if (isset($_GET['trash_view']) && $_GET['trash_view'] == 1) {
                $queryParams['trash'] = 1;
            }

            if (!empty($queryParams)) {
                $redirectUrl .= "?" . http_build_query($queryParams);
            }

            header("Location: " . $redirectUrl);
            exit;

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            // 發生錯誤時，直接終止並顯示錯誤，避免被 HTML 覆蓋
            die("Error: " . $e->getMessage());
        }
    }
}
// =======================================================================
// 存檔邏輯結束，接著才是準備顯示 HTML
// =======================================================================

$action = $d_id > 0 ? 'edit' : 'add';
$pageTitle = $action == 'edit' ? '修改' : '新增';

// 如果是編輯模式，載入現有資料
$rowData = [];
if ($action == 'edit') {
    $query = "SELECT * FROM {$tableName} WHERE {$primaryKey} = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $d_id]);
    $rowData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rowData)
        die('錯誤：找不到資料');

    if (isset($rowData['d_content'])) {
        $rowData['d_content'] = cms_process_content_with_mixed_mode($rowData['d_content']);
    }

    $imageQuery = "SELECT * FROM file_set WHERE {$col_file_fk} = :id ORDER BY file_sort ASC";
    $imageStmt = $conn->prepare($imageQuery);
    $imageStmt->execute([':id' => $d_id]);
    $rowData['images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    // 【新增】載入動態欄位資料
    $dynamicFieldsHelper = new DynamicFieldsHelper($conn);
    foreach ($moduleConfig['detailPage'] as $sheet) {
        foreach ($sheet['items'] as $item) {
            if ($item['type'] === 'dynamic_fields') {
                $fieldName = $item['field'];
                $fieldGroup = $item['fieldGroup'] ?? 'd_data1';
                $rowData[$fieldName] = $dynamicFieldsHelper->getFields($d_id, $fieldGroup);
            }
        }
    }

    // 【新增】處理欄位預設值 (如果資料庫中為空且配置中有 value)
    // 優先順序：手動填寫 > 資料庫現有值 > 配置預設值
    foreach ($moduleConfig['detailPage'] as $sheet) {
        foreach ($sheet['items'] as $item) {
            if (isset($item['field']) && isset($item['value'])) {
                $field = $item['field'];
                // 只有在資料庫中該欄位為空或 NULL 時，才使用配置中的預設值
                // 如果使用者有手動填寫過（非空字串），就保留使用者填寫的值
                if (!isset($rowData[$field]) || $rowData[$field] === null || $rowData[$field] === '') {
                    $rowData[$field] = $item['value'];
                }
            }
        }
    }
} else {
    // --- [新增] 新增模式：從 URL 預填資料 (例如從分類過濾列表點擊新增) ---
    $rowData = ['images' => []];
    
    // 1. 處理父層 ID (自關聯層級，如分類管理)
    if ($hasHierarchicalNav && $parentIdField) {
        if (isset($_GET['parent_id']) && $_GET['parent_id'] !== '') {
            $rowData[$parentIdField] = (int)$_GET['parent_id'];
        } elseif (isset($_GET['preselect_category']) && $_GET['preselect_category'] !== '') {
            $rowData[$parentIdField] = (int)$_GET['preselect_category'];
        }
    }

    // 2. 處理分類 (selectedN 或 preselect_category，如商品所屬分類)
    $configCategoryField = $moduleConfig['listPage']['categoryField'] ?? null;
    if ($configCategoryField) {
        $categoryFields = is_array($configCategoryField) ? $configCategoryField : [$configCategoryField];
        
        // A. 處理多層級參數 selected1, selected2...
        foreach ($categoryFields as $index => $field) {
            $paramName = 'selected' . ($index + 1);
            if (isset($_GET[$paramName]) && $_GET[$paramName] !== '' && $_GET[$paramName] !== 'all') {
                $rowData[$field] = (int)$_GET[$paramName];
            }
        }
        
        // B. 相容舊款單一分類參數 preselect_category (如果該欄位還沒被填，且非多欄位模式)
        if (isset($_GET['preselect_category']) && $_GET['preselect_category'] !== '') {
            $firstField = is_array($configCategoryField) ? $configCategoryField[0] : $configCategoryField;
            if (!isset($rowData[$firstField])) {
                $rowData[$firstField] = (int)$_GET['preselect_category'];
            }
        }
    }
    
    // 3. 處理配置中的預設值 (針對還沒被預填的欄位)
    foreach ($moduleConfig['detailPage'] as $sheet) {
        foreach ($sheet['items'] as $item) {
            if (isset($item['field']) && isset($item['value'])) {
                $field = $item['field'];
                if (!is_array($field) && !isset($rowData[$field])) {
                    $rowData[$field] = $item['value'];
                }
            }
        }
    }
}

// 設定表單 Action (Rewrite URL)
$queryParams = $_GET;
if (isset($queryParams['module']))
    unset($queryParams['module']);
if (isset($queryParams['action']))
    unset($queryParams['action']);
$queryString = http_build_query($queryParams);
$editFormAction = PORTAL_AUTH_URL . "tpl={$module}/detail";
if (!empty($queryString)) {
    $editFormAction .= "?" . $queryString;
}

$parentId = 0;
if ($hasHierarchicalNav && $parentIdField) {
    $parentId = $rowData[$parentIdField] ?? 0;
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
                    <h2><?php echo $moduleConfig['moduleName'] . $pageTitle; ?></h2>

                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <?php
                            require_once(__DIR__ . '/includes/menuHelper.php');
                            $currentPageTitle = $isTrashView ? '回收桶查看' : $pageTitle;
                            echo renderBreadcrumbsHtml($conn, $module, $currentPageTitle);
                            ?>
                        </ol>

                        <a class="sidebar-right-toggle" data-open="sidebar-right" style="pointer-events: none;"></a>
                    </div>
                </header>

                <form class="ecommerce-form" action="<?php echo $editFormAction; ?>" method="POST" enctype="multipart/form-data" name="form1" id="form1" <?php if ($isTrashView || $tableName === 'message_set'): ?>onsubmit="return false;" <?php endif; ?>>
                    <div class="row">
                        <div class="col">
                            <div class="datatable-header">
                                <div class="row align-items-center mb-3">
                                    <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                                        <?php
                                        if ($hasHierarchicalNav && !$isTrashView) {
                                            if ($parentId !== null && $parentId > 0) {
                                                $parentCol = $moduleConfig['cols']['parent_id'];
                                                $primaryKey = $moduleConfig['primaryKey'];
                                                $titleCol = $moduleConfig['cols']['title'];

                                                $breadcrumbQuery = "SELECT {$primaryKey}, {$parentCol}, {$titleCol} FROM {$tableName} WHERE {$primaryKey} = :currentId";
                                                $breadcrumbStmt = $conn->prepare($breadcrumbQuery);
                                                $breadcrumbStmt->execute([':currentId' => $parentId]);
                                                $currentItem = $breadcrumbStmt->fetch(PDO::FETCH_ASSOC);

                                                if ($currentItem) {
                                                    $backParentId = $currentItem[$parentCol];
                                                    $backUrl = PORTAL_AUTH_URL . "tpl={$module}/list" . ($backParentId > 0 ? "?parent_id={$backParentId}" : "");
                                                    echo "<span class='me-3'>當前位置：{$currentItem[$titleCol]}</span>";
                                                }
                                            } else {
                                                echo '<span class="me-3">當前位置：頂層選單</span>';
                                            }
                                        }
                                        ?>
                                        <?php
                                        // --- [新增] 計算返回列表的網址，包含層級與分類參數 ---
                                        $backToListUrl = PORTAL_AUTH_URL . "tpl={$module}/list";
                                        $backParams = [];

                                        // 1. 如果有父層 ID，帶入網址 (回到該層級)
                                        if (isset($parentId) && $parentId > 0) {
                                            $backParams['parent_id'] = $parentId;
                                        }

                                        // 2. 如果網址原本有帶預選分類 (例如從某分類點進新增)，也帶回去
                                        if (isset($_GET['preselect_category'])) {
                                            $backParams['selected1'] = $_GET['preselect_category'];
                                        }
                                        // 3. 如果是編輯模式且資料本身有分類，也可以帶回去 (視需求而定)
                                        elseif (isset($categoryField) && isset($rowData[$categoryField])) {
                                            $backParams['selected1'] = $rowData[$categoryField];
                                        }

                                        // 組合參數
                                        if (!empty($backParams)) {
                                            $backToListUrl .= "?" . http_build_query($backParams);
                                        }
                                        ?>
                                        <a href="<?php echo $backToListUrl; ?>" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4"><i class="fas fa-arrow-left"></i> 返回</a>
                                        <?php if (!$isTrashView): ?>
                                            <?php if ($tableName !== 'message_set' || isset($moduleConfig['statusActive']) && $moduleConfig['statusActive'] == true): ?>
                                                <?php echo renderSubmitButton('儲存 (alt+s)'); ?>
                                            <?php endif; ?>
                                            <?php if ($module === 'websites' && $d_id > 0): ?>
                                                <?php 
                                                $isPushed = !empty($rowData['d_data7']);
                                                $btnLabel = $isPushed ? '已佈署到 Git' : 'Git 設定';
                                                $btnAttr = $isPushed ? 'disabled' : 'onclick="handleGitPush(this)"';
                                                $btnClass = $isPushed ? 'btn btn-secondary' : 'btn btn-dark';
                                                ?>
                                                <button type="button" id="gitPushBtn" class="<?= $btnClass ?> btn-md font-weight-semibold btn-py-2 px-4" data-id="<?= $d_id ?>" <?= $btnAttr ?>>
                                                    <i class="fab fa-github"></i> <?= $btnLabel ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$isTrashView): ?>
                                <?php if ($tableName === 'message_set' && $moduleConfig['statusActive'] == false): ?>
                                    <div style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;"><i class="fas fa-info-circle"></i> 此項目為表單，無法編輯。</div>
                                <?php else: ?>
                                    <input type="hidden" name="<?php echo ($action == 'edit' ? 'MM_update' : 'MM_insert'); ?>" value="form1" />
                                    <input type="hidden" name="<?php echo $primaryKey; ?>" value="<?php echo $d_id; ?>" />
                                    <?php if ($tableHasLang): ?>
                                        <input type="hidden" name="lang" value="<?= $autoLangContext ?>" />
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;"><i class="fas fa-info-circle"></i> 此項目在回收桶中，無法編輯。</div>
                            <?php endif; ?>

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
                                                    <?php $i = 0;
                                                    foreach ($moduleConfig['detailPage'] as $index => $sheet):
                                                        $i++; ?>
                                                        <a class="nav-link <?php echo $index == 0 ? 'active' : ''; ?>" id="tab-<?= $i; ?>" data-bs-toggle="pill" data-bs-target="#tab<?= $i; ?>" role="tab" aria-controls="tab<?= $i; ?>" aria-selected="true"><i class="bx bx-cog me-2"></i> <?= $sheet['sheetTitle']; ?></a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-lg-3-5 col-xl-4-5">
                                            <div class="tab-content" id="tabContent">

                                                <?php $i = 0;
                                                foreach ($moduleConfig['detailPage'] as $index => $sheet):
                                                    $i++; ?>
                                                    <div class="tab-pane fade <?php echo $index == 0 ? 'show' : ''; ?> <?php echo $index == 0 ? 'active' : ''; ?>" id="tab<?= $i; ?>" role="tabpanel" aria-labelledby="tab<?= $i; ?>">
                                                        <?php foreach ($sheet['items'] as $item):
                                                            $field = $item['field'] ?? '';
                                                            $fieldValue = '';
                                                            
                                                            if (is_array($field)) {
                                                                // 如果 field 是陣列 (多欄位模式)，組合值為陣列
                                                                $fieldValue = [];
                                                                foreach ($field as $f) {
                                                                    $fieldValue[] = $rowData[$f] ?? '';
                                                                }
                                                            } else {
                                                                $fieldValue = $rowData[$field] ?? '';
                                                            }

                                                            $images = $rowData['images'] ?? [];
                                                            echo renderFormField($item, $fieldValue, $rowData);
                                                        endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div id="delete_file_container"></div>
                </form>

                <?php
                if (isset($tableName) && $tableName === 'message_set' && isset($rowData['m_email']) && $moduleConfig['replyActive']):
                    // 查詢是否有回覆紀錄
                    $replyData = null;
                    if (isset($rowData['m_reply']) && $rowData['m_reply'] == 1) {
                        try {
                            $replySql = "SELECT * FROM message_reply WHERE m_id = :id ORDER BY r_id DESC LIMIT 1";
                            $replyStmt = $conn->prepare($replySql);
                            $replyStmt->execute([':id' => $d_id]);
                            $replyData = $replyStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            // ignore error
                        }
                    }

                    $isReplied = !empty($replyData);
                    $replySubject = $isReplied ? $replyData['r_subject'] : ''; // Default subject or fetched
                    $replyContent = $isReplied ? $replyData['r_content'] : '';
                    $readonlyAttr = $isReplied ? 'readonly' : '';
                    ?>
                    <div class="row mt-5">
                        <div class="col-lg-12">
                            <form id="replyForm" class="form-horizontal">
                                <section class="card">
                                    <header class="card-header">
                                        <h2 class="card-title">回覆客戶郵件</h2>
                                        <p class="card-subtitle">
                                            將會寄送郵件至：<?php echo htmlspecialchars($rowData['m_email']); ?>
                                            <?php if ($isReplied): ?>
                                                <span class="badge badge-success ml-2">已於 <?php echo $replyData['r_date']; ?> 回覆</span>
                                            <?php endif; ?>
                                        </p>
                                    </header>
                                    <div class="card-body">
                                        <input type="hidden" name="id" value="<?php echo $d_id; ?>">

                                        <div class="form-group row pb-3">
                                            <label class="col-sm-3 control-label text-sm-end pt-2">郵件主旨 <span class="required">*</span></label>
                                            <div class="col-sm-9">
                                                <input type="text" name="reply_subject" class="form-control" placeholder="請輸入主旨" required value="<?php echo htmlspecialchars($replySubject); ?>" <?php echo $readonlyAttr; ?> />
                                            </div>
                                        </div>

                                        <div class="form-group row pb-2">
                                            <label class="col-sm-3 control-label text-sm-end pt-2">回覆內容 <span class="required">*</span></label>
                                            <div class="col-sm-9">
                                                <textarea name="reply_message" rows="8" class="form-control" placeholder="請輸入回覆內容..." required <?php echo $readonlyAttr; ?>><?php echo htmlspecialchars($replyContent); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <footer class="card-footer">
                                        <div class="row justify-content-end">
                                            <div class="col-sm-9">
                                                <?php if (!$isReplied): ?>
                                                    <button type="submit" class="btn btn-primary" id="btnSendReply"><i class="fas fa-paper-plane"></i> 發送回覆</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-default" disabled><i class="fas fa-check"></i> 已發送回覆</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </footer>
                                </section>
                            </form>
                        </div>
                    </div>

                    <script>
                        $(document).ready(function () {
                            $('#replyForm').on('submit', function (e) {
                                e.preventDefault();

                                var $btn = $('#btnSendReply');
                                var originalText = $btn.html();

                                // 禁用按鈕防止重複提交
                                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 發送中...');

                                $.ajax({
                                    url: 'ajax_reply_mail.php',
                                    type: 'POST',
                                    dataType: 'json',
                                    data: $(this).serialize(),
                                    success: function (response) {
                                        if (response.status === 'success') {
                                            Swal.fire({
                                                icon: 'success',
                                                title: '發送成功',
                                                text: response.message
                                            }).then((result) => {
                                                // 重新整理頁面以更新列表或狀態
                                                location.reload();
                                                // 或者如果不想重整，可以只清空表單
                                                // $('#replyForm')[0].reset();
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: '發送失敗',
                                                text: response.message
                                            });
                                        }
                                    },
                                    error: function () {
                                        Swal.fire({
                                            icon: 'error',
                                            title: '系統錯誤',
                                            text: '連線失敗，請稍後再試'
                                        });
                                    },
                                    complete: function () {
                                        $btn.prop('disabled', false).html(originalText);
                                    }
                                });
                            });
                        });
                    </script>
    </section>
</body>

</html>

<?php endif; // 補上遺失的 if(isset($tableName)) 閉合 ?>

<script type="text/javascript">
    <?php if ($isTrashView): ?>
        $('#form1').on('submit', function (e) {
            e.preventDefault();
            return false;
        });
        $('button[type="submit"], input[type="submit"]').prop('disabled', true).css('opacity', '0.5');
    <?php endif; ?>
</script>

<script type="text/javascript">
    $(document).ready(function () {
        // 初始化圖片拖曳排序功能
        <?php foreach ($moduleConfig['detailPage'] as $sheet): ?>
            <?php foreach ($sheet['items'] as $item): ?>
                <?php if ($item['type'] === 'image_upload'): ?>
                    if ($("#draggable_<?php echo $item['field']; ?>")[0] != undefined) {
                        var sortable_<?php echo $item['field']; ?> = Sortable.create($("#draggable_<?php echo $item['field']; ?>")[0], {
                            animation: 100,
                            handle: ".drag-handle",
                            dataIdAttr: 'data-id',
                            ghostClass: "ryder-ghost",
                            chosenClass: "ryder-chosen",
                            onSort: function (e) {
                                $.ajax({
                                    data: {
                                        ids: sortable_<?php echo $item['field']; ?>.toArray()
                                    },
                                    url: "image_sort.php",
                                    type: "POST",
                                    success: function (res) { }
                                });
                            }
                        });
                    }
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>

        // Fancybox 設定
        $("a[rel^='group']").fancybox({
            autoSize: true, openEffect: 'elastic', closeEffect: 'elastic',
            helpers: { overlay: { css: { 'background': 'rgba(0, 0, 0, 0.7)' } } }
        });
        $("a.fancyboxImg").fancybox({
            autoSize: true, openEffect: 'elastic', closeEffect: 'elastic',
            helpers: { overlay: { css: { 'background': 'rgba(0, 0, 0, 0.7)' } } }
        });
        $("a.fancyboxEdit").fancybox({
            type: 'ajax', openEffect: 'fade', closeEffect: 'fade', autoSize: true,
            helpers: { overlay: { css: { 'background': 'rgba(0, 0, 0, 0.7)' } } }
        });
        $("a.fancyboxUpload").fancybox({
            type: 'iframe', openEffect: 'fade', closeEffect: 'fade', autoSize: false, width: '1000', closeBtn: true,
            helpers: { overlay: { closeClick: true, css: { 'background': 'rgba(0, 0, 0, 0.7)' } } },
            afterClose: function () { window.location.reload(); }
        });
    });

    function markImageForDeletion(fileId) {
        const uploaderId = 'ex_' + fileId;
        if ($('#del_input_' + fileId).length > 0) return;
        $('#croppedImagePreview' + uploaderId).attr('src', 'crop/demo.jpg');
        $('#title_' + uploaderId).val('');
        $('#remove_btn_' + fileId).hide();
        $('#fileNameDisplay' + uploaderId).text('未選擇').hide();
        if (window.uploaders && window.uploaders[uploaderId]) {
            window.uploaders[uploaderId].reset();
        }
        $('#delete_file_container').append('<input type="hidden" name="delete_file[]" value="' + fileId + '" id="del_input_' + fileId + '">');
    }

    function deleteImageItem(fileId) {
        Swal.fire({
            title: '確定刪除?', text: '此操作將會刪除這張圖片', icon: 'warning',
            showCancelButton: true, confirmButtonText: '刪除', cancelButtonText: '取消'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#img_item_' + fileId).remove();
                const uploaderId = 'ex_' + fileId;
                if (window.uploaders && window.uploaders[uploaderId]) {
                    delete window.uploaders[uploaderId];
                }
                if ($('#del_input_' + fileId).length === 0) {
                    $('#delete_file_container').append('<input type="hidden" name="delete_file[]" value="' + fileId + '" id="del_input_' + fileId + '">');
                }
                Swal.fire('已刪除', '圖片已從列表中移除', 'success');
            }
        });
    }

    $(document).on('imageUpdated', '.hidden-file-input', function (e, id) {
        const fileId = id.toString().replace('ex_', '');
        $('#remove_btn_' + fileId).show();
        $('#del_input_' + fileId).remove();
        $('#croppedImagePreview' + id).css('opacity', '1');
    });
</script>



<!-- Draft System Script -->
<?php if (defined('DRAFT_SYSTEM_ENABLED') && DRAFT_SYSTEM_ENABLED): ?>
    <script>
        $(document).ready(function () {
            const MODULE = '<?= $module ?>';
            const RECORD_ID = '<?= $d_id ?>';
            const TARGET_TABLE = '<?= $tableName ?>';
            const ACTION = '<?= $action ?>';

            // 自動暫存設定（從 config.php 讀取）
            let autoSaveTimer = null;
            let lastSavedData = null;  // 記錄上次儲存的表單資料
            let hasDraftInDatabase = false;  // 記錄資料庫中是否已有草稿
            const AUTO_SAVE_INTERVAL = <?= defined('DRAFT_AUTO_SAVE_INTERVAL') ? DRAFT_AUTO_SAVE_INTERVAL : 300000 ?>; // 從 config.php 讀取
            const SHOW_CONSOLE_LOG = <?= defined('DRAFT_SHOW_CONSOLE_LOG') && DRAFT_SHOW_CONSOLE_LOG ? 'true' : 'false' ?>;
            let isFormChanged = false;

            // 1. 檢查是否有草稿
            checkDraft();

            // 2. 移除手動草稿按鈕，改為自動暫存
            // (如果需要手動按鈕，可保留下方註解的程式碼)
            /*
            const $submitBtn = $('.btn-primary[type="submit"], input.btn-primary[type="submit"]').last();
            if ($submitBtn.length > 0) {
                $('<button type="button" class="btn btn-warning btn-md font-weight-semibold btn-py-2 px-4 ms-2" onclick="saveDraft()"><i class="fas fa-save me-1"></i> 暫存草稿</button>').insertAfter($submitBtn);
            } else {
                $('.btn-primary i.fa-arrow-left').parent().after('<button type="button" class="btn btn-warning btn-md font-weight-semibold btn-py-2 px-4 ms-2" onclick="saveDraft()"><i class="fas fa-save me-1"></i> 暫存草稿</button>');
            }
            */

            // 3. 監聽表單變更
            $('#form1 input, #form1 textarea, #form1 select').on('change input', function () {
                if (SHOW_CONSOLE_LOG) console.log('📝 偵測到表單變更:', $(this).attr('name'));
                isFormChanged = true;
                resetAutoSaveTimer();
            });

            // 4. 監聽 CKEditor 變更
            if (typeof CKEDITOR !== 'undefined') {
                for (let instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].on('change', function () {
                        if (SHOW_CONSOLE_LOG) console.log('📝 偵測到 CKEditor 變更:', instance);
                        isFormChanged = true;
                        resetAutoSaveTimer();
                    });
                }
            }

            // 5. 啟動自動暫存計時器
            function resetAutoSaveTimer() {
                if (SHOW_CONSOLE_LOG) console.log('⏰ 重置自動暫存計時器 (將在 ' + (AUTO_SAVE_INTERVAL / 1000) + ' 秒後執行)');
                if (autoSaveTimer) {
                    clearTimeout(autoSaveTimer);
                }
                autoSaveTimer = setTimeout(function () {
                    if (isFormChanged) {
                        autoSaveDraft();
                    }
                }, AUTO_SAVE_INTERVAL);
            }

            // 6. 自動暫存函數
            function autoSaveDraft() {
                if (SHOW_CONSOLE_LOG) {
                    console.log('🔄 觸發自動暫存...');
                    console.log('表單已變更:', isFormChanged);
                }
                saveDraft(true); // true 表示靜默模式 (不顯示提示)
                isFormChanged = false;
            }

            // 7. 頁面離開前暫存
            $(window).on('beforeunload', function () {
                if (isFormChanged) {
                    // 同步方式暫存 (使用 navigator.sendBeacon 或 synchronous AJAX)
                    const formData = collectFormData();
                    const data = new FormData();
                    data.append('module', MODULE);
                    data.append('record_id', RECORD_ID);
                    data.append('target_table', TARGET_TABLE);
                    data.append('form_data', JSON.stringify(formData));
                    data.append('url_params', window.location.search);

                    // 使用 sendBeacon 進行非阻塞式發送
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('ajax_save_draft.php', data);
                    } else {
                        // 備用方案：同步 AJAX
                        $.ajax({
                            url: 'ajax_save_draft.php',
                            type: 'POST',
                            async: false,
                            data: {
                                module: MODULE,
                                record_id: RECORD_ID,
                                target_table: TARGET_TABLE,
                                form_data: JSON.stringify(formData),
                                url_params: window.location.search
                            }
                        });
                    }
                }
            });

            // 8. 初始啟動計時器
            resetAutoSaveTimer();
        });

        // 收集表單資料的輔助函數
        function collectFormData() {
            // 注意: CKEditor 需要先 updateElement
            if (typeof CKEDITOR !== 'undefined') {
                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
            }

            // 如果有 tinymce
            if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
            }

            return $('#form1').serializeArray();
        }

        function saveDraft(silent) {
            silent = silent || false; // 預設為非靜默模式

            const formData = collectFormData();
            const formDataString = JSON.stringify(formData);

            // 【防呆機制】檢查資料是否與上次儲存的相同
            if (lastSavedData === formDataString) {
                if (SHOW_CONSOLE_LOG) console.log('⚠️ 表單內容未變更，跳過草稿儲存');
                return;
            }

            // 額外參數
            const urlParams = window.location.search;

            // 只在非靜默模式顯示 loading
            if (!silent) {
                Swal.fire({
                    title: '正在儲存草稿...',
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }

            if (SHOW_CONSOLE_LOG) {
                console.log('=== 準備儲存草稿 ===');
                console.log('Module:', '<?= $module ?>');
                console.log('Record ID:', '<?= $d_id ?>');
                console.log('Target Table:', '<?= $tableName ?>');
                console.log('Form Data:', formData);
                console.log('URL Params:', urlParams);
                console.log('上次儲存的資料:', lastSavedData ? '有' : '無');
            }

            $.ajax({
                url: 'ajax_save_draft.php',
                type: 'POST',
                data: {
                    module: '<?= $module ?>',
                    record_id: '<?= $d_id ?>',
                    target_table: '<?= $tableName ?>',
                    form_data: formDataString,
                    url_params: urlParams
                },
                dataType: 'json',
                success: function (res) {
                    if (SHOW_CONSOLE_LOG) {
                        console.log('=== AJAX 回應 ===');
                        console.log('完整回應:', res);
                        console.log('Success:', res.success);
                        console.log('Message:', res.message);
                    }

                    if (res.success) {
                        // 【關鍵】儲存成功後，記錄當前表單資料
                        lastSavedData = formDataString;
                        hasDraftInDatabase = true;

                        if (!silent) {
                            // 手動儲存時顯示完整提示
                            Swal.fire({
                                icon: 'success',
                                title: '草稿已儲存',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            // 自動儲存時只顯示小提示
                            if (SHOW_CONSOLE_LOG) console.log('✅ 自動暫存成功: ' + res.message);
                            // 可選：在頁面右上角顯示小提示
                            showAutoSaveNotice('已自動暫存 ' + res.message);
                        }
                    } else {
                        if (SHOW_CONSOLE_LOG) console.error('❌ 儲存失敗:', res.message);
                        if (!silent) {
                            Swal.fire('儲存失敗', res.message, 'error');
                        }
                    }
                },
                error: function (xhr, status, error) {
                    if (SHOW_CONSOLE_LOG) {
                        console.error('=== AJAX 錯誤 ===');
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('Response Text:', xhr.responseText);
                        console.error('完整 XHR:', xhr);
                    }

                    if (!silent) {
                        Swal.fire('發生錯誤', '無法連接到伺服器: ' + error, 'error');
                    }
                }
            });
        }

        // 顯示自動儲存通知 (輕量級提示)
        function showAutoSaveNotice(message) {
            // 移除舊的通知
            $('.auto-save-notice').remove();

            // 建立新通知
            const $notice = $('<div class="auto-save-notice" style="position: fixed; top: 80px; right: 20px; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; z-index: 9999; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"><i class="fas fa-check-circle me-2"></i>' + message + '</div>');

            $('body').append($notice);

            // 2 秒後淡出並移除
            setTimeout(function () {
                $notice.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 2000);
        }

        function checkDraft() {
            $.ajax({
                url: 'ajax_load_draft.php',
                type: 'POST',
                data: {
                    module: '<?= $module ?>',
                    record_id: '<?= $d_id ?>'
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.has_draft) {
                        const draftTime = res.draft.updated_at;

                        // 在頁面標題加入草稿提示
                        addDraftBadgeToTitle(draftTime);

                        Swal.fire({
                            title: '發現未儲存的草稿',
                            text: `時間：${draftTime}。是否要還原草稿內容？`,
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: '是，還原草稿',
                            cancelButtonText: '否，忽略並刪除草稿',
                            confirmButtonColor: '#ffc107',
                            cancelButtonColor: '#6c757d'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // 還原草稿
                                restoreDraft(res.draft.draft_data);
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                // 使用者選擇忽略,刪除草稿
                                deleteDraft();
                                removeDraftBadge();
                            }
                        });
                    }
                }
            });
        }

        // 在頁面標題加入草稿提示
        function addDraftBadgeToTitle(draftTime) {
            const $pageHeader = $('.page-header h2');
            if ($pageHeader.length > 0 && !$pageHeader.find('.draft-badge').length) {
                const badge = `<span class="draft-badge badge bg-warning text-dark ms-2" style="font-size: 0.75em; vertical-align: middle;">
            <i class="fas fa-file-alt me-1"></i>有未儲存的草稿 (${draftTime})
        </span>`;
                $pageHeader.append(badge);
            }
        }

        // 移除草稿提示
        function removeDraftBadge() {
            $('.draft-badge').fadeOut(300, function () {
                $(this).remove();
            });
        }

        function restoreDraft(jsonStr) {
            try {
                // 因為存進去時有 stringify，拿出來的 draft_data 是一個 JSON string
                // 但有時可能是 escaped string，嘗試 parse
                let data = (typeof jsonStr === 'string') ? JSON.parse(jsonStr) : jsonStr;

                // 此時 data 應該是 [{name: "xxx", value: "yyy"}, ...] 的 array (serializeArray 格式)
                if (typeof data === 'string') {
                    // 雙重編碼保護
                    data = JSON.parse(data);
                }

                if (Array.isArray(data)) {
                    data.forEach(field => {
                        const name = field.name;
                        const value = field.value;
                        const $el = $(`[name="${name}"]`);

                        if ($el.length > 0) {
                            if ($el.is(':radio')) {
                                $el.filter(`[value="${value}"]`).prop('checked', true);
                            } else if ($el.is(':checkbox')) {
                                // checkbox 比較麻煩，serializeArray 對多選 checkbox 會有多個同名項目
                                // 這裡簡單處理單一 value match
                                $el.filter(`[value="${value}"]`).prop('checked', true);
                            } else {
                                $el.val(value);
                            }

                            // Trigger change event if needed (e.g. for select2)
                            $el.trigger('change');
                        }

                        // CKEditor 復原
                        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances[name]) {
                            CKEDITOR.instances[name].setData(value);
                        }
                    });

                    // 【關鍵】還原草稿後，記錄當前表單資料為已儲存狀態
                    lastSavedData = JSON.stringify(data);
                    hasDraftInDatabase = true;

                    Swal.fire('已還原', '草稿內容已填入表單', 'success');
                }
            } catch (e) {
                console.error('Draft Parse Error:', e);
                Swal.fire('還原失敗', '草稿資料格式有誤', 'error');
            }
        }

        // 刪除草稿
        function deleteDraft() {
            $.ajax({
                url: 'ajax_delete_draft.php',
                type: 'POST',
                data: {
                    module: '<?= $module ?>',
                    record_id: '<?= $d_id ?>'
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        // 【關鍵】刪除草稿後，清空記錄
                        lastSavedData = null;
                        hasDraftInDatabase = false;

                        if (SHOW_CONSOLE_LOG) console.log('✅ 草稿已刪除');
                    } else {
                        if (SHOW_CONSOLE_LOG) console.error('❌ 刪除草稿失敗:', res.message);
                    }
                },
                error: function (xhr) {
                    if (SHOW_CONSOLE_LOG) console.error('刪除草稿時發生錯誤:', xhr.responseText);
                }
            });
        }
    </script>
<?php endif; ?>

<script>
    $(document).ready(function () {
        function formatIcon(state) {
            if (!state.id) {
                return state.text;
            }

            var iconClass = $(state.element).data('icon');

            if (iconClass) {
                var $state = $(
                    '<span><i class="' + iconClass + ' me-2"></i>' + state.text + '</span>'
                );
                return $state;
            }

            return state.text;
        }

        $('.select2-icon-render').select2({
            minimumResultsForSearch: -1,
            templateResult: formatIcon,
            templateSelection: formatIcon,
            theme: 'bootstrap'
        });
    });
</script>

<script>
$(document).ready(function() {
    const $form = $('#form1');
    let forceSubmit = false;

    // [核心] 實作 script.php 所需的預檢 hook
    window.cmsBeforeSubmit = function(continueSubmit, needConfirm) {
        if (forceSubmit) return true;

        const $checkFields = $form.find('[data-check-duplicate="1"]');
        if ($checkFields.length === 0) return true;

        // 整理表單資料 (含陣列欄位)
        const formData = {};
        const serialized = $form.serializeArray();
        $.each(serialized, function() {
            if (this.name.indexOf('[]') !== -1) {
                const cleanName = this.name;
                if (!formData[cleanName]) formData[cleanName] = [];
                formData[cleanName].push(this.value);
            } else {
                formData[this.name] = this.value;
            }
        });

        const checks = [];
        $checkFields.each(function() {
            const $field = $(this);
            checks.push({
                field: $field.attr('name'),
                label: $field.closest('.form-group').find('label').text().replace('*', '').trim(),
                value: $field.val()
            });
        });

        if (checks.length > 0) {
            performDuplicateChecks(checks, formData, continueSubmit, needConfirm);
            return false;
        }
        
        return true;
    };

    // 監聽表單變更，一旦變更就重設強制送出狀態
    $form.on('input change', 'input, select, textarea', function() {
        forceSubmit = false;
        $('#force_submit').val('0');
    });

    function performDuplicateChecks(checks, formData, continueSubmit, needConfirm) {
        console.log('開始檢查重複資料，共 ' + checks.length + ' 個欄位:', checks);

        Swal.fire({
            title: '正在檢查資料...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // 使用 Promise.all 同時檢查所有欄位
        const checkPromises = checks.map(check => {
            return $.ajax({
                url: 'ajax_check_duplicate.php',
                type: 'POST',
                data: {
                    module: '<?= $module ?>',
                    field: check.field,
                    value: check.value,
                    currentId: '<?= $d_id ?>',
                    formData: formData
                },
                dataType: 'json'
            }).then(res => {
                return {
                    field: check.field,
                    label: check.label,
                    isDuplicate: res.isDuplicate,
                    message: res.message
                };
            }).catch(error => {
                console.error('檢查欄位 ' + check.field + ' 時發生錯誤:', error);
                return {
                    field: check.field,
                    label: check.label,
                    isDuplicate: false,
                    error: true
                };
            });
        });

        Promise.all(checkPromises).then(results => {
            console.log('所有檢查結果:', results);
            Swal.close();

            // 收集所有重複的欄位
            const duplicates = results.filter(r => r.isDuplicate);
            const errors = results.filter(r => r.error);

            if (duplicates.length > 0) {
                // 組合所有重複欄位的訊息
                const duplicateLabels = duplicates.map(d => d.label).join('、');
                const messages = duplicates.map(d => d.message).join('<br>');

                console.log('發現重複欄位:', duplicateLabels);
                showDuplicateAlert(duplicateLabels, messages, continueSubmit, needConfirm);
            } else if (errors.length > 0) {
                // 有檢查失敗的欄位
                Swal.fire({
                    title: '檢查失敗',
                    text: '無法檢查重複資料，是否繼續送出？',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonText: '繼續送出',
                    cancelButtonText: '取消'
                }).then((result) => {
                    if (result.isConfirmed) {
                        forceSubmit = true;
                        continueSubmit(needConfirm);
                        setTimeout(() => { forceSubmit = false; }, 1000);
                    }
                });
            } else {
                // 無重複，繼續送出
                console.log('無重複，繼續送出');
                forceSubmit = true;
                continueSubmit(needConfirm);
                setTimeout(() => { forceSubmit = false; }, 1000);
            }
        });
    }

    function showDuplicateAlert(duplicateLabels, messages, continueSubmit, needConfirm) {
        console.log('顯示重複警告 - 欄位:', duplicateLabels);
        console.log('顯示重複警告 - 訊息:', messages);

        // 組合顯示訊息：顯示重複的欄位名稱
        const displayMessage = messages + '<br><br>' +
                              '確定要儲存嗎？';

        Swal.fire({
            title: '發現重複資料',
            html: displayMessage,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '強制送出',
            cancelButtonText: '返回修改',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            reverseButtons: true
        }).then((result) => {
            console.log('使用者選擇:', result);

            if (result.isConfirmed) {
                console.log('使用者選擇強制送出');

                if ($('#force_submit').length === 0) {
                    $form.append('<input type="hidden" name="force_submit" id="force_submit" value="1">');
                } else {
                    $('#force_submit').val('1');
                }

                forceSubmit = true;
                continueSubmit(needConfirm);
                setTimeout(() => { forceSubmit = false; }, 1000);
            } else {
                console.log('使用者選擇返回修改');
            }
        });
    }
});
</script>

<?php SwalConfirmElement::render(); ?>

<script>
// Git 自動化推送到 GitHub
function handleGitPush(element) {
    const itemId = $(element).data('id');
    let progressInterval;
    
    // 直接開始，不彈出初始確定按鈕
    showProcessing('正在啟動自動化環境...');

    // 啟動進度輪詢
    progressInterval = setInterval(() => {
        $.ajax({
            url: 'ajax_git_status.php',
            type: 'GET',
            cache: false,
            success: function(res) {
                if (res.success && res.progress) {
                    // 動態更新 SweetAlert2 的標題，並確保隱藏所有按鈕
                    Swal.update({
                        title: res.progress,
                        showConfirmButton: false,
                        showCancelButton: false
                    });
                }
            }
        });
    }, 800);

    $.ajax({
        url: 'ajax_git_automation.php',
        type: 'POST',
        data: {
            item_id: itemId
        },
        dataType: 'json',
        success: function (response) {
            clearInterval(progressInterval);
            if (response.success) {
                // 成功後才顯示帶有「確定」按鈕的提示
                showSuccess('Git 佈署成功！', response.message, () => {
                    $('#gitPushBtn').prop('disabled', true).html('<i class="fab fa-github"></i> 已佈署到 Git');
                    if ($('#d_data7').length) {
                        $('#d_data7').val(response.url);
                    }
                });
            } else {
                showError('Git 佈署失敗', response.message || '發生未知錯誤');
            }
        },
        error: function (xhr) {
            clearInterval(progressInterval);
            showError('請求失敗', '發生連線錯誤或後端逾時，請檢查 Git 狀態');
        }
    });
}
</script>