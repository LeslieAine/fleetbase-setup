# Your Courier Network — Powered by Fleetbase

A lightweight, asset-light courier dispatch platform built on top of Fleetbase.
Runs fully on your local machine for development. One command to start.

## What this is

- **Admin console** — manage riders, live map, orders, payouts
- **Rider app** — Fleetbase Navigator (rebrandable mobile app)
- **API** — connects to your Medusa merchant backend
- **Custom extensions** — 3-package cap, customer confirmation, nearby pickups

## Custom Features

| Feature | Status |
|---------|--------|
| Max 3 packages per rider | ✅ Implemented |
| Customer confirms ready to receive | ✅ Implemented |
| Rider sees nearby pickups at merchant location | ✅ Scaffolded |
| Path-based pickup notifications | ✅ Scaffolded |

## Quick Start

```bash
# 1. Copy environment file
cp .env.example .env

# 2. Edit .env with your values
nano .env

# 3. Start everything
docker-compose up -d

# 4. Wait ~60 seconds for services to boot, then run setup
./scripts/setup.sh

# 5. Open the console
open http://localhost:4200

# 6. Default login
# Email: admin@yourdomain.com
# Password: (set in .env as ADMIN_PASSWORD)
```

## Connecting to Your Medusa Backend

Set in `.env`:
```
MEDUSA_BACKEND_URL=http://localhost:9000
MEDUSA_WEBHOOK_SECRET=your_secret_here
```

When a delivery is marked DELIVERED in Fleetbase, it automatically calls your
Medusa backend to mark the order as fulfilled.

## Rider App

The Fleetbase Navigator app source is available at:
https://github.com/fleetbase/navigator-app

Rebrand by changing:
- `app.json` → name, slug, bundleIdentifier
- `assets/images/` → your logo
- Colors in `tailwind.config.js`

## Commission

Set in `.env`:
```
PLATFORM_COMMISSION_PERCENTAGE=7
```

Riders keep 93% of each delivery fee automatically.

## Structure

```
fleetbase-setup/
  docker-compose.yml          — all services
  .env.example                — environment template
  config/
    fleetbase.php             — core configuration
    commission.php            — commission rates
  extensions/
    multi-pickup/             — custom 3-package + nearby pickup logic
      src/
        Controllers/
          MultiPickupController.php
        Models/
          RiderCapacity.php
        Events/
          NearbyPickupAvailable.php
        Listeners/
          NotifyRiderOfNearbyPickup.php
        Notifications/
          NearbyPickupNotification.php
      routes/
        api.php
  scripts/
    setup.sh                  — first-time setup
    seed-riders.sh            — seed test riders
    payout.sh                 — weekly payout helper
  docs/
    rider-onboarding.md       — WhatsApp message templates
    medusa-integration.md     — how to connect to Medusa
```
