import { useState, useEffect, useCallback } from "react";
import { apiGet } from "@/lib/api/client";

export function useCuratedHotels() {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  const fetchHotels = useCallback(async (signal) => {
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await apiGet("/hotels", { page: 0, size: 12, sort: "top_rated" }, { signal });
      setData(res);
    } catch (err) {
      if (err?.name !== "AbortError") {
        setIsError(true);
      }
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    fetchHotels(controller.signal);
    return () => controller.abort();
  }, [fetchHotels]);

  return {
    city: "",
    hotels: data?.content ?? [],
    isLoading,
    isError,
    refetch: () => fetchHotels(),
  };
}
