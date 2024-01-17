<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoDevice.php';

/**
 * TapoSocket Klasse für die Anbindung von TP-Link tapo Smarte WiFi Sockets.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 */
class TapoSocket extends \TpLink\Device
{
    protected static $ModuleIdents = [
        '\TpLink\VariableIdent'
    ];

    public function ApplyChanges()
    {
        // Migrate Old 'State' Var to 'device_on' Var
        $oldVar = @$this->GetIDForIdent('State');
        if (IPS_VariableExists($oldVar)) {
            IPS_SetIdent($oldVar, \TpLink\VariableIdent::device_on);
        }

        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterVariableBoolean(\TpLink\VariableIdent::device_on, $this->Translate(\TpLink\VariableIdent::$Variables[\TpLink\VariableIdent::device_on][\TpLink\IPSVarName]), '~Switch');
        $this->EnableAction(\TpLink\VariableIdent::device_on);
    }

    public function SwitchMode(bool $State): bool
    {
        if ($this->SetDeviceInfo([\TpLink\VariableIdent::device_on => $State])) {
            $this->SetValue(\TpLink\VariableIdent::device_on, $State);
            return true;
        }
        return false;
    }

    public function SwitchModeEx(bool $State, int $Delay): bool
    {
        $Params = [
            'delay'         => $Delay,
            'desired_states'=> [
                'on' => $State
            ],
            'enable'   => true
        ];
        $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::CountdownRule, $this->terminalUUID, $Params);

        $Response = $this->SendRequest($Request);
        if ($Response === null) {
            return false;
        }
        //$this->SetValue(\TpLink\VariableIdent::device_on, $State);
        return true;
    }
}
