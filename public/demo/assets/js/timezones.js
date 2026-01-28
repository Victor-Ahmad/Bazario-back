const TIMEZONES = [
    "UTC",
    "Europe/Berlin",
    "Europe/London",
    "Europe/Paris",
    "Europe/Istanbul",
    "America/New_York",
    "America/Chicago",
    "America/Denver",
    "America/Los_Angeles",
    "Asia/Dubai",
    "Asia/Riyadh",
    "Asia/Baghdad",
    "Asia/Tehran",
    "Asia/Kolkata",
    "Asia/Karachi",
    "Asia/Manila",
    "Asia/Singapore",
    "Asia/Tokyo",
    "Australia/Sydney",
    "Australia/Melbourne",
];

export function getTimezones() {
    const browserTz =
        Intl?.DateTimeFormat?.().resolvedOptions()?.timeZone || "UTC";
    return Array.from(new Set([browserTz, ...TIMEZONES]));
}
