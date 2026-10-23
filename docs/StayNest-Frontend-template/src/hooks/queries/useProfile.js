import { useState, useEffect, useCallback } from "react";
import { getProfile, updateProfile } from "@/lib/api/users";

export function useProfile() {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  const fetchProfile = useCallback(async () => {
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await getProfile();
      setData(res);
    } catch {
      setIsError(true);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProfile();
  }, [fetchProfile]);

  return { data, isLoading, isError, refetch: fetchProfile };
}

export function useUpdateProfile() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (body) => {
    setIsLoading(true);
    try {
      return await updateProfile(body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}
