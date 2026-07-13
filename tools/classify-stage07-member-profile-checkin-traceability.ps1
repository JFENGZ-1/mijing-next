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
    'pages/index/index' = @(
        'member-home', 'KEEP', 'member.home', 'AC-07-01',
        'Home tab: site carousel (getSiteFaceimage), notices (getNoticeList), upcoming appointments (selectAppoint) with cancel, shortcuts to shop/buy/stats; official-account nudge when refuseUserFocus. Evidence: 会员端/pages/index/index.js, index.wxml.'
    )
    'pages/mine/index' = @(
        'member-home', 'KEEP', 'member.mine', 'AC-07-02',
        'Mine tab dashboard: myMainpage stats (appointCount, lastMonthAppointCount, absenceCount/totalPoint, monthRankNum), stacked wallet cards, shortcuts to profile/records/orders/stats; WeChat card-pack CTA when isPutWeixin==0. Evidence: 会员端/pages/mine/index.js, index.wxml.'
    )
    'pages/tailor/u-avatar-cropper/u-avatar-cropper' = @(
        'member-profile', 'REPLACE', 'member.avatar', 'AC-07-03',
        'Avatar crop then upload to /common/uploadfile and persist via UpdateUserInfo on profile page. Evidence: pageMine/myInfo/index.js created() handler.'
    )
    'pages/authorization/info/index' = @(
        'member-profile', 'MERGE', 'member.onboarding', 'AC-07-04',
        'First-time nickname/avatar capture stored locally then routed to phone/register. Evidence: member-audit §4.1 authorization/info.'
    )
    'pages/authorization/phone/index' = @(
        'member-profile', 'MERGE', 'member.onboarding', 'AC-07-04',
        'Phone authorize via getWeixinPhoneNumber then register. Evidence: pages/authorization/phone and receiveCard flows.'
    )
    'pageHome/informDetails/index' = @(
        'notice', 'REPLACE', 'notice.detail', 'AC-07-05',
        'Notice detail resolved client-side from getNoticeList by noticeId query param. Evidence: pageHome/informDetails/index.js.'
    )
    'pageHome/QRcode/QRcode' = @(
        'member-home', 'KEEP', 'member.official-account-follow', 'AC-07-06',
        'Static WeChat official-account QR (qr_code_c.png); copy says 长按图片识别 and 课程取消/上课提醒/排队成功通知; reached from home 去关注 when serviceCodeFocus unset. No API, no member identity code. Evidence: pageHome/QRcode/QRcode.wxml, pages/index/index.wxml.'
    )
    'pageMine/myInfo/index' = @(
        'member-profile', 'REPLACE', 'member.profile', 'AC-07-07',
        'Profile hub: getMyUserInfo merges tenant userField config with userFaceurl/sex/nickname/ident/phone/birthday/height/weight; inline save and avatar upload. Evidence: pageMine/myInfo/index.js.'
    )
    'pageMine/modifidInfo/index' = @(
        'member-profile', 'MERGE', 'member.profile', 'AC-07-07',
        'Single-field editor; phone uses getWeixinPhoneNumber; saves via UpdateUserInfo. Evidence: pageMine/modifidInfo/index.js.'
    )
    'pageMine/totalStatistics/index' = @(
        'member-insight', 'KEEP', 'member.stats.year', 'AC-07-08',
        'Yearly appointment rollup list via sumUserList (yearlist, totalCount) linking to month drill-down. Evidence: pageMine/totalStatistics/index.js.'
    )
    'pageMine/appointmentStatistics/index' = @(
        'member-insight', 'KEEP', 'member.stats.month', 'AC-07-09',
        'Month calendar summary (sumAppointOfMonth) plus paginated appointment list (selectAppointOfMonth) with course-type tabs. Evidence: pageMine/appointmentStatistics/index.js.'
    )
    'pageMine/rankingRecord/index' = @(
        'ranking', 'REPLACE', 'ranking.opt-in', 'AC-07-10',
        'Monthly top-15 rankList plus myRank block; gated on mine tab by showMonthRank config. Evidence: pageMine/rankingRecord/index.js, pages/mine/index.wxml.'
    )
    'pageMine/point/index' = @(
        'points', 'KEEP', 'points.ledger', 'AC-07-11',
        'Paginated PointListByUserId ledger with totalPoint and descText; shown on mine when pointStarted==1. Evidence: pageMine/point/index.js, pages/mine/index.wxml.'
    )
}

$apiMap = @{
    '/c/user/getMyUserInfo' = @(
        'member-profile', 'REPLACE', 'member.profile.read', 'AC-07-07',
        'Returns user + tenant userField[] required flags for profile form. Evidence: pageMine/myInfo/index.js getUserInfo().'
    )
    '/c/user/UpdateUserInfo' = @(
        'member-profile', 'REPLACE', 'member.profile.update', 'AC-07-07',
        'Member self-service profile patch including avatar URL after upload. Evidence: pageMine/myInfo/index.js saveInfo(), modifidInfo/index.js save().'
    )
    '/c/user/getNoticeList' = @(
        'notice', 'REPLACE', 'notice.list', 'AC-07-05',
        'Home notice cards: datalist[] with noticeId, noticeTitle, noticeText, createTime. Evidence: pages/index/index.js getInformList().'
    )
    '/c/user/getSiteFaceimage' = @(
        'member-home', 'REPLACE', 'member.home.carousel', 'AC-07-01',
        'Home swiper images: data.imglist or fallback data.defImage. Evidence: pages/index/index.js getSiteFaceimage().'
    )
    '/c/user/getUserInfoForUpdate' = @(
        'member-profile', 'REPLACE', 'member.profile.purchase-gate', 'AC-07-12',
        'Preflight before 购卡续费: code!=200 blocks navigation with msg. Evidence: pages/index/index.js buyCard().'
    )
    '/c/user/myMainpage' = @(
        'member-home', 'REPLACE', 'member.mine.dashboard', 'AC-07-02',
        'Mine aggregate: user, cardlist, hellomsg, pointStarted plus appointCount/lastMonthAppointCount/absenceCount/totalPoint/monthRankNum on user. Evidence: pages/mine/index.js getMineInfo().'
    )
    '/c/user/getwxCardParam' = @(
        'member-card', 'REPLACE', 'member-card.wechat-pack.sign', 'AC-07-13',
        'WeChat card-pack signature bundle for userCardId before wx.addCard. Evidence: common/vendor.js putWXCardPackage dispatch.'
    )
    '/c/user/putweixincard' = @(
        'member-card', 'REPLACE', 'member-card.wechat-pack.confirm', 'AC-07-13',
        'Persists add-to-WeChat-wallet result after wx.addCard. Evidence: common/vendor.js putWXCardPackage.'
    )
    '/c/user/putweixinList' = @(
        'member-card', 'REPLACE', 'member-card.wechat-pack.pending', 'AC-07-13',
        'Lists cards with isPutWeixin==0 for home popup and mine CTA refresh. Evidence: pages/index/components/card-info.js, pages/mine/index.js putWxCardPack().'
    )
    '/c/user/findModifyLog' = @(
        'member-card', 'REPLACE', 'member-card.modify-log.list', 'AC-07-14',
        'Per-card metadata change history tab on useRecord (datalist, paginated). Evidence: pageMine/useRecord/index.js getChangeRecord().'
    )
    '/c/user/findUserAppointList' = @(
        'member-card', 'REPLACE', 'member-card.appointment-history.list', 'AC-07-14',
        'Per-card appointment history tab on useRecord (list, paginated by userCardId). Evidence: pageMine/useRecord/index.js getAppointmentRecord().'
    )
    '/c/user/PointListByUserId' = @(
        'points', 'REPLACE', 'points.ledger.list', 'AC-07-11',
        'Paginated point ledger: userInfo.plist, totalPoint, descText. Evidence: pageMine/point/index.js getList().'
    )
    '/c/user/rankList' = @(
        'ranking', 'REPLACE', 'ranking.monthly.list', 'AC-07-10',
        'Monthly leaderboard list[] capped to 15 plus myRank {indexnum, ncount, userRealname, userFaceurl}. Evidence: pageMine/rankingRecord/index.js.'
    )
    '/c/user/selectAppointOfMonth' = @(
        'member-insight', 'REPLACE', 'member.stats.month.appointments', 'AC-07-09',
        'Month-filtered appointment list with coursetype tabs and pagination. Evidence: pageMine/appointmentStatistics/index.js getData().'
    )
    '/c/user/sumAppointOfMonth' = @(
        'member-insight', 'REPLACE', 'member.stats.month.calendar', 'AC-07-09',
        'Month calendar heatmap/summary data for appointmentStatistics header. Evidence: pageMine/appointmentStatistics/index.js getRecord().'
    )
    '/c/user/sumUserList' = @(
        'member-insight', 'REPLACE', 'member.stats.year.summary', 'AC-07-08',
        'Yearly buckets yearlist[] and totalCount for totalStatistics page. Evidence: pageMine/totalStatistics/index.js onLoad.'
    )
}

foreach ($row in $pages) {
    $path = $row.LegacyPath
    if ($pageMap.ContainsKey($path)) {
        $m = $pageMap[$path]
        Set-PageRow $row $m[0] $m[1] $m[2] $m[3] $m[4]
    }
}

foreach ($row in $apis) {
    $ep = $row.LegacyEndpoint
    if ([string]::IsNullOrWhiteSpace($ep)) { continue }
    if ($apiMap.ContainsKey($ep)) {
        $m = $apiMap[$ep]
        Set-ApiRow $row $m[0] $m[1] $m[2] $m[3] $m[4]
    }
}

$pages | Export-Csv -LiteralPath $pageFile -NoTypeInformation -Encoding UTF8
$apis | Export-Csv -LiteralPath $apiFile -NoTypeInformation -Encoding UTF8

$pageHash = (Get-FileHash -LiteralPath $pageFile -Algorithm SHA256).Hash
$apiHash = (Get-FileHash -LiteralPath $apiFile -Algorithm SHA256).Hash
Write-Host "Updated traceability CSVs"
Write-Host "pages SHA-256 $pageHash"
Write-Host "apis  SHA-256 $apiHash"
