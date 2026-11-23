import { useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft, BarChart3, CalendarClock, CalendarDays, DoorOpen, Home, Loader2, Wallet, } from "lucide-react";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { useManagerHotel } from "@/hooks/queries/manager";
import { OverviewTab } from "@/components/manager/tabs/OverviewTab";
import { RoomsTab } from "@/components/manager/tabs/RoomsTab";
import { InventoryTab } from "@/components/manager/tabs/InventoryTab";
import { BookingsTab } from "@/components/manager/tabs/BookingsTab";
import { RefundsTab } from "@/components/manager/tabs/RefundsTab";
import { ReportsTab } from "@/components/manager/tabs/ReportsTab";
export default function HotelWorkspacePage() {
    const { hotelId } = useParams();
    const id = Number(hotelId);
    const [tab, setTab] = useState("overview");
    const { data: hotel, isLoading } = useManagerHotel(id);
    if (isLoading) {
        return (<div className="grid place-items-center py-24 text-muted-foreground">
        <Loader2 className="h-6 w-6 animate-spin"/>
      </div>);
    }
    return (<div className="mx-auto max-w-[1200px] space-y-6">
      <div>
        <Link to="/manage/hotels" className="inline-flex items-center gap-1.5 text-[12.5px] text-muted-foreground hover:text-primary">
          <ArrowLeft className="h-3.5 w-3.5"/>
          All hotels
        </Link>
        <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="font-display text-3xl text-primary">
              {hotel?.name ?? `Hotel #${hotelId}`}
            </h1>
            {hotel && (<p className="text-[13px] text-muted-foreground">
                {hotel.city} · {hotel.active ? "Live" : "Draft"} ·{" "}
                {hotel.reviewCount} reviews
              </p>)}
          </div>
        </div>
      </div>

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList className="flex h-auto w-full flex-wrap justify-start gap-1 rounded-2xl border border-border/60 bg-white p-1">
          <TabTrigger value="overview" icon={<Home className="h-4 w-4"/>} label="Overview"/>
          <TabTrigger value="rooms" icon={<DoorOpen className="h-4 w-4"/>} label="Rooms"/>
          <TabTrigger value="inventory" icon={<CalendarDays className="h-4 w-4"/>} label="Inventory"/>
          <TabTrigger value="bookings" icon={<CalendarClock className="h-4 w-4"/>} label="Bookings"/>
          <TabTrigger value="refunds" icon={<Wallet className="h-4 w-4"/>} label="Refunds"/>
          <TabTrigger value="reports" icon={<BarChart3 className="h-4 w-4"/>} label="Reports"/>
        </TabsList>

        <TabsContent value="overview" className="mt-6">
          <OverviewTab hotelId={id} hotel={hotel ?? null}/>
        </TabsContent>
        <TabsContent value="rooms" className="mt-6">
          <RoomsTab hotelId={id}/>
        </TabsContent>
        <TabsContent value="inventory" className="mt-6">
          <InventoryTab hotelId={id}/>
        </TabsContent>
        <TabsContent value="bookings" className="mt-6">
          <BookingsTab hotelId={id}/>
        </TabsContent>
        <TabsContent value="refunds" className="mt-6">
          <RefundsTab hotelId={id}/>
        </TabsContent>
        <TabsContent value="reports" className="mt-6">
          <ReportsTab hotelId={id}/>
        </TabsContent>
      </Tabs>
    </div>);
}
function TabTrigger({ value, icon, label, }) {
    return (<TabsTrigger value={value} className="flex items-center gap-2 rounded-xl px-4 py-2 text-[13px] data-[state=active]:bg-primary data-[state=active]:text-primary-foreground">
      {icon}
      {label}
    </TabsTrigger>);
}
