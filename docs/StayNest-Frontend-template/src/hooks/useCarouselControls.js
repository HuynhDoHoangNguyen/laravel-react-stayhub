import { useCallback, useEffect, useRef, useState } from "react";

export function useCarouselControls({
  autoScrollSpeed = 18,
  resumeDelayMs = 5000,
} = {}) {
  const scrollerRef = useRef(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(true);
  const interactingRef = useRef(false);
  const resumeTimerRef = useRef(null);
  const rafRef = useRef(null);
  const lastTsRef = useRef(null);

  const updateEdges = useCallback(() => {
    const el = scrollerRef.current;
    if (!el) return;
    setCanScrollLeft(el.scrollLeft > 4);
    setCanScrollRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
  }, []);

  const pauseInteraction = useCallback(() => {
    interactingRef.current = true;
    if (resumeTimerRef.current) window.clearTimeout(resumeTimerRef.current);
    resumeTimerRef.current = window.setTimeout(() => {
      interactingRef.current = false;
    }, resumeDelayMs);
  }, [resumeDelayMs]);

  useEffect(() => {
    const el = scrollerRef.current;
    if (!el) return;

    const tick = (ts) => {
      if (lastTsRef.current == null) lastTsRef.current = ts;
      const dt = (ts - lastTsRef.current) / 1000;
      lastTsRef.current = ts;

      if (!interactingRef.current && !document.hidden) {
        const max = el.scrollWidth - el.clientWidth;
        if (max > 0) {
          const next = el.scrollLeft + autoScrollSpeed * dt;
          el.scrollLeft = next >= max ? 0 : next;
        }
      }
      rafRef.current = window.requestAnimationFrame(tick);
    };

    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (!reduce) rafRef.current = window.requestAnimationFrame(tick);

    return () => {
      if (rafRef.current) window.cancelAnimationFrame(rafRef.current);
      lastTsRef.current = null;
    };
  }, [autoScrollSpeed]);

  useEffect(() => {
    const el = scrollerRef.current;
    if (!el) return;
    const onWheel = (e) => {
      if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        el.scrollLeft += e.deltaY;
        pauseInteraction();
        e.preventDefault();
      }
    };
    el.addEventListener("wheel", onWheel, { passive: false });
    return () => el.removeEventListener("wheel", onWheel);
  }, [pauseInteraction]);

  useEffect(() => {
    const el = scrollerRef.current;
    if (!el) return;
    let isDown = false;
    let startX = 0;
    let startScroll = 0;
    let moved = false;

    const down = (e) => {
      if (e.target.closest("[data-wishlist-btn]")) return;
      isDown = true;
      moved = false;
      startX = e.clientX;
      startScroll = el.scrollLeft;
      pauseInteraction();
    };

    const move = (e) => {
      if (!isDown) return;
      const dx = e.clientX - startX;
      if (Math.abs(dx) > 5) {
        moved = true;
        el.scrollLeft = startScroll - dx;
      }
    };

    const up = () => {
      isDown = false;
    };

    el.addEventListener("pointerdown", down);
    window.addEventListener("pointermove", move);
    window.addEventListener("pointerup", up);
    window.addEventListener("pointercancel", up);
    return () => {
      el.removeEventListener("pointerdown", down);
      window.removeEventListener("pointermove", move);
      window.removeEventListener("pointerup", up);
      window.removeEventListener("pointercancel", up);
    };
  }, [pauseInteraction]);

  useEffect(() => {
    const el = scrollerRef.current;
    if (!el) return;
    updateEdges();
    const onScroll = () => updateEdges();
    el.addEventListener("scroll", onScroll, { passive: true });
    const ro = new ResizeObserver(updateEdges);
    ro.observe(el);
    return () => {
      el.removeEventListener("scroll", onScroll);
      ro.disconnect();
    };
  }, [updateEdges]);

  const scrollByAmount = useCallback(
    (amount) => {
      const el = scrollerRef.current;
      if (!el) return;
      pauseInteraction();
      el.scrollBy({ left: amount, behavior: "smooth" });
    },
    [pauseInteraction],
  );

  return {
    scrollerRef,
    canScrollLeft,
    canScrollRight,
    scrollByAmount,
    pauseInteraction,
  };
}
