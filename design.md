# Enterprise ERP Design Specification

This design specification is derived from a meticulous analysis of the **Paces - Multipurpose Tailwind CSS & Bootstrap Admin Dashboard Template** by Coderthemes. It maps the visual standards, color tokens, layout hierarchy, and component interfaces to our high-performance, decoupled Multi-Tenant Enterprise ERP system (Nuxt 3+, Tailwind CSS 4+, and PrimeVue).

---

## 1. Core Visual Philosophy
Our design standards reflect **"Operational Clarity, High Density, and Premium Aesthetics."** The ERP balances data-rich density (critical for accounting, payroll, and stock management) with modern UI elegance:
*   **Harmonious Accents:** A dynamic palette of sub-brand colors based on modern HSL tokens.
*   **Depth & Glassmorphic Surfaces:** Extensive use of background filters, soft backdrops (`backdrop-blur-md`), and layered card shadows.
*   **Tactile Feedback:** Gentle hover expansions (`scale-102`), soft shadow shifts, and dynamic border transitions.
*   **High-Density Focus:** Standard layouts feature clean, structural grids, optimized vertical padding, and a dark-mode first design to minimize screen fatigue.

---

## 2. Global Styling & Color System
The color scheme is designed to scale dynamically for multi-tenant setups, mapping colors to responsive CSS variables (`--color-primary`, `--color-secondary`, etc.).

### 2.1 Theme Palette Matrix
| Color Variable | Hex Code / Tailwind Equivalent | Role & Application |
| :--- | :--- | :--- |
| **Primary (Electric Indigo)** | `#3b82f6` / `blue-500` | Accent color, buttons, active menu states, main icons |
| **Primary Subtle** | `rgba(59, 130, 246, 0.1)` | Badges, hover states, card backdrops |
| **Secondary (Cool Gray)** | `#64748b` / `slate-500` | Subtitle text, inactive borders, secondary badges |
| **Secondary Subtle** | `rgba(100, 116, 139, 0.1)` | Grid borders, secondary card widgets |
| **Success (Emerald Green)** | `#10b981` / `emerald-500` | Active listings, positive metrics, published states |
| **Success Subtle** | `rgba(16, 185, 129, 0.1)` | Soft success badges, trending indicators |
| **Warning (Amber Orange)** | `#f59e0b` / `amber-500` | Pending states, critical stock alerts, rating stars |
| **Warning Subtle** | `rgba(245, 158, 11, 0.1)` | Pending badges, soft warnings |
| **Danger (Crimson Red)** | `#ef4444` / `red-500` | Out of stock states, negative trend metrics, delete actions |
| **Danger Subtle** | `rgba(239, 68, 68, 0.1)` | Out of stock badges, destructive buttons |
| **Info (Sky Blue)** | `#0ea5e9` / `sky-500` | Dynamic counts, customer tracking, help tips |
| **Info Subtle** | `rgba(14, 165, 233, 0.1)` | Soft info badges, customer avatars |

### 2.2 Surface Elevation & Mode Styling
```css
/* Base Surface Variables */
:root {
  /* Light Mode Surface */
  --bg-layout: #f8fafc;        /* Slate 50 */
  --bg-card: #ffffff;
  --border-color: #e2e8f0;    /* Slate 200 */
  --text-heading: #0f172a;    /* Slate 900 */
  --text-body: #475569;       /* Slate 600 */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
}

[data-bs-theme="dark"] {
  /* Dark Mode Surface */
  --bg-layout: #0b0f19;       /* Ultra-deep obsidian navy */
  --bg-card: #121824;         /* Rich Slate 900 surface */
  --border-color: #1e293b;    /* Slate 800 */
  --text-heading: #f8fafc;    /* Slate 50 */
  --text-body: #94a3b8;       /* Slate 400 */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.5);
  --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -4px rgba(0, 0, 0, 0.3);
}
```

---

## 3. Typographical Hierarchy
We utilize **Outfit** for modern high-contrast titles and **Inter** for robust, highly-legible interface layout:
*   **Headers & Titles (Outfit):**
    *   Page Main Titles: `H4` / `1.125rem` (`18px`), Font Weight `600` (Semibold), leading-tight.
    *   Section Subtitles / Card Headers: `H5` / `0.9375rem` (`15px`), Font Weight `500` (Medium).
*   **Body & UI Metadata (Inter):**
    *   Standard Text: `body-sm` / `0.875rem` (`14px`), Font Weight `400` (Regular).
    *   Sub-labels / Brand Names: `text-xs` / `0.75rem` (`12px`), Font Weight `400`.
    *   Badges / Sorting Caps: `text-xxs` / `0.6875rem` (`11px`), Font Weight `600`, Uppercase, tracking-wider.
*   **Monospace Figures (JetBrains Mono):**
    *   Applied to stock codes (SKU), currency amounts, counts, and date timestamps (`text-sm`, `tracking-tight`).

---

## 4. Main Shell Structure & Layout Components

```mermaid
graph TD
    A[Global Page Wrapper] --> B[Topbar Header]
    A --> C[Sidenav sidebar]
    A --> D[Main Content viewport]
    B --> B1[Left: Hamburger toggle & Breadcrumbs]
    B --> B2[Center/Right: Mega-Menu, App Grid, Notifications, Profile]
    C --> C1[Brand Logo Header]
    C --> C2[Profile Detail Card]
    C --> C3[Hierarchical Navigation Links]
    D --> D1[Page Header Title]
    D --> D2[Metric Stat Cards Row]
    D --> D3[Data Table & Filter Canvas]
    D --> D4[Footer Copyright & Quick Links]
```

### 4.1 Topbar Header Details
A high-density top interface stretching 100% width with a soft border separator (`border-bottom`). It contains:
1.  **Sidebar Toggle:** A floating circular action icon using Lucide `ti-menu-2` / `ti-menu-4` to expand/collapse the sidenav dynamically.
2.  **Mega Menu Dropdown:** An expandable visual board category index:
    *   **Categories:** Dashboards & Analytics, Project Management, User Management.
    *   **Banner Widget:** A colorful graphic panel highlighting active user session features ("Welcome Back David", upgrade prompt, premium themes).
3.  **Apps Grid (9-Grid Selector):** Dropdown menu with soft rounded logos representing quick integrations (Figma, GitHub, Slack, Dropbox, etc.).
4.  **Instant Theme Toggle:** Toggles system light/dark/system mode dynamically, rendering immediate SVG asset swaps.
5.  **Notifications Hub:** Badged bell icon displaying "+7 New". Shows grouped user notifications with interactive media avatars (e.g. comment details, build statuses).
6.  **Monochrome Control & Language Selector:** Custom country flag switches supporting English, Spanish, German, French, Italian, and Chinese.
7.  **Profile Center Dropdown:** Displays user avatar with green online status marker. Exposes:
    *   *My Profile, Chat Messages, Account Settings, Support FAQ, Lock Screen, Sign Out.*

### 4.2 Sidebar Sidenav Menu Details
A fixed-width sidebar spanning `260px` in standard desktop view, collapsing to a minimized icon-only state (`70px`).
*   **Dynamic Brand Logos:** Contains double brand representations (automatic switches for dark/light layouts).
*   **Sidenav Profile Card:** Inline visual component containing a rounded user portrait, name, and subtitle role details ("David Dev", "Art Director"), facilitating rapid identity confirmation.
*   **Navigational Groups:**
    *   *Main:* Dashboards (Ecommerce, Analytics, CRM, Finance, Projects).
    *   *Apps:* Decoupled operational modules including **Ecommerce** (Products, Grid, Orders, Inventory, Refunds, Reviews), CRM, Tasks, Invoices, HRM, and Support Center.
    *   *Base Elements:* Hierarchical indices for components (Modals, Grids, Alerts), Forms (Validation, Wizards, Editors), and custom advanced tables.

---

## 5. Feature View: Product Management Canvas
This primary modular workspace leverages structural grids to layout critical e-commerce inventories.

### 5.1 Quick Metrics Row (5-Column Responsive Grid)
Five highly-scannable card components layout key system metrics:
1.  **Products Card (Primary subtle):** Total product count `2,240` (Active Listings: `980`). Dynamic badge `+24 New` (Success color). Accent circle containing `ti-package`.
2.  **Orders Card (Secondary subtle):** Customer order metric `8,014` (Total Orders: `105K`). Badge `+120 New`. Accent circle containing `ti-shopping-cart`.
3.  **Sales Card (Success subtle):** Today's revenue calculation `$17,854.22` (Today's Sales: `$156K`). Badge `+8.2%`. Accent circle containing `ti-currency-dollar`.
4.  **Customers Card (Info subtle):** Active client count `3,209` (Total Customers: `58,320`). Badge `+36 New`. Accent circle containing `ti-users`.
5.  **Revenue Card (Warning subtle):** Total gross margins `$3.50M` (Total Revenue: `$12.8M`). Badge `-4.5%` (Danger badge style). Accent circle containing `ti-chart-bar`.

### 5.2 Filter, Search & Actions Toolbar
A horizontal toolbar panel allowing seamless inventory control:
*   **Search Box:** Embedded prefix icon search input ("Search product name...").
*   **Multi-Filter Matrix:** Three clean inline select drop-downs:
    *   *Category Filter:* Incorporates tag icon (`ti-tag`). Exposes Electronics, Fashion, Home, Sports, Beauty.
    *   *Status Filter:* Incorporates activity icon (`ti-activity`). Exposes Published, Pending, Out of Stock.
    *   *Price Range Filter:* Incorporates currency icon (`ti-currency-dollar`). Exposes $0-$50, $51-$150, $151-$500, $500+.
*   **View Toggle:** Dual icon toggle button (Grid Category vs List Check representation).
*   **Primary Action Button:** Standalone bold red button (`btn-danger`) incorporating `ti-plus` for instant operational redirects ("Add Product").

### 5.3 High-Density Inventory Data Table
A modern, structural borderless list designed with absolute alignment:
*   **Header Sorting:** Standard metadata categories containing tiny SVGs indicating ascend/descend click status.
*   **Check-box Control:** High contrast border selector mapping dynamic selection parameters.
*   **Product Visual Avatar:** A detailed multi-item cell containing:
    *   A clean, rounded asset preview box (`48px` x `48px`).
    *   Bold product title (`text-heading`, link styled).
    *   A secondary subtitle detailing the supplier/vendor ("by: Brand name").
*   **Operational Columns:** Includes SKU, Category tags, clean numeric figures for Stock and Pricing, and interactive star graphics representing total customer ratings.
*   **Badges:** Uses curved, desaturated soft elements (`badge-soft-*` in light mode, high contrast solid in dark mode).
*   **Row Interactions:** Hover states apply transparent backdrops (`bg-slate-500/5`), softening active columns.
*   **Action Drawer:** Minimalist icons providing instant redirects: View (Eye), Edit (Pencil), and Delete (Trash).

---

## 6. Advanced Customizer Canvas (Offcanvas Menu)
A dynamic drawer sliding from the screen right margin allowing quick, custom client overrides:
1.  **Skin Selectors (24 custom theme cards):** Render visual configurations adjusting overall visual schemes (Prism, Minimalist, Vivid, Retro, Neon, Oblique, etc.).
2.  **Color Scheme Schemes:** Enforces light/dark overrides or aligns settings to the system layout.
3.  **Topbar & Sidenav Color Tuning:** Sets navbar properties to Light, Dark, Gray, or customizable color gradients.

---

## 7. Frontend Decoupled Nuxt/PrimeVue Integration
To keep this theme pixel-perfect in our Vue 3 + TypeScript architecture, follow these rules:

1.  **Tailwind Utility Standards:**
    *   Use Tailwind CSS `@apply` patterns within single-file components.
    *   Prefer Tailwind standard variables over hardcoded colors to maintain multi-tenant dynamically injected branding:
        ```html
        <!-- Example Badge Component -->
        <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xxs font-semibold rounded bg-success-subtle text-success">
          <i class="ti ti-circle-filled text-[6px]"></i>
          Published
        </span>
        ```
2.  **PrimeVue Component Mapping:**
    *   Map the Product list Table to PrimeVue's `<DataTable>` with styling overrides using `pt` (Pass Through) properties.
    *   Replace standard form elements with PrimeVue's `<InputText>`, `<Select>` (Dropdown), and `<Button>` styled matching Paces visual specification.
3.  **Dynamic Sidenav Configurations:**
    *   Render the navigational drawer dynamically utilizing Pinia configuration matrices to support multi-tenant modular capability mapping.

---

## 8. SEO Meta Configurations
For index-facing pages (public tenant landing panels, product detail displays):
*   **Meta Framework:** Injected via Nuxt `useHead`:
    ```typescript
    useHead({
      title: 'Enterprise ERP - Product Catalog Manager',
      meta: [
        { name: 'description', content: 'Configure, audit, and analyze your multi-tenant enterprise inventory with full security, database isolation, and detailed operational tracking.' }
      ]
    })
    ```
*   **Heading Structure:** Enforced single `<h1>` tag inside page scopes to optimize search crawler indexing index layouts.

---

## 9. Modular Tasks Canvas Design & Animation Concept (CSS Only)

To reflect the highly premium visual aesthetic found in senior frontend development styles, the **Tasks Module** in our Multi-Tenant ERP utilizes a pure-CSS animation and color framework. This ensures smooth, GPU-accelerated micro-animations without external JS package bloat.

### 9.1 GPU-Accelerated Animation Tokens
Integrate these custom animation keyframes and properties in `frontend/assets/css/main.css` to drive high-performance task metrics, urgent alerts, and interactive states:

```css
/* Next-Gen CSS Animation Keyframes for Tasks UI */
@layer base {
  @theme inline {
    --animate-orbit: orbit var(--duration, 10s) linear infinite;
    --animate-meteor: meteor var(--duration, 5s) linear infinite;
    --animate-ripple: ripple var(--duration, 2s) ease calc(var(--i, 0) * 0.2s) infinite;
  }

  /* 1. Orbit Effect: Circular task completion loaders & chart tracks */
  @keyframes orbit {
    0% {
      transform: rotate(calc(var(--angle) * 1deg)) translateY(calc(var(--radius) * 1px)) rotate(calc(var(--angle) * -1deg));
    }
    100% {
      transform: rotate(calc(var(--angle) * 1deg + 360deg)) translateY(calc(var(--radius) * 1px)) rotate(calc((var(--angle) * -1deg) - 360deg));
    }
  }

  /* 2. Meteor Glow: Floating streak gradients inside urgent task category cards */
  @keyframes meteor {
    0% {
      transform: rotate(var(--angle)) translateX(0);
      opacity: 1;
    }
    70% {
      opacity: 1;
    }
    100% {
      transform: rotate(var(--angle)) translateX(-500px);
      opacity: 0;
    }
  }

  /* 3. Ripple Pulse: Tactile indicator dots for Overdue or High-Priority Tasks */
  @keyframes ripple {
    0%, 100% {
      transform: translate(-50%, -50%) scale(1);
    }
    50% {
      transform: translate(-50%, -50%) scale(0.9);
    }
  }
}
```

### 9.2 Tasks Visual Layout Classes
*   **High-Priority Overdue Ripple Indicator**:
    ```html
    <div class="relative w-4 h-4 flex items-center justify-center">
      <span class="absolute w-full h-full rounded-full bg-red-500/20 animate-ripple" style="--duration: 1.5s; --i: 1;"></span>
      <span class="absolute w-2 h-2 rounded-full bg-red-500"></span>
    </div>
    ```
*   **Active Orbit Loading States**:
    ```html
    <div class="relative w-12 h-12 rounded-full border border-violet-500/10 flex items-center justify-center">
      <span class="absolute animate-orbit text-violet-400" style="--angle: 0; --radius: 20;">⚡</span>
      <span class="text-xxs font-bold text-gradient">78%</span>
    </div>
    ```
*   **Premium Meteor-Background Card**:
    ```html
    <div class="relative overflow-hidden p-5 rounded-xl border border-(--border-color) bg-(--bg-secondary)/80 backdrop-blur-md">
      <!-- Floating Meteor Effect -->
      <span class="absolute top-0 right-0 h-[2px] w-[100px] bg-linear-to-r from-violet-500 to-transparent animate-meteor" style="--angle: -45deg; --duration: 3s;"></span>
      
      <h3 class="text-xs font-bold text-(--text-primary)">Critical Release Sprint</h3>
      <p class="text-[10px] text-(--text-secondary) mt-1">Due today at 18:00</p>
    </div>
    ```
