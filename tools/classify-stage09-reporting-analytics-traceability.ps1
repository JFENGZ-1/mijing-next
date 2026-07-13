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
    'pages/report/report' = @(
        'reporting', 'MERGE', 'report.dashboard.summary', 'AC-09-01',
        'Staff report tab hub: mainpage KPIs (totalProfit, month12 chart), computeAgain daily recount gate. Evidence: 管理端/pages/report/report.js.'
    )
    'pageReport/income/businessData' = @(
        'reporting', 'REPLACE', 'report.finance.profit.monthly', 'AC-09-02',
        'Monthly profit calendar via profitMonthList with dayList drill-down. Evidence: 管理端/pageReport/income/businessData.js.'
    )
    'pageReport/income/businessReportForm' = @(
        'reporting', 'REPLACE', 'report.finance.profit.summary', 'AC-09-03',
        'Rolling profit summary list via profitList + computeTime banner. Evidence: 管理端/pageReport/income/businessReportForm.js.'
    )
    'pageReport/income/businessDataDetail' = @(
        'reporting', 'REPLACE', 'report.finance.profit.daily-detail', 'AC-09-04',
        'Single-day profit line items via profitDayList. Evidence: 管理端/pageReport/income/businessDataDetail.js.'
    )
    'pageReport/course/courseReportForm' = @(
        'reporting', 'REPLACE', 'report.course.summary', 'AC-09-05',
        'Course rollup via sumMainCourseList. Evidence: 管理端/pageReport/course/courseReportForm.js.'
    )
    'pageReport/course/courseReportFormMonth' = @(
        'reporting', 'REPLACE', 'report.course.monthly-calendar', 'AC-09-06',
        'Monthly course calendar via CourseMonthList dayList. Evidence: 管理端/pageReport/course/courseReportFormMonth.js.'
    )
    'pageReport/course/courseReportFormDay' = @(
        'reporting', 'REPLACE', 'report.course.daily-breakdown', 'AC-09-07',
        'Same-day team+private course rows via TeamCourseDayList and PriCourseDayList. Evidence: 管理端/pageReport/course/courseReportFormDay.js.'
    )
    'pageReport/remind/initiationDay' = @(
        'notification', 'REPLACE', 'notification.reminder.anniversary', 'AC-09-08',
        'Member anniversary list via findUserAnniversary with config thresholds. Evidence: 管理端/pageReport/remind/initiationDay.js.'
    )
    'pageReport/remind/missClassRemind' = @(
        'notification', 'REPLACE', 'notification.reminder.no-class', 'AC-09-09',
        'Members with no recent class via findNoclassUserList. Evidence: 管理端/pageReport/remind/missClassRemind.js.'
    )
    'pageReport/remind/leavedDue' = @(
        'member-card', 'REPLACE', 'member-card.reminder.holiday', 'AC-09-10',
        'Holiday/frozen card reminder list via findHolidayCardList. Evidence: 管理端/pageReport/remind/leavedDue.js.'
    )
    'pageReport/remind/birthdayRemind' = @(
        'notification', 'REPLACE', 'notification.reminder.birthday', 'AC-09-11',
        'Upcoming birthdays via findUserBirthday. Evidence: 管理端/pageReport/remind/birthdayRemind.js.'
    )
    'pageReport/remind/visitorRemind' = @(
        'notification', 'REPLACE', 'notification.reminder.visitor', 'AC-09-12',
        'Visitor follow-up list via findUserByVisitor. Evidence: 管理端/pageReport/remind/visitorRemind.js.'
    )
    'pageReport/memberAnalyze/allMember' = @(
        'reporting', 'REPLACE', 'report.member-card.analytics', 'AC-09-13',
        'Member card cohort analytics via UserCardAnalyze modes (last class, stop, holiday). Evidence: 管理端/pageReport/memberAnalyze/allMember.js.'
    )
    'pageReport/rank/rankTop' = @(
        'reporting', 'REPLACE', 'report.order.rank', 'AC-09-14',
        'Recharge ranking via userOrderRank with date modes. Evidence: 管理端/pageReport/rank/rankTop.js.'
    )
    'pageReport/rank/courseRank' = @(
        'reporting', 'REPLACE', 'report.course.attendance-rank', 'AC-09-15',
        'Member course attendance ranking via userCourseRank. Evidence: 管理端/pageReport/rank/courseRank.js.'
    )
    'pageReport/rank/memberPointRank' = @(
        'reporting', 'REPLACE', 'report.points.rank', 'AC-09-16',
        'Points leaderboard via findUserPointList gated by getUserPointConfig.start. Evidence: 管理端/pageReport/rank/memberPointRank.js.'
    )
    'pageReport/rank/memberPointConfig' = @(
        'points', 'REPLACE', 'points.config', 'AC-09-17',
        'Points policy editor: getUserPointConfig/saveUserPointConfig; clearUserPoint UI rejected. Evidence: 管理端/pageReport/rank/memberPointConfig.js.'
    )
    'pageReport/rank/membershipRank' = @(
        'reporting', 'REPLACE', 'report.sales.rank', 'AC-09-18',
        'Sales consultant ranking via salerList sortMode. Evidence: 管理端/pageReport/rank/membershipRank.js.'
    )
    'pageReport/rank/membershipDetailRank' = @(
        'reporting', 'REPLACE', 'report.sales.detail', 'AC-09-19',
        'Per-sales-member drill-down via findUserDetailOfOneSaler. Evidence: 管理端/pageReport/rank/membershipDetailRank.js.'
    )
    'pageReport/rank/siteModifyLog' = @(
        'reporting', 'REPLACE', 'report.audit.site-modify', 'AC-09-20',
        'Site data change audit log via FindsiteModifyLog + getsiteModifyType + staff filter. Evidence: 管理端/pageReport/rank/siteModifyLog.js.'
    )
    'pageReport/courseAnalyze/teamCourseRank' = @(
        'reporting', 'REPLACE', 'report.course.group.rank', 'AC-09-21',
        'Group class analytics via findCourseRepListForWeb. Evidence: 管理端/pageReport/courseAnalyze/teamCourseRank.js.'
    )
    'pageReport/courseAnalyze/teamCourseDetailRank' = @(
        'reporting', 'REPLACE', 'report.course.group.detail', 'AC-09-22',
        'Group class session drill-down via findCourseRepListForWebDetail. Evidence: 管理端/pageReport/courseAnalyze/teamCourseDetailRank.js.'
    )
    'pageReport/courseAnalyze/privateCourseRank' = @(
        'reporting', 'REPLACE', 'report.course.private.rank', 'AC-09-23',
        'Private lesson analytics via findPrivateCourseList. Evidence: 管理端/pageReport/courseAnalyze/privateCourseRank.js.'
    )
    'pageReport/courseAnalyze/privateCourseDetailRank' = @(
        'reporting', 'REPLACE', 'report.course.private.detail', 'AC-09-24',
        'Private lesson drill-down via findPrivateCourseWebDetail. Evidence: 管理端/pageReport/courseAnalyze/privateCourseDetailRank.js.'
    )
    'pageReport/teacherMembership/staffCourse' = @(
        'payroll', 'REPLACE', 'payroll.coach.course-commission.report', 'AC-09-25',
        'Coach course commission rollup via findStaffCourseWeb. Evidence: 管理端/pageReport/teacherMembership/staffCourse.js.'
    )
    'pageReport/teacherMembership/personalSalarySetting' = @(
        'payroll', 'REPLACE', 'payroll.coach.config.list', 'AC-09-26',
        'Per-coach salary rule picker via SalaryStaffList + getSalaryConfig. Evidence: 管理端/pageReport/teacherMembership/personalSalarySetting.js.'
    )
    'pageReport/teacherMembership/personalSalaryType' = @(
        'payroll', 'REPLACE', 'payroll.coach.config.mode', 'AC-09-27',
        'Tenant coach payroll mode toggle via getSalaryConfig/saveSalaryConfig. Evidence: 管理端/pageReport/teacherMembership/personalSalaryType.js.'
    )
    'pageReport/teacherMembership/memberSalaryType' = @(
        'payroll', 'REPLACE', 'payroll.sales.config.mode', 'AC-09-28',
        'Sales payroll mode via getMemeberSalaryConfig/saveMemeberSalaryConfig. Evidence: 管理端/pageReport/teacherMembership/memberSalaryType.js.'
    )
    'pageReport/teacherMembership/personalSalaryFixedClassHours' = @(
        'payroll', 'REPLACE', 'payroll.coach.rule.fixed-hours', 'AC-09-29',
        'Fixed class-hour rule matrix via getSalaryConfigOfOneStaff/saveSalaryConfigOfOneStaff. Evidence: 管理端/pageReport/teacherMembership/personalSalaryFixedClassHours.js.'
    )
    'pageReport/teacherMembership/personalSalaryFixedPersonTime' = @(
        'payroll', 'REPLACE', 'payroll.coach.rule.fixed-headcount', 'AC-09-30',
        'Fixed headcount rule matrix via getSalaryConfigOfOneStaff/saveSalaryConfigOfOneStaff. Evidence: 管理端/pageReport/teacherMembership/personalSalaryFixedPersonTime.js.'
    )
    'pageReport/teacherMembership/personalSalaryFixedCourseAmount' = @(
        'payroll', 'REPLACE', 'payroll.coach.rule.fixed-amount', 'AC-09-31',
        'Fixed amount-per-course rule matrix via getSalaryConfigOfOneStaff/saveSalaryConfigOfOneStaff. Evidence: 管理端/pageReport/teacherMembership/personalSalaryFixedCourseAmount.js.'
    )
    'pageReport/teacherMembership/memberShipSalaryDetail' = @(
        'payroll', 'REPLACE', 'payroll.sales.detail', 'AC-09-32',
        'Sales payroll line items via findSaleManSalary_user and findSaleManSalary_card. Evidence: 管理端/pageReport/teacherMembership/memberShipSalaryDetail.js.'
    )
    'pageReport/teacherMembership/personalSalaryDetail' = @(
        'payroll', 'REPLACE', 'payroll.coach.detail', 'AC-09-33',
        'Coach payroll appointments via findStaffTeamAppointment and findStaffPrivateAppointment. Evidence: 管理端/pageReport/teacherMembership/personalSalaryDetail.js.'
    )
    'pageReport/userCost/userCost' = @(
        'reporting', 'REPLACE', 'report.member.cost', 'AC-09-34',
        'Member cost/consumption ranking via findUserCostForWeb sortId paging. Evidence: 管理端/pageReport/userCost/userCost.js.'
    )
    'pageReport/component/head-hint/head-hint' = @(
        'payroll', 'MERGE', 'payroll.recompute.banner', 'AC-09-35',
        'Shared salary recompute banner triggering ReComputeSalary or sumSaleSalary. Evidence: 管理端/pageReport/component/head-hint/head-hint.js.'
    )
    'pageReport/coach/classStatistics' = @(
        'reporting', 'MERGE', 'report.coach.monthly-rank', 'AC-09-36',
        'Coach monthly ranking via linkage staffByMonth; duplicate of pageChain/courseStatistics. Evidence: 管理端/pageReport/coach/classStatistics.js.'
    )
    'pageReport/coach/detailed' = @(
        'reporting', 'MERGE', 'report.coach.appointment-detail', 'AC-09-37',
        'Coach appointment lines via linkage getOnestaffInMonthDetail/findPrivateAppontmentofStaffuserid; broken privateDetail route. Evidence: 管理端/pageReport/coach/detailed.js.'
    )
    'pageReport/teacherMembership/detailed' = @(
        'reporting', 'MERGE', 'report.coach.appointment-detail', 'AC-09-37',
        'Salary-context duplicate of coach detailed using report2 getOnestaffInMonthDetail variants. Evidence: 管理端/pageReport/teacherMembership/detailed.js.'
    )
    'pageReport/teacherMembership/memberShipSalary' = @(
        'payroll', 'REPLACE', 'payroll.sales.report', 'AC-09-38',
        'Sales payroll summary via findSaleManSalary. Evidence: 管理端/pageReport/teacherMembership/memberShipSalary.js.'
    )
    'pageReport/teacherMembership/personalSalary' = @(
        'payroll', 'REPLACE', 'payroll.coach.report', 'AC-09-39',
        'Coach payroll summary via findWebstaffSalaryList. Evidence: 管理端/pageReport/teacherMembership/personalSalary.js.'
    )
    'pageServer/index' = @(
        'platform-billing', 'MERGE', 'platform.subscription.hub', 'AC-09-40',
        'SaaS service hub navigation to order/agreement/help; no write APIs on page. Evidence: 管理端/pageServer/index.js.'
    )
    'pageServer/serve-agreement' = @(
        'platform-billing', 'REPLACE', 'platform.subscription.agreement', 'AC-09-41',
        'Platform service agreement HTML via getAgreement. Evidence: 管理端/pageServer/serve-agreement.js.'
    )
    'pageServer/videoHelp/videoHelp' = @(
        'platform-billing', 'KEEP', 'platform.help.videos', 'AC-09-42',
        'Static WeChat channel video help list; no backend catalog API. Evidence: 管理端/pageServer/videoHelp/videoHelp.js.'
    )
    'pageChain/brand/index' = @(
        'org', 'REPLACE', 'org.brand.upsert', 'AC-09-43',
        'Chain union brand name/logo via saveLinkInfo. Evidence: 管理端/pageChain/brand/index.js.'
    )
    'pageChain/instructions/index' = @(
        'org', 'KEEP', 'org.chain.onboarding', 'AC-09-44',
        'Static chain onboarding illustrations; no API calls. Evidence: 管理端/pageChain/instructions/index.js.'
    )
    'pageChain/configStaff/index' = @(
        'staff', 'MERGE', 'staff.directory.list', 'AC-09-45',
        'Chain staff roster via linkage getAllStaff. Evidence: 管理端/pageChain/configStaff/index.js.'
    )
    'pageChain/configStaff/staff-edit' = @(
        'staff', 'MERGE', 'staff.upsert', 'AC-09-46',
        'Chain staff create/edit via linkage savestaff and changeStatus. Evidence: 管理端/pageChain/configStaff/staff-edit.js.'
    )
    'pageChain/courseStatistics/index' = @(
        'reporting', 'MERGE', 'report.coach.monthly-rank', 'AC-09-36',
        'Chain duplicate coach monthly stats via staffByMonth. Evidence: 管理端/pageChain/courseStatistics/index.js.'
    )
    'pageChain/courseStatistics/detailed' = @(
        'reporting', 'MERGE', 'report.coach.appointment-detail', 'AC-09-37',
        'Chain duplicate coach appointment detail. Evidence: 管理端/pageChain/courseStatistics/detailed.js.'
    )
    'pageChain/storesManagement/index' = @(
        'org', 'REPLACE', 'org.chain.sites.list', 'AC-09-47',
        'Linked branch list via getLinkSite; deleteSiteBysiteOwner requires adversarial review. Evidence: 管理端/pageChain/storesManagement/index.js.'
    )
    'pageConfig/shopReport/index' = @(
        'export', 'MERGE', 'export.job.hub', 'AC-09-48',
        'Member/card export job launcher + findExportLog history. Evidence: 管理端/pageConfig/shopReport/index.js.'
    )
}

$apiMap = @{
    '/b/export/exportuser' = @(
        'export', 'REPLACE', 'export.member.export', 'AC-09-48',
        'Async member PII export job from shopReport; requires operator audit and scoped fields. Evidence: 管理端/pageConfig/shopReport/index.js.'
    )
    '/b/export/findExportLog' = @(
        'export', 'REPLACE', 'export.job.list', 'AC-09-48',
        'Paged export job history for member/card exports. Evidence: 管理端/pageConfig/shopReport/index.js.'
    )
    '/b/linkage/changeStatus' = @(
        'staff', 'MERGE', 'staff.lifecycle.status', 'AC-09-46',
        'Chain staff enable/disable/delete status change. Evidence: 管理端/pageChain/configStaff/staff-edit.js.'
    )
    '/b/linkage/deleteSiteBysiteOwner' = @(
        'org', 'REPLACE', 'org.chain.site.remove', 'AC-09-47',
        'Remove linked branch by siteId fingerprint; must be owner-only with cascade policy, not silent delete. Evidence: 管理端/pageChain/storesManagement/index.js.'
    )
    '/b/linkage/detailreport' = @(
        'reporting', 'MERGE', 'order.report.card-sales.detail', 'AC-09-49',
        'Chain card sales detail tab via detailreport. Evidence: 管理端/pageChain/cardStatistics/index.js.'
    )
    '/b/linkage/findPrivateAppontmentofStaffuserid' = @(
        'reporting', 'MERGE', 'reporting.staff-private-appointments', 'AC-09-37',
        'Private appointment lines for coach detail pages. Evidence: pageReport/coach/detailed.js, pageChain/courseStatistics/detailed.js.'
    )
    '/b/linkage/getAllStaff' = @(
        'staff', 'MERGE', 'staff.directory.list', 'AC-09-45',
        'Triplicate staff directory export; merge with /b/staff/getAll and /b/report2/getAllStaff. Evidence: pageChain/configStaff/index.js.'
    )
    '/b/linkage/getLinkSite' = @(
        'org', 'REPLACE', 'org.chain.sites.list', 'AC-09-47',
        'Linked branch roster for storesManagement. Evidence: 管理端/pageChain/storesManagement/index.js.'
    )
    '/b/linkage/getOnestaffInMonthDetail' = @(
        'reporting', 'MERGE', 'report.coach.appointment-lines', 'AC-09-37',
        'Per-coach monthly appointment lines; duplicate report2/getOnestaffInMonthDetail. Evidence: pageReport/coach/detailed.js.'
    )
    '/b/linkage/getOnestaffInMonthSum' = @(
        'reporting', 'MERGE', 'report.coach.appointment-summary', 'AC-09-37',
        'Per-coach monthly appointment summary tab. Evidence: pageReport/coach/detailed.js.'
    )
    '/b/linkage/mainreport' = @(
        'reporting', 'MERGE', 'order.report.card-sales.summary', 'AC-09-49',
        'Chain card sales rollup mainreport. Evidence: 管理端/pageChain/cardStatistics/index.js.'
    )
    '/b/linkage/saveLinkInfo' = @(
        'org', 'REPLACE', 'org.brand.upsert', 'AC-09-43',
        'Persist union brand unName/unLogo. Evidence: 管理端/pageChain/brand/index.js.'
    )
    '/b/linkage/savestaff' = @(
        'staff', 'MERGE', 'staff.upsert', 'AC-09-46',
        'Chain duplicate staff save. Evidence: 管理端/pageChain/configStaff/staff-edit.js.'
    )
    '/b/linkage/staffByMonth' = @(
        'reporting', 'MERGE', 'report.coach.monthly-rank', 'AC-09-36',
        'Coach monthly ranking list. Evidence: pageReport/coach/classStatistics.js, pageChain/courseStatistics/index.js.'
    )
    '/b/platform/getAgreement' = @(
        'platform-billing', 'REPLACE', 'platform.subscription.agreement', 'AC-09-41',
        'Platform SaaS agreement HTML. Evidence: 管理端/pageServer/serve-agreement.js.'
    )
    '/b/platform/getSiteInfo' = @(
        'platform-billing', 'MERGE', 'platform.subscription.site-status', 'AC-09-40',
        'Tenant SaaS expiry/service status; also used by expiredAlert and post-pay refresh. Evidence: components/expiredAlert/expiredAlert.js, pageServer/order.js.'
    )
    '/b/platform/pricelist' = @(
        'platform-billing', 'REPLACE', 'platform.subscription.pricing', 'AC-09-40',
        'SaaS renewal package list for WeChat pay. Evidence: 管理端/pageServer/order.js.'
    )
    '/b/platform/submitSecretkey' = @(
        'platform-billing', 'REPLACE', 'platform.subscription.license-key', 'AC-09-40',
        'Offline license key activation path on order page. Evidence: 管理端/pageServer/order.js.'
    )
    '/b/report2/CourseMonthList' = @(
        'reporting', 'REPLACE', 'report.course.monthly-calendar', 'AC-09-06',
        'Monthly course calendar data source. Evidence: 管理端/pageReport/course/courseReportFormMonth.js.'
    )
    '/b/report2/findCourseRepListForWeb' = @(
        'reporting', 'REPLACE', 'report.course.group.rank', 'AC-09-21',
        'Group course analytics list. Evidence: 管理端/pageReport/courseAnalyze/teamCourseRank.js.'
    )
    '/b/report2/findCourseRepListForWebDetail' = @(
        'reporting', 'REPLACE', 'report.course.group.detail', 'AC-09-22',
        'Group course session drill-down. Evidence: 管理端/pageReport/courseAnalyze/teamCourseDetailRank.js.'
    )
    '/b/report2/findNoclassUserList' = @(
        'notification', 'REPLACE', 'notification.reminder.no-class.list', 'AC-09-09',
        'No-class reminder member list. Evidence: 管理端/pageReport/remind/missClassRemind.js.'
    )
    '/b/report2/findPrivateAppontmentofStaffuserid' = @(
        'reporting', 'MERGE', 'reporting.staff-private-appointments', 'AC-09-37',
        'Report2 duplicate of linkage private appointment query. Evidence: pageReport/teacherMembership/detailed.js.'
    )
    '/b/report2/findPrivateCourseList' = @(
        'reporting', 'REPLACE', 'report.course.private.rank', 'AC-09-23',
        'Private course analytics list. Evidence: 管理端/pageReport/courseAnalyze/privateCourseRank.js.'
    )
    '/b/report2/findPrivateCourseWebDetail' = @(
        'reporting', 'REPLACE', 'report.course.private.detail', 'AC-09-24',
        'Private course drill-down. Evidence: 管理端/pageReport/courseAnalyze/privateCourseDetailRank.js.'
    )
    '/b/report2/findSaleManSalary' = @(
        'payroll', 'REPLACE', 'payroll.sales.report', 'AC-09-38',
        'Sales payroll summary aggregate. Evidence: 管理端/pageReport/teacherMembership/memberShipSalary.js.'
    )
    '/b/report2/findSaleManSalary_card' = @(
        'payroll', 'REPLACE', 'payroll.sales.detail.cards', 'AC-09-32',
        'Sales payroll card-sale lines. Evidence: 管理端/pageReport/teacherMembership/memberShipSalaryDetail.js.'
    )
    '/b/report2/findSaleManSalary_user' = @(
        'payroll', 'REPLACE', 'payroll.sales.detail.members', 'AC-09-32',
        'Sales payroll member-sale lines. Evidence: 管理端/pageReport/teacherMembership/memberShipSalaryDetail.js.'
    )
    '/b/report2/FindsiteModifyLog' = @(
        'reporting', 'REPLACE', 'report.audit.site-modify.list', 'AC-09-20',
        'Site modification audit log query. Evidence: 管理端/pageReport/rank/siteModifyLog.js.'
    )
    '/b/report2/findStaffCourseWeb' = @(
        'payroll', 'REPLACE', 'payroll.coach.course-commission.report', 'AC-09-25',
        'Coach course commission rollup. Evidence: 管理端/pageReport/teacherMembership/staffCourse.js.'
    )
    '/b/report2/findUserAnniversary' = @(
        'notification', 'REPLACE', 'notification.reminder.anniversary.list', 'AC-09-08',
        'Anniversary reminder list. Evidence: 管理端/pageReport/remind/initiationDay.js.'
    )
    '/b/report2/findUserBirthday' = @(
        'notification', 'REPLACE', 'notification.reminder.birthday.list', 'AC-09-11',
        'Birthday reminder list. Evidence: 管理端/pageReport/remind/birthdayRemind.js.'
    )
    '/b/report2/findUserByVisitor' = @(
        'notification', 'REPLACE', 'notification.reminder.visitor.list', 'AC-09-12',
        'Visitor follow-up list. Evidence: 管理端/pageReport/remind/visitorRemind.js.'
    )
    '/b/report2/findUserCostForWeb' = @(
        'reporting', 'REPLACE', 'report.member.cost.list', 'AC-09-34',
        'Member cost ranking list. Evidence: 管理端/pageReport/userCost/userCost.js.'
    )
    '/b/report2/findUserDetailOfOneSaler' = @(
        'reporting', 'REPLACE', 'report.sales.detail', 'AC-09-19',
        'Per-sales-consultant member sales drill-down. Evidence: 管理端/pageReport/rank/membershipDetailRank.js.'
    )
    '/b/report2/findWebstaffSalaryList' = @(
        'payroll', 'REPLACE', 'payroll.coach.report', 'AC-09-39',
        'Coach payroll summary list. Evidence: 管理端/pageReport/teacherMembership/personalSalary.js.'
    )
    '/b/report2/getAllStaff' = @(
        'staff', 'MERGE', 'staff.directory.list', 'AC-09-45',
        'Triplicate staff directory used by siteModifyLog filter. Evidence: 管理端/pageReport/rank/siteModifyLog.js.'
    )
    '/b/report2/getMemeberSalaryConfig' = @(
        'payroll', 'REPLACE', 'payroll.sales.config.get', 'AC-09-28',
        'Sales payroll mode config read. Evidence: 管理端/pageReport/teacherMembership/memberSalaryType.js.'
    )
    '/b/report2/getOnestaffInMonthDetail' = @(
        'reporting', 'MERGE', 'report.coach.appointment-lines', 'AC-09-37',
        'Report2 duplicate coach appointment lines. Evidence: pageReport/teacherMembership/detailed.js.'
    )
    '/b/report2/getOnestaffInMonthSum' = @(
        'reporting', 'MERGE', 'report.coach.appointment-summary', 'AC-09-37',
        'Report2 duplicate coach appointment summary. Evidence: pageReport/teacherMembership/detailed.js.'
    )
    '/b/report2/getSalaryConfig' = @(
        'payroll', 'REPLACE', 'payroll.coach.config.get', 'AC-09-27',
        'Tenant coach payroll config read. Evidence: 管理端/pageReport/teacherMembership/personalSalaryType.js.'
    )
    '/b/report2/getSalaryConfigOfOneStaff' = @(
        'payroll', 'REPLACE', 'payroll.coach.rule.get', 'AC-09-29',
        'Per-coach salary rule matrix read. Evidence: personalSalaryFixedClassHours.js.'
    )
    '/b/report2/getsiteModifyType' = @(
        'reporting', 'REPLACE', 'report.audit.site-modify.types', 'AC-09-20',
        'Audit log type filter metadata. Evidence: 管理端/pageReport/rank/siteModifyLog.js.'
    )
    '/b/report2/mainpage' = @(
        'reporting', 'REPLACE', 'report.dashboard.summary', 'AC-09-01',
        'Report tab KPI payload. Evidence: 管理端/pages/report/report.js.'
    )
    '/b/report2/PriCourseDayList' = @(
        'reporting', 'REPLACE', 'report.course.daily-breakdown.private', 'AC-09-07',
        'Private course rows for selected day. Evidence: 管理端/pageReport/course/courseReportFormDay.js.'
    )
    '/b/report2/profitDayList' = @(
        'reporting', 'REPLACE', 'report.finance.profit.daily-detail', 'AC-09-04',
        'Daily profit line items. Evidence: 管理端/pageReport/income/businessDataDetail.js.'
    )
    '/b/report2/profitList' = @(
        'reporting', 'REPLACE', 'report.finance.profit.summary', 'AC-09-03',
        'Profit summary list. Evidence: 管理端/pageReport/income/businessReportForm.js.'
    )
    '/b/report2/profitMonthList' = @(
        'reporting', 'REPLACE', 'report.finance.profit.monthly', 'AC-09-02',
        'Monthly profit calendar. Evidence: 管理端/pageReport/income/businessData.js.'
    )
    '/b/report2/ReComputeSalary' = @(
        'payroll', 'REPLACE', 'payroll.recompute.job', 'AC-09-35',
        'Client-triggered global coach payroll recompute; replace with authorized scoped async job + audit. Evidence: head-hint.js and multiple report pages.'
    )
    '/b/report2/SalaryStaffList' = @(
        'payroll', 'REPLACE', 'payroll.coach.config.list', 'AC-09-26',
        'Coach list for per-staff salary config. Evidence: 管理端/pageReport/teacherMembership/personalSalarySetting.js.'
    )
    '/b/report2/salerList' = @(
        'reporting', 'REPLACE', 'report.sales.rank', 'AC-09-18',
        'Sales consultant ranking. Evidence: 管理端/pageReport/rank/membershipRank.js.'
    )
    '/b/report2/saveMemeberSalaryConfig' = @(
        'payroll', 'REPLACE', 'payroll.sales.config.save', 'AC-09-28',
        'Sales payroll mode config write. Evidence: 管理端/pageReport/teacherMembership/memberSalaryType.js.'
    )
    '/b/report2/saveSalaryConfig' = @(
        'payroll', 'REPLACE', 'payroll.coach.config.save', 'AC-09-27',
        'Tenant coach payroll mode config write. Evidence: 管理端/pageReport/teacherMembership/personalSalaryType.js.'
    )
    '/b/report2/saveSalaryConfigOfOneStaff' = @(
        'payroll', 'REPLACE', 'payroll.coach.rule.save', 'AC-09-29',
        'Per-coach salary rule matrix write. Evidence: personalSalaryFixedClassHours.js.'
    )
    '/b/report2/sumMainCourseList' = @(
        'reporting', 'REPLACE', 'report.course.summary', 'AC-09-05',
        'Course rollup summary list. Evidence: 管理端/pageReport/course/courseReportForm.js.'
    )
    '/b/report2/sumSaleSalary' = @(
        'payroll', 'REPLACE', 'payroll.sales.aggregate.job', 'AC-09-35',
        'Client-triggered sales payroll aggregate job; replace with authorized async job. Evidence: head-hint.js.'
    )
    '/b/report2/TeamCourseDayList' = @(
        'reporting', 'REPLACE', 'report.course.daily-breakdown.group', 'AC-09-07',
        'Group course rows for selected day. Evidence: 管理端/pageReport/course/courseReportFormDay.js.'
    )
    '/b/report2/userCourseRank' = @(
        'reporting', 'REPLACE', 'report.course.attendance-rank', 'AC-09-15',
        'Member course attendance ranking. Evidence: 管理端/pageReport/rank/courseRank.js.'
    )
}

$targetPages = @($pages | Where-Object { $_.App -eq '管理端' -and $pageMap.ContainsKey($_.LegacyPath) })
if ($targetPages.Count -ne 51) { throw "Expected 51 Stage 09 pages, found $($targetPages.Count)." }
foreach ($row in $targetPages) {
    $value = $pageMap[$row.LegacyPath]
    Set-PageRow $row $value[0] $value[1] $value[2] $value[3] $value[4]
}

$targetApis = @($apis | Where-Object { $_.App -eq '管理端' -and $apiMap.ContainsKey($_.LegacyEndpoint) })
if ($targetApis.Count -ne 58) { throw "Expected 58 Stage 09 API rows, found $($targetApis.Count)." }
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
