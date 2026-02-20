<?php
/**
 * Date formatting helper - consistent user-facing format: Fri, 26 Feb, 10:00 AM
 */

/**
 * Format a date/datetime for display: "Fri, 26 Feb, 10:00 AM"
 * @param string|int $dateTime - Date string (Y-m-d, Y-m-d H:i:s, etc.) or Unix timestamp
 * @param bool $includeTime - If false, returns "Fri, 26 Feb" (date only)
 * @return string Formatted date or original value on parse failure
 */
function format_display_date($dateTime, $includeTime = true) {
    if (empty($dateTime)) return '—';
    $ts = is_numeric($dateTime) ? (int)$dateTime : strtotime($dateTime);
    if ($ts === false) return is_string($dateTime) ? $dateTime : '—';
    return $includeTime ? date('D, d M, g:i A', $ts) : date('D, d M', $ts);
}
