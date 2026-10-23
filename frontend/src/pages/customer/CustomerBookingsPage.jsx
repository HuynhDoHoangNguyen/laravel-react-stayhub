import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Compass,
  FileText,
  Sparkles,
  ArrowLeft,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
} from 'lucide-react';
import { BookingStats } from '@/components/bookings/BookingStats';
import { BookingCard } from '@/components/bookings/BookingCard';
import { QuickPicks } from '@/components/bookings/QuickPicks';
import { bookingService } from '@/services/bookingService';

const TABS = [
  { key: 'PENDING', label: 'Chờ xác nhận' },
  { key: 'UPCOMING', label: 'Sắp tới' },
  { key: 'CHECKED_IN', label: 'Đang lưu trú' },
  { key: 'COMPLETED', label: 'Hoàn thành' },
  { key: 'CANCELLED', label: 'Đã hủy' },
  { key: 'REJECTED', label: 'Bị từ chối' },
  { key: 'ALL', label: 'Tất cả' },
];

const SORT_LABELS = {
  NEW: 'Ngày nhận phòng (Mới nhất)',
  OLD: 'Ngày nhận phòng (Cũ nhất)',
  PRICE_DESC: 'Giá phòng cao nhất',
  PRICE_ASC: 'Giá phòng thấp nhất',
  RECENT: 'Vừa đặt gần đây',
};

const PAGE_SIZE = 5;

export default function CustomerBookingsPage() {
  const [tab, setTab] = useState('UPCOMING');
  const [sort, setSort] = useState('NEW');
  const [sortOpen, setSortOpen] = useState(false);
  const [page, setPage] = useState(1);
  const [reloadTrigger, setReloadTrigger] = useState(0);

  const [bookings, setBookings] = useState([]);
  const [allBookings, setAllBookings] = useState([]);
  const [stats, setStats] = useState(null);
  const [totalCount, setTotalCount] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState(null);
  const [isMockData, setIsMockData] = useState(false);

  useEffect(() => {
    let ignore = false;

    async function load() {
      try {
        const res = await bookingService.getMyBookings({
          tab,
          sort,
          page,
          perPage: PAGE_SIZE,
        });

        if (!ignore) {
          setBookings(res.data || []);
          setAllBookings(res.all || []);
          setStats(res.stats || null);
          setTotalCount(res.meta?.total || 0);
          setTotalPages(res.meta?.total_pages || res.meta?.last_page || 1);
          setIsMockData(Boolean(res.isMock));
          setErrorMessage(null);
          setIsLoading(false);
        }
      } catch (err) {
        if (!ignore) {
          setBookings([]);
          setAllBookings([]);
          setStats(null);
          setTotalCount(0);
          setTotalPages(1);
          setErrorMessage(err.message || 'Không thể tải danh sách đơn đặt phòng.');
          setIsLoading(false);
        }
      }
    }

    load();

    return () => {
      ignore = true;
    };
  }, [tab, sort, page, reloadTrigger]);

  const handleTabChange = (newTab) => {
    setTab(newTab);
    setPage(1);
    setIsLoading(true);
  };

  const handleSortChange = (newSort) => {
    setSort(newSort);
    setSortOpen(false);
    setPage(1);
    setIsLoading(true);
  };

  const handlePageChange = (newPage) => {
    setPage(newPage);
    setIsLoading(true);
  };

  const handleRetry = () => {
    setIsLoading(true);
    setReloadTrigger((v) => v + 1);
  };

  // Thông tin trạng thái Empty tùy biến theo từng tab
  const emptyInfo = useMemo(() => {
    switch (tab) {
      case 'PENDING':
        return {
          title: 'Chưa có đơn chờ xác nhận',
          desc: 'Các đơn đang chờ cơ sở lưu trú phản hồi sẽ hiển thị tại đây.',
          showExplore: false,
        };
      case 'UPCOMING':
        return {
          title: 'Chưa có kỳ nghỉ nào sắp diễn ra',
          desc: 'Hành trình tiếp theo đang chờ bạn. Khám phá những chỗ nghỉ boutique tinh tế để lên kế hoạch cho kỳ nghỉ tuyệt vời.',
          showExplore: true,
        };
      case 'CHECKED_IN':
        return {
          title: 'Chưa có kỳ nghỉ nào đang lưu trú',
          desc: 'Khi bạn đến nhận phòng tại cơ sở lưu trú, thông tin kỳ nghỉ đang diễn ra sẽ hiển thị tại đây.',
          showExplore: false,
        };
      case 'COMPLETED':
        return {
          title: 'Chưa có kỳ nghỉ nào hoàn thành',
          desc: 'Khi bạn kết thúc chuyến lưu trú, kỷ niệm và hóa đơn quyết toán sẽ được lưu giữ đầy đủ tại đây.',
          showExplore: false,
        };
      case 'CANCELLED':
        return {
          title: 'Không có đơn đặt phòng nào bị hủy hoặc bị từ chối',
          desc: 'Mọi đơn đặt phòng bị hủy hoặc bị cơ sở từ chối tiếp nhận sẽ hiển thị tại đây để bạn tiện theo dõi.',
          showExplore: false,
        };
      case 'REJECTED':
        return {
          title: 'Chưa có đơn bị từ chối',
          desc: 'Các đơn không được cơ sở lưu trú tiếp nhận sẽ hiển thị tại đây.',
          showExplore: false,
        };
      case 'ALL':
      default:
        return {
          title: 'Lịch sử đơn đặt phòng đang trống',
          desc: 'Khám phá các khách sạn & homestay boutique được chọn lọc và đặt kỳ nghỉ đầu tiên của bạn ngay hôm nay.',
          showExplore: true,
        };
    }
  }, [tab]);

  const pageStart = (page - 1) * PAGE_SIZE;

  return (
    <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
      {/* Breadcrumb Navigation */}
      <div className="mb-4">
        <Link
          to="/customer"
          className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          <span>Quay lại Tổng quan</span>
        </Link>
      </div>

      {/* Header Section */}
      <section className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">
              ✦ Kỳ nghỉ của tôi
            </p>
            {isMockData && (
              <span className="rounded-full bg-amber-100/80 px-2 py-0.5 text-[10px] font-medium text-amber-800">
                Dữ liệu minh họa (Dev Mode)
              </span>
            )}
          </div>
          <h1 className="mt-1 font-display text-3xl font-bold leading-tight text-primary sm:text-4xl md:text-5xl">
            Đơn đặt phòng
          </h1>
          <p className="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
            Quản lý các chuyến đi sắp tới và xem lại lịch sử các kỳ nghỉ thanh lịch của bạn tại StayHub.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Link
            to="/customer/invoices"
            className="inline-flex items-center gap-2 rounded-xl border border-border/80 bg-white px-4 py-2.5 text-xs font-semibold text-ink shadow-xs transition-colors hover:border-primary/40 hover:text-primary"
          >
            <FileText className="h-3.5 w-3.5" />
            <span>Hóa đơn của tôi</span>
          </Link>
          <Link
            to="/search"
            className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary-mid"
          >
            <Compass className="h-4 w-4" />
            <span>Khám phá chỗ nghỉ</span>
          </Link>
        </div>
      </section>

      {/* Booking Statistics Row (Chỉ hiển thị khi có dữ liệu tổng hợp đáng tin cậy: Mock toàn bộ hoặc API aggregate stats) */}
      {(stats || allBookings.length > 0) && (
        <section className="mt-8">
          {isLoading && !stats && allBookings.length === 0 ? (
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="h-24 animate-pulse rounded-2xl bg-white/70" />
              ))}
            </div>
          ) : (
            <BookingStats stats={stats} bookings={allBookings} />
          )}
        </section>
      )}

      {/* Main Grid Content: Booking List (Left) + QuickPicks (Right) */}
      <section className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div className="min-w-0">
          {/* Tabs Filter + Sort Dropdown */}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {/* Tabs */}
            <div className="relative flex flex-wrap items-center gap-1 border-b border-border/60">
              {TABS.map((t) => {
                const active = tab === t.key;
                return (
                  <button
                    key={t.key}
                    type="button"
                    onClick={() => handleTabChange(t.key)}
                    className={`relative cursor-pointer px-4 py-3 text-[13px] transition-colors ${
                      active
                        ? 'font-bold text-primary'
                        : 'font-medium text-muted-foreground hover:text-ink'
                    }`}
                  >
                    <span>{t.label}</span>
                    {active && (
                      <motion.span
                        layoutId="booking-tab-underline"
                        className="absolute inset-x-3 -bottom-px h-[2px] rounded-full bg-primary"
                      />
                    )}
                  </button>
                );
              })}
            </div>

            {/* API chỉ hỗ trợ sắp xếp theo thời gian tạo; tùy chọn sort chỉ dành cho mock local. */}
            {isMockData && <div className="relative self-end sm:self-auto">
              <button
                type="button"
                onClick={() => setSortOpen((v) => !v)}
                className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border/70 bg-white px-3.5 py-2 text-xs font-medium text-ink shadow-xs transition-colors hover:border-primary/40"
              >
                <span className="text-muted-foreground">Sắp xếp:</span>
                <span className="font-semibold">{SORT_LABELS[sort] || sort}</span>
                <ChevronDown
                  className={`h-3.5 w-3.5 text-muted-foreground transition-transform ${
                    sortOpen ? 'rotate-180' : ''
                  }`}
                />
              </button>

              <AnimatePresence>
                {sortOpen && (
                  <motion.div
                    initial={{ opacity: 0, y: -4 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -4 }}
                    className="absolute right-0 top-full z-20 mt-2 w-64 overflow-hidden rounded-xl border border-border/70 bg-white p-1 shadow-lg"
                  >
                    {Object.keys(SORT_LABELS).map((k) => (
                      <button
                        key={k}
                        type="button"
                        onClick={() => handleSortChange(k)}
                        className={`block w-full cursor-pointer rounded-lg px-3 py-2 text-left text-xs transition-colors ${
                          sort === k
                            ? 'bg-primary/[0.08] font-bold text-primary'
                            : 'text-ink hover:bg-surface'
                        }`}
                      >
                        {SORT_LABELS[k]}
                      </button>
                    ))}
                  </motion.div>
                )}
              </AnimatePresence>
            </div>}
          </div>

          {/* Booking List Container */}
          <div className="mt-6 space-y-5">
            {/* Loading State */}
            {isLoading && (
              <div className="space-y-4">
                {Array.from({ length: 2 }).map((_, i) => (
                  <div key={i} className="h-56 animate-pulse rounded-2xl bg-white/70" />
                ))}
              </div>
            )}

            {/* Error State */}
            {!isLoading && errorMessage && (
              <div className="rounded-2xl border border-border/70 bg-white p-10 text-center shadow-xs">
                <p className="font-display text-lg font-bold text-primary">
                  Không thể kết nối danh sách đơn đặt phòng
                </p>
                <p className="mt-2 text-xs text-muted-foreground">{errorMessage}</p>
                <button
                  type="button"
                  onClick={handleRetry}
                  className="mt-5 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary-mid"
                >
                  <RefreshCw className="h-3.5 w-3.5" />
                  <span>Thử lại</span>
                </button>
              </div>
            )}

            {/* Empty State */}
            {!isLoading && !errorMessage && bookings.length === 0 && (
              <div className="rounded-2xl border border-border/60 bg-white p-12 text-center shadow-xs">
                <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface text-accent">
                  <Sparkles className="h-6 w-6" aria-hidden="true" />
                </div>
                <h3 className="mt-4 font-display text-2xl font-bold text-primary">
                  {emptyInfo.title}
                </h3>
                <p className="mx-auto mt-2 max-w-md text-xs leading-relaxed text-muted-foreground">
                  {emptyInfo.desc}
                </p>
                {emptyInfo.showExplore && (
                  <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <Link
                      to="/search"
                      className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid"
                    >
                      <Compass className="h-4 w-4" />
                      <span>Khám phá chỗ nghỉ ngay</span>
                    </Link>
                  </div>
                )}
              </div>
            )}

            {/* Cards List */}
            {!isLoading && !errorMessage && bookings.length > 0 && (
              <AnimatePresence mode="popLayout">
                {bookings.map((b, i) => (
                  <BookingCard key={b.id} booking={b} index={i} />
                ))}
              </AnimatePresence>
            )}
          </div>

          {/* Pagination Controls */}
          {!isLoading && !errorMessage && totalPages > 1 && (
            <div className="mt-10 flex flex-col items-center gap-3">
              <div className="inline-flex items-center gap-1 rounded-full border border-border/70 bg-white p-1 shadow-xs">
                <button
                  type="button"
                  disabled={page === 1}
                  onClick={() => handlePageChange(Math.max(1, page - 1))}
                  className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-primary/[0.08] hover:text-primary disabled:opacity-40 disabled:hover:bg-transparent"
                  aria-label="Trang trước"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>

                {Array.from({ length: totalPages }).map((_, i) => {
                  const n = i + 1;
                  const active = n === page;
                  return (
                    <button
                      key={n}
                      type="button"
                      onClick={() => handlePageChange(n)}
                      className={`min-w-9 cursor-pointer rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                        active
                          ? 'bg-primary text-primary-foreground shadow-xs'
                          : 'text-ink hover:bg-primary/[0.08] hover:text-primary'
                      }`}
                    >
                      {n}
                    </button>
                  );
                })}

                <button
                  type="button"
                  disabled={page === totalPages}
                  onClick={() => handlePageChange(Math.min(totalPages, page + 1))}
                  className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-primary/[0.08] hover:text-primary disabled:opacity-40 disabled:hover:bg-transparent"
                  aria-label="Trang sau"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>

              <p className="text-[11.5px] text-muted-foreground">
                Hiển thị {pageStart + 1}–{Math.min(pageStart + PAGE_SIZE, totalCount)} trong số{' '}
                {totalCount} đơn đặt phòng
              </p>
            </div>
          )}
        </div>

        {/* Right Aside: Quick Picks */}
        <QuickPicks />
      </section>
    </div>
  );
}
