import { useState, useEffect, useCallback, useMemo } from "react";
import {
  addToWishlist,
  getWishlist,
  removeFromWishlist,
} from "@/lib/api/wishlist";
import { useAuth } from "@/hooks/useAuth";

export function useWishlist(q = {}) {
  const { isAuthenticated } = useAuth();
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isError, setIsError] = useState(false);

  const fetchWishlist = useCallback(async () => {
    if (!isAuthenticated) return;
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await getWishlist(q);
      setData(res);
    } catch {
      setIsError(true);
    } finally {
      setIsLoading(false);
    }
  }, [isAuthenticated, q.page, q.size]);

  useEffect(() => {
    fetchWishlist();
  }, [fetchWishlist]);

  return { data, isLoading, isError, refetch: fetchWishlist };
}

export function useWishlistIds() {
  const { isAuthenticated } = useAuth();
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (!isAuthenticated) return;
    setIsLoading(true);
    getWishlist({ page: 0, size: 500 })
      .then(setData)
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [isAuthenticated]);

  const ids = useMemo(() => {
    const set = new Set();
    for (const h of data?.content ?? []) set.add(h.id);
    return set;
  }, [data]);

  return { ids, isLoading };
}

export function useToggleWishlist() {
  const { isAuthenticated } = useAuth();
  const [isLoading, setIsLoading] = useState(false);

  const mutateAsync = async ({ hotelId, next }) => {
    if (!isAuthenticated) throw new Error("Please sign in to save stays.");
    setIsLoading(true);
    try {
      if (next) await addToWishlist(hotelId);
      else await removeFromWishlist(hotelId);
      return { hotelId, next };
    } finally {
      setIsLoading(false);
    }
  };

  return { mutateAsync, isLoading };
}
