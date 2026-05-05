<?php

require_once __DIR__ . '/../models/Utilisateur.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/activity_log.php';

class AuthController
{
    private Utilisateur $users;

    public function __construct()
    {
        $this->users = new Utilisateur();
    }

    private function resolvePermissions(array $user): array
    {
        $rolePermissions = json_decode($user['role_permissions_json'] ?? '[]', true);
        if (is_array($rolePermissions) && $rolePermissions) {
            return $rolePermissions;
        }

        $legacyPermissions = json_decode($user['permissions_json'] ?? '[]', true);
        return is_array($legacyPermissions) ? $legacyPermissions : [];
    }

    public function login(array $data): void
    {
        if (empty($data['email']) || empty($data['password'])) {
            jsonResponse(false, 'Email et mot de passe requis.', null, 400);
        }

        $user = $this->users->findByEmail($data['email']);
        if (!$user || !password_verify($data['password'], $user['mot_de_passe_hash'])) {
            logActivity(null, 'AUTH_LOGIN_FAILED', ['email' => $data['email'] ?? null]);
            jsonResponse(false, 'Identifiants invalides.', null, 401);
        }

        if ((int) $user['actif'] !== 1) {
            logActivity((int) $user['id'], 'AUTH_LOGIN_DENIED', ['email' => $user['email'], 'reason' => 'inactive']);
            jsonResponse(false, 'Compte inactif.', null, 403);
        }

        $this->users->touchLastLogin((int) $user['id']);
        $permissions = $this->resolvePermissions($user);

        $token = generateJwt([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'name' => trim($user['prenom'] . ' ' . $user['nom']),
            'id_lien' => $user['id_lien'],
            'type_lien' => $user['type_lien'],
            'permissions' => $permissions,
        ]);

        logActivity((int) $user['id'], 'AUTH_LOGIN_SUCCESS', [
            'email' => $user['email'],
            'role' => $user['role'],
        ]);

        jsonResponse(true, 'Connexion réussie.', [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role'],
                'role_libelle' => $user['role_libelle'] ?? $user['role'],
                'id_lien' => $user['id_lien'],
                'type_lien' => $user['type_lien'],
                'permissions' => $permissions,
            ],
        ]);
    }

    public function logout(): void
    {
        $auth = requireAuth();
        logActivity((int) ($auth['sub'] ?? 0), 'AUTH_LOGOUT', [
            'role' => $auth['role'] ?? null,
            'email' => $auth['email'] ?? null,
        ]);
        jsonResponse(true, 'Déconnexion prise en compte côté client.');
    }

    public function me(): void
    {
        $auth = requireAuth();
        $user = $this->users->findById((int) $auth['sub']);
        if ($user) {
            $user['permissions'] = $this->resolvePermissions($user);
        }
        jsonResponse(true, 'Profil récupéré avec succès.', $user);
    }
}
