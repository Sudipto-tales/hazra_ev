<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/messaging/WebhookEventLog.php';
require_once __BASEDIR__ . '/core/Csrf.php';

/** The admin Webhooks page: what every provider has sent us, and what it did. */
final class WebhookEventsController extends V1Controller
{
    public function index(): never
    {
        $this->requireAdmin();
        Envelope::noStore();

        try {
            $result = WebhookEventLog::listing($_GET);
        } catch (InvalidArgumentException $e) {
            Envelope::invalid($e->getMessage());
        }

        Envelope::ok($result);
    }

    public function show(): never
    {
        $this->requireAdmin();
        Envelope::noStore();

        $row = WebhookEventLog::find((string) $this->param('id'));
        if (!$row) {
            Envelope::notFound('NOT_FOUND', 'Webhook event not found.');
        }

        $row['canReplay'] = WebhookEventLog::replayReason($row) === '';
        $row['replayReason'] = WebhookEventLog::replayReason($row);

        Envelope::ok($row);
    }

    public function replay(): never
    {
        $this->requireAdmin();
        if (!ApiRequest::bearerToken() && !Csrf::validate()) {
            Envelope::forbidden('Invalid request token. Reload the page and try again.');
        }

        try {
            $result = WebhookEventLog::replay((string) $this->param('id'));
        } catch (OutOfBoundsException $e) {
            Envelope::notFound('NOT_FOUND', $e->getMessage());
        } catch (DomainException $e) {
            Envelope::fail('REPLAY_UNAVAILABLE', $e->getMessage(), 409);
        }

        Envelope::ok($result);
    }
}
