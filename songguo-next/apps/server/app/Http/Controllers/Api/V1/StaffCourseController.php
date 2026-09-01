<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Staff;
use App\Services\Catalog\CourseCatalogExtrasService;
use App\Services\Catalog\CourseCatalogWriteService;
use App\Services\Catalog\StaffCourseCatalogAccessService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class StaffCourseController extends Controller
{
    public function index(Request $request, int $site, StaffCourseCatalogAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.read', $siteModel->id);

        // 私教统一模式的内部课目不出现在课程库
        $query = $access->catalogQuery($staff, $siteModel)->where('hidden_in_catalog', false);
        if ($request->filled('courseType')) {
            $query->where('course_type', $request->string('courseType')->toString());
        }
        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        $paginator = $query
            ->with(['defaultRoom', 'coach'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (Course $course) => $this->catalogItem($course)),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $site, int $course, StaffCourseCatalogAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.read', $siteModel->id);
        $courseModel = $access->course($staff, $siteModel, $course)->load(['defaultRoom', 'coach']);

        return ApiResponse::success($this->detailData($courseModel));
    }

    public function store(
        StoreCourseRequest $request,
        int $site,
        StaffCourseCatalogAccessService $access,
        CourseCatalogWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $course = $writer->create($staff, $siteModel, $request->validated());

        return ApiResponse::success($this->detailData($course), 201);
    }

    public function update(
        UpdateCourseRequest $request,
        int $site,
        int $course,
        StaffCourseCatalogAccessService $access,
        CourseCatalogWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $courseModel = $access->course($staff, $siteModel, $course);
        $courseModel = $writer->updateForActor(DomainActor::staff($staff), $siteModel, $courseModel, $request->validated());

        return ApiResponse::success($this->detailData($courseModel));
    }

    public function archive(
        Request $request,
        int $site,
        int $course,
        StaffCourseCatalogAccessService $access,
        CourseCatalogWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $courseModel = $access->course($staff, $siteModel, $course);
        $command = $request->validate([
            'version' => ['sometimes', 'integer', 'min:1'],
            'commandKey' => ['sometimes', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $courseModel = $writer->archiveForActor(
            DomainActor::staff($staff), $siteModel, $courseModel,
            (int) ($command['version'] ?? $courseModel->version),
            $command['commandKey'] ?? null, $command['reason'] ?? null,
        );

        return ApiResponse::success($this->detailData($courseModel));
    }

    public function restore(
        Request $request,
        int $site,
        int $course,
        StaffCourseCatalogAccessService $access,
        CourseCatalogWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $courseModel = $access->course($staff, $siteModel, $course);
        $command = $request->validate([
            'version' => ['sometimes', 'integer', 'min:1'],
            'commandKey' => ['sometimes', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $courseModel = $writer->restoreForActor(
            DomainActor::staff($staff), $siteModel, $courseModel,
            (int) ($command['version'] ?? $courseModel->version),
            $command['commandKey'] ?? null, $command['reason'] ?? null,
        );

        return ApiResponse::success($this->detailData($courseModel));
    }

    public function destroy(
        Request $request,
        int $site,
        int $course,
        StaffCourseCatalogAccessService $access,
        CourseCatalogWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $courseModel = $access->course($staff, $siteModel, $course);
        $writer->assertPhysicalDeleteForbidden($courseModel);
    }

    public function tags(
        Request $request,
        int $site,
        StaffCourseCatalogAccessService $access,
        CourseCatalogExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.read', $siteModel->id);

        return ApiResponse::success($extras->listTags($staff, $siteModel));
    }

    public function updateTags(
        Request $request,
        int $site,
        StaffCourseCatalogAccessService $access,
        CourseCatalogExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);

        $payload = $request->validate([
            'tags' => ['required', 'array'],
            'tags.*.key' => ['required', 'string', 'max:40'],
            'tags.*.label' => ['required', 'string', 'max:40'],
            'tags.*.color' => ['nullable', 'string', 'max:20'],
        ]);

        return ApiResponse::success($extras->saveTags($staff, $siteModel, $payload));
    }

    public function deletePreflight(
        Request $request,
        int $site,
        int $course,
        StaffCourseCatalogAccessService $access,
        CourseCatalogExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.read', $siteModel->id);
        $courseModel = $access->course($staff, $siteModel, $course);

        return ApiResponse::success($extras->deletePreflight($staff, $siteModel, $courseModel));
    }

    private function catalogItem(Course $course): array
    {
        return [
            'id' => $course->id,
            'courseType' => $course->course_type->value,
            'name' => $course->name,
            'durationMinutes' => $course->duration_minutes,
            'difficulty' => $course->difficulty,
            'minCapacity' => $course->min_capacity,
            'maxCapacity' => $course->max_capacity,
            'defaultRoomId' => $course->default_room_id,
            'defaultRoomName' => $course->defaultRoom?->name,
            'coachStaffId' => $course->coach_staff_id,
            'coachName' => $course->coach?->name,
            'catalogStatus' => $course->catalog_status->value,
            'sortOrder' => $course->sort_order,
            'version' => $course->version,
            'faceStyle' => $course->face_style,
            'displayColor' => $course->display_color,
            'faceGradient' => app(\App\Services\Cards\CardFaceLibraryService::class)->gradientFor($course->face_style),
            'tags' => $course->tags ?? [],
        ];
    }

    private function detailData(Course $course): array
    {
        return [
            ...$this->catalogItem($course),
            'description' => $course->description,
            'tags' => $course->tags ?? [],
            'archivedAt' => $course->archived_at?->toIso8601String(),
            'createdAt' => $course->created_at?->toIso8601String(),
            'updatedAt' => $course->updated_at?->toIso8601String(),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
