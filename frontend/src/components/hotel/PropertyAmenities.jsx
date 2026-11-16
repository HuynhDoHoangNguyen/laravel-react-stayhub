import { CheckCircle2 } from 'lucide-react';
export function PropertyAmenities({ amenities = [] }) {
    return <section className="space-y-4">
        <h2 className="font-display text-2xl text-primary">Tiện nghi tại các phòng</h2>
        <p className="text-sm text-muted-foreground">Tiện nghi có thể khác nhau theo từng phòng.</p>
        {amenities.length ? <ul className="grid gap-3 sm:grid-cols-2">{amenities.map((name) => <li key={name} className="flex items-center gap-2 text-sm"><CheckCircle2 className="h-4 w-4 text-primary" />{name}</li>)}</ul>
            : <p className="text-sm text-muted-foreground">Chưa có thông tin tiện nghi.</p>}
    </section>;
}
