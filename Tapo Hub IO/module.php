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
                // todo
                //$this->SendDataToChildren
                //($Response[\TpLink\Api\Result::ResponseData][\TpLink\Api\Result]);
            }
            return true;
        }
        return false;
    }

    public function ForwardData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        $this->SendDebug('Forward', $Data, 0);
        // todo
        // childs an Configurator ausliefern
        return '';
    }
}
