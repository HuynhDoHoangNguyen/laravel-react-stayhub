import { Compass } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export default function SearchEmptyState() {
  return <Card className="rounded-3xl border-dashed bg-card">
    <CardContent className="flex flex-col items-center px-6 py-14 text-center">
      <Compass className="mb-4 size-9 text-primary" aria-hidden="true" />
      <h2 className="text-xl font-medium">Chưa tìm thấy phòng phù hợp</h2>
      <p className="mt-2 max-w-md text-sm text-muted-foreground">Thử đổi ngày nhận/trả hoặc bỏ bớt bộ lọc tiện nghi, loại phòng và khoảng giá.</p>
    </CardContent>
  </Card>;
}
