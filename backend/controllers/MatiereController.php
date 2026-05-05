<?php

require_once __DIR__ . '/BaseCrudController.php';
require_once __DIR__ . '/../models/Matiere.php';

class MatiereController extends BaseCrudController
{
    protected function model(): object
    {
        return new Matiere();
    }
}
