import { useEffect, useState } from "react";

export function useImageRotation({ count, intervalMs = 1800, paused = false }) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (paused || count <= 1) return;
    const id = window.setInterval(() => {
      setIndex((i) => (i + 1) % count);
    }, intervalMs);
    return () => window.clearInterval(id);
  }, [paused, count, intervalMs]);

  useEffect(() => {
    setIndex(0);
  }, [count]);

  return index;
}
