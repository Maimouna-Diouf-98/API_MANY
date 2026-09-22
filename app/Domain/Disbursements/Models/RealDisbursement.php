<?php

namespace App\Domain\Disbursements\Models;

class RealDisbursement extends Disbursement
{
    protected $connection = 'mysql_money';
    protected $table      = 'disbursements';
}