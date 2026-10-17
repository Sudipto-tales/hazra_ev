<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../../core/AccountDeletion.php';

final class AccountDeletionController extends V1Controller
{
    public function mine(): never
    {
        $this->requireEmployee();
        Envelope::noStore();
        $ticket = db_fetch_one('SELECT * FROM account_deletion_requests WHERE employee_id = ? AND org_id = ?', [Ctx::id(), Ctx::orgId()]);
        Envelope::ok($ticket ? AccountDeletion::present($ticket) : null);
    }

    public function request(): never
    {
        $this->requireEmployee();
        $body = ApiRequest::body();
        if (($body['confirmed'] ?? false) !== true) Envelope::invalid('Confirm that you want to request account deletion', 'confirmed');
        $reason = $body['reason'] ?? '';
        if (!is_string($reason)) Envelope::invalid('Reason must be text', 'reason');
        try {
            $ticket = AccountDeletion::request(Ctx::principal(), $reason);
        } catch (InvalidArgumentException $error) {
            Envelope::invalid($error->getMessage(), 'reason');
        } catch (DomainException $error) {
            Envelope::fail('ACCOUNT_DELETED', $error->getMessage(), 410);
        }
        Envelope::noStore();
        Envelope::ok(AccountDeletion::present($ticket));
    }

    public function index(): never
    {
        $this->requireAdmin();
        Envelope::noStore();
        $rows = db_fetch_all('SELECT * FROM account_deletion_requests WHERE org_id = ? ORDER BY requested_at DESC', [Ctx::orgId()]);
        Envelope::ok(array_map([AccountDeletion::class, 'present'], $rows), ['pending' => count(array_filter($rows, static fn($r) => $r['status'] === 'pending'))]);
    }

    public function approve(): never
    {
        $this->requireAdmin();
        Envelope::noStore();
        if ($this->input('confirmation') !== AccountDeletion::CONFIRMATION) Envelope::invalid('Type exactly: delete account', 'confirmation');
        $note = $this->input('adminNote') ?? '';
        if (!is_string($note)) Envelope::invalid('Admin note must be text', 'adminNote');
        try {
            $ticket = AccountDeletion::complete((string) $this->param('id'), Ctx::orgId(), Ctx::id(), null, $note);
        } catch (InvalidArgumentException $error) {
            Envelope::invalid($error->getMessage(), 'adminNote');
        } catch (OutOfBoundsException) {
            Envelope::notFound('DELETION_REQUEST_NOT_FOUND', 'No such deletion request');
        } catch (DomainException $error) {
            Envelope::forbidden($error->getMessage());
        }
        Envelope::ok(AccountDeletion::present($ticket));
    }
}
