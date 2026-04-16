<div class="news_detail01">
    <div class="max-w-[1400px] mx-auto mt-40">
        <h1><?=$work['d_title']?></h1>

        <?php if (!empty($coverImages)): ?>
            <div class="pic">
                <img src="<?=$coverImages['file_link1']?>" alt="<?=$coverImages['file_title'] ?? $work['d_title']?>">
            </div>
        <?php endif; ?>

        <div class="content">
            <?=$work['d_content']?>
        </div>
    </div>

    <?php if (!empty($dynamicRooms) && is_array($dynamicRooms)): ?>
        <div class="rooms-section">
            <div class="max-w-[1400px] mx-auto">
                <h2>房型資訊</h2>
                <div class="room-list">
                    <?php foreach ($dynamicRooms as $groupIndex => $roomGroup): ?>
                        <?php if (is_array($roomGroup)): ?>
                            <div class="room-group" data-group-index="<?=$groupIndex?>">
                                <?php
                                // 取得房型名稱
                                $roomEn = $roomGroup['room_en'] ?? '';
                                $roomCh = $roomGroup['room_ch'] ?? '';
                                $roomContent = $roomGroup['room_content'] ?? '';

                                // 取得房型圖片（如果是圖片欄位，會是陣列格式）
                                $roomImage = null;
                                if (isset($roomGroup['room_image'])) {
                                    if (is_array($roomGroup['room_image']) && isset($roomGroup['room_image']['file_info'])) {
                                        $roomImage = $roomGroup['room_image']['file_info'];
                                    }
                                }
                                ?>

                                <div class="room-info">
                                    <?php if (!empty($roomEn)): ?>
                                        <p><strong>英文：</strong><?=$roomEn?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($roomCh)): ?>
                                        <p><strong>中文：</strong><?=$roomCh?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($roomContent)): ?>
                                        <p><strong>說明：</strong><?=$roomContent?></p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($roomImage && !empty($roomImage['file_link1'])): ?>
                                    <div class="room-image">
                                        <img src="<?=$roomImage['file_link1']?>" alt="<?=$roomImage['file_title'] ?? $roomName?>">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>