import { useRef, useState } from 'react';
import { User, ShieldCheck, ShieldAlert, Camera, Loader2 } from 'lucide-react';

/**
 * Trích xuất chữ cái đầu viết tắt của tên người dùng theo quy chuẩn tiếng Việt.
 * Đồng bộ logic với AccountDropdown và CustomerLayout.
 */
function initialsOf(name) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return 'KH';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

/**
 * Component ProfileHeader hiển thị 3 khu vực theo chuẩn StayNest Quiet Luxury:
 * 1. Cột trái: Tagline, Tiêu đề hồ sơ, mô tả ngắn và thanh gạch nhấn hoàng gia.
 * 2. Cột giữa: Avatar khổ lớn tròn (h-32 w-32 md:h-36 md:w-36), đổ bóng đa lớp sâu, fallback viết tắt an toàn.
 * 3. Cột phải: Họ tên, Email và các huy hiệu định danh thực tế (Role, Account Status).
 */
export function ProfileHeader({ user, onAvatarSelected, avatarDisabled = false, avatarPending = false, avatarError }) {
  const [failedAvatar, setFailedAvatar] = useState(null);
  const fileInput = useRef(null);

  const roleLabel =
    user?.role === 'CUSTOMER'
      ? 'Khách hàng hội viên'
      : user?.role === 'HOST'
      ? 'Đối tác lưu trú (Host)'
      : user?.role === 'ADMIN'
      ? 'Quản trị viên (Admin)'
      : user?.role || 'Khách hàng';

  const isStatusActive = user?.status === 'ACTIVE';
  const statusLabel =
    user?.status === 'ACTIVE'
      ? 'Tài khoản đang hoạt động'
      : user?.status === 'BLOCKED'
      ? 'Tài khoản bị tạm khóa'
      : user?.status === 'INACTIVE'
      ? 'Tài khoản chưa kích hoạt'
      : user?.status || 'Chưa xác định';

  return (
    <section className="grid grid-cols-1 items-center gap-8 rounded-3xl border border-border/60 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] sm:p-8 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
      {/* Cột trái: Tiêu đề & Tagline */}
      <div className="min-w-0 text-center md:text-left">
        <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">
          ✦ Hồ sơ hội viên
        </p>
        <h1 className="mt-2 font-display text-3xl font-bold leading-tight text-primary sm:text-4xl lg:text-5xl">
          Hồ sơ cá nhân
        </h1>
        <p className="mt-2 text-[13.5px] leading-relaxed text-muted-foreground">
          Quản lý thông tin định danh và tùy chỉnh các tiện ích trải nghiệm lưu trú của bạn tại StayHub.
        </p>
        <span className="mt-4 inline-block h-[2.5px] w-14 rounded-full bg-accent" />
      </div>

      {/* Cột giữa: Avatar khổ lớn với hiệu ứng bóng đổ Quiet Luxury */}
      <div className="flex flex-col items-center">
        <div className="relative">
          <div className="grid h-32 w-32 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-primary via-primary-mid to-primary/80 text-primary-foreground shadow-[0_20px_50px_-20px_rgba(26,46,32,0.45)] ring-4 ring-background md:h-36 md:w-36">
            {user?.avatar && failedAvatar !== user.avatar ? (
              <img
                src={user.avatar}
                alt={user.name || 'Ảnh đại diện người dùng'}
                onError={() => setFailedAvatar(user.avatar)}
                className="h-full w-full object-cover"
              />
            ) : (
              <span className="font-display text-4xl font-bold tracking-wider">
                {initialsOf(user?.name)}
              </span>
            )}
          </div>
          {onAvatarSelected && <>
            <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" tabIndex={-1}
              aria-label="Chọn ảnh đại diện từ máy" disabled={avatarDisabled}
              onChange={(event) => { const file = event.target.files?.[0]; event.target.value = ''; if (file) onAvatarSelected(file); }} />
            <button type="button" title="Thay ảnh đại diện" aria-label="Thay ảnh đại diện" disabled={avatarDisabled}
              onClick={() => fileInput.current?.click()}
              className="absolute -bottom-1 -right-1 grid h-11 w-11 place-items-center rounded-full border-2 border-ink/70 bg-white text-ink shadow-md ring-2 ring-white transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/40 disabled:opacity-50">
              {avatarPending ? <Loader2 className="h-5 w-5 animate-spin" /> : <Camera className="h-5 w-5" />}
            </button>
          </>}
        </div>
        {avatarError && <p role="alert" className="mt-2 max-w-56 text-center text-xs text-destructive">{avatarError}</p>}
      </div>

      {/* Cột phải: Tên người dùng & Huy hiệu trạng thái */}
      <div className="min-w-0 text-center md:text-left">
        <h2 className="font-display text-2xl font-bold text-primary sm:text-3xl lg:text-4xl">
          {user?.name || 'Khách hàng StayHub'}
        </h2>
        <p className="mt-1 text-[13px] text-muted-foreground truncate">
          {user?.email || 'Chưa cung cấp email'}
        </p>
        <div className="mt-3.5 flex flex-wrap items-center justify-center gap-2 md:justify-start">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/[0.08] px-3 py-1 text-[12px] font-semibold text-primary">
            <User className="h-3.5 w-3.5" aria-hidden />
            <span>{roleLabel}</span>
          </span>

          <span
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-medium ${
              isStatusActive
                ? 'bg-emerald-500/10 text-emerald-700'
                : 'bg-amber-500/10 text-amber-700'
            }`}
          >
            {isStatusActive ? (
              <ShieldCheck className="h-3.5 w-3.5" aria-hidden />
            ) : (
              <ShieldAlert className="h-3.5 w-3.5" aria-hidden />
            )}
            <span>{statusLabel}</span>
          </span>
        </div>
      </div>
    </section>
  );
}

export default ProfileHeader;
