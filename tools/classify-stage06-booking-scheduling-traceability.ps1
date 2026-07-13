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
    'pages/course/course' = @(
        'booking', 'KEEP', 'booking.staff-daily-board',
        'Staff tab 课程: private coach lanes (findAllPrivateDrainerList) + group day grid (findTeamPlan) with venue closed overlay. Evidence: 管理端/pages/course/course.js.'
    )
    'pagesCourse/index/index' = @(
        'schedule', 'KEEP', 'schedule.editor',
        'Full timetable editor: scroll calendar (findPlan), add session (addCourse), copy/clear/download components. Evidence: 管理端/pagesCourse/index/index.js, components/course-scroll.js.'
    )
    'pagesCourse/index/kind-reminder' = @(
        'schedule', 'MERGE', 'schedule.copy-hint',
        'Copy-timetable reminder text editor via getPlanHint/savePlanHint. Evidence: 管理端/pagesCourse/index/kind-reminder.js.'
    )
    'pagesCourse/index/course-title' = @(
        'schedule', 'MERGE', 'schedule.display-title',
        'Timetable header title via getPlanTitle/savePlanTitle. Evidence: 管理端/pagesCourse/index/course-title.js.'
    )
    'pagesCourse/index/course-option' = @(
        'schedule', 'MERGE', 'schedule.display-tags',
        'Advanced timetable tag/legend editor via getArrangeTagData/saveArrangeTagData. Evidence: 管理端/pagesCourse/index/course-option.js.'
    )
    'pagesCourse/index/all-course' = @(
        'schedule', 'KEEP', 'schedule.course-catalog',
        'Searchable course list (findallcourse) linking to per-course schedule management. Evidence: 管理端/pagesCourse/index/all-course.js.'
    )
    'pagesCourse/index/management-schedule' = @(
        'schedule', 'KEEP', 'schedule.batch-by-course',
        'Per-course batch suspend/unsuspend/delete/change via findPlanByCourseId and batch* APIs. Evidence: 管理端/pagesCourse/index/management-schedule.js.'
    )
    'pagesCourse/subject/subject-edit' = @(
        'schedule', 'MERGE', 'schedule.session-editor',
        'Edit one scheduled session: getOne/getDetailByCourseid, updateCourse/updateAllCourse. Evidence: 管理端/pagesCourse/subject/subject-edit.js.'
    )
    'pagesCourse/personalTrainerDetails/index' = @(
        'booking', 'KEEP', 'booking.staff-private-detail',
        'Private coach appointment board: findOneDrainerDetail, findOneDrainerAppointment; cancel/absent/remark/reschedule. Evidence: 管理端/pagesCourse/personalTrainerDetails/index.js.'
    )
    'pagesCourse/leagueClassDetails/index' = @(
        'booking', 'KEEP', 'booking.staff-group-detail',
        'Group class roster: findOnePlan lists valid/line/cancel/absent; staff cancel, absent, remark, waitlist cancel, member booking popup. Evidence: 管理端/pagesCourse/leagueClassDetails/index.js.'
    )
    'pagesCourse/home/venue' = @(
        'site', 'MERGE', 'site.context-selector',
        'Site switcher inside course package via getMySiteList; not booking logic. Evidence: 管理端/pagesCourse/home/venue.js.'
    )
    'pageConfig/appointSetting/index' = @(
        'booking', 'KEEP', 'booking.policy-config',
        'Tenant booking rules: ahead time, cancel windows, auto-cancel under min students, absent penalties via getAllAppointConfig/saveAppointConfig. Evidence: 管理端/pageConfig/appointSetting/index.js.'
    )
    'pageConfig/appointment/index' = @(
        'sharing', 'MERGE', 'sharing.staff-miniapp-code',
        'Misnamed path: only createAppCode QR download/save; no appointment CRUD. Evidence: 管理端/pageConfig/appointment/index.js.'
    )
    'pagesImp/subject/subject' = @(
        'course-catalog', 'KEEP', 'course-catalog.hub',
        'Lists group (selectAllTeamCourse) and private (selecctAllPriCourse) course templates. Evidence: 管理端/pagesImp/subject/subject.js.'
    )
    'pagesImp/subject/subject-edit' = @(
        'course-catalog', 'KEEP', 'course-catalog.group.editor',
        'Group course template CRUD: saveTeamCourse, checkHasPlan, delCourse. Evidence: 管理端/pagesImp/subject/subject-edit.js.'
    )
    'pagesImp/subject/subject-personal-edit' = @(
        'course-catalog', 'KEEP', 'course-catalog.private.editor',
        'Private coach template CRUD: savePrivateCourse, delDrainer, upDrainerld. Evidence: 管理端/pagesImp/subject/subject-personal-edit.js.'
    )
}

$apiMap = @{
    '/b/arrange/batchChangeCourse' = @(
        'schedule', 'REPLACE', 'schedule.batch.change-course',
        'Batch retarget sessions to another course template. Evidence: pagesCourse/index/management-schedule.js.'
    )
    '/b/arrange/batchDeleteByCourseid' = @(
        'schedule', 'REPLACE', 'schedule.batch.delete-by-course',
        'Batch delete future sessions for one course. Evidence: management-schedule.js.'
    )
    '/b/arrange/batchDeleteByDate' = @(
        'schedule', 'REPLACE', 'schedule.batch.delete-by-date',
        'Batch delete sessions in date range. Evidence: clear-timetable.js.'
    )
    '/b/arrange/batchStopPlan' = @(
        'schedule', 'REPLACE', 'schedule.batch.suspend',
        'Batch suspend sessions. Evidence: management-schedule.js.'
    )
    '/b/arrange/batchStopPlanUndo' = @(
        'schedule', 'REPLACE', 'schedule.batch.unsuspend',
        'Batch lift suspension. Evidence: management-schedule.js.'
    )
    '/b/arrange/cancelstopOnePlan' = @(
        'schedule', 'REPLACE', 'schedule.session.unsuspend',
        'Lift single session suspension. Evidence: course-management.js.'
    )
    '/b/arrange/changeOtherCourse' = @(
        'schedule', 'REPLACE', 'schedule.session.change-course',
        'Swap course template on one session. Evidence: course-management.js.'
    )
    '/b/arrange/checkchangeOtherCourse' = @(
        'schedule', 'MERGE', 'schedule.session.change-course.preflight',
        'Preflight before course swap. Evidence: course-management.js.'
    )
    '/b/arrange/checkcopyPlan' = @(
        'schedule', 'MERGE', 'schedule.copy.preflight',
        'Preflight timetable copy. Evidence: copy-timetable.js.'
    )
    '/b/arrange/copyPlan' = @(
        'schedule', 'REPLACE', 'schedule.copy.execute',
        'Copy timetable between weeks/months. Evidence: copy-timetable.js.'
    )
    '/b/arrange/deleteOnePlan' = @(
        'schedule', 'REPLACE', 'schedule.session.delete',
        'Delete one session; may block when bookings exist (500 + modal). Evidence: course-management.js.'
    )
    '/b/arrange/findallcourse' = @(
        'schedule', 'REPLACE', 'schedule.course-catalog.list',
        'Paged course search for schedule admin. Evidence: all-course.js.'
    )
    '/b/arrange/findPlan' = @(
        'schedule', 'REPLACE', 'schedule.calendar.list',
        'Calendar grid sessions. Evidence: course-scroll.js.'
    )
    '/b/arrange/findPlanByCourseId' = @(
        'schedule', 'REPLACE', 'schedule.course-sessions.list',
        'Sessions for one course template. Evidence: management-schedule.js.'
    )
    '/b/arrange/getArrangeImage' = @(
        'schedule', 'REPLACE', 'schedule.export.image',
        'Download timetable image. Evidence: download-timetable.js.'
    )
    '/b/arrange/getArrangeTagData' = @(
        'schedule', 'REPLACE', 'schedule.display-tags.read',
        'Read timetable legend/tags. Evidence: course-option.js.'
    )
    '/b/arrange/getbgcolor' = @(
        'schedule', 'REPLACE', 'schedule.session.color.read',
        'Session color options. Evidence: select-backgroundcolor.js.'
    )
    '/b/arrange/getDetailByCourseid' = @(
        'schedule', 'REPLACE', 'schedule.recurring-template.read',
        'Read recurring schedule template for course. Evidence: subject-edit.js.'
    )
    '/b/arrange/getOne' = @(
        'schedule', 'REPLACE', 'schedule.session.read',
        'Single session detail for management drawer. Evidence: course-management.js.'
    )
    '/b/arrange/save' = @(
        'schedule', 'REPLACE', 'schedule.session.create',
        'Create timetable session (addCourse). Evidence: pagesCourse/index/index.js.'
    )
    '/b/arrange/saveArrangeTagData' = @(
        'schedule', 'REPLACE', 'schedule.display-tags.write',
        'Persist timetable legend/tags. Evidence: course-option.js.'
    )
    '/b/arrange/savebgcolor' = @(
        'schedule', 'REPLACE', 'schedule.session.color.write',
        'Persist session color. Evidence: select-backgroundcolor.js, course-management.js.'
    )
    '/b/arrange/selectAllTeamCourse' = @(
        'schedule', 'MERGE', 'schedule.group-course.picker',
        'Picker of group courses when swapping session. Evidence: select-courses.js.'
    )
    '/b/arrange/stopOnePlan' = @(
        'schedule', 'REPLACE', 'schedule.session.suspend',
        'Suspend one session. Evidence: course-management.js.'
    )
    '/b/arrange/update' = @(
        'schedule', 'REPLACE', 'schedule.session.update',
        'Update session time/course/coach (updateTime/updateCourse/updateAllCourse). Evidence: course-management.js, subject-edit.js.'
    )
    '/b/arrange/updateStaffUserid' = @(
        'schedule', 'REPLACE', 'schedule.session.replace-coach',
        'Replace coach on session. Evidence: course-management.js.'
    )
    '/b/course/checkHasPlan' = @(
        'course-catalog', 'MERGE', 'course-catalog.delete.preflight',
        'Blocks course delete when future sessions exist. Evidence: pagesImp/subject/subject-edit.js.'
    )
    '/b/course/delCourse' = @(
        'course-catalog', 'REPLACE', 'course-catalog.archive',
        'Archive group course template after preflight. Evidence: pagesImp/subject/subject-edit.js.'
    )
    '/b/course/delDrainer' = @(
        'course-catalog', 'REPLACE', 'course-catalog.private.archive',
        'Archive private coach template. Evidence: pagesImp/subject/subject-personal-edit.js.'
    )
    '/b/course/findTags' = @(
        'course-catalog', 'REPLACE', 'course-catalog.tags.list',
        'Course tag dictionary. Evidence: tag-popup.js.'
    )
    '/b/course/getOnePrivateCourse' = @(
        'course-catalog', 'REPLACE', 'course-catalog.private.read',
        'Read private coach template. Evidence: subject-personal-edit.js.'
    )
    '/b/course/getRoomList' = @(
        'site', 'REPLACE', 'site.room.list',
        'Classroom list for session editor. Evidence: suject-classroom.js.'
    )
    '/b/course/getStaffInWorking' = @(
        'staff', 'MERGE', 'staff.roster.on-duty',
        'On-duty staff picker for sessions. Evidence: subject-trainer.js.'
    )
    '/b/course/getTeamCourse' = @(
        'course-catalog', 'REPLACE', 'course-catalog.group.read',
        'Read group course template. Evidence: pagesCourse/subject/subject-edit.js.'
    )
    '/b/course/saveCourseTag' = @(
        'course-catalog', 'REPLACE', 'course-catalog.tags.write',
        'Save course tags. Evidence: tag-popup.js.'
    )
    '/b/course/savePrivateCourse' = @(
        'course-catalog', 'REPLACE', 'course-catalog.private.upsert',
        'Create/update private coach template. Evidence: subject-personal-edit.js.'
    )
    '/b/course/saveRoom' = @(
        'site', 'REPLACE', 'site.room.upsert',
        'Create/update classroom. Evidence: suject-classroom.js.'
    )
    '/b/course/saveTeamCourse' = @(
        'course-catalog', 'REPLACE', 'course-catalog.group.upsert',
        'Create/update group course template. Evidence: pagesImp/subject/subject-edit.js.'
    )
    '/b/course/selecctAllPriCourse' = @(
        'course-catalog', 'REPLACE', 'course-catalog.private.list',
        'List private coach templates. Evidence: pagesImp/subject/subject.js.'
    )
    '/b/course/selectAllTeamCourse' = @(
        'schedule', 'MERGE', 'schedule.group-course.picker',
        'Duplicate export of group course picker; merge with arrange/selectAllTeamCourse.'
    )
    '/b/course/upDrainerId' = @(
        'course-catalog', 'REPLACE', 'course-catalog.private.reassign-coach',
        'Reassign coach on private template (upDrainerld typo in export). Evidence: subject-persion.js.'
    )
    '/b/mainplan/applyAppointment' = @(
        'booking', 'REPLACE', 'booking.appointment.create',
        'Staff/member books seat with payable card list. Evidence: select-member-card.js (staff), member selected-member-card.'
    )
    '/b/mainplan/CancelAppoint' = @(
        'booking', 'REPLACE', 'booking.appointment.cancel',
        'Cancel booking or waitlist entry. Evidence: leagueClassDetails.js, personalTrainerDetails.js.'
    )
    '/b/mainplan/findAllPrivateDrainerList' = @(
        'schedule', 'REPLACE', 'schedule.private-coach.day-board',
        'Daily private coach lanes with booked members. Evidence: pages/course/course.js.'
    )
    '/b/mainplan/findOneDrainerAppointment' = @(
        'booking', 'REPLACE', 'booking.staff-private.session.read',
        'Private coach appointment timeline. Evidence: personalTrainerDetails.js.'
    )
    '/b/mainplan/findOneDrainerDetail' = @(
        'schedule', 'REPLACE', 'schedule.private-coach.detail',
        'Private coach header + share sign. Evidence: personalTrainerDetails.js.'
    )
    '/b/mainplan/findOnePlan' = @(
        'booking', 'REPLACE', 'booking.group-session.detail',
        'Group session roster buckets: valid, line, cancel, absent. Evidence: leagueClassDetails.js.'
    )
    '/b/mainplan/findTeamPlan' = @(
        'schedule', 'REPLACE', 'schedule.group-session.day-board',
        'Group sessions for one day incl. suspend markers. Evidence: pages/course/course.js.'
    )
    '/b/mainplan/getDrainerTimeList2' = @(
        'schedule', 'REPLACE', 'schedule.private-coach.slots',
        'Selectable private slots when booking/rescheduling. Evidence: select-time.js, edit-course.js.'
    )
    '/b/mainplan/PutAbsentTag' = @(
        'booking', 'REPLACE', 'booking.fulfillment.absent',
        'Mark no-show; may trigger penalty per policy. Evidence: leagueClassDetails.js.'
    )
    '/b/mainplan/replaceFormLine' = @(
        'booking', 'REPLACE', 'booking.waitlist.promote',
        'Promote waitlist member to confirmed seat. Evidence: select-member-card.js.'
    )
    '/b/mainplan/updateAppointTime' = @(
        'booking', 'REPLACE', 'booking.appointment.reschedule',
        'Reschedule private appointment time. Evidence: edit-course.js.'
    )
    '/b/setting/getAppointSetting' = @(
        'booking', 'REPLACE', 'booking.policy.read',
        'Tenant booking policy bundle. Evidence: appointSetting/index.js.'
    )
    '/b/setting/saveAppointSetting' = @(
        'booking', 'REPLACE', 'booking.policy.write',
        'Persist booking policy keys (aheadAppointTime, cancelOpenCourse, absent_*). Evidence: appointSetting/index.js.'
    )
    '/b/setting/getPlanHint' = @(
        'schedule', 'MERGE', 'schedule.copy-hint.read',
        'Read copy-timetable hint. Evidence: kind-reminder.js.'
    )
    '/b/setting/getPlanTitle' = @(
        'schedule', 'MERGE', 'schedule.display-title.read',
        'Read timetable title. Evidence: course-title.js.'
    )
    '/b/setting/savePlanHint' = @(
        'schedule', 'MERGE', 'schedule.copy-hint.write',
        'Save copy-timetable hint. Evidence: kind-reminder.js.'
    )
    '/b/setting/savePlanTitle' = @(
        'schedule', 'MERGE', 'schedule.display-title.write',
        'Save timetable title. Evidence: course-title.js.'
    )
    '/b/staffuser/selectAppoint' = @(
        'booking', 'MERGE', 'booking.staff.upcoming',
        'Staff home today upcoming appointments (appointRecord). Evidence: pages/home/home.js.'
    )
    '/b/report2/findStaffPrivateAppointment' = @(
        'reporting', 'MERGE', 'reporting.staff-private-appointments',
        'Report query for staff private appointments; defer implementation to reporting stage.'
    )
    '/b/report2/findStaffTeamAppointment' = @(
        'reporting', 'MERGE', 'reporting.staff-group-appointments',
        'Report query for staff group appointments; defer implementation to reporting stage.'
    )
    '/c/user/applyAppointment' = @(
        'booking', 'REPLACE', 'booking.appointment.create',
        'Member books with card selection. Evidence: 会员端/pageCourse/components/selected-member-card/index.js.'
    )
    '/c/user/cancelAppoint' = @(
        'booking', 'REPLACE', 'booking.appointment.cancel',
        'Member/staff cancel from detail pages. Evidence: coachCourse/index.js, clusterCourse/index.js.'
    )
    '/c/user/findAllPrivateDrainerList' = @(
        'schedule', 'MERGE', 'schedule.private-coach.day-board',
        'Member 约课 tab private lanes; same board as staff. Evidence: 会员端/pages/appointmentCourse/index.js.'
    )
    '/c/user/findOneDrainerDetail' = @(
        'schedule', 'MERGE', 'schedule.private-coach.detail',
        'Authenticated private coach landing. Evidence: coachCourse/index.js.'
    )
    '/c/user/findOneDrainerDetail_noToken' = @(
        'schedule', 'MERGE', 'schedule.private-coach.detail.public',
        'Share landing without session. Evidence: coachCourse/share-index.js.'
    )
    '/c/user/findTeamPlan' = @(
        'schedule', 'MERGE', 'schedule.group-session.day-board',
        'Member group day grid. Evidence: appointmentCourse/index.js.'
    )
    '/c/user/getCardListForPay' = @(
        'member-card', 'MERGE', 'member-card.payable.list',
        'Payable cards at booking checkout. Evidence: selected-member-card/index.js.'
    )
    '/c/user/getDrainerTimeList' = @(
        'schedule', 'MERGE', 'schedule.private-coach.slots',
        'Member private slot picker. Evidence: selected-course-timer/index.js.'
    )
    '/c/user/getOnePlan' = @(
        'booking', 'MERGE', 'booking.group-session.detail',
        'Member group class detail/booking. Evidence: clusterCourse/index.js.'
    )
    '/c/user/getOnePlan_noToken' = @(
        'booking', 'MERGE', 'booking.group-session.detail.public',
        'Share landing for group class. Evidence: clusterCourse/share-index.js.'
    )
    '/c/user/getwarmHint' = @(
        'booking', 'MERGE', 'booking.policy.hint',
        'Warm hints before booking (coursetype 6/7). Evidence: coachCourse/index.js, clusterCourse/index.js.'
    )
    '/c/user/getwarmHint_noToken' = @(
        'booking', 'MERGE', 'booking.policy.hint.public',
        'Share-page warm hints. Evidence: share-index pages.'
    )
    '/c/user/replaceFormLine' = @(
        'booking', 'MERGE', 'booking.waitlist.promote',
        'Member waitlist promotion path. Evidence: selected-member-card/index.js.'
    )
    '/c/user/selectAppoint' = @(
        'booking', 'REPLACE', 'booking.member.upcoming',
        'Member home upcoming list. Evidence: 会员端/pages/index/index.js.'
    )
    '/c/user/selectOneAppoint' = @(
        'booking', 'REPLACE', 'booking.member.detail',
        'Single appointment detail. Evidence: pageHome/appointmentDetails/index.js.'
    )
}

# Refine CRM-adjacent booking history rows still on planned:booking.*
$apiRefine = @{
    '/b/mainplan/saveStaffRemark' = @(
        'booking', 'REPLACE', 'booking.staff-note.append',
        'Append-only staff-only appointment note. Evidence: leagueClassDetails.js, personalTrainerDetails.js.'
    )
    '/b/manageuser/findUserAppointList' = @(
        'booking', 'MERGE', 'booking.member-history.list',
        'CRM monthly appointment history. Evidence: pageMember/details/courseDetail.js.'
    )
    '/b/manageuser/findUserAppointOne' = @(
        'booking', 'REPLACE', 'booking.member-history.detail',
        'CRM single appointment drill-down. Evidence: pageMember/details/recordDetails.js.'
    )
    '/b/report2/findUserAppointList' = @(
        'booking', 'MERGE', 'booking.member-history.list',
        'Duplicate history query; merge with manageuser variant.'
    )
}

foreach ($row in $pages) {
    if ($pageMap.ContainsKey($row.LegacyPath)) {
        $m = $pageMap[$row.LegacyPath]
        Set-PageRow $row $m[0] $m[1] $m[2] $m[3]
    }
}

foreach ($row in $apis) {
    if ($apiMap.ContainsKey($row.LegacyEndpoint)) {
        $m = $apiMap[$row.LegacyEndpoint]
        Set-ApiRow $row $m[0] $m[1] $m[2] $m[3]
    }
    elseif ($apiRefine.ContainsKey($row.LegacyEndpoint)) {
        $m = $apiRefine[$row.LegacyEndpoint]
        Set-ApiRow $row $m[0] $m[1] $m[2] $m[3]
    }
}

$pages | Export-Csv -LiteralPath $pageFile -NoTypeInformation -Encoding UTF8
$apis | Export-Csv -LiteralPath $apiFile -NoTypeInformation -Encoding UTF8

$pageUnreviewed = ($pages | Where-Object Disposition -eq 'UNREVIEWED').Count
$apiUnreviewed = ($apis | Where-Object Disposition -eq 'UNREVIEWED').Count
$pageUpdated = $pageMap.Count
$apiUpdated = $apiMap.Count + $apiRefine.Count

Write-Host "Updated pages: $pageUpdated"
Write-Host "Updated APIs: $apiUpdated"
Write-Host "Pages UNREVIEWED: $pageUnreviewed"
Write-Host "APIs UNREVIEWED: $apiUnreviewed"
