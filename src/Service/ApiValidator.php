<?php

namespace App\Service;

use App\Entity\Clinic;
use App\Entity\Owner;
use App\Repository\OwnerRepository;

class ApiValidator
{
    public function __construct(
        private OwnerRepository $ownerRepo,
    ) {}

    // ─── Formats invalides ────────────────────────────────────────────────────

    private function validateEmail(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Format d'email invalide.";
        }
        return null;
    }

    private function validatePhone(string $phone): ?string
    {
        if (!preg_match('/^[0-9 .+\-()]{7,20}$/', $phone)) {
            return "Format de téléphone invalide.";
        }
        return null;
    }

    private function validatePassword(string $password): ?string
    {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/', $password)) {
            return 'Le mot de passe doit contenir au moins 6 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
        }
        return null;
    }

    // ─── Owner ────────────────────────────────────────────────────────────────

    public function validateOwnerCreate(array $data, ?Clinic $clinic): ?string
    {
        if (empty($data['nom']) || empty($data['prenom']) || empty($data['email'])) {
            return 'Les champs nom, prenom et email sont obligatoires.';
        }
        if ($error = $this->validateEmail($data['email'])) return $error;
        if (!empty($data['telephone']) && ($error = $this->validatePhone($data['telephone']))) return $error;
        if ($this->ownerRepo->findOneBy(['email' => $data['email'], 'clinic' => $clinic])) {
            return 'Un propriétaire avec cet email existe déjà dans cet établissement.';
        }
        return null;
    }

    public function validateOwnerUpdate(array $data, Owner $current): ?string
    {
        if (array_key_exists('email', $data)) {
            if (empty($data['email'])) return "L'email ne peut pas être vide.";
            if ($error = $this->validateEmail($data['email'])) return $error;
            $existing = $this->ownerRepo->findOneBy(['email' => $data['email'], 'clinic' => $current->getClinic()]);
            if ($existing && $existing->getId() !== $current->getId()) {
                return 'Un propriétaire avec cet email existe déjà dans cet établissement.';
            }
        }
        if (array_key_exists('telephone', $data) && !empty($data['telephone'])) {
            if ($error = $this->validatePhone($data['telephone'])) return $error;
        }
        return null;
    }

    // ─── User ─────────────────────────────────────────────────────────────────

    public function validateUserCreate(array $data): ?string
    {
        if (empty($data['email']) || empty($data['password']) || empty($data['name']) || empty($data['role'])) {
            return 'Les champs email, password, name et role sont obligatoires.';
        }
        if ($error = $this->validateEmail($data['email'])) return $error;
        if ($error = $this->validatePassword($data['password'])) return $error;
        return null;
    }
}
