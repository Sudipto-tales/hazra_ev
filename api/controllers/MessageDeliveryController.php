<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/MessageDeliveryLog.php';
require_once __BASEDIR__ . '/core/Csrf.php';

final class MessageDeliveryController extends V1Controller
{
    public function index(): never
    {
        $this->requireAdmin();
        Envelope::noStore();
        try { $result = MessageDeliveryLog::listing($_GET); }
        catch (InvalidArgumentException $e) { Envelope::invalid($e->getMessage()); }
        Envelope::ok($result);
    }

    public function retry(): never
    {
        $this->requireAdmin();
        if (!ApiRequest::bearerToken() && !Csrf::validate()) Envelope::forbidden('Invalid request token. Reload the page and try again.');
        try { $result = MessageDeliveryLog::retry((string) $this->param('channel'), (string) $this->param('id')); }
        catch (OutOfBoundsException $e) { Envelope::notFound('NOT_FOUND', $e->getMessage()); }
        catch (DomainException $e) { Envelope::fail('RETRY_UNAVAILABLE', $e->getMessage(), 409); }
        if (!$result['success']) Envelope::fail('RETRY_FAILED', $result['message'], 400);
        Envelope::ok($result);
    }
}
