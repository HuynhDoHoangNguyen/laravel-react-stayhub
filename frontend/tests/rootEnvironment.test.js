import test from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { loadEnv, resolveConfig } from 'vite';

const frontend = fileURLToPath(new URL('..', import.meta.url));
const root = path.resolve(frontend, '..');

test('Vite resolves root envDir while keeping frontend root and public prefix', async () => {
  const config = await resolveConfig({ root: frontend, mode: 'development' }, 'serve');
  assert.equal(path.resolve(config.envDir), root);
  assert.equal(path.resolve(config.root), path.resolve(frontend));
  assert.equal(config.envPrefix ?? 'VITE_', 'VITE_');
  assert.ok(Object.keys(config.env).every(key => key.startsWith('VITE_') || ['BASE_URL', 'MODE', 'DEV', 'PROD'].includes(key)));
  assert.equal(existsSync(path.join(frontend, '.env')), false);
  assert.equal(existsSync(path.join(frontend, '.env.example')), false);
});

test('Vite loads public values and process overrides without exposing private canaries', () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'stayhub-env-'));
  const key = 'VITE_STAYHUB_ENV_TEST_OVERRIDE';
  const previous = process.env[key];
  try {
    writeFileSync(path.join(dir, '.env'), 'VITE_STAYHUB_ENV_TEST_OVERRIDE=file-value\nVITE_STAYHUB_ENV_TEST_PUBLIC=public-value\nSTAYHUB_PRIVATE_CANARY=private-value\n');
    process.env[key] = 'process-value';
    const env = loadEnv('development', dir);
    assert.equal(env[key], 'process-value');
    assert.equal(env.VITE_STAYHUB_ENV_TEST_PUBLIC, 'public-value');
    assert.equal(Object.hasOwn(env, 'STAYHUB_PRIVATE_CANARY'), false);
  } finally {
    if (previous === undefined) delete process.env[key];
    else process.env[key] = previous;
    rmSync(dir, { recursive: true, force: true });
  }
});
