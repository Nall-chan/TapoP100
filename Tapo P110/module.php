<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/Tapo P100/module.php';

/**
 * TapoP110 Klasse für die Anbindung von TP-Link tapo P100 / P110 Smart Sockets.
 * Erweitert TapoP100.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.50
 *
 * @example <b>Ohne</b>
 *
 */
class TapoP110 extends TapoP100
{
    public function ApplyChanges()
    {
        //Never delete this line!
        $this->RegisterProfileInteger(\TpLink\VariableProfile::Runtime, '', '', ' minutes', 0, 0, 0);
        $this->RegisterVariableString(\TpLink\VariableIdent::today_runtime, $this->Translate('Runtime today'));
        $this->RegisterVariableString(\TpLink\VariableIdent::month_runtime, $this->Translate('Runtime month'));
        $this->RegisterVariableInteger(\TpLink\VariableIdent::today_runtime_raw, $this->Translate('Runtime today (minutes)'), \TpLink\VariableProfile::Runtime);
        $this->RegisterVariableInteger(\TpLink\VariableIdent::month_runtime_raw, $this->Translate('Runtime month (minutes)'), \TpLink\VariableProfile::Runtime);
        $this->RegisterVariableFloat(\TpLink\VariableIdent::today_energy, $this->Translate('Energy today'), '~Electricity.Wh');
        $this->RegisterVariableFloat(\TpLink\VariableIdent::month_energy, $this->Translate('Energy month'), '~Electricity.Wh');
        $this->RegisterVariableFloat(\TpLink\VariableIdent::current_power, $this->Translate('Current power'), '~Watt');
        parent::ApplyChanges();
    }

    public function RequestState()
    {
        if (parent::RequestState()) {
            $Result = $this->GetEnergyUsage();
            if (is_array($Result)) {
                $this->SetValue(\TpLink\VariableIdent::today_runtime_raw, $Result[\TpLink\VariableIdent::today_runtime]);
                $this->SetValue(\TpLink\VariableIdent::month_runtime_raw, $Result[\TpLink\VariableIdent::month_runtime]);
                $this->SetValue(\TpLink\VariableIdent::today_runtime, sprintf(gmdate('H \%\s i \%\s', $Result[\TpLink\VariableIdent::today_runtime] * 60), $this->Translate('hours'), $this->Translate('minutes')));
                $this->SetValue(\TpLink\VariableIdent::month_runtime, sprintf(gmdate('z \%\s H \%\s i \%\s', $Result[\TpLink\VariableIdent::month_runtime] * 60), $this->Translate('days'), $this->Translate('hours'), $this->Translate('minutes')));
                $this->SetValue(\TpLink\VariableIdent::today_energy, $Result[\TpLink\VariableIdent::today_energy]);
                $this->SetValue(\TpLink\VariableIdent::month_energy, $Result[\TpLink\VariableIdent::month_energy]);
                $this->SetValue(\TpLink\VariableIdent::current_power, ($Result[\TpLink\VariableIdent::current_power] / 1000));
                return true;
            }
        }
        return false;
    }

    public function GetEnergyUsage()
    {
        $Request = json_encode([
            'method'         => 'get_energy_usage',
            'requestTimeMils'=> 0
        ]);
        $this->SendDebug(__FUNCTION__, $Request, 0);
        $Response = $this->SendRequest($Request);
        if ($Response === '') {
            return false;
        }
        $json = json_decode($Response, true);
        if ($json['error_code'] != 0) {
            trigger_error(\TpLink\Api\Protocol::$ErrorCodes[$json['error_code']], E_USER_NOTICE);
            return false;
        }
        return $json['result'];
    }
}
