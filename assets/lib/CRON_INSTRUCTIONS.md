# Cron Job Setup for Blood Konnector

**Important:** You must use **filesystem paths**, not domain names. `staging.bloodkonnector.com` is a domain—cron needs the folder path on the server (e.g. `/home/username/domains/staging.bloodkonnector.com/public_html`).

---

## 1. Emergency Cron
Handles emergency request timeouts and reminders (24h, 6h, 1h before donation).

**Format:** `*/15 * * * * /path/to/php /path/to/script.php`

**Examples (replace YOUR_USERNAME with your cPanel username):**

If site is in `public_html`:
```
*/15 * * * * /usr/local/bin/php /home/YOUR_USERNAME/public_html/assets/lib/emergency-cron.php
```

If site is in a subdomain (e.g. staging.bloodkonnector.com):
```
*/15 * * * * /usr/local/bin/php /home/YOUR_USERNAME/domains/staging.bloodkonnector.com/public_html/assets/lib/emergency-cron.php
```

---

## 2. Scheduling Cron
Handles reminders and completion ask.

**Examples:**

If site is in `public_html`:
```
*/15 * * * * /usr/local/bin/php /home/YOUR_USERNAME/public_html/assets/lib/scheduling-cron.php
```

If site is in subdomain (staging.bloodkonnector.com):
```
*/15 * * * * /usr/local/bin/php /home/YOUR_USERNAME/domains/staging.bloodkonnector.com/public_html/assets/lib/scheduling-cron.php
```

---

## How to find your correct path

1. **cPanel File Manager:** Go to your project folder → right‑click → "Copy path" or note the path in the address bar.
2. **cPanel Terminal:** Run `pwd` after `cd` into your project folder.
3. Path must start with `/home/` and end with the folder containing `assets/lib/`.

**Wrong:** `staging.bloodkonnector.com/assets/...` (domain, not path)  
**Correct:** `/home/username/domains/staging.bloodkonnector.com/public_html/assets/...` (full filesystem path)

---

## Fixing "bad command" errors

- Use **full filesystem path** (starts with `/home/`), never a domain
- Use full PHP path: `/usr/local/bin/php` (run `which php` in Terminal to verify)
- One line per cron job, no line breaks

## Windows Task Scheduler
1. Open Task Scheduler
2. Create Basic Task
3. Trigger: Daily, repeat evL`d:\xampp\htdocs\blood_konnector\assets\lib\scheduling-cron.php`
7. Start in: `d:\xampp\htdocs\blood_konnector`

## XAMPP
For XAMPP on Windows, you can run both crons via a single batch file and schedule it with Task Scheduler, or use a third-party cron emulator.
