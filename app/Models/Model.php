<?php
namespace App\Models;

abstract class Model {
    protected $db;
    protected $isAdmin = false;

    public function __construct() {
        if (isset($GLOBALS['db'])) {
            $this->db = $GLOBALS['db'];
        } else {
            global $db;
            $this->db = $db;
        }

        if (!$this->db) {
            die("錯誤：抓不到資料庫連線。");
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(0, '/'); 
            session_start();
        }
        $this->isAdmin = isset($_SESSION['MM_LoginAccountUsername']);
    }
}