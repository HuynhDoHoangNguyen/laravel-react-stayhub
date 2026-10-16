import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import RoomResultCard from './RoomResultCard';

export default function ResultsGrid({ rooms, loading, bookingSearch }) {
  if (loading) return <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-2" aria-label="Đang tải kết quả">
    {Array.from({ length: 4 }, (_, index) => <Card key={index} className="overflow-hidden rounded-3xl"><Skeleton className="aspect-[16/10] rounded-none" /><CardContent className="space-y-3 p-5"><Skeleton className="h-5 w-2/3" /><Skeleton className="h-4 w-full" /><Skeleton className="h-4 w-1/2" /></CardContent><CardFooter><Skeleton className="h-10 w-full" /></CardFooter></Card>)}
  </div>;
  return <div className="grid gap-5 sm:grid-cols-2">{rooms.map((room) => <RoomResultCard key={room.id} room={room} bookingSearch={bookingSearch} />)}</div>;
}
