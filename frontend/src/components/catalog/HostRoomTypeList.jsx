import { useEffect, useRef, useState } from 'react';
import { BedDouble, CheckCircle2, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { hostPropertyService } from '../../services/hostPropertyService';
import HostRoomTypeCreateForm from './HostRoomTypeCreateForm';
import HostRoomTypeEditForm from './HostRoomTypeEditForm';
import HostRoomTypeDeleteConfirmation from './HostRoomTypeDeleteConfirmation';

const errorMessages = {
    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    403: 'Bạn không có quyền xem danh sách loại phòng.',
    404: 'Không tìm thấy cơ sở lưu trú.',
};

export default function HostRoomTypeList({ propertyId }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading' });
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [success, setSuccess] = useState('');
    const createButton = useRef(null);
    const editButton = useRef(null);
    const deleteButton = useRef(null);
    const title = useRef(null);
    const actionOpen = showForm || editing !== null || deleting !== null;

    useEffect(() => {
        const controller = new AbortController();
        hostPropertyService.listRoomTypes(propertyId, { signal: controller.signal }).then(
            (items) => {
                if (!controller.signal.aborted) setState({ status: 'ready', items });
            },
            (error) => {
                if (!controller.signal.aborted) {
                    const code = error.response?.status ?? error.status;
                    setState({ status: 'error', message: errorMessages[code] || 'Không thể tải danh sách loại phòng.' });
                }
            },
        );
        return () => controller.abort();
    }, [propertyId, attempt]);

    function retry() {
        setState({ status: 'loading' });
        setAttempt((value) => value + 1);
    }

    function closeForm() {
        setShowForm(false);
        createButton.current?.focus();
    }

    function cancelEdit() {
        setEditing(null);
        if (editButton.current?.isConnected) editButton.current.focus();
        else title.current?.focus();
    }

    const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold shadow-sm transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:opacity-50 motion-reduce:transition-none';

    return <section aria-labelledby="host-room-types-title" className="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0"><div className="flex flex-wrap items-center gap-2">
                <h2 ref={title} tabIndex={-1} id="host-room-types-title" className="flex items-center gap-2 rounded-lg text-lg font-semibold text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><BedDouble size={20} aria-hidden="true" />Loại phòng</h2>
                {state.status === 'ready' && <span className="rounded-full bg-surface px-2.5 py-1 text-xs font-medium text-primary">{state.items.length} loại</span>}
            </div><p className="mt-1 text-sm leading-6 text-muted-foreground">Phân nhóm phòng theo tên và mô tả tại cơ sở của bạn.</p></div>
        <button ref={createButton} type="button" onClick={() => {
            setSuccess('');
            setShowForm(true);
        }} disabled={editing !== null || deleting !== null} aria-expanded={showForm} className={buttonClass + ' shrink-0 border-primary bg-primary text-primary-foreground hover:bg-primary/90'}>
            <Plus size={17} aria-hidden="true" />Thêm loại phòng
        </button>
        </header>
        {showForm && <HostRoomTypeCreateForm propertyId={propertyId} attempt={attempt} ready={state.status === 'ready'}
            onCancel={closeForm} onCheck={retry} onCreated={() => {
                setSuccess('Thêm loại phòng thành công.');
                closeForm();
                retry();
            }} />}
        {editing && <HostRoomTypeEditForm item={editing} attempt={attempt} ready={state.status === 'ready'}
            onCancel={cancelEdit} onCheck={retry} onUpdated={() => {
                setSuccess('Cập nhật loại phòng thành công.');
                setEditing(null);
                title.current?.focus();
                retry();
            }} />}
        {deleting && <HostRoomTypeDeleteConfirmation item={deleting} attempt={attempt}
            items={state.status === 'ready' ? state.items : null} onCheck={retry}
            onCancel={() => {
                setDeleting(null);
                if (deleteButton.current?.isConnected) deleteButton.current.focus();
                else title.current?.focus();
            }} onDeleted={() => {
                setDeleting(null);
                setSuccess('Xóa loại phòng thành công.');
                title.current?.focus();
                retry();
            }} />}
        {success && <p role="status" className="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800"><CheckCircle2 size={18} className="mt-0.5 shrink-0" aria-hidden="true" />{success}</p>}
        {state.status === 'loading' && <p role="status" className="flex items-center gap-2 rounded-xl bg-surface/60 p-5 text-sm text-muted-foreground"><RefreshCw size={16} className="shrink-0 animate-spin motion-reduce:animate-none" aria-hidden="true" />Đang tải danh sách loại phòng...</p>}
        {state.status === 'error' && <div className="rounded-xl border border-red-200 bg-red-50 p-4">
            <p role="alert" className="text-sm leading-6 text-red-800">{state.message}</p>
            <button type="button" onClick={retry} className={buttonClass + ' mt-3 border-border bg-card text-primary hover:bg-surface'}>
                <RefreshCw size={16} aria-hidden="true" />Thử lại
            </button>
        </div>}
        {state.status === 'ready' && (state.items.length === 0
            ? <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border bg-surface/40 px-5 py-8 text-center"><BedDouble size={30} className="text-primary/60" aria-hidden="true" /><p className="font-medium text-primary">Cơ sở chưa có loại phòng nào.</p><p className="max-w-sm text-sm leading-6 text-muted-foreground">Chọn “Thêm loại phòng” để tạo nhóm phòng đầu tiên.</p></div>
            : <ul className="grid gap-4 lg:grid-cols-2">
                {state.items.map((item) => <li key={item.id} className="flex min-w-0 flex-col rounded-2xl border border-border bg-card p-4 shadow-sm transition-shadow hover:shadow-md motion-reduce:transition-none sm:p-5">
                    <div className="mb-4 flex items-start gap-3"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface text-primary"><BedDouble size={19} aria-hidden="true" /></span><h3 className="min-w-0 break-words pt-1 text-base font-semibold leading-6 text-primary">{item.name}</h3></div>
                    {item.description !== null && <p className="mb-4 whitespace-pre-line break-words text-sm leading-6 text-muted-foreground">{item.description}</p>}
                    <div className="mt-auto flex flex-wrap gap-2 border-t border-border pt-4">
                    <button type="button" aria-label={'Chỉnh sửa ' + item.name}
                        aria-expanded={editing?.id === item.id} aria-disabled={actionOpen}
                        className={buttonClass + ' flex-1 border-border bg-surface/60 text-primary hover:bg-surface'}
                        onClick={(event) => {
                            if (actionOpen) return;
                            editButton.current = event.currentTarget;
                            setSuccess('');
                            setEditing(item);
                        }}><Pencil size={15} aria-hidden="true" />Chỉnh sửa</button>
                    <button type="button" aria-label={'Xóa ' + item.name}
                        aria-expanded={deleting?.id === item.id} aria-disabled={actionOpen}
                        className={buttonClass + ' border-red-200 bg-red-50 text-red-700 hover:bg-red-100'}
                        onClick={(event) => {
                            if (actionOpen) return;
                            deleteButton.current = event.currentTarget;
                            setSuccess('');
                            setDeleting(item);
                        }}><Trash2 size={15} aria-hidden="true" />Xóa</button>
                    </div>
                </li>)}
            </ul>)}
    </section>;
}
