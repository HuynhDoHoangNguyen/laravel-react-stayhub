import { useState, useEffect, useCallback } from 'react';

/**
 * Custom hook to execute an async function and return state.
 *
 * @param {() => Promise<any>} asyncFn
 * @param {boolean} [immediate=true]
 * @returns {{ data: any, loading: boolean, error: any, reload: () => Promise<void> }}
 */
export function useAsync(asyncFn, immediate = true) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(immediate);
    const [error, setError] = useState(null);

    const execute = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const result = await asyncFn();
            setData(result);
            return result;
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, [asyncFn]);

    useEffect(() => {
        let isMounted = true;
        if (immediate) {
            Promise.resolve().then(() => {
                if (isMounted) {
                    execute().catch(() => {});
                }
            });
        }
        return () => {
            isMounted = false;
        };
    }, [execute, immediate]);

    return { data, loading, error, reload: execute };
}
