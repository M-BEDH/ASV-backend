<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408104822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE owner_clinic (owner_id VARCHAR(36) NOT NULL COLLATE `utf8mb4_unicode_ci`, clinic_id VARCHAR(36) NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_A279D4EA7E3C61F9 (owner_id), INDEX IDX_A279D4EACC22AD4 (clinic_id), PRIMARY KEY (owner_id, clinic_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE owner_clinic ADD CONSTRAINT FK_A279D4EA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES owners (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE owner_clinic ADD CONSTRAINT FK_A279D4EACC22AD4 FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE CASCADE');

        // Migre les données existantes avant de supprimer clinic_id
        $this->addSql('INSERT IGNORE INTO owner_clinic (owner_id, clinic_id) SELECT id, clinic_id FROM owners WHERE clinic_id IS NOT NULL');

        $this->addSql('ALTER TABLE owners DROP FOREIGN KEY `FK_OWNERS_CLINIC`');
        $this->addSql('DROP INDEX IDX_427292FACC22AD4 ON owners');
        $this->addSql('ALTER TABLE owners DROP clinic_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE owner_clinic DROP FOREIGN KEY FK_A279D4EA7E3C61F9');
        $this->addSql('ALTER TABLE owner_clinic DROP FOREIGN KEY FK_A279D4EACC22AD4');
        $this->addSql('DROP TABLE owner_clinic');
        $this->addSql('ALTER TABLE owners ADD clinic_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE owners ADD CONSTRAINT `FK_OWNERS_CLINIC` FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_427292FACC22AD4 ON owners (clinic_id)');
    }
}
