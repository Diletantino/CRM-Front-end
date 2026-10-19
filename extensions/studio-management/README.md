# Studio Management for EspoCRM

Installable EspoCRM 10 extension implementing studio shifts, model/operator profiles, role-based payout percentages, recruiter and technical-director expenses, expiring registration links, immutable financial snapshots, and production-scoped analytics.

## Installation

1. In EspoCRM open **Administration → Extensions**.
2. Upload the release ZIP and install it.
3. Run **Administration → Rebuild** if EspoCRM does not rebuild automatically.
4. Clear browser cache and sign in again.

The extension is developed against EspoCRM `10.0.x` / PHP `8.3+` and does not modify core files.

## Initial setup

1. Open **Administration → Roles** and create the needed roles. Set each role's **Studio Account Type** to the same type that will be selected when creating an account link (including existing roles before generating new links). Configure normal EspoCRM entity permissions for `SmShift`, `SmShiftFinancialSnapshot`, `SmRegistrationLink`, and boolean access to `SmModels`, `SmOperators`, and `SmAnalytics`.
2. Set **Percent of shift income** (`smSharePercent`) on model, operator, producer, and technical-director roles. If a user has several roles, the highest percentage is used.
3. Open users and set **Studio Account Type**. A personal percentage can optionally override role percentages. For a producer who also works as an operator, set separate **Operator Share Percent** and **Producer Share Percent** values.
4. Open **Teams** and fill its three studio slots: one producer, one operator and one model. Selecting a slot also adds that person to native Espo group membership. Adding a typed user from the native Users panel fills an empty matching slot; duplicate slot assignments are rejected. A producer can also occupy the operator slot.
5. Registration links are administrator-only. They default to 24 hours if their expiry is omitted or invalid.
6. For recruited employees, select the recruiter and one reward mode: **$150 after 5 shifts** or **Percent per Shift**. The amount and percentage are editable per employee.
7. Create the technical director as a user with account type **Technical Director**. Their configured percentage is charged on every completed shift.

## Shift flow

1. Create a preliminary shift and choose only its group. Operator, model, producer, birth date and site credentials are copied automatically. Add model images and optionally the planned start. Participants are validated on the server, including API requests without frontend autofill.
2. The assigned operator opens the full-page **Start** form and presses **Start Shift**. The server records the UTC start timestamp.
3. During an active shift, the operator can mark each site as started or banned.
4. **Finish Shift** records the end timestamp and opens the full-page calculation form.
5. The operator enters earnings and offline bonuses per site, uploads screenshots, and confirms **Complete Calculation**.
6. The server validates every amount, calculates exact cent totals, writes a `SmShiftFinancialSnapshot`, and closes the shift in one transaction.
7. A producer assigned to the shift team (or an administrator) can revise the financial rows and screenshots through the privileged correction endpoint. The shift and its snapshot are updated in the same transaction.

The sum of model, operator, producer, technical-director, and percentage-based recruiter expenses must not exceed 100%. The one-time recruiter reward is a fixed expense and can make studio net negative. Every payout is stored as a separate line in the financial snapshot; studio share is gross less all expenses. Ordinary users cannot mutate completed shifts or snapshots; corrections are restricted to the producer of the team and administrators.

A producer can be assigned as the shift operator. In that case the same user is recorded as shift creator/producer/operator, can execute the normal operator workflow, and receives the independently configured operator and producer payouts. Recruiter rewards are never paid for a user whose current account type is producer.

## Обновление групп и смен (18.10.2026)

«Роли» EspoCRM задают права доступа, а «Тип аккаунта студии» определяет рабочий слот. Тип аккаунта не меняется от добавления в группу. Для старых аккаунтов с пустым типом используется однозначная роль (поле типа в роли либо стандартное название).

В карточке группы доступны слоты «Продюсер», «Оператор», «Модель». Группу можно собрать постепенно, но неполный состав нельзя использовать для создания смены. Чтобы заменить участника, измените его слот, а не добавляйте второго пользователя того же типа.

Продюсер сохраняется в самой смене: изменение состава группы не перенаправляет выплаты уже начатой смены. Старые смены на подсчёте без этого поля поддерживаются: продюсер определяется из поля группы или её однозначного состава. При корректировке старой закрытой смены приоритет имеет продюсер финансового снимка.

Обновление существующей Docker-установки без удаления данных:

```sh
git pull --ff-only
docker compose up -d --build
```

При запуске контейнер сам выполняет Rebuild и `studio-sync-staff`. Не удаляйте volumes. При установке без Docker выполните `php command.php rebuild`, затем `php command.php studio-sync-staff`. После обновления перезагрузите браузер. Неоднозначные или неактивные старые участники требуют выбора корректного слота вручную; миграция не угадывает состав.

Регрессии для отдельной локальной тестовой установки:

```sh
node extensions/studio-management/tests/WorkflowClientTest.cjs
node extensions/studio-management/tests/TeamWorkflowHttpTest.cjs --keep
docker compose cp extensions/studio-management/tests/WorkflowIntegrationTest.php crm:/tmp/WorkflowIntegrationTest.php
docker compose exec -T crm php /tmp/WorkflowIntegrationTest.php
# После UI-проверки — мягкое удаление только фикстур TeamWorkflowHttpTest:
docker compose cp extensions/studio-management/tests/CleanupTeamWorkflowFixtures.php crm:/tmp/CleanupTeamWorkflowFixtures.php
docker compose exec -T crm php /tmp/CleanupTeamWorkflowFixtures.php
```

HTTP-тест проверяет также персональный вход обычного оператора, полный цикл его смены, запрет чтения чужих доступов и запрет обхода прав при создании смены только по ID группы. Не запускайте тесты с фикстурами на рабочей базе.

## Registration flow

An administrator creates a **Registration Link** record and chooses one of five staff account types (Model, Operator, Producer, Technical Director, Recruiter), a role with the matching Studio Account Type, optional recruiter, and expiry. Non-administrators cannot create or list account links. EspoCRM generates a cryptographically random 192-bit token encoded as exactly 32 URL-safe characters and a copyable public URL. The secret is placed directly after `#`: URL fragments are not sent in HTTP requests or Referer headers, and the client removes it from the address bar immediately after placing it in same-tab session storage. Previously issued 64-character tokens, `#access=...` links and old `?token=` links remain accepted; query and fragment secrets are removed from the address bar immediately. Before redemption, the URL opens the registration form, validates email/name/password on the server, assigns the preset role and recruiter, and consumes the registration step transactionally. A unique redemption marker prevents concurrent double use.

After registration the same URL becomes that user's only password-login page. It displays the account login as read-only, accepts the password, and sends the link token in a dedicated header. A server-side authentication hook verifies that the token belongs to exactly that user; hiding the standard form is not the security boundary. Existing session tokens continue to work until logout or expiry. Deleting the Registration Link blocks future password logins through it.

The standard login page is reserved for administrators and is shown only when both `studioAdminHosts` and `studioAdminAllowedIps` match the current request. Docker maps these values from `CRM_ADMIN_HOSTS` and `CRM_ADMIN_ALLOWED_IPS`. IP ranges in CIDR notation are supported.

## Analytics

The **Studio Analytics** tab offers today/week/month and custom date filters. Production aggregation is executed by the database over immutable snapshot rows, while personal earnings use indexed `SmShiftExpense` rows. Producers are restricted to their production, models and operators to their own shifts, and technical directors to all shifts. Recruiters receive only their personal payout total. These restrictions are enforced in the service even if an Espo role was configured too broadly.

## Model and operator profiles

The **Models** and **Operators** tabs are available to producer/admin roles. Producers only receive active employees from teams assigned to them; administrators receive all active studio employees. The server applies this restriction to list, profile, and comment endpoints.

Each list contains the requested employment dates, current/previous week figures, last shift, avatar, team/contact data, and a direct edit action. The standard User profile contains biography, site access notes, photos, document scans, chronological comments, the latest 100 completed shifts, and a personal-income chart grouped by payout date. Native EspoCRM attachment fields provide upload, removal, preview, and full-size opening.

## Security notes

- All persistence uses EspoCRM ORM/query builders; no request value is interpolated into SQL.
- Backend validation is authoritative. Frontend validation is convenience only.
- Passwords must be at least 10 characters and contain lower-case, upper-case, and numeric characters, in addition to EspoCRM's configured strength policy. They use EspoCRM's bcrypt hash service.
- New invite tokens contain 192 bits of randomness (32 URL-safe characters); previously issued 256-bit hex tokens remain valid. Both invitation lookup and password login compare secrets exactly using constant-time comparison after the indexed database lookup, independently of case-insensitive database collations. Registration is expiring and single-use; after redemption the same token becomes the account-bound login link.
- Site rows and screenshot IDs are validated on the backend and have strict count and length limits.
- Start/finish actions are restricted to the assigned operator (or an administrator). Completed-shift corrections are restricted to the team producer (or an administrator).
- Staff lists, profiles, and comments are restricted on the backend to the owning producer or an administrator.
- Financial calculations use integer cents and fixed-point percentage units.

For internet-facing deployments, enable HTTPS and apply reverse-proxy rate limiting to `/api/v1/StudioManagement/invitation`, `/api/v1/StudioManagement/register`, and `/api/v1/App/user`. Configure the reverse proxy so that only it can reach the CRM container and make it overwrite the forwarded client-IP header before changing `CRM_IP_ADDRESS_SERVER_PARAM` to that header. Administrator sessions are checked against the configured host/IP policy on every authenticated request, not only when the password is entered.
