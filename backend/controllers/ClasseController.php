<?php

require_once __DIR__ . '/BaseCrudController.php';
require_once __DIR__ . '/../models/Classe.php';

class ClasseController extends BaseCrudController
{
    protected function model(): object
    {
        return new Classe();
    }
}
