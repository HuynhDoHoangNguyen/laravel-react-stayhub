import { Link } from 'react-router-dom';
import { ArrowLeft, AlertCircle, RefreshCw } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { ProfileHeader } from '@/components/profile/ProfileHeader';
import { PersonalInfoCard } from '@/components/profile/PersonalInfoCard';
import { AccountSecurityCard } from '@/components/profile/AccountSecurityCard';
import { ProfileQuickActions } from '@/components/profile/ProfileQuickActions';

/**
 * Trang Hồ sơ cá nhân khách hàng (/customer/profile).
 * Kế thừa 100% ngôn ngữ thiết kế Quiet Luxury từ StayNest Profile (3-column Header + 2-column Body).
 * Đọc dữ liệu tài khoản thật từ useAuth() qua Laravel Sanctum session.
 * Không phát sinh gọi lặp /api/me và không tạo form chỉnh sửa giả lập.
 */
export default function CustomerProfilePage() {
  const { user, loading, error, refreshUser } = useAuth();

  // 1. Loading Skeleton State
  if (loading) {
    return (
      <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
        {/* Breadcrumb Skeleton */}
        <div className="h-4 w-40 animate-pulse rounded bg-surface" />

        {/* ProfileHeader Skeleton */}
        <div className="mt-6 grid grid-cols-1 items-center gap-8 rounded-3xl border border-border/60 bg-white p-6 shadow-xs sm:p-8 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
          <div className="space-y-3">
            <div className="h-3 w-28 animate-pulse rounded bg-surface" />
            <div className="h-8 w-48 animate-pulse rounded-xl bg-surface" />
            <div className="h-4 w-64 animate-pulse rounded bg-surface" />
          </div>
          <div className="flex justify-center">
            <div className="h-32 w-32 animate-pulse rounded-full bg-surface" />
          </div>
          <div className="space-y-3">
            <div className="h-7 w-40 animate-pulse rounded-xl bg-surface" />
            <div className="h-4 w-52 animate-pulse rounded bg-surface" />
            <div className="h-6 w-32 animate-pulse rounded-full bg-surface" />
          </div>
        </div>

        {/* 2-Column Body Skeleton */}
        <div className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
          <div className="space-y-8">
            <div className="h-80 animate-pulse rounded-3xl bg-white/80 border border-border/60" />
            <div className="h-60 animate-pulse rounded-3xl bg-white/80 border border-border/60" />
          </div>
          <div className="h-72 animate-pulse rounded-3xl bg-white/80 border border-border/60" />
        </div>
      </div>
    );
  }

  // 2. Error or Unauthenticated State
  if (error || !user) {
    return (
      <div className="mx-auto max-w-lg py-16 text-center">
        <div className="rounded-3xl border border-border/70 bg-white p-8 shadow-xs sm:p-12">
          <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-50 text-amber-700">
            <AlertCircle className="h-7 w-7" />
          </div>
          <p className="mt-6 text-[11px] font-semibold uppercase tracking-[0.24em] text-accent">
            ✦ Tài khoản hội viên
          </p>
          <h1 className="mt-2 font-display text-2xl font-bold text-primary sm:text-3xl">
            Không thể tải thông tin hồ sơ
          </h1>
          <p className="mx-auto mt-3 max-w-sm text-xs leading-relaxed text-muted-foreground">
            {error || 'Phiên làm việc đã hết hạn hoặc không thể kết nối đến máy chủ xác thực.'}
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <button
              type="button"
              onClick={() => refreshUser()}
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-mid transition-all"
            >
              <RefreshCw className="h-4 w-4" />
              <span>Thử lại</span>
            </button>
            <Link
              to="/login?role=customer"
              className="inline-flex items-center gap-2 rounded-xl border border-border/80 bg-white px-5 py-2.5 text-xs font-semibold text-ink hover:border-primary/40 transition-colors"
            >
              <span>Đăng nhập lại</span>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  // 3. Authenticated Profile View (StayNest Quiet Luxury Pattern)
  return (
    <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
      {/* Breadcrumb Navigation */}
      <div className="mb-6">
        <Link
          to="/customer"
          className="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground transition-colors hover:text-primary"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          <span>Quay lại Tổng quan tài khoản</span>
        </Link>
      </div>

      {/* 1. Profile Header (StayNest 3-Column Hero) */}
      <ProfileHeader user={user} />

      {/* 2. Main 2-Column Content Layout (minmax(0,1fr) + 360px Sidebar) */}
      <section className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
        {/* Cột trái: Thông tin tài khoản & Bảo mật phiên */}
        <div className="min-w-0 space-y-8">
          <PersonalInfoCard user={user} />
          <AccountSecurityCard user={user} />
        </div>

        {/* Cột phải: Lối tắt quản lý (Quick Actions) */}
        <div className="min-w-0 space-y-6">
          <ProfileQuickActions />
        </div>
      </section>
    </div>
  );
}
