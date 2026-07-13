param([string]$DocsRoot = (Join-Path $PSScriptRoot "..\docs"))

$pageFile = Join-Path $DocsRoot "traceability-pages.csv"
$apiFile = Join-Path $DocsRoot "traceability-apis.csv"
$pages = Import-Csv -LiteralPath $pageFile
$apis = Import-Csv -LiteralPath $apiFile

function Set-PageRow {
    param($Row, $Domain, $Disposition, $CapabilityId, $AcceptanceCase, $Note)
    $Row.Domain = $Domain
    $Row.Disposition = $Disposition
    $Row.NewCapabilityId = $CapabilityId
    $Row.AcceptanceCase = $AcceptanceCase
    $Row.ReviewNote = $Note
}

function Set-ApiRow {
    param($Row, $Domain, $Disposition, $OperationId, $AcceptanceCase, $Note)
    $Row.Domain = $Domain
    $Row.Disposition = $Disposition
    $Row.NewOperationId = $OperationId
    $Row.AcceptanceCase = $AcceptanceCase
    $Row.ReviewNote = $Note
}

$pageMap = @{
    'pages/member/member' = @(
        'crm', 'MERGE', 'crm.member.list', 'AC-08-01',
        'Member tab: sumReport dashboard chips, pinyin u-index-list, findUser2 filters (card/tag/remark/holiday/frozen), cardCountTag visitor toggle, permission func 31. Evidence: 管理端/pages/member/member.js, member.wxml.'
    )
    'pageMember/search' = @(
        'crm', 'MERGE', 'crm.member.list', 'AC-08-02',
        'Keyword search via findUser (pagesize 100), highlight name/phone, opens detail or cardToolbox popup. Evidence: 管理端/pageMember/search.js.'
    )
    'pageMember/screen' = @(
        'crm', 'MERGE', 'crm.member.filter', 'AC-08-03',
        'Advanced filter + batch entry: card/tag/remark/leave/stop, birthday/register ranges, sumMode/runOff cohorts, flag presets from tab chips. Evidence: 管理端/pageMember/screen.js.'
    )
    'pageMember/details/index' = @(
        'crm', 'KEEP', 'crm.member.detail', 'AC-08-04',
        'Detail hub: wallet summary, tag/remark/app-access, make-over transfer, mini-program claim QR; drills to card/booking/payment sub-pages. Evidence: 管理端/pageMember/details/index.js.'
    )
    'pageMember/details/cardDetail' = @(
        'member-card', 'REPLACE', 'member-card.detail', 'planned:member-card.detail',
        'Per-card staff console; Stage 05 member-card.detail. Evidence: 管理端/pageMember/details/cardDetail.js.'
    )
    'pageMember/details/memberPoint' = @(
        'points', 'REPLACE', 'points.ledger', 'AC-07-11',
        'Staff points drill-down from member detail; Stage 07 points.ledger. Evidence: 管理端/pageMember/details/memberPoint.js.'
    )
    'pageMember/details/card-consumption' = @(
        'ledger', 'REPLACE', 'member-card.usage-ledger', 'planned:member-card.usage-ledger',
        'Card usage ledger drill-down; Stage 05. Evidence: 管理端/pageMember/details/card-consumption.js.'
    )
    'pageMember/details/rechargeAmount' = @(
        'order', 'REPLACE', 'payment.ledger', 'planned:payment.ledger',
        'Recharge/payment history drill-down; Stage 05 order adjacency. Evidence: 管理端/pageMember/details/rechargeAmount.js.'
    )
    'pageMember/details/courseDetail' = @(
        'booking', 'REPLACE', 'booking.history', 'planned:booking.history',
        'Monthly appointment history; Stage 06 booking.member-history. Evidence: 管理端/pageMember/details/courseDetail.js.'
    )
    'pageMember/details/recordDetails' = @(
        'booking', 'REPLACE', 'booking.detail', 'planned:booking.detail',
        'Single appointment drill-down; Stage 06. Evidence: 管理端/pageMember/details/recordDetails.js.'
    )
    'pageMember/information/index' = @(
        'crm', 'REPLACE', 'crm.member.form', 'AC-08-05',
        'Create/edit lead: getuserFieldSetting required matrix, saveuser, adduserbatch paste import, sales consultant picker; delUser UI rejected. Evidence: 管理端/pageMember/information/index.js.'
    )
    'pageMember/del-card/del-card' = @(
        'member-card', 'REPLACE', 'member-card.archive', 'planned:member-card.archive',
        'Archived card list UI; Stage 05 member-card.archive. Evidence: 管理端/pageMember/del-card/del-card.js.'
    )
    'pageMember/del-member/del-member' = @(
        'crm', 'REPLACE', 'crm.member.deleted', 'AC-08-06',
        'Soft-deleted member list via getDelUserList; restore via unDeleteUser; hard delete rejected. Evidence: 管理端/pageMember/del-member/del-member.js.'
    )
}

$apiMap = @{
    '/b/manageuser/sumReport' = @(
        'crm', 'REPLACE', 'crm.member.dashboard.summary', 'AC-08-01',
        'Member tab dashboard counts and pinyin bucket metadata. Evidence: 管理端/pages/member/member.js loadPinYinList/loadFindUser.'
    )
    '/b/manageuser/findUser2' = @(
        'crm', 'REPLACE', 'crm.member.list', 'AC-08-01',
        'Primary member query: keywords, pingyinChars, card/tag/remark/status filters, sumMode/runOff on screen. Evidence: member.js, search.js, screen.js.'
    )
    '/b/manageuser/pinyinList' = @(
        'crm', 'REPLACE', 'crm.member.pinyin-index', 'AC-08-01',
        'Pinyin bucket list for member search picker. Evidence: 管理端/pagesCourse/components/member-search.js.'
    )
    '/b/manageuser/getpinyinCharList' = @(
        'crm', 'REPLACE', 'crm.member.pinyin-index', 'AC-08-01',
        'Exported in vendor bundle but no static call site found; treat as alias/dead export of pinyin index until proven.'
    )
    '/b/manageuser/saveuser' = @(
        'crm', 'REPLACE', 'crm.member.upsert', 'AC-08-05',
        'Create or update CRM profile from information form. Evidence: 管理端/pageMember/information/index.js.'
    )
    '/b/manageuser/adduserbatch' = @(
        'crm', 'REPLACE', 'crm.member.batch-import', 'AC-08-07',
        'Paste/batch lead import with failCount/failline. Evidence: 管理端/pageMember/information/index.js preservation().'
    )
    '/b/manageuser/getsalestaffuserid' = @(
        'crm', 'REPLACE', 'crm.member.sales-staff.list', 'AC-08-12',
        'Sales/会籍顾问 picker list. Evidence: 管理端/pageMember/components/membershipConsultant.js.'
    )
    '/b/manageuser/updateUserTag' = @(
        'crm', 'REPLACE', 'crm.member.tag.assign', 'AC-08-04',
        'Color flag tagValue 1-5 on member. Evidence: pageMember/details/index.js, components/cardToolbox/member-details.js.'
    )
    '/b/manageuser/updateUserRemark' = @(
        'crm', 'REPLACE', 'crm.member.sticky-remark.update', 'AC-08-10',
        'Single sticky remark (hasremark icon); not append-only internal notes. Evidence: pageMember/details/index.js.'
    )
    '/b/manageuser/getDelUserList' = @(
        'crm', 'REPLACE', 'crm.member.deleted.list', 'AC-08-06',
        'Archived/deleted member list. Evidence: 管理端/pageMember/del-member/del-member.js.'
    )
    '/b/manageuser/unDeleteUser' = @(
        'crm', 'REPLACE', 'crm.member.restore', 'AC-08-06',
        'Restore archived member; 601 when blocked. Evidence: 管理端/pageMember/del-member/del-member.js.'
    )
    '/b/manageuser/delUser' = @(
        'crm', 'REJECT', 'crm.member.delete', 'AC-08-06',
        'Reject cascading deletion of appointments and revenue; UI still present on information page.'
    )
    '/b/setting/getuserFieldSetting' = @(
        'crm-config', 'REPLACE', 'crm.member-field-policy.get', 'AC-08-05',
        'Tenant profile field visibility/required flags for staff form. Evidence: 管理端/pageMember/information/index.js getUserList().'
    )
}

$memberPages = @($pages | Where-Object { $_.App -eq '管理端' -and $pageMap.ContainsKey($_.LegacyPath) })
if ($memberPages.Count -ne 13) { throw "Expected 13 staff CRM pages, found $($memberPages.Count)." }
foreach ($row in $memberPages) {
    $value = $pageMap[$row.LegacyPath]
    Set-PageRow $row $value[0] $value[1] $value[2] $value[3] $value[4]
}

$crmApis = @($apis | Where-Object { $_.App -eq '管理端' -and $apiMap.ContainsKey($_.LegacyEndpoint) })
if ($crmApis.Count -ne 13) { throw "Expected 13 Stage 08 CRM API rows, found $($crmApis.Count)." }
foreach ($row in $crmApis) {
    $value = $apiMap[$row.LegacyEndpoint]
    Set-ApiRow $row $value[0] $value[1] $value[2] $value[3] $value[4]
}

$pages | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $pageFile
$apis | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $apiFile

function Get-FileSha256([string]$Path) {
    (Get-FileHash -Algorithm SHA256 -LiteralPath $Path).Hash
}

[pscustomobject]@{
    PagesRefined = $memberPages.Count
    ApisRefined = $crmApis.Count
    PagesUnreviewed = @($pages | Where-Object Disposition -eq 'UNREVIEWED').Count
    ApisUnreviewed = @($apis | Where-Object Disposition -eq 'UNREVIEWED').Count
    PagesSha256 = Get-FileSha256 $pageFile
    ApisSha256 = Get-FileSha256 $apiFile
}
