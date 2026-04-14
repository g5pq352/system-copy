<?php
namespace App\Utils;

class Paginator {
    public $totalCount;
    public $perPage;
    public $current_page;
    public $total;
    public $baseUrl;
    
    public $queryString; 

    public function __construct($totalCount, $perPage, $current_page, $baseUrl, $queryString = '', $langurl = null) {
        $this->totalCount = $totalCount;
        $this->perPage = $perPage;
        $this->current_page = max(1, (int)$current_page);
        
        // 【修改】如果傳入 langurl，使用它；否則使用 BASE_PATH
        if ($langurl !== null) {
            $this->baseUrl = $langurl . $baseUrl;
        } else {
            $this->baseUrl = BASE_PATH . $baseUrl;
        }
        
        $this->queryString = $queryString;
        
        $this->total = ceil($totalCount / $perPage);
    }

    public function url($page) {
        return $this->baseUrl . $page . $this->queryString;
    }

    public function prev() {
        return ($this->current_page > 1) ? $this->url($this->current_page - 1) : null;
    }

    public function next() {
        return ($this->current_page < $this->total) ? $this->url($this->current_page + 1) : null;
    }

    public function items($range = 2) {
        $rawList = [];
        
        if ($this->total <= 1) {
            $rawList = [1];
        } else {
            for ($i = 1; $i <= $this->total; $i++) {
                if ($i == 1 || $i == $this->total || ($i >= $this->current_page - $range && $i <= $this->current_page + $range)) {
                    $rawList[] = $i;
                }
            }
        }

        $structuredList = [];
        $lastNum = 0;

        foreach ($rawList as $num) {
            if ($lastNum > 0 && $num - $lastNum > 1) {
                $structuredList[] = [
                    'text'  => '...',
                    'url'   => 'javascript:void(0);', 
                    'class' => '', 
                ];
            }

            $isActive = ($num == $this->current_page);
            
            $structuredList[] = [
                'text'  => str_pad($num, 2, '0', STR_PAD_LEFT),
                'url'   => $this->url($num), 
                'class' => $isActive ? 'active ' : '',
            ];

            $lastNum = $num;
        }

        return $structuredList;
    }

    public function render() {
        if ($this->total <= 1) return '';
        
        $html = '<ul class="pagination">';
        
        // Prev
        if ($prevUrl = $this->prev()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($prevUrl) . '">上一頁</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">上一頁</span></li>';
        }
        
        // Items
        foreach ($this->items() as $item) {
            if ($item['text'] === '...') {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            } else {
                $activeClass = trim($item['class']) === 'active' ? ' active' : '';
                $html .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['text']) . '</a></li>';
            }
        }
        
        // Next
        if ($nextUrl = $this->next()) {
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($nextUrl) . '">下一頁</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">下一頁</span></li>';
        }
        
        $html .= '</ul>';
        return $html;
    }
}
/*
<?php if ($prevUrl = $pages->prev()): ?>
    <a href="<?= $prevUrl ?>">上一頁</a>
<?php else: ?>
    <span class="disabled">上一頁</span>
<?php endif; ?>

<?php foreach ($pages->items() as $item): ?>
    <a href="<?= $item['url'] ?>" class="<?= $item['class'] ?>">
        <?= $item['text'] ?>
    </a>
<?php endforeach; ?>

<?php if ($nextUrl = $pages->next()): ?>
    <a href="<?= $nextUrl ?>">下一頁</a>
<?php else: ?>
    <span class="disabled">下一頁</span>
<?php endif; ?>
*/