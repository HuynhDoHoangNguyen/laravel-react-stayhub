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
      minRating: searchParams.get("minRating")
        ? Number(searchParams.get("minRating"))
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
  const [selectedPropertyTypes, setSelectedPropertyTypes] = useState([]);

  const filters = useMemo(
    () => ({
      minPrice: search.minPrice,
      maxPrice: search.maxPrice,
      minRating: search.minRating,
      sortBy: search.sortBy,
      selectedAmenities,
      selectedPropertyTypes,
    }),
    [
      search.minPrice,
      search.maxPrice,
      search.minRating,
      search.sortBy,
      selectedAmenities,
      selectedPropertyTypes,
    ]
  );

  const setFilters = (next) => {
    if (next.selectedAmenities !== undefined) {
      setSelectedAmenities(next.selectedAmenities);
    }
    if (next.selectedPropertyTypes !== undefined) {
      setSelectedPropertyTypes(next.selectedPropertyTypes);
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

    if (next.minRating !== undefined) {
      params.set("minRating", String(next.minRating));
    } else {
      params.delete("minRating");
    }

    if (next.sortBy) {
      params.set("sortBy", next.sortBy);
    }

    setSearchParams(params, { replace: true });
  };

  const resetFilters = () => {
    setSelectedAmenities([]);
    setSelectedPropertyTypes([]);
    const params = new URLSearchParams(searchParams);
    params.delete("minPrice");
    params.delete("maxPrice");
    params.delete("minRating");
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
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);
  const [isUnavailable, setIsUnavailable] = useState(false);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    let active = true;

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
        minRating: filters.minRating,
        sortBy: filters.sortBy,
        amenities: filters.selectedAmenities,
        propertyTypes: filters.selectedPropertyTypes,
        keyword: search.q,
      })
      .then((res) => {
        if (active) {
          if (res.unavailable) {
            setIsUnavailable(true);
            setProperties([]);
          } else {
            setProperties(res.data || []);
          }
          setIsLoading(false);
        }
      })
      .catch(() => {
        if (active) {
          setIsError(true);
          setProperties([]);
          setIsLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [
    search.city,
    search.startDate,
    search.endDate,
    search.roomsCount,
    search.adults,
    search.children,
    search.q,
    filters.minPrice,
    filters.maxPrice,
    filters.minRating,
    filters.sortBy,
    filters.selectedAmenities,
    filters.selectedPropertyTypes,
    retryKey,
  ]);

  const PAGE_SIZE = 10;
  const currentPage = Math.max(1, Number(searchParams.get("page")) || 1);
  const total = properties.length;
  const totalPages = Math.ceil(total / PAGE_SIZE);
  const activePage = totalPages > 0 ? Math.min(currentPage, totalPages) : 1;
  const startIndex = (activePage - 1) * PAGE_SIZE;
  const paginatedProperties = properties.slice(
    startIndex,
    startIndex + PAGE_SIZE
  );

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
    setIsLoading(true);
    setIsError(false);
    setIsUnavailable(false);
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
                    message="Không thể tải danh sách chỗ nghỉ vào lúc này. Vui lòng kiểm tra lại đường truyền và thử lại."
                    onRetry={handleRetry}
                  />
                ) : isUnavailable ? (
                  <EmptyState
                    title="Tính năng chưa khả dụng"
                    message="Hệ thống đang hoàn thiện dịch vụ tìm kiếm phòng trực tuyến. Vui lòng quay lại sau."
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
