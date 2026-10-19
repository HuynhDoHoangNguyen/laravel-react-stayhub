import { useState, useEffect, useRef } from "react";
import { motion } from "framer-motion";
import { useNavigate } from "react-router-dom";
import { SearchBar } from "./SearchBar";
import { ConversationalSearchBox } from "@/components/search/ConversationalSearchBox";
import heroAsset from "@/assets/hero.png";

/**
 * Homepage Hero Banner (UI-HERO-VIDEO-01).
 *
 * Tích hợp video nền lặp liên tục (`/videos/bannervideo.mp4`) theo chuẩn Quiet Luxury.
 * Giữ nguyên typography, SearchBar, conversational search box và các animations hiện có.
 * Hỗ trợ fallback ảnh tĩnh `hero.png`, prefers-reduced-motion và tối ưu hóa hiệu năng
 * khi cuộn ra ngoài viewport hoặc khi tab trình duyệt bị ẩn.
 */
export function Hero() {
  const navigate = useNavigate();
  const videoRef = useRef(null);

  const [prefersReducedMotion, setPrefersReducedMotion] = useState(() => {
    if (typeof window === "undefined") return false;
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  });
  const [videoLoaded, setVideoLoaded] = useState(false);
  const [videoError, setVideoError] = useState(false);

  // 1. Lắng nghe thay đổi thiết lập prefers-reduced-motion của hệ điều hành
  useEffect(() => {
    if (typeof window === "undefined") return;

    const mediaQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
    const handleMotionChange = (e) => {
      setPrefersReducedMotion(e.matches);
    };

    mediaQuery.addEventListener("change", handleMotionChange);
    return () => mediaQuery.removeEventListener("change", handleMotionChange);
  }, []);

  // 2. Tự động tạm dừng video khi tab bị ẩn để tiết kiệm pin & tài nguyên CPU/GPU
  useEffect(() => {
    if (prefersReducedMotion || videoError) return;

    const handleVisibilityChange = () => {
      if (!videoRef.current) return;
      if (document.hidden) {
        videoRef.current.pause();
      } else {
        videoRef.current.play().catch(() => {});
      }
    };

    document.addEventListener("visibilitychange", handleVisibilityChange);
    return () => document.removeEventListener("visibilitychange", handleVisibilityChange);
  }, [prefersReducedMotion, videoError]);

  // 3. Tối ưu hiệu năng: Tạm dừng phát video khi Hero cuộn ra khỏi viewport
  useEffect(() => {
    if (prefersReducedMotion || videoError) return;

    const videoEl = videoRef.current;
    if (!videoEl || typeof IntersectionObserver === "undefined") return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!videoRef.current) return;
          if (entry.isIntersecting && !document.hidden) {
            videoRef.current.play().catch(() => {});
          } else {
            videoRef.current.pause();
          }
        });
      },
      { threshold: 0.15 }
    );

    observer.observe(videoEl);
    return () => observer.disconnect();
  }, [prefersReducedMotion, videoError]);

  const handleNL = (query) => {
    navigate(`/search?city=${encodeURIComponent(query)}`);
  };

  return (
    <section
      className="relative isolate overflow-hidden flex flex-col justify-between"
      style={{ height: "min(90vh, 880px)", minHeight: 640 }}
      aria-label="StayHub Hero Banner"
    >
      {/* Background Media Container */}
      <div className="absolute inset-0 -z-10 overflow-hidden bg-background">
        {/* Fallback Static Image: Hiển thị tức thì khi tải hoặc khi video lỗi / giảm chuyển động */}
        <img
          src={heroAsset}
          alt="Biệt thự nghỉ dưỡng cao cấp bên hồ lúc hoàng hôn"
          fetchPriority="high"
          decoding="async"
          className={`h-full w-full object-cover transition-opacity duration-1000 ${
            prefersReducedMotion || videoError
              ? "opacity-100 animate-ken-burns"
              : videoLoaded
              ? "opacity-0 pointer-events-none"
              : "opacity-100"
          }`}
          style={{ objectPosition: "center 38%" }}
        />

        {/* Looping Cinematic Video Background */}
        {!prefersReducedMotion && !videoError && (
          <video
            ref={videoRef}
            src="/videos/bannervideo.mp4"
            poster={heroAsset}
            autoPlay
            muted
            loop
            playsInline
            preload="metadata"
            disablePictureInPicture
            controls={false}
            onCanPlay={() => setVideoLoaded(true)}
            onError={() => setVideoError(true)}
            className={`absolute inset-0 h-full w-full object-cover pointer-events-none transition-opacity duration-1000 ${
              videoLoaded ? "opacity-100" : "opacity-0"
            }`}
            style={{
              objectPosition: "center 38%",
              filter: "contrast(1.07) saturate(1.05) brightness(0.97)",
              transform: "translate3d(0, 0, 0)",
            }}
            aria-hidden="true"
          />
        )}

        {/* Quiet Luxury Warm Sand Gradient Overlay đảm bảo độ tương phản hoàn hảo cho văn bản & Navbar */}
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0"
          style={{
            background:
              "linear-gradient(90deg, rgba(253,250,246,0.85) 0%, rgba(253,250,246,0.52) 38%, rgba(253,250,246,0.12) 70%, rgba(253,250,246,0.03) 100%)",
          }}
        />

        {/* Lớp phủ điện ảnh khử vỡ hạt video và đồng bộ tông màu hoàng hôn ấm áp */}
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0"
          style={{
            background:
              "radial-gradient(ellipse 95% 85% at 75% 45%, rgba(253,250,246,0) 40%, rgba(26,46,32,0.12) 100%)",
          }}
        />
      </div>

      {/* Hero Content: Tiêu đề, Phụ đề, Thanh tìm kiếm */}
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
