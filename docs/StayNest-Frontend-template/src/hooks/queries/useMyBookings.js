import { useState, useEffect, useCallback } from "react";
import { getMyBookings } from "@/lib/api/bookings";

export function useMyBookings(q = {}) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  const fetchBookings = useCallback(async () => {
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await getMyBookings(q);
      setData(res);
    } catch {
      setIsError(true);
    } finally {
      setIsLoading(false);
    }
  }, [q.page, q.size]);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

  return { data, isLoading, isError, refetch: fetchBookings };
}
