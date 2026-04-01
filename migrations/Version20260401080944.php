<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401080944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync schema: CHAR→VARCHAR sur ids, renommage index, FKs owners';
    }

    public function up(Schema $schema): void
    {
        // 1. Supprimer les FKs qui bloquent la modification de owners.id et users.id
        $this->addSql('ALTER TABLE animals DROP FOREIGN KEY FK_966C69DD76C50E4A');    // proprietaire_id → owners.id
        $this->addSql('ALTER TABLE animals DROP FOREIGN KEY FK_966C69DDDE12AB56');    // created_by → users.id
        $this->addSql('ALTER TABLE medical_consultations DROP FOREIGN KEY FK_F9050EB15C80924'); // veterinaire_id → users.id

        // 2. Modifier owners et users
        $this->addSql('ALTER TABLE owners CHANGE id id VARCHAR(36) NOT NULL, CHANGE adresse adresse LONGTEXT DEFAULT NULL, CHANGE created_by created_by VARCHAR(36) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE user_id user_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE owners RENAME INDEX idx_proprietaires_created_by TO IDX_427292FADE12AB56');
        $this->addSql('ALTER TABLE owners RENAME INDEX idx_owners_clinic TO IDX_427292FACC22AD4');
        $this->addSql('ALTER TABLE owners RENAME INDEX idx_proprietaires_user_id TO IDX_427292FAA76ED395');
        $this->addSql('ALTER TABLE users CHANGE id id VARCHAR(36) NOT NULL, CHANGE role role VARCHAR(20) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX idx_users_clinic TO IDX_1483A5E9CC22AD4');

        // 3. Recréer toutes les FKs
        $this->addSql('ALTER TABLE owners ADD CONSTRAINT FK_427292FADE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE owners ADD CONSTRAINT FK_427292FAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DD76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES owners (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DDDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE medical_consultations ADD CONSTRAINT FK_F9050EB15C80924 FOREIGN KEY (veterinaire_id) REFERENCES users (id) ON DELETE SET NULL');

        // 4. messenger_messages
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // 1. Supprimer les FKs ajoutées par up()
        $this->addSql('ALTER TABLE owners DROP FOREIGN KEY FK_427292FADE12AB56');
        $this->addSql('ALTER TABLE owners DROP FOREIGN KEY FK_427292FAA76ED395');
        $this->addSql('ALTER TABLE animals DROP FOREIGN KEY FK_966C69DD76C50E4A');
        $this->addSql('ALTER TABLE animals DROP FOREIGN KEY FK_966C69DDDE12AB56');
        $this->addSql('ALTER TABLE medical_consultations DROP FOREIGN KEY FK_F9050EB15C80924');

        // 2. Revenir à CHAR(36)
        $this->addSql('ALTER TABLE owners CHANGE id id CHAR(36) DEFAULT \'uuid()\' NOT NULL, CHANGE adresse adresse TEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE created_by created_by CHAR(36) DEFAULT NULL, CHANGE user_id user_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE owners RENAME INDEX idx_427292fade12ab56 TO idx_proprietaires_created_by');
        $this->addSql('ALTER TABLE owners RENAME INDEX idx_427292faa76ed395 TO idx_proprietaires_user_id');
        $this->addSql('ALTER TABLE owners RENAME INDEX idx_427292facc22ad4 TO IDX_OWNERS_CLINIC');
        $this->addSql('ALTER TABLE users CHANGE id id CHAR(36) DEFAULT \'uuid()\' NOT NULL, CHANGE role role ENUM(\'client\', \'veterinaire\', \'responsable\', \'assistant\', \'benevole\') NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX idx_1483a5e9cc22ad4 TO IDX_USERS_CLINIC');

        // 3. Recréer les FKs
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DD76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES owners (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DDDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE medical_consultations ADD CONSTRAINT FK_F9050EB15C80924 FOREIGN KEY (veterinaire_id) REFERENCES users (id) ON DELETE SET NULL');

        // 4. messenger_messages
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
    }
}
