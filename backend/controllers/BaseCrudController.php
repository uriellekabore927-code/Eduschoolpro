<?php

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/jwt.php';

abstract class BaseCrudController
{
    abstract protected function model(): object;

    protected function permission(): string
    {
        return 'parametres';
    }

    public function index(array $roles = ['administrateur']): void
    {
        requireAuth();
        jsonResponse(true, 'Liste récupérée avec succès.', $this->model()->all());
    }

    public function store(array $data, array $roles = ['administrateur']): void
    {
        requirePermission($this->permission());
        try {
            $id = $this->model()->create($data);
            jsonResponse(true, 'Enregistrement créé avec succès.', ['id' => $id], 201);
        } catch (PDOException $exception) {
            $message = str_contains($exception->getMessage(), 'SQLSTATE[23000]')
                ? 'Impossible de créer l’enregistrement : données invalides ou déjà utilisées.'
                : 'Impossible de créer l’enregistrement.';
            jsonResponse(false, $message, null, 500);
        } catch (Throwable $exception) {
            jsonResponse(false, 'Impossible de créer l’enregistrement.', null, 500);
        }
    }

    public function update(int $id, array $data, array $roles = ['administrateur']): void
    {
        requirePermission($this->permission());
        try {
            $this->model()->update($id, $data);
            jsonResponse(true, 'Enregistrement mis à jour avec succès.', ['id' => $id]);
        } catch (PDOException $exception) {
            $message = str_contains($exception->getMessage(), 'SQLSTATE[23000]')
                ? 'Impossible de mettre à jour l’enregistrement : données invalides ou conflit de contraintes.'
                : 'Impossible de mettre à jour l’enregistrement.';
            jsonResponse(false, $message, null, 500);
        } catch (Throwable $exception) {
            jsonResponse(false, 'Impossible de mettre à jour l’enregistrement.', null, 500);
        }
    }

    public function destroy(int $id, array $roles = ['administrateur']): void
    {
        requirePermission($this->permission());
        try {
            $this->model()->delete($id);
            jsonResponse(true, 'Enregistrement supprimé avec succès.', ['id' => $id]);
        } catch (PDOException $exception) {
            $message = str_contains($exception->getMessage(), 'SQLSTATE[23000]')
                ? 'Impossible de supprimer l’enregistrement : il est utilisé par d’autres données.'
                : 'Impossible de supprimer l’enregistrement.';
            jsonResponse(false, $message, null, 500);
        } catch (Throwable $exception) {
            jsonResponse(false, 'Impossible de supprimer l’enregistrement.', null, 500);
        }
    }
}
