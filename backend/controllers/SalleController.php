<?php

require_once __DIR__ . '/BaseCrudController.php';
require_once __DIR__ . '/../models/Salle.php';

class SalleController extends BaseCrudController
{
    protected function model(): object
    {
        return new Salle();
    }
}
