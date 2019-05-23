<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @licence MIT - Portion of osCommerce 2.4
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\EC\Params;

  use ClicShopping\OM\HTML;

  class checkout_image extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = 'Static';
    public $sort_order = 500;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_ec_checkout_image_title');
      $this->description = $this->app->getDef('cfg_ec_checkout_image_desc');
    }

    public function getInputField()
    {
      $value = $this->getInputValue();

      $input = HTML::radioField($this->key, 'Static', $value, 'id="' . $this->key . '0" autocomplete="off"') . $this->app->getDef('cfg_ec_checkout_image_static') . '<br /> ';
      $input .= HTML::radioField($this->key, 'Dynamic', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_ec_checkout_image_dynamic') . '<br />';

      return $input;
    }
  }
