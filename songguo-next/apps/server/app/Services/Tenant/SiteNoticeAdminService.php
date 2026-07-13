<?php

namespace App\Services\Tenant;

use App\Models\Site;
use App\Models\SiteNotice;
use Carbon\Carbon;

class SiteNoticeAdminService
{
    private const MAX_ACTIVE_NOTICES = 3;

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     summary: array{total: int, active: int}
     * }
     */
    public function list(Site $site): array
    {
        $items = SiteNotice::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SiteNotice $notice) => $this->present($notice))
            ->values()
            ->all();

        return [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'active' => collect($items)->where('displayStatus', 'active')->count(),
            ],
        ];
    }

    /**
     * @param  array{title: string, body: string, displayDays: int, coverImageUrl?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function create(Site $site, array $payload): array
    {
        abort_if($this->activeCount($site) >= self::MAX_ACTIVE_NOTICES, 409, 'ACTIVE_NOTICE_LIMIT');

        $notice = SiteNotice::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'title' => $payload['title'],
            'body' => $payload['body'],
            'cover_image_url' => $payload['coverImageUrl'] ?? null,
            'display_days' => $payload['displayDays'],
            'status' => 'published',
            'published_at' => now(),
            'sort_order' => 0,
        ]);

        return $this->present($notice);
    }

    /**
     * @param  array{title?: string, body?: string, displayDays?: int, coverImageUrl?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function update(SiteNotice $notice, array $payload): array
    {
        abort_if($notice->status === 'archived', 409, 'CONFLICT');

        $notice->fill([
            'title' => $payload['title'] ?? $notice->title,
            'body' => $payload['body'] ?? $notice->body,
            'cover_image_url' => array_key_exists('coverImageUrl', $payload)
                ? $payload['coverImageUrl']
                : $notice->cover_image_url,
            'display_days' => $payload['displayDays'] ?? $notice->display_days,
        ])->save();

        return $this->present($notice->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(SiteNotice $notice): array
    {
        $notice->update(['status' => 'archived']);

        return $this->present($notice->fresh());
    }

    public function findForSite(Site $site, int $noticeId): SiteNotice
    {
        return SiteNotice::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($noticeId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body,
            'coverImageUrl' => $notice->cover_image_url,
            'displayDays' => $notice->display_days,
            'displayStatus' => $this->displayStatus($notice),
            'status' => $notice->status,
            'publishedAt' => $notice->published_at?->toIso8601String(),
            'expiresAt' => $this->expiresAt($notice)?->toIso8601String(),
            'createdAt' => $notice->created_at?->toIso8601String(),
        ];
    }

    private function displayStatus(SiteNotice $notice): string
    {
        if ($notice->status === 'archived') {
            return 'expired';
        }

        if ($notice->status !== 'published' || $notice->published_at === null) {
            return 'draft';
        }

        $expiresAt = $this->expiresAt($notice);
        if ($expiresAt !== null && $expiresAt->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    private function expiresAt(SiteNotice $notice): ?Carbon
    {
        if ($notice->published_at === null || $notice->display_days === null) {
            return null;
        }

        return $notice->published_at->copy()->addDays($notice->display_days);
    }

    private function activeCount(Site $site): int
    {
        return SiteNotice::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->get()
            ->filter(fn (SiteNotice $notice) => $this->displayStatus($notice) === 'active')
            ->count();
    }
}
