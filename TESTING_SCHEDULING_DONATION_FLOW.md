# Testing Guide: Scheduling Donation Flow

This guide helps you verify the **Scheduling Donation** flow after chat and approval.

---

## Flow Overview

| Step | What Happens | Where to Verify |
|------|--------------|-----------------|
| 1 | Recipient clicks "Scheduling Donation" above chat | Chat page |
| 2 | Form pops up: Date, Time, Location, Notes | Modal in chat |
| 3 | Recipient submits → Donor gets confirmation request | Donor Dashboard |
| 4 | Donor approves → Countdown + reminders created | Both dashboards |
| 5 | 1 day before: Reminder to both | Notifications (requires cron) |
| 6 | Same day, 2 hours before: Reminder | Notifications (requires cron) |
| 7 | 5 hours after donation time: "Was it completed?" | Both dashboards |
| 8 | Yes / No / Reschedule + Remarks | Both dashboards |

---

## Prerequisites

### 1. Database Tables

Ensure these exist (run migration if needed):

- `blood_donations`
- `scheduling_reminders`
- `emergency_notifications`

**Migration:** `assets/lib/migration_donor_dashboard_complete.sql`

### 2. Cron Job (Required for Reminders)

The scheduling cron **must run** for:

- 1-day-before reminder
- Same-day (2 hours before) reminder  
- 5-hours-after completion ask

**Setup on Windows (XAMPP):**

1. Open **Task Scheduler**
2. Create Basic Task
3. Trigger: **Daily**, repeat every **15 minutes**
4. Action: **Start a program**
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `d:\xampp\htdocs\blood_konnector\assets\lib\scheduling-cron.php`
7. Start in: `d:\xampp\htdocs\blood_konnector`

**Manual test (run in terminal):**

```bash
cd d:\xampp\htdocs\blood_konnector
php assets/lib/scheduling-cron.php
```

---

## Step-by-Step Testing

### Step 1: Scheduling Donation Button & Form

1. Log in as **Recipient**
2. Go to **Chat** with a donor (donor-inbox or recipient-inbox → open conversation)
3. Check that the **"Scheduling Donation"** button appears above the chat
4. Click it → A modal should open with:
   - Donation Date *
   - Donation Time *
   - Location / Hospital *
   - Additional Details (notes)
5. Fill the form and click **"Submit & Notify Donor"**
6. **Expected:** Success message; modal closes; donor gets a notification

**Note:** The API requires the current user to be the **recipient** when submitting. If the donor opens the modal, submission will fail (Invalid participant).

---

### Step 2: Donor Confirmation Request

1. Log in as **Donor**
2. Go to **Donor Dashboard**
3. **Expected:** A **"Pending Confirmation"** card appears with:
   - Recipient name
   - Donation date, time, location
   - **Confirm** and **Decline** buttons
4. Click **Confirm**
5. **Expected:** Success; card disappears; recipient is notified

**Alternative:** Click **Decline** → Donation is marked as failed; remarks can be added.

---

### Step 3: Countdown on Dashboards

After donor confirms:

1. **Donor Dashboard:** Check for any countdown or scheduled donation info
2. **Recipient Dashboard:** Check for any countdown or scheduled donation info

**Current behavior:** The donor dashboard shows a "countdown" for **next eligibility to donate** (4 months after last donation). A separate countdown for the **upcoming scheduled donation date** may need to be verified in the UI.

---

### Step 4: Reminders (1 Day Before, Same Day)

These are sent by the **scheduling cron**. To test:

**Option A – Use future dates (real time):**

1. Schedule a donation for **tomorrow** or a date 2+ days from now
2. Donor confirms
3. Run the cron every 15 minutes
4. When it’s 1 day before: both donor and recipient should get a notification
5. When it’s 2 hours before donation time: both should get another notification

**Option B – Simulate by changing DB dates:**

1. Create a scheduled donation (donor confirmed)
2. Manually update `scheduling_reminders.scheduled_for` to a past time (e.g. `NOW() - 1 minute`)
3. Run: `php assets/lib/scheduling-cron.php`
4. Check `emergency_notifications` for new rows
5. Check the notification bell in the header

---

### Step 5: Completion Ask (5 Hours After Donation Time)

1. Create a scheduled donation with:
   - Donation date = today
   - Donation time = 5+ hours ago (e.g. if now is 3 PM, use 9 AM)
2. Donor confirms
3. Cron creates `completion_ask` reminder with `scheduled_for` = donation time + 5 hours
4. Run the cron (or wait until that time)
5. **Expected:** Both donor and recipient see **"Was the donation completed?"** on their dashboards
6. **Expected:** Buttons: **Yes**, **No**, **Reschedule**

---

### Step 6: Completion Response (Yes / No / Reschedule + Remarks)

#### Yes (Successful)

1. Click **Yes**
2. A remarks field appears (optional)
3. Enter remarks and click **Submit**
4. **Expected:** Donation marked **completed**; appears in donation history

#### No (Failed)

1. Click **No**
2. Enter remarks (optional)
3. Submit
4. **Expected:** Donation marked **failed**

#### Reschedule

1. Click **Reschedule**
2. Enter new date and time
3. Enter remarks (optional)
4. Submit
5. **Expected:** Donation reset; donor gets a new confirmation request for the new date
6. Donor must confirm again; reminders are recreated for the new date

---

## Quick Checklist

- [ ] Scheduling Donation button visible above chat
- [ ] Form collects: Date, Time, Location, Notes
- [ ] Recipient can submit; donor gets confirmation request
- [ ] Donor can Confirm / Decline on dashboard
- [ ] After confirm: reminders created (1 day, day-of, 5h after)
- [ ] Scheduling cron runs (Task Scheduler or manual)
- [ ] 1-day-before reminder received
- [ ] Same-day (2h before) reminder received
- [ ] 5h-after completion ask appears on both dashboards
- [ ] Yes → Completed + remarks
- [ ] No → Failed + remarks
- [ ] Reschedule → New date requested; donor confirms again

---

## Troubleshooting

| Issue | Check |
|-------|--------|
| "Scheduling Donation" button not visible | Ensure you're on chat page with `?id=...` or correct user |
| Form submit fails | Ensure logged in as recipient; check browser console |
| Donor doesn’t see confirmation | Check `blood_donations`; `donor_confirmed = 0` |
| No reminders | Run scheduling cron; check `scheduling_reminders` |
| No completion ask | Ensure `completion_asked_at` is set; cron runs after donation time + 5h |
| Remarks not saved | Check `donor_remarks` and `recipient_remarks` in `blood_donations` |

---

## SQL Verification Queries

```sql
-- Pending donor confirmations
SELECT * FROM blood_donations WHERE status = 'scheduled' AND COALESCE(donor_confirmed, 0) = 0;

-- Completion asks (after 5h)
SELECT * FROM blood_donations WHERE status = 'scheduled' AND completion_asked_at IS NOT NULL;

-- Scheduling reminders
SELECT * FROM scheduling_reminders ORDER BY scheduled_for DESC;

-- Notifications
SELECT * FROM emergency_notifications ORDER BY created_at DESC LIMIT 20;
```

---

## Note on Manual Testing

This flow was implemented in code and reviewed for correctness. **End-to-end live testing** (chat → schedule → confirm → reminders → completion) was not run in a browser. Use this guide to walk through the flow and confirm behavior in your environment.
