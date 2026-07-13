<?php

namespace App\Services\Catalog;

use App\Enums\ScheduleSessionStatus;
use App\Models\BookingPolicy;
use App\Models\Course;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Support\Facades\DB;

class CourseCatalogExtrasService
{
    /**
     * @return array{tags: list<array{key: string, label: string, color: string}>}
     */
    public function listTags(Staff $staff, Site $site): array
    {
        $stored = BookingPolicy::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->first();

        $tags = $stored?->rules['courseTags'] ?? [];

        return ['tags' => $this->normalizeTags(is_array($tags) ? $tags : [])];
    }

    /**
     * @param  array{tags: list<array{key: string, label: string, color?: string}>}  $payload
     * @return array{tags: list<array{key: string, label: string, color: string}>}
     */
    public function saveTags(Staff $staff, Site $site, array $payload): array
    {
        $normalized = $this->normalizeTags($payload['tags'] ?? []);

        DB::transaction(function () use ($staff, $site, $normalized) {
            $policy = BookingPolicy::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->lockForUpdate()
                ->first();

            if (! $policy) {
                BookingPolicy::create([
                    'tenant_id' => $staff->tenant_id,
                    'site_id' => $site->id,
                    'version' => 1,
                    'policy' => BookingPolicyService::defaultPolicy(),
                    'rules' => ['courseTags' => $normalized],
                ]);

                return;
            }

            $rules = $policy->rules ?? [];
            $rules['courseTags'] = $normalized;
            $policy->update([
                'rules' => $rules,
                'version' => $policy->version + 1,
            ]);
        });

        return ['tags' => $normalized];
    }

    public function deletePreflight(Staff $staff, Site $site, Course $course): array
    {
        $futureSessions = ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('course_id', $course->id)
            ->where('starts_at', '>=', now())
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->count();

        return [
            'courseId' => $course->id,
            'futureSessionCount' => $futureSessions,
            'canDelete' => $futureSessions === 0,
        ];
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<array{key: string, label: string, color: string}>
     */
    private function normalizeTags(array $raw): array
    {
        return collect($raw)
            ->map(function ($tag) {
                if (! is_array($tag)) {
                    return null;
                }
                $key = trim((string) ($tag['key'] ?? ''));
                $label = trim((string) ($tag['label'] ?? ''));
                if ($key === '' || $label === '') {
                    return null;
                }

                return [
                    'key' => $key,
                    'label' => $label,
                    'color' => trim((string) ($tag['color'] ?? '#1677ff')) ?: '#1677ff',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
