<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT

   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\PS\Params;

  class ewp_paypal_cert extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public ?int $sort_order = 1100;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_ps_ewp_paypal_cert_title');
      $this->description = $this->app->getDef('cfg_ps_ewp_paypal_cert_desc');
    }
  }
