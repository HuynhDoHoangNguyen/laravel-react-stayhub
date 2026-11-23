import { Hero } from "@/components/landing/Hero";
import { FeatureStrip } from "@/components/landing/FeatureStrip";
import { CuratedStays } from "@/components/landing/CuratedStays";
import { FinalCTA } from "@/components/landing/FinalCTA";
import StayHubIntro from "@/components/intro/StayHubIntro";

function HomePage() {
  return (
    <div className="bg-background text-foreground">
      <StayHubIntro />
      <Hero />
      <FeatureStrip />
      <CuratedStays />
      <FinalCTA />
    </div>
  );
}

export default HomePage;