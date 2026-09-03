<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleSessionStatus;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ScheduleExportImageService
{
    private const WIDTH = 1080;

    /**
     * @return array{imageUrl: string, width: int, height: int, placeholder: bool, from: string, to: string}
     */
    public function export(Staff $staff, Site $site, array $payload): array
    {
        abort_unless(extension_loaded('gd'), 503, 'SCHEDULE_EXPORT_GD_REQUIRED');

        $from = Carbon::parse($payload['from'])->startOfDay();
        $to = Carbon::parse($payload['to'])->endOfDay();
        abort_if($from->diffInDays($to) > 31, 422, 'SCHEDULE_EXPORT_RANGE_TOO_LARGE');

        $sessions = ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereBetween('starts_at', [$from, $to])
            ->where('status', '!=', ScheduleSessionStatus::Cancelled)
            ->with(['course:id,name', 'room:id,name', 'coach:id,name'])
            ->orderBy('starts_at')
            ->limit(200)
            ->get()
            ->groupBy(fn (ScheduleSession $session) => $session->starts_at->toDateString());

        $dates = collect();
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();
            $dates->push(['date' => $date->copy(), 'sessions' => $sessions->get($key, collect())]);
        }

        $height = 210 + $dates->sum(fn (array $day) => 72 + max(1, $day['sessions']->count()) * 126) + 90;
        $image = imagecreatetruecolor(self::WIDTH, $height);
        abort_unless($image !== false, 503, 'SCHEDULE_EXPORT_IMAGE_CREATE_FAILED');

        imageantialias($image, true);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 35, 38, 47);
        $muted = imagecolorallocate($image, 117, 123, 135);
        $line = imagecolorallocate($image, 231, 234, 239);
        $yellow = imagecolorallocate($image, 251, 209, 40);
        $soft = imagecolorallocate($image, 248, 249, 251);
        imagefill($image, 0, 0, $white);

        $font = $this->fontPath();
        imagefilledrectangle($image, 0, 0, self::WIDTH, 168, $yellow);
        $this->text($image, 42, 52, 82, $ink, $site->name, $font);
        $this->text($image, 23, 54, 132, $ink, $from->format('Y.m.d').' - '.$to->format('Y.m.d'), $font);
        $this->text($image, 20, 780, 132, $ink, '觅境约课 · 课程表', $font);

        $y = 198;
        foreach ($dates as $day) {
            /** @var Carbon $date */
            $date = $day['date'];
            imagefilledrectangle($image, 38, $y, self::WIDTH - 38, $y + 54, $soft);
            $this->text($image, 24, 58, $y + 38, $ink, $date->format('m月d日').'  '.$this->weekday($date), $font);
            $y += 72;

            if ($day['sessions']->isEmpty()) {
                $this->text($image, 22, 64, $y + 48, $muted, '暂无排课', $font);
                $y += 126;
                continue;
            }

            foreach ($day['sessions'] as $session) {
                /** @var ScheduleSession $session */
                $accent = $this->color($image, $session->display_color ?: '#39b54a');
                imagefilledrectangle($image, 48, $y + 10, 58, $y + 108, $accent);
                $this->text(
                    $image,
                    28,
                    80,
                    $y + 48,
                    $ink,
                    $this->truncate($session->course?->name ?: '未命名课程', 20),
                    $font,
                );
                $time = $session->starts_at->format('H:i').' - '.$session->ends_at->format('H:i');
                $meta = implode('  ·  ', array_filter([
                    $session->coach?->name,
                    $session->room?->name,
                    "预约 {$session->booked_count}/{$session->capacity}",
                ]));
                $this->text($image, 22, 80, $y + 88, $muted, $time.'  '.$this->truncate($meta, 34), $font);
                imageline($image, 80, $y + 116, self::WIDTH - 48, $y + 116, $line);
                $y += 126;
            }
        }

        $this->text($image, 18, 48, $height - 40, $muted, '生成时间 '.now()->format('Y-m-d H:i'), $font);

        $directory = public_path('generated/schedule-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $this->cleanup($directory);
        $filename = 'schedule-'.$site->id.'-'.Str::lower(Str::random(32)).'.png';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $saved = imagepng($image, $path, 8);
        imagedestroy($image);
        abort_unless($saved, 503, 'SCHEDULE_EXPORT_IMAGE_SAVE_FAILED');

        return [
            'imageUrl' => url('/generated/schedule-exports/'.$filename),
            'width' => self::WIDTH,
            'height' => $height,
            'placeholder' => false,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    private function fontPath(): string
    {
        $candidates = [
            'C:\\Windows\\Fonts\\msyh.ttc',
            'C:\\Windows\\Fonts\\simhei.ttf',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        abort(503, 'SCHEDULE_EXPORT_FONT_REQUIRED');
    }

    private function text($image, int $size, int $x, int $y, int $color, string $text, string $font): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    private function color($image, string $hex): int
    {
        $hex = preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? substr($hex, 1) : '39b54a';

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    private function weekday(Carbon $date): string
    {
        return ['周日', '周一', '周二', '周三', '周四', '周五', '周六'][$date->dayOfWeek];
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1).'…' : $value;
    }

    private function cleanup(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'schedule-*.png') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < now()->subDay()->timestamp) {
                @unlink($file);
            }
        }
    }
}
