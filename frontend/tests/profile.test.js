import test from 'node:test';
import assert from 'node:assert/strict';
import axios, { AxiosError } from 'axios';
import { createProfileEditor, createProfileService, profileDraft, profilePatch, validateProfile, validatePasswordChange, birthDateError, todayInVietnam, avatarFileError } from '../src/services/profile.js';
import { commitProfileUser, subscribeToAuthFailures } from '../src/services/authSession.js';
import { getAuthDestination } from '../src/utils/authNavigation.js';

const user = { id: 1, name: 'Fixture User', email: 'fixture@example.com', phone: null, address: 'Fixture address', gender: null, date_of_birth: null, avatar: '/old.png', role: 'CUSTOMER', status: 'ACTIVE' };
function deferred() {
    let resolve, reject;
    const promise = new Promise((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
function authFixture() {
    const userRef = { current: user }, versionRef = { current: 1 };
    return { userRef, versionRef,
        capture: () => ({ userId: userRef.current?.id, version: versionRef.current }),
        isCurrent: (snapshot) => snapshot.userId === userRef.current?.id && snapshot.version === versionRef.current,
        commit: (next, snapshot) => commitProfileUser(userRef, versionRef, next, snapshot),
    };
}
function clientFixture(handler = () => ({ status: 200, data: { user, message: 'Saved fixture' } })) {
    const requests = [];
    const client = axios.create({ adapter: async (config) => {
        requests.push(config);
        if (config.url === '/sanctum/csrf-cookie') return { status: 204, data: '', headers: {}, config };
        const result = handler(config);
        if (result.network) throw new AxiosError('Fixture offline', 'ECONNABORTED', config);
        const response = { headers: {}, ...result, config };
        if (result.status >= 400) throw new AxiosError('Fixture response', '', config, null, response);
        return response;
    } });
    return { service: createProfileService(client), client, requests };
}

test('profile service uses real wrappers, CSRF and distinct whitelists without an ID in URLs', async () => {
    const { service, requests } = clientFixture();
    assert.deepEqual(await service.get(), user);
    assert.deepEqual((await service.update({ name: 'New', role: 'ADMIN', user_id: 2, password: 'forbidden', email: 'forbidden@example.com' })).user, user);
    await service.changePassword({ current_password: 'Fixture1!', password: 'NewFixture2!', password_confirmation: 'NewFixture2!', role: 'ADMIN' });
    assert.deepEqual(requests.map(({ url }) => url), ['/api/profile', '/sanctum/csrf-cookie', '/api/profile', '/sanctum/csrf-cookie', '/api/profile/password']);
    assert.deepEqual(JSON.parse(requests[2].data), { name: 'New' });
    assert.deepEqual(Object.keys(JSON.parse(requests[4].data)).sort(), ['current_password', 'password', 'password_confirmation']);
});

test('profile patch sends only normalized changed contact fields and explicit nulls for clearing', () => {
    const draft = profileDraft(user);
    assert.deepEqual(profilePatch(user, draft), {});
    assert.deepEqual(profilePatch(user, { ...draft, name: ' New name ', address: ' ', role: 'ADMIN' }), { name: 'New name', address: null });
    assert.deepEqual(profilePatch(user, { ...draft, phone: ' +84 901 234 567 ' }), { phone: '+84 901 234 567' });
});

test('profile validation retains password policy, locks email and validates optional contact bounds', () => {
    const draft = profileDraft(user);
    assert.deepEqual(validateProfile({ ...draft, email: 'legacy@localhost' }), {});
    for (const phone of ['123', '090x1234567', '+1234567890123456', '090)1234(567', '0901234567(',
        '090123456', '09012345678', '+84901234567', '090 1234567', '090.123.4567', '090-123-4567', '０９０１２３４５６７']) assert.ok(validateProfile({ ...draft, phone }).phone);
    for (const phone of ['', '0901234567', '0987654321', ' 0987654321 ']) assert.deepEqual(validateProfile({ ...draft, phone }), {});
    assert.ok(validateProfile({ ...draft, address: 'a'.repeat(501) }).address);
    assert.deepEqual(validatePasswordChange({ current_password: 'Old1!', password: 'NewFixture2!', password_confirmation: 'NewFixture2!' }), {});
    assert.ok(validatePasswordChange({ current_password: '', password: '12345678', password_confirmation: 'different' }).current_password);
    assert.ok(validatePasswordChange({ current_password: 'SameFixture1!', password: 'SameFixture1!', password_confirmation: 'SameFixture1!' }).password);
});

test('profile errors are safe and distinguish known rejections from unknown mutation outcomes', async () => {
    for (const status of [401, 403, 419, 422, 429, 500]) {
        const { service } = clientFixture(() => ({ status, data: { message: 'private SQL trace', errors: { email: ['Invalid fixture'] } } }));
        await assert.rejects(service.update({ name: 'Fixture' }), (error) => {
            assert.equal(error.status, status);
            assert.equal(error.message.includes('SQL'), false);
            assert.equal(error.outcomeUnknown, status === 500);
            assert.equal(error.config, undefined);
            if (status === 422) assert.deepEqual(error.fields, { email: ['Invalid fixture'] });
            return true;
        });
    }
    const { service, requests } = clientFixture(() => ({ network: true }));
    await assert.rejects(service.update({ name: 'Fixture' }), (error) => error.outcomeUnknown);
    assert.equal(requests.filter(({ method }) => method === 'patch').length, 1);
    await assert.rejects(service.get(), (error) => !error.outcomeUnknown);
    await assert.rejects(service.changePassword({}), (error) => error.outcomeUnknown && error.message.includes('đăng nhập lại'));
});

test('profile account errors use the central subscriber and ownership denial does not clear auth', async () => {
    for (const [status, code, count] of [[401, undefined, 1], [403, 'ACCOUNT_BLOCKED', 1], [403, undefined, 0]]) {
        const { client, service } = clientFixture(() => ({ status, data: { code } }));
        let calls = 0;
        const cleanup = subscribeToAuthFailures(client, () => calls++, () => 1);
        await assert.rejects(service.get());
        assert.equal(calls, count); cleanup();
    }
});

test('editor saves normalized changes, synchronizes auth, and cancel restores latest saved values without an API call', async () => {
    let calls = 0;
    const auth = authFixture();
    const editor = createProfileEditor({ get: async () => user, update: async (patch) => {
        calls++; assert.deepEqual(patch, { name: 'New name' }); return { user: { ...user, ...patch }, message: 'Saved' };
    } }, auth);
    await editor.load(); editor.edit(); editor.change('name', ' New name '); await editor.save();
    assert.equal(auth.userRef.current.name, 'New name');
    editor.edit(); editor.change('name', 'Discard'); editor.cancel();
    assert.equal(editor.getSnapshot().draft.name, 'New name');
    editor.edit(); await editor.save(); assert.equal(calls, 1);
});

test('editor preserves editable drafts on 422 and blocks invalid/no-change submissions', async () => {
    let calls = 0;
    const error = { status: 422, operation: 'update', fields: { phone: ['Invalid fixture'] } };
    const editor = createProfileEditor({ get: async () => user, update: async () => { calls++; throw error; } }, authFixture());
    await editor.load(); editor.edit(); editor.change('phone', 'short'); await editor.save(); assert.equal(calls, 0);
    editor.change('phone', '0901234567'); await assert.rejects(editor.save());
    assert.equal(editor.getSnapshot().draft.phone, '0901234567');
    assert.equal(editor.getSnapshot().error, error); assert.equal(editor.getSnapshot().pending, false);
});

test('malformed phone never reaches a mutation and email cannot be edited or sent', async () => {
    let calls = 0;
    const editor = createProfileEditor({ get: async () => user, update: async () => { calls++; } }, authFixture());
    await editor.load(); editor.edit(); editor.change('email', 'new@example.com'); await editor.save();
    assert.equal(editor.getSnapshot().draft.email, user.email); assert.equal(calls, 0);
    assert.deepEqual(profilePatch(user, { ...profileDraft(user), email: 'new@example.com' }), {});
    for (const value of ['09012345678', '090.123.4567']) {
        editor.edit(); editor.change('phone', value); await editor.save();
        assert.ok(editor.getSnapshot().error.fields.phone);
        assert.equal(editor.getSnapshot().draft.phone, value);
        editor.cancel();
    }
    assert.equal(calls, 0);
});

test('editor prevents duplicate mutations and refuses retry after uncertain save until a reload', async () => {
    let calls = 0; const pending = deferred();
    const editor = createProfileEditor({ get: async () => user, update: () => { calls++; return pending.promise; } }, authFixture());
    await editor.load(); editor.edit(); editor.change('name', 'New name');
    const saving = editor.save(); assert.equal(await editor.save(), null); assert.equal(calls, 1);
    pending.reject({ operation: 'update', outcomeUnknown: true }); await assert.rejects(saving);
    assert.equal(await editor.save(), null); assert.equal(calls, 1);
    editor.cancel();
    assert.equal(await editor.changePassword({}), null);
    assert.equal(editor.getSnapshot().needsReload, true);
    await editor.load(); assert.equal(editor.getSnapshot().needsReload, false);
    assert.equal(editor.getSnapshot().draft.name, user.name);
});

test('unmount, logout and changed users prevent late profile success/error from committing auth', async () => {
    for (const invalidate of [
        (editor) => editor.cancelRequests(),
        (editor, auth) => { ++auth.versionRef.current; auth.userRef.current = null; },
        (editor, auth) => { ++auth.versionRef.current; auth.userRef.current = { ...user, id: 2 }; },
    ]) {
        for (const rejects of [false, true]) {
            const pending = deferred(); const auth = authFixture();
            const editor = createProfileEditor({ get: () => pending.promise }, auth);
            const loading = editor.load(); invalidate(editor, auth);
            if (rejects) pending.reject(new Error('Late fixture error')); else pending.resolve({ ...user, name: 'Late' });
            assert.equal(await loading, null);
            assert.notEqual(auth.userRef.current?.name, 'Late');
            assert.equal(editor.getSnapshot().saved, null);
        }
    }
});

test('a failed reload does not unlock an uncertain profile mutation', async () => {
    let reads = 0, writes = 0;
    const editor = createProfileEditor({
        get: async () => { if (++reads > 1) throw { operation: 'read', status: 0 }; return user; },
        update: async () => { writes++; throw { operation: 'update', outcomeUnknown: true }; },
    }, authFixture());
    await editor.load(); editor.edit(); editor.change('name', 'Uncertain'); await assert.rejects(editor.save());
    await assert.rejects(editor.load());
    assert.equal(editor.getSnapshot().needsReload, true);
    assert.equal(await editor.save(), null); assert.equal(writes, 1);
});

test('auth transitions cannot start profile requests with an invalid snapshot', async () => {
    const auth = authFixture(); auth.isCurrent = () => false;
    let calls = 0;
    const editor = createProfileEditor({ get: async () => { calls++; return user; } }, auth);
    assert.equal(await editor.load(), null); assert.equal(calls, 0);
});

test('profile commit invalidates an older restore version and refuses identity changes', () => {
    const auth = authFixture(); const olderRestoreVersion = auth.versionRef.current;
    assert.equal(auth.commit({ ...user, name: 'Saved' }, auth.capture()), true);
    assert.notEqual(olderRestoreVersion, auth.versionRef.current);
    assert.equal(auth.commit({ ...user, id: 2 }, auth.capture()), false);
    assert.equal(auth.userRef.current.name, 'Saved');
});

test('a late PATCH cannot overwrite auth or the editor after leaving the page or logging out', async () => {
    for (const invalidation of ['unmount', 'logout', 'another-user']) {
        const pending = deferred(); const auth = authFixture();
        const editor = createProfileEditor({ get: async () => user, update: () => pending.promise }, auth);
        await editor.load(); editor.edit(); editor.change('name', 'New name');
        const saving = editor.save();
        if (invalidation === 'unmount') editor.cancelRequests();
        else { ++auth.versionRef.current; auth.userRef.current = invalidation === 'logout' ? null : { ...user, id: 2 }; }
        pending.resolve({ user: { ...user, name: 'Late name' }, message: 'Late success' });
        assert.equal(await saving, null);
        assert.notEqual(auth.userRef.current?.name, 'Late name');
        assert.equal(editor.getSnapshot().saved.name, user.name);
    }
});

test('password mutations share the pending guard without entering the contact draft or auth state', async () => {
    const pending = deferred(); const auth = authFixture(); let calls = 0;
    const editor = createProfileEditor({ get: async () => user, changePassword: () => { calls++; return pending.promise; } }, auth);
    await editor.load();
    const operation = editor.changePassword({ current_password: 'OldFixture1!', password: 'NewFixture2!', password_confirmation: 'NewFixture2!' });
    assert.equal(await editor.changePassword({}), null); assert.equal(calls, 1);
    pending.resolve({ message: 'Changed' }); await operation;
    assert.deepEqual(Object.keys(editor.getSnapshot().draft).sort(), ['address', 'date_of_birth', 'email', 'gender', 'name', 'phone']);
    assert.equal(editor.getSnapshot().notice, 'Changed'); assert.deepEqual(auth.userRef.current, user);
});

test('returnTo permits only the new self-profile routes for the matching role', () => {
    assert.equal(getAuthDestination('HOST', '/host/profile?tab=contact'), '/host/profile?tab=contact');
    assert.equal(getAuthDestination('ADMIN', '/admin/profile'), '/admin/profile');
    assert.equal(getAuthDestination('CUSTOMER', '/host/profile'), '/customer');
    assert.equal(getAuthDestination('HOST', '/admin/profile'), '/host');
    assert.equal(getAuthDestination('ADMIN', '/admin/profile/2'), '/admin');
});

test('birth dates validate real calendar dates and use Vietnam day boundaries without timezone conversion', () => {
    assert.equal(todayInVietnam(new Date('2026-10-28T18:00:00Z')), '2026-10-29');
    assert.equal(birthDateError('2000-02-29', '2026-10-29'), null);
    assert.equal(birthDateError('2026-10-29', '2026-10-29'), null);
    for (const value of ['2001-02-29', '2020-04-31', '2020-13-01', '1899-12-31', '2026-10-30', '29/10/2000']) {
        assert.ok(birthDateError(value, '2026-10-29'));
    }
    const draft = profileDraft(user);
    assert.ok(validateProfile({ ...draft, gender: 'INVALID' }).gender);
    assert.deepEqual(profilePatch(user, { ...draft, gender: 'FEMALE', date_of_birth: '2000-02-29' }), { gender: 'FEMALE', date_of_birth: '2000-02-29' });
    assert.deepEqual(profilePatch({ ...user, gender: 'MALE', date_of_birth: '2000-01-01' }, draft), { gender: null, date_of_birth: null });
});

test('avatar service sends multipart data to the upload endpoint without unrelated fields', async () => {
    const { service, requests } = clientFixture();
    const file = new Blob(['fixture'], { type: 'image/png' });
    const result = await service.uploadAvatar(file);
    assert.deepEqual(result.user, user);
    assert.deepEqual(requests.map(({ url }) => url), ['/sanctum/csrf-cookie', '/api/profile/avatar']);
    assert.equal(requests[1].method, 'post');
    assert.ok(requests[1].data instanceof FormData);
    assert.deepEqual([...requests[1].data.keys()], ['avatar']);
    assert.equal(requests[1].data.get('avatar').type, 'image/png');
});

test('invalid avatar type, empty files and size limits are rejected before any upload', async () => {
    let calls = 0;
    const editor = createProfileEditor({ get: async () => user, uploadAvatar: async () => calls++ }, authFixture());
    await editor.load();
    for (const file of [{ type: 'image/svg+xml', size: 100 }, { type: 'image/png', size: 0 }, { type: 'image/jpeg', size: 2097153 }]) {
        assert.ok(avatarFileError(file)); await editor.uploadAvatar(file); assert.equal(calls, 0);
    }
    for (const type of ['image/png', 'image/jpeg', 'image/webp']) assert.equal(avatarFileError({ type, size: 2097152 }), null);
});

test('uploading an avatar syncs auth while preserving an open contact draft and prevents duplicate requests', async () => {
    const pending = deferred(); const auth = authFixture(); let calls = 0;
    const editor = createProfileEditor({ get: async () => user, uploadAvatar: () => { calls++; return pending.promise; } }, auth);
    await editor.load(); editor.edit(); editor.change('name', 'Unsaved name');
    const uploading = editor.uploadAvatar({ type: 'image/png', size: 50 });
    assert.equal(await editor.uploadAvatar({ type: 'image/png', size: 50 }), null); assert.equal(calls, 1);
    pending.resolve({ user: { ...user, avatar: '/new-avatar.png' }, message: 'Saved avatar' }); await uploading;
    assert.equal(auth.userRef.current.avatar, '/new-avatar.png');
    assert.equal(editor.getSnapshot().draft.name, 'Unsaved name');
    assert.equal(editor.getSnapshot().editing, true);
    editor.cancel(); assert.equal(editor.getSnapshot().draft.name, user.name);
    assert.equal(editor.getSnapshot().saved.avatar, '/new-avatar.png');
    await editor.uploadAvatar({ type: 'image/svg+xml', size: 50 });
    assert.equal(editor.getSnapshot().notice, '');
});

test('avatar timeouts are uncertain, require reconciliation and are never retried automatically', async () => {
    const { service, requests } = clientFixture(() => ({ network: true }));
    await assert.rejects(service.uploadAvatar(new Blob(['fixture'], { type: 'image/png' })), (error) => error.outcomeUnknown && error.operation === 'avatar');
    assert.equal(requests.filter(({ method }) => method === 'post').length, 1);
    const editor = createProfileEditor({ get: async () => user, uploadAvatar: async () => { throw { operation: 'avatar', outcomeUnknown: true }; } }, authFixture());
    await editor.load(); await assert.rejects(editor.uploadAvatar({ type: 'image/png', size: 50 }));
    assert.equal(editor.getSnapshot().needsReload, true);
    assert.equal(await editor.uploadAvatar({ type: 'image/png', size: 50 }), null);
});

test('avatar response after logout cannot commit a new cached user or profile image', async () => {
    const auth = authFixture(); const pending = deferred();
    const editor = createProfileEditor({ get: async () => user, uploadAvatar: () => pending.promise }, auth);
    await editor.load(); const uploading = editor.uploadAvatar({ type: 'image/png', size: 50 });
    ++auth.versionRef.current; auth.userRef.current = null;
    pending.resolve({ user: { ...user, avatar: '/late.png' }, message: 'Late response' });
    assert.equal(await uploading, null); assert.equal(auth.userRef.current, null);
    assert.equal(editor.getSnapshot().saved.avatar, user.avatar);
});
