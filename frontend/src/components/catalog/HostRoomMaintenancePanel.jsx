import { useEffect, useRef, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react';
import { hostRoomMaintenanceService } from '../../services/hostRoomMaintenanceService';

const statuses = {
    SCHEDULED: { label: 'Đã lên lịch', color: 'border-sky-200 bg-sky-50 text-sky-900' },
    IN_PROGRESS: { label: 'Đang thực hiện', color: 'border-amber-200 bg-amber-50 text-amber-900' },
    COMPLETED: { label: 'Hoàn tất', color: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
    CANCELLED: { label: 'Đã hủy', color: 'border-border bg-surface text-muted-foreground' },
};
const errors = {
    400: 'Không tìm thấy phòng.',
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền xem lịch bảo trì của phòng này.',
    404: 'Không tìm thấy phòng.',
    422: 'Trang lịch bảo trì không hợp lệ.',
};
const buttonBaseClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
const buttonClass = buttonBaseClass + ' border-border bg-card text-primary hover:bg-surface';
const inputClass = 'min-h-11 w-full min-w-0 rounded-xl border border-border bg-card px-3 py-2 text-sm text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary';
const emptyDraft = { start_date: '', end_date: '', reason: '' };
const conflictMessages = {
    CATALOG_ROOM_BOOKING_CONFLICT: 'Khoảng ngày này trùng với booking đang giữ phòng hoặc khách đang lưu trú.',
    CATALOG_ROOM_MAINTENANCE_CONFLICT: 'Khoảng ngày này trùng với lịch bảo trì của phòng.',
    CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT: 'Lịch đã đổi trạng thái. Danh sách đang được tải lại.',
};
const transitionCopy = {
    start: {
        title: 'Bắt đầu bảo trì?',
        detail: 'Phòng sẽ ngừng nhận đặt cho đến khi bạn xác nhận hoàn tất bảo trì.',
        submit: 'Xác nhận bắt đầu',
        busy: 'Đang bắt đầu...',
        success: 'Đã bắt đầu bảo trì.',
    },
    cancel: {
        title: 'Hủy lịch bảo trì này?',
        detail: 'Lịch sẽ được đánh dấu Đã hủy và lưu trong lịch sử phòng.',
        submit: 'Xác nhận hủy',
        busy: 'Đang hủy...',
        success: 'Đã hủy lịch bảo trì. Lịch vẫn được lưu trong danh sách.',
    },
    complete: {
        title: 'Xác nhận đã hoàn tất bảo trì?',
        detail: 'Lịch sẽ được đánh dấu Hoàn tất và lưu trong lịch sử phòng.',
        submit: 'Xác nhận hoàn tất',
        busy: 'Đang hoàn tất...',
        success: 'Đã hoàn tất bảo trì.',
    },
};

function saveError(error, isEditing) {
    const { status, data } = error.response ?? {};
    if (status === 422) {
        return { status: 'error', message: 'Vui lòng kiểm tra thông tin lịch bảo trì.', fields: data?.errors };
    }
    return {
        status: 'error',
        message: (status === 409 ? conflictMessages[data?.code] : undefined)
            ?? (status === 403 ? 'Bạn không có quyền quản lý lịch bảo trì của phòng này.' : undefined)
            ?? (isEditing && status === 404 ? 'Không tìm thấy lịch bảo trì.' : errors[status])
            ?? 'Chưa xác nhận được kết quả. Hãy tải lại trang để kiểm tra danh sách trước khi gửi lại.',
    };
}

function formatDate(value) {
    if (/^[1-9]\d{3}-\d{2}-\d{2}$/.test(value)) {
        const date = new Date(value + 'T00:00:00Z');
        if (!Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value) {
            const [year, month, day] = value.split('-');
            return day + '/' + month + '/' + year;
        }
    }
    return null;
}

export default function HostRoomMaintenancePanel({ roomId }) {
    const [page, setPage] = useState(1);
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading' });
    const [createDraft, setCreateDraft] = useState(emptyDraft);
    const [edit, setEdit] = useState(null);
    const [transition, setTransition] = useState(null);
    const [submission, setSubmission] = useState({ status: 'idle' });
    const title = useRef(null);
    const firstField = useRef(null);
    const editTrigger = useRef(null);
    const transitionTrigger = useRef(null);
    const backButton = useRef(null);
    const pendingSave = useRef(null);

    useEffect(() => () => {
        pendingSave.current?.abort();
        pendingSave.current = null;
    }, [roomId]);

    useEffect(() => {
        if (transition) backButton.current?.focus();
    }, [transition]);

    useEffect(() => {
        const controller = new AbortController();
        hostRoomMaintenanceService.list(roomId, page, { signal: controller.signal }).then(
            (result) => {
                if (!controller.signal.aborted) setState({ status: 'ready', result });
            },
            (error) => {
                if (controller.signal.aborted) return;
                const status = error.response?.status ?? error.status;
                setState({ status: 'error', message: errors[status] || 'Không thể tải lịch bảo trì. Vui lòng thử lại.' });
            },
        );
        return () => controller.abort();
    }, [roomId, page, attempt]);

    function goToPage(nextPage) {
        title.current?.focus();
        setState({ status: 'loading' });
        setPage(nextPage);
    }

    function retry() {
        title.current?.focus();
        setState({ status: 'loading' });
        setAttempt((value) => value + 1);
    }

    function updateDraft(event) {
        const { name, value } = event.target;
        if (edit) {
            setEdit((current) => ({ ...current, draft: { ...current.draft, [name]: value } }));
        } else {
            setCreateDraft((current) => ({ ...current, [name]: value }));
        }
        setSubmission({ status: 'idle' });
    }

    function startEdit(item, trigger) {
        if (pendingSave.current) return;
        editTrigger.current = trigger;
        setEdit({ id: item.id, draft: {
            start_date: formatDate(item.start_date) ? item.start_date : '',
            end_date: formatDate(item.end_date) ? item.end_date : '',
            reason: item.reason ?? '',
        } });
        setSubmission({ status: 'idle' });
        firstField.current?.focus();
    }

    function cancelEdit() {
        setEdit(null);
        setSubmission({ status: 'idle' });
        const trigger = editTrigger.current;
        requestAnimationFrame(() => {
            if (trigger?.isConnected) trigger.focus();
            else title.current?.focus();
        });
    }

    async function saveSchedule(event) {
        event.preventDefault();
        if (pendingSave.current || transition) return;

        const controller = new AbortController();
        pendingSave.current = controller;
        setSubmission({ status: 'saving' });
        try {
            const options = { signal: controller.signal };
            const schedule = edit
                ? await hostRoomMaintenanceService.update(edit.id, edit.draft, options)
                : await hostRoomMaintenanceService.create(roomId, createDraft, options);
            if (controller.signal.aborted) return;
            if (edit) setEdit(null);
            else setCreateDraft(emptyDraft);
            setSubmission({ status: 'success', message: (edit ? 'Đã cập nhật lịch bảo trì từ ' : 'Đã tạo lịch bảo trì từ ')
                + formatDate(schedule.start_date) + ' đến ' + formatDate(schedule.end_date) + '.' });
            goToPage(1);
            setAttempt((value) => value + 1);
        } catch (error) {
            if (controller.signal.aborted) return;
            setSubmission(saveError(error, Boolean(edit)));
            if (edit && error.response?.status === 409 && error.response?.data?.code === 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT') {
                setEdit(null);
                retry();
            }
        } finally {
            if (pendingSave.current === controller) pendingSave.current = null;
        }
    }

    function requestTransition(id, action, trigger) {
        if (pendingSave.current) return;
        transitionTrigger.current = trigger;
        setTransition({ id, action });
        setSubmission({ status: 'idle' });
    }

    function closeTransition() {
        setTransition(null);
        const trigger = transitionTrigger.current;
        requestAnimationFrame(() => {
            if (trigger?.isConnected) trigger.focus();
            else title.current?.focus();
        });
    }

    async function confirmTransition() {
        if (pendingSave.current || !transition) return;
        const controller = new AbortController();
        pendingSave.current = controller;
        setSubmission({ status: 'saving' });

        try {
            await hostRoomMaintenanceService[transition.action](transition.id, { signal: controller.signal });
            if (controller.signal.aborted) return;
            setTransition(null);
            setSubmission({ status: 'success', message: transitionCopy[transition.action].success });
            retry();
        } catch (error) {
            if (controller.signal.aborted) return;
            const code = error.response?.data?.code;
            setSubmission(transition.action === 'start' && error.response?.status === 409 && Object.hasOwn(conflictMessages, code)
                ? { status: 'error', message: error.response.data.message || conflictMessages[code] }
                : saveError(error, true));
            if (error.response?.status === 409 && code === 'CATALOG_ROOM_MAINTENANCE_STATUS_CONFLICT') {
                setTransition(null);
                retry();
            } else {
                closeTransition();
            }
        } finally {
            if (pendingSave.current === controller) pendingSave.current = null;
        }
    }

    const result = state.result;
    const draft = edit?.draft ?? createDraft;
    const saving = submission.status === 'saving';
    const confirming = Boolean(transition);
    const actionsDisabled = saving || Boolean(edit) || confirming;
    const confirmationCopy = transition && transitionCopy[transition.action];
    const destructive = transition?.action === 'cancel';
    return <section aria-labelledby="room-maintenance-title"
        className="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
        <header className="flex flex-wrap items-center justify-between gap-3">
            <h2 ref={title} tabIndex={-1} id="room-maintenance-title"
                className="flex items-center gap-2 rounded-sm text-lg font-semibold text-primary focus-visible:ring-2 focus-visible:ring-primary">
                <CalendarDays size={19} className="shrink-0" aria-hidden="true" />
                Lịch bảo trì
            </h2>
            {result && <span className="rounded-full bg-surface px-3 py-1 text-sm text-muted-foreground">{result.meta.total} lịch</span>}
        </header>
        <form onSubmit={saveSchedule} aria-labelledby="room-maintenance-form-title" className="space-y-3 rounded-xl border border-border bg-surface/40 p-4">
            <h3 id="room-maintenance-form-title" className="text-sm font-semibold text-primary">{edit ? 'Sửa lịch bảo trì' : 'Tạo lịch bảo trì'}</h3>
            <p className="text-sm text-muted-foreground">Phòng được giữ từ ngày bắt đầu đến trước ngày kết thúc.</p>
            <fieldset disabled={saving || confirming} className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                {Object.entries({ start_date: 'Ngày bắt đầu', end_date: 'Ngày kết thúc', reason: 'Lý do (không bắt buộc)' }).map(([name, label]) => {
                    const id = 'room-maintenance-form-' + name;
                    const fieldError = submission.fields?.[name]?.[0];
                    const props = { id, name, value: draft[name], onChange: updateDraft, className: inputClass,
                        'aria-invalid': fieldError ? true : undefined, 'aria-describedby': fieldError ? id + '-error' : undefined };
                    return <div key={name} className={'min-w-0 space-y-1 ' + (name === 'reason' ? 'sm:col-span-2' : '')}>
                        <label htmlFor={id} className="block text-sm font-medium text-primary">{label}</label>
                        {name === 'reason' ? <textarea {...props} rows={2} /> : <input {...props} ref={name === 'start_date' ? firstField : undefined} type="date" required />}
                        {fieldError && <p id={id + '-error'} className="break-words text-sm text-red-800">{fieldError}</p>}
                    </div>;
                })}
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    <button type="submit" className={buttonClass}>
                        {saving && !confirming ? 'Đang lưu...' : edit ? 'Lưu thay đổi' : 'Lưu lịch bảo trì'}
                    </button>
                    {edit && <button type="button" className={buttonClass} onClick={cancelEdit}>Hủy sửa</button>}
                </div>
            </fieldset>
        </form>
        {submission.status === 'error' && <p role="alert" className="break-words text-sm text-red-800">{submission.message}</p>}
        {submission.status === 'success' && <p role="status" className="text-sm text-emerald-800">{submission.message}</p>}
        {state.status === 'loading' && <p role="status" className="text-sm text-muted-foreground">Đang tải lịch bảo trì...</p>}
        {state.status === 'error' && <div className="space-y-3 rounded-xl border border-red-200 bg-red-50 p-4">
            <p role="alert" className="break-words text-sm text-red-800">{state.message}</p>
            <button type="button" className={buttonClass} onClick={retry}>
                <RefreshCw size={16} aria-hidden="true" />
                Thử tải lại lịch
            </button>
        </div>}
        {result?.data.length === 0 && <p className="rounded-xl border border-dashed border-border bg-surface/40 p-4 text-sm text-muted-foreground">
            {result.meta.total === 0 ? 'Phòng chưa có lịch bảo trì.' : 'Trang này không có lịch bảo trì.'}
        </p>}
        {result?.data.length > 0 && <ul className="space-y-3">
            {result.data.map((item) => {
                const status = statuses[item.status];
                return <li key={item.id} className="min-w-0 space-y-2 rounded-xl border border-border bg-surface/40 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-semibold text-primary">
                            {formatDate(item.start_date) ?? 'Ngày chưa hợp lệ'} – {formatDate(item.end_date) ?? 'Ngày chưa hợp lệ'}
                        </p>
                        <span className={'rounded-full border px-2.5 py-1 text-xs font-medium ' + status.color}>{status.label}</span>
                    </div>
                    <p className="whitespace-pre-wrap break-words text-sm text-muted-foreground">{item.reason?.trim() || 'Chưa có lý do.'}</p>
                    {item.status === 'SCHEDULED' && <div className="flex flex-wrap gap-2">
                        <button type="button" disabled={actionsDisabled} className={buttonClass}
                            onClick={(event) => requestTransition(item.id, 'start', event.currentTarget)}>Bắt đầu bảo trì</button>
                        <button type="button" disabled={actionsDisabled} className={buttonClass}
                            onClick={(event) => startEdit(item, event.currentTarget)}>Sửa lịch</button>
                        <button type="button" disabled={actionsDisabled}
                            className={buttonBaseClass + ' border-red-200 bg-card text-red-800 hover:bg-red-50'}
                            onClick={(event) => requestTransition(item.id, 'cancel', event.currentTarget)}>Hủy lịch</button>
                    </div>}
                    {item.status === 'IN_PROGRESS' && <button type="button" disabled={actionsDisabled} className={buttonClass}
                        onClick={(event) => requestTransition(item.id, 'complete', event.currentTarget)}>Hoàn tất bảo trì</button>}
                    {transition?.id === item.id && <div role="group" aria-labelledby="room-maintenance-transition-title"
                        className={'space-y-2 rounded-xl border p-3 ' + (destructive ? 'border-red-200 bg-red-50 text-red-900' : 'border-border bg-card text-primary')}>
                        <p id="room-maintenance-transition-title" className="text-sm font-semibold">{confirmationCopy.title}</p>
                        <p className="text-sm">{confirmationCopy.detail}</p>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" ref={backButton} disabled={saving} className={buttonClass}
                                onClick={closeTransition}>Quay lại</button>
                            <button type="button" disabled={saving}
                                className={buttonBaseClass + (destructive ? ' border-red-800 bg-red-800 text-white hover:bg-red-900' : ' border-primary bg-primary text-primary-foreground hover:bg-primary/90')}
                                onClick={confirmTransition}>{saving ? confirmationCopy.busy : confirmationCopy.submit}</button>
                        </div>
                    </div>}
                </li>;
            })}
        </ul>}
        {result && (result.meta.last_page > 1 || page > 1) && <nav aria-label="Phân trang lịch bảo trì" className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
            <p className="text-sm text-muted-foreground" role="status">Trang {result.meta.current_page} / {result.meta.last_page}</p>
            <div className="flex flex-wrap gap-2">
                <button type="button" className={buttonClass} disabled={confirming || page <= 1}
                    onClick={() => goToPage(Math.min(page - 1, result.meta.last_page))}>
                    <ChevronLeft size={16} aria-hidden="true" />
                    Trang trước
                </button>
                <button type="button" className={buttonClass} disabled={confirming || page >= result.meta.last_page}
                    onClick={() => goToPage(page + 1)}>
                    Trang sau
                    <ChevronRight size={16} aria-hidden="true" />
                </button>
            </div>
        </nav>}
    </section>;
}
