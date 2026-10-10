# Прогресс по ТЗ и план работ

> **Живой документ.** После каждой завершённой фичи обновляй таблицу и раздел «Что дальше».
> Последнее обновление: 2026-10-10 (сверено с `master` после PR #14 «Интеграция с платёжной системой»).
>
> ✅ сделано · 🟡 частично · ❌ не начато

## Сводка по разделам ТЗ

| § | Раздел | Статус | Где в коде или что осталось |
|---|---|:-:|---|
| 2 | Роли (admin / organiser / participant) | ✅ | `Enums/UserRole`, `users.role`, хелперы `isAdmin()` и др. |
| 3 | Регистрация, логин, logout, `/me` | ✅ | `AuthController`, `AuthService`, Sanctum (token и cookie), подтверждение email кодом, rate limit |
| 3 | Изменение профиля и пароля | ✅ | `Profile/*Controller`, `ProfileService` (`PATCH /me/name`, `/me/password`) |
| 2/3 | Админ: смена роли, блокировка | ✅ | `Admin/UserRoleChangeController`, `Admin/UserBlockController`, `UserAdminService`, `UserPolicy::manage` |
| 2/15 | Админ: список пользователей с пагинацией | ✅ | `GET /admin/users` → `Admin/UserListController`, `UserAdminService::getList` (по 15). Фильтров пока нет |
| 4 | CRUD мероприятий, статусы, publish, cancel | ✅ | `Event/*Controller`, `EventService`, `EventPolicy`, `Enums/EventStatus` |
| 4 | Видимость: чужой черновик не виден | ✅ | `EventPolicy::view` + `EventShowController` (404) и `EventSearchService` |
| 4 | Запрет менять дату или место после продаж | ❌ | `EventService::update` пока не проверяет `sold_count` |
| 4 | Статус `finished` | ❌ | нет автоматического перехода (Scheduler) |
| 5 | Категории (CRUD админа) | ✅ | `Category/*Controller`, `CategoryService`, `CategoryPolicy` |
| 6 | Места проведения (CRUD админа) | ✅ | `Venue/*Controller`, `VenueService`, `VenuePolicy` |
| 7 | Типы билетов, автоматический остаток | ✅ | `TicketType/*Controller`, `TicketTypeService`, `TicketType::availableCount()` |
| 8 | Покупка: проверки, заказ, резерв | ✅ | `OrderCreationService::create` (транзакция и `lockForUpdate`) |
| 8 | Платёжная операция и **ссылка на оплату** | ✅ | отдельным запросом `POST /orders/{order}/pay` → `PaymentCreationService` (подробно в `04-PAYMENT.md` §5) |
| 9 | Заказы: состав, статусы, просмотр по ролям | 🟡 | `OrderListService`, `OrderShowController`, `OrderPolicy::view`. Переходы `pending → paid` и `pending → cancelled` делает вебхук. Нет `expired` |
| 10 | Вебхук и проверка подписи | ✅ | `POST /payments/webhook` → `PaymentWebhookService`: HMAC (`SignatureService`), идемпотентность, `lockForUpdate`. Нет сверки суммы (недочёт №5) |
| 11 | Билеты (`EVT-XXXXXX`) после оплаты | ❌ | таблица `tickets`, `GenerateTickets` |
| 12 | Регистрация участника, защита от повтора | ❌ | `ticket_registrations` (`ticket_id` unique) |
| 13 | Check-in билета организатором | ❌ | `POST /events/{event}/tickets/{ticket}/check-in` |
| 14 | Поиск, фильтры, сортировка | ✅ | `EventListController` → `EventSearchService`, `EventListRequest` → `EventFilterData` |
| 15 | Пагинация: мероприятия, заказы, пользователи | ✅ | `paginate()` в `EventSearchService`, `OrderListService`, `UserAdminService` |
| 15 | Пагинация: билеты | ❌ | вместе с билетами |
| 16 | Очереди: письмо о покупке, напоминание за 24 ч | ❌ | `ShouldQueue` listeners и Scheduler |
| 17 | События: EventPublished, TicketPurchased, OrderPaid, EventCancelled, EventStarted | ❌ | `app/Events` и listeners |
| 18 | Отмена мероприятия → уведомления и `refund_required` | 🟡 | сама отмена есть (`EventService::cancel`). Нет уведомлений и флага возврата |
| 2 | Участник отменяет билет «по правилам» | ❌ | правило, например не позже чем за 24 ч до начала |
| 2 | Организатор: участники и продажи своих мероприятий | 🟡 | заказы своих мероприятий видны. Нет списка регистраций и сводки продаж |
| 20 | Валидация через Form Request | ✅ | у всех действий с телом запроса, данные в сервис идут через `toDTO()` |
| 21 | Policies | ✅ | 6 policies, подключены в маршрутах через middleware `can:` |

**Тесты:** 124 Feature-теста в 18 файлах (`tests/Feature`), из них 11 на оплату (`PaymentTest`). На каждую фичу есть успешный сценарий, проверки прав (401/403) и валидации (422).

## Найденные недочёты в текущем коде (исправить по пути)

1. **`routes/web.php` ссылается на удалённый шаблон.** `Route::view('/{any?}', 'app')`, а `resources/views/app.blade.php` удалён вместе с фронтом платёжки. Любой не-API запрос (например, открыть `http://localhost:8092/` в браузере) даст 500 «View [app] not found». Исправление: удалить этот маршрут или вернуть простой ответ.
2. **Можно менять `starts_at`, `ends_at`, `venue_id` после продаж** (нарушает ТЗ §4). Проверка нужна в `EventService::update`.
3. **Можно поставить `quantity` типа билета меньше `sold_count`.** Нужна проверка в `TicketTypeService::update` или в `TicketTypeRequest`.
4. **Резерв не снимается, если оплату так и не начали.** Если вызвали `/pay`, платёжка через 15 минут сама отменит платёж и пришлёт вебхук `status=2`, и резерв снимется. Но если `/pay` не вызывали, заказ `pending` висит вечно. Нужны `orders.expires_at` и своя команда `orders:expire` в Scheduler.
5. **Вебхук не сверяет сумму.** `amount` из вебхука не сравнивается с `total_amount × 100`. Подпись защищает от подделки, но сверка — дешёвая дополнительная проверка (и её легко показать на защите).
6. **Бесплатный заказ нельзя «оплатить».** Если все билеты по 0 сум, `/pay` отправит `amount = 0`, платёжка ответит 422 (`min:1`), и мы вернём 502. Бесплатный заказ надо сразу переводить в `paid`, без платёжки.
7. **Два одновременных `/pay` могут создать два платежа.** Проверка `payment_url !== null` идёт без блокировки. На практике редкость (двойной клик), лечится `lockForUpdate` на заказе.
8. **Нет секрета в `.env` → 500.** Если `PAYMENT_SECRET` не задан, `config('services.payment.secret')` вернёт `null`, и `sign()` упадёт с TypeError. Можно проверять конфиг и отвечать понятной ошибкой.
9. **Удаление сущностей, на которые есть ссылки**, падает с 500 (ошибка FK): мероприятие с заказами, категория или место с мероприятиями, тип билета с заказами. Нужно отдавать понятную 422 или запрещать.
10. **Типы билетов черновика видны всем.** `GET /events/{event}/ticket-types` не проверяет `EventPolicy::view`, хотя само мероприятие (`GET /events/{event}`) уже скрыто. Сделать так же, как в `EventShowController`.
11. **Регистрация с лишним полем падает с 500.** `AuthRegistration::toDTO()` делает `new RegistrationData(...$this->all())`: любое поле кроме `email` и `password` (например, `name` или `password_confirmation` от фронта) даёт ошибку «Unknown named parameter». В `LoginRequest` то же самое, но там ошибку маскирует `try/catch` в `AuthController::login` («invalid credentials»). Исправление: `...$this->validated()`, как в остальных FormRequest.
12. **`SendVerificationEmail` не в очереди.** Импортирует `ShouldQueue`, но не реализует его.
13. **Заблокированный пользователь продолжает работать со старым токеном**: блокировка проверяется только при логине (`AuthService::login`). Нужен middleware, как `BlockBannedUsersMiddleware` у преподавателя, или удаление токенов при блокировке.
14. **Docker и `.env`.** `docker-compose.yml` передаёт `DB_NAME`/`DB_USER`, а Laravel читает `DB_DATABASE`/`DB_USERNAME`. В `application/.env` их надо прописать вручную (`DB_CONNECTION=pgsql`, `DB_HOST=db`).

**Исправлено рефакторингом (PR #11–#13):**
- ~~остаток платёжки `app:cancel-old-payments` в Scheduler~~: блок `withSchedule` удалён из `bootstrap/app.php`;
- ~~нет маршрута `GET /admin/users`~~: добавлен, с пагинацией;
- ~~`GET /events/{event}` отдаёт чужой черновик~~: теперь 404 через `EventPolicy::view`;
- ~~маршруты типов билетов не «скоупятся»~~: `->scopeBindings()` на update и delete;
- ~~фронт платёжки преподавателя в `resources/js`~~: удалён вместе с `package.json` и `vite.config.js`.

## Что дальше (рекомендуемый порядок)

1. **Билеты** (§11). Таблица `tickets`, событие `OrderPaid` (вызвать в `PaymentWebhookService` после коммита) и listener `GenerateTickets`, `GET /tickets`, `GET /tickets/{ticket}`.
2. **Дожать оплату.** `expires_at` и `orders:expire` (вернуть `withSchedule` в `bootstrap/app.php`), сверка суммы в вебхуке, бесплатные заказы (недочёты №4–6).
3. **События и очереди** (§16, §17). `OrderPaid`, `TicketPurchased`, `EventPublished`, `EventCancelled`, `EventStarted`. Письма через `ShouldQueue`. Напоминание за 24 ч через Scheduler.
4. **Отмена мероприятия** (§18). `EventCancelled` → `NotifyTicketHolders` и `refund_required = true` у оплаченных.
5. **Регистрация и check-in** (§12, §13). Уникальность и запрет повторного использования.
6. **Долги** из списка недочётов выше. Быстрые: №1 (web.php), №11 (`validated()`), №10 (видимость типов билетов). Потом: запрет изменения даты после продаж, `finished`, отмена билета участником, блокировка по токену.
7. **Подготовка к защите.** Сидер с демо-данными (админ, организатор, участник, мероприятия), Postman-коллекция, README с запуском.
8. **Фронтенд** (по желанию, не входит в ТЗ).

## История (PR)

| PR | Ветка | Что сделано |
|---|---|---|
| #2 | — | роли пользователей, `/me`, смена пароля и имени, смена роли админом |
| #3 | feature/block-users | блокировка пользователей и проверка при логине |
| #4 | feature/categories-venues | CRUD категорий |
| #5 | feature/venues | CRUD мест проведения |
| #6 | feature/events | CRUD мероприятий, Policy, Form Request, Enum |
| #7 | feature/event-status | публикация и отмена мероприятий |
| #8 | feature/ticket-types | типы билетов |
| #9 | feature/event-search | фильтры, поиск, сортировка |
| #10 | feature/orders | заказы и резервирование билетов |
| #11 | refactor/orders | рефакторинг заказов: однометодные контроллеры, `OrderCreationService`, `OrderListService`, DTO |
| #12 | refactor/single-action | рефакторинг категорий, мест, типов билетов, профиля, админки, мероприятий. Авторизация через `can:` в маршрутах, закрыт показ чужих черновиков, `GET /admin/users`, `PUT` вместо `PATCH` для полного обновления |
| #13 | refactor/single-action | очистка: удалены старые контроллеры, дубль маршрута, фронт платёжки |
| #14 | feature/payments-addings | интеграция с платёжной системой: `POST /orders/{order}/pay`, вебхук с подписью HMAC, `payment_reference`/`payment_url` в заказах, 11 тестов |
