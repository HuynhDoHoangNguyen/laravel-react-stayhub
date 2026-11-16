import { useEffect, useState, useSyncExternalStore } from 'react';
import { createCatalogLoader } from '../services/catalog';

// Callers key the mounted view by page/ID, so a new identity starts with empty state.
export function useCatalogResource(load) {
    const [loader] = useState(() => createCatalogLoader(load));
    const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot);
    useEffect(() => { void loader.reload(); return () => loader.cancel(); }, [loader]);
    return { ...state, retry: loader.reload };
}
