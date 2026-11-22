import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, BedDouble, CheckCircle2, Pencil, RefreshCw } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { hostRoomService } from '../../services/hostRoomService';
import { formatCatalogPrice } from '../../services/catalog';
import HostRoomEditForm from '../../components/catalog/HostRoomEditForm';
import HostRoomStatusForm from '../../components/catalog/HostRoomStatusForm';
import HostRoomImageManager from '../../components/catalog/HostRoomImageManager';
import HostRoomAmenityPanel from '../../components/catalog/HostRoomAmenityPanel';

const roomStatuses = {
    ACTIVE: { label: 'Đang hoạt động', color: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
    MAINTENANCE: { label: 'Đang bảo trì', color: 'border-amber-200 bg-amber-50 text-amber-900' },
    INACTIVE: { label: 'Ngưng hoạt động', color: 'border-border bg-surface text-muted-foreground' },
};
const propertyStatuses = { ACTIVE: 'Đang hoạt động', INACTIVE: 'Ngưng hoạt động', PENDING: 'Chờ duyệt', BLOCKED: 'Đã khóa' };

const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
const secondaryButton = buttonClass + ' border border-border bg-card text-primary hover:bg-surface';

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
    const roomStatus = room ? roomStatuses[room.status] : null;
    const actionsDisabled = editing || changingStatus || state.status !== 'ready';
    return <div className="mx-auto min-w-0 max-w-5xl space-y-5 pb-12">
        <Link to={room ? '/host/properties/' + room.property.id : '/host/properties'} className={secondaryButton}>
            <ArrowLeft size={16} className="shrink-0" aria-hidden="true" />
            {room ? 'Về cơ sở lưu trú' : 'Về danh sách cơ sở'}
        </Link>
        {state.status === 'loading' && <p role="status" className="flex items-center gap-2 rounded-xl bg-surface/60 p-4 text-sm text-muted-foreground">
            <RefreshCw size={16} className="shrink-0 animate-spin motion-reduce:animate-none" aria-hidden="true" />
            Đang tải chi tiết phòng...
        </p>}
        {['error', 'missing'].includes(state.status) && <div className="rounded-xl border border-red-200 bg-red-50 p-4">
            <p role="alert" className="break-words text-sm leading-6 text-red-800">{state.message}</p>
            {state.status === 'error' && <button type="button" onClick={retry} className={secondaryButton + ' mt-3'}>
                <RefreshCw size={16} aria-hidden="true" />
                Thử lại
            </button>}
        </div>}
        {saved && <p role="status" className="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800">
            <CheckCircle2 size={18} className="mt-0.5 shrink-0" aria-hidden="true" />
            {saved === 'status' ? 'Cập nhật trạng thái phòng thành công.' : 'Cập nhật phòng thành công.'}
        </p>}
        {room && <header className="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface text-primary sm:size-12">
                        <BedDouble size={22} aria-hidden="true" />
                    </div>
                    <div className="min-w-0 space-y-2">
                        <h1 className="break-words font-display text-2xl text-primary sm:text-3xl">{room.name}</h1>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <p className="min-w-0 break-words text-sm text-muted-foreground">Số phòng {room.room_number}</p>
                            <span className={'rounded-full border px-2.5 py-1 text-xs font-medium ' + roomStatus.color}>{roomStatus.label}</span>
                        </div>
                    </div>
                </div>
                <div className="flex shrink-0 flex-wrap gap-2">
                    <button ref={editButton} type="button" disabled={actionsDisabled} aria-expanded={editing}
                        onClick={() => { setSaved(false); setEditing(true); }}
                        className={buttonClass + ' bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-md'}>
                        <Pencil size={16} className="shrink-0" aria-hidden="true" />
                        Sửa thông tin
                    </button>
                    <button ref={statusButton} type="button" disabled={actionsDisabled} aria-expanded={changingStatus}
                        onClick={() => { setSaved(false); setChangingStatus(true); }} className={secondaryButton}>
                        <RefreshCw size={16} className="shrink-0" aria-hidden="true" />
                        Đổi trạng thái
                    </button>
                </div>
            </div>
            <dl className="mt-5 grid gap-3 sm:grid-cols-3">
                {[
                    ['Loại phòng', room.room_type?.name ?? 'Loại phòng không hợp lệ'],
                    ['Sức chứa', room.capacity + ' khách'],
                    ['Giá mỗi đêm', formatCatalogPrice(room.price_per_night)],
                ].map(([label, value]) => <div key={label} className="min-w-0 rounded-xl border border-border/70 bg-surface/50 p-3.5">
                    <dt className="text-xs text-muted-foreground">{label}</dt>
                    <dd className="mt-1 break-words font-semibold text-primary">{value}</dd>
                </div>)}
            </dl>
        </header>}
        {changingStatus && room && <HostRoomStatusForm room={room} ready={state.status === 'ready'} attempt={attempt}
            onCancel={closeStatusForm} onCheck={retry}
            onChanged={() => { setSaved('status'); closeStatusForm(); retry(); }} />}
        {editing && room && <HostRoomEditForm room={room} ready={state.status === 'ready'} attempt={attempt}
            onCancel={closeForm} onCheck={retry} onUpdated={() => { setSaved(true); closeForm(); retry(); }} />}
        {room && <div className="min-w-0 space-y-5">
            <section aria-labelledby="room-description-title" className="space-y-4 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-6">
                <h2 id="room-description-title" className="text-lg font-semibold text-primary">Thông tin phòng</h2>
                <dl className="grid gap-3 sm:grid-cols-2">
                    <div className="min-w-0">
                        <dt className="text-sm text-muted-foreground">Cơ sở lưu trú</dt>
                        <dd className="mt-1 break-words font-medium">{room.property.name}</dd>
                    </div>
                    <div className="min-w-0">
                        <dt className="text-sm text-muted-foreground">Trạng thái cơ sở</dt>
                        <dd className="mt-1 font-medium">{propertyStatuses[room.property.status]}</dd>
                    </div>
                </dl>
                <div className="space-y-1 border-t border-border pt-4">
                    <h3 className="text-sm font-medium text-muted-foreground">Mô tả</h3>
                    <p className="whitespace-pre-line break-words text-sm leading-6">{room.description ?? 'Chưa có mô tả.'}</p>
                </div>
            </section>
            <HostRoomImageManager roomId={room.id} />
            <HostRoomAmenityPanel roomId={room.id} amenities={room.amenities} ready={state.status === 'ready'} />
        </div>}
    </div>;
}
