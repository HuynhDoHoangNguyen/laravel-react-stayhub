import { useLayoutEffect, useRef, useState } from 'react';
import { validatePasswordChange } from '../../services/profile';

const EMPTY = { current_password: '', password: '', password_confirmation: '' };
const FIELDS = [
    ['current_password', 'Mật khẩu hiện tại', 'current-password'],
    ['password', 'Mật khẩu mới', 'new-password'],
    ['password_confirmation', 'Xác nhận mật khẩu mới', 'new-password'],
];

export default function ChangePasswordForm({ editor, state }) {
    const [values, setValues] = useState(EMPTY);
    const [localErrors, setLocalErrors] = useState({});
    const active = useRef(true);
    const refs = useRef({});
    useLayoutEffect(() => { active.current = true; return () => { active.current = false; }; }, []);
    const errors = { ...(state.error?.operation === 'password' ? state.error.fields : {}), ...localErrors };
    async function submit(event) {
        event.preventDefault();
        if (state.pending || state.needsReload) return;
        const fields = validatePasswordChange(values);
        setLocalErrors(fields);
        if (Object.keys(fields).length) { refs.current[Object.keys(fields)[0]]?.focus(); return; }
        try { await editor.changePassword(values); } catch { /* The editor renders the safe server error. */ }
        if (active.current) {
            setValues(EMPTY);
            const serverFields = editor.getSnapshot().error?.fields || {};
            requestAnimationFrame(() => refs.current[FIELDS.find(([name]) => serverFields[name])?.[0]]?.focus());
        }
    }
    return (
        <section className="rounded-3xl border border-border/60 bg-white p-6 shadow-xs sm:p-8">
            <h3 className="font-display text-2xl font-bold text-primary">Đổi mật khẩu</h3>
            <p id="profile-password-hint" className="mt-2 text-xs text-muted-foreground">Mật khẩu mới ít nhất 8 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</p>
            <form onSubmit={submit} noValidate aria-busy={state.pending} className="mt-5 space-y-4">
                {FIELDS.map(([name, label, autoComplete]) => (
                    <div key={name}>
                        <label htmlFor={`profile-${name}`} className="text-xs font-semibold text-ink">{label}</label>
                        <input id={`profile-${name}`} type="password" required autoComplete={autoComplete}
                            ref={(input) => { refs.current[name] = input; }} value={values[name]}
                            maxLength={name === 'current_password' ? 4096 : 72} disabled={state.pending}
                            onChange={(event) => { setValues((current) => ({ ...current, [name]: event.target.value })); setLocalErrors({}); }}
                            aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `profile-${name}-error` : 'profile-password-hint'}
                            className="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-60" />
                        {errors[name] && <p id={`profile-${name}-error`} className="mt-1 text-xs text-destructive">{errors[name][0]}</p>}
                    </div>
                ))}
                <button type="submit" disabled={state.pending || state.needsReload} className="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">{state.pending ? 'Đang xử lý...' : 'Đổi mật khẩu'}</button>
            </form>
        </section>
    );
}
