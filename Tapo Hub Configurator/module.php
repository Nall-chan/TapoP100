<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/TapoLib.php';

/**
 * TapoHubConfigurator Klasse für das anlagen von tapo hub childs.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2024 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.60
 */
class TapoHubConfigurator extends IPSModule
{
    public function ApplyChanges()
    {
        $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        //Never delete this line!
        parent::ApplyChanges();
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

    public function GetConfigurationForm()
    {
        $Form = json_decode(parent::GetConfigurationForm(), true);
        // todo
        // add Values
        return json_encode($Form);
    }
}
