import { MapPin, Star } from 'lucide-react';
export function PropertyHeader({ property }) {
    return <header className="space-y-3">
        <p className="text-xs uppercase tracking-[0.2em] text-accent">{{ HOTEL: 'Khách sạn', HOMESTAY: 'Homestay' }[property.propertyType] || property.propertyType}</p>
        <h1 className="font-display text-3xl text-primary md:text-5xl">{property.name}</h1>
        <p className="flex items-start gap-2 text-sm text-muted-foreground"><MapPin className="h-4 w-4 shrink-0" />{property.address}</p>
        <p className="flex items-center gap-2 text-sm text-ink">{property.reviewCount > 0 && property.averageRating !== null
            ? <><Star className="h-4 w-4 text-accent" />{Number(property.averageRating).toFixed(1)} · {property.reviewCount} đánh giá</>
            : 'Chưa có đánh giá'}</p>
    </header>;
}
