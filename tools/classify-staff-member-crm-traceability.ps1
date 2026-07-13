param([string]$DocsRoot = (Join-Path $PSScriptRoot "..\docs"))

$pageFile = Join-Path $DocsRoot "traceability-pages.csv"
$apiFile = Join-Path $DocsRoot "traceability-apis.csv"
$pages = Import-Csv -LiteralPath $pageFile
$apis = Import-Csv -LiteralPath $apiFile

$pageMap = @{
    'pages/member/member' = @('crm','MERGE','crm.member.list')
    'pageMember/search' = @('crm','MERGE','crm.member.list')
    'pageMember/screen' = @('crm','MERGE','crm.member.list')
    'pageMember/details/index' = @('crm','KEEP','crm.member.detail')
    'pageMember/details/cardDetail' = @('member-card','REPLACE','member-card.detail')
    'pageMember/details/memberPoint' = @('points','REPLACE','points.ledger')
    'pageMember/details/card-consumption' = @('ledger','REPLACE','member-card.usage-ledger')
    'pageMember/details/rechargeAmount' = @('order','REPLACE','payment.ledger')
    'pageMember/details/courseDetail' = @('booking','REPLACE','booking.history')
    'pageMember/details/recordDetails' = @('booking','REPLACE','booking.detail')
    'pageMember/information/index' = @('crm','REPLACE','crm.member.form')
    'pageMember/del-card/del-card' = @('member-card','REPLACE','member-card.archive')
    'pageMember/del-member/del-member' = @('crm','REPLACE','crm.member.lifecycle')
}

$memberPages = @($pages | Where-Object { $_.App -eq '管理端' -and $pageMap.ContainsKey($_.LegacyPath) })
if ($memberPages.Count -ne 13 -or $pageMap.Count -ne 13) { throw 'Staff member CRM page classification must cover exactly 13 pages.' }
foreach ($row in $memberPages) {
    $value = $pageMap[$row.LegacyPath]
    $row.Domain = $value[0]
    $row.Disposition = $value[1]
    $row.NewCapabilityId = $value[2]
    $row.AcceptanceCase = "planned:$($value[2])"
    $row.ReviewNote = if ($row.LegacyPath -eq 'pageMember/del-member/del-member') {
        'Hard deletion and revenue/appointment deletion are rejected; lifecycle history is retained.'
    } else { 'Reviewed from compiled CRM page, components and API call sites.' }
}

$manageApis = @($apis | Where-Object { $_.App -eq '管理端' -and $_.LegacyEndpoint -like '/b/manageuser/*' })
if ($manageApis.Count -ne 51) { throw "Expected 51 app-scoped /b/manageuser endpoints, found $($manageApis.Count)." }
foreach ($row in $manageApis) {
    $endpoint = $row.LegacyEndpoint
    $domain = if ($endpoint -match 'findUser2|saveuser|adduserbatch|delUser$|getDelUserList|unDeleteUser|updateUserRemark|updateUserTag|getsalestaffuserid|setUserNoLogin|transferToUser|pinyin') {
        'crm'
    } elseif ($endpoint -match 'Order|repay|saveOrder') {
        'order'
    } elseif ($endpoint -match 'Point') {
        'points'
    } else {
        'member-card'
    }
    $row.Domain = $domain
    $row.Disposition = if ($endpoint -eq '/b/manageuser/delUser') { 'REJECT' } else { 'REPLACE' }
    $row.NewOperationId = "planned:$domain"
    $row.AcceptanceCase = "planned:$domain"
    $row.ReviewNote = if ($row.Disposition -eq 'REJECT') {
        'Reject cascading deletion of member appointments and revenue; replace with reviewed lifecycle transition.'
    } else { 'Capability retained behind scoped domain API; card, ledger and order actions remain separate stages.' }
}

$pages | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $pageFile
$apis | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $apiFile

[pscustomobject]@{
    PagesReviewed = $memberPages.Count
    ManageUserApisReviewed = $manageApis.Count
}
