import { useSearchParams } from "react-router-dom";
import { useEffect, useMemo, useState } from "react";
import { motion } from "framer-motion";
import { SearchSummaryBar } from "@/components/search/SearchSummaryBar";
import { FilterSidebar } from "@/components/search/FilterSidebar";
import { ResultsHeader } from "@/components/search/ResultsHeader";
import { ResultsGrid } from "@/components/search/ResultsGrid";
import { AssistantPanel } from "@/components/search/AssistantPanel";
import { EmptyState } from "@/components/search/EmptyState";
import { BenefitsStrip } from "@/components/search/BenefitsStrip";
import { Pagination } from "@/components/search/Pagination";
import { propertyService } from "@/services/propertyService";

export default function SearchResultsPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  // Search Context parsed from URL
  const search = useMemo(() => {
    return {
      city: searchParams.get("city") || "",
      startDate: searchParams.get("startDate") || "",
      endDate: searchParams.get("endDate") || "",
      roomsCount: Number(searchParams.get("roomsCount")) || 1,
      adults: Number(searchParams.get("adults")) || 2,
      children: Number(searchParams.get("children")) || 0,
      q: searchParams.get("q") || "",
      minPrice: searchParams.get("minPrice")
        ? Number(searchParams.get("minPrice"))
        : undefined,
      maxPrice: searchParams.get("maxPrice")
        ? Number(searchParams.get("maxPrice"))
        : undefined,
      sortBy: searchParams.get("sortBy") || "PRICE_ASC",
    };
  }, [searchParams]);

  // View state: grid or list
  const [view, setView] = useState(() => {
    try {
      return sessionStorage.getItem("stayhub_search_view") || "grid";
    } catch (e) {
      void e;
      return "grid";
    }
  });

  useEffect(() => {
    try {
      sessionStorage.setItem("stayhub_search_view", view);
    } catch (e) {
      void e;
    }
  }, [view]);

  // Filter state
  const [selectedAmenities, setSelectedAmenities] = useState([]);
  const filters = useMemo(
    () => ({
      minPrice: search.minPrice,
      maxPrice: search.maxPrice,
      sortBy: search.sortBy,
      selectedAmenities,
    }),
    [search.minPrice, search.maxPrice, search.sortBy, selectedAmenities]
  );

  const setFilters = (next) => {
    if (next.selectedAmenities !== undefined) {
      setSelectedAmenities(next.selectedAmenities);
    }
    const params = new URLSearchParams(searchParams);
    params.delete("page");
    if (next.minPrice !== undefined) {
      params.set("minPrice", String(next.minPrice));
    } else {
      params.delete("minPrice");
    }

    if (next.maxPrice !== undefined) {
      params.set("maxPrice", String(next.maxPrice));
    } else {
      params.delete("maxPrice");
    }

    if (next.sortBy) {
      params.set("sortBy", next.sortBy);
    }

    setSearchParams(params, { replace: true });
  };

  const resetFilters = () => {
    setSelectedAmenities([]);
    const params = new URLSearchParams(searchParams);
    params.delete("minPrice");
    params.delete("maxPrice");
    params.delete("q");
    params.delete("page");
    params.set("sortBy", "PRICE_ASC");
    setSearchParams(params, { replace: true });
  };

  const handleKeywordChange = (newQ) => {
    const params = new URLSearchParams(searchParams);
    params.delete("page");
    if (newQ) {
      params.set("q", newQ);
    } else {
      params.delete("q");
    }
    setSearchParams(params, { replace: true });
  };

  // Data fetching
  const [properties, setProperties] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [loadedKey, setLoadedKey] = useState(null);
  const [requestError, setRequestError] = useState(null);
  const [retryKey, setRetryKey] = useState(0);
  const currentPage = Math.max(1, Number(searchParams.get("page")) || 1);
  const isMissingDates = !search.startDate || !search.endDate;
  const requestKey = JSON.stringify({
    city: search.city,
    startDate: search.startDate,
    endDate: search.endDate,
    adults: search.adults,
    children: search.children,
    keyword: search.q,
    minPrice: filters.minPrice,
    maxPrice: filters.maxPrice,
    sortBy: filters.sortBy,
    amenities: filters.selectedAmenities,
    page: currentPage,
    retryKey,
  });
  const isLoading = !isMissingDates && loadedKey !== requestKey;
  const isError = requestError?.key === requestKey;
  const errorMessage = isError ? requestError.message : '';

  useEffect(() => {
    let active = true;

    if (isMissingDates) return undefined;

    propertyService
      .searchProperties({
        city: search.city,
        startDate: search.startDate,
        endDate: search.endDate,
        roomsCount: search.roomsCount,
        adults: search.adults,
        children: search.children,
        minPrice: filters.minPrice,
        maxPrice: filters.maxPrice,
        sortBy: filters.sortBy,
        amenities: filters.selectedAmenities,
        keyword: search.q,
        page: currentPage,
      })
      .then((res) => {
        if (active) {
          if (res.unavailable) {
            setProperties([]);
          } else {
            setProperties(res.data || []);
          }
          setPagination(res.meta || null);
          setLoadedKey(requestKey);
          setRequestError(null);
        }
      })
      .catch((error) => {
        if (active) {
          setProperties([]);
          setLoadedKey(requestKey);
          setRequestError({ key: requestKey, message: error.message || 'Không thể tải danh sách phòng.' });
        }
      });

    return () => {
      active = false;
    };
  }, [
    requestKey,
    isMissingDates,
    currentPage,
    search.city,
    search.startDate,
    search.endDate,
    search.roomsCount,
    search.adults,
    search.children,
    search.q,
    filters.minPrice,
    filters.maxPrice,
    filters.sortBy,
    filters.selectedAmenities,
  ]);

  const PAGE_SIZE = Number(pagination?.per_page) || 15;
  const total = Number(pagination?.total) || 0;
  const totalPages = Number(pagination?.last_page) || 1;
  const activePage = totalPages > 0 ? Math.min(currentPage, totalPages) : 1;
  const startIndex = (activePage - 1) * PAGE_SIZE;
  const paginatedProperties = properties;

  const from = total === 0 ? 0 : startIndex + 1;
  const to = Math.min(startIndex + PAGE_SIZE, total);

  const handlePageChange = (newPage) => {
    const params = new URLSearchParams(searchParams);
    if (newPage <= 1) {
      params.delete("page");
    } else {
      params.set("page", String(newPage));
    }
    setSearchParams(params);
    const el = document.getElementById("results-header");
    if (el) {
      el.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  };

  const handleRetry = () => {
    setRetryKey((k) => k + 1);
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Search Bar at Top */}
      <SearchSummaryBar
        city={search.city}
        startDate={search.startDate}
        endDate={search.endDate}
        roomsCount={search.roomsCount}
        adults={search.adults}
        children={search.children}
      />

      <main className="mx-auto max-w-[1440px] px-6 pb-16 pt-8 md:px-10">
        <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)_340px]">
          {/* Col 1: Filters */}
          <FilterSidebar
            value={filters}
            onChange={setFilters}
            onReset={resetFilters}
            availableProperties={properties}
          />

          {/* Col 2: Results List */}
          <section className="min-w-0 space-y-6">
            <div>
              <ResultsHeader
                total={total}
                city={search.city}
                from={from}
                to={to}
                sortBy={filters.sortBy}
                onSortChange={(s) => setFilters({ ...filters, sortBy: s })}
                view={view}
                onViewChange={setView}
              />

              <motion.div
                key={`grid-${total}-${view}-${activePage}`}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
              >
                {isError ? (
                  <EmptyState
                    title="Đã xảy ra lỗi kết nối"
                    message={errorMessage || "Không thể tải danh sách phòng vào lúc này. Vui lòng kiểm tra lại đường truyền và thử lại."}
                    onRetry={handleRetry}
                  />
                ) : isMissingDates ? (
                  <EmptyState
                    title="Chọn ngày lưu trú"
                    message="Vui lòng chọn ngày nhận phòng và trả phòng để tìm phòng còn trống."
                  />
                ) : !isLoading && total === 0 ? (
                  <EmptyState
                    title="Không tìm thấy chỗ nghỉ phù hợp"
                    message="Hãy thử nới rộng khoảng giá, giảm bớt tiện ích yêu cầu hoặc tìm kiếm tại điểm đến lân cận."
                    onRetry={resetFilters}
                  />
                ) : (
                  <>
                    <ResultsGrid
                      properties={paginatedProperties}
                      isLoading={isLoading}
                      view={view}
                      context={{
                        startDate: search.startDate,
                        endDate: search.endDate,
                        roomsCount: search.roomsCount,
                        adults: search.adults,
                        children: search.children,
                      }}
                    />
                    {!isLoading && totalPages > 1 && (
                      <Pagination
                        currentPage={activePage}
                        totalPages={totalPages}
                        onPageChange={handlePageChange}
                      />
                    )}
                  </>
                )}
              </motion.div>
            </div>
          </section>

          {/* Col 3: Assistant & Quick Refine Panel */}
          <AssistantPanel
            filters={filters}
            onFiltersChange={setFilters}
            keyword={search.q}
            onKeywordChange={handleKeywordChange}
          />
        </div>
      </main>

      <BenefitsStrip />
    </div>
  );
}
