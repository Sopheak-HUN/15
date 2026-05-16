# This is Sample Design Standard for ERP Apps, can be used as reference

## 1. Design Philosophy
Our design philosophy is centered around **"Clarity, Premium Quality, and Professionalism."** The ERP must feel powerful yet intuitive, using modern UI patterns like glassmorphism, subtle depth, and vibrant accent colors against a clean, enterprise-grade backdrop.

## 2. Color Palette (Inspired by pphat.me)

### Core Colors
- **Primary (Electric Cyan)**: `#00D1FF` (The signature brand color).
- **Primary Gradient**: From `#00D1FF` (Cyan) to `#2DD4BF` (Teal). Used for hero sections, active buttons, and premium highlights.
- **Secondary (Dark Slate)**: `#111827` (Used for headers, primary text, and high-contrast light-mode UI).
- **Accent (Emerald)**: `#10B981` (Used for success states, badges, and positive indicators).

### Surface & Background
- **Light Mode**: 
  - Background: `#FFFFFF` with a subtle geometric grid pattern.
  - Surface: `#F9FAFB` (Slate-50)
  - Text: `#64748B` (Slate-500) for body, `#111827` (Slate-900) for headings.
- **Dark Mode**: 
  - Background: `#030712` (A deep, sophisticated Black/Navy).
  - Surface: `#0F172A` (Slate-900)
  - Border: `#1E293B`
  - Text: `#D1D5DB` (Gray-300) for body, `#F9FAFB` (Gray-50) for headings.

## 3. Typography
- **Primary Font**: `Inter` (Sans-serif) for high readability at all sizes.
- **Header Font**: `Outfit` (Geometric Sans) for a premium, modern feel in titles and hero sections.
- **Mono Font**: `JetBrains Mono` for code snippets, IDs, and financial figures.

### Type Scale
- **H1**: 32px / 2rem (Bold, Outfit)
- **H2**: 24px / 1.5rem (Semibold, Outfit)
- **Body**: 14px / 0.875rem (Regular, Inter)
- **Caption**: 12px / 0.75rem (Medium, Inter)

## 4. Components & Atoms

### Buttons
- **Primary**: Solid Royal Indigo with a subtle bottom shadow. Hover: Brighten by 5%.
- **Ghost**: Transparent with Indigo border. Hover: Indigo background with 10% opacity.
- **Radius**: `0.75rem` (12px) for a soft, friendly enterprise feel.

### Cards & Surfaces
- **Shadow**: `0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)`
- **Glassmorphism**: Use `backdrop-blur-md` and `bg-white/70` for overlays and modals.

### Form Inputs
- **Focus State**: 2px solid Indigo with `ring-4 ring-indigo-500/20`.
- **Labels**: Always use floating labels or clear, top-aligned caps-lock captions for high-density forms.

## 5. Layout & Spacing
- **Grid System**: 12-column grid with `1.5rem` (24px) gutters.
- **Background Texture**: Subtle geometric SVG grid pattern used in Light and Dark modes to provide structure.
- **Sidebar**: Fixed width `280px`. Collapsible to `80px` (icon only) with a glassmorphic blur.
- **Density**: "Comfortable" by default, with a "Compact" toggle for data-heavy power users (e.g., Accounting).

## 6. Motion & Interaction
- **Glow Effects**: Use `box-shadow: 0 0 15px rgba(0, 209, 255, 0.3)` on active primary elements to create a neon depth.
- **Transitions**: `200ms cubic-bezier(0.4, 0, 0.2, 1)` for smooth, responsive interactions.
- **Micro-animations**: Subtle scale-up and glow intensity increase on hover.

## 7. Iconography
- **Library**: `Lucide Vue Next` (Consistent stroke width, minimalist design).
- **Stroke Width**: `1.75px` for a balanced look across light and dark modes.

## 8. Dark Mode Implementation
- **Strategy**: System-preferred by default.
- **Toggle**: A prominent Sun/Moon switch in the top header.
- **Aesthetics**: Avoid pure black (`#000`). Use deep slates (`#020617`) to preserve depth and shadow visibility.
