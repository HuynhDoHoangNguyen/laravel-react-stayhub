import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const componentPath = fileURLToPath(new URL('../src/components/checkout/CheckoutReviewStep.jsx', import.meta.url));

test('BKG-02: CheckoutReviewStep displays direct payment disclosure to host', async () => {
    const code = await readFile(componentPath, 'utf8');

    // Test for payment disclosure notice container
    assert.match(code, /data-testid="payment-disclosure-notice"/);

    // Test for key disclosure messages
    assert.match(code, /Phương thức thanh toán trực tiếp cho cơ sở lưu trú/);
    assert.match(code, /StayHub không trực tiếp thu tiền phòng của quý khách/);
    assert.match(code, /100% tiền phòng trực tiếp cho chủ cơ sở lưu trú \(Host\)/);
    assert.match(code, /tiền mặt hoặc chuyển khoản ngân hàng ngoài hệ thống StayHub khi nhận phòng/);
});
