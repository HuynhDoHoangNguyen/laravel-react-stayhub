import { useCallback, useEffect, useMemo, useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { searchRooms } from '@/services/searchService';
import { getApiErrorMessage } from '@/utils/getApiErrorMessage';
import ErrorState from '@/components/booking/shared/ErrorState';
import SearchEmptyState from '@/components/search/EmptyState';
import FilterSidebar from '@/components/search/FilterSidebar';
import ResultsGrid from '@/components/search/ResultsGrid';
import ResultsHeader from '@/components/search/ResultsHeader';
import SearchSummaryBar from '@/components/search/SearchSummaryBar';
import Pagination from '@/components/booking/shared/Pagination';

function dateString(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function dateOffset(days) {
  const date = new Date();
  date.setHours(0, 0, 0, 0);
  date.setDate(date.getDate() + days);
  return dateString(date);
}

function setParam(params, key, value) {
  params.delete(key);
  if (value !== '' && value !== undefined && value !== null) params.set(key, String(value));
}

export default function SearchResultPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

  const search = useMemo(() => ({
    check_in_date: searchParams.get('check_in_date') || '',
    check_out_date: searchParams.get('check_out_date') || '',
    guest_count: searchParams.get('guest_count') || '2',
    keyword: searchParams.get('keyword') || '',
    min_price: searchParams.get('min_price') || '',
    max_price: searchParams.get('max_price') || '',
    room_type_id: searchParams.get('room_type_id') || '',
    amenity_ids: searchParams.getAll('amenity_ids'),
    sort: searchParams.get('sort') || 'price_asc',
    page: Number(searchParams.get('page') || 1),
  }), [searchParams]);

  useEffect(() => {
    if (search.check_in_date && search.check_out_date) return;
    const next = new URLSearchParams(searchParams);
    if (!search.check_in_date) next.set('check_in_date', dateOffset(1));
    if (!search.check_out_date) next.set('check_out_date', dateOffset(3));
    setSearchParams(next, { replace: true });
  }, [search.check_in_date, search.check_out_date, searchParams, setSearchParams]);

  const performSearch = useCallback(async () => {
    if (!search.check_in_date || !search.check_out_date || Number(search.guest_count) < 1) return;
    setLoading(true);
    setError('');
    try {
      const params = {
        check_in_date: search.check_in_date,
        check_out_date: search.check_out_date,
        guest_count: Number(search.guest_count),
        page: search.page,
        per_page: 15,
        keyword: search.keyword || undefined,
        min_price: search.min_price || undefined,
        max_price: search.max_price || undefined,
        room_type_id: search.room_type_id || undefined,
        amenity_ids: search.amenity_ids.length ? search.amenity_ids : undefined,
        sort: search.sort,
      };
      setResult(await searchRooms(params));
    } catch (requestError) {
      setError(getApiErrorMessage(requestError).message);
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => {
    let active = true;
    Promise.resolve().then(() => active && performSearch());
    return () => { active = false; };
  }, [performSearch]);

  const updateParams = (updates, resetPage = true) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(updates).forEach(([key, value]) => setParam(next, key, value));
    if (resetPage) next.set('page', '1');
    setSearchParams(next);
  };

  const applyFilters = (filters) => {
    const next = new URLSearchParams(searchParams);
    ['min_price', 'max_price', 'room_type_id', 'sort'].forEach((key) => setParam(next, key, filters[key]));
    next.delete('amenity_ids');
    filters.amenity_ids.forEach((id) => next.append('amenity_ids', id));
    next.set('page', '1');
    setSearchParams(next);
    setMobileFiltersOpen(false);
  };

  const resetFilters = () => {
    const next = new URLSearchParams(searchParams);
    ['min_price', 'max_price', 'room_type_id', 'amenity_ids'].forEach((key) => next.delete(key));
    next.set('sort', 'price_asc');
    next.set('page', '1');
    setSearchParams(next);
  };

  const rooms = result?.data || [];
  const roomTypes = [...new Map(rooms.filter((room) => room.room_type?.id).map((room) => [room.room_type.id, room.room_type])).values()];
  const amenities = [...new Map(rooms.flatMap((room) => room.amenities || []).map((amenity) => [amenity.id, amenity])).values()];
  const maxAvailablePrice = rooms.reduce((max, room) => Math.max(max, Number(room.price_per_night) || 0), 0);
  const filterValue = {
    min_price: search.min_price,
    max_price: search.max_price,
    room_type_id: search.room_type_id,
    amenity_ids: search.amenity_ids,
    sort: search.sort,
  };

  return <main className="mx-auto min-h-[70vh] max-w-7xl space-y-8 px-4 py-8 text-left sm:px-6 lg:px-8">
    <header className="space-y-2">
      <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">StayHub · Tìm kiếm</p>
      <h1 className="text-3xl font-medium text-foreground sm:text-4xl">Tìm phòng phù hợp</h1>
      <p className="max-w-2xl text-sm text-muted-foreground">Chọn ngày lưu trú và khám phá phòng còn trống tại các cơ sở lưu trú.</p>
    </header>
    <SearchSummaryBar key={`${search.check_in_date}|${search.check_out_date}|${search.guest_count}|${search.keyword}`} value={{ check_in_date: search.check_in_date, check_out_date: search.check_out_date, guest_count: search.guest_count, keyword: search.keyword }} onSearch={(value) => updateParams(value)} loading={loading} />
    <div className="grid gap-6 lg:grid-cols-[270px_minmax(0,1fr)]">
      <div>
        <Button variant="outline" className="mb-3 w-full gap-2 lg:hidden" aria-expanded={mobileFiltersOpen} aria-controls="search-filters" onClick={() => setMobileFiltersOpen((open) => !open)}>
          <SlidersHorizontal className="size-4" aria-hidden="true" />{mobileFiltersOpen ? 'Ẩn bộ lọc' : 'Hiện bộ lọc'}
        </Button>
        <aside id="search-filters" className={mobileFiltersOpen ? 'block' : 'hidden lg:block'}>
          <FilterSidebar key={`${searchParams.toString()}|${maxAvailablePrice}`} value={filterValue} roomTypes={roomTypes} amenities={amenities} maxAvailablePrice={maxAvailablePrice} onApply={applyFilters} onReset={resetFilters} />
        </aside>
      </div>
      <section aria-live="polite" aria-busy={loading} className="min-w-0">
        <ResultsHeader total={result?.meta?.total || 0} page={result?.meta?.current_page || search.page} />
        {loading ? <ResultsGrid rooms={[]} loading bookingSearch={search} /> : null}
        {!loading && error ? <ErrorState title="Không thể tìm phòng" message={error} onRetry={performSearch} /> : null}
        {!loading && !error && rooms.length === 0 && result ? <SearchEmptyState /> : null}
        {!loading && !error && rooms.length > 0 ? <>
          <ResultsGrid rooms={rooms} loading={false} bookingSearch={search} />
          {result?.meta && <div className="mt-7"><Pagination currentPage={result.meta.current_page} lastPage={result.meta.last_page} total={result.meta.total} perPage={result.meta.per_page} onPageChange={(page) => updateParams({ page }, false)} /></div>}
        </> : null}
      </section>
    </div>
  </main>;
}
