import { useState } from "react";
import { Loader2 } from "lucide-react";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { ProfileHeader } from "@/components/profile/ProfileHeader";
import { PersonalInfoCard } from "@/components/profile/PersonalInfoCard";
import { QuickActions } from "@/components/profile/QuickActions";
import { SavedGuestsPreview } from "@/components/profile/SavedGuestsPreview";
import { MyReviewsSection } from "@/components/profile/MyReviewsSection";
import { EditProfileDialog } from "@/components/profile/EditProfileDialog";
import { useProfile } from "@/hooks/queries/useProfile";
export default function ProfilePage() {
    const { data: user, isLoading, isError, refetch } = useProfile();
    const [editOpen, setEditOpen] = useState(false);
    return (<div className="min-h-screen bg-background">
      <Nav />

      <main className="mx-auto max-w-[1360px] px-4 pb-24 pt-28 sm:px-6 lg:px-10">
        {isLoading && (<div className="grid min-h-[60vh] place-items-center text-muted-foreground">
            <Loader2 className="h-6 w-6 animate-spin"/>
          </div>)}

        {isError && (<div className="mx-auto max-w-md rounded-2xl border border-border/60 bg-white p-8 text-center shadow-sm">
            <p className="text-[14px] text-ink">Unable to load your profile.</p>
            <button onClick={() => refetch()} className="mt-4 inline-flex items-center rounded-xl bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground hover:bg-primary-mid">
              Retry
            </button>
          </div>)}

        {user && (<>
            <ProfileHeader user={user}/>

            <section className="mt-12 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
              <div className="min-w-0 space-y-8">
                <PersonalInfoCard user={user} onEdit={() => setEditOpen(true)}/>
                <SavedGuestsPreview />
                <MyReviewsSection />
              </div>
              <QuickActions />
            </section>

            <EditProfileDialog open={editOpen} onOpenChange={setEditOpen} user={user}/>
          </>)}
      </main>

      <Footer />
    </div>);
}
