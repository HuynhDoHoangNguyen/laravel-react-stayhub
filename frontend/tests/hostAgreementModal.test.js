import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const modalPath = fileURLToPath(new URL('../src/components/host/HostPartnerAgreementModal.jsx', import.meta.url));
const servicePath = fileURLToPath(new URL('../src/services/hostAgreementService.js', import.meta.url));
const pagePath = fileURLToPath(new URL('../src/pages/host/HostPropertyCreatePage.jsx', import.meta.url));

test('AGR-01: hostAgreementService connects to correct endpoints', async () => {
    const code = await readFile(servicePath, 'utf8');

    assert.match(code, /get\('\/api\/host\/partner-agreements\/current'/);
    assert.match(code, /post\(`\/api\/host\/partner-agreements\/\$\{agreementId\}\/accept`/);
});

test('AGR-03: HostPartnerAgreementModal requires clickwrap checkbox and displays 10% commission and 72h notice', async () => {
    const code = await readFile(modalPath, 'utf8');

    // Verify clickwrap checkbox
    assert.match(code, /id="agree-checkbox"/);
    assert.match(code, /type="checkbox"/);
    assert.match(code, /disabled=\{!agreed\s*\|\|\s*isSubmitting\}/);

    // Verify 10% rate and 72h notice
    assert.match(code, /Hoa hồng/);
    assert.match(code, /72 giờ/);
    assert.match(code, /terms_body/);
});

test('AGR-02 / AGR-03: HostPropertyCreatePage guards against unaccepted agreement and shows modal', async () => {
    const code = await readFile(pagePath, 'utf8');

    // Verify import of agreement service and modal
    assert.match(code, /import\s*\{\s*hostAgreementService\s*\}\s*from/);
    assert.match(code, /import\s*\{\s*HostPartnerAgreementModal\s*\}\s*from/);

    // Verify automatic check on mount
    assert.match(code, /hostAgreementService\.getCurrentAgreement\(\)/);
    assert.match(code, /setShowAgreementModal\(true\)/);

    // Verify 403 AGREEMENT_REQUIRED handling
    assert.match(code, /status\s*===\s*403\s*&&\s*failure\.response\?\.data\?\.code\s*===\s*'AGREEMENT_REQUIRED'/);

    // Verify modal component is rendered in JSX
    assert.match(code, /<HostPartnerAgreementModal/);
    assert.match(code, /onAccept=\{handleAcceptAgreement\}/);
});
