import { useState } from "react";
import { searchHotelsNL } from "@/lib/api/hotels";

export function useNLHotelSearch() {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  const [data, setData] = useState(null);

  const mutateAsync = async (body) => {
    setIsLoading(true);
    setError(null);
    try {
      const res = await searchHotelsNL(body);
      setData(res);
      return res;
    } catch (err) {
      setError(err);
      throw err;
    } finally {
      setIsLoading(false);
    }
  };

  return { mutateAsync, isLoading, error, data };
}
