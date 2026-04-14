<?php
class DynamicFieldsHelper
{
    private $conn;
    private $tableName = 'data_dynamic_fields';

    /**
     * 本次 saveFields 流程中「候選刪除」的 file_id
     * 只記錄，不立刻刪
     */
    private $pendingDeleteFileIds = [];

    // ✅ Prepared statement cache (效能優化)
    private $stmtCache = [];

    private function stmt($sql)
    {
        if (!isset($this->stmtCache[$sql])) {
            $this->stmtCache[$sql] = $this->conn->prepare($sql);
        }
        return $this->stmtCache[$sql];
    }

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /* =========================
     * UUID
     * ========================= */
    private function makeUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /* =========================
     * 取得動態欄位（讀取專用）
     * ========================= */
    public function getFields($d_id, $fieldGroup = null)
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE df_d_id=?";
        $params = [$d_id];

        if ($fieldGroup !== null) {
            $sql .= " AND df_field_group=?";
            $params[] = $fieldGroup;
        }

        $sql .= " ORDER BY df_group_index ASC, df_sort ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        $imageFields = []; // 追蹤哪些欄位是圖片欄位，以便組成陣列

        foreach ($rows as $row) {
            $gi = (int)$row['df_group_index'];
            $uid = $row['df_group_uid'] ?: 'LEGACY-' . $gi;
            $fieldName = $row['df_field_name'];

            if (!isset($grouped[$gi])) {
                $grouped[$gi] = ['_uid' => $uid];
            }

            if (!empty($row['df_file_id'])) {
                $imageData = [
                    'file_id'   => (int)$row['df_file_id'],
                    'file_info' => $this->getFileInfo($row['df_file_id'])
                ];

                // 如果該欄位已經有值，表示是多圖模式，轉成陣列
                if (isset($grouped[$gi][$fieldName])) {
                    // 如果還不是陣列，先轉成陣列
                    if (!is_array($grouped[$gi][$fieldName]) || isset($grouped[$gi][$fieldName]['file_id'])) {
                        $grouped[$gi][$fieldName] = [$grouped[$gi][$fieldName]];
                    }
                    $grouped[$gi][$fieldName][] = $imageData;
                    $imageFields[$gi][$fieldName] = true;
                } else {
                    $grouped[$gi][$fieldName] = $imageData;
                }
            } else {
                $grouped[$gi][$fieldName] = $row['df_field_value'];
            }
        }

        return $grouped;
    }

    /* =========================
     * 儲存（diff-only + 正確刪圖）
     * ========================= */
    public function saveFields($d_id, $fieldGroup, $data, $fieldConfig = [])
    {
        if (!is_array($data)) {
            return true;
        }

        /* ---------- DB 現況 ---------- */
        $stmt = $this->stmt("
            SELECT * FROM {$this->tableName}
            WHERE df_d_id=? AND df_field_group=?
        ");
        $stmt->execute([$d_id, $fieldGroup]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dbGroups = [];      // [uid][field] => row
        $dbIndex  = [];      // [uid] => group_index
        $dbUidByIndex = [];  // [group_index] => uid

        foreach ($rows as $r) {
            $uid = (string)$r['df_group_uid'];
            $gi  = (int)$r['df_group_index'];

            $dbGroups[$uid][$r['df_field_name']] = $r;
            $dbIndex[$uid] = $gi;

            if (!isset($dbUidByIndex[$gi])) {
                $dbUidByIndex[$gi] = $uid;
            }
        }

        /* ---------- normalize UID ---------- */
        foreach ($data as $gi => &$group) {
            if (!is_array($group)) {
                $group = [];
            }
            if (empty($group['_uid'])) {
                $group['_uid'] = $dbUidByIndex[$gi] ?? $this->makeUUID();
            }
        }
        unset($group);

        /* ---------- 新 UID 清單 ---------- */
        $newUids = [];
        foreach ($data as $g) {
            if (!empty($g['_uid'])) {
                $newUids[] = (string)$g['_uid'];
            }
        }
        $newUids = array_values(array_unique($newUids));

        /* =====================================================
         * Phase 1️⃣ 刪 group，只收集 file_id（不刪圖）
         * ===================================================== */
        $uidsToDelete = array_diff(array_keys($dbGroups), $newUids);

        foreach ($uidsToDelete as $uid) {

            // 收集這個 group 用過的 file_id
            foreach ($dbGroups[$uid] as $r) {
                if (!empty($r['df_file_id'])) {
                    $this->pendingDeleteFileIds[] = (int)$r['df_file_id'];
                }
            }

            // 刪 dynamic_fields row
            $this->stmt("
                DELETE FROM {$this->tableName}
                WHERE df_d_id=? AND df_field_group=? AND df_group_uid=?
            ")->execute([$d_id, $fieldGroup, $uid]);
        }

        /* =====================================================
         * 新增 / 更新 group
         * ===================================================== */
        foreach ($data as $gi => $group) {
            $uid = (string)$group['_uid'];

            // 新 group
            if (!isset($dbGroups[$uid])) {
                $this->insertGroup($d_id, $fieldGroup, (int)$gi, $uid, $group, $fieldConfig);
                continue;
            }

            // reorder
            if ($dbIndex[$uid] !== (int)$gi) {
                $this->updateGroupIndexByUid($d_id, $fieldGroup, $uid, (int)$gi);
            }

            // 沒變就完全不動
            if (!$this->isGroupDirty($group, $dbGroups[$uid], $fieldConfig)) {
                continue;
            }

            $this->updateGroupByUidDiff(
                $d_id,
                $fieldGroup,
                $uid,
                (int)$gi,
                $group,
                $dbGroups[$uid],
                $fieldConfig
            );
        }

        /* =====================================================
         * Phase 2️⃣ 統一刪 orphan image（最終狀態）
         * ===================================================== */
        $this->cleanupOrphanImages($d_id, $fieldGroup);

        return true;
    }

    /* =========================
     * 判斷並刪除真正 orphan 的 image
     * ========================= */
    private function cleanupOrphanImages($d_id, $fieldGroup)
    {
        if (empty($this->pendingDeleteFileIds)) {
            return;
        }

        $this->pendingDeleteFileIds = array_values(
            array_unique($this->pendingDeleteFileIds)
        );

        // 最終仍被所有動態欄位使用的 file_id (跨記錄、跨原本群組)
        // 確保這張圖真的沒有任何動態欄位在使用，才執行刪除
        $stmt = $this->conn->prepare("
            SELECT DISTINCT df_file_id
            FROM {$this->tableName}
            WHERE df_file_id IS NOT NULL
        ");
        $stmt->execute();
        $aliveFileIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $aliveFileIds = array_map('intval', $aliveFileIds);

        foreach ($this->pendingDeleteFileIds as $fid) {
            if (!in_array($fid, $aliveFileIds, true)) {
                $this->deleteImageFile($fid);
            }
        }

        $this->pendingDeleteFileIds = [];
    }

    /* =========================
     * Dirty Check (簡化版)
     * ========================= */
    private function isGroupDirty($new, $old, $fieldConfig)
    {
        foreach ($fieldConfig as $f) {
            $name = $f['name'];

            if (!array_key_exists($name, $new)) {
                continue;
            }

            $newVal = $new[$name];
            $oldRow = $old[$name] ?? null;

            if ($f['type'] === 'image' || $f['type'] === 'file') {
                if ($this->isMediaFieldDirty($newVal, $oldRow, $f)) {
                    return true;
                }
            } else {
                if ($this->isTextFieldDirty($newVal, $oldRow)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 檢查媒體欄位 (圖片或檔案) 是否有變更
     */
    private function isMediaFieldDirty($newVal, $oldRow, $fieldConfig)
    {
        $isMultiple = $fieldConfig['multiple'] ?? false;

        if ($isMultiple) {
            return $this->isMultipleMediaDirty($newVal, $oldRow);
        } else {
            return $this->isSingleMediaDirty($newVal, $oldRow);
        }
    }

    /**
     * 檢查單一媒體欄位是否有變更
     */
    private function isSingleMediaDirty($newVal, $oldRow)
    {
        // 前端「移除」模式 1: 直接送 '__DELETE__'
        if ($newVal === '__DELETE__') {
            $oldFileId = is_array($oldRow) ? ($oldRow['df_file_id'] ?? null) : null;
            return !empty($oldFileId);
        }

        // 前端「移除」模式 2: 送 array，且 [file_id] 為 '__DELETE__'
        if (is_array($newVal) && ($newVal['file_id'] ?? null) === '__DELETE__') {
            $oldFileId = is_array($oldRow) ? ($oldRow['df_file_id'] ?? null) : null;
            return !empty($oldFileId);
        }

        // 沒有新值 (可能完全沒選、或只改說明但 file_id 沒變)
        if (is_array($newVal)) {
            $newFileId = $newVal['file_id'] ?? null;
            $oldFileId = is_array($oldRow) ? ($oldRow['df_file_id'] ?? null) : null;

            // 如果 file_id 有變，一定是 dirty
            if ($newFileId != $oldFileId) {
                return true;
            }

            // 如果 file_id 沒變，但說明有送且跟舊的不一樣，也是 dirty
            if ($newFileId && isset($newVal['title'])) {
                $oldInfo = $this->getFileInfo($newFileId);
                if ($oldInfo && ($newVal['title'] !== ($oldInfo['file_title'] ?? ''))) {
                    return true;
                }
            }

            return false;
        }

        // 單值 (Legacy or special case)
        $newFileId = $newVal;
        $oldFileId = is_array($oldRow) ? ($oldRow['df_file_id'] ?? null) : null;

        return $newFileId != $oldFileId;
    }

    /**
     * 檢查多個媒體欄位是否有變更
     */
    private function isMultipleMediaDirty($newVal, $oldRow)
    {
        $newMedia = is_array($newVal) ? $newVal : [];
        $oldMedia = is_array($oldRow) ? $oldRow : ($oldRow ? [$oldRow] : []);

        // 比較數量
        if (count($newMedia) !== count($oldMedia)) {
            return true;
        }

        // 比較每個媒體的 file_id
        foreach ($newMedia as $idx => $m) {
            $newFileId = is_array($m) ? ($m['file_id'] ?? null) : $m;
            $oldFileId = isset($oldMedia[$idx]) ?
                (is_array($oldMedia[$idx]) ? ($oldMedia[$idx]['file_id'] ?? null) : $oldMedia[$idx]) :
                null;

            if ($newFileId != $oldFileId) {
                return true;
            }

            // 也檢查標題是否有變
            if ($newFileId && isset($m['title'])) {
                $oldInfo = $this->getFileInfo($newFileId);
                if ($oldInfo && ($m['title'] !== ($oldInfo['file_title'] ?? ''))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 檢查文字欄位是否有變更
     */
    private function isTextFieldDirty($newVal, $oldRow)
    {
        // 新增非空值
        if (!$oldRow && trim((string)$newVal) !== '') {
            return true;
        }

        // 值有變更
        if ($oldRow && (string)$newVal !== (string)$oldRow['df_field_value']) {
            return true;
        }

        return false;
    }

    /* =========================
     * Diff Update (簡化版)
     * ========================= */
    private function updateGroupByUidDiff($d_id, $fieldGroup, $uid, $gi, $new, $old, $fieldConfig)
    {
        foreach ($fieldConfig as $f) {
            $name = $f['name'];

            if (!array_key_exists($name, $new)) {
                continue;
            }

            $newVal = $new[$name];
            $oldRow = $old[$name] ?? null;

            if ($f['type'] === 'image' || $f['type'] === 'file') {
                $this->updateMediaField($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow, $f);
            } else {
                $this->updateTextField($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow, $fieldConfig);
            }
        }
    }

    /**
     * 更新媒體欄位 (圖片或檔案)
     */
    private function updateMediaField($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow, $fieldConfig)
    {
        $isMultiple = $fieldConfig['multiple'] ?? false;

        if ($isMultiple) {
            // 多媒體模式
            $this->updateMultipleMedia($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow);
        } else {
            // 單媒體模式
            $this->updateSingleMedia($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow);
        }
    }

    /**
     * 更新單一媒體欄位
     */
    private function updateSingleMedia($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow)
    {
        $newFileId = is_array($newVal) ? ($newVal['file_id'] ?? $newVal['file_id_hidden'] ?? null) : $newVal;
        $oldFileId = is_array($oldRow) ? ($oldRow['df_file_id'] ?? null) : null;
        $newTitle  = is_array($newVal) ? ($newVal['title'] ?? null) : null;

        // 刪除：前端會送 null 或 '__DELETE__'
        if ($newFileId === null || $newFileId === '__DELETE__') {
            if (!empty($oldFileId)) {
                $this->pendingDeleteFileIds[] = (int)$oldFileId;
            }

            $this->stmt("
                UPDATE {$this->tableName}
                SET df_file_id = NULL
                WHERE df_d_id=? AND df_field_group=? AND df_group_uid=? AND df_field_name=?
            ")->execute([$d_id, $fieldGroup, $uid, $name]);

            return;
        }

        // 沒選新檔、也不是刪除：檢查是否需要更新標題
        if ($newFileId === null || $newFileId === '') {
            return;
        }

        // 更新標題 (file_set)
        if ($newFileId && $newTitle !== null) {
            $this->stmt("UPDATE file_set SET file_title=? WHERE file_id=?")
                 ->execute([$newTitle, $newFileId]);
        }

        // 新增記錄
        if (!$oldRow) {
            $this->insertRow($d_id, $fieldGroup, $uid, $name, null, $newFileId, (int)$gi, 0);
            return;
        }

        // 更新記錄
        if ($newFileId != $oldFileId) {
            $this->stmt("
                UPDATE {$this->tableName}
                SET df_file_id=?
                WHERE df_id=?
            ")->execute([$newFileId, $oldRow['df_id']]);

            if ($oldFileId) {
                $this->pendingDeleteFileIds[] = (int)$oldFileId;
            }
        }
    }

    /**
     * 更新文字欄位
     */
    private function updateTextField($d_id, $fieldGroup, $uid, $gi, $name, $newVal, $oldRow, $fieldConfig)
    {
        // 新增記錄
        if (!$oldRow) {
            if (trim((string)$newVal) === '') {
                return;
            }
            $this->insertGroup($d_id, $fieldGroup, (int)$gi, $uid, [$name => $newVal], $fieldConfig);
            return;
        }

        // 更新記錄
        if ((string)$newVal !== (string)$oldRow['df_field_value']) {
            $this->stmt("
                UPDATE {$this->tableName}
                SET df_field_value=?
                WHERE df_id=?
            ")->execute([$newVal, $oldRow['df_id']]);
        }
    }

    /**
     * 處理多個媒體欄位的更新
     */
    private function updateMultipleMedia($d_id, $fieldGroup, $uid, $gi, $fieldName, $newMedia, $oldMedia)
    {
        $newMedia = is_array($newMedia) ? $newMedia : [];

        // 先查詢舊的記錄
        $stmt = $this->stmt("
            SELECT df_id, df_file_id FROM {$this->tableName}
            WHERE df_d_id=? AND df_field_group=? AND df_group_uid=? AND df_field_name=?
        ");
        $stmt->execute([$d_id, $fieldGroup, $uid, $fieldName]);
        $oldRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 收集要刪除的 file_id
        foreach ($oldRows as $row) {
            if (!empty($row['df_file_id'])) {
                $this->pendingDeleteFileIds[] = (int)$row['df_file_id'];
            }
        }

        // 刪除舊記錄
        $this->stmt("
            DELETE FROM {$this->tableName}
            WHERE df_d_id=? AND df_field_group=? AND df_group_uid=? AND df_field_name=?
        ")->execute([$d_id, $fieldGroup, $uid, $fieldName]);

        // 插入新媒體
        $sort = 0;
        foreach ($newMedia as $m) {
            $fileId = is_array($m) ? ($m['file_id'] ?? null) : $m;
            $title  = is_array($m) ? ($m['title'] ?? null) : null;

            if (!$fileId || $fileId === '__DELETE__' || $fileId === '') {
                continue;
            }

            // 更新標題
            if ($title !== null) {
                $this->stmt("UPDATE file_set SET file_title=? WHERE file_id=?")
                     ->execute([$title, $fileId]);
            }

            $this->insertRow($d_id, $fieldGroup, $uid, $fieldName, null, $fileId, (int)$gi, $sort++);
        }
    }

    /* =========================
     * Insert helpers
     * ========================= */
    private function insertGroup($d_id, $fieldGroup, $gi, $uid, $group, $fieldConfig)
    {
        $sort = 0;

        foreach ($group as $name => $value) {
            if ($name === '_uid') {
                continue;
            }

            // 找到對應的欄位配置
            $fieldDef = null;
            foreach ($fieldConfig as $f) {
                if ($f['name'] === $name) {
                    $fieldDef = $f;
                    break;
                }
            }

            if ($this->isMediaField($name, $fieldConfig)) {
                $isMultiple = $fieldDef ? ($fieldDef['multiple'] ?? false) : false;

                if ($isMultiple && is_array($value)) {
                    // 多媒體模式
                    foreach ($value as $m) {
                        $fid = is_array($m) ? ($m['file_id'] ?? null) : $m;
                        $title = is_array($m) ? ($m['title'] ?? null) : null;

                        if (!$fid || $fid === '__DELETE__') continue;

                        if ($title !== null) {
                            $this->stmt("UPDATE file_set SET file_title=? WHERE file_id=?")
                                 ->execute([$title, $fid]);
                        }

                        $this->insertRow($d_id, $fieldGroup, $uid, $name, null, $fid, (int)$gi, $sort++);
                    }
                } else {
                    // 單媒體模式
                    $fid = is_array($value) ? ($value['file_id'] ?? null) : $value;
                    $title = is_array($value) ? ($value['title'] ?? null) : null;

                    if ($fid && $fid !== '__DELETE__') {
                        if ($title !== null) {
                            $this->stmt("UPDATE file_set SET file_title=? WHERE file_id=?")
                                 ->execute([$title, $fid]);
                        }
                        $this->insertRow($d_id, $fieldGroup, $uid, $name, null, $fid, (int)$gi, $sort++);
                    }
                }
            } else {
                if (trim((string)$value) === '') {
                    continue;
                }
                $this->insertRow($d_id, $fieldGroup, $uid, $name, $value, null, (int)$gi, $sort++);
            }
        }
    }

    private function insertRow($d_id, $fieldGroup, $uid, $name, $text, $fileId, $gi, $sort)
    {
        $this->stmt("
            INSERT INTO {$this->tableName}
            (df_d_id, df_field_group, df_group_uid, df_field_name,
             df_field_value, df_file_id, df_group_index, df_sort)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $d_id,
            $fieldGroup,
            $uid,
            $name,
            $text,
            $fileId,
            (int)$gi,
            (int)$sort
        ]);
    }

    private function updateGroupIndexByUid($d_id, $fieldGroup, $uid, $gi)
    {
        $this->stmt("
            UPDATE {$this->tableName}
            SET df_group_index=?
            WHERE df_d_id=? AND df_field_group=? AND df_group_uid=?
        ")->execute([(int)$gi, $d_id, $fieldGroup, $uid]);
    }

    private function isMediaField($name, $fieldConfig)
    {
        foreach ($fieldConfig as $f) {
            if (($f['name'] ?? '') === $name && (($f['type'] ?? '') === 'image' || ($f['type'] ?? '') === 'file')) {
                return true;
            }
        }
        return false;
    }

    /* =========================
     * 真正刪 image（只在 Phase 2）
     * ========================= */
    private function deleteImageFile($fileId)
    {
        if (!$fileId) {
            return;
        }

        $info = $this->getFileInfo($fileId);
        if (!$info) {
            return;
        }

        for ($i = 1; $i <= 5; $i++) {
            $k = "file_link{$i}";
            if (!empty($info[$k])) {
                $path = "../" . $info[$k];
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        $this->conn
            ->prepare("DELETE FROM file_set WHERE file_id=?")
            ->execute([$fileId]);
    }

    private function getFileInfo($fileId)
    {
        $stmt = $this->stmt("SELECT * FROM file_set WHERE file_id=? LIMIT 1");
        $stmt->execute([$fileId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
