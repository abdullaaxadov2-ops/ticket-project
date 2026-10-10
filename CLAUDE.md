# CLAUDE.md: инструкции для Claude по проекту Ticket Project

> Claude Code читает этот файл автоматически в начале каждой сессии.
> Если ты Claude в новом окне, прочитай его целиком, прежде чем что-то делать.

## Кто я и как со мной работать

- Я студент курса по Laravel. Это **Проект №2: backend платформы продажи билетов на мероприятия**. Его нужно сдать и **защитить**.
- **Отвечай на русском.** Код, имена классов и переменных пиши на английском.
- **Объясняй решения**: что делаем, почему так, какая альтернатива. Я должен понимать и уметь пересказать каждую строку на защите.
- Если вариантов несколько, **дай варианты с рекомендацией** и не начинай большие изменения без моего «да».
- Не выдумывай требования. Источник правды — ТЗ (`docs/01-TZ.md`). Если ТЗ разрешает решить самим (§19), предложи и объясни.
- После завершения фичи **обнови `docs/05-PROGRESS.md`** (статус и «Что дальше»), а при изменении API — `docs/03-API.md`.

## Документация (читать по необходимости)

| Файл | Что внутри |
|---|---|
| `docs/01-TZ.md` | полное ТЗ (перенесено из PDF) |
| `docs/02-ARCHITECTURE.md` | слои, схема БД, статусы, роли, ключевые механизмы, образец преподавателя |
| `docs/03-API.md` | все эндпоинты: что сделано и что запланировано |
| `docs/04-PAYMENT.md` | **API платёжной системы преподавателя**, подпись HMAC, вебхук, план интеграции |
| `docs/05-PROGRESS.md` | статус по каждому разделу ТЗ, найденные недочёты, порядок работ |
| `docs/06-DEFENSE.md` | план защиты, демо, ответы на вопросы, глоссарий |

Основное, что нужно знать всегда:

@docs/05-PROGRESS.md

## Образец: проект преподавателя

- Наш репозиторий — **форк** «Training Payment System» преподавателя (Mikhail Kramer, GitHub `mike-kramer`).
  Ссылка: **https://github.com/mike-kramer/training-ps**
- Его код (платёжка: кассы, платежи, подпись, вебхуки, тесты) удалён у нас коммитом `63e298b`, но **лежит в истории git**
  на момент форка: `git show 0224724:<путь>`, список файлов: `git ls-tree -r --name-only 0224724 application/app`.
- **Преподаватель продолжает развивать репозиторий.** Свежий код смотри так:
  ```bash
  git remote add upstream https://github.com/mike-kramer/training-ps   # один раз
  git fetch upstream
  git show upstream/master:application/routes/api.php
  git log --oneline 0224724..upstream/master                           # что нового с момента форка
  ```
  **Не мержить `upstream/master` в наш проект**: там платёжка, а не наш код. Только читать как образец.
- **Стиль и паттерны берём оттуда**: Contracts и привязка в `AppServiceProvider`, `readonly` DTO и `toDTO()`, `__invoke`-контроллеры для одиночных действий, Jobs с повторами, команды с `#[Signature]`, Scheduler в `bootstrap/app.php`, тесты с `Http::fake()` и `$this->mock()`. Подробный список с путями — в `docs/02-ARCHITECTURE.md` §10.

## Стек

PHP 8.5 · Laravel 13 · Sanctum 4 · PostgreSQL 17 · Redis 8 · PHPUnit 12 · Pint · Docker Compose (nginx, php, db, redis, mailhog).
Laravel-приложение лежит в `application/`, окружение в `docker/`.

## Команды

```bash
# поднять окружение (из папки docker/)
cp .env.example .env && docker compose up -d
docker compose exec -u www-data php composer install
docker compose exec -u www-data php php artisan migrate

# тесты (используют схему test_schema в Postgres, см. phpunit.xml)
docker compose exec -u www-data php php artisan test
docker compose exec -u www-data php php artisan test --filter=OrderTest

# стиль, очереди, планировщик
docker compose exec -u www-data php ./vendor/bin/pint
docker compose exec -u www-data php php artisan queue:work
docker compose exec -u www-data php php artisan schedule:work
```

- В `application/.env` нужно задать `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (значения как в `docker/.env`). Compose передаёт `DB_NAME`/`DB_USER`, а Laravel их не читает.
- Письма смотреть в Mailhog: `http://localhost:8025`.
- В облачной среде Claude может не оказаться PHP 8.5 и Postgres. Тогда честно скажи, что тесты не запускались.

## Правила кода (как уже написан проект)

Эталонная фича для подражания: **категории** (`Category/*Controller`, `CategoryRequest`, `CategoryData`, `CategoryService`, `CategoryPolicy`, `CategoryTest`).

- **Маршруты.** Все в `routes/api.php`. Публичные GET сверху, остальные внутри `Route::middleware("auth:sanctum")->group(...)`. Один маршрут — один контроллер-класс.
- **Права** (ТЗ §21) проверяются **в маршруте** через middleware `can:` и Policy:
  `->middleware('can:update,event')` (с моделью из URL), `->middleware('can:create,App\Models\Event')` (без модели),
  `->middleware('can:create,App\Models\TicketType,event')` (класс + модель для Policy). В контроллерах `$this->authorize()` **не используем** (базовый `Controller` пустой).
  Исключение: публичный маршрут, где пользователь может быть гостем. Там `Gate::forUser($request->user('sanctum'))->denies('view', $event)` прямо в контроллере, и скрытую запись отдаём как 404 (см. `EventShowController`).
  В Policy используй хелперы `isAdmin()`, `isOrganiser()`, `isParticipant()`. Владелец определяется по `organizer_id === $user->id`.
- **Контроллеры однометодные** (`__invoke`) в папке сущности: `app/Http/Controllers/<Entity>/<Entity><Action>Controller.php`.
  Действия: `List`, `Show`, `Creation`, `Update`, `Deletion`, плюс особые (`Publication`, `Cancellation`). Исключение: `AuthController` (пока многометодный).
  Контроллер только принимает FormRequest, вызывает сервис и возвращает ответ.
- **Формат ответа:**
  - создание: `response()->json(['success' => true, 'data' => $model], 201)`;
  - изменение или действие: `['success' => true, 'data' => $model]`;
  - удаление: `['success' => true]` (код 200, не 204);
  - чтение: `['data' => ...]`;
  - список с пагинацией: возвращаем пагинатор как есть.
- **Валидация** только через FormRequest (ТЗ §20) в `app/Http/Requests/<Entity>/`. `authorize()` возвращает `true`. Один Request на create и update.
  Обновление делаем через **`PUT`**, все поля обязательны (без `sometimes`). `PATCH` только для изменения одного свойства (`/me/name`, `/admin/users/{user}/role`).
- **DTO.** FormRequest имеет метод `toDTO()`, который возвращает `new XxxData(...$this->validated())`. **Только `validated()`, не `all()`**: лишнее поле в `all()` ломает конструктор DTO.
  DTO — `readonly class` в `app/Data/<Entities>/<Entity>Data.php`, необязательные поля с `= null` в конце конструктора.
- **Сервисы** (`app/Services`): один на сущность (`CategoryService`: `getList`, `create`, `update`, `delete`). Сложную операцию выносим в отдельный сервис (`OrderCreationService`, `EventSearchService`, `OrderListService`).
  Сервис принимает модели и DTO, а не `Request`. Сохранение: `Model::create((array) $data)` или `$model->fill((array) $data)->save()`.
  Изменения, затрагивающие несколько таблиц или остатки, делай в `DB::transaction` и с `lockForUpdate()`.
- **Ошибки бизнес-правил**: в сервисе `throw ValidationException::withMessages(['поле' => 'Сообщение.'])`, это даёт 422. Сообщения пишем на русском.
- **Ошибки интеграций**: своё исключение в `app/Exceptions` (`PaymentSystemException`, `InvalidSignatureException`). Сервис бросает, контроллер ловит в `try/catch` и отдаёт `['success' => false, 'message' => ...]` с нужным кодом (502, 403). Образец: `Payment/PaymentCreationController`.
- **Модели.** `#[Fillable([...])]`, касты в методе `casts()`. Служебные поля (`status`, `organizer_id`, `event_id`, `sold_count`) не делай fillable, задавай явно.
- **Enums** (`app/Enums`) — string-backed, case в PascalCase, значения в snake/lowercase.
- **Деньги** храним в `decimal(10,2)` в сумах. Платёжке отправляем целое в тийинах: `(int) round((float) $amount * 100)`.
- **Внешние сервисы** через интерфейс в `app/Contracts` и привязку в `AppServiceProvider`. Конфиг в `config/services.php` и `.env`. Секреты никогда не коммить.
  Платёжка: `config('services.payment.url' | '.cashbox_id' | '.secret')` ← `PAYMENT_URL`, `PAYMENT_CASHBOX_ID`, `PAYMENT_SECRET`. HTTP-запросы через `Http::...->throw()`, в тестах `Http::fake()`.
- **Долгие операции** (письма, HTTP) выноси в очередь: `ShouldQueue` у listener или Job.
- **Комментарии** редкие, на русском, только там, где неочевидно «почему».

## Тесты

- Feature-тест на каждую фичу: `tests/Feature/<Фича>Test.php`, `use RefreshDatabase`, методы `testCamelCaseName(): void`.
- Покрывай: успешный сценарий, 401 (гость), 403 (чужая роль или чужая запись), 404 (скрытая или чужая вложенная запись), 422 (валидация и бизнес-правила), побочные эффекты (`assertDatabaseHas`, `fresh()->sold_count`).
- Авторизация в тестах: `$this->actingAs($user, 'sanctum')->postJson(...)`, обновление через `putJson`. Роль задаётся так: `User::factory()->create(['role' => UserRole::Organiser])`.
- Ответы проверяй с учётом обёртки: `assertJsonPath('data.status', 'pending')`.
- Внешнее подменяй: `Http::fake()`, `Mail::fake()`, `Queue::fake()`, `Event::fake()`.
- Подход TDD: сначала тест, потом код.

## Git

- Одна фича — одна ветка `feature/<name>` → PR в `master` → merge commit.
- Сообщения коммитов на русском, коротко о сути: «Логика заказов и резервирование билетов.».
- Не пушь в `master` напрямую и не переписывай историю.
