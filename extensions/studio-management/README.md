# Studio Management for EspoCRM

Installable EspoCRM 10 extension implementing studio shifts, model/operator profiles, role-based payout percentages, recruiter and technical-director expenses, expiring registration links, immutable financial snapshots, and production-scoped analytics.

## Installation

1. In EspoCRM open **Administration → Extensions**.
2. Upload the release ZIP and install it.
3. Run **Administration → Rebuild** if EspoCRM does not rebuild automatically.
4. Clear browser cache and sign in again.

The extension is developed against EspoCRM `10.0.x` / PHP `8.3+` and does not modify core files.

## Initial setup

1. Open **Administration → Roles** and create the needed roles. Roles define ACL permissions independently of the studio account type selected in an account link. Configure normal EspoCRM entity permissions for `SmShift`, `SmShiftFinancialSnapshot`, `SmRegistrationLink`, and boolean access to `SmModels`, `SmOperators`, and `SmAnalytics`.
2. Set **Percent of shift income** (`smSharePercent`) on model, operator, producer, and technical-director roles. If a user has several roles, the highest percentage is used.
3. Open users and set **Studio Account Type**. A personal percentage can optionally override role percentages. For a producer who also works as an operator, set separate **Operator Share Percent** and **Producer Share Percent** values.
4. Open **Teams** and fill its three studio slots: one producer, one operator and one model. Selecting a slot also adds that person to native Espo group membership. Adding a typed user from the native Users panel fills an empty matching slot; duplicate slot assignments are rejected. A producer can also occupy the operator slot.
5. Registration links are administrator-only. The form defaults to 24 hours; expired dates are rejected.
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

### Исправления интерфейса и ошибок (19.10.2026)

- В теме StudioDark окна быстрого создания, просмотра, выбора записей и подтверждения располагаются по центру экрана. Длинные формы прокручиваются внутри; заголовок и кнопки остаются видимыми. Свёрнутые окна и вложенные диалоги редактора не затрагиваются.
  - При создании ссылки тип аккаунта и роль выбираются независимо: тип определяет назначение сотрудника, а роль — его ACL-права. В списке доступны и старые роли без поля «Тип аккаунта студии».
  - Сервер проверяет существование выбранной роли, но не требует совпадения её служебного типа с типом создаваемого аккаунта.
- Проверки: `node extensions/studio-management/tests/RegistrationLinkValidationHttpTest.cjs` (только локальная тестовая установка с `.env`); существующие клиентские и PHP-регрессии также дополнены. Опция `--ui-fixture` оставляет тестовые роли для ручной проверки, `--cleanup` мягко удаляет только эти роли.

An administrator creates a **Registration Link** record and independently chooses one of five staff account types (Model, Operator, Producer, Technical Director, Recruiter), an ACL role, optional recruiter, and expiry. By default, non-administrators cannot create or list account links. An explicit invitation-management grant plus native link CRUD ACL can delegate this power to any account type. EspoCRM generates a cryptographically random 192-bit token encoded as exactly 32 URL-safe characters and a copyable public URL. The secret is placed directly after `#`: URL fragments are not sent in HTTP requests or Referer headers, and the client removes it from the address bar immediately after placing it in same-tab session storage. Previously issued 64-character tokens, `#access=...` links and old `?token=` links remain accepted; query and fragment secrets are removed from the address bar immediately. Before redemption, the URL opens the registration form, validates email/name/password on the server, assigns the preset role and recruiter, and consumes the registration step transactionally. A unique redemption marker prevents concurrent double use.

After registration the same URL becomes that user's only password-login page. It displays the account login as read-only, accepts the password, and sends the link token in a dedicated header. A server-side authentication hook verifies that the token belongs to exactly that user; hiding the standard form is not the security boundary. Existing session tokens continue to work until logout or expiry. Deleting the Registration Link blocks future password logins through it.

The standard login page is reserved for administrators and is shown only when both `studioAdminHosts` and `studioAdminAllowedIps` match the current request. Docker maps these values from `CRM_ADMIN_HOSTS` and `CRM_ADMIN_ALLOWED_IPS`. IP ranges in CIDR notation are supported.

## Analytics

The **Studio Analytics** tab offers today/week/month and custom date filters. Production aggregation is executed by the database over immutable snapshot rows, while personal earnings use indexed `SmShiftExpense` rows. Producers are restricted to their production, models and operators to their own shifts, and technical directors to all shifts. Recruiters receive only their personal payout total. These restrictions are enforced in the service even if an Espo role was configured too broadly.

## Model and operator profiles

The **Models** and **Operators** tabs preserve producer/admin visibility by default. Explicit Studio permissions can grant access to any account type; native section and User permissions are also required. Producers using legacy defaults see their production, while an explicit all-records grant covers the studio.

Each list contains the requested employment dates, current/previous week figures, last shift, avatar, team/contact data, and a direct edit action. The standard User profile contains biography, site access notes, photos, document scans, chronological comments, the latest 100 completed shifts, and a personal-income chart grouped by payout date. Native EspoCRM attachment fields provide upload, removal, preview, and full-size opening.

## Security notes

- All persistence uses EspoCRM ORM/query builders; no request value is interpolated into SQL.
- Backend validation is authoritative. Frontend validation is convenience only.
- Passwords must be at least 10 characters and contain lower-case, upper-case, and numeric characters, in addition to EspoCRM's configured strength policy. They use EspoCRM's bcrypt hash service.
- New invite tokens contain 192 bits of randomness (32 URL-safe characters); previously issued 256-bit hex tokens remain valid. Both invitation lookup and password login compare secrets exactly using constant-time comparison after the indexed database lookup, independently of case-insensitive database collations. Registration is expiring and single-use; after redemption the same token becomes the account-bound login link.
- Site rows and screenshot IDs are validated on the backend and have strict count and length limits.
- Start/finish and closed-shift correction checks use role-configurable Studio permissions in addition to native record ACL. Unconfigured roles retain the original operator/producer restrictions.
- Staff list/profile/comment endpoints enforce configured Studio permissions on the backend; changing only frontend visibility never grants server access.
- Financial calculations use integer cents and fixed-point percentage units.

For internet-facing deployments, enable HTTPS and apply reverse-proxy rate limiting to `/api/v1/StudioManagement/invitation`, `/api/v1/StudioManagement/register`, and `/api/v1/App/user`. Configure the reverse proxy so that only it can reach the CRM container and make it overwrite the forwarded client-IP header before changing `CRM_IP_ADDRESS_SERVER_PARAM` to that header. Administrator sessions are checked against the configured host/IP policy on every authenticated request, not only when the password is entered.

## Credits и юридические уведомления (22.10.2026)

### Локальное редактирование footer и страницы регистрации без пересборки

Основной `compose.yaml` по умолчанию подключает `client/res/templates/site/footer.tpl` из репозитория прямо в контейнер CRM, только для чтения. Абсолютных Windows-путей нет: на сервер переносится весь репозиторий вместе с этим файлом, затем выполняется `docker compose up -d --build`. Тот же шаблон включён в образ, в том числе в источник штатной установки, для запуска образа без bind mount. БД и загрузки не затрагиваются.

Аналогично подключён `html/main.html` в `/var/www/html/html/main.html`: это внешняя HTML-страница приложения, в том числе подпись на регистрации по персональной ссылке. Он также включён в образ и в источник штатной установки. Правьте локальный файл, сохраняйте и полностью обновляйте страницу браузера; пересборка и перезапуск контейнера не нужны. Сохраняйте служебные `{{...}}`-подстановки, загрузчик и скрипты: этот файл используется не только регистрацией. При обычном Compose-запуске на сервере файлы берутся из того же checkout, рядом с `compose.yaml`; оба файла должны существовать и быть доступны для чтения. При запуске только образа без bind mount изменение шаблонов требует новой сборки образа. Кэш reverse proxy/CDN, если он есть, очищается отдельно.

Для локального редактирования без кэша добавьте `-f compose.footer-dev.yaml` к своей команде Docker Compose (после основного `-f compose.yaml` и остальных override-файлов). Например:

```sh
docker compose -f compose.yaml -f compose.footer-dev.yaml up -d --no-build
```

В локальном режиме редактируйте файл и обновляйте страницу; пересборка образа не нужна. Не удаляйте исходное указание авторства; штатная проверка EspoCRM остаётся без изменений и может восстановить подпись, если footer пустой.

Этот override только отключает `useCache` для локальной разработки (альтернатива — `CRM_USE_CACHE=false` в `.env`). Обычный запуск без override по умолчанию включает кэш; на сервере оставьте `CRM_USE_CACHE=true`. После правок шаблона на сервере выполните `docker compose restart crm` и обновите страницу: при старте обновляется timestamp ресурсов. При использовании своего проекта/override-файлов укажите их и в этих командах.

- Раздел Credits доступен всем вошедшим пользователям, независимо от бизнес-прав роли. Он содержит указание авторства EspoCRM и сведения об изменённой версии onlineCRM. По запросу владельца разделы лицензии, гарантий и исходного кода убраны со страницы; неизменённый текст AGPL сохранён в `client/custom/modules/studio-management/legal/AGPL-3.0.txt` и `LICENSE.txt`.
- Credits находится только в списке вкладок слева, без дублирующей ссылки сверху. На широком экране настоящее штатное уведомление «© 2026 EspoCRM, Inc.» отображается внизу левой панели над кнопкой сворачивания; в свёрнутом меню оно переносится на несколько строк, без обрезки текста. Его содержимое, ссылка и проверки ядра не изменены. На узком экране или при недоступной боковой панели остаётся обычное видимое уведомление снизу; после выхода из аккаунта восстанавливается штатное расположение.
- При сетевом размещении оператору отдельно необходимо предложить пользователям соответствующие исходники реально запущенной версии (§13 AGPL). Ссылка на исходный EspoCRM не заменяет код изменений onlineCRM. Этот экран не является юридическим заключением и не отменяет прочих условий лицензии.
- Проверка: `node extensions/studio-management/tests/CreditsClientTest.cjs`.

## Динамические роли (22.10.2026)

Проверка handoff `4c746dd2ef`:

- Настоящий администратор получает явные уровни CRUD раздела Role через `adminMandatory`, независимо от делегированного права.
- Для остальных управление ролями по-прежнему требует `SmRoleAdministration`. Для списка Role используется штатный булев фильтр доступа: разрешение без уровней `read` больше не приводит к пустому списку.
- Клиентский контроллер Team проверяет выданную CRUD-матрицу: пользователю с полными правами больше не возвращается 403 при открытии формы создания/изменения группы. Серверные и построчные проверки сохраняются.
- Docker применяет настройки Studio после штатной установки/миграции EspoCRM; студийные вкладки появляются и на первом запуске с пустыми томами.
- Регрессии: `node extensions/studio-management/tests/HandoffAccessClientTest.cjs` и `node extensions/studio-management/tests/HandoffAccessHttpTest.cjs --role-id <ID>` (только отдельная localhost-база). Для HTTP-проверки сначала создайте в интерфейсе роль `Handoff unrestricted UI test`, нажмите «Снять все ограничения» и сохраните её. Тест регистрирует обычный аккаунт с этой ролью и проверяет видимость/CRUD ролей, создание полной группы и цикл смены. Фикстуры отмечены уникальным префиксом в `data/tmp/handoff-access-fixtures.json`; не запускайте на рабочей базе.

- «Роли → Создать роль»: произвольные названия и количество ролей, поиск и штатная пагинация.
- Тип аккаунта — бизнес-категория для состава группы и выплат, не ограничение выдачи ACL.
- Панель «Права студии»: подготовка, запуск/подсчёт и корректировка смен, сотрудники, аналитика, ссылки регистрации.
- «Как раньше» сохраняет прежние правила; явные уровни «Все записи», «Свой продакшен», «Свои смены», «Запрещено» их переопределяют. Дополнительно задайте штатную матрицу прав разделов/полей (например, изменение Смен).
- Несколько ролей объединяются по штатному правилу EspoCRM: побеждает наиболее разрешающий уровень. «Запрещено» в одной роли не отменяет разрешение другой.
- В матрице разделов есть «Управление ролями (полный доступ)»: опасное делегируемое право создавать и менять любые роли, независимо от типа аккаунта.
- Управление ссылками регистрации тоже опасно: можно выдавать любые роли. Каталог приглашения возвращает только ID/названия и не открывает редактирование ролей.
- Список выбора роли теперь открывает поиск с пагинацией, а не только первые 20 результатов автодополнения.
- Права сервера обновляются на следующих запросах действующей сессии. Для обновления уже нарисованных кнопок перезагрузите страницу.
- Вкладка браузера использует onlineCRM и прозрачный значок. Видимая подпись EspoCRM сохранена согласно лицензии.
- Исправлены гонки записи state.php и чтения очищаемого ACL-кеша при параллельном сохранении ролей, через расширения модуля без изменения ядра.

Проверки (только отдельная localhost-база):

```sh
node extensions/studio-management/tests/DynamicRolesClientTest.cjs
node extensions/studio-management/tests/DynamicRolesHttpTest.cjs
docker compose cp extensions/studio-management/tests/CleanupDynamicRoles.php crm:/tmp/CleanupDynamicRoles.php
docker compose cp data/tmp/dynamic-roles-fixtures.json crm:/tmp/dynamic-roles-fixtures.json
docker compose exec -T crm php /tmp/CleanupDynamicRoles.php
```

HTTP-тест создаёт 400 ролей, проверяет параллельное сохранение, поиск/страницы, делегированное управление ролями, приглашения, все пять типов аккаунтов, реальный цикл смены, выдачу и отзыв прав. Фикстуры остаются до явной мягкой очистки указанной командой.
