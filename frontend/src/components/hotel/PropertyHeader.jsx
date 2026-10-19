import { MapPin, Star, Phone, Mail, Clock } from "lucide-react";

export function PropertyHeader({ property }) {
  if (!property) return null;

  const address = property.address || property.city;
  const rating = Number(property.averageRating);
  const reviewCount = Number(property.reviewCount) || 0;
  const hasRating = !isNaN(rating) && rating > 0 && reviewCount > 0;

  return (
    <header className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <span className="text-[11.5px] font-medium uppercase tracking-[0.22em] text-primary/80">
              Không gian nghỉ dưỡng chọn lọc
            </span>
            {property.propertyType && (
              <span className="rounded-full bg-accent-pale/50 px-2.5 py-0.5 text-[11px] font-medium text-primary">
                {property.propertyType}
              </span>
            )}
          </div>

          <h1 className="mt-2 font-display text-3xl leading-tight text-primary md:text-[42px]">
            {property.name}
          </h1>

          <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-[13.5px] text-muted-foreground">
            {address && (
              <span className="inline-flex items-center gap-1.5">
                <MapPin className="h-3.5 w-3.5 text-primary/70" />
                {address}
              </span>
            )}

            {hasRating && (
              <span className="inline-flex items-center gap-1.5 text-ink">
                <Star className="h-3.5 w-3.5 fill-accent text-accent" />
                <span className="font-medium">{rating.toFixed(1)}</span>
                <span className="text-muted-foreground">
                  ({reviewCount.toLocaleString("vi-VN")} đánh giá)
                </span>
              </span>
            )}

            {(property.checkInTime || property.checkOutTime) && (
              <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                <Clock className="h-3.5 w-3.5 text-primary/70" />
                {property.checkInTime && `Nhận phòng ${property.checkInTime}`}
                {property.checkInTime && property.checkOutTime && " · "}
                {property.checkOutTime && `Trả phòng ${property.checkOutTime}`}
              </span>
            )}
          </div>
        </div>

        {/* Contact info (only rendered when provided by data source) */}
        {(property.phone || property.email) && (
          <div className="hidden shrink-0 flex-col items-end gap-1 text-[12.5px] text-muted-foreground md:flex">
            {property.phone && (
              <a
                href={`tel:${property.phone}`}
                className="inline-flex items-center gap-1.5 transition-colors hover:text-primary"
              >
                <Phone className="h-3 w-3" />
                {property.phone}
              </a>
            )}
            {property.email && (
              <a
                href={`mailto:${property.email}`}
                className="inline-flex items-center gap-1.5 transition-colors hover:text-primary"
              >
                <Mail className="h-3 w-3" />
                {property.email}
              </a>
            )}
          </div>
        )}
      </div>
    </header>
  );
}
