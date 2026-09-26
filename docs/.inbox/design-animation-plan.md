# Hazra Electrical Bike — Comprehensive Animation & Design Strategy

**Date:** 2026-08-31  
**Prepared by:** design-agent  
**Status:** Production-ready foundation with enhancement roadmap

---

## Executive Summary

The Hazra EV website implements a sophisticated animation system across 12+ sections using:
- **CSS-driven animations** for performance and accessibility
- **Scroll-linked variables** (--p, --fi) for dynamic content reveals
- **Staggered entrance effects** for visual hierarchy
- **Micro-interactions** for engagement and feedback
- **Theme-aware design tokens** for dark/light mode coherence

This document establishes:
1. **Current animation inventory** — what exists and how it works
2. **Design system patterns** — reusable animation principles
3. **Enhancement roadmap** — new effects and refinements
4. **Implementation guidelines** — CSS/JS contracts and best practices

---

## Part 1: Design System & Brand Colors

### Primary Brand Palette

| Token | Hex | Usage | Mood |
| --- | --- | --- | --- |
| Violet (Primary) | #7b2ff7 | CTAs, highlights, spec icons | Premium, electric |
| Magenta (Secondary) | #a41fbf | Hover states, secondary accents | Vibrant, energetic |
| Blue (Tertiary) | #12a5e0 | Supporting elements, alternative accents | Trustworthy, tech |
| Orange (Accent) | #f7941d | Calls-to-action highlights, decorative | Warm, approachable |
| Flame (Alert/Energy) | #f0532b | Validation, emphasis, energy | Urgent, attention |
| Indigo (Dark Accent) | #3a1a6b | Deep shadows, dark mode depth | Sophisticated |

### Gradient System

**Brand Gradient (Primary):**
```css
linear-gradient(96deg, #7b2ff7 0%, #a41fbf 30%, #f0532b 66%, #f7941d 100%)
```
Used on: CTAs, spec icons, gradient rules, performance metrics, decorative elements

### Typography Hierarchy

| Layer | Font | Weight | Usage |
| --- | --- | --- | --- |
| Headlines | Montserrat | 800–900 | H1, H2, H3, badges, labels |
| Body | Inter | 400–600 | Paragraph, metadata, UI labels |
| UI Metadata | Inter | 700–800 | Eyebrow, caption, small emphasis |

### Easing & Timing

All animations use:
- **Ease function:** `cubic-bezier(.22,1,.36,1)` (custom ease)
- **Short (UI):** 200–350ms
- **Medium (entrance):** 600–900ms
- **Long (scroll-driven):** 1200ms+
- **Decorative loops:** 5–26s

---

## Part 2: Current Animation Inventory

### 1. Hero Section (Scroll-Zoom Pattern)

**Animation Style:** Scroll-linked zoom and parallax

**Driver:** 
- `.scroll` (260vh tall) contains `.stage` (sticky)
- Scroll surplus maps to `--p` (0 → 1) via script.js
- All hero transforms derive from this single variable

**Key Effects:**

| Element | Animation | Trigger | Duration |
| --- | --- | --- | --- |
| Canvas card | Grows from inset card to full bleed | `--p` ramp | 2.6s scroll window |
| Photo | Expands right margin to full width | `--p` calc | N/A (scroll-driven) |
| Background | Blur down, scale up | `--p` (blur 0→46px) | N/A |
| Plates (nav, tab, shelf) | Fade out (opacity 0→1) | `--p` ramp | N/A |
| Typography | Fade to white for contrast | Color-mix off `--p` | N/A |
| Pills & tags | Fade out early (--p * 2.6) | Early exit | N/A |

**Entrance Sequence (delays):**
- Canvas: +60ms
- Title: +460ms
- Pills: +760ms, +180ms stagger
- Counter: +900ms

**Parallax Tracking:**
- Mouse tracks photo via `--px` / `--py` offsets
- Damped to zero as `--p` climbs (no wobble at full bleed)
- Conditional on desktop + no reduced-motion

**Responsive Behavior:**
- ≤1024px: No zoom (`--p:0`), card is full-width section
- Hero plates collapse into static headers
- Scroll height normalizes to page content

---

### 2. About Section (Mosaic Reveal)

**Animation Style:** Staggered tile flip on scroll entry

**Trigger:** IntersectionObserver (35% threshold)

**Mechanism:**
- JavaScript builds 8×5 grid of `.tile` elements (5×4 on mobile)
- Each tile clips one region of the background image
- On `.is-open`, tiles flip 82° on X-axis with fade
- Delay: `(col + row) / span * 620ms + (c*7+r*13)%5 * 34ms` (stable jitter)

**Key Animations:**

| Property | From | To | Duration | Easing |
| --- | --- | --- | --- | --- |
| Opacity | 1 | 0 | 620ms | ease-var |
| Transform | `rotateX(0) scale(1)` | `rotateX(82deg) scale(.6)` | 720ms | cubic-bezier(.5,0,.2,1) |
| Video | `scale(1.04)` | `scale(1)` | 1600ms | ease-var |

**Copy Reveals:**
- Lead (em): Gradient text + reveal-up
- Body text: reveal-up stagger
- Stats: Count-up animation on entry

**Stat Counter:**
- Easing: Ease-out cubic (1 - (1-k)³)
- Duration: 1400ms per number
- Trigger: IntersectionObserver on `.stats` (40% threshold)

**Accessibility:**
- Tiles hidden in `prefers-reduced-motion`
- Mosaic `.is-open` class still added (no shatter)
- Video poster always visible

---

### 3. Collection (Card Stagger & Pointer Tracking)

**Animation Style:** Scroll-entry stagger + hover micro-interactions

**Card Entrance (Scroll-In Stagger):**

| Property | From | To | Duration | Delay |
| --- | --- | --- | --- | --- |
| Opacity | 0 | 1 | 800ms | `var(--i) * 90ms` |
| Transform | `translateY(38px)` | `none` | 900ms | `var(--i) * 90ms` |
| Border/Shadow | — | Brightened | 400ms | On is-in |

**Stagger Index:** Script sets `--i` to card position % 3 (cycles 0–2 across 6 cards)

**Pointer-Tracked Shine:**
- Radial gradient at mouse position
- `--mx` / `--my` updated on mousemove
- Fade in on hover: `opacity 0 → 1`
- Only on devices with hover capability

**Swatch Interactions:**
- Click to change `--c` color variable
- Wash (soft-light blend) grades the product shot
- Button receives `is-on` class

**Variant Button Feedback:**
- Hover: `translateY(-2px)`
- Click: Border becomes `var(--ink-0)`

**Cards Hover State:**
- Image: `scale(1.02) → scale(1.09)`
- Card name: `translateX(4px)`
- Border: Hair color → ink color
- Shadow: Lifted 40px

---

### 4. Why Choose (Sticky Rail + Row Reveal)

**Animation Style:** Split layout with staggered row reveals + branded ticker loop

**Layout:**
- Left: Sticky rail (pitch copy + CTA)
- Right: Scrolling list of 6 reasons
- Rows reveal on stagger (IntersectionObserver, 20% threshold)

**Row Entrance:**

| Property | From | To | Duration | Delay |
| --- | --- | --- | --- | --- |
| Opacity | 0 | 1 | 700ms | `var(--i) * 90ms` |
| Transform | `translateY(24px)` | `none` | 700ms | `var(--i) * 90ms` |

**Row Hover Effects:**
- Number: `opacity .55 → 1`, `color → magenta`
- Icon: `color → flame`, `transform: translateX(2px)`
- Rule (::before): `width: 0 → 100%` (700ms)

**Ticker (Infinite Scroll):**
- Two identical tracks slide left continuously
- Animation: `to { transform: translateX(-100%) }` (26s linear)
- Pauses on hover (`animation-play-state: paused`)
- Masked edges (fade in/out)
- Decorative (aria-hidden)

---

### 5. Feature Window (Scroll-Scrubbed Carousel)

**Animation Style:** Fixed viewport with scroll-driven index mapping

**Driver:**
- `.feat__drive` (420vh tall) contains `.feat__pin` (sticky, 100vh)
- Scroll surplus maps to `--fi` (0 → 3, 4 models) via script.js
- Every child element reads `--n` (its index) and derives `--d` (distance)

**Slide Mechanics:**
- Position: `translateX(calc(var(--d) * 78%))`
- Scale: `scale(calc(1 - var(--d)² * .12))`
- Opacity: `calc(1 - var(--d)² * 1.35)`
- Rotation: `rotateY(calc(var(--d) * -17deg))`

**Spec Badges (Left Rail):**
- Icon gradient + glass backdrop
- Four values stacked; only active is opaque
- Value swap: `opacity: calc(1 - var(--d)² * 2.4)`, `translateY(var(--d) * -10px)`

**3D Stage:**
- Halo (glow): Opacity and scale off `--d`, 3D depth translate
- Floor (shadow): Squash `scaleX(calc(1 - var(--d)² * .28))`
- Image: Drop shadow intensifies on `.is-live`

**Bike Float Animation:**
- Only active slide: `animation: bikefloat 6.5s ease-in-out infinite`
- Keyframe: `0%,100% { translateY(0) }` → `50% { translateY(-2.2%) }`
- Provides breathing motion while centered

**Arc Dial:**
- Ticks (stationary) stay fixed
- Ring rotates under cursor: `rotate(var(--fi) * -1 * 23deg)`
- Model names fade in/out and rotate around arc
- Responsive: Hidden below 901px breakpoint

**Narrow Breakpoint (≤900px):**
- Becomes snap carousel
- Slides flex horizontally with scroll-snap-align
- Dots click → scroll slide into view
- No pinned scroll work, no arc

---

### 6. Performance Stats (Entrance Stagger + Rule Growth)

**Animation Style:** Cell entrance + gradient line draw on hover

**Cell Entrance:**

| Property | From | To | Duration | Delay |
| --- | --- | --- | --- | --- |
| Opacity | 0 | 1 | 750ms | `var(--i) * 110ms` |
| Transform | `translateY(26px)` | `none` | 750ms | `var(--i) * 110ms` |

**Gradient Rule (::after):**
- `scaleX(0 → 1)` on entry: 900ms, delayed `var(--i) * 110ms + 220ms`
- Origin: left
- On hover: `scaleX(1) scaleY(2)` (double thickness, emphasize)

**Number Animation:**
- Typography: Gradient text fill
- Count-up: 1300ms ease-out cubic
- Prefix/suffix support (e.g., "4–5 Hours", "400+")

---

### 7. News / Bento (Image Zoom + Scrim Fade)

**Animation Style:** Grid card entrance + layered hover effects

**Card Entrance:**

| Property | From | To | Duration | Delay |
| --- | --- | --- | --- | --- |
| Opacity | 0 | 1 | 800ms | `var(--i) * 100ms` |
| Transform | `translateY(28px) scale(.985)` | `none` | 800ms | `var(--i) * 100ms` |

**Image Hover:**
- Scale: `1.04 → 1.1` (1100ms ease)
- Filter: Saturate/contrast transition

**Scrim Fade:**
- Opacity: `.94 → 1` on hover (550ms)
- Two-layer: Gradient + brand tint wash
- Tint brightens on hover (soft-light blend)

**Body Lift:**
- Resting: `translateY(6px)`
- Hover: `transform: none` (600ms)

**Tag (Top-Left):**
- Glass morphism (blur + border + backdrop)
- Inherits color from `--tint`

---

### 8. Test Ride Form (Entrance + Field Interactions)

**Animation Style:** Two-column entrance + input focus states

**Media & Panel Entrance:**

| Property | From | To | Duration | Delay |
| --- | --- | --- | --- | --- |
| Opacity | 0 | 1 | 800ms | 0 / 120ms |
| Transform | `translateY(26px)` | `none` | 800ms | 0 / 120ms |

**Image Hover:**
- Scale: `1.03 → 1.08` (1200ms)

**Field Focus State:**
- Border: Hair color → brand-violet
- Background: Chip RGB → surface
- Box-shadow: Glow 3px `color-mix(violet 22%, transparent)`
- Transition: 350ms

**Field Invalid State:**
- Border: → flame color
- Shadow: Glow `color-mix(flame 18%, transparent)`

**Submit Button:**
- Gradient background with animation on hover
- Icon slides right: `translateX(4px)`
- Shadow lifts and warms

**Offer Badge:**
- Glass morphism corner placement
- Remains fixed during scroll within media

---

### 9. FAQ (Accordion Smooth Expand + Chevron Rotation)

**Animation Style:** Grid-template-rows height animation + icon rotation

**Panel Height:**
- `grid-template-rows: 0fr → 1fr` (550ms ease)
- Content measures itself (no JS height needed)

**Chevron Rotation:**
- Closed: `rotate(0deg)`
- Open: `rotate(135deg)` (450ms ease)
- Border/background: Transition to gradient

**Question Hover:**
- Color: Ink → magenta (350ms)

**Brand Rule:**
- `width: 0 → 100%` on open (550ms)
- Origin: left
- Position: bottom-left, 2px height

**Answer Text Fade-In:**
- Opacity: `0 → 1` (450ms)
- Transform: `translateY(8px) → none` (450ms)
- Delay: 100ms after panel opens

---

### 10. Insights (Post Card Hover + Lift)

**Animation Style:** Card entrance + hover lift with image zoom

**Card Entrance:**

| Property | From | To | Duration | Delay |
| --- | --- | --- | --- | --- |
| Opacity | 0 | 1 | 800ms | `var(--i) * 110ms` |
| Transform | `translateY(28px)` | `none` | 800ms | `var(--i) * 110ms` |

**Hover State (if is-in):**
- Lift: `translateY(-6px)` (500ms)
- Border: Hair → tint color mix (55%)
- Shadow: Tint-based glow at 70%

**Image Hover:**
- Scale: `1.03 → 1.09` (1100ms)

**Category Tag:**
- Background: Tint mix at 16% opacity
- Text color: Pure tint

**Read Link:**
- Gap: `7px → 13px` (350ms)
- Color: Ink → tint

---

### 11. Footer (Drift Glow + Social Interactions)

**Animation Style:** Ambient drift loop + interactive hover effects

**Glow Drift:**
- Keyframe: `translate3d(0,0,0) scale(1)` ↔ `translate3d(18%,-12%,0) scale(1.14)`
- Duration: 22s ease-in-out, infinite alternate
- Creates slow-moving focal glow behind footer

**Social Icon Hover:**
- Lift: `translateY(-3px)` (350ms)
- Background: Gradient fill
- Border: Transparent

**Footer Link Underline:**
- `width: 0 → 100%` on hover (400ms)
- Origin: left
- Tinted gradient

**Video Autoplay:**
- Plays only when footer is in viewport (IntersectionObserver, 15% threshold)
- Pauses when out of view
- Decorative (aria-hidden)

---

### 12. Floating Action Dock (Label Slide-Out + Tuck)

**Animation Style:** Fixed capsule with conditional label reveal

**Desktop Behavior:**
- Fixed right edge, vertically centered
- Label slides left on hover: `translate(8px,-50%) → translate(0,-50%)`
- Icon background: Gradient on hover
- Icon transform: `translateX(-2px)`

**Mobile Behavior (≤640px):**
- Repositions to bottom center (thumb reach)
- Horizontal layout (flex-direction: row)
- Label hidden (display: none)
- Icon hover: `translateY(-2px)` (more visible feedback)

**Tuck Animation (Phone Scroll-Down):**
- Visible on scroll-up
- Hidden on scroll-down: `translate(-50%, calc(100% + 26px))`
- Transition: 420ms + fade (300ms opacity)
- Conditional on phone breakpoint + scroll velocity > 10px

**Accessibility:**
- Focus-visible rings: `2px solid brand-violet`
- Outline-offset: 3px
- Label remains in DOM for assistive tech (not display:none)

---

## Part 3: Animation Design Patterns

### Pattern 1: Scroll-Linked Variables

**When to use:** Large sections with multiple related transforms

**Best practice:**
```css
.driver { height: 250vh; }
.pin { position: sticky; top: 0; }
/* JS writes a single --var (0 → 1 or 0 → N) */
.child { transform: calc(var(--var) * something); }
```

**Advantage:** All timing is scroll-driven; no setTimeout/RAF loops needed  
**Example:** Hero zoom, Feature window carousel

### Pattern 2: Staggered Entrance (Index-Based)

**When to use:** Grid of similar items (cards, rows, tiles)

**Best practice:**
```js
items.forEach((item, i) => {
  item.style.setProperty('--i', i % groupSize); // cycle for consistency
  once(item, el => el.classList.add('is-in'), threshold);
});
```

```css
.item {
  opacity: 0; transform: translateY(24px);
  transition: all 800ms ease-var;
  transition-delay: calc(var(--i) * 90ms);
}
.item.is-in { opacity: 1; transform: none; }
```

**Advantage:** Readable visual rhythm; CSS owns timing  
**Example:** Cards, Why reasons, Stats cells, News tiles

### Pattern 3: Hover Micro-Interactions (No JS)

**When to use:** Interactive elements that need tactile feedback

**Best practice:**
```css
.button {
  transition: transform 350ms ease-var, background 350ms ease-var;
}
.button:hover {
  transform: translateY(-2px);
  background: var(--brand-grad);
}
```

**Advantage:** Low-latency, no JS overhead, works on touch-hover devices  
**Example:** Cards, buttons, footer links, dock icons

### Pattern 4: CSS Custom Properties + Calc

**When to use:** Dynamic values from pointer/scroll position

**Best practice:**
```js
card.style.setProperty('--mx', ((x / width) * 100).toFixed(1) + '%');
```

```css
.card__shine {
  background: radial-gradient(50% 50% at var(--mx,50%) var(--my,50%), ...);
}
```

**Advantage:** GPU-accelerated, smooth feedback, no layout thrashing  
**Example:** Pointer-tracked shine, color grading

### Pattern 5: IntersectionObserver + Class-Based Animation

**When to use:** Scroll-triggered entrance animations

**Best practice:**
```js
const once = (el, cb, ratio = 0.3) => {
  const io = new IntersectionObserver(([e]) => {
    if (e.isIntersecting) { io.disconnect(); cb(el); }
  }, { threshold: ratio });
  io.observe(el);
};

once(section, el => el.classList.add('is-in'), 0.25);
```

**Advantage:** Fire-once guarantee, no scroll listener thrashing  
**Example:** Section reveals, card entry, stat counters

### Pattern 6: Layered Transitions (Staggered Phases)

**When to use:** Complex animations with multiple components

**Best practice:**
```css
/* Phase 1: Container entrance */
.item {
  opacity: 0; transform: translateY(20px);
  transition: all 600ms ease-var;
}
.item.is-in { opacity: 1; transform: none; }

/* Phase 2: Child detail (delayed) */
.item__detail {
  opacity: 0;
  transition: opacity 400ms ease-var 200ms;
}
.item.is-in .item__detail { opacity: 1; }
```

**Advantage:** Creates perceived performance (first frame lands quick, details follow)  
**Example:** Bento cards (image + scrim + body), FAQ panels (height + text fade)

---

## Part 4: Brand Animation Voice

### Principles

1. **Purposeful, not decorative** — Every animation either:
   - Directs attention (entrance, hover)
   - Provides feedback (state change, validation)
   - Reveals hierarchy (stagger, parallax)

2. **Speed for hierarchy** — Faster (200–350ms) for micro-interactions, slower (600–900ms) for major reveals

3. **Easing sells elegance** — Custom ease (`cubic-bezier(.22,1,.36,1)`) on all transitions. Linear only for loops and scroll-driven effects.

4. **Reduce motion is respected** — `prefers-reduced-motion:reduce` disables all animations, no flashes or jumps

5. **Color system is animated** — Gradients, tints, and color-mix transitions reinforce brand presence in motion

### Tone

- **Energetic:** Slight overshoot (custom ease with .36,1 anchors)
- **Confident:** Smooth landings (cubic ease-out, not bounces)
- **Premium:** Blur and depth effects (3D transforms, shadows)
- **Accessible:** Respects reduced-motion, no parallax on mobile, readable contrast always

---

## Part 5: Enhancement Roadmap

### Phase 1: Consistency Passes (High Priority)

#### 1.1 Gradient Border Animations (Cards)
**Current:** Static border-top gradient on spec badges  
**Enhancement:** Animate gradient position on hover for all primary cards

```css
.card {
  border: 1px solid transparent;
  background: linear-gradient(rgb(var(--chip-rgb) / .55), rgb(var(--chip-rgb) / .55))
              padding-box,
              var(--brand-grad) border-box;
  background-size: 200% 200%;
  background-position: 0% 50%;
  transition: background-position 600ms var(--ease);
}
.card:hover {
  background-position: 100% 50%;
}
```

#### 1.2 Icon Rotation & Scale Standardization
**Current:** Ad-hoc SVG transforms (why items, FAQ chevron)  
**Enhancement:** Establish reusable icon animation classes

```css
.icon--rotate-hover {
  transition: transform 400ms var(--ease);
}
.icon--rotate-hover:hover {
  transform: rotate(180deg);
}

.icon--scale-hover {
  transition: transform 300ms var(--ease);
}
.icon--scale-hover:hover {
  transform: scale(1.12);
}
```

#### 1.3 Unified Button Micro-Interactions
**Current:** Lift + gradient, varies per context  
**Enhancement:** Define `.btn--ink:hover` and `.btn--ghost:hover` as canonical patterns

- `--ink`: Lift + gradient shift + shadow warm-up + icon slide
- `--ghost`: Lift + border brighten + no shadow
- Both: 350ms ease-var

#### 1.4 Floating Object Improvements
**Current:** Pills bob (5s infinite)  
**Enhancement:** Expand floating animation to other elements

```css
@keyframes float {
  0%, 100% { transform: translateY(0) }
  50% { transform: translateY(-8px) }
}

.pill, .badge, .offer { animation: float 5s ease-in-out infinite; }
```

---

### Phase 2: Advanced Effects (Medium Priority)

#### 2.1 Staggered Text Reveal for Headlines
**Target:** All section titles  
**Technique:** Clip-path or word/line wrapper reveal

```css
.reveal-headline {
  display: flex;
  flex-wrap: wrap;
}
.reveal-headline span {
  opacity: 0;
  transform: translateY(20px);
  transition: all 700ms var(--ease);
}
.reveal-headline.is-in span {
  opacity: 1;
  transform: none;
  transition-delay: calc(var(--word-index) * 80ms);
}
```

#### 2.2 Scroll-Triggered Counter Animations (Beyond Stats)
**Target:** Stats, performance, any metric  
**Enhancement:** Consolidate counter logic into reusable utility

```js
const animateCounter = (el, to, duration = 1300) => {
  // Centralized count-up with prefix/suffix
  // Respect prefers-reduced-motion
  // Support custom easing
};
```

#### 2.3 Background Gradient Shift Animations
**Target:** Section transitions, bento hover  
**Enhancement:** Animate gradient angle or position on interaction

```css
.section {
  background: linear-gradient(var(--angle, 0deg), color1, color2);
  transition: background-position 1s var(--ease);
}
.section:hover {
  --angle: 180deg;
}
```

#### 2.4 3D Perspective Depth Effects
**Target:** Feature window, premium cards  
**Enhancement:** Add subtle 3D tilt on mouse move (desktop)

```js
card.addEventListener('mousemove', e => {
  const rect = card.getBoundingClientRect();
  const x = (e.clientX - rect.left) / rect.width - 0.5;
  const y = (e.clientY - rect.top) / rect.height - 0.5;
  card.style.setProperty('--rx', (y * -8).toFixed(2) + 'deg');
  card.style.setProperty('--ry', (x * 8).toFixed(2) + 'deg');
});
```

```css
.card {
  transform: perspective(1200px) rotateX(var(--rx,0)) rotateY(var(--ry,0));
  transition: transform 400ms var(--ease);
}
```

---

### Phase 3: Polish & Refinement (Lower Priority)

#### 3.1 Parallax Enhancement on Scroll
**Current:** Photo has parallax only in hero  
**Enhancement:** Subtle parallax on bento images, news photos

```css
.image-parallax {
  transform: translateY(calc(var(--scroll-y) * 0.5px));
  will-change: transform;
}
```

#### 3.2 Skeleton/Placeholder Animations
**Target:** Images, lazy-loaded content  
**Enhancement:** Pulse animation during load

```css
@keyframes pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}
.image-loading {
  animation: pulse 1.5s ease-in-out infinite;
}
```

#### 3.3 Interactive Gradient Animations on Form
**Target:** Test Ride form fields  
**Enhancement:** Gradient background shift on focus

```css
.field__i {
  background: linear-gradient(90deg, rgb(var(--chip-rgb) / .6), rgb(var(--chip-rgb) / .6));
  background-size: 200% 100%;
  background-position: 100% 0;
  transition: background-position 600ms var(--ease);
}
.field__i:focus {
  background-position: 0 0;
  background-color: var(--surface-0);
}
```

#### 3.4 Changelog/Success State Animations
**Target:** Form submission, checkout states  
**Enhancement:** Confetti, checkmark reveal, success glow

```css
@keyframes successPulse {
  0% { transform: scale(0.8); opacity: 0; }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); opacity: 1; }
}
.success-state {
  animation: successPulse 600ms cubic-bezier(.34,1.56,.64,1);
}
```

---

## Part 6: Implementation Guidelines

### CSS-First Philosophy

1. **All static animations belong in CSS**
   - Entrance, hover, loop, scroll-driven state changes
   - Reduces JS bundle size
   - Enables GPU acceleration

2. **JavaScript provides data, CSS provides motion**
   ```js
   // JS: write one number or flag
   el.style.setProperty('--progress', progress);
   el.classList.add('is-in');
   
   // CSS: interpret motion
   .element { transform: calc(var(--progress) * something); }
   .element.is-in { animation: entrance 800ms var(--ease); }
   ```

3. **Custom properties for theme-aware colors**
   ```css
   .button:hover {
     background: var(--brand-grad);
     color: #fff;
     box-shadow: 0 10px 26px color-mix(in srgb, var(--brand-violet) 60%, transparent);
   }
   ```

### Performance Checklist

- [ ] All scroll-driven animations use CSS transforms (translate, scale, rotate, opacity)
- [ ] Animations respect `will-change` on scroll-heavy elements
- [ ] Reduced-motion queries disable all non-essential animations
- [ ] No layout thrashing in pointer/scroll event listeners
- [ ] Scroll events use `requestAnimationFrame` or `passive: true`
- [ ] IntersectionObserver used for scroll-triggered reveals
- [ ] Transitions use GPU-friendly properties (transform, opacity, not width/height)
- [ ] Easing function is consistent (custom ease or linear for scroll)

### Accessibility Requirements

- [ ] All animations respect `prefers-reduced-motion:reduce`
- [ ] Animated state changes update `aria-expanded`, `aria-label` in sync
- [ ] Color-only feedback has additional indicators (icon change, underline)
- [ ] Focus states visible and distinct from hover
- [ ] Form validation provides text feedback, not just color
- [ ] Auto-playing video/loops have pause controls or mute autoplay
- [ ] No animations trigger seizures (flashing >3 times/second not allowed)

### Browser Support

- **Desktop:** Chrome, Firefox, Safari, Edge (all recent versions)
- **Mobile:** iOS Safari 12+, Chrome Android 90+
- **Fallback:** Graceful degradation for older browsers (no animations, content still readable)

**Test with:**
- Chrome DevTools: Performance tab for jank detection
- Firefox: about:performance for smoother metrics
- Safari: Safari DevTools for WebKit-specific issues
- Mobile: Real devices or Chrome DevTools device emulation

---

## Part 7: Current Implementation Status

### Fully Implemented ✅
- [x] Hero scroll-zoom with parallax
- [x] About mosaic tile reveal
- [x] Collection card stagger + pointer shine
- [x] Why Choose sticky rail + row stagger
- [x] Features scroll-scrubbed carousel with 3D
- [x] Performance stats entrance + rule growth
- [x] News bento hover + image zoom
- [x] Test Ride entrance + field focus
- [x] FAQ accordion smooth expand
- [x] Insights card entrance + lift hover
- [x] Footer drift glow + social hover
- [x] Dock fixed capsule + label reveal

### Partial/Pending ⏳
- [ ] Gradient border animations (proposed)
- [ ] Staggered text reveals (proposed)
- [ ] Advanced 3D tilt (proposed)
- [ ] Skeleton pulse animations (proposed)

### Roadmap for Next Sprint 🔄
1. Add gradient border animations to primary cards
2. Implement staggered text reveal for all H2s
3. Refactor counter animations into shared utility
4. Add floating object animations to badges/offers
5. Polish timing across all micro-interactions for 350ms standard

---

## Part 8: File Inventory

**Active animation files:**
- `html/style.css` — All animations implemented (2253 lines)
- `html/script.js` — Animation triggers and scroll/pointer math (603 lines)
- `website/assets/css/style.css` — Mirror or extension (TBD)
- `website/assets/js/script.js` — Mirror or extension (TBD)

**Key CSS sections in style.css:**
- Lines 1–91: Design tokens and root variables
- Lines 192–197: Floating pill animations
- Lines 545–550: Entrance animations (.reveal-up, .reveal-pop)
- Lines 708–783: Mosaic reveal + tile animations
- Lines 844–863: Card entrance stagger
- Lines 1050–1055: Why row brand rule
- Lines 1336–1342: Feature window bike float
- Lines 1565–1577: Performance rule growth
- Lines 1637–1690: Bento card entrance + hover
- Lines 1847–1852: FAQ rule animation
- Lines 1883–1897: FAQ panel expand + text fade
- Lines 2016–2029: Footer drift glow
- Lines 2176–2211: Dock label slide

---

## Conclusion

Hazra Electrical Bike's animation system is **production-ready** with:
✅ Consistent easing across all transitions  
✅ Accessible animations respecting prefers-reduced-motion  
✅ Scroll-driven effects optimized for performance  
✅ Staggered entrances for visual hierarchy  
✅ Micro-interactions that feel tactile and responsive  

**Next phase:** Implement Phase 1 enhancements (gradient borders, text reveals, unified button states) to increase visual impact and brand consistency without performance overhead.

---

**Document Version:** 1.0  
**Last Updated:** 2026-08-31  
**Author:** design-agent  
**Review Status:** Ready for implementation sprint
