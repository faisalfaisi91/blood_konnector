# cPanel Cron Job Setup Guide - Blood Konnector

This guide explains how to set up the required cron jobs on cPanel for scheduling reminders and emergency request notifications.

---

## Step 1: Find Your PHP Path

1. Log in to **cPanel**
2. In the search box, type **"PHP"** or look for **"MultiPHP INI Editor"** / **"Select PHP Version"**
3. Or use **Terminal** (if available) and run:
   ```bash
   which php
   ```
4. Common paths on cPanel:
   - `/usr/local/bin/php`
   - `/usr/bin/php`
   - `/opt/cpanel/ea-php81/root/usr/bin/php` (PHP 8.1)
   - `/opt/cpanel/ea-php82/root/usr/bin/php` (PHP 8.2)
   - `/opt/cpanel/ea-php83/root/usr/bin/php` (PHP 8.3)

**Alternative:** In cPanel → **Software** → **Select PHP Version** → Note the path shown for PHP.

---

## Step 2: Find Your Project Path

Your Blood Konnector path on the server is usually one of:

- `/home/YOUR_USERNAME/public_html/blood_konnector`
- `/home/YOUR_USERNAME/domains/YOUR_DOMAIN/public_html/blood_konnector`

Replace:
- `YOUR_USERNAME` = your cPanel username
- `YOUR_DOMAIN` = your domain (if using addon/domain)

**Quick check:** In cPanel → **File Manager** → navigate to your `blood_konnector` folder → right‑click → "Copy path" or note the full path in the address bar.

---

## Step 3: Open Cron Jobs

1. Log in to **cPanel**
2. Under **Advanced** (or search for "Cron")
3. Click **Cron Jobs**

---

## Step 4: Add Cron Jobs

### Job 1: Scheduling Cron (Required)

**Purpose:** Sends reminders (1 day before, same day) and triggers the completion ask (5h after donation).

| Field | Value |
|-------|-------|
| **Minute** | `*/15` (every 15 minutes) |
| **Hour** | `*` |
| **Day** | `*` |
| **Month** | `*` |
| **Weekday** | `*` |
| **Command** | See below |

**Command (replace paths):**
```bash
/usr/local/bin/php /home/YOUR_USERNAME/public_html/blood_konnector/assets/lib/scheduling-cron.php
```

**Example:**
```bash
/usr/local/bin/php /home/username/public_html/blood_konnector/assets/lib/scheduling-cron.php
```

---

### Job 2: Emergency Cron (Optional but recommended)

**Purpose:** Handles emergency request timeouts and 24h/6h/1h reminders.

| Field | Value |
|-------|-------|
| **Minute** | `*/15` |
| **Hour** | `*` |
| **Day** | `*` |
| **Month** | `*` |
| **Weekday** | `*` |
| **Command** | See below |

**Command:**
```bash
/usr/local/bin/php /home/YOUR_USERNAME/public_html/blood_konnector/assets/lib/emergency-cron.php
```

---

## Step 5: Set the Schedule and Command

### Using “Add New Cron Job”

1. Under **Add New Cron Job**
2. **Common Settings:** choose **“Every 15 minutes”** (or custom)
3. **Custom cron schedule:**
   - Minute: `*/15`
   - Hour: `*`
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
4. **Command:** paste the full command (including PHP path and script path)
5. Click **Add New Cron Job**

### Schedule Options

| Schedule | Minute | Hour | Day | Month | Weekday | When it runs |
|----------|--------|------|-----|-------|---------|--------------|
| Every 15 min | `*/15` | `*` | `*` | `*` | `*` | Every 15 minutes |
| Every 30 min | `*/30` | `*` | `*` | `*` | `*` | Every 30 minutes |
| Every hour | `0` | `*` | `*` | `*` | `*` | Every hour on the hour |

Use **every 15 minutes** for both scripts.

---

## Step 6: Verify the Cron Jobs

1. After adding, they appear under **Current Cron Jobs**
2. Each job will have:
   - Schedule
   - Command
   - Status (Active)

---

## Step 7: Test Manually (Optional)

### Via cPanel Terminal

1. cPanel → **Terminal**
2. Run (adjust paths):
   ```bash
   cd /home/YOUR_USERNAME/public_html/blood_konnector
   php assets/lib/scheduling-cron.php
   ```
3. You should see JSON output, e.g.:
   ```json
   {"stats":{"reminders_sent":0,"completion_asks":0,"errors":0},"timestamp":"2026-02-20 12:00:00"}
   ```

### Via URL (if allowed)

Some hosts let you run cron via a URL. Example (if supported):

```
https://yourdomain.com/blood_konnector/assets/lib/scheduling-cron.php
```

If your host blocks this for security, use the Terminal or cron method instead.

---

## Common Issues

### “No such file or directory”

- Check the PHP path and script path
- Ensure the `blood_konnector` folder and files are uploaded correctly

### “Permission denied”

- Ensure the script is readable by the web server user
- Use `chmod 644` for the PHP files if needed

### Cron runs but nothing happens

- Confirm database credentials in `config.php` / `.env`
- Check that `blood_donations`, `scheduling_reminders`, and `emergency_notifications` tables exist
- Run the script manually in Terminal and look for errors

### Wrong PHP version

- Use the full path for your desired PHP version (e.g. `ea-php83`) instead of `/usr/local/bin/php`

---

## Checklist

- [ ] Found correct PHP path
- [ ] Found correct project path
- [ ] Added scheduling cron (every 15 minutes)
- [ ] Added emergency cron (every 15 minutes)
- [ ] Verified both jobs appear in Current Cron Jobs
- [ ] Tested scheduling cron manually in Terminal

---

## Summary Commands (Copy & Replace)

**Scheduling Cron:**
```bash
/usr/local/bin/php /home/YOUR_USERNAME/public_html/blood_konnector/assets/lib/scheduling-cron.php
```

**Emergency Cron:**
```bash
/usr/local/bin/php /home/YOUR_USERNAME/public_html/blood_konnector/assets/lib/emergency-cron.php
```

Replace `YOUR_USERNAME` and the path to `blood_konnector` with your actual values.
