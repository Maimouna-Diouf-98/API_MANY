protected function schedule(Schedule $schedule): void
{
    // Vérifier toutes les minutes les transactions expirées
    $schedule->command('transactions:expire-pending')->everyMinute();
}
