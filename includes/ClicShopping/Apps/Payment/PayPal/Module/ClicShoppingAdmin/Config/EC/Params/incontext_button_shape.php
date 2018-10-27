<?php
/**
 *
 *  @copyright 2008 - https://www.clicshopping.org
 *  @Brand : ClicShopping(Tm) at Inpi all right Reserved
 *  @Licence GPL 2 & MIT
 *  @licence MIT - Portion of osCommerce 2.4
 *
 *
 */

namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\EC\Params;

use ClicShopping\OM\HTML;

class incontext_button_shape extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
    public $default = '1';
    public $sort_order = 230;

    protected function init()
    {
        $this->title = $this->app->getDef('cfg_ec_incontext_button_shape_title');
        $this->description = $this->app->getDef('cfg_ec_incontext_button_shape_desc');
    }

    public function getInputField()
    {
        $value = $this->getInputValue();

      $input =  HTML::radioField($this->key, '1', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_ec_incontext_button_shape_pill') . '<br /> ';
      $input .=  HTML::radioField($this->key, '2', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_ec_incontext_button_shape_rect') . '<br />';

        return $input;
    }
}
