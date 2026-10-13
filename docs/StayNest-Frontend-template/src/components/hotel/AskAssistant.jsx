import { useState } from "react";
import { Sparkles, Send, Loader2 } from "lucide-react";
import { askHotelQuestion } from "@/lib/api/hotels";
const SUGGESTIONS = [
    "Is the pool heated?",
    "How is breakfast?",
    "Is it family friendly?",
    "How quiet are the rooms?",
];
export function AskAssistant({ hotelId, hotelName }) {
    const [input, setInput] = useState("");
    const [turns, setTurns] = useState([]);
    const ask = async (question) => {
        const q = question.trim();
        if (!q)
            return;
        setInput("");
        setTurns((t) => [...t, { q, loading: true }]);
        try {
            const res = await askHotelQuestion(hotelId, q);
            setTurns((t) => t.map((turn, i) => i === t.length - 1 ? { q: turn.q, a: res.answer } : turn));
        }
        catch (e) {
            setTurns((t) => t.map((turn, i) => i === t.length - 1 ? { q: turn.q, error: "Couldn't reach the concierge. Try again." } : turn));
        }
    };
    return (<section className="rounded-2xl border border-primary/15 bg-gradient-to-br from-primary/5 via-background to-background p-6 md:p-8">
      <div className="flex items-start gap-3">
        <span className="grid h-10 w-10 place-items-center rounded-full bg-primary text-primary-foreground">
          <Sparkles className="h-4 w-4"/>
        </span>
        <div className="min-w-0">
          <h2 className="font-display text-xl text-primary md:text-2xl">Ask about {hotelName}</h2>
          <p className="mt-1 text-[13px] text-muted-foreground">
            Our concierge reads real guest reviews to answer your questions instantly.
          </p>
        </div>
      </div>

      {turns.length > 0 && (<div className="mt-5 space-y-3">
          {turns.map((t, i) => (<div key={i} className="space-y-2">
              <div className="ml-auto max-w-[85%] rounded-2xl rounded-tr-sm bg-primary px-4 py-2.5 text-[13.5px] text-primary-foreground">
                {t.q}
              </div>
              <div className="max-w-[85%] rounded-2xl rounded-tl-sm border border-border/70 bg-background px-4 py-2.5 text-[13.5px] text-ink">
                {t.loading ? (<span className="inline-flex items-center gap-2 text-muted-foreground">
                    <Loader2 className="h-3.5 w-3.5 animate-spin"/> Reading reviews…
                  </span>) : t.error ? (<span className="text-destructive">{t.error}</span>) : t.a}
              </div>
            </div>))}
        </div>)}

      {turns.length === 0 && (<div className="mt-5 flex flex-wrap gap-2">
          {SUGGESTIONS.map((s) => (<button key={s} type="button" onClick={() => ask(s)} className="rounded-full border border-border/70 bg-background px-3.5 py-1.5 text-[12.5px] text-ink transition hover:border-primary/40 hover:text-primary">
              {s}
            </button>))}
        </div>)}

      <form onSubmit={(e) => { e.preventDefault(); ask(input); }} className="mt-5 flex items-center gap-2 rounded-full border border-border bg-background px-2 py-1.5 focus-within:border-primary/50">
        <input value={input} onChange={(e) => setInput(e.target.value)} placeholder="Ask about the pool, breakfast, quiet, kids…" className="flex-1 bg-transparent px-3 py-1.5 text-[13.5px] text-ink outline-none placeholder:text-muted-foreground"/>
        <button type="submit" disabled={!input.trim()} aria-label="Send question" className="grid h-9 w-9 place-items-center rounded-full bg-primary text-primary-foreground transition hover:bg-primary-mid disabled:opacity-50">
          <Send className="h-4 w-4"/>
        </button>
      </form>
    </section>);
}
