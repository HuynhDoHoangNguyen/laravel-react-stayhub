import { motion, useReducedMotion } from 'framer-motion';
import { SearchRoomCard } from './SearchRoomCard';
import { SearchPropertyCard } from './SearchPropertyCard';

function CardSkeleton({ view }) {
    return <div className={['overflow-hidden rounded-3xl bg-card ring-1 ring-black/[0.04]', view === 'list' ? 'flex flex-col xl:flex-row' : ''].join(' ')} aria-hidden>
        <div className={['w-full shrink-0 bg-surface motion-safe:animate-pulse', view === 'list' ? 'aspect-[16/10] xl:w-[240px]' : 'aspect-[16/11]'].join(' ')} />
        <div className="flex-1 space-y-3 p-5 motion-safe:animate-pulse"><div className="h-5 w-2/3 rounded-lg bg-surface" /><div className="h-4 w-1/3 rounded-lg bg-surface" /><div className="mt-6 h-9 w-full rounded-full bg-surface" /></div>
    </div>;
}

export function ResultsGrid({ rooms = [], properties, isLoading, view = 'grid' }) {
    const reducedMotion = useReducedMotion();
    const layout = view === 'list' ? 'flex flex-col gap-5' : 'grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2';
    return <div className={layout} aria-busy={isLoading}>
        {isLoading ? Array.from({ length: 6 }, (_, index) => <CardSkeleton key={index} view={view} />)
            : (properties ?? rooms).map((item, index) => <motion.div key={item.id} className="min-w-0"
                initial={reducedMotion ? false : { opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}
                transition={{ duration: reducedMotion ? 0 : 0.45, delay: reducedMotion ? 0 : Math.min(index * 0.05, 0.3), ease: [0.16, 1, 0.3, 1] }}>
                {properties ? <SearchPropertyCard property={item} view={view} /> : <SearchRoomCard room={item} view={view} />}
            </motion.div>)}
    </div>;
}
