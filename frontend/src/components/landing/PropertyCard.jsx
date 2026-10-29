import { Link, useLocation } from 'react-router-dom';
import { MapPin, Star } from 'lucide-react';
import CatalogImage from '../catalog/CatalogImage';
import { formatCatalogPrice } from '../../services/catalog';

export function PropertyCard({ property, carousel = false }) {
    const location = useLocation();
    const hasReviews = property.reviewCount > 0 && property.averageRating !== null;
    return <Link to={`/properties/${property.id}`} state={{ catalogPage: location.pathname === '/properties' ? location.search : '' }}
        className={`group block overflow-hidden rounded-3xl border border-border/60 bg-white shadow-xs transition hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${carousel ? 'w-[300px] shrink-0 sm:w-[330px]' : 'w-full'}`}>
        <div className="aspect-[4/3] overflow-hidden"><CatalogImage src={property.photos?.[0]} alt={property.name} /></div>
        <div className="space-y-3 p-5">
            <p className="text-xs uppercase tracking-wider text-muted-foreground">{{ HOTEL: 'Khách sạn', HOMESTAY: 'Homestay' }[property.propertyType] || property.propertyType}</p>
            <h2 className="font-display text-xl text-primary">{property.name}</h2>
            <p className="flex items-start gap-2 text-sm text-muted-foreground"><MapPin className="mt-0.5 h-4 w-4 shrink-0" />{property.address || property.city || 'Chưa có địa chỉ'}</p>
            <p className="flex items-center gap-2 text-xs text-muted-foreground">{hasReviews ? <><Star className="h-4 w-4 text-accent" />{Number(property.averageRating).toFixed(1)} · {property.reviewCount} đánh giá</> : 'Chưa có đánh giá'}</p>
            <div className="border-t border-border/60 pt-3"><p className="font-semibold text-ink">{formatCatalogPrice(property.price)}</p>
                <p className="mt-1 text-xs text-muted-foreground">{property.price === null ? 'Chưa có phòng để hiển thị' : 'Giá từ / đêm · tham khảo'}</p></div>
            <span className="inline-block text-sm font-semibold text-primary">Xem chi tiết →</span>
        </div>
    </Link>;
}
