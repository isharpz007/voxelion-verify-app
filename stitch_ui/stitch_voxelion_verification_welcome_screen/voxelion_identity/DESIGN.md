---
name: Voxelion Identity
colors:
  surface: '#0b1326'
  surface-dim: '#0b1326'
  surface-bright: '#31394d'
  surface-container-lowest: '#060e20'
  surface-container-low: '#131b2e'
  surface-container: '#171f33'
  surface-container-high: '#222a3d'
  surface-container-highest: '#2d3449'
  on-surface: '#dae2fd'
  on-surface-variant: '#c4c6ce'
  inverse-surface: '#dae2fd'
  inverse-on-surface: '#283044'
  outline: '#8e9198'
  outline-variant: '#43474d'
  surface-tint: '#b0c8eb'
  primary: '#b0c8eb'
  on-primary: '#19324d'
  primary-container: '#0a2540'
  on-primary-container: '#768dad'
  inverse-primary: '#49607e'
  secondary: '#c6c6c7'
  on-secondary: '#2f3131'
  secondary-container: '#454747'
  on-secondary-container: '#b4b5b5'
  tertiary: '#89ceff'
  on-tertiary: '#00344d'
  tertiary-container: '#00273b'
  on-tertiary-container: '#0094d2'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#d2e4ff'
  primary-fixed-dim: '#b0c8eb'
  on-primary-fixed: '#001c37'
  on-primary-fixed-variant: '#314865'
  secondary-fixed: '#e2e2e2'
  secondary-fixed-dim: '#c6c6c7'
  on-secondary-fixed: '#1a1c1c'
  on-secondary-fixed-variant: '#454747'
  tertiary-fixed: '#c9e6ff'
  tertiary-fixed-dim: '#89ceff'
  on-tertiary-fixed: '#001e2f'
  on-tertiary-fixed-variant: '#004c6e'
  background: '#0b1326'
  on-background: '#dae2fd'
  surface-variant: '#2d3449'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.025em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.5rem
  margin: 1rem
  margin-tablet: 1.5rem
  margin-desktop: 2.5rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
  space-2xl: 3rem
---

## Brand & Style
The design system embodies institutional trust, cryptographically sound security, and effortless verification fluidity. Tailored for enterprise teams, compliance officers, and everyday end-users undergoing remote identity checks, the aesthetic bridges deep sovereign-level authority with modern developer ergonomics.

The visual direction follows **Modern Precision Minimalist** with tactile utility. It balances rigorous compliance requirements with low-friction UX:
- **Atmosphere:** Clean, clinical without feeling sterile, calm, uncompromisingly secure.
- **Visual Weight:** Restrained surface planes, surgical border micro-weights, crisp functional typography, and purposeful biometric scanning visual language.
- **Status Communicators:** Explicit, high-contrast, unambiguous link and verification state indicators (Valid, Expired, Invalid, Used) engineered for error-free mobile auditing.

## Colors
The color architecture relies on a dominant authoritative dark mode palette anchored by high-visibility interactive cyans and functional compliance semantics.

### Core Palette
- **Primary (`#0A2540` / `#0F2A4A`):** Sovereign Navy. Forms foundational headers, primary actions, critical audit badges, and structural anchors.
- **Secondary (`#FFFFFF`):** Pure Optical White. Applied strictly to interactive surfaces, elevated cards, and contrast shields.
- **Tertiary / Accent (`#0EA5E9` / `#38BDF8`):** Electric Cyan. Directs active biometric focus, progress indicators, link generation highlights, and active states.
- **Neutral Dark (`#0F172A` / `#1E293B`):** Deep Slate Ink. Reserved for primary data values, legal disclaimers, and title typography.
- **Muted Slate (`#64748B`):** Supporting metadata, timestamps, audit trail secondary text, and inactive iconography.
- **Canvas Neutral (`#F8FAFC` to `#F1F5F9`):** Subdued, glare-reducing serene backdrop that establishes surface contrast without straining visual endurance.

### Verification State Semantics
- **Valid (`#10B981` / Surface `#ECFDF5` / Border `#A7F3D0`):** Secure, authenticated, session active.
- **Expired (`#F59E0B` / Surface `#FFFBEB` / Border `#FDE68A`):** Link duration passed, re-verification required.
- **Invalid (`#EF4444` / Surface `#FEF2F2` / Border `#FECACA`):** Tampered link, cryptographic checksum failed.
- **Used (`#64748B` / Surface `#F1F5F9` / Border `#CBD5E1`):** Single-use token consumed, archived session.

## Typography
The system uses **Inter** across all functional tiers to maintain legibility across high-density identity tables, token hashes, and rapid mobile selfie capture instructions.

- Set numeric identity fields, token nonces, and timestamp indicators with tabular figures (`tnum`) to eliminate micro-shifts during real-time link validity timers.
- Constrain body text to a maximum of 68 characters per line on desktop and full span on mobile devices with high line-height to maintain comprehension during biometric compliance flows.

## Layout & Spacing
A fluid 4-column layout on mobile scales to 8 columns on tablet and 12 columns on desktop (max constrained width: 1240px).

- **Mobile First Focus:** Spacing priorities favor single-thumb reach zones for mobile verification. Primary action targets require a minimum interactive vertical envelope of 48px.
- **Section Stack Rhythm:** Vertical spacing between disparate functional domains (e.g., biometric verification panel, session credentials, audit actions) strictly follows an 8px base rhythm (`space-lg` to `space-2xl`).
- **Data Density:** Dashboard telemetry, payload logs, and state history utilize tight component padding (`space-xs` to `space-sm`) with clear dividing hairline borders.

## Elevation & Depth
Elevation is achieved using ambient diffused shadows combined with structural hairline borders (`#E2E8F0`) optimized for dark mode interfaces. Flat planes maintain order without visual clutter.

- **Level 0 (Base Canvas):** `#F8FAFC`. Zero elevation, non-interactive ground plane.
- **Level 1 (Card & Module Resting):** Pure White `#FFFFFF`, border `1px solid #E2E8F0`, shadow `0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.02)`.
- **Level 2 (Hover & Active Identity Triggers):** Shadow `0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`, accent border tinting on focus (`#0EA5E9`).
- **Level 3 (Modals, Biometric Frames, Overlays):** Shadow `0 20px 25px -5px rgba(10, 37, 64, 0.08), 0 8px 10px -6px rgba(10, 37, 64, 0.04)`. High diffusion softens technical boundaries.
- **Backdrop Overlay:** `rgba(10, 37, 64, 0.40)` with `backdrop-filter: blur(4px)` for modal biometric verification focus.

## Shapes
Roundedness sits at `2` (default 0.5rem / 8px). This level communicates modern software discipline while avoiding playful or overly casual contours.

- **Form Controls & Inputs:** `0.5rem` (8px). Maintains structure alongside numeric security pins and code inputs.
- **Cards & Identity Panels:** `rounded-lg` (1rem / 16px). Creates cohesive groupings for verification modules and telemetry cards.
- **Security Badges, State Pills, & Microchips:** Full pill (`9999px`) to distinguish categorical metadata and state chips from actionable buttons.

## Components

### Buttons
- **Primary:** Background `#0A2540`, text `#FFFFFF`, radius `0.5rem`, height 44px (48px mobile touch targets). Hover transitions to `#0F2A4A`.
- **Secondary / Ghost:** Transparent background, `1px solid #E2E8F0`, text `#0F172A`. Hover background `#F1F5F9`.
- **Tertiary / Action:** Background `#0EA5E9`, text `#FFFFFF`. Used strictly for "Verify Identity Now" and primary cryptographic actions.

### State Toggles & Status Badges
Status indicators must explicitly present the verification state:
- **Valid:** Pill badge with `#ECFDF5` background, `#047857` text, `#A7F3D0` border. Dot indicator in solid `#10B981`.
- **Expired:** Pill badge with `#FFFBEB` background, `#B45309` text, `#FDE68A` border. Dot indicator in solid `#F59E0B`.
- **Invalid:** Pill badge with `#FEF2F2` background, `#B91C1C` text, `#FECACA` border. Dot indicator in solid `#EF4444`.
- **Used:** Pill badge with `#F1F5F9` background, `#475569` text, `#CBD5E1` border. Dot indicator in solid `#64748B`.
- **Interactive State Switcher:** Segmented controller with pill layout (`#F1F5F9` container) allowing immediate preview switching between Valid, Expired, Invalid, and Used demonstration environments.

### Input Fields & Verification Controls
- Default: Height 44px, radius `0.5rem`, border `1px solid #E2E8F0`, background `#FFFFFF`, typography `body-md` in `#0F172A`.
- Focused: Border transitions to `#0EA5E9` with an outer ring of `0 0 0 3px rgba(14, 165, 233, 0.15)`.
- Identity Hash / Read-only Fields: Background `#F8FAFC`, monospace tracking, copy button positioned trailing with feedback indicator.

### Identity Cards
- Surfaces encapsulated in optical white (`#FFFFFF`) with 16px radius (`rounded-lg`), hairline border `#E2E8F0`, and ambient shadow.
- Header lockup displays verification recipient, current cryptographic status pill, and timestamp.
- Footer hosts secondary details, partner badge ("Integrated with OpinionPadi"), and direct action trigger.

### Verification Badges & Biometric Frames
- High-visibility security locks, face scan bounding boxes, and document frame markers utilize dual-stroke styling in `#0EA5E9` with animated scanning lines running across active inputs.