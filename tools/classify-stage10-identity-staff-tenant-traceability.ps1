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
    'pages/start/index' = @(
        'identity', 'REPLACE', 'identity.deeplink.router', 'AC-10-01',
        'Cold-start deeplink router: scene/siteId/sid/go dispatches getStopInfo then routes to league class, PT detail, platform billing, or home. Evidence: 管理端/pages/start/index.js handleAddress().'
    )
    'pages/home/home' = @(
        'identity', 'REPLACE', 'staff.dashboard.home', 'AC-10-02',
        'Staff home tab: todayReport KPIs, saleCard feed, appointRecord list, venue switcher, SaaS expiry CTA via getSiteInfo. Evidence: 管理端/pages/home/home.js.'
    )
    'pages/shop/shop' = @(
        'tenant-config', 'MERGE', 'tenant.settings.hub', 'AC-10-03',
        'Shop tab settings hub linking pageConfig tools (membership fields, agreements, reminders, closures, exports) gated by shop_basics permission. Evidence: 管理端/pages/shop/shop.js baseConfig routes.'
    )
    'pages/login/login' = @(
        'identity', 'MERGE', 'identity.staff.login', 'AC-10-04',
        'Staff H5/MP login shell: wxAuthorize redirect, token handoff, getStopInfo dispatch, redirect guard. Evidence: 管理端/pages/login/login.js.'
    )
    'uview-ui/components/u-avatar-cropper/u-avatar-cropper' = @(
        'identity', 'MERGE', 'media.avatar.crop', 'AC-10-05',
        'Shared avatar crop utility navigated from staff profile, staff edit, store logo, CRM lead form. Evidence: staff-edit.js, personal.js, store-setting.js, pageMember/information/index.js.'
    )
    'uview-ui/components/u-avatar-cropper/u-avatar-croppershop' = @(
        'tenant-config', 'MERGE', 'tenant.branding.image-crop', 'AC-10-06',
        'Shop-carousel image crop variant for member mini-app home slides. Evidence: 管理端/pageConfig/memberConfigShow/editMenberConfigShow.js.'
    )
    'pages/shop/authorizationPage/info/index' = @(
        'identity', 'MERGE', 'identity.staff.onboarding.profile', 'AC-10-07',
        'First-visit avatar/nickname capture with aduserCount social proof; uploads via /common/uploadfile then routes to phone step. Evidence: 管理端/pages/shop/authorizationPage/info/index.js.'
    )
    'pages/shop/authorizationPage/phone/index' = @(
        'identity', 'MERGE', 'identity.staff.onboarding.phone', 'AC-10-08',
        'WeChat phone grant for staff onboarding via getWeixinPhoneNumber. Evidence: 管理端/pages/shop/authorizationPage/phone/index.js.'
    )
    'pagesImp/shop/setting/personal/personal' = @(
        'identity', 'REPLACE', 'staff.profile.self', 'AC-10-09',
        'Self-service staff profile: getMyInfo, avatar upload, updateMyInfo, loginout. Evidence: 管理端/pagesImp/shop/setting/personal/personal.js.'
    )
    'pagesImp/shop/setting/personal/nickname-edit' = @(
        'identity', 'MERGE', 'staff.profile.self', 'AC-10-09',
        'Nickname sub-editor calling updateMyInfo. Evidence: 管理端/pagesImp/shop/setting/personal/nickname-edit.js.'
    )
    'pagesImp/shop/setting/personal/phone-edit' = @(
        'identity', 'MERGE', 'staff.profile.self', 'AC-10-09',
        'Phone sub-editor via getWeixinPhoneNumber + updateMyInfo. Evidence: 管理端/pagesImp/shop/setting/personal/phone-edit.js.'
    )
    'pagesImp/shop/setting/store/store-setting' = @(
        'tenant-config', 'REPLACE', 'site.profile.form', 'AC-10-10',
        'Site profile editor: getSiteInfo, getConst city data, saveSiteInfo with open hours. Evidence: 管理端/pagesImp/shop/setting/store/store-setting.js.'
    )
    'pagesImp/shop/staff/staff' = @(
        'identity', 'REPLACE', 'staff.directory.list', 'AC-10-11',
        'Staff directory list via getAllStaff with invite/share deep link. Evidence: 管理端/pagesImp/shop/staff/staff.js.'
    )
    'pagesImp/shop/staff/staff-edit' = @(
        'identity', 'REPLACE', 'staff.directory.upsert', 'AC-10-12',
        'Create/edit staff: savestaff, findRoleList, findDefaultRole, changeSiteOwner, leaveWork, delstaff, checkExistPlan; staff row passed via URL not getOne API. Evidence: 管理端/pagesImp/shop/staff/staff-edit.js.'
    )
    'pagesImp/shop/staff/invited-share' = @(
        'identity', 'REPLACE', 'staff.invite.accept', 'AC-10-13',
        'Invite acceptance flow: getInviteData, getUserInfoBySign, getUnionId, acceptInvite, getStopInfo. Evidence: 管理端/pagesImp/shop/staff/invited-share.js.'
    )
    'pagesImp/shop/staff/components/permission-popup' = @(
        'identity', 'MERGE', 'staff.role.editor', 'AC-10-14',
        'Custom role permission matrix popup: findAllFunction + saveRole. Evidence: 管理端/pagesImp/shop/staff/components/permission-popup.js.'
    )
    'pagesImp/authorization/info/index' = @(
        'identity', 'MERGE', 'identity.staff.onboarding.profile', 'AC-10-07',
        'Subpackage duplicate of main-package staff onboarding profile step. Evidence: 管理端/pagesImp/authorization/info/index.js.'
    )
    'pagesImp/authorization/phone/index' = @(
        'identity', 'MERGE', 'identity.staff.onboarding.phone', 'AC-10-08',
        'Subpackage duplicate phone grant + invite accept path. Evidence: 管理端/pagesImp/authorization/phone/index.js.'
    )
    'pagesImp/authorization/error/index' = @(
        'identity', 'MERGE', 'identity.staff.onboarding.error', 'AC-10-15',
        'Invite/onboarding failure screen; retries getStopInfo. Evidence: 管理端/pagesImp/authorization/error/index.js.'
    )
    'pagesImp/authorization/success/index' = @(
        'identity', 'MERGE', 'identity.staff.onboarding.success', 'AC-10-16',
        'Post-invite success screen; dispatches getStopInfo then routes home. Evidence: 管理端/pagesImp/authorization/success/index.js.'
    )
    'pagesImp/QRcode/QRcode' = @(
        'identity', 'REJECT', '', 'AC-10-17',
        'Static WeChat official-account follow QR (qr_code.png assets only); no API and no tenant-specific payload. Evidence: 管理端/pagesImp/QRcode/QRcode.js, QRcode.wxml.'
    )
    'pageReport/league/statistics' = @(
        'reporting', 'REJECT', '', 'AC-10-18',
        'Orphan report page: registered in app.json but no inbound navigation; calls ghost export getTeamArrangeCount absent from api-catalog.csv. Evidence: pageReport/league/statistics.js; grep shows no external navigateTo.'
    )
    'pageReport/league/details' = @(
        'reporting', 'REJECT', '', 'AC-10-19',
        'Orphan drill-down only reachable from statistics; calls ghost export getOnestaffInMonthDetailByCourseid absent from api-catalog.csv. Evidence: pageReport/league/details.js.'
    )
    'pageConfig/membership/index' = @(
        'tenant-config', 'REPLACE', 'tenant.crm.field-config', 'AC-10-20',
        'CRM lead form required-field toggles via getuserFieldSetting/saveuserFieldSetting. Evidence: 管理端/pageConfig/membership/index.js.'
    )
    'pageConfig/embershipAgreement/index' = @(
        'tenant-config', 'REPLACE', 'tenant.legal.membership-agreement.read', 'AC-10-21',
        'Read membership agreement rich text via getuserProtocolSetting. Evidence: 管理端/pageConfig/embershipAgreement/index.js.'
    )
    'pageConfig/embershipAgreement/editAgreement' = @(
        'tenant-config', 'REPLACE', 'tenant.legal.membership-agreement.write', 'AC-10-22',
        'Edit membership agreement via saveuserPtotocolSetting. Evidence: 管理端/pageConfig/embershipAgreement/editAgreement.js.'
    )
    'pageConfig/memberConfigCourse/index' = @(
        'tenant-config', 'MERGE', 'tenant.member.onboarding-help', 'AC-10-23',
        'Static how-to-book imagery for member mini-app; no backend calls. Evidence: 管理端/pageConfig/memberConfigCourse/index.js.'
    )
    'pageConfig/paySetting/index' = @(
        'tenant-config', 'MERGE', 'tenant.payment.marketing', 'AC-10-24',
        'Static payment-setting marketing cards; no API in page script. Evidence: 管理端/pageConfig/paySetting/index.js.'
    )
    'pageConfig/memberConfigKindReminder/index' = @(
        'tenant-config', 'REPLACE', 'tenant.member.warm-hint.read', 'AC-10-25',
        'Member warm-hint list via getwarmHint. Evidence: 管理端/pageConfig/memberConfigKindReminder/index.js.'
    )
    'pageConfig/memberConfigShow/index' = @(
        'tenant-config', 'REPLACE', 'tenant.member.home-carousel.read', 'AC-10-26',
        'Member home carousel preview via getsavefaceimage. Evidence: 管理端/pageConfig/memberConfigShow/index.js.'
    )
    'pageConfig/memberConfigShow/editMenberConfigShow' = @(
        'tenant-config', 'REPLACE', 'tenant.member.home-carousel.write', 'AC-10-27',
        'Carousel image editor via savefaceimage + u-avatar-croppershop. Evidence: 管理端/pageConfig/memberConfigShow/editMenberConfigShow.js.'
    )
    'pageConfig/memberConfigKindReminder/editMemberConfigKindReminder' = @(
        'tenant-config', 'MERGE', 'tenant.member.warm-hint.write', 'AC-10-28',
        'Single warm-hint editor via getwarmHint/saveWarmHint. Evidence: 管理端/pageConfig/memberConfigKindReminder/editMemberConfigKindReminder.js.'
    )
    'pageConfig/displayHide/index' = @(
        'tenant-config', 'REPLACE', 'tenant.member.miniapp-layout', 'AC-10-29',
        'Member mini-app tab/module visibility matrix via getClientConfig/saveClientConfig. Evidence: 管理端/pageConfig/displayHide/index.js.'
    )
    'pageConfig/stopDoing/index' = @(
        'tenant-config', 'REPLACE', 'tenant.site.closure-calendar', 'AC-10-30',
        'Site closure/holiday list via findStopbusinessofSite. Evidence: 管理端/pageConfig/stopDoing/index.js.'
    )
    'pageConfig/coachLeave/index' = @(
        'tenant-config', 'REPLACE', 'tenant.staff.vacation.list', 'AC-10-31',
        'Coach vacation rollup via getMainHolidayList. Evidence: 管理端/pageConfig/coachLeave/index.js.'
    )
    'pageConfig/coachLeave/coachLeave' = @(
        'tenant-config', 'MERGE', 'tenant.staff.vacation.detail', 'AC-10-32',
        'Per-coach vacation lines via getHolidayOfOneStaff. Evidence: 管理端/pageConfig/coachLeave/coachLeave.js.'
    )
    'pageConfig/coachLeave/editLeave' = @(
        'tenant-config', 'MERGE', 'tenant.staff.vacation.write', 'AC-10-33',
        'Create/edit coach vacation via saveVacation/delHolidayInfo. Evidence: 管理端/pageConfig/coachLeave/editLeave.js.'
    )
    'pageConfig/stopDoing/editStopDoing' = @(
        'tenant-config', 'MERGE', 'tenant.site.closure-calendar.write', 'AC-10-34',
        'Create/edit closure window via stopbusiness save/deletes. Evidence: 管理端/pageConfig/stopDoing/editStopDoing.js.'
    )
    'pageConfig/reminderSettings/index' = @(
        'notification', 'REPLACE', 'notification.channel.config', 'AC-10-35',
        'Staff/member notification channel toggles via getHintSetting/saveHintSetting and manager picker getHintManagerConfig. Evidence: 管理端/pageConfig/reminderSettings/index.js.'
    )
    'pageConfig/notificationManagement/index' = @(
        'notification', 'REPLACE', 'notice.announcement.list', 'AC-10-36',
        'Paginated site announcement list via getNoticeList. Evidence: 管理端/pageConfig/notificationManagement/index.js.'
    )
    'pageConfig/notificationManagement/notification' = @(
        'notification', 'MERGE', 'notice.announcement.editor', 'AC-10-37',
        'Create/edit/delete announcement via notice save/deletes. Evidence: 管理端/pageConfig/notificationManagement/notification.js.'
    )
}

$apiMap = @{
    '/b/card/getAllCardForHasAgreement' = @(
        'member-card', 'REJECT', '', 'AC-05-99',
        'Exported in vendor bundle only; no static call site in 管理端 or 会员端 compiled JS. Stage 05 blocker confirmed. Evidence: grep getAllCardForHasAgreement → vendor.js only.'
    )
    '/b/site/getSiteInfo' = @(
        'tenant-config', 'REPLACE', 'site.profile.read', 'AC-10-10',
        'Site profile read used by home, shop, store-setting, start deeplink, expiredAlert. Evidence: pages/home/home.js, pages/shop/shop.js, store-setting.js.'
    )
    '/b/site/mergeOpentime' = @(
        'tenant-config', 'REJECT', '', 'AC-10-38',
        'Exported in vendor bundle only; no static call site. Open hours saved inline via saveSiteInfo.openTimeList. Evidence: grep mergeOpentime → vendor.js only.'
    )
    '/b/site/saveSiteInfo' = @(
        'tenant-config', 'REPLACE', 'site.profile.update', 'AC-10-10',
        'Site profile write from store-setting including hours and branding. Evidence: 管理端/pagesImp/shop/setting/store/store-setting.js.'
    )
    '/b/staff/acceptInvite' = @(
        'identity', 'REPLACE', 'staff.invite.accept', 'AC-10-13',
        'Accept staff invite after phone/union binding. Evidence: invited-share.js, pagesImp/authorization/phone/index.js.'
    )
    '/b/staff/changeSiteOwner' = @(
        'identity', 'REPLACE', 'staff.directory.transfer-ownership', 'AC-10-12',
        'Transfer site ownership to another staff member. Evidence: 管理端/pagesImp/shop/staff/staff-edit.js.'
    )
    '/b/staff/checkExistPlan' = @(
        'identity', 'REPLACE', 'staff.departure.preflight', 'AC-10-12',
        'Preflight future bookings before staff departure/delete. Evidence: staff-edit.js checkExistPlan().'
    )
    '/b/staff/delstaff' = @(
        'identity', 'REPLACE', 'staff.directory.delete', 'AC-10-12',
        'Hard-delete staff when deldata flag set. Evidence: staff-edit.js delstaff().'
    )
    '/b/staff/findAllFunction' = @(
        'identity', 'REPLACE', 'staff.permission.catalog', 'AC-10-14',
        'Permission function tree for custom roles. Evidence: permission-popup.js, staff-edit.js.'
    )
    '/b/staff/findDefaultRole' = @(
        'identity', 'REPLACE', 'staff.role.default-template', 'AC-10-12',
        'Default role template when creating staff. Evidence: staff-edit.js findDefaultRole().'
    )
    '/b/staff/findRoleList' = @(
        'identity', 'REPLACE', 'staff.role.list', 'AC-10-12',
        'Selectable roles for one staff user. Evidence: staff-edit.js findRoleList().'
    )
    '/b/staff/getAll' = @(
        'identity', 'REPLACE', 'staff.directory.list', 'AC-10-11',
        'Staff directory list; triplicate with /b/linkage and /b/report2 exports — collapse to one scoped operation. Evidence: pagesImp/shop/staff/staff.js.'
    )
    '/b/staff/getInviteData' = @(
        'identity', 'REPLACE', 'staff.invite.preview', 'AC-10-13',
        'Invite banner metadata by staffuserid. Evidence: invited-share.js getInviteData().'
    )
    '/b/staff/getOne' = @(
        'identity', 'REJECT', '', 'AC-10-39',
        'Exported as getUserOne in vendor only; staff-edit loads model from URL param staffone JSON, not this API. Evidence: staff-edit.js onLoad staffone branch; grep getUserOne → vendor.js only.'
    )
    '/b/staff/getUserInfoBySign?deviceId=' = @(
        'identity', 'REPLACE', 'staff.invite.sign-preview', 'AC-10-13',
        'Resolve invitee profile from invite sign token. Evidence: invited-share.js getUserInfoBySign().'
    )
    '/b/staff/leaveWork' = @(
        'identity', 'REPLACE', 'staff.departure.soft', 'AC-10-12',
        'Soft-departure (staffStatus=0) with booking preflight. Evidence: staff-edit.js leaveWorkstaff().'
    )
    '/b/staff/saveRole' = @(
        'identity', 'REPLACE', 'staff.role.upsert', 'AC-10-14',
        'Save custom role permission matrix. Evidence: permission-popup.js, staff-edit child component.'
    )
    '/b/staff/savestaff' = @(
        'identity', 'REPLACE', 'staff.directory.upsert', 'AC-10-12',
        'Create/update staff profile and role binding. Evidence: staff-edit.js savestaff().'
    )
    '/b/staffuser/aduserCount' = @(
        'identity', 'MERGE', 'identity.onboarding.social-proof', 'AC-10-07',
        'Registered-user count shown on onboarding screens. Evidence: authorizationPage/info/index.js, pagesImp/authorization/info/index.js.'
    )
    '/b/staffuser/getMyInfo' = @(
        'identity', 'REPLACE', 'staff.profile.self.read', 'AC-10-09',
        'Load current staff profile for personal settings. Evidence: personal.js.'
    )
    '/b/staffuser/getMySiteList' = @(
        'tenant-config', 'REPLACE', 'site.context.list', 'AC-10-40',
        'Multi-site picker for staff context. Evidence: pagesCourse/home/venue.js, pageChain/storesManagement/index.js.'
    )
    '/b/staffuser/getSiteConfig' = @(
        'tenant-config', 'REPLACE', 'tenant.feature-flags.read', 'AC-10-03',
        'Shop tab feature/module flags. Evidence: pages/shop/shop.js getSiteConfig().'
    )
    '/b/staffuser/loginout' = @(
        'identity', 'REPLACE', 'identity.session.logout', 'AC-10-09',
        'Staff logout from personal settings. Evidence: personal.js loginout().'
    )
    '/b/staffuser/selectPayList' = @(
        'identity', 'MERGE', 'staff.dashboard.sales-feed', 'AC-10-02',
        'Today card-sales feed on staff home (export name saleCard). Evidence: pages/home/home.js loadSaleCardRecord().'
    )
    '/b/staffuser/todayreport' = @(
        'identity', 'REPLACE', 'staff.dashboard.summary', 'AC-10-02',
        'Staff home KPI payload including softwareExpire. Evidence: pages/home/home.js loadTodayReport().'
    )
    '/b/staffuser/updateMyInfo' = @(
        'identity', 'REPLACE', 'staff.profile.self.update', 'AC-10-09',
        'Self-service staff profile updates. Evidence: personal.js, nickname-edit.js, phone-edit.js.'
    )
    '/b/staffuser/wxlogin' = @(
        'identity', 'REPLACE', 'identity.staff.session.bootstrap', 'AC-10-41',
        'Primary staff session bootstrap (store action getStopInfo); returns site context, permissions, dictVal. Evidence: pages/start/index.js, pages/login/components/mp-weixin.js.'
    )
    '/b/staffuser/wxlogin?deviceId=' = @(
        'identity', 'MERGE', 'identity.staff.session.bootstrap', 'AC-10-41',
        'Device-scoped variant of staff wxlogin/getStopInfo export. Evidence: vendor export duplicate; same store dispatch getStopInfo.'
    )
    '/common/const' = @(
        'platform', 'REPLACE', 'platform.constants.read', 'AC-10-10',
        'City/region constants for new site creation form. Evidence: store-setting.js getConst().'
    )
    '/common/dict' = @(
        'platform', 'REJECT', '', 'AC-10-42',
        'Exported as dictData in 管理端 vendor only; no static call site. uploadURL/default images consumed from dictVal populated by session bootstrap, not direct dict fetch. Evidence: grep dictData → vendor.js only.'
    )
    '/wx/getUnionId' = @(
        'identity', 'REPLACE', 'identity.wechat.unionid.resolve', 'AC-10-13',
        'UnionID resolution during staff invite acceptance. Evidence: invited-share.js, pagesImp/authorization/phone/index.js.'
    )
    '/wx/getWeixinPhoneNumber' = @(
        'identity', 'REPLACE', 'identity.wechat.phone.grant', 'AC-10-08',
        'WeChat encrypted phone grant for staff onboarding and profile phone edit. Evidence: authorization phone pages, phone-edit.js.'
    )
}

$targetPages = @($pages | Where-Object { $_.App -eq '管理端' -and $pageMap.ContainsKey($_.LegacyPath) })
if ($targetPages.Count -ne 41) { throw "Expected 41 Stage 10 pages, found $($targetPages.Count)." }
foreach ($row in $targetPages) {
    $value = $pageMap[$row.LegacyPath]
    Set-PageRow $row $value[0] $value[1] $value[2] $value[3] $value[4]
}

$targetApis = @($apis | Where-Object { $_.App -eq '管理端' -and $apiMap.ContainsKey($_.LegacyEndpoint) })
if ($targetApis.Count -ne 32) { throw "Expected 32 Stage 10 API rows, found $($targetApis.Count)." }
foreach ($row in $targetApis) {
    $value = $apiMap[$row.LegacyEndpoint]
    Set-ApiRow $row $value[0] $value[1] $value[2] $value[3] $value[4]
}

$pages | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $pageFile
$apis | Export-Csv -NoTypeInformation -Encoding utf8 -LiteralPath $apiFile

function Get-FileSha256([string]$Path) {
    (Get-FileHash -Algorithm SHA256 -LiteralPath $Path).Hash
}

[pscustomobject]@{
    PagesRefined = $targetPages.Count
    ApisRefined = $targetApis.Count
    PagesUnreviewed = @($pages | Where-Object Disposition -eq 'UNREVIEWED').Count
    ApisUnreviewed = @($apis | Where-Object Disposition -eq 'UNREVIEWED').Count
    PagesSha256 = Get-FileSha256 $pageFile
    ApisSha256 = Get-FileSha256 $apiFile
}
