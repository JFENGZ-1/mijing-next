const fs = require('fs');
const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';

function promoteCsv(file, promotions) {
  let csv = fs.readFileSync(file, 'utf8');
  let count = 0;
  for (const [id, from, to, note] of promotions) {
    const lines = csv.split('\n');
    const next = lines.map((line) => {
      if (!line.includes(id) || !line.includes(from)) return line;
      const parts = line.split('","');
      const accIdx = parts.findIndex((p) => p.includes(from));
      if (accIdx < 0) return line;
      parts[accIdx] = parts[accIdx].replace(from, to);
      const noteIdx = accIdx + 1;
      if (noteIdx < parts.length) {
        parts[noteIdx] = note + (parts[noteIdx].endsWith('"') ? '"' : '');
      }
      count++;
      return parts.join('","');
    });
    csv = next.join('\n');
  }
  fs.writeFileSync(file, csv);
  return count;
}

const apiPromos = [
  ['API-300', 'planned:identity', 'updateOwnMemberProfile', 'Member registration via PUT /member/profile; tests MemberOnboardingTest.'],
  ['API-310', 'planned:identity', 'loginWithWechat', 'WeChat code exchange via POST /auth/wechat/login.'],
  ['API-312', 'planned:identity', 'resolveWechatUnionId', 'UnionID resolution; OpenAPI POST /identity/wechat/unionid.'],
  ['API-313', 'planned:identity', 'verifyOwnWechatMobile', 'Phone grant verification; tests MemberOnboardingTest.'],
  ['API-304', 'planned:booking.member.detail', 'getMemberBookingSession', 'Member session detail. OpenAPI GET /member/booking/sessions/{id}; tests MemberBookingCatalogTest.'],
  ['API-184', 'planned:points.member-ledger.list', 'listMemberPointLedger', 'Member points ledger. OpenAPI GET /member/points/ledger; tests MemberPointLedgerTest.'],
  ['API-127', 'planned:order', 'listMemberCardOrders', 'Staff CRM card orders. OpenAPI GET .../members/{member}/orders; tests StaffMemberCardOrderTest.'],
  ['API-135', 'planned:order', 'listMemberCardOrders', 'Legacy repay merged into order list; tests StaffMemberCardOrderTest.'],
  ['API-137', 'planned:order', 'correctMemberCardOrderAmount', 'Order amount correction; tests StaffMemberCardOrderTest.'],
  ['API-121', 'planned:booking.member-history.detail', 'listStaffMemberBookingHistory', 'Appointment drill-down via booking-history scope; tests StaffMemberBookingHistoryTest.'],
  ['API-290', 'planned:booking.policy.hint', 'getStaffMemberWarmHint', 'Warm hints from staff member-experience settings.'],
  ['API-271', 'planned:member-card.balance-ledger.list', 'listStaffMemberCardLedgerEntries', 'Balance ledger; staff listStaffMemberCardLedgerEntries + member wallet ledger tests.'],
];

const pagePromos = [
  ['PAGE-165', 'planned:booking.policy-config', 'pages/settings/booking-policy/index', 'Staff booking policy UI; getStaffBookingPolicy/updateStaffBookingPolicy; StaffBookingPolicyTest.'],
  ['PAGE-075', 'planned:course-catalog.hub', 'pages/settings/courses/index', 'Course catalog hub; listStaffCourses; StaffCourseCatalogTest.'],
  ['PAGE-076', 'planned:course-catalog.group.editor', 'pages/settings/courses/edit', 'Group course editor; createStaffCourse/updateStaffCourse; StaffCourseCatalogWriteTest.'],
  ['PAGE-077', 'planned:course-catalog.private.editor', 'pages/settings/courses/edit', 'Private course editor; createStaffCourse/updateStaffCourse; StaffCourseCatalogWriteTest.'],
];

const apiCount = promoteCsv(`${root}/docs/traceability-apis.csv`, apiPromos);
const pageCount = promoteCsv(`${root}/docs/traceability-pages.csv`, pagePromos);

const apiCsv = fs.readFileSync(`${root}/docs/traceability-apis.csv`, 'utf8');
const pageCsv = fs.readFileSync(`${root}/docs/traceability-pages.csv`, 'utf8');
console.log(JSON.stringify({
  apiPromotions: apiCount,
  pagePromotions: pageCount,
  apiPlanned: (apiCsv.match(/planned:/g) || []).length,
  pagePlanned: (pageCsv.match(/planned:/g) || []).length,
}, null, 2));
