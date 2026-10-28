import { useRef, useState } from 'react';
import { GENDERS, todayInVietnam } from '../../services/profile';

const FIELDS = [
    { name: 'name', label: 'Họ và tên', autoComplete: 'name', maxLength: 255 },
    { name: 'email', label: 'Địa chỉ email', type: 'email', autoComplete: 'email', maxLength: 254 },
    { name: 'phone', label: 'Số điện thoại', type: 'tel', autoComplete: 'tel', maxLength: 20 },
    { name: 'address', label: 'Địa chỉ liên hệ', autoComplete: 'street-address', maxLength: 500 },
    { name: 'gender', label: 'Giới tính', type: 'select' },
    { name: 'date_of_birth', label: 'Ngày sinh', type: 'date', autoComplete: 'bday' },
];

export default function ProfileForm({ editor, state }) {
    const refs = useRef({});
    const [inputErrors, setInputErrors] = useState({});
    const errors = { ...(state.error?.operation === 'update' ? state.error.fields || {} : {}), ...inputErrors };
    async function submit(event) {
        event.preventDefault();
        if (state.pending) return;
        if (refs.current.date_of_birth?.validity.badInput) {
            setInputErrors({ date_of_birth: ['Vui lòng nhập ngày sinh hợp lệ.'] });
            refs.current.date_of_birth.focus();
            return;
        }
        setInputErrors({});
        try { await editor.save(); } catch { /* The editor exposes safe field errors. */ }
        const fields = editor.getSnapshot().error?.fields || {};
        requestAnimationFrame(() => refs.current[FIELDS.find(({ name }) => fields[name])?.name]?.focus());
    }
    return (
        <form onSubmit={submit} noValidate aria-busy={state.pending} className="mt-6 space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                {FIELDS.map(({ name, label, type = 'text', autoComplete, maxLength }) => (
                    <div key={name} className={name === 'address' ? 'sm:col-span-2' : ''}>
                        <label htmlFor={`profile-${name}`} className="text-xs font-semibold text-ink">{label}{name === 'name' ? ' *' : ''}</label>
                        {type === 'select' ? <select id={`profile-${name}`} value={state.draft[name]} disabled={state.pending}
                            ref={(input) => { refs.current[name] = input; }} onChange={(event) => { editor.change(name, event.target.value); setInputErrors({}); }}
                            aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `profile-${name}-error` : undefined}
                            className="mt-2 h-11 w-full rounded-xl border border-border bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-60">
                            <option value="">Không muốn cung cấp</option>
                            {Object.entries(GENDERS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select> : <input id={`profile-${name}`} ref={(input) => { refs.current[name] = input; }}
                            type={type} autoComplete={autoComplete} maxLength={maxLength}
                            readOnly={name === 'email'}
                            min={type === 'date' ? '1900-01-01' : undefined} max={type === 'date' ? todayInVietnam() : undefined}
                            inputMode={name === 'phone' ? 'numeric' : undefined}
                            pattern={name === 'phone' ? '[0-9]{10}' : undefined}
                            required={name === 'name'} value={name === 'email' ? state.saved.email : state.draft[name]}
                            disabled={state.pending} onChange={(event) => { editor.change(name, event.target.value); setInputErrors({}); }}
                            aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `profile-${name}-error` : undefined}
                            className={`mt-2 h-11 w-full rounded-xl border border-border px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-60 ${name === 'email' ? 'cursor-default bg-surface text-muted-foreground' : 'bg-background'}`} />}
                        {name === 'email' && <p className="mt-1 text-xs text-muted-foreground">Email đăng nhập không thể thay đổi.</p>}
                        {errors[name] && <p id={`profile-${name}-error`} className="mt-1 text-xs text-destructive">{errors[name][0]}</p>}
                    </div>
                ))}
            </div>
            <div className="flex flex-wrap gap-3">
                <button type="submit" disabled={state.pending || state.needsReload} className="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">{state.pending ? 'Đang xử lý...' : 'Lưu hồ sơ'}</button>
                <button type="button" disabled={state.pending} onClick={() => editor.cancel()} className="rounded-xl border border-border px-5 py-2.5 text-sm disabled:opacity-50">Hủy</button>
            </div>
        </form>
    );
}
