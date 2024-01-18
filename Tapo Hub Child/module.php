<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoHubChild Klasse für die Anbindung von TP-Link tapo Geräte welche Hubs benötigen.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 */
class TapoHubChild extends IPSModule
{
    public function Create()
    {
        $this->RegisterPropertyString(\TpLink\Property::DeviceId, '');
        $this->RegisterPropertyBoolean(\TpLink\Property::AutoRename, false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $DeviceId = $this->ReadPropertyString(\TpLink\Property::DeviceId);
        if ($DeviceId) {
            $this->SetReceiveDataFilter('.*"' . \TpLink\Property::DeviceId . '":"' . $DeviceId . '".*');
        } else {
            $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        }
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        $this->SendDebug('Event', $Data, 0);
        return '';
    }

    public function SendRequest(string $Method, array $Params = [])
    {
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'                       => \TpLink\GUID::ChildSendToHub,
                \TpLink\Api\Protocol::Method   => $Method,
                \TpLink\Property::DeviceId     => $this->ReadPropertyString(\TpLink\Property::DeviceId),
                \TpLink\Api\Protocol::Params   => $Params
            ]
        ));
        $this->SendDebug('Result', $Ret, 0);
    }
}
