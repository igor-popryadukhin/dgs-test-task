<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the DGS order, payment, delivery, inventory, promo and outbox schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
                sku VARCHAR(64) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(32) NOT NULL,
                price_minor INTEGER NOT NULL CHECK (price_minor >= 0),
                currency CHAR(3) NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                active BOOLEAN NOT NULL DEFAULT TRUE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE promo_code (
                code VARCHAR(64) PRIMARY KEY,
                type VARCHAR(16) NOT NULL CHECK (type IN ('percent', 'amount')),
                value INTEGER NOT NULL CHECK (value > 0),
                currency CHAR(3),
                max_uses INTEGER NOT NULL CHECK (max_uses > 0),
                used_count INTEGER NOT NULL DEFAULT 0 CHECK (used_count >= 0 AND used_count <= max_uses),
                active BOOLEAN NOT NULL DEFAULT TRUE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE purchase_order (
                id UUID PRIMARY KEY,
                client_request_id UUID NOT NULL UNIQUE,
                sku VARCHAR(64) NOT NULL REFERENCES product(sku),
                status VARCHAR(32) NOT NULL,
                amount_minor INTEGER NOT NULL CHECK (amount_minor >= 0),
                original_amount_minor INTEGER NOT NULL CHECK (original_amount_minor >= 0),
                currency CHAR(3) NOT NULL,
                promo_code VARCHAR(64) REFERENCES promo_code(code),
                issued_code VARCHAR(128),
                payment_event_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql('CREATE INDEX purchase_order_status_idx ON purchase_order(status)');
        $this->addSql(<<<'SQL'
            CREATE TABLE payment_event (
                event_id VARCHAR(128) PRIMARY KEY,
                order_reference UUID NOT NULL,
                status VARCHAR(16) NOT NULL CHECK (status IN ('paid', 'failed')),
                amount_minor INTEGER NOT NULL,
                currency CHAR(3) NOT NULL,
                occurred_at TIMESTAMPTZ NOT NULL,
                payload JSONB NOT NULL,
                processing_error TEXT,
                processed_at TIMESTAMPTZ,
                received_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql('CREATE INDEX payment_event_pending_idx ON payment_event(order_reference, occurred_at) WHERE processed_at IS NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_key (
                id BIGSERIAL PRIMARY KEY,
                provider VARCHAR(8) NOT NULL CHECK (provider IN ('A', 'B')),
                sku VARCHAR(64) NOT NULL REFERENCES product(sku),
                code VARCHAR(128) NOT NULL UNIQUE,
                assigned_order_id UUID UNIQUE REFERENCES purchase_order(id),
                assigned_at TIMESTAMPTZ
            )
        SQL);
        $this->addSql('CREATE INDEX inventory_available_idx ON inventory_key(provider, sku, id) WHERE assigned_order_id IS NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE supplier_issue (
                request_id VARCHAR(160) PRIMARY KEY,
                provider VARCHAR(8) NOT NULL,
                sku VARCHAR(64) NOT NULL,
                order_id UUID NOT NULL REFERENCES purchase_order(id),
                inventory_key_id BIGINT NOT NULL UNIQUE REFERENCES inventory_key(id),
                code VARCHAR(128) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_attempt (
                id UUID PRIMARY KEY,
                order_id UUID NOT NULL UNIQUE REFERENCES purchase_order(id),
                request_id VARCHAR(160) NOT NULL,
                provider VARCHAR(8) NOT NULL,
                status VARCHAR(32) NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT,
                next_retry_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE promo_redemption (
                id BIGSERIAL PRIMARY KEY,
                promo_code VARCHAR(64) NOT NULL REFERENCES promo_code(code),
                order_id UUID NOT NULL UNIQUE REFERENCES purchase_order(id),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGSERIAL PRIMARY KEY,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX messenger_messages_queue_idx ON messenger_messages(queue_name)');
        $this->addSql('CREATE INDEX messenger_messages_available_idx ON messenger_messages(available_at)');
        $this->seedCatalog();
    }

    private function seedCatalog(): void
    {
        $products = [
            ['STEAM-TOPUP-500', 'Пополнение Steam 500 ₽', 'topup', 50000, 'services/steam.png'],
            ['STEAM-TOPUP-1000', 'Пополнение Steam 1000 ₽', 'topup', 100000, 'services/steam.png'],
            ['STEAM-TOPUP-2500', 'Пополнение Steam 2500 ₽', 'topup', 250000, 'services/steam.png'],
            ['KEY-CS2-PRIME', 'CS2 Prime Status ключ', 'key', 129000, 'products/pubg.png'],
            ['KEY-GTA5', 'GTA V ключ активации', 'key', 199000, 'products/pubg.png'],
            ['KEY-EFT', 'Escape from Tarkov ключ', 'key', 349000, 'products/pubg.png'],
            ['SUB-DISCORD-1M', 'Discord Nitro 1 месяц', 'subscription', 39900, 'products/pubg.png'],
            ['SUB-YT-3M', 'YouTube Premium 3 месяца', 'subscription', 149000, 'products/pubg.png'],
            ['SUB-SPOTIFY-1M', 'Spotify Premium 1 месяц', 'subscription', 29900, 'products/pubg.png'],
            ['GIFT-PSN-1000', 'PlayStation Store карта 1000 ₽', 'giftcard', 100000, 'products/pubg.png'],
            ['GIFT-XBOX-1500', 'Xbox Gift Card 1500 ₽', 'giftcard', 150000, 'products/pubg.png'],
            ['GIFT-ROBLOX-800', 'Roblox 800 Robux', 'giftcard', 89000, 'products/pubg.png'],
        ];

        foreach ($products as [$sku, $name, $type, $price, $image]) {
            $this->addSql(
                'INSERT INTO product (sku, name, type, price_minor, currency, image_path) VALUES (?, ?, ?, ?, ?, ?)',
                [$sku, $name, $type, $price, 'RUB', $image],
            );
        }

        foreach ([['WELCOME10', 'percent', 10, null, 100], ['GG500', 'amount', 50000, 'RUB', 20], ['LIMIT3', 'percent', 25, null, 3], ['ONCEONLY', 'percent', 50, null, 1]] as $promo) {
            $this->addSql('INSERT INTO promo_code (code, type, value, currency, max_uses) VALUES (?, ?, ?, ?, ?)', $promo);
        }

        $keys = [
            'LFXC-TNCS-BPCD', 'P3EI-W8UO-9B4K', 'FEL3-GUXN-TCCH', 'YPLV-QK2Z-IUS5', '0K9E-P1FR-BY1U',
            '5LZV-UQ48-RXCZ', 'X93K-NYAQ-GEC1', 'EIO5-CQT5-35KO', 'M58F-GIIR-VJAP', 'NU8Y-SWYB-6252',
            'OODW-CCHF-MBAF', 'DNA5-WFJM-NE49', 'QRDD-MJ3F-A8TF', 'TAT9-5ZJN-G1T2', 'LI39-4330-ISMB',
            'BKJY-8Q79-8NHI', 'HHW6-4RX2-DX62', '1RG2-L28O-O80G', 'EF63-F39X-MTEA', '8XS7-P53H-JKIV',
            'JPE6-MQV6-P7ST', 'SAPG-A2GR-0ULS', 'T2DU-IJ1S-U16P', 'WSSY-QTR7-Z57J', 'U74E-EPCI-CY26',
            'FZXF-58H8-OR93', 'FPSM-HLZA-TPAL', 'WSC9-28DJ-B2JE', 'P63J-F7UZ-DCYP', 'C7W2-D4C5-QMT7',
            'JESI-DFBH-LK1K', 'SGMA-JA0T-GR7D', '3PR4-OSY9-M3ZW', 'OMBE-C0JF-D45Y', 'KIKQ-FQJ8-9TI8',
            'LMAN-RSHS-AJDO', 'BAKI-VT1X-Z5OL', '9F0X-B46W-03FS', 'S423-V6YY-IBEM', 'D4UW-WYRA-20ST',
            'XC0J-CJ0H-09RN', 'RY1W-XCFJ-0KUA', 'CJYY-YKSQ-QE6H', '97AQ-38QJ-H8HU', 'FS8E-3S5Z-I6RA',
            'ARQK-FML4-A14E', '7Z6K-NO9V-MPJB', 'D4K7-IJSG-N853', 'W67T-ZB0Q-1XKB', '7EQM-K09J-XKUO',
        ];

        foreach ($keys as $index => $code) {
            foreach ($products as [$sku]) {
                $this->addSql('INSERT INTO inventory_key (provider, sku, code) VALUES (?, ?, ?)', [0 === $index % 2 ? 'A' : 'B', $sku, $sku.'-'.$code]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE promo_redemption');
        $this->addSql('DROP TABLE delivery_attempt');
        $this->addSql('DROP TABLE supplier_issue');
        $this->addSql('DROP TABLE inventory_key');
        $this->addSql('DROP TABLE payment_event');
        $this->addSql('DROP TABLE purchase_order');
        $this->addSql('DROP TABLE promo_code');
        $this->addSql('DROP TABLE product');
    }
}
