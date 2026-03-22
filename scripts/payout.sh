#!/bin/bash
# scripts/payout.sh
#
# Weekly payout helper.
# Outputs a payout summary of what each rider earned this week.
# You then manually send via MTN MoMo / Airtel Money.
#
# Usage: ./scripts/payout.sh
#        ./scripts/payout.sh --week 2025-03-10  (specific week)

set -e
source .env

APP_CONTAINER="fleetbase-application"
WEEK_START=${1:-$(date -d 'last monday' +%Y-%m-%d 2>/dev/null || date -v-1w -v+Monday +%Y-%m-%d)}

echo ""
echo "═══════════════════════════════════════════════"
echo "  Payout Summary — Week of $WEEK_START"
echo "═══════════════════════════════════════════════"
echo ""

docker exec $APP_CONTAINER php artisan tinker --execute="
\$commission = config('commission.percentage', 7) / 100;
\$weekStart = '$WEEK_START';
\$weekEnd = date('Y-m-d', strtotime(\$weekStart . ' +7 days'));

\$results = DB::select(\"
    SELECT
        d.name AS rider_name,
        d.phone AS rider_phone,
        COUNT(o.uuid) AS deliveries,
        SUM(o.total) AS gross_ugx,
        ROUND(SUM(o.total) * (1 - ?\$commission), 0) AS rider_earns_ugx,
        ROUND(SUM(o.total) * ?\$commission, 0) AS platform_earns_ugx
    FROM orders o
    JOIN drivers d ON d.uuid = o.driver_assigned_uuid
    WHERE o.status = 'completed'
      AND o.updated_at BETWEEN '\$weekStart' AND '\$weekEnd'
    GROUP BY d.uuid
    ORDER BY rider_earns_ugx DESC
\", []);

foreach (\$results as \$r) {
    echo str_pad(\$r->rider_name, 25);
    echo str_pad(\$r->rider_phone, 15);
    echo str_pad(\$r->deliveries . ' deliveries', 18);
    echo 'UGX ' . number_format(\$r->rider_earns_ugx) . PHP_EOL;
}

echo PHP_EOL;
\$total = collect(\$results)->sum('rider_earns_ugx');
\$platform = collect(\$results)->sum('platform_earns_ugx');
echo 'Total to pay out:  UGX ' . number_format(\$total) . PHP_EOL;
echo 'Platform earned:   UGX ' . number_format(\$platform) . PHP_EOL;
"

echo ""
echo "═══════════════════════════════════════════════"
echo "  Send payments via MTN MoMo or Airtel Money"
echo "  Screenshot this and send to your riders"
echo "═══════════════════════════════════════════════"
echo ""
