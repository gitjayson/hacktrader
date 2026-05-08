# HackTrader — UI Review Brief

A briefing document for an external reviewer evaluating the effectiveness
of HackTrader's user interface and visual design. Read this before
opening the live site — it'll save you 15 minutes of reverse-engineering
the encoding system and let you focus the review on judgment, not
discovery.

---

## What HackTrader is

HackTrader is a market structure visualization tool for active traders.
The centerpiece is a **correlation radar** — a focus ticker at the center,
peer tickers plotted on concentric rings around it, where node *radius*
encodes live Pearson correlation strength and node *fill* encodes
directional bias. Surrounding surfaces (a levels ladder, channel bands,
volume context, an attempt-stress meter) describe what's currently
visible in price, level, and correlation data.

The product is for active traders who want to see basket structure
faster than they could parse it from a table or twelve charts.

## What HackTrader is *not*

It is explicitly **not a signal service** and does not claim predictive
power.

This is a deliberate stance arrived at the hard way. A walking-forward
backtest of the underlying scoring algorithm on real Massive (Polygon)
bars showed no edge after costs (~47% hit rate, ~−0.48% per trade on
daily; ~17% hit rate on 5-minute). Rather than continue to imply
predictive power, the product was reframed in v0.10.0 from "prediction
engine" to "visualization tool." The numbers, meters, and radar didn't
change; the claims they make about the future did. The disclaimer gate
states this explicitly.

This honesty stance is part of the product's identity. Reviewers should
evaluate the UI as a *visualization aid*, not as a signal generator.

## How to access the live site

Test login: `https://dev.hacktrader.com/test-login.php` — credentials in
the separate handoff. Click around for 10 minutes before reading the
rest of this doc; first impressions matter.

Then come back, read the encoding section below, and open the dashboard
again with that context. Note what changed in your read.

---

## The visual encoding system (the part most reviewers reverse-engineer)

### Correlation radar (centerpiece)

- **Focus node** in the center — the user's selected ticker. Shows
  symbol, current price, dominant direction (`↑ 74.5` style), and a
  basket verdict line (`9/12 ↑`). Fill is graded — green for up-leaning,
  red for down-leaning, alpha-scaled by lean strength (cap 0.85).
- **Indicator nodes** around it — correlated peer tickers. Same fill
  encoding (green/red, alpha-graded) but with a lower cap (0.6) so the
  focus stays visually dominant.
- **Node radius from center = correlation strength.** Closer to focus =
  stronger correlation. Concentric labeled rings at 0.5 / 0.7 / 0.9 make
  this readable.
- **Border solid = positive correlation; dashed = inverse.** A
  colorblind-friendly redundancy in case a user can't distinguish
  green-on-dashed from green-on-solid.
- Each indicator's mini-text shows symbol + dominant % + direction
  glyph. Hover tooltip surfaces price, full bias, correlation score.

### Microcharts row (under the radar)

Three side-by-side cards, each one number with a meter and subtext:

- **Directional pressure** — magnitude of the up-vs-down spread.
- **Next channel band** — where price would sit if the current
  resistance/support level breaks. Not a prediction — a chart-defined
  hypothetical.
- **Attempt stress** — how many failed probes (touches that didn't
  hold) of nearby levels have happened today.

### Levels ladder

Vertical column showing nearest resistance levels above the current
price and nearest support levels below, with the current price slotting
between them.

### Hero / focus header

A compact header above the radar: ticker, leaning chip
("↑ Up-leaning · medium alignment · Live · MASSIVE"), and a one-line
narrative summary ("9 of 12 indicators aligned · 3 failed upside probes
· 4 failed downside probes").

### Lite mode

A toggle in the topbar (and a `?lite=1` URL param) that hides
everything except the topbar, focus header, and radar — letting the
radar fill the viewport. Designed for distraction-free monitoring,
demos, embedding, and as the cleanest expression of the patent claim.

### Color semantics

- Green = up-leaning direction
- Red = down-leaning direction
- Cyan = system / interactive accents (active state, primary CTA)
- Blue = neutral / informational accents
- Amber = warnings or stale-data state
- Slate / muted blue-gray = inactive / secondary content

Bias colors (green/red) are reserved strictly for market direction.
System status (live / stale / error / cached) is encoded as a leading
dot color (cyan / amber / slate / blue) on the topbar status pill, so a
"stale-but-up" state reads as a green chip with an amber dot rather
than fighting itself.

---

## A realistic user workflow (so you can evaluate against the real task)

> 9:30 AM. User opens the dashboard, types `TSLA`, hits Scan. Sees the
> radar populate — TSLA glowing green at center (74.5% up-leaning),
> most peers tinted red (down). Notices SHOP is the lone green
> dissenter at 60% up. Hovers SHOP for the tooltip — confirms
> correlation score. Clicks SHOP to make it the focus. Watches the
> radar redraw with SHOP at center, TSLA now an indicator on the
> outside. Reads the new basket. Clicks back to TSLA, glances at the
> directional pressure microchart and the levels ladder, then makes
> a trading decision elsewhere (HackTrader doesn't take orders).

The product's value is in the *speed of read*: the radar should
communicate basket alignment, divergence, and directional pressure
faster than parsing twelve charts and a correlation table would.

---

## Specific evaluation questions

Don't just give me "is it good?" — please address these directly:

### Comprehension

1. A first-time user lands on the dashboard. What do they think they're
   looking at within 5 seconds? Within 30?
2. Does the radar's encoding read without explanation, or does it
   require the legend below?
3. Are color, position, and size each pulling weight, or is one
   dominating?

### Hierarchy

4. What's the strongest visual element on screen at first glance?
   Should it be?
5. Is anything competing for attention with the radar that shouldn't
   be? Or is the radar so dominant that other useful surfaces are
   ignored?
6. Is the topbar fighting for attention or quietly serving?

### Information density

7. Is the dashboard cluttered or balanced? Where would you cut, where
   would you give more room?
8. Is anything redundant — are there two ways the same information is
   being shown?

### Specific UI decisions

9. The Lite mode toggle — is it discoverable? Is the value proposition
   obvious before clicking?
10. The disclaimer gate — does it set the right expectation, or does
    it feel defensive / off-putting?
11. The graded fill on the indicator nodes (color encoding bias,
    alpha encoding strength) — does the alpha gradient communicate
    "stronger" intuitively, or does it just make some nodes feel
    "louder"?

### Accessibility

12. Color accessibility: green/red is the primary direction encoder.
    Does it work for the ~8% of users with deuteranopia? The dashed
    border on inverse correlation is a redundancy attempt — does it
    actually help?
13. Contrast ratios on the dark theme — any AA/AAA failures?
14. Keyboard navigation — does the dashboard work without a mouse?

### Responsiveness

15. Common laptop viewports (1440, 1366, 1280) — does the topbar fit
    cleanly at each? At narrower widths?
16. Mobile / tablet — is the layout usable, or is mobile abandoned?
    (Current intent is desktop-primary; advise if that's defensible.)

### Differentiation

17. Versus TradingView, ThinkOrSwim, Trading 212 — what's the
    differentiated visual idea here? Does it land? Is the correlation
    radar a strong enough centerpiece to anchor a product on?
18. The "visualization, not prediction" honesty stance — is it
    communicated through the UI, or only through the disclaimer? If
    we removed the disclaimer, would users still understand what
    they're looking at?

### Conversion

19. Pricing page (in `index.php`) — does it sell the value cleanly?
    Are the tiers (Free 5 tickers / Plus $29 25 tickers / Pro $99
    unlimited) clearly differentiated? Does the upgrade CTA feel
    inviting or pushy?
20. The signin flow (Google OAuth) — any friction?

---

## What's off-limits for redesign

To save us both time:

- **Single-page dashboard.** No multi-route SPA architecture.
- **Vanilla JS, no framework.** Don't suggest "introduce React."
- **PHP backend, self-hosted.** No rewrite recommendations.
- **Dark mode only.** Intentional brand choice.
- **The correlation radar concept is patent-pending.** You can suggest
  evolutions of *how it's drawn*, *what it encodes*, or *what
  surrounds it*. Please don't suggest replacing it with a heat grid,
  bar chart, or candlestick view as the centerpiece — that would
  weaken the claim.

## What's in scope for recommendations

- Layout, hierarchy, balance
- Typography, color usage, contrast
- Interaction details: hover, click, focus, transitions
- Information architecture within the dashboard
- Copy / microcopy / labels
- Empty / loading / error states
- Accessibility
- Onboarding / first-run experience
- Pricing page conversion
- Disclaimer / legal-gate UX
- Any visual concept that strengthens the radar's expressive power

## Deliverables I'm hoping for

A document with:

1. **First-impression read** — what you saw in the first 30 seconds
   without reading this brief.
2. **Strengths** — three to five things working well.
3. **Issues** — ranked by severity (blocker / major / nice-to-have),
   each with: what's wrong, why it matters, and a concrete suggestion.
4. **Direct answers to the evaluation questions above.**
5. **Optional: a sketch / mockup / annotated screenshot** for one or
   two of the highest-impact recommendations.

I'd rather have a short opinionated review than an exhaustive report.
Lean into your strongest reactions.

---

## Reference

- Live site: `https://dev.hacktrader.com`
- Test login: see handoff
- Codebase (read-only access if you want to grep selectors):
  `https://github.com/gitjayson/hacktrader`
- Patent claim summary: the novel IP is *focus node + peer indicator
  nodes plotted at radius proportional to live correlation magnitude,
  with node fill encoding directional bias by graded color*. Provisional
  filing pending.

Thanks for taking a look.
