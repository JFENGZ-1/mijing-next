<?php

namespace App\Services\Schedule;

use App\Models\BookingPolicy;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Support\Facades\DB;

class ScheduleDisplayConfigService
{
  public function __construct(
    private readonly BookingPolicyService $bookingPolicies,
  ) {}

  /**
   * @return array{
   *     displayTitle: string,
   *     copyHint: string,
   *     displayTags: list<array{key: string, label: string, color: string}>
   * }
   */
  public function forSite(Staff $staff, Site $site): array
  {
    $stored = BookingPolicy::query()
      ->where('tenant_id', $staff->tenant_id)
      ->where('site_id', $site->id)
      ->first();

    return $this->normalize($stored?->rules['scheduleDisplay'] ?? []);
  }

  /**
   * @param  array<string, mixed>  $payload
   * @return array{
   *     displayTitle: string,
   *     copyHint: string,
   *     displayTags: list<array{key: string, label: string, color: string}>
   * }
   */
  public function save(Staff $staff, Site $site, array $payload): array
  {
    $config = $this->normalize($payload);

    DB::transaction(function () use ($staff, $site, $config) {
      $policy = BookingPolicy::query()
        ->where('tenant_id', $staff->tenant_id)
        ->where('site_id', $site->id)
        ->lockForUpdate()
        ->first();

      if (! $policy) {
        $defaults = BookingPolicyService::defaultPolicy();
        $policy = BookingPolicy::create([
          'tenant_id' => $staff->tenant_id,
          'site_id' => $site->id,
          'version' => 1,
          'policy' => $defaults,
          'rules' => ['scheduleDisplay' => $config],
        ]);

        return;
      }

      $rules = $policy->rules ?? [];
      $rules['scheduleDisplay'] = $config;
      $policy->update([
        'rules' => $rules,
        'version' => $policy->version + 1,
      ]);
    });

    return $this->forSite($staff, $site->fresh());
  }

  /**
   * @param  array<string, mixed>  $raw
   * @return array{
   *     displayTitle: string,
   *     copyHint: string,
   *     displayTags: list<array{key: string, label: string, color: string}>
   * }
   */
  private function normalize(array $raw): array
  {
    $tags = collect($raw['displayTags'] ?? [])
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

    return [
      'displayTitle' => trim((string) ($raw['displayTitle'] ?? '')),
      'copyHint' => trim((string) ($raw['copyHint'] ?? '')),
      'displayTags' => $tags,
    ];
  }
}
