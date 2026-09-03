<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;

class TenantSupportService
{
    /**
     * @return array{
     *     phone: string,
     *     wechatId: string,
     *     hours: string,
     *     faqLinks: list<array{title: string, url: string}>,
     *     supportHint: string
     * }
     */
    public function contact(Staff $staff, Site $site): array
    {
        $config = $this->config($staff);
        $contact = is_array($config['contact'] ?? null) ? $config['contact'] : [];

        return [
            'phone' => (string) ($contact['phone'] ?? '400-000-0000'),
            'wechatId' => (string) ($contact['wechatId'] ?? 'mijing-support'),
            'hours' => (string) ($contact['hours'] ?? '工作日 9:00-18:00'),
            'faqLinks' => $this->faqLinks($contact),
            'supportHint' => (string) ($contact['supportHint'] ?? '如需开通微信支付、短信或连锁功能，请联系觅境客服。'),
            'siteName' => $site->name,
        ];
    }

    /**
     * @return array{videos: list<array{title: string, url: string, durationLabel: string, isPlaceholder: bool}>}
     */
    public function videoHelp(Staff $staff): array
    {
        $config = $this->config($staff);
        $videos = is_array($config['videoHelp'] ?? null) ? $config['videoHelp'] : null;

        if ($videos === null || $videos === []) {
            return ['videos' => $this->defaultVideos()];
        }

        return [
            'videos' => collect($videos)
                ->map(fn (array $video) => [
                    'title' => (string) ($video['title'] ?? '帮助视频'),
                    'url' => (string) ($video['url'] ?? ''),
                    'durationLabel' => (string) ($video['durationLabel'] ?? ''),
                    'isPlaceholder' => (bool) ($video['isPlaceholder'] ?? ! filled($video['url'] ?? null)),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(Staff $staff): array
    {
        $tenant = Tenant::query()->whereKey($staff->tenant_id)->firstOrFail();
        $stored = $tenant->staff_support_config;

        return is_array($stored) ? $stored : [];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return list<array{title: string, url: string}>
     */
    private function faqLinks(array $contact): array
    {
        $links = $contact['faqLinks'] ?? null;
        if (is_array($links) && $links !== []) {
            return collect($links)
                ->map(fn (array $link) => [
                    'title' => (string) ($link['title'] ?? '常见问题'),
                    'url' => (string) ($link['url'] ?? ''),
                ])
                ->filter(fn (array $link) => filled($link['url']))
                ->values()
                ->all();
        }

        return [
            ['title' => '如何约课与签到', 'url' => 'https://help.mijing.example/booking'],
            ['title' => '会员卡与退款说明', 'url' => 'https://help.mijing.example/cards'],
            ['title' => '连锁分店设置指南', 'url' => 'https://help.mijing.example/chain'],
        ];
    }

    /**
     * @return list<array{title: string, url: string, durationLabel: string, isPlaceholder: bool}>
     */
    private function defaultVideos(): array
    {
        return [
            [
                'title' => '员工端快速入门（演示占位）',
                'url' => 'https://help.mijing.example/videos/staff-intro',
                'durationLabel' => '3分钟',
                'isPlaceholder' => true,
            ],
            [
                'title' => '排课与签到操作（演示占位）',
                'url' => 'https://help.mijing.example/videos/scheduling-checkin',
                'durationLabel' => '5分钟',
                'isPlaceholder' => true,
            ],
            [
                'title' => '连锁通用卡配置（演示占位）',
                'url' => 'https://help.mijing.example/videos/chain-cards',
                'durationLabel' => '4分钟',
                'isPlaceholder' => true,
            ],
        ];
    }
}
