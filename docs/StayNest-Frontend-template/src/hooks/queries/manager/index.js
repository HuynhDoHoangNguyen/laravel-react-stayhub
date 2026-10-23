import { useState, useEffect, useCallback } from "react";
import {
  activateHotel,
  createHotel,
  createRoom,
  deleteHotel,
  deleteRoom,
  getHotelBookings,
  getHotelReport,
  getRefundPending,
  getRoomInventory,
  listHotels,
  listRooms,
  settleRefund,
  updateHotel,
  updateRoom,
  updateRoomInventory,
  uploadHotelPhoto,
  uploadRoomPhoto,
} from "@/lib/api/admin";
import { useAuth } from "@/hooks/useAuth";

function useIsManager() {
  const { session } = useAuth();
  return !!session?.roles?.includes("HOTEL_MANAGER");
}

export function useManagerHotels(q = {}) {
  const isManager = useIsManager();
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isError, setIsError] = useState(false);

  const fetchHotels = useCallback(async () => {
    if (!isManager) return;
    setIsLoading(true);
    setIsError(false);
    try {
      const res = await listHotels(q);
      setData(res);
    } catch {
      setIsError(true);
    } finally {
      setIsLoading(false);
    }
  }, [isManager, q.page, q.size]);

  useEffect(() => {
    fetchHotels();
  }, [fetchHotels]);

  return { data, isLoading, isError, refetch: fetchHotels };
}

export function useManagerHotel(hotelId) {
  const isManager = useIsManager();
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  const fetchHotel = useCallback(async () => {
    if (!isManager || !hotelId) return;
    setIsLoading(true);
    try {
      const page = await listHotels({ page: 0, size: 100 });
      setData(page.content.find((h) => h.id === hotelId) ?? null);
    } catch {
      setData(null);
    } finally {
      setIsLoading(false);
    }
  }, [isManager, hotelId]);

  useEffect(() => {
    fetchHotel();
  }, [fetchHotel]);

  return { data, isLoading, refetch: fetchHotel };
}

export function useCreateHotel() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (body) => {
    setIsLoading(true);
    try {
      return await createHotel(body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useUpdateHotel(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (body) => {
    setIsLoading(true);
    try {
      return await updateHotel(hotelId, body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useDeleteHotel() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (hotelId) => {
    setIsLoading(true);
    try {
      return await deleteHotel(hotelId);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useActivateHotel(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async () => {
    setIsLoading(true);
    try {
      return await activateHotel(hotelId);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useUploadHotelPhoto(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (file) => {
    setIsLoading(true);
    try {
      return await uploadHotelPhoto(hotelId, file);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useManagerRooms(hotelId) {
  const [data, setData] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  const fetchRooms = useCallback(async () => {
    if (!hotelId) return;
    setIsLoading(true);
    try {
      const res = await listRooms(hotelId);
      setData(res);
    } catch {
      setData([]);
    } finally {
      setIsLoading(false);
    }
  }, [hotelId]);

  useEffect(() => {
    fetchRooms();
  }, [fetchRooms]);

  return { data, isLoading, refetch: fetchRooms };
}

export function useCreateRoom(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (body) => {
    setIsLoading(true);
    try {
      return await createRoom(hotelId, body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useUpdateRoom(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async ({ roomId, body }) => {
    setIsLoading(true);
    try {
      return await updateRoom(hotelId, roomId, body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useDeleteRoom(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (roomId) => {
    setIsLoading(true);
    try {
      return await deleteRoom(hotelId, roomId);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useUploadRoomPhoto(hotelId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async ({ roomId, file }) => {
    setIsLoading(true);
    try {
      return await uploadRoomPhoto(hotelId, roomId, file);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useRoomInventory(roomId) {
  const [data, setData] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  const fetchInventory = useCallback(async () => {
    if (!roomId) return;
    setIsLoading(true);
    try {
      const res = await getRoomInventory(roomId);
      setData(res);
    } catch {
      setData([]);
    } finally {
      setIsLoading(false);
    }
  }, [roomId]);

  useEffect(() => {
    fetchInventory();
  }, [fetchInventory]);

  return { data, isLoading, refetch: fetchInventory };
}

export function useUpdateInventory(roomId) {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (body) => {
    setIsLoading(true);
    try {
      return await updateRoomInventory(roomId, body);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useManagerBookings(hotelId, q = {}) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  const fetchBookings = useCallback(async () => {
    if (!hotelId) return;
    setIsLoading(true);
    try {
      const res = await getHotelBookings(hotelId, q);
      setData(res);
    } catch {
      setData(null);
    } finally {
      setIsLoading(false);
    }
  }, [hotelId, q.page, q.size]);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

  return { data, isLoading, refetch: fetchBookings };
}

export function useRefundPending() {
  const isManager = useIsManager();
  const [data, setData] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  const fetchRefunds = useCallback(async () => {
    if (!isManager) return;
    setIsLoading(true);
    try {
      const res = await getRefundPending();
      setData(res);
    } catch {
      setData([]);
    } finally {
      setIsLoading(false);
    }
  }, [isManager]);

  useEffect(() => {
    fetchRefunds();
  }, [fetchRefunds]);

  return { data, isLoading, refetch: fetchRefunds };
}

export function useSettleRefund() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (bookingId) => {
    setIsLoading(true);
    try {
      return await settleRefund(bookingId);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}

export function useHotelReport(hotelId, q = {}) {
  const [data, setData] = useState(null);
  const [isLoading, setIsLoading] = useState(false);

  const fetchReport = useCallback(async () => {
    if (!hotelId) return;
    setIsLoading(true);
    try {
      const res = await getHotelReport(hotelId, q);
      setData(res);
    } catch {
      setData(null);
    } finally {
      setIsLoading(false);
    }
  }, [hotelId, q.startDate, q.endDate]);

  useEffect(() => {
    fetchReport();
  }, [fetchReport]);

  return { data, isLoading, refetch: fetchReport };
}
