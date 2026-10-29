import CatalogImage from '../catalog/CatalogImage';
import { formatCatalogPrice } from '../../services/catalog';
export function RoomCard({ room }) {
    return <article className="overflow-hidden rounded-3xl border border-border/70 bg-white">
        <div className="aspect-[16/10]"><CatalogImage src={room.photos?.[0]} alt={room.name || 'Phòng nghỉ'} /></div>
        <div className="space-y-3 p-5">
            <p className="text-xs text-muted-foreground">{room.roomTypeName}</p>
            <h3 className="font-display text-xl text-primary">{room.name}</h3>
            {room.description && <p className="whitespace-pre-line text-sm text-muted-foreground">{room.description}</p>}
            <p className="text-sm">Sức chứa tối đa: {room.capacity} khách</p>
            {room.amenities?.length > 0 && <ul className="flex flex-wrap gap-2 text-xs text-muted-foreground">{room.amenities.map((name) => <li key={name} className="rounded-lg bg-surface px-2 py-1">{name}</li>)}</ul>}
            <p className="border-t border-border pt-3 font-semibold">{formatCatalogPrice(room.price)} <span className="text-xs font-normal text-muted-foreground">/ đêm · tham khảo</span></p>
        </div>
    </article>;
}
