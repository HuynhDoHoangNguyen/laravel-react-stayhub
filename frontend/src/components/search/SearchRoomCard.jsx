import { Link } from 'react-router-dom';
import { MapPin, Users, Star } from 'lucide-react';
import CatalogImage from '../catalog/CatalogImage';

export function SearchRoomCard({ room, view }) {
    return <article className={`overflow-hidden rounded-3xl border border-border bg-card ${view === 'list' ? 'sm:flex' : ''}`}>
        <div className={`aspect-[16/10] shrink-0 ${view === 'list' ? 'sm:w-64' : ''}`}><CatalogImage src={room.images[0]} alt={room.name} /></div>
        <div className="flex flex-1 flex-col gap-3 p-5">
            <p className="text-xs font-medium text-primary">{room.room_type.name} · {room.property.name}</p>
            <h3 className="font-display text-xl text-ink">{room.name}</h3>
            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Star className="h-4 w-4 fill-amber-400 text-amber-500" aria-hidden />
                {room.property.rating === null ? 'Cơ sở chưa có đánh giá' : `${room.property.rating.toLocaleString('vi-VN')} / 5 · ${room.property.review_count} đánh giá cơ sở`}
            </p>
            <p className="flex items-start gap-2 text-sm text-muted-foreground"><MapPin className="h-4 w-4 shrink-0" aria-hidden />{room.property.address || 'Chưa cập nhật địa chỉ'}</p>
            <p className="flex items-center gap-2 text-sm"><Users className="h-4 w-4" aria-hidden />Tối đa {room.capacity} khách / phòng</p>
            {room.amenities.length > 0 && <p className="text-sm text-muted-foreground">{room.amenities.map(a => a.name).join(' · ')}</p>}
            <div className="mt-auto border-t border-border pt-3">
                <p><strong className="text-lg text-primary">{new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 2 }).format(room.price_per_night)}</strong><span className="text-sm text-muted-foreground"> / đêm</span></p>
                <p className="mt-1 text-xs text-muted-foreground">Giá tham khảo, chưa kiểm tra khả dụng theo ngày.</p>
                <Link to={`/properties/${room.property.id}`} className="mt-4 inline-block rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground">Xem cơ sở lưu trú</Link>
            </div>
        </div>
    </article>;
}
