import { useState, useEffect, useRef, useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ChevronRight } from 'lucide-react';

const INTRO_STORAGE_KEY = 'stayhub_intro_seen';

/**
 * Kiểm tra xem người dùng đã xem video intro trong phiên làm việc hiện tại hay chưa.
 *
 * @returns {boolean}
 */
function hasSeenIntro() {
    if (typeof window === 'undefined') return true;
    try {
        return sessionStorage.getItem(INTRO_STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
}

/**
 * Đánh dấu đã xem intro trong sessionStorage để không phát lại trong cùng phiên.
 */
function markIntroAsSeen() {
    if (typeof window === 'undefined') return;
    try {
        sessionStorage.setItem(INTRO_STORAGE_KEY, 'true');
    } catch {
        // Bỏ qua lỗi hạn chế lưu trữ của trình duyệt
    }
}

/**
 * Component Cinematic Welcome Video Intro cho StayHub (UI-CINEMATIC-01).
 *
 * - Phát toàn màn hình video giới thiệu thương hiệu StayHub trước khi vào Home.
 * - Chỉ phát một lần mỗi phiên truy cập (sessionStorage).
 * - Tự động fade-out sang trang chủ khi video kết thúc.
 * - Có nút "Bỏ qua" luôn nhìn thấy và hỗ trợ phím tắt Escape.
 * - Tôn trọng thiết lập prefers-reduced-motion và tự bỏ qua khi gặp lỗi tải video.
 *
 * @param {Object} props
 * @param {Function} [props.onFinish] Callback tùy chọn khi intro hoàn tất
 */
export default function StayHubIntro({ onFinish }) {
    const videoRef = useRef(null);

    // Xác định trạng thái ban đầu: chỉ hiển thị nếu chưa xem và không bật chế độ giảm chuyển động
    const [isVisible, setIsVisible] = useState(() => {
        if (typeof window === 'undefined') return false;

        // 1. Kiểm tra session storage
        if (hasSeenIntro()) return false;

        // 2. Kiểm tra prefers-reduced-motion
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            markIntroAsSeen();
            return false;
        }

        return true;
    });

    // Hàm đóng intro an toàn và mở khóa scroll
    const handleDismiss = useCallback(() => {
        markIntroAsSeen();
        setIsVisible(false);
        if (typeof document !== 'undefined') {
            document.body.style.overflow = '';
        }
        onFinish?.();
    }, [onFinish]);

    // Xử lý khóa scroll và bộ hẹn giờ an toàn phòng khi video bị treo
    useEffect(() => {
        if (!isVisible) return;

        // Khóa cuộn trang khi đang chiếu video intro
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // Bộ hẹn giờ an toàn tối đa 15 giây phòng trường hợp video không phát được
        const safetyTimer = setTimeout(() => {
            handleDismiss();
        }, 15000);

        // Hỗ trợ phím Escape để bỏ qua intro nhanh
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                handleDismiss();
            }
        };
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            clearTimeout(safetyTimer);
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [isVisible, handleDismiss]);

    // Tự động kích hoạt play khi component mount
    useEffect(() => {
        if (!isVisible || !videoRef.current) return;

        const playPromise = videoRef.current.play();
        if (playPromise !== undefined) {
            playPromise.catch(() => {
                // Nếu trình duyệt chặn autoplay dù đã muted, chuyển ngay vào Home
                handleDismiss();
            });
        }
    }, [isVisible, handleDismiss]);

    // Khi video kết thúc tự nhiên
    const handleVideoEnded = () => {
        handleDismiss();
    };

    // Khi video gặp lỗi không tải được hoặc đường dẫn hỏng
    const handleVideoError = () => {
        handleDismiss();
    };

    // Khi nạp xong metadata, điều chỉnh timer sát theo độ dài thật của video
    const handleLoadedMetadata = (e) => {
        const duration = e.target?.duration;
        if (duration && !isNaN(duration) && duration > 0) {
            // Đặt thời gian an toàn bằng độ dài video + 1.5s
            const dynamicSafety = setTimeout(() => {
                handleDismiss();
            }, Math.ceil((duration + 1.5) * 1000));

            return () => clearTimeout(dynamicSafety);
        }
    };

    if (!isVisible) {
        return null;
    }

    return (
        <AnimatePresence onExitComplete={onFinish}>
            {isVisible && (
                <motion.div
                    key="stayhub-cinematic-intro"
                    initial={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
                    className="fixed inset-0 z-[100] flex items-center justify-center bg-black overflow-hidden select-none"
                    aria-label="Video mở đầu giới thiệu thương hiệu StayHub"
                    role="region"
                >
                    {/* Video Player toàn màn hình, không viền đen */}
                    <video
                        ref={videoRef}
                        src="/videos/stayhub-intro.mp4"
                        autoPlay
                        muted
                        playsInline
                        preload="auto"
                        disablePictureInPicture
                        controls={false}
                        onEnded={handleVideoEnded}
                        onError={handleVideoError}
                        onLoadedMetadata={handleLoadedMetadata}
                        className="h-full w-full object-cover pointer-events-none"
                    />

                    {/* Nút "Bỏ qua" Quiet Luxury Glassmorphism */}
                    <div className="absolute top-6 right-6 sm:top-8 sm:right-8 z-10">
                        <button
                            type="button"
                            onClick={handleDismiss}
                            className="group inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-black/40 px-4 py-2 sm:px-5 sm:py-2.5 text-xs sm:text-sm font-medium text-white/90 shadow-2xl backdrop-blur-md transition-all duration-200 hover:border-white/40 hover:bg-black/60 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/50 cursor-pointer"
                            aria-label="Bỏ qua video giới thiệu StayHub"
                        >
                            <span>Bỏ qua</span>
                            <ChevronRight
                                className="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </button>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
