import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const servicePath = fileURLToPath(new URL('../src/services/adminCommissionService.js', import.meta.url));
const dialogPath = fileURLToPath(new URL('../src/components/commission/CommissionVerificationDialog.jsx', import.meta.url));
const pagePath = fileURLToPath(new URL('../src/pages/admin/AdminCommissionsPage.jsx', import.meta.url));
const layoutPath = fileURLToPath(new URL('../src/layouts/AdminLayout.jsx', import.meta.url));
const routerPath = fileURLToPath(new URL('../src/routes/AppRouter.jsx', import.meta.url));

test('COM-06: adminCommissionService connects to correct endpoints', async () => {
    const code = await readFile(servicePath, 'utf8');

    assert.match(code, /api\.get\('\/api\/admin\/commissions'/);
    assert.match(code, /api\.get\('\/api\/admin\/commission-remittances'/);
    assert.match(code, /api\.post\(`\/api\/admin\/commission-remittances\/\$\{id\}\/verify`/);
    assert.match(code, /api\.post\(`\/api\/admin\/commission-remittances\/\$\{id\}\/reject`/);
});

test('COM-08: CommissionVerificationDialog requires bank statement ref and checkbox confirmation', async () => {
    const code = await readFile(dialogPath, 'utf8');

    // Required fields in verify mode
    assert.match(code, /id="verified-bank-ref-input"/);
    assert.match(code, /id="confirm-statement-checkbox"/);
    assert.match(code, /confirmedStatement/);

    // Required fields in reject mode
    assert.match(code, /id="reject-reason-input"/);
    assert.match(code, /rejectReason/);

    // Dual mode tabs
    assert.match(code, /'VERIFY'/);
    assert.match(code, /'REJECT'/);
});

test('COM-08: AdminCommissionsPage renders queue, full list, and connects verification dialog', async () => {
    const code = await readFile(pagePath, 'utf8');

    // View modes
    assert.match(code, /'QUEUE'/);
    assert.match(code, /'ALL_COMMISSIONS'/);

    // Component integration
    assert.match(code, /<CommissionVerificationDialog/);
    assert.match(code, /adminCommissionService\.getRemittanceQueue/);
    assert.match(code, /adminCommissionService\.getAdminCommissions/);
    assert.match(code, /adminCommissionService\.verifyRemittance/);
    assert.match(code, /adminCommissionService\.rejectRemittance/);

    // Filter statuses
    assert.match(code, /'SUBMITTED'/);
    assert.match(code, /'VERIFIED'/);
    assert.match(code, /'REJECTED'/);
});

test('COM-08: AdminLayout and AppRouter register commission navigation', async () => {
    const layoutCode = await readFile(layoutPath, 'utf8');
    const routerCode = await readFile(routerPath, 'utf8');

    assert.match(layoutCode, /\/admin\/commissions/);
    assert.match(layoutCode, /Đối soát hoa hồng/);

    assert.match(routerCode, /import AdminCommissionsPage from/);
    assert.match(routerCode, /<Route path="commissions" element=\{<AdminCommissionsPage \/>\} \/>/);
});
