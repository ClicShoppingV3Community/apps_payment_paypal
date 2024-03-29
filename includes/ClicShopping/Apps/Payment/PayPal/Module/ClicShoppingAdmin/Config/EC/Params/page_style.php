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

  class page_style extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public ?int $sort_order = 600;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_ec_page_style_title');
      $this->description = $this->app->getDef('cfg_ec_page_style_desc');
    }
  }
