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

    /**
     * Hands one LIVE verification code to an admin — what the eye on the
     * delivery page calls.
     *
     * Deliberately its own endpoint rather than a field on the listing: a code
     * is only ever sent in response to a specific, audited request for that one
     * message, so the page can be left open, screen-shared or cached without
     * any code being in the payload. POST because it has a side effect (the
     * audit row), and never cached.
     */
    public function reveal(): never
    {
        $this->requireAdmin();
        if (!ApiRequest::bearerToken() && !Csrf::validate()) Envelope::forbidden('Invalid request token. Reload the page and try again.');
        Envelope::noStore();

        try {
            $result = MessageDeliveryLog::reveal((string) $this->param('channel'), (string) $this->param('id'), Ctx::principal());
        } catch (OutOfBoundsException $e) {
            Envelope::notFound('NOT_FOUND', $e->getMessage());
        } catch (DomainException $e) {
            Envelope::fail('REVEAL_UNAVAILABLE', $e->getMessage(), 409);
        }

        Envelope::ok([
            'code' => $result['code'],
            'challengeId' => $result['challengeId'],
            'audited' => true,
            'notice' => 'This reveal has been recorded against your admin account.',
        ]);
    }
}
