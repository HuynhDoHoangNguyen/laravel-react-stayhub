import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { formatCurrency } from '@/utils/formatCurrency';

export default function FilterSidebar({ value, roomTypes, amenities, maxAvailablePrice, onApply, onReset }) {
  const upperBound = Math.max(0, maxAvailablePrice || 0, Number(value.min_price) || 0, Number(value.max_price) || 0);
  const [draft, setDraft] = useState(value);
  const [priceRange, setPriceRange] = useState([value.min_price ?? 0, value.max_price ?? upperBound]);

  const toggleAmenity = (id) => setDraft((current) => ({
    ...current,
    amenity_ids: current.amenity_ids.includes(id) ? current.amenity_ids.filter((item) => item !== id) : [...current.amenity_ids, id],
  }));

  return <Card className="rounded-3xl border-border/60 bg-card shadow-[0_12px_40px_-30px_rgba(17,26,19,0.35)] lg:sticky lg:top-6">
    <CardContent className="space-y-6 p-5">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold uppercase tracking-[0.14em]">Bộ lọc</h2>
        <Button variant="ghost" size="sm" onClick={onReset}>Xóa lọc</Button>
      </div>
      <section className="space-y-3">
        <Label htmlFor="room-type-filter">Loại phòng</Label>
        <Select value={draft.room_type_id || 'all'} onValueChange={(room_type_id) => setDraft((current) => ({ ...current, room_type_id: room_type_id === 'all' ? '' : room_type_id }))}>
          <SelectTrigger id="room-type-filter" aria-label="Lọc theo loại phòng"><SelectValue placeholder="Tất cả loại phòng" /></SelectTrigger>
          <SelectContent><SelectItem value="all">Tất cả loại phòng</SelectItem>{roomTypes.map((type) => <SelectItem key={type.id} value={String(type.id)}>{type.name}</SelectItem>)}</SelectContent>
        </Select>
      </section>
      <section className="space-y-4">
        <Label id="price-filter-label">Khoảng giá mỗi đêm</Label>
        {upperBound > 0 ? <>
          <Slider aria-labelledby="price-filter-label" min={0} max={upperBound} step={Math.max(1, Math.round(upperBound / 100))} value={priceRange} onValueChange={setPriceRange} />
          <div className="flex justify-between text-xs text-muted-foreground"><span>{formatCurrency(priceRange[0])}</span><span>{formatCurrency(priceRange[1])}</span></div>
        </> : <p className="text-sm text-muted-foreground">Giá sẽ hiện sau khi có kết quả tìm kiếm.</p>}
      </section>
      {amenities.length > 0 && <fieldset className="space-y-3">
        <legend className="text-sm font-medium">Tiện nghi</legend>
        {amenities.map((amenity) => <label key={amenity.id} className="flex cursor-pointer items-center gap-2 text-sm text-foreground">
          <input type="checkbox" checked={draft.amenity_ids.includes(String(amenity.id))} onChange={() => toggleAmenity(String(amenity.id))} className="size-4 rounded border-input accent-primary focus-visible:outline-2 focus-visible:outline-ring" />
          <span>{amenity.name}</span>
        </label>)}
      </fieldset>}
      <section className="space-y-3">
        <Label htmlFor="search-sort">Sắp xếp</Label>
        <Select value={draft.sort} onValueChange={(sort) => setDraft((current) => ({ ...current, sort }))}>
          <SelectTrigger id="search-sort"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="price_asc">Giá thấp đến cao</SelectItem>
            <SelectItem value="price_desc">Giá cao đến thấp</SelectItem>
            <SelectItem value="newest">Mới nhất</SelectItem>
          </SelectContent>
        </Select>
      </section>
      <Button className="w-full rounded-full" onClick={() => onApply({ ...draft, min_price: priceRange[0] || '', max_price: priceRange[1] < upperBound ? priceRange[1] : draft.max_price })}>Áp dụng bộ lọc</Button>
    </CardContent>
  </Card>;
}
