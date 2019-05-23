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

  class incontext_button_size extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = '2';
    public $sort_order = 220;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_ec_incontext_button_size_title');
      $this->description = $this->app->getDef('cfg_ec_incontext_button_size_desc');
    }

    public function getInputField()
    {
      $value = $this->getInputValue();

      $array_menu = array(array('id' => '1', 'text' => $this->app->getDef('cfg_ec_incontext_button_size_small')),
        array('id' => '2', 'text' => $this->app->getDef('cfg_ec_incontext_button_size_tiny')),
        array('id' => '3', 'text' => $this->app->getDef('cfg_ec_incontext_button_size_medium'))
      );


      $input = HTML::selectField($this->key, $array_menu, $value, $this->getInputValue());

      return $input;
    }
  }
