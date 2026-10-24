// Pure formatter checks: no browser, DOM, server, or UI test framework.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'Admin Panel/public/assets/common/js/nirab-branch.js'), 'utf8');
const formatter = source.match(/const saudiDate = \(key, value\) => \{([\s\S]*?)\n    \};/);
assert.ok(formatter, 'The real branch formatter must be present');
const saudiDate = new Function('document', `return (key, value) => {${formatter[1]}}`)({documentElement: {lang: 'en-GB'}});
const calendarSource = fs.readFileSync(path.join(root, 'Admin Panel/Modules/BookingModule/Resources/views/provider/booking/calendar-view.blade.php'), 'utf8');
const calendarFormatter = calendarSource.match(/function formatOffcanvasDateParts\(date\) \{([\s\S]*?)\n    \}/);
assert.ok(calendarFormatter);
const calendarDate = new Function('date', calendarFormatter[1]);
let checks = 0;
for (const zone of ['UTC', 'Europe/London', 'America/New_York', 'Asia/Riyadh']) {
  process.env.TZ = zone;
  const expected = saudiDate('service_schedule', '2026-10-26 11:00:00');
  assert.match(expected, /11:00/); checks++;
  assert.equal(saudiDate('service_schedule', '2026-10-26T08:00:00Z'), expected); checks++;
  assert.equal(saudiDate('created_at', '2026-10-26 08:00:00'), expected); checks++;
  assert.match(saudiDate('service_schedule', '2026-10-26T20:30:00Z'), /26 Oct 2026, 23:30/); checks++;
  // FullCalendar uses UTC Date carriers for its Saudi civil components.
  const civil = calendarDate(new Date('2026-10-26T11:00:00Z'));
  assert.match(civil.datePart, /26 Oct 2026/); checks++;
  assert.match(civil.hourPart, /11 AM/); checks++;
}
console.log(`Web Saudi time: ${checks} checks passed`);
