<?php

/**
 * One provider's send path.
 *
 * A transport owns the provider request and nothing else: the chain, the
 * cooldowns, the attempt rows and the retry rules all live above it, in
 * MessagingRouter and MessageDeliveryLog. That split is why a new provider is a
 * single file.
 *
 * send() must not throw — a provider that cannot be reached is a failed
 * attempt, not a 500 on a customer's warranty form. Returning
 * ['success' => false, ...] lets the router move to the next step; an exception
 * would abort the chain.
 */
interface MessagingTransport
{
    /**
     * @param array $message {
     *   event: string, recipient: string, reference: string, attemptGroup: string,
     *   position: int, isTest: bool, params: list<string>, code: ?string, payload: array
     * }
     * @return array{success: bool, id?: string, status?: string, provider_id?: ?string,
     *               error_code?: ?string, error_detail?: ?string, message?: string}
     */
    public function send(array $message): array;
}
