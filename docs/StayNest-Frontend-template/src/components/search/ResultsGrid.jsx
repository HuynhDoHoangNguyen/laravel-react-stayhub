import { motion } from "framer-motion";
import { SearchHotelCard } from "./SearchHotelCard";

function CardSkeleton({ view }) {
  const isList = view === "list";
  return (
    <div className={["overflow-hidden rounded-3xl bg-card ring-1 ring-black/[0.04]", isList ? "flex flex-col md:flex-row" : ""].join(" ")}>
      <div className={["shimmer w-full shrink-0", isList ? "aspect-[16/10] md:w-[320px]" : "aspect-[16/11]"].join(" ")} />
      <div className="flex-1 space-y-2 p-5">
        <div className="shimmer h-4 w-2/3 rounded" />
        <div className="shimmer h-3 w-1/3 rounded" />
        <div className="shimmer mt-4 h-8 w-full rounded-full" />
      </div>
    </div>
  );
}

export function ResultsGrid({ hotels, isLoading, context, view = "grid" }) {
  const containerClass = view === "list"
    ? "flex flex-col gap-5"
    : "grid gap-5 grid-cols-1 sm:grid-cols-2 xl:grid-cols-3";

  if (isLoading && hotels.length === 0) {
    return (
      <div className={containerClass}>
        {Array.from({ length: 6 }).map((_, i) => (
          <CardSkeleton key={i} view={view} />
        ))}
      </div>
    );
  }

  return (
    <div className={containerClass}>
      {hotels.map((h, i) => (
        <motion.div
          key={h.id}
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{
            duration: 0.45,
            delay: Math.min(i * 0.05, 0.3),
            ease: [0.16, 1, 0.3, 1],
          }}
        >
          <SearchHotelCard hotel={h} context={context} view={view} />
        </motion.div>
      ))}
    </div>
  );
}
