# DGS — тестовое задание Fullstack

Магазин цифровых товаров на Symfony с интерфейсом по предоставленному макету DGS, асинхронной обработкой оплаты и строго идемпотентной выдачей ключей.

Стек: Symfony 8.1, PHP 8.4, Doctrine ORM/DBAL, PostgreSQL 17, Twig, AssetMapper, Symfony Messenger, FrankenPHP и Docker Compose. Сборщик Node.js не нужен.

## Запуск

Требуется только Docker с Compose v2.

```bash
docker compose up -d --build
```

Приложение откроется на <http://localhost:8080>. Миграция и демонстрационные товары, промокоды и ключи загружаются автоматически. В отдельных контейнерах работают HTTP-приложение, Messenger worker и PostgreSQL.

Полезные команды:

```bash
make logs       # логи приложения и worker
make test       # отдельная dgs_test, миграции и интеграционные тесты
make quality    # CS Fixer, PHPStan level 8, Twig и DI lint
make race       # параллельные заказы, webhook и лимит промокода
make acceptance # все тесты, quality checks и race-сценарии
docker compose down
```

## Реализованные сценарии

- Адаптивная Twig-верстка по Figma: header, каталог, баннер-карусель, сервисы, Steam top-up и товарные ряды.
- Каталог закрывается по клику вне меню и Escape; работает переключение разделов.
- Переключатель `$/₸/₽` в Steam-блоке с активным состоянием, поиск, избранное и hover-состояния сервисов и карточек. Валюта не пересчитывает цены, как и требуется в задании.
- Создание заказа защищено `client_request_id`; двойной клик возвращает тот же заказ.
- Webhook сначала сохраняется в durable inbox, после чего попадает в Doctrine transport Symfony Messenger.
- Уникальный `event_id` делает одинаковый webhook no-op. Событие до заказа сохраняется и повторно ставится в очередь после создания заказа.
- Обработка сериализуется PostgreSQL advisory lock на заказ. Ключ резервируется через `FOR UPDATE SKIP LOCKED`, а ограничения БД гарантируют один issue и один назначенный ключ на заказ.
- Оба stub-поставщика A/B принимают постоянный `request_id`. Timeout задерживает ответ на настраиваемое число секунд. Fallback A → B безопасен: если A уже выделил ключ до потери ответа, B получает тот же закреплённый за заказом код.
- При пустом пуле заказ становится `out_of_stock`; после пополнения администратор может безопасно повторить доставку.
- Промокоды применяются только сервером. Лимит расходуется атомарным `UPDATE ... WHERE used_count < max_uses`, поэтому не превышается параллельными запросами.

## Архитектура

Модель данных представлена Doctrine-сущностями `Product`, `PurchaseOrder`, `PaymentEvent`, `PromoCode`, `PromoRedemption`, `InventoryKey`, `SupplierIssue` и `DeliveryAttempt`. Статусы, типы товаров, промокодов и поставщики оформлены PHP enum. Контроллеры работают с типизированными input/view DTO, сервисы реализуют сценарии приложения, а чтение и запись данных инкапсулированы в репозиториях.

Низкоуровневый DBAL находится только в persistence-слое и используется для операций, которым нужны гарантии PostgreSQL, не выражаемые обычным `persist()`:

- атомарная регистрация webhook через `ON CONFLICT DO NOTHING`;
- атомарное расходование лимита промокода;
- блокировка заказа `FOR UPDATE`;
- конкурентное резервирование ключа через `FOR UPDATE SKIP LOCKED`;
- транзакционные advisory locks.

Обычные выборки, состояние заказов, платежей и доставки обслуживаются Doctrine ORM. В контроллерах и application-сервисах SQL отсутствует.

Статусы: `created`, `paid`, `delivering`, `delivered`, `payment_failed`, `out_of_stock`, `delivery_failed`.

## API

Создать заказ:

```bash
curl -X POST http://localhost:8080/api/orders \
  -H 'Content-Type: application/json' \
  -d '{"sku":"KEY-CS2-PRIME","client_request_id":"0199a9f0-509f-7d83-93de-d1b1acf12e78","promo_code":"WELCOME10"}'
```

Payment webhook:

```bash
curl -X POST http://localhost:8080/api/webhooks/payment \
  -H 'Content-Type: application/json' \
  -d '{"event_id":"evt-1","order_id":"<uuid>","status":"paid","amount":1161,"currency":"RUB","created_at":"2026-08-31T12:00:00+03:00"}'
```

Stub-поставщик (`mode` можно установить в `timeout` или `error`):

```bash
curl -X POST http://localhost:8080/api/suppliers/A/issue \
  -H 'Content-Type: application/json' \
  -d '{"request_id":"stable-request-1","sku":"KEY-CS2-PRIME","order_id":"<uuid>","mode":"timeout"}'
```

Успешный ответ:

```json
{"status":"ok","request_id":"stable-request-1","code":"KEY-CS2-PRIME-..."}
```

При `mode=timeout` ответ задерживается на `SUPPLIER_TIMEOUT_SECONDS` и завершается HTTP 504. Вероятности ошибок и таймаутов независимо настраиваются переменными `SUPPLIER_A_FAILURE_RATE`, `SUPPLIER_A_TIMEOUT_RATE`, `SUPPLIER_B_FAILURE_RATE` и `SUPPLIER_B_TIMEOUT_RATE`.

Административные endpoints защищены `X-Admin-Token` (локально `dev-admin-token`):

- `GET /api/admin/orders` — `out_of_stock` и `delivery_failed`;
- `POST /api/admin/inventory` — добавить ключи;
- `POST /api/admin/orders/{id}/retry` — идемпотентный повтор доставки.

Страница наблюдения без мутаций доступна по `/admin`.

## Почему гонки не приводят к двойной выдаче

`payment_event.event_id`, `purchase_order.client_request_id`, `supplier_issue.request_id`, `supplier_issue.order_id` и `inventory_key.assigned_order_id` имеют уникальные ограничения. Они дополняют, а не заменяют блокировки: advisory lock сериализует бизнес-операцию, row lock защищает состояние, а unique constraints остаются последним барьером целостности. Webhook и Messenger-сообщение записываются в одной транзакции PostgreSQL, поэтому событие не теряется между HTTP-ответом и постановкой в очередь.

Интеграционные тесты проверяют повторный клик, дубликаты webhook, webhook до заказа, out-of-order события, неоднозначный timeout, fallback и восстановление после пустого пула.

`scripts/race-test.sh` через реальные параллельные HTTP-запросы воспроизводит:

- 50 одновременных созданий заказа с одним `client_request_id`;
- 50 webhook с одинаковым `event_id`;
- 50 разных событий оплаты одного заказа;
- webhook до создания заказа;
- 10 конкурентных заказов с промокодом, имеющим лимит 3.

Скрипт проверяет фактические счётчики PostgreSQL и завершается с ненулевым кодом при нарушении любой гарантии.

## Комплект сдачи

- Исходники полностью запускаются локально по инструкции выше; внешний frontend deploy не требуется по условиям задания.
- Чистый архив исходников формируется без `vendor`, кешей, IDE-настроек и локальных секретов.
- Фактически затраченное время: около 6 часов.
