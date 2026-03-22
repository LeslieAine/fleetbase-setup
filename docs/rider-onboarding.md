# Rider Onboarding Guide

## WhatsApp recruitment message (send to boda groups)

```
🛵 *EARN MORE PER DELIVERY*

We're looking for delivery riders in [CITY].

✅ Keep 93% of every delivery
✅ No monthly fees
✅ Work when you want
✅ Paid every Friday via Mobile Money

Requirements:
- Smartphone (Android or iPhone)
- Valid driving permit
- Boda/motorcycle

Register: [YOUR_SIGNUP_FORM_LINK]

Questions? WhatsApp: [YOUR_NUMBER]
```

---

## Onboarding form fields (build with Google Forms or Typeform)

1. Full name
2. Phone number (this is how they receive payouts)
3. National ID number
4. Photo of National ID (upload)
5. Photo of driving permit (upload)
6. Motorcycle registration number
7. Photo with motorcycle (upload — confirms they have the bike)
8. Mobile money number (MTN or Airtel)
9. Which city will you work in?

---

## Approval WhatsApp message (after you verify their documents)

```
Hi [NAME] 👋

Your application to join [YOUR COURIER NAME] has been approved!

Here's how to get started:

1. Download the rider app:
   Android: [PLAY STORE LINK]
   iPhone: [APP STORE LINK]

2. Log in with:
   Email: [their email or phone]
   Password: [temp password]

3. Go online and start accepting deliveries

Your earnings are paid every Friday via Mobile Money to [their number].

Any issues? WhatsApp us: [YOUR NUMBER]

Welcome aboard! 🛵
```

---

## Rejection WhatsApp message

```
Hi [NAME],

Thank you for applying to join [YOUR COURIER NAME].

Unfortunately we're not able to approve your application at this time.
[OPTIONAL REASON: e.g. "We're not yet active in your area."]

We'll keep your details and reach out when we expand.

Thank you for your interest.
```

---

## Weekly payout WhatsApp (send to each rider after running payout.sh)

```
Hi [NAME] 👋

Your earnings for the week of [DATE]:

Deliveries: [X]
Amount: UGX [AMOUNT]

Sending to [PHONE NUMBER] via [MTN/Airtel] now.
You'll receive it within 24 hours.

Thank you for your work this week! 🛵
```

---

## Operations tips for running this remotely

1. **WhatsApp group** — Create one group for all riders.
   Use it for: shift updates, area coverage info, urgent comms.
   Don't use it for individual disputes — handle those privately.

2. **Daily online check** — Glance at the Fleetbase console once in
   the morning and once in the evening. The live map shows you
   who's online and where.

3. **First complaint rule** — When a merchant or customer complains,
   always get the Fleetbase order ID first before responding.
   The order history shows exactly what happened and when.

4. **Rider rating** — Fleetbase has built-in ratings.
   Riders below 3.5 stars after 20+ deliveries get a warning.
   Below 3.0 after warning = off the platform.

5. **Disputes** — Lost/damaged packages at your scale:
   Small items (< UGX 50,000): refund from your commission pool.
   Larger items: investigate order photos in Fleetbase before paying.
