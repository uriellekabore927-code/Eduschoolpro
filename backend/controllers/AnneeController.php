<?php

require_once __DIR__ . '/BaseCrudController.php';
require_once __DIR__ . '/../models/AnneeAcademique.php';

class AnneeController extends BaseCrudController
{
    protected function model(): object
    {
        return new AnneeAcademique();
    }
}
