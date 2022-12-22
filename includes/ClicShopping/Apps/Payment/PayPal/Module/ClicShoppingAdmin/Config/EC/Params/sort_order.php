<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT

   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\EC\Params;

  class sort_order extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public ?int $sort_order = 1000;
    public $default = '0';
    public bool $app_configured = false;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_ec_sort_order_title');
      $this->description = $this->app->getDef('cfg_ec_sort_order_desc');
    }
  }
