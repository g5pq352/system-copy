-- --------------------------------------------------------
-- 主機:                           127.0.0.1
-- 伺服器版本:                        8.3.0 - MySQL Community Server - GPL
-- 伺服器操作系統:                      Win64
-- HeidiSQL 版本:                  9.5.0.5196
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- 傾印  表格 template_ver3.address_book_set 結構
CREATE TABLE IF NOT EXISTS `address_book_set` (
  `a_id` int NOT NULL AUTO_INCREMENT,
  `a_title` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_subtitle` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_class1` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_class2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_gender` tinyint DEFAULT NULL,
  `a_email` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_tel` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_address` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_display_name` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_year` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_month` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_day` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `a_status` tinyint(1) DEFAULT '0',
  `a_epaper` tinyint(1) DEFAULT '0',
  `a_date` datetime DEFAULT NULL,
  PRIMARY KEY (`a_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.address_book_set 的資料：0 rows
/*!40000 ALTER TABLE `address_book_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `address_book_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.admin 結構
CREATE TABLE IF NOT EXISTS `admin` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_password` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_salt` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_level` int DEFAULT NULL,
  `group_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_id` int DEFAULT NULL COMMENT '所屬權限群組',
  `user_limit` tinyint DEFAULT '2',
  `user_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.admin 的資料：1 rows
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` (`user_id`, `user_name`, `user_password`, `user_salt`, `user_level`, `group_name`, `group_id`, `user_limit`, `user_active`) VALUES
	(1, 'admin', 'bc763ad389d8d6a24ddb9853356760023f00f2916edd87870d14124d861165d8', 'a8ecbc4e0dc5eaec9a0a238625de6259', 1, NULL, 1, 1, 1);
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;

-- 傾印  表格 template_ver3.admin_login_logs 結構
CREATE TABLE IF NOT EXISTS `admin_login_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL COMMENT '使用者ID (若登入失敗可能為 NULL)',
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '嘗試登入的帳號',
  `login_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '來源 IP',
  `login_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登入時間',
  `login_status` enum('success','fail') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success' COMMENT '登入狀態',
  `login_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '登入方式 (normal/super_admin)',
  `user_device` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '登入裝置 (Desktop, Mobile, Tablet, etc.)',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '瀏覽器資訊',
  PRIMARY KEY (`id`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.admin_login_logs 的資料：~5 rows (大約)
/*!40000 ALTER TABLE `admin_login_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_login_logs` ENABLE KEYS */;

-- 傾印  表格 template_ver3.authority_groups 結構
CREATE TABLE IF NOT EXISTS `authority_groups` (
  `group_id` int NOT NULL AUTO_INCREMENT COMMENT '群組 ID',
  `group_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '群組名稱',
  `group_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '群組描述',
  `group_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '啟用狀態',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `unique_group_name` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='權限群組表';

-- 正在傾印表格  template_ver3.authority_groups 的資料：~3 rows (大約)
/*!40000 ALTER TABLE `authority_groups` DISABLE KEYS */;
INSERT INTO `authority_groups` (`group_id`, `group_name`, `group_description`, `group_active`, `created_at`, `updated_at`) VALUES
	(1, '系統管理員', '擁有所有權限', 1, '2025-12-19 11:04:23', '2025-12-19 11:04:23'),
	(2, '編輯者', '可編輯內容但無法管理系統', 1, '2025-12-19 11:04:23', '2025-12-19 11:04:23');
/*!40000 ALTER TABLE `authority_groups` ENABLE KEYS */;

-- 傾印  表格 template_ver3.class_set 結構
CREATE TABLE IF NOT EXISTS `class_set` (
  `c_id` int NOT NULL AUTO_INCREMENT,
  `c_title` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_title_en` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_slug` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_class` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_link` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_level` tinyint DEFAULT NULL,
  `c_data1` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_data2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_data3` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_data4` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_data5` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_data6` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_head` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_active` tinyint(1) NOT NULL DEFAULT '1',
  `c_parent` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `c_sort` int DEFAULT NULL,
  PRIMARY KEY (`c_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.class_set 的資料：0 rows
/*!40000 ALTER TABLE `class_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.client 結構
CREATE TABLE IF NOT EXISTS `client` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_password` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_level` int DEFAULT NULL,
  `user_limit` tinyint DEFAULT '2',
  `user_shop` int DEFAULT NULL,
  `user_active` tinyint(1) DEFAULT '1',
  `user_sort` tinyint DEFAULT '1',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- 正在傾印表格  template_ver3.client 的資料：0 rows
/*!40000 ALTER TABLE `client` DISABLE KEYS */;
/*!40000 ALTER TABLE `client` ENABLE KEYS */;

-- 傾印  表格 template_ver3.cms_drafts 結構
CREATE TABLE IF NOT EXISTS `cms_drafts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT '建立草稿的管理員ID',
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '模組名稱 (e.g. news, product)',
  `target_table` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '目標資料表',
  `record_id` int NOT NULL DEFAULT '0' COMMENT '對應的資料ID (新增為 0)',
  `draft_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '表單內容 (JSON)',
  `url_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '當下的網址參數 (JSON, 用於確保 Context 正確)',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_draft` (`user_id`,`module`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.cms_drafts 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `cms_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `cms_drafts` ENABLE KEYS */;

-- 傾印  表格 template_ver3.cms_menus 結構
CREATE TABLE IF NOT EXISTS `cms_menus` (
  `menu_id` int NOT NULL AUTO_INCREMENT COMMENT '選單ID',
  `menu_parent_id` int DEFAULT '0' COMMENT '父選單ID（0=頂層選單）',
  `menu_level` int DEFAULT '0',
  `menu_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '選單標題',
  `m_slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '類型/模組名稱（例如：popInfo、blog）',
  `menu_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '選單連結（例如：tpl=popInfo/info）',
  `menu_auth` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '權限代碼（例如：a_11）',
  `menu_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_pk` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taxonomy_type_id` int DEFAULT NULL,
  `menu_cate_type` int DEFAULT NULL,
  `menu_id_num` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '選單編號（例如：11）',
  `menu_icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_br` tinyint(1) DEFAULT '0' COMMENT '是否換行（1=換行, 0=不換行）',
  `menu_sort` int DEFAULT '0' COMMENT '排序',
  `menu_active` tinyint(1) DEFAULT '1' COMMENT '是否啟用（1=啟用, 0=停用）',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  PRIMARY KEY (`menu_id`),
  KEY `idx_parent` (`menu_parent_id`),
  KEY `idx_sort` (`menu_sort`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMS階層式選單表';

-- 正在傾印表格  template_ver3.cms_menus 的資料：~24 rows (大約)
/*!40000 ALTER TABLE `cms_menus` DISABLE KEYS */;
INSERT INTO `cms_menus` (`menu_id`, `menu_parent_id`, `menu_level`, `menu_title`, `m_slug`, `menu_type`, `menu_link`, `menu_auth`, `menu_table`, `menu_pk`, `taxonomy_type_id`, `menu_cate_type`, `menu_id_num`, `menu_icon`, `menu_br`, `menu_sort`, `menu_active`, `created_at`, `updated_at`) VALUES
	(1, 0, 0, '選單管理', '選單管理', '', 'tpl=menus/list', NULL, NULL, NULL, 0, NULL, '12', 'bx bx-file', 0, 8, 0, '2025-12-19 01:39:43', '2026-03-03 10:53:41'),
	(2, 1, 0, '前端選單', '前端選單', 'menus', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-12-22 09:36:23', '2026-01-29 08:52:54'),
	(3, 1, 0, '後端選單', '後端選單', 'cmsMenu', '', NULL, 'cms_menus', 'menu_id', NULL, NULL, '', NULL, 0, 2, 1, '2025-12-19 01:52:32', '2026-03-03 10:53:36'),
	(4, 1, 0, '標籤', '標籤', 'taxonomyType', '', NULL, NULL, NULL, NULL, NULL, NULL, '', 0, 3, 1, '2025-12-24 16:24:06', '2026-03-03 10:53:36'),
	(5, 1, 0, '語系', '語系', 'languageType', '', NULL, NULL, NULL, 0, NULL, NULL, '', 0, 4, 1, '2025-12-26 00:15:03', '2026-03-03 10:53:36'),
	(6, 0, 0, '首頁', '首頁', '', 'tpl=popInfo/info', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 1, 1, '2025-12-29 12:13:40', '2026-03-03 10:53:32'),
	(7, 0, 0, '權限管理', '權限管理', '', 'tpl=admin/list', NULL, NULL, NULL, 0, NULL, '1', 'bx bx-user-circle', 0, 6, 1, '2025-12-19 02:02:41', '2026-03-03 10:54:13'),
	(8, 7, 0, '管理員權限', '管理員權限', 'authorityCate', '', NULL, NULL, NULL, NULL, NULL, '', NULL, 0, 2, 1, '2025-12-19 11:06:09', '2026-02-04 11:21:26'),
	(9, 7, 0, '管理員', '管理員', 'admin', '', NULL, NULL, NULL, NULL, NULL, '', NULL, 0, 1, 1, '2025-12-19 11:05:25', '2026-02-04 11:21:26'),
	(10, 0, 0, '全站', '全站', 'keywordsInfo', '', NULL, NULL, NULL, NULL, NULL, '10', 'bx bx-cog', 0, 5, 1, '2025-12-19 02:01:56', '2026-03-03 10:53:32'),
	(11, 0, 0, '最新消息', '最新消息', '', 'tpl=news/list', NULL, NULL, NULL, 0, NULL, '8', 'bx bx-file', 0, 2, 1, '2025-12-19 02:00:26', '2026-03-03 10:53:32'),
	(12, 11, 1, '最新消息', '最新消息', 'news', '', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 1, 1, '2025-12-29 12:28:50', '2026-01-29 17:38:07'),
	(13, 11, 1, '分類', '分類', 'newsCate', '', NULL, NULL, NULL, 1, NULL, NULL, 'bx bx-file', 0, 2, 1, '2025-12-29 12:29:03', '2026-01-30 09:57:39'),
	(14, 11, 1, '標籤', '標籤-2', 'newsTag', '', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 3, 1, '2026-01-29 16:57:42', '2026-01-30 09:57:39'),
	(15, 0, 0, '聯絡我們', '聯絡我們', '', 'tpl=contactus/list', NULL, NULL, NULL, 0, NULL, '9', 'bx bx-detail', 0, 4, 1, '2025-12-19 02:01:15', '2026-03-03 10:53:32'),
	(16, 15, 0, '聯絡我們', '聯絡我們', 'contactus', '', NULL, NULL, NULL, 0, NULL, NULL, '', 0, 1, 1, '2025-12-24 15:33:34', '2026-01-29 17:37:40'),
	(17, 6, 1, '燈箱', '燈箱', 'popInfo', '', NULL, NULL, NULL, 0, NULL, NULL, '', 0, 1, 1, '2025-12-29 15:10:48', '2026-01-29 17:38:29'),
	(18, 0, 0, '圖片庫', '圖片庫', '', 'picture_library', NULL, NULL, NULL, 0, NULL, NULL, 'fa-solid fa-images', 0, 7, 0, '2025-12-30 01:31:15', '2026-03-03 10:54:13'),
	(19, 6, 1, '最新消息-首頁顯示', '最新消息-首頁顯示', 'homeDisplay', '', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 2, 0, '2026-01-13 23:22:16', '2026-02-12 17:18:28'),
	(20, 0, 0, '產品', '產品', '', 'tpl=product/list', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 3, 1, '2026-01-30 09:56:16', '2026-03-03 10:53:32'),
	(21, 20, 1, '產品', '產品-2', 'product', '', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 1, 1, '2026-01-30 09:56:51', '2026-01-30 09:57:39'),
	(22, 20, 1, '分類', '分類-2', 'productCate', '', NULL, NULL, NULL, 2, NULL, NULL, 'bx bx-file', 0, 2, 1, '2026-01-30 09:57:02', '2026-01-30 09:57:39'),
	(23, 20, 1, '標籤', '標籤-3', 'productTag', '', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 3, 1, '2026-01-30 09:57:37', '2026-01-30 09:57:39'),
	(24, 1, 1, '語言包', '語言包', 'languagePack', '', NULL, NULL, NULL, 0, NULL, NULL, 'bx bx-file', 0, 5, 1, '2026-03-03 10:53:16', '2026-03-03 10:53:36');
/*!40000 ALTER TABLE `cms_menus` ENABLE KEYS */;

-- 傾印  表格 template_ver3.data_dynamic_fields 結構
CREATE TABLE IF NOT EXISTS `data_dynamic_fields` (
  `df_id` int NOT NULL AUTO_INCREMENT,
  `df_d_id` int NOT NULL,
  `df_field_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `df_group_uid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `df_field_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `df_field_value` text COLLATE utf8mb4_unicode_ci,
  `df_file_id` int DEFAULT NULL,
  `df_group_index` int NOT NULL DEFAULT '0',
  `df_sort` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`df_id`),
  KEY `idx_d_id` (`df_d_id`),
  KEY `idx_field_group` (`df_field_group`),
  CONSTRAINT `fk_dynamic_fields` FOREIGN KEY (`df_d_id`) REFERENCES `data_set` (`d_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.data_dynamic_fields 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `data_dynamic_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_dynamic_fields` ENABLE KEYS */;

-- 傾印  表格 template_ver3.data_set 結構
CREATE TABLE IF NOT EXISTS `data_set` (
  `d_id` int NOT NULL AUTO_INCREMENT,
  `lang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `d_parent_id` int DEFAULT '0',
  `d_sn` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `d_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `d_title_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `d_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `d_slug_edit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `d_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_class1` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_parent` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '產品第二層',
  `d_class2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_class3` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_class4` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_class5` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_class6` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data1` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data3` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data4` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data5` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data6` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data7` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data8` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data9` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data10` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data11` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data12` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data13` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data14` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data15` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data16` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data17` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data18` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data19` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data20` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data21` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data22` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data23` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data24` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data25` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data26` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data27` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data28` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data29` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data30` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data31` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data32` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data33` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data34` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data35` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data36` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data37` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data38` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data39` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data40` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data41` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data42` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data43` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data44` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data45` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data46` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data47` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data48` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data49` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data50` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data51` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data52` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data53` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data54` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data55` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data56` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data57` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data58` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data59` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data60` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data61` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data62` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data63` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data64` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data65` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data66` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data67` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data68` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data69` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data70` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data71` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data72` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data73` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data74` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data75` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data76` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data77` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data78` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data79` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data80` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data81` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data82` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data83` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data84` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_data85` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_seo_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_head` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_schema` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_authorize` tinyint(1) DEFAULT '1',
  `d_youtube_code` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `d_imgType` tinyint DEFAULT '1',
  `d_decade` date DEFAULT NULL COMMENT '年代',
  `d_date` datetime DEFAULT NULL,
  `d_update_time` datetime DEFAULT NULL,
  `d_delete_time` datetime DEFAULT NULL,
  `d_active` tinyint(1) DEFAULT '1',
  `d_home_active` tinyint unsigned DEFAULT '0',
  `d_level` tinyint(1) DEFAULT '0',
  `d_genre` tinyint(1) DEFAULT '0',
  `d_type` tinyint(1) DEFAULT '1',
  `d_view` int DEFAULT '0',
  `d_sort` int DEFAULT '1',
  `d_top` int DEFAULT '0',
  `d_json_array` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`d_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.data_set 的資料：~1 rows (大約)
/*!40000 ALTER TABLE `data_set` DISABLE KEYS */;
INSERT INTO `data_set` (`d_id`, `lang`, `d_parent_id`, `d_sn`, `d_title`, `d_title_en`, `d_slug`, `d_slug_edit`, `d_content`, `d_class1`, `d_parent`, `d_class2`, `d_class3`, `d_class4`, `d_class5`, `d_class6`, `d_tag`, `d_data1`, `d_data2`, `d_data3`, `d_data4`, `d_data5`, `d_data6`, `d_data7`, `d_data8`, `d_data9`, `d_data10`, `d_data11`, `d_data12`, `d_data13`, `d_data14`, `d_data15`, `d_data16`, `d_data17`, `d_data18`, `d_data19`, `d_data20`, `d_data21`, `d_data22`, `d_data23`, `d_data24`, `d_data25`, `d_data26`, `d_data27`, `d_data28`, `d_data29`, `d_data30`, `d_data31`, `d_data32`, `d_data33`, `d_data34`, `d_data35`, `d_data36`, `d_data37`, `d_data38`, `d_data39`, `d_data40`, `d_data41`, `d_data42`, `d_data43`, `d_data44`, `d_data45`, `d_data46`, `d_data47`, `d_data48`, `d_data49`, `d_data50`, `d_data51`, `d_data52`, `d_data53`, `d_data54`, `d_data55`, `d_data56`, `d_data57`, `d_data58`, `d_data59`, `d_data60`, `d_data61`, `d_data62`, `d_data63`, `d_data64`, `d_data65`, `d_data66`, `d_data67`, `d_data68`, `d_data69`, `d_data70`, `d_data71`, `d_data72`, `d_data73`, `d_data74`, `d_data75`, `d_data76`, `d_data77`, `d_data78`, `d_data79`, `d_data80`, `d_data81`, `d_data82`, `d_data83`, `d_data84`, `d_data85`, `d_seo_title`, `d_keywords`, `d_description`, `d_head`, `d_body`, `d_schema`, `d_authorize`, `d_youtube_code`, `d_imgType`, `d_decade`, `d_date`, `d_update_time`, `d_delete_time`, `d_active`, `d_home_active`, `d_level`, `d_genre`, `d_type`, `d_view`, `d_sort`, `d_top`, `d_json_array`) VALUES
	(1, 'tw', 0, NULL, '', NULL, NULL, NULL, NULL, 'keywordsInfo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, '', '', '', '', 1, NULL, 1, NULL, NULL, NULL, NULL, 1, 0, 0, 0, 1, 0, 1, 0, NULL);
/*!40000 ALTER TABLE `data_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.data_taxonomies 結構
CREATE TABLE IF NOT EXISTS `data_taxonomies` (
  `data_id` int NOT NULL,
  `taxonomy_id` int NOT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`data_id`,`taxonomy_id`),
  KEY `fk_data_taxonomies_taxonomies` (`taxonomy_id`),
  CONSTRAINT `fk_data_taxonomies_data_set` FOREIGN KEY (`data_id`) REFERENCES `data_set` (`d_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_data_taxonomies_taxonomies` FOREIGN KEY (`taxonomy_id`) REFERENCES `taxonomies` (`t_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.data_taxonomies 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `data_taxonomies` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_taxonomies` ENABLE KEYS */;

-- 傾印  表格 template_ver3.data_taxonomy_map 結構
CREATE TABLE IF NOT EXISTS `data_taxonomy_map` (
  `map_id` int unsigned NOT NULL AUTO_INCREMENT,
  `d_id` int NOT NULL COMMENT '產品ID',
  `t_id` int NOT NULL COMMENT '分類標籤ID',
  `map_level` int NOT NULL,
  `sort_num` int NOT NULL DEFAULT '0' COMMENT '在此分類的順序',
  `d_top` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`map_id`),
  KEY `idx_d_id` (`d_id`),
  KEY `idx_t_id` (`t_id`),
  CONSTRAINT `fk_data_taxonomy_map_dataset` FOREIGN KEY (`d_id`) REFERENCES `data_set` (`d_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='資料與分類關聯對照表 (支援一對多與獨立排序)';

-- 正在傾印表格  template_ver3.data_taxonomy_map 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `data_taxonomy_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `data_taxonomy_map` ENABLE KEYS */;

-- 傾印  表格 template_ver3.file_set 結構
CREATE TABLE IF NOT EXISTS `file_set` (
  `file_d_id` int DEFAULT NULL,
  `file_t_id` int DEFAULT NULL,
  `file_id` int NOT NULL AUTO_INCREMENT,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_link1` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_link2` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_link3` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_link4` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_link5` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `file_youtube_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_show_type` tinyint(1) NOT NULL DEFAULT '0',
  `file_width` smallint unsigned DEFAULT '0',
  `file_height` smallint unsigned DEFAULT '0',
  `file_sort` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`file_id`),
  KEY `fk_file_set_dataset` (`file_d_id`,`file_t_id`),
  KEY `fk_file_set_taxonomies` (`file_t_id`),
  CONSTRAINT `fk_file_set_dataset` FOREIGN KEY (`file_d_id`) REFERENCES `data_set` (`d_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_file_set_taxonomies` FOREIGN KEY (`file_t_id`) REFERENCES `taxonomies` (`t_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.file_set 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `file_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.group_permissions 結構
CREATE TABLE IF NOT EXISTS `group_permissions` (
  `gp_id` int NOT NULL AUTO_INCREMENT COMMENT '群組權限 ID',
  `group_id` int NOT NULL COMMENT '群組 ID',
  `menu_id` int NOT NULL COMMENT '選單 ID（對應 cms_menus.menu_id）',
  `can_view` tinyint(1) NOT NULL DEFAULT '0' COMMENT '檢視權限',
  `can_add` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新增權限',
  `can_edit` tinyint(1) NOT NULL DEFAULT '0' COMMENT '修改權限',
  `can_delete` tinyint(1) NOT NULL DEFAULT '0' COMMENT '刪除權限',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
  PRIMARY KEY (`gp_id`),
  UNIQUE KEY `unique_group_menu` (`group_id`,`menu_id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_menu_id` (`menu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群組權限對應表（使用 menu_id）';

-- 正在傾印表格  template_ver3.group_permissions 的資料：~16 rows (大約)
/*!40000 ALTER TABLE `group_permissions` DISABLE KEYS */;
INSERT INTO `group_permissions` (`gp_id`, `group_id`, `menu_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES
	(1, 1, 6, 1, 0, 0, 0, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(2, 1, 17, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(3, 1, 11, 1, 0, 0, 0, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(4, 1, 12, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(5, 1, 13, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(6, 1, 14, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(7, 1, 20, 1, 0, 0, 0, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(8, 1, 21, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(9, 1, 22, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(10, 1, 23, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(11, 1, 15, 1, 0, 0, 0, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(12, 1, 16, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(13, 1, 10, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(14, 1, 7, 1, 0, 0, 0, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(15, 1, 9, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23'),
	(16, 1, 8, 1, 1, 1, 1, '2026-02-26 10:15:23', '2026-02-26 10:15:23');
/*!40000 ALTER TABLE `group_permissions` ENABLE KEYS */;

-- 傾印  表格 template_ver3.home_display 結構
CREATE TABLE IF NOT EXISTS `home_display` (
  `hd_id` int NOT NULL AUTO_INCREMENT COMMENT '主鍵',
  `hd_module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模組名稱 (例如: news, product)',
  `hd_data_id` int NOT NULL COMMENT '關聯的資料ID (data_set.d_id)',
  `hd_sort` int NOT NULL DEFAULT '0' COMMENT '排序 (每個模組獨立排序)',
  `hd_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否啟用 (1=顯示, 0=不顯示)',
  `hd_created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '建立時間',
  `lang` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tw' COMMENT '語系',
  PRIMARY KEY (`hd_id`),
  UNIQUE KEY `unique_module_data` (`hd_module`,`hd_data_id`,`lang`),
  KEY `idx_module` (`hd_module`),
  KEY `idx_sort` (`hd_sort`),
  KEY `idx_active` (`hd_active`),
  KEY `idx_lang` (`lang`),
  KEY `fk_home_display_dataset` (`hd_data_id`),
  CONSTRAINT `fk_home_display_dataset` FOREIGN KEY (`hd_data_id`) REFERENCES `data_set` (`d_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首頁顯示設定表';

-- 正在傾印表格  template_ver3.home_display 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `home_display` DISABLE KEYS */;
/*!40000 ALTER TABLE `home_display` ENABLE KEYS */;

-- 傾印  表格 template_ver3.index_set 結構
CREATE TABLE IF NOT EXISTS `index_set` (
  `object_id` int DEFAULT NULL COMMENT '現在指定的ID',
  `object_prev_id` int DEFAULT NULL COMMENT '之前的ID',
  `type` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.index_set 的資料：0 rows
/*!40000 ALTER TABLE `index_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `index_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.languages 結構
CREATE TABLE IF NOT EXISTS `languages` (
  `l_id` int NOT NULL AUTO_INCREMENT,
  `l_slug` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `l_locale` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `l_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `l_name_en` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `l_is_default` tinyint(1) DEFAULT '0',
  `l_active` tinyint(1) DEFAULT '1',
  `l_sort` int DEFAULT '0',
  `l_delete_time` datetime DEFAULT NULL,
  PRIMARY KEY (`l_id`),
  UNIQUE KEY `l_slug` (`l_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.languages 的資料：~3 rows (大約)
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` (`l_id`, `l_slug`, `l_locale`, `l_name`, `l_name_en`, `l_is_default`, `l_active`, `l_sort`, `l_delete_time`) VALUES
	(1, 'tw', 'zh-Hant-TW', '繁體中文', 'Traditional Chinese', 1, 1, 1, NULL),
	(2, 'en', 'en-US', 'English', 'English', 0, 0, 2, NULL),
	(3, 'cn', 'zh-Hans-CN', '簡體中文', 'Simplified Chinese', 0, 0, 3, NULL),
	(4, 'jp', 'ja', '日文', 'Japanese', 0, 0, 4, NULL);
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;

-- 傾印  表格 template_ver3.language_packs 結構
CREATE TABLE IF NOT EXISTS `language_packs` (
  `lp_id` int NOT NULL AUTO_INCREMENT COMMENT '主鍵',
  `lp_key` varchar(100) NOT NULL COMMENT 'Key 名稱，如 contact_us',
  `lp_tw` varchar(500) DEFAULT NULL COMMENT '繁體中文（預設語系）',
  `lp_en` varchar(500) DEFAULT NULL COMMENT '英文',
  `lp_cn` varchar(500) DEFAULT NULL COMMENT '簡體中文',
  `lp_jp` varchar(500) DEFAULT NULL COMMENT '日文',
  `lp_note` varchar(255) DEFAULT NULL COMMENT '備註',
  `lp_sort` int DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`lp_id`),
  UNIQUE KEY `lp_key` (`lp_key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='語言包管理';

-- 正在傾印表格  template_ver3.language_packs 的資料：~10 rows (大約)
/*!40000 ALTER TABLE `language_packs` DISABLE KEYS */;
INSERT INTO `language_packs` (`lp_id`, `lp_key`, `lp_tw`, `lp_en`, `lp_cn`, `lp_jp`, `lp_note`, `lp_sort`) VALUES
	(1, 'contact_us', '聯絡我們', 'Contact Us', '联系我们', 'お問い合わせ', '聯絡我們按鈕/連結文字', 6),
	(2, 'home', '首頁', 'Home', '首页', 'ホーム', '首頁導航文字', 1),
	(3, 'about', '關於我們', 'About Us', '关于我们', '私たちについて', '關於我們頁面文字', 2),
	(4, 'news', '最新消息', 'News', '最新消息', 'お知らせ', '最新消息頁面文字', 3),
	(5, 'products', '產品', 'Products', '产品', '製品', '產品頁面文字', 4),
	(6, 'blog', '部落格', 'Blog', '博客', 'ブログ', '部落格頁面文字', 5),
	(7, 'read_more', '了解更多', 'Read More', '了解更多', '詳しく見る', '閱讀更多按鈕', 7),
	(8, 'search', '搜尋', 'Search', '搜索', '検索', '搜尋按鈕文字', 8),
	(9, 'submit', '送出', 'Submit', '提交', '送信', '送出按鈕文字', 9),
	(10, 'cancel', '取消', 'Cancel', '取消', 'キャンセル', '取消按鈕文字', 10);
/*!40000 ALTER TABLE `language_packs` ENABLE KEYS */;

-- 傾印  表格 template_ver3.media_files 結構
CREATE TABLE IF NOT EXISTS `media_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '檔案唯一ID (存這個進文章)',
  `folder_id` int unsigned DEFAULT NULL COMMENT '目前所在的資料夾ID',
  `filename_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '硬碟上的真實檔名 (如: img_693f6...jpg)',
  `filename_original` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原始上傳檔名 (如: 2025活動照.jpg)',
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '副檔名或MIME type (如: image/jpeg)',
  `file_size` int DEFAULT NULL COMMENT '檔案大小 (bytes)',
  `width` int DEFAULT NULL COMMENT '圖片寬度 (選填)',
  `height` int DEFAULT NULL COMMENT '圖片高度 (選填)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `folder_id` (`folder_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.media_files 的資料：0 rows
/*!40000 ALTER TABLE `media_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_files` ENABLE KEYS */;

-- 傾印  表格 template_ver3.media_folders 結構
CREATE TABLE IF NOT EXISTS `media_folders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned DEFAULT NULL COMMENT '上層資料夾ID，若是根目錄則為NULL',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '資料夾名稱，如: blog',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.media_folders 的資料：0 rows
/*!40000 ALTER TABLE `media_folders` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_folders` ENABLE KEYS */;

-- 傾印  表格 template_ver3.member_set 結構
CREATE TABLE IF NOT EXISTS `member_set` (
  `d_id` int unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'tw' COMMENT '語系',
  `d_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '名稱 (Name)',
  `d_account` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '帳號 (Account)',
  `d_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '密碼 (Password)',
  `d_salt` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '鹽值 (Salt)',
  `d_class2` int DEFAULT '0' COMMENT 'shopCate 分類 ID',
  `d_active` tinyint(1) DEFAULT '1' COMMENT '狀態 (1:啟用, 0:停用)',
  `d_sort` int DEFAULT '0' COMMENT '排序',
  `d_create_time` datetime DEFAULT NULL COMMENT '建立時間',
  `d_update_time` datetime DEFAULT NULL COMMENT '更新時間',
  PRIMARY KEY (`d_id`),
  UNIQUE KEY `idx_account` (`d_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.member_set 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `member_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `member_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.menus_set 結構
CREATE TABLE IF NOT EXISTS `menus_set` (
  `m_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '流水號',
  `lang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m_parent_id` int NOT NULL DEFAULT '0' COMMENT '父層ID (0=主選單)',
  `m_level` int NOT NULL DEFAULT '0',
  `m_title_ch` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '中文標題',
  `m_title_en` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '英文標題',
  `m_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '連結網址',
  `m_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `m_target` tinyint(1) DEFAULT '0' COMMENT '連結類型',
  `m_sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `m_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '狀態 (1=啟用, 0=停用)',
  `m_depth` tinyint NOT NULL DEFAULT '1' COMMENT '層級 (1=主選單, 2=子選單)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`m_id`),
  KEY `idx_parent_sort` (`m_parent_id`,`m_sort`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='前台選單設定';

-- 正在傾印表格  template_ver3.menus_set 的資料：~2 rows (大約)
/*!40000 ALTER TABLE `menus_set` DISABLE KEYS */;
INSERT INTO `menus_set` (`m_id`, `lang`, `m_parent_id`, `m_level`, `m_title_ch`, `m_title_en`, `m_link`, `m_slug`, `m_target`, `m_sort`, `m_active`, `m_depth`, `created_at`, `updated_at`) VALUES
	(1, 'tw', 0, 0, '最新消息', 'NEWS', '/news', '最新消息', 0, 1, 1, 1, '2026-01-07 16:45:54', '2026-01-23 15:44:50'),
	(2, 'tw', 0, 0, '聯絡我們', 'CONTACT', '/contact', '聯絡我們', 0, 2, 1, 1, '2025-12-11 12:22:12', '2026-01-23 15:44:50');
/*!40000 ALTER TABLE `menus_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.message_reply 結構
CREATE TABLE IF NOT EXISTS `message_reply` (
  `r_id` int NOT NULL AUTO_INCREMENT,
  `m_id` int NOT NULL COMMENT '對應 message_set m_id',
  `r_subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `r_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `r_date` datetime NOT NULL,
  `r_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '回覆者帳號',
  PRIMARY KEY (`r_id`),
  KEY `m_id` (`m_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服回覆紀錄表';

-- 正在傾印表格  template_ver3.message_reply 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `message_reply` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_reply` ENABLE KEYS */;

-- 傾印  表格 template_ver3.message_set 結構
CREATE TABLE IF NOT EXISTS `message_set` (
  `m_id` int unsigned NOT NULL AUTO_INCREMENT,
  `m_d_id` int unsigned NOT NULL DEFAULT '0',
  `m_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `m_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `m_inquiry` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `m_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `m_email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `m_phone` text COLLATE utf8mb4_unicode_ci,
  `m_cellphone` text COLLATE utf8mb4_unicode_ci,
  `m_address` text COLLATE utf8mb4_unicode_ci,
  `m_company` text COLLATE utf8mb4_unicode_ci,
  `m_data1` text COLLATE utf8mb4_unicode_ci,
  `m_data2` text COLLATE utf8mb4_unicode_ci,
  `m_data3` text COLLATE utf8mb4_unicode_ci,
  `m_data4` text COLLATE utf8mb4_unicode_ci,
  `m_data5` text COLLATE utf8mb4_unicode_ci,
  `m_data6` text COLLATE utf8mb4_unicode_ci,
  `m_device` text COLLATE utf8mb4_unicode_ci,
  `m_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m_ip` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `m_date` datetime DEFAULT NULL,
  `m_read` tinyint(1) DEFAULT '0',
  `m_reply` tinyint(1) DEFAULT '0',
  `m_status` enum('pending','processing','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT '處理狀態: pending=待處理, processing=處理中, completed=已完成, cancelled=已取消',
  `m_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '內部備註（不會顯示給客戶）',
  `m_handler` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '處理人員（管理員帳號）',
  `m_handled_at` datetime DEFAULT NULL COMMENT '處理時間',
  `m_m_id` int DEFAULT NULL,
  `m_rem_id` int DEFAULT NULL,
  PRIMARY KEY (`m_id`),
  KEY `idx_m_status` (`m_status`),
  KEY `idx_m_type` (`m_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.message_set 的資料：0 rows
/*!40000 ALTER TABLE `message_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.redirects_set 結構
CREATE TABLE IF NOT EXISTS `redirects_set` (
  `r_id` int NOT NULL AUTO_INCREMENT,
  `r_source_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '來源網址',
  `r_target_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '目標網址',
  `r_redirect_type` int NOT NULL DEFAULT '301' COMMENT '301永久/302暫時',
  `r_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否啟用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`r_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.redirects_set 的資料：0 rows
/*!40000 ALTER TABLE `redirects_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `redirects_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.tab_set 結構
CREATE TABLE IF NOT EXISTS `tab_set` (
  `tab_d_id` int DEFAULT '0',
  `tab_id` int NOT NULL AUTO_INCREMENT,
  `tab_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_title_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_price` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_data1` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_data2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_data3` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tab_sort` int DEFAULT '0',
  PRIMARY KEY (`tab_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.tab_set 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `tab_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `tab_set` ENABLE KEYS */;

-- 傾印  表格 template_ver3.taxonomies 結構
CREATE TABLE IF NOT EXISTS `taxonomies` (
  `t_id` int NOT NULL AUTO_INCREMENT,
  `lang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taxonomy_type_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `t_level` int DEFAULT NULL,
  `t_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `t_name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `t_content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_seo_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `t_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `t_top` int DEFAULT '0',
  PRIMARY KEY (`t_id`),
  KEY `fk_taxonomies_taxonomy_type` (`taxonomy_type_id`),
  KEY `fk_taxonomies_parent` (`parent_id`),
  CONSTRAINT `fk_taxonomies_parent` FOREIGN KEY (`parent_id`) REFERENCES `taxonomies` (`t_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_taxonomies_taxonomy_type` FOREIGN KEY (`taxonomy_type_id`) REFERENCES `taxonomy_types` (`ttp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.taxonomies 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `taxonomies` DISABLE KEYS */;
/*!40000 ALTER TABLE `taxonomies` ENABLE KEYS */;

-- 傾印  表格 template_ver3.taxonomy_types 結構
CREATE TABLE IF NOT EXISTS `taxonomy_types` (
  `ttp_id` int NOT NULL AUTO_INCREMENT,
  `lang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ttp_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `t_slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ttp_content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifier` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '系統用代碼，例如 Game type, category, tag',
  `ttp_category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ttp_set_primary` tinyint(1) DEFAULT '0',
  `ttp_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ttp_id`),
  UNIQUE KEY `identifier` (`identifier`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.taxonomy_types 的資料：~1 rows (大約)
/*!40000 ALTER TABLE `taxonomy_types` DISABLE KEYS */;
INSERT INTO `taxonomy_types` (`ttp_id`, `lang`, `ttp_name`, `t_slug`, `ttp_content`, `identifier`, `ttp_category`, `ttp_set_primary`, `ttp_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(1, 'tw', '最新消息', '最新消息', NULL, NULL, 'newsC', 0, 1, 1, '2025-11-21 23:47:06', '2025-11-21 23:47:06', '2025-12-24 16:21:59'),
	(2, 'tw', '產品', '產品', NULL, NULL, 'productC', 0, 1, 2, '2026-01-30 09:55:50', '2026-01-30 09:55:58', '2026-01-30 09:55:58');
/*!40000 ALTER TABLE `taxonomy_types` ENABLE KEYS */;

-- 傾印  表格 template_ver3.terms 結構
CREATE TABLE IF NOT EXISTS `terms` (
  `term_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `term_group` bigint NOT NULL DEFAULT '0',
  `term_type` tinyint DEFAULT '1',
  `term_active` tinyint NOT NULL DEFAULT '1',
  `term_sort` int DEFAULT '1',
  PRIMARY KEY (`term_id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.terms 的資料：0 rows
/*!40000 ALTER TABLE `terms` DISABLE KEYS */;
/*!40000 ALTER TABLE `terms` ENABLE KEYS */;

-- 傾印  表格 template_ver3.term_relationships 結構
CREATE TABLE IF NOT EXISTS `term_relationships` (
  `object_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_taxonomy_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.term_relationships 的資料：0 rows
/*!40000 ALTER TABLE `term_relationships` DISABLE KEYS */;
/*!40000 ALTER TABLE `term_relationships` ENABLE KEYS */;

-- 傾印  表格 template_ver3.term_taxonomy 結構
CREATE TABLE IF NOT EXISTS `term_taxonomy` (
  `term_taxonomy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `taxonomy` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `t_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent` bigint unsigned NOT NULL DEFAULT '0',
  `count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.term_taxonomy 的資料：0 rows
/*!40000 ALTER TABLE `term_taxonomy` DISABLE KEYS */;
/*!40000 ALTER TABLE `term_taxonomy` ENABLE KEYS */;

-- 傾印  表格 template_ver3.view_log 結構
CREATE TABLE IF NOT EXISTS `view_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL COMMENT '文章 ID',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IP 位址（支援 IPv6）',
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '瀏覽器 User-Agent',
  `device_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '裝置類型 (Desktop/Mobile/Tablet)',
  `browser` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '瀏覽器名稱',
  `os` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '作業系統',
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '國家',
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '城市',
  `referer` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '來源頁面',
  `viewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '瀏覽時間',
  PRIMARY KEY (`id`),
  KEY `idx_article_ip` (`article_id`,`ip_address`),
  KEY `idx_viewed_at` (`viewed_at`),
  KEY `idx_country` (`country`),
  KEY `idx_device_type` (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章瀏覽記錄（用於防止重複計數，保留 60 秒）';

-- 正在傾印表格  template_ver3.view_log 的資料：~0 rows (大約)
/*!40000 ALTER TABLE `view_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `view_log` ENABLE KEYS */;

-- 傾印  表格 template_ver3.webcount 結構
CREATE TABLE IF NOT EXISTS `webcount` (
  `count_id` int NOT NULL AUTO_INCREMENT,
  `count_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count_time` datetime DEFAULT NULL,
  PRIMARY KEY (`count_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.webcount 的資料：0 rows
/*!40000 ALTER TABLE `webcount` DISABLE KEYS */;
/*!40000 ALTER TABLE `webcount` ENABLE KEYS */;

-- 傾印  表格 template_ver3.zipcode 結構
CREATE TABLE IF NOT EXISTS `zipcode` (
  `Id` bigint NOT NULL AUTO_INCREMENT,
  `City` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Area` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ZipCode` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_id` int DEFAULT NULL COMMENT '對應縣市',
  `z_date` date DEFAULT NULL,
  PRIMARY KEY (`Id`),
  KEY `City` (`City`,`Area`,`ZipCode`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  template_ver3.zipcode 的資料：0 rows
/*!40000 ALTER TABLE `zipcode` DISABLE KEYS */;
/*!40000 ALTER TABLE `zipcode` ENABLE KEYS */;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
