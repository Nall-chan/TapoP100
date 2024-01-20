<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoDevice.php';

/**
 * TapoHubIO Klasse für das anlagen von tapo hub childs.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 */
class TapoHubIO extends \TpLink\Device
{
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if ($this->GetStatus() == IS_ACTIVE) {
            $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetChildDeviceList);
            $Response = $this->SendRequest($Request);
            //$Response = json_decode(file_get_contents(dirname(__DIR__) . '/tests/childdeviceslist.json'), true)['result'];
            $ChildIDs = [];
            if ($Response !== null) {
                foreach ($Response[\TpLink\Api\Result::ChildList] as $ChildDevice) {
                    $ChildIDs[] = $ChildDevice[\TpLink\Api\Result::DeviceID];
                }
                $this->ChildIDs = $ChildIDs;
                $this->SendDebug('Childs', $ChildIDs, 0);
            }
        }
    }

    public function RequestState()
    {

        $Result = $this->GetDeviceInfo(); // Eigene Vars des Hub laden und an Child senden
        if ($Result) {
            // todo eigene Daten an Hub Device senden

            foreach ($this->ChildIDs as $ChildID) {
                $Values = [
                    'device_id'  => $ChildID,
                    'requestData'=> \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::GetDeviceInfo)
                ];
                $Request = \TpLink\Api\Protocol::BuildRequest(\TpLink\Api\Method::ControlChild, $this->terminalUUID, $Values);
                //$Response = $this->SendRequest($Request);
                $Response = json_decode(file_get_contents(dirname(__DIR__) . '/tests/get_info_' . $ChildID . '.json'), true)['result'];
                if ($Response === null) {
                    continue;
                }
                $Response = $Response[\TpLink\Api\Result::ResponseData];
                $this->SendDebug('Res', $Response, 0);
                if ($Response[\TpLink\Api\ErrorCode] != 0) {
                    set_error_handler([$this, 'ModulErrorHandler']);
                    trigger_error($this->Translate(\TpLink\Api\Protocol::$ErrorCodes[$Response[\TpLink\Api\ErrorCode]]), E_USER_NOTICE);
                    restore_error_handler();
                    continue;
                }
                $this->SendDataToChildren(json_encode(
                    [
                        'DataID'                       => \TpLink\GUID::HubSendToChild,
                        \TpLink\Api\Protocol::Method   => \TpLink\Api\Method::GetDeviceInfo,
                        \TpLink\Property::DeviceId     => $ChildID,
                        \TpLink\Api\Result             => $Response[\TpLink\Api\Result]
                    ]
                ));
            }
            return true;
        }
        return false;
    }

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        $this->SendDebug('Forward', $Data, 0);
        /*
        if ($Data[\TpLink\Api\Protocol::Method] == \TpLink\Api\Method::GetChildDeviceList) {
            return serialize(json_decode(file_get_contents(dirname(__DIR__) . '/tests/childdeviceslist.json'), true)['result']);
        }
        if ($Data[\TpLink\Api\Protocol::Method] == \TpLink\Api\Method::SetDeviceInfo) {
            if ($Data[\TpLink\Property::DeviceId] != '') {
                return serialize(json_decode('{"result":{"responseData":{"error_code":0}},"error_code":0}', true)['result'][\TpLink\Api\Result::ResponseData]);
            }
            return serialize(json_decode('{"result":{"responseData":{"error_code":0}},"error_code":0}', true)['result']);
        }
         */
        $Method = $Data[\TpLink\Api\Protocol::Method];
        $Params = $Data[\TpLink\Api\Protocol::Params];
        $ChildID = $Data[\TpLink\Property::DeviceId];
        if ($ChildID != '') {
            $Params = [
                'device_id'  => $ChildID,
                'requestData'=> \TpLink\Api\Protocol::BuildRequest($Method, '', $Params)
            ];
            $Method = \TpLink\Api\Method::ControlChild;
        }
        $Request = \TpLink\Api\Protocol::BuildRequest($Method, $this->terminalUUID, $Params);
        $Response = $this->SendRequest($Request);
        if ($Response !== null) {
            if ($ChildID != '') {
                $Response = $Response[\TpLink\Api\Result::ResponseData];
            }
        }
        return serialize($Response);
    }
}
