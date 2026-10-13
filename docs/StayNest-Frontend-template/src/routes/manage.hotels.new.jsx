import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { HotelProfileForm } from "@/components/manager/HotelProfileForm";
import { useCreateHotel } from "@/hooks/queries/manager";
export default function NewHotelPage() {
    const navigate = useNavigate();
    const createMut = useCreateHotel();
    return (<div className="mx-auto max-w-3xl space-y-6">
      <header>
        <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
          Step 1 · Hotel profile
        </p>
        <h1 className="mt-1 font-display text-3xl text-primary">Register a new hotel</h1>
        <p className="mt-1 text-[14px] text-muted-foreground">
          Set up your property's identity. You'll add photos, rooms, and activate it in
          the next steps.
        </p>
      </header>

      <div className="rounded-3xl border border-border/60 bg-white p-6 sm:p-8">
        <HotelProfileForm submitLabel="Create hotel" onCancel={() => navigate("/manage/hotels")} onSubmit={async (body) => {
            try {
                const hotel = await createMut.mutateAsync(body);
                toast.success("Hotel created. Add photos & rooms next.");
                navigate(`/manage/hotels/${hotel.id}`);
            }
            catch (err) {
                toast.error(err instanceof Error ? err.message : "Failed to create hotel");
            }
        }}/>
      </div>
    </div>);
}
