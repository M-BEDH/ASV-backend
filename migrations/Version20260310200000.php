<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout table clinics + colonnes clinic_id sur users, animals, owners, medical_consultations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        // Table clinics
        $this->addSql('CREATE TABLE clinics (
            id VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY UNIQ_D7053B665E237E06 (name)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // clinic_id sur users
        $this->addSql('ALTER TABLE users ADD clinic_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9CC22AD4 FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_USERS_CLINIC ON users (clinic_id)');

        // clinic_id sur animals
        $this->addSql('ALTER TABLE animals ADD clinic_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DDCC22AD4 FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_966C69DDCC22AD4 ON animals (clinic_id)');

        // clinic_id sur owners
        $this->addSql('ALTER TABLE owners ADD clinic_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE owners ADD CONSTRAINT FK_OWNERS_CLINIC FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_OWNERS_CLINIC ON owners (clinic_id)');

        // clinic_id sur medical_consultations
        $this->addSql('ALTER TABLE medical_consultations ADD clinic_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE medical_consultations ADD CONSTRAINT FK_F9050EB1CC22AD4 FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F9050EB1CC22AD4 ON medical_consultations (clinic_id)');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        $this->addSql('ALTER TABLE medical_consultations DROP FOREIGN KEY FK_F9050EB1CC22AD4');
        $this->addSql('DROP INDEX IDX_F9050EB1CC22AD4 ON medical_consultations');
        $this->addSql('ALTER TABLE medical_consultations DROP COLUMN clinic_id');

        $this->addSql('ALTER TABLE owners DROP FOREIGN KEY FK_OWNERS_CLINIC');
        $this->addSql('DROP INDEX IDX_OWNERS_CLINIC ON owners');
        $this->addSql('ALTER TABLE owners DROP COLUMN clinic_id');

        $this->addSql('ALTER TABLE animals DROP FOREIGN KEY FK_966C69DDCC22AD4');
        $this->addSql('DROP INDEX IDX_966C69DDCC22AD4 ON animals');
        $this->addSql('ALTER TABLE animals DROP COLUMN clinic_id');

        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9CC22AD4');
        $this->addSql('DROP INDEX IDX_USERS_CLINIC ON users');
        $this->addSql('ALTER TABLE users DROP COLUMN clinic_id');

        $this->addSql('DROP TABLE clinics');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
