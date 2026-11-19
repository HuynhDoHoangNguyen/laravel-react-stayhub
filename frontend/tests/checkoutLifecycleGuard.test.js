import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const checkoutPath = fileURLToPath(new URL('../src/pages/customer/CustomerCheckoutPage.jsx', import.meta.url));
const hostDetailPath = fileURLToPath(new URL('../src/pages/host/HostBookingDetailPage.jsx', import.meta.url));
const hostServicesPath = fileURLToPath(new URL('../src/pages/host/HostBookingServicesPage.jsx', import.meta.url));
const reviewStepPath = fileURLToPath(new URL('../src/components/checkout/CheckoutReviewStep.jsx', import.meta.url));

test('R01: CustomerCheckoutPage captures propertySource and blocks preview & create mutations for mock data', async () => {
  const code = await readFile(checkoutPath, 'utf8');

  // Verify propertySource state exists and captures res?.source
  assert.match(code, /const\s*\[propertySource,\s*setPropertySource\]\s*=\s*useState\(null\);/);
  assert.match(code, /setPropertySource\(res\?\.source\s*\|\|\s*null\);/);

  // Verify handlePreviewBooking blocks non 'room-search' source
  assert.match(code, /if\s*\(propertySource\s*!==\s*'room-search'\)\s*\{\s*setPreview\(null\);/);
  assert.match(code, /propertySource\s*===\s*'mock'/);

  // Verify handleCreateBooking blocks non 'room-search' source
  assert.match(code, /if\s*\(propertySource\s*!==\s*'room-search'\)\s*\{\s*setCreateError\(/);

  // Verify mock warning banner is displayed
  assert.match(code, /propertySource\s*===\s*'mock'\s*&&\s*\(/);
  assert.match(code, /Chế độ xem thử \(Mock data\)/);
});

test('R01: CheckoutReviewStep disables action button when isMock is active', async () => {
  const code = await readFile(reviewStepPath, 'utf8');

  // Verify isMock prop is received and disables preview action
  assert.match(code, /isMock\s*=\s*false/);
  assert.match(code, /disabled=\{\s*isMock\s*\|\|\s*isPreviewing\s*\|\|\s*isCreating\s*\}/);
  assert.match(code, /isMock\s*\?\s*'Chế độ xem thử \(Khóa đặt phòng\)'\s*:\s*'Kiểm tra và đặt phòng'/);
});

test('R03: CustomerCheckoutPage invalidates preview and increments sequence id on input changes', async () => {
  const code = await readFile(checkoutPath, 'utf8');

  // Verify previewRequestIdRef sequence counter and previewSnapshot
  assert.match(code, /const\s*previewRequestIdRef\s*=\s*useRef\(0\);/);
  assert.match(code, /const\s*\[previewSnapshot,\s*setPreviewSnapshot\]\s*=\s*useState\(null\);/);

  // Invalidation via pure derived state on parameter changes
  assert.match(code, /const\s*isPreviewCurrent\s*=\s*useMemo\(\(\)\s*=>\s*\{/);
  assert.match(code, /previewSnapshot\.room_id\s*===\s*bookingPayload\.room_id/);
  assert.match(code, /const\s*activePreview\s*=\s*isPreviewCurrent\s*\?\s*preview\s*:\s*null;/);

  // handlePreviewBooking sequence check ignores stale async response
  assert.match(code, /const\s*currentReqId\s*=\s*\+\+previewRequestIdRef\.current;/);
  assert.match(code, /if\s*\(currentReqId\s*===\s*previewRequestIdRef\.current\)\s*\{\s*setPreview\(result\);/);

  // handleCreateBooking validates snapshot matches current payload before submission
  assert.match(code, /previewSnapshot\.room_id\s*===\s*bookingPayload\.room_id/);
  assert.match(code, /previewSnapshot\.check_in_date\s*===\s*bookingPayload\.check_in_date/);
  assert.match(code, /previewSnapshot\.check_out_date\s*===\s*bookingPayload\.check_out_date/);
  assert.match(code, /previewSnapshot\.guest_count\s*===\s*bookingPayload\.guest_count/);
});

test('R02: HostBookingDetailPage guards transitions against unmount and booking context switches', async () => {
  const code = await readFile(hostDetailPath, 'utf8');

  // Verify activeBookingIdRef and isMountedRef exist
  assert.match(code, /const\s*activeBookingIdRef\s*=\s*useRef\(bookingId\);/);
  assert.match(code, /const\s*isMountedRef\s*=\s*useRef\(true\);/);

  // Verify runTransition captures targetBookingId and guards state update
  assert.match(code, /const\s*targetBookingId\s*=\s*bookingId;/);
  assert.match(code, /if\s*\(!isMountedRef\.current\s*\|\|\s*activeBookingIdRef\.current\s*!==\s*targetBookingId\)\s*\{\s*return;\s*\}/);
});

test('R02: HostBookingServicesPage guards service mutations and reload against context switches', async () => {
  const code = await readFile(hostServicesPath, 'utf8');

  // Verify activeBookingIdRef and isMountedRef exist
  assert.match(code, /const\s*activeBookingIdRef\s*=\s*useRef\(bookingId\);/);
  assert.match(code, /const\s*isMountedRef\s*=\s*useRef\(true\);/);

  // Verify reloadServices and performAction guard against stale context
  assert.match(code, /const\s*targetBookingId\s*=\s*bookingId;/);
  assert.match(code, /if\s*\(!isMountedRef\.current\s*\|\|\s*activeBookingIdRef\.current\s*!==\s*targetBookingId\)\s*\{\s*return;\s*\}/);
});
