#!/bin/sh
set -eu

base_url="${BASE_URL:-http://localhost:8080}"
work_dir="$(mktemp -d)"
trap 'rm -rf "$work_dir"' EXIT

uuid() {
    cat /proc/sys/kernel/random/uuid
}

db_scalar() {
    docker compose exec -T database psql -U dgs -d dgs -Atqc "$1"
}

assert_equal() {
    if [ "$1" != "$2" ]; then
        printf 'FAIL: %s (expected %s, got %s)\n' "$3" "$2" "$1" >&2
        exit 1
    fi
    printf 'OK: %s = %s\n' "$3" "$1"
}

wait_for_status() {
    order_id="$1"
    expected="$2"
    attempt=0
    while [ "$attempt" -lt 50 ]; do
        status="$(curl -fsS "$base_url/api/orders/$order_id" | php -r '$v=json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo $v["status"];')"
        if [ "$status" = "$expected" ]; then
            return
        fi
        attempt=$((attempt + 1))
        sleep 0.1
    done
    printf 'FAIL: order %s did not reach %s\n' "$order_id" "$expected" >&2
    exit 1
}

printf '1/5 Concurrent double-click protection\n'
request_id="$(uuid)"
seq 1 50 | xargs -P 50 -I '{}' sh -c \
    'curl -sS -o "$3/order-$4.json" -w "%{http_code}" -X POST "$1/api/orders" -H "Content-Type: application/json" --data "{\"sku\":\"KEY-CS2-PRIME\",\"client_request_id\":\"$2\"}" > "$3/order-$4.status"' \
    _ "$base_url" "$request_id" "$work_dir" '{}'
assert_equal "$(db_scalar "SELECT count(*) FROM purchase_order WHERE client_request_id = '$request_id'")" 1 'orders created for one client_request_id'
assert_equal "$(sort -u "$work_dir"/order-*.status | tr -d '\n')" 201 'HTTP status for all duplicate creates'

printf '2/5 Parallel duplicate and distinct payment webhooks\n'
occurred_at="$(date -Iseconds)"
duplicate_event="duplicate-$request_id"
seq 1 50 | xargs -P 50 -I '{}' sh -c \
    'curl -fsS -o /dev/null -X POST "$1/api/webhooks/payment" -H "Content-Type: application/json" --data "{\"event_id\":\"$3\",\"order_id\":\"$2\",\"status\":\"paid\",\"amount\":1290,\"currency\":\"RUB\",\"created_at\":\"$4\"}"' \
    _ "$base_url" "$request_id" "$duplicate_event" "$occurred_at"
wait_for_status "$request_id" delivered
assert_equal "$(db_scalar "SELECT count(*) FROM payment_event WHERE event_id = '$duplicate_event'")" 1 'stored duplicate event rows'

event_prefix="parallel-$request_id"
seq 1 50 | xargs -P 50 -I '{}' sh -c \
    'curl -fsS -o /dev/null -X POST "$1/api/webhooks/payment" -H "Content-Type: application/json" --data "{\"event_id\":\"$3-$5\",\"order_id\":\"$2\",\"status\":\"paid\",\"amount\":1290,\"currency\":\"RUB\",\"created_at\":\"$4\"}"' \
    _ "$base_url" "$request_id" "$event_prefix" "$occurred_at" '{}'
assert_equal "$(db_scalar "SELECT count(*) FROM payment_event WHERE event_id LIKE '$event_prefix-%'")" 50 'stored distinct parallel events'
assert_equal "$(db_scalar "SELECT count(*) FROM supplier_issue WHERE order_id = '$request_id'")" 1 'supplier issues for webhook race'
assert_equal "$(db_scalar "SELECT count(*) FROM inventory_key WHERE assigned_order_id = '$request_id'")" 1 'assigned keys for webhook race'

printf '3/5 Webhook before order creation\n'
early_order_id="$(uuid)"
early_event="early-$early_order_id"
curl -fsS -o /dev/null -X POST "$base_url/api/webhooks/payment" -H 'Content-Type: application/json' \
    --data "{\"event_id\":\"$early_event\",\"order_id\":\"$early_order_id\",\"status\":\"paid\",\"amount\":1290,\"currency\":\"RUB\",\"created_at\":\"$occurred_at\"}"
curl -fsS -o /dev/null -X POST "$base_url/api/orders" -H 'Content-Type: application/json' \
    --data "{\"sku\":\"KEY-CS2-PRIME\",\"client_request_id\":\"$early_order_id\"}"
wait_for_status "$early_order_id" delivered
assert_equal "$(db_scalar "SELECT count(*) FROM supplier_issue WHERE order_id = '$early_order_id'")" 1 'issues for early webhook'

printf '4/5 Promo limit under concurrent requests\n'
promo_code="$(printf 'RACE%s%s' "$(date +%s)" "$(uuid | cut -c1-8)" | tr '[:lower:]' '[:upper:]')"
docker compose exec -T database psql -U dgs -d dgs -v ON_ERROR_STOP=1 -q \
    -c "INSERT INTO promo_code (code, type, value, max_uses) VALUES ('$promo_code', 'percent', 10, 3)"
seq 1 10 | xargs -P 10 -I '{}' sh -c \
    'request_id="$(cat /proc/sys/kernel/random/uuid)"; curl -sS -o /dev/null -X POST "$1/api/orders" -H "Content-Type: application/json" --data "{\"sku\":\"KEY-GTA5\",\"client_request_id\":\"$request_id\",\"promo_code\":\"$2\"}"' \
    _ "$base_url" "$promo_code"
assert_equal "$(db_scalar "SELECT used_count FROM promo_code WHERE code = '$promo_code'")" 3 'atomic promo usage count'
assert_equal "$(db_scalar "SELECT count(*) FROM promo_redemption WHERE promo_code = '$promo_code'")" 3 'successful promo redemptions'

printf '5/5 Exactly-once acceptance complete\n'
printf 'PASS: all concurrent acceptance checks succeeded\n'
