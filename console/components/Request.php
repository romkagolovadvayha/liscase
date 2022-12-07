<?php

namespace console\components;

use Yii;

class Request extends \yii\console\Request
{

    /**
     * Returns the user IP address.
     * The IP is determined using headers and / or `$_SERVER` variables.
     * @return string|null user IP address, null if not available
     */
    public function getUserIP()
    {
        return $this->getRemoteIP() ;
    }

    /**
     * Returns the IP on the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote IP address, `null` if not available.
     * @since 2.0.13
     */
    public function getRemoteIP()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
     * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
     * The value returned is turned into upper case.
     */
    public function getMethod()
    {
        return 'GET';
    }

    public function validateCsrfToken($clientSuppliedToken = null)
    {
        return true;
    }
}
