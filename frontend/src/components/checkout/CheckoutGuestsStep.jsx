import { User, Phone, Mail, Users, AlertCircle, ArrowRight, ShieldCheck, Calendar } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

function getTodayIso() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function CheckoutGuestsStep({
  user,
  room,
  startDate,
  endDate,
  onDatesChange,
  nights = 1,
  isValidDateRange = true,
  adults,
  children,
  note,
  onAdultsChange,
  onChildrenChange,
  onNoteChange,
  onContinue,
}) {
  const maxCapacity = Number(room?.capacity) || 2;
  const totalGuests = (Number(adults) || 1) + (Number(children) || 0);
  const isOverCapacity = totalGuests > maxCapacity;

  const handleNext = (e) => {
    e.preventDefault();
    if (!isValidDateRange || isOverCapacity) {
      return;
    }
    onContinue();
  };

  return (
    <form onSubmit={handleNext} className="space-y-6">
      {/* 1. Primary Guest / Contact Information Card */}
      <Card className="rounded-3xl border-border/70 bg-white p-6 shadow-xs sm:p-8">
        <div className="flex items-center justify-between border-b border-border/60 pb-4">
          <div>
            <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
              ✦ Người đặt phòng
            </span>
            <h2 className="mt-0.5 font-display text-xl font-bold text-primary sm:text-2xl">
              Thông tin liên hệ
            </h2>
          </div>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/[0.08] px-3.5 py-1 text-[11px] font-semibold text-primary">
            <ShieldCheck className="h-3.5 w-3.5" />
            Tài khoản hội viên
          </span>
        </div>

        <div className="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
          {/* Full Name */}
          <div className="space-y-1.5">
            <label htmlFor="checkout-booker-name" className="text-xs font-semibold text-ink flex items-center gap-1.5">
              <User className="h-3.5 w-3.5 text-muted-foreground" />
              <span>Họ và tên người đặt</span>
            </label>
            <Input
              id="checkout-booker-name"
              type="text"
              readOnly
              value={user?.name || 'Khách hàng StayHub'}
              className="h-11 w-full rounded-xl border-border/70 bg-surface/60 text-xs font-medium text-ink/80 read-only:cursor-not-allowed"
            />
            <p className="text-[11px] text-muted-foreground">Lấy từ hồ sơ tài khoản đã xác thực</p>
          </div>

          {/* Email */}
          <div className="space-y-1.5">
            <label htmlFor="checkout-booker-email" className="text-xs font-semibold text-ink flex items-center gap-1.5">
              <Mail className="h-3.5 w-3.5 text-muted-foreground" />
              <span>Email nhận xác nhận</span>
            </label>
            <Input
              id="checkout-booker-email"
              type="email"
              readOnly
              value={user?.email || ''}
              className="h-11 w-full rounded-xl border-border/70 bg-surface/60 text-xs font-medium text-ink/80 read-only:cursor-not-allowed"
            />
            <p className="text-[11px] text-muted-foreground">Email đăng nhập trong hồ sơ tài khoản</p>
          </div>

          {/* Contact Phone from the account profile */}
          <div className="space-y-1.5 sm:col-span-2">
            <label htmlFor="checkout-booker-phone" className="text-xs font-semibold text-ink flex items-center gap-1.5">
              <Phone className="h-3.5 w-3.5 text-muted-foreground" />
              <span>Số điện thoại trong hồ sơ</span>
            </label>
            <Input
              id="checkout-booker-phone"
              type="tel"
              readOnly
              value={user?.phone || ''}
              placeholder="Chưa có số điện thoại trong hồ sơ"
              className="h-11 w-full rounded-xl border-border/70 bg-surface/60 text-xs font-medium text-ink/80"
            />
            <p className="text-[11px] text-muted-foreground">
              Thông tin này chỉ được đọc từ hồ sơ tài khoản; booking hiện không lưu số điện thoại riêng.
            </p>
          </div>
        </div>
      </Card>

      {/* 2. Stay Dates & Duration Card */}
      <Card className="rounded-3xl border-border/70 bg-white p-6 shadow-xs sm:p-8">
        <div className="flex items-center justify-between border-b border-border/60 pb-4">
          <div>
            <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
              ✦ Thời gian lưu trú
            </span>
            <h2 className="mt-0.5 font-display text-xl font-bold text-primary sm:text-2xl">
              Chọn ngày nhận &amp; trả phòng
            </h2>
          </div>
          {isValidDateRange && nights > 0 && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/[0.08] px-3.5 py-1 text-xs font-semibold text-primary">
              <Calendar className="h-3.5 w-3.5" />
              {nights} đêm lưu trú
            </span>
          )}
        </div>

        <div className="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
          {/* Check-in Date */}
          <div className="space-y-1.5">
            <label htmlFor="checkout-check-in-date" className="text-xs font-semibold text-ink flex items-center gap-1.5">
              <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
              <span>Ngày nhận phòng (Check-in)</span>
            </label>
            <Input
              id="checkout-check-in-date"
              type="date"
              min={getTodayIso()}
              value={startDate || ''}
              onChange={(e) => {
                const newCheckIn = e.target.value;
                let newCheckOut = endDate;
                if (newCheckIn && newCheckOut && newCheckOut <= newCheckIn) {
                  const nextDay = new Date(newCheckIn);
                  nextDay.setDate(nextDay.getDate() + 1);
                  const y = nextDay.getFullYear();
                  const m = String(nextDay.getMonth() + 1).padStart(2, '0');
                  const d = String(nextDay.getDate()).padStart(2, '0');
                  newCheckOut = `${y}-${m}-${d}`;
                }
                if (onDatesChange) {
                  onDatesChange(newCheckIn, newCheckOut);
                }
              }}
              className="h-11 w-full rounded-xl border-border/70 bg-surface/30 text-xs font-medium text-ink transition-colors focus:border-primary focus:bg-white"
            />
            <p className="text-[11px] text-muted-foreground">Giờ nhận phòng tiêu chuẩn từ 14:00</p>
          </div>

          {/* Check-out Date */}
          <div className="space-y-1.5">
            <label htmlFor="checkout-check-out-date" className="text-xs font-semibold text-ink flex items-center gap-1.5">
              <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
              <span>Ngày trả phòng (Check-out)</span>
            </label>
            <Input
              id="checkout-check-out-date"
              type="date"
              min={startDate || getTodayIso()}
              value={endDate || ''}
              onChange={(e) => {
                if (onDatesChange) {
                  onDatesChange(startDate, e.target.value);
                }
              }}
              className="h-11 w-full rounded-xl border-border/70 bg-surface/30 text-xs font-medium text-ink transition-colors focus:border-primary focus:bg-white"
            />
            <p className="text-[11px] text-muted-foreground">Giờ trả phòng tiêu chuẩn trước 12:00</p>
          </div>
        </div>

        {!isValidDateRange && (
          <div className="mt-4 rounded-xl bg-destructive/10 p-3.5 border border-destructive/20 text-xs text-destructive flex items-start gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
            <span>
              Vui lòng chọn ngày nhận phòng và ngày trả phòng hợp lệ (ngày trả phòng phải sau ngày nhận phòng ít nhất 1 đêm).
            </span>
          </div>
        )}
      </Card>

      {/* 3. Guest Count & Capacity Card */}
      <Card className="rounded-3xl border-border/70 bg-white p-6 shadow-xs sm:p-8">
        <div className="border-b border-border/60 pb-4">
          <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
            ✦ Quy mô lưu trú
          </span>
          <h2 className="mt-0.5 font-display text-xl font-bold text-primary sm:text-2xl">
            Số lượng khách lưu trú
          </h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Hạng phòng hiện tại cho phép tối đa <strong className="text-ink">{maxCapacity} khách</strong>. Mỗi đơn đặt áp dụng cho 1 phòng.
          </p>
        </div>

        <div className="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
          {/* Adults Counter */}
          <div className="flex items-center justify-between rounded-2xl border border-border/70 p-4 bg-surface/30">
            <div>
              <div className="text-xs font-bold text-ink">Người lớn</div>
              <div className="text-[11px] text-muted-foreground">Từ 13 tuổi trở lên</div>
            </div>
            <div className="flex items-center gap-2.5">
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Giảm số người lớn"
                disabled={adults <= 1}
                onClick={() => onAdultsChange(Math.max(1, adults - 1))}
                className="grid h-8 w-8 place-items-center rounded-full border border-border/80 bg-white text-xs font-semibold text-ink shadow-xs hover:border-primary/40 disabled:opacity-40"
              >
                –
              </Button>
              <span className="w-6 text-center text-xs font-bold text-ink">{adults}</span>
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Tăng số người lớn"
                disabled={totalGuests >= maxCapacity}
                onClick={() => onAdultsChange(adults + 1)}
                className="grid h-8 w-8 place-items-center rounded-full border border-border/80 bg-white text-xs font-semibold text-ink shadow-xs hover:border-primary/40 disabled:opacity-40"
              >
                +
              </Button>
            </div>
          </div>

          {/* Children Counter */}
          <div className="flex items-center justify-between rounded-2xl border border-border/70 p-4 bg-surface/30">
            <div>
              <div className="text-xs font-bold text-ink">Trẻ em</div>
              <div className="text-[11px] text-muted-foreground">Dưới 13 tuổi</div>
            </div>
            <div className="flex items-center gap-2.5">
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Giảm số trẻ em"
                disabled={children <= 0}
                onClick={() => onChildrenChange(Math.max(0, children - 1))}
                className="grid h-8 w-8 place-items-center rounded-full border border-border/80 bg-white text-xs font-semibold text-ink shadow-xs hover:border-primary/40 disabled:opacity-40"
              >
                –
              </Button>
              <span className="w-6 text-center text-xs font-bold text-ink">{children}</span>
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Tăng số trẻ em"
                disabled={totalGuests >= maxCapacity}
                onClick={() => onChildrenChange(children + 1)}
                className="grid h-8 w-8 place-items-center rounded-full border border-border/80 bg-white text-xs font-semibold text-ink shadow-xs hover:border-primary/40 disabled:opacity-40"
              >
                +
              </Button>
            </div>
          </div>
        </div>

        {/* Capacity Warning if exceeded */}
        {isOverCapacity && (
          <div className="mt-4 rounded-xl bg-destructive/10 p-3.5 border border-destructive/20 text-xs text-destructive flex items-start gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
            <span>
              Tổng số khách ({totalGuests} người) vượt quá sức chứa tối đa ({maxCapacity} người) của phòng này. Quý khách vui lòng giảm số lượng khách hoặc quay lại chọn hạng phòng có sức chứa lớn hơn.
            </span>
          </div>
        )}

        <div className="mt-4 flex items-center gap-2 text-[11.5px] text-muted-foreground">
          <Users className="h-3.5 w-3.5 text-primary/70 shrink-0" />
          <span>Tổng quy mô đoàn: <strong className="text-ink">{totalGuests} khách</strong> (1 phòng nghỉ)</span>
        </div>
      </Card>

      {/* 4. Special Requests Card */}
      <Card className="rounded-3xl border-border/70 bg-white p-6 shadow-xs sm:p-8">
        <div className="border-b border-border/60 pb-4">
          <span className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-accent">
            ✦ Yêu cầu đặc biệt
          </span>
          <h2 className="mt-0.5 font-display text-xl font-bold text-primary sm:text-2xl">
            Ghi chú cho cơ sở lưu trú
          </h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Tùy chọn. Cơ sở lưu trú sẽ xem xét sắp xếp tốt nhất theo khả năng thực tế của phòng.
          </p>
        </div>

        <div className="mt-5">
          <label htmlFor="checkout-special-note" className="sr-only">Ghi chú cho cơ sở lưu trú</label>
          <textarea
            id="checkout-special-note"
            rows={3}
            maxLength={300}
            value={note || ''}
            onChange={(e) => onNoteChange(e.target.value)}
            placeholder="Ví dụ: Dự kiến đến sau 15:00, mong muốn phòng tầng cao, giường đôi rộng rãi, không hút thuốc..."
            className="w-full rounded-2xl border border-border/80 bg-white p-4 text-xs text-ink placeholder:text-muted-foreground/60 transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/20"
          />
          <div className="mt-1.5 flex justify-between text-[11px] text-muted-foreground">
            <span>Yêu cầu sẽ được chuyển tới bộ phận lễ tân</span>
            <span>{(note || '').length}/300 ký tự</span>
          </div>
        </div>
      </Card>

      {/* Continue Action Button */}
      <div className="flex items-center justify-end pt-2">
        <Button
          type="submit"
          disabled={!isValidDateRange || isOverCapacity}
          className="h-11 rounded-xl px-6 text-xs font-semibold"
        >
          <span>Tiếp tục kiểm tra đơn</span>
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>
    </form>
  );
}

export default CheckoutGuestsStep;
