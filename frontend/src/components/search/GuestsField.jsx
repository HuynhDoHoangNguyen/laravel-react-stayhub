import FieldShell from './FieldShell';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function GuestsField({ value, onChange }) {
  return <FieldShell label="Số khách">
    <Label htmlFor="search-guest-count" className="sr-only">Số khách</Label>
    <Input id="search-guest-count" type="number" min="1" max="50" value={value} onChange={(event) => onChange(event.target.value)} className="h-8 border-0 bg-transparent px-0 shadow-none focus-visible:ring-2" />
  </FieldShell>;
}
