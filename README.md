# Template Backend

後端管理系統專案

---

## 📋 專案初始化

### 1. Clone 專案
```bash
git clone <repository-url>
cd template-backend
```

### 2. 環境設定

#### 配置檔案
- 複製 `.env_example` 為 `.env`
- 修改 `.env` 檔案，設定為本機環境參數

#### 系統配置
- 編輯 `config/config.php`
- 調整本機環境相關設定（如需要）

### 3. 資料庫設定
- 將 SQL 檔案匯入到 phpMyAdmin
- 確認資料庫連線設定正確

---

## 🎨 Template 環境設定

如果使用 **template** 模式（`SYSTEM_TEMPLATE = 'template'`）：

1. 確認 `config/config.php` 中的 `SYSTEM_TEMPLATE` 設定
2. 配置 `template_set.php` 檔案
3. 圖片路徑將使用 `/img/{lang}/` 格式

如果使用 **views** 模式（`SYSTEM_TEMPLATE = 'views'`）：

1. 圖片路徑將使用 `/images/{lang}/` 格式
2. 不需要載入 `template_set.php`

---

## 🔧 統一配置說明

所有系統配置統一在 `config/config.php` 中管理：

- **環境判斷**：`IS_LOCAL` - 自動判斷本機或正式環境
- **IP 白名單**：`ALLOWED_IPS` - 允許存取的 IP 列表
- **路徑設定**：`APP_ROOT_PATH` - 專案根路徑（本機/正式環境自動切換）

---

## 📝 注意事項

- 本機環境會自動使用 `/template-backend` 作為根路徑
- 正式環境根路徑為空字串
- 所有路徑配置都基於 `APP_ROOT_PATH` 自動組合

## 🖼️ 圖片路徑使用說明

### 一般圖片
使用 `$baseurl` 變數來取得圖片路徑：

```php
<img src="<?=$baseurl?>/images/example.jpg" alt="範例圖片">
```

### 多語系圖片
使用 `$FRONT_IMG_PATH` 變數來取得語系化的圖片路徑：

```php
<img src="<?=$FRONT_IMG_PATH?>banner.jpg" alt="多語系橫幅">
```

> **說明**：`$FRONT_IMG_PATH` 會根據當前語系自動切換路徑  
> 例如：`/images/tw/` 或 `/images/en/`

---