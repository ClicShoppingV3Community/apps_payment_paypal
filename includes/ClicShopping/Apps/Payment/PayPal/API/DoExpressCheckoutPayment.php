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

  class DoExpressCheckoutPayment extends \ClicShopping\Apps\Payment\PayPal\APIAbstract
  {
    public function execute(array $extra_params = null)
    {
      $params = [
        'METHOD' => 'DoExpressCheckoutPayment',
        'PAYMENTREQUEST_0_PAYMENTACTION' => ((CLICSHOPPING_APP_PAYPAL_EC_TRANSACTION_METHOD == '1') || !$this->app->hasCredentials('EC') ? 'Sale' : 'Authorization'),
        'BUTTONSOURCE' => $this->app->getIdentifier()
      ];

      if ($this->app->hasCredentials('EC')) {
        $params['USER'] = $this->app->getCredentials('EC', 'username');
        $params['PWD'] = $this->app->getCredentials('EC', 'password');
        $params['SIGNATURE'] = $this->app->getCredentials('EC', 'signature');
      } else {
        $params['SUBJECT'] = $this->app->getCredentials('EC', 'email');
      }

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
