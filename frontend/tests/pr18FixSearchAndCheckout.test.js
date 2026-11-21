import test, { mock } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import axios from 'axios';

let adapterMock = null;
const client = axios.create({ adapter: (config) => (adapterMock ? adapterMock(config) : Promise.resolve({ data: {} })) });
mock.module(new URL('../src/api/axios.js', import.meta.url).href, { defaultExport: client });

const {
  isAvailabilitySearch,
  hasPartialDateRange,
  parseAvailabilitySearchParams,
  searchAvailableRooms,
} = await import('../src/services/roomAvailabilitySearchService.js');
const { addDaysToIsoDate, isValidCalendarDate } = await import('../src/utils/propertyBooking.js');
const { nightsBetween } = await import('../src/services/bookingService.js');

test.after(() => mock.restoreAll());

const guestsStepPath = fileURLToPath(new URL('../src/components/checkout/CheckoutGuestsStep.jsx', import.meta.url));
const searchResultsPath = fileURLToPath(new URL('../src/pages/public/SearchResultsPage.jsx', import.meta.url));
const searchCardPath = fileURLToPath(new URL('../src/components/search/SearchRoomCard.jsx', import.meta.url));

// --------------------------------------------------------------------------
// Finding 1: Dateless search must NOT auto-generate availability dates
// --------------------------------------------------------------------------
test('PR18-FIX-01 / Finding 1: parseAvailabilitySearchParams does not inject today/tomorrow when dates are omitted', () => {
  const parsed = parseAvailabilitySearchParams('?city=Da+Lat&adults=4&children=1');

  assert.equal(parsed.checkIn, '');
  assert.equal(parsed.checkOut, '');
  assert.equal(parsed.params.check_in_date, '');
  assert.equal(parsed.params.check_out_date, '');
  assert.equal(parsed.adults, 4);
  assert.equal(parsed.children, 1);
  assert.equal(parsed.guests, 5);
});

test('PR18-FIX-01 / Finding 1: isAvailabilitySearch and hasPartialDateRange correctly classify full, partial, and dateless queries', () => {
  // Dateless search
  assert.equal(isAvailabilitySearch('?city=Da+Lat&adults=2'), false);
  assert.equal(hasPartialDateRange('?city=Da+Lat&adults=2'), false);

  // Partial check-in only
  assert.equal(isAvailabilitySearch('?startDate=2026-12-01&adults=2'), false);
  assert.equal(hasPartialDateRange('?startDate=2026-12-01&adults=2'), true);

  // Partial check-out only
  assert.equal(isAvailabilitySearch('?endDate=2026-12-03&adults=2'), false);
  assert.equal(hasPartialDateRange('?endDate=2026-12-03&adults=2'), true);

  // Complete date range
  assert.equal(isAvailabilitySearch('?startDate=2026-12-01&endDate=2026-12-03'), true);
  assert.equal(hasPartialDateRange('?startDate=2026-12-01&endDate=2026-12-03'), false);

  // Alias support (checkIn / checkOut)
  assert.equal(isAvailabilitySearch('?checkIn=2026-12-01&checkOut=2026-12-03'), true);
  assert.equal(hasPartialDateRange('?checkIn=2026-12-01&checkOut=2026-12-03'), false);
});

test('PR18-FIX-01 / Finding 1: searchAvailableRooms rejects with 422 if called without both dates', async () => {
  await assert.rejects(
    async () => {
      await searchAvailableRooms('?city=Da+Lat');
    },
    (err) => {
      assert.equal(err.response?.status, 422);
      assert.ok(err.response?.data?.errors?.check_in_date);
      assert.ok(err.response?.data?.errors?.check_out_date);
      return true;
    }
  );
});

test('PR18-FIX-01 / Finding 1: SearchResultsPage does not route dateless location search to searchAvailableRooms', async () => {
  const code = await readFile(searchResultsPath, 'utf8');

  // Verify availability search is strictly decoupled from hasLocation
  assert.doesNotMatch(code, /const\s+isRoomSearch\s*=\s*available\s*\|\|\s*hasLocation/);
  assert.doesNotMatch(code, /const\s+isRoomSearch\s*=\s*hasDates\s*\|\|\s*hasLocation/);

  // Verify hasPartialDateRange alert is present
  assert.match(code, /hasPartialDateRange/);
  assert.match(code, /Khoảng ngày tìm kiếm chưa hợp lệ/);
});

// --------------------------------------------------------------------------
// Finding 2: Preserve guest count from dateless search to Checkout
// --------------------------------------------------------------------------
test('PR18-FIX-01 / Finding 2: SearchResultsPage constructs forwardParams with adults and children for dateless search', async () => {
  const code = await readFile(searchResultsPath, 'utf8');

  // Verify guest count is preserved into forwardUrl even when available is false
  assert.match(code, /forwardUrl\.set\('adults',\s*String\(finalAdults\)\)/);
  assert.match(code, /forwardUrl\.set\('children',\s*String\(finalChildren\)\)/);
  assert.match(code, /const\s+forwardParams\s*=\s*forwardUrl\.toString\(\)/);
});

test('PR18-FIX-01 / Finding 2: SearchRoomCard links to Checkout using searchParams preserving guest parameters', async () => {
  const code = await readFile(searchCardPath, 'utf8');

  // checkoutHref appends searchParams which includes adults and children
  assert.match(code, /const\s*checkoutHref\s*=\s*`\/customer\/checkout\?propertyId=\$\{room\.property\.id\}&roomId=\$\{room\.id\}\$\{searchParams\s*\?\s*`&/);
  assert.match(code, /<Link\s+to=\{checkoutHref\}[^>]*>Đặt phòng<\/Link>/);
});

// --------------------------------------------------------------------------
// Finding 3: Timezone-independent date calculation
// --------------------------------------------------------------------------
test('PR18-FIX-01 / Finding 3: addDaysToIsoDate correctly adds days without timezone shift', () => {
  // Case from issue: check-in 2026-11-24 + 1 day MUST be 2026-11-25
  assert.equal(addDaysToIsoDate('2026-11-24', 1), '2026-11-25');

  // Month boundary (Nov 30 -> Dec 01)
  assert.equal(addDaysToIsoDate('2026-11-30', 1), '2026-12-01');

  // Year boundary (Dec 31 -> Jan 01)
  assert.equal(addDaysToIsoDate('2026-12-31', 1), '2027-01-01');

  // Leap year 2028 (Feb 28 -> Feb 29)
  assert.equal(addDaysToIsoDate('2028-02-28', 1), '2028-02-29');
  assert.equal(addDaysToIsoDate('2028-02-29', 1), '2028-03-01');

  // Non-leap year 2026 (Feb 28 -> Mar 01)
  assert.equal(addDaysToIsoDate('2026-02-28', 1), '2026-03-01');

  // Negative days
  assert.equal(addDaysToIsoDate('2026-12-01', -1), '2026-11-30');

  // Invalid date inputs return empty string
  assert.equal(addDaysToIsoDate('invalid-date', 1), '');
  assert.equal(addDaysToIsoDate('', 1), '');
  assert.equal(addDaysToIsoDate(null, 1), '');
});

test('PR18-FIX-01 / Finding 3: isValidCalendarDate validates real calendar dates', () => {
  assert.equal(isValidCalendarDate('2026-11-24'), true);
  assert.equal(isValidCalendarDate('2028-02-29'), true); // 2028 is leap year
  assert.equal(isValidCalendarDate('2026-02-29'), false); // 2026 is not leap year
  assert.equal(isValidCalendarDate('2026-04-31'), false); // April has 30 days
  assert.equal(isValidCalendarDate('not-a-date'), false);
});

test('PR18-FIX-01 / Finding 3: nightsBetween calculates exact calendar nights across leap years and months', () => {
  assert.equal(nightsBetween('2026-11-24', '2026-11-25'), 1);
  assert.equal(nightsBetween('2026-11-24', '2026-11-27'), 3);
  assert.equal(nightsBetween('2028-02-28', '2028-03-01'), 2); // Includes Feb 29 in leap year
  assert.equal(nightsBetween('2026-02-28', '2026-03-01'), 1); // Non-leap year
  assert.equal(nightsBetween('2026-12-31', '2027-01-02'), 2);
});

test('PR18-FIX-01 / Finding 3: CheckoutGuestsStep uses addDaysToIsoDate for check-out adjustment', async () => {
  const code = await readFile(guestsStepPath, 'utf8');

  // Must import addDaysToIsoDate
  assert.match(code, /import\s*\{[^}]*addDaysToIsoDate[^}]*\}\s*from/);

  // Must use addDaysToIsoDate(newCheckIn, 1) instead of new Date(newCheckIn).setDate(...)
  assert.match(code, /newCheckOut\s*=\s*addDaysToIsoDate\(newCheckIn,\s*1\);/);
  assert.doesNotMatch(code, /nextDay\.setDate/);
});
