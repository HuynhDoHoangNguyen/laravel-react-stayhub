import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const servicePath = fileURLToPath(new URL('../src/services/adminCommissionService.js', import.meta.url));
const dialogPath = fileURLToPath(new URL('../src/components/commission/CommissionVerificationDialog.jsx', import.meta.url));
const pagePath = fileURLToPath(new URL('../src/pages/admin/AdminCommissionsPage.jsx', import.meta.url));
const dashboardPath = fileURLToPath(new URL('../src/pages/admin/AdminDashboardPage.jsx', import.meta.url));
const dashboardChartsPath = fileURLToPath(new URL('../src/components/dashboard/AdminDashboardCharts.jsx', import.meta.url));
const dateFilterPath = fileURLToPath(new URL('../src/components/admin/AdminDateRangeFilter.jsx', import.meta.url));
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
    assert.match(code, /Hàng chờ đối soát/);
    assert.match(code, /Tất cả hoa hồng toàn hệ thống/);

    // Component integration
    assert.match(code, /<CommissionVerificationDialog/);
    assert.match(code, /adminCommissionService\.getRemittanceQueue/);
    assert.match(code, /adminCommissionService\.getAdminCommissions/);
    assert.match(code, /adminCommissionService\.verifyRemittance/);
    assert.match(code, /adminCommissionService\.rejectRemittance/);

    // Filter statuses
    assert.match(code, /SUBMITTED: 'Chờ kiểm tra'/);
    assert.match(code, /VERIFIED: 'Đã duyệt'/);
    assert.match(code, /REJECTED: 'Đã từ chối'/);
});

test('COM-08: AdminLayout and AppRouter register commission navigation', async () => {
    const layoutCode = await readFile(layoutPath, 'utf8');
    const routerCode = await readFile(routerPath, 'utf8');

    assert.match(layoutCode, /\/admin\/commissions/);
    assert.match(layoutCode, /Đối soát hoa hồng/);

    assert.match(routerCode, /import AdminCommissionsPage from/);
    assert.match(routerCode, /<Route path="commissions" element=\{<AdminCommissionsPage \/>\} \/>/);
});

test('ADM-06: full commission list exposes date controls and separate filtered pagination', async () => {
    const [page, dateFilter] = await Promise.all([readFile(pagePath, 'utf8'), readFile(dateFilterPath, 'utf8')]);
    assert.match(page, /<AdminDateRangeFilter/);
    assert.match(page, /<AdminPagination label=\{[^}]*'Phân trang hoa hồng'/);
    assert.match(page, /commissionStatus === 'OVERDUE' \? 'due_at'/);
    assert.match(page, /getAdminCommissions\(\{[\s\S]*?from_date:\s*query\.from_date[\s\S]*?to_date:\s*query\.to_date/);
    assert.match(dateFilter, /type="date"/);
    assert.match(dateFilter, /aria-describedby=/);
});

test('ADM-07: remittance queue sends status, submission dates and page from its own filters', async () => {
    const page = await readFile(pagePath, 'utf8');
    assert.match(page, /const query = isQueue \? queueQuery : commissionQuery/);
    assert.match(page, /Ngày Host gửi yêu cầu/);
    assert.match(page, /dateFields=\{isQueue \? undefined : dateFields\}/);
    assert.match(page, /getRemittanceQueue\(\{\s*page: query\.page,\s*status: query\.status,\s*from_date: query\.from_date[^\n]*\n\s*to_date: query\.to_date/);
    assert.match(page, /'Phân trang hàng chờ đối soát'/);
});

test('ADM-08: dashboard commission links share the destination tab and status parameters', async () => {
    const [dashboard, charts, page] = await Promise.all([
        readFile(dashboardPath, 'utf8'),
        readFile(dashboardChartsPath, 'utf8'),
        readFile(pagePath, 'utf8'),
    ]);
    assert.match(dashboard, /const settledCommissionsHref = '\/admin\/commissions\?tab=commissions&commission_status=SETTLED'/);
    assert.match(dashboard, /to=\{settledCommissionsHref\}\s*>\s*Xem hoa hồng đã thu/);
    assert.match(dashboard, /<AdminDashboardCharts\b[^>]*settledCommissionsHref=\{settledCommissionsHref\}/);
    assert.match(charts, /<RevenueChart\b[^>]*settledCommissionsHref=\{settledCommissionsHref\}/);
    assert.match(charts, /<Link to=\{settledCommissionsHref\}[^>]*>\s*Xem tất cả hoa hồng đã thu/);
    assert.match(dashboard, /to="\/admin\/commissions\?tab=queue&queue_status=SUBMITTED"\s*>\s*Xem yêu cầu chờ kiểm tra/);
    assert.match(dashboard, /to="\/admin\/commissions\?tab=commissions&commission_status=OVERDUE"\s*>\s*Xem hoa hồng quá hạn/);
    assert.match(page, /params.get\('tab'\) !== 'commissions'/);
    assert.match(page, /readStatus\(params, 'queue_status', remittanceStatuses, initialQueueQuery.status\)/);
    assert.match(page, /readStatus\(params, 'commission_status', commissionStatuses, initialQuery.status\)/);
});
