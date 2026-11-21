import { Link, useLocation } from 'react-router-dom';
import { MapPin, Star } from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
import CatalogImage from '../catalog/CatalogImage';

const currency = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 2 });

export function SearchPropertyCard({ property, view = 'grid' }) {
    const location = useLocation();
    const reducedMotion = useReducedMotion();
    const hasReviews = property.reviewCount > 0 && property.averageRating !== null;
    return <motion.article whileHover={reducedMotion ? undefined : { y: -4 }} transition={{ duration: reducedMotion ? 0 : 0.35 }}
        className={['overflow-hidden rounded-3xl bg-card shadow-[0_10px_40px_-25px_rgba(17,26,19,0.25)] ring-1 ring-black/[0.04]', view === 'list' ? 'flex flex-col xl:flex-row' : 'flex h-full flex-col'].join(' ')}>
        <div className={['shrink-0 overflow-hidden bg-surface', view === 'list' ? 'aspect-[16/10] w-full xl:w-[240px]' : 'aspect-[16/11] w-full'].join(' ')}>
            <CatalogImage src={property.photos?.[0]} alt={property.name} />
        </div>
        <div className="flex min-w-0 flex-1 flex-col gap-3 p-5">
            <p className="text-[11.5px] text-primary">{{ HOTEL: 'Khách sạn', HOMESTAY: 'Homestay' }[property.propertyType] || property.propertyType}</p>
            <h3 className="break-words text-[17px] font-medium text-ink">{property.name}</h3>
            <p className="flex items-start gap-1 text-[13px] text-muted-foreground"><MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden /><span className="break-words">{property.address || 'Chưa cập nhật địa chỉ'}</span></p>
            <p className="flex items-center gap-1 text-[12.5px] text-muted-foreground">{hasReviews ? <><Star className="h-3.5 w-3.5 shrink-0 fill-accent text-accent" aria-hidden />{Number(property.averageRating).toLocaleString('vi-VN')} / 5 · {property.reviewCount} đánh giá cơ sở</> : 'Cơ sở chưa có đánh giá'}</p>
            <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border/40 pt-4">
                <div className="min-w-0"><p className="break-words text-[16px] font-semibold">{property.price === null ? 'Chưa có giá tham khảo' : currency.format(property.price)}</p>
                    <p className="text-[11.5px] text-muted-foreground">{property.price === null ? 'Chưa có phòng để hiển thị giá' : 'Giá từ / đêm · tham khảo'}</p></div>
                <Link to={'/properties/' + property.id} state={{ searchPage: location.search }}
                    className="shrink-0 rounded-full bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground">Xem chi tiết</Link>
            </div>
        </div>
    </motion.article>;
}
