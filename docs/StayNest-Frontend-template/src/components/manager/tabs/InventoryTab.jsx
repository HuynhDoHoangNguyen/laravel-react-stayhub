import { useEffect, useState } from "react";
import { CalendarDays } from "lucide-react";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, } from "@/components/ui/select";
import { InventoryCalendar } from "@/components/manager/InventoryCalendar";
import { useManagerRooms } from "@/hooks/queries/manager";
export function InventoryTab({ hotelId }) {
    const { data: rooms = [] } = useManagerRooms(hotelId);
    const [roomId, setRoomId] = useState(null);
    useEffect(() => {
        if (roomId == null && rooms.length > 0)
            setRoomId(rooms[0].id);
    }, [roomId, rooms]);
    if (rooms.length === 0) {
        return (<div className="rounded-3xl border border-dashed border-border bg-surface/40 p-12 text-center">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/[0.06]">
          <CalendarDays className="h-5 w-5 text-primary/70"/>
        </div>
        <h3 className="mt-4 font-display text-xl text-primary">
          Add a room type first
        </h3>
        <p className="mx-auto mt-1 max-w-md text-[13px] text-muted-foreground">
          Inventory calendars are generated automatically once you add rooms and
          activate the hotel.
        </p>
      </div>);
    }
    return (<div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-xl text-primary">Inventory calendar</h2>
          <p className="text-[13px] text-muted-foreground">
            Adjust surge pricing or block rooms for specific dates.
          </p>
        </div>
        <div className="w-full max-w-xs">
          <Select value={roomId ? String(roomId) : ""} onValueChange={(v) => setRoomId(Number(v))}>
            <SelectTrigger className="h-10 rounded-xl">
              <SelectValue placeholder="Choose a room"/>
            </SelectTrigger>
            <SelectContent>
              {rooms.map((r) => (<SelectItem key={r.id} value={String(r.id)}>
                  {r.type}
                </SelectItem>))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {roomId != null && <InventoryCalendar roomId={roomId}/>}
    </div>);
}
