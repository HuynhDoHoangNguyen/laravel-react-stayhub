import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const servicePath = fileURLToPath(new URL('../src/services/hostCommissionService.js', import.meta.url));
const dialogPath = fileURLToPath(new URL('../src/components/commission/CommissionRemittanceDialog.jsx', import.meta.url));
const pagePath = fileURLToPath(new URL('../src/pages/host/HostCommissionsPage.jsx', import.meta.url));
const layoutPath = fileURLToPath(new URL('../src/layouts/HostLayout.jsx', import.meta.url));
const routerPath = fileURLToPath(new URL('../src/routes/AppRouter.jsx', import.meta.url));

test('COM-04 / COM-05: hostCommissionService connects to real endpoints', async () => {
    const code = await readFile(servicePath, 'utf8');

    assert.match(code, /api\.get\('\/api\/host\/commissions'/);
    assert.match(code, /api\.get\(`\/api\/host\/commissions\/\$\{id\}`/);
    assert.match(code, /api\.post\(`\/api\/host\/commissions\/\$\{id\}\/remittances`/);
});

test('COM-07: CommissionRemittanceDialog displays dynamic payment_instructions and missing config fallback without hardcoded bank info', async () => {
    const code = await readFile(dialogPath, 'utf8');

    // Dynamic bank instructions from API payment_instructions (no hardcoded account)
    assert.doesNotMatch(code, /1029384756/);
    assert.match(code, /payment_instructions/);
    assert.match(code, /instructions\.bank_name/);
    assert.match(code, /instructions\.account_number/);
    assert.match(code, /instructions\.account_holder/);
    assert.match(code, /Chưa có hướng dẫn chuyển khoản/);
    assert.match(code, /reference_code/);

    // Form inputs and Idempotency key
    assert.match(code, /id="bank-ref-input"/);
    assert.match(code, /idempotencyKey/);
    assert.match(code, /crypto\.randomUUID/);
    assert.match(code, /onSubmit/);
});

test('COM-07: HostCommissionsPage renders table, status tabs, and connects dialog', async () => {
    const code = await readFile(pagePath, 'utf8');

    // Status tabs
    assert.match(code, /'DUE'/);
    assert.match(code, /'UNDER_REVIEW'/);
    assert.match(code, /'OVERDUE'/);
    assert.match(code, /'SETTLED'/);

    // Component integration
    assert.match(code, /<CommissionRemittanceDialog/);
    assert.match(code, /hostCommissionService\.getCommissions/);
    assert.match(code, /hostCommissionService\.submitRemittance/);
    assert.match(code, /Mã chuyển khoản/);
    assert.match(code, /Phí hoa hồng \(10%\)/);
});

test('COM-07: HostLayout and AppRouter register commission navigation', async () => {
    const layoutCode = await readFile(layoutPath, 'utf8');
    const routerCode = await readFile(routerPath, 'utf8');

    assert.match(layoutCode, /\/host\/commissions/);
    assert.match(layoutCode, /Hoa hồng & Đối soát/);

    assert.match(routerCode, /import HostCommissionsPage from/);
    assert.match(routerCode, /<Route path="commissions" element=\{<HostCommissionsPage \/>\} \/>/);
});
