<?php

function displayPages($pageNum, $queryString, $totalPages, $totalRows, $currentPage) {

    echo "<table border='0'><tr><td>";

    if ($pageNum > 0) {
        // Show if not first page

        echo "<a href=";
        printf("%s?pageNum=%d%s", $currentPage, max(0, $pageNum - 1), $queryString);
        echo "><</a>";
    } // Show if not first page

    echo "</td><td>";

    //echo $totalRows;//所有筆數
    //echo $totalPages;//最後分頁的頁數,由0開始的陣列
    //echo $pageNum;//現在顯示的頁面,由0開始的陣列
    //echo $currentPage;//現在的目錄路徑
    //echo $queryString;//以字串顯示所有的筆數,如&totalRows=11
    if ($totalPages < 10) {
        if ($totalRows == 0) {
            //如果沒有任何資料，不顯示|符號
        } else {
            echo " | ";
        }

        for ($i = 1; $i <= $totalPages + 1; $i++) {
            //如果非正在顯示的分頁則建立頁碼連結
            if ($i != intval($pageNum) + 1) {

                echo "<a href=";
                printf("%s?pageNum=%d%s", $currentPage, $i - 1, $queryString);
                echo ">" . $i . "</a> | ";
            }
            //如果是正在顯示的方頁則單純顯示頁碼
            else {
                echo "<font style=\"text-decoration: underline;\">" . $i . "</font>" . " | ";
            }
        }
    } else //$totalPages>10
    {
        $morePage_num = $totalPages - $pageNum; //此頁面之後總共有多少
        if ($morePage_num > 7) {
            if ($pageNum < 3) {
                if ($totalRows == 0) {
                    //如果沒有任何資料，不顯示|符號
                } else {
                    echo " | ";
                }

                for ($i = 1; $i <= 10; $i++) {
                    //如果非正在顯示的分頁則建立頁碼連結
                    if ($i != $pageNum + 1) {

                        echo "<a href=";
                        printf("%s?pageNum=%d%s", $currentPage, $i - 1, $queryString);
                        echo ">" . $i . "</a> | ";
                    }
                    //如果是正在顯示的方頁則單純顯示頁碼
                    else {
                        echo "<font style=\"text-decoration: underline;\">" . $i . "</font>" . " | ";
                    }
                }

                echo "<a href=";
                printf("%s?pageNum=%d%s", $currentPage, $totalPages, $queryString);
                echo ">..." . ($totalPages + 1) . "</a> | ";
            } else //$pageNum>=3
            {
                echo "<a href=";
                printf("%s?pageNum=%d%s", $currentPage, 0, $queryString);
                echo ">" . 1 . "...</a> | ";

                for ($i = $pageNum - 1; $i <= $pageNum + 8; $i++) {
                    //如果非正在顯示的分頁則建立頁碼連結
                    if ($i != $pageNum + 1) {

                        echo "<a href=";
                        printf("%s?pageNum=%d%s", $currentPage, $i - 1, $queryString);
                        echo ">" . $i . "</a> | ";
                    }
                    //如果是正在顯示的方頁則單純顯示頁碼
                    else {
                        echo "<font style=\"text-decoration: underline;\">" . $i . "</font>" . " | ";
                    }
                }

                echo "<a href=";
                printf("%s?pageNum=%d%s", $currentPage, $totalPages, $queryString);
                echo ">..." . ($totalPages + 1) . "</a> | ";
            }
        } else //$morePage_num<=7
        {
            $beforePage_num = 9 - $morePage_num;
            $beforePage = $pageNum - $beforePage_num;

            //echo "<br>beforePage_num=".$beforePage_num."<br>";
            //echo "beforePage=".$beforePage."<br>";

            echo " | <a href=";
            printf("%s?pageNum=%d%s", $currentPage, 0, $queryString);
            echo ">" . 1 . "...</a> | ";

            for ($i = $beforePage + 1; $i <= $totalPages + 1; $i++) {
                //如果非正在顯示的分頁則建立頁碼連結
                if ($i != $pageNum + 1) {

                    echo "<a href=";
                    printf("%s?pageNum=%d%s", $currentPage, $i - 1, $queryString);
                    echo ">" . $i . "</a> | ";
                }
                //如果是正在顯示的方頁則單純顯示頁碼
                else {
                    echo "<font style=\"text-decoration: underline;\">" . $i . "</font>" . " | ";
                }
            }
        }

    }

    echo "</td><td>";

    if ($pageNum < $totalPages) {
        // Show if not last page
        echo "<a href=";
        printf("%s?pageNum=%d%s", $currentPage, min($totalPages, (int)$pageNum + 1), $queryString);
        echo ">></a>";
    } // Show if not last page
    echo "</td></tr></table>";
}
?>