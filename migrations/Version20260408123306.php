<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408123306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE IF EXISTS user_clinic');
        $this->addSql('CREATE TABLE user_clinic (user_id VARCHAR(36) NOT NULL COLLATE `utf8mb4_unicode_ci`, clinic_id VARCHAR(36) NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_62D1E389A76ED395 (user_id), INDEX IDX_62D1E389CC22AD4 (clinic_id), PRIMARY KEY (user_id, clinic_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_clinic ADD CONSTRAINT FK_62D1E389A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_clinic ADD CONSTRAINT FK_62D1E389CC22AD4 FOREIGN KEY (clinic_id) REFERENCES clinics (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_clinic DROP FOREIGN KEY FK_62D1E389A76ED395');
        $this->addSql('ALTER TABLE user_clinic DROP FOREIGN KEY FK_62D1E389CC22AD4');
        $this->addSql('DROP TABLE user_clinic');
    }
}
