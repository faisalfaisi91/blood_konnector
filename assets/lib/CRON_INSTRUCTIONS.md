# Cron Job Setup for Blood Konnector

## 1. Emergency Cron (existing)
Handles emergency request timeouts and reminders (24h, 6h, 1h before donation).

**Command:**
```
php /path/to/blood_konnector/assets/lib/emergency-cron.php
```

**Schedule:** Every 15-30 minutes
```
*/15 * * * * php /path/to/blood_konnector/assets/lib/emergency-cron.php
```

## 2. Scheduling Cron (new)
Handles:
- **1 day before** donation: reminder to donor and recipient
- **On the day** (2 hours before): reminder
- **5 hours after** donation time: completion ask (yes/no/reschedule) to donor and recipient

**Command:**
```
php /path/to/blood_konnector/assets/lib/scheduling-cron.php
```

**Schedule:** Every 15-30 minutes (or hourly)
```
*/15 * * * * php /path/to/blood_konnector/assets/lib/scheduling-cron.php
```

## Windows Task Scheduler
1. Open Task Scheduler
2. Create Basic Task
3. Trigger: Daily, repeat every 15 minutes
4. Action: Start a program
5. Program: `C:\xampp\php\php.exe`
6. Arguments: `d:\xampp\htdocs\blood_konnector\assets\lib\scheduling-cron.php`
7. Start in: `d:\xampp\htdocs\blood_konnector`

## XAMPP
For XAMPP on Windows, you can run both crons via a single batch file and schedule it with Task Scheduler, or use a third-party cron emulator.
