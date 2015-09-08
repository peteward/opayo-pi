<?php namespace Academe\SagePayMsg\Message;

/**
 * The request for a session key.
 */

use Academe\SagePayMsg\Models\Auth;

class SessionKeyRequest extends AbstractRequest
{
    protected $resource_path = ['merchant-session-keys'];

    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * The vendorName goes into the request body.
     * The integrationKey and integrationPassword is used as HTTP Basic Auth credentials.
     */
    public function getAuth()
    {
        return $this->auth;
    }

    public function getIntegrationKey()
    {
        return $this->auth->getIntegrationKey();
    }

    public function getIntegrationPassword()
    {
        return $this->auth->getIntegrationPassword();
    }

    public function getBody()
    {
        return ['vendorName' => $this->auth->getVendorName()];
    }

    /**
     * The HTTP Basic Auth header, as an array.
     * Use this if your transport tool does not do "Basic Auth" out of the box.
     */
    public function getHeaders()
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->getIntegrationKey() . ':' . $this->getIntegrationPassword()),
        ];
    }
}
