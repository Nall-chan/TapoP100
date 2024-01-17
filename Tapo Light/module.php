<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoDevice.php';

/**
 * TapoLightlb Klasse für die Anbindung von TP-Link tapo WiFi Bulbs & Strips.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 */
class TapoLight extends \TpLink\Device
{
    protected static $ModuleIdents = [
        '\TpLink\VariableIdent',
        '\TpLink\VariableIdentLight'
    ];

    public function ApplyChanges()
    {
        $this->RegisterProfileInteger(\TpLink\VariableProfile::Brightness, 'Intensity', '', '%', 1, 100, 1);
        //Never delete this line!
        parent::ApplyChanges();
    }
}
