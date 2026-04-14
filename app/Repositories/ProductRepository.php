<?php
namespace App\Repositories;

use App\Repositories\DataRepository;

/**
 * ProductRepository - 產品資料存取層 (還原狀態)
 */
class ProductRepository extends DataRepository {
    // 回復原始狀態，移除所有自動階層偵測邏輯
}
