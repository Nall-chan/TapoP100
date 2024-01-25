<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoDevice.php';

/**
 * TapoMultiSockets Klasse für die Anbindung von WiFi Mehrfachsockets.
 * Erweitert \TpLink\Device.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.65
 */
class TapoMultiSockets extends \TpLink\Device
{
    protected static $ModuleIdents = [
        \TpLink\VariableIdent\OnOff,
        \TpLink\VariableIdent\Overheated,
        \TpLink\VariableIdent\Rssi,
        \TpLink\VariableIdent\Socket
    ];

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if ($this->GetStatus() == IS_ACTIVE) {
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetChildDeviceList);
            $Response = $this->SendRequest($Request);
            if ($Response !== null) {
                foreach ($Response[\TpLink\Api\Result::ChildList] as $ChildDevice) {
                    $ChildIDs[$ChildDevice[\TpLink\Api\Result::Position]] = $ChildDevice[\TpLink\Api\Result::DeviceID];
                }
                $this->ChildIDs = $ChildIDs;
            }
        }
    }

    public function RequestState()
    {
        if (parent::RequestState()) {
            foreach ($this->ChildIDs as $ChildID) {
                $Values = [
                    'device_id'  => $ChildID,
                    'requestData'=> \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo)
                ];
                $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, $this->terminalUUID, $Values);
                $Response = $this->SendRequest($Request);
                if ($Response === null) {
                    return false;
                }
                $this->SetVariables($Response[\TpLink\Api\Result::ResponseData][\TpLink\Api\Result]);
            }
            return true;
        }
        return false;
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(parent::GetConfigurationForm(), true);
        unset($Form['elements'][3]);
        return json_encode($Form);
    }

    public function SwitchModeSlot(int $Index, bool $State): bool
    {
        if (!array_key_exists($Index, $this->ChildIDs)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid index'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $Values[\TpLink\Api\Result::DeviceID] = $this->ChildIDs[$Index];
        $Values[\TpLink\VariableIdentOnOff::device_on] = $State;
        $Ident = 'Pos_' . $Index . '_' . \TpLink\VariableIdentOnOff::device_on;
        if ($this->SetDeviceInfo($Values)) {
            $this->SetValue($Ident, $State);
            return true;
        }
        return false;
    }

    public function SwitchModeSlotEx(int $Index, bool $State, int $Delay): bool
    {
        if (!array_key_exists($Index, $this->ChildIDs)) {
            set_error_handler([$this, 'ModulErrorHandler']);
            trigger_error($this->Translate('Invalid index'), E_USER_NOTICE);
            restore_error_handler();
            return false;
        }
        $ChildID = $this->ChildIDs[$Index];
        $Values[\TpLink\VariableIdentOnOff::device_on] = $State;

        $Params = [
            'delay'         => $Delay,
            'desired_states'=> [
                'on' => $State
            ],
            'enable'   => true
        ];
        $ChildRequestValues = [
            'device_id'  => $ChildID,
            'requestData'=> \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::CountdownRule, '', $Params)
        ];
        $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, $this->terminalUUID, $ChildRequestValues);
        $Response = $this->SendRequest($Request);
        if ($Response === null) {
            return false;
        }

        return true;
    }
}
