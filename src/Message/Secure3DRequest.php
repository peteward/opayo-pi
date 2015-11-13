<?php namespace Academe\SagePayMsg\Message;

/**
 * The 3DSecure request sent to Sage Pay .
 */

use Exception;
use UnexpectedValueException;

use Academe\SagePayMsg\Helper;
use Academe\SagePayMsg\Model\Auth;

class Secure3DRequest extends AbstractRequest
{
    protected $auth;
    protected $paRes;
    protected $transactionId;

    protected $resource_path = ['{transactionId}', '3d-secure'];

    /**
     * @param string|Secure3DAcsResponse $paRes The PA Result returned by the user's bank (or their agent)
     * @param string $transactionId The ID that Sage Pay gave to the transaction in its intial reponse
     */
    public function __construct(Auth $auth, $paRes, $transactionId)
    {
        if ($paRes instanceof Secure3DAcsResponse) {
            $this->paRes = $paRes->getPaRes();
        } else {
            $this->paRes = $paRes;
        }

        $this->transactionId = $transactionId;
        $this->auth = $auth;
    }

    /**
     * @return array The components of the path
     */
    public function getResourcePath()
    {
        return array_replace($this->resource_path,
            array_fill_keys(
                array_keys($this->resource_path, '{transactionId}'),
                $this->getTransactionId()
            )
        );
    }

    public function getBody()
    {
        return [
            'paRes' => $this->getPaRes(),
        ];
    }

    public function getPaRes()
    {
        return $this->paRes;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function getAuth()
    {
        return $this->auth;
    }
}
