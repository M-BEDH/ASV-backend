<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mise à jour contrainte CHECK role : ajout responsable et benevole';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT chk_user_role');
        $this->addSql("ALTER TABLE users ADD CONSTRAINT chk_user_role CHECK (role IN ('client', 'veterinaire', 'responsable', 'assistant', 'benevole'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT chk_user_role');
        $this->addSql("ALTER TABLE users ADD CONSTRAINT chk_user_role CHECK (role IN ('client', 'veterinaire', 'assistant'))");
    }
}
