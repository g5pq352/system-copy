<?php
/**
 * Form Renderer Functions
 * 根據配置陣列渲染表單欄位
 */

require_once __DIR__ . '/categoryHelper.php';

/**
 * 渲染文字輸入欄位
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @return string HTML 字串
 */
function renderTextField($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $readonly = $config['readonly'] ?? false;
    $size = $config['size'] ?? 60;
    $required = $config['required'] ?? false;
    $note = $config['note'] ?? '';
    $requiredMark = $required ? '<span class="required">*</span>' : '';
    $requiredAttr = $required ? 'required' : '';
    $checkDuplicate = !empty($config['checkDuplicate']) ? 'data-check-duplicate="1"' : '';
    $attr = $config['attr'] ?? '';

    $html = "<div class='form-group row align-items-center pb-3'>";
    $html .= "<label class=\"col-lg-5 col-xl-2 control-label text-lg-end mb-0\">{$label} {$requiredMark}</label>";
    $html .= "<div class=\"col-lg-7 col-xl-7\">";
    $html .= "<input name=\"{$field}\" type=\"text\" class=\"form-control form-control-modern\" id=\"{$field}\" value=\"{$value}\" size=\"{$size}\" {$readonly} {$requiredAttr} {$checkDuplicate} {$attr} />";
    if ($note) {
        $html .= "<label class=\"error mt-2\">{$note}</label>";
    }
    $html .= "</div>";
    $html .= "</div>";

    return $html;
}

/**
 * 渲染密碼欄位
 * (已修正：原本使用 tr/td 結構，現改為與其他欄位一致的 div/bootstrap 結構)
 * @param array $config 欄位配置
 * @param string $value 欄位值（密碼欄位永遠不顯示值）
 * @return string HTML 字串
 */
function renderPasswordField($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $size = $config['size'] ?? 60;
    $required = $config['required'] ?? false;
    $note = $config['note'] ?? '';
    $requiredMark = $required ? '<span class="required">*</span>' : '';
    $attr = $config['attr'] ?? '';
    
    // 密碼欄位永遠不顯示現有值
    $html = "<div class='form-group row align-items-center pb-3'>";
    $html .= "<label class=\"col-lg-5 col-xl-2 control-label text-lg-end mb-0\">{$label}{$requiredMark}</label>";
    $html .= "<div class=\"col-lg-7 col-xl-7\">";
    $html .= "<input name=\"{$field}\" type=\"password\" class=\"form-control form-control-modern\" id=\"{$field}\" value=\"\" size=\"{$size}\" placeholder=\"留空表示不修改\" {$attr} />";
    if ($note) {
        $html .= "<label class=\"error mt-2\">{$note}</label>";
    }
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * 渲染文字區域欄位
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @return string HTML 字串
 */
function renderTextarea($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $readonly = $config['readonly'] ?? false;
    $rows = $config['rows'] ?? 6;
    $cols = $config['cols'] ?? 80;
    $required = $config['required'] ?? false;
    $note = $config['note'] ?? '';
    $requiredMark = $required ? '<span class="required">*</span>' : '';
    $requiredAttr = $required ? 'required' : '';

    $html = "<div class='form-group row pb-3'>";
    $html .= "<label class=\"col-lg-5 col-xl-2 control-label text-lg-end pt-2 mt-1 mb-0\">{$label} {$requiredMark}</label>";
    $html .= "<div class=\"col-lg-7 col-xl-7\">";
    $html .= "<textarea name=\"{$field}\" cols=\"{$cols}\" rows=\"{$rows}\" class=\"form-control form-control-modern\" id=\"{$field}\" {$readonly} {$requiredAttr}>{$value}</textarea>";
    if ($note) {
        $html .= "<label class=\"error mt-2\">{$note}</label>";
    }
    $html .= "</div>";
    $html .= "</div>";

    return $html;
}

/**
 * 渲染編輯器欄位 (TinyMCE)
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @return string HTML 字串
 */
function renderEditor($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $readonly = $config['readonly'] ?? false;
    $rows = $config['rows'] ?? 6;
    $cols = $config['cols'] ?? 80;
    $hasGallery = $config['hasGallery'] ?? false;
    $note = $config['note'] ?? '';
    $useTiny = $config['useTiny'] ?? false;  // 是否啟用 TinyMCE
    
    // 如果啟用 TinyMCE，添加 'tiny' class
    $tinyClass = $useTiny ? ' tiny' : '';
    
    $html = "<div class='form-group row pb-3'>";
    $html .= "<label class=\"col-lg-5 col-xl-2 control-label text-lg-end pt-2 mt-1 mb-0\">{$label}</label>";
    $html .= "<div class=\"col-lg-7 col-xl-7\">";
    
    if ($hasGallery) {
        $html .= "<div class=\"gallery-opener btn btn-default mb-3\" data-target=\"{$field}\">打開圖片庫</div><br />";
    }
    
    $html .= "<textarea name=\"{$field}\" cols=\"{$cols}\" rows=\"{$rows}\" class=\"form-control form-control-modern{$tinyClass}\" id=\"{$field}\" {$readonly}>{$value}</textarea>";
    if ($note) {
        $html .= "<label class=\"error mt-2\">{$note}</label>";
    }
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * 渲染單一或多選下拉
 */
function renderSelect($config, $value = '')
{
    // 如果啟用了連動功能，則導向連動渲染函數
    if ($config['linked'] ?? false) {
        return renderLinkedSelect($config, $value);
    }

    $field = $config['field'];
    $label = $config['label'];
    $disabled = $config['disabled'] ?? false;
    $required = $config['required'] ?? false;
    $requiredMark = $required ? '<span class="required">*</span>' : '';
    $requiredAttr = $required ? 'required' : '';
    $multiple = $config['multiple'] ?? false;
    $note = $config['note'] ?? '';

    // 處理值：優先序 1.帶入的 $value, 2.配置中的 default, 3.網址參數
    if ($value === '' || $value === null) {
        if (isset($config['default'])) {
            $value = $config['default'];
        } elseif (isset($_GET[$field])) {
            $value = $_GET[$field];
        }
    }

    // 確保多選時 value 為陣列
    $selectedValues = is_array($value) ? $value : (strpos((string)$value, ',') !== false ? explode(',', (string)$value) : [$value]);

    $html = "<div class='form-group row align-items-center pb-3'>";
        $html .= "<label class=\"col-lg-5 col-xl-2 control-label text-lg-end mb-0\">{$label} {$requiredMark}</label>";
        $html .= "<div class=\"col-lg-7 col-xl-7\">";

            if (isset($config['category'])) {
                $useChosen = $config['useChosen'] ?? false;
                $imageConfig = $config['imageConfig'] ?? null;
                $html .= renderCategorySelect($config['category'], $field, $value, [
                    'useChosen' => $useChosen,
                    'multiple' => $multiple,
                    'imageConfig' => $imageConfig,
                    'includeRoot' => $config['includeRoot'] ?? ($field === 'parent_id' || $field === 'menu_parent_id'),
                    'required' => $required,
                    'canCreate' => $config['canCreate'] ?? false,
                    'showPlaceholder' => $config['showPlaceholder'] ?? false
                ]);
            }
            elseif (isset($config['options'])) {
                $class = 'form-control form-control-md';
                if (isset($config['class'])) {
                    $class .= ' ' . $config['class'] . '';
                }
                if ($config['useChosen'] ?? false) {
                    $class .= ' chosen-select';
                }

                $multipleAttr = $multiple ? 'multiple' : '';
                $nameAttr = $multiple ? "{$field}[]" : $field;

                $html .= "<select name=\"{$nameAttr}\" id=\"{$field}\" class=\"{$class}\" {$requiredAttr} {$multipleAttr}>";
                if (!$multiple && ($config['showPlaceholder'] ?? false)) {
                    $html .= "<option value=\"\">-- 請選擇 --</option>";
                }

                foreach ($config['options'] as $option) {
                    $isSelected = in_array((string)$option['value'], array_map('strval', $selectedValues)) ? 'selected' : '';
                    $iconClass = $option['icon'] ?? $option['value'];
                    $html .= "<option value=\"{$option['value']}\" data-icon=\"{$iconClass}\" {$isSelected}>{$option['label']}</option>";
                }
                $html .= "</select>";
            }
        if ($note) {
            $html .= "<label class=\"error mt-2\">{$note}</label>";
        }
        $html .= "</div>";
    $html .= "</div>";

    return $html;
}

/**
 * 渲染 Checkbox 群組 (多選)
 */
function renderCheckboxGroup($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $required = $config['required'] ?? false;
    $requiredMark = $required ? '<span class="required">*</span>' : '';
    $note = $config['note'] ?? '';
    $options = $config['options'] ?? [];

    $selectedValues = is_array($value) ? $value : (strpos((string)$value, ',') !== false ? explode(',', (string)$value) : [$value]);
    $selectedValues = array_map('strval', $selectedValues);

    $html = "<div class='form-group row align-items-center pb-3'>";
    $html .= "  <label class='col-lg-5 col-xl-2 control-label text-lg-end mb-0'>{$label} {$requiredMark}</label>";
    $html .= "  <div class='col-lg-7 col-xl-7'>";
    $html .= "    <div class='row'>";
    
    foreach ($options as $opt) {
        $isChecked = in_array((string)$opt['value'], $selectedValues) ? 'checked' : '';
        $html .= "      <div class='col-sm-4 mb-2'>";
        $html .= "        <div class='checkbox-custom checkbox-default'>";
        $html .= "          <input type='checkbox' name='{$field}[]' id='{$field}_{$opt['value']}' value='{$opt['value']}' {$isChecked}>";
        $html .= "          <label for='{$field}_{$opt['value']}'>{$opt['label']}</label>";
        $html .= "        </div>";
        $html .= "      </div>";
    }
    
    $html .= "    </div>";
    if ($note) {
        $html .= "    <label class='error mt-2'>{$note}</label>";
    }
    $html .= "  </div>";
    $html .= "</div>";

    return $html;
}

/**
 * 渲染連動下拉選單 (Hierarchical Linked Select)
 * @param array $config 欄位配置
 * @param string $value 欄位值 (最後一層的 ID)
 * @return string HTML 字串
 */
function renderLinkedSelect($config, $value = '')
{
    $field = $config['field']; // 可能是字串或陣列
    $label = $config['label'];
    $categoryName = $config['category'] ?? '';
    $required = $config['required'] ?? false;
    $requiredMark = $required ? '<span class="required">*</span>' : '';
    $note = $config['note'] ?? '';

    if (empty($categoryName)) return "Missing category for linked select";

    // 處理值與路徑
    $path = [];
    $isMultiColumn = is_array($field);
    $mainFieldName = $isMultiColumn ? $field[0] : $field; // 用第一個欄位當作 wrapper 的識別

    if ($isMultiColumn) {
        // 多欄位模式：$value 預期也是陣列，我們直接過濾掉空值來當成 path
        $path = is_array($value) ? array_filter($value) : [];
        $finalValue = !empty($path) ? end($path) : '';
    } else {
        // 單欄位模式：$value 是單一 ID，需要回推路徑
        $path = getCategoryPath($categoryName, $value);
        $finalValue = $value;
    }

    if (empty($path)) {
        $path = [0]; // 預設只顯示第一層 (root)
    }

    $html = "<!-- DEBUG LinkedSelect: field=" . (is_array($field) ? json_encode($field) : $field) . ", category={$categoryName}, value=" . (is_array($value) ? json_encode($value) : $value) . ", path=" . json_encode($path) . " -->";
    $html .= "<div class='form-group row align-items-center pb-3'>";
    $html .= "  <label class=\"col-lg-5 col-xl-2 control-label text-lg-end mb-0\">{$label} {$requiredMark}</label>";
    $html .= "  <div class=\"col-lg-7 col-xl-7\">";
    $html .= "    <div class=\"linked-select-wrapper\" data-field=\"{$mainFieldName}\" data-category=\"{$categoryName}\" data-required=\"".($required ? '1' : '0')."\" data-multi=\"" . ($isMultiColumn ? '1' : '0') . "\">";
    
    // 1. 如果是單欄位模式，渲染一個隱藏欄位儲存最終值
    if (!$isMultiColumn) {
        $html .= "      <input type=\"hidden\" name=\"{$field}\" id=\"{$field}\" value=\"{$finalValue}\" ".($required ? 'required' : '').">";
    }
    
    // 渲染路徑中的每一層
    $currentParentId = 0;
    $pathArray = array_values($path); // 確保索引連貫
    foreach ($pathArray as $index => $selectedId) {
        $options = getSubCategoryOptions($categoryName, $currentParentId);
        if (empty($options) && $index > 0) break; // 沒有子層了

        // 決定 select 的 name：如果是多欄位模式，直接使用對應欄位名；否則不設 name
        $selectName = ($isMultiColumn && isset($field[$index])) ? "name=\"{$field[$index]}\"" : "";
        $selectId = ($isMultiColumn && isset($field[$index])) ? "id=\"{$field[$index]}\"" : "";

        $html .= "      <select {$selectName} {$selectId} class=\"form-control form-control-md mb-2 linked-select-level\" data-level=\"{$index}\">";

        foreach ($options as $opt) {
            $isSel = ($opt['id'] == $selectedId) ? 'selected' : '';
            $html .= "        <option value=\"{$opt['id']}\" {$isSel}>{$opt['name']}</option>";
        }
        $html .= "      </select>";
        
        $currentParentId = $selectedId;
    }

    // 檢查最後選中的 ID 是否還有下一層，如果有且還沒顯示，則顯示下一層
    if ($currentParentId > 0) {
        $nextOptions = getSubCategoryOptions($categoryName, $currentParentId);
        if (!empty($nextOptions)) {
            $nextLevel = count($pathArray);
            $selectName = ($isMultiColumn && isset($field[$nextLevel])) ? "name=\"{$field[$nextLevel]}\"" : "";
            $selectId = ($isMultiColumn && isset($field[$nextLevel])) ? "id=\"{$field[$nextLevel]}\"" : "";

            $html .= "      <select {$selectName} {$selectId} class=\"form-control form-control-md mb-2 linked-select-level\" data-level=\"{$nextLevel}\">";

            foreach ($nextOptions as $opt) {
                $html .= "        <option value=\"{$opt['id']}\">{$opt['name']}</option>";
            }
            $html .= "      </select>";
        }
    }

    if ($note) {
        $html .= "      <label class=\"error mt-2\">{$note}</label>";
    }
    $html .= "    </div>";
    $html .= "  </div>";
    $html .= "</div>";

    // 加入 JS 邏輯
    static $linkedSelectJsAdded = false;
    if (!$linkedSelectJsAdded) {
        // 多欄位模式的 PHP 變數傳遞給 JS
        $html .= "
        <script>
        $(document).on('change', '.linked-select-level', function() {
            const \$this = \$(this);
            const \$wrapper = \$this.closest('.linked-select-wrapper');
            const level = parseInt(\$this.data('level'));
            const parentId = \$this.val();
            const category = \$wrapper.data('category');
            const isMulti = \$wrapper.data('multi') == '1';
            
            // 找出所有欄位定義 (如果有設定的話)
            // 這裡我們假設 field 陣列已經透過某種方式傳給 JS，或者我們動態去抓
            // 為了簡化，如果是多欄位模式，我們讓 render 時直接把 select 的 name 寫好，
            // 並且在 change 時，移除後面層級時，也要清空後面的欄位。
            
            // 1. 移除目前等級之後的所有下拉選單
            \$wrapper.find('.linked-select-level').each(function() {
                if (parseInt(\$(this).data('level')) > level) {
                    \$(this).remove();
                }
            });

            // 2. 更新值
            if (!isMulti) {
                const \$hidden = \$wrapper.find('input[type=hidden]');
                let finalValue = '';
                \$wrapper.find('.linked-select-level').each(function() {
                    const val = \$(this).val();
                    if (val) finalValue = val;
                });
                \$hidden.val(finalValue);
            }

            // 3. 如果有選中值，嘗試讀取下一層
            if (parentId) {
                $.ajax({
                    url: 'ajax_get_sub_categories.php',
                    data: { parent_id: parentId, category: category },
                    dataType: 'json',
                    success: function(data) {
                        if (data && data.length > 0) {
                            // 如果是多欄位，我們需要知道下一層的欄位名稱
                            // 這裡透過 data-level 配合配置。
                            // 實際上，如果是動態產生，可能要由 PHP 產生的 JS 陣列提供。
                            // 但我們可以直接從 field 陣列中在 PHP 生成時注入。
                            
                            // 獲取設定中的欄位陣列 (透過屬性注入)
                            let nextSelectName = '';
                            // 這裡做一個簡單處理：如果 wrapper 有 data-field-json 屬性
                            const fieldJson = \$wrapper.data('field-json');
                            if (fieldJson && fieldJson[level + 1]) {
                                nextSelectName = ' name=\"' + fieldJson[level + 1] + '\" id=\"' + fieldJson[level + 1] + '\"';
                            }
                            
                            let nextSelect = '<select ' + nextSelectName + ' class=\"form-control form-control-md mb-2 linked-select-level\" data-level=\"' + (level + 1) + '\">';

                            data.forEach(function(item) {
                                nextSelect += '<option value=\"' + item.id + '\">' + item.name + '</option>';
                            });
                            nextSelect += '</select>';
                            \$wrapper.append(nextSelect);
                        }
                    }
                });
            }
        });
        </script>";
        $linkedSelectJsAdded = true;
    }

    // 在 wrapper 加入欄位 JSON 供 JS 使用
    if ($isMultiColumn) {
        $fieldJson = htmlspecialchars(json_encode($field), ENT_QUOTES, 'UTF-8');
        $html = str_replace('class="linked-select-wrapper"', "class=\"linked-select-wrapper\" data-field-json='{$fieldJson}'", $html);
    }

    return $html;
}

/**
 * 渲染日期欄位
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @return string HTML 字串
 */
function renderDateTimeField($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $readonly = $config['readonly'] ?? false;
    $size = $config['size'] ?? 50;
    
    if (empty($value) || $value == '0000-00-00') {
        $value = date('Y-m-d H:i:s');
    }
    
    $html = "<div class='form-group row pb-3'>";
    $html .= "<label class=\"col-lg-2 control-label text-lg-end pt-2\">{$label}</label>";
    $html .= "<div class=\"col-lg-7\">";
    $html .= "<div class=\"input-group\">";
    $html .= "<span class=\"input-group-text\">";
    $html .= "<i class=\"fas fa-calendar-alt\"></i>";
    $html .= "</span>";
    $html .= "<input name=\"{$field}\" type=\"text\" class=\"form-control\" id=\"{$field}\" value=\"{$value}\" size=\"{$size}\" {$readonly} />";
    $html .= "</div>";
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * 渲染日期欄位
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @return string HTML 字串
 */
function renderDateField($config, $value = '')
{
    $field = $config['field'];
    $label = $config['label'];
    $readonly = $config['readonly'] ?? false;
    $size = $config['size'] ?? 50;
    
    if (empty($value) || $value == '0000-00-00') {
        $value = date('Y-m-d');
    }
    
    $html = "<div class='form-group row pb-3'>";
    $html .= "<label class=\"col-lg-2 control-label text-lg-end pt-2\">{$label}</label>";
    $html .= "<div class=\"col-lg-7\">";
    $html .= "<div class=\"input-group\">";
    $html .= "<span class=\"input-group-text\">";
    $html .= "<i class=\"fas fa-calendar-alt\"></i>";
    $html .= "</span>";
    $html .= "<input name=\"{$field}\" type=\"text\" data-plugin-datepicker data-plugin-options='{\"format\": \"yyyy-mm-dd\"}' class=\"form-control\" id=\"{$field}\" value=\"{$value}\" size=\"{$size}\" {$readonly} />";
    $html .= "</div>";
    $html .= "</div>";
    $html .= "</div>";
    
    return $html;
}

/**
 * 渲染最後更新時間欄位
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @param array $existingData 目前整筆資料
 * @return string HTML 字串
 */
function renderUpdateTimeField($config, $value = '', $existingData = [])
{
    // 如果是「新增模式」(existingData 為空或是僅包含預設圖片陣列)，則隱藏此欄位
    $isAddMode = empty($existingData) || (count($existingData) === 1 && isset($existingData['images']));
    
    // 如果網址上找不到常見的 ID 參數，也可以輔助判斷
    if ($isAddMode && !isset($_GET['d_id']) && !isset($_GET['id']) && !isset($_GET['m_id'])) {
        return "";
    }

    $field = $config['field'];
    $label = $config['label'];
    $note = $config['note'] ?? '';
    $readonly = $config['readonly'] ?? false;
    $size = $config['size'] ?? 50;

    // 總是抓取當前的時間，而不是資料庫中的值
    $value = date('Y-m-d H:i:s');

    $html = "<div class='form-group row pb-3'>";
    $html .= "<label class=\"col-lg-5 col-xl-2 control-label text-lg-end pt-2\">{$label}</label>";
    $html .= "<div class=\"col-lg-7 col-xl-7\">";
    $html .= "<div class=\"input-group\">";
    $html .= "<span class=\"input-group-text\">";
    $html .= "<i class=\"fas fa-calendar-alt\"></i>";
    $html .= "</span>";
    $html .= "<input name=\"{$field}\" type=\"text\" class=\"form-control\" id=\"{$field}\" value=\"{$value}\" size=\"{$size}\" {$readonly} />";
    $html .= "</div>";
    if ($note) {
        $html .= "<label class=\"error mt-2\">{$note}</label>";
    }
    $html .= "</div>";
    $html .= "</div>";

    return $html;
}

/**
 * 渲染圖片上傳欄位 (使用 cropper 系統)
 * (已修正：修正了標籤閉合問題，並將內部的 table 結構轉為 div 結構)
 * @param array $config 欄位配置
 * @param array $existingImages 該文章的所有圖片
 * @return string HTML 字串
 */
function renderImageUpload($config, $existingImages = [])
{
    $field = $config['field'];
    $label = $config['label'];
    $note = $config['note'] ?? '';
    $targetFileType = $config['fileType'] ?? 'image';
    
    // 過濾出屬於此欄位的圖片
    $myImages = [];
    if (!empty($existingImages)) {
        foreach ($existingImages as $img) {
            // 如果有指定 file_type，則過濾
            if (isset($img['file_type']) && $img['file_type'] == $targetFileType) {
                $myImages[] = $img;
            }
        }
    }
    
    $hasImages = !empty($myImages);
    $tbodyId = "upload_area_" . $field;
    $allowMultiple = $config['multiple'] ?? false;
    $useDropzone = $config['dropzone'] ?? false;
    $isScenario3 = ($allowMultiple && $useDropzone);
    $d_id = $_GET['d_id'] ?? 0;
    $isEditMode = ($d_id > 0);
    $html = '';

    if($hasImages){
        $label = str_replace('上傳', '目前', $label);
    }

    // --- 1. 目前圖片 / 整合圖片列 ---
    // 顯示上傳區塊
    $html .= "<div class='form-group row pb-3'>";
    $html .= "<label class=\"col-lg-2 control-label text-lg-end pt-2\">{$label}</label>";
    
    // 中間欄位：圖片拖曳區 (col-lg-6)
    $html .= "<div class=\"col-lg-7\">";

    // 將 id=draggable 的 div 作為容器
    $maxSize = $config['maxSize'] ?? $config['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 2);
    $html .= "<div class=\"draggable_image\" id='draggable_{$field}' data-config=\"{$targetFileType}\" data-prefix=\"{$field}\" data-multiple=\"" . ($allowMultiple ? '1' : '0') . "\" data-max-size=\"{$maxSize}\">";

    if ($hasImages) {
        $imageIndex = 0;
        foreach ($myImages as $img) {
            $imgId = $img['file_id'];
            // 修正：使用 div class='image-manage-item' 替代 tr/td
            $html .= "<div class='image-manage-item' id='img_item_{$imgId}' data-id='{$imgId}'>";
            $html .= "  <div style='display: flex; align-items: flex-start; margin-bottom: 10px; position:relative;'>";
            // 拖曳把手
            if ($targetFileType == 'image' || $allowMultiple) {
                $html .= "<div class='drag-handle' style='margin-right:10px; cursor:move; color:#ccc;' title='排序'><i class='fas fa-grip-vertical'></i></div>";
            }
            $html .= "    <div style='width:100px; height:100px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden;'>";
            $html .= "      <a href='../{$img['file_link1']}' class='fancyboxImg' rel='group_{$field}' title='{$img['file_title']}'>";
            $html .= "        <img src='../{$img['file_link2']}' id='croppedImagePreviewex_{$imgId}' style='width:100%; height:100%; object-fit: cover; cursor: pointer;'>";
            $html .= "      </a>";
            $html .= "    </div>";
            $html .= "    <div>";
            $html .= "      <div style='display: flex; flex-direction: column; gap: 5px;'>";
            $html .= "        <input type='file' id='{$field}_ex_{$imgId}' name='{$field}_update[{$imgId}]' class='hidden-file-input' style='display:none;' accept='image/*'>";
            $html .= "        <div style='display: flex; align-items: center; gap: 8px;'>";
            $html .= "          <button type='button' class='trigger-crop-btn btn btn-default' data-target='{$field}_ex_{$imgId}'>選擇檔案</button>";
            // 垃圾桶
            if (($targetFileType == 'image' || $allowMultiple) && $imageIndex > 0) {
                $html .= "<a href='javascript:void(0)' onclick='deleteImageItem({$imgId})' style='color:#666; font-size:16px;' title='刪除圖片'><i class='fas fa-trash-alt'></i></a>";
            }
            $html .= "        </div>";
            $html .= "        <a href='javascript:void(0)' onclick='window.uploaders[\"ex_{$imgId}\"].reset()' id='remove_btn_ex_{$imgId}' style='color:red; text-decoration:none; font-size:14px; margin-top:5px; display:inline-block;'><i class='fas fa-times-circle'></i> 移除</a>";
            $html .= "      </div>";
            $imageIndex++;
            $html .= "      <div style='margin-top: 5px;'>";
            $html .= "        <p id='fileNameDisplayex_{$imgId}' class='file-name-display' style='display:none; font-size:0.9rem; color:#555;margin: 0;'>未選擇</p>";
            $html .= "        <p id='uploadStatusex_{$imgId}' class='status-msg' style='font-size:0.9rem; color:blue; margin:5px 0 0 0;'></p>";
            $html .= "        <input type='hidden' id='imageUrlex_{$imgId}' class='url-input'>";
            $html .= "      </div>";
            $html .= "    </div>";
            $html .= "  </div>";
            $html .= "  <div style='margin-top:5px; display: flex; align-items: center;'>";
            $html .= "    <span class='table_data' style='flex-shrink:0;'>圖片說明：</span>";
            $html .= "    <input type='text' id='title_ex_{$imgId}' name='update_file_title[{$imgId}]' value='{$img['file_title']}' class='table_data' style='width: 300px; padding: 4px; border: 1px solid #ccc;'>";
            $html .= "  </div>";

            $html .= "<script>
                $(document).ready(function() {
                    const cfg = (window.GLOBAL_IMG_CONFIG && GLOBAL_IMG_CONFIG['{$targetFileType}']) || {IW:800, IH:600};
                    const maxSize = " . ($config['maxSize'] ?? $config['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 2)) . ";
                    window.uploaders['ex_{$imgId}'] = new ImageUploader(
                        'ex_{$imgId}', cfg.IW, cfg.IH, null, cfg.OW || cfg.IW, cfg.OH || cfg.IH, '{$field}', maxSize
                    );
                });
            </script>";
            
            // 修正：這裡是一個 item 的結尾
            $html .= "</div>";
        }
    }
    // Draggable container 結束
    $html .= "</div>";

    // 新增按鈕
    // 當 multiple=true 且 dropzone=true 時，編輯模式下不顯示新增按鈕（因為有 dropzone）
    // if ($allowMultiple && !($useDropzone && $isEditMode)) {
    if ($allowMultiple) {
        $html .= "<div style='margin-top:20px;'>";
        $html .= "  <a href=\"javascript:void(0)\" onclick=\"addDynamicField('draggable_{$field}', '{$field}', '{$targetFileType}')\" class='table_data' style='text-decoration:none;'>";
        $html .= "    <img src=\"image/add.png\" width=\"16\" height=\"16\" border=\"0\" style='vertical-align:middle;'> 新增圖片";
        $html .= "  </a>";
        $html .= "</div>";
    }

    // --- ⭐ 自動生成提示文字 (建議尺寸 + 大小限制) ⭐ ---
    $effMaxSize = $config['maxSize'] ?? $config['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 2);
    $sizeLimitNote = "(大小限制 {$effMaxSize}MB)";

    $dimensionNote = "";
    if (isset($config['size']) && is_array($config['size'])) {
        foreach ($config['size'] as $s) {
            if (is_array($s) && isset($s['w']) && isset($s['h'])) {
                if($s['w'] !== 0 && $s['h'] !== 0){
                    $dimensionNote = "* 建議尺寸：{$s['w']}x{$s['h']}px";
                }
                break;
            }
        }
    }

    $autoNote = trim($dimensionNote . " " . $sizeLimitNote);
    $userNote = ($note === '*' || empty($note)) ? "" : $note;
    $finalNote = trim($autoNote . " <br>" . $userNote);

    // if (($targetFileType == 'image' || $allowMultiple) && $hasImages) {
    //     $html .= "<label class=\"error mt-2\">*" . str_replace('*', '', $autoNote) . "<br>* 若要排序照片，請直接拖拉即可。</label>";
    // } elseif ($finalNote) {
    //     $html .= "<label class=\"error mt-2\">{$finalNote}</label>";
    // }
    $html .= "<label class=\"error mt-2\">{$finalNote}</label>";

    // 如果沒圖片，自動帶出一組上傳區塊
    // 在新增模式或編輯模式都需要
    if (!$hasImages) {
        $html .= "<script>
            $(document).ready(function() {
                if (typeof addDynamicField === 'function') {
                    addDynamicField('draggable_{$field}', '{$field}', '{$targetFileType}', false);
                }
            });
        </script>";
    }

    // 修正：原本這裡錯誤地使用了 </td>，現在正確關閉 col-lg-6
    $html .= "</div>"; // end col-lg-6
    $html .= "</div>"; // end form-group row
    

    // --- 2. Dropzone 上傳區塊 ---
    if ($useDropzone && $isEditMode) {
        $html .= "<div class='form-group row pb-3'>";
        $html .= "<label class='col-lg-2 control-label text-lg-end pt-2'>上傳圖片</label>";
        $html .= "<div class='col-lg-9'>";
        $maxSize = $config['maxSize'] ?? $config['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 2);
        $postMaxSize = defined('DEFAULT_POST_MAX_SIZE') ? DEFAULT_POST_MAX_SIZE : 8;
        $html .= "<div id='dropzone-{$field}' class='dropzone-modern dz-square' data-d-id='{$d_id}' data-file-type='{$targetFileType}' data-max-size='{$maxSize}'>";
        $html .= "<span class='dropzone-upload-message text-center'>";
        $html .= "<i class='bx bxs-cloud-upload'></i>";
        $html .= "<b class='text-color-primary'>Drag/Upload</b> your image here.";
        $html .= "</span>";
        $html .= "</div>";
        $html .= "<div class=\"col-lg-7 mt-2\">";
        $html .= "<label class=\"error mb-0\">* 每次上傳之檔案大小總計請勿超過{$postMaxSize}MB。</label>";
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</div>";
    }

    return $html;
}

/**
 * 渲染檔案上傳欄位
 * @param array $config 欄位配置
 * @param array $existingFiles 該文章的所有圖片/檔案
 * @return string HTML 字串
 */
function renderFileUpload($config, $existingFiles = [])
{
    $field = $config['field'];
    $label = $config['label'];
    $note = $config['note'] ?? '';
    $allowMultiple = $config['multiple'] ?? false;

    // 新格式：format 在外層，maxSize 在 size 內
    $acceptFormat = $config['format'] ?? '*';
    $maxSize = $config['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 10);

    $targetFileType = $config['fileType'] ?? 'file';

    // 過濾出屬於此欄位的檔案
    $myFiles = [];
    if (!empty($existingFiles)) {
        foreach ($existingFiles as $f) {
            if (isset($f['file_type']) && $f['file_type'] == $targetFileType) {
                $myFiles[] = $f;
            }
        }
    }

    $hasFiles = !empty($myFiles);
    $html = "<div class='form-group row pb-3 file-upload-wrapper' data-field='{$field}' data-accept='{$acceptFormat}' data-max-size='{$maxSize}'>";
    $html .= "  <label class=\"col-lg-2 control-label text-lg-end pt-2\">{$label}</label>";
    $html .= "  <div class=\"col-lg-7\">";

    // 顯示現有檔案
    if ($hasFiles) {
        $html .= "    <div class='mb-3'>";
        foreach ($myFiles as $f) {
            $fId = $f['file_id'];
            $fName = $f['file_name'];
            $fLink = "../" . $f['file_link1'];
            $html .= "      <div class='mb-2 d-flex align-items-center' id='file_item_{$fId}'>";
            $html .= "        <a href='{$fLink}' target='_blank' class='me-3 text-decoration-none'><i class='fas fa-file-alt me-1'></i> {$fName}</a>";
            $html .= "        <input type='text' name='update_file_title[{$fId}]' value='" . htmlspecialchars($f['file_title'] ?? '') . "' class='form-control form-control-sm me-3' style='width: 200px;' placeholder='檔案說明'>";
            $html .= "        <div class='form-check'>";
            $html .= "          <input class='form-check-input' type='checkbox' name='delete_file[]' value='{$fId}' id='del_file_{$fId}'>";
            $html .= "          <label class='form-check-label text-danger cursor-pointer' for='del_file_{$fId}'>刪除</label>";
            $html .= "        </div>";
            $html .= "      </div>";
        }
        $html .= "    </div>";
    }

    // 動態上傳區域
    // - 如果沒有現有檔案：顯示一個上傳欄位
    // - 如果有現有檔案且是 multiple 模式：不顯示（透過新增按鈕來新增）
    // - 如果有現有檔案且不是 multiple 模式：不顯示
    if (!$hasFiles) {
        $html .= "    <div class='file-input-container mb-3' id='container_{$field}'>";
        $html .= "      <div class='file-input-row mb-2 d-flex align-items-center'>";
        $html .= "        <input type='file' name='{$field}[]' class='form-control me-2 file-input' accept='{$acceptFormat}'>";
        $html .= "        <input type='text' name='{$field}_title[]' class='form-control me-2' style='width: 200px;' placeholder='檔案說明'>";
        $html .= "        <button type='button' class='btn btn-danger btn-sm remove-file-row' style='display:none;'><i class='fas fa-times'></i></button>";
        $html .= "      </div>";
        $html .= "    </div>";
    } else {
        // 如果有現有檔案，建立空的容器供新增按鈕使用
        $html .= "    <div class='file-input-container' id='container_{$field}'></div>";
    }

    // 只在 multiple 模式下顯示新增按鈕
    if ($allowMultiple) {
        $html .= "    <button type='button' class='btn btn-default btn-sm mt-1 add-file-row' data-target='container_{$field}'><i class='fas fa-plus me-1'></i> 新增檔案</button><br>";
    }

    // --- ⭐ 自動生成提示文字 (支援格式 + 大小限制) ⭐ ---
    $formatNote = "";
    $sizeLimitNote = "";

    // 生成格式提示
    if ($acceptFormat && $acceptFormat !== '*') {
        $formatNote = "* 支援格式：" . str_replace('.', '', $acceptFormat);
    }

    // 生成大小限制提示
    if ($maxSize > 0) {
        $sizeLimitNote = "(大小限制 {$maxSize}MB)";
    }

    $autoNote = trim($formatNote . " " . $sizeLimitNote);
    $userNote = ($note === '*' || empty($note)) ? "" : $note;
    $finalNote = trim($autoNote . ($autoNote && $userNote ? " <br>" : "") . $userNote);

    if ($finalNote) {
        $html .= "<label class=\"error mt-2\">{$finalNote}</label>";
    }
    $html .= "  </div>";

    // 只在第一次呼叫時加入 JS (使用全域變數檢查)
    static $uploadJsAdded = false;
    if (!$uploadJsAdded) {
        $html .= "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 檔案格式驗證函數
            function validateFileFormat(fileInput, acceptFormat) {
                if (!acceptFormat || acceptFormat === '*') return true; // 不限制格式

                const files = fileInput.files;
                if (!files || files.length === 0) return true;

                // 解析允許的副檔名 (例如: '.pdf,.jpg,.png')
                const allowedExtensions = acceptFormat.split(',').map(ext => ext.trim().toLowerCase().replace('.', ''));

                for (let i = 0; i < files.length; i++) {
                    const fileName = files[i].name;
                    const fileExtension = fileName.split('.').pop().toLowerCase();

                    if (!allowedExtensions.includes(fileExtension)) {
                        Swal.fire({
                            icon: 'error',
                            title: '檔案格式不允許',
                            html: '檔案「<b>' + fileName + '</b>」格式不允許！<br>允許的格式：<b>' + acceptFormat + '</b>',
                            confirmButtonText: '確定'
                        });
                        fileInput.value = ''; // 清空選擇
                        return false;
                    }
                }
                return true;
            }

            // 檔案大小驗證函數
            function validateFileSize(fileInput, maxSizeMB) {
                if (maxSizeMB <= 0) return true; // 不限制大小

                const files = fileInput.files;
                if (!files || files.length === 0) return true;

                const maxSizeBytes = maxSizeMB * 1024 * 1024;

                for (let i = 0; i < files.length; i++) {
                    if (files[i].size > maxSizeBytes) {
                        const fileSizeMB = (files[i].size / 1024 / 1024).toFixed(2);
                        Swal.fire({
                            icon: 'error',
                            title: '檔案大小超過限制',
                            html: '檔案「<b>' + files[i].name + '</b>」大小超過限制！<br>最大允許：<b>' + maxSizeMB + 'MB</b><br>檔案大小：<b>' + fileSizeMB + 'MB</b>',
                            confirmButtonText: '確定'
                        });
                        fileInput.value = ''; // 清空選擇
                        return false;
                    }
                }
                return true;
            }

            // 監聽所有檔案輸入的變更
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('file-input')) {
                    const wrapper = e.target.closest('.file-upload-wrapper');
                    if (wrapper) {
                        const acceptFormat = wrapper.getAttribute('data-accept');
                        const maxSize = parseFloat(wrapper.getAttribute('data-max-size')) || 0;

                        // 先驗證格式,再驗證大小
                        if (!validateFileFormat(e.target, acceptFormat)) {
                            return;
                        }
                        if (!validateFileSize(e.target, maxSize)) {
                            return;
                        }
                    }
                }
            });

            // 事件委派：處理新增按鈕
            document.addEventListener('click', function(e) {
                if (e.target.closest('.add-file-row')) {
                    const btn = e.target.closest('.add-file-row');
                    const targetId = btn.getAttribute('data-target');
                    const container = document.getElementById(targetId);
                    const wrapper = btn.closest('.file-upload-wrapper');
                    const fieldName = wrapper.getAttribute('data-field');
                    const accept = wrapper.getAttribute('data-accept');

                    const newRow = document.createElement('div');
                    newRow.className = 'file-input-row mb-2 d-flex align-items-center';
                    newRow.innerHTML = `
                        <input type='file' name='\${fieldName}[]' class='form-control me-2 file-input' accept='\${accept}'>
                        <input type='text' name='\${fieldName}_title[]' class='form-control me-2' style='width: 200px;' placeholder='檔案說明'>
                        <button type='button' class='btn btn-danger btn-sm remove-file-row'><i class='fas fa-times'></i></button>
                    `;
                    container.appendChild(newRow);
                }
            });

            // 事件委派：處理移除按鈕
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-file-row')) {
                    const row = e.target.closest('.file-input-row');
                    row.remove();
                }
            });
        });
        </script>";
        $uploadJsAdded = true;
    }

    $html .= "</div>";
    
    return $html;
}

/**
 * 渲染簡單圖片上傳欄位（不需裁切功能）
 * @param array $config 欄位配置
 * @param array $existingFiles 該文章的所有圖片
 * @return string HTML 字串
 */
function renderSimpleImageUpload($config, $existingFiles = [])
{
    $field = $config['field'];
    $label = $config['label'];
    $note = $config['note'] ?? '';
    $targetFileType = $config['fileType'] ?? 'simple_image';
    $allowMultiple = $config['multiple'] ?? false;

    // 新格式：format 在外層，maxSize 在 size 內
    $acceptFormat = $config['format'] ?? 'image/*';
    $maxSize = $config['size']['maxSize'] ?? (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 5);

    // 過濾出屬於此欄位的圖片
    $myFiles = [];
    if (!empty($existingFiles)) {
        foreach ($existingFiles as $f) {
            if (isset($f['file_type']) && $f['file_type'] == $targetFileType) {
                $myFiles[] = $f;
            }
        }
    }

    $hasFiles = !empty($myFiles);
    $html = "<div class='form-group row pb-3 simple-image-upload-wrapper' data-field='{$field}' data-accept='{$acceptFormat}' data-max-size='{$maxSize}'>";
    $html .= "  <label class=\"col-lg-2 control-label text-lg-end pt-2\">{$label}</label>";
    $html .= "  <div class=\"col-lg-7\">";

    // 顯示現有圖片 - 使用可拖曳容器
    if ($hasFiles) {
        $html .= "    <div class='mb-3 simple-draggable-container' id='simple_draggable_{$field}'>";
        $imageIndex = 0;
        foreach ($myFiles as $f) {
            $fId = $f['file_id'];
            $fName = $f['file_name'];
            $fLink = "../" . $f['file_link1'];
            $fThumb = "../" . ($f['file_link2'] ?? $f['file_link1']);
            $html .= "      <div class='mb-3 p-3 border rounded simple-draggable-item' id='simple_img_item_{$fId}' data-id='{$fId}' style='background-color: #f9f9f9;'>";
            $html .= "        <div class='d-flex align-items-start mb-2'>";

            // 拖曳把手 - 在 multiple 模式下顯示
            if ($allowMultiple) {
                $html .= "          <div class='drag-handle me-2' style='cursor: move; color: #ccc;' title='拖曳排序'><i class='fas fa-grip-vertical'></i></div>";
            }

            $html .= "          <div style='width:120px; height:120px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden; flex-shrink: 0;'>";
            $html .= "            <a href='{$fLink}' class='fancyboxImg' rel='group_{$field}' title='" . htmlspecialchars($f['file_title'] ?? '') . "'>";
            $html .= "              <img src='{$fThumb}' style='width:100%; height:100%; object-fit: cover; cursor: pointer;'>";
            $html .= "            </a>";
            $html .= "          </div>";
            $html .= "          <div class='flex-grow-1'>";
            $html .= "            <div class='mb-2'>";
            $html .= "              <label class='form-label mb-1'>圖片說明</label>";
            $html .= "              <input type='text' name='update_file_title[{$fId}]' value='" . htmlspecialchars($f['file_title'] ?? '') . "' class='form-control form-control-sm' placeholder='請輸入圖片說明'>";
            $html .= "            </div>";

            // 刪除按鈕 - 所有圖片都顯示
            $html .= "            <div class='form-check'>";
            $html .= "              <input class='form-check-input' type='checkbox' name='delete_file[]' value='{$fId}' id='del_simple_img_{$fId}'>";
            $html .= "              <label class='form-check-label text-danger cursor-pointer' for='del_simple_img_{$fId}'>刪除此圖片</label>";
            $html .= "            </div>";

            $html .= "          </div>";
            $html .= "        </div>";
            $html .= "      </div>";
            $imageIndex++;
        }
        $html .= "    </div>";
    }

    // 動態上傳區域
    // - 如果沒有現有圖片：顯示一個上傳欄位
    // - 如果有現有圖片且是 multiple 模式：不顯示（透過新增按鈕來新增）
    // - 如果有現有圖片且不是 multiple 模式：不顯示
    if (!$hasFiles) {
        $html .= "    <div class='simple-image-input-container' id='simple_container_{$field}'>";
        $html .= "      <div class='simple-image-input-row mb-3 p-3 border rounded' style='background-color: #f9f9f9;'>";
        $html .= "        <div class='mb-2'>";
        $html .= "          <label class='form-label mb-1'>選擇圖片</label>";
        $html .= "          <input type='file' name='{$field}[]' class='form-control simple-image-input' accept='{$acceptFormat}'>";
        $html .= "        </div>";
        $html .= "        <div class='mb-2'>";
        $html .= "          <label class='form-label mb-1'>圖片說明</label>";
        $html .= "          <input type='text' name='{$field}_title[]' class='form-control' placeholder='請輸入圖片說明'>";
        $html .= "        </div>";
        $html .= "        <button type='button' class='btn btn-danger btn-sm remove-simple-image-row' style='display:none;'><i class='fas fa-times'></i> 移除</button>";
        $html .= "      </div>";
        $html .= "    </div>";
    } else {
        // 如果有現有圖片，建立空的容器供新增按鈕使用
        $html .= "    <div class='simple-image-input-container' id='simple_container_{$field}'></div>";
    }

    // 只在 multiple 模式下顯示新增按鈕
    if ($allowMultiple) {
        $html .= "    <button type='button' style='display:block;' class='btn btn-default btn-sm mt-1 add-simple-image-row' data-target='simple_container_{$field}'><i class='fas fa-plus me-1'></i> 新增圖片</button>";
    }

    // --- ⭐ 自動生成提示文字 (支援格式 + 大小限制) ⭐ ---
    $formatNote = "* 支援圖片格式：JPG, PNG, GIF, SVG, WEBP";
    $sizeLimitNote = "";

    // 生成大小限制提示
    if ($maxSize > 0) {
        $sizeLimitNote = "(大小限制 {$maxSize}MB)";
    }

    $autoNote = trim($formatNote . " " . $sizeLimitNote);
    $userNote = ($note === '*' || empty($note)) ? "" : $note;
    $finalNote = trim($autoNote . ($autoNote && $userNote ? " <br>" : "") . $userNote);

    if ($finalNote) {
        $html .= "<label class=\"error mt-2\">{$finalNote}</label>";
    }
    $html .= "  </div>";

    // 只在第一次呼叫時加入 JS (使用全域變數檢查)
    static $simpleImageUploadJsAdded = false;
    if (!$simpleImageUploadJsAdded) {
        $html .= "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化 Sortable.js 拖曳排序
            document.querySelectorAll('.simple-draggable-container').forEach(function(container) {
                if (typeof Sortable !== 'undefined') {
                    new Sortable(container, {
                        animation: 150,
                        handle: '.drag-handle',
                        ghostClass: 'sortable-ghost',
                        onEnd: function(evt) {
                            // 更新排序到後端
                            const items = container.querySelectorAll('.simple-draggable-item');
                            const ids = Array.from(items).map(item => item.getAttribute('data-id'));

                            // 使用 jQuery AJAX 發送排序更新（與現有圖片排序一致）
                            $.ajax({
                                url: 'image_sort.php',
                                type: 'POST',
                                data: { ids: ids },
                                success: function(response) {
                                    console.log('排序已更新', response);
                                },
                                error: function(xhr, status, error) {
                                    console.error('排序更新失敗', error);
                                }
                            });
                        }
                    });
                }
            });

            // 圖片格式驗證函數
            function validateImageFormat(fileInput, acceptFormat) {
                const files = fileInput.files;
                if (!files || files.length === 0) return true;

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];

                for (let i = 0; i < files.length; i++) {
                    if (!allowedTypes.includes(files[i].type)) {
                        Swal.fire({
                            icon: 'error',
                            title: '圖片格式不允許',
                            html: '檔案「<b>' + files[i].name + '</b>」格式不允許！<br>允許的格式：<b>JPG, PNG, GIF, SVG, WEBP</b>',
                            confirmButtonText: '確定'
                        });
                        fileInput.value = '';
                        return false;
                    }
                }
                return true;
            }

            // 圖片大小驗證函數
            function validateImageSize(fileInput, maxSizeMB) {
                if (maxSizeMB <= 0) return true;

                const files = fileInput.files;
                if (!files || files.length === 0) return true;

                const maxSizeBytes = maxSizeMB * 1024 * 1024;

                for (let i = 0; i < files.length; i++) {
                    if (files[i].size > maxSizeBytes) {
                        const fileSizeMB = (files[i].size / 1024 / 1024).toFixed(2);
                        Swal.fire({
                            icon: 'error',
                            title: '圖片大小超過限制',
                            html: '檔案「<b>' + files[i].name + '</b>」大小超過限制！<br>最大允許：<b>' + maxSizeMB + 'MB</b><br>檔案大小：<b>' + fileSizeMB + 'MB</b>',
                            confirmButtonText: '確定'
                        });
                        fileInput.value = '';
                        return false;
                    }
                }
                return true;
            }

            // 監聽所有圖片輸入的變更
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('simple-image-input')) {
                    const wrapper = e.target.closest('.simple-image-upload-wrapper');
                    if (wrapper) {
                        const acceptFormat = wrapper.getAttribute('data-accept');
                        const maxSize = parseFloat(wrapper.getAttribute('data-max-size')) || 0;

                        if (!validateImageFormat(e.target, acceptFormat)) {
                            return;
                        }
                        if (!validateImageSize(e.target, maxSize)) {
                            return;
                        }
                    }
                }
            });

            // 事件委派：處理新增按鈕
            document.addEventListener('click', function(e) {
                if (e.target.closest('.add-simple-image-row')) {
                    const btn = e.target.closest('.add-simple-image-row');
                    const targetId = btn.getAttribute('data-target');
                    const container = document.getElementById(targetId);
                    const wrapper = btn.closest('.simple-image-upload-wrapper');
                    const fieldName = wrapper.getAttribute('data-field');
                    const accept = wrapper.getAttribute('data-accept');

                    const newRow = document.createElement('div');
                    newRow.className = 'simple-image-input-row mb-3 p-3 border rounded';
                    newRow.style.backgroundColor = '#f9f9f9';
                    newRow.innerHTML = `
                        <div class='mb-2'>
                            <label class='form-label mb-1'>選擇圖片</label>
                            <input type='file' name='\${fieldName}[]' class='form-control simple-image-input' accept='\${accept}'>
                        </div>
                        <div class='mb-2'>
                            <label class='form-label mb-1'>圖片說明</label>
                            <input type='text' name='\${fieldName}_title[]' class='form-control' placeholder='請輸入圖片說明'>
                        </div>
                        <button type='button' class='btn btn-danger btn-sm remove-simple-image-row'><i class='fas fa-times'></i> 移除</button>
                    `;
                    container.appendChild(newRow);
                }
            });

            // 事件委派：處理移除按鈕
            document.addEventListener('click', function(e) {
                if (e.target.closest('.remove-simple-image-row')) {
                    const row = e.target.closest('.simple-image-input-row');
                    row.remove();
                }
            });
        });
        </script>";
        $simpleImageUploadJsAdded = true;
    }

    $html .= "</div>";

    return $html;
}

/**
 * 渲染動態欄位編輯器
 */
function renderDynamicFields($config, $existingData = [])
{
    $field      = $config['field'];
    $label      = $config['label'];
    $note       = $config['note'] ?? '';
    $fieldGroup = $config['fieldGroup'] ?? 'd_data1';

    /**
     * 取出該欄位的動態資料
     */
    $fieldData = [];
    if (!empty($existingData[$field]) && is_array($existingData[$field])) {
        $fieldData = $existingData[$field];
    }

    /**
     * 🔥 關鍵：
     * 轉成「有 _uid + _index 的 array list」
     */
    $normalized = [];
    $i = 0;

    foreach ($fieldData as $uid => $group) {

        // 補 uid
        if (!isset($group['_uid'])) {
            $group['_uid'] = $uid;
        }

        // 補 index（保證存在）
        if (!isset($group['_index'])) {
            $group['_index'] = $i;
        }

        $normalized[] = $group;

        $i++;
    }

    /**
     * 🔥 排序，確保畫面順序與 DB 一致
     */
    usort($normalized, function($a, $b){
        return ($a['_index'] ?? 0) <=> ($b['_index'] ?? 0);
    });

    /**
     * JSON
     */
    $configJson = json_encode([
        'field'      => $field,
        'label'      => $label,
        'note'       => $note,
        'fieldGroup' => $fieldGroup,
        'fields'     => $config['fields'] ?? [],
        'maxSize'    => $config['maxSize'] ?? null,
        'size'       => $config['size'] ?? null
    ], JSON_UNESCAPED_UNICODE);

    $dataJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);

    /**
     * HTML + init
     */
    $html = "
    <div class='form-group row pb-3'>
        <label class='col-lg-5 col-xl-2 control-label text-lg-end mb-0'>{$label}</label>
        <div class='col-lg-7 col-xl-7'>
            <div id='{$field}_container'></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        console.log('%c[DYNAMIC INIT] {$field}','color:#09f');
        console.log('config', {$configJson});
        console.log('data', {$dataJson});

        window.dynamicFieldsEditor_{$field} =
            initDynamicFields(
                '{$field}_container',
                {$configJson},
                {$dataJson}
            );
    });
    </script>";

    return $html;
}


/**
 * 渲染表單欄位（根據類型自動選擇）
 * @param array $config 欄位配置
 * @param string $value 欄位值
 * @param array $existingData 現有圖片資料陣列
 * @return string HTML 字串
 */
function renderFormField($config, $value = '', $existingData = [])
{
    $type = $config['type'];
    
    switch ($type) {
        case 'text':
            return renderTextField($config, $value);
        case 'password':
            return renderPasswordField($config, $value);
        case 'textarea':
            return renderTextarea($config, $value);
        case 'editor':
            return renderEditor($config, $value);
        case 'select':
            return renderSelect($config, $value);
        case 'checkbox':
            return renderCheckboxGroup($config, $value);
        case 'linked_select':
            return renderLinkedSelect($config, $value);
        case 'date':
            return renderDateField($config, $value);
        case 'datetime':
            return renderDateTimeField($config, $value);
        case 'updatetime':
            return renderUpdateTimeField($config, $value, $existingData);
        case 'image_upload':
            $images = [];
            if (isset($existingData['images']) && is_array($existingData['images'])) {
                $images = $existingData['images'];
            } elseif (is_array($existingData) && !empty($existingData)) {
                $firstItem = reset($existingData);
                if (is_array($firstItem) && isset($firstItem['file_id'])) {
                    $images = $existingData;
                }
            }
            return renderImageUpload($config, $images);
        case 'file_upload':
            $files = [];
            if (isset($existingData['images']) && is_array($existingData['images'])) {
                $files = $existingData['images'];
            } elseif (is_array($existingData) && !empty($existingData)) {
                $firstItem = reset($existingData);
                if (is_array($firstItem) && isset($firstItem['file_id'])) {
                    $files = $existingData;
                }
            }
            return renderFileUpload($config, $files);
        case 'image':
            $files = [];
            if (isset($existingData['images']) && is_array($existingData['images'])) {
                $files = $existingData['images'];
            } elseif (is_array($existingData) && !empty($existingData)) {
                $firstItem = reset($existingData);
                if (is_array($firstItem) && isset($firstItem['file_id'])) {
                    $files = $existingData;
                }
            }
            return renderSimpleImageUpload($config, $files);
        case 'authority_matrix':
            global $conn;
            require_once __DIR__ . '/authorityHelper.php';
            $groupId = 0;
            if (is_array($existingData)) {
                $groupId = $existingData['group_id'] ?? $existingData['id'] ?? 0;
            }
            return renderAuthorityMatrix($conn, $groupId);
        case 'dynamic_fields':
            return renderDynamicFields($config, $existingData);
        default:
            return '';
    }
}
?>