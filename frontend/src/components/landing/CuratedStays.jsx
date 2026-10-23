import { ArrowRight } from "lucide-react";
import { motion } from "framer-motion";
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { PropertyCarousel } from "./PropertyCarousel";
import { propertyService } from "@/services/propertyService";

function CardSkeleton() {
  return (
    <div className="w-[300px] shrink-0 sm:w-[330px]">
      <div className="shimmer aspect-[4/3] rounded-3xl" />
      <div className="mt-4 space-y-2 px-1">
        <div className="shimmer h-4 w-2/3 rounded" />
        <div className="shimmer h-3 w-1/3 rounded" />
      </div>
    </div>
  );
}

function LoadingRow() {
  return (
    <div className="flex gap-6 overflow-hidden">
      {Array.from({ length: 4 }).map((_, i) => (
        <CardSkeleton key={i} />
      ))}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="rounded-3xl bg-surface/60 px-10 py-16 text-center">
      <h3 className="font-display text-2xl text-ink">
        Hiện chưa có danh sách lưu trú nào sẵn sàng.
      </h3>
      <p className="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
        Bộ sưu tập đang được cập nhật các địa điểm mới. Vui lòng quay lại sau để khám phá thêm nhiều không gian tuyệt đẹp.
      </p>
      <Link
        to="/search"
        className="mt-6 inline-flex items-center rounded-2xl bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary-mid"
      >
        Tìm kiếm nơi nghỉ
      </Link>
    </div>
  );
}

function ErrorState({ onRetry }) {
  return (
    <div className="rounded-3xl border border-border bg-background px-8 py-12 text-center">
      <h3 className="font-display text-xl text-ink">
        Không thể tải danh sách lưu trú vào lúc này.
      </h3>
      <p className="mt-2 text-sm text-muted-foreground">
        Vui lòng kiểm tra kết nối mạng và thử lại.
      </p>
      <button
        type="button"
        onClick={onRetry}
        className="mt-5 inline-flex items-center rounded-2xl border border-primary px-5 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-accent-pale/40"
      >
        Thử lại
      </button>
    </div>
  );
}

export function CuratedStays() {
  const [properties, setProperties] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    let active = true;

    propertyService
      .getFeaturedProperties()
      .then((res) => {
        if (active) {
          setProperties(res.data || []);
          setIsLoading(false);
        }
      })
      .catch(() => {
        if (active) {
          setIsError(true);
          setIsLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [retryCount]);

  const handleRetry = () => {
    setIsLoading(true);
    setIsError(false);
    setRetryCount((c) => c + 1);
  };

  return (
    <section aria-label="Nơi nghỉ dưỡng tuyển chọn" className="bg-background py-20 md:py-28">
      <div className="mx-auto grid max-w-[1440px] gap-12 px-6 md:px-10 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)] lg:gap-16">
        <motion.div
          initial={{ opacity: 0, y: 18 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true, margin: "-80px" }}
          transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
          className="lg:pt-4"
        >
          <p className="eyebrow">Tuyển chọn đặc sắc</p>
          <h2 className="font-display mt-4 text-[clamp(2rem,3.6vw,3rem)] font-light leading-[1.05] tracking-[-0.01em] text-ink">
            Không gian nghỉ dưỡng
            <br />
            đọng lại trong tâm trí.
          </h2>
          <p className="mt-5 max-w-sm text-[14.5px] leading-relaxed text-muted-foreground">
            Từ những căn biệt thự biển lộng gió đến homestay mộc mạc giữa rừng thông, mỗi nơi chốn đều được chọn lọc để giúp bạn tìm lại sự thư thái.
          </p>
          <Link
            to="/search"
            className="mt-8 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-medium text-primary-foreground transition-colors duration-200 hover:bg-primary-mid"
          >
            Khám phá tất cả
            <ArrowRight className="h-4 w-4" aria-hidden />
          </Link>
        </motion.div>

        <div className="min-w-0">
          {isLoading && <LoadingRow />}
          {!isLoading && isError && <ErrorState onRetry={handleRetry} />}
          {!isLoading && !isError && properties.length === 0 && <EmptyState />}
          {!isLoading && !isError && properties.length > 0 && (
            <PropertyCarousel properties={properties} />
          )}
        </div>
      </div>
    </section>
  );
}
