<?php namespace Academe\SagePayMsg\Model;

/**
 * Value object given the account authentication details.
 * Provides the as needed, and the correct base URL.
 */

use Exception;
use UnexpectedValueException;

class Auth
{
    /**
     * @var
     */
    protected $vendorName;
    protected $integrationKey;
    protected $integrationPassword;
    protected $mode;

    /**
     * This release is locked onto just one API version.
     * It is likely beta will remain v1 for its entire lifetime.
     */
    const API_VERSION = 'v1';

    /**
     * Modes of operation.
     */
    const MODE_LIVE = 1;
    const MODE_TEST = 2;

    /**
     * @var array The endpoint URLs, one for each mode.
     */
    protected $urls = [
        1 => 'https://www.sagepay.com/api/{version}{resource}',
        2 => 'https://test.sagepay.com/api/{version}{resource}',
    ];

    /**
     * @param string $vendorName The vendor name supplied by Sage Pay owning the API account
     * @param string $integrationKey The integration key generated for the merchant site
     * @param string $integrationPassword The integration password generated for the merchant site
     * @param int $mode The mode of operation
     */
    public function __construct(
        $vendorName,
        $integrationKey,
        $integrationPassword,
        $mode = self::MODE_LIVE
    ) {
        $this->vendorName = $vendorName;
        $this->integrationKey = $integrationKey;
        $this->integrationPassword = $integrationPassword;

        // The mode - testing or production. Possible others later.
        if ( ! isset($this->urls[$mode])) {
            throw new UnexpectedValueException(sprintf('Unexpected mode value "%s"', $mode));
        }

        $this->mode = $mode;
    }

    /**
     * @return string The vendor name
     */
    public function getVendorName()
    {
        return $this->vendorName;
    }

    /**
     * @return string The integration key
     */
    public function getIntegrationKey()
    {
        return $this->integrationKey;
    }

    /**
     * @return string The integration password
     */
    public function getIntegrationPassword()
    {
        return $this->integrationPassword;
    }

    /**
     * @return string The API version
     */
    public function getApiVersion()
    {
        return static::API_VERSION;
    }

    /**
     * Get the endpoint URL.
     * A resource can be supplied as a string or array (if it has multiple path parts).
     * String resources must include a "/" prefix and be ready-encoded for the URL.
     * A resource as an array should not have directory separators included, and will
     * be url encoded here, so should not be done in advance.
     *
     * @param string $resource The name of the resource
     *
     * @return string The absolute endpoint URL
     */
    public function getUrl($resource = '')
    {
        // If the resource is an array, then combine it into the path.
        if (is_array($resource)) {
            // Encode all parts of the path.
            $resource = '/' . implode('/', array_map('rawurlencode', $resource));
        } else {
            if ($resource !== '' && strpos('/', $resource) !== 0) {
                $resource = '/' . $resource;
            }
        }

        return str_replace(
            ['{version}', '{resource}'],
            [$this->getApiVersion(), $resource],
            $this->urls[$this->mode]
        );
    }

    /**
     * Get the URL of sagepay.js - the card token generator for the front end.
     *
     * @return string The URL to the JavaScript front end resource on the Sage Pay gateway
     */
    public function getJavascriptUrl()
    {
        return $this->getUrl(['js', 'sagepay.js']);
    }

    /**
     * Return a testing instance (since it was an optional setting on first instantiation).
     *
     * @return Auth A clone of $this with test mode set
     */
    public function withTestingMode()
    {
        $copy = clone $this;
        $copy->mode = static::MODE_TEST;
        return $copy;
    }

    /**
     * Indicates whether we are using a test account.
     *
     * @return bool True if we are in testing mode, otherwise False
     */
    public function isTesting()
    {
        return $this->mode == static::MODE_TEST;
    }

    /**
     * Override any of the URLs.
     * Supports replacement fields {version} and {resource}
     *
     * @param $mode The mode to set the endpoint URL for
     * @param $url The absolute URL or URL template with placeholders
     *
     * @return Auth A clone of $this with the new URL or URL template set
     */
    public function withUrl($mode, $url)
    {
        if ( ! isset($this->urls[$mode])) {
            throw new UnexpectedValueException(sprintf('Unexpected mode value "%s"', $mode));
        }

        $copy = clone $this;
        $copy->urls[$mode] = $url;
        return $copy;
    }
}
