import { useEffect, useState } from 'react';
import api from '../api/axios';
import { useAuth } from './useAuth';

let cachedApproval = null;
let inFlightPromise = null;

export function invalidateHostApprovalCache() {
    cachedApproval = null;
    inFlightPromise = null;
}

/**
 * Hook xác định trạng thái duyệt của Host độc lập với email_verified_at.
 * Sử dụng trường is_pending_approval từ API Hợp đồng đối tác /api/host/partner-agreements/current.
 */
export function useHostApproval() {
    const { user } = useAuth();
    const isHost = user?.role === 'HOST';
    const [status, setStatus] = useState(() => ({
        isPendingHost: isHost && cachedApproval === true,
        isLoading: isHost && cachedApproval === null,
    }));

    useEffect(() => {
        if (!isHost || cachedApproval !== null) {
            return;
        }

        if (!inFlightPromise) {
            inFlightPromise = api.get('/api/host/partner-agreements/current')
                .then((res) => {
                    const isPending = Boolean(res.data?.data?.is_pending_approval);
                    cachedApproval = isPending;
                    return isPending;
                })
                .catch((err) => {
                    if (err?.response?.data?.code === 'HOST_APPROVAL_PENDING') {
                        cachedApproval = true;
                        return true;
                    }
                    cachedApproval = false;
                    return false;
                })
                .finally(() => {
                    inFlightPromise = null;
                });
        }

        let isMounted = true;
        inFlightPromise.then((isPending) => {
            if (isMounted) {
                setStatus({ isPendingHost: isPending, isLoading: false });
            }
        });

        return () => {
            isMounted = false;
        };
    }, [isHost, user?.id]);

    return {
        isPendingHost: isHost ? status.isPendingHost : false,
        isLoading: isHost ? status.isLoading : false,
    };
}
