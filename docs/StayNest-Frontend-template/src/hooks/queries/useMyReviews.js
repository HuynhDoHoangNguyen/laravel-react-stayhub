import { useState, useEffect, useCallback } from "react";
import { getMyReviews } from "@/lib/api/reviews";

export function useMyReviews(page = 0, size = 10) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  const fetchReviews = useCallback(async () => {
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await getMyReviews(page, size);
      setData(res);
    } catch {
      setIsError(true);
    } finally {
      setIsLoading(false);
    }
  }, [page, size]);

  useEffect(() => {
    fetchReviews();
  }, [fetchReviews]);

  return { data, isLoading, isError, refetch: fetchReviews };
}
