import heroAsset from "@/assets/hero.png";
import { Nav } from "@/components/landing/Nav";
import { Hero } from "@/components/landing/Hero";
import { FeatureStrip } from "@/components/landing/FeatureStrip";
import { CuratedStays } from "@/components/landing/CuratedStays";
import { FinalCTA } from "@/components/landing/FinalCTA";
import { Footer } from "@/components/landing/Footer";
export default function LandingPage() {
    return (<main className="bg-background text-foreground">
      <Nav />
      <Hero />
      <FeatureStrip />
      <CuratedStays />
      <FinalCTA />
      <Footer />
    </main>);
}
