<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration genèse : recrée le socle (users, owners, animals, medical_consultations)
 * tel qu'il existait avant la mise en place du système de migrations (créé initialement
 * via doctrine:schema:create). Sans elle, migrations:migrate échoue sur une base vide
 * dès Version20260310141259 (`Table 'users' doesn't exist`), qui suppose ce socle déjà présent.
 *
 * État reconstruit à partir de ce que les migrations suivantes modifient/renomment/suppriment
 * en référençant des objets déjà existants (noms de contraintes/index, anciens types de colonnes) :
 * cf. Version20260318151137 (uq_users_email), Version20260401080944 (FK_966C69DD76C50E4A,
 * FK_966C69DDDE12AB56, FK_F9050EB15C80924, idx_proprietaires_created_by, idx_proprietaires_user_id,
 * anciens types CHAR(36)/ENUM/TEXT), Version20260328140532 (role ENUM d'origine).
 */
final class Version20260301000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Genèse : création du socle users/owners/animals/medical_consultations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        $this->addSql('CREATE TABLE users (
            id CHAR(36) DEFAULT \'uuid()\' NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            role ENUM(\'client\', \'veterinaire\', \'assistant\') NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX uq_users_email (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE owners (
            id CHAR(36) DEFAULT \'uuid()\' NOT NULL,
            nom VARCHAR(255) NOT NULL,
            prenom VARCHAR(255) NOT NULL,
            adresse TEXT DEFAULT NULL,
            telephone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            created_by CHAR(36) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id CHAR(36) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_proprietaires_created_by (created_by),
            INDEX idx_proprietaires_user_id (user_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE animals (
            id VARCHAR(36) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            espece VARCHAR(255) NOT NULL,
            race VARCHAR(255) DEFAULT NULL,
            date_naissance DATE DEFAULT NULL,
            remarques LONGTEXT DEFAULT NULL,
            proprietaire_id VARCHAR(36) DEFAULT NULL,
            created_by VARCHAR(36) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_966C69DD76C50E4A ON animals (proprietaire_id)');
        $this->addSql('CREATE INDEX IDX_966C69DDDE12AB56 ON animals (created_by)');
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DD76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES owners (id)');
        $this->addSql('ALTER TABLE animals ADD CONSTRAINT FK_966C69DDDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id)');

        $this->addSql('CREATE TABLE medical_consultations (
            id VARCHAR(36) NOT NULL,
            animal_id VARCHAR(36) DEFAULT NULL,
            date_consultation DATETIME NOT NULL,
            veterinaire_id VARCHAR(36) DEFAULT NULL,
            motif LONGTEXT NOT NULL,
            compte_rendu LONGTEXT DEFAULT NULL,
            traitements LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX IDX_F9050EB18E962C16 ON medical_consultations (animal_id)');
        $this->addSql('CREATE INDEX IDX_F9050EB15C80924 ON medical_consultations (veterinaire_id)');
        $this->addSql('ALTER TABLE medical_consultations ADD CONSTRAINT FK_F9050EB18E962C16 FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE medical_consultations ADD CONSTRAINT FK_F9050EB15C80924 FOREIGN KEY (veterinaire_id) REFERENCES users (id)');

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE medical_consultations');
        $this->addSql('DROP TABLE animals');
        $this->addSql('DROP TABLE owners');
        $this->addSql('DROP TABLE users');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
