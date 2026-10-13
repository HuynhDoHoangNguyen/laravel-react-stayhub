import { useState, useEffect, useCallback } from "react";
import {
  createGuest,
  deleteGuest,
  listGuests,
  updateGuest,
} from "@/lib/api/guests";

export function useGuests(q = {}) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  const fetchGuests = useCallback(async () => {
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await listGuests(q);
      setData(res);
    } catch {
      setIsError(true);
    } finally {
      setIsLoading(false);
    }
  }, [q.page, q.size, q.sort]);

  useEffect(() => {
    fetchGuests();
  }, [fetchGuests]);

  return {
    data,
    isLoading,
    isError,
    refetch: fetchGuests,
  };
}

export function useCreateGuest() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (body) => {
    setIsLoading(true);
    try {
      return await createGuest(body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useUpdateGuest() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async ({ id, body }) => {
    setIsLoading(true);
    try {
      return await updateGuest(id, body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useDeleteGuest() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (id) => {
    setIsLoading(true);
    try {
      return await deleteGuest(id);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}
