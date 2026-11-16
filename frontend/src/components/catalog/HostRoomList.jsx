import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { hostRoomService } from '../../services/hostRoomService';
import { formatCatalogPrice } from '../../services/catalog';
import HostRoomCreateForm from './HostRoomCreateForm';

const statusLabels = {
    ACTIVE: 'Đang hoạt động',
    MAINTENANCE: 'Đang bảo trì',
    INACTIVE: 'Ngưng hoạt động',
};

export default function HostRoomList({ propertyId }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading' });
    const [showForm, setShowForm] = useState(false);
    const [created, setCreated] = useState(null);
    const createButton = useRef(null);

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostRoomService.list(propertyId, { signal: controller.signal }).then(
            (rooms) => {
                if (current) setState({ status: 'ready', rooms });
            },
            (error) => {
                if (!current) return;
                const status = error.response?.status ?? error.status;
                const messages = {
                    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    403: 'Bạn không có quyền xem danh sách phòng.',
                    404: 'Không tìm thấy cơ sở lưu trú.',
                };
                setState({ status: 'error', message: messages[status] || 'Không thể tải danh sách phòng. Vui lòng thử lại.' });
            },
        );

        return () => {
            current = false;
            controller.abort();
        };
    }, [propertyId, attempt]);

    function retry() {
        setState({ status: 'loading' });
        setAttempt((value) => value + 1);
    }

    function closeForm() {
        setShowForm(false);
        requestAnimationFrame(() => createButton.current?.focus());
    }

    return <section aria-labelledby="host-rooms-title" className="space-y-4">
        <h2 id="host-rooms-title" className="text-xl font-semibold">Danh sách phòng</h2>
        <button ref={createButton} type="button" onClick={() => { setCreated(null); setShowForm(true); }} disabled={showForm}
            aria-expanded={showForm} className="rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-50">Thêm phòng</button>
        {showForm && <HostRoomCreateForm propertyId={propertyId} ready={state.status === 'ready'} attempt={attempt}
            onCancel={closeForm} onCheck={retry} onCreated={(room) => {
                setCreated(room);
                closeForm();
                retry();
            }} />}
        {created && <p role="status">Thêm phòng thành công. <Link to={'/host/rooms/' + created.id} className="text-primary underline">Xem phòng vừa tạo</Link></p>}
        {state.status === 'loading' && <p role="status">Đang tải danh sách phòng...</p>}
        {state.status === 'error' && <div>
            <p role="alert">{state.message}</p>
            <button type="button" onClick={retry}
                className="mt-3 rounded-xl border border-border px-4 py-2 text-primary">Thử lại</button>
        </div>}
        {state.status === 'ready' && (state.rooms.length === 0
            ? <p className="text-muted-foreground">Cơ sở chưa có phòng nào.</p>
            : <ul className="grid gap-4 lg:grid-cols-2">
                {state.rooms.map((room) => <li key={room.id} className="min-w-0 space-y-3 rounded-xl border border-border p-4">
                    <p className="break-words text-sm text-muted-foreground">Số phòng {room.room_number}</p>
                    <h3 className="break-words font-semibold">{room.name}</h3>
                    <Link to={'/host/rooms/' + room.id} className="inline-block text-sm text-primary underline">
                        Xem chi tiết
                    </Link>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div className="min-w-0"><dt className="text-muted-foreground">Loại phòng</dt>
                            <dd className="break-words">{room.room_type?.name ?? 'Loại phòng không hợp lệ'}</dd></div>
                        <div><dt className="text-muted-foreground">Trạng thái</dt><dd>{statusLabels[room.status]}</dd></div>
                        <div><dt className="text-muted-foreground">Sức chứa</dt><dd>{room.capacity} khách</dd></div>
                        <div><dt className="text-muted-foreground">Giá mỗi đêm</dt><dd>{formatCatalogPrice(room.price_per_night)}</dd></div>
                    </dl>
                    {room.description !== null && <p className="whitespace-pre-line break-words text-sm text-muted-foreground">{room.description}</p>}
                </li>)}
            </ul>)}
    </section>;
}
