<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260704004908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE buyer_order ADD delivery_type VARCHAR(20) DEFAULT 'courier' NOT NULL");
        // добавляем delivery_type и старым заказам сразу ставим courier
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_type DROP DEFAULT');
        // убираем дефолт, чтобы новые заказы брали тип доставки из запроса
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_region DROP NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_city DROP NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_street DROP NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_house DROP NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_postal_code DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE buyer_order DROP delivery_type');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_region SET NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_city SET NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_street SET NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_house SET NOT NULL');
        $this->addSql('ALTER TABLE buyer_order ALTER delivery_postal_code SET NOT NULL');
    }
}
