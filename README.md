# Studio Management for EspoCRM

Installable EspoCRM 10 extension implementing studio shifts, role-based payout percentages, expiring registration links, immutable financial snapshots, and studio/producer analytics.

## Installation

1. In EspoCRM open **Administration → Extensions**.
2. Upload the release ZIP and install it.
3. Run **Administration → Rebuild** if EspoCRM does not rebuild automatically.
4. Clear browser cache and sign in again.

The extension is developed against EspoCRM `10.0.x` / PHP `8.3+` and does not modify core files.

## Initial setup

1. Open **Administration → Roles** and create the needed roles. Configure normal EspoCRM entity permissions for `SmShift`, `SmShiftFinancialSnapshot`, `SmRegistrationLink`, and boolean access to `SmAnalytics`.
2. Set **Percent of shift income** (`smSharePercent`) on model, operator, and producer roles. If a user has several roles, the highest percentage is used.
3. Open users and set **Studio Account Type**. A personal percentage can optionally override role percentages.
4. Open **Teams**, select the producer, and add model/operator users to the team.
5. Restrict creation of registration links to trusted producer/admin roles. Registration links default to 24 hours if their expiry is omitted or invalid.

## Shift flow

1. Create a shift draft; choose team, participants, and currency.
2. From the record actions choose **Open Shift**. The server records the UTC start timestamp.
3. Choose **Close Shift**, enter gross income and optional platform metrics as a JSON object.
4. The server validates all data, calculates exact cent amounts, and in one database transaction writes an immutable `SmShiftFinancialSnapshot` and closes the shift.

The sum of all participant percentages and the producer percentage must not exceed 100%. Studio share is the remainder. Financial history is read from snapshots; historical shifts are never recalculated after percentages change.

## Registration flow

Create a **Registration Link** record and choose account type, role, and expiry. EspoCRM generates a cryptographically random 256-bit token and a copyable public URL. The public form validates email/name/password on the server, assigns the preset role, and consumes the token transactionally. A unique redemption marker prevents concurrent double use.

## Analytics

The **Studio Analytics** tab offers today/week/month and custom date filters. Aggregation is executed by the database over immutable snapshot rows. Producer accounts are additionally hard-restricted to their own snapshots in the service, even if a role was configured too broadly.

## Security notes

- All persistence uses EspoCRM ORM/query builders; no request value is interpolated into SQL.
- Backend validation is authoritative. Frontend validation is convenience only.
- Passwords use EspoCRM's configured strength checker and bcrypt hash service.
- Invite tokens contain 256 bits of randomness, expire, and are single-use.
- Platform metrics are accepted only as a JSON object and limited to 16 KB.
- Financial calculations use integer cents and fixed-point percentage units.

For internet-facing deployments, enable HTTPS and apply normal reverse-proxy rate limiting to `/api/v1/StudioManagement/register`.
