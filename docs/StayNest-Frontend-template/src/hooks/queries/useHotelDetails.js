import { useState, useEffect } from "react";
import {
  getHotelInfo,
  getHotelReviews,
  askHotelQuestion,
} from "@/lib/api/hotels";

export function useHotelInfo(hotelId, params) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  const enabled = Boolean(hotelId && params?.startDate && params?.endDate && params?.roomsCount);

  useEffect(() => {
    if (!enabled) return;
    let isCancelled = false;
    setIsLoading(true);
    setError(null);

    getHotelInfo(hotelId, params)
      .then((res) => {
        if (!isCancelled) setData(res);
      })
      .catch((err) => {
        if (!isCancelled) setError(err);
      })
      .finally(() => {
        if (!isCancelled) setIsLoading(false);
      });

    return () => {
      isCancelled = true;
    };
  }, [hotelId, params?.startDate, params?.endDate, params?.roomsCount, params?.minPrice, params?.maxPrice, enabled]);

  return { data, isLoading, error };
}

export function useHotelReviews(hotelId, page = 0, size = 6) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  const enabled = Boolean(hotelId);

  useEffect(() => {
    if (!enabled) return;
    let isCancelled = false;
    setIsLoading(true);
    setError(null);

    getHotelReviews(hotelId, page, size)
      .then((res) => {
        if (!isCancelled) setData(res);
      })
      .catch((err) => {
        if (!isCancelled) setError(err);
      })
      .finally(() => {
        if (!isCancelled) setIsLoading(false);
      });

    return () => {
      isCancelled = true;
    };
  }, [hotelId, page, size, enabled]);

  return { data, isLoading, error };
}

export function useAskHotel(hotelId) {
  return async (question) => askHotelQuestion(hotelId, question);
}
