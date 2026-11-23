import { useMemo, useState } from "react";
import { BarChart3, Loader2 } from "lucide-react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis, } from "recharts";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useHotelReport } from "@/hooks/queries/manager";
function daysAgo(n) {
    const d = new Date();
    d.setDate(d.getDate() - n);
    return d.toISOString().slice(0, 10);
}
export function ReportsTab({ hotelId }) {
    const [startDate, setStartDate] = useState(daysAgo(30));
    const [endDate, setEndDate] = useState(daysAgo(0));
    const { data, isLoading, isError } = useHotelReport(hotelId, { startDate, endDate });
    const chartData = useMemo(() => data
        ? [
            {
                label: "Confirmed",
                value: data.totalConfirmedBookings,
            },
            {
                label: "Revenue",
                value: Number(data.totalRevenueOfConfirmedBookings.toFixed(2)),
            },
            {
                label: "Avg / booking",
                value: Number(data.avgRevenue.toFixed(2)),
            },
        ]
        : [], [data]);
    return (<div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-xl text-primary">Performance</h2>
          <p className="text-[13px] text-muted-foreground">
            Revenue and booking counts for the selected date range.
          </p>
        </div>
        <div className="flex gap-3">
          <div className="space-y-1">
            <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              Start
            </Label>
            <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="h-10 w-44 rounded-lg"/>
          </div>
          <div className="space-y-1">
            <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              End
            </Label>
            <Input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="h-10 w-44 rounded-lg"/>
          </div>
        </div>
      </div>

      {isLoading && (<div className="grid place-items-center py-16 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin"/>
        </div>)}

      {isError && (<div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-6 text-center">
          Unable to load report.
        </div>)}

      {!isLoading && !isError && data && (<>
          <div className="grid gap-4 md:grid-cols-3">
            <StatCard label="Confirmed bookings" value={data.totalConfirmedBookings.toString()}/>
            <StatCard label="Total revenue" value={`₹${data.totalRevenueOfConfirmedBookings.toFixed(2)}`}/>
            <StatCard label="Avg / booking" value={`₹${data.avgRevenue.toFixed(2)}`}/>
          </div>

          <div className="rounded-3xl border border-border/60 bg-white p-6">
            <div className="mb-4 flex items-center gap-2 text-[13px] text-muted-foreground">
              <BarChart3 className="h-4 w-4"/>
              Summary breakdown
            </div>
            <div className="h-64 w-full">
              <ResponsiveContainer>
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.06)"/>
                  <XAxis dataKey="label" stroke="#5c6960" fontSize={12}/>
                  <YAxis stroke="#5c6960" fontSize={12}/>
                  <Tooltip contentStyle={{
                borderRadius: 12,
                border: "1px solid rgba(0,0,0,0.08)",
            }}/>
                  <Bar dataKey="value" fill="var(--primary)" radius={[8, 8, 0, 0]}/>
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
        </>)}
    </div>);
}
function StatCard({ label, value }) {
    return (<div className="rounded-2xl border border-border/60 bg-white p-5">
      <div className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
        {label}
      </div>
      <div className="mt-1 font-display text-3xl text-primary">{value}</div>
    </div>);
}
