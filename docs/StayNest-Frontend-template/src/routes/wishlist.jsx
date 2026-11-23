import { Link } from "react-router-dom";
import { Heart, Loader2, Sparkles } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { Nav } from "@/components/landing/Nav";
import { Footer } from "@/components/landing/Footer";
import { SearchHotelCard } from "@/components/search/SearchHotelCard";
import { useWishlist } from "@/hooks/queries/useWishlist";
import { useAuth } from "@/hooks/useAuth";
export default function WishlistPage() {
    const { isAuthenticated } = useAuth();
    const { data, isLoading, isError, refetch } = useWishlist({
        page: 0,
        size: 24,
    });
    const hotels = data?.content ?? [];
    const total = data?.totalElements ?? hotels.length;
    return (<div className="min-h-screen bg-background">
      <Nav />

      <main className="mx-auto max-w-[1300px] px-4 pt-28 pb-24 sm:px-6 lg:px-10">
        <header className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-[11px] font-medium uppercase tracking-[0.28em] text-primary/70">
              Saved for later
            </p>
            <h1 className="mt-2 flex items-center gap-3 font-display text-4xl leading-tight text-primary md:text-5xl">
              <Heart className="h-8 w-8 fill-primary/10 text-primary" strokeWidth={1.6} aria-hidden/>
              My Wishlist
            </h1>
            <p className="mt-2 max-w-xl text-[14px] text-muted-foreground">
              {isAuthenticated
            ? total > 0
                ? `You have ${total} saved ${total === 1 ? "stay" : "stays"}. Tap a card to view details or book.`
                : "Start exploring and bookmark stays that inspire you."
            : "Sign in to see the stays you've bookmarked."}
            </p>
          </div>
          <Link to="/search" className="inline-flex items-center gap-2 self-start rounded-xl bg-primary px-5 py-3 text-[13px] font-medium text-primary-foreground shadow-sm transition-all hover:bg-primary-mid">
            <Sparkles className="h-4 w-4" aria-hidden/>
            Discover Stays
          </Link>
        </header>

        <section className="mt-10">
          {!isAuthenticated && (<EmptyShell title="Sign in to view your wishlist" body="Once signed in, every stay you bookmark shows up here." cta={{ to: "/auth", label: "Sign in" }}/>)}

          {isAuthenticated && isLoading && (<div className="grid place-items-center py-20 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin" aria-hidden/>
            </div>)}

          {isAuthenticated && isError && (<div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-8 text-center">
              <p className="text-[14px] text-ink">Unable to load your wishlist.</p>
              <button onClick={() => refetch()} className="mt-4 inline-flex items-center rounded-xl bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground hover:bg-primary-mid">
                Retry
              </button>
            </div>)}

          {isAuthenticated && !isLoading && !isError && hotels.length === 0 && (<EmptyShell title="Your wishlist is empty" body="Start exploring stays to save them! Tap the heart on any hotel card." cta={{ to: "/search", label: "Explore Stays" }}/>)}

          {isAuthenticated && !isLoading && !isError && hotels.length > 0 && (<div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              <AnimatePresence mode="popLayout">
                {hotels.map((hotel) => (<motion.div key={hotel.id} layout initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95 }} transition={{ duration: 0.25 }}>
                    <SearchHotelCard hotel={hotel}/>
                  </motion.div>))}
              </AnimatePresence>
            </div>)}
        </section>
      </main>

      <Footer />
    </div>);
}
function EmptyShell({ title, body, cta, }) {
    return (<div className="rounded-3xl border border-border/60 bg-white p-12 text-center">
      <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-primary/[0.06]">
        <Heart className="h-6 w-6 text-primary/70" strokeWidth={1.6} aria-hidden/>
      </div>
      <h3 className="mt-5 font-display text-2xl text-primary">{title}</h3>
      <p className="mx-auto mt-2 max-w-md text-[13.5px] text-muted-foreground">
        {body}
      </p>
      <Link to={cta.to} className="mt-6 inline-flex items-center rounded-xl bg-primary px-5 py-2.5 text-[12.5px] font-medium text-primary-foreground hover:bg-primary-mid">
        {cta.label}
      </Link>
    </div>);
}
