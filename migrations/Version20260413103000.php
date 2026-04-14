<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ is_vet sur users et initialise les vets existants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_vet TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE users SET is_vet = 1 WHERE role IN ('veterinaire', 'responsable')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP is_vet');
    }
}
