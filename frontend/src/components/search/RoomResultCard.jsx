import { Check, MapPin, Users } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import roomPlaceholder from '@/assets/room-placeholder.svg';
import { formatCurrency } from '@/utils/formatCurrency';

const backendUrl = import.meta.env.VITE_BACKEND_URL || '';

function imageUrl(path) {
  if (!path) return roomPlaceholder;
  if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:')) return path;
  if (path.startsWith('/')) return `${backendUrl.replace(/\/$/, '')}${path}`;
  return `${backendUrl.replace(/\/$/, '')}/storage/${path.replace(/^\//, '')}`;
}

export default function RoomResultCard({ room, bookingSearch }) {
  const primaryImage = room.images?.find((image) => image.is_primary) || room.images?.[0];
  const [imgSrc, setImgSrc] = useState(() => imageUrl(primaryImage?.image_path));
  const checkout = new URLSearchParams({
    room_id: String(room.id),
    check_in_date: bookingSearch.check_in_date,
    check_out_date: bookingSearch.check_out_date,
    guest_count: String(bookingSearch.guest_count),
  });

  return <Card className="group overflow-hidden rounded-3xl border-border/60 bg-card shadow-[0_12px_40px_-30px_rgba(17,26,19,0.4)] transition-shadow motion-safe:duration-300 hover:shadow-[0_24px_60px_-35px_rgba(17,26,19,0.55)]">
    <div className="aspect-[16/10] overflow-hidden bg-surface">
      <img src={imgSrc} alt={`Phòng ${room.name} tại ${room.property?.name || 'cơ sở lưu trú'}`} loading="lazy" onError={() => setImgSrc(roomPlaceholder)} className="h-full w-full object-cover transition-transform motion-safe:duration-500 group-hover:scale-[1.03]" />
    </div>
    <CardContent className="space-y-3 p-5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="truncate text-xl font-medium text-foreground">{room.name}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{room.property?.name || 'Cơ sở lưu trú'}{room.room_number ? ` · Phòng ${room.room_number}` : ''}</p>
        </div>
        {room.room_type?.name && <Badge variant="secondary" className="shrink-0">{room.room_type.name}</Badge>}
      </div>
      {room.property?.address && <p className="flex gap-2 text-sm text-muted-foreground"><MapPin className="mt-0.5 size-4 shrink-0" aria-hidden="true" /><span>{room.property.address}</span></p>}
      <p className="flex items-center gap-2 text-sm text-muted-foreground"><Users className="size-4" aria-hidden="true" /> Tối đa {room.capacity} khách</p>
      {room.amenities?.length > 0 && <ul className="flex flex-wrap gap-2" aria-label="Tiện nghi">
        {room.amenities.slice(0, 5).map((amenity) => <li key={amenity.id}><Badge variant="outline" className="gap-1 font-normal"><Check className="size-3" aria-hidden="true" />{amenity.name}</Badge></li>)}
      </ul>}
    </CardContent>
    <CardFooter className="flex flex-wrap items-end justify-between gap-4 border-t border-border/60 px-5 py-4">
      <div><p className="text-xs text-muted-foreground">Giá mỗi đêm</p><p className="text-lg font-semibold text-primary">{formatCurrency(room.price_per_night)}</p></div>
      <Button asChild><Link to={`/booking/checkout?${checkout.toString()}`}>Đặt phòng</Link></Button>
    </CardFooter>
  </Card>;
}
