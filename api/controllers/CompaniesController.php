<?php

require_once __DIR__ . '/../support/V1Controller.php';

/**
 * Reference data for visit detection. Small, changes rarely — ETag plus
 * `updatedSince` make it effectively free after the first pull.
 *
 * Companies are not a picker on the report form: what the seller types is what
 * gets stored, and this list only exists so a stop can be recognised.
 */
final class CompaniesController extends V1Controller
{
    public function index(): never
    {
        $where = ['c.org_id = ?'];
        $params = [Ctx::orgId()];

        if ($query = $this->query('query')) {
            $where[] = 'c.name LIKE ?';
            $params[] = '%' . $query . '%';
        }

        if ($since = Wire::ts((string) $this->query('updatedSince', ''))) {
            $where[] = 'c.updated_at > ?';
            $params[] = $since;
        }

        $clause = implode(' AND ', $where);

        $stamp = db_fetch_one(
            "SELECT COUNT(*) AS n, COALESCE(MAX(c.updated_at), '') AS latest FROM companies c WHERE {$clause}",
            $params,
        );

        Envelope::freshness('companies-' . $stamp['n'] . '-' . $stamp['latest']);

        $companies = db_fetch_all("SELECT c.* FROM companies c WHERE {$clause} ORDER BY c.name", $params);

        $branches = [];

        if ($this->wants('branches') || $this->includes() === []) {
            foreach (db_fetch_all(
                "SELECT b.* FROM branches b JOIN companies c ON c.id = b.company_id
                  WHERE {$clause} ORDER BY b.name",
                $params,
            ) as $branch) {
                $branches[$branch['company_id']][] = $branch;
            }
        }

        $data = array_map(
            static fn(array $c) => Present::company($c, $branches[$c['id']] ?? []),
            $companies,
        );

        Envelope::ok($data, ['total' => count($data)]);
    }
}
