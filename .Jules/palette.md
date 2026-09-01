## 2024-05-10 - Unlinked Form Labels and Missing Mobile Input Attributes
**Learning:** Found a recurring pattern of unlinked `<label>` tags and standard text inputs for mobile-specific data like phone numbers and OTPs. This causes a poor mobile commerce experience because it prevents users from easily tapping labels and forces standard keyboards instead of numeric keypads.
**Action:** Always verify `for`/`id` linking for form a11y, check `type="tel"` for phone numbers, and use `autocomplete="one-time-code"` for OTPs to enable native mobile OS integrations.
