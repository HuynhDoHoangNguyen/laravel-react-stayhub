import { Badge } from '@/components/ui/badge';
import { getBookingStatusInfo } from '@/constants/booking';

const statusStyles = {
  PENDING: 'border-accent/40 bg-accent-pale text-foreground',
  CONFIRMED: 'border-primary/20 bg-primary/10 text-primary',
  CHECKED_IN: 'border-primary-mid/30 bg-primary-mid/10 text-primary-mid',
  COMPLETED: 'border-primary/20 bg-primary text-primary-foreground',
  CANCELLED: 'border-border bg-muted text-muted-foreground',
  REJECTED: 'border-destructive/30 bg-destructive/10 text-destructive',
};

export default function StatusBadge({ status }) {
  const info = getBookingStatusInfo(status);
  return <Badge variant="outline" className={statusStyles[info.key] || 'bg-muted text-muted-foreground'}>{info.label}</Badge>;
}
