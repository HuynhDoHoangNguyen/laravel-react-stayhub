import { Shield, KeyRound, Cookie, MailCheck, MailWarning } from 'lucide-react';

/**
 * Component AccountSecurityCard hiển thị các thông số bảo mật có căn cứ từ dữ liệu thật:
 * 1. Trạng thái xác thực Email (dựa trên email_verified_at).
 * 2. Cơ chế bảo vệ phiên đăng nhập (Laravel Sanctum Session Cookie).
 * 3. Trạng thái mã hóa mật khẩu và phân quyền tài khoản.
 * Tuyệt đối không chứa cam kết bảo mật tuyệt đối hoặc hotline giả.
 */
export function AccountSecurityCard({ user }) {
  const isEmailVerified = Boolean(user?.email_verified_at);

  return (
    <div className="rounded-3xl border border-border/60 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] sm:p-8">
      {/* Header */}
      <div className="flex items-center gap-3 border-b border-border/60 pb-5">
        <div className="grid h-10 w-10 place-items-center rounded-full bg-primary/[0.08] text-primary">
          <Shield className="h-4 w-4" />
        </div>
        <div>
          <h3 className="font-display text-xl font-bold text-primary sm:text-2xl">
            Bảo mật & Phiên làm việc
          </h3>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Thông tin về phương thức xác thực và trạng thái an toàn của tài khoản.
          </p>
        </div>
      </div>

      <div className="mt-5 space-y-4">
        {/* Email Verification Status */}
        <div className="flex items-start gap-3.5 rounded-2xl bg-surface/60 p-4 border border-border/60">
          <div
            className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${
              isEmailVerified
                ? 'bg-emerald-500/10 text-emerald-700'
                : 'bg-amber-500/10 text-amber-700'
            }`}
          >
            {isEmailVerified ? (
              <MailCheck className="h-4 w-4" />
            ) : (
              <MailWarning className="h-4 w-4" />
            )}
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-xs font-bold text-ink">
              {isEmailVerified ? 'Email đã được xác thực' : 'Email chưa xác thực'}
            </div>
            <p className="mt-0.5 text-[11.5px] text-muted-foreground">
              {isEmailVerified
                ? `Hòm thư điện tử ${user?.email} đã được xác nhận.`
                : `Hòm thư điện tử ${user?.email || ''} chưa hoàn tất thủ tục xác thực.`}
            </p>
          </div>
        </div>

        {/* Session Protection Mechanism */}
        <div className="flex items-start gap-3.5 rounded-2xl bg-surface/60 p-4 border border-border/60">
          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/[0.08] text-primary">
            <Cookie className="h-4 w-4" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-xs font-bold text-ink">Bảo vệ phiên đăng nhập</div>
            <p className="mt-0.5 text-[11.5px] text-muted-foreground">
              Phiên xác thực hiện tại được quản lý an toàn qua Cookie HttpOnly bởi Laravel Sanctum, phòng chống can thiệp script từ bên ngoài (XSS).
            </p>
          </div>
        </div>

        {/* Password Security */}
        <div className="flex items-start gap-3.5 rounded-2xl bg-surface/60 p-4 border border-border/60">
          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/[0.08] text-primary">
            <KeyRound className="h-4 w-4" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-xs font-bold text-ink">Mật khẩu tài khoản</div>
            <p className="mt-0.5 text-[11.5px] text-muted-foreground">
              Mật khẩu được lưu trữ dưới dạng băm một chiều (Hash) trên máy chủ StayHub. Mật khẩu không bao giờ được gửi về máy khách ở dạng rõ.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

export default AccountSecurityCard;
