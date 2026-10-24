# Chipta: frontend

SPA на Vue 3 для нашего Laravel API. В ТЗ фронт не входит (§1), он нужен для демонстрации.

Стек: Vue 3, Vite, Vue Router, Pinia, Axios, Tailwind CSS. Язык: JavaScript.

## Что уже есть (этап 1: гость и участник)

| Страница | Адрес | Запросы к API |
|---|---|---|
| Каталог с поиском, фильтрами, сортировкой и пагинацией | `/` | `GET /events`, `GET /categories`, `GET /venues` |
| Мероприятие, выбор билетов, покупка | `/events/:id` | `GET /events/{id}`, `GET /events/{id}/ticket-types`, `POST /events/{id}/orders`, `POST /orders/{id}/pay` |
| Вход | `/login` | `GET /sanctum/csrf-cookie`, `POST /auth/login`, `GET /me` |
| Регистрация и код из письма | `/register`, `/verify` | `POST /auth/register`, `POST /auth/verify` |
| Мои заказы, заказ, оплата | `/orders`, `/orders/:id` | `GET /orders`, `GET /orders/{id}`, `POST /orders/{id}/pay` |
| Результат оплаты | `/payment/result` | `GET /orders/{id}` (опрос статуса) |
| Мои билеты, билет | `/tickets`, `/tickets/:id` | `GET /tickets`, `GET /tickets/{id}` |
| Профиль: имя и пароль | `/profile` | `PATCH /me/name`, `PATCH /me/password` |

Дальше по плану: этап 2 (организатор) и этап 3 (админ).

## Запуск в Docker (без Node.js на компьютере)

В `docker/docker-compose.yml` есть сервис `frontend` (образ `node:22-slim`). Он сам ставит зависимости и запускает Vite.

1. В `application/.env` добавить порт фронта в `SANCTUM_STATEFUL_DOMAINS` (см. шаг 1 ниже) и выполнить `php artisan config:clear` в контейнере `php`.
2. `cd docker && docker compose up -d`. Первый запуск дольше обычного: идёт `npm install`.
3. Открыть **http://localhost:5173**. Логи фронта: `docker compose logs -f frontend`.

`node_modules` лежат в отдельном томе Docker, а не в папке `frontend/`: так установка быстрее на Windows и не смешиваются библиотеки для Windows и Linux.

## Запуск с Node.js на компьютере

Нужен Node.js **20.19+ или 22.12+** (проверить: `node -v`) и запущенный бэкенд (`docker compose up -d` в папке `docker/`).

1. **Один раз настроить бэкенд.** В `application/.env` добавить порт фронта в `SANCTUM_STATEFUL_DOMAINS`:
   ```
   SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8092,localhost:5173,127.0.0.1,127.0.0.1:8092,127.0.0.1:5173
   ```
   Потом сбросить кеш конфига: `docker compose exec -u www-data php php artisan config:clear`.
2. **Установить зависимости и запустить:**
   ```bash
   cd frontend
   npm install
   npm run dev
   ```
3. Открыть **http://localhost:5173**.

Если бэкенд работает не на порту 8092 (`NGINX_PORT` в `docker/.env`), скопируй `.env.example` в `.env` и поменяй `VITE_BACKEND_URL`.

**Оплата.** В кассе платёжной системы преподавателя (`/cashboxes`) укажи `success_url` и `fail_url` = `http://localhost:5173/payment/result`.

## Как это устроено

**Один адрес для браузера.** Vite в режиме разработки проксирует `/api` и `/sanctum` на бэкенд (`vite.config.js`). Браузер думает, что фронт и API живут на `localhost:5173`, поэтому CORS не нужен, а cookie сессии общие.

**Вход через cookie (Sanctum SPA).**
1. `GET /sanctum/csrf-cookie`: Laravel ставит cookie `XSRF-TOKEN`.
2. `POST /api/auth/login`: Axios сам кладёт токен в заголовок `X-XSRF-TOKEN`. `AuthController::login` по заголовку `Origin` видит, что запрос пришёл с адреса из `SANCTUM_STATEFUL_DOMAINS` (`fromFrontend`), и вместо Bearer-токена создаёт сессию.
3. Дальше браузер сам отправляет cookie сессии с каждым запросом. Токен не хранится в JS, поэтому XSS не сможет его украсть.

**Оплата.** Покупка состоит из двух запросов: создать заказ и получить ссылку на оплату. Перед уходом на страницу платёжки фронт запоминает номер заказа в `sessionStorage`. Платёжка возвращает пользователя на `/payment/result`, и фронт раз в 2 секунды запрашивает `GET /orders/{id}`, пока статус не сменится с `pending`. Сам редирект ничего не доказывает: об оплате бэкенд узнаёт только из подписанного вебхука (ТЗ §10).

## Структура

```
src/
├── main.js              точка входа: Pinia, роутер, реакция на 401
├── App.vue              каркас страницы
├── api/                 запросы к бэкенду, по файлу на сущность
│   └── http.js          axios: cookie, CSRF, повтор после 419, тексты ошибок
├── stores/              Pinia: текущий пользователь, категории и места
├── router/index.js      маршруты и проверка входа
├── utils/               форматирование дат, денег, статусов; логика перехода на оплату
├── components/          шапка, пагинация, бейдж статуса, карточка мероприятия, сообщения
└── views/               страницы
```

## Обходы недочётов бэкенда

- **№11:** регистрация отправляет только `email` и `password`. Совпадение паролей проверяется на фронте, потому что лишнее поле сейчас даёт 500.
- **Отменённое или завершённое мероприятие** бэкенд отдаёт участнику как 404 (`EventPolicy::view`). Фронт показывает «Мероприятие недоступно», а в заказах вместо названия пишет номер.
- **Продолжить начатую оплату** после 15 минут через `/pay` нельзя: бэкенд проверяет `expires_at` раньше, чем `payment_url`. Поэтому кнопка «Вернуться к оплате» открывает сохранённую `payment_url` напрямую.
- **Бесплатный заказ** (все билеты по 0 сум) оплатить нельзя: платёжка не принимает сумму 0, и бэкенд вернёт 502 (недочёт №6).
