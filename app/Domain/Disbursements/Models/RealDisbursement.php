<?php

namespace App\Domain\Disbursements\Models;

/**
 * Disbursement de production : même structure que Disbursement,
 * mais toujours lu et écrit dans la base mysql_money (table api_disbursements),
 * quelle que soit la connexion par défaut (requête API, worker de queue...).
 */
class RealDisbursement extends Disbursement
{
    protected $connection = 'mysql_money';
    protected $table      = 'api_disbursements';
}
