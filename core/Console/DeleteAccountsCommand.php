<?php

/** Run every minute: php vayu accounts:delete-due. --dry performs no writes. */
class DeleteAccountsCommand
{
    public function __construct(private string $baseDir, private array $framework = []) {}

    public function run(array $args): void
    {
        global $pdo;
        require_once $this->baseDir . '/config/db.php';
        foreach (['Wire', 'Uuid', 'Envelope', 'Ctx', 'Geo', 'Polyline', 'MapMatch', 'Present', 'Engine'] as $support) {
            require_once $this->baseDir . '/api/support/' . $support . '.php';
        }
        require_once $this->baseDir . '/core/AccountDeletion.php';
        $dry = in_array('--dry', $args, true);
        $rows = db_fetch_all("SELECT id, org_id, employee_id FROM account_deletion_requests WHERE status = 'pending' AND delete_after <= ? ORDER BY delete_after", [Wire::now()]);
        $failures = 0;
        foreach ($rows as $row) {
            if ($dry) { echo "Would delete employee {$row['employee_id']} (ticket {$row['id']})\n"; continue; }
            try {
                AccountDeletion::complete($row['id'], $row['org_id']);
                echo "Deleted employee {$row['employee_id']} (ticket {$row['id']})\n";
            } catch (Throwable $error) {
                $failures++;
                fwrite(STDERR, "Ticket {$row['id']} failed: {$error->getMessage()}\n");
            }
        }
        echo count($rows) . " due request(s); {$failures} failure(s).\n";
        if ($failures) exit(1);
    }
}
