<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318151137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email unique par établissement (multi-clinique client)';
    }

    public function up(Schema $schema): void
    {
        // Supprime l'index unique global sur email
        $this->addSql('DROP INDEX uq_users_email ON users');
        // Ajoute un index unique composite (email + clinic_id)
        // Un même email peut exister dans des établissements différents
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_clinic ON users (email, clinic_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_email_clinic ON users');
        $this->addSql('CREATE UNIQUE INDEX uq_users_email ON users (email)');
    }
}
