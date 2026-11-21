import { useEffect, useState, useSyncExternalStore } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ArrowRight, ArrowUpRight, RefreshCw } from 'lucide-react';
import { propertyService } from '../../services/propertyService.js';
import { createCatalogLoader } from '../../services/catalog.js';
import { mapRelatedPropertyRooms } from '../../mappers/relatedPropertyRoomsMapper.js';
import { RoomCard } from './RoomCard';
import { DetailAction } from './DetailAction';

export function RelatedRoomsContent({ propertyId, currentRoomId, state, search = '', navigationState, onRetry }) {
    const target = (path, roomId) => {
        const query = new URLSearchParams(search);
        if (query.has('roomId')) query.set('roomId', String(roomId));
        return path + (query.size ? '?' + query.toString() : '');
    };
    return <section aria-label="Khám phá thêm phòng cùng cơ sở" className="mt-12 min-w-0 space-y-6 border-t border-border/70 pt-10">
        <header className="space-y-2">
            <h2 className="font-display text-2xl text-primary md:text-3xl">Khám phá thêm phòng cùng cơ sở</h2>
            <p className="text-sm text-muted-foreground">Giá tham khảo mỗi đêm. Phòng trống được kiểm tra khi bạn mở chi tiết phòng.</p>
        </header>
        {state.status === 'loading' && <p role="status" className="rounded-2xl bg-surface p-6 text-sm">Đang tải phòng cùng cơ sở…</p>}
        {state.status === 'error' && <div role="alert" className="space-y-4 rounded-2xl border border-border p-6 text-sm">
            <p>Chưa thể tải phòng cùng cơ sở. Bạn vẫn có thể xem và đặt phòng hiện tại.</p>
            <DetailAction tone="secondary" type="button" onClick={onRetry}><RefreshCw aria-hidden />Thử lại danh sách phòng</DetailAction>
        </div>}
        {state.status === 'ready' && <>
            {state.result.source === 'mock' && <p role="status" className="text-sm text-amber-800">Dữ liệu giả lập DEV.</p>}
            {state.result.rooms.length ? <div className="grid items-stretch gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {state.result.rooms.map(room => <RoomCard key={room.id} room={room} footer={
                    <DetailAction asChild tone="secondary" className="w-full"><Link to={target('/rooms/' + room.id, room.id)} state={navigationState}>Xem chi tiết phòng<ArrowUpRight aria-hidden /></Link></DetailAction>
                } />)}
            </div> : <p className="rounded-2xl bg-surface p-6 text-sm text-muted-foreground">Cơ sở chưa có phòng khác để gợi ý.</p>}
        </>}
        <DetailAction asChild tone="secondary" className="max-w-full"><Link to={target('/properties/' + propertyId, currentRoomId)} state={navigationState}>
            <span className="min-w-0 break-words">Xem tất cả phòng tại {state.result?.propertyName || 'cơ sở này'}</span><ArrowRight aria-hidden />
        </Link></DetailAction>
    </section>;
}

export function RelatedPropertyRooms({ propertyId, currentRoomId }) {
    const location = useLocation();
    const [loader] = useState(() => createCatalogLoader(async options => mapRelatedPropertyRooms(
        await propertyService.getPropertyById(propertyId, options), propertyId, currentRoomId)));
    const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot, loader.getSnapshot);
    useEffect(() => { loader.reload(); return () => loader.cancel(); }, [loader]);
    return <RelatedRoomsContent propertyId={propertyId} currentRoomId={currentRoomId} state={state}
        search={location.search} navigationState={location.state} onRetry={loader.reload} />;
}
