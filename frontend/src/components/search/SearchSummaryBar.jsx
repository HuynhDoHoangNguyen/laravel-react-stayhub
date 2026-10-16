import { useState } from 'react';
import { MapPin, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FieldShell from './FieldShell';
import DateField from './DateField';
import GuestsField from './GuestsField';

export default function SearchSummaryBar({ value, onSearch, loading }) {
  const [draft, setDraft] = useState(value);
  const set = (key, next) => setDraft((current) => ({ ...current, [key]: next }));
  const setCheckIn = (date) => {
    setDraft((current) => {
      const next = { ...current, check_in_date: date };
      if (date && current.check_out_date <= date) {
        const [year, month, day] = date.split('-').map(Number);
        const checkout = new Date(year, month - 1, day + 1);
        next.check_out_date = `${checkout.getFullYear()}-${String(checkout.getMonth() + 1).padStart(2, '0')}-${String(checkout.getDate()).padStart(2, '0')}`;
      }
      return next;
    });
  };

  return <form onSubmit={(event) => { event.preventDefault(); onSearch(draft); }} className="mx-auto grid max-w-7xl grid-cols-1 gap-1 rounded-3xl border border-border/70 bg-card p-2 shadow-[0_12px_45px_-32px_rgba(17,26,19,0.4)] sm:grid-cols-2 lg:grid-cols-[minmax(180px,1.3fr)_minmax(140px,1fr)_minmax(140px,1fr)_minmax(110px,.7fr)_auto]">
    <FieldShell icon={MapPin} label="Từ khóa / cơ sở">
      <Input aria-label="Từ khóa tìm kiếm" value={draft.keyword} onChange={(event) => set('keyword', event.target.value)} placeholder="Tên hoặc địa chỉ cơ sở" className="h-8 border-0 bg-transparent px-0 shadow-none focus-visible:ring-2" />
    </FieldShell>
    <DateField label="Ngày nhận" value={draft.check_in_date} onChange={setCheckIn} />
    <DateField label="Ngày trả" value={draft.check_out_date} minDate={draft.check_in_date} onChange={(date) => set('check_out_date', date)} />
    <GuestsField value={draft.guest_count} onChange={(count) => set('guest_count', count)} />
    <div className="flex items-center p-2 sm:col-span-2 lg:col-span-1">
      <Button type="submit" disabled={loading} className="h-12 w-full rounded-2xl px-6 lg:w-auto">
        <Search className="size-4" aria-hidden="true" />
        {loading ? 'Đang tìm' : 'Tìm phòng'}
      </Button>
    </div>
  </form>;
}
