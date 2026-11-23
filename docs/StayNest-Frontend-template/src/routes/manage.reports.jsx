import { useMemo, useState, useEffect } from "react";
import { BarChart3, Loader2, TrendingUp } from "lucide-react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useManagerHotels } from "@/hooks/queries/manager";
import { getHotelReport } from "@/lib/api/admin";

function daysAgo(n) {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return d.toISOString().slice(0, 10);
}

export default function ReportsPage() {
  const [startDate, setStartDate] = useState(daysAgo(30));
  const [endDate, setEndDate] = useState(daysAgo(0));
  const { data: page, isLoading: hotelsLoading } = useManagerHotels();
  const hotels = page?.content ?? [];

  const [reportsData, setReportsData] = useState([]);
  const [reportsLoading, setReportsLoading] = useState(false);

  useEffect(() => {
    if (hotels.length === 0) {
      setReportsData([]);
      return;
    }
    setReportsLoading(true);
    Promise.all(
      hotels.map((h) =>
        getHotelReport(h.id, { startDate, endDate }).catch(() => null)
      )
    )
      .then(setReportsData)
      .finally(() => setReportsLoading(false));
  }, [hotels, startDate, endDate]);

  const loading = hotelsLoading || reportsLoading;

  const perHotel = useMemo(
    () =>
      hotels.map((h, i) => {
        const rep = reportsData[i];
        return {
          name: h.name,
          bookings: rep?.totalConfirmedBookings ?? 0,
          revenue: Number((rep?.totalRevenueOfConfirmedBookings ?? 0).toFixed(2)),
          avg: Number((rep?.avgRevenue ?? 0).toFixed(2)),
        };
      }),
    [hotels, reportsData]
  );

  const totals = useMemo(
    () =>
      perHotel.reduce(
        (acc, h) => {
          acc.bookings += h.bookings;
          acc.revenue += h.revenue;
          return acc;
        },
        { bookings: 0, revenue: 0 }
      ),
    [perHotel]
  );

  const avg = totals.bookings ? totals.revenue / totals.bookings : 0;

  return (
    <div className="mx-auto max-w-[1200px] space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
            Manager · Analytics
          </p>
          <h1 className="mt-1 font-display text-3xl text-primary">Portfolio reports</h1>
          <p className="mt-1 text-[14px] text-muted-foreground">
            Aggregated performance across every property you manage.
          </p>
        </div>
        <div className="flex gap-3">
          <div className="space-y-1">
            <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              Start
            </Label>
            <Input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="h-10 w-44 rounded-lg"
            />
          </div>
          <div className="space-y-1">
            <Label className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
              End
            </Label>
            <Input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="h-10 w-44 rounded-lg"
            />
          </div>
        </div>
      </header>

      {loading && (
        <div className="grid place-items-center py-16 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin" />
        </div>
      )}

      {!loading && (
        <>
          <div className="grid gap-4 md:grid-cols-3">
            <Stat label="Confirmed bookings" value={totals.bookings.toString()} />
            <Stat label="Total revenue" value={`₹${totals.revenue.toFixed(2)}`} />
            <Stat label="Avg / booking" value={`₹${avg.toFixed(2)}`} />
          </div>

          <div className="rounded-3xl border border-border/60 bg-white p-6">
            <div className="mb-4 flex items-center gap-2 text-[13px] text-muted-foreground">
              <BarChart3 className="h-4 w-4" /> Revenue by property
            </div>
            <div className="h-72 w-full">
              <ResponsiveContainer>
                <BarChart data={perHotel}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.06)" />
                  <XAxis dataKey="name" stroke="#5c6960" fontSize={12} />
                  <YAxis stroke="#5c6960" fontSize={12} />
                  <Tooltip
                    contentStyle={{
                      borderRadius: 12,
                      border: "1px solid rgba(0,0,0,0.08)",
                    }}
                  />
                  <Bar dataKey="revenue" fill="var(--primary)" radius={[8, 8, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="rounded-3xl border border-border/60 bg-white p-6">
            <div className="mb-4 flex items-center gap-2 text-[13px] text-muted-foreground">
              <TrendingUp className="h-4 w-4" /> Per-property breakdown
            </div>
            <table className="w-full text-left text-[13.5px]">
              <thead className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                <tr>
                  <th className="py-2">Property</th>
                  <th className="py-2">Bookings</th>
                  <th className="py-2">Revenue</th>
                  <th className="py-2">Avg</th>
                </tr>
              </thead>
              <tbody>
                {perHotel.map((r) => (
                  <tr key={r.name} className="border-t border-border/60">
                    <td className="py-2.5 font-medium text-ink">{r.name}</td>
                    <td className="py-2.5">{r.bookings}</td>
                    <td className="py-2.5">₹{r.revenue.toFixed(2)}</td>
                    <td className="py-2.5">₹{r.avg.toFixed(2)}</td>
                  </tr>
                ))}
                {perHotel.length === 0 && (
                  <tr>
                    <td className="py-6 text-center text-muted-foreground" colSpan={4}>
                      No properties yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function Stat({ label, value }) {
  return (
    <div className="rounded-2xl border border-border/60 bg-white p-5">
      <div className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
        {label}
      </div>
      <div className="mt-1 font-display text-3xl text-primary">{value}</div>
    </div>
  );
}
