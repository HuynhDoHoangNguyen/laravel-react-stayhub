<div align="center">

# 🌿 StayNest — Frontend

### *Discover, book, and manage stays in India's finest hotels*

[![Live Demo](https://img.shields.io/badge/🌐_Live_Demo-staynest.arclite.site-4ade80?style=for-the-badge)](https://staynest.arclite.site)
[![React](https://img.shields.io/badge/React_19-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![Vite](https://img.shields.io/badge/Vite_8-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vite.dev)
[![TailwindCSS](https://img.shields.io/badge/Tailwind_v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)](LICENSE)

</div>

---

## 📖 Table of Contents

- [Overview](#-overview)
- [Live Demo](#-live-demo)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Getting Started](#-getting-started)
- [Environment Variables](#-environment-variables)
- [Routes Reference](#-routes-reference)
- [API Integration](#-api-integration)
- [Component Architecture](#-component-architecture)
- [UI Design System](#-ui-design-system)
- [Build & Deployment](#-build--deployment)
- [Contributing](#-contributing)

---

## 🌟 Overview

**StayNest** is a full-featured hotel booking platform for travellers looking to discover and book curated stays across India. The frontend is built with **React 19**, **Vite 8**, and **Tailwind CSS v4** — delivering a premium, mobile-first experience with silky-smooth animations, AI-powered hotel search, and a complete hotel manager console.

```
Guest Experience     →  Search → Browse → Book → Pay → Track
Manager Experience   →  Create Hotel → Add Rooms → Go Live → Manage Bookings
AI Assistant         →  Chat with StayNest AI to discover the perfect stay
```

---

## 🌐 Live Demo

| Environment | URL |
|---|---|
| **Production** | [staynest.arclite.site](https://staynest.arclite.site) |
| **API Backend** | [hotel-booking-app-0swn.onrender.com/api/v1](https://hotel-booking-app-0swn.onrender.com/api/v1) |
| **API Docs (Swagger)** | [hotel-booking-app-0swn.onrender.com/swagger-ui.html](https://hotel-booking-app-0swn.onrender.com/swagger-ui.html) |

> **Note:** The backend runs on Render's free tier. On the first request after inactivity, expect a ~50 second cold-start delay.

---

## ✨ Features

### 🧳 Guest Features

| Feature | Description |
|---|---|
| **Smart Search** | Filter hotels by city, dates, rooms, price range, amenities, and star rating |
| **AI Chat Assistant** | Persistent AI panel powered by OpenAI that recommends hotels via natural language |
| **Hotel Details** | Full photo gallery, amenities, room listings, reviews, and per-hotel AI Q&A |
| **Room Booking** | Date-based inventory validation, multi-room selection, and real-time pricing |
| **Secure Checkout** | Stripe-powered payment with real-time booking confirmation polling (up to 120s) |
| **Booking Management** | View all bookings, cancel bookings, and track refunds with status badges |
| **Guest Profiles** | Add and manage travelling companions with dedicated guest profiles |
| **Wishlist** | Save and revisit favourite hotels |
| **User Profile** | Update personal info and change password |
| **Auth Flows** | Login, signup, forgot-password via email OTP, and password reset |

### 🏨 Manager Console

| Feature | Description |
|---|---|
| **Multi-Hotel Management** | Create and manage multiple hotel listings from a single dashboard |
| **Hotel Profile Editor** | Update name, city, contact info, amenities, and photos |
| **Room Types** | Create, update, and delete room types with custom pricing and amenities |
| **Photo Gallery** | Upload photos via Cloudinary with instant thumbnail previews |
| **Inventory Calendar** | Set and manage room availability counts per date |
| **Booking Insights** | View all bookings per hotel with search and status filters |
| **Refund Queue** | Review and process pending refund requests |
| **Revenue Reports** | Revenue and occupancy charts per hotel using Recharts |
| **Hotel Activation** | Toggle hotel live/draft to control public visibility |

---

## 🛠 Tech Stack

### Core

| Technology | Version | Purpose |
|---|---|---|
| [React](https://react.dev) | 19 | UI framework |
| [Vite](https://vite.dev) | 8 | Build tool & dev server |
| [React Router DOM](https://reactrouter.com) | 7 | Client-side routing |
| [Tailwind CSS](https://tailwindcss.com) | 4 | Utility-first styling |

### UI Components & Animation

| Library | Purpose |
|---|---|
| [Radix UI](https://www.radix-ui.com) | Accessible headless primitives (Dialog, Tabs, Select, Switch…) |
| [shadcn/ui](https://ui.shadcn.com) | Pre-built Radix-based component library |
| [Framer Motion](https://www.framer.com/motion/) | Page transitions, card hover lift animations |
| [Lucide React](https://lucide.dev) | Icon set |
| [Recharts](https://recharts.org) | Revenue & occupancy bar/line charts |
| [Embla Carousel](https://www.embla-carousel.com) | Photo carousels |
| [Sonner](https://sonner.emilkowal.ski) | Toast notifications |
| [React Day Picker](https://react-day-picker.js.org) | Date range calendar |

### Forms & Validation

| Library | Purpose |
|---|---|
| [React Hook Form](https://react-hook-form.com) | Form state management |
| [Zod](https://zod.dev) | Schema validation |
| [@hookform/resolvers](https://github.com/react-hook-form/resolvers) | Zod ↔ RHF bridge |

---

## 📁 Project Structure

```
StayNest/
├── public/                     # Static assets served directly
├── src/
│   ├── assets/                 # Bundled images (hero, auth, logos)
│   ├── components/
│   │   ├── bookings/           # BookingCard, BookingStatusBadge
│   │   ├── hotel/              # RoomsList, BookingCard (sidebar), ReviewList
│   │   ├── landing/            # HeroSection, HotelCard, SearchBar
│   │   ├── manager/            # Manager console components
│   │   │   ├── tabs/           # OverviewTab, RoomsTab, InventoryTab, BookingsTab…
│   │   │   ├── HotelProfileForm.jsx
│   │   │   ├── PhotoUploader.jsx
│   │   │   └── RequireManager.jsx    # Route guard for MANAGER/ADMIN roles
│   │   ├── search/             # AssistantPanel (AI), SearchHotelCard, FilterPanel
│   │   ├── ui/                 # shadcn/ui primitives (Button, Dialog, Input…)
│   │   └── RootLayout.jsx      # App shell: Navbar, auth event listener, session management
│   ├── hooks/
│   │   └── queries/            # Custom data-fetching hooks
│   │       └── manager.js      # useManagerHotel, useUpdateHotel, useRooms, useUploadPhoto…
│   ├── lib/
│   │   └── api/                # API client layer (all HTTP calls live here)
│   │       ├── client.js       # fetch wrapper: apiGet/Post/Put/Patch/Delete
│   │       ├── auth.js         # login, signup, logout, forgotPassword, resetPassword
│   │       ├── hotels.js       # getHotelInfo, getHotelReviews, askHotel (AI Q&A)
│   │       ├── bookings.js     # initBooking, addGuests, makePayment, verifyPayment, cancel
│   │       ├── admin.js        # manager CRUD: hotels, rooms, inventory, refunds, reports
│   │       ├── guests.js       # guest profile CRUD
│   │       ├── users.js        # user profile, change password
│   │       └── wishlist.js     # add/remove wishlist
│   ├── routes/                 # One file per page (flat file-based routing)
│   │   ├── index.jsx                     # Landing page
│   │   ├── auth.jsx                      # Login
│   │   ├── signup.jsx                    # Registration
│   │   ├── forgot-password.jsx           # Forgot password (OTP)
│   │   ├── reset-password.jsx            # Reset password via token
│   │   ├── search.jsx                    # Search results + AI assistant panel
│   │   ├── hotels.$hotelId.jsx           # Hotel details, rooms, reviews
│   │   ├── checkout.$bookingId.jsx       # Checkout & Stripe payment
│   │   ├── bookings.index.jsx            # My bookings list
│   │   ├── bookings.$bookingId.jsx       # Booking details page
│   │   ├── guests.jsx                    # Guest profile management
│   │   ├── wishlist.jsx                  # Saved hotels
│   │   ├── profile.jsx                   # User profile settings
│   │   ├── payments.success.jsx          # Payment confirmation + polling
│   │   ├── payments.failure.jsx          # Payment failure recovery
│   │   ├── manage.jsx                    # Manager console shell (sidebar layout)
│   │   ├── manage.hotels.index.jsx       # My hotels grid
│   │   ├── manage.hotels.new.jsx         # Create new hotel
│   │   ├── manage.hotels.$hotelId.jsx    # Hotel workspace (tabbed)
│   │   ├── manage.bookings.jsx           # Bookings across all hotels
│   │   ├── manage.refunds.jsx            # Refund queue
│   │   ├── manage.reports.jsx            # Revenue & occupancy reports
│   │   └── manage.settings.jsx           # Manager account settings
│   ├── styles.css              # Global CSS, design tokens
│   ├── App.jsx                 # React Router configuration
│   └── main.jsx                # React entry point
├── .env                        # Local environment variables
├── vercel.json                 # Vercel SPA rewrite rules
├── vite.config.js              # Vite configuration + path aliases
├── components.json             # shadcn/ui configuration
└── package.json
```

---

## 🚀 Getting Started

### Prerequisites

- **Node.js** ≥ 18
- **npm** ≥ 9

### 1. Clone the Repository

```bash
git clone https://github.com/ankit5609/StayNest-Frontend.git
cd StayNest-Frontend
```

### 2. Install Dependencies

```bash
npm install
```

### 3. Configure Environment

```bash
# Copy the example env file
cp .env.example .env
# Then edit .env with your backend URL
```

### 4. Start Development Server

```bash
npm run dev
```

The app will be available at **http://localhost:5173** with Hot Module Replacement enabled.

### Available Scripts

| Command | Description |
|---|---|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Build production bundle to `dist/` |
| `npm run preview` | Preview production build locally |
| `npm run lint` | Run ESLint |
| `npm run format` | Auto-format source code with Prettier |

---

## 🔑 Environment Variables

Create a `.env` file in the project root:

```env
# Required — Base URL of the Spring Boot backend API
VITE_API_BASE_URL=https://hotel-booking-app-0swn.onrender.com/api/v1

# For local development:
# VITE_API_BASE_URL=http://localhost:8080/api/v1
```

> **Important:** All Vite environment variables must be prefixed with `VITE_` to be accessible in browser-side code via `import.meta.env`.

---

## 🗺 Routes Reference

| Route | Page | Auth Required |
|---|---|---|
| `/` | Landing page with hero search | No |
| `/auth` | Login | No |
| `/signup` | Registration | No |
| `/forgot-password` | Forgot password | No |
| `/reset-password` | Reset via email token | No |
| `/search` | Hotel search results + AI panel | No |
| `/hotels/:hotelId` | Hotel details, rooms, reviews | No |
| `/checkout/:bookingId` | Checkout & Stripe payment | ✅ Guest |
| `/bookings` | My bookings list | ✅ Guest |
| `/bookings/:bookingId` | Booking details & receipt | ✅ Guest |
| `/guests` | Guest profile management | ✅ Guest |
| `/wishlist` | Saved hotels | ✅ Guest |
| `/profile` | User profile settings | ✅ Guest |
| `/payments/success` | Payment confirmation + polling | ✅ Guest |
| `/payments/failure` | Payment failure + retry | ✅ Guest |
| `/manage/hotels` | Manager: My hotels grid | ✅ Manager |
| `/manage/hotels/new` | Manager: Create new hotel | ✅ Manager |
| `/manage/hotels/:hotelId` | Manager: Hotel workspace | ✅ Manager |
| `/manage/bookings` | Manager: All bookings | ✅ Manager |
| `/manage/refunds` | Manager: Refund queue | ✅ Manager |
| `/manage/reports` | Manager: Revenue reports | ✅ Manager |
| `/manage/settings` | Manager: Account settings | ✅ Manager |

---

## 🔌 API Integration

All API calls are centralised in `src/lib/api/`. The base `client.js` provides typed, error-aware fetch wrappers with consistent behaviour:

### Client Layer (`client.js`)

```
apiGet(path, query?, init?)    → GET request with query string builder
apiPost(path, body, init?)     → POST with JSON body
apiPut(path, body, init?)      → PUT with JSON body
apiPatch(path, body, init?)    → PATCH with JSON body
apiDelete(path, init?)         → DELETE
```

**Features built into every request:**
- ✅ Automatic `Authorization: Bearer <token>` injection from `localStorage`
- ✅ Response unwrapping: extracts `data` from `{ timeStamp, data, error }` envelope
- ✅ Friendly user-facing error messages mapped by HTTP status code
- ✅ `staynest:auth-required` event dispatch on `401`/`403` → triggers login redirect

### Authentication Flow

```
1. User logs in → POST /auth/login
2. Server returns { accessToken, refreshToken, user }
3. Token stored in localStorage["staynest.auth.session"]
4. Every API call reads token and injects Authorization header
5. On 401/403 → custom event fires → RootLayout redirects to /auth
```

### Response Envelope

All backend responses follow this structure. The client unwraps `data` automatically:

```json
{
  "timeStamp": "2026-07-28T10:00:00",
  "data": { "...actual payload..." },
  "error": null
}
```

---

## 🧩 Component Architecture

```
RootLayout                  → Global shell: Navbar, session management, auth event handler
  │
  ├── LandingPage           → Hero section, featured hotels, AI search teaser
  │
  ├── SearchPage            → Full-featured hotel search
  │     ├── FilterPanel         → City, dates, rooms, price range, amenities, star rating
  │     ├── SearchHotelCard     → Hotel card with auto-cycling photo carousel on hover
  │     └── AssistantPanel      → AI chat assistant (persisted in sessionStorage)
  │
  ├── HotelDetailsPage      → Individual hotel view
  │     ├── Photo Gallery       → Multi-image with lightbox
  │     ├── RoomsList           → Room type cards with real-time pricing
  │     └── BookingCard         → Sticky sidebar price/date summary
  │
  ├── CheckoutPage          → Complete booking flow
  │     ├── Guest Selector      → Add registered guests
  │     ├── Price Breakdown     → Night-by-night cost summary
  │     └── Stripe Pay Button   → Initiates Stripe Checkout session
  │
  ├── PaymentSuccessPage    → Polls booking status every 3s for up to 120s
  │
  ├── BookingsPage          → My bookings with status filtering
  │     └── BookingCard         → Rich card with cancel & view receipt actions
  │
  └── ManageLayout          → Manager console with collapsible sidebar
        ├── ManageHotelsIndex   → Hotel property card grid
        ├── HotelWorkspacePage  → Tabbed single-hotel management hub
        │     ├── OverviewTab       → Edit hotel profile + gallery uploads
        │     ├── RoomsTab          → Room type CRUD with photo management
        │     ├── InventoryTab      → Date-based availability calendar editor
        │     ├── BookingsTab       → Bookings list for this specific hotel
        │     ├── RefundsTab        → Approve/reject refund requests
        │     └── ReportsTab        → Revenue & occupancy bar charts
        └── ManageBookingsPage  → Aggregated booking view across all hotels
```

### Access Control

`RequireManager.jsx` wraps all `/manage` routes. It validates the authenticated user's role (supporting both `{ role: "MANAGER" }` and `{ roles: ["MANAGER"] }` session shapes) and redirects non-managers to the home page.

---

## 🎨 UI Design System

StayNest uses **Tailwind CSS v4** with custom design tokens in `styles.css`:

### Colour Palette

| Token | Usage |
|---|---|
| `--color-primary` | Brand forest green — buttons, active states, links |
| `--color-surface` | Warm linen/cream — card and panel backgrounds |
| `--color-ink` | Deep dark — body text |
| `--color-muted-foreground` | Subdued grey — labels, hints, secondary text |
| `--color-destructive` | Alert red — delete actions and error states |

### Motion & Animation

| Element | Animation |
|---|---|
| Hotel cards | `whileHover={{ y: -4 }}` lift via Framer Motion |
| Search card photos | Auto-cycle every 1.2s on hover using `setInterval` |
| Modals & drawers | Radix-managed transitions |
| Toast notifications | Sonner slide-in/out |
| Loading states | Lucide `Loader2` with `animate-spin` |

### Spacing & Shape Conventions

- Buttons & inputs → `rounded-xl` (12px border-radius)
- Cards → `rounded-2xl` or `rounded-3xl`
- Shadows → layered `box-shadow` for premium depth
- Photo overlays → `bg-black/50` for text legibility on images
- Typography scale → `text-[12px]` → `text-[13px]` → `text-xl` → `text-3xl`

---

## 🏗 Build & Deployment

### Production Build

```bash
npm run build
# Output written to dist/
```

### Vercel Deployment

StayNest is deployed on **Vercel** with the following `vercel.json` for SPA routing:

```json
{
  "rewrites": [{ "source": "/(.*)", "destination": "/index.html" }]
}
```

All requests are served through `index.html` — React Router handles all navigation client-side.

### Bundle Size Notes

The production bundle is ~1.45 MB (402 KB gzipped). Primary contributors:
- **Framer Motion** — animation library
- **Recharts** — charting library
- **Radix UI** — multiple component packages

> Consider using `React.lazy()` + `Suspense` for route-based code splitting to reduce initial load time.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feat/amazing-feature`
3. Commit your changes: `git commit -m "feat: add amazing feature"`
4. Push to the branch: `git push origin feat/amazing-feature`
5. Open a Pull Request

### Code Style

- **Prettier** for auto-formatting → `npm run format`
- **ESLint** for code quality → `npm run lint`
- Component files → `.jsx` extension
- API/utility files → `.js` extension (no JSX)
- Use `@/` alias for absolute imports (configured in `vite.config.js`)

---

<div align="center">

Made with ❤️ &nbsp;·&nbsp; Built on **React 19** + **Vite 8** + **Tailwind CSS v4**

[⬆ Back to top](#-staynest--frontend)

</div>
