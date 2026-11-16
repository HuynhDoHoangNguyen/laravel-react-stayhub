import { useEffect, useRef, useState } from 'react';
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

    return <section aria-labelledby="host-room-types-title" className="min-w-0 space-y-4 rounded-2xl border border-border p-6">
        <h2 ref={title} tabIndex={-1} id="host-room-types-title" className="text-xl font-semibold">Loại phòng</h2>
        <button ref={createButton} type="button" onClick={() => {
            setSuccess('');
            setShowForm(true);
        }} disabled={editing !== null || deleting !== null} aria-expanded={showForm} className="rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-60">
            Thêm loại phòng
        </button>
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
        {success && <p role="status">{success}</p>}
        {state.status === 'loading' && <p role="status">Đang tải danh sách loại phòng...</p>}
        {state.status === 'error' && <div>
            <p role="alert">{state.message}</p>
            <button type="button" onClick={retry} className="mt-3 rounded-xl border border-border px-4 py-2 text-primary">
                Thử lại
            </button>
        </div>}
        {state.status === 'ready' && (state.items.length === 0
            ? <p className="text-muted-foreground">Cơ sở chưa có loại phòng nào.</p>
            : <ul className="space-y-4">
                {state.items.map((item) => <li key={item.id} className="min-w-0 rounded-xl border border-border p-4">
                    <h3 className="break-words font-semibold">{item.name}</h3>
                    {item.description !== null && <p className="mt-2 whitespace-pre-line break-words">{item.description}</p>}
                    <button type="button" aria-label={'Chỉnh sửa ' + item.name}
                        aria-expanded={editing?.id === item.id} aria-disabled={actionOpen}
                        className="mt-2 rounded-xl border border-border px-4 py-2 text-primary aria-disabled:opacity-60"
                        onClick={(event) => {
                            if (actionOpen) return;
                            editButton.current = event.currentTarget;
                            setSuccess('');
                            setEditing(item);
                        }}>Chỉnh sửa</button>
                    <button type="button" aria-label={'Xóa ' + item.name}
                        aria-expanded={deleting?.id === item.id} aria-disabled={actionOpen}
                        className="ml-2 mt-2 rounded-xl border border-border px-4 py-2 text-red-700 aria-disabled:opacity-60"
                        onClick={(event) => {
                            if (actionOpen) return;
                            deleteButton.current = event.currentTarget;
                            setSuccess('');
                            setDeleting(item);
                        }}>Xóa</button>
                </li>)}
            </ul>)}
    </section>;
}
