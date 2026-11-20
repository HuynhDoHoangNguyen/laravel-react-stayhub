import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { MapPin, Users, Star, Check } from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
import CatalogImage from '../catalog/CatalogImage';

const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 2 });

export function SearchRoomCard({ room, view = 'grid' }) {
    const location = useLocation();
    const [photoIndex, setPhotoIndex] = useState(0);
    const [hovered, setHovered] = useState(false);
    const reducedMotion = useReducedMotion();
    const photos = room.images;
    const activePhoto = photoIndex % Math.max(photos.length, 1);
    useEffect(() => {
        if (!hovered || reducedMotion || photos.length <= 1) return;
        const interval = setInterval(() => setPhotoIndex(index => (index + 1) % photos.length), 1400);
        return () => clearInterval(interval);
    }, [hovered, reducedMotion, photos.length]);

    return <motion.article whileHover={reducedMotion ? undefined : { y: -4 }}
        transition={{ duration: reducedMotion ? 0 : 0.35, ease: [0.16, 1, 0.3, 1] }}
        onMouseEnter={() => setHovered(true)} onMouseLeave={() => { setHovered(false); setPhotoIndex(0); }}
        className={['group overflow-hidden rounded-3xl bg-card shadow-[0_10px_40px_-25px_rgba(17,26,19,0.25)] ring-1 ring-black/[0.04] transition-shadow duration-500 hover:shadow-[0_25px_60px_-30px_rgba(17,26,19,0.4)] motion-reduce:transition-none',
            view === 'list' ? 'flex flex-col xl:flex-row' : 'flex h-full flex-col'].join(' ')}>
        <div className={['relative shrink-0 overflow-hidden bg-surface', view === 'list' ? 'aspect-[16/10] w-full xl:w-[240px]' : 'aspect-[16/11] w-full'].join(' ')}>
            {photos.length ? photos.map((src, index) => <div key={index + ':' + src} aria-hidden={index !== activePhoto}
                className={['absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none', index === activePhoto ? 'opacity-100' : 'opacity-0'].join(' ')}>
                <CatalogImage src={src} alt={room.name + ' - ảnh ' + (index + 1)}
                    className={['transition-transform duration-700 motion-reduce:transition-none', hovered && !reducedMotion ? 'scale-[1.04]' : ''].join(' ')} />
            </div>) : <CatalogImage alt={room.name} />}
            {photos.length > 1 && <div role="group" aria-label={'Ảnh phòng ' + room.name} className="absolute bottom-2 left-1/2 flex max-w-full -translate-x-1/2 flex-wrap justify-center gap-0.5 rounded-full bg-black/20 px-1">
                {photos.map((_, index) => <button key={index} type="button" aria-label={'Xem ảnh ' + (index + 1) + ' của ' + room.name} aria-pressed={index === activePhoto}
                    onClick={() => { setHovered(false); setPhotoIndex(index); }} className="grid h-6 w-6 place-items-center rounded-full focus-visible:outline-2 focus-visible:outline-white">
                    <span className={['h-1.5 rounded-full', index === activePhoto ? 'w-4 bg-white' : 'w-1.5 bg-white/60'].join(' ')} />
                </button>)}
            </div>}
        </div>
        <div className="flex min-w-0 flex-1 flex-col p-5">
            <p className="mb-1 break-words text-[11.5px] text-primary">{room.room_type.name} · {room.property.name}</p>
            <h3 className="break-words text-[17px] font-medium text-ink">{room.name}</h3>
            <p className="mt-1 flex items-start gap-1 text-[13px] text-muted-foreground"><MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden /><span className="min-w-0 break-words">{room.property.address || 'Chưa cập nhật địa chỉ'}</span></p>
            <p className="mt-2 flex items-center gap-1 text-[12.5px] text-muted-foreground">
                {room.property.rating === null ? 'Cơ sở chưa có đánh giá' : <><Star className="h-3.5 w-3.5 shrink-0 fill-accent text-accent" aria-hidden /><span>{room.property.rating.toLocaleString('vi-VN')} / 5 · {room.property.review_count} đánh giá cơ sở</span></>}
            </p>
            <p className="mt-2 flex items-center gap-1.5 text-[12px] text-muted-foreground"><Users className="h-3.5 w-3.5 shrink-0" aria-hidden />Tối đa {room.capacity.toLocaleString('vi-VN')} khách / phòng</p>
            {room.amenities.length > 0 && <ul className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[12px] text-muted-foreground">{room.amenities.map(item =>
                <li key={item.id} className="inline-flex min-w-0 max-w-full items-start gap-1"><Check className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden /><span className="break-words">{item.name}</span></li>)}</ul>}
            <div className="mt-auto pt-4">
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border/40 pt-4">
                    <div className="min-w-0"><p className="break-words text-[16px] font-semibold tracking-tight text-ink sm:text-[17px]">{currency.format(room.price_per_night)}</p><p className="text-[11.5px] text-muted-foreground">/ đêm · tham khảo</p></div>
                    <Link to={'/properties/' + room.property.id} state={{ searchPage: location.search }} className="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-full bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary-mid">Xem cơ sở</Link>
                </div>
                <p className="mt-3 text-[11px] leading-relaxed text-muted-foreground">Chưa kiểm tra phòng trống theo ngày.</p>
            </div>
        </div>
    </motion.article>;
}
