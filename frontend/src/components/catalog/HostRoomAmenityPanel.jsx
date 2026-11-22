import { useEffect, useRef, useState } from 'react';
import { Minus, Plus, RefreshCw, Save } from 'lucide-react';
import { hostRoomAmenityService } from '../../services/hostRoomAmenityService';

const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
const primaryButton = buttonClass + ' bg-primary text-primary-foreground hover:bg-primary/90';
const secondaryButton = buttonClass + ' border border-border bg-card text-primary hover:bg-surface';
const removeButtonClass = buttonClass + ' border border-red-200 bg-card text-red-800 hover:bg-red-50';
const errorMessages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền quản lý tiện nghi của phòng này.',
    404: 'Không tìm thấy phòng.',
    419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
};

export default function HostRoomAmenityPanel({ roomId, amenities, ready }) {
    const request = useRef(null);
    const openButton = useRef(null);
    const removeTrigger = useRef(null);
    const cancelRemove = useRef(null);
    const sectionTitle = useRef(null);
    const reloadButton = useRef(null);
    const formTitle = useRef(null);
    const select = useRef(null);
    const [open, setOpen] = useState(false);
    const [removing, setRemoving] = useState(null);
    const [state, setState] = useState({ status: 'idle' });
    const [selection, setSelection] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => () => request.current?.abort(), []);
    useEffect(() => {
        if (open && state.status === 'ready') (select.current ?? formTitle.current)?.focus();
    }, [open, state.status]);
    useEffect(() => {
        if (removing) cancelRemove.current?.focus();
    }, [removing]);

    async function loadOptions() {
        if (!ready || request.current) return;
        const controller = new AbortController();
        request.current = controller;
        setSelection('');
        setState((previous) => ({ ...previous, status: 'loading', error: '' }));
        try {
            const options = await hostRoomAmenityService.list(roomId, { signal: controller.signal });
            if (!controller.signal.aborted) setState({ status: 'ready', options });
        } catch (failure) {
            if (controller.signal.aborted) return;
            const status = failure.response?.status;
            const denied = [401, 403, 404].includes(status);
            setState((previous) => ({ ...previous, status: 'error', options: denied ? undefined : previous.options, denied,
                error: errorMessages[status] || 'Không thể tải danh sách tiện nghi. Vui lòng thử lại.' }));
        } finally {
            request.current = null;
        }
    }

    function closeForm() {
        setOpen(false);
        requestAnimationFrame(() => openButton.current?.focus());
    }

    function submit(event) {
        event.preventDefault();
        const selected = state.options?.available.find((item) => String(item.id) === selection);
        if (state.status === 'ready' && selected) void mutate('assign', selected);
    }

    function closeRemoval() {
        setRemoving(null);
        setState((previous) => ({ ...previous, error: '' }));
        requestAnimationFrame(() => removeTrigger.current?.focus());
    }

    async function mutate(action, item) {
        if (!ready || request.current || state.needsReload || state.denied) return;
        const isRemoval = action === 'remove';
        const controller = new AbortController();
        request.current = controller;
        setState({ ...state, status: 'saving', error: '' });
        try {
            const options = await hostRoomAmenityService[action](roomId, item.id, { signal: controller.signal });
            if (controller.signal.aborted) return;
            setState({ status: 'ready', options });
            if (isRemoval) {
                setSuccess('Đã gỡ tiện nghi ' + item.name + ' khỏi phòng.');
                setRemoving(null);
                requestAnimationFrame(() => sectionTitle.current?.focus());
            } else {
                setSuccess('Đã gán tiện nghi ' + item.name + ' cho phòng.');
                closeForm();
            }
        } catch (failure) {
            if (controller.signal.aborted) return;
            const status = failure.response?.status;
            const denied = [401, 403, 404].includes(status);
            const needsReload = status === 422 || Boolean(failure.outcomeUnknown);
            const fallbackError = isRemoval ? 'Không thể gỡ tiện nghi. Vui lòng thử lại.' : 'Không thể gán tiện nghi. Vui lòng thử lại.';
            const error = failure.outcomeUnknown
                ? 'Chưa xác định được kết quả. Hãy kiểm tra lại danh sách tiện nghi trước khi gửi lại.'
                : status === 422
                    ? 'Danh sách tiện nghi cần được tải lại. Vui lòng kiểm tra và chọn lại.'
                    : errorMessages[status] || fallbackError;
            setState({ status: needsReload || denied ? 'error' : 'ready',
                options: denied ? undefined : state.options, denied, needsReload, error });
            if (isRemoval && (needsReload || denied)) {
                setRemoving(null);
                requestAnimationFrame(() => (reloadButton.current ?? sectionTitle.current)?.focus());
            }
        } finally {
            request.current = null;
        }
    }

    const assigned = state.denied ? null : state.options?.assigned ?? amenities;
    const available = state.options?.available ?? [];
    const saving = state.status === 'saving';
    const loading = state.status === 'loading';
    const actionsDisabled = !ready || saving || loading || state.needsReload || state.denied;

    return <section aria-labelledby="room-amenities-title" aria-busy={saving || loading}
        className="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
        <header className="flex flex-wrap items-center justify-between gap-3">
            <h2 ref={sectionTitle} tabIndex={-1} id="room-amenities-title" className="rounded-sm text-lg font-semibold text-primary focus-visible:ring-2 focus-visible:ring-primary">Tiện nghi</h2>
            <button ref={openButton} type="button" disabled={actionsDisabled || open || removing !== null} aria-expanded={open}
                onClick={() => { setSuccess(''); setOpen(true); void loadOptions(); }} className={secondaryButton}>
                <Plus size={16} aria-hidden="true" />
                Gán tiện nghi
            </button>
        </header>
        {success && <p role="status" className="break-words rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{success}</p>}
        {state.error && <p role="alert" className="break-words rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{state.error}</p>}
        {loading && <p role="status" className="text-sm text-muted-foreground">Đang tải danh sách tiện nghi...</p>}
        {state.status === 'error' && !state.denied && <button ref={reloadButton} type="button" disabled={!ready} onClick={loadOptions} className={secondaryButton}>
            <RefreshCw size={16} aria-hidden="true" />
            Tải lại danh sách
        </button>}
        {assigned?.length === 0 && <p className="text-sm text-muted-foreground">Chưa gán tiện nghi.</p>}
        {assigned?.length > 0 && <ul className="grid gap-3 sm:grid-cols-2">
            {assigned.map((item) => <li key={item.id} className="min-w-0 space-y-3 rounded-xl border border-border/70 bg-surface/50 p-3.5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="min-w-0">
                        <p className="break-words text-sm font-medium">{item.name}</p>
                        <p className="mt-1 text-sm text-muted-foreground">{item.status ? 'Đang hoạt động' : 'Ngưng hoạt động'}</p>
                    </div>
                    <button type="button" aria-label={'Gỡ tiện nghi ' + item.name} aria-expanded={removing?.id === item.id}
                        disabled={actionsDisabled || open || removing !== null} className={removeButtonClass}
                        onClick={(event) => {
                            removeTrigger.current = event.currentTarget;
                            setSuccess('');
                            setState((previous) => ({ ...previous, error: '' }));
                            setRemoving(item);
                        }}>
                        <Minus size={16} aria-hidden="true" />
                        Gỡ
                    </button>
                </div>
                {removing?.id === item.id && <div role="group" aria-label={'Xác nhận gỡ ' + item.name}
                    className="space-y-3 rounded-xl border border-red-200 bg-red-50 p-3">
                    <p className="break-words text-sm text-red-900">Gỡ <strong>{item.name}</strong> khỏi phòng này?</p>
                    <div className="flex flex-wrap gap-2">
                        <button ref={cancelRemove} type="button" disabled={saving} onClick={closeRemoval} className={secondaryButton}>Hủy</button>
                        <button type="button" disabled={actionsDisabled} onClick={() => { void mutate('remove', item); }}
                            className={buttonClass + ' bg-red-700 text-white hover:bg-red-800'}>
                            {saving ? 'Đang gỡ...' : 'Xác nhận gỡ'}
                        </button>
                    </div>
                </div>}
            </li>)}
        </ul>}
        {open && <form onSubmit={submit} aria-labelledby="room-amenity-form-title" aria-busy={saving || loading}
            className="min-w-0 space-y-4 rounded-xl border border-border bg-surface/40 p-4">
            <h3 ref={formTitle} tabIndex={-1} id="room-amenity-form-title" className="font-semibold text-primary">Chọn tiện nghi</h3>
            {state.status === 'ready' && available.length === 0 && <p role="status" className="text-sm text-muted-foreground">Phòng không còn tiện nghi có thể gán.</p>}
            {(state.status === 'ready' || saving) && available.length > 0 && <div className="min-w-0">
                <label htmlFor="room-amenity-choice" className="text-sm font-medium">Tiện nghi muốn gán</label>
                <select ref={select} id="room-amenity-choice" value={selection} onChange={(event) => setSelection(event.target.value)}
                    required disabled={saving}
                    className="mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-border bg-background px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">Chọn một tiện nghi</option>
                    {available.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
            </div>}
            <div className="flex flex-wrap gap-2">
                {(state.status === 'ready' || saving) && available.length > 0 && <button type="submit" disabled={!ready || saving || !selection}
                    className={primaryButton}>
                    <Save size={16} aria-hidden="true" />
                    {saving ? 'Đang lưu...' : 'Lưu tiện nghi'}
                </button>}
                <button type="button" disabled={saving || loading} onClick={closeForm} className={secondaryButton}>Đóng</button>
            </div>
        </form>}
    </section>;
}
