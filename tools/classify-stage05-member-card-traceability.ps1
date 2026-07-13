param([string]$DocsRoot = (Join-Path $PSScriptRoot "..\docs"))

$pageFile = Join-Path $DocsRoot "traceability-pages.csv"
$apiFile = Join-Path $DocsRoot "traceability-apis.csv"
$pages = Import-Csv -LiteralPath $pageFile
$apis = Import-Csv -LiteralPath $apiFile

function Set-PageRow {
    param($Row, $Domain, $Disposition, $CapabilityId, $Note)
    $Row.Domain = $Domain
    $Row.Disposition = $Disposition
    $Row.NewCapabilityId = $CapabilityId
    $Row.AcceptanceCase = "planned:$CapabilityId"
    $Row.ReviewNote = $Note
}

function Set-ApiRow {
    param($Row, $Domain, $Disposition, $OperationId, $Note)
    $Row.Domain = $Domain
    $Row.Disposition = $Disposition
    $Row.NewOperationId = $OperationId
    $Row.AcceptanceCase = "planned:$OperationId"
    $Row.ReviewNote = $Note
}

$pageMap = @{
    'pages/shopOrder/index' = @(
        'platform-billing', 'REPLACE', 'platform.subscription.orders',
        'Calls findserviceSuccessOrder (staff token) or findMyOrder with one-time jscode; SaaS platform renewal orders, not member card commerce. Evidence: 管理端/pages/shopOrder/index.js.'
    )
    'pagesImp/card/home/home' = @(
        'card-product', 'KEEP', 'card-product.catalog',
        'Lists on-sale/stopped card templates, search, drag-sort via saveSortId; getAllCardInfo. Evidence: 管理端/pagesImp/card/home/home.js.'
    )
    'pagesImp/card/home/del-card' = @(
        'card-product', 'KEEP', 'card-product.recycle-bin',
        'Archived template list and recoverDelCard restore. Evidence: 管理端/pagesImp/card/home/del-card.js.'
    )
    'pagesImp/card/home/del-card-tp' = @(
        'card-product', 'MERGE', 'card-product.recycle-bin',
        'Union/chain template recycle variant; same recoverDelCard flow as del-card. Evidence: 管理端/pagesImp/card/home/del-card-tp.js.'
    )
    'pagesImp/card/create/create' = @(
        'card-product', 'MERGE', 'card-product.type-picker',
        'Static chooser for 储值/计次/期限 types routing to member-card editor. Evidence: 管理端/pagesImp/card/create/create.js.'
    )
    'pagesImp/card/member-card/index' = @(
        'card-product', 'KEEP', 'card-product.editor',
        'Full template editor: price, quota, validity, booking rules, saveCard/updateCardStatus/delcard. Evidence: 管理端/pagesImp/card/member-card/index.js.'
    )
    'pagesImp/card/member-card/index-tp' = @(
        'card-product', 'MERGE', 'card-product.editor',
        'Union-card template editor variant; same saveCard/getOneCardInfo/delcard APIs. Evidence: 管理端/pagesImp/card/member-card/index-tp.js.'
    )
    'pagesImp/card/card-subject/index' = @(
        'card-product', 'REPLACE', 'card-product.pricing-matrix',
        'Group/single sellable templates via getAllCardPrice. Evidence: 管理端/pagesImp/card/card-subject/index.js.'
    )
    'pagesImp/card/setting-more-subject/index' = @(
        'card-product', 'REPLACE', 'card-product.course-scope.group',
        'Maps group count-card projects to courses via getGroupCourseList/saveGroupCourseList. Evidence: 管理端/pagesImp/card/setting-more-subject/index.js.'
    )
    'pagesImp/card/setting-single-subject/index' = @(
        'card-product', 'REPLACE', 'card-product.course-scope.single',
        'Maps single-card course scope via getOneCardPrice/saveCommonCardCourse. Evidence: 管理端/pagesImp/card/setting-single-subject/index.js.'
    )
    'pagesImp/subject/suject-choice-card' = @(
        'card-product', 'MERGE', 'card-product.picker',
        'Selectable card-product list for subject edit flows via getAllCardInfo. Evidence: 管理端/pagesImp/subject/suject-choice-card.js.'
    )
    'pageReport/remind/cardExpires' = @(
        'member-card', 'REPLACE', 'member-card.reminder.expired',
        'Paged expired-card reminder via findwillExpireCard with threshold config. Evidence: 管理端/pageReport/remind/cardExpires.js.'
    )
    'pageReport/remind/component/cardExpiresSetting' = @(
        'member-card', 'MERGE', 'member-card.reminder.config',
        'Threshold editor for expiry reminders via saveconfig. Evidence: 管理端/pageReport/remind/component/cardExpiresSetting.js.'
    )
    'pageReport/remind/component/findBalaceZeroSetting' = @(
        'member-card', 'MERGE', 'member-card.reminder.config',
        'Zero-balance reminder threshold via saveconfig. Evidence: 管理端/pageReport/remind/component/findBalaceZeroSetting.js.'
    )
    'pageReport/remind/cardWill' = @(
        'member-card', 'REPLACE', 'member-card.reminder.pending-open',
        'Cards waiting to open via findWillOpenUserCardList. Evidence: 管理端/pageReport/remind/cardWill.js.'
    )
    'pageReport/remind/penalizeCard' = @(
        'member-card', 'REPLACE', 'member-card.reminder.penalized',
        'Penalized/frozen card reminder via findPunishUserCardList. Evidence: 管理端/pageReport/remind/penalizeCard.js.'
    )
    'pageReport/remind/findBalaceZeroCard' = @(
        'member-card', 'REPLACE', 'member-card.reminder.zero-balance',
        'Zero-balance member cards via findBalaceZeroCard. Evidence: 管理端/pageReport/remind/findBalaceZeroCard.js.'
    )
    'pageReport/rank/memberConsumptionCardRank' = @(
        'reporting', 'REPLACE', 'report.member-card.consumption-rank',
        'Member spend ranking via sumUserExpendPrice; read-only analytics deferred after ledger facts. Evidence: 管理端/pageReport/rank/memberConsumptionCardRank.js.'
    )
    'pageReport/rank/memberCard' = @(
        'reporting', 'REPLACE', 'report.card-product.sales-rank',
        'Per card-product sales rank via RankCardInfo_cardId. Evidence: 管理端/pageReport/rank/memberCard.js.'
    )
    'pageReport/rank/memberCardAnalyze' = @(
        'reporting', 'REPLACE', 'report.card-product.analytics',
        'Cross-site card analytics via getAllCardInfoIncludeUnionReport. Evidence: 管理端/pageReport/rank/memberCardAnalyze.js.'
    )
    'pageServer/order' = @(
        'platform-billing', 'MERGE', 'platform.subscription.orders',
        'SaaS renewal purchase via pricelist/submitwexinOrder; not tenant member-card orders. Evidence: 管理端/pageServer/order.js.'
    )
    'pageChain/cardStatistics/index' = @(
        'order', 'REPLACE', 'order.report.card-sales.summary',
        'Chain card sales rollup via mainreport/detailreport; exposes delUserOrder/saveOrderAmount (rejected patterns). Evidence: 管理端/pageChain/cardStatistics/index.js.'
    )
    'pageChain/cardStatistics/detailed-records' = @(
        'order', 'MERGE', 'order.report.card-sales.detail',
        'Per-card monthly sale detail via detailcardreport plus order note/amount mutations. Evidence: 管理端/pageChain/cardStatistics/detailed-records.js.'
    )
    'pageChain/card/home/home' = @(
        'card-product', 'MERGE', 'card-product.catalog',
        'Chain HQ catalog reusing pagesImp editor routes and getAllCardInfo. Evidence: 管理端/pageChain/card/home/home.js.'
    )
    'pageChain/card/home/subbranch-home' = @(
        'card-product', 'REPLACE', 'card-product.cross-site.link',
        'Per-site linked card visibility via getCardOfEachSite/changeToLinkcard. Evidence: 管理端/pageChain/card/home/subbranch-home.js.'
    )
    'pageChain/card/create/create' = @(
        'card-product', 'MERGE', 'card-product.type-picker',
        'Chain create chooser; routes to shared template editor. Evidence: 管理端/pageChain/card/create/create.js.'
    )
    'pageChain/card/card-subject/index' = @(
        'card-product', 'MERGE', 'card-product.pricing-matrix',
        'Chain pricing matrix via linkage getAllCardPrice. Evidence: 管理端/pageChain/card/card-subject/index.js.'
    )
    'pageChain/card/setting-more-subject/index' = @(
        'card-product', 'MERGE', 'card-product.course-scope.group',
        'Chain group course scope via linkage saveGroupCourseList. Evidence: 管理端/pageChain/card/setting-more-subject/index.js.'
    )
    'pageChain/card/setting-single-subject/index' = @(
        'card-product', 'MERGE', 'card-product.course-scope.single',
        'Chain single course scope via linkage saveCommonCardCourse. Evidence: 管理端/pageChain/card/setting-single-subject/index.js.'
    )
}

$apiMap = @{
    '/b/card/delcard' = @('card-product', 'REPLACE', 'card-product.archive', 'Soft-archives a card template; recoverDelCard restores. Evidence: pagesImp/card/member-card/index.js.')
    '/b/card/getAllCardPrice' = @('card-product', 'REPLACE', 'card-product.pricing-matrix.list', 'Returns groupCardList/singleCardList pricing matrix. Evidence: pagesImp/card/card-subject/index.js.')
    '/b/card/getDelCardInfo' = @('card-product', 'REPLACE', 'card-product.recycle-bin.list', 'Lists archived templates. Evidence: pagesImp/card/home/del-card.js.')
    '/b/card/getGroupCourseList' = @('card-product', 'REPLACE', 'card-product.course-scope.group.get', 'Group count-card course mapping. Evidence: pagesImp/card/setting-more-subject/index.js.')
    '/b/card/getOneCardInfo' = @('card-product', 'REPLACE', 'card-product.detail', 'Template detail for editor. Evidence: pagesImp/card/member-card/index.js.')
    '/b/card/getOneCardPrice' = @('card-product', 'REPLACE', 'card-product.course-scope.single.get', 'Single-card course/price scope. Evidence: pagesImp/card/setting-single-subject/index.js.')
    '/b/card/recoverDelCard' = @('card-product', 'REPLACE', 'card-product.restore', 'Restores archived template. Evidence: pagesImp/card/home/del-card.js.')
    '/b/card/saveCard' = @('card-product', 'REPLACE', 'card-product.upsert', 'Creates/updates card template with booking/validity rules. Evidence: pagesImp/card/member-card/index.js.')
    '/b/card/saveCommonCardCourse' = @('card-product', 'REPLACE', 'card-product.course-scope.single.save', 'Persists single-card course mapping. Evidence: pagesImp/card/setting-single-subject/index.js.')
    '/b/card/saveGroupCourseList' = @('card-product', 'REPLACE', 'card-product.course-scope.group.save', 'Persists group-card course mapping. Evidence: pagesImp/card/setting-more-subject/index.js.')
    '/b/card/saveSortId' = @('card-product', 'REPLACE', 'card-product.sort', 'Persists catalog sort order. Evidence: pagesImp/card/home/home.js.')
    '/b/card/selectAllbackImg' = @('card-product', 'REPLACE', 'card-product.face-library.list', 'Card face image library. Evidence: pagesImp/card/components/cardFace.js.')
    '/b/card/updateCardStatus' = @('card-product', 'REPLACE', 'card-product.sale-status', 'nstatus 0停售/1上架/2删除标记. Evidence: pagesImp/card/member-card/index.js.')
    '/b/export/exportcard' = @('card-product', 'MERGE', 'card-product.export', 'Template export job; audit required. Evidence: api-catalog export write endpoint.')
    '/b/linkage/changeToLinkcard' = @('card-product', 'REPLACE', 'card-product.cross-site.convert', 'Converts site card to linked/union card. Evidence: pageChain/card/home/subbranch-home.js.')
    '/b/linkage/detailcardreport' = @('order', 'REPLACE', 'order.report.card-sales.detail', 'Per-card sales detail for chain statistics. Evidence: pageChain/cardStatistics/detailed-records.js.')
    '/b/linkage/getAllCardPrice' = @('card-product', 'MERGE', 'card-product.pricing-matrix.list', 'Chain duplicate of getAllCardPrice. Evidence: pageChain/card/card-subject/index.js.')
    '/b/linkage/getCardOfEachSite' = @('card-product', 'REPLACE', 'card-product.cross-site.list', 'Per-branch linked catalog. Evidence: pageChain/card/home/subbranch-home.js.')
    '/b/linkage/getDelCardInfo' = @('card-product', 'MERGE', 'card-product.recycle-bin.list', 'Chain archived template list. Evidence: pagesImp/card/home/del-card-tp.js.')
    '/b/linkage/getGroupCourseList' = @('card-product', 'MERGE', 'card-product.course-scope.group.get', 'Chain duplicate course scope read. Evidence: pageChain/card/setting-more-subject/index.js.')
    '/b/linkage/getOneCardPrice' = @('card-product', 'MERGE', 'card-product.course-scope.single.get', 'Chain duplicate single scope read. Evidence: pageChain/card/setting-single-subject/index.js.')
    '/b/linkage/recoverDelCard' = @('card-product', 'MERGE', 'card-product.restore', 'Chain duplicate restore. Evidence: pagesImp/card/home/del-card-tp.js.')
    '/b/linkage/saveCommonCardCourse' = @('card-product', 'MERGE', 'card-product.course-scope.single.save', 'Chain duplicate single scope write. Evidence: pageChain/card/setting-single-subject/index.js.')
    '/b/linkage/saveGroupCourseList' = @('card-product', 'MERGE', 'card-product.course-scope.group.save', 'Chain duplicate group scope write. Evidence: pageChain/card/setting-more-subject/index.js.')
    '/b/mainplan/getCardListForPay' = @('member-card', 'MERGE', 'member-card.payable.list', 'Payable member cards during staff booking checkout. Evidence: api-usages mainplan call site.')
    '/b/platform/findMyOrder' = @('platform-billing', 'REJECT', 'platform.subscription.orders', 'One-time jscode is not an order authorization credential. Evidence: pages/shopOrder/index.js.')
    '/b/platform/findserviceSuccessOrder' = @('platform-billing', 'REPLACE', 'platform.subscription.orders.list', 'Staff-authenticated SaaS renewal history. Evidence: pages/shopOrder/index.js.')
    '/b/platform/submitwexinOrder' = @('platform-billing', 'REPLACE', 'platform.subscription.pay', 'WeChat pay for SaaS renewal. Evidence: pageServer/order.js.')
    '/b/report2/clearUserPoint' = @('points', 'REJECT', 'points.adjustment.reset', 'Reject zeroing points history; use audited reversing entries. Evidence: destructive write endpoint name.')
    '/b/report2/findBalaceZeroCard' = @('member-card', 'REPLACE', 'member-card.reminder.zero-balance.list', 'Zero-balance cards report. Evidence: pageReport/remind/findBalaceZeroCard.js.')
    '/b/report2/findHolidayCardList' = @('member-card', 'REPLACE', 'member-card.reminder.holiday.list', 'Holiday/frozen card list. Evidence: pageReport/remind/leavedDue.js call site.')
    '/b/report2/findPunishUserCardList' = @('member-card', 'REPLACE', 'member-card.reminder.penalized.list', 'Penalized card list. Evidence: pageReport/remind/penalizeCard.js.')
    '/b/report2/findUserPointList' = @('points', 'MERGE', 'points.member-ledger.list', 'Duplicate member points history query; merge with PointListByUserId. Evidence: api-catalog query endpoint.')
    '/b/report2/findwillExpireCard' = @('member-card', 'REPLACE', 'member-card.reminder.expired.list', 'Expired/near-expiry cards. Evidence: pageReport/remind/cardExpires.js.')
    '/b/report2/findWillOpenUserCardList' = @('member-card', 'REPLACE', 'member-card.reminder.pending-open.list', 'Pending activation cards. Evidence: pageReport/remind/cardWill.js.')
    '/b/report2/getAllCardInfoIncludeUnionReport' = @('reporting', 'REPLACE', 'report.card-product.analytics', 'Union card analytics source. Evidence: pageReport/rank/memberCardAnalyze.js.')
    '/b/report2/getUserPointConfig' = @('points', 'REPLACE', 'points.config.get', 'Points policy config read. Evidence: rank/memberPointConfig adjacency.')
    '/b/report2/RankCardInfo_cardId' = @('reporting', 'REPLACE', 'report.card-product.sales-rank', 'Per card-product sales ranking. Evidence: pageReport/rank/memberCard.js.')
    '/b/report2/saveconfig' = @('member-card', 'REPLACE', 'member-card.reminder.config.save', 'Reminder threshold config for card alerts. Evidence: cardExpiresSetting.js.')
    '/b/report2/saveUserPointConfig' = @('points', 'REPLACE', 'points.config.save', 'Points policy config write with audit. Evidence: rank/memberPointConfig adjacency.')
    '/b/report2/sumUserExpendPrice' = @('reporting', 'REPLACE', 'report.member-card.consumption-rank', 'Member consumption ranking source. Evidence: memberConsumptionCardRank.js.')
    '/b/report2/UserCardAnalyze' = @('reporting', 'REPLACE', 'report.member-card.analytics', 'Member card analytics query. Evidence: api-catalog endpoint name.')
    '/b/report2/userOrderRank' = @('reporting', 'REPLACE', 'report.order.rank', 'Order ranking analytics; read-only over order facts. Evidence: api-catalog endpoint name.')
    '/b/userorder/saveOrderAmount' = @('order', 'MERGE', 'order.amount.correct', 'Duplicate order amount correction path; must be immutable correction not overwrite. Evidence: pageChain/cardStatistics/index.js.')
}

$manageCardApiMap = @{
    '/b/manageuser/addUserCard' = @('member-card.issue', 'Staff issues member card from product template.')
    '/b/manageuser/allUserchangeValidTime' = @('member-card.validity.batch-adjust', 'Batch validity extension/shrink with ledger.')
    '/b/manageuser/alluserridofstopCard' = @('member-card.freeze.batch-lift', 'Batch lift stop/freeze.')
    '/b/manageuser/alluserstopUsercard' = @('member-card.freeze.batch', 'Batch stop/freeze member cards.')
    '/b/manageuser/applyHoliday' = @('member-card.holiday.apply', 'Holiday freeze with end date.')
    '/b/manageuser/batchchangeAmount' = @('member-card.balance.batch-adjust', 'Batch balance adjustment via immutable ledger commands.')
    '/b/manageuser/batchchangeValidTime' = @('member-card.validity.batch-adjust', 'Batch validity change.')
    '/b/manageuser/batchridofstopCard' = @('member-card.freeze.batch-lift', 'Batch unfreeze.')
    '/b/manageuser/batchstopUsercard' = @('member-card.freeze.batch', 'Batch freeze.')
    '/b/manageuser/cancelHoliday' = @('member-card.holiday.cancel', 'Cancel holiday freeze.')
    '/b/manageuser/changeAmount' = @('member-card.balance.adjust', 'Single balance adjustment; evidence pageMember/components/userCard/card-management.js.')
    '/b/manageuser/changeInitCardAmount' = @('member-card.opening-balance.correct', 'Opening balance correction via reversal pair.')
    '/b/manageuser/changeValidTime' = @('member-card.validity.adjust', 'Validity adjustment with audit.')
    '/b/manageuser/delUserCard' = @('member-card.archive', 'Archive member card instance; no physical delete of ledger.')
    '/b/manageuser/findAmountChangeLog' = @('member-card.balance-ledger.list', 'Balance ledger pagination; evidence cardDetail.js and member useRecord.')
    '/b/manageuser/findbatchUserCardList' = @('member-card.batch.list', 'Batch operation candidate list.')
    '/b/manageuser/findLastUserHoliday' = @('member-card.holiday.last', 'Latest holiday record.')
    '/b/manageuser/findModifyLog' = @('member-card.change-ledger.list', 'Card metadata change history.')
    '/b/manageuser/findPunishLog' = @('member-card.penalty-ledger.list', 'Penalty/absence deduction history.')
    '/b/manageuser/findUserdy2' = @('member-card.dynamic-fields.list', 'Dynamic CRM fields on card context.')
    '/b/manageuser/getDefaultFee' = @('member-card.default-fee.get', 'Default fee for card operations.')
    '/b/manageuser/getLastStopUsercardLog' = @('member-card.freeze-ledger.last', 'Latest freeze/stop record.')
    '/b/manageuser/getOneUserCardInfo' = @('member-card.detail', 'Single issued card detail.')
    '/b/manageuser/getShareKey' = @('card-transfer.share-key', 'Share key for gift/transfer; requires signed short-lived token in new system.')
    '/b/manageuser/getSumCardInfo' = @('member-card.summary', 'Aggregated card balances/counts for member.')
    '/b/manageuser/getUserCardInfo' = @('member-card.wallet.list', 'All active/archived cards for member.')
    '/b/manageuser/recoverUserCard' = @('member-card.restore', 'Restore archived member card.')
    '/b/manageuser/ridofstopCard' = @('member-card.freeze.lift', 'Lift single card freeze.')
    '/b/manageuser/stopUsercard' = @('member-card.freeze', 'Freeze/stop single member card.')
}

$memberCardApiMap = @{
    '/c/user/cardPrivilege' = @('member-card', 'REPLACE', 'member-card.benefits.get', 'Rich-text benefits for selected card. Evidence: pageMine/myInterests/index.js.')
    '/c/user/deleteUserCard' = @('member-card', 'REPLACE', 'member-card.visibility.hide', 'Member hides card from wallet. Evidence: member audit removeCard flow.')
    '/c/user/finddelUsercard' = @('member-card', 'REPLACE', 'member-card.archive.list', 'Lists hidden/archived cards for recovery UI.')
    '/c/user/findAmountChangeLog' = @('member-card', 'REPLACE', 'member-card.balance-ledger.list', 'Member balance ledger tab. Evidence: pageMine/useRecord/index.js.')
    '/c/user/getAllCardInfo' = @('card-product', 'REPLACE', 'card-product.sellable.list', 'Sellable templates on buyingCard page.')
    '/c/user/getUserCardInfo' = @('member-card', 'REPLACE', 'member-card.wallet.list', 'Member wallet cards on mine/home.')
    '/c/user/recoverdelUserCard' = @('member-card', 'REPLACE', 'member-card.visibility.restore', 'Restore hidden card. Evidence: pageMine/removeCard.')
    '/c/user/submitcard' = @('member-card', 'REPLACE', 'member-card.purchase.submit', 'Member self-service card purchase submit.')
    '/c/user/takeByuserCardId' = @('member-card', 'REPLACE', 'member-card.activate', 'Activates pending-open card instance.')
}

$pageUpdated = 0
foreach ($row in $pages) {
    if (-not $pageMap.ContainsKey($row.LegacyPath)) { continue }
    $v = $pageMap[$row.LegacyPath]
    Set-PageRow $row $v[0] $v[1] $v[2] $v[3]
    $pageUpdated++
}

$apiUpdated = 0
foreach ($row in $apis) {
    if ($apiMap.ContainsKey($row.LegacyEndpoint)) {
        $v = $apiMap[$row.LegacyEndpoint]
        Set-ApiRow $row $v[0] $v[1] $v[2] $v[3]
        $apiUpdated++
        continue
    }
    if ($manageCardApiMap.ContainsKey($row.LegacyEndpoint)) {
        $v = $manageCardApiMap[$row.LegacyEndpoint]
        Set-ApiRow $row 'member-card' 'REPLACE' $v[0] $v[1]
        $apiUpdated++
        continue
    }
    if ($memberCardApiMap.ContainsKey($row.LegacyEndpoint)) {
        $v = $memberCardApiMap[$row.LegacyEndpoint]
        Set-ApiRow $row $v[0] $v[1] $v[2] $v[3]
        $apiUpdated++
    }
}

if ($pageUpdated -ne $pageMap.Count) { throw "Expected to update $($pageMap.Count) pages, updated $pageUpdated." }

$pages | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $pageFile
$apis | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $apiFile

$pageHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $pageFile).Hash
$apiHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $apiFile).Hash
$pagesUnreviewed = @($pages | Where-Object Disposition -eq 'UNREVIEWED').Count
$apisUnreviewed = @($apis | Where-Object Disposition -eq 'UNREVIEWED').Count

[pscustomobject]@{
    PagesReviewed = $pageUpdated
    ApisReviewed = $apiUpdated
    PagesUnreviewed = $pagesUnreviewed
    ApisUnreviewed = $apisUnreviewed
    PagesHash = $pageHash
    ApisHash = $apiHash
}
