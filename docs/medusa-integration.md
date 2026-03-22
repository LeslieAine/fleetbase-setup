# Connecting Fleetbase to Your Medusa Backend

## How it works

```
Merchant creates fulfillment in dashboard
  → dashboard calls POST /merchant/orders/:id/fulfillments/:fid/dispatch
  → your Medusa dispatch route creates a Fleetbase order
  → Fleetbase assigns nearest available rider
  → Rider picks up, delivers
  → Fleetbase fires internal webhook → your extension
  → Extension calls POST /courier/delivery-confirmed on Medusa
  → Medusa marks order as delivered + captures payment
```

## Step 1 — Add to your Medusa .env

```env
FLEETBASE_API_KEY=your_fleetbase_api_key
FLEETBASE_API_URL=http://localhost:8000/api/v1
FLEETBASE_WEBHOOK_SECRET=same_as_MEDUSA_WEBHOOK_SECRET_in_fleetbase_env
```

## Step 2 — Add the dispatch route to Medusa

In `src/api/merchant/orders/[id]/fulfillments/[fulfillment_id]/dispatch/route.ts`:

Replace the Uber Direct API calls with Fleetbase:

```typescript
// Create delivery in Fleetbase
const fleetbaseRes = await fetch(
  `${process.env.FLEETBASE_API_URL}/orders`,
  {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${process.env.FLEETBASE_API_KEY}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      // external references so webhook can match back
      meta: {
        fulfillment_id: fulfillmentId,
        medusa_order_id: orderId,
      },
      payload: {
        type: 'delivery',
        pickup: {
          name: merchant.name,
          street1: merchant.warehouse_address_line_1,
          city: merchant.warehouse_city,
          country: merchant.warehouse_country_code,
          phone: merchant.warehouse_phone,
          location: `POINT(${merchant.warehouse_lng} ${merchant.warehouse_lat})`,
        },
        dropoff: {
          name: `${addr.first_name} ${addr.last_name}`,
          street1: addr.address_1,
          city: addr.city,
          country: addr.country_code,
          phone: addr.phone,
          location: `POINT(${customer_lng} ${customer_lat})`,
        },
        description: itemSummary,
      },
    }),
  }
)

const fleetbaseData = await fleetbaseRes.json()
// fleetbaseData.data.tracking_number — show to merchant
// fleetbaseData.data.uuid — store for status checks
```

## Step 3 — Add the confirmation webhook endpoint to Medusa

Create `src/api/courier/delivery-confirmed/route.ts`:

```typescript
export async function POST(req, res) {
  const { fulfillment_id, order_id, delivered_at } = req.body

  // Verify secret
  const secret = req.headers['x-webhook-secret']
  if (secret !== process.env.FLEETBASE_WEBHOOK_SECRET) {
    return res.status(401).json({ error: 'Unauthorized' })
  }

  // Use your existing mark-as-delivered workflow
  await markOrderFulfillmentAsDeliveredWorkflow(req.scope).run({
    input: { orderId: order_id, fulfillmentId: fulfillment_id }
  })

  res.json({ ok: true })
}
```

## Step 4 — Register the webhook in Fleetbase console

1. Open http://localhost:4200
2. Settings → Webhooks → Add webhook
3. URL: http://host.docker.internal:8000/api/v1/multi-pickup/fleetbase-webhook
4. Events: order.completed
5. Secret: same value as MEDUSA_WEBHOOK_SECRET in .env

## Getting rider coordinates for Medusa

If the merchant's warehouse or customer address is a text address
(not lat/lng), geocode it first:

```typescript
// Free — no API key needed
async function geocode(address: string): Promise<{lat: number, lng: number}> {
  const encoded = encodeURIComponent(address)
  const res = await fetch(
    `https://nominatim.openstreetmap.org/search?q=${encoded}&format=json&limit=1`,
    { headers: { 'User-Agent': 'YourCourierNetwork/1.0' } }
  )
  const data = await res.json()
  if (!data[0]) throw new Error('Address not found')
  return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) }
}
```
