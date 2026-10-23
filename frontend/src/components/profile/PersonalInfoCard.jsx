import {
  User,
  Mail,
  Phone,
  MapPin,
  Shield,
  CheckCircle2,
  Calendar,
  CheckCheck,
} from 'lucide-react';

function formatMemberSince(isoDate) {
  if (!isoDate) return 'Chưa cung cấp';
  try {
    const d = new Date(isoDate);
    if (isNaN(d.getTime())) return 'Chưa cung cấp';
    const month = d.getMonth() + 1;
    const year = d.getFullYear();
    return `Tháng ${month}, ${year}`;
  } catch {
    return 'Chưa cung cấp';
  }
}

/**
 * Component PersonalInfoCard hiển thị thông tin hồ sơ tài khoản thật từ /api/me:
 * Định dạng danh sách lưới 2 cột Quiet Luxury (StayNest PersonalInfoCard pattern).
 * Không tạo form chỉnh sửa giả lập khi Backend chưa có API cập nhật.
 */
export function PersonalInfoCard({ user }) {
  const roleLabel =
    user?.role === 'CUSTOMER'
      ? 'Khách hàng hội viên'
      : user?.role === 'HOST'
      ? 'Đối tác lưu trú (Host)'
      : user?.role === 'ADMIN'
      ? 'Quản trị viên (Admin)'
      : user?.role || 'Chưa cung cấp';

  const statusLabel =
    user?.status === 'ACTIVE'
      ? 'Đang hoạt động'
      : user?.status === 'BLOCKED'
      ? 'Bị tạm khóa'
      : user?.status === 'INACTIVE'
      ? 'Chưa kích hoạt'
      : user?.status || 'Chưa cung cấp';

  const emailVerifiedLabel = user?.email_verified_at
    ? 'Đã xác thực'
    : 'Chưa xác thực';

  const rows = [
    {
      icon: User,
      label: 'Họ và tên',
      value: user?.name || 'Chưa cung cấp',
    },
    {
      icon: Mail,
      label: 'Địa chỉ Email',
      value: user?.email || 'Chưa cung cấp',
    },
    {
      icon: Phone,
      label: 'Số điện thoại',
      value: user?.phone || 'Chưa cung cấp',
    },
    {
      icon: MapPin,
      label: 'Địa chỉ liên hệ',
      value: user?.address || 'Chưa cung cấp',
    },
    {
      icon: Shield,
      label: 'Vai trò tài khoản',
      value: roleLabel,
    },
    {
      icon: CheckCircle2,
      label: 'Trạng thái hoạt động',
      value: statusLabel,
    },
    {
      icon: CheckCheck,
      label: 'Trạng thái xác thực Email',
      value: emailVerifiedLabel,
    },
    {
      icon: Calendar,
      label: 'Thành viên từ',
      value: formatMemberSince(user?.created_at),
    },
  ];

  return (
    <div className="rounded-3xl border border-border/60 bg-white p-6 shadow-[0_20px_60px_-40px_rgba(17,26,19,0.35)] sm:p-8">
      {/* Header thẻ */}
      <div className="flex flex-col gap-3 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="grid h-10 w-10 place-items-center rounded-full bg-primary/[0.08] text-primary">
            <User className="h-4 w-4" />
          </div>
          <div>
            <h3 className="font-display text-xl font-bold text-primary sm:text-2xl">
              Thông tin tài khoản
            </h3>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Thông tin định danh được đồng bộ từ tài khoản xác thực StayHub.
            </p>
          </div>
        </div>

        <span className="inline-flex items-center gap-1.5 self-start rounded-full border border-border/70 bg-surface px-3 py-1 text-[11px] font-semibold text-muted-foreground">
          Dữ liệu định danh
        </span>
      </div>

      {/* Lưới định nghĩa 2 cột theo chuẩn StayNest */}
      <dl className="mt-2 grid grid-cols-1 divide-y divide-border/60 sm:grid-cols-2 sm:divide-y-0">
        {rows.map((row, i) => {
          const Icon = row.icon;
          const isLeft = i % 2 === 0;
          return (
            <div
              key={row.label}
              className={`flex items-start gap-4 py-5 ${
                isLeft ? 'sm:pr-8' : 'sm:border-l sm:border-border/60 sm:pl-8'
              } ${i >= 2 ? 'sm:border-t sm:border-border/60' : ''}`}
            >
              <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/[0.06] text-primary">
                <Icon className="h-4 w-4" />
              </div>
              <div className="min-w-0">
                <dt className="text-[11.5px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                  {row.label}
                </dt>
                <dd className="mt-1 font-display text-[16px] font-bold text-ink truncate">
                  {row.value}
                </dd>
              </div>
            </div>
          );
        })}
      </dl>

      {/* Ghi chú chân thẻ */}
      <div className="mt-4 rounded-2xl bg-surface/70 p-4 border border-border/60 text-xs text-muted-foreground leading-relaxed">
        Thông tin trên được sử dụng để lập hóa đơn điện tử và liên hệ đón tiếp khi đặt phòng. Mọi thay đổi dữ liệu hồ sơ được hỗ trợ qua các kênh chính thức của StayHub.
      </div>
    </div>
  );
}

export default PersonalInfoCard;
