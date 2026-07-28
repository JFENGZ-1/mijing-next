<?php

/**
 * 会员卡卡面图案库（平台级，faceStyle 索引 0-9）。
 *
 * 员工端「更换图案」、卡种列表、会员端卡包/购卡目录统一从此下发。
 * 后续接入平台 Web 后台时，将此配置迁移为数据库表并在
 * CardFaceLibraryService 中改读库即可，前端无需改动。
 */
return [
    'items' => [
        ['id' => 0, 'name' => '青瓷', 'gradient' => 'linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)'],
        ['id' => 1, 'name' => '黛蓝', 'gradient' => 'linear-gradient(135deg, #2b5876 0%, #4e4376 100%)'],
        ['id' => 2, 'name' => '松林', 'gradient' => 'linear-gradient(135deg, #1f4037 0%, #2e7d5b 100%)'],
        ['id' => 3, 'name' => '鎏金', 'gradient' => 'linear-gradient(135deg, #8a6034 0%, #b3862f 100%)'],
        ['id' => 4, 'name' => '曜石', 'gradient' => 'linear-gradient(135deg, #33383f 0%, #181818 100%)'],
        ['id' => 5, 'name' => '星紫', 'gradient' => 'linear-gradient(135deg, #614385 0%, #516395 100%)'],
        ['id' => 6, 'name' => '绯棕', 'gradient' => 'linear-gradient(135deg, #a8521f 0%, #c96a32 100%)'],
        ['id' => 7, 'name' => '藏蓝', 'gradient' => 'linear-gradient(135deg, #003d82 0%, #2a5fb6 100%)'],
        ['id' => 8, 'name' => '玫瑰', 'gradient' => 'linear-gradient(135deg, #7a2f4f 0%, #a84a6e 100%)'],
        ['id' => 9, 'name' => '灰蓝', 'gradient' => 'linear-gradient(135deg, #4a4d7a 0%, #696b99 100%)'],
    ],
];
