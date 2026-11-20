import test from 'node:test';
import assert from 'node:assert/strict';
import { bookingDisplayError } from '../src/utils/bookingDisplayError.js';

test('display layer handles Axios and plain service errors without changing backend messages', () => {
  assert.equal(bookingDisplayError({ response: { status: 409, data: { message: 'Conflict fixture.' } } }, 'Fallback.'), 'Conflict fixture.');
  assert.equal(bookingDisplayError({ response: { status: 403, data: {} } }, 'Fallback.'), 'Tài khoản hiện tại không có quyền thực hiện thao tác này.');
  assert.equal(bookingDisplayError(new Error('Network error'), 'Fallback.'), 'Fallback.');
  assert.equal(bookingDisplayError({ status: 400, message: 'Mã không hợp lệ.' }, 'Fallback.'), 'Mã không hợp lệ.');
  assert.equal(bookingDisplayError({ response: { status: 403, data: {} } }, 'Detail fallback.', {
    useStatusMessages: false,
  }), 'Detail fallback.');
  assert.equal(bookingDisplayError({ response: { status: 404, data: { message: 'Private detail.' } } }, 'Fallback.', {
    notFoundMessage: 'Không tìm thấy đơn đặt phòng này.',
  }), 'Không tìm thấy đơn đặt phòng này.');
});
