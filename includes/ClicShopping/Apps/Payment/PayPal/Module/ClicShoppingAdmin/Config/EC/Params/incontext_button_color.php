<?php
/**
 *
 *  @copyright 2008 - https://www.clicshopping.org
 *  @Brand : ClicShopping(Tm) at Inpi all right Reserved
 *  @Licence GPL 2 & MIT
 *  @licence MIT - Portion of osCommerce 2.4
 *  @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\EC\Params;

use ClicShopping\OM\HTML;

class incontext_button_color extends \ClicShopping\Apps\Payment\PayPal\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
    public $default = '1';
    public $sort_order = 210;

    protected function init()
    {
        $this->title = $this->app->getDef('cfg_ec_incontext_button_color_title');
        $this->description = $this->app->getDef('cfg_ec_incontext_button_color_desc');
    }

    public function getInputField()
    {
        $value = $this->getInputValue();

        $array_menu = array(array('id' => '1',  'text' =>  $this->app->getDef('cfg_ec_incontext_button_color_gold')),
                            array('id' => '2',  'text' =>  $this->app->getDef('cfg_ec_incontext_button_color_blue')),
                            array('id' => '3',  'text' =>  $this->app->getDef('cfg_ec_incontext_button_color_silver'))
        );

      $input = HTML::selectField($this->key, $array_menu, $value, $this->getInputValue());

        return $input;
    }
}
