# Интеграция с платёжной системой курса (ТЗ §8, §10)

> Платёжная система — это **отдельный проект преподавателя** «Training Payment System»
> (Mikhail Kramer, GitHub `mike-kramer`). Наш репозиторий был форком этого проекта, поэтому
> **весь код платёжки есть в нашей истории git** (коммит `0224724`). Всё ниже взято прямо из
> этого кода, а не придумано.
>
> - ТЗ самой платёжки: https://docs.google.com/document/d/11eqsxuavwpzYEZJjuhYl0OcH8CObd8zxePcz1771Gzg/edit
> - Репозиторий преподавателя: **https://github.com/mike-kramer/training-ps**
> - Посмотреть код локально: `git show 0224724:application/app/Services/PaymentProcessingService.php`
>   (или свежий: `git fetch upstream && git show upstream/master:<путь>`, см. `CLAUDE.md`)
>
> ✅ **Сверено с `upstream/master` на коммите `664301c` (2026-10-02).** Код создания платежа, подписи,
> вебхука и отмены старых платежей с момента форка **не менялся**. Если на защите что-то не сходится,
> сначала проверь, не обновил ли преподаватель эти файлы:
> `git diff 0224724 upstream/master -- application/app/Services/Payment* application/app/Services/SignatureService.php`

---

## 1. Как работает платёжка (глазами нашего backend)

```mermaid
sequenceDiagram
    autonumber
    participant F as Frontend
    participant B as Наш backend
    participant P as Платёжная система
    participant U as Пользователь (браузер)

    F->>B: POST /api/events/5/orders {items}
    B->>B: транзакция: проверки, заказ pending, резерв sold_count
    B-->>F: 201 {success, data: заказ}
    F->>B: POST /api/orders/123/pay
    B->>P: POST /api/create-payment {cashbox_id, order_id: UUID, description, amount} + X-Signature
    P-->>B: 201 {"data": {"url": ".../process-payment/15"}}
    B->>B: orders.payment_reference = UUID, orders.payment_url = url
    B-->>F: 200 {success, data: {payment_url}}
    F->>U: редирект на payment_url
    U->>P: нажимает «Success» или «Failure»
    P-->>U: редирект на success_url или fail_url кассы (страница фронта)
    P->>B: (из очереди) POST /api/payments/webhook {order_id, amount, status} + X-Signature
    B->>B: проверка подписи, поиск заказа по payment_reference, идемпотентность
    B->>B: status=1 → paid (🛠 дальше: OrderPaid → билеты + письмо); status=2 → cancelled, резерв снят
    B-->>P: 200
    F->>B: GET /api/orders/123 (опрос статуса). Фронт сам об успехе НЕ решает
```

### Понятия платёжки

| Понятие | Что это |
|---|---|
| **Касса (cashbox)** | «Магазин» внутри платёжки. У неё есть `id`, `secret_key` (20 символов), `webhook_url`, `success_url`, `fail_url`. Создаётся владельцем через UI платёжки (`/cashboxes`) |
| **secret_key** | общий секрет для подписи HMAC. Узнать: `POST /api/cashboxes/{id}/reveal-secret` с паролем (или кнопка в UI). **Хранить только в `.env`, не коммитить** |
| **Платёж (payment)** | `id`, `cashbox_id`, `order_id` (строка, **уникален в пределах кассы**), `amount` (целое, **в тийинах**), `description`, `status` |
| **Статусы платежа** | `0` = pending, `1` = paid, `2` = failed |

## 2. Создание платежа

```
POST {PAYMENT_URL}/api/create-payment
Content-Type: application/json
Accept: application/json
X-Signature: <hmac>
```

Тело: **ровно 4 поля**.

```json
{ "cashbox_id": 1, "order_id": "9b1c...-uuid", "description": "Заказ #123", "amount": 45000000 }
```

У нас `order_id` — это UUID из `orders.payment_reference`, а не id заказа (почему — в §5).

| Поле | Правило в платёжке |
|---|---|
| `cashbox_id` | `required, integer, exists:cashboxes` |
| `amount` | `required, integer, min:1`, **в тийинах**: 450 000 сум → `45000000` |
| `description` | `required, string` |
| `order_id` | `required`, **уникален для кассы**: второй платёж с тем же `order_id` даст 422 |

Ответы:

| Код | Тело | Значит |
|---|---|---|
| 201 | `{"data": {"url": "http://<платёжка>/process-payment/15"}}` | ссылка на оплату, её отдаём фронту |
| 400 | `{"success": false, "message": "Invalid signature"}` | неверная подпись (не тот ключ или не те поля) |
| 422 | ошибки валидации | например, `order_id` уже использован или `amount` = 0 |
| 500 | — | прислали **лишнее поле**: платёжка делает `new PaymentData(...$request->all())`, лишний ключ ломает конструктор |

> ⚠️ **Ловушки:**
> - **Сумма ×100.** У нас `decimal` в сумах, платёжке нужно целое в тийинах: `(int) round($order->total_amount * 100)`.
> - **Один заказ — один платёж.** Повторно создать платёж с тем же `order_id` нельзя. У нас это решено так: повторный `/pay` отдаёт сохранённую ссылку.
> - **Бесплатные билеты.** `amount` = 0 не пройдёт (`min:1`). Бесплатный заказ надо сразу помечать `paid`, без платёжки. ⚠️ У нас пока не сделано: `/pay` по заказу на 0 сум вернёт 502.
> - **Ссылка строится из `APP_URL` платёжки.** Если там стоит не тот адрес, ссылка будет битая.

## 3. Подпись (HMAC-SHA256), самое важное

Алгоритм из `SignatureService` преподавателя:
1. Отсортировать поля по **ключу** (`ksort`).
2. Склеить в строку `ключ|значение|ключ|значение|...`.
3. Посчитать `hash_hmac('sha256', строка, secret_key)` и получить hex-строку из 64 символов.

```php
public function sign(array $data, string $key): string
{
    ksort($data);
    $string = implode("|", array_map(
        fn($key, $val) => "{$key}|{$val}",
        array_keys($data),
        array_values($data)
    ));
    return hash_hmac("sha256", $string, $key);
}

public function checkSignature(array $data, string $key, string $controlSignature): bool
{
    return hash_equals($controlSignature, $this->sign($data, $key)); // hash_equals защищает от timing-атаки
}
```

**Проверочный пример** (можно использовать в unit-тесте):

```
data   = {cashbox_id: 1, order_id: "123", description: "Заказ №123", amount: 45000000}
key    = "secret-key-example"
строка = "amount|45000000|cashbox_id|1|description|Заказ №123|order_id|123"
hmac   = 27ed109ace0ef17242e9934a086a03e38b7a085caaa497c12c88da8708a3d713
```

Подписываются **ровно те поля, что отправляются**: при создании все 4, у вебхука 3 (`order_id`, `amount`, `status`).
Не передавайте `true`, `false` или `null`: в строке они превратятся в `1` или пустоту, и подписи разойдутся.

## 4. Вебхук от платёжки

Когда платёж сменил статус (пользователь нажал кнопку, или прошло 15 минут), платёжка **из очереди** шлёт запрос:

```
POST {webhook_url кассы}            ← у нас: /api/payments/webhook
Content-Type: application/json
X-Signature: hmac({order_id, amount, status}, secret_key)

{ "order_id": "123", "amount": 45000000, "status": 1 }
```

| Поведение платёжки | Что это значит для нас |
|---|---|
| Ответ 2xx | доставлено, больше не шлёт |
| Ответ 4xx | **повторов не будет** (записывает в лог `webhook-failed-with-40x-code`) |
| Ответ 5xx или нет соединения | повторяет до 5 раз через 1, 2, 4, 8 минут |
| Платёж `pending` дольше 15 минут | команда `app:cancel-old-payments` (раз в 5 минут) ставит `failed` и шлёт вебхук со `status: 2` |
| Статус уже не `pending` | второй раз не меняется. Но **дубли вебхука возможны** из-за повторов, поэтому нужна идемпотентность |

### Как обрабатываем у нас (сделано в PR #14)

```
PaymentWebhookController (__invoke)
1. PaymentWebhookRequest: order_id (required|string), amount (required|integer), status (required|integer|in:1,2)
      → toDTO() → PaymentWebhookData. Невалидно → 422
2. PaymentWebhookService::handle():
   a. Подпись: checkSignature((array) $data, config('services.payment.secret'), X-Signature)
         неверная → InvalidSignatureException → контроллер отдаёт 403. 4xx = платёжка не повторяет мусор
   b. DB::transaction:
         Order::where('payment_reference', $data->order_id)->lockForUpdate()->firstOrFail()   нет → 404
   c. Идемпотентность: заказ уже не pending → выходим, ответ 200, ничего не меняем
   d. status = 1 → order.status = paid
      status = 2 → order.status = cancelled, sold_count -= quantity по каждой позиции (резерв снят)
3. Ответ 200 {"success": true}
```

🛠 Ещё не сделано (следующие шаги):
- **сверка суммы**: `amount` из вебхука не сравнивается с `total_amount × 100` (недочёт в `05-PROGRESS.md`);
- **событие `OrderPaid`** после коммита → `GenerateTickets` (билеты `EVT-XXXXXX`), `SendPurchaseConfirmation` (письмо, `ShouldQueue`), опционально `NotifyOrganiser`;
- оплата пришла по уже `expired` заказу → `refund_required = true` (когда появится просрочка).

## 5. Как это сделано в нашем коде (PR #14)

**Конфиг** (`config/services.php` и `.env`, в `.env.example` пустые значения):

```php
'payment' => [
    'url' => env('PAYMENT_URL'),               // http://host.docker.internal:8092 (из контейнера)
    'cashbox_id' => (int) env('PAYMENT_CASHBOX_ID'),
    'secret' => env('PAYMENT_SECRET'),
],
```

**Файлы:**

| Файл | Роль |
|---|---|
| `app/Contracts/SignatureContract.php`, `app/Services/SignatureService.php` | подпись HMAC, один в один как у преподавателя. Привязка `singleton` в `AppServiceProvider` |
| `app/Data/Payments/PaymentData.php` | DTO запроса в платёжку: `cashbox_id`, `order_id`, `description`, `amount` (как `PaymentData` преподавателя) |
| `app/Services/PaymentCreationService.php` | создаёт платёж: проверки, подпись, `Http::post(.../api/create-payment)->throw()`, сохраняет ссылку в заказ |
| `app/Http/Controllers/Payment/PaymentCreationController.php` | `POST /orders/{order}/pay`, права `can:pay,order` (`OrderPolicy::pay` — только владелец) |
| `app/Exceptions/PaymentSystemException.php` | платёжка недоступна или ответила ошибкой → контроллер отдаёт 502 |
| `app/Http/Requests/Payment/PaymentWebhookRequest.php`, `app/Data/Payments/PaymentWebhookData.php` | валидация вебхука и DTO |
| `app/Services/PaymentWebhookService.php` | обработка вебхука (схема выше) |
| `app/Http/Controllers/Payment/PaymentWebhookController.php` | `POST /payments/webhook`, публичный маршрут |
| `app/Exceptions/InvalidSignatureException.php` | неверная подпись → 403 |
| миграция `2026_10_07_..._add_payment_fields_to_orders_table` | `orders.payment_reference` (string, nullable, **unique**), `orders.payment_url` (string, nullable) |
| `tests/Feature/PaymentTest.php` | 11 тестов (список ниже) |

**Ключевые решения (и как их объяснить):**
- **Оплата отдельным запросом `POST /orders/{order}/pay`**, а не внутри создания заказа.
  - Заказ создаётся в быстрой транзакции. HTTP-запрос к внешней системе идёт **вне** транзакции, и блокировки `lockForUpdate` не висят, пока ждём платёжку.
  - Если платёжка упала (502), заказ и резерв не теряются: фронт просто повторяет `/pay`.
  - ТЗ §8 говорит «вернуть ссылку на оплату», а §19 разрешает самим выбрать структуру API. Для фронта это два вызова подряд.
- **Без отдельной таблицы `payments`.** У заказа один платёж, поэтому хватает двух полей в `orders`. Отдельная таблица понадобится, если будет несколько попыток оплаты или возвраты.
- **`order_id` для платёжки — это UUID, а не id заказа.** Хранится в `orders.payment_reference`. Платёжка требует, чтобы `order_id` был **уникален в пределах кассы**. Если пересоздать нашу БД (`migrate:fresh`, тесты), id заказов начнутся с 1 заново, и платёжка ответит 422 «order_id уже занят». С UUID такого не бывает, а ещё снаружи нельзя угадать номер чужого заказа.
- **Повторный `/pay` отдаёт ту же ссылку** (`payment_url` уже сохранён), второй платёж не создаётся.
- **Оплатить можно только `pending`-заказ**, иначе 422.
- **Сумма в тийинах:** `(int) round((float) $order->total_amount * 100)`. `round` нужен, чтобы `0.29 * 100 = 28.999…` не превратилось в 28.
- **Неверная подпись → 403**, неизвестный заказ → 404. Оба кода 4xx, на них платёжка повтор не делает.
- **Идемпотентность вебхука:** заказ уже не `pending` → 200 без изменений. `lockForUpdate` защищает от двух одновременных вебхуков.
- **Отмена при `status = 2` снимает резерв** (`sold_count -= quantity`). Платёжка сама ставит `failed` неоплаченным платежам через 15 минут и шлёт вебхук, поэтому **заказ, по которому нажали «Оплатить», освободит билеты сам**. Заказ, по которому `/pay` так и не вызвали, пока висит вечно: нужна своя команда просрочки.
- **Источник истины — только вебхук.** `success_url` ведёт на страницу фронта, которая **опрашивает** `GET /api/orders/{id}`. Сам факт редиректа ничего не доказывает (ТЗ §10).
- **Секрет хранится в `.env`.** В тестах он задаётся через `config([...])`.

**Тесты** (`tests/Feature/PaymentTest.php`, 11 штук):
`testParticipantGetsPaymentUrl` (ссылка, сумма ×100 и подпись в запросе через `Http::assertSent`), `testRepeatedPayReturnsSameUrl`, `testCannotPayForeignOrder` (403), `testGuestCannotPay` (401), `testCannotPayNotPendingOrder` (422), `testPaymentSystemErrorReturns502`, `testSuccessfulWebhookMarksOrderPaid`, `testFailedWebhookCancelsOrderAndReleasesTickets`, `testWebhookWithInvalidSignatureIsRejected` (403), `testWebhookForUnknownOrderReturns404`, `testRepeatedWebhookDoesNotChangeProcessedOrder`.

## 6. Как поднять платёжку локально рядом с нашим проектом

1. Склонировать репозиторий преподавателя в **отдельную папку**: `cd docker && cp .env.example .env && docker compose up -d`, потом `docker compose exec -u www-data php composer install`, `php artisan migrate`.
2. ⚠️ **Порты.** `docker/.env.example` у нас и у преподавателя одинаковые (8092 / 2505 / 2506). В одном из проектов поменяйте их, например у нас на 8093 / 2507 / 2508.
3. В `application/.env` платёжки задать `APP_URL=http://localhost:<её порт>`, иначе ссылка оплаты будет неверной.
4. В платёжке **запустить очередь и планировщик**, иначе вебхуки не уйдут:
   `docker compose exec -u www-data php php artisan queue:work` и `... php artisan schedule:work`.
5. Зарегистрироваться в UI платёжки, войти, открыть `/cashboxes` и создать кассу:
   - `webhook_url` = `http://host.docker.internal:<наш порт>/api/payments/webhook` (запрос идёт **из контейнера** платёжки, поэтому не `localhost`);
   - `success_url` и `fail_url` = страницы нашего фронта (пока можно любые).
6. Узнать `secret_key` (reveal-secret), взять `cashbox_id`, вписать в **наш** `application/.env`:
   `PAYMENT_URL=http://host.docker.internal:<порт платёжки>`, `PAYMENT_CASHBOX_ID`, `PAYMENT_SECRET`.
