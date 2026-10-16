import { CalendarDays, MapPin, ArrowUpRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import StatusBadge from './shared/StatusBadge';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDate } from '@/utils/formatDate';

export default function BookingCard({ booking }) {
  return <Card className="overflow-hidden rounded-3xl border-border/60 transition-shadow motion-safe:duration-300 hover:shadow-lg">
    <CardContent className="space-y-4 p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Mã đặt phòng</p><p className="mt-1 font-semibold text-primary">{booking.booking_code}</p></div>
        <StatusBadge status={booking.status} />
      </div>
      <div><h2 className="text-lg font-medium">{booking.room?.name || 'Phòng lưu trú'}</h2><p className="mt-1 text-sm text-muted-foreground">{booking.room?.property?.name || 'Cơ sở lưu trú'}{booking.room?.room_number ? ` · Phòng ${booking.room.room_number}` : ''}</p></div>
      {booking.room?.property?.address && <p className="flex gap-2 text-sm text-muted-foreground"><MapPin className="size-4 shrink-0" aria-hidden="true" />{booking.room.property.address}</p>}
      <p className="flex items-center gap-2 text-sm text-muted-foreground"><CalendarDays className="size-4" aria-hidden="true" />{formatDate(booking.check_in_date)} – {formatDate(booking.check_out_date)} · {booking.number_of_nights} đêm</p>
    </CardContent>
    <CardFooter className="flex items-center justify-between border-t border-border/60 px-5 py-4">
      <div><p className="text-xs text-muted-foreground">Tiền phòng</p><p className="font-semibold text-primary">{formatCurrency(booking.room_total)}</p></div>
      <Button asChild variant="outline" size="sm"><Link to={`/customer/bookings/${booking.id}`}>Chi tiết <ArrowUpRight className="size-4" aria-hidden="true" /></Link></Button>
    </CardFooter>
  </Card>;
}
