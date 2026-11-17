import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { hostRoomService } from '../../services/hostRoomService';
import { formatCatalogPrice } from '../../services/catalog';
import HostRoomEditForm from '../../components/catalog/HostRoomEditForm';
import HostRoomStatusForm from '../../components/catalog/HostRoomStatusForm';
import CatalogImage from '../../components/catalog/CatalogImage';

const roomStatuses = { ACTIVE: 'Đang hoạt động', MAINTENANCE: 'Đang bảo trì', INACTIVE: 'Ngưng hoạt động' };
const propertyStatuses = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', PENDING: 'Chờ duyệt', BLOCKED: 'Đã khóa' };

export default function HostRoomDetailPage() {
    const { roomId } = useParams();
    const { user } = useAuth();
    return <RoomDetail key={user?.id + ':' + roomId} roomId={roomId} />;
}

function RoomDetail({ roomId }) {
    const [state, setState] = useState({ status: 'loading' });
    const [attempt, setAttempt] = useState(0);
    const [editing, setEditing] = useState(false);
    const [saved, setSaved] = useState(false);
    const [changingStatus, setChangingStatus] = useState(false);
    const statusButton = useRef(null);
    const editButton = useRef(null);

    function closeForm() {
        setEditing(false);
        requestAnimationFrame(() => editButton.current?.focus());
    }

    function closeStatusForm() {
        setChangingStatus(false);
        requestAnimationFrame(() => statusButton.current?.focus());
    }

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostRoomService.detail(roomId, { signal: controller.signal }).then(
            (room) => {
                if (current) setState({ status: 'ready', room });
            },
            (error) => {
                if (!current) return;
                const status = error.response?.status ?? error.status;
                const messages = {
                    400: 'Không tìm thấy phòng.',
                    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    403: 'Bạn không có quyền xem phòng này.',
                    404: 'Không tìm thấy phòng.',
                };
                setState((previous) => ({ room: [400, 401, 403, 404].includes(status) ? undefined : previous.room,
                    status: status === 404 || status === 400 ? 'missing' : 'error',
                    message: messages[status] || 'Không thể tải chi tiết phòng. Vui lòng thử lại.' }));
            },
        );
        return () => {
            current = false;
            controller.abort();
        };
    }, [roomId, attempt]);

    useEffect(() => {
        if (saved && !editing && !changingStatus && state.status === 'ready') {
            (saved === 'status' ? statusButton : editButton).current?.focus();
        }
    }, [saved, editing, changingStatus, state.status]);

    function retry() {
        setState((previous) => ({ ...previous, status: 'loading' }));
        setAttempt((value) => value + 1);
    }

    const room = state.room;
    return <div className="mx-auto max-w-5xl space-y-6 pb-12">
        <Link to={room ? '/host/properties/' + room.property.id : '/host/properties'} className="text-primary underline">
            {room ? '← Về cơ sở lưu trú' : '← Về danh sách cơ sở'}
        </Link>
        {state.status === 'loading' && <p role="status">Đang tải chi tiết phòng...</p>}
        {['error', 'missing'].includes(state.status) && <div className="rounded-xl border border-border p-6">
            <p role="alert">{state.message}</p>
            {state.status === 'error' && <button type="button" onClick={retry}
                className="mt-3 rounded-xl border border-border px-4 py-2 text-primary">Thử lại</button>}
        </div>}
        {saved && <p role="status">{saved === 'status' ? 'Cập nhật trạng thái phòng thành công.' : 'Cập nhật phòng thành công.'}</p>}
        {room && <button ref={editButton} type="button" disabled={editing || changingStatus || state.status !== 'ready'}
            onClick={() => { setSaved(false); setEditing(true); }}
            className="rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-50">Sửa thông tin</button>}
        {room && <button ref={statusButton} type="button" disabled={editing || changingStatus || state.status !== 'ready'}
            onClick={() => { setSaved(false); setChangingStatus(true); }}
            className="rounded-xl border border-border px-4 py-2 text-primary disabled:opacity-50">Đổi trạng thái</button>}
        {changingStatus && room && <HostRoomStatusForm room={room} ready={state.status === 'ready'} attempt={attempt}
            onCancel={closeStatusForm} onCheck={retry}
            onChanged={() => { setSaved('status'); closeStatusForm(); retry(); }} />}
        {editing && room && <HostRoomEditForm room={room} ready={state.status === 'ready'} attempt={attempt}
            onCancel={closeForm} onCheck={retry} onUpdated={() => { setSaved(true); closeForm(); retry(); }} />}
        {room && <article className="space-y-6">
            <h1 className="break-words font-display text-3xl text-primary">{room.name}</h1>
            <dl className="grid gap-5 rounded-2xl border border-border p-6 sm:grid-cols-2">
                {[
                    ['Số phòng', room.room_number],
                    ['Cơ sở lưu trú', room.property.name],
                    ['Trạng thái cơ sở', propertyStatuses[room.property.status]],
                    ['Loại phòng', room.room_type?.name ?? 'Loại phòng không hợp lệ'],
                    ['Trạng thái phòng', roomStatuses[room.status]],
                    ['Sức chứa', room.capacity + ' khách'],
                    ['Giá mỗi đêm', formatCatalogPrice(room.price_per_night)],
                ].map(([label, value]) => <div key={label} className="min-w-0">
                    <dt className="text-sm text-muted-foreground">{label}</dt>
                    <dd className="mt-1 break-words">{value}</dd>
                </div>)}
            </dl>
            <section aria-labelledby="room-description-title" className="space-y-3">
                <h2 id="room-description-title" className="text-xl font-semibold">Mô tả</h2>
                <p className="whitespace-pre-line break-words">{room.description ?? 'Chưa có mô tả.'}</p>
            </section>
            <section aria-labelledby="room-images-title" className="space-y-3">
                <h2 id="room-images-title" className="text-xl font-semibold">Hình ảnh phòng</h2>
                {room.images.length === 0 ? <p className="text-muted-foreground">Chưa có hình ảnh.</p>
                    : <div className="grid gap-4 sm:grid-cols-2">
                        {room.images.map((url, index) => <div key={index} className="aspect-[4/3] overflow-hidden rounded-xl">
                            <CatalogImage src={url} alt={room.name + ' — ảnh ' + (index + 1)} />
                        </div>)}
                    </div>}
            </section>
            <section aria-labelledby="room-amenities-title" className="space-y-3">
                <h2 id="room-amenities-title" className="text-xl font-semibold">Tiện nghi</h2>
                {room.amenities.length === 0 ? <p className="text-muted-foreground">Chưa gán tiện nghi.</p>
                    : <ul className="grid gap-3 sm:grid-cols-2">
                        {room.amenities.map((item) => <li key={item.id} className="min-w-0 rounded-xl border border-border p-4">
                            <p className="break-words">{item.name}</p>
                            <p className="mt-1 text-sm text-muted-foreground">{item.status ? 'Đang hoạt động' : 'Ngưng hoạt động'}</p>
                        </li>)}
                    </ul>}
            </section>
        </article>}
    </div>;
}
