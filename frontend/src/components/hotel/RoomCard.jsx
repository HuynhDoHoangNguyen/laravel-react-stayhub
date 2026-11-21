import CatalogImage from '../catalog/CatalogImage';
import { formatCatalogPrice } from '../../services/catalog';
export function RoomCard({ room, footer, selected = false }) {
    return <article data-selected={selected || undefined} className={['flex h-full min-w-0 flex-col overflow-hidden rounded-3xl border bg-card shadow-[0_8px_24px_-16px_rgba(26,46,32,0.3)] motion-safe:transition-shadow motion-safe:duration-200 hover:shadow-[0_16px_36px_-20px_rgba(26,46,32,0.4)]',
        selected ? 'border-primary ring-2 ring-primary/15' : 'border-border/70'].join(' ')}>
        <div className="aspect-[16/10] shrink-0 overflow-hidden"><CatalogImage src={room.photos?.[0]} alt={room.name || 'Phòng nghỉ'} /></div>
        <div className="flex flex-1 flex-col p-5">
            <div className="space-y-3 break-words">
            <p className="text-xs text-muted-foreground">{room.roomTypeName}</p>
            <h3 className="font-display text-xl text-primary">{room.name}</h3>
            {room.description && <p className="whitespace-pre-line text-sm text-muted-foreground">{room.description}</p>}
            <p className="text-sm">Sức chứa tối đa: {room.capacity} khách</p>
            {room.amenities?.length > 0 && <ul className="flex flex-wrap gap-2 text-xs text-muted-foreground">{room.amenities.map((name) => <li key={name} className="rounded-lg bg-surface px-2 py-1">{name}</li>)}</ul>}
            </div>
            <div className="mt-auto pt-5">
                <p className="border-t border-border pt-4 font-semibold">{formatCatalogPrice(room.price)} <span className="text-xs font-normal text-muted-foreground">/ đêm · tham khảo</span></p>
                {footer && <footer aria-label={'Thao tác cho ' + room.name} className={['mt-4 space-y-3 rounded-2xl p-3', selected ? 'bg-primary/5' : 'bg-surface/50'].join(' ')}>{footer}</footer>}
            </div>
        </div>
    </article>;
}
