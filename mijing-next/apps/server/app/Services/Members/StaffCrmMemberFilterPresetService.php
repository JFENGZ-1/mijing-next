<?php

namespace App\Services\Members;

class StaffCrmMemberFilterPresetService
{
    /**
     * Preset metadata for legacy screen.js / member tab filter chips.
     *
     * @return array<string, mixed>
     */
    public function presets(): array
    {
        return [
            'sumModePresets' => [
                ['id' => 'all', 'label' => '全部会员', 'query' => ['sumMode' => 'all']],
                ['id' => 'monthNew', 'label' => '本月新增', 'query' => ['sumMode' => 'monthNew']],
                ['id' => 'valid', 'label' => '有效会员', 'query' => ['sumMode' => 'valid']],
                ['id' => 'invalid', 'label' => '无效会员', 'query' => ['sumMode' => 'invalid']],
                ['id' => 'noCard', 'label' => '无卡/访客', 'query' => ['sumMode' => 'noCard']],
                ['id' => 'blocked', 'label' => '屏蔽会员', 'query' => ['sumMode' => 'blocked']],
            ],
            'flagPresets' => [
                ['flag' => 0, 'label' => '全部会员', 'query' => ['sumMode' => 'all']],
                ['flag' => 1, 'label' => '有效会员', 'query' => ['sumMode' => 'valid']],
                ['flag' => 2, 'label' => '无效会员', 'query' => ['sumMode' => 'invalid']],
                ['flag' => 3, 'label' => '上月上课', 'query' => ['flag' => 3], 'listSupported' => false],
                ['flag' => 4, 'label' => '本月上课', 'query' => ['flag' => 4], 'listSupported' => false],
                ['flag' => 5, 'label' => '30天未上课', 'query' => ['flag' => 5], 'listSupported' => false],
                ['flag' => 6, 'label' => '60天未上课', 'query' => ['flag' => 6], 'listSupported' => false],
                ['flag' => 7, 'label' => '90天未上课', 'query' => ['flag' => 7], 'listSupported' => false],
                ['flag' => 8, 'label' => '120天未上课', 'query' => ['flag' => 8], 'listSupported' => false],
                ['flag' => 9, 'label' => '无卡/访客', 'query' => ['sumMode' => 'noCard']],
                ['flag' => 10, 'label' => '屏蔽会员', 'query' => ['sumMode' => 'blocked']],
                ['flag' => 11, 'label' => '本月新增', 'query' => ['sumMode' => 'monthNew']],
            ],
            'runOffPresets' => [
                [
                    'runOff' => 1,
                    'label' => '余额为0或卡过期后，超过三个月未续费且未购新卡',
                    'query' => ['runOff' => 1],
                ],
            ],
        ];
    }
}
