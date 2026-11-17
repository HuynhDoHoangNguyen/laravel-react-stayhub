import { motion } from "framer-motion";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { SearchPropertyCard } from "./SearchPropertyCard";

function CardSkeleton({ view }) {
  const isList = view === "list";
  return (
    <Card
      aria-hidden="true"
      className={[
        "overflow-hidden rounded-3xl border-border/60 p-0 shadow-sm",
        isList ? "flex flex-col md:flex-row" : "",
      ].join(" ")}
    >
      <Skeleton
        className={[
          "w-full shrink-0 rounded-none bg-surface",
          isList ? "aspect-[16/10] md:w-[320px]" : "aspect-[16/11]",
        ].join(" ")}
      />
      <div className="flex-1 space-y-3 p-5">
        <Skeleton className="h-5 w-2/3 rounded-lg" />
        <Skeleton className="h-4 w-1/3 rounded-lg" />
        <Skeleton className="mt-6 h-9 w-full rounded-full" />
      </div>
    </Card>
  );
}

export function ResultsGrid({ properties, isLoading, context, view = "grid" }) {
  // Wider result column is used until the assistant panel joins the layout at xl.
  const containerClass =
    view === "list"
      ? "flex flex-col gap-5"
      : "grid gap-5 grid-cols-1 sm:grid-cols-2 xl:grid-cols-2";

  if (isLoading) {
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
          key={p.resultId ?? p.id}
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
