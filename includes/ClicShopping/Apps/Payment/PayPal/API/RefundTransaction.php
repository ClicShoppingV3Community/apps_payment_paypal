<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT

   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal\API;

  class RefundTransaction extends \ClicShopping\Apps\Payment\PayPal\APIAbstract
  {
    public function execute(array $extra_params = null)
    {
      $params = [
        'USER' => $this->app->getApiCredentials($this->server, 'username'),
        'PWD' => $this->app->getApiCredentials($this->server, 'password'),
        'SIGNATURE' => $this->app->getApiCredentials($this->server, 'signature'),
        'METHOD' => 'RefundTransaction'
      ];

      if (!empty($extra_params)) {
        $params = array_merge($params, $extra_params);
      }

      $response = $this->getResult($params);

      return [
        'res' => $response,
        'success' => \in_array($response['ACK'], ['Success', 'SuccessWithWarning']),
        'req' => $params
      ];
    }
  }
