<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonne type sur la table clinics (clinique|refuge|association)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinics ADD type VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinics DROP COLUMN type');
    }
}
