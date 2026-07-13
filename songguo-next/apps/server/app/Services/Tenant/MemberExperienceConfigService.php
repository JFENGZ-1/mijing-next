<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\SiteCarouselItem;
use Illuminate\Support\Facades\DB;

class MemberExperienceConfigService
{
    private const WARM_HINT_COURSE_TYPES = [
        6 => '私教',
        7 => '团课',
    ];

    private const MAX_CAROUSEL_ITEMS = 5;

    /**
     * @var list<array{key: string, label: string, group: string, defaultEnabled: bool}>
     */
    private const MINIAPP_LAYOUT_DEFINITIONS = [
        ['key' => 'showBuyCardBtn', 'label' => '首页购卡按钮', 'group' => 'home', 'defaultEnabled' => true],
        ['key' => 'showBuyCardPrice', 'label' => '首页卡价展示', 'group' => 'home', 'defaultEnabled' => true],
        ['key' => 'showPrivateDrainer', 'label' => '私教入口', 'group' => 'course', 'defaultEnabled' => true],
        ['key' => 'privateShow', 'label' => '私教列表', 'group' => 'course', 'defaultEnabled' => true],
        ['key' => 'showPhoneOfDrainer', 'label' => '私教手机号', 'group' => 'course', 'defaultEnabled' => true],
        ['key' => 'teamShow', 'label' => '团课列表', 'group' => 'course', 'defaultEnabled' => true],
        ['key' => 'showTimeoutTeamPlan', 'label' => '过期团课', 'group' => 'course', 'defaultEnabled' => true],
        ['key' => 'showMonthRank', 'label' => '月度排行', 'group' => 'mine', 'defaultEnabled' => false],
        ['key' => 'refuseUserNoLogin', 'label' => '未登录拦截', 'group' => 'refuse', 'defaultEnabled' => false],
        ['key' => 'refuseUserZeroBalance', 'label' => '零余额拦截', 'group' => 'refuse', 'defaultEnabled' => false],
        ['key' => 'refuseUserCardExpired', 'label' => '卡过期拦截', 'group' => 'refuse', 'defaultEnabled' => false],
        ['key' => 'refuseUserFocus', 'label' => '未关注公众号拦截', 'group' => 'other', 'defaultEnabled' => false],
    ];

    /**
     * @return array{hints: list<array<string, mixed>>}
     */
    public function warmHints(Site $site): array
    {
        $stored = $site->member_warm_hints ?? [];

        return [
            'hints' => collect(self::WARM_HINT_COURSE_TYPES)
                ->map(function (string $label, int $courseType) use ($stored) {
                    $hint = is_array($stored[(string) $courseType] ?? null)
                        ? $stored[(string) $courseType]
                        : (is_array($stored[$courseType] ?? null) ? $stored[$courseType] : []);
                    $text = (string) ($hint['text'] ?? '');
                    $title = (string) ($hint['title'] ?? '');

                    return [
                        'courseType' => $courseType,
                        'courseTypeLabel' => $label,
                        'title' => $title,
                        'text' => $text,
                        'hasContent' => filled(trim(strip_tags($text))),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{courseType: int, title?: ?string, text?: ?string}  $payload
     * @return array{hints: list<array<string, mixed>>}
     */
    public function updateWarmHint(Site $site, array $payload): array
    {
        $courseType = (int) $payload['courseType'];
        abort_unless(array_key_exists($courseType, self::WARM_HINT_COURSE_TYPES), 422, 'VALIDATION_FAILED');

        $stored = $site->member_warm_hints ?? [];
        $text = (string) ($payload['text'] ?? '');
        $plainLength = mb_strlen(trim(strip_tags($text)));
        abort_if($plainLength > 500, 422, 'VALIDATION_FAILED');

        if ($plainLength === 0) {
            unset($stored[(string) $courseType], $stored[$courseType]);
        } else {
            $stored[(string) $courseType] = [
                'title' => (string) ($payload['title'] ?? self::WARM_HINT_COURSE_TYPES[$courseType].'温馨提示'),
                'text' => $text,
            ];
        }

        $site->update(['member_warm_hints' => $stored === [] ? null : $stored]);

        return $this->warmHints($site->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function carousel(Site $site): array
    {
        $items = SiteCarouselItem::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteCarouselItem $item) => [
                'id' => $item->id,
                'imageUrl' => $item->image_url,
                'linkUrl' => $item->link_url,
                'sortOrder' => $item->sort_order,
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'defaultImageUrl' => $site->carousel_default_image_url,
            'usesDefaultImage' => $items === [],
        ];
    }

    /**
     * @param  array{items: list<array{imageUrl: string, linkUrl?: ?string, sortOrder?: int}>, defaultImageUrl?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function updateCarousel(Site $site, array $payload): array
    {
        $items = $payload['items'] ?? [];
        abort_if(count($items) > self::MAX_CAROUSEL_ITEMS, 422, 'VALIDATION_FAILED');

        DB::transaction(function () use ($site, $items, $payload) {
            SiteCarouselItem::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->delete();

            foreach ($items as $index => $item) {
                SiteCarouselItem::create([
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'image_url' => $item['imageUrl'],
                    'link_url' => $item['linkUrl'] ?? null,
                    'status' => 'published',
                    'sort_order' => $item['sortOrder'] ?? $index,
                ]);
            }

            $site->update([
                'carousel_default_image_url' => $payload['defaultImageUrl'] ?? $site->carousel_default_image_url,
            ]);
        });

        return $this->carousel($site->fresh());
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function miniappLayout(Site $site): array
    {
        $stored = $site->member_miniapp_layout ?? [];

        return [
            'items' => collect(self::MINIAPP_LAYOUT_DEFINITIONS)
                ->map(function (array $definition) use ($stored, $site) {
                    $enabled = array_key_exists($definition['key'], $stored)
                        ? (bool) $stored[$definition['key']]
                        : ($definition['key'] === 'showMonthRank'
                            ? (bool) $site->tenant?->show_month_rank
                            : $definition['defaultEnabled']);

                    return [
                        'key' => $definition['key'],
                        'label' => $definition['label'],
                        'group' => $definition['group'],
                        'enabled' => $enabled,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{items: list<array{key: string, enabled: bool}>}  $payload
     * @return array{items: list<array<string, mixed>>}
     */
    public function updateMiniappLayout(Site $site, array $payload): array
    {
        $allowedKeys = collect(self::MINIAPP_LAYOUT_DEFINITIONS)->pluck('key')->all();
        $layout = $site->member_miniapp_layout ?? [];

        foreach ($payload['items'] as $item) {
            abort_unless(in_array($item['key'], $allowedKeys, true), 422, 'VALIDATION_FAILED');
            $layout[$item['key']] = (bool) $item['enabled'];
        }

        $site->update(['member_miniapp_layout' => $layout]);

        if (array_key_exists('showMonthRank', $layout) && $site->tenant) {
            $site->tenant->update(['show_month_rank' => $layout['showMonthRank']]);
        }

        return $this->miniappLayout($site->fresh()->loadMissing('tenant'));
    }

    /**
     * @return array{posterUrl: ?string, stepUrl: ?string}
     */
    public function onboardingHelp(Site $site): array
    {
        $stored = $site->member_onboarding_help ?? [];

        return [
            'posterUrl' => $stored['posterUrl'] ?? null,
            'stepUrl' => $stored['stepUrl'] ?? null,
        ];
    }

    /**
     * @param  array{posterUrl?: ?string, stepUrl?: ?string}  $payload
     * @return array{posterUrl: ?string, stepUrl: ?string}
     */
    public function updateOnboardingHelp(Site $site, array $payload): array
    {
        $site->update([
            'member_onboarding_help' => [
                'posterUrl' => $payload['posterUrl'] ?? null,
                'stepUrl' => $payload['stepUrl'] ?? null,
            ],
        ]);

        return $this->onboardingHelp($site->fresh());
    }

    public function layoutEnabled(Site $site, string $key): bool
    {
        $stored = $site->member_miniapp_layout ?? [];
        if (array_key_exists($key, $stored)) {
            return (bool) $stored[$key];
        }

        $definition = collect(self::MINIAPP_LAYOUT_DEFINITIONS)->firstWhere('key', $key);
        if (! $definition) {
            return true;
        }

        if ($key === 'showMonthRank') {
            return (bool) $site->tenant?->show_month_rank;
        }

        return $definition['defaultEnabled'];
    }
}
