<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Guarantee at most one supplier issue per order across provider fallback';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX supplier_issue_order_unique_idx ON supplier_issue (order_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX supplier_issue_order_unique_idx');
    }
}
