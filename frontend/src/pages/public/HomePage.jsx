import { Hero } from "@/components/landing/Hero";
import { FeatureStrip } from "@/components/landing/FeatureStrip";
import { CuratedStays } from "@/components/landing/CuratedStays";
import { FinalCTA } from "@/components/landing/FinalCTA";

function HomePage() {
  return (
    <div className="bg-background text-foreground">
      <Hero />
      <FeatureStrip />
      <CuratedStays />
      <FinalCTA />
    </div>
  );
}

export default HomePage;