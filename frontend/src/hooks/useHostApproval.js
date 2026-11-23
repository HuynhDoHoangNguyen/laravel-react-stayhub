import { useEffect, useState } from 'react';
import api from '../api/axios';
import { useAuth } from './useAuth';

let cachedUserId = null;
let cachedApproval = null;
let inFlightPromise = null;

export function invalidateHostApprovalCache(userId = null) {
    if (userId === null || cachedUserId === userId) {
        cachedUserId = null;
        cachedApproval = null;
        inFlightPromise = null;
    }
}

/**
 * Hook xác định trạng thái duyệt của Host độc lập với email_verified_at.
 * Sử dụng trường is_pending_approval từ API Hợp đồng đối tác /api/host/partner-agreements/current.
 * Cách ly trạng thái duyệt theo đúng user.id hiện tại để tránh nhận nhầm trạng thái của tài khoản trước.
 */
export function useHostApproval() {
    const { user } = useAuth();
    const isHost = user?.role === 'HOST';
    const currentUserId = user?.id ?? null;

    const isCurrentCached = isHost && cachedUserId === currentUserId && cachedApproval !== null;

    const [status, setStatus] = useState(() => ({
        userId: currentUserId,
        isPendingHost: isCurrentCached ? cachedApproval : false,
        isLoading: isHost && !isCurrentCached,
    }));

    if (status.userId !== currentUserId) {
        setStatus({
            userId: currentUserId,
            isPendingHost: isCurrentCached ? cachedApproval : false,
            isLoading: isHost && !isCurrentCached,
        });
    }

    useEffect(() => {
        if (!isHost || !currentUserId || (cachedUserId === currentUserId && cachedApproval !== null)) {
            return;
        }

        let isMounted = true;
        cachedUserId = currentUserId;
        cachedApproval = null;
        inFlightPromise = api.get('/api/host/partner-agreements/current')
            .then((res) => {
                const isPending = Boolean(res.data?.data?.is_pending_approval);
                if (cachedUserId === currentUserId) {
                    cachedApproval = isPending;
                }
                return isPending;
            })
            .catch((err) => {
                const isPending = err?.response?.data?.code === 'HOST_APPROVAL_PENDING';
                if (cachedUserId === currentUserId) {
                    cachedApproval = isPending;
                }
                return isPending;
            })
            .finally(() => {
                inFlightPromise = null;
            });

        inFlightPromise.then((isPending) => {
            if (isMounted && cachedUserId === currentUserId) {
                setStatus({ userId: currentUserId, isPendingHost: isPending, isLoading: false });
            }
        });

        return () => {
            isMounted = false;
        };
    }, [isHost, currentUserId]);

    const isMatch = isHost && cachedUserId === currentUserId && status.userId === currentUserId;

    return {
        isPendingHost: isMatch ? status.isPendingHost : false,
        isLoading: isMatch ? status.isLoading : (isHost && !isCurrentCached),
    };
}
