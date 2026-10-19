import { motion } from "framer-motion";
import { useNavigate } from "react-router-dom";
import { SearchBar } from "./SearchBar";
import { ConversationalSearchBox } from "@/components/search/ConversationalSearchBox";
import heroAsset from "@/assets/hero.png";

export function Hero() {
  const navigate = useNavigate();

  const handleNL = (query) => {
    navigate(`/search?city=${encodeURIComponent(query)}`);
  };

  return (
    <section
      className="relative isolate overflow-hidden flex flex-col justify-between"
      style={{ height: "min(94vh, 960px)", minHeight: 740 }}
    >
      <div className="absolute inset-0 -z-10 overflow-hidden">
        <img
          src={heroAsset}
          alt="Biệt thự nghỉ dưỡng cao cấp bên hồ lúc hoàng hôn"
          fetchPriority="high"
          decoding="async"
          className="h-full w-full object-cover animate-ken-burns"
        />
        <div
          aria-hidden
          className="absolute inset-0"
          style={{
            background:
              "linear-gradient(90deg, rgba(253,250,246,0.82) 0%, rgba(253,250,246,0.52) 38%, rgba(253,250,246,0) 70%)",
          }}
        />
      </div>

      <div className="mx-auto flex h-full w-full max-w-[1440px] flex-1 flex-col justify-between px-6 pt-28 pb-10 md:px-10 md:pt-32 md:pb-12">
        <motion.div
          initial={{ opacity: 0, y: 14 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
          className="max-w-4xl"
        >
          <p className="eyebrow">Chào mừng đến với StayHub</p>
          <h1 className="font-display mt-5 text-[clamp(2.4rem,5vw,4.5rem)] font-light leading-[1.08] tracking-[-0.02em] text-ink text-balance">
            Nơi dừng chân hoàn hảo.
            <br />
            Trọn vẹn từng khoảnh khắc.
          </h1>
          <p className="mt-6 max-w-2xl text-[15px] leading-relaxed text-ink/80">
            Tuyển chọn những không gian lưu trú và homestay thanh lịch khắp Việt Nam.
            <br />
            Kỳ nghỉ an yên, trải nghiệm trọn vẹn.
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.25, duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
          className="mt-8 w-full max-w-5xl space-y-3.5"
        >
          <SearchBar />
          <ConversationalSearchBox compact onSubmit={handleNL} />
        </motion.div>
      </div>
    </section>
  );
}
