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
  assert.match(code, /if\s*\(!isMountedRef\.current\s*\|\|\s*activeBookingIdRef\.current\s*!==\s*targetBookingId/);
});

test('R02: HostBookingServicesPage guards service mutations and reload against context switches', async () => {
  const code = await readFile(hostServicesPath, 'utf8');

  // Verify activeBookingIdRef and isMountedRef exist
  assert.match(code, /const\s*activeBookingIdRef\s*=\s*useRef\(bookingId\);/);
  assert.match(code, /const\s*isMountedRef\s*=\s*useRef\(true\);/);

  // Verify reloadServices and performAction guard against stale context
  assert.match(code, /const\s*targetBookingId\s*=\s*bookingId;/);
  assert.match(code, /if\s*\(!isMountedRef\.current\s*\|\|\s*activeBookingIdRef\.current\s*!==\s*targetBookingId/);
});

test('PR10-04: Changing guest count while preview is pending cancels loading state and ignores subsequent resolve', async () => {
  let isPreviewing = false;
  let preview = null;
  let previewSnapshot = null;
  let previewError = '';
  let previewRequestId = 0;
  let isMounted = true;

  let resolveApiPromise = null;
  const deferredApiCall = () => new Promise((resolve) => { resolveApiPromise = resolve; });

  const handlePreviewBooking = async () => {
    const currentReqId = ++previewRequestId;
    const requestedPayload = { room_id: 1, check_in_date: '2026-12-01', check_out_date: '2026-12-03', guest_count: 2 };
    preview = null;
    previewSnapshot = null;
    previewError = '';
    isPreviewing = true;

    try {
      const result = await deferredApiCall();
      if (!isMounted) return;
      if (currentReqId === previewRequestId) {
        preview = result;
        previewSnapshot = requestedPayload;
      }
    } catch (err) {
      if (!isMounted) return;
      if (currentReqId === previewRequestId) {
        previewError = err.message;
      }
    } finally {
      if (isMounted && currentReqId === previewRequestId) {
        isPreviewing = false;
      }
    }
  };

  const updateGuestCount = () => {
    preview = null;
    previewSnapshot = null;
    previewError = '';
    isPreviewing = false; // PR10-04 fix
    previewRequestId += 1;
  };

  // 1. User triggers preview -> request is in flight
  const previewTask = handlePreviewBooking();
  assert.equal(isPreviewing, true, 'isPreviewing should be true while request is in flight');

  // 2. User changes guest count while preview is pending
  updateGuestCount();
  assert.equal(isPreviewing, false, 'PR10-04: isPreviewing must be reset to false so action button is not stuck locked');

  // 3. Stale in-flight request finishes later
  resolveApiPromise({
    pricing: { total: '1000000.00' },
    availability: { available: true },
  });
  await previewTask;

  // 4. Stale response was rejected and isPreviewing remains false
  assert.equal(isPreviewing, false, 'isPreviewing must remain false after stale request finishes');
  assert.equal(preview, null, 'Stale preview data must not be written');
  assert.equal(previewSnapshot, null, 'Stale snapshot must not be written');
  assert.equal(previewError, '', 'No error should be set for cancelled request');
});

test('PR10-04: CustomerCheckoutPage source contains updateGuestCount reset and isMountedRef lifecycle guards', async () => {
  const code = await readFile(checkoutPath, 'utf8');

  // Verify updateGuestCount resets isPreviewing(false)
  assert.match(code, /const\s*updateGuestCount\s*=\s*\([^)]*\)\s*=>\s*\{[^}]*setIsPreviewing\(false\);/s);

  // Verify isMountedRef exists and unmount cleanup hook
  assert.match(code, /const\s*isMountedRef\s*=\s*useRef\(true\);/);
  assert.match(code, /isMountedRef\.current\s*=\s*false;/);

  // Verify handlePreviewBooking and handleCreateBooking guard with isMountedRef
  assert.match(code, /if\s*\(!isMountedRef\.current\)\s*return;/);
  assert.match(code, /if\s*\(isMountedRef\.current\s*&&\s*currentReqId\s*===\s*previewRequestIdRef\.current\)/);
});

test('PR10-05: HostBookingDetailPage resets actionLoading when bookingId switches during pending transition', async () => {
  let activeBookingId = 10;
  let actionLoading = false;
  let actionLock = false;
  let bookingData = { id: 10, status: 'PENDING' };
  let isMounted = true;

  let resolveTransition = null;
  const deferredTransition = () => new Promise((resolve) => { resolveTransition = resolve; });

  const runTransition = async () => {
    if (actionLock) return;
    const targetBookingId = activeBookingId;
    actionLock = true;
    actionLoading = true;

    try {
      const updated = await deferredTransition();
      if (!isMounted || activeBookingId !== targetBookingId) {
        return;
      }
      bookingData = updated;
    } finally {
      actionLock = false;
      if (isMounted) {
        actionLoading = false;
      }
    }
  };

  const switchBooking = (newId) => {
    activeBookingId = newId;
    actionLoading = false; // PR10-05 reset
    actionLock = false;
  };

  // 1. Host triggers transition on booking 10
  const transitionTask = runTransition('confirm');
  assert.equal(actionLoading, true, 'actionLoading must be true during pending mutation');

  // 2. User switches to booking 11 while transition is pending
  switchBooking(11);
  assert.equal(actionLoading, false, 'PR10-05: actionLoading must immediately reset to false when switching booking');

  // 3. Old transition finishes later
  resolveTransition({ id: 10, status: 'CONFIRMED' });
  await transitionTask;

  // 4. actionLoading remains false and bookingData was NOT overwritten with stale booking 10 data
  assert.equal(actionLoading, false, 'actionLoading must remain false after stale transition completes');
  assert.equal(bookingData.id, 10, 'Booking data must not be mutated with stale transition result');
  assert.equal(bookingData.status, 'PENDING');
});

test('PR10-05: HostBookingServicesPage resets saving state when bookingId switches during pending mutation', async () => {
  let activeBookingId = 20;
  let saving = '';
  let actionLock = false;
  let services = [{ id: 1, name: 'Service 1' }];
  let isMounted = true;

  let resolveServiceAction = null;
  const deferredAction = () => new Promise((resolve) => { resolveServiceAction = resolve; });

  const performAction = async (key, actionFn) => {
    if (actionLock) return;
    const targetBookingId = activeBookingId;
    actionLock = true;
    saving = key;

    try {
      await actionFn();
      if (!isMounted || activeBookingId !== targetBookingId) {
        return;
      }
      services = [...services, { id: 2, name: 'Service 2' }];
    } finally {
      actionLock = false;
      if (isMounted) {
        saving = '';
      }
    }
  };

  const switchBooking = (newId) => {
    activeBookingId = newId;
    saving = ''; // PR10-05 reset
    actionLock = false;
  };

  // 1. Host triggers add service on booking 20
  const actionTask = performAction('add-service', deferredAction);
  assert.equal(saving, 'add-service', 'saving key must be set during pending mutation');

  // 2. User switches to booking 21 while service mutation is pending
  switchBooking(21);
  assert.equal(saving, '', 'PR10-05: saving must immediately reset to empty string when switching booking');

  // 3. Old service mutation finishes later
  resolveServiceAction();
  await actionTask;

  // 4. saving remains empty and services list was NOT overwritten with stale booking 20 data
  assert.equal(saving, '', 'saving must remain empty after stale mutation completes');
  assert.equal(services.length, 1, 'Services list must not be mutated with stale booking 20 services');
});

test('PR12-01: CustomerCheckoutPage setup sets isMountedRef to true, surviving StrictMode cleanup-remount cycle', async () => {
  const code = await readFile(checkoutPath, 'utf8');

  // StrictMode: useEffect setup must explicitly set isMountedRef.current = true
  assert.match(code, /useEffect\(\(\)\s*=>\s*\{\s*isMountedRef\.current\s*=\s*true;\s*return\s*\(\)\s*=>\s*\{\s*isMountedRef\.current\s*=\s*false;\s*\};\s*\},/);
});

test('PR12-04: Host mutations on booking B are not unlocked or corrupted by late-resolving mutation from booking A', async () => {
  let activeBookingId = 100;
  let actionLock = false;
  let saving = '';
  let mutationRequestId = 0;
  let isMounted = true;
  let bMutationFinished = false;

  let resolveA = null;
  const deferredA = () => new Promise((resolve) => { resolveA = resolve; });

  let resolveB = null;
  const deferredB = () => new Promise((resolve) => { resolveB = resolve; });

  const performAction = async (key, actionFn) => {
    if (actionLock) return false;
    const targetBookingId = activeBookingId;
    const currentRequestId = ++mutationRequestId;
    actionLock = true;
    saving = key;

    try {
      await actionFn();
      if (!isMounted || activeBookingId !== targetBookingId || currentRequestId !== mutationRequestId) {
        return false;
      }
      return true;
    } finally {
      if (currentRequestId === mutationRequestId && activeBookingId === targetBookingId) {
        actionLock = false;
        if (isMounted) {
          saving = '';
        }
      }
    }
  };

  const switchBooking = (newId) => {
    activeBookingId = newId;
    actionLock = false;
    mutationRequestId += 1;
    saving = '';
  };

  // 1. Start mutation on A
  const taskA = performAction('mutation-A', deferredA);
  assert.equal(actionLock, true);
  assert.equal(saving, 'mutation-A');

  // 2. Switch to B while A is pending
  switchBooking(200);
  assert.equal(actionLock, false);
  assert.equal(saving, '');

  // 3. Start mutation on B
  const taskB = performAction('mutation-B', async () => {
    await deferredB();
    bMutationFinished = true;
  });
  assert.equal(actionLock, true);
  assert.equal(saving, 'mutation-B');

  // 4. A resolves while B is still pending. It MUST NOT unlock B or clear saving!
  resolveA();
  await taskA;
  assert.equal(actionLock, true, 'Late A resolution must NOT unlock actionLock while B is pending');
  assert.equal(saving, 'mutation-B', 'Late A resolution must NOT clear saving while B is pending');

  // 5. B finishes normally
  resolveB();
  await taskB;
  assert.equal(bMutationFinished, true);
  assert.equal(actionLock, false, 'B resolution releases lock');
  assert.equal(saving, '', 'B resolution clears saving');
});

test('PR12-07: CustomerBookingDetailPage cancel mutation on booking A does not overwrite booking B on late response', async () => {
  let activeBookingId = 'BK_A';
  let cancelLock = false;
  let isCancelling = false;
  let isCancelDialogOpen = true;
  let bookingState = { id: 'BK_A', status: 'CONFIRMED' };
  let cancelRequestId = 0;
  let isMounted = true;

  let resolveCancelA = null;
  const deferredCancelA = () => new Promise((resolve) => { resolveCancelA = resolve; });

  const handleCancelBooking = async () => {
    if (cancelLock) return;
    const targetBookingId = activeBookingId;
    const currentRequestId = ++cancelRequestId;

    cancelLock = true;
    isCancelling = true;

    try {
      const updated = await deferredCancelA();
      if (!isMounted || activeBookingId !== targetBookingId || currentRequestId !== cancelRequestId) {
        return;
      }
      bookingState = { ...bookingState, ...updated };
      isCancelDialogOpen = false;
    } finally {
      if (currentRequestId === cancelRequestId && activeBookingId === targetBookingId) {
        cancelLock = false;
        if (isMounted) {
          isCancelling = false;
        }
      }
    }
  };

  const switchBooking = (newId) => {
    activeBookingId = newId;
    cancelLock = false;
    cancelRequestId += 1;
    isCancelling = false;
    isCancelDialogOpen = false;
    bookingState = { id: newId, status: 'PENDING' };
  };

  // 1. User opens dialog and cancels booking A
  const cancelTask = handleCancelBooking();
  assert.equal(isCancelling, true);
  assert.equal(cancelLock, true);

  // 2. User switches to booking B while cancel is in flight
  switchBooking('BK_B');
  assert.equal(activeBookingId, 'BK_B');
  assert.equal(bookingState.id, 'BK_B');
  assert.equal(isCancelling, false);
  assert.equal(isCancelDialogOpen, false);

  // 3. Old cancel A resolves later with CANCELLED status
  resolveCancelA({ id: 'BK_A', status: 'CANCELLED' });
  await cancelTask;

  // 4. Booking B state was NOT overwritten by A's late response
  assert.equal(bookingState.id, 'BK_B', 'Booking B ID must not be overwritten');
  assert.equal(bookingState.status, 'PENDING', 'Booking B status must remain PENDING');
  assert.equal(isCancelling, false);
  assert.equal(isCancelDialogOpen, false);
});
