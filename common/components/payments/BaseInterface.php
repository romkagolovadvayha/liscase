<?php

namespace common\components\payments;

use Yii;

interface BaseInterface
{
    /**
     * @param      $amount
     *
     * @return mixed
     */
    public function create($amount);

    /**
     * @param      $paymentId
     *
     * @return mixed
     */
    public function check($paymentId);

    /**
     * @param      $paymentId
     *
     * @return mixed
     */
    public function debugCheck($paymentId);

}
