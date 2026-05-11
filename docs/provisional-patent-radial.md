# Provisional Patent Application — Draft

**Title:** Radial Visualization of Live Correlation Structure for a Focus Financial Instrument and Its Peer Basket

**Inventor:** Jayson Hawley
**Assignee (intended):** Penguins LLC, Ladera Ranch, California, USA
**Drafted:** May 2026
**Status:** Draft for attorney review — *not yet filed*. This document is intended as the substantive content for a USPTO provisional patent application (Form SB/16). Have it reviewed by a registered patent practitioner before filing.

---

## CROSS-REFERENCE TO RELATED APPLICATIONS

This application claims priority to no prior applications. Inventor reserves the right to claim priority to any application filed within twelve (12) months of the filing date of this provisional application.

## STATEMENT REGARDING FEDERALLY SPONSORED RESEARCH OR DEVELOPMENT

Not applicable.

## REFERENCE TO SEQUENCE LISTING, A TABLE, OR A COMPUTER PROGRAM LISTING APPENDIX

Not applicable. Source code embodying the invention is held by the assignee under copyright and trade secret protection and may be submitted as supporting material upon request.

---

## TECHNICAL FIELD

The present invention relates to the field of financial market data visualization, and more particularly to graphical user interfaces for displaying correlation structure between a chosen focus financial instrument and a plurality of peer instruments in real time.

## BACKGROUND OF THE INVENTION

### Problem Domain

Active traders evaluating a single financial instrument (the "focus instrument," typically an equity, ETF, futures contract, or cryptocurrency) commonly need to assess the *correlation structure* between that instrument and a basket of related instruments — for example, sector ETFs, peer equities, or macroeconomic proxies. Correlation structure refers both to the strength of statistical association between price series and to the directional bias each instrument is currently exhibiting. Together these two dimensions describe whether the focus instrument is moving "with the basket" (peers confirming) or "against the basket" (peers diverging), a distinction widely understood in technical and quantitative trading practice as significant context for decision-making.

### Prior Art and Its Limitations

Existing tools surface correlation structure principally through one of three patterns:

(a) **Tabular correlation matrices** (for example, charting platforms such as TradingView, Bloomberg Terminal, and ThinkOrSwim) display Pearson or rank-correlation coefficients in numerical grid form. These are accurate but slow to read; the user must scan many cells and translate numbers into mental positions. They also do not encode directional bias — only association strength.

(b) **Heatmap visualizations** (for example, market-map dashboards by Finviz, Sectorview, and similar tools) display a basket of instruments as colored rectangles whose tint encodes directional change over a fixed period. These convey direction effectively but do not encode each peer's *correlation* with a chosen focus instrument; they treat the basket as a flat universe rather than as a structured set of peers around a center.

(c) **Scatter plots and pair plots** (typical of statistical/quantitative platforms) display two instruments at a time on Cartesian axes. They communicate a single pairwise relationship clearly but do not scale to a multi-peer basket view, and they require the user to read multiple charts to build a basket-wide picture.

None of these prior-art patterns encodes, in a single visualization, *both* (i) the live correlation magnitude of each peer to a chosen focus, and (ii) the live directional bias of each peer, in a way that allows a viewer to read basket structure in seconds without numerical decoding. Furthermore, none distinguishes positive from inverse correlation through a non-color-dependent visual channel, leaving such tools effectively unusable for color-vision-deficient users for the most informational portion of their display.

### Need for the Present Invention

There exists a need for a visualization that, in a single compact view, simultaneously communicates: (i) the identity of a focus instrument; (ii) the live directional bias of the focus instrument and a quantified measure of that bias's magnitude; (iii) the identity of each of a plurality of peer instruments correlated to the focus instrument; (iv) the live magnitude of each peer's correlation with the focus instrument; (v) the sign of each peer's correlation (positive or inverse); (vi) the live directional bias of each peer; and (vii) an aggregate measure of how many peers are confirming the focus instrument's directional bias versus how many are diverging. Such a visualization should additionally be readable by users with red-green color vision deficiency.

The present invention satisfies these needs.

---

## SUMMARY OF THE INVENTION

The present invention is a graphical user interface ("GUI") for displaying the live correlation structure between a chosen focus financial instrument and a plurality of peer financial instruments, comprising:

- a **focus node** rendered at or near the geometric center of a display region, the focus node containing identifying text for the focus instrument and visual encoding of the focus instrument's live directional bias and bias magnitude;

- a plurality of **peer indicator nodes** rendered around the focus node, each peer indicator node corresponding to a respective peer financial instrument and being positioned at an angular position around the focus node and at a *radial distance* from the focus node that is determined as a function of the live correlation magnitude between that peer instrument and the focus instrument;

- visual encoding of each peer node's live directional bias by means of a *graded fill color*, the color hue indicating direction (one hue for up-leaning, a different hue for down-leaning) and the fill opacity or saturation indicating the magnitude of the lean; and

- visual encoding of each peer node's correlation sign (positive or inverse) by means of a *non-color border attribute* such as solid border versus dashed border, providing redundancy against color vision deficiency.

In a preferred embodiment, the focus node further displays a *basket verdict* — an aggregate ratio summarizing how many peer nodes are confirming the focus instrument's directional bias versus how many are diverging.

In a further preferred embodiment, each peer indicator node and the focus node display a *direction glyph* (such as ▲, ▼, or ▪) of geometric shape redundantly encoding the directional bias, providing further accessibility against color vision deficiency.

In a further embodiment, the visualization may be rendered in a "lite" mode in which supporting elements are hidden and the radial visualization fills the available viewport, providing a distraction-free distillation of the focus instrument's basket structure.

The invention enables a viewer to apprehend the live structural disposition of a focus instrument and its peer basket within seconds, in a manner not achievable by prior-art tabular, heatmap, or scatter-plot visualizations.

---

## BRIEF DESCRIPTION OF THE DRAWINGS

**FIG. 1** is an annotated screenshot of a representative embodiment of the radial correlation visualization, showing a focus instrument (NVDA) at center with a plurality of correlated peer instruments arrayed at varying radial distances and exhibiting varying graded fills.

**FIG. 2** is a schematic diagram identifying each visual encoding channel of FIG. 1 and the data dimension it represents.

**FIG. 3** is a flowchart of the data pipeline and rendering loop producing the visualization of FIG. 1, beginning with retrieval of price series from a market-data source and ending with the rendered display.

**FIG. 4** is a representative embodiment of the visualization in "lite" mode, in which the radial visualization fills the available viewport.

**FIG. 5** illustrates a representative basket-verdict display ("9/12 ↑") within the focus node, summarizing peer-confirmation aggregate.

**FIG. 6** is a representative embodiment showing the direction-glyph encoding (▲, ▼, ▪) inside each peer node and the focus node.

*(Note for filing: the four figures referenced above should be supplied as labeled image attachments to the provisional application. See the assignee's `docs/ui-screenshots/` directory for source images suitable for figure preparation.)*

---

## DETAILED DESCRIPTION OF THE INVENTION

### Overview

The invention is a graphical user interface for visualizing live correlation structure surrounding a chosen focus financial instrument. The interface is rendered on a display device and updated in real time as new price data arrives.

The invention may be embodied in any computing system capable of rendering a two-dimensional graphical display, including but not limited to web browsers receiving HTML, CSS, and JavaScript content; native desktop applications; mobile applications; or terminal-resident charting platforms. The preferred embodiment is web-based, using HTML5 Canvas, SVG, or DOM-positioned elements within a browser window.

### The Focus Node

The visualization is anchored by a single **focus node** rendered at or near the geometric center of a designated display region (the "radar stage"). The focus node represents a single financial instrument selected by the user — referred to as the *focus instrument*. The focus node displays:

- an **identifier** for the focus instrument (for example, the equity ticker symbol "NVDA" or "SPY");
- a **current price** of the focus instrument;
- a **direction indicator** comprising at least a numerical magnitude representing the strength of the focus instrument's current directional bias; and
- a **basket verdict** comprising an aggregate count of how many peer indicator nodes are confirming the focus instrument's directional bias versus the total number of peer nodes (for example, "9/12 ↑").

The focus node is rendered with a graded fill, the hue of the fill indicating the direction of the bias (a first hue, by way of non-limiting example green, for up-leaning bias; a second hue, by way of non-limiting example red, for down-leaning bias; and an absence of color or a third hue for neutral bias), and the *opacity* of the fill indicating the *magnitude* of the bias according to a function of the form:

```
alpha = pow(magnitude / 100, K) * cap_focus
```

where `magnitude` is the dominant directional probability score (in the range 0–100), `K` is a power-curve exponent in the range approximately 1.0 to 1.5 (preferred value 1.3) that biases the visual response toward the high-magnitude end of the scale, and `cap_focus` is a maximum opacity coefficient in the range approximately 0.7 to 1.0 (preferred value 0.85) chosen so that the focus node, when strongly leaning, is the most visually saturated element of the display.

### The Peer Indicator Nodes

Surrounding the focus node, a plurality of **peer indicator nodes** are rendered, each representing a respective peer financial instrument selected as correlated with the focus instrument. The peer instruments may be selected by any suitable means including but not limited to: a curated correlation basket stored in a configuration file; a sector-fallback heuristic mapping focus instruments to thematic peer sets; or computed-correlation rankings based on historical price series.

In a preferred embodiment, twelve peer indicator nodes are rendered around the focus node. The number of peer nodes may be greater or fewer in alternative embodiments without departing from the scope of the invention.

### Radial Position Encoding

The defining novelty of the invention is the encoding of each peer's *correlation strength* with the focus instrument as the *radial distance* of the peer indicator node from the focus node. Peer nodes whose live Pearson correlation coefficient (or analogous statistical measure) with the focus instrument is high in magnitude are rendered closer to the focus node; peer nodes with weaker correlation are rendered farther away.

In a preferred embodiment, the radial position is computed as:

```
radius = max_radius - (correlation_magnitude * (max_radius - min_radius))
```

where `correlation_magnitude` is the absolute value of the live Pearson correlation coefficient (in the range 0–1), `min_radius` is the smallest permissible distance from the focus node center (preventing peer nodes from overlapping the focus node), and `max_radius` is the largest permissible distance (the outer ring of the visualization).

The angular position of each peer node around the focus node may be assigned by any suitable scheme including: a fixed ordering of peer slots; a dynamic placement minimizing visual collisions; or a clock-face ordering corresponding to a domain-meaningful sequence. The preferred embodiment uses a fixed even-distribution scheme: peer nodes are placed at uniformly spaced angular intervals (e.g., 360 degrees divided by the number of peers) starting from a reference angle (e.g., 12 o'clock, with the first peer at the top).

In a preferred embodiment, **concentric ring guides** are rendered as faintly visible circles at radial distances corresponding to specific correlation thresholds (for example, at correlation values of 0.5, 0.7, and 0.9). These guides assist the viewer in reading the correlation magnitude from radial position without requiring numerical disclosure.

### Directional Bias Encoding

Each peer indicator node, like the focus node, is rendered with a graded fill encoding its own directional bias. The hue convention is consistent across the visualization: a first hue (e.g., green) for up-leaning peers, a second hue (e.g., red) for down-leaning peers, and a neutral or absent fill for peers without a clearly determined direction.

The fill opacity for peer nodes is computed by a function similar to that for the focus node, but with a smaller maximum opacity coefficient `cap_peer` in the range approximately 0.4 to 0.7 (preferred value 0.6), so that even strongly-leaning peer nodes do not exceed the visual prominence of the focus node. This intentional asymmetry ensures the focus node remains the center of visual attention regardless of basket activity.

### Correlation Sign Encoding

In financial markets, two instruments can be either *positively correlated* (they tend to move in the same direction) or *negatively/inversely correlated* (they tend to move in opposite directions). To encode this distinction without additional reliance on color, each peer indicator node is rendered with a **border style** that indicates its correlation sign:

- A **solid border** indicates positive correlation;
- A **dashed border** indicates inverse (negative) correlation.

This encoding is critical to color-vision-deficient accessibility, as it allows users to distinguish "this peer is up-leaning AND positively correlated" from "this peer is up-leaning AND inversely correlated" — semantically meaningful but otherwise indistinguishable distinctions — by border shape rather than color alone.

### Direction Glyph Encoding

Within each peer indicator node, and within the focus node, a **direction glyph** is rendered alongside any numerical text. The glyph takes one of three geometric forms:

- An upward-pointing triangle (▲) for up-leaning bias;
- A downward-pointing triangle (▼) for down-leaning bias;
- A small filled square (▪) for neutral bias.

The glyph is colored according to the same direction-encoding hue convention. However, because the glyph's *shape* alone unambiguously encodes the directional bias, the encoding remains readable to users with red-green color vision deficiency, providing a third redundant accessibility channel (alongside fill hue and border style).

### Basket Verdict

The focus node displays an **aggregate basket verdict** in the form `N/M ↑` or `N/M ↓` (or analogous format), where:

- `M` is the total number of peer indicator nodes in the visualization;
- `N` is the count of peer nodes whose directional bias *confirms* the focus instrument's directional bias (taking correlation sign into account: a positively-correlated peer leaning the same direction as the focus is confirming; an inversely-correlated peer leaning the *opposite* direction as the focus is *also* confirming, since inverse correlation predicts opposite movement);
- The arrow indicates the focus instrument's own directional bias.

The basket verdict provides at-a-glance summary of "how aligned is the basket with the focus right now," which is the principal analytical question this invention is designed to answer.

### Connecting Lines

In a preferred embodiment, **connecting lines** are rendered from the focus node center to each peer indicator node. The line color encodes the live agreement state:

- A first color (e.g., green) when the peer is confirming the focus's bias;
- A second color (e.g., red) when the peer is diverging from the focus's bias;
- A neutral color when the peer's bias does not pass a configured tolerance threshold.

These lines reinforce the basket-verdict aggregate visually: a viewer can see at a glance how many lines are green versus red, which corresponds to the numerical verdict in the focus node.

### Hover/Tooltip Disclosure

Because the peer nodes are visually compact, additional details for each peer are surfaced via mouse hover or equivalent pointer interaction. The tooltip discloses, at minimum: the peer's symbol; its current price; its full directional bias text; its precise correlation coefficient with the focus instrument; and its up-bias and down-bias percentages.

### Lite Mode

In an alternative rendering embodiment ("lite mode"), all peripheral display elements (such as supporting microcharts, levels ladders, intel cards, navigation chrome) are hidden via display style suppression, and the radial visualization is allowed to fill the available viewport. This alternative rendering is invoked by a user toggle or by a URL parameter, and its preferences may be persisted in browser local storage. Lite mode constitutes a pure embodiment of the invention's central encoding system, suitable for embedding, demonstration, and distraction-free monitoring.

### Animated Repositioning

When the underlying market data refreshes (typically on intervals of 30 seconds to 5 minutes depending on the user's selected period), peer indicator nodes are repositioned to reflect updated correlation values. The repositioning is animated using a smooth transition (preferred easing function: a cubic-bezier of approximately `(0.22, 1, 0.36, 1)`, transition duration approximately 450 milliseconds), producing a "swimming" visual effect as nodes move radially in response to evolving correlation structure. This animated transition reinforces the encoding's temporal dimension: the user perceives correlation strength as a *living* quantity rather than a static snapshot.

### Data Pipeline

The visualization is fed by a data pipeline comprising:

1. Retrieval of recent price bars for the focus instrument and each peer instrument from a market-data source;
2. Computation of directional-bias scores for the focus and each peer (which may use any suitable technical-analysis methodology, the specific methodology not being a part of this invention);
3. Computation of live Pearson correlation coefficients between the focus and each peer over a recent window;
4. Determination of correlation sign (positive/inverse) for each peer;
5. Submission of (1)–(4) as a structured data payload to the rendering engine;
6. Rendering of the visualization per the encoding rules described above.

### Implementation

The invention may be implemented in any web rendering technology, native UI framework, or graphical application. The preferred embodiment uses HTML, CSS, and JavaScript, with peer indicator nodes rendered as absolutely-positioned DOM elements inside a containing radar stage element, and connecting lines rendered as SVG line elements in an overlaying SVG canvas. The graded fill is implemented using CSS `radial-gradient` with computed alpha values derived from the directional-bias magnitude per the formulas above.

---

## CLAIMS (informal — for non-provisional conversion later)

The following claims are illustrative of the scope of protection sought in a future non-provisional application. They are submitted here for reference purposes only and are not formal claims.

**1.** A computer-implemented graphical user interface for visualizing correlation structure between a focus financial instrument and a plurality of peer financial instruments, comprising:

  (a) a focus node rendered at or near the geometric center of a display region, the focus node displaying an identifier and a graded fill encoding a directional bias and bias magnitude of the focus financial instrument;

  (b) a plurality of peer indicator nodes rendered around the focus node, each peer indicator node corresponding to a respective peer financial instrument and being positioned at a radial distance from the focus node determined as a function of a live correlation magnitude between the peer financial instrument and the focus financial instrument; and

  (c) each peer indicator node being rendered with a graded fill having a hue determined by a directional bias of the peer financial instrument and an opacity determined by a magnitude of the directional bias.

**2.** The graphical user interface of claim 1, wherein each peer indicator node is rendered with a border style indicating a sign of correlation between the peer financial instrument and the focus financial instrument, the border style being one of solid (positive correlation) or dashed (inverse correlation).

**3.** The graphical user interface of claim 1, wherein each peer indicator node and the focus node display a direction glyph of geometric shape, the geometric shape being one of an upward-pointing triangle, a downward-pointing triangle, or a filled square, the geometric shape independently encoding the directional bias of the corresponding financial instrument.

**4.** The graphical user interface of claim 1, wherein the focus node further displays a basket verdict comprising an aggregate count of peer indicator nodes confirming the focus financial instrument's directional bias relative to the total number of peer indicator nodes.

**5.** The graphical user interface of claim 1, further comprising connecting lines rendered between the focus node and each peer indicator node, each connecting line having a color indicating whether the corresponding peer is confirming or diverging from the focus financial instrument's directional bias.

**6.** The graphical user interface of claim 1, further comprising a plurality of concentric ring guides rendered at radial distances corresponding to specified correlation thresholds.

**7.** The graphical user interface of claim 1, wherein the radial position of each peer indicator node is recomputed and the peer indicator nodes are animatedly repositioned upon receipt of updated market data.

**8.** The graphical user interface of claim 1, further comprising a lite-mode rendering in which supporting display elements are hidden and the radial visualization fills the available viewport.

**9.** A non-transitory computer-readable medium storing instructions which, when executed by one or more processors, cause the one or more processors to render the graphical user interface of claim 1.

**10.** A method comprising performing the rendering steps embodied by the graphical user interface of claim 1.

---

## ABSTRACT OF THE DISCLOSURE

A graphical user interface visualizes correlation structure between a focus financial instrument and a plurality of peer financial instruments. A focus node is rendered at the center of a display region with a graded fill encoding the focus instrument's directional bias and bias magnitude. Peer indicator nodes are rendered around the focus node, each at a radial distance proportional to the live correlation magnitude between that peer and the focus instrument, with a graded color fill encoding the peer's directional bias and a border style encoding correlation sign. Geometric shape glyphs and connecting lines provide redundant directional encoding for accessibility. The focus node displays an aggregate basket-confirmation verdict. A lite-mode rendering hides supporting elements and fills the viewport with the radial visualization. The invention permits at-a-glance reading of basket-disposition structure not achievable by prior-art tabular correlation matrices, heatmaps, or scatter plots.

---

## NOTES FOR THE INVENTOR

### Before filing

1. **Have a registered patent practitioner review this draft.** A 30–60 minute attorney consultation will catch claim-language issues, prior-art gaps, and formal-format problems before your $300 filing locks the priority date. Look for an attorney experienced with software/UI patents; ask specifically about post-*Alice* drafting strategy for software inventions.

2. **Prepare the figures.** The "Brief Description of the Drawings" section above references six figures. At minimum, prepare:
   - One annotated screenshot of the live radar (FIG. 1)
   - One schematic diagram identifying each encoding channel (FIG. 2) — can be drawn in any vector editor
   - The other figures may be optional at the provisional stage

3. **Do a prior-art search.** Use Google Patents and USPTO's PatFT/AppFT databases to confirm nothing similar has been filed. Search terms to try: "radial correlation visualization," "polar correlation chart," "basket disposition visualization," "focus ticker correlation display."

4. **Confirm small-entity status.** As a solo founder filing through Penguins LLC, you almost certainly qualify as a "micro-entity" or "small entity" under USPTO rules, which reduces filing fees by 60–80%. Confirm and select the right fee tier on Form SB/16.

### Filing mechanics

- The provisional patent is filed via [USPTO EFS-Web](https://efs.uspto.gov/) (electronic filing). Upload this document (converted to PDF), the figures, and Form SB/16. Pay the filing fee. Receive a filing receipt with your priority date.
- After filing, you have **12 months** to convert to a non-provisional. During this window you can mark the product "Patent Pending."
- If you do not convert, the priority date lapses and the disclosure becomes public domain (in the sense that anyone can use it without infringing your rights, since you have none).

### What to do right after filing

- Update the website footer or a dedicated patent page with: "Radial correlation visualization is patent pending."
- Tighten access control on `run-brk.py` and any internal docs describing the algorithm. Trade-secret protection only works if you actively maintain secrecy.
- Begin tracking competitive products that might infringe; the priority date is what makes future enforcement possible.

### What this filing does *not* protect

- The specific implementation code (covered by copyright instead — register at copyright.gov for ~$65)
- The brand "HackTrader" (file a federal trademark application separately for ~$250–350)
- The scoring algorithm in `run-brk.py` (per the analysis we did earlier, this is not a strong patent candidate; trade secret + license terms are better protection)
