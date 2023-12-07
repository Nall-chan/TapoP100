<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoP100 Klasse für die Anbindung von TP-Link tapo P100 / P110 Smart Sockets.
 * Erweitert IPSModule.
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
class TapoP100 extends \TpLink\Device
{
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->RegisterVariableBoolean(\TpLink\VariableIdent::State, $this->Translate(\TpLink\VariableIdent::State), '~Switch');
        $this->EnableAction(\TpLink\VariableIdent::State);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case \TpLink\VariableIdent::State:
                $this->SwitchMode((bool) $Value);
                return;
        }
    }

    public function RequestState()
    {
        $Result = $this->GetDeviceInfo();
        if (is_array($Result)) {
            $this->SetValue(\TpLink\VariableIdent::State, $Result[\TpLink\Api\Result::DeviceOn]);
            return true;
        }
        return false;
    }

    public function SwitchMode(bool $State): bool
    {
        $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::SetDeviceInfo, $this->terminalUUID, [\TpLink\Api\Param::DeviceOn => $State]);
        $this->SendDebug(__FUNCTION__, $Request, 0);
        $Response = $this->SendRequest($Request);
        if ($Response === '') {
            return false;
        }
        $json = json_decode($Response, true);
        if ($json[\TpLink\Api\ErrorCode] != 0) {
            trigger_error(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]], E_USER_NOTICE);
            return false;
        }
        $this->SetValue(\TpLink\VariableIdent::State, $State);
        return true;
    }

    public function SwitchModeEx(bool $State, int $Delay): bool
    {
        $Params = [
            'delay'         => $Delay,
            'desired_states'=> [
                'on' => $State
            ],
            'enable'   => true,
            'remain'   => $Delay
        ];
        $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::CountdownRule, $this->terminalUUID, $Params);

        $this->SendDebug(__FUNCTION__, $Request, 0);
        $Response = $this->SendRequest($Request);
        if ($Response === '') {
            return false;
        }
        $json = json_decode($Response, true);
        if ($json[\TpLink\Api\ErrorCode] != 0) {
            trigger_error(\TpLink\Api\Protocol::$ErrorCodes[$json[\TpLink\Api\ErrorCode]], E_USER_NOTICE);
            return false;
        }
        $this->SetValue(\TpLink\VariableIdent::State, $State);
        return true;
    }

    protected function SetStatus($Status)
    {
        if ($this->GetStatus() != $Status) {
            parent::SetStatus($Status);
            if ($Status == IS_ACTIVE) {
                $this->RequestState();
            }
        }
        return true;
    }
}
