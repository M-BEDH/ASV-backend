<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328140532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users MODIFY COLUMN role ENUM('client','veterinaire','responsable','assistant','benevole') NOT NULL");

    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users MODIFY COLUMN role ENUM('client','veterinaire','assistant') NOT NULL");

    }
}
