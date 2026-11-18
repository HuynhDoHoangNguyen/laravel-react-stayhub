import { SearchRoomCard } from './SearchRoomCard';

export function ResultsGrid({ rooms, isLoading, view = 'grid' }) {
    const layout = view === 'list' ? 'flex flex-col gap-5' : 'grid grid-cols-1 gap-6 md:grid-cols-2';
    return <div className={layout} aria-busy={isLoading}>
        {isLoading ? Array.from({ length: 4 }, (_, i) => <div key={i} className="animate-pulse overflow-hidden rounded-3xl border border-border bg-card" aria-hidden>
            <div className="aspect-[16/10] bg-surface" /><div className="space-y-4 p-5"><div className="h-5 w-2/3 rounded bg-surface" /><div className="h-5 w-1/2 rounded bg-surface" /></div>
        </div>) : rooms.map(room => <SearchRoomCard key={room.id} room={room} view={view} />)}
    </div>;
}
