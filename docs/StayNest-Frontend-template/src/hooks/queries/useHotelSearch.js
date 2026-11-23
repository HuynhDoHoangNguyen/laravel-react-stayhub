import { useState, useEffect } from "react";
import { searchHotels } from "@/lib/api/hotels";

export function useHotelSearch(params) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isError, setIsError] = useState(false);
  const [error, setError] = useState(null);

  const ready = Boolean(
    params?.city && params?.startDate && params?.endDate && params?.roomsCount
  );

  useEffect(() => {
    if (!ready) return;
    const controller = new AbortController();
    setIsLoading(true);
    setIsError(false);
    setError(null);

    searchHotels(params, { signal: controller.signal })
      .then((res) => {
        setData(res);
      })
      .catch((err) => {
        if (err?.name !== "AbortError") {
          setIsError(true);
          setError(err);
        }
      })
      .finally(() => {
        setIsLoading(false);
      });

    return () => controller.abort();
  }, [
    ready,
    params?.city,
    params?.startDate,
    params?.endDate,
    params?.roomsCount,
    params?.page,
    params?.size,
    params?.minPrice,
    params?.maxPrice,
    params?.minRating,
    params?.sortBy,
  ]);

  return { data, isLoading, isError, error, isFetching: isLoading };
}

export function useInfiniteHotelSearch(params) {
  const [data, setData] = useState({ pages: [], pageParams: [0] });
  const [isLoading, setIsLoading] = useState(false);
  const [isFetchingNextPage, setIsFetchingNextPage] = useState(false);
  const [isError, setIsError] = useState(false);
  const [error, setError] = useState(null);

  const ready = Boolean(
    params?.city && params?.startDate && params?.endDate && params?.roomsCount
  );

  useEffect(() => {
    if (!ready) return;
    const controller = new AbortController();
    setIsLoading(true);
    setIsError(false);

    searchHotels({ ...params, page: 0 }, { signal: controller.signal })
      .then((res) => {
        setData({ pages: [res], pageParams: [0] });
      })
      .catch((err) => {
        if (err?.name !== "AbortError") {
          setIsError(true);
          setError(err);
        }
      })
      .finally(() => {
        setIsLoading(false);
      });

    return () => controller.abort();
  }, [
    ready,
    params?.city,
    params?.startDate,
    params?.endDate,
    params?.roomsCount,
    params?.size,
    params?.minPrice,
    params?.maxPrice,
    params?.minRating,
    params?.sortBy,
  ]);

  const lastPage = data.pages[data.pages.length - 1];
  const hasNextPage = lastPage ? !lastPage.last : false;

  const fetchNextPage = async () => {
    if (!hasNextPage || isFetchingNextPage) return;
    setIsFetchingNextPage(true);
    const nextPage = lastPage.number + 1;
    try {
      const res = await searchHotels({ ...params, page: nextPage });
      setData((prev) => ({
        pages: [...prev.pages, res],
        pageParams: [...prev.pageParams, nextPage],
      }));
    } catch (err) {
      setIsError(true);
      setError(err);
    } finally {
      setIsFetchingNextPage(false);
    }
  };

  return {
    data,
    isLoading,
    isFetchingNextPage,
    hasNextPage,
    fetchNextPage,
    isError,
    error,
  };
}
