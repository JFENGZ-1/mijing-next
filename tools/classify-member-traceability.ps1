param([string]$DocsRoot = (Join-Path $PSScriptRoot "..\docs"))

$pageFile = Join-Path $DocsRoot "traceability-pages.csv"
$apiFile = Join-Path $DocsRoot "traceability-apis.csv"
$pages = Import-Csv -LiteralPath $pageFile
$apis = Import-Csv -LiteralPath $apiFile

$pageMap = @{
    'pages/start/index' = @('identity-routing','REPLACE','member.start-router')
    'pages/index/index' = @('member-home','KEEP','member.home')
    'pages/appointmentCourse/index' = @('booking','KEEP','booking.catalog')
    'pages/mine/index' = @('member-home','KEEP','member.mine')
    'pages/tailor/u-avatar-cropper/u-avatar-cropper' = @('member-profile','REPLACE','member.avatar')
    'pages/authorization/info/index' = @('member-profile','MERGE','member.onboarding')
    'pages/authorization/noLogin/index' = @('membership','REPLACE','membership.empty-state')
    'pages/authorization/phone/index' = @('member-profile','MERGE','member.onboarding')
    'pages/receiveCard/authorization/info/index' = @('card-transfer','MERGE','card-transfer.claim')
    'pages/receiveCard/authorization/phone/index' = @('card-transfer','MERGE','card-transfer.claim')
    'pages/receiveCard/index' = @('card-transfer','KEEP','card-transfer.claim')
    'pages/not/index' = @('membership','REPLACE','membership.empty-state')
    'pages/webView/index' = @('legal','REPLACE','legal.document')
    'pages/myOrder/index' = @('order','KEEP','order.list')
    'pageHome/shopDetails/index' = @('site','KEEP','site.public-detail')
    'pageHome/toggleShop/index' = @('site','REPLACE','site.context-selector')
    'pageHome/informDetails/index' = @('notice','REPLACE','notice.detail')
    'pageHome/appointmentDetails/index' = @('booking','KEEP','booking.detail')
    'pageHome/buyingCard/index' = @('commerce','KEEP','card-purchase.catalog')
    'pageHome/buyingCard/buySuccess' = @('commerce','REPLACE','order.result')
    'pageHome/QRcode/QRcode' = @('member-code','UNREVIEWED','')
    'pageCourse/coachCourse/index' = @('booking','KEEP','booking.private-detail')
    'pageCourse/coachCourse/share-index' = @('sharing','MERGE','booking.private-detail')
    'pageCourse/clusterCourse/index' = @('booking','KEEP','booking.group-detail')
    'pageCourse/clusterCourse/share-index' = @('sharing','MERGE','booking.group-detail')
    'pageMine/myInfo/index' = @('member-profile','REPLACE','member.profile')
    'pageMine/myInterests/index' = @('member-card','KEEP','member-card.benefits')
    'pageMine/useRecord/index' = @('ledger','KEEP','member.ledger')
    'pageMine/removeCard/index' = @('member-card','REPLACE','member-card.visibility')
    'pageMine/memberAgreement/index' = @('legal','REPLACE','legal.document')
    'pageMine/totalStatistics/index' = @('member-insight','KEEP','member.stats.year')
    'pageMine/appointmentStatistics/index' = @('member-insight','KEEP','member.stats.month')
    'pageMine/rankingRecord/index' = @('ranking','REPLACE','ranking.opt-in')
    'pageMine/modifidInfo/index' = @('member-profile','MERGE','member.profile')
    'pageMine/point/index' = @('points','KEEP','points.ledger')
}

$apiDomains = @{
    'identity' = @('/common/dict','/c/user/wxlogin','/wx/getWeixinPhoneNumber','/wx/getUnionId','/c/user/register')
    'member-home' = @('/c/user/getSiteFaceimage','/c/user/getNoticeList','/c/user/myMainpage','/c/user/getUserInfoForUpdate','/c/user/putweixinList','/c/user/getwxCardParam','/c/user/putweixincard')
    'order' = @('/c/user/myOrderList','/c/user/myOrderList_notoken')
    'booking' = @('/c/user/findAllPrivateDrainerList','/c/user/findTeamPlan','/c/user/findOneDrainerDetail','/c/user/findOneDrainerDetail_noToken','/c/user/getDrainerTimeList','/c/user/getOnePlan','/c/user/getOnePlan_noToken','/c/user/getwarmHint','/c/user/getwarmHint_noToken','/c/user/getCardListForPay','/c/user/applyAppointment','/c/user/replaceFormLine','/c/user/cancelAppoint','/c/user/selectAppoint','/c/user/selectOneAppoint')
    'member-card' = @('/c/user/checkCloseSite','/c/user/getAllCardInfo','/c/user/submitcard','/c/user/cardPrivilege','/c/user/getuserProtocolSetting','/c/user/getUserCardInfo','/c/user/takeByuserCardId','/c/user/deleteUserCard','/c/user/finddelUsercard','/c/user/recoverdelUserCard')
    'member-profile' = @('/c/user/getMyUserInfo','/c/user/UpdateUserInfo')
    'member-insight' = @('/c/user/findUserAppointList','/c/user/findModifyLog','/c/user/findAmountChangeLog','/c/user/sumUserList','/c/user/selectAppointOfMonth','/c/user/sumAppointOfMonth','/c/user/rankList','/c/user/PointListByUserId')
}

$endpointDomain = @{}
foreach ($domain in $apiDomains.Keys) {
    foreach ($endpoint in $apiDomains[$domain]) { $endpointDomain[$endpoint] = $domain }
}

$memberPages = @($pages | Where-Object App -eq '会员端')
if ($memberPages.Count -ne 35 -or $pageMap.Count -ne 35) { throw 'Member page classification must cover exactly 35 pages.' }
foreach ($row in $memberPages) {
    if (-not $pageMap.ContainsKey($row.LegacyPath)) { throw "Missing page classification: $($row.LegacyPath)" }
    $value = $pageMap[$row.LegacyPath]
    $row.Domain = $value[0]
    $row.Disposition = $value[1]
    $row.NewCapabilityId = $value[2]
    $row.AcceptanceCase = if ($value[1] -eq 'UNREVIEWED') { '' } else { "planned:$($value[2])" }
    $row.ReviewNote = if ($row.LegacyPath -eq 'pageHome/QRcode/QRcode') { 'Legacy artifact does not reveal whether this is an identity, card, or redemption code.' } else { 'Reviewed from compiled page artifacts and member audit.' }
}

$memberApis = @($apis | Where-Object App -eq '会员端')
if ($memberApis.Count -ne 49 -or $endpointDomain.Count -ne 49) { throw 'Member API classification must cover exactly 49 app-scoped endpoints.' }
foreach ($row in $memberApis) {
    if (-not $endpointDomain.ContainsKey($row.LegacyEndpoint)) { throw "Missing API classification: $($row.LegacyEndpoint)" }
    $row.Domain = $endpointDomain[$row.LegacyEndpoint]
    $row.Disposition = if ($row.LegacyEndpoint -eq '/c/user/myOrderList_notoken') { 'REJECT' } else { 'REPLACE' }
    $row.NewOperationId = "planned:$($endpointDomain[$row.LegacyEndpoint])"
    $row.AcceptanceCase = "planned:$($endpointDomain[$row.LegacyEndpoint])"
    $row.ReviewNote = if ($row.Disposition -eq 'REJECT') { 'One-time wx code is not an order authorization credential.' } else { 'Business capability retained behind new authenticated domain API.' }
}

$pages | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $pageFile
$apis | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $apiFile

[pscustomobject]@{
    MemberPagesReviewed = @($memberPages | Where-Object Disposition -ne 'UNREVIEWED').Count
    MemberPagesBlocked = @($memberPages | Where-Object Disposition -eq 'UNREVIEWED').Count
    MemberApisReviewed = @($memberApis | Where-Object Disposition -ne 'UNREVIEWED').Count
}
