#!/bin/bash
# scripts/setup.sh
# Run this ONCE after docker-compose up -d
# It sets up the database, creates admin account, runs migrations

set -e

echo ""
echo "═══════════════════════════════════════════════"
echo "  Your Courier Network — First-Time Setup"
echo "═══════════════════════════════════════════════"
echo ""

# Load .env
if [ ! -f .env ]; then
  echo "❌ .env file not found. Run: cp .env.example .env"
  exit 1
fi

source .env

APP_CONTAINER="fleetbase-application"

echo "⏳ Waiting for services to be ready..."
sleep 10

echo ""
echo "1/6 Generating app key..."
docker exec $APP_CONTAINER php artisan key:generate --force

echo ""
echo "2/6 Running database migrations..."
docker exec $APP_CONTAINER php artisan migrate --force

echo ""
echo "3/6 Running extension migrations..."
docker exec $APP_CONTAINER php artisan migrate --path=packages/extensions/multi-pickup/database/migrations --force

echo ""
echo "4/6 Seeding initial data..."
docker exec $APP_CONTAINER php artisan db:seed --force

echo ""
echo "5/6 Creating admin account..."
docker exec $APP_CONTAINER php artisan fleetbase:create-user \
  --name="${ADMIN_NAME:-Admin}" \
  --email="${ADMIN_EMAIL:-admin@example.com}" \
  --password="${ADMIN_PASSWORD:-changeme123}" \
  --admin

echo ""
echo "6/6 Registering extension service provider..."
docker exec $APP_CONTAINER php artisan package:discover --ansi

echo ""
echo "═══════════════════════════════════════════════"
echo "  ✅ Setup complete!"
echo "═══════════════════════════════════════════════"
echo ""
echo "  Console:    http://localhost:4200"
echo "  API:        http://localhost:8000/api/v1"
echo "  Admin:      ${ADMIN_EMAIL:-admin@example.com}"
echo "  Password:   ${ADMIN_PASSWORD:-changeme123}"
echo ""
echo "  Next steps:"
echo "  1. Open http://localhost:4200 and log in"
echo "  2. Go to Settings → API Keys → create a key"
echo "  3. Add that key to your Medusa .env as FLEETBASE_API_KEY"
echo "  4. Share the rider app with your first drivers"
echo ""
