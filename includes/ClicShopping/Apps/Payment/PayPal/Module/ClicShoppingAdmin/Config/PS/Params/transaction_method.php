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

  use ClicShopping\OM\HTML;

  class transaction_method extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = '1';
    public ?int $sort_order = 300;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_ps_transaction_method_title');
      $this->description = $this->app->getDef('cfg_ps_transaction_method_desc');
    }

    public function getInputField()
    {
      $value = $this->getInputValue();

      $input = HTML::radioField($this->key, '1', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_ps_transaction_method_sale') . '<br /> ';
      $input .= HTML::radioField($this->key, '0', $value, 'id="' . $this->key . '0" autocomplete="off"') . $this->app->getDef('cfg_ps_transaction_method_authorize') . '<br />';

      return $input;
    }
  }
