# Studio Management for EspoCRM

Installable EspoCRM 10 extension implementing studio shifts, model/operator profiles, role-based payout percentages, expiring registration links, immutable financial snapshots, and studio/producer analytics.

## Installation

1. In EspoCRM open **Administration → Extensions**.
2. Upload the release ZIP and install it.
3. Run **Administration → Rebuild** if EspoCRM does not rebuild automatically.
4. Clear browser cache and sign in again.

The extension is developed against EspoCRM `10.0.x` / PHP `8.3+` and does not modify core files.

## Initial setup

1. Open **Administration → Roles** and create the needed roles. Configure normal EspoCRM entity permissions for `SmShift`, `SmShiftFinancialSnapshot`, `SmRegistrationLink`, and boolean access to `SmModels`, `SmOperators`, and `SmAnalytics`.
2. Set **Percent of shift income** (`smSharePercent`) on model, operator, and producer roles. If a user has several roles, the highest percentage is used.
3. Open users and set **Studio Account Type**. A personal percentage can optionally override role percentages.
4. Open **Teams**, select the producer, and add model/operator users to the team.
5. Restrict creation of registration links to trusted producer/admin roles. Registration links default to 24 hours if their expiry is omitted or invalid.

## Shift flow

1. Create a preliminary shift and choose its team, assigned operator, model, model birth date, site credentials, and model images.
2. The assigned operator opens the full-page **Start** form and presses **Start Shift**. The server records the UTC start timestamp.
3. During an active shift, the operator can mark each site as started or banned.
4. **Finish Shift** records the end timestamp and opens the full-page calculation form.
5. The operator enters earnings and offline bonuses per site, uploads screenshots, and confirms **Complete Calculation**.
6. The server validates every amount, calculates exact cent totals, writes a `SmShiftFinancialSnapshot`, and closes the shift in one transaction.
7. A producer assigned to the shift team (or an administrator) can revise the financial rows and screenshots through the privileged correction endpoint. The shift and its snapshot are updated in the same transaction.

The sum of the model, operator, and producer percentages must not exceed 100%. Studio share is the remainder. Ordinary users cannot mutate completed shifts or snapshots; corrections are restricted to the producer of the team and administrators.

## Registration flow

Create a **Registration Link** record and choose account type, role, and expiry. EspoCRM generates a cryptographically random 256-bit token and a copyable public URL. The public form validates email/name/password on the server, assigns the preset role, and consumes the token transactionally. A unique redemption marker prevents concurrent double use.

## Analytics

The **Studio Analytics** tab offers today/week/month and custom date filters. Aggregation is executed by the database over immutable snapshot rows. Producer accounts are additionally hard-restricted to their own snapshots in the service, even if a role was configured too broadly.

## Model and operator profiles

The **Models** and **Operators** tabs are available to producer/admin roles. Producers only receive active employees from teams assigned to them; administrators receive all active studio employees. The server applies this restriction to list, profile, and comment endpoints.

Each list contains the requested employment dates, current/previous week figures, last shift, avatar, team/contact data, and a direct edit action. The standard User profile contains biography, site access notes, photos, document scans, chronological comments, the latest 100 completed shifts, and a personal-income chart grouped by payout date. Native EspoCRM attachment fields provide upload, removal, preview, and full-size opening.

## Security notes

- All persistence uses EspoCRM ORM/query builders; no request value is interpolated into SQL.
- Backend validation is authoritative. Frontend validation is convenience only.
- Passwords use EspoCRM's configured strength checker and bcrypt hash service.
- Invite tokens contain 256 bits of randomness, expire, and are single-use.
- Site rows and screenshot IDs are validated on the backend and have strict count and length limits.
- Start/finish actions are restricted to the assigned operator (or an administrator). Completed-shift corrections are restricted to the team producer (or an administrator).
- Staff lists, profiles, and comments are restricted on the backend to the owning producer or an administrator.
- Financial calculations use integer cents and fixed-point percentage units.

For internet-facing deployments, enable HTTPS and apply normal reverse-proxy rate limiting to `/api/v1/StudioManagement/register`.
