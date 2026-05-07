<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;

defined('BASEPATH') or exit('No direct script access allowed');

class CI_Spreadsheet extends Spreadsheet
{
    public function __construct()
    {
        parent::__construct();
    }
}
