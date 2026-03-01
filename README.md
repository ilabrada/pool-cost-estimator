# Pool Cost Estimator

A modern, mobile-responsive web application for estimating the cost of building a swimming pool. Built for pool construction businesses to quickly generate professional estimates.

## Features

- 🔐 **PIN-based authentication** — Secure single-user access (default PIN: `1234`)
- 📐 **Pool cost calculator** — Estimate based on dimensions, material, shape, and add-ons
- 💰 **Detailed cost breakdown** — Itemized line items with subtotal, contingency, and total
- 📄 **PDF export** — Generate a professional PDF estimate to share with clients
- 🖨️ **Print support** — Print estimates directly from the browser
- 👥 **Client management** — Save clients and associate estimates with them
- 💾 **Saved estimates** — Store, search, edit, and delete past estimates
- 📱 **Mobile-first design** — Optimized for mobile, tablet, and desktop
- ⚙️ **Settings** — Change your PIN at any time

## Pool Variables Supported

| Category | Options |
|----------|---------|
| Dimensions | Length, Width, Depth (in feet) |
| Shape | Rectangular, Oval, Freeform/Custom |
| Material | Concrete (Gunite), Fiberglass, Vinyl Liner |
| Add-ons | Spa/Jacuzzi, Heating, Safety Cover, Water Feature, Steps, Deck, Fencing, Automation |
| Lighting | LED lights (per unit) |

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | [React 19](https://react.dev/) + [Vite 7](https://vite.dev/) |
| Styling | [Tailwind CSS v4](https://tailwindcss.com/) |
| Routing | [React Router v7](https://reactrouter.com/) |
| PDF | [jsPDF v4](https://github.com/parallax/jsPDF) |
| Storage | Browser `localStorage` (no backend required) |

## Getting Started

```bash
npm install
npm run dev
```

Open http://localhost:5173 and enter the default PIN `1234`.

## Build for Production

```bash
npm run build
```

The output in `dist/` is a fully static site — just HTML, CSS, and JavaScript. Upload it to any web host (Hostinger, cPanel, Netlify, GitHub Pages, etc.).

## Hosting

Since this is a static site, it can be hosted anywhere:

- **Shared hosting** (Hostinger, Bluehost, etc.) — Upload `dist/` via FTP/cPanel File Manager
- **Netlify / Vercel** — Connect your GitHub repo for automatic deploys
- **GitHub Pages** — Free static hosting

## Changing the Default PIN

Log in with the default PIN (`1234`), go to **Settings**, and update the PIN under "Change PIN". The PIN is stored in `localStorage`.

## Data Storage

All data (estimates, clients, PIN) is stored in the browser's `localStorage`. This means:
- Data persists between sessions on the same device/browser
- Data does not sync across devices
- Clearing browser data will erase all estimates
