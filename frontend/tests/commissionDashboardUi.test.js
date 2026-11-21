import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const hostServicePath = fileURLToPath(new URL('../src/services/hostCommissionService.js', import.meta.url));
const adminServicePath = fileURLToPath(new URL('../src/services/adminCommissionService.js', import.meta.url));
const hostDashboardPath = fileURLToPath(new URL('../src/pages/host/HostDashboardPage.jsx', import.meta.url));
const adminDashboardPath = fileURLToPath(new URL('../src/pages/admin/AdminDashboardPage.jsx', import.meta.url));

test('RPT-01: hostCommissionService defines getFinancialSummary endpoint', async () => {
    const code = await readFile(hostServicePath, 'utf8');

    assert.match(code, /getFinancialSummary/);
    assert.match(code, /api\.get\('\/api\/host\/commissions\/dashboard\/financial-summary'/);
});

test('RPT-02: adminCommissionService defines getCommissionSummary endpoint', async () => {
    const code = await readFile(adminServicePath, 'utf8');

    assert.match(code, /getCommissionSummary/);
    assert.match(code, /api\.get\('\/api\/admin\/dashboard\/commission-summary'/);
});

test('RPT-01: HostDashboardPage fetches financial summary and renders gross, settled, and net metrics', async () => {
    const code = await readFile(hostDashboardPath, 'utf8');

    // Service call
    assert.match(code, /hostCommissionService\.getFinancialSummary/);

    // Key financial metrics
    assert.match(code, /net_revenue_estimate/);
    assert.match(code, /gross_revenue/);
    assert.match(code, /commission_settled/);
    assert.match(code, /commission_due/);

    // Commission card navigation
    assert.match(code, /to="\/host\/commissions"/);
});

test('RPT-02: AdminDashboardPage renders settled platform revenue, outstanding receivables, and remittance alert', async () => {
    const code = await readFile(adminDashboardPath, 'utf8');

    // Service call
    assert.match(code, /adminCommissionService\.getCommissionSummary/);

    // AC-21 strict separation: Settled revenue vs Outstanding receivables
    assert.match(code, /total_settled/);
    assert.match(code, /total_outstanding/);
    assert.match(code, /total_under_review/);
    assert.match(code, /total_overdue/);
    assert.match(code, /pending_remittances_count/);

    // Direct link to remittance queue
    assert.match(code, /to="\/admin\/commissions"/);
});
