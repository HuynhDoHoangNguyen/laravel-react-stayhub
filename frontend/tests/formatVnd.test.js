import test from 'node:test';
import assert from 'node:assert/strict';
import { formatVnd } from '../src/utils/formatVnd.js';

const examples = [
  ['0.00', '0'], ['0.01', '0'], ['0.49', '0'], ['0.50', '1'], ['0.99', '1'],
  ['100.25', '100'], ['300.76', '301'], ['999.49', '999'], ['999.50', '1.000'],
  ['1000000.00', '1.000.000'], ['19999999999.98', '20.000.000.000'],
  ['9007199254740993.01', '9.007.199.254.740.993'],
  ['9007199254740993.49', '9.007.199.254.740.993'],
  ['9007199254740993.50', '9.007.199.254.740.994'],
  ['999999999999999999.99', '1.000.000.000.000.000.000'],
];

for (const [amount, expected] of examples) {
  test(`whole VND display: ${amount} rounds to ${expected}`, () => {
    assert.equal(formatVnd(amount), `${expected}\u00a0₫`);
  });
}

test('malformed, signed, noncanonical or nonstring values are rejected without coercion', () => {
  for (const value of [null, undefined, '', 1, 1n, NaN, Infinity, {}, [], '1', '01.00', '1.0', '1.000',
    '-1.00', '-0.00', '+1.00', '1e2', ' 1.00', '1.00 ', '1.00\n', '1.00\r', '1.00\r\n', '1.00\u2028', '1.00\u2029']) {
    assert.throws(() => formatVnd(value), TypeError);
  }
});
