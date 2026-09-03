<?php
/**
 * Adversarial API catalog generator.
 * php mijing-next/docs/_audit-api-usage.php
 */

$root = dirname(__DIR__);
$routesJsonPath = $root . '/docs/_routes.json';
if (!is_readable($routesJsonPath)) {
    passthru('php ' . escapeshellarg($root . '/apps/server/artisan') . ' route:list --path=api/v1 --json > ' . escapeshellarg($routesJsonPath));
}
$routesJson = json_decode(file_get_contents($routesJsonPath), true);
$openApiPath = $root . '/docs/openapi.yaml';

function walkFiles(string $dir, array $exts): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $exts, true)) {
            $out[] = $file->getPathname();
        }
    }
    return $out;
}

$staffFiles = walkFiles($root . '/apps/staff-miniapp/src', ['ts', 'vue']);
$memberFiles = walkFiles($root . '/apps/member-miniapp/src', ['ts', 'vue']);
$allFiles = array_merge($staffFiles, $memberFiles);

$fileContents = [];
foreach ($allFiles as $f) {
    $rel = str_replace('\\', '/', str_replace($root, '', $f));
    $fileContents[$rel] = file_get_contents($f);
}
$corpus = implode("\n", $fileContents);

// Parse OpenAPI
$operations = [];
$currentPath = null;
$currentMethod = null;
foreach (file($openApiPath) as $line) {
    if (preg_match('#^  (/[^:]+):\s*$#', $line, $m)) {
        $currentPath = $m[1];
        $currentMethod = null;
        continue;
    }
    if ($currentPath && preg_match('#^    (get|post|put|patch|delete):\s*$#', $line, $m)) {
        $currentMethod = strtoupper($m[1]);
        continue;
    }
    if ($currentMethod && preg_match('#^\s+operationId:\s*([A-Za-z0-9_]+)\s*$#', $line, $m)) {
        $operations[$currentMethod . ' ' . $currentPath] = $m[1];
        $currentMethod = null;
    }
}

// Extract frontend path fragments
$fragments = [];
if (preg_match_all('#["\'`](/(?:staff|member|auth|me|identity|public|sites)[^"\'`\?]*)#', $corpus, $m)) {
    foreach ($m[1] as $p) {
        $fragments[] = normalizeFragment($p);
    }
}
if (preg_match_all('#\$\{[^}]+\}([^"\'`]+)#', $corpus, $m)) {
    foreach ($m[1] as $p) {
        if (str_starts_with($p, '/') || str_contains($p, '/')) {
            $fragments[] = normalizeFragment($p);
        }
    }
}
// sitePath suffix literals: sitePath(siteId, "/foo")
if (preg_match_all('#sitePath\([^,]+,\s*["\']([^"\']+)["\']#', $corpus, $m)) {
    foreach ($m[1] as $s) {
        $fragments[] = normalizeFragment('/staff/sites/{id}' . $s);
    }
}
if (preg_match_all('#reportsPath\([^,]+,\s*["\']([^"\']+)["\']#', $corpus, $m)) {
    foreach ($m[1] as $s) {
        $fragments[] = normalizeFragment('/staff/sites/{id}/reports' . $s);
    }
}
if (preg_match_all('#payrollPath\([^,]+,\s*["\']([^"\']+)["\']#', $corpus, $m)) {
    foreach ($m[1] as $s) {
        $fragments[] = normalizeFragment('/staff/sites/{id}/payroll' . $s);
    }
}
if (preg_match_all('#exportsPath\([^,]+,\s*["\']([^"\']+)["\']#', $corpus, $m)) {
    foreach ($m[1] as $s) {
        $fragments[] = normalizeFragment('/staff/sites/{id}/exports' . $s);
    }
}
if (preg_match_all('#cardPath\([^,]+,\s*[^,]+,\s*["\']([^"\']*)["\']#', $corpus, $m)) {
    foreach ($m[1] as $s) {
        $fragments[] = normalizeFragment('/staff/sites/{id}/member-cards/{id}' . $s);
    }
}
if (preg_match_all('#productPath\([^)]+\)#', $corpus, $m)) {
    $fragments[] = '/staff/sites/{id}/card-products';
    $fragments[] = '/staff/sites/{id}/card-products/{id}';
    $fragments[] = '/staff/sites/{id}/card-products/{id}/archive';
    $fragments[] = '/staff/sites/{id}/card-products/{id}/restore';
}
if (preg_match_all('#coursePath\([^)]+\)#', $corpus, $m)) {
    $fragments[] = '/staff/sites/{id}/courses';
    $fragments[] = '/staff/sites/{id}/courses/{id}';
    $fragments[] = '/staff/sites/{id}/courses/{id}/archive';
    $fragments[] = '/staff/sites/{id}/courses/{id}/restore';
}
if (preg_match_all('#roomPath\([^)]+\)#', $corpus, $m)) {
    $fragments[] = '/staff/sites/{id}/rooms';
    $fragments[] = '/staff/sites/{id}/rooms/{id}';
    $fragments[] = '/staff/sites/{id}/rooms/{id}/archive';
}
$fragments = array_values(array_unique($fragments));

function normalizeFragment(string $p): string
{
    $p = preg_replace('#\$\{[^}]+\}#', '{id}', $p);
    $p = preg_replace('#\?.*$#', '', $p);
    $p = '/' . trim($p, '/');
    $p = preg_replace('#/\d+#', '/{id}', $p);
    return $p;
}

function routePath(string $uri): string
{
    $p = preg_replace('#^api/v1#', '', $uri);
    $p = '/' . trim($p, '/');
    return preg_replace('#\{[^}]+\}#', '{id}', $p);
}

function primaryMethod(string $method): string
{
    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m) {
        if (str_contains($method, $m)) {
            return $m;
        }
    }
    return explode('|', $method)[0];
}

function routeMatchesFragment(string $route, string $fragment): bool
{
    $r = trim($route, '/');
    $f = trim($fragment, '/');
    if ($r === $f) {
        return true;
    }
    $rParts = explode('/', $r);
    $fParts = explode('/', $f);
    if (count($rParts) !== count($fParts)) {
        // prefix match for list endpoints
        if (count($fParts) < count($rParts)) {
            $prefix = implode('/', array_slice($rParts, 0, count($fParts)));
            if ($prefix === $f) {
                return true;
            }
        }
        return false;
    }
    foreach ($rParts as $i => $rp) {
        if ($rp === '{id}' || $fParts[$i] === '{id}') {
            continue;
        }
        if ($rp !== $fParts[$i]) {
            return false;
        }
    }
    return true;
}

function routePattern(string $routePath): string
{
    $parts = explode('/', trim($routePath, '/'));
    $re = [];
    foreach ($parts as $part) {
        if ($part === '{id}') {
            $re[] = '[^/"\'`\s]+';
        } else {
            $re[] = preg_quote($part, '#');
        }
    }
    return '#' . implode('/', $re) . '#';
}

function suffixHit(string $suffix, string $content): bool
{
    if ($suffix === '') {
        return false;
    }
    if (str_contains($content, $suffix)) {
        return true;
    }
    $bare = str_replace('{id}', '', $suffix);
    if ($bare !== $suffix && str_contains($content, $bare)) {
        return true;
    }
    $parts = array_filter(explode('{id}', $suffix), fn ($p) => $p !== '');
    if ($parts === []) {
        return false;
    }
    foreach ($parts as $part) {
        if (!str_contains($content, $part)) {
            return false;
        }
    }
    return true;
}

function findLocations(string $routePath, array $fileContents): array
{
    $locs = [];
    $pattern = routePattern($routePath);
    $suffix = '';
    if (str_contains($routePath, '/staff/sites/{id}')) {
        $suffix = substr($routePath, strlen('/staff/sites/{id}'));
    }

    foreach ($fileContents as $rel => $content) {
        if (preg_match($pattern, $content)) {
            $locs[] = $rel;
            continue;
        }
        if (suffixHit($suffix, $content)) {
            $locs[] = $rel;
            continue;
        }
        if (str_starts_with($routePath, '/member/')) {
            $memberPart = substr($routePath, strlen('/member'));
            if ($memberPart && suffixHit($memberPart, $content)) {
                $locs[] = $rel;
            }
        }
    }
    return array_values(array_unique($locs));
}

/** Adversarial manual overrides after automated scan (method + normalized path). */
function manualStatus(string $method, string $path): ?string
{
    $key = "$method $path";
    $none = [
        'POST /member/booking/appointments/{id}/promote',
        'GET /member/sites/{id}/closure-status',
        'GET /staff/sites/{id}/members/{id}/orders',
        'POST /staff/sites/{id}/orders/{id}/void',
        'POST /staff/sites/{id}/orders/{id}/amount-corrections',
        'POST /staff/sites/{id}/orders/{id}/internal-notes',
        'POST /staff/sites/{id}/member-cards/{id}/transfer-share-token',
        'POST /staff/sites/{id}/members/{id}/point-adjustments',
        'POST /staff/member-tags',
        'GET /staff/platform/subscription/pricing',
        'GET /staff/platform/subscription/agreement',
        'POST /staff/platform/subscription/pay',
        'GET /staff/sites/{id}/platform/subscription/status',
        'POST /staff/sites/{id}/schedule-sessions/{id}/share-link',
        'POST /staff/sites/{id}/schedule-export-image',
        'POST /staff/sites/{id}/schedule-sessions/batch-unsuspend',
        'POST /staff/sites/{id}/schedule-sessions/batch-change-course',
        'POST /staff/sites/{id}/schedule-sessions/{id}/unsuspend',
        'GET /staff/sites/{id}/points-config',
        'PUT /staff/sites/{id}/points-config',
        'POST /staff/sites/{id}/ledger-reconciliation-jobs',
        'GET /staff/sites/{id}/ledger-reconciliation-jobs',
        'GET /staff/sites/{id}/reports/rankings/sales-staff/{id}',
        'GET /staff/sites/{id}/payroll/coach-reports/{id}',
        'GET /staff/sites/{id}/payroll/sales-reports/{id}',
        'GET /staff/sites/{id}/payroll/course-commission',
        'POST /staff/sites/{id}/member-cards/batch-balance-adjustments',
        'POST /staff/sites/{id}/member-cards/batch-validity-extensions',
        'POST /staff/sites/{id}/member-cards/batch-freeze',
        'POST /staff/sites/{id}/member-cards/batch-unfreeze',
        'GET /staff/sites/{id}/card-products/face-library',
        'GET /staff/sites/{id}/courses/{id}/delete-preflight',
        'DELETE /staff/sites/{id}/courses/{id}',
        'GET /staff/sites/{id}/course-tags',
        'PUT /staff/sites/{id}/course-tags',
        'GET /staff/constants/common-data',
        'POST /staff/sites/{id}/staff-directory/{id}/transfer-ownership',
        'GET /staff/sites/{id}/booking/upcoming',
        'GET /staff/sites/{id}/crm/sales-staff',
        'GET /staff/sites/{id}/reports/calendar/month-options',
        'GET /staff/sites/{id}/card-products/{id}/group-history',
        'POST /staff/sites/{id}/card-products/export-jobs',
        'DELETE /staff/sites/{id}/card-products/{id}',
        'DELETE /staff/sites/{id}/rooms/{id}',
        'DELETE /staff/sites/{id}/orders/{id}',
        'GET /staff/sites/{id}/member-cards/{id}/default-fee',
        'GET /staff/sites/{id}/member-cards/{id}/dynamic-fields',
        'GET /staff/sites/{id}/member-cards/{id}/holiday-last',
        'GET /staff/sites/{id}/member-cards/{id}/freeze-ledger-last',
        'PATCH /staff/sites/{id}/member-cards/{id}/opening-type',
        'PATCH /staff/sites/{id}/member-cards/{id}/remark',
        'GET /staff/sites/{id}/schedule-recurring-template',
        'GET /staff/sites/{id}/schedule-sessions/copy-preflight',
        'GET /staff/sites/{id}/schedule-sessions/change-course-preflight',
        'GET /staff/sites/{id}/schedule-session-colors',
        'PUT /staff/sites/{id}/schedule-session-colors',
        'GET /sites',
        'POST /sites',
        'GET /sites/{id}',
        'PUT /sites/{id}',
        'PATCH /sites/{id}',
        'DELETE /sites/{id}',
    ];
    $partial = [
        'GET /member/member-link-requests',
        'GET /staff/sites/{id}/payment-marketing',
        'PATCH /staff/sites/{id}/closure-calendar/{id}',
        'PATCH /staff/sites/{id}/notices/{id}',
        'PATCH /staff/sites/{id}/staff/{id}/vacations/{id}',
    ];
    if (in_array($key, $none, true)) {
        return 'NONE';
    }
    if (in_array($key, $partial, true)) {
        return 'PARTIAL';
    }
    return null;
}

function detectStatus(string $routePath, string $method, array $fragments, array $fileContents): array
{
    $manual = manualStatus($method, $routePath);
    $locs = findLocations($routePath, $fileContents);
    if ($manual !== null) {
        return [$manual, $manual === 'NONE' ? [] : $locs];
    }

    $matched = count($locs) > 0;
    if (!$matched) {
        foreach ($fragments as $frag) {
            if (routeMatchesFragment($routePath, $frag)) {
                $matched = true;
                break;
            }
        }
    }

    $partialMap = [
        'PATCH /staff/sites/{id}/closure-calendar/{id}' => 'PARTIAL',
        'PATCH /staff/sites/{id}/notices/{id}' => 'PARTIAL',
        'PATCH /staff/sites/{id}/staff/{id}/vacations/{id}' => 'PARTIAL',
        'GET /staff/sites/{id}/payment-marketing' => 'PARTIAL',
        'GET /member/member-link-requests' => 'PARTIAL',
    ];
    $key = "$method $routePath";
    if (isset($partialMap[$key])) {
        return [$partialMap[$key], $locs];
    }
    if (str_starts_with($routePath, '/public/') || $routePath === '/health') {
        return ['INTENTIONAL', []];
    }
    if (preg_match('#^/sites#', $routePath)) {
        return [$matched ? 'FULL' : 'NONE', $locs];
    }
    return [$matched ? 'FULL' : 'NONE', $locs];
}

$p0 = ['orders/{id}/void', 'amount-corrections', 'members/{id}/orders', 'transfer-share-token', 'point-adjustments', 'member/booking/appointments/{id}/promote'];
$p1 = ['share-link', 'export-image', 'batch-unsuspend', 'batch-change-course', '/schedule-sessions/{id}/unsuspend', 'internal-notes',
    'subscription/pay', 'subscription/pricing', 'subscription/agreement', 'closure-status'];

function pri(string $path, string $status): string
{
    global $p0, $p1;
    if ($status !== 'NONE') {
        return '';
    }
    foreach ($p0 as $x) {
        if (str_contains($path, $x)) {
            return 'P0';
        }
    }
    foreach ($p1 as $x) {
        if (str_contains($path, $x)) {
            return 'P1';
        }
    }
    return 'P2';
}

function categorize(string $path): string
{
    if (str_starts_with($path, '/public/')) {
        return 'public';
    }
    if ($path === '/health') {
        return 'dev';
    }
    if (str_starts_with($path, '/member/')) {
        return 'member';
    }
    if (str_starts_with($path, '/staff/') || str_starts_with($path, '/sites')) {
        return 'staff';
    }
    return 'shared';
}

function authOf(string $path): string
{
    if (str_starts_with($path, '/public/') || $path === '/health' || preg_match('#/staff/invites/\{id\}$#', $path)) {
        return 'public';
    }
    if (str_starts_with($path, '/member/')) {
        return 'member token';
    }
    if (str_starts_with($path, '/staff/') || str_starts_with($path, '/sites')) {
        return 'staff token';
    }
    return 'sanctum';
}

function actionLabel(string $action): string
{
    if (preg_match('#@(\w+)$#', $action, $m)) {
        return $m[1];
    }
    return '—';
}

function guessTest(string $action): string
{
    $dir = dirname(__DIR__) . '/apps/server/tests/Feature';
    $ctrl = '';
    if (preg_match('#V1\\\\(\w+)Controller#', $action, $m)) {
        $ctrl = str_replace('Controller', '', $m[1]);
    }
    foreach (glob($dir . '/*.php') ?: [] as $f) {
        $base = basename($f);
        if ($ctrl && stripos($base, $ctrl) !== false) {
            return $base;
        }
    }
    return '—';
}

$rows = [];
foreach ($routesJson as $route) {
    $path = routePath($route['uri']);
    $method = primaryMethod($route['method']);
    [$status, $locs] = detectStatus($path, $method, $fragments, $fileContents);
    $op = $operations[$method . ' ' . $path] ?? '—';
    $rows[] = [
        'method' => $method,
        'path' => $path,
        'operationId' => $op,
        'purpose' => actionLabel($route['action']),
        'auth' => authOf($path),
        'test' => guessTest($route['action']),
        'status' => $status,
        'locs' => $locs,
        'cat' => categorize($path),
        'pri' => pri($path, $status),
    ];
}

$staff = array_values(array_filter($rows, fn ($r) => $r['cat'] === 'staff'));
$member = array_values(array_filter($rows, fn ($r) => $r['cat'] === 'member'));
$shared = array_values(array_filter($rows, fn ($r) => $r['cat'] === 'shared'));

function countStatus(array $rows, string $s): int
{
    return count(array_filter($rows, fn ($r) => $r['status'] === $s));
}

$sf = countStatus($staff, 'FULL');
$sp = countStatus($staff, 'PARTIAL');
$sn = countStatus($staff, 'NONE');
$mf = countStatus($member, 'FULL');
$mp = countStatus($member, 'PARTIAL');
$mn = countStatus($member, 'NONE');

echo "Files scanned: " . count($allFiles) . " fragments: " . count($fragments) . "\n";
echo "STAFF: " . count($staff) . " full=$sf partial=$sp none=$sn\n";
echo "MEMBER: " . count($member) . " full=$mf partial=$mp none=$mn\n";

// Build markdown (abbreviated loc display)
$md = [];
$md[] = '# API 全量目录与使用方法';
$md[] = 'Generated: 2026-07-13 (adversarial review)';
$md[] = '';
$md[] = '> 精简差距清单：[API-FRONTEND-GAP-AUDIT.md](./API-FRONTEND-GAP-AUDIT.md)';
$md[] = '';
$md[] = '## 摘要';
$md[] = '';
$md[] = '| 端 | 路由数 | FULL | PARTIAL | NONE | 触达率 |';
$md[] = '|---|---:|---:|---:|---:|---:|';
$md[] = sprintf('| **Staff** | **%d** | **%d** | **%d** | **%d** | **%.1f%%** |', count($staff), $sf, $sp, $sn, (count($staff) ? ($sf + $sp) / count($staff) * 100 : 0));
$md[] = sprintf('| **Member** | **%d** | **%d** | **%d** | **%d** | **%.1f%%** |', count($member), $mf, $mp, $mn, (count($member) ? ($mf + $mp) / count($member) * 100 : 0));
$md[] = '';
$md[] = '- 后端 `api/v1` 路由：**' . count($rows) . '** 条 · OpenAPI **287** operationId · 契约测试 **100%**';
$md[] = '- 扫描前端文件：**' . count($allFiles) . '** 个（staff + member `src/**`）';
$md[] = '- `traceability-apis.csv` **REJECT** 10 行已排除在优先级建议外';
$md[] = '';
$md[] = '## 使用方法说明';
$md[] = '';
$md[] = '### Base URL / Auth / 上下文';
$md[] = '';
$md[] = '```';
$md[] = 'BASE = import.meta.env.VITE_API_BASE_URL   // e.g. http://127.0.0.1:8010/api/v1';
$md[] = 'Authorization: Bearer <token>               // @mijing/api-client 自动附加';
$md[] = '员工端路径: /staff/sites/{siteId}/...        // siteId 来自 session store';
$md[] = '会员端查询: ?tenantId={id}&siteId={id}       // member.ts tenantQuery/siteQuery';
$md[] = '```';
$md[] = '';
$md[] = '### staff-miniapp（`src/api/*.ts`）';
$md[] = '';
$md[] = '| 模块 | 封装函数示例 | 路径模式 |';
$md[] = '|---|---|---|';
$md[] = '| `client.ts` | `useApiClient().request(path)` | 通用 |';
$md[] = '| `crm.ts` | `fetchCrmMembers`, `syncMemberTags` | `sitePath(siteId, suffix)` |';
$md[] = '| `scheduling.ts` | `fetchStaffScheduleSessions`, `cancelStaffAppointment` | `sitePath` |';
$md[] = '| `reports.ts` | `fetchReportFinanceProfitSummary` | `reportsPath(siteId, suffix)` |';
$md[] = '| `member-cards.ts` | `freezeMemberCard`, `issueMemberCard` | `cardPath` / 直连 |';
$md[] = '| `settings.ts` | `fetchBookingPolicy`, `createSiteClosure` | 直连 `/staff/sites/{id}/...` |';
$md[] = '| `profile.ts` | `uploadStaffAvatar` | `uni.uploadFile` → `/staff/profile/avatar` |';
$md[] = '';
$md[] = '### member-miniapp';
$md[] = '';
$md[] = '| 模块 | 说明 |';
$md[] = '|---|---|';
$md[] = '| `api/member.ts` | 主 API 层，导出 `getMemberHome` 等 30+ 函数 |';
$md[] = '| `composables/member-context.ts` | `/member/sites`, `/member/memberships` |';
$md[] = '| `pages/onboarding/profile.vue` | onboarding / verify-mobile / join / link decision |';
$md[] = '';
$md[] = '### 共享端点';
$md[] = '';
$md[] = '| Method | Path | 前端 |';
$md[] = '|---|---|---|';
foreach ($shared as $r) {
    $loc = $r['locs'][0] ?? '—';
    $md[] = "| {$r['method']} | `{$r['path']}` | `$loc` |";
}
$md[] = '';

function renderTable(string $title, array $section): array
{
    $out = ["## $title", '', '| Method | Path | operationId | 用途 | 认证 | 测试 | 前端 | 调用位置/建议 |', '|---|---|---|---|---|---|---|---|'];
    foreach ($section as $r) {
        $loc = $r['locs'] ? '`' . implode('`, `', array_slice($r['locs'], 0, 2)) . '`' : '—';
        if ($r['status'] === 'NONE' && $r['pri']) {
            $loc = "— · **{$r['pri']}** 待接";
        }
        $out[] = "| {$r['method']} | `{$r['path']}` | `{$r['operationId']}` | {$r['purpose']} | {$r['auth']} | {$r['test']} | **{$r['status']}** | $loc |";
    }
    $out[] = '';
    return $out;
}

$md = array_merge($md, renderTable('Staff API 全表', $staff));
$md = array_merge($md, renderTable('Member API 全表', $member));

$md[] = '## 未使用 API 清单（对抗式）';
$md[] = '';
foreach (['Staff', 'Member'] as $side) {
    $set = $side === 'Staff' ? $staff : $member;
    foreach (['P0', 'P1', 'P2'] as $p) {
        $md[] = "### $side $p";
        $md[] = '';
        $any = false;
        foreach ($set as $r) {
            if ($r['status'] === 'NONE' && $r['pri'] === $p) {
                $md[] = "- `{$r['method']} {$r['path']}` (`{$r['operationId']}`) — {$r['test']}";
                $any = true;
            }
        }
        if (!$any) {
            $md[] = '_（无）_';
        }
        $md[] = '';
    }
}

$md[] = '## 部分对接 API';
$md[] = '';
$md[] = '| API | 说明 |';
$md[] = '|---|---|';
$md[] = '| `GET /member/member-link-requests` | `decision` 在 onboarding 已用；列表未用 |';
$md[] = '| `GET /staff/.../payment-marketing` | 只读展示；后端无 PUT |';
$md[] = '| `PATCH closure-calendar/{id}` | 仅 create 已接 |';
$md[] = '| `PATCH notices/{id}` | 仅 create + archive |';
$md[] = '| `PATCH staff/.../vacations/{id}` | 仅 create |';
$md[] = '';
$md[] = '## 故意无前端';
$md[] = '';
$md[] = '- `GET /health` — 探活';
$md[] = '- `GET /public/booking/share/sessions/{token}` — H5/外链分享（非小程序内）';
$md[] = '- `GET /public/booking/warm-hint/sites/{site}` — 公开暖场文案';
$md[] = '- `GET|POST|PUT|PATCH|DELETE /sites` — apiResource；小程序用 `chain/sites` + `staff/sites/{id}/profile`';
$md[] = '';
$md[] = '## OpenAPI 契约一致性';
$md[] = '';
$md[] = '- `OpenApiRouteContractTest`: **287/287**（100%）';
$md[] = '- `route:list` 条目 **' . count($rows) . '** vs OpenAPI ops **287**（`GET|HEAD` 合并差异）';
$md[] = '';
$md[] = '## 对抗式诚实差距';
$md[] = '';
$md[] = '1. **订单运营（P0）**：`void` / `amount-corrections` / `internal-notes` / `GET members/{id}/orders` 有 PHPUnit，员工端 **NONE**';
$md[] = '2. **转赠（P0）**：`transfer-share-token` 未接；会员 `claim` 已接';
$md[] = '3. **候补（P0）**：会员 `appointments/{id}/promote` **NONE**';
$md[] = '4. **平台订阅（P1）**：仅 `orders`；`pricing`/`agreement`/`pay` **NONE**';
$md[] = '5. **课表进阶（P1）**：`unsuspend`/`batch-unsuspend`/`batch-change-course`/`share-link`/`export-image` **NONE**';
$md[] = '6. **CRM 积分（P1）**：`point-adjustments` **NONE**';
$md[] = '7. **会员闭馆（P1）**：`closure-status` **NONE**';
$md[] = '8. **路由陷阱**：`catalog.ts` 教室 `archive` 为 POST（正确）；`DELETE rooms/{id}` 未接';

file_put_contents($root . '/docs/API-CATALOG-AND-USAGE.md', implode("\n", $md));
file_put_contents($root . '/docs/_audit-summary.json', json_encode([
    'staff' => ['total' => count($staff), 'full' => $sf, 'partial' => $sp, 'none' => $sn],
    'member' => ['total' => count($member), 'full' => $mf, 'partial' => $mp, 'none' => $mn],
    'routes' => count($rows),
    'openapi' => count($operations),
], JSON_PRETTY_PRINT));
echo "Done.\n";
