import { motion } from "framer-motion";
import { useNavigate } from "react-router-dom";
import { SearchBar } from "./SearchBar";
import { ConversationalSearchBox } from "@/components/search/ConversationalSearchBox";
import heroAsset from "@/assets/hero.png";

export function Hero() {
  const navigate = useNavigate();
  const handleNL = (query) => {
    navigate(`/search?q=${encodeURIComponent(query)}`);
  };

  return (
    <section className="relative isolate overflow-hidden" style={{ height: "min(92vh, 940px)", minHeight: 720 }}>
      <div className="absolute inset-0 -z-10 overflow-hidden">
        <img
          src={heroAsset}
          alt="Cliffside boutique villa overlooking a lake at golden hour"
          fetchPriority="high"
          decoding="async"
          className="h-full w-full object-cover animate-ken-burns"
        />
        <div
          aria-hidden
          className="absolute inset-0"
          style={{
            background:
              "linear-gradient(90deg, rgba(253,250,246,0.72) 0%, rgba(253,250,246,0.35) 32%, rgba(253,250,246,0) 60%)",
          }}
        />
      </div>

      <div className="mx-auto flex h-full max-w-[1440px] flex-col justify-between px-6 pt-32 pb-10 md:px-10">
        <motion.div
          initial={{ opacity: 0, y: 14 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
          className="max-w-2xl"
        >
          <p className="eyebrow">Welcome to StayNest</p>
          <h1 className="font-display mt-5 text-[clamp(2.6rem,6vw,5rem)] font-light leading-[1.02] tracking-[-0.02em] text-ink">
            Find your nest.
            <br />
            Make it memorable.
          </h1>
          <p className="mt-6 max-w-md text-[15px] leading-relaxed text-ink/80">
            Curated stays for the modern traveler.
            <br />
            Peaceful getaways. Meaningful experiences.
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.25, duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
          className="mt-10 w-full max-w-5xl space-y-4"
        >
          <SearchBar />
          <ConversationalSearchBox compact onSubmit={handleNL} />
        </motion.div>
      </div>
    </section>
  );
}
