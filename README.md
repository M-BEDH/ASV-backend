# ASV — Backend (API REST)

API REST de l'application de suivi vétérinaire, développée avec **Symfony 7 / PHP 8.2**.

Repo principal (infra Docker) : [ASV](https://github.com/M-BEDH/ASV)

---

## Prérequis

- PHP 8.2+
- Composer
- MySQL 8 (ou via Docker, voir repo principal)

---

## Installation

```bash
composer install
cp .env .env.local   # renseigner DATABASE_URL, JWT_SECRET_KEY, MAILER_DSN…
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # données de dev (optionnel)
```

---

## Lancer en développement

```bash
symfony server:start   # ou php -S localhost:8000 -t public/
```

Via Docker (recommandé) : se référer au `docker-compose.yml` du repo principal.  
L'API est exposée sur **http://localhost:8080**.

---

## Structure `src/`

```
src/
├── Controller/
│   └── Api/
│       ├── AuthController.php              # register / login / JWT
│       ├── AnimalApiController.php
│       ├── ClinicApiController.php
│       ├── MedicalConsultationApiController.php
│       ├── OwnerApiController.php
│       ├── UserApiController.php
│       └── ClinicAccessTrait.php           # contrôle d'accès inter-cliniques
├── Entity/                                 # entités Doctrine
│   ├── User.php
│   ├── Clinic.php
│   ├── Animal.php
│   ├── Owner.php
│   └── MedicalConsultation.php
├── Repository/                             # requêtes Doctrine personnalisées
├── Service/
│   ├── ApiValidator.php                    # validation entrées API
│   ├── SerializerService.php              # sérialisation JSON
│   └── UserOwnerLinkingService.php        # lien User ↔ Owner à l'inscription
├── Security/
│   └── AdminUserProvider.php
├── EventListener/
│   └── PrivateNetworkAccessListener.php   # restriction accès admin aux IPs privées
├── DataFixtures/
│   └── AppFixtures.php                    # données de dev
└── Constant/                              # constantes métier (rôles, types…)
```

---

## Authentification

JWT via **LexikJWTAuthenticationBundle**.

| Endpoint | Méthode | Description |
|---|---|---|
| `/api/auth/register` | POST | Inscription ou activation d'un pré-compte |
| `/api/auth/login` | POST | Connexion — retourne un token JWT |

Le token JWT doit être passé dans le header `Authorization: Bearer <token>` pour toutes les routes protégées.

---

## Rôles

| Rôle | Accès |
|---|---|
| `super_admin` | Accès total toutes cliniques |
| `responsable` | Gestion de sa clinique (modifier la clinique, supprimer des utilisateurs, assigner des rôles) |
| `veterinaire` | Lecture/écriture dans sa clinique, peut créer des consultations, peut supprimer des utilisateurs |
| `assistant` | Lecture/écriture dans sa clinique (même accès Symfony que `veterinaire`) |
| `benevole` | Lecture/écriture dans refuges/associations uniquement |
| `client` | Accès à ses propres animaux et consultations |

---

## Migrations

```bash
php bin/console doctrine:migrations:migrate          # appliquer
php bin/console doctrine:migrations:generate         # créer une migration vide
php bin/console doctrine:migrations:diff             # générer depuis les entités
```

---

## Tests

Tests d'intégration PHPUnit avec base de données réelle (pas de mocks DB).

```bash
php bin/phpunit
```

Les tests se trouvent dans `tests/Controller/`. Chaque controller API a sa suite de tests (`AnimalControllerTest`, `UserControllerTest`, etc.).

---

## Variables d'environnement clés

| Variable | Description |
|---|---|
| `DATABASE_URL` | DSN MySQL |
| `JWT_SECRET_KEY` | Clé privée JWT |
| `JWT_PUBLIC_KEY` | Clé publique JWT |
| `JWT_PASSPHRASE` | Passphrase JWT |
| `MAILER_DSN` | Transport mail (Mailpit en dev) |

---

## CI

Le pipeline GitHub Actions de **ce repo** exécute les tests PHPUnit à chaque push sur `master`.  
Le repo principal (ASV) possède son propre CI qui valide uniquement la configuration `docker-compose`.
