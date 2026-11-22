import { useEffect, useRef, useState } from 'react';
import { ArrowRight, BedDouble, CheckCircle2, Plus, RefreshCw, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { hostRoomService } from '../../services/hostRoomService';
import { formatCatalogPrice } from '../../services/catalog';
import HostRoomCreateForm from './HostRoomCreateForm';

const roomStatuses = {
    ACTIVE: { label: 'Đang hoạt động', color: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
    MAINTENANCE: { label: 'Đang bảo trì', color: 'border-amber-200 bg-amber-50 text-amber-900' },
    INACTIVE: { label: 'Ngưng hoạt động', color: 'border-border bg-surface text-muted-foreground' },
};

const buttonClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none';

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

    return <section aria-labelledby="host-rooms-title" className="min-w-0 space-y-5">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 flex-wrap items-center gap-3">
                <h2 id="host-rooms-title" className="flex items-center gap-2 text-lg font-semibold text-primary">
                    <BedDouble size={20} className="shrink-0" aria-hidden="true" />
                    Danh sách phòng
                </h2>
                {state.status === 'ready' && <span className="rounded-full bg-surface px-2.5 py-1 text-xs font-medium text-primary">
                    {state.rooms.length} phòng
                </span>}
            </div>
            <button ref={createButton} type="button" onClick={() => { setCreated(null); setShowForm(true); }} disabled={showForm}
                aria-expanded={showForm}
                className={buttonClass + ' shrink-0 bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50'}>
                <Plus size={17} aria-hidden="true" />
                Thêm phòng
            </button>
        </header>
        {showForm && <HostRoomCreateForm propertyId={propertyId} ready={state.status === 'ready'} attempt={attempt}
            onCancel={closeForm} onCheck={retry} onCreated={(room) => {
                setCreated(room);
                closeForm();
                retry();
            }} />}
        {created && <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            <p role="status" className="flex min-w-0 items-start gap-2">
                <CheckCircle2 size={18} className="mt-0.5 shrink-0" aria-hidden="true" />
                <span className="break-words">Đã thêm phòng <strong>{created.name}</strong>.</span>
            </p>
            <Link to={'/host/rooms/' + created.id}
                className="inline-flex min-h-10 items-center gap-2 rounded-md font-semibold underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">
                Xem phòng vừa tạo <ArrowRight size={15} aria-hidden="true" />
            </Link>
        </div>}
        {state.status === 'loading' && <p role="status" className="flex items-center gap-2 rounded-xl bg-surface/60 p-5 text-sm text-muted-foreground">
            <RefreshCw size={16} className="shrink-0 animate-spin motion-reduce:animate-none" aria-hidden="true" />
            Đang tải danh sách phòng...
        </p>}
        {state.status === 'error' && <div className="rounded-xl border border-red-200 bg-red-50 p-4">
            <p role="alert" className="text-sm leading-6 text-red-800">{state.message}</p>
            <button type="button" onClick={retry} className={buttonClass + ' mt-3 border border-border bg-card text-primary hover:bg-surface'}>
                <RefreshCw size={16} aria-hidden="true" />
                Thử lại
            </button>
        </div>}
        {state.status === 'ready' && (state.rooms.length === 0
            ? <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border bg-surface/40 px-4 py-8 text-center">
                <BedDouble size={28} className="text-primary/60" aria-hidden="true" />
                <p className="text-sm font-medium text-primary">Cơ sở chưa có phòng nào.</p>
            </div>
            : <ul className="grid gap-4 lg:grid-cols-2">
                {state.rooms.map((room) => {
                    const status = roomStatuses[room.status];
                    return <li key={room.id} className="flex min-w-0 flex-col gap-4 rounded-2xl border border-border bg-card p-4 shadow-sm transition-shadow hover:shadow-md motion-reduce:transition-none sm:p-5">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <p className="min-w-0 break-words text-sm font-medium text-muted-foreground">Số phòng {room.room_number}</p>
                                <span className={'rounded-full border px-2.5 py-1 text-xs font-medium ' + status.color}>
                                    {status.label}
                                </span>
                            </div>
                            <h3 className="break-words text-base font-semibold leading-6 text-primary">{room.name}</h3>
                        </div>
                        {room.description && <p className="whitespace-pre-line break-words text-sm leading-6 text-muted-foreground">{room.description}</p>}
                        <dl className="grid min-w-0 grid-cols-2 gap-3 rounded-xl bg-surface/50 p-3 text-sm">
                            <div className="min-w-0">
                                <dt className="text-xs text-muted-foreground">Loại phòng</dt>
                                <dd className="mt-1 break-words font-medium">{room.room_type?.name ?? 'Loại phòng không hợp lệ'}</dd>
                            </div>
                            <div className="min-w-0">
                                <dt className="text-xs text-muted-foreground">Sức chứa</dt>
                                <dd className="mt-1 flex items-start gap-1.5 font-medium">
                                    <Users size={15} className="mt-0.5 shrink-0 text-primary" aria-hidden="true" />
                                    <span className="min-w-0 break-words">{room.capacity} khách</span>
                                </dd>
                            </div>
                        </dl>
                        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                            <dl className="min-w-0">
                                <dt className="text-xs text-muted-foreground">Giá mỗi đêm</dt>
                                <dd className="mt-1 break-words font-semibold text-primary">{formatCatalogPrice(room.price_per_night)}</dd>
                            </dl>
                            <Link to={'/host/rooms/' + room.id} aria-label={'Xem chi tiết phòng ' + room.room_number + ' – ' + room.name}
                                className={buttonClass + ' border border-border bg-card text-primary hover:bg-surface hover:shadow-md'}>
                                Xem chi tiết <ArrowRight size={16} aria-hidden="true" />
                            </Link>
                        </div>
                    </li>;
                })}
            </ul>)}
    </section>;
}
