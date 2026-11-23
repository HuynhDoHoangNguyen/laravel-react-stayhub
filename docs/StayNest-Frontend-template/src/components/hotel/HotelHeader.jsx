import { MapPin, Star, Phone, Mail } from "lucide-react";
export function HotelHeader({ hotel }) {
    return (<header className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="min-w-0">
          <p className="text-[11.5px] font-medium uppercase tracking-[0.22em] text-primary/80">
            Curated luxury stay
          </p>
          <h1 className="mt-2 font-display text-3xl leading-tight text-primary md:text-[42px]">
            {hotel.name}
          </h1>
          <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-[13.5px] text-muted-foreground">
            <span className="inline-flex items-center gap-1.5">
              <MapPin className="h-3.5 w-3.5"/>
              {hotel.contactInfo?.address ?? hotel.city}
            </span>
            {hotel.reviewCount > 0 && (<span className="inline-flex items-center gap-1.5 text-ink">
                <Star className="h-3.5 w-3.5 fill-accent text-accent"/>
                <span className="font-medium">{hotel.averageRating.toFixed(1)}</span>
                <span className="text-muted-foreground">
                  ({hotel.reviewCount} reviews)
                </span>
              </span>)}
          </div>
        </div>
        <div className="hidden shrink-0 flex-col items-end gap-1 text-[12.5px] text-muted-foreground md:flex">
          {hotel.contactInfo?.phoneNumber && (<span className="inline-flex items-center gap-1.5">
              <Phone className="h-3 w-3"/>
              {hotel.contactInfo.phoneNumber}
            </span>)}
          {hotel.contactInfo?.email && (<span className="inline-flex items-center gap-1.5">
              <Mail className="h-3 w-3"/>
              {hotel.contactInfo.email}
            </span>)}
        </div>
      </div>
    </header>);
}
