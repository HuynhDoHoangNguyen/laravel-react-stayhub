import CatalogImage from '../catalog/CatalogImage';
import { formatCatalogPrice } from '../../services/catalog';
export function RoomCard({ room, availability, footer, selected = false, variant = 'default' }) {
    const compact = variant === 'compact';
    const actions = footer && <footer aria-label={'Thao tác cho ' + room.name} className={[
        compact ? 'col-span-full flex min-w-0 flex-wrap justify-end gap-2 sm:col-span-1 sm:grid sm:grid-cols-1 sm:self-center' : 'mt-4 space-y-3 rounded-2xl p-3',
        selected && 'bg-primary/5',
    ].filter(Boolean).join(' ')}>{footer}</footer>;
    return <article data-selected={selected || undefined} className={['min-w-0 overflow-hidden border bg-card shadow-[0_8px_24px_-16px_rgba(26,46,32,0.3)] motion-safe:transition-shadow motion-safe:duration-200 hover:shadow-[0_16px_36px_-20px_rgba(26,46,32,0.4)]',
        compact ? 'grid grid-cols-[4rem_minmax(0,1fr)] items-start gap-3 rounded-2xl p-3' : 'flex h-full flex-col rounded-3xl',
        compact && (actions ? 'sm:grid-cols-[5.5rem_minmax(0,1fr)_5.5rem]' : 'sm:grid-cols-[5.5rem_minmax(0,1fr)]'),
        selected ? 'border-primary ring-2 ring-primary/15' : 'border-border/70'].filter(Boolean).join(' ')}>
        <div className={compact ? 'aspect-square overflow-hidden rounded-xl' : 'aspect-[16/10] shrink-0 overflow-hidden'}>
            <CatalogImage src={room.photos?.[0]} alt={room.name || 'Phòng nghỉ'}
                className={compact ? 'min-h-0 gap-1 text-center [&>svg]:size-5' : ''} />
        </div>
        <div className={compact ? 'min-w-0 space-y-1' : 'flex flex-1 flex-col p-5'}>
            <div className={compact ? 'space-y-1 break-words' : 'space-y-3 break-words'}>
                <p className="text-xs text-muted-foreground">{room.roomTypeName}</p>
                <h3 className={compact ? 'font-display text-lg leading-tight text-primary' : 'font-display text-xl text-primary'}>{room.name}</h3>
                {!compact && room.description && <p className="whitespace-pre-line text-sm text-muted-foreground">{room.description}</p>}
                <p className="text-sm">Sức chứa tối đa: {room.capacity} khách</p>
                {!compact && room.amenities?.length > 0 && <ul className="flex flex-wrap gap-2 text-xs text-muted-foreground">{room.amenities.map((name) => <li key={name} className="rounded-lg bg-surface px-2 py-1">{name}</li>)}</ul>}
            </div>
            <div className={compact ? 'min-w-0 space-y-1' : 'mt-auto pt-5'}>
                <p className={compact ? 'break-words text-sm font-semibold' : 'border-t border-border pt-4 font-semibold'}>{formatCatalogPrice(room.price)} <span className="text-xs font-normal text-muted-foreground">/ đêm · tham khảo</span></p>
                {compact ? availability : actions}
            </div>
        </div>
        {compact && actions}
    </article>;
}
