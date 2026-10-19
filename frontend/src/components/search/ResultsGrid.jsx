import { motion } from "framer-motion";
import { SearchPropertyCard } from "./SearchPropertyCard";

function CardSkeleton({ view }) {
  const isList = view === "list";
  return (
    <div
      className={[
        "overflow-hidden rounded-3xl bg-card ring-1 ring-black/[0.04]",
        isList ? "flex flex-col md:flex-row" : "",
      ].join(" ")}
    >
      <div
        className={[
          "shimmer w-full shrink-0",
          isList ? "aspect-[16/10] md:w-[320px]" : "aspect-[16/11]",
        ].join(" ")}
      />
      <div className="flex-1 space-y-3 p-5">
        <div className="shimmer h-5 w-2/3 rounded-lg" />
        <div className="shimmer h-4 w-1/3 rounded-lg" />
        <div className="shimmer mt-6 h-9 w-full rounded-full" />
      </div>
    </div>
  );
}

export function ResultsGrid({ properties, isLoading, context, view = "grid" }) {
  // Grid columns adapted for the 3-column layout on desktop:
  // Mobile: 1 col, Tablet: 2 cols, Desktop (lg with 3-col layout): 1 col (320-380px), Wide Desktop (xl+): 2 cols (300-380px)
  const containerClass =
    view === "list"
      ? "flex flex-col gap-5"
      : "grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 2xl:grid-cols-2";

  if (isLoading && properties.length === 0) {
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
      {properties.map((p, i) => (
        <motion.div
          key={p.id}
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{
            duration: 0.45,
            delay: Math.min(i * 0.05, 0.3),
            ease: [0.16, 1, 0.3, 1],
          }}
        >
          <SearchPropertyCard property={p} context={context} view={view} />
        </motion.div>
      ))}
    </div>
  );
}
